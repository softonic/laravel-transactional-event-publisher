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
    ) {
    }

    public function toArray(): array
    {
        return [
            'service'   => $this->service,
            'eventType' => $this->eventType,
            'modelName' => $this->modelName,
            'eventName' => $this->eventName,
            'payload'   => $this->payload,
            'createdAt' => $this->createdAt,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
