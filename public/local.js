// public/js/kenya-heatmap.js

window.initKenyaMapWidget = function(widgetId, options = {}) {
    const el = document.getElementById('kenya-heatmap-' + widgetId);
    if (!el) {
        console.log('[KENYA MAP] Container not found:', widgetId);
        return;
    }
    // Prevent duplicate map rendering
    if (el._mapLoaded) return;
    el._mapLoaded = true;

    // Clear container and set up HTML
    el.innerHTML = '';
    el.style.height = (options.height || 480) + 'px';

    // Create map div
    const mapDivId = 'map-canvas-' + widgetId;
    const mapDiv = document.createElement('div');
    mapDiv.id = mapDivId;
    mapDiv.style.height = '100%';
    mapDiv.style.width = '100%';
    mapDiv.style.borderRadius = '0.5rem';
    el.appendChild(mapDiv);

    // Ensure Leaflet, Heat, and Turf are loaded
    if (!window.L || !window.turf || !window.L.heatLayer) {
        mapDiv.innerHTML = '<div style="color:red">Leaflet/Heat/Turf not loaded</div>';
        return;
    }

    // Get data from options
    const mapData = options.mapData || {};
    const geojson = options.geojson || {};
    if (!geojson.features || !Array.isArray(mapData.countyData)) {
        mapDiv.innerHTML = '<div style="color:red">No geojson or county data provided</div>';
        return;
    }

    // Color scales and lookup
    const intensities = mapData.countyData.map(c => c.intensity);
    const min = Math.min(...intensities, 0), max = Math.max(...intensities, 1);
    const countyLookup = {};
    mapData.countyData.forEach(c => countyLookup[c.name.trim().toLowerCase()] = c);

    // Fire heatmap points
    const heatPoints = [];

    // Intensity and actions (reuse your existing code)
    function getIntensityCategory(intensity, min, max) {
        if (intensity === 0) return "No Data";
        if (intensity <= min + 0.15*(max-min)) return "Low";
        if (intensity <= min + 0.35*(max-min)) return "Moderate";
        if (intensity <= min + 0.7*(max-min)) return "High";
        return "Very High";
    }
    function getAction(category, countyName) {
        switch(category) {
            case "No Data": return {msg: "No recent training data. <b>Urgently assess reporting or program reach.</b>", action: "", icon: "❓"};
            case "Low": return {msg: "Coverage is low. <b>Plan targeted training or mentorship sessions.</b>", action: "", icon: "🟠"};
            case "Moderate": return {msg: "Moderate coverage. <b>Monitor progress, consider refresher training if needed.</b>", action: "", icon: "🟡"};
            case "High": return {msg: "High coverage. <b>Maintain quality and ensure retention.</b>", action: "", icon: "🟢"};
            case "Very High": return {msg: "Excellent coverage! <b>Share best practices with other counties.</b>", action: "", icon: "🏅"};
            default: return {msg: "", action: "", icon: ""};
        }
    }

    // Map logic
    const map = L.map(mapDivId, {
        scrollWheelZoom: true,
        doubleClickZoom: true,
        boxZoom: true,
        center: [-0.7, 37.7],
        zoom: 6,
        minZoom: 5,
        maxZoom: 12,
    });
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);

    // County polygons
    const geoLayer = L.geoJSON(geojson, {
        style: function(feature) {
            const name = (feature.properties.COUNTY || feature.properties.name || '').trim().toLowerCase();
            const d = countyLookup[name] || { intensity: 0 };
            // Heatmap centroid
            const centroid = window.turf.centroid(feature);
            if (centroid?.geometry?.coordinates) {
                const [lon, lat] = centroid.geometry.coordinates;
                heatPoints.push([lat, lon, d.intensity]);
            }
            // Choropleth color
            if (d.intensity === 0) return { fillColor: '#e5e7eb', weight: 2, opacity: 1, color: '#374151', fillOpacity: 0.8 };
            if (d.intensity <= min + 0.15 * (max - min)) return { fillColor: '#86efac', weight: 2, opacity: 1, color: '#374151', fillOpacity: 0.8 };
            if (d.intensity <= min + 0.35 * (max - min)) return { fillColor: '#fde68a', weight: 2, opacity: 1, color: '#374151', fillOpacity: 0.8 };
            if (d.intensity <= min + 0.7 * (max - min)) return { fillColor: '#fb923c', weight: 2, opacity: 1, color: '#374151', fillOpacity: 0.8 };
            return { fillColor: '#dc2626', weight: 2, opacity: 1, color: '#374151', fillOpacity: 0.8 };
        },
        onEachFeature: function(feature, layer) {
            const name = (feature.properties.COUNTY || feature.properties.name || '').trim();
            const key = name.toLowerCase();
            const d = countyLookup[key] || {trainings:0,participants:0,facilities:0,intensity:0};
            const cat = getIntensityCategory(d.intensity, min, max);
            const act = getAction(cat, name);
            const html = `
                <div class="p-2 min-w-48">
                    <h4 class="font-bold text-base mb-2 text-gray-900 flex items-center gap-2">${act.icon} ${name} County</h4>
                    <div class="space-y-1 text-xs">
                        <div class="flex justify-between"><span>Trainings:</span><span class="font-bold">${d.trainings ?? 0}</span></div>
                        <div class="flex justify-between"><span>Participants:</span><span class="font-bold">${d.participants ?? 0}</span></div>
                        <div class="flex justify-between"><span>Facilities:</span><span class="font-bold">${d.facilities ?? 0}</span></div>
                        <div class="flex justify-between border-t pt-1 mt-2"><span>Intensity:</span><span class="font-bold text-blue-700">${(d.intensity ?? 0).toFixed(1)}</span></div>
                        <div class="flex justify-between"><span>Intensity Category:</span><span class="font-bold">${cat}</span></div>
                    </div>
                    <div class="mt-2 text-xs italic text-gray-800">${act.msg}</div>
                    <div class="mt-2 text-[11px] text-gray-500 border-t pt-1">
                        <b>What is intensity?</b><br>
                        Training intensity reflects relative coverage in the county (grey = lowest, red = highest).
                    </div>
                </div>
            `;
            layer.bindPopup(html);
            layer.on('mouseover', function() {
                layer.setStyle({ weight: 3, color: '#1e293b' });
            });
            layer.on('mouseout', function() {
                layer.setStyle({ weight: 2, color: '#374151' });
            });
        }
    }).addTo(map);

    // Fire heatmap overlay
    const maxHeat = Math.max(...heatPoints.map(p=>p[2]),1);
    const heatNorm = heatPoints.map(p=>[p[0],p[1],p[2]/maxHeat]);
    L.heatLayer(heatNorm, {
        radius: 42, blur: 25, minOpacity: 0.44, maxZoom: 12,
        gradient: {0:'#fef3c7',0.2:'#fde68a',0.4:'#fca311',0.7:'#dc2626',1:'#7f1d1d'}
    }).addTo(map);

    // Fit bounds
    map.fitBounds(geoLayer.getBounds(), { maxZoom: 8 });

    // Update summary stats (if you want to update widget stats, expose a callback or use options)
    if (typeof options.onStats === 'function') {
        const active = mapData.countyData.filter(c => c.trainings > 0).length;
        const avg = mapData.countyData.reduce((sum, c) => sum + c.intensity, 0) / (mapData.countyData.length || 1);
        const pct = (active / 47) * 100;
        options.onStats({active, avg, pct});
    }
};
