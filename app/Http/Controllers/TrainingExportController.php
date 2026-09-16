<?php

// Step 2: Create app/Http/Controllers/TrainingExportController.php

namespace App\Http\Controllers;

use App\Models\Training;
use App\Models\User;
use App\Models\TrainingParticipant;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Carbon\Carbon;

class TrainingExportController extends Controller
{
    public function download(Request $request, string $exportId)
    {
        // Debug logging
        logger('Download request received:', [
            'export_id' => $exportId,
            'cache_keys' => cache()->get('cache_keys_debug', [])
        ]);
        
        // Get export data from cache
        $data = cache()->get($exportId);
        $filename = cache()->get($exportId . '_filename', 'training_export.csv');
        
        logger('Cache retrieval:', [
            'data_exists' => !empty($data),
            'filename' => $filename
        ]);
        
        if (!$data) {
            logger('Export data not found in cache');
            
            // Show a simple error instead of abort
            return response()->view('errors.simple-error', [
                'title' => 'Export Not Found',
                'message' => "Export ID: {$exportId} not found in cache. Please try generating the export again.",
                'back_url' => route('filament.admin.resources.training-exports.create')
            ], 404);
        }
        
        // Clean up cache after retrieving
        cache()->forget($exportId);
        cache()->forget($exportId . '_filename');
        
        try {
            logger('Generating CSV content...');
            
            // Generate simple CSV content
            $csvContent = $this->generateSimpleCsvContent($data);
            
            logger('CSV generated, length:', [strlen($csvContent)]);
            
            // Force CSV download with proper headers
            return response($csvContent)
                ->header('Content-Type', 'text/csv; charset=utf-8')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
                ->header('Content-Length', strlen($csvContent))
                ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
                ->header('Pragma', 'no-cache')
                ->header('Expires', '0');
                
        } catch (\Exception $e) {
            logger('Export generation failed:', [$e->getMessage()]);
            
            return response()->view('errors.simple-error', [
                'title' => 'Export Generation Failed',
                'message' => $e->getMessage(),
                'back_url' => route('filament.admin.resources.training-exports.create')
            ], 500);
        }
    }

    protected function generateSimpleCsvContent(array $data): string
    {
        logger('Starting CSV generation for export type:', [$data['export_type']]);
        
        // Always start with basic info
        $csv = "Training Export\n";
        $csv .= "Generated: " . Carbon::now()->format('Y-m-d H:i:s') . "\n";
        $csv .= "Export Type: " . ucfirst(str_replace('_', ' ', $data['export_type'])) . "\n\n";
        
        if ($data['export_type'] === 'training_participants') {
            return $this->generateTrainingParticipantsCsv($data);
        } elseif ($data['export_type'] === 'participant_trainings') {
            return $this->generateParticipantTrainingsCsv($data);
        } else {
            return $this->generateTrainingSummaryCsv($data);
        }
    }

    protected function generateCsvContent(array $data): string
    {
        $csv = '';
        
        switch ($data['export_type']) {
            case 'training_participants':
                $csv = $this->generateTrainingParticipantsCsv($data);
                break;
            case 'participant_trainings':
                $csv = $this->generateParticipantTrainingsCsv($data);
                break;
            case 'training_summary':
                $csv = $this->generateTrainingSummaryCsv($data);
                break;
            default:
                throw new \Exception('Unknown export type');
        }
        
        return $csv;
    }

    protected function generateTrainingParticipantsCsv(array $data): string
    {
        $csv = '';
        $first = true;

        $trainings = Training::with([
            'facility', 'county', 'partner', 'division',
            'programs', 'participants.user.facility.subcounty.county',
            'participants.user.department', 'participants.user.cadre',
            'participants.assessmentResults'
        ])->whereIn('id', $data['selected_trainings'])->get();

        foreach ($trainings as $training) {
            $participants = $this->getFilteredParticipants($training, $data);
            
            if ($participants->isEmpty()) {
                continue;
            }

            // Add training header
            if (!$first) {
                $csv .= "\n\n";
            }
            $csv .= "TRAINING: {$training->title}\n";
            $csv .= "TYPE: " . ($training->type === 'global_training' ? 'MOH Training' : 'Facility Mentorship') . "\n";
            $csv .= "DATES: " . ($training->start_date ? $training->start_date->format('M j, Y') : 'TBD') . 
                   " to " . ($training->end_date ? $training->end_date->format('M j, Y') : 'TBD') . "\n\n";

            // Headers
            $headers = $this->generateParticipantHeaders($data);
            $csv .= $this->arrayToCsv($headers);

            // Data rows
            foreach ($participants as $participant) {
                $row = $this->formatParticipantRow($participant, $training, $data);
                $csv .= $this->arrayToCsv($row);
            }

            $first = false;
        }

        return $csv;
    }

    protected function generateParticipantTrainingsCsv(array $data): string
    {
        $csv = '';
        $first = true;

        $participants = User::with([
            'facility.subcounty.county', 'department', 'cadre',
            'trainingParticipations.training.programs',
            'trainingParticipations.training.locations'
        ])->whereIn('id', $data['selected_participants'])->get();

        foreach ($participants as $participant) {
            if (!$first) {
                $csv .= "\n\n";
            }

            // Participant header
            $csv .= "PARTICIPANT: {$participant->full_name}\n";
            $csv .= "FACILITY: " . ($participant->facility?->name ?? 'Not specified') . "\n\n";

            // Headers
            $headers = [
                'Training Title', 'Training ID', 'Type', 'Programs',
                'Start Date', 'End Date', 'Registration Date',
                
            ];
            $csv .= $this->arrayToCsv($headers);

            // Training data
            $trainings = $participant->trainingParticipations()->with([
                'training.programs', 'training.locations'
            ])->get();

            foreach ($trainings as $participation) {
                $row = $this->formatParticipantTrainingRow($participation);
                $csv .= $this->arrayToCsv($row);
            }

            $first = false;
        }

        return $csv;
    }

