<script>
let map;
let countyLayer;
let currentMode = '{{ $mode ?? "training" }}';
let currentYear = '{{ $selectedYear ?? "" }}';
let selectedTraining = null;
let charts = {};

// Debug function
function debug(message, data = null) {
    console.log(`[Dashboard Debug] ${message}`, data || '');
}

// Initialize map with enhanced zoom controls
function initializeMap() {
    try {
        debug('Initializing map with zoom controls...');
        
        if (map) {
            map.remove();
        }

        map = L.map('kenyaMap', {
            center: [-0.5, 37.5], // More centered on Kenya
            zoom: 7, // Higher initial zoom
            minZoom: 6,
            maxZoom: 12,
            zoomControl: false, // Disable default controls, we'll add custom ones
            scrollWheelZoom: true,
            doubleClickZoom: true
        });

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors',
            maxZoom: 18
        }).addTo(map);

        // Add custom zoom controls
        addCustomZoomControls();

        debug('Map initialized successfully with zoom level 7');
        loadCountyData();

    } catch (error) {
        debug('Error initializing map:', error);
        showMapError('Map failed to initialize: ' + error.message);
    }
}

// Add custom zoom control buttons
function addCustomZoomControls() {
    const zoomControl = L.control({ position: 'topright' });

    zoomControl.onAdd = function(map) {
        const div = L.DomUtil.create('div', 'leaflet-bar leaflet-control custom-zoom-controls');
        
        div.innerHTML = `
            <a href="#" title="Zoom In" class="zoom-btn zoom-in-btn">
                <i class="fas fa-plus"></i>
            </a>
            <a href="#" title="Zoom Out" class="zoom-btn zoom-out-btn">
                <i class="fas fa-minus"></i>
            </a>
            <a href="#" title="Fit to Kenya" class="zoom-btn fit-kenya-btn">
                <i class="fas fa-expand-arrows-alt"></i>
            </a>
            <a href="#" title="Reset View" class="zoom-btn reset-view-btn">
                <i class="fas fa-home"></i>
            </a>
        `;

        // Style the container
        div.style.backgroundColor = 'white';
        div.style.boxShadow = '0 2px 8px rgba(0,0,0,0.1)';
        div.style.borderRadius = '8px';

        // Get button elements
        const zoomInBtn = div.querySelector('.zoom-in-btn');
        const zoomOutBtn = div.querySelector('.zoom-out-btn');
        const fitKenyaBtn = div.querySelector('.fit-kenya-btn');
        const resetViewBtn = div.querySelector('.reset-view-btn');

        // Style individual buttons
        [zoomInBtn, zoomOutBtn, fitKenyaBtn, resetViewBtn].forEach((btn, index, array) => {
            btn.style.cssText = `
                width: 34px;
                height: 34px;
                display: flex;
                align-items: center;
                justify-content: center;
                text-decoration: none;
                color: #374151;
                border-bottom: ${index < array.length - 1 ? '1px solid #e5e7eb' : 'none'};
                transition: all 0.2s;
                font-size: 14px;
            `;
            
            btn.addEventListener('mouseenter', function() {
                this.style.backgroundColor = '#f3f4f6';
                this.style.color = '#3b82f6';
            });
            
            btn.addEventListener('mouseleave', function() {
                this.style.backgroundColor = '';
                this.style.color = '#374151';
            });
        });

        // Zoom in
        zoomInBtn.onclick = function(e) {
            e.preventDefault();
            map.zoomIn();
            debug('Map zoomed in to level:', map.getZoom());
            return false;
        };

        // Zoom out
        zoomOutBtn.onclick = function(e) {
            e.preventDefault();
            map.zoomOut();
            debug('Map zoomed out to level:', map.getZoom());
            return false;
        };

        // Fit to Kenya bounds
        fitKenyaBtn.onclick = function(e) {
            e.preventDefault();
            zoomToKenyaBounds();
            return false;
        };

        // Reset view
        resetViewBtn.onclick = function(e) {
            e.preventDefault();
            resetMapView();
            return false;
        };

        return div;
    };

    zoomControl.addTo(map);
}

// Function to zoom to Kenya bounds
function zoomToKenyaBounds() {
    if (map) {
        // Kenya's approximate bounds
        const kenyaBounds = [
            [-4.8, 33.9], // Southwest coordinates
            [5.5, 41.9]    // Northeast coordinates
        ];
        
        map.fitBounds(kenyaBounds, {
            padding: [20, 20],
            maxZoom: 8
        });
        debug('Map fitted to Kenya bounds');
    }
}

