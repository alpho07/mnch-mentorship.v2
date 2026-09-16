<?php
namespace Database\Factories;

use App\Models\Program;
use App\Models\ProgramModule;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProgramModuleFactory extends Factory
{
    protected $model = ProgramModule::class;

    public function definition(): array
    {
        return [
            'program_id'     => Program::factory(),
            'name'           => fake()->words(4, true),
            'description'    => fake()->sentence(),
            'order_sequence' => fake()->numberBetween(1, 10),
            'duration_weeks' => fake()->numberBetween(1, 8),
            'is_active'      => true,
        ];
    }
}
