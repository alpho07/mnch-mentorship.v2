<?php

namespace App\Filament\Resources\ResourceResource\Pages;

use App\Filament\Resources\ResourceResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use App\Models\ResourceFile;

class ViewResource extends ViewRecord {

    protected static string $resource = ResourceResource::class;

    protected function getHeaderActions(): array {
        return [
            Actions\EditAction::make(),
                    Actions\Action::make('view_frontend')
                    ->label('View Live')
                    ->icon('heroicon-o-globe-alt')
                    ->url(fn() => route('resources.show', $this->getRecord()->slug))
                    ->openUrlInNewTab()
                    ->visible(fn() => $this->getRecord()->status === 'published'),
                    Actions\Action::make('download')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn() => route('admin.resources.download', $this->getRecord()))
                    ->openUrlInNewTab()
                    ->visible(fn() => $this->getRecord()->canBeDownloaded()),
                    Actions\Action::make('preview_file')
                    ->label('Preview File')
                    ->icon('heroicon-o-document-magnifying-glass')
                    ->url(fn() => route('admin.resources.preview', $this->getRecord()))
                    ->openUrlInNewTab()
                    ->visible(fn() => $this->getRecord()->isPreviewable()),
        ];
    }

    public function infolist(Infolist $infolist): Infolist {
        return $infolist
                        ->schema([
                            Infolists\Components\Section::make('Basic Information')
                            ->schema([
                                Infolists\Components\Grid::make(2)
                                ->schema([
                                    Infolists\Components\TextEntry::make('title')
                                    ->size('lg')
                                    ->weight('bold'),
                                    Infolists\Components\TextEntry::make('slug')
                                    ->copyable(),
                                    Infolists\Components\TextEntry::make('category.name')
                                    ->badge(),
                                    Infolists\Components\TextEntry::make('resourceType.name')
                                    ->label('Type')
                                    ->badge()
                                    ->color('success'),
                                    Infolists\Components\TextEntry::make('difficulty_level')
                                    ->badge()
                                    ->color('warning')
                                    ->formatStateUsing(fn(string $state): string => ucfirst($state)),
                                    Infolists\Components\TextEntry::make('status')
                                    ->badge()
                                    ->color(fn(string $state): string => match ($state) {
                                                'draft' => 'warning',
                                                'published' => 'success',
                                                'archived' => 'danger',
                                            }),
                                ]),
                                Infolists\Components\TextEntry::make('excerpt')
                                ->columnSpanFull(),
                            ]),
                            Infolists\Components\Section::make('Media & Files')
                            ->schema([
                                Infolists\Components\ImageEntry::make('featured_image')
                                ->disk('thumbnails')
                                ->height(200),
                                Infolists\Components\RepeatableEntry::make('files')
                                ->schema([
                                    Infolists\Components\Grid::make(4)
                                    ->schema([
                                        Infolists\Components\IconEntry::make('file_icon')
                                        ->getStateUsing(fn(ResourceFile $record) => $record->getFileIcon())
                                        ->size('lg'),
                                        Infolists\Components\TextEntry::make('original_name')
                                        ->weight('medium')
                                        ->copyable(),
                                        Infolists\Components\TextEntry::make('formatted_file_size')
                                        ->badge()
                                        ->color('gray'),
                                        Infolists\Components\IconEntry::make('is_primary')
                                        ->boolean()
                                        ->trueIcon('heroicon-o-star')
                                        ->trueColor('warning')
                                        ->falseIcon('heroicon-o-minus')
                                        ->falseColor('gray'),
                                    ]),
                                    Infolists\Components\TextEntry::make('description')
                                    ->placeholder('No description')
                                    ->columnSpanFull(),
                                    Infolists\Components\Actions::make([
                                        Infolists\Components\Actions\Action::make('download')
                                        ->icon('heroicon-o-arrow-down-tray')
                                        ->url(fn(ResourceFile $record) => route('admin.resource-files.download', $record))
                                        ->openUrlInNewTab(),
                                        Infolists\Components\Actions\Action::make('preview')
                                        ->icon('heroicon-o-eye')
                                        ->url(fn(ResourceFile $record) => route('admin.resource-files.preview', $record))
                                        ->openUrlInNewTab()
                                        ->visible(fn(ResourceFile $record) => $record->isPreviewable()),
                                    ])
                                    ->columnSpanFull(),
                                ])
                                ->contained(false)
                                ->columns(1),
                                Infolists\Components\TextEntry::make('external_url')
                                ->url(fn($record) => $record->external_url)
                                ->openUrlInNewTab()
                                ->visible(fn($record) => !empty($record->external_url)),
                            ])
                            ->collapsible(),
                            Infolists\Components\Section::make('Analytics & Engagement')
                            ->schema([
                                Infolists\Components\Grid::make(4)
                                ->schema([
                                    Infolists\Components\TextEntry::make('view_count')
                                    ->label('Views')
                                    ->numeric()
                                    ->color('primary'),
                                    Infolists\Components\TextEntry::make('download_count')
                                    ->label('Downloads')
                                    ->numeric()
                                    ->color('success'),
                                    Infolists\Components\TextEntry::make('like_count')
                                    ->label('Likes')
                                    ->numeric()
                                    ->color('danger'),
                                    Infolists\Components\TextEntry::make('comment_count')
                                    ->label('Comments')
                                    ->numeric()
                                    ->color('warning'),
                                ]),
                            ])
                            ->collapsible(),
                            Infolists\Components\Section::make('Publishing Details')
                            ->schema([
                                Infolists\Components\Grid::make(2)
                                ->schema([
                                    Infolists\Components\TextEntry::make('author.full_name')
                                    ->label('Author'),
                                    Infolists\Components\TextEntry::make('published_at')
                                    ->dateTime()
                                    ->placeholder('Not published'),
                                    Infolists\Components\TextEntry::make('created_at')
                                    ->dateTime(),
                                    Infolists\Components\TextEntry::make('updated_at')
                                    ->dateTime(),
                                ]),
                            ])
                            ->collapsible(),
                            Infolists\Components\Section::make('Tags')
                            ->schema([
                                Infolists\Components\TextEntry::make('tags.name')
                                ->badge()
                                ->separator(',')
                                ->placeholder('No tags assigned'),
                            ])
                            ->collapsible()
                            ->visible(fn($record) => $record->tags->isNotEmpty()),
                            Infolists\Components\Section::make('Content')
                            ->schema([
                                Infolists\Components\TextEntry::make('content')
                                ->html()
                                ->columnSpanFull(),
                            ])
                            ->collapsible(),
        ]);
    }
}
