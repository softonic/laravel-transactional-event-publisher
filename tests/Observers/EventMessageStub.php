<?php

namespace Softonic\TransactionalEventPublisher\Tests\Observers;

use Illuminate\Database\Eloquent\Model;
use Softonic\TransactionalEventPublisher\Contracts\EventMessageContract;

class EventMessageStub implements EventMessageContract
{
    private $payload;

    private $eventType;

    public function __construct(Model $model, $eventType)
    {
//        $this->payload = $model->toArray();
//        $this->eventType = $eventType;
    }

    public function toArray()
    {
        return [
//            'event_type' => $this->eventType,
//            'payload' => $this->payload,
        ];
    }

    public function jsonSerialize()
    {
        return $this->toArray();
    }
}
