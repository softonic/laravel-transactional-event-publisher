<?php

namespace Softonic\TransactionalEventPublisher\Concerns;

use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Softonic\TransactionalEventPublisher\Exceptions\EventStoreFailedException;
use Softonic\TransactionalEventPublisher\Fixtures\AdoptingPublishingModel;
use Softonic\TransactionalEventPublisher\Fixtures\DomainEventsTestCase;
use Softonic\TransactionalEventPublisher\Fixtures\PublishingModel;
use Softonic\TransactionalEventPublisher\Fixtures\UnobservedPublishingModel;
use Throwable;

class PublishesDomainEventsTest extends DomainEventsTestCase
{
    private const int CALLER_ROW_ID = 99;

    public static function operationProvider(): array
    {
        return [
            'insert' => ['insert', 'created'],
            'update' => ['update', 'updated'],
            'delete' => ['delete', 'deleted'],
        ];
    }

    public static function operationNameProvider(): array
    {
        return [
            'insert' => ['insert'],
            'update' => ['update'],
            'delete' => ['delete'],
        ];
    }

    public static function eventStoreFailureProvider(): array
    {
        $cases = [];
        foreach (['insert', 'update', 'delete'] as $operation) {
            $cases["{$operation} when middleware returns false"] = [
                $operation,
                'middlewareReturnsFalse',
                EventStoreFailedException::class,
            ];
            $cases["{$operation} when middleware throws"] = [$operation, 'middlewareThrows', RuntimeException::class];
            $cases["{$operation} when builder throws"] = [$operation, 'builderThrows', RuntimeException::class];
        }

        return $cases;
    }

    public static function haltingListenerProvider(): array
    {
        return [
            'creating' => ['insert', 'creating'],
            'updating' => ['update', 'updating'],
            'deleting' => ['delete', 'deleting'],
        ];
    }

    #[Test]
    public function whenInsertFailsInsideAnExternalTransactionOnlyTheCallerTransactionRemainsOpenAndIsRolledBack(): void
    {
        $this->seedPublishingModel(1, 'existing');
        $failingInsert = static fn () => PublishingModel::create(['id' => 2, 'name' => 'existing']);

        [$caught, $levelInsideAfterFailure, $propagated] = $this->runFailingOperationInsideExternalTransaction(
            $failingInsert
        );

        self::assertInstanceOf(UniqueConstraintViolationException::class, $caught);
        self::assertSame($caught, $propagated);
        self::assertSame(1, $levelInsideAfterFailure);
        self::assertSame(0, $this->transactionLevel());
        self::assertFalse($this->publishingModelExists(2));
        self::assertFalse($this->publishingModelExists(self::CALLER_ROW_ID));
        self::assertSame([], $this->middleware->storedMessages);
    }

    #[Test]
    public function whenUpdateFailsInsideAnExternalTransactionOnlyTheCallerTransactionRemainsOpenAndIsRolledBack(): void
    {
        $this->seedPublishingModel(1, 'first');
        $this->seedPublishingModel(2, 'second');
        $model = PublishingModel::findOrFail(2);
        $failingUpdate = static function () use ($model) {
            $model->name = 'first';

            return $model->save();
        };

        [$caught, $levelInsideAfterFailure, $propagated] = $this->runFailingOperationInsideExternalTransaction(
            $failingUpdate
        );

        self::assertInstanceOf(UniqueConstraintViolationException::class, $caught);
        self::assertSame($caught, $propagated);
        self::assertSame(1, $levelInsideAfterFailure);
        self::assertSame(0, $this->transactionLevel());
        self::assertSame('second', $this->publishingModelName(2));
        self::assertFalse($this->publishingModelExists(self::CALLER_ROW_ID));
        self::assertSame([], $this->middleware->storedMessages);
    }

