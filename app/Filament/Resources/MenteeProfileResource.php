<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MenteeProfileResource\Pages;
use App\Models\County;
use App\Models\Training;
use App\Models\TrainingParticipant;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\Action;
use Illuminate\Database\Eloquent\Builder;

class MenteeProfileResource extends Resource {

    protected static ?string $model = County::class;
    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';
    protected static ?string $navigationLabel = 'Mentor Profiles';
    protected static ?string $navigationGroup = 'Training Management';
    protected static ?int $navigationSort = 7;
    protected static ?string $slug = 'mentee-profiles';

    public static function shouldRegisterNavigation(): bool {
        return auth()->check() && auth()->user()->can('view_any_mentee::profile');}

    public function getTitle(): string {
        return 'Mentorship Counties';
    }

    public static function table(Table $table): Table {
        return $table
                        ->query(
                                County::query()
                                ->whereHas('facilities.trainings', function ($query) {
                                    $query->where('type', 'facility_mentorship1');
                                })
                        )
                        ->columns([
                            TextColumn::make('name')
                            ->label('Mentorship Counties')
                            ->searchable()
                            ->sortable()
                            ->weight('bold')
                            ->description(fn($record) => "{$record->subcounties()->count()} subcounties"),
                            TextColumn::make('mentorship_count')
                            ->label('No. of Mentorships')
                            ->getStateUsing(function ($record) {
                                return Training::where('type', 'facility_mentorship')
                                                ->whereHas('facility.subcounty', function ($query) use ($record) {
                                                    $query->where('county_id', $record->id);
                                                })
                                                ->count();
                            })
                            ->alignCenter()
                            ->badge()
                            ->color('primary'),
                            TextColumn::make('facilities_count')
                            ->label('Facilities with Mentorships')
                            ->getStateUsing(function ($record) {
                                return $record->facilities()
                                                ->whereHas('trainings', function ($query) {
                                                    $query->where('type', 'facility_mentorship');
                                                })
                                                ->count();
                            })
                            ->alignCenter()
                            ->badge()
                            ->color('info'),
                            TextColumn::make('total_mentees')
                            ->label('Total Mentees')
                            ->getStateUsing(function ($record) {
                                return TrainingParticipant::whereHas('training', function ($query) use ($record) {
                                            $query->where('type', 'facility_mentorship')
                                                    ->whereHas('facility.subcounty', function ($q) use ($record) {
                                                        $q->where('county_id', $record->id);
                                                    });
                                        })->distinct('user_id')->count();
                            })
                            ->alignCenter()
                            ->badge()
                            ->color('success'),
                                /* TextColumn::make('completion_rate')
                                  ->label('Completion Rate')
                                  ->getStateUsing(function ($record) {
                                  $total = TrainingParticipant::whereHas('training', function ($query) use ($record) {
                                  $query->where('type', 'facility_mentorship')
                                  ->whereHas('facility.subcounty', function ($q) use ($record) {
                                  $q->where('county_id', $record->id);
                                  });
                                  })->count();

                                  $completed = TrainingParticipant::whereHas('training', function ($query) use ($record) {
                                  $query->where('type', 'facility_mentorship')
                                  ->whereHas('facility.subcounty', function ($q) use ($record) {
                                  $q->where('county_id', $record->id);
                                  });
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
                            Action::make('view_facilities')
                            ->label('View Facilities')
                            ->icon('heroicon-o-building-office')
                            ->color('primary')
                            ->url(fn($record) => static::getUrl('county-facilities', ['county' => $record->id])),
                        ])
                        ->defaultSort('name')
                        ->emptyStateHeading('No Counties with Mentorships')
                        ->emptyStateDescription('Counties will appear here once they have facilities running mentorship programs.');
    }

    public static function getPages(): array {
        return [
            'index' => Pages\ListMenteeProfiles::route('/'),
            'county-facilities' => Pages\CountyFacilities::route('/{county}/facilities'),
            'facility-mentorships' => Pages\FacilityMentorships::route('/{county}/facility/{facility}/mentorships'),
            'mentorship-mentees' => Pages\MentorshipMentees::route('/{county}/facility/{facility}/mentorship/{mentorship}/mentees'),
            'mentee-detail' => Pages\MenteeDetail::route('/{county}/facility/{facility}/mentorship/{mentorship}/mentee/{mentee}'),
        ];
    }

    public static function canCreate(): bool {
        return false;
    }
}
