<?php

namespace Tests\Unit;

use App\Models\ReportTemplate;
use Database\Seeders\ReportTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReportTemplateSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Raw inserts, not the Indicator model — two different Indicator model
     * classes exist (App\Models\Indicator, App\Models\Indicators\Indicator)
     * with incompatible $fillable lists mapping to the same table. Builds
     * the minimal valid row chain (indicator_report_types -> indicator_groups
     * -> indicators) without depending on either model.
     */
    private function makeIndicator(string $code): int
    {
        $reportTypeId = DB::table('indicator_report_types')->insertGetId([
            'code' => 'test_type_' . $code,
            'name' => 'Test Type',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $groupId = DB::table('indicator_groups')->insertGetId([
            'report_type_id' => $reportTypeId,
            'name' => 'Test Group',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('indicators')->insertGetId([
            'group_id' => $groupId,
            'code' => $code,
            'name' => "Indicator $code",
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_it_creates_one_general_report_template_with_all_indicators_attached(): void
    {
        $this->makeIndicator('IND-1');
        $this->makeIndicator('IND-2');

        (new ReportTemplateSeeder())->run();

        $template = ReportTemplate::where('code', 'MONTHLY_FACILITY_INDICATORS')->first();

        $this->assertNotNull($template);
        $this->assertSame('general', $template->report_type);
        $this->assertSame('monthly', $template->frequency);
        $this->assertTrue($template->is_active);
        $this->assertCount(2, $template->indicators);
    }

    public function test_running_it_twice_does_not_duplicate_the_template_or_indicator_links(): void
    {
        $this->makeIndicator('IND-1');

        (new ReportTemplateSeeder())->run();
        (new ReportTemplateSeeder())->run();

        $this->assertSame(1, ReportTemplate::where('code', 'MONTHLY_FACILITY_INDICATORS')->count());
        $template = ReportTemplate::where('code', 'MONTHLY_FACILITY_INDICATORS')->first();
        $this->assertCount(1, $template->indicators);
    }

    public function test_running_it_again_after_a_new_indicator_is_added_attaches_the_new_one_too(): void
    {
        $this->makeIndicator('IND-1');
        (new ReportTemplateSeeder())->run();

        $this->makeIndicator('IND-2');
        (new ReportTemplateSeeder())->run();

        $template = ReportTemplate::where('code', 'MONTHLY_FACILITY_INDICATORS')->first();
        $this->assertCount(2, $template->indicators);
    }
}
