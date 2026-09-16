<?php
// app/Filament/Resources/MenteeProfileResource/RelationManagers/MenteeStatusLogsRelationManager.php

namespace App\Filament\Resources\MenteeProfileResource\RelationManagers;

use App\Models\MenteeStatus;
use App\Models\MenteeStatusLog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Schema;

class MenteeStatusLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'statusLogs';
    protected static ?string $title = 'Status History';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Grid::make(2)->schema([
                // Use a neutral 'status_id' field for the form, then map into columns in mutate hooks
                Forms\Components\Select::make('status_id')
                    ->label('New Status')
                    ->options(fn () => MenteeStatus::query()
                        ->where('is_active', true)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->required()
                    ->native(false)
                    ->searchable(),
                Forms\Components\DatePicker::make('effective_date')
                    ->label('Effective Date')
                    ->default(now())
                    ->maxDate(now())
                    ->required(),
            ]),
            Forms\Components\TextInput::make('reason')->required()->maxLength(255),
            Forms\Components\Textarea::make('notes')->rows(3)->maxLength(2000),
            Forms\Components\Fieldset::make('Review Window')->schema([
                Forms\Components\Toggle::make('review_3m')->label('3 months')
                    ->visible(fn () => Schema::hasColumn((new MenteeStatusLog)->getTable(), 'review_3m')),
                Forms\Components\Toggle::make('review_6m')->label('6 months')
                    ->visible(fn () => Schema::hasColumn((new MenteeStatusLog)->getTable(), 'review_6m')),
                Forms\Components\Toggle::make('review_12m')->label('12 months')
                    ->visible(fn () => Schema::hasColumn((new MenteeStatusLog)->getTable(), 'review_12m')),
            ])->columns(3),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('effective_date')->label('Effective')->date(),
                Tables\Columns\BadgeColumn::make('new_status')
                    ->label('Status')
                    ->colors([
                        'success' => 'active',
                        'warning' => 'study_leave',
                        'danger'  => fn ($state) => in_array($state, ['resigned','transferred','retired','defected','deceased','suspended','dropped_out'], true),
                    ])
                    ->formatStateUsing(fn ($state) => ucwords(str_replace('_', ' ', $state))),
                Tables\Columns\TextColumn::make('reason')->wrap(),
                Tables\Columns\TextColumn::make('notes')->toggleable()->toggledHiddenByDefault()->wrap(),
                Tables\Columns\TextColumn::make('created_at')->since()->label('Logged'),
            ])
            ->filters([])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data) {
                        // Map the selected status_id into new_status string (and mentee_status_id if column exists)
                        $status = isset($data['status_id']) ? MenteeStatus::find($data['status_id']) : null;
                        if ($status) {
                            // Always write string if column exists
                            if (Schema::hasColumn((new MenteeStatusLog)->getTable(), 'new_status')) {
                                $data['new_status'] = $status->name;
                            }
                            // Optionally write foreign key if present
                            if (Schema::hasColumn((new MenteeStatusLog)->getTable(), 'mentee_status_id')) {
                                $data['mentee_status_id'] = $status->id;
                            }
                        }
                        unset($data['status_id']);

                        // previous_status if column exists
                        if (Schema::hasColumn((new MenteeStatusLog)->getTable(), 'previous_status')) {
                            $parent = $this->getOwnerRecord();
                            $data['previous_status'] = \App\Filament\Resources\MenteeProfileResource::currentStatus($parent);
                        }

                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->mutateFormDataUsing(function (array $data) {
                        $status = isset($data['status_id']) ? MenteeStatus::find($data['status_id']) : null;
                        if ($status) {
                            if (Schema::hasColumn((new MenteeStatusLog)->getTable(), 'new_status')) {
                                $data['new_status'] = $status->name;
                            }
                            if (Schema::hasColumn((new MenteeStatusLog)->getTable(), 'mentee_status_id')) {
                                $data['mentee_status_id'] = $status->id;
                            }
                        }
                        unset($data['status_id']);
                        return $data;
                    }),
            ])
            ->bulkActions([]);
    }
}
