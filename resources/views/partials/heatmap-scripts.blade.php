// =====================================================
// COMPLETE KENYA TRAINING HEATMAP SCRIPTS
// Interactive drill-down from County → Facility → Participant
// =====================================================

// Global variables
let mapInstance = null;
let geoJsonLayer = null;
const widgetId = '{{ $widgetId }}';
const trainingType = '{{ $type }}';

// =====================================================
// MAP INITIALIZATION AND DISPLAY
// =====================================================

function initKenyaMap(widgetId, options) {
    const mapContainer = document.getElementById(`kenya-heatmap-${widgetId}`);
    const loadingIndicator = document.getElementById(`map-loading-${widgetId}`);

    if (!mapContainer) {
        console.error('Map container not found');
        return;
    }

    try {
        // Remove existing map if any
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
            dragging: true
        }).setView([-0.0236, 37.9062], 6);

        // Add tile layer
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors',
            maxZoom: 18,
            subdomains: ['a', 'b', 'c']
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
        showToast('Error loading map. Please refresh the page.', 'error');
        if (loadingIndicator) {
            loadingIndicator.innerHTML = '<div class="text-center text-red-600">Error loading map</div>';
        }
    }
}

function displayCountyData(geojson, mapData, widgetId) {
    if (!geojson || !mapData) {
        console.error('Missing geojson or mapData');
        return;
    }

    const countyData = mapData.countyData;
    const intensityLevels = mapData.intensityLevels;

    // Create lookup object for county data
    const dataLookup = {};
    countyData.forEach(county => {
        dataLookup[county.name.toLowerCase()] = county;
    });

    // Style function for counties
    function getCountyStyle(feature) {
        const countyName = feature.properties.COUNTY.toLowerCase();
        const county = dataLookup[countyName];

        // Default style for counties with no data
        if (!county || county.intensity === 0) {
            return {
                fillColor: '#e5e7eb',
                weight: 1,
                opacity: 0.8,
                color: '#9ca3af',
                fillOpacity: 0.6
            };
        }

        // Determine color based on intensity
        let fillColor = '#e5e7eb';
        if (county.intensity <= intensityLevels.low * 0.5) {
            fillColor = '#fca5a5'; // Very low - light red
        } else if (county.intensity <= intensityLevels.low) {
            fillColor = '#fbbf24'; // Low - yellow
        } else if (county.intensity <= intensityLevels.medium) {
            fillColor = '#a3a3a3'; // Medium - gray
        } else if (county.intensity <= intensityLevels.high) {
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
            const countyName = feature.properties.COUNTY;
            const county = dataLookup[countyName.toLowerCase()];

            // Create tooltip content
            let tooltipContent = createCountyTooltip(countyName, county);

            // Bind tooltip
            layer.bindTooltip(tooltipContent, {
                permanent: false,
                direction: 'center',
                className: 'custom-tooltip',
                offset: [0, 0]
            });

            // Add event listeners
            layer.on({
                mouseover: function(e) {
                    highlightFeature(e);
                },
                mouseout: function(e) {
                    resetHighlight(e);
                },
                click: function(e) {
                    handleCountyClick(e, countyName, county, widgetId);
                }
            });
        }
    }).addTo(mapInstance);

    // Fit map to bounds
    mapInstance.fitBounds(geoJsonLayer.getBounds());
}

function createCountyTooltip(countyName, county) {
    let content = `
        <div class="p-3 bg-white border rounded-lg shadow-lg min-w-48">
            <div class="font-bold text-gray-900 text-base mb-2">${countyName} County</div>
    `;

    if (county && county.trainings > 0) {
        content += `
            <div class="space-y-1 text-sm">
                <div class="flex justify-between items-center">
                    <span class="text-gray-600">Programs:</span>
                    <span class="font-semibold text-blue-600">${county.trainings}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-gray-600">Participants:</span>
                    <span class="font-semibold text-green-600">${county.participants.toLocaleString()}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-gray-600">Facilities:</span>
                    <span class="font-semibold text-purple-600">${county.facilities}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-gray-600">Intensity:</span>
                    <span class="font-semibold text-orange-600">${Math.round(county.intensity)}</span>
                </div>
            </div>
            <div class="mt-2 pt-2 border-t border-gray-200">
                <div class="text-xs text-blue-600 font-medium flex items-center">
                    <i class="fas fa-mouse-pointer mr-1"></i>
                    Click for detailed insights
                </div>
            </div>
        `;
    } else {
        content += `
            <div class="text-sm text-red-500 flex items-center">
                <i class="fas fa-exclamation-circle mr-2"></i>
                No training data available
            </div>
        `;
    }

    content += '</div>';
    return content;
}

function highlightFeature(e) {
    const layer = e.target;
    layer.setStyle({
        weight: 3,
        color: '#1f2937',
        fillOpacity: 0.9
    });
    layer.bringToFront();
}

function resetHighlight(e) {
    geoJsonLayer.resetStyle(e.target);
}

function handleCountyClick(e, countyName, county, widgetId) {
    if (county && county.trainings > 0) {
        showCountyDetails(widgetId, countyName, county);
    } else {
        showToast(`No training data available for ${countyName} County`, 'info');
    }
}

// =====================================================
// COUNTY DETAILS MODAL
// =====================================================

