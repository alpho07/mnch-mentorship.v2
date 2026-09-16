{{-- resources/views/filament/pages/participant-detail.blade.php --}}
<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Participant Info Section --}}
        <div class="bg-white rounded-lg shadow">
            {{ $this->participantInfolist }}
        </div>

        {{-- Status Tracking Timeline --}}
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Status Tracking Timeline</h3>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                @foreach([3, 6, 12] as $month)
                    <div class="border rounded-lg p-4">
                        <h4 class="font-medium text-center mb-3 text-gray-700">
                            {{ $month }} Months Post-Training
                        </h4>
                        
                        @if(isset($this->statusLogs[$month]) && $this->statusLogs[$month]->isNotEmpty())
                            <div class="space-y-2">
                                @foreach($this->statusLogs[$month] as $log)
                                    <div class="text-sm border-l-4 border-blue-400 pl-3 py-1">
                                        <div class="font-medium">{{ ucwords(str_replace('_', ' ', $log->status_type)) }}</div>
                                        <div class="text-gray-600">
                                            From: <span class="text-red-600">{{ $log->old_value ?? 'Not set' }}</span>
                                        </div>
                                        <div class="text-gray-600">
                                            To: <span class="text-green-600">{{ $log->new_value }}</span>
                                        </div>
                                        @if($log->notes)
                                            <div class="text-gray-500 italic">{{ $log->notes }}</div>
                                        @endif
                                        <div class="text-xs text-gray-400">
                                            {{ $log->recorded_at->diffForHumans() }} by {{ $log->recorder->full_name ?? 'System' }}
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center text-gray-400 py-4">
                                <div class="text-sm">No updates recorded</div>
                                <div class="text-xs">Use action buttons above to add status updates</div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Other Training Programs Table --}}
        <div class="bg-white rounded-lg shadow">
            {{ $this->table }}
        </div>
    </div>
</x-filament-panels::page>