<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentType;
use App\Models\Facility;
use App\Models\HumanResourceResponse;
use App\Models\MainCadre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AssessmentExecutiveHumanResourcesTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAssessor(): User
    {
        $user = User::factory()->create(['name' => 'Test Assessor']);
        Role::firstOrCreate(['name' => 'assessor', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view_any_assessment', 'guard_name' => 'web']);
        $user->assignRole('assessor');
        $user->givePermissionTo('view_any_assessment');
        $this->actingAs($user);

        return $user;
    }

    /**
     * human_resource_responses.cadre_id references assessment_cadres (the
     * MainCadre model) — a completely different, unrelated table also
     * happens to exist called `cadres` (used for users.cadre_id
     * elsewhere in the app). Joining against the wrong one silently
     * dropped every cadre row whose id didn't happen to match a `cadres`
     * row, showing only one cadre instead of all of them.
     */
    public function test_executive_dashboard_shows_every_cadre_not_just_the_first(): void
    {
        $assessor = $this->actingAsAssessor();

        $type = AssessmentType::create([
            'name' => 'HR Test Template',
            'code' => 'HR_TEST_TEMPLATE',
            'version' => '1.0',
            'is_active' => true,
        ]);
        $facility = Facility::factory()->create();
        $assessment = Assessment::create([
            'facility_id' => $facility->id,
            'assessment_type_id' => $type->id,
            'assessment_type' => 'baseline',
            'assessment_date' => now(),
            'status' => 'completed',
            'assessor_id' => $assessor->id,
            'assessor_name' => $assessor->name,
        ]);

        $neonatologist = MainCadre::create(['name' => 'Neonatologist', 'order' => 1, 'is_active' => true]);
        $midwife = MainCadre::create(['name' => 'Midwives', 'order' => 2, 'is_active' => true]);
        $clinicalOfficer = MainCadre::create(['name' => 'Clinical Officer', 'order' => 3, 'is_active' => true]);

        foreach ([$neonatologist, $midwife, $clinicalOfficer] as $cadre) {
            HumanResourceResponse::create([
                'assessment_id' => $assessment->id,
                'cadre_id' => $cadre->id,
                'total_in_facility' => 5,
                'etat_plus' => 1,
                'comprehensive_newborn_care' => 1,
                'imnci' => 0,
                'type_1_diabetes' => 0,
                'essential_newborn_care' => 0,
            ]);
        }

        $response = $this->get(route('assessment.executive', $assessment));

        $response->assertOk();
        $response->assertSee('Neonatologist');
        $response->assertSee('Midwives');
        $response->assertSee('Clinical Officer');
    }
}
