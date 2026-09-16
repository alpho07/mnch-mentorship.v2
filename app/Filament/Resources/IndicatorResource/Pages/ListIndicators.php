<?php

namespace App\Filament\Resources\IndicatorResource\Pages;

use App\Filament\Resources\IndicatorResource;
use App\Models\Indicator;
use App\Models\ReportTemplate;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Actions\BulkAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ListIndicators extends ListRecords
{
    protected static string $resource = IndicatorResource::class;

    // Cache for tab badge counts
    protected array $indicatorTabCounts = [];

    public function mount(): void
    {
        parent::mount();

        // Batch query for badge counts to avoid N+1 queries
        $this->indicatorTabCounts = [
            'all' => Indicator::count(),
            'active' => Indicator::where('is_active', true)->count(),
            'inactive' => Indicator::where('is_active', false)->count(),
            'percentage' => Indicator::where('calculation_type', 'percentage')->count(),
            'count' => Indicator::where('calculation_type', 'count')->count(),
            'rate' => Indicator::where('calculation_type', 'rate')->count(),
            'ratio' => Indicator::where('calculation_type', 'ratio')->count(),
            'no_target' => Indicator::whereNull('target_value')->count(),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('bulk_assign')
                ->label('Bulk Assign to Template')
                ->icon('heroicon-o-link')
                ->color('info')
                ->form([
                    Forms\Components\Select::make('template_id')
                        ->label('Report Template')
                        ->options(fn() => ReportTemplate::where('is_active', true)->pluck('name', 'id'))
                        ->required()
                        ->searchable(),

                    Forms\Components\Select::make('indicator_ids')
                        ->label('Indicators')
                        ->options(fn() => Indicator::where('is_active', true)->pluck('name', 'id'))
                        ->multiple()
                        ->required()
                        ->searchable()
                        ->helperText('Select indicators to assign to the template'),

                    Forms\Components\TextInput::make('start_order')
                        ->label('Starting Sort Order')
                        ->numeric()
                        ->default(1)
                        ->helperText('The sort order for the first indicator (others will follow sequentially)'),
                ])
                ->action(function (array $data) {
                    DB::transaction(function () use ($data) {
                        $template = ReportTemplate::findOrFail($data['template_id']);
                        $indicators = Indicator::whereIn('id', $data['indicator_ids'])->get();
                        $startOrder = $data['start_order'];

                        $syncData = [];
                        foreach ($indicators as $index => $indicator) {
                            $syncData[$indicator->id] = [
                                'sort_order' => $startOrder + $index,
                                'is_required' => true,
                            ];
                        }

                        // Use syncWithoutDetaching to avoid race conditions and duplicates
                        $beforeCount = $template->indicators()->count();
                        $template->indicators()->syncWithoutDetaching($syncData);
                        $afterCount = $template->indicators()->count();
                        $attached = $afterCount - $beforeCount;
                        $skipped = count($syncData) - $attached;

                        $message = "Assigned {$attached} indicators to {$template->name}.";
                        if ($skipped > 0) {
                            $message .= " Skipped {$skipped} already assigned indicators.";
                        }

                        Notification::make()
                            ->title('Bulk Assignment Complete')
                            ->body($message)
                            ->success()
                            ->send();
                    });
                })
                ->requiresConfirmation()
                ->modalHeading('Bulk Assign Indicators to Template')
                ->modalDescription('Assign multiple indicators to a report template at once.')
                ->modalSubmitActionLabel('Assign Indicators'),

            Actions\Action::make('import_indicators')
                ->label('Import Indicators')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('warning')
                ->form([
                    Forms\Components\Textarea::make('indicators_data')
                        ->label('Indicators JSON Data')
                        ->rows(10)
                        ->helperText('Paste JSON array of indicator objects with fields: name, code, description, numerator_description, denominator_description, calculation_type, source_document, target_value')
                        ->placeholder('[
    {
        "name": "Example Indicator",
        "code": "EXAMPLE_IND",
        "description": "Description here",
        "numerator_description": "Numerator description",
        "denominator_description": "Denominator description",
        "calculation_type": "percentage",
        "source_document": "Source document",
        "target_value": 80.0
    }
]'),
                ])
                ->action(function (array $data) {
                    try {
                        $indicators = json_decode($data['indicators_data'], true);

                        if (!is_array($indicators)) {
                            throw new \Exception('Invalid JSON format');
                        }

                        $created = 0;
                        $errors = [];

                        foreach ($indicators as $indicatorData) {
                            // Use Laravel validator for robust validation
                            $validator = validator($indicatorData, [
                                'name' => 'required|string|max:255',
                                'code' => 'required|string|max:255|unique:indicators,code',
                                'numerator_description' => 'required|string',
                                'calculation_type' => 'required|string|in:percentage,count,rate,ratio',
                                'description' => 'nullable|string',
                                'denominator_description' => 'nullable|string',
                                'source_document' => 'nullable|string',
                                'target_value' => 'nullable|numeric',
                                'is_active' => 'nullable|boolean',
                            ]);

                            if ($validator->fails()) {
                                $errors[] = "Error with indicator '" . (isset($indicatorData['name']) ? $indicatorData['name'] : 'Unknown') . "': " . implode('; ', $validator->errors()->all());
                                continue;
                            }

                            Indicator::create([
                                'name' => $indicatorData['name'],
                                'code' => $indicatorData['code'],
                                'description' => $indicatorData['description'] ?? null,
                                'numerator_description' => $indicatorData['numerator_description'],
                                'denominator_description' => $indicatorData['denominator_description'] ?? null,
                                'calculation_type' => $indicatorData['calculation_type'],
                                'source_document' => $indicatorData['source_document'] ?? null,
                                'target_value' => $indicatorData['target_value'] ?? null,
                                'is_active' => $indicatorData['is_active'] ?? true,
                            ]);
                            $created++;
                        }

                        $message = "Successfully imported {$created} indicators.";
                        if (!empty($errors)) {
                            $message .= " Errors: " . implode(', ', array_slice($errors, 0, 3));
                            if (count($errors) > 3) {
                                $message .= " and " . (count($errors) - 3) . " more...";
                            }
                        }

                        Notification::make()
                            ->title('Import Complete')
                            ->body($message)
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Import Failed')
                            ->body('Error parsing JSON: ' . $e->getMessage())
                            ->danger()
                            ->send();
                    }
                })
                ->modalHeading('Import Indicators')
                ->modalDescription('Import multiple indicators from JSON data.')
                ->modalSubmitActionLabel('Import'),
        ];
    }

    public function getTabs(): array
    {
        // Use cached counts for badge numbers
        return [
            'all' => Tab::make('All Indicators')
                ->badge($this->indicatorTabCounts['all'] ?? 0),

            'active' => Tab::make('Active')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('is_active', true))
                ->badge($this->indicatorTabCounts['active'] ?? 0),

            'inactive' => Tab::make('Inactive')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('is_active', false))
                ->badge($this->indicatorTabCounts['inactive'] ?? 0),

            'percentage' => Tab::make('Percentage')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('calculation_type', 'percentage'))
                ->badge($this->indicatorTabCounts['percentage'] ?? 0),

            'count' => Tab::make('Count')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('calculation_type', 'count'))
                ->badge($this->indicatorTabCounts['count'] ?? 0),

            'rate' => Tab::make('Rate')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('calculation_type', 'rate'))
                ->badge($this->indicatorTabCounts['rate'] ?? 0),

            'ratio' => Tab::make('Ratio')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('calculation_type', 'ratio'))
                ->badge($this->indicatorTabCounts['ratio'] ?? 0),

            'no_target' => Tab::make('No Target Set')
                ->modifyQueryUsing(fn(Builder $query) => $query->whereNull('target_value'))
                ->badge($this->indicatorTabCounts['no_target'] ?? 0)
                ->icon('heroicon-o-exclamation-triangle'),
        ];
    }

    protected function getTableBulkActions(): array
    {
        return [
            BulkAction::make('activate')
                ->label('Activate')
                ->icon('heroicon-o-check')
                ->color('success')
                ->action(function (Collection $records) {
                    $records->each->update(['is_active' => true]);

                    Notification::make()
                        ->title('Indicators Activated')
                        ->body('Selected indicators have been activated.')
                        ->success()
                        ->send();
                })
                ->deselectRecordsAfterCompletion(),

            BulkAction::make('deactivate')
                ->label('Deactivate')
                ->icon('heroicon-o-x-mark')
                ->color('danger')
                ->action(function (Collection $records) {
                    $records->each->update(['is_active' => false]);

                    Notification::make()
                        ->title('Indicators Deactivated')
                        ->body('Selected indicators have been deactivated.')
                        ->success()
                        ->send();
                })
                ->requiresConfirmation()
                ->deselectRecordsAfterCompletion(),

            BulkAction::make('assign_to_template')
                ->label('Assign to Template')
                ->icon('heroicon-o-link')
                ->color('info')
                ->form([
                    Forms\Components\Select::make('template_id')
                        ->label('Report Template')
                        ->options(fn() => ReportTemplate::where('is_active', true)->pluck('name', 'id'))
                        ->required()
                        ->searchable(),

                    Forms\Components\TextInput::make('start_order')
                        ->label('Starting Sort Order')
                        ->numeric()
                        ->default(1)
                        ->helperText('The sort order for the first indicator (others will follow sequentially)'),

                    Forms\Components\Toggle::make('is_required')
                        ->label('Mark as Required')
                        ->default(true),
                ])
                ->action(function (Collection $records, array $data) {
                    DB::transaction(function () use ($records, $data) {
                        $template = ReportTemplate::findOrFail($data['template_id']);
                        $startOrder = $data['start_order'];
                        $isRequired = $data['is_required'];

                        $syncData = [];
                        foreach ($records as $index => $indicator) {
                            $syncData[$indicator->id] = [
                                'sort_order' => $startOrder + $index,
                                'is_required' => $isRequired,
                            ];
                        }

                        $beforeCount = $template->indicators()->count();
                        $template->indicators()->syncWithoutDetaching($syncData);
                        $afterCount = $template->indicators()->count();
                        $attached = $afterCount - $beforeCount;
                        $skipped = count($syncData) - $attached;

                        $message = "Assigned {$attached} indicators to {$template->name}.";
                        if ($skipped > 0) {
                            $message .= " Skipped {$skipped} already assigned indicators.";
                        }

                        Notification::make()
                            ->title('Bulk Assignment Complete')
                            ->body($message)
                            ->success()
                            ->send();
                    });
                })
                ->deselectRecordsAfterCompletion(),

            BulkAction::make('set_target_value')
                ->label('Set Target Value')
                ->icon('heroicon-o-target')
                ->color('warning')
                ->form([
                    Forms\Components\TextInput::make('target_value')
                        ->label('Target Value')
                        ->numeric()
                        ->required()
                        ->helperText('Set the same target value for all selected indicators'),
                ])
                ->action(function (Collection $records, array $data) {
                    $records->each->update(['target_value' => $data['target_value']]);

                    Notification::make()
                        ->title('Target Values Updated')
                        ->body("Set target value to {$data['target_value']} for " . $records->count() . " indicators.")
                        ->success()
                        ->send();
                })
                ->deselectRecordsAfterCompletion(),

            BulkAction::make('export')
                ->label('Export Selected')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->action(function (Collection $records) {
                    $data = $records->map(function ($indicator) {
                        return [
                            'name' => $indicator->name,
                            'code' => $indicator->code,
                            'description' => $indicator->description,
                            'numerator_description' => $indicator->numerator_description,
                            'denominator_description' => $indicator->denominator_description,
                            'calculation_type' => $indicator->calculation_type,
                            'source_document' => $indicator->source_document,
                            'target_value' => $indicator->target_value,
                            'is_active' => $indicator->is_active,
                        ];
                    })->values()->all();

                    // Use response()->streamDownload for direct file download
                    return response()->streamDownload(function () use ($data) {
                        echo json_encode($data, JSON_PRETTY_PRINT);
                    }, 'indicators-export.json');
                })
                ->deselectRecordsAfterCompletion(),
        ];
    }

    protected function getTableEmptyStateHeading(): ?string
    {
        return 'No indicators found';
    }

    protected function getTableEmptyStateDescription(): ?string
    {
        return 'Create your first indicator to start building report templates.';
    }

    protected function getTableEmptyStateActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Create First Indicator'),
        ];
    }

    public function getTitle(): string
    {
        return 'Healthcare Indicators';
    }

    public function getSubheading(): ?string
    {
        $activeCount = $this->indicatorTabCounts['active'] ?? 0;
        $totalCount = $this->indicatorTabCounts['all'] ?? 0;

        return "Manage your healthcare quality indicators ({$activeCount} active of {$totalCount} total)";
    }
}
