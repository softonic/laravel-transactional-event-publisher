<?php

namespace Softonic\TransactionalEventPublisher\EventStoreMiddlewares;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Softonic\TransactionalEventPublisher\Interfaces\EventMessageInterface;
use Softonic\TransactionalEventPublisher\Interfaces\EventStoreMiddlewareInterface;
use Softonic\TransactionalEventPublisher\Models\DomainEvent;
use Softonic\TransactionalEventPublisher\Services\LogDescriber;

class DatabaseMiddleware implements EventStoreMiddlewareInterface
{
    /**
     * Stores the messages in database.
     */
    public function store(EventMessageInterface ...$messages): bool
    {
        $inserts = [];

        try {
            foreach ($messages as $message) {
                $inserts[] = ['message' => serialize(clone $message)];
            }

            DB::beginTransaction();
            DomainEvent::insert($inserts);
            DB::commit();

            return true;
        } catch (Exception $e) {
            DB::rollBack();

            Log::error(
                'The event messages could not be stored in the domain_events table',
                [
                    ...LogDescriber::describeException($e),
                    'messages' => $this->describeMessages($messages, $inserts),
                ]
            );

            return false;
        }
    }

    /**
     * Describes the messages being stored so the failure can be diagnosed without logging their payload.
     *
     * @param EventMessageInterface[] $messages
     * @param array<int, array{message: string}> $inserts
     *
     * @return array<int, array<string, mixed>>
     */
    private function describeMessages(array $messages, array $inserts): array
    {
        $descriptions = [];
        foreach ($messages as $index => $message) {
            $description = LogDescriber::describeMessage($message);
            $description['sizeInBytes'] = isset($inserts[$index]) ? strlen($inserts[$index]['message']) : null;

            $descriptions[] = $description;
        }

        return $descriptions;
    }
}
