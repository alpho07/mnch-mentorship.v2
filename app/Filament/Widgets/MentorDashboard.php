<?php

namespace App\Filament\Widgets;

use App\Models\ClassParticipant;
use App\Models\SessionAttendance;
use App\Models\MenteeAssessmentResult;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class MentorDashboard extends BaseWidget {

    protected static ?int $sort = 1;
    protected static ?string $pollingInterval = '60s';

    protected function getStats(): array {
        $userId = Auth::id();

        // Get all class enrollments
        $enrollments = ClassParticipant::where('user_id', $userId)
                ->with(['mentorshipClass.classModules'])
                ->get();

        // Get attendance records
        $attendanceRecords = SessionAttendance::whereHas('classParticipant', function ($query) use ($userId) {
                    $query->where('user_id', $userId);
                })->get();

        // Get assessment results
        $assessments = MenteeAssessmentResult::whereHas('participant', function ($query) use ($userId) {
                    $query->where('user_id', $userId);
                })->get();

        // Calculate statistics
        $activeClasses = $enrollments->where('status', 'active')->count();
        $completedClasses = $enrollments->where('status', 'completed')->count();

        $totalSessions = $attendanceRecords->count();
        $attendedSessions = $attendanceRecords->where('status', 'present')->count();
        $attendanceRate = $totalSessions > 0 ? round(($attendedSessions / $totalSessions) * 100, 1) : 0;

        $totalAssessments = $assessments->count();
        $passedAssessments = $assessments->where('result', 'pass')->count();
        $averageScore = $totalAssessments > 0 ? round($assessments->avg('score') ?? 0, 1) : 0;

        $totalModules = $enrollments->sum(function ($enrollment) {
            return $enrollment->mentorshipClass->classModules()->count();
        });

        $completedModules = $enrollments->sum(function ($enrollment) {
            return $enrollment->mentorshipClass->classModules()
                            ->where('status', 'completed')
                            ->count();
        });

        return [
                    Stat::make('My Classes', $enrollments->count())
                    ->description("{$activeClasses} active, {$completedClasses} completed")
                    ->descriptionIcon('heroicon-o-academic-cap')
                    ->color('success')
                    ->chart([1, 2, 3, 3, 4, 5, 5, 6]),
                    Stat::make('Attendance Rate', $attendanceRate . '%')
                    ->description("{$attendedSessions} of {$totalSessions} sessions attended")
                    ->descriptionIcon('heroicon-o-check-circle')
                    ->color($attendanceRate >= 80 ? 'success' : ($attendanceRate >= 60 ? 'warning' : 'danger'))
                    ->chart([70, 75, 78, 80, 82, 85, 88, $attendanceRate]),
                    Stat::make('Module Progress', $totalModules > 0 ? round(($completedModules / $totalModules) * 100, 1) . '%' : '0%')
                    ->description("{$completedModules} of {$totalModules} modules completed")
                    ->descriptionIcon('heroicon-o-book-open')
                    ->color('primary')
                    ->chart([10, 20, 30, 40, 50, 60, 70, 75]),
                    Stat::make('Assessment Score', $averageScore . '%')
                    ->description("{$passedAssessments} of {$totalAssessments} assessments passed")
                    ->descriptionIcon('heroicon-o-clipboard-document-check')
                    ->color($averageScore >= 70 ? 'success' : ($averageScore >= 50 ? 'warning' : 'danger'))
                    ->chart([50, 55, 60, 65, 68, 70, 72, $averageScore]),
        ];
    }
}
