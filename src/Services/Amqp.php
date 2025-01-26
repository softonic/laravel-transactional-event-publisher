<?php

namespace Softonic\TransactionalEventPublisher\Services;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPConnectionConfig;
use PhpAmqpLib\Connection\AMQPConnectionFactory;
use PhpAmqpLib\Connection\AMQPStreamConnection;

class Amqp
{
    public readonly AMQPStreamConnection $connection;

    public readonly AMQPChannel $channel;

    public function __invoke(): void
    {
        $this->connection = $this->connection();
        $this->channel = $this->connection->channel();
    }

    private function connection(): AMQPStreamConnection
    {
        $config = config('transactional-event-publisher.properties.amqp');

        $amqpConfig = new AMQPConnectionConfig();
        $amqpConfig->setHost($config['host']);
        $amqpConfig->setPort($config['port']);
        $amqpConfig->setVhost($config['vhost']);
        $amqpConfig->setUser($config['user']);
        $amqpConfig->setPassword($config['password']);

        if (isset($config['ssl_options'])) {
            $amqpConfig->setStreamContext($config['ssl_options']);
            $amqpConfig->setIsSecure(true);
        } else {
            $amqpConfig->setInsist($config['insist'] ?? false);
            $amqpConfig->setLoginMethod($config['login_method'] ?? 'AMQPLAIN');
            $amqpConfig->setLoginResponse($config['login_response'] ?? null);
            $amqpConfig->setLocale($config['locale'] ?? 3);
            $amqpConfig->setConnectionTimeout($config['connection_timeout'] ?? 3.0);
            $amqpConfig->setReadTimeout($config['read_write_timeout'] ?? 130);
            $amqpConfig->setWriteTimeout($config['read_write_timeout'] ?? 130);
            $amqpConfig->setStreamContext($config['context'] ?? null);
            $amqpConfig->setKeepalive($config['keepalive'] ?? false);
            $amqpConfig->setHeartbeat($config['heartbeat'] ?? 60);
            $amqpConfig->setChannelRpcTimeout($config['channel_rpc_timeout'] ?? 0.0);
        }

        $connection = AMQPConnectionFactory::create($amqpConfig);

        $connection->channel()->exchange_declare(
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

            $queueInfo = $connection->channel()->queue_declare(
                $config['queue'],
                $config['queue_passive'] ?? false,
                $config['queue_durable'] ?? true,
                $config['queue_exclusive'] ?? false,
                $config['queue_auto_delete'] ?? false,
                $config['queue_nowait'] ?? false,
                $config['queue_properties'] ?? []
            );

            foreach ((array) $config['routing'] as $routingKey) {
                $connection->channel()->queue_bind(
                    $config['queue'] ?: $queueInfo[0],
                    $config['exchange'],
                    $routingKey
                );
            }
        }

        $connection->set_close_on_destruct();

        return $connection;
    }
}
