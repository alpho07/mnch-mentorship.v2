<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\AssessmentQuestionResponse;
use App\Models\AssessmentSection;
use App\Models\MainCadre;
use Barryvdh\DomPDF\Facade\Pdf;

class AssessmentPdfReportService {

    /**
     * Section keys a user can opt out of in the PDF download, keyed to the
     * $sectionEnabled() check each section's @if wraps in
     * reports/assessment-html-report.blade.php. Facility Information,
     * Section Performance, and Overall Score are always included — not
     * offered as toggles.
     */
    public const TOGGLEABLE_SECTIONS = [
        'infrastructure' => 'Infrastructure',
        'skills_lab' => 'Skills Lab',
        'human_resources' => 'Human Resources',
        'health_products' => 'Health Products & Commodities',
        'information_systems' => 'Information Systems',
        'quality_of_care' => 'Quality of Care',
        'indicators' => 'Newborn & Paediatric Indicators',
    ];

    /**
     * Generate PDF report — renders the exact same view as the web
     * summary page (reports.assessment-html-report, via the PDF-specific
     * wrapper that supplies the .badge/.info-row/etc. CSS the Filament
     * page normally provides), so the PDF matches what's on screen
     * instead of a separately-maintained layout.
     *
     * @param  array<int, string>|null  $enabledSections  Keys from
     *     TOGGLEABLE_SECTIONS to include; null (the default) includes all
     *     of them, same as before this parameter existed.
     */
    public function generateExecutiveReport(Assessment $assessment, ?array $enabledSections = null) {
        $data = $this->prepareReportData($assessment);
        $data['comparison'] = app(\App\Services\AssessmentComparisonService::class)->prepareComparisonData($assessment);
        $data['enabledSections'] = $enabledSections;
        $data['isPdf'] = true;

        $pdf = Pdf::loadView('pdf.assessment-html-report-wrapper', $data);

        $pdf->setPaper('a4', 'portrait');

        $pdf->setOptions([
            'defaultFont' => 'sans-serif',
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => true,
        ]);

        return $pdf;
    }

    /**
     * Generate HTML report for web display.
     *
     * @param  array<int, string>|null  $enabledSections  Keys from
     *     TOGGLEABLE_SECTIONS to include; null includes all of them.
     */
    public function generateHtmlReport(Assessment $assessment, ?array $enabledSections = null): string {
        $data = $this->prepareReportData($assessment);
        $data['comparison'] = app(\App\Services\AssessmentComparisonService::class)->prepareComparisonData($assessment);
        $data['enabledSections'] = $enabledSections;
        $data['isPdf'] = false;

        return view('reports.assessment-html-report', $data)->render();
    }

    /**
     * Prepare all report data
     */
    public function prepareReportData(Assessment $assessment): array {
        // Load all relationships
        $assessment->load([
            'facility.subcounty.county',
            'assessor',
            'sectionScores.section',
            'questionResponses.question.section',
            'humanResourceResponses.cadre',
            'commodityResponses.commodity.category',
            'commodityResponses.department',
            'departmentScores.department',
            'departmentScores.category',
        ]);

        // Facility Information
        $facilityInfo = $this->getFacilityInfo($assessment);

        // Assessment Details
        $assessmentDetails = [
            'type' => $assessment->round_display,
            'date' => $assessment->assessment_date->format('F j, Y'),
            'status' => ucfirst($assessment->status),
            'assessor_name' => $assessment->assessor_name,
            'assessor_contact' => $assessment->assessor_contact,
            'completed_at' => $assessment->completed_at?->format('F j, Y'),
        ];

        // Overall Score
        $overallScore = $this->getOverallScore($assessment);

        // Section Scores
        $sectionScores = $this->getSectionScores($assessment);

        // Section Details (with both old and new names)
        $infrastructureData = $this->getInfrastructureDetails($assessment);
        $skillsLabData = $this->getSkillsLabDetails($assessment);
        $humanResourcesData = $this->getHumanResourcesDetails($assessment);
        $healthProductsData = $this->getHealthProductsDetails($assessment);
        $informationSystemsData = $this->getInformationSystemsDetails($assessment);
        $qualityOfCareData = $this->getQualityOfCareDetails($assessment);
        $indicatorsData = $this->getIndicatorsDetails($assessment);

        return [
            'assessment' => $assessment,
            'facilityInfo' => $facilityInfo,
            'assessmentDetails' => $assessmentDetails,
            'overallScore' => $overallScore,
            'sectionScores' => $sectionScores,
            // Old names for PDF compatibility
            'infrastructure' => $infrastructureData,
            'skillsLab' => $skillsLabData,
            'humanResources' => $humanResourcesData,
            'healthProducts' => $healthProductsData,
            'informationSystems' => $informationSystemsData,
            'qualityOfCare' => $qualityOfCareData,
            'indicators' => $indicatorsData,
            // New names for HTML view
            'infrastructureDetails' => $infrastructureData,
            'skillsLabDetails' => $skillsLabData,
            'humanResourcesDetails' => $humanResourcesData,
            'healthProductsDetails' => $healthProductsData,
            'informationSystemsDetails' => $informationSystemsData,
            'qualityOfCareDetails' => $qualityOfCareData,
            'indicatorsDetails' => $indicatorsData,
        ];
    }

