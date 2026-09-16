<?php

namespace Database\Seeders;

use App\Models\ModuleRubric;
use App\Models\Program;
use App\Models\ProgramModule;
use App\Models\ProgramModuleContent;
use App\Models\ProgramModuleQuiz;
use App\Models\QuizOption;
use App\Models\QuizQuestion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds real content + quiz data for four EmONC modules ("Batch A"):
 *   - Module 2: Labour Care Guide (LCG)
 *   - Module 3: Prolonged & Obstructed Labour
 *   - Module 4: Active Management of the Third Stage of Labor (AMSTL)
 *   - Module 6: Management of Cord Prolapse
 *
 * Content is drawn from the CHAI EmONC Mentor Knowledge Pack (authoritative for
 * clinical facts) supplemented by the EmONC Mentee Manual for procedural detail.
 */
class EmoncBatchAContentSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $program = Program::where('name', 'Maternal Health (EmONC)')->firstOrFail();

            $this->seedLabourCareGuideModule($program);
            $this->seedProlongedObstructedLabourModule($program);
            $this->seedAmtslModule($program);
            $this->seedCordProlapseModule($program);
        });

        $this->command->info('EmONC Batch A (LCG, Prolonged & Obstructed Labour, AMSTL, Cord Prolapse) content seeded successfully.');
    }

    /**
     * Set plain-array objectives/content on the ProgramModule itself (not ProgramModuleContent).
     */
    private function setModuleObjectivesAndContent(ProgramModule $module, array $objectives, array $content): void
    {
        $module->update([
            'objectives' => $objectives,
            'content' => $content,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // MODULE 2: LABOUR CARE GUIDE (LCG)
    // ─────────────────────────────────────────────────────────────────────────

    private function seedLabourCareGuideModule(Program $program): void
    {
        $module = ProgramModule::where('program_id', $program->id)
            ->where('name', 'like', '%Labour Care Guide%')
            ->whereNull('parent_id')
            ->firstOrFail();

        // NOTE: this module already had "Introduction" and "Case Scenario"
        // ProgramModuleContent rows seeded elsewhere, but on inspection both
        // contained Lorem Ipsum placeholder filler text rather than real
        // content. Since that is not "genuine" existing content, we replace
        // those specific placeholder rows in place instead of creating
        // duplicates or leaving dummy text live on a production platform.
        $existingIntro = ProgramModuleContent::where('program_module_id', $module->id)
            ->where('type', 'introduction')
            ->first();

        $introContent = 'The WHO Labour Care Guide (LCG) is the Ministry of Health\'s recommended tool for monitoring '
            .'women in active labour. It supports timely clinical decision-making through systematic assessment of '
            .'maternal and fetal wellbeing, labour progress, supportive care and documentation. Unlike the old '
            .'partograph, the LCG replaces the single 1 cm/hour "action line" with evidence-based, dilatation-specific '
            .'time limits and an "Alert" column.'."\n\n"
            .'This drill enables participants to use the LCG to identify normal labour, recognize deviations early, '
            .'institute appropriate interventions, and provide respectful maternity care. Any observation meeting an '
            .'alert criterion is circled in red; the sequence is ASSESS → RECORD → CHECK ALERTS → PLAN, and any alert '
            .'triggers four immediate actions: (1) reassess the mother and fetus, (2) identify the cause, (3) document '
            .'the assessment and plan on the LCG, and (4) act — treat or escalate without delay.';

        if ($existingIntro && str_contains((string) $existingIntro->content, 'Lorem Ipsum')) {
            $existingIntro->update([
                'title' => 'Introduction — Labour Care Guide (LCG)',
                'content' => $introContent,
                'order_sequence' => 1,
                'is_active' => true,
            ]);
        } else {
            ProgramModuleContent::firstOrCreate(
                [
                    'program_module_id' => $module->id,
                    'type' => 'introduction',
                    'title' => 'Introduction — Labour Care Guide (LCG)',
                ],
                [
                    'content' => $introContent,
                    'order_sequence' => 1,
                    'is_active' => true,
                ]
            );
        }

        ProgramModuleContent::firstOrCreate(
            [
                'program_module_id' => $module->id,
                'type' => 'introduction',
                'title' => 'WHO LCG Alert-Threshold Reference',
            ],
            [
                'content' => 'Any observation meeting an alert criterion is circled in red; alert the senior '
                    .'midwife/doctor, record the assessment and action, then act.'."\n\n"
                    .'FETAL ALERTS: baseline FHR <110 or ≥160 bpm; late decelerations (L); thick meconium (M+++) or '
                    ."blood-stained liquor (B); posterior (P) or transverse (T) fetal position; caput +++; moulding +++.\n\n"
                    .'MATERNAL ALERTS: pulse <60 or ≥120 bpm; systolic BP <80 or ≥140 mmHg; diastolic BP ≥90 mmHg; '
                    .'temperature <35.0 or ≥37.5 °C; urine protein ++ or acetone ++.'."\n\n"
                    .'LABOUR PROGRESS ALERTS: contraction frequency ≤2 or >5 per 10 min; contraction duration <20 or '
                    .'>60 sec; cervical dilatation stalled at 5 cm ≥6 h, 6 cm ≥5 h, 7 cm ≥3 h, 8 cm ≥2.5 h, or 9 cm ≥2 h; '
                    .'no progressive descent in the second stage despite adequate contractions.'."\n\n"
                    .'Supportive-care prompts (companion present, pain relief offered, oral fluids offered, posture) are '
                    .'recorded Y/N/D — these are prompts to act on, not formal red alerts. Only late (L) decelerations '
                    .'are the formal deceleration red alert, though recurrent variable decelerations with minimal '
                    .'variability also warrant intrauterine resuscitation.',
                'order_sequence' => 2,
                'is_active' => true,
            ]
        );

        $lcgCaseScenario = 'PATIENT: Grace, 26 years, G2P1 at 39+2 weeks.'."\n\n"
            .'PRESENTING: Spontaneous labour, admitted in active labour at 5 cm. Previous normal vaginal delivery; '
            .'current pregnancy uncomplicated with 4 ANC visits, good fetal movements, no danger signs. Accompanied '
            .'by her husband.'."\n\n"
            .'INITIAL ASSESSMENT: BP 118/72 | Pulse 82 | Temp 36.7°C | RR 18 | FHR 142 (no decelerations) | Liquor '
            .'clear | Cervix 5 cm | Occiput anterior | Contractions 3 in 10 min (45 sec) | Descent 4/5 palpable | No '
            .'caput/moulding | Urinalysis: ketones 0, acetones 0.'."\n\n"
            .'TASK: Initiate and complete the Labour Care Guide throughout labour, recognizing any alert values, '
            .'developing an assessment and plan, and escalating care when indicated.';

        $existingCaseScenario = ProgramModuleContent::where('program_module_id', $module->id)
            ->where('type', 'case_scenario')
            ->first();

        if ($existingCaseScenario && str_contains((string) $existingCaseScenario->content, 'Lorem Ipsum')) {
            $existingCaseScenario->update([
                'title' => 'LCG Simulation Drill — Case Scenario',
                'content' => $lcgCaseScenario,
                'order_sequence' => 1,
                'is_active' => true,
            ]);
        } elseif (! $existingCaseScenario) {
            ProgramModuleContent::firstOrCreate(
                [
                    'program_module_id' => $module->id,
                    'type' => 'case_scenario',
                    'title' => 'LCG Simulation Drill — Case Scenario',
                ],
                [
                    'content' => $lcgCaseScenario,
                    'order_sequence' => 1,
                    'is_active' => true,
                ]
            );
        }

        ProgramModuleContent::firstOrCreate(
            [
                'program_module_id' => $module->id,
                'type' => 'case_scenario_progression',
                'title' => 'LCG Simulation Drill — Scenario Progression',
            ],
            [
                'content' => "SCENARIO PROGRESSION (Grace):\n"
                    .'• Time 0: BP 118/72, Pulse 82, Temp 36.7°C, FHR 142 (normal variability), cervix 5 cm, '
                    .'contractions 3 in 10 (45 sec), head 4/5, clear liquor, OA, no caput/moulding. Initiate the LCG; '
                    .'explain its purpose; encourage companionship, mobility, oral fluids and bladder emptying; '
                    ."document baseline findings.\n"
                    .'• +2 hours: Cervix 6 cm, FHR 145, Pulse 88, BP 116/70, contractions 4 in 10 (50 sec), clear '
                    .'liquor. Update the LCG (all parameters); reassess maternal/fetal wellbeing; continue supportive '
                    ."care — no alerts.\n"
                    .'• +4 hours — first alert: Cervix remains 6 cm, FHR 150, Pulse 96, BP 110/68, contractions 4 in '
                    .'10 (55 sec); has not passed urine for several hours (full bladder). Recognize slow progress and '
                    ."the full-bladder prompt; reassess (VE, position); catheterize; discuss findings; document plan.\n"
                    .'• +6 hours — multiple alerts: Cervix 7 cm, FHR 168, Pulse 118, Temp 37.8°C, thick meconium '
                    .'(M+++), caput +, moulding +. Circle multiple alerts (fetal tachycardia, maternal fever/'
                    .'tachycardia, thick meconium, slow progress); suspect chorioamnionitis/fetal compromise; notify '
                    ."senior; consider antibiotics; update the LCG.\n"
                    .'• +7–8 hours — critical alerts: Cervix remains 7 cm, FHR 170 with minimal variability and '
                    .'recurrent late decelerations, Pulse 124, BP 98/60, Temp 38.1°C, caput ++, moulding ++. Recognize '
                    .'severe fetal compromise and obstructed labour; stop oxytocin if running; intrauterine '
                    .'resuscitation (left-lateral position, oxygen, IV fluids); immediate senior review and consent '
                    .'for emergency caesarean; complete LCG documentation.',
                'order_sequence' => 1,
                'is_active' => true,
            ]
        );

        ProgramModuleContent::firstOrCreate(
            [
                'program_module_id' => $module->id,
                'type' => 'expected_learning_outcome',
                'title' => 'Expected Learning Outcome',
            ],
            [
                'content' => 'By the end of this module, the mentee should be able to correctly initiate the WHO '
                    .'Labour Care Guide at the appropriate time (active phase, ≥5 cm), accurately complete all '
                    .'sections while monitoring maternal and fetal wellbeing, recognize and circle alert values '
                    .'across the fetal, maternal and labour-progress domains, formulate an appropriate assessment '
                    .'and management plan for any alert finding, escalate care in a timely manner when indicated, '
                    .'and consistently practice respectful, woman-centred maternity care with shared decision-making '
                    .'throughout labour.',
                'order_sequence' => 1,
                'is_active' => true,
            ]
        );

        $this->setModuleObjectivesAndContent(
            $module,
            [
                'Initiate the LCG appropriately (active phase, ≥ 5 cm).',
                'Complete all sections accurately and monitor maternal and fetal wellbeing.',
                'Assess labour progress and recognize alert values.',
                'Develop an assessment and plan, and escalate care when indicated.',
                'Demonstrate respectful maternity care and shared decision-making.',
            ],
            [
                ['label' => 'Drill', 'duration' => '12-15 min'],
                ['label' => 'Debrief', 'duration' => '20-25 min'],
            ]
        );

        $rubric = ModuleRubric::firstOrCreate(
            ['program_module_id' => $module->id],
            [
                'title' => 'Module 2: Labour Care Guide (LCG) — Practical Skills Assessment',
                'description' => 'Hands-on practical rubric assessing correct use of the WHO Labour Care Guide to '
                    .'monitor labour, recognize alert values, and escalate care appropriately.',
                'case_scenario' => $lcgCaseScenario,
                'total_marks' => 14,
                'pass_marks' => (int) round(14 * 0.85),
                'pass_percentage' => round(round(14 * 0.85) / 14 * 100, 2),
                'equipment_supplies' => [
                    'Labour bed',
                    'Blank Labour Care Guide forms',
                    'Blood pressure machine',
                    'Thermometer',
                    'Doppler or fetoscope',
                    'Blue/black pens and a red pen',
                    'Sterile gloves',
                    'Vaginal-examination set',
                    'Urinary catheter set / urine sticks',
                    'IV fluids and cannulas',
                    'Oxygen source',
                    'Labour-ward emergency trolley',
                ],
                'debrief_questions' => [
                    'How did the assessment feel?',
                    'What are the steps of initiating and completing the Labour Care Guide?',
                    'Which steps did you perform well?',
                    'Which steps need to be improved?',
                ],
                'order_sequence' => 1,
                'is_active' => true,
            ]
        );

        $quiz = ProgramModuleQuiz::firstOrCreate(
            [
                'program_module_id' => $module->id,
                'type' => 'both',
            ],
            [
                'title' => 'Labour Care Guide (LCG) Knowledge Assessment (Pre-test & Post-test)',
                'description' => 'A 15-question instrument administered before and after the LCG simulation drill to '
                    .'measure knowledge gain on correct use, interpretation and escalation via the WHO Labour Care Guide.',
                'pass_mark_percentage' => 70.00,
                'order_sequence' => 1,
                'is_active' => true,
            ]
        );

        $questions = [
            [
                'text' => 'According to the WHO Labour Care Guide, the active first stage of labour begins at:',
                'explanation' => 'The WHO LCG defines the active first stage as beginning at 5 cm cervical dilatation, replacing the older 4 cm threshold used by the traditional partograph.',
                'options' => [
                    ['text' => '3 cm cervical dilatation', 'correct' => false],
                    ['text' => '4 cm cervical dilatation', 'correct' => false],
                    ['text' => '5 cm cervical dilatation', 'correct' => true],
                    ['text' => '6 cm cervical dilatation', 'correct' => false],
                    ['text' => '7 cm cervical dilatation', 'correct' => false],
                ],
            ],
            [
                'text' => 'In the LCG, an alert is triggered for cervical dilatation from 5 cm to 6 cm if there is no progress after:',
                'explanation' => 'The LCG progress-alert table sets the threshold at 5 cm ≥ 6 hours; each subsequent centimetre has its own individualized time limit rather than a single fixed action line.',
                'options' => [
                    ['text' => '3 hours', 'correct' => false],
                    ['text' => '4 hours', 'correct' => false],
                    ['text' => '5 hours', 'correct' => false],
                    ['text' => '6 hours', 'correct' => true],
                    ['text' => '8 hours', 'correct' => false],
                ],
            ],
            [
                'text' => 'A persistent baseline fetal heart rate of 168 bpm should be:',
                'explanation' => 'Baseline FHR ≥ 160 bpm meets the LCG fetal alert criterion and requires immediate reassessment of the mother and exclusion of causes of fetal compromise.',
                'options' => [
                    ['text' => 'Circled as an alert and prompt urgent reassessment', 'correct' => true],
                    ['text' => 'Considered normal in the second stage', 'correct' => false],
                    ['text' => 'Treated only with maternal repositioning', 'correct' => false],
                    ['text' => 'Documented but no immediate action needed', 'correct' => false],
                    ['text' => 'Repeated in 4 hours if no other symptoms', 'correct' => false],
                ],
            ],
            [
                'text' => 'Recurrent late decelerations on the LCG should prompt the midwife to:',
                'explanation' => 'Late decelerations are the LCG\'s formal red deceleration alert. The response is left-lateral positioning, stopping oxytocin if running, oxygen if indicated, excluding cord prolapse, senior review, and expediting birth if unresolved.',
                'options' => [
                    ['text' => 'Perform immediate intrauterine resuscitation and escalate care', 'correct' => true],
                    ['text' => 'Increase the oxytocin infusion', 'correct' => false],
                    ['text' => 'Perform artificial rupture of membranes', 'correct' => false],
                    ['text' => 'Discharge the woman if progress is good', 'correct' => false],
                    ['text' => 'Reassure the woman and continue routine monitoring', 'correct' => false],
                ],
            ],
            [
                'text' => 'The primary purpose of the "Alert" column in the LCG is to:',
                'explanation' => 'The Alert column functions as an early-warning system, flagging abnormal findings that require further assessment and action rather than mandating a specific intervention automatically.',
                'options' => [
                    ['text' => 'Automatically indicate caesarean section', 'correct' => false],
                    ['text' => 'Serve as an early warning for abnormal findings requiring further assessment and action', 'correct' => true],
                    ['text' => 'Replace clinical judgement', 'correct' => false],
                    ['text' => 'Document only normal labour', 'correct' => false],
                    ['text' => 'Calculate the estimated date of delivery', 'correct' => false],
                ],
            ],
            [
                'text' => 'Which of the following is NOT considered an alert in supportive care?',
                'explanation' => 'Mobility is encouraged during labour, so a woman walking freely is not a supportive-care concern. Absence of a companion, inadequate pain relief, and declining oral fluids are all supportive-care prompts that should trigger action.',
                'options' => [
                    ['text' => 'No labour companion', 'correct' => false],
                    ['text' => 'Inadequate pain relief', 'correct' => false],
                    ['text' => 'Woman walking freely', 'correct' => true],
                    ['text' => 'Not taking oral fluids', 'correct' => false],
                    ['text' => 'Woman declining oral fluids', 'correct' => false],
                ],
            ],
            [
                'text' => 'A woman at 6 cm with no progress after 5 hours on the LCG should have:',
                'explanation' => 'The LCG threshold for 6 cm is ≥5 hours without progress; this warrants comprehensive reassessment (contractions, position, descent, caput, moulding, bladder, hydration), exclusion of CPD/malposition, and a documented plan, not automatic operative delivery.',
                'options' => [
                    ['text' => 'Immediate caesarean section', 'correct' => false],
                    ['text' => 'Comprehensive reassessment for causes of delay and a documented management plan', 'correct' => true],
                    ['text' => 'Continued expectant management for another 4 hours', 'correct' => false],
                    ['text' => 'Routine augmentation with oxytocin', 'correct' => false],
                    ['text' => 'Immediate operative vaginal delivery', 'correct' => false],
                ],
            ],
            [
                'text' => 'The Labour Care Guide encourages vaginal examinations:',
                'explanation' => 'Unlike a fixed schedule, the LCG recommends vaginal examinations only when clinically indicated, reducing unnecessary examinations while still ensuring timely reassessment when alerts occur.',
                'options' => [
                    ['text' => 'Every hour', 'correct' => false],
                    ['text' => 'Every 30 minutes', 'correct' => false],
                    ['text' => 'Only when clinically indicated', 'correct' => true],
                    ['text' => 'Every 15 minutes', 'correct' => false],
                    ['text' => 'Every 2 hours regardless of progress', 'correct' => false],
                ],
            ],
            [
                'text' => 'The LCG recommends that any alert finding should lead to:',
                'explanation' => 'Any alert triggers four actions: reassess the mother and fetus, identify the cause, document the assessment and plan on the LCG, and act — treat or escalate without delay, in shared decision-making with the woman.',
                'options' => [
                    ['text' => 'Immediate caesarean section', 'correct' => false],
                    ['text' => 'Circling the alert, further assessment, shared decision-making and a documented plan', 'correct' => true],
                    ['text' => 'No documentation needed', 'correct' => false],
                    ['text' => 'Waiting for senior review before acting', 'correct' => false],
                    ['text' => 'Automatic transfer to a higher-level facility', 'correct' => false],
                ],
            ],
            [
                'text' => 'During LCG monitoring, the birth companion is considered:',
                'explanation' => 'Companionship is one of the four supportive-care prompts (alongside pain relief, oral fluids and posture/mobility) and is an essential part of respectful, woman-centred labour care.',
                'options' => [
                    ['text' => 'An essential component of supportive care', 'correct' => true],
                    ['text' => 'Only for the second stage', 'correct' => false],
                    ['text' => 'Not to be documented', 'correct' => false],
                    ['text' => 'Relevant only in private facilities', 'correct' => false],
                    ['text' => 'Optional and only permitted in private facilities', 'correct' => false],
                ],
            ],
            [
                'text' => 'The most appropriate response when multiple LCG alerts appear simultaneously is to:',
                'explanation' => 'Multiple simultaneous alerts (e.g. fetal tachycardia, maternal fever, thick meconium, slow progress) require holistic reassessment of mother and fetus together, escalation to a senior clinician, and involving the woman in decisions — not a narrow focus on a single parameter.',
                'options' => [
                    ['text' => 'Document only', 'correct' => false],
                    ['text' => 'Perform holistic reassessment, escalate care and involve the woman in decisions', 'correct' => true],
                    ['text' => 'Focus only on cervical dilatation', 'correct' => false],
                    ['text' => 'Stop all interventions', 'correct' => false],
                    ['text' => 'Address only the most recent alert and ignore earlier ones', 'correct' => false],
                ],
            ],
            [
                'text' => 'The Labour Care Guide is best described as:',
                'explanation' => 'The LCG is a dynamic clinical decision-support tool promoting individualized, woman-centred, evidence-based care — not a rigid protocol or a documentation-only form, and it is the recommended tool across facility levels in Kenya.',
                'options' => [
                    ['text' => 'A rigid protocol that replaces clinical judgement', 'correct' => false],
                    ['text' => 'A dynamic clinical decision-support tool that promotes woman-centred, evidence-based care', 'correct' => true],
                    ['text' => 'Only a documentation form', 'correct' => false],
                    ['text' => 'An optional tool in Kenyan facilities', 'correct' => false],
                    ['text' => 'A screening tool used only in referral facilities', 'correct' => false],
                ],
            ],
            [
                'text' => 'With maternal fever (38.1 °C), fetal tachycardia and thick meconium (M+++) on the LCG, the midwife should suspect:',
                'explanation' => 'This combination of maternal fever, fetal tachycardia and thick meconium is classic for chorioamnionitis with possible fetal compromise, warranting senior notification and consideration of antibiotics.',
                'options' => [
                    ['text' => 'Chorioamnionitis with possible fetal compromise', 'correct' => true],
                    ['text' => 'Dehydration only', 'correct' => false],
                    ['text' => 'False labour', 'correct' => false],
                    ['text' => 'Cord prolapse', 'correct' => false],
                    ['text' => 'Normal variant of advanced labour', 'correct' => false],
                ],
            ],
            [
                'text' => 'The LCG differs from the traditional partograph mainly by:',
                'explanation' => 'The LCG replaces the fixed 1 cm/hour action line with individualized, dilatation-specific alert thresholds, and adds structured supportive-care prompts and an assessment/plan section — it does not remove fetal monitoring.',
                'options' => [
                    ['text' => 'Using a stricter 1 cm/hour rule', 'correct' => false],
                    ['text' => 'Removing fetal monitoring', 'correct' => false],
                    ['text' => 'Focusing only on documentation', 'correct' => false],
                    ['text' => 'Emphasising individualised care, supportive care and alert thresholds instead of fixed action lines', 'correct' => true],
                    ['text' => 'Requiring a fixed 1 cm/hour cervical dilatation rate for all women', 'correct' => false],
                ],
            ],
            [
                'text' => 'In the applied LCG plotting exercise (Francisca, 16 years, primigravida, 40 weeks), the cervix remains at 6 cm from 12:00 to 16:00 with static descent (3/5), a persistent occipito-transverse position, caput and moulding progressing to +++, baseline FHR rising to 166 bpm with late decelerations, and liquor progressing to thick dark-green meconium. What is the correct assessment?',
                'explanation' => 'Arrest of cervical dilatation and descent over 4 hours with progressive caput/moulding to +++, a persistent malposition, and evolving fetal compromise (FHR ≥ 160 bpm with late decelerations, thick meconium) together indicate obstructed labour due to deep transverse arrest with cephalopelvic disproportion; oxytocin augmentation is contraindicated and immediate senior review for emergency caesarean is required.',
                'options' => [
                    ['text' => 'Normal labour progress requiring continued observation', 'correct' => false],
                    ['text' => 'Obstructed labour due to deep transverse arrest with cephalopelvic disproportion, complicated by non-reassuring fetal status', 'correct' => true],
                    ['text' => 'Cord prolapse requiring immediate knee-chest positioning', 'correct' => false],
                    ['text' => 'Placental abruption requiring emergency caesarean for haemorrhage', 'correct' => false],
                    ['text' => 'Normal second-stage descent delay managed with oxytocin augmentation', 'correct' => false],
                ],
            ],
        ];

        $this->seedQuestions($quiz, $questions);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // MODULE 3: PROLONGED & OBSTRUCTED LABOUR
    // ─────────────────────────────────────────────────────────────────────────

    private function seedProlongedObstructedLabourModule(Program $program): void
    {
        $module = ProgramModule::where('program_id', $program->id)
            ->where('name', 'like', '%Prolonged & Obstructed Labour%')
            ->whereNull('parent_id')
            ->firstOrFail();

        ProgramModuleContent::firstOrCreate(
            [
                'program_module_id' => $module->id,
                'type' => 'introduction',
                'title' => 'Introduction — Prolonged & Obstructed Labour',
            ],
            [
                'content' => 'This drill develops the skills required to recognize and manage prolonged labour '
                    .'progressing to obstructed labour, while demonstrating teamwork, communication and respectful '
                    .'maternity care. Participants set up the simulation area, assign roles (client, provider/mentee, '
                    .'birth companion, observers), run the drill and complete a structured debrief.',
                'order_sequence' => 1,
                'is_active' => true,
            ]
        );

        ProgramModuleContent::firstOrCreate(
            [
                'program_module_id' => $module->id,
                'type' => 'introduction',
                'title' => 'Key Highlights — Prolonged & Obstructed Labour',
            ],
            [
                'content' => 'Communication — with the client (respectful maternity care) and team (SBAR, closed-loop, '
                    ."call-outs, think-aloud).\n"
                    .'Clinical recognition — prolonged labour, obstructed labour, CPD, malpresentation, impending '
                    ."uterine rupture.\n"
                    .'Avoiding pitfalls — no oxytocin augmentation and no vacuum once obstruction is suspected.'."\n"
                    .'Emergency management — maternal stabilization and timely decision for caesarean section or '
                    ."referral.\n"
                    .'Teamwork & leadership — clear task allocation and early call for help.',
                'order_sequence' => 2,
                'is_active' => true,
            ]
        );

        $polCaseScenario = 'PATIENT: Sarah, 19 years, primigravida (G1P0) at 40+3 weeks.'."\n\n"
            .'PRESENTING: Referred from a Level II facility after prolonged labour — labouring approximately 18 '
            .'hours, received oxytocin augmentation with no improvement, fully dilated for over 3 hours with no '
            .'descent of the fetal head despite strong contractions. Exhausted, dehydrated, with early signs of '
            .'obstructed labour.'."\n\n"
            .'HISTORY: G1P0, 40+3 weeks; 4 ANC visits; no chronic illness; oxytocin augmentation at the referring '
            .'facility; membranes ruptured 6 hours ago; fully dilated > 3 hours with no descent.'."\n\n"
            .'INITIAL VITAL SIGNS: BP 120/70 mmHg | Pulse 108 bpm | RR 22/min | Temp 37.4°C | FHR 145 bpm | '
            .'Contractions 4 in 10 min (60 sec) | Cervical dilatation fully dilated | Station −2 | Membranes ruptured '
            .'(6 hours) | Moulding ++ | Caput ++.'."\n\n"
            .'DIAGNOSIS: Obstructed labour secondary to cephalopelvic disproportion (CPD), with impending uterine '
            .'rupture if unmanaged.';

        ProgramModuleContent::firstOrCreate(
            [
                'program_module_id' => $module->id,
                'type' => 'case_scenario',
                'title' => 'Prolonged & Obstructed Labour Simulation Drill — Case Scenario',
            ],
            [
                'content' => $polCaseScenario,
                'order_sequence' => 1,
                'is_active' => true,
            ]
        );

        ProgramModuleContent::firstOrCreate(
            [
                'program_module_id' => $module->id,
                'type' => 'case_scenario_progression',
                'title' => 'Prolonged & Obstructed Labour Simulation Drill — Scenario Progression',
            ],
            [
                'content' => "SCENARIO PROGRESSION:\n"
                    .'• 0–2 min: BP 120/70, Pulse 108, Temp 37.4°C, FHR 145, fully dilated, station −2, caput ++, '
                    .'moulding ++. Client exhausted: "I cannot push anymore." Introduce self and reassure; perform '
                    .'ABCDE; review the referral note and Labour Care Guide; assess maternal vitals and fetal '
                    .'wellbeing (FHR); abdominal and vaginal examination; recognize prolonged second stage; call for '
                    ."help.\n"
                    .'• 2–4 min: BP 110/70, Pulse 118, Temp 37.6°C, FHR 115, station −2, caput +++, moulding +++, '
                    .'Bandl\'s ring beginning. Recognize obstructed labour; suspect CPD; stop oxytocin immediately; '
                    .'explain findings; insert two large-bore IV cannulas; start normal saline; give oxygen 6–8 L/min '
                    .'by face mask; left-lateral position; draw blood for FBC, U/E, group-and-cross-match; '
                    ."catheterize; check urine for ketones/acetones; notify senior clinician, theatre and anaesthesia.\n"
                    .'• 4–6 min: BP 100/60, Pulse 126, Temp 38.0°C, FHR 95, blood-stained urine, Bandl\'s ring '
                    .'obvious, caput +++. Diagnose obstructed labour secondary to CPD; continue resuscitation; give '
                    .'broad-spectrum antibiotics; prepare for emergency caesarean, obtain consent, arrange blood; '
                    .'continue fetal monitoring. Vacuum is contraindicated here (caput +++, station −2).'."\n"
                    .'• 6–8 min: BP 90/60, Pulse 136, RR 30, Temp 38.5°C, FHR 80, minimal urine output. Continue '
                    .'oxygen and IV fluids; prepare for immediate theatre transfer; SBAR handover; inform the '
                    ."neonatal team.\n"
                    .'• 8–10 min: With good management, BP improves to 95/60. If severely delayed instead: BP 70/40, '
                    .'Pulse 150, FHR absent, sudden severe pain then collapse — recognize uterine rupture, continue '
                    .'resuscitation, arrange immediate laparotomy/caesarean, communicate with the family, document.',
                'order_sequence' => 1,
                'is_active' => true,
            ]
        );

        ProgramModuleContent::firstOrCreate(
            [
                'program_module_id' => $module->id,
                'type' => 'expected_learning_outcome',
                'title' => 'Expected Learning Outcome',
            ],
            [
                'content' => 'By the end of this module, the mentee should be able to recognize prolonged labour '
                    .'progressing to obstructed labour — including cephalopelvic disproportion, malpresentation and '
                    .'impending uterine rupture (Bandl\'s ring) — interpret the Labour Care Guide to support this '
                    .'diagnosis, avoid inappropriate oxytocin augmentation or vacuum use once obstruction is '
                    .'suspected, stabilize the mother using the ABCDE approach, and make a timely decision for '
                    .'caesarean section or referral while demonstrating SBAR communication, closed-loop '
                    .'communication and effective team leadership.',
                'order_sequence' => 1,
                'is_active' => true,
            ]
        );

        $this->setModuleObjectivesAndContent(
            $module,
            [
                'Recognize prolonged and obstructed labour early, including cephalopelvic disproportion (CPD), malpresentation and signs of impending uterine rupture.',
                'Interpret the Labour Care Guide correctly to support the diagnosis.',
                'Avoid inappropriate oxytocin augmentation in suspected obstruction.',
                'Stabilize the mother (ABCDE), stop oxytocin, and make a timely decision for caesarean section or referral.',
                'Demonstrate SBAR, closed-loop communication, leadership and effective multidisciplinary response.',
            ],
            [
                ['label' => 'Drill', 'duration' => '10-12 min'],
                ['label' => 'Debrief', 'duration' => '20-25 min'],
            ]
        );

        $rubric = ModuleRubric::firstOrCreate(
            ['program_module_id' => $module->id],
            [
                'title' => 'Module 3: Prolonged & Obstructed Labour — Practical Skills Assessment',
                'description' => 'Hands-on practical rubric assessing recognition and emergency management of '
                    .'prolonged labour progressing to obstructed labour, including maternal stabilization and timely '
                    .'escalation for caesarean section.',
                'case_scenario' => $polCaseScenario,
                'total_marks' => 24,
                'pass_marks' => (int) round(24 * 0.85),
                'pass_percentage' => round(round(24 * 0.85) / 24 * 100, 2),
                'equipment_supplies' => [
                    'Labour bed, birth companion, whiteboard and marker, clock/timer',
                    'Labour Care Guide, black or blue pens and red pens',
                    'Blood pressure machine, stethoscope, fetoscope or Doppler, pulse oximeter, thermometer, urine sticks',
                    'Oxygen source, face masks, nasal prongs',
                    'Two large-bore IV cannulas (16G/18G), IV giving sets, Ringer\'s lactate / normal saline',
                    'Blood sample bottles and group-and-cross-match forms',
                    'Sterile gloves and examination gloves, sterile delivery pack and suture pack',
                    'Urinary catheter set and urine bag, lubricant',
                    'Caesarean-section consent and referral forms, theatre checklist',
                    'Broad-spectrum antibiotics (per Kenya maternal-sepsis regimen — e.g. cefuroxime + metronidazole ± gentamicin)',
                    'Neonatal resuscitation equipment, warm towels, Ambu-bag with face masks, suction catheters, penguin suckers, radiant warmer',
                ],
                'debrief_questions' => [
                    'How did the assessment feel?',
                    'What are the steps of managing prolonged and obstructed labour?',
                    'Which steps did you perform well?',
                    'Which steps need to be improved?',
                ],
                'order_sequence' => 1,
                'is_active' => true,
            ]
        );

        $quiz = ProgramModuleQuiz::firstOrCreate(
            [
                'program_module_id' => $module->id,
                'type' => 'both',
            ],
            [
                'title' => 'Prolonged & Obstructed Labour Knowledge Assessment (Pre-test & Post-test)',
                'description' => 'A 15-question instrument administered before and after the prolonged/obstructed '
                    .'labour simulation drill to measure knowledge gain on early recognition, safe stabilization and '
                    .'timely escalation.',
                'pass_mark_percentage' => 70.00,
                'order_sequence' => 1,
                'is_active' => true,
            ]
        );

        $questions = [
            [
                'text' => 'A 19-year-old primigravida at 40+3 weeks has been fully dilated for 4 hours with no descent despite strong contractions and oxytocin augmentation. The most likely diagnosis is:',
                'explanation' => 'Arrest of descent beyond 3 hours in a nullipara, despite adequate contractions and augmentation already attempted, indicates obstruction — most commonly from cephalopelvic disproportion.',
                'options' => [
                    ['text' => 'Prolonged latent phase', 'correct' => false],
                    ['text' => 'Prolonged active phase', 'correct' => false],
                    ['text' => 'Obstructed labour secondary to cephalopelvic disproportion (CPD)', 'correct' => true],
                    ['text' => 'Incoordinate uterine action', 'correct' => false],
                    ['text' => 'Normal second stage progress', 'correct' => false],
                ],
            ],
            [
                'text' => 'In a woman with suspected obstructed labour, oxytocin infusion should be:',
                'explanation' => 'Oxytocin increases the risk of uterine rupture and fetal compromise once obstruction is suspected and must be stopped immediately.',
                'options' => [
                    ['text' => 'Increased to achieve adequate contractions', 'correct' => false],
                    ['text' => 'Stopped immediately', 'correct' => true],
                    ['text' => 'Continued at the same rate', 'correct' => false],
                    ['text' => 'Given only if the fetal heart is normal', 'correct' => false],
                    ['text' => 'Doubled to overcome the obstruction', 'correct' => false],
                ],
            ],
            [
                'text' => 'Which clinical finding is most indicative of obstructed labour?',
                'explanation' => 'Strong contractions with no fetal descent, together with severe moulding and caput succedaneum, are classic signs that the fetal head cannot negotiate the pelvis.',
                'options' => [
                    ['text' => 'Cervical dilatation of 6 cm after 8 hours', 'correct' => false],
                    ['text' => 'Strong contractions with no fetal descent, severe moulding and caput succedaneum', 'correct' => true],
                    ['text' => 'Maternal pulse 100 bpm and BP 110/70 mmHg', 'correct' => false],
                    ['text' => 'Fetal heart rate of 140 bpm', 'correct' => false],
                    ['text' => 'Cervix fully effaced with 2 cm dilatation at onset of labour', 'correct' => false],
                ],
            ],
            [
                'text' => 'The appearance of Bandl\'s ring in a woman in labour indicates:',
                'explanation' => 'Bandl\'s ring is a pathological retraction ring seen with obstructed labour and signals impending uterine rupture, requiring emergency laparotomy/caesarean section.',
                'options' => [
                    ['text' => 'Impending uterine rupture', 'correct' => true],
                    ['text' => 'Uterine atony', 'correct' => false],
                    ['text' => 'Placental abruption', 'correct' => false],
                    ['text' => 'Chorioamnionitis', 'correct' => false],
                    ['text' => 'A normal retraction ring in active labour', 'correct' => false],
                ],
            ],
            [
                'text' => 'A primigravida in the second stage for > 3 hours with no descent should have:',
                'explanation' => 'Prolonged second stage in a nullipara (>3 hours, or >4 hours with epidural) with no descent warrants comprehensive assessment for obstructed labour and preparation for caesarean section, not continued pushing or oxytocin.',
                'options' => [
                    ['text' => 'Continued encouragement to push', 'correct' => false],
                    ['text' => 'Immediate vacuum extraction', 'correct' => false],
                    ['text' => 'Comprehensive assessment for obstructed labour and preparation for caesarean section', 'correct' => true],
                    ['text' => 'Augmentation with oxytocin', 'correct' => false],
                    ['text' => 'Immediate discharge home to continue labouring', 'correct' => false],
                ],
            ],
            [
                'text' => 'During management of obstructed labour, the immediate priorities include all the following EXCEPT:',
                'explanation' => 'Vacuum (or forceps) delivery is contraindicated once obstruction is suspected because the head cannot be safely delivered vaginally; stopping oxytocin, IV access and catheterization are all correct immediate priorities.',
                'options' => [
                    ['text' => 'Stop oxytocin', 'correct' => false],
                    ['text' => 'Insert two large-bore IV lines', 'correct' => false],
                    ['text' => 'Perform bladder catheterization', 'correct' => false],
                    ['text' => 'Perform assisted vacuum delivery', 'correct' => true],
                    ['text' => 'Draw blood for FBC and group-and-cross-match', 'correct' => false],
                ],
            ],
            [
                'text' => 'Which of the following is NOT an indication/prerequisite for augmentation of labour?',
                'explanation' => 'CPD (or any obstruction/malpresentation) is a contraindication to augmentation, not an indication. Before augmenting, also confirm a reassuring fetal status and assess membrane status.',
                'options' => [
                    ['text' => 'Ineffective uterine contractions', 'correct' => false],
                    ['text' => 'Cephalopelvic disproportion', 'correct' => true],
                    ['text' => 'Cervix ≥ 5 cm dilated', 'correct' => false],
                    ['text' => 'Cephalic presentation', 'correct' => false],
                    ['text' => 'Reassuring fetal heart rate', 'correct' => false],
                ],
            ],
            [
                'text' => 'The Labour Care Guide is a critical tool in preventing obstructed labour because it:',
                'explanation' => 'The LCG\'s systematic monitoring and alert thresholds help detect slow labour progress early, prompting timely intervention before obstruction develops.',
                'options' => [
                    ['text' => 'Helps in early detection of slow progress and prompts timely intervention', 'correct' => true],
                    ['text' => 'Measures amniotic fluid volume', 'correct' => false],
                    ['text' => 'Diagnoses malpresentation', 'correct' => false],
                    ['text' => 'Replaces clinical examination', 'correct' => false],
                    ['text' => 'Documents fetal heart rate trends only', 'correct' => false],
                ],
            ],
            [
                'text' => 'A key complication of prolonged obstructed labour in a primigravida is:',
                'explanation' => 'Prolonged obstructed labour can cause postpartum haemorrhage, obstetric fistula, and uterine rupture, among other complications — all of the listed outcomes are recognized risks.',
                'options' => [
                    ['text' => 'Postpartum haemorrhage', 'correct' => false],
                    ['text' => 'Obstetric fistula', 'correct' => false],
                    ['text' => 'Uterine rupture', 'correct' => false],
                    ['text' => 'All of the above', 'correct' => true],
                    ['text' => 'Placenta praevia', 'correct' => false],
                ],
            ],
            [
                'text' => 'In obstructed labour with suspected uterine rupture, the most appropriate immediate action is:',
                'explanation' => 'Suspected uterine rupture is a maternal and fetal emergency requiring aggressive fluid resuscitation and immediate laparotomy to control haemorrhage and deliver the baby.',
                'options' => [
                    ['text' => 'Continue oxytocin to deliver vaginally', 'correct' => false],
                    ['text' => 'Aggressive fluid resuscitation and immediate laparotomy', 'correct' => true],
                    ['text' => 'Expectant management', 'correct' => false],
                    ['text' => 'Give tocolytics', 'correct' => false],
                    ['text' => 'Administer misoprostol to expedite delivery', 'correct' => false],
                ],
            ],
            [
                'text' => 'Respectful maternity care during management of obstructed labour includes:',
                'explanation' => 'Respectful maternity care requires explaining findings and the plan to the woman and her companion, obtaining consent, and never leaving her unattended during distress.',
                'options' => [
                    ['text' => 'Explaining findings and plan to the woman and companion', 'correct' => true],
                    ['text' => 'Keeping the woman uninformed to reduce anxiety', 'correct' => false],
                    ['text' => 'Performing procedures without consent', 'correct' => false],
                    ['text' => 'Leaving the woman alone during distress', 'correct' => false],
                    ['text' => 'Restraining the woman during procedures to reduce movement', 'correct' => false],
                ],
            ],
            [
                'text' => 'A major system-related cause of prolonged/obstructed labour in low-resource settings is:',
                'explanation' => 'Delayed recognition due to poor LCG use, absence of a functioning theatre, and lack of timely referral all contribute at a systems level to prolonged/obstructed labour and its complications.',
                'options' => [
                    ['text' => 'Delayed recognition due to poor Labour Care Guide use', 'correct' => false],
                    ['text' => 'Absence of a functioning theatre', 'correct' => false],
                    ['text' => 'Lack of timely referral', 'correct' => false],
                    ['text' => 'All of the above', 'correct' => true],
                    ['text' => 'Shortage of oxytocin supplies', 'correct' => false],
                ],
            ],
            [
                'text' => 'The single most important lesson from a prolonged/obstructed labour simulation is:',
                'explanation' => 'Every prolonged labour must be assessed for obstruction and acted upon promptly to prevent uterine rupture and other serious complications.',
                'options' => [
                    ['text' => 'Every prolonged labour must be assessed for obstruction and acted upon promptly to prevent uterine rupture and other complications', 'correct' => true],
                    ['text' => 'Oxytocin should always be used in the second stage', 'correct' => false],
                    ['text' => 'Instrumental delivery is always safe', 'correct' => false],
                    ['text' => 'Caesarean section should be avoided at all costs', 'correct' => false],
                    ['text' => 'Second stage should never exceed 1 hour in any parity', 'correct' => false],
                ],
            ],
            [
                'text' => 'Which of the following is NOT a common cause of obstructed labour?',
                'explanation' => 'Premature rupture of membranes does not by itself obstruct labour. Hydrocephalus, malpresentation (e.g. brow, shoulder) and uterine fibroids are all recognized structural or fetal causes of obstruction.',
                'options' => [
                    ['text' => 'Hydrocephalus', 'correct' => false],
                    ['text' => 'Malpresentation (e.g. brow, shoulder)', 'correct' => false],
                    ['text' => 'Uterine fibroids', 'correct' => false],
                    ['text' => 'Premature rupture of membranes', 'correct' => true],
                    ['text' => 'Persistent occipito-posterior position', 'correct' => false],
                ],
            ],
            [
                'text' => 'Which maternal complication is most commonly associated with prolonged obstructed labour?',
                'explanation' => 'Obstetric fistula results from prolonged pressure necrosis of the vaginal wall, bladder and/or rectum during obstructed labour, and is a well-recognized long-term complication in under-resourced settings.',
                'options' => [
                    ['text' => 'Placenta accreta', 'correct' => false],
                    ['text' => 'Obstetric fistula', 'correct' => true],
                    ['text' => 'Placenta previa', 'correct' => false],
                    ['text' => 'Gestational diabetes', 'correct' => false],
                    ['text' => 'Uterine leiomyoma', 'correct' => false],
                ],
            ],
        ];

        $this->seedQuestions($quiz, $questions);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // MODULE 4: ACTIVE MANAGEMENT OF THE THIRD STAGE OF LABOR (AMSTL)
    // ─────────────────────────────────────────────────────────────────────────

    private function seedAmtslModule(Program $program): void
    {
        $module = ProgramModule::where('program_id', $program->id)
            ->where('name', 'like', '%Active Management of the Third Stage%')
            ->whereNull('parent_id')
            ->firstOrFail();

        // A ModuleRubric already exists for this module (21-item checklist, 11
        // equipment items, 4 debrief questions) via ModuleRubricSeeder, along
        // with a real case_scenario ProgramModuleContent ("AMTSL Practical
        // Assessment Rubric — Case Scenario"). We deliberately do not touch
        // the rubric or add a duplicate case_scenario here.

        ProgramModuleContent::firstOrCreate(
            [
                'program_module_id' => $module->id,
                'type' => 'introduction',
                'title' => 'Introduction — Active Management of the Third Stage of Labour (AMTSL)',
            ],
            [
                'content' => 'AMTSL is a package of evidence-based interventions performed immediately after birth '
                    .'to facilitate placental delivery and prevent postpartum haemorrhage (PPH), the leading cause '
                    .'of maternal death. This drill strengthens competence in performing AMTSL per the Kenya Basic '
                    .'Obstetric Protocols — each step correctly, safely and in sequence — while maintaining '
                    .'respectful maternity care, communication and continuous monitoring of mother and newborn.',
                'order_sequence' => 1,
                'is_active' => true,
            ]
        );

        ProgramModuleContent::firstOrCreate(
            [
                'program_module_id' => $module->id,
                'type' => 'introduction',
                'title' => 'Critical Safety Points — Errors to Avoid in AMTSL',
            ],
            [
                'content' => 'Giving a uterotonic before delivery of the baby or before excluding a second baby.'."\n"
                    .'Milking the umbilical cord.'."\n"
                    .'Performing controlled cord traction (CCT) without a contraction, or pulling on the cord without '
                    ."counter-traction.\n"
                    .'Failing to inspect the placenta, assess uterine tone, or quantify blood loss.'."\n"
                    .'Missing retained tissue or genital-tract trauma.',
                'order_sequence' => 2,
                'is_active' => true,
            ]
        );

        ProgramModuleContent::firstOrCreate(
            [
                'program_module_id' => $module->id,
                'type' => 'case_scenario_progression',
                'title' => 'AMTSL Simulation Drill — Scenario Progression',
            ],
            [
                'content' => "SCENARIO PROGRESSION (Mary, 28 years, G2P1, healthy term baby just delivered vaginally):\n"
                    .'• 0–2 min: Baby just delivered and vigorous; mother stable; placenta in utero. Introduce '
                    .'yourself and reassure; explain AMTSL and obtain verbal consent; palpate the abdomen to exclude '
                    .'a second baby before giving a uterotonic; administer the recommended uterotonic within one '
                    .'minute of birth; place the calibrated drape under the mother; remove outer gloves (if '
                    ."double-gloved) before cord clamping.\n"
                    .'• 2–4 min: Mother and baby stable; cord continues to pulsate. Perform delayed cord clamping '
                    .'(1–3 minutes); clamp and cut the cord using sterile technique; encourage skin-to-skin and '
                    ."initiate breastfeeding if appropriate; prepare for placental delivery.\n"
                    .'• 4–6 min: Signs of separation appear; a uterine contraction occurs. Perform CCT only during a '
                    .'contraction while applying counter-traction to the uterus; deliver the placenta gently, '
                    .'rotating slowly to prevent membrane tearing; inspect the placenta and membranes; state aloud '
                    ."whether the placenta is complete.\n"
                    .'• 6–8 min: Placenta delivered complete; uterus initially soft; bleeding ≈ 150 mL. Assess '
                    .'uterine tone immediately and massage until firm; assess bleeding using the calibrated drape; '
                    ."examine the birth canal for tears; continue maternal assessment.\n"
                    .'• 8–10 min: Uterus firm; bleeding minimal; mother and baby stable. Continue monitoring vitals, '
                    .'uterine tone and blood loss on the blood-loss monitoring chart; encourage bladder emptying; '
                    .'educate the woman/companion on assessing uterine firmness and when to seek help; document all '
                    .'procedures.'."\n\n"
                    .'SCENARIO VARIATIONS: (A) Retained placenta at 30 minutes despite CCT — confirm duration, '
                    .'encourage bladder emptying (catheterize only if distended), give an additional 10 IU oxytocin '
                    .'only if oxytocin was the initial uterotonic, continue CCT during contractions, and prepare for '
                    .'manual removal under adequate analgesia/anaesthesia if the placenta remains undelivered or '
                    .'bleeding develops. (B) Soft uterus with blood loss ≈ 350 mL — massage immediately, reassess '
                    .'tone, quantify loss, monitor closely; activate the First Response Bundle (E-MOTIVE) if bleeding '
                    .'increases or an abnormal observation appears. (C) Incomplete placenta — recognize retained '
                    .'tissue, explain to the mother, arrange manual removal/evacuation. (D) Cervical/vaginal tear — '
                    .'inspect the birth canal, identify the source, repair if within scope or seek advanced care; '
                    .'apply direct pressure and activate E-MOTIVE if heavy.',
                'order_sequence' => 1,
                'is_active' => true,
            ]
        );

        ProgramModuleContent::firstOrCreate(
            [
                'program_module_id' => $module->id,
                'type' => 'expected_learning_outcome',
                'title' => 'Expected Learning Outcome',
            ],
            [
                'content' => 'By the end of this module, the mentee should be able to explain AMTSL and obtain '
                    .'informed consent, correctly exclude a second baby before administering the appropriate '
                    .'uterotonic within one minute of birth, perform delayed cord clamping and controlled cord '
                    .'traction with counter-traction safely, inspect the placenta and membranes for completeness, '
                    .'assess uterine tone and perform massage when indicated, accurately quantify blood loss and '
                    .'examine the birth canal for trauma, and recognize findings that require escalation — including '
                    .'activation of the E-MOTIVE First Response Bundle.',
                'order_sequence' => 1,
                'is_active' => true,
            ]
        );

        $this->setModuleObjectivesAndContent(
            $module,
            [
                'Explain AMTSL and obtain informed consent.',
                'Exclude a second baby before giving a uterotonic; administer the correct uterotonic within one minute of birth.',
                'Perform delayed cord clamping and controlled cord traction (CCT) with counter-traction correctly.',
                'Inspect the placenta and membranes; assess uterine tone and massage when indicated.',
                'Quantify blood loss; examine the birth canal; monitor mother and newborn; recognize findings needing escalation.',
            ],
            [
                ['label' => 'Drill', 'duration' => '10-12 min'],
                ['label' => 'Debrief', 'duration' => '20-25 min'],
            ]
        );

        // NOTE: ModuleRubric intentionally NOT created/modified here — one already exists.

        $quiz = ProgramModuleQuiz::firstOrCreate(
            [
                'program_module_id' => $module->id,
                'type' => 'both',
            ],
            [
                'title' => 'AMTSL Knowledge Assessment (Pre-test & Post-test)',
                'description' => 'A 15-question instrument administered before and after the AMTSL simulation drill '
                    .'to measure knowledge gain on the correct sequence, uterotonic choice/timing, and recognition of '
                    .'findings requiring escalation.',
                'pass_mark_percentage' => 70.00,
                'order_sequence' => 1,
                'is_active' => true,
            ]
        );

        $questions = [
            [
                'text' => 'AMTSL is primarily performed to:',
                'explanation' => 'AMTSL is the most effective package of interventions for preventing postpartum haemorrhage, the leading cause of maternal death.',
                'options' => [
                    ['text' => 'Speed up placental delivery', 'correct' => false],
                    ['text' => 'Prevent postpartum haemorrhage', 'correct' => true],
                    ['text' => 'Reduce pain during placental delivery', 'correct' => false],
                    ['text' => 'Improve neonatal iron stores', 'correct' => false],
                    ['text' => 'Reduce the length of the third stage only, with no effect on bleeding', 'correct' => false],
                ],
            ],
            [
                'text' => 'Before administering a uterotonic in AMTSL, the midwife must:',
                'explanation' => 'A second baby must always be excluded by abdominal palpation before giving a uterotonic, since uterotonics could compromise an undelivered second twin.',
                'options' => [
                    ['text' => 'Perform controlled cord traction', 'correct' => false],
                    ['text' => 'Massage the uterus', 'correct' => false],
                    ['text' => 'Exclude the presence of a second baby by abdominal palpation', 'correct' => true],
                    ['text' => 'Inspect the placenta', 'correct' => false],
                    ['text' => 'Confirm placental separation signs', 'correct' => false],
                ],
            ],
            [
                'text' => 'The first-line uterotonic recommended for AMTSL in Kenya is:',
                'explanation' => 'Oxytocin 10 IU IM/IV is first-line. Heat-stable carbetocin 100 µg IM/IV is an equivalent first-line option where the cold chain is unreliable; misoprostol/ergometrine are alternatives (observing contraindications).',
                'options' => [
                    ['text' => 'Misoprostol 600 µg rectally', 'correct' => false],
                    ['text' => 'Oxytocin 10 IU IM/IV', 'correct' => true],
                    ['text' => 'Syntometrine', 'correct' => false],
                    ['text' => 'Carbetocin 100 µg IM/IV', 'correct' => false],
                    ['text' => 'Ergometrine 0.5 mg IM', 'correct' => false],
                ],
            ],
            [
                'text' => 'Delayed cord clamping should normally be performed:',
                'explanation' => 'Delayed cord clamping between 1 and 3 minutes after birth is recommended when mother and baby are stable, as it improves neonatal iron stores; the cord should never be milked.',
                'options' => [
                    ['text' => 'Immediately after birth', 'correct' => false],
                    ['text' => 'After 10 minutes', 'correct' => false],
                    ['text' => 'Between 1 and 3 minutes after birth if mother and baby are stable', 'correct' => true],
                    ['text' => 'Only after placental delivery', 'correct' => false],
                    ['text' => '5 to 10 minutes regardless of stability', 'correct' => false],
                ],
            ],
            [
                'text' => 'Controlled cord traction should be performed:',
                'explanation' => 'CCT is performed only during a uterine contraction, with counter-traction on the uterus (guarding), after signs of placental separation have appeared — never by pulling strongly on an unsupported cord.',
                'options' => [
                    ['text' => 'Only during a uterine contraction with counter-traction on the uterus', 'correct' => true],
                    ['text' => 'Without waiting for signs of separation', 'correct' => false],
                    ['text' => 'By pulling strongly on the cord', 'correct' => false],
                    ['text' => 'Before administering the uterotonic', 'correct' => false],
                    ['text' => 'Continuously throughout the third stage regardless of contractions', 'correct' => false],
                ],
            ],
            [
                'text' => 'After delivery of the placenta, the midwife should immediately:',
                'explanation' => 'Uterine tone must be assessed immediately after placental delivery, with massage performed if the uterus is soft, to prevent atony-related postpartum haemorrhage.',
                'options' => [
                    ['text' => 'Examine the birth canal', 'correct' => false],
                    ['text' => 'Assess uterine tone and perform uterine massage if soft', 'correct' => true],
                    ['text' => 'Give a second dose of uterotonic', 'correct' => false],
                    ['text' => 'Suture any tears', 'correct' => false],
                    ['text' => 'Administer a second prophylactic dose of misoprostol', 'correct' => false],
                ],
            ],
            [
                'text' => 'The placenta and membranes must be inspected after delivery to:',
                'explanation' => 'Inspecting the placenta and membranes identifies retained tissue, which increases the risk of postpartum haemorrhage and infection if not recognized and managed.',
                'options' => [
                    ['text' => 'Rule out retained placental tissue', 'correct' => true],
                    ['text' => 'Measure blood loss', 'correct' => false],
                    ['text' => 'Check for cord abnormalities', 'correct' => false],
                    ['text' => 'Determine gestational age', 'correct' => false],
                    ['text' => 'Estimate gestational age at delivery', 'correct' => false],
                ],
            ],
            [
                'text' => 'Blood loss in the third stage should be:',
                'explanation' => 'Blood loss should be objectively quantified using a calibrated drape or blood-collection device, rather than relying on visual estimation, which is unreliable.',
                'options' => [
                    ['text' => 'Quantified using a calibrated drape', 'correct' => true],
                    ['text' => 'Ignored if the uterus is firm', 'correct' => false],
                    ['text' => 'Documented only if > 500 mL', 'correct' => false],
                    ['text' => 'Measured only after 30 minutes', 'correct' => false],
                    ['text' => 'Estimated visually only, without any device', 'correct' => false],
                ],
            ],
            [
                'text' => 'Following AMTSL, maternal observations should be monitored:',
                'explanation' => 'Standard practice is to monitor blood loss, uterine tone, and vital signs every 15 minutes for the first 2 hours after placental delivery, then less frequently if the mother remains stable.',
                'options' => [
                    ['text' => 'Every 15 minutes for 2 hours, then — if stable — every 30 minutes for the next period', 'correct' => true],
                    ['text' => 'Only if bleeding occurs', 'correct' => false],
                    ['text' => 'Once before discharge', 'correct' => false],
                    ['text' => 'Every hour only', 'correct' => false],
                    ['text' => 'Every 4 hours until discharge', 'correct' => false],
                ],
            ],
            [
                'text' => 'If the uterus is soft after placental delivery, the first action should be:',
                'explanation' => 'Uterine massage until firm is the first-line action for a soft (atonic) uterus; bimanual compression and additional uterotonics are reserved for cases where massage and initial measures fail.',
                'options' => [
                    ['text' => 'Give additional uterotonic immediately', 'correct' => false],
                    ['text' => 'Perform bimanual compression', 'correct' => false],
                    ['text' => 'Start IV fluids', 'correct' => false],
                    ['text' => 'Perform uterine massage until firm', 'correct' => true],
                    ['text' => 'Immediate bimanual compression', 'correct' => false],
                ],
            ],
            [
                'text' => 'The E-MOTIVE First Response Bundle is activated in the postpartum period when:',
                'explanation' => 'The trigger is blood loss ≥ 500 mL, or 300–499 mL together with an abnormal observation (haemodynamic instability) on the blood-loss monitoring chart — clinicians should not wait for the full 500 mL mark if the woman is already showing signs.',
                'options' => [
                    ['text' => 'Blood loss is 200 mL', 'correct' => false],
                    ['text' => 'Blood loss ≥ 500 mL, or 300–499 mL with clinical signs of haemodynamic instability', 'correct' => true],
                    ['text' => 'The uterus is firm', 'correct' => false],
                    ['text' => 'The placenta is complete', 'correct' => false],
                    ['text' => 'Only after 1000 mL has been lost', 'correct' => false],
                ],
            ],
            [
                'text' => 'A woman received heat-stable carbetocin after birth. The placenta remains undelivered after 30 minutes with minimal bleeding. The most appropriate next step is:',
                'explanation' => 'This is a retained placenta. Repeat carbetocin is not indicated; a single 10 IU oxytocin dose plus continued CCT may be tried, but if the placenta remains undelivered, the definitive step is manual removal under adequate analgesia/anaesthesia.',
                'options' => [
                    ['text' => 'Repeat carbetocin', 'correct' => false],
                    ['text' => 'Manual removal of the placenta under adequate analgesia/anaesthesia', 'correct' => true],
                    ['text' => 'Misoprostol', 'correct' => false],
                    ['text' => 'Wait a further hour', 'correct' => false],
                    ['text' => 'Administer intravenous ergometrine', 'correct' => false],
                ],
            ],
            [
                'text' => 'Milking the umbilical cord towards the baby is:',
                'explanation' => 'Cord milking is contraindicated within the AMTSL sequence; it is not part of standard delayed cord clamping practice in this protocol.',
                'options' => [
                    ['text' => 'Recommended in all cases', 'correct' => false],
                    ['text' => 'Contraindicated in AMTSL', 'correct' => true],
                    ['text' => 'Useful for delayed cord clamping', 'correct' => false],
                    ['text' => 'Required before uterotonic administration', 'correct' => false],
                    ['text' => 'Recommended to hasten cord blood collection', 'correct' => false],
                ],
            ],
            [
                'text' => 'The most effective component of AMTSL for preventing PPH is:',
                'explanation' => 'Administration of a uterotonic within one minute of birth is the single most effective component of AMTSL in preventing postpartum haemorrhage.',
                'options' => [
                    ['text' => 'Delayed cord clamping', 'correct' => false],
                    ['text' => 'Uterine massage', 'correct' => false],
                    ['text' => 'Administration of a uterotonic', 'correct' => true],
                    ['text' => 'Controlled cord traction', 'correct' => false],
                    ['text' => 'Immediate skin-to-skin contact', 'correct' => false],
                ],
            ],
            [
                'text' => 'The single most important principle in AMTSL is:',
                'explanation' => 'AMTSL\'s value comes from performing the correct sequence and safely administering each intervention to prevent postpartum haemorrhage — not from speed alone or any single step in isolation.',
                'options' => [
                    ['text' => 'Speed of placental delivery', 'correct' => false],
                    ['text' => 'Correct sequence and safe administration of interventions to prevent PPH', 'correct' => true],
                    ['text' => 'Early cord clamping', 'correct' => false],
                    ['text' => 'Routine manual removal of the placenta', 'correct' => false],
                    ['text' => 'Minimizing maternal pain during placental delivery', 'correct' => false],
                ],
            ],
        ];

        $this->seedQuestions($quiz, $questions);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // MODULE 6: MANAGEMENT OF CORD PROLAPSE
    // ─────────────────────────────────────────────────────────────────────────

    private function seedCordProlapseModule(Program $program): void
    {
        $module = ProgramModule::where('program_id', $program->id)
            ->where('name', 'like', '%Cord Prolapse%')
            ->whereNull('parent_id')
            ->firstOrFail();

        ProgramModuleContent::firstOrCreate(
            [
                'program_module_id' => $module->id,
                'type' => 'introduction',
                'title' => 'Introduction — Management of Cord Prolapse',
            ],
            [
                'content' => 'Umbilical cord prolapse is a life-threatening emergency in which the cord descends '
                    .'below or alongside the presenting part after rupture of membranes. Compression between the '
                    .'presenting part and the maternal pelvis compromises fetal oxygenation and can rapidly cause '
                    .'hypoxia, neurological injury or death. Management depends on early recognition, immediate '
                    .'relief of cord compression, activation of the emergency team, and expedited delivery.',
                'order_sequence' => 1,
                'is_active' => true,
            ]
        );

        $cordCaseScenario = 'PATIENT: Jane, 30 years, G3P2 at 39 weeks.'."\n\n"
            .'PRESENTING: Active labour, progressing normally for 6 hours. Immediately after spontaneous rupture of '
            .'membranes, the fetal heart rate drops to 80 bpm; vaginal examination reveals a pulsating umbilical '
            .'cord below the presenting part.'."\n\n"
            .'INITIAL VITAL SIGNS: BP 118/74 mmHg | Pulse 88 bpm | RR 20/min | Temp 36.8°C | FHR 80 bpm | Membranes '
            .'recently ruptured | Cervical dilatation 7 cm | Occiput anterior | Caput/Moulding 0 | Station +1 | '
            .'Cephalic presentation | Contractions 4 in 10 min (50 sec).'."\n\n"
            .'DIAGNOSIS: Umbilical cord prolapse with a live fetus.';

        ProgramModuleContent::firstOrCreate(
            [
                'program_module_id' => $module->id,
                'type' => 'case_scenario',
                'title' => 'Cord Prolapse Simulation Drill — Case Scenario',
            ],
            [
                'content' => $cordCaseScenario,
                'order_sequence' => 1,
                'is_active' => true,
            ]
        );

        ProgramModuleContent::firstOrCreate(
            [
                'program_module_id' => $module->id,
                'type' => 'case_scenario_progression',
                'title' => 'Cord Prolapse Simulation Drill — Scenario Progression',
            ],
            [
                'content' => "SCENARIO PROGRESSION (Jane):\n"
                    .'• 0–2 min: Midwife reports sudden fetal bradycardia (80 bpm) immediately after rupture of '
                    .'membranes; woman reports "something coming down." Introduce yourself and reassure; assess '
                    .'maternal vitals; confirm fetal status and FHR; perform sterile vaginal examination; recognize '
                    .'the prolapsed, pulsating cord; check cord pulsations; explain the diagnosis; call for help '
                    ."immediately.\n"
                    .'• 2–4 min: Pulsating cord felt below the presenting part; FHR remains 80 bpm. Relieve pressure '
                    .'by manually elevating the presenting part (not the cord); position the woman knee–chest or '
                    .'knee–elbow; maintain manual elevation; give oxygen; assign team roles; notify theatre, '
                    ."anaesthesia and neonatal team via SBAR.\n"
                    .'• 4–6 min: Theatre team alerted; woman in knee–chest; FHR improves to 100 bpm. Continue manual '
                    .'elevation; insert an IV cannula and commence fluids; obtain consent for emergency caesarean; '
                    .'prepare theatre. If referral is needed: catheterize, fill the bladder with 500 mL normal '
                    .'saline, give a tocolytic (terbutaline 250 µg SC, or nifedipine if unavailable), maintain '
                    ."position and arrange transfer.\n"
                    .'• 6–8 min: Theatre ready; woman stable; cord continues to pulsate. Escort to theatre '
                    .'maintaining manual elevation; remove the hand only when ready for surgery; ensure the neonatal '
                    ."team is ready; give a complete SBAR handover.\n\n"
                    .'ALTERNATIVE SCENARIOS (advanced learners): Scenario B — cervix fully dilated, vertex at +2, '
                    .'cord pulsating, delivery imminent: recognize that immediate vaginal delivery is faster than '
                    .'caesarean; perform episiotomy if indicated; expedite with vacuum if appropriate (assisted '
                    .'breech extraction if breech); prepare for neonatal resuscitation. Scenario C — cord no longer '
                    .'pulsating: confirm fetal demise; explain compassionately; deliver by the safest route for the '
                    .'mother; avoid unnecessary caesarean unless indicated for obstetric reasons; continue '
                    .'respectful care and emotional support.',
                'order_sequence' => 1,
                'is_active' => true,
            ]
        );

        ProgramModuleContent::firstOrCreate(
            [
                'program_module_id' => $module->id,
                'type' => 'expected_learning_outcome',
                'title' => 'Expected Learning Outcome',
            ],
            [
                'content' => 'By the end of this module, the mentee should be able to differentiate cord prolapse '
                    .'from cord presentation, recognize its risk factors and clinical features and confirm the '
                    .'diagnosis on sterile vaginal examination, assess fetal viability by checking cord pulsations, '
                    .'immediately relieve pressure on the cord through manual elevation of the presenting part and '
                    .'appropriate maternal positioning (knee-chest or knee-elbow), activate the emergency team, and '
                    .'prepare for emergency caesarean section or expedited vaginal birth as clinically indicated — '
                    .'all while maintaining clear communication and respectful maternity care.',
                'order_sequence' => 1,
                'is_active' => true,
            ]
        );

        $this->setModuleObjectivesAndContent(
            $module,
            [
                'Differentiate cord prolapse from cord presentation.',
                'Recognize risk factors and clinical features; confirm the diagnosis on sterile vaginal examination.',
                'Assess fetal viability by checking cord pulsations.',
                'Immediately relieve pressure on the cord and institute appropriate maternal positioning.',
                'Activate the emergency team and prepare for emergency caesarean or expedited vaginal birth.',
                'Demonstrate communication, teamwork and respectful maternity care.',
            ],
            [
                ['label' => 'Drill', 'duration' => '8-10 min'],
                ['label' => 'Debrief', 'duration' => '20-25 min'],
            ]
        );

        $rubric = ModuleRubric::firstOrCreate(
            ['program_module_id' => $module->id],
            [
                'title' => 'Module 6: Management of Cord Prolapse — Practical Skills Assessment',
                'description' => 'Hands-on practical rubric assessing recognition and emergency management of '
                    .'umbilical cord prolapse, including relief of cord compression and preparation for expedited '
                    .'delivery.',
                'case_scenario' => $cordCaseScenario,
                'total_marks' => 18,
                'pass_marks' => (int) round(18 * 0.85),
                'pass_percentage' => round(round(18 * 0.85) / 18 * 100, 2),
                'equipment_supplies' => [
                    'Labour bed',
                    'Blood pressure machine, stethoscope, thermometer',
                    'Fetoscope/Doppler',
                    'Sterile gloves, examination gloves, vaginal-examination set, lubricant',
                    'Oxygen source',
                    'IV cannulas (16G/18G), giving sets, normal saline/Ringer\'s lactate',
                    'Urinary catheter and drainage bag',
                    '500 mL normal saline (for bladder filling if referral is needed)',
                    'Blood sample bottles and request forms',
                    'Caesarean-section consent, theatre checklist, referral forms',
                    'Neonatal resuscitation trolley (bag and mask, radiant warmer, suction, warm towels)',
                    'Terbutaline 250 µg SC (RCOG first-choice tocolytic), or nifedipine if unavailable',
                ],
                'debrief_questions' => [
                    'How did the assessment feel?',
                    'What are the steps of managing umbilical cord prolapse?',
                    'Which steps did you perform well?',
                    'Which steps need to be improved?',
                ],
                'order_sequence' => 1,
                'is_active' => true,
            ]
        );

        $quiz = ProgramModuleQuiz::firstOrCreate(
            [
                'program_module_id' => $module->id,
                'type' => 'both',
            ],
            [
                'title' => 'Management of Cord Prolapse Knowledge Assessment (Pre-test & Post-test)',
                'description' => 'A 15-question instrument administered before and after the cord prolapse '
                    .'simulation drill to measure knowledge gain on recognition, immediate decompression, and '
                    .'expedited delivery.',
                'pass_mark_percentage' => 70.00,
                'order_sequence' => 1,
                'is_active' => true,
            ]
        );

        $questions = [
            [
                'text' => 'Umbilical cord prolapse is best defined as:',
                'explanation' => 'Cord prolapse is the descent of the umbilical cord below the presenting part after rupture of membranes, distinct from cord presentation (cord below the presenting part with membranes still intact).',
                'options' => [
                    ['text' => 'Cord presentation before rupture of membranes', 'correct' => false],
                    ['text' => 'Descent of the umbilical cord below the presenting part after rupture of membranes', 'correct' => true],
                    ['text' => 'Cord around the fetal neck', 'correct' => false],
                    ['text' => 'Cord compression without prolapse', 'correct' => false],
                    ['text' => 'Cord lying below the presenting part with membranes still intact', 'correct' => false],
                ],
            ],
            [
                'text' => 'The most common clinical presentation of cord prolapse is:',
                'explanation' => 'Sudden fetal bradycardia immediately following rupture of membranes is the classic presenting sign of cord prolapse and should prompt urgent sterile vaginal examination.',
                'options' => [
                    ['text' => 'Sudden fetal bradycardia following rupture of membranes', 'correct' => true],
                    ['text' => 'Vaginal bleeding', 'correct' => false],
                    ['text' => 'Maternal hypertension', 'correct' => false],
                    ['text' => 'Uterine hypertonus', 'correct' => false],
                    ['text' => 'Prolonged rupture of membranes with fever', 'correct' => false],
                ],
            ],
            [
                'text' => 'After diagnosing cord prolapse, the immediate priority is to:',
                'explanation' => 'The immediate priority is to relieve pressure on the cord by manually elevating the presenting part, which prevents further compression while the team prepares for delivery.',
                'options' => [
                    ['text' => 'Perform caesarean section', 'correct' => false],
                    ['text' => 'Relieve pressure on the cord by manually elevating the presenting part', 'correct' => true],
                    ['text' => 'Replace the cord into the uterus', 'correct' => false],
                    ['text' => 'Administer tocolytics first', 'correct' => false],
                    ['text' => 'Perform amnioinfusion', 'correct' => false],
                ],
            ],
            [
                'text' => 'When managing cord prolapse, the umbilical cord itself should:',
                'explanation' => 'The cord must not be manipulated or pushed back into the uterus, since handling it risks provoking vasospasm and worsening fetal compromise.',
                'options' => [
                    ['text' => 'Be gently pushed back into the uterus', 'correct' => false],
                    ['text' => 'Be clamped if not pulsating', 'correct' => false],
                    ['text' => 'Not be manipulated or pushed back (to avoid vasospasm)', 'correct' => true],
                    ['text' => 'Be wrapped in warm saline gauze', 'correct' => false],
                    ['text' => 'Be replaced above the pelvic brim and held there manually', 'correct' => false],
                ],
            ],
            [
                'text' => 'The recommended maternal position to relieve cord compression is:',
                'explanation' => 'Knee–chest or knee–elbow position (or exaggerated Sim\'s / head-down left-lateral as a transfer alternative) uses gravity to reduce pressure of the presenting part on the prolapsed cord.',
                'options' => [
                    ['text' => 'Supine position', 'correct' => false],
                    ['text' => 'Lithotomy position', 'correct' => false],
                    ['text' => 'Trendelenburg with legs flat', 'correct' => false],
                    ['text' => 'Knee–chest or knee–elbow position', 'correct' => true],
                    ['text' => 'Semi-Fowler\'s position', 'correct' => false],
                ],
            ],
            [
                'text' => 'Before deciding on management in cord prolapse, it is essential to:',
                'explanation' => 'Checking cord pulsations determines fetal viability and directly guides whether the team proceeds with expedited delivery or manages a confirmed fetal demise.',
                'options' => [
                    ['text' => 'Check for cord pulsations to assess fetal viability', 'correct' => true],
                    ['text' => 'Perform artificial rupture of membranes', 'correct' => false],
                    ['text' => 'Give broad-spectrum antibiotics first', 'correct' => false],
                    ['text' => 'Perform ultrasound', 'correct' => false],
                    ['text' => 'Confirm cervical dilatation only', 'correct' => false],
                ],
            ],
            [
                'text' => 'In cord prolapse with a fully dilated cervix and low station, the best management is:',
                'explanation' => 'When the cervix is fully dilated and the head is low, expedited vaginal delivery (vacuum/forceps, or assisted breech extraction if breech) is faster and safer than caesarean section.',
                'options' => [
                    ['text' => 'Immediate caesarean section', 'correct' => false],
                    ['text' => 'Expedited vaginal delivery (vacuum/forceps or assisted breech if appropriate)', 'correct' => true],
                    ['text' => 'Expectant management', 'correct' => false],
                    ['text' => 'Replace cord and wait', 'correct' => false],
                    ['text' => 'Wait for spontaneous descent and delivery', 'correct' => false],
                ],
            ],
            [
                'text' => 'If the cord is not pulsating during assessment, the midwife should:',
                'explanation' => 'A non-pulsating cord suggests fetal demise; the priority shifts to confirming this compassionately and delivering by the safest route for the mother, avoiding an unnecessary caesarean.',
                'options' => [
                    ['text' => 'Proceed with emergency caesarean section', 'correct' => false],
                    ['text' => 'Administer tocolytics', 'correct' => false],
                    ['text' => 'Confirm fetal demise and manage delivery by the safest route for the mother', 'correct' => true],
                    ['text' => 'Continue manual elevation for 30 minutes', 'correct' => false],
                    ['text' => 'Attempt to restore pulsations with oxygen', 'correct' => false],
                ],
            ],
            [
                'text' => 'Bladder filling with 500 mL normal saline is used in cord prolapse when:',
                'explanation' => 'Bladder filling helps elevate the presenting part off the cord during transfer and is specifically used when referral to another facility is required.',
                'options' => [
                    ['text' => 'Delivery is imminent', 'correct' => false],
                    ['text' => 'The cord is not pulsating', 'correct' => false],
                    ['text' => 'Fetal heart rate is normal', 'correct' => false],
                    ['text' => 'Transfer to another facility is required', 'correct' => true],
                    ['text' => 'The cervix is fully dilated', 'correct' => false],
                ],
            ],
            [
                'text' => 'Which medication may be considered during referral to reduce uterine contractions?',
                'explanation' => 'RCOG GTG 50 specifies terbutaline 250 µg SC as the acute tocolytic of choice for cord prolapse, with nifedipine as an acceptable alternative. Oxytocin, ergometrine and misoprostol are uterotonics and would worsen compression; magnesium sulphate is not used as a tocolytic here.',
                'options' => [
                    ['text' => 'Oxytocin', 'correct' => false],
                    ['text' => 'Ergometrine', 'correct' => false],
                    ['text' => 'A tocolytic (terbutaline first choice; nifedipine if unavailable)', 'correct' => true],
                    ['text' => 'Misoprostol', 'correct' => false],
                    ['text' => 'Magnesium sulphate', 'correct' => false],
                ],
            ],
            [
                'text' => 'Recurrent variable decelerations or fetal bradycardia in cord prolapse is caused by:',
                'explanation' => 'Mechanical compression of the umbilical cord between the presenting part and the maternal pelvis reduces fetal blood flow and oxygenation, producing bradycardia or variable decelerations.',
                'options' => [
                    ['text' => 'Maternal hypotension', 'correct' => false],
                    ['text' => 'Mechanical compression of the umbilical cord', 'correct' => true],
                    ['text' => 'Uterine hyperstimulation', 'correct' => false],
                    ['text' => 'Placental abruption', 'correct' => false],
                    ['text' => 'Maternal fever', 'correct' => false],
                ],
            ],
            [
                'text' => 'When performing manual elevation of the presenting part, the hand should:',
                'explanation' => 'Manual elevation must be maintained continuously until the baby is delivered or the woman is in theatre — it should not be released early, even if the fetal heart rate improves, since compression can recur.',
                'options' => [
                    ['text' => 'Only be used for 10 minutes', 'correct' => false],
                    ['text' => 'Be removed once the woman is in knee–chest position', 'correct' => false],
                    ['text' => 'Be kept in place until the baby is delivered or in theatre', 'correct' => true],
                    ['text' => 'Be replaced by the woman\'s own hand', 'correct' => false],
                    ['text' => 'Be removed once the fetal heart rate normalizes to baseline', 'correct' => false],
                ],
            ],
            [
                'text' => 'The definitive diagnosis of cord prolapse is made by:',
                'explanation' => 'Sterile vaginal examination confirming a palpable, pulsating cord below the presenting part is the definitive means of diagnosing cord prolapse.',
                'options' => [
                    ['text' => 'Abdominal palpation', 'correct' => false],
                    ['text' => 'Ultrasound', 'correct' => false],
                    ['text' => 'Sterile vaginal examination', 'correct' => true],
                    ['text' => 'Cardiotocography', 'correct' => false],
                    ['text' => 'Abdominal ultrasound', 'correct' => false],
                ],
            ],
            [
                'text' => 'A major complication of delayed management of cord prolapse is:',
                'explanation' => 'Prolonged cord compression causes fetal hypoxia, which can lead to neurological injury or death if decompression and delivery are delayed.',
                'options' => [
                    ['text' => 'Fetal hypoxia leading to neurological injury or death', 'correct' => true],
                    ['text' => 'Uterine rupture', 'correct' => false],
                    ['text' => 'Postpartum infection', 'correct' => false],
                    ['text' => 'Retained placenta', 'correct' => false],
                    ['text' => 'Maternal haemorrhage', 'correct' => false],
                ],
            ],
            [
                'text' => 'The single most important principle in the management of cord prolapse is:',
                'explanation' => 'Cord prolapse is a time-critical emergency; the guiding principle is rapid relief of cord compression combined with expedited delivery by the fastest safe route.',
                'options' => [
                    ['text' => 'Immediate replacement of the cord', 'correct' => false],
                    ['text' => 'Rapid relief of cord compression and expedited delivery', 'correct' => true],
                    ['text' => 'Conservative management with tocolytics', 'correct' => false],
                    ['text' => 'Waiting for spontaneous resolution', 'correct' => false],
                    ['text' => 'Prophylactic antibiotic administration', 'correct' => false],
                ],
            ],
        ];

        $this->seedQuestions($quiz, $questions);
    }

    /**
     * Shared helper: seed a set of questions + options for a quiz, following
     * the same firstOrCreate loop structure used by AphModuleContentSeeder.
     */
    private function seedQuestions(ProgramModuleQuiz $quiz, array $questions): void
    {
        foreach ($questions as $seq => $q) {
            $question = QuizQuestion::firstOrCreate(
                [
                    'program_module_quiz_id' => $quiz->id,
                    'question_text' => $q['text'],
                ],
                [
                    'explanation' => $q['explanation'],
                    'order_sequence' => $seq + 1,
                    'is_active' => true,
                ]
            );

            foreach ($q['options'] as $optSeq => $opt) {
                QuizOption::firstOrCreate(
                    [
                        'quiz_question_id' => $question->id,
                        'option_text' => $opt['text'],
                    ],
                    [
                        'is_correct' => $opt['correct'],
                        'order_sequence' => $optSeq + 1,
                    ]
                );
            }
        }
    }
}
