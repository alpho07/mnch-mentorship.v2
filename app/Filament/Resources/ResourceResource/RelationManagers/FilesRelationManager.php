<?php

namespace App\Filament\Resources\ResourceResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use App\Models\ResourceFile;

class FilesRelationManager extends RelationManager
{
    protected static string $relationship = 'files';
    protected static ?string $recordTitleAttribute = 'original_name';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\FileUpload::make('file_path')
                    ->label('File')
                    ->required()
                    ->disk('resources')
                    ->directory(fn () => 'documents/' . date('Y/m/d'))
                    ->maxSize(102400) // 100MB
                    ->acceptedFileTypes([
                        'application/pdf',
                        'application/msword',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'application/vnd.ms-powerpoint',
                        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                        'application/vnd.ms-excel',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'text/plain', 'text/csv',
                        'application/zip', 'application/x-rar-compressed',
                        'video/mp4', 'video/webm',
                        'audio/mpeg', 'audio/wav',
                        'image/jpeg', 'image/png', 'image/gif',
                    ])
                    ->getUploadedFileNameForStorageUsing(
                        fn (\Livewire\Features\SupportFileUploads\TemporaryUploadedFile $file): string => 
                            time() . '_' . \Str::random(8) . '.' . $file->getClientOriginalExtension()
                    )
                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                        if ($state instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
                            $originalName = $state->getClientOriginalName();
                            $set('original_name', $originalName);
                            $set('file_name', $originalName);
                            $set('file_size', $state->getSize());
                            $set('file_type', $state->getMimeType());
                        }
                    })
                    ->columnSpanFull(),

                Forms\Components\TextInput::make('original_name')
                    ->label('File Name')
                    ->required()
                    ->maxLength(255)
                    ->helperText('This will be the display name for the file'),

                Forms\Components\TextInput::make('description')
                    ->label('Description')
                    ->maxLength(255),

                Forms\Components\Toggle::make('is_primary')
                    ->label('Primary File')
                    ->helperText('Mark as the main file for this resource'),

                // Hidden fields for file metadata
                Forms\Components\Hidden::make('file_name'),
                Forms\Components\Hidden::make('file_size'),
                Forms\Components\Hidden::make('file_type'),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('original_name')
            ->columns([
                Tables\Columns\IconColumn::make('file_icon')
                    ->label('')
                    ->getStateUsing(fn (ResourceFile $record) => $record->getFileIcon())
                    ->size('lg'),

                Tables\Columns\TextColumn::make('original_name')
                    ->label('File Name')
                    ->searchable()
                    ->copyable()
                    ->limit(50),

                Tables\Columns\TextColumn::make('description')
                    ->limit(30)
                    ->placeholder('No description'),

                Tables\Columns\TextColumn::make('formatted_file_size')
                    ->label('Size')
                    ->alignRight(),

                Tables\Columns\IconColumn::make('is_primary')
                    ->label('Primary')
                    ->boolean()
                    ->trueIcon('heroicon-o-star')
                    ->trueColor('warning')
                    ->falseIcon('heroicon-o-minus')
                    ->falseColor('gray'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Uploaded')
                    ->dateTime('M j, Y g:i A')
                    ->since(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_primary')
                    ->label('Primary File'),
                
                Tables\Filters\SelectFilter::make('file_type')
                    ->label('File Type')
                    ->options([
                        'application/pdf' => 'PDF',
                        'application/msword' => 'Word Document',
                        'image/jpeg' => 'JPEG Image',
                        'image/png' => 'PNG Image',
                        'video/mp4' => 'MP4 Video',
                        'audio/mpeg' => 'MP3 Audio',
                    ]),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Upload File'),
            ])
            ->actions([
                Tables\Actions\Action::make('download')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('primary')
                    ->url(fn (ResourceFile $record) => route('admin.resource-files.download', $record))
                    ->openUrlInNewTab(),

                Tables\Actions\Action::make('preview')
                    ->label('Preview')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->url(fn (ResourceFile $record) => route('admin.resource-files.preview', $record))
                    ->openUrlInNewTab()
                    ->visible(fn (ResourceFile $record) => $record->isPreviewable()),

                Tables\Actions\EditAction::make(),

                Tables\Actions\DeleteAction::make()
                    ->before(function (ResourceFile $record) {
                        // Delete the actual file
                        $record->deleteFile();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(function ($records) {
                            // Delete all files before removing records
                            foreach ($records as $record) {
                                $record->deleteFile();
                            }
                        }),
                ]),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order');
    }
}