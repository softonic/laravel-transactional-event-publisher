<?php

namespace Softonic\TransactionalEventPublisher\Console\Commands;

use Closure;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Log;
use Mockery;
use Override;
use phpmock\mockery\PHPMockery;
use PHPUnit\Framework\Attributes\Test;
use Softonic\TransactionalEventPublisher\EventStoreMiddlewares\AmqpMiddleware;
use Softonic\TransactionalEventPublisher\Interfaces\EventStoreMiddlewareInterface;
use Softonic\TransactionalEventPublisher\Models\DomainEvent;
use Softonic\TransactionalEventPublisher\ServiceProvider;
use Softonic\TransactionalEventPublisher\TestCase;

class EmitEventsTest extends TestCase
{
    use DatabaseTransactions;

    private readonly EventStoreMiddlewareInterface $eventPublisherMiddleware;

    private readonly EmitEvents $emitEvents;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->register(ServiceProvider::class);

        $this->loadMigrationsFrom(__DIR__ . '/../../../database/migrations');

        config()->set('transactional-event-publisher.event_publisher_middleware', AmqpMiddleware::class);

        $this->eventPublisherMiddleware = Mockery::mock(EventStoreMiddlewareInterface::class);
        $this->app->instance(EventStoreMiddlewareInterface::class, $this->eventPublisherMiddleware);

        $this->emitEvents = new EmitEvents();
        $this->emitEvents->eventPublisherMiddleware = $this->eventPublisherMiddleware;
        $this->emitEvents->dbConnection = 'testing';
        $this->emitEvents->dbConnectionUnbuffered = 'testing';
        $this->emitEvents->batchSize = 2;
    }

    #[Test]
    public function whenSendingABatchButThereAreNoEventsItShouldWaitAndDoNothing(): void
    {
        PHPMockery::mock(__NAMESPACE__, 'usleep')
            ->once()
            ->with(1000);

        $this->emitEvents->sendBatch();

        self::assertEquals(1, $this->emitEvents->attemptForErrors);
        self::assertEquals(2, $this->emitEvents->attemptForNoEvents);
    }

    #[Test]
    public function whenSendingABatchButThereAreNoEventsForFifthTimeItShouldWaitAndDoNothing(): void
    {
        $this->emitEvents->attemptForNoEvents = 5;

        PHPMockery::mock(__NAMESPACE__, 'usleep')
            ->once()
            ->with(16000);

        $this->emitEvents->sendBatch();

        self::assertEquals(1, $this->emitEvents->attemptForErrors);
        self::assertEquals(6, $this->emitEvents->attemptForNoEvents);
    }

    #[Test]
    public function whenSendingABatchAndThereIsOneEventItShouldPublishItAndDeletedIt(): void
    {
        $event = DomainEvent::factory()->create();

        $this->whenEventsArePublished(fn (...$eventMessages) => $event['message']->toArray() === $eventMessages[0]->toArray());
        $this->emitEvents->sendBatch();

        self::assertEquals(1, $this->emitEvents->attemptForErrors);
        self::assertEquals(1, $this->emitEvents->attemptForNoEvents);
        self::assertDatabaseMissing(DomainEvent::class, ['id' => $event['id']]);
    }

    #[Test]
    public function whenSendingABatchAfterThreeAttemptsWithErrorsItShouldPublishItDeleteItAndResetAttempts(): void
    {
        $this->emitEvents->attemptForErrors = 4;

        $event = DomainEvent::factory()->create();

        $this->whenEventsArePublished(fn (...$eventMessages) => $event['message']->toArray() === $eventMessages[0]->toArray());
        $this->emitEvents->sendBatch();

        self::assertEquals(1, $this->emitEvents->attemptForErrors);
        self::assertEquals(1, $this->emitEvents->attemptForNoEvents);
        self::assertDatabaseMissing(DomainEvent::class, ['id' => $event['id']]);
    }

    #[Test]
    public function whenSendingABatchAfterFiveAttemptsWithNoEventsItShouldPublishItDeleteItAndResetAttempts(): void
    {
        $this->emitEvents->attemptForNoEvents = 6;

        $event = DomainEvent::factory()->create();

        $this->whenEventsArePublished(fn (...$eventMessages) => $event['message']->toArray() === $eventMessages[0]->toArray());
        $this->emitEvents->sendBatch();

        self::assertEquals(1, $this->emitEvents->attemptForErrors);
        self::assertEquals(1, $this->emitEvents->attemptForNoEvents);
        self::assertDatabaseMissing(DomainEvent::class, ['id' => $event['id']]);
    }

    #[Test]
    public function whenSendingABatchAndThereAreSameEventsThanBatchSizeItShouldPublishThemAndDeleteThem(): void
    {
        $events = DomainEvent::factory(2)->create();

        $this->whenEventsArePublished(fn (...$eventMessages) => count($eventMessages) === 2);
        $this->emitEvents->sendBatch();

        self::assertEquals(1, $this->emitEvents->attemptForErrors);
        self::assertEquals(1, $this->emitEvents->attemptForNoEvents);
        $events->each(function (DomainEvent $event): void {
            self::assertDatabaseMissing(DomainEvent::class, ['id' => $event['id']]);
        });
    }

    #[Test]
    public function whenSendingABatchAndThereAreMoreEventsThanBatchSizeItShouldPublishAndDeleteOnlyTheBatchSizeAmount(): void
    {
        $events = DomainEvent::factory(3)->create();

        $this->whenEventsArePublished(fn (...$eventMessages) => count($eventMessages) === 2);
        $this->whenEventsArePublished(fn (...$eventMessages) => count($eventMessages) === 1);

        $this->emitEvents->sendBatch();

        self::assertEquals(1, $this->emitEvents->attemptForErrors);
        self::assertEquals(1, $this->emitEvents->attemptForNoEvents);
        $events->each(function (DomainEvent $event): void {
            self::assertDatabaseMissing(DomainEvent::class, ['id' => $event['id']]);
        });
    }

    #[Test]
    public function whenSendingABatchButThereIsAnErrorPublishingTheEventsItShouldLogAnAlertAndWaitAndDoNotDelete(): void
    {
        $event = DomainEvent::factory()->create();

        $this->eventPublisherMiddleware->shouldReceive('store')
            ->once()
            ->andReturnFalse();

        Log::shouldReceive('alert')
            ->once()
            ->with("The events couldn't be sent. Retrying...", ['eventMessages' => [$event['message']]]);

        PHPMockery::mock(__NAMESPACE__, 'sleep')
            ->once()
            ->with(1);

        $this->emitEvents->sendBatch();

        self::assertEquals(2, $this->emitEvents->attemptForErrors);
        self::assertEquals(1, $this->emitEvents->attemptForNoEvents);
        self::assertDatabaseHas(DomainEvent::class, ['id' => $event['id']]);
    }

    private function whenEventsArePublished(Closure $closure): void
    {
        $this->eventPublisherMiddleware->shouldReceive('store')
            ->once()
            ->withArgs($closure)
            ->andReturnTrue();
    }
}
