<?php

namespace Softonic\TransactionalEventPublisher;

use Illuminate\Database\Eloquent\Model;
use Softonic\TransactionalEventPublisher\Contracts\EventMessageContract;

class CustomEventMessage implements EventMessageContract
{
    /**
     * EventMessageContract constructor.
     *
     * @param Model $model
     * @param       $eventType
     */
    public function __construct(Model $model, $eventType)
    {
    }

    /**
     * Returns the message in an array format.
     *
     * @return array
     */
    public function toArray()
    {
        return ['test'];
    }

    /**
     * Specify data which should be serialized to JSON
     * @link  http://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     *               which is a value of any type other than a resource.
     * @since 5.4.0
     */
    public function jsonSerialize()
    {
        return '["test"]';
    }
}
