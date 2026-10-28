<?php

namespace Softonic\TransactionalEventPublisher\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Runs every insert, update and delete of the model in its own transaction, together with the storage of its domain
 * event by the ModelObserver attached to the model. Any failure (query, event store, halted listener...) rolls back
 * only the level opened here.
 */
trait PublishesDomainEvents
{
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
