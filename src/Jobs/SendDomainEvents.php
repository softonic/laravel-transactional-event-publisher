<?php

namespace Softonic\TransactionalEventPublisher\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Psr\Log\LoggerInterface;
use Softonic\TransactionalEventPublisher\Contracts\EventMessageContract;
use Softonic\TransactionalEventPublisher\EventStoreMiddlewares\AmqpMiddleware;

class SendDomainEvents implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var EventMessageContract
     */
    private $eventMessage;

    /**
     * @var integer $retry
     */
    private $retry = 0;

    /**
     * @var Dispatcher $dispatcher
     */
    private $dispatcher;

    /**
     * Create a new job instance.
     *
     * @param EventMessageContract $eventMessage
     */
    public function __construct(EventMessageContract $eventMessage, $retry = 0)
    {
        $this->eventMessage = $eventMessage;
        $this->retry        = $retry;

        $this->onConnection('database')
            ->onQueue('domainEvents');
    }

    /**
     * Execute the job.
     */
    public function handle(AmqpMiddleware $amqpMiddleware, Dispatcher $dispatcher, LoggerInterface $logger)
    {
        $this->dispatcher     = $dispatcher;
        $this->amqpMiddleware = $amqpMiddleware;
        $this->logger         = $logger;

        try {
            $this->sendEvent();
        } catch (\Exception $e) {
            $this->waitExponentialBackOff();
            $this->retry();
        }
    }

    protected function sendEvent(): void
    {
        if (!$this->amqpMiddleware->store($this->eventMessage)) {
            $errorMessage = "The event could't be sent. Retrying message: " . json_encode($this->eventMessage);
            $this->logger->alert($errorMessage);

            throw new \RuntimeException($errorMessage);
        }
    }

    protected function waitExponentialBackOff(): void
    {
        $timeToWait = $this->retry < 18
            ? pow(++$this->retry, 2)
            : pow($this->retry, 2);
        sleep($timeToWait);
    }

    protected function retry(): void
    {
        $job = (new static($this->eventMessage, $this->retry))
            ->onQueue('retryDomainEvent');

        $this->dispatcher->dispatch($job);
    }
}
