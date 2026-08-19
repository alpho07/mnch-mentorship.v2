<?php

use App\Models\Program;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Makes certificate issuance scope (per-class vs whole-program)
     * configurable per program instead of hardcoded to "is this EmONC".
     * Defaults to 'per_class' and backfills every currently-EmONC program to
     * 'per_program' below, so behavior is unchanged until someone deliberately
     * reconfigures a program.
     */
    public function up(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->enum('certificate_scope', ['per_class', 'per_program'])
                ->default('per_class')
                ->after('is_active');
        });

        Program::query()->get()->each(function (Program $program) {
            if ($program->isEmonc()) {
                $program->update(['certificate_scope' => 'per_program']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->dropColumn('certificate_scope');
        });
    }
};
