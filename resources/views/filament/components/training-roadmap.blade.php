<div class="space-y-6">
    <div class="text-center">
        <h3 class="text-lg font-medium text-gray-900">Personalized Training Roadmap</h3>
        <p class="mt-1 text-sm text-gray-600">AI-generated recommendations based on {{ $mentee->full_name }}'s performance and career path</p>
    </div>

    <div class="space-y-4">
        @foreach($recommendations as $index => $recommendation)
            <div class="relative">
                {{-- Connection line --}}
                @if(!$loop->last)
                    <div class="absolute left-4 top-12 w-0.5 h-8 bg-gray-200"></div>
                @endif
                
                <div class="flex items-start space-x-4">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center
                            {{ $recommendation['priority'] === 'high' ? 'bg-red-100 text-red-600' : '' }}
                            {{ $recommendation['priority'] === 'medium' ? 'bg-yellow-100 text-yellow-600' : '' }}
                            {{ $recommendation['priority'] === 'low' ? 'bg-green-100 text-green-600' : '' }}">
                            <span class="text-sm font-medium">{{ $index + 1 }}</span>
                        </div>
                    </div>
                    
                    <div class="flex-1 bg-white border border-gray-200 rounded-lg p-4">
                        <div class="flex items-start justify-between">
                            <div>
                                <h4 class="text-sm font-medium text-gray-900">{{ $recommendation['title'] }}</h4>
                                <p class="mt-1 text-sm text-gray-600">{{ $recommendation['description'] }}</p>
                            </div>
                            <div class="ml-4 flex flex-col items-end">
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                                    {{ $recommendation['priority'] === 'high' ? 'bg-red-100 text-red-800' : '' }}
                                    {{ $recommendation['priority'] === 'medium' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                    {{ $recommendation['priority'] === 'low' ? 'bg-green-100 text-green-800' : '' }}">
                                    {{ ucfirst($recommendation['priority']) }} Priority
                                </span>
                                <span class="mt-1 text-xs text-gray-500">{{ $recommendation['estimated_duration'] }}</span>
                            </div>
                        </div>
                        
                        <div class="mt-3 flex items-center space-x-2">
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                {{ ucfirst($recommendation['type']) }}
                            </span>
                            
                            {{-- Action buttons --}}
                            <div class="flex space-x-2">
                                <button type="button" class="text-xs text-blue-600 hover:text-blue-800 font-medium">
                                    Enroll Now
                                </button>
                                <button type="button" class="text-xs text-gray-500 hover:text-gray-700">
                                    Learn More
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Career Path Suggestion --}}
    <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-lg p-4">
        <div class="flex items-center">
            <div class="flex-shrink-0">
                <svg class="h-6 w-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                </svg>
            </div>
            <div class="ml-3">
                <h3 class="text-sm font-medium text-blue-800">Career Development Path</h3>
                <p class="mt-1 text-sm text-blue-700">
                    Based on {{ $mentee->full_name }}'s performance in {{ $mentee->department?->name }}, 
                    consider advanced certifications and leadership training for career progression.
                </p>
            </div>
        </div>
    </div>
</div>
