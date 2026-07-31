@extends('layouts.dashboard')
@php
    $dashTitle = match($mode) {
        'mentorship' => 'Infant, Child & Newborn Care Dashboard',
        'emonc'      => 'EmONC Analytics Dashboard',
        'mentor'     => 'Mentor Analytics Dashboard',
        'assessment' => 'Assessment Analytics Dashboard',
        'training'   => 'Training Analytics Dashboard',
        default      => ucfirst($mode).' Analytics Dashboard',
    };
@endphp
@section('title', $dashTitle.' — MNCH')

@section('content')

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/intro.js/7.2.0/introjs.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/intro.js/7.2.0/intro.min.js"></script>

<!-- AOS scroll animations -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js"></script>

<meta name="dashboard-mode" content="{{ $mode ?? 'training' }}">
<meta name="dashboard-year" content="{{ $selectedYear ?? '' }}">

<style>
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

/* ── Base ── */
body { background: var(--gray-50); font-family: 'Segoe UI', system-ui, sans-serif; color: var(--gray-700); }

/* ── Hero header ── */
.dash-hero {
    background: linear-gradient(135deg, var(--teal-dark) 0%, var(--teal-mid) 40%, var(--teal) 75%, var(--teal-light) 100%);
    padding: 1.5rem 2rem 2.5rem;
    position: relative;
    overflow: hidden;
    margin-bottom: 0;
}
.dash-hero::before {
    content: '';
    position: absolute; inset: 0;
    background: url("data:image/svg+xml,%3Csvg width='80' height='24' viewBox='0 0 80 24' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M0 12 L8 12 L12 4 L16 20 L20 12 L28 12' stroke='rgba(255,255,255,0.08)' stroke-width='1.5' fill='none'/%3E%3C/svg%3E") repeat-x center;
    opacity: 0.6;
}
.dash-hero-inner { position: relative; z-index: 2; display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem; }
.dash-hero-left h1 { font-size: 1.9rem; font-weight: 800; color: #fff; margin: 0 0 .25rem; letter-spacing: -0.02em; }
.dash-hero-left p  { color: rgba(255,255,255,.8); margin: 0; font-size: .95rem; }
.hero-badge { display: inline-flex; align-items: center; gap: .4rem; background: rgba(255,255,255,.15); border: 1px solid rgba(255,255,255,.25); color: #fff; border-radius: 20px; padding: .3rem .85rem; font-size: .78rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; margin-bottom: .6rem; backdrop-filter: blur(4px); }
.dash-hero-right { display: flex; align-items: center; gap: .75rem; flex-wrap: wrap; }

/* Mode toggle */
.mode-toggle { display: flex; background: rgba(0,0,0,.25); border-radius: 10px; padding: 4px; border: 1px solid rgba(255,255,255,.15); }
.mode-btn { padding: .55rem 1.25rem; border-radius: 7px; border: none; background: transparent; color: rgba(255,255,255,.75); font-weight: 600; font-size: .875rem; cursor: pointer; transition: all .2s; white-space: nowrap; }
.mode-btn.active { background: rgba(255,255,255,.22); color: #fff; box-shadow: 0 2px 8px rgba(0,0,0,.2); }
.mode-btn:hover:not(.active) { background: rgba(255,255,255,.1); color: #fff; }

/* Hero action buttons */
.btn-hero { border-radius: 8px; padding: .55rem 1rem; font-size: .85rem; font-weight: 600; border: 1px solid rgba(255,255,255,.3); background: rgba(255,255,255,.12); color: #fff; cursor: pointer; transition: all .2s; }
.btn-hero:hover { background: rgba(255,255,255,.22); color: #fff; }
.btn-hero.btn-hero-white { background: #fff; color: var(--teal-dark); border-color: #fff; }
.btn-hero.btn-hero-white:hover { background: var(--teal-50); color: var(--teal-dark); }

/* ── KPI cards pulled up over hero ── */
.kpi-strip-wrap {
    margin-top: -1.75rem;
    padding: 0 1.5rem;
    margin-bottom: 1.5rem;
}
.kpi-strip { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 1rem; }
.kpi-card {
    background: #fff;
    border-radius: 14px;
    padding: 1.25rem 1.25rem 1.1rem;
    box-shadow: 0 4px 20px rgba(0,0,0,.09);
    border-top: 4px solid var(--teal);
    display: flex; flex-direction: column; gap: .4rem;
    transition: transform .2s, box-shadow .2s;
    position: relative; overflow: hidden;
}
.kpi-card:hover { transform: translateY(-3px); box-shadow: 0 8px 28px rgba(0,0,0,.13); }
.kpi-card::after { content: ''; position: absolute; top: -20px; right: -20px; width: 70px; height: 70px; border-radius: 50%; opacity: .08; background: var(--kpi-accent, var(--teal)); }
.kpi-card:nth-child(1) { --kpi-accent: var(--teal);    border-top-color: var(--teal); }
.kpi-card:nth-child(2) { --kpi-accent: var(--amber);   border-top-color: var(--amber); }
.kpi-card:nth-child(3) { --kpi-accent: var(--emerald); border-top-color: var(--emerald); }
.kpi-card:nth-child(4) { --kpi-accent: var(--violet);  border-top-color: var(--violet); }
.kpi-card:nth-child(5) { --kpi-accent: var(--blue);    border-top-color: var(--blue); }
.kpi-card:nth-child(6) { --kpi-accent: #E11D48;        border-top-color: #E11D48; }
.kpi-icon { width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center; background: rgba(0,151,167,.1); color: var(--kpi-accent, var(--teal)); font-size: 1rem; margin-bottom: .2rem; }
.kpi-card:nth-child(2) .kpi-icon { background: rgba(245,158,11,.1); }
.kpi-card:nth-child(3) .kpi-icon { background: rgba(16,185,129,.1); }
.kpi-card:nth-child(4) .kpi-icon { background: rgba(139,92,246,.1); }
.kpi-card:nth-child(5) .kpi-icon { background: rgba(37,99,235,.1); }
.kpi-card:nth-child(6) .kpi-icon { background: rgba(225,29,72,.1); }
.kpi-value { font-size: 2rem; font-weight: 800; color: var(--gray-800); line-height: 1; letter-spacing: -0.03em; }
.kpi-label { font-size: .78rem; font-weight: 600; color: var(--gray-500); text-transform: uppercase; letter-spacing: .05em; }
.kpi-trend { display: inline-flex; align-items: center; gap: .3rem; font-size: .75rem; font-weight: 600; padding: .2rem .55rem; border-radius: 20px; }
.kpi-trend.up   { background: #D1FAE5; color: #065F46; }
.kpi-trend.down { background: #FEE2E2; color: #991B1B; }
.kpi-trend.flat { background: #F1F5F9; color: #475569; }

/* ── Section wrappers ── */
.dash-section { padding: 0 1.5rem; margin-bottom: 1.5rem; }
.section-title { font-size: 1rem; font-weight: 700; color: var(--gray-800); margin-bottom: 1rem; display: flex; align-items: center; gap: .5rem; }
.section-title i { color: var(--teal); }

/* ── Insight cards ── */
.insights-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: .85rem; }
.insight-card { background: #fff; border-radius: 12px; padding: 1rem 1.15rem; display: flex; gap: .85rem; align-items: flex-start; box-shadow: 0 2px 10px rgba(0,0,0,.06); border-left: 4px solid var(--teal); }
.insight-card.success { border-left-color: var(--emerald); }
.insight-card.warning { border-left-color: var(--amber); }
.insight-card.danger  { border-left-color: var(--red); }
.insight-card.primary { border-left-color: var(--teal); }
.insight-card.info    { border-left-color: var(--blue); }
.insight-icon { flex-shrink: 0; width: 34px; height: 34px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: .9rem; background: var(--teal-50); color: var(--teal); }
.insight-card.success .insight-icon { background: #D1FAE5; color: #065F46; }
.insight-card.warning .insight-icon { background: #FEF3C7; color: #92400E; }
.insight-card.danger  .insight-icon { background: #FEE2E2; color: #991B1B; }
.insight-card.primary .insight-icon { background: var(--teal-50); color: var(--teal); }
.insight-card.info    .insight-icon { background: #DBEAFE; color: #1D4ED8; }
.insight-text { font-size: .855rem; color: var(--gray-700); line-height: 1.5; }

/* ── Charts ── */
.chart-card { background: #fff; border-radius: 14px; box-shadow: 0 2px 12px rgba(0,0,0,.07); border: 1px solid var(--gray-200); overflow: hidden; }
.chart-card-header { padding: 1.1rem 1.25rem .6rem; border-bottom: 1px solid var(--gray-100); display: flex; justify-content: space-between; align-items: center; }
.chart-card-header h6 { font-size: .9rem; font-weight: 700; color: var(--gray-800); margin: 0; display: flex; align-items: center; gap: .4rem; }
.chart-card-header h6 i { color: var(--teal); font-size: .85rem; }
.chart-card-header small { font-size: .75rem; color: var(--gray-500); }
.chart-card-body { padding: 1.1rem 1.25rem 1.25rem; }
.chart-2-3 { flex: 2; min-width: 0; }
.chart-1-3 { flex: 1; min-width: 0; }
.chart-row { display: flex; gap: 1.25rem; }
.chart-half { flex: 1; min-width: 0; }

/* ── Map section ── */
.map-container { position: relative; border-radius: 8px; overflow: hidden; }
#kenyaMap { border-radius: 8px; border: 1px solid var(--gray-200); }
.map-legend { position: absolute; bottom: 16px; right: 16px; background: rgba(255,255,255,.97); padding: 10px 14px; border-radius: 10px; box-shadow: 0 4px 16px rgba(0,0,0,.12); border: 1px solid var(--gray-200); z-index: 1000; min-width: 160px; }
.map-legend h6 { font-size: .78rem; font-weight: 800; color: var(--gray-700); margin: 0 0 8px; text-transform: uppercase; letter-spacing: .05em; }
.legend-item { display: flex; align-items: center; margin-bottom: 4px; font-size: .74rem; color: var(--gray-600); }
.legend-item:last-child { margin-bottom: 0; }
.legend-color { width: 14px; height: 14px; border-radius: 3px; margin-right: 7px; flex-shrink: 0; border: 1px solid rgba(0,0,0,.08); }
.map-overlay { position: absolute; top: 16px; left: 16px; background: rgba(255,255,255,.97); backdrop-filter: blur(8px); padding: 14px; border-radius: 14px; box-shadow: 0 6px 24px rgba(0,0,0,.1); border: 1px solid rgba(255,255,255,.8); z-index: 1000; min-width: 210px; }
.overlay-header { display: flex; align-items: center; gap: 8px; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 2px solid var(--teal-50); }
.overlay-header h6 { margin: 0; font-size: 1rem; font-weight: 700; color: var(--gray-800); }
.coverage-section { display: flex; align-items: flex-start; gap: 10px; padding: 10px 0; border-bottom: 1px solid var(--gray-100); }
.coverage-section:last-child { border-bottom: none; }
.coverage-icon { flex-shrink: 0; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; background: var(--teal-50); border-radius: 7px; color: var(--teal); }
.coverage-content { flex: 1; }
.coverage-label { font-size: .85rem; font-weight: 600; color: var(--gray-700); margin-bottom: 2px; }
.coverage-fraction { font-size: .78rem; color: var(--gray-500); font-weight: 500; }
.coverage-fraction span { font-weight: 700; color: var(--gray-700); }
.coverage-percentage { font-size: .82rem; font-weight: 700; color: var(--teal); }
.coverage-percentage-large { font-size: 1.05rem; font-weight: 800; color: var(--emerald); }
.overall-section { background: linear-gradient(135deg, var(--teal-50) 0%, #E0F2FE 100%); border-radius: 8px; padding: 12px; margin-top: 6px; border: 1px solid var(--teal-100); }
.loading-overlay { position: absolute; inset: 0; background: rgba(255,255,255,.94); display: none; align-items: center; justify-content: center; z-index: 1000; border-radius: 8px; }
.loading-overlay.show { display: flex; }

/* ── Programs list ── */
.programs-panel { background: #fff; border-radius: 14px; box-shadow: 0 2px 12px rgba(0,0,0,.07); border: 1px solid var(--gray-200); overflow: hidden; height: 100%; }
.programs-header { padding: 1.1rem 1.25rem; border-bottom: 1px solid var(--gray-100); background: var(--gray-50); }
.programs-header h6 { font-size: .9rem; font-weight: 700; color: var(--gray-800); margin: 0 0 .15rem; }
.programs-header small { font-size: .76rem; color: var(--gray-500); }
.programs-body { max-height: 560px; overflow-y: auto; }
.training-card { padding: 1rem 1.25rem; border-bottom: 1px solid var(--gray-100); cursor: pointer; transition: all .2s; position: relative; }
.training-card:last-child { border-bottom: none; }
.training-card:hover { background: var(--teal-50); }
.training-card.selected { background: linear-gradient(90deg, rgba(0,151,167,.07) 0%, rgba(0,151,167,.02) 100%); border-left: 3px solid var(--teal); }
.training-title { font-size: .9rem; font-weight: 600; color: var(--gray-800); margin-bottom: .5rem; line-height: 1.35; }
.training-card.selected .training-title { color: var(--teal-dark); }
.training-meta { display: grid; grid-template-columns: 1fr 1fr; gap: .5rem; margin-bottom: .5rem; }
.meta-item { background: var(--gray-50); border-radius: 6px; padding: .35rem .5rem; text-align: center; }
.training-card.selected .meta-item { background: rgba(0,151,167,.08); }
.meta-label { display: block; font-size: .7rem; color: var(--gray-500); font-weight: 600; text-transform: uppercase; letter-spacing: .03em; }
.meta-value { display: block; font-size: .95rem; font-weight: 700; color: var(--gray-800); }
.training-card.selected .meta-value { color: var(--teal); }
.training-badges { display: flex; gap: .4rem; flex-wrap: wrap; }
.training-badge { font-size: .7rem; padding: .2rem .5rem; border-radius: 12px; background: var(--gray-100); color: var(--gray-600); font-weight: 600; }
.training-info { display: flex; justify-content: space-between; align-items: center; }
.action-icon { color: var(--teal); opacity: .6; }

/* ── Mentorship sidebar ── */
.layout-mentorship { display: flex; gap: 1.25rem; }
.layout-mentorship .map-section { flex: 2; min-width: 0; }
.layout-mentorship .sidebar-section { width: 320px; flex-shrink: 0; }
.county-sidebar { background: #fff; border-radius: 14px; box-shadow: 0 2px 12px rgba(0,0,0,.07); border: 1px solid var(--gray-200); height: 100%; display: flex; flex-direction: column; overflow: hidden; }
.sidebar-header { padding: 1.1rem 1.25rem; border-bottom: 1px solid var(--gray-100); background: var(--gray-50); }
.sidebar-content { flex: 1; overflow-y: auto; padding: 1rem; }
.facility-item { background: #fff; border: 1px solid var(--gray-200); border-radius: 10px; padding: .9rem; margin-bottom: .65rem; cursor: pointer; transition: all .2s; }
.facility-item:hover { border-color: var(--teal); box-shadow: 0 4px 14px rgba(0,151,167,.12); }
.facility-item h6 { font-size: .875rem; font-weight: 700; color: var(--gray-800); margin-bottom: .5rem; }
.facility-item .facility-meta { display: grid; grid-template-columns: 1fr 1fr; gap: .5rem; margin-bottom: .6rem; }
.facility-item .meta-value { font-size: .95rem; font-weight: 700; color: var(--teal); }
.facility-item .meta-label { font-size: .72rem; color: var(--gray-500); }
.sidebar-empty { text-align: center; padding: 2.5rem 1rem; color: var(--gray-500); }
.summary-cards-grid { display: grid; grid-template-columns: 1fr 1fr; gap: .65rem; margin-bottom: 1rem; }
.summary-card { background: var(--teal-50); border-radius: 8px; padding: .75rem; text-align: center; }
.summary-card h4 { font-size: 1.4rem; font-weight: 800; color: var(--teal-dark); margin: 0 0 .2rem; }
.summary-card p { font-size: .75rem; color: var(--gray-600); margin: 0; font-weight: 600; }

/* ── Filter card ── */
.filter-card { background: #fff; border-radius: 14px; box-shadow: 0 2px 12px rgba(0,0,0,.07); border: 1px solid var(--gray-200); padding: 1.25rem; margin-bottom: 1.25rem; }
.filter-card .form-select, .filter-card .form-control { border-radius: 8px; border: 1px solid var(--gray-200); font-size: .875rem; }
.filter-card .form-select:focus, .filter-card .form-control:focus { border-color: var(--teal); box-shadow: 0 0 0 3px rgba(0,151,167,.12); }
.form-label { font-size: .78rem; font-weight: 700; color: var(--gray-700); text-transform: uppercase; letter-spacing: .04em; margin-bottom: .4rem; }

/* ── Status legend chips ── */
.status-legend { display: flex; gap: .5rem; flex-wrap: wrap; margin-top: .5rem; }
.status-chip { display: inline-flex; align-items: center; gap: .3rem; font-size: .72rem; font-weight: 700; padding: .22rem .6rem; border-radius: 20px; }
.chip-active    { background: #D1FAE5; color: #065F46; }
.chip-completed { background: #DBEAFE; color: #1E40AF; }
.chip-draft     { background: #FEF3C7; color: #92400E; }
.chip-cancelled { background: #FEE2E2; color: #991B1B; }

/* ── Table inside card ── */
.analytics-table { width: 100%; border-collapse: collapse; font-size: .85rem; }
.analytics-table th { background: linear-gradient(90deg, var(--teal-dark) 0%, var(--teal-mid) 100%); color: rgba(255,255,255,.9); font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; padding: .7rem 1rem; text-align: left; white-space: nowrap; }
.analytics-table td { padding: .65rem 1rem; border-bottom: 1px solid var(--gray-100); color: var(--gray-700); vertical-align: middle; }
.analytics-table tr:nth-child(even) td { background: #FAFFFE; }
.analytics-table tr:hover td { background: var(--teal-50); }

/* Responsive */
@media(max-width:1200px){ .kpi-strip{grid-template-columns:repeat(3,1fr);} .layout-mentorship{flex-direction:column;} .layout-mentorship .sidebar-section{width:100%;} }
@media(max-width:992px){ .chart-row{flex-direction:column;} }
@media(max-width:768px){ .kpi-strip{grid-template-columns:repeat(2,1fr);} .dash-hero{padding:1rem 1rem 2rem;} .kpi-strip-wrap{padding:0 1rem;} .dash-section{padding:0 1rem;} }
@media(max-width:480px){ .kpi-strip{grid-template-columns:1fr;} }

/* tour */
.introjs-tooltip{max-width:380px;font-size:13.5px;border-radius:12px;}
.introjs-button{background:var(--teal);border:none;color:#fff;border-radius:7px;padding:8px 18px;font-weight:600;}
.introjs-button:hover{background:var(--teal-mid);}

/* ── Scroll animations ── */
[data-aos] { will-change: transform, opacity; }
.aos-init { opacity: 0; }
</style>

<!-- ████████ HERO HEADER ████████ -->
<div class="dash-hero">
    <div class="dash-hero-inner">
        <div class="dash-hero-left">
            <div class="hero-badge"><i class="fas fa-heartbeat"></i> MNCH Analytics</div>
            <h1>{{ $dashTitle }}</h1>
            <p>
                @if($mode === 'training')
                    Comprehensive insights across Kenya healthcare training programs
                @elseif($mode === 'mentorship')
                    Infant, child &amp; newborn care facility-based mentorship — progress, modules &amp; mentee outcomes
                @elseif($mode === 'emonc')
                    Emergency Obstetric &amp; Newborn Care mentorship — mentee progress, competency assessments &amp; certification
                @elseif($mode === 'mentor')
                    Mentor performance, CPD points, and active class activity
                @elseif($mode === 'assessment')
                    Facility readiness assessments across counties and subcounties
                @else
                    Deep analytics across facility-based mentorship programs
                @endif
                @if($selectedYear) — <strong>{{ $selectedYear }}</strong>@endif
            </p>
        </div>
        <div class="dash-hero-right">
            <!-- Guided Tour -->
            <button class="btn-hero" id="startTourBtn" title="Take a guided tour">
                <i class="fas fa-question-circle me-1"></i> Tour
            </button>

            <!-- Filters toggle -->
            <button class="btn-hero" type="button" data-bs-toggle="collapse" data-bs-target="#filtersCollapse">
                <i class="fas fa-sliders-h me-1"></i> Filters
            </button>

            <!-- Mode toggle -->
            <div class="mode-toggle" data-intro="Switch between Mentorship, EmONC, Mentor, Assessment and Training analytics modes." data-step="2">
                <button type="button" class="mode-btn {{ $mode === 'mentorship' ? 'active' : '' }}" data-mode="mentorship"
                    title="Infant, Child &amp; Newborn Care Dashboard — facility-based mentorship progress, modules &amp; mentee outcomes">
                    <i class="fas fa-user-friends me-1"></i> Mentorships
                </button>

                <button type="button" class="mode-btn {{ $mode === 'emonc' ? 'active' : '' }}" data-mode="emonc"
                    title="EmONC Analytics — Emergency Obstetric &amp; Newborn Care mentee progress, competency assessments &amp; certification">
                    <i class="fas fa-heart-pulse me-1"></i> EmONC
                </button>

                <button type="button" class="mode-btn {{ $mode === 'mentor' ? 'active' : '' }}" data-mode="mentor"
                    title="Mentor Analytics — mentor CPD points, active classes, mentee reach and leaderboards">
                    <i class="fas fa-user-tie me-1"></i> Mentors
                </button>
                <button type="button" class="mode-btn {{ $mode === 'assessment' ? 'active' : '' }}" data-mode="assessment"
                    title="Assessment Analytics — facility readiness assessments across counties and subcounties">
                    <i class="fas fa-clipboard-check me-1"></i> Assessments
                </button>
                <button type="button" class="mode-btn {{ $mode === 'training' ? 'active' : '' }}" data-mode="training"
                    title="Training Analytics — comprehensive insights across Kenya healthcare training programs">
                    <i class="fas fa-chalkboard-teacher me-1"></i> Trainings
                </button>
            </div>

            <a href="{{ url('/admin') }}" class="btn-hero btn-hero-white">
                <i class="fas fa-tachometer-alt me-1"></i> Admin
            </a>
        </div>
    </div>
</div>

@if($mode === 'assessment')
    @include('analytics.dashboard.assessment-mode')
@elseif($mode === 'mentor')
    @include('analytics.dashboard.mentor-mode')
@elseif($mode === 'emonc')
    @include('analytics.dashboard.emonc-mode')
@else
<!-- ████████ KPI STRIP ████████ -->
<div class="kpi-strip-wrap" data-intro="Key performance metrics for the selected mode and filters." data-step="4">
            @php
        $totalPrograms    = $summaryStats['totalPrograms'] ?? 0;
        $totalParticipants= $summaryStats['totalParticipants'] ?? 0;
        $totalFacilities  = $summaryStats['totalFacilities'] ?? 0;
        $facilityCoverage = $summaryStats['facilityCoverage'] ?? 0;
        $avgParticipants  = $extendedStats['avgParticipants'] ?? 0;
        $yoy              = $extendedStats['yearComparison'] ?? [];
        $yoyChange        = $yoy['change'] ?? 0;
        $attendanceRate   = $extendedStats['attendanceRate'] ?? 0;
    @endphp
    <div class="kpi-strip">
        <div class="kpi-card" data-aos="fade-up" data-aos-delay="0">
            <div class="kpi-icon"><i class="fas fa-{{ $mode === 'training' ? 'chalkboard-teacher' : 'user-friends' }}"></i></div>
            <div class="kpi-value counter-animate" id="kpi-programs" data-counter="{{ $totalPrograms }}">{{ number_format($totalPrograms) }}</div>
            <div class="kpi-label">{{ $mode === 'training' ? 'Training Programs' : 'Mentorships' }}</div>
        </div>
        <div class="kpi-card" data-aos="fade-up" data-aos-delay="100">
            <div class="kpi-icon"><i class="fas fa-users"></i></div>
            <div class="kpi-value counter-animate" id="kpi-participants" data-counter="{{ $totalParticipants }}">{{ number_format($totalParticipants) }}</div>
            <div class="kpi-label">{{ $mode === 'training' ? 'Participants' : 'Mentees' }}</div>
        </div>
        <div class="kpi-card" data-aos="fade-up" data-aos-delay="200">
            <div class="kpi-icon"><i class="fas fa-hospital"></i></div>
            <div class="kpi-value counter-animate" id="kpi-facilities" data-counter="{{ $totalFacilities }}">{{ number_format($totalFacilities) }}</div>
            <div class="kpi-label">Facilities Covered</div>
        </div>
        <div class="kpi-card" data-aos="fade-up" data-aos-delay="300">
            <div class="kpi-icon"><i class="fas fa-map-marked-alt"></i></div>
            <div class="kpi-value counter-animate" id="kpi-coverage" data-counter="{{ $facilityCoverage }}" data-suffix="%" data-decimals="1">{{ $facilityCoverage }}%</div>
            <div class="kpi-label">Facility Coverage</div>
            @if($facilityCoverage >= 60)
                <span class="kpi-trend up"><i class="fas fa-arrow-up"></i> Good</span>
            @elseif($facilityCoverage >= 30)
                <span class="kpi-trend flat"><i class="fas fa-minus"></i> Moderate</span>
            @else
                <span class="kpi-trend down"><i class="fas fa-arrow-down"></i> Low</span>
            @endif
        </div>
        <div class="kpi-card" data-aos="fade-up" data-aos-delay="400">
            <div class="kpi-icon"><i class="fas fa-chart-line"></i></div>
            <div class="kpi-value counter-animate" data-counter="{{ $avgParticipants }}" data-decimals="1">{{ $avgParticipants }}</div>
            <div class="kpi-label">Avg {{ $mode === 'training' ? 'per Training' : 'Mentees/Program' }}</div>
            @if($yoyChange > 0)
                <span class="kpi-trend up"><i class="fas fa-arrow-up"></i> {{ $yoyChange }}% YoY</span>
            @elseif($yoyChange < 0)
                <span class="kpi-trend down"><i class="fas fa-arrow-down"></i> {{ abs($yoyChange) }}% YoY</span>
            @else
                <span class="kpi-trend flat"><i class="fas fa-minus"></i> Stable</span>
            @endif
        </div>
        @if($mode === 'mentorship')
        <div class="kpi-card" data-aos="fade-up" data-aos-delay="500">
            <div class="kpi-icon"><i class="fas fa-clipboard-check"></i></div>
            <div class="kpi-value counter-animate" id="kpi-attendance" data-counter="{{ $attendanceRate }}" data-suffix="%" data-decimals="1">{{ $attendanceRate }}%</div>
            <div class="kpi-label">Module Attendance</div>
            @if($attendanceRate >= 80)
                <span class="kpi-trend up"><i class="fas fa-arrow-up"></i> Strong</span>
            @elseif($attendanceRate >= 60)
                <span class="kpi-trend flat"><i class="fas fa-minus"></i> Moderate</span>
            @else
                <span class="kpi-trend down"><i class="fas fa-arrow-down"></i> Low</span>
            @endif
        </div>
        @endif
    </div>
</div>

<!-- ████████ FILTERS (collapsible) ████████ -->
<div class="dash-section" data-aos="fade-up" data-aos-delay="50">
    <div class="collapse" id="filtersCollapse" data-intro="Filter by year, program, or search to narrow your analysis." data-step="3">
        <div class="filter-card">
            <div class="row g-3 align-items-end">
                <div class="col-lg-4 col-md-6">
                    <label class="form-label">Program</label>
                    <select class="form-select" id="program-filter">
                        <option value="">All Programs</option>
                        @foreach($trainingsList as $training)
                            <option value="{{ $training->id }}">{{ $training->title }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-3 col-md-6">
                    <label class="form-label">Year</label>
                    <select class="form-select" id="year-filter">
                        <option value="" {{ empty($selectedYear) ? 'selected' : '' }}>All Years</option>
                        @foreach($availableYears ?? [] as $year)
                            <option value="{{ $year }}" {{ $selectedYear == $year ? 'selected' : '' }}>{{ $year }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-3 col-md-6">
                    <label class="form-label">Search</label>
                    <input type="text" class="form-control" id="search-filter" placeholder="Search programs…">
                </div>
                <div class="col-lg-2 col-md-6">
                    <button class="btn btn-outline-secondary w-100" id="clear-filters">
                        <i class="fas fa-times me-1"></i>Clear
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ████████ INTELLIGENT INSIGHTS ████████ -->
@if(count($insights) > 0)
<div class="dash-section" data-aos="fade-up">
    <div class="section-title"><i class="fas fa-lightbulb"></i> Intelligent Insights</div>
    <div class="insights-grid">
        @foreach($insights as $index => $insight)
        <div class="insight-card {{ $insight['type'] }}" data-aos="fade-up" data-aos-delay="{{ $index * 100 }}">
            <div class="insight-icon"><i class="fas fa-{{ $insight['icon'] }}"></i></div>
            <div class="insight-text">{{ $insight['text'] }}</div>
        </div>
        @endforeach
    </div>
</div>
@endif

<!-- ████████ CHARTS ROW 1 — Trend + Status ████████ -->
<div class="dash-section" data-aos="fade-up">
    <div class="section-title"><i class="fas fa-chart-area"></i> Trends & Distribution</div>
    <div class="chart-row" data-intro="Monthly trend and program status distribution charts." data-step="9">
        <!-- 12-month trend -->
        <div class="chart-card chart-2-3" data-aos="fade-right" data-aos-delay="100">
            <div class="chart-card-header">
                <h6><i class="fas fa-chart-line"></i> 12-Month {{ $mode === 'training' ? 'Enrollment' : 'Activity' }} Trend</h6>
                <small id="trendSubtitle">Monthly count over the last 12 months</small>
            </div>
            <div class="chart-card-body">
                <canvas id="trendChart" style="max-height:220px;"></canvas>
            </div>
        </div>
        <!-- Status donut -->
        <div class="chart-card chart-1-3" data-aos="fade-left" data-aos-delay="200">
            <div class="chart-card-header">
                <h6><i class="fas fa-chart-pie"></i> {{ $mode === 'training' ? 'Training' : 'Mentorship' }} Status</h6>
                <small>Breakdown by status</small>
            </div>
            <div class="chart-card-body" style="display:flex;flex-direction:column;align-items:center;gap:.75rem;">
                <canvas id="statusChart" style="max-height:190px;max-width:220px;"></canvas>
                @php $statusMap = $extendedStats['statusBreakdown'] ?? []; @endphp
                <div class="status-legend">
                    @foreach($statusMap as $st => $cnt)
                        <span class="status-chip chip-{{ in_array($st,['active','ongoing']) ? 'active' : (in_array($st,['completed']) ? 'completed' : (in_array($st,['draft']) ? 'draft' : 'cancelled')) }}">
                            {{ ucfirst($st) }}: {{ $cnt }}
                        </span>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ████████ CHARTS ROW 2 — Department + Cadre ████████ -->
<div class="dash-section" data-aos="fade-up">
    <div class="section-title"><i class="fas fa-users-cog"></i> Participant Breakdown</div>
    <div class="chart-row" data-intro="Breakdown by department and professional cadre." data-step="10">
        <div class="chart-card chart-half" data-aos="fade-up" data-aos-delay="100">
            <div class="chart-card-header">
                <h6><i class="fas fa-building"></i> By Department</h6>
                <small id="deptChartSubtitle">Top departments by participation</small>
            </div>
            <div class="chart-card-body">
                <canvas id="departmentChart" style="max-height:260px;"></canvas>
            </div>
        </div>
        <div class="chart-card chart-half" data-aos="fade-up" data-aos-delay="200">
            <div class="chart-card-header">
                <h6><i class="fas fa-user-md"></i> By Cadre</h6>
                <small id="cadreChartSubtitle">Professional roles distribution</small>
            </div>
            <div class="chart-card-body">
                <canvas id="cadreChart" style="max-height:260px;"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- ████████ CHARTS ROW 3 — Facility Type + Top Counties ████████ -->
<div class="dash-section" data-aos="fade-up">
    <div class="section-title"><i class="fas fa-hospital-symbol"></i> Coverage & Geographic Reach</div>
    <div class="chart-row" data-intro="Facility type coverage and top counties by participation." data-step="11">
        <div class="chart-card chart-half" data-aos="fade-up" data-aos-delay="100">
            <div class="chart-card-header">
                <h6><i class="fas fa-clinic-medical"></i> Coverage by Facility Type</h6>
                <small id="facilityChartSubtitle">Penetration by facility category</small>
            </div>
            <div class="chart-card-body">
                <canvas id="facilityTypeChart" style="max-height:250px;cursor:pointer;"></canvas>
            </div>
        </div>
        <div class="chart-card chart-half" data-aos="fade-up" data-aos-delay="200">
            <div class="chart-card-header">
                <h6><i class="fas fa-map-marked-alt"></i> Top Counties by {{ $mode === 'training' ? 'Participants' : 'Mentees' }}</h6>
                <small>Leading counties for {{ $mode }}</small>
            </div>
            <div class="chart-card-body">
                <canvas id="topCountiesChart" style="max-height:250px;"></canvas>
            </div>
        </div>
    </div>
</div>

@if($mode === 'mentorship')
<!-- ████████ CHARTS ROW 4 — Mentee Status (mentorship only) ████████ -->
<div class="dash-section" data-aos="fade-up">
    <div class="section-title"><i class="fas fa-graduation-cap"></i> Mentee & Program Analytics</div>
    <div class="chart-row">
        <div class="chart-card chart-half" data-aos="fade-up" data-aos-delay="100">
            <div class="chart-card-header">
                <h6><i class="fas fa-users"></i> Mentee Status Distribution</h6>
                <small>Current status of enrolled mentees</small>
            </div>
            <div class="chart-card-body" style="display:flex;flex-direction:column;align-items:center;gap:.75rem;">
                <canvas id="menteeStatusChart" style="max-height:200px;max-width:240px;"></canvas>
            </div>
        </div>
        <div class="chart-card chart-half" data-aos="fade-up" data-aos-delay="200">
            <div class="chart-card-header">
                <h6><i class="fas fa-trophy"></i> Top Mentorship Programs</h6>
                <small>By mentee count</small>
            </div>
            <div class="chart-card-body">
                <canvas id="topProgramsChart" style="max-height:200px;"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- ████████ CHARTS ROW 5 — Class Lifecycle & Completion (mentorship only) ████████ -->
<div class="dash-section" data-aos="fade-up">
    <div class="section-title"><i class="fas fa-project-diagram"></i> Class Lifecycle & Completion</div>
    <div class="chart-row">
        <div class="chart-card chart-half" data-aos="fade-up" data-aos-delay="100">
            <div class="chart-card-header">
                <h6><i class="fas fa-chalkboard"></i> Class Lifecycle Status</h6>
                <small>Draft · Active · Completed · Cancelled</small>
            </div>
            <div class="chart-card-body" style="display:flex;flex-direction:column;align-items:center;gap:.75rem;">
                <canvas id="classStatusChart" style="max-height:200px;max-width:240px;"></canvas>
            </div>
        </div>
        <div class="chart-card chart-half" data-aos="fade-up" data-aos-delay="200">
            <div class="chart-card-header">
                <h6><i class="fas fa-chart-bar"></i> Mentee Module Completion</h6>
                <small>Distribution of modules completed per mentee</small>
            </div>
            <div class="chart-card-body">
                <canvas id="completionDistributionChart" style="max-height:220px;"></canvas>
            </div>
        </div>
    </div>
</div>
@endif

<!-- ████████ MAP + PROGRAMS SECTION ████████ -->
<div class="dash-section" data-aos="fade-up">
    <div class="section-title"><i class="fas fa-map"></i> Geographic Coverage Map</div>

    @if($mode === 'training')
    <!-- TRAINING: Programs list + Map side by side -->
    <div class="row g-3" data-intro="Interactive Kenya county coverage map with program list." data-step="5">
        <div class="col-lg-4" data-aos="fade-right" data-aos-delay="100">
            <div class="programs-panel">
                <div class="programs-header">
                    <h6 id="programsTitle">Training Programs</h6>
                    <small>Click a program to filter map · <span id="programCount">{{ $trainingsList->count() }}</span> found</small>
                </div>
                <div class="programs-body" id="trainingsList">
                    @forelse($trainingsList as $training)
                    <div class="training-card" data-training-id="{{ $training->id }}">
                        <div class="training-title">{{ $training->title }}</div>
                        <div class="training-meta">
                            <div class="meta-item">
                                <span class="meta-label">Participants</span>
                                <span class="meta-value">{{ number_format($training->total_participants ?? 0) }}</span>
                            </div>
                            <div class="meta-item">
                                <span class="meta-label">Facilities</span>
                                <span class="meta-value">{{ number_format($training->facilities_count ?? 0) }}</span>
                            </div>
                        </div>
                        <div class="training-info">
                            <div class="training-badges">
                                @if($training->start_date)
                                    <span class="training-badge">{{ \Carbon\Carbon::parse($training->start_date)->format('Y') }}</span>
                                @endif
                                @if($training->identifier)
                                    <span class="training-badge">{{ $training->identifier }}</span>
                                @endif
                                @if($training->status)
                                    <span class="training-badge chip-{{ $training->status }}">{{ ucfirst($training->status) }}</span>
                                @endif
                            </div>
                            <i class="fas fa-chevron-right action-icon"></i>
                        </div>
                    </div>
                    @empty
                    <div class="sidebar-empty">
                        <i class="fas fa-chart-bar fa-2x mb-3"></i>
                        <p style="font-weight:700;margin-bottom:.3rem;">No Training Programs Found</p>
                        <p style="font-size:.85rem;margin-bottom:.75rem;">No programs match the selected period.</p>
                        <a href="{{ request()->fullUrlWithQuery(['year' => null]) }}" style="font-size:.82rem;font-weight:600;color:var(--gray-700);text-decoration:underline;">Reset Period</a>
                    </div>
                    @endforelse
                </div>
            </div>
        </div>
        <div class="col-lg-8" data-aos="fade-left" data-aos-delay="200">
            <div class="chart-card" style="height:100%;">
                <div class="chart-card-header">
                    <h6><i class="fas fa-map"></i> <span id="mapTitle">Kenya Counties Training Coverage Map</span></h6>
                    <small id="mapSubtitle">Click on a county to drill into facilities and participants</small>
                </div>
                <div class="chart-card-body" style="padding-bottom:.5rem;">
                    <div class="map-container" data-intro="Click any county to drill down." data-step="6">
                        <div id="kenyaMap" style="height:500px;border-radius:8px;"></div>
                        <!-- Coverage Overlay -->
                        <div class="map-overlay" id="mapOverlay" data-intro="Live coverage stats." data-step="7">
                            <div class="overlay-header">
                                <i class="fas fa-chart-pie" style="color:var(--teal);"></i>
                                <h6>Coverage Summary</h6>
                            </div>
                            <div class="coverage-section">
                                <div class="coverage-icon"><i class="fas fa-hospital"></i></div>
                                <div class="coverage-content">
                                    <div class="coverage-label">Facility Coverage</div>
                                    <div class="coverage-fraction"><span id="overlay-facilities-active">{{ $summaryStats['totalFacilities'] ?? 0 }}</span>/<span id="overlay-facilities-total">0</span> facilities</div>
                                    <div class="coverage-percentage"><span id="overlay-facility-coverage">{{ number_format($summaryStats['facilityCoverage'] ?? 0,1) }}</span>% coverage</div>
                                </div>
                            </div>
                            <div class="coverage-section">
                                <div class="coverage-icon"><i class="fas fa-map-marked-alt"></i></div>
                                <div class="coverage-content">
                                    <div class="coverage-label">County Coverage</div>
                                    <div class="coverage-fraction"><span id="overlay-counties-active">0</span>/<span id="overlay-counties-total">47</span> counties</div>
                                    <div class="coverage-percentage"><span id="overlay-county-coverage">0.0</span>% coverage</div>
                                </div>
                            </div>
                            <div class="coverage-section overall-section">
                                <div class="coverage-icon"><i class="fas fa-chart-line"></i></div>
                                <div class="coverage-content">
                                    <div class="coverage-label">Overall Coverage</div>
                                    <div class="coverage-percentage-large"><span id="overlay-overall-coverage">{{ number_format($summaryStats['facilityCoverage'] ?? 0,1) }}</span>%</div>
                                </div>
                            </div>
                        </div>
                        <!-- Legend -->
                        <div class="map-legend" id="mapLegend" data-intro="Color coding for coverage levels." data-step="8">
                            <h6>Coverage Legend</h6>
                            <div class="legend-item"><div class="legend-color" style="background:#15803d;"></div>Extremely High (300+)</div>
                            <div class="legend-item"><div class="legend-color" style="background:#22c55e;"></div>High (100–299)</div>
                            <div class="legend-item"><div class="legend-color" style="background:#fef08a;"></div>Moderate (10–99)</div>
                            <div class="legend-item"><div class="legend-color" style="background:#ffcccc;"></div>Minimal (1–9)</div>
                            <div class="legend-item"><div class="legend-color" style="background:#9ca3af;"></div>No Training</div>
                        </div>
                        <div class="loading-overlay">
                            <div class="text-center">
                                <div class="spinner-border mb-2" style="color:var(--teal);" role="status"><span class="visually-hidden">Loading…</span></div>
                                <div><strong>Loading map data…</strong></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    @if($mode === 'mentorship')
    <!-- MENTORSHIP: Map + county sidebar -->
    <div class="layout-mentorship">
        <div class="map-section" data-aos="fade-right" data-aos-delay="100">
            <div class="chart-card h-100">
                <div class="chart-card-header">
                    <h6><i class="fas fa-map"></i> <span id="mapTitle">Kenya Counties Mentorship Coverage Map</span></h6>
                    <small id="mapSubtitle">Click a county to see facilities with mentorships</small>
                </div>
                <div class="chart-card-body" style="padding-bottom:.5rem;">
                    <div class="map-container">
                        <div id="kenyaMap" style="height:500px;border-radius:8px;"></div>
                        <!-- Mentorship overlay -->
                        <div class="map-overlay" id="mapOverlay">
                            <div class="overlay-header">
                                <i class="fas fa-user-friends" style="color:var(--emerald);"></i>
                                <h6>Mentorship Summary</h6>
                            </div>
                            <div class="coverage-section">
                                <div class="coverage-icon" style="background:#D1FAE5;color:#065F46;"><i class="fas fa-list-ol"></i></div>
                                <div class="coverage-content">
                                    <div class="coverage-label">Mentorships</div>
                                    <div class="coverage-percentage-large" id="overlay-programs">{{ $summaryStats['totalPrograms'] ?? 0 }}</div>
                                </div>
                            </div>
                            <div class="coverage-section">
                                <div class="coverage-icon" style="background:#DBEAFE;color:#1D4ED8;"><i class="fas fa-users"></i></div>
                                <div class="coverage-content">
                                    <div class="coverage-label">Mentees</div>
                                    <div class="coverage-percentage-large" id="overlay-participants">{{ number_format($summaryStats['totalParticipants'] ?? 0) }}</div>
                                </div>
                            </div>
                            <div class="coverage-section overall-section">
                                <div class="coverage-icon"><i class="fas fa-hospital"></i></div>
                                <div class="coverage-content">
                                    <div class="coverage-label">Facilities</div>
                                    <div class="coverage-percentage-large" id="overlay-facilities">{{ $summaryStats['totalFacilities'] ?? 0 }}</div>
                                </div>
                            </div>
                        </div>
                        <!-- Legend -->
                        <div class="map-legend" id="mapLegend">
                            <h6>Coverage Levels</h6>
                            <div class="legend-item"><div class="legend-color" style="background:#15803d;"></div>Extremely High (300+)</div>
                            <div class="legend-item"><div class="legend-color" style="background:#22c55e;"></div>High (100–299)</div>
                            <div class="legend-item"><div class="legend-color" style="background:#fef08a;"></div>Moderate (10–99)</div>
                            <div class="legend-item"><div class="legend-color" style="background:#ffcccc;"></div>Minimal (1–9)</div>
                            <div class="legend-item"><div class="legend-color" style="background:#9ca3af;"></div>No Mentorship</div>
                        </div>
                        <div class="loading-overlay">
                            <div class="text-center">
                                <div class="spinner-border mb-2" style="color:var(--emerald);" role="status"><span class="visually-hidden">Loading…</span></div>
                                <div><strong>Loading map data…</strong></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="sidebar-section" data-aos="fade-left" data-aos-delay="200">
            <div class="county-sidebar">
                <div class="sidebar-header">
                    <h6 id="sidebar-title" style="font-weight:700;color:var(--gray-800);margin:0 0 .15rem;">Select a County</h6>
                    <small style="color:var(--gray-500);font-size:.76rem;">Click on a county to view mentorship facilities</small>
                </div>
                <div class="sidebar-content" id="sidebar-content">
                    <div class="sidebar-empty">
                        <i class="fas fa-map-marker-alt fa-2x mb-3" style="color:var(--teal);opacity:.5;"></i>
                        <p style="font-size:.875rem;">Click a county on the map to view its mentorship programs.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>

<!-- Top Programs Table (training mode) -->
@if($mode === 'training' && count($extendedStats['topPrograms'] ?? []) > 0)
<div class="dash-section" data-aos="fade-up">
    <div class="section-title"><i class="fas fa-trophy"></i> Top Performing Training Programs</div>
    <div class="chart-card" data-aos="fade-up" data-aos-delay="100">
        <table class="analytics-table">
            <thead>
                <tr>
                    <th>Program</th>
                    <th>Participants</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($extendedStats['topPrograms'] as $index => $prog)
                <tr data-aos="fade-up" data-aos-delay="{{ ($index + 1) * 80 }}">
                    <td style="font-weight:600;">{{ $prog['title'] }}</td>
                    <td><strong>{{ number_format($prog['count']) }}</strong></td>
                    <td><span class="status-chip chip-{{ $prog['status'] }}">{{ ucfirst($prog['status'] ?? 'N/A') }}</span></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

<!-- ████████ JSON DATA PAYLOADS ████████ -->
<script type="application/json" id="chart-data">@json($chartData ?? [])</script>
<script type="application/json" id="extended-data">@json($extendedStats ?? [])</script>

@endif

@endsection

@section('page-scripts')
(function() {
    'use strict';

    // ── Teal clinical color palette for Chart.js ──
    const TEAL_COLORS = [
        '#0097A7','#26C6DA','#004D40','#10B981','#F59E0B',
        '#8B5CF6','#2563EB','#EF4444','#EC4899','#14B8A6',
        '#F97316','#84CC16','#6366F1','#06B6D4','#D97706'
    ];
    const TEAL_ALPHA = (i, a=0.85) => {
        const c = TEAL_COLORS[i % TEAL_COLORS.length];
        // convert hex to rgba approximation — just return solid colors
        return c;
    };

    // ── Dashboard state ──
    const Dashboard = {
        state: {
            map: null,
            countyLayer: null,
            selectedTraining: null,
            selectedCounty: null,
            charts: {},
            currentMode: document.querySelector('meta[name="dashboard-mode"]')?.content || 'training',
            currentYear: document.querySelector('meta[name="dashboard-year"]')?.content || '',
            facilityTypeData: null,
            originalTrainingsList: null
        },

        coverageLevels: {
            EXTREMELY_HIGH: { min: 300, color: '#15803d', label: 'Extremely High' },
            HIGH:           { min: 100, color: '#22c55e', label: 'High' },
            MODERATE:       { min: 10,  color: '#fef08a', label: 'Moderate' },
            MINIMAL:        { min: 1,   color: '#ffcccc', label: 'Minimal' },
            NONE:           { min: 0,   color: '#9ca3af', label: 'No Training' }
        },

        getCoverageLevel(n) {
            for (const lvl of Object.values(this.coverageLevels)) {
                if (n >= lvl.min) return lvl;
            }
            return this.coverageLevels.NONE;
        },

        // ── Map ──
        Map: {
            init() {
                if (!document.getElementById('kenyaMap')) return;
                if (Dashboard.state.map) Dashboard.state.map.remove();
                Dashboard.state.map = L.map('kenyaMap', {
                    center: [-0.5, 37.5], zoom: 7, minZoom: 6, maxZoom: 12,
                    maxBounds: [[-5.5, 33.0],[5.5, 42.0]], maxBoundsViscosity: 1.0
                });
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap contributors', opacity: 0.65
                }).addTo(Dashboard.state.map);
                this.loadCountyData();
            },

            async loadCountyData(filterTrainingId = null) {
                const overlay = document.querySelector('.loading-overlay');
                if (overlay) overlay.classList.add('show');
                try {
                    const p = new URLSearchParams();
                    p.set('mode', Dashboard.state.currentMode);
                    if (Dashboard.state.currentYear) p.set('year', Dashboard.state.currentYear);
                    if (filterTrainingId) p.set('training_id', filterTrainingId);
                    const res = await fetch(`/analytics/dashboard/geojson?${p}`);
                    if (!res.ok) throw new Error(`HTTP ${res.status}`);
                    const data = await res.json();
                    if (Dashboard.state.countyLayer) Dashboard.state.map.removeLayer(Dashboard.state.countyLayer);
                    Dashboard.state.countyLayer = L.geoJSON(data, {
                        style: f => this.getCountyStyle(f.properties),
                        onEachFeature: (f, layer) => this.setupCountyInteractions(f, layer)
                    }).addTo(Dashboard.state.map);
                    const bounds = Dashboard.state.countyLayer.getBounds();
                    if (bounds.isValid()) Dashboard.state.map.fitBounds(bounds, { padding:[20,20], maxZoom: filterTrainingId ? 9 : 8 });
                    this.updateMapOverlay(data);
                } catch (e) {
                    console.error('Map error:', e);
                } finally {
                    if (overlay) overlay.classList.remove('show');
                }
            },

            updateMapOverlay(data) {
                const features = data.features || [];
                let activeFacilities = 0, totalFacilities = 0, activeCounties = 0;
                features.forEach(f => {
                    const p = f.properties;
                    if (p.total_participants > 0 || p.total_programs > 0) activeCounties++;
                    activeFacilities += p.facilities_with_programs || 0;
                    totalFacilities  += p.total_facilities || 0;
                });
                const facCov   = totalFacilities > 0 ? ((activeFacilities/totalFacilities)*100).toFixed(1) : '0.0';
                const cntyCov  = ((activeCounties/47)*100).toFixed(1);
                const overall  = ((parseFloat(facCov)+parseFloat(cntyCov))/2).toFixed(1);
                const upd = (id, val) => { const el = document.getElementById(id); if(el) el.textContent = val; };
                upd('overlay-facilities-active', activeFacilities);
                upd('overlay-facilities-total',  totalFacilities);
                upd('overlay-facility-coverage', facCov);
                upd('overlay-counties-active',   activeCounties);
                upd('overlay-counties-total',    47);
                upd('overlay-county-coverage',   cntyCov);
                upd('overlay-overall-coverage',  overall);
            },

            getCountyStyle(props) {
                const lvl = Dashboard.getCoverageLevel(props.total_participants || 0);
                return { fillColor: lvl.color, weight: 2, opacity: 1, color: 'white', fillOpacity: 0.8 };
            },

            createTooltip(props) {
                const lvl = Dashboard.getCoverageLevel(props.total_participants || 0);
                const pLabel = Dashboard.state.currentMode === 'training' ? 'Participants' : 'Mentees';
                const prLabel = Dashboard.state.currentMode === 'training' ? 'Programs' : 'Mentorships';
                return `<div style="min-width:200px;">
                    <strong style="display:block;margin-bottom:6px;">${props.county_name || 'Unknown'} County</strong>
                    <span style="background:${lvl.color};color:${(props.total_participants||0)>50?'white':'black'};padding:3px 8px;border-radius:10px;font-size:11px;font-weight:700;">${lvl.label} — ${props.total_participants||0} ${pLabel}</span>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-top:8px;font-size:12px;">
                        <div style="text-align:center;background:#f8fafc;border-radius:4px;padding:4px;"><strong style="color:#0097A7;">${props.total_programs||0}</strong><div>${prLabel}</div></div>
                        <div style="text-align:center;background:#f8fafc;border-radius:4px;padding:4px;"><strong style="color:#0097A7;">${props.facilities_with_programs||0}/${props.total_facilities||0}</strong><div>Facilities</div></div>
                        <div style="text-align:center;background:#f8fafc;border-radius:4px;padding:4px;"><strong style="color:#0097A7;">${props.coverage_percentage||0}%</strong><div>Coverage</div></div>
                        <div style="text-align:center;background:#f8fafc;border-radius:4px;padding:4px;"><strong style="color:#0097A7;">${props.total_participants||0}</strong><div>${pLabel}</div></div>
                    </div></div>`;
            },

            setupCountyInteractions(feature, layer) {
                layer.bindTooltip(this.createTooltip(feature.properties), { permanent:false, direction:'center', className:'county-tooltip' });
                layer.on({
                    mouseover: e => { e.target.setStyle({weight:3,color:'#0097A7',fillOpacity:0.9}); e.target.openTooltip(); },
                    mouseout:  e => { Dashboard.state.countyLayer.resetStyle(e.target); e.target.closeTooltip(); },
                    click:     e => {
                        const id = feature.properties.county_id;
                        if (!id) return;
                        if (Dashboard.state.currentMode === 'training') {
                            const p = new URLSearchParams({mode:'training'});
                            if (Dashboard.state.currentYear) p.set('year', Dashboard.state.currentYear);
                            if (Dashboard.state.selectedTraining) p.set('training_id', Dashboard.state.selectedTraining);
                            window.location.href = `/analytics/dashboard/county/${id}?${p}`;
                        } else {
                            this.loadCountyMentorships(id);
                        }
                    }
                });
            },

            async loadCountyMentorships(countyId) {
                try {
                    const p = new URLSearchParams({mode:'mentorship'});
                    if (Dashboard.state.currentYear) p.set('year', Dashboard.state.currentYear);
                    const data = await (await fetch(`/analytics/dashboard/county/${countyId}/mentorships?${p}`)).json();
                    this.updateMentorshipSidebar(data);
                } catch(e) { console.error('Sidebar error:', e); }
            },

            updateMentorshipSidebar(data) {
                const title   = document.getElementById('sidebar-title');
                const content = document.getElementById('sidebar-content');
                if (title) title.textContent = `${data.county.name} County`;
                if (!content) return;
                if (data.facilities.length === 0) {
                    content.innerHTML = `<div class="sidebar-empty"><i class="fas fa-hospital fa-2x mb-3" style="color:var(--teal);opacity:.4;"></i><p>No mentorship facilities in ${data.county.name} County.</p></div>`;
                    return;
                }
                const stats = data.summary || {};
                content.innerHTML = `
                    <div class="summary-cards-grid">
                        <div class="summary-card"><h4>${stats.total_mentorships||0}</h4><p>Mentorships</p></div>
                        <div class="summary-card"><h4>${stats.total_mentees||0}</h4><p>Mentees</p></div>
                    </div>
                    ${data.facilities.map(f=>`
                        <div class="facility-item" data-facility-id="${f.id}" data-county-id="${data.county.id}">
                            <h6>${f.name}</h6>
                            <div class="facility-meta">
                                <div class="meta-item"><div class="meta-value">${f.mentorship_count}</div><div class="meta-label">Mentorships</div></div>
                                <div class="meta-item"><div class="meta-value">${f.total_mentees}</div><div class="meta-label">Mentees</div></div>
                            </div>
                            <div style="display:flex;justify-content:space-between;align-items:center;">
                                <small style="color:var(--gray-500);font-size:.76rem;">${f.facility_type} · ${f.subcounty}</small>
                                <i class="fas fa-arrow-right" style="color:var(--teal);"></i>
                            </div>
                        </div>`).join('')}`;
                document.querySelectorAll('.facility-item').forEach(el => {
                    el.addEventListener('click', function() {
                        const fid = this.dataset.facilityId, cid = this.dataset.countyId;
                        if (fid && cid) {
                            const p = new URLSearchParams({mode:'mentorship'});
                            if (Dashboard.state.currentYear) p.set('year', Dashboard.state.currentYear);
                            window.location.href = `/analytics/dashboard/county/${cid}/facility/${fid}/mentorships?${p}`;
                        }
                    });
                });

                // Refresh AOS so dynamically added sidebar items animate
                if (typeof AOS !== 'undefined') {
                    setTimeout(() => AOS.refresh(), 50);
                }
            }
        },

        // ── Charts ──
        Charts: {
            chartData: {},
            extData: {},

            init() {
                const cdEl = document.getElementById('chart-data');
                const exEl = document.getElementById('extended-data');
                try { this.chartData = cdEl ? JSON.parse(cdEl.textContent) : {}; } catch(e){}
                try { this.extData   = exEl ? JSON.parse(exEl.textContent)  : {}; } catch(e){}

                this.initTrendChart();
                this.initStatusChart();
                this.initDepartmentChart();
                this.initCadreChart();
                this.initFacilityTypeChart();
                this.initTopCountiesChart();
                if (Dashboard.state.currentMode === 'mentorship') {
                    this.initMenteeStatusChart();
                    this.initTopProgramsChart();
                    this.initClassStatusChart();
                    this.initCompletionDistributionChart();
                }
            },

            mkConfig(type, labels, datasets, opts={}) {
                return {
                    type, data: { labels, datasets },
                    options: {
                        responsive: true, maintainAspectRatio: true,
                        plugins: { legend: { position:'bottom', labels:{ font:{size:11}, padding:10, boxWidth:12 } }, tooltip:{ callbacks:{} } },
                        ...opts
                    }
                };
            },

            initTrendChart() {
                const el = document.getElementById('trendChart');
                if (!el) return;
                const trend = this.extData.trend12 || [];
                const labels = trend.map(t => t.month);
                const counts = trend.map(t => t.count);
                const ctx = el.getContext('2d');
                // gradient fill
                const grad = ctx.createLinearGradient(0,0,0,200);
                grad.addColorStop(0,'rgba(0,151,167,0.3)');
                grad.addColorStop(1,'rgba(0,151,167,0.02)');
                Dashboard.state.charts.trend = new Chart(ctx, {
                    type: 'line',
                    data: { labels, datasets: [{
                        label: Dashboard.state.currentMode === 'training' ? 'Enrollments' : 'Programs Started',
                        data: counts, borderColor: '#0097A7', backgroundColor: grad,
                        borderWidth: 2.5, fill: true, tension: 0.4, pointRadius: 4,
                        pointBackgroundColor: '#0097A7', pointBorderColor: '#fff', pointBorderWidth: 2
                    }]},
                    options: {
                        responsive: true, maintainAspectRatio: true,
                        plugins: { legend:{ display:false }, tooltip:{ mode:'index', intersect:false } },
                        scales: {
                            x: { grid:{ display:false }, ticks:{ font:{size:11} } },
                            y: { beginAtZero:true, grid:{ color:'rgba(0,0,0,0.05)' }, ticks:{ font:{size:11} } }
                        }
                    }
                });
            },

            initStatusChart() {
                const el = document.getElementById('statusChart');
                if (!el) return;
                const sb = this.extData.statusBreakdown || {};
                const labels = Object.keys(sb).map(k => k.charAt(0).toUpperCase() + k.slice(1));
                const data   = Object.values(sb);
                if (!data.length) return;
                const statusColors = { active:'#10B981', ongoing:'#10B981', completed:'#2563EB', draft:'#F59E0B', cancelled:'#EF4444', unknown:'#9CA3AF' };
                const colors = Object.keys(sb).map(k => statusColors[k] || '#9CA3AF');
                Dashboard.state.charts.status = new Chart(el, {
                    type: 'doughnut',
                    data: { labels, datasets: [{ data, backgroundColor: colors, borderWidth: 2, borderColor:'#fff', hoverOffset: 4 }] },
                    options: {
                        responsive: true, maintainAspectRatio: true, cutout: '65%',
                        plugins: { legend:{ position:'bottom', labels:{ font:{size:11}, padding:8, boxWidth:12 } } }
                    }
                });
            },

            initDepartmentChart() {
                const el = document.getElementById('departmentChart');
                if (!el) return;
                const depts = (this.chartData.departments || []).slice(0,10);
                if (!depts.length) return;
                Dashboard.state.charts.dept = new Chart(el, {
                    type: 'bar',
                    data: {
                        labels: depts.map(d => d.name || 'Unknown'),
                        datasets: [{ label:'Participants', data: depts.map(d => d.count||0),
                            backgroundColor: TEAL_COLORS.slice(0,depts.length),
                            borderRadius: 6, borderSkipped: false }]
                    },
                    options: {
                        indexAxis: 'y', responsive: true, maintainAspectRatio: true,
                        plugins: { legend:{ display:false } },
                        scales: { x:{ beginAtZero:true, grid:{ color:'rgba(0,0,0,.04)' } }, y:{ ticks:{ font:{size:11} } } }
                    }
                });
            },

            initCadreChart() {
                const el = document.getElementById('cadreChart');
                if (!el) return;
                const cadres = (this.chartData.cadres || []).slice(0,8);
                if (!cadres.length) return;
                Dashboard.state.charts.cadre = new Chart(el, {
                    type: 'doughnut',
                    data: {
                        labels: cadres.map(c => c.name||'Unknown'),
                        datasets: [{ data: cadres.map(c=>c.count||0),
                            backgroundColor: TEAL_COLORS, borderWidth:2, borderColor:'#fff', hoverOffset:4 }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: true, cutout:'55%',
                        plugins: { legend:{ position:'bottom', labels:{ font:{size:10}, padding:8, boxWidth:11 } } }
                    }
                });
            },

            initFacilityTypeChart() {
                const el = document.getElementById('facilityTypeChart');
                if (!el) return;
                const ftypes = this.chartData.facilityTypes || [];
                Dashboard.state.facilityTypeData = ftypes;
                if (!ftypes.length) return;
                Dashboard.state.charts.facility = new Chart(el, {
                    type: 'bar',
                    data: {
                        labels: ftypes.map(f => f.name||'Unknown'),
                        datasets: [
                            { label:'Active', data: ftypes.map(f=>f.facilities_with_training||0), backgroundColor:'#0097A7', borderRadius:4 },
                            { label:'Total',  data: ftypes.map(f=>f.total_facilities||0), backgroundColor:'rgba(0,151,167,0.18)', borderRadius:4 }
                        ]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: true,
                        plugins: { legend:{ position:'bottom', labels:{ font:{size:11}, padding:8, boxWidth:12 } } },
                        scales: { x:{ stacked:false, ticks:{ font:{size:10} } }, y:{ beginAtZero:true, grid:{ color:'rgba(0,0,0,.04)' } } }
                    }
                });
            },

            initTopCountiesChart() {
                const el = document.getElementById('topCountiesChart');
                if (!el) return;
                const counties = (this.extData.topCounties || []).slice(0,10);
                if (!counties.length) return;
                Dashboard.state.charts.counties = new Chart(el, {
                    type: 'bar',
                    data: {
                        labels: counties.map(c => c.name),
                        datasets: [{ label: Dashboard.state.currentMode==='training'?'Participants':'Mentees',
                            data: counties.map(c=>c.count),
                            backgroundColor: counties.map((_,i) => TEAL_COLORS[i % TEAL_COLORS.length]),
                            borderRadius: 5, borderSkipped: false }]
                    },
                    options: {
                        indexAxis: 'y', responsive: true, maintainAspectRatio: true,
                        plugins: { legend:{ display:false } },
                        scales: { x:{ beginAtZero:true, grid:{ color:'rgba(0,0,0,.04)' } }, y:{ ticks:{ font:{size:11} } } }
                    }
                });
            },

            initMenteeStatusChart() {
                const el = document.getElementById('menteeStatusChart');
                if (!el) return;
                const ms = this.extData.menteeStatus || {};
                const labels = Object.keys(ms).map(k => k.charAt(0).toUpperCase()+k.slice(1));
                const data   = Object.values(ms);
                if (!data.length) return;
                const colors = { completed:'#10B981', enrolled:'#0097A7', dropped:'#EF4444', active:'#2563EB', default:'#9CA3AF' };
                Dashboard.state.charts.menteeStatus = new Chart(el, {
                    type:'doughnut',
                    data:{ labels, datasets:[{ data, backgroundColor: Object.keys(ms).map(k=>colors[k]||colors.default), borderWidth:2, borderColor:'#fff', hoverOffset:4 }] },
                    options:{ responsive:true, maintainAspectRatio:true, cutout:'60%',
                        plugins:{ legend:{ position:'bottom', labels:{ font:{size:11}, padding:8, boxWidth:11 } } } }
                });
            },

            initTopProgramsChart() {
                const el = document.getElementById('topProgramsChart');
                if (!el) return;
                const progs = (this.extData.topPrograms || []).slice(0,5);
                if (!progs.length) return;
                Dashboard.state.charts.topProgs = new Chart(el, {
                    type: 'bar',
                    data: {
                        labels: progs.map(p => { const t = p.title||'(Untitled)'; return t.length>30 ? t.substring(0,30)+'…' : t; }),
                        datasets: [{ label:'Mentees', data: progs.map(p=>p.count),
                            backgroundColor: TEAL_COLORS.slice(0,5), borderRadius:5 }]
                    },
                    options: {
                        indexAxis:'y', responsive:true, maintainAspectRatio:true,
                        plugins:{ legend:{ display:false } },
                        scales:{ x:{ beginAtZero:true }, y:{ ticks:{ font:{size:10} } } }
                    }
                });
            },

            initClassStatusChart() {
                const el = document.getElementById('classStatusChart');
                if (!el) return;
                const sb = this.extData.classStatusBreakdown || {};
                const labels = Object.keys(sb).map(k => k.charAt(0).toUpperCase()+k.slice(1));
                const data   = Object.values(sb);
                if (!data.length) return;
                const statusColors = { draft:'#F59E0B', active:'#10B981', completed:'#2563EB', cancelled:'#EF4444', unknown:'#9CA3AF' };
                const colors = Object.keys(sb).map(k => statusColors[k] || '#9CA3AF');
                Dashboard.state.charts.classStatus = new Chart(el, {
                    type:'doughnut',
                    data:{ labels, datasets:[{ data, backgroundColor: colors, borderWidth:2, borderColor:'#fff', hoverOffset:4 }] },
                    options:{ responsive:true, maintainAspectRatio:true, cutout:'60%',
                        plugins:{ legend:{ position:'bottom', labels:{ font:{size:11}, padding:8, boxWidth:11 } } } }
                });
            },

            initCompletionDistributionChart() {
                const el = document.getElementById('completionDistributionChart');
                if (!el) return;
                const dist = this.extData.completionDistribution || {};
                const bucketOrder = ['0-25%', '26-50%', '51-75%', '76-99%', '100%'];
                const labels = bucketOrder.filter(k => dist[k] !== undefined);
                const data = labels.map(k => dist[k] || 0);
                if (!data.length || data.every(v => v === 0)) return;
                const bucketColors = ['#EF4444','#F97316','#F59E0B','#3B82F6','#10B981'];
                Dashboard.state.charts.completionDist = new Chart(el, {
                    type:'bar',
                    data:{ labels, datasets:[{ label:'Mentees', data, backgroundColor: bucketColors, borderRadius:6, borderSkipped:false }] },
                    options:{ responsive:true, maintainAspectRatio:true,
                        plugins:{ legend:{ display:false }, tooltip:{ callbacks:{ label: c => `${c.raw} mentee${c.raw===1?'':'s'}` } } },
                        scales:{ x:{ grid:{ display:false }, ticks:{ font:{size:11} } }, y:{ beginAtZero:true, grid:{ color:'rgba(0,0,0,.04)' }, ticks:{ font:{size:11} } } } }
                });
            },

            async refreshAllCharts(trainingId = null) {
                try {
                    const p = new URLSearchParams({ mode: Dashboard.state.currentMode });
                    if (Dashboard.state.currentYear) p.set('year', Dashboard.state.currentYear);
                    if (trainingId) p.set('training_id', trainingId);
                    const res = await fetch(`/analytics/dashboard/training-data?${p}`);
                    if (!res.ok) return;
                    const json = await res.json();
                    if (!json.success) return;
                    const cd = json.chartData;
                    const ss = json.summaryStats;

                    // Update KPI data-counter values and re-animate counters
                    const setCounter = (id, value) => {
                        const el = document.getElementById(id);
                        if (el) {
                            el.dataset.counter = value;
                            el.dataset.counterAnimated = 'false';
                        }
                    };
                    setCounter('kpi-programs',     ss.totalPrograms||0);
                    setCounter('kpi-participants', ss.totalParticipants||0);
                    setCounter('kpi-facilities',   ss.totalFacilities||0);
                    setCounter('kpi-coverage',     ss.facilityCoverage||0);
                    if (Dashboard.state.currentMode === 'mentorship' && ss.attendanceRate !== undefined) {
                        setCounter('kpi-attendance', ss.attendanceRate||0);
                    }

                    // Legacy overlay IDs
                    const upd = (id, v) => { const el=document.getElementById(id); if(el) el.textContent=v; };
                    upd('totalPrograms',     (ss.totalPrograms||0).toLocaleString());
                    upd('totalParticipants', (ss.totalParticipants||0).toLocaleString());
                    upd('totalFacilities',   (ss.totalFacilities||0).toLocaleString());
                    upd('facilityCoverage',  (ss.facilityCoverage||0)+'%');

                    // Re-animate counters with updated values
                    try { Dashboard.Counters.refresh(); } catch(e) { console.error('Counter refresh failed:', e); }
                    // Legacy overlay IDs
                    upd('totalPrograms',     (ss.totalPrograms||0).toLocaleString());
                    upd('totalParticipants', (ss.totalParticipants||0).toLocaleString());
                    upd('totalFacilities',   (ss.totalFacilities||0).toLocaleString());
                    upd('facilityCoverage',  (ss.facilityCoverage||0)+'%');

                    // Update charts if they exist
                    const updateChart = (chartKey, newLabels, newData, datasetIdx=0) => {
                        const ch = Dashboard.state.charts[chartKey];
                        if (!ch) return;
                        ch.data.labels = newLabels;
                        ch.data.datasets[datasetIdx].data = newData;
                        ch.update();
                    };
                    if (cd.departments?.length) updateChart('dept', cd.departments.map(d=>d.name), cd.departments.map(d=>d.count||0));
                    if (cd.cadres?.length)      updateChart('cadre', cd.cadres.map(c=>c.name), cd.cadres.map(c=>c.count||0));
                    if (cd.facilityTypes?.length) {
                        const ch = Dashboard.state.charts.facility;
                        if (ch) {
                            ch.data.labels = cd.facilityTypes.map(f=>f.name);
                            ch.data.datasets[0].data = cd.facilityTypes.map(f=>f.facilities_with_training||0);
                            ch.data.datasets[1].data = cd.facilityTypes.map(f=>f.total_facilities||0);
                            ch.update();
                        }
                    }

                    // Mentorship-specific chart updates
                    if (Dashboard.state.currentMode === 'mentorship') {
                        if (cd.classStatusBreakdown && Dashboard.state.charts.classStatus) {
                            const ch = Dashboard.state.charts.classStatus;
                            ch.data.labels = Object.keys(cd.classStatusBreakdown).map(k => k.charAt(0).toUpperCase()+k.slice(1));
                            ch.data.datasets[0].data = Object.values(cd.classStatusBreakdown);
                            ch.update();
                        }
                        if (cd.completionDistribution && Dashboard.state.charts.completionDist) {
                            const ch = Dashboard.state.charts.completionDist;
                            const bucketOrder = ['0-25%', '26-50%', '51-75%', '76-99%', '100%'];
                            ch.data.labels = bucketOrder.filter(k => cd.completionDistribution[k] !== undefined);
                            ch.data.datasets[0].data = ch.data.labels.map(k => cd.completionDistribution[k] || 0);
                            ch.update();
                        }
                    }
                } catch(e) { console.error('Chart refresh error:', e); }
            }
        },

        // ── Training list UI ──
        TrainingList: {
            init() {
                const list = document.getElementById('trainingsList');
                if (!list) return;
                Dashboard.state.originalTrainingsList = [...list.querySelectorAll('.training-card')];
                list.querySelectorAll('.training-card').forEach(card => {
                    card.addEventListener('click', () => this.selectTraining(card));
                });
                const search = document.getElementById('search-filter');
                if (search) {
                    search.addEventListener('input', e => this.filterList(e.target.value));
                }
            },
            selectTraining(card) {
                const id = card.dataset.trainingId;
                document.querySelectorAll('.training-card').forEach(c => c.classList.remove('selected'));
                if (Dashboard.state.selectedTraining === id) {
                    Dashboard.state.selectedTraining = null;
                    Dashboard.Map.loadCountyData(null);
                    Dashboard.Charts.refreshAllCharts(null);
                } else {
                    card.classList.add('selected');
                    Dashboard.state.selectedTraining = id;
                    Dashboard.Map.loadCountyData(id);
                    Dashboard.Charts.refreshAllCharts(id);
                }
            },
            filterList(query) {
                const q = query.toLowerCase();
                const cards = document.querySelectorAll('.training-card');
                cards.forEach(card => {
                    const title = card.querySelector('.training-title')?.textContent?.toLowerCase() || '';
                    card.style.display = (!q || title.includes(q)) ? '' : 'none';
                });
                const visible = [...cards].filter(c => c.style.display !== 'none').length;
                const cnt = document.getElementById('programCount');
                if (cnt) cnt.textContent = visible;
            }
        },

        // ── Animated Counters ──
        Counters: {
            observers: [],

            formatNumber(value, decimals = 0) {
                const num = parseFloat(value) || 0;
                if (decimals > 0) {
                    return num.toLocaleString(undefined, { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
                }
                return Math.round(num).toLocaleString();
            },

            animate(el) {
                if (el.dataset.counterAnimated === 'true') return;
                el.dataset.counterAnimated = 'true';

                const target = parseFloat(el.dataset.counter) || 0;
                const suffix = el.dataset.suffix || '';
                const decimals = parseInt(el.dataset.decimals || '0', 10);
                const duration = parseInt(el.dataset.counterDuration || '1500', 10);
                const start = performance.now();

                const step = (now) => {
                    const elapsed = now - start;
                    const progress = Math.min(elapsed / duration, 1);
                    // easeOutCubic
                    const eased = 1 - Math.pow(1 - progress, 3);
                    const current = target * eased;
                    el.textContent = this.formatNumber(current, decimals) + suffix;
                    if (progress < 1) {
                        requestAnimationFrame(step);
                    } else {
                        el.textContent = this.formatNumber(target, decimals) + suffix;
                    }
                };

                requestAnimationFrame(step);
            },

            init() {
                const counters = document.querySelectorAll('.counter-animate');
                if (!counters.length) return;

                // Use IntersectionObserver to trigger counters when scrolled into view
                if ('IntersectionObserver' in window) {
                    const observer = new IntersectionObserver((entries) => {
                        entries.forEach(entry => {
                            if (entry.isIntersecting) {
                                this.animate(entry.target);
                                observer.unobserve(entry.target);
                            }
                        });
                    }, { threshold: 0.5 });

                    counters.forEach(counter => observer.observe(counter));
                    this.observers.push(observer);
                } else {
                    // Fallback for older browsers
                    counters.forEach(counter => this.animate(counter));
                }
            },

            // Re-trigger counters after AJAX refresh (new values)
            refresh() {
                document.querySelectorAll('.counter-animate').forEach(el => {
                    el.dataset.counterAnimated = 'false';
                    this.animate(el);
                });
            }
        },

        // ── Filters ──
        Filters: {
            init() {
                // Mode toggle
                document.querySelectorAll('.mode-btn').forEach(btn => {
                    btn.addEventListener('click', () => {
                        const url = new URL(window.location);
                        url.searchParams.set('mode', btn.dataset.mode);
                        if (Dashboard.state.currentYear) url.searchParams.set('year', Dashboard.state.currentYear);
                        window.location.href = url.toString();
                    });
                });
                // Year filter
                const yearSel = document.getElementById('year-filter');
                if (yearSel) {
                    yearSel.addEventListener('change', function() {
                        const url = new URL(window.location);
                        this.value ? url.searchParams.set('year', this.value) : url.searchParams.delete('year');
                        url.searchParams.set('mode', Dashboard.state.currentMode);
                        window.location.href = url.toString();
                    });
                }
                // Program filter dropdown
                const progSel = document.getElementById('program-filter');
                if (progSel) {
                    progSel.addEventListener('change', function() {
                        const id = this.value;
                        if (id) {
                            Dashboard.state.selectedTraining = id;
                            Dashboard.Map.loadCountyData(id);
                            Dashboard.Charts.refreshAllCharts(id);
                            document.querySelectorAll('.training-card').forEach(c => {
                                c.classList.toggle('selected', c.dataset.trainingId === id);
                            });
                        } else {
                            Dashboard.state.selectedTraining = null;
                            Dashboard.Map.loadCountyData(null);
                            Dashboard.Charts.refreshAllCharts(null);
                            document.querySelectorAll('.training-card').forEach(c => c.classList.remove('selected'));
                        }
                    });
                }
                // Clear filters
                const clearBtn = document.getElementById('clear-filters');
                if (clearBtn) {
                    clearBtn.addEventListener('click', () => {
                        const url = new URL(window.location);
                        url.searchParams.set('mode', Dashboard.state.currentMode);
                        url.searchParams.delete('year');
                        url.searchParams.delete('training_id');
                        window.location.href = url.toString();
                    });
                }
            }
        },

        // ── Guided Tour ──
        Tour: {
            init() {
                const btn = document.getElementById('startTourBtn');
                if (!btn) return;
                btn.addEventListener('click', () => {
                    introJs().setOptions({
                        steps: [
                            { title:'👋 Welcome!', intro:'Welcome to the MNCH Analytics Dashboard — let\'s take a quick tour of the key features.' },
                            { element: document.querySelector('.mode-toggle'), title:'Mode Toggle', intro:'Switch between Training Programs and Mentorship analytics.' },
                            { element: document.getElementById('filtersCollapse'), title:'Filters', intro:'Filter by year, program, or search to narrow your analysis.' },
                            { element: document.querySelector('.kpi-strip'), title:'KPI Cards', intro:'Key metrics: programs, participants, facilities covered, and year-on-year trend.' },
                            { element: document.getElementById('trendChart')?.closest('.chart-card'), title:'Trend Chart', intro:'12-month enrollment or activity trend.' },
                            { element: document.getElementById('departmentChart')?.closest('.chart-card'), title:'Department Chart', intro:'Participation breakdown by department.' },
                            { element: document.getElementById('topCountiesChart')?.closest('.chart-card'), title:'Top Counties', intro:'Leading counties by participation volume.' },
                            { element: document.getElementById('kenyaMap'), title:'Kenya Map', intro:'Interactive map — click any county to drill down into facilities and participants.' },
                        ].filter(s => !s.element || document.body.contains(s.element)),
                        nextLabel: 'Next →', prevLabel: '← Back', doneLabel: 'Done',
                        showProgress: true, scrollToElement: true, disableInteraction: false
                    }).start();
                });
            }
        },

        // ── Bootstrap ──
        init() {
            // Initialize scroll animations
            try {
                if (typeof AOS !== 'undefined') {
                    AOS.init({
                        duration: 600,
                        easing: 'ease-out-cubic',
                        once: true,
                        offset: 60,
                        disable: window.matchMedia('(prefers-reduced-motion: reduce)').matches
                    });
                }
            } catch(e) { console.error('AOS init failed:', e); }

            // Each module is isolated — a failure in one doesn't block the others
            try { Dashboard.Counters.init(); } catch(e) { console.error('Counters init failed:', e); }
            try { Dashboard.Filters.init(); } catch(e) { console.error('Filters init failed:', e); }
            try { Dashboard.Tour.init();    } catch(e) { console.error('Tour init failed:', e); }
            try { Dashboard.Charts.init(); } catch(e) { console.error('Charts init failed:', e); }
            try { Dashboard.TrainingList.init(); } catch(e) { console.error('TrainingList init failed:', e); }
            try { Dashboard.Map.init();    } catch(e) { console.error('Map init failed:', e); }
        }
    };

    Dashboard.init();
})();
@endsection

@section('scripts')
{{-- No additional scripts needed — all inline above --}}
@endsection
