<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Loads skills-lab devices from the validated distribution exports.
 *
 * Both CSVs are the audited output of the facility-matching pipeline: every
 * row already carries a verified MFL code and facilities.id, so the seeder
 * resolves on mfl_code and refuses any row it cannot place rather than
 * silently attaching devices to the wrong facility.
 */
class SkillsLabDeviceSeeder extends Seeder
{
    private const SOURCES = [
        ['file' => 'facility_device_inventory_manikins.csv', 'source' => 'manikin distribution'],
        ['file' => 'facility_device_inventory_pulse_oximeter.csv', 'source' => 'pulse oximeter distribution'],
    ];

    private const GROUPS = [
        'PREEMIE NATALIE' => 'manikin',
        'NEO NATALIE' => 'manikin',
        'BABY ANNE' => 'manikin',
        'AIR DEVICE' => 'air_device',
        'PULSE OXIMETER' => 'pulse_oximeter',
    ];

    public function run(): void
    {
        DB::table('skills_lab_devices')->truncate();

        $known = DB::table('facilities')->whereNull('deleted_at')
            ->whereNotNull('mfl_code')->pluck('id', 'mfl_code');

        $loaded = 0;
        $skipped = [];

        foreach (self::SOURCES as $src) {
            $path = storage_path('app/exports/'.$src['file']);
            if (! is_file($path)) {
                $this->command?->warn("missing: {$src['file']}");
                continue;
            }

            $fh = fopen($path, 'r');
            $header = fgetcsv($fh);
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);   // strip the Excel BOM
            $ix = array_flip($header);

            $batch = [];
            while (($row = fgetcsv($fh)) !== false) {
                $mfl = trim($row[$ix['MFL_CODE']] ?? '');
                $device = strtoupper(trim($row[$ix['DEVICE']] ?? ''));
                $facilityId = $known[$mfl] ?? null;

                if ($facilityId === null || ! isset(self::GROUPS[$device])) {
                    $skipped[] = $mfl.' / '.$device;
                    continue;
                }

                $batch[] = [
                    'facility_id' => $facilityId,
                    'device' => $device,
                    'device_group' => self::GROUPS[$device],
                    'asset_tag' => trim($row[$ix['SEQ']] ?? '') ?: null,
                    'quantity' => max(1, (int) ($row[$ix['QUANTITY']] ?? 1)),
                    'source' => $src['source'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                if (count($batch) >= 500) {
                    DB::table('skills_lab_devices')->insert($batch);
                    $loaded += count($batch);
                    $batch = [];
                }
            }
            fclose($fh);

            if ($batch) {
                DB::table('skills_lab_devices')->insert($batch);
                $loaded += count($batch);
            }
        }

        $this->command?->info("skills_lab_devices: {$loaded} rows loaded, ".count($skipped).' skipped');
        foreach (array_slice($skipped, 0, 10) as $s) {
            $this->command?->warn("  skipped: {$s}");
        }
    }
}
