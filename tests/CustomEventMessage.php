<?php

namespace Softonic\TransactionalEventPublisher;

use Illuminate\Database\Eloquent\Model;
use Softonic\TransactionalEventPublisher\Interfaces\EventMessageInterface;

class CustomEventMessage implements EventMessageInterface
{
    public function toArray(): array
    {
        return ['test'];
    }

    public function jsonSerialize()
    {
        return '["test"]';
    }
}
