<x-filament-panels::page>
    <div class="space-y-6" x-data="{ activeTab: 0 }">
        {{-- Overall completion meter --}}
        <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-5 shadow-sm">
            <div class="flex items-center justify-between mb-2">
                <h3 class="text-base font-bold text-slate-900 dark:text-white">Overall Completion</h3>
                <span class="text-sm font-semibold text-slate-600 dark:text-slate-300">
                    {{ $data['overall_completion']['answered'] }} / {{ $data['overall_completion']['total'] }} ({{ $data['overall_completion']['percentage'] }}%)
                </span>
            </div>
            <div class="w-full h-3 rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden">
                <div class="h-full rounded-full"
                     style="width: {{ $data['overall_completion']['percentage'] }}%; background-color: {{ match ($data['overall_completion']['grade']) { 'green' => '#0ca30c', 'yellow' => '#fab219', default => '#d03b3b' } }};"></div>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-2">{{ $data['response_count'] }} submitted response(s)</p>
        </div>

        {{-- AI summary --}}
        <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-5 shadow-sm">
            <div class="flex items-center justify-between mb-2">
                <h3 class="text-base font-bold text-slate-900 dark:text-white">AI Summary</h3>
                <button type="button" wire:click="generateSummary" wire:loading.attr="disabled" wire:target="generateSummary"
                        class="fi-btn fi-btn-color-primary inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-semibold text-white bg-primary-600 disabled:opacity-50">
                    <span wire:loading.remove wire:target="generateSummary">Generate Summary</span>
                    <span wire:loading wire:target="generateSummary">Generating…</span>
                </button>
            </div>
            @if ($summary)
                <p class="text-sm text-slate-700 dark:text-slate-300 whitespace-pre-line">{{ $summary }}</p>
            @else
                <p class="text-sm text-slate-400">No summary generated yet.</p>
            @endif
        </div>

        {{-- Event dropdown --}}
        @if ($events->isNotEmpty())
            <div class="flex items-center gap-3">
                <label for="dashboard-event-select" class="text-sm font-semibold text-slate-700 dark:text-slate-300">Event</label>
                <select id="dashboard-event-select" wire:model.live="eventId"
                        class="rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-white text-sm">
                    <option value="">All Events</option>
                    @foreach ($events as $event)
                        <option value="{{ $event->id }}">{{ $event->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        {{-- Section tabs --}}
        @if (count($data['sections']) > 0)
            <div class="flex flex-wrap gap-2 border-b border-slate-200 dark:border-slate-700">
                @foreach ($data['sections'] as $index => $section)
                    <button type="button" @click="activeTab = {{ $index }}"
                            :class="activeTab === {{ $index }} ? 'border-primary-600 text-primary-600' : 'border-transparent text-slate-500 dark:text-slate-400'"
                            class="px-4 py-2 text-sm font-semibold border-b-2 -mb-px">
                        {{ $section['name'] }}
                    </button>
                @endforeach
            </div>

            @foreach ($data['sections'] as $index => $section)
                <div x-show="activeTab === {{ $index }}" x-cloak class="space-y-4">
                    @if ($section['completion'])
                        <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-4">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Section Completion</span>
                                <span class="text-sm font-semibold text-slate-600 dark:text-slate-300">{{ $section['completion']['percentage'] }}%</span>
                            </div>
                            <div class="w-full h-2 rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden">
                                <div class="h-full rounded-full"
                                     style="width: {{ $section['completion']['percentage'] }}%; background-color: {{ match ($section['completion']['grade']) { 'green' => '#0ca30c', 'yellow' => '#fab219', default => '#d03b3b' } }};"></div>
                            </div>
                        </div>
                    @endif

                    @foreach ($section['questions'] as $q)
                        <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-4">
                            <h4 class="text-sm font-bold text-slate-900 dark:text-white mb-3">{{ $q['text'] }}</h4>

                            @if ($q['chart'] === 'bar')
                                <div wire:key="chart-{{ $q['id'] }}-{{ $eventId ?? 'all' }}"
                                     x-data="{ init() { new Chart(this.$refs.canvas, {
                                         type: 'bar',
                                         data: { labels: @js(collect($q['data'])->pluck('label')), datasets: [{ data: @js(collect($q['data'])->pluck('count')), backgroundColor: '#2a78d6', borderRadius: 4 }] },
                                         options: { indexAxis: 'y', responsive: true, plugins: { legend: { display: false } } }
                                     }) } }">
                                    <canvas x-ref="canvas" height="{{ max(80, count($q['data']) * 30) }}"></canvas>
                                </div>
                            @elseif ($q['chart'] === 'status_bar')
                                <div wire:key="chart-{{ $q['id'] }}-{{ $eventId ?? 'all' }}"
                                     x-data="{ init() { new Chart(this.$refs.canvas, {
                                         type: 'bar',
                                         data: { labels: ['Complete', 'Incomplete'], datasets: [{ data: @js([$q['data']['complete'], $q['data']['incomplete']]), backgroundColor: ['#0ca30c', '#d03b3b'], borderRadius: 4 }] },
                                         options: { indexAxis: 'y', responsive: true, plugins: { legend: { display: false } } }
                                     }) } }">
                                    <canvas x-ref="canvas" height="100"></canvas>
                                </div>
                            @elseif ($q['chart'] === 'histogram')
                                <div class="flex gap-4 mb-3 text-sm text-slate-600 dark:text-slate-300">
                                    <span>Avg: <strong>{{ $q['data']['avg'] }}</strong></span>
                                    <span>Min: <strong>{{ $q['data']['min'] }}</strong></span>
                                    <span>Max: <strong>{{ $q['data']['max'] }}</strong></span>
                                </div>
                                <div wire:key="chart-{{ $q['id'] }}-{{ $eventId ?? 'all' }}"
                                     x-data="{ init() { new Chart(this.$refs.canvas, {
                                         type: 'bar',
                                         data: { labels: @js(collect($q['data']['bins'])->pluck('range')), datasets: [{ data: @js(collect($q['data']['bins'])->pluck('count')), backgroundColor: '#2a78d6', borderRadius: 4 }] },
                                         options: { responsive: true, plugins: { legend: { display: false } } }
                                     }) } }">
                                    <canvas x-ref="canvas" height="180"></canvas>
                                </div>
                            @elseif ($q['chart'] === 'diverging_stack')
                                @foreach ($q['data']['rows'] as $row)
                                    <div class="mb-3">
                                        <p class="text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">{{ $row['label'] }}</p>
                                        <div wire:key="chart-{{ $q['id'] }}-{{ $loop->index }}-{{ $eventId ?? 'all' }}"
                                             x-data="{ init() { new Chart(this.$refs.canvas, {
                                                 type: 'bar',
                                                 data: {
                                                     labels: [''],
                                                     datasets: @js(collect($q['data']['columns'])->map(fn ($col, $i) => [
                                                         'label' => $col,
                                                         'data' => [$row['counts'][$col] ?? 0],
                                                         'backgroundColor' => $i <= $q['data']['neutral_index'] ? ($i === $q['data']['neutral_index'] ? '#f0efec' : '#1baf7a') : '#2a78d6',
                                                     ])->values()),
                                                 },
                                                 options: { indexAxis: 'y', responsive: true, scales: { x: { stacked: true }, y: { stacked: true } } }
                                             }) } }">
                                            <canvas x-ref="canvas" height="60"></canvas>
                                        </div>
                                    </div>
                                @endforeach
                            @elseif ($q['chart'] === 'list')
                                <ul class="space-y-2 max-h-64 overflow-y-auto">
                                    @forelse ($q['data']['responses'] as $response)
                                        <li class="text-sm text-slate-700 dark:text-slate-300 border-b border-slate-100 dark:border-slate-800 pb-2">{{ $response }}</li>
                                    @empty
                                        <li class="text-sm text-slate-400">No responses yet.</li>
                                    @endforelse
                                </ul>
                            @elseif ($q['chart'] === 'table')
                                <p class="text-sm text-slate-600 dark:text-slate-300 mb-2">
                                    {{ $q['data']['row_count'] }} row(s) across {{ $q['data']['response_count'] }} response(s)
                                </p>
                                @if (count($q['data']['rows']) > 0)
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full text-sm">
                                            <thead>
                                                <tr>
                                                    @foreach (array_keys($q['data']['rows'][0]) as $column)
                                                        <th class="text-left px-2 py-1 text-slate-500 dark:text-slate-400 font-semibold">{{ $column }}</th>
                                                    @endforeach
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($q['data']['rows'] as $row)
                                                    <tr class="border-t border-slate-100 dark:border-slate-800">
                                                        @foreach ($row as $cell)
                                                            <td class="px-2 py-1 text-slate-700 dark:text-slate-300">{{ $cell }}</td>
                                                        @endforeach
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @endif
                            @endif

                            @if ($q['trend'])
                                <div class="mt-4">
                                    <p class="text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">Trend across events</p>
                                    <div wire:key="trend-{{ $q['id'] }}"
                                         x-data="{ init() { new Chart(this.$refs.canvas, {
                                             type: 'line',
                                             data: { labels: @js($q['trend']['labels']), datasets: [{ data: @js($q['trend']['values']), borderColor: '#2a78d6', backgroundColor: '#2a78d6', tension: 0.2 }] },
                                             options: { responsive: true, plugins: { legend: { display: false } } }
                                         }) } }">
                                        <canvas x-ref="canvas" height="120"></canvas>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endforeach
        @else
            <div class="text-center text-slate-400 py-12">No sections with questions yet.</div>
        @endif
    </div>

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    @endpush
</x-filament-panels::page>
