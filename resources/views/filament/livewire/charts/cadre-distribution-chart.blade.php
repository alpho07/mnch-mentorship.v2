<div wire:key="cadre-distribution-{{ md5(json_encode($filters)) }}">
    <div class="relative w-full" style="height: 320px;">
        <canvas 
            id="cadreDistributionChart{{ $this->getId() }}" 
            class="absolute inset-0 w-full h-full"
        ></canvas>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    initCadreDistributionChart{{ $this->getId() }}();
});

Livewire.on('filtersUpdated', () => {
    setTimeout(() => {
        initCadreDistributionChart{{ $this->getId() }}();
    }, 100);
});

function initCadreDistributionChart{{ $this->getId() }}() {
    const chartId = 'cadreDistributionChart{{ $this->getId() }}';
    const existingChart = Chart.getChart(chartId);
    if (existingChart) {
        existingChart.destroy();
    }

    const cadreData = @json($chartData);
    const ctx = document.getElementById(chartId);
    
    if (!ctx || !cadreData.length) {
        // Show no data message
        if (ctx) {
            const parent = ctx.parentElement;
            parent.innerHTML = '<div class="flex items-center justify-center h-full text-gray-500"><p>No data available</p></div>';
        }
        return;
    }

    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: cadreData.map(item => item.cadre),
            datasets: [{
                data: cadreData.map(item => item.count),
                backgroundColor: [
                    '#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6',
                    '#06B6D4', '#84CC16', '#F97316', '#EC4899', '#6B7280',
                    '#14B8A6', '#F472B6', '#A78BFA', '#FB7185', '#34D399'
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
            },
            cutout: '50%'
        }
    });
}
</script>
@endpush