<?php

namespace App\Exports;

use App\Models\Training;
use App\Models\User;
use App\Models\TrainingParticipant;
use App\Models\County;
use App\Models\Facility;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Maatwebsite\Excel\Events\AfterSheet;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class TrainingParticipantsExport implements WithMultipleSheets
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
        $query = Training::with([
            'facility', 'county', 'partner', 'division',
            'programs', 'modules', 'methodologies', 'locations',
            'participants.user.facility.subcounty.county',
            'participants.user.department',
            'participants.user.cadre',
            'participants.assessmentResults.assessmentCategory',
            'assessmentCategories'
        ])->whereIn('id', $this->config['selected_trainings']);

        // Apply filters
        $this->applyFilters($query);

        $this->trainings = $query->get();
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

    protected function applyFilters($query): void
    {
        // Date filters
        if (!empty($this->config['date_from'])) {
            $query->where('start_date', '>=', $this->config['date_from']);
        }
        if (!empty($this->config['date_to'])) {
            $query->where('start_date', '<=', $this->config['date_to']);
        }

        // Geographic filters - apply to participants
        if (!empty($this->config['filter_counties']) || !empty($this->config['filter_facilities'])) {
            $query->whereHas('participants.user', function ($participantQuery) {
                if (!empty($this->config['filter_counties'])) {
                    $participantQuery->whereHas('facility.subcounty.county', function ($countyQuery) {
                        $countyQuery->whereIn('id', $this->config['filter_counties']);
                    });
                }
                if (!empty($this->config['filter_facilities'])) {
                    $participantQuery->whereIn('facility_id', $this->config['filter_facilities']);
                }
            });
        }
    }

    public function sheets(): array
    {
        $sheets = [];

        // Add summary sheet if requested
        if ($this->config['include_summary_sheet'] ?? true) {
            $sheets[] = new SummarySheet($this->config, $this->trainings, $this->participants);
        }

        // Add data sheets based on export type
        switch ($this->config['export_type']) {
            case 'training_participants':
                foreach ($this->trainings as $training) {
                    $sheets[] = new TrainingParticipantsSheet($training, $this->config);
                }
                break;

            case 'participant_trainings':
                foreach ($this->participants as $participant) {
                    $sheets[] = new ParticipantTrainingsSheet($participant, $this->config);
                }
                break;

            case 'training_summary':
                $sheets[] = new TrainingSummarySheet($this->trainings, $this->config);
                break;
        }

        return $sheets;
    }
}

// Summary Dashboard Sheet
class SummarySheet implements FromCollection, WithTitle, WithHeadings, WithStyles, ShouldAutoSize
{
    protected array $config;
    protected Collection $trainings;
    protected Collection $participants;

    public function __construct(array $config, Collection $trainings = null, Collection $participants = null)
    {
        $this->config = $config;
        $this->trainings = $trainings ?? collect();
        $this->participants = $participants ?? collect();
    }

    public function collection()
    {
        $summary = collect();

        // Export information
        $summary->push([
            'Metric', 'Value', 'Details'
        ]);
        $summary->push([
            'Export Generated', 
            Carbon::now()->format('Y-m-d H:i:s'),
            'Generated by: ' . (auth()->user()->full_name ?? 'System')
        ]);
        $summary->push([
            'Export Type', 
            ucfirst(str_replace('_', ' ', $this->config['export_type'])),
            ''
        ]);

        $summary->push(['', '', '']); // Empty row

        // Training statistics
        if ($this->trainings->isNotEmpty()) {
            $summary->push(['TRAINING OVERVIEW', '', '']);
            $summary->push([
                'Total Trainings Exported',
                $this->trainings->count(),
                ''
            ]);
            $summary->push([
                'MOH Global Trainings',
                $this->trainings->where('type', 'global_training')->count(),
                ''
            ]);
            $summary->push([
                'Facility Mentorships',
                $this->trainings->where('type', 'facility_mentorship')->count(),
                ''
            ]);

            // Participant statistics
            $totalParticipants = $this->trainings->sum(fn($t) => $t->participants->count());
            $completedParticipants = $this->trainings->sum(fn($t) => $t->participants->where('completion_status', 'completed')->count());
            
            $summary->push(['', '', '']);
            $summary->push(['PARTICIPANT OVERVIEW', '', '']);
            $summary->push([
                'Total Participants',
                $totalParticipants,
                ''
            ]);
            $summary->push([
                'Completed Participants',
                $completedParticipants,
                $totalParticipants > 0 ? round(($completedParticipants / $totalParticipants) * 100, 1) . '%' : '0%'
            ]);

            // County breakdown
            $countyStats = collect();
            foreach ($this->trainings as $training) {
                foreach ($training->participants as $participant) {
                    $county = $participant->user->facility?->subcounty?->county?->name ?? 'Unknown';
                    $countyStats[$county] = ($countyStats[$county] ?? 0) + 1;
                }
            }

            if ($countyStats->isNotEmpty()) {
                $summary->push(['', '', '']);
                $summary->push(['COUNTY BREAKDOWN', '', '']);
                foreach ($countyStats->sortDesc()->take(10) as $county => $count) {
                    $summary->push([
                        $county,
                        $count,
                        round(($count / $totalParticipants) * 100, 1) . '%'
                    ]);
                }
            }
        }

        // Participant-focused statistics
        if ($this->participants->isNotEmpty()) {
            $summary->push(['', '', '']);
            $summary->push(['SELECTED PARTICIPANTS', '', '']);
            $summary->push([
                'Total Participants',
                $this->participants->count(),
                ''
            ]);
            
            $totalTrainings = $this->participants->sum(fn($p) => $p->trainingParticipations->count());
            $summary->push([
                'Total Training Participations',
                $totalTrainings,
                'Average: ' . round($totalTrainings / $this->participants->count(), 1) . ' per participant'
            ]);
        }

        return $summary;
    }

