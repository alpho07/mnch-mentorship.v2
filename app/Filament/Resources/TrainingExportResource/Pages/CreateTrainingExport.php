<?php

namespace App\Filament\Resources\TrainingExportResource\Pages;

use App\Filament\Resources\TrainingExportResource;
use App\Models\Training;
use App\Models\User;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class CreateTrainingExport extends CreateRecord {

    protected static string $resource = TrainingExportResource::class;
    protected static bool $canCreateAnother = false;

    public function getTitle(): string {
        return 'Configure Export';
    }

    protected function getFormActions(): array {
        return [
                    Actions\Action::make('preview')
                    ->label('Preview Data')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->size('lg')
                    ->action('showPreview'),
                    Actions\Action::make('export')
                    ->label('Generate & Download Export')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('primary')
                    ->size('lg')
                    ->action(fn() => $this->generateExport()),
                    Actions\Action::make('cancel')
                    ->label('Cancel')
                    ->color('gray')
                    ->url(fn(): string => static::getResource()::getUrl('index')),
        ];
    }

    public function showPreview() {
        try {
            $data = $this->form->getState();
            $this->validateFormData($data);

            session(['export_preview_data' => $data]);

            return redirect()->route('filament.admin.resources.training-exports.preview');
        } catch (\Exception $e) {
            Notification::make()
                    ->title('Preview Failed')
                    ->body($e->getMessage())
                    ->danger()
                    ->send();
        }
    }

    protected function generateExport() {
        try {
            $data = $this->form->getState();
            $this->validateFormData($data);

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

    protected function validateFormData(array $data): void {
        if (empty($data['export_type'])) {
            throw new \Exception('Please select an export type');
        }

        if ($data['export_type'] === 'training_participants' && empty($data['selected_trainings'])) {
            throw new \Exception('Please select at least one training/mentorship');
        }

        if ($data['export_type'] === 'participant_trainings' && empty($data['selected_participants'])) {
            throw new \Exception('Please select at least one participant/mentee');
        }
    }

    protected function generateExcelFile(array $data, string $filename): void {
        $workbook = ['filename' => $filename, 'worksheets' => []];

        // Add summary sheet
        if ($data['include_summary_sheet'] ?? true) {
            $workbook['worksheets'][] = $this->createSummarySheet($data);
        }

        // Add main data sheets
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

        // Add optional assessment sheets
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

    protected function shouldIncludeOverallResult(array $data, ?Training $training): bool {
        // Only include overall result if:
        // 1. Assessments are enabled in export settings
        // 2. Training actually has assessments configured and being used
        return ($data['include_assessments'] ?? false) &&
                $training &&
                $this->trainingHasActualAssessments($training);
    }

    protected function addParticipantSheets(array &$workbook, array $data): void {
        $trainings = $this->loadTrainingsWithRelations($data['selected_trainings']);

        foreach ($trainings as $training) {
            $workbook['worksheets'][] = $this->createParticipantSheet($training, $data);
        }
    }

    protected function addParticipantHistorySheets(array &$workbook, array $data): void {
        $participants = $this->loadParticipantsWithRelations($data['selected_participants']);

        foreach ($participants as $participant) {
            $workbook['worksheets'][] = $this->createParticipantHistorySheet($participant, $data);
        }
    }

    protected function loadTrainingsWithRelations(array $trainingIds) {
        return Training::with([
                    'facility', 'county', 'partner', 'programs',
                    'participants.user.facility.subcounty.county',
                    'participants.user.department',
                    'participants.user.cadre',
                    'participants.assessmentResults.assessor',
                    'assessmentCategories'
                ])->whereIn('id', $trainingIds)->get();
    }

    protected function loadParticipantsWithRelations(array $participantIds) {
        return User::with([
                    'facility.subcounty.county', 'department', 'cadre',
                    'trainingParticipations.training.programs',
                    'trainingParticipations.assessmentResults'
                ])->whereIn('id', $participantIds)->get();
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
            [strtoupper('Programs/Modules: ' . $training->programs->pluck('name')->implode(', '))]
        ];

        // Add mentored by for mentorships
        if (!$isTraining && $training->mentor) {
            $worksheetData[] = [strtoupper('Mentored by: ' . $training->mentor->full_name)];
        }

        // Add coordinator information
        if ($training->organizer) {
            $coordinatorTitle = 'Coordinated by';
            $worksheetData[] = [strtoupper("{$coordinatorTitle}: {$training->organizer->full_name}")];
        }

        $worksheetData[] = [strtoupper('Location: ' . $this->getActivityLocation($training))];

        if ($training->start_date) {
            $dateRange = $training->start_date->format('M j, Y');
            if ($training->end_date) {
                $dateRange .= ' to ' . $training->end_date->format('M j, Y');
            }
            $worksheetData[] = [strtoupper('Dates: ' . $dateRange)];
        }

        // Add assessment status information
        if ($this->trainingHasActualAssessments($training)) {
            $worksheetData[] = [strtoupper('Assessment Categories: ' . $training->assessmentCategories->count())];
        } else {
            $worksheetData[] = [strtoupper('Assessment: Not configured or not used')];
        }

        $worksheetData[] = [''];

        // Add headers and data
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

    protected function determineFacilityLevel($facility): string {
        if (!$facility)
            return '';

        // Use the facility type relationship if available
        if ($facility->facilityType && $facility->facilityType->name) {
            return strtoupper($facility->facilityType->name);
        }

        // Fallback to facility name parsing if facility type is not set
        $facilityName = strtolower($facility->name ?? '');

        // Level 6 - National Referral
        if (strpos($facilityName, 'national') !== false ||
                strpos($facilityName, 'kenyatta') !== false ||
                strpos($facilityName, 'moi') !== false) {
            return 'LEVEL 6 - NATIONAL REFERRAL';
        }

        // Level 5 - County Referral  
        if (strpos($facilityName, 'county') !== false &&
                (strpos($facilityName, 'hospital') !== false || strpos($facilityName, 'referral') !== false)) {
            return 'LEVEL 5 - COUNTY REFERRAL';
        }

        // Level 4 - Sub-County Hospital
        if (strpos($facilityName, 'hospital') !== false ||
                strpos($facilityName, 'sub') !== false) {
            return 'LEVEL 4 - SUB-COUNTY HOSPITAL';
        }

        // Level 3 - Health Centre
        if (strpos($facilityName, 'health centre') !== false ||
                strpos($facilityName, 'health center') !== false) {
            return 'LEVEL 3 - HEALTH CENTRE';
        }

        // Level 2 - Dispensary
        if (strpos($facilityName, 'dispensary') !== false) {
            return 'LEVEL 2 - DISPENSARY';
        }

        // Default
        return 'LEVEL 3 - HEALTH CENTRE';
    }

    protected function buildHeaders(array $data, Training $training = null): array {
        $headers = [
            strtoupper("Attendant's Name"),
            strtoupper('County'),
            strtoupper('Subcounty'),
            strtoupper('Health MFL Code'),
            strtoupper('Facility Name'),
            strtoupper('Facility Type (Level of care)'),
            strtoupper('Department'),
            strtoupper('Cadre'),
            strtoupper('Mobile Number'),
            strtoupper('Month'),
            strtoupper('Year')
        ];

        // Add assessment category headers only if assessments are actually being used
        if ($this->shouldIncludeAssessmentCategories($data, $training)) {
            $headers = array_merge($headers, $this->buildAssessmentCategoryHeaders($training, $data));
        }

        // Add overall result header only if assessments are actually being used
        if ($this->shouldIncludeOverallResult($data, $training)) {
            $headers[] = strtoupper('Overall Result');
        }

        return $headers;
    }

    protected function shouldIncludeAssessmentCategories(array $data, ?Training $training): bool {
        // Only include if assessments are enabled AND training has assessment categories configured
        return ($data['include_assessments'] ?? false) &&
                ($data['include_individual_categories'] ?? false) &&
                $training &&
                $training->assessmentCategories->isNotEmpty() &&
                $this->trainingHasActualAssessments($training);
    }

    protected function trainingHasActualAssessments(Training $training): bool {
        // Check if training has assess_participants flag enabled
        if (property_exists($training, 'assess_participants') && !$training->assess_participants) {
            return false;
        }

        // Check if training has assessment categories configured
        if ($training->assessmentCategories->isEmpty()) {
            return false;
        }

        // Check if any participants have been actually assessed
        $hasAssessmentResults = $training->participants()
                ->whereHas('assessmentResults')
                ->exists();

        return $hasAssessmentResults;
    }

    protected function buildAssessmentCategoryHeaders(Training $training, array $data): array {
        $headers = [];
        $categoryFormat = $data['category_column_format'] ?? 'result_with_weight';
        $categories = $training->assessmentCategories->sortBy('pivot.order_sequence');

        foreach ($categories as $category) {
            // Remove percentages from category headers
            $headers[] = strtoupper($category->name);
        }

        return $headers;
    }

    protected function buildParticipantRow($participant, Training $training, array $data): array {
        $user = $participant->user;

        $row = [
            strtoupper($user->full_name ?? ''),
            strtoupper($user->facility?->subcounty?->county?->name ?? ''),
            strtoupper($user->facility?->subcounty?->name ?? ''),
            strtoupper($user->facility?->mfl_code ?? ''),
            strtoupper($user->facility?->name ?? ''),
            strtoupper($this->determineFacilityLevel($user->facility)),
            strtoupper($user->department?->name ?? ''),
            strtoupper($user->cadre?->name ?? ''),
            strtoupper($user->phone ?? ''),
            strtoupper($training->start_date?->format('F') ?? ''),
            strtoupper($training->start_date?->format('Y') ?? '')
        ];

        // Add assessment category results only if assessments are actually being used
        if ($this->shouldIncludeAssessmentCategories($data, $training)) {
            $row = array_merge($row, $this->buildAssessmentCategoryResults($participant, $training, $data));
        }

        // Add overall result only if assessments are actually being used
        if ($this->shouldIncludeOverallResult($data, $training)) {
            $row[] = strtoupper($this->calculateOverallResult($participant, $training));
        }

        return $row;
    }

    protected function buildAssessmentCategoryResults($participant, Training $training, array $data): array {
        $results = [];
        $incompleteDisplay = $data['incomplete_display'] ?? 'not_assessed';

        $categories = $training->assessmentCategories->sortBy('pivot.order_sequence');
        $assessmentResults = $participant->assessmentResults->keyBy('assessment_category_id');

        foreach ($categories as $category) {
            $result = $assessmentResults->get($category->id);

            if ($result && $result->result) {
                $results[] = strtoupper($result->result);
            } else {
                $results[] = strtoupper($this->getIncompleteText($incompleteDisplay));
            }
        }

        return $results;
    }

    protected function calculateOverallResult($participant, Training $training): string {
        // First check if this training actually uses assessments
        if (!$this->trainingHasActualAssessments($training)) {
            // For trainings without assessments, base on completion status only
            return match ($participant->completion_status) {
                'completed' => 'COMPLETED',
                'dropped' => 'DROPPED',
                'in_progress' => 'IN PROGRESS',
                default => 'REGISTERED'
            };
        }

        // For trainings with assessments, use assessment calculation
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

        // Fallback for trainings with assessment categories but no calculation method
        $hasAnyAssessmentResults = $participant->assessmentResults()
                ->whereIn('assessment_category_id', $training->assessmentCategories->pluck('id'))
                ->exists();

        if (!$hasAnyAssessmentResults) {
            return 'NOT ASSESSED';
        }

        // Check if all categories are assessed
        $totalCategories = $training->assessmentCategories->count();
        $assessedCategories = $participant->assessmentResults()
                ->whereIn('assessment_category_id', $training->assessmentCategories->pluck('id'))
                ->count();

        if ($assessedCategories < $totalCategories) {
            return 'ASSESSMENT INCOMPLETE';
        }

        // Check if all assessments passed
        $passedCategories = $participant->assessmentResults()
                ->whereIn('assessment_category_id', $training->assessmentCategories->pluck('id'))
                ->where('result', 'pass')
                ->count();

        return $passedCategories === $totalCategories ? 'PASS' : 'FAIL';
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

    protected function getFilteredParticipants(Training $training, array $data) {
        $query = $training->participants()->with([
            'user.facility.subcounty.county',
            'user.department',
            'user.cadre',
            'assessmentResults'
        ]);

        // Apply geographic filters (only for MOH trainings)
        if ($training->type === 'global_training') {
            if (!empty($data['filter_counties'])) {
                $query->whereHas('user.facility.subcounty', fn($q) => $q->whereIn('county_id', $data['filter_counties']));
            }
            if (!empty($data['filter_facilities'])) {
                $query->whereHas('user', fn($q) => $q->whereIn('facility_id', $data['filter_facilities']));
            }
        }

        // Apply participant filters
        if (!empty($data['filter_departments'])) {
            $query->whereHas('user', fn($q) => $q->whereIn('department_id', $data['filter_departments']));
        }
        if (!empty($data['filter_cadres'])) {
            $query->whereHas('user', fn($q) => $q->whereIn('cadre_id', $data['filter_cadres']));
        }

        // Apply year filters
        if (!empty($data['filter_years'])) {
            $query->whereHas('training', fn($q) => $q->whereIn(DB::raw('YEAR(start_date)'), $data['filter_years']));
        }

        return $query->get();
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
                strtoupper($training->programs->pluck('name')->implode('; ')),
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
                strtoupper($training->programs->pluck('name')->implode('; ')),
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
                $result = $this->calculateOverallResult($participant, $training);

                if (!in_array($result, ['Incomplete', 'Pending'])) {
                    $fullyAssessed++;
                    $result === 'Pass' ? $passed++ : $failed++;
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

    // CSV Export Methods
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
        $trainings = $this->loadTrainingsWithRelations($data['selected_trainings']);

        foreach ($trainings as $index => $training) {
            if ($index > 0)
                $csv .= "\n\n";

            $isTraining = $training->type === 'global_training';
            $activityType = $isTraining ? 'TRAINING' : 'MENTORSHIP';

            $csv .= strtoupper("{$activityType}: {$training->title}") . "\n";
            $csv .= strtoupper("Type: " . ($isTraining ? 'MOH Training' : 'Facility Mentorship')) . "\n";
            $csv .= strtoupper("Programs: " . $training->programs->pluck('name')->implode(', ')) . "\n";

            // Add mentored by for mentorships
            if (!$isTraining && $training->mentor) {
                $csv .= strtoupper("Mentored by: " . $training->mentor->full_name) . "\n";
            }

            // Add coordinator information
            if ($training->organizer) {
                $coordinatorTitle = 'Coordinated by';
                $csv .= strtoupper("{$coordinatorTitle}: {$training->organizer->full_name}") . "\n";
            }

            $csv .= strtoupper("Location: " . $this->getActivityLocation($training)) . "\n";

            // Add assessment status
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
        $participants = $this->loadParticipantsWithRelations($data['selected_participants']);
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
                    strtoupper($training->programs->pluck('name')->implode('; ')),
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
        $headers = ['Activity Name', 'Activity Type', 'Lead Organization', 'Programs', 'Start Date', 'End Date', 'Location', 'Total Enrolled', 'Completed', 'Completion Rate'];
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
                $training->programs->pluck('name')->implode('; '),
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

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model {
        return new Training();
    }

    protected function mutateFormDataBeforeCreate(array $data): array {
        return [];
    }
}
