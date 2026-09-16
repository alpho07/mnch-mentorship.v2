<?php

// FacilityParticipants.php

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
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Actions\Action;
use Filament\Actions;

class FacilityParticipants extends Page implements HasTable {

    use InteractsWithTable;

    protected static string $resource = ParticipantProfileResource::class;
    protected static string $view = 'filament.pages.facility-participants';
    public County $county;
    public Training $training;
    public Facility $facility;

    public function mount($county, $training, $facility): void {
        $this->county = County::findOrFail($this->county->id);
        $this->training = Training::findOrFail($this->training->id);
        $this->facility = Facility::findOrFail($this->facility->id);
    }

    public function getTitle(): string {
        return "Participants - {$this->facility->name}";
    }

    public function getSubheading(): ?string {
        return "Training: {$this->training->title}";
    }

    protected function getHeaderActions(): array {
        return [
                    Actions\Action::make('back')
                    ->label('Back to Facilities')
                    ->icon('heroicon-o-arrow-left')
                    ->color('gray')
                    ->url(ParticipantProfileResource::getUrl(
                                    'training-facilities',
                                    ['county' => $this->county->id, 'training' => $this->training->id]
                            )),
        ];
    }

    public function table(Table $table): Table {
        return $table
                        ->query(
                                TrainingParticipant::query()
                                ->where('training_id', $this->training->id)
                                ->whereHas('user', function ($query) {
                                    $query->where('facility_id', $this->facility->id);
                                })
                                ->with(['user.department', 'user.cadre', 'assessmentResults'])
                        )
                        ->columns([
                            TextColumn::make('user.full_name')
                            ->label('Participant Name')
                            ->searchable(['name', 'first_name', 'last_name'])
                            ->weight('bold'),
                            TextColumn::make('user.phone')
                            ->label('Phone')
                            ->searchable()
                            ->copyable(),
                            TextColumn::make('user.department.name')
                            ->label('Department')
                            ->badge()
                            ->color('info'),
                            TextColumn::make('user.cadre.name')
                            ->label('Cadre')
                            ->badge()
                            ->color('success'),
                            /*BadgeColumn::make('attendance_status')
                            ->colors([
                                'secondary' => 'registered',
                                'warning' => 'attending',
                                'success' => 'completed',
                                'danger' => 'dropped',
                            ]),
                            TextColumn::make('assessment_score')
                            ->label('Score')
                            ->getStateUsing(function ($record) {
                                $calculation = $this->training->calculateOverallScore($record);
                                return $calculation['all_assessed'] ? $calculation['score'] . '%' : 'Incomplete';
                            })
                            ->badge()
                            ->color('primary'),
                            TextColumn::make('registration_date')
                            ->label('Enrolled')
                            ->date('M j, Y')
                            ->sortable(),*/
                        ])
                        ->actions([
                            Action::make('view_profile')
                            ->label('View Profile')
                            ->icon('heroicon-o-user')
                            ->color('primary')
                            ->url(fn($record) => ParticipantProfileResource::getUrl(
                                            'participant-detail',
                                            [
                                                'county' => $this->county->id,
                                                'training' => $this->training->id,
                                                'facility' => $this->facility->id,
                                                'participant' => $record->id
                                            ]
                                    )),
                        ])
                        ->defaultSort('registration_date', 'desc')
                        ->emptyStateHeading("No Participants Found")
                        ->emptyStateDescription("No participants from {$this->facility->name} are enrolled in this training.");
    }
}
