<?php

namespace Softonic\TransactionalEventPublisher\Observers;

use Illuminate\Database\Eloquent\Model;
use Softonic\TransactionalEventPublisher\Contracts\EventStoreMiddlewareContract;
use Softonic\TransactionalEventPublisher\Contracts\MessageBuilderContract;
use Softonic\TransactionalEventPublisher\Exceptions\EventStoreFailedException;

/**
 * Class ModelObserver
 *
 * @package Softonic\TransactionalEventPublisher\Observers
 */
class ModelObserver
{
    private $messageBuilder;

    private $eventStoreMiddleware;

    /**
     * ModelObserver constructor.
     *
     * @param \Softonic\TransactionalEventPublisher\Contracts\MessageBuilderContract       $messageBuilder
     * @param \Softonic\TransactionalEventPublisher\Contracts\EventStoreMiddlewareContract $eventStoreMiddleware
     */
    public function __construct(MessageBuilderContract $messageBuilder, EventStoreMiddlewareContract $eventStoreMiddleware)
    {
        $this->messageBuilder = $messageBuilder;
        $this->eventStoreMiddleware = $eventStoreMiddleware;
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
        $classBasename = class_basename($model);
        $message = $this->messageBuilder->build($classBasename, $modelEvent, $model->toArray());

        if (true === $this->eventStoreMiddleware->store($message)) {
            $connection->commit();
        } else {
            $connection->rollBack();
            throw new EventStoreFailedException('Event Store failed when storing event message');
        }
    }
}
