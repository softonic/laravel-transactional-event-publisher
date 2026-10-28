<?php

declare(strict_types=1);

namespace Softonic\TransactionalEventPublisher\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Override;
use Softonic\TransactionalEventPublisher\Interfaces\EventMessageBuilderInterface;
use Softonic\TransactionalEventPublisher\Interfaces\EventMessageInterface;
use Throwable;

class FakeEventMessageBuilder implements EventMessageBuilderInterface
{
    public ?Throwable $exceptionToThrow = null;

    public int $buildCalls = 0;

    #[Override]
    public function build(Model $model, string $eventType): EventMessageInterface
    {
        $this->buildCalls++;

        if ($this->exceptionToThrow instanceof Throwable) {
            throw $this->exceptionToThrow;
        }

        return new FakeEventMessage($model::class, $model->getKey(), $eventType);
    }
}