// Function to reset map view
function resetMapView() {
    if (map) {
        if (countyLayer && countyLayer.getBounds().isValid()) {
            map.fitBounds(countyLayer.getBounds(), { 
                padding: [20, 20],
                maxZoom: 8 
            });
            debug('Map reset to county layer bounds');
        } else {
            map.setView([-0.5, 37.5], 7);
            debug('Map reset to default view');
        }
    }
}

// Function to set specific zoom level
function setMapZoom(zoomLevel) {
    if (map) {
        map.setZoom(zoomLevel);
        debug(`Map zoomed to level ${zoomLevel}`);
    }
}

// Load county data with enhanced zoom handling
async function loadCountyData(filterTrainingId = null) {
    try {
        debug('Loading county data...', { filterTrainingId, currentMode, currentYear });
        
        const loadingOverlay = document.querySelector('.loading-overlay');
        if (loadingOverlay) {
            loadingOverlay.classList.add('show');
        }

        const params = new URLSearchParams();
        params.set('mode', currentMode);
        if (currentYear) params.set('year', currentYear);
        if (filterTrainingId) params.set('training_id', filterTrainingId);

        const url = `/analytics/dashboard/geojson?${params.toString()}`;
        debug('Fetching from URL:', url);

        const response = await fetch(url);
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        const data = await response.json();
        debug('GeoJSON data received:', data);

        if (!data.features || data.features.length === 0) {
            showMapWarning('No county data available for the selected filters.');
            if (loadingOverlay) loadingOverlay.classList.remove('show');
            return;
        }

        // Clear existing layer
        if (countyLayer) {
            map.removeLayer(countyLayer);
        }

        // Add new layer
        countyLayer = L.geoJSON(data, {
            style: function(feature) {
                return getCountyStyle(feature.properties);
            },
            onEachFeature: function(feature, layer) {
                setupCountyLayer(feature, layer);
            }
        });

        countyLayer.addTo(map);
        
        // Enhanced zoom handling after data loads
        try {
            const bounds = countyLayer.getBounds();
            if (bounds.isValid()) {
                // Use different zoom strategies based on context
                if (filterTrainingId) {
                    // When filtering, zoom in more to show detail
                    map.fitBounds(bounds, { 
                        padding: [30, 30],
                        maxZoom: 9 
                    });
                } else {
                    // For overview, use moderate zoom
                    map.fitBounds(bounds, { 
                        padding: [20, 20],
                        maxZoom: 8 
                    });
                }
                debug('Map bounds fitted with zoom level:', map.getZoom());
            }
        } catch (boundsError) {
            debug('Bounds error:', boundsError);
            // Fallback to default zoom
            setMapZoom(7);
        }

        debug('County layer added successfully');
        
        if (loadingOverlay) {
            loadingOverlay.classList.remove('show');
        }

    } catch (error) {
        debug('Error loading county data:', error);
        const loadingOverlay = document.querySelector('.loading-overlay');
        if (loadingOverlay) {
            loadingOverlay.classList.remove('show');
        }
        showMapError(`Failed to load map data: ${error.message}`);
    }
}

// Get county style based on participants
function getCountyStyle(properties) {
    const participants = properties.total_participants || 0;
    let fillColor = '#6b7280';
    let fillOpacity = 0.7;

    if (participants >= 300) {
        fillColor = '#22c55e';
        fillOpacity = 0.8;
    } else if (participants >= 200) {
        fillColor = '#84cc16';
        fillOpacity = 0.75;
    } else if (participants >= 100) {
        fillColor = '#eab308';
        fillOpacity = 0.7;
    } else if (participants >= 50) {
        fillColor = '#f97316';
        fillOpacity = 0.7;
    } else if (participants >= 1) {
        fillColor = '#ef4444';
        fillOpacity = 0.7;
    } else {
        fillColor = '#6b7280';
        fillOpacity = 0.5;
    }

    return {
        fillColor: fillColor,
        weight: 2,
        opacity: 1,
        color: 'white',
        dashArray: '3',
        fillOpacity: fillOpacity
    };
}

