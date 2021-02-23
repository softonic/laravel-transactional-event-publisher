<?php

namespace Softonic\TransactionalEventPublisher\Contracts;

use Illuminate\Database\Eloquent\Model;
use JsonSerializable;

/**
 * Interface EventMessageContract
 *
 * @package Softonic\TransactionalEventPublisher\Contracts
 */
interface EventMessageContract extends JsonSerializable
{
    /**
     * EventMessageContract constructor.
     *
     * @param Model $model
     * @param       $eventType
     */
    public function __construct(Model $model, $eventType);

    /**
     * Generates the createdAt value.
     */
    public function generateCreatedAt(): string;

    /**
     * Returns the message in an array format.
     *
     * @return array
     */
    public function toArray();
}
