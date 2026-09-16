<?php

namespace App\Filament\Resources\ParticipantProfileResource\Pages;

use App\Filament\Resources\ParticipantProfileResource;
use App\Models\County;
use App\Models\Training;
use App\Models\TrainingParticipant;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\Action;
use Filament\Actions;
use Illuminate\Database\Eloquent\Builder;

class CountyTrainings extends Page implements HasTable {

    use InteractsWithTable;

    protected static string $resource = ParticipantProfileResource::class;
    protected static string $view = 'filament.pages.county-trainings';
    public County $county;

    public function mount($county): void {
        //dd($this->county->id);
        $this->county = County::findOrFail( $this->county->id);
    }

    public function getTitle(): string {
        return "MOH Trainings - {$this->county->name} County";
    }

    protected function getHeaderActions(): array {
        return [
                    Actions\Action::make('back')
                    ->label('Back to Counties')
                    ->icon('heroicon-o-arrow-left')
                    ->color('gray')
                    ->url(ParticipantProfileResource::getUrl('index')),
        ];
    }

    public function table(Table $table): Table {
        return $table
                        ->query(
                                Training::query()
                                ->where('type', 'global_training')
                                ->whereHas('participants.user.facility.subcounty', function ($query) {
                                    $query->where('county_id', $this->county->id);
                                })
                                ->with(['participants.user.facility'])
                        )
                        ->columns([
                            TextColumn::make('title')
                            ->label('Training Name')
                            ->searchable()
                            ->sortable()
                            ->weight('bold')
                            ->wrap(),
                            TextColumn::make('identifier')
                            ->label('Training ID')
                            ->searchable()
                            ->copyable(),
                            TextColumn::make('start_date')
                            ->label('Year')
                            ->date('Y')
                            ->sortable(),
                            /*TextColumn::make('end_date')
                            ->label('End Date')
                            ->date('M j, Y')
                            ->sortable(),*/
                              TextColumn::make('facilities_count')
                            ->label('No. of Facilities')
                            ->getStateUsing(function ($record) {
                                return $record->participants()
                                                ->whereHas('user.facility.subcounty', function ($query) {
                                                    $query->where('county_id', $this->county->id);
                                                })
                                                ->join('users', 'training_participants.user_id', '=', 'users.id')
                                                ->distinct('users.facility_id')
                                                ->count();
                            })
                            ->alignCenter()
                            ->badge()
                            ->color('info'),
                            TextColumn::make('participants_from_county')
                            ->label('No of Participants')
                            ->getStateUsing(function ($record) {
                                return $record->participants()
                                                ->whereHas('user.facility.subcounty', function ($query) {
                                                    $query->where('county_id', $this->county->id);
                                                })
                                                ->count();
                            })
                            ->alignCenter()
                            ->badge()
                            ->color('primary'),
                          
                            /*TextColumn::make('completion_rate')
                            ->label('Completion Rate')
                            ->getStateUsing(function ($record) {
                                $total = $record->participants()
                                        ->whereHas('user.facility.subcounty', function ($query) {
                                            $query->where('county_id', $this->county->id);
                                        })
                                        ->count();

                                $completed = $record->participants()
                                        ->whereHas('user.facility.subcounty', function ($query) {
                                            $query->where('county_id', $this->county->id);
                                        })
                                        ->where('completion_status', 'completed')
                                        ->count();

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
                            }),
                            TextColumn::make('status')
                            ->badge()
                            ->colors([
                                'secondary' => 'new',
                                'warning' => 'repeat',
                                'success' => 'ongoing',
                                'primary' => 'completed',
                                'danger' => 'cancelled',
                            ]),*/
                        ])
                        ->actions([
                            Action::make('view_facilities')
                            ->label('View Facilities')
                            ->icon('heroicon-o-building-office')
                            ->color('primary')
                            ->url(fn($record) => ParticipantProfileResource::getUrl(
                                            'training-facilities',
                                            ['county' => $this->county->id, 'training' => $record->id]
                                    )),
                        ])
                        ->defaultSort('start_date', 'desc')
                        ->emptyStateHeading("No Global Trainings Found")
                        ->emptyStateDescription("No global training programs have participants from {$this->county->name} County.");
    }
}
