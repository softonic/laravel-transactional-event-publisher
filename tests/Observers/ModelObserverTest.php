<?php

namespace Softonic\TransactionalEventPublisher\Observers;

use Illuminate\Database\Connectors\MySqlConnector;
use Illuminate\Database\Eloquent\Model;
use Softonic\TransactionalEventPublisher\Contracts\EventStoreMiddlewareContract;
use Softonic\TransactionalEventPublisher\TestCase;

class ModelObserverTest extends TestCase
{
    public function testWhenANewItemIsCreatedShouldStoreTheEventMessage()
    {
        $eventStoreResult = true;

        $mySqlConnectorMock = \Mockery::mock(MySqlConnector::class);
        $mySqlConnectorMock->shouldReceive('beginTransaction')->once();
        $mySqlConnectorMock->shouldReceive('commit')->once();

        $modelMock = \Mockery::mock(Model::class);
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
        $eventStoreResult = false;

        $mySqlConnectorMock = \Mockery::mock(MySqlConnector::class);
        $mySqlConnectorMock->shouldReceive('beginTransaction')->once();
        $mySqlConnectorMock->shouldReceive('rollBack')->once();

        $modelMock = \Mockery::mock(Model::class);
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

    public function testWhenAnItemIsUpdatedShouldStoreTheEventMessage()
    {
        $eventStoreResult = true;

        $mySqlConnectorMock = \Mockery::mock(MySqlConnector::class);
        $mySqlConnectorMock->shouldReceive('beginTransaction')->once();
        $mySqlConnectorMock->shouldReceive('commit')->once();

        $modelMock = \Mockery::mock(Model::class);
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
        $eventStoreResult = false;

        $mySqlConnectorMock = \Mockery::mock(MySqlConnector::class);
        $mySqlConnectorMock->shouldReceive('beginTransaction')->once();
        $mySqlConnectorMock->shouldReceive('rollBack')->once();

        $modelMock = \Mockery::mock(Model::class);
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

    public function testWhenAnItemDeletedShouldStoreTheEventMessage()
    {
        $eventStoreResult = true;

        $mySqlConnectorMock = \Mockery::mock(MySqlConnector::class);
        $mySqlConnectorMock->shouldReceive('beginTransaction')->once();
        $mySqlConnectorMock->shouldReceive('commit')->once();

        $modelMock = \Mockery::mock(Model::class);
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
        $eventStoreResult = false;

        $mySqlConnectorMock = \Mockery::mock(MySqlConnector::class);
        $mySqlConnectorMock->shouldReceive('beginTransaction')->once();
        $mySqlConnectorMock->shouldReceive('rollBack')->once();

        $modelMock = \Mockery::mock(Model::class);
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

    public function testWhenItemIsCreatedWithMultipleMiddlewaresShouldStoreTheEventMessagesInAllTheMiddlewares()
    {
        $eventStoreResult = true;

        $mySqlConnectorMock = \Mockery::mock(MySqlConnector::class);
        $mySqlConnectorMock->shouldReceive('beginTransaction')->once();
        $mySqlConnectorMock->shouldReceive('commit')->once();

        $modelMock = \Mockery::mock(Model::class);
        $modelMock
            ->shouldReceive('getConnection')
            ->times(2)
            ->andReturn($mySqlConnectorMock);

        $firstEventStoreMiddlewareMock = \Mockery::mock(EventStoreMiddlewareContract::class);
        $firstEventStoreMiddlewareMock
            ->shouldReceive('store')
            ->once()
            ->andReturn($eventStoreResult);

        $secondEventStoreMiddlewareMock = \Mockery::mock(EventStoreMiddlewareContract::class);
        $secondEventStoreMiddlewareMock
            ->shouldReceive('store')
            ->once()
            ->andReturn($eventStoreResult);

        $modelObserver = new ModelObserver(
            [
                $firstEventStoreMiddlewareMock,
                $secondEventStoreMiddlewareMock,
            ],
            EventMessageStub::class
        );

        $modelObserver->creating($modelMock);

        $this->assertTrue($modelObserver->created($modelMock));
    }
}
