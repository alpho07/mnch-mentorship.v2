<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ScopeResource\Pages;
use App\Models\Scope;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ScopeResource extends Resource
{
    protected static ?string $model = Scope::class;
    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';
    protected static ?string $navigationGroup = 'App Configuration';
    protected static ?int $navigationSort = 1;
    protected static ?string $recordTitleAttribute = 'label';

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->can('view_any_scope');}

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->can('view_any_scope');}

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Identity')->schema([
                Forms\Components\TextInput::make('slug')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->regex('/^[a-z0-9_]+$/')
                    ->maxLength(50)
                    ->helperText('Lowercase letters, numbers, underscores only. e.g. assessments'),
                Forms\Components\TextInput::make('label')
                    ->required()
                    ->maxLength(100),
                Forms\Components\TextInput::make('icon')
                    ->required()
                    ->maxLength(10)
                    ->helperText('Paste an emoji. e.g. 🏥'),
                Forms\Components\TextInput::make('sort_order')
                    ->numeric()
                    ->default(0)
                    ->helperText('Lower number = appears first on hub screen'),
                Forms\Components\Toggle::make('is_active')
                    ->default(true)
                    ->helperText('Inactive scopes are hidden from all users immediately'),
            ])->columns(2),

            Forms\Components\Section::make('Visual Style')->schema([
                Forms\Components\ColorPicker::make('color')
                    ->required()
                    ->label('Primary Color'),
                Forms\Components\ColorPicker::make('gradient.0')
                    ->required()
                    ->label('Gradient Start'),
                Forms\Components\ColorPicker::make('gradient.1')
                    ->required()
                    ->label('Gradient End'),
            ])->columns(3),

            Forms\Components\Section::make('Bottom Nav Tabs')->schema([
                Forms\Components\Repeater::make('tabs')
                    ->simple(
                        Forms\Components\TextInput::make('tab')
                            ->required()
                            ->placeholder('e.g. home, assessments, reports, profile')
                    )
                    ->reorderable()
                    ->addActionLabel('Add Tab')
                    ->helperText('Tab slugs in display order. Must match tab IDs used in the mobile scope component.'),
            ]),

            Forms\Components\Section::make('Role Access')->schema([
                Forms\Components\CheckboxList::make('role_names')
                    ->label('Roles that can access this scope')
                    ->options([
                        'super_admin'          => 'Super Admin',
                        'admin'                => 'Admin',
                        'division'             => 'Division',
                        'national'             => 'National',
                        'division_lead'        => 'Division Lead',
                        'national_mentor_lead' => 'National Mentor Lead',
                        'county'               => 'County',
                        'county_mentor_lead'   => 'County Mentor Lead',
                        'subcounty'            => 'Subcounty',
                        'subcounty_mentor_lead'=> 'Subcounty Mentor Lead',
                        'facility_mentor'      => 'Facility Mentor',
                        'facility_mentor_lead' => 'Facility Mentor Lead',
                        'spoke_mentor'         => 'Spoke Mentor',
                        'spoke_mentor_lead'    => 'Spoke Mentor Lead',
                        'mentee'               => 'Mentee',
                    ])
                    ->columns(3)
                    ->helperText('Note: super_admin always sees all active scopes regardless of this setting'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sort_order')->sortable()->label('#'),
                Tables\Columns\TextColumn::make('icon'),
                Tables\Columns\TextColumn::make('label')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('slug')->searchable(),
                Tables\Columns\TextColumn::make('tabs')
                    ->formatStateUsing(fn ($state) => implode(', ', $state ?? []))
                    ->label('Tabs'),
                Tables\Columns\IconColumn::make('is_active')->boolean()->label('Active'),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListScopes::route('/'),
            'create' => Pages\CreateScope::route('/create'),
            'edit'   => Pages\EditScope::route('/{record}/edit'),
        ];
    }
}
