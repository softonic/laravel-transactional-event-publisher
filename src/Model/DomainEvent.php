<?php

namespace Softonic\TransactionalEventPublisher\Model;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Softonic\TransactionalEventPublisher\Contracts\EventMessageContract;
use Softonic\TransactionalEventPublisher\Database\Factories\DomainEventFactory;

class DomainEvent extends Model
{
    use HasFactory;

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

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): Factory
    {
        return DomainEventFactory::new();
    }
}
