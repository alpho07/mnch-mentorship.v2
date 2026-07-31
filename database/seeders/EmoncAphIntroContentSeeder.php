<?php

namespace Database\Seeders;

use App\Models\Program;
use App\Models\ProgramModule;
use App\Models\ProgramModuleContent;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Module 1 (Ante Partum Hemorrhage) was deliberately left untouched by last week's
 * EmONC content build-out (EmoncBatchA/B/C + PPH seeders) because it already had a
 * real hand-authored quiz. That skip also meant it never got the Introduction /
 * Expected Learning Outcome / Objectives / Workplan content every other EmONC
 * module received, so its mentee page silently renders without an Introduction
 * card (`$hasIntroContent` requires either a module description or an
 * `introduction`-typed ProgramModuleContent row — APH had neither). Source: "CHAI
 * APH Module Simulation Drill with Pre and Post test.docx".
 */
class EmoncAphIntroContentSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $program = Program::where('name', 'Maternal Health (EmONC)')->firstOrFail();

            $module = ProgramModule::where('program_id', $program->id)
                ->where('name', 'like', '%Ante Partum Hemorrhage%')
                ->whereNull('parent_id')
                ->firstOrFail();

            ProgramModuleContent::firstOrCreate(
                [
                    'program_module_id' => $module->id,
                    'type' => 'introduction',
                    'title' => 'Introduction — Antepartum Haemorrhage (APH)',
                ],
                [
                    'content' => 'This module is a structured emergency obstetric simulation (drill) for the '
                        .'recognition and initial management of antepartum haemorrhage (APH). It includes a case '
                        .'scenario, a step-by-step scenario progression, a debrief guide, and a paired pre-test and '
                        .'post-test to measure knowledge gained.'."\n\n"
                        .'Key highlights: communicate with the client (respectful maternity care) and the team (SBAR, '
                        .'closed-loop communication, call-outs, thinking aloud); recognize APH early and stabilize '
                        .'using the DRCAB approach — left lateral tilt, oxygen, large-bore IV access, blood sampling '
                        .'and cross-match; avoid digital vaginal examination until placenta praevia is excluded; '
                        .'differentiate abruption from praevia, use point-of-care ultrasound (OPOCUS), and escalate '
                        .'promptly by activating the massive obstetric haemorrhage protocol and preparing for '
                        .'emergency caesarean section.',
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
                    'content' => 'By the end of this module, the mentee should be able to recognize antepartum '
                        .'haemorrhage early and distinguish placental abruption from placenta praevia on clinical '
                        .'grounds, carry out immediate maternal stabilization using the DRCAB approach (left lateral '
                        .'tilt, oxygen, two large-bore cannulae and blood sampling), avoid contraindicated '
                        .'examinations — particularly digital vaginal examination before praevia is excluded — use '
                        .'point-of-care ultrasound (OPOCUS) appropriately and interpret findings, escalate promptly '
                        .'by activating the massive obstetric haemorrhage protocol and mobilizing theatre, blood '
                        .'bank, anaesthesia and the neonatal team, and communicate effectively with the woman '
                        .'(respectful maternity care) and the team (SBAR, closed-loop communication, call-outs, '
                        .'thinking aloud).',
                    'order_sequence' => 1,
                    'is_active' => true,
                ]
            );

            $module->update([
                'objectives' => [
                    'Recognize APH early and distinguish placental abruption from placenta praevia on clinical grounds.',
                    'Carry out immediate maternal stabilization using the DRCAB approach, including left lateral tilt, oxygen, two large-bore cannulae and blood sampling.',
                    'Avoid contraindicated examinations, in particular digital vaginal examination before praevia is excluded.',
                    'Use point-of-care ultrasound (OPOCUS) appropriately and interpret findings.',
                    'Escalate promptly: activate the massive obstetric haemorrhage protocol and mobilize theatre, blood bank, anaesthesia and the neonatal team.',
                    'Communicate effectively with the woman (respectful maternity care) and the team (SBAR, closed-loop communication, call-outs, thinking aloud).',
                ],
                'content' => [
                    ['label' => 'Drill', 'duration' => '10-12 min'],
                    ['label' => 'Debrief', 'duration' => '25-30 min'],
                ],
            ]);
        });

        $this->command->info('EmONC Module 1 (APH) introduction/outcome/objectives/workplan content seeded successfully.');
    }
}
