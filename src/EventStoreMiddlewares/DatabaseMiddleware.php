<?php

namespace Softonic\TransactionalEventPublisher\EventStoreMiddlewares;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Softonic\TransactionalEventPublisher\Interfaces\EventMessageInterface;
use Softonic\TransactionalEventPublisher\Interfaces\EventStoreMiddlewareInterface;
use Softonic\TransactionalEventPublisher\Models\DomainEvent;

class DatabaseMiddleware implements EventStoreMiddlewareInterface
{
    /**
     * Message fields logged to identify the event when storing it fails.
     *
     * The payload is deliberately left out because it is usually what makes the insert fail and it
     * is too big to be logged, so only the size of the serialized message is reported instead.
     */
    private const array IDENTIFYING_FIELDS = [
        'service'   => true,
        'eventType' => true,
        'modelName' => true,
        'eventName' => true,
        'createdAt' => true,
    ];

    /**
     * Max length logged for the exception message.
     *
     * A QueryException message embeds the whole query with its bindings, so it carries the
     * serialized message that is too big to be logged. Everything needed to diagnose the failure
     * (the SQLSTATE and the driver error) comes before that.
     */
    private const int MAX_LOGGED_EXCEPTION_MESSAGE_LENGTH = 500;

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
                'The event messages could not be stored in the domain_events table: '
                . Str::limit($e->getMessage(), self::MAX_LOGGED_EXCEPTION_MESSAGE_LENGTH),
                [
                    'exception' => $e::class,
                    'messages'  => $this->describeMessages($messages, $inserts),
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
            $description = array_intersect_key($message->toArray(), self::IDENTIFYING_FIELDS);
            $description['sizeInBytes'] = isset($inserts[$index]) ? strlen($inserts[$index]['message']) : null;

            $descriptions[] = $description;
        }

        return $descriptions;
    }
}
