<?php

namespace Tests\Feature;

use App\Filament\Resources\MonthlyReportResource\Pages\ListMonthlyReports;
use App\Models\Facility;
use App\Models\MonthlyReport;
use App\Models\ReportTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MonthlyReportResourceScopingTest extends TestCase
{
    use RefreshDatabase;

    private function makeReport(Facility $facility, User $creator): MonthlyReport
    {
        $template = ReportTemplate::firstOrCreate(
            ['code' => 'TEST_TEMPLATE'],
            ['name' => 'Test Template', 'report_type' => 'general']
        );

        return MonthlyReport::create([
            'facility_id' => $facility->id,
            'report_template_id' => $template->id,
            'created_by' => $creator->id,
            'reporting_period' => now()->startOfMonth(),
            'status' => 'draft',
        ]);
    }

    private function grantViewAnyPermission(User $user): void
    {
        Permission::firstOrCreate(['name' => 'view_any_monthly::report', 'guard_name' => 'web']);
        $user->givePermissionTo('view_any_monthly::report');
    }

    public function test_an_above_site_user_sees_monthly_reports_for_every_facility(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
        $this->grantViewAnyPermission($admin);
        $this->actingAs($admin);

        $facilityA = Facility::factory()->create();
        $facilityB = Facility::factory()->create();
        $reportA = $this->makeReport($facilityA, $admin);
        $reportB = $this->makeReport($facilityB, $admin);

        Livewire::test(ListMonthlyReports::class)
            ->assertCanSeeTableRecords([$reportA, $reportB]);
    }

    public function test_a_facility_scoped_user_only_sees_their_own_facilitys_monthly_reports(): void
    {
        $ownFacility = Facility::factory()->create();
        $otherFacility = Facility::factory()->create();

        $scopedUser = User::factory()->create(['facility_id' => $ownFacility->id]);
        $this->grantViewAnyPermission($scopedUser);
        $this->actingAs($scopedUser);

        $ownReport = $this->makeReport($ownFacility, $scopedUser);
        $otherReport = $this->makeReport($otherFacility, $scopedUser);

        Livewire::test(ListMonthlyReports::class)
            ->assertCanSeeTableRecords([$ownReport])
            ->assertCanNotSeeTableRecords([$otherReport]);
    }
}
