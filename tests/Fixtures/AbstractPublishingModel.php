<?php

declare(strict_types=1);

namespace Softonic\TransactionalEventPublisher\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Override;
use Softonic\TransactionalEventPublisher\Concerns\PublishesDomainEvents;

/**
 * Base model of an application: the trait is declared only here.
 */
abstract class AbstractPublishingModel extends Model
{
    use PublishesDomainEvents;

    #[Override]
    public $timestamps = false;

    #[Override]
    protected $table = 'publishing_models';

    #[Override]
    protected $guarded = [];
}
