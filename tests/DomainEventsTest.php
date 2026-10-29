<?php

namespace Softonic\TransactionalEventPublisher;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Softonic\TransactionalEventPublisher\Fixtures\DomainEventsTestCase;
use Softonic\TransactionalEventPublisher\Fixtures\LegacyModel;
use Softonic\TransactionalEventPublisher\Fixtures\PublishingModel;
use Softonic\TransactionalEventPublisher\Observers\ModelObserver;
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
    public function whenDisabledTheDeprecatedObserverNeitherStoresEventsNorOpensLevels(): void
    {
        LegacyModel::observe(ModelObserver::class);
        DomainEvents::disable();
        $levels = [];

        DB::transaction(function () use (&$levels): void {
            $model = LegacyModel::create(['id' => 1, 'name' => 'legacy']);
            $levels[] = $this->transactionLevel();
            $model->name = 'legacy updated';
            $model->save();
            $levels[] = $this->transactionLevel();
            LegacyModel::create(['id' => 2, 'name' => 'another legacy'])->delete();
            $levels[] = $this->transactionLevel();
        });

        self::assertSame([1, 1, 1], $levels);
        self::assertSame([], $this->middleware->storedMessages);
        self::assertSame(0, $this->transactionLevel());
        self::assertSame('legacy updated', DB::table('legacy_models')->where('id', 1)->value('name'));
        self::assertSame(1, DB::table('legacy_models')->count());
    }

    #[Test]
    public function whenReEnabledTheDeprecatedObserverStoresEventsAgain(): void
    {
        LegacyModel::observe(ModelObserver::class);
        DomainEvents::disable();
        LegacyModel::create(['id' => 1, 'name' => 'silent']);

        DomainEvents::enable();
        set_error_handler(static fn (): bool => true, E_USER_DEPRECATED);
        try {
            LegacyModel::create(['id' => 2, 'name' => 'published']);
        } finally {
            restore_error_handler();
        }

        self::assertSame(['created'], $this->middleware->storedEventTypes());
        self::assertSame(0, $this->transactionLevel());
        self::assertSame(2, DB::table('legacy_models')->count());
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
