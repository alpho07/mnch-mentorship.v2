<div class="space-y-6">
    <!-- Filters -->
    <div class="bg-white p-6 rounded-lg shadow">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold">Filters</h3>
            <button wire:click="clearFilters"
                class="px-2 py-1 text-sm text-gray-600 bg-gray-200 rounded hover:bg-gray-300">Clear All</button>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-3">
            <div>
                <label>Program</label>
                <select wire:model="program_id" multiple class="w-full border rounded">
                    @foreach ($programOptions as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label>Period</label>
                <select wire:model="period" multiple class="w-full border rounded">
                    @foreach ($periodOptions as $val => $label)
                        <option value="{{ $val }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label>County</label>
                <select wire:model="county_id" multiple class="w-full border rounded">
                    @foreach ($countyOptions as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label>Subcounty</label>
                <select wire:model="subcounty_id" multiple class="w-full border rounded">
                    @foreach ($subcountyOptions as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label>Facility</label>
                <select wire:model="facility_id" multiple class="w-full border rounded">
                    @foreach ($facilityOptions as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label>Department</label>
                <select wire:model="department_id" class="w-full border rounded">
                    <option value="">All Departments</option>
                    @foreach ($departmentOptions as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label>Cadre</label>
                <select wire:model="cadre_id" class="w-full border rounded">
                    <option value="">All Cadres</option>
                    @foreach ($cadreOptions as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <!-- KPIs -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4">
        <div class="bg-blue-50 p-4 rounded flex flex-col items-center">
            <div class="text-xs text-gray-500">Total Trainings</div>
            <div class="text-xl font-bold">{{ $totalTrainings }}</div>
        </div>
        <div class="bg-green-50 p-4 rounded flex flex-col items-center">
            <div class="text-xs text-gray-500">Total Participants</div>
            <div class="text-xl font-bold">{{ $totalParticipants }}</div>
        </div>
        <div class="bg-purple-50 p-4 rounded flex flex-col items-center">
            <div class="text-xs text-gray-500">Facilities Trained</div>
            <div class="text-xl font-bold">{{ $facilitiesTrained }}</div>
        </div>
        <div class="bg-rose-50 p-4 rounded flex flex-col items-center">
            <div class="text-xs text-gray-500">Coverage %</div>
            <div class="text-xl font-bold">{{ number_format($coveragePercentage, 1) }}%</div>
        </div>
        <div class="bg-orange-50 p-4 rounded flex flex-col items-center">
            <div class="text-xs text-gray-500">Last Training</div>
            <div class="text-xl font-bold">{{ $lastTrainingDate ?? 'N/A' }}</div>
        </div>
        <div class="bg-indigo-50 p-4 rounded flex flex-col items-center">
            <div class="text-xs text-gray-500">Active Months</div>
            <div class="text-xl font-bold">{{ $activeMonths }}</div>
        </div>
    </div>

    <!-- Charts -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Trainings by Month -->
        <div class="bg-white p-4 rounded shadow">
            <h4 class="font-semibold mb-2">Trainings by Month</h4>
            <div x-data="{
                chart: null,
                labels: @js($labels),
                data: @js($data),
                init() {
                    if (this.chart) this.chart.destroy();
                    let ctx = $el.querySelector('canvas').getContext('2d');
                    this.chart = new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: this.labels,
                            datasets: [{
                                label: 'Trainings',
                                data: this.data,
                                backgroundColor: '#3B82F6',
                                borderColor: '#2563EB',
                                borderWidth: 1,
                                borderRadius: 4
                            }]
                        },
                        options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
                    });
                },
                $watch('labels', v => { if (this.chart) { this.chart.data.labels = v;
                        this.chart.update(); } }),
                $watch('data', v => { if (this.chart) { this.chart.data.datasets[0].data = v;
                        this.chart.update(); } })
            }" x-init="init()" class="h-64"><canvas></canvas></div>
        </div>

        <!-- Trainings by County -->
        <div class="bg-white p-4 rounded shadow">
            <h4 class="font-semibold mb-2">Trainings by County</h4>
            <div x-data="{
                chart: null,
                labels: @js($county_labels),
                data: @js($county_data),
                init() {
                    if (this.chart) this.chart.destroy();
                    let ctx = $el.querySelector('canvas').getContext('2d');
                    this.chart = new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: this.labels,
                            datasets: [{
                                label: 'Trainings',
                                data: this.data,
                                backgroundColor: '#10B981',
                                borderColor: '#059669',
                                borderWidth: 1,
                                borderRadius: 4
                            }]
                        },
                        options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
                    });
                },
                $watch('labels', v => { if (this.chart) { this.chart.data.labels = v;
                        this.chart.update(); } }),
                $watch('data', v => { if (this.chart) { this.chart.data.datasets[0].data = v;
                        this.chart.update(); } })
            }" x-init="init()" class="h-64"><canvas></canvas></div>
        </div>

        <!-- By Department -->
        <div class="bg-white p-4 rounded shadow">
            <h4 class="font-semibold mb-2">Participants by Department</h4>
            <div x-data="{
                chart: null,
                labels: @js($dept_labels),
                data: @js($dept_data),
                init() {
                    if (this.chart) this.chart.destroy();
                    let ctx = $el.querySelector('canvas').getContext('2d');
                    this.chart = new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: this.labels,
                            datasets: [{
                                label: 'Participants',
                                data: this.data,
                                backgroundColor: '#8B5CF6',
                                borderColor: '#7C3AED',
                                borderWidth: 1,
                                borderRadius: 4
                            }]
                        },
                        options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
                    });
                },
                $watch('labels', v => { if (this.chart) { this.chart.data.labels = v;
                        this.chart.update(); } }),
                $watch('data', v => { if (this.chart) { this.chart.data.datasets[0].data = v;
                        this.chart.update(); } })
            }" x-init="init()" class="h-64"><canvas></canvas></div>
        </div>

        <!-- By Cadre -->
        <div class="bg-white p-4 rounded shadow">
            <h4 class="font-semibold mb-2">Participants by Cadre</h4>
            <div x-data="{
                chart: null,
                labels: @js($cadre_labels),
                data: @js($cadre_data),
                init() {
                    if (this.chart) this.chart.destroy();
                    let ctx = $el.querySelector('canvas').getContext('2d');
                    this.chart = new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: this.labels,
                            datasets: [{
                                label: 'Participants',
                                data: this.data,
                                backgroundColor: '#F59E0B',
                                borderColor: '#D97706',
                                borderWidth: 1,
                                borderRadius: 4
                            }]
                        },
                        options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
                    });
                },
                $watch('labels', v => { if (this.chart) { this.chart.data.labels = v;
                        this.chart.update(); } }),
                $watch('data', v => { if (this.chart) { this.chart.data.datasets[0].data = v;
                        this.chart.update(); } })
            }" x-init="init()" class="h-64"><canvas></canvas></div>
        </div>
    </div>
</div>
