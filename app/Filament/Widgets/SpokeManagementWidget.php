<?php

namespace App\Filament\Widgets;

use App\Models\Facility;
use App\Models\StockLevel;
use App\Models\StockRequest;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class SpokeManagementWidget extends BaseWidget
{
    protected static ?string $heading = 'Spoke Facilities Management';
    protected int|string|array $columnSpan = 'full';

    public ?Facility $facility = null;

    public function mount(?Facility $facility = null): void
    {
        $this->facility = $facility ?? request()->route('record');

        if (is_string($this->facility)) {
            $this->facility = Facility::find($this->facility);
        }
    }

    public function table(Table $table): Table
    {
        if (!$this->facility || !$this->facility->is_hub) {
            return $table->query(Facility::whereRaw('1 = 0'));
        }

        return $table
            ->query(
                Facility::where('hub_id', $this->facility->id)
                    ->with(['subcounty', 'facilityType'])
                    ->orderBy('name')
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Facility Name')
                    ->searchable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('mfl_code')
                    ->label('MFL Code')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('facilityType.name')
                    ->label('Type')
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('stock_summary')
                    ->label('Stock Items')
                    ->getStateUsing(fn ($record) =>
                        StockLevel::where('facility_id', $record->id)
                            ->where('current_stock', '>', 0)
                            ->count()
                    )
                    ->badge()
                    ->color('success'),
                Tables\Columns\TextColumn::make('pending_requests')
                    ->label('Pending Requests')
                    ->getStateUsing(fn ($record) =>
                        StockRequest::where('requesting_facility_id', $record->id)
                            ->where('status', 'pending')
                            ->count()
                    )
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'warning' : 'success'),
                Tables\Columns\TextColumn::make('staff_count')
                    ->label('Staff')
                    ->getStateUsing(fn ($record) => $record->users()->count())
                    ->badge()
                    ->color('primary'),
                Tables\Columns\IconColumn::make('coordinates')
                    ->label('GPS')
                    ->boolean()
                    ->getStateUsing(fn ($record) => $record->coordinates !== null)
                    ->trueIcon('heroicon-o-map-pin')
                    ->falseIcon('heroicon-o-exclamation-triangle')
                    ->trueColor('success')
                    ->falseColor('danger'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Added')
                    ->date()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('view_facility')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->url(fn ($record) => route('filament.admin.resources.facilities.view', [
                        'record' => $record->id
                    ])),
                Tables\Actions\Action::make('coordinate_transfer')
                    ->label('Coordinate Transfer')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('primary')
                    ->url(fn ($record) => route('filament.admin.resources.stock-transfers.create', [
                        'from_facility_id' => $this->facility->id,
                        'to_facility_id' => $record->id,
                    ])),
                Tables\Actions\Action::make('view_requests')
                    ->label('View Requests')
                    ->icon('heroicon-o-list-bullet')
                    ->color('warning')
                    ->visible(fn ($record) =>
                        StockRequest::where('requesting_facility_id', $record->id)
                            ->where('status', 'pending')
                            ->count() > 0
                    )
                    ->url(fn ($record) => route('filament.admin.resources.stock-requests.index', [
                        'tableFilters[requesting_facility][value]' => $record->id
                    ])),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('facility_type')
                    ->relationship('facilityType', 'name'),
                Tables\Filters\Filter::make('has_pending_requests')
                    ->label('Has Pending Requests')
                    ->query(fn ($query) => $query->whereHas('stockRequests', function ($q) {
                        $q->where('status', 'pending');
                    })),
                Tables\Filters\Filter::make('missing_gps')
                    ->label('Missing GPS')
                    ->query(fn ($query) => $query->whereNull('lat')->orWhereNull('long')),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('bulk_transfer')
                    ->label('Bulk Transfer to Spokes')
                    ->icon('heroicon-o-share')
                    ->color('primary')
                    ->form([
                        \Filament\Forms\Components\Select::make('inventory_item_id')
                            ->label('Item to Transfer')
                            ->options(\App\Models\InventoryItem::active()->pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                        \Filament\Forms\Components\TextInput::make('quantity_per_facility')
                            ->label('Quantity per Facility')
                            ->numeric()
                            ->required()
                            ->minValue(1),
                        \Filament\Forms\Components\Textarea::make('notes')
                            ->label('Transfer Notes')
                            ->rows(3),
                    ])
                    ->action(function ($records, array $data) {
                        foreach ($records as $spoke) {
                            // Create transfer for each selected spoke
                            \App\Models\StockTransfer::create([
                                'from_facility_id' => $this->facility->id,
                                'to_facility_id' => $spoke->id,
                                'initiated_by' => auth()->id(),
                                'transfer_date' => now(),
                                'priority' => 'medium',
                                'notes' => $data['notes'] ?? "Hub coordination transfer",
                                'requires_approval' => false,
                            ]);
                        }
                    }),
            ])
            ->emptyStateHeading('No Spoke Facilities')
            ->emptyStateDescription('This hub has no connected spoke facilities.')
            ->emptyStateActions([
                Tables\Actions\Action::make('assign_spokes')
                    ->label('Assign Spoke Facilities')
                    ->url(route('filament.admin.resources.facilities.index', [
                        'tableFilters[unassigned_spokes]' => true
                    ]))
                    ->icon('heroicon-m-plus'),
            ]);
    }
}
