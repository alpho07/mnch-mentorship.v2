<div wire:key="approach-distribution-{{ md5(json_encode($filters)) }}">
    <div class="relative w-full" style="height: 320px;">
        <canvas 
            id="approachDistributionChart{{ $this->getId() }}" 
            class="absolute inset-0 w-full h-full"
        ></canvas>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    initApproachDistributionChart{{ $this->getId() }}();
});

Livewire.on('filtersUpdated', () => {
    setTimeout(() => {
        initApproachDistributionChart{{ $this->getId() }}();
    }, 100);
});

function initApproachDistributionChart{{ $this->getId() }}() {
    const chartId = 'approachDistributionChart{{ $this->getId() }}';
    const existingChart = Chart.getChart(chartId);
    if (existingChart) {
        existingChart.destroy();
    }

    const approachData = @json($chartData);
    const ctx = document.getElementById(chartId);
    
    if (!ctx || !approachData.length) {
        // Show no data message
        if (ctx) {
            const parent = ctx.parentElement;
            parent.innerHTML = '<div class="flex items-center justify-center h-full text-gray-500"><p>No data available</p></div>';
        }
        return;
    }

    new Chart(ctx, {
        type: 'pie',
        data: {
            labels: approachData.map(item => item.approach.charAt(0).toUpperCase() + item.approach.slice(1)),
            datasets: [{
                data: approachData.map(item => item.count),
                backgroundColor: [
                    '#3B82F6', // Blue for onsite
                    '#10B981', // Green for virtual  
                    '#F59E0B'  // Yellow for hybrid
                ],
                borderWidth: 2,
                borderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 20,
                        usePointStyle: true,
                        font: {
                            size: 12
                        }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.parsed;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = ((value / total) * 100).toFixed(1);
                            return `${label}: ${value} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
}
</script>
@endpush