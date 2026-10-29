<?php

declare(strict_types=1);

namespace Softonic\TransactionalEventPublisher\Factories;

use LogicException;
use PhpAmqpLib\Message\AMQPMessage;
use Softonic\TransactionalEventPublisher\Interfaces\EventMessageInterface;

class AmqpMessageFactory
{
    /**
     * Makes a AMQPMessage object.
     *
     * @param array<string, mixed> $properties
     */
    public function make(EventMessageInterface $eventMessage, array $properties = []): AMQPMessage
    {
        $this->checkMessage($eventMessage->toArray());

        return new AMQPMessage(
            json_encode($eventMessage, JSON_THROW_ON_ERROR),
            array_merge(['content_type' => 'application/json'], $properties)
        );
    }

    /**
     * @param array<string, mixed> $message
     */
    protected function checkMessage(array $message): void
    {
        if (empty(array_filter($message))) {
            throw new LogicException('No message provided');
        }
    }
}
