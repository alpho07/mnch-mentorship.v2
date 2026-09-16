<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ParticipantProfileResource\Pages;
use App\Models\County;
use App\Models\Training;
use App\Models\TrainingParticipant;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\Action;
use Illuminate\Database\Eloquent\Builder;

class ParticipantProfileResource extends Resource {

    protected static ?string $model = County::class;
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationLabel = 'Participant/Mentee Profiles';
    protected static ?string $navigationGroup = 'Training Management';
    protected static ?int $navigationSort = 6;
    protected static ?string $slug = 'participant-profiles';

    public static function shouldRegisterNavigation(): bool {
        return auth()->check() && auth()->user()->can('view_any_participant::profile');}

    public static function table(Table $table): Table {
        return $table
                        ->query(
                                County::query()
                                ->whereHas('facilities.users.trainingParticipations.training', function ($query) {
                                    $query->where('type', 'global_training');
                                })
                        )
                        ->columns([
                            TextColumn::make('name')
                            ->label('County')
                            ->searchable()
                            ->sortable()
                            ->weight('bold')
                            ->description(fn($record) => "{$record->subcounties()->count()} subcounties"),
                            TextColumn::make('global_trainings_count')
                            ->label('No.of Trainings')
                            ->getStateUsing(function ($record) {
                                return Training::where('type', 'global_training')
                                                ->whereHas('participants.user.facility.subcounty', function ($query) use ($record) {
                                                    $query->where('county_id', $record->id);
                                                })
                                                ->distinct()
                                                ->count();
                            })
                            ->alignCenter()
                            ->badge()
                            ->color('primary'),
                            TextColumn::make('total_participants')
                            ->label('No. of Participants')
                            ->getStateUsing(function ($record) {
                                return TrainingParticipant::whereHas('user.facility.subcounty', function ($query) use ($record) {
                                            $query->where('county_id', $record->id);
                                        })->whereHas('training', function ($query) {
                                            $query->where('type', 'global_training');
                                        })->distinct('user_id')->count();
                            })
                            ->alignCenter()
                            ->badge()
                            ->color('success'),
                                /* TextColumn::make('completion_rate')
                                  ->label('Completion Rate')
                                  ->getStateUsing(function ($record) {
                                  $total = TrainingParticipant::whereHas('user.facility.subcounty', function ($query) use ($record) {
                                  $query->where('county_id', $record->id);
                                  })->whereHas('training', function ($query) {
                                  $query->where('type', 'global_training');
                                  })->count();

                                  $completed = TrainingParticipant::whereHas('user.facility.subcounty', function ($query) use ($record) {
                                  $query->where('county_id', $record->id);
                                  })->whereHas('training', function ($query) {
                                  $query->where('type', 'global_training');
                                  })->where('completion_status', 'completed')->count();

                                  return $total > 0 ? round(($completed / $total) * 100, 1) . '%' : '0%';
                                  })
                                  ->alignCenter()
                                  ->badge()
                                  ->color(function ($record) {
                                  $rate = (float) str_replace('%', '', $record->completion_rate ?? '0');
                                  if ($rate >= 80)
                                  return 'success';
                                  if ($rate >= 60)
                                  return 'warning';
                                  return 'danger';
                                  }), */
                        ])
                        ->actions([
                            Action::make('view_trainings')
                            ->label('View Trainings')
                            ->icon('heroicon-o-eye')
                            ->color('primary')
                            ->url(fn($record) => static::getUrl('county-trainings', ['county' => $record->id])),
                        ])
                        ->defaultSort('name')
                        ->emptyStateHeading('No Counties with Global Training Participants')
                        ->emptyStateDescription('Counties will appear here once they have participants in global training programs.');
    }

    public static function getPages(): array {
        return [
            'index' => Pages\ListParticipantProfiles::route('/'),
            'county-trainings' => Pages\CountyTrainings::route('/{county}/trainings'),
            'training-facilities' => Pages\TrainingFacilities::route('/{county}/training/{training}/facilities'),
            'facility-participants' => Pages\FacilityParticipants::route('/{county}/training/{training}/facility/{facility}/participants'),
            'participant-detail' => Pages\ParticipantDetail::route('/{county}/training/{training}/facility/{facility}/participant/{participant}'),
        ];
    }

    public static function canCreate(): bool {
        return false;
    }
}
