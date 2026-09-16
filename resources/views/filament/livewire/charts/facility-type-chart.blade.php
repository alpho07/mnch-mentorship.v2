<div wire:key="facility-type-{{ md5(json_encode($filters)) }}">
    <div class="relative w-full" style="height: 320px;">
        <canvas 
            id="facilityTypeChart{{ $this->getId() }}" 
            class="absolute inset-0 w-full h-full"
        ></canvas>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    initFacilityTypeChart{{ $this->getId() }}();
});

Livewire.on('filtersUpdated', () => {
    setTimeout(() => {
        initFacilityTypeChart{{ $this->getId() }}();
    }, 100);
});

function initFacilityTypeChart{{ $this->getId() }}() {
    const chartId = 'facilityTypeChart{{ $this->getId() }}';
    const existingChart = Chart.getChart(chartId);
    if (existingChart) {
        existingChart.destroy();
    }

    const facilityTypeData = @json($chartData);
    const ctx = document.getElementById(chartId);
    
    if (!ctx || !facilityTypeData.length) {
        // Show no data message
        if (ctx) {
            const parent = ctx.parentElement;
            parent.innerHTML = '<div class="flex items-center justify-center h-full text-gray-500"><p>No data available</p></div>';
        }
        return;
    }

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: facilityTypeData.map(item => item.facility_type),
            datasets: [{
                label: 'Trainings',
                data: facilityTypeData.map(item => item.count),
                backgroundColor: 'rgba(59, 130, 246, 0.8)',
                borderColor: 'rgb(59, 130, 246)',
                borderWidth: 2,
                borderRadius: 4,
                borderSkipped: false,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: {
                    display: true,
                    title: {
                        display: true,
                        text: 'Facility Type'
                    },
                    ticks: {
                        maxRotation: 45,
                        minRotation: 0
                    }
                },
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Number of Trainings'
                    },
                    ticks: {
                        stepSize: 1
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                },
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
</script>
@endpush