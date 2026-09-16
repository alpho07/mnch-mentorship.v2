<?php

namespace App\Filament\Resources\AssessmentResource\Actions;

use App\Models\Assessment;
use App\Models\User;
use App\Services\AssessmentTeamService;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;

class ManageTeamAction {

    /**
     * Build the full "Manage Team" slide-over action.
     * Inject $record (Assessment) at call site via ->record() or closure.
     */
    public static function make(Assessment $assessment): Action {
        $service = app(AssessmentTeamService::class);
        $actorId = auth()->id();
        $actor = auth()->user();
        $isSuperior = $actor->hasRole(['super_admin', 'admin', 'division']);
        $canManage = $assessment->canManageTeam($actorId);

        return Action::make('manage_team')
                        ->label('Manage Team')
                        ->icon('heroicon-o-user-group')
                        ->color('primary')
                        ->slideOver()
                        ->modalWidth('lg')
                        ->modalHeading('Assessment Team')
                        ->modalDescription(new HtmlString(
                                        '<span class="text-sm text-gray-500">Team members can view and carry out this assessment. The team lead manages membership.</span>'
                        ))
                        ->form(function () use ($assessment, $service, $canManage, $isSuperior): array {
                            $team = $service->getTeamForDisplay($assessment);

                            // Build the current team display HTML
                            $teamHtml = self::buildTeamHtml($team, $assessment, $canManage, $isSuperior);

                            $fields = [
                                        Placeholder::make('current_team')
                                        ->label('Current Team')
                                        ->content(new HtmlString($teamHtml))
                                        ->columnSpanFull(),
                            ];

                            if ($canManage) {
                                // Eligible users to add (Assessor role, not already on team)
                                $eligible = $service->getEligibleUsers($assessment);

                                $fields[] = Section::make('Add Team Members')
                                        ->description('Only users with the Assessor role are shown.')
                                        ->schema([
                                            CheckboxList::make('new_member_ids')
                                            ->label('Select Assessors to Add')
                                            ->options(
                                                    $eligible->mapWithKeys(fn($u) => [
                                                        $u->id => self::formatUserLabel($u),
                                                            ])->toArray()
                                            )
                                            ->searchable()
                                            ->columns(1)
                                            ->helperText($eligible->isEmpty() ? 'No eligible assessors available to add.' : null),
                                        ])
                                        ->collapsible()
                                        ->collapsed($eligible->isEmpty());
                            }

                            return $fields;
                        })
                        ->action(function (array $data) use ($assessment, $service, $actorId, $canManage): void {
                            if (!$canManage) {
                                Notification::make()
                                        ->title('Permission denied')
                                        ->danger()
                                        ->send();
                                return;
                            }

                            $added = 0;

                            foreach ($data['new_member_ids'] ?? [] as $userId) {
                                try {
                                    $service->addMember($assessment, (int) $userId, $actorId);
                                    $added++;
                                } catch (\Exception $e) {
                                    Notification::make()
                                            ->title('Could not add member')
                                            ->body($e->getMessage())
                                            ->warning()
                                            ->send();
                                }
                            }

                            if ($added > 0) {
                                Notification::make()
                                        ->title("{$added} member(s) added to the team")
                                        ->success()
                                        ->send();
                            }
                        })
                        ->modalSubmitActionLabel($canManage ? 'Add Selected Members' : 'Close')
                        ->modalCancelActionLabel('Cancel')
                        ->extraModalFooterActions(function () use ($assessment, $service, $actorId, $canManage, $isSuperior): array {
                            $extras = [];

                            if ($canManage) {
                                // Promote member to team lead action
                                $members = $assessment->teamMembersOnly()->get();

                                if ($members->isNotEmpty()) {
                                    $extras[] = Action::make('promote_team_lead')
                                            ->label('Transfer Lead Role')
                                            ->icon('heroicon-o-arrow-up-circle')
                                            ->color('warning')
                                            ->form([
                                                Select::make('new_lead_id')
                                                ->label('Promote to Team Lead')
                                                ->options(
                                                        $members->mapWithKeys(fn($u) => [
                                                            $u->id => self::formatUserLabel($u),
                                                                ])->toArray()
                                                )
                                                ->required()
                                                ->searchable()
                                                ->helperText('The current team lead will become a regular member.'),
                                            ])
                                            ->action(function (array $data) use ($assessment, $service, $actorId): void {
                                                try {
                                                    $service->promoteToTeamLead($assessment, (int) $data['new_lead_id'], $actorId);
                                                    $user = User::find($data['new_lead_id']);
                                                    Notification::make()
                                                            ->title("{$user->name} is now the team lead")
                                                            ->success()
                                                            ->send();
                                                } catch (\Exception $e) {
                                                    Notification::make()
                                                            ->title('Could not promote')
                                                            ->body($e->getMessage())
                                                            ->danger()
                                                            ->send();
                                                }
                                            })
                                            ->modalHeading('Transfer Team Lead Role')
                                            ->requiresConfirmation()
                                            ->modalDescription('The current team lead will become a regular member. This cannot be undone without their consent.');
                                }

                                // Remove member action
                                $removable = $assessment->teamMembers()
                                        ->where('users.id', '!=', $actorId)
                                        ->get();

                                if ($removable->isNotEmpty()) {
                                    $extras[] = Action::make('remove_member')
                                            ->label('Remove Member')
                                            ->icon('heroicon-o-user-minus')
                                            ->color('danger')
                                            ->form([
                                                Select::make('remove_user_id')
                                                ->label('Member to Remove')
                                                ->options(
                                                        $removable->mapWithKeys(fn($u) => [
                                                            $u->id => self::formatUserLabel($u) . ($u->pivot->role === 'team_lead' ? ' (Lead)' : ''),
                                                                ])->toArray()
                                                )
                                                ->required()
                                                ->searchable(),
                                            ])
                                            ->action(function (array $data) use ($assessment, $service, $actorId): void {
                                                try {
                                                    $service->removeMember($assessment, (int) $data['remove_user_id'], $actorId);
                                                    $user = User::find($data['remove_user_id']);
                                                    Notification::make()
                                                            ->title(($user?->name ?? 'Member') . ' removed from team')
                                                            ->success()
                                                            ->send();
                                                } catch (\Exception $e) {
                                                    Notification::make()
                                                            ->title('Could not remove member')
                                                            ->body($e->getMessage())
                                                            ->danger()
                                                            ->send();
                                                }
                                            })
                                            ->modalHeading('Remove Team Member')
                                            ->requiresConfirmation()
                                            ->modalDescription('This person will lose access to the assessment immediately.');
                                }
                            }

                            return $extras;
                        });
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    private static function buildTeamHtml($team, Assessment $assessment, bool $canManage, bool $isSuperior): string {
        if ($team->isEmpty()) {
            return '<p class="text-sm text-gray-500 italic">No team members yet.</p>';
        }

        $html = '<div class="space-y-2">';

        foreach ($team as $member) {
            $isLead = $member->pivot->role === 'team_lead';
            $badgeClass = $isLead ? 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300';
            $roleLabel = $isLead ? 'Team Lead' : 'Member';
            $addedAt = $member->pivot->added_at ? \Carbon\Carbon::parse($member->pivot->added_at)->format('d M Y') : '—';

            $html .= <<<HTML
            <div class="flex items-center justify-between rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2.5 shadow-sm">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="flex-shrink-0 w-8 h-8 rounded-full bg-primary-500 text-white flex items-center justify-center text-sm font-semibold">
                        {$member->initials}
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{$member->name}</p>
                        <p class="text-xs text-gray-500 truncate">{$member->email}</p>
                    </div>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0 ml-2">
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {$badgeClass}">
                        {$roleLabel}
                    </span>
                    <span class="text-xs text-gray-400">Added {$addedAt}</span>
                </div>
            </div>
            HTML;
        }

        $html .= '</div>';

        return $html;
    }

    private static function formatUserLabel(User $user): string {
        $facility = $user->facility?->name ?? 'No facility';
        return "{$user->name} — {$facility}";
    }
}
