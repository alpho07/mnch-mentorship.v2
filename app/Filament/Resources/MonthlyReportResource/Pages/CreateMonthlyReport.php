<?php
// CreateMonthlyReport.php
namespace App\Filament\Resources\MonthlyReportResource\Pages;

use App\Filament\Resources\MonthlyReportResource;
use App\Models\MonthlyReport;
use App\Models\IndicatorValue;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateMonthlyReport extends CreateRecord
{
    protected static string $resource = MonthlyReportResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data) {
            // Check if report already exists
            $existingReport = MonthlyReport::where([
                'facility_id' => $data['facility_id'],
                'report_template_id' => $data['report_template_id'],
                'reporting_period' => $data['reporting_period'],
            ])->first();

            if ($existingReport) {
                throw new \Exception('A report for this facility, template, and period already exists.');
            }

            // Extract indicator values from form data
            $indicatorValuesData = $data['indicatorValues'] ?? [];
            unset($data['indicatorValues']); // Remove from main data

            // Create the monthly report WITHOUT triggering observer
            $report = new MonthlyReport([
                'facility_id' => $data['facility_id'],
                'report_template_id' => $data['report_template_id'],
                'reporting_period' => $data['reporting_period'],
                'status' => $data['status'] ?? 'draft',
                'comments' => $data['comments'] ?? null,
                'created_by' => auth()->id(),
            ]);

            // Save without firing events
            $report->saveQuietly();

            // Clear any existing indicator values (just in case)
            IndicatorValue::where('monthly_report_id', $report->id)->delete();

            // Create indicator values from form data
            $createdValues = [];
            foreach ($indicatorValuesData as $indicatorValueData) {
                if (!empty($indicatorValueData['indicator_id'])) {
                    $indicator = \App\Models\Indicator::find($indicatorValueData['indicator_id']);
                    
                    // Calculate the value if numerator is provided
                    $calculatedValue = null;
                    if (isset($indicatorValueData['numerator']) && $indicatorValueData['numerator'] !== null) {
                        $calculatedValue = $indicator?->calculateValue(
                            $indicatorValueData['numerator'],
                            $indicatorValueData['denominator'] ?? null
                        );
                    }

                    $indicatorValue = [
                        'monthly_report_id' => $report->id,
                        'indicator_id' => $indicatorValueData['indicator_id'],
                        'numerator' => $indicatorValueData['numerator'] ?? null,
                        'denominator' => $indicatorValueData['denominator'] ?? null,
                        'calculated_value' => $calculatedValue,
                        'comments' => $indicatorValueData['comments'] ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    $createdValues[] = $indicatorValue;
                }
            }

            // Bulk insert indicator values if any exist from form
            if (!empty($createdValues)) {
                IndicatorValue::insert($createdValues);
            }

            // Ensure all template indicators have values (create missing ones as empty)
            $this->ensureAllIndicatorValuesExist($report);

            return $report;
        });
    }

    protected function ensureAllIndicatorValuesExist(MonthlyReport $report): void
    {
        $template = $report->reportTemplate;
        $existingIndicatorIds = IndicatorValue::where('monthly_report_id', $report->id)
            ->pluck('indicator_id')
            ->toArray();
        
        $missingValues = [];
        foreach ($template->indicators as $indicator) {
            if (!in_array($indicator->id, $existingIndicatorIds)) {
                $missingValues[] = [
                    'monthly_report_id' => $report->id,
                    'indicator_id' => $indicator->id,
                    'numerator' => null,
                    'denominator' => null,
                    'calculated_value' => null,
                    'comments' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        if (!empty($missingValues)) {
            IndicatorValue::insert($missingValues);
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Ensure created_by is set
        $data['created_by'] = auth()->id();
        
        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Monthly report created successfully';
    }
}