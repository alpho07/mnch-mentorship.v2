<?php

namespace Tests\Unit;

use App\Filament\Resources\MentorshipResource\Pages\ChatMentorshipSetup;
use App\Models\ClassParticipant;
use App\Models\MentorshipClass;
use App\Models\Training;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MentorshipChatRemainingRequirementsTest extends TestCase
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

    public function test_empty_answers_returns_the_full_checklist_including_composite_stages(): void
    {
        $this->actingAsCoordinator();
        $page = new ChatMentorshipSetup;
        $page->mount();

        $requirements = $page->remainingRequirements();
        $labels = array_column($requirements, 'label');

        $this->assertContains('Select training modules', $labels);
        $this->assertContains('Enroll mentees', $labels);
        $this->assertContains('Who should receive the email?', $labels);
        $this->assertTrue(collect($requirements)->every(fn ($r) => $r['filled'] === false));
    }

    public function test_filling_a_slot_removes_exactly_its_own_entry(): void
    {
        $this->actingAsCoordinator();
        $page = new ChatMentorshipSetup;
        $page->mount();

        $before = collect($page->remainingRequirements())->pluck('filled', 'label');
        $this->assertFalse($before['Is this a real live mentorship or a pilot/test run?'] ?? null);

        $page->answers['is_pilot'] = 0;

        $after = collect($page->remainingRequirements())->pluck('filled', 'label');
        $this->assertTrue($after['Is this a real live mentorship or a pilot/test run?']);
        // Nothing else flipped.
        $this->assertFalse($after['Which county?']);
    }

    public function test_reaching_the_modules_stage_removes_its_placeholder_once_module_ids_is_set(): void
    {
        $this->actingAsCoordinator();
        $training = Training::factory()->facilityMentorship()->create();
        $class = MentorshipClass::factory()->create(['training_id' => $training->id]);

        $page = new ChatMentorshipSetup;
        $page->training = $training;
        $page->class = $class;
        $page->answers = [
            'is_pilot' => 0,
            'county_id' => 1,
            'facility_id' => 1,
            'program_id' => 1,
            'max_participants' => 5,
            'class_name' => 'Cohort 1',
        ];

        $before = collect($page->remainingRequirements())->pluck('filled', 'label');
        $this->assertFalse($before['Select training modules']);

        $page->answers['module_ids'] = [1, 2];

        $after = collect($page->remainingRequirements())->pluck('filled', 'label');
        $this->assertTrue($after['Select training modules']);
        $this->assertFalse($after['Enroll mentees']);
    }

    public function test_returns_empty_once_everything_including_invitations_is_done(): void
    {
        $this->actingAsCoordinator();
        $training = Training::factory()->facilityMentorship()->create();
        $class = MentorshipClass::factory()->create(['training_id' => $training->id]);
        $mentee = User::factory()->create();
        ClassParticipant::factory()->create(['mentorship_class_id' => $class->id, 'user_id' => $mentee->id]);

        $page = new ChatMentorshipSetup;
        $page->training = $training;
        $page->class = $class;
        $page->answers = [
            'is_pilot' => 0,
            'county_id' => 1,
            'facility_id' => 1,
            'program_id' => 1,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
            'max_participants' => 5,
            'class_name' => 'Cohort 1',
            'class_start_date' => '2026-09-01',
            'class_end_date' => '2026-09-30',
            'class_description' => 'skip',
            'module_ids' => [1],
            'selected_users' => [$mentee->id],
            'recipients' => 'all',
        ];

        $remaining = array_filter($page->remainingRequirements(), fn ($r) => ! $r['filled']);

        $this->assertSame([], array_values($remaining));
    }

    /**
     * MentorshipChatScript::STAGES orders these as training_details,
     * first_class, modules, enroll_mentees, send_invitations — but
     * modules/enroll_mentees aren't real Slot objects, so a naive
     * implementation that walks slots() and only appends them at the very
     * end puts "Who should receive the email?" (a real, always-unfilled
     * Slot) *before* them in the list. MNCHGPT was observed literally
     * following that wrong order — asking about email recipients before
     * modules or mentees were ever touched. The checklist order must match
     * the real stage order the app itself enforces.
     */
    public function test_modules_and_enroll_mentees_are_listed_before_recipients_not_after(): void
    {
        $this->actingAsCoordinator();
        $training = Training::factory()->facilityMentorship()->create();
        $class = MentorshipClass::factory()->create(['training_id' => $training->id]);

        $page = new ChatMentorshipSetup;
        $page->training = $training;
        $page->class = $class;
        $page->answers = [
            'is_pilot' => 0,
            'county_id' => 1,
            'facility_id' => 1,
            'program_id' => 1,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
            'max_participants' => 5,
            'class_name' => 'Cohort 1',
            'class_start_date' => '2026-09-01',
            'class_end_date' => '2026-09-30',
            'class_description' => 'skip',
        ];

        $labels = array_column($page->remainingRequirements(), 'label');
        $modulesIndex = array_search('Select training modules', $labels);
        $menteesIndex = array_search('Enroll mentees', $labels);
        $recipientsIndex = array_search('Who should receive the email?', $labels);

        $this->assertLessThan($recipientsIndex, $modulesIndex);
        $this->assertLessThan($recipientsIndex, $menteesIndex);
    }
}
