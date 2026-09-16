<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Assessment Overview Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4"> 
            @php
                $stats = $this->getAssessmentStats();
            @endphp
            
            <div class="bg-white rounded-lg border p-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <x-heroicon-o-users class="h-6 w-6 text-blue-600" />
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-gray-500">Total Mentees</p>
                        <p class="text-2xl font-semibold text-gray-900">{{ $stats['total_mentees'] }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg border p-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <x-heroicon-o-clipboard-document-check class="h-6 w-6 text-green-600" />
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-gray-500">Assessments Complete</p>
                        <p class="text-2xl font-semibold text-gray-900">{{ $stats['completion_rate'] }}%</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg border p-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <x-heroicon-o-chart-bar class="h-6 w-6 text-yellow-600" />
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-gray-500">Categories</p>
                        <p class="text-2xl font-semibold text-gray-900">{{ $stats['total_categories'] }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg border p-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <x-heroicon-o-academic-cap class="h-6 w-6 text-purple-600" />
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-gray-500">Total Assessments</p>
                        <p class="text-2xl font-semibold text-gray-900">{{ $stats['total_assessments'] }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Assessment Categories Preview -->
        <div class="bg-white rounded-lg border">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Assessment Categories</h3>
                <p class="text-sm text-gray-500">Categories used to evaluate mentee performance</p>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($this->record->assessmentCategories as $category)
                        <div class="border rounded-lg p-4">
                            <div class="flex items-center justify-between mb-2">
                                <h4 class="font-medium text-gray-900">{{ $category->name }}</h4>
                                @if($category->is_required)
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                        Required
                                    </span>
                                @endif
                            </div>
                            <p class="text-sm text-gray-600 mb-3">{{ $category->description }}</p>
                            <div class="space-y-1 text-xs text-gray-500">
                                <div>Pass Threshold: {{ $category->pass_threshold }}%</div>
                                <div>Weight: {{ $category->weight_percentage }}%</div>
                                <div>Method: {{ $category->assessment_method }}</div>
                            </div>
                            
                            @php
                                $categoryStats = $this->getCategoryStats($category->id);
                            @endphp
                            <div class="mt-3 pt-3 border-t border-gray-200">
                                <div class="flex justify-between text-xs">
                                    <span>Assessed: {{ $categoryStats['assessed'] }}/{{ $stats['total_mentees'] }}</span>
                                    <span class="text-{{ $categoryStats['pass_rate'] >= 70 ? 'green' : 'red' }}-600">
                                        {{ number_format($categoryStats['pass_rate'], 0) }}% pass
                                    </span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="bg-gray-50 rounded-lg border border-dashed border-gray-300 p-6">
            <div class="text-center">
                <x-heroicon-o-sparkles class="mx-auto h-12 w-12 text-gray-400" />
                <h3 class="mt-2 text-sm font-medium text-gray-900">Quick Assessment Tools</h3>
                <p class="mt-1 text-sm text-gray-500">Use the buttons above to quickly assess mentees or generate reports</p>
                <div class="mt-4 flex justify-center space-x-4">
                    <div class="text-center">
                        <div class="text-xs text-gray-500 mb-1">Assessment Wizard</div>
                        <div class="text-sm text-gray-900">Assess all mentees for one category</div>
                    </div>
                    <div class="text-center">
                        <div class="text-xs text-gray-500 mb-1">Bulk Assessment</div>
                        <div class="text-sm text-gray-900">Assess one mentee across all categories</div>
                    </div>
                    <div class="text-center">
                        <div class="text-xs text-gray-500 mb-1">Individual Assessment</div>
                        <div class="text-sm text-gray-900">Use "Assess" button in the table below</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Assessment Table -->
        <div class="bg-white rounded-lg border">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Mentee Assessment Matrix</h3>
                <p class="text-sm text-gray-500">Track individual mentee performance across all assessment categories</p>
            </div>
            
            {{ $this->table }}
        </div>
    </div>

    @push('scripts')
    <script>
        // Add any JavaScript for enhanced assessment interactions
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-refresh assessment stats when assessments are completed
            window.addEventListener('assessment-completed', function() {
                window.location.reload();
            });
        });
    </script>
    @endpush
</x-filament-panels::page>