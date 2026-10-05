<?php

namespace Tests\Feature\Api;

use App\Models\Assessment;
use App\Models\AssessmentDepartment;
use App\Models\AssessmentSection;
use App\Models\AssessmentType;
use App\Models\Commodity;
use App\Models\CommodityCategory;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * The mobile save path derives commodity-matrix completion the same way the
 * admin panel does. It used to write a hardcoded `health_products` key on any
 * all-departments POST: a literal that belongs to no section on a template
 * naming its matrix differently, that skipped the sibling matrix section on a
 * template carrying two, and that claimed completion even when most
 * departments were still unanswered.
 */
class HealthProductsCompletionApiTest extends TestCase
{
    use RefreshDatabase;

    private function authUser(): array
    {
        $user = User::factory()->create(['name' => 'Matrix Api Assessor']);
        Permission::firstOrCreate(['name' => 'update_assessment', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view_assessment', 'guard_name' => 'web']);
        $user->givePermissionTo(['update_assessment', 'view_assessment']);

        return [$user, $user->createToken('test')->plainTextToken];
    }

    /**
     * Template with two commodity_matrix sections and two departments, one
     * commodity each.
     */
    private function makeScenario(string $suffix): array
    {
        $type = AssessmentType::create([
            'name' => "API Matrix {$suffix}", 'code' => "API_MATRIX_{$suffix}", 'is_active' => true,
        ]);

        AssessmentSection::create([
            'assessment_type_id' => $type->id, 'name' => 'Department Commodities',
            'code' => "department_health_products_{$suffix}",
            'section_type' => AssessmentSection::KIND_COMMODITY_MATRIX, 'order' => 1, 'is_active' => true,
        ]);
        AssessmentSection::create([
            'assessment_type_id' => $type->id, 'name' => 'Health Products and Technologies',
            'code' => "health_products_{$suffix}",
            'section_type' => AssessmentSection::KIND_COMMODITY_MATRIX, 'order' => 2, 'is_active' => true,
        ]);

        $skillsLab = AssessmentDepartment::create([
            'assessment_type_id' => $type->id, 'name' => 'Skills lab',
            'slug' => "api-skills-lab-{$suffix}", 'is_active' => true, 'order' => 1,
        ]);
        $nbu = AssessmentDepartment::create([
            'assessment_type_id' => $type->id, 'name' => 'NBU',
            'slug' => "api-nbu-{$suffix}", 'is_active' => true, 'order' => 2,
        ]);

        $category = CommodityCategory::create([
            'assessment_type_id' => $type->id, 'name' => 'AIRWAY', 'slug' => "api-airway-{$suffix}", 'order' => 1,
        ]);
        $itemA = Commodity::create(['commodity_category_id' => $category->id, 'name' => 'Item A', 'order' => 1, 'is_active' => true]);
        $itemB = Commodity::create(['commodity_category_id' => $category->id, 'name' => 'Item B', 'order' => 2, 'is_active' => true]);
        $itemA->applicableDepartments()->attach($skillsLab->id);
        $itemB->applicableDepartments()->attach($nbu->id);

        [$user, $token] = $this->authUser();

        $assessment = Assessment::create([
            'facility_id' => Facility::factory()->create()->id, 'assessment_type_id' => $type->id,
            'round' => 'baseline', 'assessment_date' => now(),
            'assessor_id' => $user->id, 'assessor_name' => $user->name,
        ]);

        return [$assessment, $token, $skillsLab, $nbu, $itemA, $itemB];
    }

    private function postProducts(string $token, Assessment $assessment, array $responses, ?int $departmentId = null)
    {
        $body = ['responses' => $responses];

        if ($departmentId !== null) {
            $body['department_id'] = $departmentId;
        }

        return $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/assessments/{$assessment->id}/health-products", $body);
    }

    public function test_an_all_departments_post_that_is_actually_partial_does_not_complete_the_section(): void
    {
        [$assessment, $token, $skillsLab, $nbu, $itemA] = $this->makeScenario('PARTIAL');

        // No department_id — the old code took this as "all departments" and
        // unconditionally marked the section complete, though NBU is untouched.
        $this->postProducts($token, $assessment, [
            ['department_id' => $skillsLab->id, 'commodity_id' => $itemA->id, 'available' => true],
        ])->assertSuccessful();

        $progress = $assessment->fresh()->section_progress ?? [];

        $this->assertFalse($progress['department_health_products_PARTIAL'] ?? true);
        $this->assertFalse($progress['health_products_PARTIAL'] ?? true);
        $this->assertArrayNotHasKey(
            'health_products',
            $progress,
            'The hardcoded literal key must no longer be written.'
        );
    }

    public function test_every_matrix_section_completes_once_all_departments_are_answered(): void
    {
        [$assessment, $token, $skillsLab, $nbu, $itemA, $itemB] = $this->makeScenario('FULL');

        $this->postProducts($token, $assessment, [
            ['department_id' => $skillsLab->id, 'commodity_id' => $itemA->id, 'available' => true],
            ['department_id' => $nbu->id, 'commodity_id' => $itemB->id, 'available' => false],
        ])->assertSuccessful();

        $progress = $assessment->fresh()->section_progress ?? [];

        $this->assertTrue($progress['department_health_products_FULL'] ?? false);
        $this->assertTrue($progress['health_products_FULL'] ?? false);
    }

    public function test_a_save_and_next_post_completes_the_section_when_it_finishes_the_matrix(): void
    {
        [$assessment, $token, $skillsLab, $nbu, $itemA, $itemB] = $this->makeScenario('SAVENEXT');

        // Per-department saves: the old code skipped the completion write
        // entirely whenever department_id was present, so a matrix finished
        // via Save & Next stayed pending forever.
        $this->postProducts($token, $assessment, [
            ['department_id' => $skillsLab->id, 'commodity_id' => $itemA->id, 'available' => true],
        ], $skillsLab->id)->assertSuccessful();

        $this->assertFalse($assessment->fresh()->section_progress['health_products_SAVENEXT'] ?? true);

        $this->postProducts($token, $assessment, [
            ['department_id' => $nbu->id, 'commodity_id' => $itemB->id, 'available' => true],
        ], $nbu->id)->assertSuccessful();

        $progress = $assessment->fresh()->section_progress ?? [];

        $this->assertTrue($progress['department_health_products_SAVENEXT'] ?? false);
        $this->assertTrue($progress['health_products_SAVENEXT'] ?? false);
    }
}
