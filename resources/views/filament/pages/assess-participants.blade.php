<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Instructions --}}
        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-blue-800 dark:text-blue-200">Assessment Instructions</h3>
                    <div class="mt-2 text-sm text-blue-700 dark:text-blue-300">
                        <p>Assess each participant against the pre-defined objectives. Objectives are already categorized as "Skill" or "Non-Skill".</p>
                        <p class="mt-1">Simply select whether each participant has <strong>Passed</strong> or <strong>Failed</strong> each objective.</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Matrix View --}}
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Quick Assessment Matrix</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Overview of all participants and their objective assessments</p>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                                Participant
                            </th>
                            @foreach($objectives as $objective)
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                                    <div class="space-y-1">
                                        <div>{{ Str::limit($objective->objective_text, 30) }}</div>
                                        <div class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                            {{ $objective->type === 'skill' ? 'bg-blue-100 text-blue-800 dark:bg-blue-800 dark:text-blue-100' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200' }}">
                                            {{ ucfirst($objective->type) }}
                                        </div>
                                    </div>
                                </th>
                            @endforeach
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                                Overall Outcome
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                        @foreach($participants as $participant)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div>
                                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                            {{ $participant->name }}
                                        </div>
                                        <div class="text-sm text-gray-500 dark:text-gray-400">
                                            {{ $participant->cadre?->name }} • {{ $participant->department?->name }}
                                        </div>
                                    </div>
                                </td>
                                @foreach($objectives as $objective)
                                    @php
                                        $result = $participant->objectiveResults
                                            ->where('objective_id', $objective->id)
                                            ->first();
                                        
                                        if ($result) {
                                            $result->load('grade');
                                        }
                                    @endphp
                                    <td class="px-4 py-4 text-center text-sm">
                                        @if($result && $result->grade)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                {{ strtolower($result->grade->name) === 'pass' ? 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100' : 'bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100' }}">
                                                {{ $result->grade->name }}
                                            </span>
                                        @else
                                            <span class="text-gray-400 text-xs">Not Assessed</span>
                                        @endif
                                    </td>
                                @endforeach
                                <td class="px-4 py-4 text-center text-sm">
                                    @if($participant->outcome)
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium
                                            {{ strtolower($participant->outcome->name) === 'pass' ? 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100' : 'bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100' }}">
                                            {{ $participant->outcome->name }}
                                        </span>
                                    @else
                                        <span class="text-gray-400 text-xs">Pending</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Detailed Assessment Form --}}
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Detailed Assessment</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Assess each participant individually with optional comments</p>
            </div>
            
            <div class="p-6">
                <form wire:submit="save">
                    {{ $this->form }}

                    <div class="mt-6 flex justify-between items-center">
                        <div class="text-sm text-gray-600 dark:text-gray-400">
                            @php
                                $progress = $this->assessmentProgress;
                            @endphp
                            <div class="flex items-center space-x-2">
                                <span>Progress: {{ $progress['assessed'] }} of {{ $progress['total'] }} assessments completed</span>
                                <span class="text-lg font-semibold text-primary-600">({{ $progress['percentage'] }}%)</span>
                            </div>
                            <div class="mt-2 w-64 bg-gray-200 rounded-full h-2.5 dark:bg-gray-700">
                                <div class="bg-primary-600 h-2.5 rounded-full transition-all duration-300" style="width: {{ $progress['percentage'] }}%"></div>
                            </div>
                        </div>
                        
                        <x-filament::button 
                            type="submit" 
                            size="lg"
                            wire:loading.attr="disabled"
                            wire:loading.class="opacity-70 cursor-wait"
                        >
                            <span wire:loading.remove wire:target="save">
                                Save All Assessments
                            </span>
                            <span wire:loading wire:target="save" class="flex items-center">
                                <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Saving...
                            </span>
                        </x-filament::button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-filament-panels::page>