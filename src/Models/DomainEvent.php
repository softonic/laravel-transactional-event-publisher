<?php

namespace Softonic\TransactionalEventPublisher\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Softonic\TransactionalEventPublisher\Database\Factories\DomainEventFactory;
use Softonic\TransactionalEventPublisher\Interfaces\EventMessageInterface;

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

    public function setMessageAttribute(EventMessageInterface $message): void
    {
        $this->attributes['message'] = serialize(clone $message);
    }

    public function getMessageAttribute($value): EventMessageInterface
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
