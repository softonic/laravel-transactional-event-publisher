<?php

namespace Softonic\TransactionalEventPublisher\EventStoreMiddlewares;

use Bschmitt\Amqp\Amqp;
use Psr\Log\LoggerInterface;
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
     * @var LoggerInterface
     */
    private $logger;

    /**
     * AmqpMiddleware constructor.
     *
     * @param \Softonic\TransactionalEventPublisher\Factories\AmqpMessageFactory $messageFactory
     * @param \Bschmitt\Amqp\Amqp                                                $amqp
     * @param array                                                              $properties
     * @param LoggerInterface                                                    $logger
     */
    public function __construct(
        AmqpMessageFactory $messageFactory,
        Amqp $amqp,
        array $properties,
        LoggerInterface $logger
    ) {
        $this->messageFactory = $messageFactory;
        $this->amqp           = $amqp;
        $this->properties     = $properties;
        $this->logger         = $logger;
    }

    /**
     * Publishes the message to the AMQP Message broker.
     *
     * @param EventMessageContract $message
     *
     * @return bool
     */
    public function store(EventMessageContract $message)
    {
        try {
            $this->amqp->publish(
                $message->service . '.' . $message->eventType . '.' . $message->modelName,
                $this->messageFactory->make($message),
                $this->properties
            );

            return true;
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return false;
        }
    }
}
