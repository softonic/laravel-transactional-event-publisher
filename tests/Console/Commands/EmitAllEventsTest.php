<?php

namespace Softonic\TransactionalEventPublisher\Tests\Console\Commands;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Softonic\TransactionalEventPublisher\Jobs\SendDomainEvents;
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
    }

    /**
     * @test
     */
    public function whenRunCommandItShouldResendAllTheCurrentDomainEvents(): void
    {
        factory(DomainEvent::class, 4)->create();
        $this->expectsJobs(SendDomainEvents::class);
        $this->app->register('Softonic\TransactionalEventPublisher\ServiceProvider');
        $this->artisan('event-sourcing:emit-all')->run();

        $this->assertCount(4, $this->dispatchedJobs);
    }
}
