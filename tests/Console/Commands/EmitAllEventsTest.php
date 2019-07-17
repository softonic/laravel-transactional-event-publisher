<?php

namespace Softonic\TransactionalEventPublisher\Console\Commands;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Softonic\TransactionalEventPublisher\Jobs\SendDomainEvents;
use Softonic\TransactionalEventPublisher\Model\DomainEvent;
use Softonic\TransactionalEventPublisher\TestCase;

class EmitAllEventsTest extends TestCase
{
    //use DatabaseTransactions;

    public function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate:fresh')->run();
    }

    /**
     * @test
     */
    public function whenRunCommandItShouldResendAllTheCurrentDomainEvents(): void
    {
        factory(DomainEvent::class, 4)->create();

        $this->expectsJobs(SendDomainEvents::class);

        Artisan::call('event-sourcing:emit-all', ['queueConnection' => 'sync']);

        $this->assertCount(4, $this->dispatchedJobs);
    }
}
