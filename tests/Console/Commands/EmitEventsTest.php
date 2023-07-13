<?php

namespace Softonic\TransactionalEventPublisher\Console\Commands;

use Closure;
use Exception;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Log;
use Mockery;
use phpmock\mockery\PHPMockery;
use Softonic\TransactionalEventPublisher\Contracts\EventStoreMiddlewareContract;
use Softonic\TransactionalEventPublisher\EventStoreMiddlewares\AmqpMiddleware;
use Softonic\TransactionalEventPublisher\Models\DomainEvent;
use Softonic\TransactionalEventPublisher\Models\DomainEventsCursor;
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
        $this->emitEvents->batchSize = 2;
    }

    /**
     * @test
     */
    public function whenThereIsNoCursorAndEventsItShouldInitializeItAndCallTheSendBatchesMethod(): void
    {
        $emitEvents = Mockery::mock(EmitEvents::class)->makePartial();
        $emitEvents->__construct();
        $emitEvents->shouldAllowMockingProtectedMethods();
        $emitEvents->shouldReceive('sendBatches')->once();
        $this->app->instance(EmitEvents::class, $emitEvents);

        $this->artisan('event-sourcing:emit');

        self::assertDatabaseHas(DomainEventsCursor::class, ['last_id' => 0]);
    }

    /**
     * @test
     */
    public function whenThereIsACursorButNoEventsItShouldCallTheSendBatchesMethod(): void
    {
        $cursor = DomainEventsCursor::factory()->create();

        $emitEvents = Mockery::mock(EmitEvents::class)->makePartial();
        $emitEvents->__construct();
        $emitEvents->shouldAllowMockingProtectedMethods();
        $emitEvents->shouldReceive('sendBatches')->once();
        $this->app->instance(EmitEvents::class, $emitEvents);

        $this->artisan('event-sourcing:emit');

        self::assertDatabaseHas(DomainEventsCursor::class, ['last_id' => $cursor->last_id]);
    }

    /**
     * @test
     */
    public function whenTheAllEventsOptionIsReceivedItShouldResetTheCursorAndCallTheSendBatchesMethod(): void
    {
        DomainEventsCursor::factory()->create();

        $emitEvents = Mockery::mock(EmitEvents::class)->makePartial();
        $emitEvents->__construct();
        $emitEvents->shouldAllowMockingProtectedMethods();
        $emitEvents->shouldReceive('sendBatches')->once();
        $this->app->instance(EmitEvents::class, $emitEvents);

        $this->artisan('event-sourcing:emit --allEvents');

        self::assertDatabaseHas(DomainEventsCursor::class, ['last_id' => 0]);
    }

    /**
     * @test
     */
    public function whenSendingABatchButThereAreNoEventsItShouldWaitAndDoNothing(): void
    {
        $cursor = DomainEventsCursor::factory()->create(['last_id' => 0]);

        PHPMockery::mock(__NAMESPACE__, 'usleep')
            ->once()
            ->with(1000);

        $this->emitEvents->cursor = $cursor;
        $this->emitEvents->sendBatch();

        $this->checkFinalCursor(0);
        self::assertEquals(1, $this->emitEvents->attemptForErrors);
        self::assertEquals(2, $this->emitEvents->attemptForNoEvents);
    }

    /**
     * @test
     */
    public function whenSendingABatchButThereAreNoEventsForFifthTimeItShouldWaitAndDoNothing(): void
    {
        $this->emitEvents->attemptForNoEvents = 5;

        $cursor = DomainEventsCursor::factory()->create(['last_id' => 0]);

        PHPMockery::mock(__NAMESPACE__, 'usleep')
            ->once()
            ->with(16000);

        $this->emitEvents->cursor = $cursor;
        $this->emitEvents->sendBatch();

        $this->checkFinalCursor(0);
        self::assertEquals(1, $this->emitEvents->attemptForErrors);
        self::assertEquals(6, $this->emitEvents->attemptForNoEvents);
    }

    /**
     * @test
     */
    public function whenSendingABatchAndThereIsOneEventItShouldPublishIt(): void
    {
        $cursor = DomainEventsCursor::factory()->create(['last_id' => 0]);
        $event = DomainEvent::factory()->create();

        $this->whenEventsArePublished(fn (...$eventMessages) => $event['message']->toArray() === $eventMessages[0]->toArray());

        $this->emitEvents->cursor = $cursor;
        $this->emitEvents->sendBatch();

        $this->checkFinalCursor(1);
        self::assertEquals(1, $this->emitEvents->attemptForErrors);
        self::assertEquals(1, $this->emitEvents->attemptForNoEvents);
    }

    /**
     * @test
     */
    public function whenSendingABatchAfterThreeAttemptsWithErrorsItShouldPublishItAndResetAttempts(): void
    {
        $this->emitEvents->attemptForErrors = 4;

        $cursor = DomainEventsCursor::factory()->create(['last_id' => 0]);
        $event = DomainEvent::factory()->create();

        $this->whenEventsArePublished(fn (...$eventMessages) => $event['message']->toArray() === $eventMessages[0]->toArray());

        $this->emitEvents->cursor = $cursor;
        $this->emitEvents->sendBatch();

        $this->checkFinalCursor(1);
        self::assertEquals(1, $this->emitEvents->attemptForErrors);
        self::assertEquals(1, $this->emitEvents->attemptForNoEvents);
    }

    /**
     * @test
     */
    public function whenSendingABatchAfterFiveAttemptsWithNoEventsItShouldPublishItAndResetAttempts(): void
    {
        $this->emitEvents->attemptForNoEvents = 6;

        $cursor = DomainEventsCursor::factory()->create(['last_id' => 0]);
        $event = DomainEvent::factory()->create();

        $this->whenEventsArePublished(fn (...$eventMessages) => $event['message']->toArray() === $eventMessages[0]->toArray());

        $this->emitEvents->cursor = $cursor;
        $this->emitEvents->sendBatch();

        $this->checkFinalCursor(1);
        self::assertEquals(1, $this->emitEvents->attemptForErrors);
        self::assertEquals(1, $this->emitEvents->attemptForNoEvents);
    }

    /**
     * @test
     */
    public function whenSendingABatchAndThereAreSameEventsThanBatchSizeItShouldPublishThem(): void
    {
        $cursor = DomainEventsCursor::factory()->create(['last_id' => 0]);
        DomainEvent::factory(2)->create();

        $this->whenEventsArePublished(fn (...$eventMessages) => count($eventMessages) === 2);

        $this->emitEvents->cursor = $cursor;
        $this->emitEvents->sendBatch();

        $this->checkFinalCursor(2);
        self::assertEquals(1, $this->emitEvents->attemptForErrors);
        self::assertEquals(1, $this->emitEvents->attemptForNoEvents);
    }

    /**
     * @test
     */
    public function whenSendingABatchAndThereAreMoreEventsThanBatchSizeItShouldPublishOnlyTheBatchSizeAmount(): void
    {
        $cursor = DomainEventsCursor::factory()->create(['last_id' => 0]);
        DomainEvent::factory(3)->create();

        $this->whenEventsArePublished(fn (...$eventMessages) => count($eventMessages) === 2);
        $this->whenEventsArePublished(fn (...$eventMessages) => count($eventMessages) === 1);

        $this->emitEvents->cursor = $cursor;
        $this->emitEvents->sendBatch();

        $this->checkFinalCursor(3);
        self::assertEquals(1, $this->emitEvents->attemptForErrors);
        self::assertEquals(1, $this->emitEvents->attemptForNoEvents);
    }

    /**
     * @test
     */
    public function whenSendingABatchAndThereAreMorePendingEventsThanBatchSizeAndCursorIsNotAtStartItShouldPublishOnlyTheBatchSizeAmount(): void
    {
        $cursor = DomainEventsCursor::factory()->create(['last_id' => 2]);
        DomainEvent::factory(5)->create();

        $this->whenEventsArePublished(fn (...$eventMessages) => count($eventMessages) === 2);
        $this->whenEventsArePublished(fn (...$eventMessages) => count($eventMessages) === 1);

        $this->emitEvents->cursor = $cursor;
        $this->emitEvents->sendBatch();

        $this->checkFinalCursor(5);
        self::assertEquals(1, $this->emitEvents->attemptForErrors);
        self::assertEquals(1, $this->emitEvents->attemptForNoEvents);
    }

    /**
     * @test
     */
    public function whenSendingABatchAndThereIsOnePendingEventsThanBatchSizeAndCursorIsNotAtStartItShouldPublishOnlyThatEvent(): void
    {
        $cursor = DomainEventsCursor::factory()->create(['last_id' => 2]);
        DomainEvent::factory(3)->create();

        $this->whenEventsArePublished(fn (...$eventMessages) => count($eventMessages) === 1);

        $this->emitEvents->cursor = $cursor;
        $this->emitEvents->sendBatch();

        $this->checkFinalCursor(3);
        self::assertEquals(1, $this->emitEvents->attemptForErrors);
        self::assertEquals(1, $this->emitEvents->attemptForNoEvents);
    }

    /**
     * @test
     */
    public function whenSendingABatchButThereIsAnErrorPublishingTheEventsItShouldLogAnAlertAndWaitAndDoNotChangeCursor(): void
    {
        $cursor = DomainEventsCursor::factory()->create(['last_id' => 0]);
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

        $this->emitEvents->cursor = $cursor;
        $this->emitEvents->sendBatch();

        $this->checkFinalCursor(0);
        self::assertEquals(2, $this->emitEvents->attemptForErrors);
        self::assertEquals(1, $this->emitEvents->attemptForNoEvents);
    }

    /**
     * @test
     */
    public function whenSendingABatchButTheCursorAndTheNumberOfEventsIsNotConsistentItShouldLogAnErrorAndWaitAndDoNotChangeCursor(): void
    {
        $cursor = DomainEventsCursor::factory()->create(['last_id' => 0]);
        DomainEvent::factory()->create();

        $this->eventPublisherMiddleware->shouldNotReceive('store');

        Log::shouldReceive('error')
            ->once()
            ->with(
                'Mismatch in the events to send. Retrying...',
                [
                    'previousLastId' => 0,
                    'eventMessagesCount' => 1,
                    'lastId' => 1,
                ]
            );

        PHPMockery::mock(__NAMESPACE__, 'sleep')
            ->once()
            ->with(1);

        $emitEvents = Mockery::mock(EmitEvents::class)->makePartial();
        $emitEvents->__construct();
        $emitEvents->shouldAllowMockingProtectedMethods();
        $emitEvents->shouldReceive('isCursorConsistentWithMessages')->once()->andReturnFalse();

        $emitEvents->eventPublisherMiddleware = $this->eventPublisherMiddleware;
        $emitEvents->databaseConnection = 'testing';
        $emitEvents->batchSize = 2;
        $emitEvents->cursor = $cursor;
        $emitEvents->sendBatch();

        self::assertEquals(0, $emitEvents->cursor->last_id);
        self::assertDatabaseHas(DomainEventsCursor::class, ['last_id' => 0]);
        self::assertEquals(2, $emitEvents->attemptForErrors);
        self::assertEquals(1, $emitEvents->attemptForNoEvents);
    }

    /**
     * @test
     */
    public function whenSendingABatchWithTheMaxBatchSizeMessagesItShouldNotCallTheCheckCursorConsistencyWithEventsMethodAndPublishTheEvents(): void
    {
        $cursor = DomainEventsCursor::factory()->create(['last_id' => 0]);
        DomainEvent::factory(2)->create();

        $this->whenEventsArePublished(fn (...$eventMessages) => count($eventMessages) === 2);

        $emitEvents = Mockery::mock(EmitEvents::class)->makePartial();
        $emitEvents->__construct();
        $emitEvents->shouldAllowMockingProtectedMethods();
        $emitEvents->shouldNotReceive('checkCursorConsistencyWithEvents');

        $emitEvents->eventPublisherMiddleware = $this->eventPublisherMiddleware;
        $emitEvents->databaseConnection = 'testing';
        $emitEvents->batchSize = 2;
        $emitEvents->cursor = $cursor;
        $emitEvents->sendBatch();

        self::assertEquals(2, $emitEvents->cursor->last_id);
        self::assertDatabaseHas(DomainEventsCursor::class, ['last_id' => 2]);
        self::assertEquals(1, $emitEvents->attemptForErrors);
        self::assertEquals(1, $emitEvents->attemptForNoEvents);
    }

    /**
     * @test
     */
    public function whenSendingABatchAndThereIsAnErrorSavingTheCursorItShouldWaitAndRestoreTheCursor(): void
    {
        $cursor = DomainEventsCursor::factory()->create(['last_id' => 0]);
        DomainEvent::factory()->create();

        $this->whenEventsArePublished(fn (...$eventMessages) => count($eventMessages) === 1);

        DomainEventsCursor::saving(fn () => throw new Exception());

        PHPMockery::mock(__NAMESPACE__, 'sleep')
            ->once()
            ->with(1);

        $this->emitEvents->cursor = $cursor;
        $this->emitEvents->sendBatch();

        $this->checkFinalCursor(0);
        self::assertEquals(2, $this->emitEvents->attemptForErrors);
        self::assertEquals(1, $this->emitEvents->attemptForNoEvents);
    }

    /**
     * @test
     */
    public function whenSendingABatchAndThereIsAnErrorSavingTheCursorForThirdTimeItShouldWaitAndRestoreTheCursor(): void
    {
        $this->emitEvents->attemptForErrors = 3;

        $cursor = DomainEventsCursor::factory()->create(['last_id' => 0]);
        DomainEvent::factory()->create();

        $this->whenEventsArePublished(fn (...$eventMessages) => count($eventMessages) === 1);

        DomainEventsCursor::saving(fn () => throw new Exception());

        PHPMockery::mock(__NAMESPACE__, 'sleep')
            ->once()
            ->with(4);

        $this->emitEvents->cursor = $cursor;
        $this->emitEvents->sendBatch();

        $this->checkFinalCursor(0);
        self::assertEquals(4, $this->emitEvents->attemptForErrors);
        self::assertEquals(1, $this->emitEvents->attemptForNoEvents);
    }

    /**
     * @test
     */
    public function whenSendingABatchAndThereIsAnErrorSavingTheCursorForSeventhTimeItShouldWaitTheMaxSecondsAndRestoreTheCursor(): void
    {
        $this->emitEvents->attemptForErrors = 7;

        $cursor = DomainEventsCursor::factory()->create(['last_id' => 0]);
        DomainEvent::factory()->create();

        $this->whenEventsArePublished(fn (...$eventMessages) => count($eventMessages) === 1);

        DomainEventsCursor::saving(fn () => throw new Exception());

        PHPMockery::mock(__NAMESPACE__, 'sleep')
            ->once()
            ->with(60);

        $this->emitEvents->cursor = $cursor;
        $this->emitEvents->sendBatch();

        $this->checkFinalCursor(0);
        self::assertEquals(8, $this->emitEvents->attemptForErrors);
        self::assertEquals(1, $this->emitEvents->attemptForNoEvents);
    }

    private function whenEventsArePublished(Closure $closure): void
    {
        $this->eventPublisherMiddleware->shouldReceive('store')
            ->once()
            ->withArgs($closure)
            ->andReturnTrue();
    }

    private function checkFinalCursor(int $lastId): void
    {
        self::assertEquals($lastId, $this->emitEvents->cursor->last_id);
        self::assertDatabaseHas(DomainEventsCursor::class, ['last_id' => $lastId]);
    }
}