    #[Test]
    public function whenDeleteFailsInsideAnExternalTransactionOnlyTheCallerTransactionRemainsOpenAndIsRolledBack(): void
    {
        $this->seedPublishingModel(1, 'parent');
        DB::table('publishing_model_children')->insert(['id' => 1, 'publishing_model_id' => 1]);
        $model = PublishingModel::findOrFail(1);
        $failingDelete = static fn () => $model->delete();

        [$caught, $levelInsideAfterFailure, $propagated] = $this->runFailingOperationInsideExternalTransaction(
            $failingDelete
        );

        self::assertInstanceOf(QueryException::class, $caught);
        self::assertSame($caught, $propagated);
        self::assertSame(1, $levelInsideAfterFailure);
        self::assertSame(0, $this->transactionLevel());
        self::assertTrue($this->publishingModelExists(1));
        self::assertFalse($this->publishingModelExists(self::CALLER_ROW_ID));
        self::assertSame([], $this->middleware->storedMessages);
    }

    #[Test]
    #[DataProvider('operationNameProvider')]
    public function whenTheQueryFailsWithoutAnExternalTransactionNoTransactionIsLeftOpen(string $operation): void
    {
        $failingOperation = $this->prepareFailingOperation($operation);

        $caught = null;
        try {
            $failingOperation();
        } catch (Throwable $exception) {
            $caught = $exception;
        }

        self::assertInstanceOf(QueryException::class, $caught);
        self::assertSame(0, $this->transactionLevel());
        self::assertSame([], $this->middleware->storedMessages);
    }

    #[Test]
    #[DataProvider('eventStoreFailureProvider')]
    public function whenStoringTheEventFailsInsideAnExternalTransactionTheWriteIsRolledBackAndOnlyTheCallerLevelRemains(
        string $operation,
        string $failureMode,
        string $expectedException
    ): void {
        $successfulOperation = $this->prepareSuccessfulOperation($operation);
        $this->makeEventStoreFail($failureMode);

        [$caught, $levelInsideAfterFailure, $propagated] = $this->runFailingOperationInsideExternalTransaction(
            $successfulOperation
        );

        self::assertInstanceOf($expectedException, $caught);
        self::assertSame($caught, $propagated);
        self::assertSame(1, $levelInsideAfterFailure);
        self::assertSame(0, $this->transactionLevel());
        $this->assertOperationNotApplied($operation);
        self::assertFalse($this->publishingModelExists(self::CALLER_ROW_ID));
        self::assertSame([], $this->middleware->storedMessages);
    }

    #[Test]
    #[DataProvider('operationNameProvider')]
    public function whenTheMiddlewareReturnsFalseWithoutAnExternalTransactionTheWriteIsRolledBackAndNoTransactionIsLeftOpen(
        string $operation
    ): void {
        $successfulOperation = $this->prepareSuccessfulOperation($operation);
        $this->makeEventStoreFail('middlewareReturnsFalse');

        $caught = null;
        try {
            $successfulOperation();
        } catch (Throwable $exception) {
            $caught = $exception;
        }

        self::assertInstanceOf(EventStoreFailedException::class, $caught);
        self::assertSame(0, $this->transactionLevel());
        $this->assertOperationNotApplied($operation);
    }

    #[Test]
    #[DataProvider('operationProvider')]
    public function whenTheOperationSucceedsWithoutAnExternalTransactionExactlyOneEventIsStoredAndTheWriteIsCommitted(
        string $operation,
        string $expectedModelEvent
    ): void {
        $successfulOperation = $this->prepareSuccessfulOperation($operation);

        $result = $successfulOperation();

        self::assertTrue($result);
        self::assertSame([$expectedModelEvent], $this->middleware->storedEventTypes());
        self::assertSame(PublishingModel::class, $this->middleware->storedMessages[0]->toArray()['modelName']);
        self::assertSame(2, $this->middleware->storedMessages[0]->toArray()['modelKey']);
        self::assertCount(1, $this->middleware->transactionLevelsWhenStoring);
        self::assertGreaterThan(
            0,
            $this->middleware->transactionLevelsWhenStoring[0],
            'The event must be stored inside the same transaction as the write.'
        );
        self::assertSame(0, $this->transactionLevel());
        $this->assertOperationApplied($operation);
    }

