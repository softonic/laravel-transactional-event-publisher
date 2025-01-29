<?php

namespace Softonic\TransactionalEventPublisher\Builders;

use PhpAmqpLib\Connection\AMQPConnectionConfig;

class AmqpConnectionConfigBuilder
{
    public function __construct(private readonly array $config)
    {
    }

    public function build(): AMQPConnectionConfig
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

        return $amqpConfig;
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
