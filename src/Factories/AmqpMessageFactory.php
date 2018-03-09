<?php

namespace Softonic\TransactionalEventPublisher\Factories;

use PhpAmqpLib\Message\AMQPMessage;
use Softonic\TransactionalEventPublisher\Entities\EventMessage;

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
     * @param EventMessage $message
     * @param null  $properties
     *
     * @return \PhpAmqpLib\Message\AMQPMessage
     */
    public function make(EventMessage $message, $properties = null)
    {
        $this->checkMessage($message->toArray());

        return new AMQPMessage($message->toJson(), $properties);
    }

    private function checkMessage($message)
    {
        if (0 == array_sum($message)) {
            throw new \LogicException('No message provided');
        }
    }
}
