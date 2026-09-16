<?php

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\Field;

class ActivityEnrollmentMatrix extends Field
{
    protected string $view = 'filament.forms.components.activity-enrollment-matrix';

    protected \Closure|array $participants = [];

    protected \Closure|array $activities = [];

    protected \Closure|array $enrolledActivityIds = [];

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

    public function enrolledActivityIds(array|\Closure $enrolledActivityIds): static
    {
        $this->enrolledActivityIds = $enrolledActivityIds;

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

    public function getEnrolledActivityIds(): array
    {
        return $this->evaluate($this->enrolledActivityIds);
    }
}
