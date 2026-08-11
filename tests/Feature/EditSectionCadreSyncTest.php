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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EditSectionCadreSyncTest extends TestCase
{
    use RefreshDatabase;

    private function makeAssessor(): User
    {
        $user = User::factory()->create(['name' => 'Cadre Sync Assessor']);
        Role::firstOrCreate(['name' => 'assessor', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view_any_assessment', 'guard_name' => 'web']);
        $user->givePermissionTo('view_any_assessment');
        $user->assignRole('assessor');
        $this->actingAs($user);

        return $user;
    }

    public function test_visiting_the_emonc_facility_context_section_materializes_hr_questions_for_active_cadres(): void
    {
        $assessor = $this->makeAssessor();
        Cadre::create(['name' => 'Live Sync Nurses', 'code' => 'live_sync_nurses', 'category' => 'emonc', 'is_active' => true, 'order' => 1]);

        $type = AssessmentType::create(['name' => 'Cadre Sync Page Test', 'code' => 'CADRE_SYNC_PAGE_TEST', 'is_active' => true]);
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

        $this->assertFalse(AssessmentQuestion::where('question_code', 'like', 'EMONC_A_HR_CADRE%')->exists());

        $url = AssessmentResource::getUrl('edit-section', ['record' => $assessment->id, 'sectionCode' => 'emonc_facility_context']);
        $response = $this->get($url);
        $response->assertOk();

        $synced = AssessmentQuestion::where('question_code', 'like', 'EMONC_A_HR_CADRE%')->get();
        $this->assertCount(3, $synced);
        $this->assertTrue($synced->every(fn ($q) => $q->group === 'Human Resources in Maternity Unit|Cadre|Live Sync Nurses'));
    }

    public function test_other_question_group_sections_are_not_affected_by_the_sync(): void
    {
        $assessor = $this->makeAssessor();
        $type = AssessmentType::create(['name' => 'Non EmONC Sync Test', 'code' => 'NON_EMONC_SYNC_TEST', 'is_active' => true]);
        AssessmentSection::create([
            'assessment_type_id' => $type->id,
            'name' => 'Some Other Section',
            'code' => 'some_other_section_test',
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

        $url = AssessmentResource::getUrl('edit-section', ['record' => $assessment->id, 'sectionCode' => 'some_other_section_test']);
        $this->get($url)->assertOk();

        $this->assertFalse(AssessmentQuestion::where('question_code', 'like', 'EMONC_A_HR_CADRE%')->exists());
    }
}
