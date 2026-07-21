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
 * Seeds introductions, expected learning outcomes, objectives/content, and
 * pre/post quizzes for the first batch of 6 PPH procedural tracks:
 *   1. Bimanual Compression of the Uterus
 *   2. Compression of the Abdominal Aorta
 *   3. Removal of Retained Placenta
 *   4. Uterine Inversion
 *   5. Intrauterine Balloon Tamponade — Condom/Foley variant
 *   6. Intrauterine Balloon Tamponade — Free Flow System (FFS) variant
 *
 * Content is drawn from the EmONC Mentee Manual, Second Edition (2024),
 * Module 5 (Management of Postpartum Hemorrhage), Tracks I–VI, which carry
 * the detailed step-by-step technique for each skill. ModuleRubric rows for
 * these tracks already exist and are intentionally left untouched here.
 */
class EmoncPphTracksBatch1Seeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $program = Program::where('name', 'Maternal Health (EmONC)')->firstOrFail();

            $pphModule = ProgramModule::where('program_id', $program->id)
                ->where('name', 'like', '%Postpartum Hemorrhage%')
                ->whereNull('parent_id')
                ->firstOrFail();

            foreach ($this->trackDefinitions() as $definition) {
                $track = ProgramModule::where('parent_id', $pphModule->id)
                    ->where('name', 'like', "%{$definition['fragment']}%")
                    ->firstOrFail();

                $this->seedTrack($track, $definition);
            }
        });

        $this->command->info('EmONC PPH Tracks Batch 1 (6 tracks) content and quizzes seeded successfully.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function trackDefinitions(): array
    {
        return [
            $this->bimanualCompressionDefinition(),
            $this->abdominalAortaDefinition(),
            $this->retainedPlacentaDefinition(),
            $this->uterineInversionDefinition(),
            $this->condomTamponadeDefinition(),
            $this->freeFlowSystemDefinition(),
        ];
    }

    private function seedTrack(ProgramModule $track, array $definition): void
    {
        // Skip if case_scenario already missing safety check per instructions — verify it exists (should already be seeded elsewhere).
        $hasCaseScenario = ProgramModuleContent::where('program_module_id', $track->id)
            ->where('type', 'case_scenario')
            ->exists();

        if (! $hasCaseScenario) {
            $this->command->warn("Case scenario not found for track: {$track->name} (expected to already exist; skipping creation as instructed).");
        }

        ProgramModuleContent::firstOrCreate(
            [
                'program_module_id' => $track->id,
                'type' => 'introduction',
                'title' => $definition['introduction_title'],
            ],
            [
                'content' => $definition['introduction_content'],
                'order_sequence' => 1,
                'is_active' => true,
            ]
        );

        ProgramModuleContent::firstOrCreate(
            [
                'program_module_id' => $track->id,
                'type' => 'expected_learning_outcome',
                'title' => $definition['outcome_title'],
            ],
            [
                'content' => $definition['outcome_content'],
                'order_sequence' => 2,
                'is_active' => true,
            ]
        );

        $track->update([
            'objectives' => $definition['objectives'],
            'content' => $definition['content'],
        ]);

        $quiz = ProgramModuleQuiz::firstOrCreate(
            [
                'program_module_id' => $track->id,
                'type' => 'both',
            ],
            [
                'title' => $definition['quiz_title'],
                'description' => $definition['quiz_description'],
                'pass_mark_percentage' => 70.00,
                'order_sequence' => 1,
                'is_active' => true,
            ]
        );

        foreach ($definition['questions'] as $seq => $q) {
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

    // =====================================================================
    // TRACK 1: Bimanual Compression of the Uterus
    // =====================================================================
    private function bimanualCompressionDefinition(): array
    {
        return [
            'fragment' => 'Bimanual compression',
            'introduction_title' => 'Bimanual Compression of the Uterus — Procedure',
            'introduction_content' => "Bimanual compression of the uterus is an emergency measure used in refractory postpartum hemorrhage (PPH) when uterine atony persists despite uterine massage and first-line uterotonics (the MOTIVE/First Response Bundle). It works by physically compressing the uterus between two hands to control bleeding while other resuscitative and pharmacological measures continue.\n\nSTEP-BY-STEP PROCEDURE:\n1. Shout for help and assemble the emergency team.\n2. Briefly explain the procedure to the mother and obtain informed consent.\n3. Perform hand hygiene and put on gynecological (elbow-length) gloves — double-glove.\n4. Perform a vaginal examination.\n5. Insert the whole hand into the vagina and, with the hand in the vagina, identify the anterior fornix.\n6. Form a fist with the internal hand.\n7. Place the fist into the anterior fornix, pressing it against the anterior wall of the uterus.\n8. With the other hand on the suprapubic area, press deeply into the abdomen behind the uterus, applying pressure against the posterior wall of the uterus (suprapubic pressure) — the uterus is thereby compressed between the internal fist and the external hand.\n9. Maintain firm compression for at least 5 minutes, until the bleeding is controlled and the uterus contracts, while continuing with other PPH interventions (uterotonics, IV fluids, blood products as indicated) — do not stop other resuscitative measures while performing this procedure.\n10. Reassess: if bleeding is controlled and the uterus is well contracted, gradually release compression while observing for re-bleeding. If bleeding is not controlled, continue compression and escalate (e.g. abdominal aortic compression, uterine balloon tamponade, or transfer for surgical management).\n11. Explain to the mother the results of the procedure and next steps, and document blood loss and findings.\n\nThis is a bridging/temporizing skill — it buys time to organize definitive management (transport, theatre, blood products) and should never delay escalation if bleeding is not controlled.",
            'outcome_title' => 'Bimanual Compression of the Uterus — Expected Learning Outcome',
            'outcome_content' => 'By the end of this track, the mentee should be able to independently recognize when bimanual uterine compression is indicated for refractory uterine atony, correctly position both hands (an internal fist in the anterior fornix against the anterior uterine wall, and an external hand compressing the posterior uterine wall suprapubically), sustain compression safely for at least 5 minutes while continuing other first-response PPH interventions, and communicate findings, results, and next steps clearly to the mother and the wider team.',
            'objectives' => [
                'Identify uterine atony refractory to massage and first-line uterotonics as the indication for bimanual compression.',
                'Demonstrate correct hand positioning: internal fist in the anterior fornix and external hand on the posterior uterine wall via the suprapubic area.',
                'Maintain effective bimanual compression for a minimum of 5 minutes while continuing other PPH management steps.',
                'Recognize signs that compression is effective (bleeding controlled, uterus contracting) versus signs requiring escalation.',
                'Communicate the procedure, consent, findings, and next steps clearly to the mother and document blood loss.',
            ],
            'content' => [
                ['label' => 'Video & Mentor Demonstration', 'duration' => '15 min'],
                ['label' => 'Return Demonstration', 'duration' => '10 min'],
                ['label' => 'Assessment Checklist & Debrief', 'duration' => '10 min'],
            ],
            'quiz_title' => 'Bimanual Compression of the Uterus — Knowledge Assessment (Pre-test & Post-test)',
            'quiz_description' => 'A 10-question instrument administered before and after the bimanual compression skill session to measure knowledge gain on indications, technique, and safe duration of the procedure.',
            'questions' => [
                [
                    'text' => 'Bimanual compression of the uterus is indicated when:',
                    'explanation' => 'Bimanual compression is an escalation step used when uterine atony persists despite uterine massage and first-line uterotonics as part of the PPH First Response Bundle.',
                    'options' => [
                        ['text' => 'As the very first action taken for any postpartum bleeding', 'correct' => false],
                        ['text' => 'The uterus remains atonic and bleeding continues despite massage and uterotonics', 'correct' => true],
                        ['text' => 'Only after a hysterectomy has been performed', 'correct' => false],
                        ['text' => 'When the placenta has not yet been delivered', 'correct' => false],
                        ['text' => 'Routinely during every third stage of labour', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'When performing bimanual compression, where should the internal (vaginal) fist be placed?',
                    'explanation' => 'The whole hand is inserted into the vagina, the anterior fornix is identified, and a fist is formed and placed against the anterior wall of the uterus at the anterior fornix.',
                    'options' => [
                        ['text' => 'Against the cervix only, without entering the vagina fully', 'correct' => false],
                        ['text' => 'In the anterior fornix, pressed against the anterior uterine wall', 'correct' => true],
                        ['text' => 'In the posterior fornix only', 'correct' => false],
                        ['text' => 'Outside the introitus, compressing the perineum', 'correct' => false],
                        ['text' => 'Inside the rectum', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'What is the role of the external (abdominal) hand during bimanual compression?',
                    'explanation' => 'The external hand presses deeply into the suprapubic area/abdomen behind the uterus, applying pressure against the posterior uterine wall so the uterus is compressed between both hands.',
                    'options' => [
                        ['text' => 'It rests lightly on the abdomen without applying pressure', 'correct' => false],
                        ['text' => 'It presses deeply on the suprapubic area against the posterior uterine wall', 'correct' => true],
                        ['text' => 'It is used only to auscultate fetal heart tones', 'correct' => false],
                        ['text' => 'It compresses the femoral artery', 'correct' => false],
                        ['text' => 'It stabilises the cervix with forceps', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'For how long should bimanual compression be maintained?',
                    'explanation' => 'Compression should be maintained for at least 5 minutes, until bleeding is controlled and the uterus contracts, while other PPH interventions continue.',
                    'options' => [
                        ['text' => 'At least 30 seconds', 'correct' => false],
                        ['text' => 'At least 5 minutes, until bleeding is controlled and the uterus contracts', 'correct' => true],
                        ['text' => 'Exactly 1 minute regardless of response', 'correct' => false],
                        ['text' => 'Only until the mentor arrives', 'correct' => false],
                        ['text' => 'There is no minimum duration', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'Which infection-prevention step is required before performing bimanual compression?',
                    'explanation' => 'Hand hygiene must be performed and gynecological (elbow-length) gloves worn, with double gloving, before inserting the hand into the vagina.',
                    'options' => [
                        ['text' => 'Hand hygiene and double gloving with gynecological gloves', 'correct' => true],
                        ['text' => 'Sterile gown only, gloves are optional', 'correct' => false],
                        ['text' => 'No special precautions are needed for this procedure', 'correct' => false],
                        ['text' => 'A single pair of examination gloves is always sufficient', 'correct' => false],
                        ['text' => 'Face shield only', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'While bimanual compression is being performed, other PPH interventions such as uterotonics and IV fluids should:',
                    'explanation' => 'Bimanual compression is performed alongside, not instead of, other first-response PPH interventions; these should continue throughout.',
                    'options' => [
                        ['text' => 'Be stopped until compression is complete', 'correct' => false],
                        ['text' => 'Continue simultaneously with the compression', 'correct' => true],
                        ['text' => 'Be postponed until the woman reaches theatre', 'correct' => false],
                        ['text' => 'Only be given if compression fails after 30 minutes', 'correct' => false],
                        ['text' => 'Be replaced entirely by the compression technique', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'Which finding indicates that bimanual compression is being effective?',
                    'explanation' => 'Effective compression is indicated by controlled bleeding and a uterus that is becoming firm/contracted.',
                    'options' => [
                        ['text' => 'The mother reports less abdominal pain', 'correct' => false],
                        ['text' => 'Bleeding is controlled and the uterus becomes firm/contracted', 'correct' => true],
                        ['text' => 'The fetal heart rate normalises', 'correct' => false],
                        ['text' => 'The mother\'s blood pressure rises above baseline', 'correct' => false],
                        ['text' => 'The placenta separates spontaneously', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'Before beginning bimanual compression, the provider should:',
                    'explanation' => 'As with any procedure, the mother should have the procedure briefly explained and informed consent obtained before proceeding, alongside shouting for help.',
                    'options' => [
                        ['text' => 'Proceed immediately without any explanation to save time', 'correct' => false],
                        ['text' => 'Shout for help, briefly explain the procedure, and obtain informed consent', 'correct' => true],
                        ['text' => 'Sedate the mother fully before starting', 'correct' => false],
                        ['text' => 'Wait for written consent from a family member only', 'correct' => false],
                        ['text' => 'Perform the vaginal examination only after compression is finished', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'If bimanual compression fails to control bleeding after an adequate attempt, the next appropriate step is to:',
                    'explanation' => 'Bimanual compression is a temporizing measure; if ineffective, escalate to further interventions such as aortic compression, uterine balloon tamponade, or transfer for surgical/theatre management, without delay.',
                    'options' => [
                        ['text' => 'Repeat the same compression indefinitely without escalating', 'correct' => false],
                        ['text' => 'Escalate to the next-line intervention (e.g. aortic compression, balloon tamponade, or theatre) without delay', 'correct' => true],
                        ['text' => 'Discharge the mother for outpatient follow-up', 'correct' => false],
                        ['text' => 'Stop all uterotonics', 'correct' => false],
                        ['text' => 'Wait 24 hours before further action', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'After completing bimanual compression, the provider should:',
                    'explanation' => 'The final steps are to explain the results of the procedure and next steps to the mother, and to document blood loss and findings.',
                    'options' => [
                        ['text' => 'Discharge the mother immediately', 'correct' => false],
                        ['text' => 'Explain the results and next steps to the mother and document findings', 'correct' => true],
                        ['text' => 'Avoid discussing the procedure with the mother', 'correct' => false],
                        ['text' => 'Remove all IV access immediately', 'correct' => false],
                        ['text' => 'Withhold documentation until the next shift', 'correct' => false],
                    ],
                ],
            ],
        ];
    }

    // =====================================================================
    // TRACK 2: Compression of the Abdominal Aorta
    // =====================================================================
    private function abdominalAortaDefinition(): array
    {
        return [
            'fragment' => 'Compression of abdominal aorta',
            'introduction_title' => 'Compression of the Abdominal Aorta — Procedure',
            'introduction_content' => "Compression of the abdominal aorta is a temporizing, non-invasive technique used in refractory PPH to reduce blood flow to the pelvis while other resuscitative measures and definitive interventions (uterotonics, blood products, transfer to theatre) are organized. It is not a definitive treatment and must not delay escalation.\n\nSTEP-BY-STEP PROCEDURE:\n1. Shout for help.\n2. Briefly explain the procedure to the mother and obtain consent.\n3. Place the calibrated blood collection drape to allow ongoing quantification of blood loss.\n4. Locate the femoral pulse before starting, to have a baseline for comparison.\n5. Place a closed fist above the umbilicus, slightly to the patient's left of the midline (over the aorta, which lies slightly left of the midline at this level).\n6. Apply firm downward pressure through the abdominal wall onto the abdominal aorta.\n7. With the other hand, palpate the femoral pulse to check the adequacy of compression — an adequately compressed aorta should cause the femoral pulse to diminish or disappear.\n8. Comment on whether the femoral pulse is present or absent, and adjust the position/pressure of the fist as needed to achieve adequate compression.\n9. Maintain the compression continuously until the bleeding is controlled or the patient reaches the operating table for definitive surgical management.\n10. Continue to measure blood loss throughout.\n11. Explain the results of the procedure and next steps to the mother, and document the findings.\n\nBecause this technique only reduces flow rather than stopping the underlying cause of bleeding, it must always be paired with continuing resuscitation (IV fluids, uterotonics, blood products) and rapid preparation for definitive management.",
            'outcome_title' => 'Compression of the Abdominal Aorta — Expected Learning Outcome',
            'outcome_content' => 'By the end of this track, the mentee should be able to correctly locate the anatomical landmark for abdominal aortic compression (a closed fist above the umbilicus, slightly left of the midline), apply and confirm adequate downward compression using the femoral pulse as a check, sustain the compression safely as a bridging measure until bleeding is controlled or the woman reaches theatre, and recognize that this technique is temporizing and must be combined with continued resuscitation and escalation.',
            'objectives' => [
                'Identify refractory PPH as the indication for abdominal aortic compression as a bridging/temporizing measure.',
                'Locate the correct anatomical landmark (closed fist above the umbilicus, slightly left of midline) for compression.',
                'Confirm adequacy of compression by palpating the femoral pulse for diminished or absent pulsation.',
                'Maintain compression safely until bleeding is controlled or the woman reaches the operating table.',
                'Recognize this is a temporizing measure that must be paired with continued resuscitation and rapid escalation to definitive care.',
            ],
            'content' => [
                ['label' => 'Video & Mentor Demonstration', 'duration' => '15 min'],
                ['label' => 'Return Demonstration', 'duration' => '10 min'],
                ['label' => 'Assessment Checklist & Debrief', 'duration' => '10 min'],
            ],
            'quiz_title' => 'Compression of the Abdominal Aorta — Knowledge Assessment (Pre-test & Post-test)',
            'quiz_description' => 'A 10-question instrument administered before and after the abdominal aortic compression skill session to measure knowledge gain on landmark identification, technique, and confirmation of adequacy.',
            'questions' => [
                [
                    'text' => 'Where should the closed fist be placed to compress the abdominal aorta?',
                    'explanation' => 'The closed fist is placed above the umbilicus, slightly to the patient\'s left of the midline, corresponding to the position of the abdominal aorta at this level.',
                    'options' => [
                        ['text' => 'Below the umbilicus, over the pubic symphysis', 'correct' => false],
                        ['text' => 'Above the umbilicus, slightly to the patient\'s left of midline', 'correct' => true],
                        ['text' => 'Directly over the right iliac fossa', 'correct' => false],
                        ['text' => 'Over the xiphisternum', 'correct' => false],
                        ['text' => 'Over the left flank', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'How is the adequacy of abdominal aortic compression confirmed?',
                    'explanation' => 'The femoral pulse is palpated with the other hand; adequate compression should cause the femoral pulse to diminish or disappear.',
                    'options' => [
                        ['text' => 'By checking the mother\'s reported pain level', 'correct' => false],
                        ['text' => 'By palpating the femoral pulse, which should diminish or disappear if compression is adequate', 'correct' => true],
                        ['text' => 'By auscultating the fetal heart rate', 'correct' => false],
                        ['text' => 'By measuring the mother\'s temperature', 'correct' => false],
                        ['text' => 'By checking capillary refill on the hand', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'The direction of pressure applied during abdominal aortic compression is:',
                    'explanation' => 'Firm downward pressure is applied through the abdominal wall onto the abdominal aorta.',
                    'options' => [
                        ['text' => 'Upward, toward the diaphragm', 'correct' => false],
                        ['text' => 'Downward, through the abdominal wall onto the aorta', 'correct' => true],
                        ['text' => 'Lateral, toward the flank', 'correct' => false],
                        ['text' => 'Circular massaging motion only', 'correct' => false],
                        ['text' => 'No pressure is applied; the fist rests passively', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'What must be placed before starting abdominal aortic compression, to allow ongoing assessment?',
                    'explanation' => 'A calibrated blood collection drape should be placed so blood loss can continue to be quantified during the procedure.',
                    'options' => [
                        ['text' => 'A calibrated blood collection drape', 'correct' => true],
                        ['text' => 'A cervical tenaculum', 'correct' => false],
                        ['text' => 'An NASG segment 6', 'correct' => false],
                        ['text' => 'A speculum', 'correct' => false],
                        ['text' => 'A perineal repair tray', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'How long should abdominal aortic compression be maintained?',
                    'explanation' => 'Compression is maintained continuously until the bleeding is controlled or the patient reaches the operating table for definitive management.',
                    'options' => [
                        ['text' => 'For exactly 2 minutes only', 'correct' => false],
                        ['text' => 'Until bleeding is controlled or the patient reaches the operating table', 'correct' => true],
                        ['text' => 'Until the mentor signs the checklist', 'correct' => false],
                        ['text' => 'For 30 seconds, then stopped regardless of response', 'correct' => false],
                        ['text' => 'Only while awaiting blood cross-match results', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'Abdominal aortic compression is best described as:',
                    'explanation' => 'It is a temporizing/bridging measure that reduces pelvic blood flow while definitive management is organized; it does not treat the underlying cause of bleeding.',
                    'options' => [
                        ['text' => 'A definitive, curative treatment for PPH', 'correct' => false],
                        ['text' => 'A temporizing bridge to definitive management while reducing pelvic blood flow', 'correct' => true],
                        ['text' => 'A substitute for uterotonic drugs', 'correct' => false],
                        ['text' => 'A first-line treatment used before uterine massage', 'correct' => false],
                        ['text' => 'Only appropriate after hysterectomy', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'If the femoral pulse remains strongly palpable while attempting compression, this suggests:',
                    'explanation' => 'A strongly palpable femoral pulse during the attempt indicates the compression is inadequate and the fist position/pressure should be adjusted.',
                    'options' => [
                        ['text' => 'The compression is adequate and should be maintained as is', 'correct' => false],
                        ['text' => 'The compression is inadequate and the position or pressure should be adjusted', 'correct' => true],
                        ['text' => 'The aorta has been fully occluded', 'correct' => false],
                        ['text' => 'The procedure should be abandoned entirely', 'correct' => false],
                        ['text' => 'The mother is in labour', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'Before beginning the procedure, the provider should first:',
                    'explanation' => 'Shouting for help and explaining the procedure to obtain consent are the first steps, along with locating the femoral pulse as a baseline before compression starts.',
                    'options' => [
                        ['text' => 'Sedate the mother heavily', 'correct' => false],
                        ['text' => 'Shout for help, explain the procedure, obtain consent, and locate the baseline femoral pulse', 'correct' => true],
                        ['text' => 'Perform the procedure silently without informing the mother', 'correct' => false],
                        ['text' => 'Wait for the laboratory results before doing anything', 'correct' => false],
                        ['text' => 'Insert a Foley catheter first, before any other step', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'What should be measured and documented throughout abdominal aortic compression?',
                    'explanation' => 'Blood loss should continue to be measured and results documented, along with explaining findings and next steps to the mother.',
                    'options' => [
                        ['text' => 'Only the time the procedure started', 'correct' => false],
                        ['text' => 'Blood loss, with results explained to the mother and documented', 'correct' => true],
                        ['text' => 'The mother\'s dietary intake', 'correct' => false],
                        ['text' => 'The partner\'s presence in the room', 'correct' => false],
                        ['text' => 'Nothing needs to be documented during an emergency procedure', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'While abdominal aortic compression is ongoing, other PPH management (uterotonics, IV access, blood products) should:',
                    'explanation' => 'Aortic compression is a bridging measure performed alongside continued resuscitation and other PPH management, not a replacement for it.',
                    'options' => [
                        ['text' => 'Be paused until compression ends', 'correct' => false],
                        ['text' => 'Continue simultaneously and be organized rapidly for escalation', 'correct' => true],
                        ['text' => 'Be discontinued permanently', 'correct' => false],
                        ['text' => 'Only start once the mother reaches the operating table', 'correct' => false],
                        ['text' => 'Be delayed until the femoral pulse study is complete', 'correct' => false],
                    ],
                ],
            ],
        ];
    }

    // =====================================================================
    // TRACK 3: Removal of Retained Placenta
    // =====================================================================
    private function retainedPlacentaDefinition(): array
    {
        return [
            'fragment' => 'Removal of retained placenta',
            'introduction_title' => 'Manual Removal of Retained Placenta — Procedure',
            'introduction_content' => "Retained placenta is managed by manual removal when the placenta has not delivered despite appropriate active management of the third stage (controlled cord traction, an additional 10 IU oxytocin dose if oxytocin was the initial uterotonic, and encouragement of bladder emptying), and bleeding develops or the delay is prolonged.\n\nSTEP-BY-STEP PROCEDURE:\n1. Ensure privacy.\n2. Explain the procedure to the mother and obtain informed consent.\n3. Place a blood-loss measuring (calibrated) drape.\n4. Insert large-bore IV line(s) if not already in place; take blood samples and run IV fluids.\n5. Perform hand hygiene and put on sterile gloves; insert a Foley catheter and empty the bladder.\n6. Administer analgesia and antibiotic prophylaxis: Diazepam 10 mg IM/IV (if the woman is not in shock); plus Ampicillin 2 g IV, OR Cefazolin 1 g IV, OR Ceftriaxone 2 g IV plus Metronidazole 500 mg IV.\n7. Perform hand hygiene, wear full PPE, and put on gynecological (elbow-length) gloves.\n8. Hold the umbilical cord with a clamp and gently pull, using the cord to guide the other hand into the uterus.\n9. Place the fingers of one hand into the uterus, follow the cord to locate the placenta and identify its edge.\n10. Identify the plane behind the placenta and carefully separate it from the uterine wall using a smooth shearing/sweeping motion of the fingers, back and forth, while the other hand stabilizes the uterine fundus abdominally, providing counter-traction.\n11. Withdraw the hand, bringing the placenta with it, while continuing to provide abdominal counter-traction.\n12. Once the placenta is out, check uterine tone and massage if the uterus is soft.\n13. Examine the placenta for completeness.\n14. Perform exploration of the uterine cavity for any retained fragments.\n15. Remove any retained fragments by hand, using ovum forceps or a wide curette.\n16. Examine the cervix, vagina, and perineum for tears and repair accordingly.\n17. Give oxytocin 20 IU in 1 litre normal saline at 60 drops/minute.\n18. Monitor blood pressure, pulse, and uterine tone every 15 minutes for 2 hours after the placenta is out, then every 30 minutes until 6 hours postpartum.\n19. Explain the results of the procedure to the mother.\n20. If the placenta remains adherent despite the manual attempt at removal, DO NOT force further removal — escalate: manual removal in theatre under anesthesia, laparotomy, or (in extreme cases) subtotal hysterectomy.\n21. Document the blood loss and procedure on the blood-loss monitoring chart.",
            'outcome_title' => 'Manual Removal of Retained Placenta — Expected Learning Outcome',
            'outcome_content' => 'By the end of this track, the mentee should be able to recognize retained placenta requiring manual removal, prepare the woman appropriately (consent, IV access, catheterization, analgesia, and antibiotic prophylaxis), perform manual removal using the correct shearing technique with abdominal counter-traction, manage retained fragments and genital tract trauma, administer the correct post-procedure uterotonic and monitoring schedule, and recognize and safely escalate a case of adherent placenta rather than attempting forceful removal.',
            'objectives' => [
                'Recognize the indications and pre-procedure preparation required for manual removal of a retained placenta.',
                'Administer correct analgesia (diazepam) and antibiotic prophylaxis before the procedure.',
                'Perform the manual removal technique correctly, including cord-guided hand insertion, shearing separation, and abdominal counter-traction.',
                'Manage retained placental fragments and identify and repair associated genital tract trauma.',
                'Administer the correct post-procedure oxytocin infusion and monitoring schedule, and recognize when an adherent placenta requires escalation to theatre rather than continued manual attempts.',
            ],
            'content' => [
                ['label' => 'Video & Mentor Demonstration', 'duration' => '15 min'],
                ['label' => 'Return Demonstration', 'duration' => '15 min'],
                ['label' => 'Assessment Checklist & Debrief', 'duration' => '10 min'],
            ],
            'quiz_title' => 'Removal of Retained Placenta — Knowledge Assessment (Pre-test & Post-test)',
            'quiz_description' => 'A 10-question instrument administered before and after the manual placenta removal skill session to measure knowledge gain on preparation, technique, and post-procedure management.',
            'questions' => [
                [
                    'text' => 'Which analgesic is given before manual removal of the placenta, provided the woman is not in shock?',
                    'explanation' => 'Diazepam 10 mg IM/IV is given for analgesia/sedation before the procedure, provided the woman is not in shock.',
                    'options' => [
                        ['text' => 'Morphine 10 mg IV', 'correct' => false],
                        ['text' => 'Diazepam 10 mg IM/IV', 'correct' => true],
                        ['text' => 'Paracetamol 1 g PO', 'correct' => false],
                        ['text' => 'Ketamine 100 mg IM', 'correct' => false],
                        ['text' => 'No analgesia is required', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'Which of the following is an acceptable antibiotic prophylaxis regimen before manual placenta removal?',
                    'explanation' => 'Acceptable regimens include Ampicillin 2 g IV, or Cefazolin 1 g IV, or Ceftriaxone 2 g IV plus Metronidazole 500 mg IV.',
                    'options' => [
                        ['text' => 'Ceftriaxone 2 g IV plus Metronidazole 500 mg IV', 'correct' => true],
                        ['text' => 'Oral amoxicillin only, after the procedure', 'correct' => false],
                        ['text' => 'No antibiotics are indicated for this procedure', 'correct' => false],
                        ['text' => 'Erythromycin 250 mg PO', 'correct' => false],
                        ['text' => 'Doxycycline 100 mg PO', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'What technique is used to separate the placenta from the uterine wall during manual removal?',
                    'explanation' => 'The fingers identify the plane behind the placenta and use a smooth shearing/sweeping motion, back and forth, to separate it from the uterine wall, while the other hand stabilizes the fundus abdominally.',
                    'options' => [
                        ['text' => 'A single sharp pull on the umbilical cord alone', 'correct' => false],
                        ['text' => 'A smooth shearing/sweeping motion of the fingers behind the placenta, with abdominal counter-traction', 'correct' => true],
                        ['text' => 'Curettage of the entire uterine cavity before touching the placenta', 'correct' => false],
                        ['text' => 'Firm fundal pressure alone, without internal manipulation', 'correct' => false],
                        ['text' => 'Injection of oxytocin directly into the placental bed before separation', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'How is the internal hand guided into the uterus during manual removal?',
                    'explanation' => 'The umbilical cord is held with a clamp and gently pulled, using it to guide the other hand into the uterus, then the fingers follow the cord to locate the placenta.',
                    'options' => [
                        ['text' => 'By following the umbilical cord, held with a clamp, into the uterus', 'correct' => true],
                        ['text' => 'By blind insertion without any cord guidance', 'correct' => false],
                        ['text' => 'Via ultrasound guidance only', 'correct' => false],
                        ['text' => 'Through an abdominal incision', 'correct' => false],
                        ['text' => 'Using a speculum to visualize the placenta directly', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'After the placenta is removed, if the uterus is found to be soft, the provider should:',
                    'explanation' => 'Uterine tone should be checked immediately after removal, and the uterus massaged if soft.',
                    'options' => [
                        ['text' => 'Ignore it, as softness is expected', 'correct' => false],
                        ['text' => 'Massage the uterus to restore tone', 'correct' => true],
                        ['text' => 'Immediately proceed to hysterectomy', 'correct' => false],
                        ['text' => 'Give a uterine relaxant', 'correct' => false],
                        ['text' => 'Discharge the patient for observation at home', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'If retained fragments are found after examining the delivered placenta, they should be removed using:',
                    'explanation' => 'Retained fragments are removed by hand, using ovum forceps, or a wide curette.',
                    'options' => [
                        ['text' => 'Hand, ovum forceps, or a wide curette', 'correct' => true],
                        ['text' => 'A sharp curette only, regardless of size', 'correct' => false],
                        ['text' => 'Suction alone without any instrumentation', 'correct' => false],
                        ['text' => 'They should be left in place and monitored', 'correct' => false],
                        ['text' => 'A speculum used to visually extract them without touching the uterus', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'What uterotonic regimen is given after successful manual removal of the placenta?',
                    'explanation' => 'Oxytocin 20 IU in 1 litre normal saline is given at 60 drops per minute after the placenta is removed.',
                    'options' => [
                        ['text' => 'Oxytocin 20 IU in 1 litre normal saline at 60 drops/minute', 'correct' => true],
                        ['text' => 'Ergometrine 500 mcg IM regardless of blood pressure', 'correct' => false],
                        ['text' => 'No further uterotonic is needed once the placenta is out', 'correct' => false],
                        ['text' => 'Misoprostol 1200 mcg rectally only', 'correct' => false],
                        ['text' => 'Carboprost 2 mg IM as a single dose', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'After manual removal, how frequently should BP, pulse, and uterine tone be monitored?',
                    'explanation' => 'Monitoring is every 15 minutes for the first 2 hours after the placenta is out, then every 30 minutes until 6 hours postpartum.',
                    'options' => [
                        ['text' => 'Every 15 minutes for 2 hours, then every 30 minutes until 6 hours postpartum', 'correct' => true],
                        ['text' => 'Once, immediately after the procedure only', 'correct' => false],
                        ['text' => 'Every 4 hours for 24 hours', 'correct' => false],
                        ['text' => 'Only if the woman complains of symptoms', 'correct' => false],
                        ['text' => 'Hourly for the first 10 minutes only', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'If the placenta remains adherent despite an appropriate manual removal attempt, the provider should:',
                    'explanation' => 'Continued forceful attempts are not recommended; the case should be escalated to manual removal in theatre under anesthesia, laparotomy, or, in extreme cases, subtotal hysterectomy.',
                    'options' => [
                        ['text' => 'Keep attempting manual removal indefinitely at the bedside', 'correct' => false],
                        ['text' => 'Escalate to theatre for removal under anesthesia, laparotomy, or hysterectomy if extreme', 'correct' => true],
                        ['text' => 'Discharge the woman and follow up in 2 weeks', 'correct' => false],
                        ['text' => 'Administer oral antibiotics and observe at home', 'correct' => false],
                        ['text' => 'Apply fundal pressure to force separation', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'Before beginning manual removal, which of the following must be established?',
                    'explanation' => 'A calibrated blood-loss drape, large-bore IV access with blood samples and fluids running, and a Foley catheter with an emptied bladder should all be established before the procedure.',
                    'options' => [
                        ['text' => 'A calibrated blood-loss drape, IV access with bloods drawn, and an emptied bladder via Foley catheter', 'correct' => true],
                        ['text' => 'General anesthesia in all cases, regardless of stability', 'correct' => false],
                        ['text' => 'A full bladder to help stabilize the uterus', 'correct' => false],
                        ['text' => 'Discontinuation of all IV fluids', 'correct' => false],
                        ['text' => 'Nothing further is required beyond consent', 'correct' => false],
                    ],
                ],
            ],
        ];
    }

    // =====================================================================
    // TRACK 4: Uterine Inversion
    // =====================================================================
    private function uterineInversionDefinition(): array
    {
        return [
            'fragment' => 'Uterine inversion',
            'introduction_title' => 'Manual Replacement of Uterine Inversion — Procedure',
            'introduction_content' => "Uterine inversion is a rare but life-threatening obstetric emergency in which the fundus collapses into (or through) the endometrial cavity, turning the uterus partially or completely inside out. Immediate recognition and management significantly improve the chance of successful replacement.\n\nCLASSIFICATION:\nBy extent — 1st degree (incomplete: fundus within the endometrial cavity); 2nd degree (complete: fundus protrudes through the cervical os — the most common presentation); 3rd degree (prolapsed: fundus protrudes to or beyond the introitus); 4th degree (total: uterus and vagina both inverted). In practice, simply described as complete or incomplete.\nBy timing — acute (within 24 hours of delivery), sub-acute (24 hours to 4 weeks), chronic (≥1 month postpartum).\n\nDIAGNOSIS: Clinical — hemorrhage, severe low abdominal pain, shock out of proportion to visible blood loss, uterine fundus not palpable abdominally (a cup-like fundal notch may be felt in incomplete inversion), a vaginal mass on examination, and urinary retention. Ultrasound shows absence of the normal fundal contour with a homogeneous globular mass. Treatment should not be delayed for radiographic confirmation.\n\nMANAGEMENT — resuscitation and correction happen simultaneously:\n1. Shout for help, assemble the emergency team, and perform a quick survey (vital signs).\n2. Quickly assess ABCs and resuscitate as necessary.\n3. Insert 2 wide-bore cannulas (ideally gauge 16 or 18).\n4. Take blood for FHG, U/E/Cr, group and cross-match, and coagulation profile.\n5. Start an infusion of crystalloids (normal saline or Ringer's lactate).\n6. Manage postpartum hemorrhage and shock if present.\n7. Discontinue uterotonic drugs immediately — uterine relaxation is required to allow replacement of the fundus.\n8. Give antibiotic prophylaxis: Ceftriaxone 2 g IV plus Metronidazole 500 mg IV stat (or, for penicillin/cephalosporin allergy, Clindamycin 600 mg IV plus Gentamicin 5 mg/kg IV), ideally within 60 minutes before any surgical incision.\n9. Replace the uterine fundus to its correct position (see techniques below).\n10. Measure blood loss using a calibrated drape and blood-loss monitoring chart.\n\nIMPORTANT: If the placenta is adherent, LEAVE IT IN SITU. Attempting to deliver it may cause massive hemorrhage and/or shock.\n\nNON-SURGICAL REPLACEMENT TECHNIQUES:\n\nJohnson's Manoeuvre — place a hand inside the vagina and push the fundus along the long axis of the vagina toward the umbilicus. If a constriction ring is palpable, apply pressure to the part of the fundus nearest the ring first, easing it through from bottom to top (do not attempt to push the wider fundal mass through the ring directly, as this is likely to fail). Prompt intervention is critical, since the lower segment and cervix contract over time, making replacement progressively harder. If the woman is hemodynamically unstable after an initial attempt, proceed directly to laparotomy. If stable but the initial attempt is unsuccessful, consider uterine relaxants and reattempt manual replacement.\n\nUterine relaxants (used only in hemodynamically stable women when the initial attempt fails): Magnesium sulfate 4–6 g IV over 15–20 minutes (slow onset); Terbutaline 0.25 mg SC (rapid onset, short half-life); or Glyceryl trinitrate 50 mcg IV, with up to 4 further 50 mcg doses as needed. CAUTION: relaxants worsen atonic PPH once the uterus is replaced — stop them immediately once the uterus is restored to its normal shape.\n\nHydrostatic Reduction (O'Sullivan technique) — first exclude uterine rupture. Warm normal saline is run rapidly into the vagina (using the largest giving set available) while the introitus is manually sealed, or via a silastic ventouse cup connected to the giving set for a better seal. Two to three litres of fluid may be required.\n\nSURGICAL INTERVENTIONS (if the above measures fail): the patient is taken to theatre for correction under spinal or general anesthesia.\nHuntington's procedure — the cup formed by the inversion is located; an Allis or Babcock clamp is placed on each round ligament entering the cup, about 2 cm deep; gentle traction on the clamps exerts upward traction on the inverted fundus; clamping in 2 cm increments with traction is repeated until the inversion is corrected (a second operator may assist vaginally).\nHaultain's procedure — a posterior uterine incision (about 4 cm) is made to transect the constriction ring, after which manual reduction (vaginal or via the incision) is performed; the incision is repaired once the uterus is restored.\n\nPOST-PROCEDURE: Carefully explore the uterus and remove any remaining placental fragments; ensure there is no uterine/cervical trauma; treat hemorrhagic shock and watch for PPH; commence an oxytocin infusion (20 units in 1 litre normal saline over 4 hours) after successful placental removal, since atonic PPH is common after correction and this reduces the risk of hemorrhage and re-inversion; nurse in a high-care area for at least 24 hours, monitoring uterine tone, PV bleeding and vital signs every 15 minutes for 2 hours then every 30 minutes for the next 4 hours; ensure adequate documentation and debrief the woman and her birth partner.",
            'outcome_title' => 'Manual Replacement of Uterine Inversion — Expected Learning Outcome',
            'outcome_content' => 'By the end of this track, the mentee should be able to recognize the clinical signs of uterine inversion (including shock disproportionate to visible blood loss and an impalpable fundus), initiate simultaneous resuscitation and correction, discontinue uterotonics appropriately, perform Johnson\'s manoeuvre for manual replacement, use uterine relaxants safely (and stop them immediately once the uterus is replaced), recognize when to proceed to hydrostatic reduction or surgical correction (Huntington\'s or Haultain\'s procedure), manage an adherent placenta safely by leaving it in situ, and provide correct post-procedure oxytocin cover and monitoring.',
            'objectives' => [
                'Classify uterine inversion by extent (1st–4th degree) and timing (acute, sub-acute, chronic) and recognize the most common clinical presentation.',
                'Recognize the clinical diagnostic features of uterine inversion, particularly shock disproportionate to visible blood loss and an impalpable fundus.',
                'Initiate resuscitation and correction simultaneously, including immediate discontinuation of uterotonics and appropriate antibiotic prophylaxis.',
                'Perform Johnson\'s manoeuvre for manual replacement, and describe the indications and cautions for uterine relaxants and hydrostatic reduction.',
                'Identify when surgical correction (Huntington\'s or Haultain\'s procedure) is required, and manage the placenta and post-procedure monitoring safely, including never forcibly removing an adherent placenta.',
            ],
            'content' => [
                ['label' => 'Lecturette & Mentor Demonstration', 'duration' => '20 min'],
                ['label' => 'Return Demonstration', 'duration' => '15 min'],
                ['label' => 'Assessment Checklist & Debrief', 'duration' => '10 min'],
            ],
            'quiz_title' => 'Uterine Inversion — Knowledge Assessment (Pre-test & Post-test)',
            'quiz_description' => 'A 10-question instrument administered before and after the uterine inversion skill session to measure knowledge gain on classification, diagnosis, resuscitation, and manual/surgical replacement techniques.',
            'questions' => [
                [
                    'text' => 'What is the most common clinical presentation of uterine inversion?',
                    'explanation' => 'The most common presentation is 2nd degree/complete uterine inversion, in which the fundus protrudes through the cervical os, typically accompanied by severe PPH and hypovolemic shock.',
                    'options' => [
                        ['text' => '1st degree/incomplete inversion, rarely associated with bleeding', 'correct' => false],
                        ['text' => '2nd degree/complete inversion, with the fundus protruding through the cervical os', 'correct' => true],
                        ['text' => '4th degree/total inversion only', 'correct' => false],
                        ['text' => 'Chronic inversion presenting weeks after delivery', 'correct' => false],
                        ['text' => 'Inversion without any associated bleeding', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'Which finding should raise strong suspicion of acute uterine inversion?',
                    'explanation' => 'Suspect acute uterine inversion when there is hypotension/shock out of proportion to the estimated blood loss and the provider is unable to palpate a normally positioned fundus abdominally.',
                    'options' => [
                        ['text' => 'A normally palpable, firm fundus with mild bleeding', 'correct' => false],
                        ['text' => 'Shock out of proportion to estimated blood loss, with an impalpable fundus abdominally', 'correct' => true],
                        ['text' => 'Painless bright-red bleeding with a soft, relaxed uterus', 'correct' => false],
                        ['text' => 'A fetal heart rate of 90 bpm with a tense abdomen', 'correct' => false],
                        ['text' => 'Bradycardia in the newborn', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'Why must uterotonic drugs be discontinued immediately when uterine inversion is diagnosed?',
                    'explanation' => 'Uterine relaxation, not contraction, is needed to allow the fundus to be manually replaced; uterotonics work against this.',
                    'options' => [
                        ['text' => 'Because uterotonics cause hypertension', 'correct' => false],
                        ['text' => 'Because uterine relaxation is required to allow replacement of the fundus', 'correct' => true],
                        ['text' => 'Because uterotonics are contraindicated in all postpartum women', 'correct' => false],
                        ['text' => 'Because they interfere with antibiotic absorption', 'correct' => false],
                        ['text' => 'There is no need to discontinue uterotonics', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'In which direction is the fundus pushed during Johnson\'s manoeuvre?',
                    'explanation' => 'A hand is placed inside the vagina and the fundus is pushed along the long axis of the vagina toward the umbilicus.',
                    'options' => [
                        ['text' => 'Along the long axis of the vagina, toward the umbilicus', 'correct' => true],
                        ['text' => 'Directly downward, toward the introitus', 'correct' => false],
                        ['text' => 'Laterally toward the iliac fossa', 'correct' => false],
                        ['text' => 'Toward the sacrum', 'correct' => false],
                        ['text' => 'It is not manipulated manually; only hydrostatic pressure is used', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'If a constriction ring is palpable while attempting manual replacement, the correct approach is to:',
                    'explanation' => 'Pressure should be applied to the part of the fundus nearest the ring first, easing it through from bottom to top, rather than pushing the wider fundal mass directly through the ring.',
                    'options' => [
                        ['text' => 'Push the widest part of the fundus through the ring first', 'correct' => false],
                        ['text' => 'Apply pressure to the part of the fundus nearest the ring first, easing it through progressively', 'correct' => true],
                        ['text' => 'Abandon replacement and proceed directly to hysterectomy without any further attempt', 'correct' => false],
                        ['text' => 'Increase uterotonic doses to force the ring open', 'correct' => false],
                        ['text' => 'Apply fundal pressure abdominally instead', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'If the placenta is still attached during management of uterine inversion, the provider should:',
                    'explanation' => 'If the placenta is adherent, it should be left in situ, since attempts to deliver it may cause massive hemorrhage and/or shock.',
                    'options' => [
                        ['text' => 'Attempt immediate manual removal before replacing the uterus', 'correct' => false],
                        ['text' => 'Leave the placenta in situ, since removal attempts may cause massive hemorrhage', 'correct' => true],
                        ['text' => 'Apply firm cord traction to deliver it quickly', 'correct' => false],
                        ['text' => 'Give additional uterotonics to expel the placenta', 'correct' => false],
                        ['text' => 'Perform curettage immediately', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'Which of the following is an appropriate uterine relaxant option (with correct caution) used to facilitate replacement?',
                    'explanation' => 'Options include Magnesium sulfate 4–6 g IV over 15–20 minutes, Terbutaline 0.25 mg SC, or Glyceryl trinitrate 50 mcg IV (up to 4 further doses); all must be stopped immediately once the uterus is replaced, as they worsen atonic PPH.',
                    'options' => [
                        ['text' => 'Terbutaline 0.25 mg SC, stopped immediately once the uterus is replaced', 'correct' => true],
                        ['text' => 'Oxytocin 10 IU IV, continued after replacement', 'correct' => false],
                        ['text' => 'Ergometrine 0.5 mg IM, given before replacement', 'correct' => false],
                        ['text' => 'Misoprostol 800 mcg sublingual before replacement', 'correct' => false],
                        ['text' => 'Carboprost 0.25 mg IM before replacement', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'If the woman becomes hemodynamically unstable after an initial attempt at manual replacement, the next step is to:',
                    'explanation' => 'If hemodynamically unstable after an initial attempt, it is reasonable to proceed directly to laparotomy rather than repeating manual attempts.',
                    'options' => [
                        ['text' => 'Repeat manual replacement attempts indefinitely at the bedside', 'correct' => false],
                        ['text' => 'Proceed directly to laparotomy', 'correct' => true],
                        ['text' => 'Discharge the patient for outpatient review', 'correct' => false],
                        ['text' => 'Give additional uterotonics and observe for 24 hours', 'correct' => false],
                        ['text' => 'Perform hydrostatic reduction only, without surgical backup', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'What must be excluded before attempting hydrostatic reduction (O\'Sullivan technique)?',
                    'explanation' => 'Uterine rupture must be excluded before attempting hydrostatic reduction, which involves running warm saline rapidly into the vagina while the introitus is sealed.',
                    'options' => [
                        ['text' => 'Uterine rupture', 'correct' => true],
                        ['text' => 'Placenta praevia', 'correct' => false],
                        ['text' => 'Gestational diabetes', 'correct' => false],
                        ['text' => 'Fetal macrosomia', 'correct' => false],
                        ['text' => 'Maternal anemia', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'Following successful replacement and placental removal, what postpartum oxytocin regimen is recommended and why?',
                    'explanation' => 'Oxytocin 20 units in 1 litre normal saline over 4 hours is started after successful placental removal, since atonic PPH is common after correction of uterine inversion, and this reduces the risk of hemorrhage and re-inversion.',
                    'options' => [
                        ['text' => 'Oxytocin 20 units in 1 litre normal saline over 4 hours, to reduce the risk of atonic PPH and re-inversion', 'correct' => true],
                        ['text' => 'No further uterotonic is required once the uterus is replaced', 'correct' => false],
                        ['text' => 'Ergometrine 1 mg IV bolus immediately after replacement', 'correct' => false],
                        ['text' => 'Misoprostol 200 mcg only, with no infusion', 'correct' => false],
                        ['text' => 'Carbetocin repeated every 4 hours', 'correct' => false],
                    ],
                ],
            ],
        ];
    }

    // =====================================================================
    // TRACK 5: Intrauterine Balloon Tamponade — Condom/Foley Variant
    // =====================================================================
    private function condomTamponadeDefinition(): array
    {
        return [
            'fragment' => 'condom tamponade',
            'introduction_title' => 'Placement of Intrauterine Balloon/Condom Tamponade — Procedure',
            'introduction_content' => "Intrauterine balloon tamponade is an escalation step for PPH refractory to uterotonics and other first-line measures. This variant uses a condom or balloon fitted over a Foley catheter and inflated within the uterine cavity to apply direct pressure to the placental bed and control bleeding.\n\nSTEP-BY-STEP PROCEDURE:\n1. Briefly explain the procedure to the mother and obtain informed consent.\n2. Wear sterile gloves.\n3. Assess and quantify blood loss.\n4. Place the condom/balloon over the end of a Foley catheter.\n5. Tie the lower end of the balloon tightly, below the level of the balloon, using suture or string — tight enough to prevent leakage of water from the balloon, but not so tight that it strangulates/occludes the catheter and prevents water flowing into the balloon.\n6. Inflate the Foley catheter's own retention balloon with about 20 cc of water.\n7. Place a Sim's speculum into the vagina and identify the cervix.\n8. Grasp the anterior lip of the cervix with ovum forceps.\n9. Aseptically guide the balloon/catheter end high into the uterus using the forceps, ensuring the entire balloon passes above the cervical os.\n10. Connect the Foley catheter to an IV giving set attached to a 1-litre infusion bag, and rapidly inflate the balloon with 300–500 mL of saline until bleeding stops.\n11. Clamp the catheter once the desired volume is reached and bleeding is controlled.\n12. Maintain the balloon in situ for up to 24 hours after bleeding is controlled and the patient is stable.\n13. Give oxytocin 20 IU in 1 litre normal saline at 60 drops/minute.\n14. Give broad-spectrum antibiotics.\n15. Monitor vital signs, uterine tone, bleeding, and urine output every 15 minutes for the first 2 hours, then every 30 minutes until 6 hours postpartum.\n16. Once the patient is stable (after 24 hours), slowly deflate the balloon by letting out 50 mL of water/saline every hour. If bleeding resumes, re-inflate with 50 mL back to the previous level. The balloon may be kept in place for up to 24 hours.\n17. If bleeding is not controlled within 15 minutes of initial insertion (or sooner if the mother is hemodynamically unstable or has heavy PV bleeding), abandon the procedure and seek surgical intervention immediately.\n18. Transfuse if indicated.\n19. Explain the results of the procedure to the mother.\n\nThis is a distinct device from the Free Flow System (FFS) balloon tamponade (Track VI): here the balloon is inflated with a fixed syringe/IV-bag volume (300–500 mL) via a tied Foley catheter, rather than the gravity-fed, BP-calibrated free-flow system.",
            'outcome_title' => 'Placement of Intrauterine Balloon/Condom Tamponade — Expected Learning Outcome',
            'outcome_content' => 'By the end of this track, the mentee should be able to correctly assemble a condom/Foley-based intrauterine balloon tamponade device, place it aseptically above the cervical os using a Sim\'s speculum and ovum forceps, inflate it with the correct volume to control bleeding, maintain and monitor it appropriately, deflate it safely once the woman is stable, and recognize the 15-minute failure criterion that mandates abandoning the procedure for surgical escalation.',
            'objectives' => [
                'Correctly assemble a condom/balloon device onto a Foley catheter, including safe tying to prevent leakage without occluding inflow.',
                'Place the balloon aseptically above the cervical os using a Sim\'s speculum and ovum forceps.',
                'Inflate the balloon with the correct volume (300–500 mL) to control bleeding and administer appropriate post-insertion oxytocin and antibiotics.',
                'Apply the correct monitoring schedule and safe deflation protocol once the woman is stable at 24 hours.',
                'Recognize the 15-minute failure criterion and escalate promptly to surgical management if bleeding is not controlled.',
            ],
            'content' => [
                ['label' => 'Video & Mentor Demonstration', 'duration' => '15 min'],
                ['label' => 'Return Demonstration', 'duration' => '15 min'],
                ['label' => 'Assessment Checklist & Debrief', 'duration' => '10 min'],
            ],
            'quiz_title' => 'Intrauterine Balloon/Condom Tamponade — Knowledge Assessment (Pre-test & Post-test)',
            'quiz_description' => 'A 10-question instrument administered before and after the condom/Foley balloon tamponade skill session to measure knowledge gain on assembly, insertion technique, and monitoring.',
            'questions' => [
                [
                    'text' => 'With how much water is the Foley catheter\'s retention balloon inflated before insertion of the condom/balloon device?',
                    'explanation' => 'The Foley catheter\'s own retention balloon is inflated with about 20 cc of water.',
                    'options' => [
                        ['text' => 'About 20 cc', 'correct' => true],
                        ['text' => 'About 5 cc', 'correct' => false],
                        ['text' => 'About 100 cc', 'correct' => false],
                        ['text' => 'About 500 cc', 'correct' => false],
                        ['text' => 'No inflation is needed at this stage', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'Why must the lower end of the balloon be tied at the correct tightness on the catheter?',
                    'explanation' => 'The tie must be tight enough to prevent leakage of water from the balloon, but must not strangulate the catheter, which would prevent water flowing into the balloon.',
                    'options' => [
                        ['text' => 'Tight enough to prevent leakage, but not so tight it occludes the catheter lumen', 'correct' => true],
                        ['text' => 'As loosely as possible to allow free drainage', 'correct' => false],
                        ['text' => 'Tightness does not matter for this device', 'correct' => false],
                        ['text' => 'It should be tied only after full inflation', 'correct' => false],
                        ['text' => 'It should be sealed with adhesive tape instead of a tie', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'What volume of saline is typically used to inflate the balloon within the uterine cavity to control bleeding?',
                    'explanation' => 'The balloon is rapidly inflated with 300–500 mL of saline until bleeding stops.',
                    'options' => [
                        ['text' => '50–100 mL', 'correct' => false],
                        ['text' => '300–500 mL', 'correct' => true],
                        ['text' => '1000–1500 mL', 'correct' => false],
                        ['text' => '2000 mL', 'correct' => false],
                        ['text' => 'No fixed volume; air is used instead of saline', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'Where must the balloon be positioned before inflation?',
                    'explanation' => 'The balloon must be guided high into the uterus, ensuring the entire balloon passes above the cervical os, using a Sim\'s speculum and ovum forceps for placement.',
                    'options' => [
                        ['text' => 'Entirely above the cervical os, high within the uterine cavity', 'correct' => true],
                        ['text' => 'Partially within the cervical canal only', 'correct' => false],
                        ['text' => 'In the vagina, below the cervix', 'correct' => false],
                        ['text' => 'Within the fallopian tube', 'correct' => false],
                        ['text' => 'Position does not matter as long as it is inflated', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'If bleeding is not controlled within 15 minutes of balloon insertion, the correct action is to:',
                    'explanation' => 'If bleeding is not controlled within 15 minutes of insertion (sooner if the mother is hemodynamically unstable or bleeding heavily), the procedure should be abandoned and surgical intervention sought immediately.',
                    'options' => [
                        ['text' => 'Continue waiting up to 2 hours before considering other options', 'correct' => false],
                        ['text' => 'Abandon the procedure and seek surgical intervention immediately', 'correct' => true],
                        ['text' => 'Add more saline indefinitely until bleeding stops, regardless of time', 'correct' => false],
                        ['text' => 'Remove the balloon and discharge the patient', 'correct' => false],
                        ['text' => 'Switch to oral misoprostol only', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'How long can the balloon typically be maintained in situ once bleeding is controlled and the patient is stable?',
                    'explanation' => 'The balloon is maintained in situ for up to 24 hours after bleeding is controlled and the patient is stable.',
                    'options' => [
                        ['text' => 'Up to 24 hours', 'correct' => true],
                        ['text' => 'Only 1 hour', 'correct' => false],
                        ['text' => 'Up to 7 days', 'correct' => false],
                        ['text' => 'It must be removed within 10 minutes of bleeding stopping', 'correct' => false],
                        ['text' => 'Indefinitely, with no planned removal', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'What is the correct protocol for deflating the balloon once the patient is stable at 24 hours?',
                    'explanation' => 'Slowly deflate by letting out 50 mL of water/saline every hour; if bleeding resumes, re-inflate with 50 mL back to the previous level.',
                    'options' => [
                        ['text' => 'Deflate all at once, immediately', 'correct' => false],
                        ['text' => 'Slowly release 50 mL every hour, re-inflating with 50 mL if bleeding resumes', 'correct' => true],
                        ['text' => 'Deflate only after 7 days', 'correct' => false],
                        ['text' => 'Cut the catheter without deflating', 'correct' => false],
                        ['text' => 'There is no need to monitor for bleeding during deflation', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'What uterotonic and antibiotic regimen accompanies balloon placement?',
                    'explanation' => 'Oxytocin 20 IU in 1 litre normal saline is given at 60 drops/minute, along with broad-spectrum antibiotics.',
                    'options' => [
                        ['text' => 'Oxytocin 20 IU in 1 litre normal saline at 60 drops/minute, plus broad-spectrum antibiotics', 'correct' => true],
                        ['text' => 'No uterotonic is needed once the balloon is inflated', 'correct' => false],
                        ['text' => 'Ergometrine 1 mg IV bolus only, no antibiotics', 'correct' => false],
                        ['text' => 'Antibiotics only, no uterotonic cover', 'correct' => false],
                        ['text' => 'Misoprostol 1600 mcg rectally, no oxytocin', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'How frequently should vital signs, uterine tone, bleeding, and urine output be monitored after balloon placement?',
                    'explanation' => 'Monitoring is every 15 minutes for the first 2 hours, then every 30 minutes until 6 hours postpartum.',
                    'options' => [
                        ['text' => 'Every 15 minutes for 2 hours, then every 30 minutes until 6 hours postpartum', 'correct' => true],
                        ['text' => 'Once daily only', 'correct' => false],
                        ['text' => 'Every 4 hours for 24 hours', 'correct' => false],
                        ['text' => 'Only if the woman reports symptoms', 'correct' => false],
                        ['text' => 'Every 5 minutes for the entire 24-hour period', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'What instruments are used to visualize the cervix and grasp its anterior lip during insertion of this device?',
                    'explanation' => 'A Sim\'s speculum is used to visualize the cervix, and ovum forceps are used to grasp the anterior lip of the cervix and guide the balloon into place.',
                    'options' => [
                        ['text' => 'Sim\'s speculum and ovum forceps', 'correct' => true],
                        ['text' => 'Bivalve speculum and sponge forceps only', 'correct' => false],
                        ['text' => 'No instruments are needed; insertion is entirely digital', 'correct' => false],
                        ['text' => 'A vacuum extractor cup', 'correct' => false],
                        ['text' => 'An amnihook', 'correct' => false],
                    ],
                ],
            ],
        ];
    }

    // =====================================================================
    // TRACK 6: Intrauterine Balloon Tamponade — Free Flow System (FFS)
    // =====================================================================
    private function freeFlowSystemDefinition(): array
    {
        return [
            'fragment' => 'free flow system',
            'introduction_title' => 'Placement of Free Flow System (FFS) Intrauterine Balloon Tamponade — Procedure',
            'introduction_content' => "The Free Flow System (FFS) intrauterine balloon tamponade is a distinct device from the condom/Foley-based system (Track V). It uses a dedicated balloon unit connected via a T-valve to a gravity-fed supply bag of sterile water/saline, with the fill pressure calibrated to the woman's systolic blood pressure rather than a fixed syringe volume.\n\nSTEP-BY-STEP PROCEDURE:\n1. Shout for help.\n2. Obtain informed consent.\n3. Ensure a blood collection drape is in situ.\n4. Wear sterile gloves.\n5. Assemble the FFS UBT by filling the supply bag manually or via the spike with 1 litre of sterile water or normal saline; hang the supply bag on a drip stand with the T-valve closed.\n6. Position the patient in the dorsal or lithotomy position.\n7. Clean the vulva and perineum with an antiseptic solution.\n8. Catheterize the mother and ensure the bladder is empty, leaving the catheter in situ for urine output monitoring.\n9. Drape the patient using sterile drapes.\n10. Introduce a Sim's speculum to visualize the cervix.\n11. Apply 2 sponge-holding/Kelly's forceps to the anterior lip of the cervix to stabilize the uterus with gentle traction.\n12. Remove the Sim's speculum.\n13. Introduce the balloon unit into the uterus — either by holding it in the palm of the inserting hand with the index and middle fingers guiding it through the cervical canal, or, for instrumental insertion, by holding the balloon just beneath its tip with ovum forceps and gently advancing it high into the uterine cavity to reach the fundus.\n14. Gently withdraw the forceps to release the anterior cervical lip, leaving the balloon unit in position.\n15. Position 2 fingers (index and middle) at the cervix to hold the balloon unit in place and prevent expulsion during inflation.\n16. Open the T-valve to allow water to flow into the balloon from the supply bag by gravity, continuing to inflate while keeping the fingers in place and checking the balloon remains well secured (the balloon fills within about 45 seconds).\n17. Allow water to flow until the flow stops, indicating equilibrium with the uterine cavity.\n18. Remove the fingers at the cervix and wait 2 minutes after inflation, then recheck that the balloon remains in the uterine cavity, observing the vulva for the level of vaginal bleeding.\n19. Set the supply bag to the appropriate height according to the patient's systolic blood pressure, using the 4 markings on the device tubing (measured from the T-valve toward the supply bag): 60 mmHg = 0.8 m, 80 mmHg = 1.1 m, 100 mmHg = 1.3 m, 120 mmHg = 1.6 m.\n20. Keep the T-valve open and note the water level in the bag when bleeding ceases.\n21. Check the patient is comfortable.\n22. Tape the tubing to the patient's thigh, leaving enough leeway for movement.\n23. Administer broad-spectrum IV antibiotics.\n24. Document the time of insertion, the total volume of water instilled into the balloon, and the level in the bag.\n25. Continue IV fluid resuscitation and uterotonic treatment.\n26. Monitor the patient closely for active bleeding, checking vital signs every 15 minutes for the first hour, every 30 minutes for the second hour, and hourly thereafter.\n27. Consider removal from 6–8 hours (to allow physiological contraction and relaxation of the uterus) or after a maximum of 24 hours if bleeding is controlled. If bleeding resumes on deflation, leave the tamponade in situ.\n28. To remove: drain water from the balloon into the supply bag by positioning the bag at or below the level of the patient with the T-valve open (takes about 60 seconds).\n29. Once all water has drained out of the balloon (1 litre back in the supply bag), remove the balloon by gently pulling on the tubing.\n30. Observe closely for resumption of active bleeding during decompression of the balloon.\n31. Document the procedure findings and all outcomes in the client record, and explain the outcome respectfully to the mother, guiding her on completing her course of antibiotics.",
            'outcome_title' => 'Placement of Free Flow System (FFS) Balloon Tamponade — Expected Learning Outcome',
            'outcome_content' => 'By the end of this track, the mentee should be able to correctly assemble and prime the FFS uterine balloon tamponade device, insert and secure the balloon unit within the uterine cavity using sponge-holding forceps for cervical stabilization, inflate it safely by gravity via the T-valve, calibrate the supply-bag height correctly against the woman\'s systolic blood pressure, monitor the woman appropriately, and safely deflate and remove the device at the correct time, recognizing when bleeding recurrence requires the tamponade to remain in situ.',
            'objectives' => [
                'Correctly assemble and prime the FFS uterine balloon tamponade device with 1 litre of sterile water/saline.',
                'Insert and secure the balloon unit within the uterine cavity using sponge-holding/Kelly\'s forceps and correct digital technique.',
                'Inflate the balloon safely by gravity via the T-valve and calibrate the supply-bag height correctly to the woman\'s systolic blood pressure.',
                'Apply the correct monitoring schedule and identify the appropriate time window for considering removal (6–8 hours, up to a maximum of 24 hours).',
                'Perform safe drainage and removal of the balloon, and recognize when bleeding recurrence on deflation requires the tamponade to be left in situ.',
            ],
            'content' => [
                ['label' => 'Video & Mentor Demonstration', 'duration' => '15 min'],
                ['label' => 'Return Demonstration', 'duration' => '15 min'],
                ['label' => 'Assessment Checklist & Debrief', 'duration' => '10 min'],
            ],
            'quiz_title' => 'Free Flow System (FFS) Balloon Tamponade — Knowledge Assessment (Pre-test & Post-test)',
            'quiz_description' => 'A 10-question instrument administered before and after the FFS balloon tamponade skill session to measure knowledge gain on assembly, insertion, BP-calibrated inflation, and safe removal.',
            'questions' => [
                [
                    'text' => 'How much sterile water or normal saline is used to fill the FFS supply bag before starting the procedure?',
                    'explanation' => 'The supply bag is filled with 1 litre of sterile water or normal saline, either manually or via the spike, and hung on a drip stand with the T-valve closed.',
                    'options' => [
                        ['text' => '1 litre', 'correct' => true],
                        ['text' => '250 mL', 'correct' => false],
                        ['text' => '3 litres', 'correct' => false],
                        ['text' => '500 mL', 'correct' => false],
                        ['text' => 'No fluid is used; the balloon is inflated with air', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'How does the FFS balloon inflate once the T-valve is opened?',
                    'explanation' => 'Water flows into the balloon from the supply bag by gravity through the open T-valve; the balloon typically fills within about 45 seconds.',
                    'options' => [
                        ['text' => 'By gravity flow through the open T-valve, filling within about 45 seconds', 'correct' => true],
                        ['text' => 'By manual syringe inflation only', 'correct' => false],
                        ['text' => 'By a battery-powered pump', 'correct' => false],
                        ['text' => 'By mouth-to-tube inflation', 'correct' => false],
                        ['text' => 'The balloon does not require inflation', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'What is the purpose of applying sponge-holding/Kelly\'s forceps to the anterior cervical lip during FFS insertion?',
                    'explanation' => 'The forceps are applied to the anterior cervical lip to stabilize the uterus with gentle traction while the balloon unit is introduced.',
                    'options' => [
                        ['text' => 'To stabilize the uterus with gentle traction during balloon insertion', 'correct' => true],
                        ['text' => 'To repair a cervical tear', 'correct' => false],
                        ['text' => 'To take a cervical biopsy', 'correct' => false],
                        ['text' => 'To administer local anesthesia', 'correct' => false],
                        ['text' => 'To measure cervical dilatation', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'For a patient with a systolic blood pressure of 100 mmHg, at what height should the supply bag be positioned (measured from the T-valve)?',
                    'explanation' => 'The device tubing markings correspond to 60 mmHg = 0.8 m, 80 mmHg = 1.1 m, 100 mmHg = 1.3 m, and 120 mmHg = 1.6 m, measured from the T-valve toward the supply bag.',
                    'options' => [
                        ['text' => '0.8 m', 'correct' => false],
                        ['text' => '1.1 m', 'correct' => false],
                        ['text' => '1.3 m', 'correct' => true],
                        ['text' => '1.6 m', 'correct' => false],
                        ['text' => 'Height does not need adjustment based on blood pressure', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'When should removal of the FFS balloon tamponade be considered, if bleeding is controlled?',
                    'explanation' => 'Removal is considered from 6–8 hours (to allow physiological contraction and relaxation of the uterus) or after a maximum of 24 hours.',
                    'options' => [
                        ['text' => '6–8 hours, or a maximum of 24 hours', 'correct' => true],
                        ['text' => 'Immediately, within 30 minutes of insertion', 'correct' => false],
                        ['text' => 'Only after 72 hours', 'correct' => false],
                        ['text' => 'Only once the woman is discharged home', 'correct' => false],
                        ['text' => 'There is no recommended time window for removal', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'If bleeding resumes when the balloon is being deflated, what should be done?',
                    'explanation' => 'If bleeding resumes on deflation of the balloon, the tamponade should be left in situ rather than completing removal.',
                    'options' => [
                        ['text' => 'Leave the tamponade in situ', 'correct' => true],
                        ['text' => 'Complete removal regardless of bleeding', 'correct' => false],
                        ['text' => 'Discharge the patient immediately', 'correct' => false],
                        ['text' => 'Remove the balloon and reinsert an entirely different device', 'correct' => false],
                        ['text' => 'Ignore the bleeding and observe for 24 more hours before acting', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'What is the purpose of catheterizing the mother before FFS balloon insertion?',
                    'explanation' => 'The mother is catheterized to ensure the bladder is empty and the catheter is left in situ to allow ongoing urine output monitoring.',
                    'options' => [
                        ['text' => 'To empty the bladder and allow ongoing urine output monitoring', 'correct' => true],
                        ['text' => 'To administer intravenous antibiotics', 'correct' => false],
                        ['text' => 'To measure blood loss directly', 'correct' => false],
                        ['text' => 'To provide analgesia', 'correct' => false],
                        ['text' => 'Catheterization is not required for this procedure', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'How is the balloon drained during removal of the FFS device?',
                    'explanation' => 'The balloon is drained by positioning the supply bag at or below the level of the patient with the T-valve open, which takes about 60 seconds for the water to drain back into the bag.',
                    'options' => [
                        ['text' => 'By positioning the supply bag at or below patient level with the T-valve open (about 60 seconds)', 'correct' => true],
                        ['text' => 'By cutting the tubing directly', 'correct' => false],
                        ['text' => 'By aspirating with a syringe through the balloon wall', 'correct' => false],
                        ['text' => 'The balloon cannot be drained and must be surgically removed', 'correct' => false],
                        ['text' => 'By raising the supply bag above the patient', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'How frequently should vital signs be monitored in the first two hours after FFS balloon placement?',
                    'explanation' => 'Vital signs are monitored every 15 minutes for the first hour, then every 30 minutes for the second hour, and hourly thereafter.',
                    'options' => [
                        ['text' => 'Every 15 minutes for the first hour, then every 30 minutes for the second hour', 'correct' => true],
                        ['text' => 'Once only, immediately after insertion', 'correct' => false],
                        ['text' => 'Every 4 hours throughout', 'correct' => false],
                        ['text' => 'Only if the woman reports feeling unwell', 'correct' => false],
                        ['text' => 'Every 5 minutes for the entire 24-hour period', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'How does the FFS device fundamentally differ from the condom/Foley-based balloon tamponade (Track V)?',
                    'explanation' => 'The FFS uses a dedicated balloon unit filled by gravity through a T-valve from a supply bag whose height is calibrated to the woman\'s systolic blood pressure, rather than a fixed syringe/IV-bag volume (300–500 mL) inflated through a tied Foley catheter.',
                    'options' => [
                        ['text' => 'The FFS is inflated by gravity via a T-valve, with supply-bag height calibrated to systolic blood pressure, unlike the fixed-volume Foley/condom system', 'correct' => true],
                        ['text' => 'The two systems are functionally identical with no procedural differences', 'correct' => false],
                        ['text' => 'The FFS does not require any antibiotics, unlike the Foley/condom system', 'correct' => false],
                        ['text' => 'The FFS is inserted only during caesarean section', 'correct' => false],
                        ['text' => 'The FFS uses air instead of fluid for inflation', 'correct' => false],
                    ],
                ],
            ],
        ];
    }
}
