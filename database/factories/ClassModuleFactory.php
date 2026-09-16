<?php

namespace Database\Factories;

use App\Models\ClassModule;
use App\Models\ProgramModule;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClassModuleFactory extends Factory
{
    protected $model = ClassModule::class;

    public function definition(): array
    {
        return [
            'program_module_id'    => ProgramModule::factory(),
            'status'               => 'not_started',
            'order_sequence'       => 1,
            'requires_assessment'  => false,
            'attendance_link_active' => false,
        ];
    }
}