    #[Test]
    #[DataProvider('operationProvider')]
    public function whenTheOperationSucceedsInsideAnExternalTransactionExactlyOneEventIsStoredAndTheLevelIsPreserved(
        string $operation,
        string $expectedModelEvent
    ): void {
        $successfulOperation = $this->prepareSuccessfulOperation($operation);
        $levelBeforeOperation = null;
        $levelAfterOperation = null;
        $result = null;

        DB::transaction(function () use (
            $successfulOperation,
            &$levelBeforeOperation,
            &$levelAfterOperation,
            &$result
        ): void {
            DB::table('publishing_models')->insert(['id' => self::CALLER_ROW_ID, 'name' => 'caller write']);
            $levelBeforeOperation = $this->transactionLevel();
            $result = $successfulOperation();
            $levelAfterOperation = $this->transactionLevel();
        });

        self::assertTrue($result);
        self::assertSame(1, $levelBeforeOperation);
        self::assertSame($levelBeforeOperation, $levelAfterOperation);
        self::assertSame(0, $this->transactionLevel());
        self::assertSame([$expectedModelEvent], $this->middleware->storedEventTypes());
        self::assertCount(1, $this->middleware->transactionLevelsWhenStoring);
        self::assertGreaterThan(1, $this->middleware->transactionLevelsWhenStoring[0]);
        $this->assertOperationApplied($operation);
        self::assertTrue($this->publishingModelExists(self::CALLER_ROW_ID));
    }

    #[Test]
    #[DataProvider('haltingListenerProvider')]
    public function whenAListenerHaltsTheOperationNoEventIsStoredAndNoTransactionIsLeftOpen(
        string $operation,
        string $haltingModelEvent
    ): void {
        $successfulOperation = $this->prepareSuccessfulOperation($operation);
        PublishingModel::{$haltingModelEvent}(static fn (): bool => false);
        $levelInsideAfterOperation = null;
        $result = null;

        DB::transaction(function () use ($successfulOperation, &$levelInsideAfterOperation, &$result): void {
            DB::table('publishing_models')->insert(['id' => self::CALLER_ROW_ID, 'name' => 'caller write']);
            $result = $successfulOperation();
            $levelInsideAfterOperation = $this->transactionLevel();
        });

        self::assertFalse($result);
        self::assertSame(1, $levelInsideAfterOperation);
        self::assertSame(0, $this->transactionLevel());
        self::assertSame([], $this->middleware->storedMessages);
        $this->assertOperationNotApplied($operation);
        self::assertTrue($this->publishingModelExists(self::CALLER_ROW_ID));
    }

    #[Test]
    public function whenSavingAModelWithoutChangesNoEventIsStoredAndNoTransactionIsLeftOpen(): void
    {
        $this->seedPublishingModel(1, 'unchanged');
        $model = PublishingModel::findOrFail(1);

        $result = $model->save();

        self::assertTrue($result);
        self::assertSame(0, $this->transactionLevel());
        self::assertSame([], $this->middleware->storedMessages);
    }

    #[Test]
    public function whenAModelAdoptsAnExistingRowOnUniqueViolationTheExternalTransactionIsUnaffectedAndCommits(): void
    {
        $this->seedPublishingModel(1, 'existing');
        $levelBeforeSave = null;
        $levelAfterSave = null;
        $saveResult = null;
        $adoptedModel = new AdoptingPublishingModel(['id' => 1, 'name' => 'existing']);

        DB::transaction(function () use ($adoptedModel, &$levelBeforeSave, &$levelAfterSave, &$saveResult): void {
            DB::table('publishing_models')->insert(['id' => self::CALLER_ROW_ID, 'name' => 'caller write']);
            $levelBeforeSave = $this->transactionLevel();
            $saveResult = $adoptedModel->save();
            $levelAfterSave = $this->transactionLevel();
            DB::table('publishing_models')->insert(['id' => self::CALLER_ROW_ID + 1, 'name' => 'later caller write']);
        });

        self::assertTrue($saveResult);
        self::assertTrue($adoptedModel->exists);
        self::assertSame(1, $levelBeforeSave);
        self::assertSame($levelBeforeSave, $levelAfterSave);
        self::assertSame(0, $this->transactionLevel());
        self::assertTrue($this->publishingModelExists(self::CALLER_ROW_ID));
        self::assertTrue($this->publishingModelExists(self::CALLER_ROW_ID + 1));
        self::assertSame(3, DB::table('publishing_models')->count());
        self::assertSame([], $this->middleware->storedMessages);
    }

