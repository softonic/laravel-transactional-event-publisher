<?php

namespace Softonic\TransactionalEventPublisher\Interfaces;

use JsonSerializable;

interface EventMessageInterface extends JsonSerializable
{
    /**
     * Returns the message in an array format.
     */
    public function toArray(): array;
}
