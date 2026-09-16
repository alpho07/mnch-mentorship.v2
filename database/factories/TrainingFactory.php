<?php

namespace Database\Factories;

use App\Models\Training;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Training>
 */
class TrainingFactory extends Factory
{
    protected $model = Training::class;

    public function definition(): array
    {
        return [
            'title'       => fake()->sentence(4),
            'type'        => 'global_training',
            'start_date'  => fake()->dateTimeBetween('-1 month', 'now')->format('Y-m-d'),
            'end_date'    => fake()->dateTimeBetween('now', '+3 months')->format('Y-m-d'),
            'facility_id' => null, // nullable after migration 2025_07_26_034726
        ];
    }

    public function facilityMentorship(): static
    {
        return $this->state(fn(array $attributes) => [
            'type' => 'facility_mentorship',
        ]);
    }
}
