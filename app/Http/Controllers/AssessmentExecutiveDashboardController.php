<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Services\AssessmentComparisonService;
use App\Services\AssessmentPdfReportService;
use App\Services\ConditionalLogicEvaluator;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;

class AssessmentExecutiveDashboardController extends Controller
{
    /**
     * A total/answered count of exactly this value is a known junk
     * sentinel from bad data entry, not a real question count.
     */
    private const JUNK_COUNT_SENTINEL = 1111;

    public function __construct(private AssessmentComparisonService $comparisonService)
    {
    }

    public function show(Assessment $assessment)
    {
        $data = $this->buildDashboardData($assessment);
        $data['comparison'] = $this->comparisonService->prepareComparisonData($assessment);

        return view('analytics.assessment-executive.dashboard', $data);
    }

    public function export(Assessment $assessment)
    {
        $data = $this->buildDashboardData($assessment);
        $data['isPdf'] = true;

        $pdf = Pdf::loadView('pdf.assessment-executive-dashboard', $data)
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'defaultFont' => 'DejaVu Sans',
            ]);

        $filename = 'executive-assessment-'.$assessment->id.'-'.now()->format('Ymd').'.pdf';

        return $pdf->download($filename);
    }

    public function buildDashboardData(Assessment $assessment): array
    {
        $assessment->load([
            'facility.facilityLevel',
            'facility.facilityType',
            'facility.subcounty.county',
        ]);

        $aId = $assessment->id;

        // Resolves a question's display_conditions against every response
        // on this assessment (not just whichever section is being pulled
        // below) — a condition can reference a question in a different
        // section, as INFOSYS_DOC_TYPE does for both the EMR questions and
        // the paper-register table further down.
        $responsesByCode = DB::table('assessment_question_responses')
            ->join('assessment_questions', 'assessment_questions.id', '=', 'assessment_question_responses.assessment_question_id')
            ->where('assessment_question_responses.assessment_id', $aId)
            ->pluck('assessment_question_responses.response_value', 'assessment_questions.question_code')
            ->all();

        // ── Section scores ────────────────────────────────────────────────────
        $sectionScores = DB::table('assessment_section_scores')
            ->join('assessment_sections', 'assessment_sections.id', '=', 'assessment_section_scores.assessment_section_id')
            ->where('assessment_section_scores.assessment_id', $aId)
            ->select(
                'assessment_sections.code',
                'assessment_sections.name',
                'assessment_section_scores.total_score',
                'assessment_section_scores.max_score',
                'assessment_section_scores.percentage',
                'assessment_section_scores.grade',
                'assessment_section_scores.total_questions',
                'assessment_section_scores.answered_questions',
            )
            ->get()
            ->keyBy('code')
            ->map(function ($ss) {
                // 1111 is a known junk sentinel from bad data entry, not a
                // real question count — scrubbed here so every downstream
                // consumer (score-strip, per-section "X/Y" boxes, the Data
                // Quality completeness bars) sees null instead of a
                // misleading number, rather than sanitizing it separately
                // in each place it's displayed.
                if ($ss->total_questions === self::JUNK_COUNT_SENTINEL || $ss->answered_questions === self::JUNK_COUNT_SENTINEL) {
                    $ss->total_questions = null;
                    $ss->answered_questions = null;
                }

                if ($ss->code === 'quality_of_care') {
                    $ss->name = 'Quality of Care (Death Audits)';
                }

                return $ss;
            });

        // ── Infrastructure responses ──────────────────────────────────────────
        $infraResponses = DB::table('assessment_questions')
            ->join('assessment_sections', 'assessment_sections.id', '=', 'assessment_questions.assessment_section_id')
            ->leftJoin('assessment_question_responses', function ($j) use ($aId) {
                $j->on('assessment_question_responses.assessment_question_id', '=', 'assessment_questions.id')
                    ->where('assessment_question_responses.assessment_id', '=', $aId);
            })
            ->where('assessment_sections.code', 'infrastructure')
            ->where(fn ($q) => $this->inTemplateOrAnswered($q, $assessment))
            // Unanswered questions are excluded at the query itself, not just
            // filtered out of the view — they shouldn't factor into counts or
            // insights for this section either.
            ->whereNotNull('assessment_question_responses.response_value')
            ->where('assessment_question_responses.response_value', '!=', '')
            ->select(
                'assessment_questions.question_code',
                'assessment_questions.question_text',
                'assessment_questions.display_conditions',
                'assessment_question_responses.response_value',
                'assessment_question_responses.score',
            )
            ->orderBy('assessment_questions.id')
            ->get();
        $infraResponses = $this->filterVisibleRows($infraResponses, $responsesByCode);

        // ── Skills Lab ────────────────────────────────────────────────────────
        $skillsResponses = DB::table('assessment_questions')
            ->join('assessment_sections', 'assessment_sections.id', '=', 'assessment_questions.assessment_section_id')
            ->leftJoin('assessment_question_responses', function ($j) use ($aId) {
                $j->on('assessment_question_responses.assessment_question_id', '=', 'assessment_questions.id')
                    ->where('assessment_question_responses.assessment_id', '=', $aId);
            })
            ->where('assessment_sections.code', 'skills_lab')
            ->where(fn ($q) => $this->inTemplateOrAnswered($q, $assessment))
            ->select(
                'assessment_questions.question_code',
                'assessment_questions.question_text',
                'assessment_questions.is_scored',
                'assessment_questions.display_conditions',
                'assessment_question_responses.response_value',
                'assessment_question_responses.score',
            )
            ->orderBy('assessment_questions.id')
            ->get();
        $skillsResponses = $this->filterVisibleRows($skillsResponses, $responsesByCode);

        $skillsMaster = $skillsResponses->firstWhere('question_code', 'SKILLS_MASTER');
        $hasDedicatedLab = $skillsMaster && $skillsMaster->response_value === 'Yes';

        $skillsAvailable = $skillsResponses->filter(fn ($r) => $r->response_value === 'Yes');
        $skillsMissing = $skillsResponses->filter(fn ($r) => $r->is_scored && $r->response_value === 'No');

        // ── Information Systems ───────────────────────────────────────────────
        $infoResponses = DB::table('assessment_questions')
            ->join('assessment_sections', 'assessment_sections.id', '=', 'assessment_questions.assessment_section_id')
            ->leftJoin('assessment_question_responses', function ($j) use ($aId) {
                $j->on('assessment_question_responses.assessment_question_id', '=', 'assessment_questions.id')
                    ->where('assessment_question_responses.assessment_id', '=', $aId);
            })
            ->where('assessment_sections.code', 'information_systems')
            ->where(fn ($q) => $this->inTemplateOrAnswered($q, $assessment))
            // Unanswered questions are excluded at the query itself, not just
            // filtered out of the view — they shouldn't factor into counts or
            // insights for this section either.
            ->whereNotNull('assessment_question_responses.response_value')
            ->where('assessment_question_responses.response_value', '!=', '')
            ->select(
                'assessment_questions.question_code',
                'assessment_questions.question_text',
                'assessment_questions.is_scored',
                'assessment_questions.group',
                'assessment_questions.display_conditions',
                'assessment_question_responses.response_value',
                'assessment_question_responses.score',
            )
            ->orderBy('assessment_questions.id')
            ->get();
        $infoResponses = $this->filterVisibleRows($infoResponses, $responsesByCode);

        // The MoH-form Available/Completeness pairs (see
        // AssessmentPdfReportService::getInformationSystemsDetails, which
        // this mirrors) share a "Category|Type|{formName}" group and both
        // use the bare question text "Available"/"Completeness" — left in
        // the flat list above, that's dozens of identical-looking rows
        // with no indication of which form each belongs to. Pulled into
        // their own form-by-form table instead; $infoResponses below is
        // filtered down to the ungrouped questions only.
        $infoDataToolsTable = $infoResponses
            ->filter(fn ($r) => $r->group !== null)
            ->groupBy(fn ($r) => last(explode('|', $r->group)))
            ->map(function ($pair, $formName) {
                $available = $pair->first(fn ($r) => str_ends_with($r->question_code, '_AVAILABLE'));
                $complete = $pair->first(fn ($r) => str_ends_with($r->question_code, '_COMPLETE'));

                return [
                    'form' => $formName,
                    'available' => $available?->response_value ?? 'N/A',
                    'completeness' => $complete?->response_value ?? 'N/A',
                ];
            })
            ->values();

        // ── Quality of Care ───────────────────────────────────────────────────
        $qocAll = DB::table('assessment_questions')
            ->join('assessment_sections', 'assessment_sections.id', '=', 'assessment_questions.assessment_section_id')
            ->leftJoin('assessment_question_responses', function ($j) use ($aId) {
                $j->on('assessment_question_responses.assessment_question_id', '=', 'assessment_questions.id')
                    ->where('assessment_question_responses.assessment_id', '=', $aId);
            })
            ->where('assessment_sections.code', 'quality_of_care')
            ->where(fn ($q) => $this->inTemplateOrAnswered($q, $assessment))
            ->select(
                'assessment_questions.question_code',
                'assessment_questions.question_text',
                'assessment_questions.is_scored',
                'assessment_questions.display_conditions',
                'assessment_question_responses.response_value',
                'assessment_question_responses.score',
            )
            ->orderBy('assessment_questions.id')
            ->get();
        $qocAll = $this->filterVisibleRows($qocAll, $responsesByCode)->keyBy('question_code');

        // ── Human Resources ───────────────────────────────────────────────────
        // human_resource_responses.cadre_id references assessment_cadres
        // (the MainCadre model), not the unrelated `cadres` table — that
        // table only has a single "Assessor" row, so joining against it
        // silently dropped every response whose cadre_id didn't happen to
        // be 1.
        $hrRows = DB::table('human_resource_responses')
            ->join('assessment_cadres', 'assessment_cadres.id', '=', 'human_resource_responses.cadre_id')
            ->where('human_resource_responses.assessment_id', $aId)
            ->select(
                'assessment_cadres.name as cadre',
                'human_resource_responses.total_in_facility',
                'human_resource_responses.etat_plus',
                'human_resource_responses.comprehensive_newborn_care',
                'human_resource_responses.imnci',
                'human_resource_responses.type_1_diabetes',
                'human_resource_responses.essential_newborn_care',
            )
            ->orderByDesc('human_resource_responses.total_in_facility')
            ->get()
            ->map(function ($r) {
                $r->total_trained = $r->etat_plus + $r->comprehensive_newborn_care + $r->imnci + $r->type_1_diabetes + $r->essential_newborn_care;
                $r->coverage_pct = $r->total_in_facility > 0
                    ? round(min($r->total_trained / $r->total_in_facility, 1) * 100, 1)
                    : 0;

                return $r;
            });

        $totalStaff = $hrRows->sum('total_in_facility');
        $totalTrained = $hrRows->sum('total_trained');
        $hrCoverage = $totalStaff > 0 ? round(min($totalTrained / $totalStaff, 1) * 100, 1) : 0;

        // ── Health Products by department ─────────────────────────────────────
        $deptScores = DB::table('assessment_commodity_responses')
            ->join('assessment_departments', 'assessment_departments.id', '=', 'assessment_commodity_responses.assessment_department_id')
            ->where('assessment_commodity_responses.assessment_id', $aId)
            ->select(
                'assessment_departments.name as department',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(assessment_commodity_responses.available) as available'),
                DB::raw('ROUND(SUM(assessment_commodity_responses.available)/COUNT(*)*100,1) as percentage'),
            )
            ->groupBy('assessment_departments.id', 'assessment_departments.name')
            ->orderByDesc('percentage')
            ->get();

        // Health Products by category
        $categoryScores = DB::table('assessment_commodity_responses')
            ->join('commodities', 'commodities.id', '=', 'assessment_commodity_responses.commodity_id')
            ->join('commodity_categories', 'commodity_categories.id', '=', 'commodities.commodity_category_id')
            ->where('assessment_commodity_responses.assessment_id', $aId)
            ->select(
                'commodity_categories.name as category',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(assessment_commodity_responses.available) as available'),
                DB::raw('ROUND(SUM(assessment_commodity_responses.available)/COUNT(*)*100,1) as percentage'),
            )
            ->groupBy('commodity_categories.id', 'commodity_categories.name')
            ->orderByDesc('percentage')
            ->get();

        $overallCommodityPct = $deptScores->isNotEmpty()
            ? round($deptScores->avg('percentage'), 1)
            : 0;

        $commodityGaps = $this->commodityGapsByCategory($aId);

        // ── Newborn & Paediatric Indicators ───────────────────────────────────
        // Same proportions the assessment summary reports, as numbers.
        $indicatorMetrics = collect(app(AssessmentPdfReportService::class)->getIndicatorMetrics($assessment))
            ->map(function ($m) {
                $m['status'] = $this->indicatorStatus($m);
                $m['short'] = \Illuminate\Support\Str::limit(preg_replace('/^Proportion of /', '', $m['label']), 90);

                return $m;
            });
        $indicatorInsights = $this->generateIndicatorInsights($indicatorMetrics);

        // When the facility has an earlier round of the same assessment
        // (baseline → midline → endline …), show movement since then.
        $previousRoundLabel = null;
        $previous = $this->previousAssessment($assessment);
        if ($previous && $indicatorMetrics->isNotEmpty()) {
            $previousRoundLabel = $previous->round_display.($previous->assessment_date ? ', '.$previous->assessment_date->format('M Y') : '');
            $prevByLabel = collect(app(AssessmentPdfReportService::class)->getIndicatorMetrics($previous))->keyBy('label');
            $indicatorMetrics = $indicatorMetrics->map(function ($m) use ($prevByLabel) {
                $prevPct = $prevByLabel->get($m['label'])['pct'] ?? null;
                $m['prev_pct'] = $prevPct;
                $m['delta'] = ($prevPct !== null && $m['pct'] !== null) ? round($m['pct'] - $prevPct, 1) : null;

                return $m;
            });
            if ($trend = $this->indicatorTrendInsight($indicatorMetrics, $previousRoundLabel)) {
                array_unshift($indicatorInsights, $trend);
            }
        }

        // ── CEO Insights ──────────────────────────────────────────────────────
        $insights = $this->generateInsights(
            $assessment, $sectionScores, $infraResponses, $hasDedicatedLab,
            $skillsMissing, $infoResponses, $qocAll, $hrRows, $deptScores,
            $hrCoverage, $overallCommodityPct, $commodityGaps
        );

        // Outcome, strengths and data-coverage findings sit in Executive Insights
        // under the Paediatric Indicators heading (not in the indicators section).
        $execIndicatorInsights = array_values(array_filter(
            $indicatorInsights,
            fn ($i) => in_array($i['area'], ['Outcome Indicators', 'Indicator Strengths', 'Indicator Data'], true)
        ));

        // Infrastructure and Information Systems show this same insight
        // inline in their own section instead of a per-question table.
        $infraInsight = collect($insights)->firstWhere('area', 'Infrastructure');
        $infoInsight = collect($insights)->firstWhere('area', 'Information Systems');

        // ── Data Quality ─────────────────────────────────────────────────────
        $sectionCompleteness = $this->buildSectionCompleteness($sectionScores);
        $overallCompleteness = $this->overallCompleteness($sectionCompleteness);
        $straightLiningFlags = $this->detectStraightLining($infraResponses, $skillsResponses, $infoResponses, $qocAll);
        $dataQualityInsights = $this->generateDataQualityInsights(
            $sectionScores, $hrCoverage, $overallCommodityPct, $deptScores
        );

        // A completeness result always exists once any section has been
        // scored — this reads it out loud instead of leaving the
        // percentage bars to speak for themselves with no narrative,
        // which read as blank when nothing else in this array triggered.
        $completenessInsight = $this->generateCompletenessInsight($sectionCompleteness, $overallCompleteness);
        if ($completenessInsight) {
            array_unshift($dataQualityInsights, $completenessInsight);
        }

        return compact(
            'assessment',
            'sectionScores',
            'infraResponses',
            'infraInsight',
            'infoInsight',
            'skillsResponses',
            'skillsMaster',
            'hasDedicatedLab',
            'skillsAvailable',
            'skillsMissing',
            'infoResponses',
            'infoDataToolsTable',
            'qocAll',
            'hrRows',
            'totalStaff',
            'totalTrained',
            'hrCoverage',
            'deptScores',
            'categoryScores',
            'overallCommodityPct',
            'insights',
            'sectionCompleteness',
            'overallCompleteness',
            'straightLiningFlags',
            'dataQualityInsights',
            'indicatorMetrics',
            'execIndicatorInsights',
            'previousRoundLabel',
        );
    }

    /**
     * Drops rows whose question's display_conditions no longer resolve to
     * visible given the assessment's current answers — same evaluator
     * DynamicFormBuilder (live form) and AssessmentPdfReportService (PDF/
     * HTML report) use, so this dashboard can't show a question, or a
     * whole conditional table like "Data Collection Tools & Registers",
     * that the data-entry screen itself currently hides (e.g. answered
     * before INFOSYS_DOC_TYPE was changed from "Paper based" to "EMR").
     *
     * @param  \Illuminate\Support\Collection<int, \stdClass>  $rows  Each row must carry a `display_conditions` column (raw JSON string or null, as returned by DB::table()).
     * @param  array<string, mixed>  $responsesByCode
     */
    private function filterVisibleRows(\Illuminate\Support\Collection $rows, array $responsesByCode): \Illuminate\Support\Collection
    {
        return $rows->filter(function ($row) use ($responsesByCode) {
            $conditions = $row->display_conditions ?? null;

            if (is_string($conditions)) {
                $conditions = json_decode($conditions, true);
            }

            if (empty($conditions)) {
                return true;
            }

            return ConditionalLogicEvaluator::isVisible($conditions, fn (string $code) => $responsesByCode[$code] ?? null);
        })->values();
    }

    /**
     * Per-section response-completeness percentages, driven by the same
     * assessment_section_scores rows the score strip already uses. The
     * 1111 junk sentinel (see the $sectionScores map() above) has already
     * been scrubbed to null by this point — a section carrying it is
     * marked invalid and displayed as "N/A" rather than a misleading
     * ratio, and is excluded from the overall completeness figure.
     *
     * @return \Illuminate\Support\Collection<int, array{code: string, name: string, answered: int, total: int, percentage: float, valid: bool, display: string}>
     */
    private function buildSectionCompleteness($sectionScores): \Illuminate\Support\Collection
    {
        return $sectionScores->map(function ($ss) {
            $valid = $ss->total_questions !== null && $ss->answered_questions !== null;
            $total = (int) $ss->total_questions;
            $answered = (int) $ss->answered_questions;
            $percentage = $valid && $total > 0 ? round(($answered / $total) * 100, 1) : 0.0;

            return [
                'code' => $ss->code,
                'name' => $ss->name,
                'answered' => $answered,
                'total' => $total,
                'percentage' => $percentage,
                'valid' => $valid,
                'display' => $valid ? "{$answered}/{$total} ({$percentage}%)" : 'N/A',
            ];
        })->values();
    }

    private function overallCompleteness($sectionCompleteness): float
    {
        $valid = $sectionCompleteness->where('valid', true);
        $total = $valid->sum('total');
        $answered = $valid->sum('answered');

        return $total > 0 ? round(($answered / $total) * 100, 1) : 0.0;
    }

    /**
     * Narrates the completeness bars instead of leaving them as bare
     * percentages with no accompanying read — null only when there's
     * nothing scored yet to narrate (buildSectionCompleteness() returned
     * nothing valid), which is the one case actually worth calling blank.
     *
     * @return array{type: string, icon: string, area: string, text: string}|null
     */
    private function generateCompletenessInsight($sectionCompleteness, float $overallCompleteness): ?array
    {
        $valid = $sectionCompleteness->where('valid', true);

        if ($valid->isEmpty()) {
            return null;
        }

        $incomplete = $valid->filter(fn ($sc) => $sc['percentage'] < 100);

        if ($incomplete->isEmpty()) {
            return ['type' => 'success', 'icon' => 'check-double', 'area' => 'Data Quality', 'text' => "Every scored section was fully answered — {$overallCompleteness}% overall response completeness. This is a complete dataset to base decisions on."];
        }

        $complete = $valid->filter(fn ($sc) => $sc['percentage'] >= 100)->pluck('name')->all();
        $gaps = $incomplete->map(fn ($sc) => "{$sc['name']} ({$sc['display']})")->all();

        $openingPart = ! empty($complete)
            ? $this->naturalJoin($complete).' '.(count($complete) === 1 ? 'was' : 'were').' fully answered, but '
            : '';

        $text = "{$openingPart}gaps remain in {$this->naturalJoin($gaps)}, bringing overall response completeness to {$overallCompleteness}%. Completing these sections would give a fuller picture of the facility's readiness.";

        return [
            'type' => $overallCompleteness >= 70 ? 'warning' : 'danger',
            'icon' => 'magnifying-glass-chart',
            'area' => 'Data Quality',
            'text' => ucfirst($text),
        ];
    }

    /**
     * Flags sections where every answered Yes/No item carries the exact
     * same value — a classic sign the assessor moved through the form
     * without engaging each item individually ("straight-lining"), rather
     * than a facility that genuinely has (or lacks) everything. Requires
     * at least 3 answered Yes/No items in a section before flagging, so a
     * short section with a legitimately uniform answer isn't mislabelled.
     *
     * @return array<int, array{section: string, value: string, count: int}>
     */
    private function detectStraightLining($infraResponses, $skillsResponses, $infoResponses, $qocAll): array
    {
        $flags = [];

        $sections = [
            'Infrastructure' => $infraResponses,
            'Skills Lab' => $skillsResponses,
            'Information Systems' => $infoResponses,
            'Quality of Care' => $qocAll instanceof \Illuminate\Support\Collection ? $qocAll->values() : $qocAll,
        ];

        foreach ($sections as $label => $responses) {
            $yesNo = collect($responses)->filter(fn ($r) => in_array($r->response_value, ['Yes', 'No'], true));

            if ($yesNo->count() < 3) {
                continue;
            }

            $distinctValues = $yesNo->pluck('response_value')->unique();

            if ($distinctValues->count() === 1) {
                $flags[] = [
                    'section' => $label,
                    'value' => $distinctValues->first(),
                    'count' => $yesNo->count(),
                ];
            }
        }

        return $flags;
    }

    /**
     * Rule-based relationships between sections that would otherwise be
     * read in isolation — surfaced the same way as generateInsights(),
     * but specifically calling out where two data points reinforce or
     * contradict each other, which is more actionable for a reviewer than
     * either figure alone.
     *
     * @return array<int, array{type: string, icon: string, area: string, text: string}>
     */
    private function generateDataQualityInsights($sectionScores, float $hrCoverage, float $commodityPct, $deptScores): array
    {
        $insights = [];

        // HR training coverage vs Quality of Care score
        $qocScore = $sectionScores->get('quality_of_care');
        if ($qocScore) {
            $qocPct = (float) $qocScore->percentage;

            if ($hrCoverage >= 60 && $qocPct < 50) {
                $insights[] = ['type' => 'warning', 'icon' => 'link', 'area' => 'HR ↔ Quality of Care', 'text' => "Staff training coverage is strong ({$hrCoverage}%) but Quality of Care scores only {$qocPct}% — trained staff aren't yet translating into consistent audit/review practice. This is a process gap, not a skills gap."];
            } elseif ($hrCoverage < 30 && $qocPct >= 60) {
                $insights[] = ['type' => 'success', 'icon' => 'link', 'area' => 'HR ↔ Quality of Care', 'text' => "Quality of Care practice is solid ({$qocPct}%) despite low formal training coverage ({$hrCoverage}%) — the facility is sustaining good practice through experience or supervision. Formal training would likely lock in and scale these gains."];
            } elseif ($hrCoverage >= 60 && $qocPct >= 60) {
                $insights[] = ['type' => 'success', 'icon' => 'link', 'area' => 'HR ↔ Quality of Care', 'text' => "Staff training coverage ({$hrCoverage}%) and Quality of Care practice ({$qocPct}%) are both strong and reinforcing each other — a good candidate site to model for peer facilities."];
            }
        }

        // Infrastructure completeness vs Skills Lab readiness
        $infraCompleteness = $sectionScores->get('infrastructure');
        $skillsScore = $sectionScores->get('skills_lab');
        if ($infraCompleteness && $skillsScore) {
            $infraPct = (float) $infraCompleteness->percentage;
            $skillsPct = (float) $skillsScore->percentage;

            if ($infraPct >= 80 && $skillsPct < 50) {
                $insights[] = ['type' => 'warning', 'icon' => 'link', 'area' => 'Infrastructure ↔ Skills Lab', 'text' => "Infrastructure is well-developed ({$infraPct}%) but Skills Lab readiness lags at {$skillsPct}% — capital investment hasn't yet extended to simulation-based training capacity. This is usually the fastest gap to close since the physical space already exists."];
            } elseif ($infraPct < 50 && $skillsPct >= 60) {
                $insights[] = ['type' => 'warning', 'icon' => 'link', 'area' => 'Infrastructure ↔ Skills Lab', 'text' => "The Skills Lab is well-equipped ({$skillsPct}%) despite weak general infrastructure ({$infraPct}%) — simulation training may be happening in a space that itself needs investment."];
            }
        }

        // Commodity availability vs weakest department
        if ($deptScores->isNotEmpty()) {
            $lowestDept = $deptScores->sortBy('percentage')->first();
            $spread = (float) $deptScores->max('percentage') - (float) $deptScores->min('percentage');

            if ($spread >= 30) {
                $insights[] = ['type' => 'warning', 'icon' => 'link', 'area' => 'Health Products ↔ Departments', 'text' => "Commodity availability varies widely by department — {$lowestDept->department} sits at {$lowestDept->percentage}% against a facility average of {$commodityPct}%, a {$spread}-point spread. Since the overall figure masks this, a department-targeted restock would move the facility average more efficiently than a general procurement push."];
            }
        }

        return $insights;
    }

    /**
     * Turns a Yes/No question's text into a noun phrase that reads
     * naturally inside a sentence — "Do you have a NICU?" becomes "a NICU",
     * "Is there a triage area...?" becomes "a triage area...". Strips
     * legacy baked-in numbering first (the same way
     * AssessmentPdfReportService does for the PDF/HTML report), then the
     * usual truncate-at-parenthesis/strip-punctuation cleanup, then the
     * question's own interrogative prefix. Only the common prefixes this
     * codebase's question text actually uses are handled; anything else is
     * returned lightly lower-cased rather than guessed at further.
     */
    private function toFeaturePhrase(string $text): string
    {
        $text = AssessmentPdfReportService::stripLegacyNumbering($text);
        $text = trim(str_replace(['?', '.'], '', explode('(', $text)[0]));

        $prefixes = [
            '/^Do you have\s+/i',
            '/^Does (?:the|this|your) facility have\s+/i',
            '/^Is there\s+/i',
            '/^Are there\s+/i',
            '/^Does\s+/i',
            '/^Is\s+/i',
            '/^Are\s+/i',
        ];

        foreach ($prefixes as $pattern) {
            $stripped = preg_replace($pattern, '', $text, 1);
            if ($stripped !== $text) {
                return lcfirst($stripped);
            }
        }

        return lcfirst($text);
    }

    /**
     * Converts a set of same-answer rows into narrative phrases, collapsing
     * the common "{Stem}: {Detail}" pattern (e.g. three separate "Does the
     * EMR generate the following Reports: X" rows, one per report) into a
     * single clause — "the EMR generate the following Reports including X,
     * Y, and Z" — instead of repeating that stem's own clunky phrase once
     * per distinct detail, which reads as broken/robotic rather than
     * narrative. Exact duplicate phrases (a handful of templates carry a
     * genuinely duplicated question) are deduplicated for display — this
     * doesn't touch scoring, only how the sentence reads.
     *
     * @param  \Illuminate\Support\Collection<int, \stdClass>  $rows  Rows carrying a `question_text` column.
     * @return array<int, string>
     */
    private function narrativePhrases(\Illuminate\Support\Collection $rows): array
    {
        $grouped = $rows->groupBy(function ($r) {
            $text = AssessmentPdfReportService::stripLegacyNumbering($r->question_text);
            $text = trim(explode('(', $text)[0]);

            return str_contains($text, ':') ? trim(explode(':', $text, 2)[0]) : $text;
        });

        $phrases = [];

        foreach ($grouped as $stem => $group) {
            $hasColon = str_contains(trim(explode('(', AssessmentPdfReportService::stripLegacyNumbering($group->first()->question_text))[0]), ':');

            if ($group->count() > 1 && $hasColon) {
                $details = $group->map(function ($r) {
                    $text = AssessmentPdfReportService::stripLegacyNumbering($r->question_text);
                    $text = trim(str_replace('?', '', explode('(', $text)[0]));

                    return trim(explode(':', $text, 2)[1] ?? '');
                })->filter()->all();

                $phrases[] = $this->toFeaturePhrase($stem).' including '.$this->naturalJoin($details);
            } else {
                foreach ($group as $r) {
                    $phrases[] = $this->toFeaturePhrase($r->question_text);
                }
            }
        }

        return array_values(array_unique($phrases));
    }

    /**
     * "a NICU" / "a NICU and a PICU" / "a NICU, a PICU, and a triage area" —
     * standard natural-language list joining with an Oxford comma.
     */
    private function naturalJoin(array $items): string
    {
        $items = array_values($items);
        $count = count($items);

        if ($count === 0) {
            return '';
        }
        if ($count === 1) {
            return $items[0];
        }
        if ($count === 2) {
            return "{$items[0]} and {$items[1]}";
        }

        $last = array_pop($items);

        return implode(', ', $items).', and '.$last;
    }

    /**
     * Keeps a narrative sentence from turning into an unreadable run-on
     * when a section has a dozen+ answered items — names up to $max of
     * them and rolls the rest into "among N others" rather than listing
     * every single one.
     */
    private function summarizeList(array $items, int $max = 3): string
    {
        $items = array_values($items);

        if (count($items) <= $max) {
            return $this->naturalJoin($items);
        }

        $shown = array_slice($items, 0, $max);
        $remaining = count($items) - $max;

        return $this->naturalJoin($shown).", among {$remaining} other".($remaining === 1 ? '' : 's');
    }

    /**
     * Keep every question of the assessment's own template, plus any
     * question from another template (older or newer) that this
     * assessment actually has an answer for — so answers given to
     * questions from a previous template are never left out. Expects the
     * query to join assessment_sections and left-join this assessment's
     * assessment_question_responses.
     */
    private function inTemplateOrAnswered($q, Assessment $assessment): void
    {
        $q->where('assessment_sections.assessment_type_id', $assessment->assessment_type_id)
            ->orWhere(function ($w) {
                $w->whereNotNull('assessment_question_responses.response_value')
                    ->where('assessment_question_responses.response_value', '!=', '');
            });
    }

    /**
     * The same facility's immediately preceding round of this assessment
     * type (baseline before midline, midline before endline …), using the
     * ordering the comparison tab uses.
     */
    private function previousAssessment(Assessment $assessment): ?Assessment
    {
        $siblings = $this->comparisonService->getComparableAssessments($assessment);
        $index = $siblings->search(fn (Assessment $a) => $a->id === $assessment->id);

        return ($index === false || $index === 0) ? null : $siblings->get($index - 1);
    }

    /**
     * Movement in indicator performance since the previous round. "Better"
     * respects direction: a rise in a coverage measure or a fall in a
     * burden/mortality measure both count as improvement. Changes under
     * 2 points are treated as stable.
     */
    private function indicatorTrendInsight($metrics, string $previousLabel): ?array
    {
        $moved = $metrics->filter(fn ($m) => $m['delta'] !== null && $m['direction'] !== 'context');
        if ($moved->isEmpty()) {
            return null;
        }

        $better = fn ($m) => $m['direction'] === 'higher' ? $m['delta'] : -$m['delta'];
        $improved = $moved->filter(fn ($m) => $better($m) >= 2)->sortByDesc($better)->values();
        $declined = $moved->filter(fn ($m) => $better($m) <= -2)->sortBy($better)->values();
        $fmt = fn ($m) => lcfirst($m['short']).' ('.($m['delta'] > 0 ? '+' : '').$m['delta'].' pts)';

        $parts = [];
        if ($improved->isNotEmpty()) {
            $parts[] = "{$improved->count()} improved, led by ".$this->naturalJoin($improved->take(2)->map($fmt)->all());
        }
        if ($declined->isNotEmpty()) {
            $parts[] = "{$declined->count()} regressed, notably ".$this->naturalJoin($declined->take(2)->map($fmt)->all());
        }
        $stable = $moved->count() - $improved->count() - $declined->count();
        if ($stable > 0) {
            $parts[] = "{$stable} unchanged";
        }

        $type = $declined->isEmpty() ? 'success' : ($improved->isEmpty() ? 'danger' : 'warning');
        $closing = match ($type) {
            'success' => ' The gains since the earlier assessment show mentorship is translating into practice.',
            'danger' => ' No indicator has improved since the earlier assessment — the approach needs to be revisited with the facility team.',
            default => ' Sustain what is working and focus mentorship on the indicators that slipped.',
        };

        return [
            'type' => $type,
            'icon' => 'chart-line',
            'area' => 'Progress since '.$previousLabel,
            'text' => 'Of '.$moved->count().' comparable indicators: '.implode('; ', $parts).'.'.$closing,
        ];
    }

    /**
     * good / warning / danger / na for one indicator proportion.
     * Coverage measures: >=80 good, 50-79 warning, <50 danger.
     * Burden measures: <=10 good, <=25 warning, else danger; mortality
     * measures: 0 good, any death warning, >=5% danger.
     * Context measures (case-mix) are not judged.
     */
    private function indicatorStatus(array $m): string
    {
        if ($m['pct'] === null) {
            return 'na';
        }

        return match ($m['direction']) {
            'higher' => $m['pct'] >= 80 ? 'good' : ($m['pct'] >= 50 ? 'warning' : 'danger'),
            'lower' => str_contains($m['label'], ' died')
                ? ($m['pct'] == 0 ? 'good' : ($m['pct'] < 5 ? 'warning' : 'danger'))
                : ($m['pct'] <= 10 ? 'good' : ($m['pct'] <= 25 ? 'warning' : 'danger')),
            default => 'context',
        };
    }

    /**
     * Narrative insights from the indicator proportions: care-coverage gaps
     * per group, burden/mortality flags, strengths, and how much could not
     * be calculated.
     */
    private function generateIndicatorInsights($metrics): array
    {
        $insights = [];
        $judged = $metrics->whereIn('status', ['good', 'warning', 'danger']);
        if ($judged->isEmpty()) {
            return $insights;
        }

        $fmt = fn ($m) => lcfirst($m['short'])." ({$m['pct']}%)";

        // Care-coverage gaps, per group, weakest first
        foreach (['Newborn', 'Paediatric'] as $group) {
            $gaps = $judged->where('group', $group)->where('direction', 'higher')
                ->whereIn('status', ['warning', 'danger'])->sortBy('pct')->values();
            if ($gaps->isEmpty()) {
                continue;
            }
            $worst = $gaps->take(2)->filter(fn ($m) => $m['why'])->map(fn ($m) => $m['why'].'.')->implode(' ');
            $insights[] = [
                'type' => $gaps->contains('status', 'danger') ? 'danger' : 'warning',
                'icon' => $group === 'Newborn' ? 'baby' : 'child',
                'area' => "{$group} Indicators",
                'text' => "Care delivery falls short on {$gaps->count()} {$group} ".($gaps->count() === 1 ? 'indicator' : 'indicators').': '
                    .$this->naturalJoin($gaps->take(4)->map($fmt)->all()).($gaps->count() > 4 ? ', among others' : '').'. '
                    .$worst.' Every one of these is a step in the file review that mentorship can directly strengthen.',
            ];
        }

        // Burden / mortality measures
        $burden = $judged->where('direction', 'lower')->whereIn('status', ['warning', 'danger'])->sortByDesc('pct')->values();
        if ($burden->isNotEmpty()) {
            $insights[] = [
                'type' => $burden->contains('status', 'danger') ? 'danger' : 'warning',
                'icon' => 'heartbeat',
                'area' => 'Outcome Indicators',
                'text' => 'Outcome measures need attention: '.$this->naturalJoin($burden->take(3)->map($fmt)->all()).'. '
                    .$burden->take(2)->filter(fn ($m) => $m['why'])->map(fn ($m) => $m['why'].'.')->implode(' ')
                    .' These figures are where care gaps show up as preventable harm, so they should be tracked month on month.',
            ];
        }

        // Strengths
        $strong = $judged->where('direction', 'higher')->where('status', 'good')->sortByDesc('pct')->values();
        if ($strong->isNotEmpty()) {
            $insights[] = [
                'type' => 'success',
                'icon' => 'check-circle',
                'area' => 'Indicator Strengths',
                'text' => 'Strong performance on '.$this->naturalJoin($strong->take(3)->map($fmt)->all()).($strong->count() > 3 ? ', and '.($strong->count() - 3).' more' : '').'. These practices are embedded and can anchor peer learning with other facilities.',
            ];
        }

        // What couldn't be calculated
        $na = $metrics->where('status', 'na')->count();
        if ($na > 0) {
            $insights[] = [
                'type' => $na > $metrics->count() / 2 ? 'warning' : 'info',
                'icon' => 'database',
                'area' => 'Indicator Data',
                'text' => "{$na} of {$metrics->count()} indicators could not be calculated because the numerator or denominator is missing, zero or marked not applicable. Complete these counts from the registers so performance on them can be judged.",
            ];
        }

        return $insights;
    }

    /**
     * Availability and missing items per commodity category (grouped by
     * name, because the same category exists once per assessment type).
     */
    private function commodityGapsByCategory(int $aId): array
    {
        $rows = DB::table('assessment_commodity_responses as r')
            ->join('commodities as c', 'c.id', '=', 'r.commodity_id')
            ->join('commodity_categories as cc', 'cc.id', '=', 'c.commodity_category_id')
            ->where('r.assessment_id', $aId)
            ->select('cc.name as category', 'c.name as commodity', 'r.available')
            ->get();

        return $rows->groupBy(fn ($r) => strtoupper(trim($r->category)))
            ->map(function ($g, $cat) {
                $missing = $g->where('available', 0)->pluck('commodity')->unique()
                    // Bare sizes like "00" or "G23" mean nothing without their parent item
                    ->filter(fn ($n) => preg_match('/[a-z]{4,}/i', $n))->values();

                return [
                    'category' => $cat,
                    'pct' => round($g->where('available', 1)->count() / max($g->count(), 1) * 100, 1),
                    'missing' => $missing->all(),
                ];
            })
            ->filter(fn ($c) => $c['pct'] < 75)
            ->sortBy('pct')
            ->values()
            ->all();
    }

    /**
     * Plain-language statement of what the weakest commodity categories mean
     * for patient outcomes.
     */
    private function commodityClinicalImpact(array $gaps): string
    {
        if (empty($gaps)) {
            return '';
        }

        $impact = [
            'AIRWAY' => ['airway and oxygen supplies', 'A newborn or child who cannot be suctioned, oxygenated or ventilated within minutes of presenting is at immediate risk of death or permanent brain injury — birth asphyxia and severe pneumonia remain leading causes of newborn and child mortality'],
            'BREATHING' => ['breathing-support supplies', 'Without pulse oximetry, oxygen delivery and bubble CPAP, hypoxia and respiratory distress go undetected or untreated, which is how preventable pneumonia and prematurity complications become deaths'],
            'CIRCULATION' => ['circulation and IV-access supplies', 'Shock from sepsis, dehydration or bleeding kills within hours; without cannulas, intraosseous needles, fluids and monitors, resuscitation cannot start'],
            'DISABILITY' => ['neurological and glucose-monitoring supplies', 'Undetected hypoglycaemia and seizures cause irreversible brain damage and death, and both are cheap to detect and treat when the tools are available'],
            'EXPOSURE' => ['temperature-management supplies', 'Hypothermia is a silent killer of small babies; thermometers and warming equipment are what keep a stable newborn from becoming a critical one'],
            'MEDICATION' => ['essential medicines', 'Missing first-line antibiotics, anticonvulsants, caffeine, antenatal steroids or glucose means a diagnosed illness cannot be treated, turning a curable condition into a fatal one'],
            'MEDICINE/DRUGS' => ['essential medicines', 'Missing first-line antibiotics, anticonvulsants, caffeine, antenatal steroids or glucose means a diagnosed illness cannot be treated, turning a curable condition into a fatal one'],
            'LABORATORY' => ['laboratory and point-of-care tests', 'Without bedside glucose, haemoglobin, malaria and bilirubin tests, treatment is guesswork and dangerous conditions are found too late'],
            'INFECTION PREVENTION' => ['infection prevention supplies', 'Sick newborns and children are highly susceptible, so missing hand hygiene, sterile and disinfection supplies drive hospital-acquired sepsis and outbreaks'],
            'INFECTION PREVENTION AND CONTROL (IPC)' => ['infection prevention supplies', 'Sick newborns and children are highly susceptible, so missing hand hygiene, sterile and disinfection supplies drive hospital-acquired sepsis and outbreaks'],
            'NUTRITION ASSESSMENT' => ['nutrition assessment tools', 'Without MUAC tapes, scales and length boards, severe malnutrition goes unrecognised, and malnourished children are far more likely to die from common infections'],
            'OTHER NEWBORN' => ['newborn care supplies', 'Items such as kangaroo-care, feeding and thermal-care supplies are what separate a surviving small baby from a deteriorating one'],
            'OTHER PAEDIATRIC' => ['paediatric care supplies', 'These supplies underpin routine assessment and treatment of sick children, and gaps push care towards delay or referral'],
            'TRIAGE' => ['triage supplies', 'Triage is how the sickest child is seen first; without it, children with emergency signs wait in the same queue as everyone else'],
            'ORT CORNER' => ['oral rehydration supplies', 'Diarrhoea is easily treated with ORS and zinc, yet it still kills children when these are not on hand'],
        ];

        // Match on keywords so renamed/variant categories ("Airway & Oxygen",
        // "MEDICINES") still resolve; anything unknown gets a generic line.
        $resolve = function (string $category) use ($impact) {
            if (isset($impact[$category])) {
                return $impact[$category];
            }
            foreach ([
                'AIRWAY' => 'AIRWAY', 'OXYGEN' => 'AIRWAY', 'BREATH' => 'BREATHING', 'CIRCULAT' => 'CIRCULATION',
                'DISABILIT' => 'DISABILITY', 'EXPOSURE' => 'EXPOSURE', 'MEDIC' => 'MEDICATION', 'DRUG' => 'MEDICATION',
                'LAB' => 'LABORATORY', 'INFECTION' => 'INFECTION PREVENTION', 'IPC' => 'INFECTION PREVENTION',
                'NUTRITION' => 'NUTRITION ASSESSMENT', 'NEWBORN' => 'OTHER NEWBORN', 'PAED' => 'OTHER PAEDIATRIC',
                'TRIAGE' => 'TRIAGE', 'ORT' => 'ORT CORNER',
            ] as $needle => $key) {
                if (str_contains($category, $needle)) {
                    return $impact[$key];
                }
            }

            return [
                strtolower($category).' supplies',
                'Each of these items supports a step in assessing or treating a sick newborn or child, so shortages translate into delayed or missed care',
            ];
        };

        $parts = [];
        foreach (array_slice($gaps, 0, 3) as $gap) {
            $meta = $resolve($gap['category']);
            $examples = array_slice($gap['missing'], 0, 3);
            $examplesText = $examples ? ' (e.g. '.$this->naturalJoin($examples).')' : '';
            $parts[] = ucfirst($meta[0])." at {$gap['pct']}%{$examplesText}. {$meta[1]}.";
        }

        if (empty($parts)) {
            return '';
        }

        return ' These are not administrative gaps — they are survival gaps. Weakest areas: '.implode(' ', $parts)
            .' Closing these shortfalls is one of the fastest, lowest-cost ways to reduce preventable newborn and child deaths at this facility.';
    }

    private function generateInsights(
        Assessment $assessment,
        $sectionScores,
        $infraResponses,
        bool $hasDedicatedLab,
        $skillsMissing,
        $infoResponses,
        $qocAll,
        $hrRows,
        $deptScores,
        float $hrCoverage,
        float $commodityPct,
        array $commodityGaps = [],
    ): array {
        $insights = [];
        $overall = (float) ($assessment->overall_percentage ?? 0);

        // Overall grade insight
        if ($overall >= 80) {
            $insights[] = ['type' => 'success', 'icon' => 'trophy', 'area' => 'Overall', 'text' => "This facility scored {$overall}% overall — a strong performance. It is well-positioned for mentorship engagement and can serve as a model site."];
        } elseif ($overall >= 50) {
            $insights[] = ['type' => 'warning', 'icon' => 'chart-line', 'area' => 'Overall', 'text' => "This facility scored {$overall}% overall — a moderate performance. Targeted support in weak areas can quickly move it to readiness."];
        } else {
            $insights[] = ['type' => 'danger', 'icon' => 'exclamation-triangle', 'area' => 'Overall', 'text' => "This facility scored {$overall}% overall — significant gaps remain. A structured improvement plan with hands-on mentorship is recommended before formal rollout."];
        }

        // Infrastructure — narrated, positivity first: what's in place is
        // woven into an opening sentence before the gaps are raised, rather
        // than two flat "Working well / Missing" lists.
        $infraScore = $sectionScores->get('infrastructure');
        if ($infraScore) {
            $pct = (float) $infraScore->percentage;
            $presentInfra = $this->narrativePhrases($infraResponses->filter(fn ($r) => $r->response_value === 'Yes'));
            $missingInfra = $this->narrativePhrases($infraResponses->filter(fn ($r) => $r->response_value === 'No'));

            if (empty($missingInfra)) {
                $insights[] = ['type' => 'success', 'icon' => 'building', 'area' => 'Infrastructure', 'text' => 'All infrastructure indicators are met. The facility has the physical environment required to deliver quality newborn and paediatric care.'];
            } else {
                $opening = match (true) {
                    $pct >= 80 => 'The data shows strong infrastructure for newborn and paediatric care',
                    $pct >= 50 => 'The data shows a developing infrastructure base for newborn and paediatric care',
                    default => 'The data shows significant infrastructure gaps for newborn and paediatric care',
                };
                $strengthPart = ! empty($presentInfra) ? ', with '.$this->summarizeList($presentInfra).' already in place' : '';
                $text = "{$opening}{$strengthPart}. However, the facility currently lacks {$this->naturalJoin($missingInfra)} — closing these gaps would strengthen its ability to manage both mother and newborn safely, particularly for higher-risk cases.";

                $insights[] = ['type' => $pct >= 50 ? 'warning' : 'danger', 'icon' => 'building', 'area' => 'Infrastructure', 'text' => $text];
            }
        }

        // Skills Lab
        $slScore = $sectionScores->get('skills_lab');
        if ($slScore) {
            if (! $hasDedicatedLab) {
                $insights[] = ['type' => 'danger', 'icon' => 'flask', 'area' => 'Skills Lab', 'text' => 'No dedicated skills lab exists. This limits simulation-based training capacity. Establishing even a basic skills space is the highest-impact infrastructure investment for this facility.'];
            } elseif ((float) $slScore->percentage < 60) {
                $missing = $skillsMissing->count();
                $insights[] = ['type' => 'warning', 'icon' => 'flask', 'area' => 'Skills Lab', 'text' => "A skills lab exists but {$missing} equipment/supply items are missing. Procurement of core consumables and task trainers should be prioritised before mentorship sessions."];
            } else {
                $insights[] = ['type' => 'success', 'icon' => 'flask', 'area' => 'Skills Lab', 'text' => 'Skills lab is well-equipped. This facility can host high-quality simulation-based training with minimal additional investment.'];
            }
        }

        // Human Resources
        if ($hrRows->isNotEmpty()) {
            $topCadre = $hrRows->sortByDesc('total_in_facility')->first();
            $totalStaff = $hrRows->sum('total_in_facility');

            // Coverage per training programme (trained / all staff) and per cadre
            $programmes = [
                'etat_plus' => 'ETAT+',
                'comprehensive_newborn_care' => 'Comprehensive Newborn Care',
                'imnci' => 'IMNCI',
                'type_1_diabetes' => 'Type-1 Diabetes',
                'essential_newborn_care' => 'Essential Newborn Care',
            ];
            $programmeCoverage = collect($programmes)->map(fn ($label, $col) => [
                'label' => $label,
                'trained' => (int) $hrRows->sum($col),
                'pct' => $totalStaff > 0 ? round(min($hrRows->sum($col) / $totalStaff, 1) * 100, 1) : 0,
            ])->sortBy('pct')->values();
            $weakest = $programmeCoverage->first();
            $strongest = $programmeCoverage->last();
            $untrainedCadres = $hrRows->filter(fn ($r) => $r->total_in_facility > 0 && $r->total_trained == 0)->pluck('cadre');

            $coverageDetail = '';
            if ($totalStaff > 0 && $strongest['pct'] !== $weakest['pct']) {
                $coverageDetail = " Coverage is highest for {$strongest['label']} ({$strongest['pct']}% of staff) and lowest for {$weakest['label']} ({$weakest['pct']}%).";
            } elseif ($totalStaff > 0) {
                $coverageDetail = " Coverage is uniform across programmes at {$weakest['pct']}% of staff.";
            }
            if ($untrainedCadres->isNotEmpty()) {
                $coverageDetail .= ' No trained staff recorded for: '.$untrainedCadres->take(4)->implode(', ').($untrainedCadres->count() > 4 ? ' and '.($untrainedCadres->count() - 4).' more' : '').'.';
            }

            if ($hrCoverage < 30) {
                $insights[] = ['type' => 'danger', 'icon' => 'users', 'area' => 'Human Resources', 'text' => "Only {$hrCoverage}% of staff have received any specialist training. This represents a critical capacity gap requiring an urgent training plan for all cadres, prioritising {$topCadre->cadre}.".$coverageDetail];
            } elseif ($hrCoverage < 60) {
                $insights[] = ['type' => 'warning', 'icon' => 'users', 'area' => 'Human Resources', 'text' => "{$hrCoverage}% of staff have received specialist training. While progress is visible, targeted refresher courses are needed to achieve full competency across all cadres.".$coverageDetail];
            } else {
                $insights[] = ['type' => 'success', 'icon' => 'users', 'area' => 'Human Resources', 'text' => "{$hrCoverage}% staff training coverage achieved. The workforce is well-trained and can effectively absorb mentorship interventions.".$coverageDetail];
            }
        }

        // Health Products
        $lowestDept = $deptScores->sortBy('percentage')->first();
        $clinicalImpact = $this->commodityClinicalImpact($commodityGaps);
        if ($commodityPct < 50) {
            $insights[] = ['type' => 'danger', 'icon' => 'capsules', 'area' => 'Health Products', 'text' => "Commodity availability is critically low at {$commodityPct}% overall".($lowestDept ? " — {$lowestDept->department} is the weakest department at {$lowestDept->percentage}%" : '').'. Emergency procurement is needed to ensure patient safety.'.$clinicalImpact];
        } elseif ($commodityPct < 75) {
            $insights[] = ['type' => 'warning', 'icon' => 'capsules', 'area' => 'Health Products', 'text' => "Commodity availability at {$commodityPct}% is below target".($lowestDept ? "; {$lowestDept->department} needs immediate restocking at {$lowestDept->percentage}%" : '').'. A supply chain review is recommended.'.$clinicalImpact];
        } else {
            $insights[] = ['type' => 'success', 'icon' => 'capsules', 'area' => 'Health Products', 'text' => "Good commodity availability at {$commodityPct}%. The facility is well-stocked to deliver clinical care across departments.".$clinicalImpact];
        }

        // Information Systems — same narrated, positivity-first treatment.
        $infoScore = $sectionScores->get('information_systems');
        if ($infoScore) {
            $scoredInfo = $infoResponses->filter(fn ($r) => $r->is_scored);
            $presentInfo = $this->narrativePhrases($scoredInfo->filter(fn ($r) => $r->response_value === 'Yes'));
            $missingInfo = $this->narrativePhrases($scoredInfo->filter(fn ($r) => $r->response_value === 'No'));

            if (empty($missingInfo)) {
                $insights[] = ['type' => 'success', 'icon' => 'database', 'area' => 'Information Systems', 'text' => 'All information system elements are in place. The facility has a strong data infrastructure to support evidence-based care and mentorship follow-up.'];
            } else {
                $count = count($missingInfo);
                $opening = $count > 4
                    ? 'The data points to meaningful gaps in record-keeping and data systems'
                    : 'The data shows a reasonably solid record-keeping and data system';
                $strengthPart = ! empty($presentInfo) ? ', with '.$this->summarizeList($presentInfo).' already functioning well' : '';
                $text = "{$opening}{$strengthPart}. However, {$this->naturalJoin($missingInfo)} ".($count === 1 ? 'is' : 'are')." still missing — without these, data-driven decision making is compromised and M&E targets become harder to track.";

                $insights[] = ['type' => $count > 4 ? 'danger' : 'warning', 'icon' => 'database', 'area' => 'Information Systems', 'text' => $text];
            }
        }

        // Quality of Care
        $qocScore = $sectionScores->get('quality_of_care');
        if ($qocScore) {
            // The audit questions are coded QOC_*_AUDIT in the 2025 form and
            // QOC_*_AUDITS in the 2026 one.
            $firstAnswer = fn (array $codes) => collect($codes)->map(fn ($c) => $qocAll->get($c)->response_value ?? null)->first(fn ($v) => $v !== null && $v !== '');
            $hasNeonatalAudit = $firstAnswer(['QOC_NEONATAL_AUDITS', 'QOC_NEONATAL_AUDIT']) === 'Yes';
            $hasChildAudit = $firstAnswer(['QOC_CHILD_AUDITS', 'QOC_CHILD_AUDIT']) === 'Yes';
            $auditFreq = $qocAll->get('QOC_AUDIT_FREQUENCY')->response_value ?? null;

            if ($hasNeonatalAudit && $hasChildAudit) {
                $freqText = $auditFreq ? " (frequency: {$auditFreq})" : '';
                $insights[] = ['type' => 'success', 'icon' => 'heartbeat', 'area' => 'Quality of Care (Death Audits)', 'text' => "Both neonatal and child death audits are conducted{$freqText}. This is a strong quality indicator demonstrating a learning culture that can be reinforced through mentorship."];
            } elseif ($hasNeonatalAudit || $hasChildAudit) {
                $insights[] = ['type' => 'warning', 'icon' => 'heartbeat', 'area' => 'Quality of Care (Death Audits)', 'text' => 'Partial audit practice — only one of neonatal/child audits is conducted. Completing the audit cycle for both cohorts is essential to close the mortality review loop.'];
            } else {
                $insights[] = ['type' => 'danger', 'icon' => 'heartbeat', 'area' => 'Quality of Care (Death Audits)', 'text' => 'No death audits are being conducted. Establishing a routine mortality review process is a critical first step towards quality improvement.'];
            }
        }

        return $insights;
    }
}
