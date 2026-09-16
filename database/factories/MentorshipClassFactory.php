<?php

namespace Database\Factories;

use App\Models\MentorshipClass;
use Illuminate\Database\Eloquent\Factories\Factory;

class MentorshipClassFactory extends Factory
{
    protected $model = MentorshipClass::class;

    public function definition(): array
    {
        return [
            'name'       => 'Class ' . $this->faker->word(),
            'status'     => 'draft',
            'start_date' => now()->toDateString(),
            'end_date'   => now()->addMonths(2)->toDateString(),
        ];
    }
}
