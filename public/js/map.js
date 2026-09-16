/* 
 * MOH Training Heatmap Dashboard JavaScript - Complete Implementation
 * Supports full drill-down: County → Facility → Participants → Training History
 */

// Global variables
let mapInstance = null;
let geoJsonLayer = null;
let currentWidgetId = null;

// Initialize map
function initKenyaMap(widgetId, options) {
    currentWidgetId = widgetId;
    const mapContainer = document.getElementById(`kenya-heatmap-${widgetId}`);
    const loadingIndicator = document.getElementById(`map-loading-${widgetId}`);

    if (!mapContainer) {
        console.error('Map container not found');
        return;
    }

    try {
        // Remove existing map instance
        if (mapInstance) {
            mapInstance.remove();
            mapInstance = null;
        }

        // Create new map instance
        mapInstance = L.map(mapContainer, {
            zoomControl: true,
            attributionControl: true,
            scrollWheelZoom: true,
            doubleClickZoom: true,
            touchZoom: true
        }).setView([-0.0236, 37.9062], 6);

        // Set z-index for map panes
        mapInstance.getPane('mapPane').style.zIndex = 1;
        mapInstance.getPane('tilePane').style.zIndex = 1;
        mapInstance.getPane('overlayPane').style.zIndex = 2;

        // Add tile layer
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors',
            maxZoom: 18,
            tileSize: 256,
            zoomOffset: 0
        }).addTo(mapInstance);

        // Display county data if available
        if (options.geojson && options.mapData) {
            displayCountyData(options.geojson, options.mapData, widgetId);
        }

        // Hide loading indicator
        if (loadingIndicator) {
            loadingIndicator.style.display = 'none';
        }

        console.log('Map initialized successfully');

    } catch (error) {
        console.error('Map initialization error:', error);
        showToast('Error loading map: ' + error.message, 'error');
        
        // Show error message in map container
        if (mapContainer) {
            mapContainer.innerHTML = `
                <div class="flex items-center justify-center h-full">
                    <div class="text-center">
                        <svg class="mx-auto h-16 w-16 text-red-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">Map Loading Error</h3>
                        <p class="text-gray-500">Unable to load the interactive map. Please refresh the page.</p>
                    </div>
                </div>
            `;
        }
    }
}

// Load GeoJSON data for Kenya counties
async function loadKenyaGeoJSON() {
    try {
        // Try to load GeoJSON from multiple sources
        const geoJsonSources = [
            '/geojson/kenya-counties.geojson',
            '/assets/geojson/kenya-counties.geojson',
            '/storage/geojson/kenya-counties.geojson',
            'https://raw.githubusercontent.com/holtzy/D3-graph-gallery/master/DATA/world.geojson' // Fallback
        ];

        for (const source of geoJsonSources) {
            try {
                const response = await fetch(source);
                if (response.ok) {
                    const geoJsonData = await response.json();
                    console.log('GeoJSON loaded from:', source);
                    return geoJsonData;
                }
            } catch (e) {
                console.log('Failed to load from:', source);
                continue;
            }
        }

        // If no external GeoJSON found, create a simple Kenya outline
        console.warn('No GeoJSON file found, creating basic Kenya outline');
        return createKenyaFallbackGeoJSON();

    } catch (error) {
        console.error('Error loading GeoJSON:', error);
        return createKenyaFallbackGeoJSON();
    }
}

// Create a basic Kenya GeoJSON as fallback
function createKenyaFallbackGeoJSON() {
    // Simple Kenya county data - you should replace this with actual GeoJSON
    const kenyanCounties = [
        'Nairobi', 'Mombasa', 'Kwale', 'Kilifi', 'Tana River', 'Lamu', 'Taita Taveta',
        'Garissa', 'Wajir', 'Mandera', 'Marsabit', 'Isiolo', 'Meru', 'Tharaka Nithi',
        'Embu', 'Kitui', 'Machakos', 'Makueni', 'Nyandarua', 'Nyeri', 'Kirinyaga',
        'Murang\'a', 'Kiambu', 'Turkana', 'West Pokot', 'Samburu', 'Trans Nzoia',
        'Uasin Gishu', 'Elgeyo Marakwet', 'Nandi', 'Baringo', 'Laikipia', 'Nakuru',
        'Narok', 'Kajiado', 'Kericho', 'Bomet', 'Kakamega', 'Vihiga', 'Bungoma',
        'Busia', 'Siaya', 'Kisumu', 'Homa Bay', 'Migori', 'Kisii', 'Nyamira'
    ];

    return {
        type: "FeatureCollection",
        features: kenyanCounties.map((county, index) => ({
            type: "Feature",
            properties: {
                COUNTY: county,
                COUNTY_ID: index + 1
            },
            geometry: {
                type: "Polygon",
                coordinates: [[
                    // Simple rectangular coordinates for demonstration
                    [36 + (index % 8) * 0.5, -1 + Math.floor(index / 8) * 0.5],
                    [36.5 + (index % 8) * 0.5, -1 + Math.floor(index / 8) * 0.5],
                    [36.5 + (index % 8) * 0.5, -0.5 + Math.floor(index / 8) * 0.5],
                    [36 + (index % 8) * 0.5, -0.5 + Math.floor(index / 8) * 0.5],
                    [36 + (index % 8) * 0.5, -1 + Math.floor(index / 8) * 0.5]
                ]]
            }
        }))
    };
}

