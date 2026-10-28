<?php

namespace Softonic\TransactionalEventPublisher\Observers;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use Softonic\TransactionalEventPublisher\Concerns\PublishesDomainEvents;
use Softonic\TransactionalEventPublisher\Fixtures\DomainEventsTestCase;
use Softonic\TransactionalEventPublisher\Fixtures\LegacyModel;
use Softonic\TransactionalEventPublisher\Fixtures\PublishingModel;
use Throwable;

class ModelObserverDeprecationTest extends DomainEventsTestCase
{
    /**
     * Runs in a separate process because the deprecation is triggered only once per process.
     */
    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function whenUsedOnAModelWithoutTheTraitItTriggersADeprecationAndKeepsItsBehaviour(): void
    {
        $deprecations = [];
        set_error_handler(
            static function (int $level, string $message) use (&$deprecations): bool {
                $deprecations[] = $message;

                return true;
            },
            E_USER_DEPRECATED
        );
        LegacyModel::observe(ModelObserver::class);

        try {
            $model = LegacyModel::create(['id' => 1, 'name' => 'legacy']);
            $model->name = 'legacy updated';
            $model->save();
            LegacyModel::create(['id' => 2, 'name' => 'another legacy']);
            $model->delete();
        } finally {
            restore_error_handler();
        }

        self::assertCount(1, $deprecations, 'ModelObserver must trigger an E_USER_DEPRECATED once per process.');
        self::assertStringContainsString(PublishesDomainEvents::class, $deprecations[0]);
        self::assertSame(['created', 'updated', 'created', 'deleted'], $this->middleware->storedEventTypes());
        self::assertSame(0, $this->transactionLevel());
        self::assertSame(1, DB::table('legacy_models')->count());
    }

    #[Test]
    public function whenObservingAModelWithTheTraitItDoesNotStoreExtraEventsNorOpenExtraLevels(): void
    {
        PublishingModel::observe(ModelObserver::class);
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
        PublishingModel::observe(ModelObserver::class);
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

    #[Test]
    public function whenCalledDirectlyOnAModelWithTheTraitItIsACompleteNoOp(): void
    {
        $model = new PublishingModel(['id' => 1, 'name' => 'model']);
        $observer = $this->app->make(ModelObserver::class);
        $levels = [];

        foreach (['creating', 'created', 'updating', 'updated', 'deleting', 'deleted'] as $modelEvent) {
            $observer->{$modelEvent}($model);
            $levels[] = $this->transactionLevel();
        }

        self::assertSame([0, 0, 0, 0, 0, 0], $levels);
        self::assertSame(0, $this->builder->buildCalls);
        self::assertSame([], $this->middleware->transactionLevelsWhenStoring);
    }
}
