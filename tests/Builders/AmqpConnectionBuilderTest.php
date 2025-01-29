<?php

namespace Builders;

use Mockery;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPConnectionFactory;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PHPUnit\Framework\Attributes\Test;
use Softonic\TransactionalEventPublisher\Builders\AmqpConnectionBuilder;
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
            'routing' => ['test_routing_key']
        ];

        $mockChannel = Mockery::mock(AMQPChannel::class);
        $mockChannel->shouldReceive('exchange_declare')->once();
        $mockChannel->shouldReceive('queue_declare')->once()->andReturn(['test_queue']);
        $mockChannel->shouldReceive('queue_bind')->once();

        $mockConnection = Mockery::mock(AMQPStreamConnection::class);
        $mockConnection->shouldReceive('channel')->andReturn($mockChannel);
        $mockConnection->shouldReceive('set_close_on_destruct')->once();

        Mockery::mock('overload:' . AMQPConnectionFactory::class)
            ->shouldReceive('create')
            ->once()
            ->andReturn($mockConnection);

        $builder = new AmqpConnectionBuilder($config);
        $connection = $builder->build();

        $this->assertInstanceOf(AMQPStreamConnection::class, $connection);

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

        $mockChannel = Mockery::mock(AMQPChannel::class);
        $mockChannel->shouldReceive('exchange_declare')->once();

        $mockConnection = Mockery::mock(AMQPStreamConnection::class);
        $mockConnection->shouldReceive('channel')->andReturn($mockChannel);
        $mockConnection->shouldReceive('set_close_on_destruct')->once();

        Mockery::mock('overload:' . AMQPConnectionFactory::class)
            ->shouldReceive('create')
            ->once()
            ->andReturn($mockConnection);

        $builder = new AmqpConnectionBuilder($config);
        $connection = $builder->build();

        $this->assertInstanceOf(AMQPStreamConnection::class, $connection);
    }
}
