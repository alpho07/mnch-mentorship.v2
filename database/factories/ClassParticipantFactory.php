<?php

namespace Database\Factories;

use App\Models\ClassParticipant;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClassParticipantFactory extends Factory
{
    protected $model = ClassParticipant::class;

    public function definition(): array
    {
        return [
            'status'      => 'enrolled',
            'enrolled_at' => now(),
        ];
    }
}
