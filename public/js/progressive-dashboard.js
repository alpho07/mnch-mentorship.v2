class ProgressiveDashboard {
    constructor() {
        this.currentView = 'national';
        this.currentType = 'global_training';
        this.currentYear = 'all';
        this.currentCountyId = null;
        this.currentFacilityTypeId = null;
        this.currentFacilityId = null;
        this.currentDepartmentId = null;
        this.currentTrainingId = null;
        this.charts = {};
        this.data = {};
        
        this.init();
    }

    async init() {
        this.setupEventListeners();
        await this.loadAvailableYears();
        await this.showNationalView();
    }

    setupEventListeners() {
        // Global filter changes
        document.getElementById('training-type-filter').addEventListener('change', (e) => {
            this.currentType = e.target.value;
            this.refreshCurrentView();
        });

        document.getElementById('year-filter').addEventListener('change', (e) => {
            this.currentYear = e.target.value;
            this.refreshCurrentView();
        });

        document.getElementById('refresh-data').addEventListener('click', () => {
            this.clearCache();
            this.refreshCurrentView();
        });

        // Search and filter handlers
        this.setupSearchFilters();
        
        // Modal close handlers
        document.addEventListener('click', (e) => {
            if (e.target.matches('.modal-backdrop')) {
                this.closeAllModals();
            }
        });

        // Escape key to close modals
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.closeAllModals();
            }
        });
    }

    setupSearchFilters() {
        // Debounced search handlers
        let searchTimeout;
        
        const setupSearchInput = (inputId, filterFunction) => {
            const input = document.getElementById(inputId);
            if (input) {
                input.addEventListener('input', (e) => {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => {
                        filterFunction(e.target.value);
                    }, 300);
                });
            }
        };

        setupSearchInput('facility-search', (query) => this.filterFacilities(query));
        setupSearchInput('participant-search', (query) => this.filterParticipants(query));
        setupSearchInput('staff-search', (query) => this.filterStaff(query));
        setupSearchInput('training-participant-search', (query) => this.filterTrainingParticipants(query));
        setupSearchInput('department-staff-search', (query) => this.filterDepartmentStaff(query));
    }

    async loadAvailableYears() {
        try {
            const response = await this.makeApiCall(`/analytics/progressive-dashboard/api/years?type=${this.currentType}`);
            const years = response;
            
            const yearFilter = document.getElementById('year-filter');
            yearFilter.innerHTML = '<option value="all">All Years</option>';
            
            years.forEach(year => {
                const option = document.createElement('option');
                option.value = year;
                option.textContent = year;
                yearFilter.appendChild(option);
            });
        } catch (error) {
            console.error('Error loading available years:', error);
        }
    }

    async makeApiCall(url, options = {}) {
        try {
            const response = await fetch(url, {
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    ...options.headers
                },
                ...options
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            return await response.json();
        } catch (error) {
            this.showError(`API call failed: ${error.message}`);
            throw error;
        }
    }

    showLoading() {
        document.getElementById('loading-state').classList.remove('hidden');
        document.getElementById('error-state').classList.add('hidden');
        this.hideAllViews();
    }

    hideLoading() {
        document.getElementById('loading-state').classList.add('hidden');
    }

    showError(message) {
        document.getElementById('error-message').textContent = message;
        document.getElementById('error-state').classList.remove('hidden');
        document.getElementById('loading-state').classList.add('hidden');
        this.hideAllViews();
    }

    hideAllViews() {
        ['national-view', 'county-view', 'facility-type-view', 'facility-view'].forEach(viewId => {
            document.getElementById(viewId).classList.add('hidden');
        });
    }

    updateBreadcrumb(items) {
        const breadcrumb = document.getElementById('breadcrumb');
        breadcrumb.innerHTML = items.map((item, index) => {
            if (index === items.length - 1) {
                return `<span class="text-gray-800 font-medium">${item.text}</span>`;
            } else {
                return `<span class="breadcrumb-link cursor-pointer" onclick="${item.action}">${item.text}</span>`;
            }
        }).join(' <span class="text-gray-400">></span> ');
    }

    // LEVEL 0: NATIONAL VIEW
    async showNationalView() {
        this.currentView = 'national';
        this.currentCountyId = null;
        this.currentFacilityTypeId = null;
        this.currentFacilityId = null;
        
        this.showLoading();
        this.updateBreadcrumb([{ text: 'National Overview' }]);

        try {
            const data = await this.makeApiCall(`/analytics/progressive-dashboard/api/national?type=${this.currentType}&year=${this.currentYear}`);
            this.data.national = data;
            
            this.renderNationalView(data);
            this.hideLoading();
            document.getElementById('national-view').classList.remove('hidden');
        } catch (error) {
            console.error('Error loading national data:', error);
        }
    }

    renderNationalView(data) {
        // Update summary stats
        document.getElementById('national-counties').textContent = data.national_summary.total_counties;
        document.getElementById('national-facilities').textContent = `${data.national_summary.covered_facilities}/${data.national_summary.total_facilities}`;
        document.getElementById('national-participants').textContent = data.national_summary.total_participants.toLocaleString();
        document.getElementById('national-coverage').textContent = `${Math.round(data.national_summary.average_coverage)}%`;

        // Render counties list
        this.renderCountiesList(data.counties);
        
        // Render insights
        this.renderInsights(data.insights, 'insights-content');
        
        // Setup county sorting
        document.getElementById('county-sort').addEventListener('change', (e) => {
            this.sortCounties(data.counties, e.target.value);
        });
    }

    renderCountiesList(counties) {
        const container = document.getElementById('counties-list');
        container.innerHTML = counties.map(county => `
            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 cursor-pointer transition-colors"
                 onclick="dashboard.showCountyView(${county.id})">
                <div class="flex items-center space-x-3">
                    <span class="coverage-indicator coverage-${this.getCoverageClass(county.coverage_percentage)}"></span>
                    <div>
                        <div class="font-medium text-gray-800">${county.name}</div>
                        <div class="text-sm text-gray-600">${county.covered_facilities}/${county.total_facilities} facilities</div>
                    </div>
                </div>
                <div class="text-right">
                    <div class="font-bold text-blue-600">${county.coverage_percentage}%</div>
                    <div class="text-sm text-gray-600">${county.participant_count} participants</div>
                </div>
            </div>
        `).join('');
    }

    sortCounties(counties, criteria) {
        let sorted;
        switch (criteria) {
            case 'coverage':
                sorted = [...counties].sort((a, b) => b.coverage_percentage - a.coverage_percentage);
                break;
            case 'participants':
                sorted = [...counties].sort((a, b) => b.participant_count - a.participant_count);
                break;
            case 'facilities':
                sorted = [...counties].sort((a, b) => b.total_facilities - a.total_facilities);
                break;
            case 'name':
                sorted = [...counties].sort((a, b) => a.name.localeCompare(b.name));
                break;
            default:
                sorted = counties;
        }
        this.renderCountiesList(sorted);
    }

    // LEVEL 1: COUNTY VIEW
    async showCountyView(countyId) {
        this.currentView = 'county';
        this.currentCountyId = countyId;
        this.currentFacilityTypeId = null;
        this.currentFacilityId = null;
        
        this.showLoading();

        try {
            const data = await this.makeApiCall(`/analytics/progressive-dashboard/api/county/${countyId}?type=${this.currentType}&year=${this.currentYear}`);
            this.data.county = data;
            
            this.updateBreadcrumb([
                { text: 'National Overview', action: 'dashboard.showNationalView()' },
                { text: data.county.name }
            ]);
            
            this.renderCountyView(data);
            this.hideLoading();
            document.getElementById('county-view').classList.remove('hidden');
        } catch (error) {
            console.error('Error loading county data:', error);
        }
    }

    renderCountyView(data) {
        // Update header
        document.getElementById('county-name').textContent = data.county.name;
        document.getElementById('county-summary').textContent = `${data.county.subcounties_count} subcounties, ${data.county.facilities_count} facilities`;
        document.getElementById('county-coverage-percent').textContent = `${data.coverage.coverage_percentage}%`;

        // Update metrics
        document.getElementById('county-total-facilities').textContent = data.coverage.total_facilities;
        document.getElementById('county-covered-facilities').textContent = data.coverage.covered_facilities;
        document.getElementById('county-participants').textContent = data.coverage.participant_count.toLocaleString();
        document.getElementById('county-programs').textContent = data.coverage.program_count;

        // Render facility types with drill-down capability
        this.renderFacilityTypesList(data.facility_types);
        
        // Render departments with drill-down capability
        this.renderDepartmentsList(data.departments);
        
        // Render insights and actions
        this.renderInsights(data.insights, 'county-insights');
        this.renderRecommendedActions(data.recommended_actions);
    }

    renderFacilityTypesList(facilityTypes) {
        const container = document.getElementById('facility-types-list');
        container.innerHTML = facilityTypes.map(type => `
            <div class="facility-type-card flex items-center justify-between p-3 border rounded-lg hover:border-blue-300 transition-colors"
                 onclick="dashboard.showFacilityTypeView(${this.currentCountyId}, ${type.id})">
                <div>
                    <div class="font-medium text-gray-800">${type.name}</div>
                    <div class="text-sm text-gray-600">${type.covered}/${type.total} facilities covered</div>
                </div>
                <div class="text-right">
                    <div class="font-bold text-blue-600">${type.coverage_percentage}%</div>
                    <div class="text-xs px-2 py-1 rounded bg-${this.getPriorityColor(type.priority)}-100 text-${this.getPriorityColor(type.priority)}-800">
                        ${type.priority} Priority
                    </div>
                </div>
            </div>
        `).join('');
    }

    renderDepartmentsList(departments) {
        const container = document.getElementById('departments-list');
        container.innerHTML = departments.map(dept => `
            <div class="department-card flex items-center justify-between p-3 border rounded-lg hover:border-blue-300 transition-colors"
                 onclick="dashboard.showDepartmentStaff(${this.currentCountyId}, '${dept.name}')">
                <div>
                    <div class="font-medium text-gray-800">${dept.name}</div>
                    <div class="text-sm text-gray-600">${dept.trained}/${dept.total} staff trained</div>
                </div>
                <div class="text-right">
                    <div class="font-bold text-green-600">${dept.coverage_percentage}%</div>
                    <div class="text-sm text-gray-600">${dept.untrained} untrained</div>
                </div>
            </div>
        `).join('');
    }

    // LEVEL 2: FACILITY TYPE VIEW
    async showFacilityTypeView(countyId, facilityTypeId) {
        this.currentView = 'facility-type';
        this.currentCountyId = countyId;
        this.currentFacilityTypeId = facilityTypeId;
        this.currentFacilityId = null;
        
        this.showLoading();

        try {
            const data = await this.makeApiCall(`/analytics/progressive-dashboard/facility-type/${countyId}/${facilityTypeId}?type=${this.currentType}&year=${this.currentYear}`);
            this.data.facilityType = data;
            
            this.updateBreadcrumb([
                { text: 'National Overview', action: 'dashboard.showNationalView()' },
                { text: data.county.name, action: `dashboard.showCountyView(${countyId})` },
                { text: data.facility_type.name }
            ]);
            
            this.renderFacilityTypeView(data);
            this.hideLoading();
            document.getElementById('facility-type-view').classList.remove('hidden');
        } catch (error) {
            console.error('Error loading facility type data:', error);
        }
    }

    renderFacilityTypeView(data) {
        // Update header
        document.getElementById('facility-type-name').textContent = data.facility_type.name;
        document.getElementById('facility-type-county').textContent = `${data.county.name} County`;
        
        // Update metrics
        document.getElementById('type-total-facilities').textContent = data.summary.total;
        document.getElementById('type-covered-facilities').textContent = data.summary.covered;
        document.getElementById('type-total-participants').textContent = data.summary.total_participants.toLocaleString();
        document.getElementById('type-coverage-percentage').textContent = `${Math.round((data.summary.covered / data.summary.total) * 100)}%`;

        // Render facilities grid
        this.renderFacilitiesGrid(data.facilities);
        
        // Render insights
        this.renderInsights(data.insights, 'performance-insights');
    }

    renderFacilitiesGrid(facilities) {
        const container = document.getElementById('facilities-grid');
        container.innerHTML = facilities.map(facility => `
            <div class="participant-card bg-white border rounded-lg p-4 shadow-sm"
                 onclick="dashboard.showFacilityView(${facility.id})">
                <div class="flex items-center justify-between mb-2">
                    <h5 class="font-medium text-gray-800 truncate">${facility.name}</h5>
                    <span class="coverage-indicator coverage-${facility.is_covered ? 'high' : 'none'}"></span>
                </div>
                <div class="text-sm text-gray-600 mb-2">${facility.subcounty}</div>
                <div class="text-sm text-gray-600 mb-2">MFL: ${facility.mfl_code || 'N/A'}</div>
                <div class="flex justify-between text-sm">
                    <span>${facility.participant_count} participants</span>
                    <span>${facility.completion_rate}% completed</span>
                </div>
            </div>
        `).join('');
    }

    // LEVEL 3: FACILITY VIEW
    async showFacilityView(facilityId) {
        this.currentView = 'facility';
        this.currentFacilityId = facilityId;
        
        this.showLoading();

        try {
            const data = await this.makeApiCall(`/analytics/progressive-dashboard/api/facility/${facilityId}?type=${this.currentType}&year=${this.currentYear}`);
            this.data.facility = data;
            
            this.updateBreadcrumb([
                { text: 'National Overview', action: 'dashboard.showNationalView()' },
                { text: data.facility.county, action: `dashboard.showCountyView(${this.currentCountyId})` },
                { text: data.facility.type, action: `dashboard.showFacilityTypeView(${this.currentCountyId}, ${this.currentFacilityTypeId})` },
                { text: data.facility.name }
            ]);
            
            this.renderFacilityView(data);
            this.hideLoading();
            document.getElementById('facility-view').classList.remove('hidden');
        } catch (error) {
            console.error('Error loading facility data:', error);
        }
    }

    renderFacilityView(data) {
        // Update header
        document.getElementById('facility-name').textContent = data.facility.name;
        document.getElementById('facility-details').textContent = `${data.facility.type} • ${data.facility.county}, ${data.facility.subcounty} • MFL: ${data.facility.mfl_code || 'N/A'}`;
        
        // Update metrics
        document.getElementById('facility-participants').textContent = data.performance.total_participants;
        document.getElementById('facility-programs').textContent = data.performance.program_count;
        document.getElementById('facility-departments').textContent = data.performance.departments_represented;
        document.getElementById('facility-completion').textContent = `${data.performance.completion_rate}%`;

        // Render training history with drill-down capability
        this.renderTrainingHistory(data.training_history);
        
        // Render all facility users (enhanced)
        this.renderFacilityUsers(data.facility.id);
        
        // Render active participants (original)
        this.renderParticipantsGrid(data.participants);
        
        // Render charts
        this.renderDepartmentChart(data.department_breakdown);
        this.renderCadreChart(data.cadre_breakdown);
        
        // Render insights
        this.renderInsights(data.insights, 'facility-insights');
    }

    renderTrainingHistory(trainingHistory) {
        const container = document.getElementById('training-history');
        if (!trainingHistory || trainingHistory.length === 0) {
            container.innerHTML = '<p class="text-gray-600">No training history available.</p>';
            return;
        }
        
        container.innerHTML = trainingHistory.map(training => `
            <div class="timeline-item" onclick="dashboard.showTrainingParticipants(${training.id})">
                <div class="flex items-center justify-between">
                    <div>
                        <h5 class="font-medium text-gray-800">${training.title}</h5>
                        <p class="text-sm text-gray-600">${training.start_date} - ${training.end_date}</p>
                        <p class="text-sm text-gray-600">${training.participants_count} participants • ${training.completion_rate}% completion</p>
                    </div>
                    <div class="text-right">
                        <span class="text-xs px-2 py-1 rounded bg-blue-100 text-blue-800">${training.status}</span>
                        <div class="text-xs text-gray-500 mt-1">Click to view participants</div>
                    </div>
                </div>
            </div>
        `).join('');
    }

    async renderFacilityUsers(facilityId) {
        try {
            const response = await this.makeApiCall(`/analytics/progressive-dashboard/api/facility/${facilityId}/users?type=${this.currentType}&year=${this.currentYear}`);
            const users = response.users;
            
            const container = document.getElementById('facility-users-grid');
            container.innerHTML = users.map(user => `
                <div class="participant-card bg-white border rounded-lg p-4 shadow-sm"
                     onclick="dashboard.showParticipantProfile(${user.id})">
                    <div class="flex items-center justify-between mb-2">
                        <h5 class="font-medium text-gray-800 truncate">${user.name}</h5>
                        <span class="training-status-badge status-${this.getTrainingStatusClass(user.training_status)}">
                            ${user.training_status}
                        </span>
                    </div>
                    <div class="text-sm text-gray-600 mb-1">${user.department}</div>
                    <div class="text-sm text-gray-600 mb-2">${user.cadre}</div>
                    
                    <!-- Training Timeline & Recency -->
                    <div class="mt-2 p-2 bg-gray-50 rounded">
                        <div class="text-xs text-gray-600">
                            ${user.last_training_date ? 
                                `Last Training: ${user.last_training_date} (${user.days_since_last_training} days ago)` : 
                                'Never attended training'
                            }
                        </div>
                        ${user.training_recency ? `
                            <div class="training-recency recency-${user.training_recency.toLowerCase()} mt-1">
                                ${user.training_recency}
                            </div>
                        ` : ''}
                    </div>
                    
                    <div class="flex justify-between text-sm mt-2">
                        <span>${user.trainings_completed || 0} completed</span>
                        <span>${user.completion_rate || 0}% rate</span>
                    </div>
                </div>
            `).join('');
        } catch (error) {
            console.error('Error loading facility users:', error);
        }
    }

    renderParticipantsGrid(participants) {
        const container = document.getElementById('participants-grid');
        container.innerHTML = participants.map(participant => `
            <div class="participant-card bg-white border rounded-lg p-4 shadow-sm"
                 onclick="dashboard.showParticipantProfile(${participant.user_id || participant.id})">
                <div class="flex items-center justify-between mb-2">
                    <h5 class="font-medium text-gray-800 truncate">${participant.name}</h5>
                    <span class="text-xs px-2 py-1 rounded bg-green-100 text-green-800">${participant.status}</span>
                </div>
                <div class="text-sm text-gray-600 mb-1">${participant.department}</div>
                <div class="text-sm text-gray-600 mb-2">${participant.cadre}</div>
                <div class="text-sm text-gray-600 mb-2">${participant.training || 'Multiple trainings'}</div>
                <div class="flex justify-between text-sm">
                    <span>${participant.phone || 'No phone'}</span>
                    <span>${participant.completion_date || 'In progress'}</span>
                </div>
            </div>
        `).join('');
    }

    // ENHANCED MODALS AND DRILL-DOWN FEATURES

    async showTrainingParticipants(trainingId) {
        this.currentTrainingId = trainingId;
        
        try {
            const response = await this.makeApiCall(`/analytics/progressive-dashboard/api/training/${trainingId}/participants?type=${this.currentType}&year=${this.currentYear}`);
            
            // Get training details
            const training = this.data.facility.training_history.find(t => t.id == trainingId);
            
            // Populate modal
            document.getElementById('modal-training-title').textContent = training.title;
            
            // Training basic info
            document.getElementById('training-basic-info').innerHTML = `
                <div>
                    <h5 class="font-semibold text-gray-800">Training Details</h5>
                    <p class="text-gray-600">Identifier: ${training.identifier}</p>
                    <p class="text-gray-600">Duration: ${training.start_date} - ${training.end_date}</p>
                    <p class="text-gray-600">Status: ${training.status}</p>
                </div>
                <div>
                    <h5 class="font-semibold text-gray-800">Participation Metrics</h5>
                    <p class="text-gray-600">Total Participants: ${training.participants_count}</p>
                    <p class="text-gray-600">Completion Rate: ${training.completion_rate}%</p>
                </div>
            `;
            
            // Render participants
            this.renderTrainingParticipantsGrid(response.participants);
            
            // Show modal
            document.getElementById('training-modal').classList.remove('hidden');
            
        } catch (error) {
            console.error('Error loading training participants:', error);
        }
    }

    renderTrainingParticipantsGrid(participants) {
        const container = document.getElementById('training-participants-grid');
        container.innerHTML = participants.map(participant => `
            <div class="participant-card bg-white border rounded-lg p-4 shadow-sm"
                 onclick="dashboard.showParticipantProfile(${participant.user_id})">
                <div class="flex items-center justify-between mb-2">
                    <h5 class="font-medium text-gray-800 truncate">${participant.name}</h5>
                    <span class="text-xs px-2 py-1 rounded bg-${this.getStatusColor(participant.completion_status)}-100 text-${this.getStatusColor(participant.completion_status)}-800">
                        ${participant.completion_status}
                    </span>
                </div>
                <div class="text-sm text-gray-600 mb-1">${participant.department}</div>
                <div class="text-sm text-gray-600 mb-2">${participant.cadre}</div>
                <div class="text-sm text-gray-600 mb-2">Enrolled: ${participant.enrollment_date}</div>
                <div class="flex justify-between text-sm">
                    <span>${participant.phone}</span>
                    <span>${participant.completion_date || 'In Progress'}</span>
                </div>
            </div>
        `).join('');
    }

    async showDepartmentStaff(countyId, departmentName) {
        this.currentDepartmentId = departmentName;
        
        try {
            const response = await this.makeApiCall(`/analytics/progressive-dashboard/api/county/${countyId}/department/${encodeURIComponent(departmentName)}/participants?type=${this.currentType}&year=${this.currentYear}`);
            
            // Populate modal
            document.getElementById('modal-department-title').textContent = `${departmentName} Department Staff`;
            
            // Department summary
            document.getElementById('department-summary').innerHTML = `
                <div class="bg-blue-50 p-4 rounded-lg text-center">
                    <div class="text-2xl font-bold text-blue-600">${response.summary.total_staff}</div>
                    <div class="text-sm text-gray-600">Total Staff</div>
                </div>
                <div class="bg-green-50 p-4 rounded-lg text-center">
                    <div class="text-2xl font-bold text-green-600">${response.summary.trained_staff}</div>
                    <div class="text-sm text-gray-600">Trained Staff</div>
                </div>
                <div class="bg-orange-50 p-4 rounded-lg text-center">
                    <div class="text-2xl font-bold text-orange-600">${response.summary.coverage_percentage}%</div>
                    <div class="text-sm text-gray-600">Coverage Rate</div>
                </div>
            `;
            
            // Render department staff
            this.renderDepartmentStaffGrid(response.staff);
            
            // Show modal
            document.getElementById('department-modal').classList.remove('hidden');
            
        } catch (error) {
            console.error('Error loading department staff:', error);
        }
    }

    renderDepartmentStaffGrid(staff) {
        const container = document.getElementById('department-staff-grid');
        container.innerHTML = staff.map(person => `
            <div class="participant-card bg-white border rounded-lg p-4 shadow-sm"
                 onclick="dashboard.showParticipantProfile(${person.id})">
                <div class="flex items-center justify-between mb-2">
                    <h5 class="font-medium text-gray-800 truncate">${person.name}</h5>
                    <span class="training-status-badge status-${this.getTrainingStatusClass(person.training_status)}">
                        ${person.training_status}
                    </span>
                </div>
                <div class="text-sm text-gray-600 mb-1">${person.cadre}</div>
                <div class="text-sm text-gray-600 mb-2">${person.facility_name}</div>
                
                <!-- Training Timeline & Recency -->
                <div class="mt-2 p-2 bg-gray-50 rounded">
                    <div class="text-xs text-gray-600">
                        ${person.last_training_date ? 
                            `Last Training: ${person.last_training_date} (${person.days_since_last_training} days ago)` : 
                            'Never attended training'
                        }
                    </div>
                    ${person.training_recency ? `
                        <div class="training-recency recency-${person.training_recency.toLowerCase()} mt-1">
                            ${person.training_recency}
                        </div>
                    ` : ''}
                </div>
                
                <div class="flex justify-between text-sm mt-2">
                    <span>${person.trainings_completed || 0} completed</span>
                    <span>${person.completion_rate || 0}% rate</span>
                </div>
            </div>
        `).join('');
    }

    async showParticipantProfile(participantId) {
        try {
            const response = await this.makeApiCall(`/analytics/progressive-dashboard/api/participant/${participantId}?type=${this.currentType}&year=${this.currentYear}`);
            
            // Populate modal
            document.getElementById('modal-participant-name').textContent = response.participant.user.name;
            
            // Basic info
            document.getElementById('participant-basic-info').innerHTML = `
                <div>
                    <h5 class="font-semibold text-gray-800">Personal Information</h5>
                    <p class="text-gray-600">Phone: ${response.participant.user.phone || 'Not provided'}</p>
                    <p class="text-gray-600">Email: ${response.participant.user.email || 'Not provided'}</p>
                    <p class="text-gray-600">Department: ${response.participant.user.department}</p>
                    <p class="text-gray-600">Cadre: ${response.participant.user.cadre}</p>
                </div>
                <div>
                    <h5 class="font-semibold text-gray-800">Facility Information</h5>
                    <p class="text-gray-600">Facility: ${response.participant.user.facility.name}</p>
                    <p class="text-gray-600">Type: ${response.participant.user.facility.type}</p>
                    <p class="text-gray-600">County: ${response.participant.user.facility.county}</p>
                </div>
            `;
            
            // Training Timeline & Recency
            this.renderParticipantTrainingTimeline(response.training_timeline);
            
            // Enhanced Assessment Summary
            this.renderDetailedAssessmentSummary(response.detailed_assessment_summary);
            
            // Training History
            this.renderParticipantTrainingHistory(response.training_history);
            
            // Performance Metrics
            this.renderParticipantPerformanceMetrics(response.performance_metrics);
            
            // Show modal
            document.getElementById('participant-modal').classList.remove('hidden');
            
        } catch (error) {
            console.error('Error loading participant profile:', error);
        }
    }

    renderParticipantTrainingTimeline(timeline) {
        const container = document.getElementById('training-recency-info');
        container.innerHTML = `
            <div class="bg-blue-50 p-3 rounded-lg text-center">
                <div class="font-bold text-blue-600">${timeline.last_training_date || 'Never'}</div>
                <div class="text-xs text-gray-600">Last Training</div>
            </div>
            <div class="bg-green-50 p-3 rounded-lg text-center">
                <div class="font-bold text-green-600">${timeline.last_training_year || 'N/A'}</div>
                <div class="text-xs text-gray-600">Last Year Attended</div>
            </div>
            <div class="bg-orange-50 p-3 rounded-lg text-center">
                <div class="font-bold text-orange-600">${timeline.days_since_last_training || 'N/A'}</div>
                <div class="text-xs text-gray-600">Days Since Last</div>
            </div>
        `;
        
        const statusContainer = document.getElementById('training-status');
        statusContainer.innerHTML = `
            <div class="flex items-center justify-between">
                <div>
                    <h5 class="font-medium text-gray-800">Training Status</h5>
                    <p class="text-gray-600">${timeline.status_description}</p>
                </div>
                <span class="training-status-badge status-${this.getTrainingStatusClass(timeline.status)}">
                    ${timeline.status}
                </span>
            </div>
            ${timeline.period_since_start ? `
                <div class="mt-2 text-sm text-gray-600">
                    Period since first training: ${timeline.period_since_start}
                </div>
            ` : ''}
        `;
    }

    renderDetailedAssessmentSummary(assessmentSummary) {
        const container = document.getElementById('assessment-categories');
        
        if (!assessmentSummary || !assessmentSummary.categories) {
            container.innerHTML = '<p class="text-gray-600">No assessment data available.</p>';
            return;
        }
        
        container.innerHTML = assessmentSummary.categories.map(category => `
            <div class="assessment-category assessment-${category.status.toLowerCase()}">
                <div class="flex items-center justify-between mb-2">
                    <h5 class="font-medium text-gray-800">${category.name}</h5>
                    <div class="flex items-center space-x-2">
                        <span class="font-bold text-lg">${category.score}%</span>
                        <span class="text-xs px-2 py-1 rounded bg-${this.getStatusColor(category.status)}-100 text-${this.getStatusColor(category.status)}-800">
                            ${category.status}
                        </span>
                    </div>
                </div>
                <div class="text-sm text-gray-600 mb-1">
                    Weight: ${category.weight}% • Attempts: ${category.attempts}
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div class="bg-${this.getStatusColor(category.status)}-500 h-2 rounded-full" style="width: ${category.score}%"></div>
                </div>
            </div>
        `).join('');
        
        // Overall summary
        const overallDiv = document.createElement('div');
        overallDiv.className = 'mt-4 p-4 bg-gray-50 rounded-lg';
        overallDiv.innerHTML = `
            <div class="flex items-center justify-between">
                <h5 class="font-medium text-gray-800">Overall Assessment Performance</h5>
                <div class="text-right">
                    <div class="text-xl font-bold text-blue-600">${assessmentSummary.overall_score}%</div>
                    <div class="text-sm text-gray-600">Weighted Average</div>
                </div>
            </div>
            <div class="mt-2 text-sm text-gray-600">
                Categories Passed: ${assessmentSummary.passed_categories}/${assessmentSummary.total_categories}
            </div>
        `;
        container.appendChild(overallDiv);
    }

    renderParticipantTrainingHistory(trainingHistory) {
        const container = document.getElementById('training-history-list');
        
        if (!trainingHistory || trainingHistory.length === 0) {
            container.innerHTML = '<p class="text-gray-600">No training history available.</p>';
            return;
        }
        
        container.innerHTML = trainingHistory.map(training => `
            <div class="flex items-center justify-between p-3 border rounded-lg">
                <div>
                    <h6 class="font-medium text-gray-800">${training.training_title}</h6>
                    <p class="text-sm text-gray-600">${training.training_type} • Registered: ${training.registration_date}</p>
                    ${training.completion_date ? `<p class="text-sm text-green-600">Completed: ${training.completion_date}</p>` : ''}
                </div>
                <div class="text-right">
                    <div class="font-bold text-blue-600">${training.assessment_score || 0}%</div>
                    <div class="text-xs px-2 py-1 rounded bg-${this.getStatusColor(training.completion_status)}-100 text-${this.getStatusColor(training.completion_status)}-800">
                        ${training.completion_status}
                    </div>
                </div>
            </div>
        `).join('');
    }

    renderParticipantPerformanceMetrics(metrics) {
        const container = document.getElementById('performance-metrics-grid');
        container.innerHTML = `
            <div class="bg-blue-50 p-4 rounded-lg text-center">
                <div class="text-2xl font-bold text-blue-600">${metrics.total_trainings}</div>
                <div class="text-sm text-gray-600">Total Trainings</div>
            </div>
            <div class="bg-green-50 p-4 rounded-lg text-center">
                <div class="text-2xl font-bold text-green-600">${metrics.completed_trainings}</div>
                <div class="text-sm text-gray-600">Completed</div>
            </div>
            <div class="bg-orange-50 p-4 rounded-lg text-center">
                <div class="text-2xl font-bold text-orange-600">${metrics.completion_rate}%</div>
                <div class="text-sm text-gray-600">Completion Rate</div>
            </div>
        `;
        
        if (metrics.latest_activity) {
            container.innerHTML += `
                <div class="col-span-full mt-4 p-3 bg-gray-50 rounded-lg">
                    <div class="text-sm text-gray-600">Latest Activity: ${metrics.latest_activity}</div>
                    <div class="text-sm text-gray-600">Average Score: ${metrics.average_score}%</div>
                </div>
            `;
        }
    }

    // UTILITY METHODS

    getCoverageClass(percentage) {
        if (percentage >= 80) return 'high';
        if (percentage >= 50) return 'medium';
        if (percentage >= 1) return 'low';
        return 'none';
    }

    getPriorityColor(priority) {
        switch (priority.toLowerCase()) {
            case 'high': return 'red';
            case 'medium': return 'yellow';
            case 'low': return 'green';
            default: return 'gray';
        }
    }

    getStatusColor(status) {
        switch (status.toLowerCase()) {
            case 'completed':
            case 'passed':
            case 'high':
                return 'green';
            case 'in_progress':
            case 'pending':
            case 'medium':
                return 'yellow';
            case 'failed':
            case 'dropped':
            case 'low':
                return 'red';
            default:
                return 'gray';
        }
    }

    getTrainingStatusClass(status) {
        switch (status.toLowerCase()) {
            case 'never attended':
            case 'never_attended':
                return 'never';
            case 'trained this year':
            case 'current':
                return 'current';
            case 'recently trained':
            case 'recent':
                return 'recent';
            case 'needs training':
            case 'needs_training':
                return 'needs';
            default:
                return 'never';
        }
    }

    // FILTERING METHODS

    filterFacilities(query) {
        const facilities = document.querySelectorAll('#facilities-grid .participant-card');
        facilities.forEach(card => {
            const text = card.textContent.toLowerCase();
            const show = text.includes(query.toLowerCase());
            card.style.display = show ? 'block' : 'none';
        });
    }

    filterParticipants(query) {
        const participants = document.querySelectorAll('#participants-grid .participant-card');
        participants.forEach(card => {
            const text = card.textContent.toLowerCase();
            const show = text.includes(query.toLowerCase());
            card.style.display = show ? 'block' : 'none';
        });
    }

    filterStaff(query) {
        const staff = document.querySelectorAll('#facility-users-grid .participant-card');
        staff.forEach(card => {
            const text = card.textContent.toLowerCase();
            const show = text.includes(query.toLowerCase());
            card.style.display = show ? 'block' : 'none';
        });
    }

    filterTrainingParticipants(query) {
        const participants = document.querySelectorAll('#training-participants-grid .participant-card');
        participants.forEach(card => {
            const text = card.textContent.toLowerCase();
            const show = text.includes(query.toLowerCase());
            card.style.display = show ? 'block' : 'none';
        });
    }

    filterDepartmentStaff(query) {
        const staff = document.querySelectorAll('#department-staff-grid .participant-card');
        staff.forEach(card => {
            const text = card.textContent.toLowerCase();
            const show = text.includes(query.toLowerCase());
            card.style.display = show ? 'block' : 'none';
        });
    }

    // CHART RENDERING

    renderDepartmentChart(departmentData) {
        const ctx = document.getElementById('facility-departments-canvas').getContext('2d');
        
        if (this.charts.departments) {
            this.charts.departments.destroy();
        }
        
        this.charts.departments = new Chart(ctx, {
            type: 'pie',
            data: {
                labels: departmentData.map(d => d.name),
                datasets: [{
                    data: departmentData.map(d => d.count),
                    backgroundColor: [
                        '#3b82f6', '#ef4444', '#10b981', '#f59e0b',
                        '#8b5cf6', '#06b6d4', '#84cc16', '#f97316'
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
    }

    renderCadreChart(cadreData) {
        const ctx = document.getElementById('facility-cadres-canvas').getContext('2d');
        
        if (this.charts.cadres) {
            this.charts.cadres.destroy();
        }
        
        this.charts.cadres = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: cadreData.map(c => c.name),
                datasets: [{
                    data: cadreData.map(c => c.count),
                    backgroundColor: [
                        '#10b981', '#3b82f6', '#f59e0b', '#ef4444',
                        '#8b5cf6', '#06b6d4', '#84cc16', '#f97316'
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
    }

    // HELPER RENDERING METHODS

    renderInsights(insights, containerId) {
        const container = document.getElementById(containerId);
        if (!insights || insights.length === 0) {
            container.innerHTML = '<p class="text-gray-600">No insights available.</p>';
            return;
        }
        
        container.innerHTML = insights.map(insight => `
            <div class="p-4 border-l-4 border-${this.getInsightColor(insight.type)}-400 bg-${this.getInsightColor(insight.type)}-50">
                <div class="flex items-center mb-2">
                    <h5 class="font-medium text-${this.getInsightColor(insight.type)}-800">${insight.title}</h5>
                </div>
                <p class="text-${this.getInsightColor(insight.type)}-700 text-sm mb-2">${insight.message}</p>
                ${insight.action ? `<p class="text-${this.getInsightColor(insight.type)}-600 text-sm font-medium">Action: ${insight.action}</p>` : ''}
            </div>
        `).join('');
    }

    renderRecommendedActions(actions) {
        const container = document.getElementById('recommended-actions');
        if (!actions || actions.length === 0) {
            container.innerHTML = '<p class="text-gray-600">No recommended actions.</p>';
            return;
        }
        
        container.innerHTML = actions.map(action => `
            <div class="p-4 border rounded-lg bg-white">
                <div class="flex items-center justify-between mb-2">
                    <h5 class="font-medium text-gray-800">${action.title}</h5>
                    <span class="text-xs px-2 py-1 rounded bg-${this.getPriorityColor(action.priority)}-100 text-${this.getPriorityColor(action.priority)}-800">
                        ${action.priority}
                    </span>
                </div>
                <p class="text-gray-600 text-sm mb-2">${action.description}</p>
                <div class="flex justify-between text-sm text-gray-500">
                    <span>${action.estimated_participants} participants</span>
                    <span>${action.timeline}</span>
                </div>
            </div>
        `).join('');
    }

    getInsightColor(type) {
        switch (type.toLowerCase()) {
            case 'success': return 'green';
            case 'warning': return 'yellow';
            case 'alert': return 'red';
            case 'info': return 'blue';
            default: return 'gray';
        }
    }

    // MODAL MANAGEMENT

    closeParticipantModal() {
        document.getElementById('participant-modal').classList.add('hidden');
    }

    closeTrainingModal() {
        document.getElementById('training-modal').classList.add('hidden');
    }

    closeDepartmentModal() {
        document.getElementById('department-modal').classList.add('hidden');
    }

    closeAllModals() {
        this.closeParticipantModal();
        this.closeTrainingModal();
        this.closeDepartmentModal();
    }

    // CACHE AND STATE MANAGEMENT

    async clearCache() {
        try {
            await this.makeApiCall('/analytics/progressive-dashboard/api/clear-cache', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    entity_type: this.currentView,
                    entity_id: this.getCurrentEntityId()
                })
            });
        } catch (error) {
            console.error('Error clearing cache:', error);
        }
    }

    getCurrentEntityId() {
        switch (this.currentView) {
            case 'county': return this.currentCountyId;
            case 'facility-type': return this.currentFacilityTypeId;
            case 'facility': return this.currentFacilityId;
            default: return null;
        }
    }

    async refreshCurrentView() {
        switch (this.currentView) {
            case 'national':
                await this.showNationalView();
                break;
            case 'county':
                if (this.currentCountyId) {
                    await this.showCountyView(this.currentCountyId);
                }
                break;
            case 'facility-type':
                if (this.currentCountyId && this.currentFacilityTypeId) {
                    await this.showFacilityTypeView(this.currentCountyId, this.currentFacilityTypeId);
                }
                break;
            case 'facility':
                if (this.currentFacilityId) {
                    await this.showFacilityView(this.currentFacilityId);
                }
                break;
        }
    }

    retryLoad() {
        this.refreshCurrentView();
    }

    // MAP TOGGLE FUNCTIONS (PLACEHOLDER)
    toggleUncoveredOnly() {
        // Toggle showing only uncovered facilities on county map
        console.log('Toggle uncovered only');
    }

    toggleCoveredOnly() {
        // Toggle showing only covered facilities on facility type map
        console.log('Toggle covered only');
    }
}