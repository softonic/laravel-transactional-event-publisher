<?php

declare(strict_types=1);

namespace Softonic\TransactionalEventPublisher;

use Softonic\TransactionalEventPublisher\Interfaces\EventMessageInterface;

class CustomEventMessage implements EventMessageInterface
{
    public function toArray(): array
    {
        return ['test'];
    }

    public function jsonSerialize(): mixed
    {
        return '["test"]';
    }
}
