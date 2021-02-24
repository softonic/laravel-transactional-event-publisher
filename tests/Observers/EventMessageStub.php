<?php

namespace Softonic\TransactionalEventPublisher\Observers;

use Illuminate\Database\Eloquent\Model;
use Softonic\TransactionalEventPublisher\Contracts\EventMessageContract;

class EventMessageStub implements EventMessageContract
{
    public function __construct(Model $model, $eventType)
    {
    }

    public function toArray()
    {
        return [
        ];
    }

    public function jsonSerialize()
    {
        return $this->toArray();
    }
}
