<?php

namespace Softonic\TransactionalEventPublisher\ValueObjects;

use Illuminate\Database\Eloquent\Model;
use Softonic\TransactionalEventPublisher\Contracts\EventMessageContract;

class EventMessage implements EventMessageContract
{
    public $service;

    public $eventType;

    public $modelName;

    public $eventName;

    public $createdAt;

    public $payload;

    public function __construct(Model $model, $eventType)
    {
        $this->service   = config('transactional-event-publisher.service');
        $this->eventType = $eventType;
        $this->modelName = class_basename($model);
        $this->eventName = $this->buildEventName($this->modelName, $eventType);
        $this->payload   = $model->toArray();
        $this->createdAt = date('Y-m-d H:i:s');
    }

    private function buildEventName($modelName, $event)
    {
        return $modelName . ucfirst($event);
    }

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
            'service'   => $this->service,
            'eventName' => $this->eventName,
            'createdAt' => $this->createdAt,
            'payload'   => $this->payload,
        ];
    }
}