    #[Test]
    public function whenAModelAdoptsAnExistingRowOnUniqueViolationWithoutAnExternalTransactionNoTransactionIsLeftOpen(): void
    {
        $this->seedPublishingModel(1, 'existing');
        $adoptedModel = new AdoptingPublishingModel(['id' => 1, 'name' => 'existing']);

        $saveResult = $adoptedModel->save();

        self::assertTrue($saveResult);
        self::assertTrue($adoptedModel->exists);
        self::assertSame(0, $this->transactionLevel());
        self::assertSame(1, DB::table('publishing_models')->count());
        self::assertSame([], $this->middleware->storedMessages);
    }

    #[Test]
    #[DataProvider('haltingListenerProvider')]
    public function whenAListenerHaltsTheOperationWithoutAnExternalTransactionNoTransactionIsLeftOpen(
        string $operation,
        string $haltingModelEvent
    ): void {
        $successfulOperation = $this->prepareSuccessfulOperation($operation);
        PublishingModel::{$haltingModelEvent}(static fn (): bool => false);

        $result = $successfulOperation();

        self::assertFalse($result);
        self::assertSame(0, $this->transactionLevel());
        self::assertSame([], $this->middleware->storedMessages);
        $this->assertOperationNotApplied($operation);
    }

    public static function incrementOrDecrementProvider(): array
    {
        return [
            'increment' => ['increment', 1],
            'decrement' => ['decrement', -1],
        ];
    }

    #[Test]
    #[DataProvider('incrementOrDecrementProvider')]
    public function whenIncrementingOrDecrementingTheEventIsStoredInTheSameTransactionAsTheWrite(
        string $method,
        int $expectedCounter
    ): void {
        $this->seedPublishingModel(1, 'counted');
        $model = PublishingModel::findOrFail(1);

        $model->{$method}('counter');

        self::assertSame(['updated'], $this->middleware->storedEventTypes());
        self::assertGreaterThan(
            0,
            $this->middleware->transactionLevelsWhenStoring[0],
            'The event must be stored inside the same transaction as the write.'
        );
        self::assertSame(0, $this->transactionLevel());
        self::assertSame($expectedCounter, DB::table('publishing_models')->where('id', 1)->value('counter'));
    }

    public static function incrementOrDecrementMethodProvider(): array
    {
        return [
            'increment' => ['increment'],
            'decrement' => ['decrement'],
        ];
    }

    #[Test]
    #[DataProvider('incrementOrDecrementMethodProvider')]
    public function whenStoringTheEventOfAnIncrementOrDecrementFailsTheWriteIsRolledBack(string $method): void
    {
        $this->seedPublishingModel(1, 'counted');
        $model = PublishingModel::findOrFail(1);
        $this->makeEventStoreFail('middlewareReturnsFalse');

        $caught = null;
        try {
            $model->{$method}('counter');
        } catch (Throwable $exception) {
            $caught = $exception;
        }

        self::assertInstanceOf(EventStoreFailedException::class, $caught);
        self::assertSame(0, $this->transactionLevel());
        self::assertSame(0, DB::table('publishing_models')->where('id', 1)->value('counter'));
    }

    #[Test]
    public function whenAModelUsingTheTraitIsSavedWithoutRegisteringAnyObserverTheEventIsStored(): void
    {
        $model = new PublishingModel(['id' => 1, 'name' => 'no explicit observer']);

        $model->save();

        self::assertSame(['created'], $this->middleware->storedEventTypes());
        self::assertSame(0, $this->transactionLevel());
        self::assertTrue($this->publishingModelExists(1));
    }

