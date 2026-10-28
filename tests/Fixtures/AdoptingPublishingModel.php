<?php

declare(strict_types=1);

namespace Softonic\TransactionalEventPublisher\Fixtures;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Override;
use Softonic\TransactionalEventPublisher\Concerns\PublishesDomainEvents;

/**
 * Reproduces a model that, on a unique violation, "adopts" the
 * already existing row instead of failing, without touching transaction levels.
 */
class AdoptingPublishingModel extends Model
{
    use PublishesDomainEvents {
        performInsert as transactionalPerformInsert;
    }

    #[Override]
    public $timestamps = false;

    #[Override]
    protected $table = 'publishing_models';

    #[Override]
    protected $guarded = [];

    protected function performInsert(Builder $query)
    {
        try {
            return $this->transactionalPerformInsert($query);
        } catch (UniqueConstraintViolationException) {
            $this->exists = true;
            $this->wasRecentlyCreated = false;

            return true;
        }
    }
}
