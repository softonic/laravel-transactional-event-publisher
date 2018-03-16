<?php

namespace Softonic\TransactionalEventPublisher\Tests\Factories;

use PhpAmqpLib\Message\AMQPMessage;
use phpmock\mockery\PHPMockery;
use PHPUnit\Framework\TestCase;
use Softonic\TransactionalEventPublisher\Factories\AmqpMessageFactory;
use Softonic\TransactionalEventPublisher\ValueObjects\EventMessage;

class AmqpMessageFactoryTest extends TestCase
{
    /**
     * @expectedException \LogicException
     * @expectedExceptionMessage No message provided
     */
    public function testWhenNoMessageShouldThrowALogicException()
    {
        $factory = new AmqpMessageFactory();

        $eventMessageMock = \Mockery::mock(EventMessage::class);
        $eventMessageMock->shouldReceive('toArray')->once()->andReturn([]);

        $factory->make($eventMessageMock);
    }

    public function testWhenRoutingKeyProvidedAndMessageShouldCreateAnAMQPMessageObject()
    {
        $factory = new AmqpMessageFactory();
        $eventMessage = \Mockery::mock(EventMessage::class);
        $eventMessage
            ->shouldReceive('toArray')
            ->andReturn(['service' => 'service', 'eventName' => 'created']);

        PHPMockery::mock('Softonic\TransactionalEventPublisher\Factories', 'json_encode')
            ->andReturn('json encoded string message');

        $eventMessage->service = 'service';
        $eventMessage->eventName = 'created';
        $eventMessage->createdAt = '2018-02-01 21:00:01';
        $eventMessage->payload = 'payload data';

        $amqpMessage = $factory->make($eventMessage);

        $this->assertInstanceOf(AMQPMessage::class, $amqpMessage);
    }
}
