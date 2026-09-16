<?php

namespace App\Filament\Resources\MentorshipResource\Pages;

use App\Filament\Resources\MentorshipTrainingResource;
use App\Models\ClassModule;
use App\Models\MentorshipClass;
use App\Models\Program;
use App\Models\Training;
use Filament\Actions;
use Filament\Infolists;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\Page;
use Filament\Support\Enums\FontWeight;

class ModuleSummary extends Page implements HasInfolists
{
    use InteractsWithInfolists;

    protected static string $resource = MentorshipTrainingResource::class;

    protected static string $view = 'filament.pages.module-summary';

    protected static bool $shouldRegisterNavigation = false;

    public Training $training;

    public MentorshipClass $class;

    public ClassModule $module;

    public function mount(Training $training, MentorshipClass $class, ClassModule $module): void
    {
        $this->training = $training;
        $this->class = $class;
        $this->module = $module->load([
            'programModule.parent',
            'programModule.activities',
            'mentorshipClass',
            'sessions.facilitator',
            'menteeProgress.classParticipant.user.facility',
            'activityEnrollments.classParticipant.user',
            'activityEnrollments.activity',
        ]);
    }

    private function isEmonc(): bool
    {
        $program = Program::find($this->training->program_id);

        return $program
            && str_contains(strtolower($program->name), 'maternal')
            && str_contains(strtolower($program->name), 'emonc');
    }

    public function getTitle(): string
    {
        return 'Module Summary';
    }

