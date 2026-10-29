<?php

declare(strict_types=1);

namespace Softonic\TransactionalEventPublisher\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Override;

class LegacyModel extends Model
{
    #[Override]
    public $timestamps = false;

    #[Override]
    protected $table = 'legacy_models';

    #[Override]
    protected $guarded = [];
}
