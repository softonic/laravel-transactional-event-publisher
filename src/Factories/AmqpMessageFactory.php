<?php

namespace Softonic\TransactionalEventPublisher\Factories;

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
     * @param array        $properties
     *
     * @return \PhpAmqpLib\Message\AMQPMessage
     */
    public function make(EventMessageContract $eventMessage, array $properties = null)
    {
        $this->checkMessage($eventMessage->toArray());

        return new AMQPMessage(json_encode($eventMessage), $properties);
    }

    private function checkMessage(array $message)
    {
        if (empty(array_filter($message))) {
            throw new \LogicException('No message provided');
        }
    }
}
