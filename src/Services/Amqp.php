<?php

namespace Softonic\TransactionalEventPublisher\Services;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Connection\AMQPConnectionFactory;
use PhpAmqpLib\Message\AMQPMessage;
use Softonic\TransactionalEventPublisher\Builders\AmqpConnectionConfigBuilder;
use Softonic\TransactionalEventPublisher\Interfaces\EventMessageInterface;

class Amqp
{
    private readonly AMQPChannel $channel;

    private ?AbstractConnection $connection = null;

    public function __construct(private readonly array $config)
    {
    }

    public function setUp(): void
    {
        if ($this->connection instanceof AbstractConnection) {
            return;
        }

        $configBuilder = new AmqpConnectionConfigBuilder($this->config);
        $amqpConfig = $configBuilder->build();
        $this->connection = AMQPConnectionFactory::create($amqpConfig);

        $this->connection->channel()->exchange_declare(
            $this->config['exchange'],
            $this->config['exchange_type'],
            $this->config['exchange_passive'] ?? false,
            $this->config['exchange_durable'] ?? true,
            $this->config['exchange_auto_delete'] ?? false,
            $this->config['exchange_internal'] ?? false,
            $this->config['exchange_nowait'] ?? false,
            $this->config['exchange_properties'] ?? []
        );

        if (!empty($this->config['queue']) || isset($this->config['queue_force_declare'])) {

            $queueInfo = $this->connection->channel()->queue_declare(
                $this->config['queue'],
                $this->config['queue_passive'] ?? false,
                $this->config['queue_durable'] ?? true,
                $this->config['queue_exclusive'] ?? false,
                $this->config['queue_auto_delete'] ?? false,
                $this->config['queue_nowait'] ?? false,
                $this->config['queue_properties'] ?? []
            );

            foreach ((array) $this->config['routing'] as $routingKey) {
                $this->connection->channel()->queue_bind(
                    $this->config['queue'] ?: $queueInfo[0],
                    $this->config['exchange'],
                    $routingKey
                );
            }
        }

        $this->connection->set_close_on_destruct();
    }

    public function basic_publish(AMQPMessage $message, string $routingKey): void
    {
        $this->connection->channel()->basic_publish(
            $message,
            $this->config['exchange'],
            $routingKey
        );
    }

    public function batch_basic_publish(AMQPMessage $message, string $routingKey): void
    {
        $this->connection->channel()->batch_basic_publish(
            $message,
            $this->config['exchange'],
            $routingKey
        );
    }

    public function publish_batch(): void
    {
        $this->connection->channel()->publish_batch();
    }

    public function getRoutingKey(EventMessageInterface $message): string
    {
        $routingKey = $message->service . '.' . $message->eventType . '.' . $message->modelName;
        if (isset($this->config['routing_key_fields'])) {
            $routingKey = implode(
                '.',
                array_map(fn ($key) => $message->$key, $this->config['routing_key_fields'])
            );
        }

        return strtolower($routingKey);
    }
}
