<?php

namespace Softonic\TransactionalEventPublisher\Observers;

use Illuminate\Database\Connectors\MySqlConnector;
use Illuminate\Database\Eloquent\Model;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Softonic\TransactionalEventPublisher\Exceptions\EventStoreFailedException;
use Softonic\TransactionalEventPublisher\Interfaces\EventMessageBuilderInterface;
use Softonic\TransactionalEventPublisher\Interfaces\EventMessageInterface;
use Softonic\TransactionalEventPublisher\Interfaces\EventStoreMiddlewareInterface;
use Softonic\TransactionalEventPublisher\TestCase;

class ModelObserverTest extends TestCase
{
    #[Test]
    public function whenANewItemIsCreatedShouldStoreTheEventMessage(): void
    {
        $mySqlConnectorMock = Mockery::mock(MySqlConnector::class);
        $mySqlConnectorMock->shouldReceive('beginTransaction')->once();
        $mySqlConnectorMock->shouldReceive('commit')->once();

        $modelMock = Mockery::mock(Model::class);
        $modelMock
            ->shouldReceive('getConnection')
            ->times(2)
            ->andReturn($mySqlConnectorMock);

        $eventMessage = Mockery::mock(EventMessageInterface::class);
        $builderMock = $this->getBuilderMock($modelMock, $eventMessage, 'created');
        $eventStoreMiddlewareMock = $this->whenEventMessageIsStored($eventMessage, true);

        $modelObserver = new ModelObserver($eventStoreMiddlewareMock, $builderMock);

        $modelObserver->creating($modelMock);

        self::assertTrue($modelObserver->created($modelMock));
    }

    #[Test]
    public function whenANewItemIsCreatedButTheEventStoreFailsWhenStoring(): void
    {
        $this->expectException(EventStoreFailedException::class);

        $mySqlConnectorMock = Mockery::mock(MySqlConnector::class);
        $mySqlConnectorMock->shouldReceive('beginTransaction')->once();
        $mySqlConnectorMock->shouldReceive('rollBack')->once();

        $modelMock = Mockery::mock(Model::class);
        $modelMock
            ->shouldReceive('getConnection')
            ->times(2)
            ->andReturn($mySqlConnectorMock);


        $eventMessage = Mockery::mock(EventMessageInterface::class);
        $builderMock = $this->getBuilderMock($modelMock, $eventMessage, 'created');
        $eventStoreMiddlewareMock = $this->whenEventMessageIsStored($eventMessage, false);

        $modelObserver = new ModelObserver($eventStoreMiddlewareMock, $builderMock);

        $modelObserver->creating($modelMock);
        $modelObserver->created($modelMock);
    }

    #[Test]
    public function whenAnItemIsUpdatedShouldStoreTheEventMessage(): void
    {
        $mySqlConnectorMock = Mockery::mock(MySqlConnector::class);
        $mySqlConnectorMock->shouldReceive('beginTransaction')->once();
        $mySqlConnectorMock->shouldReceive('commit')->once();

        $modelMock = Mockery::mock(Model::class);
        $modelMock
            ->shouldReceive('getConnection')
            ->times(2)
            ->andReturn($mySqlConnectorMock);

        $eventMessage = Mockery::mock(EventMessageInterface::class);
        $builderMock = $this->getBuilderMock($modelMock, $eventMessage, 'updated');
        $eventStoreMiddlewareMock = $this->whenEventMessageIsStored($eventMessage, true);

        $modelObserver = new ModelObserver($eventStoreMiddlewareMock, $builderMock);

        $modelObserver->updating($modelMock);

        self::assertTrue($modelObserver->updated($modelMock));
    }

    #[Test]
    public function whenAnItemIsUpdatedButTheEventStoreFailsWhenStoring(): void
    {
        $this->expectException(EventStoreFailedException::class);

        $mySqlConnectorMock = Mockery::mock(MySqlConnector::class);
        $mySqlConnectorMock->shouldReceive('beginTransaction')->once();
        $mySqlConnectorMock->shouldReceive('rollBack')->once();

        $modelMock = Mockery::mock(Model::class);
        $modelMock
            ->shouldReceive('getConnection')
            ->times(2)
            ->andReturn($mySqlConnectorMock);

        $eventMessage = Mockery::mock(EventMessageInterface::class);
        $builderMock = $this->getBuilderMock($modelMock, $eventMessage, 'updated');
        $eventStoreMiddlewareMock = $this->whenEventMessageIsStored($eventMessage, false);

        $modelObserver = new ModelObserver($eventStoreMiddlewareMock, $builderMock);

        $modelObserver->updating($modelMock);
        $modelObserver->updated($modelMock);
    }

