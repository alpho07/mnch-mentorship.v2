<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Trainings by Month -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
        <div class="p-6">
            <h3 class="text-lg font-semibold mb-4 flex items-center text-gray-900 dark:text-gray-100">
                <x-heroicon-o-calendar class="w-5 h-5 mr-2" />
                Trainings by Month
            </h3>
            <div class="h-64">
                <canvas id="monthChart-{{ $this->getId() }}"></canvas>
            </div>
        </div>
    </div>

    <!-- Trainings by County -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
        <div class="p-6">
            <h3 class="text-lg font-semibold mb-4 flex items-center text-gray-900 dark:text-gray-100">
                <x-heroicon-o-map-pin class="w-5 h-5 mr-2" />
                Trainings by County
            </h3>
            <div class="h-64">
                <canvas id="countyChart-{{ $this->getId() }}"></canvas>
            </div>
        </div>
    </div>

    <!-- Participants by Department -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
        <div class="p-6">
            <h3 class="text-lg font-semibold mb-4 flex items-center text-gray-900 dark:text-gray-100">
                <x-heroicon-o-building-office class="w-5 h-5 mr-2" />
                Participants by Department
            </h3>
            <div class="h-64">
                <canvas id="departmentChart-{{ $this->getId() }}"></canvas>
            </div>
        </div>
    </div>

    <!-- Participants by Cadre -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
        <div class="p-6">
            <h3 class="text-lg font-semibold mb-4 flex items-center text-gray-900 dark:text-gray-100">
                <x-heroicon-o-user-group class="w-5 h-5 mr-2" />
                Participants by Cadre
            </h3>
            <div class="h-64">
                <canvas id="cadreChart-{{ $this->getId() }}"></canvas>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const widgetId = '{{ $this->getId() }}';
    let charts = {};

    function destroyCharts() {
        Object.values(charts).forEach(chart => {
            if (chart && typeof chart.destroy === 'function') {
                chart.destroy();
            }
        });
        charts = {};
    }

    function createCharts() {
        destroyCharts();

        // Get chart data from the widget
        const chartData = @json($this->getChartData());

        // Chart.js defaults
        Chart.defaults.font.family = 'Inter, system-ui, sans-serif';
        Chart.defaults.color = '#6B7280';
        Chart.defaults.plugins.legend.display = false;

        // Trainings by Month Chart
        const monthCanvas = document.getElementById(`monthChart-${widgetId}`);
        if (monthCanvas && chartData.monthData) {
            charts.monthChart = new Chart(monthCanvas, {
                type: 'bar',
                data: {
                    labels: chartData.monthData.map(item => item.period),
                    datasets: [{
                        label: 'Trainings',
                        data: chartData.monthData.map(item => item.count),
                        backgroundColor: '#3B82F6',
                        borderColor: '#2563EB',
                        borderWidth: 1,
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { stepSize: 1 }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return `Trainings: ${context.parsed.y}`;
                                }
                            }
                        }
                    }
                }
            });
        }

        // Trainings by County Chart
        const countyCanvas = document.getElementById(`countyChart-${widgetId}`);
        if (countyCanvas && chartData.countyData) {
            charts.countyChart = new Chart(countyCanvas, {
                type: 'bar',
                data: {
                    labels: Object.keys(chartData.countyData),
                    datasets: [{
                        label: 'Trainings',
                        data: Object.values(chartData.countyData),
                        backgroundColor: '#10B981',
                        borderColor: '#059669',
                        borderWidth: 1,
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { stepSize: 1 }
                        },
                        x: {
                            ticks: {
                                maxRotation: 45,
                                minRotation: 0
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return `Trainings: ${context.parsed.y}`;
                                }
                            }
                        }
                    }
                }
            });
        }

        // Participants by Department Chart
        const departmentCanvas = document.getElementById(`departmentChart-${widgetId}`);
        if (departmentCanvas && chartData.departmentData) {
            charts.departmentChart = new Chart(departmentCanvas, {
                type: 'bar',
                data: {
                    labels: Object.keys(chartData.departmentData),
                    datasets: [{
                        label: 'Participants',
                        data: Object.values(chartData.departmentData),
                        backgroundColor: '#8B5CF6',
                        borderColor: '#7C3AED',
                        borderWidth: 1,
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { stepSize: 1 }
                        },
                        x: {
                            ticks: {
                                maxRotation: 45,
                                minRotation: 0
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return `Participants: ${context.parsed.y}`;
                                }
                            }
                        }
                    }
                }
            });
        }

        // Participants by Cadre Chart
        const cadreCanvas = document.getElementById(`cadreChart-${widgetId}`);
        if (cadreCanvas && chartData.cadreData) {
            charts.cadreChart = new Chart(cadreCanvas, {
                type: 'bar',
                data: {
                    labels: Object.keys(chartData.cadreData),
                    datasets: [{
                        label: 'Participants',
                        data: Object.values(chartData.cadreData),
                        backgroundColor: '#F59E0B',
                        borderColor: '#D97706',
                        borderWidth: 1,
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { stepSize: 1 }
                        },
                        x: {
                            ticks: {
                                maxRotation: 45,
                                minRotation: 0
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return `Participants: ${context.parsed.y}`;
                                }
                            }
                        }
                    }
                }
            });
        }
    }

    // Initial chart creation
    createCharts();

    // Listen for Livewire updates
    Livewire.on('refreshCharts', () => {
        setTimeout(() => {
            createCharts();
        }, 100);
    });

    // Listen for widget updates
    document.addEventListener('livewire:updated', function() {
        setTimeout(() => {
            createCharts();
        }, 200);
    });
});
</script>
