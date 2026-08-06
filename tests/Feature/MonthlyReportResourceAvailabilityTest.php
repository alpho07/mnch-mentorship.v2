<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MonthlyReportResourceAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_monthly_reports_table_does_not_exist_yet(): void
    {
        $this->assertFalse(
            Schema::hasTable('monthly_reports'),
            'monthly_reports now has a migration and exists — see docs/PHASE1-DISCOVERY-BASELINE.md §9.1a. '
            . 'This test documented a known-broken feature (MonthlyReportResource, MonthlyReportObserver, and '
            . 'the reports:generate-monthly command all reference a table with no migration). Now that it '
            . 'exists: delete this test and replace it with real MonthlyReportResource facility-scoping '
            . 'coverage — see docs/PHASE1-DISCOVERY-BASELINE.md §9.1 for what to test (getEloquentQuery() '
            . 'scoping, canViewAny(), and the EditMonthlyReport canAccessFacility() check all need coverage '
            . 'once the underlying table is real).'
        );
    }
}
