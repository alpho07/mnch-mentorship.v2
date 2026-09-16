<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Broad name-pattern facility classification pass. 9,958 of 10,700
     * facilities (93%) carry no real facility_type_id ("NOT CLASSIFIED" or
     * NULL) — this infers a type from clear naming conventions used in the
     * KMHFL-derived facility list, so reporting/dashboards that group or
     * filter by facility_type_id (e.g. the referral-hospital mentorship
     * targeting) actually have something to work with.
     *
     * The referral-family rules (national/teaching/sub-county/county
     * referral) are authoritative and re-evaluate every matching name,
     * even if already classified — this dataset has real pre-existing
     * errors in that family (e.g. "Iten County Referral Hospital" tagged
     * as a KMTC training college, "Busia County Refferal Hospital" the
     * same, several "Sub County Referral Hospital" facilities tagged
     * under County Referral Hospital instead of Sub County Hospital).
     * Everything else (Health Centre, Dispensary, Faith Based, KMTC,
     * KEMRI, CHMT, County HQ, Diabetes Centre) only fills in facilities
     * that are still unclassified, to avoid touching unrelated manually-
     * set data. "Clinic", "Nursing Home", "Maternity", and generic
     * "Medical Centre" have no clean KMHFL equivalent and are deliberately
     * left unclassified rather than guessed at. Idempotent.
     */
    public function up(): void
    {
        $typeId = fn (string $name) => DB::table('facility_types')->where('name', $name)->value('id');

        $nationalReferral = $typeId('NATIONAL REFERRAL HOSPITAL');
        $teachingReferral = $typeId('TEACHING & REFERRAL HOSPITAL');
        $subCountyHospital = $typeId('SUB COUNTY HOSPITAL');
        $countyReferral = $typeId('COUNTY REFERRAL HOSPITAL');
        $healthCentre = $typeId('HEALTH CENTRE');
        $dispensary = $typeId('DISPENSARY');
        $missionHospital = $typeId('MISSION HOSPITAL');
        $faithBased = $typeId('FAITH BASED ORGANIZATION');
        $kmtc = $typeId('KENYA MEDICAL TRAINING COLLEGE(KMTC)');
        $kemri = $typeId('KEMRI');
        $chmt = $typeId('COUNTY HEALTH MANAGEMENT TEAM (CHMT)');
        $countyHq = $typeId('COUNTY HEADQUARTERS');
        $diabetesCentre = $typeId('DIABETES CENTRE');

        // "Referral" is frequently misspelled "Refferal" in this dataset —
        // every referral-family match below checks both spellings.
        $referralLike = function ($q) {
            $q->where('name', 'like', '%referral%')->orWhere('name', 'like', '%refferal%');
        };

        // ── Reset facilities tagged as a KMTC training college whose name
        // doesn't actually say so (e.g. "Bondo District Hospital") back to
        // unclassified, so the rules below can reclassify them properly.
        if ($kmtc) {
            DB::table('facilities')
                ->where('facility_type_id', $kmtc)
                ->where('name', 'not like', '%kmtc%')
                ->where('name', 'not like', '%medical training college%')
                ->update(['facility_type_id' => 16]);
        }

        // ── Referral family — authoritative, re-evaluates every matching
        // name regardless of current classification (most specific first).

        // National Referral Hospital
        if ($nationalReferral) {
            DB::table('facilities')
                ->where('name', 'like', '%national%')
                ->where($referralLike)
                ->update(['facility_type_id' => $nationalReferral]);
        }

        // Teaching & Referral Hospital
        if ($teachingReferral) {
            DB::table('facilities')
                ->where('name', 'like', '%teaching%')
                ->where($referralLike)
                ->update(['facility_type_id' => $teachingReferral]);
        }

        // Sub County Hospital — "Sub County/Sub-County Hospital", "Sub
        // County/Sub-County Referral Hospital", "Sub District Hospital",
        // or the pre-devolution "District Hospital" naming. Excludes rows
        // already claimed by National/Teaching above.
        if ($subCountyHospital) {
            DB::table('facilities')
                ->where(function ($q) {
                    $q->where('name', 'regexp', 'sub[- ]?county|sub[- ]?district')
                        ->orWhere('name', 'like', '%district hospital%');
                })
                ->where(fn ($q) => $q->where('name', 'not like', '%national%')->where('name', 'not like', '%teaching%'))
                ->update(['facility_type_id' => $subCountyHospital]);
        }

        // County Referral Hospital — remaining "Referral"/"Refferal" names
        // not already claimed by National/Teaching/Sub County above.
        if ($countyReferral) {
            DB::table('facilities')
                ->where($referralLike)
                ->where('name', 'not regexp', 'sub[- ]?county|sub[- ]?district')
                ->where(fn ($q) => $q->where('name', 'not like', '%national%')->where('name', 'not like', '%teaching%'))
                ->update(['facility_type_id' => $countyReferral]);
        }

        // ── Everything else — only fills in still-unclassified facilities.
        $unclassified = fn ($query) => $query->where(function ($q) {
            $q->whereNull('facility_type_id')->orWhere('facility_type_id', 16);
        });

        // Health Centre
        if ($healthCentre) {
            $unclassified(DB::table('facilities')->where('name', 'like', '%health cent%'))
                ->update(['facility_type_id' => $healthCentre]);
        }

        // Dispensary
        if ($dispensary) {
            $unclassified(DB::table('facilities')->where('name', 'like', '%dispensary%'))
                ->update(['facility_type_id' => $dispensary]);
        }

        // Mission Hospital
        if ($missionHospital) {
            $unclassified(DB::table('facilities')
                ->where('name', 'like', '%mission%')
                ->where('name', 'like', '%hospital%'))
                ->update(['facility_type_id' => $missionHospital]);
        }

        // Faith Based Organization — denominational markers, plus
        // "Mission" facilities that aren't hospitals.
        if ($faithBased) {
            $unclassified(DB::table('facilities')
                ->where(function ($q) {
                    $q->where('name', 'like', '%catholic%')
                        ->orWhere('name', 'like', '%mission%')
                        ->orWhere('name', 'regexp', '\\bAIC\\b')
                        ->orWhere('name', 'like', '%PCEA%')
                        ->orWhere('name', 'regexp', '\\bSDA\\b')
                        ->orWhere('name', 'regexp', '\\bACK\\b');
                }))
                ->update(['facility_type_id' => $faithBased]);
        }

        // Kenya Medical Training College
        if ($kmtc) {
            $unclassified(DB::table('facilities')
                ->where(function ($q) {
                    $q->where('name', 'like', '%kmtc%')
                        ->orWhere('name', 'like', '%medical training college%');
                }))
                ->update(['facility_type_id' => $kmtc]);
        }

        // KEMRI
        if ($kemri) {
            $unclassified(DB::table('facilities')->where('name', 'like', '%kemri%'))
                ->update(['facility_type_id' => $kemri]);
        }

        // County Health Management Team
        if ($chmt) {
            $unclassified(DB::table('facilities')
                ->where(function ($q) {
                    $q->where('name', 'like', '%chmt%')
                        ->orWhere('name', 'like', '%county health management team%');
                }))
                ->update(['facility_type_id' => $chmt]);
        }

        // County Headquarters
        if ($countyHq) {
            $unclassified(DB::table('facilities')
                ->where(function ($q) {
                    $q->where('name', 'like', '%county headquarters%')
                        ->orWhere('name', 'like', '%county hq%');
                }))
                ->update(['facility_type_id' => $countyHq]);
        }

        // Diabetes Centre
        if ($diabetesCentre) {
            $unclassified(DB::table('facilities')->where('name', 'like', '%diabetes cent%'))
                ->update(['facility_type_id' => $diabetesCentre]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * Not reversible — nearly all rows touched here started with no
     * classification at all ("NOT CLASSIFIED" / NULL), and the referral-
     * family corrections replace pre-existing data errors, so there is no
     * meaningful prior state to restore.
     */
    public function down(): void
    {
        //
    }
};
