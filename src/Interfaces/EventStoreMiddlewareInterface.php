<?php

declare(strict_types=1);

namespace Softonic\TransactionalEventPublisher\Interfaces;

interface EventStoreMiddlewareInterface
{
    /**
     * Stores in the message-oriented middleware.
     */
    public function store(EventMessageInterface ...$messages): bool;
}
