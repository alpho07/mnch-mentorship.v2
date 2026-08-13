<?php

namespace Tests\Feature;

use App\Filament\Resources\AssessmentTypeResource\Pages\EditAssessmentType;
use App\Models\AssessmentType;
use App\Models\User;
use Database\Seeders\FacilityAssessment2026\FacilityAssessment2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Regression test for a save-crash on AssessmentTypeResource's edit form.
 * The 2026 seeder used to nest its {{token}} interpolation parameters
 * inside the `metadata` column (metadata.parameters), which is also
 * edited in full by this form's `KeyValue::make('metadata')` field.
 * Filament's KeyValue component requires every value in that column to
 * be a flat string, so saving any AssessmentType whose metadata held a
 * nested array threw a TypeError. The fix moved interpolation parameters
 * to their own `template_parameters` column so `metadata` stays flat.
 */
class AssessmentTypeEditFormTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $user = User::factory()->create(['name' => 'Admin']);
        foreach (['view_any_assessment::type', 'view_assessment::type', 'update_assessment::type'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
        $user->givePermissionTo(['view_any_assessment::type', 'view_assessment::type', 'update_assessment::type']);
        $this->actingAs($user);

        return $user;
    }

    public function test_saving_the_2026_assessment_type_edit_form_does_not_crash(): void
    {
        $this->actingAsAdmin();
        $this->seed(FacilityAssessment2026Seeder::class);

        $type = AssessmentType::where('code', 'STANDARD_FACILITY_ASSESSMENT_2026')->firstOrFail();

        Livewire::test(EditAssessmentType::class, ['record' => $type->id])
            ->fillForm(['name' => 'Standard Facility Readiness Assessment (2026 Revised)'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Standard Facility Readiness Assessment (2026 Revised)', $type->fresh()->name);
    }

    public function test_saving_does_not_disturb_the_template_parameters_column(): void
    {
        $this->actingAsAdmin();
        $this->seed(FacilityAssessment2026Seeder::class);

        $type = AssessmentType::where('code', 'STANDARD_FACILITY_ASSESSMENT_2026')->firstOrFail();

        Livewire::test(EditAssessmentType::class, ['record' => $type->id])
            ->fillForm(['version' => '2026.1'])
            ->call('save')
            ->assertHasNoFormErrors();

        $fresh = $type->fresh();
        $this->assertSame('2026.1', $fresh->version);
        $this->assertSame('Neonates 7–28 days', $fresh->template_parameters['quality_of_care_timeline'] ?? null);
        $this->assertArrayNotHasKey('parameters', $fresh->metadata ?? []);
    }
}
