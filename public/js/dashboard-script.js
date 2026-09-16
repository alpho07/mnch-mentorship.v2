// Healthcare Training Dashboard - Complete JavaScript Implementation
(function() {
    'use strict';

    // Dashboard state management
    const Dashboard = {
        state: {
            map: null,
            countyLayer: null,
            selectedTraining: null,
            charts: {},
            currentMode: document.querySelector('meta[name="dashboard-mode"]')?.content || 'training',
            currentYear: document.querySelector('meta[name="dashboard-year"]')?.content || '',
            facilityTypeData: null,
            originalTrainingsList: null
        },

        // Coverage levels for map coloring
        coverageLevels: {
            EXTREMELY_HIGH: { min: 300, color: '#15803d', label: 'Extremely High' },
            HIGH: { min: 100, color: '#22c55e', label: 'High' },
            MODERATE: { min: 10, color: '#fef08a', label: 'Moderate' },
            MINIMAL: { min: 1, color: '#ffcccc', label: 'Minimal' },
            NONE: { min: 0, color: '#9ca3af', label: 'No Training' }
        },

        // Chart colors
        chartColors: [
            '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6',
            '#06b6d4', '#84cc16', '#f97316', '#ec4899', '#6366f1'
        ],

        // Utility functions
        getCoverageLevel(participants) {
            for (const level of Object.values(this.coverageLevels)) {
                if (participants >= level.min) return level;
            }
            return this.coverageLevels.NONE;
        },

        getCoverageLevelByPercentage(percentage) {
            if (percentage >= 80) return this.coverageLevels.EXTREMELY_HIGH;
            if (percentage >= 60) return this.coverageLevels.HIGH;
            if (percentage >= 40) return this.coverageLevels.MODERATE;
            if (percentage >= 20) return this.coverageLevels.MINIMAL;
            return this.coverageLevels.NONE;
        },

        getChartColor(index) {
            return this.chartColors[index % this.chartColors.length];
        },

        // Map management
        Map: {
            init() {
                if (Dashboard.state.map) {
                    Dashboard.state.map.remove();
                }

                Dashboard.state.map = L.map('kenyaMap', {
                    center: [-0.5, 37.5],
                    zoom: 7,
                    minZoom: 6,
                    maxZoom: 12,
                    maxBounds: [[-5.5, 33.0], [5.5, 42.0]],
                    maxBoundsViscosity: 1.0
                });

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap contributors',
                    opacity: 0.7
                }).addTo(Dashboard.state.map);

                this.loadCountyData();
            },

            async loadCountyData(filterTrainingId = null) {
                const loadingOverlay = document.querySelector('.loading-overlay');
                if (loadingOverlay) loadingOverlay.classList.add('show');

                try {
                    const params = new URLSearchParams();
                    params.set('mode', Dashboard.state.currentMode);
                    if (Dashboard.state.currentYear) params.set('year', Dashboard.state.currentYear);
                    if (filterTrainingId) params.set('training_id', filterTrainingId);

                    const response = await fetch(`/analytics/dashboard/geojson?${params}`);
                    
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }

                    const data = await response.json();

                    if (Dashboard.state.countyLayer) {
                        Dashboard.state.map.removeLayer(Dashboard.state.countyLayer);
                    }

                    Dashboard.state.countyLayer = L.geoJSON(data, {
                        style: feature => this.getCountyStyle(feature.properties),
                        onEachFeature: (feature, layer) => this.setupCountyInteractions(feature, layer)
                    });

                    Dashboard.state.countyLayer.addTo(Dashboard.state.map);
                    
                    this.updateMapSummary(data);
                    
                    const bounds = Dashboard.state.countyLayer.getBounds();
                    if (bounds.isValid()) {
                        Dashboard.state.map.fitBounds(bounds, { 
                            padding: [20, 20],
                            maxZoom: filterTrainingId ? 9 : 8
                        });
                    }
                } catch (error) {
                    console.error('Map loading error:', error);
                    this.showError(`Failed to load map data: ${error.message}`);
                } finally {
                    if (loadingOverlay) loadingOverlay.classList.remove('show');
                }
            },

            updateMapSummary(geoJsonData) {
                let totalFacilities = 0;
                let facilitiesWithTraining = 0;
                let countiesWithTraining = 0;
                let totalParticipants = 0;
                const totalCounties = 47;

                if (geoJsonData.features) {
                    geoJsonData.features.forEach(feature => {
                        const props = feature.properties;
                        const facilities = props.total_facilities || 0;
                        const facilitiesWithPrograms = props.facilities_with_programs || 0;
                        const participants = props.total_participants || 0;

                        totalFacilities += facilities;
                        facilitiesWithTraining += facilitiesWithPrograms;
                        totalParticipants += participants;

                        if (facilitiesWithPrograms > 0) {
                            countiesWithTraining++;
                        }
                    });
                }

                const facilityCoverage = totalFacilities > 0 ? 
                    Math.round((facilitiesWithTraining / totalFacilities) * 100) : 0;
                const countyCoverage = Math.round((countiesWithTraining / totalCounties) * 100);
                const overallCoverage = facilityCoverage;

                // Update displays
                this.updateCoverageDisplay('facilityCoveragePercent', 'facilitiesRatio', 'facilityIndicator', 
                    facilityCoverage, `${facilitiesWithTraining.toLocaleString()}/${totalFacilities.toLocaleString()} facilities`);
                
                this.updateCoverageDisplay('countyCoveragePercent', 'countiesRatio', 'countyIndicator', 
                    countyCoverage, `${countiesWithTraining}/47 counties`);
                
                this.updateCoverageDisplay('overallCoverage', 'participantsCount', 'overallIndicator', 
                    overallCoverage, `${totalParticipants.toLocaleString()} participants`);

                this.updateQuickInsights(facilityCoverage, countyCoverage, overallCoverage, 
                    countiesWithTraining, facilitiesWithTraining, totalParticipants);
            },

            updateCoverageDisplay(percentageId, detailId, indicatorId, percentage, detailText) {
                const percentageEl = document.getElementById(percentageId);
                const detailEl = document.getElementById(detailId);
                const indicatorEl = document.getElementById(indicatorId);
                
                if (percentageEl) {
                    percentageEl.textContent = `${percentage}%`;
                    const level = Dashboard.getCoverageLevelByPercentage(percentage);
                    percentageEl.style.color = level.color;
                }
                
                if (detailEl) detailEl.textContent = detailText;
                
                if (indicatorEl) {
                    const level = Dashboard.getCoverageLevelByPercentage(percentage);
                    indicatorEl.style.backgroundColor = level.color;
                }
            },

            updateQuickInsights(facilityCoverage, countyCoverage, overallCoverage, countiesWithTraining, facilitiesWithTraining, totalParticipants) {
                const quickInsight = document.getElementById('quickInsight');
                const trendInsight = document.getElementById('trendInsight');
                const geoInsight = document.getElementById('geoInsight');
                
                let mainInsight = '';
                if (facilityCoverage >= 80) mainInsight = 'Excellent facility coverage nationwide';
                else if (facilityCoverage >= 60) mainInsight = 'Good facility penetration achieved';
                else if (facilityCoverage >= 40) mainInsight = 'Moderate facility coverage in place';
                else if (facilityCoverage >= 20) mainInsight = 'Limited facility coverage - expansion needed';
                else mainInsight = 'Early stage coverage - significant growth potential';
                
                if (quickInsight) quickInsight.textContent = mainInsight;

                let trendText = '';
                if (countyCoverage > facilityCoverage + 20) trendText = 'Wide geographic spread with room for deeper penetration';
                else if (facilityCoverage > countyCoverage + 20) trendText = 'Concentrated in fewer counties with high facility density';
                else trendText = 'Balanced distribution across counties and facilities';
                
                if (trendInsight) trendInsight.textContent = trendText;

                const avgParticipantsPerCounty = countiesWithTraining > 0 ? Math.round(totalParticipants / countiesWithTraining) : 0;
                let geoText = '';
                
                if (countiesWithTraining >= 40) geoText = `Comprehensive national reach in ${countiesWithTraining} counties`;
                else if (countiesWithTraining >= 25) geoText = `Strong regional presence in ${countiesWithTraining} counties`;
                else if (countiesWithTraining >= 10) geoText = `Focused deployment in ${countiesWithTraining} key counties`;
                else geoText = `Pilot implementation in ${countiesWithTraining} counties`;
                
                if (avgParticipantsPerCounty > 0) {
                    geoText += ` (avg: ${avgParticipantsPerCounty} participants/county)`;
                }
                
                if (geoInsight) geoInsight.textContent = geoText;
            },

            getCountyStyle(properties) {
                const participants = properties.total_participants || 0;
                const level = Dashboard.getCoverageLevel(participants);

                return {
                    fillColor: level.color,
                    weight: 2,
                    opacity: 1,
                    color: 'white',
                    fillOpacity: 0.8
                };
            },

            setupCountyInteractions(feature, layer) {
                const props = feature.properties;
                const tooltipContent = this.createTooltip(props);

                layer.bindTooltip(tooltipContent, {
                    permanent: false,
                    direction: 'center',
                    className: 'county-tooltip'
                });

                layer.on({
                    mouseover: (e) => {
                        const layer = e.target;
                        layer.setStyle({
                            weight: 4,
                            color: '#3b82f6',
                            fillOpacity: 0.9
                        });
                        layer.openTooltip();
                    },
                    mouseout: (e) => {
                        Dashboard.state.countyLayer.resetStyle(e.target);
                        e.target.closeTooltip();
                    },
                    click: (e) => {
                        const countyId = props.county_id;
                        if (countyId) this.navigateToCounty(countyId);
                    }
                });
            },

            createTooltip(properties) {
                const countyName = properties.county_name || 'Unknown County';
                const programs = properties.total_programs || 0;
                const participants = properties.total_participants || 0;
                const facilities = properties.facilities_with_programs || 0;
                const totalFacilities = properties.total_facilities || 0;
                const coverage = properties.coverage_percentage || 0;
                
                const level = Dashboard.getCoverageLevel(participants);
                const programLabel = Dashboard.state.currentMode === 'training' ? 'Training Programs' : 'Mentorship Programs';
                const participantLabel = Dashboard.state.currentMode === 'training' ? 'Participants' : 'Mentees';

                return `
                    <div style="min-width: 200px;">
                        <h6 style="margin-bottom: 8px; font-weight: bold;">${countyName} County</h6>
                        <div style="margin-bottom: 8px; text-align: center;">
                            <span style="background: ${level.color}; color: ${participants > 50 ? 'white' : 'black'}; 
                                         padding: 4px 8px; border-radius: 12px; font-weight: bold; font-size: 12px;">
                                ${level.label} - ${participants} ${participantLabel}
                            </span>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; font-size: 12px;">
                            <div style="text-align: center; padding: 4px; background: #f8fafc; border-radius: 4px;">
                                <div style="font-weight: bold; color: #3b82f6;">${programs}</div>
                                <div>${programLabel}</div>
                            </div>
                            <div style="text-align: center; padding: 4px; background: #f8fafc; border-radius: 4px;">
                                <div style="font-weight: bold; color: #3b82f6;">${participants}</div>
                                <div>${participantLabel}</div>
                            </div>
                            <div style="text-align: center; padding: 4px; background: #f8fafc; border-radius: 4px;">
                                <div style="font-weight: bold; color: #3b82f6;">${facilities}/${totalFacilities}</div>
                                <div>Facilities</div>
                            </div>
                            <div style="text-align: center; padding: 4px; background: #f8fafc; border-radius: 4px;">
                                <div style="font-weight: bold; color: #3b82f6;">${coverage}%</div>
                                <div>Coverage</div>
                            </div>
                        </div>
                    </div>
                `;
            },

            navigateToCounty(countyId) {
                const params = new URLSearchParams({ mode: Dashboard.state.currentMode });
                if (Dashboard.state.currentYear) params.set('year', Dashboard.state.currentYear);
                window.location.href = `/analytics/dashboard/county/${countyId}?${params}`;
            },

            showError(message) {
                const mapContainer = document.getElementById('kenyaMap');
                if (mapContainer) {
                    mapContainer.innerHTML = `
                        <div class="alert alert-danger h-100 d-flex align-items-center justify-content-center">
                            <div class="text-center">
                                <i class="fas fa-exclamation-triangle fa-3x mb-3"></i>
                                <h6>Map Error</h6>
                                <p>${message}</p>
                                <button class="btn btn-sm btn-outline-danger" onclick="Dashboard.Map.init()">
                                    <i class="fas fa-redo"></i> Retry
                                </button>
                            </div>
                        </div>
                    `;
                }
            }
        },

        // Chart management
        Charts: {
            init() {
                const chartDataElement = document.getElementById('chart-data');
                let chartData = {};
                
                if (chartDataElement) {
                    try {
                        chartData = JSON.parse(chartDataElement.textContent);
                    } catch (e) {
                        console.error('Error parsing chart data:', e);
                        return;
                    }
                }
                
                this.destroyExisting();
                this.createDepartmentChart(chartData);
                this.createCadreChart(chartData);
                this.createFacilityChart(chartData);
                this.createTrendsChart(chartData);
            },

            destroyExisting() {
                Object.values(Dashboard.state.charts).forEach(chart => {
                    if (chart && typeof chart.destroy === 'function') {
                        chart.destroy();
                    }
                });
                Dashboard.state.charts = {};
            },

            createDepartmentChart(chartData) {
                const ctx = document.getElementById('departmentChart');
                if (!ctx || !chartData.departments) return;

                const data = chartData.departments
                    .map(d => ({
                        name: d.name,
                        count: d.count || 0,
                        coverage: d.coverage_percentage || 0
                    }))
                    .sort((a, b) => b.count - a.count);

                Dashboard.state.charts.department = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: data.map(d => d.name),
                        datasets: [{
                            label: 'Participants',
                            data: data.map(d => d.count),
                            backgroundColor: data.map((d, index) => Dashboard.getChartColor(index)),
                            borderWidth: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: (context) => {
                                        const item = data[context.dataIndex];
                                        const level = Dashboard.getCoverageLevelByPercentage(item.coverage);
                                        return `${item.name}: ${item.count} participants (${level.label} - ${item.coverage}%)`;
                                    }
                                }
                            }
                        },
                        scales: { 
                            y: { beginAtZero: true }
                        }
                    }
                });
            },

            createCadreChart(chartData) {
                const ctx = document.getElementById('cadreChart');
                if (!ctx || !chartData.cadres) return;

                const data = chartData.cadres
                    .map(c => ({
                        name: c.name,
                        count: c.count || 0,
                        coverage: c.coverage_percentage || 0
                    }))
                    .sort((a, b) => b.count - a.count);

                Dashboard.state.charts.cadre = new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: data.map(c => c.name),
                        datasets: [{
                            data: data.map(c => c.count),
                            backgroundColor: data.map((c, index) => Dashboard.getChartColor(index))
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { 
                                position: 'bottom',
                                labels: {
                                    boxWidth: 12,
                                    padding: 8,
                                    generateLabels: (chart) => {
                                        const original = Chart.defaults.plugins.legend.labels.generateLabels;
                                        const labels = original.call(this, chart);
                                        
                                        labels.forEach((label, index) => {
                                            if (data[index]) {
                                                const level = Dashboard.getCoverageLevelByPercentage(data[index].coverage);
                                                label.text += ` (${level.label})`;
                                            }
                                        });
                                        
                                        return labels;
                                    }
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: (context) => {
                                        const item = data[context.dataIndex];
                                        const level = Dashboard.getCoverageLevelByPercentage(item.coverage);
                                        return `${item.name}: ${item.count} participants (${level.label} - ${item.coverage}%)`;
                                    }
                                }
                            }
                        }
                    }
                });
            },

            createFacilityChart(chartData) {
                const ctx = document.getElementById('facilityTypeChart');
                if (!ctx || !chartData.facilityTypes) return;

                const data = chartData.facilityTypes
                    .map(f => ({
                        name: f.name,
                        coverage: f.coverage_percentage || 0,
                        total_facilities: f.total_facilities || 0,
                        active_facilities: f.active_facilities || 0,
                        total_participants: f.total_participants || 0,
                        county_breakdown: f.county_breakdown || []
                    }))
                    .sort((a, b) => b.coverage - a.coverage);

                // Store facility type data for modal use
                Dashboard.state.facilityTypeData = data;

                Dashboard.state.charts.facilityType = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: data.map(f => f.name),
                        datasets: [{
                            label: 'Coverage %',
                            data: data.map(f => f.coverage),
                            backgroundColor: data.map((f, index) => Dashboard.getChartColor(index)),
                            borderWidth: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: (context) => {
                                        const item = data[context.dataIndex];
                                        const level = Dashboard.getCoverageLevelByPercentage(item.coverage);
                                        return `${item.name}: ${level.label} Coverage (${item.coverage}%)`;
                                    }
                                }
                            }
                        },
                        scales: { 
                            y: { 
                                beginAtZero: true, 
                                max: 100,
                                ticks: {
                                    callback: (value) => value + '%'
                                }
                            }
                        },
                        onClick: (event, elements) => {
                            if (elements && elements.length > 0) {
                                const index = elements[0].index;
                                const facilityType = data[index];
                                Dashboard.FacilityInsights.show(facilityType);
                            }
                        }
                    }
                });
            },

            createTrendsChart(chartData) {
                const ctx = document.getElementById('trendsChart');
                if (!ctx || !chartData.monthly) return;

                const data = chartData.monthly.map(m => ({
                    month: m.month,
                    count: m.count || 0,
                    coverage: m.coverage_percentage || 0
                }));

                Dashboard.state.charts.trends = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: data.map(m => m.month),
                        datasets: [{
                            label: 'Monthly Activity',
                            data: data.map(m => m.count),
                            borderColor: '#3b82f6',
                            backgroundColor: 'rgba(59, 130, 246, 0.1)',
                            pointBackgroundColor: '#3b82f6',
                            pointBorderColor: '#ffffff',
                            pointBorderWidth: 2,
                            pointRadius: 6,
                            fill: true,
                            tension: 0.4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { 
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: (context) => {
                                        const item = data[context.dataIndex];
                                        const level = Dashboard.getCoverageLevelByPercentage(item.coverage);
                                        return `${item.month}: ${item.count} registrations (${level.label} - ${item.coverage}%)`;
                                    }
                                }
                            }
                        },
                        scales: { 
                            y: { beginAtZero: true }
                        }
                    }
                });
            },

            async update(trainingId = null) {
                try {
                    const params = new URLSearchParams();
                    params.set('mode', Dashboard.state.currentMode);
                    if (Dashboard.state.currentYear) params.set('year', Dashboard.state.currentYear);
                    if (trainingId) params.set('training_id', trainingId);

                    const response = await fetch(`/analytics/dashboard/training-data?${params}`);
                    const result = await response.json();
                    
                    if (result.success && result.chartData) {
                        this.updateData(result.chartData);
                        if (result.summaryStats) this.updateSummaryStats(result.summaryStats);
                    }
                } catch (error) {
                    console.error('Error updating charts:', error);
                }
            },

            updateData(data) {
                if (Dashboard.state.charts.department && data.departments) {
                    const sortedData = data.departments
                        .map(d => ({ name: d.name, count: d.count || 0, coverage: d.coverage_percentage || 0 }))
                        .sort((a, b) => b.count - a.count);
                    
                    const chart = Dashboard.state.charts.department;
                    chart.data.labels = sortedData.map(d => d.name);
                    chart.data.datasets[0].data = sortedData.map(d => d.count);
                    chart.data.datasets[0].backgroundColor = sortedData.map((d, index) => Dashboard.getChartColor(index));
                    chart.update('none');
                }

                if (Dashboard.state.charts.cadre && data.cadres) {
                    const sortedData = data.cadres
                        .map(c => ({ name: c.name, count: c.count || 0, coverage: c.coverage_percentage || 0 }))
                        .sort((a, b) => b.count - a.count);
                    
                    const chart = Dashboard.state.charts.cadre;
                    chart.data.labels = sortedData.map(c => c.name);
                    chart.data.datasets[0].data = sortedData.map(c => c.count);
                    chart.data.datasets[0].backgroundColor = sortedData.map((c, index) => Dashboard.getChartColor(index));
                    chart.update('none');
                }

                if (Dashboard.state.charts.facilityType && data.facilityTypes) {
                    const sortedData = data.facilityTypes
                        .map(f => ({ 
                            name: f.name, 
                            coverage: f.coverage_percentage || 0,
                            total_facilities: f.total_facilities || 0,
                            active_facilities: f.active_facilities || 0,
                            total_participants: f.total_participants || 0,
                            county_breakdown: f.county_breakdown || []
                        }))
                        .sort((a, b) => b.coverage - a.coverage);
                    
                    Dashboard.state.facilityTypeData = sortedData;
                    
                    const chart = Dashboard.state.charts.facilityType;
                    chart.data.labels = sortedData.map(f => f.name);
                    chart.data.datasets[0].data = sortedData.map(f => f.coverage);
                    chart.data.datasets[0].backgroundColor = sortedData.map((f, index) => Dashboard.getChartColor(index));
                    chart.update('none');
                }

                if (Dashboard.state.charts.trends && data.monthly) {
                    const monthlyData = data.monthly.map(m => ({
                        month: m.month,
                        count: m.count || 0,
                        coverage: m.coverage_percentage || 0
                    }));
                    
                    const chart = Dashboard.state.charts.trends;
                    chart.data.labels = monthlyData.map(m => m.month);
                    chart.data.datasets[0].data = monthlyData.map(m => m.count);
                    chart.update('none');
                }
            },

            updateSummaryStats(stats) {
                const elements = {
                    totalPrograms: document.getElementById('totalPrograms'),
                    totalParticipants: document.getElementById('totalParticipants'),
                    totalFacilities: document.getElementById('totalFacilities'),
                    facilityCoverage: document.getElementById('facilityCoverage')
                };
                
                if (elements.totalPrograms) elements.totalPrograms.textContent = (stats.totalPrograms || 0).toLocaleString();
                if (elements.totalParticipants) elements.totalParticipants.textContent = (stats.totalParticipants || 0).toLocaleString();
                if (elements.totalFacilities) elements.totalFacilities.textContent = (stats.totalFacilities || 0).toLocaleString();
                if (elements.facilityCoverage) elements.facilityCoverage.textContent = (stats.facilityCoverage || 0) + '%';
            }
        },

        // Facility Type Insights Modal
        FacilityInsights: {
            async show(facilityType) {
                const modal = new bootstrap.Modal(document.getElementById('facilityInsightsModal'));
                
                // Update modal title
                const titleElement = document.getElementById('facilityTypeTitle');
                if (titleElement) titleElement.textContent = facilityType.name;
                
                // Show loading
                const loadingEl = document.getElementById('facilityInsightsLoading');
                const contentEl = document.getElementById('facilityInsightsContent');
                if (loadingEl) loadingEl.style.display = 'block';
                if (contentEl) contentEl.style.display = 'none';
                
                modal.show();
                
                try {
                    await this.loadFacilityData(facilityType);
                } catch (error) {
                    console.error('Error loading facility insights:', error);
                    this.showError(error.message);
                }
            },

            async loadFacilityData(facilityType) {
                try {
                    const params = new URLSearchParams();
                    params.set('mode', Dashboard.state.currentMode);
                    params.set('facility_type', facilityType.name);
                    if (Dashboard.state.currentYear) params.set('year', Dashboard.state.currentYear);
                    if (Dashboard.state.selectedTraining) params.set('training_id', Dashboard.state.selectedTraining);

                    const response = await fetch(`/analytics/dashboard/facility-insights?${params}`);
                    if (!response.ok) throw new Error('Failed to load facility insights');
                    
                    const data = await response.json();
                    this.populateModal(data, facilityType);
                    
                    // Hide loading, show content
                    const loadingEl = document.getElementById('facilityInsightsLoading');
                    const contentEl = document.getElementById('facilityInsightsContent');
                    if (loadingEl) loadingEl.style.display = 'none';
                    if (contentEl) contentEl.style.display = 'block';
                    
                } catch (error) {
                    this.showError(error.message);
                }
            },

            populateModal(data, facilityType) {
                // Update summary stats
                const statsElements = {
                    totalFacilitiesOfType: document.getElementById('totalFacilitiesOfType'),
                    activeFacilitiesOfType: document.getElementById('activeFacilitiesOfType'),
                    participantsInType: document.getElementById('participantsInType'),
                    coverageOfType: document.getElementById('coverageOfType')
                };

                if (statsElements.totalFacilitiesOfType) {
                    statsElements.totalFacilitiesOfType.textContent = (data.totalFacilities || 0).toLocaleString();
                }
                if (statsElements.activeFacilitiesOfType) {
                    statsElements.activeFacilitiesOfType.textContent = (data.activeFacilities || 0).toLocaleString();
                }
                if (statsElements.participantsInType) {
                    statsElements.participantsInType.textContent = (data.totalParticipants || 0).toLocaleString();
                }
                if (statsElements.coverageOfType) {
                    statsElements.coverageOfType.textContent = `${data.coveragePercentage || 0}%`;
                }

                // Update participant label
                const participantLabel = document.getElementById('participantLabel');
                if (participantLabel) {
                    participantLabel.textContent = Dashboard.state.currentMode === 'training' ? 'Participants' : 'Mentees';
                }

                // Generate insights
                this.generateInsights(data, facilityType);

                // Populate county breakdown table
                this.populateCountyTable(data.countyBreakdown || []);
            },

            generateInsights(data, facilityType) {
                const insightsContainer = document.getElementById('facilityInsightsText');
                if (!insightsContainer) return;

                const coverage = data.coveragePercentage || 0;
                const totalFacilities = data.totalFacilities || 0;
                const activeFacilities = data.activeFacilities || 0;
                const totalParticipants = data.totalParticipants || 0;
                const countyCount = data.countyBreakdown ? data.countyBreakdown.length : 0;

                let insights = [];

                // Coverage assessment
                if (coverage >= 80) {
                    insights.push({
                        icon: 'fas fa-check-circle text-success',
                        text: `Excellent coverage achieved with ${coverage}% of ${facilityType.name.toLowerCase()} facilities participating in ${Dashboard.state.currentMode} programs.`
                    });
                } else if (coverage >= 60) {
                    insights.push({
                        icon: 'fas fa-thumbs-up text-success',
                        text: `Good coverage with ${coverage}% participation. Consider strategies to reach the remaining ${100 - coverage}% of facilities.`
                    });
                } else if (coverage >= 40) {
                    insights.push({
                        icon: 'fas fa-chart-line text-warning',
                        text: `Moderate coverage at ${coverage}%. Significant opportunity exists to expand to ${totalFacilities - activeFacilities} additional facilities.`
                    });
                } else if (coverage >= 20) {
                    insights.push({
                        icon: 'fas fa-exclamation-triangle text-orange',
                        text: `Limited coverage detected. Only ${activeFacilities} out of ${totalFacilities} ${facilityType.name.toLowerCase()} facilities are engaged.`
                    });
                } else {
                    insights.push({
                        icon: 'fas fa-info-circle text-info',
                        text: `Early implementation phase with ${coverage}% coverage. Strong growth potential across ${totalFacilities - activeFacilities} untapped facilities.`
                    });
                }

                // Geographic distribution
                if (countyCount > 0) {
                    const avgFacilitiesPerCounty = Math.round(totalFacilities / countyCount);
                    const avgParticipantsPerFacility = activeFacilities > 0 ? Math.round(totalParticipants / activeFacilities) : 0;

                    insights.push({
                        icon: 'fas fa-map-marker-alt text-info',
                        text: `Geographic spread across ${countyCount} counties with an average of ${avgFacilitiesPerCounty} facilities per county.`
                    });

                    if (avgParticipantsPerFacility > 0) {
                        insights.push({
                            icon: 'fas fa-users text-primary',
                            text: `Average participation rate of ${avgParticipantsPerFacility} ${Dashboard.state.currentMode === 'training' ? 'participants' : 'mentees'} per active facility indicates ${avgParticipantsPerFacility >= 20 ? 'strong' : avgParticipantsPerFacility >= 10 ? 'moderate' : 'light'} engagement levels.`
                        });
                    }
                }

                // Performance comparison
                if (Dashboard.state.facilityTypeData) {
                    const allTypes = Dashboard.state.facilityTypeData;
                    const avgCoverage = allTypes.reduce((sum, type) => sum + (type.coverage || 0), 0) / allTypes.length;
                    const rank = allTypes.findIndex(type => type.name === facilityType.name) + 1;

                    if (coverage > avgCoverage) {
                        insights.push({
                            icon: 'fas fa-trophy text-warning',
                            text: `Outperforming average with ${Math.round(coverage - avgCoverage)}% above the ${Math.round(avgCoverage)}% facility type average (ranked #${rank}).`
                        });
                    } else if (coverage < avgCoverage) {
                        insights.push({
                            icon: 'fas fa-arrow-up text-info',
                            text: `Opportunity for improvement: ${Math.round(avgCoverage - coverage)}% below the ${Math.round(avgCoverage)}% facility type average (ranked #${rank}).`
                        });
                    }
                }

                // Render insights
                insightsContainer.innerHTML = insights.map(insight => 
                    `<div class="insight-point">
                        <i class="${insight.icon} me-2"></i>
                        <span>${insight.text}</span>
                    </div>`
                ).join('');
            },

            populateCountyTable(countyData) {
                const tbody = document.getElementById('countyBreakdownBody');
                if (!tbody) return;

                if (countyData.length === 0) {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="6" class="text-center py-4">
                                <i class="fas fa-info-circle me-2"></i>
                                No county data available for this facility type
                            </td>
                        </tr>
                    `;
                    return;
                }

                const sortedData = countyData
                    .map(county => ({
                        ...county,
                        coverage: county.total_facilities > 0 ? 
                            Math.round((county.active_facilities / county.total_facilities) * 100) : 0
                    }))
                    .sort((a, b) => b.coverage - a.coverage);

                tbody.innerHTML = sortedData.map(county => {
                    const level = Dashboard.getCoverageLevelByPercentage(county.coverage);
                    
                    return `
                        <tr class="county-row" data-county-id="${county.county_id}" style="cursor: pointer;">
                            <td>
                                <strong>${county.county_name}</strong>
                            </td>
                            <td class="text-center">
                                ${(county.total_facilities || 0).toLocaleString()}
                            </td>
                            <td class="text-center">
                                <span class="badge bg-success">${(county.active_facilities || 0).toLocaleString()}</span>
                            </td>
                            <td class="text-center">
                                ${(county.total_participants || 0).toLocaleString()}
                            </td>
                            <td class="text-center">
                                <strong style="color: ${level.color};">${county.coverage}%</strong>
                            </td>
                            <td class="text-center">
                                <span class="badge" style="background-color: ${level.color}; color: ${county.coverage > 50 ? 'white' : 'black'};">
                                    ${level.label}
                                </span>
                            </td>
                        </tr>
                    `;
                }).join('');

                // Add click handlers for county rows
                tbody.querySelectorAll('.county-row').forEach(row => {
                    row.addEventListener('click', () => {
                        const countyId = row.dataset.countyId;
                        if (countyId) {
                            const params = new URLSearchParams({ mode: Dashboard.state.currentMode });
                            if (Dashboard.state.currentYear) params.set('year', Dashboard.state.currentYear);
                            window.location.href = `/analytics/dashboard/county/${countyId}?${params}`;
                        }
                    });
                });
            },

            showError(message) {
                const loadingEl = document.getElementById('facilityInsightsLoading');
                const contentEl = document.getElementById('facilityInsightsContent');
                
                if (loadingEl) {
                    loadingEl.innerHTML = `
                        <div class="text-center py-5">
                            <i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i>
                            <h6 class="text-danger">Error Loading Data</h6>
                            <p class="text-muted">${message}</p>
                            <button class="btn btn-outline-primary" onclick="this.closest('.modal').querySelector('.btn-close').click()">
                                Close
                            </button>
                        </div>
                    `;
                }
                
                if (contentEl) contentEl.style.display = 'none';
            }
        },

        // Training selection management
        Training: {
            select(trainingId, trainingTitle) {
                Dashboard.state.selectedTraining = trainingId;
                
                // Update visual selection
                document.querySelectorAll('.training-card').forEach(card => {
                    card.classList.toggle('selected', card.dataset.trainingId == trainingId);
                });
                
                // Update program filter to match selection
                const programFilter = document.getElementById('program-filter');
                if (programFilter) {
                    programFilter.value = trainingId;
                }
                
                this.updateTitles(trainingTitle, true);
                this.updateChartSubtitles(true);
                Dashboard.Map.loadCountyData(trainingId);
                Dashboard.Charts.update(trainingId);
                this.updateInsights(trainingTitle);
            },

            clear() {
                Dashboard.state.selectedTraining = null;
                
                // Clear visual selection
                document.querySelectorAll('.training-card').forEach(card => {
                    card.classList.remove('selected');
                });
                
                // Clear program filter
                const programFilter = document.getElementById('program-filter');
                if (programFilter) {
                    programFilter.value = '';
                }
                
                this.updateTitles(null, false);
                this.updateChartSubtitles(false);
                Dashboard.Map.loadCountyData();
                Dashboard.Charts.update();
                this.updateInsights();
            },

            updateTitles(trainingTitle, isFiltered) {
                const mapTitle = document.getElementById('mapTitle');
                const mapSubtitle = document.getElementById('mapSubtitle');
                
                if (isFiltered && trainingTitle) {
                    if (mapTitle) mapTitle.textContent = `${trainingTitle} - County Coverage`;
                    if (mapSubtitle) mapSubtitle.textContent = 'Showing counties participating in this specific program';
                } else {
                    if (mapTitle) mapTitle.textContent = `Kenya Counties ${Dashboard.state.currentMode === 'training' ? 'Training' : 'Mentorship'} Coverage Map`;
                    if (mapSubtitle) mapSubtitle.textContent = 'Overall coverage across all programs. Click on a county to explore detailed analytics.';
                }
            },

            updateChartSubtitles(filtered) {
                const subtitles = {
                    deptChartSubtitle: filtered ? 'Filtered by selected program' : 'Top departments by participation',
                    cadreChartSubtitle: filtered ? 'Filtered by selected program' : 'Professional roles distribution',
                    facilityChartSubtitle: filtered ? 'Filtered by selected program - Click for county breakdown' : 'Training penetration by facility category - Click for county breakdown',
                    trendsChartSubtitle: filtered ? 'Filtered by selected program' : 'Last 6 months registration activity'
                };
                
                Object.entries(subtitles).forEach(([id, text]) => {
                    const element = document.getElementById(id);
                    if (element) element.textContent = text;
                });
            },

            updateInsights(selectedTrainingTitle = null) {
                const container = document.getElementById('insightsContent');
                if (!container) return;
                
                if (selectedTrainingTitle) {
                    container.innerHTML = `
                        <div class="insight-item">
                            <i class="fas fa-filter text-primary me-2"></i>
                            <small><strong>Filtered:</strong> ${selectedTrainingTitle}</small>
                        </div>
                        <div class="insight-item">
                            <i class="fas fa-map-marked text-info me-2"></i>
                            <small>Map shows program-specific counties</small>
                        </div>
                        <div class="insight-item">
                            <i class="fas fa-chart-bar text-success me-2"></i>
                            <small>Charts filtered to program data</small>
                        </div>
                        <div class="insight-item">
                            <i class="fas fa-hospital text-warning me-2"></i>
                            <small>Click facility chart for detailed breakdowns</small>
                        </div>
                    `;
                } else {
                    // Generate dynamic insights based on current data
                    const stats = this.getCurrentStats();
                    container.innerHTML = `
                        <div class="insight-item">
                            <i class="fas fa-chart-line text-success me-2"></i>
                            <small>${stats.totalPrograms} ${Dashboard.state.currentMode} programs nationwide</small>
                        </div>
                        <div class="insight-item">
                            <i class="fas fa-users text-primary me-2"></i>
                            <small>${stats.totalParticipants} healthcare workers engaged</small>
                        </div>
                        <div class="insight-item">
                            <i class="fas fa-map-marked text-info me-2"></i>
                            <small>${stats.facilityCoverage}% facility coverage nationwide</small>
                        </div>
                        <div class="insight-item">
                            <i class="fas fa-hospital text-warning me-2"></i>
                            <small>Click facility chart for detailed breakdowns</small>
                        </div>
                    `;
                }
            },

            getCurrentStats() {
                return {
                    totalPrograms: document.getElementById('totalPrograms')?.textContent || '0',
                    totalParticipants: document.getElementById('totalParticipants')?.textContent || '0',
                    facilityCoverage: document.getElementById('facilityCoverage')?.textContent || '0%'
                };
            }
        },

        // Search and Filter functionality
        Search: {
            init() {
                // Store original training list for filtering
                const trainingsList = document.getElementById('trainingsList');
                if (trainingsList) {
                    Dashboard.state.originalTrainingsList = trainingsList.innerHTML;
                }

                this.setupSearchFilter();
                this.setupProgramCounter();
            },

            setupSearchFilter() {
                const searchFilter = document.getElementById('search-filter');
                if (!searchFilter) return;

                let searchTimeout;
                searchFilter.addEventListener('input', (e) => {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => {
                        this.filterTrainings(e.target.value.toLowerCase());
                    }, 300);
                });
            },

            setupProgramCounter() {
                // Update program counter when visibility changes
                const observer = new MutationObserver(() => {
                    this.updateProgramCounter();
                });

                const trainingsList = document.getElementById('trainingsList');
                if (trainingsList) {
                    observer.observe(trainingsList, {
                        childList: true,
                        subtree: true,
                        attributes: true,
                        attributeFilter: ['style']
                    });
                }
            },

            filterTrainings(searchTerm) {
                const trainingCards = document.querySelectorAll('.training-card');
                let visibleCount = 0;

                trainingCards.forEach(card => {
                    const title = card.querySelector('.training-title')?.textContent?.toLowerCase() || '';
                    const identifier = card.querySelector('.training-badge')?.textContent?.toLowerCase() || '';
                    
                    const isVisible = !searchTerm || 
                        title.includes(searchTerm) || 
                        identifier.includes(searchTerm);

                    card.style.display = isVisible ? 'block' : 'none';
                    
                    if (isVisible) visibleCount++;
                });

                this.updateProgramCounter(visibleCount);
                this.showNoResultsMessage(visibleCount === 0 && searchTerm);
            },

            updateProgramCounter(count = null) {
                const programCount = document.getElementById('programCount');
                if (!programCount) return;

                if (count === null) {
                    const visibleCards = document.querySelectorAll('.training-card:not([style*="display: none"])');
                    count = visibleCards.length;
                }

                programCount.textContent = count;
            },

            showNoResultsMessage(show) {
                const trainingsList = document.getElementById('trainingsList');
                if (!trainingsList) return;

                let noResultsMsg = trainingsList.querySelector('.no-search-results');
                
                if (show && !noResultsMsg) {
                    noResultsMsg = document.createElement('div');
                    noResultsMsg.className = 'no-search-results empty-state p-4';
                    noResultsMsg.innerHTML = `
                        <div class="text-center">
                            <i class="fas fa-search fa-3x text-muted mb-3"></i>
                            <h6 class="text-muted">No Programs Found</h6>
                            <p class="text-muted mb-0">Try adjusting your search terms</p>
                        </div>
                    `;
                    trainingsList.appendChild(noResultsMsg);
                } else if (!show && noResultsMsg) {
                    noResultsMsg.remove();
                }
            }
        },

        // Event handling
        Events: {
            init() {
                this.setupTrainingCards();
                this.setupModeButtons();
                this.setupFilters();
                this.setupInsights();
                this.setupModal();
                this.setupResponsive();
            },

            setupTrainingCards() {
                // Use event delegation for training cards
                const trainingsList = document.getElementById('trainingsList');
                if (trainingsList) {
                    trainingsList.addEventListener('click', (e) => {
                        const card = e.target.closest('.training-card');
                        if (!card) return;

                        e.preventDefault();
                        e.stopPropagation();
                        
                        const trainingId = card.dataset.trainingId;
                        const trainingTitle = card.querySelector('.training-title')?.textContent?.trim();
                        
                        if (card.classList.contains('selected')) {
                            Dashboard.Training.clear();
                        } else {
                            Dashboard.Training.select(trainingId, trainingTitle);
                        }
                    });
                }
            },

            setupModeButtons() {
                document.querySelectorAll('.mode-btn').forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        e.preventDefault();
                        const mode = btn.dataset.mode;
                        this.switchMode(mode);
                    });
                });
            },

            setupFilters() {
                // Year filter
                const yearFilter = document.getElementById('year-filter');
                if (yearFilter) {
                    yearFilter.addEventListener('change', () => {
                        const url = new URL(window.location);
                        if (yearFilter.value) {
                            url.searchParams.set('year', yearFilter.value);
                        } else {
                            url.searchParams.delete('year');
                        }
                        url.searchParams.set('mode', Dashboard.state.currentMode);
                        window.location.href = url.toString();
                    });
                }

                // Program filter
                const programFilter = document.getElementById('program-filter');
                if (programFilter) {
                    programFilter.addEventListener('change', () => {
                        if (programFilter.value) {
                            const card = document.querySelector(`[data-training-id="${programFilter.value}"]`);
                            if (card) {
                                const title = card.querySelector('.training-title')?.textContent?.trim();
                                Dashboard.Training.select(programFilter.value, title);
                                
                                // Scroll to selected card
                                card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                            }
                        } else {
                            Dashboard.Training.clear();
                        }
                    });
                }

                // Clear filters
                const clearFilters = document.getElementById('clear-filters');
                if (clearFilters) {
                    clearFilters.addEventListener('click', () => {
                        if (programFilter) programFilter.value = '';
                        const searchFilter = document.getElementById('search-filter');
                        if (searchFilter) {
                            searchFilter.value = '';
                            Dashboard.Search.filterTrainings('');
                        }
                        Dashboard.Training.clear();
                    });
                }
            },

            setupInsights() {
                const toggle = document.querySelector('.insights-toggle');
                if (toggle) {
                    toggle.addEventListener('click', (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        this.toggleInsights();
                    });
                }

                // Close insights when clicking outside
                document.addEventListener('click', (e) => {
                    const bubble = document.querySelector('.insights-bubble');
                    const panel = document.getElementById('insightsPanel');
                    
                    if (bubble && !bubble.contains(e.target) && panel?.classList.contains('show')) {
                        panel.classList.remove('show');
                    }
                });
            },

            setupModal() {
                // Export functionality
                const exportBtn = document.getElementById('exportFacilityData');
                if (exportBtn) {
                    exportBtn.addEventListener('click', () => {
                        this.exportFacilityData();
                    });
                }
            },

            setupResponsive() {
                let resizeTimer;
                window.addEventListener('resize', () => {
                    clearTimeout(resizeTimer);
                    resizeTimer = setTimeout(() => {
                        if (Dashboard.state.map) {
                            Dashboard.state.map.invalidateSize();
                        }
                        Object.values(Dashboard.state.charts).forEach(chart => {
                            if (chart && chart.resize) chart.resize();
                        });
                    }, 250);
                });
            },

            switchMode(mode) {
                if (mode === Dashboard.state.currentMode) return;
                
                const url = new URL(window.location);
                url.searchParams.set('mode', mode);
                if (Dashboard.state.currentYear) url.searchParams.set('year', Dashboard.state.currentYear);
                window.location.href = url.toString();
            },

            toggleInsights() {
                const panel = document.getElementById('insightsPanel');
                if (panel) panel.classList.toggle('show');
            },

            exportFacilityData() {
                const facilityTypeTitle = document.getElementById('facilityTypeTitle')?.textContent || 'Facility';
                const tableBody = document.getElementById('countyBreakdownBody');
                
                if (!tableBody) return;
                
                const rows = Array.from(tableBody.querySelectorAll('tr:not(:has(td[colspan]))'));
                if (rows.length === 0) return;
                
                // Generate CSV content
                const headers = ['County', 'Total Facilities', 'Active Facilities', 'Participants', 'Coverage %', 'Level'];
                const csvContent = [
                    headers.join(','),
                    ...rows.map(row => {
                        const cells = Array.from(row.querySelectorAll('td'));
                        return cells.map(cell => {
                            let content = cell.textContent.trim();
                            // Remove any badges/formatting, keep just the text
                            const badge = cell.querySelector('.badge');
                            if (badge) content = badge.textContent.trim();
                            return `"${content}"`;
                        }).join(',');
                    })
                ].join('\n');
                
                // Download CSV
                const blob = new Blob([csvContent], { type: 'text/csv' });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `${facilityTypeTitle}_Coverage_Analysis.csv`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.URL.revokeObjectURL(url);
            }
        },

        // Main initialization
        init() {
            if (typeof L === 'undefined' || typeof Chart === 'undefined') {
                console.error('Required libraries (Leaflet, Chart.js) not loaded');
                return;
            }
            
            this.Map.init();
            this.Charts.init();
            this.Search.init();
            this.Events.init();
            
            console.log('Healthcare Training Dashboard initialized successfully');
        }
    };

    // Global function exports
    window.toggleInsights = () => Dashboard.Events.toggleInsights();
    
    window.toggleDetailedInsights = function() {
        const detailedInsights = document.getElementById('detailedInsights');
        const toggleIcon = document.getElementById('toggleIcon');
        
        if (detailedInsights && toggleIcon) {
            const isHidden = detailedInsights.style.display === 'none';
            
            detailedInsights.style.display = isHidden ? 'block' : 'none';
            toggleIcon.className = isHidden ? 'fas fa-chevron-up' : 'fas fa-chevron-down';
            
            if (isHidden) {
                detailedInsights.style.opacity = '0';
                detailedInsights.style.transform = 'translateY(-10px)';
                
                setTimeout(() => {
                    detailedInsights.style.transition = 'all 0.3s ease';
                    detailedInsights.style.opacity = '1';
                    detailedInsights.style.transform = 'translateY(0)';
                }, 10);
            }
        }
    };
    
    // API for external access and debugging
    window.DashboardAPI = {
        selectTraining: (id, title) => Dashboard.Training.select(id, title),
        clearTraining: () => Dashboard.Training.clear(),
        reloadMap: () => Dashboard.Map.loadCountyData(Dashboard.state.selectedTraining),
        reloadCharts: () => Dashboard.Charts.update(Dashboard.state.selectedTraining),
        updateMapSummary: () => {
            if (Dashboard.state.countyLayer) {
                const geoJson = Dashboard.state.countyLayer.toGeoJSON();
                Dashboard.Map.updateMapSummary(geoJson);
            }
        },
        getState: () => Dashboard.state,
        getCoverageLevels: () => Dashboard.coverageLevels,
        reinitialize: () => Dashboard.init(),
        showFacilityInsights: (facilityType) => Dashboard.FacilityInsights.show(facilityType),
        exportData: () => Dashboard.Events.exportFacilityData(),
        searchTrainings: (term) => Dashboard.Search.filterTrainings(term),
        getInsightsData: () => {
            const facilityCoverage = document.getElementById('facilityCoveragePercent')?.textContent || '0%';
            const countyCoverage = document.getElementById('countyCoveragePercent')?.textContent || '0%';
            const overallCoverage = document.getElementById('overallCoverage')?.textContent || '0%';
            
            return {
                facilityCoverage,
                countyCoverage,
                overallCoverage,
                quickInsight: document.getElementById('quickInsight')?.textContent || '',
                trendInsight: document.getElementById('trendInsight')?.textContent || '',
                geoInsight: document.getElementById('geoInsight')?.textContent || ''
            };
        }
    };

    // Initialize when DOM is ready
   
        Dashboard.init();
  

    // Ensure Bootstrap is available for modal functionality
    if (typeof bootstrap === 'undefined') {
        console.warn('Bootstrap is required for modal functionality');
    }

})();