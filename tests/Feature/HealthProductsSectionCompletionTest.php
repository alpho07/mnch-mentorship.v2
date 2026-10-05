<?php

namespace Tests\Feature;

use App\Filament\Resources\AssessmentResource;
use App\Filament\Resources\AssessmentResource\Pages\EditHealthProducts;
use App\Models\Assessment;
use App\Models\AssessmentDepartment;
use App\Models\AssessmentSection;
use App\Models\AssessmentType;
use App\Models\Commodity;
use App\Models\CommodityCategory;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Health Products completion is derived from the commodity responses on
 * record, not from a flag set the first time any one department was saved.
 *
 * The bug this covers: a template can carry more than one active
 * commodity_matrix section (production template 2 has both
 * `department_health_products` and `health_products`), and EditHealthProducts
 * only ever flagged the one it happened to resolve — so the sibling section
 * stayed "Pending" on the dashboard no matter how much was answered. The same
 * flag also read as complete after a single department was saved, while the
 * other departments were still untouched.
 */
class HealthProductsSectionCompletionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['name' => 'Matrix Completion Assessor']);
        Role::firstOrCreate(['name' => 'assessor', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view_any_assessment', 'guard_name' => 'web']);
        $this->user->givePermissionTo('view_any_assessment');
        $this->user->assignRole('assessor');
        $this->actingAs($this->user);
    }

    /**
     * Template with two commodity_matrix sections and two departments, each
     * with one commodity.
     *
     * @return array{0: Assessment, 1: AssessmentDepartment, 2: AssessmentDepartment, 3: Commodity, 4: Commodity, 5: AssessmentSection}
     */
    private function makeScenario(string $suffix): array
    {
        $type = AssessmentType::create([
            'name' => "Matrix {$suffix}", 'code' => "MATRIX_{$suffix}", 'is_active' => true,
        ]);

        AssessmentSection::create([
            'assessment_type_id' => $type->id, 'name' => 'Department Commodities', 'code' => "department_health_products_{$suffix}",
            'section_type' => AssessmentSection::KIND_COMMODITY_MATRIX, 'order' => 1, 'is_active' => true,
        ]);
        AssessmentSection::create([
            'assessment_type_id' => $type->id, 'name' => 'Health Products and Technologies', 'code' => "health_products_{$suffix}",
            'section_type' => AssessmentSection::KIND_COMMODITY_MATRIX, 'order' => 2, 'is_active' => true,
        ]);
        $trailing = AssessmentSection::create([
            'assessment_type_id' => $type->id, 'name' => 'Quality of Care', 'code' => "quality_of_care_{$suffix}",
            'section_type' => AssessmentSection::KIND_QUESTION_GROUP, 'order' => 3, 'is_active' => true,
        ]);

        $skillsLab = AssessmentDepartment::create([
            'assessment_type_id' => $type->id, 'name' => 'Skills lab', 'slug' => "skills-lab-{$suffix}", 'is_active' => true, 'order' => 1,
        ]);
        $nbu = AssessmentDepartment::create([
            'assessment_type_id' => $type->id, 'name' => 'NBU', 'slug' => "nbu-{$suffix}", 'is_active' => true, 'order' => 2,
        ]);

        $category = CommodityCategory::create([
            'assessment_type_id' => $type->id, 'name' => 'AIRWAY', 'slug' => "airway-{$suffix}", 'order' => 1,
        ]);
        $itemA = Commodity::create(['commodity_category_id' => $category->id, 'name' => 'Item A', 'order' => 1, 'is_active' => true]);
        $itemB = Commodity::create(['commodity_category_id' => $category->id, 'name' => 'Item B', 'order' => 2, 'is_active' => true]);
        $itemA->applicableDepartments()->attach($skillsLab->id);
        $itemB->applicableDepartments()->attach($nbu->id);

        $assessment = Assessment::create([
            'facility_id' => Facility::factory()->create()->id, 'assessment_type_id' => $type->id,
            'round' => 'baseline', 'assessment_date' => now(),
            'assessor_id' => $this->user->id, 'assessor_name' => $this->user->name,
        ]);

        return [$assessment, $skillsLab, $nbu, $itemA, $itemB, $trailing];
    }

    private function saveDepartment(Assessment $assessment, AssessmentDepartment $dept, Commodity $commodity, ?string $deptQuery = null)
    {
        return Livewire::withQueryParams(['dept' => $deptQuery ?? $dept->slug])
            ->test(EditHealthProducts::class, ['record' => $assessment->id])
            ->fillForm(['commodities' => [$dept->id => [$commodity->id => 1]]])
            ->call('saveDepartmentTab', $dept->id);
    }

    public function test_section_stays_pending_while_another_department_is_unanswered(): void
    {
        [$assessment, $skillsLab, $nbu, $itemA] = $this->makeScenario('PENDING');

        $this->saveDepartment($assessment, $skillsLab, $itemA);

        $progress = $assessment->fresh()->section_progress ?? [];

        $this->assertFalse(
            $progress['department_health_products_PENDING'] ?? true,
            'Saving one department must not mark the whole matrix complete while NBU is unanswered.'
        );
        $this->assertFalse($progress['health_products_PENDING'] ?? true);
    }

    public function test_every_commodity_matrix_section_completes_once_all_departments_are_answered(): void
    {
        [$assessment, $skillsLab, $nbu, $itemA, $itemB] = $this->makeScenario('DONE');

        $this->saveDepartment($assessment, $skillsLab, $itemA);
        $this->saveDepartment($assessment, $nbu, $itemB);

        $progress = $assessment->fresh()->section_progress ?? [];

        // Both sections — including the one EditHealthProducts didn't resolve
        // — must read complete, which is what the dashboard renders from.
        $this->assertTrue($progress['department_health_products_DONE'] ?? false);
        $this->assertTrue($progress['health_products_DONE'] ?? false);
    }

    public function test_saving_advances_to_the_next_unanswered_department(): void
    {
        [$assessment, $skillsLab, $nbu, $itemA] = $this->makeScenario('NEXTDEPT');

        $this->saveDepartment($assessment, $skillsLab, $itemA)
            ->assertRedirect(
                AssessmentResource::getUrl('edit-health-products', ['record' => $assessment->id])."?dept={$nbu->slug}"
            );
    }

    public function test_saving_the_last_unanswered_department_advances_to_the_next_section(): void
    {
        [$assessment, $skillsLab, $nbu, $itemA, $itemB, $trailing] = $this->makeScenario('NEXTSECT');

        $this->saveDepartment($assessment, $nbu, $itemB);

        // NBU is last in tab order, but Skills lab is still unanswered — the
        // redirect must wrap back to it rather than leaving it behind.
        $this->saveDepartment($assessment, $nbu, $itemB)
            ->assertRedirect(
                AssessmentResource::getUrl('edit-health-products', ['record' => $assessment->id])."?dept={$skillsLab->slug}"
            );

        // With every department answered, the matrix is done and the next
        // top-level section takes over.
        $this->saveDepartment($assessment, $skillsLab, $itemA)
            ->assertRedirect(AssessmentResource::getUrl('edit-section', [
                'record' => $assessment->id,
                'sectionCode' => $trailing->code,
            ]));
    }
}
