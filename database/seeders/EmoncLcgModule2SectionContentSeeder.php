<?php

namespace Database\Seeders;

use App\Models\Program;
use App\Models\ProgramModule;
use App\Models\ProgramModuleContent;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Replaces Module 2's ("Labour Care Guide") mentee case-scenario content with 7
 * section-based "Practice" narratives (patient "Mary Jane") and adds matching
 * mentor-only theory + figures per section, sourced from
 * "LCG webinar presentation 2.pptx". See
 * docs/superpowers/specs/2026-08-20-emonc-module2-lcg-section-content-design.md.
 */
class EmoncLcgModule2SectionContentSeeder extends Seeder
{
    private const ASSET_DIR = 'program-module-content/emonc-module-2-lcg';

    public function run(): void
    {
        DB::transaction(function () {
            $program = Program::where('name', 'Maternal Health (EmONC)')->firstOrFail();

            $module = ProgramModule::where('program_id', $program->id)
                ->where('name', 'like', '%Labour Care Guide%')
                ->whereNull('parent_id')
                ->firstOrFail();

            ProgramModuleContent::where('program_module_id', $module->id)
                ->whereIn('title', [
                    'LCG Simulation Drill — Case Scenario',
                    'LCG Simulation Drill — Scenario Progression',
                ])
                ->delete();

            $img = $this->publishImages();

            $this->seedMenteePracticeRows($module, $img);
            $this->seedMentorMaterialsRows($module, $img);
        });

        $this->command->info('EmONC Module 2 (LCG) section-based mentee/mentor content seeded successfully.');
    }

    /**
     * Copy the committed figures into public storage (skips files that already
     * exist so the seeder stays idempotent) and return a map of
     * filename => public URL.
     */
    private function publishImages(): array
    {
        $files = [
            'section-1-fig-a.png', 'section-1-fig-b.png',
            'section-2-fig-a.png', 'section-2-fig-b.png',
            'section-3-fig-a.png', 'section-3-fig-b.png', 'section-3-fig-c.png',
            'section-4-fig-a.png', 'section-4-fig-b.png',
            'section-5-fig-a.png', 'section-5-fig-b.png', 'section-5-fig-c.png', 'section-5-fig-d.png', 'section-5-fig-e.png',
            'section-6-fig-a.png',
            'section-7-fig-a.png', 'section-7-fig-b.png', 'section-7-fig-c.png',
            'appendix-sample-completed-lcg.png',
        ];

        $disk = Storage::disk('public');
        $urls = [];

        foreach ($files as $file) {
            $storagePath = self::ASSET_DIR.'/'.$file;

            if (! $disk->exists($storagePath)) {
                $sourcePath = database_path('seeders/assets/emonc-module2-lcg/'.$file);
                $disk->put($storagePath, file_get_contents($sourcePath));
            }

            $urls[$file] = $disk->url($storagePath);
        }

        return $urls;
    }

