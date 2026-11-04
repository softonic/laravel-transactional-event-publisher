<?php

declare(strict_types=1);

namespace Softonic\TransactionalEventPublisher\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Softonic\TransactionalEventPublisher\DomainEvents;
use Softonic\TransactionalEventPublisher\Services\EventStore;

/**
 * Publishes the domain event of every insert, update (including increment and decrement) and delete of the model.
 *
 * Each write runs in its own transaction, together with the storage of its event through the EventStore resolved from
 * the container. Any failure (query, message builder, event store, a listener halting the write) rolls back only the
 * level opened here. Nothing is stored while publishing is disabled through DomainEvents.
 */
trait PublishesDomainEvents
{
    /**
     * Laravel calls it once per concrete model class when it boots, so a base model using the trait covers every model
     * extending it, each with its own listeners.
     */
    public static function bootPublishesDomainEvents(): void
    {
        foreach (['created', 'updated', 'deleted'] as $modelEvent) {
            static::registerModelEvent($modelEvent, static function (Model $model) use ($modelEvent): void {
                if (DomainEvents::isEnabled()) {
                    resolve(EventStore::class)->store($model, $modelEvent);
                }
            });
        }
    }

    /**
     * @return bool
     */
    protected function performInsert(Builder $query)
    {
        return $this->getConnection()->transaction(fn () => parent::performInsert($query));
    }

    /**
     * @return bool
     */
    protected function performUpdate(Builder $query)
    {
        return $this->getConnection()->transaction(fn () => parent::performUpdate($query));
    }

    /**
     * increment() and decrement() fire the "updated" event without going through performUpdate().
     *
     * @param string $column
     * @param float|int $amount
     * @param array<string, mixed> $extra
     * @param string $method
     * @return int|false
     */
    protected function incrementOrDecrement($column, $amount, $extra, $method)
    {
        return $this->getConnection()->transaction(
            fn () => parent::incrementOrDecrement($column, $amount, $extra, $method)
        );
    }

    /**
     * The "deleted" event is fired outside performDeleteOnModel(), so the whole delete is wrapped.
     *
     * @return bool|null
     */
    public function delete()
    {
        return $this->getConnection()->transaction(fn () => parent::delete());
    }
}
