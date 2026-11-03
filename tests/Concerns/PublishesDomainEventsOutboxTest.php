<?php

declare(strict_types=1);

namespace Softonic\TransactionalEventPublisher\Concerns;

use Closure;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Softonic\TransactionalEventPublisher\EventStoreMiddlewares\DatabaseMiddleware;
use Softonic\TransactionalEventPublisher\Exceptions\EventStoreFailedException;
use Softonic\TransactionalEventPublisher\Fixtures\DomainEventsTestCase;
use Softonic\TransactionalEventPublisher\Fixtures\PublishingModel;
use Softonic\TransactionalEventPublisher\Fixtures\SpyEventStoreMiddleware;
use Throwable;

/**
 * The event stored by a middleware that writes on the same connection (the database outbox) must be rolled back
 * together with the model write when a later middleware fails.
 */
class PublishesDomainEventsOutboxTest extends DomainEventsTestCase
{
    private const int CALLER_ROW_ID = 99;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('domain_events', static function (Blueprint $table): void {
            $table->increments('id');
            $table->text('message');
        });

        config()->set(
            'transactional-event-publisher.middleware',
            [DatabaseMiddleware::class, SpyEventStoreMiddleware::class]
        );
        $this->seedPublishingModel(1, 'existing');
    }

    /**
     * @return array<string, array{Closure(): mixed, Closure(self): void}>
     */
    public static function writeProvider(): array
    {
        return [
            'insert' => [
                static fn () => PublishingModel::create(['id' => 2, 'name' => 'new']),
                static fn (self $test) => self::assertFalse($test->publishingModelExists(2)),
            ],
            'update' => [
                static fn () => PublishingModel::findOrFail(1)->update(['name' => 'updated']),
                static fn (self $test) => self::assertSame('existing', $test->publishingModelName(1)),
            ],
            'increment' => [
                static fn () => PublishingModel::findOrFail(1)->increment('counter'),
                static fn () => self::assertSame(0, (int) DB::table('publishing_models')->where('id', 1)->value('counter')),
            ],
            'delete' => [
                static fn () => PublishingModel::findOrFail(1)->delete(),
                static fn (self $test) => self::assertTrue($test->publishingModelExists(1)),
            ],
        ];
    }

    /**
     * @return array<string, array{Closure(): mixed}>
     */
    public static function successfulWriteProvider(): array
    {
        return array_map(static fn (array $case): array => [$case[0]], self::writeProvider());
    }

    #[Test]
    #[DataProvider('successfulWriteProvider')]
    public function whenEveryMiddlewareSucceedsTheEventIsStoredInTheOutboxWithTheWrite(Closure $write): void
    {
        $write();

        self::assertSame(1, DB::table('domain_events')->count());
        self::assertCount(1, $this->middleware->storedMessages);
        self::assertSame(0, $this->transactionLevel());
    }

    #[Test]
    #[DataProvider('writeProvider')]
    public function whenALaterMiddlewareFailsTheOutboxEventIsRolledBackWithTheWrite(
        Closure $write,
        Closure $assertWriteNotApplied
    ): void {
        $this->middleware->result = false;

        $caught = $this->catchThrowable($write);

        self::assertInstanceOf(EventStoreFailedException::class, $caught);
        self::assertSame(0, DB::table('domain_events')->count());
        $assertWriteNotApplied($this);
        self::assertSame(0, $this->transactionLevel());
    }

    #[Test]
    #[DataProvider('writeProvider')]
    public function whenTheCallerCatchesTheFailureOnlyTheWriteAndItsOutboxEventAreRolledBack(
        Closure $write,
        Closure $assertWriteNotApplied
    ): void {
        $this->middleware->result = false;
        $levelAfterFailure = null;
        $caught = null;

        DB::transaction(function () use ($write, &$levelAfterFailure, &$caught): void {
            DB::table('publishing_models')->insert(['id' => self::CALLER_ROW_ID, 'name' => 'caller before']);
            $caught = $this->catchThrowable($write);
            $levelAfterFailure = $this->transactionLevel();
            DB::table('publishing_models')->where('id', self::CALLER_ROW_ID)->update(['name' => 'caller after']);
        });

        self::assertInstanceOf(EventStoreFailedException::class, $caught);
        self::assertSame(1, $levelAfterFailure);
        self::assertSame(0, $this->transactionLevel());
        self::assertSame('caller after', $this->publishingModelName(self::CALLER_ROW_ID));
        self::assertSame(0, DB::table('domain_events')->count());
        $assertWriteNotApplied($this);
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
}
