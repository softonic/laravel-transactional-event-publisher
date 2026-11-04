<?php

declare(strict_types=1);

namespace Softonic\TransactionalEventPublisher\Services;

use Illuminate\Support\Facades\DB;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Softonic\TransactionalEventPublisher\Exceptions\EventStoreFailedException;
use Softonic\TransactionalEventPublisher\Fixtures\DomainEventsTestCase;
use Softonic\TransactionalEventPublisher\Fixtures\OtherSpyEventStoreMiddleware;
use Softonic\TransactionalEventPublisher\Fixtures\PublishingModel;
use Softonic\TransactionalEventPublisher\Fixtures\SpyEventStoreMiddleware;

/**
 * The EventStore only builds and stores the event: transactions belong to the PublishesDomainEvents trait.
 */
class EventStoreTest extends DomainEventsTestCase
{
    private OtherSpyEventStoreMiddleware $otherMiddleware;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->otherMiddleware = new OtherSpyEventStoreMiddleware();
        $this->app->instance(OtherSpyEventStoreMiddleware::class, $this->otherMiddleware);
    }

    public static function eventTypeProvider(): array
    {
        return [
            'created' => ['created'],
            'updated' => ['updated'],
            'deleted' => ['deleted'],
        ];
    }

    #[Test]
    #[DataProvider('eventTypeProvider')]
    public function whenResolvedFromTheContainerItStoresTheEventWithTheConfiguredMiddlewareAndBuilder(
        string $eventType
    ): void {
        $model = new PublishingModel(['id' => 1, 'name' => 'model']);
        $eventStore = $this->app->make(EventStore::class);

        $eventStore->store($model, $eventType);

        self::assertSame([$eventType], $this->middleware->storedEventTypes());
        self::assertSame(PublishingModel::class, $this->middleware->storedMessages[0]->toArray()['modelName']);
        self::assertSame(1, $this->middleware->storedMessages[0]->toArray()['modelKey']);
        self::assertSame(1, $this->builder->buildCalls);
    }

    #[Test]
    public function whenTheMiddlewareConfigIsAnArrayOfClassesItStoresTheEventInAllOfThem(): void
    {
        $this->app['config']->set(
            'transactional-event-publisher.middleware',
            [SpyEventStoreMiddleware::class, OtherSpyEventStoreMiddleware::class]
        );
        $model = new PublishingModel(['id' => 1, 'name' => 'model']);
        $eventStore = $this->app->make(EventStore::class);

        $eventStore->store($model, 'created');

        self::assertSame(['created'], $this->middleware->storedEventTypes());
        self::assertSame(['created'], $this->otherMiddleware->storedEventTypes());
        self::assertSame(1, $this->builder->buildCalls);
    }

    #[Test]
    #[DataProvider('eventTypeProvider')]
    public function whenStoringTheEventItNeverOpensNorClosesTransactionLevels(string $eventType): void
    {
        $model = new PublishingModel(['id' => 1, 'name' => 'model']);
        $eventStore = $this->app->make(EventStore::class);
        DB::beginTransaction();

        $eventStore->store($model, $eventType);

        self::assertSame(1, $this->transactionLevel());
        self::assertSame([1], $this->middleware->transactionLevelsWhenStoring);
        DB::rollBack();
    }

    #[Test]
    #[DataProvider('eventTypeProvider')]
    public function whenTheMiddlewareReturnsFalseItThrowsWithoutTouchingTransactionLevels(string $eventType): void
    {
        $model = new PublishingModel(['id' => 1, 'name' => 'model']);
        $this->middleware->result = false;
        $eventStore = $this->app->make(EventStore::class);
        DB::beginTransaction();

        $caught = null;
        try {
            $eventStore->store($model, $eventType);
        } catch (EventStoreFailedException $exception) {
            $caught = $exception;
        }

        self::assertInstanceOf(EventStoreFailedException::class, $caught);
        self::assertSame(1, $this->transactionLevel());
        DB::rollBack();
    }

    #[Test]
    public function whenThereAreMultipleMiddlewaresItStoresTheEventInAllOfThem(): void
    {
        $model = new PublishingModel(['id' => 1, 'name' => 'model']);
        $eventStore = new EventStore([$this->middleware, $this->otherMiddleware], $this->builder);

        $eventStore->store($model, 'created');

        self::assertSame(['created'], $this->middleware->storedEventTypes());
        self::assertSame(['created'], $this->otherMiddleware->storedEventTypes());
    }

    #[Test]
    public function whenAnyOfMultipleMiddlewaresFailsItThrows(): void
    {
        $model = new PublishingModel(['id' => 1, 'name' => 'model']);
        $this->otherMiddleware->result = false;
        $eventStore = new EventStore([$this->middleware, $this->otherMiddleware], $this->builder);

        $this->expectException(EventStoreFailedException::class);

        $eventStore->store($model, 'created');
    }

    #[Test]
    public function whenTheFirstOfMultipleMiddlewaresFailsItThrowsAlthoughTheLastOneSucceedsAndAllAreCalled(): void
    {
        $model = new PublishingModel(['id' => 1, 'name' => 'model']);
        $this->otherMiddleware->result = false;
        $eventStore = new EventStore([$this->otherMiddleware, $this->middleware], $this->builder);

        $caught = null;
        try {
            $eventStore->store($model, 'created');
        } catch (EventStoreFailedException $exception) {
            $caught = $exception;
        }

        self::assertInstanceOf(EventStoreFailedException::class, $caught);
        self::assertCount(1, $this->otherMiddleware->transactionLevelsWhenStoring);
        self::assertSame(['created'], $this->middleware->storedEventTypes());
    }

    #[Test]
    public function whenASingleMiddlewareInstanceIsGivenItStoresTheEvent(): void
    {
        $model = new PublishingModel(['id' => 1, 'name' => 'model']);
        $eventStore = new EventStore($this->middleware, $this->builder);

        $eventStore->store($model, 'updated');

        self::assertSame(['updated'], $this->middleware->storedEventTypes());
    }

    #[Test]
    public function whenTheBuilderThrowsTheExceptionIsPropagatedUnchangedAndNoMiddlewareIsCalled(): void
    {
        $model = new PublishingModel(['id' => 1, 'name' => 'model']);
        $exception = new RuntimeException('Builder exploded');
        $this->builder->exceptionToThrow = $exception;
        $eventStore = new EventStore($this->middleware, $this->builder);
        DB::beginTransaction();

        $caught = null;
        try {
            $eventStore->store($model, 'created');
        } catch (RuntimeException $thrown) {
            $caught = $thrown;
        }

        self::assertSame($exception, $caught);
        self::assertSame([], $this->middleware->transactionLevelsWhenStoring);
        self::assertSame(1, $this->transactionLevel());
        DB::rollBack();
    }

    #[Test]
    public function whenTheMiddlewareThrowsTheExceptionIsPropagatedUnchanged(): void
    {
        $model = new PublishingModel(['id' => 1, 'name' => 'model']);
        $exception = new RuntimeException('Middleware exploded');
        $this->middleware->exceptionToThrow = $exception;
        $eventStore = new EventStore($this->middleware, $this->builder);

        $caught = null;
        try {
            $eventStore->store($model, 'created');
        } catch (RuntimeException $thrown) {
            $caught = $thrown;
        }

        self::assertSame($exception, $caught);
    }
}
