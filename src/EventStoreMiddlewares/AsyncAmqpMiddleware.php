<?php

namespace Softonic\TransactionalEventPublisher\EventStoreMiddlewares;

use Illuminate\Contracts\Bus\Dispatcher;
use Softonic\TransactionalEventPublisher\Contracts\EventMessageContract;
use Softonic\TransactionalEventPublisher\Contracts\EventStoreMiddlewareContract;
use Softonic\TransactionalEventPublisher\Jobs\SendDomainEvents;

class AsyncAmqpMiddleware implements EventStoreMiddlewareContract
{
    /**
     * @var Dispatcher
     */
    private $dispatcher;

    public function __construct(Dispatcher $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    /**
     * Stores in the message-oriented middleware.
     *
     * @param \Softonic\TransactionalEventPublisher\Contracts\EventMessageContract $message
     *
     * @return mixed
     */
    public function store(EventMessageContract $message)
    {
        try {
            $job = new SendDomainEvents($message);

            $this->dispatcher->dispatch($job);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
