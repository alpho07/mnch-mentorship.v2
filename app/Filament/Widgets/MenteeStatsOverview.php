<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MenteeStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $q = User::query();

        $all = (clone $q)->count();
        $active = (clone $q)->whereHas('statusLogs', function($qq){
            $qq->orderByDesc('effective_date')->orderByDesc('id')->limit(1)->where('new_status','active');
        })->orWhereDoesntHave('statusLogs')->count();

        $inactive12 = (clone $q)->whereDoesntHave('trainingParticipations.training', function($tq){
            $tq->whereDate('start_date','>=',now()->subDays(365))
               ->orWhereDate('end_date','>=',now()->subDays(365));
        })->whereDoesntHave('trainingParticipations', function($pq){
            $pq->whereDate('completion_date','>=',now()->subDays(365));
        })->count();

        return [
            Stat::make('Total Mentees', number_format($all)),
            Stat::make('Active', number_format($active)),
            Stat::make('No Activity ≥ 12 mo', number_format($inactive12)),
        ];
    }
}
