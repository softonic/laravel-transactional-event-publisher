<?php

namespace Softonic\TransactionalEventPublisher\EventStoreMiddlewares;

use Exception;
use Illuminate\Contracts\Bus\Dispatcher;
use Softonic\TransactionalEventPublisher\Contracts\EventMessageContract;
use Softonic\TransactionalEventPublisher\Contracts\EventStoreMiddlewareContract;
use Softonic\TransactionalEventPublisher\Jobs\SendDomainEvents;

class AsyncMiddleware implements EventStoreMiddlewareContract
{
    private EventStoreMiddlewareContract $eventPublisherMiddleware;

    private Dispatcher $dispatcher;

    public function __construct(EventStoreMiddlewareContract $eventPublisherMiddleware, Dispatcher $dispatcher)
    {
        $this->eventPublisherMiddleware = $eventPublisherMiddleware;
        $this->dispatcher               = $dispatcher;
    }

    /**
     * Stores in the message-oriented middleware.
     *
     * @param EventMessageContract $messages
     *
     * @return mixed
     */
    public function store(EventMessageContract ...$messages)
    {
        try {
            $job = new SendDomainEvents($this->eventPublisherMiddleware, 0, ...$messages);

            $this->dispatcher->dispatch($job);

            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
