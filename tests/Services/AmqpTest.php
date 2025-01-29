<?php

namespace Services;

use Mockery;
use Override;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPConnectionFactory;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PHPUnit\Framework\Attributes\Test;
use Softonic\TransactionalEventPublisher\Interfaces\EventMessageInterface;
use Softonic\TransactionalEventPublisher\Services\Amqp;
use Softonic\TransactionalEventPublisher\TestCase;

class AmqpTest extends TestCase
{
    private array $config;

    private AMQPStreamConnection $connectionMock;

    private AMQPChannel $channelMock;

    private Amqp $amqp;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->config = [
            'host' => 'localhost',
            'port' => 5672,
            'username' => 'guest',
            'password' => 'guest',
            'vhost' => '/',
            'exchange' => 'test_exchange',
            'exchange_type' => 'direct',
            'queue' => 'test_queue',
            'routing' => ['test.key'],
        ];

        $this->connectionMock = Mockery::mock(AMQPStreamConnection::class);
        $this->channelMock = Mockery::mock(AMQPChannel::class);

        $this->connectionMock->shouldReceive('channel')->andReturn($this->channelMock);

        Mockery::mock('alias:' . AMQPConnectionFactory::class)
            ->shouldReceive('create')
            ->andReturn($this->connectionMock);

        $this->amqp = new Amqp($this->config);
    }

    #[Test]
    public function whenRunningSetUp(): void
    {
        $this->channelMock->shouldReceive('exchange_declare')->once();
        $this->channelMock->shouldReceive('queue_declare')->once()->andReturn(['test_queue']);
        $this->channelMock->shouldReceive('queue_bind')->once();

        $this->connectionMock->shouldReceive('set_close_on_destruct')->once();

        $this->amqp->setUp();
    }

    #[Test]
    public function testGetRoutingKeyWithCustomFields(): void
    {
        $this->config['routing_key_fields'] = ['service', 'eventType', 'modelName'];
        $this->amqp = new Amqp($this->config);

        $message = Mockery::mock(EventMessageInterface::class);
        $message->service = 'testService';
        $message->eventType = 'created';
        $message->modelName = 'testModel';

        $this->assertEquals('testservice.created.testmodel', $this->amqp->getRoutingKey($message));
    }

}
