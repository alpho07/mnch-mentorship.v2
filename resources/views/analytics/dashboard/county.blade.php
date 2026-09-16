@extends('layouts.dashboard')

@section('title', $county->name . ' County Analytics')

@section('content')
<div class="container-fluid px-4">
    <!-- Header -->
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="h2 mb-1">{{ $county->name }} County Analytics</h1>
                @if(isset($selectedTraining) && $selectedTraining && $programs->count() > 0)
                    <p class="mb-0">Facilities participating in: <strong>{{ $programs->first()->title }}</strong></p>
                @else
                    <p class="mb-0">Training facilities and coverage analysis</p>
                @endif
            </div>
            
            <div class="d-flex gap-3 align-items-center flex-wrap">
                <span class="mode-indicator">
                    {{ $mode === 'training' ? 'Training Mode' : 'Mentorship Mode' }}
                </span>
                
                <select id="yearFilter" class="form-select" style="width: auto; display:none;">
                    <option value="" {{ empty($selectedYear) ? 'selected' : '' }}>All Years</option>
                    @foreach($availableYears ?? [] as $year)
                        <option value="{{ $year }}" {{ ($selectedYear ?? '') == $year ? 'selected' : '' }}>{{ $year }}</option>
                    @endforeach
                </select>
                
                @if(isset($selectedTraining) && $selectedTraining)
                <button type="button" class="btn btn-outline-secondary"  style="width: auto; display:none;" onclick="clearTrainingFilter()">
                    <i class="fas fa-times me-1"></i>Clear Filter
                </button>
                @endif
            </div>
        </div>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
         <div class="stats-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
            <div class="icon"><i class="fas fa-map-marker-alt"></i></div>
            <h3>{{ number_format($county->subcounties->count() ?? 0) }}</h3>
            <p>Subcounties</p>
        </div>
        <div class="stats-card">
            <div class="icon"><i class="fas fa-hospital"></i></div>
            <h3>{{ number_format(isset($facilities) ? $facilities->count() : $county->facilities->count()) }}</h3>
            <p>{{ isset($selectedTraining) && $selectedTraining ? 'Participating' : 'Total' }} Facilities</p>
        </div>
        <div class="stats-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
            <div class="icon"><i class="fas fa-chart-bar"></i></div>
            <h3>{{ number_format($programs->count() ?? 0) }}</h3>
            <p>{{ $mode === 'training' ? 'Trainings' : 'Mentorships' }}</p>
        </div>
        <div class="stats-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
            <div class="icon"><i class="fas fa-users"></i></div>
            <h3>{{ number_format(isset($facilities) ? $facilities->sum('participants_count') : ($programs->sum('county_participants') ?: $programs->sum('mentees_count'))) }}</h3>
            <p>Total {{ $mode === 'training' ? 'Participants' : 'Mentees' }}</p>
        </div>
    </div>

    <div class="row">
        @if(isset($facilities) && $facilities->count() > 0)
        <!-- Facilities List (when training program is selected) -->
        <div class="col-lg-8 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5>Participating Facilities</h5>
                    <small class="text-muted">Facilities participating in {{ $programs->first()->title ?? 'selected training program' }}</small>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        @foreach($facilities as $facility)
                        <div class="col-lg-6 col-md-12">
                            <div class="facility-card card h-100" data-facility-id="{{ $facility->id }}">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div>
                                            <h6 class="card-title mb-1">{{ $facility->name }}</h6>
                                            <small class="text-muted">{{ $facility->subcounty->name ?? 'N/A' }}</small>
                                        </div>
                                        <span class="badge bg-info">{{ $facility->facilityType->name ?? 'N/A' }}</span>
                                    </div>
                                    
                                    @if($facility->mfl_code)
                                    <div class="mb-2">
                                        <small class="text-muted d-block">MFL Code</small>
                                        <span class="fw-semibold">{{ $facility->mfl_code }}</span>
                                    </div>
                                    @endif
                                    
                                    <div class="row g-2 mb-3">
                                        <div class="col-6">
                                            <small class="text-muted d-block">Participants</small>
                                            <span class="fw-semibold fs-5 text-primary">{{ number_format($facility->participants_count ?? 0) }}</span>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted d-block">Status</small>
                                            <span class="badge bg-success">Active</span>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted">View participants</small>
                                        <i class="fas fa-arrow-right text-primary"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
        @else
        <!-- Programs List (when no specific training is selected) -->
        <div class="col-lg-8 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5>{{ $mode === 'training' ? 'Trainings' : 'Mentorships' }}</h5>
                    <small class="text-muted">Click on a program to view participating facilities | Ordered by year (newest first)</small>
                </div>
                <div class="card-body">
                    @php
                        // Sort programs by year (descending - newest first)
                        $sortedPrograms = ($programs ?? collect())->sortByDesc(function($program) {
                            return $program->start_date ? \Carbon\Carbon::parse($program->start_date)->year : 0;
                        });
                    @endphp
                    @if($sortedPrograms->count() > 0)
                        <div class="row g-3">
                            @foreach($sortedPrograms as $program)
                            <div class="col-md-6">
                                <div class="program-card card h-100" data-program-id="{{ $program->id }}">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h6 class="card-title mb-1">{{ $program->title }}</h6>
                                            <span class="badge bg-primary">{{ $program->identifier ?? 'N/A' }}</span>
                                        </div>
                                        
                                        <div class="row g-2 mb-3">
                                            <div class="col-6">
                                                <small class="text-muted d-block">Year</small>
                                                <span class="fw-semibold">{{ $program->start_date ? \Carbon\Carbon::parse($program->start_date)->format('Y') : 'N/A' }}</span>
                                            </div>
                                            <div class="col-6">
                                                <small class="text-muted d-block">{{ $mode === 'training' ? 'Participants' : 'Mentees' }}</small>
                                                <span class="fw-semibold">{{ number_format($program->county_participants ?: $program->mentees_count) }}</span>
                                            </div>
                                        </div>
                                        
                                        @if($mode === 'mentorship' && $program->facility)
                                        <div class="mb-2">
                                            <small class="text-muted d-block">Facility</small>
                                            <span class="fw-semibold">{{ $program->facility->name }}</span>
                                        </div>
                                        @endif
                                        
                                        <div class="d-flex justify-content-between align-items-center">
                                            <small class="text-muted">Click to explore</small>
                                            <i class="fas fa-arrow-right text-primary"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    @else
                        <div class="empty-state">
                            <div class="icon"><i class="fas fa-file-alt"></i></div>
                            <h6>No Programs Found</h6>
                            <p>No {{ $mode }} programs available for {{ $selectedYear ?: 'the selected period' }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
        @endif

        <!-- Coverage -->
        <div class="col-lg-4 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5>Coverage Analysis</h5>
                    <small class="text-muted">Training coverage by category | Ordered by coverage (highest first)</small>
                </div>
                <div class="card-body">
                    <!-- Department Coverage -->
                    <div class="mb-4">
                        <h6 class="mb-3 text-gradient">By Department</h6>
                        @php
                            $sortedDepartments = ($coverageData['departmentCoverage'] ?? collect())
                                ->sortByDesc('coverage_percentage')
                                ->take(5);
                        @endphp
                        @foreach($sortedDepartments as $dept)
                        <div class="coverage-item">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="fw-semibold">{{ $dept->name }}</span>
                                <span class="badge bg-{{ $dept->coverage_percentage >= 70 ? 'success' : ($dept->coverage_percentage >= 40 ? 'warning' : 'danger') }}">
                                    {{ $dept->coverage_percentage }}%
                                </span>
                            </div>
                            <div class="progress mb-1">
                                <div class="progress-bar bg-{{ $dept->coverage_percentage >= 70 ? 'success' : ($dept->coverage_percentage >= 40 ? 'warning' : 'danger') }}" 
                                     style="width: {{ $dept->coverage_percentage }}%"></div>
                            </div>
                            <small class="text-muted">{{ $dept->trained_users }}/{{ $dept->county_users }} staff</small>
                        </div>
                        @endforeach
                    </div>

                    <!-- Cadre Coverage -->
                    <div class="mb-4">
                        <h6 class="mb-3 text-gradient">By Cadre</h6>
                        @php
                            $sortedCadres = ($coverageData['cadreCoverage'] ?? collect())
                                ->sortByDesc('coverage_percentage')
                                ->take(5);
                        @endphp
                        @foreach($sortedCadres as $cadre)
                        <div class="coverage-item">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="fw-semibold">{{ $cadre->name }}</span>
                                <span class="badge bg-{{ $cadre->coverage_percentage >= 70 ? 'success' : ($cadre->coverage_percentage >= 40 ? 'warning' : 'danger') }}">
                                    {{ $cadre->coverage_percentage }}%
                                </span>
                            </div>
                            <div class="progress mb-1">
                                <div class="progress-bar bg-{{ $cadre->coverage_percentage >= 70 ? 'success' : ($cadre->coverage_percentage >= 40 ? 'warning' : 'danger') }}" 
                                     style="width: {{ $cadre->coverage_percentage }}%"></div>
                            </div>
                            <small class="text-muted">{{ $cadre->trained_users }}/{{ $cadre->county_users }} staff</small>
                        </div>
                        @endforeach
                    </div>

                    <!-- Facility Type Coverage -->
                    <div>
                        <h6 class="mb-3 text-gradient">By Facility Type</h6>
                        @php
                            $sortedFacilityTypes = ($coverageData['facilityTypeCoverage'] ?? collect())
                                ->sortByDesc('coverage_percentage')
                                ->take(4);
                        @endphp
                        @foreach($sortedFacilityTypes as $type)
                        <div class="coverage-item">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="fw-semibold">{{ $type->name }}</span>
                                <span class="badge bg-{{ $type->coverage_percentage >= 70 ? 'success' : ($type->coverage_percentage >= 40 ? 'warning' : 'danger') }}">
                                    {{ $type->coverage_percentage }}%
                                </span>
                            </div>
                            <div class="progress mb-1">
                                <div class="progress-bar bg-{{ $type->coverage_percentage >= 70 ? 'success' : ($type->coverage_percentage >= 40 ? 'warning' : 'danger') }}" 
                                     style="width: {{ $type->coverage_percentage }}%"></div>
                            </div>
                            <small class="text-muted">{{ $type->facilities_with_training }}/{{ $type->county_facilities }} facilities</small>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('custom-styles')
.program-card, .facility-card {
    transition: all 0.3s ease;
    cursor: pointer;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    background: white;
    overflow: hidden;
    height: 100%;
}

.program-card:hover, .facility-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 28px rgba(0,0,0,0.15);
    border-color: #3b82f6;
}

