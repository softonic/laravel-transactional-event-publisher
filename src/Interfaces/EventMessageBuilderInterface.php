<?php

namespace Softonic\TransactionalEventPublisher\Interfaces;

use Illuminate\Database\Eloquent\Model;

interface EventMessageBuilderInterface
{
    public function build(Model $model, string $eventType): EventMessageInterface;
}
