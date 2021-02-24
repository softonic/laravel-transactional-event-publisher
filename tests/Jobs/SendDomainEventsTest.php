<?php

namespace Softonic\TransactionalEventPublisher\Jobs;

use Illuminate\Contracts\Bus\Dispatcher;
use Mockery;
use Psr\Log\LoggerInterface;
use Softonic\TransactionalEventPublisher\Contracts\EventMessageContract;
use Softonic\TransactionalEventPublisher\Contracts\EventStoreMiddlewareContract;
use Softonic\TransactionalEventPublisher\TestCase;

function sleep($time)
{
    SendDomainEventsTest::$functions->sleep($time);
}

class SendDomainEventsTest extends TestCase
{
    private EventStoreMiddlewareContract $eventPublisherMiddleware;

    private Dispatcher $dispatcher;

    private LoggerInterface $logger;

    public static $functions;

    public function setUp(): void
    {
        parent::setUp();

        $this->eventMessage = Mockery::mock(EventMessageContract::class);

        $this->eventPublisherMiddleware = Mockery::mock(EventStoreMiddlewareContract::class);
        $this->dispatcher               = Mockery::mock(Dispatcher::class);
        $this->logger                   = Mockery::mock(LoggerInterface::class);

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
            'single message'    => [
                'messages' => [$firstMessage],
            ],
            'multiple messages' => [
                'messages' => [
                    $firstMessage,
                    $secondMessage,
                ],
            ],
        ];
    }

    /**
     * @test
     * @dataProvider messagesToSendProvider
     */
    public function whenMessageIsSendItShouldResumeTheJob(array $messages): void
    {
        $this->eventPublisherMiddleware->shouldReceive('store')
            ->once()
            ->with(...$messages)
            ->andReturn(true);

        $this->dispatcher->shouldNotReceive('dispatch');

        $this->logger->shouldNotReceive('alert');

        $sendDomainEvents = new SendDomainEvents($this->eventPublisherMiddleware, 0, ...$messages);
        $sendDomainEvents->handle($this->dispatcher, $this->logger);
    }

    /**
     * @test
     * @dataProvider messagesToSendProvider
     */
    public function whenMessageIsSendWithExponentialRetryItShouldResumeTheJobWaitingASpecificTime(array $messages)
    {
        $this->eventPublisherMiddleware->shouldReceive('store')
            ->once()
            ->with(...$messages)
            ->andReturn(true);

        $this->dispatcher->shouldNotReceive('dispatch');

        $this->logger->shouldReceive('alert')
            ->never();

        self::$functions->shouldNotReceive('sleep');

        $sendDomainEvents = new SendDomainEvents($this->eventPublisherMiddleware, 0, ...$messages);
        $sendDomainEvents->handle($this->dispatcher, $this->logger);
    }

    /**
     * @test
     * @dataProvider messagesToSendProvider
     */
    public function whenMessageCannotBeSendItShouldTryAgainLater(array $messages)
    {
        $warningMessage = "The event couldn't be sent. Retrying message: " . json_encode($messages);

        $this->eventPublisherMiddleware->shouldReceive('store')
            ->once()
            ->with(...$messages)
            ->andReturn(false);

        $this->dispatcher->shouldReceive('dispatch')
            ->once();

        $this->logger->shouldReceive('alert')
            ->once()
            ->with($warningMessage);

        self::$functions->shouldReceive('sleep')->with(9)->once();

        $sendDomainEvents = new SendDomainEvents($this->eventPublisherMiddleware, 2, ...$messages);
        $sendDomainEvents->handle($this->dispatcher, $this->logger);
    }
}
