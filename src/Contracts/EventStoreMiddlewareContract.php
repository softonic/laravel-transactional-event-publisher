<?php

namespace Softonic\TransactionalEventPublisher\Contracts;

interface EventStoreMiddlewareContract
{
    /**
     * Stores in the message-oriented middleware.
     */
    public function store(EventMessageContract ...$messages): bool;
}
