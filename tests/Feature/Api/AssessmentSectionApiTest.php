<?php

namespace Tests\Feature\Api;

use App\Models\AssessmentQuestion;
use App\Models\AssessmentSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentSectionApiTest extends TestCase
{
    use RefreshDatabase;

    private function tokenFor(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    public function test_index_lists_active_sections_with_a_question_count(): void
    {
        $user = User::factory()->create();
        $section = AssessmentSection::create([
            'name' => 'Infrastructure',
            'code' => 'infrastructure',
            'section_type' => 'dynamic_questions',
            'is_scored' => true,
            'order' => 1,
            'is_active' => true,
        ]);
        AssessmentQuestion::create([
            'assessment_section_id' => $section->id,
            'question_code' => 'Q1',
            'question_text' => 'Is water available?',
            'question_type' => 'yes_no',
            'order' => 1,
            'is_active' => true,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenFor($user))
            ->getJson('/api/v1/sections');

        $response->assertSuccessful();
        $response->assertJsonPath('data.0.code', 'infrastructure');
        $response->assertJsonPath('data.0.questions_count', 1);
    }

    public function test_show_returns_the_section_with_its_full_question_list(): void
    {
        $user = User::factory()->create();
        $section = AssessmentSection::create([
            'name' => 'Infrastructure',
            'code' => 'infrastructure',
            'section_type' => 'dynamic_questions',
            'is_scored' => true,
            'order' => 1,
            'is_active' => true,
        ]);
        AssessmentQuestion::create([
            'assessment_section_id' => $section->id,
            'question_code' => 'Q1',
            'question_text' => 'Is water available?',
            'question_type' => 'yes_no',
            'order' => 1,
            'is_active' => true,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenFor($user))
            ->getJson('/api/v1/sections/' . $section->id);

        $response->assertSuccessful();
        $response->assertJsonPath('data.code', 'infrastructure');
        $response->assertJsonPath('data.questions.0.question_code', 'Q1');
    }
}