function showCountyDetails(widgetId, countyName, countyData) {
    const modal = document.getElementById(`county-details-modal-${widgetId}`);
    const title = document.getElementById(`county-modal-title-${widgetId}`);
    const content = document.getElementById(`county-modal-content-${widgetId}`);

    if (!modal || !title || !content) {
        console.error('County modal elements not found');
        return;
    }

    title.textContent = `${countyName} County Training Details`;

    // Build modal content
    let html = createCountySummaryCards(countyData);
    
    if (countyData.training_details && countyData.training_details.length > 0) {
        html += createTrainingDetailsList(countyData.training_details);
    } else {
        html += createNoDataMessage();
    }

    content.innerHTML = html;
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function createCountySummaryCards(countyData) {
    return `
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-gradient-to-br from-blue-50 to-blue-100 p-6 rounded-xl text-center border border-blue-200 transform hover:scale-105 transition-transform">
                <div class="text-3xl font-bold text-blue-600">${countyData.trainings}</div>
                <div class="text-sm text-blue-700 font-medium mt-1">Training Programs</div>
            </div>
            <div class="bg-gradient-to-br from-green-50 to-green-100 p-6 rounded-xl text-center border border-green-200 transform hover:scale-105 transition-transform">
                <div class="text-3xl font-bold text-green-600">${countyData.participants.toLocaleString()}</div>
                <div class="text-sm text-green-700 font-medium mt-1">Total Participants</div>
            </div>
            <div class="bg-gradient-to-br from-purple-50 to-purple-100 p-6 rounded-xl text-center border border-purple-200 transform hover:scale-105 transition-transform">
                <div class="text-3xl font-bold text-purple-600">${countyData.facilities}</div>
                <div class="text-sm text-purple-700 font-medium mt-1">Participating Facilities</div>
            </div>
            <div class="bg-gradient-to-br from-orange-50 to-orange-100 p-6 rounded-xl text-center border border-orange-200 transform hover:scale-105 transition-transform">
                <div class="text-3xl font-bold text-orange-600">${Math.round(countyData.intensity)}</div>
                <div class="text-sm text-orange-700 font-medium mt-1">Intensity Score</div>
            </div>
        </div>
    `;
}

function createTrainingDetailsList(trainingDetails) {
    return `
        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <h4 class="text-xl font-bold text-gray-900 flex items-center">
                    <i class="fas fa-clipboard-list mr-3 text-blue-600"></i>
                    Training Programs with Facility Breakdown
                </h4>
                <span class="bg-blue-100 text-blue-800 text-xs font-semibold px-3 py-1 rounded-full">
                    ${trainingDetails.length} Program${trainingDetails.length !== 1 ? 's' : ''}
                </span>
            </div>
            
            <div class="space-y-6 max-h-96 overflow-y-auto pr-2">
                ${trainingDetails.map((training, index) => createTrainingCard(training, index)).join('')}
            </div>
        </div>
    `;
}

function createTrainingCard(training, index) {
    return `
        <div class="bg-gradient-to-r from-gray-50 to-white border border-gray-200 rounded-xl p-6 hover:shadow-lg transition-all duration-300">
            <div class="flex items-start justify-between mb-4">
                <div class="flex-1">
                    <h5 class="text-lg font-semibold text-gray-900 mb-2">${training.training_title}</h5>
                    <div class="flex flex-wrap gap-2 mb-3">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800 border border-blue-200">
                            <i class="fas fa-id-card mr-1"></i> ${training.training_identifier}
                        </span>
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800 border border-purple-200">
                            ${training.training_type === 'global_training' ? 
                                '<i class="fas fa-globe mr-1"></i> Global Training' : 
                                '<i class="fas fa-hospital mr-1"></i> Facility Mentorship'}
                        </span>
                        ${training.status ? `
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 border border-green-200">
                                <i class="fas fa-check-circle mr-1"></i> ${training.status}
                            </span>
                        ` : ''}
                    </div>
                    ${training.programs ? `
                        <div class="text-sm text-gray-600 mb-2">
                            <strong><i class="fas fa-graduation-cap mr-1"></i> Programs:</strong> ${training.programs}
                        </div>
                    ` : ''}
                    ${training.start_date ? `
                        <div class="text-sm text-gray-600">
                            <strong><i class="fas fa-calendar mr-1"></i> Duration:</strong> 
                            ${training.start_date}${training.end_date ? ` → ${training.end_date}` : ''}
                        </div>
                    ` : ''}
                </div>
            </div>
            
            <div class="grid grid-cols-2 gap-4 mb-6">
                <div class="bg-white p-4 rounded-lg border-2 border-blue-100 text-center hover:border-blue-300 transition-colors">
                    <div class="text-2xl font-bold text-blue-600">${training.facilities_count}</div>
                    <div class="text-sm text-blue-700 font-medium">Facilities Involved</div>
                </div>
                <div class="bg-white p-4 rounded-lg border-2 border-green-100 text-center hover:border-green-300 transition-colors">
                    <div class="text-2xl font-bold text-green-600">${training.participants_count.toLocaleString()}</div>
                    <div class="text-sm text-green-700 font-medium">Total Participants</div>
                </div>
            </div>
            
            ${training.facilities && training.facilities.length > 0 ? 
                createFacilitiesList(training.facilities, training.training_id) : ''}
        </div>
    `;
}

function createFacilitiesList(facilities, trainingId) {
    return `
        <div class="mt-6">
            <div class="text-sm font-semibold text-gray-700 mb-4 flex items-center">
                <i class="fas fa-hospital mr-2 text-gray-600"></i>
                Participating Facilities & Participants
            </div>
            <div class="space-y-3">
                ${facilities.map(facility => createFacilityCard(facility, trainingId)).join('')}
            </div>
        </div>
    `;
}

function createFacilityCard(facility, trainingId) {
    return `
        <div class="facility-section bg-gray-50 border border-gray-200 rounded-lg p-4">
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-blue-500 rounded-lg flex items-center justify-center mr-3">
                        <i class="fas fa-hospital text-white"></i>
                    </div>
                    <div>
                        <h6 class="font-semibold text-gray-900">${facility.facility_name}</h6>
                        <p class="text-xs text-gray-500">MFL: ${facility.mfl_code || 'N/A'}</p>
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-lg font-bold text-green-600">${facility.participant_count}</div>
                    <div class="text-xs text-gray-500">participants</div>
                </div>
            </div>
            
            <button onclick="showFacilityParticipants('${facility.facility_id}', '${trainingId}', '${facility.facility_name}')" 
                    class="drill-down-btn w-full justify-center mb-3 bg-blue-50 hover:bg-blue-100 text-blue-700 px-4 py-2 rounded-md border border-blue-200 transition-colors">
                <i class="fas fa-users mr-2"></i>
                View ${facility.participant_count} Participants
            </button>
            
            ${facility.participants && facility.participants.length > 0 ? 
                createParticipantPreview(facility.participants, facility.facility_id, trainingId, facility.facility_name) : ''}
        </div>
    `;
}

function createParticipantPreview(participants, facilityId, trainingId, facilityName) {
    const previewCount = Math.min(6, participants.length);
    const remainingCount = participants.length - previewCount;

    return `
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2">
            ${participants.slice(0, previewCount).map(participant => createParticipantPreviewCard(participant)).join('')}
            ${remainingCount > 0 ? `
                <div class="col-span-full text-center">
                    <button onclick="showFacilityParticipants('${facilityId}', '${trainingId}', '${facilityName}')" 
                            class="text-sm text-blue-600 hover:text-blue-800 underline font-medium">
                        <i class="fas fa-plus mr-1"></i> View ${remainingCount} more participants...
                    </button>
                </div>
            ` : ''}
        </div>
    `;
}

function createParticipantPreviewCard(participant) {
    const statusClass = getStatusClass(participant.completion_status);
    const performanceClass = getPerformanceClass(participant.assessment_score);

    return `
        <div class="participant-card bg-white border border-gray-200 rounded-lg p-3 hover:shadow-md transition-all duration-200 cursor-pointer" 
             onclick="showParticipantProfile('${participant.user_id}')">
            <div class="flex items-center space-x-3">
                <div class="participant-avatar w-8 h-8 bg-gradient-to-br from-blue-500 to-blue-600 rounded-full flex items-center justify-center text-white font-semibold text-xs">
                    ${getInitials(participant.name)}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-900 truncate">${participant.name}</p>
                    <p class="text-xs text-gray-500">${participant.cadre || 'N/A'}</p>
                    <div class="flex items-center mt-1 space-x-1">
                        <span class="status-badge ${statusClass} px-2 py-1 rounded-full text-xs font-medium">
                            ${participant.completion_status || 'Not Started'}
                        </span>
                        ${participant.assessment_score ? `
                            <span class="performance-badge ${performanceClass} px-2 py-1 rounded-full text-xs font-medium">
                                ${participant.assessment_score}%
                            </span>
                        ` : ''}
                    </div>
                </div>
            </div>
        </div>
    `;
}

function createNoDataMessage() {
    return `
        <div class="text-center py-16">
            <i class="fas fa-chart-line text-gray-400 text-6xl mb-4"></i>
            <h3 class="text-lg font-medium text-gray-900 mb-2">No Training Data Available</h3>
            <p class="text-gray-500 mb-4">This county has not participated in any training programs yet.</p>
            <div class="bg-yellow-100 text-yellow-800 px-4 py-2 rounded-lg inline-block">
                <i class="fas fa-lightbulb mr-2"></i>
                Consider including this county in upcoming training cycles
            </div>
        </div>
    `;
}

// =====================================================
// FACILITY PARTICIPANTS MODAL
// =====================================================

function showFacilityParticipants(facilityId, trainingId, facilityName) {
    showToast('Loading facility participants...', 'info');
    
    const url = `/training/facility/${facilityId}/participants?training_id=${trainingId}&training_type=${trainingType === 'moh' ? 'global_training' : 'facility_mentorship'}`;
    
    fetch(url)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            displayFacilityParticipants(data, facilityName);
        })
        .catch(error => {
            console.error('Error loading facility participants:', error);
            showToast('Error loading participants. Please try again.', 'error');
        });
}

