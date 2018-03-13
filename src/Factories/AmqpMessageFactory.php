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
     * @param EventMessage $eventMessage
     * @param array        $properties
     *
     * @return \PhpAmqpLib\Message\AMQPMessage
     */
    public function make(EventMessage $eventMessage, array $properties = null)
    {
        $this->checkMessage($eventMessage->toArray());

        return new AMQPMessage(json_encode($eventMessage), $properties);
    }

    private function checkMessage(array $message)
    {
        if (0 == array_sum($message)) {
            throw new \LogicException('No message provided');
        }
    }
}
