<?php

namespace App\Filament\Resources\IndicatorReportTypeResource\RelationManagers;

use App\Models\Indicators\Indicator;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class IndicatorsRelationManager extends RelationManager {

    protected static string $relationship = 'indicators';
    protected static ?string $title = 'Indicators';

    public function form(Form $form): Form {
        return $form->schema([
                    Forms\Components\Grid::make(2)->schema([
                                Forms\Components\TextInput::make('code')
                                ->required()->maxLength(30),
                                Forms\Components\TextInput::make('short_name')
                                ->maxLength(100),
                    ]),
                            Forms\Components\TextInput::make('name')
                            ->required()->maxLength(255)->columnSpanFull(),
                    Forms\Components\Grid::make(2)->schema([
                                Forms\Components\Select::make('indicator_type')
                                ->options([
                                    'proportion' => 'Proportion',
                                    'count' => 'Count',
                                    'yes_no' => 'Yes / No',
                                    'rate' => 'Rate',
                                ])
                                ->required()->default('proportion')->live(),
                                Forms\Components\Select::make('category')
                                ->options([
                                    'process' => 'Process',
                                    'output' => 'Output',
                                    'outcome' => 'Outcome',
                                    'satisfaction' => 'Satisfaction',
                                ])
                                ->required()->default('process'),
                    ]),
                    Forms\Components\Grid::make(2)->schema([
                                Forms\Components\TextInput::make('numerator_label')
                                ->maxLength(200)
                                ->visible(fn(Get $get) => in_array($get('indicator_type'), ['proportion', 'rate'])),
                                Forms\Components\TextInput::make('denominator_label')
                                ->maxLength(200)
                                ->visible(fn(Get $get) => in_array($get('indicator_type'), ['proportion', 'rate'])),
                    ]),
                            Forms\Components\Select::make('parent_indicator_id')
                            ->label('Parent Indicator (sub-band only)')
                            ->options(
                                    Indicator::where('group_id', $this->getOwnerRecord()->id)
                                    ->whereNull('parent_indicator_id')
                                    ->pluck('name', 'id')
                            )
                            ->nullable()->searchable()->placeholder('None'),
                    Forms\Components\Grid::make(3)->schema([
                                Forms\Components\TextInput::make('dhis2_numerator_uid')->maxLength(11)
                                ->visible(fn(Get $get) => in_array($get('indicator_type'), ['proportion', 'rate'])),
                                Forms\Components\TextInput::make('dhis2_denominator_uid')->maxLength(11)
                                ->visible(fn(Get $get) => in_array($get('indicator_type'), ['proportion', 'rate'])),
                                Forms\Components\TextInput::make('dhis2_count_uid')->maxLength(11)
                                ->visible(fn(Get $get) => $get('indicator_type') === 'count'),
                    ]),
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('sort_order')->numeric()->default(0),
                        Forms\Components\Toggle::make('is_active')->default(true),
                    ]),
        ]);
    }

    public function table(Table $table): Table {
        return $table
                        ->columns([
                            Tables\Columns\TextColumn::make('code')
                            ->badge()->color('gray')->searchable()->copyable(),
                            Tables\Columns\TextColumn::make('name')
                            ->searchable()->wrap()
                            ->description(fn($record) => $record->short_name),
                            Tables\Columns\BadgeColumn::make('indicator_type')
                            ->colors([
                                'primary' => 'proportion',
                                'info' => 'count',
                                'success' => 'yes_no',
                                'warning' => 'rate',
                            ]),
                            Tables\Columns\IconColumn::make('dhis2_ready')
                            ->label('DHIS2')
                            ->getStateUsing(fn($record) => $record->isDhis2Ready())
                            ->boolean()
                            ->trueColor('success')->falseColor('gray'),
                            Tables\Columns\IconColumn::make('is_active')->boolean(),
                            Tables\Columns\TextColumn::make('sort_order')->sortable()->alignCenter(),
                        ])
                        ->headerActions([Tables\Actions\CreateAction::make()])
                        ->actions([
                            Tables\Actions\EditAction::make(),
                            Tables\Actions\ReplicateAction::make()
                            ->mutateRecordDataUsing(function (array $data): array {
                                $data['code'] = $data['code'] . '_COPY';
                                $data['dhis2_numerator_uid'] = null;
                                $data['dhis2_denominator_uid'] = null;
                                $data['dhis2_count_uid'] = null;
                                return $data;
                            }),
                            Tables\Actions\DeleteAction::make()
                            ->before(function ($record, $action) {
                                if ($record->values()->exists()) {
                                    \Filament\Notifications\Notification::make()
                                            ->title('Cannot delete')->body('Has report data — deactivate instead.')
                                            ->danger()->send();
                                    $action->cancel();
                                }
                            }),
                        ])
                        ->defaultSort('sort_order')
                        ->reorderable('sort_order');
    }
}