// Setup county layer interactions
function setupCountyLayer(feature, layer) {
    const props = feature.properties;
    const tooltipContent = createTooltipContent(props);

    layer.bindTooltip(tooltipContent, {
        permanent: false,
        direction: 'center',
        className: 'county-tooltip'
    });

    layer.on({
        mouseover: function(e) {
            const layer = e.target;
            layer.setStyle({
                weight: 4,
                color: '#3b82f6',
                dashArray: '',
                fillOpacity: 0.9
            });
            layer.openTooltip();
        },
        mouseout: function(e) {
            if (countyLayer) {
                countyLayer.resetStyle(e.target);
            }
            e.target.closeTooltip();
        },
        click: function(e) {
            const countyId = props.county_id;
            if (countyId) {
                navigateToCounty(countyId);
            }
        }
    });
}

// Create tooltip content
function createTooltipContent(properties) {
    const countyName = properties.county_name || properties.COUNTY || 'Unknown County';
    const programs = properties.total_programs || 0;
    const participants = properties.total_participants || 0;
    const facilitiesWithPrograms = properties.facilities_with_programs || 0;
    const totalFacilities = properties.total_facilities || 0;
    const coverage = properties.coverage_percentage || 0;

    const programLabel = currentMode === 'training' ? 'Training Programs' : 'Mentorship Programs';
    const participantLabel = currentMode === 'training' ? 'Participants' : 'Mentees';

    return `
        <div style="min-width: 200px;">
            <h6 style="margin-bottom: 8px; font-weight: bold;">${countyName} County</h6>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; font-size: 12px;">
                <div style="text-align: center; padding: 4px; background: #f8fafc; border-radius: 4px;">
                    <div style="font-weight: bold; color: #3b82f6;">${programs}</div>
                    <div>${programLabel}</div>
                </div>
                <div style="text-align: center; padding: 4px; background: #f8fafc; border-radius: 4px;">
                    <div style="font-weight: bold; color: #3b82f6;">${participants}</div>
                    <div>${participantLabel}</div>
                </div>
                <div style="text-align: center; padding: 4px; background: #f8fafc; border-radius: 4px;">
                    <div style="font-weight: bold; color: #3b82f6;">${facilitiesWithPrograms}/${totalFacilities}</div>
                    <div>Facilities</div>
                </div>
                <div style="text-align: center; padding: 4px; background: #f8fafc; border-radius: 4px;">
                    <div style="font-weight: bold; color: #3b82f6;">${coverage}%</div>
                    <div>Coverage</div>
                </div>
            </div>
            <div style="text-align: center; margin-top: 8px; font-size: 11px; color: #6b7280;">
                Click to explore county details
            </div>
        </div>
    `;
}

// Navigate to county page
function navigateToCounty(countyId) {
    const params = new URLSearchParams({ mode: currentMode });
    if (currentYear) params.set('year', currentYear);
    window.location.href = `/analytics/dashboard/county/${countyId}?${params.toString()}`;
}

// Error handling functions
function showMapError(message) {
    const mapContainer = document.getElementById('kenyaMap');
    if (mapContainer) {
        mapContainer.innerHTML = `
            <div class="alert alert-danger h-100 d-flex align-items-center justify-content-center">
                <div class="text-center">
                    <i class="fas fa-exclamation-triangle fa-3x mb-3 text-danger"></i>
                    <h6>Map Loading Error</h6>
                    <p class="mb-3">${message}</p>
                    <button class="btn btn-sm btn-outline-danger" onclick="retryMapLoad()">
                        <i class="fas fa-redo"></i> Retry
                    </button>
                </div>
            </div>
        `;
    }
}

function showMapWarning(message) {
    const mapContainer = document.getElementById('kenyaMap');
    if (mapContainer) {
        mapContainer.innerHTML = `
            <div class="alert alert-warning h-100 d-flex align-items-center justify-content-center">
                <div class="text-center">
                    <i class="fas fa-info-circle fa-3x mb-3 text-warning"></i>
                    <h6>No Data Available</h6>
                    <p class="mb-0">${message}</p>
                </div>
            </div>
        `;
    }
}

function retryMapLoad() {
    const mapContainer = document.getElementById('kenyaMap');
    if (mapContainer) {
        mapContainer.innerHTML = '';
    }
    setTimeout(initializeMap, 500);
}

