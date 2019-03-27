<?php

namespace Softonic\TransactionalEventPublisher\Jobs;

use Illuminate\Contracts\Bus\Dispatcher;
use Mockery;
use Psr\Log\LoggerInterface;
use Softonic\TransactionalEventPublisher\Contracts\EventMessageContract;
use Softonic\TransactionalEventPublisher\EventStoreMiddlewares\AmqpMiddleware;
use Softonic\TransactionalEventPublisher\TestCase;

function sleep($time)
{
    SendDomainEventsTest::$functions->sleep($time);
}

class SendDomainEventsTest extends TestCase
{
    public static $functions;

    protected function setUp()
    {
        parent::setUp();

        self::$functions = Mockery::mock();
    }

    /**
     * @test
     */
    public function whenMessageIsSendItShouldResumeTheJob()
    {
        $message = Mockery::mock(EventMessageContract::class);
        $message->shouldReceive('jsonSerialize')
            ->andReturn('message');

        $amqpMiddleware = Mockery::mock(AmqpMiddleware::class);

        $amqpMiddleware->shouldReceive('store')
            ->once()
            ->with($message)
            ->andReturn(true);

        $dispatcher = Mockery::mock(Dispatcher::class);
        $dispatcher->shouldNotReceive('dispatch');

        $logger = \Mockery::mock(LoggerInterface::class);
        $logger->shouldNotReceive('alert');

        $sendDomainEvents = new SendDomainEvents($message);
        $sendDomainEvents->handle($amqpMiddleware, $dispatcher, $logger);
    }

    /**
     * @test
     */
    public function whenMessageIsSendWithExponentialRetryItShouldResumeTheJobWaitingASpecificTime()
    {
        $message = Mockery::mock(EventMessageContract::class);
        $message->shouldReceive('jsonSerialize')
            ->andReturn('message');

        $amqpMiddleware = Mockery::mock(AmqpMiddleware::class);

        $amqpMiddleware->shouldReceive('store')
            ->once()
            ->with($message)
            ->andReturn(true);

        $dispatcher = Mockery::mock(Dispatcher::class);
        $dispatcher->shouldNotReceive('dispatch');

        $logger = \Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('alert')
            ->never();

        self::$functions->shouldNotReceive('sleep');

        $sendDomainEvents = new SendDomainEvents($message);
        $sendDomainEvents->handle($amqpMiddleware, $dispatcher, $logger);
    }

    /**
     * @test
     */
    public function whenMessageCannotBeSendItShouldTryAgainLater()
    {
        $message = Mockery::mock(EventMessageContract::class);
        $message->shouldReceive('jsonSerialize')
            ->andReturn('message');

        $amqpMiddleware = Mockery::mock(AmqpMiddleware::class);
        $warningMessage = "The event could't be sent. Retrying message: " . json_encode($message);

        $amqpMiddleware->shouldReceive('store')
            ->once()
            ->with($message)
            ->andReturn(false);

        $dispatcher = Mockery::mock(Dispatcher::class);
        $dispatcher->shouldReceive('dispatch')
            ->once();

        $logger = \Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('alert')
            ->once()
            ->with($warningMessage);

        $sendDomainEvents = new SendDomainEvents($message, 2);

        self::$functions->shouldReceive('sleep')->with(9)->once();

        $sendDomainEvents->handle($amqpMiddleware, $dispatcher, $logger);
    }
}
