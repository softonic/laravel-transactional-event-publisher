<?php

namespace Softonic\TransactionalEventPublisher\Fixtures;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Override;
use Softonic\TransactionalEventPublisher\ServiceProvider;
use Softonic\TransactionalEventPublisher\TestCase;

/**
 * Base test case running real Eloquent models against an in-memory sqlite database,
 * with the package configured to use a spy middleware and a fake message builder.
 */
abstract class DomainEventsTestCase extends TestCase
{
    protected SpyEventStoreMiddleware $middleware;

    protected FakeEventMessageBuilder $builder;

    #[Override]
    protected function getPackageProviders($app): array
    {
        return [ServiceProvider::class];
    }

    #[Override]
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        $app['config']->set('transactional-event-publisher.middleware', SpyEventStoreMiddleware::class);
        $app['config']->set('transactional-event-publisher.messageBuilder', FakeEventMessageBuilder::class);
    }

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->middleware = new SpyEventStoreMiddleware();
        $this->builder = new FakeEventMessageBuilder();
        $this->app->instance(SpyEventStoreMiddleware::class, $this->middleware);
        $this->app->instance(FakeEventMessageBuilder::class, $this->builder);

        Schema::enableForeignKeyConstraints();

        Schema::create('publishing_models', function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->string('name')->unique();
            $table->integer('counter')->default(0);
        });

        Schema::create('publishing_model_children', function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->integer('publishing_model_id');
            $table->foreign('publishing_model_id')->references('id')->on('publishing_models');
        });

        Schema::create('legacy_models', function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->string('name')->unique();
        });
    }

    protected function transactionLevel(): int
    {
        return DB::connection()->transactionLevel();
    }

    protected function seedPublishingModel(int $id, string $name): void
    {
        DB::table('publishing_models')->insert(['id' => $id, 'name' => $name]);
    }

    protected function publishingModelName(int $id): ?string
    {
        return DB::table('publishing_models')->where('id', $id)->value('name');
    }

    protected function publishingModelExists(int $id): bool
    {
        return DB::table('publishing_models')->where('id', $id)->exists();
    }
}