// Initialize charts with fixed sizing
function initializeCharts() {
    try {
        debug('Initializing charts...');
        
        const chartData = @json($chartData ?? []);
        debug('Chart data received:', chartData);
        
        // Destroy existing charts
        Object.values(charts).forEach(chart => {
            if (chart && typeof chart.destroy === 'function') {
                chart.destroy();
            }
        });
        charts = {};
        
        // Department Chart
        const deptCtx = document.getElementById('departmentChart');
        if (deptCtx) {
            const deptLabels = chartData.departments ? chartData.departments.map(d => d.name || 'Unknown') : ['No Data'];
            const deptData = chartData.departments ? chartData.departments.map(d => d.count || 0) : [0];
            
            charts.department = new Chart(deptCtx, {
                type: 'bar',
                data: {
                    labels: deptLabels,
                    datasets: [{
                        label: 'Participants',
                        data: deptData,
                        backgroundColor: '#3b82f6',
                        borderColor: '#1d4ed8',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { 
                        legend: { display: false }
                    },
                    scales: { 
                        y: { 
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    },
                    layout: {
                        padding: 10
                    }
                }
            });
            debug('Department chart created');
        }

        // Cadre Chart
        const cadreCtx = document.getElementById('cadreChart');
        if (cadreCtx) {
            const cadreLabels = chartData.cadres ? chartData.cadres.map(c => c.name || 'Unknown') : ['No Data'];
            const cadreData = chartData.cadres ? chartData.cadres.map(c => c.count || 0) : [0];
            
            charts.cadre = new Chart(cadreCtx, {
                type: 'doughnut',
                data: {
                    labels: cadreLabels,
                    datasets: [{
                        data: cadreData,
                        backgroundColor: [
                            '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6',
                            '#06b6d4', '#84cc16', '#f97316', '#ec4899', '#6366f1'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { 
                        legend: { 
                            position: 'bottom',
                            labels: {
                                boxWidth: 12,
                                padding: 8
                            }
                        }
                    },
                    layout: {
                        padding: 10
                    }
                }
            });
            debug('Cadre chart created');
        }

        // Facility Type Chart
        const facilityCtx = document.getElementById('facilityTypeChart');
        if (facilityCtx) {
            const facilityLabels = chartData.facilityTypes ? chartData.facilityTypes.map(f => f.name || 'Unknown') : ['No Data'];
            const facilityCoverageData = chartData.facilityTypes ? chartData.facilityTypes.map(f => f.coverage_percentage || 0) : [0];
            
            charts.facilityType = new Chart(facilityCtx, {
                type: 'bar',
                data: {
                    labels: facilityLabels,
                    datasets: [{
                        label: 'Coverage %',
                        data: facilityCoverageData,
                        backgroundColor: '#10b981',
                        borderColor: '#059669',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { 
                        legend: { display: false }
                    },
                    scales: { 
                        y: { 
                            beginAtZero: true, 
                            max: 100,
                            ticks: {
                                callback: function(value) {
                                    return value + '%';
                                }
                            }
                        }
                    },
                    layout: {
                        padding: 10
                    }
                }
            });
            debug('Facility type chart created');
        }

        // Trends Chart
        const trendsCtx = document.getElementById('trendsChart');
        if (trendsCtx) {
            const monthlyLabels = chartData.monthly ? chartData.monthly.map(m => m.month || 'Unknown') : ['No Data'];
            const monthlyData = chartData.monthly ? chartData.monthly.map(m => m.count || 0) : [0];
            
            charts.trends = new Chart(trendsCtx, {
                type: 'line',
                data: {
                    labels: monthlyLabels,
                    datasets: [{
                        label: 'Monthly Registrations',
                        data: monthlyData,
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        fill: true,
                        tension: 0.4,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { 
                        legend: { display: false }
                    },
                    scales: { 
                        y: { 
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    },
                    layout: {
                        padding: 10
                    }
                }
            });
            debug('Trends chart created');
        }

        debug('All charts initialized successfully');
    } catch (error) {
        debug('Error initializing charts:', error);
    }
}

// Update charts with better error handling
async function updateCharts(trainingId = null) {
    try {
        debug('Updating charts...', { trainingId });
        
        const params = new URLSearchParams();
        params.set('mode', currentMode);
        if (currentYear) params.set('year', currentYear);
        if (trainingId) params.set('training_id', trainingId);

        const url = `/analytics/dashboard/training-data?${params.toString()}`;
        debug('Fetching chart data from:', url);

        const response = await fetch(url);
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const result = await response.json();
        debug('Chart update response:', result);
        
        if (!result.success) {
            throw new Error(result.error || 'Unknown error occurred');
        }
        
        const data = result;

        // Update each chart with new data
        if (charts.department && data.chartData.departments) {
            const labels = data.chartData.departments.map(d => d.name || 'Unknown');
            const values = data.chartData.departments.map(d => d.count || 0);
            
            charts.department.data.labels = labels;
            charts.department.data.datasets[0].data = values;
            charts.department.update('none');
            debug('Department chart updated');
        }

        if (charts.cadre && data.chartData.cadres) {
            const labels = data.chartData.cadres.map(c => c.name || 'Unknown');
            const values = data.chartData.cadres.map(c => c.count || 0);
            
            charts.cadre.data.labels = labels;
            charts.cadre.data.datasets[0].data = values;
            charts.cadre.update('none');
            debug('Cadre chart updated');
        }

        if (charts.facilityType && data.chartData.facilityTypes) {
            const labels = data.chartData.facilityTypes.map(f => f.name || 'Unknown');
            const values = data.chartData.facilityTypes.map(f => f.coverage_percentage || 0);
            
            charts.facilityType.data.labels = labels;
            charts.facilityType.data.datasets[0].data = values;
            charts.facilityType.update('none');
            debug('Facility type chart updated');
        }

        if (charts.trends && data.chartData.monthly) {
            const labels = data.chartData.monthly.map(m => m.month || 'Unknown');
            const values = data.chartData.monthly.map(m => m.count || 0);
            
            charts.trends.data.labels = labels;
            charts.trends.data.datasets[0].data = values;
            charts.trends.update('none');
            debug('Trends chart updated');
        }

        // Update summary stats
        if (data.summaryStats) {
            updateSummaryStats(data.summaryStats);
        }

        debug('Charts updated successfully');
    } catch (error) {
        debug('Error updating charts:', error);
    }
}

// Update summary statistics
function updateSummaryStats(stats) {
    try {
        const elements = {
            totalPrograms: document.getElementById('totalPrograms'),
            totalParticipants: document.getElementById('totalParticipants'),
            totalFacilities: document.getElementById('totalFacilities'),
            facilityCoverage: document.getElementById('facilityCoverage')
        };
        
        if (elements.totalPrograms) elements.totalPrograms.textContent = (stats.totalPrograms || 0).toLocaleString();
        if (elements.totalParticipants) elements.totalParticipants.textContent = (stats.totalParticipants || 0).toLocaleString();
        if (elements.totalFacilities) elements.totalFacilities.textContent = (stats.totalFacilities || 0).toLocaleString();
        if (elements.facilityCoverage) elements.facilityCoverage.textContent = (stats.facilityCoverage || 0) + '%';
        
        debug('Summary stats updated', stats);
    } catch (error) {
        debug('Error updating summary stats:', error);
    }
}

// Handle training selection with better debugging
function selectTraining(trainingId, trainingTitle) {
    debug('Selecting training:', { trainingId, trainingTitle });
    
    selectedTraining = trainingId;
    
    // Update UI state
    document.querySelectorAll('.training-card').forEach(card => {
        card.classList.toggle('selected', card.dataset.trainingId == trainingId);
    });
    
    // Update titles
    const mapTitle = document.getElementById('mapTitle');
    const mapSubtitle = document.getElementById('mapSubtitle');
    
    if (mapTitle) mapTitle.textContent = `${trainingTitle} - County Coverage`;
    if (mapSubtitle) mapSubtitle.textContent = 'Showing counties participating in this specific program';
    
    // Update chart subtitles
    updateChartSubtitles(true);
    
    // Trigger updates
    debug('Triggering map and chart updates...');
    loadCountyData(trainingId);
    updateCharts(trainingId);
    updateInsights(trainingTitle);
}

// Clear training selection
function clearTrainingSelection() {
    debug('Clearing training selection');
    
    selectedTraining = null;
    
    // Update UI
    document.querySelectorAll('.training-card').forEach(card => {
        card.classList.remove('selected');
    });
    
    // Reset titles
    const mapTitle = document.getElementById('mapTitle');
    const mapSubtitle = document.getElementById('mapSubtitle');
    
    if (mapTitle) mapTitle.textContent = `Kenya Counties ${currentMode === 'training' ? 'Training' : 'Mentorship'} Coverage Map`;
    if (mapSubtitle) mapSubtitle.textContent = 'Overall coverage across all programs. Click on a county to explore detailed analytics.';
    
    // Reset chart subtitles
    updateChartSubtitles(false);
    
    // Trigger updates
    loadCountyData();
    updateCharts();
    updateInsights();
}

// Update chart subtitles
function updateChartSubtitles(filtered) {
    const subtitles = {
        deptChartSubtitle: filtered ? 'Filtered by selected program' : 'Top departments by participation',
        cadreChartSubtitle: filtered ? 'Filtered by selected program' : 'Professional roles distribution',
        facilityChartSubtitle: filtered ? 'Filtered by selected program' : 'Training penetration by facility category',
        trendsChartSubtitle: filtered ? 'Filtered by selected program' : 'Last 6 months registration activity'
    };
    
    Object.entries(subtitles).forEach(([id, text]) => {
        const element = document.getElementById(id);
        if (element) element.textContent = text;
    });
}

// Mode switching function
function switchMode(mode) {
    debug('Switching mode to:', mode);
    
    currentMode = mode;
    selectedTraining = null;
    
    // Update UI
    document.querySelectorAll('.mode-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.mode === mode);
    });
    
    // Reload page with new mode
    const url = new URL(window.location);
    url.searchParams.set('mode', mode);
    if (currentYear) {
        url.searchParams.set('year', currentYear);
    }
    window.location.href = url.toString();
}

// Continuation of Complete Dashboard JavaScript

function updateInsights(selectedTrainingTitle = null) {
    const container = document.getElementById('insightsContent');
    if (!container) return;
    
    if (selectedTrainingTitle) {
        container.innerHTML = `
            <div class="insight-item">
                <i class="fas fa-filter text-primary me-2"></i>
                <small><strong>Filtered View:</strong> ${selectedTrainingTitle}</small>
            </div>
            <div class="insight-item">
                <i class="fas fa-map-marked text-info me-2"></i>
                <small>Map shows counties participating in this program</small>
            </div>
            <div class="insight-item">
                <i class="fas fa-chart-bar text-success me-2"></i>
                <small>Charts filtered to show program-specific data</small>
            </div>
            <div class="insight-item">
                <i class="fas fa-mouse-pointer text-warning me-2"></i>
                <small>Click empty area to clear filter</small>
            </div>
        `;
    } else {
        container.innerHTML = `
            <div class="insight-item">
                <i class="fas fa-chart-line text-success me-2"></i>
                <small>${currentMode === 'training' ? 'Training' : 'Mentorship'} programs active across the country</small>
            </div>
            <div class="insight-item">
                <i class="fas fa-users text-primary me-2"></i>
                <small>Healthcare workers engaged nationwide</small>
            </div>
            <div class="insight-item">
                <i class="fas fa-percentage text-info me-2"></i>
                <small>Facility coverage shown on map</small>
            </div>
            <div class="insight-item">
                <i class="fas fa-mouse-pointer text-warning me-2"></i>
                <small>Click any program to filter the view</small>
            </div>
        `;
    }
}

function toggleInsights() {
    const panel = document.getElementById('insightsPanel');
    if (panel) {
        panel.classList.toggle('show');
    }
}

// Enhanced event listeners setup with detailed logging
function setupEventListeners() {
    debug('Setting up event listeners...');
    
    // Wait for DOM to be fully ready
    setTimeout(() => {
        // Get all training cards
        const trainingCards = document.querySelectorAll('.training-card');
        debug(`Found ${trainingCards.length} training cards`);
        
        if (trainingCards.length === 0) {
            debug('No training cards found! Check HTML structure.');
            return;
        }
        
        // Remove existing listeners by cloning nodes
        trainingCards.forEach((card, index) => {
            const newCard = card.cloneNode(true);
            card.parentNode.replaceChild(newCard, card);
        });
        
        // Get fresh cards after cloning
        const freshCards = document.querySelectorAll('.training-card');
        
        // Add click listeners to fresh cards
        freshCards.forEach((card, index) => {
            debug(`Setting up listener for card ${index + 1}:`, {
                trainingId: card.dataset.trainingId,
                hasTitle: !!card.querySelector('.training-title')
            });
            
            card.addEventListener('click', function(e) {
                debug('Training card clicked!', {
                    cardIndex: index + 1,
                    trainingId: this.dataset.trainingId
                });
                
                e.preventDefault();
                e.stopPropagation();
                
                const trainingId = this.dataset.trainingId;
                const trainingTitleElement = this.querySelector('.training-title');
                
                if (!trainingId) {
                    debug('No training ID found on card');
                    return;
                }
                
                if (!trainingTitleElement) {
                    debug('No training title element found');
                    return;
                }
                
                const trainingTitle = trainingTitleElement.textContent.trim();
                
                if (this.classList.contains('selected')) {
                    debug('Clearing selection (card was already selected)');
                    clearTrainingSelection();
                } else {
                    debug('Selecting training:', trainingTitle);
                    selectTraining(trainingId, trainingTitle);
                }
            });
        });
        
        debug('Event listeners attached to all training cards');
        
    }, 500);
    
    // Setup other event listeners
    setupOtherEventListeners();
}

function setupOtherEventListeners() {
    // Mode toggle buttons
    const modeButtons = document.querySelectorAll('.mode-btn');
    debug(`Found ${modeButtons.length} mode buttons`);
    
    modeButtons.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const mode = this.dataset.mode;
            debug('Mode button clicked:', mode);
            switchMode(mode);
        });
    });
    
    // Year filter
    const yearFilter = document.getElementById('yearFilter');
    if (yearFilter) {
        debug('Year filter found');
        yearFilter.addEventListener('change', function() {
            debug('Year changed to:', this.value);
            const url = new URL(window.location);
            const selectedValue = this.value;
            
            if (selectedValue && selectedValue !== '') {
                url.searchParams.set('year', selectedValue);
            } else {
                url.searchParams.delete('year');
            }
            
            url.searchParams.set('mode', currentMode);
            window.location.href = url.toString();
        });
    } else {
        debug('Year filter not found');
    }
    
    // Insights toggle
    const insightsToggle = document.querySelector('.insights-toggle');
    if (insightsToggle) {
        debug('Insights toggle found');
        insightsToggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            debug('Insights toggle clicked');
            toggleInsights();
        });
    } else {
        debug('Insights toggle not found');
    }
    
    // Close insights when clicking outside
    document.addEventListener('click', function(e) {
        const bubble = document.querySelector('.insights-bubble');
        const panel = document.getElementById('insightsPanel');
        
        if (bubble && !bubble.contains(e.target) && panel && panel.classList.contains('show')) {
            panel.classList.remove('show');
        }
    });
    
    // Clear selection when clicking empty areas
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.training-card') && 
            !e.target.closest('.chart-container') && 
            !e.target.closest('#kenyaMap') &&
            !e.target.closest('.insights-bubble') &&
            !e.target.closest('button') &&
            !e.target.closest('select') &&
            !e.target.closest('.mode-toggle') &&
            selectedTraining) {
            
            const trainingsList = document.getElementById('trainingsList');
            if (trainingsList && trainingsList.contains(e.target) && !e.target.closest('.training-card')) {
                debug('Empty area clicked, clearing selection');
                clearTrainingSelection();
            }
        }
    });
    
    debug('All event listeners setup complete');
}

// Check if required libraries are loaded
function checkLibrariesLoaded() {
    const errors = [];
    
    if (typeof L === 'undefined') {
        errors.push('Leaflet is not loaded');
    }
    
    if (typeof Chart === 'undefined') {
        errors.push('Chart.js is not loaded');
    }
    
    if (errors.length > 0) {
        debug('Missing libraries:', errors);
        showMapError('Required libraries not loaded: ' + errors.join(', '));
        return false;
    }
    
    return true;
}

// Function to handle responsive behavior
function handleResponsive() {
    const isMobile = window.innerWidth <= 768;
    
    // Adjust chart heights for mobile
    if (isMobile) {
        Object.values(charts).forEach(chart => {
            if (chart && chart.canvas) {
                chart.canvas.style.height = '250px';
                chart.resize();
            }
        });
    } else {
        Object.values(charts).forEach(chart => {
            if (chart && chart.canvas) {
                chart.canvas.style.height = '300px';
                chart.resize();
            }
        });
    }
    
    // Adjust map view for mobile
    if (map && isMobile) {
        map.invalidateSize();
        if (countyLayer) {
            const bounds = countyLayer.getBounds();
            if (bounds.isValid()) {
                map.fitBounds(bounds, { padding: [10, 10] });
            }
        }
    }
}

// Add window resize listener
window.addEventListener('resize', function() {
    clearTimeout(window.resizeTimer);
    window.resizeTimer = setTimeout(handleResponsive, 250);
});

// Debug function to check current state
function debugCurrentState() {
    return {
        currentMode,
        currentYear,
        selectedTraining,
        chartsInitialized: Object.keys(charts).length > 0,
        mapInitialized: map !== null,
        countyLayerExists: countyLayer !== null,
        mapZoom: map ? map.getZoom() : null
    };
}

// Function to test training card clicks manually
function testTrainingCardClick() {
    const firstCard = document.querySelector('.training-card');
    if (firstCard) {
        debug('Testing first training card click...');
        firstCard.click();
    } else {
        debug('No training cards found for testing');
    }
}

// Global error handlers
window.addEventListener('error', function(event) {
    debug('Global error caught:', event.error);
});

window.addEventListener('unhandledrejection', function(event) {
    debug('Unhandled promise rejection:', event.reason);
    event.preventDefault();
});

// Enhanced debug object with zoom controls
window.dashboardDebug = {
    // State functions
    getCurrentState: debugCurrentState,
    getCharts: () => charts,
    getMap: () => map,
    getSelectedTraining: () => selectedTraining,
    
    // Action functions
    reloadMap: () => loadCountyData(selectedTraining),
    reloadCharts: () => updateCharts(selectedTraining),
    selectTraining,
    clearSelection: clearTrainingSelection,
    
    // Map zoom functions
    zoomIn: () => map?.zoomIn(),
    zoomOut: () => map?.zoomOut(),
    setZoom: setMapZoom,
    fitToKenya: zoomToKenyaBounds,
    resetView: resetMapView,
    
    // Testing functions
    testClick: testTrainingCardClick,
    setupEvents: setupEventListeners,
    
    // Utilities
    handleResize: handleResponsive
};

// Initialize everything when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    debug('DOM loaded, starting initialization...');
    
    // Check if libraries are loaded
    if (!checkLibrariesLoaded()) {
        return;
    }
    
    // Initialize with delay to ensure everything is loaded
    setTimeout(function() {
        try {
            initializeMap();
            initializeCharts();
            setupEventListeners();
            debug('Dashboard initialization complete');
            
            // Show success message
            console.log('%c✅ Dashboard Ready!', 'color: #10b981; font-weight: bold; font-size: 14px;');
            console.log('Available debug functions:', Object.keys(window.dashboardDebug));
            console.log('Map zoom level:', map ? map.getZoom() : 'Map not initialized');
            
        } catch (error) {
            debug('Initialization error:', error);
            console.error('Dashboard initialization failed:', error);
        }
    }, 1000);
});

