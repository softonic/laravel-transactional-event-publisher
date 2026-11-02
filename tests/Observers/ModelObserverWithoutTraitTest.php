<?php

namespace Softonic\TransactionalEventPublisher\Observers;

use Closure;
use Illuminate\Support\Facades\DB;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Softonic\TransactionalEventPublisher\DomainEvents;
use Softonic\TransactionalEventPublisher\Exceptions\ModelWithoutPublishesDomainEventsException;
use Softonic\TransactionalEventPublisher\Fixtures\DomainEventsTestCase;
use Softonic\TransactionalEventPublisher\Fixtures\LegacyModel;
use Throwable;

/**
 * The ModelObserver on models without the PublishesDomainEvents trait: every write is rejected before reaching the
 * database, without storing events nor leaving transaction levels open.
 */
class ModelObserverWithoutTraitTest extends DomainEventsTestCase
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        LegacyModel::observe(ModelObserver::class);
        DB::table('legacy_models')->insert(['id' => 1, 'name' => 'existing']);
    }

    /**
     * @return array<string, array{Closure(): mixed}>
     */
    public static function writeProvider(): array
    {
        return [
            'create' => [static fn () => LegacyModel::create(['id' => 2, 'name' => 'new'])],
            'update' => [static fn () => LegacyModel::findOrFail(1)->update(['name' => 'updated'])],
            'increment' => [static fn () => LegacyModel::findOrFail(1)->increment('id')],
            'delete' => [static fn () => LegacyModel::findOrFail(1)->delete()],
        ];
    }

    #[Test]
    #[DataProvider('writeProvider')]
    public function whenTheModelIsWrittenItThrowsWithoutWritingNorStoringEvents(Closure $write): void
    {
        $caught = $this->catchThrowable($write);

        self::assertInstanceOf(ModelWithoutPublishesDomainEventsException::class, $caught);
        self::assertStringContainsString(LegacyModel::class, $caught->getMessage());
        $this->assertNothingChanged();
    }

    #[Test]
    #[DataProvider('writeProvider')]
    public function whenTheModelIsWrittenInsideATransactionNoTransactionLevelIsLeftOpen(Closure $write): void
    {
        $caught = $this->catchThrowable(static fn () => DB::transaction($write));

        self::assertInstanceOf(ModelWithoutPublishesDomainEventsException::class, $caught);
        $this->assertNothingChanged();
    }

    #[Test]
    #[DataProvider('writeProvider')]
    public function whenPublishingIsDisabledItThrowsAnyway(Closure $write): void
    {
        DomainEvents::disable();

        $caught = $this->catchThrowable($write);

        self::assertInstanceOf(ModelWithoutPublishesDomainEventsException::class, $caught);
        $this->assertNothingChanged();
    }

    private function catchThrowable(Closure $callback): ?Throwable
    {
        try {
            $callback();
        } catch (Throwable $exception) {
            return $exception;
        }

        return null;
    }

    private function assertNothingChanged(): void
    {
        self::assertSame(0, $this->transactionLevel());
        self::assertSame([], $this->middleware->storedMessages);
        self::assertSame(
            [['id' => 1, 'name' => 'existing']],
            DB::table('legacy_models')->get()->map(static fn (object $row): array => (array) $row)->all()
        );
    }
}
