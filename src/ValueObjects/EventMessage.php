<?php

namespace Softonic\TransactionalEventPublisher\ValueObjects;

use Softonic\TransactionalEventPublisher\Interfaces\EventMessageInterface;

class EventMessage implements EventMessageInterface
{
    public function __construct(
        public readonly string $service,
        public readonly string $eventType,
        public readonly string $modelName,
        public readonly string $eventName,
        public readonly array $payload,
        public readonly string $createdAt,
        /**
         * Trace context of whoever produced the event, as a propagation carrier
         * (`traceparent` and friends). It travels inside the message because the
         * publisher is an outbox: the event is stored during the originating
         * request and published later by `EmitEvents`, a separate long-running
         * process whose own context is worthless to a consumer.
         */
        public readonly array $tracing = [],
    ) {
    }

    public function toArray(): array
    {
        $message = [
            'service'   => $this->service,
            'eventType' => $this->eventType,
            'modelName' => $this->modelName,
            'eventName' => $this->eventName,
            'payload'   => $this->payload,
            'createdAt' => $this->createdAt,
        ];

        // Omitted when empty, so consumers that do not know about it see the
        // message exactly as before.
        if ($this->tracing !== []) {
            $message['tracing'] = $this->tracing;
        }

        return $message;
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
