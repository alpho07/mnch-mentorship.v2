<?php

namespace Database\Seeders;

use App\Models\ModuleRubric;
use App\Models\Program;
use App\Models\ProgramModule;
use App\Models\RubricItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Seeds the per-item Competency Assessment Checklist (RubricItem rows) that mentors use to
 * score each mentee's practical skills demonstration, for every module/track that has one
 * in the mentor manual (the precedence source per the mentor-manual-wins rule). Modules 4
 * (AMTSL) and 5 (PPH) plus PPH tracks 1-10 already have a ModuleRubric + items seeded by an
 * earlier effort — this seeder only fills the gap for the remaining modules, plus PPH Track
 * 11 (Simulation), which reuses the overall PPH checklist since it drills the full flow.
 */
class EmoncRubricItemsSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $program = Program::where('name', 'Maternal Health (EmONC)')->firstOrFail();

            $checklists = $this->moduleChecklists();
            $pphChecklist = $checklists['Postpartum Hemorrhage'];
            unset($checklists['Postpartum Hemorrhage']); // reserved for the Track 11 fallback below, not the parent module

            foreach ($checklists as $fragment => $checklist) {
                $module = ProgramModule::where('program_id', $program->id)
                    ->where('name', 'like', "%{$fragment}%")
                    ->whereNull('parent_id')
                    ->first();

                if (! $module) {
                    $this->command->warn("Module not found: {$fragment}");

                    continue;
                }

                $this->seedRubricAndItems($module, $checklist);
            }

            // PPH Track 11 (Simulation) — no per-track checklist exists in the mentor manual
            // for this track (unlike tracks 1-10, which use mentee-manual per-skill
            // checklists seeded separately); the mentor manual's overall PPH checklist is
            // the closest fit since this track drills the full PPH management flow.
            $pphModule = ProgramModule::where('program_id', $program->id)
                ->where('name', 'like', '%Postpartum Hemorrhage%')
                ->whereNull('parent_id')
                ->first();

            if ($pphModule) {
                $simulationTrack = ProgramModule::where('parent_id', $pphModule->id)
                    ->where('name', 'like', '%simulation%')
                    ->first();

                if ($simulationTrack) {
                    $this->seedRubricAndItems($simulationTrack, $pphChecklist, 'PPH Simulation');
                } else {
                    $this->command->warn('PPH Simulation track not found.');
                }
            }
        });

        $this->command->info('EmONC competency assessment checklist (RubricItem) rows seeded successfully.');
    }

    private function seedRubricAndItems(ProgramModule $module, array $checklist, ?string $titleOverride = null): void
    {
        $totalMarks = count($checklist);
        $passMarks = (int) round($totalMarks * 0.85);

        $rubric = ModuleRubric::firstOrCreate(
            ['program_module_id' => $module->id],
            [
                'title' => ($titleOverride ?? $module->name).' — Practical Skills Assessment',
                'description' => 'Mentor-scored competency checklist for the practical skills demonstration, sourced from the mentor manual\'s Competency Assessment Checklist.',
                'case_scenario' => null,
                'total_marks' => $totalMarks,
                'pass_marks' => $passMarks,
                'pass_percentage' => round(($passMarks / $totalMarks) * 100, 2),
                'equipment_supplies' => [],
                'debrief_questions' => [
                    'How did the assessment feel?',
                    'What are the steps of '.Str::of($module->name)->after(': ')->lower().'?',
                    'Which steps did you perform well?',
                    'Which steps need to be improved?',
                ],
                'order_sequence' => 1,
                'is_active' => true,
            ]
        );

        foreach ($checklist as $seq => $item) {
            RubricItem::firstOrCreate(
                [
                    'module_rubric_id' => $rubric->id,
                    'description' => $item,
                ],
                [
                    'order_sequence' => $seq + 1,
                    'is_active' => true,
                ]
            );
        }

        // Whichever agent created this rubric first may have guessed a slightly different
        // item count from its own manual pass — reconcile total_marks/pass_marks to the
        // actual seeded checklist (the scoring math must match the real number of items).
        $actualTotal = $rubric->items()->count();
        $actualPass = (int) round($actualTotal * 0.85);
        $rubric->update([
            'total_marks' => $actualTotal,
            'pass_marks' => $actualPass,
            'pass_percentage' => round(($actualPass / $actualTotal) * 100, 2),
        ]);
    }

    /**
     * Competency Assessment Checklist items, verbatim from the mentor manual (CHAI EmONC
     * Knowledge Pack), keyed by a module-name fragment. Item counts match the manual's
     * stated "(N items)" checklist headers exactly.
     */
    private function moduleChecklists(): array
    {
        return [
            'Ante Partum Hemorrhage' => [
                'Introduced self/respectful care', 'Focused obstetric history', 'Recognized APH early', 'Called for help',
                'DRCAB assessment', 'Left lateral tilt', 'High-flow O2 (10 L/min)', 'Two large-bore IV cannulae',
                'FBC/cross-match/coag bloods', 'IV crystalloid resuscitation', 'Avoided digital VE before excluding praevia',
                'Urinary catheter + urine output monitoring', 'Requested/interpreted OPOCUS', 'Distinguished abruption from praevia',
                'Activated massive haemorrhage protocol/blood products', 'Notified theatre/anaesthesia/blood bank/neonatal team',
                'Prepared for emergency CS', 'Obtained informed consent', 'SBAR/closed-loop communication',
                'Teamwork/leadership', 'Documentation/handover',
            ],
            'Labour Care Guide' => [
                'Respectful care', 'Initiated LCG correctly (active phase ≥5cm)', 'Maternal assessment', 'Fetal assessment',
                'Assessed labour progress', 'Documented findings accurately', 'Recognized alert values (circled correctly)',
                'Appropriate reassessment', 'Supportive care (companion/mobility/fluids/pain relief/bladder)',
                'Shared decision-making', 'Appropriate assessment and plan', 'Escalated care appropriately',
                'SBAR', 'Documentation accuracy',
            ],
            'Prolonged & Obstructed Labour' => [
                'Respectful care introduction', 'Focused history', 'Maternal ABCDE', 'Maternal vitals', 'Fetal wellbeing/FHR',
                'Reviewed/interpreted Labour Care Guide', 'Recognized prolonged labour', 'Recognized obstructed labour',
                'Suspected CPD', 'Identified caput/moulding', "Recognized Bandl's ring/impending rupture", 'Called for help early',
                'Stopped oxytocin appropriately', 'Two large-bore IV cannulae', 'IV fluid resuscitation',
                'Requested FBC/cross-match/other investigations', 'Catheterized + checked urine ketones/acetones',
                'Recognized blood-stained urine as danger sign', 'Administered broad-spectrum antibiotics',
                'Prepared for emergency CS', 'SBAR', 'Teamwork/leadership', 'Counselled woman/companion', 'Documentation/handover',
            ],
            'Cord Prolapse' => [
                'Introduced self/reassured', 'Recognized fetal bradycardia after ROM', 'Sterile VE promptly',
                'Correctly diagnosed cord prolapse', 'Checked cord pulsations', 'Called for help immediately',
                'Explained diagnosis', 'Elevated presenting part (not cord)', 'Positioned appropriately (knee-chest/knee-elbow)',
                'Maintained manual elevation until definitive management', 'IV access/fluids',
                'Gave tocolytic appropriately when transfer required', 'Prepared for emergency CS or expedited vaginal delivery',
                'Prepared for neonatal resuscitation', 'SBAR handover', 'Teamwork/leadership', 'Respectful communication', 'Documentation',
            ],
            'Vaginal Breech Delivery' => [
                'Confirmed breech presentation', 'Assessed eligibility', 'Explained diagnosis/consent', 'Called for help promptly',
                'Ensured neonatal resuscitation team/equipment available', 'Confirmed full dilatation', 'Ensured bladder empty',
                'Positioned woman appropriately', 'Maintained hands-off approach until required', 'Episiotomy only if indicated',
                'Assisted leg delivery correctly', 'Held fetus by bony pelvis', "Correctly performed Lovset's manoeuvre",
                'Correctly performed MSV manoeuvre', 'Requested suprapubic pressure when appropriate', 'Avoided excessive traction',
                'Completed delivery promptly after buttock delivery', 'Assessed/resuscitated newborn',
                'Assessed mother for bleeding/trauma', 'Teamwork/communication', 'Documentation',
            ],
            'Shoulder Dystocia' => [
                'Recognized shoulder dystocia promptly', 'Announced emergency/called for help', 'Recorded time of head delivery',
                'Explained situation to mother/companion', 'Instructed mother to stop pushing', 'Avoided excessive traction on head',
                'Avoided fundal pressure', 'Performed McRoberts correctly', 'Applied suprapubic pressure correctly',
                'Assessed need for episiotomy', 'Performed internal rotational manoeuvres correctly (if indicated)',
                'Delivered posterior arm correctly (if indicated)', 'Repositioned to all-fours when indicated',
                'Recognized need for salvage manoeuvres', 'Prepared for neonatal resuscitation', 'Initiated AMTSL after delivery',
                'Assessed mother for PPH/trauma', 'Assessed newborn for birth injuries/asphyxia', 'Teamwork/communication', 'Documentation',
            ],
            'Vacuum Assisted Delivery' => [
                'Confirmed indication', 'Excluded contraindications', 'Obtained informed verbal consent', 'Explained procedure',
                'Called for assistance/prepared team', 'Ensured bladder empty', 'Confirmed full dilatation', 'Confirmed ROM',
                'Confirmed engaged head (≤1/5)', 'Correctly identified head position', 'Applied cup at flexion point',
                'Ensured no maternal tissue trapped', 'Created vacuum gradually (0.2→0.8 kg/cm²)', 'Applied traction only during contractions',
                'Applied traction in pelvic axis (J-curve)', 'Assessed descent after each pull', 'Avoided rotating head with cup',
                'Recognized failure criteria appropriately', 'Abandoned procedure when indicated', 'Completed delivery safely',
                'Initiated AMTSL', 'Assessed mother for trauma/PPH', 'Assessed newborn for vacuum-related complications', 'Documentation',
            ],
            'Maternal Shock' => [
                'Recognized early signs', 'Called for help promptly', 'Assigned team roles', 'Systematic ABCDE', 'High-flow O2',
                'Two large-bore IV cannulas', 'Collected blood samples for investigations', 'Requested group and cross-match',
                'Rapid crystalloid infusion', 'Arranged blood transfusion when indicated', 'Inserted urinary catheter',
                'Monitored urine output', 'Identified underlying cause', 'Initiated appropriate definitive treatment',
                'Reassessed vitals after each intervention', 'Monitored maternal response continuously',
                'Prepared for referral/surgical intervention', 'Leadership/teamwork', 'Communication with woman/team', 'Documentation',
            ],
            'Maternal Resuscitation' => [
                'Ensured scene safety', 'Assessed responsiveness', 'Called for help immediately',
                'Activated maternal emergency response team', 'Performed DRABC correctly', 'Opened/maintained airway',
                'Effective bag-mask ventilation with O2', 'Recognized absent pulse within 10 seconds',
                'Started high-quality compressions immediately', 'Maintained correct rate (100–120/min)',
                'Maintained correct depth (5–6cm)', 'Minimized interruptions', 'Rotated compressors every 2 min',
                'Applied manual left uterine displacement/left lateral tilt', 'Established two large-bore IV lines',
                'Considered/managed 4 Hs and 4 Ts', 'Recognized PMCS indication', 'Continued CPR during PMCS', 'Recognized ROSC',
                'Initiated post-resuscitation care', 'Leadership/teamwork', 'Communication', 'Documentation',
            ],
            'Pre-Eclampsia' => [
                'Recognized severe pre-eclampsia promptly', 'Recognized eclamptic seizure', 'Called for help immediately',
                'Protected woman from injury during seizure', 'Positioned appropriately (left lateral after seizure)',
                'ABC assessment', 'Administered O2 appropriately', 'Established IV access promptly',
                'Administered correct MgSO4 loading dose', 'Administered additional MgSO4 appropriately for recurrent seizures',
                'Controlled severe hypertension appropriately', 'Monitored RR/reflexes/urine output',
                'Recognized/managed MgSO4 toxicity appropriately', 'Requested appropriate labs',
                'Assessed fetal wellbeing after maternal stabilization', 'Planned appropriate delivery timing/mode',
                'Maintained accurate fluid balance', 'Teamwork/leadership', 'Communication with woman/companion', 'Documentation',
            ],
            'Neonatal Resuscitation' => [
                'Prepared neonatal resuscitation area before delivery', 'Checked bag-mask device functionality',
                'Ensured thermal protection before receiving baby', 'Performed rapid initial assessment',
                'Identified indications for resuscitation', 'Initiated resuscitation within Golden Minute',
                'Positioned airway correctly (sniffing)', 'Avoided unnecessary routine suctioning',
                'Performed suction only when indicated', 'Correct bag-mask ventilation technique',
                'Achieved visible chest movement during PPV', 'Applied MRSOPA corrective steps appropriately',
                'Reassessed heart rate after effective ventilation', 'Correctly identified indication for chest compressions',
                'Performed compressions using two-thumb technique', 'Used correct 3:1 ratio',
                'Recognized indications for adrenaline', 'Maintained thermal protection throughout',
                'Provided appropriate post-resuscitation care', 'Teamwork/leadership', 'Communication with mother/family', 'Documentation',
            ],
            'Postpartum Hemorrhage' => [
                'Recognized PPH promptly', 'Quantified blood loss', 'Called for help', 'Assigned team leader', 'Reassured woman',
                'Initiated uterine massage', 'Assessed/emptied bladder', 'Administered appropriate uterotonics',
                'Administered TXA correctly', 'IV access + fluid resuscitation', 'Collected bloods without delaying treatment',
                'Administered oxygen appropriately', 'Catheterized + monitored urine output', 'Systematically assessed 4 Ts',
                'Identified underlying cause', 'Instituted cause-based definitive management', 'Escalated care appropriately',
                'Prepared for advanced interventions/referral', 'Teamwork/communication', 'SBAR handover', 'Documentation',
            ],
        ];
    }
}