.program-card .card-body, .facility-card .card-body {
    padding: 1.5rem;
    position: relative;
}

.program-card .card-body::before, .facility-card .card-body::before {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.1) 0%, rgba(29, 78, 216, 0.1) 100%);
    border-radius: 50%;
    transform: translate(20px, -20px);
}

.coverage-item {
    padding: 1rem;
    border-radius: 12px;
    background: white;
    border: 1px solid #e5e7eb;
    transition: all 0.2s;
    margin-bottom: 1rem;
    position: relative;
    overflow: hidden;
}

.coverage-item:hover {
    border-color: #3b82f6;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.coverage-item::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
}
@endsection

@section('page-scripts')
// Year filter
document.getElementById('yearFilter').addEventListener('change', function() {
    const url = new URL(window.location);
    if (this.value) {
        url.searchParams.set('year', this.value);
    } else {
        url.searchParams.delete('year');
    }
    url.searchParams.set('mode', '{{ $mode ?? "training" }}');
    
    // Maintain training filter if it exists
    const selectedTraining = '{{ $selectedTraining ?? "" }}';
    if (selectedTraining) {
        url.searchParams.set('training_id', selectedTraining);
    }
    
    window.location.href = url.toString();
});

// Clear training filter
function clearTrainingFilter() {
    const url = new URL(window.location);
    url.searchParams.delete('training_id');
    url.searchParams.set('mode', '{{ $mode ?? "training" }}');
    
    const currentYear = '{{ $selectedYear ?? "" }}';
    if (currentYear) {
        url.searchParams.set('year', currentYear);
    }
    
    window.location.href = url.toString();
}

