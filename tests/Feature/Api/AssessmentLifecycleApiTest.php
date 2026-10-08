<?php

namespace Tests\Feature\Api;

use App\Models\Assessment;
use App\Models\AssessmentQuestion;
use App\Models\AssessmentSection;
use App\Models\AssessmentType;
use App\Models\Facility;
use App\Models\MainCadre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Template-aware create / progress / responses / close / reopen / team via the
 * mobile API — and that assessments on an older (or retired) template keep working.
 */
class AssessmentLifecycleApiTest extends TestCase
{
    use RefreshDatabase;

    private AssessmentType $old;   // the "default" legacy template

    private AssessmentType $new;   // a newer template reusing the same section code

    private User $assessor;

    private Facility $facility;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['assessor', 'admin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
        foreach (['view_assessment', 'update_assessment', 'create_assessment', 'delete_assessment'] as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        // The migrations already seed the standard template; reuse it as the "default".
        $this->old = AssessmentType::firstOrCreate(
            ['code' => 'STANDARD_FACILITY_ASSESSMENT'],
            ['name' => 'Standard', 'version' => '1.0', 'is_active' => true]
        );
        $this->old->sections()->delete();
        $this->new = AssessmentType::create(['name' => 'Readiness 2026', 'code' => 'READINESS_2026', 'version' => '2026', 'is_active' => true]);

        // Same section code in both templates (codes are only unique per template).
        $this->section($this->old, 'infrastructure', 'OLD_Q1');
        $this->section($this->new, 'infrastructure', 'NEW_Q1');
        $this->section($this->new, 'skills', 'NEW_Q2');

        $this->assessor = $this->user('assessor');
        $this->facility = Facility::factory()->create();
    }

    private function section(AssessmentType $type, string $code, string $questionCode): AssessmentSection
    {
        $section = AssessmentSection::create([
            'assessment_type_id' => $type->id, 'name' => ucfirst($code), 'code' => $code,
            'section_type' => 'dynamic_questions', 'is_scored' => true, 'order' => 1, 'is_active' => true,
        ]);
        AssessmentQuestion::create([
            'assessment_section_id' => $section->id, 'question_code' => $questionCode, 'question_text' => 'Q?',
            'question_type' => 'yes_no', 'order' => 1, 'is_active' => true, 'is_scored' => true,
            'scoring_map' => ['Yes' => 1, 'No' => 0],
        ]);

        return $section;
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['name' => 'User '.fake()->unique()->numerify('###'), 'email' => fake()->unique()->safeEmail()]);
        $user->assignRole($role);
        $user->givePermissionTo(['view_assessment', 'update_assessment', 'create_assessment', 'delete_assessment']);

        return $user;
    }

    private function as(User $user): self
    {
        // The auth guard caches the first resolved user for the whole test;
        // forget it so each request really runs as $user.
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer '.$user->createToken('t')->plainTextToken);
    }

    private function create(array $payload = [], ?User $as = null)
    {
        return $this->as($as ?? $this->assessor)->postJson('/api/v1/assessments', array_merge([
            'facility_id' => $this->facility->id,
            'assessment_type_id' => $this->new->id,
            'round' => 'baseline',
            'assessment_date' => now()->toDateString(),
        ], $payload));
    }

    // ── templates ─────────────────────────────────────────────────────────

    public function test_templates_list_shows_active_templates_rounds_and_default(): void
    {
        AssessmentType::create(['name' => 'Hidden', 'code' => 'HIDDEN', 'version' => '1', 'is_active' => false]);

        $this->as($this->assessor)->getJson('/api/v1/assessment-templates')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('default_template_id', $this->old->id)
            ->assertJsonPath('rounds.3.value', 'other');
    }

    // ── create ────────────────────────────────────────────────────────────

    public function test_create_stores_template_round_and_only_that_templates_sections(): void
    {
        $response = $this->create()->assertCreated()
            ->assertJsonPath('assessment.assessment_type_id', $this->new->id)
            ->assertJsonPath('assessment.template.code', 'READINESS_2026')
            ->assertJsonPath('assessment.round', 'baseline')
            ->assertJsonPath('assessment.is_locked', false)
            ->assertJsonPath('assessment.can_edit', true);

        $assessment = Assessment::findOrFail($response->json('assessment.id'));
        $this->assertEqualsCanonicalizing(['infrastructure', 'skills'], array_keys($assessment->section_progress));
        $this->assertSame($this->assessor->id, $assessment->created_by);
        $this->assertTrue($assessment->isTeamLead($this->assessor->id));
    }

