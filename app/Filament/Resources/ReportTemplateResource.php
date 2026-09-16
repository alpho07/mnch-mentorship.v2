<?php

// ReportTemplateResource.php

namespace App\Filament\Resources;

use App\Filament\Resources\ReportTemplateResource\Pages;
use App\Models\ReportTemplate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ReportTemplateResource extends Resource {

    protected static ?string $model = ReportTemplate::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Report Management';
    protected static ?int $navigationSort = 1;

    public static function shouldRegisterNavigation(): bool {
        return auth()->check() && auth()->user()->can('view_any_report::template');}

    public static function form(Form $form): Form {
        return $form
                        ->schema([
                            Forms\Components\Section::make('Basic Information')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                ->required()
                                ->maxLength(255)
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn(string $context, $state, Forms\Set $set) =>
                                        $context === 'create' ? $set('code', str(str($state)->slug())->upper()) : null),
                                Forms\Components\TextInput::make('code')
                                ->required()
                                ->unique(ignoreRecord: true)
                                ->maxLength(255),
                                //->alpha_dash()
                                //->uppercase(),
                                Forms\Components\Textarea::make('description')
                                ->rows(3)
                                ->columnSpanFull(),
                                Forms\Components\Select::make('report_type')
                                ->required()
                                ->options([
                                    'newborn' => 'Newborn Care',
                                    'pediatric' => 'Pediatric Care',
                                    'general' => 'General',
                                ]),
                                Forms\Components\Select::make('frequency')
                                ->required()
                                ->default('monthly')
                                ->options([
                                    'monthly' => 'Monthly',
                                    'quarterly' => 'Quarterly',
                                    'annually' => 'Annually',
                                ]),
                                Forms\Components\Toggle::make('is_active')
                                ->default(true),
                            ])
                            ->columns(2),
                            Forms\Components\Section::make('Indicators')
                            ->schema([
                                Forms\Components\Repeater::make('indicators')
                                ->relationship('indicators')
                                ->schema([
                                    Forms\Components\TextInput::make('sort_order')
                                    ->numeric()
                                    ->default(fn(Forms\Get $get) => count($get('../../indicators')) + 1),
                                    Forms\Components\Toggle::make('is_required')
                                    ->default(true),
                                ])
                                ->columns(4)
                                ->itemLabel(fn(array $state): ?string =>
                                        \App\Models\Indicator::find($state['id'])?->name ?? null)
                                ->addActionLabel('Add Indicator')
                                ->reorderable('sort_order')
                                ->collapsible(),
                            ])
                            ->hidden(fn(string $operation): bool => $operation === 'create'),
        ]);
    }

    public static function table(Table $table): Table {
        return $table
                        ->columns([
                            Tables\Columns\TextColumn::make('name')
                            ->searchable()
                            ->sortable(),
                            Tables\Columns\TextColumn::make('code')
                            ->searchable()
                            ->sortable()
                            ->badge(),
                            Tables\Columns\TextColumn::make('report_type')
                            ->badge()
                            ->color(fn(string $state): string => match ($state) {
                                        'newborn' => 'success',
                                        'pediatric' => 'info',
                                        'general' => 'gray',
                                    }),
                            Tables\Columns\TextColumn::make('frequency')
                            ->badge()
                            ->color('warning'),
                            Tables\Columns\TextColumn::make('indicators_count')
                            ->counts('indicators')
                            ->label('Indicators'),
                            Tables\Columns\IconColumn::make('is_active')
                            ->boolean(),
                            Tables\Columns\TextColumn::make('created_at')
                            ->dateTime()
                            ->sortable()
                            ->toggleable(isToggledHiddenByDefault: true),
                        ])
                        ->filters([
                            Tables\Filters\SelectFilter::make('report_type')
                            ->options([
                                'newborn' => 'Newborn Care',
                                'pediatric' => 'Pediatric Care',
                                'general' => 'General',
                            ]),
                            Tables\Filters\TernaryFilter::make('is_active'),
                        ])
                        ->actions([
                            Tables\Actions\ViewAction::make(),
                            Tables\Actions\EditAction::make(),
                        ])
                        ->bulkActions([
                            Tables\Actions\BulkActionGroup::make([
                                Tables\Actions\DeleteBulkAction::make(),
                            ]),
        ]);
    }

    public static function getRelations(): array {
        return [
                //
        ];
    }

    public static function getPages(): array {
        return [
            'index' => Pages\ListReportTemplates::route('/'),
            'create' => Pages\CreateReportTemplate::route('/create'),
            'view' => Pages\ViewReportTemplate::route('/{record}'),
            'edit' => Pages\EditReportTemplate::route('/{record}/edit'),
        ];
    }
}
