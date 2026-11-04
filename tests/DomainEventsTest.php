<?php

namespace Softonic\TransactionalEventPublisher;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Softonic\TransactionalEventPublisher\Fixtures\DomainEventsTestCase;
use Softonic\TransactionalEventPublisher\Fixtures\PublishingModel;
use Softonic\TransactionalEventPublisher\Services\EventStore;
use Throwable;

class DomainEventsTest extends DomainEventsTestCase
{
    #[Test]
    public function byDefaultPublishingIsEnabled(): void
    {
        self::assertTrue(DomainEvents::isEnabled());
    }

    #[Test]
    public function whenDisabledModelsWithTheTraitArePersistedWithoutStoringEvents(): void
    {
        DomainEvents::disable();

        $model = PublishingModel::create(['id' => 1, 'name' => 'first']);
        $model->name = 'first updated';
        $model->save();
        $model->increment('counter');
        PublishingModel::create(['id' => 2, 'name' => 'second'])->delete();

        self::assertFalse(DomainEvents::isEnabled());
        self::assertSame([], $this->middleware->storedMessages);
        self::assertSame('first updated', $this->publishingModelName(1));
        self::assertSame(1, DB::table('publishing_models')->where('id', 1)->value('counter'));
        self::assertFalse($this->publishingModelExists(2));
        self::assertSame(0, $this->transactionLevel());
    }

    #[Test]
    public function whenDisabledAFailingWriteOfAModelWithTheTraitStillRestoresTheCallerLevel(): void
    {
        $this->seedPublishingModel(1, 'existing');
        DomainEvents::disable();
        $levelAfterFailure = null;

        try {
            DB::transaction(function () use (&$levelAfterFailure): void {
                try {
                    PublishingModel::create(['id' => 2, 'name' => 'existing']);
                } finally {
                    $levelAfterFailure = $this->transactionLevel();
                }
            });
        } catch (Throwable) {
        }

        self::assertSame(1, $levelAfterFailure);
        self::assertSame(0, $this->transactionLevel());
        self::assertSame([], $this->middleware->storedMessages);
    }

    #[Test]
    public function whenReEnabledModelsWithTheTraitStoreEventsAgain(): void
    {
        DomainEvents::disable();
        PublishingModel::create(['id' => 1, 'name' => 'silent']);

        DomainEvents::enable();
        PublishingModel::create(['id' => 2, 'name' => 'published']);

        self::assertTrue(DomainEvents::isEnabled());
        self::assertSame(['created'], $this->middleware->storedEventTypes());
        self::assertSame(2, DB::table('publishing_models')->count());
    }

    #[Test]
    public function whenDisabledTheEventStoreIsNotEvenResolved(): void
    {
        $this->app->bind(EventStore::class, static function (): never {
            self::fail('The EventStore must not be resolved while publishing is disabled.');
        });
        DomainEvents::disable();

        $model = PublishingModel::create(['id' => 1, 'name' => 'silent']);
        $model->name = 'silent update';
        $model->save();
        $model->increment('counter');
        $model->delete();

        self::assertSame(0, $this->builder->buildCalls);
        self::assertFalse($this->publishingModelExists(1));
        self::assertSame(0, $this->transactionLevel());
    }

    /**
     * @return array<string, array{string, Closure(): mixed}>
     */
    public static function writeProvider(): array
    {
        return [
            'insert' => ['created', static fn () => PublishingModel::create(['id' => 2, 'name' => 'new'])],
            'update' => ['updated', static fn () => PublishingModel::findOrFail(1)->update(['name' => 'updated'])],
            'increment' => ['updated', static fn () => PublishingModel::findOrFail(1)->increment('counter')],
            'decrement' => ['updated', static fn () => PublishingModel::findOrFail(1)->decrement('counter')],
            'delete' => ['deleted', static fn () => PublishingModel::findOrFail(1)->delete()],
        ];
    }

    #[Test]
    #[DataProvider('writeProvider')]
    public function whenDisabledWritesOfModelsWithTheTraitStillRunInTheirOwnTransaction(
        string $modelEvent,
        Closure $write
    ): void {
        $this->seedPublishingModel(1, 'existing');
        DomainEvents::disable();
        $levelsInsideWrite = [];
        Event::listen('eloquent.' . $modelEvent . ': ' . PublishingModel::class, function () use (&$levelsInsideWrite): void {
            $levelsInsideWrite[] = $this->transactionLevel();
        });

        DB::transaction($write);

        self::assertSame([2], $levelsInsideWrite);
        self::assertSame([], $this->middleware->storedMessages);
        self::assertSame(0, $this->transactionLevel());
    }

    #[Test]
    public function withoutPublishingRunsTheCallbackWithPublishingDisabledAndReturnsItsResult(): void
    {
        $result = DomainEvents::withoutPublishing(static function (): string {
            PublishingModel::create(['id' => 1, 'name' => 'silent']);

            return DomainEvents::isEnabled() ? 'enabled' : 'disabled';
        });
        PublishingModel::create(['id' => 2, 'name' => 'published']);

        self::assertSame('disabled', $result);
        self::assertTrue(DomainEvents::isEnabled());
        self::assertSame(['created'], $this->middleware->storedEventTypes());
    }

    #[Test]
    public function withoutPublishingRestoresPublishingWhenTheCallbackThrows(): void
    {
        $exception = new RuntimeException('callback failed');

        try {
            DomainEvents::withoutPublishing(static fn () => throw $exception);
            self::fail('The exception of the callback must be propagated.');
        } catch (RuntimeException $caught) {
            self::assertSame($exception, $caught);
        }

        self::assertTrue(DomainEvents::isEnabled());
    }

    #[Test]
    public function withoutPublishingKeepsPublishingDisabledWhenItWasAlreadyDisabled(): void
    {
        DomainEvents::disable();

        DomainEvents::withoutPublishing(static fn () => null);

        self::assertFalse(DomainEvents::isEnabled());
    }
}
