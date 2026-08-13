<?php

namespace Database\Seeders\FacilityAssessment2026;

use App\Models\AssessmentChecklist;
use App\Models\AssessmentQuestion;
use App\Models\AssessmentSection;
use App\Models\AssessmentType;
use Illuminate\Database\Seeder;

class InfrastructureSeeder extends Seeder
{
    public function run(): void
    {
        $type = AssessmentType::where('code', 'STANDARD_FACILITY_ASSESSMENT_2026')->firstOrFail();
        $section = AssessmentSection::firstOrCreate(
            ['assessment_type_id' => $type->id, 'code' => 'infrastructure'],
            ['name' => 'Infrastructure', 'section_type' => AssessmentSection::KIND_QUESTION_GROUP, 'is_scored' => true, 'order' => 2, 'is_active' => true]
        );
        $ortChecklist = AssessmentChecklist::where('assessment_type_id', $type->id)->where('title', 'ORT Corner checklist')->first();
        $triageChecklist = AssessmentChecklist::where('assessment_type_id', $type->id)->where('title', 'Triage requirements')->first();

        $order = 0;
        // Counts only the top-level questions this closure creates (not
        // bedCountPair's Functional/Non-Functional sub-fields, which stay
        // grouped under their parent's number rather than getting their
        // own) — separate from $order, which threads through both.
        $number = 0;
        $create = function (array $attrs) use ($section, &$order, &$number) {
            $order++;
            $number++;
            $attrs['question_text'] = "{$number}. {$attrs['question_text']}";
            AssessmentQuestion::updateOrCreate(
                ['assessment_section_id' => $section->id, 'question_code' => $attrs['question_code']],
                array_merge([
                    'question_type' => 'yes_no',
                    'is_scored' => true,
                    'scoring_map' => ['Yes' => 1, 'No' => 0],
                    'requires_explanation_on' => ['No'],
                    'order' => $order,
                    'is_active' => true,
                    'indent_level' => 0,
                ], $attrs)
            );
        };

        $create(['question_code' => 'INFRA_HAS_NBU', 'question_text' => 'Do you have a newborn unit?']);
        $order = $this->bedCountPair($section, $order, 'INFRA_NBU_GENERAL', 'General NBU beds', 'INFRA_HAS_NBU');
        $order = $this->bedCountPair($section, $order, 'INFRA_NBU_KMC', 'KMC beds', 'INFRA_HAS_NBU');

        $create(['question_code' => 'INFRA_HAS_PAED', 'question_text' => 'Do you have a paediatric unit?']);
        $order = $this->bedCountPair($section, $order, 'INFRA_PAED_GENERAL', 'General ward beds', 'INFRA_HAS_PAED');

        $create(['question_code' => 'INFRA_HAS_NICU', 'question_text' => 'Do you have a NICU?']);
        $order = $this->bedCountPair($section, $order, 'INFRA_NICU', 'NICU Beds', 'INFRA_HAS_NICU');

        $create(['question_code' => 'INFRA_HAS_PICU', 'question_text' => 'Do you have a PICU?']);
        $order = $this->bedCountPair($section, $order, 'INFRA_PICU', 'PICU Beds', 'INFRA_HAS_PICU');

        $create(['question_code' => 'INFRA_SEPARATE_NBU_PAED', 'question_text' => 'Is there a separate newborn and paediatric unit?']);
        $create(['question_code' => 'INFRA_SEPARATE_OPD', 'question_text' => 'Are newborns and paediatrics patients seen separately from the adults in the outpatient department?']);
        $create(['question_code' => 'INFRA_RESUS_LABOUR', 'question_text' => 'Is there a warm functional newborn resuscitation area in labour ward with: Complete resuscitation tray with an updated checklist, Radiant warmer, suction machine?']);
        $create(['question_code' => 'INFRA_RESUS_THEATRE', 'question_text' => 'Is there a warm functional newborn resuscitation area in maternity theater?']);
        $create(['question_code' => 'INFRA_ORT_OUTPATIENT', 'question_text' => 'Is there a functional Oral Rehydration Therapy (ORT) corner in the outpatient department?', 'checklist_id' => $ortChecklist?->id]);
        $create(['question_code' => 'INFRA_ORT_INPATIENT', 'question_text' => 'Is there a functional Oral Rehydration Therapy (ORT) corner in the inpatient department?', 'checklist_id' => $ortChecklist?->id]);
        $create(['question_code' => 'INFRA_ORT_REGISTER', 'question_text' => 'Is there an updated Oral Rehydration Therapy (ORT) corner register?(Observe availability and functionality)?']);
        $create(['question_code' => 'INFRA_NEBULIZATION', 'question_text' => 'Is there a nebulization corner?']);
        $create(['question_code' => 'INFRA_TRIAGE', 'question_text' => 'Is there a triage area in the outpatient department?', 'checklist_id' => $triageChecklist?->id]);

        $this->command->info('  ✓ infrastructure: 30 questions (14 gating/plain + 16 bed-capacity).');
    }

    /**
     * A Functional/Non-Functional pair of number questions, both
     * conditionally visible only when $gatingCode = Yes. Shares one
     * `group` value ("General NBU beds" etc.) so GroupedFieldRenderer
     * renders them side by side in a single Fieldset with that name as
     * its legend, rather than as two separate full-width rows stacked on
     * top of each other. `indent_level` stays 0 here — the Fieldset box
     * itself is the visual nesting cue; the flat margin-left indent is
     * for ungrouped line items, and combining both would double up.
     * Returns the updated $order counter.
     */
    private function bedCountPair(AssessmentSection $section, int $order, string $codePrefix, string $label, string $gatingCode): int
    {
        foreach (['FUNCTIONAL' => 'No. Functional', 'NONFUNCTIONAL' => 'No. Non-Functional'] as $suffix => $variantLabel) {
            $order++;
            AssessmentQuestion::updateOrCreate(
                ['assessment_section_id' => $section->id, 'question_code' => "{$codePrefix}_{$suffix}"],
                [
                    'question_text' => $variantLabel,
                    'question_type' => 'number',
                    'is_scored' => false,
                    'display_conditions' => ['question_code' => $gatingCode, 'operator' => 'equals', 'value' => 'Yes'],
                    'group' => $label,
                    'indent_level' => 0,
                    'order' => $order,
                    'is_active' => true,
                ]
            );
        }

        return $order;
    }
}