    #[Test]
    public function whenAnItemDeletedShouldStoreTheEventMessage(): void
    {
        $mySqlConnectorMock = Mockery::mock(MySqlConnector::class);
        $mySqlConnectorMock->shouldReceive('beginTransaction')->once();
        $mySqlConnectorMock->shouldReceive('commit')->once();

        $modelMock = Mockery::mock(Model::class);
        $modelMock
            ->shouldReceive('getConnection')
            ->times(2)
            ->andReturn($mySqlConnectorMock);

        $eventMessage = Mockery::mock(EventMessageInterface::class);
        $builderMock = $this->getBuilderMock($modelMock, $eventMessage, 'deleted');
        $eventStoreMiddlewareMock = $this->whenEventMessageIsStored($eventMessage, true);

        $modelObserver = new ModelObserver($eventStoreMiddlewareMock, $builderMock);

        $modelObserver->deleting($modelMock);

        self::assertTrue($modelObserver->deleted($modelMock));
    }

    #[Test]
    public function whenAnItemIsDeletedButTheEventStoreFailsWhenStoring(): void
    {
        $this->expectException(EventStoreFailedException::class);

        $mySqlConnectorMock = Mockery::mock(MySqlConnector::class);
        $mySqlConnectorMock->shouldReceive('beginTransaction')->once();
        $mySqlConnectorMock->shouldReceive('rollBack')->once();

        $modelMock = Mockery::mock(Model::class);
        $modelMock
            ->shouldReceive('getConnection')
            ->times(2)
            ->andReturn($mySqlConnectorMock);

        $eventMessage = Mockery::mock(EventMessageInterface::class);
        $builderMock = $this->getBuilderMock($modelMock, $eventMessage, 'deleted');
        $eventStoreMiddlewareMock = $this->whenEventMessageIsStored($eventMessage, false);

        $modelObserver = new ModelObserver($eventStoreMiddlewareMock, $builderMock);

        $modelObserver->deleting($modelMock);
        $modelObserver->deleted($modelMock);
    }

    #[Test]
    public function whenItemIsCreatedWithMultipleMiddlewaresShouldStoreTheEventMessagesInAllTheMiddlewares(): void
    {
        $mySqlConnectorMock = Mockery::mock(MySqlConnector::class);
        $mySqlConnectorMock->shouldReceive('beginTransaction')->once();
        $mySqlConnectorMock->shouldReceive('commit')->once();

        $modelMock = Mockery::mock(Model::class);
        $modelMock
            ->shouldReceive('getConnection')
            ->times(2)
            ->andReturn($mySqlConnectorMock);

        $eventMessage = Mockery::mock(EventMessageInterface::class);
        $builderMock = $this->getBuilderMock($modelMock, $eventMessage, 'created');
        $firstEventStoreMiddlewareMock = $this->whenEventMessageIsStored($eventMessage, true);
        $secondEventStoreMiddlewareMock = $this->whenEventMessageIsStored($eventMessage, true);

        $modelObserver = new ModelObserver(
            [
                $firstEventStoreMiddlewareMock,
                $secondEventStoreMiddlewareMock,
            ],
            $builderMock
        );

        $modelObserver->creating($modelMock);

        self::assertTrue($modelObserver->created($modelMock));
    }

    private function getBuilderMock(
        Model $modelMock,
        EventMessageInterface $eventMessage,
        string $eventType
    ): EventMessageBuilderInterface {
        $builderMock = Mockery::mock(EventMessageBuilderInterface::class);
        $builderMock
            ->shouldReceive('build')
            ->once()
            ->with($modelMock, $eventType)
            ->andReturn($eventMessage);

        return $builderMock;
    }

    private function whenEventMessageIsStored(
        EventMessageInterface $eventMessage,
        bool $result
    ): EventStoreMiddlewareInterface {
        $eventStoreMiddlewareMock = Mockery::mock(EventStoreMiddlewareInterface::class);
        $eventStoreMiddlewareMock
            ->shouldReceive('store')
            ->once()
            ->with($eventMessage)
            ->andReturn($result);

        return $eventStoreMiddlewareMock;
    }
}