@if(isset($facilities) && $facilities->count() > 0)
// Facility cards click (when training is selected)
document.querySelectorAll('.facility-card').forEach(card => {
    card.addEventListener('click', function() {
        const facilityId = this.dataset.facilityId;
        if (facilityId) {
            navigateToFacility(facilityId);
        }
    });
});

function navigateToFacility(facilityId) {
    const params = new URLSearchParams({
        mode: '{{ $mode ?? "training" }}'
    });
    
    const currentYear = '{{ $selectedYear ?? "" }}';
    if (currentYear) {
        params.set('year', currentYear);
    }
    
    // Navigate to facility participants for the selected training
    const selectedTraining = '{{ $selectedTraining ?? "" }}';
    window.location.href = `/analytics/dashboard/county/{{ $county->id }}/program/${selectedTraining}/facility/${facilityId}?${params.toString()}`;
}
@else
// Program cards click (when no training is selected)
document.querySelectorAll('.program-card').forEach(card => {
    card.addEventListener('click', function() {
        const programId = this.dataset.programId;
        if (programId) {
            navigateToProgram(programId);
        }
    });
});

function navigateToProgram(programId) {
    const params = new URLSearchParams({
        mode: '{{ $mode ?? "training" }}'
    });
    
    const currentYear = '{{ $selectedYear ?? "" }}';
    if (currentYear) {
        params.set('year', currentYear);
    }
    
    window.location.href = `/analytics/dashboard/county/{{ $county->id }}/program/${programId}?${params.toString()}`;
}
@endif
@endsection