<?php

namespace Database\Seeders;

use App\Models\AssessmentQuestion;
use App\Models\AssessmentSection;
use App\Models\AssessmentType;
use App\Models\AssessmentTypeCategory;
use Illuminate\Database\Seeder;

/**
 * Ports CHAI's REDCap "Post EmONC Training Supportive Supervision" survey
 * into the platform's dynamic assessment engine as a single categorized
 * AssessmentType. Content transcribed from the live survey on 2026-08-11 —
 * see docs/superpowers/specs/2026-08-11-emonc-supportive-supervision-assessment-design.md
 * §8 for the source-of-truth question inventory.
 *
 * Idempotent — every write is updateOrCreate, safe to re-run.
 *
 * Run with:
 *   php artisan db:seed --class=EmoncSupportiveSupervisionSeeder
 */
class EmoncSupportiveSupervisionSeeder extends Seeder
{
    public const TYPE_CODE = 'EMONC_SUPPORTIVE_SUPERVISION';

    private int $order = 0;

    public function run(): void
    {
        $this->seedCategories();
        $type = $this->seedAssessmentType();
        $sectionA = $this->seedSectionA($type);
        $this->seedEmoncCadres();
        app(\App\Services\CadreMatrixSyncService::class)->syncMaternityHrQuestions($sectionA);
        $this->seedSectionB($type);
        $this->seedSectionC($type);
        $this->seedSectionD($type);
        $this->seedSectionE($type);
        $this->seedSectionF($type);
        $this->seedSectionG($type);
        $this->seedSectionH($type);
        $this->seedSectionJ($type);
    }

    private function seedCategories(): void
    {
        $categories = [
            ['name' => 'EmONC', 'description' => 'Emergency Maternal and Newborn Care assessments.', 'order' => 1],
            ['name' => 'Newborn, Infant & Child', 'description' => 'Newborn, infant, and child health assessments.', 'order' => 2],
            ['name' => 'General Facility Readiness', 'description' => 'Catch-all category for facility assessment templates that predate categorization.', 'order' => 0],
        ];

        foreach ($categories as $category) {
            AssessmentTypeCategory::updateOrCreate(
                ['name' => $category['name']],
                array_merge($category, ['is_active' => true])
            );
        }
    }

    private function seedAssessmentType(): AssessmentType
    {
        $category = AssessmentTypeCategory::where('name', 'EmONC')->firstOrFail();

        return AssessmentType::updateOrCreate(
            ['code' => self::TYPE_CODE],
            [
                'name' => 'EmONC Post-Training Supportive Supervision Survey',
                'description' => 'Assesses how facilities apply EmONC training in practice: facility readiness, commodities, emergency kits, referral systems, infection prevention, and gaps/success stories. Ported from CHAI\'s REDCap instrument.',
                'version' => '1.0',
                'is_active' => true,
                'category_id' => $category->id,
            ]
        );
    }

    // ── Shared helpers, used by every seedSectionX() method ────────────────

    private function upsertSection(AssessmentType $type, string $code, string $name, ?string $description, bool $isScored, int $order): AssessmentSection
    {
        $this->order = 0;

        return AssessmentSection::updateOrCreate(
            ['code' => $code],
            [
                'assessment_type_id' => $type->id,
                'name' => $name,
                'description' => $description,
                'section_type' => AssessmentSection::KIND_QUESTION_GROUP,
                'is_scored' => $isScored,
                'order' => $order,
                'is_active' => true,
            ]
        );
    }

    private function nextOrder(): int
    {
        return ++$this->order;
    }

    private function upsertQuestion(AssessmentSection $section, array $attrs): void
    {
        AssessmentQuestion::updateOrCreate(
            ['question_code' => $attrs['code']],
            [
                'assessment_section_id' => $section->id,
                'question_text' => $attrs['text'],
                'help_text' => $attrs['help_text'] ?? null,
                'question_type' => $attrs['type'],
                'options' => $attrs['options'] ?? null,
                'is_required' => false,
                'is_scored' => $attrs['scored'] ?? false,
                'scoring_map' => $attrs['scoring_map'] ?? null,
                'requires_explanation_on' => $attrs['requires_explanation_on'] ?? null,
                // NOT NULL DB column with a default — pass that default
                // explicitly rather than null for question types that never
                // render an explanation field anyway (only buildYesNoField
                // reads this column).
                'explanation_label' => $attrs['explanation_label'] ?? 'Comments/Recommendations',
                'group' => $attrs['group'] ?? null,
                'order' => $attrs['order'],
                'is_active' => true,
            ]
        );
    }

