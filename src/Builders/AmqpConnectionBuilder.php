<?php

namespace Softonic\TransactionalEventPublisher\Builders;

use PhpAmqpLib\Connection\AMQPConnectionConfig;
use PhpAmqpLib\Connection\AMQPConnectionFactory;
use PhpAmqpLib\Connection\AMQPStreamConnection;

class AmqpConnectionBuilder
{
    public function __construct(private readonly array $config)
    {
    }

    public function build(): AMQPStreamConnection
    {
        $amqpConfig = new AMQPConnectionConfig();
        $amqpConfig->setHost($this->config['host']);
        $amqpConfig->setPort($this->config['port']);
        $amqpConfig->setVhost($this->config['vhost']);
        $amqpConfig->setUser($this->config['username']);
        $amqpConfig->setPassword($this->config['password']);

        if (isset($this->config['ssl_options'])) {
            $sslContext = $this->createSslContext($this->config['ssl_options']);
            $amqpConfig->setStreamContext($sslContext);
            $amqpConfig->setIsSecure(true);
        } else {
            $amqpConfig->setInsist($this->config['insist'] ?? false);
            $amqpConfig->setLoginMethod($this->config['login_method'] ?? 'AMQPLAIN');
            $amqpConfig->setLoginResponse($this->config['login_response'] ?? '');
            $amqpConfig->setLocale($this->config['locale'] ?? 3);
            $amqpConfig->setConnectionTimeout($this->config['connection_timeout'] ?? 3.0);
            $amqpConfig->setReadTimeout($this->config['read_write_timeout'] ?? 130);
            $amqpConfig->setWriteTimeout($this->config['read_write_timeout'] ?? 130);
            $amqpConfig->setStreamContext($this->config['context'] ?? null);
            $amqpConfig->setKeepalive($this->config['keepalive'] ?? false);
            $amqpConfig->setHeartbeat($this->config['heartbeat'] ?? 60);
            $amqpConfig->setChannelRpcTimeout($this->config['channel_rpc_timeout'] ?? 0.0);
        }

        $connection = AMQPConnectionFactory::create($amqpConfig);

        $connection->channel()->exchange_declare(
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

            $queueInfo = $connection->channel()->queue_declare(
                $this->config['queue'],
                $this->config['queue_passive'] ?? false,
                $this->config['queue_durable'] ?? true,
                $this->config['queue_exclusive'] ?? false,
                $this->config['queue_auto_delete'] ?? false,
                $this->config['queue_nowait'] ?? false,
                $this->config['queue_properties'] ?? []
            );

            foreach ((array) $this->config['routing'] as $routingKey) {
                $connection->channel()->queue_bind(
                    $this->config['queue'] ?: $queueInfo[0],
                    $this->config['exchange'],
                    $routingKey
                );
            }
        }

        $connection->set_close_on_destruct();

        return $connection;
    }

    private function createSslContext($options)
    {
        $ssl_context = stream_context_create();
        foreach ($options as $k => $v) {
            stream_context_set_option($ssl_context, 'ssl', $k, $v);
        }

        return $ssl_context;
    }
}
