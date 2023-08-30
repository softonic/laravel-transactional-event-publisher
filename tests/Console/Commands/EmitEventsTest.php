<?php

namespace Softonic\TransactionalEventPublisher\Console\Commands;

use Closure;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Log;
use Mockery;
use phpmock\mockery\PHPMockery;
use Softonic\TransactionalEventPublisher\Contracts\EventStoreMiddlewareContract;
use Softonic\TransactionalEventPublisher\EventStoreMiddlewares\AmqpMiddleware;
use Softonic\TransactionalEventPublisher\Models\DomainEvent;
use Softonic\TransactionalEventPublisher\TestCase;

class EmitEventsTest extends TestCase
{
    use DatabaseTransactions;

    private readonly EventStoreMiddlewareContract $eventPublisherMiddleware;

    private readonly EmitEvents $emitEvents;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->register('Softonic\TransactionalEventPublisher\ServiceProvider');

        $this->loadMigrationsFrom(__DIR__ . '/../../../database/migrations');

        config()->set('transactional-event-publisher.event_publisher_middleware', AmqpMiddleware::class);

        $this->eventPublisherMiddleware = Mockery::mock(EventStoreMiddlewareContract::class);
        $this->app->instance(EventStoreMiddlewareContract::class, $this->eventPublisherMiddleware);

        $this->emitEvents = new EmitEvents();
        $this->emitEvents->eventPublisherMiddleware = $this->eventPublisherMiddleware;
        $this->emitEvents->databaseConnection = 'testing';
        $this->emitEvents->databaseUnbufferedConnection = 'testing';
        $this->emitEvents->batchSize = 2;
    }

    /**
     * @test
     */
    public function whenSendingABatchButThereAreNoEventsItShouldWaitAndDoNothing(): void
    {
        PHPMockery::mock(__NAMESPACE__, 'usleep')
            ->once()
            ->with(1000);

        $this->emitEvents->sendBatch();

        self::assertEquals(1, $this->emitEvents->attemptForErrors);
        self::assertEquals(2, $this->emitEvents->attemptForNoEvents);
    }

    /**
     * @test
     */
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

    /**
     * @test
     */
    public function whenSendingABatchAndThereIsOneEventItShouldPublishItAndDeletedIt(): void
    {
        $event = DomainEvent::factory()->create();

        $this->whenEventsArePublished(fn (...$eventMessages) => $event['message']->toArray() === $eventMessages[0]->toArray());
        $this->emitEvents->sendBatch();

        self::assertEquals(1, $this->emitEvents->attemptForErrors);
        self::assertEquals(1, $this->emitEvents->attemptForNoEvents);
        self::assertDatabaseMissing(DomainEvent::class, ['id' => $event['id']]);
    }

    /**
     * @test
     */
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

    /**
     * @test
     */
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

    /**
     * @test
     */
    public function whenSendingABatchAndThereAreSameEventsThanBatchSizeItShouldPublishThemAndDeleteThem(): void
    {
        $events = DomainEvent::factory(2)->create();

        $this->whenEventsArePublished(fn (...$eventMessages) => count($eventMessages) === 2);
        $this->emitEvents->sendBatch();

        self::assertEquals(1, $this->emitEvents->attemptForErrors);
        self::assertEquals(1, $this->emitEvents->attemptForNoEvents);
        $events->each(function ($event) {
            self::assertDatabaseMissing(DomainEvent::class, ['id' => $event['id']]);
        });
    }

    /**
     * @test
     */
    public function whenSendingABatchAndThereAreMoreEventsThanBatchSizeItShouldPublishAndDeleteOnlyTheBatchSizeAmount(): void
    {
        $events = DomainEvent::factory(3)->create();

        $this->whenEventsArePublished(fn (...$eventMessages) => count($eventMessages) === 2);
        $this->whenEventsArePublished(fn (...$eventMessages) => count($eventMessages) === 1);

        $this->emitEvents->sendBatch();

        self::assertEquals(1, $this->emitEvents->attemptForErrors);
        self::assertEquals(1, $this->emitEvents->attemptForNoEvents);
        $events->each(function ($event) {
            self::assertDatabaseMissing(DomainEvent::class, ['id' => $event['id']]);
        });
    }

    /**
     * @test
     */
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
