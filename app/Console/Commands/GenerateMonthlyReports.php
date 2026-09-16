<?php
namespace App\Console\Commands;

use App\Models\Facility;
use App\Models\ReportTemplate;
use App\Services\MonthlyReportService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateMonthlyReports extends Command
{
    protected $signature = 'reports:generate-monthly
                           {--period= : Reporting period (YYYY-MM, defaults to current month)}
                           {--template= : Specific template ID to generate}
                           {--facility= : Specific facility ID to generate for}';

    protected $description = 'Generate monthly reports for facilities';

    public function handle(MonthlyReportService $reportService): int
    {
        $period = $this->option('period')
            ? Carbon::createFromFormat('Y-m', $this->option('period'))
            : now();

        $templateQuery = ReportTemplate::where('is_active', true);
        if ($this->option('template')) {
            $templateQuery->where('id', $this->option('template'));
        }

        $facilityQuery = Facility::query();
        if ($this->option('facility')) {
            $facilityQuery->where('id', $this->option('facility'));
        }

        $templates = $templateQuery->get();
        $facilities = $facilityQuery->get();

        $this->info("Generating reports for period: " . $period->format('F Y'));

        foreach ($templates as $template) {
            $this->info("Processing template: {$template->name}");

            // Get facilities assigned to this template
            $assignedFacilities = $template->facilities()
                ->wherePivot('start_date', '<=', $period->format('Y-m-01'))
                ->where(function ($query) use ($period) {
                    $query->wherePivot('end_date', '>=', $period->format('Y-m-01'))
                          ->orWherePivot('end_date', null);
                })
                ->get();

            if ($this->option('facility')) {
                $assignedFacilities = $assignedFacilities->where('id', $this->option('facility'));
            }

            foreach ($assignedFacilities as $facility) {
                try {
                    $reportService->createMonthlyReport(
                        $facility,
                        $template,
                        $period,
                        1 // System user
                    );
                    $this->line("✓ Created report for {$facility->name}");
                } catch (\Exception $e) {
                    $this->error("✗ Failed to create report for {$facility->name}: {$e->getMessage()}");
                }
            }
        }

        $this->info('Monthly report generation completed!');
        return Command::SUCCESS;
    }
}
