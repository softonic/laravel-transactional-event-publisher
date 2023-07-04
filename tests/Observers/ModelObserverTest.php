<?php

namespace Softonic\TransactionalEventPublisher\Observers;

use Illuminate\Database\Connectors\MySqlConnector;
use Illuminate\Database\Eloquent\Model;
use Mockery;
use Softonic\TransactionalEventPublisher\Contracts\EventStoreMiddlewareContract;
use Softonic\TransactionalEventPublisher\Exceptions\EventStoreFailedException;
use Softonic\TransactionalEventPublisher\TestCase;

class ModelObserverTest extends TestCase
{
    /**
     * @test
     */
    public function whenANewItemIsCreatedShouldStoreTheEventMessage()
    {
        $eventStoreResult = true;

        $mySqlConnectorMock = Mockery::mock(MySqlConnector::class);
        $mySqlConnectorMock->shouldReceive('beginTransaction')->once();
        $mySqlConnectorMock->shouldReceive('commit')->once();

        $modelMock = Mockery::mock(Model::class);
        $modelMock
            ->shouldReceive('getConnection')
            ->times(2)
            ->andReturn($mySqlConnectorMock);

        $eventStoreMiddlewareMock = Mockery::mock(EventStoreMiddlewareContract::class);
        $eventStoreMiddlewareMock
            ->shouldReceive('store')
            ->once()
            ->andReturn($eventStoreResult);

        $modelObserver = new ModelObserver($eventStoreMiddlewareMock, EventMessageStub::class);

        $modelObserver->creating($modelMock);

        self::assertTrue($modelObserver->created($modelMock));
    }

    /**
     * @test
     */
    public function whenANewItemIsCreatedButTheEventStoreFailsWhenStoring()
    {
        $this->expectException(EventStoreFailedException::class);

        $eventStoreResult = false;

        $mySqlConnectorMock = Mockery::mock(MySqlConnector::class);
        $mySqlConnectorMock->shouldReceive('beginTransaction')->once();
        $mySqlConnectorMock->shouldReceive('rollBack')->once();

        $modelMock = Mockery::mock(Model::class);
        $modelMock
            ->shouldReceive('getConnection')
            ->times(2)
            ->andReturn($mySqlConnectorMock);

        $eventStoreMiddlewareMock = Mockery::mock(EventStoreMiddlewareContract::class);
        $eventStoreMiddlewareMock
            ->shouldReceive('store')
            ->once()
            ->andReturn($eventStoreResult);

        $modelObserver = new ModelObserver($eventStoreMiddlewareMock, EventMessageStub::class);

        $modelObserver->creating($modelMock);
        $modelObserver->created($modelMock);
    }

    /**
     * @test
     */
    public function whenAnItemIsUpdatedShouldStoreTheEventMessage()
    {
        $eventStoreResult = true;

        $mySqlConnectorMock = Mockery::mock(MySqlConnector::class);
        $mySqlConnectorMock->shouldReceive('beginTransaction')->once();
        $mySqlConnectorMock->shouldReceive('commit')->once();

        $modelMock = Mockery::mock(Model::class);
        $modelMock
            ->shouldReceive('getConnection')
            ->times(2)
            ->andReturn($mySqlConnectorMock);

        $eventStoreMiddlewareMock = Mockery::mock(EventStoreMiddlewareContract::class);
        $eventStoreMiddlewareMock
            ->shouldReceive('store')
            ->once()
            ->andReturn($eventStoreResult);

        $modelObserver = new ModelObserver($eventStoreMiddlewareMock, EventMessageStub::class);

        $modelObserver->updating($modelMock);

        self::assertTrue($modelObserver->updated($modelMock));
    }

