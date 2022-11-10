<?php

namespace Softonic\TransactionalEventPublisher\Tests\Console\Commands;

use Illuminate\Contracts\Bus\Dispatcher as BusDispatcherContract;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use Softonic\TransactionalEventPublisher\EventStoreMiddlewares\AmqpMiddleware;
use Softonic\TransactionalEventPublisher\Model\DomainEvent;
use Softonic\TransactionalEventPublisher\TestCase;

class EmitAllEventsTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Setup the test environment.
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__ . '/../../../database/migrations');

        config()->set('transactional-event-publisher.event_publisher_middleware', AmqpMiddleware::class);
    }

    /**
     * @test
     */
    public function whenRunCommandItShouldResendAllTheCurrentDomainEvents(): void
    {
        factory(DomainEvent::class, 4)->create();

        $mock = Mockery::mock(BusDispatcherContract::class);
        $mock->shouldReceive('dispatch')->andReturnUsing(function ($dispatched) {
            $this->dispatchedJobs[] = $dispatched;
        });
        $this->app->instance(BusDispatcherContract::class, $mock);
        $this->app->register('Softonic\TransactionalEventPublisher\ServiceProvider');
        $this->artisan('event-sourcing:emit-all')->run();

        self::assertCount(4, $this->dispatchedJobs);
    }

    /**
     * @test
     */
    public function whenRunCommandWithBatchSizeItShouldResendAllTheCurrentDomainEventsInBatch(): void
    {
        factory(DomainEvent::class, 4)->create();
        $mock = Mockery::mock(BusDispatcherContract::class);
        $mock->shouldReceive('dispatch')->andReturnUsing(function ($dispatched) {
            $this->dispatchedJobs[] = $dispatched;
        });
        $this->app->instance(BusDispatcherContract::class, $mock);
        $this->app->register('Softonic\TransactionalEventPublisher\ServiceProvider');
        $this->artisan('event-sourcing:emit-all --batchSize=2')->run();

        self::assertCount(2, $this->dispatchedJobs);
    }
}
