<?php

namespace Tests\Feature;

use App\Filament\Resources\AssessmentResource\Pages\CreateAssessment;
use App\Models\Assessment;
use App\Models\AssessmentType;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CreateAssessmentRoundTest extends TestCase
{
    use RefreshDatabase;

    private function makeAssessor(): User
    {
        $user = User::factory()->create(['name' => 'Test Assessor']);

        $role = Role::firstOrCreate(['name' => 'assessor', 'guard_name' => 'web']);
        foreach ([
            'view_any_assessment',
            'view_any_assessment::type',
            'update_assessment::type',
            'create_assessment::type',
            'view_any_assessment::question',
        ] as $permission) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
        }
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    public function test_create_assessment_stores_the_selected_round(): void
    {
        $this->makeAssessor();
        $type = AssessmentType::create(['name' => 'Standard Template', 'code' => 'STD', 'version' => '1.0', 'is_active' => true]);
        $facility = Facility::factory()->create();

        Livewire::test(CreateAssessment::class)
            ->fillForm([
                'facility_id' => $facility->id,
                'assessment_type_id' => $type->id,
                'assessment_date' => now()->toDateString(),
                'round' => 'midline',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame('midline', Assessment::where('facility_id', $facility->id)->value('round'));
    }

    public function test_create_assessment_requires_round_label_when_round_is_other(): void
    {
        $this->makeAssessor();
        $type = AssessmentType::create(['name' => 'Standard Template', 'code' => 'STD2', 'version' => '1.0', 'is_active' => true]);
        $facility = Facility::factory()->create();

        Livewire::test(CreateAssessment::class)
            ->fillForm([
                'facility_id' => $facility->id,
                'assessment_type_id' => $type->id,
                'assessment_date' => now()->toDateString(),
                'round' => 'other',
                'round_label' => '',
            ])
            ->call('create')
            ->assertHasFormErrors(['round_label' => 'required']);
    }

    public function test_create_assessment_allows_baseline_and_midline_for_same_facility_and_template(): void
    {
        $assessor = $this->makeAssessor();
        $type = AssessmentType::create(['name' => 'Standard Template', 'code' => 'STD3', 'version' => '1.0', 'is_active' => true]);
        $facility = Facility::factory()->create();

        Assessment::create([
            'facility_id' => $facility->id,
            'assessment_type_id' => $type->id,
            'round' => 'baseline',
            'assessment_date' => now(),
            'assessor_id' => $assessor->id,
            'assessor_name' => $assessor->name,
        ]);

        Livewire::test(CreateAssessment::class)
            ->fillForm([
                'facility_id' => $facility->id,
                'assessment_type_id' => $type->id,
                'assessment_date' => now()->toDateString(),
                'round' => 'midline',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(2, Assessment::where('facility_id', $facility->id)->where('assessment_type_id', $type->id)->count());
    }

    public function test_create_assessment_blocks_a_duplicate_round_for_the_same_facility_and_template(): void
    {
        $assessor = $this->makeAssessor();
        $type = AssessmentType::create(['name' => 'Standard Template', 'code' => 'STD4', 'version' => '1.0', 'is_active' => true]);
        $facility = Facility::factory()->create();

        Assessment::create([
            'facility_id' => $facility->id,
            'assessment_type_id' => $type->id,
            'round' => 'baseline',
            'assessment_date' => now(),
            'assessor_id' => $assessor->id,
            'assessor_name' => $assessor->name,
        ]);

        Livewire::test(CreateAssessment::class)
            ->fillForm([
                'facility_id' => $facility->id,
                'assessment_type_id' => $type->id,
                'assessment_date' => now()->toDateString(),
                'round' => 'baseline',
            ])
            ->call('create');

        $this->assertSame(1, Assessment::where('facility_id', $facility->id)->where('assessment_type_id', $type->id)->count());
    }

    public function test_create_assessment_allows_two_distinctly_labeled_other_rounds(): void
    {
        $assessor = $this->makeAssessor();
        $type = AssessmentType::create(['name' => 'Standard Template', 'code' => 'STD5', 'version' => '1.0', 'is_active' => true]);
        $facility = Facility::factory()->create();

        Assessment::create([
            'facility_id' => $facility->id,
            'assessment_type_id' => $type->id,
            'round' => 'other',
            'round_label' => 'Ad-hoc Review 1',
            'assessment_date' => now(),
            'assessor_id' => $assessor->id,
            'assessor_name' => $assessor->name,
        ]);

        Livewire::test(CreateAssessment::class)
            ->fillForm([
                'facility_id' => $facility->id,
                'assessment_type_id' => $type->id,
                'assessment_date' => now()->toDateString(),
                'round' => 'other',
                'round_label' => 'Ad-hoc Review 2',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(2, Assessment::where('facility_id', $facility->id)->where('assessment_type_id', $type->id)->count());
    }
}
