<?php

namespace Softonic\TransactionalEventPublisher\Jobs;

use Mockery;
use Psr\Log\LoggerInterface;
use Softonic\TransactionalEventPublisher\Contracts\EventMessageContract;
use Softonic\TransactionalEventPublisher\EventStoreMiddlewares\AmqpMiddleware;
use Softonic\TransactionalEventPublisher\TestCase;

class SendDomainEventsTest extends TestCase
{
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

        $logger = \Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('alert')
            ->never();

        $sendDomainEvents = new SendDomainEvents($message);
        $sendDomainEvents->handle($amqpMiddleware, $logger);
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

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage($warningMessage);

        $logger = \Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('alert')
            ->once()
            ->with($warningMessage);

        $sendDomainEvents = new SendDomainEvents($message, $logger);
        $sendDomainEvents->handle($amqpMiddleware, $logger);
    }
}
