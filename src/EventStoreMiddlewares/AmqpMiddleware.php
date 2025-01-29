<?php

namespace Softonic\TransactionalEventPublisher\EventStoreMiddlewares;

use Exception;
use Illuminate\Support\Facades\Log;
use Softonic\TransactionalEventPublisher\Factories\AmqpMessageFactory;
use Softonic\TransactionalEventPublisher\Interfaces\EventMessageInterface;
use Softonic\TransactionalEventPublisher\Interfaces\EventStoreMiddlewareInterface;
use Softonic\TransactionalEventPublisher\Services\Amqp;

class AmqpMiddleware implements EventStoreMiddlewareInterface
{
    public function __construct(
        private readonly AmqpMessageFactory $messageFactory,
        private readonly Amqp               $amqp,
        private readonly array              $properties,
    ) {
    }

    /**
     * Publishes the messages to the AMQP Message broker.
     */
    public function store(EventMessageInterface ...$messages): bool
    {
        try {

            $this->amqp->setUp($this->properties);

            if (count($messages) === 1) {
                $routing = $this->getRoutingKey($messages[0]);
                $this->amqp->basic_publish(
                    $this->messageFactory->make($messages[0]),
                    $this->properties['exchange'],
                    $routing
                );
                return true;
            }

            foreach ($messages as $message) {
                $routing = $this->getRoutingKey($message);
                $this->amqp->batch_basic_publish(
                    $this->messageFactory->make($message),
                    $this->properties['exchange'],
                    $routing
                );
            }

            $this->amqp->publish_batch();
            return true;
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return false;
        }
    }

    /**
     * Returns the message routing key based in the configured parameters
     * or a default value based in service, eventType and modelName.
     */
    private function getRoutingKey(EventMessageInterface $message): string
    {
        $routingKey = $message->service . '.' . $message->eventType . '.' . $message->modelName;
        if (isset($this->properties['routing_key_fields'])) {
            $routingKey = implode(
                '.',
                array_map(fn ($key) => $message->$key, $this->properties['routing_key_fields'])
            );
        }

        return strtolower($routingKey);
    }
}