    /**
     * Get facility information
     */
    protected function getFacilityInfo(Assessment $assessment): array {
        return [
            'name' => $assessment->facility->name,
            'mfl_code' => $assessment->facility->mfl_code ?? 'N/A',
            'level' => $assessment->facility->level ?? 'N/A',
            'ownership' => $assessment->facility->ownership ?? 'N/A',
            'county' => $assessment->facility->subcounty->county->name ?? 'N/A',
            'subcounty' => $assessment->facility->subcounty->name ?? 'N/A',
            'contact' => $assessment->facility->phone ?? $assessment->facility->email ?? 'N/A',
        ];
    }

    /**
     * Get overall score
     */
    protected function getOverallScore(Assessment $assessment): array {
        return [
            'score' => $assessment->overall_score ?? 0,
            'max_score' => $assessment->max_score ?? 100,
            'percentage' => $assessment->overall_percentage ?? 0,
            'grade' => $assessment->overall_grade ?? 'N/A',
            'grade_color' => $this->getGradeColor($assessment->overall_grade ?? 'gray'),
        ];
    }

    /**
     * Get section scores
     */
    protected function getSectionScores(Assessment $assessment): array {
        return $assessment->sectionScores->map(function ($sectionScore) {
                    return [
                        'section_name' => $sectionScore->section->name,
                        'score' => $sectionScore->total_score ?? 0,
                        'max_score' => $sectionScore->max_score ?? 0,
                        'percentage' => $sectionScore->percentage ?? 0,
                        'total_questions' => $sectionScore->total_questions ?? 0,
                        'answered_questions' => $sectionScore->answered_questions ?? 0,
                        'skipped_questions' => $sectionScore->skipped_questions ?? 0,
                    ];
                })->toArray();
    }

    /**
     * Every section with this code, across ALL assessment templates.
     * An assessment can hold answers to questions from an older template
     * (e.g. the 2025 QOC_NEONATAL_AUDIT on a 2026 assessment), and those
     * rows are the assessment's own data — scoping to its current template
     * would leave them out of the report. The responses queries are
     * already limited to this assessment, so this only widens which
     * questions are recognised.
     *
     * @return array<int, int>
     */
    private function sectionIdsForCode(string $code): array {
        return AssessmentSection::where('code', $code)->pluck('id')->all();
    }

    /**
     * Get infrastructure details
     */
    protected function getInfrastructureDetails(Assessment $assessment): array {
        $sectionIds = $this->sectionIdsForCode('infrastructure');

        $responses = $assessment->questionResponses()
                ->whereHas('question', function ($q) use ($sectionIds) {
                    $q->whereIn('assessment_section_id', $sectionIds);
                })
                ->with('question')
                ->get();

        $responses = $this->filterVisibleResponses($assessment, $responses);

        // For PDF (detailed structure)
        $nbuResponse = $responses->where('question.question_code', 'INFRA_NBU')->first();
        $paedResponse = $responses->where('question.question_code', 'INFRA_PAED')->first();

        // Bed-count sub-questions (question_type 'number', sharing a
        // 'group' like "KMC beds") pulled into their own Unit/Functional/
        // Non-Functional table instead of two flat rows per unit mixed in
        // with the rest of the yes/no infrastructure questions.
        $bedResponses = $responses->filter(fn ($r) => $r->question->question_type === 'number' && $r->question->group !== null);
        $otherResponses = $responses->reject(fn ($r) => $r->question->question_type === 'number' && $r->question->group !== null);

        $bedsTable = $bedResponses->groupBy('question.group')->map(function ($pair, $unitName) {
            $functional = $pair->first(fn ($r) => str_ends_with($r->question->question_code, '_FUNCTIONAL'));
            $nonFunctional = $pair->first(fn ($r) => str_ends_with($r->question->question_code, '_NONFUNCTIONAL'));
            $functionalValue = $functional?->response_value;
            $nonFunctionalValue = $nonFunctional?->response_value;

            return [
                'unit' => $unitName,
                'functional' => $functionalValue ?? 'N/A',
                'non_functional' => $nonFunctionalValue ?? 'N/A',
                'total' => (is_numeric($functionalValue) ? (float) $functionalValue : 0)
                    + (is_numeric($nonFunctionalValue) ? (float) $nonFunctionalValue : 0),
            ];
        })->values()->toArray();

        return [
            // Simple structure for HTML — bed counts excluded, shown via
            // beds_table instead.
            'responses' => $otherResponses->map(function ($response) {
                return [
                    'question' => $this->stripLegacyNumbering($response->question->question_text),
                    'response' => $response->response_value ?? 'N/A',
                    'is_number' => $response->question->question_type === 'number',
                    'score' => $response->score ?? 0,
                    'explanation' => $response->explanation,
                    'group' => $response->question->group,
                    'indent_level' => (int) ($response->question->indent_level ?? 0),
                ];
            })->values()->toArray(),
            'beds_table' => $bedsTable,
            // Detailed structure for PDF
            'has_nbu' => $nbuResponse?->response_value === 'Yes',
            'nbu_beds' => $nbuResponse?->metadata['nicu_beds'] ?? 0,
            'nbu_cots' => $nbuResponse?->metadata['general_cots'] ?? 0,
            'nbu_kmc' => $nbuResponse?->metadata['kmc_beds'] ?? 0,
            'has_paed' => $paedResponse?->response_value === 'Yes',
            'paed_beds' => $paedResponse?->metadata['general_beds'] ?? 0,
            'paed_picu' => $paedResponse?->metadata['picu_beds'] ?? 0,
            'all_responses' => $responses,
        ];
    }

