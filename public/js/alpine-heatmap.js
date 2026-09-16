// MOH Heatmap Alpine.js Component
// This should be included AFTER Alpine.js is loaded

// Define the global component function that will be used in the template
window.mohHeatmapComponent = function() {
    return {
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
            
            // Set global reference for backwards compatibility with existing functions
            window.currentWidgetId = this.widgetId;
        },

        // Load initial map data
        async loadInitialData() {
            try {
                this.loading = true;
                this.error = null;
                
                // Get map data from the backend (passed through the Blade template)
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
                    // Use sample GeoJSON for Kenya counties if not provided
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
            // Watch for modal state changes to handle body scroll
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
                
                // Call the global function to show facility breakdown
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
                
                // Call the global function to show participant details
                if (window.showParticipantDetails) {
                    window.showParticipantDetails(this.widgetId, facilityId, facilityName);
                }
            } catch (error) {
                console.error('Error opening participant details:', error);
                this.showToast('Error loading participant data', 'error');
            }
        },// Alpine.js Heatmap Component
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
            this.widgetId = this.$el.closest('[x-data]').getAttribute('data-widget-id') || 'default';
            this.loadInitialData();
            this.setupEventListeners();
        },

        // Load initial map data
        async loadInitialData() {
            try {
                this.loading = true;
                this.error = null;
                
                // Get map data from the backend (passed through the Blade template)
                const mapDataElement = document.getElementById(`map-data-${this.widgetId}`);
                if (mapDataElement) {
                    this.mapData = JSON.parse(mapDataElement.textContent);
                }
                
                // Initialize the map if data is available
                if (this.mapData && window.initKenyaMap) {
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
                // Check if we have GeoJSON data (you'll need to provide this)
                const geoJsonElement = document.getElementById(`geojson-data-${this.widgetId}`);
                let geoJsonData = null;
                
                if (geoJsonElement) {
                    geoJsonData = JSON.parse(geoJsonElement.textContent);
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
            }
        },

        // Setup event listeners
        setupEventListeners() {
            // Listen for modal events
            this.$watch('showCountyModal', (value) => {
                if (value) {
                    document.body.style.overflow = 'hidden';
                } else {
                    document.body.style.overflow = 'auto';
                }
            });

            this.$watch('showFacilityModal', (value) => {
                if (value) {
                    document.body.style.overflow = 'hidden';
                } else {
                    document.body.style.overflow = 'auto';
                }
            });

            this.$watch('showParticipantModal', (value) => {
                if (value) {
                    document.body.style.overflow = 'hidden';
                } else {
                    document.body.style.overflow = 'auto';
                }
            });

            this.$watch('showParticipantHistoryModal', (value) => {
                if (value) {
                    document.body.style.overflow = 'hidden';
                } else {
                    document.body.style.overflow = 'auto';
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
                
                // Call the global function to show facility breakdown
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
                
                // Call the global function to show participant details
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
                
                // Call the global function to show participant history
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

        async exportFacilityData(countyId) {
            try {
                this.loading = true;
                
                if (window.exportFacilityData) {
                    await window.exportFacilityData(this.widgetId, countyId);
                }
                
                this.showToast('Facility data exported successfully', 'success');
            } catch (error) {
                console.error('Error exporting facility data:', error);
                this.showToast('Error exporting facility data', 'error');
            } finally {
                this.loading = false;
            }
        },

        async exportParticipantData(facilityId) {
            try {
                this.loading = true;
                
                if (window.exportParticipantData) {
                    await window.exportParticipantData(this.widgetId, facilityId);
                }
                
                this.showToast('Participant data exported successfully', 'success');
            } catch (error) {
                console.error('Error exporting participant data:', error);
                this.showToast('Error exporting participant data', 'error');
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
                
                // Simulate refresh delay
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
                // Fallback toast implementation
                this.createSimpleToast(message, type);
            }
        },

        // Simple toast implementation as fallback
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
            
            // Animate in
            setTimeout(() => {
                toast.classList.remove('translate-x-full');
            }, 100);
            
            // Auto remove
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
            
            if (intensity <= 12.5) return '#fca5a5'; // Very low - light red
            if (intensity <= 25) return '#fbbf24';   // Low - yellow
            if (intensity <= 50) return '#a3a3a3';   // Medium - gray
            if (intensity <= 75) return '#84cc16';   // High - light green
            return '#16a34a'; // Very high - dark green
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

        // Data getters (computed properties)
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
    };
};

// Initialize when DOM is ready and Alpine is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Wait for Alpine.js to be ready
    if (window.Alpine) {
        console.log('MOH Heatmap component loaded');
    } else {
        // Wait for Alpine to load
        document.addEventListener('alpine:init', () => {
            console.log('MOH Heatmap component ready for Alpine.js');
        });
    }
});

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

        async exportFacilityData(countyId) {
            try {
                this.loading = true;
                
                if (window.exportFacilityData) {
                    await window.exportFacilityData(this.widgetId, countyId);
                }
                
                this.showToast('Facility data exported successfully', 'success');
            } catch (error) {
                console.error('Error exporting facility data:', error);
                this.showToast('Error exporting facility data', 'error');
            } finally {
                this.loading = false;
            }
        },

        async exportParticipantData(facilityId) {
            try {
                this.loading = true;
                
                if (window.exportParticipantData) {
                    await window.exportParticipantData(this.widgetId, facilityId);
                }
                
                this.showToast('Participant data exported successfully', 'success');
            } catch (error) {
                console.error('Error exporting participant data:', error);
                this.showToast('Error exporting participant data', 'error');
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
                
                // Simulate refresh delay
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
                console.log(`Toast [${type}]: ${message}`);
            }
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

        // Loading and error states
        get isLoading() {
            return this.loading;
        },

        get hasError() {
            return !!this.error;
        },

        get hasData() {
            return !!this.mapData && this.mapData.hasData;
        }
    }));
});