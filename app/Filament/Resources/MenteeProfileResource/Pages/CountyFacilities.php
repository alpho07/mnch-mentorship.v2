<?php

namespace App\Filament\Resources\MenteeProfileResource\Pages;

use App\Filament\Resources\MenteeProfileResource;
use App\Models\County;
use App\Models\Facility;
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

class CountyFacilities extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = MenteeProfileResource::class;
    protected static string $view = 'filament.pages.county-facilities';

    public County $county;

    public function mount( $county): void
    {
        $this->county = County::findOrFail($this->county->id);
    }

    public function getTitle(): string
    {
        return "Facilities with Mentorships - {$this->county->name} County";
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label('Back to Counties')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(MenteeProfileResource::getUrl('index')),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Facility::query()
                    ->whereHas('subcounty', function ($query) {
                        $query->where('county_id', $this->county->id);
                    })
                    ->whereHas('trainings', function ($query) {
                        $query->where('type', 'facility_mentorship');
                    })
                    ->with(['subcounty', 'facilityType', 'trainings' => function ($query) {
                        $query->where('type', 'facility_mentorship');
                    }])
            )
            ->columns([
                
                TextColumn::make('subcounty.name')
                    ->label('Subcounty')
                    ->searchable(),
                
                TextColumn::make('name')
                    ->label('Facility Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->wrap(),

                TextColumn::make('mfl_code')
                    ->label('MFL Code')
                    ->searchable()
                    ->copyable()
                    ->placeholder('No MFL Code'),

                TextColumn::make('facilityType.name')
                    ->label('Facility Type')
                    ->badge()
                    ->color('info'),

                

                /*TextColumn::make('mentorship_programs_count')
                    ->label('Mentorship Programs')
                    ->getStateUsing(function ($record) {
                        return $record->trainings()
                            ->where('type', 'facility_mentorship')
                            ->count();
                    })
                    ->alignCenter()
                    ->badge()
                    ->color('primary'),*/

                TextColumn::make('total_mentees_count')
                    ->label('Total Mentees')
                    ->getStateUsing(function ($record) {
                        return TrainingParticipant::whereHas('training', function ($query) use ($record) {
                            $query->where('type', 'facility_mentorship')
                                ->where('facility_id', $record->id);
                        })->distinct('user_id')->count();
                    })
                    ->alignCenter()
                    ->badge()
                    ->color('success'),

                /*TextColumn::make('active_programs_count')
                    ->label('Active Programs')
                    ->getStateUsing(function ($record) {
                        return $record->trainings()
                            ->where('type', 'facility_mentorship')
                            ->whereIn('status', ['ongoing', 'new'])
                            ->count();
                    })
                    ->alignCenter()
                    ->badge()
                    ->color('warning'),

                TextColumn::make('completion_rate')
                    ->label('Completion Rate')
                    ->getStateUsing(function ($record) {
                        $total = TrainingParticipant::whereHas('training', function ($query) use ($record) {
                            $query->where('type', 'facility_mentorship')
                                ->where('facility_id', $record->id);
                        })->count();

                        $completed = TrainingParticipant::whereHas('training', function ($query) use ($record) {
                            $query->where('type', 'facility_mentorship')
                                ->where('facility_id', $record->id);
                        })->where('completion_status', 'completed')->count();

                        return $total > 0 ? round(($completed / $total) * 100, 1) . '%' : '0%';
                    })
                    ->alignCenter()
                    ->badge()
                    ->color(function ($record) {
                        $rate = (float) str_replace('%', '', $record->completion_rate ?? '0');
                        if ($rate >= 80) return 'success';
                        if ($rate >= 60) return 'warning';
                        return 'danger';
                    }),*/
            ])
            ->actions([
                Action::make('view_mentorships')
                    ->label('View Mentorships')
                    ->icon('heroicon-o-academic-cap')
                    ->color('primary')
                    ->url(fn($record) => MenteeProfileResource::getUrl(
                        'facility-mentorships',
                        ['county' => $this->county->id, 'facility' => $record->id]
                    )),
            ])
            ->defaultSort('name')
            ->emptyStateHeading("No Facilities with Mentorship Programs")
            ->emptyStateDescription("No facilities in {$this->county->name} County are currently running mentorship programs.");
    }
}