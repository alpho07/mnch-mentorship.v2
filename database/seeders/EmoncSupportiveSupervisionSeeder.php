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
        $this->seedSectionA($type);
        $this->seedSectionB($type);
        $this->seedSectionC($type);
        $this->seedSectionD($type);
        $this->seedSectionE($type);
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

    private function seedSectionA(AssessmentType $type): void
    {
        $section = $this->upsertSection(
            $type,
            'emonc_facility_context',
            'A. Facility Profile',
            'Facility identity, EmONC training coverage, and human resources in the maternity unit. Facility name, MFL code, county, level, and ownership are shown from the selected facility record and not re-collected here.',
            false,
            1
        );

        $this->upsertQuestion($section, [
            'code' => 'EMONC_A_FACILITY_CATEGORY',
            'text' => 'Facility Category',
            'type' => 'select',
            'options' => ['CEMONC', 'BEMONC'],
            'order' => $this->nextOrder(),
        ]);

        for ($i = 1; $i <= 3; $i++) {
            $this->upsertQuestion($section, ['code' => "EMONC_A_SUP{$i}_NAME", 'text' => "Supervisor {$i} — Name", 'type' => 'text', 'order' => $this->nextOrder()]);
            $this->upsertQuestion($section, ['code' => "EMONC_A_SUP{$i}_TITLE", 'text' => "Supervisor {$i} — Title", 'type' => 'text', 'order' => $this->nextOrder()]);
        }

        $this->upsertQuestion($section, ['code' => 'EMONC_A_RESPONDENT_NAME', 'text' => 'Facility Supervision Respondent — Name', 'type' => 'text', 'order' => $this->nextOrder()]);
        $this->upsertQuestion($section, ['code' => 'EMONC_A_RESPONDENT_CONTACT', 'text' => 'Facility Supervision Respondent — Contact', 'type' => 'text', 'order' => $this->nextOrder()]);
        $this->upsertQuestion($section, ['code' => 'EMONC_A_RESPONDENT_CADRE', 'text' => 'Facility Supervision Respondent — Cadre', 'type' => 'text', 'order' => $this->nextOrder()]);

        $cadres = [
            'EMONC_A_HR_NURSES' => 'Nurses',
            'EMONC_A_HR_CO' => 'Clinical Officers',
            'EMONC_A_HR_MO' => 'Medical Officers',
            'EMONC_A_HR_OB' => 'Obstetricians',
        ];
        foreach ($cadres as $prefix => $label) {
            $this->upsertQuestion($section, ['code' => "{$prefix}_ALLOCATED", 'text' => "{$label} — Number Allocated in Maternity (ANW/Labour Ward/PNW)", 'type' => 'number', 'order' => $this->nextOrder()]);
            $this->upsertQuestion($section, ['code' => "{$prefix}_TRAINED", 'text' => "{$label} — Number Trained on 5-day EmONC (from 2024 to date)", 'type' => 'number', 'order' => $this->nextOrder()]);
            $this->upsertQuestion($section, ['code' => "{$prefix}_24HR", 'text' => "{$label} — Number present in the maternity unit in a 24hr shift", 'type' => 'number', 'order' => $this->nextOrder()]);
        }

        $this->upsertQuestion($section, ['code' => 'EMONC_A_EMONC_TRAINED_TOTAL', 'text' => 'Number of EmONC-trained healthcare workers', 'type' => 'number', 'order' => $this->nextOrder()]);

        $departments = ['ANC', 'HRC', 'L/W', 'NBU', 'ANW', 'PNW'];
        foreach ($departments as $dept) {
            $deptCode = str_replace('/', '', $dept);
            $this->upsertQuestion($section, ['code' => "EMONC_A_DIST_{$deptCode}", 'text' => "EmONC-trained healthcare workers — {$dept}", 'type' => 'number', 'order' => $this->nextOrder()]);
        }
    }

    // ── B. Feedback to Office & Colleagues (scored) ────────────────────────

    private function seedSectionB(AssessmentType $type): void
    {
        $section = $this->upsertSection($type, 'emonc_feedback', 'B. Feedback to Office & Colleagues', null, true, 2);

        $this->upsertQuestion($section, $this->yesNo('EMONC_B_FEEDBACK_MEETING_DONE', 'Feedback meeting to office held', $this->nextOrder()));

        for ($i = 1; $i <= 3; $i++) {
            $this->upsertQuestion($section, ['code' => "EMONC_B_AP{$i}_TEXT", 'text' => "Action Plan {$i} — Description", 'type' => 'text', 'order' => $this->nextOrder()]);
            $this->upsertQuestion($section, [
                'code' => "EMONC_B_AP{$i}_STATUS",
                'text' => "Action Plan {$i} — Status",
                'type' => 'select',
                'options' => ['Resolved', 'In Progress', 'Not Addressed'],
                'order' => $this->nextOrder(),
            ]);
            $this->upsertQuestion($section, ['code' => "EMONC_B_AP{$i}_REMARKS", 'text' => "Action Plan {$i} — Remarks", 'type' => 'text', 'order' => $this->nextOrder()]);
        }
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
}
