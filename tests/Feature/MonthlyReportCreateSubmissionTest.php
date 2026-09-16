<?php

namespace Tests\Feature;

use App\Filament\Resources\MonthlyReportResource\Pages\CreateMonthlyReport;
use App\Models\Facility;
use App\Models\IndicatorValue;
use App\Models\MonthlyReport;
use App\Models\ReportTemplate;
use App\Models\User;
use Database\Seeders\ReportTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MonthlyReportCreateSubmissionTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $user = User::factory()->create(['name' => 'Admin']);
        foreach (['create_monthly::report', 'view_any_monthly::report', 'view_monthly::report'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
        $user->givePermissionTo(['create_monthly::report', 'view_any_monthly::report', 'view_monthly::report']);
        $this->actingAs($user);

        return $user;
    }

    public function test_submitting_the_create_form_saves_a_monthly_report_and_its_indicator_values(): void
    {
        $this->actingAsAdmin();
        $facility = Facility::factory()->create();

        $reportTypeId = DB::table('indicator_report_types')->insertGetId([
            'code' => 'submission_test_type', 'name' => 'Test Type', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $groupId = DB::table('indicator_groups')->insertGetId([
            'report_type_id' => $reportTypeId, 'name' => 'Test Group', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('indicators')->insert([
            'group_id' => $groupId, 'code' => 'SUB-1', 'name' => 'Submission Indicator',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        (new ReportTemplateSeeder())->run();
        $template = ReportTemplate::where('code', 'MONTHLY_FACILITY_INDICATORS')->firstOrFail();

        Livewire::test(CreateMonthlyReport::class)
            ->fillForm([
                'facility_id' => $facility->id,
                'report_template_id' => $template->id,
                'reporting_period' => now()->startOfMonth()->format('Y-m-d'),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseCount('monthly_reports', 1);
        $report = MonthlyReport::firstOrFail();
        $this->assertSame($facility->id, $report->facility_id);

        $this->assertGreaterThan(0, IndicatorValue::where('monthly_report_id', $report->id)->count());
    }
}
