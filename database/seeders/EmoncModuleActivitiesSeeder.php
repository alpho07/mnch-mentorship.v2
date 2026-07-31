<?php

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\Program;
use App\Models\ProgramModule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Corrects EmONC module/track activity assignments to match "EmONC Modules
 * Summary.docx" (project root). EmoncProgramSeeder originally blanket-assigned
 * all 3 activities (CME, Hands-on Demo, Drill) to every module and every PPH
 * track — the summary doc shows each module actually uses a specific subset,
 * and PPH's 11 tracks are hands-on only except the NASG track (CME + hands-on).
 *
 * "Respectful Maternity Care" and "Communication" in the source doc are
 * cross-cutting themes woven into module content (e.g. APH's introduction and
 * video content already cover respectful maternity care) — they aren't
 * separate ProgramModule rows, so there's nothing to map them to here.
 */
class EmoncModuleActivitiesSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $program = Program::where('name', 'Maternal Health (EmONC)')->firstOrFail();
            $activityIds = Activity::whereIn('name', ['CME', 'Hands-on Demo', 'Drill'])
                ->pluck('id', 'name');

            $cme = [$activityIds['CME']];
            $cmeDrill = [$activityIds['CME'], $activityIds['Drill']];
            $cmeHandsOn = [$activityIds['CME'], $activityIds['Hands-on Demo']];
            $all = [$activityIds['CME'], $activityIds['Hands-on Demo'], $activityIds['Drill']];
            $handsOnOnly = [$activityIds['Hands-on Demo']];

            $moduleActivities = [
                'Ante Partum Hemorrhage' => $cmeDrill,
                'Labour Care Guide (LCG)' => $cmeHandsOn,
                'Prolonged & Obstructed Labour' => $cme,
                'Active Management of the Third Stage of Labor (AMSTL)' => $all,
                'Management of Postpartum Hemorrhage (PPH)' => $all,
                'Management of Cord Prolapse' => $cmeDrill,
                'Vaginal Breech Delivery' => $all,
                'Shoulder Dystocia Delivery' => $all,
                'Vaginal Vacuum Assisted Delivery' => $all,
                'Management of Maternal Shock' => $cme,
                'Maternal Resuscitation' => $all,
                'Management of Pre-Eclampsia/Eclampsia' => $cmeDrill,
                'Immediate Neonatal Resuscitation' => $all,
            ];

            foreach ($moduleActivities as $name => $ids) {
                $module = ProgramModule::where('program_id', $program->id)
                    ->where('name', 'like', "%{$name}%")
                    ->whereNull('parent_id')
                    ->first();

                $module?->activities()->sync($ids);
            }

            // PPH tracks: hands-on only, except NASG (CME + hands-on).
            $pphParent = ProgramModule::where('program_id', $program->id)
                ->where('name', 'like', '%Management of Postpartum Hemorrhage%')
                ->whereNull('parent_id')
                ->firstOrFail();

            ProgramModule::where('parent_id', $pphParent->id)->get()->each(function (ProgramModule $track) use ($cmeHandsOn, $handsOnOnly) {
                $isNasg = str_contains($track->name, 'non-pneumatic anti-shock garment');
                $track->activities()->sync($isNasg ? $cmeHandsOn : $handsOnOnly);
            });
        });

        $this->command->info('EmONC module/track activities corrected to match EmONC Modules Summary.docx.');
    }
}
