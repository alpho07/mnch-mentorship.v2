<?php

namespace App\Filament\Resources\MentorshipResource\Pages;

use App\Filament\Resources\MentorshipTrainingResource;
use App\Models\ClassAttendance;
use App\Models\ClassModule;
use App\Models\ClassModuleActivityParticipant;
use App\Models\ClassParticipant;
use App\Models\MenteeModuleProgress;
use App\Models\MentorshipClass;
use App\Models\Training;
use App\Services\EmoncNotificationService;
use App\Services\QuizAttemptService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class ManageModuleMentees extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = MentorshipTrainingResource::class;

    protected static string $view = 'filament.pages.manage-module-mentees';

    protected static bool $shouldRegisterNavigation = false;

    public Training $training;

    public MentorshipClass $class;

    public ClassModule $module;

    public function mount(Training $training, MentorshipClass $class, ClassModule $module): void
    {
        $this->training = $training;
        $this->class = $class;
        $this->module = $module->load('programModule');
    }

    public function getTitle(): string
    {
        return "Attendance — {$this->module->programModule?->name}";
    }

    public function getSubheading(): ?string
    {
        $confirmed = $this->module->confirmedAttendanceCount();
        $total = $this->module->enrolledMenteeCount();
        $rate = $this->module->attendanceRate();
        $status = $this->module->status_label;

        return "Status: {$status} · {$confirmed}/{$total} confirmed ({$rate}%) · Class: {$this->class->name}";
    }

    // ─────────────────────────────────────────────────────────────────────────
    // View Data
    // ─────────────────────────────────────────────────────────────────────────

    public function getViewData(): array
    {
        $attendanceLink = ($this->module->attendance_token && $this->module->attendance_link_active) ? route('module.attendance', ['token' => $this->module->attendance_token]) : null;

        return [
            'attendanceLink' => $attendanceLink,
            'attendanceLinkActive' => (bool) $this->module->attendance_link_active,
            'attendanceToken' => $this->module->attendance_token,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Attendance Link Helpers
    // ─────────────────────────────────────────────────────────────────────────

    public function toggleAttendanceLink(): void
    {
        if (! $this->module->attendance_token) {
            $this->module->update([
                'attendance_token' => Str::random(32),
                'attendance_link_active' => true,
            ]);
            Notification::make()->success()->title('Attendance link activated')->send();
        } else {
            $this->module->update([
                'attendance_link_active' => ! $this->module->attendance_link_active,
            ]);
            $state = $this->module->fresh()->attendance_link_active ? 'activated' : 'deactivated';
            Notification::make()->success()->title("Attendance link {$state}")->send();
        }

        $this->module = $this->module->fresh('programModule');
    }

    public function regenerateAttendanceLink(): void
    {
        $this->module->update([
            'attendance_token' => Str::random(32),
            'attendance_link_active' => true,
        ]);
        $this->module = $this->module->fresh('programModule');
        Notification::make()->success()->title('Attendance link regenerated')->send();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Header Actions
    // ─────────────────────────────────────────────────────────────────────────

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('toggle_attendance_link')
                ->label(fn () => $this->module->attendance_link_active ? 'Deactivate Link' : 'Activate Link')
                ->icon(fn () => $this->module->attendance_link_active ? 'heroicon-o-link-slash' : 'heroicon-o-link')
                ->color(fn () => $this->module->attendance_link_active ? 'warning' : 'success')
                ->action('toggleAttendanceLink'),
            Actions\Action::make('regenerate_link')
                ->label('Regenerate Link')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('This will invalidate the current link. Existing QR codes will stop working.')
                ->action('regenerateAttendanceLink')
                ->visible(fn () => (bool) $this->module->attendance_token),
            Actions\Action::make('mark_all_present')
                ->label('Mark All Present')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn () => $this->module->status === 'in_progress')
                ->requiresConfirmation()
                ->modalHeading('Mark All Mentees as Present')
                ->modalDescription('This will mark all enrolled mentees as present for this module. Already confirmed mentees are skipped.')
                ->action(function () {
                    $participants = ClassParticipant::where('mentorship_class_id', $this->class->id)
                        ->whereIn('status', ['enrolled', 'active'])
                        ->get();

                    $marked = 0;
                    foreach ($participants as $participant) {
                        if ($this->isPresent($participant)) {
                            continue;
                        }

                        ClassAttendance::create([
                            'class_id' => $this->class->id,
                            'class_module_id' => $this->module->id,
                            'session_id' => null,
                            'user_id' => $participant->user_id,
                            'marked_by' => auth()->id(),
                            'marked_at' => now(),
                            'source' => 'manual',
                        ]);

                        MenteeModuleProgress::where('class_participant_id', $participant->id)
                            ->where('class_module_id', $this->module->id)
                            ->where('status', 'not_started')
                            ->update(['status' => 'in_progress', 'started_at' => now()]);

                        if ($participant->user) {
                            Notification::make()
                                ->title('Attendance Confirmed')
                                ->body("You have been marked present for: {$this->module->programModule?->name}")
                                ->success()
                                ->sendToDatabase($participant->user);
                        }

                        $marked++;
                    }

                    Notification::make()->success()->title("{$marked} mentees marked as present")->send();
                }),
            Actions\Action::make('export_attendance')
                ->label('Export')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function () {
                    Notification::make()->info()->title('Export coming soon')->send();
                }),
            Actions\Action::make('back')
                ->label('Back to Modules')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn () => MentorshipTrainingResource::getUrl('class-modules', [
                    'training' => $this->training->id,
                    'class' => $this->class->id,
                ])),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Table
    // ─────────────────────────────────────────────────────────────────────────

    public function table(Table $table): Table
    {
        return $table
            ->query(
                ClassParticipant::query()
                    ->where('mentorship_class_id', $this->class->id)
                    ->whereIn('status', ['enrolled', 'active'])
                    ->with([
                        'user.facility',
                        'moduleProgress' => fn ($q) => $q->where('class_module_id', $this->module->id),
                    ])
            )
            ->columns([
                Tables\Columns\TextColumn::make('user.full_name')
                    ->label('Mentee')
                    ->searchable(['first_name', 'last_name'])
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('user.facility.name')
                    ->label('Facility')
                    ->searchable()
                    ->toggleable(),
                // ── Attendance Confirmed (✓ / ✗) ────────────────────────────
                // Source of truth: MenteeModuleProgress status (in_progress/completed).
                // This handles both new records (class_module_id set on ClassAttendance)
                // and legacy records (class_module_id = null on ClassAttendance).
                Tables\Columns\IconColumn::make('attendance_confirmed')
                    ->label('Confirmed')
                    ->getStateUsing(fn (ClassParticipant $record) => $this->isPresent($record))
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle'),
                // ── Confirmed At timestamp ───────────────────────────────────
                // Tries ClassAttendance first, falls back to progress.started_at
                Tables\Columns\TextColumn::make('confirmed_at')
                    ->label('Confirmed At')
                    ->getStateUsing(function (ClassParticipant $record) {
                        $att = $this->attendanceRecord($record->user_id);
                        if ($att) {
                            return $att->marked_at;
                        }
                        // Fallback: use progress started_at (covers legacy records)
                        $progress = $record->moduleProgress->first();
                        if ($progress && in_array($progress->status, ['in_progress', 'completed'])) {
                            return $progress->started_at;
                        }

                        return null;
                    })
                    ->dateTime('d M Y H:i')
                    ->placeholder('Not confirmed')
                    ->color(fn (ClassParticipant $record) => $this->isPresent($record) ? 'success' : 'gray'),
                // ── Source badge (link | manual | auto) ─────────────────────
                // Tries ClassAttendance, falls back to 'manual' if progress shows
                // present but no attendance record found (legacy data).
                Tables\Columns\TextColumn::make('attendance_source')
                    ->label('Source')
                    ->getStateUsing(function (ClassParticipant $record) {
                        $att = $this->attendanceRecord($record->user_id);
                        if ($att) {
                            return $att->source;
                        }
                        // Legacy: present in progress but no scoped attendance record
                        $progress = $record->moduleProgress->first();
                        if ($progress && in_array($progress->status, ['in_progress', 'completed'])) {
                            return 'auto'; // best guess for legacy auto-confirmed
                        }

                        return null;
                    })
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'link' => 'Self-Confirmed',
                        'manual' => 'Manual',
                        'auto' => 'Auto',
                        default => '—',
                    })
                    ->color(fn (?string $state) => match ($state) {
                        'link' => 'success',
                        'manual' => 'warning',
                        'auto' => 'info',
                        default => 'gray',
                    })
                    ->placeholder('—'),
                // ── Progress Status ──────────────────────────────────────────
                Tables\Columns\BadgeColumn::make('module_progress_status')
                    ->label('Progress')
                    ->getStateUsing(fn (ClassParticipant $record) => $record->moduleProgress->first()?->status ?? 'not_started')
                    ->colors([
                        'gray' => 'not_started',
                        'warning' => 'in_progress',
                        'success' => 'completed',
                        'info' => 'exempted',
                    ])
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'not_started' => 'Not Started',
                        'in_progress' => 'Present',
                        'completed' => 'Completed',
                        'exempted' => 'Exempted',
                        default => ucfirst($state),
                    }),
                // ── Recommendation indicator ─────────────────────────────────
                // ── Recommendation indicator ─────────────────────────────────
                // Query DB fresh — eager-loaded moduleProgress is stale after
                // write/edit recommendation actions run in the same page load.
                Tables\Columns\IconColumn::make('has_recommendation')
                    ->label('Recommendation')
                    ->getStateUsing(fn (ClassParticipant $record) => MenteeModuleProgress::where('class_participant_id', $record->id)
                        ->where('class_module_id', $this->module->id)
                        ->whereNotNull('mentor_recommendation')
                        ->exists()
                    )
                    ->boolean()
                    ->trueColor('primary')
                    ->falseColor('gray')
                    ->trueIcon('heroicon-o-document-text')
                    ->falseIcon('heroicon-o-minus'),
                // ── Hands-on Video Submitted ─────────────────────────────────
                Tables\Columns\IconColumn::make('has_video')
                    ->label('Video')
                    ->getStateUsing(fn (ClassParticipant $record) => MenteeModuleProgress::where('class_participant_id', $record->id)
                        ->where('class_module_id', $this->module->id)
                        ->where(function ($q) {
                            $q->whereNotNull('hands_on_video_url')
                                ->orWhereNotNull('hands_on_video_path');
                        })
                        ->exists()
                    )
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->trueIcon('heroicon-o-video-camera')
                    ->falseIcon('heroicon-o-minus'),
                // ── Video Review Status ──────────────────────────────────────
                Tables\Columns\BadgeColumn::make('video_review_status')
                    ->label('Video Review')
                    ->getStateUsing(fn (ClassParticipant $record) => MenteeModuleProgress::where('class_participant_id', $record->id)
                        ->where('class_module_id', $this->module->id)
                        ->value('video_review_status') ?? 'pending'
                    )
                    ->colors([
                        'gray' => 'pending',
                        'success' => 'passed',
                        'danger' => 'failed',
                    ])
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'pending' => 'Pending',
                        'passed' => 'Passed',
                        'failed' => 'Failed',
                        default => ucfirst($state),
                    }),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    // ── Full Review page ─────────────────────────────────────
                    Tables\Actions\Action::make('full_review')
                        ->label('Full Review')
                        ->icon('heroicon-o-clipboard-document-check')
                        ->color('primary')
                        ->url(fn (ClassParticipant $record) => MentorshipTrainingResource::getUrl('module-mentee-review', [
                            'training' => $this->training->id,
                            'class' => $this->class->id,
                            'module' => $this->module->id,
                            'participant' => $record->id,
                        ])),
                    // ── Mark Present (mentor manual override) ────────────────
                    Tables\Actions\Action::make('mark_present')
                        ->label('Mark Present')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn (ClassParticipant $record) => $this->module->status === 'in_progress' &&
                        ! $this->isPresent($record)
                        )
                        ->action(function (ClassParticipant $record) {
                            ClassAttendance::create([
                                'class_id' => $this->class->id,
                                'class_module_id' => $this->module->id,
                                'session_id' => null,
                                'user_id' => $record->user_id,
                                'marked_by' => auth()->id(),
                                'marked_at' => now(),
                                'source' => 'manual',
                            ]);

                            MenteeModuleProgress::where('class_participant_id', $record->id)
                                ->where('class_module_id', $this->module->id)
                                ->where('status', 'not_started')
                                ->update(['status' => 'in_progress', 'started_at' => now()]);

                            if ($record->user) {
                                Notification::make()
                                    ->title('Attendance Confirmed')
                                    ->body("Your mentor has marked you present for: {$this->module->programModule?->name}")
                                    ->success()
                                    ->sendToDatabase($record->user);
                            }

                            Notification::make()->success()->title("Marked {$record->user?->full_name} as present")->send();
                        }),
                    // ── Remove Attendance ────────────────────────────────────
                    Tables\Actions\Action::make('remove_attendance')
                        ->label('Remove Attendance')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(fn (ClassParticipant $record) => $this->module->status === 'in_progress' &&
                                $this->isPresent($record)
                        )
                        ->requiresConfirmation()
                        ->action(function (ClassParticipant $record) {
                            // Delete any scoped attendance record (new records)
                            ClassAttendance::where('class_id', $this->class->id)
                                ->where('class_module_id', $this->module->id)
                                ->where('user_id', $record->user_id)
                                ->delete();

                            // Also delete legacy records (null class_module_id, same class)
                            // that match this user — only if no scoped record existed
                            // Note: do NOT delete enrollment-level records (those have no marked_by = user)
                            ClassAttendance::where('class_id', $this->class->id)
                                ->whereNull('class_module_id')
                                ->where('user_id', $record->user_id)
                                ->where('marked_by', '!=', $record->user_id) // not self-enrollment
                                ->delete();

                            MenteeModuleProgress::where('class_participant_id', $record->id)
                                ->where('class_module_id', $this->module->id)
                                ->whereIn('status', ['in_progress'])
                                ->update(['status' => 'not_started', 'started_at' => null]);

                            Notification::make()->warning()->title('Attendance removed')->send();
                        }),
                    // ── Write / Edit Recommendation ──────────────────────────
                    Tables\Actions\Action::make('write_recommendation')
                        ->label(fn (ClassParticipant $record) => MenteeModuleProgress::where('class_participant_id', $record->id)
                            ->where('class_module_id', $this->module->id)
                            ->whereNotNull('mentor_recommendation')
                            ->exists() ? 'Edit Recommendation' : 'Write Recommendation'
                        )
                        ->icon(fn (ClassParticipant $record) => MenteeModuleProgress::where('class_participant_id', $record->id)
                            ->where('class_module_id', $this->module->id)
                            ->whereNotNull('mentor_recommendation')
                            ->exists() ? 'heroicon-o-pencil-square' : 'heroicon-o-document-text'
                        )
                        ->color(fn (ClassParticipant $record) => MenteeModuleProgress::where('class_participant_id', $record->id)
                            ->where('class_module_id', $this->module->id)
                            ->whereNotNull('mentor_recommendation')
                            ->exists() ? 'warning' : 'primary'
                        )
                        ->visible(fn (ClassParticipant $record) => $this->isPresent($record))
                        ->slideOver()
                        ->mountUsing(fn (\Filament\Forms\ComponentContainer $form, ClassParticipant $record) => $form->fill([
                            'mentor_recommendation' => MenteeModuleProgress::where('class_participant_id', $record->id)
                                ->where('class_module_id', $this->module->id)
                                ->value('mentor_recommendation') ?? '',
                        ])
                        )
                        ->form([
                            Forms\Components\Placeholder::make('mentee_name')
                                ->label('Mentee')
                                ->content(fn (ClassParticipant $record) => $record->user?->full_name ?? '—'),
                            Forms\Components\Placeholder::make('module_name')
                                ->label('Module')
                                ->content($this->module->programModule?->name ?? '—'),
                            Forms\Components\Textarea::make('mentor_recommendation')
                                ->label('Recommendation')
                                ->required()
                                ->rows(6)
                                ->maxLength(2000)
                                ->placeholder("Describe the mentee's performance, strengths, and areas for improvement..."),
                        ])
                        ->action(function (ClassParticipant $record, array $data) {
                            // Always query fresh from DB — never use eager-loaded moduleProgress
                            $progress = MenteeModuleProgress::updateOrCreate(
                                [
                                    'class_participant_id' => $record->id,
                                    'class_module_id' => $this->module->id,
                                ],
                                [
                                    'status' => 'in_progress',
                                    'started_at' => now(),
                                    'mentor_recommendation' => $data['mentor_recommendation'],
                                    'recommendation_by' => auth()->id(),
                                    'recommendation_written_at' => now(),
                                ]
                            );

                            app(EmoncNotificationService::class)->mentorRecommendationWritten($progress);

                            Notification::make()
                                ->success()
                                ->title('Recommendation Saved')
                                ->body("Recommendation written for {$record->user?->full_name}.")
                                ->send();
                        }),
                    // ── View Recommendation ──────────────────────────────────
                    Tables\Actions\Action::make('view_recommendation')
                        ->label('View Recommendation')
                        ->icon('heroicon-o-eye')
                        ->color('gray')
                        ->visible(fn (ClassParticipant $record) => MenteeModuleProgress::where('class_participant_id', $record->id)
                            ->where('class_module_id', $this->module->id)
                            ->whereNotNull('mentor_recommendation')
                            ->exists()
                        )
                        ->modalHeading(fn (ClassParticipant $record) => "Recommendation — {$record->user?->full_name}")
                        ->modalContent(fn (ClassParticipant $record) => view(
                            'filament.components.recommendation-view',
                            [
                                'progress' => MenteeModuleProgress::where('class_participant_id', $record->id)
                                    ->where('class_module_id', $this->module->id)
                                    ->first(),
                                'mentee' => $record->user,
                                'module' => $this->module,
                            ]
                        ))
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Close'),
                ]),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('attendance_status')
                    ->label('Attendance Status')
                    ->options([
                        'confirmed' => 'Confirmed Present',
                        'absent' => 'Not Confirmed',
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (empty($data['value'])) {
                            return;
                        }

                        // Use MenteeModuleProgress as source of truth (handles legacy records)
                        if ($data['value'] === 'confirmed') {
                            $query->whereHas('moduleProgress', function ($q) {
                                $q->where('class_module_id', $this->module->id)
                                    ->whereIn('status', ['in_progress', 'completed']);
                            });
                        } else {
                            $query->whereDoesntHave('moduleProgress', function ($q) {
                                $q->where('class_module_id', $this->module->id)
                                    ->whereIn('status', ['in_progress', 'completed']);
                            });
                        }
                    }),
                Tables\Filters\SelectFilter::make('progress_status')
                    ->label('Progress')
                    ->options([
                        'not_started' => 'Not Started',
                        'in_progress' => 'Present',
                        'completed' => 'Completed',
                        'exempted' => 'Exempted',
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (empty($data['value'])) {
                            return;
                        }

                        $query->whereHas('moduleProgress', function ($q) use ($data) {
                            $q->where('class_module_id', $this->module->id)
                                ->where('status', $data['value']);
                        });
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('bulk_pass_video')
                    ->label('Pass Video Review for Selected')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Pass Video Review for Selected Mentees')
                    ->modalDescription('This will mark the hands-on video review as PASSED for all selected mentees who have submitted a video.')
                    ->action(function ($records) {
                        $notificationService = app(EmoncNotificationService::class);
                        $passed = 0;
                        $skipped = 0;

                        foreach ($records as $record) {
                            $progress = MenteeModuleProgress::where('class_participant_id', $record->id)
                                ->where('class_module_id', $this->module->id)
                                ->first();

                            if (! $progress || ! $progress->hasSubmittedVideo() || $progress->video_review_status === 'passed') {
                                $skipped++;

                                continue;
                            }

                            $progress->recordVideoReview('passed', 'Reviewed in bulk by mentor.', auth()->id());
                            $notificationService->videoReviewed($progress->fresh());
                            $passed++;
                        }

                        Notification::make()
                            ->success()
                            ->title('Bulk Video Review Complete')
                            ->body("{$passed} passed · {$skipped} skipped")
                            ->send();
                    }),
            ])
            ->emptyStateHeading('No Mentees Enrolled')
            ->emptyStateDescription('Enroll mentees in the class first.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Whether a mentee is confirmed present for this module.
     *
     * Uses MenteeModuleProgress as the authoritative source — this is always
     * correctly set regardless of whether the ClassAttendance record has
     * class_module_id populated (handles legacy null records).
     */
    private function isPresent(ClassParticipant $participant): bool
    {
        // Use eager-loaded progress if available
        $progress = $participant->relationLoaded('moduleProgress') ? $participant->moduleProgress->first() : MenteeModuleProgress::where('class_participant_id', $participant->id)
            ->where('class_module_id', $this->module->id)
            ->first();

        if ($progress && in_array($progress->status, ['in_progress', 'completed'])) {
            return true;
        }

        // Fallback: direct ClassAttendance record with class_module_id (new records)
        return ClassAttendance::where('class_id', $this->class->id)
            ->where('class_module_id', $this->module->id)
            ->where('user_id', $participant->user_id)
            ->exists();
    }

    /**
     * Legacy: kept for hasAttendance calls by user_id (e.g. mark_all_present).
     * Internal use only — prefer isPresent(ClassParticipant) where possible.
     */
    private function hasAttendance(int $userId): bool
    {
        return ClassAttendance::where('class_id', $this->class->id)
            ->where('class_module_id', $this->module->id)
            ->where('user_id', $userId)
            ->exists();
    }

    /**
     * Get the most relevant ClassAttendance record for a user.
     * Prefers records with class_module_id set; falls back to class-scoped records.
     */
    private function attendanceRecord(int $userId): ?ClassAttendance
    {
        // Try new record first (class_module_id is set)
        $record = ClassAttendance::where('class_id', $this->class->id)
            ->where('class_module_id', $this->module->id)
            ->where('user_id', $userId)
            ->first();

        if ($record) {
            return $record;
        }

        // Legacy: find any class-scoped record for this user (class_module_id = null)
        // Only return if the user is actually confirmed present (via progress)
        return ClassAttendance::where('class_id', $this->class->id)
            ->whereNull('class_module_id')
            ->where('user_id', $userId)
            ->where('source', '!=', 'auto') // exclude enrollment auto-marks
            ->first();
    }

    /**
     * Build pre/post-test status for the evaluate mentee slide-over.
     */
    private function buildQuizStatus(?\App\Models\User $mentee, $quizzes, QuizAttemptService $service, string $attemptIdColumn): array
    {
        if ($quizzes->isEmpty() || ! $mentee) {
            return ['exists' => false, 'passed' => true, 'completed' => true, 'attempt' => null, 'quiz' => null];
        }

        $quiz = $quizzes->first();
        $latestCompleted = $service->getLatestAttempts($mentee, $quiz);
        $key = $attemptIdColumn === 'pre_test_attempt_id' ? 'pre_test' : 'post_test';
        $latest = $latestCompleted[$key];

        if ($latest) {
            return [
                'exists' => true,
                'passed' => $latest->isPassed(),
                'completed' => true,
                'attempt' => $latest,
                'quiz' => $quiz,
            ];
        }

        return [
            'exists' => true,
            'passed' => false,
            'completed' => false,
            'attempt' => null,
            'quiz' => $quiz,
        ];
    }
}
