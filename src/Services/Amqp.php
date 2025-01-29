<?php

namespace Softonic\TransactionalEventPublisher\Services;

use PhpAmqpLib\Connection\AMQPConnectionConfig;
use PhpAmqpLib\Connection\AMQPConnectionFactory;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class Amqp
{
    private readonly AMQPStreamConnection $connection;

    public function __construct(private readonly AMQPConnectionConfig $config)
    {
    }

    public function setUp(array $properties): void
    {
        $this->connection = AMQPConnectionFactory::create($this->config);

        $this->connection->channel()->exchange_declare(
            $properties['exchange'],
            $properties['exchange_type'],
            $properties['exchange_passive'] ?? false,
            $properties['exchange_durable'] ?? true,
            $properties['exchange_auto_delete'] ?? false,
            $properties['exchange_internal'] ?? false,
            $properties['exchange_nowait'] ?? false,
            $properties['exchange_properties'] ?? []
        );

        if (!empty($properties['queue']) || isset($properties['queue_force_declare'])) {

            $queueInfo = $this->connection->channel()->queue_declare(
                $properties['queue'],
                $properties['queue_passive'] ?? false,
                $properties['queue_durable'] ?? true,
                $properties['queue_exclusive'] ?? false,
                $properties['queue_auto_delete'] ?? false,
                $properties['queue_nowait'] ?? false,
                $properties['queue_properties'] ?? []
            );

            foreach ((array) $properties['routing'] as $routingKey) {
                $this->connection->channel()->queue_bind(
                    $properties['queue'] ?: $queueInfo[0],
                    $properties['exchange'],
                    $routingKey
                );
            }
        }

        $this->connection->set_close_on_destruct();
    }

    public function basic_publish(AMQPMessage $message, string $exchange, string $routingKey): void
    {
        $this->connection->channel()->basic_publish(
            $message,
            $exchange,
            $routingKey
        );
    }

    public function batch_basic_publish(AMQPMessage $message, string $exchange, string $routingKey): void
    {
        $this->connection->channel()->batch_basic_publish(
            $message,
            $exchange,
            $routingKey
        );
    }

    public function publish_batch(): void
    {
        $this->connection->channel()->publish_batch();
    }
}
