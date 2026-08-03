<?php

namespace Tests\Unit;

use App\Filament\Resources\MentorshipResource\Pages\ChatMentorshipSetup;
use App\Models\Facility;
use App\Models\Program;
use App\Models\User;
use App\Services\Chat\Tools\MentorshipMenteesToolProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MentorshipMenteesToolProviderTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsCoordinator(): User
    {
        $user = User::factory()->create(['name' => 'Coordinator']);
        Permission::firstOrCreate(['name' => 'create_mentorship::training', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view_any_mentorship::training', 'guard_name' => 'web']);
        $user->givePermissionTo(['create_mentorship::training', 'view_any_mentorship::training']);
        $this->actingAs($user);

        return $user;
    }

    private function advanceToEnrollMenteesStage($page, Program $program, Facility $facility, int $maxParticipants = 8): void
    {
        $page->answer('is_pilot', 0);
        $page->answer('county_id', $facility->subcounty->county_id);
        $page->answer('facility_id', $facility->id);
        $page->answer('program_id', $program->id);
        $page->answer('start_date', now()->addDay()->toDateString());
        $page->answer('end_date', now()->addMonth()->toDateString());
        $page->answer('max_participants', $maxParticipants);
        $page->answer('class_name', 'Cohort A');
        $page->answer('class_start_date', now()->addDay()->toDateString());
        $page->answer('class_end_date', now()->addMonth()->toDateString());
        $page->answer('class_description', 'skip');
        $page->submitModules([]);
    }

    public function test_execute_resolves_a_uniquely_matching_name_and_enrolls_them(): void
    {
        $user = $this->actingAsCoordinator();
        $program = Program::factory()->create(['is_active' => true]);
        $facility = Facility::factory()->create();
        $mentee = User::factory()->create(['first_name' => 'Alphonce', 'last_name' => 'Ochieng', 'status' => 'active', 'email' => 'alphonce@example.com']);
        $page = new ChatMentorshipSetup;
        $page->mount();
        $this->advanceToEnrollMenteesStage($page, $program, $facility);

        $this->assertSame('enroll_mentees', $page->activeStage());

        $tool = MentorshipMenteesToolProvider::tool($page);
        $result = $tool->execute(['existing_mentee_queries' => ['Alphonce']], $user);

        $this->assertSame([$mentee->id], $result['enrolled']);
        $this->assertDatabaseHas('class_participants', [
            'mentorship_class_id' => $page->class->id,
            'user_id' => $mentee->id,
        ]);
    }

    public function test_execute_finds_a_user_by_email_or_phone(): void
    {
        $user = $this->actingAsCoordinator();
        $program = Program::factory()->create(['is_active' => true]);
        $facility = Facility::factory()->create();
        $mentee = User::factory()->create(['status' => 'active', 'email' => 'jane.doe@example.com', 'phone' => '0712345678']);
        $page = new ChatMentorshipSetup;
        $page->mount();
        $this->advanceToEnrollMenteesStage($page, $program, $facility);

        $tool = MentorshipMenteesToolProvider::tool($page);
        $result = $tool->execute(['existing_mentee_queries' => ['jane.doe@example.com']], $user);

        $this->assertSame([$mentee->id], $result['enrolled']);
    }

    public function test_execute_returns_candidates_for_an_ambiguous_query_instead_of_guessing(): void
    {
        $user = $this->actingAsCoordinator();
        $program = Program::factory()->create(['is_active' => true]);
        $facility = Facility::factory()->create();
        User::factory()->create(['first_name' => 'John', 'last_name' => 'Kamau', 'status' => 'active']);
        User::factory()->create(['first_name' => 'John', 'last_name' => 'Otieno', 'status' => 'active']);
        $page = new ChatMentorshipSetup;
        $page->mount();
        $this->advanceToEnrollMenteesStage($page, $program, $facility);

        $tool = MentorshipMenteesToolProvider::tool($page);
        $result = $tool->execute(['existing_mentee_queries' => ['John']], $user);

        $this->assertArrayNotHasKey('enrolled', $result);
        $this->assertArrayHasKey('candidates', $result);
        $labels = array_column($result['candidates']['John'], 'label');
        $this->assertCount(2, $labels);
        $this->assertArrayNotHasKey('selected_users', $page->answers);
    }

    public function test_execute_reports_unresolved_when_nothing_matches(): void
    {
        $user = $this->actingAsCoordinator();
        $program = Program::factory()->create(['is_active' => true]);
        $facility = Facility::factory()->create();
        $page = new ChatMentorshipSetup;
        $page->mount();
        $this->advanceToEnrollMenteesStage($page, $program, $facility);

        $tool = MentorshipMenteesToolProvider::tool($page);
        $result = $tool->execute(['existing_mentee_queries' => ['Nobody Real Whatsoever']], $user);

        $this->assertArrayNotHasKey('enrolled', $result);
        $this->assertContains('Nobody Real Whatsoever', $result['unresolved']);
        $this->assertArrayNotHasKey('selected_users', $page->answers);
    }

    public function test_execute_enrolls_a_brand_new_mentee_by_email(): void
    {
        $user = $this->actingAsCoordinator();
        $program = Program::factory()->create(['is_active' => true]);
        $facility = Facility::factory()->create();
        $page = new ChatMentorshipSetup;
        $page->mount();
        $this->advanceToEnrollMenteesStage($page, $program, $facility);

        $tool = MentorshipMenteesToolProvider::tool($page);
        $result = $tool->execute([
            'new_mentee' => ['email' => 'brandnew@example.com', 'first_name' => 'Brand', 'last_name' => 'New'],
        ], $user);

        $this->assertArrayHasKey('enrolled', $result);
        $this->assertDatabaseHas('users', ['email' => 'brandnew@example.com']);
    }

    public function test_execute_skips_when_the_user_wants_no_mentees_yet(): void
    {
        $user = $this->actingAsCoordinator();
        $program = Program::factory()->create(['is_active' => true]);
        $facility = Facility::factory()->create();
        $page = new ChatMentorshipSetup;
        $page->mount();
        $this->advanceToEnrollMenteesStage($page, $program, $facility);

        $tool = MentorshipMenteesToolProvider::tool($page);
        $tool->execute(['skip' => true], $user);

        $this->assertArrayHasKey('selected_users', $page->answers);
        $this->assertSame(0, $page->class->participants()->count());
    }

    public function test_execute_rejects_more_mentees_than_the_max_participants_cap(): void
    {
        $user = $this->actingAsCoordinator();
        $program = Program::factory()->create(['is_active' => true]);
        $facility = Facility::factory()->create();
        $menteeA = User::factory()->create(['first_name' => 'Alice', 'status' => 'active']);
        $menteeB = User::factory()->create(['first_name' => 'Brian', 'status' => 'active']);
        $menteeC = User::factory()->create(['first_name' => 'Cyrus', 'status' => 'active']);
        $page = new ChatMentorshipSetup;
        $page->mount();
        $this->advanceToEnrollMenteesStage($page, $program, $facility, maxParticipants: 2);

        $tool = MentorshipMenteesToolProvider::tool($page);
        $result = $tool->execute(['existing_mentee_queries' => ['Alice', 'Brian', 'Cyrus']], $user);

        $this->assertArrayHasKey('error', $result);
        $this->assertArrayNotHasKey('selected_users', $page->answers);
        $this->assertSame(0, $page->class->participants()->count());
    }

    /**
     * A real, matched existing mentee with no email on file pauses instead
     * of enrolling — chat-mentees-turn.blade.php's missing-email modal is
     * the only way to supply one (its inputs are bound to Livewire
     * properties, not reachable through a tool call), so the result must
     * steer the model toward pointing the user at that modal rather than
     * asking for a full "registration" (role, department, etc.) that this
     * app never actually collects.
     */
    public function test_execute_pauses_and_hints_at_the_modal_when_a_matched_mentee_has_no_email(): void
    {
        $user = $this->actingAsCoordinator();
        $program = Program::factory()->create(['is_active' => true]);
        $facility = Facility::factory()->create();
        $mentee = User::factory()->create(['name' => 'Betty Njoroge', 'first_name' => 'Betty', 'last_name' => 'Njoroge', 'status' => 'active', 'email' => null]);
        $page = new ChatMentorshipSetup;
        $page->mount();
        $this->advanceToEnrollMenteesStage($page, $program, $facility);

        $tool = MentorshipMenteesToolProvider::tool($page);
        $result = $tool->execute(['existing_mentee_queries' => ['Betty']], $user);

        $this->assertTrue($result['awaiting_email']);
        $this->assertSame(['Betty Njoroge'], $result['mentees_needing_email']);
        $this->assertArrayHasKey('message', $result);
        $this->assertArrayNotHasKey('enrolled', $result);
        $this->assertSame(0, $page->class->participants()->count());
        $this->assertSame([['id' => $mentee->id, 'name' => 'Betty Njoroge']], $page->menteesNeedingEmail);
    }
}
