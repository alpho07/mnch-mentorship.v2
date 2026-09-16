@extends('layouts.app')

@section('title', $title)
@section('meta_description', $description ?? 'Interactive training coverage dashboard with participant-level insights')

@section('content')
@php
    $widgetId = $widgetId ?? ($widget->getId() ?? 'kenya-heatmap-' . uniqid());
    $mapData = $widget->getMapData();
    $aiInsights = $widget->getAIInsights();
    $trainingTypeLabel = $type === 'moh' ? 'MOH Global Training' : 'Facility Mentorship';
@endphp

<div class="min-h-screen py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <!-- Page Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">
                        {{ $title }}
                    </h1>
                    <p class="mt-2 text-gray-600">
                        {{ $description ?? 'Comprehensive analytics with participant-level insights across all 47 counties' }}
                    </p>
                </div>
                <div class="flex items-center space-x-3">
                    <a href="{{ route('training.heatmap.' . ($type === 'moh' ? 'mentorship' : 'moh')) }}"
                        class="inline-flex items-center px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white text-sm font-medium rounded-md shadow transition-all duration-200">
                        <i class="fas fa-exchange-alt mr-2"></i>
                        Switch to {{ $type === 'moh' ? 'Mentorship' : 'MOH' }} View
                    </a>
                    <a href="{{ url('admin') }}"
                        class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-md shadow transition-all duration-200">
                        <i class="fas fa-tachometer-alt mr-2"></i>
                        Admin Dashboard
                    </a>
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
                            <i class="fas fa-map-marked-alt text-blue-600 mr-3"></i>
                            {{ $trainingTypeLabel }} Coverage Dashboard
                        </h2>
                        <p class="text-sm text-gray-600 mt-1">Click counties → facilities → participants for detailed insights</p>
                    </div>

                    <!-- Summary Stats -->
                    <div class="flex space-x-4 text-sm flex-wrap gap-4">
                        <div class="text-center stat-card bg-white p-4 rounded-xl shadow-sm border">
                            <div class="text-3xl font-bold text-blue-600">{{ $mapData['totalTrainings'] }}</div>
                            <div class="text-gray-600 text-sm">{{ $type === 'moh' ? 'Global Programs' : 'Mentorship Programs' }}</div>
                        </div>
                        <div class="text-center stat-card bg-white p-4 rounded-xl shadow-sm border">
                            <div class="text-3xl font-bold text-green-600">
                                {{ number_format($mapData['totalParticipants']) }}</div>
                            <div class="text-gray-600 text-sm">Total Participants</div>
                        </div>
                        <div class="text-center stat-card bg-white p-4 rounded-xl shadow-sm border">
                            <div class="text-3xl font-bold text-purple-600">{{ $mapData['totalFacilities'] }}</div>
                            <div class="text-gray-600 text-sm">Active Facilities</div>
                        </div>
                        <div class="text-center stat-card bg-white p-4 rounded-xl shadow-sm border">
                            <div class="text-3xl font-bold text-orange-600">
                                {{ $mapData['summary']['counties_with_training'] }}</div>
                            <div class="text-gray-600 text-sm">Counties Covered</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- AI Insights Section -->
            <div class="p-6 border-b border-gray-200">
                <div class="bg-gradient-to-r from-blue-600 to-purple-600 rounded-xl p-6 text-white">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-xl font-semibold flex items-center">
                            <span class="bg-white/20 text-xs px-3 py-1 rounded-full mr-3">🤖 AI INSIGHTS</span>
                            {{ $trainingTypeLabel }} Analysis
                        </h3>
                        <button onclick="refreshInsights('{{ $widgetId }}')"
                            class="text-white/80 hover:text-white transition-colors p-2 rounded-lg hover:bg-white/10">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </div>

                    <div class="grid md:grid-cols-3 gap-4">
                        <div class="bg-white/10 backdrop-blur rounded-lg p-4 border border-white/20">
                            <h4 class="font-semibold mb-2 flex items-center">
                                <i class="fas fa-target mr-2"></i>Coverage Assessment
                            </h4>
                            <p class="text-sm text-white/90">
                                {{ $aiInsights['coverage'] }}
                            </p>
                        </div>
                        <div class="bg-white/10 backdrop-blur rounded-lg p-4 border border-white/20">
                            <h4 class="font-semibold mb-2 flex items-center">
                                <i class="fas fa-chart-line mr-2"></i>Participation Trends
                            </h4>
                            <p class="text-sm text-white/90">
                                {{ $aiInsights['participation'] }}
                            </p>
                        </div>
                        <div class="bg-white/10 backdrop-blur rounded-lg p-4 border border-white/20">
                            <h4 class="font-semibold mb-2 flex items-center">
                                <i class="fas fa-lightbulb mr-2"></i>Strategic Recommendations
                            </h4>
                            <p class="text-sm text-white/90">
                                {{ $aiInsights['recommendations'] }}
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
                            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto mb-2"></div>
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
                            @if ($mapData['summary']['avg_participants_per_training'] > 0)
                                <div class="text-sm text-gray-600 bg-white px-4 py-2 rounded-full border shadow-sm">
                                    <i class="fas fa-chart-bar mr-1"></i>
                                    Avg. {{ $mapData['summary']['avg_participants_per_training'] }} participants per training
                                </div>
                            @endif

                            <button onclick="toggleTableView('{{ $widgetId }}')"
                                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm flex items-center shadow-sm transition-colors">
                                <i class="fas fa-table mr-2"></i>
                                Show Table View
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Summary Table (hidden by default) -->
        <div id="summary-table-{{ $widgetId }}" class="hidden bg-white rounded-lg shadow-sm mt-6 border border-gray-200">
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center justify-between flex-wrap gap-4">
                    <div>
                        <h3 class="text-xl font-semibold text-gray-900">County Training Summary</h3>
                        <p class="text-sm text-gray-600">Detailed breakdown of training participation by county</p>
                    </div>
                    <div class="flex items-center space-x-3">
                        <div class="relative">
                            <input type="text" id="county-search-{{ $widgetId }}"
                                placeholder="Search counties..." onkeyup="filterCounties('{{ $widgetId }}')"
                                class="pl-10 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-64">
                            <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                        </div>
                        <select id="sort-select-{{ $widgetId }}" onchange="sortTable('{{ $widgetId }}')"
                            class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="intensity">Sort by Intensity</option>
                            <option value="name">Sort by Name</option>
                            <option value="trainings">Sort by Trainings</option>
                            <option value="participants">Sort by Participants</option>
                            <option value="facilities">Sort by Facilities</option>
                        </select>
                        <button onclick="exportTableData('{{ $widgetId }}')"
                            class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm flex items-center shadow-sm transition-colors">
                            <i class="fas fa-download mr-2"></i>
                            Export CSV
                        </button>
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto">
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
                        @foreach ($mapData['countyData'] as $county)
                            <tr class="county-row hover:bg-gray-50" data-county="{{ $county['name'] }}">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        @php
                                            $intensityColor = '#e5e7eb';
                                            if ($county['trainings'] > 0) {
                                                if ($county['intensity'] > 75) {
                                                    $intensityColor = '#16a34a';
                                                } elseif ($county['intensity'] > 50) {
                                                    $intensityColor = '#84cc16';
                                                } elseif ($county['intensity'] > 25) {
                                                    $intensityColor = '#a3a3a3';
                                                } elseif ($county['intensity'] > 10) {
                                                    $intensityColor = '#fbbf24';
                                                } else {
                                                    $intensityColor = '#fca5a5';
                                                }
                                            }
                                        @endphp
                                        <div class="flex-shrink-0 h-10 w-10">
                                            <div class="h-10 w-10 rounded-full flex items-center justify-center text-sm font-bold text-white shadow-sm"
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
                                    <div class="text-sm text-gray-500">{{ $type === 'moh' ? 'Global' : 'Mentorship' }} programs</div>
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
                                            <div class="h-2 rounded-full"
                                                style="width: {{ min(100, max(5, $county['intensity'])) }}%; background-color: {{ $intensityColor }};">
                                            </div>
                                        </div>
                                        <span class="text-sm font-semibold text-gray-900">{{ round($county['intensity']) }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @php
                                        $coverage = $county['trainings'] > 0
                                            ? ($county['intensity'] > 75 ? 'Excellent'
                                                : ($county['intensity'] > 50 ? 'Good'
                                                    : ($county['intensity'] > 25 ? 'Fair'
                                                        : ($county['intensity'] > 10 ? 'Limited' : 'Minimal'))))
                                            : 'None';
                                        $badgeClass = $county['trainings'] > 0
                                            ? ($county['intensity'] > 75 ? 'bg-green-100 text-green-800'
                                                : ($county['intensity'] > 50 ? 'bg-blue-100 text-blue-800'
                                                    : ($county['intensity'] > 25 ? 'bg-yellow-100 text-yellow-800'
                                                        : ($county['intensity'] > 10 ? 'bg-orange-100 text-orange-800' : 'bg-red-100 text-red-800'))))
                                            : 'bg-gray-100 text-gray-800';
                                    @endphp
                                    <span class="inline-flex px-3 py-1 text-xs font-semibold rounded-full {{ $badgeClass }}">
                                        {{ $coverage }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                    @if ($county['trainings'] > 0)
                                        <button onclick="showCountyDetails('{{ $widgetId }}', '{{ $county['name'] }}', @json($county))"
                                            class="text-blue-600 hover:text-blue-900 hover:underline">
                                            <i class="fas fa-eye mr-1"></i>View Details
                                        </button>
                                    @endif
                                    <button onclick="highlightCountyOnMap('{{ $widgetId }}', '{{ $county['name'] }}')"
                                        class="text-green-600 hover:text-green-900 hover:underline">
                                        <i class="fas fa-map-marker-alt mr-1"></i>Locate
                                    </button>
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
                        <span>Total Coverage: <span
                                class="font-medium">{{ round(($mapData['summary']['counties_with_training'] / 47) * 100, 1) }}%</span></span>
                        <span>•</span>
                        <span>Active Counties: <span
                                class="font-medium">{{ $mapData['summary']['counties_with_training'] }}/47</span></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- County Details Modal -->
<div id="county-details-modal-{{ $widgetId }}" class="hidden fixed inset-0 bg-black bg-opacity-50 overflow-y-auto h-full w-full" style="z-index: 9998;">
    <div class="relative top-4 mx-auto p-6 border w-11/12 md:w-4/5 lg:w-3/4 xl:w-2/3 shadow-xl rounded-xl bg-white max-h-screen overflow-y-auto">
        <div class="flex items-center justify-between mb-6 sticky top-0 bg-white pb-4 border-b z-50">
            <div>
                <h3 id="county-modal-title-{{ $widgetId }}" class="text-2xl font-bold text-gray-900"></h3>
                <p class="text-sm text-gray-600 mt-1">{{ $trainingTypeLabel }} participation breakdown</p>
            </div>
            <button onclick="closeCountyModal('{{ $widgetId }}')"
                class="text-gray-400 hover:text-gray-600 hover:bg-gray-100 p-3 rounded-full transition-colors">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div id="county-modal-content-{{ $widgetId }}" class="mt-4">
            <!-- Content populated by JavaScript -->
        </div>
    </div>
</div>

<!-- Facility Participants Modal -->
<div id="facility-participants-modal-{{ $widgetId }}" class="hidden fixed inset-0 bg-black bg-opacity-50 overflow-y-auto h-full w-full" style="z-index: 9999;">
    <div class="relative top-4 mx-auto p-6 border w-11/12 md:w-5/6 lg:w-4/5 xl:w-3/4 shadow-xl rounded-xl bg-white max-h-screen overflow-y-auto">
        <div class="flex items-center justify-between mb-6 sticky top-0 bg-white pb-4 border-b z-50">
            <div>
                <h3 id="facility-modal-title-{{ $widgetId }}" class="text-2xl font-bold text-gray-900"></h3>
                <p class="text-sm text-gray-600 mt-1">Training participants and performance overview</p>
            </div>
            <button onclick="closeFacilityModal('{{ $widgetId }}')"
                class="text-gray-400 hover:text-gray-600 hover:bg-gray-100 p-3 rounded-full transition-colors">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div id="facility-modal-content-{{ $widgetId }}" class="mt-4">
            <!-- Content populated by JavaScript -->
        </div>
    </div>
</div>

<!-- Participant Profile Modal -->
<div id="participant-profile-modal-{{ $widgetId }}" class="hidden fixed inset-0 bg-black bg-opacity-50 overflow-y-auto h-full w-full" style="z-index: 10000;">
    <div class="relative top-4 mx-auto p-6 border w-11/12 md:w-5/6 lg:w-4/5 xl:w-3/4 shadow-xl rounded-xl bg-white max-h-screen overflow-y-auto">
        <div class="flex items-center justify-between mb-6 sticky top-0 bg-white pb-4 border-b z-50">
            <div>
                <h3 id="participant-modal-title-{{ $widgetId }}" class="text-2xl font-bold text-gray-900"></h3>
                <p class="text-sm text-gray-600 mt-1">Comprehensive training profile and performance analysis</p>
            </div>
            <button onclick="closeParticipantModal('{{ $widgetId }}')"
                class="text-gray-400 hover:text-gray-600 hover:bg-gray-100 p-3 rounded-full transition-colors">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div id="participant-modal-content-{{ $widgetId }}" class="mt-4">
            <!-- Content populated by JavaScript -->
        </div>
    </div>
</div>

<!-- Toast Notifications -->
<div id="toast-container" class="fixed top-4 right-4 space-y-2" style="z-index: 10001;"></div>

@endsection

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<style>
    .participant-card {
        @apply bg-white border border-gray-200 rounded-lg p-4 hover:shadow-md transition-all duration-200 cursor-pointer;
    }
    
    .participant-card:hover {
        @apply border-blue-300 shadow-lg;
    }
    
    .facility-section {
        @apply bg-gray-50 border border-gray-200 rounded-lg p-4 mb-4;
    }
    
    .drill-down-btn {
        @apply inline-flex items-center px-3 py-1.5 bg-blue-50 hover:bg-blue-100 text-blue-700 text-xs font-medium rounded-md border border-blue-200 transition-colors;
    }
    
    .participant-avatar {
        @apply w-10 h-10 bg-gradient-to-br from-blue-500 to-blue-600 rounded-full flex items-center justify-center text-white font-semibold text-sm;
    }
    
    .status-badge {
        @apply inline-flex items-center px-2 py-1 rounded-full text-xs font-medium;
    }
    
    .status-completed { @apply bg-green-100 text-green-800; }
    .status-in-progress { @apply bg-yellow-100 text-yellow-800; }
    .status-not-started { @apply bg-gray-100 text-gray-800; }
    
    .performance-badge {
        @apply inline-flex items-center px-2 py-1 rounded-full text-xs font-medium;
    }
    
    .performance-excellent { @apply bg-green-100 text-green-800; }
    .performance-good { @apply bg-blue-100 text-blue-800; }
    .performance-needs-improvement { @apply bg-red-100 text-red-800; }
    
    .timeline-item {
        @apply relative pb-4;
    }
    
    .timeline-item:not(:last-child)::after {
        content: '';
        @apply absolute left-4 top-8 w-0.5 h-full bg-gray-200;
    }

    .stat-card {
        transition: transform 0.2s ease-in-out;
    }

    .stat-card:hover {
        transform: translateY(-2px);
    }
</style>
@endpush

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    // All the JavaScript from the enhanced heatmap view goes here
    // Including the drill-down functionality for facilities and participants
    
    // Global variables
    let mapInstance = null;
    let geoJsonLayer = null;
    const widgetId = '{{ $widgetId }}';
    const trainingType = '{{ $type }}';

    // [Include all the JavaScript functions from the enhanced-heatmap-view artifact]
    // This includes: initKenyaMap, showCountyDetails, showFacilityParticipants, 
    // displayFacilityParticipants, showParticipantProfile, displayParticipantProfile, etc.
    
    @include('partials.heatmap-scripts')
</script>
@endpush 