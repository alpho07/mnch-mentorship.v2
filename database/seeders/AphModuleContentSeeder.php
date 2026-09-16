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

class AphModuleContentSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $program = Program::where('name', 'Maternal Health (EmONC)')->firstOrFail();

            $this->seedAphQuizAndContent($program);
            $this->seedModuleVideos($program);
        });

        $this->command->info('APH quiz, case scenario, and EmONC module videos seeded successfully.');
    }

    private function seedAphQuizAndContent(Program $program): void
    {
        $aphModule = ProgramModule::where('program_id', $program->id)
            ->where('name', 'like', '%Ante Partum Hemorrhage%')
            ->whereNull('parent_id')
            ->firstOrFail();

        // Simulation drill case scenario
        ProgramModuleContent::firstOrCreate(
            [
                'program_module_id' => $aphModule->id,
                'type' => 'case_scenario',
                'title' => 'APH Simulation Drill — Case Scenario',
            ],
            [
                'content' => "PATIENT: Mary Jane, 32 years, G3P2+0 at 35 weeks.\n\nPRESENTING COMPLAINT: Sudden moderate vaginal bleeding and severe lower abdominal pain for 1 hour. Reduced fetal movements for 1 day. On treatment for chronic hypertension (labetalol 100 mg BD).\n\nANC HISTORY: 5 visits; Blood group O Rh+; Hb 10.5 g/dL. No previous APH or caesarean section.\n\nINITIAL VITAL SIGNS: BP 110/70 | Pulse 96 | RR 20 | Temp 36.8°C | FHR 100 bpm | Moderate PV bleeding.\n\nSCENARIO PROGRESSION:\n• 0–2 min: Anxious patient, asks \"Is my baby okay?\". Assess DRCAB, auscultate FHR, take focused history, call for help.\n• 2–4 min: BP 100/60, FHR 95. Left lateral tilt + O₂ 10 L/min, two large-bore IVs, blood samples (FBC/cross-match/coagulation), IV crystalloids. AVOID digital VE. Insert Foley, request OPOCUS.\n• 4–6 min: OPOCUS — fundal-posterior placenta (not low-lying), praevia excluded. FHR 90. Probable abruption. SBAR handover. Notify theatre/anaesthesia/blood bank/neonatal team.\n• 6–8 min: BP 80/50, FHR 80, minimal urine. Activate massive obstetric haemorrhage protocol, rapid fluids + blood products, obtain consent, transfer to theatre.\n• 8–10 min: BP improving (90/60 after fluids). Reassess DRCAB, confirm SBAR, complete checklist, communicate with family, document.\n\nKEY COMPETENCIES ASSESSED:\n• Early APH recognition and DRCAB stabilization\n• Avoidance of digital VE until praevia excluded\n• OPOCUS use and interpretation\n• SBAR communication and team leadership\n• Timely escalation to theatre and blood products\n• Preparation for emergency CS and neonatal resuscitation",
                'order_sequence' => 1,
                'is_active' => true,
            ]
        );

        // Pre/post quiz — unique constraint is on (program_module_id, type)
        $quiz = ProgramModuleQuiz::firstOrCreate(
            [
                'program_module_id' => $aphModule->id,
                'type' => 'both',
            ],
            [
                'title' => 'APH Knowledge Assessment (Pre-test & Post-test)',
                'description' => 'A 15-question instrument administered before and after the APH simulation drill to measure knowledge gain. The same questions are used for both the pre-test and post-test; comparing scores demonstrates learning achieved. Facilitators may shuffle question order for the post-test to reduce recall bias.',
                'pass_mark_percentage' => 70.00,
                'order_sequence' => 1,
                'is_active' => true,
            ]
        );

        $questions = [
            [
                'text' => 'A 32-year-old G3P2+0 at 35 weeks with chronic hypertension presents with sudden moderate vaginal bleeding and severe abdominal pain. Fetal movements are reduced. Which clinical feature most strongly suggests placental abruption rather than placenta praevia?',
                'explanation' => 'Abruption is characterised by painful bleeding with a tender, hypertonic uterus and a non-reassuring fetal heart rate. Painless bleeding suggests praevia; previous caesarean is a praevia risk factor.',
                'options' => [
                    ['text' => 'Painless bright-red bleeding', 'correct' => false],
                    ['text' => 'Uterine tenderness and hypertonus', 'correct' => true],
                    ['text' => 'Maternal hypotension with a normal fetal heart rate', 'correct' => false],
                    ['text' => 'History of previous caesarean section', 'correct' => false],
                    ['text' => 'Bleeding that stops spontaneously', 'correct' => false],
                ],
            ],
            [
                'text' => 'In a woman presenting with APH at 34 weeks, digital vaginal examination is:',
                'explanation' => 'Digital VE may provoke catastrophic bleeding in praevia and must be deferred until ultrasound excludes it. A speculum examination is acceptable to look for local causes.',
                'options' => [
                    ['text' => 'Mandatory to rule out cervical causes', 'correct' => false],
                    ['text' => 'Safe once placenta praevia is excluded by ultrasound', 'correct' => false],
                    ['text' => 'Contraindicated until placenta praevia is excluded', 'correct' => true],
                    ['text' => 'Only contraindicated when placenta praevia is confirmed on ultrasound', 'correct' => false],
                    ['text' => 'Recommended in all cases of suspected abruption', 'correct' => false],
                ],
            ],
            [
                'text' => 'A patient with suspected APH develops signs of hypovolaemic shock. The most appropriate initial fluid resuscitation is:',
                'explanation' => 'Insert two wide-bore cannulae and give rapid warmed crystalloid (Ringer\'s lactate or normal saline) while arranging cross-matched blood.',
                'options' => [
                    ['text' => 'A 500 ml crystalloid bolus over 30 minutes', 'correct' => false],
                    ['text' => 'Two large-bore (16G) IV lines with rapid crystalloid infusion (1–2 L)', 'correct' => true],
                    ['text' => 'Colloids as first-line replacement', 'correct' => false],
                    ['text' => 'Immediate blood transfusion without crystalloids', 'correct' => false],
                    ['text' => 'Oral rehydration only', 'correct' => false],
                ],
            ],
            [
                'text' => 'Which of the following is the strongest risk factor for placenta praevia?',
                'explanation' => 'Previous caesarean section (uterine scarring) is the strongest risk factor for praevia and also for the placenta accreta spectrum. Hypertension and smoking are more associated with abruption.',
                'options' => [
                    ['text' => 'Previous placental abruption', 'correct' => false],
                    ['text' => 'Previous caesarean section', 'correct' => true],
                    ['text' => 'Chronic hypertension', 'correct' => false],
                    ['text' => 'Smoking', 'correct' => false],
                    ['text' => 'Multiple gestation', 'correct' => false],
                ],
            ],
            [
                'text' => 'During management of major APH with suspected abruption, the target urine output should be maintained at:',
                'explanation' => 'A urine output of at least 30 ml/hour (about 0.5 ml/kg/hour) reflects adequate renal perfusion.',
                'options' => [
                    ['text' => '≥ 10 ml/hour', 'correct' => false],
                    ['text' => '≥ 100 ml/hour', 'correct' => false],
                    ['text' => '≥ 30 ml/hour', 'correct' => true],
                    ['text' => 'Any output is acceptable if the blood pressure is stable', 'correct' => false],
                    ['text' => '≥ 150 ml/hour', 'correct' => false],
                ],
            ],
            [
                'text' => 'A 36-week pregnant woman with APH has an ultrasound showing a fundal placenta with a retroplacental clot. The fetal heart rate is 85 bpm with persistent late decelerations. The most appropriate next step is:',
                'explanation' => 'Abruption with a non-reassuring fetal heart rate requires immediate delivery; with a live fetus and abnormal FHR this means emergency caesarean section.',
                'options' => [
                    ['text' => 'Expectant management with steroids', 'correct' => false],
                    ['text' => 'Magnesium sulphate for fetal neuroprotection', 'correct' => false],
                    ['text' => 'Immediate emergency caesarean section', 'correct' => true],
                    ['text' => 'Repeat ultrasound in 4 hours', 'correct' => false],
                    ['text' => 'Conservative management in HDU', 'correct' => false],
                ],
            ],
            [
                'text' => 'In APH, which finding indicates concealed haemorrhage?',
                'explanation' => 'In concealed abruption, blood is retained behind the placenta, so the woman is more shocked than the visible loss suggests, often with a tense, tender uterus.',
                'options' => [
                    ['text' => 'Heavy visible per-vaginal bleeding', 'correct' => false],
                    ['text' => 'Bradycardia in the mother', 'correct' => false],
                    ['text' => 'Painless bleeding', 'correct' => false],
                    ['text' => 'Normal maternal pulse and blood pressure', 'correct' => false],
                    ['text' => 'Maternal shock out of proportion to the visible blood loss, with a tense uterus', 'correct' => true],
                ],
            ],
            [
                'text' => 'A woman with placenta praevia at 30 weeks of gestation has a small antepartum bleed that settles, and both mother and fetus remain stable. In addition to admission and surveillance, which intervention most improves the neonatal outcome should preterm birth occur?',
                'explanation' => 'Antenatal corticosteroids accelerate fetal lung maturity and reduce neonatal respiratory distress, intraventricular haemorrhage and death. Magnesium sulphate for neuroprotection is also indicated before 34 weeks when the mother and fetus are stable.',
                'options' => [
                    ['text' => 'Antenatal corticosteroids (dexamethasone 6 mg IM 12-hourly for 4 doses) and magnesium sulphate for neuro-protection', 'correct' => true],
                    ['text' => 'Prophylactic oral antibiotics', 'correct' => false],
                    ['text' => 'Routine therapeutic anticoagulation', 'correct' => false],
                    ['text' => 'Immediate induction of labour', 'correct' => false],
                    ['text' => 'Blood transfusion to a haemoglobin of 14 g/dL', 'correct' => false],
                ],
            ],
            [
                'text' => 'A woman with APH and suspected abruption has a platelet count of 60 × 10⁹/L and a fibrinogen of 1.2 g/L. This indicates:',
                'explanation' => 'A falling platelet count with low fibrinogen (<2 g/L) reflects consumptive coagulopathy (DIC), a recognized complication of abruption; activate the massive haemorrhage protocol.',
                'options' => [
                    ['text' => 'Normal coagulation for pregnancy', 'correct' => false],
                    ['text' => 'Early disseminated intravascular coagulation (DIC)', 'correct' => true],
                    ['text' => 'Iron-deficiency anaemia', 'correct' => false],
                    ['text' => 'An effect of chronic hypertension', 'correct' => false],
                    ['text' => 'No clinical significance', 'correct' => false],
                ],
            ],
            [
                'text' => 'The most critical reason for using left lateral tilt in a woman with major APH is to:',
                'explanation' => 'From the second trimester the gravid uterus compresses the inferior vena cava and aorta when supine; left lateral tilt restores venous return and cardiac output (which secondarily improves fetal oxygenation).',
                'options' => [
                    ['text' => 'Prevent aortocaval compression and improve venous return', 'correct' => true],
                    ['text' => 'Improve fetal oxygenation directly', 'correct' => false],
                    ['text' => 'Reduce uterine tone', 'correct' => false],
                    ['text' => 'Facilitate abdominal examination', 'correct' => false],
                    ['text' => 'Prevent aspiration', 'correct' => false],
                ],
            ],
            [
                'text' => 'In a woman with placenta praevia who is stable at 33 weeks of gestation, the most appropriate management is:',
                'explanation' => 'A stable preterm woman with praevia is managed expectantly with admission, antenatal corticosteroids and surveillance, delivering at term or sooner if bleeding or fetal compromise occurs.',
                'options' => [
                    ['text' => 'Immediate induction of labour', 'correct' => false],
                    ['text' => 'Immediate caesarean section regardless of maternal or fetal condition', 'correct' => false],
                    ['text' => 'Expectant management with admission, corticosteroids and fetal surveillance', 'correct' => true],
                    ['text' => 'Artificial rupture of membranes', 'correct' => false],
                    ['text' => 'Discharge home to return at 38 weeks', 'correct' => false],
                ],
            ],
            [
                'text' => 'Which of the following best defines antepartum haemorrhage?',
                'explanation' => 'APH is defined as bleeding from the genital tract after 24 weeks of gestation and before delivery of the baby.',
                'options' => [
                    ['text' => 'Bleeding from the genital tract after 20 weeks and before labour', 'correct' => false],
                    ['text' => 'Bleeding from the genital tract after 24 weeks of gestation and before delivery', 'correct' => true],
                    ['text' => 'Any vaginal bleeding occurring during pregnancy', 'correct' => false],
                    ['text' => 'Bleeding occurring after the onset of labour but before placental delivery', 'correct' => false],
                    ['text' => 'Bleeding from the genital tract after 28 weeks', 'correct' => false],
                ],
            ],
            [
                'text' => 'A woman at 32 weeks of gestation presents with APH. Before ultrasound has excluded placenta praevia, the healthcare provider should:',
                'explanation' => 'A speculum examination can identify lower genital tract causes and is permissible; digital and bimanual examinations are deferred until praevia is excluded.',
                'options' => [
                    ['text' => 'Perform a digital vaginal examination to assess cervical dilatation', 'correct' => false],
                    ['text' => 'Perform a bimanual pelvic examination', 'correct' => false],
                    ['text' => 'Perform a speculum examination but avoid digital vaginal examination', 'correct' => true],
                    ['text' => 'Immediately induce labour', 'correct' => false],
                    ['text' => 'Perform a speculum examination then later perform a digital examination', 'correct' => false],
                ],
            ],
            [
                'text' => 'Which of the following is NOT an indication for immediate delivery in APH?',
                'explanation' => 'A stable mother at 34 weeks with mild, settling bleeding may be managed expectantly with steroids and surveillance; the other options all mandate immediate delivery.',
                'options' => [
                    ['text' => 'Maternal haemodynamic instability despite resuscitation', 'correct' => false],
                    ['text' => 'Non-reassuring fetal status', 'correct' => false],
                    ['text' => 'Gestational age of 34 weeks with mild bleeding and a stable mother', 'correct' => true],
                    ['text' => 'Suspected uterine rupture', 'correct' => false],
                    ['text' => 'Massive concealed abruption with coagulopathy', 'correct' => false],
                ],
            ],
            [
                'text' => 'During an APH simulation drill, the team fails to call for help early and delays blood sampling. The most important learning point from the debrief should focus on:',
                'explanation' => 'Debriefs are non-punitive and systems-focused; the key lesson is early escalation, defined leadership and timely activation of the haemorrhage protocol.',
                'options' => [
                    ['text' => 'Individual blame for the delay', 'correct' => false],
                    ['text' => 'The importance of an early "shout for help", clear leadership and activation of the massive obstetric haemorrhage protocol', 'correct' => true],
                    ['text' => 'The need for a better ultrasound machine', 'correct' => false],
                    ['text' => 'The importance of documenting everything first', 'correct' => false],
                    ['text' => 'Changing the simulation scenario to avoid the issue', 'correct' => false],
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

    private function seedModuleVideos(Program $program): void
    {
        // [module name fragment => videos to attach]
        $moduleVideos = [
            'Ante Partum Hemorrhage' => [
                ['title' => 'Respectful Maternity Care', 'url' => 'https://www.youtube.com/watch?v=pn9LsxQqr94'],
            ],
            'Active Management of the Third Stage' => [
                ['title' => 'Active Management of Third Stage of Labour', 'url' => 'https://www.youtube.com/watch?v=_JgOjTdAW-M'],
            ],
            'Vaginal Breech Delivery' => [
                ['title' => 'Vaginal Breech Delivery', 'url' => 'https://www.youtube.com/watch?v=iuH6bSjGBq4'],
            ],
            'Shoulder Dystocia' => [
                ['title' => 'Shoulder Dystocia Management', 'url' => 'https://www.youtube.com/watch?v=VIyHZyij0Bg'],
            ],
            'Vaginal Vacuum Assisted Delivery' => [
                ['title' => 'Assisted Vaginal Delivery', 'url' => 'https://www.youtube.com/watch?v=dnDiRrQqRJA'],
            ],
            'Immediate Neonatal Resuscitation' => [
                ['title' => 'Newborn — First Hours After Birth', 'url' => 'https://www.youtube.com/watch?v=uMcgJR8ESRc'],
            ],
            'Management of Pre-Eclampsia' => [
                ['title' => 'Pre-Eclampsia and Eclampsia Management', 'url' => 'https://www.youtube.com/watch?v=vMw5CqVFrWs'],
            ],
            'Obstructed Labour' => [
                ['title' => 'Obstructed and Prolonged Labour', 'url' => 'https://www.youtube.com/watch?v=eM7P3GsxDF8'],
            ],
            'Partograph Use' => [
                ['title' => 'Labour Care Guide — Partograph and Monitoring', 'url' => 'https://www.youtube.com/watch?v=4TSVQtIx-ZU'],
            ],
        ];

        foreach ($moduleVideos as $nameFragment => $videos) {
            $module = ProgramModule::where('program_id', $program->id)
                ->where('name', 'like', "%{$nameFragment}%")
                ->whereNull('parent_id')
                ->first();

            if (! $module) {
                $this->command->warn("Module not found: {$nameFragment}");
                continue;
            }

            foreach ($videos as $seq => $video) {
                ProgramModuleContent::firstOrCreate(
                    [
                        'program_module_id' => $module->id,
                        'type' => 'video',
                        'title' => $video['title'],
                    ],
                    [
                        'video_url' => $video['url'],
                        'order_sequence' => $seq + 1,
                        'is_active' => true,
                    ]
                );
            }
        }

        // PPH track videos — keyed by track name fragment
        $pphTrackVideos = [
            'Bimanual compression of the uterus' => [
                ['title' => 'Bimanual Uterine Compression', 'url' => 'https://youtu.be/eiTslknDtcY'],
            ],
            'Compression of abdominal aorta' => [
                ['title' => 'Abdominal Aortic Compression', 'url' => 'https://youtu.be/rc9BYcIhamA'],
            ],
            'Removal of retained placenta' => [
                ['title' => 'Manual Removal of Placenta', 'url' => 'https://youtu.be/uYcchuIFdOo'],
            ],
            'intrauterine balloon tamponade (condom tamponade)' => [
                ['title' => 'Uterine Balloon Tamponade', 'url' => 'https://www.youtube.com/watch?v=w96M1nguUk0'],
            ],
            'intrauterine balloon tamponade (free flow system)' => [
                ['title' => 'Uterine Balloon Tamponade — Free Flow System', 'url' => 'https://youtu.be/N7pt6M78l3A'],
            ],
            'Repair of perineal tear' => [
                ['title' => '1st and 2nd Degree Perineal Tear Repair', 'url' => 'https://www.youtube.com/watch?v=m5Vm8ZT24HM'],
                ['title' => '3rd Degree Perineal Tear Repair', 'url' => 'https://www.youtube.com/watch?v=oZW2KSuD800'],
            ],
            'Repair of cervical tear' => [
                ['title' => 'Cervical Tear Repair', 'url' => 'https://www.youtube.com/watch?v=ldPK3vpoexs'],
            ],
            'B-Lynch suture' => [
                ['title' => 'Placement of the B-Lynch Suture', 'url' => 'https://www.youtube.com/watch?v=KkWEuvJGY7o'],
            ],
        ];

        $pphModule = ProgramModule::where('program_id', $program->id)
            ->where('name', 'like', '%Management of Postpartum Hemorrhage%')
            ->whereNull('parent_id')
            ->first();

        if (! $pphModule) {
            $this->command->warn('PPH module not found; skipping PPH track videos.');
            return;
        }

        foreach ($pphTrackVideos as $trackFragment => $videos) {
            $track = ProgramModule::where('program_id', $program->id)
                ->where('parent_id', $pphModule->id)
                ->where('name', 'like', "%{$trackFragment}%")
                ->first();

            if (! $track) {
                $this->command->warn("PPH track not found: {$trackFragment}");
                continue;
            }

            foreach ($videos as $seq => $video) {
                ProgramModuleContent::firstOrCreate(
                    [
                        'program_module_id' => $track->id,
                        'type' => 'video',
                        'title' => $video['title'],
                    ],
                    [
                        'video_url' => $video['url'],
                        'order_sequence' => $seq + 1,
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
