<?php

namespace Softonic\TransactionalEventPublisher\EventStoreMiddlewares;

use Exception;
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
     * @param EventMessageContract $messages
     *
     * @return mixed
     */
    public function store(EventMessageContract ...$messages)
    {
        try {
            $job = new SendDomainEvents(0, $messages);

            $this->dispatcher->dispatch($job);

            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
