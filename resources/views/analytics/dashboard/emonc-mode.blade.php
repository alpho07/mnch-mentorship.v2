{{-- EmONC mode partial — rendered inside analytics/dashboard/index.blade.php --}}
{{-- Variables: $emoncKpis, $emoncMatrix, $emoncChartData, $emoncPendingActions, $emoncExtendedStats, $emoncInsights --}}

<style>
/* ── EmONC accent colours on top of shared teal design system ── */
.emonc-kpi-strip .kpi-card:nth-child(1) { --kpi-accent:#1A54C8; border-top-color:#1A54C8; }
.emonc-kpi-strip .kpi-card:nth-child(1) .kpi-icon { background:rgba(26,84,200,.1); color:#1A54C8; }
.emonc-kpi-strip .kpi-card:nth-child(2) { --kpi-accent:var(--teal); border-top-color:var(--teal); }
.emonc-kpi-strip .kpi-card:nth-child(3) { --kpi-accent:var(--emerald); border-top-color:var(--emerald); }
.emonc-kpi-strip .kpi-card:nth-child(3) .kpi-icon { background:rgba(16,185,129,.1); color:var(--emerald); }
.emonc-kpi-strip .kpi-card:nth-child(4) { --kpi-accent:var(--violet); border-top-color:var(--violet); }
.emonc-kpi-strip .kpi-card:nth-child(4) .kpi-icon { background:rgba(139,92,246,.1); color:var(--violet); }
.emonc-kpi-strip .kpi-card:nth-child(5) { --kpi-accent:var(--amber); border-top-color:var(--amber); }
.emonc-kpi-strip .kpi-card:nth-child(5) .kpi-icon { background:rgba(245,158,11,.1); color:var(--amber); }
.emonc-kpi-strip .kpi-card:nth-child(6) { --kpi-accent:#E11D48; border-top-color:#E11D48; }
.emonc-kpi-strip .kpi-card:nth-child(6) .kpi-icon { background:rgba(225,29,72,.1); color:#E11D48; }
.emonc-kpi-strip .kpi-card:nth-child(7) { --kpi-accent:#F97316; border-top-color:#F97316; }
.emonc-kpi-strip .kpi-card:nth-child(7) .kpi-icon { background:rgba(249,115,22,.1); color:#F97316; }
.emonc-kpi-strip .kpi-card:nth-child(8) { --kpi-accent:var(--emerald); border-top-color:var(--emerald); }
.emonc-kpi-strip .kpi-card:nth-child(8) .kpi-icon { background:rgba(16,185,129,.1); color:var(--emerald); }

/* Matrix */
.emonc-matrix { width:100%; border-collapse:collapse; font-size:.84rem; }
.emonc-matrix thead th { background:linear-gradient(90deg,var(--teal-dark) 0%,var(--teal-mid) 100%); color:rgba(255,255,255,.9); font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em; padding:.7rem 1rem; text-align:left; white-space:nowrap; }
.emonc-matrix tbody tr { border-bottom:1px solid var(--gray-100); transition:background .15s; }
.emonc-matrix tbody tr:hover { background:var(--teal-50); }
.emonc-matrix tbody td { padding:.65rem 1rem; vertical-align:middle; }
.badge-sm { display:inline-flex;align-items:center;padding:.2rem .55rem;border-radius:12px;font-size:.72rem;font-weight:700; }
.badge-cert    { background:#D1FAE5;color:#065F46; }
.badge-blocked { background:#FEE2E2;color:#991B1B; }
.badge-prog    { background:#FEF3C7;color:#92400E; }
.badge-none    { background:#F1F5F9;color:#475569; }
.act-chip { display:inline-flex;align-items:center;gap:.2rem;padding:.15rem .45rem;border-radius:6px;font-size:.68rem;font-weight:700;border:1px solid transparent; }
.act-done    { background:#D1FAE5;color:#065F46;border-color:#A7F3D0; }
.act-pending { background:#FEF3C7;color:#92400E;border-color:#FDE68A; }
.act-none    { background:#F1F5F9;color:#64748B;border-color:#E2E8F0; }
.emonc-filter { padding:.45rem .85rem;border:1px solid var(--gray-200);border-radius:8px;font-size:.85rem;color:var(--gray-700);background:#fff;outline:none;transition:border-color .15s,box-shadow .15s;width:260px; }
.emonc-filter:focus { border-color:var(--teal);box-shadow:0 0 0 3px rgba(0,151,167,.12); }
.cpd-badge { display:inline-flex;align-items:center;gap:.2rem;padding:.2rem .5rem;border-radius:8px;font-size:.7rem;font-weight:800;border:1px solid transparent;white-space:nowrap; }
.emonc-pager { display:flex;align-items:center;gap:.5rem;font-size:.82rem;color:var(--gray-600); }
.emonc-pager button { padding:.3rem .7rem;border:1px solid var(--gray-200);border-radius:6px;background:#fff;cursor:pointer;font-size:.8rem;color:var(--gray-700);transition:all .15s; }
.emonc-pager button:hover:not(:disabled) { border-color:var(--teal);color:var(--teal); }
.emonc-pager button:disabled { opacity:.35;cursor:default; }
</style>

{{-- ── KPI STRIP ────────────────────────────────────────────────────────────── --}}
<div class="kpi-strip-wrap emonc-kpi-strip" data-aos="fade-up">
    <div class="kpi-strip">
        <div class="kpi-card">
            <div class="kpi-icon"><i class="fas fa-users"></i></div>
            <div class="kpi-value counter-animate" data-counter="{{ $emoncKpis['active_mentees'] }}">{{ $emoncKpis['active_mentees'] }}</div>
            <div class="kpi-label">Active Mentees</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon"><i class="fas fa-chalkboard-teacher"></i></div>
            <div class="kpi-value counter-animate" data-counter="{{ $emoncKpis['active_classes'] }}">{{ $emoncKpis['active_classes'] }}</div>
            <div class="kpi-label">Active Classes</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon"><i class="fas fa-check-double"></i></div>
            <div class="kpi-value counter-animate" data-counter="{{ $emoncKpis['completed_classes'] }}">{{ $emoncKpis['completed_classes'] }}</div>
            <div class="kpi-label">Completed Classes</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon"><i class="fas fa-book-open"></i></div>
            <div class="kpi-value counter-animate" data-counter="{{ $emoncKpis['completed_modules'] }}">{{ $emoncKpis['completed_modules'] }}</div>
            <div class="kpi-label">Modules Done</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon"><i class="fas fa-video"></i></div>
            <div class="kpi-value counter-animate" data-counter="{{ $emoncKpis['pending_video_reviews'] }}">{{ $emoncKpis['pending_video_reviews'] }}</div>
            <div class="kpi-label">Pending Video Reviews</div>
            @if($emoncKpis['pending_video_reviews'] > 0)
                <span class="kpi-trend down"><i class="fas fa-clock"></i> Action needed</span>
            @else
                <span class="kpi-trend up"><i class="fas fa-check"></i> Clear</span>
            @endif
        </div>
        <div class="kpi-card">
            <div class="kpi-icon"><i class="fas fa-user-check"></i></div>
            <div class="kpi-value counter-animate" data-counter="{{ $emoncKpis['pending_mentor_approvals'] }}">{{ $emoncKpis['pending_mentor_approvals'] }}</div>
            <div class="kpi-label">Awaiting Mentor Approval</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon"><i class="fas fa-shield-alt"></i></div>
            <div class="kpi-value counter-animate" data-counter="{{ $emoncKpis['pending_drmh_approvals'] }}">{{ $emoncKpis['pending_drmh_approvals'] }}</div>
            <div class="kpi-label">Pending DRMH Certs</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon"><i class="fas fa-certificate"></i></div>
            <div class="kpi-value counter-animate" data-counter="{{ $emoncKpis['certificates_issued'] }}">{{ $emoncKpis['certificates_issued'] }}</div>
            <div class="kpi-label">Certificates Issued</div>
            @if($emoncKpis['certificates_issued'] > 0)
                <span class="kpi-trend up"><i class="fas fa-award"></i> Certified</span>
            @endif
        </div>
    </div>
</div>

{{-- ── INSIGHTS ─────────────────────────────────────────────────────────────── --}}
@if(count($emoncInsights) > 0)
<div class="dash-section" data-aos="fade-up">
    <div class="section-title"><i class="fas fa-lightbulb"></i> Intelligent Insights</div>
    <div class="insights-grid">
        @foreach($emoncInsights as $idx => $insight)
        <div class="insight-card {{ $insight['type'] }}" data-aos="fade-up" data-aos-delay="{{ $idx * 80 }}">
            <div class="insight-icon"><i class="fas fa-{{ $insight['icon'] }}"></i></div>
            <div class="insight-text">{{ $insight['text'] }}</div>
        </div>
        @endforeach
    </div>
</div>
@endif

{{-- ── TRENDS & STATUS ──────────────────────────────────────────────────────── --}}
<div class="dash-section" data-aos="fade-up">
    <div class="section-title"><i class="fas fa-chart-area"></i> Trends &amp; Distribution</div>
    <div class="chart-row">
        {{-- 12-month trend --}}
        <div class="chart-card chart-2-3" data-aos="fade-right" data-aos-delay="100">
            <div class="chart-card-header">
                <h6><i class="fas fa-chart-line"></i> 12-Month EmONC Class Activity</h6>
                <small>Classes started per month over the last 12 months</small>
            </div>
            <div class="chart-card-body">
                <canvas id="emoncTrendChart" style="max-height:220px;"></canvas>
            </div>
        </div>
        {{-- Class lifecycle donut --}}
        <div class="chart-card chart-1-3" data-aos="fade-left" data-aos-delay="200">
            <div class="chart-card-header">
                <h6><i class="fas fa-chart-pie"></i> Class Lifecycle</h6>
                <small>Draft · Active · Completed</small>
            </div>
            <div class="chart-card-body d-flex flex-column align-items-center gap-3">
                <canvas id="emoncClassStatusChart" style="max-height:190px;max-width:220px;"></canvas>
                <div class="status-legend">
                    @foreach($emoncChartData['classStatusBreakdown'] as $st => $cnt)
                    <span class="status-chip chip-{{ in_array($st,['active','ongoing']) ? 'active' : (in_array($st,['completed']) ? 'completed' : (in_array($st,['draft']) ? 'draft' : 'cancelled')) }}">
                        {{ ucfirst($st) }}: {{ $cnt }}
                    </span>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ── ACTION QUEUE + CERTIFICATION PIPELINE ───────────────────────────────── --}}
<div class="dash-section" data-aos="fade-up">
    <div class="section-title"><i class="fas fa-tasks"></i> Action Queue &amp; Certification Pipeline</div>
    <div class="chart-row" style="flex-wrap:wrap;">
        {{-- Pending actions --}}
        <div class="chart-card" style="flex:1;min-width:260px;">
            <div class="chart-card-header">
                <h6><i class="fas fa-exclamation-circle"></i> Action Queue</h6>
                <small>Items requiring attention</small>
            </div>
            <div class="chart-card-body">
                <div class="d-flex flex-column gap-3">
                    @foreach($emoncPendingActions as $action)
                    <a href="{{ $action['url'] }}" class="text-decoration-none d-flex align-items-center justify-content-between p-3 rounded-3"
                       style="background:var(--gray-50);border:1px solid var(--gray-200);transition:all .2s;"
                       onmouseenter="this.style.background='var(--teal-50)';this.style.borderColor='var(--teal)'"
                       onmouseleave="this.style.background='var(--gray-50)';this.style.borderColor='var(--gray-200)'">
                        <div class="d-flex align-items-center gap-2">
                            @php
                                $dotColor = match($action['color'] ?? '') {
                                    'amber'  => 'var(--amber)',
                                    'blue'   => 'var(--blue)',
                                    'violet' => 'var(--violet)',
                                    default  => 'var(--teal)',
                                };
                            @endphp
                            <div style="width:8px;height:8px;border-radius:50%;background:{{ $dotColor }};flex-shrink:0;"></div>
                            <span style="font-size:.85rem;font-weight:600;color:var(--gray-700);">{{ $action['label'] }}</span>
                        </div>
                        <span style="font-size:1.1rem;font-weight:800;color:var(--gray-800);">{{ $action['count'] }}</span>
                    </a>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Module completion donut --}}
        <div class="chart-card" style="flex:1;min-width:240px;">
            <div class="chart-card-header">
                <h6><i class="fas fa-chart-pie"></i> Module Completion</h6>
                <small>Distribution across all mentees</small>
            </div>
            <div class="chart-card-body d-flex align-items-center gap-4" style="flex-wrap:wrap;">
                <div style="flex:1;min-width:150px;max-width:190px;margin:0 auto;">
                    <canvas id="emoncCompletionDonut" height="190"></canvas>
                </div>
                <div class="d-flex flex-column gap-2">
                    @php $donutColors = ['#22c55e','#f59e0b','#94a3b8']; @endphp
                    @foreach($emoncChartData['completionDistribution'] as $i => $item)
                    <div class="d-flex align-items-center gap-2" style="font-size:.82rem;color:var(--gray-700);">
                        <div style="width:12px;height:12px;border-radius:3px;background:{{ $donutColors[$i % 3] }};flex-shrink:0;"></div>
                        <span>{{ $item['label'] }}</span>
                        <strong style="margin-left:auto;padding-left:.5rem;">{{ $item['value'] }}</strong>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Certification pipeline bars --}}
        <div class="chart-card" style="flex:1;min-width:240px;">
            <div class="chart-card-header">
                <h6><i class="fas fa-award"></i> Certification Pipeline</h6>
                <small>Approval stages at a glance</small>
            </div>
            <div class="chart-card-body">
                @php
                    $total = max(1, $emoncKpis['active_mentees']);
                    $stages = [
                        ['label' => 'Mentor Approved',  'val' => max(0, $emoncKpis['active_mentees'] - $emoncKpis['pending_mentor_approvals']), 'color' => '#1A54C8'],
                        ['label' => 'DRMH Pending',     'val' => $emoncKpis['pending_drmh_approvals'],  'color' => '#8B5CF6'],
                        ['label' => 'Certified',        'val' => $emoncKpis['certificates_issued'],     'color' => '#22c55e'],
                    ];
                @endphp
                @foreach($stages as $stage)
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span style="font-size:.78rem;font-weight:600;color:var(--gray-700);">{{ $stage['label'] }}</span>
                        <span style="font-size:.78rem;font-weight:700;color:var(--gray-800);">{{ $stage['val'] }}</span>
                    </div>
                    <div style="height:6px;background:var(--gray-100);border-radius:4px;overflow:hidden;">
                        <div style="height:100%;width:{{ min(100, round($stage['val']/$total*100)) }}%;background:{{ $stage['color'] }};border-radius:4px;transition:width .6s;"></div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

{{-- ── PARTICIPANT BREAKDOWN ─────────────────────────────────────────────────── --}}
<div class="dash-section" data-aos="fade-up">
    <div class="section-title"><i class="fas fa-users-cog"></i> Participant Breakdown</div>
    <div class="chart-row">
        <div class="chart-card chart-half" data-aos="fade-up" data-aos-delay="100">
            <div class="chart-card-header">
                <h6><i class="fas fa-building"></i> By Department</h6>
                <small>Top departments by mentee count</small>
            </div>
            <div class="chart-card-body">
                <canvas id="emoncDeptChart" style="max-height:260px;"></canvas>
            </div>
        </div>
        <div class="chart-card chart-half" data-aos="fade-up" data-aos-delay="200">
            <div class="chart-card-header">
                <h6><i class="fas fa-user-md"></i> By Cadre</h6>
                <small>Professional roles distribution</small>
            </div>
            <div class="chart-card-body">
                <canvas id="emoncCadreChart" style="max-height:260px;"></canvas>
            </div>
        </div>
    </div>
</div>

{{-- ── FACILITY TYPE + TOP COUNTIES ────────────────────────────────────────── --}}
<div class="dash-section" data-aos="fade-up">
    <div class="section-title"><i class="fas fa-hospital-symbol"></i> Coverage &amp; Geographic Reach</div>
    <div class="chart-row">
        <div class="chart-card chart-half" data-aos="fade-up" data-aos-delay="100">
            <div class="chart-card-header">
                <h6><i class="fas fa-clinic-medical"></i> EmONC Facilities by Type</h6>
                <small>Breakdown of EmONC host facilities by category</small>
            </div>
            <div class="chart-card-body">
                <canvas id="emoncFacilityTypeChart" style="max-height:250px;"></canvas>
            </div>
        </div>
        <div class="chart-card chart-half" data-aos="fade-up" data-aos-delay="200">
            <div class="chart-card-header">
                <h6><i class="fas fa-map-marked-alt"></i> Top Counties by Mentees</h6>
                <small>Leading counties for EmONC mentorship</small>
            </div>
            <div class="chart-card-body">
                <canvas id="emoncTopCountiesChart" style="max-height:250px;"></canvas>
            </div>
        </div>
    </div>
</div>

{{-- ── MENTEE STATUS + TOP PROGRAMS ────────────────────────────────────────── --}}
<div class="dash-section" data-aos="fade-up">
    <div class="section-title"><i class="fas fa-graduation-cap"></i> Mentee &amp; Programme Analytics</div>
    <div class="chart-row">
        <div class="chart-card chart-half" data-aos="fade-up" data-aos-delay="100">
            <div class="chart-card-header">
                <h6><i class="fas fa-users"></i> Mentee Status Distribution</h6>
                <small>Current enrolment status of all mentees</small>
            </div>
            <div class="chart-card-body d-flex flex-column align-items-center gap-3">
                <canvas id="emoncMenteeStatusChart" style="max-height:200px;max-width:240px;"></canvas>
            </div>
        </div>
        <div class="chart-card chart-half" data-aos="fade-up" data-aos-delay="200">
            <div class="chart-card-header">
                <h6><i class="fas fa-trophy"></i> Top EmONC Programmes</h6>
                <small>By mentee count</small>
            </div>
            <div class="chart-card-body">
                <canvas id="emoncTopProgramsChart" style="max-height:200px;"></canvas>
            </div>
        </div>
    </div>
</div>

{{-- ── MODULE COMPLETION DISTRIBUTION ──────────────────────────────────────── --}}
<div class="dash-section" data-aos="fade-up">
    <div class="section-title"><i class="fas fa-project-diagram"></i> Module Completion Distribution</div>
    <div class="chart-card" data-aos="fade-up" data-aos-delay="100">
        <div class="chart-card-header">
            <h6><i class="fas fa-chart-bar"></i> Mentee Module Completion</h6>
            <small>Distribution of modules completed per mentee</small>
        </div>
        <div class="chart-card-body">
            <canvas id="emoncCompletionDistChart" style="max-height:220px;"></canvas>
        </div>
    </div>
</div>

{{-- ── PER-MENTEE COMPLETION MATRIX ────────────────────────────────────────── --}}
<div class="dash-section" data-aos="fade-up">
    <div class="section-title"><i class="fas fa-table"></i> Per-Mentee Completion Matrix</div>
    <div class="chart-card">
        <div class="chart-card-header">
            <h6><i class="fas fa-heartbeat"></i> EmONC Mentee Status</h6>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <small class="text-muted" id="emoncMatrixCount">{{ count($emoncMatrix) }} records</small>
                <input type="text" id="emoncMatrixFilter" placeholder="Filter by name, module or class…" class="emonc-filter">
            </div>
        </div>
        <div style="overflow-x:auto;">
            @if(count($emoncMatrix) === 0)
            <div style="padding:3rem;text-align:center;color:var(--gray-500);">
                <i class="fas fa-heartbeat fa-2x mb-3" style="color:var(--gray-200);"></i>
                <p style="font-size:.95rem;font-weight:700;color:var(--gray-700);margin-bottom:.35rem;">No EmONC Mentorship Data Found</p>
                <p style="font-size:.85rem;">Programmes must be tagged with <em>Maternal EmONC</em> in the program name.</p>
            </div>
            @else
            <table class="emonc-matrix" id="emoncMatrixTable">
                <thead>
                    <tr>
                        <th>Mentee</th>
                        <th>Facility</th>
                        <th>Class</th>
                        <th>Module</th>
                        <th>Activities</th>
                        <th>Video</th>
                        <th>Approvals</th>
                        <th>Certificate</th>
                        <th>CPD Points</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($emoncMatrix as $row)
                    <tr class="emonc-row{{ $row['certificate_ready'] ? ' emonc-row-certified' : '' }}"
                        data-search="{{ strtolower($row['mentee_name'] . ' ' . $row['module_name'] . ' ' . ($row['parent_module_name'] ?? '') . ' ' . $row['class_name'] . ' ' . $row['facility_name']) }}">
                        <td>
                            <div style="font-weight:600;color:var(--gray-800);font-size:.84rem;">{{ $row['mentee_name'] }}</div>
                            <div style="font-size:.72rem;color:var(--gray-500);">{{ $row['county_name'] }}</div>
                        </td>
                        <td style="font-size:.82rem;color:var(--gray-600);">{{ $row['facility_name'] }}</td>
                        <td style="font-size:.82rem;color:var(--gray-700);">{{ $row['class_name'] }}</td>
                        <td>
                            <div style="font-size:.83rem;font-weight:600;color:var(--gray-800);">{{ $row['module_name'] }}</div>
                            @if($row['parent_module_name'])
                            <div style="font-size:.7rem;color:var(--teal);font-weight:600;">{{ $row['parent_module_name'] }}</div>
                            @endif
                        </td>
                        <td>
                            <div style="display:flex;flex-wrap:wrap;gap:3px;">
                                @foreach($row['activities'] as $act)
                                <span class="act-chip {{ $act['status'] === 'completed' ? 'act-done' : ($act['status'] === 'not_enrolled' ? 'act-none' : 'act-pending') }}">
                                    {{ $act['name'] }} {{ $act['status'] === 'completed' ? '✓' : ($act['status'] === 'not_enrolled' ? '—' : '○') }}
                                </span>
                                @endforeach
                                @if(empty($row['activities']))
                                <span style="font-size:.74rem;color:var(--gray-400);">None</span>
                                @endif
                            </div>
                        </td>
                        <td>
                            @php
                                $vr = $row['video_review'];
                                $vClass = match($vr) { 'passed' => 'badge-cert', 'failed' => 'badge-blocked', 'pending' => 'badge-prog', default => 'badge-none' };
                                $vLabel = match($vr) { 'passed' => 'Passed', 'failed' => 'Failed', 'pending' => 'Pending', 'not_submitted' => 'Not Submitted', default => ucfirst((string)$vr) };
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
                                <span class="badge-sm badge-cert"><i class="fas fa-award me-1" style="font-size:.65rem;"></i> Certified</span>
                            @else
                                <span class="badge-sm badge-blocked" title="{{ implode(', ', $row['blocked_reasons']) }}" style="cursor:help;">
                                    <i class="fas fa-lock me-1" style="font-size:.65rem;"></i>
                                    Blocked{{ count($row['blocked_reasons']) ? ' ('.count($row['blocked_reasons']).')' : '' }}
                                </span>
                            @endif
                        </td>
                        <td>
                            <div style="font-weight:800;font-size:.9rem;color:{{ $row['cpd_level_color'] }};">{{ $row['cpd_total'] }} pts</div>
                            <span class="cpd-badge" style="background:{{ $row['cpd_level_color'] }}18;color:{{ $row['cpd_level_color'] }};border-color:{{ $row['cpd_level_color'] }}33;">
                                {{ $row['cpd_level_short'] }} · {{ $row['cpd_level'] }}
                            </span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            <div style="padding:.75rem 1rem;border-top:1px solid var(--gray-100);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem;">
                <div class="emonc-pager">
                    <button id="emoncPagerPrev" onclick="emoncPagerGo(-1)">&#8592; Prev</button>
                    <span id="emoncPagerInfo" style="padding:0 .4rem;"></span>
                    <button id="emoncPagerNext" onclick="emoncPagerGo(1)">Next &#8594;</button>
                </div>
                <small class="text-muted" id="emoncPagerSummary"></small>
            </div>
            @endif
        </div>
    </div>
</div>

{{-- ── JSON payloads + JS ──────────────────────────────────────────────────── --}}
<script type="application/json" id="emonc-chart-data">@json($emoncChartData)</script>
<script type="application/json" id="emonc-extended-data">@json($emoncExtendedStats)</script>

<script>
(function () {
    const TEAL_COLORS = [
        '#0097A7','#26C6DA','#004D40','#10B981','#F59E0B',
        '#8B5CF6','#2563EB','#EF4444','#EC4899','#14B8A6',
        '#F97316','#84CC16','#6366F1','#06B6D4','#D97706'
    ];

    function parseJSON(id) {
        try { return JSON.parse(document.getElementById(id)?.textContent || '{}'); } catch (e) { return {}; }
    }

    const pctLabel = (value, ctx) => {
        const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
        return total > 0 && value > 0 ? Math.round((value / total) * 100) + '%' : '';
    };

    function mkLine(id, labels, data, label) {
        const el = document.getElementById(id);
        if (!el || typeof Chart === 'undefined') return;
        const ctx  = el.getContext('2d');
        const grad = ctx.createLinearGradient(0, 0, 0, 200);
        grad.addColorStop(0, 'rgba(0,151,167,0.3)');
        grad.addColorStop(1, 'rgba(0,151,167,0.02)');
        new Chart(ctx, {
            type: 'line',
            data: { labels, datasets: [{ label, data, borderColor: '#0097A7', backgroundColor: grad, borderWidth: 2.5, fill: true, tension: 0.4, pointRadius: 4, pointBackgroundColor: '#0097A7', pointBorderColor: '#fff', pointBorderWidth: 2 }] },
            options: {
                responsive: true, maintainAspectRatio: true,
                plugins: {
                    legend: { display: false }, tooltip: { mode: 'index', intersect: false },
                    datalabels: {
                        display: true, anchor: 'end', align: 'top', offset: 4,
                        color: '#00707D', font: { size: 10, weight: '600' },
                        formatter: (value) => value > 0 ? value.toLocaleString() : '',
                    },
                },
                scales: { x: { grid: { display: false }, ticks: { font: { size: 11 } } }, y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' }, ticks: { font: { size: 11 } } } }
            }
        });
    }

    function mkDonut(id, labels, data, colors) {
        const el = document.getElementById(id);
        if (!el || !data.length || typeof Chart === 'undefined') return;
        new Chart(el, {
            type: 'doughnut',
            data: { labels, datasets: [{ data, backgroundColor: colors, borderWidth: 2, borderColor: '#fff', hoverOffset: 4 }] },
            options: {
                responsive: true, maintainAspectRatio: true, cutout: '65%',
                plugins: {
                    legend: { position: 'bottom', labels: { font: { size: 11 }, padding: 8, boxWidth: 12 } },
                    datalabels: { display: true, color: '#fff', font: { size: 10, weight: '700' }, formatter: pctLabel },
                },
            }
        });
    }

    function mkHBar(id, labels, data, label) {
        const el = document.getElementById(id);
        if (!el || !labels.length || typeof Chart === 'undefined') return;
        new Chart(el, {
            type: 'bar',
            data: { labels, datasets: [{ label, data, backgroundColor: labels.map((_, i) => TEAL_COLORS[i % TEAL_COLORS.length]), borderRadius: 6, borderSkipped: false }] },
            options: {
                indexAxis: 'y', responsive: true, maintainAspectRatio: true,
                plugins: {
                    legend: { display: false },
                    datalabels: {
                        display: true, anchor: 'end', align: 'end', offset: 4,
                        color: '#374151', font: { size: 10, weight: '600' },
                        formatter: (value) => value > 0 ? value.toLocaleString() : '',
                    },
                },
                layout: { padding: { right: 24 } },
                scales: { x: { beginAtZero: true, grid: { color: 'rgba(0,0,0,.04)' } }, y: { ticks: { font: { size: 11 } } } }
            }
        });
    }

    function mkBar(id, labels, data, colors) {
        const el = document.getElementById(id);
        if (!el || !labels.length || typeof Chart === 'undefined') return;
        new Chart(el, {
            type: 'bar',
            data: { labels, datasets: [{ data, backgroundColor: colors, borderRadius: 6, borderSkipped: false }] },
            options: {
                responsive: true, maintainAspectRatio: true,
                plugins: {
                    legend: { display: false }, tooltip: { callbacks: { label: c => c.raw + ' mentee' + (c.raw === 1 ? '' : 's') } },
                    datalabels: {
                        display: true, anchor: 'end', align: 'top', offset: 2,
                        color: '#374151', font: { size: 10, weight: '600' },
                        formatter: (value) => value > 0 ? value.toLocaleString() : '',
                    },
                },
                scales: { x: { grid: { display: false }, ticks: { font: { size: 11 } } }, y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,.04)' }, ticks: { font: { size: 11 } } } }
            }
        });
    }

    function initEmoncCharts() {
        const cd = parseJSON('emonc-chart-data');
        const ex = parseJSON('emonc-extended-data');

        // Trend
        const trend = ex.trend12 || [];
        mkLine('emoncTrendChart', trend.map(t => t.short || t.month), trend.map(t => t.count), 'Classes Started');

        // Class status donut
        const csb = cd.classStatusBreakdown || {};
        const statusColors = { draft: '#F59E0B', active: '#10B981', completed: '#2563EB', cancelled: '#EF4444', unknown: '#9CA3AF' };
        mkDonut('emoncClassStatusChart',
            Object.keys(csb).map(k => k.charAt(0).toUpperCase() + k.slice(1)),
            Object.values(csb),
            Object.keys(csb).map(k => statusColors[k] || '#9CA3AF')
        );

        // Completion donut
        const dist = cd.completionDistribution || [];
        mkDonut('emoncCompletionDonut', dist.map(d => d.label), dist.map(d => d.value), ['#22c55e', '#f59e0b', '#94a3b8']);

        // Departments horizontal bar
        const depts = (cd.departments || []).slice(0, 10);
        mkHBar('emoncDeptChart', depts.map(d => d.name || 'Unknown'), depts.map(d => d.count || 0), 'Mentees');

        // Cadres donut
        const cadres = (cd.cadres || []).slice(0, 8);
        mkDonut('emoncCadreChart', cadres.map(c => c.name || 'Unknown'), cadres.map(c => c.count || 0), TEAL_COLORS);

        // Facility types grouped bar
        const ft = cd.facilityTypes || [];
        const ftEl = document.getElementById('emoncFacilityTypeChart');
        if (ftEl && ft.length && typeof Chart !== 'undefined') {
            new Chart(ftEl, {
                type: 'bar',
                data: {
                    labels: ft.map(f => f.name || 'Unknown'),
                    datasets: [{ label: 'EmONC Facilities', data: ft.map(f => f.facilities_with_training || 0), backgroundColor: '#0097A7', borderRadius: 4 }]
                },
                options: {
                    responsive: true, maintainAspectRatio: true,
                    plugins: {
                        legend: { position: 'bottom', labels: { font: { size: 11 } } },
                        datalabels: {
                            display: true, anchor: 'end', align: 'top', offset: 2,
                            color: '#374151', font: { size: 10, weight: '600' },
                            formatter: (value) => value > 0 ? value.toLocaleString() : '',
                        },
                    },
                    scales: { x: { ticks: { font: { size: 10 } } }, y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,.04)' } } }
                }
            });
        }

        // Top counties
        const counties = (ex.topCounties || []).slice(0, 10);
        mkHBar('emoncTopCountiesChart', counties.map(c => c.name), counties.map(c => c.count), 'Mentees');

        // Mentee status donut
        const ms = cd.menteeStatus || {};
        const menteeColors = { completed: '#10B981', enrolled: '#0097A7', dropped: '#EF4444', active: '#2563EB', default: '#9CA3AF' };
        mkDonut('emoncMenteeStatusChart',
            Object.keys(ms).map(k => k.charAt(0).toUpperCase() + k.slice(1)),
            Object.values(ms),
            Object.keys(ms).map(k => menteeColors[k] || menteeColors.default)
        );

        // Top programs horizontal bar
        const progs = (ex.topPrograms || []).slice(0, 5);
        mkHBar('emoncTopProgramsChart',
            progs.map(p => { const t = p.title || '(Untitled)'; return t.length > 30 ? t.substring(0, 30) + '…' : t; }),
            progs.map(p => p.count),
            'Mentees'
        );

        // Module completion % distribution
        const buckets = cd.buckets || {};
        const bucketOrder = ['0-25%', '26-50%', '51-75%', '76-99%', '100%'];
        const bLabels = bucketOrder.filter(k => buckets[k] !== undefined);
        const bData   = bLabels.map(k => buckets[k] || 0);
        mkBar('emoncCompletionDistChart', bLabels, bData, ['#EF4444', '#F97316', '#F59E0B', '#3B82F6', '#10B981']);
    }

    // Matrix: filter + pagination
    let emoncPage    = 1;
    const PAGE_SIZE  = 25;
    let emoncVisible = [];

    function emoncRefresh() {
        const q    = (document.getElementById('emoncMatrixFilter')?.value || '').toLowerCase().trim();
        const rows = Array.from(document.querySelectorAll('#emoncMatrixTable .emonc-row'));

        emoncVisible = rows.filter(r => !q || r.dataset.search.includes(q));

        // clamp page
        const totalPages = Math.max(1, Math.ceil(emoncVisible.length / PAGE_SIZE));
        if (emoncPage > totalPages) emoncPage = totalPages;
        if (emoncPage < 1) emoncPage = 1;

        const start = (emoncPage - 1) * PAGE_SIZE;
        const end   = start + PAGE_SIZE;

        rows.forEach(r => { r.style.display = 'none'; });
        emoncVisible.slice(start, end).forEach(r => { r.style.display = ''; });

        const info    = document.getElementById('emoncPagerInfo');
        const summary = document.getElementById('emoncPagerSummary');
        const prev    = document.getElementById('emoncPagerPrev');
        const next    = document.getElementById('emoncPagerNext');
        if (info)    info.textContent    = 'Page ' + emoncPage + ' of ' + totalPages;
        if (summary) summary.textContent = 'Showing ' + Math.min(start + 1, emoncVisible.length) + '–' + Math.min(end, emoncVisible.length) + ' of ' + emoncVisible.length + (q ? ' filtered' : '') + ' records';
        if (prev)    prev.disabled       = emoncPage <= 1;
        if (next)    next.disabled       = emoncPage >= totalPages;
    }

    window.emoncPagerGo = function (dir) { emoncPage += dir; emoncRefresh(); };

    function initEmoncFilter() {
        const input = document.getElementById('emoncMatrixFilter');
        if (!input) return;
        input.addEventListener('input', function () { emoncPage = 1; emoncRefresh(); });
        emoncRefresh(); // initial render
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { initEmoncCharts(); initEmoncFilter(); });
    } else {
        initEmoncCharts(); initEmoncFilter();
    }
})();
</script>