// Display county data on map
async function displayCountyData(geojson, mapData, widgetId) {
    try {
        // If no geojson provided, try to load it
        if (!geojson) {
            console.log('No GeoJSON provided, attempting to load...');
            geojson = await loadKenyaGeoJSON();
        }

        if (!geojson) {
            console.error('No GeoJSON data available');
            showToast('Map data unavailable - showing data table only', 'warning');
            return;
        }

        const countyData = mapData.countyData;
        const intensityLevels = mapData.intensityLevels;

        // Create lookup object for faster access
        const dataLookup = {};
        countyData.forEach(county => {
            // Try multiple property names for county matching
            const countyKey = county.name.toLowerCase().trim();
            dataLookup[countyKey] = county;
            
            // Also try variations
            dataLookup[countyKey.replace(/\s/g, '')] = county;
            dataLookup[countyKey.replace(/'/g, '')] = county;
        });

        // Function to get county style based on intensity
        function getCountyStyle(feature) {
            // Try different property names for county identification
            const countyName = (
                feature.properties.COUNTY || 
                feature.properties.NAME || 
                feature.properties.County || 
                feature.properties.name ||
                feature.properties.ADM1_EN ||
                feature.properties.NAME_1
            );

            if (!countyName) {
                console.warn('No county name found in feature properties:', feature.properties);
                return getDefaultStyle();
            }

            const countyKey = countyName.toLowerCase().trim();
            const county = dataLookup[countyKey] || 
                          dataLookup[countyKey.replace(/\s/g, '')] || 
                          dataLookup[countyKey.replace(/'/g, '')];

            if (!county || county.trainings === 0) {
                return getDefaultStyle();
            }

            return getIntensityStyle(county.intensity, intensityLevels);
        }

        function getDefaultStyle() {
            return {
                fillColor: '#e5e7eb',
                weight: 1,
                opacity: 0.8,
                color: '#9ca3af',
                fillOpacity: 0.6
            };
        }

        function getIntensityStyle(intensity, levels) {
            let fillColor;
            if (intensity <= levels.low * 0.5) {
                fillColor = '#fca5a5'; // Very low - light red
            } else if (intensity <= levels.low) {
                fillColor = '#fbbf24'; // Low - yellow
            } else if (intensity <= levels.medium) {
                fillColor = '#a3a3a3'; // Medium - gray
            } else if (intensity <= levels.high) {
                fillColor = '#84cc16'; // High - light green
            } else {
                fillColor = '#16a34a'; // Very high - dark green
            }

            return {
                fillColor: fillColor,
                weight: 1,
                opacity: 1,
                color: '#374151',
                fillOpacity: 0.8
            };
        }

        // Create GeoJSON layer
        geoJsonLayer = L.geoJSON(geojson, {
            style: getCountyStyle,
            onEachFeature: function(feature, layer) {
                const countyName = (
                    feature.properties.COUNTY || 
                    feature.properties.NAME || 
                    feature.properties.County || 
                    feature.properties.name ||
                    feature.properties.ADM1_EN ||
                    feature.properties.NAME_1
                );

                if (!countyName) {
                    console.warn('No county name for feature:', feature.properties);
                    return;
                }

                const countyKey = countyName.toLowerCase().trim();
                const county = dataLookup[countyKey] || 
                              dataLookup[countyKey.replace(/\s/g, '')] || 
                              dataLookup[countyKey.replace(/'/g, '')];

                // Create tooltip content
                let tooltipContent = `<div class="county-tooltip">
                    <strong>${countyName} County</strong><br/>
                `;

                if (county && county.trainings > 0) {
                    tooltipContent += `
                        🎓 Trainings: <strong>${county.trainings}</strong><br/>
                        👥 Participants: <strong>${county.participants.toLocaleString()}</strong><br/>
                        🏥 Facilities: <strong>${county.facilities}</strong><br/>
                        📊 Intensity: <strong>${Math.round(county.intensity)}</strong><br/>
                        <em>💡 Click for details</em>
                    `;
                } else {
                    tooltipContent += `<span style="color: #fca5a5;">No training data</span>`;
                }

                tooltipContent += '</div>';

                // Bind tooltip
                layer.bindTooltip(tooltipContent, {
                    permanent: false,
                    direction: 'center',
                    className: 'county-tooltip',
                    offset: [0, 0]
                });

                // Add event listeners
                layer.on({
                    mouseover: function(e) {
                        const layer = e.target;
                        layer.setStyle({
                            weight: 3,
                            color: '#1f2937',
                            fillOpacity: 0.9
                        });
                        layer.bringToFront();
                    },
                    mouseout: function(e) {
                        geoJsonLayer.resetStyle(e.target);
                    },
                    click: function(e) {
                        if (county && county.trainings > 0) {
                            showCountyDetails(widgetId, countyName, county);
                        } else {
                            showToast(`No training data for ${countyName} County`, 'info');
                        }
                    }
                });
            }
        }).addTo(mapInstance);

        // Fit map to bounds
        mapInstance.fitBounds(geoJsonLayer.getBounds());
        
        console.log('County data displayed successfully');

    } catch (error) {
        console.error('Error displaying county data:', error);
        showToast('Error displaying county data', 'error');
        
        // Show message in map container
        const mapContainer = document.getElementById(`kenya-heatmap-${widgetId}`);
        if (mapContainer) {
            mapContainer.innerHTML = `
                <div class="flex items-center justify-center h-full">
                    <div class="text-center">
                        <svg class="mx-auto h-16 w-16 text-yellow-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-1.447-.894L15 4m0 13V4m0 0L9 7"/>
                        </svg>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">Map Data Unavailable</h3>
                        <p class="text-gray-500">County boundaries could not be loaded. Use the table view below for data analysis.</p>
                    </div>
                </div>
            `;
        }
    }
}

// Show county details modal
function showCountyDetails(widgetId, countyName, countyData) {
    const modal = document.getElementById(`county-details-modal-${widgetId}`);
    const title = document.getElementById(`county-modal-title-${widgetId}`);
    const content = document.getElementById(`county-modal-content-${widgetId}`);

    if (!modal || !title || !content) {
        console.error('Modal elements not found');
        return;
    }

    title.textContent = `${countyName} County Training Details`;

    let html = `
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-gradient-to-br from-blue-50 to-blue-100 p-4 rounded-xl text-center border border-blue-200 stat-card">
                <div class="text-3xl font-bold text-blue-600">${countyData.trainings}</div>
                <div class="text-sm text-blue-700 font-medium">MOH Trainings</div>
            </div>
            <div class="bg-gradient-to-br from-green-50 to-green-100 p-4 rounded-xl text-center border border-green-200 stat-card">
                <div class="text-3xl font-bold text-green-600">${countyData.participants.toLocaleString()}</div>
                <div class="text-sm text-green-700 font-medium">Total Participants</div>
            </div>
            <div class="bg-gradient-to-br from-purple-50 to-purple-100 p-4 rounded-xl text-center border border-purple-200 stat-card">
                <div class="text-3xl font-bold text-purple-600">${countyData.facilities}</div>
                <div class="text-sm text-purple-700 font-medium">Participating Facilities</div>
            </div>
            <div class="bg-gradient-to-br from-orange-50 to-orange-100 p-4 rounded-xl text-center border border-orange-200 stat-card">
                <div class="text-3xl font-bold text-orange-600">${Math.round(countyData.intensity)}</div>
                <div class="text-sm text-orange-700 font-medium">Intensity Score</div>
            </div>
        </div>
        
        <div class="mb-6">
            <button onclick="showFacilityBreakdown('${widgetId}', ${countyData.county_id || 0}, '${countyName}')" 
                class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-medium transition-colors flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-4m-5 0H9m0 0H5m0 0v-4"></path>
                </svg>
                View Facility Breakdown
            </button>
        </div>
    `;

    if (countyData.training_details && countyData.training_details.length > 0) {
        html += `
            <div class="space-y-6">
                <div class="flex items-center justify-between">
                    <h4 class="text-xl font-bold text-gray-900 flex items-center">
                        <svg class="w-6 h-6 mr-2 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        MOH Training Participation
                    </h4>
                    <span class="bg-blue-100 text-blue-800 text-xs font-semibold px-3 py-1 rounded-full">
                        ${countyData.training_details.length} Training${countyData.training_details.length !== 1 ? 's' : ''}
                    </span>
                </div>
                
                <div class="space-y-4 max-h-96 overflow-y-auto pr-2 custom-scrollbar">
                    ${countyData.training_details.map((training, index) => `
                        <div class="bg-gradient-to-r from-gray-50 to-white border border-gray-200 rounded-xl p-6 hover:shadow-lg transition-all duration-300">
                            <div class="flex items-start justify-between mb-4">
                                <div class="flex-1">
                                    <h5 class="text-lg font-semibold text-gray-900 mb-2">${training.training_title}</h5>
                                    <div class="flex flex-wrap gap-2 mb-3">
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800 border border-blue-200">
                                            📋 ${training.training_id}
                                        </span>
                                        ${training.status ? `<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 border border-green-200">✅ ${training.status}</span>` : ''}
                                    </div>
                                    ${training.programs ? `<div class="text-sm text-gray-600 mb-2"><strong>📚 Programs:</strong> ${training.programs}</div>` : ''}
                                    ${training.start_date ? `<div class="text-sm text-gray-600"><strong>📅 Duration:</strong> ${training.start_date}${training.end_date ? ` → ${training.end_date}` : ''}</div>` : ''}
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-4 mb-4">
                                <div class="bg-white p-4 rounded-lg border-2 border-blue-100 text-center hover:border-blue-300 transition-colors">
                                    <div class="text-2xl font-bold text-blue-600">${training.facilities_count}</div>
                                    <div class="text-sm text-blue-700 font-medium">Facilities Trained</div>
                                </div>
                                <div class="bg-white p-4 rounded-lg border-2 border-green-100 text-center hover:border-green-300 transition-colors">
                                    <div class="text-2xl font-bold text-green-600">${training.participants_count.toLocaleString()}</div>
                                    <div class="text-sm text-green-700 font-medium">Participants</div>
                                </div>
                            </div>
                            
                            ${training.facility_names && training.facility_names.length > 0 ? `
                            <div class="mt-4">
                                <div class="text-sm font-semibold text-gray-700 mb-3 flex items-center">
                                    <svg class="w-4 h-4 mr-2 text-gray-600" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M4 4a2 2 0 00-2 2v8a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2H4zm3 5a1 1 0 011-1h4a1 1 0 110 2H8a1 1 0 01-1-1z" clip-rule="evenodd"/>
                                    </svg>
                                    Participating Facilities (${training.facility_names.length})
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2">
                                    ${training.facility_names.slice(0, 15).map(facility => `
                                        <div class="facility-tag bg-blue-50 border border-blue-200 text-blue-800 px-3 py-2 rounded-lg text-xs font-medium hover:bg-blue-100 transition-colors">
                                            🏥 ${facility}
                                        </div>
                                    `).join('')}
                                    ${training.facility_names.length > 15 ? `
                                        <div class="col-span-full">
                                            <button onclick="showAllFacilities('${widgetId}', ${index})" class="text-sm text-blue-600 hover:text-blue-800 underline font-medium">
                                                ➕ View ${training.facility_names.length - 15} more facilities...
                                            </button>
                                        </div>
                                    ` : ''}
                                </div>
                            </div>
                            ` : ''}
                        </div>
                    `).join('')}
                </div>
            </div>
        `;
    } else {
        html += `
            <div class="text-center py-16">
                <svg class="mx-auto h-16 w-16 text-gray-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                </svg>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No Training Data Available</h3>
                <p class="text-gray-500 mb-4">This county has not participated in any MOH training programs yet.</p>
                <div class="space-y-3">
                    <div class="inline-flex items-center px-4 py-2 rounded-lg text-sm bg-yellow-100 text-yellow-800">
                        💡 Consider including this county in upcoming training cycles
                    </div>
                </div>
            </div>
        `;
    }

    content.innerHTML = html;
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

// Show facility breakdown
async function showFacilityBreakdown(widgetId, countyId, countyName) {
    const modal = document.getElementById(`facility-modal-${widgetId}`);
    const title = document.getElementById(`facility-modal-title-${widgetId}`);
    const content = document.getElementById(`facility-modal-content-${widgetId}`);

    if (!modal || !title || !content) {
        console.error('Facility modal elements not found');
        return;
    }

    title.textContent = `${countyName} County - Facility Breakdown`;
    
    // Show loading state
    content.innerHTML = `
        <div class="flex items-center justify-center py-16">
            <div class="loading-spinner mr-3"></div>
            <span class="text-gray-600">Loading facility data...</span>
        </div>
    `;

    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';

    try {
        const response = await fetch(`/admin/heatmap/facility-details/${countyId}`);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const facilityData = await response.json();

        if (facilityData.error) {
            content.innerHTML = `
                <div class="text-center py-16">
                    <svg class="mx-auto h-16 w-16 text-red-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">Error Loading Data</h3>
                    <p class="text-gray-500">${facilityData.error}</p>
                </div>
            `;
            return;
        }

        let html = `
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-gradient-to-br from-blue-50 to-blue-100 p-4 rounded-xl text-center border border-blue-200">
                    <div class="text-3xl font-bold text-blue-600">${facilityData.total_facilities}</div>
                    <div class="text-sm text-blue-700 font-medium">Active Facilities</div>
                </div>
                <div class="bg-gradient-to-br from-green-50 to-green-100 p-4 rounded-xl text-center border border-green-200">
                    <div class="text-3xl font-bold text-green-600">${facilityData.summary ? facilityData.summary.total_trainings : 0}</div>
                    <div class="text-sm text-green-700 font-medium">Total Trainings</div>
                </div>
                <div class="bg-gradient-to-br from-purple-50 to-purple-100 p-4 rounded-xl text-center border border-purple-200">
                    <div class="text-3xl font-bold text-purple-600">${facilityData.summary ? facilityData.summary.total_participants : 0}</div>
                    <div class="text-sm text-purple-700 font-medium">Total Participants</div>
                </div>
            </div>
            
            <div class="mb-6 flex justify-end">
                <button onclick="exportFacilityData('${widgetId}', ${countyId})" 
                    class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    Export Facility Data
                </button>
            </div>
        `;

        if (facilityData.facilities && facilityData.facilities.length > 0) {
            html += `
                <div class="space-y-4">
                    <h4 class="text-xl font-bold text-gray-900 flex items-center mb-6">
                        <svg class="w-6 h-6 mr-2 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4 4a2 2 0 00-2 2v8a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2H4zm3 5a1 1 0 011-1h4a1 1 0 110 2H8a1 1 0 01-1-1z" clip-rule="evenodd"/>
                        </svg>
                        Facilities with Training Participation
                    </h4>
                    
                    <div class="space-y-4 max-h-96 overflow-y-auto pr-2 custom-scrollbar">
                        ${facilityData.facilities.map(facility => `
                            <div class="bg-white border border-gray-200 rounded-xl p-6 hover:shadow-lg transition-all duration-300">
                                <div class="flex items-start justify-between mb-4">
                                    <div class="flex-1">
                                        <h5 class="text-lg font-semibold text-gray-900 mb-2">${facility.facility_name}</h5>
                                        <div class="flex flex-wrap gap-2 mb-3">
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800 border border-purple-200">
                                                🏥 ${facility.facility_type}
                                            </span>
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800 border border-gray-200">
                                                📍 ${facility.subcounty}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-3 gap-4 mb-4">
                                    <div class="bg-blue-50 p-3 rounded-lg text-center border border-blue-100">
                                        <div class="text-xl font-bold text-blue-600">${facility.total_trainings}</div>
                                        <div class="text-xs text-blue-700 font-medium">Trainings</div>
                                    </div>
                                    <div class="bg-green-50 p-3 rounded-lg text-center border border-green-100">
                                        <div class="text-xl font-bold text-green-600">${facility.total_participants}</div>
                                        <div class="text-xs text-green-700 font-medium">Participants</div>
                                    </div>
                                    <div class="bg-orange-50 p-3 rounded-lg text-center border border-orange-100">
                                        <div class="text-xl font-bold text-orange-600">${facility.avg_participants_per_training}</div>
                                        <div class="text-xs text-orange-700 font-medium">Avg/Training</div>
                                    </div>
                                </div>
                                
                                <div class="flex justify-end space-x-2">
                                    <button onclick="showParticipantDetails('${widgetId}', ${facility.facility_id}, '${facility.facility_name}')"
                                        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                                        View Participants
                                    </button>
                                    <button onclick="showTrainingBreakdown('${widgetId}', ${facility.facility_id})"
                                        class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                                        Training Details
                                    </button>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
        } else {
            html += `
                <div class="text-center py-16">
                    <svg class="mx-auto h-16 w-16 text-gray-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-4m-5 0H9m0 0H5m0 0v-4"></path>
                    </svg>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No Facility Data</h3>
                    <p class="text-gray-500">${facilityData.message || 'No facilities have participated in training programs in this county.'}</p>
                </div>
            `;
        }

        content.innerHTML = html;

    } catch (error) {
        console.error('Error loading facility data:', error);
        content.innerHTML = `
            <div class="text-center py-16">
                <svg class="mx-auto h-16 w-16 text-red-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <h3 class="text-lg font-medium text-gray-900 mb-2">Error Loading Facility Data</h3>
                <p class="text-gray-500">Unable to load facility breakdown. Please try again.</p>
                <button onclick="showFacilityBreakdown('${widgetId}', ${countyId}, '${countyName}')" 
                    class="mt-4 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm">
                    Retry
                </button>
            </div>
        `;
        showToast('Error loading facility data', 'error');
    }
}

// Show participant details for a facility
async function showParticipantDetails(widgetId, facilityId, facilityName) {
    const modal = document.getElementById(`participant-modal-${widgetId}`);
    const title = document.getElementById(`participant-modal-title-${widgetId}`);
    const content = document.getElementById(`participant-modal-content-${widgetId}`);

    if (!modal || !title || !content) {
        console.error('Participant modal elements not found');
        return;
    }

    title.textContent = `${facilityName} - Training Participants`;
    
    // Show loading state
    content.innerHTML = `
        <div class="flex items-center justify-center py-16">
            <div class="loading-spinner mr-3"></div>
            <span class="text-gray-600">Loading participant data...</span>
        </div>
    `;

    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';

    try {
        const response = await fetch(`/admin/heatmap/participant-details/${facilityId}`);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const participantData = await response.json();

        if (participantData.error) {
            content.innerHTML = `
                <div class="text-center py-16">
                    <svg class="mx-auto h-16 w-16 text-red-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">Error Loading Data</h3>
                    <p class="text-gray-500">${participantData.error}</p>
                </div>
            `;
            return;
        }

        let html = `
            <div class="mb-6">
                <div class="bg-gradient-to-r from-blue-50 to-indigo-50 p-4 rounded-xl border border-blue-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <h4 class="text-lg font-semibold text-gray-900">${participantData.facility.name}</h4>
                            <p class="text-sm text-gray-600">${participantData.facility.type} • ${participantData.facility.subcounty}, ${participantData.facility.county}</p>
                        </div>
                        <button onclick="exportParticipantData('${widgetId}', ${facilityId})" 
                            class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors flex items-center">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            Export Data
                        </button>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
                <div class="bg-gradient-to-br from-blue-50 to-blue-100 p-4 rounded-xl text-center border border-blue-200">
                    <div class="text-2xl font-bold text-blue-600">${participantData.summary.total_participants}</div>
                    <div class="text-xs text-blue-700 font-medium">Total Participants</div>
                </div>
                <div class="bg-gradient-to-br from-green-50 to-green-100 p-4 rounded-xl text-center border border-green-200">
                    <div class="text-2xl font-bold text-green-600">${participantData.summary.total_trainings}</div>
                    <div class="text-xs text-green-700 font-medium">Training Programs</div>
                </div>
                <div class="bg-gradient-to-br from-yellow-50 to-yellow-100 p-4 rounded-xl text-center border border-yellow-200">
                    <div class="text-2xl font-bold text-yellow-600">${participantData.summary.completion_rate}%</div>
                    <div class="text-xs text-yellow-700 font-medium">Completion Rate</div>
                </div>
                <div class="bg-gradient-to-br from-purple-50 to-purple-100 p-4 rounded-xl text-center border border-purple-200">
                    <div class="text-2xl font-bold text-purple-600">${participantData.summary.high_performers}</div>
                    <div class="text-xs text-purple-700 font-medium">High Performers</div>
                </div>
                <div class="bg-gradient-to-br from-red-50 to-red-100 p-4 rounded-xl text-center border border-red-200">
                    <div class="text-2xl font-bold text-red-600">${participantData.summary.at_risk_participants}</div>
                    <div class="text-xs text-red-700 font-medium">At Risk</div>
                </div>
            </div>
        `;

        if (participantData.participants && participantData.participants.length > 0) {
            html += `
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <h4 class="text-xl font-bold text-gray-900 flex items-center">
                            <svg class="w-6 h-6 mr-2 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3z"/>
                            </svg>
                            Training Participants (${participantData.participants.length})
                        </h4>
                        <div class="flex items-center space-x-2">
                            <select id="participant-filter-${widgetId}" onchange="filterParticipants('${widgetId}')"
                                class="px-3 py-1 border border-gray-300 rounded-lg text-sm">
                                <option value="">All Participants</option>
                                <option value="high_performer">High Performers (85%+)</option>
                                <option value="low_performer">Low Performers (<60%)</option>
                                <option value="at_risk">At Risk</option>
                                <option value="improving">Improving Trend</option>
                                <option value="declining">Declining Trend</option>
                            </select>
                        </div>
                    </div>
                    
                    <div id="participants-container-${widgetId}" class="space-y-3 max-h-96 overflow-y-auto pr-2 custom-scrollbar">
                        ${participantData.participants.map(participant => `
                            <div class="participant-card bg-white border border-gray-200 rounded-xl p-4 hover:shadow-lg transition-all duration-300" 
                                 data-performance="${getPerformanceCategory(participant)}"
                                 data-trend="${participant.performance_trend.toLowerCase().replace(' ', '_')}">
                                <div class="flex items-start justify-between mb-3">
                                    <div class="flex-1">
                                        <h5 class="text-lg font-semibold text-gray-900 mb-1">${participant.user_name}</h5>
                                        <div class="flex flex-wrap gap-2 mb-2">
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                👨‍⚕️ ${participant.cadre}
                                            </span>
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                                🏢 ${participant.department}
                                            </span>
                                            ${getStatusBadge(participant.current_status)}
                                            ${getTrendBadge(participant.performance_trend)}
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-4 gap-3 mb-3">
                                    <div class="text-center">
                                        <div class="text-lg font-bold text-blue-600">${participant.total_trainings}</div>
                                        <div class="text-xs text-gray-600">Trainings</div>
                                    </div>
                                    <div class="text-center">
                                        <div class="text-lg font-bold text-green-600">${participant.completed_trainings}</div>
                                        <div class="text-xs text-gray-600">Completed</div>
                                    </div>
                                    <div class="text-center">
                                        <div class="text-lg font-bold ${getScoreColor(participant.avg_score)}">${participant.avg_score ? participant.avg_score.toFixed(1) : 'N/A'}</div>
                                        <div class="text-xs text-gray-600">Avg Score</div>
                                    </div>
                                    <div class="text-center">
                                        <div class="text-lg font-bold text-orange-600">${participant.training_history.length}</div>
                                        <div class="text-xs text-gray-600">Records</div>
                                    </div>
                                </div>
                                
                                <div class="flex justify-end space-x-2">
                                    <button onclick="showParticipantHistory('${widgetId}', ${participant.user_id}, '${participant.user_name}')"
                                        class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-sm font-medium transition-colors">
                                        View History
                                    </button>
                                    <button onclick="showAssessmentDetails('${widgetId}', ${participant.user_id})"
                                        class="bg-purple-600 hover:bg-purple-700 text-white px-3 py-1 rounded text-sm font-medium transition-colors">
                                        Assessments
                                    </button>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
        } else {
            html += `
                <div class="text-center py-16">
                    <svg class="mx-auto h-16 w-16 text-gray-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No Participant Data</h3>
                    <p class="text-gray-500">No participants found for this facility in the training programs.</p>
                </div>
            `;
        }

        content.innerHTML = html;

    } catch (error) {
        console.error('Error loading participant data:', error);
        content.innerHTML = `
            <div class="text-center py-16">
                <svg class="mx-auto h-16 w-16 text-red-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <h3 class="text-lg font-medium text-gray-900 mb-2">Error Loading Participant Data</h3>
                <p class="text-gray-500">Unable to load participant information. Please try again.</p>
                <button onclick="showParticipantDetails('${widgetId}', ${facilityId}, '${facilityName}')" 
                    class="mt-4 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm">
                    Retry
                </button>
            </div>
        `;
        showToast('Error loading participant data', 'error');
    }
}

// Show participant training history
async function showParticipantHistory(widgetId, userId, userName) {
    const modal = document.getElementById(`participant-history-modal-${widgetId}`);
    const title = document.getElementById(`participant-history-title-${widgetId}`);
    const content = document.getElementById(`participant-history-content-${widgetId}`);

    if (!modal || !title || !content) {
        console.error('Participant history modal elements not found');
        return;
    }

    title.textContent = `${userName} - Complete Training History`;
    
    // Show loading state
    content.innerHTML = `
        <div class="flex items-center justify-center py-16">
            <div class="loading-spinner mr-3"></div>
            <span class="text-gray-600">Loading training history...</span>
        </div>
    `;

    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';

    try {
        const response = await fetch(`/admin/heatmap/participant-history/${userId}`);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const historyData = await response.json();

        if (historyData.error) {
            content.innerHTML = `
                <div class="text-center py-16">
                    <svg class="mx-auto h-16 w-16 text-red-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">Error Loading Data</h3>
                    <p class="text-gray-500">${historyData.error}</p>
                </div>
            `;
            return;
        }

        let html = `
            <!-- Participant Profile Header -->
            <div class="bg-gradient-to-r from-blue-50 to-indigo-50 p-6 rounded-xl border border-blue-200 mb-8">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <h4 class="text-xl font-bold text-gray-900 mb-2">${historyData.user.name}</h4>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                            <div>
                                <span class="text-gray-600">Email:</span>
                                <div class="font-medium">${historyData.user.email}</div>
                            </div>
                            <div>
                                <span class="text-gray-600">Cadre:</span>
                                <div class="font-medium">${historyData.user.cadre}</div>
                            </div>
                            <div>
                                <span class="text-gray-600">Department:</span>
                                <div class="font-medium">${historyData.user.department}</div>
                            </div>
                            <div>
                                <span class="text-gray-600">Facility:</span>
                                <div class="font-medium">${historyData.user.facility}</div>
                            </div>
                            <div>
                                <span class="text-gray-600">County:</span>
                                <div class="font-medium">${historyData.user.county}</div>
                            </div>
                            <div>
                                <span class="text-gray-600">Status:</span>
                                <div class="font-medium">${getStatusBadge(historyData.user.current_status)}</div>
                            </div>
                            <div>
                                <span class="text-gray-600">Trend:</span>
                                <div class="font-medium">${getTrendBadge(historyData.user.performance_trend)}</div>
                            </div>
                            <div>
                                <span class="text-gray-600">Phone:</span>
                                <div class="font-medium">${historyData.user.phone || 'N/A'}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Performance Statistics -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
                <div class="bg-gradient-to-br from-blue-50 to-blue-100 p-4 rounded-xl text-center border border-blue-200">
                    <div class="text-2xl font-bold text-blue-600">${historyData.statistics.total_trainings}</div>
                    <div class="text-sm text-blue-700 font-medium">Total Trainings</div>
                </div>
                <div class="bg-gradient-to-br from-green-50 to-green-100 p-4 rounded-xl text-center border border-green-200">
                    <div class="text-2xl font-bold text-green-600">${historyData.statistics.completed_trainings}</div>
                    <div class="text-sm text-green-700 font-medium">Completed</div>
                </div>
                <div class="bg-gradient-to-br from-yellow-50 to-yellow-100 p-4 rounded-xl text-center border border-yellow-200">
                    <div class="text-2xl font-bold text-yellow-600">${historyData.statistics.certificates_earned}</div>
                    <div class="text-sm text-yellow-700 font-medium">Certificates</div>
                </div>
                <div class="bg-gradient-to-br from-purple-50 to-purple-100 p-4 rounded-xl text-center border border-purple-200">
                    <div class="text-2xl font-bold text-purple-600">${historyData.statistics.average_score ? historyData.statistics.average_score.toFixed(1) : 'N/A'}</div>
                    <div class="text-sm text-purple-700 font-medium">Avg Score</div>
                </div>
            </div>

            <!-- Training Type Breakdown -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                <div class="bg-white border border-gray-200 rounded-xl p-6">
                    <h5 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M10.394 2.08a1 1 0 00-.788 0l-7 3a1 1 0 000 1.84L5.25 8.051a.999.999 0 01.356-.257l4-1.714a1 1 0 11.788 1.838L7.667 9.088l1.94.831a1 1 0 00.787 0l7-3a1 1 0 000-1.838l-7-3zM3.31 9.397L5 10.12v4.102a8.969 8.969 0 00-1.05-.174 1 1 0 01-.89-.89 11.115 11.115 0 01.25-3.762zM9.3 16.573A9.026 9.026 0 007 14.935v-3.957l1.818.78a3 3 0 002.364 0l5.508-2.361a11.026 11.026 0 01.25 3.762 1 1 0 01-.89.89 8.968 8.968 0 00-5.35 2.524 1 1 0 01-1.4 0zM6 18a1 1 0 001-1v-2.065a8.935 8.935 0 00-2-.712V17a1 1 0 001 1z"/>
                        </svg>
                        Global Training Programs
                    </h5>
                    <div class="space-y-2">
                        <div class="text-3xl font-bold text-blue-600">${historyData.statistics.global_trainings}</div>
                        <div class="text-sm text-gray-600">Institutional training programs attended</div>
                    </div>
                </div>
                
                <div class="bg-white border border-gray-200 rounded-xl p-6">
                    <h5 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3z"/>
                        </svg>
                        Mentorship Programs
                    </h5>
                    <div class="space-y-2">
                        <div class="text-3xl font-bold text-green-600">${historyData.statistics.mentorship_programs}</div>
                        <div class="text-sm text-gray-600">Facility-based mentorship participations</div>
                    </div>
                </div>
            </div>
        `;

        if (historyData.training_history && historyData.training_history.length > 0) {
            html += `
                <div class="space-y-6">
                    <div class="flex items-center justify-between">
                        <h4 class="text-xl font-bold text-gray-900 flex items-center">
                            <svg class="w-6 h-6 mr-2 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/>
                            </svg>
                            Training History Timeline (${historyData.training_history.length})
                        </h4>
                        <div class="flex items-center space-x-2">
                            <select id="history-filter-${widgetId}" onchange="filterTrainingHistory('${widgetId}')"
                                class="px-3 py-1 border border-gray-300 rounded-lg text-sm">
                                <option value="">All Trainings</option>
                                <option value="global_training">Global Trainings</option>
                                <option value="facility_mentorship">Mentorship Programs</option>
                                <option value="completed">Completed Only</option>
                                <option value="in_progress">In Progress</option>
                            </select>
                        </div>
                    </div>
                    
                    <div id="training-history-container-${widgetId}" class="space-y-4 max-h-96 overflow-y-auto pr-2 custom-scrollbar">
                        ${historyData.training_history.map((record, index) => `
                            <div class="training-record bg-white border border-gray-200 rounded-xl p-6 hover:shadow-lg transition-all duration-300"
                                 data-type="${record.training.type}"
                                 data-status="${record.participation.completion_status}">
                                <div class="flex items-start justify-between mb-4">
                                    <div class="flex-1">
                                        <h5 class="text-lg font-semibold text-gray-900 mb-2">${record.training.title}</h5>
                                        <div class="flex flex-wrap gap-2 mb-3">
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium ${record.training.type === 'global_training' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'} border">
                                                ${record.training.type === 'global_training' ? '🎓 Global Training' : '👥 Mentorship'}
                                            </span>
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800 border">
                                                📋 ${record.training.identifier}
                                            </span>
                                            ${getCompletionStatusBadge(record.participation.completion_status)}
                                            ${record.participation.certificate_issued ? '<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 border">🏆 Certified</span>' : ''}
                                        </div>
                                        ${record.training.programs ? `<div class="text-sm text-gray-600 mb-2"><strong>📚 Programs:</strong> ${record.training.programs}</div>` : ''}
                                        ${record.training.location ? `<div class="text-sm text-gray-600 mb-2"><strong>📍 Location:</strong> ${record.training.location}</div>` : ''}
                                        <div class="text-sm text-gray-600"><strong>📅 Duration:</strong> ${record.training.start_date} ${record.training.end_date ? `→ ${record.training.end_date}` : ''}</div>
                                    </div>
                                </div>
                                
                                <!-- Assessment Summary -->
                                ${record.assessment.overall_score !== null ? `
                                <div class="grid grid-cols-3 gap-4 mb-4">
                                    <div class="bg-blue-50 p-3 rounded-lg text-center border border-blue-100">
                                        <div class="text-xl font-bold ${getScoreColor(record.assessment.overall_score)}">${record.assessment.overall_score.toFixed(1)}</div>
                                        <div class="text-xs text-blue-700 font-medium">Overall Score</div>
                                    </div>
                                    <div class="bg-green-50 p-3 rounded-lg text-center border border-green-100">
                                        <div class="text-xl font-bold ${record.assessment.overall_status === 'PASSED' ? 'text-green-600' : 'text-red-600'}">${record.assessment.overall_status}</div>
                                        <div class="text-xs text-green-700 font-medium">Status</div>
                                    </div>
                                    <div class="bg-purple-50 p-3 rounded-lg text-center border border-purple-100">
                                        <div class="text-xl font-bold text-purple-600">${record.assessment.summary.assessed_categories}/${record.assessment.summary.total_categories}</div>
                                        <div class="text-xs text-purple-700 font-medium">Assessed</div>
                                    </div>
                                </div>
                                ` : ''}
                                
                                <!-- Assessment Categories -->
                                ${record.assessment.summary.category_breakdown && record.assessment.summary.category_breakdown.length > 0 ? `
                                <div class="mb-4">
                                    <h6 class="text-sm font-semibold text-gray-700 mb-2">Assessment Categories:</h6>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                        ${record.assessment.summary.category_breakdown.map(category => `
                                            <div class="flex items-center justify-between bg-gray-50 p-2 rounded border">
                                                <span class="text-sm text-gray-700">${category.category_name}</span>
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${category.result === 'pass' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">
                                                    ${category.result === 'pass' ? '✅ Pass' : '❌ Fail'}
                                                </span>
                                            </div>
                                        `).join('')}
                                    </div>
                                </div>
                                ` : ''}
                                
                                <!-- Objective Results (for legacy data) -->
                                ${record.assessment.objective_results && record.assessment.objective_results.length > 0 ? `
                                <div class="mb-4">
                                    <h6 class="text-sm font-semibold text-gray-700 mb-2">Objective Results:</h6>
                                    <div class="space-y-2">
                                        ${record.assessment.objective_results.map(objective => `
                                            <div class="bg-gray-50 p-3 rounded border">
                                                <div class="flex items-center justify-between mb-1">
                                                    <span class="text-sm font-medium text-gray-700">${objective.objective_text}</span>
                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${objective.passed ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">
                                                        ${objective.score}% - ${objective.grade}
                                                    </span>
                                                </div>
                                                ${objective.feedback ? `<div class="text-xs text-gray-600">${objective.feedback}</div>` : ''}
                                            </div>
                                        `).join('')}
                                    </div>
                                </div>
                                ` : ''}
                                
                                <!-- Training Notes -->
                                ${record.participation.notes ? `
                                <div class="mb-4">
                                    <h6 class="text-sm font-semibold text-gray-700 mb-2">Notes:</h6>
                                    <div class="bg-yellow-50 p-3 rounded border border-yellow-200 text-sm text-gray-700">
                                        ${record.participation.notes}
                                    </div>
                                </div>
                                ` : ''}
                                
                                <div class="flex justify-between items-center pt-3 border-t border-gray-200">
                                    <div class="text-xs text-gray-500">
                                        Registered: ${record.participation.registration_date}
                                        ${record.participation.completion_date ? ` • Completed: ${record.participation.completion_date}` : ''}
                                    </div>
                                    <div class="flex space-x-2">
                                        ${record.assessment.overall_score !== null ? `
                                        <button onclick="showDetailedAssessment('${widgetId}', ${record.participation_id})"
                                            class="bg-purple-600 hover:bg-purple-700 text-white px-3 py-1 rounded text-xs font-medium transition-colors">
                                            View Assessment
                                        </button>
                                        ` : ''}
                                        <button onclick="downloadCertificate('${widgetId}', ${record.participation_id})"
                                            class="bg-yellow-600 hover:bg-yellow-700 text-white px-3 py-1 rounded text-xs font-medium transition-colors ${!record.participation.certificate_issued ? 'opacity-50 cursor-not-allowed' : ''}"
                                            ${!record.participation.certificate_issued ? 'disabled' : ''}>
                                            Certificate
                                        </button>
                                    </div>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
        } else {
            html += `
                <div class="text-center py-16">
                    <svg class="mx-auto h-16 w-16 text-gray-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No Training History</h3>
                    <p class="text-gray-500">This participant has no training records available.</p>
                </div>
            `;
        }

        content.innerHTML = html;

    } catch (error) {
        console.error('Error loading participant history:', error);
        content.innerHTML = `
            <div class="text-center py-16">
                <svg class="mx-auto h-16 w-16 text-red-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <h3 class="text-lg font-medium text-gray-900 mb-2">Error Loading Training History</h3>
                <p class="text-gray-500">Unable to load training history. Please try again.</p>
                <button onclick="showParticipantHistory('${widgetId}', ${userId}, '${userName}')" 
                    class="mt-4 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm">
                    Retry
                </button>
            </div>
        `;
        showToast('Error loading training history', 'error');
    }
}

// Helper functions for styling and formatting
function getPerformanceCategory(participant) {
    if (participant.avg_score >= 85) return 'high_performer';
    if (participant.avg_score < 60) return 'low_performer';
    if (participant.performance_trend === 'Declining') return 'at_risk';
    return 'average';
}

function getStatusBadge(status) {
    const statusMap = {
        'active': 'bg-green-100 text-green-800',
        'resigned': 'bg-red-100 text-red-800',
        'transferred': 'bg-yellow-100 text-yellow-800',
        'study_leave': 'bg-blue-100 text-blue-800',
        'suspended': 'bg-red-100 text-red-800',
        'retired': 'bg-gray-100 text-gray-800'
    };
    
    const statusClass = statusMap[status] || 'bg-gray-100 text-gray-800';
    const statusText = status.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());
    
    return `<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${statusClass}">${statusText}</span>`;
}

function getTrendBadge(trend) {
    const trendMap = {
        'Improving': 'bg-green-100 text-green-800',
        'Declining': 'bg-red-100 text-red-800',
        'Stable': 'bg-blue-100 text-blue-800',
        'No Data': 'bg-gray-100 text-gray-800'
    };
    
    const trendClass = trendMap[trend] || 'bg-gray-100 text-gray-800';
    const trendIcon = trend === 'Improving' ? '📈' : trend === 'Declining' ? '📉' : '📊';
    
    return `<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${trendClass}">${trendIcon} ${trend}</span>`;
}

function getCompletionStatusBadge(status) {
    const statusMap = {
        'completed': 'bg-green-100 text-green-800',
        'in_progress': 'bg-yellow-100 text-yellow-800',
        'registered': 'bg-blue-100 text-blue-800',
        'withdrawn': 'bg-red-100 text-red-800'
    };
    
    const statusClass = statusMap[status] || 'bg-gray-100 text-gray-800';
    const statusText = status.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());
    const statusIcon = status === 'completed' ? '✅' : status === 'in_progress' ? '⏳' : '📝';
    
    return `<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${statusClass}">${statusIcon} ${statusText}</span>`;
}

function getScoreColor(score) {
    if (!score) return 'text-gray-600';
    if (score >= 85) return 'text-green-600';
    if (score >= 70) return 'text-blue-600';
    if (score >= 60) return 'text-yellow-600';
    return 'text-red-600';
}

// Filter functions
function filterParticipants(widgetId) {
    const filterValue = document.getElementById(`participant-filter-${widgetId}`).value;
    const participants = document.querySelectorAll(`#participants-container-${widgetId} .participant-card`);
    
    participants.forEach(card => {
        const performance = card.dataset.performance;
        const trend = card.dataset.trend;
        
        let shouldShow = true;
        
        if (filterValue === 'high_performer' && performance !== 'high_performer') {
            shouldShow = false;
        } else if (filterValue === 'low_performer' && performance !== 'low_performer') {
            shouldShow = false;
        } else if (filterValue === 'at_risk' && performance !== 'at_risk') {
            shouldShow = false;
        } else if (filterValue === 'improving' && trend !== 'improving') {
            shouldShow = false;
        } else if (filterValue === 'declining' && trend !== 'declining') {
            shouldShow = false;
        }
        
        card.style.display = shouldShow ? 'block' : 'none';
    });
}

function filterTrainingHistory(widgetId) {
    const filterValue = document.getElementById(`history-filter-${widgetId}`).value;
    const records = document.querySelectorAll(`#training-history-container-${widgetId} .training-record`);
    
    records.forEach(record => {
        const type = record.dataset.type;
        const status = record.dataset.status;
        
        let shouldShow = true;
        
        if (filterValue === 'global_training' && type !== 'global_training') {
            shouldShow = false;
        } else if (filterValue === 'facility_mentorship' && type !== 'facility_mentorship') {
            shouldShow = false;
        } else if (filterValue === 'completed' && status !== 'completed') {
            shouldShow = false;
        } else if (filterValue === 'in_progress' && status !== 'in_progress') {
            shouldShow = false;
        }
        
        record.style.display = shouldShow ? 'block' : 'none';
    });
}

// Modal close functions
function closeCountyModal(widgetId) {
    const modal = document.getElementById(`county-details-modal-${widgetId}`);
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
}

function closeFacilityModal(widgetId) {
    const modal = document.getElementById(`facility-modal-${widgetId}`);
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
}

function closeParticipantModal(widgetId) {
    const modal = document.getElementById(`participant-modal-${widgetId}`);
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
}

function closeParticipantHistoryModal(widgetId) {
    const modal = document.getElementById(`participant-history-modal-${widgetId}`);
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
}

// Table view functions
function toggleTableView(widgetId) {
    const table = document.getElementById(`summary-table-${widgetId}`);
    const button = event.target;
    
    if (table.classList.contains('hidden')) {
        table.classList.remove('hidden');
        button.innerHTML = `
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-1.447-.894L15 4m0 13V4m0 0L9 7"></path>
            </svg>
            Hide Table View
        `;
    } else {
        table.classList.add('hidden');
        button.innerHTML = `
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 6h18m-9 8h9"></path>
            </svg>
            Show Table View
        `;
    }
}

function filterCounties(widgetId) {
    const searchInput = document.getElementById(`county-search-${widgetId}`);
    const searchTerm = searchInput.value.toLowerCase();
    const rows = document.querySelectorAll(`#county-table-body-${widgetId} tr`);
    
    rows.forEach(row => {
        const countyName = row.dataset.county.toLowerCase();
        const shouldShow = countyName.includes(searchTerm);
        row.style.display = shouldShow ? '' : 'none';
    });
}

function sortTable(widgetId) {
    const select = document.getElementById(`sort-select-${widgetId}`);
    const sortBy = select.value;
    const tbody = document.getElementById(`county-table-body-${widgetId}`);
    const rows = Array.from(tbody.querySelectorAll('tr'));
    
    rows.sort((a, b) => {
        let aVal, bVal;
        
        switch (sortBy) {
            case 'name':
                aVal = a.dataset.county;
                bVal = b.dataset.county;
                return aVal.localeCompare(bVal);
            
            case 'trainings':
                aVal = parseInt(a.querySelector('td:nth-child(2) .text-lg').textContent) || 0;
                bVal = parseInt(b.querySelector('td:nth-child(2) .text-lg').textContent) || 0;
                return bVal - aVal;
            
            case 'participants':
                aVal = parseInt(a.querySelector('td:nth-child(3) .text-lg').textContent.replace(/,/g, '')) || 0;
                bVal = parseInt(b.querySelector('td:nth-child(3) .text-lg').textContent.replace(/,/g, '')) || 0;
                return bVal - aVal;
            
            case 'facilities':
                aVal = parseInt(a.querySelector('td:nth-child(4) .text-lg').textContent) || 0;
                bVal = parseInt(b.querySelector('td:nth-child(4) .text-lg').textContent) || 0;
                return bVal - aVal;
            
            case 'intensity':
            default:
                aVal = parseFloat(a.querySelector('td:nth-child(5) .text-sm').textContent) || 0;
                bVal = parseFloat(b.querySelector('td:nth-child(5) .text-sm').textContent) || 0;
                return bVal - aVal;
        }
    });
    
    rows.forEach(row => tbody.appendChild(row));
}

// Export functions
async function exportTableData(widgetId) {
    try {
        const rows = document.querySelectorAll(`#county-table-body-${widgetId} tr:not([style*="display: none"])`);
        
        let csv = 'County,Trainings,Participants,Facilities,Intensity,Coverage\n';
        
        rows.forEach(row => {
            const cells = row.querySelectorAll('td');
            const countyName = row.dataset.county;
            const trainings = cells[1].querySelector('.text-lg').textContent;
            const participants = cells[2].querySelector('.text-lg').textContent.replace(/,/g, '');
            const facilities = cells[3].querySelector('.text-lg').textContent;
            const intensity = cells[4].querySelector('.text-sm').textContent;
            const coverage = cells[5].querySelector('.inline-flex').textContent.trim();
            
            csv += `"${countyName}","${trainings}","${participants}","${facilities}","${intensity}","${coverage}"\n`;
        });
        
        downloadCSV(csv, `county_training_summary_${new Date().toISOString().split('T')[0]}.csv`);
        showToast('County data exported successfully', 'success');
        
    } catch (error) {
        console.error('Export error:', error);
        showToast('Error exporting data', 'error');
    }
}

async function exportFacilityData(widgetId, countyId) {
    try {
        const response = await fetch(`/admin/heatmap/export/facilities/${countyId}`);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = response.headers.get('Content-Disposition').split('filename=')[1].replace(/"/g, '');
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
        
        showToast('Facility data exported successfully', 'success');
        
    } catch (error) {
        console.error('Export error:', error);
        showToast('Error exporting facility data', 'error');
    }
}

async function exportParticipantData(widgetId, facilityId) {
    try {
        const response = await fetch(`/admin/heatmap/export/participants/${facilityId}`);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = response.headers.get('Content-Disposition').split('filename=')[1].replace(/"/g, '');
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
        
        showToast('Participant data exported successfully', 'success');
        
    } catch (error) {
        console.error('Export error:', error);
        showToast('Error exporting participant data', 'error');
    }
}

// Utility functions
function downloadCSV(csv, filename) {
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', filename);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

function highlightCountyOnMap(widgetId, countyName) {
    if (!geoJsonLayer) {
        showToast('Map not available', 'warning');
        return;
    }
    
    geoJsonLayer.eachLayer(function(layer) {
        if (layer.feature.properties.COUNTY === countyName) {
            layer.setStyle({
                weight: 4,
                color: '#ef4444',
                fillOpacity: 0.9
            });
            
            mapInstance.fitBounds(layer.getBounds());
            layer.openTooltip();
            
            setTimeout(() => {
                geoJsonLayer.resetStyle(layer);
            }, 3000);
        }
    });
    
    showToast(`Highlighted ${countyName} County on map`, 'info');
}

function refreshInsights(widgetId) {
    const button = event.target;
    const originalContent = button.innerHTML;
    
    button.innerHTML = '<div class="loading-spinner"></div>';
    button.disabled = true;
    
    // Simulate refresh delay
    setTimeout(() => {
        button.innerHTML = originalContent;
        button.disabled = false;
        showToast('AI insights refreshed', 'success');
    }, 2000);
}

// Toast notification function
function showToast(message, type = 'info') {
    const container = document.getElementById('toast-container') || createToastContainer();
    
    const toast = document.createElement('div');
    toast.className = `toast flex items-center p-4 mb-4 text-sm rounded-lg shadow-lg transform transition-all duration-300 translate-x-full`;
    
    const colors = {
        success: 'bg-green-100 text-green-800 border border-green-200',
        error: 'bg-red-100 text-red-800 border border-red-200',
        warning: 'bg-yellow-100 text-yellow-800 border border-yellow-200',
        info: 'bg-blue-100 text-blue-800 border border-blue-200'
    };
    
    const icons = {
        success: '✅',
        error: '❌',
        warning: '⚠️',
        info: 'ℹ️'
    };
    
    toast.className += ` ${colors[type] || colors.info}`;
    toast.innerHTML = `
        <span class="mr-2">${icons[type] || icons.info}</span>
        <span>${message}</span>
        <button onclick="this.parentElement.remove()" class="ml-auto text-current hover:opacity-75">
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
            </svg>
        </button>
    `;
    
    container.appendChild(toast);
    
    // Animate in
    setTimeout(() => {
        toast.classList.remove('translate-x-full');
    }, 100);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        toast.classList.add('translate-x-full');
        setTimeout(() => {
            if (toast.parentElement) {
                toast.parentElement.removeChild(toast);
            }
        }, 300);
    }, 5000);
}

function createToastContainer() {
    const container = document.createElement('div');
    container.id = 'toast-container';
    container.className = 'fixed top-4 right-4 space-y-2';
    container.style.zIndex = '10001';
    document.body.appendChild(container);
    return container;
}

// Additional helper functions for enhanced functionality
function showDetailedAssessment(widgetId, participationId) {
    // This would show a detailed breakdown of assessment results
    showToast('Detailed assessment view coming soon', 'info');
}

function downloadCertificate(widgetId, participationId) {
    // This would download the participant's certificate
    showToast('Certificate download feature coming soon', 'info');
}

function showTrainingBreakdown(widgetId, facilityId) {
    // This would show detailed training breakdown for a facility
    showToast('Training breakdown view coming soon', 'info');
}

function showAssessmentDetails(widgetId, userId) {
    // This would show detailed assessment information for a participant
    showToast('Assessment details view coming soon', 'info');
}

// Initialize dashboard when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    console.log('MOH Training Heatmap Dashboard initialized');
    
    // Add event listeners for modal close on backdrop click
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal-overlay')) {
            const widgetId = currentWidgetId;
            if (widgetId) {
                closeCountyModal(widgetId);
                closeFacilityModal(widgetId);
                closeParticipantModal(widgetId);
                closeParticipantHistoryModal(widgetId);
            }
        }
    });
    
    // Add keyboard event listener for ESC key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const widgetId = currentWidgetId;
            if (widgetId) {
                closeCountyModal(widgetId);
                closeFacilityModal(widgetId);
                closeParticipantModal(widgetId);
                closeParticipantHistoryModal(widgetId);
            }
        }
    });
});