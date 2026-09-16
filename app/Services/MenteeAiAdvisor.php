<?php
// app/Services/MenteeAiAdvisor.php

namespace App\Services;

use App\Models\User;
use App\Models\MenteeStatusLog;
use Carbon\Carbon;

class MenteeAiAdvisor
{
    public static function analyze(User $user): array
    {
        $participations = $user->trainingParticipations()
            ->with(['training:id,title,type,lead_type,start_date,end_date'])
            ->get();

        $totalTrainings = $participations->count();
        $completedCount = (int) $participations->where('completion_status', 'completed')->count();
        $assessedCount  = (int) $participations->filter(fn($p) => !is_null($p->overall_score ?? null))->count();

        $lastDate = $participations
            ->map(fn($p) => $p->completion_date
                ?? optional($p->training)->end_date
                ?? optional($p->training)->start_date)
            ->filter()
            ->max();

        $daysSince = $lastDate ? Carbon::parse($lastDate)->diffInDays(now()) : null;

        $latestStatus = optional(
            $user->statusLogs()->orderByDesc('effective_date')->orderByDesc('id')->first()
        )->new_status ?? 'active';

        $attritionSet = method_exists(MenteeStatusLog::class, 'getAttritionStatuses')
            ? MenteeStatusLog::getAttritionStatuses()
            : ['resigned','transferred','retired','defected','deceased','suspended','dropped_out'];

        $risk = 'Low'; $rationale = [];

        if (in_array($latestStatus, $attritionSet, true)) {
            $risk = self::maxRisk($risk, 'Very High');
            $rationale[] = "Current status is attrition-related ({$latestStatus}).";
        }

        if ($daysSince === null) {
            $risk = self::maxRisk($risk, 'High');
            $rationale[] = 'No training history on record.';
        } else {
            if ($daysSince >= 365) { $risk = self::maxRisk($risk, 'High');   $rationale[] = 'No training within the last 12 months.'; }
            elseif ($daysSince >= 180) { $risk = self::maxRisk($risk, 'Medium'); $rationale[] = 'No training within the last 6 months.'; }
            elseif ($daysSince >= 90) { $rationale[] = 'More than 3 months since last training.'; }
        }

        if ($assessedCount > 0) {
            $avgScore = round($participations->avg(fn($p) => (float) ($p->overall_score ?? 0)), 1);
            if ($avgScore < 60)      { $risk = self::maxRisk($risk, 'High');   $rationale[] = "Average assessed score low ({$avgScore}%)."; }
            elseif ($avgScore < 75)  { $risk = self::maxRisk($risk, 'Medium'); $rationale[] = "Average assessed score moderate ({$avgScore}%)."; }
        }

        if ($totalTrainings >= 5 && $completedCount / max(1, $totalTrainings) < 0.6) {
            $risk = self::maxRisk($risk, 'Medium');
            $rationale[] = 'Low completion ratio across attended trainings.';
        }

        $recommendations = [];
        if ($daysSince === null || $daysSince >= 365)      { $recommendations[] = 'Schedule a refresher or advanced training within 30 days.'; }
        elseif ($daysSince >= 180)                         { $recommendations[] = 'Book a competency check or mentorship in 30–60 days.'; }
        elseif ($daysSince >= 90)                          { $recommendations[] = 'Light touch check-in (skills practice/micro-module) in 2–4 weeks.'; }

        if (in_array($latestStatus, $attritionSet, true))  { $recommendations[] = 'Open an intervention ticket—confirm placement/eligibility/exit.'; }
        elseif ($latestStatus === 'study_leave')           { $recommendations[] = 'Plan a return-to-practice bridge after study leave.'; }

        if ($assessedCount > 0) {
            $weak = $participations->filter(fn($p) => ($p->overall_score ?? 100) < 70)
                ->pluck('training.title')->unique()->take(3)->values();
            if ($weak->isNotEmpty()) { $recommendations[] = 'Targeted refresh on: ' . $weak->implode(', ') . '.'; }
        }

        $review = match ($risk) {
            'Very High', 'High' => ['3m'=>true,'6m'=>true,'12m'=>true],
            'Medium'            => ['3m'=>true,'6m'=>true,'12m'=>true],
            default             => ['3m'=>false,'6m'=>true,'12m'=>true],
        };

        return [
            'risk'             => $risk,
            'latest_status'    => $latestStatus,
            'last_training_at' => $lastDate ? Carbon::parse($lastDate)->toDateString() : null,
            'days_since'       => $daysSince,
            'totals'           => ['trainings'=>$totalTrainings,'completed'=>$completedCount,'assessed'=>$assessedCount],
            'rationale'        => $rationale,
            'recommendations'  => $recommendations,
            'review_windows'   => $review,
        ];
    }

    private static function maxRisk(string $current, string $candidate): string
    {
        $order = ['Low'=>0,'Medium'=>1,'High'=>2,'Very High'=>3];
        return $order[$candidate] > $order[$current] ? $candidate : $current;
    }
}
