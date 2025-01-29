<?php

namespace Builders;

use PhpAmqpLib\Connection\AMQPConnectionConfig;
use PHPUnit\Framework\Attributes\Test;
use Softonic\TransactionalEventPublisher\Builders\AmqpConnectionConfigBuilder;
use Softonic\TransactionalEventPublisher\TestCase;

class AmqpConnectionBuilderTest extends TestCase
{

    #[Test]
    public function whenAmqpConnectionIsBuilt(): void
    {
        $config = [
            'host' => 'localhost',
            'port' => 5672,
            'vhost' => '/',
            'username' => 'guest',
            'password' => 'guest',
            'exchange' => 'test_exchange',
            'exchange_type' => 'topic',
            'exchange_durable'    => true,
            'queue' => 'test_queue',
            'routing' => ['test_routing_key'],
        ];

        $builder = new AmqpConnectionConfigBuilder($config);
        $connection = $builder->build();

        $this->assertInstanceOf(AMQPConnectionConfig::class, $connection);
        $this->assertEquals('localhost', $connection->getHost());
        $this->assertEquals('AMQPLAIN', $connection->getLoginMethod());
        $this->assertNull($connection->getStreamContext());
    }

    #[Test]
    public function whenAmqpConnectionIsBuiltWithSsl(): void
    {
        $config = [
            'host' => 'localhost',
            'port' => 5671,
            'vhost' => '/',
            'username' => 'guest',
            'password' => 'guest',
            'ssl_options' => ['verify_peer' => true],
            'exchange' => 'test_exchange',
            'exchange_type' => 'topic',
            'exchange_durable'    => true,
            'consumer_tag'        => 'test-api-consumer',
            'connect_options'     => [],
            'queue_properties'    => ['x-ha-policy' => ['S', 'all']],
            'exchange_properties' => [],
            'timeout'             => 0,
            'routing_key_fields'  => ['site', 'service', 'eventType', 'modelName'],
        ];

        $builder = new AmqpConnectionConfigBuilder($config);
        $connection = $builder->build();

        $this->assertInstanceOf(AMQPConnectionConfig::class, $connection);
        $this->assertNotNull($connection->getStreamContext());
    }
}
