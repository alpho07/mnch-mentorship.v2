<?php

namespace App\Filament\Resources\MentorshipResource\Pages;

use App\Filament\Forms\Components\CardCheckboxList;
use App\Filament\Resources\MentorshipTrainingResource;
use App\Mail\MenteeEnrollmentInvitationMail;
use App\Models\Cadre;
use App\Models\ClassParticipant;
use App\Models\Department;
use App\Models\Facility;
use App\Models\MentorshipClass;
use App\Models\Program;
use App\Models\Training;
use App\Models\User;
use App\Services\EmoncNotificationService;
use App\Services\EnrollmentService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Str;
use League\Csv\Writer;
use Spatie\Browsershot\Browsershot;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class ManageClassMentees extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = MentorshipTrainingResource::class;

    protected static string $view = 'filament.pages.manage-class-mentees';

    protected static bool $shouldRegisterNavigation = false;

    public Training $training;

    public MentorshipClass $class;

    public function mount(Training $training, MentorshipClass $class): void
    {
        $this->training = $training;
        $this->class = $class->load(['training']);
    }

    public function getTitle(): string
    {
        return "Mentees — {$this->class->name}";
    }

    public function getSubheading(): ?string
    {
        $enrolled = ClassParticipant::where('mentorship_class_id', $this->class->id)
            ->whereIn('status', ['enrolled', 'active'])
            ->count();

        return "{$enrolled} enrolled · ".\Illuminate\Support\Str::limit($this->training->title, 60);
    }

    // Pass variables the blade view needs
    public function getViewData(): array
    {
        $token = $this->class->enrollment_token;
        $enrollmentLink = $token ? route('mentee.enroll', ['token' => $token]) : null;

        return [
            'enrollmentLink' => $enrollmentLink,
            'enrollmentLinkActive' => (bool) $token,
            'training' => $this->training,
            'class' => $this->class,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Table (enrolled mentees roster)
    // ─────────────────────────────────────────────────────────────────────────

    public function table(Table $table): Table
    {
        return $table
            ->query(
                ClassParticipant::query()
                    ->where('mentorship_class_id', $this->class->id)
                    ->with(['user.cadre', 'user.facility', 'user.department'])
            )
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Name')
                    ->searchable(['users.first_name', 'users.last_name', 'users.name'])
                    ->sortable()
                    ->description(fn ($record) => $record->user?->email ?? '—'),
                Tables\Columns\TextColumn::make('user.email')
                    ->label('Email')
                    ->searchable()
                    ->copyable()
                    ->placeholder('No email')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('user.phone')
                    ->label('Phone')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('user.cadre.name')
                    ->label('Cadre')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('user.department.name')
                    ->label('Department')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('user.facility.name')
                    ->label('Facility')
                    ->toggleable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'enrolled',
                        'success' => 'active',
                        'gray' => 'completed',
                        'danger' => 'withdrawn',
                    ]),
                Tables\Columns\BadgeColumn::make('mentor_approved')
                    ->label('Mentor Approval')
                    ->getStateUsing(fn (ClassParticipant $record) => $record->mentor_approved_at ? 'approved' : 'pending')
                    ->colors([
                        'success' => 'approved',
                        'gray' => 'pending',
                    ])
                    ->formatStateUsing(fn (string $state) => $state === 'approved' ? 'Approved' : 'Pending')
                    ->toggleable(),
                Tables\Columns\BadgeColumn::make('head_drmh_approved')
                    ->label('Head DRMH')
                    ->getStateUsing(fn (ClassParticipant $record) => $record->head_drmh_approved_at ? 'approved' : 'pending')
                    ->colors([
                        'success' => 'approved',
                        'gray' => 'pending',
                    ])
                    ->formatStateUsing(fn (string $state) => $state === 'approved' ? 'Certified' : 'Pending')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('enrolled_at')
                    ->label('Enrolled')
                    ->date('d M Y')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('invitation_sent_at')
                    ->label('Invited')
                    ->getStateUsing(fn (ClassParticipant $record) => $record->invitation_sent_at ? $record->invitation_sent_at->diffForHumans() : 'Not sent'
                    )
                    ->icon(fn (ClassParticipant $record) => $record->invitation_sent_at ? 'heroicon-o-envelope' : 'heroicon-o-envelope-open'
                    )
                    ->color(fn (ClassParticipant $record) => $record->invitation_sent_at ? 'success' : 'gray'
                    )
                    ->toggleable(),
            ])
            ->actions([
                Tables\Actions\Action::make('update_email')
                    ->label('Update Email')
                    ->icon('heroicon-o-envelope')
                    ->color('warning')
                    ->visible(fn (ClassParticipant $record) => empty($record->user->email))
                    ->form([
                        Forms\Components\TextInput::make('email')
                            ->label('Email Address')
                            ->email()
                            ->required()
                            ->unique('users', 'email'),
                    ])
                    ->action(function (ClassParticipant $record, array $data) {
                        $record->user->update(['email' => $data['email']]);

                        Notification::make()
                            ->success()
                            ->title('Email Updated')
                            ->body("Email for {$record->user->full_name} has been updated.")
                            ->send();
                    }),
                Tables\Actions\Action::make('send_invite')
                    ->label(fn (ClassParticipant $record) => $record->invitation_sent_at ? 'Resend Invite' : 'Send Invite'
                    )
                    ->icon('heroicon-o-paper-airplane')
                    ->color(fn (ClassParticipant $record) => $record->invitation_sent_at ? 'gray' : 'info'
                    )
                    ->visible(fn (ClassParticipant $record) => ! empty($record->user->email))
                    ->requiresConfirmation()
                    ->modalHeading(fn (ClassParticipant $record) => $record->invitation_sent_at ? 'Resend Enrollment Invitation?' : 'Send Enrollment Invitation?'
                    )
                    ->modalDescription(fn (ClassParticipant $record) => $record->invitation_sent_at ? "This will resend the enrollment link to {$record->user->email}. The email will mention they can ignore it if already enrolled." : "This will send the enrollment link to {$record->user->email} for the first time."
                    )
                    ->action(function (ClassParticipant $record) {
                        $isResend = (bool) $record->invitation_sent_at;

                        // Ensure token + active link exists
                        $this->ensureEnrollmentToken();

                        try {
                            Mail::to($record->user->email)
                                ->send(new MenteeEnrollmentInvitationMail(
                                    $record->user,
                                    $this->class,
                                    $record,
                                    $isResend
                                ));

                            $record->update(['invitation_sent_at' => now()]);

                            Notification::make()
                                ->success()
                                ->title($isResend ? 'Invitation Resent' : 'Invitation Sent')
                                ->body("Email sent to {$record->user->email}")
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->danger()
                                ->title('Email Failed')
                                ->body("Could not send to {$record->user->email}: {$e->getMessage()}")
                                ->persistent()
                                ->send();
                        }
                    }),
                Tables\Actions\Action::make('remove')
                    ->label('Remove')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Remove Mentee')
                    ->modalDescription('This will remove the mentee and all their module progress for this class.')
                    ->action(function ($record) {
                        app(EnrollmentService::class)->removeFromClass($record);
                        Notification::make()->success()->title('Mentee Removed')->send();
                    }),
                Tables\Actions\Action::make('mentor_approve')
                    ->label('Mentor Approve')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (ClassParticipant $record) => $this->isEmonc() &&
                        $this->canMentorApprove() &&
                        ! $record->mentor_approved_at &&
                        $record->status === 'completed'
                    )
                    ->requiresConfirmation()
                    ->modalHeading(fn (ClassParticipant $record) => "Mentor Approve — {$record->user?->full_name}")
                    ->modalDescription('Approve this mentee for certification. The Head DRMH will still need to certify them.')
                    ->action(function (ClassParticipant $record) {
                        if (! $this->isReadyForMentorApproval($record)) {
                            Notification::make()
                                ->danger()
                                ->title('Not Ready for Approval')
                                ->body('This mentee must have a passed video review and completed module progress before mentor approval.')
                                ->send();

                            return;
                        }

                        try {
                            $record->markMentorApproved(auth()->id());
                        } catch (\DomainException $e) {
                            Notification::make()->danger()->title('Not Ready for Approval')->body($e->getMessage())->send();

                            return;
                        }

                        app(EmoncNotificationService::class)->mentorApproved($record->fresh());

                        Notification::make()
                            ->success()
                            ->title('Mentor Approval Saved')
                            ->body("{$record->user?->full_name} has been approved for certification.")
                            ->send();
                    }),
                Tables\Actions\Action::make('head_drmh_certify')
                    ->label('Head DRMH Certify')
                    ->icon('heroicon-o-shield-check')
                    ->color('primary')
                    ->visible(fn (ClassParticipant $record) => $this->canHeadDrmhCertify() &&
                        $record->isReadyForHeadDrmhCertification() &&
                        ! $record->head_drmh_approved_at &&
                        $record->status === 'completed'
                    )
                    ->requiresConfirmation()
                    ->modalHeading(fn (ClassParticipant $record) => "Certify — {$record->user?->full_name}")
                    ->modalDescription('This is the final certification step. A certificate will be generated and can be downloaded.')
                    ->action(function (ClassParticipant $record) {
                        $record->markHeadDrmhApproved(auth()->id());

                        app(EmoncNotificationService::class)->headDrmhCertified($record->fresh());

                        Notification::make()
                            ->success()
                            ->title('Certification Complete')
                            ->body("{$record->user?->full_name} has been certified.")
                            ->send();
                    }),
                Tables\Actions\Action::make('download_certificate')
                    ->label('Certificate')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('info')
                    ->visible(fn (ClassParticipant $record) => $record->isCertified())
                    ->url(fn (ClassParticipant $record) => route('reports.class.certificate', [
                        'class' => $this->class->id,
                        'participant' => $record->id,
                    ]))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('bulk_remove')
                    ->label('Remove Selected')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function ($records) {
                        $service = app(EnrollmentService::class);
                        foreach ($records as $record) {
                            $service->removeFromClass($record);
                        }
                        Notification::make()->success()->title('Mentees Removed')->send();
                    }),
                Tables\Actions\BulkAction::make('send_invitations')
                    ->label('Send Invitations')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Send Enrollment Invitations')
                    ->modalDescription('This will send (or resend) enrollment invitation emails to all selected mentees who have an email address.')
                    ->action(function ($records) {
                        $this->ensureEnrollmentToken();

                        $sent = 0;
                        $resent = 0;
                        $failed = [];

                        foreach ($records as $record) {
                            if (empty($record->user->email)) {
                                continue;
                            }

                            $isResend = (bool) $record->invitation_sent_at;

                            try {
                                Mail::to($record->user->email)
                                    ->send(new MenteeEnrollmentInvitationMail(
                                        $record->user,
                                        $this->class,
                                        $record,
                                        $isResend
                                    ));

                                $record->update(['invitation_sent_at' => now()]);

                                $isResend ? $resent++ : $sent++;
                            } catch (\Exception $e) {
                                $failed[] = $record->user->full_name;
                            }
                        }

                        $parts = [];
                        if ($sent) {
                            $parts[] = "{$sent} invited";
                        }
                        if ($resent) {
                            $parts[] = "{$resent} resent";
                        }

                        // Mirrors ClassLifecycleController::start() — requires
                        // modules and enrolled mentees, so a class with no
                        // modules assigned yet stays in draft.
                        if ($this->class->canStart()) {
                            $this->class->start();
                            $parts[] = 'class started';
                        }

                        Notification::make()
                            ->success()
                            ->title('Invitations Sent')
                            ->body(implode(' · ', $parts).(count($failed) ? ' · '.count($failed).' failed' : ''))
                            ->send();

                        if (count($failed)) {
                            Notification::make()
                                ->warning()
                                ->title('Some emails failed')
                                ->body('Could not send to: '.implode(', ', $failed))
                                ->persistent()
                                ->send();
                        }
                    }),
                Tables\Actions\BulkAction::make('bulk_mentor_approve')
                    ->label('Mentor Approve Selected')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Mentor Approve Selected Mentees')
                    ->modalDescription('This will approve all selected mentees who are eligible for certification.')
                    ->visible(fn () => $this->isEmonc() && $this->canMentorApprove())
                    ->action(function ($records) {
                        $notificationService = app(EmoncNotificationService::class);
                        $approved = 0;
                        $skipped = 0;

                        foreach ($records as $record) {
                            if ($record->mentor_approved_at) {
                                $skipped++;

                                continue;
                            }

                            try {
                                $record->markMentorApproved(auth()->id());
                            } catch (\DomainException $e) {
                                $skipped++;

                                continue;
                            }

                            $notificationService->mentorApproved($record->fresh());
                            $approved++;
                        }

                        Notification::make()
                            ->success()
                            ->title('Bulk Mentor Approval Complete')
                            ->body("{$approved} approved · {$skipped} skipped")
                            ->send();
                    }),
                Tables\Actions\BulkAction::make('bulk_drmh_certify')
                    ->label('Head DRMH Certify Selected')
                    ->icon('heroicon-o-shield-check')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading('Certify Selected Mentees')
                    ->modalDescription('This will certify all selected mentees who have mentor approval.')
                    ->visible(fn () => $this->canHeadDrmhCertify())
                    ->action(function ($records) {
                        $notificationService = app(EmoncNotificationService::class);
                        $certified = 0;
                        $skipped = 0;

                        foreach ($records as $record) {
                            if ($record->head_drmh_approved_at || ! $record->mentor_approved_at) {
                                $skipped++;

                                continue;
                            }

                            $record->markHeadDrmhApproved(auth()->id());
                            $notificationService->headDrmhCertified($record->fresh());
                            $certified++;
                        }

                        Notification::make()
                            ->success()
                            ->title('Bulk Certification Complete')
                            ->body("{$certified} certified · {$skipped} skipped")
                            ->send();
                    }),
                Tables\Actions\BulkAction::make('bulk_download_certificates')
                    ->label('Download Certificates (ZIP)')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('info')
                    ->visible(fn () => $this->canMentorApprove() || $this->canHeadDrmhCertify())
                    ->action(function ($records) {
                        $certified = $records->filter(fn ($r) => $r->isCertified());

                        if ($certified->isEmpty()) {
                            Notification::make()
                                ->warning()
                                ->title('No Certified Mentees')
                                ->body('Select mentees who have completed both mentor and Head DRMH approval.')
                                ->send();

                            return;
                        }

                        return $this->downloadCertificateZip($certified);
                    }),
                Tables\Actions\BulkAction::make('export_progress_csv')
                    ->label('Export Progress CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->action(function () {
                        return $this->exportProgressCsv();
                    }),
            ])
            ->emptyStateHeading('No Mentees Enrolled')
            ->emptyStateDescription('Use "Add from List" to enroll existing users, or "Add Mentee" to create and enroll a new one.')
            ->emptyStateIcon('heroicon-o-users');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Header Actions
    // ─────────────────────────────────────────────────────────────────────────

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label('Back to Classes')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn () => MentorshipTrainingResource::getUrl('classes', ['record' => $this->training->id])),
            //
            // ─── ACTION A: Checkbox slide-over ──────────────────────────────
            Actions\ActionGroup::make([
                Actions\Action::make('manage_from_list')
                    ->label('Add from List')
                    ->icon('heroicon-o-list-bullet')
                    ->color('primary')
                    ->slideOver()
                    ->modalWidth('2xl')
                    ->modalHeading('Manage Class Mentees')
                    ->modalDescription('Select mentees to enroll. Already-enrolled mentees are pre-checked. Uncheck to remove.')
                    ->form([
                        Forms\Components\Hidden::make('page')->default(1),
                        Forms\Components\TextInput::make('search')
                            ->label('Search')
                            ->placeholder('Search by name, phone, email, or facility...')
                            ->live(debounce: 400)
                            ->afterStateUpdated(fn (Forms\Set $set) => $set('page', 1))
                            ->prefixIcon('heroicon-o-magnifying-glass'),
                        CardCheckboxList::make('selected_users')
                            ->label('Available Users')
                            ->options(fn (Forms\Get $get) => $this->menteeListOptions(
                                $get('search'),
                                (int) ($get('page') ?? 1),
                                collect($get('selected_users') ?? [])->map(fn ($id) => (int) $id)->all()
                            ))
                            ->maxSelections(fn () => $this->training->max_participants)
                            ->default(fn () => ClassParticipant::where('mentorship_class_id', $this->class->id)
                                ->pluck('user_id')
                                ->toArray()
                            )
                            ->columnSpanFull(),
                        Forms\Components\Actions::make([
                            Forms\Components\Actions\Action::make('previous')
                                ->label('Previous')
                                ->color('gray')
                                ->action(function (Forms\Set $set, Forms\Get $get) {
                                    $set('page', max(1, (int) $get('page') - 1));
                                }),
                            Forms\Components\Actions\Action::make('next')
                                ->label('Next')
                                ->color('gray')
                                ->action(function (Forms\Set $set, Forms\Get $get) {
                                    $set('page', (int) $get('page') + 1);
                                }),
                        ])->columnSpanFull(),
                    ])
                    ->action(function (array $data) {

                        $selected = $data['selected_users'] ?? [];

                        $currentIds = ClassParticipant::where('mentorship_class_id', $this->class->id)
                            ->pluck('user_id')
                            ->toArray();

                        $toAdd = array_diff($selected, $currentIds);
                        $toRemove = array_diff($currentIds, $selected);

                        $service = app(EnrollmentService::class);

                        DB::transaction(function () use ($toAdd, $toRemove, $service) {

                            foreach ($toRemove as $userId) {
                                $p = ClassParticipant::where('mentorship_class_id', $this->class->id)
                                    ->where('user_id', $userId)
                                    ->first();

                                if ($p) {
                                    $service->removeFromClass($p);
                                }
                            }

                            foreach ($toAdd as $userId) {
                                $u = User::find($userId);
                                if ($u) {
                                    $service->enrollInClass($u, $this->class, 'manual');
                                }
                            }
                        });

                        $parts = [];
                        if (count($toAdd)) {
                            $parts[] = count($toAdd).' enrolled';
                        }
                        if (count($toRemove)) {
                            $parts[] = count($toRemove).' removed';
                        }

                        Notification::make()
                            ->success()
                            ->title('Mentees Updated')
                            ->body(implode(' · ', $parts) ?: 'No changes made.')
                            ->send();
                    }),
                // ─── ACTION B: Add Mentee popout (NEW) ───────────────────────────
                // Email lookup → load existing details OR create new user + enroll
                Actions\Action::make('add_mentee')
                    ->label('Add Mentee')
                    ->icon('heroicon-o-user-plus')
                    ->color('success')
                    ->modalWidth('xl')
                    ->modalHeading('Add Mentee')
                    ->modalDescription('Enter the email address to look up an existing user, or fill in all fields to create a new mentee account.')
                    ->form([
                        // ── Email field (triggers live lookup) ────────────────
                        Forms\Components\TextInput::make('email')
                            ->label('Email Address')
                            ->email()
                            ->required()
                            ->live(debounce: 600)
                            ->afterStateUpdated(function (Forms\Set $set, ?string $state) {
                                if (! $state || ! filter_var($state, FILTER_VALIDATE_EMAIL)) {
                                    $set('_found_user_id', null);
                                    $set('_found_label', null);
                                    $set('_already_enrolled', false);

                                    return;
                                }

                                $user = User::where('email', $state)
                                    ->with(['cadre', 'facility', 'department'])
                                    ->first();

                                if ($user) {
                                    $set('_found_user_id', $user->id);
                                    $set('_already_enrolled', ClassParticipant::where('mentorship_class_id', $this->class->id)->where('user_id', $user->id)->exists());

                                    // Pre-fill form with existing data (read-only in found state)
                                    $set('first_name', $user->first_name);
                                    $set('middle_name', $user->middle_name);
                                    $set('last_name', $user->last_name);
                                    $set('phone', $user->phone);
                                    $set('cadre_id', $user->cadre_id);
                                    $set('department_id', $user->department_id);
                                    $set('facility_id', $user->facility_id);
                                } else {
                                    $set('_found_user_id', null);
                                    $set('_already_enrolled', false);
                                    $set('_found_label', null);
                                    // Clear prefills so form is editable
                                    $set('first_name', null);
                                    $set('middle_name', null);
                                    $set('last_name', null);
                                    $set('phone', null);
                                    $set('cadre_id', null);
                                    $set('department_id', null);
                                    $set('facility_id', null);
                                }
                            })
                            ->placeholder('e.g. jane.wanjiku@moh.go.ke'),
                        // Hidden tracking fields
                        Forms\Components\Hidden::make('_found_user_id'),
                        Forms\Components\Hidden::make('_already_enrolled')->default(false),
                        // ── "User Found" status banner ────────────────────────
                        Forms\Components\Placeholder::make('_status_banner')
                            ->label('')
                            ->content(function (Forms\Get $get) {
                                $email = $get('email');
                                if (! $email || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                                    return '';
                                }

                                if ($get('_found_user_id')) {
                                    if ($get('_already_enrolled')) {
                                        return '⚠️  This person is already enrolled in this class.';
                                    }

                                    return '✅  User found! Fields are pre-filled from their profile. Click "Add Mentee" to enroll them.';
                                }

                                return '🆕  No account found for this email. Fill in the details below — a new user will be created with password 123456.';
                            })
                            ->visible(fn (Forms\Get $get) => ! empty($get('email')) && filter_var($get('email'), FILTER_VALIDATE_EMAIL))
                            ->extraAttributes(fn (Forms\Get $get) => [
                                'class' => $get('_found_user_id') ? ($get('_already_enrolled') ? 'text-sm font-medium text-amber-700 dark:text-amber-400' : 'text-sm font-medium text-emerald-700 dark:text-emerald-400') : 'text-sm font-medium text-blue-700 dark:text-blue-400',
                            ]),
                        Forms\Components\Fieldset::make('Personal Details')
                            ->schema([
                                Forms\Components\Grid::make(3)->schema([
                                    Forms\Components\TextInput::make('first_name')
                                        ->label('First Name')
                                        ->required(fn (Forms\Get $get) => ! $get('_found_user_id'))
                                        ->readOnly(fn (Forms\Get $get) => (bool) $get('_found_user_id'))
                                        ->maxLength(100),
                                    Forms\Components\TextInput::make('middle_name')
                                        ->label('Middle Name')
                                        ->readOnly(fn (Forms\Get $get) => (bool) $get('_found_user_id'))
                                        ->maxLength(100),
                                    Forms\Components\TextInput::make('last_name')
                                        ->label('Last Name')
                                        ->required(fn (Forms\Get $get) => ! $get('_found_user_id'))
                                        ->readOnly(fn (Forms\Get $get) => (bool) $get('_found_user_id'))
                                        ->maxLength(100),
                                ]),
                                Forms\Components\TextInput::make('phone')
                                    ->label('Phone')
                                    ->tel()
                                    ->readOnly(fn (Forms\Get $get) => (bool) $get('_found_user_id'))
                                    ->placeholder('+254 7XX XXX XXX')
                                    ->maxLength(20),
                            ]),
                        Forms\Components\Fieldset::make('Professional Details')
                            ->schema([
                                Forms\Components\Grid::make(2)->schema([
                                    Forms\Components\Select::make('cadre_id')
                                        ->label('Cadre')
                                        ->options(Cadre::orderBy('name')->pluck('name', 'id'))
                                        ->searchable()
                                        ->disabled(fn (Forms\Get $get) => (bool) $get('_found_user_id')),
                                    Forms\Components\Select::make('department_id')
                                        ->label('Department')
                                        ->options(Department::orderBy('name')->pluck('name', 'id'))
                                        ->searchable()
                                        ->disabled(fn (Forms\Get $get) => (bool) $get('_found_user_id')),
                                ]),
                                Forms\Components\Select::make('facility_id')
                                    ->label('Facility')
                                    ->options(fn () => Facility::orderBy('name')
                                        ->get()
                                        ->mapWithKeys(fn ($f) => [$f->id => "{$f->mfl_code} - {$f->name}"]))
                                    ->searchable()
                                    ->disabled(fn (Forms\Get $get) => (bool) $get('_found_user_id')),
                            ]),
                        // Password notice — only shown for new users
                        Forms\Components\Placeholder::make('_password_notice')
                            ->label('Password')
                            ->content('Default password 123456 will be set. The mentee should change it on first login.')
                            ->visible(fn (Forms\Get $get) => ! $get('_found_user_id') &&
                                    ! empty($get('email')) &&
                                    filter_var($get('email'), FILTER_VALIDATE_EMAIL)
                            ),
                    ])
                    ->modalSubmitActionLabel('Add Mentee')
                    ->action(function (array $data) {
                        $service = app(EnrollmentService::class);

                        // ── Existing user ─────────────────────────────────────
                        if (! empty($data['_found_user_id'])) {
                            if ($data['_already_enrolled']) {
                                Notification::make()
                                    ->warning()
                                    ->title('Already Enrolled')
                                    ->body('This mentee is already enrolled in this class.')
                                    ->send();

                                return;
                            }

                            $user = User::findOrFail((int) $data['_found_user_id']);
                            $service->enrollInClass($user, $this->class, 'manual');

                            Notification::make()
                                ->success()
                                ->title('Mentee Enrolled')
                                ->body("{$user->name} has been enrolled in {$this->class->name}.")
                                ->send();

                            return;
                        }

                        // ── New user: validate and create ─────────────────────
                        $email = $data['email'];

                        if (User::where('email', $email)->exists()) {
                            Notification::make()
                                ->danger()
                                ->title('Email Already Taken')
                                ->body('A user with this email already exists. Please reload and search again.')
                                ->send();

                            return;
                        }

                        if (empty(trim($data['first_name'] ?? '')) || empty(trim($data['last_name'] ?? ''))) {
                            Notification::make()
                                ->danger()
                                ->title('Name Required')
                                ->body('First and last name are required to create a new account.')
                                ->send();

                            return;
                        }

                        $displayName = trim(implode(' ', array_filter([
                            $data['first_name'],
                            $data['middle_name'] ?? null,
                            $data['last_name'],
                        ])));

                        DB::transaction(function () use ($data, $email, $displayName, $service) {
                            $user = User::create([
                                'first_name' => $data['first_name'],
                                'middle_name' => $data['middle_name'] ?? null,
                                'last_name' => $data['last_name'],
                                'name' => $displayName,
                                'email' => $email,
                                'phone' => $data['phone'] ?? null,
                                'cadre_id' => $data['cadre_id'] ?? null,
                                'department_id' => $data['department_id'] ?? null,
                                'facility_id' => $data['facility_id'] ?? null,
                                'password' => Hash::make('123456'),
                                'status' => 'active',
                                'role' => 'mentee',
                            ]);

                            if (method_exists($user, 'assignRole')) {
                                try {
                                    $user->assignRole('mentee');
                                } catch (\Exception) {

                                }
                            }

                            $service->enrollInClass($user, $this->class, 'manual');
                        });

                        Notification::make()
                            ->success()
                            ->title('Mentee Created & Enrolled')
                            ->body(
                                "{$displayName} has been registered (email: {$email}) and enrolled in {$this->class->name}. ".
                                'Default password: 123456'
                            )
                            ->persistent()
                            ->send();
                    }),
                // ─── Enrollment link ──────────────────────────────────────────────
                Actions\Action::make('enrollment_link')
                    ->label('Enrollment Link')
                    ->icon('heroicon-o-link')
                    ->color('info')
                    ->visible(fn () => ClassParticipant::where('mentorship_class_id', $this->class->id)->exists())
                    ->action(function () {
                        $missing = ClassParticipant::where('mentorship_class_id', $this->class->id)
                            ->whereHas('user', fn ($q) => $q->whereNull('email')->orWhere('email', ''))
                            ->with('user')
                            ->get();

                        if ($missing->isNotEmpty()) {
                            $names = $missing->map(fn ($p) => $p->user?->name ?? 'Unknown')->implode(', ');
                            Notification::make()
                                ->danger()
                                ->title('Missing Emails')
                                ->body("These mentees have no email: {$names}. Update emails first.")
                                ->persistent()
                                ->send();

                            return;
                        }

                        // Generate token if missing, and always activate the link
                        if (! $this->class->enrollment_token) {
                            $this->class->update([
                                'enrollment_token' => \Illuminate\Support\Str::random(32),
                                'enrollment_link_active' => true,
                            ]);
                        } else {
                            // Token exists but flag may still be 0 — ensure it's active
                            $this->class->update(['enrollment_link_active' => true]);
                        }

                        $this->class->refresh();

                        $link = route('mentee.enroll', ['token' => $this->class->enrollment_token]);

                        Notification::make()
                            ->success()
                            ->title('Enrollment Link Active')
                            ->body("Share this link with mentees: {$link}")
                            ->persistent()
                            ->send();
                    }),
                Actions\Action::make('send_all_invitations')
                    ->label('Send Invitations')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('info')
                    ->visible(fn () => ClassParticipant::where('mentorship_class_id', $this->class->id)
                        ->whereHas('user', fn ($q) => $q->whereNotNull('email')->where('email', '!=', ''))
                        ->exists()
                    )
                    ->slideOver()
                    ->modalWidth('md')
                    ->modalHeading('Send Enrollment Invitations')
                    ->modalDescription(new \Illuminate\Support\HtmlString(
                        '<p style="font-size:13.5px;color:#475569;line-height:1.7">
            This will send enrollment invitation emails to your mentees.<br>
            Mentees who have already received an invite will get a <strong>reminder</strong> that
            says <em>"if you\'re already enrolled, ignore this."</em>
        </p>'
                    ))
                    ->form([
                        \Filament\Forms\Components\Radio::make('recipients')
                            ->label('Who should receive the email?')
                            ->options([
                                'all' => 'All mentees with email addresses',
                                'not_sent' => 'Only those not yet invited',
                                'resend' => 'Only those already invited (reminder)',
                            ])
                            ->default('all')
                            ->required(),
                        \Filament\Forms\Components\Placeholder::make('preview')
                            ->label('')
                            ->content(function () {
                                $all = ClassParticipant::where('mentorship_class_id', $this->class->id)
                                    ->whereHas('user', fn ($q) => $q->whereNotNull('email')->where('email', '!=', ''))
                                    ->count();
                                $notSent = ClassParticipant::where('mentorship_class_id', $this->class->id)
                                    ->whereNull('invitation_sent_at')
                                    ->whereHas('user', fn ($q) => $q->whereNotNull('email')->where('email', '!=', ''))
                                    ->count();
                                $resent = $all - $notSent;
                                $noEmail = ClassParticipant::where('mentorship_class_id', $this->class->id)
                                    ->whereHas('user', fn ($q) => $q->whereNull('email')->orWhere('email', ''))
                                    ->count();

                                $html = '<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:14px 16px;font-size:12.5px;color:#475569;line-height:1.8">';
                                $html .= "📧 <strong>{$all}</strong> mentees have email addresses<br>";
                                $html .= "✉️ <strong>{$notSent}</strong> have never received an invitation<br>";
                                $html .= "🔔 <strong>{$resent}</strong> have already been invited<br>";
                                if ($noEmail) {
                                    $html .= "<span style='color:#dc2626'>⚠️ <strong>{$noEmail}</strong> mentee(s) have no email — they will be skipped</span>";
                                }
                                $html .= '</div>';

                                return new \Illuminate\Support\HtmlString($html);
                            }),
                    ])
                    ->action(function (array $data) {
                        $this->ensureEnrollmentToken();

                        $query = ClassParticipant::where('mentorship_class_id', $this->class->id)
                            ->whereHas('user', fn ($q) => $q->whereNotNull('email')->where('email', '!=', ''))
                            ->with('user');

                        if ($data['recipients'] === 'not_sent') {
                            $query->whereNull('invitation_sent_at');
                        } elseif ($data['recipients'] === 'resend') {
                            $query->whereNotNull('invitation_sent_at');
                        }

                        $participants = $query->get();
                        $sent = 0;
                        $resent = 0;
                        $failed = [];

                        foreach ($participants as $record) {
                            $isResend = (bool) $record->invitation_sent_at;
                            try {
                                Mail::to($record->user->email)
                                    ->send(new MenteeEnrollmentInvitationMail(
                                        $record->user,
                                        $this->class,
                                        $record,
                                        $isResend
                                    ));

                                $record->update(['invitation_sent_at' => now()]);
                                $isResend ? $resent++ : $sent++;
                            } catch (\Exception $e) {
                                $failed[] = $record->user->full_name;
                            }
                        }

                        $parts = [];
                        if ($sent) {
                            $parts[] = "{$sent} new invitation(s) sent";
                        }
                        if ($resent) {
                            $parts[] = "{$resent} reminder(s) sent";
                        }

                        Notification::make()
                            ->success()
                            ->title('Done!')
                            ->body(implode(' · ', $parts) ?: 'No emails sent.')
                            ->send();

                        if ($failed) {
                            Notification::make()
                                ->warning()
                                ->title(count($failed).' email(s) failed')
                                ->body('Failed: '.implode(', ', $failed))
                                ->persistent()
                                ->send();
                        }
                    }),
            ])->label('Enroll Mentees')
                ->icon('heroicon-o-user-group')
                ->color('primary')
                ->button(),
            Actions\Action::make('start_class')
                ->label('Start Class')
                ->icon('heroicon-o-play')
                ->color('success')
                ->visible(fn () => $this->class->status === 'draft')
                ->requiresConfirmation()
                ->modalHeading(function () {
                    $hasMentees = ClassParticipant::where('mentorship_class_id', $this->class->id)->exists();

                    return $hasMentees ? 'Start This Class?' : '⚠️ Cannot Start — No Mentees';
                })
                ->modalDescription(function () {
                    $menteeCount = ClassParticipant::where('mentorship_class_id', $this->class->id)->count();
                    $moduleCount = $this->class->classModules()->count();

                    if ($menteeCount === 0) {
                        return new \Illuminate\Support\HtmlString('
                <div style="display:flex;flex-direction:column;gap:12px;">
                    <div style="background:#fef9c3;border:1px solid #fde047;border-radius:10px;padding:14px 16px;font-size:0.875rem;color:#713f12;line-height:1.6;">
                        <strong>This class has no enrolled mentees.</strong><br>
                        A class cannot be started without at least one mentee — there would be no one to track attendance for.
                    </div>
                    <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:14px 16px;font-size:0.875rem;color:#1e3a8a;line-height:1.75;">
                        <strong>How to add mentees on this page:</strong><br>
                        <span style="display:inline-flex;align-items:center;gap:6px;margin-top:4px;">
                            <span style="background:#3b82f6;color:#fff;border-radius:6px;padding:2px 8px;font-size:0.75rem;font-weight:600;">Add from List</span>
                            — Find and enrol users already registered in the system
                        </span><br>
                        <span style="display:inline-flex;align-items:center;gap:6px;margin-top:6px;">
                            <span style="background:#22c55e;color:#fff;border-radius:6px;padding:2px 8px;font-size:0.75rem;font-weight:600;">Add Mentee</span>
                            — Create a brand new user account and enrol them directly
                        </span>
                    </div>
                    <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:10px 14px;font-size:0.78rem;color:#64748b;">
                        Close this dialog and use the buttons at the top right of this page.
                    </div>
                </div>
            ');
                    }

                    if ($moduleCount === 0) {
                        return new \Illuminate\Support\HtmlString('
                <div style="background:#fef9c3;border:1px solid #fde047;border-radius:10px;padding:14px 16px;font-size:0.875rem;color:#713f12;line-height:1.6;">
                    <strong>This class has no modules assigned.</strong><br>
                    Go to the <strong>Modules</strong> tab to add modules before starting the class.
                </div>
            ');
                    }

                    return "This will activate the class and start all {$moduleCount} module(s) for {$menteeCount} mentee(s). "
                            .'Attendance links will open for each module. '
                            .'The enrollment link stays open so mentees can still join.';
                })
                ->modalSubmitActionLabel(function () {
                    $hasMentees = ClassParticipant::where('mentorship_class_id', $this->class->id)->exists();
                    $hasModules = $this->class->classModules()->exists();

                    return ($hasMentees && $hasModules) ? 'Yes, Start Class' : 'Close';
                })
                ->action(function () {
                    $menteeCount = ClassParticipant::where('mentorship_class_id', $this->class->id)->count();
                    $moduleCount = $this->class->classModules()->count();

                    // ── Block: no mentees ────────────────────────────────────────────
                    if ($menteeCount === 0) {
                        Notification::make()
                            ->warning()
                            ->title('Add Mentees First')
                            ->body('Use "Add from List" to enrol existing users, or "Add Mentee" to create a new account.')
                            ->persistent()
                            ->send();

                        return;
                    }

                    // ── Block: no modules ────────────────────────────────────────────
                    if ($moduleCount === 0) {
                        Notification::make()
                            ->warning()
                            ->title('No Modules Assigned')
                            ->body('Go to the Modules page and assign at least one module before starting.')
                            ->persistent()
                            ->send();

                        return;
                    }

                    // ── All clear → start ────────────────────────────────────────────
                    try {
                        $this->class->start();
                        $this->class = $this->class->fresh();

                        Notification::make()
                            ->success()
                            ->title('Class Started')
                            ->body("All {$moduleCount} module(s) now in progress. Attendance active for {$menteeCount} mentee(s).")
                            ->send();
                    } catch (\LogicException $e) {
                        Notification::make()
                            ->danger()
                            ->title('Cannot Start Class')
                            ->body($e->getMessage())
                            ->send();
                    }
                }),
            Actions\Action::make('end_class')
                ->label('End Class')
                ->icon('heroicon-o-stop')
                ->color('danger')
                ->visible(fn () => $this->class->status === 'active')
                ->requiresConfirmation()
                ->modalHeading('End This Class?')
                ->modalDescription(
                    'This will permanently close the class. '.
                    'All modules will be marked complete, attendance links will stop working, '.
                    'and the enrollment link will be deactivated. '.
                    'This action cannot be undone.'
                )
                ->modalSubmitActionLabel('Yes, End Class')
                ->action(function () {
                    try {
                        $this->class->complete();
                        $this->class = $this->class->fresh();

                        Notification::make()
                            ->success()
                            ->title('Class Ended')
                            ->body('All modules completed. Enrollment and attendance links are now inactive.')
                            ->send();
                    } catch (\LogicException $e) {
                        Notification::make()
                            ->danger()
                            ->title('Cannot End Class')
                            ->body($e->getMessage())
                            ->send();
                    }
                }),
            Actions\Action::make('view_report')
                ->label('Class Report')
                ->icon('heroicon-o-document-chart-bar')
                ->color('info')
                ->url(fn () => route('reports.class.html', $this->class->id))
                ->openUrlInNewTab(),
            //                    ])
            //                    ->label('Actions')
            //                    ->icon('heroicon-o-ellipsis-vertical')
            //                    ->color('gray')
            //                    ->button(),
        ];
    }

    private function ensureEnrollmentToken(): void
    {
        if (! $this->class->enrollment_token) {
            $this->class->update([
                'enrollment_token' => Str::random(32),
                'enrollment_link_active' => true,
            ]);
        } elseif (! $this->class->enrollment_link_active) {
            $this->class->update(['enrollment_link_active' => true]);
        }

        $this->class->refresh();
    }

    private function isEmonc(): bool
    {
        $program = Program::find($this->training->program_id);

        return $program?->isEmonc() ?? false;
    }

    /**
     * Builds the "Add from List" id => label options, with already-selected
     * mentees (including pre-checked, already-enrolled ones) pinned to the
     * top so they stay visible even if a later search/page filters them out
     * of the main results.
     */
    private function menteeListOptions(?string $search, int $page, array $selectedIds): array
    {
        $perPage = 25;

        $selected = empty($selectedIds)
            ? collect()
            : User::whereIn('id', $selectedIds)->with('facility')->orderBy('first_name')->get();

        $query = User::query()
            ->where('status', 'active')
            ->with(['facility'])
            ->orderBy('first_name');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhereHas('facility', function ($facilityQuery) use ($search) {
                        $facilityQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('mfl_code', 'like', "%{$search}%");
                    });
            });
        }

        $results = $query->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get()
            ->reject(fn ($u) => in_array($u->id, $selectedIds));

        return $selected->concat($results)
            ->mapWithKeys(fn ($u) => [$u->id => $this->formatMenteeLabel($u)])
            ->toArray();
    }

    private function formatMenteeLabel(User $u): string
    {
        return implode(' · ', array_filter([
            $u->name,
            $u->phone,
            $u->email,
            $u->facility ? "{$u->facility->name}".
                ($u->facility->mfl_code ? " (MFL {$u->facility->mfl_code})" : '') : null,
        ]));
    }

    private function canMentorApprove(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ($user->hasRole(['super_admin', 'admin'])) {
            return true;
        }

        return $this->class->training->mentor_id === $user->id
            || $this->class->training->isCoMentor($user->id);
    }

    private function canHeadDrmhCertify(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->can('page_HeadDrmhDashboard');
    }

    private function isReadyForMentorApproval(ClassParticipant $participant): bool
    {
        return $participant->hasCompletedAllModules();
    }

    private function downloadCertificateZip($participants): StreamedResponse
    {
        $class = $this->class->load(['training.program', 'training.facility', 'training.mentor']);
        $modules = $class->classModules()->with('programModule')->orderBy('order_sequence')->get();

        return Response::streamDownload(function () use ($participants, $class, $modules) {
            $tmpFile = tempnam(sys_get_temp_dir(), 'certs_').'.zip';
            $zip = new ZipArchive;
            $zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);

            foreach ($participants as $participant) {
                $participant->load('user');
                $html = view('certificates.completion', compact('class', 'participant', 'modules'))->render();
                $pdf = Browsershot::html($html)
                    ->setChromePath(config('browsershot.chrome_path', '/usr/bin/chromium-browser'))
                    ->addChromiumArguments(['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage'])
                    ->landscape()
                    ->format('A4')
                    ->margins(0, 0, 0, 0)
                    ->pdf();

                $name = str($participant->user->name ?? 'mentee')->slug();
                $zip->addFromString("certificate-{$name}.pdf", $pdf);
            }

            $zip->close();
            echo file_get_contents($tmpFile);
            unlink($tmpFile);
        }, 'certificates-'.str($class->name)->slug().'-'.now()->format('Y-m-d').'.zip');
    }

    private function exportProgressCsv(): StreamedResponse
    {
        $class = $this->class->load(['training.program']);
        $modules = $class->classModules()->with('programModule.parent')->orderBy('order_sequence')->get();
        $participants = ClassParticipant::with('user')->where('mentorship_class_id', $class->id)->get();
        $progress = MenteeModuleProgress::whereIn('class_module_id', $modules->pluck('id'))
            ->whereIn('class_participant_id', $participants->pluck('id'))
            ->get()
            ->keyBy(fn ($p) => "{$p->class_participant_id}:{$p->class_module_id}");

        return Response::streamDownload(function () use ($participants, $modules, $progress) {
            $csv = Writer::createFromString('');
            $headers = ['Name', 'Email', 'Phone', 'Status'];
            foreach ($modules as $module) {
                $headers[] = $module->programModule?->name ?? 'Module';
            }
            $headers[] = 'Mentor Approved';
            $headers[] = 'Head DRMH Approved';
            $headers[] = 'Certified';
            $csv->insertOne($headers);

            foreach ($participants as $participant) {
                $row = [
                    $participant->user?->name ?? '—',
                    $participant->user?->email ?? '—',
                    $participant->user?->phone ?? '—',
                    $participant->status,
                ];

                foreach ($modules as $module) {
                    $key = "{$participant->id}:{$module->id}";
                    $prog = $progress->get($key);
                    $row[] = $prog ? ucfirst(str_replace('_', ' ', $prog->status)) : 'Not Started';
                }

                $row[] = $participant->mentor_approved_at ? 'Yes' : 'No';
                $row[] = $participant->head_drmh_approved_at ? 'Yes' : 'No';
                $row[] = $participant->isCertified() ? 'Yes' : 'No';
                $csv->insertOne($row);
            }

            echo $csv->toString();
        }, 'class-progress-'.str($class->name)->slug().'-'.now()->format('Y-m-d').'.csv');
    }
}
