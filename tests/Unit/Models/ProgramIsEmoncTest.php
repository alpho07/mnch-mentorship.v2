<?php

namespace Tests\Unit\Models;

use App\Models\Program;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgramIsEmoncTest extends TestCase
{
    use RefreshDatabase;

    public function test_maternal_emonc_program_name_is_emonc(): void
    {
        $program = Program::factory()->create(['name' => 'Maternal Health (EmONC)']);

        $this->assertTrue($program->isEmonc());
    }

    public function test_newborn_care_program_name_is_not_emonc(): void
    {
        $program = Program::factory()->create(['name' => 'Newborn Care']);

        $this->assertFalse($program->isEmonc());
    }

    public function test_infant_and_child_care_program_name_is_not_emonc(): void
    {
        $program = Program::factory()->create(['name' => 'Infant and Child Care']);

        $this->assertFalse($program->isEmonc());
    }
}
