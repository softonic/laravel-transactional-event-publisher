<?php

namespace Softonic\TransactionalEventPublisher\Builders;

use Illuminate\Support\Carbon;
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

        $eventMessage = (new EventMessageBuilder())->build($model, 'created');

        $this->assertEquals(':service:', $eventMessage->service);
        $this->assertEquals('created', $eventMessage->eventType);
        $this->assertEquals('TestModel', $eventMessage->modelName);
        $this->assertEquals('TestModelCreated', $eventMessage->eventName);
        $this->assertEquals([], $eventMessage->payload);
        $this->assertEquals('2021-01-01 00:00:00', $eventMessage->createdAt);
    }
}
