@php
    $uniqueAssessments      = $summaryStats['uniqueAssessments'] ?? 0;
    $facilitiesAssessed     = $summaryStats['facilitiesAssessed'] ?? 0;
    $allFacilities          = $summaryStats['allFacilities'] ?? 0;
    $avgScore               = $summaryStats['avgScore'] ?? 0;
    $withSkillsLab          = $summaryStats['withSkillsLab'] ?? 0;
    $eligible               = $summaryStats['eligible'] ?? 0;
    $readyForMentorship     = $summaryStats['readyForMentorship'] ?? 0;
    $facilityCoverage       = $summaryStats['facilityCoveragePercent'] ?? 0;
    $yoyChange              = $summaryStats['yoyChange'] ?? 0;
    $avgColor               = $avgScore >= 80 ? 'up' : ($avgScore >= 50 ? 'flat' : 'down');
    $withMentorships        = $summaryStats['withMentorships'] ?? 0;
    $mentorshipCoverage     = $summaryStats['mentorshipCoverage'] ?? 0;
@endphp

{{-- ████████ ASSESSMENT FILTERS ████████ --}}
<div class="dash-section" data-aos="fade-up" data-aos-delay="50">
    <div class="collapse show" id="assessmentFilters">
        <div class="filter-card">
            <form method="GET" action="{{ route('analytics.dashboard.index') }}" class="row g-2 align-items-end">
                <input type="hidden" name="mode" value="assessment">
                <div class="col-lg-2 col-md-4 col-sm-6">
                    <label class="form-label">Year</label>
                    <select name="year" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Years</option>
                        @foreach($availableYears as $yr)
                            <option value="{{ $yr }}" {{ $selectedYear == $yr ? 'selected' : '' }}>{{ $yr }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2 col-md-4 col-sm-6">
                    <label class="form-label">County</label>
                    <select name="county_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Counties</option>
                        @foreach($counties ?? [] as $county)
                            <option value="{{ $county->id }}" {{ ($selectedCounty ?? '') == $county->id ? 'selected' : '' }}>{{ $county->name }}</option>
                        @endforeach
                    </select>
                </div>
                @if(($subcounties ?? collect())->isNotEmpty())
                <div class="col-lg-2 col-md-4 col-sm-6">
                    <label class="form-label">Subcounty</label>
                    <select name="subcounty_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Subcounties</option>
                        @foreach($subcounties as $sc)
                            <option value="{{ $sc->id }}" {{ ($selectedSubcounty ?? '') == $sc->id ? 'selected' : '' }}>{{ $sc->name }}</option>
                        @endforeach
                    </select>
                </div>
                @endif
                @if(($facilities ?? collect())->isNotEmpty())
                <div class="col-lg-2 col-md-4 col-sm-6">
                    <label class="form-label">Facility</label>
                    <select name="facility_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Facilities</option>
                        @foreach($facilities as $fac)
                            <option value="{{ $fac->id }}" {{ ($selectedFacility ?? '') == $fac->id ? 'selected' : '' }}>{{ $fac->name }}</option>
                        @endforeach
                    </select>
                </div>
                @endif
                <div class="col-lg-2 col-md-4 col-sm-6">
                    <label class="form-label">Assessment Type</label>
                    <select name="assessment_type" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Types</option>
                        <option value="baseline" {{ ($selectedAssessmentType ?? '') === 'baseline' ? 'selected' : '' }}>Baseline</option>
                        <option value="midline"  {{ ($selectedAssessmentType ?? '') === 'midline'  ? 'selected' : '' }}>Midline</option>
                        <option value="endline"  {{ ($selectedAssessmentType ?? '') === 'endline'  ? 'selected' : '' }}>Endline</option>
                    </select>
                </div>
                <div class="col-lg-2 col-md-4 col-sm-6 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary">Apply</button>
                    <a href="{{ route('analytics.dashboard.index', ['mode' => 'assessment']) }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ████████ KPI STRIP ████████ --}}
<div class="kpi-strip-wrap">
    @php $kpiDelay = 0; @endphp
    <div class="kpi-strip" style="grid-template-columns:repeat(6,1fr);">
        <div class="kpi-card" data-aos="fade-up" data-aos-delay="{{ $kpiDelay }}">
            <div class="kpi-icon"><i class="fas fa-hospital"></i></div>
            <div class="kpi-value counter-animate" data-counter="{{ $uniqueAssessments }}">0</div>
            <div class="kpi-label">Facility Assessments</div>
            @if($yoyChange > 0)
                <span class="kpi-trend up"><i class="fas fa-arrow-up"></i> {{ $yoyChange }}% YoY</span>
            @elseif($yoyChange < 0)
                <span class="kpi-trend down"><i class="fas fa-arrow-down"></i> {{ abs($yoyChange) }}% YoY</span>
            @else
                <span class="kpi-trend flat"><i class="fas fa-building"></i> {{ $facilityCoverage }}% coverage</span>
            @endif
        </div>
        @php $kpiDelay += 75; @endphp
        <div class="kpi-card" data-aos="fade-up" data-aos-delay="{{ $kpiDelay }}">
            <div class="kpi-icon"><i class="fas fa-star-half-alt"></i></div>
            <div class="kpi-value counter-animate" data-counter="{{ $avgScore }}" data-suffix="%" data-decimals="1">0%</div>
            <div class="kpi-label">Avg Score</div>
            <span class="kpi-trend {{ $avgColor }}">
                {{ $avgScore >= 80 ? 'Good' : ($avgScore >= 50 ? 'Fair' : 'Needs Work') }}
            </span>
        </div>
        @php $kpiDelay += 75; @endphp
        <div class="kpi-card" data-aos="fade-up" data-aos-delay="{{ $kpiDelay }}">
            <div class="kpi-icon"><i class="fas fa-flask"></i></div>
            <div class="kpi-value counter-animate" data-counter="{{ $withSkillsLab }}">0</div>
            <div class="kpi-label">Have Skills Lab</div>
            @if($facilitiesAssessed > 0)
                <span class="kpi-trend flat">{{ round(($withSkillsLab / $facilitiesAssessed) * 100) }}% of assessed</span>
            @endif
        </div>
        @php $kpiDelay += 75; @endphp
        <div class="kpi-card" data-aos="fade-up" data-aos-delay="{{ $kpiDelay }}">
            <div class="kpi-icon"><i class="fas fa-check-double"></i></div>
            <div class="kpi-value counter-animate" data-counter="{{ $eligible }}">0</div>
            <div class="kpi-label">Eligible for Mentorship</div>
            @php $pendingFeedback = $readyForMentorship - $eligible; @endphp
            @if($pendingFeedback > 0)
                <span class="kpi-trend flat" title="Have a skills lab/room but feedback not yet given">{{ $pendingFeedback }} pending feedback</span>
            @elseif($readyForMentorship > 0)
                <span class="kpi-trend up">All ready facilities eligible</span>
            @endif
        </div>
        @php $kpiDelay += 75; @endphp
        <div class="kpi-card" data-aos="fade-up" data-aos-delay="{{ $kpiDelay }}">
            <div class="kpi-icon"><i class="fas fa-handshake"></i></div>
            <div class="kpi-value counter-animate" data-counter="{{ $withMentorships }}">0</div>
            <div class="kpi-label">With Mentorships</div>
            @if($facilitiesAssessed > 0)
                <span class="kpi-trend flat">{{ $mentorshipCoverage }}% of assessed</span>
            @endif
        </div>
        @php $kpiDelay += 75; @endphp
        <div class="kpi-card" data-aos="fade-up" data-aos-delay="{{ $kpiDelay }}">
            <div class="kpi-icon"><i class="fas fa-percentage"></i></div>
            <div class="kpi-value counter-animate" data-counter="{{ $mentorshipCoverage }}" data-suffix="%" data-decimals="1">0%</div>
            <div class="kpi-label">Mentorship Coverage</div>
            @php $uncovered = $facilitiesAssessed - $withMentorships; @endphp
            @if($uncovered > 0)
                <span class="kpi-trend down">{{ $uncovered }} without</span>
            @else
                <span class="kpi-trend up">Full coverage</span>
            @endif
        </div>
    </div>
</div>

{{-- ████████ INSIGHTS ████████ --}}
@if(!empty($insights))
<div class="dash-section" data-aos="fade-up" data-aos-delay="100">
    <div class="section-title"><i class="fas fa-lightbulb"></i> Insights</div>
    <div class="insights-grid">
        @foreach($insights as $index => $insight)
            <div class="insight-card {{ $insight['type'] }}" data-aos="fade-up" data-aos-delay="{{ $index * 75 }}">
                <div class="insight-icon"><i class="fas fa-{{ $insight['icon'] }}"></i></div>
                <div class="insight-text">{{ $insight['text'] }}</div>
            </div>
        @endforeach
    </div>
</div>
@endif

{{-- ████████ SKILLS LAB / ROOM × MENTORSHIP READINESS ████████ --}}
@php
    $sms = $skillsMentorshipStatus ?? ['total' => 0, 'goodProgress' => 0, 'needsMentorship' => 0, 'needsSetup' => 0, 'goodProgressPercent' => 0, 'needsMentorshipPercent' => 0, 'needsSetupPercent' => 0];
@endphp
@if($sms['total'] > 0)
<div class="dash-section" data-aos="fade-up" data-aos-delay="120">
    <div class="section-title"><i class="fas fa-flask"></i> Skills Lab/Room Readiness vs Mentorship</div>
    <div class="insights-grid">
        <div class="insight-card success clickable-insight" data-aos="fade-up" data-aos-delay="0"
             onclick="window.dispatchEvent(new CustomEvent('filter-readiness', {detail:{ready:'yes',mentorship:'yes'}}))"
             title="Click to view these facilities">
            <div class="insight-icon"><i class="fas fa-check-circle"></i></div>
            <div class="insight-text">
                <strong>{{ $sms['goodProgress'] }}</strong> ({{ $sms['goodProgressPercent'] }}%) have a skills lab/room <strong>and</strong> a live mentorship — good progress.
                <div class="insight-cta">View facilities <i class="fas fa-arrow-right"></i></div>
            </div>
        </div>
        <div class="insight-card warning clickable-insight" data-aos="fade-up" data-aos-delay="75"
             onclick="window.dispatchEvent(new CustomEvent('filter-readiness', {detail:{ready:'yes',mentorship:'no'}}))"
             title="Click to view these facilities">
            <div class="insight-icon"><i class="fas fa-exclamation-triangle"></i></div>
            <div class="insight-text">
                <strong>{{ $sms['needsMentorship'] }}</strong> ({{ $sms['needsMentorshipPercent'] }}%) have a skills lab/room but <strong>no</strong> mentorship yet — a missed opportunity.
                <div class="insight-cta">View facilities <i class="fas fa-arrow-right"></i></div>
            </div>
        </div>
        <div class="insight-card danger clickable-insight" data-aos="fade-up" data-aos-delay="150"
             onclick="window.dispatchEvent(new CustomEvent('filter-readiness', {detail:{ready:'no',mentorship:'all'}}))"
             title="Click to view these facilities">
            <div class="insight-icon"><i class="fas fa-exclamation-circle"></i></div>
            <div class="insight-text">
                <strong>{{ $sms['needsSetup'] }}</strong> ({{ $sms['needsSetupPercent'] }}%) have <strong>no</strong> skills lab or room — urgent need to set one up.
                <div class="insight-cta">View facilities <i class="fas fa-arrow-right"></i></div>
            </div>
        </div>
    </div>
</div>
@endif

{{-- ████████ CHARTS ROW 1 ████████ --}}
<div class="dash-section" data-aos="fade-up" data-aos-delay="150">
    <div class="chart-row">
        <div class="chart-2-3">
            <div class="chart-card">
                <div class="chart-card-header">
                    <h6><i class="fas fa-chart-bar"></i> Assessments Over Time</h6>
                    <small>Last 12 months by type</small>
                </div>
                <div class="chart-card-body"><div class="chart-canvas-wrap"><canvas id="assessmentTrendChart"></canvas></div></div>
            </div>
        </div>
        <div class="chart-1-3">
            <div class="chart-card">
                <div class="chart-card-header">
                    <h6><i class="fas fa-circle-notch"></i> Grade Distribution</h6>
                    <small>Completed assessments</small>
                </div>
                <div class="chart-card-body">
                    <div class="chart-canvas-wrap"><canvas id="gradeDistChart"></canvas></div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ████████ CHARTS ROW 2 ████████ --}}