    /**
     * Get skills lab details
     */
    protected function getSkillsLabDetails(Assessment $assessment): array {
        $sectionIds = $this->sectionIdsForCode('skills_lab');

        $responses = $assessment->questionResponses()
                ->whereHas('question', function ($q) use ($sectionIds) {
                    $q->whereIn('assessment_section_id', $sectionIds);
                })
                ->with('question')
                ->get();

        // A follow-up question's response row can outlive the answer it
        // depended on — e.g. SKILLS_HAS_LAB flipped from Yes to No after its
        // Yes-only follow-ups were already answered. Those stale rows are
        // never deleted, so without this filter the report would show
        // answers to questions the form no longer displays.
        $responses = $this->filterVisibleResponses($assessment, $responses);

        $hasSkillsLab = $responses->where('question.question_code', 'SKILLS_MASTER')->first()?->response_value === 'Yes';

        return [
            // Simple structure for HTML
            'responses' => $responses->map(function ($response) {
                $displayValue = $response->response_value ?? 'N/A';

                if ($response->question->question_type === 'multi_select' && $displayValue !== 'N/A') {
                    $selected = json_decode($displayValue, true);
                    if (is_array($selected)) {
                        $displayValue = $selected === [] ? 'N/A' : implode(', ', $selected);
                    }
                }

                return [
                    'question' => $this->stripLegacyNumbering($response->question->question_text),
                    'response' => $displayValue,
                    'score' => $response->score ?? 0,
                    'explanation' => $response->explanation,
                    'group' => $response->question->group,
                    'indent_level' => (int) ($response->question->indent_level ?? 0),
                ];
            })->toArray(),
            // Detailed structure for PDF
            'has_skills_lab' => $hasSkillsLab,
            'all_responses' => $responses,
        ];
    }

    /**
     * Get human resources details
     */
    protected function getHumanResourcesDetails(Assessment $assessment): array {
        // Mirrors EditHumanResources::defaultExcludedCadreIds() — null means
        // the assessor never customized the selection via "Manage Cadres",
        // which defaults to excluding only the catch-all "Others" cadre.
        $excludedCadreIds = $assessment->excluded_cadre_ids ?? MainCadre::where('is_active', true)
            ->where('assessment_type_id', $assessment->assessment_type_id)
            ->where('name', 'Others')
            ->pluck('id')
            ->toArray();

        // A deactivated cadre (is_active = false) or one unchecked via
        // "Manage Cadres" for this assessment is hidden from the report,
        // but its HumanResourceResponse row is left untouched — re-activate
        // the cadre, or re-check it for this assessment, and the same
        // figures reappear here unchanged.
        $responses = $assessment->humanResourceResponses()
                ->whereNotIn('cadre_id', $excludedCadreIds)
                ->whereHas('cadre', function ($query) {
                    $query->where('is_active', true)
                        ->whereNotIn('name', [
                            'County Officer',
                            'National Officer',
                            'Medical Officer Intern',
                        ]);
                })
                ->with('cadre')
                ->get();

        return [
            // Simple structure for HTML
            'responses' => $responses->map(function ($response) {
                return [
                    'cadre' => $response?->cadre?->name,
                    'total_in_facility' => $this->hrCell($response, 'total_in_facility'),
                    'etat_plus' => $this->hrCell($response, 'etat_plus'),
                    'comprehensive_newborn_care' => $this->hrCell($response, 'comprehensive_newborn_care'),
                    'imnci' => $this->hrCell($response, 'imnci'),
                    'type_1_diabetes' => $this->hrCell($response, 'type_1_diabetes'),
                    'essential_newborn_care' => $this->hrCell($response, 'essential_newborn_care'),
                ];
            })->toArray(),
            // Detailed structure for PDF
            'total_staff' => $responses->sum('total_in_facility'),
            'total_etat_plus' => $responses->sum('etat_plus'),
            'total_comprehensive_nb' => $responses->sum('comprehensive_newborn_care'),
            'total_imnci' => $responses->sum('imnci'),
            'total_diabetes' => $responses->sum('type_1_diabetes'),
            'total_essential_nb' => $responses->sum('essential_newborn_care'),
            'by_cadre' => $responses->map(function ($response) {
                return [
                    'cadre' => $response?->cadre?->name,
                    'total' => $this->hrCell($response, 'total_in_facility'),
                    'etat_plus' => $this->hrCell($response, 'etat_plus'),
                    'comprehensive_nb' => $this->hrCell($response, 'comprehensive_newborn_care'),
                    'imnci' => $this->hrCell($response, 'imnci'),
                    'diabetes' => $this->hrCell($response, 'type_1_diabetes'),
                    'essential_nb' => $this->hrCell($response, 'essential_newborn_care'),
                ];
            })->toArray(),
        ];
    }

    private function hrCell($response, string $column) {
        return $response->cadre?->isColumnNotApplicable($column) ? 'N/A' : ($response->{$column} ?? 0);
    }

