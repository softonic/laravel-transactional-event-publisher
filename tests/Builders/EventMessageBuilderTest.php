<?php

namespace Softonic\TransactionalEventPublisher\Builders;

use Illuminate\Support\Carbon;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\Context\Context;
use PHPUnit\Framework\Attributes\Test;
use Softonic\TransactionalEventPublisher\TestCase;
use Softonic\TransactionalEventPublisher\TestModel;

class EventMessageBuilderTest extends TestCase
{
    #[Test]
    public function whenEventMessageIsBuilt(): void
    {
        config(['transactional-event-publisher.service' => ':service:']);

        $model = new TestModel();

        Carbon::setTestNow(Carbon::parse('2021-01-01 00:00:00'));

        $eventMessage = new EventMessageBuilder()->build($model, 'created');

        $this->assertEquals(':service:', $eventMessage->service);
        $this->assertEquals('created', $eventMessage->eventType);
        $this->assertEquals('TestModel', $eventMessage->modelName);
        $this->assertEquals('TestModelCreated', $eventMessage->eventName);
        $this->assertEquals([], $eventMessage->payload);
        $this->assertEquals('2021-01-01 00:00:00', $eventMessage->createdAt);
    }

    #[Test]
    public function whenThereIsNoTraceContextTheMessageIsUnchanged(): void
    {
        config(['transactional-event-publisher.service' => ':service:']);

        $eventMessage = new EventMessageBuilder()->build(new TestModel(), 'created');

        $this->assertSame([], $eventMessage->tracing);
        $this->assertArrayNotHasKey('tracing', $eventMessage->toArray());
    }

    #[Test]
    public function whenAProducingTraceIsActiveItTravelsWithTheMessage(): void
    {
        config(['transactional-event-publisher.service' => ':service:']);

        // The consumer is a different process, so the context has to survive inside
        // the message: the outbox publishes it long after this request is gone.
        $carrier = [
            'traceparent' => '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01',
        ];
        $scope = TraceContextPropagator::getInstance()
            ->extract($carrier)
            ->activate();

        try {
            $eventMessage = new EventMessageBuilder()->build(new TestModel(), 'created');
        } finally {
            $scope->detach();
        }

        $this->assertSame($carrier, $eventMessage->tracing);
        $this->assertSame($carrier, $eventMessage->toArray()['tracing']);
    }
}
