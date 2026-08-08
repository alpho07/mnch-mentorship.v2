<?php

namespace App\Filament\Resources\RagDocumentResource\Pages;

use App\Filament\Resources\RagDocumentResource;
use Filament\Actions;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewRagDocument extends ViewRecord
{
    protected static string $resource = RagDocumentResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Status')
                ->schema([
                    Infolists\Components\TextEntry::make('title'),
                    Infolists\Components\TextEntry::make('status')->badge(),
                    Infolists\Components\TextEntry::make('error_message')
                        ->visible(fn ($record): bool => filled($record->error_message))
                        ->columnSpanFull(),
                ])
                ->columns(2),
            Infolists\Components\Section::make('File')
                ->schema([
                    Infolists\Components\TextEntry::make('original_name')->label('Original name'),
                    Infolists\Components\TextEntry::make('extension')->badge(),
                    Infolists\Components\TextEntry::make('formatted_size')
                        ->label('Size')
                        ->state(fn ($record): string => $record->formattedSize()),
                    Infolists\Components\TextEntry::make('sha256')->copyable(),
                    Infolists\Components\TextEntry::make('page_or_slide_count')->label('Pages/slides'),
                    Infolists\Components\TextEntry::make('chunk_count'),
                    Infolists\Components\TextEntry::make('processed_at')->dateTime(),
                    Infolists\Components\TextEntry::make('uploader.full_name')->label('Uploader'),
                ])
                ->columns(2),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('download')
                ->icon('heroicon-o-arrow-down-tray')
                ->url(fn (): string => route('rag.documents.download', $this->record))
                ->openUrlInNewTab()
                ->visible(fn (): bool => auth()->user()?->can('download', $this->record) && $this->record->fileExists()),
        ];
    }
}
