<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Mentorship program targeting relies on facility_type_id to identify
     * referral hospitals. A number of facilities whose name clearly marks
     * them as one ("X County Referral Hospital", "X Sub County Referral
     * Hospital", ...) are misclassified under an unrelated type (Sub
     * County Hospital, KMTC, etc). Reclassify any facility named like a
     * referral hospital that isn't already under one of the referral-family
     * types (County/Sub-County Referral, Teaching & Referral, National
     * Referral) as County Referral Hospital. Idempotent.
     */
    public function up(): void
    {
        $referralTypeId = DB::table('facility_types')->where('name', 'COUNTY REFERRAL HOSPITAL')->value('id');

        if (! $referralTypeId) {
            return;
        }

        $referralFamilyIds = DB::table('facility_types')
            ->where('name', 'like', '%REFERRAL%')
            ->pluck('id')
            ->all();

        DB::table('facilities')
            ->where('name', 'like', '%referral%')
            ->where(function ($query) use ($referralFamilyIds) {
                $query->whereNull('facility_type_id')
                    ->orWhereNotIn('facility_type_id', $referralFamilyIds);
            })
            ->update(['facility_type_id' => $referralTypeId]);
    }

    /**
     * Reverse the migrations.
     *
     * Not reversible — the facility_type_id values being corrected here
     * were themselves data errors (e.g. a referral hospital tagged as a
     * KMTC training college), so there is no meaningful prior state to
     * restore.
     */
    public function down(): void
    {
        //
    }
};
