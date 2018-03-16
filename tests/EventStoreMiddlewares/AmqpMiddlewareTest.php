<?php

namespace Softonic\TransactionalEventPublisher\Tests\EventStoreMiddlewares;

use Bschmitt\Amqp\Amqp;
use Illuminate\Database\Eloquent\Model;
use PhpAmqpLib\Message\AMQPMessage;
use PHPUnit\Framework\TestCase;
use Softonic\TransactionalEventPublisher\EventStoreMiddlewares\AmqpMiddleware;
use Softonic\TransactionalEventPublisher\Factories\AmqpMessageFactory;
use Softonic\TransactionalEventPublisher\ValueObjects\EventMessage;

class AmqpMiddlewareTest extends TestCase
{
    public function testWhenStoringAMessageThrowAnExceptionAmqpMiddlewareShouldReturnFalse()
    {
        $message = \Mockery::mock(EventMessage::class);
        $amqpMessage = new AMQPMessage();
        $properties = ['AMQP properties'];

        $amqpMessageFactory = \Mockery::mock(AmqpMessageFactory::class);
        $amqpMessageFactory
            ->shouldReceive('make')
            ->once()
            ->andReturn($amqpMessage);

        $amqpMock = \Mockery::mock(Amqp::class);
        $amqpMock
            ->shouldReceive('publish')
            ->once()
            ->andThrow('\Exception');

        $amqpMiddleware = new AmqpMiddleware($amqpMessageFactory, $amqpMock, $properties);

        $this->assertFalse($amqpMiddleware->store($message));
    }

    public function testWhenStoringAMessageShouldReturnTrue()
    {
        $message = \Mockery::mock(EventMessage::class);
        $properties = ['AMQP properties'];
        $message->service = 'service';
        $message->eventType = 'created';
        $message->modelName = 'model';
        $amqpMessage = new AMQPMessage();

        $amqpMock = \Mockery::mock(Amqp::class);
        $amqpMock
            ->shouldReceive('publish')
            ->once()
            ->with('service.created.model', $amqpMessage, $properties);

        $amqpMessageFactory = \Mockery::mock(AmqpMessageFactory::class);
        $amqpMessageFactory
            ->shouldReceive('make')
            ->once()
            ->andReturn($amqpMessage);

        $amqpMiddleware = new AmqpMiddleware($amqpMessageFactory, $amqpMock, $properties);

        $this->assertTrue($amqpMiddleware->store($message));
    }
}
