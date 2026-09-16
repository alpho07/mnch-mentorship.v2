
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Healthcare Training & Mentorship Dashboard</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>      
        <script src="https://unpkg.com/aos@next/dist/aos.js"></script>
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
        <link rel="stylesheet" href="https://unpkg.com/aos@next/dist/aos.css" />
        <style>
            .metric-card {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                transition: transform 0.3s ease, box-shadow 0.3s ease;
            }
            .metric-card:hover {
                transform: translateY(-5px);
                box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            }
            .coverage-high {
                background-color: #10B981;
            }
            .coverage-medium {
                background-color: #F59E0B;
            }
            .coverage-low {
                background-color: #EF4444;
            }
            .coverage-none {
                background-color: #6B7280;
            }

            .county-path {
                cursor: pointer;
                transition: all 0.3s ease;
            }
            .county-path:hover {
                transform: scale(1.02);
                filter: brightness(1.1);
            }

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

            .chart-container {
                position: relative;
                height: 400px;
                width: 100%;
            }

            .insight-card {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                border-left: 5px solid #3B82F6;
            }
        </style>
    </head>
    <body class="bg-gray-50 font-sans">
        <!-- Header -->
    <header class="bg-white shadow-lg border-b-2 border-blue-500">
        <div class="max-w-7xl mx-auto px-6 py-4">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800">Healthcare Trainings & Mentorships Dashboard</h1>
                    <p class="text-gray-600 mt-1">Coverage insights and analytics for Kenya's healthcare workforce development</p>
                    </div>

                    <!-- Training Type Toggle -->
                    <div class="bg-gray-100 p-1 rounded-lg">
                        <button id="global-training-btn" class="px-6 py-2 rounded-md text-sm font-medium transition-colors bg-blue-600 text-white">
                            MOH Trainings
                        </button>
                        <button id="mentorship-btn" class="px-6 py-2 rounded-md text-sm font-medium transition-colors text-gray-700 hover:text-gray-900">
                            Facility Mentorships
                        </button>
                    </div>
                </div>
            </div>
        </header>

        <!-- Year Filter -->
        <div class="max-w-7xl mx-auto px-6 py-4">
            <div class="bg-white rounded-lg shadow p-4 mb-6">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-800">Filter by Year</h3>
                    <select id="year-filter" class="px-4 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <!-- Options populated by JavaScript -->
                    </select>
                </div>
            </div>
        </div>

        <!-- Overview Metrics -->
        <div class="max-w-7xl mx-auto px-6 mb-8">
            <div class="grid grid-cols-1 md:grid-cols-5 gap-6" data-aos="fade-up">
                <div class="metric-card rounded-xl p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-blue-100">Counties Covered</p>
                            <p class="text-3xl font-bold" id="counties-covered">--</p>
                            <p class="text-sm text-blue-100">of <span id="counties-total">--</span> counties</p>
                        </div>
                        <div class="text-4xl text-blue-200">🗺️</div>
                    </div>
                </div>

                <div class="metric-card rounded-xl p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-blue-100">Facilities Reached</p>
                            <p class="text-3xl font-bold" id="facilities-covered">--</p>
                            <p class="text-sm text-blue-100">of <span id="facilities-total">--</span> facilities</p>
                        </div>
                        <div class="text-4xl text-blue-200">🏥</div>
                    </div>
                </div>

                <div class="metric-card rounded-xl p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-blue-100" id="programs-label">Training Programs</p>
                            <p class="text-3xl font-bold" id="unique-programs">--</p>
                            <p class="text-sm text-blue-100">in <span id="current-year">--</span></p>
                        </div>
                        <div class="text-4xl text-blue-200">📚</div>
                    </div>
                </div>

                <div class="metric-card rounded-xl p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-blue-100" id="participants-label">Participants</p>
                            <p class="text-3xl font-bold" id="year-participants">--</p>
                            <p class="text-sm text-blue-100">in <span id="current-year-2">--</span></p>
                        </div>
                        <div class="text-4xl text-blue-200">👥</div>
                    </div>
                </div>

                <div class="metric-card rounded-xl p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-blue-100">Total Reached</p>
                            <p class="text-3xl font-bold" id="total-participants">--</p>
                            <p class="text-sm text-blue-100">all time</p>
                        </div>
                        <div class="text-4xl text-blue-200">🎯</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="max-w-7xl mx-auto px-6 grid grid-cols-1 lg:grid-cols-3 gap-8">

            <!-- Kenya Map -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-xl shadow-lg p-6" data-aos="fade-right">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-xl font-bold text-gray-800">Coverage Map - Kenya Counties</h2>
                        <div class="flex items-center space-x-4 text-sm">
                            <div class="flex items-center">
                                <div class="w-4 h-4 coverage-high rounded mr-2"></div>
                                <span>80-100%</span>
                            </div>
                            <div class="flex items-center">
                                <div class="w-4 h-4 coverage-medium rounded mr-2"></div>
                                <span>40-79%</span>
                            </div>
                            <div class="flex items-center">
                                <div class="w-4 h-4 coverage-low rounded mr-2"></div>
                                <span>1-39%</span>
                            </div>
                            <div class="flex items-center">
                                <div class="w-4 h-4 coverage-none rounded mr-2"></div>
                                <span>0%</span>
                            </div>
                        </div>
                    </div>

                    <!-- Map Container -->
                    <div id="kenya-map" class="h-96 bg-gray-100 rounded-lg flex items-center justify-center">
                        <div class="loading-spinner"></div>
                    </div>

                    <!-- Map Tooltip -->
                    <div id="map-tooltip" class="absolute bg-black text-white px-3 py-2 rounded-lg text-sm pointer-events-none opacity-0 transition-opacity z-50">
                        <div id="tooltip-content"></div>
                    </div>
                </div>
            </div>

            <!-- Insights Sidebar -->
            <div class="space-y-6">

                <!-- Key Insights -->
                <div class="bg-white rounded-xl shadow-lg p-6" data-aos="fade-left">
                    <h3 class="text-lg font-bold text-gray-800 mb-4">Key Insights</h3>
                    <div id="insights-container" class="space-y-3">
                        <div class="loading-spinner mx-auto"></div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="bg-white rounded-xl shadow-lg p-6" data-aos="fade-left" data-aos-delay="100">
                    <h3 class="text-lg font-bold text-gray-800 mb-4">Quick Actions</h3>
                    <div class="space-y-3">
                        <button id="show-uncovered-counties" class="w-full px-4 py-3 bg-red-50 text-red-700 rounded-lg hover:bg-red-100 transition-colors text-left">
                            <div class="font-medium">View Uncovered Counties</div>
                            <div class="text-sm text-red-600">Counties with 0% coverage</div>
                        </button>

                        <button id="show-facility-gaps" class="w-full px-4 py-3 bg-yellow-50 text-yellow-700 rounded-lg hover:bg-yellow-100 transition-colors text-left">
                            <div class="font-medium">Identify Facility Gaps</div>
                            <div class="text-sm text-yellow-600">By facility type and region</div>
                        </button>

                        <button id="show-department-analysis" class="w-full px-4 py-3 bg-blue-50 text-blue-700 rounded-lg hover:bg-blue-100 transition-colors text-left">
                            <div class="font-medium">Department Analysis</div>
                            <div class="text-sm text-blue-600">Coverage by healthcare department</div>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Analysis Sections (Initially Hidden) -->
        <div class="max-w-7xl mx-auto px-6 mt-8 space-y-8">

            <!-- Facility Type Coverage -->
            <div id="facility-type-section" class="bg-white rounded-xl shadow-lg p-6 hidden" data-aos="fade-up">
                <h3 class="text-xl font-bold text-gray-800 mb-6">Coverage by Facility Type</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="chart-container">
                        <canvas id="facility-type-chart"></canvas>
                    </div>
                    <div id="facility-type-insights" class="space-y-4">
                        <div class="loading-spinner mx-auto"></div>
                    </div>
                </div>
            </div>

            <!-- Department Coverage -->
            <div id="department-section" class="bg-white rounded-xl shadow-lg p-6 hidden" data-aos="fade-up">
                <h3 class="text-xl font-bold text-gray-800 mb-6">Coverage by Department</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="chart-container">
                        <canvas id="department-chart"></canvas>
                    </div>
                    <div id="department-insights" class="space-y-4">
                        <div class="loading-spinner mx-auto"></div>
                    </div>
                </div>
            </div>

            <!-- Cadre Coverage -->
            <div id="cadre-section" class="bg-white rounded-xl shadow-lg p-6 hidden" data-aos="fade-up">
                <h3 class="text-xl font-bold text-gray-800 mb-6">Coverage by Cadre</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="chart-container">
                        <canvas id="cadre-chart"></canvas>
                    </div>
                    <div id="cadre-insights" class="space-y-4">
                        <div class="loading-spinner mx-auto"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <footer class="mt-16 bg-gray-800 text-white py-8">
            <div class="max-w-7xl mx-auto px-6 text-center">
                <p>&copy; 2024 Ministry of Health - Kenya. Healthcare Training Dashboard</p>
            </div>
        </footer>

        <script>
                // Healthcare Dashboard JavaScript - Complete Clean Version
        class HealthcareDashboard {
            constructor() {
                this.currentType = 'global_training';
                this.currentYear = new Date().getFullYear();
                this.kenyaCounties = null;
                this.charts = {};
        
                this.init();
            }
    
            async init() {
                try {
                    // Initialize AOS animations
                    if (typeof AOS !== 'undefined') {
                        AOS.init({
                            duration: 800,
                            once: true
                        });
                    }
            
                    // Setup event listeners
                    this.setupEventListeners();
            
                    // Load initial data
                    await this.loadAvailableYears();
                    await this.loadOverviewStats();
                    await this.loadCountiesData();
            
                    // Initialize map
                    this.initializeMap();
                } catch (error) {
                    console.error('Error initializing dashboard:', error);
                    this.showError('Failed to initialize dashboard');
                }
            }
    
            setupEventListeners() {
                try {
                    // Training type toggle buttons
                    const globalBtn = document.getElementById('global-training-btn');
                    const mentorshipBtn = document.getElementById('mentorship-btn');
            
                    if (globalBtn) {
                        globalBtn.addEventListener('click', () => {
                            this.switchTrainingType('global_training');
                        });
                    }
            
                    if (mentorshipBtn) {
                        mentorshipBtn.addEventListener('click', () => {
                            this.switchTrainingType('facility_mentorship');
                        });
                    }
            
                    // Year filter
                    const yearFilter = document.getElementById('year-filter');
                    if (yearFilter) {
                        yearFilter.addEventListener('change', (e) => {
                            this.currentYear = e.target.value;
                            this.refreshData();
                        });
                    }
            
                    // Quick action buttons
                    const facilityGapsBtn = document.getElementById('show-facility-gaps');
                    const departmentBtn = document.getElementById('show-department-analysis');
                    const uncoveredBtn = document.getElementById('show-uncovered-counties');
            
                    if (facilityGapsBtn) {
                        facilityGapsBtn.addEventListener('click', () => {
                            this.showFacilityTypeAnalysis();
                        });
                    }
            
                    if (departmentBtn) {
                        departmentBtn.addEventListener('click', () => {
                            this.showDepartmentAnalysis();
                        });
                    }
            
                    if (uncoveredBtn) {
                        uncoveredBtn.addEventListener('click', () => {
                            this.highlightUncoveredCounties();
                        });
                    }
                } catch (error) {
                    console.error('Error setting up event listeners:', error);
                }
            }
    
            async loadAvailableYears() {
                try {
                    const response = await fetch(`/dashboard/api/years?type=${this.currentType}`);
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    const years = await response.json();
            
                    const yearFilter = document.getElementById('year-filter');
                    if (yearFilter) {
                        yearFilter.innerHTML = '';
                
                        years.forEach(year => {
                            const option = document.createElement('option');
                            option.value = year;
                            option.textContent = year;
                            option.selected = year == this.currentYear;
                            yearFilter.appendChild(option);
                        });
                    }
                } catch (error) {
                    console.error('Error loading years:', error);
                    this.showError('Failed to load available years');
                }
            }
    
            async loadOverviewStats() {
                try {
                    const response = await fetch(`/dashboard/api/overview?type=${this.currentType}&year=${this.currentYear}`);
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    const stats = await response.json();
            
                    // Update counties metrics
                    this.setElementText('counties-covered', stats.counties.covered);
                    this.setElementText('counties-total', stats.counties.total);
            
                    // Update facilities metrics
                    this.setElementText('facilities-covered', stats.facilities.covered);
                    this.setElementText('facilities-total', stats.facilities.total);
            
                    // Update programs metrics
                    this.setElementText('unique-programs', stats.year_data.programs);
                    this.setElementText('current-year', stats.year_data.year);
            
                    // Update participants for the year
                    this.setElementText('year-participants', this.formatNumber(stats.year_data.participants));
                    this.setElementText('current-year-2', stats.year_data.year);
            
                    // Update all-time participants
                    this.setElementText('total-participants', this.formatNumber(stats.all_time_data.participants));
            
                    // Update labels based on type
                    this.setElementText('participants-label', 
                        this.currentType === 'global_training' ? 'Participants' : 'Mentees');
                    this.setElementText('programs-label', 
                        this.currentType === 'global_training' ? 'Training Programs' : 'Mentorships');
                
                    // Load enhanced insights
                    await this.loadEnhancedInsights();
            
                } catch (error) {
                    console.error('Error loading overview stats:', error);
                    this.showError('Failed to load overview statistics');
                }
            }
    
            async loadEnhancedInsights() {
                try {
                    const response = await fetch(`/dashboard/api/enhanced-insights?type=${this.currentType}&year=${this.currentYear}`);
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    const insights = await response.json();
            
                    this.renderInsights(insights);
            
                } catch (error) {
                    console.error('Error loading enhanced insights:', error);
                    this.showError('Failed to load insights');
                }
            }
    
            async loadCountiesData() {
                try {
                    const response = await fetch(`/dashboard/api/counties-heatmap?type=${this.currentType}&year=${this.currentYear}`);
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    this.kenyaCounties = await response.json();
            
                    // Update map
                    this.updateMapDisplay();
            
                } catch (error) {
                    console.error('Error loading counties data:', error);
                    this.showError('Failed to load counties data');
                }
            }
    
            initializeMap() {
                try {
                    const mapContainer = document.getElementById('kenya-map');
                    if (mapContainer) {
                        mapContainer.innerHTML = `
                <div class="text-center">
                    <div class="text-6xl mb-4">🗺️</div>
                    <h3 class="text-xl font-bold text-gray-600 mb-2">Kenya Counties Interactive Map</h3>
                    <p class="text-gray-500 mb-4">Click on counties to drill down into coverage details</p>
                    <div class="grid grid-cols-6 gap-2 max-w-md mx-auto" id="counties-grid">
                                    ${this.createCountyGrid()}
                    </div>
                </div>
                        `;
                    }
                } catch (error) {
                    console.error('Error initializing map:', error);
                    this.showError('Failed to initialize map');
                }
            }
    
            createCountyGrid() {
                if (!this.kenyaCounties || this.kenyaCounties.length === 0) {
                        return '<div class="col-span-6 text-gray-500">Loading counties...</div>';
                }
        
                try {
                    return this.kenyaCounties.map(county => {
                        const colorClass = this.getCoverageColorClass(county.coverage_percentage);
                        const safeName = county.name.replace(/'/g, "\\'");
                        return `
                        <div class="county-mini ${colorClass} p-2 rounded cursor-pointer text-white text-xs text-center font-medium hover:opacity-80 transition-opacity"
                                 onclick="dashboard.drillDownToCounty(${county.id})"
                                 title="${safeName}: ${county.coverage_percentage}% coverage">
                                ${county.name.substring(0, 3)}
                        </div>
                        `;
                    }).join('');
                } catch (error) {
                    console.error('Error creating county grid:', error);
                    return '<div class="col-span-6 text-red-500">Error loading counties</div>';
                }
            }
    
            updateMapDisplay() {
                try {
                    const grid = document.getElementById('counties-grid');
                    if (grid) {
                        grid.innerHTML = this.createCountyGrid();
                    }
                } catch (error) {
                    console.error('Error updating map display:', error);
                }
            }
    
            getCoverageColorClass(percentage) {
                if (percentage >= 80) return 'coverage-high';
                if (percentage >= 40) return 'coverage-medium';
                if (percentage >= 1) return 'coverage-low';
                return 'coverage-none';
            }
    
            renderInsights(insights) {
                try {
                    const container = document.getElementById('insights-container');
                    if (!container) return;
            
                    if (!insights || insights.length === 0) {
                        container.innerHTML = '<p class="text-gray-500">No insights available</p>';
                        return;
                    }
            
                    container.innerHTML = insights.map(insight => {
                        const colorClass = {
                            'success': 'bg-green-600',
                            'info': 'bg-blue-600',
                            'warning': 'bg-yellow-600',
                            'alert': 'bg-red-600'
                        }[insight.type] || 'bg-gray-600';
                
                        const icon = {
                            'success': '✅',
                            'info': 'ℹ️',
                            'warning': '⚠️',
                            'alert': '🚨'
                        }[insight.type] || '📊';
                
                        return `
                        <div class="insight-card p-4 rounded-lg text-white ${colorClass}">
                            <div class="flex items-start space-x-3">
                                <div class="text-xl">${icon}</div>
                                <div class="flex-1">
                                    <h4 class="font-medium mb-1">${this.escapeHtml(insight.title || 'Insight')}</h4>
                                    <p class="text-sm opacity-90">${this.escapeHtml(insight.description || 'No description')}</p>
                                </div>
                            </div>
                        </div>
                        `;
                    }).join('');
                } catch (error) {
                    console.error('Error rendering insights:', error);
                    const container = document.getElementById('insights-container');
                    if (container) {
                        container.innerHTML = '<p class="text-red-500">Error loading insights</p>';
                    }
                }
            }
    
            switchTrainingType(type) {
                try {
                    this.currentType = type;
            
                    // Update button styles
                    const globalBtn = document.getElementById('global-training-btn');
                    const mentorshipBtn = document.getElementById('mentorship-btn');
            
                    if (globalBtn && mentorshipBtn) {
                        // Reset classes
                        globalBtn.className = 'px-6 py-2 rounded-md text-sm font-medium transition-colors';
                        mentorshipBtn.className = 'px-6 py-2 rounded-md text-sm font-medium transition-colors';
                
                        if (type === 'global_training') {
                            globalBtn.className += ' bg-blue-600 text-white';
                            mentorshipBtn.className += ' text-gray-700 hover:text-gray-900';
                        } else {
                            mentorshipBtn.className += ' bg-blue-600 text-white';
                            globalBtn.className += ' text-gray-700 hover:text-gray-900';
                        }
                    }
            
                    this.refreshData();
                } catch (error) {
                    console.error('Error switching training type:', error);
                }
            }
    
            async refreshData() {
                try {
                    await this.loadAvailableYears();
                    await this.loadOverviewStats();
                    await this.loadCountiesData();
                } catch (error) {
                    console.error('Error refreshing data:', error);
                    this.showError('Failed to refresh data');
                }
            }
    
            async showFacilityTypeAnalysis() {
                try {
                    const section = document.getElementById('facility-type-section');
                    if (section) {
                        section.classList.remove('hidden');
                        section.scrollIntoView({ behavior: 'smooth' });
                    }
            
                    const response = await fetch(`/dashboard/api/coverage/facility-type?type=${this.currentType}&year=${this.currentYear}`);
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    const data = await response.json();
            
                    this.createFacilityTypeChart(data);
                    this.generateFacilityTypeInsights(data);
            
                } catch (error) {
                    console.error('Error loading facility type data:', error);
                    this.showError('Failed to load facility type analysis');
                }
            }
    
            async showDepartmentAnalysis() {
                try {
                    const section = document.getElementById('department-section');
                    if (section) {
                        section.classList.remove('hidden');
                        section.scrollIntoView({ behavior: 'smooth' });
                    }
            
                    const response = await fetch(`/dashboard/api/coverage/department?type=${this.currentType}&year=${this.currentYear}`);
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    const data = await response.json();
            
                    this.createDepartmentChart(data);
                    this.generateDepartmentInsights(data);
            
                } catch (error) {
                    console.error('Error loading department data:', error);
                    this.showError('Failed to load department analysis');
                }
            }
    
            createFacilityTypeChart(data) {
                try {
                    const canvas = document.getElementById('facility-type-chart');
                    if (!canvas) return;
            
                    const ctx = canvas.getContext('2d');
                    if (!ctx) return;
            
                    // Destroy existing chart
                    if (this.charts.facilityType) {
                        this.charts.facilityType.destroy();
                    }
            
                    this.charts.facilityType = new Chart(ctx, {
                        type: 'doughnut',
                        data: {
                            labels: data.map(item => item.name || 'Unknown'),
                            datasets: [{
                                data: data.map(item => item.coverage_percentage || 0),
                                backgroundColor: [
                                    '#10B981', '#F59E0B', '#EF4444', '#3B82F6', '#8B5CF6', '#F97316'
                                ]
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'bottom'
                                }
                            }
                        }
                    });
                } catch (error) {
                    console.error('Error creating facility type chart:', error);
                }
            }
    
            createDepartmentChart(data) {
                try {
                    const canvas = document.getElementById('department-chart');
                    if (!canvas) return;
            
                    const ctx = canvas.getContext('2d');
                    if (!ctx) return;
            
                    // Destroy existing chart
                    if (this.charts.department) {
                        this.charts.department.destroy();
                    }
            
                    this.charts.department = new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: data.map(item => item.name || 'Unknown'),
                            datasets: [{
                                label: 'Trained',
                                data: data.map(item => item.trained || 0),
                                backgroundColor: '#10B981'
                            }, {
                                label: 'Untrained',
                                data: data.map(item => item.untrained || 0),
                                backgroundColor: '#EF4444'
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                x: {
                                    stacked: true
                                },
                                y: {
                                    stacked: true
                                }
                            },
                            plugins: {
                                legend: {
                                    position: 'bottom'
                                }
                            }
                        }
                    });
                } catch (error) {
                    console.error('Error creating department chart:', error);
                }
            }
    
            generateFacilityTypeInsights(data) {
                try {
                    const container = document.getElementById('facility-type-insights');
                    if (!container) return;
            
                    const sorted = data.sort((a, b) => (b.coverage_percentage || 0) - (a.coverage_percentage || 0));
            
                    container.innerHTML = `
                        <div class="space-y-4">
                            <h4 class="font-medium text-gray-800">Coverage by Facility Type</h4>
                            ${sorted.map(item => `
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <div>
                                    <div class="font-medium">${this.escapeHtml(item.name || 'Unknown')}</div>
                                    <div class="text-sm text-gray-600">${item.covered || 0}/${item.total || 0} facilities</div>
                                </div>
                                <div class="text-right">
                                    <div class="font-bold ${this.getCoverageTextColor(item.coverage_percentage || 0)}">
                                            ${item.coverage_percentage || 0}%
                                    </div>
                                </div>
                            </div>
                            `).join('')}
                        </div>
                    `;
                } catch (error) {
                    console.error('Error generating facility type insights:', error);
                    const container = document.getElementById('facility-type-insights');
                    if (container) {
                        container.innerHTML = '<p class="text-red-500">Error loading insights</p>';
                    }
                }
            }
    
            generateDepartmentInsights(data) {
                try {
                    const container = document.getElementById('department-insights');
                    if (!container) return;
            
                    const sorted = data.sort((a, b) => (b.coverage_percentage || 0) - (a.coverage_percentage || 0));
                    const mostCovered = sorted[0];
                    const leastCovered = sorted[sorted.length - 1];
            
                    if (!mostCovered || !leastCovered) {
                        container.innerHTML = '<p class="text-gray-500">No department data available</p>';
                        return;
                    }
            
                    container.innerHTML = `
                        <div class="space-y-4">
                            <h4 class="font-medium text-gray-800">Department Analysis</h4>
                    
                            <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                                <div class="flex items-center text-green-800">
                                    <span class="font-medium">Best Covered: ${this.escapeHtml(mostCovered.name || 'Unknown')}</span>
                                </div>
                                <p class="text-sm text-green-700 mt-1">
                                    ${mostCovered.coverage_percentage || 0}% coverage (${mostCovered.trained || 0}/${mostCovered.total || 0})
                                </p>
                            </div>
                    
                            <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                                <div class="flex items-center text-red-800">
                                    <span class="font-medium">Needs Attention: ${this.escapeHtml(leastCovered.name || 'Unknown')}</span>
                                </div>
                                <p class="text-sm text-red-700 mt-1">
                                    Only ${leastCovered.coverage_percentage || 0}% coverage - ${leastCovered.untrained || 0} staff need training
                                </p>
                            </div>
                    
                            <div class="space-y-2">
                                ${sorted.slice(0, 5).map(item => `
                                <div class="flex items-center justify-between p-2 bg-gray-50 rounded">
                                    <div>
                                        <div class="text-sm font-medium">${this.escapeHtml(item.name || 'Unknown')}</div>
                                        <div class="text-xs text-gray-600">${item.trained || 0}/${item.total || 0} trained</div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-sm font-bold ${this.getCoverageTextColor(item.coverage_percentage || 0)}">
                                                ${item.coverage_percentage || 0}%
                                        </div>
                                    </div>
                                </div>
                                `).join('')}
                            </div>
                        </div>
                    `;
                } catch (error) {
                    console.error('Error generating department insights:', error);
                    const container = document.getElementById('department-insights');
                    if (container) {
                        container.innerHTML = '<p class="text-red-500">Error loading department insights</p>';
                    }
                }
            }
    
            drillDownToCounty(countyId) {
                try {
                    if (!countyId) {
                        console.error('No county ID provided');
                        return;
                    }
                    window.location.href = `/dashboard/county/${countyId}?type=${this.currentType}&year=${this.currentYear}`;
                } catch (error) {
                    console.error('Error drilling down to county:', error);
                    this.showError('Failed to navigate to county details');
                }
            }
    
            highlightUncoveredCounties() {
                try {
                    if (!this.kenyaCounties) {
                        this.showError('County data not loaded');
                        return;
                    }
            
                    const uncovered = this.kenyaCounties.filter(county => 
                        (county.coverage_percentage || 0) === 0
                    );
            
                    if (uncovered.length === 0) {
                        alert('Great news! All counties have some training coverage.');
                        return;
                    }
            
                    const countyNames = uncovered.map(c => c.name || 'Unknown').join(', ');
                    alert(`Uncovered Counties (${uncovered.length}):\n${countyNames}`);
                } catch (error) {
                    console.error('Error highlighting uncovered counties:', error);
                    this.showError('Failed to analyze uncovered counties');
                }
            }
    
            // Utility methods
            setElementText(elementId, text) {
                try {
                    const element = document.getElementById(elementId);
                    if (element) {
                        element.textContent = text;
                    }
                } catch (error) {
                    console.error(`Error setting text for ${elementId}:`, error);
                }
            }
    
            formatNumber(num) {
                return typeof num === 'number' && num >= 1000 ? num.toLocaleString() : num.toString();
            }
    
            getCoverageTextColor(percentage) {
                if (percentage >= 80) return 'text-green-600';
                if (percentage >= 50) return 'text-yellow-600';
                return 'text-red-600';
            }
    
            escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }
    
            showError(message) {
                try {
                    console.error('Dashboard Error:', message);
            
                    // Show a user-friendly error message
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'fixed top-4 right-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded z-50';
                    errorDiv.innerHTML = `
                        <div class="flex items-center">
                            <span class="mr-2">⚠️</span>
                            <span>${this.escapeHtml(message)}</span>
                            <button class="ml-4 text-red-500 hover:text-red-700" onclick="this.parentElement.parentElement.remove()">×</button>
                        </div>
                    `;
            
                    document.body.appendChild(errorDiv);
            
                    // Auto-remove after 5 seconds
                    setTimeout(() => {
                        if (errorDiv.parentNode) {
                            errorDiv.parentNode.removeChild(errorDiv);
                        }
                    }, 5000);
                } catch (err) {
                    console.error('Error showing error message:', err);
                }
            }
        }

        // Initialize dashboard
        document.addEventListener('DOMContentLoaded', function() {
            try {
                window.dashboard = new HealthcareDashboard();
            } catch (error) {
                console.error('Failed to initialize dashboard:', error);
            }
        });

        // Fallback for older browsers or if DOM is already loaded
        if (document.readyState !== 'loading') {
            try {
                window.dashboard = new HealthcareDashboard();
            } catch (error) {
                console.error('Immediate dashboard initialization failed:', error);
        }
        }   
                </script>   
            </body>
</html>