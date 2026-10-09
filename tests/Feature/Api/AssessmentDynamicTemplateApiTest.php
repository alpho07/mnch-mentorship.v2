<?php

namespace Tests\Feature\Api;

use App\Models\Assessment;
use App\Models\AssessmentChecklist;
use App\Models\AssessmentChecklistItem;
use App\Models\AssessmentQuestion;
use App\Models\AssessmentSection;
use App\Models\AssessmentType;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Dynamic-template behaviour the mobile app relies on: conditional sections
 * in the submit gate, checklists in the schema, team search while creating,
 * and the executive report endpoints.
 */
class AssessmentDynamicTemplateApiTest extends TestCase
{
    use RefreshDatabase;

    private AssessmentType $template;

    private User $assessor;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'assessor', 'guard_name' => 'web']);
        foreach (['view_assessment', 'update_assessment', 'create_assessment'] as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $this->template = AssessmentType::create(['name' => 'Dyn 2026', 'code' => 'DYN_2026', 'version' => '1', 'is_active' => true]);
        $this->assessor = $this->user('Ada Assessor');
    }

    private function user(string $name): User
    {
        $user = User::factory()->create(['name' => $name, 'email' => fake()->unique()->safeEmail()]);
        $user->assignRole('assessor');
        $user->givePermissionTo(['view_assessment', 'update_assessment', 'create_assessment']);

        return $user;
    }

    private function as(User $user): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer '.$user->createToken('t')->plainTextToken);
    }

    private function section(string $code, int $order, ?array $conditions = null): AssessmentSection
    {
        return AssessmentSection::create([
            'assessment_type_id' => $this->template->id, 'name' => ucfirst($code), 'code' => $code,
            'section_type' => 'dynamic_questions', 'is_scored' => true, 'order' => $order, 'is_active' => true,
            'display_conditions' => $conditions,
        ]);
    }

    private function question(AssessmentSection $section, string $code, array $extra = []): AssessmentQuestion
    {
        return AssessmentQuestion::create(array_merge([
            'assessment_section_id' => $section->id, 'question_code' => $code, 'question_text' => 'Q?',
            'question_type' => 'yes_no', 'order' => 1, 'is_active' => true, 'is_scored' => true,
            'scoring_map' => ['Yes' => 1, 'No' => 0],
        ], $extra));
    }

    private function assessment(array $progress): Assessment
    {
        return Assessment::create([
            'facility_id' => Facility::factory()->create()->id,
            'assessment_type_id' => $this->template->id,
            'assessment_type' => 'baseline', 'round' => 'baseline',
            'assessment_date' => now()->toDateString(),
            'assessor_id' => $this->assessor->id, 'assessor_name' => $this->assessor->name,
            'assessor_contact' => $this->assessor->email, 'created_by' => $this->assessor->id,
            'status' => 'in_progress', 'section_progress' => $progress,
        ]);
    }

    public function test_hidden_conditional_section_does_not_block_submit(): void
    {
        $main = $this->section('main', 1);
        $this->question($main, 'HAS_ICU');
        $icu = $this->section('icu', 2, ['question_code' => 'HAS_ICU', 'operator' => 'equals', 'value' => 'Yes']);
        $this->question($icu, 'ICU_BEDS');

        $assessment = $this->assessment(['main' => true, 'icu' => false]);

        // "No ICU" → the ICU section does not apply, so main alone is enough.
        $this->as($this->assessor)->postJson("/api/v1/assessments/{$assessment->id}/responses", [
            'section_code' => 'main', 'responses' => ['HAS_ICU' => 'No'],
        ])->assertOk();

        $this->as($this->assessor)->postJson("/api/v1/assessments/{$assessment->id}/submit")
            ->assertOk()->assertJsonPath('assessment.status', 'completed');
    }

    public function test_visible_conditional_section_still_blocks_submit(): void
    {
        $main = $this->section('main', 1);
        $this->question($main, 'HAS_ICU');
        $icu = $this->section('icu', 2, ['question_code' => 'HAS_ICU', 'operator' => 'equals', 'value' => 'Yes']);
        $this->question($icu, 'ICU_BEDS');

        $assessment = $this->assessment(['main' => true, 'icu' => false]);

        $this->as($this->assessor)->postJson("/api/v1/assessments/{$assessment->id}/responses", [
            'section_code' => 'main', 'responses' => ['HAS_ICU' => 'Yes'],
        ])->assertOk();

        $this->as($this->assessor)->postJson("/api/v1/assessments/{$assessment->id}/submit")
            ->assertStatus(422)->assertJsonPath('incomplete_sections.0', 'icu');
    }

    public function test_schema_includes_question_checklists_and_section_conditions(): void
    {
        $conditions = ['question_code' => 'HAS_ICU', 'operator' => 'equals', 'value' => 'Yes'];
        $section = $this->section('icu', 1, $conditions);
        $checklist = AssessmentChecklist::create(['assessment_type_id' => $this->template->id, 'title' => 'ICU equipment']);
        AssessmentChecklistItem::create(['assessment_checklist_id' => $checklist->id, 'group_label' => 'Monitors', 'label' => 'Pulse oximeter', 'qty' => 2, 'order' => 1]);
        $this->question($section, 'ICU_EQ', ['checklist_id' => $checklist->id, 'indent_level' => 1]);

        $this->as($this->assessor)->getJson("/api/v1/assessment-templates/{$this->template->id}")
            ->assertOk()
            ->assertJsonPath('data.0.display_conditions.value', 'Yes')
            ->assertJsonPath('data.0.questions.0.indent_level', 1)
            ->assertJsonPath('data.0.questions.0.checklist.title', 'ICU equipment')
            ->assertJsonPath('data.0.questions.0.checklist.items.0.label', 'Pulse oximeter')
            ->assertJsonPath('data.0.questions.0.checklist.items.0.qty', 2);
    }

    public function test_team_search_finds_people_but_not_the_creator(): void
    {
        $this->user('Grace Mentor');

        $this->as($this->assessor)->getJson('/api/v1/assessments/team/search?q=Grace')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Grace Mentor');

        $this->as($this->assessor)->getJson('/api/v1/assessments/team/search?q=Ada')
            ->assertOk()->assertJsonCount(0, 'data');

        $this->as($this->assessor)->getJson('/api/v1/assessments/team/search?q=G')
            ->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_executive_pdf_requires_a_completed_assessment(): void
    {
        $assessment = $this->assessment([]);

        $this->as($this->assessor)->getJson("/api/v1/assessments/{$assessment->id}/report/executive/pdf")
            ->assertStatus(422);
    }

    public function test_executive_report_requires_authentication(): void
    {
        $assessment = $this->assessment([]);

        $this->getJson("/api/v1/assessments/{$assessment->id}/report/executive")->assertUnauthorized();
    }

    public function test_executive_report_returns_the_dashboard_payload(): void
    {
        $assessment = $this->assessment([]);

        $this->as($this->assessor)->getJson("/api/v1/assessments/{$assessment->id}/report/executive")
            ->assertOk()
            ->assertJsonStructure(['assessment', 'section_scores', 'insights', 'human_resources', 'commodities', 'data_quality']);
    }
}
