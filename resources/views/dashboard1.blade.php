@extends('layouts.app')

@section('title', 'MOH Trainings Heatmap')

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<style>
/* Enhanced Heatmap Dashboard Styles */

/* Loading Spinner */
.loading-spinner {
    display: inline-block;
    width: 20px;
    height: 20px;
    border: 3px solid #f3f3f3;
    border-top: 3px solid #3498db;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Gradient Text */
.gradient-text {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

/* Stat Cards */
.stat-card {
    transition: all 0.3s ease;
    cursor: pointer;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.15);
}

/* AI Insights Card */
.insight-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    position: relative;
    overflow: hidden;
}

.insight-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.1'%3E%3Ccircle cx='30' cy='30' r='2'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E") repeat;
    opacity: 0.3;
}

.ai-badge {
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.3);
}

.glass {
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
}

/* Modal Styles */
.modal-overlay {
    background-color: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(4px);
    z-index: 1000;
}

.fade-in {
    animation: fadeIn 0.3s ease-in-out;
}

@keyframes fadeIn {
    from { 
        opacity: 0; 
        transform: translateY(20px);
    }
    to { 
        opacity: 1; 
        transform: translateY(0);
    }
}

/* Custom Scrollbar */
.custom-scrollbar {
    scrollbar-width: thin;
    scrollbar-color: #cbd5e0 #f7fafc;
}

.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}

.custom-scrollbar::-webkit-scrollbar-track {
    background: #f7fafc;
    border-radius: 3px;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #cbd5e0;
    border-radius: 3px;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #a0aec0;
}

/* County Tooltip */
.county-tooltip {
    background: rgba(0, 0, 0, 0.9);
    color: white;
    padding: 12px 16px;
    border-radius: 8px;
    font-size: 14px;
    line-height: 1.4;
    box-shadow: 0 10px 25px rgba(0,0,0,0.3);
    border: 1px solid rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    max-width: 250px;
}

/* Badge Styles */
.badge-excellent {
    background-color: #dcfce7;
    color: #166534;
    border: 1px solid #bbf7d0;
}

.badge-good {
    background-color: #dbeafe;
    color: #1d4ed8;
    border: 1px solid #bfdbfe;
}

.badge-fair {
    background-color: #fef3c7;
    color: #d97706;
    border: 1px solid #fed7aa;
}

.badge-limited {
    background-color: #fee2e2;
    color: #dc2626;
    border: 1px solid #fecaca;
}

.badge-none {
    background-color: #f3f4f6;
    color: #6b7280;
    border: 1px solid #d1d5db;
}

/* Button Styles */
.btn-primary {
    background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
    transition: all 0.3s ease;
}

.btn-primary:hover {
    background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
    transform: translateY(-1px);
    box-shadow: 0 10px 25px rgba(59, 130, 246, 0.3);
}

.btn-success {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    transition: all 0.3s ease;
}

.btn-success:hover {
    background: linear-gradient(135deg, #059669 0%, #047857 100%);
    transform: translateY(-1px);
    box-shadow: 0 10px 25px rgba(16, 185, 129, 0.3);
}

/* Facility Tag */
.facility-tag {
    transition: all 0.3s ease;
    cursor: pointer;
}

.facility-tag:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
}

/* Responsive Design */
@media (max-width: 768px) {
    .stat-card {
        margin-bottom: 1rem;
    }
    
    .county-tooltip {
        max-width: 200px;
        font-size: 12px;
        padding: 8px 12px;
    }
    
    .facility-tag {
        font-size: 0.75rem;
        padding: 0.5rem 0.75rem;
    }
    
    .modal-overlay > div {
        width: 95%;
        margin: 1rem auto;
        max-height: 90vh;
    }
}

@media (max-width: 640px) {
    .gradient-text {
        font-size: 1.75rem;
    }
    
    .insight-card {
        padding: 1rem;
    }
    
    .glass {
        padding: 0.75rem;
    }
    
    .stat-card {
        padding: 0.75rem;
    }
}

/* Performance Animations */
.county-row {
    transition: background-color 0.2s ease;
}

