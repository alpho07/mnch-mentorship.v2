@php $widgetId = $widgetId ?? ($this->getId() ?? 'kenya-heatmap-' . uniqid()); @endphp

<div class="bg-white rounded-lg shadow-sm">
    <!-- Header with summary stats -->
    <div class="p-4 border-b border-gray-200">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Training Coverage Across Kenya</h3>
                <p class="text-sm text-gray-600">Click on counties for detailed training breakdown</p>
            </div>
            
            @php $mapData = $this->getMapData(); @endphp
            <div class="flex space-x-6 text-sm">
                <div class="text-center">
                    <div class="text-2xl font-bold text-blue-600">{{ $mapData['totalTrainings'] }}</div>
                    <div class="text-gray-600">Total Trainings</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-green-600">{{ number_format($mapData['totalParticipants']) }}</div>
                    <div class="text-gray-600">Participants</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-purple-600">{{ $mapData['totalFacilities'] }}</div>
                    <div class="text-gray-600">Facilities</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-orange-600">{{ $mapData['summary']['counties_with_training'] }}</div>
                    <div class="text-gray-600">Active Counties</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Map container -->
    <div class="p-4">
        <div id="kenya-heatmap-{{ $widgetId }}" style="height:480px;background:#f3f4f6;border-radius:0.5rem;"></div>
        
        <!-- Legend -->
        <div class="mt-4 flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <span class="text-sm font-medium text-gray-700">Training Intensity:</span>
                <div class="flex items-center space-x-2">
                    <div class="w-4 h-4 rounded" style="background-color: #fee2e2;"></div>
                    <span class="text-xs text-gray-600">Low</span>
                </div>
                <div class="flex items-center space-x-2">
                    <div class="w-4 h-4 rounded" style="background-color: #fbbf24;"></div>
                    <span class="text-xs text-gray-600">Medium</span>
                </div>
                <div class="flex items-center space-x-2">
                    <div class="w-4 h-4 rounded" style="background-color: #f59e0b;"></div>
                    <span class="text-xs text-gray-600">High</span>
                </div>
                <div class="flex items-center space-x-2">
                    <div class="w-4 h-4 rounded" style="background-color: #dc2626;"></div>
                    <span class="text-xs text-gray-600">Very High</span>
                </div>
            </div>
            
            @if($mapData['summary']['avg_participants_per_training'] > 0)
            <div class="text-sm text-gray-600">
                Avg. {{ $mapData['summary']['avg_participants_per_training'] }} participants per training
            </div>
            @endif
        </div>
    </div>
</div>

<!-- County details modal (hidden by default) -->
<div id="county-details-modal-{{ $widgetId }}" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 id="county-modal-title-{{ $widgetId }}" class="text-lg font-semibold text-gray-900"></h3>
                <button onclick="closeCountyModal('{{ $widgetId }}')" class="text-gray-400 hover:text-gray-600">
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
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const widgetId = '{{ $widgetId }}';
    const mapData = @json($mapData);
    
    // Initialize map
    if (window.initKenyaMapWidget) {
        window.initKenyaMapWidget(widgetId, {
            height: 480,
            mapData: mapData,
            geojson: @json(json_decode(file_get_contents(public_path('kenyan-counties.geojson')), true)),
            onCountyClick: function(countyName, countyData) {
                showCountyDetails(widgetId, countyName, countyData);
            }
        });
    }
});

// Handle Livewire navigation
document.addEventListener('livewire:navigated', function() {
    const widgetId = '{{ $widgetId }}';
    const mapData = @json($mapData);
    
    if (window.initKenyaMapWidget) {
        window.initKenyaMapWidget(widgetId, {
            height: 480,
            mapData: mapData,
            geojson: @json(json_decode(file_get_contents(public_path('kenyan-counties.geojson')), true)),
            onCountyClick: function(countyName, countyData) {
                showCountyDetails(widgetId, countyName, countyData);
            }
        });
    }
});

// Function to show county details modal
function showCountyDetails(widgetId, countyName, countyData) {
    const modal = document.getElementById(`county-details-modal-${widgetId}`);
    const title = document.getElementById(`county-modal-title-${widgetId}`);
    const content = document.getElementById(`county-modal-content-${widgetId}`);
    
    title.textContent = `${countyName} County Training Details`;
    
    let html = `
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-blue-50 p-3 rounded-lg text-center">
                <div class="text-2xl font-bold text-blue-600">${countyData.trainings}</div>
                <div class="text-sm text-gray-600">Trainings</div>
            </div>
            <div class="bg-green-50 p-3 rounded-lg text-center">
                <div class="text-2xl font-bold text-green-600">${countyData.participants}</div>
                <div class="text-sm text-gray-600">Participants</div>
            </div>
            <div class="bg-purple-50 p-3 rounded-lg text-center">
                <div class="text-2xl font-bold text-purple-600">${countyData.facilities}</div>
                <div class="text-sm text-gray-600">Facilities</div>
            </div>
            <div class="bg-orange-50 p-3 rounded-lg text-center">
                <div class="text-2xl font-bold text-orange-600">${Math.round(countyData.intensity)}</div>
                <div class="text-sm text-gray-600">Intensity Score</div>
            </div>
        </div>
    `;
    
    if (countyData.training_details && countyData.training_details.length > 0) {
        html += `
            <div class="space-y-3">
                <h4 class="font-semibold text-gray-900 mb-3">Training Breakdown:</h4>
                ${countyData.training_details.map(training => `
                    <div class="border border-gray-200 rounded-lg p-3">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <h5 class="font-medium text-gray-900">${training.title}</h5>
                                <div class="text-sm text-gray-600 mt-1">
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${training.type === 'Global Training' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'}">
                                        ${training.type}
                                    </span>
                                </div>
                                ${training.programs ? `<div class="text-sm text-gray-600 mt-1">Programs: ${training.programs}</div>` : ''}
                            </div>
                            <div class="text-right">
                                <div class="text-lg font-semibold text-gray-900">${training.participants}</div>
                                <div class="text-xs text-gray-500">participants</div>
                            </div>
                        </div>
                        <div class="text-sm text-gray-600 mt-2">
                            <strong>Facility:</strong> ${training.facility}
                        </div>
                    </div>
                `).join('')}
            </div>
        `;
    } else {
        html += '<div class="text-center text-gray-500 py-8">No training data available for this county.</div>';
    }
    
    content.innerHTML = html;
    modal.classList.remove('hidden');
}

// Function to close county details modal
function closeCountyModal(widgetId) {
    const modal = document.getElementById(`county-details-modal-${widgetId}`);
    modal.classList.add('hidden');
}

// Close modal when clicking outside
document.addEventListener('click', function(event) {
    const modals = document.querySelectorAll('[id^="county-details-modal-"]');
    modals.forEach(modal => {
        if (event.target === modal) {
            modal.classList.add('hidden');
        }
    });
});
</script>