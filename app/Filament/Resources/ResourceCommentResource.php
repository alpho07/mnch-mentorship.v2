<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ResourceCommentResource\Pages;
use App\Models\ResourceComment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;

class ResourceCommentResource extends Resource {

    protected static ?string $model = ResourceComment::class;
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static ?string $navigationGroup = 'Content Management';
    protected static ?int $navigationSort = 5;
    protected static ?string $recordTitleAttribute = 'content';

    public static function shouldRegisterNavigation(): bool {
        return auth()->check() && auth()->user()->can('view_any_resource::comment');}

    public static function form(Form $form): Form {
        return $form
                        ->schema([
                            Forms\Components\Section::make('Comment Details')
                            ->schema([
                                Forms\Components\Select::make('resource_id')
                                ->relationship('resource', 'title')
                                ->required()
                                ->searchable()
                                ->columnSpanFull(),
                                Forms\Components\Select::make('user_id')
                                ->relationship('user', 'first_name')
                                ->searchable()
                                ->getOptionLabelFromRecordUsing(fn($record) => $record->full_name)
                                ->label('User (if authenticated)'),
                                Forms\Components\Select::make('parent_id')
                                ->relationship('parent', 'content')
                                ->searchable()
                                ->getOptionLabelFromRecordUsing(fn($record) => Str::limit($record->content, 50))
                                ->label('Reply to Comment'),
                                Forms\Components\TextInput::make('author_name')
                                ->maxLength(255)
                                ->label('Guest Name')
                                ->hint('For non-authenticated users'),
                                Forms\Components\TextInput::make('author_email')
                                ->email()
                                ->maxLength(255)
                                ->label('Guest Email')
                                ->hint('For non-authenticated users'),
                                Forms\Components\Textarea::make('content')
                                ->required()
                                ->rows(4)
                                ->columnSpanFull(),
                                Forms\Components\Toggle::make('is_approved')
                                ->default(false)
                                ->label('Approved'),
                                Forms\Components\TextInput::make('ip_address')
                                ->maxLength(45)
                                ->disabled()
                                ->label('IP Address'),
                            ])
                            ->columns(2),
        ]);
    }

    public static function table(Table $table): Table {
        return $table
                        ->columns([
                            Tables\Columns\TextColumn::make('resource.title')
                            ->label('Resource')
                            ->searchable()
                            ->sortable()
                            ->limit(30)
                            ->tooltip(function (Tables\Columns\TextColumn $column): ?string {
                                $state = $column->getState();
                                return strlen($state) > 30 ? $state : null;
                            }),
                            Tables\Columns\TextColumn::make('content')
                            ->searchable()
                            ->limit(50)
                            ->tooltip(function (Tables\Columns\TextColumn $column): ?string {
                                $state = $column->getState();
                                return strlen($state) > 50 ? $state : null;
                            }),
                            Tables\Columns\TextColumn::make('author')
                            ->label('Author')
                            ->getStateUsing(fn(ResourceComment $record): string =>
                                    $record->user ? $record->user->full_name :
                                    ($record->author_name ?: 'Anonymous')
                            )
                            ->searchable(['author_name'])
                            ->sortable(),
                            Tables\Columns\IconColumn::make('is_reply')
                            ->label('Reply')
                            ->getStateUsing(fn(ResourceComment $record): bool => !is_null($record->parent_id))
                            ->boolean()
                            ->trueIcon('heroicon-o-arrow-turn-down-right')
                            ->falseIcon('heroicon-o-chat-bubble-left')
                            ->trueColor('warning')
                            ->falseColor('primary'),
                            Tables\Columns\BadgeColumn::make('is_approved')
                            ->label('Status')
                            ->getStateUsing(fn(ResourceComment $record): string =>
                                    $record->is_approved ? 'approved' : 'pending'
                            )
                            ->colors([
                                'success' => 'approved',
                                'warning' => 'pending',
                            ]),
                            Tables\Columns\TextColumn::make('created_at')
                            ->dateTime()
                            ->sortable()
                            ->since(),
                            Tables\Columns\TextColumn::make('ip_address')
                            ->label('IP')
                            ->toggleable(isToggledHiddenByDefault: true),
                        ])
                        ->filters([
                            Tables\Filters\SelectFilter::make('resource_id')
                            ->relationship('resource', 'title')
                            ->label('Resource')
                            ->searchable(),
                            Tables\Filters\Filter::make('is_approved')
                            ->query(fn(Builder $query): Builder => $query->where('is_approved', true))
                            ->label('Approved Only'),
                            Tables\Filters\Filter::make('pending')
                            ->query(fn(Builder $query): Builder => $query->where('is_approved', false))
                            ->label('Pending Approval'),
                            Tables\Filters\Filter::make('replies')
                            ->query(fn(Builder $query): Builder => $query->whereNotNull('parent_id'))
                            ->label('Replies Only'),
                            Tables\Filters\Filter::make('authenticated_users')
                            ->query(fn(Builder $query): Builder => $query->whereNotNull('user_id'))
                            ->label('From Authenticated Users'),
                            Tables\Filters\TrashedFilter::make(),
                        ])
                        ->actions([
                            Tables\Actions\ViewAction::make(),
                            Tables\Actions\EditAction::make(),
                            Tables\Actions\Action::make('approve')
                            ->icon('heroicon-o-check-circle')
                            ->color('success')
                            ->action(fn(ResourceComment $record) => $record->update(['is_approved' => true]))
                            ->visible(fn(ResourceComment $record): bool => !$record->is_approved),
                            Tables\Actions\Action::make('unapprove')
                            ->icon('heroicon-o-x-circle')
                            ->color('warning')
                            ->action(fn(ResourceComment $record) => $record->update(['is_approved' => false]))
                            ->visible(fn(ResourceComment $record): bool => $record->is_approved),
                            Tables\Actions\DeleteAction::make(),
                            Tables\Actions\RestoreAction::make(),
                        ])
                        ->bulkActions([
                            Tables\Actions\BulkActionGroup::make([
                                Tables\Actions\DeleteBulkAction::make(),
                                Tables\Actions\RestoreBulkAction::make(),
                                Tables\Actions\BulkAction::make('approve')
                                ->label('Approve')
                                ->icon('heroicon-o-check-circle')
                                ->action(fn($records) => $records->each(fn($record) =>
                                                $record->update(['is_approved' => true])
                                        ))
                                ->color('success'),
                                Tables\Actions\BulkAction::make('unapprove')
                                ->label('Mark as Pending')
                                ->icon('heroicon-o-clock')
                                ->action(fn($records) => $records->each(fn($record) =>
                                                $record->update(['is_approved' => false])
                                        ))
                                ->color('warning'),
                            ]),
                        ])
                        ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array {
        return [
                //
        ];
    }

    public static function getPages(): array {
        return [
            'index' => Pages\ListResourceComments::route('/'),
            'create' => Pages\CreateResourceComment::route('/create'),
            //'view' => Pages\ViewResourceComment::route('/{record}'),
            'edit' => Pages\EditResourceComment::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder {
        return parent::getEloquentQuery()
                        ->withoutGlobalScopes([
                            SoftDeletingScope::class,
        ]);
    }
}
