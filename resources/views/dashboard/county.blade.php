<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>County Coverage Details</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script src="https://unpkg.com/aos@next/dist/aos.js"></script>
        <link rel="stylesheet" href="https://unpkg.com/aos@next/dist/aos.css" />
        <style>
            .recommendation-high {
                border-left: 5px solid #EF4444;
                background-color: #FEF2F2;
            }
            .recommendation-medium {
                border-left: 5px solid #F59E0B;
                background-color: #FFFBEB;
            }
            .recommendation-low {
                border-left: 5px solid #10B981;
                background-color: #ECFDF5;
            }

            .chart-container {
                position: relative;
                height: 300px;
                width: 100%;
            }
        </style>
    </head>
    <body class="bg-gray-50">
        <!-- Header -->
    <header class="bg-white shadow-lg border-b-2 border-blue-500">
        <div class="max-w-7xl mx-auto px-6 py-4">
            <div class="flex items-center justify-between">
                <div>
                    <nav class="text-sm breadcrumbs mb-2">
                        <a href="/dashboard" class="text-blue-600 hover:text-blue-800">Dashboard</a>
                        <span class="mx-2 text-gray-400">></span>
                        <span class="text-gray-600" id="county-name-breadcrumb">Loading...</span>
                    </nav>
                    <h1 class="text-3xl font-bold text-gray-800" id="county-title">County Coverage Analysis</h1>
                    <p class="text-gray-600 mt-1" id="county-subtitle">Detailed coverage breakdown and recommendations</p>
                </div>

                    <!-- Training Type Display -->
                <div class="text-right">
                    <div class="text-sm text-gray-500">Analyzing</div>
                    <div class="text-lg font-semibold text-blue-600" id="training-type-display">Loading...</div>
                </div>
            </div>
        </div>
    </header>

        <!-- County Overview -->
    <div class="max-w-7xl mx-auto px-6 py-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8" data-aos="fade-up">
            <div class="bg-white rounded-xl shadow-lg p-6 text-center">
                <div class="text-3xl font-bold text-blue-600" id="total-facilities">--</div>
                <div class="text-gray-600">Total Facilities</div>
            </div>

            <div class="bg-white rounded-xl shadow-lg p-6 text-center">
                <div class="text-3xl font-bold text-green-600" id="covered-facilities">--</div>
                <div class="text-gray-600">Covered Facilities</div>
            </div>

            <div class="bg-white rounded-xl shadow-lg p-6 text-center">
                <div class="text-3xl font-bold text-red-600" id="uncovered-facilities">--</div>
                <div class="text-gray-600">Uncovered Facilities</div>
            </div>

            <div class="bg-white rounded-xl shadow-lg p-6 text-center">
                <div class="text-3xl font-bold text-purple-600" id="total-participants">--</div>
                <div class="text-gray-600" id="participants-label">Participants</div>
            </div>
        </div>

            <!-- Recommendations Section -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-8" data-aos="fade-up" data-aos-delay="100">
            <h2 class="text-xl font-bold text-gray-800 mb-6">Strategic Recommendations</h2>
            <div id="recommendations-container" class="space-y-4">
                <div class="flex justify-center py-8">
                    <div class="loading-spinner"></div>
                </div>
            </div>
        </div>

            <!-- Analysis Charts -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">

                <!-- Facility Type Analysis -->
            <div class="bg-white rounded-xl shadow-lg p-6" data-aos="fade-right">
                <h3 class="text-lg font-bold text-gray-800 mb-4">Coverage by Facility Type</h3>
                <div class="chart-container">
                    <canvas id="facility-type-chart"></canvas>
                </div>
                <div id="facility-type-details" class="mt-4 space-y-2">
                        <!-- Populated by JavaScript -->
                </div>
            </div>

                <!-- Department Analysis -->
            <div class="bg-white rounded-xl shadow-lg p-6" data-aos="fade-left">
                <h3 class="text-lg font-bold text-gray-800 mb-4">Coverage by Department</h3>
                <div class="chart-container">
                    <canvas id="department-chart"></canvas>
                </div>
                <div id="department-details" class="mt-4 space-y-2">
                        <!-- Populated by JavaScript -->
                </div>
            </div>
        </div>

            <!-- Cadre Analysis -->
        <div class="bg-white rounded-xl shadow-lg p-6 mt-8" data-aos="fade-up">
            <h3 class="text-lg font-bold text-gray-800 mb-4">Coverage by Cadre</h3>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="chart-container">
                    <canvas id="cadre-chart"></canvas>
                </div>
                <div id="cadre-details" class="space-y-2">
                        <!-- Populated by JavaScript -->
                </div>
            </div>
        </div>

            <!-- Facility Lists -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mt-8">
                <!-- Covered Facilities -->
            <div class="bg-white rounded-xl shadow-lg p-6" data-aos="fade-right">
                <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                    <span class="w-3 h-3 bg-green-500 rounded-full mr-2"></span>
                        Covered Facilities
                </h3>
                <div id="covered-facilities-list" class="space-y-2 max-h-96 overflow-y-auto">
                        <!-- Populated by JavaScript -->
                </div>
            </div>

                <!-- Uncovered Facilities -->
            <div class="bg-white rounded-xl shadow-lg p-6" data-aos="fade-left">
                <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                    <span class="w-3 h-3 bg-red-500 rounded-full mr-2"></span>
                        Uncovered Facilities
                </h3>
                <div id="uncovered-facilities-list" class="space-y-2 max-h-96 overflow-y-auto">
                        <!-- Populated by JavaScript -->
                </div>
            </div>
        </div>
    </div>

    <script>
            class CountyDashboard {
                constructor() {
        this.countyId = {{ $county }};
                    this.trainingType = new URLSearchParams(window.location.search).get('type') || 'global_training';
                    this.year = new URLSearchParams(window.location.search).get('year') || new Date().getFullYear();
                    this.charts = {};
                
                    this.init();
                }
            
                async init() {
                    AOS.init({ duration: 800, once: true });
                
                    this.updateLabels();
                    await this.loadCountyData();
                }
            
                updateLabels() {
                    const isGlobal = this.trainingType === 'global_training';
                    document.getElementById('training-type-display').textContent = 
                        isGlobal ? 'Global Training Programs' : 'Facility Mentorships';
                    document.getElementById('participants-label').textContent = 
                        isGlobal ? 'Participants' : 'Mentees';
                }
            
                async loadCountyData() {
                    try {
                        const response = await fetch(`/dashboard/api/county/${this.countyId}?type=${this.trainingType}&year=${this.year}`);
                        const data = await response.json();
                    
                        this.renderCountyOverview(data);
                        this.renderRecommendations(data.recommendations);
                        this.renderFacilityTypeAnalysis(data.facility_type_analysis);
                        this.renderDepartmentAnalysis(data.department_analysis);
                        this.renderCadreAnalysis(data.cadre_analysis);
                        this.renderFacilityLists(data.covered_facilities, data.uncovered_facilities);
                    
                    } catch (error) {
                        console.error('Error loading county data:', error);
                    }
                }
            
                renderCountyOverview(data) {
                    document.getElementById('county-name-breadcrumb').textContent = data.county.name;
                    document.getElementById('county-title').textContent = `${data.county.name} County Coverage Analysis`;
                
                    document.getElementById('total-facilities').textContent = data.coverage.total_facilities;
                    document.getElementById('covered-facilities').textContent = data.coverage.covered_facilities;
                    document.getElementById('uncovered-facilities').textContent = data.coverage.uncovered_facilities;
                    document.getElementById('total-participants').textContent = data.coverage.participant_count;
                }
            
                renderRecommendations(recommendations) {
                    const container = document.getElementById('recommendations-container');
                
                    if (!recommendations || recommendations.length === 0) {
                        container.innerHTML = '<div class="text-gray-500 text-center py-4">No specific recommendations at this time</div>';
                        return;
                    }
                
                    container.innerHTML = recommendations.map(rec => {
                        const priorityClass = `recommendation-${rec.priority}`;
                        const priorityIcon = rec.priority === 'high' ? '🔴' : rec.priority === 'medium' ? '🟡' : '🟢';
                    
                        return `
        <div class="${priorityClass} rounded-lg p-4">
            <div class="flex items-start space-x-3">
                <span class="text-xl">${priorityIcon}</span>
                <div class="flex-1">
                    <h4 class="font-medium text-gray-800 mb-1">${rec.title}</h4>
                    <p class="text-gray-600 text-sm mb-2">${rec.description}</p>
                    <div class="bg-white bg-opacity-50 rounded p-2">
                        <div class="text-xs font-medium text-gray-700 mb-1">Recommended Action:</div>
                        <div class="text-sm text-gray-800">${rec.action}</div>
                    </div>
                </div>
            </div>
        </div>
                        `;
                    }).join('');
                }
            
                renderFacilityTypeAnalysis(data) {
                    // Create chart
                    const ctx = document.getElementById('facility-type-chart').getContext('2d');
                    this.charts.facilityType = new Chart(ctx, {
                        type: 'doughnut',
                        data: {
                            labels: data.map(item => item.name),
                            datasets: [{
                                data: data.map(item => item.coverage_percentage),
                                backgroundColor: ['#10B981', '#F59E0B', '#EF4444', '#3B82F6', '#8B5CF6', '#F97316']
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { position: 'bottom' }
                            }
                        }
                    });
                
                    // Create details
                    const detailsContainer = document.getElementById('facility-type-details');
                    detailsContainer.innerHTML = data.map(item => `
        <div class="flex items-center justify-between p-2 bg-gray-50 rounded">
            <div>
                <div class="font-medium text-sm">${item.name}</div>
                <div class="text-xs text-gray-600">${item.covered}/${item.total} facilities</div>
            </div>
            <div class="text-right">
                <div class="font-bold text-sm ${this.getCoverageColor(item.coverage_percentage)}">${item.coverage_percentage}%</div>
                ${item.gap > 0 ? `<div class="text-xs text-red-600">${item.gap} uncovered</div>` : ''}
            </div>
        </div>
                    `).join('');
                }
            
                getCoverageColor(percentage) {
                    if (percentage >= 80) return 'text-green-600';
                    if (percentage >= 50) return 'text-yellow-600';
                    return 'text-red-600';
                }
            
                renderDepartmentAnalysis(data) {
                    const ctx = document.getElementById('department-chart').getContext('2d');
                    this.charts.department = new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: data.map(item => item.name),
                            datasets: [{
                                label: 'Coverage %',
                                data: data.map(item => item.coverage_percentage),
                                backgroundColor: data.map(item => this.getBarColor(item.coverage_percentage))
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: { beginAtZero: true, max: 100 }
                            }
                        }
                    });
                
                    const detailsContainer = document.getElementById('department-details');
                    detailsContainer.innerHTML = data.slice(0, 8).map(item => `
        <div class="flex items-center justify-between p-2 bg-gray-50 rounded">
            <div>
                <div class="font-medium text-sm">${item.name}</div>
                <div class="text-xs text-gray-600">${item.trained}/${item.total} trained</div>
            </div>
            <div class="font-bold text-sm ${this.getCoverageColor(item.coverage_percentage)}">${item.coverage_percentage}%</div>
        </div>
                    `).join('');
                }
            
                renderCadreAnalysis(data) {
                    const ctx = document.getElementById('cadre-chart').getContext('2d');
                    this.charts.cadre = new Chart(ctx, {
                        type: 'horizontalBar',
                        data: {
                            labels: data.map(item => item.name),
                            datasets: [{
                                label: 'Trained',
                                data: data.map(item => item.trained),
                                backgroundColor: '#10B981'
                            }, {
                                label: 'Untrained',
                                data: data.map(item => item.untrained),
                                backgroundColor: '#EF4444'
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                x: { stacked: true },
                                y: { stacked: true }
                            }
                        }
                    });
                
                    const detailsContainer = document.getElementById('cadre-details');
                    detailsContainer.innerHTML = data.slice(0, 8).map(item => `
        <div class="flex items-center justify-between p-2 bg-gray-50 rounded">
            <div>
                <div class="font-medium text-sm">${item.name}</div>
                <div class="text-xs text-gray-600">${item.trained}/${item.total} staff</div>
            </div>
            <div class="font-bold text-sm ${this.getCoverageColor(item.coverage_percentage)}">${item.coverage_percentage}%</div>
        </div>
                    `).join('');
                }
            
                getBarColor(percentage) {
                    if (percentage >= 80) return '#10B981';
                    if (percentage >= 50) return '#F59E0B';
                    return '#EF4444';
                }
            
                renderFacilityLists(coveredFacilities, uncoveredFacilities) {
                    // Covered facilities
                    const coveredContainer = document.getElementById('covered-facilities-list');
                    if (coveredFacilities.length === 0) {
                        coveredContainer.innerHTML = '<div class="text-gray-500 text-center py-4">No facilities covered yet</div>';
                    } else {
                        coveredContainer.innerHTML = coveredFacilities.map(facility => `
        <div class="p-3 bg-green-50 border border-green-200 rounded-lg">
            <div class="flex items-center justify-between">
                <div>
                    <div class="font-medium text-green-800">${facility.name}</div>
                    <div class="text-sm text-green-600">${facility.type} • ${facility.subcounty}</div>
                </div>
                <div class="text-xs text-green-600">
                                        ${facility.mfl_code ? `MFL: ${facility.mfl_code}` : 'No MFL'}
                </div>
            </div>
        </div>
                        `).join('');
                    }
                
                    // Uncovered facilities
                    const uncoveredContainer = document.getElementById('uncovered-facilities-list');
                    if (uncoveredFacilities.length === 0) {
                        uncoveredContainer.innerHTML = '<div class="text-gray-500 text-center py-4">All facilities covered!</div>';
                    } else {
                        uncoveredContainer.innerHTML = uncoveredFacilities.map(facility => `
        <div class="p-3 bg-red-50 border border-red-200 rounded-lg">
            <div class="flex items-center justify-between">
                <div>
                    <div class="font-medium text-red-800">${facility.name}</div>
                    <div class="text-sm text-red-600">${facility.type} • ${facility.subcounty}</div>
                </div>
                <div class="text-xs text-red-600">
                                        ${facility.mfl_code ? `MFL: ${facility.mfl_code}` : 'No MFL'}
                </div>
            </div>
        </div>
                        `).join('');
                    }
                }
            }
        
            // Initialize the dashboard
            const countyDashboard = new CountyDashboard();
    </script>

    <style>
            .loading-spinner {
                border: 3px solid #f3f3f3;
                border-top: 3px solid #3498db;
                border-radius: 50%;
                width: 40px;
                height: 40px;
                animation: spin 1s linear infinite;
            }

            @keyframes spin {
                0% {
                    transform: rotate(0deg);
                }
                100% {
                    transform: rotate(360deg);
                }
            }
    </style>
</body>
</html>