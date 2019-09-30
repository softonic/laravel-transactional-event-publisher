<?php

namespace Softonic\TransactionalEventPublisher\EventStoreMiddlewares;

use Exception;
use Softonic\TransactionalEventPublisher\Contracts\EventMessageContract;
use Softonic\TransactionalEventPublisher\Contracts\EventStoreMiddlewareContract;
use Softonic\TransactionalEventPublisher\Model\DomainEvent;

/**
 * Class DatabaseMiddleware
 *
 * @package Softonic\TransactionalEventPublisher\EventStoreMiddlewares
 */
class DatabaseMiddleware implements EventStoreMiddlewareContract
{
    /**
     * Store the messages in database.
     *
     * @param EventMessageContract $message
     *
     * @return bool
     */
    public function store(EventMessageContract $message)
    {
        try {
            DomainEvent::create(compact('message'));

            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
