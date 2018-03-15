<?php

namespace Softonic\TransactionalEventPublisher\Contracts;

/**
 * Interface EventStoreMiddlewareContract
 *
 * @package Softonic\TransactionalEventPublisher\Contracts
 */
interface EventStoreMiddlewareContract
{
    /**
     * Stores in the message-oriented middleware.
     *
     * @param \Softonic\TransactionalEventPublisher\Contracts\EventMessageContract $message
     *
     * @return mixed
     */
    public function store(EventMessageContract $message);
}
