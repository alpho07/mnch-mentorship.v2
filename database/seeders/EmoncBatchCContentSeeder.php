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
 * Seeds content, rubrics, and quizzes for EmONC "Batch C" modules:
 *   - Management of Maternal Shock
 *   - Maternal Resuscitation
 *   - Management of Pre-Eclampsia/Eclampsia
 *   - Immediate Neonatal Resuscitation
 *
 * All clinical facts are drawn from the mentor-facing CHAI EmONC Knowledge Pack,
 * which is treated as authoritative over the mentee manual where the two disagree
 * (notably: pre-eclampsia diastolic BP threshold of 90 mmHg, and the MgSO4
 * toxicity respiratory-rate cutoff of <12 breaths/min).
 */
class EmoncBatchCContentSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $program = Program::where('name', 'Maternal Health (EmONC)')->firstOrFail();

            $this->seedMaternalShock($program);
            $this->seedMaternalResuscitation($program);
            $this->seedPreEclampsiaEclampsia($program);
            $this->seedNeonatalResuscitation($program);
        });

        $this->command->info('EmONC Batch C (Maternal Shock, Maternal Resuscitation, Pre-Eclampsia/Eclampsia, Neonatal Resuscitation) content seeded successfully.');
    }

    /**
     * MODULE: Management of Maternal Shock
     */
    private function seedMaternalShock(Program $program): void
    {
        $module = ProgramModule::where('program_id', $program->id)
            ->where('name', 'like', '%Maternal Shock%')
            ->whereNull('parent_id')
            ->firstOrFail();

        ProgramModuleContent::firstOrCreate(
            [
                'program_module_id' => $module->id,
                'type' => 'introduction',
                'title' => 'Maternal Shock — Overview',
            ],
            [
                'content' => "Maternal shock is a life-threatening obstetric emergency resulting from inadequate tissue perfusion and oxygen delivery to vital organs. Without prompt recognition and immediate intervention, shock rapidly progresses to multiple organ dysfunction, irreversible tissue injury, and maternal death. It remains a major contributor to maternal mortality in Kenya.\n\nThe two most common causes of maternal shock in pregnancy and the postpartum period are hypovolemic shock due to obstetric haemorrhage and septic shock due to severe maternal infection. Cardiogenic and anaphylactic shock are less common causes.\n\nSuccessful management depends on early recognition, prompt activation of the emergency response team, a systematic Airway–Breathing–Circulation–Disability–Exposure (ABCDE) assessment, aggressive resuscitation, identification and treatment of the underlying cause, and continuous monitoring of the maternal response — all performed simultaneously, not sequentially.",
                'order_sequence' => 1,
                'is_active' => true,
            ]
        );

        if (! ProgramModuleContent::where('program_module_id', $module->id)->where('type', 'case_scenario')->exists()) {
            ProgramModuleContent::firstOrCreate(
                [
                    'program_module_id' => $module->id,
                    'type' => 'case_scenario',
                    'title' => 'Maternal Shock — Case Scenario',
                ],
                [
                    'content' => "PATIENT: Grace, 30 years, G3P3, delivered vaginally 20 minutes ago.\n\nPRESENTING COMPLAINT: The placenta delivered completely, but Grace has continued to bleed heavily per vaginum. She was initially stable but is now becoming progressively weak, pale and restless.\n\nINITIAL ASSESSMENT: BP 104/68 mmHg | Pulse 108 bpm | RR 24/min | Temp 36.5°C | SpO2 96% (room air) | Alert but anxious | Uterus soft and enlarged | Vaginal bleeding ~600 mL and continuing | Baby stable with mother.\n\nThe participant is expected to recognize evolving hypovolemic shock secondary to postpartum haemorrhage, initiate immediate resuscitation, identify uterine atony as the likely cause, and institute definitive management while coordinating the multidisciplinary team.",
                    'order_sequence' => 1,
                    'is_active' => true,
                ]
            );
        }

        ProgramModuleContent::firstOrCreate(
            [
                'program_module_id' => $module->id,
                'type' => 'case_scenario_progression',
                'title' => 'Maternal Shock — Scenario Progression',
            ],
            [
                'content' => "0–2 min: Grace reports dizziness/weakness; heavy bleeding continues; pale and anxious; BP 104/68, Pulse 108, uterus soft. EXPECTED: Recognize evolving PPH-related shock; call for help; assign team roles; perform ABCDE; give high-flow O2 (10–15 L/min); begin uterine massage; establish two large-bore IV cannulas; send bloods (FBC, group & cross-match, coagulation profile, U&E); initiate the WHO E-MOTIVE bundle.\n\n2–4 min: Bleeding continues; BP falls to 92/58, Pulse 122, RR 28; increasingly restless — \"I feel like I'm going to faint.\" EXPECTED: Recognize compensated hypovolemic shock; begin rapid crystalloid infusion (Normal Saline/Ringer's Lactate); give uterotonics and tranexamic acid; catheterize bladder and monitor urine output; arrange urgent blood transfusion; continue to identify/treat the cause (4 Ts).\n\n4–6 min: Despite fluids, BP 82/48, Pulse 136, RR 32; confused, cold, clammy; minimal urine output; uterus remains atonic. EXPECTED: Recognize decompensated shock; escalate care; transfuse blood as soon as available; continue PPH protocol management of uterine atony; prepare for theatre; consider a Non-Pneumatic Anti-Shock Garment (NASG) if available.\n\n6–8 min: Following uterotonics, uterine massage and further intervention, the uterus becomes firm and bleeding reduces; BP improves to 96/60, Pulse falls to 112, more responsive. EXPECTED: Continue MEOWS-chart and blood-loss monitoring; maintain IV fluids/blood transfusion; reassess vitals every 15 minutes; monitor urine output; explain the situation to the woman and family; prepare referral if advanced care is needed; complete documentation.",
                'order_sequence' => 2,
                'is_active' => true,
            ]
        );

        ProgramModuleContent::firstOrCreate(
            [
                'program_module_id' => $module->id,
                'type' => 'expected_learning_outcome',
                'title' => 'Maternal Shock — Expected Learning Outcome',
            ],
            [
                'content' => 'By the end of this module, the mentee should be able to rapidly recognize the early clinical features of maternal shock, distinguish hypovolemic from septic shock, perform a systematic ABCDE assessment, initiate oxygen therapy and IV access without delay, begin appropriate fluid resuscitation and blood replacement, identify and treat the underlying cause simultaneously with resuscitation, and demonstrate effective teamwork, communication, monitoring and documentation throughout the emergency.',
                'order_sequence' => 1,
                'is_active' => true,
            ]
        );

        $module->update([
            'objectives' => [
                'Recognize the early clinical features of maternal shock.',
                'Differentiate between hypovolemic and septic shock.',
                'Perform rapid assessment using the ABCDE approach.',
                'Activate the emergency response team promptly.',
                'Initiate oxygen therapy and establish intravenous access.',
                'Begin appropriate fluid resuscitation and blood replacement.',
                'Identify and treat the underlying cause of shock.',
                'Monitor maternal response to treatment.',
                'Demonstrate effective leadership, teamwork and communication during resuscitation.',
                'Document interventions and arrange referral or escalation of care when indicated.',
            ],
            'content' => [
                ['label' => 'Drill', 'duration' => '12-15 min'],
                ['label' => 'Debrief', 'duration' => '20-25 min'],
            ],
        ]);

        $rubric = ModuleRubric::firstOrCreate(
            ['program_module_id' => $module->id],
            [
                'title' => 'Management of Maternal Shock — Practical Skills Assessment',
                'description' => 'Assesses the mentee\'s ability to recognize maternal shock early and lead a systematic, simultaneous resuscitation and treatment of the underlying cause.',
                'case_scenario' => 'Grace, 30 years, G3P3, delivered vaginally 20 minutes ago with heavy ongoing vaginal bleeding. Initially alert but becoming progressively pale, restless and tachycardic, with a soft/enlarged uterus and falling blood pressure — evolving hypovolemic shock secondary to postpartum haemorrhage from uterine atony.',
                'total_marks' => 20,
                'pass_marks' => (int) round(20 * 0.85),
                'pass_percentage' => round(round(20 * 0.85) / 20 * 100, 2),
                'equipment_supplies' => [
                    'Blood pressure machine, stethoscope, pulse oximeter, thermometer, watch/timer',
                    'MEOWS chart and blood-loss monitoring chart / calibrated drape',
                    'Oxygen source with face mask',
                    'Suction machine and oropharyngeal airways',
                    'Bag-valve-mask',
                    'Two large-bore IV cannulas, IV giving sets, crystalloids (Normal Saline, Ringer\'s Lactate)',
                    'Blood sample tubes, blood request forms, transfusion giving set',
                    'Urinary catheter and drainage bag',
                    'Emergency drugs (oxytocin, tranexamic acid, misoprostol, broad-spectrum antibiotics)',
                    'PPH emergency trolley, sterile delivery pack, uterine balloon tamponade (if available)',
                    'Non-pneumatic Anti-Shock Garment (NASG), where available',
                ],
                'debrief_questions' => [
                    'How did the assessment feel?',
                    'What are the steps of recognizing and resuscitating a woman in maternal shock?',
                    'Which steps did you perform well?',
                    'Which steps need to be improved?',
                ],
                'order_sequence' => 1,
                'is_active' => true,
            ]
        );

        $quiz = ProgramModuleQuiz::firstOrCreate(
            ['program_module_id' => $module->id, 'type' => 'both'],
            [
                'title' => 'Maternal Shock Knowledge Assessment (Pre-test & Post-test)',
                'description' => 'A 15-question instrument administered before and after the Maternal Shock simulation drill to measure knowledge gain. The same questions are used for both pre-test and post-test.',
                'pass_mark_percentage' => 70.00,
                'order_sequence' => 1,
                'is_active' => true,
            ]
        );

        $questions = [
            [
                'text' => 'The earliest and most sensitive clinical sign of maternal shock is usually:',
                'explanation' => 'Tachycardia (>100 bpm) is an early compensatory response and often precedes hypotension, which is a late sign. Oliguria and altered mental status also occur later as compensation fails.',
                'options' => [
                    ['text' => 'Tachycardia', 'correct' => true],
                    ['text' => 'Oliguria', 'correct' => false],
                    ['text' => 'Altered mental status', 'correct' => false],
                    ['text' => 'Cold clammy skin', 'correct' => false],
                    ['text' => 'Hypotension', 'correct' => false],
                ],
            ],
            [
                'text' => 'In a pregnant or postpartum woman, the most common cause of hypovolemic shock is:',
                'explanation' => 'Obstetric haemorrhage (e.g. postpartum haemorrhage, abruption, ruptured uterus) is by far the most common cause of hypovolemic shock in the peripartum period.',
                'options' => [
                    ['text' => 'Septic shock', 'correct' => false],
                    ['text' => 'Obstetric haemorrhage', 'correct' => true],
                    ['text' => 'Cardiogenic shock', 'correct' => false],
                    ['text' => 'Neurogenic shock', 'correct' => false],
                    ['text' => 'Anaphylactic shock', 'correct' => false],
                ],
            ],
            [
                'text' => 'The structured approach recommended for initial assessment and resuscitation of maternal shock is:',
                'explanation' => 'ABCDE (Airway, Breathing, Circulation, Disability, Exposure) provides a systematic method of identifying and treating life-threatening problems in order of priority.',
                'options' => [
                    ['text' => 'SOAP', 'correct' => false],
                    ['text' => 'ABCDE', 'correct' => true],
                    ['text' => 'E-MOTIVE', 'correct' => false],
                    ['text' => 'HELPERR', 'correct' => false],
                    ['text' => 'DRABC', 'correct' => false],
                ],
            ],
            [
                'text' => 'High-flow oxygen (10–15 L/min via face mask) should be administered in maternal shock because it:',
                'explanation' => 'High-flow oxygen maximizes tissue oxygen delivery at a time when perfusion is already compromised; it should not be withheld pending saturation results.',
                'options' => [
                    ['text' => 'Improves maternal comfort', 'correct' => false],
                    ['text' => 'Prevents hyperventilation', 'correct' => false],
                    ['text' => 'Is only required if saturation is below 90%', 'correct' => false],
                    ['text' => 'Increases oxygen delivery to tissues', 'correct' => true],
                    ['text' => 'Reduces uterine bleeding directly', 'correct' => false],
                ],
            ],
            [
                'text' => 'The initial fluid of choice for resuscitation in maternal hypovolemic shock is:',
                'explanation' => 'Rapid crystalloid infusion (Normal Saline or Ringer\'s Lactate) is the recommended initial fluid while cross-matched blood is being arranged.',
                'options' => [
                    ['text' => 'Colloids', 'correct' => false],
                    ['text' => 'Crystalloids (Normal Saline or Ringer\'s Lactate)', 'correct' => true],
                    ['text' => '5% Dextrose', 'correct' => false],
                    ['text' => 'Oral rehydration solution', 'correct' => false],
                    ['text' => 'Whole blood only', 'correct' => false],
                ],
            ],
            [
                'text' => 'In a woman with suspected postpartum haemorrhage and shock, the first-line uterotonic is:',
                'explanation' => 'Oxytocin 10 IU IM/IV followed by an infusion is the first-line uterotonic in PPH management, as part of the WHO E-MOTIVE bundle.',
                'options' => [
                    ['text' => 'Oxytocin 10 IU IM/IV followed by infusion', 'correct' => true],
                    ['text' => 'Ergometrine', 'correct' => false],
                    ['text' => 'Carbetocin', 'correct' => false],
                    ['text' => 'Syntometrine', 'correct' => false],
                    ['text' => 'Misoprostol alone', 'correct' => false],
                ],
            ],
            [
                'text' => 'Tranexamic acid should be administered in postpartum haemorrhage:',
                'explanation' => 'Tranexamic acid should be given as soon as possible and within 3 hours of birth; delaying it reduces its effectiveness.',
                'options' => [
                    ['text' => 'Only if bleeding exceeds 1,000 mL', 'correct' => false],
                    ['text' => 'As soon as possible and within 3 hours of birth', 'correct' => true],
                    ['text' => 'After all uterotonics have failed', 'correct' => false],
                    ['text' => 'Only in coagulopathy', 'correct' => false],
                    ['text' => 'Only after blood transfusion has started', 'correct' => false],
                ],
            ],
            [
                'text' => 'A major error in the management of maternal shock is:',
                'explanation' => 'Maternal shock progresses rapidly; delaying life-saving resuscitation while awaiting laboratory results increases maternal morbidity and mortality.',
                'options' => [
                    ['text' => 'Early oxygen administration', 'correct' => false],
                    ['text' => 'Delaying resuscitation while awaiting laboratory results', 'correct' => true],
                    ['text' => 'Inserting two large-bore IV lines', 'correct' => false],
                    ['text' => 'Monitoring urine output', 'correct' => false],
                    ['text' => 'Calling for help immediately', 'correct' => false],
                ],
            ],
            [
                'text' => 'In a woman with maternal shock and a soft, atonic uterus, the midwife should:',
                'explanation' => 'A soft uterus in shock suggests uterine atony as the cause of PPH; uterine massage and the WHO E-MOTIVE bundle should be initiated promptly.',
                'options' => [
                    ['text' => 'Perform uterine massage and initiate the E-MOTIVE bundle', 'correct' => true],
                    ['text' => 'Give antibiotics first', 'correct' => false],
                    ['text' => 'Perform immediate laparotomy', 'correct' => false],
                    ['text' => 'Administer only fluids', 'correct' => false],
                    ['text' => 'Wait for the uterus to firm on its own', 'correct' => false],
                ],
            ],
            [
                'text' => 'The most important reason for early blood transfusion in hypovolemic shock is:',
                'explanation' => 'Blood transfusion restores oxygen-carrying capacity, which crystalloids cannot do; this is critical once significant blood volume has been lost.',
                'options' => [
                    ['text' => 'To improve maternal comfort', 'correct' => false],
                    ['text' => 'To correct coagulopathy only', 'correct' => false],
                    ['text' => 'To restore oxygen-carrying capacity', 'correct' => true],
                    ['text' => 'To prevent infection', 'correct' => false],
                    ['text' => 'To reduce the need for oxygen therapy', 'correct' => false],
                ],
            ],
            [
                'text' => 'Which of the following is a late sign of hypovolemic shock?',
                'explanation' => 'Hypotension typically appears late, after compensatory mechanisms such as tachycardia and tachypnoea have already failed to maintain perfusion.',
                'options' => [
                    ['text' => 'Anxiety', 'correct' => false],
                    ['text' => 'Tachycardia', 'correct' => false],
                    ['text' => 'Hypotension', 'correct' => true],
                    ['text' => 'Tachypnoea', 'correct' => false],
                    ['text' => 'Pallor', 'correct' => false],
                ],
            ],
            [
                'text' => 'In suspected septic shock, broad-spectrum antibiotics should be administered:',
                'explanation' => 'Broad-spectrum IV antibiotics should be given within the first hour of recognizing septic shock; delaying antibiotics increases mortality.',
                'options' => [
                    ['text' => 'After fluid resuscitation', 'correct' => false],
                    ['text' => 'Within the first hour of recognition', 'correct' => true],
                    ['text' => 'Only after blood cultures', 'correct' => false],
                    ['text' => 'When temperature exceeds 39°C', 'correct' => false],
                    ['text' => 'Only once the source of infection is confirmed', 'correct' => false],
                ],
            ],
            [
                'text' => 'Which of the following is most suggestive of septic shock rather than hypovolemic shock?',
                'explanation' => 'Persistent hypotension despite fluid resuscitation, together with signs of infection (fever, foul lochia), points to septic rather than purely hypovolemic shock.',
                'options' => [
                    ['text' => 'Severe hypertension', 'correct' => false],
                    ['text' => 'Persistent hypotension despite fluid resuscitation with signs of infection', 'correct' => true],
                    ['text' => 'Bradycardia after delivery', 'correct' => false],
                    ['text' => 'Polyhydramnios', 'correct' => false],
                    ['text' => 'Painless heavy bleeding only', 'correct' => false],
                ],
            ],
            [
                'text' => 'The Modified Early Obstetric Warning System (MEOWS) chart is useful in maternal shock because it:',
                'explanation' => 'The MEOWS chart tracks physiological parameters over time, helping teams detect deterioration early; it supports, but does not replace, clinical judgement.',
                'options' => [
                    ['text' => 'Helps in early detection of deterioration through physiological parameters', 'correct' => true],
                    ['text' => 'Documents only blood loss', 'correct' => false],
                    ['text' => 'Replaces the need for ABCDE assessment', 'correct' => false],
                    ['text' => 'Replaces clinical judgement', 'correct' => false],
                    ['text' => 'Is only used after delivery', 'correct' => false],
                ],
            ],
            [
                'text' => 'The single most important principle in the management of maternal shock is:',
                'explanation' => 'Shock resuscitation and treatment of the underlying cause must occur simultaneously, with frequent reassessment of the response to treatment.',
                'options' => [
                    ['text' => 'Simultaneous resuscitation and treatment of the underlying cause with frequent reassessment', 'correct' => true],
                    ['text' => 'Focusing only on fluid resuscitation', 'correct' => false],
                    ['text' => 'Delaying oxygen until saturation falls', 'correct' => false],
                    ['text' => 'Relying solely on laboratory results', 'correct' => false],
                    ['text' => 'Treating the cause only after full haemodynamic stabilization', 'correct' => false],
                ],
            ],
        ];

        $this->seedQuestions($quiz, $questions);
    }

    /**
     * MODULE: Maternal Resuscitation
     */
    private function seedMaternalResuscitation(Program $program): void
    {
        $module = ProgramModule::where('program_id', $program->id)
            ->where('name', 'like', '%Maternal Resuscitation%')
            ->whereNull('parent_id')
            ->firstOrFail();

        ProgramModuleContent::firstOrCreate(
            [
                'program_module_id' => $module->id,
                'type' => 'introduction',
                'title' => 'Maternal Resuscitation — Overview',
            ],
            [
                'content' => "Maternal cardiac arrest is a rare but catastrophic obstetric emergency requiring immediate, coordinated, and highly skilled resuscitation. Survival of both the mother and fetus depends on early recognition, prompt initiation of high-quality cardiopulmonary resuscitation (CPR), correction of reversible causes, and effective multidisciplinary teamwork.\n\nMaternal resuscitation differs from standard adult resuscitation because of the physiological changes of pregnancy. From the second trimester, the gravid uterus compresses the inferior vena cava and aorta when the mother lies supine, reducing venous return and cardiac output. Pregnant women also have increased oxygen consumption, reduced functional residual lung capacity, airway oedema, and a higher risk of aspiration.\n\nThese adaptations require modifications to standard CPR: manual left uterine displacement (or left lateral tilt), early airway management, high-flow oxygen, and consideration of perimortem Caesarean section (PMCS) when return of spontaneous circulation (ROSC) has not occurred within four minutes in pregnancies beyond 20 weeks gestation. The DRABC approach — Danger, Response, Airway, Breathing, Circulation — structures the initial response.",
                'order_sequence' => 1,
                'is_active' => true,
            ]
        );

        if (! ProgramModuleContent::where('program_module_id', $module->id)->where('type', 'case_scenario')->exists()) {
            ProgramModuleContent::firstOrCreate(
                [
                    'program_module_id' => $module->id,
                    'type' => 'case_scenario',
                    'title' => 'Maternal Resuscitation — Case Scenario',
                ],
                [
                    'content' => "PATIENT: Jane, 29 years, G2P1 at 34+2 weeks, admitted for observation after complaining of shortness of breath and chest discomfort.\n\nPRESENTING EVENT: While awaiting review, Jane suddenly collapses. She is initially gasping but quickly becomes unresponsive.\n\nINITIAL ASSESSMENT: Unresponsive | Airway partially obstructed by the tongue | Breathing: agonal gasps only | Carotid pulse: absent | BP not recordable | SpO2 unrecordable | Fetal heart rate initially 90 bpm.\n\nThe participant is expected to rapidly recognize maternal cardiac arrest (suspected pulmonary embolism or amniotic fluid embolism), initiate high-quality CPR with pregnancy-specific modifications, identify likely reversible causes (4 Hs and 4 Ts), and determine whether perimortem Caesarean section (PMCS) is indicated if ROSC is not achieved within 4 minutes.",
                    'order_sequence' => 1,
                    'is_active' => true,
                ]
            );
        }

        ProgramModuleContent::firstOrCreate(
            [
                'program_module_id' => $module->id,
                'type' => 'case_scenario_progression',
                'title' => 'Maternal Resuscitation — Scenario Progression',
            ],
            [
                'content' => "0–2 min: Jane collapses; unresponsive, occasional agonal gasps, no carotid pulse. EXPECTED: Ensure scene safety; check responsiveness; call for help immediately; activate the maternal emergency response team; begin DRABC assessment; apply manual left uterine displacement (or left lateral tilt); open the airway (head-tilt chin-lift, or jaw-thrust if trauma suspected); begin bag-mask ventilation with 100% oxygen; start high-quality CPR at 100–120 compressions/min, depth 5–6 cm, ratio 30:2 if no advanced airway yet; assign team roles and start timing the arrest.\n\n2–4 min: CPR ongoing, pulseless electrical activity (PEA) on monitor, no ROSC. EXPECTED: Continue uninterrupted high-quality CPR; rotate compressors every 2 minutes; establish two large-bore IV cannulas; take blood samples without interrupting CPR; give resuscitation medications per protocol; systematically assess the 4 Hs and 4 Ts; prepare PMCS equipment and alert obstetric/neonatal teams.\n\n4–5 min: Four minutes have elapsed with no ROSC; pregnancy is 34 weeks with the fundus well above the umbilicus. EXPECTED: Recognize the indication for perimortem Caesarean section (PMCS); continue uninterrupted CPR while another clinician prepares for immediate PMCS at the site of arrest; continue airway management, oxygenation and compressions throughout the procedure.\n\n5–8 min: PMCS is performed while CPR continues; following delivery of the fetus, maternal pulse returns (ROSC); BP 90/58, Pulse 118; spontaneous respirations resume but she remains unconscious. EXPECTED: Recognize ROSC; continue oxygen therapy; secure the airway if required; continue haemodynamic stabilization with IV fluids/blood products; identify and treat the underlying cause; transfer to ICU/HDU; neonatal team initiates newborn resuscitation; continue close monitoring and complete documentation.",
                'order_sequence' => 2,
                'is_active' => true,
            ]
        );

        ProgramModuleContent::firstOrCreate(
            [
                'program_module_id' => $module->id,
                'type' => 'expected_learning_outcome',
                'title' => 'Maternal Resuscitation — Expected Learning Outcome',
            ],
            [
                'content' => 'By the end of this module, the mentee should be able to promptly recognize maternal cardiac arrest, perform a rapid DRABC assessment, deliver high-quality CPR with pregnancy-specific modifications (manual left uterine displacement, effective ventilation with 100% oxygen), systematically identify and treat reversible causes (4 Hs and 4 Ts), recognize the indication and timing for perimortem Caesarean section, and provide appropriate post-ROSC care while demonstrating strong leadership and closed-loop team communication.',
                'order_sequence' => 1,
                'is_active' => true,
            ]
        );

        $module->update([
            'objectives' => [
                'Recognize maternal cardiac arrest promptly.',
                'Perform a rapid DRABC assessment.',
                'Demonstrate high-quality CPR according to current recommendations.',
                'Apply manual left uterine displacement during resuscitation.',
                'Open and maintain the airway using appropriate airway adjuncts.',
                'Administer effective ventilation with 100% oxygen.',
                'Identify and treat reversible causes of maternal cardiac arrest (4 Hs and 4 Ts).',
                'Demonstrate effective leadership, role allocation, and closed-loop communication.',
                'Recognize the indications for perimortem Caesarean section.',
                'Initiate post-resuscitation care following return of spontaneous circulation (ROSC).',
            ],
            'content' => [
                ['label' => 'Drill', 'duration' => '15-20 min'],
                ['label' => 'Debrief', 'duration' => '20-30 min'],
            ],
        ]);

        $rubric = ModuleRubric::firstOrCreate(
            ['program_module_id' => $module->id],
            [
                'title' => 'Maternal Resuscitation — Practical Skills Assessment',
                'description' => 'Assesses the mentee\'s ability to lead high-quality, pregnancy-modified CPR, identify reversible causes, and recognize the indication for perimortem Caesarean section.',
                'case_scenario' => 'Jane, 29 years, G2P1 at 34+2 weeks, admitted for observation after complaining of shortness of breath and chest tightness. She suddenly collapses, becomes unresponsive with agonal gasps only and no carotid pulse — suspected massive pulmonary embolism or amniotic fluid embolism requiring immediate maternal resuscitation.',
                'total_marks' => 22,
                'pass_marks' => (int) round(22 * 0.85),
                'pass_percentage' => round(round(22 * 0.85) / 22 * 100, 2),
                'equipment_supplies' => [
                    'Oxygen source with flow meter, bag-valve-mask, oropharyngeal airways, suction machine',
                    'Laryngoscope and endotracheal tubes (if available)',
                    'CPR board (if available)',
                    'Cardiac monitor/defibrillator or AED',
                    'Two large-bore IV cannulas, IV giving sets, crystalloids',
                    'Blood collection tubes, blood request forms, blood transfusion set',
                    'Cardiff wedge or firm pillows for left lateral tilt',
                    'Delivery pack, emergency Caesarean section tray, scalpel',
                    'Neonatal resuscitation trolley (radiant warmer, bag and mask, suction, warm towels)',
                ],
                'debrief_questions' => [
                    'How did the assessment feel?',
                    'What are the steps of high-quality maternal CPR with pregnancy-specific modifications?',
                    'Which steps did you perform well?',
                    'Which steps need to be improved?',
                ],
                'order_sequence' => 1,
                'is_active' => true,
            ]
        );

        $quiz = ProgramModuleQuiz::firstOrCreate(
            ['program_module_id' => $module->id, 'type' => 'both'],
            [
                'title' => 'Maternal Resuscitation Knowledge Assessment (Pre-test & Post-test)',
                'description' => 'A 15-question instrument administered before and after the Maternal Resuscitation simulation drill to measure knowledge gain. The same questions are used for both pre-test and post-test.',
                'pass_mark_percentage' => 70.00,
                'order_sequence' => 1,
                'is_active' => true,
            ]
        );

        $questions = [
            [
                'text' => 'Maternal cardiac arrest is best recognized by:',
                'explanation' => 'Recognition requires unresponsiveness, absence of normal breathing (agonal gasps are not normal breathing), and absence of a carotid pulse within 10 seconds.',
                'options' => [
                    ['text' => 'Hypotension and tachycardia', 'correct' => false],
                    ['text' => 'Unresponsiveness + absence of normal breathing and no carotid pulse within 10 seconds', 'correct' => true],
                    ['text' => 'Agonal gasps only', 'correct' => false],
                    ['text' => 'Maternal seizure', 'correct' => false],
                    ['text' => 'Severe hypertension', 'correct' => false],
                ],
            ],
            [
                'text' => 'The primary goal of perimortem Caesarean section (PMCS) during maternal cardiac arrest is to:',
                'explanation' => 'PMCS is performed primarily to improve maternal resuscitation by relieving aortocaval compression from the gravid uterus; fetal survival is a secondary benefit.',
                'options' => [
                    ['text' => 'Improve maternal resuscitation by relieving aortocaval compression', 'correct' => true],
                    ['text' => 'Control haemorrhage', 'correct' => false],
                    ['text' => 'Allow better chest compressions technique only', 'correct' => false],
                    ['text' => 'Save the fetus', 'correct' => false],
                    ['text' => 'Reduce the need for CPR', 'correct' => false],
                ],
            ],
            [
                'text' => 'Which of the following is one of the 4 Ts of reversible causes of cardiac arrest?',
                'explanation' => 'The 4 Ts are Thromboembolism, Toxicity, Tension pneumothorax, and Cardiac tamponade.',
                'options' => [
                    ['text' => 'Tachycardia', 'correct' => false],
                    ['text' => 'Tension pneumothorax', 'correct' => true],
                    ['text' => 'Thrombocytopenia', 'correct' => false],
                    ['text' => 'Thyrotoxicosis', 'correct' => false],
                    ['text' => 'Trauma', 'correct' => false],
                ],
            ],
            [
                'text' => 'The recommended chest compression rate during high-quality CPR in maternal cardiac arrest is:',
                'explanation' => 'Compressions should be delivered at a rate of 100–120 per minute, with full chest recoil and minimal interruptions.',
                'options' => [
                    ['text' => '60–80 per minute', 'correct' => false],
                    ['text' => '100–120 per minute', 'correct' => true],
                    ['text' => '80–100 per minute', 'correct' => false],
                    ['text' => 'As fast as possible', 'correct' => false],
                    ['text' => '130–150 per minute', 'correct' => false],
                ],
            ],
            [
                'text' => 'In maternal cardiac arrest, the chest compression depth should be:',
                'explanation' => 'Compression depth should be 5–6 cm to generate adequate cardiac output.',
                'options' => [
                    ['text' => 'As deep as possible', 'correct' => false],
                    ['text' => '4–5 cm', 'correct' => false],
                    ['text' => '5–6 cm', 'correct' => true],
                    ['text' => '6–7 cm', 'correct' => false],
                    ['text' => '2–3 cm', 'correct' => false],
                ],
            ],
            [
                'text' => 'Perimortem Caesarean section should be considered during maternal resuscitation when:',
                'explanation' => 'PMCS is indicated if there is no ROSC within 4 minutes of effective CPR in a pregnancy ≥20 weeks gestation, with delivery ideally by 5 minutes after arrest.',
                'options' => [
                    ['text' => 'No ROSC within 4 minutes in a pregnancy ≥20 weeks gestation', 'correct' => true],
                    ['text' => 'Only after 10 minutes of unsuccessful CPR', 'correct' => false],
                    ['text' => 'Only if fetal distress is present', 'correct' => false],
                    ['text' => 'Only in the operating theatre', 'correct' => false],
                    ['text' => 'Immediately at the onset of arrest regardless of gestation', 'correct' => false],
                ],
            ],
            [
                'text' => 'Chest compressors performing CPR should ideally be changed every:',
                'explanation' => 'Compressor fatigue reduces compression quality; rotating compressors every 2 minutes maintains high-quality CPR.',
                'options' => [
                    ['text' => '30 seconds', 'correct' => false],
                    ['text' => '1 minute', 'correct' => false],
                    ['text' => '2 minutes', 'correct' => true],
                    ['text' => '5 minutes', 'correct' => false],
                    ['text' => '10 minutes', 'correct' => false],
                ],
            ],
            [
                'text' => 'During CPR on a pregnant woman, chest compressions should be performed:',
                'explanation' => 'Standard hand position is used with manual left uterine displacement (or left lateral tilt) applied throughout to relieve aortocaval compression.',
                'options' => [
                    ['text' => 'Slightly higher on the sternum only', 'correct' => false],
                    ['text' => 'In the standard position, with manual left uterine displacement', 'correct' => true],
                    ['text' => 'With the woman in full left lateral position', 'correct' => false],
                    ['text' => 'Only after delivery of the baby', 'correct' => false],
                    ['text' => 'With the woman fully supine and no tilt', 'correct' => false],
                ],
            ],
            [
                'text' => 'A major error during maternal resuscitation is:',
                'explanation' => 'Frequent interruptions in chest compressions markedly reduce coronary and cerebral perfusion and worsen outcomes.',
                'options' => [
                    ['text' => 'Frequent interruptions in chest compressions', 'correct' => true],
                    ['text' => 'Rotating compressors every two minutes', 'correct' => false],
                    ['text' => 'Calling for help immediately', 'correct' => false],
                    ['text' => 'Using a bag-valve-mask with 100% oxygen', 'correct' => false],
                    ['text' => 'Applying manual left uterine displacement', 'correct' => false],
                ],
            ],
            [
                'text' => 'Which airway adjunct is commonly used during basic maternal resuscitation?',
                'explanation' => 'An oropharyngeal airway helps maintain airway patency during bag-mask ventilation in an unresponsive patient without a gag reflex.',
                'options' => [
                    ['text' => 'Nasogastric tube', 'correct' => false],
                    ['text' => 'Chest drain', 'correct' => false],
                    ['text' => 'Foley catheter', 'correct' => false],
                    ['text' => 'Oropharyngeal airway', 'correct' => true],
                    ['text' => 'Umbilical catheter', 'correct' => false],
                ],
            ],
            [
                'text' => 'The most common reversible cause of maternal cardiac arrest in the peripartum period is:',
                'explanation' => 'Hypovolaemia due to obstetric haemorrhage is the most common reversible cause of peripartum cardiac arrest.',
                'options' => [
                    ['text' => 'Tension pneumothorax', 'correct' => false],
                    ['text' => 'Hypothermia', 'correct' => false],
                    ['text' => 'Hypovolaemia due to haemorrhage', 'correct' => true],
                    ['text' => 'Hyperkalaemia', 'correct' => false],
                    ['text' => 'Cardiac tamponade', 'correct' => false],
                ],
            ],
            [
                'text' => 'Which statement regarding maternal CPR is correct?',
                'explanation' => 'High-quality CPR with minimal interruptions is the single most important determinant of survival in cardiac arrest.',
                'options' => [
                    ['text' => 'Chest compressions should be interrupted frequently to reassess the pulse', 'correct' => false],
                    ['text' => 'High-quality CPR with minimal interruptions improves survival', 'correct' => true],
                    ['text' => 'Ventilation is unnecessary if chest compressions are effective', 'correct' => false],
                    ['text' => 'Pregnancy does not alter resuscitation techniques', 'correct' => false],
                    ['text' => 'Compression depth is unimportant as long as the rate is correct', 'correct' => false],
                ],
            ],
            [
                'text' => 'During maternal resuscitation, the mnemonic DRABC begins with:',
                'explanation' => 'DRABC stands for Danger, Response, Airway, Breathing, Circulation — scene safety is assessed first.',
                'options' => [
                    ['text' => 'Defibrillation', 'correct' => false],
                    ['text' => 'Diagnosis', 'correct' => false],
                    ['text' => 'Drugs', 'correct' => false],
                    ['text' => 'Danger', 'correct' => true],
                    ['text' => 'Delivery', 'correct' => false],
                ],
            ],
            [
                'text' => 'In maternal cardiac arrest, ventilation should be performed with:',
                'explanation' => 'Unlike neonatal resuscitation (which uses room air), maternal CPR uses 100% oxygen to maximize oxygen delivery during arrest.',
                'options' => [
                    ['text' => '100% oxygen', 'correct' => true],
                    ['text' => '21% oxygen', 'correct' => false],
                    ['text' => 'Nitrous oxide', 'correct' => false],
                    ['text' => 'No ventilation until an advanced airway is placed', 'correct' => false],
                    ['text' => '50% oxygen', 'correct' => false],
                ],
            ],
            [
                'text' => 'The single most important principle in maternal resuscitation is:',
                'explanation' => 'High-quality CPR with minimal interruptions, combined with pregnancy-specific modifications and rapid treatment of reversible causes, is the core principle of maternal resuscitation.',
                'options' => [
                    ['text' => 'Saving the fetus first', 'correct' => false],
                    ['text' => 'Immediate perimortem Caesarean section in all cases', 'correct' => false],
                    ['text' => 'Focusing on advanced airway management above all else', 'correct' => false],
                    ['text' => 'High-quality CPR with minimal interruptions combined with pregnancy-specific modifications and rapid treatment of reversible causes', 'correct' => true],
                    ['text' => 'Delaying CPR until the emergency team arrives', 'correct' => false],
                ],
            ],
        ];

        $this->seedQuestions($quiz, $questions);
    }

    /**
     * MODULE: Management of Pre-Eclampsia/Eclampsia
     */
    private function seedPreEclampsiaEclampsia(Program $program): void
    {
        $module = ProgramModule::where('program_id', $program->id)
            ->where('name', 'like', '%Pre-Eclampsia%')
            ->whereNull('parent_id')
            ->firstOrFail();

        ProgramModuleContent::firstOrCreate(
            [
                'program_module_id' => $module->id,
                'type' => 'introduction',
                'title' => 'Pre-Eclampsia and Eclampsia — Overview',
            ],
            [
                'content' => "Hypertensive disorders of pregnancy are among the leading causes of maternal and perinatal morbidity and mortality worldwide. In Kenya, pre-eclampsia with severe features and eclampsia remain major contributors to maternal deaths through complications such as cerebral haemorrhage, pulmonary oedema, renal failure, HELLP syndrome, placental abruption, disseminated intravascular coagulation, and multi-organ failure.\n\nPre-eclampsia is a multisystem disorder characterized by new-onset hypertension (blood pressure ≥140/90 mmHg — i.e. a diastolic threshold of 90 mmHg) occurring after 20 weeks of gestation in a previously normotensive woman, with or without proteinuria. Severe disease is diagnosed when hypertension is accompanied by severe clinical or laboratory features, including BP ≥160/110 mmHg, persistent headache, visual disturbances, epigastric or right-upper-quadrant pain, pulmonary oedema, oliguria, thrombocytopenia, elevated liver enzymes, renal impairment, or fetal compromise.\n\nEclampsia is the occurrence of generalized tonic-clonic seizures in a woman with pre-eclampsia that cannot be attributed to another neurological cause. Any seizure occurring during pregnancy, labour, or within 12 weeks postpartum should be treated as eclampsia until proven otherwise — up to 44% of eclamptic seizures occur postpartum, particularly within the first 48 hours. Since delivery is the only definitive treatment, management balances maternal stabilization with the timing of delivery.",
                'order_sequence' => 1,
                'is_active' => true,
            ]
        );

        if (! ProgramModuleContent::where('program_module_id', $module->id)->where('type', 'case_scenario')->exists()) {
            ProgramModuleContent::firstOrCreate(
                [
                    'program_module_id' => $module->id,
                    'type' => 'case_scenario',
                    'title' => 'Pre-Eclampsia and Eclampsia — Case Scenario',
                ],
                [
                    'content' => "PATIENT: Faith, 24 years, G1P0 at 35+4 weeks, attended antenatal care only twice, no known chronic illness.\n\nPRESENTING COMPLAINT: Severe frontal headache, blurred vision, and severe epigastric pain for six hours. While being assessed in the labour ward, she suddenly develops a generalized tonic-clonic seizure.\n\nINITIAL ASSESSMENT: BP 178/116 mmHg | Pulse 102 bpm | RR 22/min | Temp 36.8°C | SpO2 96% (room air) | Urine dipstick: protein +++ | Alert but anxious before the seizure | FHR 148 bpm | Cervix closed, no contractions.\n\nThe participant is expected to recognize eclampsia complicating severe pre-eclampsia, stabilize the mother using the ABC approach, administer magnesium sulphate correctly, control severe hypertension, monitor for magnesium toxicity, assess fetal wellbeing, and prepare for delivery once the mother is stabilized.",
                    'order_sequence' => 1,
                    'is_active' => true,
                ]
            );
        }

        ProgramModuleContent::firstOrCreate(
            [
                'program_module_id' => $module->id,
                'type' => 'case_scenario_progression',
                'title' => 'Pre-Eclampsia and Eclampsia — Scenario Progression',
            ],
            [
                'content' => "0–2 min: Worsening headache, blurred vision, severe epigastric pain; BP 178/116; proteinuria +++; anxious, \"I can't see properly.\" EXPECTED: Recognize severe pre-eclampsia; call for help; ABC assessment; place woman in left lateral position; insert IV access; give oxygen if indicated; monitor vitals; prepare magnesium sulphate and antihypertensive medication; explain the condition to the woman and companion.\n\n2–4 min: A generalized tonic-clonic seizure occurs lasting ~1 minute; SpO2 falls to 90%. EXPECTED: Protect from injury; do NOT restrain or place objects in the mouth; maintain airway patency; position left lateral AFTER the seizure; suction secretions if necessary; give high-flow oxygen; call for additional help; administer the MgSO4 loading dose (4 g IV over 5–20 min + 10 g IM, 14 g total).\n\n4–6 min: Seizure stops; drowsy, responds only to pain; BP 172/112; RR 20/min; FHR 150 bpm. EXPECTED: Continue stabilization; give antihypertensive therapy (hydralazine, labetalol, or nifedipine per local protocol); insert urinary catheter and monitor urine output; send bloods (FBC, LFTs, RFTs, coagulation profile, group & cross-match); assess fetal wellbeing; prepare for delivery once stabilized.\n\n6–8 min: 15 minutes after the loading dose, a second seizure occurs; BP remains elevated; RR 18/min; patellar reflexes present. EXPECTED: Recognize recurrent eclampsia; give an additional 2 g IV MgSO4 (20%) over 5–10 minutes; reassess ABC; continue oxygen and seizure precautions; reassess fetal condition and escalate care.\n\n8–10 min: More alert; BP decreases to 156/98; RR 18/min; adequate urine output; no further seizures; FHR reassuring. EXPECTED: Continue MgSO4 maintenance therapy; monitor BP, RR, urine output and patellar reflexes hourly; counsel woman and family; arrange definitive management including delivery after stabilization; complete documentation and referral if needed.",
                'order_sequence' => 2,
                'is_active' => true,
            ]
        );

        ProgramModuleContent::firstOrCreate(
            [
                'program_module_id' => $module->id,
                'type' => 'expected_learning_outcome',
                'title' => 'Pre-Eclampsia and Eclampsia — Expected Learning Outcome',
            ],
            [
                'content' => 'By the end of this module, the mentee should be able to recognize severe pre-eclampsia and eclampsia promptly, safely manage an eclamptic seizure without restraining the woman, correctly administer the magnesium sulphate loading and maintenance doses, monitor for and manage magnesium sulphate toxicity (respiratory rate <12/min, absent reflexes, oliguria), safely lower severe blood pressure while avoiding sudden drops, maintain conservative fluid balance, assess fetal wellbeing after maternal stabilization, and recognize indications for delivery.',
                'order_sequence' => 1,
                'is_active' => true,
            ]
        );

        $module->update([
            'objectives' => [
                'Recognize pre-eclampsia with severe features using clinical and laboratory findings.',
                'Recognize and immediately manage an eclamptic seizure.',
                'Perform rapid maternal assessment using the ABC approach.',
                'Correctly administer magnesium sulphate loading and maintenance doses.',
                'Monitor for magnesium sulphate toxicity and institute appropriate management.',
                'Safely lower severe blood pressure using recommended antihypertensive medications.',
                'Implement appropriate fluid restriction and fluid balance monitoring.',
                'Assess fetal wellbeing during maternal stabilization.',
                'Recognize indications for immediate delivery.',
                'Demonstrate effective teamwork, communication, leadership, and documentation during obstetric emergencies.',
            ],
            'content' => [
                ['label' => 'Drill', 'duration' => '15-20 min'],
                ['label' => 'Debrief', 'duration' => '20-30 min'],
            ],
        ]);

        $rubric = ModuleRubric::firstOrCreate(
            ['program_module_id' => $module->id],
            [
                'title' => 'Management of Pre-Eclampsia/Eclampsia — Practical Skills Assessment',
                'description' => 'Assesses the mentee\'s ability to recognize severe pre-eclampsia and eclampsia, safely manage a seizure, and correctly administer and monitor magnesium sulphate therapy.',
                'case_scenario' => 'Faith, 24 years, G1P0 at 35+4 weeks, presents with severe frontal headache, blurred vision and epigastric pain, then develops a generalized tonic-clonic seizure. BP 178/116 mmHg, proteinuria +++ — eclampsia complicating severe pre-eclampsia.',
                'total_marks' => 20,
                'pass_marks' => (int) round(20 * 0.85),
                'pass_percentage' => round(round(20 * 0.85) / 20 * 100, 2),
                'equipment_supplies' => [
                    'Oxygen source with flow meter, suction machine and catheters, bag-valve-mask, oropharyngeal airways, face masks, pulse oximeter',
                    'Two large-bore IV cannulas, IV giving sets, crystalloids',
                    'Blood collection tubes, blood request forms, urinary catheter and urine bag',
                    'Magnesium sulphate 50%, calcium gluconate 10%, lignocaine 2%',
                    'Antihypertensives (hydralazine, labetalol, nifedipine)',
                    'Delivery pack, Caesarean section theatre on standby, neonatal resuscitation equipment',
                ],
                'debrief_questions' => [
                    'How did the assessment feel?',
                    'What are the steps of managing an eclamptic seizure and administering magnesium sulphate?',
                    'Which steps did you perform well?',
                    'Which steps need to be improved?',
                ],
                'order_sequence' => 1,
                'is_active' => true,
            ]
        );

        $quiz = ProgramModuleQuiz::firstOrCreate(
            ['program_module_id' => $module->id, 'type' => 'both'],
            [
                'title' => 'Pre-Eclampsia and Eclampsia Knowledge Assessment (Pre-test & Post-test)',
                'description' => 'A 15-question instrument administered before and after the Pre-Eclampsia/Eclampsia simulation drill to measure knowledge gain. The same questions are used for both pre-test and post-test.',
                'pass_mark_percentage' => 70.00,
                'order_sequence' => 1,
                'is_active' => true,
            ]
        );

        $questions = [
            [
                'text' => 'Severe pre-eclampsia is diagnosed in the presence of:',
                'explanation' => 'Severe pre-eclampsia is diagnosed with BP ≥160/110 mmHg, or lower blood pressure accompanied by severe features such as persistent headache, visual disturbances, epigastric pain, or laboratory abnormalities.',
                'options' => [
                    ['text' => 'Blood pressure ≥140/90 mmHg with proteinuria', 'correct' => false],
                    ['text' => 'Blood pressure ≥160/110 mmHg or severe features such as persistent headache, visual disturbances, epigastric pain, or laboratory abnormalities', 'correct' => true],
                    ['text' => 'Proteinuria +++ only', 'correct' => false],
                    ['text' => 'Oedema of the hands and feet', 'correct' => false],
                    ['text' => 'Blood pressure ≥140/90 mmHg without any other feature', 'correct' => false],
                ],
            ],
            [
                'text' => 'Which maternal position is recommended after an eclamptic seizure?',
                'explanation' => 'Left lateral position after the seizure protects the airway and reduces the risk of aspiration.',
                'options' => [
                    ['text' => 'Supine', 'correct' => false],
                    ['text' => 'Trendelenburg', 'correct' => false],
                    ['text' => 'Left lateral position', 'correct' => true],
                    ['text' => 'Sitting upright', 'correct' => false],
                    ['text' => 'Prone', 'correct' => false],
                ],
            ],
            [
                'text' => 'The first-line anticonvulsant for the prevention and treatment of eclamptic seizures is:',
                'explanation' => 'Magnesium sulphate is the first-line anticonvulsant, superior to diazepam and phenytoin for preventing recurrent seizures and reducing maternal mortality.',
                'options' => [
                    ['text' => 'Diazepam', 'correct' => false],
                    ['text' => 'Phenytoin', 'correct' => false],
                    ['text' => 'Phenobarbitone', 'correct' => false],
                    ['text' => 'Magnesium sulphate', 'correct' => true],
                    ['text' => 'Lorazepam', 'correct' => false],
                ],
            ],
            [
                'text' => 'The loading dose of magnesium sulphate for eclampsia is:',
                'explanation' => 'The loading dose is 4 g IV over 5–20 minutes plus 10 g IM (5 g into each buttock with lignocaine), for a total of 14 g.',
                'options' => [
                    ['text' => '4 g IV over 5–20 minutes only', 'correct' => false],
                    ['text' => '4 g IV over 5–20 minutes and 10 g IM (a total of 14 g)', 'correct' => true],
                    ['text' => '1 g IV bolus', 'correct' => false],
                    ['text' => '8 g IM only', 'correct' => false],
                    ['text' => '20 g IV over 1 hour', 'correct' => false],
                ],
            ],
            [
                'text' => 'A woman receiving magnesium sulphate for eclampsia develops loss of patellar reflexes and a respiratory rate of 10 breaths/minute. The immediate action is to:',
                'explanation' => 'This is MgSO4 toxicity (respiratory rate below the 12 breaths/min cutoff, absent reflexes). Stop MgSO4 and give calcium gluconate 10%, 10 mL IV slowly, while supporting respiration.',
                'options' => [
                    ['text' => 'Give an additional loading dose', 'correct' => false],
                    ['text' => 'Stop magnesium sulphate and administer 10 mL of 10% calcium gluconate IV slowly, and support respiration', 'correct' => true],
                    ['text' => 'Increase the maintenance dose', 'correct' => false],
                    ['text' => 'Administer diazepam', 'correct' => false],
                    ['text' => 'Continue the current maintenance dose unchanged', 'correct' => false],
                ],
            ],
            [
                'text' => 'The respiratory rate cutoff below which magnesium sulphate toxicity should be suspected is:',
                'explanation' => 'Per the mentor manual, respiratory depression in MgSO4 toxicity is defined as a respiratory rate below 12 breaths/minute.',
                'options' => [
                    ['text' => '<20 breaths/min', 'correct' => false],
                    ['text' => '<16 breaths/min', 'correct' => false],
                    ['text' => '<12 breaths/min', 'correct' => true],
                    ['text' => '<8 breaths/min', 'correct' => false],
                    ['text' => '<24 breaths/min', 'correct' => false],
                ],
            ],
            [
                'text' => 'The target blood pressure when treating severe hypertension in pregnancy is:',
                'explanation' => 'The goal is roughly 130–150 mmHg systolic / 80–90 mmHg diastolic — treating severe hypertension while avoiding sudden drops below 140/90 mmHg.',
                'options' => [
                    ['text' => '<140/90 mmHg', 'correct' => false],
                    ['text' => '130–150 mmHg systolic; 80–90 mmHg diastolic', 'correct' => true],
                    ['text' => '<150/100 mmHg', 'correct' => false],
                    ['text' => '<160/110 mmHg (avoid sudden drops below 140/90 mmHg)', 'correct' => false],
                    ['text' => 'No specific target; treat until normotensive', 'correct' => false],
                ],
            ],
            [
                'text' => 'During an eclamptic seizure, the midwife should:',
                'explanation' => 'The priority is protecting the woman from injury, maintaining the airway, and positioning her in the left lateral position after the seizure — never restraining her or forcing objects into her mouth.',
                'options' => [
                    ['text' => 'Restrain the woman to prevent injury', 'correct' => false],
                    ['text' => 'Perform immediate Caesarean section', 'correct' => false],
                    ['text' => 'Protect the woman from injury, maintain airway patency, and position her in the left lateral position after the seizure', 'correct' => true],
                    ['text' => 'Administer magnesium sulphate during the seizure itself', 'correct' => false],
                    ['text' => 'Place a padded object in her mouth to prevent tongue biting', 'correct' => false],
                ],
            ],
            [
                'text' => 'Magnesium sulphate is continued for how long after delivery or the last seizure in eclampsia?',
                'explanation' => 'MgSO4 is continued for 24 hours after delivery or the last seizure, whichever occurs later.',
                'options' => [
                    ['text' => '24 hours', 'correct' => true],
                    ['text' => '48 hours', 'correct' => false],
                    ['text' => '12 hours', 'correct' => false],
                    ['text' => 'Until blood pressure normalizes', 'correct' => false],
                    ['text' => '6 hours', 'correct' => false],
                ],
            ],
            [
                'text' => 'A major complication of aggressive fluid administration in severe pre-eclampsia is:',
                'explanation' => 'Endothelial dysfunction and capillary leak in severe pre-eclampsia increase the risk of pulmonary oedema with aggressive IV fluids.',
                'options' => [
                    ['text' => 'Hypertension', 'correct' => false],
                    ['text' => 'Pulmonary oedema', 'correct' => true],
                    ['text' => 'Seizures', 'correct' => false],
                    ['text' => 'HELLP syndrome', 'correct' => false],
                    ['text' => 'Hypoglycaemia', 'correct' => false],
                ],
            ],
            [
                'text' => 'The definitive treatment for pre-eclampsia and eclampsia is:',
                'explanation' => 'Delivery is the only definitive treatment; magnesium sulphate and antihypertensives are supportive/stabilizing measures.',
                'options' => [
                    ['text' => 'Magnesium sulphate', 'correct' => false],
                    ['text' => 'Antihypertensive therapy', 'correct' => false],
                    ['text' => 'Delivery', 'correct' => true],
                    ['text' => 'Fluid restriction', 'correct' => false],
                    ['text' => 'Bed rest until term', 'correct' => false],
                ],
            ],
            [
                'text' => 'If an eclamptic woman develops another seizure 20 minutes after the loading dose of magnesium sulphate, the appropriate next step is to:',
                'explanation' => 'For recurrent seizures after the loading dose, an additional 2 g IV of 20% magnesium sulphate is given.',
                'options' => [
                    ['text' => 'Repeat the full loading dose', 'correct' => false],
                    ['text' => 'Administer an additional 2 g IV of 20% magnesium sulphate', 'correct' => true],
                    ['text' => 'Stop magnesium sulphate immediately', 'correct' => false],
                    ['text' => 'Give diazepam first', 'correct' => false],
                    ['text' => 'Double the maintenance infusion rate', 'correct' => false],
                ],
            ],
            [
                'text' => 'During magnesium sulphate therapy, urine output should be maintained at:',
                'explanation' => 'Urine output ≥30 mL/hour reflects adequate renal function and clearance of magnesium, reducing toxicity risk.',
                'options' => [
                    ['text' => '≥10 mL/hour', 'correct' => false],
                    ['text' => '≥20 mL/hour', 'correct' => false],
                    ['text' => '≥30 mL/hour', 'correct' => true],
                    ['text' => '≥60 mL/hour', 'correct' => false],
                    ['text' => 'No specific minimum', 'correct' => false],
                ],
            ],
            [
                'text' => 'Which antihypertensive medication is recommended for severe hypertension in pregnancy?',
                'explanation' => 'Hydralazine (along with labetalol and oral immediate-release nifedipine) is a recommended first-line antihypertensive for severe hypertension in pregnancy.',
                'options' => [
                    ['text' => 'Atenolol', 'correct' => false],
                    ['text' => 'Hydralazine', 'correct' => true],
                    ['text' => 'Hydrochlorothiazide', 'correct' => false],
                    ['text' => 'Enalapril', 'correct' => false],
                    ['text' => 'Losartan', 'correct' => false],
                ],
            ],
            [
                'text' => 'The single most important principle in the management of severe pre-eclampsia and eclampsia is:',
                'explanation' => 'Maternal stabilization (seizure control with MgSO4, blood pressure control) should precede delivery whenever possible, with delivery following once the mother is stable.',
                'options' => [
                    ['text' => 'Immediate Caesarean section in all cases', 'correct' => false],
                    ['text' => 'Maternal stabilization followed by timely delivery, with magnesium sulphate as the anticonvulsant of choice', 'correct' => true],
                    ['text' => 'Aggressive fluid resuscitation', 'correct' => false],
                    ['text' => 'Delaying delivery until 37 weeks regardless of maternal condition', 'correct' => false],
                    ['text' => 'Withholding magnesium sulphate until after delivery', 'correct' => false],
                ],
            ],
        ];

        $this->seedQuestions($quiz, $questions);
    }

    /**
     * MODULE: Immediate Neonatal Resuscitation
     */
    private function seedNeonatalResuscitation(Program $program): void
    {
        $module = ProgramModule::where('program_id', $program->id)
            ->where('name', 'like', '%Neonatal Resuscitation%')
            ->whereNull('parent_id')
            ->firstOrFail();

        ProgramModuleContent::firstOrCreate(
            [
                'program_module_id' => $module->id,
                'type' => 'introduction',
                'title' => 'Immediate Neonatal Resuscitation — Overview',
            ],
            [
                'content' => "Birth asphyxia remains one of the leading causes of neonatal mortality and long-term neurological disability worldwide, and among the leading causes of death during the first week of life in Kenya. Most babies transition successfully from intrauterine to extrauterine life without assistance; however, approximately 1 in every 20 newborns requires assistance to initiate breathing at birth, and a small proportion require advanced resuscitation.\n\nThe most important intervention in neonatal resuscitation is effective ventilation. Most newborns requiring resuscitation respond to simple measures — warmth, drying, airway positioning, stimulation, and positive pressure ventilation (PPV) using a bag and mask. Chest compressions, medications, and advanced airway management are reserved for the small minority who fail to respond to adequate ventilation.\n\nBecause it is not always possible to predict which babies will require resuscitation, every birth should be attended by at least one healthcare provider trained in newborn resuscitation, with functioning equipment immediately available. Resuscitation for an apnoeic or gasping baby should begin within the first 60 seconds of life — the 'Golden Minute'.",
                'order_sequence' => 1,
                'is_active' => true,
            ]
        );

        if (! ProgramModuleContent::where('program_module_id', $module->id)->where('type', 'case_scenario')->exists()) {
            ProgramModuleContent::firstOrCreate(
                [
                    'program_module_id' => $module->id,
                    'type' => 'case_scenario',
                    'title' => 'Immediate Neonatal Resuscitation — Case Scenario',
                ],
                [
                    'content' => "PATIENT: Male newborn, 39+1 weeks gestation, birth weight 3.2 kg, born to a 26-year-old G2P1 woman after spontaneous vaginal delivery following a prolonged second stage complicated by persistent fetal bradycardia and thick meconium-stained liquor.\n\nAT BIRTH: Tone poor (floppy) | Colour pale with central cyanosis | Breathing: apnoeic | Heart rate 80 bpm | Thick meconium around the mouth | Warm immediately after birth.\n\nThe participant is expected to rapidly recognize birth asphyxia, initiate neonatal resuscitation within the Golden Minute, establish effective positive pressure ventilation, reassess the heart rate appropriately, and progress to chest compressions only if indicated.",
                    'order_sequence' => 1,
                    'is_active' => true,
                ]
            );
        }

        ProgramModuleContent::firstOrCreate(
            [
                'program_module_id' => $module->id,
                'type' => 'case_scenario_progression',
                'title' => 'Immediate Neonatal Resuscitation — Scenario Progression',
            ],
            [
                'content' => "0–1 min (Golden Minute): Baby delivered limp, apnoeic, does not cry; HR 80 bpm; thick meconium around the mouth but no obvious airway obstruction. EXPECTED: Dry thoroughly, remove wet towels, keep warm under the radiant warmer, position in the sniffing position, assess breathing and heart rate, avoid routine suctioning (suction only visible secretions obstructing the airway), and begin PPV with room air (21% oxygen) within the Golden Minute.\n\n1–2 min: Chest movement poor despite bag-mask ventilation; HR remains 70 bpm. EXPECTED: Recognize ineffective ventilation and perform the MRSOPA corrective sequence — Mask adjustment, Reposition airway, Suction if secretions visible, Open mouth, Pressure increase, Alternative airway if necessary — ensuring visible chest rise before continuing.\n\n2–3 min: Following effective ventilation, chest movement improves; HR rises to 110 bpm; occasional spontaneous breaths but weak and irregular. EXPECTED: Recognize improvement; continue assisted ventilation at 30–50 breaths/min until spontaneous breathing becomes regular; continue thermal protection; reassess heart rate and respirations frequently.\n\n3–5 min: Baby begins crying with regular spontaneous respirations; HR 140 bpm; colour and tone improve. EXPECTED: Discontinue PPV once breathing effectively; continue observation under the radiant warmer; assess temperature; monitor SpO2 if available; encourage skin-to-skin contact once stable; initiate breastfeeding.\n\nAlternative progression (if PPV remains ineffective): Despite MRSOPA correction, HR falls to 50 bpm and the baby remains apnoeic. EXPECTED: Begin chest compressions using the two-thumb encircling technique at a 3:1 compression-to-ventilation ratio while ventilating with 100% oxygen if available; continue for 60 seconds before reassessing heart rate; consider advanced airway management and adrenaline if bradycardia persists despite effective ventilation and compressions.",
                'order_sequence' => 2,
                'is_active' => true,
            ]
        );

        ProgramModuleContent::firstOrCreate(
            [
                'program_module_id' => $module->id,
                'type' => 'expected_learning_outcome',
                'title' => 'Immediate Neonatal Resuscitation — Expected Learning Outcome',
            ],
            [
                'content' => 'By the end of this module, the mentee should be able to prepare the resuscitation area before every delivery, perform the rapid initial newborn assessment, initiate resuscitation within the Golden Minute, position the airway correctly and avoid unnecessary suctioning, deliver effective bag-mask ventilation confirmed by visible chest movement, apply the MRSOPA corrective sequence when needed, correctly identify the indication for and perform chest compressions at a 3:1 ratio, recognize indications for adrenaline, and provide thermal protection and post-resuscitation monitoring throughout.',
                'order_sequence' => 1,
                'is_active' => true,
            ]
        );

        $module->update([
            'objectives' => [
                'Prepare the neonatal resuscitation area before every delivery.',
                'Perform the rapid initial assessment immediately after birth.',
                'Recognize newborns requiring resuscitation.',
                'Initiate neonatal resuscitation within the Golden Minute.',
                'Position the airway correctly using the sniffing position.',
                'Demonstrate effective positive pressure ventilation using a bag and mask.',
                'Apply the MRSOPA corrective ventilation sequence when necessary.',
                'Initiate chest compressions correctly when indicated.',
                'Recognize indications for adrenaline (epinephrine) and volume expansion.',
                'Prevent hypothermia throughout resuscitation.',
                'Provide appropriate post-resuscitation monitoring and care.',
                'Demonstrate effective teamwork, communication, and documentation.',
            ],
            'content' => [
                ['label' => 'Drill', 'duration' => '15-20 min'],
                ['label' => 'Debrief', 'duration' => '20-30 min'],
            ],
        ]);

        $rubric = ModuleRubric::firstOrCreate(
            ['program_module_id' => $module->id],
            [
                'title' => 'Immediate Neonatal Resuscitation — Practical Skills Assessment',
                'description' => 'Assesses the mentee\'s ability to prepare for, initiate, and deliver effective newborn resuscitation within the Golden Minute, escalating to compressions and medications only when indicated.',
                'case_scenario' => 'A 39+1-week male newborn (birth weight 3.2 kg) is born by spontaneous vaginal delivery after a prolonged second stage complicated by persistent fetal bradycardia and thick meconium-stained liquor. The baby is limp, apnoeic, pale with central cyanosis, and has a heart rate of 80 bpm at birth.',
                'total_marks' => 21,
                'pass_marks' => (int) round(21 * 0.85),
                'pass_percentage' => round(round(21 * 0.85) / 21 * 100, 2),
                'equipment_supplies' => [
                    'Radiant warmer or resuscitaire, firm resuscitation surface, adequate lighting, visible clock/timer',
                    'Penguin suction device, suction machine, suction catheters (F6, F8, F10)',
                    'Oropharyngeal airways (sizes 000, 00, 0)',
                    'Laryngoscope with blades 0 and 1',
                    'Endotracheal tubes (2.5, 3.0, 3.5, 4.0 mm)',
                    'Neonatal self-inflating bag (200-300 mL) and face masks (sizes 00, 0, 1)',
                    'Oxygen source with flow meter, nasal prongs',
                    'Pulse oximeter with neonatal probe',
                    'Stethoscope, umbilical catheter supplies, syringes (1 mL, 2 mL, 10 mL)',
                    'Normal saline, 10% dextrose, adrenaline (epinephrine)',
                    'Warm towels (minimum two), baby hat, blankets, thermometer',
                ],
                'debrief_questions' => [
                    'How did the assessment feel?',
                    'What are the steps of newborn resuscitation from initial assessment through the Golden Minute?',
                    'Which steps did you perform well?',
                    'Which steps need to be improved?',
                ],
                'order_sequence' => 1,
                'is_active' => true,
            ]
        );

        $quiz = ProgramModuleQuiz::firstOrCreate(
            ['program_module_id' => $module->id, 'type' => 'both'],
            [
                'title' => 'Immediate Neonatal Resuscitation Knowledge Assessment (Pre-test & Post-test)',
                'description' => 'A 15-question instrument administered before and after the Immediate Neonatal Resuscitation simulation drill to measure knowledge gain. The same questions are used for both pre-test and post-test.',
                'pass_mark_percentage' => 70.00,
                'order_sequence' => 1,
                'is_active' => true,
            ]
        );

        $questions = [
            [
                'text' => 'The single most important intervention in neonatal resuscitation is:',
                'explanation' => 'Effective positive pressure ventilation resolves most cases of neonatal bradycardia/apnoea; compressions and medications are reserved for the minority who do not respond to ventilation.',
                'options' => [
                    ['text' => 'Effective positive pressure ventilation', 'correct' => true],
                    ['text' => 'Chest compressions', 'correct' => false],
                    ['text' => 'Administration of adrenaline', 'correct' => false],
                    ['text' => 'Routine suctioning', 'correct' => false],
                    ['text' => 'Immediate intubation', 'correct' => false],
                ],
            ],
            [
                'text' => 'The "Golden Minute" refers to:',
                'explanation' => 'The Golden Minute is the first 60 seconds after birth within which resuscitation should be initiated for babies who are apnoeic or gasping.',
                'options' => [
                    ['text' => 'Time allowed for delayed cord clamping', 'correct' => false],
                    ['text' => 'The first 60 seconds after birth in which resuscitation should be initiated for apnoeic or gasping babies', 'correct' => true],
                    ['text' => 'Time to administer adrenaline', 'correct' => false],
                    ['text' => 'Duration of post-resuscitation observation', 'correct' => false],
                    ['text' => 'Time before chest compressions must start', 'correct' => false],
                ],
            ],
            [
                'text' => 'The three rapid assessment questions asked immediately after birth are:',
                'explanation' => 'The rapid initial assessment asks: Is the baby term? Does the baby have good muscle tone? Is the baby breathing or crying?',
                'options' => [
                    ['text' => 'Is the baby breathing? Is the baby pink? Is the baby warm?', 'correct' => false],
                    ['text' => 'Is the baby term? Does the baby have good muscle tone? Is the baby breathing or crying?', 'correct' => true],
                    ['text' => 'Is the baby crying? Is the heart rate above 100? Is there meconium?', 'correct' => false],
                    ['text' => 'Is the baby term? Is the baby pink? Is the baby active?', 'correct' => false],
                    ['text' => 'Is the baby male or female? Is the baby crying? Is the cord pulsating?', 'correct' => false],
                ],
            ],
            [
                'text' => 'The correct position for the newborn\'s head during resuscitation is:',
                'explanation' => 'The sniffing position — neutral with slight extension — opens the airway best; hyperextension or flexion can obstruct it.',
                'options' => [
                    ['text' => 'Chin to chest', 'correct' => false],
                    ['text' => 'Hyperextended neck', 'correct' => false],
                    ['text' => 'Sniffing position (neutral position with slight extension)', 'correct' => true],
                    ['text' => 'Full neck flexion', 'correct' => false],
                    ['text' => 'Head turned fully to one side', 'correct' => false],
                ],
            ],
            [
                'text' => 'Routine suctioning of the newborn\'s mouth and nose at birth is:',
                'explanation' => 'Routine suctioning is not recommended, even with meconium-stained liquor; suction only if visible secretions are obstructing the airway.',
                'options' => [
                    ['text' => 'Recommended for all babies', 'correct' => false],
                    ['text' => 'Only indicated in meconium-stained liquor', 'correct' => false],
                    ['text' => 'Done with the head in extension', 'correct' => false],
                    ['text' => 'Not recommended', 'correct' => true],
                    ['text' => 'Required before drying the baby', 'correct' => false],
                ],
            ],
            [
                'text' => 'Effective positive pressure ventilation (PPV) is confirmed by:',
                'explanation' => 'Visible chest movement with each breath is the key sign of effective ventilation — not breath sounds, heart rate increase alone, or crying.',
                'options' => [
                    ['text' => 'Audible breath sounds', 'correct' => false],
                    ['text' => 'Visible chest movement with each breath', 'correct' => true],
                    ['text' => 'Increase in heart rate only', 'correct' => false],
                    ['text' => 'Baby crying', 'correct' => false],
                    ['text' => 'Pink lips', 'correct' => false],
                ],
            ],
            [
                'text' => 'When chest movement is inadequate during PPV, the MRSOPA corrective steps should be performed. MRSOPA stands for:',
                'explanation' => 'MRSOPA: Mask adjustment, Reposition airway, Suction if needed, Open mouth, Pressure increase, Alternative airway.',
                'options' => [
                    ['text' => 'Mask, Rate, Suction, Oxygen, Pressure, Airway', 'correct' => false],
                    ['text' => 'Mask adjustment, Reposition airway, Suction if needed, Open mouth, Pressure increase, Alternative airway', 'correct' => true],
                    ['text' => 'Mask, Rotation, Suction, Oxygen, Positive pressure, Advanced airway', 'correct' => false],
                    ['text' => 'Mask, Reposition, Stimulation, Oxygen, Pressure, Adrenaline', 'correct' => false],
                    ['text' => 'Mask, Rate, Stimulate, Observe, Pressure, Airway', 'correct' => false],
                ],
            ],
            [
                'text' => 'Chest compressions in a newborn should be initiated when the heart rate remains below:',
                'explanation' => 'Compressions are indicated when the heart rate is <60 bpm despite at least 30 seconds of effective ventilation.',
                'options' => [
                    ['text' => '60 beats per minute despite at least 30 seconds of effective ventilation', 'correct' => true],
                    ['text' => '80 beats per minute', 'correct' => false],
                    ['text' => '120 beats per minute', 'correct' => false],
                    ['text' => '40 beats per minute', 'correct' => false],
                    ['text' => '100 beats per minute immediately at birth', 'correct' => false],
                ],
            ],
            [
                'text' => 'Which gas should be used when initiating positive pressure ventilation in a term newborn?',
                'explanation' => 'Room air (21% oxygen) is used to initiate PPV in a term newborn; supplemental oxygen is titrated based on response and, where available, pulse oximetry.',
                'options' => [
                    ['text' => '100% oxygen', 'correct' => false],
                    ['text' => '21% oxygen', 'correct' => true],
                    ['text' => 'Nitrous oxide', 'correct' => false],
                    ['text' => 'Carbon dioxide', 'correct' => false],
                    ['text' => '50% oxygen', 'correct' => false],
                ],
            ],
            [
                'text' => 'The correct compression-to-ventilation ratio in neonatal resuscitation is:',
                'explanation' => 'Neonatal resuscitation uses a 3:1 compression-to-ventilation ratio, approximately 90 compressions and 30 ventilations per minute (120 events/minute).',
                'options' => [
                    ['text' => '15:2', 'correct' => false],
                    ['text' => '3:1', 'correct' => true],
                    ['text' => '30:2', 'correct' => false],
                    ['text' => '5:1', 'correct' => false],
                    ['text' => '1:1', 'correct' => false],
                ],
            ],
            [
                'text' => 'The preferred technique for chest compressions in a newborn is:',
                'explanation' => 'The two-thumb encircling hands technique on the lower third of the sternum is preferred over the two-finger technique.',
                'options' => [
                    ['text' => 'Two-finger technique', 'correct' => false],
                    ['text' => 'Palm of one hand', 'correct' => false],
                    ['text' => 'One finger only', 'correct' => false],
                    ['text' => 'Two-thumb encircling hands technique', 'correct' => true],
                    ['text' => 'Fist compression', 'correct' => false],
                ],
            ],
            [
                'text' => 'Adrenaline (epinephrine) during neonatal resuscitation is indicated when:',
                'explanation' => 'Adrenaline is indicated when the heart rate remains below 60 bpm despite effective ventilation and coordinated chest compressions.',
                'options' => [
                    ['text' => 'The baby is preterm', 'correct' => false],
                    ['text' => 'Heart rate is below 100 bpm', 'correct' => false],
                    ['text' => 'Heart rate remains below 60 bpm despite effective ventilation and chest compressions', 'correct' => true],
                    ['text' => 'The baby is gasping', 'correct' => false],
                    ['text' => 'At birth, as a precaution, in all meconium-stained deliveries', 'correct' => false],
                ],
            ],
            [
                'text' => 'The most important intervention to prevent hypothermia in the newborn is:',
                'explanation' => 'Thorough drying and immediate removal of wet towels is the single most effective step to prevent evaporative heat loss immediately after birth.',
                'options' => [
                    ['text' => 'Placing the baby in an incubator immediately', 'correct' => false],
                    ['text' => 'Thorough drying and removing wet towels immediately after birth', 'correct' => true],
                    ['text' => 'Skin-to-skin contact only', 'correct' => false],
                    ['text' => 'Covering the baby with blankets without drying', 'correct' => false],
                    ['text' => 'Delaying all handling until the baby is fully assessed', 'correct' => false],
                ],
            ],
            [
                'text' => 'The single most important principle in neonatal resuscitation is:',
                'explanation' => 'Effective ventilation delivered with minimal interruptions is the cornerstone of neonatal resuscitation and resolves the majority of cases.',
                'options' => [
                    ['text' => 'Early administration of medications', 'correct' => false],
                    ['text' => 'Effective ventilation with minimal interruptions', 'correct' => true],
                    ['text' => 'Immediate chest compressions', 'correct' => false],
                    ['text' => 'Delaying resuscitation until senior review', 'correct' => false],
                    ['text' => 'Routine intubation of all depressed newborns', 'correct' => false],
                ],
            ],
            [
                'text' => 'After successful resuscitation, the newborn should be:',
                'explanation' => 'Once stable, the baby should be placed skin-to-skin with the mother with continued monitoring for deterioration, hypoglycaemia, and hypothermia.',
                'options' => [
                    ['text' => 'Placed skin-to-skin with the mother once stable, with continued monitoring', 'correct' => true],
                    ['text' => 'Kept under the radiant warmer for 2 hours regardless of condition', 'correct' => false],
                    ['text' => 'Transferred to the nursery immediately without monitoring', 'correct' => false],
                    ['text' => 'Given formula milk before breastfeeding', 'correct' => false],
                    ['text' => 'Left unattended once heart rate normalizes', 'correct' => false],
                ],
            ],
        ];

        $this->seedQuestions($quiz, $questions);
    }

    /**
     * @param  array<int, array{text: string, explanation: string, options: array<int, array{text: string, correct: bool}>}>  $questions
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
