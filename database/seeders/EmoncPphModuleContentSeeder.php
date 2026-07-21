<?php

namespace Database\Seeders;

use App\Models\Program;
use App\Models\ProgramModule;
use App\Models\ProgramModuleContent;
use App\Models\ProgramModuleQuiz;
use App\Models\QuizOption;
use App\Models\QuizQuestion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the parent-module content, objectives/workplan, and knowledge-assessment
 * quiz for Module 5: Management of Postpartum Hemorrhage (PPH).
 *
 * Scope: this seeder covers ONLY the PPH module's own top-level content
 * (introduction, case scenario/progression, expected learning outcome,
 * mentor materials) and its module-level quiz on PPH overview knowledge
 * (definitions/thresholds, the E-MOTIVE bundle, first-line uterotonic,
 * TXA dosing, and escalation). It intentionally does NOT touch the PPH
 * Track (child ProgramModule) records or their own track-level content —
 * those are seeded separately. It also does NOT touch the ModuleRubric
 * for this module, which already exists.
 *
 * Source of truth: where the mentor manual (CHAI EmONC Knowledge Pack,
 * Module 6: Management of Postpartum Haemorrhage) and the mentee manual
 * (Module 5) disagree, the mentor manual is authoritative — notably the
 * bundle is named "E-MOTIVE" (not "MOTIVE"), and heat-stable carbetocin is
 * used only for PPH prevention (AMTSL), never for PPH treatment.
 */
class EmoncPphModuleContentSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $program = Program::where('name', 'Maternal Health (EmONC)')->firstOrFail();

            $pphModule = ProgramModule::where('program_id', $program->id)
                ->where('name', 'like', '%Postpartum Hemorrhage%')
                ->whereNull('parent_id')
                ->firstOrFail();

            $this->seedContent($pphModule);
            $this->seedModuleFields($pphModule);
            $this->seedQuiz($pphModule);
        });

        $this->command->info('PPH module content, objectives/workplan, and knowledge assessment quiz seeded successfully.');
    }

    private function seedContent(ProgramModule $pphModule): void
    {
        // Introduction / overview
        ProgramModuleContent::firstOrCreate(
            [
                'program_module_id' => $pphModule->id,
                'type' => 'introduction',
                'title' => 'Overview: Recognising and Managing Postpartum Haemorrhage (PPH)',
            ],
            [
                'content' => "PPH is the leading cause of maternal death in Kenya and one of the most time-critical obstetric emergencies. Objective measurement of blood loss (e.g. with a calibrated drape) plus clinical assessment improves early detection — visual estimation alone is frequently inaccurate and PPH often goes unrecognised until it is too late for a life-saving intervention.\n\nDEFINITION AND THRESHOLDS\nPPH is blood loss of ≥500 mL after vaginal birth, or ≥1000 mL after caesarean section, or any amount of postpartum bleeding that causes haemodynamic instability. Kenya/WHO recognition trigger: act when blood loss reaches ≥500 mL, OR ≥300 mL plus at least one abnormal observation (e.g. atonic uterus, heavy flow/large clots/constant trickle, pulse >100 bpm or a rise of ≥20 bpm, or systolic BP <100 mmHg or a fall of ≥20 mmHg). Do not wait for the 500 mL mark if the woman is already showing signs — a woman with anaemia may deteriorate with relatively little blood loss.\n\nTYPES OF PPH\n• Primary (immediate) PPH — occurs within the first 24 hours after birth; approximately 70% of cases are due to uterine atony (failure of the uterus to contract adequately).\n• Secondary PPH — significant vaginal bleeding between 24 hours and 6 weeks after birth; common causes include retained placental fragments, endometritis, sepsis and delayed involution of the placental bed.\n• Refractory PPH — bleeding requiring second-line interventions: three or more uterotonics, bimanual uterine compression, uterine balloon tamponade, or surgical treatment (laceration repair, uterine cavity exploration, devascularisation, compression sutures, or hysterectomy).\n\nCAUSES — THE 4 Ts\nTone (uterine atony, ~70%) • Trauma (uterine/cervical/vaginal tears or rupture, ~20%) • Tissue (retained placenta, membranes or clots, ~10%) • Thrombin (pre-existing or acquired coagulopathy, ~1%).\n\nIMMEDIATE RESPONSE\nOnce PPH is diagnosed: shout for help and call for the PPH kit; identify a team leader who assigns roles; reassure the woman and briefly explain what is happening; ensure the airway is open and she is breathing (give oxygen by mask if breathing, begin resuscitation if not); start the WHO First Response Bundle regardless of the suspected cause.\n\nTHE E-MOTIVE BUNDLE (WHO First Response Bundle)\nE — Early detection: objective blood-loss measurement plus clinical judgement; trigger at ≥500 mL, or ≥300 mL with an abnormal observation.\nM — Massage: massage the uterus until firm; empty the bladder (catheterise if distended).\nO — Oxytocics: oxytocin 10 IU IV/IM (or 10 IU in 500 mL crystalloid over 10 minutes, or as fast as possible), then a maintenance infusion of 20 IU in 1 L crystalloid over 4 hours; misoprostol 800 mcg sublingually may be added. Note: heat-stable carbetocin (100 mcg IM/slow IV) is used only for PPH PREVENTION during active management of the third stage of labour — it is not used to treat established PPH.\nT — TXA: tranexamic acid 1 g IV over 10 minutes, given as soon as possible and within 3 hours of birth, in all cases of PPH regardless of cause; repeat 1 g IV over 10 minutes if bleeding continues 30 minutes after the first dose or restarts within 24 hours. Do not begin TXA if birth occurred more than 3 hours ago — it confers no benefit and the risk of harm outweighs any possible benefit.\nIV — IV fluids: two large-bore (16G/18G) IV cannulas; draw blood samples (FBC, group and cross-match, U&E, coagulation profile) without delaying treatment; give crystalloid (normal saline or Ringer's lactate) with restraint, moving early to blood products rather than over-transfusing crystalloid.\nE — Examination & escalation: assess and empty the bladder; examine the birth canal for cervical/vaginal tears (repair within scope, or refer if 3rd/4th-degree tears or extensive lacerations); re-check the placenta for completeness; once the bundle and examination are complete, manage the identified cause specifically.\nAll bundle components should be started within the shortest possible time (ideally within 15 minutes) without waiting for a response to one intervention before starting the next.\n\nESCALATION\nEscalate if the cause is unclear, the team is unable to manage, or bleeding continues despite the bundle: call for additional senior help (obstetrician, anaesthesia); repeat TXA if indicated; continue/add uterotonics; consider advanced interventions for refractory PPH — non-pneumatic anti-shock garment (NASG), bimanual uterine compression, aortic artery compression, uterine balloon tamponade, and surgical options (haemostatic compression sutures such as B-Lynch, systematic pelvic devascularisation, subtotal hysterectomy); transfuse as soon as blood is needed and available (especially if Hb <7 g/dL or Hct <20%); transfer if required. Once bleeding is controlled, continue routine monitoring of uterine tone, bleeding and vital signs every 15 minutes for 2 hours then every 30 minutes for 4 hours, using the MEOWS chart.\n\nA major, avoidable error in PPH management is underestimation of blood loss — always quantify it objectively rather than relying on visual estimation alone.",
                'order_sequence' => 1,
                'is_active' => true,
            ]
        );

        // Case scenario already exists for this module (from the ModuleRubric-linked seed).
        // Per instructions, only add a new case_scenario if genuinely missing.
        $hasCaseScenario = ProgramModuleContent::where('program_module_id', $pphModule->id)
            ->where('type', 'case_scenario')
            ->exists();

        if (! $hasCaseScenario) {
            ProgramModuleContent::firstOrCreate(
                [
                    'program_module_id' => $pphModule->id,
                    'type' => 'case_scenario',
                    'title' => 'PPH Simulation — Case Scenario',
                ],
                [
                    'content' => "PATIENT: Nancy, para 3+0.\n\nSCENARIO: Nancy has just successfully delivered a live male infant who scored 10/10 at 1 and 5 minutes. The placenta was delivered and found complete. She has a history of prolonged labour. Shortly after delivery, excessive vaginal bleeding is noted; the uterus is soft (boggy).\n\nDEFAULT DIAGNOSIS: Primary PPH secondary to uterine atony.\n\nTASK: Lead the emergency management of this client. You may ask for help from other mentees as necessary.",
                    'order_sequence' => 1,
                    'is_active' => true,
                ]
            );
        }

        // Minute-by-minute escalation for the scenario — added as its own record either way,
        // whether the case scenario above is newly written or (as here) already exists.
        ProgramModuleContent::firstOrCreate(
            [
                'program_module_id' => $pphModule->id,
                'type' => 'case_scenario_progression',
                'title' => 'PPH Simulation — Scenario Progression',
            ],
            [
                'content' => "SCENARIO CONTEXT: Continuing the case of Nancy (para 3+0), who has just delivered a live male infant scoring 10/10 at 1 and 5 minutes, with a complete placenta following a prolonged labour. She develops excessive vaginal bleeding shortly after delivery.\n\nINITIAL ASSESSMENT (time 0): BP 108/68 mmHg | Pulse 96 bpm | RR 22/min | Temp 36.8°C | Blood loss 350 mL and increasing | Uterine tone: soft (boggy) | Placenta: complete | Baby: stable, breastfeeding.\n\n0–2 minutes: Uterus remains boggy; blood loss reaches 500 mL; pulse rises to 108 bpm; Nancy reports feeling dizzy.\nExpected actions: Recognise PPH at the ≥500 mL trigger. Shout for help and call for the PPH kit; identify a team leader who assigns roles. Reassure Nancy and briefly explain what is happening. Begin the E-MOTIVE bundle without delay.\n\n2–4 minutes: Blood loss reaches 700 mL; BP falls to 96/60 mmHg; pulse 118 bpm; uterus still soft.\nExpected actions: M — massage the uterus and empty the bladder. O — start an oxytocin infusion (10 IU in 500 mL over 10 minutes, then maintenance 20 IU in 1 L over 4 hours) plus misoprostol 800 mcg sublingually. T — give tranexamic acid 1 g IV over 10 minutes. IV — insert two large-bore cannulas; draw blood for FBC, group and cross-match, U&E and coagulation profile; commence crystalloid infusion.\n\n4–6 minutes: Bleeding continues; blood loss reaches 900 mL.\nExpected actions: E — examine and escalate. Systematically assess the 4 Ts: Tone (uterine tone), Trauma (inspect cervix, vagina and perineum for tears), Tissue (placenta/membrane completeness, retained products), Thrombin (consider coagulopathy if no obvious cause). Manage the identified cause specifically.\n\n6–8 minutes: Bleeding persists; BP 88/54 mmHg; pulse 126 bpm.\nExpected actions: Escalate immediately to a senior obstetrician and anaesthesia. Repeat TXA 1 g IV if more than 30 minutes have passed since the first dose, or if bleeding has restarted. Continue uterotonics. Prepare advanced interventions (bimanual compression, NASG, uterine balloon tamponade, surgery). Prepare for blood transfusion.\n\n8–10 minutes: Bleeding reduces following massage, oxytocin infusion and bimanual compression; the uterus becomes firm; vital signs begin to improve.\nExpected actions: Continue close monitoring — reassess tone, bleeding and vital signs every 15 minutes. Complete documentation, communicate with Nancy and the team, and continue observation using the MEOWS chart.\n\nKEY COMPETENCIES ASSESSED: early recognition and objective quantification of blood loss; prompt, complete activation of the E-MOTIVE bundle without delay; systematic use of the 4 Ts to identify and treat the cause; timely escalation and preparation for advanced/surgical interventions; teamwork, leadership, closed-loop communication and SBAR handover; accurate documentation.",
                'order_sequence' => 2,
                'is_active' => true,
            ]
        );

        // Expected learning outcome
        ProgramModuleContent::firstOrCreate(
            [
                'program_module_id' => $pphModule->id,
                'type' => 'expected_learning_outcome',
                'title' => 'Expected Learning Outcome',
            ],
            [
                'content' => 'By the end of this module, the mentee should be able to recognise postpartum haemorrhage promptly using objective blood-loss measurement and clinical assessment, activate the WHO First Response Bundle (E-MOTIVE) without delay, systematically identify the underlying cause using the 4 Ts (Tone, Trauma, Tissue, Thrombin), institute cause-specific management, and escalate appropriately — including preparing for advanced interventions such as bimanual compression, uterine balloon tamponade, NASG application and surgical options — while demonstrating effective teamwork, leadership, SBAR communication and respectful maternity care throughout.',
                'order_sequence' => 1,
                'is_active' => true,
            ]
        );

        // Mentor-only simulation setup/materials — distinct from the mentee-facing overview above.
        ProgramModuleContent::firstOrCreate(
            [
                'program_module_id' => $pphModule->id,
                'type' => 'mentor_materials',
                'title' => 'Mentor-Only Simulation Setup and Materials',
            ],
            [
                'content' => "MENTOR-ONLY SETUP — do not read this section to the mentees; the clinical scenario is read to the simulated client and birth companion only.\n\nAnticipated simulation duration: 10–12 minutes. Debrief duration: 20–25 minutes. Level of care: Basic or Comprehensive EmONC facility. Default diagnosis: Primary PPH secondary to uterine atony.\n\nEquipment: blood pressure machine, stethoscope, pulse oximeter, thermometer; calibrated blood-collection drape; fully stocked PPH emergency tray/kit; IV cannulas (16G/18G), giving sets, crystalloids; blood sample bottles and group-and-cross-match forms; urinary catheter and drainage bag; sterile gloves, vaginal-examination set, good examination light; suturing set; uterine balloon-tamponade kit (if available); Non-pneumatic Anti-Shock Garment (NASG); oxygen source; suction machine.\n\nMedications: oxytocin, tranexamic acid (TXA), misoprostol, ergometrine (avoid in hypertension), carboprost (if stocked).\n\nPersonal protective equipment: gloves, aprons, face masks, hand sanitiser.\n\nSimulation personnel/roles: team leader, primary birth attendant, assistant nurse/midwife, runner, simulated laboratory/theatre representatives, mentor, observer(s).\n\nMentor note: this is a high-acuity emergency drill. Emphasise rapid recognition, teamwork and implementation of the E-MOTIVE bundle within the shortest possible time; any delays by participants should be reflected in progressive deterioration of the simulated patient.",
                'order_sequence' => 1,
                'is_active' => true,
            ]
        );
    }

    private function seedModuleFields(ProgramModule $pphModule): void
    {
        $pphModule->update([
            'objectives' => [
                'Recognise PPH using blood-loss measurement and clinical assessment; identify early shock.',
                'Activate the emergency response and initiate the WHO First Response Bundle (E-MOTIVE) without delay.',
                'Identify the cause of PPH using the 4 Ts (Tone, Trauma, Tissue, Thrombin) and perform cause-specific interventions.',
                'Escalate appropriately when bleeding persists, including preparing for advanced/surgical interventions.',
                'Demonstrate teamwork, leadership, communication (including SBAR handover) and respectful maternity care.',
            ],
            'content' => [
                ['label' => 'Session 1: Lecturette', 'duration' => '10 min'],
                ['label' => 'Session 2: Mentor Demonstration', 'duration' => '20 min'],
                ['label' => 'Session 3: Return Demonstration & Debrief (per mentee)', 'duration' => '30 min'],
                ['label' => 'Session 4: Assessment & Debrief (per mentee)', 'duration' => '20 min'],
                ['label' => 'Simulation Drill', 'duration' => '10-12 min'],
                ['label' => 'Debrief', 'duration' => '20-25 min'],
            ],
        ]);
    }

    private function seedQuiz(ProgramModule $pphModule): void
    {
        $quiz = ProgramModuleQuiz::firstOrCreate(
            [
                'program_module_id' => $pphModule->id,
                'type' => 'both',
            ],
            [
                'title' => 'PPH Knowledge Assessment (Pre-test & Post-test)',
                'description' => 'A 15-question instrument administered before and after the PPH module to measure knowledge gain on PPH recognition, the E-MOTIVE bundle, uterotonic/TXA use, and escalation. The same questions are used for both the pre-test and post-test. Facilitators may shuffle question order for the post-test to reduce recall bias.',
                'pass_mark_percentage' => 70.00,
                'order_sequence' => 1,
                'is_active' => true,
            ]
        );

        $questions = [
            [
                'text' => 'Postpartum haemorrhage after vaginal birth is defined as blood loss of:',
                'explanation' => 'PPH is defined as blood loss of ≥500 mL after vaginal birth, or ≥1000 mL after caesarean section, or any postpartum bleeding causing haemodynamic instability.',
                'options' => [
                    ['text' => '300 mL or more after vaginal delivery', 'correct' => false],
                    ['text' => '500 mL or more after vaginal delivery, or 1000 mL or more after caesarean section', 'correct' => true],
                    ['text' => 'Any bleeding that requires transfusion', 'correct' => false],
                    ['text' => '750 mL after any delivery', 'correct' => false],
                    ['text' => '200 mL or more within the first hour after birth', 'correct' => false],
                ],
            ],
            [
                'text' => 'Which is the most common cause of primary PPH?',
                'explanation' => 'Uterine atony — failure of the uterus to contract adequately after childbirth — accounts for approximately 70% of primary PPH cases and falls under "Tone" in the 4 Ts framework.',
                'options' => [
                    ['text' => 'Retained placenta', 'correct' => false],
                    ['text' => 'Cervical tears', 'correct' => false],
                    ['text' => 'Uterine atony', 'correct' => true],
                    ['text' => 'Coagulopathy', 'correct' => false],
                    ['text' => 'Uterine rupture', 'correct' => false],
                ],
            ],
            [
                'text' => 'In the E-MOTIVE bundle, what does the "T" stand for?',
                'explanation' => 'In the E-MOTIVE bundle, "T" stands for Tranexamic acid (TXA), which is given to all women with PPH regardless of the underlying cause.',
                'options' => [
                    ['text' => 'Temperature control', 'correct' => false],
                    ['text' => 'Tranexamic acid', 'correct' => true],
                    ['text' => 'Tissue examination', 'correct' => false],
                    ['text' => 'Theatre preparation', 'correct' => false],
                    ['text' => 'Team leadership', 'correct' => false],
                ],
            ],
            [
                'text' => 'Tranexamic acid should ideally be administered in PPH:',
                'explanation' => 'TXA should be given as soon as possible and always within 3 hours of birth, in all cases of PPH regardless of cause; it should not be started if birth occurred more than 3 hours ago.',
                'options' => [
                    ['text' => 'After 3 hours of bleeding onset', 'correct' => false],
                    ['text' => 'Only if blood loss exceeds 1000 mL', 'correct' => false],
                    ['text' => 'Only after uterotonics have failed', 'correct' => false],
                    ['text' => 'As soon as possible and within 3 hours of birth', 'correct' => true],
                    ['text' => 'Only for PPH caused by genital tract trauma', 'correct' => false],
                ],
            ],
            [
                'text' => 'A key action when performing uterine massage in PPH is to:',
                'explanation' => 'Massage should be combined with emptying a distended bladder and continued until the uterus is firm; if the uterus remains soft after about a minute of massage alone, move quickly to giving a uterotonic.',
                'options' => [
                    ['text' => 'Empty the bladder and rub the uterus until it is firm', 'correct' => true],
                    ['text' => 'Massage continuously without ever reassessing tone', 'correct' => false],
                    ['text' => 'Massage only once bleeding exceeds 1 litre', 'correct' => false],
                    ['text' => 'Combine massage with bimanual compression from the outset in every case', 'correct' => false],
                    ['text' => 'Delay massage until oxytocin has been given', 'correct' => false],
                ],
            ],
            [
                'text' => 'A woman has a firm uterus but continues to bleed heavily after delivery. The most likely cause is:',
                'explanation' => 'A firm, well-contracted uterus with ongoing bleeding points away from atony and toward genital tract trauma (cervical or vaginal tears), which should be identified on examination and repaired.',
                'options' => [
                    ['text' => 'Uterine atony', 'correct' => false],
                    ['text' => 'Cervical or vaginal trauma', 'correct' => true],
                    ['text' => 'Retained placenta', 'correct' => false],
                    ['text' => 'Polyhydramnios', 'correct' => false],
                    ['text' => 'Coagulopathy', 'correct' => false],
                ],
            ],
            [
                'text' => 'The Non-pneumatic Anti-Shock Garment (NASG) is used in PPH to:',
                'explanation' => 'The NASG is a temporising measure that stabilises a woman in shock during transfer or while awaiting definitive treatment; it does not replace blood transfusion or definitive haemorrhage control.',
                'options' => [
                    ['text' => 'Stabilise the woman during transfer or while awaiting definitive treatment', 'correct' => true],
                    ['text' => 'Replace the need for blood transfusion', 'correct' => false],
                    ['text' => 'Treat uterine atony directly', 'correct' => false],
                    ['text' => 'Prevent genital tract trauma', 'correct' => false],
                    ['text' => 'Serve as first-line treatment before the E-MOTIVE bundle', 'correct' => false],
                ],
            ],
            [
                'text' => 'A major, avoidable error in PPH management is:',
                'explanation' => 'Visual estimation of blood loss is notoriously inaccurate and commonly underestimates true losses, which delays recognition and treatment; objective measurement (e.g. a calibrated drape) helps avoid this error.',
                'options' => [
                    ['text' => 'Early use of tranexamic acid', 'correct' => false],
                    ['text' => 'Calling for help early', 'correct' => false],
                    ['text' => 'Quantifying blood loss objectively', 'correct' => false],
                    ['text' => 'Underestimation of blood loss', 'correct' => true],
                    ['text' => 'Activating the E-MOTIVE bundle promptly', 'correct' => false],
                ],
            ],
            [
                'text' => 'A woman has ongoing PPH despite the initial E-MOTIVE interventions and a firm uterus. The next most important step is:',
                'explanation' => 'With a firm uterus and continued bleeding, atony is unlikely; the priority is a systematic 4 Ts assessment (Tone, Trauma, Tissue, Thrombin) to find and treat the actual source rather than repeating atony-directed treatment.',
                'options' => [
                    ['text' => 'Repeat the dose of oxytocin', 'correct' => false],
                    ['text' => 'Systematic examination using the 4 Ts to identify the source of bleeding', 'correct' => true],
                    ['text' => 'Immediate hysterectomy', 'correct' => false],
                    ['text' => 'Administration of more crystalloids and colloids', 'correct' => false],
                    ['text' => 'Discontinue TXA and switch to ergometrine', 'correct' => false],
                ],
            ],
            [
                'text' => 'The systematic approach using the "4 Ts" refers to:',
                'explanation' => 'The 4 Ts — Tone, Trauma, Tissue, Thrombin — is the systematic framework used to identify the underlying cause of PPH once the E-MOTIVE bundle has been initiated.',
                'options' => [
                    ['text' => 'Time, Temperature, Trauma, Tone', 'correct' => false],
                    ['text' => 'Tears, Tissue, Tone, Transfusion', 'correct' => false],
                    ['text' => 'Tone, Trauma, Tissue, Thrombin', 'correct' => true],
                    ['text' => 'Tone, Tears, Tissue, Thrombosis', 'correct' => false],
                    ['text' => 'Tone, Trauma, Thrombin, Transfusion', 'correct' => false],
                ],
            ],
            [
                'text' => 'Which advanced intervention may be used for refractory uterine atony?',
                'explanation' => 'Uterine balloon tamponade is a recognised advanced intervention for atonic PPH that fails to respond to uterotonics and massage; the other options are procedures used for unrelated obstetric complications.',
                'options' => [
                    ['text' => 'Vacuum extraction', 'correct' => false],
                    ['text' => 'Uterine balloon tamponade', 'correct' => true],
                    ['text' => 'External cephalic version', 'correct' => false],
                    ['text' => "McRoberts' manoeuvre", 'correct' => false],
                    ['text' => 'Manual removal of the placenta', 'correct' => false],
                ],
            ],
            [
                'text' => 'Persistent bleeding with a complete placenta and no genital-tract trauma found should raise suspicion of:',
                'explanation' => 'When the uterus is firm, the placenta is complete and no trauma is found, but bleeding (often diffuse oozing) continues, coagulopathy/DIC — the "Thrombin" T — should be suspected and a coagulation profile and fibrinogen sent.',
                'options' => [
                    ['text' => 'Multiple pregnancy', 'correct' => false],
                    ['text' => 'Coagulopathy', 'correct' => true],
                    ['text' => 'Placenta praevia', 'correct' => false],
                    ['text' => 'Shoulder dystocia', 'correct' => false],
                    ['text' => 'Uterine atony', 'correct' => false],
                ],
            ],
            [
                'text' => 'Which statement about PPH management is TRUE?',
                'explanation' => 'Objective measurement of blood loss (e.g. calibrated drapes) improves early recognition compared with visual estimation, which is frequently inaccurate. All E-MOTIVE components should be started without delay rather than sequentially, and TXA is given for all causes of PPH, not only atony.',
                'options' => [
                    ['text' => 'Visual estimation of blood loss is always accurate', 'correct' => false],
                    ['text' => 'Treatment should wait until the cause of bleeding is confirmed', 'correct' => false],
                    ['text' => 'Objective measurement of blood loss improves early recognition', 'correct' => true],
                    ['text' => 'Tranexamic acid should only be given for uterine atony', 'correct' => false],
                    ['text' => 'Each E-MOTIVE component should be given in turn, waiting for a response before starting the next', 'correct' => false],
                ],
            ],
            [
                'text' => 'The most appropriate initial fluid resuscitation in PPH is:',
                'explanation' => 'Two large-bore (16G/18G) IV cannulas with rapid, restrained crystalloid infusion is the initial resuscitation step, moving early to blood products rather than over-transfusing crystalloid.',
                'options' => [
                    ['text' => 'Two large-bore IV lines with rapid crystalloid infusion', 'correct' => true],
                    ['text' => 'Oral rehydration', 'correct' => false],
                    ['text' => 'Immediate blood transfusion in all cases before any crystalloid', 'correct' => false],
                    ['text' => '500 mL of crystalloid infused over 1 hour', 'correct' => false],
                    ['text' => 'A single small-bore (22G) cannula with slow infusion', 'correct' => false],
                ],
            ],
            [
                'text' => 'In PPH due to uterine atony, the first-line uterotonic is:',
                'explanation' => 'Oxytocin 10 IU IM/IV (or 10 IU in 500 mL crystalloid over 10 minutes), followed by a maintenance infusion of 20 IU in 1 L over 4 hours, is the first-line uterotonic for PPH treatment. Heat-stable carbetocin is used only for PPH prevention during AMTSL and should never be used to treat established PPH; carboprost and ergometrine are second-line/adjunct options.',
                'options' => [
                    ['text' => 'Misoprostol 600 µg rectally', 'correct' => false],
                    ['text' => 'Oxytocin 10 IU IM/IV followed by a maintenance infusion', 'correct' => true],
                    ['text' => 'Ergometrine 0.5 mg IM', 'correct' => false],
                    ['text' => 'Heat-stable carbetocin, since it can be used interchangeably with oxytocin for treatment', 'correct' => false],
                    ['text' => 'Carboprost 0.25 mg IM as first-line therapy', 'correct' => false],
                ],
            ],
        ];

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
