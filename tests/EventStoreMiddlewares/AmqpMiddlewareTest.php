<?php

namespace Softonic\TransactionalEventPublisher\Tests\EventStoreMiddlewares;

use Bschmitt\Amqp\Amqp;
use Mockery;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;
use Softonic\TransactionalEventPublisher\EventStoreMiddlewares\AmqpMiddleware;
use Softonic\TransactionalEventPublisher\Factories\AmqpMessageFactory;
use Softonic\TransactionalEventPublisher\TestCase;
use Softonic\TransactionalEventPublisher\ValueObjects\EventMessage;

class AmqpMiddlewareTest extends TestCase
{
    public function testWhenStoringAMessageThrowAnExceptionAmqpMiddlewareShouldReturnFalse()
    {
        $message     = Mockery::mock(EventMessage::class);
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

        $this->assertFalse($amqpMiddleware->store($message));
    }

    public function testWhenStoringAMessageShouldReturnTrue()
    {
        $message            = Mockery::mock(EventMessage::class);
        $properties         = ['AMQP properties'];
        $logger             = Mockery::mock(LoggerInterface::class);
        $message->service   = 'service';
        $message->eventType = 'created';
        $message->modelName = 'Model';
        $amqpMessage        = new AMQPMessage();

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

        $this->assertTrue($amqpMiddleware->store($message));
    }

    public function testConfigurableRoutingKey()
    {
        $message            = Mockery::mock(EventMessage::class);
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

        $this->assertTrue($amqpMiddleware->store($message));
    }
}
