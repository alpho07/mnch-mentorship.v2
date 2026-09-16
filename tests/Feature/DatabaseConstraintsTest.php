<?php

namespace Tests\Feature;

use App\Models\AssessmentType;
use App\Models\Facility;
use App\Models\MonthlyReport;
use App\Models\ReportTemplate;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseConstraintsTest extends TestCase
{
    use RefreshDatabase;

    public function test_monthly_reports_enforces_one_report_per_facility_template_and_period(): void
    {
        $facility = Facility::factory()->create();
        $template = ReportTemplate::create(['name' => 'T', 'code' => 'T1', 'report_type' => 'general']);
        $creator = User::factory()->create();
        $period = now()->startOfMonth();

        MonthlyReport::create([
            'facility_id' => $facility->id,
            'report_template_id' => $template->id,
            'created_by' => $creator->id,
            'reporting_period' => $period,
        ]);

        $this->expectException(QueryException::class);

        MonthlyReport::create([
            'facility_id' => $facility->id,
            'report_template_id' => $template->id,
            'created_by' => $creator->id,
            'reporting_period' => $period,
        ]);
    }

    public function test_monthly_reports_cascades_on_facility_deletion(): void
    {
        $facility = Facility::factory()->create();
        $template = ReportTemplate::create(['name' => 'T', 'code' => 'T2', 'report_type' => 'general']);
        $creator = User::factory()->create();

        $report = MonthlyReport::create([
            'facility_id' => $facility->id,
            'report_template_id' => $template->id,
            'created_by' => $creator->id,
            'reporting_period' => now()->startOfMonth(),
        ]);

        $facility->forceDelete();

        $this->assertDatabaseMissing('monthly_reports', ['id' => $report->id]);
    }

    public function test_report_templates_code_is_unique(): void
    {
        ReportTemplate::create(['name' => 'A', 'code' => 'DUPLICATE_CODE', 'report_type' => 'general']);

        $this->expectException(QueryException::class);

        ReportTemplate::create(['name' => 'B', 'code' => 'DUPLICATE_CODE', 'report_type' => 'general']);
    }

    public function test_assessment_types_code_is_unique(): void
    {
        AssessmentType::create(['name' => 'A', 'code' => 'DUP_TYPE', 'version' => '1.0', 'is_active' => true]);

        $this->expectException(QueryException::class);

        AssessmentType::create(['name' => 'B', 'code' => 'DUP_TYPE', 'version' => '1.0', 'is_active' => true]);
    }

    public function test_users_email_is_unique(): void
    {
        User::factory()->create(['email' => 'duplicate@example.test']);

        $this->expectException(QueryException::class);

        User::factory()->create(['email' => 'duplicate@example.test']);
    }
}
