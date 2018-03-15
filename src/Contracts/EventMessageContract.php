<?php

namespace Softonic\TransactionalEventPublisher\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Interface EventMessageContract
 *
 * @package Softonic\TransactionalEventPublisher\Contracts
 */
interface EventMessageContract extends \JsonSerializable
{
    /**
     * EventMessageContract constructor.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param                                     $eventType
     */
    public function __construct(Model $model, $eventType);

    /**
     * Returns the message in an array format.
     *
     * @return array
     */
    public function toArray();
}