    /**
     * Runs the operation inside a caller transaction that has already written a row.
     *
     * @return array{0: ?Throwable, 1: ?int, 2: ?Throwable} [exception caught inside the caller closure,
     *                                                      level inside the closure right after the failure,
     *                                                      exception propagated out of DB::transaction]
     */
    private function runFailingOperationInsideExternalTransaction(Closure $operation): array
    {
        $caught = null;
        $levelInsideAfterFailure = null;
        $propagated = null;

        try {
            DB::transaction(function () use ($operation, &$caught, &$levelInsideAfterFailure): void {
                DB::table('publishing_models')->insert(['id' => self::CALLER_ROW_ID, 'name' => 'caller write']);

                try {
                    $operation();
                } catch (Throwable $exception) {
                    $caught = $exception;
                    $levelInsideAfterFailure = $this->transactionLevel();

                    throw $exception;
                }
            });
        } catch (Throwable $exception) {
            $propagated = $exception;
        }

        return [$caught, $levelInsideAfterFailure, $propagated];
    }

    /**
     * Seeds the data needed and returns a closure performing an operation that will fail in the database.
     */
    private function prepareFailingOperation(string $operation): Closure
    {
        switch ($operation) {
            case 'insert':
                $this->seedPublishingModel(1, 'existing');

                return static fn () => PublishingModel::create(['id' => 2, 'name' => 'existing']);
            case 'update':
                $this->seedPublishingModel(1, 'first');
                $this->seedPublishingModel(2, 'second');
                $model = PublishingModel::findOrFail(2);

                return static function () use ($model) {
                    $model->name = 'first';

                    return $model->save();
                };
            default:
                $this->seedPublishingModel(1, 'parent');
                DB::table('publishing_model_children')->insert(['id' => 1, 'publishing_model_id' => 1]);
                $model = PublishingModel::findOrFail(1);

                return static fn () => $model->delete();
        }
    }

    /**
     * Seeds the data needed and returns a closure performing a valid operation on the model with id 2.
     * The closure returns the result of save()/delete().
     */
    private function prepareSuccessfulOperation(string $operation): Closure
    {
        $this->seedPublishingModel(1, 'first');

        switch ($operation) {
            case 'insert':
                return static fn () => new PublishingModel(['id' => 2, 'name' => 'new'])->save();
            case 'update':
                $this->seedPublishingModel(2, 'second');
                $model = PublishingModel::findOrFail(2);

                return static function () use ($model) {
                    $model->name = 'renamed';

                    return $model->save();
                };
            default:
                $this->seedPublishingModel(2, 'second');
                $model = PublishingModel::findOrFail(2);

                return static fn () => $model->delete();
        }
    }

    #[Test]
    public function withoutTheModelObserverAttachedTheWritesRunInTheirTransactionButNoEventIsStored(): void
    {
        $levels = [];

        DB::transaction(function () use (&$levels): void {
            $model = UnobservedPublishingModel::create(['id' => 1, 'name' => 'created']);
            $levels[] = $this->transactionLevel();
            $model->name = 'updated';
            $model->save();
            $model->increment('counter');
            $model->delete();
            $levels[] = $this->transactionLevel();
        });

        self::assertSame([1, 1], $levels);
        self::assertSame([], $this->middleware->storedMessages);
        self::assertSame(0, $this->builder->buildCalls);
        self::assertFalse($this->publishingModelExists(1));
    }

    private function makeEventStoreFail(string $failureMode): void
    {
        match ($failureMode) {
            'middlewareReturnsFalse' => $this->middleware->result = false,
            'middlewareThrows' => $this->middleware->exceptionToThrow = new RuntimeException('Middleware exploded'),
            'builderThrows' => $this->builder->exceptionToThrow = new RuntimeException('Builder exploded'),
        };
    }

    private function assertOperationApplied(string $operation): void
    {
        match ($operation) {
            'insert' => self::assertSame('new', $this->publishingModelName(2)),
            'update' => self::assertSame('renamed', $this->publishingModelName(2)),
            'delete' => self::assertFalse($this->publishingModelExists(2)),
        };
    }

    private function assertOperationNotApplied(string $operation): void
    {
        match ($operation) {
            'insert' => self::assertFalse($this->publishingModelExists(2)),
            'update' => self::assertSame('second', $this->publishingModelName(2)),
            'delete' => self::assertSame('second', $this->publishingModelName(2)),
        };
    }
}
