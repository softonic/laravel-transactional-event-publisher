<?php

namespace Softonic\TransactionalEventPublisher\Factories;

use LogicException;
use Mockery;
use PhpAmqpLib\Message\AMQPMessage;
use phpmock\mockery\PHPMockery;
use Softonic\TransactionalEventPublisher\Interfaces\EventMessageInterface;
use Softonic\TransactionalEventPublisher\TestCase;

class AmqpMessageFactoryTest extends TestCase
{
    /**
     * @test
     */
    public function whenNoMessageShouldThrowALogicException()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('No message provided');

        $factory = new AmqpMessageFactory();

        $eventMessageMock = Mockery::mock(EventMessageInterface::class);
        $eventMessageMock->shouldReceive('toArray')->once()->andReturn([]);

        $factory->make($eventMessageMock);
    }

    /**
     * @test
     */
    public function whenRoutingKeyProvidedAndMessageShouldCreateAnAMQPMessageObject()
    {
        $factory      = new AmqpMessageFactory();
        $eventMessage = Mockery::mock(EventMessageInterface::class);
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

        self::assertInstanceOf(AMQPMessage::class, $amqpMessage);
    }
}