    /** A scored Yes/No question with the survey's standard always-visible-remarks config. */
    private function yesNo(string $code, string $text, int $order, ?string $group = null, ?string $helpText = null): array
    {
        return [
            'code' => $code,
            'text' => $text,
            'type' => 'yes_no',
            'scored' => true,
            'scoring_map' => ['Yes' => 1, 'No' => 0],
            'requires_explanation_on' => ['Yes', 'No'],
            'explanation_label' => 'Remarks',
            'order' => $order,
            'group' => $group,
            'help_text' => $helpText,
        ];
    }

    // ── A. Facility Profile (not scored) ───────────────────────────────────

    private function seedSectionA(AssessmentType $type): AssessmentSection
    {
        $section = $this->upsertSection(
            $type,
            'emonc_facility_context',
            'A. Facility Profile',
            'Facility identity, EmONC training coverage, and human resources in the maternity unit. Facility name, MFL code, county, level, and ownership are shown from the selected facility record and not re-collected here. "Supervisors" from the source survey are not separate fields here — they are this assessment\'s real team lead/members (see the Team Members step on Create Assessment and the "Manage Team" action), the same as every other assessment template.',
            false,
            1
        );

        // Cleanup for environments that ran an earlier version of this
        // seeder: the source survey's free-text "Supervisors" table (Name +
        // Title x3) was initially ported as its own fields, but that data
        // is fully redundant with the assessment's real team lead/member
        // records — removed in favor of the existing team feature rather
        // than duplicating it.
        AssessmentQuestion::where('question_code', 'like', 'EMONC_A_SUP%')->delete();

        $this->upsertQuestion($section, [
            'code' => 'EMONC_A_FACILITY_CATEGORY',
            'text' => 'Facility Category',
            'type' => 'select',
            'options' => ['CEMONC', 'BEMONC'],
            'order' => $this->nextOrder(),
        ]);

        $this->upsertQuestion($section, ['code' => 'EMONC_A_INCHARGE_NAME', 'text' => 'Name', 'type' => 'short_text', 'group' => 'Person In Charge of the Facility', 'order' => $this->nextOrder()]);
        $this->upsertQuestion($section, ['code' => 'EMONC_A_INCHARGE_CONTACT', 'text' => 'Contact', 'type' => 'short_text', 'group' => 'Person In Charge of the Facility', 'order' => $this->nextOrder()]);

        $this->upsertQuestion($section, ['code' => 'EMONC_A_RESPONDENT_NAME', 'text' => 'Name', 'type' => 'short_text', 'group' => 'Facility Supervision Respondent', 'order' => $this->nextOrder()]);
        $this->upsertQuestion($section, ['code' => 'EMONC_A_RESPONDENT_CONTACT', 'text' => 'Contact', 'type' => 'short_text', 'group' => 'Facility Supervision Respondent', 'order' => $this->nextOrder()]);
        $this->upsertQuestion($section, ['code' => 'EMONC_A_RESPONDENT_CADRE', 'text' => 'Cadre', 'type' => 'cadre_select', 'group' => 'Facility Supervision Respondent', 'order' => $this->nextOrder()]);

        // Cleanup for environments that ran an earlier version of this
        // seeder: Human Resources in Maternity Unit used to be 4 hardcoded
        // cadres seeded as static questions here. It's now driven by the
        // admin-managed Cadre list (category: 'emonc') and materialized by
        // CadreMatrixSyncService (called at the end of run(), and again on
        // every Section A page visit) — see seedEmoncCadres() below.
        AssessmentQuestion::where('question_code', 'like', 'EMONC_A_HR_NURSES%')
            ->orWhere('question_code', 'like', 'EMONC_A_HR_CO_%')
            ->orWhere('question_code', 'like', 'EMONC_A_HR_MO_%')
            ->orWhere('question_code', 'like', 'EMONC_A_HR_OB_%')
            ->delete();

        // Reserves order 500-599 for the dynamically-synced HR cadre
        // questions (up to ~33 cadres at 3 questions each — see
        // CadreMatrixSyncService::syncMaternityHrQuestions(), which uses
        // the same 500 base), so they render between "Facility Supervision
        // Respondent" above and "Number of EmONC-trained healthcare
        // workers" below, matching the source survey's layout, regardless
        // of how many cadres actually exist.
        $this->order = 599;

        // One 7-column table row: total + the 6 department counts —
        // matches the source survey's "Number of EmONC trained healthcare
        // worker" / "Distribution... per department" table.
        $distributionGroup = 'EmONC-Trained Healthcare Workers';
        $this->upsertQuestion($section, ['code' => 'EMONC_A_EMONC_TRAINED_TOTAL', 'text' => 'Total', 'type' => 'number', 'group' => $distributionGroup, 'order' => $this->nextOrder()]);

        $departments = ['ANC', 'HRC', 'L/W', 'NBU', 'ANW', 'PNW'];
        foreach ($departments as $dept) {
            $deptCode = str_replace('/', '', $dept);
            $this->upsertQuestion($section, ['code' => "EMONC_A_DIST_{$deptCode}", 'text' => $dept, 'type' => 'number', 'group' => $distributionGroup, 'order' => $this->nextOrder()]);
        }

        return $section;
    }

