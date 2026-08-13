<?php

namespace Tests\Feature;

use App\Filament\Resources\AssessmentResource;
use App\Models\Assessment;
use App\Models\AssessmentQuestion;
use App\Models\AssessmentSection;
use App\Models\AssessmentType;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AssessmentLineItemQuestionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_split_line_item_questions_render_lettered_and_indented(): void
    {
        $user = User::factory()->create(['name' => 'Line Item Assessor']);
        Role::firstOrCreate(['name' => 'assessor', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view_any_assessment', 'guard_name' => 'web']);
        $user->givePermissionTo('view_any_assessment');
        $user->assignRole('assessor');
        $this->actingAs($user);

        $type = AssessmentType::create(['name' => 'Line Item Test', 'code' => 'LINE_ITEM_TEST', 'is_active' => true]);
        $section = AssessmentSection::create([
            'assessment_type_id' => $type->id, 'name' => 'Infrastructure', 'code' => 'infrastructure_li',
            'section_type' => AssessmentSection::KIND_QUESTION_GROUP, 'order' => 1, 'is_active' => true,
        ]);

        foreach ([['Fr-6', 1], ['Fr-8', 2], ['Fr-10', 3]] as [$size, $order]) {
            AssessmentQuestion::create([
                'assessment_section_id' => $section->id,
                'question_code' => 'SUCTION_'.strtoupper(str_replace('-', '_', $size)),
                'question_text' => $size,
                'question_type' => 'yes_no',
                'group' => 'Suction catheter sizes',
                'indent_level' => 1,
                'order' => $order,
                'is_active' => true,
            ]);
        }

        $facility = Facility::factory()->create();
        $assessment = Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type->id,
            'assessment_type' => 'baseline', 'assessment_date' => now(), 'assessor_id' => $user->id,
        ]);

        $url = AssessmentResource::getUrl('edit-section', ['record' => $assessment->id, 'sectionCode' => $section->code]);
        $response = $this->get($url);

        $response->assertOk();
        $response->assertSee('Suction catheter sizes');
        $response->assertSee('a) Fr-6', false);
        $response->assertSee('b) Fr-8', false);
        $response->assertSee('c) Fr-10', false);
    }
}
