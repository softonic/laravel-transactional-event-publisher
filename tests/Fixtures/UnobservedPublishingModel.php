<?php

declare(strict_types=1);

namespace Softonic\TransactionalEventPublisher\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Override;
use Softonic\TransactionalEventPublisher\Concerns\PublishesDomainEvents;

/**
 * Uses the trait but nobody attaches the ModelObserver to it.
 */
class UnobservedPublishingModel extends Model
{
    use PublishesDomainEvents;

    #[Override]
    public $timestamps = false;

    #[Override]
    protected $table = 'publishing_models';

    #[Override]
    protected $guarded = [];
}