    protected function generateTrainingSummaryCsv(array $data): string
    {
        $trainings = Training::with(['participants'])
            ->whereIn('id', $data['selected_trainings'])
            ->get();

        $csv = "TRAINING SUMMARY REPORT\n";
        $csv .= "Generated: " . Carbon::now()->format('Y-m-d H:i:s') . "\n\n";

        $csv .= "Training Name,Type,Start Date,End Date,Total Participants,Completed,Completion Rate\n";

        foreach ($trainings as $training) {
            $totalParticipants = $training->participants->count();
            $completedParticipants = $training->participants->where('completion_status', 'completed')->count();
            $completionRate = $totalParticipants > 0 ? round(($completedParticipants / $totalParticipants) * 100, 1) : 0;

            $csv .= $this->arrayToCsv([
                $training->title,
                $training->type === 'global_training' ? 'MOH Training' : 'Facility Mentorship',
                $training->start_date?->format('Y-m-d') ?? '',
                $training->end_date?->format('Y-m-d') ?? '',
                $totalParticipants,
                $completedParticipants,
                $completionRate . '%'
            ]);
        }

        return $csv;
    }

    protected function getFilteredParticipants($training, $data)
    {
        $query = $training->participants()->with([
            'user.facility.subcounty.county',
            'user.department', 'user.cadre'
        ]);

        // Apply filters
        if (!empty($data['filter_departments'])) {
            $query->whereHas('user', function ($userQuery) use ($data) {
                $userQuery->whereIn('department_id', $data['filter_departments']);
            });
        }

        if (!empty($data['filter_cadres'])) {
            $query->whereHas('user', function ($userQuery) use ($data) {
                $userQuery->whereIn('cadre_id', $data['filter_cadres']);
            });
        }

        if (!empty($data['filter_attendance_status'])) {
            $query->whereIn('attendance_status', $data['filter_attendance_status']);
        }

        return $query->get();
    }

    protected function generateParticipantHeaders($data): array
    {
        $headers = [];
        $participantFields = $data['participant_fields'] ?? [];
        $trainingFields = $data['training_fields'] ?? [];

        if (in_array('name', $participantFields)) $headers[] = 'Full Name';
        if (in_array('phone', $participantFields)) $headers[] = 'Phone Number';
        //if (in_array('email', $participantFields)) $headers[] = 'Email';
        if (in_array('facility_name', $participantFields)) $headers[] = 'Facility';
        if (in_array('county', $participantFields)) $headers[] = 'County';
        if (in_array('department', $participantFields)) $headers[] = 'Department';
        if (in_array('cadre', $participantFields)) $headers[] = 'Cadre';
        //if (in_array('registration_date', $participantFields)) $headers[] = 'Registration Date';
        //if (in_array('attendance_status', $participantFields)) $headers[] = 'Attendance Status';
        //if (in_array('completion_status', $participantFields)) $headers[] = 'Completion Status';

        return $headers;
    }

    protected function formatParticipantRow($participant, $training, $data): array
    {
        $row = [];
        $user = $participant->user;
        $participantFields = $data['participant_fields'] ?? [];

        if (in_array('name', $participantFields)) $row[] = $user->full_name;
        if (in_array('phone', $participantFields)) $row[] = $user->phone ?? '';
        //if (in_array('email', $participantFields)) $row[] = $user->email ?? '';
        if (in_array('facility_name', $participantFields)) $row[] = $user->facility?->name ?? '';
        if (in_array('county', $participantFields)) $row[] = $user->facility?->subcounty?->county?->name ?? '';
        if (in_array('department', $participantFields)) $row[] = $user->department?->name ?? '';
        if (in_array('cadre', $participantFields)) $row[] = $user->cadre?->name ?? '';
        //if (in_array('registration_date', $participantFields)) $row[] = $participant->registration_date?->format('Y-m-d') ?? '';
        //if (in_array('attendance_status', $participantFields)) $row[] = ucfirst($participant->attendance_status);
        //if (in_array('completion_status', $participantFields)) $row[] = ucfirst($participant->completion_status);

        return $row;
    }

    protected function formatParticipantTrainingRow($participation): array
    {
        $training = $participation->training;
        
        return [
            $training->title,
            $training->identifier ?? '',
            $training->type === 'global_training' ? 'MOH Global' : 'Facility Mentorship',
            $training->programs->pluck('name')->join(', '),
            $training->start_date?->format('Y-m-d') ?? '',
            $training->end_date?->format('Y-m-d') ?? '',
            $participation->registration_date?->format('Y-m-d') ?? '',
            //ucfirst($participation->attendance_status),
            //ucfirst($participation->completion_status),
        ];
    }

    protected function arrayToCsv(array $data): string
    {
        $output = '';
        $delimiter = ',';
        $enclosure = '"';

        foreach ($data as $field) {
            $field = str_replace($enclosure, $enclosure . $enclosure, (string)$field);
            $output .= $enclosure . $field . $enclosure . $delimiter;
        }

        return rtrim($output, $delimiter) . "\n";
    }
}