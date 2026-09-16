<?php

namespace App\Services\Analytics;

use App\Models\County;
use App\Models\Facility;
use App\Models\Training;
use App\Models\User;
use App\Models\TrainingParticipant;

class TrainingInsightsService {

    /**
     * Scope accepts:
     *   - county_id?, facility_id?, training_id? / training_ids?
     *   - training_types?: ['global_trainings','facility_mentorship']
     *   - filters?: completion|status|search
     */
    public function summarize(array $scope): array {
        $countyId = $scope['county_id'] ?? null;
        $facilityId = $scope['facility_id'] ?? null;
        $trainingIds = $this->resolveTrainingIds($scope);
        $types = array_values(array_filter((array) ($scope['training_types'] ?? [])));

        $q = TrainingParticipant::query()
                ->when(!empty($trainingIds), fn($qq) => $qq->whereIn('training_id', $trainingIds))
                ->when(!empty($types), fn($qq) => $qq->whereHas('training', fn($w) => $w->whereIn('type', $types)))
                ->when($countyId, fn($qq) => $qq->whereHas('user.facility.subcounty', fn($w) => $w->where('county_id', $countyId)))
                ->when($facilityId, fn($qq) => $qq->whereHas('user', fn($w) => $w->where('facility_id', $facilityId)))
                ->with([
                    'training:id,title',
                    'user:id,name,email,facility_id',
                    'user.facility:id,name,subcounty_id',
                    'user.facility.subcounty:id,county_id',
                    'user.facility.subcounty.county:id,name',
        ]);        
        

        $this->applyFilters($q, $scope['filters'] ?? []);
        $participants = $q->get();
        
        //dd($participants);

        $total = $participants->count();
        $completed = $participants->where('completion_status', 'completed')->count();
        $assessed = $participants->whereNotNull('outcome_id')->count();
        $passed = $participants->where('outcome_id', 1)->count();

        $completionRate = $total ? round($completed / $total * 100, 1) : 0.0;
        $passRate = $assessed ? round($passed / $assessed * 100, 1) : 0.0;

        $byCounty = [];
        $byFacility = [];
        foreach ($participants as $p) {
            $countyName = optional($p->user?->facility?->subcounty?->county)->name;
            $facilityName = optional($p->user?->facility)->name;
            if ($countyName)
                $byCounty[$countyName] = ($byCounty[$countyName] ?? 0) + 1;
            if ($facilityName)
                $byFacility[$facilityName] = ($byFacility[$facilityName] ?? 0) + 1;
        }
        arsort($byCounty);
        arsort($byFacility);
        $topCounties = array_slice(array_keys($byCounty), 0, 3);
        $topFacilities = array_slice(array_keys($byFacility), 0, 5);

        $where = [];
        if ($countyId && ($c = County::find($countyId)))
            $where[] = $c->name;
        if ($facilityId && ($f = Facility::find($facilityId)))
            $where[] = $f->name;
        if (empty($scope['training_ids']) && !empty($scope['training_id'])) {
            if ($t = Training::find($scope['training_id']))
                $where[] = ($t->title ?? $t->name ?? "Training #{$t->id}");
        }
        if (!empty($types))
            $where[] = 'Type: ' . implode(' + ', $types);
        $whereText = $where ? ' in ' . implode(' › ', $where) : '';

        $overview = "{$total} participants{$whereText}; completion {$completionRate}%, pass rate {$passRate}%.";

        $drivers = [];
        if (!empty($topCounties))
            $drivers[] = 'Top counties: ' . implode(', ', $topCounties);
        if (!empty($topFacilities))
            $drivers[] = 'Top facilities: ' . implode(', ', $topFacilities);

        $risks = [];
        if ($completionRate < 60 && $total > 20)
            $risks[] = 'Low completion — target follow-ups';
        if ($passRate < 70 && $assessed > 20)
            $risks[] = 'Pass rate < 70% — review quality/prereqs';

        $actions = [];
        if ($completed < $total)
            $actions[] = 'Automate reminders for in-progress learners';
        if ($assessed < $total)
            $actions[] = 'Increase assessment coverage';
        if ($passRate >= 85)
            $actions[] = 'Scale playbooks from top facilities';

        return [
            'metrics' => [
                'total_participants' => $total,
                'completed' => $completed,
                'assessed' => $assessed,
                'passed' => $passed,
                'completion_rate' => $completionRate,
                'pass_rate' => $passRate,
                'average_score' => 0,
            ],
            'overview' => $overview,
            'drivers' => $drivers,
            'risks' => $risks,
            'actions' => $actions,
        ];
    }

    public function summarizeParticipant(User $user, array $stats): array {
        $headline = "Outlook for {$user->name}: "
                . (($stats['pass_rate'] ?? 0) >= 80 ? 'High-performing' : ((($stats['pass_rate'] ?? 0) >= 60) ? 'Solid foundation' : 'Needs support'));

        $notes = [];
        if (($stats['completion_rate'] ?? 0) < 60)
            $notes[] = 'Low completion — schedule mentorship';
        if (($stats['pass_rate'] ?? 0) < 70 && ($stats['assessed_trainings'] ?? 0) >= 3)
            $notes[] = 'Pass trend suggests targeted coaching';
        if (($stats['average_score'] ?? 0) >= 80)
            $notes[] = 'High performer — consider advanced modules';

        return ['headline' => $headline, 'recommendations' => $notes];
    }

    // --------------- helpers ---------------

    private function resolveTrainingIds(array $scope): array {
        if (!empty($scope['training_ids']) && is_array($scope['training_ids'])) {
            $ids = array_values(array_unique(array_map('intval', $scope['training_ids'])));
            sort($ids);
            return $ids;
        }
        if (!empty($scope['training_id']))
            return [(int) $scope['training_id']];

        $types = array_values(array_filter((array) ($scope['training_types'] ?? [])));

        return TrainingParticipant::query()
                        ->select('training_id')
                        ->whereNotNull('training_id')
                        ->when(!empty($types), fn($q) => $q->whereHas('training', fn($w) => $w->whereIn('type', $types)))
                        ->distinct()
                        ->pluck('training_id')
                        ->map(fn($v) => (int) $v)
                        ->values()
                        ->all();
    }

    private function applyFilters($q, array $filters): void {
        if (!empty($filters['completion']))
            $q->where('completion_status', $filters['completion']);
        if (!empty($filters['status'])) {
            if ($filters['status'] === 'passed')
                $q->where('outcome_id', 1);
            elseif ($filters['status'] === 'failed')
                $q->where('outcome_id', 2);
            elseif ($filters['status'] === 'not_assessed')
                $q->whereNull('outcome_id');
        }
        if (!empty($filters['search'])) {
            $term = $filters['search'];
            $q->whereHas('user', fn($w) => $w->where(function ($s) use ($term) {
                        $s->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%");
                    }));
        }
    }
}