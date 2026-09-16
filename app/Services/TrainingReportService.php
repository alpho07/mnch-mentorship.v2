<?php

namespace App\Services;

use App\Models\Training;
use App\Models\TrainingParticipant;
use App\Models\ParticipantObjectiveResult;
use App\Models\Grade;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Exception;

class TrainingReportService
{
    /**
     * Generate comprehensive participant report
     */
    public function generateParticipantReport(Training $training): array
    {
        $participants = $training->participants()
            ->with([
                'user',
                'facility.subcounty.county',
                'department',
                'cadre',
                'outcome',
                'objectiveResults.objective'
            ])
            ->get();

        $objectives = $training->objectives()->orderBy('order')->get();

        $reportData = $participants->map(function ($participant) use ($objectives, $training) {
            $objectiveScores = [];
            foreach ($objectives as $objective) {
                $result = $participant->objectiveResults
                    ->where('objective_id', $objective->id)
                    ->first();
                $objectiveScores["objective_{$objective->order}_score"] = $result?->score ?? '';
                $objectiveScores["objective_{$objective->order}_passed"] = $result ? ($result->score >= $objective->pass_criteria ? 'Yes' : 'No') : '';
            }

            return [
                'participant_id' => $participant->id,
                'training_code' => $training->identifier,
                'training_title' => $training->title,
                'name' => $participant->user->full_name,
                'phone' => $participant->user->phone,
                'email' => $participant->user->email,
                'id_number' => $participant->user->id_number,
                'facility' => $participant->facility->name ?? 'N/A',
                'mfl_code' => $participant->facility->mfl_code ?? 'N/A',
                'county' => $participant->facility->subcounty->county->name ?? 'N/A',
                'subcounty' => $participant->facility->subcounty->name ?? 'N/A',
                'department' => $participant->department->name,
                'cadre' => $participant->cadre->name,
                'registration_date' => $participant->registration_date?->format('Y-m-d'),
                'attendance_status' => ucfirst(str_replace('_', ' ', $participant->attendance_status)),
                'completion_status' => ucfirst(str_replace('_', ' ', $participant->completion_status)),
                'final_score' => $participant->final_score,
                'outcome' => $participant->outcome?->name ?? 'Not Assessed',
                'passed_training' => $participant->outcome?->is_passing_grade ? 'Yes' : 'No',
                'completion_date' => $participant->completion_date?->format('Y-m-d'),
                'certificate_issued' => $participant->certificate_issued ? 'Yes' : 'No',
                'training_start_date' => $training->start_date->format('Y-m-d'),
                'training_end_date' => $training->end_date->format('Y-m-d'),
                'training_duration_days' => $training->start_date->diffInDays($training->end_date) + 1,
                'mentor_name' => $training->mentor?->full_name ?? 'N/A',
                'organizer_name' => $training->organizer?->full_name ?? 'N/A',
            ] + $objectiveScores;
        });

        return [
            'training' => $training,
            'participants' => $reportData,
            'objectives' => $objectives,
            'summary' => $this->generateSummaryStats($training),
        ];
    }

