<?php

namespace App\Exports;

use App\Models\Training;
use App\Models\User;
use App\Models\TrainingParticipant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Response;
use Carbon\Carbon;

/**
 * Simple CSV Export - No external dependencies
 * Use this as a fallback if PhpSpreadsheet causes issues
 */
class SimpleCsvExport
{
    protected array $config;
    protected Collection $trainings;
    protected Collection $participants;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->loadData();
    }

    protected function loadData(): void
    {
        switch ($this->config['export_type']) {
            case 'training_participants':
            case 'training_summary':
                $this->loadTrainingData();
                break;
            case 'participant_trainings':
                $this->loadParticipantData();
                break;
        }
    }

    protected function loadTrainingData(): void
    {
        $this->trainings = Training::with([
            'facility', 'county', 'partner', 'division',
            'programs', 'modules', 'methodologies', 'locations',
            'participants.user.facility.subcounty.county',
            'participants.user.department',
            'participants.user.cadre',
            'participants.assessmentResults.assessmentCategory',
            'assessmentCategories'
        ])->whereIn('id', $this->config['selected_trainings'])->get();
    }

    protected function loadParticipantData(): void
    {
        $this->participants = User::with([
            'facility.subcounty.county',
            'department', 'cadre',
            'trainingParticipations.training.programs',
            'trainingParticipations.training.locations',
            'trainingParticipations.assessmentResults.assessmentCategory'
        ])->whereIn('id', $this->config['selected_participants'])->get();
    }

    public function download(string $filename = null): \Symfony\Component\HttpFoundation\Response
    {
        if (!$filename) {
            $timestamp = Carbon::now()->format('Y-m-d_H-i-s');
            $exportType = str_replace('_', '-', $this->config['export_type']);
            $filename = "training_export_{$exportType}_{$timestamp}.csv";
        }

        $csvData = $this->generateCsvData();

        return Response::make($csvData, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ]);
    }

    protected function generateCsvData(): string
    {
        $csv = '';

        switch ($this->config['export_type']) {
            case 'training_participants':
                $csv = $this->generateTrainingParticipantsCsv();
                break;
            case 'participant_trainings':
                $csv = $this->generateParticipantTrainingsCsv();
                break;
            case 'training_summary':
                $csv = $this->generateTrainingSummaryCsv();
                break;
        }

        return $csv;
    }

    protected function generateTrainingParticipantsCsv(): string
    {
        $csv = '';
        $first = true;

        foreach ($this->trainings as $training) {
            $participants = $this->getFilteredParticipants($training);
            
            if ($participants->isEmpty()) {
                continue;
            }

            // Add training header
            if (!$first) {
                $csv .= "\n\n";
            }
            $csv .= "TRAINING: {$training->title}\n";
            $csv .= "TYPE: " . ($training->type === 'global_training' ? 'MOH Global Training' : 'Facility Mentorship') . "\n";
            $csv .= "PERIOD: " . ($training->start_date ? $training->start_date->format('M j, Y') : 'TBD') . 
                   " to " . ($training->end_date ? $training->end_date->format('M j, Y') : 'TBD') . "\n\n";

            // Headers
            $headers = $this->generateParticipantHeaders($training);
            $csv .= $this->arrayToCsv($headers);

            // Data rows
            foreach ($participants as $participant) {
                $row = $this->formatParticipantRow($participant, $training);
                $csv .= $this->arrayToCsv($row);
            }

            $first = false;
        }

        return $csv;
    }

    protected function generateParticipantTrainingsCsv(): string
    {
        $csv = '';
        $first = true;

        foreach ($this->participants as $participant) {
            if (!$first) {
                $csv .= "\n\n";
            }

            // Participant header
            $csv .= "PARTICIPANT: {$participant->full_name}\n";
            $csv .= "FACILITY: " . ($participant->facility?->name ?? 'Not specified') . "\n\n";

            // Headers
            $headers = [
                'Training Title', 'Training ID', 'Type', 'Programs',
                'Start Date', 'End Date', 'Location', 'Registration Date',
                'Attendance Status', 'Completion Status', 'Completion Date',
                'Overall Score', 'Overall Status'
            ];
            $csv .= $this->arrayToCsv($headers);

            // Training data
            $trainings = $participant->trainingParticipations()->with([
                'training.programs', 'training.locations', 'training.facility', 'training.county'
            ])->get();

            foreach ($trainings as $participation) {
                $row = $this->formatParticipantTrainingRow($participation);
                $csv .= $this->arrayToCsv($row);
            }

            $first = false;
        }

        return $csv;
    }

    protected function generateTrainingSummaryCsv(): string
    {
        $csv = "TRAINING SUMMARY REPORT\n";
        $csv .= "Generated: " . Carbon::now()->format('Y-m-d H:i:s') . "\n\n";

        $csv .= "Metric,Value,Details\n";
        $csv .= "Total Trainings," . $this->trainings->count() . ",\n";
        $csv .= "MOH Global Trainings," . $this->trainings->where('type', 'global_training')->count() . ",\n";
        $csv .= "Facility Mentorships," . $this->trainings->where('type', 'facility_mentorship')->count() . ",\n";

        $totalParticipants = $this->trainings->sum(fn($t) => $t->participants->count());
        $completedParticipants = $this->trainings->sum(fn($t) => $t->participants->where('completion_status', 'completed')->count());

        $csv .= "Total Participants,{$totalParticipants},\n";
        $csv .= "Completed Participants,{$completedParticipants}," . 
                ($totalParticipants > 0 ? round(($completedParticipants / $totalParticipants) * 100, 1) . '%' : '0%') . "\n";

        return $csv;
    }

    protected function getFilteredParticipants(Training $training): Collection
    {
        $query = $training->participants()->with([
            'user.facility.subcounty.county',
            'user.department',
            'user.cadre',
            'assessmentResults.assessmentCategory'
        ]);

        // Apply filters
        if (!empty($this->config['filter_departments'])) {
            $query->whereHas('user', function ($userQuery) {
                $userQuery->whereIn('department_id', $this->config['filter_departments']);
            });
        }

        if (!empty($this->config['filter_cadres'])) {
            $query->whereHas('user', function ($userQuery) {
                $userQuery->whereIn('cadre_id', $this->config['filter_cadres']);
            });
        }

        if (!empty($this->config['filter_attendance_status'])) {
            $query->whereIn('attendance_status', $this->config['filter_attendance_status']);
        }

        return $query->get();
    }

    protected function generateParticipantHeaders(Training $training): array
    {
        $headers = [];
        $participantFields = $this->config['participant_fields'] ?? [];
        $trainingFields = $this->config['training_fields'] ?? [];
        $assessmentFields = $this->config['assessment_fields'] ?? [];

        if (in_array('name', $participantFields)) $headers[] = 'Full Name';
        if (in_array('phone', $participantFields)) $headers[] = 'Phone Number';
        if (in_array('email', $participantFields)) $headers[] = 'Email';
        if (in_array('facility_name', $participantFields)) $headers[] = 'Facility';
        if (in_array('county', $participantFields)) $headers[] = 'County';
        if (in_array('department', $participantFields)) $headers[] = 'Department';
        if (in_array('cadre', $participantFields)) $headers[] = 'Cadre';
        if (in_array('training_title', $trainingFields)) $headers[] = 'Training Title';
        if (in_array('training_type', $trainingFields)) $headers[] = 'Training Type';
        if (in_array('programs', $trainingFields)) $headers[] = 'Programs';
        if (in_array('registration_date', $participantFields)) $headers[] = 'Registration Date';
        if (in_array('attendance_status', $participantFields)) $headers[] = 'Attendance Status';
        if (in_array('completion_status', $participantFields)) $headers[] = 'Completion Status';

        if ($this->config['include_assessments'] ?? false) {
            if (in_array('overall_score', $assessmentFields)) $headers[] = 'Overall Score';
            if (in_array('overall_status', $assessmentFields)) $headers[] = 'Overall Status';
        }

        return $headers;
    }

    protected function formatParticipantRow($participant, Training $training): array
    {
        $row = [];
        $user = $participant->user;
        $participantFields = $this->config['participant_fields'] ?? [];
        $trainingFields = $this->config['training_fields'] ?? [];
        $assessmentFields = $this->config['assessment_fields'] ?? [];

        if (in_array('name', $participantFields)) $row[] = $user->full_name;
        if (in_array('phone', $participantFields)) $row[] = $user->phone ?? '';
        if (in_array('email', $participantFields)) $row[] = $user->email ?? '';
        if (in_array('facility_name', $participantFields)) $row[] = $user->facility?->name ?? '';
        if (in_array('county', $participantFields)) $row[] = $user->facility?->subcounty?->county?->name ?? '';
        if (in_array('department', $participantFields)) $row[] = $user->department?->name ?? '';
        if (in_array('cadre', $participantFields)) $row[] = $user->cadre?->name ?? '';
        if (in_array('training_title', $trainingFields)) $row[] = $training->title;
        if (in_array('training_type', $trainingFields)) $row[] = $training->type === 'global_training' ? 'MOH Global' : 'Facility Mentorship';
        if (in_array('programs', $trainingFields)) $row[] = $training->programs->pluck('name')->join(', ');
        if (in_array('registration_date', $participantFields)) $row[] = $participant->registration_date?->format('Y-m-d') ?? '';
        if (in_array('attendance_status', $participantFields)) $row[] = ucfirst($participant->attendance_status);
        if (in_array('completion_status', $participantFields)) $row[] = ucfirst($participant->completion_status);

        if ($this->config['include_assessments'] ?? false) {
            $calculation = $training->calculateOverallScore($participant);
            if (in_array('overall_score', $assessmentFields)) {
                $row[] = $calculation['all_assessed'] ? $calculation['score'] . '%' : 'Not assessed';
            }
            if (in_array('overall_status', $assessmentFields)) {
                $row[] = $calculation['status'];
            }
        }

        return $row;
    }

    protected function formatParticipantTrainingRow($participation): array
    {
        $training = $participation->training;
        
        return [
            $training->title,
            $training->identifier,
            $training->type === 'global_training' ? 'MOH Global' : 'Facility Mentorship',
            $training->programs->pluck('name')->join(', '),
            $training->start_date?->format('Y-m-d') ?? '',
            $training->end_date?->format('Y-m-d') ?? '',
            $training->locations->pluck('name')->join(', ') ?: 
                ($training->facility?->name ?? $training->county?->name ?? 'Not specified'),
            $participation->registration_date?->format('Y-m-d') ?? '',
            ucfirst($participation->attendance_status),
            ucfirst($participation->completion_status),
            $participation->completion_date?->format('Y-m-d') ?? '',
            $this->getOverallScore($participation),
            $this->getOverallStatus($participation),
        ];
    }

    protected function getOverallScore($participation): string
    {
        if (!method_exists($participation->training, 'hasAssessments') || !$participation->training->hasAssessments()) {
            return 'No assessments';
        }
        
        $calculation = $participation->training->calculateOverallScore($participation);
        return $calculation['all_assessed'] ? $calculation['score'] . '%' : 'Not assessed';
    }

    protected function getOverallStatus($participation): string
    {
        if (!method_exists($participation->training, 'hasAssessments') || !$participation->training->hasAssessments()) {
            return 'No assessments';
        }
        
        $calculation = $participation->training->calculateOverallScore($participation);
        return $calculation['status'];
    }

    protected function arrayToCsv(array $data): string
    {
        $output = '';
        $delimiter = ',';
        $enclosure = '"';

        foreach ($data as $field) {
            $field = str_replace($enclosure, $enclosure . $enclosure, $field);
            $output .= $enclosure . $field . $enclosure . $delimiter;
        }

        return rtrim($output, $delimiter) . "\n";
    }
}