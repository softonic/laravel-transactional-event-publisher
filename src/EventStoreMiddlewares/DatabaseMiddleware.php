<?php

namespace Softonic\TransactionalEventPublisher\EventStoreMiddlewares;

use Exception;
use Illuminate\Support\Facades\DB;
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
     * @param EventMessageContract[] $messages
     *
     * @return bool
     */
    public function store(EventMessageContract ...$messages)
    {
        try {
            foreach ($messages as $message) {
                DB::beginTransaction();
                DomainEvent::create(compact('message'));
            }
            DB::commit();

            return true;
        } catch (Exception $e) {
            DB::rollBack();

            return false;
        }
    }
}
