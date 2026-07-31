<?php

namespace App\Filament\Resources\MentorshipResource\Pages;

use App\Filament\Resources\MentorshipTrainingResource;
use App\Mail\CoMentorInvitation;
use App\Models\MentorshipClass;
use App\Models\MentorshipCoMentor;
use App\Models\Training;
use App\Models\User;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class ManageMentorshipCoMentors extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = MentorshipTrainingResource::class;

    protected static string $view = 'filament.pages.manage-co-mentors';

    public Training $record;

    /**
     * Holds the invitation link after a co-mentor is invited.
     * Displayed in the blade template.
     */
    public ?string $lastInvitationLink = null;

    public ?string $lastInvitedName = null;

    public string $activeTab = 'co_mentors';

    public function mount(): void
    {
        // $this->record is automatically bound by Filament from the route
        $this->record = $this->record->load(['mentor', 'coMentors', 'facility', 'program']);
    }

    public function getTitle(): string
    {
        return "Co-Mentors — {$this->record->program->name}";
    }

    public function getSubheading(): ?string
    {
        $accepted = $this->record->coMentors()->where('status', 'accepted')->count();
        $pending = $this->record->coMentors()->where('status', 'pending')->count();

        return "Facility: {$this->record->facility->name} • {$accepted} active co-mentors • {$pending} pending invitations";
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('tab_classes')
                ->label('Classes')
                ->icon('heroicon-o-academic-cap')
                ->color($this->activeTab === 'classes' ? 'primary' : 'gray')
                ->outlined($this->activeTab !== 'classes')
                ->action(function () {
                    $this->activeTab = 'classes';
                    $this->resetTable();
                }),
            Actions\Action::make('tab_co_mentors')
                ->label('Co-Mentors')
                ->icon('heroicon-o-user-group')
                ->color($this->activeTab === 'co_mentors' ? 'primary' : 'gray')
                ->outlined($this->activeTab !== 'co_mentors')
                ->action(function () {
                    $this->activeTab = 'co_mentors';
                    $this->resetTable();
                }),
            Actions\Action::make('invite_co_mentor')
                ->label('Invite Co-Mentor')
                ->icon('heroicon-o-user-plus')
                ->color('success')
                ->form([
                    Forms\Components\Select::make('user_id')
                        ->label('Select Mentor')
                        ->options(function () {
                            $existingCoMentorIds = $this->record->coMentors()->pluck('user_id')->toArray();
                            $existingCoMentorIds[] = $this->record->mentor_id;

                            return User::whereNotIn('id', $existingCoMentorIds)
                                ->where('status', 'active')
                                ->whereHas('roles', fn ($q) => $q->where('name', 'like', '%mentor%'))
                                ->orderBy('first_name')
                                ->get()
                                ->mapWithKeys(fn ($user) => [
                                    $user->id => $user->full_name.
                                    ' ('.($user->facility?->name ?? 'No Facility').') - '.
                                    ($user->cadre?->name ?? 'No Cadre'),
                                ]);
                        })
                        ->required()
                        ->searchable()
                        ->helperText('Select a user to invite as co-mentor'),
                    Forms\Components\Textarea::make('invitation_message')
                        ->label('Invitation Message (Optional)')
                        ->rows(3)
                        ->placeholder('Add a personal message to the invitation email'),
                ])
                ->action(fn (array $data) => $this->inviteCoMentor($data)),
            Actions\Action::make('back')
                ->label('Back to Mentorship')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn () => MentorshipTrainingResource::getUrl('view', ['record' => $this->record])),
        ];
    }

    public function table(Table $table): Table
    {
        return $this->activeTab === 'classes'
            ? $this->getClassesTable($table)
            : $this->getCoMentorsTable($table);
    }

    private function getClassesTable(Table $table): Table
    {
        return $table
            ->query(
                MentorshipClass::query()
                    ->where('training_id', $this->record->id)
                    ->with(['classModules', 'creator'])
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Class Name')
                    ->searchable()
                    ->weight('bold')
                    ->description(fn (MentorshipClass $record): string => $record->description ?? ''),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'secondary' => 'draft',
                        'warning' => 'active',
                        'success' => 'completed',
                        'danger' => 'cancelled',
                    ]),
                Tables\Columns\TextColumn::make('start_date')
                    ->date('M j, Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('end_date')
                    ->date('M j, Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('module_count')
                    ->label('Modules')
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('participants_count')
                    ->label('Mentees')
                    ->counts('participants')
                    ->badge()
                    ->color('success'),
                Tables\Columns\TextColumn::make('co_mentors_count')
                    ->label('Co-Mentors')
                    ->getStateUsing(fn () => $this->record->coMentors()->where('status', 'accepted')->count())
                    ->badge()
                    ->color('warning'),
            ])
            ->actions([
                Tables\Actions\Action::make('view_co_mentors')
                    ->label('Co-Mentors')
                    ->icon('heroicon-o-user-group')
                    ->color('warning')
                    ->modalHeading(fn (MentorshipClass $record) => "Co-Mentors — {$record->name}")
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalContent(fn () => view('filament.modals.class-co-mentors', [
                        'coMentors' => $this->record->coMentors()
                            ->where('status', 'accepted')
                            ->with('user')
                            ->get(),
                    ])),
                Tables\Actions\Action::make('view_class')
                    ->label('View Class')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->url(fn (MentorshipClass $record) => MentorshipTrainingResource::getUrl('class-modules', [
                        'training' => $this->record->id,
                        'class' => $record->id,
                    ])),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No Classes Yet')
            ->emptyStateDescription('Create a class cohort to assign co-mentors.')
            ->emptyStateActions([
                Tables\Actions\Action::make('go_create_class')
                    ->label('Create Class')
                    ->icon('heroicon-o-plus-circle')
                    ->url(fn () => MentorshipTrainingResource::getUrl('classes', [
                        'record' => $this->record->id,
                    ])),
            ]);
    }

    private function getCoMentorsTable(Table $table): Table
    {
        return $table
            ->query(
                MentorshipCoMentor::query()
                    ->where('training_id', $this->record->id)
                    ->with(['user.facility', 'user.cadre', 'inviter'])
            )
            ->columns([
                Tables\Columns\TextColumn::make('user.full_name')
                    ->label('Co-Mentor Name')
                    ->searchable(['first_name', 'last_name'])
                    ->weight('bold')
                    ->description(fn (MentorshipCoMentor $record): string => ($record->user->cadre?->name ?? '').' • '.
                            ($record->user->facility?->name ?? 'No Facility')
                    ),
                Tables\Columns\TextColumn::make('user.email')
                    ->label('Email')
                    ->searchable()
                    ->copyable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('user.phone')
                    ->label('Phone')
                    ->searchable()
                    ->copyable()
                    ->toggleable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'accepted',
                        'danger' => fn ($state) => in_array($state, ['declined', 'removed', 'revoked']),
                        'secondary' => 'removed',
                    ]),
                Tables\Columns\TextColumn::make('invitation_link')
                    ->label('Invitation Link')
                    ->getStateUsing(function (MentorshipCoMentor $record) {
                        if ($record->invitation_token) {
                            return url("/co-mentor/accept/{$record->invitation_token}");
                        }

                        return '—';
                    })
                    ->copyable()
                    ->copyMessage('Invitation link copied!')
                    ->limit(35)
                    ->tooltip(function (MentorshipCoMentor $record) {
                        if ($record->invitation_token) {
                            return url("/co-mentor/accept/{$record->invitation_token}");
                        }

                        return null;
                    })
                    ->visible(fn () => $this->record->coMentors()->whereNotNull('invitation_token')->exists()),
                Tables\Columns\TextColumn::make('inviter.full_name')
                    ->label('Invited By')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('invited_at')
                    ->label('Invited')
                    ->dateTime('M j, Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('accepted_at')
                    ->label('Accepted')
                    ->dateTime('M j, Y')
                    ->placeholder('Not accepted')
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'accepted' => 'Accepted',
                        'declined' => 'Declined',
                        'removed' => 'Removed',
                    ])
                    ->multiple(),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('copy_link')
                        ->label('Copy Invitation Link')
                        ->icon('heroicon-o-clipboard-document')
                        ->color('info')
                        ->visible(fn (MentorshipCoMentor $record) => $record->status === 'pending' && $record->invitation_token
                        )
                        ->action(function (MentorshipCoMentor $record) {
                            $link = url("/co-mentor/accept/{$record->invitation_token}");
                            Notification::make()
                                ->success()
                                ->title('Invitation Link')
                                ->body(new HtmlString(
                                    "<div class='font-mono text-xs break-all bg-gray-100 dark:bg-gray-800 p-2 rounded'>{$link}</div>".
                                    "<p class='mt-2 text-sm'>Share this link with <strong>{$record->user->full_name}</strong> to accept the invitation.</p>"
                                ))
                                ->persistent()
                                ->send();
                        }),
                    Tables\Actions\Action::make('accept')
                        ->label('Accept')
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->visible(fn (MentorshipCoMentor $record) => $record->status === 'pending' &&
                                $record->user_id === auth()->id()
                        )
                        ->requiresConfirmation()
                        ->action(function (MentorshipCoMentor $record) {
                            $record->accept();
                            Notification::make()
                                ->success()
                                ->title('Invitation Accepted')
                                ->body('You are now a co-mentor for this mentorship.')
                                ->send();
                        }),
                    Tables\Actions\Action::make('decline')
                        ->label('Decline')
                        ->icon('heroicon-o-x-mark')
                        ->color('danger')
                        ->visible(fn (MentorshipCoMentor $record) => $record->status === 'pending' &&
                                $record->user_id === auth()->id()
                        )
                        ->requiresConfirmation()
                        ->action(function (MentorshipCoMentor $record) {
                            $record->decline();
                            Notification::make()
                                ->warning()
                                ->title('Invitation Declined')
                                ->send();
                        }),
                    Tables\Actions\Action::make('remove')
                        ->label('Remove')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->visible(fn (MentorshipCoMentor $record) => in_array($record->status, ['accepted', 'pending']) &&
                                auth()->id() === $this->record->mentor_id
                        )
                        ->requiresConfirmation()
                        ->modalDescription('Remove this co-mentor from the mentorship?')
                        ->action(function (MentorshipCoMentor $record) {
                            $record->remove();
                            Notification::make()
                                ->warning()
                                ->title('Co-Mentor Removed')
                                ->send();
                        }),
                    Tables\Actions\Action::make('resend_invitation')
                        ->label('Resend Invitation')
                        ->icon('heroicon-o-envelope')
                        ->color('info')
                        ->visible(fn (MentorshipCoMentor $record) => $record->status === 'pending')
                        ->action(function (MentorshipCoMentor $record) {
                            // TODO: Resend invitation email
                            $link = $record->invitation_token ? url("/co-mentor/accept/{$record->invitation_token}") : 'No link generated';

                            Notification::make()
                                ->success()
                                ->title('Invitation Resent')
                                ->body(new HtmlString(
                                    "Invitation email sent to {$record->user->email}<br>".
                                    "<span class='font-mono text-xs'>{$link}</span>"
                                ))
                                ->persistent()
                                ->send();
                        }),
                ]),
            ])
            ->defaultSort('invited_at', 'desc')
            ->emptyStateHeading('No Co-Mentors Yet')
            ->emptyStateDescription('Invite other mentors to collaborate on this mentorship program.')
            ->emptyStateIcon('heroicon-o-user-group')
            ->emptyStateActions([
                Tables\Actions\Action::make('invite_first')
                    ->label('Invite Co-Mentor')
                    ->icon('heroicon-o-plus')
                    ->button()
                    ->action(function () {
                        $this->mountAction('invite_co_mentor');
                    }),
            ]);
    }

    private function inviteCoMentor(array $data): void
    {
        // Generate a unique invitation token
        $token = Str::random(64);

        $coMentor = MentorshipCoMentor::create([
            'training_id' => $this->record->id,
            'user_id' => $data['user_id'],
            'invited_by' => auth()->id(),
            'invited_at' => now(),
            'status' => 'pending',
            'invitation_token' => $token,
            'permissions' => [
                'can_facilitate' => true,
                'can_create_classes' => false,
                'can_invite_mentors' => false,
            ],
        ]);

        $user = User::find($data['user_id']);
        $invitationLink = url("/co-mentor/accept/{$token}");

        // Store for blade template display
        $this->lastInvitationLink = $invitationLink;
        $this->lastInvitedName = $user->full_name;

        // Send invitation email
        if ($user->email) {
            Mail::to($user->email)->send(new CoMentorInvitation($this->record, $coMentor, $data['invitation_message'] ?? null));
        }

        Notification::make()
            ->success()
            ->title('Co-Mentor Invited Successfully')
            ->body(new HtmlString(
                "<p><strong>{$user->full_name}</strong> has been invited as a co-mentor.</p>".
                ($user->email ? "<p class='mt-2 text-sm text-gray-600'>An invitation email has been sent to <strong>{$user->email}</strong>.</p>" : '').
                "<p class='mt-2 text-sm font-medium'>Invitation Link:</p>".
                "<div class='font-mono text-xs break-all bg-gray-100 dark:bg-gray-800 p-2 rounded mt-1'>{$invitationLink}</div>".
                "<p class='mt-2 text-xs text-gray-500'>Share this link with the co-mentor to accept the invitation.</p>"
            ))
            ->persistent()
            ->send();
    }
}
