<?php

namespace App\Filament\Widgets;

use App\Models\County;
use App\Models\TrainingParticipant;
use App\Models\Training;
use App\Models\Facility;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\StatsOverviewWidget\Card;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;

class CountyInsightsWidget extends BaseWidget {

    //protected static ?string $heading = 'County Insights';
    protected static string $view = 'filament.widgets.county-insights-widget';
    protected int|string|array $columnSpan = 'full';
    public ?int $countyId = null;

    public function mount(?int $countyId = null): void {
        $this->countyId = $countyId ?? County::first()?->id;
    }

    protected function getCards(): array {
        if (!$this->countyId) {
            return [
                Card::make('County', 'No county selected'),
            ];
        }

        $county = County::with(['subcounties.facilities'])->find($this->countyId);

        // Total facilities in county
        $totalFacilities = $county->subcounties->flatMap->facilities->count();

        // Facilities with at least 1 training participant
        $facilitiesWithTrainings = Facility::whereHas('participants.training', function ($q) use ($county) {
                    $q->whereHas('facility.subcounty.county', fn($cq) => $cq->where('id', $this->countyId));
                })->count();

        // Total participants from this county
        $totalParticipants = TrainingParticipant::whereHas('user.facility.subcounty.county', fn($q) =>
                        $q->where('id', $this->countyId)
                )->count();

        // Trainings held in this county
        $totalTrainings = Training::whereHas('participants.user.facility.subcounty.county', fn($q) =>
                        $q->where('id', $this->countyId)
                )->count();

        // Coverage %
        $coveragePercent = $totalFacilities > 0 ? round(($facilitiesWithTrainings / $totalFacilities) * 100, 1) : 0;

        return [
            Card::make('County', $county->name),
            Card::make('Total Facilities', number_format($totalFacilities)),
            Card::make('Facilities Reached', number_format($facilitiesWithTrainings) . " ({$coveragePercent}%)"),
            Card::make('Trainings Held', number_format($totalTrainings)),
            Card::make('Participants Reached', number_format($totalParticipants)),
        ];
    }
}
