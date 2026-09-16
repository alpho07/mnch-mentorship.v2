<?php

namespace App\Filament\Resources\MentorshipResource\Pages;

use App\Filament\Resources\MentorshipTrainingResource;
use App\Models\ClassParticipant;
use App\Models\Facility;
use App\Models\MenteeModuleProgress;
use App\Models\MentorshipClass;
use App\Models\Training;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AttendanceReportPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = MentorshipTrainingResource::class;

    protected static string $view = 'filament.pages.attendance-report';

    protected static bool $shouldRegisterNavigation = false;

    // Filter state
    public ?int $filterTrainingId = null;

    public ?int $filterFacilityId = null;

    public ?string $filterStatus = null;

    public function getTitle(): string
    {
        return 'Attendance Report';
    }

    public function getSubheading(): ?string
    {
        $user = auth()->user();
        if ($user->hasRole(['super_admin', 'admin', 'division', 'national_mentor_lead'])) {
            return 'All mentorships · Use filters to narrow down';
        }

        return 'Your mentorship classes';
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('back')
                ->label('Back')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(MentorshipTrainingResource::getUrl('index')),
        ];
    }

    public function table(Table $table): Table
    {
        $user = auth()->user();
        $isAdmin = $user->hasRole(['super_admin', 'admin', 'division', 'national_mentor_lead']);

        return $table
            ->query(
                MentorshipClass::query()
                    ->with([
                        'training.program',
                        'training.facility',
                        'training.mentor',
                        'classModules',
                        'participants',
                    ])
                    ->when(! $isAdmin, fn (Builder $q) => $q->whereHas('training', fn ($t) => $t->forMentorOrCoMentor($user->id)
                    )
                    )
                    ->when($this->filterTrainingId, fn ($q) => $q->where('training_id', $this->filterTrainingId)
                    )
                    ->when($this->filterFacilityId, fn ($q) => $q->whereHas('training', fn ($t) => $t->where('facility_id', $this->filterFacilityId)
                    )
                    )
                    ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus)
                    )
            )
            ->columns([
                Tables\Columns\TextColumn::make('training.facility.name')
                    ->label('Facility')
                    ->sortable()
                    ->searchable()
                    ->visible($isAdmin),
                Tables\Columns\TextColumn::make('training.title')
                    ->label('Mentorship')
                    ->sortable()
                    ->searchable()
                    ->description(fn (MentorshipClass $r) => $r->training->program->name ?? ''),
                Tables\Columns\TextColumn::make('name')
                    ->label('Class')
                    ->sortable()
                    ->searchable()
                    ->weight('bold'),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'gray' => 'draft',
                        'warning' => 'active',
                        'success' => 'completed',
                        'danger' => 'cancelled',
                    ])
                    ->formatStateUsing(fn ($state) => ucfirst($state)),
                Tables\Columns\TextColumn::make('participants_count')
                    ->label('Mentees')
                    ->getStateUsing(fn (MentorshipClass $r) => ClassParticipant::where('mentorship_class_id', $r->id)->count()
                    )
                    ->badge()->color('primary'),
                Tables\Columns\TextColumn::make('completed_count')
                    ->label('Completed')
                    ->getStateUsing(fn (MentorshipClass $r) => ClassParticipant::where('mentorship_class_id', $r->id)
                        ->where('status', 'completed')->count()
                    )
                    ->badge()->color('success'),
                Tables\Columns\TextColumn::make('avg_attendance')
                    ->label('Avg Attendance')
                    ->getStateUsing(function (MentorshipClass $r) {
                        $moduleIds = $r->classModules->pluck('id');
                        if ($moduleIds->isEmpty()) {
                            return '—';
                        }

                        $total = ClassParticipant::where('mentorship_class_id', $r->id)->count();
                        if ($total === 0) {
                            return '—';
                        }

                        $confirmed = MenteeModuleProgress::whereIn('class_module_id', $moduleIds)
                            ->whereIn('status', ['in_progress', 'completed'])
                            ->count();

                        $modules = $moduleIds->count();

                        return round(($confirmed / ($total * $modules)) * 100).'%';
                    })
                    ->badge()
                    ->color(fn (MentorshipClass $r) => 'gray'),
                Tables\Columns\TextColumn::make('start_date')
                    ->label('Period')
                    ->getStateUsing(fn (MentorshipClass $r) => ($r->start_date ? \Carbon\Carbon::parse($r->start_date)->format('d M Y') : '—').
                            ' → '.
                            ($r->end_date ? \Carbon\Carbon::parse($r->end_date)->format('d M Y') : 'Ongoing')
                    )
                    ->color('gray'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'active' => 'Active',
                        'completed' => 'Completed',
                    ]),
                Tables\Filters\SelectFilter::make('training_id')
                    ->label('Mentorship')
                    ->visible($isAdmin)
                    ->options(
                        Training::where('type', 'facility_mentorship')
                            ->pluck('title', 'id')
                    )
                    ->query(fn (Builder $q, array $data) => $data['value'] ? $q->where('training_id', $data['value']) : $q
                    ),
                Tables\Filters\SelectFilter::make('facility_id')
                    ->label('Facility')
                    ->visible($isAdmin)
                    ->options(Facility::pluck('name', 'id'))
                    ->query(fn (Builder $q, array $data) => $data['value'] ? $q->whereHas('training', fn ($t) => $t->where('facility_id', $data['value'])) : $q
                    ),
            ])
            ->actions([
                Tables\Actions\Action::make('view_report')
                    ->label('View Report')
                    ->icon('heroicon-o-document-chart-bar')
                    ->color('primary')
                    ->url(fn (MentorshipClass $r) => route('reports.class.html', $r->id))
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('download_pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->url(fn (MentorshipClass $r) => route('reports.class.pdf', $r->id))
                    ->openUrlInNewTab(),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No classes found')
            ->emptyStateDescription('Adjust filters to find classes.')
            ->emptyStateIcon('heroicon-o-document-chart-bar');
    }
}
