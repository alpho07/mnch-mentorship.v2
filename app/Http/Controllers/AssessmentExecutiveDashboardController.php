<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Services\AssessmentComparisonService;
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

        $pdf = Pdf::loadView('analytics.assessment-executive.dashboard', $data)
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'defaultFont' => 'sans-serif',
            ]);

        $filename = 'executive-assessment-'.$assessment->id.'-'.now()->format('Ymd').'.pdf';

        return $pdf->download($filename);
    }

    private function buildDashboardData(Assessment $assessment): array
    {
        $assessment->load([
            'facility.facilityLevel',
            'facility.facilityType',
            'facility.subcounty.county',
        ]);

        $aId = $assessment->id;

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
            ->select(
                'assessment_questions.question_code',
                'assessment_questions.question_text',
                'assessment_question_responses.response_value',
                'assessment_question_responses.score',
            )
            ->orderBy('assessment_questions.id')
            ->get();

        // ── Skills Lab ────────────────────────────────────────────────────────
        $skillsResponses = DB::table('assessment_questions')
            ->join('assessment_sections', 'assessment_sections.id', '=', 'assessment_questions.assessment_section_id')
            ->leftJoin('assessment_question_responses', function ($j) use ($aId) {
                $j->on('assessment_question_responses.assessment_question_id', '=', 'assessment_questions.id')
                    ->where('assessment_question_responses.assessment_id', '=', $aId);
            })
            ->where('assessment_sections.code', 'skills_lab')
            ->select(
                'assessment_questions.question_code',
                'assessment_questions.question_text',
                'assessment_questions.is_scored',
                'assessment_question_responses.response_value',
                'assessment_question_responses.score',
            )
            ->orderBy('assessment_questions.id')
            ->get();

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
            ->select(
                'assessment_questions.question_code',
                'assessment_questions.question_text',
                'assessment_questions.is_scored',
                'assessment_questions.group',
                'assessment_question_responses.response_value',
                'assessment_question_responses.score',
            )
            ->orderBy('assessment_questions.id')
            ->get();

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

        // $infoResponses itself stays the full list — generateInsights()
        // and detectStraightLining() below need every scored row,
        // grouped or not, to count "missing" correctly. Only the grid
        // rendering (dashboard blade) uses this trimmed-down version.
        $infoResponsesUngrouped = $infoResponses->filter(fn ($r) => $r->group === null)->values();

        // ── Quality of Care ───────────────────────────────────────────────────
        $qocAll = DB::table('assessment_questions')
            ->join('assessment_sections', 'assessment_sections.id', '=', 'assessment_questions.assessment_section_id')
            ->leftJoin('assessment_question_responses', function ($j) use ($aId) {
                $j->on('assessment_question_responses.assessment_question_id', '=', 'assessment_questions.id')
                    ->where('assessment_question_responses.assessment_id', '=', $aId);
            })
            ->where('assessment_sections.code', 'quality_of_care')
            ->select(
                'assessment_questions.question_code',
                'assessment_questions.question_text',
                'assessment_questions.is_scored',
                'assessment_question_responses.response_value',
                'assessment_question_responses.score',
            )
            ->orderBy('assessment_questions.id')
            ->get()
            ->keyBy('question_code');

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

        // ── CEO Insights ──────────────────────────────────────────────────────
        $insights = $this->generateInsights(
            $assessment, $sectionScores, $infraResponses, $hasDedicatedLab,
            $skillsMissing, $infoResponses, $qocAll, $hrRows, $deptScores,
            $hrCoverage, $overallCommodityPct
        );

        // ── Data Quality ─────────────────────────────────────────────────────
        $sectionCompleteness = $this->buildSectionCompleteness($sectionScores);
        $overallCompleteness = $this->overallCompleteness($sectionCompleteness);
        $straightLiningFlags = $this->detectStraightLining($infraResponses, $skillsResponses, $infoResponses, $qocAll);
        $dataQualityInsights = $this->generateDataQualityInsights(
            $sectionScores, $hrCoverage, $overallCommodityPct, $deptScores
        );

        return compact(
            'assessment',
            'sectionScores',
            'infraResponses',
            'skillsResponses',
            'skillsMaster',
            'hasDedicatedLab',
            'skillsAvailable',
            'skillsMissing',
            'infoResponses',
            'infoResponsesUngrouped',
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
        );
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

        // Infrastructure
        $infraScore = $sectionScores->get('infrastructure');
        if ($infraScore) {
            $missingInfra = $infraResponses->filter(fn ($r) => $r->response_value === 'No')->pluck('question_text');
            if ($missingInfra->isNotEmpty()) {
                $list = $missingInfra->map(fn ($t) => trim(str_replace(['?', '.'], '', explode('(', $t)[0])))->implode('; ');
                $insights[] = ['type' => 'warning', 'icon' => 'building', 'area' => 'Infrastructure', 'text' => "Missing infrastructure elements: {$list}. These gaps directly limit clinical capacity and should be flagged for capital planning."];
            } else {
                $insights[] = ['type' => 'success', 'icon' => 'building', 'area' => 'Infrastructure', 'text' => 'All infrastructure indicators are met. The facility has the physical environment required to deliver quality newborn and paediatric care.'];
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
            if ($hrCoverage < 30) {
                $insights[] = ['type' => 'danger', 'icon' => 'users', 'area' => 'Human Resources', 'text' => "Only {$hrCoverage}% of staff have received any specialist training. This represents a critical capacity gap requiring an urgent training plan for all cadres, prioritising {$topCadre->cadre}."];
            } elseif ($hrCoverage < 60) {
                $insights[] = ['type' => 'warning', 'icon' => 'users', 'area' => 'Human Resources', 'text' => "{$hrCoverage}% of staff have received specialist training. While progress is visible, targeted refresher courses are needed to achieve full competency across all cadres."];
            } else {
                $insights[] = ['type' => 'success', 'icon' => 'users', 'area' => 'Human Resources', 'text' => "{$hrCoverage}% staff training coverage achieved. The workforce is well-trained and can effectively absorb mentorship interventions."];
            }
        }

        // Health Products
        $lowestDept = $deptScores->sortBy('percentage')->first();
        if ($commodityPct < 50) {
            $insights[] = ['type' => 'danger', 'icon' => 'capsules', 'area' => 'Health Products', 'text' => "Commodity availability is critically low at {$commodityPct}% overall".($lowestDept ? " — {$lowestDept->department} is the weakest department at {$lowestDept->percentage}%" : '').'. Emergency procurement is needed to ensure patient safety.'];
        } elseif ($commodityPct < 75) {
            $insights[] = ['type' => 'warning', 'icon' => 'capsules', 'area' => 'Health Products', 'text' => "Commodity availability at {$commodityPct}% is below target".($lowestDept ? "; {$lowestDept->department} needs immediate restocking at {$lowestDept->percentage}%" : '').'. A supply chain review is recommended.'];
        } else {
            $insights[] = ['type' => 'success', 'icon' => 'capsules', 'area' => 'Health Products', 'text' => "Good commodity availability at {$commodityPct}%. The facility is well-stocked to deliver clinical care across departments."];
        }

        // Information Systems
        $infoScore = $sectionScores->get('information_systems');
        if ($infoScore) {
            $missingInfo = $infoResponses->filter(fn ($r) => $r->is_scored && $r->response_value === 'No')->count();
            if ($missingInfo > 4) {
                $insights[] = ['type' => 'danger', 'icon' => 'database', 'area' => 'Information Systems', 'text' => "{$missingInfo} key information system elements are absent. Without complete records, data-driven decision making is compromised and M&E targets cannot be tracked."];
            } elseif ($missingInfo > 0) {
                $insights[] = ['type' => 'warning', 'icon' => 'database', 'area' => 'Information Systems', 'text' => "{$missingInfo} records or registers are missing. Completing the documentation system is needed to support reliable monitoring of newborn and paediatric outcomes."];
            } else {
                $insights[] = ['type' => 'success', 'icon' => 'database', 'area' => 'Information Systems', 'text' => 'All information system elements are in place. The facility has a strong data infrastructure to support evidence-based care and mentorship follow-up.'];
            }
        }

        // Quality of Care
        $qocScore = $sectionScores->get('quality_of_care');
        if ($qocScore) {
            $hasNeonatalAudit = ($qocAll->get('QOC_NEONATAL_AUDIT')->response_value ?? '') === 'Yes';
            $hasChildAudit = ($qocAll->get('QOC_CHILD_AUDIT')->response_value ?? '') === 'Yes';
            $auditFreq = $qocAll->get('QOC_AUDIT_FREQUENCY')->response_value ?? null;

            if ($hasNeonatalAudit && $hasChildAudit) {
                $freqText = $auditFreq ? " (frequency: {$auditFreq})" : '';
                $insights[] = ['type' => 'success', 'icon' => 'heartbeat', 'area' => 'Quality of Care', 'text' => "Both neonatal and child death audits are conducted{$freqText}. This is a strong quality indicator demonstrating a learning culture that can be reinforced through mentorship."];
            } elseif ($hasNeonatalAudit || $hasChildAudit) {
                $insights[] = ['type' => 'warning', 'icon' => 'heartbeat', 'area' => 'Quality of Care', 'text' => 'Partial audit practice — only one of neonatal/child audits is conducted. Completing the audit cycle for both cohorts is essential to close the mortality review loop.'];
            } else {
                $insights[] = ['type' => 'danger', 'icon' => 'heartbeat', 'area' => 'Quality of Care', 'text' => 'No death audits are being conducted. Establishing a routine mortality review process is a critical first step towards quality improvement.'];
            }
        }

        return $insights;
    }
}