function displayFacilityParticipants(data, facilityName) {
    const modal = document.getElementById(`facility-participants-modal-${widgetId}`);
    const title = document.getElementById(`facility-modal-title-${widgetId}`);
    const content = document.getElementById(`facility-modal-content-${widgetId}`);

    if (!modal || !title || !content) {
        console.error('Facility modal elements not found');
        return;
    }

    title.textContent = `${facilityName || data.facility.name} - Training Participants`;

    const html = `
        ${createFacilityHeader(data.facility, data.summary)}
        ${createFacilitySummaryStats(data.summary)}
        ${data.insights && data.insights.length > 0 ? createFacilityInsights(data.insights) : ''}
        ${createParticipantsByTraining(data.participants_by_training)}
    `;

    content.innerHTML = html;
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function createFacilityHeader(facility, summary) {
    return `
        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg p-6 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h4 class="text-xl font-bold text-gray-900 flex items-center">
                        <i class="fas fa-hospital mr-2 text-blue-600"></i>
                        ${facility.name}
                    </h4>
                    <p class="text-gray-600 mt-1">
                        <i class="fas fa-id-card mr-1"></i> MFL Code: ${facility.mfl_code || 'N/A'} • 
                        <i class="fas fa-map-marker-alt mr-1"></i> ${facility.subcounty}, ${facility.county}
                    </p>
                    <p class="text-sm text-gray-500 mt-1">
                        <i class="fas fa-building mr-1"></i> Facility Type: ${facility.type}
                    </p>
                </div>
                <div class="text-right">
                    <div class="text-3xl font-bold text-blue-600">${summary.total_participants}</div>
                    <div class="text-sm text-gray-600">Total Participants</div>
                </div>
            </div>
        </div>
    `;
}

function createFacilitySummaryStats(summary) {
    return `
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white p-4 rounded-lg border text-center hover:shadow-md transition-shadow">
                <div class="text-2xl font-bold text-green-600">${summary.unique_users}</div>
                <div class="text-sm text-gray-600">Unique Learners</div>
            </div>
            <div class="bg-white p-4 rounded-lg border text-center hover:shadow-md transition-shadow">
                <div class="text-2xl font-bold text-blue-600">${summary.completed_trainings}</div>
                <div class="text-sm text-gray-600">Completed</div>
            </div>
            <div class="bg-white p-4 rounded-lg border text-center hover:shadow-md transition-shadow">
                <div class="text-2xl font-bold text-yellow-600">${summary.in_progress}</div>
                <div class="text-sm text-gray-600">In Progress</div>
            </div>
            <div class="bg-white p-4 rounded-lg border text-center hover:shadow-md transition-shadow">
                <div class="text-2xl font-bold text-purple-600">${summary.pass_rate}%</div>
                <div class="text-sm text-gray-600">Pass Rate</div>
            </div>
        </div>
    `;
}

function createFacilityInsights(insights) {
    return `
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
            <h5 class="font-semibold text-blue-900 mb-2">
                <i class="fas fa-lightbulb mr-1"></i> Facility Insights
            </h5>
            <ul class="space-y-1">
                ${insights.map(insight => `
                    <li class="text-sm text-blue-800 flex items-start">
                        <i class="fas fa-check-circle mr-2 mt-0.5 text-blue-600"></i>
                        <span>${insight}</span>
                    </li>
                `).join('')}
            </ul>
        </div>
    `;
}

function createParticipantsByTraining(participantsByTraining) {
    return `
        <div class="space-y-6">
            <h5 class="text-lg font-semibold text-gray-900 flex items-center">
                <i class="fas fa-users mr-2 text-gray-600"></i>
                Participants by Training Program
            </h5>
            ${Object.entries(participantsByTraining).map(([trainingTitle, trainingData]) => 
                createTrainingParticipantsSection(trainingTitle, trainingData)
            ).join('')}
        </div>
    `;
}

function createTrainingParticipantsSection(trainingTitle, trainingData) {
    return `
        <div class="bg-white border border-gray-200 rounded-lg p-6">
            <div class="flex items-center justify-between mb-4">
                <h6 class="text-lg font-semibold text-gray-900">${trainingTitle}</h6>
                <span class="bg-blue-100 text-blue-800 text-xs font-semibold px-3 py-1 rounded-full">
                    ${trainingData.participants.length} participants
                </span>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                ${trainingData.participants.map(participant => createFullParticipantCard(participant)).join('')}
            </div>
        </div>
    `;
}

function createFullParticipantCard(participant) {
    const statusClass = getStatusClass(participant.completion_status);
    const performanceClass = getPerformanceClass(participant.assessment_score);

    return `
        <div class="participant-card bg-white border border-gray-200 rounded-lg p-4 hover:shadow-md hover:border-blue-300 transition-all duration-200 cursor-pointer" 
             onclick="showParticipantProfile('${participant.id}')">
            <div class="flex items-start space-x-3">
                <div class="participant-avatar w-12 h-12 bg-gradient-to-br from-blue-500 to-blue-600 rounded-full flex items-center justify-center text-white font-semibold">
                    ${getInitials(participant.name)}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-900">${participant.name}</p>
                    <p class="text-xs text-gray-500">${participant.cadre || 'N/A'} • ${participant.department || 'N/A'}</p>
                    
                    <div class="flex items-center justify-between mt-2">
                        <span class="status-badge ${statusClass} px-2 py-1 rounded-full text-xs font-medium">
                            ${participant.completion_status || 'Not Started'}
                        </span>
                        ${participant.assessment_score ? `
                            <span class="performance-badge ${performanceClass} px-2 py-1 rounded-full text-xs font-medium">
                                ${participant.assessment_score}%
                            </span>
                        ` : ''}
                    </div>
                    
                    ${participant.completion_date ? `
                        <p class="text-xs text-gray-400 mt-1">
                            <i class="fas fa-calendar-check mr-1"></i>
                            Completed: ${participant.completion_date}
                        </p>
                    ` : ''}
                </div>
            </div>
        </div>
    `;
}

// =====================================================
// PARTICIPANT PROFILE MODAL
// =====================================================

function showParticipantProfile(userId) {
    showToast('Loading participant profile...', 'info');
    
    fetch(`/training/participant/${userId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            displayParticipantProfile(data);
        })
        .catch(error => {
            console.error('Error loading participant profile:', error);
            showToast('Error loading participant profile. Please try again.', 'error');
        });
}

function displayParticipantProfile(data) {
    const modal = document.getElementById(`participant-profile-modal-${widgetId}`);
    const title = document.getElementById(`participant-modal-title-${widgetId}`);
    const content = document.getElementById(`participant-modal-content-${widgetId}`);

    if (!modal || !title || !content) {
        console.error('Participant modal elements not found');
        return;
    }

    title.textContent = `${data.user.name} - Training Profile`;

    const html = `
        ${createParticipantHeader(data.user, data.training_summary)}
        ${createPerformanceSummary(data.training_summary, data.performance_metrics)}
        ${data.recommendations && data.recommendations.length > 0 ? createRecommendations(data.recommendations) : ''}
        ${createTrainingHistory(data.training_history)}
        ${createPerformanceAnalysis(data.performance_metrics)}
    `;

    content.innerHTML = html;
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function createParticipantHeader(user, trainingSummary) {
    const statusClass = getStatusClass(user.current_status);

    return `
        <div class="bg-gradient-to-r from-indigo-50 to-purple-50 rounded-lg p-6 mb-6">
            <div class="flex items-start space-x-6">
                <div class="w-20 h-20 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-full flex items-center justify-center text-white text-2xl font-bold shadow-lg">
                    ${getInitials(user.name)}
                </div>
                <div class="flex-1">
                    <h4 class="text-2xl font-bold text-gray-900">${user.name}</h4>
                    <p class="text-gray-600 mt-1">
                        <i class="fas fa-user-md mr-1"></i> ${user.cadre || 'N/A'} • 
                        <i class="fas fa-building mr-1"></i> ${user.department || 'N/A'}
                    </p>
                    <p class="text-sm text-gray-500 mt-1">
                        <i class="fas fa-hospital mr-1"></i> ${user.facility?.name} • 
                        <i class="fas fa-map-marker-alt mr-1"></i> ${user.facility?.county}
                    </p>
                    <div class="flex items-center space-x-4 mt-3">
                        <span class="status-badge ${statusClass} px-3 py-1 rounded-full text-xs font-medium">
                            <i class="fas fa-circle mr-1"></i>
                            ${user.current_status || 'Active'}
                        </span>
                        ${user.email ? `
                            <span class="text-sm text-gray-500">
                                <i class="fas fa-envelope mr-1"></i>${user.email}
                            </span>
                        ` : ''}
                        ${user.phone ? `
                            <span class="text-sm text-gray-500">
                                <i class="fas fa-phone mr-1"></i>${user.phone}
                            </span>
                        ` : ''}
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-3xl font-bold text-indigo-600">${trainingSummary.total_trainings}</div>
                    <div class="text-sm text-gray-600">Trainings Attended</div>
                </div>
            </div>
        </div>
    `;
}

function createPerformanceSummary(trainingSummary, performanceMetrics) {
    return `
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white p-4 rounded-lg border text-center hover:shadow-md transition-shadow">
                <div class="text-2xl font-bold text-green-600">${trainingSummary.completion_rate}%</div>
                <div class="text-sm text-gray-600">Completion Rate</div>
                <div class="w-full bg-gray-200 rounded-full h-2 mt-2">
                    <div class="bg-green-600 h-2 rounded-full" style="width: ${trainingSummary.completion_rate}%"></div>
                </div>
            </div>
            <div class="bg-white p-4 rounded-lg border text-center hover:shadow-md transition-shadow">
                <div class="text-2xl font-bold text-blue-600">${performanceMetrics.overall_score || 'N/A'}</div>
                <div class="text-sm text-gray-600">Average Score</div>
                ${performanceMetrics.overall_score ? `
                    <div class="w-full bg-gray-200 rounded-full h-2 mt-2">
                        <div class="bg-blue-600 h-2 rounded-full" style="width: ${performanceMetrics.overall_score}%"></div>
                    </div>
                ` : ''}
            </div>
            <div class="bg-white p-4 rounded-lg border text-center hover:shadow-md transition-shadow">
                <div class="text-2xl font-bold text-purple-600">${performanceMetrics.assessment_completion}%</div>
                <div class="text-sm text-gray-600">Assessment Complete</div>
                <div class="w-full bg-gray-200 rounded-full h-2 mt-2">
                    <div class="bg-purple-600 h-2 rounded-full" style="width: ${performanceMetrics.assessment_completion}%"></div>
                </div>
            </div>
            <div class="bg-white p-4 rounded-lg border text-center hover:shadow-md transition-shadow">
                <div class="text-2xl font-bold ${getTrendColor(performanceMetrics.trend)}">${performanceMetrics.trend}</div>
                <div class="text-sm text-gray-600">Performance Trend</div>
                <div class="mt-2">
                    <i class="fas fa-${getTrendIcon(performanceMetrics.trend)} ${getTrendColor(performanceMetrics.trend)}"></i>
                </div>
            </div>
        </div>
    `;
}

function createRecommendations(recommendations) {
    return `
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 mb-6">
            <h5 class="font-semibold text-yellow-900 mb-4 flex items-center">
                <i class="fas fa-lightbulb mr-2"></i> Personalized Recommendations
            </h5>
            <div class="space-y-4">
                ${recommendations.map(rec => createRecommendationCard(rec)).join('')}
            </div>
        </div>
    `;
}

function createRecommendationCard(rec) {
    const priorityColors = {
        urgent: { bg: 'bg-red-50', border: 'border-red-200', text: 'text-red-800', badge: 'bg-red-100 text-red-800' },
        high: { bg: 'bg-orange-50', border: 'border-orange-200', text: 'text-orange-800', badge: 'bg-orange-100 text-orange-800' },
        medium: { bg: 'bg-blue-50', border: 'border-blue-200', text: 'text-blue-800', badge: 'bg-blue-100 text-blue-800' },
        low: { bg: 'bg-gray-50', border: 'border-gray-200', text: 'text-gray-800', badge: 'bg-gray-100 text-gray-800' }
    };

    const colors = priorityColors[rec.priority] || priorityColors.medium;

    return `
        <div class="${colors.bg} ${colors.border} border rounded-lg p-4">
            <div class="flex items-start justify-between">
                <div class="flex-1">
                    <h6 class="font-medium ${colors.text} flex items-center">
                        <i class="fas fa-${getPriorityIcon(rec.priority)} mr-2"></i>
                        ${rec.title}
                    </h6>
                    <p class="text-sm ${colors.text} mt-1 opacity-90">${rec.description}</p>
                    <p class="text-xs ${colors.text} mt-2 font-medium">
                        <i class="fas fa-arrow-right mr-1"></i>
                        <strong>Action:</strong> ${rec.action}
                    </p>
                </div>
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium ${colors.badge} ml-4">
                    ${rec.priority.charAt(0).toUpperCase() + rec.priority.slice(1)} Priority
                </span>
            </div>
        </div>
    `;
}

function createTrainingHistory(trainingHistory) {
    return `
        <div class="bg-white rounded-lg border p-6 mb-6">
            <h5 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                <i class="fas fa-history mr-2 text-gray-600"></i>
                Training History Timeline
            </h5>
            <div class="space-y-4 max-h-96 overflow-y-auto">
                ${trainingHistory.length > 0 ? 
                    trainingHistory.map(training => createTimelineItem(training)).join('') :
                    '<div class="text-center text-gray-500 py-8">No training history available</div>'
                }
            </div>
        </div>
    `;
}

function createTimelineItem(training) {
    const statusClass = getStatusClass(training.completion_status);
    const performanceClass = getPerformanceClass(training.assessment_score);

    return `
        <div class="timeline-item flex items-start space-x-4 p-4 hover:bg-gray-50 rounded-lg transition-colors border-l-4 ${getBorderColor(training.training_type)}">
            <div class="w-10 h-10 ${getBgColor(training.training_type)} rounded-full flex items-center justify-center flex-shrink-0">
                <i class="fas fa-${training.training_type === 'global_training' ? 'globe' : 'hospital'} text-white"></i>
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <h6 class="text-sm font-medium text-gray-900">${training.training_title}</h6>
                        <p class="text-xs text-gray-500 mt-1">${training.programs}</p>
                        <p class="text-xs text-gray-400 mt-1 flex items-center">
                            <i class="fas fa-calendar mr-1"></i>
                            ${training.start_date} ${training.end_date ? `→ ${training.end_date}` : ''}
                        </p>
                    </div>
                    <div class="text-right ml-4">
                        <span class="status-badge ${statusClass} px-2 py-1 rounded-full text-xs font-medium">
                            ${training.completion_status || 'Not Started'}
                        </span>
                        ${training.assessment_score ? `
                            <div class="mt-1">
                                <span class="performance-badge ${performanceClass} px-2 py-1 rounded-full text-xs font-medium">
                                    ${training.assessment_score}%
                                </span>
                            </div>
                        ` : ''}
                    </div>
                </div>
                
                ${training.assessment_details && training.assessment_details.length > 0 ? `
                    <div class="mt-3 space-y-1">
                        <p class="text-xs font-medium text-gray-700">Assessment Breakdown:</p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                            ${training.assessment_details.map(assessment => createAssessmentDetail(assessment)).join('')}
                        </div>
                    </div>
                ` : ''}
            </div>
        </div>
    `;
}

function createAssessmentDetail(assessment) {
    const resultClass = assessment.result === 'pass' ? 'text-green-600 bg-green-50' : 'text-red-600 bg-red-50';

    return `
        <div class="text-xs ${resultClass} p-2 rounded border">
            <span class="font-medium">${assessment.category}:</span>
            <span class="ml-1">
                ${assessment.result} ${assessment.score ? `(${assessment.score}%)` : ''}
            </span>
        </div>
    `;
}

function createPerformanceAnalysis(performanceMetrics) {
    if (!performanceMetrics.strengths?.length && !performanceMetrics.improvement_areas?.length) {
        return '';
    }

    return `
        <div class="grid md:grid-cols-2 gap-6">
            ${performanceMetrics.strengths?.length > 0 ? `
                <div class="bg-green-50 border border-green-200 rounded-lg p-6">
                    <h6 class="font-semibold text-green-900 mb-3 flex items-center">
                        <i class="fas fa-trophy mr-2"></i> Strengths & Achievements
                    </h6>
                    <ul class="space-y-2">
                        ${performanceMetrics.strengths.map(strength => `
                            <li class="text-sm text-green-800 flex items-start">
                                <i class="fas fa-check-circle mr-2 mt-0.5 text-green-600"></i>
                                <span>${strength}</span>
                            </li>
                        `).join('')}
                    </ul>
                </div>
            ` : ''}
            
            ${performanceMetrics.improvement_areas?.length > 0 ? `
                <div class="bg-orange-50 border border-orange-200 rounded-lg p-6">
                    <h6 class="font-semibold text-orange-900 mb-3 flex items-center">
                        <i class="fas fa-target mr-2"></i> Areas for Development
                    </h6>
                    <ul class="space-y-2">
                        ${performanceMetrics.improvement_areas.map(area => `
                            <li class="text-sm text-orange-800 flex items-start">
                                <i class="fas fa-arrow-circle-right mr-2 mt-0.5 text-orange-600"></i>
                                <span>${area}</span>
                            </li>
                        `).join('')}
                    </ul>
                </div>
            ` : ''}
        </div>
    `;
}

// =====================================================
// MODAL CONTROL FUNCTIONS
// =====================================================

function closeCountyModal(widgetId) {
    const modal = document.getElementById(`county-details-modal-${widgetId}`);
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
}

function closeFacilityModal(widgetId) {
    const modal = document.getElementById(`facility-participants-modal-${widgetId}`);
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
}

function closeParticipantModal(widgetId) {
    const modal = document.getElementById(`participant-profile-modal-${widgetId}`);
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
}

// =====================================================
// TABLE FUNCTIONALITY
// =====================================================

function toggleTableView(widgetId) {
    const table = document.getElementById(`summary-table-${widgetId}`);
    const button = event.target.closest('button');

    if (!table || !button) return;

    if (table.classList.contains('hidden')) {
        table.classList.remove('hidden');
        button.innerHTML = '<i class="fas fa-eye-slash mr-2"></i>Hide Table View';
        table.scrollIntoView({ behavior: 'smooth' });
    } else {
        table.classList.add('hidden');
        button.innerHTML = '<i class="fas fa-table mr-2"></i>Show Table View';
    }
}

function filterCounties(widgetId) {
    const searchInput = document.getElementById(`county-search-${widgetId}`);
    const tableBody = document.getElementById(`county-table-body-${widgetId}`);
    
    if (!searchInput || !tableBody) return;

    const searchTerm = searchInput.value.toLowerCase();
    const rows = tableBody.querySelectorAll('tr');
    let visibleCount = 0;

    rows.forEach(row => {
        const countyName = row.getAttribute('data-county')?.toLowerCase() || '';
        if (countyName.includes(searchTerm)) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });

    showToast(`Found ${visibleCount} matching counties`, 'info');
}

function sortTable(widgetId) {
    const select = document.getElementById(`sort-select-${widgetId}`);
    const tableBody = document.getElementById(`county-table-body-${widgetId}`);
    
    if (!select || !tableBody) return;

    const sortBy = select.value;
    const rows = Array.from(tableBody.querySelectorAll('tr'));

    rows.sort((a, b) => {
        let aValue, bValue;

        try {
            switch (sortBy) {
                case 'name':
                    aValue = a.getAttribute('data-county') || '';
                    bValue = b.getAttribute('data-county') || '';
                    return aValue.localeCompare(bValue);

                case 'trainings':
                    aValue = parseInt(a.cells[1].querySelector('.text-lg')?.textContent || '0');
                    bValue = parseInt(b.cells[1].querySelector('.text-lg')?.textContent || '0');
                    return bValue - aValue;

                case 'participants':
                    aValue = parseInt(a.cells[2].querySelector('.text-lg')?.textContent?.replace(/,/g, '') || '0');
                    bValue = parseInt(b.cells[2].querySelector('.text-lg')?.textContent?.replace(/,/g, '') || '0');
                    return bValue - aValue;

                case 'facilities':
                    aValue = parseInt(a.cells[3].querySelector('.text-lg')?.textContent || '0');
                    bValue = parseInt(b.cells[3].querySelector('.text-lg')?.textContent || '0');
                    return bValue - aValue;

                case 'intensity':
                default:
                    aValue = parseInt(a.cells[4].querySelector('span')?.textContent || '0');
                    bValue = parseInt(b.cells[4].querySelector('span')?.textContent || '0');
                    return bValue - aValue;
            }
        } catch (error) {
            console.error('Error sorting table:', error);
            return 0;
        }
    });

    rows.forEach(row => tableBody.appendChild(row));
    showToast(`Table sorted by ${sortBy}`, 'info');
}

function exportTableData(widgetId) {
    const tableBody = document.getElementById(`county-table-body-${widgetId}`);
    if (!tableBody) return;

    const rows = tableBody.querySelectorAll('tr');
    let csvContent = 'County,Trainings,Participants,Facilities,Intensity,Coverage\n';

    rows.forEach(row => {
        if (row.style.display !== 'none') {
            try {
                const cells = row.cells;
                const county = cells[0].querySelector('.text-sm.font-medium')?.textContent || '';
                const trainings = cells[1].querySelector('.text-lg')?.textContent || '0';
                const participants = cells[2].querySelector('.text-lg')?.textContent || '0';
                const facilities = cells[3].querySelector('.text-lg')?.textContent || '0';
                const intensity = cells[4].querySelector('span')?.textContent || '0';
                const coverage = cells[5].querySelector('span')?.textContent || '';

                csvContent += `"${county}","${trainings}","${participants}","${facilities}","${intensity}","${coverage}"\n`;
            } catch (error) {
                console.error('Error processing row:', error);
            }
        }
    });

    try {
        const blob = new Blob([csvContent], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `kenya_training_summary_${trainingType}_${new Date().toISOString().split('T')[0]}.csv`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);

        showToast('Data exported successfully!', 'success');
    } catch (error) {
        console.error('Error exporting data:', error);
        showToast('Error exporting data. Please try again.', 'error');
    }
}

function highlightCountyOnMap(widgetId, countyName) {
    if (!geoJsonLayer) return;

    geoJsonLayer.eachLayer(function(layer) {
        const layerCountyName = layer.feature.properties.COUNTY.toLowerCase();
        if (layerCountyName === countyName.toLowerCase()) {
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

    showToast(`Located ${countyName} County on map`, 'info');
}

function refreshInsights(widgetId) {
    showToast('Refreshing AI insights...', 'info');
    
    // Simulate refresh delay
    setTimeout(() => {
        showToast('AI insights updated!', 'success');
    }, 2000);
}

// =====================================================
// UTILITY FUNCTIONS
// =====================================================

function getInitials(name) {
    if (!name) return '??';
    return name.split(' ')
        .map(n => n[0])
        .join('')
        .substring(0, 2)
        .toUpperCase();
}

function getStatusClass(status) {
    const statusClasses = {
        'completed': 'bg-green-100 text-green-800',
        'in_progress': 'bg-yellow-100 text-yellow-800',
        'in-progress': 'bg-yellow-100 text-yellow-800',
        'not_started': 'bg-gray-100 text-gray-800',
        'not-started': 'bg-gray-100 text-gray-800',
        'active': 'bg-green-100 text-green-800',
        'inactive': 'bg-red-100 text-red-800'
    };
    
    return statusClasses[status?.toLowerCase().replace(' ', '_')] || 'bg-gray-100 text-gray-800';
}

function getPerformanceClass(score) {
    if (!score) return 'bg-gray-100 text-gray-800';
    
    if (score >= 80) return 'bg-green-100 text-green-800';
    if (score >= 70) return 'bg-blue-100 text-blue-800';
    return 'bg-red-100 text-red-800';
}

function getTrendColor(trend) {
    const trendColors = {
        'Improving': 'text-green-600',
        'Stable': 'text-blue-600',
        'Declining': 'text-red-600'
    };
    
    return trendColors[trend] || 'text-gray-600';
}

function getTrendIcon(trend) {
    const trendIcons = {
        'Improving': 'arrow-up',
        'Stable': 'minus',
        'Declining': 'arrow-down'
    };
    
    return trendIcons[trend] || 'question';
}

function getPriorityIcon(priority) {
    const priorityIcons = {
        'urgent': 'exclamation-triangle',
        'high': 'exclamation-circle',
        'medium': 'info-circle',
        'low': 'lightbulb'
    };
    
    return priorityIcons[priority] || 'info-circle';
}

function getBorderColor(trainingType) {
    return trainingType === 'global_training' ? 'border-blue-400' : 'border-green-400';
}

function getBgColor(trainingType) {
    return trainingType === 'global_training' ? 'bg-blue-500' : 'bg-green-500';
}

// =====================================================
// TOAST NOTIFICATION SYSTEM
// =====================================================

function showToast(message, type = 'info') {
    const container = document.getElementById('toast-container');
    if (!container) {
        console.error('Toast container not found');
        return;
    }

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

    toast.className = `${colors[type]} text-white px-4 py-3 rounded-lg shadow-lg flex items-center space-x-2 transform transition-all duration-300 ease-in-out mb-2`;
    toast.innerHTML = `
        <span>${icons[type]}</span>
        <span>${message}</span>
        <button onclick="this.parentElement.remove()" class="ml-2 text-white hover:text-gray-200">
            <i class="fas fa-times"></i>
        </button>
    `;
    toast.style.transform = 'translateX(100%)';

    container.appendChild(toast);

    // Animate in
    setTimeout(() => {
        toast.style.transform = 'translateX(0)';
    }, 100);

    // Auto remove after 5 seconds
    setTimeout(() => {
        if (toast.parentNode) {
            toast.style.transform = 'translateX(100%)';
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.remove();
                }
            }, 300);
        }
    }, 5000);
}

// =====================================================
// EVENT LISTENERS
// =====================================================

// Close modals on outside click
document.addEventListener('click', function(event) {
    const modals = document.querySelectorAll(`[id$="-modal-${widgetId}"]`);
    modals.forEach(modal => {
        if (event.target === modal) {
            modal.classList.add('hidden');
            document.body.style.overflow = 'auto';
        }
    });
});

// Close modals on Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const modals = document.querySelectorAll(`[id$="-modal-${widgetId}"]`);
        modals.forEach(modal => {
            if (!modal.classList.contains('hidden')) {
                modal.classList.add('hidden');
                document.body.style.overflow = 'auto';
            }
        });
    }
});

// =====================================================
// INITIALIZATION
// =====================================================

// Initialize map when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    const mapData = @json($mapData);
    const geojsonData = @json(json_decode(file_get_contents(public_path('kenyan-counties.geojson')), true));

    const mapContainer = document.getElementById(`kenya-heatmap-${widgetId}`);
    if (mapContainer) {
        mapContainer.style.zIndex = '1';
        mapContainer.style.position = 'relative';
    }

    // Initialize the map
    setTimeout(() => {
        initKenyaMap(widgetId, {
            height: 500,
            mapData: mapData,
            geojson: geojsonData
        });
    }, 100);
});

// Handle Livewire navigation (if using Livewire)
document.addEventListener('livewire:navigated', function() {
    const mapData = @json($mapData);
    const geojsonData = @json(json_decode(file_get_contents(public_path('kenyan-counties.geojson')), true));
    
    setTimeout(() => {
        initKenyaMap(widgetId, {
            height: 500,
            mapData: mapData,
            geojson: geojsonData
        });
    }, 100);
});

// Console log for debugging
console.log('Kenya Training Heatmap Scripts Loaded Successfully', {
    widgetId: widgetId,
    trainingType: trainingType,
    timestamp: new Date().toISOString()
}); 