    /**
     * Get health products details grouped by department
     */
    protected function getHealthProductsDetails(Assessment $assessment): array {
        $commodityResponses = $assessment->commodityResponses()
                ->with(['commodity.category', 'department'])
                ->get()
                ->groupBy('department.name');

        $result = [];

        foreach ($commodityResponses as $departmentName => $responses) {
            // Group by category
            $byCategory = $responses->groupBy('commodity.category.name');

            $categories = [];
            foreach ($byCategory as $categoryName => $items) {
                $applicableItems = $items->where('not_applicable', false);
                $available = $applicableItems->where('available', true)->count();
                $total = $applicableItems->count();

                $categories[] = [
                    'name' => $categoryName,
                    'available' => $available,
                    'total' => $total,
                    'percentage' => $total > 0 ? round(($available / $total) * 100, 1) : 0,
                    // Same order the commodities were seeded in — needed
                    // for group_label/indent_level numbering below to
                    // cluster consecutive line items correctly, same as
                    // buildCategorySections()'s ->orderBy('order').
                    'items' => $items->sortBy('commodity.order')->map(function ($item) {
                        return [
                            'name' => $item->commodity->name,
                            'available' => $item->available,
                            'not_applicable' => $item->not_applicable,
                            'group' => $item->commodity->group_label,
                            'indent_level' => (int) ($item->commodity->indent_level ?? 0),
                            'quantity' => $item->quantity,
                        ];
                    })->values()->toArray(),
                ];
            }

            $applicableResponses = $responses->where('not_applicable', false);
            $totalAvailable = $applicableResponses->where('available', true)->count();
            $totalApplicable = $applicableResponses->count();
            $percentage = $totalApplicable > 0 ? round(($totalAvailable / $totalApplicable) * 100, 1) : 0;

            $result[$departmentName] = [
                'available' => $totalAvailable,
                'total' => $totalApplicable,
                'percentage' => $percentage,
                'grade' => $this->calculateGrade($percentage), // Add grade
                'categories' => $categories,
                'commodities' => $responses->map(function ($response) {
                    return [
                        'name' => $response->commodity->name,
                        'category' => $response->commodity->category->name,
                        'available' => $response->available,
                        'not_applicable' => $response->not_applicable,
                    ];
                }),
                'by_category' => collect($categories)->keyBy('name'),
            ];
        }

        return $result;
    }

    /**
     * Get information systems details
     */
    protected function getInformationSystemsDetails(Assessment $assessment): array {
        $sectionIds = $this->sectionIdsForCode('information_systems');

        $responses = $assessment->questionResponses()
                ->whereHas('question', function ($q) use ($sectionIds) {
                    $q->whereIn('assessment_section_id', $sectionIds);
                })
                ->with('question')
                ->get();

        $responses = $this->filterVisibleResponses($assessment, $responses);

        // The 24 MoH-form Available/Completeness pairs share a
        // "Data Collection Tools & Registers|Form|{formName}" group and
        // both use the bare question text "Available"/"Completeness" —
        // dumped into the same flat question/response list as everything
        // else, that's 48 identical-looking rows with no indication of
        // which form each belongs to. Pulled out into their own
        // form-by-form table instead.
        $grouped = $responses->filter(fn ($r) => $r->question->group !== null)
            ->sortBy(fn ($r) => $r->question->order);
        $ungrouped = $responses->filter(fn ($r) => $r->question->group === null);

        $dataToolsTable = $grouped
            ->groupBy(fn ($r) => last(explode('|', $r->question->group)))
            ->map(function ($pair, $formName) {
                $available = $pair->first(fn ($r) => str_ends_with($r->question->question_code, '_AVAILABLE'));
                $complete = $pair->first(fn ($r) => str_ends_with($r->question->question_code, '_COMPLETE'));

                return [
                    'form' => $formName,
                    'available' => $available?->response_value ?? 'N/A',
                    'completeness' => $complete?->response_value ?? 'N/A',
                ];
            })
            ->values()
            ->toArray();

        return [
            // Simple structure for HTML (ungrouped questions only — the
            // grouped MoH-form pairs render via data_tools_table instead)
            'responses' => $ungrouped->map(function ($response) {
                $isMortality = $response->question->question_type === 'mortality_three_month';
                $displayValue = $response->response_value ?? 'N/A';

                if ($isMortality && $displayValue !== 'N/A') {
                    $counts = json_decode($displayValue, true);
                    if (is_array($counts)) {
                        $parts = [];
                        foreach ($counts as $month => $count) {
                            // Convert slug "aug_2025" → "Aug 2025"
                            $label = ucfirst(str_replace('_', ' ', $month));
                            $parts[] = "{$label}: {$count}";
                        }
                        $displayValue = implode(', ', $parts);
                    }
                }

                return [
                    'question' => $this->stripLegacyNumbering($response->question->question_text),
                    'response' => $displayValue,
                    'score' => $response->score ?? 0,
                    'explanation' => $response->explanation,
                    'is_mortality' => $isMortality,
                    'group' => $response->question->group,
                    'indent_level' => (int) ($response->question->indent_level ?? 0),
                ];
            })->values()->toArray(),
            'data_tools_table' => $dataToolsTable,
            // For PDF (all responses)
            'all_responses' => $responses,
        ];
    }