    /**
     * Export participant report to CSV
     */
    public function exportParticipantReportToCsv(Training $training): string
    {
        try {
            $reportData = $this->generateParticipantReport($training);
            $participants = $reportData['participants'];
            $objectives = $reportData['objectives'];

            // Build CSV headers
            $headers = [
                'Participant ID',
                'Training Code',
                'Training Title',
                'Name',
                'Phone',
                'Email',
                'ID Number',
                'Facility',
                'MFL Code',
                'County',
                'Subcounty',
                'Department',
                'Cadre',
                'Registration Date',
                'Attendance Status',
                'Completion Status',
            ];

            // Add objective headers
            foreach ($objectives as $objective) {
                $headers[] = "Objective {$objective->order} Score";
                $headers[] = "Objective {$objective->order} Passed";
            }

            // Add final assessment headers
            $headers = array_merge($headers, [
                'Final Score',
                'Final Grade',
                'Passed Training',
                'Completion Date',
                'Certificate Issued',
                'Training Start Date',
                'Training End Date',
                'Training Duration (Days)',
                'Mentor Name',
                'Organizer Name',
            ]);

            // Create CSV content
            $csvContent = [];
            $csvContent[] = $headers;

            foreach ($participants as $participant) {
                $row = [
                    $participant['participant_id'],
                    $participant['training_code'],
                    $participant['training_title'],
                    $participant['name'],
                    $participant['phone'],
                    $participant['email'],
                    $participant['id_number'],
                    $participant['facility'],
                    $participant['mfl_code'],
                    $participant['county'],
                    $participant['subcounty'],
                    $participant['department'],
                    $participant['cadre'],
                    $participant['registration_date'],
                    $participant['attendance_status'],
                    $participant['completion_status'],
                ];

                // Add objective scores
                foreach ($objectives as $objective) {
                    $scoreKey = "objective_{$objective->order}_score";
                    $passedKey = "objective_{$objective->order}_passed";
                    $row[] = $participant[$scoreKey] ?? '';
                    $row[] = $participant[$passedKey] ?? '';
                }

                // Add final assessment data
                $row = array_merge($row, [
                    $participant['final_score'],
                    $participant['outcome'],
                    $participant['passed_training'],
                    $participant['completion_date'],
                    $participant['certificate_issued'],
                    $participant['training_start_date'],
                    $participant['training_end_date'],
                    $participant['training_duration_days'],
                    $participant['mentor_name'],
                    $participant['organizer_name'],
                ]);

                $csvContent[] = $row;
            }

            // Convert to CSV string
            $output = fopen('php://temp', 'r+');
            foreach ($csvContent as $row) {
                fputcsv($output, $row);
            }
            rewind($output);
            $csvString = stream_get_contents($output);
            fclose($output);

            // Save to file
            $fileName = "training-report-{$training->identifier}-" . now()->format('Y-m-d-H-i-s') . '.csv';
            $filePath = storage_path("app/temp/{$fileName}");

            // Ensure directory exists
            if (!file_exists(dirname($filePath))) {
                mkdir(dirname($filePath), 0755, true);
            }

            file_put_contents($filePath, $csvString);

            return $filePath;
        } catch (Exception $e) {
            throw new Exception("Failed to generate CSV report: " . $e->getMessage());
        }
    }

    /**
     * Generate summary statistics for training
     */
    private function generateSummaryStats(Training $training): array
    {
        $participants = $training->participants;
        $total = $participants->count();

        if ($total === 0) {
            return [
                'total_participants' => 0,
                'completed_count' => 0,
                'pass_rate' => 0,
                'average_score' => 0,
                'certificates_issued' => 0,
                'attendance_rate' => 0,
            ];
        }

        $completed = $participants->where('completion_status', 'completed')->count();
        $passed = $participants->whereHas('outcome', function ($query) {
            $query->where('is_passing_grade', true);
        })->count();

        $attended = $participants->whereIn('attendance_status', ['attended', 'partially_attended'])->count();
        $certificatesIssued = $participants->where('certificate_issued', true)->count();

        $averageScore = $participants->where('final_score', '>', 0)->avg('final_score') ?? 0;

        return [
            'total_participants' => $total,
            'completed_count' => $completed,
            'pass_rate' => $total > 0 ? round(($passed / $total) * 100, 1) : 0,
            'average_score' => round($averageScore, 1),
            'certificates_issued' => $certificatesIssued,
            'attendance_rate' => $total > 0 ? round(($attended / $total) * 100, 1) : 0,
        ];
    }

    /**
     * Generate dashboard metrics
     */
    public function generateDashboardMetrics(): array
    {
        $currentMonth = now()->startOfMonth();
        $lastMonth = now()->subMonth()->startOfMonth();

        return [
            'active_trainings' => Training::where('status', 'ongoing')->count(),
            'total_participants_this_month' => TrainingParticipant::where('registration_date', '>=', $currentMonth)->count(),
            'completion_rate_this_month' => $this->getCompletionRateForPeriod($currentMonth),
            'upcoming_trainings' => Training::where('start_date', '>', now())->count(),
            'trainings_completed_this_month' => Training::where('status', 'completed')
                ->where('end_date', '>=', $currentMonth)->count(),
            'certificates_issued_this_month' => TrainingParticipant::where('certificate_issued', true)
                ->where('completion_date', '>=', $currentMonth)->count(),
        ];
    }

    /**
     * Get completion rate for specific period
     */
    private function getCompletionRateForPeriod($startDate): float
    {
        $participants = TrainingParticipant::where('registration_date', '>=', $startDate)->get();
        $total = $participants->count();

        if ($total === 0) return 0;

        $completed = $participants->where('completion_status', 'completed')->count();
        return round(($completed / $total) * 100, 1);
    }

