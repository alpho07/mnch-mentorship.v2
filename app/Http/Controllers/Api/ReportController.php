<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendAssessmentReportEmail;
use App\Models\Assessment;
use App\Models\AssessmentEmailJob;
use App\Models\AssessmentSection;
use App\Services\AssessmentPdfReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ReportController extends Controller {

    /** Sections that are informational only — never shown in the mobile report. */
    private const EXCLUDED_FROM_REPORT = ['facility_profile', 'bed_capacity'];

    public function __construct(
            private readonly AssessmentPdfReportService $pdfService
    ) {
        
    }

    public function show(Request $request, Assessment $assessment): JsonResponse {
        $this->authorize('view', $assessment);

        $assessment->load([
            'facility.subcounty.county',
            'sectionScores.section',
            'questionResponses.question.section',
        ]);

        $sections = AssessmentSection::active()
                ->ordered()
                ->whereNotIn('code', self::EXCLUDED_FROM_REPORT)
                ->get();

        $sectionScores = $assessment->sectionScores->keyBy('section.code');

        $sectionReports = $sections->map(function ($section) use ($assessment, $sectionScores) {
                    $score = $sectionScores->get($section->code);
                    $responses = $assessment->questionResponses
                                    ->filter(fn($r) => $r->question->assessment_section_id === $section->id)
                                    ->map(fn($r) => [
                                        'question_code' => $r->question->question_code,
                                        'question_text' => $r->question->question_text,
                                        'question_type' => $r->question->question_type,
                                        'response' => $r->response_value,
                                        'explanation' => $r->explanation,
                                        'score' => $r->score,
                                            ])->values();

                    return [
                        'code' => $section->code,
                        'name' => $section->name,
                        'icon' => $section->icon,
                        'color' => $section->color,
                        'percentage' => $score?->percentage ?? 0,
                        'grade' => $score?->grade,
                        'total_questions' => $score?->total_questions ?? 0,
                        'answered_questions' => $score?->answered_questions ?? 0,
                        'responses' => $responses,
                    ];
                })->values();

        return response()->json([
                    'assessment' => [
                        'id' => $assessment->id,
                        'facility_name' => $assessment->facility->name,
                        'mfl_code' => $assessment->facility->mfl_code,
                        'county' => $assessment->facility->subcounty->county->name ?? null,
                        'subcounty' => $assessment->facility->subcounty->name ?? null,
                        'assessment_type' => $assessment->assessment_type,
                        'assessment_date' => $assessment->assessment_date,
                        'assessor_name' => $assessment->assessor_name,
                        'assessor_contact' => $assessment->assessor_contact,
                        'status' => $assessment->status,
                        'completed_at' => $assessment->completed_at,
                        'overall_percentage' => $assessment->overall_percentage,
                        'overall_grade' => $assessment->overall_grade,
                    ],
                    'section_reports' => $sectionReports,
        ]);
    }

    public function summary(Request $request, Assessment $assessment): JsonResponse {
        $this->authorize('view', $assessment);

        $assessment->load(['facility', 'sectionScores.section']);

        return response()->json([
                    'overall' => [
                        'percentage' => $assessment->overall_percentage,
                        'grade' => $assessment->overall_grade,
                    ],
                    'sections' => $assessment->sectionScores
                            ->filter(fn($s) => !in_array($s->section->code, self::EXCLUDED_FROM_REPORT))
                            ->map(fn($s) => [
                                'code' => $s->section->code,
                                'name' => $s->section->name,
                                'percentage' => $s->percentage,
                                'grade' => $s->grade,
                                'answered' => $s->answered_questions,
                                'total' => $s->total_questions,
                                    ])->values(),
        ]);
    }

    public function downloadPdf(Request $request, Assessment $assessment): JsonResponse {
        $this->authorize('view', $assessment);

        if ($assessment->status !== 'completed') {
            return response()->json(['message' => 'PDF only available for completed assessments.'], 422);
        }

        $pdf = $this->pdfService->generateExecutiveReport($assessment);

        $safeName = preg_replace('/[^a-zA-Z0-9\-_]/', '-', $assessment->facility->name ?? 'report');
        $date     = $assessment->assessment_date instanceof \Carbon\Carbon
            ? $assessment->assessment_date->format('Y-m-d')
            : (string) $assessment->assessment_date;

        $filename = "MNCH-Assessment-{$safeName}-{$date}.pdf";
        $path     = "reports/{$filename}";

        Storage::disk('public')->put($path, $pdf->output());

        return response()->json([
            'download_url' => Storage::disk('public')->url($path),
            'filename'     => $filename,
        ]);
    }

    public function emailReport(Request $request, Assessment $assessment): JsonResponse {
        $this->authorize('view', $assessment);

        if ($assessment->status !== 'completed') {
            return response()->json(['message' => 'Email report only available for completed assessments.'], 422);
        }

        $validated = $request->validate([
            'emails'   => 'required|array|min:1|max:10',
            'emails.*' => 'required|email',
        ]);

        $emailJob = AssessmentEmailJob::create([
            'assessment_id' => $assessment->id,
            'user_id'       => $request->user()->id,
            'emails'        => $validated['emails'],
            'status'        => 'queued',
        ]);

        SendAssessmentReportEmail::dispatch($emailJob);

        return response()->json([
            'id'         => $emailJob->id,
            'status'     => 'queued',
            'emails'     => $emailJob->emails,
            'queued_at'  => $emailJob->created_at->toIso8601String(),
        ], 201);
    }

    public function emailJobStatus(Request $request, Assessment $assessment, AssessmentEmailJob $emailJob): JsonResponse {
        $this->authorize('view', $assessment);

        if ($emailJob->user_id !== $request->user()->id || $emailJob->assessment_id !== $assessment->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        return response()->json([
            'id'         => $emailJob->id,
            'status'     => $emailJob->status,
            'emails'     => $emailJob->emails,
            'error'      => $emailJob->error,
            'sent_at'    => $emailJob->sent_at?->toIso8601String(),
            'created_at' => $emailJob->created_at->toIso8601String(),
        ]);
    }

    public function emailJobs(Request $request): JsonResponse {
        $jobs = AssessmentEmailJob::where('user_id', $request->user()->id)
            ->with('assessment:id,assessment_date,assessment_type,facility_id')
            ->latest()
            ->take(100)
            ->get()
            ->map(fn ($j) => [
                'id'              => $j->id,
                'assessment_id'   => $j->assessment_id,
                'facility_name'   => $j->assessment->facility->name ?? null,
                'assessment_date' => $j->assessment->assessment_date instanceof \Carbon\Carbon
                    ? $j->assessment->assessment_date->format('Y-m-d')
                    : (string) ($j->assessment->assessment_date ?? ''),
                'assessment_type' => $j->assessment->assessment_type ?? null,
                'emails'          => $j->emails,
                'status'          => $j->status,
                'error'           => $j->error,
                'sent_at'         => $j->sent_at?->toIso8601String(),
                'created_at'      => $j->created_at->toIso8601String(),
            ]);

        return response()->json(['data' => $jobs]);
    }

    public function dashboard(Request $request): JsonResponse {
        $userId = $request->user()->id;
        $assessments = Assessment::where('assessor_id', $userId)
                ->with('sectionScores.section')
                ->get();

        $completed = $assessments->where('status', 'completed');
        $avgScore = $completed->count() ? round($completed->avg('overall_percentage'), 1) : 0;

        $sections = AssessmentSection::active()->ordered()
                ->whereNotIn('code', self::EXCLUDED_FROM_REPORT)
                ->get();

        $sectionAvgs = $sections->map(function ($section) use ($completed) {
                    $scores = $completed->flatMap(
                            fn($a) => $a->sectionScores->where('section.code', $section->code)->pluck('percentage')
                    );
                    return [
                        'code' => $section->code,
                        'name' => $section->name,
                        'icon' => $section->icon,
                        'color' => $section->color,
                        'average_pct' => $scores->count() ? round($scores->avg(), 1) : 0,
                    ];
                })->values();

        return response()->json([
                    'summary' => [
                        'total_assessments' => $assessments->count(),
                        'completed' => $completed->count(),
                        'in_progress' => $assessments->where('status', 'in_progress')->count(),
                        'average_score' => $avgScore,
                    ],
                    'grade_distribution' => [
                        'green' => $completed->where('overall_grade', 'green')->count(),
                        'yellow' => $completed->where('overall_grade', 'yellow')->count(),
                        'red' => $completed->where('overall_grade', 'red')->count(),
                    ],
                    'section_averages' => $sectionAvgs,
                    'recent_assessments' => $completed->sortByDesc('completed_at')->take(5)->map(fn($a) => [
                        'id' => $a->id,
                        'facility_name' => $a->facility->name ?? null,
                        'assessment_type' => $a->assessment_type,
                        'assessment_date' => $a->assessment_date,
                        'overall_percentage' => $a->overall_percentage,
                        'overall_grade' => $a->overall_grade,
                            ])->values(),
        ]);
    }

    public function sectionAverages(Request $request): JsonResponse {
        $userId = $request->user()->id;
        $completed = Assessment::where('assessor_id', $userId)
                ->where('status', 'completed')
                ->with('sectionScores.section')
                ->get();

        $sections = AssessmentSection::active()->ordered()
                ->whereNotIn('code', self::EXCLUDED_FROM_REPORT)
                ->get();

        return response()->json([
                    'data' => $sections->map(function ($section) use ($completed) {
                        $scores = $completed->flatMap(
                                fn($a) => $a->sectionScores->where('section.code', $section->code)->pluck('percentage')
                        );
                        return [
                            'code' => $section->code,
                            'name' => $section->name,
                            'icon' => $section->icon,
                            'average_pct' => $scores->count() ? round($scores->avg(), 1) : 0,
                            'min_pct' => $scores->count() ? round($scores->min(), 1) : 0,
                            'max_pct' => $scores->count() ? round($scores->max(), 1) : 0,
                            'total_count' => $scores->count(),
                        ];
                    })->values(),
        ]);
    }
}
