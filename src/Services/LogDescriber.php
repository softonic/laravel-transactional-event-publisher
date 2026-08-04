<?php

declare(strict_types=1);

namespace Softonic\TransactionalEventPublisher\Services;

use Illuminate\Support\Str;
use Softonic\TransactionalEventPublisher\Interfaces\EventMessageInterface;
use Throwable;

/**
 * Builds the descriptions used when logging an event handling failure.
 *
 * Both descriptions exist for the same reason: a failure has to be diagnosable from the logs
 * without dumping payload sized content into them.
 */
class LogDescriber
{
    /**
     * Message fields logged to identify an event.
     *
     * The payload is deliberately left out because it is usually what makes the handling fail and
     * it is too big to be logged, so only the size of the serialized message is reported instead.
     */
    private const array IDENTIFYING_FIELDS = [
        'service'   => true,
        'eventType' => true,
        'modelName' => true,
        'eventName' => true,
        'createdAt' => true,
    ];

    /**
     * Max length logged for an exception message.
     *
     * A QueryException message embeds the whole query with its bindings, so it carries the
     * serialized message that is too big to be logged. Everything needed to diagnose the failure
     * (the SQLSTATE and the driver error) comes before that.
     */
    private const int MAX_EXCEPTION_MESSAGE_LENGTH = 500;

    /**
     * Describes an event message without exposing its payload.
     *
     * @return array<string, mixed>
     */
    public static function describeMessage(EventMessageInterface $message): array
    {
        return array_intersect_key($message->toArray(), self::IDENTIFYING_FIELDS);
    }

    /**
     * Describes an exception keeping its message within a size that is safe to log.
     *
     * @return array{exception: string, error: string}
     */
    public static function describeException(Throwable $e): array
    {
        return [
            'exception' => $e::class,
            'error'     => Str::limit($e->getMessage(), self::MAX_EXCEPTION_MESSAGE_LENGTH),
        ];
    }
}
