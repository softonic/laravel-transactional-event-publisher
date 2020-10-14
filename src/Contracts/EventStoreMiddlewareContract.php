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
     * @param EventMessageContract[] $messages
     *
     * @return mixed
     */
    public function store(EventMessageContract ...$messages);
}