<div class="dash-section" data-aos="fade-up" data-aos-delay="200">
    <div class="chart-row">
        <div class="chart-half">
            <div class="chart-card">
                <div class="chart-card-header">
                    <h6><i class="fas fa-layer-group"></i> Section Score Averages</h6>
                    <small>Avg % across all completed assessments</small>
                </div>
                <div class="chart-card-body"><div class="chart-canvas-wrap"><canvas id="sectionScoreChart"></canvas></div></div>
            </div>
        </div>
        <div class="chart-half">
            <div class="chart-card">
                <div class="chart-card-header">
                    <h6><i class="fas fa-tasks"></i> Assessment Status</h6>
                    <small>Draft / In Progress / Completed</small>
                </div>
                <div class="chart-card-body">
                    <div class="chart-canvas-wrap"><canvas id="statusChart"></canvas></div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ████████ FACILITIES READINESS TABLE ████████ --}}
<div class="dash-section" data-aos="fade-up" data-aos-delay="250" id="readinessSection" x-data="{
    filterSkillsLab: 'all',
    filterRoom: 'all',
    filterEligibility: 'all',
    filterFeedback: 'all',
    filterType: 'all',
    filterMentorships: 'all',
    filterTraining: 'all',
    filterFacilityReady: 'all',
    searchFacility: '',
    page: 1,
    perPage: 25,

    get filteredRows() {
        return Array.from(this.$refs.tableBody.querySelectorAll('tr[data-skills-lab]')).filter(row => this.matches(row));
    },

    get paginatedRows() {
        const start = (this.page - 1) * this.perPage;
        return this.filteredRows.slice(start, start + this.perPage);
    },

    get totalPages() {
        return Math.ceil(this.filteredRows.length / this.perPage) || 1;
    },

    get pages() {
        const total = this.totalPages;
        const current = this.page;
        const pages = [];
        for (let i = 1; i <= total; i++) {
            if (i === 1 || i === total || (i >= current - 1 && i <= current + 1)) {
                pages.push(i);
            } else if (pages[pages.length - 1] !== '...') {
                pages.push('...');
            }
        }
        return pages;
    },

    goToPage(p) {
        if (p === '...' || p < 1 || p > this.totalPages) return;
        this.page = p;
        this.$refs.tableBody.scrollIntoView({ behavior: 'smooth', block: 'start' });
    },

    matches(row) {
        if (this.filterSkillsLab !== 'all' && row.dataset.skillsLab !== this.filterSkillsLab) return false;
        if (this.filterRoom !== 'all' && row.dataset.room !== this.filterRoom) return false;
        if (this.filterEligibility !== 'all' && row.dataset.eligibility !== this.filterEligibility) return false;
        if (this.filterFeedback !== 'all' && row.dataset.feedback !== this.filterFeedback) return false;
        if (this.filterType !== 'all' && row.dataset.atype !== this.filterType) return false;
        if (this.filterMentorships !== 'all' && row.dataset.mentorships !== this.filterMentorships) return false;
        if (this.filterTraining !== 'all' && row.dataset.training !== this.filterTraining) return false;
        if (this.filterFacilityReady !== 'all' && row.dataset.facilityReady !== this.filterFacilityReady) return false;
        if (this.searchFacility.trim() !== '' && !row.dataset.facility.includes(this.searchFacility.toLowerCase().trim())) return false;
        return true;
    },

    applyReadinessFilter(detail) {
        this.filterSkillsLab = 'all';
        this.filterRoom = 'all';
        this.filterEligibility = 'all';
        this.filterFeedback = 'all';
        this.filterType = 'all';
        this.filterTraining = 'all';
        this.searchFacility = '';
        this.filterFacilityReady = detail.ready;
        this.filterMentorships = detail.mentorship;
        this.page = 1;
        this.$nextTick(() => document.getElementById('readinessSection').scrollIntoView({ behavior: 'smooth', block: 'start' }));
    },

    init() {
        this.$watch('filterSkillsLab', () => this.page = 1);
        this.$watch('filterRoom', () => this.page = 1);
        this.$watch('filterEligibility', () => this.page = 1);
        this.$watch('filterFeedback', () => this.page = 1);
        this.$watch('filterType', () => this.page = 1);
        this.$watch('filterMentorships', () => this.page = 1);
        this.$watch('filterTraining', () => this.page = 1);
        this.$watch('filterFacilityReady', () => this.page = 1);
        this.$watch('searchFacility', () => this.page = 1);
        this.$watch('perPage', () => this.page = 1);
    }
}" x-on:filter-readiness.window="applyReadinessFilter($event.detail)">
    <div class="section-title d-flex align-items-center justify-content-between flex-wrap gap-2">
        <span><i class="fas fa-table"></i> Facilities Readiness &amp; Mentorship Eligibility</span>
        <a href="{{ route('analytics.dashboard.assessment.export-readiness', array_filter(['year' => $selectedYear, 'county_id' => $selectedCounty, 'subcounty_id' => $selectedSubcounty ?? null, 'facility_id' => $selectedFacility ?? null, 'assessment_type' => $selectedAssessmentType])) }}"
           class="btn btn-sm btn-outline-primary"
           style="font-size:.78rem;font-weight:600;border-color:var(--teal);color:var(--teal);"
           title="Export this table as a PDF report">
            <i class="fas fa-file-pdf me-1"></i> Export PDF
        </a>
    </div>

    {{-- Table filters --}}
    <div class="filter-card mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label">Search Facility</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text" style="background:none;border-right:none;"><i class="fas fa-search" style="font-size:.72rem;color:var(--gray-400)"></i></span>
                    <input type="text"
                           class="form-control form-control-sm"
                           placeholder="Facility name..."
                           x-model="searchFacility"
                           style="border-left:none;min-width:160px;">
                </div>
            </div>
            <div class="col-auto">
                <label class="form-label">Skills Lab</label>
                <select class="form-select form-select-sm" x-model="filterSkillsLab">
                    <option value="all">All</option>
                    <option value="yes">Yes</option>
                    <option value="no">No</option>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label">Room</label>
                <select class="form-select form-select-sm" x-model="filterRoom">
                    <option value="all">All</option>
                    <option value="yes">Yes</option>
                    <option value="no">No</option>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label">Eligibility</label>
                <select class="form-select form-select-sm" x-model="filterEligibility">
                    <option value="all">All</option>
                    <option value="eligible">Eligible</option>
                    <option value="partial">Partial</option>
                    <option value="not_eligible">Not Eligible</option>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label">Facility Readiness</label>
                <select class="form-select form-select-sm" x-model="filterFacilityReady">
                    <option value="all">All</option>
                    <option value="yes">Has Skills Lab/Room</option>
                    <option value="no">No Skills Lab/Room</option>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label">Feedback</label>
                <select class="form-select form-select-sm" x-model="filterFeedback">
                    <option value="all">All</option>
                    <option value="given">Given</option>
                    <option value="pending">Pending</option>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label">Type</label>
                <select class="form-select form-select-sm" x-model="filterType">
                    <option value="all">All Types</option>
                    <option value="baseline">Baseline</option>
                    <option value="midline">Midline</option>
                    <option value="endline">Endline</option>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label">Mentorships</label>
                <select class="form-select form-select-sm" x-model="filterMentorships">
                    <option value="all">All</option>
                    <option value="yes">With Mentorships</option>
                    <option value="no">No Mentorships</option>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label">Has Been Trained</label>
                <select class="form-select form-select-sm" x-model="filterTraining">
                    <option value="all">All</option>
                    <option value="yes">Yes</option>
                    <option value="no">No</option>
                    <option value="not_eligible">Not Eligible</option>
                </select>
            </div>
        </div>
    </div>

    <div class="chart-card" style="overflow-x:auto;">
        <table class="analytics-table" id="readinessTable" style="font-size:.75rem;white-space:nowrap;">
            <thead>
                <tr style="font-size:.68rem;text-transform:uppercase;letter-spacing:.4px;">
                    <th>Facility</th>
                    <th>Subcounty / County</th>
                    <th>Level</th>
                    <th>Latest Assessment</th>
                    <th>Assessed By</th>
                    <th>Skills Lab</th>
                    <th>Room</th>
                    <th>Assessment Feedback</th>
                    <th>Training Eligibility</th>
                    <th>Has Been Trained</th>
                    <th>Mentorships</th>
                </tr>
            </thead>
            <tbody x-ref="tableBody">
                @foreach($facilitiesReadiness as $assessment)
                    @php
                        $slKey       = $assessment->has_skills_lab ? 'yes' : 'no';
                        $rmKey       = $assessment->has_room ? 'yes' : 'no';
                        $fbKey       = $assessment->feedback_given ? 'given' : 'pending';
                        $eligKey     = $assessment->eligibility_status;
                        $msKey       = $assessment->mentorship_count > 0 ? 'yes' : 'no';
                        $readyKey    = ($assessment->has_skills_lab || $assessment->has_room) ? 'yes' : 'no';
                        $trainingKey = $eligKey !== 'eligible' ? 'not_eligible' : ($assessment->has_prior_training ? 'yes' : 'no');
                        $eligStyle   = match($eligKey) {
                            'eligible' => 'background:#D1FAE5;color:#065F46',
                            'partial'  => 'background:#FEF3C7;color:#92400E',
                            default    => 'background:#FEE2E2;color:#991B1B',
                        };
                        $eligLabel   = match($eligKey) {
                            'eligible' => '🟢 Eligible',
                            'partial'  => '🟡 Partial',
                            default    => '🔴 Not Eligible',
                        };
                    @endphp
                    <tr
                        x-show="paginatedRows.includes($el)"
                        data-skills-lab="{{ $slKey }}"
                        data-room="{{ $rmKey }}"
                        data-eligibility="{{ $eligKey }}"
                        data-feedback="{{ $fbKey }}"
                        data-atype="{{ $assessment->assessment_type }}"
                        data-mentorships="{{ $msKey }}"
                        data-training="{{ $trainingKey }}"
                        data-facility-ready="{{ $readyKey }}"
                        data-facility="{{ strtolower($assessment->facility->name) }}"
                    >
                        <td>
                            <div style="font-weight:600;color:var(--gray-800);white-space:normal;max-width:160px;">{{ $assessment->facility->name }}</div>
                            <span class="badge bg-secondary" style="font-size:.62rem;">{{ $assessment->facility->mfl_code }}</span>
                        </td>
                        <td>
                            <div>{{ $assessment->facility->subcounty->name ?? '—' }}</div>
                            <div style="color:var(--gray-500);font-size:.7rem;">{{ $assessment->facility->subcounty->county->name ?? '—' }}</div>
                        </td>
                        <td>
                            @if($assessment->facility->facilityLevel)
                                <span class="badge" style="background:var(--teal-50);color:var(--teal-dark);font-size:.65rem;">{{ $assessment->facility->facilityLevel->name }}</span>
                            @else
                                <span style="color:var(--gray-400)">—</span>
                            @endif
                        </td>
                        <td>
                            <div>{{ $assessment->assessment_date->format('d M Y') }}</div>
                            <span class="badge" style="font-size:.65rem;background:{{ $assessment->assessment_type === 'baseline' ? 'rgba(0,151,167,.12)' : ($assessment->assessment_type === 'midline' ? 'rgba(245,158,11,.12)' : 'rgba(139,92,246,.12)') }};color:{{ $assessment->assessment_type === 'baseline' ? 'var(--teal-dark)' : ($assessment->assessment_type === 'midline' ? '#92400E' : '#5B21B6') }}">
                                {{ ucfirst($assessment->assessment_type) }}
                            </span>
                            @if($assessment->status === 'completed')
                                <div style="margin-top:.25rem;display:flex;gap:.25rem;flex-wrap:wrap;">
                                    <a href="/admin/assessments/{{ $assessment->id }}/summary"
                                       target="_blank"
                                       style="font-size:.65rem;font-weight:600;color:var(--teal);text-decoration:none;display:inline-flex;align-items:center;gap:.2rem;padding:.1rem .35rem;border-radius:5px;background:var(--teal-50);border:1px solid var(--teal-100);"
                                       title="View Assessment Summary">
                                        <i class="fas fa-file-alt"></i> View
                                    </a>
                                    <a href="{{ route('assessment.executive', $assessment->id) }}"
                                       target="_blank"
                                       style="font-size:.65rem;font-weight:600;color:#7c3aed;text-decoration:none;display:inline-flex;align-items:center;gap:.2rem;padding:.1rem .35rem;border-radius:5px;background:#f5f3ff;border:1px solid #ddd6fe;"
                                       title="Executive Summary Dashboard">
                                        <i class="fas fa-chart-pie"></i> Exec
                                    </a>
                                </div>
                            @endif
                        </td>
                        <td style="color:var(--gray-700);">{{ $assessment->assessor_name }}</td>
                        <td>
                            @if($assessment->has_skills_lab)
                                <span class="badge" style="font-size:.65rem;background:#D1FAE5;color:#065F46"><i class="fas fa-check me-1"></i>Yes</span>
                            @else
                                <span class="badge" style="font-size:.65rem;background:#FEE2E2;color:#991B1B"><i class="fas fa-times me-1"></i>No</span>
                            @endif
                        </td>
                        <td>
                            @if($assessment->has_skills_lab)
                                <span style="color:var(--gray-400);font-size:.72rem">N/A</span>
                            @elseif($assessment->has_room)
                                <span class="badge" style="font-size:.65rem;background:#D1FAE5;color:#065F46"><i class="fas fa-check me-1"></i>Yes</span>
                            @else
                                <span class="badge" style="font-size:.65rem;background:#FEE2E2;color:#991B1B"><i class="fas fa-times me-1"></i>No</span>
                            @endif
                        </td>
                        <td>
                            @if($assessment->feedback_given)
                                <div style="color:#065F46;white-space:normal;max-width:130px;">
                                    <i class="fas fa-check-circle text-success me-1"></i>{{ $assessment->feedbackGivenBy->name ?? 'System' }}
                                    @if($assessment->feedback_given_at)
                                        <div style="color:var(--gray-500);font-size:.68rem;">{{ $assessment->feedback_given_at->format('d M Y') }}</div>
                                    @endif
                                </div>
                            @else
                                <span class="badge" style="font-size:.65rem;background:#FEF3C7;color:#92400E"><i class="fas fa-clock me-1"></i>Pending</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge" style="font-size:.65rem;{{ $eligStyle }}">{{ $eligLabel }}</span>
                        </td>
                        <td>
                            @if($eligKey !== 'eligible')
                                <span class="badge" style="font-size:.65rem;background:#E5E7EB;color:#6B7280">Not Eligible</span>
                            @elseif($assessment->has_prior_training)
                                <span class="badge" style="font-size:.65rem;background:#D1FAE5;color:#065F46"><i class="fas fa-check me-1"></i>Yes</span>
                            @else
                                <span class="badge" style="font-size:.65rem;background:#FEE2E2;color:#991B1B"><i class="fas fa-times me-1"></i>No</span>
                            @endif
                        </td>
                        <td style="text-align:center;">
                            @if($assessment->mentorship_count > 0)
                                <a href="{{ route('analytics.dashboard.facility.mentorship-breakdown', ['facility' => $assessment->facility_id, 'year' => $selectedYear]) }}"
                                   style="color:var(--teal);font-weight:700;text-decoration:none;">
                                    {{ $assessment->mentorship_count }}
                                    <i class="fas fa-external-link-alt ms-1" style="font-size:.6rem"></i>
                                </a>
                            @else
                                <span style="color:var(--gray-400)">0</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
                <tr x-show="filteredRows.length === 0">
                    <td colspan="11" style="text-align:center;padding:2rem;color:var(--gray-500)">
                        <i class="fas fa-clipboard-list fa-2x mb-2 d-block"></i>
                        No assessed facilities found for the current filters.
                    </td>
                </tr>
            </tbody>
        </table>

        {{-- Client-side pagination --}}
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 px-3 py-2 border-top" style="background:#fff;" x-show="filteredRows.length > 0">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <select class="form-select form-select-sm" x-model="perPage" style="width:auto;min-width:70px;">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
                <small class="text-muted" style="font-size:.75rem;">
                    Showing <span x-text="filteredRows.length ? ((page - 1) * perPage) + 1 : 0"></span>
                    – <span x-text="Math.min(page * perPage, filteredRows.length)"></span>
                    of <span x-text="filteredRows.length"></span>
                </small>
            </div>
            <nav aria-label="Facilities readiness pagination">
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item" :class="{ disabled: page === 1 }">
                        <a class="page-link" href="#" @click.prevent="goToPage(page - 1)">Previous</a>
                    </li>
                    <template x-for="p in pages" :key="p">
                        <li class="page-item" :class="{ active: p === page, disabled: p === '...' }">
                            <a class="page-link" href="#" @click.prevent="goToPage(p)" x-text="p"></a>
                        </li>
                    </template>
                    <li class="page-item" :class="{ disabled: page === totalPages }">
                        <a class="page-link" href="#" @click.prevent="goToPage(page + 1)">Next</a>
                    </li>
                </ul>
            </nav>
        </div>
    </div>
