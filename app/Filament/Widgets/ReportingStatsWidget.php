<?php
// ReportingStatsWidget.php
namespace App\Filament\Widgets;

use App\Models\MonthlyReport;
use App\Models\ReportTemplate;
use App\Models\Facility;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class ReportingStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $currentMonth = now()->format('Y-m-01');
        $user = auth()->user();

        // Get facilities the user has access to
        $facilityIds = Facility::pluck('id'); /*$user->isAboveSite()
            ? Facility::pluck('id')
            : $user->scopedFacilityIds();*/

        // Current month reports
        $currentMonthReports = MonthlyReport::where('reporting_period', $currentMonth)
            ->whereIn('facility_id', $facilityIds)
            ->count();

        // Expected reports for current month
        $expectedReports = DB::table('facility_report_templates')
            ->join('report_templates', 'facility_report_templates.report_template_id', '=', 'report_templates.id')
            ->whereIn('facility_report_templates.facility_id', $facilityIds)
            ->where('report_templates.is_active', true)
            ->where('facility_report_templates.start_date', '<=', $currentMonth)
            ->where(function ($query) use ($currentMonth) {
                $query->whereNull('facility_report_templates.end_date')
                      ->orWhere('facility_report_templates.end_date', '>=', $currentMonth);
            })
            ->count();

        // Pending approvals
        $pendingApprovals = MonthlyReport::where('status', 'submitted')
            ->whereIn('facility_id', $facilityIds)
            ->count();

        // Completion rate
        $completionRate = $expectedReports > 0
            ? round(($currentMonthReports / $expectedReports) * 100, 1)
            : 0;

        return [
            Stat::make('Current Month Reports', $currentMonthReports . ' / ' . $expectedReports)
                ->description('Reports submitted for ' . now()->format('F Y'))
                ->descriptionIcon('heroicon-m-clipboard-document-list')
                ->color($completionRate >= 80 ? 'success' : ($completionRate >= 50 ? 'warning' : 'danger')),

            Stat::make('Completion Rate', $completionRate . '%')
                ->description('Monthly reporting completion')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color($completionRate >= 80 ? 'success' : ($completionRate >= 50 ? 'warning' : 'danger')),

            Stat::make('Pending Approvals', $pendingApprovals)
                ->description('Reports awaiting approval')
                ->descriptionIcon('heroicon-m-clock')
                ->color($pendingApprovals > 10 ? 'warning' : 'success'),

            Stat::make('Active Templates', ReportTemplate::where('is_active', true)->count())
                ->description('Available report templates')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('info'),
        ];
    }
}

