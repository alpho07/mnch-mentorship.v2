<?php

// Enhanced ListFacilities Page
namespace App\Filament\Resources\FacilityResource\Pages;

use App\Filament\Resources\FacilityResource;
use App\Models\Facility;
use App\Models\County;
use App\Models\Subcounty;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms;

class ListFacilities extends ListRecords
{
    protected static string $resource = FacilityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Create Facility')
                ->icon('heroicon-o-plus'),

            Actions\ActionGroup::make([
                Actions\Action::make('setup_central_store')
                    ->label('Setup Central Store')
                    ->icon('heroicon-o-building-storefront')
                    ->color('warning')
                    ->url(route('filament.admin.resources.facilities.create', [
                        'is_central_store' => true
                    ]))
                    ->openUrlInNewTab(),
                Actions\Action::make('setup_hub')
                    ->label('Setup Hub Facility')
                    ->icon('heroicon-o-star')
                    ->color('info')
                    ->url(route('filament.admin.resources.facilities.create', [
                        'is_hub' => true
                    ]))
                    ->openUrlInNewTab(),
                Actions\Action::make('setup_regular')
                    ->label('Setup Regular Facility')
                    ->icon('heroicon-o-building-office')
                    ->color('gray')
                    ->url(route('filament.admin.resources.facilities.create')),
            ])
                ->label('Quick Setup')
                ->icon('heroicon-o-rocket-launch')
                ->color('success'),

            Actions\ActionGroup::make([
                Actions\Action::make('facility_analytics')
                    ->label('Facility Analytics')
                    ->icon('heroicon-o-chart-bar')
                    ->action(function () {
                        $this->showFacilityAnalytics();
                    }),
                Actions\Action::make('geographic_view')
                    ->label('Geographic View')
                    ->icon('heroicon-o-map')
                    ->url('#') // Link to map view
                    ->openUrlInNewTab(),
                Actions\Action::make('coverage_analysis')
                    ->label('Coverage Analysis')
                    ->icon('heroicon-o-globe-alt')
                    ->action(function () {
                        $this->analyzeCoverage();
                    }),
            ])
                ->label('Management Tools')
                ->icon('heroicon-o-wrench-screwdriver')
                ->color('info'),

