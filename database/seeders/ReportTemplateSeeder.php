<?php

namespace Database\Seeders;

use App\Models\Indicator;
use App\Models\ReportTemplate;
use Illuminate\Database\Seeder;

class ReportTemplateSeeder extends Seeder
{
    private const CODE = 'MONTHLY_FACILITY_INDICATORS';

    public function run(): void
    {
        $template = ReportTemplate::firstOrCreate(
            ['code' => self::CODE],
            [
                'name' => 'Monthly Facility Indicators Report',
                'description' => 'Monthly facility-level report covering all current indicators (newborn and pediatric/child care modules).',
                'report_type' => 'general',
                'frequency' => 'monthly',
                'is_active' => true,
            ]
        );

        $indicators = Indicator::orderBy('sort_order')->orderBy('id')->get();

        $syncData = $indicators->mapWithKeys(fn (Indicator $indicator, int $index) => [
            $indicator->id => ['sort_order' => $index, 'is_required' => true],
        ])->toArray();

        $template->indicators()->syncWithoutDetaching($syncData);

        $this->command?->info(sprintf(
            'Monthly Facility Indicators Report template ready with %d indicator(s) attached.',
            $indicators->count()
        ));
    }
}
