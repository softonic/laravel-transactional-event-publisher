<?php

namespace Softonic\TransactionalEventPublisher\EventStoreMiddlewares;

use Exception;
use Illuminate\Support\Facades\Log;
use Softonic\Amqp\Amqp;
use Softonic\TransactionalEventPublisher\Contracts\EventMessageContract;
use Softonic\TransactionalEventPublisher\Contracts\EventStoreMiddlewareContract;
use Softonic\TransactionalEventPublisher\Factories\AmqpMessageFactory;

/**
 * Class AmqpMiddleware
 *
 * @package Softonic\TransactionalEventPublisher\EventStoreMiddlewares
 */
class AmqpMiddleware implements EventStoreMiddlewareContract
{
    private $messageFactory;

    private $amqp;

    private $properties;

    /**
     * AmqpMiddleware constructor.
     *
     * @param AmqpMessageFactory $messageFactory
     * @param Amqp               $amqp
     * @param array              $properties
     */
    public function __construct(
        AmqpMessageFactory $messageFactory,
        Amqp $amqp,
        array $properties
    ) {
        $this->messageFactory = $messageFactory;
        $this->amqp           = $amqp;
        $this->properties     = $properties;
    }

    /**
     * Publishes the message to the AMQP Message broker.
     *
     * @param EventMessageContract $messages
     *
     * @return bool
     */
    public function store(EventMessageContract ...$messages)
    {
        try {
            if (count($messages) === 1) {
                $this->amqp->publish(
                    $this->getRoutingKey($messages[0]),
                    $this->messageFactory->make($messages[0]),
                    $this->properties
                );

                return true;
            }

            foreach ($messages as $message) {
                $this->amqp->batchBasicPublish(
                    $this->getRoutingKey($message),
                    $this->messageFactory->make($message)
                );
            }

            $this->amqp->batchPublish($this->properties);

            return true;
        } catch (Exception $e) {
            Log::error($e->getMessage());

            return false;
        }
    }

    /**
     * Returns the messsage routing key based in the configured parameters
     * or a default value based in service, eventType and modelName.
     *
     * @param EventMessageContract $message
     *
     * @return string
     */
    private function getRoutingKey(EventMessageContract $message): string
    {
        $routingKey = $message->service . '.' . $message->eventType . '.' . $message->modelName;
        if (isset($this->properties['routing_key_fields'])) {
            $routingKey = implode(
                '.',
                array_map(function ($key) use ($message) {
                    return $message->$key;
                }, $this->properties['routing_key_fields'])
            );
        }

        return strtolower($routingKey);
    }
}
