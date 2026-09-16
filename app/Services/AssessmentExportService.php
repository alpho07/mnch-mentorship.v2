<?php

namespace App\Services;

use App\Models\Assessment;
use Illuminate\Support\Facades\DB;

class AssessmentExportService {

    /**
     * Export complete assessment data to CSV
     */
    public function exportAssessmentToCSV(Assessment $assessment): string {
        $assessment->load([
            'facility.subcounty.county',
            'assessor',
            'questionResponses.question.section',
            'humanResourceResponses.cadre',
            'commodityResponses.commodity.category',
            'commodityResponses.department',
        ]);

        $csvData = [];

        // Add header row
        $csvData[] = $this->getHeaderRow();

        // Add facility information rows
        $csvData = array_merge($csvData, $this->getFacilityRows($assessment));

        // Add separator
        $csvData[] = [''];

        // Add assessment details
        $csvData = array_merge($csvData, $this->getAssessmentDetailsRows($assessment));

        // Add separator
        $csvData[] = [''];

        // Add Infrastructure responses
        $csvData[] = ['INFRASTRUCTURE SECTION'];
        $csvData[] = ['Question Code', 'Question Text', 'Response', 'Score', 'Explanation', 'Metadata'];
        $csvData = array_merge($csvData, $this->getQuestionResponseRows($assessment, 'infrastructure'));

        // Add separator
        $csvData[] = [''];

        // Add Skills Lab responses
        $csvData[] = ['SKILLS LAB SECTION'];
        $csvData[] = ['Question Code', 'Question Text', 'Response', 'Score', 'Explanation', 'Metadata'];
        $csvData = array_merge($csvData, $this->getQuestionResponseRows($assessment, 'skills_lab'));

        // Add separator
        $csvData[] = [''];

        // Add Human Resources
        $csvData[] = ['HUMAN RESOURCES SECTION'];
        $csvData[] = ['Cadre', 'Total in Facility', 'ETAT+', 'Comprehensive NB Care', 'IMNCI', 'Type 1 Diabetes', 'Essential NB Care'];
        $csvData = array_merge($csvData, $this->getHumanResourceRows($assessment));

        // Add separator
        $csvData[] = [''];

        // Add Health Products/Commodities
        $csvData[] = ['HEALTH PRODUCTS SECTION'];
        $csvData[] = ['Department', 'Category', 'Commodity', 'Available', 'Score'];
        $csvData = array_merge($csvData, $this->getCommodityRows($assessment));

        // Add separator
        $csvData[] = [''];

        // Add Information Systems responses
        $csvData[] = ['INFORMATION SYSTEMS SECTION'];
        $csvData[] = ['Question Code', 'Question Text', 'Response', 'Score', 'Explanation', 'Metadata'];
        $csvData = array_merge($csvData, $this->getQuestionResponseRows($assessment, 'information_systems'));

        // Add separator
        $csvData[] = [''];

        // Add Quality of Care responses
        $csvData[] = ['QUALITY OF CARE SECTION'];
        $csvData[] = ['Question Code', 'Question Text', 'Question Type', 'Response', 'Score', 'Explanation', 'Metadata'];
        $csvData = array_merge($csvData, $this->getQualityOfCareRows($assessment));

        // Add separator
        $csvData[] = [''];

        // Add Section Scores Summary
        $csvData[] = ['SECTION SCORES SUMMARY'];
        $csvData[] = ['Section', 'Total Score', 'Max Score', 'Percentage', 'Total Questions', 'Answered', 'Skipped'];
        $csvData = array_merge($csvData, $this->getSectionScoreRows($assessment));

        // Convert to CSV string
        return $this->arrayToCSV($csvData);
    }

    protected function getHeaderRow(): array {
        return [
            'MNCH BASELINE ASSESSMENT - COMPLETE RAW DATA EXPORT',
            'Generated: ' . now()->format('Y-m-d H:i:s')
        ];
    }

