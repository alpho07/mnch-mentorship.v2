<x-filament-panels::page>

    {{-- ── Filter bar ──────────────────────────────────────────────────────── --}}
    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm p-4">
        {{ $this->form }}
    </div>

    {{-- ── Result count ────────────────────────────────────────────────────── --}}
    <div class="flex items-center justify-between">
        <p class="text-sm text-gray-500">
            @if($totalCount > 0)
                Showing <strong class="text-gray-700 dark:text-gray-300">{{ $totalCount }}</strong> indicator{{ $totalCount !== 1 ? 's' : '' }}
                across <strong class="text-gray-700 dark:text-gray-300">{{ $groups->count() }}</strong> module{{ $groups->count() !== 1 ? 's' : '' }}
            @else
            No indicators match your filters.
            @endif
        </p>
    </div>

    {{-- ── Grouped indicator cards ─────────────────────────────────────────── --}}
    @forelse($groups as $groupData)
        @php
            $group      = $groupData['group'];
            $indicators = $groupData['indicators'];
            $reportType = $group->reportType;
        @endphp

        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm overflow-hidden">

            {{-- Group header --}}
            <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/50 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="text-xs font-mono bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-300 px-1.5 py-0.5 rounded">
                        {{ $group->code }}
                    </span>
                    <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-100">{{ $group->name }}</h3>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-xs text-gray-400">{{ count($indicators) }} indicator{{ count($indicators) !== 1 ? 's' : '' }}</span>
                    @if($reportType)
                        <span class="text-xs px-2 py-0.5 rounded-full font-medium bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400">
                            {{ $reportType->name }}
                        </span>
                    @endif
                </div>
            </div>

            {{-- Indicators --}}
            <div class="divide-y divide-gray-100 dark:divide-gray-800">
                @foreach($indicators as $indicator)
                    <div x-data="{ open: false }" class="px-5 py-4">
                        <button
                            @click="open = !open"
                    class="w-full text-left flex items-start gap-3"
                    >
                            {{-- Code + Type badge --}}
                            <span class="shrink-0 mt-0.5 font-mono text-xs bg-gray-100 dark:bg-gray-800 text-gray-500 px-1.5 py-0.5 rounded">
                                {{ $indicator->code }}
                            </span>

                            <div class="flex-1 min-w-0">
                                <div class="flex items-start justify-between gap-2">
                                    <p class="text-sm font-medium text-gray-800 dark:text-gray-100 leading-snug">
                                        {{ $indicator->name }}
                                    </p>
                                    <div class="shrink-0 flex items-center gap-1.5">
                                        {{-- Indicator type --}}
                                        <span class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded-full font-medium
                                            @if($indicator->indicator_type === 'proportion') bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400
                                            @elseif($indicator->indicator_type === 'count') bg-purple-100 text-purple-600 dark:bg-purple-900/30 dark:text-purple-400
                                            @elseif($indicator->indicator_type === 'yes_no') bg-green-100 text-green-600 dark:bg-green-900/30 dark:text-green-400
                                            @else bg-orange-100 text-orange-600 dark:bg-orange-900/30 dark:text-orange-400 @endif">
                                            {{ ucfirst($indicator->indicator_type) }}
                                        </span>
                                        {{-- Category --}}
                                        <span class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded-full font-medium
                                            @if($indicator->category === 'outcome') bg-purple-100 text-purple-600
                                            @elseif($indicator->category === 'output') bg-sky-100 text-sky-600
                                            @elseif($indicator->category === 'process') bg-amber-100 text-amber-600
                                            @else bg-gray-100 text-gray-500 @endif">
                                            {{ ucfirst($indicator->category) }}
                                        </span>
                                        {{-- Expand chevron --}}
                                        <x-heroicon-m-chevron-down
                                    class="w-4 h-4 text-gray-400 transition-transform duration-200"
                                    ::class="{ 'rotate-180': open }"
                                    />
                                    </div>
                                </div>

                                @if($indicator->short_name && $indicator->short_name !== $indicator->name)
                                    <p class="text-xs text-gray-400 mt-0.5 italic">{{ $indicator->short_name }}</p>
                                @endif
                            </div>
                        </button>

                        {{-- Expanded detail panel --}}
                        <div x-show="open" x-transition x-cloak class="mt-4 ml-9 space-y-4">

                            {{-- Definition --}}
                            @if($indicator->definition)
                                <div>
                                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Definition</p>
                                    <p class="text-sm text-gray-700 dark:text-gray-300">{{ $indicator->definition }}</p>
                                </div>
                            @endif

                            {{-- Measurement --}}
                            @if($indicator->indicator_type === 'proportion')
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    @if($indicator->numerator_label)
                                        <div class="rounded-lg bg-blue-50 dark:bg-blue-950/20 border border-blue-200 dark:border-blue-800 px-3 py-2">
                                            <p class="text-xs font-semibold text-blue-600 dark:text-blue-400 mb-0.5">Numerator</p>
                                            <p class="text-sm text-gray-700 dark:text-gray-300">{{ $indicator->numerator_label }}</p>
                                        </div>
                                    @endif
                                    @if($indicator->denominator_label)
                                        <div class="rounded-lg bg-indigo-50 dark:bg-indigo-950/20 border border-indigo-200 dark:border-indigo-800 px-3 py-2">
                                            <p class="text-xs font-semibold text-indigo-600 dark:text-indigo-400 mb-0.5">Denominator</p>
                                            <p class="text-sm text-gray-700 dark:text-gray-300">{{ $indicator->denominator_label }}</p>
                                        </div>
                                    @endif
                                </div>
                            @endif

                            {{-- Source document --}}
                            @if($indicator->source_document)
                                <div class="flex items-center gap-2 text-xs text-gray-500">
                                    <x-heroicon-m-document-text class="w-3.5 h-3.5 shrink-0" />
                                    <span><strong>Source:</strong> {{ $indicator->source_document }}</span>
                                    @if($indicator->source_document_code)
                                        <span class="font-mono bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 rounded text-gray-500">
                                            {{ $indicator->source_document_code }}
                                        </span>
                                    @endif
                                </div>
                            @endif

                            {{-- Display hint --}}
                            @if($indicator->display_hint)
                                <div class="flex items-start gap-2 text-xs text-primary-600 dark:text-primary-400">
                                    <x-heroicon-m-information-circle class="w-3.5 h-3.5 shrink-0 mt-0.5" />
                                    <span>{{ $indicator->display_hint }}</span>
                                </div>
                            @endif

                            {{-- Sub-bands --}}
                            @if($indicator->children->isNotEmpty())
                                <div>
                                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Sub-bands</p>
                                    <div class="flex flex-wrap gap-2">
                                        @foreach($indicator->children as $child)
                                            <span class="inline-flex items-center gap-1 text-xs bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 rounded-full px-2.5 py-1">
                                                <span class="font-mono text-[10px] text-gray-400">{{ $child->code }}</span>
                                                {{ $child->short_name ?? $child->name }}
                                            </span>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            {{-- DHIS2 UID status --}}
                            <div class="flex items-center gap-2 text-xs">
                                @if($indicator->isDhis2Ready())
                                    <x-heroicon-m-check-circle class="w-3.5 h-3.5 text-success-500" />
                                    <span class="text-success-600 dark:text-success-400">DHIS2 UIDs mapped</span>
                                @else
                                    <x-heroicon-m-x-circle class="w-3.5 h-3.5 text-gray-400" />
                                    <span class="text-gray-400">DHIS2 UIDs not yet mapped</span>
                                @endif
                            </div>

                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @empty
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-12 text-center">
            <x-heroicon-o-magnifying-glass class="w-10 h-10 text-gray-300 mx-auto mb-3" />
            <p class="text-sm text-gray-400">No indicators match your current filters.</p>
            <p class="text-xs text-gray-300 mt-1">Try clearing the search or changing the filter selections.</p>
        </div>
    @endforelse

</x-filament-panels::page>