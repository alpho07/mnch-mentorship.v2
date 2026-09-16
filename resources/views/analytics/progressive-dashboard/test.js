// public/js/progressive-dashboard.js

class ProgressiveDashboard {
    constructor() {
        this.apiBase = '/api/progressive-dashboard';
        this.currentLevel = 'national';
        this.currentType = 'global_training';
        this.currentYear = 'all';
        this.currentCounty = null;
        this.currentFacilityType = null;
        this.currentFacility = null;
        this.charts = {};
        this.breadcrumb = ['National Overview'];
        this.csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        
        this.init();
    }
    
    async init() {
        this.setupEventListeners();
        await this.loadYears();
        await this.loadNationalData();
    }
    
    // API Helper Methods
    async apiGet(endpoint, params = {}) {
        const url = new URL(`${this.apiBase}${endpoint}`, window.location.origin);
        Object.keys(params).forEach(key => url.searchParams.append(key, params[key]));
        
        try {
            const response = await fetch(url);
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            return await response.json();
        } catch (error) {
            console.error(`API Error (${endpoint}):`, error);
            this.showError(`Failed to load data: ${error.message}`);
            throw error;
        }
    }
    
    async apiPost(endpoint, data = {}) {
        try {
            const response = await fetch(`${this.apiBase}${endpoint}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken || ''
                },
                body: JSON.stringify(data)
            });
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            return await response.json();
        } catch (error) {
            console.error(`API Error (${endpoint}):`, error);
            this.showError(`Failed to process request: ${error.message}`);
            throw error;
        }
    }
    
    setupEventListeners() {
        // Training type toggle
        document.getElementById('global-training-btn')?.addEventListener('click', () => {
            this.switchTrainingType('global_training');
        });
        
        document.getElementById('mentorship-btn')?.addEventListener('click', () => {
            this.switchTrainingType('facility_mentorship');
        });
        
        // Year filter
        document.getElementById('year-filter')?.addEventListener('change', (e) => {
            this.currentYear = e.target.value;
            this.refreshCurrentLevel();
        });
        
        // County search
        const countySearch = document.getElementById('county-search');
        if (countySearch) {
            countySearch.addEventListener('input', (e) => {
                this.filterCounties(e.target.value);
            });
        }
        
        // Export current view
        const exportBtn = document.getElementById('export-current-view');
        if (exportBtn) {
            exportBtn.addEventListener('click', () => {
                this.exportCurrentView();
            });
        }
    }
    
    switchTrainingType(type) {
        this.currentType = type;
        
        // Update button styles
        const globalBtn = document.getElementById('global-training-btn');
        const mentorshipBtn = document.getElementById('mentorship-btn');
        
        if (type === 'global_training') {
            globalBtn.className = 'px-4 py-2 rounded-md text-sm font-medium transition-colors bg-blue-600 text-white';
            mentorshipBtn.className = 'px-4 py-2 rounded-md text-sm font-medium transition-colors text-gray-700 hover:text-gray-900';
        } else {
            mentorshipBtn.className = 'px-4 py-2 rounded-md text-sm font-medium transition-colors bg-blue-600 text-white';
            globalBtn.className = 'px-4 py-2 rounded-md text-sm font-medium transition-colors text-gray-700 hover:text-gray-900';
        }
        
        // Update labels
        const isGlobal = type === 'global_training';
        this.setElementText('participants-label', isGlobal ? 'Participants' : 'Mentees');
        this.setElementText('programs-label', isGlobal ? 'Programs' : 'Mentorships');
        
        // Update type-specific labels in facility level
        this.setElementText('type-participants-label', isGlobal ? 'Participants' : 'Mentees');
        this.setElementText('facility-participants-label', isGlobal ? 'Participants' : 'Mentees');
        
        this.refreshCurrentLevel();
    }
    
    async loadYears() {
        try {
            const years = await this.apiGet('/years', { type: this.currentType });
            
            const yearFilter = document.getElementById('year-filter');
            if (!yearFilter) return;
            
            yearFilter.innerHTML = '';
            
            // Add "All Years" as the first option
            const allYearsOption = document.createElement('option');
            allYearsOption.value = 'all';
            allYearsOption.textContent = 'All Years';
            allYearsOption.selected = true;
            yearFilter.appendChild(allYearsOption);
            
            // Add individual years
            years.forEach(year => {
                const option = document.createElement('option');
                option.value = year;
                option.textContent = year;
                yearFilter.appendChild(option);
            });
            
            this.currentYear = 'all';
            
        } catch (error) {
            console.error('Error loading years:', error);
        }
    }
    
    showLoading() {
        const overlay = document.getElementById('loading-overlay');
        if (overlay) {
            overlay.classList.remove('hidden');
        }
    }
    
    hideLoading() {
        const overlay = document.getElementById('loading-overlay');
        if (overlay) {
            overlay.classList.add('hidden');
        }
    }
    
    showLevel(level) {
        // Hide all levels
        document.querySelectorAll('.dashboard-level').forEach(el => {
            el.classList.add('hidden');
        });
        
        // Show current level with animation
        const currentLevelEl = document.getElementById(`${level}-level`);
        if (currentLevelEl) {
            currentLevelEl.classList.remove('hidden');
            currentLevelEl.classList.add('slide-in');
            
            // Initialize level-specific functionality
            if (level === 'facility') {
                this.initializeFacilityLevel();
            }
        }
        
        this.updateBreadcrumb();
    }
    
    updateBreadcrumb() {
        const breadcrumbEl = document.getElementById('breadcrumb');
        if (!breadcrumbEl) return;
        
        breadcrumbEl.innerHTML = this.breadcrumb.map((item, index) => {
            if (index === this.breadcrumb.length - 1) {
                return `<span class="text-gray-600">${item}</span>`;
            } else {
                const level = this.getLevelFromIndex(index);
                return `<span class="text-blue-600 cursor-pointer hover:text-blue-800" onclick="dashboard.goToLevel('${level}')">${item}</span>`;
            }
        }).join(' <span class="mx-2 text-gray-400">></span> ');
    }
    
    getLevelFromIndex(index) {
        const levels = ['national', 'county', 'facility-type', 'facility'];
        return levels[index] || 'national';
    }
    
    goToLevel(level) {
        if (level === 'national') {
            this.currentLevel = 'national';
            this.currentCounty = null;
            this.currentFacilityType = null;
            this.currentFacility = null;
            this.breadcrumb = ['National Overview'];
            this.loadNationalData();
        } else if (level === 'county' && this.currentCounty) {
            this.currentLevel = 'county';
            this.currentFacilityType = null;
            this.currentFacility = null;
            this.breadcrumb = ['National Overview', this.currentCounty.name];
            this.loadCountyData(this.currentCounty.id);
        } else if (level === 'facility-type' && this.currentCounty && this.currentFacilityType) {
            this.currentLevel = 'facility-type';
            this.currentFacility = null;
            this.breadcrumb = ['National Overview', this.currentCounty.name, `${this.currentFacilityType.name} Facilities`];
            this.loadFacilityTypeData(this.currentCounty.id, this.currentFacilityType.id);
        }
    }
    
    async loadNationalData() {
        this.showLoading();
        this.currentLevel = 'national';
        
        try {
            const data = await this.apiGet('/national', {
                type: this.currentType,
                year: this.currentYear
            });
            
            this.renderNationalOverview(data);
            this.showLevel('national');
            
        } catch (error) {
            console.error('Error loading national data:', error);
        } finally {
            this.hideLoading();
        }
    }
    
    renderNationalOverview(data) {
        const summary = data.national_summary;
        
        // Update metrics
        this.setElementText('national-counties', `${summary.covered_counties}/${summary.total_counties}`);
        this.setElementText('national-facilities', `${summary.covered_facilities}/${summary.total_facilities}`);
        this.setElementText('national-participants', this.formatNumber(summary.total_participants));
        this.setElementText('national-programs', summary.total_programs);
        
        const avgCoverage = summary.total_counties > 0 ? 
            Math.round((summary.covered_counties / summary.total_counties) * 100) : 0;
        this.setElementText('national-coverage', `${avgCoverage}%`);
        
        // Render counties grid
        this.renderCountiesGrid(data.counties);
        
        // Render insights
        this.renderInsights(data.insights, 'national-insights');
    }
    
    renderCountiesGrid(counties) {
        const grid = document.getElementById('counties-grid');
        if (!grid) return;
        
        grid.innerHTML = counties.map(county => {
            const colorClass = this.getCoverageColorClass(county.coverage_percentage);
            
            return `
                <div class="county-card ${colorClass} text-white rounded-lg p-4" 
                     onclick="dashboard.drillDownToCounty(${county.id}, '${county.name}')">
                    <div class="flex items-center justify-between mb-2">
                        <h4 class="font-bold text-lg">${county.name}</h4>
                        <span class="text-2xl font-bold">${county.coverage_percentage}%</span>
                    </div>
                    <div class="grid grid-cols-2 gap-2 text-sm">
                        <div>
                            <div class="opacity-90">Facilities</div>
                            <div class="font-medium">${county.covered_facilities}/${county.total_facilities}</div>
                        </div>
                        <div>
                            <div class="opacity-90">Participants</div>
                            <div class="font-medium">${this.formatNumber(county.participant_count)}</div>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    }
    
    filterCounties(searchTerm) {
        const cards = document.querySelectorAll('.county-card');
        cards.forEach(card => {
            const countyName = card.querySelector('h4')?.textContent.toLowerCase() || '';
            const matches = countyName.includes(searchTerm.toLowerCase());
            card.style.display = matches ? 'block' : 'none';
        });
    }
    
    async drillDownToCounty(countyId, countyName) {
        this.currentCounty = { id: countyId, name: countyName };
        this.breadcrumb = ['National Overview', countyName];
        await this.loadCountyData(countyId);
    }
    
    async loadCountyData(countyId) {
        this.showLoading();
        this.currentLevel = 'county';
        
        try {
            const data = await this.apiGet(`/county/${countyId}`, {
                type: this.currentType,
                year: this.currentYear
            });
            
            this.renderCountyAnalysis(data);
            this.showLevel('county');
            
        } catch (error) {
            console.error('Error loading county data:', error);
        } finally {
            this.hideLoading();
        }
    }
    
    renderCountyAnalysis(data) {
        // Update county title and metrics
        this.setElementText('county-title', `${data.county.name} County Analysis`);
        
        const totalFacilities = data.facility_types.reduce((sum, type) => sum + type.total, 0);
        const coveredFacilities = data.facility_types.reduce((sum, type) => sum + type.covered, 0);
        const uncoveredFacilities = totalFacilities - coveredFacilities;
        const coveragePercentage = totalFacilities > 0 ? Math.round((coveredFacilities / totalFacilities) * 100) : 0;
        
        this.setElementText('county-total-facilities', totalFacilities);
        this.setElementText('county-covered-facilities', coveredFacilities);
        this.setElementText('county-uncovered-facilities', uncoveredFacilities);
        
        // Update progress bar
        const progressBar = document.getElementById('county-coverage-bar');
        const progressText = document.getElementById('county-coverage-percentage');
        if (progressBar && progressText) {
            progressBar.style.width = `${coveragePercentage}%`;
            progressText.textContent = `${coveragePercentage}%`;
        }
        
        // Update quick stats
        const participants = data.coverage?.participant_count || 0;
        const programs = data.coverage?.program_count || 0;
        const departmentsCount = data.departments ? data.departments.length : 0;
        
        this.setElementText('county-participants', participants);
        this.setElementText('county-programs', programs);
        this.setElementText('county-departments-count', departmentsCount);
        
        // Render facility types chart
        this.renderFacilityTypesChart(data.facility_types);
        
        // Render facility types list
        this.renderFacilityTypesList(data.facility_types);
        
        // Render departments
        this.renderDepartmentsList(data.departments);
        
        // Render recommended actions
        this.renderRecommendedActions(data.recommended_actions);
        
        // Render insights
        this.renderInsights(data.insights, 'county-insights');
    }
    
    renderFacilityTypesChart(facilityTypes) {
        const canvas = document.getElementById('facility-types-canvas');
        if (!canvas) return;
        
        const ctx = canvas.getContext('2d');
        
        if (this.charts.facilityTypes) {
            this.charts.facilityTypes.destroy();
        }
        
        this.charts.facilityTypes = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: facilityTypes.map(type => type.name),
                datasets: [{
                    data: facilityTypes.map(type => type.total),
                    backgroundColor: ['#10B981', '#F59E0B', '#EF4444', '#3B82F6', '#8B5CF6', '#F97316'],
                    borderWidth: 2,
                    borderColor: '#fff'
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
    }
    
    renderFacilityTypesList(facilityTypes) {
        const listEl = document.getElementById('facility-types-list');
        if (!listEl) return;
        
        listEl.innerHTML = facilityTypes.map(type => `
            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg cursor-pointer hover:bg-gray-100 transition-colors"
                 onclick="dashboard.drillDownToFacilityType(${type.id}, '${type.name}')">
                <div>
                    <div class="font-medium">${type.name}</div>
                    <div class="text-sm text-gray-600">${type.covered}/${type.total} facilities covered</div>
                </div>
                <div class="text-right">
                    <div class="font-bold ${this.getCoverageTextColor(type.coverage_percentage)}">${type.coverage_percentage}%</div>
                    <div class="text-xs px-2 py-1 rounded ${this.getPriorityClass(type.priority)}">${type.priority}</div>
                </div>
            </div>
        `).join('');
    }
    
    renderDepartmentsList(departments) {
        const listEl = document.getElementById('departments-list');
        if (!listEl || !departments) return;
        
        listEl.innerHTML = departments.slice(0, 8).map(dept => `
            <div class="flex items-center justify-between p-2 bg-gray-50 rounded">
                <div>
                    <div class="text-sm font-medium">${dept.name}</div>
                    <div class="text-xs text-gray-600">${dept.trained}/${dept.total} trained</div>
                </div>
                <div class="text-sm font-bold ${this.getCoverageTextColor(dept.coverage_percentage)}">${dept.coverage_percentage}%</div>
            </div>
        `).join('');
    }
    
    renderRecommendedActions(actions) {
        const actionsEl = document.getElementById('recommended-actions');
        if (!actionsEl || !actions) return;
        
        actionsEl.innerHTML = actions.map(action => `
            <div class="p-3 border-l-4 ${this.getActionBorderColor(action.priority)} bg-gray-50 rounded">
                <div class="flex items-center justify-between mb-1">
                    <div class="font-medium text-sm">${action.title}</div>
                    <span class="text-xs px-2 py-1 rounded ${this.getPriorityClass(action.priority)}">${action.priority}</span>
                </div>
                <div class="text-xs text-gray-600 mb-2">${action.description}</div>
                <div class="text-xs font-medium text-blue-600">Impact: ${action.estimated_impact || action.estimated_participants + ' participants'}</div>
            </div>
        `).join('');
    }
    
    async drillDownToFacilityType(typeId, typeName) {
        this.currentFacilityType = { id: typeId, name: typeName };
        this.breadcrumb = ['National Overview', this.currentCounty.name, `${typeName} Facilities`];
        await this.loadFacilityTypeData(this.currentCounty.id, typeId);
    }
    
    async loadFacilityTypeData(countyId, facilityTypeId) {
        this.showLoading();
        this.currentLevel = 'facility-type';
        
        try {
            const data = await this.apiGet(`/county/${countyId}/facility-type/${facilityTypeId}`, {
                type: this.currentType,
                year: this.currentYear
            });
            
            this.renderFacilityTypeAnalysis(data);
            this.showLevel('facility-type');
            
        } catch (error) {
            console.error('Error loading facility type data:', error);
        } finally {
            this.hideLoading();
        }
    }
    
    renderFacilityTypeAnalysis(data) {
        // Update titles and metrics
        this.setElementText('facility-type-title', `${data.facility_type.name} Analysis - ${data.county.name}`);
        this.setElementText('type-total-facilities', data.summary.total);
        this.setElementText('type-covered-facilities', data.summary.covered);
        this.setElementText('type-total-participants', this.formatNumber(data.summary.total_participants));
        
        const coverage = data.summary.total > 0 ? Math.round((data.summary.covered / data.summary.total) * 100) : 0;
        this.setElementText('type-coverage-percentage', `${coverage}%`);
        
        // Render facilities list
        this.renderFacilitiesList(data.facilities);
        
        // Render performance chart if data available
        if (data.performance_metrics) {
            this.renderPerformanceChart(data.performance_metrics);
        }
    }
    
    renderFacilitiesList(facilities) {
        const listEl = document.getElementById('facilities-list');
        if (!listEl) return;
        
        listEl.innerHTML = facilities.map(facility => `
            <div class="flex items-center justify-between p-4 border rounded-lg cursor-pointer hover:bg-gray-50 transition-colors ${facility.is_covered ? 'border-green-200 bg-green-50' : 'border-red-200 bg-red-50'}"
                 onclick="dashboard.drillDownToFacility(${facility.id}, '${facility.name}')">
                <div>
                    <div class="font-medium">${facility.name}</div>
                    <div class="text-sm text-gray-600">${facility.subcounty} • MFL: ${facility.mfl_code || 'N/A'}</div>
                </div>
                <div class="text-right">
                    <div class="flex items-center space-x-2">
                        <span class="${facility.is_covered ? 'text-green-600' : 'text-red-600'} font-medium">
                            ${facility.is_covered ? 'Covered' : 'Not Covered'}
                        </span>
                        ${facility.participant_count > 0 ? `<span class="text-sm text-gray-600">${facility.participant_count} participants</span>` : ''}
                    </div>
                    ${facility.completion_rate ? `<div class="text-xs text-gray-500">${facility.completion_rate}% completion</div>` : ''}
                </div>
            </div>
        `).join('');
    }
    
    renderPerformanceChart(performanceMetrics) {
        const canvas = document.getElementById('performance-canvas');
        if (!canvas) return;
        
        const ctx = canvas.getContext('2d');
        
        if (this.charts.performance) {
            this.charts.performance.destroy();
        }
        
        // Simple performance distribution chart
        this.charts.performance = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Total Facilities', 'Covered', 'High Performers', 'Underperformers'],
                datasets: [{
                    data: [
                        performanceMetrics.total_facilities || 0,
                        performanceMetrics.covered_facilities || 0,
                        (performanceMetrics.total_facilities || 0) - (performanceMetrics.underperformers || 0),
                        performanceMetrics.underperformers || 0
                    ],
                    backgroundColor: ['#3B82F6', '#10B981', '#F59E0B', '#EF4444']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    }
    
    async drillDownToFacility(facilityId, facilityName) {
        this.currentFacility = { id: facilityId, name: facilityName };
        this.breadcrumb = [
            'National Overview', 
            this.currentCounty.name, 
            `${this.currentFacilityType.name} Facilities`,
            facilityName
        ];
        await this.loadFacilityData(facilityId);
    }
    
    async loadFacilityData(facilityId) {
        this.showLoading();
        this.currentLevel = 'facility';
        
        try {
            const data = await this.apiGet(`/facility/${facilityId}`, {
                type: this.currentType,
                year: this.currentYear
            });
            
            this.renderFacilityAnalysis(data);
            this.showLevel('facility');
            
        } catch (error) {
            console.error('Error loading facility data:', error);
        } finally {
            this.hideLoading();
        }
    }
    
    renderFacilityAnalysis(data) {
        // Update facility title and metrics
        this.setElementText('facility-title', `${data.facility.name} - ${data.facility.type}`);
        this.setElementText('facility-participants', data.performance.total_participants);
        this.setElementText('facility-programs', data.training_history.length);
        this.setElementText('facility-departments', data.performance.departments_represented);
        this.setElementText('facility-completion', `${data.performance.completion_rate}%`);
        
        // Render department breakdown chart
        this.renderFacilityDepartmentChart(data.department_breakdown);
        
        // Render cadre breakdown chart
        this.renderFacilityCadreChart(data.cadre_breakdown);
        
        // Render training history
        this.renderTrainingHistory(data.training_history);
        
        // Render participants table
        this.renderParticipantsTable(data.participants);
    }
    
    renderFacilityDepartmentChart(departmentData) {
        const canvas = document.getElementById('facility-departments-canvas');
        if (!canvas) return;
        
        const ctx = canvas.getContext('2d');
        
        if (this.charts.facilityDepartments) {
            this.charts.facilityDepartments.destroy();
        }
        
        this.charts.facilityDepartments = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: departmentData.map(dept => dept.name),
                datasets: [{
                    data: departmentData.map(dept => dept.count),
                    backgroundColor: [
                        '#10B981', '#3B82F6', '#F59E0B', '#EF4444', 
                        '#8B5CF6', '#F97316', '#06B6D4', '#84CC16'
                    ]
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
    }
    
    renderFacilityCadreChart(cadreData) {
        const canvas = document.getElementById('facility-cadres-canvas');
        if (!canvas) return;
        
        const ctx = canvas.getContext('2d');
        
        if (this.charts.facilityCadres) {
            this.charts.facilityCadres.destroy();
        }
        
        this.charts.facilityCadres = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: cadreData.map(cadre => cadre.name),
                datasets: [{
                    label: 'Participants',
                    data: cadreData.map(cadre => cadre.count),
                    backgroundColor: '#3B82F6'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true }
                },
                plugins: {
                    legend: { display: false }
                }
            }
        });
    }
    
    renderTrainingHistory(trainingHistory) {
        const container = document.getElementById('training-history');
        if (!container) return;
        
        if (trainingHistory.length === 0) {
            container.innerHTML = '<div class="text-gray-500 text-center py-8">No training history available</div>';
            return;
        }
        
        container.innerHTML = trainingHistory.map(training => `
            <div class="flex items-center justify-between p-4 border rounded-lg">
                <div class="flex-1">
                    <div class="font-medium">${training.title}</div>
                    <div class="text-sm text-gray-600">${training.start_date} - ${training.end_date}</div>
                    <div class="text-xs text-gray-500">ID: ${training.identifier || training.id}</div>
                </div>
                <div class="text-right">
                    <div class="text-sm font-medium">${training.participants_count || training.participants_from_facility} participants</div>
                    <div class="text-xs text-gray-600">${training.completion_rate}% completion</div>
                    <span class="inline-block px-2 py-1 text-xs rounded ${this.getStatusClass(training.status)}">${training.status}</span>
                </div>
            </div>
        `).join('');
    }
    
    renderParticipantsTable(participants) {
        const container = document.getElementById('participants-table');
        if (!container) return;
        
        if (participants.length === 0) {
            container.innerHTML = '<div class="text-gray-500 text-center py-8">No participants found</div>';
            return;
        }
        
        container.innerHTML = `
            <table class="min-w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Department</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cadre</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Score</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    ${participants.map(participant => `
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <div class="font-medium">${participant.name}</div>
                                <div class="text-sm text-gray-600">${participant.phone || 'No phone'}</div>
                            </td>
                            <td class="px-4 py-3 text-sm">${participant.department}</td>
                            <td class="px-4 py-3 text-sm">${participant.cadre}</td>
                            <td class="px-4 py-3">
                                <span class="inline-block px-2 py-1 text-xs rounded ${this.getStatusClass(participant.status || participant.enrollment_status)}">
                                    ${participant.status || participant.enrollment_status}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                ${participant.assessment_score ? participant.assessment_score + '%' : 'N/A'}
                            </td>
                            <td class="px-4 py-3">
                                <button onclick="dashboard.viewParticipantProfile(${participant.id || participant.user_id})" 
                                        class="text-blue-600 hover:text-blue-800 text-sm">View Profile</button>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
    }
    
    renderInsights(insights, containerId) {
        const container = document.getElementById(containerId);
        if (!container) return;
        
        if (!insights || insights.length === 0) {
            container.innerHTML = '<div class="text-gray-500 text-center py-4">No insights available</div>';
            return;
        }
        
        container.innerHTML = insights.map(insight => `
            <div class="p-3 ${this.getInsightClass(insight.type)} rounded-lg">
                <div class="flex items-start space-x-2">
                    <div class="text-lg">${this.getInsightIcon(insight.type)}</div>
                    <div>
                        <div class="font-medium text-sm">${insight.title}</div>
                        <div class="text-xs mt-1">${insight.message}</div>
                        ${insight.action ? `<div class="text-xs mt-2 font-medium">Action: ${insight.action}</div>` : ''}
                    </div>
                </div>
            </div>
        `).join('');
    }
    
    // Modal and interaction methods
    showCountyMap() {
        if (!this.currentCounty) return;
        
        // Load county facilities GeoJSON and show map
        this.apiGet(`/county/${this.currentCounty.id}/facilities-geojson`, {
            type: this.currentType,
            year: this.currentYear
        })
        .then(data => {
            this.displayCountyMap(data);
        })
        .catch(error => {
            console.error('Error loading county map:', error);
            this.showError('Failed to load county map');
        });
    }
    
    displayCountyMap(geoData) {
        // Simple implementation - in production you'd use Leaflet or similar
        alert(`County map would show ${geoData.features.length} facilities. ${geoData.metadata.covered_facilities || 0} are covered.`);
    }
    
    toggleCoveredOnly() {
        const btn = document.getElementById('toggle-covered');
        if (!btn) return;
        
        const currentText = btn.textContent;
        const facilityCards = document.querySelectorAll('#facilities-list > div');
        
        if (currentText === 'Show All') {
            btn.textContent = 'Show Covered Only';
            // Show only covered facilities
            facilityCards.forEach(card => {
                const isCovered = card.classList.contains('border-green-200');
                card.style.display = isCovered ? '' : 'none';
            });
        } else {
            btn.textContent = 'Show All';
            // Show all facilities
            facilityCards.forEach(card => {
                card.style.display = '';
            });
        }
    }
    
    exportFacilityList() {
        if (!this.currentCounty || !this.currentFacilityType) return;
        
        const facilityType = this.currentFacilityType.name;
        const county = this.currentCounty.name;
        
        this.apiPost('/export-facility-list', {
            county_id: this.currentCounty.id,
            facility_type_id: this.currentFacilityType.id,
            type: this.currentType,
            year: this.currentYear
        })
        .then(data => {
            if (data.download_url) {
                window.open(data.download_url, '_blank');
            } else {
                this.showSuccess('Export prepared - check downloads');
            }
        })
        .catch(error => {
            console.error('Export error:', error);
            this.showError('Export failed');
        });
    }
    
    closeFacilityDetails() {
        const modal = document.getElementById('facility-details-modal');
        if (modal) {
            modal.classList.add('hidden');
        }
    }
    
    async viewParticipantProfile(participantId) {
        try {
            const data = await this.apiGet(`/participant/${participantId}`, {
                type: this.currentType,
                year: this.currentYear
            });
            
            this.showParticipantModal(data);
        } catch (error) {
            console.error('Error loading participant profile:', error);
            this.showError('Failed to load participant profile');
        }
    }
    
    showParticipantModal(participantData) {
        // Remove existing modal if present
        this.closeParticipantModal();
        
        const modalHtml = `
            <div id="participant-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                <div class="bg-white rounded-lg p-6 max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-2xl font-bold text-gray-800">Participant Profile</h3>
                        <button onclick="dashboard.closeParticipantModal()" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                    
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- Personal Information -->
                        <div class="bg-gray-50 rounded-lg p-4">
                            <h4 class="font-semibold text-lg mb-4">Personal Information</h4>
                            <div class="space-y-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Name</label>
                                    <div class="text-lg font-semibold">${participantData.participant.user.name}</div>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Department</label>
                                        <div>${participantData.participant.user.department}</div>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Cadre</label>
                                        <div>${participantData.participant.user.cadre}</div>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Facility</label>
                                    <div>${participantData.participant.user.facility.name}</div>
                                    <div class="text-sm text-gray-600">${participantData.participant.user.facility.type} • ${participantData.participant.user.facility.county}</div>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Phone</label>
                                        <div>${participantData.participant.user.phone || 'Not provided'}</div>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Email</label>
                                        <div class="text-sm">${participantData.participant.user.email || 'Not provided'}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Training Information -->
                        <div class="bg-blue-50 rounded-lg p-4">
                            <h4 class="font-semibold text-lg mb-4">Current Training</h4>
                            <div class="space-y-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Training Program</label>
                                    <div class="font-medium">${participantData.participant.training.title}</div>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Type</label>
                                        <span class="px-2 py-1 text-xs rounded ${participantData.participant.training.type === 'global_training' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'}">
                                            ${participantData.participant.training.type === 'global_training' ? 'Global Training' : 'Mentorship'}
                                        </span>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Status</label>
                                        <span class="px-2 py-1 text-xs rounded ${this.getStatusClass(participantData.participant.participation.completion_status)}">
                                            ${participantData.participant.participation.completion_status}
                                        </span>
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Start Date</label>
                                        <div>${participantData.participant.training.start_date}</div>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Registration</label>
                                        <div>${participantData.participant.participation.registration_date}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Performance Metrics -->
                    <div class="mt-6 bg-green-50 rounded-lg p-4">
                        <h4 class="font-semibold text-lg mb-4">Performance Overview</h4>
                        <div class="grid grid-cols-4 gap-4">
                            <div class="text-center">
                                <div class="text-2xl font-bold text-green-600">${participantData.performance_metrics.total_trainings}</div>
                                <div class="text-sm text-gray-600">Total Trainings</div>
                            </div>
                            <div class="text-center">
                                <div class="text-2xl font-bold text-blue-600">${participantData.performance_metrics.completed_trainings}</div>
                                <div class="text-sm text-gray-600">Completed</div>
                            </div>
                            <div class="text-center">
                                <div class="text-2xl font-bold text-purple-600">${participantData.performance_metrics.completion_rate}%</div>
                                <div class="text-sm text-gray-600">Completion Rate</div>
                            </div>
                            <div class="text-center">
                                <div class="text-2xl font-bold text-orange-600">${participantData.performance_metrics.average_score}%</div>
                                <div class="text-sm text-gray-600">Avg Score</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Training History -->
                    <div class="mt-6">
                        <h4 class="font-semibold text-lg mb-4">Training History</h4>
                        <div class="space-y-2 max-h-64 overflow-y-auto">
                            ${participantData.training_history.map(training => `
                                <div class="p-3 bg-gray-50 rounded flex items-center justify-between">
                                    <div>
                                        <div class="font-medium">${training.training_title}</div>
                                        <div class="text-sm text-gray-600">
                                            ${training.training_type === 'global_training' ? 'Global Training' : 'Mentorship'} • 
                                            ${training.registration_date}
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <span class="px-2 py-1 text-xs rounded ${this.getStatusClass(training.completion_status)}">
                                            ${training.completion_status}
                                        </span>
                                        ${training.assessment_score > 0 ? `<div class="text-sm font-medium">${training.assessment_score}%</div>` : ''}
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', modalHtml);
    }
    
    closeParticipantModal() {
        const modal = document.getElementById('participant-modal');
        if (modal) {
            modal.remove();
        }
    }
    
    // Filter methods
    setupParticipantFilters() {
        const searchInput = document.getElementById('participant-search');
        const filterSelect = document.getElementById('participant-filter');
        
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                this.filterParticipants(e.target.value, filterSelect?.value || 'all');
            });
        }
        
        if (filterSelect) {
            filterSelect.addEventListener('change', (e) => {
                this.filterParticipants(searchInput?.value || '', e.target.value);
            });
        }
    }
    
    filterParticipants(searchTerm, status) {
        const rows = document.querySelectorAll('#participants-table tbody tr');
        
        rows.forEach(row => {
            const name = row.querySelector('td:first-child .font-medium')?.textContent.toLowerCase() || '';
            const participantStatus = row.querySelector('td:nth-child(4) span')?.textContent.toLowerCase() || '';
            
            const matchesSearch = !searchTerm || name.includes(searchTerm.toLowerCase());
            const matchesStatus = status === 'all' || participantStatus.includes(status.replace('-', '_'));
            
            row.style.display = matchesSearch && matchesStatus ? '' : 'none';
        });
    }
    
    // Initialize methods
    initializeFacilityLevel() {
        this.setupParticipantFilters();
        
        // Setup export button
        const exportBtn = document.getElementById('export-participants');
        if (exportBtn) {
            exportBtn.addEventListener('click', () => {
                this.exportParticipants();
            });
        }
        
        // Setup facility details button
        const detailsBtn = document.getElementById('facility-details-btn');
        if (detailsBtn) {
            detailsBtn.addEventListener('click', () => {
                this.showFacilityDetails();
            });
        }
        
        // Setup contact facility button
        const contactBtn = document.getElementById('contact-facility-btn');
        if (contactBtn) {
            contactBtn.addEventListener('click', () => {
                this.showContactInfo();
            });
        }
    }
    
    showFacilityDetails() {
        if (!this.currentFacility) return;
        
        const modalContent = `
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Facility Name</label>
                        <div class="font-semibold">${this.currentFacility.name}</div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Type</label>
                        <div>${this.currentFacilityType?.name || 'Unknown'}</div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">County</label>
                        <div>${this.currentCounty?.name || 'Unknown'}</div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">MFL Code</label>
                        <div>Loading...</div>
                    </div>
                </div>
            </div>
        `;
        
        const contentEl = document.getElementById('facility-details-content');
        const modalEl = document.getElementById('facility-details-modal');
        if (contentEl && modalEl) {
            contentEl.innerHTML = modalContent;
            modalEl.classList.remove('hidden');
        }
    }
    
    showContactInfo() {
        alert('Contact information feature - would show facility contact details, management, etc.');
    }
    
    exportParticipants() {
        if (!this.currentFacility) return;
        
        this.apiGet(`/facility/${this.currentFacility.id}/participants/export`, {
            type: this.currentType,
            year: this.currentYear
        })
        .then(data => {
            if (data.download_url) {
                window.open(data.download_url, '_blank');
                this.showSuccess(`Exporting ${data.record_count} participants`);
            }
        })
        .catch(error => {
            console.error('Export error:', error);
            this.showError('Export failed');
        });
    }
    
    exportCurrentView() {
        const exportData = {
            level: this.currentLevel,
            type: this.currentType,
            year: this.currentYear
        };
        
        if (this.currentCounty) exportData.county_id = this.currentCounty.id;
        if (this.currentFacilityType) exportData.facility_type_id = this.currentFacilityType.id;
        if (this.currentFacility) exportData.facility_id = this.currentFacility.id;
        
        this.apiPost('/export', exportData)
        .then(data => {
            if (data.download_url) {
                window.open(data.download_url, '_blank');
                this.showSuccess('Export started');
            }
        })
        .catch(error => {
            console.error('Export error:', error);
            this.showError('Export failed');
        });
    }
    
    async refreshCurrentLevel() {
        if (this.currentLevel === 'national') {
            await this.loadNationalData();
        } else if (this.currentLevel === 'county' && this.currentCounty) {
            await this.loadCountyData(this.currentCounty.id);
        } else if (this.currentLevel === 'facility-type' && this.currentCounty && this.currentFacilityType) {
            await this.loadFacilityTypeData(this.currentCounty.id, this.currentFacilityType.id);
        } else if (this.currentLevel === 'facility' && this.currentFacility) {
            await this.loadFacilityData(this.currentFacility.id);
        }
    }
    
    // Utility methods
    getCoverageColorClass(percentage) {
        if (percentage >= 80) return 'coverage-high';
        if (percentage >= 40) return 'coverage-medium';
        if (percentage >= 1) return 'coverage-low';
        return 'coverage-none';
    }
    
    getCoverageTextColor(percentage) {
        if (percentage >= 80) return 'text-green-600';
        if (percentage >= 50) return 'text-yellow-600';
        return 'text-red-600';
    }
    
    getPriorityClass(priority) {
        const classes = {
            'High': 'bg-red-100 text-red-800',
            'Medium': 'bg-yellow-100 text-yellow-800',
            'Low': 'bg-green-100 text-green-800'
        };
        return classes[priority] || 'bg-gray-100 text-gray-800';
    }
    
    getActionBorderColor(priority) {
        const colors = {
            'High': 'border-red-500',
            'Medium': 'border-yellow-500',
            'Low': 'border-green-500'
        };
        return colors[priority] || 'border-gray-500';
    }
    
    getInsightClass(type) {
        const classes = {
            'success': 'bg-green-100 border border-green-200',
            'warning': 'bg-yellow-100 border border-yellow-200',
            'alert': 'bg-red-100 border border-red-200',
            'info': 'bg-blue-100 border border-blue-200'
        };
        return classes[type] || 'bg-gray-100 border border-gray-200';
    }
    
    getInsightIcon(type) {
        const icons = {
            'success': '✅',
            'warning': '⚠️',
            'alert': '🚨',
            'info': 'ℹ️'
        };
        return icons[type] || '📊';
    }
    
    getStatusClass(status) {
        const statusClasses = {
            'completed': 'bg-green-100 text-green-800',
            'in_progress': 'bg-yellow-100 text-yellow-800',
            'in-progress': 'bg-yellow-100 text-yellow-800',
            'dropped': 'bg-red-100 text-red-800',
            'registered': 'bg-blue-100 text-blue-800',
            'ongoing': 'bg-blue-100 text-blue-800',
            'new': 'bg-gray-100 text-gray-800',
            'all_completed': 'bg-green-100 text-green-800',
            'has_active': 'bg-yellow-100 text-yellow-800',
            'partially_completed': 'bg-orange-100 text-orange-800',
            'enrolled_only': 'bg-gray-100 text-gray-800'
        };
        
        return statusClasses[status] || 'bg-gray-100 text-gray-800';
    }
    
    formatNumber(num) {
        if (typeof num !== 'number') return '0';
        return num >= 1000 ? num.toLocaleString() : num.toString();
    }
    
    setElementText(elementId, text) {
        const element = document.getElementById(elementId);
        if (element) {
            element.textContent = text;
        }
    }
    
    showError(message) {
        console.error('Dashboard Error:', message);
        
        // Show user-friendly error message
        const toast = this.createToast(message, 'error');
        document.body.appendChild(toast);
        
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 5000);
    }
    
    showSuccess(message) {
        const toast = this.createToast(message, 'success');
        document.body.appendChild(toast);
        
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 3000);
    }
    
