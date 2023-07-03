<?php

namespace Softonic\TransactionalEventPublisher\Factories;

use LogicException;
use PhpAmqpLib\Message\AMQPMessage;
use Softonic\TransactionalEventPublisher\Contracts\EventMessageContract;

class AmqpMessageFactory
{
    /**
     * Makes a AMQPMessage object.
     */
    public function make(EventMessageContract $eventMessage, array $properties = []): AMQPMessage
    {
        $this->checkMessage($eventMessage->toArray());

        return new AMQPMessage(
            json_encode($eventMessage),
            array_merge(['content_type' => 'application/json'], $properties)
        );
    }

    private function checkMessage(array $message): void
    {
        if (empty(array_filter($message))) {
            throw new LogicException('No message provided');
        }
    }
}
