<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RagDocumentResource\Pages;
use App\Jobs\ProcessRagDocument;
use App\Models\RagDocument;
use App\Services\Rag\RagClient;
use App\Support\RagAccess;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class RagDocumentResource extends Resource
{
    protected static ?string $model = RagDocument::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-magnifying-glass';

    protected static ?string $navigationGroup = 'knowledge Base';

    protected static ?string $navigationLabel = 'RAG Documents';

    protected static ?string $modelLabel = 'RAG Document';

    protected static ?int $navigationSort = 90;

    public static function shouldRegisterNavigation(): bool
    {
        return RagAccess::canManageDocuments(auth()->user());
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Document')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\FileUpload::make('path')
                            ->label('Document')
                            ->disk(fn () => config('rag.uploads.disk', 'local'))
                            ->directory(fn () => trim((string) config('rag.uploads.directory', 'private/knowledge-base'), '/').'/'.now()->format('Y/m/d'))
                            ->acceptedFileTypes(config('rag.uploads.allowed_mime_types', []))
                            ->rules(['extensions:'.implode(',', config('rag.uploads.allowed_extensions', []))])
                            ->maxSize((int) config('rag.uploads.max_size_kb', 51200))
                            ->storeFileNamesIn('original_name')
                            ->getUploadedFileNameForStorageUsing(fn (TemporaryUploadedFile $file): string => Str::uuid().'.'.strtolower($file->getClientOriginalExtension()))
                            ->required()
                            ->helperText('Files are stored privately and indexed after creation.'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->latest())
            ->poll('10s')
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('original_name')
                    ->label('File')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('extension')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('formatted_size')
                    ->label('Size')
                    ->state(fn (RagDocument $record): string => $record->formattedSize()),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        RagDocument::STATUS_READY => 'success',
                        RagDocument::STATUS_FAILED => 'danger',
                        RagDocument::STATUS_PROCESSING => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('uploader.full_name')
                    ->label('Uploader')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('processed_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        RagDocument::STATUS_PENDING => 'Pending',
                        RagDocument::STATUS_PROCESSING => 'Processing',
                        RagDocument::STATUS_READY => 'Ready',
                        RagDocument::STATUS_FAILED => 'Failed',
                    ]),
                Tables\Filters\SelectFilter::make('extension')
                    ->options([
                        'pdf' => 'PDF',
                        'docx' => 'DOCX',
                        'pptx' => 'PPTX',
                        'xlsx' => 'XLSX',
                        'csv' => 'CSV',
                        'txt' => 'TXT',
                        'md' => 'Markdown',
                        'html' => 'HTML',
                        'json' => 'JSON',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (RagDocument $record): string => route('rag.documents.download', $record))
                    ->openUrlInNewTab()
                    ->visible(fn (RagDocument $record): bool => auth()->user()?->can('download', $record) && $record->fileExists()),
                Tables\Actions\Action::make('retry')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (RagDocument $record): bool => auth()->user()?->can('retry', $record) ?? false)
                    ->action(function (RagDocument $record): void {
                        $record->forceFill([
                            'status' => RagDocument::STATUS_PENDING,
                            'failed_at' => null,
                            'error_message' => null,
                        ])->save();

                        ProcessRagDocument::dispatch($record->id);

                        Notification::make()->title('Document queued for reprocessing')->success()->send();
                    }),
                Tables\Actions\DeleteAction::make()
                    ->before(function (RagDocument $record): void {
                        app(RagClient::class)->delete($record->external_document_id);
                        $record->chunks()->delete();

                        if ($record->fileExists()) {
                            Storage::disk($record->disk)->delete($record->path);
                        }
                    }),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRagDocuments::route('/'),
            'create' => Pages\CreateRagDocument::route('/create'),
            'view' => Pages\ViewRagDocument::route('/{record}'),
        ];
    }
}