    public function test_legacy_client_without_a_template_gets_the_default_template(): void
    {
        $this->as($this->assessor)->postJson('/api/v1/assessments', [
            'facility_id' => $this->facility->id,
            'assessment_type' => 'midline',
            'assessment_date' => now()->toDateString(),
        ])->assertCreated()
            ->assertJsonPath('assessment.assessment_type_id', $this->old->id)
            ->assertJsonPath('assessment.round', 'midline');
    }

    public function test_past_dates_are_still_rejected(): void
    {
        // Existing product rule (see AssessmentDateValidationTest) — kept as is.
        $this->create(['assessment_date' => now()->subDays(2)->toDateString()])->assertStatus(422);
    }

    public function test_duplicate_facility_template_round_returns_409_with_the_existing_assessment(): void
    {
        $first = $this->create()->assertCreated();

        $this->create()->assertStatus(409)
            ->assertJsonPath('assessment.id', $first->json('assessment.id'));

        // A different round or template is fine.
        $this->create(['round' => 'midline'])->assertCreated();
        $this->create(['assessment_type_id' => $this->old->id])->assertCreated();
    }

    public function test_other_round_needs_a_label_and_labels_distinguish_duplicates(): void
    {
        $this->create(['round' => 'other'])->assertStatus(422);
        $this->create(['round' => 'other', 'round_label' => 'Post-COVID'])->assertCreated();
        $this->create(['round' => 'other', 'round_label' => 'Post-COVID'])->assertStatus(409);
        $this->create(['round' => 'other', 'round_label' => 'Follow-up'])->assertCreated();
    }

    public function test_inactive_templates_cannot_be_started(): void
    {
        $this->new->update(['is_active' => false]);

        $this->create()->assertStatus(422);
    }

    public function test_create_can_invite_team_members(): void
    {
        $member = $this->user('assessor');

        $id = $this->create(['member_ids' => [$member->id]])->assertCreated()->json('assessment.id');

        $this->assertTrue(Assessment::find($id)->isTeamMember($member->id));
    }

    // ── template scoping of progress + responses ──────────────────────────

    public function test_responses_and_progress_resolve_codes_within_the_assessments_own_template(): void
    {
        $oldAssessment = Assessment::findOrFail($this->create(['assessment_type_id' => $this->old->id])->json('assessment.id'));

        // 'infrastructure' exists in both templates — the OLD one's question must be used.
        $this->as($this->assessor)->postJson("/api/v1/assessments/{$oldAssessment->id}/responses", [
            'section_code' => 'infrastructure',
            'responses' => ['OLD_Q1' => 'Yes'],
        ])->assertOk()->assertJsonPath('saved', 1);

        // A question that only exists on the other template is skipped, not saved.
        $this->as($this->assessor)->postJson("/api/v1/assessments/{$oldAssessment->id}/responses", [
            'section_code' => 'infrastructure',
            'responses' => ['NEW_Q1' => 'Yes'],
        ])->assertOk()->assertJsonPath('saved', 0)->assertJsonPath('skipped.0', 'NEW_Q1');

        // A section that belongs only to the other template is rejected.
        $this->as($this->assessor)->putJson("/api/v1/assessments/{$oldAssessment->id}/sections/skills/progress", ['done' => true])
            ->assertStatus(422);

        $this->as($this->assessor)->getJson("/api/v1/assessments/{$oldAssessment->id}/responses")
            ->assertOk()->assertJsonPath('responses.OLD_Q1', 'Yes')->assertJsonCount(1, 'sectionProgress');
    }

    // ── close / reopen ────────────────────────────────────────────────────

    private function readyToSubmit(): Assessment
    {
        $assessment = Assessment::findOrFail($this->create(['assessment_type_id' => $this->old->id])->json('assessment.id'));
        $this->as($this->assessor)->postJson("/api/v1/assessments/{$assessment->id}/responses", [
            'section_code' => 'infrastructure', 'responses' => ['OLD_Q1' => 'Yes'],
        ])->assertOk();

        return $assessment;
    }

    public function test_submit_is_blocked_until_the_templates_sections_are_done(): void
    {
        $assessment = $this->readyToSubmit();

        $this->as($this->assessor)->postJson("/api/v1/assessments/{$assessment->id}/submit")
            ->assertStatus(422)->assertJsonPath('incomplete_sections.0', 'infrastructure');
    }

