<?php

declare(strict_types=1);

namespace Softonic\TransactionalEventPublisher\Observers;

use Illuminate\Database\Eloquent\Model;
use Softonic\TransactionalEventPublisher\Concerns\PublishesDomainEvents;
use Softonic\TransactionalEventPublisher\DomainEvents;
use Softonic\TransactionalEventPublisher\Exceptions\EventStoreFailedException;
use Softonic\TransactionalEventPublisher\Interfaces\EventMessageBuilderInterface;
use Softonic\TransactionalEventPublisher\Interfaces\EventMessageInterface;
use Softonic\TransactionalEventPublisher\Interfaces\EventStoreMiddlewareInterface;

/**
 * Stores the domain event of every insert, update and delete of the observed models.
 *
 * On models using the PublishesDomainEvents trait it only stores the event: the trait runs the write and this
 * observer in the same transaction. On models without the trait it keeps the deprecated behaviour of opening a
 * transaction level in the "-ing" events, which is left open when the query fails.
 */
class ModelObserver
{
    private static bool $deprecationTriggered = false;

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
        $this->beginLegacyTransaction($model);
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
        $this->beginLegacyTransaction($model);
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
        $this->beginLegacyTransaction($model);
    }

    /**
     * Handles the model deleted event.
     */
    public function deleted(Model $model): bool
    {
        $this->performStoreEventMessage($model, __FUNCTION__);

        return true;
    }

    /**
     * Nothing is done while publishing is disabled through DomainEvents.
     *
     * @throws EventStoreFailedException
     */
    private function performStoreEventMessage(Model $model, string $modelEvent): void
    {
        if (!DomainEvents::isEnabled()) {
            return;
        }

        if ($this->usesTrait($model)) {
            $this->storeEventMessage($model, $modelEvent);

            return;
        }

        $connection = $model->getConnection();

        try {
            $this->storeEventMessage($model, $modelEvent);
        } catch (EventStoreFailedException $exception) {
            $connection->rollBack();

            throw $exception;
        }

        $connection->commit();
    }

    /**
     * @throws EventStoreFailedException
     */
    private function storeEventMessage(Model $model, string $modelEvent): void
    {
        $message = $this->builder->build($model, $modelEvent);

        if (!$this->executeMiddlewares($message)) {
            throw new EventStoreFailedException('Event Store failed when storing event message');
        }
    }

    private function executeMiddlewares(EventMessageInterface $message): bool
    {
        $success = true;
        foreach ($this->eventStoreMiddleware as $middleware) {
            $success &= $middleware->store($message);
        }

        return (bool) $success;
    }

    /**
     * @deprecated since 11.1.0, add the PublishesDomainEvents trait to the model (or its base model) instead.
     */
    private function beginLegacyTransaction(Model $model): void
    {
        if (!DomainEvents::isEnabled() || $this->usesTrait($model)) {
            return;
        }

        $this->triggerDeprecation();

        $model->getConnection()->beginTransaction();
    }

    private function usesTrait(Model $model): bool
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
            'Observing a model without the ' . PublishesDomainEvents::class . ' trait with ' . self::class
            . ' is deprecated since 11.1.0, add the trait to the model.',
            E_USER_DEPRECATED
        );
    }
}
