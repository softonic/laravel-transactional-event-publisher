<?php

namespace Softonic\TransactionalEventPublisher\Jobs;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Softonic\TransactionalEventPublisher\Contracts\EventMessageContract;
use Softonic\TransactionalEventPublisher\EventStoreMiddlewares\AmqpMiddleware;

class SendDomainEvents implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const NO_RETRIES = 0;

    /**
     * @var EventMessageContract[]
     */
    private array $eventMessages;

    private int $retry;

    private Dispatcher $dispatcher;

    private AmqpMiddleware $amqpMiddleware;

    private LoggerInterface $logger;


    /**
     * Create a new job instance.
     *
     * @param int                  $retry
     * @param EventMessageContract $eventMessages
     */
    public function __construct(int $retry, EventMessageContract ...$eventMessages)
    {
        $this->eventMessages = $eventMessages;
        $this->retry         = $retry;

        $this->onConnection('database')
            ->onQueue('domainEvents');
    }

    /**
     * Execute the job.
     */
    public function handle(AmqpMiddleware $amqpMiddleware, Dispatcher $dispatcher, LoggerInterface $logger): void
    {
        $this->dispatcher     = $dispatcher;
        $this->amqpMiddleware = $amqpMiddleware;
        $this->logger         = $logger;

        try {
            $this->sendEvent();
        } catch (Exception $e) {
            $this->waitExponentialBackOff();
            $this->retry();
        }
    }

    protected function sendEvent(): void
    {
        if (!$this->amqpMiddleware->store(...$this->eventMessages)) {
            $errorMessage = "The event could't be sent. Retrying message: " . json_encode($this->eventMessages);
            $this->logger->alert($errorMessage);

            throw new RuntimeException($errorMessage);
        }
    }

    protected function waitExponentialBackOff(): void
    {
        $timeToWait = $this->retry < 18
            ? ++$this->retry ** 2
            : $this->retry ** 2;
        sleep($timeToWait);
    }

    protected function retry(): void
    {
        $job = (new static($this->retry, ...$this->eventMessages))
            ->onQueue('retryDomainEvent');

        $this->dispatcher->dispatch($job);
    }
}
