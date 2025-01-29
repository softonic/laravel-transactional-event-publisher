<?php

namespace Softonic\TransactionalEventPublisher\EventStoreMiddlewares;

use Exception;
use Illuminate\Support\Facades\Log;
use Mockery;
use PhpAmqpLib\Message\AMQPMessage;
use PHPUnit\Framework\Attributes\Test;
use Softonic\TransactionalEventPublisher\Factories\AmqpMessageFactory;
use Softonic\TransactionalEventPublisher\Interfaces\EventMessageInterface;
use Softonic\TransactionalEventPublisher\Services\Amqp;
use Softonic\TransactionalEventPublisher\TestCase;

class AmqpMiddlewareTest extends TestCase
{
    #[Test]
    public function whenStoringAMessageThrowAnExceptionAmqpMiddlewareShouldReturnFalse(): void
    {
        $message     = $this->getOneMessage();
        $amqpMessage = new AMQPMessage();
        $properties  = ['exchange' => 'exchange'];

        Log::shouldReceive('error')
            ->once();

        $amqpMessageFactory = Mockery::mock(AmqpMessageFactory::class);
        $amqpMessageFactory
            ->shouldReceive('make')
            ->once()
            ->andReturn($amqpMessage);

        $amqpChannelMock = Mockery::mock(Amqp::class);

        $amqpChannelMock
            ->shouldReceive('setUp')
            ->once();

        $amqpChannelMock
            ->shouldReceive('basic_publish')
            ->once()
            ->andThrow(Exception::class);

        $amqpMiddleware = new AmqpMiddleware($amqpMessageFactory, $amqpChannelMock, $properties);

        self::assertFalse($amqpMiddleware->store($message));
    }

    private function getOneMessage(): EventMessageInterface
    {
        $message            = Mockery::mock(EventMessageInterface::class);
        $message->site      = 'softonic';
        $message->service   = 'service';
        $message->eventType = 'created';
        $message->modelName = 'Model';
        $message->shouldReceive('jsonSerialize')
            ->andReturn('message');

        return $message;
    }

    #[Test]
    public function whenStoringMultipleMessagesThrowAnExceptionAmqpMiddlewareShouldReturnFalse(): void
    {
        $messages = $this->getTwoMessages();

        $amqpMessage = new AMQPMessage();
        $properties  = ['exchange' => 'exchange'];

        Log::shouldReceive('error')
            ->once();

        $amqpMessageFactory = Mockery::mock(AmqpMessageFactory::class);
        $amqpMessageFactory
            ->shouldReceive('make')
            ->twice()
            ->andReturn($amqpMessage);

        $amqpChannelMock = Mockery::mock(Amqp::class);
        $amqpChannelMock
            ->shouldReceive('setUp')
            ->once();
        $amqpChannelMock
            ->shouldReceive('batch_basic_publish')
            ->twice();
        $amqpChannelMock
            ->shouldReceive('publish_batch')
            ->once()
            ->andThrow(Exception::class);

        $amqpMiddleware = new AmqpMiddleware($amqpMessageFactory, $amqpChannelMock, $properties);

        self::assertFalse($amqpMiddleware->store(...$messages));
    }

    private function getTwoMessages(): array
    {
        $message            = Mockery::mock(EventMessageInterface::class);
        $message->site      = 'softonic';
        $message->service   = 'service';
        $message->eventType = 'updated';
        $message->modelName = 'Model';
        $message->shouldReceive('jsonSerialize')
            ->andReturn('message');

        return [
            $this->getOneMessage(),
            $message,
        ];
    }

    #[Test]
    public function whenStoringAMessageShouldReturnTrue(): void
    {
        $message     = $this->getOneMessage();
        $properties  = ['exchange' => 'exchange'];
        $amqpMessage = new AMQPMessage();

        $amqpMessageFactory = Mockery::mock(AmqpMessageFactory::class);
        $amqpMessageFactory
            ->shouldReceive('make')
            ->once()
            ->andReturn($amqpMessage);

        $amqpChannelMock = Mockery::mock(Amqp::class);
        $amqpChannelMock
            ->shouldReceive('setUp')
            ->once();
        $amqpChannelMock
            ->shouldReceive('basic_publish')
            ->once()
            ->with($amqpMessage, $properties['exchange'], 'service.created.model');

        $amqpMiddleware = new AmqpMiddleware($amqpMessageFactory, $amqpChannelMock, $properties);

        self::assertTrue($amqpMiddleware->store($message));
    }