    public function test_submit_completes_and_locks_then_edits_are_blocked(): void
    {
        $assessment = $this->readyToSubmit();
        $this->as($this->assessor)->putJson("/api/v1/assessments/{$assessment->id}/sections/infrastructure/progress", ['done' => true])->assertOk();

        $this->as($this->assessor)->postJson("/api/v1/assessments/{$assessment->id}/submit")
            ->assertOk()
            ->assertJsonPath('assessment.status', 'completed')
            ->assertJsonPath('assessment.is_locked', true)
            ->assertJsonPath('assessment.can_edit', false);

        // Only the assessment's own template's sections get a score row.
        $this->assertSame(1, $assessment->sectionScores()->count());

        $this->as($this->assessor)->postJson("/api/v1/assessments/{$assessment->id}/responses", [
            'section_code' => 'infrastructure', 'responses' => ['OLD_Q1' => 'No'],
        ])->assertStatus(403)->assertJsonPath('code', 'assessment_closed');
        $this->as($this->assessor)->putJson("/api/v1/assessments/{$assessment->id}", ['round' => 'midline'])->assertStatus(403);
        $this->as($this->assessor)->deleteJson("/api/v1/assessments/{$assessment->id}")->assertStatus(403);
        $this->as($this->assessor)->postJson("/api/v1/assessments/{$assessment->id}/submit")->assertStatus(409);
    }

    public function test_only_an_admin_can_reopen_and_editing_resumes(): void
    {
        $assessment = $this->readyToSubmit();
        $this->as($this->assessor)->putJson("/api/v1/assessments/{$assessment->id}/sections/infrastructure/progress", ['done' => true]);
        $this->as($this->assessor)->postJson("/api/v1/assessments/{$assessment->id}/submit")->assertOk();

        $this->as($this->assessor)->postJson("/api/v1/assessments/{$assessment->id}/reopen")->assertStatus(403);

        $admin = $this->user('admin');
        $this->as($admin)->postJson("/api/v1/assessments/{$assessment->id}/reopen")
            ->assertOk()
            ->assertJsonPath('assessment.status', 'in_progress')
            ->assertJsonPath('assessment.is_locked', false)
            ->assertJsonPath('assessment.can_edit', true);

        $this->as($this->assessor)->postJson("/api/v1/assessments/{$assessment->id}/responses", [
            'section_code' => 'infrastructure', 'responses' => ['OLD_Q1' => 'No'],
        ])->assertOk();

        // Reopening something that isn't closed is a conflict.
        $this->as($admin)->postJson("/api/v1/assessments/{$assessment->id}/reopen")->assertStatus(409);
    }

    public function test_can_reopen_flag_is_only_true_for_admins_on_closed_assessments(): void
    {
        $assessment = $this->readyToSubmit();
        $this->as($this->assessor)->putJson("/api/v1/assessments/{$assessment->id}/sections/infrastructure/progress", ['done' => true]);
        $this->as($this->assessor)->postJson("/api/v1/assessments/{$assessment->id}/submit")->assertOk();

        $this->as($this->assessor)->getJson("/api/v1/assessments/{$assessment->id}")->assertJsonPath('data.can_reopen', false);
        $this->as($this->user('admin'))->getJson("/api/v1/assessments/{$assessment->id}")->assertJsonPath('data.can_reopen', true);
    }

    // ── previous / retired templates ──────────────────────────────────────

    public function test_assessments_on_a_retired_template_keep_their_template_and_schema(): void
    {
        $assessment = Assessment::findOrFail($this->create(['assessment_type_id' => $this->old->id])->json('assessment.id'));

        $this->old->delete(); // soft-delete = retire

        $this->as($this->assessor)->getJson("/api/v1/assessments/{$assessment->id}")
            ->assertOk()
            ->assertJsonPath('data.template.code', 'STANDARD_FACILITY_ASSESSMENT')
            ->assertJsonPath('data.template.is_retired', true);

        $this->as($this->assessor)->getJson("/api/v1/assessments/{$assessment->id}/schema")
            ->assertOk()->assertJsonPath('sections.0.questions.0.question_code', 'OLD_Q1');

        $this->as($this->assessor)->getJson("/api/v1/assessment-templates/{$this->old->id}")->assertOk();

        // It can still be worked on…
        $this->as($this->assessor)->postJson("/api/v1/assessments/{$assessment->id}/responses", [
            'section_code' => 'infrastructure', 'responses' => ['OLD_Q1' => 'Yes'],
        ])->assertOk()->assertJsonPath('saved', 1);

        // …but a new assessment can't be started on it.
        $this->create(['assessment_type_id' => $this->old->id, 'round' => 'midline'])->assertStatus(422);
    }

