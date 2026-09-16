<?php

namespace App\Filament\Resources\TrainingExportResource\Pages;

use App\Filament\Resources\TrainingExportResource;
use App\Models\Training;
use App\Models\User;
use Filament\Actions;
use Filament\Resources\Pages\Page;
use Filament\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class PreviewTrainingExport extends Page {

    protected static string $resource = TrainingExportResource::class;
    protected static string $view = 'filament.pages.preview-training-export';
    public $previewData = [];
    public $selectedTraining = null;
    public $activeTab = 0;
    // Filter and Search properties
    public $search = '';
    public $sortColumn = null;
    public $sortDirection = 'asc';
    public $perPage = 50;
    public $currentPage = 1;

    public function mount(): void {
        $data = session('export_preview_data');

        if (!$data) {
            Notification::make()
                    ->title('No Preview Data')
                    ->body('Please configure export settings first.')
                    ->warning()
                    ->send();

            redirect()->route('filament.admin.resources.training-exports.create');
            return;
        }

        $this->previewData = $this->generatePreviewData($data);

        if (!empty($this->previewData['sheets'])) {
            $this->selectedTraining = array_key_first($this->previewData['sheets']);
        }
    }

    protected function getHeaderActions(): array {
        return [
                    Actions\Action::make('download')
                    ->label('Download Full Export')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->size('lg')
                    ->action('downloadExport'),
                    Actions\Action::make('back')
                    ->label('Back to Configuration')
                    ->icon('heroicon-o-arrow-left')
                    ->color('gray')
                    ->url(fn(): string => static::getResource()::getUrl('create')),
        ];
    }

    public function downloadExport() {
        $data = session('export_preview_data');

        if (!$data) {
            Notification::make()
                    ->title('Export Failed')
                    ->body('Session expired. Please reconfigure export.')
                    ->danger()
                    ->send();
            return;
        }

        try {
            $timestamp = Carbon::now()->format('Y-m-d_H-i-s');
            $exportType = Str::slug($data['export_type']);
            $fileFormat = $data['file_format'] ?? 'xlsx';

            if ($fileFormat === 'xlsx') {
                $filename = "export_{$exportType}_{$timestamp}.xlsx";
                $this->generateExcelFile($data, $filename);
            } else {
                $filename = "export_{$exportType}_{$timestamp}.csv";
                $csvContent = $this->createCsvContent($data);
                $this->downloadCsvFile($csvContent, $filename);
            }

            Notification::make()
                    ->title('Export Complete')
                    ->body("Downloaded: {$filename}")
                    ->success()
                    ->send();
        } catch (\Exception $e) {
            Notification::make()
                    ->title('Export Failed')
                    ->body($e->getMessage())
                    ->danger()
                    ->send();
        }
    }

    public function changeTab($index) {
        $this->activeTab = $index;
        $sheets = array_keys($this->previewData['sheets']);
        $this->selectedTraining = $sheets[$index] ?? null;
        $this->resetFilters();
    }

    public function sortBy($column) {
        if ($this->sortColumn === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortColumn = $column;
            $this->sortDirection = 'asc';
        }
        $this->currentPage = 1;
    }

    public function resetFilters() {
        $this->search = '';
        $this->sortColumn = null;
        $this->sortDirection = 'asc';
        $this->currentPage = 1;
    }

    public function updatedSearch() {
        $this->currentPage = 1;
    }

    public function getFilteredRows() {
        if (!$this->selectedTraining || !isset($this->previewData['sheets'][$this->selectedTraining])) {
            return [];
        }

        $sheet = $this->previewData['sheets'][$this->selectedTraining];
        $rows = $sheet['rows'];

        // Search filter
        if ($this->search) {
            $rows = array_filter($rows, function ($row) {
                foreach ($row as $cell) {
                    if (stripos((string) $cell, $this->search) !== false) {
                        return true;
                    }
                }
                return false;
            });
        }

        // Sort
        if ($this->sortColumn !== null) {
            $rows = array_values($rows); // Re-index after filter
            usort($rows, function ($a, $b) {
                $aVal = $a[$this->sortColumn] ?? '';
                $bVal = $b[$this->sortColumn] ?? '';

                $result = strcasecmp((string) $aVal, (string) $bVal);
                return $this->sortDirection === 'asc' ? $result : -$result;
            });
        }

        return array_values($rows);
    }

    public function getPaginatedRows() {
        $rows = $this->getFilteredRows();
        $offset = ($this->currentPage - 1) * $this->perPage;
        return array_slice($rows, $offset, $this->perPage);
    }

    public function getTotalPages() {
        $rows = $this->getFilteredRows();
        return max(1, ceil(count($rows) / $this->perPage));
    }

    public function nextPage() {
        if ($this->currentPage < $this->getTotalPages()) {
            $this->currentPage++;
        }
    }

    public function previousPage() {
        if ($this->currentPage > 1) {
            $this->currentPage--;
        }
    }

    public function exportCurrentView() {
        if (!$this->selectedTraining || !isset($this->previewData['sheets'][$this->selectedTraining])) {
            Notification::make()
                    ->title('Export Failed')
                    ->body('No data to export')
                    ->danger()
                    ->send();
            return;
        }

        $sheet = $this->previewData['sheets'][$this->selectedTraining];
        $rows = $this->getFilteredRows();

        $csvContent = implode(',', array_map([$this, 'csvEscape'], $sheet['headers'])) . "\n";

        foreach ($rows as $row) {
            $csvContent .= implode(',', array_map([$this, 'csvEscape'], $row)) . "\n";
        }

        $filename = Str::slug($sheet['name']) . '_filtered_' . Carbon::now()->format('Y-m-d_H-i-s') . '.csv';
        $this->downloadCsvFile($csvContent, $filename);

        Notification::make()
                ->title('Filtered Export Complete')
                ->body("Downloaded: {$filename}")
                ->success()
                ->send();
    }

    protected function generatePreviewData(array $data): array {
        $preview = [
            'export_type' => $data['export_type'],
            'generated_at' => Carbon::now()->format('F j, Y g:i A'),
            'stats' => [],
            'sheets' => []
        ];

        switch ($data['export_type']) {
            case 'training_participants':
                $preview = array_merge($preview, $this->generateParticipantsPreview($data));
                break;
            case 'participant_trainings':
                $preview = array_merge($preview, $this->generateParticipantHistoryPreview($data));
                break;
            case 'training_summary':
                $preview = array_merge($preview, $this->generateSummaryPreview($data));
                break;
        }

        return $preview;
    }

    protected function generateParticipantsPreview(array $data): array {
        $trainings = Training::with([
                    'facility', 'county', 'partner', 'programs', 'assessmentCategories', 'mentor', 'organizer',
                    'participants.user.facility.subcounty.county',
                    'participants.user.department',
                    'participants.user.cadre',
                    'participants.assessmentResults'
                ])->whereIn('id', $data['selected_trainings'])->get();

        $sheets = [];
        $totalParticipants = 0;

        foreach ($trainings as $training) {
            $participants = $this->getFilteredParticipants($training, $data);
            $totalParticipants += $participants->count();

            $sheets[$training->id] = [
                'name' => $training->title,
                'type' => $training->type === 'global_training' ? 'MOH Training' : 'Facility Mentorship',
                'info' => [
                    'Programs' => $training->programs->pluck('name')->implode(', ') ?: 'N/A',
                    'Location' => $this->getActivityLocation($training),
                    'Start Date' => $training->start_date?->format('M j, Y') ?? 'N/A',
                    'End Date' => $training->end_date?->format('M j, Y') ?? 'N/A',
                    'Participants' => $participants->count(),
                    'Assessment Categories' => $training->assessmentCategories->count(),
                ],
                'headers' => $this->buildHeaders($data, $training),
                'rows' => $participants->map(function ($participant) use ($training, $data) {
                    return $this->buildParticipantRow($participant, $training, $data);
                })->toArray()
            ];
        }

        return [
            'stats' => [
                'Total Trainings/Mentorships' => $trainings->count(),
                'Total Participants/Mentees' => $totalParticipants,
                'MOH Trainings' => $trainings->where('type', 'global_training')->count(),
                'Facility Mentorships' => $trainings->where('type', 'facility_mentorship')->count(),
            ],
            'sheets' => $sheets
        ];
    }

    protected function generateParticipantHistoryPreview(array $data): array {
        $participants = User::with([
                    'facility.subcounty.county', 'department', 'cadre',
                    'trainingParticipations.training.programs',
                    'trainingParticipations.training.facility',
                    'trainingParticipations.training.county',
                    'trainingParticipations.assessmentResults'
                ])->whereIn('id', $data['selected_participants'])->get();

        $sheets = [];
        $totalActivities = 0;

        foreach ($participants as $participant) {
            $activities = $participant->trainingParticipations;
            $totalActivities += $activities->count();

            $sheets[$participant->id] = [
                'name' => $participant->full_name,
                'type' => 'Participant History',
                'info' => [
                    'Facility' => $participant->facility?->name ?? 'Not specified',
                    'County' => $participant->facility?->subcounty?->county?->name ?? 'Not specified',
                    'Department' => $participant->department?->name ?? 'Not specified',
                    'Cadre' => $participant->cadre?->name ?? 'Not specified',
                    'Total Activities' => $activities->count(),
                ],
                'headers' => [
                    'Activity Title', 'Activity Type', 'Programs', 'Start Date',
                    'End Date', 'Location', 'Registration Date', 'Completion Status', 'Overall Result'
                ],
                'rows' => $activities->map(function ($participation) {
                    $training = $participation->training;
                    return [
                        $training->title,
                        $training->type === 'global_training' ? 'MOH Training' : 'Facility Mentorship',
                        $training->programs->pluck('name')->implode('; ') ?: 'N/A',
                        $training->start_date?->format('Y-m-d') ?? '',
                        $training->end_date?->format('Y-m-d') ?? '',
                        $this->getActivityLocation($training),
                        $participation->registration_date?->format('Y-m-d') ?? '',
                        $participation->completion_status,
                        $this->calculateOverallResult($participation, $training)
                    ];
                })->toArray()
            ];
        }

        return [
            'stats' => [
                'Total Participants/Mentees' => $participants->count(),
                'Total Activities' => $totalActivities,
            ],
            'sheets' => $sheets
        ];
    }

    protected function generateSummaryPreview(array $data): array {
        $trainings = Training::with(['participants', 'programs', 'county', 'partner', 'facility'])
                        ->whereIn('id', $data['selected_trainings'])->get();

        $rows = [];
        foreach ($trainings as $training) {
            $totalParticipants = $training->participants->count();
            $completedParticipants = $training->participants->where('completion_status', 'completed')->count();
            $completionRate = $totalParticipants > 0 ? round(($completedParticipants / $totalParticipants) * 100, 1) : 0;

            $rows[] = [
                $training->title,
                $training->type === 'global_training' ? 'MOH Training' : 'Facility Mentorship',
                $this->getLeadOrganization($training),
                $training->programs->pluck('name')->implode('; ') ?: 'N/A',
                $training->start_date?->format('Y-m-d') ?? '',
                $training->end_date?->format('Y-m-d') ?? '',
                $this->getActivityLocation($training),
                $totalParticipants,
                $completedParticipants,
                $completionRate . '%'
            ];
        }

        return [
            'stats' => [
                'Total Activities' => $trainings->count(),
                'Total Participants' => $trainings->sum(fn($t) => $t->participants->count()),
            ],
            'sheets' => [
                'summary' => [
                    'name' => 'Activity Summary',
                    'type' => 'Summary Report',
                    'info' => [],
                    'headers' => [
                        'Activity Name', 'Activity Type', 'Lead Organization', 'Programs',
                        'Start Date', 'End Date', 'Location', 'Total Enrolled', 'Completed', 'Completion Rate'
                    ],
                    'rows' => $rows
                ]
            ]
        ];
    }

    // Helper methods from CreateTrainingExport
    protected function buildHeaders(array $data, Training $training = null): array {
        $headers = [
            "Attendant's Name", 'County', 'Subcounty', 'Health MFL Code', 'Facility Name',
            'Facility Type (Level of care)', 'Department', 'Cadre', 'Mobile Number', 'Month', 'Year'
        ];

        if ($this->shouldIncludeAssessmentCategories($data, $training)) {
            foreach ($training->assessmentCategories->sortBy('pivot.order_sequence') as $category) {
                $headers[] = $category->name;
            }
        }

        if ($this->shouldIncludeOverallResult($data, $training)) {
            $headers[] = 'Overall Result';
        }

        return $headers;
    }

    protected function buildParticipantRow($participant, Training $training, array $data): array {
        $user = $participant->user;

        $row = [
            $user->full_name ?? '',
            $user->facility?->subcounty?->county?->name ?? '',
            $user->facility?->subcounty?->name ?? '',
            $user->facility?->mfl_code ?? '',
            $user->facility?->name ?? '',
            $this->determineFacilityLevel($user->facility),
            $user->department?->name ?? '',
            $user->cadre?->name ?? '',
            $user->phone ?? '',
            $training->start_date?->format('F') ?? '',
            $training->start_date?->format('Y') ?? ''
        ];

        if ($this->shouldIncludeAssessmentCategories($data, $training)) {
            $row = array_merge($row, $this->buildAssessmentCategoryResults($participant, $training, $data));
        }

        if ($this->shouldIncludeOverallResult($data, $training)) {
            $row[] = $this->calculateOverallResult($participant, $training);
        }

        return $row;
    }

    protected function getFilteredParticipants(Training $training, array $data) {
        $query = $training->participants()->with([
            'user.facility.subcounty.county',
            'user.department',
            'user.cadre',
            'assessmentResults'
        ]);

        if ($training->type === 'global_training') {
            if (!empty($data['filter_counties'])) {
                $query->whereHas('user.facility.subcounty', fn($q) => $q->whereIn('county_id', $data['filter_counties']));
            }
            if (!empty($data['filter_facilities'])) {
                $query->whereHas('user', fn($q) => $q->whereIn('facility_id', $data['filter_facilities']));
            }
        }

        if (!empty($data['filter_departments'])) {
            $query->whereHas('user', fn($q) => $q->whereIn('department_id', $data['filter_departments']));
        }
        if (!empty($data['filter_cadres'])) {
            $query->whereHas('user', fn($q) => $q->whereIn('cadre_id', $data['filter_cadres']));
        }

        if (!empty($data['filter_years'])) {
            $query->whereHas('training', fn($q) => $q->whereIn(DB::raw('YEAR(start_date)'), $data['filter_years']));
        }

        return $query->get();
    }

    protected function shouldIncludeAssessmentCategories(array $data, ?Training $training): bool {
        return ($data['include_assessments'] ?? false) &&
                ($data['include_individual_categories'] ?? false) &&
                $training &&
                $training->assessmentCategories->isNotEmpty() &&
                $this->trainingHasActualAssessments($training);
    }

    protected function shouldIncludeOverallResult(array $data, ?Training $training): bool {
        return ($data['include_assessments'] ?? false) &&
                $training &&
                $this->trainingHasActualAssessments($training);
    }

    protected function trainingHasActualAssessments(Training $training): bool {
        if (property_exists($training, 'assess_participants') && !$training->assess_participants) {
            return false;
        }

        if ($training->assessmentCategories->isEmpty()) {
            return false;
        }

        return $training->participants()->whereHas('assessmentResults')->exists();
    }

    protected function buildAssessmentCategoryResults($participant, Training $training, array $data): array {
        $results = [];
        $incompleteDisplay = $data['incomplete_display'] ?? 'not_assessed';
        $categories = $training->assessmentCategories->sortBy('pivot.order_sequence');
        $assessmentResults = $participant->assessmentResults->keyBy('assessment_category_id');

        foreach ($categories as $category) {
            $result = $assessmentResults->get($category->id);
            $results[] = $result && $result->result ? strtoupper($result->result) : $this->getIncompleteText($incompleteDisplay);
        }

        return $results;
    }

    protected function calculateOverallResult($participant, Training $training): string {
        if (!$this->trainingHasActualAssessments($training)) {
            return match ($participant->completion_status) {
                'completed' => 'COMPLETED',
                'dropped' => 'DROPPED',
                'in_progress' => 'IN PROGRESS',
                default => 'REGISTERED'
            };
        }

        if (method_exists($training, 'calculateOverallScore')) {
            $calculation = $training->calculateOverallScore($participant);

            if (!$calculation['all_assessed']) {
                return 'ASSESSMENT INCOMPLETE';
            }

            return match ($calculation['status']) {
                'PASSED' => 'PASS',
                'FAILED' => 'FAIL',
                default => 'PENDING'
            };
        }

        $hasAnyAssessmentResults = $participant->assessmentResults()
                ->whereIn('assessment_category_id', $training->assessmentCategories->pluck('id'))
                ->exists();

        if (!$hasAnyAssessmentResults) {
            return 'NOT ASSESSED';
        }

        $totalCategories = $training->assessmentCategories->count();
        $assessedCategories = $participant->assessmentResults()
                ->whereIn('assessment_category_id', $training->assessmentCategories->pluck('id'))
                ->count();

        if ($assessedCategories < $totalCategories) {
            return 'ASSESSMENT INCOMPLETE';
        }

        $passedCategories = $participant->assessmentResults()
                ->whereIn('assessment_category_id', $training->assessmentCategories->pluck('id'))
                ->where('result', 'pass')
                ->count();

        return $passedCategories === $totalCategories ? 'PASS' : 'FAIL';
    }

    protected function determineFacilityLevel($facility): string {
        if (!$facility)
            return '';

        if ($facility->facilityType && $facility->facilityType->name) {
            return strtoupper($facility->facilityType->name);
        }

        $facilityName = strtolower($facility->name ?? '');

        if (strpos($facilityName, 'national') !== false ||
                strpos($facilityName, 'kenyatta') !== false ||
                strpos($facilityName, 'moi') !== false) {
            return 'LEVEL 6 - NATIONAL REFERRAL';
        }

        if (strpos($facilityName, 'county') !== false &&
                (strpos($facilityName, 'hospital') !== false || strpos($facilityName, 'referral') !== false)) {
            return 'LEVEL 5 - COUNTY REFERRAL';
        }

        if (strpos($facilityName, 'hospital') !== false || strpos($facilityName, 'sub') !== false) {
            return 'LEVEL 4 - SUB-COUNTY HOSPITAL';
        }

        if (strpos($facilityName, 'health centre') !== false || strpos($facilityName, 'health center') !== false) {
            return 'LEVEL 3 - HEALTH CENTRE';
        }

        if (strpos($facilityName, 'dispensary') !== false) {
            return 'LEVEL 2 - DISPENSARY';
        }

        return 'LEVEL 3 - HEALTH CENTRE';
    }

    protected function getActivityLocation(Training $training): string {
        if ($training->facility)
            return $training->facility->name;
        if ($training->county)
            return $training->county->name . ' County';
        return 'Various Locations';
    }

    protected function getLeadOrganization(Training $training): string {
        return match ($training->lead_type) {
            'national' => 'Ministry of Health',
            'county' => $training->county?->name ?? 'County Government',
            'partner' => $training->partner?->name ?? 'Partner Organization',
            default => 'Not specified'
        };
    }

    protected function getIncompleteText(string $incompleteDisplay): string {
        return match ($incompleteDisplay) {
            'blank' => '',
            'not_assessed' => 'NOT ASSESSED',
            'pending' => 'PENDING',
            'dash' => '—',
            default => 'NOT ASSESSED'
        };
    }

    protected function getExportTypeLabel(string $exportType): string {
        return match ($exportType) {
            'training_participants' => 'Participants Export',
            'participant_trainings' => 'Participant History Export',
            'training_summary' => 'Summary Report Export',
            default => ucwords(str_replace('_', ' ', $exportType))
        };
    }

    protected function sanitizeSheetName(string $name): string {
        $name = preg_replace('/[^\w\s-]/', '', $name);
        $name = str_replace(['/', '\\', '?', '*', ':', '[', ']'], '', $name);
        return substr($name, 0, 31);
    }

    // Excel generation methods
    protected function generateExcelFile(array $data, string $filename): void {
        $workbook = ['filename' => $filename, 'worksheets' => []];

        if ($data['include_summary_sheet'] ?? true) {
            $workbook['worksheets'][] = $this->createSummarySheet($data);
        }

        switch ($data['export_type']) {
            case 'training_participants':
                $this->addParticipantSheets($workbook, $data);
                break;
            case 'participant_trainings':
                $this->addParticipantHistorySheets($workbook, $data);
                break;
            case 'training_summary':
                $workbook['worksheets'][] = $this->createSummaryReportSheet($data);
                break;
        }

        if ($this->shouldIncludeAssessmentSheets($data)) {
            if ($data['create_assessment_summary'] ?? false) {
                $workbook['worksheets'][] = $this->createAssessmentSummarySheet($data['selected_trainings']);
            }
            if ($data['include_category_definitions'] ?? false) {
                $workbook['worksheets'][] = $this->createCategoryDefinitionsSheet($data['selected_trainings']);
            }
        }

        $this->downloadExcelFile($workbook);
    }

    protected function shouldIncludeAssessmentSheets(array $data): bool {
        return ($data['include_assessments'] ?? false) &&
                $data['export_type'] === 'training_participants' &&
                !empty($data['selected_trainings']);
    }

    protected function addParticipantSheets(array &$workbook, array $data): void {
        $trainings = Training::with([
                    'facility', 'county', 'partner', 'programs', 'mentor', 'organizer',
                    'participants.user.facility.subcounty.county',
                    'participants.user.department',
                    'participants.user.cadre',
                    'participants.assessmentResults.assessor',
                    'assessmentCategories'
                ])->whereIn('id', $data['selected_trainings'])->get();

        foreach ($trainings as $training) {
            $workbook['worksheets'][] = $this->createParticipantSheet($training, $data);
        }
    }

    protected function addParticipantHistorySheets(array &$workbook, array $data): void {
        $participants = User::with([
                    'facility.subcounty.county', 'department', 'cadre',
                    'trainingParticipations.training.programs',
                    'trainingParticipations.assessmentResults'
                ])->whereIn('id', $data['selected_participants'])->get();

        foreach ($participants as $participant) {
            $workbook['worksheets'][] = $this->createParticipantHistorySheet($participant, $data);
        }
    }

    protected function createSummarySheet(array $data): array {
        $summary = [
            ['Export Summary Dashboard'],
            ['Generated: ' . Carbon::now()->format('F j, Y g:i A')],
            ['Export Type: ' . $this->getExportTypeLabel($data['export_type'])],
            ['']
        ];

        if ($data['export_type'] === 'training_participants' && !empty($data['selected_trainings'])) {
            $trainings = Training::with('participants')->whereIn('id', $data['selected_trainings'])->get();
            $totalParticipants = $trainings->sum(fn($t) => $t->participants->count());
            $completedParticipants = $trainings->sum(fn($t) => $t->participants->where('completion_status', 'completed')->count());
            $completionRate = $totalParticipants > 0 ? round(($completedParticipants / $totalParticipants) * 100, 1) : 0;

            $summary = array_merge($summary, [
                ['OVERVIEW'],
                ['Metric', 'Value', 'Details'],
                ['Total Activities', $trainings->count()],
                ['MOH Trainings', $trainings->where('type', 'global_training')->count()],
                ['Facility Mentorships', $trainings->where('type', 'facility_mentorship')->count()],
                ['Total Participants', $totalParticipants],
                ['Completed Participants', $completedParticipants, $completionRate . '%']
            ]);
        }

        return ['name' => 'Summary', 'data' => $summary];
    }

    protected function createParticipantSheet(Training $training, array $data): array {
        $isTraining = $training->type === 'global_training';
        $activityType = $isTraining ? 'TRAINING' : 'MENTORSHIP';

        $worksheetData = [
            [strtoupper("{$activityType}: {$training->title}")],
            [strtoupper('Type: ' . ($isTraining ? 'MOH Training' : 'Facility Mentorship'))],
            [strtoupper('Programs/Modules: ' . ($training->programs->pluck('name')->implode(', ') ?: 'N/A'))]
        ];

        if (!$isTraining && $training->mentor) {
            $worksheetData[] = [strtoupper('Mentored by: ' . $training->mentor->full_name)];
        }

        if ($training->organizer) {
            $worksheetData[] = [strtoupper('Coordinated by: ' . $training->organizer->full_name)];
        }

        $worksheetData[] = [strtoupper('Location: ' . $this->getActivityLocation($training))];

        if ($training->start_date) {
            $dateRange = $training->start_date->format('M j, Y');
            if ($training->end_date) {
                $dateRange .= ' to ' . $training->end_date->format('M j, Y');
            }
            $worksheetData[] = [strtoupper('Dates: ' . $dateRange)];
        }

        if ($this->trainingHasActualAssessments($training)) {
            $worksheetData[] = [strtoupper('Assessment Categories: ' . $training->assessmentCategories->count())];
        } else {
            $worksheetData[] = [strtoupper('Assessment: Not configured or not used')];
        }

        $worksheetData[] = [''];

        $headers = $this->buildHeaders($data, $training);
        $worksheetData[] = $headers;

        $participants = $this->getFilteredParticipants($training, $data);
        foreach ($participants as $participant) {
            $worksheetData[] = $this->buildParticipantRow($participant, $training, $data);
        }

        return [
            'name' => $this->sanitizeSheetName($training->title),
            'data' => $worksheetData
        ];
    }

    protected function createParticipantHistorySheet(User $participant, array $data): array {
        $worksheetData = [
            [strtoupper('PARTICIPANT: ' . $participant->full_name)],
            [strtoupper('Facility: ' . ($participant->facility?->name ?? 'Not specified'))],
            [strtoupper('County: ' . ($participant->facility?->subcounty?->county?->name ?? 'Not specified'))],
            [''],
            [
                strtoupper('Activity Title'),
                strtoupper('Activity Type'),
                strtoupper('Programs'),
                strtoupper('Start Date'),
                strtoupper('End Date'),
                strtoupper('Location'),
                strtoupper('Registration Date'),
                strtoupper('Completion Status'),
                strtoupper('Overall Result')
            ]
        ];

        $activities = $participant->trainingParticipations()->with([
                    'training.programs', 'training.facility', 'training.county', 'assessmentResults'
                ])->get();

        foreach ($activities as $participation) {
            $training = $participation->training;
            $isTraining = $training->type === 'global_training';

            $worksheetData[] = [
                strtoupper($training->title),
                strtoupper($isTraining ? 'MOH Training' : 'Facility Mentorship'),
                strtoupper($training->programs->pluck('name')->implode('; ') ?: 'N/A'),
                strtoupper($training->start_date?->format('Y-m-d') ?? ''),
                strtoupper($training->end_date?->format('Y-m-d') ?? ''),
                strtoupper($this->getActivityLocation($training)),
                strtoupper($participation->registration_date?->format('Y-m-d') ?? ''),
                strtoupper($participation->completion_status),
                strtoupper($this->calculateOverallResult($participation, $training))
            ];
        }

        return [
            'name' => $this->sanitizeSheetName($participant->full_name),
            'data' => $worksheetData
        ];
    }

    protected function createSummaryReportSheet(array $data): array {
        $worksheetData = [
            [strtoupper('ACTIVITY SUMMARY REPORT')],
            [strtoupper('Generated: ' . Carbon::now()->format('F j, Y g:i A'))],
            [''],
            [
                strtoupper('Activity Name'),
                strtoupper('Activity Type'),
                strtoupper('Lead Organization'),
                strtoupper('Programs'),
                strtoupper('Start Date'),
                strtoupper('End Date'),
                strtoupper('Location'),
                strtoupper('Total Enrolled'),
                strtoupper('Completed'),
                strtoupper('Completion Rate')
            ]
        ];

        $trainings = Training::with(['participants', 'programs', 'county', 'partner'])
                ->whereIn('id', $data['selected_trainings'])
                ->get();

        foreach ($trainings as $training) {
            $isTraining = $training->type === 'global_training';
            $totalParticipants = $training->participants->count();
            $completedParticipants = $training->participants->where('completion_status', 'completed')->count();
            $completionRate = $totalParticipants > 0 ? round(($completedParticipants / $totalParticipants) * 100, 1) : 0;

            $worksheetData[] = [
                strtoupper($training->title),
                strtoupper($isTraining ? 'MOH Training' : 'Facility Mentorship'),
                strtoupper($this->getLeadOrganization($training)),
                strtoupper($training->programs->pluck('name')->implode('; ') ?: 'N/A'),
                strtoupper($training->start_date?->format('Y-m-d') ?? ''),
                strtoupper($training->end_date?->format('Y-m-d') ?? ''),
                strtoupper($this->getActivityLocation($training)),
                $totalParticipants,
                $completedParticipants,
                $completionRate . '%'
            ];
        }

        return [
            'name' => strtoupper('Activity Summary'),
            'data' => $worksheetData
        ];
    }

    protected function createAssessmentSummarySheet(array $selectedTrainings): array {
        $worksheetData = [
            ['ASSESSMENT SUMMARY REPORT'],
            ['Generated: ' . Carbon::now()->format('F j, Y g:i A')],
            [''],
            ['Activity Name', 'Total Participants', 'Fully Assessed', 'Passed Overall', 'Failed Overall', 'Pass Rate']
        ];

        $trainings = Training::with(['participants.assessmentResults', 'assessmentCategories'])
                        ->whereIn('id', $selectedTrainings)->get();

        foreach ($trainings as $training) {
            $participants = $training->participants;
            $totalParticipants = $participants->count();

            $fullyAssessed = 0;
            $passed = 0;
            $failed = 0;

            foreach ($participants as $participant) {
                $result = strtoupper($this->calculateOverallResult($participant, $training));

                if (!in_array($result, ['INCOMPLETE', 'PENDING', 'NOT ASSESSED', 'ASSESSMENT INCOMPLETE'])) {
                    $fullyAssessed++;
                    $result === 'PASS' ? $passed++ : $failed++;
                }
            }

            $passRate = $fullyAssessed > 0 ? round(($passed / $fullyAssessed) * 100, 1) : 0;

            $worksheetData[] = [
                $training->title,
                $totalParticipants,
                $fullyAssessed,
                $passed,
                $failed,
                $passRate . '%'
            ];
        }

        return ['name' => 'Assessment Summary', 'data' => $worksheetData];
    }

    protected function createCategoryDefinitionsSheet(array $selectedTrainings): array {
        $worksheetData = [
            ['ASSESSMENT CATEGORIES DEFINITIONS'],
            ['This sheet explains the assessment categories used in each activity'],
            [''],
            ['Category Name', 'Description', 'Assessment Method', 'Used in Activities', 'Weights Used', 'Category Type']
        ];

        $trainings = Training::with('assessmentCategories')->whereIn('id', $selectedTrainings)->get();
        $allCategories = collect();

        foreach ($trainings as $training) {
            $activityType = $training->type === 'global_training' ? 'Training' : 'Mentorship';

            foreach ($training->assessmentCategories as $category) {
                if (!$allCategories->has($category->id)) {
                    $allCategories->put($category->id, [
                        'category' => $category,
                        'activities' => collect(),
                        'weights' => collect()
                    ]);
                }

                $allCategories[$category->id]['activities']->push("{$training->title} [{$activityType}]");
                $allCategories[$category->id]['weights']->push($category->pivot->weight_percentage . '%');
            }
        }

        foreach ($allCategories as $categoryData) {
            $category = $categoryData['category'];
            $worksheetData[] = [
                $category->name,
                $category->description ?? 'Not specified',
                $category->assessment_method ?? 'Not specified',
                $categoryData['activities']->unique()->implode('; '),
                $categoryData['weights']->unique()->implode(', '),
                ucfirst($category->category_type ?? 'general')
            ];
        }

        return ['name' => 'Assessment Categories', 'data' => $worksheetData];
    }

    protected function downloadExcelFile(array $workbook): void {
        $workbookJson = json_encode($workbook);

        $this->js("
        (function() {
            if (typeof XLSX === 'undefined') {
                const script = document.createElement('script');
                script.src = 'https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js';
                script.onload = generateExcel;
                document.head.appendChild(script);
            } else {
                generateExcel();
            }

            function generateExcel() {
                const workbookData = {$workbookJson};
                const wb = XLSX.utils.book_new();

                workbookData.worksheets.forEach(function(worksheet) {
                    const ws = XLSX.utils.aoa_to_sheet(worksheet.data);
                    
                    if (worksheet.data.length > 0) {
                        const colWidths = [];
                        for (let i = 0; i < worksheet.data[0].length; i++) {
                            colWidths.push({ width: 20 });
                        }
                        ws['!cols'] = colWidths;
                    }

                    XLSX.utils.book_append_sheet(wb, ws, worksheet.name);
                });

                XLSX.writeFile(wb, workbookData.filename);
            }
        })();
    ");
    }

// CSV Methods
    protected function createCsvContent(array $data): string {
        $content = "Export Report\n";
        $content .= "Generated: " . Carbon::now()->format('F j, Y g:i A') . "\n";
        $content .= "Export Type: " . $this->getExportTypeLabel($data['export_type']) . "\n\n";

        return $content . match ($data['export_type']) {
                    'training_participants' => $this->createParticipantsCsv($data),
                    'participant_trainings' => $this->createParticipantHistoryCsv($data),
                    'training_summary' => $this->createSummaryCsv($data),
                    default => ''
                };
    }

    protected function createParticipantsCsv(array $data): string {
        $csv = '';
        $trainings = Training::with([
                    'facility', 'county', 'partner', 'programs', 'mentor', 'organizer',
                    'participants.user.facility.subcounty.county',
                    'participants.user.department',
                    'participants.user.cadre',
                    'participants.assessmentResults.assessor',
                    'assessmentCategories'
                ])->whereIn('id', $data['selected_trainings'])->get();

        foreach ($trainings as $index => $training) {
            if ($index > 0)
                $csv .= "\n\n";

            $isTraining = $training->type === 'global_training';
            $activityType = $isTraining ? 'TRAINING' : 'MENTORSHIP';

            $csv .= strtoupper("{$activityType}: {$training->title}") . "\n";
            $csv .= strtoupper("Type: " . ($isTraining ? 'MOH Training' : 'Facility Mentorship')) . "\n";
            $csv .= strtoupper("Programs: " . ($training->programs->pluck('name')->implode(', ') ?: 'N/A')) . "\n";

            if (!$isTraining && $training->mentor) {
                $csv .= strtoupper("Mentored by: " . $training->mentor->full_name) . "\n";
            }

            if ($training->organizer) {
                $csv .= strtoupper("Coordinated by: " . $training->organizer->full_name) . "\n";
            }

            $csv .= strtoupper("Location: " . $this->getActivityLocation($training)) . "\n";

            if ($this->trainingHasActualAssessments($training)) {
                $csv .= strtoupper("Assessment Categories: " . $training->assessmentCategories->count()) . "\n";
            } else {
                $csv .= strtoupper("Assessment: Not configured or not used") . "\n";
            }

            $csv .= "\n";

            $headers = $this->buildHeaders($data, $training);
            $csv .= implode(',', array_map([$this, 'csvEscape'], $headers)) . "\n";

            $participants = $this->getFilteredParticipants($training, $data);
            foreach ($participants as $participant) {
                $row = $this->buildParticipantRow($participant, $training, $data);
                $csv .= implode(',', array_map([$this, 'csvEscape'], $row)) . "\n";
            }
        }

        return $csv;
    }

    protected function createParticipantHistoryCsv(array $data): string {
        $csv = '';
        $participants = User::with([
                    'facility.subcounty.county', 'department', 'cadre',
                    'trainingParticipations.training.programs',
                    'trainingParticipations.training.facility',
                    'trainingParticipations.training.county',
                    'trainingParticipations.assessmentResults'
                ])->whereIn('id', $data['selected_participants'])->get();

        $headers = [
            strtoupper('Activity Title'),
            strtoupper('Activity Type'),
            strtoupper('Programs'),
            strtoupper('Start Date'),
            strtoupper('End Date'),
            strtoupper('Location'),
            strtoupper('Registration Date'),
            strtoupper('Completion Status'),
            strtoupper('Overall Result')
        ];

        foreach ($participants as $index => $participant) {
            if ($index > 0)
                $csv .= "\n\n";

            $csv .= strtoupper("PARTICIPANT: {$participant->full_name}") . "\n";
            $csv .= strtoupper("Facility: " . ($participant->facility?->name ?? 'Not specified')) . "\n";
            $csv .= strtoupper("County: " . ($participant->facility?->subcounty?->county?->name ?? 'Not specified')) . "\n\n";
            $csv .= implode(',', array_map([$this, 'csvEscape'], $headers)) . "\n";

            $activities = $participant->trainingParticipations()->with([
                        'training.programs', 'training.facility', 'training.county', 'assessmentResults'
                    ])->get();

            foreach ($activities as $participation) {
                $training = $participation->training;
                $isTraining = $training->type === 'global_training';

                $row = [
                    strtoupper($training->title),
                    strtoupper($isTraining ? 'MOH Training' : 'Facility Mentorship'),
                    strtoupper($training->programs->pluck('name')->implode('; ') ?: 'N/A'),
                    strtoupper($training->start_date?->format('Y-m-d') ?? ''),
                    strtoupper($training->end_date?->format('Y-m-d') ?? ''),
                    strtoupper($this->getActivityLocation($training)),
                    strtoupper($participation->registration_date?->format('Y-m-d') ?? ''),
                    strtoupper($participation->completion_status),
                    strtoupper($this->calculateOverallResult($participation, $training))
                ];

                $csv .= implode(',', array_map([$this, 'csvEscape'], $row)) . "\n";
            }
        }

        return $csv;
    }

    protected function createSummaryCsv(array $data): string {
        $csv = "ACTIVITY SUMMARY REPORT\n\n";
        $headers = [
            'Activity Name', 'Activity Type', 'Lead Organization', 'Programs',
            'Start Date', 'End Date', 'Location', 'Total Enrolled', 'Completed', 'Completion Rate'
        ];
        $csv .= implode(',', array_map([$this, 'csvEscape'], $headers)) . "\n";

        $trainings = Training::with(['participants', 'programs', 'county', 'partner'])
                        ->whereIn('id', $data['selected_trainings'])->get();

        foreach ($trainings as $training) {
            $isTraining = $training->type === 'global_training';
            $totalParticipants = $training->participants->count();
            $completedParticipants = $training->participants->where('completion_status', 'completed')->count();
            $completionRate = $totalParticipants > 0 ? round(($completedParticipants / $totalParticipants) * 100, 1) : 0;

            $row = [
                $training->title,
                $isTraining ? 'MOH Training' : 'Facility Mentorship',
                $this->getLeadOrganization($training),
                $training->programs->pluck('name')->implode('; ') ?: 'N/A',
                $training->start_date?->format('Y-m-d') ?? '',
                $training->end_date?->format('Y-m-d') ?? '',
                $this->getActivityLocation($training),
                $totalParticipants,
                $completedParticipants,
                $completionRate . '%'
            ];

            $csv .= implode(',', array_map([$this, 'csvEscape'], $row)) . "\n";
        }

        return $csv;
    }

    protected function downloadCsvFile(string $csvContent, string $filename): void {
        $escapedContent = str_replace(["\r\n", "\r", "\n"], "\\n", addslashes($csvContent));

        $this->js("
        (function() {
            const csvContent = '{$escapedContent}';
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', '{$filename}');
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
        })();
    ");
    }

    protected function csvEscape($value): string {
        $value = (string) $value;

        if (strpos($value, ',') !== false || strpos($value, '"') !== false || strpos($value, "\n") !== false) {
            $value = '"' . str_replace('"', '""', $value) . '"';
        }

        return $value;
    }
}
