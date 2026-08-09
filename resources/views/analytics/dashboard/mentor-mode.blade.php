{{-- Mentor analytics mode — $mentorKpis, $mentorMatrix, $mentorCharts, $mentorInsights --}}
{{-- Filters: $mentorFilters, $mentorPrograms, $mentorCounties, $mentorSubcounties, $mentorFacilities, $mentorCadres, $mentorDepartments, $mentorUsers --}}

<style>
.mentor-kpi-strip .kpi-card:nth-child(1) { --kpi-accent:#3B82F6; border-top-color:#3B82F6; }
.mentor-kpi-strip .kpi-card:nth-child(1) .kpi-icon { background:rgba(59,130,246,.1); color:#3B82F6; }
.mentor-kpi-strip .kpi-card:nth-child(2) { --kpi-accent:var(--teal); border-top-color:var(--teal); }
.mentor-kpi-strip .kpi-card:nth-child(3) { --kpi-accent:var(--emerald); border-top-color:var(--emerald); }
.mentor-kpi-strip .kpi-card:nth-child(3) .kpi-icon { background:rgba(16,185,129,.1); color:var(--emerald); }
.mentor-kpi-strip .kpi-card:nth-child(4) { --kpi-accent:var(--violet); border-top-color:var(--violet); }
.mentor-kpi-strip .kpi-card:nth-child(4) .kpi-icon { background:rgba(139,92,246,.1); color:var(--violet); }
.mentor-kpi-strip .kpi-card:nth-child(5) { --kpi-accent:var(--amber); border-top-color:var(--amber); }
.mentor-kpi-strip .kpi-card:nth-child(5) .kpi-icon { background:rgba(245,158,11,.1); color:var(--amber); }
.mentor-kpi-strip .kpi-card:nth-child(6) { --kpi-accent:#F97316; border-top-color:#F97316; }
.mentor-kpi-strip .kpi-card:nth-child(6) .kpi-icon { background:rgba(249,115,22,.1); color:#F97316; }
.mentor-kpi-strip .kpi-card:nth-child(7) { --kpi-accent:var(--emerald); border-top-color:var(--emerald); }
.mentor-kpi-strip .kpi-card:nth-child(7) .kpi-icon { background:rgba(16,185,129,.1); color:var(--emerald); }

/* Matrix */
.mentor-matrix { width:100%;border-collapse:collapse;font-size:.84rem; }
.mentor-matrix thead th { background:linear-gradient(90deg,#1e3a5f 0%,#1d4ed8 100%);color:rgba(255,255,255,.9);font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;padding:.65rem .9rem;text-align:left;white-space:nowrap; }
.mentor-matrix tbody tr { border-bottom:1px solid var(--gray-100);transition:background .15s; }
.mentor-matrix tbody tr:hover { background:#EFF6FF; }
.mentor-matrix tbody td { padding:.6rem .9rem;vertical-align:middle; }
.m-badge { display:inline-flex;align-items:center;padding:.2rem .5rem;border-radius:10px;font-size:.7rem;font-weight:700;border:1px solid transparent;white-space:nowrap; }
.m-filter { padding:.4rem .75rem;border:1px solid var(--gray-200);border-radius:7px;font-size:.82rem;color:var(--gray-700);background:#fff;outline:none;transition:border-color .15s,box-shadow .15s; }
.m-filter:focus { border-color:#3B82F6;box-shadow:0 0 0 3px rgba(59,130,246,.1); }
.mentor-pager { display:flex;align-items:center;gap:.5rem;font-size:.82rem;color:var(--gray-600); }
.mentor-pager button { padding:.28rem .65rem;border:1px solid var(--gray-200);border-radius:6px;background:#fff;cursor:pointer;font-size:.79rem;color:var(--gray-700);transition:all .15s; }
.mentor-pager button:hover:not(:disabled) { border-color:#3B82F6;color:#3B82F6; }
.mentor-pager button:disabled { opacity:.35;cursor:default; }

/* Filter bar */
.mf-bar { background:#fff;border:1px solid var(--gray-200);border-radius:12px;padding:.85rem 1.1rem;margin-bottom:1.25rem;display:flex;flex-wrap:wrap;gap:.6rem;align-items:flex-end; }
.mf-group { display:flex;flex-direction:column;gap:.2rem;min-width:140px; }
.mf-label { font-size:.7rem;font-weight:700;color:var(--gray-500);text-transform:uppercase;letter-spacing:.04em; }
.mf-select { padding:.38rem .65rem;border:1px solid var(--gray-200);border-radius:7px;font-size:.82rem;color:var(--gray-700);background:#fff;outline:none;cursor:pointer; }
.mf-select:focus { border-color:#3B82F6;box-shadow:0 0 0 3px rgba(59,130,246,.1); }
.mf-has-value { border-color:#3B82F6;background:#EFF6FF;color:#1E40AF; }
.mf-apply { padding:.4rem .9rem;background:#3B82F6;color:#fff;border:none;border-radius:7px;font-size:.82rem;font-weight:700;cursor:pointer;transition:background .15s; }
.mf-apply:hover { background:#2563EB; }
.mf-clear { padding:.4rem .75rem;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:7px;font-size:.82rem;font-weight:600;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center; }
.mf-clear:hover { background:var(--gray-200); }
</style>

{{-- ── FILTER BAR ───────────────────────────────────────────────────────────── --}}
<form method="GET" action="" id="mentorFilterForm">
    <input type="hidden" name="mode" value="mentor">
    <input type="hidden" name="year" value="{{ $selectedYear ?? '' }}">
    <div class="mf-bar" data-aos="fade-up">
        <div class="mf-group">
            <span class="mf-label">Program</span>
            <select name="program_id" class="mf-select {{ !empty($mentorFilters['program_id']) ? 'mf-has-value' : '' }}" onchange="this.form.submit()">
                <option value="">All Programs</option>
                @foreach($mentorPrograms as $p)
                    <option value="{{ $p->id }}" {{ ($mentorFilters['program_id'] ?? '') == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="mf-group">
            <span class="mf-label">Mentor</span>
            <select name="mentor_id" class="mf-select {{ !empty($mentorFilters['mentor_id']) ? 'mf-has-value' : '' }}" onchange="this.form.submit()">
                <option value="">All Mentors</option>
                @foreach($mentorUsers as $mu)
                    <option value="{{ $mu->id }}" {{ ($mentorFilters['mentor_id'] ?? '') == $mu->id ? 'selected' : '' }}>{{ $mu->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="mf-group">
            <span class="mf-label">County</span>
            <select name="county_id" class="mf-select {{ !empty($mentorFilters['county_id']) ? 'mf-has-value' : '' }}" onchange="this.form.submit()">
                <option value="">All Counties</option>
                @foreach($mentorCounties as $c)
                    <option value="{{ $c->id }}" {{ ($mentorFilters['county_id'] ?? '') == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                @endforeach
            </select>
        </div>
        @if($mentorSubcounties->count())
        <div class="mf-group">
            <span class="mf-label">Sub-County</span>
            <select name="subcounty_id" class="mf-select {{ !empty($mentorFilters['subcounty_id']) ? 'mf-has-value' : '' }}" onchange="this.form.submit()">
                <option value="">All Sub-Counties</option>
                @foreach($mentorSubcounties as $sc)
                    <option value="{{ $sc->id }}" {{ ($mentorFilters['subcounty_id'] ?? '') == $sc->id ? 'selected' : '' }}>{{ $sc->name }}</option>
                @endforeach
            </select>
        </div>
        @endif
        @if($mentorFacilities->count())
        <div class="mf-group">
            <span class="mf-label">Facility</span>
            <select name="facility_id" class="mf-select {{ !empty($mentorFilters['facility_id']) ? 'mf-has-value' : '' }}" onchange="this.form.submit()">
                <option value="">All Facilities</option>
                @foreach($mentorFacilities as $f)
                    <option value="{{ $f->id }}" {{ ($mentorFilters['facility_id'] ?? '') == $f->id ? 'selected' : '' }}>{{ $f->name }}</option>
                @endforeach
            </select>
        </div>
        @endif
        <div class="mf-group">
            <span class="mf-label">Cadre</span>
            <select name="cadre_id" class="mf-select {{ !empty($mentorFilters['cadre_id']) ? 'mf-has-value' : '' }}" onchange="this.form.submit()">
                <option value="">All Cadres</option>
                @foreach($mentorCadres as $cad)
                    <option value="{{ $cad->id }}" {{ ($mentorFilters['cadre_id'] ?? '') == $cad->id ? 'selected' : '' }}>{{ $cad->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="mf-group">
            <span class="mf-label">Department</span>
            <select name="department_id" class="mf-select {{ !empty($mentorFilters['department_id']) ? 'mf-has-value' : '' }}" onchange="this.form.submit()">
                <option value="">All Departments</option>
                @foreach($mentorDepartments as $dep)
                    <option value="{{ $dep->id }}" {{ ($mentorFilters['department_id'] ?? '') == $dep->id ? 'selected' : '' }}>{{ $dep->name }}</option>
                @endforeach
            </select>
        </div>
        @php $hasFilters = collect($mentorFilters)->filter()->isNotEmpty(); @endphp
        @if($hasFilters)
        <div class="mf-group" style="justify-content:flex-end;">
            <span class="mf-label">&nbsp;</span>
            <a href="?mode=mentor" class="mf-clear"><i class="fas fa-times me-1" style="font-size:.72rem;"></i>Clear filters</a>
        </div>
        @endif
    </div>
</form>

{{-- ── EXCEPTIONS ───────────────────────────────────────────────────────────── --}}
@if(!empty($mentorExceptions))
<div class="dash-section" data-aos="fade-up" style="margin-bottom:1.25rem;background:#fff;border:1px solid var(--gray-200);border-radius:12px;overflow:hidden;">
    <div style="padding:.9rem 1.1rem;border-bottom:1px solid var(--gray-100);">
        <h6 style="margin:0;font-weight:700;color:var(--gray-800);font-size:.92rem;">
            <i class="fas fa-triangle-exclamation" style="color:#F59E0B;margin-right:.4rem;"></i>
            {{ count($mentorExceptions) }} exception{{ count($mentorExceptions) !== 1 ? 's' : '' }} need attention
        </h6>
    </div>
    @foreach(array_slice($mentorExceptions, 0, 5) as $item)
        @php
            $tierColor = match($item['tier']) {
                1 => '#EF4444',
                2 => '#F59E0B',
                default => '#3B82F6',
            };
        @endphp
        <div style="padding:.75rem 1.1rem;{{ !$loop->last ? 'border-bottom:1px solid var(--gray-100);' : '' }}display:flex;align-items:center;justify-content:space-between;gap:1rem;">
            <div style="display:flex;align-items:center;gap:.65rem;min-width:0;">
                <span style="width:8px;height:8px;border-radius:50%;background:{{ $tierColor }};flex-shrink:0;"></span>
                <div style="min-width:0;">
                    <div style="font-size:.85rem;font-weight:700;color:var(--gray-800);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $item['headline'] }}</div>
                    <div style="font-size:.76rem;color:var(--gray-500);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $item['subtext'] }}</div>
                </div>
            </div>
            <a href="{{ $item['url'] }}" style="flex-shrink:0;padding:.4rem .9rem;border-radius:8px;background:{{ $tierColor }};color:#fff;font-size:.78rem;font-weight:700;text-decoration:none;">
                {{ $item['label'] }}
            </a>
        </div>
    @endforeach
    @if(count($mentorExceptions) > 5)
        <div style="padding:.75rem 1.1rem;text-align:center;border-top:1px solid var(--gray-100);">
            <button type="button" class="btn btn-link" style="font-size:.82rem;font-weight:700;text-decoration:none;" data-bs-toggle="modal" data-bs-target="#exceptionsModal">
                See all {{ count($mentorExceptions) }} exceptions →
            </button>
        </div>
    @endif
</div>

{{-- ── EXCEPTIONS MODAL (all items) ─────────────────────────────────────────── --}}
<div class="modal fade" id="exceptionsModal" tabindex="-1" aria-labelledby="exceptionsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exceptionsModalLabel">All {{ count($mentorExceptions) }} Exceptions</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <table class="table table-sm table-hover">
                    <thead>
                        <tr>
                            <th>Tier</th>
                            <th>Item</th>
                            <th>Detail</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($mentorExceptions as $item)
                            @php
                                $tierColor = match($item['tier']) {
                                    1 => '#EF4444',
                                    2 => '#F59E0B',
                                    default => '#3B82F6',
                                };
                                $detail = match($item['tier']) {
                                    1 => ($item['meta']['completion_pct'] ?? '—') . '% completion, ' . ($item['meta']['attendance_pct'] ?? '—') . '% attendance',
                                    2 => ($item['meta']['days_inactive'] ?? '—') . ' days inactive',
                                    3 => ($item['meta']['classes_led'] ?? '—') . ' class(es) led, 0 CPD',
                                    default => '—',
                                };
                            @endphp
                            <tr>
                                <td><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:{{ $tierColor }};"></span></td>
                                <td>
                                    <div style="font-size:.85rem;font-weight:700;color:var(--gray-800);">{{ $item['headline'] }}</div>
                                    <div style="font-size:.76rem;color:var(--gray-500);">{{ $item['subtext'] }}</div>
                                </td>
                                <td style="font-size:.82rem;color:var(--gray-600);">{{ $detail }}</td>
                                <td>
                                    <a href="{{ $item['url'] }}" style="padding:.35rem .8rem;border-radius:8px;background:{{ $tierColor }};color:#fff;font-size:.76rem;font-weight:700;text-decoration:none;white-space:nowrap;">
                                        {{ $item['label'] }}
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@else
<div class="dash-section" data-aos="fade-up" style="margin-bottom:1.25rem;background:#F0FDF4;border:1px solid #BBF7D0;border-radius:12px;padding:.9rem 1.1rem;">
    <span style="font-size:.85rem;font-weight:700;color:#166534;">No exceptions</span>
    <span style="font-size:.82rem;color:#15803D;margin-left:.4rem;">Every facility and mentor in view is healthy.</span>
</div>
@endif

{{-- ── KPI STRIP ────────────────────────────────────────────────────────────── --}}
<div class="kpi-strip-wrap mentor-kpi-strip" data-aos="fade-up">
    <div class="kpi-strip">
        <div class="kpi-card">
            <div class="kpi-icon"><i class="fas fa-user-tie"></i></div>
            <div class="kpi-value counter-animate" data-counter="{{ $mentorKpis['total_mentors'] }}">{{ $mentorKpis['total_mentors'] }}</div>
            <div class="kpi-label">Total Mentors</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon"><i class="fas fa-chalkboard-teacher"></i></div>
            <div class="kpi-value counter-animate" data-counter="{{ $mentorKpis['active_classes'] }}">{{ $mentorKpis['active_classes'] }}</div>
            <div class="kpi-label">Active Classes</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon"><i class="fas fa-check-double"></i></div>
            <div class="kpi-value counter-animate" data-counter="{{ $mentorKpis['completed_classes'] }}">{{ $mentorKpis['completed_classes'] }}</div>
            <div class="kpi-label">Completed Classes</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon"><i class="fas fa-book-open"></i></div>
            <div class="kpi-value counter-animate" data-counter="{{ $mentorKpis['modules_facilitated'] }}">{{ $mentorKpis['modules_facilitated'] }}</div>
            <div class="kpi-label">Modules Facilitated</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon"><i class="fas fa-users"></i></div>
            <div class="kpi-value counter-animate" data-counter="{{ $mentorKpis['total_mentees'] }}">{{ $mentorKpis['total_mentees'] }}</div>
            <div class="kpi-label">Mentees Supported</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon"><i class="fas fa-star"></i></div>
            <div class="kpi-value counter-animate" data-counter="{{ $mentorKpis['total_cpd_awarded'] }}">{{ $mentorKpis['total_cpd_awarded'] }}</div>
            <div class="kpi-label">Total CPD Points</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon"><i class="fas fa-chart-bar"></i></div>
            <div class="kpi-value">{{ $mentorKpis['avg_cpd_per_mentor'] }}</div>
            <div class="kpi-label">Avg CPD / Mentor</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon"><i class="fas fa-balance-scale"></i></div>
            <div class="kpi-value">{{ $mentorKpis['mentor_to_mentee_ratio'] }}</div>
            <div class="kpi-label">Mentees per Mentor</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon"><i class="fas fa-clipboard-question"></i></div>
            <div class="kpi-value">
                {{ $mentorKpis['avg_assessment_score'] !== null ? $mentorKpis['avg_assessment_score'] . '%' : 'No data yet' }}
            </div>
            <div class="kpi-label">Avg Assessment Score</div>
            @if($mentorKpis['assessment_pass_rate'] !== null)
                <span class="kpi-trend {{ $mentorKpis['assessment_pass_rate'] >= 70 ? 'up' : 'down' }}">
                    {{ $mentorKpis['assessment_pass_rate'] }}% pass rate
                </span>
            @endif
        </div>
        <div class="kpi-card">
            <div class="kpi-icon"><i class="fas fa-hand-holding-medical"></i></div>
            <div class="kpi-value">
                {{ $mentorKpis['avg_rubric_score'] !== null ? $mentorKpis['avg_rubric_score'] : 'No data yet' }}
            </div>
            <div class="kpi-label">Avg Practical Skills Score</div>
            @if($mentorKpis['rubric_pass_rate'] !== null)
                <span class="kpi-trend {{ $mentorKpis['rubric_pass_rate'] >= 70 ? 'up' : 'down' }}">
                    {{ $mentorKpis['rubric_pass_rate'] }}% pass rate
                </span>
            @endif
        </div>
        <div class="kpi-card">
            <div class="kpi-icon"><i class="fas fa-user-clock"></i></div>
            <div class="kpi-value counter-animate" data-counter="{{ $mentorKpis['inactive_mentors'] }}">{{ $mentorKpis['inactive_mentors'] }}</div>
            <div class="kpi-label">Inactive Mentors</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon"><i class="fas fa-user-slash"></i></div>
            <div class="kpi-value counter-animate" data-counter="{{ $mentorKpis['dropped_mentees'] }}">{{ $mentorKpis['dropped_mentees'] }}</div>
            <div class="kpi-label">Dropped Mentees</div>
        </div>
    </div>
</div>

{{-- ── INSIGHTS ─────────────────────────────────────────────────────────────── --}}
@if(count($mentorInsights))
<div class="dash-section" data-aos="fade-up" style="margin-bottom:1.25rem;">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:.75rem;">
        @foreach($mentorInsights as $ins)
        @php
            $ic = ['success'=>['#D1FAE5','#065F46'],'warning'=>['#FEF3C7','#92400E'],'danger'=>['#FEE2E2','#991B1B'],'info'=>['#DBEAFE','#1E40AF'],'primary'=>['#EDE9FE','#5B21B6']];
            $cc = $ic[$ins['type']] ?? ['#F1F5F9','#475569'];
        @endphp
        <div style="background:{{ $cc[0] }};border-radius:10px;padding:.75rem 1rem;display:flex;align-items:flex-start;gap:.55rem;">
            <i class="fas fa-{{ $ins['icon'] }}" style="color:{{ $cc[1] }};margin-top:.15rem;font-size:.85rem;flex-shrink:0;"></i>
            <span style="font-size:.83rem;color:{{ $cc[1] }};">{!! $ins['text'] !!}</span>
        </div>
        @endforeach
    </div>
</div>
@endif

{{-- ── LEADERBOARDS ─────────────────────────────────────────────────────────── --}}
<div class="dash-section" data-aos="fade-up">
    <div class="section-title"><i class="fas fa-trophy"></i> Mentor Leaderboards</div>
    <div class="chart-row">
        <div class="chart-card chart-half" data-aos="fade-right" data-aos-delay="100">
            <div class="chart-card-header">
                <h6><i class="fas fa-star"></i> Top 10 — CPD Points</h6>
                <small>Ranked by total CPD points earned</small>
            </div>
            <div class="chart-card-body">
                <canvas id="mentorCpdLeaderChart" style="max-height:220px;"></canvas>
            </div>
        </div>
        <div class="chart-card chart-half" data-aos="fade-left" data-aos-delay="200">
            <div class="chart-card-header">
                <h6><i class="fas fa-users"></i> Top 10 — Mentee Reach</h6>
                <small>Ranked by total mentees supported</small>
            </div>
            <div class="chart-card-body">
                <canvas id="mentorReachLeaderChart" style="max-height:220px;"></canvas>
            </div>
        </div>
    </div>
</div>

{{-- ── LEVEL DIST + CLASS STATUS ────────────────────────────────────────────── --}}
<div class="dash-section" data-aos="fade-up">
    <div class="section-title"><i class="fas fa-chart-pie"></i> Distribution &amp; Trends</div>
    <div class="chart-row">
        <div class="chart-card chart-1-3" data-aos="fade-right" data-aos-delay="100">
            <div class="chart-card-header">
                <h6><i class="fas fa-layer-group"></i> CPD Level Distribution</h6>
                <small>Mentors per level</small>
            </div>
            <div class="chart-card-body d-flex flex-column align-items-center gap-2">
                <canvas id="mentorLevelChart" style="max-height:190px;max-width:210px;"></canvas>
            </div>
        </div>
        <div class="chart-card chart-1-3" data-aos="fade-up" data-aos-delay="150">
            <div class="chart-card-header">
                <h6><i class="fas fa-tasks"></i> Class Status</h6>
                <small>Draft · Active · Completed</small>
            </div>
            <div class="chart-card-body d-flex flex-column align-items-center gap-2">
                <canvas id="mentorClassStatusChart" style="max-height:190px;max-width:210px;"></canvas>
            </div>
        </div>
        <div class="chart-card chart-1-3" data-aos="fade-left" data-aos-delay="200">
            <div class="chart-card-header">
                <h6><i class="fas fa-chart-line"></i> 12-Month Trend</h6>
                <small>Classes started per month</small>
            </div>
            <div class="chart-card-body">
                <canvas id="mentorTrendChart" style="max-height:190px;"></canvas>
            </div>
        </div>
    </div>
</div>

{{-- ── BY DEPT + BY FACILITY ─────────────────────────────────────────────────── --}}
<div class="dash-section" data-aos="fade-up">
    <div class="section-title"><i class="fas fa-sitemap"></i> Mentors by Department &amp; Facility</div>
    <div class="chart-row">
        <div class="chart-card chart-half" data-aos="fade-right" data-aos-delay="100">
            <div class="chart-card-header">
                <h6><i class="fas fa-hospital"></i> Mentors by Facility</h6>
                <small>Unique mentors per facility (top 10)</small>
            </div>
            <div class="chart-card-body">
                <canvas id="mentorByFacilityChart" style="max-height:220px;"></canvas>
            </div>
        </div>
        <div class="chart-card chart-half" data-aos="fade-left" data-aos-delay="200">
            <div class="chart-card-header">
                <h6><i class="fas fa-hospital-user"></i> Mentors by Department</h6>
                <small>Unique mentors per department</small>
            </div>
            <div class="chart-card-body">
                <canvas id="mentorByDeptChart" style="max-height:220px;"></canvas>
            </div>
        </div>
    </div>
</div>

{{-- ── BY MODULE ─────────────────────────────────────────────────────────────── --}}
<div class="dash-section" data-aos="fade-up">
    <div class="chart-card">
        <div class="chart-card-header">
            <h6><i class="fas fa-book-open"></i> Mentors by Module</h6>
            <small>Unique mentors facilitating each module across live classes</small>
        </div>
        <div class="chart-card-body">
            <canvas id="mentorByModuleChart" style="max-height:220px;"></canvas>
        </div>
    </div>
</div>

{{-- ── MENTOR MATRIX ────────────────────────────────────────────────────────── --}}
<div class="dash-section" data-aos="fade-up">
    <div class="section-title"><i class="fas fa-table"></i> Mentor Performance Matrix</div>
    <div class="chart-card">
        <div class="chart-card-header">
            <h6><i class="fas fa-user-tie"></i> Active Classes — Mentor Activity</h6>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <small class="text-muted">{{ count($mentorMatrix) }} live class(es)</small>
                <input type="text" id="mentorMatrixFilter" placeholder="Search mentor, facility, county…" class="m-filter" style="width:220px;">
            </div>
        </div>
        <div style="overflow-x:auto;">
            @if(count($mentorMatrix) === 0)
            <div style="padding:3rem;text-align:center;color:var(--gray-500);">
                <i class="fas fa-user-tie fa-2x mb-3" style="color:var(--gray-200);"></i>
                <p style="font-size:.95rem;font-weight:700;color:var(--gray-700);margin-bottom:.35rem;">No Mentors Found</p>
                <p style="font-size:.85rem;margin-bottom:1rem;">No mentors match the current filters.</p>
                <a href="?mode=mentor" class="mf-clear" style="display:inline-flex;">
                    <i class="fas fa-times me-1" style="font-size:.72rem;"></i>Clear filters
                </a>
            </div>
            @else
            <table class="mentor-matrix" id="mentorMatrixTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th data-col="0" class="sortable-col">Mentor <i class="fas fa-sort" style="font-size:.65rem;opacity:.4;"></i></th>
                        <th data-col="1" class="sortable-col">Cadre <i class="fas fa-sort" style="font-size:.65rem;opacity:.4;"></i></th>
                        <th data-col="2" class="sortable-col">Dept <i class="fas fa-sort" style="font-size:.65rem;opacity:.4;"></i></th>
                        <th data-col="3" class="sortable-col">Facility <i class="fas fa-sort" style="font-size:.65rem;opacity:.4;"></i></th>
                        <th>Class</th>
                        <th data-col="5" class="sortable-col">Modules <i class="fas fa-sort" style="font-size:.65rem;opacity:.4;"></i></th>
                        <th>Modules Done</th>
                        <th data-col="6" class="sortable-col">Mentees <i class="fas fa-sort" style="font-size:.65rem;opacity:.4;"></i></th>
                        <th data-col="7" class="sortable-col">Certified <i class="fas fa-sort" style="font-size:.65rem;opacity:.4;"></i></th>
                        <th data-col="8" class="sortable-col">CPD <i class="fas fa-sort" style="font-size:.65rem;opacity:.4;"></i></th>
                        <th>Level</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($mentorMatrix as $i => $row)
                    <tr class="mentor-row"
                        data-search="{{ strtolower($row['mentor_name'].' '.$row['cadre'].' '.$row['facility'].' '.$row['county']) }}">
                        <td style="color:var(--gray-400);font-size:.78rem;font-weight:700;">{{ $i + 1 }}</td>
                        <td>
                            <div style="font-weight:700;color:var(--gray-800);font-size:.86rem;">{{ $row['mentor_name'] }}</div>
                            <div style="font-size:.7rem;color:var(--gray-500);">{{ $row['county'] }}</div>
                        </td>
                        <td style="font-size:.8rem;color:var(--gray-600);">{{ $row['cadre'] }}</td>
                        <td style="font-size:.8rem;color:var(--gray-600);">{{ $row['department'] }}</td>
                        <td style="font-size:.8rem;color:var(--gray-600);">{{ $row['facility'] }}</td>
                        <td style="font-size:.8rem;color:var(--gray-700);font-weight:600;">{{ $row['class_name'] }}</td>
                        <td>
                            <div style="font-size:.8rem;color:var(--gray-800);font-weight:700;text-align:center;">{{ $row['modules_count'] }}</div>
                            @if(!empty($row['modules']))
                            <div style="font-size:.68rem;color:var(--gray-400);line-height:1.3;margin-top:2px;">{{ implode(', ', array_slice($row['modules'], 0, 3)) }}{{ count($row['modules']) > 3 ? ' …' : '' }}</div>
                            @endif
                        </td>
                        <td style="text-align:center;white-space:nowrap;">
                            <span style="font-weight:700;font-size:.85rem;color:{{ $row['modules_completed'] === $row['modules_total'] && $row['modules_total'] > 0 ? '#10B981' : 'var(--gray-700)' }};">{{ $row['modules_completed'] }}</span><span style="font-size:.78rem;color:var(--gray-400);">/{{ $row['modules_total'] }}</span>
                        </td>
                        <td style="text-align:center;font-weight:600;color:var(--gray-700);">{{ $row['mentees'] }}</td>
                        <td style="text-align:center;">
                            @if($row['certified'] > 0)
                            <span class="m-badge" style="background:#EDE9FE;color:#5B21B6;border-color:#DDD6FE;">
                                <i class="fas fa-award me-1" style="font-size:.6rem;"></i>{{ $row['certified'] }}
                            </span>
                            @else
                            <span style="color:var(--gray-300);font-size:.78rem;">—</span>
                            @endif
                        </td>
                        <td style="text-align:center;">
                            <div style="font-weight:800;font-size:.95rem;color:{{ $row['cpd_level_color'] }};">{{ $row['cpd_total'] }}</div>
                        </td>
                        <td>
                            <span class="m-badge" style="background:{{ $row['cpd_level_color'] }}18;color:{{ $row['cpd_level_color'] }};border-color:{{ $row['cpd_level_color'] }}33;">
                                {{ $row['cpd_level_short'] }} · {{ $row['cpd_level'] }}
                            </span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            <div style="padding:.65rem 1rem;border-top:1px solid var(--gray-100);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem;">
                <div class="mentor-pager">
                    <button id="mentorPrev" onclick="mentorPagerGo(-1)">&#8592; Prev</button>
                    <span id="mentorPagerInfo"></span>
                    <button id="mentorNext" onclick="mentorPagerGo(1)">Next &#8594;</button>
                </div>
                <small class="text-muted" id="mentorPagerSummary"></small>
            </div>
            @endif
        </div>
    </div>
</div>

<script type="application/json" id="mentor-chart-data">@json($mentorCharts)</script>
<script type="application/json" id="mentor-matrix-data">@json(array_values($mentorMatrix))</script>

<script>
(function () {
    const COLORS = ['#3B82F6','#10B981','#F59E0B','#8B5CF6','#EF4444','#F97316','#06B6D4','#EC4899','#84CC16','#6366F1'];
    const LEVEL_COLORS = { 'Foundation':'#6B7280','Practitioner':'#3B82F6','Advanced Practitioner':'#10B981','Expert':'#8B5CF6','Master Practitioner':'#F59E0B' };

    function parseJSON(id) {
        try { return JSON.parse(document.getElementById(id)?.textContent || '{}'); } catch(e) { return {}; }
    }

    const pctLabel = (value, ctx) => {
        const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
        return total > 0 && value > 0 ? Math.round((value / total) * 100) + '%' : '';
    };

    function mkHBar(id, labels, data, colors, tooltipSuffix) {
        const el = document.getElementById(id);
        if (!el || !labels.length || typeof Chart === 'undefined') return;
        new Chart(el, {
            type: 'bar',
            data: { labels, datasets: [{ data, backgroundColor: colors, borderRadius: 4, borderSkipped: false }] },
            options: {
                indexAxis: 'y', responsive: true, maintainAspectRatio: true,
                plugins: {
                    legend: { display: false }, tooltip: { callbacks: { label: c => c.raw + (tooltipSuffix || '') } },
                    datalabels: {
                        display: true, anchor: 'end', align: 'end', offset: 4,
                        color: '#374151', font: { size: 10, weight: '600' },
                        formatter: (value) => value > 0 ? value.toLocaleString() : '',
                    },
                },
                layout: { padding: { right: 24 } },
                scales: { x: { beginAtZero: true, grid: { color: 'rgba(0,0,0,.04)' }, ticks: { font: { size: 10 } } }, y: { ticks: { font: { size: 10 } } } }
            }
        });
    }

    function mkDonut(id, labels, data, colors) {
        const el = document.getElementById(id);
        if (!el || !data.some(v => v > 0) || typeof Chart === 'undefined') return;
        new Chart(el, {
            type: 'doughnut',
            data: { labels, datasets: [{ data, backgroundColor: colors, borderWidth: 2, borderColor: '#fff', hoverOffset: 4 }] },
            options: {
                responsive: true, maintainAspectRatio: true, cutout: '62%',
                plugins: {
                    legend: { position: 'bottom', labels: { font: { size: 10 }, padding: 7, boxWidth: 10 } },
                    datalabels: { display: true, color: '#fff', font: { size: 10, weight: '700' }, formatter: pctLabel },
                },
            }
        });
    }

    function truncate(s, n) { s = s || ''; return s.length > n ? s.substring(0, n) + '…' : s; }

    function initMentorCharts() {
        const cd = parseJSON('mentor-chart-data');

        // CPD Leaderboard — server pre-computed, deduped by mentor
        const lbCpd = cd.leaderboardCpd || { labels: [], data: [] };
        mkHBar('mentorCpdLeaderChart',
            lbCpd.labels.map(n => truncate(n, 22)),
            lbCpd.data,
            lbCpd.labels.map((_, i) => COLORS[i % COLORS.length]),
            ' CPD pts'
        );

        // Mentee reach leaderboard — server pre-computed, deduped by mentor
        const lbReach = cd.leaderboardMentees || { labels: [], data: [] };
        mkHBar('mentorReachLeaderChart',
            lbReach.labels.map(n => truncate(n, 22)),
            lbReach.data,
            lbReach.labels.map((_, i) => COLORS[i % COLORS.length]),
            ' mentees'
        );

        // Level donut
        const lv = cd.levelCounts || {};
        mkDonut('mentorLevelChart', Object.keys(lv), Object.values(lv), Object.keys(lv).map(k => LEVEL_COLORS[k] || '#9CA3AF'));

        // Class status donut
        const cs = cd.classStatus || {};
        const statusColors = { draft: '#F59E0B', active: '#10B981', completed: '#3B82F6', cancelled: '#EF4444', unknown: '#9CA3AF' };
        mkDonut('mentorClassStatusChart',
            Object.keys(cs).map(k => k.charAt(0).toUpperCase() + k.slice(1)),
            Object.values(cs),
            Object.keys(cs).map(k => statusColors[k] || '#9CA3AF')
        );

        // 12-month trend
        const tr = cd.trend12 || [];
        const trEl = document.getElementById('mentorTrendChart');
        if (trEl && tr.length && typeof Chart !== 'undefined') {
            const ctx = trEl.getContext('2d');
            const grad = ctx.createLinearGradient(0, 0, 0, 190);
            grad.addColorStop(0, 'rgba(59,130,246,0.28)');
            grad.addColorStop(1, 'rgba(59,130,246,0.01)');
            new Chart(trEl, {
                type: 'line',
                data: { labels: tr.map(t => t.short), datasets: [{ label: 'Classes', data: tr.map(t => t.count), borderColor: '#3B82F6', backgroundColor: grad, borderWidth: 2, fill: true, tension: 0.4, pointRadius: 3, pointBackgroundColor: '#3B82F6', pointBorderColor: '#fff', pointBorderWidth: 2 }] },
                options: {
                    responsive: true, maintainAspectRatio: true,
                    plugins: {
                        legend: { display: false },
                        datalabels: {
                            display: true, anchor: 'end', align: 'top', offset: 4,
                            color: '#1D4ED8', font: { size: 10, weight: '600' },
                            formatter: (value) => value > 0 ? value.toLocaleString() : '',
                        },
                    },
                    scales: { x: { grid: { display: false }, ticks: { font: { size: 10 } } }, y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,.05)' }, ticks: { font: { size: 10 } } } }
                }
            });
        }

        // By department — mentorships per dept
        const dept = cd.byDepartment || [];
        mkHBar('mentorByDeptChart',
            dept.map(d => truncate(d.name, 24)),
            dept.map(d => d.count),
            dept.map((_, i) => COLORS[i % COLORS.length]),
            ' mentorship(s)'
        );

        // By facility
        const fac = cd.byFacility || [];
        mkHBar('mentorByFacilityChart',
            fac.map(f => truncate(f.name, 24)),
            fac.map(f => f.count),
            fac.map((_, i) => COLORS[i % COLORS.length]),
            ' mentor(s)'
        );

        // By module
        const mod = cd.byModule || [];
        mkHBar('mentorByModuleChart',
            mod.map(m => truncate(m.name, 30)),
            mod.map(m => m.count),
            mod.map((_, i) => COLORS[i % COLORS.length]),
            ' mentor(s)'
        );
    }

    // ── Column sort ───────────────────────────────────────────────────────────
    let sortCol = -1, sortAsc = true;
    function initSort() {
        document.querySelectorAll('#mentorMatrixTable .sortable-col').forEach(th => {
            th.style.cursor = 'pointer';
            th.addEventListener('click', function () {
                const col = parseInt(this.dataset.col);
                if (sortCol === col) { sortAsc = !sortAsc; } else { sortCol = col; sortAsc = true; }
                const tbody = document.querySelector('#mentorMatrixTable tbody');
                const rows  = Array.from(tbody.querySelectorAll('tr'));
                rows.sort((a, b) => {
                    const ac = a.querySelectorAll('td')[col + 1]; // +1 for rank column
                    const bc = b.querySelectorAll('td')[col + 1];
                    const av = ac ? ac.innerText.trim() : '';
                    const bv = bc ? bc.innerText.trim() : '';
                    const an = parseFloat(av), bn = parseFloat(bv);
                    const cmp = (!isNaN(an) && !isNaN(bn)) ? an - bn : av.localeCompare(bv);
                    return sortAsc ? cmp : -cmp;
                });
                rows.forEach(r => tbody.appendChild(r));
                // Update rank numbers
                rows.forEach((r, i) => { const td = r.querySelector('td'); if(td) td.textContent = i + 1; });
                document.querySelectorAll('#mentorMatrixTable .sortable-col i').forEach(ic => { ic.className = 'fas fa-sort'; ic.style.opacity = '.4'; });
                const icon = this.querySelector('i');
                if (icon) { icon.className = sortAsc ? 'fas fa-sort-up' : 'fas fa-sort-down'; icon.style.opacity = '1'; }
                mentorPage = 1; mentorRefresh();
            });
        });
    }

    // Pagination
    let mentorPage    = 1;
    const PAGE_SIZE   = 20;
    let mentorVisible = [];

    function mentorRefresh() {
        const q    = (document.getElementById('mentorMatrixFilter')?.value || '').toLowerCase().trim();
        const rows = Array.from(document.querySelectorAll('#mentorMatrixTable .mentor-row'));
        mentorVisible = rows.filter(r => !q || r.dataset.search.includes(q));
        const totalPages = Math.max(1, Math.ceil(mentorVisible.length / PAGE_SIZE));
        if (mentorPage > totalPages) mentorPage = totalPages;
        if (mentorPage < 1) mentorPage = 1;
        const start = (mentorPage - 1) * PAGE_SIZE;
        const end   = start + PAGE_SIZE;
        rows.forEach(r => { r.style.display = 'none'; });
        mentorVisible.slice(start, end).forEach(r => { r.style.display = ''; });
        const info    = document.getElementById('mentorPagerInfo');
        const summary = document.getElementById('mentorPagerSummary');
        const prev    = document.getElementById('mentorPrev');
        const next    = document.getElementById('mentorNext');
        if (info)    info.textContent    = 'Page ' + mentorPage + ' / ' + totalPages;
        if (summary) summary.textContent = Math.min(start + 1, mentorVisible.length) + '–' + Math.min(end, mentorVisible.length) + ' of ' + mentorVisible.length + (q ? ' filtered' : '') + ' mentors';
        if (prev)    prev.disabled       = mentorPage <= 1;
        if (next)    next.disabled       = mentorPage >= totalPages;
    }

    window.mentorPagerGo = function (dir) { mentorPage += dir; mentorRefresh(); };

    function boot() {
        initMentorCharts();
        initSort();
        const input = document.getElementById('mentorMatrixFilter');
        if (input) { input.addEventListener('input', function () { mentorPage = 1; mentorRefresh(); }); }
        mentorRefresh();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
</script>

