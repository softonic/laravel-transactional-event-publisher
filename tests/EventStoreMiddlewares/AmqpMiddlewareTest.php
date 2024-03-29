<?php

namespace Softonic\TransactionalEventPublisher\EventStoreMiddlewares;

use Illuminate\Support\Facades\Log;
use Mockery;
use PhpAmqpLib\Message\AMQPMessage;
use Softonic\Amqp\Amqp;
use Softonic\TransactionalEventPublisher\Factories\AmqpMessageFactory;
use Softonic\TransactionalEventPublisher\Interfaces\EventMessageInterface;
use Softonic\TransactionalEventPublisher\TestCase;

class AmqpMiddlewareTest extends TestCase
{
    /**
     * @test
     */
    public function whenStoringAMessageThrowAnExceptionAmqpMiddlewareShouldReturnFalse()
    {
        $message     = $this->getOneMessage();
        $amqpMessage = new AMQPMessage();
        $properties  = ['AMQP properties'];

        Log::shouldReceive('error')
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

        $amqpMiddleware = new AmqpMiddleware($amqpMessageFactory, $amqpMock, $properties);

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

    /**
     * @test
     */
    public function whenStoringMultipleMessagesThrowAnExceptionAmqpMiddlewareShouldReturnFalse()
    {
        $messages = $this->getTwoMessages();

        $amqpMessage = new AMQPMessage();
        $properties  = ['AMQP properties'];

        Log::shouldReceive('error')
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

        $amqpMiddleware = new AmqpMiddleware($amqpMessageFactory, $amqpMock, $properties);

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

    /**
     * @test
     */
    public function whenStoringAMessageShouldReturnTrue()
    {
        $message     = $this->getOneMessage();
        $properties  = ['AMQP properties'];
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

        $amqpMiddleware = new AmqpMiddleware($amqpMessageFactory, $amqpMock, $properties);

        self::assertTrue($amqpMiddleware->store($message));
    }

    /**
     * @test
     */
    public function whenStoringMultipleMessagesShouldReturnTrue()
    {
        $messages          = $this->getTwoMessages();
        $properties        = ['AMQP properties'];
        $firstAmqpMessage  = new AMQPMessage();
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

        $amqpMiddleware = new AmqpMiddleware($amqpMessageFactory, $amqpMock, $properties);

        self::assertTrue($amqpMiddleware->store(...$messages));
    }

    public function testConfigurableRoutingKey()
    {
        $message            = $this->getOneMessage();
        $properties         = [
            'routing_key_fields' => ['site', 'service', 'eventType', 'modelName'],
        ];
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

        $amqpMiddleware = new AmqpMiddleware($amqpMessageFactory, $amqpMock, $properties);

        self::assertTrue($amqpMiddleware->store($message));
    }

    public function testConfigurableRoutingKeyForMultipleMessages()
    {
        $messages          = $this->getTwoMessages();
        $properties        = [
            'routing_key_fields' => ['site', 'service', 'eventType', 'modelName'],
        ];
        $firstAmqpMessage  = new AMQPMessage();
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

        $amqpMiddleware = new AmqpMiddleware($amqpMessageFactory, $amqpMock, $properties);

        self::assertTrue($amqpMiddleware->store(...$messages));
    }
}
