<?php

namespace App\Filament\Widgets;

use App\Models\Facility;
use App\Models\StockRequest;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class FacilityRequestsWidget extends BaseWidget
{
    protected static ?string $heading = 'Recent Stock Requests';
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
        if (!$this->facility) {
            return $table->query(StockRequest::whereRaw('1 = 0'));
        }

        return $table
            ->query(
                StockRequest::where('requesting_facility_id', $this->facility->id)
                    ->with(['centralStore', 'items.inventoryItem'])
                    ->orderBy('created_at', 'desc')
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('request_number')
                    ->label('Request #')
                    ->searchable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('centralStore.name')
                    ->label('Central Store')
                    ->limit(20),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'partially_approved' => 'info',
                        'rejected' => 'danger',
                        'dispatched' => 'primary',
                        'received' => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('priority')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'urgent' => 'danger',
                        'high' => 'warning',
                        'medium' => 'info',
                        'low' => 'gray',
                    }),
                Tables\Columns\TextColumn::make('total_items')
                    ->label('Items')
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('total_requested_value')
                    ->label('Value')
                    ->money('KES'),
                Tables\Columns\TextColumn::make('request_date')
                    ->label('Requested')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('approved_date')
                    ->label('Approved')
                    ->date()
                    ->placeholder('N/A'),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->url(fn ($record) => route('filament.admin.resources.stock-requests.view', [
                        'record' => $record->id
                    ])),
                Tables\Actions\Action::make('track')
                    ->label('Track')
                    ->icon('heroicon-o-truck')
                    ->color('primary')
                    ->visible(fn ($record) => in_array($record->status, ['dispatched', 'partially_dispatched']))
                    ->action(function ($record) {
                        // Show tracking information
                        \Filament\Notifications\Notification::make()
                            ->title('Request Status')
                            ->body("Request {$record->request_number} was dispatched on {$record->dispatch_date?->format('M j, Y')}. Expected delivery within 2-3 business days.")
                            ->info()
                            ->send();
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'dispatched' => 'Dispatched',
                        'received' => 'Received',
                        'rejected' => 'Rejected',
                    ]),
                Tables\Filters\SelectFilter::make('priority')
                    ->options([
                        'urgent' => 'Urgent',
                        'high' => 'High',
                        'medium' => 'Medium',
                        'low' => 'Low',
                    ]),
            ])
            ->emptyStateHeading('No Stock Requests')
            ->emptyStateDescription('This facility has not made any stock requests yet.')
            ->emptyStateActions([
                Tables\Actions\Action::make('create_request')
                    ->label('Create Stock Request')
                    ->url(route('filament.admin.resources.stock-requests.create', [
                        'requesting_facility_id' => $this->facility->id
                    ]))
                    ->icon('heroicon-m-plus'),
            ]);
    }
}
