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

class AssessmentTableFiltersTest extends TestCase
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

    /**
     * Two assessments in two different counties/facilities, assessed by
     * two different users, against two different templates — enough
     * variation for each filter to prove it actually narrows the table
     * rather than passing by coincidence.
     *
     * @return array{a: Assessment, b: Assessment}
     */
    private function buildTwoDistinctAssessments(): array
    {
        $standardType = AssessmentType::where('code', 'STANDARD_FACILITY_ASSESSMENT')->firstOrFail();
        $otherType = AssessmentType::create([
            'name' => 'Alternate Template',
            'code' => 'ALTERNATE_TEMPLATE',
            'version' => '1.0',
            'is_active' => true,
        ]);

        $facilityA = Facility::factory()->create(['name' => 'Facility A']);
        $facilityB = Facility::factory()->create(['name' => 'Facility B']);

        $assessorA = User::factory()->create(['name' => 'Assessor A']);
        $assessorB = User::factory()->create(['name' => 'Assessor B']);

        $admin = auth()->user();

        // Assessment::creating() auto-overwrites assessor_id/assessor_name
        // from the currently authenticated user — act as each assessor in
        // turn while creating so the records end up owned by the intended
        // assessor, not whoever is viewing the table.
        $this->actingAs($assessorA);
        $a = Assessment::create([
            'facility_id' => $facilityA->id,
            'assessment_type_id' => $standardType->id,
            'assessment_type' => 'baseline',
            'assessment_date' => now(),
            'status' => 'completed',
        ]);

        $this->actingAs($assessorB);
        $b = Assessment::create([
            'facility_id' => $facilityB->id,
            'assessment_type_id' => $otherType->id,
            'assessment_type' => 'midline',
            'assessment_date' => now(),
            'status' => 'draft',
        ]);

        $this->actingAs($admin);

        return compact('a', 'b');
    }

    public function test_county_filter_narrows_to_the_selected_countys_assessments(): void
    {
        $this->actingAsAdmin();
        ['a' => $a, 'b' => $b] = $this->buildTwoDistinctAssessments();

        $countyId = $a->facility->subcounty->county_id;

        Livewire::test(ListAssessments::class)
            ->filterTable('county_id', $countyId)
            ->assertCanSeeTableRecords([$a])
            ->assertCanNotSeeTableRecords([$b]);
    }

    public function test_facility_filter_narrows_to_the_selected_facility(): void
    {
        $this->actingAsAdmin();
        ['a' => $a, 'b' => $b] = $this->buildTwoDistinctAssessments();

        Livewire::test(ListAssessments::class)
            ->filterTable('facility_id', ['facility_id' => $a->facility_id])
            ->assertCanSeeTableRecords([$a])
            ->assertCanNotSeeTableRecords([$b]);
    }

    public function test_assessment_template_filter_narrows_to_the_selected_template(): void
    {
        $this->actingAsAdmin();
        ['a' => $a, 'b' => $b] = $this->buildTwoDistinctAssessments();

        Livewire::test(ListAssessments::class)
            ->filterTable('assessment_type_id', $a->assessment_type_id)
            ->assertCanSeeTableRecords([$a])
            ->assertCanNotSeeTableRecords([$b]);
    }

    public function test_assessor_filter_narrows_to_the_selected_assessor(): void
    {
        $this->actingAsAdmin();
        ['a' => $a, 'b' => $b] = $this->buildTwoDistinctAssessments();

        Livewire::test(ListAssessments::class)
            ->filterTable('assessor_id', $a->assessor_id)
            ->assertCanSeeTableRecords([$a])
            ->assertCanNotSeeTableRecords([$b]);
    }

    public function test_status_filter_still_narrows_to_the_selected_status(): void
    {
        $this->actingAsAdmin();
        ['a' => $a, 'b' => $b] = $this->buildTwoDistinctAssessments();

        Livewire::test(ListAssessments::class)
            ->filterTable('status', 'draft')
            ->assertCanSeeTableRecords([$b])
            ->assertCanNotSeeTableRecords([$a]);
    }

    /**
     * The Assessor and County filters build their option lists eagerly
     * (options()), and Filament's Select crashes with a TypeError
     * ("$label must be of type string, null given") the moment ANY
     * option in that list has a null label — not just the affected one.
     * Both `users.name` and `counties.name` are nullable at the schema
     * level even though the columns are usually populated. Reproduces
     * with a user who has a null `name` (only first_name/last_name set,
     * as several real users in production do) — the Assessor filter must
     * not blow up just because such a user exists somewhere in the
     * system, whether or not they've ever assessed anything.
     */
    public function test_page_renders_when_a_user_with_a_null_name_exists_in_the_system(): void
    {
        $this->actingAsAdmin();
        $this->buildTwoDistinctAssessments();

        User::factory()->create([
            'name' => null,
            'first_name' => 'Null',
            'last_name' => 'Name',
        ]);

        $response = $this->get(\App\Filament\Resources\AssessmentResource::getUrl());

        $response->assertOk();
    }
}
