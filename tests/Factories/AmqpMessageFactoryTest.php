<?php

namespace Softonic\TransactionalEventPublisher\Factories;

use LogicException;
use Mockery;
use PhpAmqpLib\Message\AMQPMessage;
use phpmock\mockery\PHPMockery;
use Softonic\TransactionalEventPublisher\TestCase;
use Softonic\TransactionalEventPublisher\ValueObjects\EventMessage;

class AmqpMessageFactoryTest extends TestCase
{
    public function testWhenNoMessageShouldThrowALogicException()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('No message provided');

        $factory = new AmqpMessageFactory();

        $eventMessageMock = Mockery::mock(EventMessage::class);
        $eventMessageMock->shouldReceive('toArray')->once()->andReturn([]);

        $factory->make($eventMessageMock);
    }

    public function testWhenRoutingKeyProvidedAndMessageShouldCreateAnAMQPMessageObject()
    {
        $factory      = new AmqpMessageFactory();
        $eventMessage = Mockery::mock(EventMessage::class);
        $eventMessage
            ->shouldReceive('toArray')
            ->andReturn(['service' => 'service', 'eventName' => 'created']);

        PHPMockery::mock('Softonic\TransactionalEventPublisher\Factories', 'json_encode')
            ->andReturn('json encoded string message');

        $eventMessage->service   = 'service';
        $eventMessage->eventName = 'created';
        $eventMessage->createdAt = '2018-02-01 21:00:01';
        $eventMessage->payload   = 'payload data';

        $amqpMessage = $factory->make($eventMessage);

        $this->assertInstanceOf(AMQPMessage::class, $amqpMessage);
    }
}
