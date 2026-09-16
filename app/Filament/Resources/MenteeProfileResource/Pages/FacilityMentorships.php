<?php
// FacilityMentorships.php
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

class FacilityMentorships extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = MenteeProfileResource::class;
    protected static string $view = 'filament.pages.facility-mentorships';

    public County $county;
    public Facility $facility;

    public function mount( $county,  $facility): void
    {
        $this->county = County::findOrFail($this->county->id);
        $this->facility = Facility::findOrFail($this->facility->id);
    }

    public function getTitle(): string
    {
        return "Mentorship - {$this->facility->name}";
    }

    public function getSubheading(): ?string
    {
        return "County: {$this->county->name} | MFL: {$this->facility->mfl_code}";
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label('Back to Facilities')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(MenteeProfileResource::getUrl('county-facilities', ['county' => $this->county->id])),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Training::query()
                    ->where('type', 'facility_mentorship')
                    ->where('facility_id', $this->facility->id)
                    ->with(['participants.user'])
            )
            ->columns([
                TextColumn::make('title')
                    ->label('Mentorship Program')
                    ->searchable()
                    ->weight('bold')
                    ->wrap(),

                TextColumn::make('identifier')
                    ->label('Mentorship ID')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('start_date')
                    ->label('year')
                    ->date('Y')
                    ->sortable(),

                /*TextColumn::make('end_date')
                    ->label('End Date')
                    ->date('M j, Y')
                    ->sortable(),

                TextColumn::make('mentees_count')
                    ->label('Mentees')
                    ->counts('participants')
                    ->alignCenter()
                    ->badge()
                    ->color('primary'),

                TextColumn::make('completion_rate')
                    ->label('Completion Rate')
                    ->getStateUsing(function ($record) {
                        $total = $record->participants()->count();
                        $completed = $record->participants()->where('completion_status', 'completed')->count();
                        return $total > 0 ? round(($completed / $total) * 100, 1) . '%' : '0%';
                    })
                    ->alignCenter()
                    ->badge()
                    ->color(function ($record) {
                        $rate = (float) str_replace('%', '', $record->completion_rate ?? '0');
                        if ($rate >= 80) return 'success';
                        if ($rate >= 60) return 'warning';
                        return 'danger';
                    }),

                BadgeColumn::make('status')
                    ->colors([
                        'secondary' => 'new',
                        'success' => 'ongoing',
                        'primary' => 'completed',
                        'danger' => 'cancelled',
                    ]),*/
            ])
            ->actions([
                Action::make('view_mentees')
                    ->label('View Mentees')
                    ->icon('heroicon-o-users')
                    ->color('primary')
                    ->url(fn($record) => MenteeProfileResource::getUrl(
                        'mentorship-mentees',
                        [
                            'county' => $this->county->id,
                            'facility' => $this->facility->id,
                            'mentorship' => $record->id
                        ]
                    )),
            ])
            ->defaultSort('start_date', 'desc')
            ->emptyStateHeading("No Mentorship Programs")
            ->emptyStateDescription("This facility has not run any mentorship programs yet.");
    }
}