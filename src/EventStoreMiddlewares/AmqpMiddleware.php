<?php

namespace Softonic\TransactionalEventPublisher\EventStoreMiddlewares;

use Exception;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Channel\AMQPChannel;
use Softonic\TransactionalEventPublisher\Factories\AmqpMessageFactory;
use Softonic\TransactionalEventPublisher\Interfaces\EventMessageInterface;
use Softonic\TransactionalEventPublisher\Interfaces\EventStoreMiddlewareInterface;

class AmqpMiddleware implements EventStoreMiddlewareInterface
{
    public function __construct(
        private readonly AmqpMessageFactory $messageFactory,
        private readonly AMQPChannel        $channel,
        private readonly array              $properties,
    ) {
    }

    /**
     * Publishes the messages to the AMQP Message broker.
     */
    public function store(EventMessageInterface ...$messages): bool
    {
        try {
            if (count($messages) === 1) {
                $messageFactory = $this->messageFactory->make($messages[0]);
                $routing = $this->getRoutingKey($messages[0]);
                $this->channel->basic_publish($messageFactory, $this->properties['exchange'], $routing);
                return true;
            }

            foreach ($messages as $message) {
                $messageFactory = $this->messageFactory->make($message);
                $routing = $this->getRoutingKey($message);
                $this->channel->batch_basic_publish($messageFactory, $this->properties['exchange'], $routing);
            }

            $this->channel->publish_batch();
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
