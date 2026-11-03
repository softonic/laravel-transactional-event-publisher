<?php

declare(strict_types=1);

namespace Softonic\TransactionalEventPublisher\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Override;
use Softonic\TransactionalEventPublisher\Concerns\PublishesDomainEvents;

class SoftDeletingPublishingModel extends Model
{
    use PublishesDomainEvents;
    use SoftDeletes;

    #[Override]
    public $timestamps = false;

    #[Override]
    protected $table = 'publishing_models';

    #[Override]
    protected $guarded = [];
}