    protected function getFacilityRows(Assessment $assessment): array {
        return [
            ['FACILITY INFORMATION'],
            ['Facility Name', $assessment->facility->name],
            ['MFL Code', $assessment->facility->mfl_code ?? 'N/A'],
            ['County', $assessment->facility->subcounty->county->name ?? 'N/A'],
            ['Sub-County', $assessment->facility->subcounty->name ?? 'N/A'],
            ['Level', $assessment->facility->level ?? 'N/A'],
            ['Ownership', $assessment->facility->ownership ?? 'N/A'],
            ['Contact', $assessment->facility->phone ?? $assessment->facility->email ?? 'N/A'],
        ];
    }

    protected function getAssessmentDetailsRows(Assessment $assessment): array {
        return [
            ['ASSESSMENT DETAILS'],
            ['Assessment ID', $assessment->id],
            ['Assessment Type', ucfirst($assessment->assessment_type)],
            ['Assessment Date', $assessment->assessment_date->format('Y-m-d')],
            ['Status', ucfirst($assessment->status)],
            ['Assessor Name', $assessment->assessor_name],
            ['Assessor Contact', $assessment->assessor_contact],
            ['Created At', $assessment->created_at->format('Y-m-d H:i:s')],
            ['Completed At', $assessment->completed_at?->format('Y-m-d H:i:s') ?? 'Not completed'],
            ['Overall Score', $assessment->overall_score ?? 'N/A'],
            ['Overall Percentage', $assessment->overall_percentage ? number_format($assessment->overall_percentage, 2) . '%' : 'N/A'],
            ['Overall Grade', strtoupper($assessment->overall_grade ?? 'N/A')],
        ];
    }

    protected function getQuestionResponseRows(Assessment $assessment, string $sectionCode): array {
        $responses = $assessment->questionResponses()
                ->whereHas('question.section', function ($q) use ($sectionCode) {
                    $q->where('code', $sectionCode);
                })
                ->with('question')
                ->get();

        $rows = [];

        foreach ($responses as $response) {
            $rows[] = [
                $response->question->question_code,
                $response->question->question_text,
                $response->response_value ?? 'N/A',
                $response->score ?? 'N/A',
                $response->explanation ?? '',
                $response->metadata ? json_encode($response->metadata) : '',
            ];
        }

        return $rows;
    }

    protected function getHumanResourceRows(Assessment $assessment): array {
        $responses = $assessment->humanResourceResponses()->with('cadre')->get();

        $rows = [];

        foreach ($responses as $response) {
            $rows[] = [
                $response->cadre->name,
                $response->total_in_facility ?? 0,
                $response->etat_plus ?? 0,
                $response->comprehensive_newborn_care ?? 0,
                $response->imnci ?? 0,
                $response->type_1_diabetes ?? 0,
                $response->essential_newborn_care ?? 0,
            ];
        }

        // Add totals row
        if (!empty($rows)) {
            $totals = [
                'TOTAL',
                $responses->sum('total_in_facility'),
                $responses->sum('etat_plus'),
                $responses->sum('comprehensive_newborn_care'),
                $responses->sum('imnci'),
                $responses->sum('type_1_diabetes'),
                $responses->sum('essential_newborn_care'),
            ];
            $rows[] = $totals;
        }

        return $rows;
    }

    protected function getCommodityRows(Assessment $assessment): array {
        $responses = $assessment->commodityResponses()
                ->with(['commodity.category', 'department'])
                ->get()
                ->sortBy([
                    ['department.order', 'asc'],
                    ['commodity.category.order', 'asc'],
                    ['commodity.order', 'asc'],
        ]);

        $rows = [];

        foreach ($responses as $response) {
            $rows[] = [
                $response->department->name ?? 'N/A',
                $response->commodity->category->name ?? 'N/A',
                $response->commodity->name ?? 'N/A',
                $response->available ? 'Yes' : 'No',
                $response->score ?? 0,
            ];
        }

        return $rows;
    }