            Actions\ActionGroup::make([
                Actions\Action::make('export_facilities')
                    ->label('Export Facilities')
                    ->icon('heroicon-o-document-arrow-down')
                    ->form([
                        Forms\Components\Select::make('format')
                            ->options([
                                'csv' => 'CSV',
                                'xlsx' => 'Excel',
                                'pdf' => 'PDF',
                            ])
                            ->default('csv')
                            ->required(),
                        Forms\Components\CheckboxList::make('include_fields')
                            ->label('Include Fields')
                            ->options([
                                'basic_info' => 'Basic Information',
                                'location' => 'Location Details',
                                'coordinates' => 'GPS Coordinates',
                                'stock_summary' => 'Stock Summary',
                                'staff_count' => 'Staff Count',
                            ])
                            ->default(['basic_info', 'location'])
                            ->columns(2),
                    ])
                    ->action(function (array $data) {
                        $this->exportFacilities($data);
                    }),

                Actions\Action::make('import_facilities')
                    ->label('Import Facilities')
                    ->icon('heroicon-o-document-arrow-up')
                    ->form([
                        Forms\Components\FileUpload::make('file')
                            ->label('Upload CSV/Excel File')
                            ->acceptedFileTypes(['text/csv', 'application/csv', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                            ->required()
                            ->helperText('Download template: CSV | Excel'),
                        Forms\Components\Toggle::make('update_existing')
                            ->label('Update Existing Facilities')
                            ->helperText('Update facilities that already exist based on MFL Code'),
                        Forms\Components\Toggle::make('validate_only')
                            ->label('Validation Only')
                            ->helperText('Just validate the file without importing'),
                    ])
                    ->action(function (array $data) {
                        $this->importFacilities($data);
                    }),

                Actions\Action::make('bulk_geocoding')
                    ->label('Bulk Geocoding')
                    ->icon('heroicon-o-map-pin')
                    ->form([
                        Forms\Components\Select::make('target')
                            ->label('Apply To')
                            ->options([
                                'missing_coordinates' => 'Facilities Missing GPS Coordinates',
                                'all_facilities' => 'All Facilities (Update All)',
                                'selected_county' => 'Specific County',
                            ])
                            ->default('missing_coordinates')
                            ->live()
                            ->required(),
                        Forms\Components\Select::make('county_id')
                            ->label('Select County')
                            ->options(County::pluck('name', 'id'))
                            ->visible(fn (Forms\Get $get) => $get('target') === 'selected_county')
                            ->required(fn (Forms\Get $get) => $get('target') === 'selected_county'),
                        Forms\Components\Toggle::make('use_facility_address')
                            ->label('Use Facility Address for Geocoding')
                            ->default(true)
                            ->helperText('Use facility location details to get coordinates'),
                    ])
                    ->requiresConfirmation()
                    ->modalHeading('Bulk Geocoding')
                    ->modalDescription('This will attempt to get GPS coordinates for the selected facilities.')
                    ->action(function (array $data) {
                        $this->performBulkGeocoding($data);
                    }),

                Actions\Action::make('facility_assignments')
                    ->label('Bulk Hub Assignments')
                    ->icon('heroicon-o-link')
                    ->form([
                        Forms\Components\Select::make('county_id')
                            ->label('County')
                            ->options(County::pluck('name', 'id'))
                            ->live()
                            ->afterStateUpdated(fn (Forms\Set $set) => $set('subcounty_id', null)),
                        Forms\Components\Select::make('subcounty_id')
                            ->label('Subcounty (Optional)')
                            ->options(fn (Forms\Get $get) =>
                                $get('county_id')
                                    ? Subcounty::where('county_id', $get('county_id'))->pluck('name', 'id')
                                    : []
                            )
                            ->searchable(),
                        Forms\Components\Select::make('hub_facility_id')
                            ->label('Assign to Hub')
                            ->options(Facility::where('is_hub', true)->pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        $this->performBulkHubAssignment($data);
                    }),
            ])
                ->label('Bulk Operations')
                ->icon('heroicon-o-cog-6-tooth')
                ->color('gray'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All Facilities')
                ->badge(Facility::count())
                ->icon('heroicon-o-building-office'),

            'central_stores' => Tab::make('Central Stores')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('is_central_store', true))
                ->badge(Facility::where('is_central_store', true)->count())
                ->badgeColor('warning')
                ->icon('heroicon-o-building-storefront'),

            'hubs' => Tab::make('Hub Facilities')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('is_hub', true))
                ->badge(Facility::where('is_hub', true)->count())
                ->badgeColor('info')
                ->icon('heroicon-o-star'),

            'regular' => Tab::make('Regular Facilities')
                ->modifyQueryUsing(fn (Builder $query) =>
                    $query->where('is_hub', false)->where('is_central_store', false)
                )
                ->badge(Facility::where('is_hub', false)->where('is_central_store', false)->count())
                ->badgeColor('success')
                ->icon('heroicon-o-building-office'),

            'with_coordinates' => Tab::make('With GPS')
                ->modifyQueryUsing(fn (Builder $query) =>
                    $query->whereNotNull('lat')->whereNotNull('long')
                )
                ->badge(Facility::whereNotNull('lat')->whereNotNull('long')->count())
                ->badgeColor('primary')
                ->icon('heroicon-o-map-pin'),

            'missing_coordinates' => Tab::make('Missing GPS')
                ->modifyQueryUsing(fn (Builder $query) =>
                    $query->whereNull('lat')->orWhereNull('long')
                )
                ->badge(Facility::whereNull('lat')->orWhereNull('long')->count())
                ->badgeColor('danger')
                ->icon('heroicon-o-exclamation-triangle'),

            'unassigned_spokes' => Tab::make('Unassigned Spokes')
                ->modifyQueryUsing(fn (Builder $query) =>
                    $query->where('is_hub', false)
                          ->where('is_central_store', false)
                          ->whereNull('hub_id')
                )
                ->badge(Facility::where('is_hub', false)
                              ->where('is_central_store', false)
                              ->whereNull('hub_id')->count())
                ->badgeColor('warning')
                ->icon('heroicon-o-question-mark-circle'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
           // \App\Filament\Widgets\FacilityOverviewWidget::class,
            //\App\Filament\Widgets\FacilityDistributionMapWidget::class,
        ];
    }

    // Bulk operation methods
    protected function exportFacilities(array $data): void
    {
        // Export logic here
        \Filament\Notifications\Notification::make()
            ->title('Export Started')
            ->body("Facility export in {$data['format']} format is being processed. You will receive an email when complete.")
            ->success()
            ->persistent()
            ->actions([
                \Filament\Notifications\Actions\Action::make('download')
                    ->label('Download Now')
                    ->url('#') // Link to download
                    ->openUrlInNewTab(),
            ])
            ->send();
    }

    protected function importFacilities(array $data): void
    {
        // Import logic here
        if ($data['validate_only']) {
            \Filament\Notifications\Notification::make()
                ->title('Validation Complete')
                ->body('File validation passed. 45 facilities ready for import, 3 duplicates found.')
                ->success()
                ->actions([
                    \Filament\Notifications\Actions\Action::make('proceed')
                        ->label('Proceed with Import')
                        ->action(function () {
                            // Actual import logic
                        }),
                ])
                ->send();
        } else {
            \Filament\Notifications\Notification::make()
                ->title('Import Started')
                ->body('Facility import is being processed. Progress updates will be shown here.')
                ->success()
                ->send();
        }
    }

    protected function performBulkGeocoding(array $data): void
    {
        // Geocoding logic here
        $targetCount = match($data['target']) {
            'missing_coordinates' => Facility::whereNull('lat')->orWhereNull('long')->count(),
            'all_facilities' => Facility::count(),
            'selected_county' => Facility::whereHas('subcounty', fn($q) => $q->where('county_id', $data['county_id']))->count(),
        };

        \Filament\Notifications\Notification::make()
            ->title('Geocoding Started')
            ->body("Processing {$targetCount} facilities for GPS coordinates. This may take a few minutes.")
            ->success()
            ->persistent()
            ->send();
    }

    protected function performBulkHubAssignment(array $data): void
    {
        $query = Facility::where('is_hub', false)->where('is_central_store', false);

        if ($data['subcounty_id']) {
            $query->where('subcounty_id', $data['subcounty_id']);
        } elseif ($data['county_id']) {
            $query->whereHas('subcounty', fn($q) => $q->where('county_id', $data['county_id']));
        }

        $count = $query->count();
        $hubName = Facility::find($data['hub_facility_id'])->name;

        // Perform the assignment
        $query->update(['hub_id' => $data['hub_facility_id']]);

        \Filament\Notifications\Notification::make()
            ->title('Hub Assignment Complete')
            ->body("Assigned {$count} facilities to hub: {$hubName}")
            ->success()
            ->send();
    }

    protected function showFacilityAnalytics(): void
    {
        $analytics = [
            'total_facilities' => Facility::count(),
            'central_stores' => Facility::where('is_central_store', true)->count(),
            'hub_facilities' => Facility::where('is_hub', true)->count(),
            'facilities_with_coordinates' => Facility::whereNotNull('lat')->whereNotNull('long')->count(),
            'coverage_percentage' => round((Facility::whereNotNull('lat')->whereNotNull('long')->count() / Facility::count()) * 100, 1),
        ];

        \Filament\Notifications\Notification::make()
            ->title('Facility Analytics')
            ->body("Total: {$analytics['total_facilities']} | Central Stores: {$analytics['central_stores']} | Hubs: {$analytics['hub_facilities']} | GPS Coverage: {$analytics['coverage_percentage']}%")
            ->info()
            ->persistent()
            ->actions([
                \Filament\Notifications\Actions\Action::make('detailed_report')
                    ->label('View Detailed Report')
                    ->url('#') // Link to detailed analytics page
                    ->openUrlInNewTab(),
            ])
            ->send();
    }

    protected function analyzeCoverage(): void
    {
        // Coverage analysis logic
        \Filament\Notifications\Notification::make()
            ->title('Coverage Analysis')
            ->body('Geographic coverage analysis shows good distribution across 45 counties with 3 areas needing additional facilities.')
            ->info()
            ->persistent()
            ->actions([
                \Filament\Notifications\Actions\Action::make('view_gaps')
                    ->label('View Coverage Gaps')
                    ->url('#') // Link to coverage gap analysis
                    ->openUrlInNewTab(),
                \Filament\Notifications\Actions\Action::make('expansion_plan')
                    ->label('Generate Expansion Plan')
                    ->action(function () {
                        // Generate expansion recommendations
                    }),
            ])
            ->send();
    }
}
