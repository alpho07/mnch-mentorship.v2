<div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
    <h4 class="font-medium text-gray-900 mb-3">Assessment Statistics</h4>


    @if(!empty($stats['assessment_breakdown']))
        <h5 class="font-medium text-gray-900 mb-2">Category Breakdown</h5>
        <div class="space-y-2">
            @foreach($stats['assessment_breakdown'] as $breakdown)
                <div class="flex justify-between items-center text-sm border-b border-gray-200 pb-2">
                    <span class="font-medium">{{ $breakdown['category'] }} ({{ $breakdown['weight'] }}%)</span>
                    <span class="text-gray-600">
                        {{ $breakdown['assessed'] }}/{{ $stats['total_mentees'] }} assessed • 
                        <span class="{{ $breakdown['pass_rate'] >= 70 ? 'text-green-600' : 'text-red-600' }}">{{ $breakdown['pass_rate'] }}% pass</span>
                    </span>
                </div>
            @endforeach
        </div>
    @endif
</div>