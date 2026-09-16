<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Healthcare Training Dashboard</title>
    
    <!-- External Dependencies -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Custom Styles -->
    <style>
       /* Healthcare Training Dashboard CSS */
:root {
    --primary-color: #3b82f6;
    --secondary-color: #8b5cf6;
    --success-color: #10b981;
    --warning-color: #f59e0b;
    --danger-color: #ef4444;
    --dark-color: #1f2937;
    --light-color: #f8fafc;
    --border-color: #e5e7eb;
    --shadow-light: 0 1px 3px rgba(0, 0, 0, 0.1);
    --shadow-medium: 0 4px 6px rgba(0, 0, 0, 0.1);
    --shadow-heavy: 0 10px 25px rgba(0, 0, 0, 0.15);
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
    overflow-x: hidden;
}

/* Layout Components */
.dashboard-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 1rem;
}

.header {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-radius: 1rem;
    padding: 1.5rem;
    margin-bottom: 2rem;
    box-shadow: var(--shadow-medium);
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.breadcrumb {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.875rem;
    color: #6b7280;
    margin-bottom: 1rem;
}

.breadcrumb-item {
    cursor: pointer;
    transition: color 0.2s ease;
}

.breadcrumb-item:hover {
    color: var(--primary-color);
}

.breadcrumb-separator {
    color: #9ca3af;
}

/* Metric Cards */
.metrics-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.metric-card {
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(10px);
    border-radius: 1rem;
    padding: 1.5rem;
    border: 1px solid rgba(255, 255, 255, 0.3);
    position: relative;
    overflow: hidden;
    transition: all 0.3s ease;
}

.metric-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-heavy);
}

.metric-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
}

.metric-icon {
    width: 3rem;
    height: 3rem;
    border-radius: 0.75rem;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 1rem;
    box-shadow: var(--shadow-light);
}

.metric-value {
    font-size: 2.5rem;
    font-weight: 700;
    color: var(--dark-color);
    margin-bottom: 0.5rem;
}

.metric-label {
    font-size: 0.875rem;
    color: #6b7280;
    font-weight: 500;
}

.progress-bar {
    height: 4px;
    background: #e5e7eb;
    border-radius: 2px;
    margin-top: 1rem;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--success-color), #34d399);
    border-radius: 2px;
    transition: width 0.8s ease;
}

/* Status Indicators */
.status-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    position: absolute;
    top: 1rem;
    right: 1rem;
}

.status-high {
    background: var(--success-color);
    box-shadow: 0 0 10px rgba(16, 185, 129, 0.5);
}

.status-medium {
    background: var(--warning-color);
    box-shadow: 0 0 10px rgba(245, 158, 11, 0.5);
}

.status-low {
    background: var(--danger-color);
    box-shadow: 0 0 10px rgba(239, 68, 68, 0.5);
}

/* Chart Containers */
.chart-container {
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(10px);
    border-radius: 1rem;
    padding: 1.5rem;
    border: 1px solid rgba(255, 255, 255, 0.3);
    margin-bottom: 2rem;
}

.chart-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}

.chart-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--dark-color);
}

.chart-actions {
    display: flex;
    gap: 0.5rem;
}

/* Buttons */
.btn {
    padding: 0.5rem 1rem;
    border-radius: 0.5rem;
    font-size: 0.875rem;
    font-weight: 500;
    border: none;
    cursor: pointer;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-primary {
    background: var(--primary-color);
    color: white;
}

.btn-primary:hover {
    background: #2563eb;
}

.btn-secondary {
    background: rgba(107, 114, 128, 0.1);
    color: #6b7280;
    border: 1px solid rgba(107, 114, 128, 0.2);
}

.btn-secondary:hover {
    background: rgba(107, 114, 128, 0.2);
}

.btn-export {
    background: var(--success-color);
    color: white;
}

.btn-export:hover {
    background: #059669;
}

/* Tables */
.data-table {
    width: 100%;
    border-collapse: collapse;
    background: rgba(255, 255, 255, 0.9);
    border-radius: 0.75rem;
    overflow: hidden;
    box-shadow: var(--shadow-light);
}

.data-table th {
    background: rgba(59, 130, 246, 0.1);
    padding: 1rem;
    text-align: left;
    font-weight: 600;
    color: var(--dark-color);
    border-bottom: 1px solid var(--border-color);
}

.data-table td {
    padding: 1rem;
    border-bottom: 1px solid #f3f4f6;
    vertical-align: middle;
}

.data-table tr:hover {
    background: rgba(59, 130, 246, 0.05);
}

.table-row-clickable {
    cursor: pointer;
    transition: background-color 0.2s ease;
}

.table-row-clickable:hover {
    background: rgba(59, 130, 246, 0.1);
}

/* Filters */
.filters {
    display: flex;
    gap: 1rem;
    margin-bottom: 2rem;
    flex-wrap: wrap;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.filter-label {
    font-size: 0.875rem;
    font-weight: 500;
    color: var(--dark-color);
}

.filter-select {
    padding: 0.5rem 1rem;
    border-radius: 0.5rem;
    border: 1px solid var(--border-color);
    background: rgba(255, 255, 255, 0.9);
    color: var(--dark-color);
    font-size: 0.875rem;
    min-width: 150px;
}

.filter-select:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

/* Modal */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(4px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
}

.modal-overlay.active {
    opacity: 1;
    visibility: visible;
}

.modal {
    background: white;
    border-radius: 1rem;
    padding: 2rem;
    max-width: 90vw;
    max-height: 90vh;
    overflow-y: auto;
    transform: scale(0.9);
    transition: transform 0.3s ease;
    box-shadow: var(--shadow-heavy);
}

.modal-overlay.active .modal {
    transform: scale(1);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border-color);
}

.modal-title {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--dark-color);
}

.modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: #6b7280;
    padding: 0.25rem;
    border-radius: 0.25rem;
}

