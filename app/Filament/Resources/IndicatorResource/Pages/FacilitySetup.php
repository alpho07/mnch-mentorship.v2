<?php

namespace App\Filament\Resources\IndicatorResource\Pages;

use App\Models\Facility;
use App\Models\Indicators\FacilityIndicatorAssignment;
use App\Models\Indicators\IndicatorReportType;
use App\Services\Indicators\FacilityAssignmentService;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class FacilitySetup extends Page implements HasForms
{
    use InteractsWithForms;

    // ──────────────────────────────────────────────────────────────────────────
    // Page configuration
    // ──────────────────────────────────────────────────────────────────────────

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationLabel = 'Facility Setup';

    protected static ?string $navigationGroup = 'Indicator Reporting';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'indicators/facility-setup';

    protected static string $view = 'filament.pages.indicators.facility-setup';

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->can('view_any_indicator');
    }

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->can('view_any_indicator');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Livewire state
    // ──────────────────────────────────────────────────────────────────────────

    public ?array $data = [];

    // Derived state (set in mount, refreshed after actions)
    public ?int $resolvedFacilityId = null;

    public bool $isSuperAdmin = false;

    public bool $facilityLocked = false;

    public bool $canChangeFacility = false;

    public bool $isConfigured = false;

    public ?array $assignmentData = null;   // serialized FacilityIndicatorAssignment

    public ?array $facilityData = null;   // serialized Facility

    // ──────────────────────────────────────────────────────────────────────────
    // Boot
    // ──────────────────────────────────────────────────────────────────────────

    public function mount(): void
    {
        $this->isSuperAdmin = auth()->user()->hasRole('super_admin');
        $this->resolveState();
        $this->fillForm();
    }

    private function resolveState(): void
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        $service = app(FacilityAssignmentService::class);

        // Determine the active facility ID
        $this->resolvedFacilityId = $user->facility_id ?? null;

        $status = $service->getStatus($user, $this->resolvedFacilityId);

        $this->facilityLocked = $status['locked'];
        $this->isConfigured = $status['configured'];
        $this->canChangeFacility = $service->canChangeFacility($user, $status['assignment']);
        $this->assignmentData = $status['assignment'] ? $status['assignment']->toArray() : null;
        $this->facilityData = $status['facility'] ? [
            'id' => $status['facility']->id,
            'name' => $status['facility']->name,
            'mfl_code' => $status['facility']->mfl_code,
            'subcounty' => $status['facility']?->subcounty?->name,
            'county' => $status['facility']?->subcounty?->county?->name,
            'level' => $status['facility']?->facilityLevel?->name,
        ] : null;
    }

    private function fillForm(): void
    {
        $this->form->fill([
            'facility_id' => $this->resolvedFacilityId,
            'enabled_report_types' => $this->assignmentData['enabled_report_types'] ?? [],
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Form
    // ──────────────────────────────────────────────────────────────────────────

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                // ── Facility selection ──────────────────────────────────────
                Section::make('Reporting Facility')
                    ->description('The health facility this account will submit indicator reports for.')
                    ->icon('heroicon-o-building-office-2')
                    ->schema([
                        // Super-admin gets a searchable dropdown; everyone else sees a read-only display
                        Select::make('facility_id')
                            ->label('Facility')
                            ->options(
                                Facility::with('subcounty.county')
                                    ->get()
                                    ->mapWithKeys(fn ($f) => [
                                        $f->id => $f->name
                                        .($f->mfl_code ? " ({$f->mfl_code})" : '')
                                        .($f->subcounty?->county ? " — {$f->subcounty->county->name}" : ''),
                                    ])
                            )
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state) {
                                $this->resolvedFacilityId = $state ? (int) $state : null;
                            })
                            ->disabled(fn () => ! $this->canChangeFacility || $this->facilityLocked)
                            ->helperText(fn () => match (true) {
                                ! $this->canChangeFacility => 'Your facility is pre-assigned to your account and cannot be changed.',
                                $this->facilityLocked => 'Facility is locked. Contact a system administrator to unlock.',
                                default => 'Select the facility you will submit reports for.',
                            }),
                    ]),
                // ── Report types ────────────────────────────────────────────
                Section::make('Report Types')
                    ->description('Select which indicator report types this facility will report on.')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->schema([
                        CheckboxList::make('enabled_report_types')
                            ->label('Enabled Report Types')
                            ->options(
                                IndicatorReportType::active()
                                    ->get()
                                    ->mapWithKeys(fn ($rt) => [$rt->id => $rt->name])
                            )
                            ->descriptions(
                                IndicatorReportType::active()
                                    ->get()
                                    ->mapWithKeys(fn ($rt) => [$rt->id => $rt->description])
                            )
                            ->columns(2)
                            ->disabled(fn () => $this->facilityLocked && ! $this->isSuperAdmin)
                            ->required()
                            ->minItems(1)
                            ->helperText(
                                $this->facilityLocked && ! $this->isSuperAdmin ? 'Report types are locked. Contact a system administrator to modify.' : 'At least one report type must be selected.'
                            ),
                    ]),
            ])
            ->statePath('data');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Header actions
    // ──────────────────────────────────────────────────────────────────────────

    protected function getHeaderActions(): array
    {
        $actions = [];

        // Save draft (unlocked or super_admin)
        if (! $this->facilityLocked || $this->isSuperAdmin) {
            $actions[] = Action::make('save')
                ->label('Save')
                ->icon('heroicon-o-check')
                ->color('gray')
                ->action('saveAssignment');
        }

        // Confirm & Lock
        if (! $this->facilityLocked || $this->isSuperAdmin) {
            $actions[] = Action::make('lock')
                ->label('Confirm & Lock')
                ->icon('heroicon-o-lock-closed')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Confirm and Lock Facility Setup')
                ->modalDescription(
                    'Once locked, this facility assignment cannot be changed by regular users. '
                    .'Only a system administrator can unlock it. Are you sure?'
                )
                ->modalSubmitActionLabel('Yes, Confirm & Lock')
                ->action('lockAssignment');
        }

        // Unlock (super_admin only, when already locked)
        if ($this->facilityLocked && $this->isSuperAdmin) {
            $actions[] = Action::make('unlock')
                ->label('Unlock Assignment')
                ->icon('heroicon-o-lock-open')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Unlock Facility Assignment')
                ->modalDescription(
                    'This will allow the facility assignment to be modified. '
                    .'Remember to re-lock once changes are complete.'
                )
                ->action('unlockAssignment');
        }

        return $actions;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Action handlers
    // ──────────────────────────────────────────────────────────────────────────

    public function saveAssignment(): void
    {
        $data = $this->form->getState();

        $facilityId = (int) $data['facility_id'];

        if (! $facilityId) {
            Notification::make()
                ->title('No facility selected')
                ->body('Please select a facility before saving.')
                ->danger()
                ->send();

            return;
        }

        try {
            app(FacilityAssignmentService::class)->save(
                $facilityId,
                array_map('intval', $data['enabled_report_types'] ?? []),
                auth()->user()
            );

            $this->resolvedFacilityId = $facilityId;
            $this->resolveState();

            Notification::make()
                ->title('Setup saved')
                ->body('Facility reporting configuration has been saved successfully.')
                ->success()
                ->send();
        } catch (\RuntimeException $e) {
            Notification::make()
                ->title('Could not save')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function lockAssignment(): void
    {
        $data = $this->form->getState();

        $facilityId = (int) $data['facility_id'];

        if (! $facilityId) {
            Notification::make()->title('No facility selected')->danger()->send();

            return;
        }

        if (empty($data['enabled_report_types'])) {
            Notification::make()
                ->title('No report types selected')
                ->body('Please select at least one report type before locking.')
                ->danger()
                ->send();

            return;
        }

        try {
            app(FacilityAssignmentService::class)->lock(
                $facilityId,
                array_map('intval', $data['enabled_report_types']),
                auth()->user()
            );

            $this->resolvedFacilityId = $facilityId;
            $this->resolveState();

            Notification::make()
                ->title('Facility setup confirmed & locked')
                ->body('This facility is now configured for indicator reporting. You can proceed to submit reports.')
                ->success()
                ->persistent()
                ->send();
        } catch (\RuntimeException $e) {
            Notification::make()
                ->title('Could not lock')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function unlockAssignment(): void
    {
        if (! $this->resolvedFacilityId) {
            return;
        }

        try {
            app(FacilityAssignmentService::class)->unlock(
                $this->resolvedFacilityId,
                auth()->user()
            );

            $this->resolveState();

            Notification::make()
                ->title('Assignment unlocked')
                ->body('The facility assignment can now be modified.')
                ->warning()
                ->send();
        } catch (\RuntimeException $e) {
            Notification::make()
                ->title('Could not unlock')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // View data
    // ──────────────────────────────────────────────────────────────────────────

    public function getViewData(): array
    {
        $enabledTypes = [];
        if ($this->assignmentData && ! empty($this->assignmentData['enabled_report_types'])) {
            $enabledTypes = IndicatorReportType::whereIn('id', $this->assignmentData['enabled_report_types'])
                ->get()
                ->toArray();
        }

        return [
            'isSuperAdmin' => $this->isSuperAdmin,
            'facilityLocked' => $this->facilityLocked,
            'canChangeFacility' => $this->canChangeFacility,
            'isConfigured' => $this->isConfigured,
            'facilityData' => $this->facilityData,
            'assignmentData' => $this->assignmentData,
            'enabledTypes' => $enabledTypes,
        ];
    }
}
