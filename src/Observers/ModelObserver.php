<?php

declare(strict_types=1);

namespace Softonic\TransactionalEventPublisher\Observers;

use Illuminate\Database\Eloquent\Model;
use Softonic\TransactionalEventPublisher\Concerns\PublishesDomainEvents;
use Softonic\TransactionalEventPublisher\Exceptions\EventStoreFailedException;
use Softonic\TransactionalEventPublisher\Interfaces\EventMessageBuilderInterface;
use Softonic\TransactionalEventPublisher\Interfaces\EventStoreMiddlewareInterface;

/**
 * @deprecated since 11.1.0, use the PublishesDomainEvents trait in the model (or its base model) instead.
 *             This observer opens a transaction level in the "-ing" events that is left open when the query fails.
 */
class ModelObserver
{
    private static bool $deprecationTriggered = false;

    private readonly DomainEventObserver $domainEventObserver;

    /**
     * @param EventStoreMiddlewareInterface | EventStoreMiddlewareInterface[] $eventStoreMiddleware
     */
    public function __construct(
        array|EventStoreMiddlewareInterface $eventStoreMiddleware,
        protected readonly EventMessageBuilderInterface $builder
    ) {
        $this->domainEventObserver = new DomainEventObserver($eventStoreMiddleware, $builder);
    }

    /**
     * Handles the model creating event.
     */
    public function creating(Model $model): void
    {
        if ($this->isHandledByTrait($model)) {
            return;
        }

        $this->triggerDeprecation();

        $model->getConnection()->beginTransaction();
    }

    /**
     * Handles the model created event.
     */
    public function created(Model $model): bool
    {
        if ($this->isHandledByTrait($model)) {
            return true;
        }

        $this->performStoreEventMessage($model, __FUNCTION__);

        return true;
    }

    /**
     * Handles the model updating event.
     */
    public function updating(Model $model): void
    {
        if ($this->isHandledByTrait($model)) {
            return;
        }

        $this->triggerDeprecation();

        $model->getConnection()->beginTransaction();
    }

    /**
     * Handles the model updated event.
     */
    public function updated(Model $model): bool
    {
        if ($this->isHandledByTrait($model)) {
            return true;
        }

        $this->performStoreEventMessage($model, __FUNCTION__);

        return true;
    }

    /**
     * Handles the model deleting event.
     */
    public function deleting(Model $model): void
    {
        if ($this->isHandledByTrait($model)) {
            return;
        }

        $this->triggerDeprecation();

        $model->getConnection()->beginTransaction();
    }

    /**
     * Handles the model deleted event.
     */
    public function deleted(Model $model): bool
    {
        if ($this->isHandledByTrait($model)) {
            return true;
        }

        $this->performStoreEventMessage($model, __FUNCTION__);

        return true;
    }

    private function performStoreEventMessage(Model $model, string $modelEvent): void
    {
        $connection = $model->getConnection();

        try {
            $this->domainEventObserver->storeEventMessage($model, $modelEvent);
        } catch (EventStoreFailedException $exception) {
            $connection->rollBack();

            throw $exception;
        }

        $connection->commit();
    }

    /**
     * Models using the PublishesDomainEvents trait are already handled by the DomainEventObserver.
     */
    private function isHandledByTrait(Model $model): bool
    {
        return in_array(PublishesDomainEvents::class, class_uses_recursive($model), true);
    }

    private function triggerDeprecation(): void
    {
        if (self::$deprecationTriggered) {
            return;
        }

        self::$deprecationTriggered = true;
        trigger_error(
            self::class . ' is deprecated since 11.1.0, use the ' . PublishesDomainEvents::class . ' trait in the model instead.',
            E_USER_DEPRECATED
        );
    }
}
