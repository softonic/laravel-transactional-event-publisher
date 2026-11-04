<?php

declare(strict_types=1);

namespace Softonic\TransactionalEventPublisher\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Override;

/**
 * A model that does not use the PublishesDomainEvents trait.
 */
class NonPublishingModel extends Model
{
    #[Override]
    public $timestamps = false;

    #[Override]
    protected $table = 'non_publishing_models';

    #[Override]
    protected $guarded = [];
}
