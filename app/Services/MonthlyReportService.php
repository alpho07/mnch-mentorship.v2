<?php

namespace App\Services;

use App\Models\MonthlyReport;
use App\Models\IndicatorValue;
use App\Models\Facility;
use App\Models\ReportTemplate;
use Carbon\Carbon;

class MonthlyReportService
{
    public function createMonthlyReport(
        Facility $facility,
        ReportTemplate $template,
        Carbon $reportingPeriod,
        int $createdBy
    ): MonthlyReport {
        // Check if report already exists
        $existingReport = MonthlyReport::where([
            'facility_id' => $facility->id,
            'report_template_id' => $template->id,
            'reporting_period' => $reportingPeriod->format('Y-m-01'),
        ])->first();

        if ($existingReport) {
            throw new \Exception('A report for this facility, template, and period already exists.');
        }

        // Create the report
        $report = MonthlyReport::create([
            'facility_id' => $facility->id,
            'report_template_id' => $template->id,
            'reporting_period' => $reportingPeriod->format('Y-m-01'),
            'status' => 'draft',
            'created_by' => $createdBy,
        ]);

        // Create indicator value placeholders
        foreach ($template->indicators as $indicator) {
            IndicatorValue::create([
                'monthly_report_id' => $report->id,
                'indicator_id' => $indicator->id,
            ]);
        }

        return $report;
    }

    public function bulkCreateReports(
        array $facilityIds,
        int $templateId,
        Carbon $reportingPeriod,
        int $createdBy
    ): array {
        $template = ReportTemplate::findOrFail($templateId);
        $results = [];

        foreach ($facilityIds as $facilityId) {
            try {
                $facility = Facility::findOrFail($facilityId);
                $report = $this->createMonthlyReport(
                    $facility,
                    $template,
                    $reportingPeriod,
                    $createdBy
                );
                $results['success'][] = $facility->name;
            } catch (\Exception $e) {
                $results['errors'][] = [
                    'facility' => Facility::find($facilityId)?->name ?? "Facility ID: $facilityId",
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    public function calculateReportingCompleteness(
        array $facilityIds,
        Carbon $reportingPeriod
    ): array {
        $period = $reportingPeriod->format('Y-m-01');

        $expectedReports = \DB::table('facility_report_templates')
            ->join('report_templates', 'facility_report_templates.report_template_id', '=', 'report_templates.id')
            ->whereIn('facility_report_templates.facility_id', $facilityIds)
            ->where('report_templates.is_active', true)
            ->where('facility_report_templates.start_date', '<=', $period)
            ->where(function ($query) use ($period) {
                $query->whereNull('facility_report_templates.end_date')
                      ->orWhere('facility_report_templates.end_date', '>=', $period);
            })
            ->count();

        $submittedReports = MonthlyReport::whereIn('facility_id', $facilityIds)
            ->where('reporting_period', $period)
            ->whereIn('status', ['submitted', 'approved'])
            ->count();

        return [
            'expected' => $expectedReports,
            'submitted' => $submittedReports,
            'percentage' => $expectedReports > 0 ? ($submittedReports / $expectedReports) * 100 : 0,
        ];
    }
}
