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

    public function setUp(): void
    {
        parent::setUp();

        self::$functions = Mockery::mock();
    }

    public function messagesToSendProvider(): array
    {
        $firstMessage = Mockery::mock(EventMessageContract::class);
        $firstMessage->shouldReceive('jsonSerialize')
            ->andReturn('message');
        $secondMessage = Mockery::mock(EventMessageContract::class);
        $secondMessage->shouldReceive('jsonSerialize')
            ->andReturn('message');

        return [
            'single message' => [
                'messages' => [$firstMessage],
            ],
            'multiple messages' => [
                'messages' => [
                    $firstMessage,
                    $secondMessage,
                ]
            ]
        ];
    }

    /**
     * @test
     * @dataProvider messagesToSendProvider
     */
    public function whenMessageIsSendItShouldResumeTheJob($messages): void
    {
        $amqpMiddleware = Mockery::mock(AmqpMiddleware::class);

        $amqpMiddleware->shouldReceive('store')
            ->once()
            ->with(...$messages)
            ->andReturn(true);

        $dispatcher = Mockery::mock(Dispatcher::class);
        $dispatcher->shouldNotReceive('dispatch');

        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldNotReceive('alert');

        $sendDomainEvents = new SendDomainEvents(0, ...$messages);
        $sendDomainEvents->handle($amqpMiddleware, $dispatcher, $logger);
    }

    /**
     * @test
     * @dataProvider messagesToSendProvider
     */
    public function whenMessageIsSendWithExponentialRetryItShouldResumeTheJobWaitingASpecificTime($messages)
    {
        $amqpMiddleware = Mockery::mock(AmqpMiddleware::class);

        $amqpMiddleware->shouldReceive('store')
            ->once()
            ->with(...$messages)
            ->andReturn(true);

        $dispatcher = Mockery::mock(Dispatcher::class);
        $dispatcher->shouldNotReceive('dispatch');

        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('alert')
            ->never();

        self::$functions->shouldNotReceive('sleep');

        $sendDomainEvents = new SendDomainEvents(0, ...$messages);
        $sendDomainEvents->handle($amqpMiddleware, $dispatcher, $logger);
    }

    /**
     * @test
     * @dataProvider messagesToSendProvider
     */
    public function whenMessageCannotBeSendItShouldTryAgainLater($messages)
    {
        $amqpMiddleware = Mockery::mock(AmqpMiddleware::class);
        $warningMessage = "The event could't be sent. Retrying message: " . json_encode($messages);

        $amqpMiddleware->shouldReceive('store')
            ->once()
            ->with(...$messages)
            ->andReturn(false);

        $dispatcher = Mockery::mock(Dispatcher::class);
        $dispatcher->shouldReceive('dispatch')
            ->once();

        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('alert')
            ->once()
            ->with($warningMessage);

        $sendDomainEvents = new SendDomainEvents(2, ...$messages);

        self::$functions->shouldReceive('sleep')->with(9)->once();

        $sendDomainEvents->handle($amqpMiddleware, $dispatcher, $logger);
    }
}
