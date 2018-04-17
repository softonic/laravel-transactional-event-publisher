<?php

namespace Softonic\TransactionalEventPublisher\Model;

use Illuminate\Database\Eloquent\Model;
use Softonic\TransactionalEventPublisher\Contracts\EventMessageContract;

class DomainEvent extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['message'];

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    public function setMessageAttribute(EventMessageContract $message)
    {
        $this->attributes['message'] = serialize(clone $message);
    }

    public function getMessageAttribute($value)
    {
        return unserialize($value);
    }
}
