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
 * Seeds real content, rubrics and quizzes for "Batch B" of the EmONC
 * Maternal Health program:
 *   - Module 7: Vaginal Breech Delivery
 *   - Module 8: Shoulder Dystocia Delivery
 *   - Module 9: Vaginal Vacuum Assisted Delivery
 *
 * Content is drawn from the CHAI EmONC Mentor Manual (authoritative source
 * for clinical facts) with supplementary procedural detail from the EmONC
 * Mentee Manual where the mentor manual does not elaborate further.
 *
 * Does NOT touch AphModuleContentSeeder, EmoncProgramSeeder, or
 * DatabaseSeeder. Videos for these modules are already seeded by
 * AphModuleContentSeeder::seedModuleVideos().
 */
class EmoncBatchBContentSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $program = Program::where('name', 'Maternal Health (EmONC)')->firstOrFail();

            $this->seedBreechModule($program);
            $this->seedShoulderDystociaModule($program);
            $this->seedVacuumModule($program);
        });

        $this->command->info('EmONC Batch B content (Breech, Shoulder Dystocia, Vacuum) seeded successfully.');
    }

    /* =====================================================================
     * MODULE 7: VAGINAL BREECH DELIVERY
     * ===================================================================*/
    private function seedBreechModule(Program $program): void
    {
        $module = ProgramModule::where('program_id', $program->id)
            ->where('name', 'like', '%Vaginal Breech Delivery%')
            ->whereNull('parent_id')
            ->firstOrFail();

        // ── Introduction ────────────────────────────────────────────────
        ProgramModuleContent::firstOrCreate(
            [
                'program_module_id' => $module->id,
                'type' => 'introduction',
                'title' => 'Vaginal Breech Delivery — Drill Overview',
            ],
            [
                'content' => "Breech presentation occurs when the fetal buttocks or feet present first instead of the head, complicating approximately 3–4% of term pregnancies and carrying increased maternal and neonatal morbidity and mortality. Planned Caesarean section remains the recommended mode of delivery for most term singleton breech presentations because it is associated with improved neonatal outcomes.\n\nHowever, women may present late in labour or in the second stage when Caesarean section is no longer the safest or most feasible option. This simulation strengthens participants' competence in the assessment, preparation and conduct of vaginal breech delivery using evidence-based techniques from the Kenya Ministry of Health Basic Obstetric Protocols, emphasizing correct patient selection, appropriate timing of interventions, a \"hands-off\" approach until assistance is required, and the safe performance of manoeuvres for delivery of the legs, arms and after-coming head.",
                'order_sequence' => 1,
                'is_active' => true,
            ]
        );

        ProgramModuleContent::firstOrCreate(
            [
                'program_module_id' => $module->id,
                'type' => 'introduction',
                'title' => 'Universal Principles — Communication and Safety',
            ],
            [
                'content' => "As with all EmONC simulation drills, participants should demonstrate respectful maternity care throughout: introduce yourself, explain each step, and obtain informed verbal consent before any intervention. Call for help early and allocate roles clearly, using closed-loop communication and SBAR handover if the case needs escalation to theatre. Think aloud so the mentor can follow your clinical reasoning.\n\nFor vaginal breech delivery specifically, the guiding safety principle is to remain hands-off until intervention is genuinely required, to hold the fetus by the bony pelvis rather than the abdomen or soft tissues, and to always have the neonatal resuscitation team and equipment ready before the after-coming head delivers.",
                'order_sequence' => 2,
                'is_active' => true,
            ]
        );

        // ── Case scenario (only if not already present) ────────────────
        if (! ProgramModuleContent::where('program_module_id', $module->id)->where('type', 'case_scenario')->exists()) {
            ProgramModuleContent::firstOrCreate(
                [
                    'program_module_id' => $module->id,
                    'type' => 'case_scenario',
                    'title' => 'Vaginal Breech Delivery — Case Scenario',
                ],
                [
                    'content' => "PATIENT: Jane, 24 years, G2P1 at 39 weeks.\n\nBACKGROUND: Jane arrives at the maternity unit in advanced labour after labouring at home for approximately 8 hours. On examination she is fully dilated (10 cm) with a frank breech presentation. The membranes have ruptured spontaneously with clear liquor, and the fetal buttocks are visible at the introitus during contractions.\n\nINITIAL ASSESSMENT: BP 118/74 mmHg | Pulse 86 bpm | RR 20 | Temp 36.8°C | FHR 140 bpm with early decelerations | Cervix fully dilated (10 cm) | Presentation: frank breech | Membranes ruptured, clear liquor | Station: breech visible at vulva during contractions.\n\nThe participant is expected to assess the woman, determine that vaginal breech delivery is appropriate, prepare the woman and team, and conduct a safe assisted vaginal breech delivery, recognizing when intervention (assisted leg delivery, Lovset's manoeuvre, Mauriceau-Smellie-Veit manoeuvre) is required and safely delivering the after-coming head.",
                    'order_sequence' => 3,
                    'is_active' => true,
                ]
            );
        }

        // ── Case scenario progression ───────────────────────────────────
        ProgramModuleContent::firstOrCreate(
            [
                'program_module_id' => $module->id,
                'type' => 'case_scenario_progression',
                'title' => 'Vaginal Breech Delivery — Scenario Progression',
            ],
            [
                'content' => "SCENARIO PROGRESSION:\n• 0–2 min: Strong urge to push; breech confirmed, buttocks visible during contractions, FHR 140 bpm. Explain the diagnosis and obtain verbal consent. Call for help. Confirm full cervical dilatation and eligibility for vaginal breech delivery. Empty the bladder. Prepare neonatal resuscitation equipment. Position the woman at the edge of the bed; encourage pushing only with contractions. Adopt a hands-off approach until assistance becomes necessary.\n• 2–4 min: Buttocks deliver spontaneously followed by the trunk to the level of the umbilicus; legs remain extended and fail to deliver spontaneously. Continue the hands-off approach until intervention is indicated; deliver the legs one at a time by flexing/abducting at the knee and gently grasping the ankle. Avoid traction on the fetus. Consider episiotomy only if clinically indicated.\n• 4–6 min: Trunk delivers to the level of the scapulae; one arm delivers spontaneously but the second remains extended above the head. FHR remains reassuring. Hold the fetus by the bony pelvis, recognize the extended arm, and perform Lovset's manoeuvre to deliver the remaining arm. Avoid pulling or excessive rotation.\n• 6–8 min: Both arms delivered; head remains in the birth canal, hairline visible but not delivering spontaneously. Allow the fetus to hang until the hairline is visible, then perform the Mauriceau-Smellie-Veit (MSV) manoeuvre — fingers on the fetal maxilla (not inside the mouth) to maintain flexion while supporting the shoulders and occiput; request suprapubic pressure if required. Aim to complete delivery within 5 minutes of buttock delivery.\n• 8–10 min: Baby delivered with initially poor tone and weak respiratory effort. Clamp and cut the cord; transfer the newborn immediately for assessment/resuscitation. Assess the mother for bleeding, uterine tone and genital tract trauma. Continue routine third-stage management and document the delivery.",
                'order_sequence' => 4,
                'is_active' => true,
            ]
        );

        // ── Expected learning outcome ────────────────────────────────────
        ProgramModuleContent::firstOrCreate(
            [
                'program_module_id' => $module->id,
                'type' => 'expected_learning_outcome',
                'title' => 'Vaginal Breech Delivery — Expected Learning Outcome',
            ],
            [
                'content' => "By the end of this module, the mentee should be able to confirm eligibility for vaginal breech delivery, maintain a hands-off approach until intervention is required, correctly perform assisted leg delivery, Lovset's manoeuvre for extended or nuchal arms, and the Mauriceau-Smellie-Veit manoeuvre for the after-coming head, while completing delivery within approximately 5 minutes of buttock delivery, preparing for neonatal resuscitation, and demonstrating respectful maternity care and effective teamwork throughout.",
                'order_sequence' => 5,
                'is_active' => true,
            ]
        );

        // ── Module objectives & content workplan ─────────────────────────
        $module->update([
            'objectives' => [
                'Recognize women eligible for vaginal breech delivery.',
                'Explain the diagnosis and obtain informed consent.',
                'Prepare the woman, newborn and delivery team appropriately.',
                'Demonstrate the principles of assisted vaginal breech delivery.',
                'Perform assisted delivery of the legs when indicated.',
                "Perform Lovset's manoeuvre for extended arms.",
                'Perform the Mauriceau-Smellie-Veit (MSV) manoeuvre for delivery of the after-coming head.',
                'Recognize complications requiring immediate intervention.',
                'Prepare for neonatal resuscitation.',
                'Demonstrate effective teamwork, communication and respectful maternity care.',
            ],
            'content' => [
                ['label' => 'Drill', 'duration' => '10-12 min'],
                ['label' => 'Debrief', 'duration' => '20-25 min'],
            ],
        ]);

        // ── Rubric ────────────────────────────────────────────────────────
        $rubric = ModuleRubric::firstOrCreate(
            ['program_module_id' => $module->id],
            [
                'title' => 'Vaginal Breech Delivery — Practical Skills Assessment',
                'description' => "Hands-on practical rubric assessing safe patient selection, a hands-off approach until intervention is required, and correct performance of Lovset's and Mauriceau-Smellie-Veit manoeuvres during vaginal breech delivery.",
                'case_scenario' => "PATIENT: Jane, 24 years, G2P1 at 39 weeks gestation.\n\nSCENARIO: Jane arrives in advanced labour after labouring at home. On examination she is fully dilated with a frank breech presentation; the membranes have ruptured with clear liquor and the fetal buttocks are visible at the introitus during contractions. Fetal heart rate is 140 bpm with early decelerations.\n\nTASK: Assess Jane, determine that vaginal breech delivery is appropriate, prepare the woman and team, and conduct a safe assisted vaginal breech delivery, giving a running commentary of each step.",
                'total_marks' => 21,
                'pass_marks' => (int) round(21 * 0.85),
                'pass_percentage' => round((round(21 * 0.85) / 21) * 100, 2),
                'equipment_supplies' => [
                    'Labour bed with adjustable foot section',
                    'Sterile delivery pack, sterile gloves, cord clamps, sterile scissors, gauze',
                    'Neonatal towels, warm blankets, sterile lubricant',
                    'Episiotomy set, local anaesthetic, suturing set',
                    'Oxygen source, IV cannulas and fluids',
                    'PPH emergency tray',
                    'Neonatal resuscitation trolley (radiant warmer, bag and mask, suction)',
                    'Personal protective equipment: sterile gloves, aprons, face masks, hand hygiene supplies',
                ],
                'debrief_questions' => [
                    'How did the assessment feel?',
                    "What are the steps of vaginal breech delivery (Lovset's and Mauriceau-Smellie-Veit manoeuvres)?",
                    'Which steps did you perform well?',
                    'Which steps need to be improved?',
                ],
                'order_sequence' => 1,
                'is_active' => true,
            ]
        );

        // ── Quiz ──────────────────────────────────────────────────────────
        $quiz = ProgramModuleQuiz::firstOrCreate(
            ['program_module_id' => $module->id, 'type' => 'both'],
            [
                'title' => 'Vaginal Breech Delivery Knowledge Assessment (Pre-test & Post-test)',
                'description' => 'A 15-question instrument administered before and after the vaginal breech delivery simulation drill to measure knowledge gain. The same questions are used for both the pre-test and post-test.',
                'pass_mark_percentage' => 70.00,
                'order_sequence' => 1,
                'is_active' => true,
            ]
        );

        $this->seedQuestions($quiz, $this->breechQuestions());

        $this->command->line("  ✓ Vaginal Breech Delivery: content, rubric ({$rubric->total_marks} items) and 15 questions seeded.");
    }

    /* =====================================================================
     * MODULE 8: SHOULDER DYSTOCIA DELIVERY
     * ===================================================================*/
    private function seedShoulderDystociaModule(Program $program): void
    {
        $module = ProgramModule::where('program_id', $program->id)
            ->where('name', 'like', '%Shoulder Dystocia%')
            ->whereNull('parent_id')
            ->firstOrFail();

        // ── Introduction ────────────────────────────────────────────────
        ProgramModuleContent::firstOrCreate(
            [
                'program_module_id' => $module->id,
                'type' => 'introduction',
                'title' => 'Shoulder Dystocia — Drill Overview',
            ],
            [
                'content' => "Shoulder dystocia is an unpredictable obstetric emergency in which the fetal shoulders fail to deliver spontaneously after delivery of the head, because the anterior shoulder becomes impacted behind the maternal symphysis pubis, the posterior shoulder becomes impacted against the sacral promontory, or both. Although uncommon, it is associated with significant maternal and neonatal morbidity and requires immediate, skilled intervention.\n\nShoulder dystocia cannot be reliably predicted or prevented — approximately 50% of cases occur in infants of normal birth weight, and many affected women have no identifiable risk factors. This simulation strengthens participants' competence in the rapid recognition and management of shoulder dystocia using the HELPERR approach and other evidence-based manoeuvres from the Kenya Ministry of Health Basic Obstetric Protocols, emphasizing teamwork, communication, timely progression through manoeuvres, and avoidance of excessive traction that may result in fetal injury.",
                'order_sequence' => 1,
                'is_active' => true,
            ]
        );

        ProgramModuleContent::firstOrCreate(
            [
                'program_module_id' => $module->id,
                'type' => 'introduction',
                'title' => 'Universal Principles — Communication and Safety',
            ],
            [
                'content' => "As with all EmONC emergencies, respectful maternity care, calm leadership and closed-loop communication are essential: explain the emergency briefly to the mother and birth companion, call for help immediately, and delegate roles clearly. Record the time the fetal head delivers, since this anchors the timing of every subsequent manoeuvre.\n\nThe two critical safety rules for shoulder dystocia are to never apply fundal pressure — this worsens impaction and risks uterine rupture — and to avoid excessive or prolonged traction on the fetal head, which risks brachial plexus injury. Each manoeuvre should generally be attempted for no more than 30 seconds before progressing systematically to the next step in the HELPERR sequence.",
                'order_sequence' => 2,
                'is_active' => true,
            ]
        );

        // ── Case scenario (only if not already present) ────────────────
        if (! ProgramModuleContent::where('program_module_id', $module->id)->where('type', 'case_scenario')->exists()) {
            ProgramModuleContent::firstOrCreate(
                [
                    'program_module_id' => $module->id,
                    'type' => 'case_scenario',
                    'title' => 'Shoulder Dystocia — Case Scenario',
                ],
                [
                    'content' => "PATIENT: Sarah, 32 years, G4P3 at 40 weeks gestation, estimated fetal weight 4.1 kg.\n\nBACKGROUND: Sarah has had an uncomplicated pregnancy and has progressed to the second stage of labour after three previous spontaneous vaginal births. She has been pushing effectively and the fetal head delivers spontaneously. Immediately afterward, the baby fails to deliver with the next contraction: the head retracts tightly against the perineum (turtle sign) and restitution does not occur.\n\nINITIAL ASSESSMENT: BP 122/76 mmHg | Pulse 92 bpm | RR 20 | Temp 36.8°C | FHR before head delivery 140 bpm with variable decelerations | Liquor clear | Head delivered; anterior shoulder impacted behind the symphysis pubis.\n\nThe participant is expected to recognize shoulder dystocia promptly and perform the appropriate sequence of manoeuvres (HELPERR) to achieve a safe vaginal delivery while minimizing maternal and neonatal complications.",
                    'order_sequence' => 3,
                    'is_active' => true,
                ]
            );
        }

        // ── Case scenario progression ───────────────────────────────────
        ProgramModuleContent::firstOrCreate(
            [
                'program_module_id' => $module->id,
                'type' => 'case_scenario_progression',
                'title' => 'Shoulder Dystocia — Scenario Progression',
            ],
            [
                'content' => "SCENARIO PROGRESSION:\n• 0–2 min: Head delivers then retracts tightly against the perineum (turtle sign); restitution fails and gentle downward traction fails to deliver the shoulders. Recognize shoulder dystocia immediately, announce the diagnosis, call for help, ask the woman to stop pushing, explain briefly to the mother and companion, record the time of head delivery, prepare the neonatal resuscitation team. Do not pull on the head or apply fundal pressure.\n• 2–3 min: Anterior shoulder remains impacted. Place the woman in the McRoberts position (flex and abduct both hips, buttocks at the edge of the bed). Apply suprapubic pressure directed downward and laterally toward the baby's chest while maintaining gentle axial traction on the head. Reassess after approximately 30 seconds.\n• 3–4 min: Delivery still unsuccessful. Assess whether an episiotomy is required to facilitate internal manoeuvres; perform if indicated. Proceed to internal rotational manoeuvres (Rubin II, Wood's Screw or Reverse Wood's Screw) by inserting fingers through the sacral hollow and rotating the shoulders into the oblique diameter. Avoid excessive traction.\n• 4–5 min: Shoulders remain impacted after rotational manoeuvres. Deliver the posterior arm — locate the posterior elbow, flex the arm across the fetal chest, and gently sweep the forearm and hand over the face and out. Use an axillary sling if the posterior arm cannot be reached directly.\n• 5–6 min: Delivery still unsuccessful. Roll the woman into the all-fours (Gaskin) position while maintaining support of the fetal head; repeat appropriate internal manoeuvres. Aim to complete delivery within 5 minutes of head delivery.\n• 6–8 min: Shoulders release and the baby delivers floppy and not breathing adequately. Clamp and cut the cord; transfer the newborn immediately for resuscitation. Assess the mother for postpartum haemorrhage, genital tract trauma and uterine tone; initiate AMTSL. Document the sequence and timing of all manoeuvres performed.",
                'order_sequence' => 4,
                'is_active' => true,
            ]
        );

        // ── Expected learning outcome ────────────────────────────────────
        ProgramModuleContent::firstOrCreate(
            [
                'program_module_id' => $module->id,
                'type' => 'expected_learning_outcome',
                'title' => 'Shoulder Dystocia — Expected Learning Outcome',
            ],
            [
                'content' => 'By the end of this module, the mentee should be able to recognize the clinical features of shoulder dystocia immediately after delivery of the head (turtle sign, failed restitution), call for help and lead a structured HELPERR sequence — McRoberts manoeuvre, suprapubic pressure, internal rotational manoeuvres and delivery of the posterior arm — while avoiding fundal pressure and excessive traction, recognizing when to progress to salvage manoeuvres, and preparing for neonatal resuscitation and post-delivery maternal assessment.',
                'order_sequence' => 5,
                'is_active' => true,
            ]
        );

        // ── Module objectives & content workplan ─────────────────────────
        $module->update([
            'objectives' => [
                'Recognize the clinical features of shoulder dystocia promptly.',
                'Call for help immediately and organize the emergency response team.',
                'Explain the emergency to the mother and birth companion.',
                'Perform the HELPERR sequence systematically.',
                'Demonstrate correct McRoberts manoeuvre and suprapubic pressure.',
                'Perform internal rotational manoeuvres when indicated.',
                'Deliver the posterior arm safely.',
                'Recognize when to progress to advanced or salvage manoeuvres.',
                'Prepare for neonatal resuscitation immediately after birth.',
                'Demonstrate effective teamwork, communication and respectful maternity care throughout the emergency.',
            ],
            'content' => [
                ['label' => 'Drill', 'duration' => '10-12 min'],
                ['label' => 'Debrief', 'duration' => '20-25 min'],
            ],
        ]);

        // ── Rubric ────────────────────────────────────────────────────────
        $rubric = ModuleRubric::firstOrCreate(
            ['program_module_id' => $module->id],
            [
                'title' => 'Shoulder Dystocia Delivery — Practical Skills Assessment',
                'description' => 'Hands-on practical rubric assessing prompt recognition of shoulder dystocia and correct, systematic progression through the HELPERR sequence of manoeuvres.',
                'case_scenario' => "PATIENT: Sarah, 32 years, G4P3 at 40 weeks gestation, estimated fetal weight 4.1 kg.\n\nSCENARIO: Sarah has progressed normally through labour and the fetal head delivers spontaneously. Immediately afterward, the head retracts tightly against the perineum (turtle sign) and restitution fails; gentle downward traction does not deliver the shoulders, which are impacted behind the symphysis pubis.\n\nTASK: Recognize shoulder dystocia and lead the emergency team through the HELPERR sequence to safely deliver the baby, giving a running commentary of each step.",
                'total_marks' => 21,
                'pass_marks' => (int) round(21 * 0.85),
                'pass_percentage' => round((round(21 * 0.85) / 21) * 100, 2),
                'equipment_supplies' => [
                    'Labour bed with adjustable foot section',
                    'Sterile delivery pack, sterile gloves, gauze, cord clamps, scissors',
                    'Episiotomy set, local anaesthetic, suturing set',
                    'Oxygen source, IV cannulas and fluids',
                    'PPH emergency tray',
                    'Neonatal resuscitation trolley (radiant warmer, bag and mask, suction, warm towels)',
                    'Personal protective equipment: sterile gloves, aprons, face masks, hand hygiene supplies',
                ],
                'debrief_questions' => [
                    'How did the assessment feel?',
                    'What are the steps of the HELPERR sequence for shoulder dystocia?',
                    'Which steps did you perform well?',
                    'Which steps need to be improved?',
                ],
                'order_sequence' => 1,
                'is_active' => true,
            ]
        );

        // ── Quiz ──────────────────────────────────────────────────────────
        $quiz = ProgramModuleQuiz::firstOrCreate(
            ['program_module_id' => $module->id, 'type' => 'both'],
            [
                'title' => 'Shoulder Dystocia Knowledge Assessment (Pre-test & Post-test)',
                'description' => 'A 15-question instrument administered before and after the shoulder dystocia simulation drill to measure knowledge gain. The same questions are used for both the pre-test and post-test.',
                'pass_mark_percentage' => 70.00,
                'order_sequence' => 1,
                'is_active' => true,
            ]
        );

        $this->seedQuestions($quiz, $this->shoulderDystociaQuestions());

        $this->command->line("  ✓ Shoulder Dystocia Delivery: content, rubric ({$rubric->total_marks} items) and 15 questions seeded.");
    }

    /* =====================================================================
     * MODULE 9: VAGINAL VACUUM ASSISTED DELIVERY
     * ===================================================================*/
    private function seedVacuumModule(Program $program): void
    {
        $module = ProgramModule::where('program_id', $program->id)
            ->where('name', 'like', '%Vacuum Assisted Delivery%')
            ->whereNull('parent_id')
            ->firstOrFail();

        // ── Introduction ────────────────────────────────────────────────
        ProgramModuleContent::firstOrCreate(
            [
                'program_module_id' => $module->id,
                'type' => 'introduction',
                'title' => 'Vacuum-Assisted Vaginal Birth — Drill Overview',
            ],
            [
                'content' => "Vacuum-assisted vaginal birth (VAVB), also known as vacuum extraction, is an important component of Comprehensive Emergency Obstetric and Newborn Care (CEmONC). It is a safe and effective method of expediting vaginal birth when indicated, reducing maternal and neonatal morbidity associated with a prolonged second stage of labour or fetal compromise. Compared with Caesarean section performed during the second stage, vacuum-assisted delivery is associated with fewer surgical complications, shorter recovery time and lower risk of maternal infection when appropriately selected and correctly performed.\n\nSuccessful vacuum-assisted vaginal birth requires careful patient selection, confirmation that all prerequisites have been met, correct application of the vacuum cup, appropriate traction synchronized with uterine contractions, timely abandonment of the procedure when failure criteria are met, and a clear backup plan should extraction not achieve delivery.",
                'order_sequence' => 1,
                'is_active' => true,
            ]
        );

        ProgramModuleContent::firstOrCreate(
            [
                'program_module_id' => $module->id,
                'type' => 'introduction',
                'title' => 'Universal Principles — Communication and Safety',
            ],
            [
                'content' => "As with all EmONC procedures, respectful maternity care is central: explain the procedure fully and obtain informed verbal consent before proceeding, call for assistance early, and think aloud so the mentor can follow your clinical reasoning. Work systematically through the prerequisites for vacuum extraction before applying the cup, apply traction only during contractions and along the pelvic axis, and never use the cup to actively rotate the fetal head.\n\nRecognize the objective failure criteria early and be prepared to abandon the procedure and transition promptly to Caesarean section — patient safety, not procedural persistence, is the priority.",
                'order_sequence' => 2,
                'is_active' => true,
            ]
        );

        // ── Case scenario (only if not already present) ────────────────
        if (! ProgramModuleContent::where('program_module_id', $module->id)->where('type', 'case_scenario')->exists()) {
            ProgramModuleContent::firstOrCreate(
                [
                    'program_module_id' => $module->id,
                    'type' => 'case_scenario',
                    'title' => 'Vacuum-Assisted Vaginal Birth — Case Scenario',
                ],
                [
                    'content' => "PATIENT: Mary, 27 years, G2P1 at 39+5 weeks gestation.\n\nBACKGROUND: Mary has had an uncomplicated pregnancy with spontaneous onset of labour. She has progressed to the second stage and has been pushing effectively for nearly two hours but is becoming exhausted. Fetal heart rate monitoring demonstrates persistent fetal bradycardia requiring expedited delivery.\n\nINITIAL ASSESSMENT: BP 118/74 mmHg | Pulse 90 bpm | RR 20 | Temp 36.8°C | FHR 100–105 bpm with variable decelerations | Cervix fully dilated (10 cm) | Membranes ruptured, clear liquor | Presentation cephalic, occipito-anterior | Station +2 (1/5 palpable abdominally) | Estimated fetal weight 3.2 kg | Bladder full, requires catheterization.\n\nThe participant is expected to recognize that vacuum-assisted vaginal birth is appropriate, confirm all prerequisites and exclude contraindications, safely perform the procedure, recognize successful or failed extraction, and provide immediate post-delivery care to mother and newborn.",
                    'order_sequence' => 3,
                    'is_active' => true,
                ]
            );
        }

        // ── Case scenario progression ───────────────────────────────────
        ProgramModuleContent::firstOrCreate(
            [
                'program_module_id' => $module->id,
                'type' => 'case_scenario_progression',
                'title' => 'Vacuum-Assisted Vaginal Birth — Scenario Progression',
            ],
            [
                'content' => "SCENARIO PROGRESSION:\n• 0–2 min: Woman exhausted after two hours in the second stage; FHR 100–105 bpm. VE confirms full dilatation, ruptured membranes, vertex OA position, engaged head (+2 station), no evidence of cephalopelvic disproportion. Recognize the indication for vacuum-assisted vaginal birth, explain the procedure and obtain informed verbal consent, call for assistance, confirm all prerequisites and exclude contraindications, empty the bladder, and prepare neonatal resuscitation equipment.\n• 2–4 min: Vacuum equipment available; fetal head position needs confirmation before cup application. Identify the sagittal suture and fontanelles, confirm the OA position, select the largest appropriate cup, and position it 2–3 cm anterior to the posterior fontanelle (the flexion point). Ensure no maternal tissue is trapped beneath the rim.\n• 4–6 min: Cup positioned correctly. Create an initial vacuum of 0.2 kg/cm², recheck cup placement, then increase gradually to 0.8 kg/cm². Confirm suction is maintained and the cup remains secure; ask the mother to indicate the next contraction.\n• 6–8 min: Contractions continue and the woman is able to push. Apply gentle traction only during contractions, following the pelvic axis in a J-shaped curve. Place one finger beside the cup to assess descent and detect slippage. Reassess FHR and cup application between contractions. Consider episiotomy only if clinically indicated.\n• 8–10 min: After two effective pulls the head begins to crown; with the third pull the head delivers. Release the vacuum immediately, remove the cup gently, complete delivery in the normal manner, and initiate AMTSL. Assess the mother for genital tract trauma and postpartum haemorrhage; assess the newborn for scalp injuries, cephalohematoma, sub-galeal haemorrhage and need for resuscitation. Document the procedure.",
                'order_sequence' => 4,
                'is_active' => true,
            ]
        );

        // ── Expected learning outcome ────────────────────────────────────
        ProgramModuleContent::firstOrCreate(
            [
                'program_module_id' => $module->id,
                'type' => 'expected_learning_outcome',
                'title' => 'Vacuum-Assisted Vaginal Birth — Expected Learning Outcome',
            ],
            [
                'content' => 'By the end of this module, the mentee should be able to identify the indications and prerequisites for vacuum-assisted vaginal birth, exclude contraindications, correctly determine fetal head position and locate the flexion point, apply the vacuum cup and pressure correctly, apply traction only during contractions along the pelvic axis, recognize the objective failure criteria for abandonment, and safely transition to the backup plan (Caesarean section) when indicated, while assessing mother and newborn for complications and maintaining respectful maternity care and teamwork throughout.',
                'order_sequence' => 5,
                'is_active' => true,
            ]
        );

        // ── Module objectives & content workplan ─────────────────────────
        $module->update([
            'objectives' => [
                'Identify the indications for vacuum-assisted vaginal birth.',
                'Confirm that all prerequisites have been met before the procedure.',
                'Recognize contraindications to vacuum extraction.',
                'Obtain informed consent and prepare the woman appropriately.',
                'Correctly determine fetal head position and identify the flexion point.',
                'Demonstrate correct application of the vacuum cup.',
                'Apply appropriate vacuum pressure and traction.',
                'Recognize criteria for failed vacuum extraction.',
                'Safely abandon the procedure when indicated and initiate the backup plan.',
                'Assess the mother and newborn for complications following delivery.',
                'Demonstrate effective teamwork, communication and respectful maternity care.',
            ],
            'content' => [
                ['label' => 'Drill', 'duration' => '12-15 min'],
                ['label' => 'Debrief', 'duration' => '20-25 min'],
            ],
        ]);

        // ── Rubric ────────────────────────────────────────────────────────
        $rubric = ModuleRubric::firstOrCreate(
            ['program_module_id' => $module->id],
            [
                'title' => 'Vaginal Vacuum Assisted Delivery — Practical Skills Assessment',
                'description' => 'Hands-on practical rubric assessing correct patient selection, cup application at the flexion point, traction technique, and timely recognition of failure criteria during vacuum-assisted vaginal birth.',
                'case_scenario' => "PATIENT: Mary, 27 years, G2P1 at 39+5 weeks gestation.\n\nSCENARIO: Mary has been pushing effectively for nearly two hours in the second stage and is becoming exhausted. The fetal heart rate has fallen to 100–105 bpm. Vaginal examination confirms full cervical dilatation, ruptured membranes, occipito-anterior position, and the fetal head at +2 station (1/5 palpable abdominally), with no evidence of cephalopelvic disproportion.\n\nTASK: Confirm the indication and prerequisites for vacuum-assisted vaginal birth, correctly perform the procedure, and recognize successful or failed extraction, giving a running commentary of each step.",
                'total_marks' => 23,
                'pass_marks' => (int) round(23 * 0.85),
                'pass_percentage' => round((round(23 * 0.85) / 23) * 100, 2),
                'equipment_supplies' => [
                    'Blood pressure machine, stethoscope, thermometer, pulse oximeter, Doppler/fetoscope',
                    'Functional vacuum extractor (manual or electric), assorted vacuum cups, tubing, suction device, pressure gauge',
                    'Sterile delivery pack, sterile gloves, sterile drapes, cord clamps, scissors, gauze',
                    'Episiotomy set, local anaesthetic, suturing set',
                    'Oxygen source, IV cannulas and fluids, emergency drug tray, PPH tray',
                    'Neonatal resuscitation trolley (radiant warmer, bag and mask, suction, warm towels)',
                    'Personal protective equipment: sterile gloves, aprons, face masks, hand hygiene supplies',
                ],
                'debrief_questions' => [
                    'How did the assessment feel?',
                    'What are the steps of vacuum-assisted vaginal birth?',
                    'Which steps did you perform well?',
                    'Which steps need to be improved?',
                ],
                'order_sequence' => 1,
                'is_active' => true,
            ]
        );

        // ── Quiz ──────────────────────────────────────────────────────────
        $quiz = ProgramModuleQuiz::firstOrCreate(
            ['program_module_id' => $module->id, 'type' => 'both'],
            [
                'title' => 'Vaginal Vacuum Assisted Delivery Knowledge Assessment (Pre-test & Post-test)',
                'description' => 'A 15-question instrument administered before and after the vacuum-assisted vaginal birth simulation drill to measure knowledge gain. The same questions are used for both the pre-test and post-test.',
                'pass_mark_percentage' => 70.00,
                'order_sequence' => 1,
                'is_active' => true,
            ]
        );

        $this->seedQuestions($quiz, $this->vacuumQuestions());

        $this->command->line("  ✓ Vaginal Vacuum Assisted Delivery: content, rubric ({$rubric->total_marks} items) and 15 questions seeded.");
    }

    /* =====================================================================
     * SHARED QUESTION-SEEDING HELPER (mirrors AphModuleContentSeeder)
     * ===================================================================*/
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

    /* =====================================================================
     * QUESTION BANKS
     * ===================================================================*/
    private function breechQuestions(): array
    {
        return [
            [
                'text' => 'What is the preferred mode of delivery for most term singleton breech presentations diagnosed before or in early labour?',
                'explanation' => 'Planned Caesarean section at 39 weeks is associated with improved neonatal outcomes and remains the recommended mode of delivery for most term singleton breech presentations diagnosed antenatally or in the first stage.',
                'options' => [
                    ['text' => 'Assisted vaginal breech delivery', 'correct' => false],
                    ['text' => 'Planned Caesarean section at 39 weeks gestation', 'correct' => true],
                    ['text' => 'External cephalic version performed during labour', 'correct' => false],
                    ['text' => 'Expectant management awaiting spontaneous version', 'correct' => false],
                    ['text' => 'Induction of labour at 37 weeks followed by vaginal breech delivery', 'correct' => false],
                ],
            ],
            [
                'text' => 'A woman with a second-stage frank breech presentation is being assessed for eligibility for vaginal breech delivery. Which finding would exclude her from safe vaginal breech delivery?',
                'explanation' => 'Eligibility criteria include second stage of labour, full cervical dilatation, frank/complete breech, adequate pelvis, estimated fetal weight below approximately 3.5 kg, no previous Caesarean section, and good descent. An estimated fetal weight of 4.2 kg exceeds the safe threshold and is a contraindication.',
                'options' => [
                    ['text' => 'Fully dilated cervix', 'correct' => false],
                    ['text' => 'Estimated fetal weight of 4.2 kg', 'correct' => true],
                    ['text' => 'Frank breech presentation', 'correct' => false],
                    ['text' => 'No previous Caesarean section', 'correct' => false],
                    ['text' => 'Good descent of the breech', 'correct' => false],
                ],
            ],
            [
                'text' => 'Which type of breech presentation is generally considered suitable for vaginal delivery?',
                'explanation' => 'Frank or complete breech is generally suitable for vaginal delivery. Footling breech carries a higher risk of cord prolapse and is not favoured; transverse lie and compound presentation are not breech presentations amenable to vaginal breech delivery.',
                'options' => [
                    ['text' => 'Footling breech', 'correct' => false],
                    ['text' => 'Transverse lie', 'correct' => false],
                    ['text' => 'Frank/complete breech', 'correct' => true],
                    ['text' => 'Compound presentation', 'correct' => false],
                    ['text' => 'Oblique lie', 'correct' => false],
                ],
            ],
            [
                'text' => 'A woman presents in the second stage of labour with a confirmed frank breech and a strong urge to push. What is the correct sequence of initial actions?',
                'explanation' => 'The first 0–2 minutes of the drill require explaining the diagnosis and obtaining verbal consent, calling for help, confirming full dilatation and eligibility, emptying the bladder, and preparing neonatal resuscitation equipment before encouraging pushing only with contractions.',
                'options' => [
                    ['text' => 'Immediately begin traction on the breech to expedite delivery', 'correct' => false],
                    ['text' => 'Explain the diagnosis, obtain verbal consent, call for help, confirm full dilatation and eligibility, empty the bladder, and prepare neonatal resuscitation', 'correct' => true],
                    ['text' => 'Perform an episiotomy immediately to prevent perineal trauma', 'correct' => false],
                    ['text' => 'Begin continuous suprapubic pressure from the outset', 'correct' => false],
                    ['text' => "Perform Lovset's manoeuvre as a precaution before spontaneous descent", 'correct' => false],
                ],
            ],
            [
                'text' => 'During spontaneous descent of the breech, what is the recommended approach for the birth attendant?',
                'explanation' => 'A hands-off approach is recommended until intervention is genuinely necessary; premature traction may extend the fetal head or arms and increase the risk of trauma and asphyxia.',
                'options' => [
                    ['text' => 'Hands-off management until intervention is necessary', 'correct' => true],
                    ['text' => 'Continuous gentle traction on the fetal trunk', 'correct' => false],
                    ['text' => 'Early fundal pressure to expedite delivery', 'correct' => false],
                    ['text' => 'Routine early episiotomy', 'correct' => false],
                    ['text' => 'Immediate application of forceps to the after-coming head', 'correct' => false],
                ],
            ],
            [
                'text' => 'The fetal trunk has delivered to the level of the umbilicus but the legs remain extended and fail to deliver spontaneously. What is the correct action?',
                'explanation' => 'If the legs remain extended, deliver one leg at a time by flexing and abducting at the knee, gently grasping the ankle, and delivering each foot in turn, while avoiding traction on the fetus.',
                'options' => [
                    ['text' => 'Apply firm traction on the trunk until the legs deliver', 'correct' => false],
                    ['text' => 'Flex and abduct at the knee, gently grasp the ankle, and deliver each foot in turn', 'correct' => true],
                    ['text' => "Perform Lovset's manoeuvre", 'correct' => false],
                    ['text' => 'Immediately proceed to Caesarean section', 'correct' => false],
                    ['text' => 'Apply suprapubic pressure to release the legs', 'correct' => false],
                ],
            ],
            [
                'text' => "Lovset's manoeuvre is indicated during vaginal breech delivery when:",
                'explanation' => "Lovset's manoeuvre is performed when one or both fetal arms are extended or nuchal and fail to deliver spontaneously.",
                'options' => [
                    ['text' => 'The legs fail to deliver spontaneously', 'correct' => false],
                    ['text' => 'The after-coming head is entrapped', 'correct' => false],
                    ['text' => 'One or both fetal arms are extended or nuchal and fail to deliver spontaneously', 'correct' => true],
                    ['text' => 'The fetal heart rate becomes non-reassuring', 'correct' => false],
                    ['text' => 'Cord prolapse occurs', 'correct' => false],
                ],
            ],
            [
                'text' => 'During the Mauriceau-Smellie-Veit (MSV) manoeuvre, where should the operator place their fingers?',
                'explanation' => 'Fingers are placed on the fetal maxilla (bony prominence of the cheekbones), not inside the mouth, to maintain flexion of the head while avoiding jaw fracture or soft tissue injury.',
                'options' => [
                    ['text' => 'On the fetal maxilla, not inside the mouth', 'correct' => true],
                    ['text' => 'Inside the fetal mouth to maintain flexion', 'correct' => false],
                    ['text' => 'On the fetal abdomen with both hands', 'correct' => false],
                    ['text' => 'Around the umbilical cord for traction', 'correct' => false],
                    ['text' => 'On the fetal occiput only, without supporting the shoulders', 'correct' => false],
                ],
            ],
            [
                'text' => 'The after-coming head in vaginal breech delivery should ideally be delivered within:',
                'explanation' => 'Delivery should ideally be completed within approximately 5 minutes of buttock delivery to minimize cord compression and fetal hypoxia.',
                'options' => [
                    ['text' => 'Approximately 5 minutes of buttock delivery', 'correct' => true],
                    ['text' => '10–15 minutes to avoid trauma', 'correct' => false],
                    ['text' => 'Only after full spontaneous extension of the head', 'correct' => false],
                    ['text' => 'Immediately, before the arms are delivered', 'correct' => false],
                    ['text' => 'After a mandatory 10-minute wait to allow moulding', 'correct' => false],
                ],
            ],
            [
                'text' => 'Suprapubic pressure during the Mauriceau-Smellie-Veit manoeuvre is applied to:',
                'explanation' => 'Suprapubic pressure helps flex and assist delivery of the after-coming head; it is not used to control bleeding, prevent cord prolapse, or rotate the fetus.',
                'options' => [
                    ['text' => 'Prevent cord prolapse', 'correct' => false],
                    ['text' => 'Control postpartum bleeding', 'correct' => false],
                    ['text' => 'Flex and assist delivery of the after-coming head', 'correct' => true],
                    ['text' => 'Reduce perineal trauma', 'correct' => false],
                    ['text' => 'Rotate the fetus into an oblique diameter', 'correct' => false],
                ],
            ],
            [
                'text' => 'An episiotomy during vaginal breech delivery should be performed:',
                'explanation' => 'An episiotomy in vaginal breech delivery should be performed only when clinically indicated to facilitate delivery or manoeuvres; it is not performed routinely.',
                'options' => [
                    ['text' => 'Routinely before delivery of the buttocks', 'correct' => false],
                    ['text' => 'Only when clinically indicated to facilitate delivery or manoeuvres', 'correct' => true],
                    ['text' => 'Only in primigravid women', 'correct' => false],
                    ['text' => 'After the head has already delivered', 'correct' => false],
                    ['text' => 'Only if the woman requests it', 'correct' => false],
                ],
            ],
            [
                'text' => 'If the after-coming head becomes entrapped despite correct manoeuvres, the next step is to:',
                'explanation' => 'Call for senior assistance and prepare for uterine relaxation and theatre management; continuing to pull or applying fundal pressure risks serious fetal and maternal injury.',
                'options' => [
                    ['text' => 'Continue vigorous traction on the fetal trunk', 'correct' => false],
                    ['text' => 'Call for senior assistance and prepare for uterine relaxation and theatre', 'correct' => true],
                    ['text' => 'Apply fundal pressure to assist delivery', 'correct' => false],
                    ['text' => 'Wait for spontaneous delivery without further intervention', 'correct' => false],
                    ['text' => 'Repeat the Mauriceau-Smellie-Veit manoeuvre indefinitely until successful', 'correct' => false],
                ],
            ],
            [
                'text' => 'A major risk of inappropriate or excessive traction during vaginal breech delivery is:',
                'explanation' => 'Excessive traction risks extending the fetal head or arms and causing brachial plexus injury; it is not primarily associated with uterine rupture, retained placenta, or urinary retention.',
                'options' => [
                    ['text' => 'Maternal perineal laceration only', 'correct' => false],
                    ['text' => 'Fetal brachial plexus injury or extension of the fetal head/arms', 'correct' => true],
                    ['text' => 'Uterine rupture', 'correct' => false],
                    ['text' => 'Retained placenta', 'correct' => false],
                    ['text' => 'Maternal urinary retention', 'correct' => false],
                ],
            ],
            [
                'text' => 'Before encouraging maternal pushing during breech delivery, the clinician must confirm:',
                'explanation' => 'Full cervical dilatation must be confirmed before encouraging pushing; pushing before full dilatation risks cervical trauma and poor progress.',
                'options' => [
                    ['text' => 'Estimated fetal weight only', 'correct' => false],
                    ['text' => 'Maternal blood group', 'correct' => false],
                    ['text' => 'Full cervical dilatation', 'correct' => true],
                    ['text' => 'Rupture of membranes only', 'correct' => false],
                    ['text' => 'Availability of a paediatrician on site', 'correct' => false],
                ],
            ],
            [
                'text' => 'What is the single most important principle for safe vaginal breech delivery?',
                'explanation' => 'The core principle is to allow spontaneous descent with a hands-off approach, intervening with timely, skilled manoeuvres only when necessary.',
                'options' => [
                    ['text' => 'Routine use of forceps for the after-coming head', 'correct' => false],
                    ['text' => 'Allow spontaneous descent with timely, skilled intervention only when necessary', 'correct' => true],
                    ['text' => 'Immediate episiotomy in all cases', 'correct' => false],
                    ['text' => 'Continuous traction to expedite delivery', 'correct' => false],
                    ['text' => 'Encourage pushing before full dilatation to save time', 'correct' => false],
                ],
            ],
        ];
    }

    private function shoulderDystociaQuestions(): array
    {
        return [
            [
                'text' => 'Shoulder dystocia is best defined as:',
                'explanation' => 'Shoulder dystocia is the impaction of the fetal shoulders after delivery of the head, requiring additional obstetric manoeuvres beyond gentle downward traction.',
                'options' => [
                    ['text' => 'Failure of the fetal head to deliver', 'correct' => false],
                    ['text' => 'Impaction of the fetal shoulders after delivery of the head', 'correct' => true],
                    ['text' => 'Cord prolapse occurring after delivery of the head', 'correct' => false],
                    ['text' => 'Uterine rupture occurring during the second stage', 'correct' => false],
                    ['text' => 'Delay in delivery of the placenta after birth of the shoulders', 'correct' => false],
                ],
            ],
            [
                'text' => 'The classic clinical sign of shoulder dystocia is:',
                'explanation' => 'The "turtle sign" — retraction of the fetal head tightly against the perineum with failure of restitution — is the classic sign of shoulder dystocia.',
                'options' => [
                    ['text' => 'Turtle sign (retraction of the fetal head against the perineum)', 'correct' => true],
                    ['text' => 'Maternal hypertension', 'correct' => false],
                    ['text' => 'Fetal bradycardia before head delivery', 'correct' => false],
                    ['text' => 'Prolonged first stage of labour', 'correct' => false],
                    ['text' => 'Excessive moulding of the fetal skull', 'correct' => false],
                ],
            ],
            [
                'text' => 'What is the first step after recognizing shoulder dystocia?',
                'explanation' => 'The first step is to announce the emergency and call for help immediately, before proceeding to any manoeuvre.',
                'options' => [
                    ['text' => 'Perform an episiotomy', 'correct' => false],
                    ['text' => 'Call for help and announce the emergency', 'correct' => true],
                    ['text' => 'Apply fundal pressure', 'correct' => false],
                    ['text' => 'Start an oxytocin infusion', 'correct' => false],
                    ['text' => 'Immediately attempt the McRoberts manoeuvre without announcing the emergency', 'correct' => false],
                ],
            ],
            [
                'text' => 'Each manoeuvre in the management of shoulder dystocia should generally be attempted for no longer than:',
                'explanation' => 'Each manoeuvre should generally be attempted for no more than approximately 30 seconds before progressing to the next step if unsuccessful, to avoid delaying delivery.',
                'options' => [
                    ['text' => '10 seconds', 'correct' => false],
                    ['text' => '30 seconds', 'correct' => true],
                    ['text' => '2 minutes', 'correct' => false],
                    ['text' => '5 minutes', 'correct' => false],
                    ['text' => 'Until the mother requests to stop', 'correct' => false],
                ],
            ],
            [
                'text' => 'The McRoberts manoeuvre involves:',
                'explanation' => 'The McRoberts manoeuvre involves flexion and abduction of both maternal hips with the buttocks positioned at the edge of the bed, which straightens the lumbosacral angle and rotates the symphysis cephalad.',
                'options' => [
                    ['text' => 'Rolling the woman onto all fours', 'correct' => false],
                    ['text' => 'Applying fundal pressure', 'correct' => false],
                    ['text' => 'Performing suprapubic pressure', 'correct' => false],
                    ['text' => 'Flexion and abduction of both maternal hips', 'correct' => true],
                    ['text' => 'Insertion of fingers through the sacral hollow to rotate the shoulders', 'correct' => false],
                ],
            ],
            [
                'text' => 'Suprapubic pressure in shoulder dystocia should be applied:',
                'explanation' => 'Suprapubic pressure is applied downward and laterally toward the fetal chest, to help adduct the anterior shoulder under the symphysis pubis.',
                'options' => [
                    ['text' => 'Downward and laterally toward the fetal chest', 'correct' => true],
                    ['text' => 'On the maternal abdomen above the umbilicus', 'correct' => false],
                    ['text' => 'Directly downward on the uterine fundus', 'correct' => false],
                    ['text' => 'On the posterior fetal shoulder', 'correct' => false],
                    ['text' => 'Only after the posterior arm has been delivered', 'correct' => false],
                ],
            ],
            [
                'text' => 'Fundal pressure is contraindicated in shoulder dystocia because it:',
                'explanation' => 'Fundal pressure worsens shoulder impaction and increases the risk of uterine rupture and fetal injury; it must never be used.',
                'options' => [
                    ['text' => 'Causes maternal discomfort only', 'correct' => false],
                    ['text' => 'Worsens shoulder impaction and increases the risk of uterine rupture', 'correct' => true],
                    ['text' => 'Increases the risk of perineal tear only', 'correct' => false],
                    ['text' => 'Delays delivery of the placenta', 'correct' => false],
                    ['text' => 'Reduces maternal blood pressure', 'correct' => false],
                ],
            ],
            [
                'text' => 'The posterior arm is delivered by:',
                'explanation' => 'The posterior arm is delivered by locating the posterior elbow, flexing the arm across the fetal chest, and sweeping the forearm and hand over the face and out of the vagina, reducing the bi-acromial diameter.',
                'options' => [
                    ['text' => 'Rotating the fetus 180 degrees externally', 'correct' => false],
                    ['text' => 'Locating the posterior elbow, flexing the arm across the fetal chest, and sweeping it over the face', 'correct' => true],
                    ['text' => 'Applying firm traction on the anterior shoulder', 'correct' => false],
                    ['text' => "Performing Lovset's manoeuvre", 'correct' => false],
                    ['text' => 'Pulling directly on the posterior wrist without flexing the elbow', 'correct' => false],
                ],
            ],
            [
                'text' => 'McRoberts manoeuvre, suprapubic pressure and internal rotational manoeuvres have all failed. What is the next recommended step?',
                'explanation' => 'If standard manoeuvres fail, the woman should be rolled into the all-fours (Gaskin) position, and appropriate manoeuvres reattempted.',
                'options' => [
                    ['text' => 'Immediate Caesarean section', 'correct' => false],
                    ['text' => 'Roll the woman into the all-fours (Gaskin) position', 'correct' => true],
                    ['text' => 'Repeat McRoberts manoeuvre a third time', 'correct' => false],
                    ['text' => 'Perform a symphysiotomy', 'correct' => false],
                    ['text' => 'Apply fundal pressure combined with traction', 'correct' => false],
                ],
            ],
            [
                'text' => 'Approximately what proportion of shoulder dystocia cases occur in infants of normal birth weight?',
                'explanation' => 'Approximately 50% of shoulder dystocia cases occur in infants of normal birth weight, which is why the emergency cannot be reliably predicted or prevented.',
                'options' => [
                    ['text' => '10%', 'correct' => false],
                    ['text' => '25%', 'correct' => false],
                    ['text' => '50%', 'correct' => true],
                    ['text' => '90%', 'correct' => false],
                    ['text' => 'Shoulder dystocia occurs only in macrosomic infants', 'correct' => false],
                ],
            ],
            [
                'text' => 'Which mnemonic summarizes the recommended structured sequence of manoeuvres for shoulder dystocia?',
                'explanation' => 'HELPERR is the mnemonic used for the structured sequence of manoeuvres in shoulder dystocia management.',
                'options' => [
                    ['text' => 'ABCDE', 'correct' => false],
                    ['text' => 'DRSABC', 'correct' => false],
                    ['text' => 'HELPERR', 'correct' => true],
                    ['text' => 'SBAR', 'correct' => false],
                    ['text' => 'A–J', 'correct' => false],
                ],
            ],
            [
                'text' => 'An episiotomy in shoulder dystocia is indicated primarily to:',
                'explanation' => 'An episiotomy in shoulder dystocia is indicated primarily to facilitate internal manoeuvres when soft tissue is limiting access to the fetal shoulders; it does not itself relieve bony impaction.',
                'options' => [
                    ['text' => 'Speed up delivery routinely in all cases', 'correct' => false],
                    ['text' => 'Prevent perineal trauma', 'correct' => false],
                    ['text' => 'Facilitate internal manoeuvres when soft tissue is limiting access', 'correct' => true],
                    ['text' => 'Allow delivery of the posterior arm without any manoeuvre', 'correct' => false],
                    ['text' => 'Reduce the risk of uterine rupture', 'correct' => false],
                ],
            ],
            [
                'text' => 'During management of shoulder dystocia, the midwife should record:',
                'explanation' => 'The time of head delivery and the timing/sequence of each manoeuvre performed must be documented, as this information is critical for subsequent clinical and medico-legal review.',
                'options' => [
                    ['text' => 'Only the final outcome', 'correct' => false],
                    ['text' => 'Only the manoeuvres used, without timing', 'correct' => false],
                    ['text' => 'Nothing until after the debrief', 'correct' => false],
                    ['text' => 'The time of head delivery and the timing/sequence of each manoeuvre', 'correct' => true],
                    ['text' => 'Only the neonatal Apgar score', 'correct' => false],
                ],
            ],
            [
                'text' => 'McRoberts, suprapubic pressure, internal rotational manoeuvres, posterior arm delivery and the all-fours position have all failed to deliver the shoulders. What should be considered next, according to facility capability?',
                'explanation' => 'When all standard manoeuvres fail, salvage procedures — such as intentional clavicular fracture, axillary sling traction, abdominal rescue, or the Zavanelli manoeuvre — may be considered according to facility capability.',
                'options' => [
                    ['text' => 'Continue repeating the same manoeuvres indefinitely', 'correct' => false],
                    ['text' => 'Apply fundal pressure as a last resort', 'correct' => false],
                    ['text' => 'Consider salvage procedures such as deliberate clavicular fracture, axillary sling traction, abdominal rescue or the Zavanelli manoeuvre', 'correct' => true],
                    ['text' => 'Discharge and reassess in 30 minutes', 'correct' => false],
                    ['text' => 'Perform an immediate hysterectomy', 'correct' => false],
                ],
            ],
            [
                'text' => 'What is the single most important lesson from shoulder dystocia simulation?',
                'explanation' => 'Prompt recognition, structured manoeuvres, avoidance of excessive traction and fundal pressure, and effective teamwork are the key determinants of a safe outcome.',
                'options' => [
                    ['text' => 'Shoulder dystocia can always be predicted and prevented', 'correct' => false],
                    ['text' => 'Prompt recognition, structured manoeuvres, avoidance of excessive traction, and effective teamwork improve outcomes', 'correct' => true],
                    ['text' => 'Caesarean section is never required', 'correct' => false],
                    ['text' => 'Delaying help is acceptable if manoeuvres are eventually performed correctly', 'correct' => false],
                    ['text' => 'Fundal pressure should always be attempted before McRoberts', 'correct' => false],
                ],
            ],
        ];
    }

    private function vacuumQuestions(): array
    {
        return [
            [
                'text' => 'Vacuum-assisted vaginal birth is most appropriate in which of the following situations?',
                'explanation' => 'Vacuum-assisted vaginal birth is indicated for a prolonged second stage of labour with fetal compromise, once all prerequisites have been confirmed.',
                'options' => [
                    ['text' => 'Prolonged first stage of labour', 'correct' => false],
                    ['text' => 'Prolonged second stage with fetal compromise and confirmed prerequisites', 'correct' => true],
                    ['text' => 'Breech presentation', 'correct' => false],
                    ['text' => 'Suspected cephalopelvic disproportion', 'correct' => false],
                    ['text' => 'Transverse lie at full dilatation', 'correct' => false],
                ],
            ],
            [
                'text' => 'Alongside full cervical dilatation, which of the following is a critical prerequisite before applying the vacuum cup?',
                'explanation' => 'An engaged fetal head (≤1/5 palpable abdominally) is a critical prerequisite; without engagement, vacuum extraction risks failure and serious fetal/maternal injury.',
                'options' => [
                    ['text' => 'Estimated fetal weight below 3.5 kg', 'correct' => false],
                    ['text' => 'Presence of meconium-stained liquor', 'correct' => false],
                    ['text' => 'An engaged fetal head (≤1/5 palpable abdominally)', 'correct' => true],
                    ['text' => 'Gestation of at least 34 weeks', 'correct' => false],
                    ['text' => 'Maternal request for an operative delivery', 'correct' => false],
                ],
            ],
            [
                'text' => 'What is the correct placement of the vacuum cup?',
                'explanation' => 'The cup is placed 2–3 cm anterior to the posterior fontanelle on the sagittal suture — the flexion point — to promote flexion of the fetal head during traction.',
                'options' => [
                    ['text' => 'Over the anterior fontanelle', 'correct' => false],
                    ['text' => '2–3 cm anterior to the posterior fontanelle on the sagittal suture (the flexion point)', 'correct' => true],
                    ['text' => 'Directly over the occiput', 'correct' => false],
                    ['text' => 'Over the posterior fontanelle itself', 'correct' => false],
                    ['text' => 'At the midpoint between the two fontanelles', 'correct' => false],
                ],
            ],
            [
                'text' => 'What is the recommended initial vacuum pressure before it is gradually increased?',
                'explanation' => 'Vacuum is created gradually: an initial pressure of 0.2 kg/cm² is applied, cup placement is rechecked, and pressure is then increased to 0.8 kg/cm².',
                'options' => [
                    ['text' => '0.8 kg/cm²', 'correct' => false],
                    ['text' => '0.2 kg/cm²', 'correct' => true],
                    ['text' => '0.6 kg/cm²', 'correct' => false],
                    ['text' => 'Maximum pressure from the outset', 'correct' => false],
                    ['text' => 'No pressure until the second contraction', 'correct' => false],
                ],
            ],
            [
                'text' => 'Traction during vacuum extraction should be applied:',
                'explanation' => 'Traction should be applied only during uterine contractions, following the pelvic axis in a J-shaped curve, to work with rather than against the natural forces of labour.',
                'options' => [
                    ['text' => 'Continuously, regardless of contractions', 'correct' => false],
                    ['text' => 'In a straight downward direction only', 'correct' => false],
                    ['text' => 'Using the cup to rotate the fetal head into the correct position', 'correct' => false],
                    ['text' => 'Only during uterine contractions, following the pelvic axis', 'correct' => true],
                    ['text' => 'Only after the cup has been in place for at least 10 minutes', 'correct' => false],
                ],
            ],
            [
                'text' => 'Which of the following is a major contraindication to vacuum extraction?',
                'explanation' => 'Suspected cephalopelvic disproportion is a major contraindication to vacuum extraction, since expediting delivery through a disproportionate pelvis risks severe maternal and fetal injury.',
                'options' => [
                    ['text' => 'Suspected cephalopelvic disproportion', 'correct' => true],
                    ['text' => 'A fetal heart rate of 110 bpm', 'correct' => false],
                    ['text' => 'Occipito-anterior position', 'correct' => false],
                    ['text' => 'Gestation of 37 weeks', 'correct' => false],
                    ['text' => 'Ruptured membranes', 'correct' => false],
                ],
            ],
            [
                'text' => 'The vacuum cup should be released:',
                'explanation' => 'The vacuum should be released immediately after delivery of the fetal head; the remainder of the birth then proceeds normally.',
                'options' => [
                    ['text' => 'After each contraction', 'correct' => false],
                    ['text' => 'Immediately after delivery of the fetal head', 'correct' => true],
                    ['text' => 'Only after the shoulders are delivered', 'correct' => false],
                    ['text' => 'Once the procedure exceeds 10 minutes', 'correct' => false],
                    ['text' => 'Only when the placenta has delivered', 'correct' => false],
                ],
            ],
            [
                'text' => 'Which of the following is NOT a recognized failure/abandonment criterion for vacuum extraction?',
                'explanation' => 'Failure criteria are objective: no descent with each pull, no delivery after three pulls, two cup detachments ("pop-offs"), or a procedure exceeding 20 minutes. Maternal request to continue is not itself a criterion for abandonment or continuation.',
                'options' => [
                    ['text' => 'No descent with each pull', 'correct' => false],
                    ['text' => 'Two cup detachments ("pop-offs")', 'correct' => false],
                    ['text' => 'Procedure duration exceeding 20 minutes', 'correct' => false],
                    ['text' => 'Maternal request to continue despite lack of progress', 'correct' => true],
                    ['text' => 'No delivery after three pulls', 'correct' => false],
                ],
            ],
            [
                'text' => 'What is the most serious neonatal complication associated with incorrect vacuum cup placement away from the flexion point?',
                'explanation' => 'Sub-galeal haemorrhage is the most serious neonatal complication of incorrect cup placement; it can cause life-threatening blood loss into the subgaleal space.',
                'options' => [
                    ['text' => 'Cephalohematoma', 'correct' => false],
                    ['text' => 'Sub-galeal haemorrhage', 'correct' => true],
                    ['text' => 'Caput succedaneum', 'correct' => false],
                    ['text' => 'Minor scalp abrasions', 'correct' => false],
                    ['text' => 'Neonatal jaundice', 'correct' => false],
                ],
            ],
            [
                'text' => 'During vacuum extraction, the midwife places one finger beside the cup in order to:',
                'explanation' => 'Placing one finger beside the cup allows the operator to monitor fetal descent with each pull and detect cup slippage early.',
                'options' => [
                    ['text' => 'Apply additional traction', 'correct' => false],
                    ['text' => 'Monitor fetal descent and detect cup slippage', 'correct' => true],
                    ['text' => 'Rotate the fetal head', 'correct' => false],
                    ['text' => 'Measure the vacuum pressure', 'correct' => false],
                    ['text' => 'Assess maternal blood loss', 'correct' => false],
                ],
            ],
            [
                'text' => 'A backup plan for failed vacuum extraction should always include:',
                'explanation' => 'Immediate preparation for Caesarean section is the essential backup plan whenever vacuum extraction is attempted, in case failure criteria are met.',
                'options' => [
                    ['text' => 'A repeat attempt after 30 minutes of rest', 'correct' => false],
                    ['text' => 'Proceeding to forceps delivery', 'correct' => false],
                    ['text' => 'Immediate preparation for Caesarean section', 'correct' => true],
                    ['text' => 'Symphysiotomy', 'correct' => false],
                    ['text' => 'Awaiting spontaneous delivery', 'correct' => false],
                ],
            ],
            [
                'text' => 'Vacuum extraction is generally considered safer than second-stage Caesarean section when:',
                'explanation' => 'When all prerequisites are met and the operator is skilled, vacuum extraction is associated with fewer surgical complications and faster recovery than second-stage Caesarean section.',
                'options' => [
                    ['text' => 'There is suspected cephalopelvic disproportion', 'correct' => false],
                    ['text' => 'All prerequisites are met and the operator is skilled', 'correct' => true],
                    ['text' => 'The fetal head is not engaged', 'correct' => false],
                    ['text' => 'The woman has had a previous Caesarean section', 'correct' => false],
                    ['text' => 'The fetal head position cannot be determined', 'correct' => false],
                ],
            ],
            [
                'text' => 'Before applying the vacuum cup, why is it important to correctly determine fetal head position by identifying the sagittal suture and fontanelles?',
                'explanation' => 'Correctly determining fetal head position ensures the cup is placed on the flexion point, which improves the chance of success and reduces the risk of complications.',
                'options' => [
                    ['text' => 'It confirms fetal sex', 'correct' => false],
                    ['text' => 'It ensures placement on the flexion point, improving success and reducing complications', 'correct' => true],
                    ['text' => 'It is required only in occipito-posterior positions', 'correct' => false],
                    ['text' => 'It replaces the need to confirm full dilatation', 'correct' => false],
                    ['text' => 'It determines the choice of episiotomy type', 'correct' => false],
                ],
            ],
            [
                'text' => 'Following successful vacuum-assisted delivery, the newborn should be specifically assessed for:',
                'explanation' => 'The newborn should be assessed for scalp injuries, cephalohematoma and sub-galeal haemorrhage, which are complications specifically associated with vacuum extraction.',
                'options' => [
                    ['text' => 'Only the Apgar score at 1 minute', 'correct' => false],
                    ['text' => 'Scalp injuries, cephalohematoma and sub-galeal haemorrhage', 'correct' => true],
                    ['text' => 'Congenital anomalies unrelated to delivery mode', 'correct' => false],
                    ['text' => 'Hip dysplasia', 'correct' => false],
                    ['text' => 'Umbilical hernia', 'correct' => false],
                ],
            ],
            [
                'text' => 'What is the single most important principle in vacuum-assisted vaginal birth?',
                'explanation' => 'Appropriate case selection, correct technique, and timely abandonment when failure criteria are met are the core principles underlying safe vacuum-assisted vaginal birth.',
                'options' => [
                    ['text' => 'Routine use of maximum vacuum pressure from the start', 'correct' => false],
                    ['text' => 'Appropriate case selection, correct technique, and timely abandonment when indicated', 'correct' => true],
                    ['text' => 'Performing the procedure without consent to save time', 'correct' => false],
                    ['text' => 'Continuing despite failure criteria being met', 'correct' => false],
                    ['text' => 'Using the cup to actively rotate the fetal head into an anterior position', 'correct' => false],
                ],
            ],
        ];
    }
}
