<?php

namespace App\Filament\Resources\SurveyResource\Pages;

use App\Filament\Resources\SurveyResource;
use App\Models\Survey;
use App\Models\SurveyEvent;
use App\Services\SurveyDashboardService;
use Filament\Resources\Pages\Page;

class SurveyDashboard extends Page
{
    protected static string $resource = SurveyResource::class;

    protected static string $view = 'filament.pages.survey.dashboard';

    public Survey $record;

    public ?int $eventId = null;

    public array $dashboardData = [];

    /**
     * Accepts Survey|int|string, not just int|string: in real panel usage
     * Livewire resolves the typed $this->record property via route-model
     * binding before mount() runs and this parameter is just the raw route
     * key (matching AssessmentDashboard::mount()'s exact convention — see
     * its own comment for why $this->record->id, not $record, is what's
     * trusted below). The Survey branch exists only so this page can be
     * exercised in tests via Livewire::test(..., ['record' => $survey])
     * without a full HTTP round trip through real routing.
     */
    public function mount(Survey|int|string $record): void
    {
        $this->record = SurveyResource::getEloquentQuery()->findOrFail($this->record->id);
        $this->loadDashboardData();
    }

    public function updatedEventId(): void
    {
        $this->loadDashboardData();
    }

    protected function loadDashboardData(): void
    {
        $event = $this->eventId ? SurveyEvent::find($this->eventId) : null;
        $this->dashboardData = SurveyDashboardService::build($this->record, $event);
    }

    protected function getViewData(): array
    {
        return [
            'survey' => $this->record,
            'events' => $this->record->events()->ordered()->get(),
            'data' => $this->dashboardData,
        ];
    }
}
