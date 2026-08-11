<?php

namespace Tests\Feature;

use App\Models\AssessmentQuestion;
use App\Models\AssessmentSection;
use App\Models\AssessmentType;
use App\Models\Cadre;
use App\Services\CadreMatrixSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CadreMatrixSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeSection(): AssessmentSection
    {
        $type = AssessmentType::create(['name' => 'Cadre Sync Test', 'code' => 'CADRE_SYNC_TEST', 'is_active' => true]);

        return AssessmentSection::create([
            'assessment_type_id' => $type->id,
            'name' => 'Facility Context',
            'code' => 'cadre_sync_section_test',
            'section_type' => AssessmentSection::KIND_QUESTION_GROUP,
            'is_scored' => false,
            'order' => 1,
            'is_active' => true,
        ]);
    }

    public function test_sync_materializes_3_questions_per_active_category_cadre(): void
    {
        $section = $this->makeSection();
        $nurses = Cadre::create(['name' => 'Sync Nurses', 'code' => 'sync_nurses', 'category' => 'sync_test', 'is_active' => true, 'order' => 1]);
        $doctors = Cadre::create(['name' => 'Sync Doctors', 'code' => 'sync_doctors', 'category' => 'sync_test', 'is_active' => true, 'order' => 2]);
        Cadre::create(['name' => 'Sync Other Category', 'code' => 'sync_other', 'category' => 'not_sync_test', 'is_active' => true, 'order' => 3]);

        app(CadreMatrixSyncService::class)->sync($section, 'sync_test', 'SYNC_TEST_CADRE');

        $questions = AssessmentQuestion::where('question_code', 'like', 'SYNC_TEST_CADRE%')->get();

        $this->assertCount(6, $questions); // 2 cadres x 3 metrics
        $this->assertSame(3, $questions->where('group', 'Sync Nurses')->count());
        $this->assertSame(3, $questions->where('group', 'Sync Doctors')->count());
        $this->assertTrue($questions->every(fn ($q) => $q->is_active && ! $q->is_scored));
    }

    public function test_sync_is_idempotent_and_does_not_duplicate(): void
    {
        $section = $this->makeSection();
        Cadre::create(['name' => 'Idempotent Cadre', 'code' => 'idempotent_cadre', 'category' => 'idempotent_test', 'is_active' => true, 'order' => 1]);

        $service = app(CadreMatrixSyncService::class);
        $service->sync($section, 'idempotent_test', 'IDEMPOTENT_TEST_CADRE');
        $countBefore = AssessmentQuestion::where('question_code', 'like', 'IDEMPOTENT_TEST_CADRE%')->count();

        $service->sync($section, 'idempotent_test', 'IDEMPOTENT_TEST_CADRE');
        $countAfter = AssessmentQuestion::where('question_code', 'like', 'IDEMPOTENT_TEST_CADRE%')->count();

        $this->assertSame(3, $countBefore);
        $this->assertSame($countBefore, $countAfter);
    }

    public function test_sync_deactivates_questions_for_a_cadre_that_becomes_inactive(): void
    {
        $section = $this->makeSection();
        $cadre = Cadre::create(['name' => 'Retiring Cadre', 'code' => 'retiring_cadre', 'category' => 'retire_test', 'is_active' => true, 'order' => 1]);

        $service = app(CadreMatrixSyncService::class);
        $service->sync($section, 'retire_test', 'RETIRE_TEST_CADRE');
        $this->assertSame(3, AssessmentQuestion::where('question_code', 'like', 'RETIRE_TEST_CADRE%')->where('is_active', true)->count());

        $cadre->update(['is_active' => false]);
        $service->sync($section, 'retire_test', 'RETIRE_TEST_CADRE');

        // Rows are deactivated, not deleted -- preserves any historical
        // responses already recorded against them.
        $this->assertSame(0, AssessmentQuestion::where('question_code', 'like', 'RETIRE_TEST_CADRE%')->where('is_active', true)->count());
        $this->assertSame(3, AssessmentQuestion::where('question_code', 'like', 'RETIRE_TEST_CADRE%')->count());
    }

    public function test_sync_picks_up_a_newly_added_cadre_on_a_later_call(): void
    {
        $section = $this->makeSection();
        Cadre::create(['name' => 'First Cadre', 'code' => 'first_cadre', 'category' => 'grow_test', 'is_active' => true, 'order' => 1]);

        $service = app(CadreMatrixSyncService::class);
        $service->sync($section, 'grow_test', 'GROW_TEST_CADRE');
        $this->assertSame(3, AssessmentQuestion::where('question_code', 'like', 'GROW_TEST_CADRE%')->count());

        Cadre::create(['name' => 'Second Cadre', 'code' => 'second_cadre', 'category' => 'grow_test', 'is_active' => true, 'order' => 2]);
        $service->sync($section, 'grow_test', 'GROW_TEST_CADRE');

        $this->assertSame(6, AssessmentQuestion::where('question_code', 'like', 'GROW_TEST_CADRE%')->count());
    }
}