    #[Test]
    public function whenStoringMultipleMessagesShouldReturnTrue(): void
    {
        $messages          = $this->getTwoMessages();
        $properties        = ['exchange' => 'exchange'];
        $firstAmqpMessage  = new AMQPMessage();
        $secondAmqpMessage = new AMQPMessage();

        $amqpMessageFactory = Mockery::mock(AmqpMessageFactory::class);
        $amqpMessageFactory
            ->shouldReceive('make')
            ->twice()
            ->andReturn($firstAmqpMessage, $secondAmqpMessage);

        $amqpChannelMock = Mockery::mock(Amqp::class);
        $amqpChannelMock
            ->shouldReceive('setUp')
            ->once();
        $amqpChannelMock
            ->shouldReceive('batch_basic_publish')
            ->once()
            ->with($firstAmqpMessage, $properties['exchange'], 'service.created.model');
        $amqpChannelMock
            ->shouldReceive('batch_basic_publish')
            ->once()
            ->with($secondAmqpMessage, $properties['exchange'], 'service.updated.model');
        $amqpChannelMock
            ->shouldReceive('publish_batch')
            ->once();

        $amqpMiddleware = new AmqpMiddleware($amqpMessageFactory, $amqpChannelMock, $properties);

        self::assertTrue($amqpMiddleware->store(...$messages));
    }

    public function testConfigurableRoutingKey(): void
    {
        $message            = $this->getOneMessage();
        $properties         = [
            'exchange'          => 'exchange',
            'routing_key_fields' => ['site', 'service', 'eventType', 'modelName'],
        ];
        $message->site      = 'softonic';
        $message->service   = 'service';
        $message->eventType = 'created';
        $message->modelName = 'Model';
        $amqpMessage        = new AMQPMessage();

        $amqpMessageFactory = Mockery::mock(AmqpMessageFactory::class);
        $amqpMessageFactory
            ->shouldReceive('make')
            ->once()
            ->andReturn($amqpMessage);

        $amqpChannelMock = Mockery::mock(Amqp::class);
        $amqpChannelMock
            ->shouldReceive('setUp')
            ->once();
        $amqpChannelMock
            ->shouldReceive('basic_publish')
            ->once()
            ->with($amqpMessage, $properties['exchange'], 'softonic.service.created.model');

        $amqpMiddleware = new AmqpMiddleware($amqpMessageFactory, $amqpChannelMock, $properties);

        self::assertTrue($amqpMiddleware->store($message));
    }

    public function testConfigurableRoutingKeyForMultipleMessages(): void
    {
        $messages          = $this->getTwoMessages();
        $properties        = [
            'exchange'          => 'exchange',
            'routing_key_fields' => ['site', 'service', 'eventType', 'modelName'],
        ];
        $firstAmqpMessage  = new AMQPMessage();
        $secondAmqpMessage = new AMQPMessage();

        $amqpMessageFactory = Mockery::mock(AmqpMessageFactory::class);
        $amqpMessageFactory
            ->shouldReceive('make')
            ->twice()
            ->andReturn($firstAmqpMessage, $secondAmqpMessage);

        $amqpChannelMock = Mockery::mock(Amqp::class);
        $amqpChannelMock
            ->shouldReceive('setUp')
            ->once();
        $amqpChannelMock
            ->shouldReceive('batch_basic_publish')
            ->once()
            ->with($firstAmqpMessage, $properties['exchange'], 'softonic.service.created.model');
        $amqpChannelMock
            ->shouldReceive('batch_basic_publish')
            ->once()
            ->with($secondAmqpMessage, $properties['exchange'], 'softonic.service.updated.model');
        $amqpChannelMock
            ->shouldReceive('publish_batch')
            ->once();


        $amqpMiddleware = new AmqpMiddleware($amqpMessageFactory, $amqpChannelMock, $properties);

        self::assertTrue($amqpMiddleware->store(...$messages));
    }
}