.modal-close:hover {
    background: rgba(107, 114, 128, 0.1);
}

/* Loading States */
.loading {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem;
    color: #6b7280;
}

.spinner {
    width: 2rem;
    height: 2rem;
    border: 2px solid #e5e7eb;
    border-top: 2px solid var(--primary-color);
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-right: 0.5rem;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

/* Animations */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes slideInRight {
    from {
        opacity: 0;
        transform: translateX(20px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.animate-fade-in-up {
    animation: fadeInUp 0.6s ease forwards;
}

.animate-slide-in-right {
    animation: slideInRight 0.4s ease forwards;
}

/* Responsive Design */
@media (max-width: 768px) {
    .dashboard-container {
        padding: 0.5rem;
    }
    
    .metrics-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .header {
        padding: 1rem;
    }
    
    .metric-card {
        padding: 1rem;
    }
    
    .chart-container {
        padding: 1rem;
    }
    
    .filters {
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .modal {
        padding: 1rem;
        margin: 1rem;
    }
    
    .data-table {
        font-size: 0.875rem;
    }
    
    .data-table th,
    .data-table td {
        padding: 0.5rem;
    }
}

/* Utility Classes */
.text-center { text-align: center; }
.text-right { text-align: right; }
.font-bold { font-weight: 700; }
.font-semibold { font-weight: 600; }
.text-sm { font-size: 0.875rem; }
.text-xs { font-size: 0.75rem; }
.mb-2 { margin-bottom: 0.5rem; }
.mb-4 { margin-bottom: 1rem; }
.mb-6 { margin-bottom: 1.5rem; }
.mt-4 { margin-top: 1rem; }
.p-4 { padding: 1rem; }
.p-6 { padding: 1.5rem; }
.rounded { border-radius: 0.5rem; }
.shadow { box-shadow: var(--shadow-light); }
.border { border: 1px solid var(--border-color); }
.cursor-pointer { cursor: pointer; }
.hidden { display: none; }
.block { display: block; }
.flex { display: flex; }
.items-center { align-items: center; }
.justify-between { justify-content: space-between; }
.gap-2 { gap: 0.5rem; }
.gap-4 { gap: 1rem; }
.w-full { width: 100%; }
.h-full { height: 100%; }
    </style>
</head> 
<body>
    <div class="dashboard-container">
        <!-- Header Section -->
        <div class="header">
            <div id="breadcrumbs" class="breadcrumb">
                <span class="breadcrumb-item text-gray-900 font-semibold">National Overview</span> 
            </div>
            
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 mb-2">Healthcare Training Dashboard</h1>
                    <p class="text-gray-600">Comprehensive training analytics and performance insights</p>
                </div>
                
                <div class="filters">
                    <div class="filter-group">
                        <label class="filter-label">Training Type</label>
                        <select id="training-type-filter" class="filter-select">
                            <option value="all">All Types</option>
                            <option value="global_training">Global Training</option>
                            <option value="facility_mentorship">Facility Mentorship</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Year</label>
                        <select id="year-filter" class="filter-select">
                            <!-- Options populated by JavaScript -->
                        </select>
                    </div>
                    
                    <button id="export-btn" class="btn btn-export">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        Export Data
                    </button>
                </div>
            </div>
        </div>

        <!-- Loading Indicator -->
        <div id="loading" class="loading hidden">
            <div class="spinner"></div>
            Loading dashboard data...
        </div>

        <!-- National Level View -->
        <div id="national-view" class="dashboard-view">
            <!-- Key Metrics -->
            <div class="metrics-grid">
                <div class="metric-card animate-fade-in-up">
                    <div class="metric-icon" style="background: linear-gradient(135deg, #3b82f6, #1d4ed8);">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                    </div>
                    <div class="metric-value" id="national-counties">--</div>
                    <div class="metric-label">Total Counties</div>
                    <div class="progress-bar">
                        <div id="counties-progress" class="progress-fill" style="width: 0%"></div>
                    </div>
                    <div class="status-dot status-high"></div>
                </div>

                <div class="metric-card animate-fade-in-up" style="animation-delay: 0.1s;">
                    <div class="metric-icon" style="background: linear-gradient(135deg, #10b981, #047857);">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                        </svg>
                    </div>
                    <div class="metric-value" id="national-facilities">--</div>
                    <div class="metric-label">Health Facilities</div>
                    <div class="progress-bar">
                        <div id="facilities-progress" class="progress-fill" style="width: 0%"></div>
                    </div>
                    <div class="status-dot status-medium"></div>
                </div>

                <div class="metric-card animate-fade-in-up" style="animation-delay: 0.2s;">
                    <div class="metric-icon" style="background: linear-gradient(135deg, #8b5cf6, #6d28d9);">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/>
                        </svg>
                    </div>
                    <div class="metric-value" id="national-participants">--</div>
                    <div class="metric-label">Total Participants</div>
                    <div class="progress-bar">
                        <div id="participants-progress" class="progress-fill" style="width: 0%"></div>
                    </div>
                    <div class="status-dot status-high"></div>
                </div>

                <div class="metric-card animate-fade-in-up" style="animation-delay: 0.3s;">
                    <div class="metric-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                    </div>
                    <div class="metric-value" id="national-coverage">--%</div>
                    <div class="metric-label">Average Coverage</div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: 0%"></div>
                    </div>
                    <div class="status-dot status-medium"></div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <div class="chart-container">
                    <div class="chart-header">
                        <h3 class="chart-title">County Performance</h3>
                        <div class="chart-actions">
                            <button class="btn btn-secondary btn-sm">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                </svg>
                                Download
                            </button>
                        </div>
                    </div>
                    <div style="height: 300px;">
                        <canvas id="counties-chart-canvas"></canvas>
                    </div>
                </div>

                <div class="chart-container">
                    <div class="chart-header">
                        <h3 class="chart-title">Performance Trends</h3>
                        <div class="chart-actions">
                            <button class="btn btn-secondary btn-sm">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                </svg>
                                Download
                            </button>
                        </div>
                    </div>
                    <div style="height: 300px;">
                        <canvas id="performance-chart-canvas"></canvas>
                    </div>
                </div>
            </div>

            <!-- Counties Table -->
            <div class="chart-container">
                <div class="chart-header">
                    <h3 class="chart-title">Counties Overview</h3>
                    <div class="chart-actions">
                        <input type="text" placeholder="Search counties..." class="filter-select" style="min-width: 200px;">
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>County</th>
                                <th class="text-center">Total Facilities</th>
                                <th class="text-center">Covered Facilities</th>
                                <th class="text-center">Participants</th>
                                <th class="text-center">Coverage %</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="counties-table-body">
                            <!-- Populated by JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Insights Section -->
            <div class="chart-container">
                <div class="chart-header">
                    <h3 class="chart-title">Key Insights & Recommendations</h3>
                </div>
                <div id="insights-container" class="space-y-4">
                    <!-- Populated by JavaScript -->
                </div>
            </div>
        </div>

        <!-- County Level View -->
        <div id="county-view" class="dashboard-view hidden">
            <div class="chart-container">
                <div class="chart-header">
                    <h3 class="chart-title">
                        <span id="current-county-name">County</span> Overview
                    </h3>
                </div>
                
                <!-- County Metrics -->
                <div class="metrics-grid mb-8">
                    <div class="metric-card">
                        <div class="metric-icon" style="background: linear-gradient(135deg, #10b981, #047857);">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5"/>
                            </svg>
                        </div>
                        <div class="metric-value" id="county-facilities">--</div>
                        <div class="metric-label">Health Facilities</div>
                    </div>

                    <div class="metric-card">
                        <div class="metric-icon" style="background: linear-gradient(135deg, #8b5cf6, #6d28d9);">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/>
                            </svg>
                        </div>
                        <div class="metric-value" id="county-participants">--</div>
                        <div class="metric-label">Total Participants</div>
                    </div>

                    <div class="metric-card">
                        <div class="metric-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                        </div>
                        <div class="metric-value" id="county-coverage">--%</div>
                        <div class="metric-label">Coverage Rate</div>
                    </div>

                    <div class="metric-card">
                        <div class="metric-icon" style="background: linear-gradient(135deg, #3b82f6, #1d4ed8);">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                        </div>
                        <div class="metric-value" id="county-departments">--</div>
                        <div class="metric-label">Active Departments</div>
                    </div>
                </div>

                <!-- County Charts -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                    <div class="chart-container">
                        <div class="chart-header">
                            <h4 class="chart-title">Facility Types Distribution</h4>
                        </div>
                        <div style="height: 300px;">
                            <canvas id="facility-types-canvas"></canvas>
                        </div>
                    </div>

                    <div class="chart-container">
                        <div class="chart-header">
                            <h4 class="chart-title">Department Participation</h4>
                        </div>
                        <div style="height: 300px;">
                            <canvas id="departments-canvas"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Facility Types Table -->
                <div class="chart-container mb-6">
                    <div class="chart-header">
                        <h4 class="chart-title">Facility Types</h4>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Facility Type</th>
                                    <th class="text-center">Total Facilities</th>
                                    <th class="text-center">Covered</th>
                                    <th class="text-center">Participants</th>
                                    <th class="text-center">Coverage %</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="facility-types-table-body">
                                <!-- Populated by JavaScript -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Departments Table -->
                <div class="chart-container">
                    <div class="chart-header">
                        <h4 class="chart-title">Department Performance</h4>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Department</th>
                                    <th class="text-center">Total Staff</th>
                                    <th class="text-center">Participants</th>
                                    <th class="text-center">Coverage %</th>
                                    <th class="text-center">Last Training</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="departments-table-body">
                                <!-- Populated by JavaScript -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Facility Type Level View -->
        <div id="facility-type-view" class="dashboard-view hidden">
            <div class="chart-container">
                <div class="chart-header">
                    <h3 class="chart-title">
                        <span id="facility-type-name">Facility Type</span> in 
                        <span id="facility-type-county">County</span>
                    </h3>
                </div>
                
                <!-- Facility Type Metrics -->
                <div class="metrics-grid mb-8">
                    <div class="metric-card">
                        <div class="metric-icon" style="background: linear-gradient(135deg, #10b981, #047857);">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16"/>
                            </svg>
                        </div>
                        <div class="metric-value" id="type-total-facilities">--</div>
                        <div class="metric-label">Total Facilities</div>
                    </div>

                    <div class="metric-card">
                        <div class="metric-icon" style="background: linear-gradient(135deg, #3b82f6, #1d4ed8);">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div class="metric-value" id="type-covered-facilities">--</div>
                        <div class="metric-label">Covered Facilities</div>
                    </div>

                    <div class="metric-card">
                        <div class="metric-icon" style="background: linear-gradient(135deg, #8b5cf6, #6d28d9);">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1z"/>
                            </svg>
                        </div>
                        <div class="metric-value" id="type-total-participants">--</div>
                        <div class="metric-label">Total Participants</div>
                    </div>

                    <div class="metric-card">
                        <div class="metric-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6"/>
                            </svg>
                        </div>
                        <div class="metric-value" id="type-coverage-percentage">--%</div>
                        <div class="metric-label">Coverage Rate</div>
                    </div>
                </div>

                <!-- Facilities Table -->
                <div class="chart-container">
                    <div class="chart-header">
                        <h4 class="chart-title">Individual Facilities</h4>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Facility Name</th>
                                    <th class="text-center">Location</th>
                                    <th class="text-center">Total Staff</th>
                                    <th class="text-center">Participants</th>
                                    <th class="text-center">Coverage %</th>
                                    <th class="text-center">Last Training</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="facilities-table-body">
                                <!-- Populated by JavaScript -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Facility Level View -->
        <div id="facility-view" class="dashboard-view hidden">
            <div class="chart-container">
                <div class="chart-header">
                    <h3 class="chart-title">
                        <span id="facility-name">Facility</span> Training Details
                    </h3>
                </div>
                
                <!-- Facility Metrics -->
                <div class="metrics-grid mb-8">
                    <div class="metric-card">
                        <div class="metric-icon" style="background: linear-gradient(135deg, #8b5cf6, #6d28d9);">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1z"/>
                            </svg>
                        </div>
                        <div class="metric-value" id="facility-participants">--</div>
                        <div class="metric-label">Total Participants</div>
                    </div>

                    <div class="metric-card">
                        <div class="metric-icon" style="background: linear-gradient(135deg, #10b981, #047857);">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                            </svg>
                        </div>
                        <div class="metric-value" id="facility-trainings">--</div>
                        <div class="metric-label">Training Programs</div>
                    </div>

                    <div class="metric-card">
                        <div class="metric-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div class="metric-value" id="facility-completion">--%</div>
                        <div class="metric-label">Completion Rate</div>
                    </div>

                    <div class="metric-card">
                        <div class="metric-icon" style="background: linear-gradient(135deg, #3b82f6, #1d4ed8);">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857"/>
                            </svg>
                        </div>
                        <div class="metric-value" id="facility-departments">--</div>
                        <div class="metric-label">Active Departments</div>
                    </div>
                </div>

                <!-- Facility Charts -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                    <div class="chart-container">
                        <div class="chart-header">
                            <h4 class="chart-title">Participation Trends</h4>
                        </div>
                        <div style="height: 300px;">
                            <canvas id="participation-chart-canvas"></canvas>
                        </div>
                    </div>

                    <div class="chart-container">
                        <div class="chart-header">
                            <h4 class="chart-title">Training Programs</h4>
                        </div>
                        <div style="height: 300px;">
                            <canvas id="trainings-chart-canvas"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Participants Table -->
                <div class="chart-container">
                    <div class="chart-header">
                        <h4 class="chart-title">Training Participants</h4>
                        <div class="chart-actions">
                            <input type="text" placeholder="Search participants..." class="filter-select" style="min-width: 200px;">
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Participant Name</th>
                                    <th>Cadre</th>
                                    <th>Department</th>
                                    <th class="text-center">Trainings</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-center">Last Training</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="participants-table-body">
                                <!-- Populated by JavaScript -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal -->
    <div id="modal" class="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <h2 id="modal-title" class="modal-title">Modal Title</h2>
                <button class="modal-close" onclick="dashboard.closeModal()">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div id="modal-content" class="modal-content">
                <!-- Dynamic content -->
            </div>
        </div>
    </div>

    <!-- Custom JavaScript -->
    <script>
        /**
 * Healthcare Training Dashboard
 * Complete drill-down functionality with enhanced UI
 */
/**
 * Healthcare Training Dashboard
 * Complete drill-down functionality with enhanced UI
 */
class HealthcareTrainingDashboard {
    constructor() {
        this.currentLevel = 'national';
        this.currentData = {};
        this.charts = {};
        this.filters = {
            trainingType: 'all',
            year: new Date().getFullYear()
        };
        this.breadcrumbs = [];
        this.csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        
        this.init();
    }

    async init() {
        this.setupEventListeners();
        this.setupFilters();
        await this.loadNationalData();
    }

    setupEventListeners() {
        // Filter changes
        document.getElementById('training-type-filter')?.addEventListener('change', (e) => {
            this.filters.trainingType = e.target.value;
            this.refreshCurrentView();
        });

        document.getElementById('year-filter')?.addEventListener('change', (e) => {
            this.filters.year = e.target.value;
            this.refreshCurrentView();
        });

        // Export functionality
        document.getElementById('export-btn')?.addEventListener('click', () => {
            this.exportData();
        });

        // Modal close
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal-overlay')) {
                this.closeModal();
            }
        });

        // Breadcrumb navigation
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('breadcrumb-item')) {
                const level = e.target.dataset.level;
                this.navigateToLevel(level);
            }
        });
    }

    setupFilters() {
        const currentYear = new Date().getFullYear();
        const yearFilter = document.getElementById('year-filter');
        
        if (yearFilter) {
            yearFilter.innerHTML = '';
            for (let year = currentYear; year >= currentYear - 5; year--) {
                const option = document.createElement('option');
                option.value = year;
                option.textContent = year;
                if (year === currentYear) option.selected = true;
                yearFilter.appendChild(option);
            }
        }
    }

    async loadNationalData() {
        this.showLoading();
        this.updateBreadcrumbs([{label: 'National Overview', level: 'national'}]);
        
        try {
            const response = await this.apiCall('/training-dashboard/api/overview', {
                training_type: this.filters.trainingType,
                year: this.filters.year
            });
            
            this.currentData = response;
            this.renderNationalView(response);
        } catch (error) {
            this.showError('Failed to load national data');
        }
    }

    async loadCountyData(countyId, countyName) {
        this.showLoading();
        this.updateBreadcrumbs([
            {label: 'National Overview', level: 'national'},
            {label: countyName, level: 'county', id: countyId}
        ]);
        
        try {
            const response = await this.apiCall(`/training-dashboard/api/county/${countyId}`, {
                training_type: this.filters.trainingType,
                year: this.filters.year
            });
            
            this.currentData = response;
            this.currentLevel = 'county';
            this.renderCountyView(response, countyName);
        } catch (error) {
            this.showError('Failed to load county data');
        }
    }

    async loadFacilityTypeData(countyId, facilityTypeId, facilityTypeName, countyName) {
        this.showLoading();
        this.updateBreadcrumbs([
            {label: 'National Overview', level: 'national'},
            {label: countyName, level: 'county', id: countyId},
            {label: facilityTypeName, level: 'facility-type', countyId, facilityTypeId}
        ]);
        
        try {
            const response = await this.apiCall(`/training-dashboard/api/facility-type/${countyId}/${facilityTypeId}`, {
                training_type: this.filters.trainingType,
                year: this.filters.year
            });
            
            this.currentData = response;
            this.currentLevel = 'facility-type';
            this.renderFacilityTypeView(response, facilityTypeName, countyName);
        } catch (error) {
            this.showError('Failed to load facility type data');
        }
    }

    async loadFacilityData(facilityId, facilityName) {
        this.showLoading();
        
        try {
            const response = await this.apiCall(`/training-dashboard/api/facility/${facilityId}`, {
                training_type: this.filters.trainingType,
                year: this.filters.year
            });
            
            this.currentData = response;
            this.currentLevel = 'facility';
            this.renderFacilityView(response, facilityName);
        } catch (error) {
            this.showError('Failed to load facility data');
        }
    }

    renderNationalView(data) {
        this.currentLevel = 'national';
        
        // Update metrics
        this.updateElement('national-counties', data.total_counties || 0);
        this.updateElement('national-facilities', data.total_facilities || 0);
        this.updateElement('national-participants', data.total_participants || 0);
        this.updateElement('national-coverage', `${(data.coverage_percentage || 0).toFixed(1)}%`);
        
        // Update progress bars
        this.updateProgressBar('counties-progress', data.counties_coverage || 0);
        this.updateProgressBar('facilities-progress', data.facilities_coverage || 0);
        this.updateProgressBar('participants-progress', data.participation_rate || 0);
        
        // Render charts
        this.renderCountiesChart(data.counties || []);
        this.renderPerformanceChart(data.performance_trends || []);
        
        // Render counties table
        this.renderCountiesTable(data.counties || []);
        
        // Update insights
        this.renderInsights(data.insights || []);
        
        this.hideLoading();
    }

    renderCountyView(data, countyName) {
        document.getElementById('current-county-name').textContent = countyName;
        
        // Update metrics
        this.updateElement('county-facilities', data.total_facilities || 0);
        this.updateElement('county-participants', data.total_participants || 0);
        this.updateElement('county-coverage', `${(data.coverage_percentage || 0).toFixed(1)}%`);
        this.updateElement('county-departments', data.total_departments || 0);
        
        // Render charts
        this.renderFacilityTypesChart(data.facility_types || []);
        this.renderDepartmentsChart(data.departments || []);
        
        // Render tables
        this.renderFacilityTypesTable(data.facility_types || []);
        this.renderDepartmentsTable(data.departments || []);
        
        this.hideLoading();
    }

    renderFacilityTypeView(data, facilityType, countyName) {
        // Update header
        document.getElementById('facility-type-name').textContent = facilityType;
        document.getElementById('facility-type-county').textContent = countyName;
        
        // Update metrics
        this.updateElement('type-total-facilities', data.total_facilities || 0);
        this.updateElement('type-covered-facilities', data.covered_facilities || 0);
        this.updateElement('type-total-participants', data.total_participants || 0);
        this.updateElement('type-coverage-percentage', `${(data.coverage_percentage || 0).toFixed(1)}%`);
        
        // Render facilities table
        this.renderFacilitiesTable(data.facilities || []);
        
        this.hideLoading();
    }

    renderFacilityView(data, facilityName) {
        // Update header
        document.getElementById('facility-name').textContent = facilityName;
        
        // Update metrics
        this.updateElement('facility-participants', data.total_participants || 0);
        this.updateElement('facility-trainings', data.total_trainings || 0);
        this.updateElement('facility-completion', `${(data.completion_rate || 0).toFixed(1)}%`);
        this.updateElement('facility-departments', data.total_departments || 0);
        
        // Render charts
        this.renderParticipationChart(data.participation_trends || []);
        this.renderTrainingsChart(data.trainings || []);
        
        // Render participants table
        this.renderParticipantsTable(data.participants || []);
        
        this.hideLoading();
    }

    renderCountiesChart(counties) {
        const ctx = document.getElementById('counties-chart-canvas');
        if (!ctx) return;

        this.destroyChart('counties');
        
        const data = counties.slice(0, 10).map(county => ({
            label: county.name,
            coverage: county.coverage_percentage || 0,
            participants: county.total_participants || 0
        }));

        this.charts.counties = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: data.map(item => item.label),
                datasets: [{
                    label: 'Coverage %',
                    data: data.map(item => item.coverage),
                    backgroundColor: 'rgba(59, 130, 246, 0.8)',
                    borderColor: 'rgba(59, 130, 246, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100
                    }
                },
                onClick: (event, elements) => {
                    if (elements.length > 0) {
                        const index = elements[0].index;
                        const county = counties[index];
                        this.loadCountyData(county.id, county.name);
                    }
                }
            }
        });
    }

    renderFacilityTypesChart(facilityTypes) {
        const ctx = document.getElementById('facility-types-canvas');
        if (!ctx) return;

        this.destroyChart('facilityTypes');

        this.charts.facilityTypes = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: facilityTypes.map(type => type.name),
                datasets: [{
                    data: facilityTypes.map(type => type.total_participants || 0),
                    backgroundColor: [
                        '#3b82f6', '#8b5cf6', '#10b981', '#f59e0b', '#ef4444'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                onClick: (event, elements) => {
                    if (elements.length > 0) {
                        const index = elements[0].index;
                        const facilityType = facilityTypes[index];
                        this.loadFacilityTypeData(
                            this.getCurrentCountyId(),
                            facilityType.id,
                            facilityType.name,
                            this.getCurrentCountyName()
                        );
                    }
                }
            }
        });
    }

    renderCountiesTable(counties) {
        const tableBody = document.getElementById('counties-table-body');
        if (!tableBody) return;

        tableBody.innerHTML = '';
        
        counties.forEach(county => {
            const row = document.createElement('tr');
            row.className = 'table-row-clickable';
            row.addEventListener('click', () => {
                this.loadCountyData(county.id, county.name);
            });
            
            row.innerHTML = `
                <td class="font-semibold">${county.name}</td>
                <td class="text-center">${county.total_facilities || 0}</td>
                <td class="text-center">${county.covered_facilities || 0}</td>
                <td class="text-center">${county.total_participants || 0}</td>
                <td class="text-center">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                        ${this.getCoverageClass(county.coverage_percentage)}">
                        ${(county.coverage_percentage || 0).toFixed(1)}%
                    </span>
                </td>
                <td class="text-center">
                    <button class="btn btn-primary btn-sm" 
                            onclick="dashboard.loadCountyData(${county.id}, '${county.name}')">
                        View Details
                    </button>
                </td>
            `;
            
            tableBody.appendChild(row);
        });
    }

    renderDepartmentsTable(departments) {
        const tableBody = document.getElementById('departments-table-body');
        if (!tableBody) return;

        tableBody.innerHTML = '';
        
        departments.forEach(department => {
            const row = document.createElement('tr');
            row.className = 'table-row-clickable';
            row.addEventListener('click', () => {
                this.loadDepartmentData(department.id, department.name);
            });
            
            row.innerHTML = `
                <td class="font-semibold">${department.name}</td>
                <td class="text-center">${department.total_staff || 0}</td>
                <td class="text-center">${department.total_participants || 0}</td>
                <td class="text-center">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                        ${this.getCoverageClass(department.coverage_percentage)}">
                        ${(department.coverage_percentage || 0).toFixed(1)}%
                    </span>
                </td>
                <td class="text-center">${this.formatDate(department.last_training_date)}</td>
                <td class="text-center">
                    <button class="btn btn-primary btn-sm" 
                            onclick="dashboard.loadDepartmentData('${department.id}', '${department.name}')">
                        View Staff
                    </button>
                </td>
            `;
            
            tableBody.appendChild(row);
        });
    }

    async loadDepartmentData(departmentId, departmentName) {
        try {
            const countyCrumb = this.breadcrumbs.find(b => b.level === 'county');
            if (!countyCrumb) return;

            const response = await this.apiCall(`/training-dashboard/api/department/${countyCrumb.id}/${departmentName}/staff`, {
                training_type: this.filters.trainingType,
                year: this.filters.year
            });
            
            this.openModal(`${departmentName} Staff`, this.renderDepartmentStaffContent(response, departmentName));
        } catch (error) {
            this.showError('Failed to load department data');
        }
    }

    renderDepartmentStaffContent(data, departmentName) {
        return `
            <div class="department-staff">
                <div class="mb-6">
                    <h4 class="font-semibold mb-2">${departmentName} - Staff Overview</h4>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                        <div class="text-center">
                            <div class="text-2xl font-bold text-blue-600">${data.total_staff || 0}</div>
                            <div class="text-gray-600">Total Staff</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-green-600">${data.trained_staff || 0}</div>
                            <div class="text-gray-600">Trained</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-orange-600">${data.pending_staff || 0}</div>
                            <div class="text-gray-600">Pending</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-purple-600">${(data.coverage_percentage || 0).toFixed(1)}%</div>
                            <div class="text-gray-600">Coverage</div>
                        </div>
                    </div>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Staff Member</th>
                                <th>Cadre</th>
                                <th class="text-center">Training Status</th>
                                <th class="text-center">Last Training</th>
                                <th class="text-center">Trainings Count</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${(data.staff || []).map(staff => `
                                <tr>
                                    <td class="font-medium">${staff.name}</td>
                                    <td>${staff.cadre || 'N/A'}</td>
                                    <td class="text-center">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                            ${this.getStatusClass(staff.training_status)}">
                                            ${staff.training_status || 'No Training'}
                                        </span>
                                    </td>
                                    <td class="text-center">${this.formatDate(staff.last_training_date)}</td>
                                    <td class="text-center">${staff.trainings_count || 0}</td>
                                    <td class="text-center">
                                        <button class="btn btn-secondary btn-sm" 
                                                onclick="dashboard.showParticipantProfile({id: ${staff.id}})">
                                            View Profile
                                        </button>
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    }

    renderParticipantsTable(participants) {
        const tableBody = document.getElementById('participants-table-body');
        if (!tableBody) return;

        tableBody.innerHTML = '';
        
        participants.forEach(participant => {
            const row = document.createElement('tr');
            row.className = 'table-row-clickable';
            row.addEventListener('click', () => {
                this.showParticipantProfile(participant);
            });
            
            row.innerHTML = `
                <td class="font-semibold">${participant.name}</td>
                <td>${participant.cadre || 'N/A'}</td>
                <td>${participant.department || 'N/A'}</td>
                <td class="text-center">${participant.trainings_attended || 0}</td>
                <td class="text-center">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                        ${this.getStatusClass(participant.last_training_status)}">
                        ${participant.last_training_status || 'No Training'}
                    </span>
                </td>
                <td class="text-center">${this.formatDate(participant.last_training_date)}</td>
                <td class="text-center">
                    <button class="btn btn-secondary btn-sm" 
                            onclick="dashboard.showParticipantProfile(${JSON.stringify(participant).replace(/"/g, '&quot;')})">
                        View Profile
                    </button>
                </td>
            `;
            
            tableBody.appendChild(row);
        });
    }

    async showParticipantProfile(participant) {
        try {
            const response = await this.apiCall(`/training-dashboard/api/participant/${participant.id}`);
            this.openModal('Participant Profile', this.renderParticipantProfileContent(response));
        } catch (error) {
            this.showError('Failed to load participant profile');
        }
    }

    renderParticipantProfileContent(data) {
        return `
            <div class="participant-profile">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <h4 class="font-semibold mb-2">Personal Information</h4>
                        <div class="space-y-2 text-sm">
                            <p><strong>Name:</strong> ${data.name}</p>
                            <p><strong>Email:</strong> ${data.email || 'N/A'}</p>
                            <p><strong>Phone:</strong> ${data.phone || 'N/A'}</p>
                            <p><strong>Cadre:</strong> ${data.cadre || 'N/A'}</p>
                            <p><strong>Department:</strong> ${data.department || 'N/A'}</p>
                            <p><strong>Facility:</strong> ${data.facility || 'N/A'}</p>
                        </div>
                    </div>
                    <div>
                        <h4 class="font-semibold mb-2">Training Statistics</h4>
                        <div class="space-y-2 text-sm">
                            <p><strong>Total Trainings:</strong> ${data.total_trainings || 0}</p>
                            <p><strong>Completed:</strong> ${data.completed_trainings || 0}</p>
                            <p><strong>In Progress:</strong> ${data.in_progress_trainings || 0}</p>
                            <p><strong>Completion Rate:</strong> ${(data.completion_rate || 0).toFixed(1)}%</p>
                            <p><strong>Last Training:</strong> ${this.formatDate(data.last_training_date)}</p>
                        </div>
                    </div>
                </div>
                
                <div class="mb-6">
                    <h4 class="font-semibold mb-3">Training History</h4>
                    <div class="overflow-x-auto">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Training</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Score</th>
                                    <th>Duration</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${(data.training_history || []).map(training => `
                                    <tr>
                                        <td class="font-medium">${training.name}</td>
                                        <td>${this.formatDate(training.date)}</td>
                                        <td>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                ${this.getStatusClass(training.status)}">
                                                ${training.status}
                                            </span>
                                        </td>
                                        <td class="text-center">${training.score || 'N/A'}</td>
                                        <td class="text-center">${training.duration || 'N/A'}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div class="flex justify-end gap-2">
                    <button class="btn btn-secondary" onclick="dashboard.closeModal()">Close</button>
                    <button class="btn btn-primary" onclick="dashboard.exportParticipantData(${data.id})">Export History</button>
                </div>
            </div>
        `;
    }

    async exportParticipantData(participantId) {
        try {
            const response = await fetch(`/training-dashboard/export/participant/${participantId}`, {
                method: 'GET',
                headers: {
                    'X-CSRF-TOKEN': this.csrfToken,
                    'Accept': 'application/json'
                }
            });
            
            if (response.ok) {
                const blob = await response.blob();
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `participant_${participantId}_history.xlsx`;
                a.click();
                window.URL.revokeObjectURL(url);
            }
        } catch (error) {
            this.showError('Failed to export participant data');
        }
    }

    async apiCall(endpoint, params = {}) {
        const url = new URL(endpoint, window.location.origin);
        Object.keys(params).forEach(key => {
            if (params[key] !== null && params[key] !== undefined) {
                url.searchParams.append(key, params[key]);
            }
        });

        const response = await fetch(url, {
            method: 'GET',
            headers: {
                'X-CSRF-TOKEN': this.csrfToken,
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            }
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        return await response.json();
    }

    openModal(title, content) {
        const modal = document.getElementById('modal');
        const modalTitle = document.getElementById('modal-title');
        const modalContent = document.getElementById('modal-content');
        
        if (modal && modalTitle && modalContent) {
            modalTitle.textContent = title;
            modalContent.innerHTML = content;
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    }

    closeModal() {
        const modal = document.getElementById('modal');
        if (modal) {
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }
    }

    updateBreadcrumbs(breadcrumbs) {
        this.breadcrumbs = breadcrumbs;
        const container = document.getElementById('breadcrumbs');
        if (!container) return;

        container.innerHTML = breadcrumbs.map((crumb, index) => {
            const isLast = index === breadcrumbs.length - 1;
            return `
                <span class="breadcrumb-item ${isLast ? 'text-gray-900 font-semibold' : 'cursor-pointer'}" 
                      data-level="${crumb.level}" 
                      data-id="${crumb.id || ''}"
                      ${!isLast ? `onclick="dashboard.navigateToBreadcrumb('${crumb.level}', '${crumb.id || ''}')"` : ''}>
                    ${crumb.label}
                </span>
                ${!isLast ? '<span class="breadcrumb-separator">></span>' : ''}
            `;
        }).join('');
    }

    navigateToBreadcrumb(level, id) {
        switch (level) {
            case 'national':
                this.loadNationalData();
                break;
            case 'county':
                if (id) {
                    const countyName = this.breadcrumbs.find(b => b.level === 'county')?.label;
                    this.loadCountyData(id, countyName);
                }
                break;
            case 'facility-type':
                // Navigate back to county level
                const countyCrumb = this.breadcrumbs.find(b => b.level === 'county');
                if (countyCrumb) {
                    this.loadCountyData(countyCrumb.id, countyCrumb.label);
                }
                break;
        }
    }

    async refreshCurrentView() {
        switch (this.currentLevel) {
            case 'national':
                await this.loadNationalData();
                break;
            case 'county':
                const countyCrumb = this.breadcrumbs.find(b => b.level === 'county');
                if (countyCrumb) {
                    await this.loadCountyData(countyCrumb.id, countyCrumb.label);
                }
                break;
            case 'facility-type':
                const facilityTypeCrumb = this.breadcrumbs.find(b => b.level === 'facility-type');
                const countyTypeCrumb = this.breadcrumbs.find(b => b.level === 'county');
                if (facilityTypeCrumb && countyTypeCrumb) {
                    await this.loadFacilityTypeData(
                        facilityTypeCrumb.countyId,
                        facilityTypeCrumb.facilityTypeId,
                        facilityTypeCrumb.facilityTypeName,
                        countyTypeCrumb.label
                    );
                }
                break;
            case 'facility':
                // Reload current facility data
                if (this.currentData.facility_id) {
                    await this.loadFacilityData(this.currentData.facility_id, this.currentData.facility_name);
                }
                break;
        }
    }

    async exportData() {
        try {
            let endpoint = '/training-dashboard/export';
            const params = {
                level: this.currentLevel,
                training_type: this.filters.trainingType,
                year: this.filters.year
            };

            // Add specific IDs based on current level
            const countyCrumb = this.breadcrumbs.find(b => b.level === 'county');
            if (countyCrumb) {
                params.county_id = countyCrumb.id;
                endpoint = `/training-dashboard/export/county/${countyCrumb.id}`;
            }

            const facilityTypeCrumb = this.breadcrumbs.find(b => b.level === 'facility-type');
            if (facilityTypeCrumb) {
                params.facility_type_id = facilityTypeCrumb.facilityTypeId;
            }

            if (this.currentData.facility_id) {
                endpoint = `/training-dashboard/export/facility/${this.currentData.facility_id}`;
                params.facility_id = this.currentData.facility_id;
            }

            const url = new URL(endpoint, window.location.origin);
            Object.keys(params).forEach(key => {
                if (params[key] !== null && params[key] !== undefined) {
                    url.searchParams.append(key, params[key]);
                }
            });

            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'X-CSRF-TOKEN': this.csrfToken
                }
            });

            if (response.ok) {
                const blob = await response.blob();
                const downloadUrl = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = downloadUrl;
                a.download = `training_dashboard_${this.currentLevel}_${Date.now()}.xlsx`;
                a.click();
                window.URL.revokeObjectURL(downloadUrl);
            } else {
                throw new Error('Export failed');
            }
        } catch (error) {
            this.showError('Failed to export data');
        }
    }

    showLoading() {
        const loader = document.getElementById('loading');
        if (loader) {
            loader.classList.remove('hidden');
        }
    }

    hideLoading() {
        const loader = document.getElementById('loading');
        if (loader) {
            loader.classList.add('hidden');
        }
    }

    showError(message) {
        this.hideLoading();
        
        // Create or update error message
        let errorDiv = document.getElementById('error-message');
        if (!errorDiv) {
            errorDiv = document.createElement('div');
            errorDiv.id = 'error-message';
            errorDiv.className = 'fixed top-4 right-4 bg-red-500 text-white px-4 py-2 rounded-lg shadow-lg z-50';
            document.body.appendChild(errorDiv);
        }
        
        errorDiv.textContent = message;
        errorDiv.classList.remove('hidden');
        
        // Auto-hide after 5 seconds
        setTimeout(() => {
            errorDiv.classList.add('hidden');
        }, 5000);
    }

    updateElement(id, value) {
        const element = document.getElementById(id);
        if (element) {
            element.textContent = value;
        }
    }

    updateProgressBar(id, percentage) {
        const progressBar = document.getElementById(id);
        if (progressBar) {
            const fill = progressBar.querySelector('.progress-fill') || progressBar;
            fill.style.width = `${Math.min(100, Math.max(0, percentage))}%`;
        }
    }

    destroyChart(chartName) {
        if (this.charts[chartName]) {
            this.charts[chartName].destroy();
            delete this.charts[chartName];
        }
    }

    getCoverageClass(percentage) {
        if (percentage >= 80) return 'bg-green-100 text-green-800';
        if (percentage >= 60) return 'bg-yellow-100 text-yellow-800';
        return 'bg-red-100 text-red-800';
    }

    getStatusClass(status) {
        switch (status?.toLowerCase()) {
            case 'completed': return 'bg-green-100 text-green-800';
            case 'in_progress': return 'bg-blue-100 text-blue-800';
            case 'pending': return 'bg-yellow-100 text-yellow-800';
            case 'failed': return 'bg-red-100 text-red-800';
            default: return 'bg-gray-100 text-gray-800';
        }
    }

    formatDate(dateString) {
        if (!dateString) return 'N/A';
        try {
            return new Date(dateString).toLocaleDateString();
        } catch {
            return 'Invalid Date';
        }
    }

    getCurrentCountyId() {
        const countyCrumb = this.breadcrumbs.find(b => b.level === 'county');
        return countyCrumb?.id;
    }

    getCurrentCountyName() {
        const countyCrumb = this.breadcrumbs.find(b => b.level === 'county');
        return countyCrumb?.label;
    }

    renderInsights(insights) {
        const container = document.getElementById('insights-container');
        if (!container || !insights.length) return;

        container.innerHTML = insights.map(insight => `
            <div class="insight-item p-4 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg border-l-4 border-blue-500">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <svg class="w-5 h-5 text-blue-500 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h5 class="font-semibold text-gray-900">${insight.title}</h5>
                        <p class="text-sm text-gray-600 mt-1">${insight.description}</p>
                        ${insight.recommendation ? `
                            <p class="text-sm text-blue-600 mt-2 font-medium">
                                Recommendation: ${insight.recommendation}
                            </p>
                        ` : ''}
                    </div>
                </div>
            </div>
        `).join('');
    }

    renderPerformanceChart(trends) {
        const ctx = document.getElementById('performance-chart-canvas');
        if (!ctx) return;

        this.destroyChart('performance');

        this.charts.performance = new Chart(ctx, {
            type: 'line',
            data: {
                labels: trends.map(trend => trend.period),
                datasets: [{
                    label: 'Coverage %',
                    data: trends.map(trend => trend.coverage),
                    borderColor: 'rgb(59, 130, 246)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4
                }, {
                    label: 'Participation',
                    data: trends.map(trend => trend.participation),
                    borderColor: 'rgb(139, 92, 246)',
                    backgroundColor: 'rgba(139, 92, 246, 0.1)',
                    tension: 0.4,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        max: 100
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        grid: {
                            drawOnChartArea: false,
                        }
                    }
                }
            }
        });
    }
}

// Initialize dashboard when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.dashboard = new HealthcareTrainingDashboard();
});

// Export for global access
window.HealthcareTrainingDashboard = HealthcareTrainingDashboard;
    </script>
</body>
</html>