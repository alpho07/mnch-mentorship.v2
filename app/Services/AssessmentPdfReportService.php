<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\AssessmentSection;
use Barryvdh\DomPDF\Facade\Pdf;

class AssessmentPdfReportService {

    /**
     * Generate PDF report
     */
    public function generateExecutiveReport(Assessment $assessment) {
        $data = $this->prepareReportData($assessment);

        $pdf = Pdf::loadView('pdf.assessment-executive-report', $data);

        $pdf->setPaper('a4', 'portrait');

        $pdf->setOptions([
            'defaultFont' => 'sans-serif',
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => true,
        ]);

        return $pdf;
    }

    /**
     * Generate HTML report for web display
     */
    public function generateHtmlReport(Assessment $assessment): string {
        $data = $this->prepareReportData($assessment);

        return view('reports.assessment-html-report', $data)->render();
    }

    /**
     * Prepare all report data
     */
    protected function prepareReportData(Assessment $assessment) {
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
            'type' => ucfirst($assessment->assessment_type),
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
            // New names for HTML view
            'infrastructureDetails' => $infrastructureData,
            'skillsLabDetails' => $skillsLabData,
            'humanResourcesDetails' => $humanResourcesData,
            'healthProductsDetails' => $healthProductsData,
            'informationSystemsDetails' => $informationSystemsData,
            'qualityOfCareDetails' => $qualityOfCareData,
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
     * Get infrastructure details
     */
    protected function getInfrastructureDetails(Assessment $assessment): array {
        $sectionId = AssessmentSection::where('code', 'infrastructure')->value('id');

        $responses = $assessment->questionResponses()
                ->whereHas('question', function ($q) use ($sectionId) {
                    $q->where('assessment_section_id', $sectionId);
                })
                ->with('question')
                ->get();

        // For PDF (detailed structure)
        $nbuResponse = $responses->where('question.question_code', 'INFRA_NBU')->first();
        $paedResponse = $responses->where('question.question_code', 'INFRA_PAED')->first();

        return [
            // Simple structure for HTML
            'responses' => $responses->map(function ($response) {
                return [
                    'question' => $response->question->question_text,
                    'response' => $response->response_value ?? 'N/A',
                    'score' => $response->score ?? 0,
                    'explanation' => $response->explanation,
                ];
            })->toArray(),
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
        $sectionId = AssessmentSection::where('code', 'skills_lab')->value('id');

        $responses = $assessment->questionResponses()
                ->whereHas('question', function ($q) use ($sectionId) {
                    $q->where('assessment_section_id', $sectionId);
                })
                ->with('question')
                ->get();

        $hasSkillsLab = $responses->where('question.question_code', 'SKILLS_MASTER')->first()?->response_value === 'Yes';

        return [
            // Simple structure for HTML
            'responses' => $responses->map(function ($response) {
                return [
                    'question' => $response->question->question_text,
                    'response' => $response->response_value ?? 'N/A',
                    'score' => $response->score ?? 0,
                    'explanation' => $response->explanation,
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
        $responses = $assessment->humanResourceResponses()
                ->whereHas('cadre', function ($query) {
                    $query->whereNotIn('name', [
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
                    'total_in_facility' => $response->total_in_facility ?? 0,
                    'etat_plus' => $response->etat_plus ?? 0,
                    'comprehensive_newborn_care' => $response->comprehensive_newborn_care ?? 0,
                    'imnci' => $response->imnci ?? 0,
                    'type_1_diabetes' => $response->type_1_diabetes ?? 0,
                    'essential_newborn_care' => $response->essential_newborn_care ?? 0,
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
                    'total' => $response->total_in_facility ?? 0,
                    'etat_plus' => $response->etat_plus ?? 0,
                    'comprehensive_nb' => $response->comprehensive_newborn_care ?? 0,
                    'imnci' => $response->imnci ?? 0,
                    'diabetes' => $response->type_1_diabetes ?? 0,
                    'essential_nb' => $response->essential_newborn_care ?? 0,
                ];
            })->toArray(),
        ];
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
                $available = $items->where('available', true)->count();
                $total = $items->count();

                $categories[] = [
                    'name' => $categoryName,
                    'available' => $available,
                    'total' => $total,
                    'percentage' => $total > 0 ? round(($available / $total) * 100, 1) : 0,
                    'items' => $items->map(function ($item) {
                        return [
                            'name' => $item->commodity->name,
                            'available' => $item->available,
                        ];
                    })->toArray(),
                ];
            }

            $totalAvailable = $responses->where('available', true)->count();
            $totalApplicable = $responses->count();
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
        $sectionId = AssessmentSection::where('code', 'information_systems')->value('id');

        $responses = $assessment->questionResponses()
                ->whereHas('question', function ($q) use ($sectionId) {
                    $q->where('assessment_section_id', $sectionId);
                })
                ->with('question')
                ->get();

        return [
            // Simple structure for HTML
            'responses' => $responses->map(function ($response) {
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
                    'question' => $response->question->question_text,
                    'response' => $displayValue,
                    'score' => $response->score ?? 0,
                    'explanation' => $response->explanation,
                    'is_mortality' => $isMortality,
                ];
            })->toArray(),
            // For PDF (all responses)
            'all_responses' => $responses,
        ];
    }

    /**
     * Get quality of care details
     */
    protected function getQualityOfCareDetails(Assessment $assessment): array {
        $sectionId = AssessmentSection::where('code', 'quality_of_care')->value('id');

        $responses = $assessment->questionResponses()
                ->whereHas('question', function ($q) use ($sectionId) {
                    $q->where('assessment_section_id', $sectionId);
                })
                ->with('question')
                ->get();

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
                        'question' => $response->question->question_text,
                        'response' => $response->response_value ?? 'N/A',
                        'score' => $response->score ?? 0,
                        'explanation' => $response->explanation,
                    ];
                })->values()->toArray();

        $selectArray = $selectCollection->map(function ($response) {
                    return [
                        'question' => $response->question->question_text,
                        'response' => $response->response_value ?? 'N/A',
                        'score' => $response->score ?? 0,
                        'explanation' => $response->explanation,
                    ];
                })->values()->toArray();

        $newbornStatsArray = $newbornStatsCollection->map(function ($response) {
                    return [
                        'question' => $response->question->question_text,
                        'response' => $response->response_value ?? '0',
                    ];
                })->values()->toArray();

        $paedStatsArray = $paedStatsCollection->map(function ($response) {
                    return [
                        'question' => $response->question->question_text,
                        'response' => $response->response_value ?? '0',
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
}
