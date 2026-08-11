<?php

namespace Tests\Feature;

use App\Filament\Resources\AssessmentResource;
use App\Models\Assessment;
use App\Models\AssessmentQuestion;
use App\Models\AssessmentSection;
use App\Models\AssessmentType;
use App\Models\Cadre;
use App\Models\Facility;
use App\Models\User;
use App\Services\CadreMatrixSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EditSectionManageCadresActionTest extends TestCase
{
    use RefreshDatabase;

    private function makeAssessor(): User
    {
        $user = User::factory()->create(['name' => 'Manage Cadres Assessor']);
        Role::firstOrCreate(['name' => 'assessor', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view_any_assessment', 'guard_name' => 'web']);
        $user->givePermissionTo('view_any_assessment');
        $user->assignRole('assessor');
        $this->actingAs($user);

        return $user;
    }

    // ── CadreMatrixSyncService::applyMaternityCadreManagement() — the
    // action's actual logic, unit-tested directly since EditSection's
    // `sectionCode` comes from the URL route, which Livewire's test
    // harness doesn't resolve outside a real HTTP request. ──────────────

    public function test_apply_maternity_cadre_management_creates_a_new_cadre_and_syncs_its_questions(): void
    {
        $section = AssessmentSection::create([
            'assessment_type_id' => AssessmentType::create(['name' => 'Apply Test', 'code' => 'APPLY_CADRE_TEST', 'is_active' => true])->id,
            'name' => 'A. Facility Profile',
            'code' => 'apply_cadre_section_test',
            'section_type' => AssessmentSection::KIND_QUESTION_GROUP,
            'is_scored' => false,
            'order' => 1,
            'is_active' => true,
        ]);

        app(CadreMatrixSyncService::class)->applyMaternityCadreManagement($section, [], 'Anaesthetists');

        $cadre = Cadre::category('emonc')->where('name', 'Anaesthetists')->first();
        $this->assertNotNull($cadre);
        $this->assertTrue($cadre->is_active);
        $this->assertSame(3, AssessmentQuestion::where('question_code', 'like', "EMONC_A_HR_CADRE{$cadre->id}_%")->count());
    }

    public function test_apply_maternity_cadre_management_deactivates_cadres_not_in_the_active_list(): void
    {
        $section = AssessmentSection::create([
            'assessment_type_id' => AssessmentType::create(['name' => 'Apply Test 2', 'code' => 'APPLY_CADRE_TEST_2', 'is_active' => true])->id,
            'name' => 'A. Facility Profile',
            'code' => 'apply_cadre_section_test_2',
            'section_type' => AssessmentSection::KIND_QUESTION_GROUP,
            'is_scored' => false,
            'order' => 1,
            'is_active' => true,
        ]);
        $keep = Cadre::create(['name' => 'Keep Me', 'code' => 'emonc_keep_me_test', 'category' => 'emonc', 'is_active' => true, 'order' => 1]);
        $remove = Cadre::create(['name' => 'Remove Me', 'code' => 'emonc_remove_me_test', 'category' => 'emonc', 'is_active' => true, 'order' => 2]);
        app(CadreMatrixSyncService::class)->syncMaternityHrQuestions($section);
        $this->assertSame(3, AssessmentQuestion::where('question_code', 'like', "EMONC_A_HR_CADRE{$remove->id}_%")->where('is_active', true)->count());

        app(CadreMatrixSyncService::class)->applyMaternityCadreManagement($section, [$keep->id]);

        $this->assertTrue($keep->fresh()->is_active);
        $this->assertFalse($remove->fresh()->is_active);
        $this->assertSame(0, AssessmentQuestion::where('question_code', 'like', "EMONC_A_HR_CADRE{$remove->id}_%")->where('is_active', true)->count());
    }

    // ── EditSection page wiring — the button shows only where it should ──

    public function test_manage_cadres_button_shows_on_the_emonc_facility_context_section(): void
    {
        $assessor = $this->makeAssessor();
        $type = AssessmentType::create(['name' => 'Button Visible Test', 'code' => 'BUTTON_VISIBLE_TEST', 'is_active' => true]);
        AssessmentSection::create([
            'assessment_type_id' => $type->id,
            'name' => 'A. Facility Profile',
            'code' => 'emonc_facility_context',
            'section_type' => AssessmentSection::KIND_QUESTION_GROUP,
            'is_scored' => false,
            'order' => 1,
            'is_active' => true,
        ]);
        $facility = Facility::factory()->create();
        $assessment = Assessment::create([
            'facility_id' => $facility->id,
            'assessment_type_id' => $type->id,
            'assessment_type' => 'baseline',
            'assessment_date' => now(),
            'assessor_id' => $assessor->id,
        ]);

        $url = AssessmentResource::getUrl('edit-section', ['record' => $assessment->id, 'sectionCode' => 'emonc_facility_context']);
        // Checked by the action's unique machine name (embedded in the
        // button's wire:click attribute), not its "Manage Cadres" label —
        // Filament's global-search index can embed every registered
        // action's label site-wide regardless of which page rendered it,
        // making the label alone an unreliable presence/absence signal.
        $this->get($url)->assertOk()->assertSee('manage_emonc_cadres');
    }

    public function test_manage_cadres_button_does_not_show_on_other_sections(): void
    {
        $assessor = $this->makeAssessor();
        $type = AssessmentType::create(['name' => 'Button Hidden Test', 'code' => 'BUTTON_HIDDEN_TEST', 'is_active' => true]);
        AssessmentSection::create([
            'assessment_type_id' => $type->id,
            'name' => 'Some Other Section',
            'code' => 'button_hidden_section_test',
            'section_type' => AssessmentSection::KIND_QUESTION_GROUP,
            'is_scored' => false,
            'order' => 1,
            'is_active' => true,
        ]);
        $facility = Facility::factory()->create();
        $assessment = Assessment::create([
            'facility_id' => $facility->id,
            'assessment_type_id' => $type->id,
            'assessment_type' => 'baseline',
            'assessment_date' => now(),
            'assessor_id' => $assessor->id,
        ]);

        $url = AssessmentResource::getUrl('edit-section', ['record' => $assessment->id, 'sectionCode' => 'button_hidden_section_test']);
        $this->get($url)->assertOk()->assertDontSee('manage_emonc_cadres');
    }
}
