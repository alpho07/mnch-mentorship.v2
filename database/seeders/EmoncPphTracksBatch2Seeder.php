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
use Illuminate\Support\Facades\Schema;

/**
 * Seeds mentee-facing session content, learning outcomes, objectives/workplans,
 * and knowledge quizzes for PPH Tracks 7-11:
 *   Track 7  - Repair of perineal tear
 *   Track 8  - Repair of cervical tear
 *   Track 9  - Placement of the B-Lynch suture
 *   Track 10 - Placement of non-pneumatic anti-shock garment (NASG)
 *   Track 11 - Postpartum hemorrhage simulation
 *
 * Source: EmONC Mentee Manual (primary source for procedural detail) and the
 * CHAI EmONC Knowledge Pack (mentor manual, used only for the E-MOTIVE bundle
 * framing of the Track 11 simulation, which is consistent with the mentee
 * manual's own AMTSL + First Response Bundle checklist items).
 *
 * Tracks 7-10 already have `video` and `case_scenario` ProgramModuleContent
 * records and existing ModuleRubric rows (seeded elsewhere) — this seeder does
 * not touch those. Track 11 has neither a case_scenario content record nor a
 * ModuleRubric, both of which are created here.
 */
class EmoncPphTracksBatch2Seeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $program = Program::where('name', 'Maternal Health (EmONC)')->firstOrFail();

            $pphModule = ProgramModule::where('program_id', $program->id)
                ->where('name', 'like', '%Postpartum Hemorrhage%')
                ->whereNull('parent_id')
                ->firstOrFail();

            foreach ($this->tracks() as $data) {
                $this->seedTrack($pphModule, $data);
            }
        });

        $this->command->info('EmONC PPH Tracks 7-11 content, rubric (Track 11), and quizzes seeded successfully.');
    }

    private function seedTrack(ProgramModule $pphModule, array $data): void
    {
        $track = ProgramModule::where('parent_id', $pphModule->id)
            ->where('name', 'like', "%{$data['fragment']}%")
            ->first();

        if (! $track) {
            $this->command->warn("PPH track not found for fragment: {$data['fragment']}");

            return;
        }

        // Session instructions ("introduction") — real step-by-step procedure from the mentee manual.
        ProgramModuleContent::firstOrCreate(
            [
                'program_module_id' => $track->id,
                'type' => 'introduction',
                'title' => $data['introduction_title'],
            ],
            [
                'content' => $data['introduction_content'],
                'order_sequence' => 1,
                'is_active' => true,
            ]
        );

        // Expected learning outcome.
        ProgramModuleContent::firstOrCreate(
            [
                'program_module_id' => $track->id,
                'type' => 'expected_learning_outcome',
                'title' => $data['outcome_title'],
            ],
            [
                'content' => $data['outcome_content'],
                'order_sequence' => 1,
                'is_active' => true,
            ]
        );

        // Only add a case_scenario content record if one is genuinely missing (Track 11).
        $hasCaseScenario = ProgramModuleContent::where('program_module_id', $track->id)
            ->where('type', 'case_scenario')
            ->exists();

        if (! $hasCaseScenario && isset($data['case_scenario'])) {
            ProgramModuleContent::firstOrCreate(
                [
                    'program_module_id' => $track->id,
                    'type' => 'case_scenario',
                    'title' => $data['case_scenario_title'],
                ],
                [
                    'content' => $data['case_scenario'],
                    'order_sequence' => 1,
                    'is_active' => true,
                ]
            );
        }

        // Track 11 only: create the missing ModuleRubric. Tracks 7-10 already have one — never touched here.
        if (isset($data['rubric'])) {
            ModuleRubric::firstOrCreate(
                ['program_module_id' => $track->id],
                $data['rubric']
            );
        }

        // Objectives / content workplan live on the ProgramModule itself.
        // Guarded: the `program_modules` table is currently missing the
        // `objectives`/`content` JSON columns that the ProgramModule model
        // declares as fillable/cast (schema drift — see final report), so an
        // unguarded update() would throw and roll back this entire seeder.
        if (Schema::hasColumn('program_modules', 'objectives') && Schema::hasColumn('program_modules', 'content')) {
            $track->update([
                'objectives' => $data['objectives'],
                'content' => $data['content_plan'],
            ]);
        } else {
            $this->command->warn(
                "Skipping objectives/content update for \"{$track->name}\": ".
                "'program_modules' table has no objectives/content columns (schema out of sync with the ProgramModule model)."
            );
        }

        // Quiz + 10 questions.
        $quiz = ProgramModuleQuiz::firstOrCreate(
            [
                'program_module_id' => $track->id,
                'type' => 'both',
            ],
            [
                'title' => $data['quiz_title'],
                'description' => $data['quiz_description'],
                'pass_mark_percentage' => 70.00,
                'order_sequence' => 1,
                'is_active' => true,
            ]
        );

        foreach ($data['questions'] as $seq => $q) {
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

    /**
     * @return array<int, array<string, mixed>>
     */
    private function tracks(): array
    {
        return [
            $this->perinealTearTrack(),
            $this->cervicalTearTrack(),
            $this->bLynchTrack(),
            $this->nasgTrack(),
            $this->simulationTrack(),
        ];
    }

    private function perinealTearTrack(): array
    {
        return [
            'fragment' => 'perineal tear',
            'introduction_title' => 'Repair of Perineal Tear — Session Instructions',
            'introduction_content' => <<<'TEXT'
Perineal tears are classified by degree, and the degree determines whether the repair can be done at the bedside or must be done in theatre:
• 1st degree — vaginal mucosa/perineal skin only
• 2nd degree — perineal muscles involved, anal sphincter intact
• 3rd degree — involves the anal sphincter
• 4th degree — involves the anal sphincter and rectal mucosa
• Buttonhole tears

3rd-degree, 4th-degree, and buttonhole tears must be repaired in theatre under regional or general anaesthesia — do not attempt these at the bedside.

Step-by-step repair (1st/2nd-degree tears):
1. Briefly explain the procedure to the mother and obtain informed consent.
2. Ensure a blood-loss collection drape is in place.
3. Place the woman in a high lithotomy position and ensure proper lighting.
4. Perform hand hygiene and put on sterile gloves.
5. Aseptically clean the vulva.
6. Drape the patient and catheterize the bladder.
7. Infiltrate the perineum with local anaesthesia (Lignocaine Hydrochloride 1%, 10–20 ml) and examine the tear.
8. Classify the degree of the perineal tear.
9. Place sterile gauze to collect ongoing lochia loss and improve visibility of the tear.
10. Identify the apex of the vaginal trauma and insert the first stitch 5–10 mm above this point.
11. Suture the posterior vaginal trauma and hymenal remnants using a loose continuous non-locking stitch.
12. Bring the needle through the tissue underneath the hymenal ring and continue repairing the deep and superficial perineal muscles with a loose continuous stitch, sealing off any dead space to avoid haematoma formation.
13. Appose the skin edges and complete the perineal repair.
14. Complete the subcutaneous repair up to the hymenal ring, swing the needle under the tissue into the vagina, and complete the repair with a terminal loop knot.
15. Explain the outcome of the procedure to the mother.

Key messages to the mother and her partner/birth companion after repair: perineal hygiene, sex education, drug compliance, hospital delivery for future 3rd/4th-degree tears at a CEmONC facility, pelvic muscle exercises, and family planning.
TEXT,
            'outcome_title' => 'Expected Learning Outcome — Repair of Perineal Tear',
            'outcome_content' => 'By the end of this track, the mentee should be able to correctly classify a perineal tear by degree, safely infiltrate local anaesthesia, and repair 1st- and 2nd-degree tears in the correct anatomical sequence (apex, vaginal wall, perineal muscles, skin), while recognising 3rd-degree, 4th-degree, and buttonhole tears that require repair in theatre under regional or general anaesthesia.',
            'objectives' => [
                'Classify perineal tears by degree (1st–4th degree, including buttonhole tears)',
                'Correctly infiltrate local anaesthesia and prepare the repair site',
                'Repair 1st- and 2nd-degree perineal tears in the correct sequence using appropriate suture technique',
                'Recognise 3rd-degree, 4th-degree, and buttonhole tears that require repair in theatre under regional or general anaesthesia',
                'Deliver correct post-repair counselling messages to the mother and birth companion',
            ],
            'content_plan' => [
                ['label' => 'Lecturette', 'duration' => '10 minutes'],
                ['label' => 'Demonstration Video (1st/2nd- and 3rd-Degree Repair)', 'duration' => '10 minutes'],
                ['label' => 'Mentor Demonstration', 'duration' => '30 minutes'],
                ['label' => 'Return Demonstration & Debrief', 'duration' => '20 minutes per mentee'],
                ['label' => 'Skill Assessment & Debrief', 'duration' => '20 minutes per mentee'],
            ],
            'quiz_title' => 'Repair of Perineal Tear — Knowledge Assessment (Pre-test & Post-test)',
            'quiz_description' => 'A 10-question instrument administered before and after the perineal tear repair practical session to measure knowledge gain on classification, technique, and escalation criteria.',
            'questions' => [
                [
                    'text' => 'A primigravida who had a precipitate delivery of a 4 kg infant is found to have a perineal tear involving the anal sphincter and rectal mucosa. How should this tear be classified and managed?',
                    'explanation' => 'A tear involving both the anal sphincter and rectal mucosa is a 4th-degree tear. 3rd-degree, 4th-degree, and buttonhole tears must be repaired in theatre under regional or general anaesthesia, not at the bedside.',
                    'options' => [
                        ['text' => '2nd degree; repair at the bedside with local anaesthesia', 'correct' => false],
                        ['text' => '3rd degree; repair at the bedside with local anaesthesia', 'correct' => false],
                        ['text' => '4th degree; repair in theatre under regional or general anaesthesia', 'correct' => true],
                        ['text' => '1st degree; no repair needed', 'correct' => false],
                        ['text' => '4th degree; repair at the bedside with sedation only', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'Where should the first stitch be placed when repairing a perineal tear?',
                    'explanation' => 'The apex of the vaginal trauma must be identified and the first stitch inserted 5–10 mm above this point to secure any retracted bleeding vessels at the apex.',
                    'options' => [
                        ['text' => 'At the skin edge nearest the anus', 'correct' => false],
                        ['text' => '5–10 mm above the identified apex of the vaginal trauma', 'correct' => true],
                        ['text' => 'At the hymenal ring only', 'correct' => false],
                        ['text' => 'At the midpoint of the tear', 'correct' => false],
                        ['text' => 'Wherever bleeding is heaviest', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'What is the purpose of avoiding dead space when repairing the deep and superficial perineal muscles?',
                    'explanation' => 'Sealing off dead space prevents blood or serous fluid from collecting in the tissue plane, which prevents haematoma formation.',
                    'options' => [
                        ['text' => 'To reduce suturing time', 'correct' => false],
                        ['text' => 'To prevent haematoma formation', 'correct' => true],
                        ['text' => 'To make the knot easier to tie', 'correct' => false],
                        ['text' => 'To reduce the amount of suture material used', 'correct' => false],
                        ['text' => 'To avoid the need for antibiotics', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'Which suturing technique is recommended for the posterior vaginal trauma and hymenal remnants?',
                    'explanation' => 'A loose continuous non-locking stitch is used so the tissue is approximated without strangulating it, reducing tension and ischaemia at the repair site.',
                    'options' => [
                        ['text' => 'Interrupted locking sutures', 'correct' => false],
                        ['text' => 'A loose continuous non-locking stitch', 'correct' => true],
                        ['text' => 'A single mattress suture', 'correct' => false],
                        ['text' => 'Staples', 'correct' => false],
                        ['text' => 'A tight continuous locking stitch', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'What local anaesthetic and dose is used to infiltrate the perineum before repair?',
                    'explanation' => 'Lignocaine Hydrochloride Injection BP 1%, 10–20 ml, is infiltrated into the perineum before examining and repairing the tear.',
                    'options' => [
                        ['text' => 'Bupivacaine 0.5%, 5 ml', 'correct' => false],
                        ['text' => 'Lignocaine Hydrochloride 1%, 10–20 ml', 'correct' => true],
                        ['text' => 'Adrenaline-only solution', 'correct' => false],
                        ['text' => 'Ketamine 50 mg IM', 'correct' => false],
                        ['text' => 'No anaesthesia is required for perineal repair', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'What position and preparation are required before starting a perineal tear repair?',
                    'explanation' => 'The woman is placed in a high lithotomy position with proper lighting, and the blood-loss collection drape, aseptic technique, and catheterisation are prepared before infiltrating anaesthesia.',
                    'options' => [
                        ['text' => 'Left lateral position with no drape', 'correct' => false],
                        ['text' => 'High lithotomy position with proper lighting, blood-loss drape in place, and aseptic technique', 'correct' => true],
                        ['text' => 'Supine position without catheterisation', 'correct' => false],
                        ['text' => 'Standing position', 'correct' => false],
                        ['text' => 'Prone position', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'How is the perineal repair completed at the level of the hymenal ring?',
                    'explanation' => 'The subcutaneous repair is completed to the hymenal ring, the needle is swung under the tissue into the vagina, and the repair is finished with a terminal loop knot.',
                    'options' => [
                        ['text' => 'With a single interrupted stitch tied externally', 'correct' => false],
                        ['text' => 'By swinging the needle under the tissue into the vagina and tying a terminal loop knot', 'correct' => true],
                        ['text' => 'By leaving the hymenal ring unsutured', 'correct' => false],
                        ['text' => 'With a mattress suture through the skin only', 'correct' => false],
                        ['text' => 'By tying the knot on the outer skin surface only', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'Which of the following is an appropriate key message to give the mother after perineal tear repair?',
                    'explanation' => 'Post-repair counselling includes perineal hygiene, sex education, drug compliance, hospital delivery for future 3rd/4th-degree tears at a CEmONC facility, pelvic muscle exercises, and family planning.',
                    'options' => [
                        ['text' => 'She should avoid all antibiotics regardless of what is prescribed', 'correct' => false],
                        ['text' => 'Future deliveries should always be by elective caesarean section', 'correct' => false],
                        ['text' => 'Deliver her next baby at a CEmONC facility given her history of a severe tear, and perform pelvic muscle exercises', 'correct' => true],
                        ['text' => 'Perineal hygiene is not necessary once the wound has healed', 'correct' => false],
                        ['text' => 'She does not need family planning counselling at this visit', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'What proportion of checklist items must a mentee perform to standard to pass the perineal tear repair practical assessment?',
                    'explanation' => 'The perineal tear repair checklist has 20 scored items, and the pass mark is ≥85% (17/20 items performed to standard).',
                    'options' => [
                        ['text' => '≥70% (14/20)', 'correct' => false],
                        ['text' => '≥85% (17/20)', 'correct' => true],
                        ['text' => '≥50% (10/20)', 'correct' => false],
                        ['text' => '100% (20/20)', 'correct' => false],
                        ['text' => '≥90% (18/20)', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'Before infiltrating local anaesthesia and examining the tear, which infection-prevention step must the provider perform?',
                    'explanation' => 'Hand hygiene and donning sterile gloves, followed by aseptic cleaning of the vulva and draping, are required before catheterisation, anaesthesia infiltration, and examination.',
                    'options' => [
                        ['text' => 'Perform hand hygiene, put on sterile gloves, and aseptically clean the vulva', 'correct' => true],
                        ['text' => 'Proceed directly to suturing without gloves to save time', 'correct' => false],
                        ['text' => 'Apply antiseptic only after the repair is complete', 'correct' => false],
                        ['text' => 'Skip catheterisation entirely', 'correct' => false],
                        ['text' => 'Use clean (non-sterile) gloves throughout the procedure', 'correct' => false],
                    ],
                ],
            ],
        ];
    }

    private function cervicalTearTrack(): array
    {
        return [
            'fragment' => 'cervical tear',
            'introduction_title' => 'Repair of Cervical Tear — Session Instructions',
            'introduction_content' => <<<'TEXT'
Suspect a cervical tear whenever a woman continues to bleed after birth despite a well-contracted uterus and a complete placenta.

Step-by-step repair:
1. Briefly explain the procedure to the mother and obtain informed consent; give analgesia and antibiotics.
2. Ensure a blood-loss collection drape is in situ.
3. Place the woman in a high lithotomy position.
4. Clean the perineum, vulva, and vagina with antiseptic.
5. Insert a catheter to empty the bladder.
6. Administer regional anaesthesia or sedation (ketamine hydrochloride or diazepam).
7. Systematically examine, in a clockwise direction, the peri-urethral area, perineum, vaginal opening, vagina, and cervix.
8. Identify the cervical tear and apply local anaesthetic to the area.
9. Grasp the cervix on one side of the tear with a sponge-holding forceps, then grasp the other side of the tear with a second sponge forceps.
10. Gently pull the cervix and rotate the sponge forceps to make sure the tip (apex) of the tear is located. If the tip cannot be located, the repair must be done under regional or general anaesthesia in theatre.
11. Once the tip of the tear is identified, place both forceps in one hand.
12. Place the first suture above the tip of the tear, then place two further continuous sutures.
13. Use continuous sutures to complete the repair.
14. Wipe off any visible blood and confirm haemostasis. If haemostasis is not achieved, take the patient to theatre.
15. Explain the results of the procedure to the mother.
16. Document the results on the blood-loss monitoring chart.
TEXT,
            'outcome_title' => 'Expected Learning Outcome — Repair of Cervical Tear',
            'outcome_content' => 'By the end of this track, the mentee should be able to systematically examine the birth canal to identify a cervical tear, locate its apex using sponge forceps, and repair it with continuous sutures, while recognising the specific situations (tip not located, haemostasis not achieved) that require escalation to theatre.',
            'objectives' => [
                'Systematically examine the birth canal in a clockwise fashion to identify a cervical tear',
                'Administer appropriate analgesia/anaesthesia (ketamine or diazepam) before repair',
                'Locate the apex (tip) of a cervical tear using sponge-holding forceps',
                'Repair a cervical tear using continuous sutures placed above the identified apex',
                'Confirm haemostasis after repair and recognise indications for escalation to theatre',
            ],
            'content_plan' => [
                ['label' => 'Lecturette', 'duration' => '10 minutes'],
                ['label' => 'Demonstration Video', 'duration' => '10 minutes'],
                ['label' => 'Mentor Demonstration', 'duration' => '30 minutes'],
                ['label' => 'Return Demonstration & Debrief', 'duration' => '20 minutes per mentee'],
                ['label' => 'Skill Assessment & Debrief', 'duration' => '20 minutes per mentee'],
            ],
            'quiz_title' => 'Repair of Cervical Tear — Knowledge Assessment (Pre-test & Post-test)',
            'quiz_description' => 'A 10-question instrument administered before and after the cervical tear repair practical session to measure knowledge gain on recognition, technique, and escalation criteria.',
            'questions' => [
                [
                    'text' => 'A woman continues to bleed after birth despite a well-contracted uterus and a complete placenta. What should be suspected?',
                    'explanation' => 'When the uterus is firm and the placenta is complete, ongoing bleeding points to genital-tract trauma such as a cervical, vaginal, or perineal tear as the source.',
                    'options' => [
                        ['text' => 'Uterine atony', 'correct' => false],
                        ['text' => 'A cervical tear or other genital-tract trauma', 'correct' => true],
                        ['text' => 'Retained placental fragments', 'correct' => false],
                        ['text' => 'Coagulopathy alone, without examining the birth canal', 'correct' => false],
                        ['text' => 'Uterine inversion', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'What anaesthesia/sedation is used for cervical tear repair?',
                    'explanation' => 'Regional anaesthesia or sedation with ketamine hydrochloride or diazepam is administered before the systematic examination and repair of a cervical tear.',
                    'options' => [
                        ['text' => 'General anaesthesia is always required', 'correct' => false],
                        ['text' => 'Regional anaesthesia or sedation with ketamine hydrochloride or diazepam', 'correct' => true],
                        ['text' => 'No anaesthesia is needed', 'correct' => false],
                        ['text' => 'Topical anaesthetic spray only', 'correct' => false],
                        ['text' => 'Inhalational anaesthesia only', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'In what order should the birth canal be examined when looking for a cervical tear?',
                    'explanation' => 'A systematic clockwise examination of the peri-urethral area, perineum, vaginal opening, vagina, and cervix ensures no source of bleeding is missed.',
                    'options' => [
                        ['text' => 'Cervix only, since that is the suspected source', 'correct' => false],
                        ['text' => 'Clockwise: peri-urethral area, perineum, vaginal opening, vagina, and cervix', 'correct' => true],
                        ['text' => 'Random order, whichever is easiest to visualise', 'correct' => false],
                        ['text' => 'Vagina first, then abdomen', 'correct' => false],
                        ['text' => 'Perineum only', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'How is the tip (apex) of a cervical tear located during repair?',
                    'explanation' => 'The cervix is grasped on each side of the tear with sponge-holding forceps, then gently pulled and rotated so the tip of the tear can be visualised.',
                    'options' => [
                        ['text' => 'By blind digital palpation alone', 'correct' => false],
                        ['text' => 'By grasping both sides of the tear with sponge forceps and gently pulling/rotating to visualise the tip', 'correct' => true],
                        ['text' => 'By applying traction on the fundus', 'correct' => false],
                        ['text' => 'It is not necessary to locate the tip before suturing', 'correct' => false],
                        ['text' => 'By packing the vagina with gauze', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'If the tip of a cervical tear cannot be located at the bedside, what should be done?',
                    'explanation' => 'When the apex of the tear cannot be visualised, the repair should be done under regional or general anaesthesia in theatre, where better exposure and analgesia are available.',
                    'options' => [
                        ['text' => 'Suture as far up as visible and stop', 'correct' => false],
                        ['text' => 'Pack the vagina and observe', 'correct' => false],
                        ['text' => 'Proceed to theatre and repair under regional or general anaesthesia', 'correct' => true],
                        ['text' => 'Apply a NASG and discharge the patient', 'correct' => false],
                        ['text' => 'Repeat the local anaesthetic infiltration and continue at the bedside', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'Where should the first suture be placed when repairing a cervical tear?',
                    'explanation' => 'The first suture is placed above the identified tip (apex) of the tear, followed by continuous sutures to complete the repair.',
                    'options' => [
                        ['text' => 'At the external cervical os', 'correct' => false],
                        ['text' => 'Above the tip of the tear', 'correct' => true],
                        ['text' => 'At the midpoint of the tear only', 'correct' => false],
                        ['text' => 'Below the tip of the tear', 'correct' => false],
                        ['text' => 'Wherever bleeding is most visible', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'How is successful repair of a cervical tear confirmed before ending the procedure?',
                    'explanation' => 'After completing continuous sutures, visible blood is wiped away and haemostasis is confirmed; if haemostasis is not achieved, the patient must be taken to theatre.',
                    'options' => [
                        ['text' => 'By checking the partograph', 'correct' => false],
                        ['text' => 'By wiping away visible blood and confirming haemostasis', 'correct' => true],
                        ['text' => 'By palpating the fundus only', 'correct' => false],
                        ['text' => 'By asking the mother if she feels better', 'correct' => false],
                        ['text' => 'Haemostasis does not need to be confirmed if sutures were placed', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'What suture material is used for cervical tear repair?',
                    'explanation' => 'Absorbable Vicryl suture (0, 1, or 2) on a round-body needle is used to repair a cervical tear.',
                    'options' => [
                        ['text' => 'Non-absorbable silk suture', 'correct' => false],
                        ['text' => 'Absorbable Vicryl suture (0, 1, or 2) on a round-body needle', 'correct' => true],
                        ['text' => 'Stainless steel wire', 'correct' => false],
                        ['text' => 'Skin staples', 'correct' => false],
                        ['text' => 'Catgut only, on a cutting needle', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'What proportion of checklist items must a mentee perform to standard to pass the cervical tear repair practical assessment?',
                    'explanation' => 'The cervical tear repair checklist has 18 scored items, and the pass mark is ≥85% (16/18 items performed to standard).',
                    'options' => [
                        ['text' => '≥85% (16/18)', 'correct' => true],
                        ['text' => '≥70% (13/18)', 'correct' => false],
                        ['text' => '100% (18/18)', 'correct' => false],
                        ['text' => '≥50% (9/18)', 'correct' => false],
                        ['text' => '≥90% (17/18)', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'Before examining for a cervical tear, what should the provider do to the bladder?',
                    'explanation' => 'A catheter is inserted to empty the bladder before the systematic examination of the birth canal, improving access and visibility.',
                    'options' => [
                        ['text' => 'Leave the bladder full to help visualise the cervix', 'correct' => false],
                        ['text' => 'Insert a catheter to empty the bladder', 'correct' => true],
                        ['text' => 'Perform a bladder scan only, without catheterisation', 'correct' => false],
                        ['text' => 'Ask the mother to void spontaneously only', 'correct' => false],
                        ['text' => 'Catheterisation is not required for this procedure', 'correct' => false],
                    ],
                ],
            ],
        ];
    }

    private function bLynchTrack(): array
    {
        return [
            'fragment' => 'B-Lynch suture',
            'introduction_title' => 'Placement of the B-Lynch Suture — Session Instructions',
            'introduction_content' => <<<'TEXT'
The B-Lynch compression suture is placed in theatre during laparotomy for uterine atony that has not responded to conservative first-line management (uterotonics, massage, bimanual compression).

Step-by-step technique:
1. Briefly explain the procedure to the mother/family.
2. Administer general anaesthesia with the patient in the supine position; scrub and drape the abdomen.
3. Take baseline vital signs (blood pressure, respiratory rate, pulse rate).
4. Open the abdomen and identify the uterus.
5. Assess the uterus for atony and confirm the decision to place a B-Lynch suture.
6. Make a lower uterine segment incision and remove any retained placental tissue or products of conception.
7. Using a round-bodied large needle loaded with size 0, 1, or 2 Vicryl suture, start from the right side (if right-handed), approximately 3 cm from the incision.
8. Insert the compression suture starting from the lower edge of the lower-segment uterine incision, exiting on the upper edge.
9. Pass the suture over the fundus and enter the uterine cavity posteriorly at the level of the lower-segment incision.
10. Pass the suture horizontally to the left lower uterine segment and exit posteriorly.
11. Pass the suture round over the fundus anteriorly to the upper edge of the left side of the incision, then exit from the lower edge.
12. Ask an assistant to compress the uterus bimanually while the two ends are pulled and tied together.
13. Ask the assistant to confirm that vaginal bleeding is controlled.
14. Close the uterine incision.
15. If bleeding does not stop after the suture is tied, proceed to hysterectomy.
16. Explain the results of the procedure to the mother.
17. Document the results and the blood-loss monitoring chart.
TEXT,
            'outcome_title' => 'Expected Learning Outcome — Placement of the B-Lynch Suture',
            'outcome_content' => 'By the end of this track, the mentee should be able to state the indications for a B-Lynch compression suture, describe correct pre-operative preparation, and verbalise/demonstrate the correct suture pathway and tensioning technique, while recognising that hysterectomy is the next step if bleeding does not stop after the suture is tied.',
            'objectives' => [
                'State the indications for a B-Lynch compression suture (uterine atony refractory to conservative management)',
                'Describe correct pre-operative preparation and positioning for laparotomy',
                'Demonstrate or verbalise the correct anatomical pathway for placing and tensioning the B-Lynch suture',
                'Confirm haemostasis after suture placement before closing the uterine incision',
                'Recognise when to escalate to hysterectomy if the B-Lynch suture does not control bleeding',
            ],
            'content_plan' => [
                ['label' => 'Lecturette', 'duration' => '10 minutes'],
                ['label' => 'Demonstration Video', 'duration' => '10 minutes'],
                ['label' => 'Mentor Demonstration', 'duration' => '30 minutes'],
                ['label' => 'Return Demonstration & Debrief', 'duration' => '20 minutes per mentee'],
                ['label' => 'Skill Assessment & Debrief', 'duration' => '20 minutes per mentee'],
            ],
            'quiz_title' => 'Placement of the B-Lynch Suture — Knowledge Assessment (Pre-test & Post-test)',
            'quiz_description' => 'A 10-question instrument administered before and after the B-Lynch suture practical session to measure knowledge gain on indications, technique, and escalation criteria.',
            'questions' => [
                [
                    'text' => 'A patient develops uterine atony that has not responded to uterotonics, uterine massage, or bimanual compression during laparotomy. What is the most appropriate next step?',
                    'explanation' => 'The B-Lynch compression suture is indicated for uterine atony that is refractory to conservative first-line measures, and is placed in theatre during laparotomy.',
                    'options' => [
                        ['text' => 'Discharge the patient home for outpatient follow-up', 'correct' => false],
                        ['text' => 'Place a B-Lynch compression suture', 'correct' => true],
                        ['text' => 'Repeat oxytocin only, with no other intervention', 'correct' => false],
                        ['text' => 'Apply a perineal pad and observe', 'correct' => false],
                        ['text' => 'Perform manual removal of the placenta', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'What needle and suture material are used to place a B-Lynch suture?',
                    'explanation' => 'A large round-bodied needle loaded with size 0, 1, or 2 Vicryl suture is used to place the compression suture.',
                    'options' => [
                        ['text' => 'A cutting needle with silk suture', 'correct' => false],
                        ['text' => 'A round-bodied needle with size 0, 1, or 2 Vicryl suture', 'correct' => true],
                        ['text' => 'A straight needle with catgut', 'correct' => false],
                        ['text' => 'Skin staples', 'correct' => false],
                        ['text' => 'Stainless steel wire', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'From which side and incision landmark should the B-Lynch suture typically begin (for a right-handed surgeon)?',
                    'explanation' => 'The suture starts from the right side of the lower uterine segment incision, approximately 3 cm from the edge, for a right-handed surgeon.',
                    'options' => [
                        ['text' => 'The fundus, working downward', 'correct' => false],
                        ['text' => 'The right side of the lower uterine segment incision, approximately 3 cm from the edge', 'correct' => true],
                        ['text' => 'The cervix', 'correct' => false],
                        ['text' => 'The left side of the incision only', 'correct' => false],
                        ['text' => 'The broad ligament', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'What is the correct pathway of the suture after it is inserted at the lower edge of the uterine incision and exits at the upper edge?',
                    'explanation' => 'The suture passes over the fundus, enters the uterine cavity posteriorly at the level of the lower-segment incision, passes horizontally to the left lower segment, exits posteriorly, then passes back over the fundus anteriorly to the upper-left edge and exits at the lower edge.',
                    'options' => [
                        ['text' => 'It passes over the fundus, posteriorly to the left lower segment, then back over the fundus to exit at the lower-left edge', 'correct' => true],
                        ['text' => 'It passes directly through the cervix', 'correct' => false],
                        ['text' => 'It stays entirely on the anterior uterine wall', 'correct' => false],
                        ['text' => 'It passes through the bladder', 'correct' => false],
                        ['text' => 'It is tied off immediately after the first pass, without crossing the fundus', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'What is the assistant\'s role while the two ends of the B-Lynch suture are tied?',
                    'explanation' => 'The assistant compresses the uterus bimanually while the surgeon pulls the two suture ends together and ties them, ensuring adequate compression is achieved and maintained.',
                    'options' => [
                        ['text' => 'The assistant retracts the bladder only', 'correct' => false],
                        ['text' => 'The assistant compresses the uterus bimanually while the suture ends are tied', 'correct' => true],
                        ['text' => 'The assistant has no role at this step', 'correct' => false],
                        ['text' => 'The assistant administers additional anaesthesia', 'correct' => false],
                        ['text' => 'The assistant closes the skin incision', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'How is successful placement of the B-Lynch suture confirmed before closing the uterine incision?',
                    'explanation' => 'An assistant confirms that vaginal bleeding is controlled before the uterine incision is closed.',
                    'options' => [
                        ['text' => 'By checking the partograph', 'correct' => false],
                        ['text' => 'By confirming, via an assistant, that vaginal bleeding is controlled', 'correct' => true],
                        ['text' => 'By palpating the abdomen only', 'correct' => false],
                        ['text' => 'Confirmation is not needed before closing', 'correct' => false],
                        ['text' => 'By checking the mother\'s temperature', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'If bleeding does not stop after the B-Lynch suture is tied and the uterine incision is closed, what is the next step?',
                    'explanation' => 'If bleeding persists despite the compression suture, the next step is to proceed to hysterectomy.',
                    'options' => [
                        ['text' => 'Repeat the B-Lynch suture indefinitely until it works', 'correct' => false],
                        ['text' => 'Proceed to hysterectomy', 'correct' => true],
                        ['text' => 'Close the abdomen and observe in the ward', 'correct' => false],
                        ['text' => 'Discharge the patient with oral iron', 'correct' => false],
                        ['text' => 'Apply a NASG only and cancel surgery', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'What must be done before placing the B-Lynch suture if placental tissue or products of conception remain in the uterus?',
                    'explanation' => 'Any retained placental tissue or products of conception must be removed through the lower uterine segment incision before placing the compression suture.',
                    'options' => [
                        ['text' => 'Nothing; the suture can be placed regardless', 'correct' => false],
                        ['text' => 'Remove the retained placental tissue or products of conception first', 'correct' => true],
                        ['text' => 'Close the abdomen and treat with antibiotics only', 'correct' => false],
                        ['text' => 'Perform a hysterectomy immediately without attempting the suture', 'correct' => false],
                        ['text' => 'Pack the uterus and delay surgery', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'What proportion of checklist items must a mentee perform (or correctly verbalise) to pass the B-Lynch suture practical assessment?',
                    'explanation' => 'The B-Lynch suture checklist has 22 scored items, and the pass mark is ≥85% (19/22 items performed to standard).',
                    'options' => [
                        ['text' => '≥85% (19/22)', 'correct' => true],
                        ['text' => '≥70% (15/22)', 'correct' => false],
                        ['text' => '100% (22/22)', 'correct' => false],
                        ['text' => '≥50% (11/22)', 'correct' => false],
                        ['text' => '≥90% (20/22)', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'What baseline observations should be taken before making the uterine incision for a B-Lynch suture?',
                    'explanation' => 'Baseline vital signs — blood pressure, respiratory rate, and pulse rate — are taken after the patient is anaesthetised and before the abdomen is opened.',
                    'options' => [
                        ['text' => 'Blood pressure, respiratory rate, and pulse rate', 'correct' => true],
                        ['text' => 'Only the mother\'s temperature', 'correct' => false],
                        ['text' => 'No baseline observations are required in theatre', 'correct' => false],
                        ['text' => 'Only urine output', 'correct' => false],
                        ['text' => 'Only fetal heart rate', 'correct' => false],
                    ],
                ],
            ],
        ];
    }

    private function nasgTrack(): array
    {
        return [
            'fragment' => 'anti-shock garment',
            'introduction_title' => 'Placement of the Non-Pneumatic Anti-Shock Garment (NASG) — Session Instructions',
            'introduction_content' => <<<'TEXT'
The non-pneumatic anti-shock garment (NASG) is a first-aid device used to stabilise a woman in hypovolaemic shock from postpartum haemorrhage while she is prepared for referral or definitive treatment.

Step-by-step application:
1. Briefly explain the procedure to the mother and obtain informed consent.
2. Ensure infection-prevention measures are adhered to and check the woman's vital signs.
3. Place the woman correctly on the opened NASG: the top edge of the garment at the lowest rib, the pressure ball over the umbilicus, and the dotted line between segments 5 and 6 in line with the spine.
4. Start application from segment pair 1 (fold segment 1 in half and begin from segment 2), performing the snap test.
5. Perform the snap test at each segment: place 1–2 fingers under the top of the segment, pull the fabric back, and let go — a properly tightened segment produces a sound like snapping fingers.
6. Continue closing segment pairs from segment 2 to segment 3, performing the snap test for each.
7. Bring the legs together and apply segment 4 around the woman's pelvis (do not perform the snap test on this segment).
8. Place segment 5 with the pressure ball over the umbilicus, then place segment 6 over segment 5 to close.
9. Confirm the woman can breathe normally: slide a hand under the NASG on the abdomen to check for space, and if needed, slightly loosen segments 5 and 6 while continuing to support the pressure ball.
10. Continue with other relevant PPH management and monitor for shortness of breath and decreased urine output.
11. Take the pulse rate and blood pressure as a baseline immediately before opening the first segment during removal, and document.
12. When removing the garment, open segment pair 1 first (or segment pair 2 for shorter women).
13. Explain the outcome of the procedure to the mother.
14. Document the results on the blood-loss monitoring chart.
TEXT,
            'outcome_title' => 'Expected Learning Outcome — Placement of the NASG',
            'outcome_content' => 'By the end of this track, the mentee should be able to correctly position and apply the non-pneumatic anti-shock garment using anatomical landmarks and the snap test, monitor for complications during use, and remove it safely in the correct sequence once the woman is stable and definitive care is available.',
            'objectives' => [
                'Correctly position the NASG using anatomical landmarks (lowest rib, umbilicus, spine)',
                'Apply the garment segments in the correct sequence, using the snap test to confirm adequate compression',
                'Continue essential PPH management concurrently with NASG application',
                'Monitor for complications of NASG use, particularly breathing difficulty and decreased urine output',
                'Safely remove the NASG in the correct segment order once bleeding is controlled and the woman is stable',
            ],
            'content_plan' => [
                ['label' => 'Lecturette', 'duration' => '10 minutes'],
                ['label' => 'Demonstration Video', 'duration' => '10 minutes'],
                ['label' => 'Mentor Demonstration', 'duration' => '30 minutes'],
                ['label' => 'Return Demonstration & Debrief', 'duration' => '20 minutes per mentee'],
                ['label' => 'Skill Assessment & Debrief', 'duration' => '20 minutes per mentee'],
            ],
            'quiz_title' => 'Placement of the NASG — Knowledge Assessment (Pre-test & Post-test)',
            'quiz_description' => 'A 10-question instrument administered before and after the NASG practical session to measure knowledge gain on positioning, application sequence, and monitoring.',
            'questions' => [
                [
                    'text' => 'What are the correct anatomical landmarks for positioning the NASG on a woman before application?',
                    'explanation' => 'The top edge of the garment should sit at the lowest rib, the pressure ball over the umbilicus, and the dotted line between segments 5 and 6 in line with the spine.',
                    'options' => [
                        ['text' => 'Top edge at the xiphisternum, pressure ball over the pubic bone', 'correct' => false],
                        ['text' => 'Top edge at the lowest rib, pressure ball over the umbilicus, dotted line between segments 5 and 6 in line with the spine', 'correct' => true],
                        ['text' => 'Top edge at the knees, pressure ball over the chest', 'correct' => false],
                        ['text' => 'Any position is acceptable as long as it is snug', 'correct' => false],
                        ['text' => 'Top edge at the ankles, working upward', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'In what order are the NASG segments applied?',
                    'explanation' => 'Application starts at segment pair 1 (folding segment 1 and starting from segment 2), proceeds through segments 2 and 3, then segment 4 around the pelvis, then segments 5 and 6 over the abdomen.',
                    'options' => [
                        ['text' => 'Segments 5 and 6 first, then working outward to the legs', 'correct' => false],
                        ['text' => 'Segment pair 1, then 2, then 3, then segment 4 (pelvis), then segments 5 and 6', 'correct' => true],
                        ['text' => 'Any order is acceptable', 'correct' => false],
                        ['text' => 'Segment 4 first, then the legs', 'correct' => false],
                        ['text' => 'Segments 5 and 6 only, without the leg segments', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'What is the "snap test" used to confirm during NASG application?',
                    'explanation' => 'The snap test involves placing 1–2 fingers under the top of a segment, pulling the fabric back, and releasing it — a properly tightened segment produces a sound like snapping fingers, confirming adequate compression.',
                    'options' => [
                        ['text' => 'That the Velcro fasteners are correctly aligned', 'correct' => false],
                        ['text' => 'That each segment is tight enough, indicated by a snapping sound when the fabric is pulled and released', 'correct' => true],
                        ['text' => 'That the woman\'s pulse is palpable at the wrist', 'correct' => false],
                        ['text' => 'That the pressure ball is inflated', 'correct' => false],
                        ['text' => 'That the garment material is not torn', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'Which NASG segment should NOT be snap-tested during application?',
                    'explanation' => 'Segment 4, which goes around the pelvis after the legs are brought together, is applied without performing the snap test.',
                    'options' => [
                        ['text' => 'Segment 1', 'correct' => false],
                        ['text' => 'Segment 2', 'correct' => false],
                        ['text' => 'Segment 3', 'correct' => false],
                        ['text' => 'Segment 4 (around the pelvis)', 'correct' => true],
                        ['text' => 'Segment 6', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'After fully applying the NASG, what must the provider check, and what should be done if it is abnormal?',
                    'explanation' => 'The provider must confirm the woman can breathe normally; if breathing is restricted, segments 5 and 6 should be slightly loosened while continuing to support the pressure ball.',
                    'options' => [
                        ['text' => 'Check breathing; if restricted, remove the entire garment immediately', 'correct' => false],
                        ['text' => 'Check that the woman can breathe normally; if restricted, slightly loosen segments 5 and 6 while supporting the pressure ball', 'correct' => true],
                        ['text' => 'Check only the blood pressure; breathing is not assessed', 'correct' => false],
                        ['text' => 'No further checks are needed once applied', 'correct' => false],
                        ['text' => 'Tighten all segments further if breathing feels restricted', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'What complications should be monitored for while a woman is in the NASG?',
                    'explanation' => 'While the NASG is in place, the woman should be monitored for shortness of breath and decreased urine output, alongside continued PPH management.',
                    'options' => [
                        ['text' => 'Shortness of breath and decreased urine output', 'correct' => true],
                        ['text' => 'Only fever', 'correct' => false],
                        ['text' => 'Only skin rash at the application site', 'correct' => false],
                        ['text' => 'No monitoring is needed once applied', 'correct' => false],
                        ['text' => 'Only headache', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'When should baseline pulse and blood pressure be recorded in relation to NASG removal?',
                    'explanation' => 'Baseline pulse and blood pressure are taken and documented immediately before opening the first segment during removal.',
                    'options' => [
                        ['text' => 'Only after the entire garment is removed', 'correct' => false],
                        ['text' => 'Immediately before opening the first segment', 'correct' => true],
                        ['text' => 'Vitals do not need to be recorded during removal', 'correct' => false],
                        ['text' => 'Only 24 hours after removal', 'correct' => false],
                        ['text' => 'Only if the woman reports symptoms', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'Which segment(s) should be opened first when removing the NASG?',
                    'explanation' => 'Segment pair 1 is opened first during removal (or segment pair 2 for shorter women), and the woman is reassessed for stability before removing further segments.',
                    'options' => [
                        ['text' => 'Segments 5 and 6, over the abdomen', 'correct' => false],
                        ['text' => 'Segment pair 1 (or segment pair 2 for shorter women)', 'correct' => true],
                        ['text' => 'Segment 4, around the pelvis', 'correct' => false],
                        ['text' => 'All segments simultaneously', 'correct' => false],
                        ['text' => 'Whichever segment is loosest', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'A woman who delivered 30 minutes ago is found confused, agitated, and in a pool of blood as she is prepared for referral. Which intervention is most appropriate alongside definitive PPH management?',
                    'explanation' => 'Signs of hypovolaemic shock (confusion, agitation, heavy visible blood loss) in a woman being prepared for referral or advanced care are an indication for applying the NASG while continuing other PPH management.',
                    'options' => [
                        ['text' => 'Apply the NASG while continuing PPH management and prepare for referral', 'correct' => true],
                        ['text' => 'Discharge her home to rest', 'correct' => false],
                        ['text' => 'Withhold IV fluids until she is fully alert', 'correct' => false],
                        ['text' => 'Apply the NASG only, with no other PPH management', 'correct' => false],
                        ['text' => 'Wait for the ambulance before starting any intervention', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'What proportion of checklist items must a mentee perform to standard to pass the NASG practical assessment?',
                    'explanation' => 'The NASG placement checklist has 20 scored items, and the pass mark is ≥85% (17/20 items performed to standard).',
                    'options' => [
                        ['text' => '≥85% (17/20)', 'correct' => true],
                        ['text' => '≥70% (14/20)', 'correct' => false],
                        ['text' => '100% (20/20)', 'correct' => false],
                        ['text' => '≥50% (10/20)', 'correct' => false],
                        ['text' => '≥60% (12/20)', 'correct' => false],
                    ],
                ],
            ],
        ];
    }

    private function simulationTrack(): array
    {
        $caseScenario = <<<'TEXT'
CLIENT: Nancy, 28 years, para 3+0 gravida 4 at 39 weeks, with a history of prolonged labour and 6 antenatal care visits during a healthy pregnancy. Nancy has just delivered a live male infant who scored 10/10 at 1 and 5 minutes.

SECTION I — AMTSL (0–5 minutes):
The mentee continues active management of the third stage of labour as the baby is being dried and wiped: explain the procedure and obtain consent, palpate the abdomen to rule out a second baby, administer a uterotonic within 1 minute of birth, unfold the blood-loss collection drape, change gloves before cutting the cord, deliver the placenta by controlled cord traction with counter-pressure only during a contraction, inspect the placenta for completeness, assess fundal tone and check for tears immediately after delivery of the placenta, and massage the uterus until tone is achieved.

SECTION II — RECOGNITION AND E-MOTIVE FIRST RESPONSE (5–15 minutes):
Shortly after placental delivery, the uterus becomes boggy and bleeding increases in the collection drape.
• E — Early detection: the mentee recognises excessive bleeding using the calibrated drape and clinical signs (rising pulse, falling blood pressure), shouts for help, and the team lead assigns roles while reassuring Nancy.
• M — Massage: the uterus is massaged until firm; the bladder is checked and catheterised.
• O — Oxytocics: two wide-bore cannulas are inserted, blood samples are drawn (FBC, group and cross-match, U/E/C, coagulation profile), oxytocin 10 IU is infused in 500 ml of crystalloid over 10 minutes (as fast as possible), followed by 20 IU in 1 litre of crystalloid over 4 hours, and misoprostol 800 mcg sublingual is given (topped up to a total of 800 mcg if 600 mcg was already given during AMTSL).
• T — TXA: 1 g of tranexamic acid is given IV at 1 ml/minute over 10 minutes, since birth occurred within the last 3 hours.
• IV — IV fluids: further crystalloid is given if clinically indicated, with restraint to avoid over-transfusion before blood products are available.
• E — Examination & escalation: the team checks for tears, rechecks the placenta for completeness, and rechecks uterine tone. Bleeding persists despite the bundle, so the team leader escalates care: bimanual uterine compression, uterine tamponade, compression of the abdominal aorta, transfer to theatre, referral, application of the NASG for safe transport, intermittent aortic compression, compression sutures (e.g. B-Lynch), uterine vessel ligation, and hysterectomy are named in order of escalation as appropriate.

SECTION III — STABILISATION (15–20 minutes):
Once bleeding is controlled, the team monitors uterine tone, vaginal bleeding, and vital signs every 15 minutes for the first 2 hours, then every 30 minutes for the next 4 hours, while providing respectful, clearly communicated care to Nancy and documenting all findings on the blood-loss monitoring chart.

TASK: Lead the emergency management of this client from recognition through to stabilisation, asking other mentees for help as necessary and thinking aloud/using SBAR throughout.
TEXT;

        return [
            'fragment' => 'simulation',
            'introduction_title' => 'Postpartum Hemorrhage Simulation — Session Instructions',
            'introduction_content' => <<<'TEXT'
This simulation combines the skills learned in earlier PPH tracks into a single, time-critical scenario, because PPH can kill a mother within 2 hours — early, well-sequenced management is crucial for survival.

The simulation runs in two sections using a simulated client ("Nancy") played by one mentee, with the rest of the team called in to manage the emergency:

Section I — AMTSL:
1. Briefly explain the procedure to the mother and obtain informed consent.
2. Palpate the abdomen to rule out a second baby.
3. Explain to the mother the medication she is receiving and why.
4. Administer a uterotonic within 1 minute of the birth of the baby.
5. Unfold the blood-loss collection drape or place the blood-collection device.
6. Change gloves before cutting the cord, and disengage/undo the cord after giving the uterotonic.
7. Deliver the placenta by controlled cord traction with counter-pressure on the uterus, performing traction only during a contraction; use both hands to catch the placenta and gently rotate it as it delivers.
8. Inspect the placenta for completeness.
9. Assess fundal tone immediately after placental delivery, and check the birth canal for tears or lacerations.
10. Assess the amount of blood loss and massage the uterus until tone is achieved; teach the woman/birth companion to check uterine tone every 15 minutes for 2 hours and report if soft or bleeding excessively.
11. Check the baby's colour, temperature, and breathing; initiate breastfeeding within 1 hour and encourage skin-to-skin contact.
12. Explain the results to the mother, encourage frequent bladder emptying, and document everything.

Section II — First Response Bundle (E-MOTIVE) and escalation, triggered if the uterus becomes boggy and bleeding increases:
1. Shout for help; the team assembles quickly and the PPH kit is brought; the team leader assigns roles and reassures the mother while the team manages the emergency.
2. Check the blood-loss collection drape and quantify bleeding; quickly assess airway/breathing/circulation and resuscitate as necessary; massage the uterus; check and catheterise the bladder.
3. Insert two wide-bore IV cannulas and draw blood samples (full blood count, group and cross-match, urea/electrolytes/creatinine, coagulation profile).
4. Infuse 10 IU oxytocin in 500 ml crystalloid over 10 minutes (or as fast as possible), then 20 IU in 1 litre of crystalloid over 4 hours; give misoprostol 800 mcg sublingually (topping up to 800 mcg total if 600 mcg was already given in AMTSL).
5. Give 1 g tranexamic acid (TXA) IV at 1 ml/minute over 10 minutes, if birth occurred within the last 3 hours; give further IV fluids if clinically indicated.
6. Check for tears, recheck the placenta for completeness, and recheck the uterus.
7. If bleeding continues, escalate: bimanual uterine compression, uterine tamponade, compression of the abdominal aorta, transfer to theatre, referral, application of the NASG for transport, intermittent aortic compression, compression sutures (e.g. B-Lynch), ligation of uterine vessels, and hysterectomy — named in escalating order as appropriate to the scenario.
8. Once bleeding is controlled, monitor uterine tone, vaginal bleeding, and vital signs every 15 minutes for the first 2 hours, then every 30 minutes for the next 4 hours.
9. Provide respectful care and clear communication throughout, and document all findings on the blood-loss monitoring chart.

Throughout the drill, mentees should practise: addressing the client directly by name, using SBAR to hand over to seniors, "closing the loop" with timely feedback, thinking out loud, and following a clear referral process.
TEXT,
            'outcome_title' => 'Expected Learning Outcome — PPH Simulation',
            'outcome_content' => 'By the end of this simulation, the mentee should be able to lead the initial recognition and first-two-hours management of primary postpartum haemorrhage, correctly sequencing AMTSL, the E-MOTIVE first-response bundle, and appropriate escalation, while communicating effectively and respectfully as part of an emergency team.',
            'case_scenario_title' => 'PPH Simulation — Practical Skills Assessment — Case Scenario',
            'case_scenario' => $caseScenario,
            'objectives' => [
                'Recognise primary postpartum haemorrhage promptly using objective blood-loss assessment and clinical signs',
                'Correctly sequence active management of the third stage of labour (AMTSL) actions',
                'Lead a coordinated team response to PPH using the E-MOTIVE first-response bundle (Early detection, Massage, Oxytocics, TXA, IV fluids, Examination & escalation)',
                'Identify the correct escalation pathway for PPH refractory to first-line management (bimanual compression, uterine tamponade, aortic compression, NASG, compression sutures, theatre, hysterectomy)',
                'Demonstrate effective team communication (shouting for help, role assignment, SBAR, closed-loop communication) during an obstetric emergency',
            ],
            'content_plan' => [
                ['label' => 'Simulation Briefing & Room Set-Up', 'duration' => '10 minutes'],
                ['label' => 'Section I: AMTSL Simulation & Assessment', 'duration' => '20 minutes per mentee'],
                ['label' => 'Section II: First Response Bundle Simulation & Assessment', 'duration' => '30 minutes per mentee'],
                ['label' => 'Structured Debrief (Client & Provider Roles)', 'duration' => '20 minutes'],
            ],
            'rubric' => [
                'title' => 'PPH Simulation — Practical Skills Assessment',
                'description' => 'A full integrated simulation combining active management of the third stage of labour (AMTSL) with the E-MOTIVE first-response bundle and structured escalation, assessing the mentee\'s ability to recognise and manage primary postpartum haemorrhage as a team leader within the critical first two hours after birth.',
                'case_scenario' => $caseScenario,
                'total_marks' => 51,
                'pass_marks' => (int) round(51 * 0.85),
                'pass_percentage' => 85.00,
                'equipment_supplies' => [
                    'Simulation pant and waterproof pant',
                    'Two adult diapers/absorbent pads (one under the simulation pant, one under the patient on the couch)',
                    'Baby and placenta models, with improvised baby cry',
                    'Injection pad, placed on the thigh',
                    'Simulated blood (1000 ml) with long tubing, covered and hanging from an IV pole',
                    'Two IV arm bands',
                    'Whiteboard and dry-erase pen',
                    'Delivery couch and beddings',
                    "Sim's speculum",
                    'Blood pressure machine',
                    'Two stethoscopes (maternal and neonatal)',
                    'Two 500 ml bags of IV solution (Ringer\'s lactate or normal saline) and an IV/drip stand',
                    'Eight 1 ml ampoules of sterile water labelled oxytocin (5 IU/ml)',
                    'Three 5 cc syringes',
                    'Blood sample bottles',
                    'Ampoules and tablets labelled as medications (oxytocin, misoprostol, ergometrine, heat-stable carbetocin, tranexamic acid)',
                    'Non-pneumatic anti-shock garment (NASG)',
                    'Uterine balloon tamponade (UBT) kit',
                    'Sharps/safety box',
                    'Complete delivery pack',
                    'Fetoscope or Doppler',
                    'Gestational age wheel',
                    'Personal protective equipment (gowns, aprons, boots)',
                    'Sterile gloves and examination gloves',
                    "Foley's catheter",
                    'Oxygen tubing with nasal cannula (if available)',
                    'Oxygen concentrator or cylinder, maternal and neonatal (if available)',
                    'Resuscitaire with resuscitation bag and mask, and a neonatal mannequin covered with a towel',
                    'Warmer with a firm, padded resuscitation surface',
                    'Penguin sucker',
                    'Clean towels for the baby',
                    'Blood loss monitoring chart',
                    'Blood loss collection drape',
                ],
                'debrief_questions' => [
                    'How did the assessment feel?',
                    'What are the steps of managing PPH using the E-MOTIVE approach?',
                    'Which steps did you perform well?',
                    'Which steps need to be improved?',
                ],
                'order_sequence' => 1,
                'is_active' => true,
            ],
            'quiz_title' => 'Postpartum Hemorrhage Simulation — Knowledge Assessment (Pre-test & Post-test)',
            'quiz_description' => 'A 10-question instrument administered before and after the integrated PPH simulation drill to measure knowledge gain on recognition, the E-MOTIVE bundle, and escalation.',
            'questions' => [
                [
                    'text' => 'What does the E-MOTIVE bundle stand for in the first response to postpartum haemorrhage?',
                    'explanation' => 'E-MOTIVE stands for Early detection, Massage, Oxytocics, TXA, IV fluids, and Examination & escalation — the WHO First Response Bundle for PPH.',
                    'options' => [
                        ['text' => 'Emergency, Monitoring, Oxygen, Transfusion, Investigation, Vitals, Evacuation', 'correct' => false],
                        ['text' => 'Early detection, Massage, Oxytocics, TXA, IV fluids, Examination & escalation', 'correct' => true],
                        ['text' => 'Examine, Manage, Observe, Treat, Investigate, Verify, Escalate', 'correct' => false],
                        ['text' => 'Evacuate, Monitor, Oxygenate, Transport, IV access, Vitals, Explain', 'correct' => false],
                        ['text' => 'Estimate, Massage, Oxytocin only, Transfuse, IV fluids, Exit', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'Within what time frame should tranexamic acid (TXA) be given during PPH management, and at what dose?',
                    'explanation' => 'TXA 1 g IV is given at a rate of 1 ml per minute over 10 minutes, and should be given as soon as possible and within 3 hours of birth for it to be effective.',
                    'options' => [
                        ['text' => '1 g IV over 10 minutes, within 3 hours of birth', 'correct' => true],
                        ['text' => '2 g IV bolus, at any time after birth', 'correct' => false],
                        ['text' => '500 mg IM, within 24 hours of birth', 'correct' => false],
                        ['text' => '1 g orally, within 1 hour of birth', 'correct' => false],
                        ['text' => 'TXA is not indicated for PPH', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'What is the correct oxytocin regimen as part of the first response bundle for a woman with ongoing PPH?',
                    'explanation' => '10 IU oxytocin is infused in 500 ml of crystalloid over 10 minutes (or as fast as possible), followed by a maintenance infusion of 20 IU in 1 litre of crystalloid over 4 hours.',
                    'options' => [
                        ['text' => '10 IU in 500 ml crystalloid over 10 minutes, then 20 IU in 1 litre over 4 hours', 'correct' => true],
                        ['text' => 'A single 10 IU IM injection only, with no further doses', 'correct' => false],
                        ['text' => '40 IU in 1 litre crystalloid as a rapid bolus', 'correct' => false],
                        ['text' => 'Oxytocin is not part of the first response bundle', 'correct' => false],
                        ['text' => '5 IU IV push every 5 minutes until bleeding stops', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'A woman received 600 mcg of misoprostol during AMTSL and is now bleeding heavily. How much additional misoprostol should she receive as part of the first response bundle?',
                    'explanation' => 'The total sublingual misoprostol dose in the first response bundle is 800 mcg; if 600 mcg was already given during AMTSL, an additional 200 mcg should be given to reach the 800 mcg total.',
                    'options' => [
                        ['text' => 'An additional 800 mcg, for a total of 1400 mcg', 'correct' => false],
                        ['text' => 'An additional 200 mcg, to bring the total to 800 mcg', 'correct' => true],
                        ['text' => 'No additional misoprostol should be given under any circumstances', 'correct' => false],
                        ['text' => 'Repeat the full 600 mcg dose again', 'correct' => false],
                        ['text' => 'Switch entirely to ergometrine instead', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'What is the very first action the team should take on recognising that a woman has developed PPH?',
                    'explanation' => 'The first response bundle begins with shouting for help and quickly assembling the emergency team; the team leader then assigns roles while reassuring the mother.',
                    'options' => [
                        ['text' => 'Immediately transfer the patient to theatre without further assessment', 'correct' => false],
                        ['text' => 'Shout for help, assemble the emergency team, and assign roles', 'correct' => true],
                        ['text' => 'Wait for a senior clinician to arrive before doing anything', 'correct' => false],
                        ['text' => 'Give oral fluids only', 'correct' => false],
                        ['text' => 'Document the event before starting treatment', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'During AMTSL, when should controlled cord traction (CCT) be performed?',
                    'explanation' => 'Controlled cord traction should be performed only when the uterus is contracting, with counter-pressure applied on the uterus above the pubic bone to prevent uterine inversion.',
                    'options' => [
                        ['text' => 'Continuously, regardless of contractions', 'correct' => false],
                        ['text' => 'Only when the patient is having a contraction, with counter-pressure on the uterus', 'correct' => true],
                        ['text' => 'Only after the placenta has already separated spontaneously', 'correct' => false],
                        ['text' => 'Before the uterotonic is given', 'correct' => false],
                        ['text' => 'CCT should never be used during AMTSL', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'If bleeding continues despite the E-MOTIVE first response bundle, which of the following is an appropriate next step?',
                    'explanation' => 'When bleeding persists despite the first response bundle, escalation options include bimanual uterine compression, uterine tamponade, compression of the abdominal aorta, transfer to theatre, referral, and application of the NASG for transport.',
                    'options' => [
                        ['text' => 'Repeat only the oxytocin infusion and wait, with no other escalation', 'correct' => false],
                        ['text' => 'Escalate care: bimanual uterine compression, uterine tamponade, aortic compression, transfer to theatre, or referral as indicated', 'correct' => true],
                        ['text' => 'Discharge the patient once she says she feels better', 'correct' => false],
                        ['text' => 'Stop all uterotonics immediately', 'correct' => false],
                        ['text' => 'Wait 24 hours before reassessing', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'Once bleeding is controlled after PPH management, how frequently should uterine tone, vaginal bleeding, and vital signs be monitored?',
                    'explanation' => 'Monitoring is done every 15 minutes for the first 2 hours, then every 30 minutes for the next 4 hours, once bleeding is controlled.',
                    'options' => [
                        ['text' => 'Every 15 minutes for 2 hours, then every 30 minutes for the next 4 hours', 'correct' => true],
                        ['text' => 'Once every 6 hours', 'correct' => false],
                        ['text' => 'Only at the next scheduled ward round', 'correct' => false],
                        ['text' => 'Every 5 minutes for 24 hours continuously', 'correct' => false],
                        ['text' => 'Monitoring is not required once bleeding appears controlled', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'Which blood samples should be drawn as part of the first response bundle for a woman with PPH?',
                    'explanation' => 'Full blood count, group and cross-match, urea/electrolytes/creatinine, and a coagulation profile should all be sent as part of the initial workup.',
                    'options' => [
                        ['text' => 'Full blood count, group and cross-match, U/E/C, and coagulation profile', 'correct' => true],
                        ['text' => 'Only a random blood sugar', 'correct' => false],
                        ['text' => 'Only a malaria test', 'correct' => false],
                        ['text' => 'No blood samples are needed if the bleeding looks moderate', 'correct' => false],
                        ['text' => 'Only a pregnancy test', 'correct' => false],
                    ],
                ],
                [
                    'text' => 'Which communication practices should the team demonstrate throughout the PPH simulation?',
                    'explanation' => 'Good practice includes calling out/shouting for help, closing the loop with clear feedback, using SBAR, thinking out loud, addressing the client by name, and following a clear referral process.',
                    'options' => [
                        ['text' => 'Silent teamwork with no verbal communication, to avoid alarming the client', 'correct' => false],
                        ['text' => 'Calling out for help, using SBAR, closing the loop, thinking out loud, and addressing the client by name', 'correct' => true],
                        ['text' => 'Only the most senior team member should speak', 'correct' => false],
                        ['text' => 'Communication is only needed after the emergency is over', 'correct' => false],
                        ['text' => 'Documentation replaces the need for verbal handover', 'correct' => false],
                    ],
                ],
            ],
        ];
    }
}
