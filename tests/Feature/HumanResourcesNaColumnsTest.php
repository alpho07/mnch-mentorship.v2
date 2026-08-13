<?php

namespace Tests\Feature;

use App\Filament\Resources\AssessmentResource;
use App\Filament\Resources\AssessmentResource\Pages\EditHumanResources;
use App\Models\Assessment;
use App\Models\AssessmentSection;
use App\Models\AssessmentType;
use App\Models\Facility;
use App\Models\HumanResourceResponse;
use App\Models\MainCadre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HumanResourcesNaColumnsTest extends TestCase
{
    use RefreshDatabase;

    private function makeAssessor(): User
    {
        $user = User::factory()->create(['name' => 'HR NA Assessor']);
        Role::firstOrCreate(['name' => 'assessor', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view_any_assessment', 'guard_name' => 'web']);
        $user->givePermissionTo('view_any_assessment');
        $user->assignRole('assessor');
        $this->actingAs($user);

        return $user;
    }

    public function test_na_training_column_is_not_rendered_and_saves_as_null(): void
    {
        $assessor = $this->makeAssessor();
        $type = AssessmentType::create(['name' => 'HR NA Test', 'code' => 'HR_NA_TEST', 'is_active' => true]);
        AssessmentSection::create([
            'assessment_type_id' => $type->id, 'name' => 'Human Resources', 'code' => 'human_resources',
            'section_type' => AssessmentSection::KIND_HUMAN_RESOURCES, 'order' => 1, 'is_active' => true,
        ]);
        $cadre = MainCadre::create([
            'assessment_type_id' => $type->id,
            'name' => 'Maternity theatre anaesthetists',
            'code' => 'maternity_theatre_anaesthetists',
            'is_active' => true,
            'order' => 1,
            'na_training_columns' => ['comprehensive_newborn_care', 'imnci', 'type_1_diabetes'],
        ]);
        $facility = Facility::factory()->create();
        $assessment = Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type->id,
            'assessment_type' => 'baseline', 'assessment_date' => now(), 'assessor_id' => $assessor->id,
        ]);

        $url = AssessmentResource::getUrl('edit-human-resources', ['record' => $assessment->id]);
        $rendered = $this->get($url);
        $rendered->assertOk();
        $rendered->assertDontSee("hr_{$cadre->id}_comprehensive_newborn_care");

        Livewire::test(EditHumanResources::class, ['record' => $assessment->id])
            ->fillForm([
                "hr_{$cadre->id}_total_in_facility" => 3,
                "hr_{$cadre->id}_etat_plus" => 2,
                "hr_{$cadre->id}_essential_newborn_care" => 1,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $response = HumanResourceResponse::where('assessment_id', $assessment->id)->where('cadre_id', $cadre->id)->first();

        $this->assertSame(3, $response->total_in_facility);
        $this->assertSame(2, $response->etat_plus);
        $this->assertNull($response->comprehensive_newborn_care);
        $this->assertNull($response->imnci);
        $this->assertNull($response->type_1_diabetes);
    }
}