    /**
     * @test
     */
    public function whenAnItemIsUpdatedButTheEventStoreFailsWhenStoring()
    {
        $this->expectException(EventStoreFailedException::class);

        $eventStoreResult = false;

        $mySqlConnectorMock = Mockery::mock(MySqlConnector::class);
        $mySqlConnectorMock->shouldReceive('beginTransaction')->once();
        $mySqlConnectorMock->shouldReceive('rollBack')->once();

        $modelMock = Mockery::mock(Model::class);
        $modelMock
            ->shouldReceive('getConnection')
            ->times(2)
            ->andReturn($mySqlConnectorMock);

        $eventStoreMiddlewareMock = Mockery::mock(EventStoreMiddlewareContract::class);
        $eventStoreMiddlewareMock
            ->shouldReceive('store')
            ->once()
            ->andReturn($eventStoreResult);

        $modelObserver = new ModelObserver($eventStoreMiddlewareMock, EventMessageStub::class);

        $modelObserver->updating($modelMock);
        $modelObserver->updated($modelMock);
    }

    /**
     * @test
     */
    public function whenAnItemDeletedShouldStoreTheEventMessage()
    {
        $eventStoreResult = true;

        $mySqlConnectorMock = Mockery::mock(MySqlConnector::class);
        $mySqlConnectorMock->shouldReceive('beginTransaction')->once();
        $mySqlConnectorMock->shouldReceive('commit')->once();

        $modelMock = Mockery::mock(Model::class);
        $modelMock
            ->shouldReceive('getConnection')
            ->times(2)
            ->andReturn($mySqlConnectorMock);

        $eventStoreMiddlewareMock = Mockery::mock(EventStoreMiddlewareContract::class);
        $eventStoreMiddlewareMock
            ->shouldReceive('store')
            ->once()
            ->andReturn($eventStoreResult);

        $modelObserver = new ModelObserver($eventStoreMiddlewareMock, EventMessageStub::class);

        $modelObserver->deleting($modelMock);

        self::assertTrue($modelObserver->deleted($modelMock));
    }

    /**
     * @test
     */
    public function whenAnItemIsDeletedButTheEventStoreFailsWhenStoring()
    {
        $this->expectException(EventStoreFailedException::class);
        $eventStoreResult = false;

        $mySqlConnectorMock = Mockery::mock(MySqlConnector::class);
        $mySqlConnectorMock->shouldReceive('beginTransaction')->once();
        $mySqlConnectorMock->shouldReceive('rollBack')->once();

        $modelMock = Mockery::mock(Model::class);
        $modelMock
            ->shouldReceive('getConnection')
            ->times(2)
            ->andReturn($mySqlConnectorMock);

        $eventStoreMiddlewareMock = Mockery::mock(EventStoreMiddlewareContract::class);
        $eventStoreMiddlewareMock
            ->shouldReceive('store')
            ->once()
            ->andReturn($eventStoreResult);

        $modelObserver = new ModelObserver($eventStoreMiddlewareMock, EventMessageStub::class);

        $modelObserver->deleting($modelMock);
        $modelObserver->deleted($modelMock);
    }

    /**
     * @test
     */
    public function whenItemIsCreatedWithMultipleMiddlewaresShouldStoreTheEventMessagesInAllTheMiddlewares()
    {
        $eventStoreResult = true;

        $mySqlConnectorMock = Mockery::mock(MySqlConnector::class);
        $mySqlConnectorMock->shouldReceive('beginTransaction')->once();
        $mySqlConnectorMock->shouldReceive('commit')->once();

        $modelMock = Mockery::mock(Model::class);
        $modelMock
            ->shouldReceive('getConnection')
            ->times(2)
            ->andReturn($mySqlConnectorMock);

        $firstEventStoreMiddlewareMock = Mockery::mock(EventStoreMiddlewareContract::class);
        $firstEventStoreMiddlewareMock
            ->shouldReceive('store')
            ->once()
            ->andReturn($eventStoreResult);

        $secondEventStoreMiddlewareMock = Mockery::mock(EventStoreMiddlewareContract::class);
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

        self::assertTrue($modelObserver->created($modelMock));
    }
}
