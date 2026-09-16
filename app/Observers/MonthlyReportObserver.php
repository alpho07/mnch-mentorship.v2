<?php
// MonthlyReportObserver.php
namespace App\Observers;

use App\Models\MonthlyReport;
use App\Models\IndicatorValue;
use Illuminate\Support\Facades\Log;

class MonthlyReportObserver
{
    public function created(MonthlyReport $monthlyReport): void
    {
        // Only create indicator values if:
        // 1. No indicator values exist for this report
        // 2. This is not being called from Filament form creation
        $existingCount = IndicatorValue::where('monthly_report_id', $monthlyReport->id)->count();
        
        // Check if we're in a Filament context (form creation)
        $isFilamentRequest = request()->is('admin/*') || 
                           str_contains(request()->header('referer', ''), '/admin/') ||
                           app()->runningInConsole() === false;
        
        Log::info('MonthlyReport created', [
            'report_id' => $monthlyReport->id,
            'existing_count' => $existingCount,
            'is_filament' => $isFilamentRequest,
            'request_path' => request()->path()
        ]);
        
        // Only create if no values exist AND this is not from Filament
        if ($existingCount === 0 && !$isFilamentRequest) {
            Log::info('Creating indicator values via observer', ['report_id' => $monthlyReport->id]);
            
            try {
                $template = $monthlyReport->reportTemplate;
                
                $indicatorValues = [];
                foreach ($template->indicators as $indicator) {
                    $indicatorValues[] = [
                        'monthly_report_id' => $monthlyReport->id,
                        'indicator_id' => $indicator->id,
                        'numerator' => null,
                        'denominator' => null,
                        'calculated_value' => null,
                        'comments' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                
                if (!empty($indicatorValues)) {
                    IndicatorValue::insert($indicatorValues);
                    Log::info('Indicator values created via observer', [
                        'report_id' => $monthlyReport->id,
                        'count' => count($indicatorValues)
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Error creating indicator values in observer', [
                    'report_id' => $monthlyReport->id,
                    'error' => $e->getMessage()
                ]);
            }
        } else {
            Log::info('Skipping indicator value creation in observer', [
                'report_id' => $monthlyReport->id,
                'reason' => $existingCount > 0 ? 'values_exist' : 'filament_request'
            ]);
        }
    }

    public function updating(MonthlyReport $monthlyReport): void
    {
        // Auto-set submission timestamp when status changes to submitted
        if ($monthlyReport->isDirty('status') && $monthlyReport->status === 'submitted') {
            $monthlyReport->submitted_at = now();
        }

        // Auto-set approval timestamp and user when status changes to approved
        if ($monthlyReport->isDirty('status') && $monthlyReport->status === 'approved') {
            $monthlyReport->approved_at = now();
            if (!$monthlyReport->approved_by) {
                $monthlyReport->approved_by = auth()->id();
            }
        }
    }
}