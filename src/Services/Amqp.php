<?php

namespace Softonic\TransactionalEventPublisher\Services;

use PhpAmqpLib\Connection\AMQPConnectionConfig;
use PhpAmqpLib\Connection\AMQPConnectionFactory;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class Amqp
{
    private readonly AMQPStreamConnection $connection;

    public function __construct(AMQPConnectionConfig $config)
    {
        $this->connection = AMQPConnectionFactory::create($config);
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

    public function setUp(array $config): void
    {
        $this->connection->channel()->exchange_declare(
            $config['exchange'],
            $config['exchange_type'],
            $config['exchange_passive'] ?? false,
            $config['exchange_durable'] ?? true,
            $config['exchange_auto_delete'] ?? false,
            $config['exchange_internal'] ?? false,
            $config['exchange_nowait'] ?? false,
            $config['exchange_properties'] ?? []
        );

        if (!empty($config['queue']) || isset($config['queue_force_declare'])) {

            $queueInfo = $this->connection->channel()->queue_declare(
                $config['queue'],
                $config['queue_passive'] ?? false,
                $config['queue_durable'] ?? true,
                $config['queue_exclusive'] ?? false,
                $config['queue_auto_delete'] ?? false,
                $config['queue_nowait'] ?? false,
                $config['queue_properties'] ?? []
            );

            foreach ((array) $config['routing'] as $routingKey) {
                $this->connection->channel()->queue_bind(
                    $config['queue'] ?: $queueInfo[0],
                    $config['exchange'],
                    $routingKey
                );
            }
        }

        $this->connection->set_close_on_destruct();
    }
}
