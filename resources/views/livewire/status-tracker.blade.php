<div class="bg-white p-6 rounded-lg shadow">
    <h3 class="text-lg font-semibold mb-4">Status Tracking History</h3>
    
    @if($monthlyLogs->isEmpty())
        <div class="text-center text-gray-500 py-8">
            <div class="text-sm">No status updates recorded yet</div>
        </div>
    @else
        <div class="space-y-6">
            @foreach($availableMonths as $month)
                @if(isset($monthlyLogs[$month]))
                    <div class="border-l-4 border-blue-500 pl-4">
                        <h4 class="font-medium text-gray-800 mb-2">{{ $month }} Months Post-Training</h4>
                        
                        <div class="space-y-3">
                            @foreach($monthlyLogs[$month] as $log)
                                <div class="bg-gray-50 rounded p-3">
                                    <div class="flex items-start justify-between">
                                        <div class="flex-1">
                                            <div class="flex items-center space-x-2 mb-1">
                                                <x-heroicon-o-clipboard-document-list class="w-4 h-4 text-gray-500" />
                                                <span class="font-medium text-sm">
                                                    {{ ucwords(str_replace('_', ' ', $log->status_type)) }}
                                                </span>
                                            </div>
                                            
                                            <div class="text-sm text-gray-600 ml-6">
                                                <span class="text-red-600">{{ $log->old_value ?? 'Not set' }}</span>
                                                <span class="mx-2">→</span>
                                                <span class="text-green-600 font-medium">{{ $log->new_value }}</span>
                                            </div>
                                            
                                            @if($log->notes)
                                                <div class="text-sm text-gray-500 ml-6 mt-1 italic">
                                                    "{{ $log->notes }}"
                                                </div>
                                            @endif
                                        </div>
                                        
                                        <div class="text-xs text-gray-400 text-right">
                                            <div>{{ $this->getTimeAgo($log->recorded_at) }}</div>
                                            <div>by {{ $log->recorder->full_name ?? 'System' }}</div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endforeach
        </div>
    @endif
</div>