    public function title(): string
    {
        return 'Summary Dashboard';
    }

    public function headings(): array
    {
        return [
            'Training Data Export Summary',
            '',
            'Generated: ' . Carbon::now()->format('F j, Y \a\t g:i A')
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'size' => 16],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'E8F4FD']],
            ],
            'A:C' => ['alignment' => ['vertical' => Alignment::VERTICAL_TOP]],
        ];
    }
}

// Individual Training Participants Sheet
class TrainingParticipantsSheet implements FromCollection, WithTitle, WithHeadings, WithStyles, ShouldAutoSize, WithEvents
{
    protected Training $training;
    protected array $config;

    public function __construct(Training $training, array $config)
    {
        $this->training = $training;
        $this->config = $config;
    }

    public function collection()
    {
        $participants = $this->training->participants()->with([
            'user.facility.subcounty.county',
            'user.department',
            'user.cadre',
            'assessmentResults.assessmentCategory'
        ]);

        // Apply participant filters
        $this->applyParticipantFilters($participants);

        return $participants->get()->map(function ($participant) {
            return $this->formatParticipantRow($participant);
        });
    }

    protected function applyParticipantFilters($query): void
    {
        // Department filter
        if (!empty($this->config['filter_departments'])) {
            $query->whereHas('user', function ($userQuery) {
                $userQuery->whereIn('department_id', $this->config['filter_departments']);
            });
        }

        // Cadre filter
        if (!empty($this->config['filter_cadres'])) {
            $query->whereHas('user', function ($userQuery) {
                $userQuery->whereIn('cadre_id', $this->config['filter_cadres']);
            });
        }

        // Attendance status filter
        if (!empty($this->config['filter_attendance_status'])) {
            $query->whereIn('attendance_status', $this->config['filter_attendance_status']);
        }

        // Registration date filters
        if (!empty($this->config['registration_from'])) {
            $query->where('registration_date', '>=', $this->config['registration_from']);
        }
        if (!empty($this->config['registration_to'])) {
            $query->where('registration_date', '<=', $this->config['registration_to']);
        }
    }

