<?php

namespace Tests\Feature;

use App\Filament\Resources\AssessmentResource\Pages\ListAssessments;
use App\Models\Assessment;
use App\Models\AssessmentType;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * AssessmentResource::table() is dead code — ListAssessments defines its
 * own table() that fully overrides it (Filament pages take priority over
 * the Resource's own table() when they declare one). The feedback,
 * training, and executive-dashboard row actions previously only existed
 * in that dead code, making them unreachable from the real UI even
 * though their underlying logic worked fine when called directly. These
 * tests exercise the actions now that they live on ListAssessments,
 * the page that's actually rendered.
 */
class AssessmentDownloadActionTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $user = User::factory()->create(['name' => 'Test Admin']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view_any_assessment', 'guard_name' => 'web']);
        $user->assignRole('admin');
        $user->givePermissionTo('view_any_assessment');
        $this->actingAs($user);

        return $user;
    }

    private function createCompletedAssessment(): Assessment
    {
        $facility = Facility::factory()->create(['name' => 'Test Facility']);
        $assessmentType = AssessmentType::where('code', 'STANDARD_FACILITY_ASSESSMENT')->firstOrFail();

        return Assessment::create([
            'facility_id' => $facility->id,
            'assessment_type_id' => $assessmentType->id,
            'assessment_type' => 'baseline',
            'assessment_date' => now(),
            'status' => 'completed',
        ]);
    }

    public function test_download_pdf_action_streams_a_pdf_for_a_completed_assessment(): void
    {
        $this->actingAsAdmin();
        $assessment = $this->createCompletedAssessment();

        Livewire::test(ListAssessments::class)
            ->callTableAction('download_pdf', $assessment)
            ->assertFileDownloaded("MNCH-Assessment-Test Facility-{$assessment->assessment_date->format('Y-m-d')}.pdf");
    }

    public function test_executive_dashboard_action_links_to_the_executive_route_and_only_for_completed_assessments(): void
    {
        $this->actingAsAdmin();
        $assessment = $this->createCompletedAssessment();

        Livewire::test(ListAssessments::class)
            ->assertTableActionVisible('executive_dashboard', $assessment)
            ->assertTableActionHasUrl('executive_dashboard', route('assessment.executive', $assessment), $assessment);

        $assessment->update(['status' => 'in_progress']);

        Livewire::test(ListAssessments::class)
            ->assertTableActionHidden('executive_dashboard', $assessment);
    }

    public function test_mark_feedback_given_action_records_feedback(): void
    {
        $admin = $this->actingAsAdmin();
        $assessment = $this->createCompletedAssessment();

        Livewire::test(ListAssessments::class)
            ->callTableAction('markFeedbackGiven', $assessment, data: [
                'feedback_notes' => 'Shared findings with the facility team.',
            ]);

        $assessment->refresh();
        $this->assertTrue($assessment->feedback_given);
        $this->assertSame($admin->id, $assessment->feedback_given_by);
        $this->assertNotNull($assessment->feedback_given_at);
        $this->assertSame('Shared findings with the facility team.', $assessment->feedback_notes);
    }

    public function test_mark_trained_action_is_hidden_until_feedback_has_been_given(): void
    {
        $this->actingAsAdmin();
        $assessment = $this->createCompletedAssessment();

        Livewire::test(ListAssessments::class)
            ->assertTableActionHidden('markTrained', $assessment);

        $assessment->update(['feedback_given' => true]);

        Livewire::test(ListAssessments::class)
            ->callTableAction('markTrained', $assessment, data: [
                'trained_before_mentorship' => '1',
            ]);

        $assessment->refresh();
        $this->assertTrue($assessment->trained_before_mentorship);
        $this->assertNotNull($assessment->trained_marked_at);
    }

    public function test_actions_have_the_renamed_labels(): void
    {
        $this->actingAsAdmin();
        $assessment = $this->createCompletedAssessment();

        Livewire::test(ListAssessments::class)
            ->assertTableActionHasLabel('view_summary', 'View Summary', $assessment)
            ->assertTableActionHasLabel('download_pdf', 'Download Report', $assessment);

        $assessment->update(['status' => 'in_progress']);

        Livewire::test(ListAssessments::class)
            ->assertTableActionHasLabel('dashboard', 'Continue Summary', $assessment);
    }

    public function test_only_an_admin_can_delete_a_completed_assessment(): void
    {
        Role::firstOrCreate(['name' => 'assessor', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view_any_assessment', 'guard_name' => 'web']);
        $assessor = User::factory()->create(['name' => 'Plain Assessor']);
        $assessor->assignRole('assessor');
        $assessor->givePermissionTo('view_any_assessment');
        $this->actingAs($assessor);

        $assessment = $this->createCompletedAssessment();

        Livewire::test(ListAssessments::class)
            ->assertTableActionHidden('delete', $assessment);

        $this->actingAsAdmin();

        Livewire::test(ListAssessments::class)
            ->assertTableActionVisible('delete', $assessment);
    }

    public function test_delete_is_visible_for_non_completed_assessments_regardless_of_role(): void
    {
        Role::firstOrCreate(['name' => 'assessor', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view_any_assessment', 'guard_name' => 'web']);
        $assessor = User::factory()->create(['name' => 'Plain Assessor']);
        $assessor->assignRole('assessor');
        $assessor->givePermissionTo('view_any_assessment');
        $this->actingAs($assessor);

        $assessment = $this->createCompletedAssessment();
        $assessment->update(['status' => 'in_progress']);

        Livewire::test(ListAssessments::class)
            ->assertTableActionVisible('delete', $assessment);
    }

    public function test_assessor_column_is_visible_without_toggling(): void
    {
        $admin = $this->actingAsAdmin();
        $this->createCompletedAssessment();

        Livewire::test(ListAssessments::class)
            ->assertSee($admin->name);
    }
}
