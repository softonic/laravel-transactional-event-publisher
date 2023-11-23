<?php

namespace Softonic\TransactionalEventPublisher\Factories;

use LogicException;
use PhpAmqpLib\Message\AMQPMessage;
use Softonic\TransactionalEventPublisher\Interfaces\EventMessageInterface;

class AmqpMessageFactory
{
    /**
     * Makes a AMQPMessage object.
     */
    public function make(EventMessageInterface $eventMessage, array $properties = []): AMQPMessage
    {
        $this->checkMessage($eventMessage->toArray());

        return new AMQPMessage(
            json_encode($eventMessage),
            array_merge(['content_type' => 'application/json'], $properties)
        );
    }

    protected function checkMessage(array $message): void
    {
        if (empty(array_filter($message))) {
            throw new LogicException('No message provided');
        }
    }
}
