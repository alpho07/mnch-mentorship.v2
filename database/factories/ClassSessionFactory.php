<?php

namespace Database\Factories;

use App\Models\ClassSession;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClassSessionFactory extends Factory
{
    protected $model = ClassSession::class;

    public function definition(): array
    {
        return [
            'title'            => 'Session 1',
            'session_number'   => 1,
            'status'           => 'scheduled',
            'attendance_taken' => false,
        ];
    }
}
