<?php

namespace Tests\Feature;

use App\Filament\Resources\AssessmentResource\Pages\CreateAssessment;
use App\Models\AssessmentType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CreateAssessmentTemplatePreloadTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A searchable Filament relationship Select with no ->preload() shows
     * zero options until the user types (Select::options() returns null
     * in that state — see Select.php's relationship() helper). With only
     * one active AssessmentType template in the system, that made the
     * "Assessment" field on Create look empty/unusable even though the
     * template exists and is reusable — hence ->preload() being required.
     */
    public function test_active_assessment_type_is_visible_without_typing_a_search_term(): void
    {
        $user = User::factory()->create();
        Permission::firstOrCreate(['name' => 'view_any_assessment', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'create_assessment', 'guard_name' => 'web']);
        $user->givePermissionTo(['view_any_assessment', 'create_assessment']);
        $this->actingAs($user);

        // The standard template is seeded by a data migration
        // (backfill_standard_facility_assessment_type), so it already
        // exists on a fresh RefreshDatabase run.
        $type = AssessmentType::where('code', 'STANDARD_FACILITY_ASSESSMENT')->firstOrFail();

        $inactiveType = AssessmentType::create([
            'name' => 'Retired Template',
            'code' => 'RETIRED_TEMPLATE',
            'is_active' => false,
        ]);

        $component = Livewire::test(CreateAssessment::class);

        $options = $component->instance()->form->getComponent('data.assessment_type_id')->getOptions();

        $this->assertSame([$type->id => $type->name], $options);
        $this->assertArrayNotHasKey($inactiveType->id, $options);
    }
}
