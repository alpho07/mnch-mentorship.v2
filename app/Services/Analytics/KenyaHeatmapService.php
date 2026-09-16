<?php


namespace App\Services\Analytics;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use App\Models\County;
use App\Models\TrainingParticipant;

class KenyaHeatmapService
{
    /**
     * Scope:
     *   - training_ids?:  int[]      // explicit set of trainings
     *   - training_types?: string[]  // ['global_trainings','facility_mentorship']
     */
    public function getMapData(array $scope = []): array
    {
        [$trainingIds, $typeKey] = $this->resolveTrainingIds($scope);
        $cacheKey = 'heatmap:data:trainings:' . $typeKey . ':' . (empty($trainingIds) ? 'none' : substr(sha1(implode(',', $trainingIds)), 0, 16));

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($trainingIds) {
            $countyRows = $this->buildCountyRows($trainingIds);

            return [
                'countyData'        => $countyRows->values()->all(),
                'intensityLevels'   => $this->legend(),
                'totalTrainings'    => (int) $countyRows->sum('trainings'),
                'totalParticipants' => (int) $countyRows->sum('participants'),
                'totalFacilities'   => (int) $countyRows->sum('facilities'),
                'hasData'           => $countyRows->sum('trainings') > 0,
                'summary'           => [
                    'counties_with_training'        => $countyRows->where('trainings', '>', 0)->count(),
                    'avg_participants_per_training' => ($countyRows->sum('trainings') > 0)
                        ? round($countyRows->sum('participants') / max(1, $countyRows->sum('trainings')), 1)
                        : 0,
                    'top_counties'                  => $countyRows->sortByDesc('intensity')->take(5)->pluck('name'),
                ],
            ];
        });
    }

    public function getAIInsights(array $scope = []): array
    {
        [$trainingIds, $typeKey] = $this->resolveTrainingIds($scope);
        $cacheKey = 'heatmap:ai:trainings:' . $typeKey . ':' . (empty($trainingIds) ? 'none' : substr(sha1(implode(',', $trainingIds)), 0, 16));

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($trainingIds) {
            $rows = $this->buildCountyRows($trainingIds);

            $totalCounties   = $rows->count();
            $activeCounties  = $rows->where('trainings', '>', 0)->count();
            $coveragePct     = $totalCounties ? round($activeCounties / $totalCounties * 100, 1) : 0;

            $totalTrainings    = $rows->sum('trainings');
            $totalParticipants = $rows->sum('participants');
            $avgPerTraining    = $totalTrainings ? round($totalParticipants / max(1, $totalTrainings), 1) : 0;

            $top  = $rows->where('trainings','>',0)->sortByDesc('intensity')->take(3)->pluck('name')->all();
            $zero = $rows->where('trainings', 0)->pluck('name')->take(3)->all();

            return [
                'coverage' => "Coverage at {$coveragePct}% ({$activeCounties}/{$totalCounties} counties) across selected trainings.",
                'participation' => "{$totalParticipants} participants overall (~{$avgPerTraining} per training).",
                'recommendations' =>
                    (empty($zero) ? 'All counties active.' : 'Underserved: ' . implode(', ', $zero) . '.') .
                    (!empty($top) ? ' Scale best practices from ' . implode(', ', $top) . '.' : ''),
            ];
        });
    }

    // -------------------- Internals --------------------

    /**
     * Resolve eligible training IDs from training_types and/or explicit training_ids.
     */
    private function resolveTrainingIds(array $scope): array
    {
        $explicitIds = array_values(array_filter(array_map('intval', (array)($scope['training_ids'] ?? []))));
        $types       = array_values(array_filter((array)($scope['training_types'] ?? [])));

        sort($explicitIds);
        sort($types);
        $typeKey = empty($types) ? 'alltypes' : implode(',', $types);

        if (!empty($explicitIds)) {
            return [$explicitIds, $typeKey];
        }

        // All trainings that have participants, optionally restricted by training.type
        $ids = TrainingParticipant::query()
            ->select('training_id')
            ->whereNotNull('training_id')
            ->when(!empty($types), function ($q) use ($types) {
                $q->whereHas('training', fn($w) => $w->whereIn('type', $types));
            })
            ->distinct()
            ->pluck('training_id')
            ->map(fn($v) => (int)$v)
            ->values()
            ->all();

        sort($ids);
        return [$ids, $typeKey];
    }

    private function legend(): array
    {
        return [
            ['label'=>'No Data',   'min'=>0,  'max'=>0,  'color'=>'#e5e7eb'],
            ['label'=>'Very Low',  'min'=>1,  'max'=>10, 'color'=>'#fca5a5'],
            ['label'=>'Low',       'min'=>11, 'max'=>25, 'color'=>'#fbbf24'],
            ['label'=>'Medium',    'min'=>26, 'max'=>50, 'color'=>'#a3a3a3'],
            ['label'=>'High',      'min'=>51, 'max'=>75, 'color'=>'#84cc16'],
            ['label'=>'Very High', 'min'=>76, 'max'=>100,'color'=>'#16a34a'],
        ];
    }

    private function buildCountyRows(array $trainingIds): Collection
    {
        $all = County::query()->orderBy('name')->get(['id','name']);
        if ($all->isEmpty()) return collect([]);

        $base = [];
        foreach ($all as $c) {
            $base[$c->id] = [
                'id'               => $c->id,
                'name'             => $c->name,
                'trainings'        => 0,
                'participants'     => 0,
                'facilities'       => 0,
                'training_details' => [],
            ];
        }

        $tp = TrainingParticipant::query()
            ->when(!empty($trainingIds), fn($q) => $q->whereIn('training_id', $trainingIds))
            ->with([
                'training:id,title',
                'user.facility:id,subcounty_id',
                'user.facility.subcounty:id,county_id',
                'user.facility.subcounty.county:id,name',
            ])
            ->get();

        foreach ($tp as $row) {
            $county = optional($row->user?->facility?->subcounty?->county);
            if (!$county?->id) continue;

            $cid = $county->id;
            $base[$cid]['participants']++;

            $tid = $row->training_id;
            if ($tid) {
                if (!isset($base[$cid]['training_details'][$tid])) {
                    $base[$cid]['training_details'][$tid] = [
                        'training_id' => $tid,
                        'title'       => $row->training?->title ?? $row->training?->name ?? "Training #{$tid}",
                        'participants'=> 0,
                        'facilities'  => [],
                    ];
                }
                $base[$cid]['training_details'][$tid]['participants']++;
            }

            $fid = optional($row->user?->facility)->id;
            if ($fid && $tid) {
                $base[$cid]['training_details'][$tid]['facilities'][$fid] = true;
            }
        }

        $rows = [];
        foreach ($base as $cid => $bucket) {
            $trainings = count($bucket['training_details']);
            $facilities = 0;
            foreach ($bucket['training_details'] as $td) {
                $facilities += count($td['facilities']);
            }
            $rows[] = [
                'id'              => $bucket['id'],
                'name'            => $bucket['name'],
                'trainings'       => $trainings,
                'participants'    => $bucket['participants'],
                'facilities'      => $facilities,
                'intensity'       => 0,
                'training_details'=> array_values($bucket['training_details']),
            ];
        }

        $maxP = collect($rows)->max('participants') ?: 0;
        foreach ($rows as &$r) {
            $r['intensity'] = $maxP ? round(($r['participants'] / $maxP) * 100) : 0;
        }
        unset($r);

        return collect($rows);
    }

    private function overallCompletionRate(array $trainingIds): float
    {
        $q = TrainingParticipant::query();
        if (!empty($trainingIds)) $q->whereIn('training_id', $trainingIds);
        $total = (clone $q)->count();
        if ($total === 0) return 0.0;
        $completed = (clone $q)->where('completion_status', 'completed')->count();
        return round($completed / $total * 100, 1);
    }

    private function overallPassRate(array $trainingIds): float
    {
        $q = TrainingParticipant::query();
        if (!empty($trainingIds)) $q->whereIn('training_id', $trainingIds);
        $assessed = (clone $q)->whereNotNull('outcome_id')->count();
        if ($assessed === 0) return 0.0;
        $passed = (clone $q)->where('outcome_id', 1)->count();
        return round($passed / $assessed * 100, 1);
    }
}