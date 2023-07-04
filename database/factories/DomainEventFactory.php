<?php

namespace Softonic\TransactionalEventPublisher\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Softonic\TransactionalEventPublisher\CustomEventMessage;
use Softonic\TransactionalEventPublisher\Models\DomainEvent;
use Softonic\TransactionalEventPublisher\TestModel;

class DomainEventFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = DomainEvent::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition(): array
    {
        return [
            'message' => new CustomEventMessage(new TestModel(), 'testEvent')
        ];
    }
}
