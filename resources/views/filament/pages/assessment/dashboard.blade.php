<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Assessment Summary --}}
        {{ $this->getInfolist('assessment_summary') }}

        {{-- Progress Overview Stats --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl p-5 border border-blue-200">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-blue-600">Total Sections</p>
                        <p class="text-3xl font-bold text-blue-900 mt-1">{{ $progressStats['total'] }}</p>
                    </div>
                    <div class="w-12 h-12 bg-blue-200 rounded-full flex items-center justify-center">
                        <x-heroicon-o-document-text class="w-6 h-6 text-blue-700" />
                    </div>
                </div>
            </div>

            <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-xl p-5 border border-green-200">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-green-600">Completed</p>
                        <p class="text-3xl font-bold text-green-900 mt-1">{{ $progressStats['completed'] }}</p>
                    </div>
                    <div class="w-12 h-12 bg-green-200 rounded-full flex items-center justify-center">
                        <x-heroicon-o-check-circle class="w-6 h-6 text-green-700" />
                    </div>
                </div>
            </div>

            <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-xl p-5 border border-purple-200">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-purple-600">Progress</p>
                        <p class="text-3xl font-bold text-purple-900 mt-1">{{ $progressStats['percentage'] }}%</p>
                    </div>
                    <div class="w-12 h-12 bg-purple-200 rounded-full flex items-center justify-center">
                        <x-heroicon-o-chart-bar class="w-6 h-6 text-purple-700" />
                    </div>
                </div>
            </div>
        </div>

        {{-- Filters Section --}}
        <div class="bg-white rounded-xl border shadow-sm p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Assessment Sections</h3>
                <span class="text-sm text-gray-500">{{ count($sections) }} section(s) shown</span>
            </div>

            {{-- Filter Form --}}
            <form wire:submit.prevent class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    {{-- Search Input --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Search Sections</label>
                        <input 
                            type="text" 
                            wire:model.live.debounce.300ms="searchTerm"
                            placeholder="Type to search..."
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition"
                        />
                    </div>

                    {{-- Status Filter --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                        <select 
                            wire:model.live="statusFilter"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition"
                        >
                            <option value="all">All Sections</option>
                            <option value="completed">Completed Only</option>
                            <option value="incomplete">Incomplete Only</option>
                        </select>
                    </div>

                    {{-- Clear Filters Button --}}
                    <div class="flex items-end">
                        <button 
                            type="button"
                            wire:click="$set('searchTerm', null); $set('statusFilter', 'all')"
                            class="w-full px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition font-medium"
                        >
                            Clear Filters
                        </button>
                    </div>
                </div>
            </form>
        </div>

        {{-- Section Cards Grid --}}
        @if(count($sections) > 0)
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                @foreach ($sections as $section)
                    <div class="group relative bg-white rounded-xl border-2 hover:border-primary-300 shadow-sm hover:shadow-lg transition-all duration-300 overflow-hidden">
                        {{-- Status Badge (Top Right) --}}
                        <div class="absolute top-3 right-3 z-10">
                            @if($section['done'])
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800 border border-green-200">
                                    <x-heroicon-s-check-circle class="w-3.5 h-3.5 mr-1" />
                                    Done
                                </span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 border border-amber-200">
                                    <x-heroicon-s-exclamation-circle class="w-3.5 h-3.5 mr-1" />
                                    Pending
                                </span>
                            @endif
                        </div>

                        <div class="p-6">
                            {{-- Icon Circle --}}
                            <div class="mb-4">
                                @if($section['done'])
                                    <div class="w-16 h-16 mx-auto rounded-2xl bg-gradient-to-br from-green-400 to-green-600 flex items-center justify-center shadow-lg transform group-hover:scale-110 transition-transform duration-300">
                                        @svg($section['icon'] ?? 'heroicon-o-check', 'w-8 h-8 text-white')
                                    </div>
                                @else
                                    <div class="w-16 h-16 mx-auto rounded-2xl bg-gradient-to-br from-blue-400 to-blue-600 flex items-center justify-center shadow-lg transform group-hover:scale-110 transition-transform duration-300">
                                        @svg($section['icon'] ?? 'heroicon-o-document', 'w-8 h-8 text-white')
                                    </div>
                                @endif
                            </div>

                            {{-- Section Title --}}
                            <h3 class="text-center text-base font-bold text-gray-900 mb-4 min-h-[2.5rem] flex items-center justify-center">
                                {{ $section['label'] }}
                            </h3>

                            {{-- Action Button --}}
                            @if ($section['route'])
                                {{-- Sections with routes get clickable buttons --}}
                                @if($section['done'])
                                    <a href="{{ $section['route'] }}"
                                       style="background-color: #16a34a; color: #ffffff;"
                                       class="block w-full text-center px-4 py-2.5 rounded-lg font-medium transition-all duration-200 shadow-sm hover:shadow-md hover:opacity-90">
                                        Review Section
                                    </a>
                                @else
                                    <a href="{{ $section['route'] }}"
                                       style="background-color: #3b82f6; color: #ffffff;"
                                       class="block w-full text-center px-4 py-2.5 rounded-lg font-medium transition-all duration-200 shadow-sm hover:shadow-md hover:opacity-90">
                                        Start Section
                                    </a>
                                @endif
                            @else
                                {{-- Sections without routes (like facility_assessor) --}}
                                @if($section['done'])
                                    <div style="background-color: #16a34a; color: #ffffff;" class="w-full text-center px-4 py-2.5 rounded-lg font-medium shadow-sm flex items-center justify-center gap-2">
                                        <x-heroicon-s-check-circle class="w-4 h-4" />
                                        <span>Completed</span>
                                    </div>
                                @else
                                    <div class="w-full text-center px-4 py-2.5 rounded-lg font-medium bg-gray-100 text-gray-500 border-2 border-dashed border-gray-300">
                                        Not Available
                                    </div>
                                @endif
                            @endif
                        </div>

                        {{-- Hover Effect Border --}}
                        <div class="absolute inset-0 border-2 border-transparent group-hover:border-primary-400 rounded-xl pointer-events-none transition-colors duration-300"></div>
                    </div>
                @endforeach
            </div>
        @else
            {{-- Empty State --}}
            <div class="bg-white rounded-xl border-2 border-dashed border-gray-300 p-12 text-center">
                <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-gray-100 flex items-center justify-center">
                    <x-heroicon-o-funnel class="w-8 h-8 text-gray-400" />
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">No sections found</h3>
                <p class="text-gray-500 mb-4">Try adjusting your filters to see more results</p>
                <button 
                    wire:click="$set('searchTerm', null); $set('statusFilter', 'all')"
                    style="background-color: #3b82f6; color: #ffffff;"
                    class="px-4 py-2 rounded-lg transition font-medium hover:opacity-90"
                >
                    Clear All Filters
                </button>
            </div>
        @endif
    </div>
</x-filament-panels::page>