@extends('layouts.dashboard')

@section('title', $program->title . ' - County Program Analytics')

@section('content')
<div class="container-fluid px-4">
    <!-- Header -->
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <h1 class="h2 mb-1">{{ $program->title }}</h1>
                <p class="mb-2">Training Program Analytics | {{ $county->name }} County</p>
                <div class="d-flex gap-3 flex-wrap">
                    @if($program->identifier)
                    <span class="badge bg-white text-dark">ID: {{ $program->identifier }}</span>
                    @endif
                    @if($program->start_date)
                    <span class="badge bg-white text-dark">Year: {{ \Carbon\Carbon::parse($program->start_date)->format('Y') }}</span>
                    @endif
                    <span class="badge bg-white text-dark">Training Program</span>
                </div>
            </div>
            
         
        </div>
    </div>

    <!-- Program Statistics -->
    <div class="stats-grid">
        <div class="stats-card">
            <div class="icon"><i class="fas fa-hospital"></i></div>
            <h3>{{ number_format($facilities->count() ?? 0) }}</h3>
            <p>Participating Facilities</p>
        </div>
        <div class="stats-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
            <div class="icon"><i class="fas fa-users"></i></div>
            <h3>{{ number_format($programStats['totalParticipants'] ?? 0) }}</h3>
            <p>Total Participants</p>
        </div>
        <div class="stats-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
            <div class="icon"><i class="fas fa-calendar"></i></div>
            <h3>{{ $program->start_date ? \Carbon\Carbon::parse($program->start_date)->format('M j, Y') : 'N/A' }}</h3>
            <p>Start Date</p>
        </div>
        <div class="stats-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
            <div class="icon"><i class="fas fa-check-circle"></i></div>
            @php
                $completionRate = ($facilities->sum('participants_count') > 0) ? 
                    round(($facilities->where('completion_status', 'completed')->sum('participants_count') / $facilities->sum('participants_count')) * 100, 1) : 0;
            @endphp
            <h3>{{ $program->status ? ucfirst($program->status) : 'Active' }}</h3>
            <p>Program Status</p>
        </div>
    </div>

    <!-- Participating Facilities -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5>Participating Facilities</h5>
                    <small class="text-muted">Facilities in {{ $county->name }} County participating in {{ $program->title }}</small>
                </div>
                <div class="card-body">
                    @if($facilities && $facilities->count() > 0)
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
                    @else
                        <div class="empty-state">
                            <div class="icon"><i class="fas fa-hospital"></i></div>
                            <h6>No Participating Facilities</h6>
                            <p>No facilities in {{ $county->name }} County are participating in {{ $program->title }}.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Program Details -->
    @if($program->description || $program->learning_outcomes || $program->target_audience)
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5>Program Details</h5>
                </div>
                <div class="card-body">
                    @if($program->description)
                    <div class="mb-3">
                        <h6 class="fw-bold">Description</h6>
                        <p class="text-muted">{{ $program->description }}</p>
                    </div>
                    @endif

                    @if($program->target_audience)
                    <div class="mb-3">
                        <h6 class="fw-bold">Target Audience</h6>
                        <p class="text-muted">{{ $program->target_audience }}</p>
                    </div>
                    @endif

                    @if($program->learning_outcomes && is_array($program->learning_outcomes))
                    <div class="mb-3">
                        <h6 class="fw-bold">Learning Outcomes</h6>
                        <ul>
                            @foreach($program->learning_outcomes as $outcome)
                                <li>{{ $outcome }}</li>
                            @endforeach
                        </ul>
                    </div>
                    @endif

                    <div class="row g-3">
                        @if($program->start_date && $program->end_date)
                        <div class="col-md-6">
                            <h6 class="fw-bold">Duration</h6>
                            <p class="text-muted">
                                {{ \Carbon\Carbon::parse($program->start_date)->format('M j, Y') }} - 
                                {{ \Carbon\Carbon::parse($program->end_date)->format('M j, Y') }}
                            </p>
                        </div>
                        @endif

                        @if($program->max_participants)
                        <div class="col-md-6">
                            <h6 class="fw-bold">Maximum Participants</h6>
                            <p class="text-muted">{{ number_format($program->max_participants) }}</p>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
@endsection

@section('custom-styles')
.facility-card {
    transition: all 0.3s ease;
    cursor: pointer;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    background: white;
    overflow: hidden;
    height: 100%;
}

.facility-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 28px rgba(0,0,0,0.15);
    border-color: #3b82f6;
}

.facility-card .card-body {
    padding: 1.5rem;
    position: relative;
}

.facility-card .card-body::before {
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

@media (max-width: 768px) {
    .facility-card .card-body {
        padding: 1rem;
    }
}
@endsection

@section('page-scripts')
// Year filter
const _yearFilter = document.getElementById('yearFilter');
if (_yearFilter) {
    _yearFilter.addEventListener('change', function() {
        const url = new URL(window.location);
        if (this.value) {
            url.searchParams.set('year', this.value);
        } else {
            url.searchParams.delete('year');
        }
        url.searchParams.set('mode', 'training');
        window.location.href = url.toString();
    });
}

// Facility cards click
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
        mode: 'training'
    });
    
    const currentYear = '{{ $selectedYear ?? "" }}';
    if (currentYear) {
        params.set('year', currentYear);
    }
    
    // Navigate to facility participants for the selected training
    window.location.href = `/analytics/dashboard/county/{{ $county->id }}/program/{{ $program->id }}/facility/${facilityId}?${params.toString()}`;
}
@endsection