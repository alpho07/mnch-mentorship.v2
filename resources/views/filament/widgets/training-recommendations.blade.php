{{-- resources/views/filament/widgets/training-recommendations.blade.php --}}
<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center gap-2">
                <x-heroicon-o-light-bulb class="h-5 w-5 text-yellow-500" />
                AI-Powered Training Insights
            </div>
        </x-slot>

        <div class="space-y-6">
            {{-- Recommendations Section --}}
            @if(!empty($recommendations))
                <div>
                    <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-3">
                        Smart Recommendations
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @foreach($recommendations as $recommendation)
                            <div class="p-4 rounded-lg border border-gray-200 dark:border-gray-700 bg-{{ $recommendation['color'] }}-50 dark:bg-{{ $recommendation['color'] }}-900/20">
                                <div class="flex items-start gap-3">
                                    <div class="flex-shrink-0">
                                        @svg($recommendation['icon'], 'h-5 w-5 text-' . $recommendation['color'] . '-600 dark:text-' . $recommendation['color'] . '-400')
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 mb-1">
                                            <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                                {{ $recommendation['title'] }}
                                            </h4>
                                            <span class="px-2 py-1 text-xs font-medium rounded-full bg-{{ $recommendation['color'] }}-100 text-{{ $recommendation['color'] }}-800 dark:bg-{{ $recommendation['color'] }}-900 dark:text-{{ $recommendation['color'] }}-200">
                                                {{ ucfirst($recommendation['priority']) }}
                                            </span>
                                        </div>
                                        <p class="text-xs text-gray-600 dark:text-gray-400 mb-2">
                                            {{ $recommendation['description'] }}
                                        </p>

                                        @if(isset($recommendation['departments']))
                                            <div class="flex flex-wrap gap-1">
                                                @foreach($recommendation['departments'] as $dept)
                                                    <span class="px-2 py-1 text-xs bg-white dark:bg-gray-800 rounded border">
                                                        {{ $dept }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        @endif

                                        @if(isset($recommendation['programs']))
                                            <div class="flex flex-wrap gap-1">
                                                @foreach($recommendation['programs'] as $program)
                                                    <span class="px-2 py-1 text-xs bg-white dark:bg-gray-800 rounded border">
                                                        {{ $program }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Insights Section --}}
            @if(!empty($insights))
                <div>
                    <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-3">
                        Performance Insights
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        @foreach($insights as $insight)
                            <div class="p-4 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
                                <div class="flex items-start gap-3">
                                    <div class="flex-shrink-0">
                                        @svg($insight['icon'], 'h-5 w-5 text-' . $insight['color'] . '-600 dark:text-' . $insight['color'] . '-400')
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-1">
                                            {{ $insight['title'] }}
                                        </h4>
                                        <p class="text-xs text-gray-600 dark:text-gray-400 mb-2">
                                            {{ $insight['description'] }}
                                        </p>

                                        @if(isset($insight['data']) && $insight['type'] === 'success')
                                            <div class="space-y-1">
                                                @foreach($insight['data'] as $item)
                                                    <div class="flex justify-between items-center text-xs">
                                                        <span class="text-gray-600 dark:text-gray-400">{{ $item['name'] }}</span>
                                                        <span class="font-medium text-green-600 dark:text-green-400">{{ $item['rate'] }}</span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Quick Actions --}}
            <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100">
                        Quick Actions
                    </h3>
                    <div class="flex gap-2">
                        <x-filament::button
                            href="{{ route('filament.admin.resources.trainings.create') }}"
                            size="sm"
                            icon="heroicon-o-plus"
                        >
                            New Training
                        </x-filament::button>

                        <x-filament::button
                            href="{{ route('filament.admin.resources.trainings.index') }}"
                            size="sm"
                            color="gray"
                            icon="heroicon-o-eye"
                        >
                            View All
                        </x-filament::button>
                    </div>
                </div>
            </div>

            {{-- Last Updated --}}
            <div class="text-xs text-gray-500 dark:text-gray-400 text-center">
                <div class="flex items-center justify-center gap-1">
                    <x-heroicon-o-clock class="h-3 w-3" />
                    Last updated: {{ now()->format('M j, Y \a\t g:i A') }}
                </div>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>

<script>
    // Auto-refresh the widget every 5 minutes
    setInterval(() => {
        if (typeof window.Livewire !== 'undefined') {
            window.Livewire.emit('refreshWidget');
        }
    }, 300000);
</script>
