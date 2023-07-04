<?php

namespace Softonic\TransactionalEventPublisher\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Softonic\TransactionalEventPublisher\Database\Factories\DomainEventsCursorFactory;

class DomainEventsCursor extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['last_id'];

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'domain_events_cursor';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'last_id';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): Factory
    {
        return DomainEventsCursorFactory::new();
    }
}
