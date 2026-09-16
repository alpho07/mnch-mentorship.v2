<?php

namespace App\Filament\Pages;

use App\Models\Facility;
use App\Models\ReportTemplate;
use App\Services\MonthlyReportService;
use App\Services\FacilityReportTemplateService;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Carbon\Carbon;

class ReportManagement extends Page implements HasForms {

    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationGroup = 'Report Management';
    protected static ?int $navigationSort = 10;
    protected static string $view = 'filament.pages.report-management';
    public ?array $assignTemplateData = [];
    public ?array $generateReportsData = [];

    public static function shouldRegisterNavigation(): bool {
        return auth()->check() && auth()->user()->can('page_ReportManagement');
    }

    public function mount(): void {
        $this->form->fill();
    }

    public function form(Form $form): Form {
        return $form
                        ->schema([
                            Forms\Components\Tabs::make('management_tabs')
                            ->tabs([
                                Forms\Components\Tabs\Tab::make('assign_templates')
                                ->label('Assign Templates')
                                ->schema([
                                    Forms\Components\Section::make('Assign Report Template to Facilities')
                                    ->description('Assign a report template to multiple facilities at once')
                                    ->schema([
                                        Forms\Components\Select::make('assignTemplateData.template_id')
                                        ->label('Report Template')
                                        ->options(ReportTemplate::where('is_active', true)->pluck('name', 'id'))
                                        ->required()
                                        ->searchable(),
                                        Forms\Components\Select::make('assignTemplateData.facility_ids')
                                        ->label('Facilities')
                                        ->options(Facility::pluck('name', 'id'))/* function () {
                                          $user = auth()->user();
                                          return $user->isAboveSite()
                                          ? Facility::pluck('name', 'id')
                                          : Facility::whereIn('id', $user->scopedFacilityIds())->pluck('name', 'id');
                                          }) */
                                        ->multiple()
                                        ->required()
                                        ->searchable(),
                                        Forms\Components\DatePicker::make('assignTemplateData.start_date')
                                        ->label('Start Date')
                                        ->required()
                                        ->default(now()->startOfMonth()),
                                        Forms\Components\DatePicker::make('assignTemplateData.end_date')
                                        ->label('End Date (Optional)')
                                        ->helperText('Leave empty for ongoing assignment'),
                                    ])
                                    ->columns(2),
                                ]),
                                Forms\Components\Tabs\Tab::make('generate_reports')
                                ->label('Generate Reports')
                                ->schema([
                                    Forms\Components\Section::make('Generate Monthly Reports')
                                    ->description('Bulk generate monthly reports for selected facilities and templates')
                                    ->schema([
                                        Forms\Components\Select::make('generateReportsData.template_id')
                                        ->label('Report Template')
                                        ->options(ReportTemplate::where('is_active', true)->pluck('name', 'id'))
                                        ->required()
                                        ->searchable(),
                                        Forms\Components\Select::make('generateReportsData.facility_ids')
                                        ->label('Facilities')
                                        ->options(Facility::pluck('name', 'id'))/* function () {
                                          $user = auth()->user();
                                          return $user->isAboveSite()
                                          ? Facility::pluck('name', 'id')
                                          : Facility::whereIn('id', $user->scopedFacilityIds())->pluck('name', 'id');
                                          }) */
                                        ->multiple()
                                        ->required()
                                        ->searchable(),
                                        Forms\Components\DatePicker::make('generateReportsData.reporting_period')
                                        ->label('Reporting Period')
                                        ->displayFormat('F Y')
                                        ->format('Y-m-01')
                                        ->required()
                                        ->default(now()->startOfMonth())
                                        ->helperText('Select the month for which to generate reports'),
                                    ])
                                    ->columns(2),
                                ]),
                            ])
                            ->columnSpanFull(),
        ]);
    }

    public function assignTemplate(): void {
        $data = $this->form->getState();
        $assignData = $data['assignTemplateData'] ?? [];

        if (empty($assignData['template_id']) || empty($assignData['facility_ids'])) {
            Notification::make()
                    ->title('Validation Error')
                    ->body('Please fill in all required fields.')
                    ->danger()
                    ->send();
            return;
        }

        try {
            $service = app(FacilityReportTemplateService::class);
            $template = ReportTemplate::findOrFail($assignData['template_id']);
            $startDate = Carbon::parse($assignData['start_date']);
            $endDate = !empty($assignData['end_date']) ? Carbon::parse($assignData['end_date']) : null;

            $service->assignTemplateToFacilities(
                    $assignData['facility_ids'],
                    $template,
                    $startDate,
                    $endDate
            );

            Notification::make()
                    ->title('Template Assigned Successfully')
                    ->body("Template '{$template->name}' has been assigned to " . count($assignData['facility_ids']) . " facilities.")
                    ->success()
                    ->send();

            // Reset the form
            $this->form->fill();
        } catch (\Exception $e) {
            Notification::make()
                    ->title('Assignment Failed')
                    ->body($e->getMessage())
                    ->danger()
                    ->send();
        }
    }

    public function generateReports(): void {
        $data = $this->form->getState();
        $generateData = $data['generateReportsData'] ?? [];

        if (empty($generateData['template_id']) || empty($generateData['facility_ids'])) {
            Notification::make()
                    ->title('Validation Error')
                    ->body('Please fill in all required fields.')
                    ->danger()
                    ->send();
            return;
        }

        try {
            $service = app(MonthlyReportService::class);
            $template = ReportTemplate::findOrFail($generateData['template_id']);
            $reportingPeriod = Carbon::parse($generateData['reporting_period']);

            $results = $service->bulkCreateReports(
                    $generateData['facility_ids'],
                    $generateData['template_id'],
                    $reportingPeriod,
                    auth()->id()
            );

            $successCount = count($results['success'] ?? []);
            $errorCount = count($results['errors'] ?? []);

            if ($successCount > 0) {
                Notification::make()
                        ->title('Reports Generated')
                        ->body("Successfully generated {$successCount} reports for {$template->name}")
                        ->success()
                        ->send();
            }

            if ($errorCount > 0) {
                $errorMessages = collect($results['errors'])
                        ->pluck('error')
                        ->unique()
                        ->join(', ');

                Notification::make()
                        ->title('Some Reports Failed')
                        ->body("Failed to generate {$errorCount} reports. Errors: {$errorMessages}")
                        ->warning()
                        ->send();
            }

            // Reset the form
            $this->form->fill();
        } catch (\Exception $e) {
            Notification::make()
                    ->title('Generation Failed')
                    ->body($e->getMessage())
                    ->danger()
                    ->send();
        }
    }

    protected function getFormActions(): array {
        return [
                    Action::make('assignTemplate')
                    ->label('Assign Template')
                    ->icon('heroicon-o-link')
                    ->color('primary')
                    ->action('assignTemplate'),
                    Action::make('generateReports')
                    ->label('Generate Reports')
                    ->icon('heroicon-o-document-plus')
                    ->color('success')
                    ->action('generateReports')
                    ->requiresConfirmation()
                    ->modalHeading('Generate Monthly Reports')
                    ->modalDescription('This will create new monthly reports for the selected facilities and period. Existing reports will be skipped.')
                    ->modalSubmitActionLabel('Generate'),
        ];
    }

    public function getTitle(): string {
        return 'Report Management';
    }

    public function getSubheading(): ?string {
        return 'Manage report templates and generate monthly reports';
    }
}
