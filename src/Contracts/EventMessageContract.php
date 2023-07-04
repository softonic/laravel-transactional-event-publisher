<?php

namespace Softonic\TransactionalEventPublisher\Contracts;

use Illuminate\Database\Eloquent\Model;
use JsonSerializable;

interface EventMessageContract extends JsonSerializable
{
    public function __construct(Model $model, $eventType);

    /**
     * Returns the message in an array format.
     */
    public function toArray(): array;
}