    protected function formatParticipantRow($participant): array
    {
        $row = [];
        $user = $participant->user;
        $participantFields = $this->config['participant_fields'] ?? [];
        $trainingFields = $this->config['training_fields'] ?? [];
        $assessmentFields = $this->config['assessment_fields'] ?? [];

        // Participant information
        if (in_array('name', $participantFields)) {
            $row['Full Name'] = $user->full_name;
        }
        if (in_array('phone', $participantFields)) {
            $row['Phone Number'] = $user->phone;
        }
        if (in_array('email', $participantFields)) {
            $row['Email'] = $user->email;
        }
        if (in_array('id_number', $participantFields)) {
            $row['ID Number'] = $user->id_number;
        }
        if (in_array('facility_name', $participantFields)) {
            $row['Facility'] = $user->facility?->name;
        }
        if (in_array('facility_type', $participantFields)) {
            $row['Facility Type'] = $user->facility?->facilityType?->name;
        }
        if (in_array('facility_mfl_code', $participantFields)) {
            $row['MFL Code'] = $user->facility?->mfl_code;
        }
        if (in_array('county', $participantFields)) {
            $row['County'] = $user->facility?->subcounty?->county?->name;
        }
        if (in_array('subcounty', $participantFields)) {
            $row['Subcounty'] = $user->facility?->subcounty?->name;
        }
        if (in_array('department', $participantFields)) {
            $row['Department'] = $user->department?->name;
        }
        if (in_array('cadre', $participantFields)) {
            $row['Cadre'] = $user->cadre?->name;
        }
        if (in_array('role', $participantFields)) {
            $row['Role'] = $user->role;
        }

        // Training information
        if (in_array('training_title', $trainingFields)) {
            $row['Training Title'] = $this->training->title;
        }
        if (in_array('training_identifier', $trainingFields)) {
            $row['Training ID'] = $this->training->identifier;
        }
        if (in_array('training_type', $trainingFields)) {
            $row['Training Type'] = $this->training->type === 'global_training' ? 'MOH Global' : 'Facility Mentorship';
        }
        if (in_array('training_status', $trainingFields)) {
            $row['Training Status'] = ucfirst($this->training->status);
        }
        if (in_array('lead_organization', $trainingFields)) {
            $row['Lead Organization'] = $this->training->lead_organization;
        }
        if (in_array('programs', $trainingFields)) {
            $row['Programs'] = $this->training->programs->pluck('name')->join(', ');
        }
        if (in_array('modules', $trainingFields)) {
            $row['Modules'] = $this->training->modules->pluck('name')->join(', ');
        }
        if (in_array('start_date', $trainingFields)) {
            $row['Start Date'] = $this->training->start_date?->format('Y-m-d');
        }
        if (in_array('end_date', $trainingFields)) {
            $row['End Date'] = $this->training->end_date?->format('Y-m-d');
        }
        if (in_array('duration_days', $trainingFields)) {
            $row['Duration (Days)'] = $this->training->start_date && $this->training->end_date 
                ? $this->training->start_date->diffInDays($this->training->end_date) + 1 
                : '';
        }
        if (in_array('location', $trainingFields)) {
            $row['Location'] = $this->training->locations->pluck('name')->join(', ');
        }

        // Participant status
        if (in_array('registration_date', $participantFields)) {
            $row['Registration Date'] = $participant->registration_date?->format('Y-m-d');
        }
        if (in_array('attendance_status', $participantFields)) {
            $row['Attendance Status'] = ucfirst($participant->attendance_status);
        }
        if (in_array('completion_status', $participantFields)) {
            $row['Completion Status'] = ucfirst($participant->completion_status);
        }
        if (in_array('completion_date', $participantFields)) {
            $row['Completion Date'] = $participant->completion_date?->format('Y-m-d');
        }

        // Assessment information
        if ($this->config['include_assessments'] ?? false) {
            $calculation = $this->training->calculateOverallScore($participant);
            
            if (in_array('overall_score', $assessmentFields)) {
                $row['Overall Score'] = $calculation['all_assessed'] ? $calculation['score'] . '%' : 'Not assessed';
            }
            if (in_array('overall_status', $assessmentFields)) {
                $row['Overall Status'] = $calculation['status'];
            }
            if (in_array('assessment_progress', $assessmentFields)) {
                $progress = $calculation['total_categories'] > 0 
                    ? round(($calculation['assessed_categories'] / $calculation['total_categories']) * 100, 1)
                    : 0;
                $row['Assessment Progress'] = $progress . '%';
            }

            // Individual category results
            if (in_array('individual_categories', $assessmentFields)) {
                foreach ($this->training->assessmentCategories as $category) {
                    $result = $participant->assessmentResults
                        ->where('assessment_category_id', $category->id)
                        ->first();
                    
                    $columnName = $category->name . ' (' . $category->pivot->weight_percentage . '%)';
                    $row[$columnName] = $result ? strtoupper($result->result) : 'NOT ASSESSED';
                }
            }

            if (in_array('assessment_date', $assessmentFields)) {
                $latestAssessment = $participant->assessmentResults->sortByDesc('assessment_date')->first();
                $row['Assessment Date'] = $latestAssessment?->assessment_date?->format('Y-m-d');
            }
        }

        return $row;
    }

