<?php

namespace Softonic\TransactionalEventPublisher\EventStoreMiddlewares;

use Exception;
use Illuminate\Support\Facades\DB;
use Softonic\TransactionalEventPublisher\Interfaces\EventMessageInterface;
use Softonic\TransactionalEventPublisher\Interfaces\EventStoreMiddlewareInterface;
use Softonic\TransactionalEventPublisher\Models\DomainEvent;

class DatabaseMiddleware implements EventStoreMiddlewareInterface
{
    /**
     * Stores the messages in database.
     */
    public function store(EventMessageInterface ...$messages): bool
    {
        try {
            $inserts = [];
            foreach ($messages as $message) {
                $inserts[] = ['message' => serialize(clone $message)];
            }

            DB::beginTransaction();
            DomainEvent::insert($inserts);
            DB::commit();

            return true;
        } catch (Exception) {
            DB::rollBack();

            return false;
        }
    }
}
