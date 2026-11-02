<?php

namespace Softonic\TransactionalEventPublisher\Observers;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Softonic\TransactionalEventPublisher\Exceptions\EventStoreFailedException;
use Softonic\TransactionalEventPublisher\Fixtures\DomainEventsTestCase;
use Softonic\TransactionalEventPublisher\Fixtures\PublishingModel;
use Softonic\TransactionalEventPublisher\Fixtures\SpyEventStoreMiddleware;
use Throwable;

/**
 * The ModelObserver on models using the PublishesDomainEvents trait: it only stores the event, the trait manages the
 * transaction.
 */
class ModelObserverWithTraitTest extends DomainEventsTestCase
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
        $model = new PublishingModel(['id' => 1, 'name' => 'model']);
        $observer = $this->app->make(ModelObserver::class);

        $observer->{$modelEvent}($model);

        self::assertSame([$modelEvent], $this->middleware->storedEventTypes());
        self::assertSame(PublishingModel::class, $this->middleware->storedMessages[0]->toArray()['modelName']);
        self::assertSame(1, $this->builder->buildCalls);
    }

    #[Test]
    #[DataProvider('modelEventProvider')]
    public function whenStoringTheEventItNeverOpensNorClosesTransactionLevels(string $modelEvent): void
    {
        $model = new PublishingModel(['id' => 1, 'name' => 'model']);
        $observer = $this->app->make(ModelObserver::class);
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
        $model = new PublishingModel(['id' => 1, 'name' => 'model']);
        $this->middleware->result = false;
        $observer = $this->app->make(ModelObserver::class);
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
        $model = new PublishingModel(['id' => 1, 'name' => 'model']);
        $secondMiddleware = new SpyEventStoreMiddleware();
        $observer = new ModelObserver([$this->middleware, $secondMiddleware], $this->builder);

        $observer->created($model);

        self::assertSame(['created'], $this->middleware->storedEventTypes());
        self::assertSame(['created'], $secondMiddleware->storedEventTypes());
    }

    #[Test]
    public function whenAnyOfMultipleMiddlewaresFailsItThrows(): void
    {
        $model = new PublishingModel(['id' => 1, 'name' => 'model']);
        $failingMiddleware = new SpyEventStoreMiddleware();
        $failingMiddleware->result = false;

        $observer = new ModelObserver([$this->middleware, $failingMiddleware], $this->builder);

        $this->expectException(EventStoreFailedException::class);

        $observer->created($model);
    }

    #[Test]
    public function whenTheFirstOfMultipleMiddlewaresFailsItThrowsAlthoughTheLastOneSucceeds(): void
    {
        $model = new PublishingModel(['id' => 1, 'name' => 'model']);
        $failingMiddleware = new SpyEventStoreMiddleware();
        $failingMiddleware->result = false;

        $observer = new ModelObserver([$failingMiddleware, $this->middleware], $this->builder);

        $this->expectException(EventStoreFailedException::class);

        $observer->created($model);
    }

    #[Test]
    public function whenASingleMiddlewareIsGivenItStoresTheEvent(): void
    {
        $model = new PublishingModel(['id' => 1, 'name' => 'model']);
        $observer = new ModelObserver($this->middleware, $this->builder);

        $observer->updated($model);

        self::assertSame(['updated'], $this->middleware->storedEventTypes());
    }

    #[Test]
    public function whenTheWriteIsAboutToHappenItNeitherOpensTransactionLevelsNorStoresEvents(): void
    {
        $model = new PublishingModel(['id' => 1, 'name' => 'model']);
        $observer = $this->app->make(ModelObserver::class);
        $levels = [];

        foreach (['creating', 'updating', 'deleting'] as $modelEvent) {
            $observer->{$modelEvent}($model);
            $levels[] = $this->transactionLevel();
        }

        self::assertSame([0, 0, 0], $levels);
        self::assertSame(0, $this->builder->buildCalls);
    }

    #[Test]
    public function whenObservingAModelWithTheTraitItStoresOneEventPerWriteWithoutOpeningExtraLevels(): void
    {
        $levels = [];

        DB::transaction(function () use (&$levels): void {
            $levels[] = $this->transactionLevel();
            $model = PublishingModel::create(['id' => 1, 'name' => 'created']);
            $levels[] = $this->transactionLevel();
            $model->name = 'updated';
            $model->save();
            $levels[] = $this->transactionLevel();
            $model->delete();
            $levels[] = $this->transactionLevel();
        });

        self::assertSame([1, 1, 1, 1], $levels);
        self::assertSame(0, $this->transactionLevel());
        self::assertSame(['created', 'updated', 'deleted'], $this->middleware->storedEventTypes());
        self::assertCount(3, $this->middleware->transactionLevelsWhenStoring);
    }

    #[Test]
    public function whenObservingAModelWithTheTraitAndTheInsertFailsNoTransactionIsLeftOpen(): void
    {
        $this->seedPublishingModel(1, 'existing');
        $levelInsideAfterFailure = null;
        $caught = null;

        try {
            DB::transaction(function () use (&$levelInsideAfterFailure): void {
                try {
                    PublishingModel::create(['id' => 2, 'name' => 'existing']);
                } finally {
                    $levelInsideAfterFailure = $this->transactionLevel();
                }
            });
        } catch (Throwable $exception) {
            $caught = $exception;
        }

        self::assertInstanceOf(UniqueConstraintViolationException::class, $caught);
        self::assertSame(1, $levelInsideAfterFailure);
        self::assertSame(0, $this->transactionLevel());
        self::assertSame([], $this->middleware->storedMessages);
    }
}
