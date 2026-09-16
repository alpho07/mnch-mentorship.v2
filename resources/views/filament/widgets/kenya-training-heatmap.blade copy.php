@php $widgetId = $widgetId ?? ($this->getId() ?? 'kenya-heatmap-' . uniqid()); @endphp

<div id="kenya-heatmap-{{ $widgetId }}" style="height:480px;background:#f3f4f6;border-radius:0.5rem;"></div>

<script>
    window.initKenyaMapWidget &&
        window.initKenyaMapWidget(
            '{{ $widgetId }}',
            {
                height: 480,
                mapData: @json($this->getMapData()),
                geojson: @json(json_decode(file_get_contents(public_path('kenyan-counties.geojson')), true))
            }
        );
    document.addEventListener('livewire:navigated', function() {
        window.initKenyaMapWidget &&
            window.initKenyaMapWidget(
                '{{ $widgetId }}',
                {
                    height: 480,
                    mapData: @json($this->getMapData()),
                    geojson: @json(json_decode(file_get_contents(public_path('kenyan-counties.geojson')), true))
                }
            );
    });
</script>