    /**
     * The 4 cadres Human Resources in Maternity Unit is measured against.
     * Seeded into the shared assessment_cadres table (category: 'emonc')
     * rather than hardcoded as questions, so an admin can add/remove/rename
     * them later via the Cadres admin page — CadreMatrixSyncService (called
     * below, and again on every Section A page visit) keeps the actual
     * question rows in sync with whatever's active in this category.
     */
    private function seedEmoncCadres(): void
    {
        $cadres = [
            ['name' => 'Nurses', 'code' => 'emonc_nurses', 'order' => 1],
            ['name' => 'Clinical Officers', 'code' => 'emonc_clinical_officers', 'order' => 2],
            ['name' => 'Medical Officers', 'code' => 'emonc_medical_officers', 'order' => 3],
            ['name' => 'Obstetricians', 'code' => 'emonc_obstetricians', 'order' => 4],
        ];

        foreach ($cadres as $cadre) {
            \App\Models\Cadre::updateOrCreate(
                ['code' => $cadre['code']],
                array_merge($cadre, ['category' => 'emonc', 'is_active' => true])
            );
        }
    }

    // ── B. Feedback to Office & Colleagues (scored) ────────────────────────

    private function seedSectionB(AssessmentType $type): void
    {
        $section = $this->upsertSection($type, 'emonc_feedback', 'B. Feedback to Office & Colleagues', null, true, 2);

        $this->upsertQuestion($section, $this->yesNo('EMONC_B_FEEDBACK_MEETING_DONE', 'Feedback meeting to office held', $this->nextOrder()));

        // Cleanup for environments that ran an earlier version of this
        // seeder: action plans used to be 3 hardcoded slots. The source
        // survey's own row limit is arbitrary too — replaced with a
        // dynamic add/remove table so an assessor can record as many (or
        // as few) as actually came out of the meeting.
        AssessmentQuestion::where('question_code', 'like', 'EMONC_B_AP%')->delete();

        $this->upsertQuestion($section, [
            'code' => 'EMONC_B_ACTION_PLANS',
            'text' => 'Please specify below which action plan(s) were developed during the meeting and the status of each',
            'type' => 'repeater',
            'options' => [
                ['key' => 'plan', 'label' => 'Action Plan', 'type' => 'text'],
                ['key' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['Resolved', 'In Progress', 'Not Addressed']],
                ['key' => 'remarks', 'label' => 'Remarks', 'type' => 'text'],
            ],
            'order' => $this->nextOrder(),
        ]);
    }

    // ── C. Capacity Building (scored) ───────────────────────────────────────

    private function seedSectionC(AssessmentType $type): void
    {
        $section = $this->upsertSection($type, 'emonc_capacity_building', 'C. Capacity Building', 'Sessions for knowledge and skills sharing.', true, 3);

        $helpText = 'Confirm using the CME register/booklet';
        $this->upsertQuestion($section, $this->yesNo('EMONC_C_CMES', 'CMEs held', $this->nextOrder(), null, $helpText));
        $this->upsertQuestion($section, $this->yesNo('EMONC_C_DRILLS', 'Drills held', $this->nextOrder(), null, $helpText));
    }

    // ── D. Key Commodities (scored) ─────────────────────────────────────────

