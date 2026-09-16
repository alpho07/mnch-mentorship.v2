<x-filament-panels::page>
    <div class="space-y-6" x-data="{ countyFilter: 'all' }">
        {{-- KPI cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="rounded-2xl bg-gradient-to-br from-indigo-500 to-violet-600 p-5 text-white shadow-lg">
                <div class="text-xs font-semibold uppercase tracking-wider opacity-80">Active Mentees</div>
                <div class="text-3xl font-extrabold mt-1">{{ $kpis['active_mentees'] }}</div>
            </div>
            <div class="rounded-2xl bg-gradient-to-br from-blue-500 to-cyan-500 p-5 text-white shadow-lg">
                <div class="text-xs font-semibold uppercase tracking-wider opacity-80">Active Classes</div>
                <div class="text-3xl font-extrabold mt-1">{{ $kpis['active_classes'] }}</div>
            </div>
            <div class="rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-500 p-5 text-white shadow-lg">
                <div class="text-xs font-semibold uppercase tracking-wider opacity-80">Modules Completed</div>
                <div class="text-3xl font-extrabold mt-1">{{ $kpis['completed_modules'] }}</div>
            </div>
            <div class="rounded-2xl bg-gradient-to-br from-amber-400 to-orange-500 p-5 text-white shadow-lg">
                <div class="text-xs font-semibold uppercase tracking-wider opacity-80">Pending Video Reviews</div>
                <div class="text-3xl font-extrabold mt-1">{{ $kpis['pending_video_reviews'] }}</div>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="rounded-2xl bg-gradient-to-br from-blue-400 to-indigo-500 p-5 text-white shadow-lg">
                <div class="text-xs font-semibold uppercase tracking-wider opacity-80">Pending Mentor Approvals</div>
                <div class="text-3xl font-extrabold mt-1">{{ $kpis['pending_mentor_approvals'] }}</div>
            </div>
            <div class="rounded-2xl bg-gradient-to-br from-violet-500 to-fuchsia-500 p-5 text-white shadow-lg">
                <div class="text-xs font-semibold uppercase tracking-wider opacity-80">Pending DRMH Certs</div>
                <div class="text-3xl font-extrabold mt-1">{{ $kpis['pending_drmh_approvals'] }}</div>
            </div>
            <div class="rounded-2xl bg-gradient-to-br from-rose-400 to-pink-500 p-5 text-white shadow-lg">
                <div class="text-xs font-semibold uppercase tracking-wider opacity-80">Certificates Issued</div>
                <div class="text-3xl font-extrabold mt-1">{{ $kpis['certificates_issued'] }}</div>
            </div>
        </div>

        {{-- Pending actions --}}
        <div class="flex flex-wrap gap-3">
            @foreach($pendingActions as $action)
                <span class="inline-flex items-center gap-2 px-4 py-2 rounded-full text-sm font-semibold
                    {{ match($action['color']) {
                        'amber' => 'bg-amber-100 text-amber-700',
                        'blue' => 'bg-blue-100 text-blue-700',
                        'violet' => 'bg-violet-100 text-violet-700',
                        default => 'bg-slate-100 text-slate-700',
                    } }}">
                    {{ $action['label'] }}
                    <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-white text-xs font-bold">{{ $action['count'] }}</span>
                </span>
            @endforeach
        </div>

        {{-- Map and chart --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-5 shadow-sm">
                <h3 class="text-base font-bold text-slate-900 dark:text-white mb-3">County Overview</h3>
                <div id="emonc-map" class="w-full h-80 rounded-xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-400">
                    Loading map…
                </div>
            </div>
            <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-5 shadow-sm">
                <h3 class="text-base font-bold text-slate-900 dark:text-white mb-3">Completion Distribution</h3>
                <canvas id="completionDonut" height="220"></canvas>
            </div>
        </div>

        {{-- Completion matrix --}}
        <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-5 shadow-sm overflow-hidden">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
                <h3 class="text-base font-bold text-slate-900 dark:text-white">Per-Mentee Completion Map</h3>
                <input type="text" x-model="countyFilter" placeholder="Filter by mentee or module…"
                       class="px-3 py-1.5 text-sm rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100">
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-800">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold text-slate-600 dark:text-slate-300">Mentee</th>
                            <th class="px-4 py-3 text-left font-semibold text-slate-600 dark:text-slate-300">Class</th>
                            <th class="px-4 py-3 text-left font-semibold text-slate-600 dark:text-slate-300">Module / Track</th>
                            <th class="px-4 py-3 text-left font-semibold text-slate-600 dark:text-slate-300">Activities</th>
                            <th class="px-4 py-3 text-left font-semibold text-slate-600 dark:text-slate-300">Video</th>
                            <th class="px-4 py-3 text-left font-semibold text-slate-600 dark:text-slate-300">Certificate</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach($completionMatrix as $row)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50"
                                x-show="countyFilter === 'all' || '{{ strtolower($row['mentee_name'] . ' ' . $row['module_name'] . ' ' . ($row['parent_module_name'] ?? '')) }}'.includes(countyFilter.toLowerCase())">
                                <td class="px-4 py-3 font-medium text-slate-900 dark:text-white">{{ $row['mentee_name'] }}</td>
                                <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $row['class_name'] }}</td>
                                <td class="px-4 py-3">
                                    <div class="text-slate-900 dark:text-white font-medium">{{ $row['module_name'] }}</div>
                                    @if($row['parent_module_name'])
                                        <div class="text-xs text-brand-600 dark:text-brand-400">{{ $row['parent_module_name'] }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach($row['activities'] as $act)
                                            @php
                                                $color = match($act['status']) {
                                                    'completed' => 'bg-emerald-100 text-emerald-700 border-emerald-200',
                                                    'pending', 'in_progress' => 'bg-amber-100 text-amber-700 border-amber-200',
                                                    default => 'bg-slate-100 text-slate-600 border-slate-200',
                                                };
                                            @endphp
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-semibold border {{ $color }}">
                                                {{ $act['name'] }} {{ $act['status'] === 'completed' ? '✓' : '○' }}
                                            </span>
                                        @endforeach
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    @php
                                        $vcolor = match($row['video_review']) {
                                            'passed' => 'text-emerald-600',
                                            'failed' => 'text-red-600',
                                            'pending' => 'text-amber-600',
                                            default => 'text-slate-400',
                                        };
                                    @endphp
                                    <span class="font-semibold {{ $vcolor }}">{{ ucfirst(str_replace('_', ' ', $row['video_review'])) }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    @if($row['certificate_ready'])
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-700">Certified ✓</span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-red-100 text-red-700" title="{{ implode(', ', $row['blocked_reasons']) }}">
                                            Blocked ✗
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                // Map
                const mapEl = document.getElementById('emonc-map');
                if (mapEl && typeof L !== 'undefined') {
                    const map = L.map('emonc-map').setView([-1.2921, 36.8219], 6);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '&copy; OpenStreetMap contributors'
                    }).addTo(map);

                    fetch('/geojson/kenya-counties.json')
                        .then(r => r.json())
                        .then(geojson => {
                            L.geoJSON(geojson, {
                                style: { color: '#4f46e5', weight: 1, fillColor: '#c7d2fe', fillOpacity: 0.4 },
                                onEachFeature: function (feature, layer) {
                                    layer.bindPopup(feature.properties.name || 'County');
                                }
                            }).addTo(map);
                            mapEl.innerHTML = '';
                            map.invalidateSize();
                        })
                        .catch(() => {
                            mapEl.innerHTML = '<p class="text-slate-400">Map data unavailable.</p>';
                        });
                }

                // Donut chart
                const ctx = document.getElementById('completionDonut');
                if (ctx) {
                    new Chart(ctx, {
                        type: 'doughnut',
                        data: {
                            labels: {!! json_encode(array_column($chartData['completion_distribution'], 'label')) !!},
                            datasets: [{
                                data: {!! json_encode(array_column($chartData['completion_distribution'], 'value')) !!},
                                backgroundColor: ['#22c55e', '#f59e0b', '#94a3b8'],
                                borderWidth: 0
                            }]
                        },
                        options: { responsive: true, maintainAspectRatio: false }
                    });
                }
            });
        </script>
    @endpush
</x-filament-panels::page>
