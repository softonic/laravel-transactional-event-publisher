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
use Softonic\TransactionalEventPublisher\Contracts\EventStoreMiddlewareContract;

class SendDomainEvents implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public const NO_RETRIES = 0;

    /**
     * @var EventMessageContract[]
     */
    private array $eventMessages;

    private int $retry;

    private EventStoreMiddlewareContract $eventPublisherMiddleware;

    private Dispatcher $dispatcher;

    private LoggerInterface $logger;

    public function __construct(
        EventStoreMiddlewareContract $eventPublisherMiddleware,
        int $retry,
        EventMessageContract ...$eventMessages
    ) {
        $this->eventPublisherMiddleware = $eventPublisherMiddleware;
        $this->eventMessages            = $eventMessages;
        $this->retry                    = $retry;

        $this->onConnection('database')
            ->onQueue('domainEvents');
    }

    public function handle(Dispatcher $dispatcher, LoggerInterface $logger): void
    {
        $this->dispatcher = $dispatcher;
        $this->logger     = $logger;

        try {
            $this->sendEvent();
        } catch (Exception $e) {
            $this->waitExponentialBackOff();
            $this->retry();
        }
    }

    protected function sendEvent(): void
    {
        if (!$this->eventPublisherMiddleware->store(...$this->eventMessages)) {
            $errorMessage = "The event couldn't be sent. Retrying message: " . json_encode($this->eventMessages);
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
        $job = (new static($this->eventPublisherMiddleware, $this->retry, ...$this->eventMessages))
            ->onQueue('retryDomainEvent');

        $this->dispatcher->dispatch($job);
    }
}