    private function seedSectionD(AssessmentType $type): void
    {
        $section = $this->upsertSection(
            $type,
            'emonc_key_commodities',
            'D. Key Commodities',
            'Available and functional in quantity sufficient for one month\'s caseload in the maternity department. Does not refer to other departments.',
            true,
            4
        );

        $items = [
            'Assorted IV cannulas/branulas',
            'Assorted disposable syringes with needles',
            'Elbow gloves/gynaecological gloves',
            'Sterile surgical gloves',
            'Assorted suture material',
            'Blood pressure measurement equipment (Digital BP machine or sphygmomanometer + stethoscope)',
            'Delivery Kit (5 Green towels, 1 Tray 10×14, 2 straight artery forceps 8", cord scissors, episiotomy scissors, 2 needle holders 7", 2 large kidney dishes 10", cord clamps, 1 Gallipot — randomly check 1 kit for contents)',
            'Ambu bag (280ml) with neonatal pre-term (size 0) masks',
            'Ambu bag (280ml) with neonatal term (size 1) masks',
            'Ambu bag (1.5L) with adult masks',
            'Fetoscope/handheld fetal heart monitor/digital fetoscope',
            'Portable examination lamp',
            'Assorted speculums (small/medium/large)',
            'Functional suction machines and catheters or penguin suction',
            'Functional Infant Resuscitation Unit/Radiant Warmer/Resuscitaire',
            'Oxygen set (portable cylinder or central wall supply with mask/nasal cannula + flow meter) or concentrator',
            'Patella hammer',
            'Thermometer',
            'Non-Pneumatic Antishock Garment (NASG)',
            'Oropharyngeal airway for adults',
            'Urine strips (proteinuria and sugar dip sticks) in labour ward and lab',
            'Functioning refrigerator for cold-chain drugs/lab reagents, powered 24/7 (excludes KEPI fridges)',
            'Blood/blood products currently stored with blood-giving/transfusion sets',
            'Haemoglobin meter with reagents',
            'Blood grouping & cross-matching kit (water bath, centrifuge, reagents, cold-chain blood carriers)',
            'Functioning refrigerator available for storing blood, powered 24/7',
            "IV fluids assorted (Normal saline / Ringer's lactate / Half-strength Darrow's) with IV administration set",
        ];

        foreach ($items as $i => $text) {
            $this->upsertQuestion($section, $this->yesNo('EMONC_D_'.($i + 1), $text, $this->nextOrder()));
        }
    }

    // ── E. Emergency Preparedness — Kits & SOPs (scored) ────────────────────

