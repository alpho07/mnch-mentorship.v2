<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * IndicatorValue's $fillable (monthly_report_id, numerator, denominator,
     * calculated_value, comments) has never matched any of these 4 columns
     * on the real indicator_values table — that table only ever had the
     * period_id-based shape (period_id, numerator_value, denominator_value,
     * computed_percentage, comment) serving the separate, working DHIS2/
     * indicator_report_periods reporting flow (43 real rows there today).
     *
     * Found while building the Monthly Reports empty-state fix:
     * CreateMonthlyReport's indicatorValues Repeater crashes on page load
     * (not just on submit) because it queries indicator_values by
     * monthly_report_id, which doesn't exist anywhere. Adding these 4
     * columns as nullable is purely additive — the existing 43 period_id-
     * based rows are completely unaffected (they simply leave these new
     * columns NULL), and the two "shapes" coexist in the same table going
     * forward, matching what the Monthly Reports code has always assumed.
     *
     * period_id is also relaxed to nullable: it's currently NOT NULL with
     * no default, which would reject every Monthly-Report-sourced insert
     * (that flow never sets period_id). Existing 43 rows all have a real
     * period_id already, so this is a pure relaxation, not a data change —
     * nothing existing becomes invalid. MySQL's unique index on
     * (period_id, indicator_id) allows multiple NULLs, so several Monthly
     * Report indicator values sharing an indicator_id with a NULL
     * period_id won't collide.
     */
    public function up(): void
    {
        Schema::table('indicator_values', function (Blueprint $table) {
            $table->unsignedBigInteger('period_id')->nullable()->change();

            if (! Schema::hasColumn('indicator_values', 'monthly_report_id')) {
                $table->foreignId('monthly_report_id')->nullable()->after('id')
                    ->constrained('monthly_reports')->cascadeOnDelete();
            }
            if (! Schema::hasColumn('indicator_values', 'numerator')) {
                $table->integer('numerator')->nullable()->after('indicator_id');
            }
            if (! Schema::hasColumn('indicator_values', 'denominator')) {
                $table->integer('denominator')->nullable()->after('numerator');
            }
            if (! Schema::hasColumn('indicator_values', 'calculated_value')) {
                $table->decimal('calculated_value', 8, 4)->nullable()->after('denominator');
            }
            if (! Schema::hasColumn('indicator_values', 'comments')) {
                $table->text('comments')->nullable()->after('calculated_value');
            }
        });
    }

    public function down(): void
    {
        Schema::table('indicator_values', function (Blueprint $table) {
            $table->dropConstrainedForeignId('monthly_report_id');
            $table->dropColumn(['numerator', 'denominator', 'calculated_value', 'comments']);
        });
    }
};
