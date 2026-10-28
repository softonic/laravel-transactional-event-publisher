<?php

declare(strict_types=1);

namespace Softonic\TransactionalEventPublisher\Observers;

use Illuminate\Database\Eloquent\Model;
use Softonic\TransactionalEventPublisher\Exceptions\EventStoreFailedException;
use Softonic\TransactionalEventPublisher\Interfaces\EventMessageBuilderInterface;
use Softonic\TransactionalEventPublisher\Interfaces\EventMessageInterface;
use Softonic\TransactionalEventPublisher\Interfaces\EventStoreMiddlewareInterface;

/**
 * Stores the domain event of a model write. It does not manage transactions: the
 * PublishesDomainEvents trait runs the write and this observer in the same transaction.
 */
class DomainEventObserver
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
     * Handles the model created event.
     */
    public function created(Model $model): bool
    {
        $this->storeEventMessage($model, __FUNCTION__);

        return true;
    }

    /**
     * Handles the model updated event.
     */
    public function updated(Model $model): bool
    {
        $this->storeEventMessage($model, __FUNCTION__);

        return true;
    }

    /**
     * Handles the model deleted event.
     */
    public function deleted(Model $model): bool
    {
        $this->storeEventMessage($model, __FUNCTION__);

        return true;
    }

    /**
     * @throws EventStoreFailedException
     */
    public function storeEventMessage(Model $model, string $modelEvent): void
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
}
