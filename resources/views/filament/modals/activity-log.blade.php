<div class="space-y-4">
    @forelse($activities as $activity)
        <div class="flex items-start space-x-3 p-3 bg-gray-50 rounded-lg">
            <div class="flex-shrink-0">
                <div class="w-8 h-8 bg-primary-100 rounded-full flex items-center justify-center">
                    <x-heroicon-s-clock class="w-4 h-4 text-primary-600" />
                </div>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-900">
                    {{ $activity->description }}
                </p>
                <p class="text-xs text-gray-500">
                    {{ $activity->causer?->full_name ?? 'System' }} • 
                    {{ $activity->created_at->diffForHumans() }}
                </p>
                @if($activity->properties && count($activity->properties))
                    <div class="mt-1">
                        <details class="text-xs text-gray-600">
                            <summary class="cursor-pointer hover:text-gray-800">Details</summary>
                            <pre class="mt-1 p-2 bg-gray-100 rounded text-xs overflow-auto">{{ json_encode($activity->properties, JSON_PRETTY_PRINT) }}</pre>
                        </details>
                    </div>
                @endif
            </div>
        </div>
    @empty
        <p class="text-sm text-gray-500 text-center py-4">No activities recorded yet.</p>
    @endforelse
</div>