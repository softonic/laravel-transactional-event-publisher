<?php

namespace Softonic\TransactionalEventPublisher\Observers;

use Illuminate\Database\Eloquent\Model;
use Softonic\TransactionalEventPublisher\Contracts\EventStoreMiddlewareContract;
use Softonic\TransactionalEventPublisher\Exceptions\EventStoreFailedException;

class ModelObserver
{
    private $eventStoreMiddleware;

    /**
     * @param EventStoreMiddlewareContract | EventStoreMiddlewareContract[] $eventStoreMiddleware
     */
    public function __construct(
        $eventStoreMiddleware,
        private readonly string $messageClass
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

    private function performStoreEventMessage(Model $model, $modelEvent): void
    {
        $connection = $model->getConnection();
        $message = new $this->messageClass($model, $modelEvent);

        if (true === $this->executeMiddlewares($message)) {
            $connection->commit();
        } else {
            $connection->rollBack();
            throw new EventStoreFailedException('Event Store failed when storing event message');
        }
    }

    private function executeMiddlewares($message): bool
    {
        $success = true;
        foreach ($this->eventStoreMiddleware as $middleware) {
            $success &= $middleware->store($message);
        }

        return $success;
    }
}