    protected function getQualityOfCareRows(Assessment $assessment): array {
        $responses = $assessment->questionResponses()
                ->whereHas('question.section', function ($q) {
                    $q->where('code', 'quality_of_care');
                })
                ->with('question')
                ->get();

        $rows = [];

        foreach ($responses as $response) {
            $rows[] = [
                $response->question->question_code,
                $response->question->question_text,
                $response->question->question_type,
                $response->response_value ?? 'N/A',
                $response->score ?? 'N/A',
                $response->explanation ?? '',
                $response->metadata ? json_encode($response->metadata) : '',
            ];
        }

        return $rows;
    }

    protected function getSectionScoreRows(Assessment $assessment): array {
        $sectionScores = $assessment->sectionScores()->with('section')->get();

        $rows = [];

        foreach ($sectionScores as $score) {
            $rows[] = [
                $score->section->name,
                $score->total_score ?? 0,
                $score->max_score ?? 0,
                $score->percentage ? number_format($score->percentage, 2) . '%' : 'N/A',
                $score->total_questions ?? 0,
                $score->answered_questions ?? 0,
                $score->skipped_questions ?? 0,
            ];
        }

        return $rows;
    }

    protected function arrayToCSV(array $data): string {
        $output = fopen('php://temp', 'r+');

        foreach ($data as $row) {
            fputcsv($output, $row);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    /**
     * Export multiple assessments (bulk export)
     */
    public function exportMultipleAssessments($assessments): string {
        $csvData = [];

        // Add header
        $csvData[] = ['MNCH ASSESSMENTS - BULK EXPORT', 'Generated: ' . now()->format('Y-m-d H:i:s')];
        $csvData[] = [''];

        // Add column headers
        $csvData[] = [
            'Assessment ID',
            'Facility Name',
            'MFL Code',
            'County',
            'Sub-County',
            'Assessment Type',
            'Assessment Date',
            'Status',
            'Assessor',
            'Overall Score',
            'Overall Percentage',
            'Overall Grade',
            'Infrastructure %',
            'Skills Lab %',
            'Human Resources %',
            'Health Products %',
            'Information Systems %',
            'Quality of Care %',
            'Completed At',
        ];

        foreach ($assessments as $assessment) {
            $assessment->load([
                'facility.subcounty.county',
                'sectionScores.section'
            ]);

            // Get section scores as associative array
            $sectionScores = $assessment->sectionScores->keyBy('section.code');

            $csvData[] = [
                $assessment->id,
                $assessment->facility->name,
                $assessment->facility->mfl_code ?? 'N/A',
                $assessment->facility->subcounty->county->name ?? 'N/A',
                $assessment->facility->subcounty->name ?? 'N/A',
                ucfirst($assessment->assessment_type),
                $assessment->assessment_date->format('Y-m-d'),
                ucfirst($assessment->status),
                $assessment->assessor_name,
                $assessment->overall_score ?? 'N/A',
                $assessment->overall_percentage ? number_format($assessment->overall_percentage, 2) : 'N/A',
                strtoupper($assessment->overall_grade ?? 'N/A'),
                $sectionScores->get('infrastructure')?->percentage ? number_format($sectionScores->get('infrastructure')->percentage, 2) : 'N/A',
                $sectionScores->get('skills_lab')?->percentage ? number_format($sectionScores->get('skills_lab')->percentage, 2) : 'N/A',
                $sectionScores->get('human_resources')?->percentage ? number_format($sectionScores->get('human_resources')->percentage, 2) : 'N/A',
                $sectionScores->get('health_products')?->percentage ? number_format($sectionScores->get('health_products')->percentage, 2) : 'N/A',
                $sectionScores->get('information_systems')?->percentage ? number_format($sectionScores->get('information_systems')->percentage, 2) : 'N/A',
                $sectionScores->get('quality_of_care')?->percentage ? number_format($sectionScores->get('quality_of_care')->percentage, 2) : 'N/A',
                $assessment->completed_at?->format('Y-m-d H:i:s') ?? 'Not completed',
            ];
        }

        return $this->arrayToCSV($csvData);
    }
}
