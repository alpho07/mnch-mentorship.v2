<?php

namespace App\Filament\Resources\MentorshipResource\Pages;

use App\Filament\Resources\MentorshipTrainingResource;
use App\Models\ClassParticipant;
use App\Models\Setting;
use App\Models\Training;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListMentorshipTrainings extends ListRecords
{
    protected static string $resource = MentorshipTrainingResource::class;

    // static protected string|null $breadcrumb = 'Mentorship';

    protected function getHeaderActions(): array
    {
        $newMentorshipEnabled = Setting::getBool(Setting::NEW_MENTORSHIP_BUTTON_ENABLED);
        $guidedSetupEnabled = Setting::getBool(Setting::GUIDED_SETUP_BUTTON_ENABLED);
        $chatSetupEnabled = Setting::getBool(Setting::CHAT_SETUP_BUTTON_ENABLED);
        $mnchgptEnabled = Setting::getBool(Setting::MNCHGPT_BUTTON_ENABLED);
        $quickSetupEnabled = Setting::getBool(Setting::QUICK_SETUP_BUTTON_ENABLED);

        return [
            Actions\CreateAction::make()
                ->label('New Mentorship')
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->visible($newMentorshipEnabled),
            Actions\Action::make('guided_setup')
                ->label('New Mentorship Guided Setup')
                ->icon('heroicon-o-sparkles')
                ->color('gray')
                ->url(fn () => MentorshipTrainingResource::getUrl('guided-setup'))
                ->visible($guidedSetupEnabled),
            Actions\Action::make('chat_setup')
                ->label('Chat Setup')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('gray')
                ->url(fn () => MentorshipTrainingResource::getUrl('chat-setup'))
                ->visible($chatSetupEnabled),
            Actions\Action::make('mnchgpt_setup')
                ->label('MNCHGPT')
                ->icon('heroicon-o-sparkles')
                ->color('gray')
                ->url(fn () => MentorshipTrainingResource::getUrl('mnchgpt-setup'))
                ->visible($mnchgptEnabled),
            Actions\Action::make('quick_setup')
                ->label('Quick Setup')
                ->icon('heroicon-o-bolt')
                ->color('gray')
                ->url(fn () => MentorshipTrainingResource::getUrl('quick-setup'))
                ->visible($quickSetupEnabled),
        ];
    }

    public function getTitle(): string
    {
        return 'Mentorships';
    }

    public function getSubheading(): ?string
    {
        $stats = $this->getQuickStats();

        return "Facility-based mentorships • {$stats['total']} total • {$stats['active']} active • {$stats['mentees']} mentees";
    }

    protected function getHeaderWidgets(): array
    {
        // When Home analytics tab is active, swap to the embed widget only
        if (request()->get('tab') === 'home') {
            return [\App\Filament\Widgets\MentorshipAnalyticsEmbed::class];
        }

        return [
            \App\Filament\Widgets\PendingGuidedSetupNotice::class,
            \App\Filament\Widgets\MentorshipGuidanceNotice::class,
            \App\Filament\Widgets\MentorshipStatsOverview::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }

    public function getTabs(): array
    {
        $user = auth()->user();
        $isSuperAdmin = $user->hasRole('super_admin');

        $liveCount = $this->getScopedBaseQuery()->where('is_pilot', false)->count();
        $pilotCount = $this->getScopedBaseQuery()->where('is_pilot', true)->count();
        $trashCount = $isSuperAdmin
            ? Training::onlyTrashed()->where('type', 'facility_mentorship')->count()
            : 0;

        $tabs = [
            'live' => Tab::make('Live Mentorships')
                ->icon('heroicon-o-academic-cap')
                ->badge($liveCount ?: null)
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->whereNull('trainings.deleted_at')
                    ->where('is_pilot', false)),

            'pilots' => Tab::make('Pilot Runs')
                ->icon('heroicon-o-beaker')
                ->badge($pilotCount ?: null)
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->whereNull('trainings.deleted_at')
                    ->where('is_pilot', true)),
        ];

        if ($isSuperAdmin) {
            $tabs['trash'] = Tab::make('Trash')
                ->icon('heroicon-o-trash')
                ->badge($trashCount ?: null)
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNotNull('trainings.deleted_at'));
        }

        $tabs['home'] = Tab::make('Home')
            ->icon('heroicon-o-chart-bar-square')
            ->modifyQueryUsing(fn (Builder $query) => $query->whereRaw('1 = 0'));

        return $tabs;
    }

    /**
     * Build a base query scoped by role: admins see all, others see only their own.
     */
    protected function getScopedBaseQuery(): Builder
    {
        $query = Training::where('type', 'facility_mentorship');

        $user = auth()->user();
        if (! $user->hasRole(['super_admin', 'admin', 'division'])) {
            $query->forMentorOrCoMentor($user->id);
        }

        return $query;
    }

    protected function getQuickStats(): array
    {
        $live = fn () => $this->getScopedBaseQuery()->where('is_pilot', false);

        return [
            'total' => $live()->count(),
            'active' => $live()->where(function (Builder $query) {
                $query->whereIn('status', ['active', 'ongoing'])
                    ->orWhereHas('mentorshipClasses', fn (Builder $q) => $q->where('status', 'active'));
            })->count(),
            'completed' => $live()->where('status', 'completed')->count(),
            'draft' => $live()->whereIn('status', ['draft', 'new'])->count(),
            'upcoming' => $live()->where('start_date', '>', now())->count(),
            'mentees' => $this->getScopedMenteesQuery()->distinct('class_participants.user_id')->count('class_participants.user_id'),
        ];
    }

    protected function getScopedMenteesQuery(): Builder
    {
        $user = auth()->user();

        return ClassParticipant::query()
            ->whereHas('mentorshipClass.training', function (Builder $query) use ($user) {
                // Matches getScopedBaseQuery()/getQuickStats()'s is_pilot
                // filter — without it this undercounted nothing but
                // over-counted mentees from pilot/trial mentorships,
                // disagreeing with the "total"/"active" figures on the same
                // subheading line and with MentorshipStatsService's mentee
                // count shown elsewhere on this page.
                $query->where('type', 'facility_mentorship')->where('is_pilot', false);

                if (! $user->hasRole(['super_admin', 'admin', 'division'])) {
                    $query->where('mentor_id', $user->id);
                }
            });
    }

    protected function getTabCount(string $tab): int
    {
        $query = $this->getScopedBaseQuery();

        return match ($tab) {
            'all' => $query->count(),
            'ongoing' => $query->where('status', 'ongoing')->count(),
            'repeat' => $query->where('status', 'repeat')->count(),
            'completed' => $query->where('status', 'completed')->count(),
            'new' => $query->where('status', 'new')->count(),
            default => 0,
        };
    }

    protected function redirectToAssessment(): void
    {
        $this->redirect(route('filament.admin.resources.facility-assessments.create'));
    }
}