    /**
     * Generate training analytics for charts
     */
    public function getTrainingAnalytics(): array
    {
        // Performance trends for the last 6 months
        $performanceData = DB::table('training_participants')
            ->join('trainings', 'training_participants.training_id', '=', 'trainings.id')
            ->selectRaw("
                DATE_FORMAT(trainings.end_date, '%Y-%m') as month,
                COUNT(*) as total_participants,
                SUM(CASE WHEN completion_status = 'completed' THEN 1 ELSE 0 END) as completed,
                ROUND((SUM(CASE WHEN completion_status = 'completed' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 1) as completion_rate
            ")
            ->where('trainings.end_date', '>=', now()->subMonths(6))
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // Department distribution
        $departmentData = DB::table('training_participants')
            ->join('departments', 'training_participants.department_id', '=', 'departments.id')
            ->selectRaw('departments.name, COUNT(*) as count')
            ->groupBy('departments.name')
            ->orderByDesc('count')
            ->limit(8)
            ->get();

        // Training type distribution
        $typeData = DB::table('trainings')
            ->selectRaw('type, COUNT(*) as count')
            ->groupBy('type')
            ->get();

        return [
            'performance_trends' => $performanceData,
            'department_distribution' => $departmentData,
            'type_distribution' => $typeData,
        ];
    }

    /**
     * Generate detailed participant assessment report
     */
    public function generateAssessmentReport(Training $training): array
    {
        $objectives = $training->objectives()->orderBy('order')->get();
        $participants = $training->participants()->with([
            'user',
            'objectiveResults.objective',
            'objectiveResults.grade'
        ])->get();

        $assessmentData = [];

        foreach ($objectives as $objective) {
            $results = ParticipantObjectiveResult::where('objective_id', $objective->id)
                ->with(['participant.user', 'grade'])
                ->get();

            $stats = [
                'objective' => $objective,
                'total_assessments' => $results->count(),
                'average_score' => $results->avg('score') ?? 0,
                'pass_rate' => $results->where('score', '>=', $objective->pass_criteria)->count(),
                'results' => $results,
            ];

            $assessmentData[] = $stats;
        }

        return [
            'training' => $training,
            'objectives_assessment' => $assessmentData,
            'overall_stats' => $this->calculateOverallAssessmentStats($training),
        ];
    }

    /**
     * Calculate overall assessment statistics
     */
    private function calculateOverallAssessmentStats(Training $training): array
    {
        $participants = $training->participants;
        $objectives = $training->objectives;

        $totalAssessments = 0;
        $completedAssessments = 0;
        $passedAssessments = 0;

        foreach ($participants as $participant) {
            foreach ($objectives as $objective) {
                $totalAssessments++;
                $result = $participant->objectiveResults
                    ->where('objective_id', $objective->id)
                    ->first();

                if ($result) {
                    $completedAssessments++;
                    if ($result->score >= $objective->pass_criteria) {
                        $passedAssessments++;
                    }
                }
            }
        }

        return [
            'total_possible_assessments' => $totalAssessments,
            'completed_assessments' => $completedAssessments,
            'assessment_completion_rate' => $totalAssessments > 0 ?
                round(($completedAssessments / $totalAssessments) * 100, 1) : 0,
            'overall_pass_rate' => $completedAssessments > 0 ?
                round(($passedAssessments / $completedAssessments) * 100, 1) : 0,
        ];
    }

    /**
     * Generate simple text-based assessment report
     */
    public function generateAssessmentReportText(Training $training): string
    {
        $reportData = $this->generateAssessmentReport($training);

        $report = "TRAINING ASSESSMENT REPORT\n";
        $report .= "========================\n\n";
        $report .= "Training: {$training->title}\n";
        $report .= "Code: {$training->identifier}\n";
        $report .= "Generated: " . now()->format('Y-m-d H:i:s') . "\n\n";

        foreach ($reportData['objectives_assessment'] as $objectiveData) {
            $objective = $objectiveData['objective'];

            $report .= "OBJECTIVE: {$objective->title}\n";
            $report .= str_repeat('-', 50) . "\n";
            $report .= "Total Assessments: {$objectiveData['total_assessments']}\n";
            $report .= "Average Score: " . round($objectiveData['average_score'], 1) . "%\n";
            $report .= "Pass Rate: {$objectiveData['pass_rate']} participants\n\n";

            foreach ($objectiveData['results'] as $result) {
                $passed = $result->score >= $objective->pass_criteria ? 'PASSED' : 'FAILED';
                $report .= "- {$result->participant->user->full_name}: {$result->score}% [{$passed}]\n";
            }

            $report .= "\n";
        }

        $overall = $reportData['overall_stats'];
        $report .= "OVERALL STATISTICS\n";
        $report .= str_repeat('=', 50) . "\n";
        $report .= "Assessment Completion Rate: {$overall['assessment_completion_rate']}%\n";
        $report .= "Overall Pass Rate: {$overall['overall_pass_rate']}%\n";

        return $report;
    }
}
