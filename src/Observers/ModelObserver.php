<?php

namespace Softonic\TransactionalEventPublisher\Observers;

use Illuminate\Database\Eloquent\Model;
use Softonic\TransactionalEventPublisher\Exceptions\EventStoreFailedException;
use Softonic\TransactionalEventPublisher\Interfaces\EventMessageBuilderInterface;
use Softonic\TransactionalEventPublisher\Interfaces\EventMessageInterface;
use Softonic\TransactionalEventPublisher\Interfaces\EventStoreMiddlewareInterface;

class ModelObserver
{
    /**
     * @var EventStoreMiddlewareInterface[]
     */
    private readonly array $eventStoreMiddleware;

    /**
     * @param EventStoreMiddlewareInterface | EventStoreMiddlewareInterface[] $eventStoreMiddleware
     */
    public function __construct(
        array|EventStoreMiddlewareInterface $eventStoreMiddleware,
        protected readonly EventMessageBuilderInterface $builder
    ) {
        $this->eventStoreMiddleware = is_array($eventStoreMiddleware) ? $eventStoreMiddleware : [$eventStoreMiddleware];
    }

    /**
     * Handles the model creating event.
     */
    public function creating(Model $model): void
    {
        $model->getConnection()->beginTransaction();
    }

    /**
     * Handles the model created event.
     */
    public function created(Model $model): bool
    {
        $this->performStoreEventMessage($model, __FUNCTION__);

        return true;
    }

    /**
     * Handles the model updating event.
     */
    public function updating(Model $model): void
    {
        $model->getConnection()->beginTransaction();
    }

    /**
     * Handles the model updated event.
     */
    public function updated(Model $model): bool
    {
        $this->performStoreEventMessage($model, __FUNCTION__);

        return true;
    }

    /**
     * Handles the model deleting event.
     */
    public function deleting(Model $model): void
    {
        $model->getConnection()->beginTransaction();
    }

    /**
     * Handles the model deleted event.
     */
    public function deleted(Model $model): bool
    {
        $this->performStoreEventMessage($model, __FUNCTION__);

        return true;
    }

    private function performStoreEventMessage(Model $model, string $modelEvent): void
    {
        $connection = $model->getConnection();
        $message = $this->builder->build($model, $modelEvent);

        if ($this->executeMiddlewares($message)) {
            $connection->commit();
        } else {
            $connection->rollBack();
            throw new EventStoreFailedException('Event Store failed when storing event message');
        }
    }

    private function executeMiddlewares(EventMessageInterface $message): bool
    {
        $success = true;
        foreach ($this->eventStoreMiddleware as $middleware) {
            $success &= $middleware->store($message);
        }

        return $success;
    }
}
