<?php

namespace App\Filament\Resources;

use App\Models\Program;
use App\Models\ProgramModule;
use App\Models\Resource;
use App\Models\Tag;
use App\Services\ResourceService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource as FilamentResource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use App\Filament\Resources\ResourceResource\Pages;
use Filament\Notifications\Notification;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ResourceResource extends FilamentResource {

    protected static ?string $model = Resource::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Content Management';
    protected static ?int $navigationSort = 1;

    public static function shouldRegisterNavigation(): bool {
        return auth()->check() && auth()->user()->can('view_any_resource');}

    public static function form(Form $form): Form {
        return $form->schema([
                            Forms\Components\Group::make()
                            ->schema([
                                static::getBasicInfoSection(),
                                static::getFileSection(),
                            ])
                            ->columnSpan(['lg' => 2]),
                            Forms\Components\Group::make()
                            ->schema([
                                static::getPublishingSection(),
                                static::getCategorizationSection(),
                                static::getProgramsSection(),
                                static::getAnalyticsSection(),
                            ])
                            ->columnSpan(['lg' => 1]),
                        ])
                        ->columns(3);
    }

    protected static function getBasicInfoSection(): Forms\Components\Section {
        return Forms\Components\Section::make('Basic Information')
                        ->schema([
                            Forms\Components\TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn(string $operation, $state, Forms\Set $set) =>
                                    $operation === 'create' ? $set('slug', Str::slug($state)) : null
                            ),
                            Forms\Components\TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->rules(['alpha_dash']),
                            Forms\Components\Textarea::make('excerpt')
                            ->maxLength(500)
                            ->rows(3),
                            Forms\Components\RichEditor::make('content')
                            ->required()
                            ->columnSpanFull(),
        ]);
    }

    protected static function getFileSection(): Forms\Components\Section {
        return Forms\Components\Section::make('Files & Media')
                        ->schema([
                            // --- Featured Image (simplified - let default behavior work) ---
                            Forms\Components\FileUpload::make('featured_image')
                            ->image()
                            ->disk('thumbnails')
                            ->directory('resources')
                            ->maxSize(102400)
                            ->label('Featured Image')
                            ->validationMessages([
                                'max' => 'The featured image exceeds the 100MB limit.',
                            ]),
                            // --- Resource Files Repeater ---
                            Forms\Components\Repeater::make('resource_files')
                            ->label('Resource Files')
                            ->relationship('files')
                            ->schema([
                                Forms\Components\FileUpload::make('file_path')
                                ->label('File')
                                ->disk('resources')
                                ->directory(fn() => 'documents/' . date('Y/m/d'))
                                ->maxSize(102400)
                                ->acceptedFileTypes([
                                    'application/pdf', 'application/msword',
                                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                    'application/vnd.ms-powerpoint',
                                    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                                    'application/vnd.ms-excel',
                                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                    'text/plain', 'text/csv', 'application/zip', 'application/x-rar-compressed',
                                    'video/mp4', 'video/webm', 'audio/mpeg', 'audio/wav',
                                    'image/jpeg', 'image/png', 'image/gif',
                                ])
                                ->getUploadedFileNameForStorageUsing(
                                        function (TemporaryUploadedFile $file): string {
                                            return time() . '_' . Str::random(8) . '.' . $file->getClientOriginalExtension();
                                        }
                                )
                                ->afterStateUpdated(function ($state, Forms\Set $set) {
                                    if (!$state instanceof TemporaryUploadedFile) {
                                        return;
                                    }

                                    try {
                                        $set('original_name', $state->getClientOriginalName());
                                        $set('file_name', $state->getClientOriginalName());
                                        $set('file_size', $state->getSize());
                                        $set('file_type', $state->getMimeType());
                                    } catch (\Exception $e) {
                                        Notification::make()
                                                ->title('File processing error')
                                                ->body('Could not read file metadata: ' . $e->getMessage())
                                                ->danger()
                                                ->send();
                                    }
                                })
                               // ->required()
                                ->validationMessages([
                                    'max' => 'This file is too large. Max allowed is 100MB.',
                                    'mimetypes' => 'This file type is not supported by the system.',
                                    'required' => 'An actual file upload is required here.',
                                ]),
                                Forms\Components\TextInput::make('original_name')
                                ->label('File Name')
                                //->required()
                                ->maxLength(255),
                                Forms\Components\TextInput::make('description')
                                ->label('File Description')
                                ->maxLength(255),
                                Forms\Components\Toggle::make('is_primary')
                                ->label('Primary File')
                                ->helperText('Mark as the main file for this resource'),
                                Forms\Components\Hidden::make('file_name'),
                                Forms\Components\Hidden::make('file_size'),
                                Forms\Components\Hidden::make('file_type'),
                            ])
                            ->columns(2)
                            ->itemLabel(fn(array $state): ?string => $state['original_name'] ?? 'New File')
                            ->addActionLabel('Add File')
                            ->reorderable('sort_order')
                            ->collapsible()
                            ->cloneable(),
                            // --- External Link Option ---
                            Forms\Components\TextInput::make('external_url')
                            ->url()
                            ->maxLength(500)
                            ->label('External URL')
                            ->helperText('Alternative to file upload - link to external resource'),
                        ])
                        ->collapsible();
    }

    protected static function getPublishingSection(): Forms\Components\Section {
        return Forms\Components\Section::make('Publishing')
                        ->schema([
                            Forms\Components\Select::make('status')
                            ->options([
                                'draft' => 'Draft',
                                'published' => 'Published',
                                'archived' => 'Archived',
                            ])
                            ->default('draft')
                            ->required(),
                            Forms\Components\Select::make('visibility')
                            ->options([
                                'public' => 'Public',
                                'authenticated' => 'Authenticated Users',
                                'private' => 'Private',
                            ])
                            ->default('public')
                            ->required(),
                            Forms\Components\DateTimePicker::make('published_at')
                            ->default(now()),
                            Forms\Components\Toggle::make('is_featured'),
                            Forms\Components\Toggle::make('is_downloadable')
                            ->default(true),
        ]);
    }

    protected static function getCategorizationSection(): Forms\Components\Section {
        return Forms\Components\Section::make('Categorization')
                        ->schema([
                            Forms\Components\Select::make('category_id')
                            ->relationship('category', 'name')
                            ->required()
                            ->searchable()
                            ->preload(),
                            Forms\Components\Select::make('resource_type_id')
                            ->relationship('resourceType', 'name')
                            ->required()
                            ->searchable()
                            ->preload(),
                            Forms\Components\Select::make('difficulty_level')
                            ->options([
                                'beginner' => 'Beginner',
                                'intermediate' => 'Intermediate',
                                'advanced' => 'Advanced',
                            ]),
                            Forms\Components\Select::make('author_id')
                            ->relationship('author', 'first_name')
                            ->getOptionLabelFromRecordUsing(fn($record) => $record->full_name ?? $record->first_name)
                            ->searchable(['first_name', 'last_name'])
                            ->default(fn() => auth()->id()),
                            Forms\Components\TagsInput::make('tag_names')
                            ->suggestions(Tag::pluck('name')->toArray())
                            ->helperText('Enter tags and press enter'),
                            Forms\Components\Select::make('access_groups')
                            ->multiple()
                            ->relationship('accessGroups', 'name')
                            ->preload(),
        ]);
    }

    protected static function getProgramsSection(): Forms\Components\Section {
        return Forms\Components\Section::make('Programs & Modules')
                        ->description('Optionally link this resource to specific programs or modules.')
                        ->schema([
                            Forms\Components\Select::make('program_ids')
                            ->label('Programs')
                            ->multiple()
                            ->options(fn () => Program::orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(fn (Forms\Set $set) => $set('program_module_ids', []))
                            ->helperText('Select one or more programs'),
                            Forms\Components\Select::make('program_module_ids')
                            ->label('Modules')
                            ->multiple()
                            ->options(fn (Forms\Get $get) => filled($get('program_ids'))
                                ? ProgramModule::whereIn('program_id', $get('program_ids'))
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                : ProgramModule::orderBy('name')->pluck('name', 'id')
                            )
                            ->searchable()
                            ->helperText('Leave empty to attach to all modules of the selected programs'),
                        ])
                        ->collapsible();
    }

    protected static function getAnalyticsSection(): Forms\Components\Section {
        return Forms\Components\Section::make('Analytics')
                        ->schema([
                            Forms\Components\Placeholder::make('view_count')
                            ->content(fn(?Resource $record) => $record?->view_count ?? 0),
                            Forms\Components\Placeholder::make('download_count')
                            ->content(fn(?Resource $record) => $record?->download_count ?? 0),
                            Forms\Components\Placeholder::make('like_count')
                            ->content(fn(?Resource $record) => $record?->like_count ?? 0),
                            Forms\Components\Placeholder::make('created_at')
                            ->content(fn(?Resource $record) =>
                                    $record?->created_at?->format('M j, Y') ?? 'Not saved yet'
                            ),
                        ])
                        ->hiddenOn('create');
    }

    public static function table(Table $table): Table {
        return $table
                        ->columns([
                            Tables\Columns\ImageColumn::make('featured_image')
                            ->disk('thumbnails')
                            ->size(50)
                            ->square()
                            ->defaultImageUrl(url('/images/placeholder-resource.png')),
                            Tables\Columns\TextColumn::make('title')
                            ->searchable()
                            ->sortable()
                            ->limit(50)
                            ->tooltip(function (Tables\Columns\TextColumn $column): ?string {
                                $state = $column->getState();
                                return strlen($state) > 50 ? $state : null;
                            }),
                            Tables\Columns\TextColumn::make('category.name')
                            ->badge()
                            ->searchable()
                            ->sortable(),
                            Tables\Columns\TextColumn::make('resourceType.name')
                            ->label('Type')
                            ->badge()
                            ->color('success'),
                            Tables\Columns\TextColumn::make('files_count')
                            ->label('Files')
                            ->counts('files')
                            ->numeric()
                            ->color('primary')
                            ->icon('heroicon-o-document'),
                            Tables\Columns\TextColumn::make('primary_file.formatted_file_size')
                            ->label('Primary File Size')
                            ->placeholder('No primary file'),
                            Tables\Columns\IconColumn::make('has_files')
                            ->label('Has Files')
                            ->getStateUsing(fn(Resource $record) => $record->files()->exists())
                            ->boolean()
                            ->trueIcon('heroicon-o-document-check')
                            ->falseIcon('heroicon-o-document-minus'),
                            Tables\Columns\TextColumn::make('status')
                            ->badge()
                            ->color(fn(string $state): string => match ($state) {
                                        'draft' => 'warning',
                                        'published' => 'success',
                                        'archived' => 'danger',
                                    }),
                            Tables\Columns\TextColumn::make('visibility')
                            ->badge()
                            ->color(fn(string $state): string => match ($state) {
                                        'public' => 'success',
                                        'authenticated' => 'info',
                                        'private' => 'warning',
                                        default => 'gray',
                                    }),
                            Tables\Columns\IconColumn::make('is_featured')
                            ->boolean()
                            ->trueIcon('heroicon-o-star')
                            ->trueColor('warning'),
                            Tables\Columns\TextColumn::make('view_count')
                            ->numeric()
                            ->sortable(),
                            Tables\Columns\TextColumn::make('download_count')
                            ->numeric()
                            ->sortable(),
                            Tables\Columns\TextColumn::make('published_at')
                            ->dateTime('M j, Y')
                            ->sortable()
                            ->placeholder('Not published'),
                            Tables\Columns\TextColumn::make('author.first_name')
                            ->label('Author')
                            ->formatStateUsing(fn($record) => $record->author?->full_name ?? 'Unknown')
                            ->searchable(['first_name', 'last_name']),
                        ])
                        ->filters([
                            Tables\Filters\SelectFilter::make('status')
                            ->options([
                                'draft' => 'Draft',
                                'published' => 'Published',
                                'archived' => 'Archived',
                            ])
                            ->multiple(),
                            Tables\Filters\SelectFilter::make('category')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->multiple(),
                            Tables\Filters\SelectFilter::make('resource_type')
                            ->relationship('resourceType', 'name')
                            ->multiple(),
                            Tables\Filters\TernaryFilter::make('is_featured')
                            ->placeholder('All resources')
                            ->trueLabel('Featured only')
                            ->falseLabel('Not featured'),
                            Tables\Filters\TernaryFilter::make('has_file')
                            ->placeholder('All resources')
                            ->trueLabel('With files')
                            ->falseLabel('Without files')
                            ->queries(
                                    true: fn(Builder $query) => $query->whereNotNull('file_path'),
                                    false: fn(Builder $query) => $query->whereNull('file_path'),
                            ),
                        ])
                        ->actions([
                            Tables\Actions\ActionGroup::make([
                                Tables\Actions\ViewAction::make(),
                                Tables\Actions\EditAction::make(),
                                Tables\Actions\DeleteAction::make(),
                            ])
                            ->label('Actions')
                            ->icon('heroicon-m-ellipsis-vertical')
                            ->size('sm')
                            ->color('gray')
                            ->button(),
                        ])
                        ->bulkActions([
                            Tables\Actions\BulkActionGroup::make([
                                Tables\Actions\DeleteBulkAction::make(),
                                Tables\Actions\BulkAction::make('publish')
                                ->label('Publish Selected')
                                ->icon('heroicon-o-eye')
                                ->color('success')
                                ->action(function (Collection $records) {
                                    $records->each(function (Resource $record) {
                                        $record->update([
                                            'status' => 'published',
                                            'published_at' => $record->published_at ?? now(),
                                        ]);
                                    });
                                })
                                ->requiresConfirmation()
                                ->deselectRecordsAfterCompletion(),
                                Tables\Actions\BulkAction::make('draft')
                                ->label('Mark as Draft')
                                ->icon('heroicon-o-pencil')
                                ->color('warning')
                                ->action(fn(Collection $records) => $records->each->update(['status' => 'draft']))
                                ->deselectRecordsAfterCompletion(),
                            ]),
                        ])
                        ->defaultSort('created_at', 'desc')
                        ->emptyStateHeading('No Resources Yet')
                        ->emptyStateDescription('Add articles, guides, or files to the knowledge base.')
                        ->emptyStateActions([
                            Tables\Actions\CreateAction::make(),
                        ]);
    }

    public static function getRelations(): array {
        return [
            ResourceResource\RelationManagers\FilesRelationManager::class,
        ];
    }

    public static function getPages(): array {
        return [
            'index' => Pages\ListResources::route('/'),
            'create' => Pages\CreateResource::route('/create'),
            'edit' => Pages\EditResource::route('/{record}/edit'),
            'view' => Pages\ViewResource::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder {
        return parent::getEloquentQuery()
                        ->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getNavigationBadge(): ?string {
        $count = static::getModel()::where('status', 'draft')->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string {
        return 'warning';
    }
}
