<?php

namespace Softonic\TransactionalEventPublisher\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Softonic\TransactionalEventPublisher\Models\DomainEventsCursor;

class DomainEventsCursorFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = DomainEventsCursor::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition(): array
    {
        return [
            'last_id' => $this->faker->randomNumber(),
        ];
    }
}
