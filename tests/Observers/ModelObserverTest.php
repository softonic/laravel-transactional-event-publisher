<?php

namespace Softonic\TransactionalEventPublisher\Tests\Observers;

use Illuminate\Database\Connectors\MySqlConnector;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\TestCase;
use Softonic\TransactionalEventPublisher\Contracts\EventStoreMiddlewareContract;
use Softonic\TransactionalEventPublisher\Contracts\MessageBuilderContract;
use Softonic\TransactionalEventPublisher\Entities\EventMessage;
use Softonic\TransactionalEventPublisher\Observers\ModelObserver;

class ModelObserverTest extends TestCase
{
    public function testWhenANewItemIsCreatedShouldSendAnEventMessage()
    {
        $eventMessage = new EventMessage();
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

        $messageBuilderMock = \Mockery::mock(MessageBuilderContract::class);
        $messageBuilderMock
            ->shouldReceive('build')
            ->once()
            ->with(class_basename($modelMock), 'created', $payload)
            ->andReturn($eventMessage);

        $eventStoreMiddlewareMock = \Mockery::mock(EventStoreMiddlewareContract::class);
        $eventStoreMiddlewareMock
            ->shouldReceive('store')
            ->once()
            ->with($eventMessage)
            ->andReturn($eventStoreResult);

        $modelObserver = new ModelObserver($messageBuilderMock, $eventStoreMiddlewareMock);

        $modelObserver->creating($modelMock);

        $this->assertTrue($modelObserver->created($modelMock));
    }

    /**
     * @expectedException \Softonic\TransactionalEventPublisher\Exceptions\EventStoreFailedException
     */
    public function testWhenANewItemIsCreatedButTheEventStoreFailsWhenStoring()
    {
        $eventMessage = new EventMessage();
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

        $messageBuilderMock = \Mockery::mock(MessageBuilderContract::class);
        $messageBuilderMock
            ->shouldReceive('build')
            ->once()
            ->with(class_basename($modelMock), 'created', $payload)
            ->andReturn($eventMessage);

        $eventStoreMiddlewareMock = \Mockery::mock(EventStoreMiddlewareContract::class);
        $eventStoreMiddlewareMock
            ->shouldReceive('store')
            ->once()
            ->with($eventMessage)
            ->andReturn($eventStoreResult);

        $modelObserver = new ModelObserver($messageBuilderMock, $eventStoreMiddlewareMock);

        $modelObserver->creating($modelMock);
        $modelObserver->created($modelMock);
    }

    public function testWhenAnItemIsUpdatedShouldSendAnEventMessage()
    {
        $eventMessage = new EventMessage();
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

        $messageBuilderMock = \Mockery::mock(MessageBuilderContract::class);
        $messageBuilderMock
            ->shouldReceive('build')
            ->once()
            ->with(class_basename($modelMock), 'updated', $payload)
            ->andReturn($eventMessage);

        $eventStoreMiddlewareMock = \Mockery::mock(EventStoreMiddlewareContract::class);
        $eventStoreMiddlewareMock
            ->shouldReceive('store')
            ->once()
            ->with($eventMessage)
            ->andReturn($eventStoreResult);

        $modelObserver = new ModelObserver($messageBuilderMock, $eventStoreMiddlewareMock);

        $modelObserver->updating($modelMock);

        $this->assertTrue($modelObserver->updated($modelMock));
    }

    /**
     * @expectedException \Softonic\TransactionalEventPublisher\Exceptions\EventStoreFailedException
     */
    public function testWhenAnItemIsUpdatedButTheEventStoreFailsWhenStoring()
    {
        $eventMessage = new EventMessage();
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

        $messageBuilderMock = \Mockery::mock(MessageBuilderContract::class);
        $messageBuilderMock
            ->shouldReceive('build')
            ->once()
            ->with(class_basename($modelMock), 'updated', $payload)
            ->andReturn($eventMessage);

        $eventStoreMiddlewareMock = \Mockery::mock(EventStoreMiddlewareContract::class);
        $eventStoreMiddlewareMock
            ->shouldReceive('store')
            ->once()
            ->with($eventMessage)
            ->andReturn($eventStoreResult);

        $modelObserver = new ModelObserver($messageBuilderMock, $eventStoreMiddlewareMock);

        $modelObserver->updating($modelMock);
        $modelObserver->updated($modelMock);
    }

    public function testWhenAnItemDeletedShouldSendAnEventMessage()
    {
        $eventMessage = new EventMessage();
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

        $messageBuilderMock = \Mockery::mock(MessageBuilderContract::class);
        $messageBuilderMock
            ->shouldReceive('build')
            ->once()
            ->with(class_basename($modelMock), 'deleted', $payload)
            ->andReturn($eventMessage);

        $eventStoreMiddlewareMock = \Mockery::mock(EventStoreMiddlewareContract::class);
        $eventStoreMiddlewareMock
            ->shouldReceive('store')
            ->once()
            ->with($eventMessage)
            ->andReturn($eventStoreResult);

        $modelObserver = new ModelObserver($messageBuilderMock, $eventStoreMiddlewareMock);

        $modelObserver->deleting($modelMock);

        $this->assertTrue($modelObserver->deleted($modelMock));
    }

    /**
     * @expectedException \Softonic\TransactionalEventPublisher\Exceptions\EventStoreFailedException
     */
    public function testWhenAnItemIsDeletedButTheEventStoreFailsWhenStoring()
    {
        $eventMessage = new EventMessage();
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

        $messageBuilderMock = \Mockery::mock(MessageBuilderContract::class);
        $messageBuilderMock
            ->shouldReceive('build')
            ->once()
            ->with(class_basename($modelMock), 'deleted', $payload)
            ->andReturn($eventMessage);

        $eventStoreMiddlewareMock = \Mockery::mock(EventStoreMiddlewareContract::class);
        $eventStoreMiddlewareMock
            ->shouldReceive('store')
            ->once()
            ->with($eventMessage)
            ->andReturn($eventStoreResult);

        $modelObserver = new ModelObserver($messageBuilderMock, $eventStoreMiddlewareMock);

        $modelObserver->deleting($modelMock);
        $modelObserver->deleted($modelMock);
    }
}