    private function seedSectionE(AssessmentType $type): void
    {
        $section = $this->upsertSection($type, 'emonc_emergency_kits', 'E. Emergency Preparedness — Kits & SOPs', 'Kits with checklists, followed by SOPs/job aids.', true, 5);

        $this->seedKit($section, 'EMONC_E_K1', '1. Obstetric Hemorrhage Kit', 'Obstetric Hemorrhage Kit', [
            'Large bore cannulas',
            'Oxytocin',
            'Tranexamic acid',
            'Misoprostol',
            'Balloon tamponade (UBT or condom)',
            'IV fluids',
            'Giving sets',
            '2-way Foleys catheters',
            'Gynecological gloves',
            'Specimen bottles',
            'NASG',
            'Blood loss monitoring chart',
            'Calibrated drapes',
            'MEOWS chart',
        ]);

        $this->seedKit($section, 'EMONC_E_K2', '2. Neonatal Resuscitation Kit', 'Neonatal Resuscitation Kit', [
            'Resuscitation table with radiant warmer',
            'Ambu bag (280ml, neonatal pre-term size 1/0)',
            'Penguin sucker',
            'Oral pharyngeal airway',
            'Oxygen source',
            'Non-rebreather mask',
            'Suction catheter size 8 (preterm)',
            'Suction catheter size 10 (all)',
            'Suction catheter size 12 (meconium)',
            'Assorted syringes & needles',
            'Cannulas',
            'Pulse oximeter',
            'Stethoscope',
            'Thermal blanket / plastic wrap for preterm',
            'Cap to prevent heat loss',
            'Dextrose solution (50%)',
            'Adrenalin injection',
            'Neonatal nasal prongs',
        ]);

        $this->seedKit($section, 'EMONC_E_K3', '3. PET/Eclampsia Kit', 'PET/Eclampsia Kit', [
            'Magnesium sulphate 50% (3 ampoules)',
            'Calcium gluconate',
            'Patella hammer',
            '20cc syringes',
            '10cc syringes',
            'Labetalol (oral and injectable)',
            'Methyldopa',
            'Nifedipine',
            'Inj. hydralazine',
            'Water for injection',
            'Inj. lignocaine 2%',
            '2-way Foleys catheter',
            'Urine bag',
            'Cannulas',
            'Specimen bottles',
            'Gloves',
            'Nasal prongs',
            'Magnesium Sulphate Toxicity Monitoring Chart',
        ]);

        $this->seedKit($section, 'EMONC_E_K4', '4. Maternal Resuscitation Kit', 'Maternal Resuscitation Kit', [
            'Ambu bag (1.5L, adult)',
            'Oropharyngeal airway (different sizes)',
            'Foleys catheter with urine bag',
            'Oxygen tubing & mask (NRM)',
            'IV fluids',
            'Large bore cannulas',
            'Specimen bottles',
            'NASG',
            'Patella hammer',
            'Fetoscope',
            'Stethoscope',
            'BP machine',
            'Thermometer',
            'Blood loss monitoring chart',
        ]);

        $this->seedKit($section, 'EMONC_E_K5', '5. Delivery Kit', 'Delivery Kit', [
            '6 green towels',
            '1 Tray 10×14',
            '2 straight artery forceps 8"',
            'Cord scissors',
            'Episiotomy scissors',
            '2 needle holders 7"',
            '2 large kidney dishes 10"',
            'Cord clamps',
            '1 Gallipot',
            'Sims speculum (small/medium/large)',
            'Cusco speculum (small/medium/large)',
        ]);

        $this->seedKit($section, 'EMONC_E_K6', '6. Assisted Vacuum Delivery Kit (AVD/Kiwi kit)', 'Assisted Vacuum Delivery Kit (AVD/Kiwi kit)', [
            'Vacuum extractor (Omni Cap/Pro Cap)',
            'Syringes',
            'Needles',
            'Foleys catheter',
            'Fetoscope',
            'V-drape',
            'Lubricant (e.g. K-Y jelly)',
        ]);

        $sopHelpText = 'Confirm physically that the job aid is available — laminated chart, wall chart, poster, or leaflet — appropriately placed in a visible location.';
        $sops = [
            'EMOTIVE',
            'PET/Eclampsia',
            'Breech Delivery',
            'Shoulder Dystocia',
            'Maternal Resuscitation',
            'Neonatal Resuscitation',
            'Maternal Shock',
            'PPH',
            'NASG Application',
            'Assisted Vacuum Delivery',
            'Heat Stable Carbetocin',
            'AMTSL Job Aid',
        ];
        foreach ($sops as $i => $text) {
            $this->upsertQuestion($section, $this->yesNo('EMONC_E_SOP_'.($i + 1), $text, $this->nextOrder(), 'SOPs / Job Aids', $sopHelpText));
        }
    }

    /**
     * Seeds one kit: a parent "kit available" yes/no, each of its sub-items
     * (all sharing $groupLabel so DynamicFormBuilder renders them as one
     * fieldset), and a trailing group_completeness question that
     * DynamicScoringService derives from every other question in the group.
     */
    private function seedKit(AssessmentSection $section, string $codePrefix, string $groupLabel, string $parentText, array $items): void
    {
        $this->upsertQuestion($section, $this->yesNo("{$codePrefix}_PARENT", $parentText, $this->nextOrder(), $groupLabel));

        foreach ($items as $i => $itemText) {
            $this->upsertQuestion($section, $this->yesNo($codePrefix.'_'.($i + 1), $itemText, $this->nextOrder(), $groupLabel));
        }

        $this->upsertQuestion($section, [
            'code' => "{$codePrefix}_COMPLETE",
            'text' => "{$parentText} Completeness",
            'type' => 'group_completeness',
            'scored' => true,
            'group' => $groupLabel,
            'order' => $this->nextOrder(),
        ]);
    }

    // ── F. Referral Systems (scored) ─────────────────────────────────────

