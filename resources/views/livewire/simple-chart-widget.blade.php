


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
                        label: 'Demo',
                        data: this.data,
                        backgroundColor: '#3B82F6'
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false }
            });
        }
    }" x-init="init()" class="h-64 w-full">
    <canvas></canvas>
</div>
