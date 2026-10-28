<?php

namespace Softonic\TransactionalEventPublisher\Observers;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Softonic\TransactionalEventPublisher\Exceptions\EventStoreFailedException;
use Softonic\TransactionalEventPublisher\Fixtures\DomainEventsTestCase;
use Softonic\TransactionalEventPublisher\Fixtures\LegacyModel;
use Softonic\TransactionalEventPublisher\Fixtures\SpyEventStoreMiddleware;

class DomainEventObserverTest extends DomainEventsTestCase
{
    public static function modelEventProvider(): array
    {
        return [
            'created' => ['created'],
            'updated' => ['updated'],
            'deleted' => ['deleted'],
        ];
    }

    #[Test]
    #[DataProvider('modelEventProvider')]
    public function whenResolvedFromTheContainerItStoresTheEventWithTheConfiguredMiddlewareAndBuilder(
        string $modelEvent
    ): void {
        $model = new LegacyModel(['id' => 1, 'name' => 'model']);
        $observer = $this->app->make(DomainEventObserver::class);

        $observer->{$modelEvent}($model);

        self::assertSame([$modelEvent], $this->middleware->storedEventTypes());
        self::assertSame(LegacyModel::class, $this->middleware->storedMessages[0]->toArray()['modelName']);
        self::assertSame(1, $this->builder->buildCalls);
    }

    #[Test]
    #[DataProvider('modelEventProvider')]
    public function whenStoringTheEventItNeverOpensNorClosesTransactionLevels(string $modelEvent): void
    {
        $model = new LegacyModel(['id' => 1, 'name' => 'model']);
        $observer = $this->app->make(DomainEventObserver::class);
        DB::beginTransaction();

        $observer->{$modelEvent}($model);

        self::assertSame(1, $this->transactionLevel());
        self::assertSame([1], $this->middleware->transactionLevelsWhenStoring);
        DB::rollBack();
    }

    #[Test]
    #[DataProvider('modelEventProvider')]
    public function whenTheMiddlewareFailsItThrowsWithoutTouchingTransactionLevels(string $modelEvent): void
    {
        $model = new LegacyModel(['id' => 1, 'name' => 'model']);
        $this->middleware->result = false;
        $observer = $this->app->make(DomainEventObserver::class);
        DB::beginTransaction();

        $caught = null;
        try {
            $observer->{$modelEvent}($model);
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
        $model = new LegacyModel(['id' => 1, 'name' => 'model']);
        $secondMiddleware = new SpyEventStoreMiddleware();
        $observer = new DomainEventObserver([$this->middleware, $secondMiddleware], $this->builder);

        $observer->created($model);

        self::assertSame(['created'], $this->middleware->storedEventTypes());
        self::assertSame(['created'], $secondMiddleware->storedEventTypes());
    }

    #[Test]
    public function whenAnyOfMultipleMiddlewaresFailsItThrows(): void
    {
        $model = new LegacyModel(['id' => 1, 'name' => 'model']);
        $failingMiddleware = new SpyEventStoreMiddleware();
        $failingMiddleware->result = false;

        $observer = new DomainEventObserver([$this->middleware, $failingMiddleware], $this->builder);

        $this->expectException(EventStoreFailedException::class);

        $observer->created($model);
    }

    #[Test]
    public function whenTheFirstOfMultipleMiddlewaresFailsItThrowsAlthoughTheLastOneSucceeds(): void
    {
        $model = new LegacyModel(['id' => 1, 'name' => 'model']);
        $failingMiddleware = new SpyEventStoreMiddleware();
        $failingMiddleware->result = false;

        $observer = new DomainEventObserver([$failingMiddleware, $this->middleware], $this->builder);

        $this->expectException(EventStoreFailedException::class);

        $observer->created($model);
    }

    #[Test]
    public function whenASingleMiddlewareIsGivenItStoresTheEvent(): void
    {
        $model = new LegacyModel(['id' => 1, 'name' => 'model']);
        $observer = new DomainEventObserver($this->middleware, $this->builder);

        $observer->updated($model);

        self::assertSame(['updated'], $this->middleware->storedEventTypes());
    }
}
