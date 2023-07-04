<?php

namespace Softonic\TransactionalEventPublisher\EventStoreMiddlewares;

use Exception;
use Illuminate\Support\Facades\DB;
use Softonic\TransactionalEventPublisher\Contracts\EventMessageContract;
use Softonic\TransactionalEventPublisher\Contracts\EventStoreMiddlewareContract;
use Softonic\TransactionalEventPublisher\Models\DomainEvent;

class DatabaseMiddleware implements EventStoreMiddlewareContract
{
    /**
     * Stores the messages in database.
     */
    public function store(EventMessageContract ...$messages): bool
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
        } catch (Exception $e) {
            DB::rollBack();

            return false;
        }
    }
}
