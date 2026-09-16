<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\BulkStoreResponsesRequest;
use App\Models\Assessment;
use App\Models\AssessmentQuestion;
use App\Models\AssessmentQuestionResponse;
use App\Models\AssessmentSection;
use App\Services\DynamicScoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AssessmentResponseController extends Controller {

    public function __construct(
            private readonly DynamicScoringService $scoringService
    ) {
        
    }

    // =========================================================================
    // GET /api/v1/assessments/{assessment}/responses
    //
    // Returns all saved responses for this assessment, keyed by question_code.
    // Response shape:
    // {
    //   "responses":    { "INFRA_001": "Yes", "INFRA_002": "No", ... },
    //   "explanations": { "INFRA_002": "Water supply intermittent", ... },
    //   "section_progress": { "infrastructure": { "answered": 12, "total": 15 }, ... }
    // }
    // =========================================================================
    public function index(Request $request, Assessment $assessment): JsonResponse {
        $this->authorize('view', $assessment);

        // Load saved responses with question (to get question_code)
        $rawResponses = AssessmentQuestionResponse::where('assessment_id', $assessment->id)
                ->with('question:id,question_code,assessment_section_id')
                ->get();

        $responses = [];
        $explanations = [];

        foreach ($rawResponses as $r) {
            if (!$r->question)
                continue;
            $code = $r->question->question_code;
            if ($r->response_value !== null && $r->response_value !== '') {
                $responses[$code] = $r->response_value;
            }
            if ($r->explanation !== null && $r->explanation !== '') {
                $explanations[$code] = $r->explanation;
            }
        }

        // Per-section answered/total for progress rings
        $sections = AssessmentSection::active()
                ->ordered()
                ->with(['questions' => fn($q) => $q->active()->ordered()->select('id', 'assessment_section_id', 'question_code')])
                ->get(['id', 'code']);

        $sectionProgress = [];
        foreach ($sections as $section) {
            $codes = $section->questions->pluck('question_code')->all();
            $total = count($codes);
            $answered = count(array_filter($codes, fn($c) => isset($responses[$c]) && $responses[$c] !== ''));
            $sectionProgress[$section->code] = compact('answered', 'total');
        }

        return response()->json(compact('responses', 'explanations', 'sectionProgress'));
    }

    // =========================================================================
    // POST /api/v1/assessments/{assessment}/responses
    //
    // Bulk-saves all responses for a single section, calculates scores, and
    // triggers section rescoring. Busts any relevant caches on success.
    // =========================================================================
    public function bulkStore(BulkStoreResponsesRequest $request, Assessment $assessment): JsonResponse {
        $this->authorize('update', $assessment);

        if ($assessment->status === 'completed') {
            return response()->json(['message' => 'Completed assessments cannot be modified.'], 403);
        }

        $sectionCode = $request->input('section_code');
        $responses = $request->input('responses', []);
        $explanations = $request->input('explanations', []);

        $section = AssessmentSection::where('code', $sectionCode)->where('is_active', true)->first();

        if (!$section) {
            return response()->json(['message' => "Section '{$sectionCode}' not found or inactive."], 422);
        }

        $questions = AssessmentQuestion::where('assessment_section_id', $section->id)
                ->where('is_active', true)
                ->get()
                ->keyBy('question_code');

        $saved = 0;
        $skipped = [];

        DB::transaction(function () use ($assessment, $questions, $responses, $explanations, &$saved, &$skipped) {
            foreach ($responses as $questionCode => $responseValue) {
                $question = $questions->get($questionCode);
                if (!$question) {
                    $skipped[] = $questionCode;
                    continue;
                }

                // Score from scoring_map
                $score = null;
                if ($question->is_scored && is_array($question->scoring_map) && $responseValue !== null && $responseValue !== '') {
                    $score = $question->scoring_map[$responseValue] ?? 0;
                }

                $explanation = $explanations[$questionCode] ?? null;

                AssessmentQuestionResponse::updateOrCreate(
                        ['assessment_id' => $assessment->id, 'assessment_question_id' => $question->id],
                        [
                            'response_value' => ($responseValue !== '' ? $responseValue : null),
                            'explanation' => ($explanation !== '' ? $explanation : null),
                            'score' => $score,
                        ]
                );
                $saved++;
            }
        });

        // Rescore section
        DynamicScoringService::recalculateSectionScore($assessment->id, $section->id);

        // Bust assessment-level caches so reports/dashboard reflect new data immediately
        Cache::forget("assessment.{$assessment->id}.summary");
        Cache::forget("assessment.{$assessment->id}.report");

        // Completion summary for this section
        $questionIds = $questions->pluck('id');
        $answeredCount = AssessmentQuestionResponse::where('assessment_id', $assessment->id)
                ->whereIn('assessment_question_id', $questionIds)
                ->whereNotNull('response_value')
                ->where('response_value', '!=', '')
                ->count();

        $totalQuestions = $questions->count();

        return response()->json([
                    'message' => "Responses saved for section '{$sectionCode}'.",
                    'saved' => $saved,
                    'skipped' => $skipped,
                    'section_completion' => [
                        'section_code' => $sectionCode,
                        'answered' => $answeredCount,
                        'total' => $totalQuestions,
                        'percentage' => $totalQuestions > 0 ? round(($answeredCount / $totalQuestions) * 100, 1) : 0,
                    ],
        ]);
    }

    // =========================================================================
    // GET /api/v1/assessments/{assessment}/responses/{questionCode}
    // =========================================================================
    public function show(Request $request, Assessment $assessment, string $questionCode): JsonResponse {
        $this->authorize('view', $assessment);

        $question = AssessmentQuestion::where('question_code', $questionCode)->where('is_active', true)->first();

        if (!$question) {
            return response()->json(['message' => "Question '{$questionCode}' not found."], 404);
        }

        $response = AssessmentQuestionResponse::where('assessment_id', $assessment->id)
                ->where('assessment_question_id', $question->id)
                ->first();

        return response()->json([
                    'question_code' => $questionCode,
                    'response_value' => $response?->response_value,
                    'explanation' => $response?->explanation,
                    'score' => $response?->score,
                    'updated_at' => $response?->updated_at?->toIso8601String(),
        ]);
    }
}
