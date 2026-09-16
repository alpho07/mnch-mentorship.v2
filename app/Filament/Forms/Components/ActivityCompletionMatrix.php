<?php

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\Field;

class ActivityCompletionMatrix extends Field
{
    protected string $view = 'filament.forms.components.activity-completion-matrix';

    protected \Closure|array $participants = [];

    protected \Closure|array $activities = [];

    protected \Closure|array $completedActivityIds = [];

    protected \Closure|array $videoReviews = [];

    protected \Closure|array $certificateStatuses = [];

    public function participants(array|\Closure $participants): static
    {
        $this->participants = $participants;

        return $this;
    }

    public function activities(array|\Closure $activities): static
    {
        $this->activities = $activities;

        return $this;
    }

    public function completedActivityIds(array|\Closure $completedActivityIds): static
    {
        $this->completedActivityIds = $completedActivityIds;

        return $this;
    }

    public function videoReviews(array|\Closure $videoReviews): static
    {
        $this->videoReviews = $videoReviews;

        return $this;
    }

    public function certificateStatuses(array|\Closure $certificateStatuses): static
    {
        $this->certificateStatuses = $certificateStatuses;

        return $this;
    }

    public function getParticipants(): array
    {
        return $this->evaluate($this->participants);
    }

    public function getActivities(): array
    {
        return $this->evaluate($this->activities);
    }

    public function getCompletedActivityIds(): array
    {
        return $this->evaluate($this->completedActivityIds);
    }

    public function getVideoReviews(): array
    {
        return $this->evaluate($this->videoReviews);
    }

    public function getCertificateStatuses(): array
    {
        return $this->evaluate($this->certificateStatuses);
    }
}
