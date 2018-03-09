<?php

namespace Softonic\TransactionalEventPublisher\Tests\Factories;

use PhpAmqpLib\Message\AMQPMessage;
use PHPUnit\Framework\TestCase;
use Softonic\TransactionalEventPublisher\Entities\EventMessage;
use Softonic\TransactionalEventPublisher\Factories\AmqpMessageFactory;

class AmqpMessageFactoryTest extends TestCase
{
    /**
     * @expectedException \LogicException
     * @expectedExceptionMessage No message provided
     */
    public function testWhenNoMessageShouldThrowALogicException()
    {
        $factory = new AmqpMessageFactory();

        $factory->make(new EventMessage());
    }

    public function testWhenRoutingKeyProvidedAndMessageShouldCreateAnAMQPMessageObject()
    {
        $factory = new AmqpMessageFactory();
        $eventMessage = new EventMessage();

        $eventMessage->service = 'service';
        $eventMessage->eventName = 'created';
        $eventMessage->createdAt = '2018-02-01 21:00:01';
        $eventMessage->payload = 'payload data';
        $eventMessage->meta = ['meta_1' => 'meta value'];

        $amqpMessage = $factory->make($eventMessage);

        $this->assertInstanceOf(AMQPMessage::class, $amqpMessage);
    }
}
