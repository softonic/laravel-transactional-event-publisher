<?php

declare(strict_types=1);

namespace Softonic\TransactionalEventPublisher\Services;

use Illuminate\Database\Eloquent\Model;
use Softonic\TransactionalEventPublisher\Exceptions\EventStoreFailedException;
use Softonic\TransactionalEventPublisher\Interfaces\EventMessageBuilderInterface;
use Softonic\TransactionalEventPublisher\Interfaces\EventMessageInterface;
use Softonic\TransactionalEventPublisher\Interfaces\EventStoreMiddlewareInterface;

/**
 * Builds the domain event of a model write and stores it in every configured middleware.
 *
 * It never opens nor closes transactions: the PublishesDomainEvents trait calls it inside the transaction of the write.
 * It is resolved from the container on every write, so a project can replace it with its own binding.
 */
class EventStore
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
     * @throws EventStoreFailedException
     */
    public function store(Model $model, string $eventType): void
    {
        $message = $this->builder->build($model, $eventType);

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
