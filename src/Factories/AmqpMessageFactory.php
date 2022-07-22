<?php

namespace Softonic\TransactionalEventPublisher\Factories;

use LogicException;
use PhpAmqpLib\Message\AMQPMessage;
use Softonic\TransactionalEventPublisher\Contracts\EventMessageContract;

/**
 * Class AmqpMessageFactory
 *
 * @package Softonic\TransactionalEventPublisher\Factories
 */
class AmqpMessageFactory
{
    /**
     * Makes a AMQPMessage object.
     *
     * @param EventMessageContract $eventMessage
     * @param array                $properties
     *
     * @return AMQPMessage
     */
    public function make(EventMessageContract $eventMessage, array $properties = [])
    {
        $this->checkMessage($eventMessage->toArray());

        return new AMQPMessage(
            json_encode($eventMessage),
            array_merge(['content_type' => 'application/json'], $properties)
        );
    }

    private function checkMessage(array $message)
    {
        if (empty(array_filter($message))) {
            throw new LogicException('No message provided');
        }
    }
}
