<?php

// MentorshipMentees.php  

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
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Actions\Action;
use Filament\Actions;

class MentorshipMentees extends Page implements HasTable {

    use InteractsWithTable;

    protected static string $resource = MenteeProfileResource::class;
    protected static string $view = 'filament.pages.mentorship-mentees';
    public County $county;
    public Facility $facility;
    public Training $mentorship;

    public function mount( $county,  $facility,  $mentorship): void {
        $this->county = County::findOrFail($this->county->id);
        $this->facility = Facility::findOrFail($this->facility->id);
        $this->mentorship = Training::findOrFail( $this->mentorship->id);
    }

    public function getTitle(): string {
        return "Mentees - {$this->mentorship->title}";
    }

    public function getSubheading(): ?string {
        return "Facility: {$this->facility->name}";
    }

    protected function getHeaderActions(): array {
        return [
                    Actions\Action::make('back')
                    ->label('Back to Mentorships')
                    ->icon('heroicon-o-arrow-left')
                    ->color('gray')
                    ->url(MenteeProfileResource::getUrl(
                                    'facility-mentorships',
                                    ['county' => $this->county->id, 'facility' => $this->facility->id]
                            )),
        ];
    }

    public function table(Table $table): Table {
        return $table
                        ->query(
                                TrainingParticipant::query()
                                ->where('training_id', $this->mentorship->id)
                                ->with(['user.department', 'user.cadre', 'assessmentResults'])
                        )
                        ->columns([
                            TextColumn::make('user.full_name')
                            ->label('Mentee Name')
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
                                $calculation = $this->mentorship->calculateOverallScore($record);
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
                            ->url(fn($record) => MenteeProfileResource::getUrl(
                                            'mentee-detail',
                                            [
                                                'county' => $this->county->id,
                                                'facility' => $this->facility->id,
                                                'mentorship' => $this->mentorship->id,
                                                'mentee' => $record->id
                                            ]
                                    )),
                        ])
                        ->defaultSort('registration_date', 'desc')
                        ->emptyStateHeading("No Mentees Found")
                        ->emptyStateDescription("No mentees are enrolled in this mentorship program.");
    }
}
