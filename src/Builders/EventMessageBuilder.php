<?php

namespace Softonic\TransactionalEventPublisher\Builders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Softonic\TransactionalEventPublisher\Interfaces\EventMessageBuilderInterface;
use Softonic\TransactionalEventPublisher\Interfaces\EventMessageInterface;
use Softonic\TransactionalEventPublisher\ValueObjects\EventMessage;

class EventMessageBuilder implements EventMessageBuilderInterface
{

    public function build(Model $model, string $eventType): EventMessageInterface
    {
        $modelName = class_basename($model);

        return new EventMessage(
            service: config('transactional-event-publisher.service'),
            eventType: $eventType,
            modelName: $modelName,
            eventName: $modelName . ucfirst($eventType),
            payload: $model->attributesToArray(),
            createdAt: Carbon::now()->toDateTimeString(),
        );
    }
}
