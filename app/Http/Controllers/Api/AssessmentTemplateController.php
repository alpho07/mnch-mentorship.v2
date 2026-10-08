<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\AssessmentType;
use App\Services\AssessmentSchemaService;
use Illuminate\Http\JsonResponse;

/**
 * Assessment templates for the mobile app: what can be started (active
 * templates) and the schema for any template an assessment already uses.
 */
class AssessmentTemplateController extends Controller
{
    public const ROUNDS = [
        'baseline' => 'Baseline',
        'midline' => 'Midline',
        'endline' => 'Endline',
        'other' => 'Other',
    ];

    public function __construct(private readonly AssessmentSchemaService $schemas) {}

    /**
     * GET /api/v1/assessment-templates
     * Active templates that can be started, with their rounds.
     */
    public function index(): JsonResponse
    {
        $templates = AssessmentType::active()->with('category')->orderBy('name')->get()
            ->map(fn (AssessmentType $t) => $this->schemas->templateSummary($t) + [
                'sections_count' => $t->sections()->active()->count(),
            ])->values();

        return response()->json([
            'data' => $templates,
            'rounds' => collect(self::ROUNDS)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
            'default_template_id' => $this->schemas->defaultTemplate()?->id,
        ]);
    }

    /**
     * GET /api/v1/assessment-templates/{id}
     * Full schema for one template. Includes retired templates (withTrashed)
     * so older assessments stay openable.
     */
    public function show(int $id): JsonResponse
    {
        $type = AssessmentType::withTrashed()->with('category')->findOrFail($id);

        return response()->json([
            'template' => $this->schemas->templateSummary($type),
            'data' => $this->schemas->forTemplate($type),
        ]);
    }

    /**
     * GET /api/v1/assessments/{assessment}/schema
     * The schema of the template THIS assessment was started on.
     */
    public function forAssessment(Assessment $assessment): JsonResponse
    {
        $this->authorize('view', $assessment);

        $payload = $this->schemas->forAssessment($assessment);

        abort_if($payload === null, 404, 'This assessment has no template.');

        return response()->json($payload);
    }
}
