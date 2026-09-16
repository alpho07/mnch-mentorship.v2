<x-filament-panels::page> 
    <div class="space-y-6">
        <!-- Quick Stats -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="bg-white p-4 rounded-lg border text-center">
                <div class="text-2xl font-bold text-blue-600">{{ $this->stats['total_mentees'] }}</div>
                <div class="text-sm text-gray-600">Mentees</div>
            </div>
            <div class="bg-white p-4 rounded-lg border text-center">
                <div class="text-2xl font-bold text-green-600">{{ $this->stats['completion_rate'] }}%</div>
                <div class="text-sm text-gray-600">Complete</div>
            </div>
            <div class="bg-white p-4 rounded-lg border text-center">
                <div class="text-2xl font-bold text-purple-600">{{ $this->stats['pass_rate'] }}%</div>
                <div class="text-sm text-gray-600">Pass Rate</div>
            </div>
            <div class="bg-white p-4 rounded-lg border text-center">
                <div class="text-2xl font-bold text-yellow-600">{{ $this->stats['total_categories'] }}</div>
                <div class="text-sm text-gray-600">Categories</div>
            </div>
        </div>

        <!-- Assessment Categories -->
        @if($this->record->assessmentCategories->count() > 0)
            <div class="bg-white rounded-lg border">
                <div class="p-4 border-b">
                    <h3 class="font-medium">Assessment Categories</h3>
                </div>
                <div class="p-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        @foreach($this->record->assessmentCategories as $category)
                            <div class="flex items-center justify-between p-3 border rounded">
                                <div class="flex-1">
                                    <div class="font-medium">{{ $category->name }}</div>
                                    <div class="text-sm text-gray-600">Weight: {{ $category->weight_percentage }}%</div>
                                </div>
                                <div class="ml-2">
                                    @if($category->is_required)
                                        <span class="bg-red-100 text-red-700 px-2 py-1 rounded text-xs">Required</span>
                                    @else
                                        <span class="bg-gray-100 text-gray-700 px-2 py-1 rounded text-xs">Optional</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        <!-- Assessment Matrix -->
        <div class="bg-white rounded-lg border">
            <div class="p-4 border-b">
                <h3 class="font-medium">Assessment Matrix</h3>
                <p class="text-sm text-gray-600 mt-1">Pass/Fail status for each mentee</p>
            </div>
            
            <div class="overflow-x-auto">
                {{ $this->table }}
            </div>
        </div>

        <!-- Empty State -->
        @if($this->stats['total_mentees'] == 0)
            <div class="bg-gray-50 rounded-lg border-2 border-dashed border-gray-300 p-12 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                </svg>
                <h3 class="mt-4 text-lg font-medium text-gray-900">No Mentees to Assess</h3>
                <p class="mt-2 text-gray-600">Add mentees to this training program to begin assessments.</p>
                <div class="mt-6">
                    <a href="{{ \App\Filament\Resources\MentorshipTrainingResource::getUrl('mentees', ['record' => $this->record]) }}" 
                       class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                        Add Mentees
                    </a>
                </div>
            </div>
        @endif

        <!-- Quick Help -->
        @if($this->stats['total_mentees'] > 0)
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <div class="flex items-start">
                    <svg class="w-5 h-5 text-blue-500 mt-0.5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <div>
                        <h4 class="font-medium text-blue-900">Quick Assessment Tips</h4>
                        <ul class="mt-2 text-sm text-blue-800 space-y-1">
                            <li>• Use <strong>Quick Assess</strong> to evaluate all mentees for one category</li>
                            <li>• Use <strong>Bulk Pass/Fail</strong> to mark all mentees with the same result</li>
                            <li>• Click <strong>Assess</strong> on individual rows for detailed evaluation</li>
                            <li>• Required categories must be passed for overall program completion</li>
                        </ul>
                    </div>
                </div>
            </div>
        @endif
    </div>

    @push('styles')
    <style>
        .filament-table {
            font-size: 0.875rem;
        }
        
        .filament-table td {
            padding: 0.75rem 0.5rem;
        }
        
        @media (max-width: 768px) {
            .filament-table {
                font-size: 0.75rem;
            }
            
            .filament-table td {
                padding: 0.5rem 0.25rem;
            }
        }
    </style>
    @endpush
</x-filament-panels::page>