@php
    $participant = $getRecord();
    $training = $participant->training;
    $categories = $training->assessmentCategories()->orderBy('order_sequence')->get();
    $results = $participant->user->assessmentResults()
        ->whereHas('assessmentCategory', function($query) use ($training) {
            $query->where('training_id', $training->id);
        })
        ->with('assessmentCategory')
        ->get()
        ->keyBy('assessment_category_id');
@endphp

<div class="grid grid-cols-{{ min($categories->count(), 4) }} gap-2 py-2">
    @foreach($categories as $category)
        @php
            $result = $results->get($category->id);
            $score = $result?->score;
            $passed = $result ? $result->score >= $category->pass_threshold : null;
        @endphp
        
        <div class="text-center">
            <div class="text-xs font-medium text-gray-600 mb-1">
                {{ Str::limit($category->name, 8) }}
            </div>
            
            @if($result)
                <div class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                    {{ $passed ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                    {{ number_format($score, 0) }}%
                </div>
            @else
                <div class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-500">
                    Not Assessed
                </div>
            @endif
        </div>
    @endforeach
</div>