    private function seedSectionF(AssessmentType $type): void
    {
        $section = $this->upsertSection($type, 'emonc_referrals', 'F. Referral Systems', 'Confirm using the referral form or referral register, where available.', true, 6);

        $questions = [
            'Notified of, or notified about, a referral before the patient arrived in the receiving facility (most recent referral)',
            'Does the maternity unit have access to a functional phone?',
            'Do you have access to ambulance services available 24/7 for maternity referrals to higher-level facilities?',
            'Was the most recent referral accompanied by a skilled health personnel?',
        ];
        foreach ($questions as $i => $text) {
            $this->upsertQuestion($section, $this->yesNo('EMONC_F_'.($i + 1), $text, $this->nextOrder()));
        }

        // Cleanup for environments that ran an earlier version of this
        // seeder: monthly referrals used to be 15 hardcoded month slots
        // (Jan 2025 - Mar 2026). Replaced with a dynamic add/remove table
        // (pick any month, add a row) so it isn't bound to a fixed window.
        AssessmentQuestion::where('question_code', 'like', 'EMONC_F_REF_%')->delete();

        $this->upsertQuestion($section, [
            'code' => 'EMONC_F_MONTHLY_REFERRALS',
            'text' => 'Please enter the number of monthly referrals out from the year 2025 to date',
            'type' => 'repeater',
            'options' => [
                ['key' => 'month', 'label' => 'Month', 'type' => 'select', 'options' => $this->monthYearOptions()],
                ['key' => 'referrals_out', 'label' => 'Referrals Out', 'type' => 'number'],
            ],
            'order' => $this->nextOrder(),
        ]);
    }

    /** "Jan 2023" .. "Dec 2027" — a wide practical window for the Month select on any repeater. */
    private function monthYearOptions(): array
    {
        $options = [];
        $start = \Carbon\Carbon::create(2023, 1, 1);

        for ($i = 0; $i < 60; $i++) {
            $options[] = $start->copy()->addMonths($i)->format('M Y');
        }

        return $options;
    }

    // ── G. Infection Prevention Control (scored) ────────────────────────────

    private function seedSectionG(AssessmentType $type): void
    {
        $section = $this->upsertSection($type, 'emonc_ipc', 'G. Infection Prevention Control', null, true, 7);

        $questions = [
            'Is there clean running water/soap?',
            'Is the waste segregated? (color-coded bins and liners)',
            'Are antiseptics available?',
            'Are there alcohol hand rubs?',
            'Are disinfectants available?',
            'Is there a functional facility for sterilization?',
        ];
        foreach ($questions as $i => $text) {
            $this->upsertQuestion($section, $this->yesNo('EMONC_G_'.($i + 1), $text, $this->nextOrder()));
        }
    }

    // ── H. Key Gaps, Recommendations & Success Stories (not scored) ────────

    private function seedSectionH(AssessmentType $type): void
    {
        $section = $this->upsertSection($type, 'emonc_gaps_success', 'H. Gaps & Success Stories', null, false, 8);

        // Cleanup for environments that ran an earlier version of this
        // seeder: gaps and success stories used to be 5 hardcoded row slots
        // each. Replaced with dynamic add/remove tables.
        AssessmentQuestion::where('question_code', 'like', 'EMONC_H_GAP%')
            ->orWhere('question_code', 'like', 'EMONC_H_SUCCESS%')
            ->delete();

        $this->upsertQuestion($section, [
            'code' => 'EMONC_H_GAPS',
            'text' => 'Key Gaps and Recommendations',
            'type' => 'repeater',
            'options' => [
                ['key' => 'gap', 'label' => 'Gap', 'type' => 'text'],
                ['key' => 'action', 'label' => 'Action', 'type' => 'text'],
                ['key' => 'who', 'label' => 'Who', 'type' => 'text'],
                ['key' => 'when', 'label' => 'When', 'type' => 'text'],
            ],
            'order' => $this->nextOrder(),
        ]);

        $this->upsertQuestion($section, [
            'code' => 'EMONC_H_SUCCESS_STORIES',
            'text' => 'Since the last EmONC training or supportive supervision, please describe any notable positive outcomes in maternal or neonatal care that resulted from applying EmONC skills and protocols',
            'help_text' => 'Include what happened, how it was achieved, and its impact on patient care.',
            'type' => 'repeater',
            'options' => [
                ['key' => 'what', 'label' => 'What Happened', 'type' => 'text'],
                ['key' => 'how', 'label' => 'How It Was Achieved', 'type' => 'text'],
                ['key' => 'impact', 'label' => 'Impact on Patient Care', 'type' => 'text'],
            ],
            'order' => $this->nextOrder(),
        ]);
    }

    // ── J. Additional Notes (not scored) ────────────────────────────────────

    private function seedSectionJ(AssessmentType $type): void
    {
        $section = $this->upsertSection($type, 'emonc_notes', 'J. Additional Notes', null, false, 9);

        $this->upsertQuestion($section, [
            'code' => 'EMONC_J_COMMENTS',
            'text' => 'Additional comments',
            'help_text' => 'Optional',
            'type' => 'text',
            'order' => $this->nextOrder(),
        ]);
    }
}