    /**
     * Get quality of care details
     */
    protected function getQualityOfCareDetails(Assessment $assessment): array {
        $sectionIds = $this->sectionIdsForCode('quality_of_care');

        $responses = $assessment->questionResponses()
                ->whereHas('question', function ($q) use ($sectionIds) {
                    $q->whereIn('assessment_section_id', $sectionIds);
                })
                ->with('question')
                ->get();

        $responses = $this->filterVisibleResponses($assessment, $responses);

        // For PDF - keep as collections
        $yesNoCollection = $responses->filter(function ($response) {
            return $response->question->question_type === 'yes_no';
        });

        $selectCollection = $responses->filter(function ($response) {
            return $response->question->question_type === 'select';
        });

        $numberQuestions = $responses->filter(function ($response) {
            return $response->question->question_type === 'number';
        });

        // Group number questions by category
        $newbornStatsCollection = $numberQuestions->filter(function ($response) {
            $code = $response->question->question_code ?? '';
            return str_contains($code, 'NEWBORN') || str_contains($code, 'PRETERM') ||
                    str_contains($code, 'ASPHYXIA') || str_contains($code, 'CPAP') ||
                    str_contains($code, 'APNOEA') || str_contains($code, 'CAFFEINE') ||
                    str_contains($code, 'HYPOTHERMIA') || str_contains($code, 'O2_SAT') ||
                    str_contains($code, 'RBS') || str_contains($code, 'HEAD_TO_TOE');
        });

        $paedStatsCollection = $numberQuestions->filter(function ($response) {
            $code = $response->question->question_code ?? '';
            return str_contains($code, 'PAED');
        });

        // For HTML - convert to arrays
        $yesNoArray = $yesNoCollection->map(function ($response) {
                    return [
                        'question' => $this->stripLegacyNumbering($response->question->question_text),
                        'response' => $response->response_value ?? 'N/A',
                        'score' => $response->score ?? 0,
                        'explanation' => $response->explanation,
                        'group' => $response->question->group,
                        'indent_level' => (int) ($response->question->indent_level ?? 0),
                    ];
                })->values()->toArray();

        $selectArray = $selectCollection->map(function ($response) {
                    return [
                        'question' => $this->stripLegacyNumbering($response->question->question_text),
                        'response' => $response->response_value ?? 'N/A',
                        'score' => $response->score ?? 0,
                        'explanation' => $response->explanation,
                        'group' => $response->question->group,
                        'indent_level' => (int) ($response->question->indent_level ?? 0),
                    ];
                })->values()->toArray();

        $newbornStatsArray = $newbornStatsCollection->map(function ($response) {
                    return [
                        'question' => $this->stripLegacyNumbering($response->question->question_text),
                        'response' => $response->response_value ?? '0',
                        'group' => $response->question->group,
                        'indent_level' => (int) ($response->question->indent_level ?? 0),
                    ];
                })->values()->toArray();

        $paedStatsArray = $paedStatsCollection->map(function ($response) {
                    return [
                        'question' => $this->stripLegacyNumbering($response->question->question_text),
                        'response' => $response->response_value ?? '0',
                        'group' => $response->question->group,
                        'indent_level' => (int) ($response->question->indent_level ?? 0),
                    ];
                })->values()->toArray();

        return [
            // For PDF (collections with ->count())
            'yes_no' => $yesNoCollection,
            'select' => $selectCollection,
            'newborn_stats' => $newbornStatsCollection,
            'paed_stats' => $paedStatsCollection,
            // For HTML (arrays)
            'yes_no_array' => $yesNoArray,
            'select_array' => $selectArray,
            'newborn_stats_array' => $newbornStatsArray,
            'paed_stats_array' => $paedStatsArray,
        ];
    }

    /**
     * [label, numerator question_code, denominator question_code] — the
     * source spreadsheet's own "REPORTING PROPORTIONAL NEWBORN INDICATORS"
     * table, which computes each proportion from the raw counts entered
     * above it rather than asking a separate question. Every pair here
     * maps cleanly to an existing IND_NEWBORN_* question.
     */
    private const NEWBORN_PROPORTIONS = [
        ['Proportion of newborns who had their oxygen saturation taken at admission', 'IND_NEWBORN_O2SAT_TAKEN', 'IND_NEWBORN_ADMISSIONS'],
        ['Proportion of newborns who had their RBS taken at admission', 'IND_NEWBORN_RBS_TAKEN', 'IND_NEWBORN_ADMISSIONS'],
        ['Proportion of newborns who had their temperature taken at admission', 'IND_NEWBORN_HEADTOTOE', 'IND_NEWBORN_ADMISSIONS'],
        ['Proportion of newborns with hypothermia at admission (temp <36.5)', 'IND_NEWBORN_HYPOTHERMIA', 'IND_NEWBORN_ADMISSIONS'],
        ['Proportion of newborns who had a diagnosis of birth asphyxia', 'IND_NEWBORN_BIRTH_ASPHYXIA', 'IND_NEWBORN_ADMISSIONS'],
        ['Proportion of newborns <34 weeks admitted in the last complete month', 'IND_NEWBORN_LT34_ADMISSIONS', 'IND_NEWBORN_ADMISSIONS'],
        ['Proportion of newborns <34 weeks initiated on caffeine citrate', 'IND_NEWBORN_LT34_CAFFEINE', 'IND_NEWBORN_LT34_ADMISSIONS'],
        ['Proportion of mothers with preterm newborns <34 weeks gestation who received at least one dose of antenatal corticosteroids', 'IND_NEWBORN_ANTENATAL_CORTICOSTEROIDS', 'IND_NEWBORN_LT34_ADMISSIONS'],
        ['Proportion of newborns <32 weeks admitted in the last complete month', 'IND_NEWBORN_LT32_ADMISSIONS', 'IND_NEWBORN_ADMISSIONS'],
        ['Proportion of newborns <32 weeks initiated on CPAP', 'IND_NEWBORN_LT32_CPAP', 'IND_NEWBORN_LT32_ADMISSIONS'],
        ['Proportion of newborns < 2500g initiated on KMC', 'IND_NEWBORN_LT2500G_KMC', 'IND_NEWBORN_ADMISSIONS'],
        ['Proportion of newborns initiated on KMC within 2 hours after birth', 'IND_NEWBORN_KMC_WITHIN_2HRS', 'IND_NEWBORN_LT2500G_KMC'],
        ['Proportion of newborns initiated on KMC during their hospital stay', 'IND_NEWBORN_KMC_DURING_STAY', 'IND_NEWBORN_ADMISSIONS'],
    ];

