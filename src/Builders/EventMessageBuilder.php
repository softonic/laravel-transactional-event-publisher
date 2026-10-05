<?php

namespace Softonic\TransactionalEventPublisher\Builders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use OpenTelemetry\API\Globals;
use Softonic\TransactionalEventPublisher\Interfaces\EventMessageBuilderInterface;
use Softonic\TransactionalEventPublisher\Interfaces\EventMessageInterface;
use Softonic\TransactionalEventPublisher\ValueObjects\EventMessage;

class EventMessageBuilder implements EventMessageBuilderInterface
{
    public function build(Model $model, string $eventType): EventMessageInterface
    {
        $modelName = class_basename($model);

        // Captured here, while the producing request's span is still current.
        // Without an SDK configured the propagator is a no-op and this stays empty.
        $tracing = [];
        Globals::propagator()->inject($tracing);

        return new EventMessage(
            service: config('transactional-event-publisher.service'),
            eventType: $eventType,
            modelName: $modelName,
            eventName: $modelName . ucfirst($eventType),
            payload: $model->attributesToArray(),
            createdAt: Carbon::now()->toDateTimeString(),
            tracing: $tracing,
        );
    }
}