    private function seedMenteePracticeRows(ProgramModule $module, array $img): void
    {
        $rows = [
            [
                'title' => 'Section 1 — Practice',
                'content' => 'Date: June 07, 2020. Time: 06:00. Mary Jane presented with contractions and reports '
                    .'that she has experienced leakage of fluid from the vagina for the last hour. Her gestational '
                    .'age is 38 weeks. This is her fourth pregnancy. She previously had two births, one of a live '
                    .'baby and one of a stillbirth at term. She also had a miscarriage. She is taking oral iron to '
                    .'treat anaemia. Cervical dilatation was 5 cm at 06:00 am.',
            ],
            [
                'title' => 'Section 2 — Practice',
                'content' => 'Time 06:00. Mary Jane is not accompanied by a relative or someone from her social '
                    .'network. She reports feeling significant pain due to the uterine contractions, and requests '
                    ."pain relief. She drank a fruit juice and is walking.\n\n"
                    .'Time 07:00. Mary Jane is with her sister and using relaxation techniques for pain relief with '
                    .'good results. She has been drinking water when thirsty, and Mary Jane is now lying in bed in '
                    .'a supine position.',
            ],
            [
                'title' => 'Section 3 — Practice',
                'content' => 'Time 06:00. The baby moves during monitoring and has a heart rate of 140 beats per '
                    .'minute (bpm). There are no decelerations. Vaginal examination shows 5 cm cervical dilatation, '
                    .'cephalic presentation. There is no caput or moulding and the fetal position is occiput '
                    ."posterior. Amniotic fluid is clear.\n\n"
                    .'Time 07:00. FHR 132 bpm. There are variable decelerations.',
            ],
            [
                'title' => 'Section 4 — Practice',
                'content' => "Time 06:00. Mary Jane's pulse rate is 88 bpm, with blood pressure of 120/80 mmHg. "
                    .'Her temperature is 36.5°C. She passed urine at admission, without proteinuria or acetone.',
            ],
            [
                'title' => 'Section 5 — Practice',
                'content' => 'Time 06:00. At the time of admission, Mary Jane presented with three uterine '
                    .'contractions every 10 minutes, lasting 40 seconds. Vaginal examination shows 5 cm cervical '
                    ."dilatation. Fetal descent is 4/5.\n\n"
                    .'Time 07:00. Three contractions in 10 minutes, lasting 40 seconds.',
            ],
            [
                'title' => 'Sections 6 & 7 — Practice',
                'content' => "Section 6: Medication. There was no medication given.\n\n"
                    .'Section 7: Shared decision-making. Make an assessment and plan based on the findings.',
            ],
        ];

        foreach ($rows as $order => $row) {
            ProgramModuleContent::updateOrCreate(
                [
                    'program_module_id' => $module->id,
                    'type' => 'case_scenario',
                    'title' => $row['title'],
                ],
                [
                    'content' => $row['content'],
                    'order_sequence' => $order + 1,
                    'is_active' => true,
                ]
            );
        }
    }

