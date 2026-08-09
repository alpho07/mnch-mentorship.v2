<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>@yield('title', 'Training Analytics Dashboard')</title>

        <!-- Alpine.js -->
        <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.8/dist/cdn.min.js"></script>
        <!-- Bootstrap CSS -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <!-- Font Awesome -->
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
        <!-- Chart.js -->
        <link href="https://unpkg.com/charts.css/dist/charts.min.css" rel="stylesheet">
        <!-- Leaflet CSS -->
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" 
              integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" 
              crossorigin="" />
        <meta name="dashboard-mode" content="{{ $mode ?? 'training' }}">
        <meta name="dashboard-year" content="{{ $selectedYear ?? '' }}">
                


        @yield('additional-styles')
        

        <style>
            /* ── Clinical Teal Design System ── */
            :root {
                --teal:        #0097A7;
                --teal-dark:   #004D40;
                --teal-mid:    #00695C;
                --teal-light:  #26C6DA;
                --teal-50:     #E0F7FA;
                --teal-100:    #B2EBF2;
                --amber:       #F59E0B;
                --emerald:     #10B981;
                --violet:      #8B5CF6;
                --blue:        #2563EB;
                --red:         #EF4444;
                --gray-50:     #F8FAFC;
                --gray-100:    #F1F5F9;
                --gray-200:    #E2E8F0;
                --gray-500:    #64748B;
                --gray-700:    #334155;
                --gray-800:    #1E293B;
                --gray-900:    #0F172A;
            }

            /* Base Styles */
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background-color: var(--gray-50);
                color: var(--gray-700);
                line-height: 1.6;
            }

            /* ── Site navigation strip ── links back to the main MNCH Kenya
               site (Home / Resources / Categories) so this dashboard never
               becomes a dead end. */
            .mnch-topbar {
                background: #ffffff;
                border-bottom: 1px solid var(--gray-200);
                position: sticky;
                top: 0;
                z-index: 1050;
            }

            .mnch-topbar-inner {
                max-width: 1400px;
                margin: 0 auto;
                padding: 0.6rem 1.5rem;
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 1rem;
                flex-wrap: wrap;
            }

            .mnch-topbar-brand {
                display: flex;
                align-items: center;
                gap: 0.5rem;
                font-weight: 700;
                font-size: 1rem;
                color: var(--gray-900, #0F172A);
                text-decoration: none;
            }

            .mnch-topbar-brand:hover {
                color: var(--gray-900, #0F172A);
                text-decoration: none;
            }

            .mnch-topbar-logo {
                width: 2rem;
                height: 2rem;
                border-radius: 0.6rem;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                background: linear-gradient(135deg, #1D6FB8 0%, #4FB3E8 100%);
                color: #fff;
                font-size: 0.8rem;
                flex-shrink: 0;
            }

            .mnch-topbar-brand-accent {
                color: #1D6FB8;
            }

            .mnch-topbar-nav {
                display: flex;
                align-items: center;
                gap: 0.25rem;
            }

            .mnch-topbar-nav a {
                display: inline-flex;
                align-items: center;
                gap: 0.4rem;
                padding: 0.45rem 0.85rem;
                border-radius: 0.6rem;
                font-size: 0.875rem;
                font-weight: 600;
                color: var(--gray-700);
                text-decoration: none;
                transition: all 0.15s ease;
            }

            .mnch-topbar-nav a:hover,
            .mnch-topbar-nav a.active {
                background: #EAF7FE;
                color: #1D6FB8;
            }

            .mnch-topbar-left {
                display: flex;
                align-items: center;
                gap: 1.25rem;
                flex-wrap: wrap;
            }

            .mnch-topbar-extra {
                display: flex;
                align-items: center;
                gap: 0.5rem;
                flex-wrap: wrap;
            }

            /* Mode toggle, light-topbar variant */
            .mode-toggle {
                display: flex;
                background: var(--gray-100, #F1F5F9);
                border-radius: 10px;
                padding: 4px;
                border: 1px solid var(--gray-200, #E2E8F0);
            }
            .mode-btn {
                padding: .5rem 1rem;
                border-radius: 7px;
                border: none;
                background: transparent;
                color: var(--gray-700, #334155);
                font-weight: 600;
                font-size: .8rem;
                cursor: pointer;
                transition: all .2s;
                white-space: nowrap;
            }
            .mode-btn.active {
                background: #1D6FB8;
                color: #fff;
                box-shadow: 0 2px 8px rgba(29,111,184,.3);
            }
            .mode-btn:hover:not(.active) {
                background: var(--gray-200, #E2E8F0);
                color: var(--gray-900, #0F172A);
            }

            .mnch-topbar-admin {
                display: inline-flex;
                align-items: center;
                gap: 0.4rem;
                padding: 0.5rem 0.9rem;
                border-radius: 0.6rem;
                font-size: 0.8rem;
                font-weight: 700;
                color: #fff;
                background: linear-gradient(135deg, #1D6FB8 0%, #4FB3E8 100%);
                text-decoration: none;
                white-space: nowrap;
            }
            .mnch-topbar-admin:hover {
                color: #fff;
                opacity: 0.9;
            }

            .container-fluid {
                max-width: 1400px;
                margin: 0 auto;
            }

            /* Breadcrumb */
            .breadcrumb-custom {
                background: linear-gradient(90deg, var(--teal-50) 0%, var(--teal-100) 100%);
                border-radius: 12px;
                padding: 0.85rem 1.5rem;
                margin-bottom: 0;
                border: 1px solid var(--teal-100);
            }

            .breadcrumb-custom .breadcrumb {
                margin-bottom: 0;
                background: none;
                padding: 0;
            }

            .breadcrumb-custom a {
                color: var(--teal-mid);
                text-decoration: none;
                font-weight: 600;
            }

            .breadcrumb-custom a:hover {
                color: var(--teal-dark);
                text-decoration: underline;
            }

            .breadcrumb-item.active { color: var(--gray-700); font-weight: 500; }
            .breadcrumb-item + .breadcrumb-item::before { color: var(--teal); }

            /* Page Header — inner pages */
            .page-header {
                background: linear-gradient(135deg, var(--teal-dark) 0%, var(--teal-mid) 40%, var(--teal) 80%, var(--teal-light) 100%);
                color: white;
                padding: 1.75rem 2rem;
                margin-bottom: 1.75rem;
                border-radius: 16px;
                position: relative;
                overflow: hidden;
                margin-top: 1rem;
            }

            .page-header::before {
                content: '';
                position: absolute;
                top: -40px;
                right: -40px;
                width: 180px;
                height: 180px;
                background: rgba(255,255,255,0.08);
                border-radius: 50%;
            }

            .page-header::after {
                content: '';
                position: absolute;
                bottom: -30px;
                left: -20px;
                width: 120px;
                height: 120px;
                background: rgba(255,255,255,0.05);
                border-radius: 50%;
            }

            .page-header h1, .page-header h2 {
                font-weight: 700;
                position: relative;
                z-index: 2;
            }

            .page-header p {
                opacity: 0.9;
                position: relative;
                z-index: 2;
                margin-bottom: 0;
            }

            .page-header .badge {
                position: relative;
                z-index: 2;
                background: rgba(255,255,255,.18) !important;
                color: #fff !important;
                border: 1px solid rgba(255,255,255,.3);
                backdrop-filter: blur(4px);
            }

            /* Mode indicator badge */
            .mode-indicator {
                background: rgba(255,255,255,.18);
                color: white;
                padding: 0.5rem 1.25rem;
                border-radius: 25px;
                font-size: 0.8rem;
                font-weight: 700;
                border: 1px solid rgba(255,255,255,.3);
                letter-spacing: .04em;
                text-transform: uppercase;
            }

            /* Stats Cards */
            .stats-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 1.25rem;
                margin-bottom: 1.75rem;
            }

            .stats-card {
                background: linear-gradient(135deg, var(--teal-dark) 0%, var(--teal) 100%);
                color: white;
                border-radius: 16px;
                padding: 1.75rem;
                transition: transform 0.2s ease, box-shadow 0.2s;
                border: none;
                position: relative;
                overflow: hidden;
                text-align: center;
                box-shadow: 0 4px 16px rgba(0,151,167,.25);
            }

            .stats-card:hover {
                transform: translateY(-3px);
                box-shadow: 0 8px 24px rgba(0,151,167,.35);
            }

            .stats-card::before {
                content: '';
                position: absolute;
                top: -30px;
                right: -30px;
                width: 100px;
                height: 100px;
                background: rgba(255,255,255,0.1);
                border-radius: 50%;
            }

            .stats-card h3 {
                font-size: 2.4rem;
                font-weight: 800;
                margin-bottom: 0.4rem;
                position: relative;
                z-index: 2;
                letter-spacing: -0.02em;
            }

            .stats-card p {
                opacity: 0.9;
                margin-bottom: 0;
                position: relative;
                z-index: 2;
                font-weight: 600;
                font-size: .875rem;
            }

            .stats-card .icon {
                position: absolute;
                top: 1rem;
                right: 1rem;
                font-size: 1.75rem;
                opacity: 0.25;
                z-index: 1;
            }

            /* Cards */
            .card {
                border: none;
                box-shadow: 0 2px 12px rgba(0,0,0,.08);
                border-radius: 14px;
                transition: box-shadow 0.2s ease;
                overflow: hidden;
            }

            .card:hover {
                box-shadow: 0 6px 20px rgba(0,0,0,.13);
            }

            .card-header {
                background: white;
                border-bottom: 1px solid var(--gray-200);
                border-radius: 14px 14px 0 0 !important;
                padding: 1.25rem 1.5rem;
                position: relative;
            }

            .card-header::after {
                content: '';
                position: absolute;
                bottom: 0;
                left: 1.5rem;
                right: 1.5rem;
                height: 2px;
                background: linear-gradient(90deg, var(--teal) 0%, var(--teal-light) 100%);
                border-radius: 1px;
            }

            .card-header h5 {
                margin-bottom: 0.2rem;
                font-weight: 700;
                color: var(--gray-800);
                font-size: .95rem;
            }

            .card-header small {
                color: var(--gray-500);
            }

            .card-body {
                padding: 1.5rem;
            }

            /* Form Controls */
            .form-select {
                border-radius: 10px;
                border: 1px solid var(--gray-200);
                padding: 0.65rem 1rem;
                transition: all 0.2s ease;
                background-color: white;
            }

            .form-select:focus {
                border-color: var(--teal);
                box-shadow: 0 0 0 3px rgba(0,151,167,0.12);
            }

            /* Buttons */
            .btn {
                border-radius: 10px;
                font-weight: 600;
                padding: 0.65rem 1.25rem;
                transition: all 0.2s ease;
                border: none;
                font-size: .875rem;
            }

            .btn-primary {
                background: linear-gradient(135deg, var(--teal-mid) 0%, var(--teal) 100%);
                color: white;
                box-shadow: 0 4px 12px rgba(0,151,167,.3);
            }

            .btn-primary:hover {
                background: linear-gradient(135deg, var(--teal-dark) 0%, var(--teal-mid) 100%);
                color: white;
                transform: translateY(-1px);
                box-shadow: 0 6px 16px rgba(0,151,167,.4);
            }

            .btn-outline-primary {
                border: 2px solid var(--teal);
                color: var(--teal);
                background: white;
            }

            .btn-outline-primary:hover {
                background: var(--teal);
                color: white;
                transform: translateY(-1px);
                box-shadow: 0 4px 12px rgba(0,151,167,.3);
            }

            .btn-outline-secondary {
                border: 1.5px solid var(--gray-200);
                color: var(--gray-700);
                background: white;
            }

            .btn-outline-secondary:hover {
                background: var(--gray-100);
                color: var(--gray-800);
            }

            .btn-group .btn {
                border-radius: 0;
            }

            .btn-group .btn:first-child {
                border-radius: 10px 0 0 10px;
            }

            .btn-group .btn:last-child {
                border-radius: 0 10px 10px 0;
            }

            /* Progress Bars */
            .progress {
                height: 6px;
                border-radius: 3px;
                background-color: #e2e8f0;
                overflow: hidden;
            }

            .progress-bar {
                border-radius: 3px;
                transition: width 0.6s ease;
            }

            /* Badge */
            .badge {
                padding: 0.5rem 1rem;
                border-radius: 20px;
                font-weight: 600;
                font-size: 0.75rem;
            }

            /* Text Utilities */
            .fw-semibold {
                font-weight: 600;
            }

            .text-gradient {
                background: linear-gradient(135deg, var(--teal) 0%, var(--teal-light) 100%);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                background-clip: text;
            }

            /* Empty State */
            .empty-state {
                text-align: center;
                padding: 3rem 1rem;
                color: #6b7280;
            }

            .empty-state .icon {
                font-size: 4rem;
                margin-bottom: 1rem;
                opacity: 0.5;
            }

            .empty-state h6 {
                margin-top: 1rem;
                color: #374151;
                font-weight: 600;
            }

            /* Heatmap */
            .heatmap-container {
                min-height: 400px;
                background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
                border-radius: 12px;
                padding: 1.5rem;
                position: relative;
                overflow: hidden;
                border: 1px solid #e2e8f0;
            }

            .heatmap-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
                gap: 1rem;
                height: 100%;
            }

            .heatmap-cell {
                padding: 1.25rem;
                border-radius: 12px;
                border: 1px solid #e5e7eb;
                background: white;
                cursor: pointer;
                transition: all 0.3s ease;
                display: flex;
                flex-direction: column;
                justify-content: space-between;
                min-height: 140px;
                position: relative;
                overflow: hidden;
            }

            .heatmap-cell:hover {
                transform: scale(1.05);
                box-shadow: 0 8px 20px rgba(0,0,0,0.15);
                z-index: 10;
            }

            /* Responsive */
            @media (max-width: 768px) {
                .stats-grid {
                    grid-template-columns: repeat(2, 1fr);
                    gap: 1rem;
                }

                .stats-card {
                    padding: 1.5rem;
                }

                .stats-card h3 {
                    font-size: 2rem;
                }

                .page-header {
                    padding: 1.5rem;
                }

                .page-header h1 {
                    font-size: 1.75rem;
                }

                .heatmap-grid {
                    grid-template-columns: 1fr;
                }

                .mode-toggle {
                    width: 100%;
                    margin-bottom: 1rem;
                }
            }

            @media (max-width: 576px) {
                .container-fluid {
                    padding-left: 1rem;
                    padding-right: 1rem;
                }

                .stats-grid {
                    grid-template-columns: 1fr;
                }

                .breadcrumb-custom {
                    padding: 0.75rem 1rem;
                    margin-bottom: 1rem;
                }

                .card-header,
                .card-body {
                    padding: 1rem;
                }

                .heatmap-cell {
                    min-height: 120px;
                    padding: 1rem;
                }
            }

            /* Animation */
            .fade-in {
                animation: fadeIn 0.5s ease-in;
            }

            @keyframes fadeIn {
                from {
                    opacity: 0;
                    transform: translateY(20px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            @yield('custom-styles')
        </style>
    </head>
    <body>
    <div class="mnch-topbar">
        <div class="mnch-topbar-inner">
            <div class="mnch-topbar-left">
                <a href="{{ route('home') }}" class="mnch-topbar-brand">
                    <span class="mnch-topbar-logo"><i class="fas fa-stethoscope"></i></span>
                    <span><span class="mnch-topbar-brand-accent">MNCH</span> Kenya</span>
                </a>
                <nav class="mnch-topbar-nav">
                    <a href="{{ route('home') }}" class="{{ request()->routeIs('home') ? 'active' : '' }}">
                        <i class="fas fa-home"></i> Home
                    </a>
                    <a href="{{ route('resources.index') }}" class="{{ request()->routeIs('resources.*') ? 'active' : '' }}">
                        <i class="fas fa-book-open"></i> Resources
                    </a>
                    <a href="{{ route('categories.index') }}" class="{{ request()->routeIs('categories.*') ? 'active' : '' }}">
                        <i class="fas fa-th-large"></i> Categories
                    </a>
                </nav>
            </div>

            <div class="mnch-topbar-extra">
                @yield('topbar-extra')
            </div>
        </div>
    </div>

    @if(isset($breadcrumbs) && count($breadcrumbs) > 1)
        <!-- Breadcrumb -->
        <div class="container-fluid px-4 py-3">
            <nav class="breadcrumb-custom">
                <ol class="breadcrumb mb-0">
                    @foreach($breadcrumbs as $crumb)
                    @if($crumb['url'])
                            <li class="breadcrumb-item">
                                <a href="{{ $crumb['url'] }}">{{ $crumb['name'] }}</a>
                            </li>
                        @else
                            <li class="breadcrumb-item active">{{ $crumb['name'] }}</li>
                        @endif
                    @endforeach
                </ol>
            </nav>
        </div>
    @endif

    @yield('content')

        <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <!-- Chart.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
        <!-- Chart.js data-label plugin — registered globally but defaulted off,
             so individual charts opt in via `options.plugins.datalabels`,
             keeping every other existing chart on the platform unaffected. -->
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0"></script>
    <script>
        if (window.Chart && window.ChartDataLabels) {
            Chart.register(ChartDataLabels);
            Chart.defaults.set('plugins.datalabels', { display: false });
        }
    </script>
        <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" 
                integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" 
    crossorigin=""></script>

    <script>
            // Common year filter functionality
            function initYearFilter() {
                const yearFilter = document.getElementById('yearFilter');
                if (yearFilter) {
                    yearFilter.addEventListener('change', function() {
                        const url = new URL(window.location);
                        const selectedValue = this.value;
                    
                        if (selectedValue && selectedValue !== '') {
                            url.searchParams.set('year', selectedValue);
                        } else {
                            url.searchParams.delete('year');
                        }
                    
                        // Preserve mode if it exists
                        const currentMode = url.searchParams.get('mode') || '{{ $mode ?? "training" }}';
                        url.searchParams.set('mode', currentMode);
                    
                        window.location.href = url.toString();
                    });
                }
            }

            // Common mode switching functionality
            function switchMode(mode) {
                const url = new URL(window.location);
                url.searchParams.set('mode', mode);
            
                // Preserve year if it exists
                const currentYear = '{{ $selectedYear ?? "" }}';
                if (currentYear) {
                    url.searchParams.set('year', currentYear);
                }
            
                window.location.href = url.toString();
            }

            // Initialize common functionality
            document.addEventListener('DOMContentLoaded', function() {
                initYearFilter();
        @yield('page-scripts')
            });
    </script>

    @yield('scripts')
    @stack('scripts')
</body>
</html>