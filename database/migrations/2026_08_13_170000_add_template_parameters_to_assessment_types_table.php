<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Moves {{token}} interpolation parameters out of the generic
     * `metadata` column into their own dedicated column. `metadata` is
     * edited directly by AssessmentTypeResource's "Additional Metadata"
     * KeyValue field, which requires every value to be a flat string —
     * storing a nested `parameters` array there (as the original 2026
     * seeder did) crashes that field's dehydration with a TypeError the
     * moment an admin opens and saves the template edit form, for ANY
     * field on the record (not just metadata itself).
     */
    public function up(): void
    {
        Schema::table('assessment_types', function (Blueprint $table) {
            $table->json('template_parameters')->nullable()->after('metadata');
        });

        // Migrate any already-seeded metadata.parameters data (the live
        // 2026 STANDARD_FACILITY_ASSESSMENT_2026 row) to the new column,
        // then strip `parameters` out of metadata so the existing KeyValue
        // field stops choking on it immediately.
        foreach (DB::table('assessment_types')->whereNotNull('metadata')->get() as $type) {
            $metadata = json_decode($type->metadata, true) ?? [];

            if (! array_key_exists('parameters', $metadata)) {
                continue;
            }

            $parameters = $metadata['parameters'];
            unset($metadata['parameters']);

            DB::table('assessment_types')->where('id', $type->id)->update([
                'template_parameters' => json_encode($parameters),
                'metadata' => $metadata === [] ? null : json_encode($metadata),
            ]);
        }
    }

    public function down(): void
    {
        foreach (DB::table('assessment_types')->whereNotNull('template_parameters')->get() as $type) {
            $metadata = json_decode($type->metadata, true) ?? [];
            $metadata['parameters'] = json_decode($type->template_parameters, true);

            DB::table('assessment_types')->where('id', $type->id)->update([
                'metadata' => json_encode($metadata),
            ]);
        }

        Schema::table('assessment_types', function (Blueprint $table) {
            $table->dropColumn('template_parameters');
        });
    }
};
