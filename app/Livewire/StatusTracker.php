<?php

namespace App\Livewire;

use App\Models\ParticipantStatusLog;
use Livewire\Component;
use Illuminate\Support\Collection;

class StatusTracker extends Component
{
    public Collection $statusLogs;
    public string $participantType = 'participant'; // 'participant' or 'mentee'
    public int $participantId;

    public function mount($statusLogs, $participantType = 'participant', $participantId = null)
    {
        $this->statusLogs = collect($statusLogs);
        $this->participantType = $participantType;
        $this->participantId = $participantId;
    }

    public function render()
    {
        $groupedLogs = $this->statusLogs->groupBy('month_number');
        
        return view('livewire.status-tracker', [
            'monthlyLogs' => $groupedLogs,
            'availableMonths' => [3, 6, 12]
        ]);
    }

    public function getTimeAgo($date)
    {
        return $date ? \Carbon\Carbon::parse($date)->diffForHumans() : 'Unknown';
    }

    public function getStatusColor($statusType, $oldValue, $newValue)
    {
        if ($statusType === ParticipantStatusLog::STATUS_TYPE_OVERALL) {
            return match (strtolower($newValue)) {
                'active' => 'success',
                'retired', 'deceased', 'terminated' => 'danger',
                'transferred', 'study_leave' => 'warning',
                default => 'gray'
            };
        }

        // For other status types, show blue for changes
        return $oldValue !== $newValue ? 'info' : 'gray';
    }

    public function getStatusIcon($statusType)
    {
        return match ($statusType) {
            ParticipantStatusLog::STATUS_TYPE_OVERALL => 'heroicon-o-user-circle',
            ParticipantStatusLog::STATUS_TYPE_CADRE => 'heroicon-o-academic-cap',
            ParticipantStatusLog::STATUS_TYPE_DEPARTMENT => 'heroicon-o-building-office',
            ParticipantStatusLog::STATUS_TYPE_FACILITY => 'heroicon-o-building-office-2',
            ParticipantStatusLog::STATUS_TYPE_COUNTY => 'heroicon-o-map',
            ParticipantStatusLog::STATUS_TYPE_SUBCOUNTY => 'heroicon-o-map-pin',
            default => 'heroicon-o-clipboard-document-list'
        };
    }
}