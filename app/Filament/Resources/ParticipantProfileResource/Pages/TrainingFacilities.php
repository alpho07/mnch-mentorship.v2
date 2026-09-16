<?php
// TrainingFacilities.php
namespace App\Filament\Resources\ParticipantProfileResource\Pages;

use App\Filament\Resources\ParticipantProfileResource;
use App\Models\County;
use App\Models\Training;
use App\Models\Facility;
use App\Models\TrainingParticipant;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\Action;
use Filament\Actions;

class TrainingFacilities extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = ParticipantProfileResource::class;
    protected static string $view = 'filament.pages.training-facilities';

    public County $county;
    public Training $training;

    public function mount($county, $training): void
    {
        $this->county = County::findOrFail($this->county->id);
        $this->training = Training::findOrFail($this->training->id);
    }

    public function getTitle(): string
    {
        return "Facilities - {$this->training->title}";
    }

    public function getSubheading(): ?string
    {
        return "Participants from {$this->county->name} County";
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label('Back to Trainings')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(ParticipantProfileResource::getUrl('county-trainings', ['county' => $this->county->id])),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Facility::query()
                    ->whereHas('users.trainingParticipations', function ($query) {
                        $query->where('training_id', $this->training->id);
                    })
                    ->whereHas('subcounty', function ($query) {
                        $query->where('county_id', $this->county->id);
                    })
                    ->with(['subcounty', 'facilityType'])
            )
            ->columns([
                 TextColumn::make('subcounty.name')
                    ->label('Subcounty')
                    ->searchable(),
                
                TextColumn::make('name')
                    ->label('Facility Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('facilityType.name')
                    ->label('Type')
                    ->badge()
                    ->color('info'),

               

                TextColumn::make('participants_count')
                    ->label('No. of Participants')
                    ->getStateUsing(function ($record) {
                        return TrainingParticipant::where('training_id', $this->training->id)
                            ->whereHas('user', function ($query) use ($record) {
                                $query->where('facility_id', $record->id);
                            })
                            ->count();
                    })
                    ->alignCenter()
                    ->badge()
                    ->color('primary'),

                /*TextColumn::make('completion_rate')
                    ->label('Completion Rate')
                    ->getStateUsing(function ($record) {
                        $total = TrainingParticipant::where('training_id', $this->training->id)
                            ->whereHas('user', function ($query) use ($record) {
                                $query->where('facility_id', $record->id);
                            })
                            ->count();

                        $completed = TrainingParticipant::where('training_id', $this->training->id)
                            ->whereHas('user', function ($query) use ($record) {
                                $query->where('facility_id', $record->id);
                            })
                            ->where('completion_status', 'completed')
                            ->count();

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
                Action::make('view_participants')
                    ->label('View Participants')
                    ->icon('heroicon-o-users')
                    ->color('primary')
                    ->url(fn($record) => ParticipantProfileResource::getUrl(
                        'facility-participants',
                        [
                            'county' => $this->county->id,
                            'training' => $this->training->id,
                            'facility' => $record->id
                        ]
                    )),
            ])
            ->defaultSort('name')
            ->emptyStateHeading("No Facilities Found")
            ->emptyStateDescription("No facilities from {$this->county->name} County have participants in this training.");
    }
}