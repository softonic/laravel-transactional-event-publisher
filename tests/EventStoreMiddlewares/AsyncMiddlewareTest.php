<?php

namespace Softonic\TransactionalEventPublisher\EventStoreMiddlewares;

use Exception;
use Illuminate\Contracts\Bus\Dispatcher;
use Mockery;
use Softonic\TransactionalEventPublisher\Contracts\EventMessageContract;
use Softonic\TransactionalEventPublisher\Contracts\EventStoreMiddlewareContract;
use Softonic\TransactionalEventPublisher\Jobs\SendDomainEvents;
use Softonic\TransactionalEventPublisher\TestCase;

class AsyncMiddlewareTest extends TestCase
{
    private Dispatcher $commandBus;

    private AsyncMiddleware $asyncMiddleware;

    private EventMessageContract $eventMessage;

    public function setUp(): void
    {
        parent::setUp();

        $this->eventMessage = Mockery::mock(EventMessageContract::class);

        $eventPublisherMiddleware = Mockery::mock(EventStoreMiddlewareContract::class);
        $this->commandBus         = Mockery::mock(Dispatcher::class);

        $this->asyncMiddleware = new AsyncMiddleware($eventPublisherMiddleware, $this->commandBus);
    }

    /**
     * @test
     */
    public function whenStoreDomainEventFailsItShouldReturnFalse()
    {
        $this->commandBus->shouldReceive('dispatch')
            ->once()
            ->andThrow(Exception::class);

        self::assertFalse($this->asyncMiddleware->store($this->eventMessage));
    }

    /**
     * @test
     */
    public function whenStoreEventAndSendJobItShouldReturnTrue()
    {
        $this->commandBus->shouldReceive('dispatch')
            ->once()
            ->withArgs(function ($job) {
                return $job instanceof SendDomainEvents;
            });

        self::assertTrue($this->asyncMiddleware->store($this->eventMessage));
    }
}
