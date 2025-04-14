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
        private readonly Amqp               $amqp
    ) {
    }

    /**
     * Publishes the messages to the AMQP Message broker.
     */
    public function store(EventMessageInterface ...$messages): bool
    {
        $this->amqp->setUp();

        try {
            if (count($messages) === 1) {
                $this->amqp->basic_publish(
                    $this->messageFactory->make($messages[0]),
                    $this->amqp->getRoutingKey($messages[0])
                );
                return true;
            }

            foreach ($messages as $message) {
                $this->amqp->batch_basic_publish(
                    $this->messageFactory->make($message),
                    $this->amqp->getRoutingKey($message)
                );
            }

            $this->amqp->publish_batch();
            return true;
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return false;
        }
    }
}