    /**
     * All 11 of the spreadsheet's "REPORTING PROPORTIONAL PAEDIATRIC
     * INDICATORS" — each pair maps to an IND_PAED_* raw count question.
     */
    private const PAEDIATRIC_PROPORTIONS = [
        ['Proportion of children under 5 years with severe pneumonia initiated on oxygen', 'IND_PAED_SEVERE_PNEUMONIA_OXYGEN', 'IND_PAED_SEVERE_PNEUMONIA_ADMISSIONS'],
        ['Proportion of children under 5 years with severe pneumonia initiated on oxygen therapy who had correct oxygen prescription (appropriate delivery device, flow rate and target SpO2)', 'IND_PAED_OXYGEN_CORRECT_PRESCRIPTION', 'IND_PAED_SEVERE_PNEUMONIA_OXYGEN'],
        ['Proportion of children under 5 years with pneumonia initiated on Amoxicillin DT', 'IND_PAED_PNEUMONIA_AMOXICILLIN', 'IND_PAED_PNEUMONIA_ADMISSIONS'],
        ['Proportion of children under 5 years with severe pneumonia who died', 'IND_PAED_SEVERE_PNEUMONIA_DEATHS', 'IND_PAED_SEVERE_PNEUMONIA_ADMISSIONS'],
        ['Proportion of children under 5 years with diarrhoea treated with ORS/Zinc co-pack', 'IND_PAED_DIARRHOEA_ORS', 'IND_PAED_DIARRHOEA_ADMISSIONS'],
        ['Proportion of children under 5 years with hypovolemic shock due to diarrhoea treated with correct volume of isotonic fluid', 'IND_PAED_HYPOVOLEMIC_SHOCK', 'IND_PAED_DIARRHOEA_ADMISSIONS'],
        ['Proportion of children under 5 years admitted with an RBS measurement', 'IND_PAED_RBS', 'IND_PAED_ADMISSIONS'],
        ['Proportion of children under 5 years screened for malnutrition (MUAC/WHZ/nutritional oedema) in the outpatient department', 'IND_PAED_MALNUTRITION_OUTPATIENT', 'IND_PAED_OUTPATIENT_ATTENDANCE'],
        ['Proportion of children under 5 years screened for malnutrition (MUAC/WHZ/nutritional oedema) in the inpatient department', 'IND_PAED_MALNUTRITION_INPATIENT', 'IND_PAED_ADMISSIONS'],
        ['Proportion of patients aged 0-18 years with type 1 DM on basal bolus regimen', 'IND_PAED_T1DM_BASAL_BOLUS', 'IND_PAED_T1DM_ACTIVE'],
        ['Proportion of children aged 0-18 years admitted with DKA who died', 'IND_PAED_DKA_DEATHS', 'IND_PAED_T1DM_ACTIVE'],
    ];

    /**
     * How each proportion should be read, keyed by its numerator code.
     * 'higher' = a coverage/quality-of-care measure (more is better);
     * 'lower'  = a burden or outcome measure (less is better);
     * 'context' = describes the case-mix and is not judged good or bad.
     */
    private const INDICATOR_META = [
        'IND_NEWBORN_O2SAT_TAKEN' => ['higher', 'Newborn', 'Pulse oximetry at admission is how hypoxia and critical congenital heart disease are caught before a baby deteriorates'],
        'IND_NEWBORN_RBS_TAKEN' => ['higher', 'Newborn', 'Unchecked blood sugar in sick newborns leads to hypoglycaemic seizures and brain injury'],
        'IND_NEWBORN_HEADTOTOE' => ['higher', 'Newborn', 'A recorded admission examination is the basis for spotting danger signs and sepsis early'],
        'IND_NEWBORN_HYPOTHERMIA' => ['lower', 'Newborn', 'Hypothermia at admission points to failures in the warm chain from birth to the ward, and cold babies die more often'],
        'IND_NEWBORN_BIRTH_ASPHYXIA' => ['lower', 'Newborn', 'Birth asphyxia is a leading cause of newborn death and disability; a high share signals gaps in intrapartum care and resuscitation'],
        'IND_NEWBORN_LT34_ADMISSIONS' => ['context', 'Newborn', 'The share of preterm admissions determines how much specialist care the unit must be ready to deliver'],
        'IND_NEWBORN_LT34_CAFFEINE' => ['higher', 'Newborn', 'Caffeine citrate reduces apnoea and the need for ventilation in preterm babies'],
        'IND_NEWBORN_ANTENATAL_CORTICOSTEROIDS' => ['higher', 'Newborn', 'Antenatal steroids mature the preterm lung and are one of the most effective ways to prevent preterm respiratory deaths'],
        'IND_NEWBORN_LT32_ADMISSIONS' => ['context', 'Newborn', 'Very preterm admissions need CPAP, thermal support and feeding support to survive'],
        'IND_NEWBORN_LT32_CPAP' => ['higher', 'Newborn', 'CPAP is the main life-saving treatment for respiratory distress syndrome in very preterm babies'],
        'IND_NEWBORN_LT2500G_KMC' => ['higher', 'Newborn', 'Kangaroo mother care cuts mortality in small babies and is among the cheapest interventions available'],
        'IND_NEWBORN_KMC_WITHIN_2HRS' => ['higher', 'Newborn', 'Early KMC initiation is when it saves the most lives'],
        'IND_NEWBORN_KMC_DURING_STAY' => ['higher', 'Newborn', 'KMC during the stay supports warmth, feeding and bonding for small and sick babies'],
        'IND_PAED_SEVERE_PNEUMONIA_OXYGEN' => ['higher', 'Paediatric', 'Oxygen is the life-saving treatment for hypoxaemic severe pneumonia, a leading killer of children under five'],
        'IND_PAED_OXYGEN_CORRECT_PRESCRIPTION' => ['higher', 'Paediatric', 'Oxygen given at the wrong flow or by the wrong device is less effective and can harm'],
        'IND_PAED_PNEUMONIA_AMOXICILLIN' => ['higher', 'Paediatric', 'Amoxicillin DT is first-line treatment for pneumonia; delayed or wrong antibiotics let treatable disease become severe'],
        'IND_PAED_SEVERE_PNEUMONIA_DEATHS' => ['lower', 'Paediatric', 'Deaths among children with severe pneumonia show whether oxygen, antibiotics and monitoring are reaching them in time'],
        'IND_PAED_DIARRHOEA_ORS' => ['higher', 'Paediatric', 'ORS and zinc are the proven, low-cost treatment that prevent dehydration deaths from diarrhoea'],
        'IND_PAED_HYPOVOLEMIC_SHOCK' => ['higher', 'Paediatric', 'Children in shock from diarrhoea die within hours unless given the correct volume of fluid'],
        'IND_PAED_RBS' => ['higher', 'Paediatric', 'Blood sugar checks catch hypoglycaemia, a common and rapidly fatal complication in sick children'],
        'IND_PAED_MALNUTRITION_OUTPATIENT' => ['higher', 'Paediatric', 'Outpatient screening is the main chance to find malnutrition before a child becomes critically ill'],
        'IND_PAED_MALNUTRITION_INPATIENT' => ['higher', 'Paediatric', 'Malnourished inpatients are far more likely to die from common infections unless recognised and managed'],
        'IND_PAED_T1DM_BASAL_BOLUS' => ['higher', 'Paediatric', 'A basal-bolus regimen gives children with type 1 diabetes safer glucose control and fewer DKA episodes'],
        'IND_PAED_DKA_DEATHS' => ['lower', 'Paediatric', 'DKA deaths are largely preventable with early recognition, fluids and insulin'],
    ];