    public function getSubheading(): ?string
    {
        return $this->module->programModule->name;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('manage_sessions')
                ->label('Manage Sessions')
                ->icon('heroicon-o-calendar')
                ->color('primary')
                ->visible(fn () => ! $this->isEmonc())
                ->url(fn () => MentorshipTrainingResource::getUrl('module-sessions', [
                    'training' => $this->training->id,
                    'class' => $this->class->id,
                    'module' => $this->module->id,
                ])),
            Actions\Action::make('manage_mentees')
                ->label('Manage Mentees')
                ->icon('heroicon-o-users')
                ->color('success')
                ->url(fn () => MentorshipTrainingResource::getUrl('class-mentees', [
                    'training' => $this->training->id,
                    'class' => $this->class->id,
                    // 'module' => $this->module->id,
                ])),
            Actions\Action::make('back')
                ->label('Back to Modules')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn () => MentorshipTrainingResource::getUrl('classes', [
                    'record' => $this->training,
                ]).'?class='.$this->class->id),
        ];
    }

    public function summaryInfolist(Infolist $infolist): Infolist
    {
        $enrolledCount = $this->module->menteeProgress()->count();
        $completedCount = $this->module->menteeProgress()->where('status', 'completed')->count();
        $inProgressCount = $this->module->menteeProgress()->where('status', 'in_progress')->count();
        $exemptedCount = $this->module->menteeProgress()->where('status', 'exempted')->count();
        $notStartedCount = $this->module->menteeProgress()->where('status', 'not_started')->count();

        $totalSessions = $this->module->sessions()->count();
        $completedSessions = $this->module->sessions()->where('status', 'completed')->count();
        $scheduledSessions = $this->module->sessions()->where('status', 'scheduled')->count();

        $avgAttendance = $this->module->menteeProgress()
            ->whereNotNull('attendance_percentage')
            ->avg('attendance_percentage') ?? 0;

        return $infolist
            ->record($this->module)
            ->schema([
                Infolists\Components\Section::make('Location & Program Information')
                    ->icon('heroicon-o-map-pin')
                    ->schema([
                        Infolists\Components\Grid::make(4)
                            ->schema([
                                Infolists\Components\TextEntry::make('training.facility.name')
                                    ->label('County')
                                    ->icon('heroicon-o-map')
                                    ->weight(FontWeight::Bold)
                                    ->getStateUsing(fn () => $this->training->facility?->county?->name ?? 'N/A'),
                                Infolists\Components\TextEntry::make('training.facility.subcounty.name')
                                    ->label('Sub-County')
                                    ->icon('heroicon-o-map-pin')
                                    ->getStateUsing(fn () => $this->training->facility?->sub_county?->name ?? 'N/A'),
                                Infolists\Components\TextEntry::make('training.program')
                                    ->label('Mentorship Program')
                                    ->icon('heroicon-o-academic-cap')
                                    ->weight(FontWeight::Bold)
                                    ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                                    ->getStateUsing(fn () => $this->training->program?->name ?? 'N/A')
                                    ->columnSpan(2),
                            ]),
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('training.start_date')
                                    ->label('Mentorship Start Date')
                                    ->icon('heroicon-o-calendar')
                                    ->date('M j, Y')
                                    ->getStateUsing(fn () => $this->training->start_date),
                                Infolists\Components\TextEntry::make('training.end_date')
                                    ->label('Mentorship End Date')
                                    ->icon('heroicon-o-calendar')
                                    ->date('M j, Y')
                                    ->getStateUsing(fn () => $this->training->end_date),
                                Infolists\Components\TextEntry::make('training.facility')
                                    ->label('Facility')
                                    ->icon('heroicon-o-building-office')
                                    ->weight(FontWeight::Bold)
                                    ->getStateUsing(fn () => $this->training->facility?->name ?? 'N/A'),
                            ]),
                    ]),
                Infolists\Components\Section::make('Mentorship Details')
                    ->icon('heroicon-o-user-group')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('class.name')
                                    ->label('Cohort/Class Name')
                                    ->icon('heroicon-o-folder')
                                    ->weight(FontWeight::Bold)
                                    ->getStateUsing(fn () => $this->class->name),
                                Infolists\Components\TextEntry::make('class.dates')
                                    ->label('Cohort Period')
                                    ->icon('heroicon-o-calendar-days')
                                    ->getStateUsing(fn () => $this->class->start_date?->format('M j, Y').' - '.
                                            ($this->class->end_date?->format('M j, Y') ?? 'Ongoing')
                                    ),
                                Infolists\Components\TextEntry::make('training.mentor')
                                    ->label('Lead Mentor')
                                    ->icon('heroicon-o-user')
                                    ->weight(FontWeight::Bold)
                                    ->getStateUsing(fn () => $this->training->mentor?->full_name ?? 'N/A'),
                            ]),
                    ]),
                Infolists\Components\Section::make('Module Details')
                    ->icon('heroicon-o-book-open')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('training.program')
                                    ->label('Program')
                                    ->icon('heroicon-o-academic-cap')
                                    ->weight(FontWeight::Bold)
                                    ->getStateUsing(fn () => $this->training->program?->name ?? 'N/A'),
                                Infolists\Components\TextEntry::make('programModule.name')
                                    ->label('Module Name')
                                    ->icon('heroicon-o-rectangle-stack')
                                    ->weight(FontWeight::Bold)
                                    ->size(Infolists\Components\TextEntry\TextEntrySize::Large),
                                Infolists\Components\TextEntry::make('programModule.parent.name')
                                    ->label('Track')
                                    ->icon('heroicon-o-arrow-path')
                                    ->weight(FontWeight::Medium)
                                    ->placeholder('N/A')
                                    ->visible(fn () => $this->module->programModule?->parent_id !== null),
                                Infolists\Components\TextEntry::make('status')
                                    ->label('Module Status')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'not_started' => 'gray',
                                        'in_progress' => 'warning',
                                        'completed' => 'success',
                                        default => 'gray',
                                    })
                                    ->formatStateUsing(fn (string $state): string => match ($state) {
                                        'not_started' => 'Not Started',
                                        'in_progress' => 'In Progress',
                                        'completed' => 'Completed',
                                        default => ucfirst($state),
                                    }
                                    ),
                            ]),
                        Infolists\Components\TextEntry::make('programModule.description')
                            ->label('Description')
                            ->markdown()
                            ->columnSpanFull()
                            ->default('No description available'),
                        Infolists\Components\Grid::make(4)
                            ->schema([
                                Infolists\Components\TextEntry::make('started_at')
                                    ->label('Module Started')
                                    ->icon('heroicon-o-play')
                                    ->dateTime('M j, Y')
                                    ->placeholder('Not started yet'),
                                Infolists\Components\TextEntry::make('completed_at')
                                    ->label('Module Completed')
                                    ->icon('heroicon-o-check-circle')
                                    ->dateTime('M j, Y')
                                    ->placeholder('Not completed yet'),
                                Infolists\Components\TextEntry::make('order_sequence')
                                    ->label('Module Number')
                                    ->icon('heroicon-o-hashtag')
                                    ->badge()
                                    ->color('primary'),
                                Infolists\Components\TextEntry::make('programModule.total_time_minutes')
                                    ->label('Total Duration')
                                    ->icon('heroicon-o-clock')
                                    ->formatStateUsing(function ($state) {
                                        if (! $state) {
                                            return 'N/A';
                                        }
                                        $hours = floor($state / 60);
                                        $minutes = $state % 60;

                                        return $hours > 0 ? "{$hours}h {$minutes}m" : "{$minutes}m";
                                    })
                                    ->badge()
                                    ->color('info'),
                            ]),
                    ]),
                Infolists\Components\Section::make('Sessions Overview')
                    ->icon('heroicon-o-calendar')
                    ->description("Total: {$totalSessions} sessions")
                    ->visible(fn () => ! $this->isEmonc())
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('completed_sessions')
                                    ->label('Completed Sessions')
                                    ->icon('heroicon-o-check-circle')
                                    ->getStateUsing(fn () => $completedSessions)
                                    ->badge()
                                    ->color('success')
                                    ->formatStateUsing(fn () => "{$completedSessions} / {$totalSessions}"),
                                Infolists\Components\TextEntry::make('scheduled_sessions')
                                    ->label('Scheduled Sessions')
                                    ->icon('heroicon-o-calendar-days')
                                    ->getStateUsing(fn () => $scheduledSessions)
                                    ->badge()
                                    ->color('warning'),
                                Infolists\Components\TextEntry::make('progress_percentage')
                                    ->label('Progress')
                                    ->icon('heroicon-o-chart-bar')
                                    ->getStateUsing(fn () => $totalSessions > 0 ? round(($completedSessions / $totalSessions) * 100, 1).'%' : '0%'
                                    )
                                    ->badge()
                                    ->color('primary'),
                            ]),
                    ]),
                Infolists\Components\Section::make('Mentee Statistics')
                    ->icon('heroicon-o-users')
                    ->description("Total Enrolled: {$enrolledCount}")
                    ->schema([
                        Infolists\Components\Grid::make(5)
                            ->schema([
                                Infolists\Components\TextEntry::make('completed_mentees')
                                    ->label('Completed')
                                    ->icon('heroicon-o-check-circle')
                                    ->getStateUsing(fn () => $completedCount)
                                    ->badge()
                                    ->color('success'),
                                Infolists\Components\TextEntry::make('in_progress_mentees')
                                    ->label('In Progress')
                                    ->icon('heroicon-o-arrow-path')
                                    ->getStateUsing(fn () => $inProgressCount)
                                    ->badge()
                                    ->color('warning'),
                                Infolists\Components\TextEntry::make('not_started_mentees')
                                    ->label('Not Started')
                                    ->icon('heroicon-o-clock')
                                    ->getStateUsing(fn () => $notStartedCount)
                                    ->badge()
                                    ->color('gray'),
                                Infolists\Components\TextEntry::make('exempted_mentees')
                                    ->label('Exempted')
                                    ->icon('heroicon-o-shield-check')
                                    ->getStateUsing(fn () => $exemptedCount)
                                    ->badge()
                                    ->color('info'),
                                Infolists\Components\TextEntry::make('avg_attendance')
                                    ->label('Avg Attendance')
                                    ->icon('heroicon-o-user-group')
                                    ->getStateUsing(fn () => round($avgAttendance, 1).'%')
                                    ->badge()
                                    ->color(fn () => $avgAttendance >= 80 ? 'success' : ($avgAttendance >= 60 ? 'warning' : 'danger')),
                            ]),
                    ]),
                Infolists\Components\Section::make('Activity Enrollments')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->description('Mentees enrolled per activity')
                    ->visible(fn () => $this->isEmonc())
                    ->schema([
                        Infolists\Components\TextEntry::make('activity_enrollments')
                            ->label('')
                            ->html()
                            ->columnSpanFull()
                            ->getStateUsing(function () {
                                $activities = $this->module->programModule?->activities ?? collect();
                                if ($activities->isEmpty()) {
                                    return '<p class="text-gray-500">No activities configured for this module.</p>';
                                }

                                $enrollments = $this->module->activityEnrollments->groupBy('activity_id');
                                $html = '<div class="space-y-4">';
                                foreach ($activities as $activity) {
                                    $activityEnrollments = $enrollments->get($activity->id, collect());
                                    $menteeNames = $activityEnrollments
                                        ->map(fn ($e) => $e->classParticipant?->user?->name ?? 'Unknown')
                                        ->filter()
                                        ->sort()
                                        ->values();
                                    $count = $menteeNames->count();
                                    $namesHtml = $count > 0
                                        ? '<ul class="list-disc list-inside text-sm text-gray-700 dark:text-gray-300 mt-1">'.
                                            $menteeNames->map(fn ($n) => '<li>'.e($n).'</li>')->join('').
                                            '</ul>'
                                        : '<p class="text-sm text-gray-500 mt-1">0 mentees enrolled</p>';

                                    $html .= '<div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">';
                                    $html .= '<h4 class="font-semibold text-gray-900 dark:text-white">'.e($activity->name).'</h4>';
                                    $html .= '<div class="text-xs text-gray-500 mb-2">'.$count.' mentee(s)</div>';
                                    $html .= $namesHtml;
                                    $html .= '</div>';
                                }
                                $html .= '</div>';

                                return $html;
                            }),
                    ]),
                Infolists\Components\Section::make('Mentee Details')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->description('Individual mentee progress tracking')
                    ->collapsed()
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('menteeProgress')
                            ->label('')
                            ->schema([
                                Infolists\Components\Grid::make(6)
                                    ->schema([
                                        Infolists\Components\TextEntry::make('classParticipant.user.full_name')
                                            ->label('Name')
                                            ->weight(FontWeight::Bold)
                                            ->icon('heroicon-o-user')
                                            ->columnSpan(2),
                                        Infolists\Components\TextEntry::make('classParticipant.user.facility.name')
                                            ->label('Facility')
                                            ->icon('heroicon-o-building-office')
                                            ->default('N/A'),
                                        Infolists\Components\TextEntry::make('status')
                                            ->badge()
                                            ->color(fn (string $state): string => match ($state) {
                                                'not_started' => 'gray',
                                                'in_progress' => 'warning',
                                                'completed' => 'success',
                                                'exempted' => 'info',
                                                default => 'gray',
                                            })
                                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                                'not_started' => 'Not Started',
                                                'in_progress' => 'In Progress',
                                                'completed' => 'Completed',
                                                'exempted' => 'Exempted',
                                                default => ucfirst($state),
                                            }
                                            ),
                                        Infolists\Components\TextEntry::make('attendance_percentage')
                                            ->label('Attendance')
                                            ->suffix('%')
                                            ->badge()
                                            ->color(fn ($state) => match (true) {
                                                $state >= 80 => 'success',
                                                $state >= 60 => 'warning',
                                                $state === null => 'gray',
                                                default => 'danger',
                                            })
                                            ->default('N/A'),
                                        Infolists\Components\TextEntry::make('assessment_score')
                                            ->label('Assessment')
                                            ->formatStateUsing(fn ($state) => $state ? number_format($state, 1).'%' : 'N/A')
                                            ->badge()
                                            ->color(fn ($state) => $state >= 70 ? 'success' : ($state ? 'danger' : 'gray')),
                                    ]),
                            ])
                            ->contained(false),
                    ]),
            ]);
    }
}
