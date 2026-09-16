<div wire:key="monthly-trends-{{ md5(json_encode($filters)) }}">
    <div class="relative w-full" style="height: 320px;">
        <canvas 
            id="monthlyTrendsChart{{ $this->getId() }}" 
            class="absolute inset-0 w-full h-full"
        ></canvas>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    initMonthlyTrendsChart{{ $this->getId() }}();
});

// Listen for Livewire updates
Livewire.on('filtersUpdated', () => {
    setTimeout(() => {
        initMonthlyTrendsChart{{ $this->getId() }}();
    }, 100);
});

function initMonthlyTrendsChart{{ $this->getId() }}() {
    const chartId = 'monthlyTrendsChart{{ $this->getId() }}';
    const existingChart = Chart.getChart(chartId);
    if (existingChart) {
        existingChart.destroy();
    }

    const monthlyData = @json($chartData);
    const ctx = document.getElementById(chartId);
    
    if (!ctx || !monthlyData.length) {
        // Show no data message
        if (ctx) {
            const parent = ctx.parentElement;
            parent.innerHTML = '<div class="flex items-center justify-center h-full text-gray-500"><p>No data available</p></div>';
        }
        return;
    }

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: monthlyData.map(item => item.period),
            datasets: [{
                label: 'Trainings',
                data: monthlyData.map(item => item.trainings),
                borderColor: 'rgb(59, 130, 246)',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                tension: 0.4,
                fill: true
            }, {
                label: 'Participants',
                data: monthlyData.map(item => item.participants),
                borderColor: 'rgb(16, 185, 129)',
                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                tension: 0.4,
                fill: true,
                yAxisID: 'y1'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            scales: {
                x: {
                    display: true,
                    title: {
                        display: true,
                        text: 'Period'
                    }
                },
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    title: {
                        display: true,
                        text: 'Trainings'
                    },
                    beginAtZero: true
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: 'Participants'
                    },
                    beginAtZero: true,
                    grid: {
                        drawOnChartArea: false,
                    },
                }
            },
            plugins: {
                legend: {
                    position: 'top'
                },
                tooltip: {
                    mode: 'index',
                    intersect: false
                }
            }
        }
    });
}
</script>
@endpush