</div>

{{-- ████████ CHART JS ████████ --}}
@push('scripts')
<script>
(function () {
    const pct = (value, ctx) => {
        const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
        return total > 0 && value > 0 ? Math.round((value / total) * 100) + '%' : '';
    };

    const trendChart = document.getElementById('assessmentTrendChart');
    if (trendChart) {
        new Chart(trendChart, {
            type: 'bar',
            data: {
                labels: {!! json_encode(array_column($chartData['monthlyTrend'], 'short')) !!},
                datasets: [
                    { label: 'Baseline', data: {!! json_encode(array_column($chartData['monthlyTrend'], 'baseline')) !!}, backgroundColor: '#0097A7' },
                    { label: 'Midline',  data: {!! json_encode(array_column($chartData['monthlyTrend'], 'midline'))  !!}, backgroundColor: '#F59E0B' },
                    { label: 'Endline',  data: {!! json_encode(array_column($chartData['monthlyTrend'], 'endline'))  !!}, backgroundColor: '#8B5CF6' },
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top' },
                    datalabels: {
                        display: true, anchor: 'end', align: 'top', offset: 2,
                        color: '#374151', font: { size: 10, weight: '600' },
                        formatter: (value) => value > 0 ? value.toLocaleString() : '',
                    },
                },
                scales: { x: { stacked: false }, y: { beginAtZero: true, stacked: false } },
            }
        });
    }

    const gradeChart = document.getElementById('gradeDistChart');
    if (gradeChart) {
        new Chart(gradeChart, {
            type: 'doughnut',
            data: {
                labels: ['Good (≥80%)', 'Fair (50–79%)', 'Poor (<50%)'],
                datasets: [{ data: [{{ $chartData['gradeDistribution']['green'] }}, {{ $chartData['gradeDistribution']['yellow'] }}, {{ $chartData['gradeDistribution']['red'] }}], backgroundColor: ['#10B981','#F59E0B','#EF4444'], borderWidth: 2 }]
            },
            options: {
                responsive: true, maintainAspectRatio: false, cutout: '65%',
                plugins: {
                    legend: { position: 'bottom' },
                    datalabels: {
                        display: true, color: '#fff', font: { size: 11, weight: '700' },
                        formatter: pct,
                    },
                },
            }
        });
    }

    const sectionChart = document.getElementById('sectionScoreChart');
    if (sectionChart) {
        new Chart(sectionChart, {
            type: 'bar',
            data: {
                labels: {!! json_encode($chartData['sectionAverages']->pluck('name')->toArray()) !!},
                datasets: [{
                    label: 'Avg Score (%)',
                    data: {!! json_encode($chartData['sectionAverages']->pluck('percentage')->toArray()) !!},
                    backgroundColor: {!! json_encode($chartData['sectionAverages']->pluck('color')->toArray()) !!},
                }]
            },
            options: {
                indexAxis: 'y', responsive: true, maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    datalabels: {
                        display: true, anchor: 'end', align: 'end', offset: 4,
                        color: '#374151', font: { size: 10, weight: '600' },
                        formatter: (value) => value > 0 ? value + '%' : '',
                    },
                },
                layout: { padding: { right: 30 } },
                scales: { x: { beginAtZero: true, max: 100 } },
            }
        });
    }

    const statusChart = document.getElementById('statusChart');
    if (statusChart) {
        new Chart(statusChart, {
            type: 'doughnut',
            data: {
                labels: ['Completed', 'In Progress', 'Draft'],
                datasets: [{ data: [{{ $chartData['statusBreakdown']['completed'] }}, {{ $chartData['statusBreakdown']['in_progress'] }}, {{ $chartData['statusBreakdown']['draft'] }}], backgroundColor: ['#0097A7','#F59E0B','#94A3B8'], borderWidth: 2 }]
            },
            options: {
                responsive: true, maintainAspectRatio: false, cutout: '65%',
                plugins: {
                    legend: { position: 'bottom' },
                    datalabels: {
                        display: true, color: '#fff', font: { size: 11, weight: '700' },
                        formatter: pct,
                    },
                },
            }
        });
    }
})();
</script>
@endpush