.county-row:hover {
    background-color: #f9fafb;
}

.county-avatar {
    transition: transform 0.3s ease;
}

.county-row:hover .county-avatar {
    transform: scale(1.1);
}

/* Intensity Bar Animation */
.intensity-bar {
    transition: width 0.8s ease-in-out;
    animation: fillBar 1s ease-in-out;
}

@keyframes fillBar {
    from { width: 0%; }
}
</style>
@endpush

@section('content')
@php
    $widgetId = $widgetId ?? ($widget->getId() ?? 'kenya-heatmap-' . uniqid());
    $mapData = $widget->getMapData();
    $aiInsights = $widget->getAIInsights();
@endphp

<!-- Hidden data containers for JavaScript -->
<script type="application/json" id="map-data-{{ $widgetId }}">
    @json($mapData)
</script>

<!-- Include GeoJSON data if available -->
@if(isset($geoJsonData))
<script type="application/json" id="geojson-data-{{ $widgetId }}">
    @json($geoJsonData)
</script>
@endif

<div x-data="mohHeatmap" data-widget-id="{{ $widgetId }}" class="min-h-screen py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <!-- Page Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 gradient-text">
                        MNCH Training Coverage Dashboard
                    </h1>
                    <p class="mt-2 text-gray-600">
                        Comprehensive analytics of MOH training participation across all 47 counties
                    </p>
                </div>
                <div class="flex items-center space-x-3">
                    <a href="{{ url('admin') }}"
                        class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-md shadow transition-all duration-200">
                        <i class="fas fa-tachometer-alt mr-2"></i>
                        Admin Dashboard
                    </a>
                </div>
            </div>
        </div>

        <!-- Loading State -->
        <div x-show="isLoading" class="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg p-6 flex items-center space-x-3">
                <div class="loading-spinner"></div>
                <span class="text-gray-700">Loading dashboard...</span>
            </div>
        </div>

        <!-- Error State -->
        <div x-show="hasError" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <strong class="font-bold">Error:</strong>
            <span class="block sm:inline" x-text="error"></span>
        </div>

        <!-- Main Dashboard Widget -->
        <div x-show="hasData" class="bg-white rounded-lg shadow-sm border border-gray-200">

            <!-- Header with Summary Stats -->
            <div class="p-6 border-b border-gray-200 bg-gradient-to-r from-blue-50 to-indigo-50">
                <div class="flex items-center justify-between flex-wrap gap-4">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900 flex items-center">
                            <svg class="w-8 h-8 mr-3 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M2 10a8 8 0 018-8v8h8a8 8 0 11-16 0z" />
                                <path d="M12 2.252A8.014 8.014 0 0117.748 8H12V2.252z" />
                            </svg>
                            Interactive Training Heatmap
                        </h2>
                        <p class="text-sm text-gray-600 mt-1">Click on counties → facilities → participants for detailed analysis</p>
                    </div>

                    <!-- Summary Stats -->
                    <div class="flex space-x-4 text-sm flex-wrap gap-4">
                        <div class="text-center stat-card bg-white p-4 rounded-xl shadow-sm border">
                            <div class="text-3xl font-bold text-blue-600" x-text="formatNumber(totalTrainings)"></div>
                            <div class="text-gray-600 text-sm">MOH Trainings</div>
                        </div>
                        <div class="text-center stat-card bg-white p-4 rounded-xl shadow-sm border">
                            <div class="text-3xl font-bold text-green-600" x-text="formatNumber(totalParticipants)"></div>
                            <div class="text-gray-600 text-sm">Total Participants</div>
                        </div>
                        <div class="text-center stat-card bg-white p-4 rounded-xl shadow-sm border">
                            <div class="text-3xl font-bold text-purple-600" x-text="formatNumber(totalFacilities)"></div>
                            <div class="text-gray-600 text-sm">Active Facilities</div>
                        </div>
                        <div class="text-center stat-card bg-white p-4 rounded-xl shadow-sm border">
                            <div class="text-3xl font-bold text-orange-600" x-text="activeCounties"></div>
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
                        <button @click="refreshInsights()" 
                            class="text-white/80 hover:text-white transition-colors p-2 rounded-lg hover:bg-white/10"
                            :disabled="isLoading">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15">
                                </path>
                            </svg>
                        </button>
                    </div>

                    <div class="grid md:grid-cols-3 gap-4">
                        <div class="glass backdrop-blur rounded-lg p-4">
                            <h4 class="font-semibold mb-2 flex items-center">
                                🎯 <span class="ml-2">Coverage Assessment</span>
                            </h4>
                            <p class="text-sm text-white/90">
                                {{ $aiInsights['coverage'] ?? 'Training coverage varies significantly across regions. Focus needed on underserved counties.' }}
                            </p>
                        </div>
                        <div class="glass backdrop-blur rounded-lg p-4">
                            <h4 class="font-semibold mb-2 flex items-center">
                                📊 <span class="ml-2">Participation Trends</span>
                            </h4>
                            <p class="text-sm text-white/90">
                                {{ $aiInsights['participation'] ?? 'Higher facility participation correlates with better training outcomes. Consider hub-based approach.' }}
                            </p>
                        </div>
                        <div class="glass backdrop-blur rounded-lg p-4">
                            <h4 class="font-semibold mb-2 flex items-center">
                                💡 <span class="ml-2">Recommendations</span>
                            </h4>
                            <p class="text-sm text-white/90">
                                {{ $aiInsights['recommendations'] ?? 'Target low-coverage counties for next training cycle. Leverage high-performing regions as training hubs.' }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Map Section -->
            <div class="p-6">
                <div id="kenya-heatmap-{{ $widgetId }}"
                    class="relative rounded-lg overflow-hidden shadow-lg border"
                    style="height:500px; background:#f8fafc;">
                    <div id="map-loading-{{ $widgetId }}"
                        class="absolute inset-0 flex items-center justify-center bg-gray-100 z-10">
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
                                <div class="w-4 h-4 rounded border-2 border-gray-400"
                                    style="background-color: #e5e7eb;"></div>
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
                            <template x-if="mapData && mapData.summary && mapData.summary.avg_participants_per_training > 0">
                                <div class="text-sm text-gray-600 bg-white px-4 py-2 rounded-full border shadow-sm">
                                    📊 Avg. <span x-text="mapData.summary.avg_participants_per_training"></span> participants per training
                                </div>
                            </template>

                            <button @click="toggleTableView()"
                                class="btn-primary text-white px-4 py-2 rounded-lg text-sm flex items-center shadow-sm">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M3 10h18M3 6h18m-9 8h9"></path>
                                </svg>
                                <span x-text="filters.showTableView ? 'Hide Table View' : 'Show Table View'"></span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- County Training Summary Table -->
        <div id="summary-table-{{ $widgetId }}"
            x-show="filters.showTableView"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 transform scale-95"
            x-transition:enter-end="opacity-100 transform scale-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 transform scale-100"
            x-transition:leave-end="opacity-0 transform scale-95"
            class="bg-white rounded-lg shadow-sm mt-6 border border-gray-200">
            
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center justify-between flex-wrap gap-4">
                    <div>
                        <h3 class="text-xl font-semibold text-gray-900">County Training Summary</h3>
                        <p class="text-sm text-gray-600">Detailed breakdown of training participation by county</p>
                    </div>
                    <div class="flex items-center space-x-3">
                        <div class="relative">
                            <input type="text" 
                                id="county-search-{{ $widgetId }}"
                                x-model="filters.searchTerm"
                                @input="filterCounties()"
                                placeholder="Search counties..." 
                                class="pl-10 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-64">
                            <svg class="w-4 h-4 absolute left-3 top-3 text-gray-400" fill="none"
                                stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>
                        <select id="sort-select-{{ $widgetId }}" 
                            x-model="filters.sortBy"
                            @change="sortTable()"
                            class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="intensity">Sort by Intensity</option>
                            <option value="name">Sort by Name</option>
                            <option value="trainings">Sort by Trainings</option>
                            <option value="participants">Sort by Participants</option>
                            <option value="facilities">Sort by Facilities</option>
                        </select>
                        <button @click="exportTableData()"
                            :disabled="isLoading"
                            class="btn-success text-white px-4 py-2 rounded-lg text-sm flex items-center shadow-sm">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                                </path>
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
                        <template x-if="mapData && mapData.countyData">
                            <template x-for="county in filteredCounties" :key="county.county_id">
                                <tr class="county-row hover:bg-gray-50 transition-colors" 
                                    :data-county="county.name" 
                                    :data-county-id="county.county_id">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10">
                                                <div class="county-avatar h-10 w-10 rounded-full flex items-center justify-center text-sm font-bold text-white shadow-sm"
                                                    :style="`background-color: ${getIntensityColor(county.intensity, county.trainings)}`">
                                                    <span x-text="county.name.substring(0, 2)"></span>
                                                </div>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900" x-text="county.name"></div>
                                                <div class="text-sm text-gray-500">County</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-lg font-semibold text-gray-900" x-text="county.trainings"></div>
                                        <div class="text-sm text-gray-500">MOH trainings</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-lg font-semibold text-gray-900" x-text="formatNumber(county.participants)"></div>
                                        <div class="text-sm text-gray-500">Total participants</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-lg font-semibold text-gray-900" x-text="county.facilities"></div>
                                        <div class="text-sm text-gray-500">Active facilities</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 w-20 bg-gray-200 rounded-full h-2 mr-3">
                                                <div class="intensity-bar h-2 rounded-full"
                                                    :style="`width: ${Math.min(100, Math.max(5, county.intensity))}%; background-color: ${getIntensityColor(county.intensity, county.trainings)}`">
                                                </div>
                                            </div>
                                            <span class="text-sm font-semibold text-gray-900" x-text="Math.round(county.intensity)"></span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex px-3 py-1 text-xs font-semibold rounded-full"
                                            :class="getCoverageBadgeClass(county.intensity, county.trainings)"
                                            x-text="getCoverageText(county.intensity, county.trainings)">
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                        <template x-if="county.trainings > 0">
                                            <div class="flex space-x-2">
                                                <button @click="openCountyDetails(county.name, county)"
                                                    class="text-blue-600 hover:text-blue-900 hover:underline">
                                                    View Details
                                                </button>
                                                <button @click="openFacilityBreakdown(county.county_id, county.name)"
                                                    class="text-purple-600 hover:text-purple-900 hover:underline">
                                                    View Facilities
                                                </button>
                                            </div>
                                        </template>
                                        <button @click="highlightCountyOnMap(county.name)"
                                            class="text-green-600 hover:text-green-900 hover:underline">
                                            Locate
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </template>
                    </tbody>
                </table>
            </div>

            <div class="p-4 bg-gray-50 border-t border-gray-200">
                <div class="flex items-center justify-between text-sm text-gray-600">
                    <div>
                        Showing <span class="font-medium" x-text="filteredCounties.length"></span> counties
                    </div>
                    <div class="flex items-center space-x-4">
                        <span>Total Coverage: <span class="font-medium" x-text="coveragePercentage"></span></span>
                        <span>•</span>
                        <span>Active Counties: <span class="font-medium" x-text="`${activeCounties}/47`"></span></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modals (same as before but simplified) -->
    <!-- County Details Modal -->
    <div x-show="showCountyModal"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        @click.away="closeCountyModal()"
        class="fixed inset-0 modal-overlay overflow-y-auto h-full w-full z-50">
        <div class="relative top-4 mx-auto p-6 border w-11/12 md:w-4/5 lg:w-3/4 xl:w-2/3 shadow-xl rounded-xl bg-white max-h-screen overflow-y-auto fade-in">
            <div class="flex items-center justify-between mb-6 sticky top-0 bg-white pb-4 border-b z-50">
                <div>
                    <h3 id="county-modal-title-{{ $widgetId }}" class="text-2xl font-bold text-gray-900"></h3>
                    <p class="text-sm text-gray-600 mt-1">MOH training participation breakdown</p>
                </div>
                <button @click="closeCountyModal()"
                    class="text-gray-400 hover:text-gray-600 hover:bg-gray-100 p-3 rounded-full transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <div id="county-modal-content-{{ $widgetId }}" class="mt-4">
                <!-- Content will be populated by JavaScript -->
            </div>
        </div>
    </div>

    <!-- Other modals follow same pattern... -->
    <!-- For brevity, I'll include just the essential structure -->

    <!-- Toast Notifications Container -->
    <div id="toast-container" class="fixed top-4 right-4 space-y-2" style="z-index: 10001;"></div>
</div>

<!-- Scripts Section - CRITICAL: Load in correct order -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<!-- Include the map.js functions -->
<script src="{{ asset('js/map.js') }}"></script>
<script>
// Define the Alpine component BEFORE Alpine.js loads
document.addEventListener('alpine:init', () => {
    Alpine.data('mohHeatmap', () => ({
        // Component state
        widgetId: null,
        mapData: null,
        loading: false,
        error: null,
        
        // Modal states
        showCountyModal: false,
        showFacilityModal: false,
        showParticipantModal: false,
        showParticipantHistoryModal: false,
        
        // Current data context
        currentCounty: null,
        currentFacility: null,
        currentParticipant: null,
        
        // Filters and search
        filters: {
            searchTerm: '',
            sortBy: 'intensity',
            showTableView: false
        },

        // Initialize component
        init() {
            this.widgetId = this.$el.getAttribute('data-widget-id') || 'default';
            this.loadInitialData();
            this.setupEventListeners();
            
            // Set global reference for backwards compatibility
            window.currentWidgetId = this.widgetId;
        },

        // Load initial map data
        async loadInitialData() {
            try {
                this.loading = true;
                this.error = null;
                
                // Get map data from the backend
                const mapDataElement = document.getElementById(`map-data-${this.widgetId}`);
                if (mapDataElement) {
                    this.mapData = JSON.parse(mapDataElement.textContent);
                    console.log('Map data loaded:', this.mapData);
                }
                
                // Initialize the map if data is available
                if (this.mapData && window.initKenyaMap) {
                    await this.$nextTick(); // Wait for DOM to be ready
                    this.initializeMap();
                }
                
            } catch (error) {
                console.error('Error loading initial data:', error);
                this.error = 'Failed to load map data';
                this.showToast('Error loading map data', 'error');
            } finally {
                this.loading = false;
            }
        },

        // Initialize the Leaflet map
        initializeMap() {
            try {
                // Check if we have GeoJSON data
                const geoJsonElement = document.getElementById(`geojson-data-${this.widgetId}`);
                let geoJsonData = null;
                
                if (geoJsonElement) {
                    geoJsonData = JSON.parse(geoJsonElement.textContent);
                } else {
                    console.warn('No GeoJSON data found, map will initialize without county boundaries');
                }
                
                // Initialize map with options
                const mapOptions = {
                    geojson: geoJsonData,
                    mapData: this.mapData
                };
                
                window.initKenyaMap(this.widgetId, mapOptions);
                
            } catch (error) {
                console.error('Error initializing map:', error);
                this.error = 'Failed to initialize map';
                this.showToast('Map initialization failed', 'error');
            }
        },

        // Setup event listeners
        setupEventListeners() {
            // Watch for modal state changes
            this.$watch('showCountyModal', (value) => {
                document.body.style.overflow = value ? 'hidden' : 'auto';
            });

            this.$watch('showFacilityModal', (value) => {
                document.body.style.overflow = value ? 'hidden' : 'auto';
            });

            this.$watch('showParticipantModal', (value) => {
                document.body.style.overflow = value ? 'hidden' : 'auto';
            });

            this.$watch('showParticipantHistoryModal', (value) => {
                document.body.style.overflow = value ? 'hidden' : 'auto';
            });

            // Close modals on escape key
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    this.closeAllModals();
                }
            });
        },

        // County operations
        async openCountyDetails(countyName, countyData) {
            try {
                this.currentCounty = { name: countyName, data: countyData };
                this.showCountyModal = true;
                
                // Call the global function to show county details
                if (window.showCountyDetails) {
                    window.showCountyDetails(this.widgetId, countyName, countyData);
                }
            } catch (error) {
                console.error('Error opening county details:', error);
                this.showToast('Error loading county details', 'error');
            }
        },

        closeCountyModal() {
            this.showCountyModal = false;
            this.currentCounty = null;
            if (window.closeCountyModal) {
                window.closeCountyModal(this.widgetId);
            }
        },

        // Facility operations
        async openFacilityBreakdown(countyId, countyName) {
            try {
                this.showFacilityModal = true;
                
                if (window.showFacilityBreakdown) {
                    window.showFacilityBreakdown(this.widgetId, countyId, countyName);
                }
            } catch (error) {
                console.error('Error opening facility breakdown:', error);
                this.showToast('Error loading facility data', 'error');
            }
        },

        closeFacilityModal() {
            this.showFacilityModal = false;
            if (window.closeFacilityModal) {
                window.closeFacilityModal(this.widgetId);
            }
        },

        // Participant operations
        async openParticipantDetails(facilityId, facilityName) {
            try {
                this.showParticipantModal = true;
                
                if (window.showParticipantDetails) {
                    window.showParticipantDetails(this.widgetId, facilityId, facilityName);
                }
            } catch (error) {
                console.error('Error opening participant details:', error);
                this.showToast('Error loading participant data', 'error');
            }
        },

        closeParticipantModal() {
            this.showParticipantModal = false;
            if (window.closeParticipantModal) {
                window.closeParticipantModal(this.widgetId);
            }
        },

        // Participant history operations
        async openParticipantHistory(userId, userName) {
            try {
                this.showParticipantHistoryModal = true;
                
                if (window.showParticipantHistory) {
                    window.showParticipantHistory(this.widgetId, userId, userName);
                }
            } catch (error) {
                console.error('Error opening participant history:', error);
                this.showToast('Error loading training history', 'error');
            }
        },

        closeParticipantHistoryModal() {
            this.showParticipantHistoryModal = false;
            if (window.closeParticipantHistoryModal) {
                window.closeParticipantHistoryModal(this.widgetId);
            }
        },

        // Close all modals
        closeAllModals() {
            this.showCountyModal = false;
            this.showFacilityModal = false;
            this.showParticipantModal = false;
            this.showParticipantHistoryModal = false;
            document.body.style.overflow = 'auto';
        },

        // Table view operations
        toggleTableView() {
            this.filters.showTableView = !this.filters.showTableView;
            
            if (window.toggleTableView) {
                window.toggleTableView(this.widgetId);
            }
        },

        // Search and filter operations
        filterCounties() {
            if (window.filterCounties) {
                window.filterCounties(this.widgetId);
            }
        },

        sortTable() {
            if (window.sortTable) {
                window.sortTable(this.widgetId);
            }
        },

        // Export operations
        async exportTableData() {
            try {
                this.loading = true;
                
                if (window.exportTableData) {
                    await window.exportTableData(this.widgetId);
                }
                
                this.showToast('Data exported successfully', 'success');
            } catch (error) {
                console.error('Error exporting data:', error);
                this.showToast('Error exporting data', 'error');
            } finally {
                this.loading = false;
            }
        },

        // Map operations
        highlightCountyOnMap(countyName) {
            if (window.highlightCountyOnMap) {
                window.highlightCountyOnMap(this.widgetId, countyName);
            }
        },

        // Insights operations
        async refreshInsights() {
            try {
                this.loading = true;
                
                if (window.refreshInsights) {
                    window.refreshInsights(this.widgetId);
                }
                
                await new Promise(resolve => setTimeout(resolve, 2000));
                
                this.showToast('AI insights refreshed', 'success');
            } catch (error) {
                console.error('Error refreshing insights:', error);
                this.showToast('Error refreshing insights', 'error');
            } finally {
                this.loading = false;
            }
        },

        // Utility methods
        showToast(message, type = 'info') {
            if (window.showToast) {
                window.showToast(message, type);
            } else {
                this.createSimpleToast(message, type);
            }
        },

        createSimpleToast(message, type) {
            const toast = document.createElement('div');
            toast.className = `fixed top-4 right-4 p-4 rounded-lg text-white z-50 transition-all duration-300 transform translate-x-full`;
            
            const colors = {
                success: 'bg-green-500',
                error: 'bg-red-500',
                warning: 'bg-yellow-500',
                info: 'bg-blue-500'
            };
            
            toast.classList.add(colors[type] || colors.info);
            toast.textContent = message;
            
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.classList.remove('translate-x-full');
            }, 100);
            
            setTimeout(() => {
                toast.classList.add('translate-x-full');
                setTimeout(() => {
                    if (toast.parentElement) {
                        toast.parentElement.removeChild(toast);
                    }
                }, 300);
            }, 3000);
        },

        // Format helpers
        formatNumber(number) {
            if (typeof number !== 'number') return '0';
            return number.toLocaleString();
        },

        formatPercentage(value, total) {
            if (!total || total === 0) return '0%';
            return Math.round((value / total) * 100) + '%';
        },

        // Helper methods for template rendering
        getIntensityColor(intensity, trainings) {
            if (trainings === 0) return '#e5e7eb';
            
            if (intensity <= 12.5) return '#fca5a5';
            if (intensity <= 25) return '#fbbf24';
            if (intensity <= 50) return '#a3a3a3';
            if (intensity <= 75) return '#84cc16';
            return '#16a34a';
        },
        
        getCoverageBadgeClass(intensity, trainings) {
            if (trainings === 0) return 'badge-none';
            
            if (intensity > 75) return 'badge-excellent';
            if (intensity > 50) return 'badge-good';
            if (intensity > 25) return 'badge-fair';
            if (intensity > 10) return 'badge-limited';
            return 'badge-limited';
        },
        
        getCoverageText(intensity, trainings) {
            if (trainings === 0) return 'None';
            
            if (intensity > 75) return 'Excellent';
            if (intensity > 50) return 'Good';
            if (intensity > 25) return 'Fair';
            if (intensity > 10) return 'Limited';
            return 'Minimal';
        },

        // Data getters
        get totalCounties() {
            return this.mapData ? this.mapData.countyData.length : 47;
        },

        get activeCounties() {
            return this.mapData ? this.mapData.summary.counties_with_training : 0;
        },

        get totalTrainings() {
            return this.mapData ? this.mapData.totalTrainings : 0;
        },

        get totalParticipants() {
            return this.mapData ? this.mapData.totalParticipants : 0;
        },

        get totalFacilities() {
            return this.mapData ? this.mapData.totalFacilities : 0;
        },

        get coveragePercentage() {
            return this.formatPercentage(this.activeCounties, this.totalCounties);
        },

        // State getters
        get isLoading() {
            return this.loading;
        },

        get hasError() {
            return !!this.error;
        },

        get hasData() {
            return !!this.mapData && this.mapData.hasData;
        },

        // Filter helpers
        get filteredCounties() {
            if (!this.mapData || !this.mapData.countyData) return [];
            
            let counties = this.mapData.countyData;
            
            // Apply search filter
            if (this.filters.searchTerm) {
                const searchTerm = this.filters.searchTerm.toLowerCase();
                counties = counties.filter(county => 
                    county.name.toLowerCase().includes(searchTerm)
                );
            }
            
            // Apply sorting
            counties = counties.sort((a, b) => {
                switch (this.filters.sortBy) {
                    case 'name':
                        return a.name.localeCompare(b.name);
                    case 'trainings':
                        return b.trainings - a.trainings;
                    case 'participants':
                        return b.participants - a.participants;
                    case 'facilities':
                        return b.facilities - a.facilities;
                    case 'intensity':
                    default:
                        return b.intensity - a.intensity;
                }
            });
            
            return counties;
        }
    }));
});
</script>

<!-- Load Alpine.js AFTER the component is defined -->
<script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>

<!-- Load the enhanced map.js AFTER Alpine.js -->
<script>
// Wait for Alpine to be ready, then load map functions
document.addEventListener('alpine:initialized', () => {
    // Now load the map.js file content
    if (typeof initKenyaMap === 'undefined') {
        // Load map.js functions here or include them below
        console.log('Loading map functions...');
    }
});
</script>


@endsection