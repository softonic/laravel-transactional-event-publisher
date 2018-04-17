<?php

namespace Softonic\TransactionalEventPublisher\Observers;

use Illuminate\Database\Eloquent\Model;
use Softonic\TransactionalEventPublisher\Contracts\EventStoreMiddlewareContract;
use Softonic\TransactionalEventPublisher\Exceptions\EventStoreFailedException;

/**
 * Class ModelObserver
 *
 * @package Softonic\TransactionalEventPublisher\Observers
 */
class ModelObserver
{
    private $eventStoreMiddleware;

    private $messageClass;

    /**
     * ModelObserver constructor.
     *
     * @param EventStoreMiddlewareContract | EventStoreMiddlewareContract[] $eventStoreMiddleware
     * @param string                                                        $messageClass
     */
    public function __construct(
        $eventStoreMiddleware,
        $messageClass
    ) {
        $this->eventStoreMiddleware = is_array($eventStoreMiddleware) ? $eventStoreMiddleware : [$eventStoreMiddleware];
        $this->messageClass         = $messageClass;
    }

    /**
     * Handles the model creating event.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     *
     * @throws \Exception
     */
    public function creating(Model $model)
    {
        $model->getConnection()->beginTransaction();
    }

    /**
     * Handles the model created event.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     *
     * @return bool
     */
    public function created(Model $model)
    {
        $this->performStoreEventMessage($model, __FUNCTION__);

        return true;
    }

    /**
     * Handles the model updating event.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     *
     * @throws \Exception
     */
    public function updating(Model $model)
    {
        $model->getConnection()->beginTransaction();
    }

    /**
     * Handles the model updated event.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     *
     * @return bool
     */
    public function updated(Model $model)
    {
        $this->performStoreEventMessage($model, __FUNCTION__);

        return true;
    }

    /**
     * Handles the model deleting event.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     *
     * @throws \Exception
     */
    public function deleting(Model $model)
    {
        $model->getConnection()->beginTransaction();
    }

    /**
     * Handles the model deleted event.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     *
     * @return bool
     */
    public function deleted(Model $model)
    {
        $this->performStoreEventMessage($model, __FUNCTION__);

        return true;
    }

    private function performStoreEventMessage(Model $model, $modelEvent)
    {
        $connection = $model->getConnection();
        $message    = new $this->messageClass($model, $modelEvent);

        if (true === $this->executeMiddlewares($message)) {
            $connection->commit();
        } else {
            $connection->rollBack();
            throw new EventStoreFailedException('Event Store failed when storing event message');
        }
    }

    /**
     * @param $message
     *
     * @return mixed
     */
    private function executeMiddlewares($message): bool
    {
        $success = true;
        foreach($this->eventStoreMiddleware as $middleware) {
            $success &= $middleware->store($message);
        }

        return $success;
    }
}
