<?php

declare(strict_types=1);

namespace Softonic\TransactionalEventPublisher\Fixtures;

use Override;
use Softonic\TransactionalEventPublisher\Interfaces\EventMessageInterface;

class FakeEventMessage implements EventMessageInterface
{
    public function __construct(
        public readonly string $modelName,
        public readonly mixed $modelKey,
        public readonly string $eventType,
    ) {
    }

    #[Override]
    public function toArray(): array
    {
        return [
            'modelName' => $this->modelName,
            'modelKey' => $this->modelKey,
            'eventType' => $this->eventType,
        ];
    }

    #[Override]
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