    /**
     * Get newborn & paediatric indicators details — split by the same
     * "Newborn Indicators"/"Paediatric Indicators" group IndicatorsSeeder
     * tags each question with, matching the two collapsible sections the
     * live form renders.
     */
    protected function getIndicatorsDetails(Assessment $assessment): array {
        $sectionIds = $this->sectionIdsForCode('newborn_paediatric_indicators');

        $responses = $assessment->questionResponses()
                ->whereHas('question', function ($q) use ($sectionIds) {
                    $q->whereIn('assessment_section_id', $sectionIds);
                })
                ->with('question')
                ->get();

        $responses = $this->filterVisibleResponses($assessment, $responses);

        // A question the assessor marked "does not apply" is left out of the
        // report altogether rather than shown as an N/A row.
        $toArray = fn ($collection) => $collection->reject(fn ($response) => $response->not_applicable)->map(function ($response) {
                    return [
                        'question' => $this->stripLegacyNumbering($response->question->question_text),
                        // A row the assessor marked "does not apply" reads
                        // as N/A, never as the 0 an unanswered row falls
                        // back to — the whole point of the flag is that
                        // this facility has no count here, which is not
                        // the same claim as a count of zero.
                        'response' => $response->not_applicable ? 'N/A' : ($response->response_value ?? '0'),
                        'group' => $response->question->group,
                        'indent_level' => (int) ($response->question->indent_level ?? 0),
                    ];
                })->values()->toArray();

        $responsesByCode = $responses->keyBy('question.question_code');

        // Admissions is the denominator for almost every other proportion
        // below it, so it's shown first as a plain count rather than a
        // computed percentage — there's nothing to divide it by.
        $admissionsRow = $responsesByCode->get('IND_NEWBORN_ADMISSIONS')?->not_applicable ? [] : [[
            'question' => 'Total number of newborn admissions for the last complete month',
            'response' => $this->rawCount($responsesByCode->get('IND_NEWBORN_ADMISSIONS')) ?? 'N/A',
            'group' => null,
            'indent_level' => 0,
        ]];

        return [
            'newborn_array' => $toArray($responses->filter(fn ($r) => $r->question->group === 'Newborn Indicators')),
            'paediatric_array' => $toArray($responses->filter(fn ($r) => $r->question->group === 'Paediatric Indicators')),
            'newborn_proportions_array' => array_merge($admissionsRow, $this->computeProportions(self::NEWBORN_PROPORTIONS, $responsesByCode)),
            'paediatric_proportions_array' => $this->computeProportions(self::PAEDIATRIC_PROPORTIONS, $responsesByCode),
            'all_responses' => $responses,
        ];
    }

    /**
     * Structured version of the report's proportion tables, using the same
     * definitions and N/A rules, for consumers that need numbers rather
     * than display strings (e.g. the executive dashboard).
     *
     * @return array<int, array{group: string, label: string, numerator: ?float, denominator: ?float, pct: ?float, direction: string, why: string}>
     */
    public function getIndicatorMetrics(Assessment $assessment): array {
        $sectionIds = $this->sectionIdsForCode('newborn_paediatric_indicators');

        if (empty($sectionIds)) {
            return [];
        }

        $responses = $assessment->questionResponses()
                ->whereHas('question', fn ($q) => $q->whereIn('assessment_section_id', $sectionIds))
                ->with('question')
                ->get();

        $byCode = $this->filterVisibleResponses($assessment, $responses)->keyBy('question.question_code');

        $metrics = [];
        foreach ([...self::NEWBORN_PROPORTIONS, ...self::PAEDIATRIC_PROPORTIONS] as [$label, $numCode, $denCode]) {
            if ($this->isMarkedNotApplicable($byCode, $numCode, $denCode)) {
                continue;
            }

            [$direction, $group, $why] = self::INDICATOR_META[$numCode] ?? ['context', 'Other', ''];
            $num = $this->rawCount($byCode->get($numCode));
            $den = $this->rawCount($byCode->get($denCode));
            $computable = is_numeric($num) && is_numeric($den) && (float) $den > 0;

            $metrics[] = [
                'group' => $group,
                'label' => $label,
                'numerator' => is_numeric($num) ? (float) $num : null,
                'denominator' => is_numeric($den) ? (float) $den : null,
                'pct' => $computable ? round((float) $num / (float) $den * 100, 1) : null,
                'direction' => $direction,
                'why' => $why,
            ];
        }

        return $metrics;
    }

