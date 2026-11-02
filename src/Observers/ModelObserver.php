<?php

declare(strict_types=1);

namespace Softonic\TransactionalEventPublisher\Observers;

use Illuminate\Database\Eloquent\Model;
use Softonic\TransactionalEventPublisher\Concerns\PublishesDomainEvents;
use Softonic\TransactionalEventPublisher\DomainEvents;
use Softonic\TransactionalEventPublisher\Exceptions\EventStoreFailedException;
use Softonic\TransactionalEventPublisher\Exceptions\ModelWithoutPublishesDomainEventsException;
use Softonic\TransactionalEventPublisher\Interfaces\EventMessageBuilderInterface;
use Softonic\TransactionalEventPublisher\Interfaces\EventMessageInterface;
use Softonic\TransactionalEventPublisher\Interfaces\EventStoreMiddlewareInterface;

/**
 * Stores the domain event of every insert, update and delete of the observed models.
 *
 * The observed models must use the PublishesDomainEvents trait, which runs the write and this observer in the same
 * transaction. Writes of observed models without the trait are rejected before they reach the database.
 */
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
     *
     * @throws ModelWithoutPublishesDomainEventsException
     */
    public function creating(Model $model): void
    {
        $this->ensureModelUsesTrait($model);
    }

    /**
     * Handles the model created event.
     *
     * @throws EventStoreFailedException
     */
    public function created(Model $model): bool
    {
        $this->performStoreEventMessage($model, __FUNCTION__);

        return true;
    }

    /**
     * Handles the model updating event.
     *
     * @throws ModelWithoutPublishesDomainEventsException
     */
    public function updating(Model $model): void
    {
        $this->ensureModelUsesTrait($model);
    }

    /**
     * Handles the model updated event.
     *
     * @throws EventStoreFailedException
     */
    public function updated(Model $model): bool
    {
        $this->performStoreEventMessage($model, __FUNCTION__);

        return true;
    }

    /**
     * Handles the model deleting event.
     *
     * @throws ModelWithoutPublishesDomainEventsException
     */
    public function deleting(Model $model): void
    {
        $this->ensureModelUsesTrait($model);
    }

    /**
     * Handles the model deleted event.
     *
     * @throws EventStoreFailedException
     */
    public function deleted(Model $model): bool
    {
        $this->performStoreEventMessage($model, __FUNCTION__);

        return true;
    }

    /**
     * Nothing is stored while publishing is disabled through DomainEvents.
     *
     * @throws EventStoreFailedException
     */
    private function performStoreEventMessage(Model $model, string $modelEvent): void
    {
        if (!DomainEvents::isEnabled()) {
            return;
        }

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
     * @throws ModelWithoutPublishesDomainEventsException
     */
    private function ensureModelUsesTrait(Model $model): void
    {
        if (!in_array(PublishesDomainEvents::class, class_uses_recursive($model), true)) {
            throw ModelWithoutPublishesDomainEventsException::forModel($model::class);
        }
    }
}
