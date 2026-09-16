<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Kenya Training Coverage Dashboard - Interactive heatmap showing global training participation across counties">
    <title>Kenya Training Coverage Dashboard</title>
    
    <!-- External CSS Dependencies -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Custom Heatmap Styles - External CSS File -->
    <link rel="stylesheet" href="{{asset('css/map.css')}}">
    
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23059669'><path d='M2 10a8 8 0 018-8v8h8a8 8 0 11-16 0z'/></svg>">
</head>
<body class="bg-gray-50">
    @php 
    $widgetId = $widgetId ?? ($widget->getId() ?? 'kenya-heatmap-' . uniqid()); 
    $mapData = $widget->getMapData();
    $aiInsights = $widget->getAIInsights();
    @endphp

    <!-- Main Container -->
    <div class="min-h-screen py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <!-- Page Header -->
            <div class="mb-8">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900 gradient-text">
                            Kenya Training Coverage Dashboard
                        </h1>
                        <p class="mt-2 text-gray-600">
                            Comprehensive analytics of global training participation across all 47 counties
                        </p>
                    </div>
                    <div class="flex items-center space-x-3">
                        <span class="text-sm text-gray-500">Last updated:</span>
                        <span class="text-sm font-medium text-gray-900">{{ now()->format('M j, Y g:i A') }}</span>
                    </div>
                </div>
            </div>

            <!-- Main Dashboard Widget -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                
                <!-- Header with Summary Stats -->
                <div class="p-6 border-b border-gray-200 bg-gradient-to-r from-blue-50 to-indigo-50">
                    <div class="flex items-center justify-between flex-wrap gap-4">
                        <div>
                            <h2 class="text-2xl font-bold text-gray-900 flex items-center">
                                <svg class="w-8 h-8 mr-3 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M2 10a8 8 0 018-8v8h8a8 8 0 11-16 0z"/>
                                    <path d="M12 2.252A8.014 8.014 0 0117.748 8H12V2.252z"/>
                                </svg>
                                Interactive Training Heatmap
                            </h2>
                            <p class="text-sm text-gray-600 mt-1">Click on counties for detailed global training breakdown</p>
                        </div>
                        
                        <!-- Summary Stats -->
                        <div class="flex space-x-4 text-sm flex-wrap gap-4">
                            <div class="text-center stat-card bg-white p-4 rounded-xl shadow-sm border">
                                <div class="text-3xl font-bold text-blue-600">{{ $mapData['totalTrainings'] }}</div>
                                <div class="text-gray-600 text-sm">Global Trainings</div>
                            </div>
                            <div class="text-center stat-card bg-white p-4 rounded-xl shadow-sm border">
                                <div class="text-3xl font-bold text-green-600">{{ number_format($mapData['totalParticipants']) }}</div>
                                <div class="text-gray-600 text-sm">Total Participants</div>
                            </div>
                            <div class="text-center stat-card bg-white p-4 rounded-xl shadow-sm border">
                                <div class="text-3xl font-bold text-purple-600">{{ $mapData['totalFacilities'] }}</div>
                                <div class="text-gray-600 text-sm">Active Facilities</div>
                            </div>
                            <div class="text-center stat-card bg-white p-4 rounded-xl shadow-sm border">
                                <div class="text-3xl font-bold text-orange-600">{{ $mapData['summary']['counties_with_training'] }}</div>
                                <div class="text-gray-600 text-sm">Counties Covered</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- AI Insights Section -->
                <div class="p-6 border-b border-gray-200">
                    <div class="insight-card rounded-xl p-6 text-white">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-xl font-semibold flex items-center">
                                <span class="ai-badge text-xs px-3 py-1 rounded-full mr-3">🤖 AI INSIGHTS</span>
                                Training Coverage Analysis
                            </h3>
                            <button onclick="refreshInsights('{{ $widgetId }}')" class="text-white/80 hover:text-white transition-colors p-2 rounded-lg hover:bg-white/10">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                </svg>
                            </button>
                        </div>
                        
                        <div class="grid md:grid-cols-3 gap-4">
                            <div class="glass backdrop-blur rounded-lg p-4">
                                <h4 class="font-semibold mb-2 flex items-center">
                                    🎯 <span class="ml-2">Coverage Assessment</span>
                                </h4>
                                <p class="text-sm text-white/90">{{ $aiInsights['coverage'] ?? 'Training coverage varies significantly across regions. Focus needed on underserved counties.' }}</p>
                            </div>
                            <div class="glass backdrop-blur rounded-lg p-4">
                                <h4 class="font-semibold mb-2 flex items-center">
                                    📊 <span class="ml-2">Participation Trends</span>
                                </h4>
                                <p class="text-sm text-white/90">{{ $aiInsights['participation'] ?? 'Higher facility participation correlates with better training outcomes. Consider hub-based approach.' }}</p>
                            </div>
                            <div class="glass backdrop-blur rounded-lg p-4">
                                <h4 class="font-semibold mb-2 flex items-center">
                                    💡 <span class="ml-2">Recommendations</span>
                                </h4>
                                <p class="text-sm text-white/90">{{ $aiInsights['recommendations'] ?? 'Target low-coverage counties for next training cycle. Leverage high-performing regions as training hubs.' }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Map Section -->
                <div class="p-6">
                    <div id="kenya-heatmap-{{ $widgetId }}" class="relative rounded-lg overflow-hidden shadow-lg border" style="height:500px; background:#f8fafc;">
                        <div id="map-loading-{{ $widgetId }}" class="absolute inset-0 flex items-center justify-center bg-gray-100 z-10">
                            <div class="text-center">
                                <div class="loading-spinner mx-auto mb-2"></div>
                                <div class="text-sm text-gray-600">Loading interactive map...</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Legend and Controls -->
                    <div class="mt-6 bg-gray-50 p-4 rounded-lg">
                        <div class="flex items-center justify-between flex-wrap gap-4">
                            <div class="flex items-center space-x-4 flex-wrap">
                                <span class="text-sm font-semibold text-gray-700">Training Intensity Scale:</span>
                                <div class="flex items-center space-x-2">
                                    <div class="w-4 h-4 rounded border-2 border-gray-400" style="background-color: #e5e7eb;"></div>
                                    <span class="text-xs text-gray-600 font-medium">No Data</span>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <div class="w-4 h-4 rounded" style="background-color: #fca5a5;"></div>
                                    <span class="text-xs text-gray-600">Very Low</span>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <div class="w-4 h-4 rounded" style="background-color: #fbbf24;"></div>
                                    <span class="text-xs text-gray-600">Low</span>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <div class="w-4 h-4 rounded" style="background-color: #a3a3a3;"></div>
                                    <span class="text-xs text-gray-600">Medium</span>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <div class="w-4 h-4 rounded" style="background-color: #84cc16;"></div>
                                    <span class="text-xs text-gray-600">High</span>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <div class="w-4 h-4 rounded" style="background-color: #16a34a;"></div>
                                    <span class="text-xs text-gray-600">Very High</span>
                                </div>
                            </div>
                            
                            <div class="flex items-center space-x-3">
                                @if($mapData['summary']['avg_participants_per_training'] > 0)
                                <div class="text-sm text-gray-600 bg-white px-4 py-2 rounded-full border shadow-sm">
                                    📊 Avg. {{ $mapData['summary']['avg_participants_per_training'] }} participants per training
                                </div>
                                @endif
                                
                                <button onclick="toggleTableView('{{ $widgetId }}')" class="btn-primary text-white px-4 py-2 rounded-lg text-sm flex items-center shadow-sm">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 6h18m-9 8h9"></path>
                                    </svg>
                                    Show Table View
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detailed Summary Table -->
            <div id="summary-table-{{ $widgetId }}" class="hidden bg-white rounded-lg shadow-sm mt-6 border border-gray-200">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex items-center justify-between flex-wrap gap-4">
                        <div>
                            <h3 class="text-xl font-semibold text-gray-900">County Training Summary</h3>
                            <p class="text-sm text-gray-600">Detailed breakdown of training participation by county</p>
                        </div>
                        <div class="flex items-center space-x-3">
                            <div class="relative">
                                <input type="text" id="county-search-{{ $widgetId }}" placeholder="Search counties..." 
                                       onkeyup="filterCounties('{{ $widgetId }}')"
                                       class="pl-10 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-64">
                                <svg class="w-4 h-4 absolute left-3 top-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                </svg>
                            </div>
                            <select id="sort-select-{{ $widgetId }}" onchange="sortTable('{{ $widgetId }}')" 
                                    class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="intensity">Sort by Intensity</option>
                                <option value="name">Sort by Name</option>
                                <option value="trainings">Sort by Trainings</option>
                                <option value="participants">Sort by Participants</option>
                                <option value="facilities">Sort by Facilities</option>
                            </select>
                            <button onclick="exportTableData('{{ $widgetId }}')" class="btn-success text-white px-4 py-2 rounded-lg text-sm flex items-center shadow-sm">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                Export CSV
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="overflow-x-auto custom-scrollbar">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">County</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Trainings</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Participants</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Facilities</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Intensity</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Coverage</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="county-table-body-{{ $widgetId }}" class="bg-white divide-y divide-gray-200">
                            @foreach($mapData['countyData'] as $county)
                            <tr class="county-row" data-county="{{ $county['name'] }}">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        @php
                                            $intensityColor = '#e5e7eb';
                                            if ($county['trainings'] > 0) {
                                                if ($county['intensity'] > 75) $intensityColor = '#16a34a';
                                                elseif ($county['intensity'] > 50) $intensityColor = '#84cc16';
                                                elseif ($county['intensity'] > 25) $intensityColor = '#a3a3a3';
                                                elseif ($county['intensity'] > 10) $intensityColor = '#fbbf24';
                                                else $intensityColor = '#fca5a5';
                                            }
                                        @endphp
                                        <div class="flex-shrink-0 h-10 w-10">
                                            <div class="county-avatar h-10 w-10 rounded-full flex items-center justify-center text-sm font-bold text-white shadow-sm"
                                                 style="background-color: {{ $intensityColor }};">
                                                {{ substr($county['name'], 0, 2) }}
                                            </div>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900">{{ $county['name'] }}</div>
                                            <div class="text-sm text-gray-500">County</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-lg font-semibold text-gray-900">{{ $county['trainings'] }}</div>
                                    <div class="text-sm text-gray-500">Global trainings</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-lg font-semibold text-gray-900">{{ number_format($county['participants']) }}</div>
                                    <div class="text-sm text-gray-500">Total participants</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-lg font-semibold text-gray-900">{{ $county['facilities'] }}</div>
                                    <div class="text-sm text-gray-500">Active facilities</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 w-20 bg-gray-200 rounded-full h-2 mr-3">
                                            <div class="intensity-bar h-2 rounded-full" 
                                                 style="width: {{ min(100, max(5, $county['intensity'])) }}%; background-color: {{ $intensityColor }};"></div>
                                        </div>
                                        <span class="text-sm font-semibold text-gray-900">{{ round($county['intensity']) }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @php
                                        $coverage = $county['trainings'] > 0 ? 
                                            ($county['intensity'] > 75 ? 'Excellent' : 
                                            ($county['intensity'] > 50 ? 'Good' : 
                                            ($county['intensity'] > 25 ? 'Fair' : 
                                            ($county['intensity'] > 10 ? 'Limited' : 'Minimal')))) : 'None';
                                        $badgeClass = $county['trainings'] > 0 ? 
                                            ($county['intensity'] > 75 ? 'badge-excellent' : 
                                            ($county['intensity'] > 50 ? 'badge-good' : 
                                            ($county['intensity'] > 25 ? 'badge-fair' : 
                                            ($county['intensity'] > 10 ? 'badge-limited' : 'badge-limited')))) : 'badge-none';
                                    @endphp
                                    <span class="inline-flex px-3 py-1 text-xs font-semibold rounded-full {{ $badgeClass }}">
                                        {{ $coverage }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                    @if($county['trainings'] > 0)
                                    <button onclick="showCountyDetails('{{ $widgetId }}', '{{ $county['name'] }}', @json($county))" 
                                            class="text-blue-600 hover:text-blue-900 hover:underline">View Details</button>
                                    @endif
                                    <button onclick="highlightCountyOnMap('{{ $widgetId }}', '{{ $county['name'] }}')" 
                                            class="text-green-600 hover:text-green-900 hover:underline">Locate</button>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="p-4 bg-gray-50 border-t border-gray-200">
                    <div class="flex items-center justify-between text-sm text-gray-600">
                        <div>
                            Showing <span class="font-medium">{{ count($mapData['countyData']) }}</span> counties
                        </div>
                        <div class="flex items-center space-x-4">
                            <span>Total Coverage: <span class="font-medium">{{ round(($mapData['summary']['counties_with_training'] / 47) * 100, 1) }}%</span></span>
                            <span>•</span>
                            <span>Active Counties: <span class="font-medium">{{ $mapData['summary']['counties_with_training'] }}/47</span></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- County Details Modal -->
    <div id="county-details-modal-{{ $widgetId }}" class="hidden fixed inset-0 modal-overlay overflow-y-auto h-full w-full">
        <div class="relative top-4 mx-auto p-6 border w-11/12 md:w-4/5 lg:w-3/4 xl:w-2/3 shadow-xl rounded-xl bg-white max-h-screen overflow-y-auto fade-in">
            <div class="flex items-center justify-between mb-6 sticky top-0 bg-white pb-4 border-b z-50">
                <div>
                    <h3 id="county-modal-title-{{ $widgetId }}" class="text-2xl font-bold text-gray-900"></h3>
                    <p class="text-sm text-gray-600 mt-1">Global training participation breakdown</p>
                </div>
                <button onclick="closeCountyModal('{{ $widgetId }}')" class="text-gray-400 hover:text-gray-600 hover:bg-gray-100 p-3 rounded-full transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <div id="county-modal-content-{{ $widgetId }}" class="mt-4">
                <!-- Content will be populated by JavaScript -->
            </div>
        </div>
    </div>

    <!-- Toast Notifications -->
    <div id="toast-container" class="fixed top-4 right-4 space-y-2" style="z-index: 10001;"></div>

    <!-- External JavaScript -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <!-- Inline JavaScript -->
    <script>
        // Global variables
        let mapInstance = null;
        let geoJsonLayer = null;

        // Initialize map
        function initKenyaMap(widgetId, options) {
            const mapContainer = document.getElementById(`kenya-heatmap-${widgetId}`);
            const loadingIndicator = document.getElementById(`map-loading-${widgetId}`);
            
            if (!mapContainer) return;

            try {
                if (mapInstance) mapInstance.remove();

                mapInstance = L.map(mapContainer, {
                    zoomControl: true,
                    attributionControl: true
                }).setView([-0.0236, 37.9062], 6);

                mapInstance.getPane('mapPane').style.zIndex = 1;
                mapInstance.getPane('tilePane').style.zIndex = 1;
                mapInstance.getPane('overlayPane').style.zIndex = 2;

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap contributors',
                    maxZoom: 18
                }).addTo(mapInstance);

                if (options.geojson && options.mapData) {
                    displayCountyData(options.geojson, options.mapData, widgetId);
                }

                if (loadingIndicator) loadingIndicator.style.display = 'none';

            } catch (error) {
                console.error('Map error:', error);
                showToast('Error loading map', 'error');
            }
        }

        // Display county data
        function displayCountyData(geojson, mapData, widgetId) {
            const countyData = mapData.countyData;
            const intensityLevels = mapData.intensityLevels;

            const dataLookup = {};
            countyData.forEach(county => {
                dataLookup[county.name.toLowerCase()] = county;
            });

            function getCountyStyle(feature) {
                const countyName = feature.properties.COUNTY.toLowerCase();
                const county = dataLookup[countyName];
                
                if (!county || county.intensity === 0) {
                    return {
                        fillColor: '#e5e7eb',
                        weight: 1,
                        opacity: 0.8,
                        color: '#9ca3af',
                        fillOpacity: 0.6
                    };
                }

                let fillColor;
                if (county.intensity <= intensityLevels.low * 0.5) {
                    fillColor = '#fca5a5';
                } else if (county.intensity <= intensityLevels.low) {
                    fillColor = '#fbbf24';
                } else if (county.intensity <= intensityLevels.medium) {
                    fillColor = '#a3a3a3';
                } else if (county.intensity <= intensityLevels.high) {
                    fillColor = '#84cc16';
                } else {
                    fillColor = '#16a34a';
                }

                return {
                    fillColor: fillColor,
                    weight: 1,
                    opacity: 1,
                    color: '#374151',
                    fillOpacity: 0.8
                };
            }

            geoJsonLayer = L.geoJSON(geojson, {
                style: getCountyStyle,
                onEachFeature: function(feature, layer) {
                    const countyName = feature.properties.COUNTY;
                    const county = dataLookup[countyName.toLowerCase()];
                    
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

                    layer.bindTooltip(tooltipContent, {
                        permanent: false,
                        direction: 'center',
                        className: 'county-tooltip'
                    });

                    layer.on({
                        mouseover: function(e) {
                            e.target.setStyle({
                                weight: 3,
                                color: '#1f2937',
                                fillOpacity: 0.9
                            });
                            e.target.bringToFront();
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

            mapInstance.fitBounds(geoJsonLayer.getBounds());
        }

        // Show county details modal
        function showCountyDetails(widgetId, countyName, countyData) {
            const modal = document.getElementById(`county-details-modal-${widgetId}`);
            const title = document.getElementById(`county-modal-title-${widgetId}`);
            const content = document.getElementById(`county-modal-content-${widgetId}`);
            
            title.textContent = `${countyName} County Training Details`;
            
            let html = `
                <div class="grid grid-cols-2 md:grid-cols-4 gap-6 mb-8">
                    <div class="bg-gradient-to-br from-blue-50 to-blue-100 p-4 rounded-xl text-center border border-blue-200 stat-card">
                        <div class="text-3xl font-bold text-blue-600">${countyData.trainings}</div>
                        <div class="text-sm text-blue-700 font-medium">Global Trainings</div>
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
            `;
            
            if (countyData.training_details && countyData.training_details.length > 0) {
                html += `
                    <div class="space-y-6">
                        <div class="flex items-center justify-between">
                            <h4 class="text-xl font-bold text-gray-900 flex items-center">
                                <svg class="w-6 h-6 mr-2 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                Global Training Participation
                            </h4>
                            <span class="bg-blue-100 text-blue-800 text-xs font-semibold px-3 py-1 rounded-full">
                                ${countyData.training_details.length} Training${countyData.training_details.length !== 1 ? 's' : ''}
                            </span>
                        </div>
                        
                        <div class="space-y-4 max-h-96 overflow-y-auto pr-2">
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
                        <p class="text-gray-500 mb-4">This county has not participated in any global training programs yet.</p>
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

        // Close modal
        function closeCountyModal(widgetId) {
            const modal = document.getElementById(`county-details-modal-${widgetId}`);
            modal.classList.add('hidden');
            document.body.style.overflow = 'auto';
        }

        // Toggle table view
        function toggleTableView(widgetId) {
            const table = document.getElementById(`summary-table-${widgetId}`);
            const button = event.target.closest('button');
            
            if (table.classList.contains('hidden')) {
                table.classList.remove('hidden');
                button.innerHTML = `
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 11H3m5.745-6.745L3 9.255m7.745-2L9 12.255m8 5.745L21 9.745M15 3l-3.745 6.745M21 3l-8.745 8.745M3 21l6.745-6.745M12 21l6.745-6.745"></path>
                    </svg>
                    Hide Table View
                `;
                table.scrollIntoView({ behavior: 'smooth' });
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

        // Filter counties
        function filterCounties(widgetId) {
            const searchInput = document.getElementById(`county-search-${widgetId}`);
            const tableBody = document.getElementById(`county-table-body-${widgetId}`);
            const searchTerm = searchInput.value.toLowerCase();
            
            const rows = tableBody.querySelectorAll('tr');
            let visibleCount = 0;
            
            rows.forEach(row => {
                const countyName = row.getAttribute('data-county').toLowerCase();
                if (countyName.includes(searchTerm)) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });
            
            showToast(`Found ${visibleCount} matching counties`, 'info');
        }

        // Sort table
        function sortTable(widgetId) {
            const select = document.getElementById(`sort-select-${widgetId}`);
            const tableBody = document.getElementById(`county-table-body-${widgetId}`);
            const sortBy = select.value;
            
            const rows = Array.from(tableBody.querySelectorAll('tr'));
            
            rows.sort((a, b) => {
                let aValue, bValue;
                
                switch (sortBy) {
                    case 'name':
                        aValue = a.getAttribute('data-county');
                        bValue = b.getAttribute('data-county');
                        return aValue.localeCompare(bValue);
                        
                    case 'trainings':
                        aValue = parseInt(a.cells[1].querySelector('.text-lg').textContent);
                        bValue = parseInt(b.cells[1].querySelector('.text-lg').textContent);
                        return bValue - aValue;
                        
                    case 'participants':
                        aValue = parseInt(a.cells[2].querySelector('.text-lg').textContent.replace(/,/g, ''));
                        bValue = parseInt(b.cells[2].querySelector('.text-lg').textContent.replace(/,/g, ''));
                        return bValue - aValue;
                        
                    case 'facilities':
                        aValue = parseInt(a.cells[3].querySelector('.text-lg').textContent);
                        bValue = parseInt(b.cells[3].querySelector('.text-lg').textContent);
                        return bValue - aValue;
                        
                    case 'intensity':
                    default:
                        aValue = parseInt(a.cells[4].querySelector('span').textContent);
                        bValue = parseInt(b.cells[4].querySelector('span').textContent);
                        return bValue - aValue;
                }
            });
            
            rows.forEach(row => tableBody.appendChild(row));
            showToast(`Table sorted by ${sortBy}`, 'info');
        }

        // Export table data
        function exportTableData(widgetId) {
            const tableBody = document.getElementById(`county-table-body-${widgetId}`);
            const rows = tableBody.querySelectorAll('tr');
            
            let csvContent = 'County,Trainings,Participants,Facilities,Intensity,Coverage\n';
            
            rows.forEach(row => {
                if (row.style.display !== 'none') {
                    const cells = row.cells;
                    const county = cells[0].querySelector('.text-sm.font-medium').textContent;
                    const trainings = cells[1].querySelector('.text-lg').textContent;
                    const participants = cells[2].querySelector('.text-lg').textContent;
                    const facilities = cells[3].querySelector('.text-lg').textContent;
                    const intensity = cells[4].querySelector('span').textContent;
                    const coverage = cells[5].querySelector('span').textContent;
                    
                    csvContent += `"${county}","${trainings}","${participants}","${facilities}","${intensity}","${coverage}"\n`;
                }
            });
            
            const blob = new Blob([csvContent], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `kenya_training_summary_${new Date().toISOString().split('T')[0]}.csv`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            
            showToast('Data exported successfully!', 'success');
        }

        // Highlight county on map
        function highlightCountyOnMap(widgetId, countyName) {
            if (geoJsonLayer) {
                geoJsonLayer.eachLayer(function(layer) {
                    if (layer.feature.properties.COUNTY.toLowerCase() === countyName.toLowerCase()) {
                        layer.setStyle({
                            weight: 4,
                            color: '#2563eb',
                            fillOpacity: 0.9,
                            fillColor: '#3b82f6'
                        });
                        
                        mapInstance.fitBounds(layer.getBounds(), { padding: [20, 20] });
                        layer.openTooltip();
                        
                        setTimeout(() => {
                            geoJsonLayer.resetStyle(layer);
                        }, 3000);
                    }
                });
            }
            
            showToast(`Located ${countyName} County on map`, 'info');
        }

        // Refresh AI insights
        function refreshInsights(widgetId) {
            showToast('Refreshing AI insights...', 'info');
            setTimeout(() => {
                showToast('AI insights updated!', 'success');
            }, 2000);
        }

        // Show all facilities
        function showAllFacilities(widgetId, trainingIndex) {
            showToast('Expanding facility list...', 'info');
        }

        // Toast notification system
        function showToast(message, type = 'info') {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            
            const colors = {
                info: 'bg-blue-500',
                success: 'bg-green-500',
                error: 'bg-red-500',
                warning: 'bg-yellow-500'
            };
            
            const icons = {
                info: 'ℹ️',
                success: '✅',
                error: '❌', 
                warning: '⚠️'
            };
            
            toast.className = `${colors[type]} text-white px-4 py-3 rounded-lg shadow-lg fade-in flex items-center space-x-2`;
            toast.innerHTML = `<span>${icons[type]}</span><span>${message}</span>`;
            
            container.appendChild(toast);
            
            setTimeout(() => {
                toast.remove();
            }, 4000);
        }

        // Event listeners
        document.addEventListener('click', function(event) {
            const modals = document.querySelectorAll('[id^="county-details-modal-"]');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.classList.add('hidden');
                    document.body.style.overflow = 'auto';
                }
            });
        });

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                const modals = document.querySelectorAll('[id^="county-details-modal-"]');
                modals.forEach(modal => {
                    if (!modal.classList.contains('hidden')) {
                        modal.classList.add('hidden');
                        document.body.style.overflow = 'auto';
                    }
                });
            }
        });

        // Initialize when page loads
        document.addEventListener('DOMContentLoaded', function() {
            const widgetId = '{{ $widgetId }}';
            const mapData = @json($mapData);
            
            const mapContainer = document.getElementById(`kenya-heatmap-${widgetId}`);
            if (mapContainer) {
                mapContainer.style.zIndex = '1';
                mapContainer.style.position = 'relative';
            }
            
            initKenyaMap(widgetId, {
                height: 500,
                mapData: mapData,
                geojson: @json(json_decode(file_get_contents(public_path('kenyan-counties.geojson')), true))
            });
        });

        // Handle Livewire navigation
        document.addEventListener('livewire:navigated', function() {
            const widgetId = '{{ $widgetId }}';
            const mapData = @json($mapData);
            
            initKenyaMap(widgetId, {
                height: 500,
                mapData: mapData,
                geojson: @json(json_decode(file_get_contents(public_path('kenyan-counties.geojson')), true))
            });
        });
    </script>
</body>
</html>