// Add enhanced CSS for zoom controls
const zoomControlsCSS = `
    .custom-zoom-controls {
        box-shadow: 0 2px 10px rgba(0,0,0,0.1) !important;
        border-radius: 8px !important;
        overflow: hidden;
    }
    
    .custom-zoom-controls a {
        transition: all 0.2s ease !important;
        border-radius: 0 !important;
    }
    
    .custom-zoom-controls a:hover {
        background-color: #f3f4f6 !important;
        color: #3b82f6 !important;
        transform: scale(1.1);
    }
    
    .custom-zoom-controls a:active {
        transform: scale(0.95);
    }
    
    .leaflet-control-zoom {
        box-shadow: none !important;
    }
    
    /* Enhanced tooltips */
    .county-tooltip {
        background: white !important;
        border: none !important;
        border-radius: 8px !important;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15) !important;
        padding: 0 !important;
    }
    
    .county-tooltip .leaflet-tooltip-content {
        margin: 0 !important;
        padding: 0 !important;
    }
`;

// Add the CSS to the page
const zoomStyleSheet = document.createElement('style');
zoomStyleSheet.textContent = zoomControlsCSS;
document.head.appendChild(zoomStyleSheet);

debug('Dashboard script fully loaded with enhanced zoom controls');
console.log('%c🚀 Dashboard Analytics System Ready', 'color: #3b82f6; font-weight: bold; font-size: 16px;');
console.log('💡 Use window.dashboardDebug for debugging utilities');
console.log('🔍 Zoom controls: dashboardDebug.zoomIn(), dashboardDebug.setZoom(8), etc.');
</script>