    private function seedMentorMaterialsRows(ProgramModule $module, array $img): void
    {
        $rows = [
            [
                'title' => 'Section 1 — Admission: Identifying Information and Labour Characteristics',
                'content' => '**New recommendation:** do not conduct routine clinical pelvimetry on admission of '
                    ."healthy pregnant women in labour.\n\n"
                    .'Decisions on place of birth, level of care provision, the type of provider who should lead '
                    .'management of her care, and the frequency of monitoring should be based on risk factors and '
                    ."their potential impact on birth outcome.\n\n"
                    ."![Fig. 2: Section 1 admission fields on the WHO Labour Care Guide]({$img['section-1-fig-a.png']})\n\n"
                    ."![Fig. 3: How to complete Section 1]({$img['section-1-fig-b.png']})",
            ],
            [
                'title' => 'Section 2 — Supportive Care',
                'content' => 'Supportive-care parameters, recorded Y/N/D (Yes/No/Woman declines) at each '
                    ."assessment:\n\n"
                    ."- **Companionship** — Y = Yes, N = No, D = Woman declines\n"
                    ."- **Pain relief** — Y = Yes, N = No, D = Woman declines\n"
                    ."- **Oral fluid** — Y = Yes, N = No, D = Woman declines\n"
                    ."- **Posture** — SP = Supine, MO = Mobile\n\n"
                    ."All four of these supportive-care parameters are new to the Labour Care Guide.\n\n"
                    ."![Fig. 4: Section 2 supportive-care fields]({$img['section-2-fig-a.png']})\n\n"
                    ."![Fig. 5: Section 2 supportive care, completed example]({$img['section-2-fig-b.png']})",
            ],
            [
                'title' => 'Section 3 — Well-being of the Baby',
                'content' => 'Baseline fetal heart rate (FHR) and decelerations, fetal position, and caput are all '
                    ."new fields on the Labour Care Guide.\n\n"
                    ."**WHO 2018 recommendations for intrapartum care:**\n"
                    .'> "Intermittent auscultation of the fetal heart rate with either a Doppler ultrasound or '
                    ."Pinard stethoscope is recommended for healthy pregnant women.\"\n"
                    .'> "Continuous cardiotocography is not recommended in healthy women undergoing spontaneous '
                    ."labour.\"\n\n"
                    ."If it is difficult to assess FHR in the woman's chosen position, explain why she needs to "
                    .'change position, assist her to change position and assess FHR, then assist her back into her '
                    ."chosen position.\n\n"
                    .'**Amniotic fluid** (alert!): I = Intact membranes; C = Membranes ruptured, clear fluid; '
                    .'M = Meconium-stained fluid — record + (non-significant), ++ (medium), +++ (thick); '
                    ."B = Blood-stained meconium.\n\n"
                    .'**Moulding** (alert!): 0 = None; + = Sutures apposed; ++ = Sutures overlapped but reducible; '
                    ."+++ = Sutures overlapped and not reducible.\n\n"
                    .'**Fetal position** (alert!): A = Any occiput anterior position; P = Any occiput posterior '
                    ."position; T = Any occiput transverse position.\n\n"
                    ."**Caput** (alert!): 0 = None; + ; ++ ; +++ = Marked.\n\n"
                    ."Assess all parameters that require a vaginal examination at the same time.\n\n"
                    ."![Fig. 6: Section 3 well-being of the baby fields]({$img['section-3-fig-a.png']})\n\n"
                    ."![Fig. 7: Amniotic fluid and moulding alert codes]({$img['section-3-fig-b.png']})\n\n"
                    ."![Fig. 8: Section 3, completed example]({$img['section-3-fig-c.png']})",
            ],
            [
                'title' => 'Section 4 — Well-being of the Woman',
                'content' => 'Pulse and blood pressure values are recorded as numbers rather than being plotted on '
                    ."a graph.\n\n"
                    .'The frequency of recording maternal well-being on the Labour Care Guide depends on the '
                    ."woman's clinical status.\n\n"
                    ."![Fig. 9: Section 4 well-being of the woman fields]({$img['section-4-fig-a.png']})\n\n"
                    ."![Fig. 10: Section 4, completed example]({$img['section-4-fig-b.png']})",
            ],
            [
                'title' => 'Section 5 — Labour Progress (First & Second Stage)',
                'content' => "**Key WHO recommendations — first stage:**\n\n"
                    .'Women should be informed that a standard duration of the latent first stage has not been '
                    ."established and can vary widely from one woman to another.\n\n"
                    .'Women should be informed that the duration of the active first stage (from 5 cm until full '
                    .'cervical dilatation) usually does not extend beyond 12 hours in first labours and usually '
                    ."does not extend beyond 10 hours in subsequent labours.\n\n"
                    .'Labour may not naturally accelerate until a cervical dilatation threshold of 5 cm is '
                    ."reached.\n\n"
                    .'For women with spontaneous labour onset, a cervical dilatation rate threshold of 1 cm/hour '
                    .'during the active first stage is inaccurate for identifying women at risk of adverse birth '
                    .'outcomes and is therefore not recommended. A slower than 1 cm/hour dilatation rate alone '
                    .'should not be an indication for obstetric intervention — first carefully evaluate to exclude '
                    .'developing complications (e.g. cephalo-pelvic disproportion) and to determine whether the '
                    ."woman's emotional, psychological and physical needs in labour are being met. In facilities "
                    .'where interventions such as augmentation and caesarean section cannot be performed and '
                    .'referral is difficult, the alert line can still be used for triaging women who may require '
                    ."additional care.\n\n"
                    .'**Second stage — parameters recorded:** contractions (duration and frequency per 10 min), '
                    ."pushing, and descent.\n\n"
                    ."**Key WHO recommendations — second stage:**\n\n"
                    .'Women should be informed that in first labours, birth is usually completed within 3 hours, '
                    ."whereas in subsequent labours birth is usually completed within 2 hours.\n\n"
                    ."Encouraging the adoption of a birth position of the woman's own choice, including upright "
                    ."positions, is recommended.\n\n"
                    .'Women in the expulsive phase of the second stage should be encouraged and supported to '
                    .'follow their own urge to push. Health care providers should avoid imposing directed pushing, '
                    ."as there is no evidence of benefit from this technique.\n\n"
                    .'**Phases of the second stage:** the early (non-expulsive) phase is when the cervix is 10 cm '
                    .'but the woman does not yet have the urge to push; the late (expulsive) phase is when the '
                    ."presenting part of the fetus reaches the pelvic floor and the woman has the urge to push.\n\n"
                    ."![Fig. 11: Section 5 first-stage labour progress fields]({$img['section-5-fig-a.png']})\n\n"
                    ."![Fig. 12: Section 5 first-stage, completed example]({$img['section-5-fig-b.png']})\n\n"
                    ."![Fig. 13: Section 5 second-stage labour progress fields]({$img['section-5-fig-c.png']})\n\n"
                    ."![Fig. 14: Section 5 second-stage, completed example]({$img['section-5-fig-d.png']})\n\n"
                    ."![Fig. 15: Section 5, full completed example]({$img['section-5-fig-e.png']})",
            ],
            [
                'title' => 'Section 6 — Medications',
                'content' => '**Oxytocin** — is oxytocin currently being administered to the woman? If not, '
                    .'record N = No. If it is, record the amount in units per litre (U/L) and drops per minute '
                    ."(drops/min), and record the amount administered every 60 minutes.\n\n"
                    .'**Medicine** — if no other medication is being administered, record N = No. Otherwise record '
                    .'the name, dose and route of administration of any additional medication given during active '
                    ."first or second stage of labour (e.g. 50 mg pethidine, intramuscular (IM)).\n\n"
                    .'**IV fluid** — is the woman on IV fluids? Record Y = Yes or N = No. The routine '
                    .'administration of IV fluids for all women in labour is not recommended, as it reduces the '
                    ."woman's mobility and unnecessarily increases costs. Low-risk women should be encouraged to "
                    ."drink oral fluids and should receive IV fluids only if indicated.\n\n"
                    ."![Fig. 16: Section 6 medications fields]({$img['section-6-fig-a.png']})",
            ],
            [
                'title' => 'Section 7 — Shared Decision-Making',
                'content' => '**Making an assessment and developing the plan of care** are the final steps of '
                    ."each Labour Care Guide cycle.\n\n"
                    ."**Types of decision-making:**\n"
                    ."- *Paternalistic* — information & recommendations only.\n"
                    .'- *Informed medical decision-making* — information & recommendations, with evidence-based '
                    ."medicine as the basis for optimal patient care.\n"
                    ."- *Shared decision-making* — information & options for care, combined with the woman's "
                    .'values, preferences and concerns, achieved through patient-centred communication between '
                    ."clinician and patient.\n\n"
                    ."Develop and record the plan of care based on the assessment.\n\n"
                    .'**Record your initials** — the provider initials the findings, assessment, and plan they '
                    ."have recorded.\n\n"
                    ."![Fig. 17: Types of decision-making]({$img['section-7-fig-a.png']})\n\n"
                    ."![Fig. 18: Recording provider initials]({$img['section-7-fig-b.png']})\n\n"
                    ."![Fig. 19: Section 6 & 7, completed example]({$img['section-7-fig-c.png']})",
            ],
            [
                'title' => 'Sample Completed LCG (Reference)',
                'content' => 'A fully completed Labour Care Guide for the case used throughout this module, '
                    ."showing every section filled in from admission through to birth.\n\n"
                    ."![Sample completed LCG — Mary Jane]({$img['appendix-sample-completed-lcg.png']})",
            ],
        ];

        foreach ($rows as $order => $row) {
            ProgramModuleContent::updateOrCreate(
                [
                    'program_module_id' => $module->id,
                    'type' => 'mentor_materials',
                    'title' => $row['title'],
                ],
                [
                    'content' => $row['content'],
                    'order_sequence' => $order + 1,
                    'is_active' => true,
                ]
            );
        }
    }
}
