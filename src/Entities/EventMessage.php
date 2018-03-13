<?php

namespace Softonic\TransactionalEventPublisher\Entities;

class EventMessage implements \JsonSerializable
{
    public $service;

    public $eventType;

    public $modelName;

    public $eventName;

    public $createdAt;

    public $payload;

    public $meta;

    /**
     * @return string
     */
    public function __toString()
    {
        return serialize($this->toArray());
    }

    public function jsonSerialize()
    {
        return $this->toArray();
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return [
            'service' => $this->service,
            'eventName' => $this->eventName,
            'createdAt' => $this->createdAt,
            'payload' => $this->payload,
            'meta' => $this->meta,
        ];
    }
}
