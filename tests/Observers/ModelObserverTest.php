<?php

namespace Softonic\TransactionalEventPublisher\Tests\Observers;

use Illuminate\Database\Connectors\MySqlConnector;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\TestCase;
use Softonic\TransactionalEventPublisher\Contracts\EventStoreMiddlewareContract;
use Softonic\TransactionalEventPublisher\Observers\ModelObserver;

class ModelObserverTest extends TestCase
{
    public function testWhenANewItemIsCreatedShouldSendAnEventMessage()
    {
        $payload = ['id' => 123, 'field' => 'value 1'];
        $eventStoreResult = true;

        $mySqlConnectorMock = \Mockery::mock(MySqlConnector::class);
        $mySqlConnectorMock->shouldReceive('beginTransaction')->once();
        $mySqlConnectorMock->shouldReceive('commit')->once();

        $modelMock = \Mockery::mock(Model::class);
        $modelMock->shouldReceive('toArray')->once()->andReturn($payload);
        $modelMock
            ->shouldReceive('getConnection')
            ->times(2)
            ->andReturn($mySqlConnectorMock);

        $eventStoreMiddlewareMock = \Mockery::mock(EventStoreMiddlewareContract::class);
        $eventStoreMiddlewareMock
            ->shouldReceive('store')
            ->once()
            ->andReturn($eventStoreResult);

        $modelObserver = new ModelObserver($eventStoreMiddlewareMock, EventMessageStub::class);

        $modelObserver->creating($modelMock);

        $this->assertTrue($modelObserver->created($modelMock));
    }

    /**
     * @expectedException \Softonic\TransactionalEventPublisher\Exceptions\EventStoreFailedException
     */
    public function testWhenANewItemIsCreatedButTheEventStoreFailsWhenStoring()
    {
        $payload = ['id' => 123, 'field' => 'value 1'];
        $eventStoreResult = false;

        $mySqlConnectorMock = \Mockery::mock(MySqlConnector::class);
        $mySqlConnectorMock->shouldReceive('beginTransaction')->once();
        $mySqlConnectorMock->shouldReceive('rollBack')->once();

        $modelMock = \Mockery::mock(Model::class);
        $modelMock->shouldReceive('toArray')->once()->andReturn($payload);
        $modelMock
            ->shouldReceive('getConnection')
            ->times(2)
            ->andReturn($mySqlConnectorMock);

        $eventStoreMiddlewareMock = \Mockery::mock(EventStoreMiddlewareContract::class);
        $eventStoreMiddlewareMock
            ->shouldReceive('store')
            ->once()
            ->andReturn($eventStoreResult);

        $modelObserver = new ModelObserver($eventStoreMiddlewareMock, EventMessageStub::class);

        $modelObserver->creating($modelMock);
        $modelObserver->created($modelMock);
    }

    public function testWhenAnItemIsUpdatedShouldSendAnEventMessage()
    {
        $payload = ['id' => 123, 'field' => 'value 1'];
        $eventStoreResult = true;

        $mySqlConnectorMock = \Mockery::mock(MySqlConnector::class);
        $mySqlConnectorMock->shouldReceive('beginTransaction')->once();
        $mySqlConnectorMock->shouldReceive('commit')->once();

        $modelMock = \Mockery::mock(Model::class);
        $modelMock->shouldReceive('toArray')->once()->andReturn($payload);
        $modelMock
            ->shouldReceive('getConnection')
            ->times(2)
            ->andReturn($mySqlConnectorMock);

        $eventStoreMiddlewareMock = \Mockery::mock(EventStoreMiddlewareContract::class);
        $eventStoreMiddlewareMock
            ->shouldReceive('store')
            ->once()
            ->andReturn($eventStoreResult);

        $modelObserver = new ModelObserver($eventStoreMiddlewareMock, EventMessageStub::class);

        $modelObserver->updating($modelMock);

        $this->assertTrue($modelObserver->updated($modelMock));
    }

    /**
     * @expectedException \Softonic\TransactionalEventPublisher\Exceptions\EventStoreFailedException
     */
    public function testWhenAnItemIsUpdatedButTheEventStoreFailsWhenStoring()
    {
        $payload = ['id' => 123, 'field' => 'value 1'];
        $eventStoreResult = false;

        $mySqlConnectorMock = \Mockery::mock(MySqlConnector::class);
        $mySqlConnectorMock->shouldReceive('beginTransaction')->once();
        $mySqlConnectorMock->shouldReceive('rollBack')->once();

        $modelMock = \Mockery::mock(Model::class);
        $modelMock->shouldReceive('toArray')->once()->andReturn($payload);
        $modelMock
            ->shouldReceive('getConnection')
            ->times(2)
            ->andReturn($mySqlConnectorMock);

        $eventStoreMiddlewareMock = \Mockery::mock(EventStoreMiddlewareContract::class);
        $eventStoreMiddlewareMock
            ->shouldReceive('store')
            ->once()
            ->andReturn($eventStoreResult);

        $modelObserver = new ModelObserver($eventStoreMiddlewareMock, EventMessageStub::class);

        $modelObserver->updating($modelMock);
        $modelObserver->updated($modelMock);
    }

    public function testWhenAnItemDeletedShouldSendAnEventMessage()
    {
        $payload = ['id' => 123, 'field' => 'value 1'];
        $eventStoreResult = true;

        $mySqlConnectorMock = \Mockery::mock(MySqlConnector::class);
        $mySqlConnectorMock->shouldReceive('beginTransaction')->once();
        $mySqlConnectorMock->shouldReceive('commit')->once();

        $modelMock = \Mockery::mock(Model::class);
        $modelMock->shouldReceive('toArray')->once()->andReturn($payload);
        $modelMock
            ->shouldReceive('getConnection')
            ->times(2)
            ->andReturn($mySqlConnectorMock);

        $eventStoreMiddlewareMock = \Mockery::mock(EventStoreMiddlewareContract::class);
        $eventStoreMiddlewareMock
            ->shouldReceive('store')
            ->once()
            ->andReturn($eventStoreResult);

        $modelObserver = new ModelObserver($eventStoreMiddlewareMock, EventMessageStub::class);

        $modelObserver->deleting($modelMock);

        $this->assertTrue($modelObserver->deleted($modelMock));
    }

    /**
     * @expectedException \Softonic\TransactionalEventPublisher\Exceptions\EventStoreFailedException
     */
    public function testWhenAnItemIsDeletedButTheEventStoreFailsWhenStoring()
    {
        $payload = ['id' => 123, 'field' => 'value 1'];
        $eventStoreResult = false;

        $mySqlConnectorMock = \Mockery::mock(MySqlConnector::class);
        $mySqlConnectorMock->shouldReceive('beginTransaction')->once();
        $mySqlConnectorMock->shouldReceive('rollBack')->once();

        $modelMock = \Mockery::mock(Model::class);
        $modelMock->shouldReceive('toArray')->once()->andReturn($payload);
        $modelMock
            ->shouldReceive('getConnection')
            ->times(2)
            ->andReturn($mySqlConnectorMock);

        $eventStoreMiddlewareMock = \Mockery::mock(EventStoreMiddlewareContract::class);
        $eventStoreMiddlewareMock
            ->shouldReceive('store')
            ->once()
            ->andReturn($eventStoreResult);

        $modelObserver = new ModelObserver($eventStoreMiddlewareMock, EventMessageStub::class);

        $modelObserver->deleting($modelMock);
        $modelObserver->deleted($modelMock);
    }
}
