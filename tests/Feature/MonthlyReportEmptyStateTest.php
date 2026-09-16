<?php

namespace Tests\Feature;

use App\Filament\Resources\MonthlyReportResource\Pages\CreateMonthlyReport;
use App\Models\ReportTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MonthlyReportEmptyStateTest extends TestCase
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

    public function test_no_helper_text_is_shown_when_a_report_template_already_exists(): void
    {
        $this->actingAsAdmin();
        ReportTemplate::create(['name' => 'T', 'code' => 'T1', 'report_type' => 'general', 'is_active' => true]);

        Livewire::test(CreateMonthlyReport::class)
            ->assertSuccessful()
            ->assertDontSee('No report templates yet');
    }

    public function test_helper_text_with_a_link_is_shown_when_no_report_template_exists(): void
    {
        $this->actingAsAdmin();

        Livewire::test(CreateMonthlyReport::class)
            ->assertSuccessful()
            ->assertSee('No report templates yet');
    }
}
