{{-- resources/views/filament/modals/assessments.blade.php --}}
<div class="space-y-4">
    @if($participant && $participant->assessmentResults && $participant->assessmentResults->count() > 0)
        @foreach($participant->assessmentResults as $result)
            <div class="border border-gray-200 rounded-lg p-4">
                <div class="grid grid-cols-4 gap-4">
                    <div>
                        <span class="text-sm font-medium text-gray-700">Category</span>
                        <p class="text-sm">{{ $result->assessmentCategory->name ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <span class="text-sm font-medium text-gray-700">Result</span>
                        <p class="text-sm">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $result->result === 'pass' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                {{ ucfirst($result->result ?? 'N/A') }}
                            </span>
                        </p>
                    </div>
                    <div>
                        <span class="text-sm font-medium text-gray-700">Score</span>
                        <p class="text-sm">{{ $result->score ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <span class="text-sm font-medium text-gray-700">Date</span>
                        <p class="text-sm">{{ $result->assessment_date?->format('M d, Y') ?? 'Not set' }}</p>
                    </div>
                </div>
                @if($result->feedback)
                    <div class="mt-3">
                        <span class="text-sm font-medium text-gray-700">Feedback</span>
                        <p class="text-sm text-gray-600">{{ $result->feedback }}</p>
                    </div>
                @endif
            </div>
        @endforeach
    @else
        <div class="text-center py-8">
            <x-heroicon-o-clipboard-document-list class="mx-auto h-12 w-12 text-gray-400" />
            <h3 class="mt-2 text-sm font-medium text-gray-900">No assessments found</h3>
            <p class="mt-1 text-sm text-gray-500">This participant has no assessment results yet.</p>
        </div>
    @endif
</div>