    public function test_template_can_only_change_before_any_answers_exist(): void
    {
        $assessment = Assessment::findOrFail($this->create(['assessment_type_id' => $this->old->id])->json('assessment.id'));

        $this->as($this->assessor)->putJson("/api/v1/assessments/{$assessment->id}", ['assessment_type_id' => $this->new->id])
            ->assertOk()->assertJsonPath('assessment.assessment_type_id', $this->new->id);
        $this->assertEqualsCanonicalizing(['infrastructure', 'skills'], array_keys($assessment->fresh()->section_progress));

        $this->as($this->assessor)->postJson("/api/v1/assessments/{$assessment->id}/responses", [
            'section_code' => 'infrastructure', 'responses' => ['NEW_Q1' => 'Yes'],
        ])->assertOk();

        $this->as($this->assessor)->putJson("/api/v1/assessments/{$assessment->id}", ['assessment_type_id' => $this->old->id])
            ->assertStatus(422);
    }

    // ── schema ────────────────────────────────────────────────────────────

    public function test_schema_endpoint_can_be_scoped_to_a_template(): void
    {
        $this->as($this->assessor)->getJson("/api/v1/sections/schema/full?assessment_type_id={$this->new->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('template.code', 'READINESS_2026');
    }

    // ── team ──────────────────────────────────────────────────────────────

    public function test_lead_can_remove_a_member_but_not_the_only_lead(): void
    {
        $member = $this->user('assessor');
        $id = $this->create(['member_ids' => [$member->id]])->json('assessment.id');

        $this->as($this->assessor)->deleteJson("/api/v1/assessments/{$id}/team/{$member->id}")
            ->assertOk()->assertJsonCount(0, 'team_members');

        $this->as($this->assessor)->deleteJson("/api/v1/assessments/{$id}/team/{$this->assessor->id}")
            ->assertStatus(422);
    }

    public function test_lead_can_transfer_the_lead_role_and_non_managers_are_refused(): void
    {
        $member = $this->user('assessor');
        $outsider = $this->user('assessor');
        $id = $this->create(['member_ids' => [$member->id]])->json('assessment.id');

        $this->as($outsider)->putJson("/api/v1/assessments/{$id}/team/{$member->id}/role", ['role' => 'team_lead'])->assertStatus(403);

        $this->as($this->assessor)->putJson("/api/v1/assessments/{$id}/team/{$member->id}/role", ['role' => 'team_lead'])
            ->assertOk()->assertJsonPath('lead_assessor.id', $member->id);

        // The old lead is now a plain member and can no longer manage the team.
        $this->as($this->assessor)->putJson("/api/v1/assessments/{$id}/team/{$this->assessor->id}/role", ['role' => 'team_lead'])
            ->assertStatus(403);
    }

    // ── human resources ───────────────────────────────────────────────────

    public function test_human_resources_only_lists_and_accepts_the_templates_own_cadres(): void
    {
        $oldCadre = MainCadre::create(['assessment_type_id' => $this->old->id, 'name' => 'Nurse (old)', 'is_active' => true, 'order' => 1]);
        $newCadre = MainCadre::create(['assessment_type_id' => $this->new->id, 'name' => 'Nurse (new)', 'is_active' => true, 'order' => 1]);
        $others = MainCadre::create(['assessment_type_id' => $this->new->id, 'name' => 'Others', 'is_active' => true, 'order' => 2]);
        $id = $this->create()->json('assessment.id');

        // "Others" is hidden by default; only this template's cadres are offered.
        $this->as($this->assessor)->getJson("/api/v1/assessments/{$id}/human-resources")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.cadre_name', 'Nurse (new)')
            ->assertJsonCount(2, 'cadres');

        $this->as($this->assessor)->postJson("/api/v1/assessments/{$id}/human-resources", [
            'responses' => [['cadre_id' => $oldCadre->id, 'total_in_facility' => 3]],
        ])->assertStatus(422);

        $this->as($this->assessor)->putJson("/api/v1/assessments/{$id}/human-resources/cadres", [
            'included_cadre_ids' => [$newCadre->id, $others->id],
        ])->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_na_training_columns_are_returned_as_null_and_not_stored(): void
    {
        $tots = MainCadre::create([
            'assessment_type_id' => $this->new->id, 'name' => 'No of TOTs', 'is_active' => true, 'order' => 1,
            'na_training_columns' => ['total_in_facility', 'type_1_diabetes'],
        ]);
        $id = $this->create()->json('assessment.id');

        $this->as($this->assessor)->postJson("/api/v1/assessments/{$id}/human-resources", [
            'responses' => [['cadre_id' => $tots->id, 'total_in_facility' => 9, 'imnci' => 2, 'type_1_diabetes' => 4]],
        ])->assertOk();

        $this->as($this->assessor)->getJson("/api/v1/assessments/{$id}/human-resources")
            ->assertJsonPath('data.0.total_in_facility', null)
            ->assertJsonPath('data.0.type_1_diabetes', null)
            ->assertJsonPath('data.0.imnci', 2)
            ->assertJsonPath('data.0.hides_total_in_facility', true);
    }
}
