<?php

namespace App\Filament\Widgets;

use App\Models\ClassParticipant;
use App\Models\MenteeModuleProgress;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MenteeStatsWidget extends BaseWidget {

    protected static ?int $sort = 1;

    protected function getStats(): array {
        $userId = auth()->id();

        // Total modules enrolled
        $totalModules = MenteeModuleProgress::whereHas('classParticipant', function ($query) use ($userId) {
                    $query->where('user_id', $userId);
                })->count();

        // Completed modules
        $completedModules = MenteeModuleProgress::whereHas('classParticipant', function ($query) use ($userId) {
                    $query->where('user_id', $userId);
                })
                ->where('status', 'completed')
                ->count();

        // In progress modules
        $inProgressModules = MenteeModuleProgress::whereHas('classParticipant', function ($query) use ($userId) {
                    $query->where('user_id', $userId);
                })
                ->where('status', 'in_progress')
                ->count();

        // Exempted modules
        $exemptedModules = MenteeModuleProgress::whereHas('classParticipant', function ($query) use ($userId) {
                    $query->where('user_id', $userId);
                })
                ->where('status', 'exempted')
                ->count();

        // Average attendance
        $avgAttendance = MenteeModuleProgress::whereHas('classParticipant', function ($query) use ($userId) {
                            $query->where('user_id', $userId);
                        })
                        ->whereNotNull('attendance_percentage')
                        ->avg('attendance_percentage') ?? 0;

        // Average assessment score
        $avgAssessment = MenteeModuleProgress::whereHas('classParticipant', function ($query) use ($userId) {
                            $query->where('user_id', $userId);
                        })
                        ->whereNotNull('assessment_score')
                        ->avg('assessment_score') ?? 0;

        return [
                    Stat::make('Total Modules', $totalModules)
                    ->description('Enrolled in')
                    ->descriptionIcon('heroicon-o-book-open')
                    ->color('primary'),
                    Stat::make('Completed', $completedModules)
                    ->description('Modules finished')
                    ->descriptionIcon('heroicon-o-check-circle')
                    ->color('success')
                    ->chart([1, 2, 3, 5, 7, 10, 12]),
                    Stat::make('In Progress', $inProgressModules)
                    ->description('Currently learning')
                    ->descriptionIcon('heroicon-o-arrow-path')
                    ->color('warning'),
                    Stat::make('Exempted', $exemptedModules)
                    ->description('Previously completed')
                    ->descriptionIcon('heroicon-o-shield-check')
                    ->color('info'),
                    Stat::make('Avg Attendance', round($avgAttendance, 1) . '%')
                    ->description($avgAttendance >= 80 ? 'Excellent!' : ($avgAttendance >= 60 ? 'Good' : 'Needs improvement'))
                    ->descriptionIcon('heroicon-o-user-group')
                    ->color($avgAttendance >= 80 ? 'success' : ($avgAttendance >= 60 ? 'warning' : 'danger')),
                    Stat::make('Avg Assessment', round($avgAssessment, 1) . '%')
                    ->description($avgAssessment >= 70 ? 'Passing' : 'Keep improving')
                    ->descriptionIcon('heroicon-o-trophy')
                    ->color($avgAssessment >= 70 ? 'success' : 'danger'),
        ];
    }

    public static function canView(): bool {
        // Only show for mentees
        return  auth()->check() && ClassParticipant::where('user_id', auth()->id())->exists();
    }
}
