<?php

namespace Softonic\TransactionalEventPublisher\Contracts;

use Softonic\TransactionalEventPublisher\Entities\EventMessage;

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
     * @param EventMessage $message
     *
     * @return mixed
     */
    public function store(EventMessage $message);
}
