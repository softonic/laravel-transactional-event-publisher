<?php

namespace Softonic\TransactionalEventPublisher\EventStoreMiddlewares;

use Exception;
use Illuminate\Contracts\Bus\Dispatcher;
use Mockery;
use Softonic\TransactionalEventPublisher\Contracts\EventMessageContract;
use Softonic\TransactionalEventPublisher\Jobs\SendDomainEvents;
use Softonic\TransactionalEventPublisher\TestCase;

class AsyncAmqpMiddlewareTest extends TestCase
{
    /**
     * @test
     */
    public function whenStoreDomainEventFailsItShouldReturnFalse()
    {
        $eventMessage        = Mockery::mock(EventMessageContract::class);
        $commandBus          = Mockery::mock(Dispatcher::class);
        $asyncAmqpMiddleware = new AsyncAmqpMiddleware($commandBus);

        $commandBus->shouldReceive('dispatch')
            ->once()
            ->andThrow(Exception::class);

        $this->assertFalse($asyncAmqpMiddleware->store($eventMessage));
    }

    /**
     * @test
     */
    public function whenStoreEventAndSendJobItShouldReturnTrue()
    {
        $eventMessage        = Mockery::mock(EventMessageContract::class);
        $commandBus          = Mockery::mock(Dispatcher::class);
        $asyncAmqpMiddleware = new AsyncAmqpMiddleware($commandBus);

        $commandBus->shouldReceive('dispatch')
            ->once()
            ->withArgs(function ($job) {
                return $job instanceof SendDomainEvents;
            });

        $this->assertTrue($asyncAmqpMiddleware->store($eventMessage));
    }
}