    /**
     * Drops responses whose question's display_conditions no longer
     * resolve to visible given the assessment's current answers — same
     * evaluator DynamicFormBuilder (live form) and DynamicScoringService
     * (scoring) use, so the report can't show a question the form itself
     * would currently hide. Conditions may reference a question outside
     * $responses' own section, so the resolver is built from every response
     * on the assessment, not just the ones passed in.
     */
    private function filterVisibleResponses(Assessment $assessment, \Illuminate\Support\Collection $responses): \Illuminate\Support\Collection {
        $responsesByCode = AssessmentQuestionResponse::query()
                ->where('assessment_id', $assessment->id)
                ->join('assessment_questions', 'assessment_questions.id', '=', 'assessment_question_responses.assessment_question_id')
                ->pluck('assessment_question_responses.response_value', 'assessment_questions.question_code')
                ->all();

        return $responses->filter(function ($response) use ($responsesByCode) {
            $conditions = $response->question->display_conditions ?? null;

            if (empty($conditions)) {
                return true;
            }

            return ConditionalLogicEvaluator::isVisible($conditions, fn (string $code) => $responsesByCode[$code] ?? null);
        })->values();
    }

    /**
     * True when the numerator or denominator question of a proportion was
     * explicitly marked "does not apply" for this assessment.
     */
    private function isMarkedNotApplicable($responsesByCode, string ...$codes): bool {
        foreach ($codes as $code) {
            if ($responsesByCode->get($code)?->not_applicable) {
                return true;
            }
        }

        return false;
    }

    /**
     * The usable count on a response, or null when there isn't one — the
     * response is missing, or the assessor marked the question as not
     * applying to this facility. Callers treat null as N/A.
     */
    private function rawCount(?AssessmentQuestionResponse $response): ?string {
        if (! $response || $response->not_applicable) {
            return null;
        }

        return $response->response_value;
    }

    /**
     * @param  array<int, array{0: string, 1: string, 2: string}>  $definitions  [label, numerator code, denominator code]
     * @param  \Illuminate\Support\Collection  $responsesByCode  Keyed by question_code.
     */
    private function computeProportions(array $definitions, $responsesByCode): array {
        $result = [];

        foreach ($definitions as [$label, $numeratorCode, $denominatorCode]) {
            // A term the assessor marked "does not apply" removes the whole
            // proportion from the report instead of listing it as N/A.
            if ($this->isMarkedNotApplicable($responsesByCode, $numeratorCode, $denominatorCode)) {
                continue;
            }

            $numerator = $this->rawCount($responsesByCode->get($numeratorCode));
            $denominator = $this->rawCount($responsesByCode->get($denominatorCode));

            // Both counts must actually be answered, and the denominator
            // must be a real population (0 admissions means "not
            // applicable this period", not "0%") — otherwise show N/A
            // rather than a misleading computed value. A count the
            // assessor marked "does not apply" arrives here as null and so
            // takes the same N/A path: a proportion built on a term this
            // facility doesn't have isn't 0%, it's unanswerable.
            if (! is_numeric($numerator) || ! is_numeric($denominator) || (float) $denominator <= 0) {
                $result[] = ['question' => $label, 'response' => 'N/A', 'group' => null, 'indent_level' => 0];

                continue;
            }

            $percentage = number_format(((float) $numerator / (float) $denominator) * 100, 1);
            $result[] = ['question' => $label, 'response' => "{$percentage}% ({$numerator}/{$denominator})", 'group' => null, 'indent_level' => 0];
        }

        return $result;
    }

    /**
     * Calculate grade based on percentage
     */
    protected function calculateGrade(float $percentage): string {
        if ($percentage >= 80) {
            return 'green';
        }
        if ($percentage >= 50 && $percentage < 80) {
            return 'yellow';
        }
        if ($percentage < 50) {
            return 'red';
        }
    }

    /**
     * Get color for grade
     */
    protected function getGradeColor(string $grade): string {
        return match ($grade) {
            'green' => '#10b981',
            'yellow' => '#f59e0b',
            'red' => '#ef4444',
            default => '#6b7280',
        };
    }

    /**
     * Some question_text values in the database still carry a leading
     * "1. "/"11. " etc. baked in from before question numbering became a
     * report-rendering concern (see reports/partials/comparison-rows.blade.php's
     * `numbered` option) — left displaying that stale digit is harmless on
     * its own, but stacks into "11. 11. Question text" once the report
     * adds its own live-computed number on top. Stripped here so every
     * caller gets clean text regardless of which convention that
     * particular question's row predates.
     */
    public static function stripLegacyNumbering(string $text): string {
        return preg_replace('/^\d+\.\s*/', '', $text);
    }
}
