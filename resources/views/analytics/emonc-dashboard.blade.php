@extends('layouts.dashboard')

@section('title', 'EmONC Maternal Health Dashboard — MNCH Analytics')

@section('additional-styles')
<style>
/* ── EmONC colour overrides (rose/crimson accent on teal base) ── */
:root {
    --emonc:       #E11D48;
    --emonc-dark:  #9F1239;
    --emonc-light: #FB7185;
    --emonc-50:    #FFF1F2;
}

/* Hero */
.dash-hero {
    background: linear-gradient(135deg, #1a0a2e 0%, #0c1445 30%, #1245A8 65%, #1A54C8 100%) !important;
    padding: 1.5rem 2rem 2.5rem;
    position: relative;
    overflow: hidden;
}
.dash-hero::before {
    content: '';
    position: absolute; inset: 0;
    background: url("data:image/svg+xml,%3Csvg width='100' height='30' viewBox='0 0 100 30' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M0 15 L10 15 L14 5 L20 25 L26 10 L32 20 L38 8 L44 22 L50 15 L100 15' stroke='rgba(255,255,255,0.06)' stroke-width='1.5' fill='none'/%3E%3C/svg%3E") repeat-x center;
    opacity: 0.7;
}

/* KPI card accent colours for EmONC */
.kpi-card:nth-child(1) { --kpi-accent: #1A54C8;  border-top-color: #1A54C8; }
.kpi-card:nth-child(2) { --kpi-accent: var(--teal);   border-top-color: var(--teal); }
.kpi-card:nth-child(3) { --kpi-accent: var(--emerald);border-top-color: var(--emerald); }
.kpi-card:nth-child(4) { --kpi-accent: var(--amber);  border-top-color: var(--amber); }
.kpi-card:nth-child(5) { --kpi-accent: var(--violet); border-top-color: var(--violet); }
.kpi-card:nth-child(6) { --kpi-accent: var(--emonc);  border-top-color: var(--emonc); }
.kpi-card:nth-child(7) { --kpi-accent: #F97316;       border-top-color: #F97316; }
.kpi-card:nth-child(8) { --kpi-accent: var(--blue);   border-top-color: var(--blue); }
.kpi-card:nth-child(9) { --kpi-accent: var(--emerald);border-top-color: var(--emerald); }

/* Pending action chips */
.action-chip {
    display: inline-flex; align-items: center; gap: .5rem;
    padding: .45rem 1rem; border-radius: 999px;
    font-size: .82rem; font-weight: 700;
    border: 1.5px solid currentColor;
}
.action-chip .chip-badge {
    display: inline-flex; align-items: center; justify-content: center;
    width: 22px; height: 22px; border-radius: 50%;
    font-size: .72rem; font-weight: 800;
    background: currentColor; color: #fff;
}
.action-chip.amber  { color: #92400E; background: #FEF3C7; border-color: #F59E0B; }
.action-chip.amber  .chip-badge { background: #F59E0B; }
.action-chip.blue   { color: #1E40AF; background: #DBEAFE; border-color: #3B82F6; }
.action-chip.blue   .chip-badge { background: #3B82F6; }
.action-chip.violet { color: #5B21B6; background: #EDE9FE; border-color: #8B5CF6; }
.action-chip.violet .chip-badge { background: #8B5CF6; }

/* Status badges */
.badge-cert     { background:#D1FAE5; color:#065F46; }
.badge-blocked  { background:#FEE2E2; color:#991B1B; }
.badge-prog     { background:#FEF3C7; color:#92400E; }
.badge-none     { background:#F1F5F9; color:#475569; }
.badge-sm { display:inline-flex;align-items:center;padding:.2rem .55rem;border-radius:12px;font-size:.72rem;font-weight:700; }

/* Matrix table */
.matrix-table { width:100%; border-collapse:collapse; font-size:.84rem; }
.matrix-table thead th { background:var(--gray-50); padding:.7rem 1rem; font-size:.74rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:var(--gray-500); text-align:left; border-bottom:1px solid var(--gray-200); white-space:nowrap; }
.matrix-table tbody tr { border-bottom:1px solid var(--gray-100); transition:background .15s; }
.matrix-table tbody tr:hover { background:var(--teal-50); }
.matrix-table tbody td { padding:.65rem 1rem; vertical-align:middle; }

/* Activity chips */
.act-chip { display:inline-flex;align-items:center;gap:.2rem;padding:.15rem .45rem;border-radius:6px;font-size:.68rem;font-weight:700;border:1px solid transparent; }
.act-done { background:#D1FAE5;color:#065F46;border-color:#A7F3D0; }
.act-pending { background:#FEF3C7;color:#92400E;border-color:#FDE68A; }
.act-none { background:#F1F5F9;color:#64748B;border-color:#E2E8F0; }

/* Filter input */
.matrix-filter { padding:.45rem .85rem; border:1px solid var(--gray-200); border-radius:8px; font-size:.85rem; color:var(--gray-700); background:#fff; width:260px; outline:none; transition:border-color .15s,box-shadow .15s; }
.matrix-filter:focus { border-color:var(--teal); box-shadow:0 0 0 3px rgba(0,151,167,.12); }

/* Back link */
.back-link { display:inline-flex;align-items:center;gap:.35rem;color:rgba(255,255,255,.75);font-size:.8rem;font-weight:600;text-decoration:none;transition:color .15s; }
.back-link:hover { color:#fff; }
.back-link svg { width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2.5;stroke-linecap:round;stroke-linejoin:round; }

/* Donut legend */
.donut-legend { display:flex;flex-direction:column;gap:.5rem;margin-top:1rem; }
.donut-legend-item { display:flex;align-items:center;gap:.5rem;font-size:.82rem;color:var(--gray-700); }
.donut-swatch { width:12px;height:12px;border-radius:3px;flex-shrink:0; }
</style>
@endsection

@section('content')

<div x-data="{ search: '' }">

{{-- ── Hero ─────────────────────────────────────────────────────────────── --}}
<div class="dash-hero">
    <div class="dash-hero-inner">
        <div class="dash-hero-left">
            <a href="/admin" class="back-link mb-2 d-inline-flex">
                <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
                Back to Admin
            </a>
            <div class="hero-badge mt-2">
                <i class="fas fa-heartbeat fa-xs"></i>
                Maternal, Newborn &amp; Child Health
            </div>
            <h1 class="mt-1">EmONC Mentorship Dashboard</h1>
            <p>Real-time overview of hands-on clinical mentorship — modules, activities, video assessments &amp; certification pipeline.</p>
        </div>
        <div class="dash-hero-right">
            <a href="/admin/head-drmh-dashboard" class="btn-hero">
                <i class="fas fa-certificate me-1"></i> Head DRMH Portal
            </a>
            <a href="/admin" class="btn-hero btn-hero-white">
                <i class="fas fa-cog me-1"></i> Admin Panel
            </a>
        </div>
    </div>
</div>

{{-- ── KPI strip ────────────────────────────────────────────────────────── --}}
<div class="kpi-strip-wrap">
    <div class="kpi-strip">
        <div class="kpi-card">
            <div class="kpi-icon"><i class="fas fa-users"></i></div>
            <div class="kpi-value">{{ $kpis['active_mentees'] }}</div>
            <div class="kpi-label">Active Mentees</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon"><i class="fas fa-chalkboard-teacher"></i></div>
            <div class="kpi-value">{{ $kpis['active_classes'] }}</div>
            <div class="kpi-label">Active Classes</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon"><i class="fas fa-check-double"></i></div>
            <div class="kpi-value">{{ $kpis['completed_classes'] }}</div>
            <div class="kpi-label">Completed Classes</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon"><i class="fas fa-book-open"></i></div>
            <div class="kpi-value">{{ $kpis['completed_modules'] }}</div>
            <div class="kpi-label">Modules Done</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon"><i class="fas fa-video"></i></div>
            <div class="kpi-value">{{ $kpis['pending_video_reviews'] }}</div>
            <div class="kpi-label">Pending Video Reviews</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon"><i class="fas fa-user-check"></i></div>
            <div class="kpi-value">{{ $kpis['pending_mentor_approvals'] }}</div>
            <div class="kpi-label">Awaiting Mentor Approval</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon"><i class="fas fa-shield-alt"></i></div>
            <div class="kpi-value">{{ $kpis['pending_drmh_approvals'] }}</div>
            <div class="kpi-label">Pending DRMH Certs</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon"><i class="fas fa-certificate"></i></div>
            <div class="kpi-value">{{ $kpis['certificates_issued'] }}</div>
            <div class="kpi-label">Certificates Issued</div>
        </div>
    </div>
</div>

{{-- ── Pending actions + chart row ─────────────────────────────────────── --}}
<div class="dash-section">
    <div class="chart-row" style="gap:1.25rem; flex-wrap:wrap;">

        {{-- Pending actions panel --}}
        <div class="chart-card" style="flex:1; min-width:280px;">
            <div class="chart-card-header">
                <h6><i class="fas fa-tasks"></i> Action Queue</h6>
                <small>Items requiring attention</small>
            </div>
            <div class="chart-card-body">
                <div class="d-flex flex-column gap-3">
                    @foreach($pendingActions as $action)
                    <a href="{{ $action['url'] }}" class="text-decoration-none d-flex align-items-center justify-content-between p-3 rounded-3"
                       style="background:var(--gray-50); border:1px solid var(--gray-200); transition:all .2s;"
                       onmouseenter="this.style.background='var(--teal-50)'; this.style.borderColor='var(--teal)'"
                       onmouseleave="this.style.background='var(--gray-50)'; this.style.borderColor='var(--gray-200)'">
                        <div class="d-flex align-items-center gap-2">
                            @if($action['color'] === 'amber')
                                <div style="width:8px;height:8px;border-radius:50%;background:var(--amber);flex-shrink:0;"></div>
                            @elseif($action['color'] === 'blue')
                                <div style="width:8px;height:8px;border-radius:50%;background:var(--blue);flex-shrink:0;"></div>
                            @else
                                <div style="width:8px;height:8px;border-radius:50%;background:var(--violet);flex-shrink:0;"></div>
                            @endif
                            <span style="font-size:.85rem; font-weight:600; color:var(--gray-700);">{{ $action['label'] }}</span>
                        </div>
                        <span style="font-size:1.1rem; font-weight:800; color:var(--gray-800);">{{ $action['count'] }}</span>
                    </a>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Completion distribution donut --}}
        <div class="chart-card" style="flex:1; min-width:260px;">
            <div class="chart-card-header">
                <h6><i class="fas fa-chart-pie"></i> Module Completion</h6>
                <small>Distribution across all mentees</small>
            </div>
            <div class="chart-card-body" style="display:flex; align-items:center; gap:1.5rem; flex-wrap:wrap;">
                <div style="flex:1; min-width:160px; max-width:200px; margin:0 auto;">
                    <canvas id="completionDonut" height="180"></canvas>
                </div>
                <div class="donut-legend">
                    @php $donutColors = ['#22c55e','#f59e0b','#94a3b8']; @endphp
                    @foreach($chartData['completion_distribution'] as $i => $item)
                    <div class="donut-legend-item">
                        <div class="donut-swatch" style="background:{{ $donutColors[$i % 3] }};"></div>
                        <span>{{ $item['label'] }}</span>
                        <strong style="margin-left:auto; padding-left:.5rem;">{{ $item['value'] }}</strong>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Certification pipeline --}}
        <div class="chart-card" style="flex:1; min-width:260px;">
            <div class="chart-card-header">
                <h6><i class="fas fa-award"></i> Certification Pipeline</h6>
                <small>Approval stages at a glance</small>
            </div>
            <div class="chart-card-body">
                @php
                    $total = $kpis['active_mentees'] ?: 1;
                    $stages = [
                        ['label' => 'Mentor Approved',  'val' => $kpis['active_mentees'] - $kpis['pending_mentor_approvals'], 'color' => '#1A54C8'],
                        ['label' => 'DRMH Pending',     'val' => $kpis['pending_drmh_approvals'],    'color' => '#8B5CF6'],
                        ['label' => 'Certified',        'val' => $kpis['certificates_issued'],       'color' => '#22c55e'],
                    ];
                @endphp
                @foreach($stages as $stage)
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span style="font-size:.78rem; font-weight:600; color:var(--gray-700);">{{ $stage['label'] }}</span>
                        <span style="font-size:.78rem; font-weight:700; color:var(--gray-800);">{{ max(0, $stage['val']) }}</span>
                    </div>
                    <div style="height:6px; background:var(--gray-100); border-radius:4px; overflow:hidden;">
                        <div style="height:100%; width:{{ min(100, round(max(0,$stage['val'])/$total*100)) }}%; background:{{ $stage['color'] }}; border-radius:4px; transition:width .6s;"></div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>

    </div>
</div>

{{-- ── Completion matrix ─────────────────────────────────────────────────── --}}
<div class="dash-section">
    <div class="chart-card">
        <div class="chart-card-header">
            <h6><i class="fas fa-table"></i> Per-Mentee Completion Matrix</h6>
            <div class="d-flex align-items-center gap-2">
                <small class="text-muted">{{ count($completionMatrix) }} records</small>
                <input type="text"
                       x-model="search"
                       placeholder="Filter by mentee, module or class…"
                       class="matrix-filter">
            </div>
        </div>
        <div class="chart-card-body" style="padding:0; overflow-x:auto;">
            @if(count($completionMatrix) === 0)
            <div style="padding:3rem; text-align:center; color:var(--gray-500);">
                <i class="fas fa-heartbeat fa-2x mb-3" style="color:var(--gray-200);"></i>
                <p style="font-size:.9rem;">No EmONC mentorship data found. Programmes must be tagged with <em>Maternal EmONC</em> in the program name.</p>
            </div>
            @else
            <table class="matrix-table">
                <thead>
                    <tr>
                        <th>Mentee</th>
                        <th>Facility</th>
                        <th>Class</th>
                        <th>Module / Track</th>
                        <th>Activities</th>
                        <th>Video</th>
                        <th>Status</th>
                        <th>Certificate</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($completionMatrix as $row)
                    @php
                        $searchStr = strtolower($row['mentee_name'] . ' ' . $row['module_name'] . ' ' . ($row['parent_module_name'] ?? '') . ' ' . $row['class_name'] . ' ' . $row['facility_name']);
                    @endphp
                    <tr x-show="search === '' || '{{ $searchStr }}'.includes(search.toLowerCase())">
                        <td>
                            <div style="font-weight:600; color:var(--gray-800); font-size:.84rem;">{{ $row['mentee_name'] }}</div>
                            <div style="font-size:.72rem; color:var(--gray-500);">{{ $row['county_name'] }}</div>
                        </td>
                        <td style="font-size:.82rem; color:var(--gray-600);">{{ $row['facility_name'] }}</td>
                        <td style="font-size:.82rem; color:var(--gray-700);">{{ $row['class_name'] }}</td>
                        <td>
                            <div style="font-size:.83rem; font-weight:600; color:var(--gray-800);">{{ $row['module_name'] }}</div>
                            @if($row['parent_module_name'])
                            <div style="font-size:.7rem; color:var(--teal); font-weight:600;">{{ $row['parent_module_name'] }}</div>
                            @endif
                        </td>
                        <td>
                            <div style="display:flex; flex-wrap:wrap; gap:3px;">
                                @foreach($row['activities'] as $act)
                                <span class="act-chip {{ $act['status'] === 'completed' ? 'act-done' : ($act['status'] === 'not_enrolled' ? 'act-none' : 'act-pending') }}">
                                    {{ $act['name'] }}
                                    @if($act['status'] === 'completed') ✓ @elseif($act['status'] === 'not_enrolled') — @else ○ @endif
                                </span>
                                @endforeach
                                @if(empty($row['activities']))
                                <span style="font-size:.74rem; color:var(--gray-400);">None defined</span>
                                @endif
                            </div>
                        </td>
                        <td>
                            @php
                                $vr = $row['video_review'];
                                $vClass = match($vr) { 'passed' => 'badge-cert', 'failed' => 'badge-blocked', 'pending' => 'badge-prog', default => 'badge-none' };
                                $vLabel = match($vr) { 'passed' => 'Passed', 'failed' => 'Failed', 'pending' => 'Pending', 'not_submitted' => 'Not Submitted', default => ucfirst($vr) };
                            @endphp
                            <span class="badge-sm {{ $vClass }}">{{ $vLabel }}</span>
                        </td>
                        <td>
                            <div class="d-flex flex-column gap-1">
                                <span class="badge-sm {{ $row['mentor_approved'] ? 'badge-cert' : 'badge-none' }}" style="width:fit-content;">
                                    {{ $row['mentor_approved'] ? '✓ Mentor' : '○ Mentor' }}
                                </span>
                                <span class="badge-sm {{ $row['head_drmh_approved'] ? 'badge-cert' : 'badge-none' }}" style="width:fit-content;">
                                    {{ $row['head_drmh_approved'] ? '✓ DRMH' : '○ DRMH' }}
                                </span>
                            </div>
                        </td>
                        <td>
                            @if($row['certificate_ready'])
                                <span class="badge-sm badge-cert">
                                    <i class="fas fa-award me-1" style="font-size:.65rem;"></i> Certified
                                </span>
                            @else
                                <span class="badge-sm badge-blocked"
                                      title="{{ implode(', ', $row['blocked_reasons']) }}"
                                      style="cursor:help;">
                                    <i class="fas fa-lock me-1" style="font-size:.65rem;"></i>
                                    Blocked
                                    @if(count($row['blocked_reasons']))
                                        ({{ count($row['blocked_reasons']) }})
                                    @endif
                                </span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif
        </div>
    </div>
</div>

</div>{{-- end x-data --}}

@endsection

@section('additional-scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const ctx = document.getElementById('completionDonut');
    if (!ctx) return;
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: {!! json_encode(array_column($chartData['completion_distribution'], 'label')) !!},
            datasets: [{
                data: {!! json_encode(array_column($chartData['completion_distribution'], 'value')) !!},
                backgroundColor: ['#22c55e', '#f59e0b', '#94a3b8'],
                borderWidth: 0,
                hoverOffset: 4,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '72%',
            plugins: { legend: { display: false } }
        }
    });
});
</script>
@endsection
