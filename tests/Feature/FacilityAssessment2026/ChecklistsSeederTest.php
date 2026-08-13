<?php

namespace Tests\Feature\FacilityAssessment2026;

use App\Models\AssessmentChecklist;
use App\Models\AssessmentType;
use Database\Seeders\FacilityAssessment2026\ChecklistsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChecklistsSeederTest extends TestCase
{
    use RefreshDatabase;

    private function makeType(): AssessmentType
    {
        return AssessmentType::create(['name' => 'CL Test', 'code' => 'STANDARD_FACILITY_ASSESSMENT_2026', 'is_active' => true]);
    }

    public function test_ort_corner_checklist_has_17_items_with_min_qty(): void
    {
        $this->makeType();
        $this->seed(ChecklistsSeeder::class);

        $checklist = AssessmentChecklist::where('title', 'ORT Corner checklist')->firstOrFail();
        $this->assertCount(17, $checklist->items);
        $this->assertSame(6, $checklist->items->firstWhere('label', 'Clean spoons')?->qty);
        $this->assertNull($checklist->items->firstWhere('label', 'Chlorine for disinfection')?->qty);
    }

    public function test_triage_requirements_has_17_items_no_qty(): void
    {
        $this->makeType();
        $this->seed(ChecklistsSeeder::class);

        $checklist = AssessmentChecklist::where('title', 'Triage requirements')->firstOrFail();
        $this->assertCount(17, $checklist->items);
        $this->assertTrue($checklist->items->every(fn ($i) => $i->qty === null));
    }

    public function test_skills_lab_checklist_has_equipment_and_stationery_groups(): void
    {
        $this->makeType();
        $this->seed(ChecklistsSeeder::class);

        $checklist = AssessmentChecklist::where('title', 'Skills Lab Checklist Requirements')->firstOrFail();
        $equipment = $checklist->items->where('group_label', 'EQUIPMENT');
        $stationery = $checklist->items->where('group_label', 'STATIONERY');

        $this->assertCount(5, $equipment); // 3 manikin/model items + Radiant Warmer + Suction Machine
        $this->assertCount(2, $stationery);
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->makeType();
        $this->seed(ChecklistsSeeder::class);
        $this->seed(ChecklistsSeeder::class);

        $this->assertSame(3, AssessmentChecklist::count());
    }
}
