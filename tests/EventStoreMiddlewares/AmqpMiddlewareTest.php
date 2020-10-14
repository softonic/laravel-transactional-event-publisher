<?php

namespace Softonic\TransactionalEventPublisher\Tests\EventStoreMiddlewares;

use Softonic\Amqp\Amqp;
use Mockery;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;
use Softonic\TransactionalEventPublisher\Contracts\EventMessageContract;
use Softonic\TransactionalEventPublisher\EventStoreMiddlewares\AmqpMiddleware;
use Softonic\TransactionalEventPublisher\Factories\AmqpMessageFactory;
use Softonic\TransactionalEventPublisher\TestCase;

class AmqpMiddlewareTest extends TestCase
{
    public function testWhenStoringAMessageThrowAnExceptionAmqpMiddlewareShouldReturnFalse()
    {
        $message     = $this->getOneMessage();
        $amqpMessage = new AMQPMessage();
        $properties  = ['AMQP properties'];

        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('error')
            ->once();

        $amqpMessageFactory = Mockery::mock(AmqpMessageFactory::class);
        $amqpMessageFactory
            ->shouldReceive('make')
            ->once()
            ->andReturn($amqpMessage);

        $amqpMock = Mockery::mock(Amqp::class);
        $amqpMock
            ->shouldReceive('publish')
            ->once()
            ->andThrow('\Exception');

        $amqpMiddleware = new AmqpMiddleware(
            $amqpMessageFactory,
            $amqpMock,
            $properties,
            $logger
        );

        self::assertFalse($amqpMiddleware->store($message));
    }

    private function getOneMessage(): EventMessageContract
    {
        $message            = Mockery::mock(EventMessageContract::class);
        $message->site      = 'softonic';
        $message->service   = 'service';
        $message->eventType = 'created';
        $message->modelName = 'Model';
        $message->shouldReceive('jsonSerialize')
            ->andReturn('message');

        return $message;
    }

    public function testWhenStoringMultipleMessagesThrowAnExceptionAmqpMiddlewareShouldReturnFalse()
    {
        $messages = $this->getTwoMessages();

        $amqpMessage = new AMQPMessage();
        $properties  = ['AMQP properties'];

        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('error')
            ->once();

        $amqpMessageFactory = Mockery::mock(AmqpMessageFactory::class);
        $amqpMessageFactory
            ->shouldReceive('make')
            ->twice()
            ->andReturn($amqpMessage);

        $amqpMock = Mockery::mock(Amqp::class);
        $amqpMock
            ->shouldReceive('batchBasicPublish')
            ->twice();
        $amqpMock
            ->shouldReceive('batchPublish')
            ->once()
            ->andThrow('\Exception');

        $amqpMiddleware = new AmqpMiddleware(
            $amqpMessageFactory,
            $amqpMock,
            $properties,
            $logger
        );

        self::assertFalse($amqpMiddleware->store(...$messages));
    }

    private function getTwoMessages(): array
    {
        $message            = Mockery::mock(EventMessageContract::class);
        $message->site      = 'softonic';
        $message->service   = 'service';
        $message->eventType = 'updated';
        $message->modelName = 'Model';
        $message->shouldReceive('jsonSerialize')
            ->andReturn('message');

        return [
            $this->getOneMessage(),
            $message
        ];
    }

    public function testWhenStoringAMessageShouldReturnTrue()
    {
        $message     = $this->getOneMessage();
        $properties  = ['AMQP properties'];
        $logger      = Mockery::mock(LoggerInterface::class);
        $amqpMessage = new AMQPMessage();

        $amqpMock = Mockery::mock(Amqp::class);
        $amqpMock
            ->shouldReceive('publish')
            ->once()
            ->with('service.created.model', $amqpMessage, $properties);

        $amqpMessageFactory = Mockery::mock(AmqpMessageFactory::class);
        $amqpMessageFactory
            ->shouldReceive('make')
            ->once()
            ->andReturn($amqpMessage);

        $amqpMiddleware = new AmqpMiddleware($amqpMessageFactory, $amqpMock, $properties, $logger);

        self::assertTrue($amqpMiddleware->store($message));
    }

    public function testWhenStoringMultipleMessagesShouldReturnTrue()
    {
        $messages     = $this->getTwoMessages();
        $properties  = ['AMQP properties'];
        $logger      = Mockery::mock(LoggerInterface::class);
        $firstAmqpMessage = new AMQPMessage();
        $secondAmqpMessage = new AMQPMessage();

        $amqpMock = Mockery::mock(Amqp::class);
        $amqpMock
            ->shouldReceive('batchBasicPublish')
            ->once()
            ->with('service.created.model', $firstAmqpMessage);
        $amqpMock
            ->shouldReceive('batchBasicPublish')
            ->once()
            ->with('service.updated.model', $secondAmqpMessage);
        $amqpMock
            ->shouldReceive('batchPublish')
            ->once()
            ->with($properties);

        $amqpMessageFactory = Mockery::mock(AmqpMessageFactory::class);
        $amqpMessageFactory
            ->shouldReceive('make')
            ->twice()
            ->andReturn($firstAmqpMessage, $secondAmqpMessage);

        $amqpMiddleware = new AmqpMiddleware($amqpMessageFactory, $amqpMock, $properties, $logger);

        self::assertTrue($amqpMiddleware->store(...$messages));
    }

    public function testConfigurableRoutingKey()
    {
        $message            = $this->getOneMessage();
        $properties         = [
            'routing_key_fields' => ['site', 'service', 'eventType', 'modelName'],
        ];
        $logger             = Mockery::mock(LoggerInterface::class);
        $message->site      = 'softonic';
        $message->service   = 'service';
        $message->eventType = 'created';
        $message->modelName = 'Model';
        $amqpMessage        = new AMQPMessage();

        $amqpMock = Mockery::mock(Amqp::class);
        $amqpMock
            ->shouldReceive('publish')
            ->once()
            ->with('softonic.service.created.model', $amqpMessage, $properties);

        $amqpMessageFactory = Mockery::mock(AmqpMessageFactory::class);
        $amqpMessageFactory
            ->shouldReceive('make')
            ->once()
            ->andReturn($amqpMessage);

        $amqpMiddleware = new AmqpMiddleware($amqpMessageFactory, $amqpMock, $properties, $logger);

        self::assertTrue($amqpMiddleware->store($message));
    }

    public function testConfigurableRoutingKeyForMultipleMessages()
    {
        $messages            = $this->getTwoMessages();
        $properties         = [
            'routing_key_fields' => ['site', 'service', 'eventType', 'modelName'],
        ];
        $logger             = Mockery::mock(LoggerInterface::class);
        $firstAmqpMessage = new AMQPMessage();
        $secondAmqpMessage = new AMQPMessage();

        $amqpMock = Mockery::mock(Amqp::class);
        $amqpMock
            ->shouldReceive('batchBasicPublish')
            ->once()
            ->with('softonic.service.created.model', $firstAmqpMessage);
        $amqpMock
            ->shouldReceive('batchBasicPublish')
            ->once()
            ->with('softonic.service.updated.model', $secondAmqpMessage);
        $amqpMock
            ->shouldReceive('batchPublish')
            ->once()
            ->with($properties);

        $amqpMessageFactory = Mockery::mock(AmqpMessageFactory::class);
        $amqpMessageFactory
            ->shouldReceive('make')
            ->twice()
            ->andReturn($firstAmqpMessage, $secondAmqpMessage);

        $amqpMiddleware = new AmqpMiddleware($amqpMessageFactory, $amqpMock, $properties, $logger);

        self::assertTrue($amqpMiddleware->store(...$messages));
    }
}