    createToast(message, type) {
        const toast = document.createElement('div');
        const bgColor = type === 'error' ? 'bg-red-100 border-red-400 text-red-700' : 'bg-green-100 border-green-400 text-green-700';
        const icon = type === 'error' ? '⚠️' : '✅';
        
        toast.className = `fixed top-4 right-4 ${bgColor} px-4 py-3 rounded border z-50`;
        toast.innerHTML = `
            <div class="flex items-center">
                <span class="mr-2">${icon}</span>
                <span>${message}</span>
                <button class="ml-4 text-current hover:opacity-70" onclick="this.parentElement.parentElement.remove()">×</button>
            </div>
        `;
        
        return toast;
    }
}

// Initialize dashboard when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    try {
        window.dashboard = new ProgressiveDashboard();
    } catch (error) {
        console.error('Failed to initialize dashboard:', error);
        
        // Show fallback error to user
        const fallbackError = document.createElement('div');
        fallbackError.className = 'fixed top-4 right-4 bg-red-100 border-red-400 text-red-700 px-4 py-3 rounded border z-50';
        fallbackError.innerHTML = `
            <div class="flex items-center">
                <span class="mr-2">⚠️</span>
                <span>Dashboard failed to initialize. Please refresh the page.</span>
                <button class="ml-4 text-current hover:opacity-70" onclick="location.reload()">Refresh</button>
            </div>
        `;
        document.body.appendChild(fallbackError);
    }
});

// Fallback for older browsers or if DOM is already loaded
if (document.readyState !== 'loading') {
    try {
        window.dashboard = new ProgressiveDashboard();
    } catch (error) {
        console.error('Immediate dashboard initialization failed:', error);
    }
}