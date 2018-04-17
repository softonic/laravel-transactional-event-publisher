<?php

namespace Softonic\TransactionalEventPublisher\Jobs;

use Illuminate\Bus\Queueable;
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
     * Create a new job instance.
     *
     * @param EventMessageContract $eventMessage
     */
    public function __construct(EventMessageContract $eventMessage)
    {
        $this->eventMessage = $eventMessage;
        $this->onConnection('database')
            ->onQueue('domainEvents');
    }

    /**
     * Execute the job.
     *
     */
    public function handle(AmqpMiddleware $amqpMiddleware, LoggerInterface $logger)
    {
        if (!$amqpMiddleware->store($this->eventMessage)) {
            $errorMessage = "The event could't be sent. Retrying message: " . json_encode($this->eventMessage);
            $logger->alert($errorMessage);
            throw new \RuntimeException($errorMessage);
        }
    }
}
