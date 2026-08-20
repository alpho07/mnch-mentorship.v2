<?php

namespace Tests\Feature;

use App\Models\ProgramModule;
use App\Models\ProgramModuleContent;
use Database\Seeders\EmoncBatchAContentSeeder;
use Database\Seeders\EmoncLcgModule2SectionContentSeeder;
use Database\Seeders\EmoncProgramSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EmoncLcgModule2SectionContentSeederTest extends TestCase
{
    use RefreshDatabase;

    private function seedBaseline(): ProgramModule
    {
        Storage::fake('public');

        $this->seed(EmoncProgramSeeder::class);
        $this->seed(EmoncBatchAContentSeeder::class);

        return ProgramModule::where('name', 'like', '%Labour Care Guide%')
            ->whereNull('parent_id')
            ->firstOrFail();
    }

    public function test_seeder_replaces_grace_scenario_with_seven_section_practice_rows(): void
    {
        $module = $this->seedBaseline();

        $this->assertTrue(
            ProgramModuleContent::where('program_module_id', $module->id)
                ->where('title', 'LCG Simulation Drill — Case Scenario')
                ->exists()
        );

        $this->seed(EmoncLcgModule2SectionContentSeeder::class);

        $this->assertFalse(
            ProgramModuleContent::where('program_module_id', $module->id)
                ->whereIn('title', [
                    'LCG Simulation Drill — Case Scenario',
                    'LCG Simulation Drill — Scenario Progression',
                ])
                ->exists()
        );

        $practiceRows = ProgramModuleContent::where('program_module_id', $module->id)
            ->where('type', 'case_scenario')
            ->orderBy('order_sequence')
            ->get();

        $this->assertCount(6, $practiceRows);
        $this->assertSame('Section 1 — Practice', $practiceRows[0]->title);
        $this->assertSame('Sections 6 & 7 — Practice', $practiceRows[5]->title);
        $this->assertStringContainsString('Mary Jane', $practiceRows[0]->content);
    }

    public function test_seeder_creates_eight_mentor_materials_rows_with_images(): void
    {
        $module = $this->seedBaseline();

        $this->seed(EmoncLcgModule2SectionContentSeeder::class);

        $mentorRows = ProgramModuleContent::where('program_module_id', $module->id)
            ->where('type', 'mentor_materials')
            ->orderBy('order_sequence')
            ->get();

        $this->assertCount(8, $mentorRows);
        $this->assertSame('Section 1 — Admission: Identifying Information and Labour Characteristics', $mentorRows[0]->title);
        $this->assertSame('Sample Completed LCG (Reference)', $mentorRows[7]->title);
        $this->assertStringContainsString('program-module-content/emonc-module-2-lcg/section-1-fig-a.png', $mentorRows[0]->content);

        Storage::disk('public')->assertExists('program-module-content/emonc-module-2-lcg/section-1-fig-a.png');
        Storage::disk('public')->assertExists('program-module-content/emonc-module-2-lcg/appendix-sample-completed-lcg.png');
    }

    public function test_content_type_audience_is_correct(): void
    {
        $module = $this->seedBaseline();
        $this->seed(EmoncLcgModule2SectionContentSeeder::class);

        $practiceRow = ProgramModuleContent::where('program_module_id', $module->id)
            ->where('type', 'case_scenario')
            ->firstOrFail();
        $mentorRow = ProgramModuleContent::where('program_module_id', $module->id)
            ->where('type', 'mentor_materials')
            ->firstOrFail();

        $this->assertSame('mentee', $practiceRow->audience());
        $this->assertSame('mentor', $mentorRow->audience());
    }

    public function test_seeder_is_idempotent(): void
    {
        $module = $this->seedBaseline();

        $this->seed(EmoncLcgModule2SectionContentSeeder::class);
        $countBefore = ProgramModuleContent::where('program_module_id', $module->id)->count();

        $this->seed(EmoncLcgModule2SectionContentSeeder::class);
        $countAfter = ProgramModuleContent::where('program_module_id', $module->id)->count();

        $this->assertSame($countBefore, $countAfter);
    }
}