    public function title(): string
    {
        $title = $this->training->title;
        // Sanitize title for Excel sheet name (max 31 chars, no special chars)
        $title = preg_replace('/[^\w\s-]/', '', $title);
        return substr($title, 0, 31);
    }

    public function headings(): array
    {
        // Generate dynamic headings based on selected fields
        $participant = $this->training->participants()->with([
            'user.facility.subcounty.county',
            'user.department',
            'user.cadre',
            'assessmentResults.assessmentCategory'
        ])->first();

        if (!$participant) {
            return ['No participants found'];
        }

        $sampleRow = $this->formatParticipantRow($participant);
        return array_keys($sampleRow);
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'size' => 12],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'E8F4FD']],
                'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THICK]],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                // Add training header information
                $sheet = $event->sheet;
                $training = $this->training;
                
                // Insert training info at top
                $sheet->getDelegate()->insertNewRowBefore(1, 3);
                
                $sheet->setCellValue('A1', 'TRAINING: ' . $training->title);
                $sheet->setCellValue('A2', 'TYPE: ' . ($training->type === 'global_training' ? 'MOH Global Training' : 'Facility Mentorship'));
                $sheet->setCellValue('A3', 'PERIOD: ' . ($training->start_date ? $training->start_date->format('M j, Y') : 'TBD') . 
                    ' to ' . ($training->end_date ? $training->end_date->format('M j, Y') : 'TBD'));
                
                // Style training header
                $sheet->getStyle('A1:A3')->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle('A1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('2563EB');
                $sheet->getStyle('A1')->getFont()->getColor()->setRGB('FFFFFF');
                
                // Freeze header rows
                $sheet->getDelegate()->freezePane('A5');
            },
        ];
    }
}

// Participant Training History Sheet
class ParticipantTrainingsSheet implements FromCollection, WithTitle, WithHeadings, WithStyles, ShouldAutoSize
{
    protected User $participant;
    protected array $config;

    public function __construct(User $participant, array $config)
    {
        $this->participant = $participant;
        $this->config = $config;
    }

    public function collection()
    {
        return $this->participant->trainingParticipations()
            ->with([
                'training.programs',
                'training.locations',
                'training.facility',
                'training.county',
                'assessmentResults.assessmentCategory'
            ])
            ->get()
            ->map(function ($participation) {
                return $this->formatTrainingRow($participation);
            });
    }

    protected function formatTrainingRow($participation): array
    {
        $training = $participation->training;
        
        return [
            'Training Title' => $training->title,
            'Training ID' => $training->identifier,
            'Type' => $training->type === 'global_training' ? 'MOH Global' : 'Facility Mentorship',
            'Programs' => $training->programs->pluck('name')->join(', '),
            'Start Date' => $training->start_date?->format('Y-m-d'),
            'End Date' => $training->end_date?->format('Y-m-d'),
            'Location' => $training->locations->pluck('name')->join(', ') ?: 
                         ($training->facility?->name ?? $training->county?->name ?? 'Not specified'),
            'Registration Date' => $participation->registration_date?->format('Y-m-d'),
            'Attendance Status' => ucfirst($participation->attendance_status),
            'Completion Status' => ucfirst($participation->completion_status),
            'Completion Date' => $participation->completion_date?->format('Y-m-d'),
            'Overall Score' => $this->getOverallScore($participation),
            'Overall Status' => $this->getOverallStatus($participation),
        ];
    }

    protected function getOverallScore($participation): string
    {
        if (!$participation->training->hasAssessments()) {
            return 'No assessments';
        }
        
        $calculation = $participation->training->calculateOverallScore($participation);
        return $calculation['all_assessed'] ? $calculation['score'] . '%' : 'Not assessed';
    }

    protected function getOverallStatus($participation): string
    {
        if (!$participation->training->hasAssessments()) {
            return 'No assessments';
        }
        
        $calculation = $participation->training->calculateOverallScore($participation);
        return $calculation['status'];
    }

    public function title(): string
    {
        $name = $this->participant->full_name;
        // Sanitize for Excel sheet name
        $name = preg_replace('/[^\w\s-]/', '', $name);
        return substr($name, 0, 31);
    }

    public function headings(): array
    {
        return [
            'Training Title',
            'Training ID', 
            'Type',
            'Programs',
            'Start Date',
            'End Date',
            'Location',
            'Registration Date',
            'Attendance Status',
            'Completion Status', 
            'Completion Date',
            'Overall Score',
            'Overall Status'
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'size' => 12],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'E8F4FD']],
            ],
        ];
    }
} 