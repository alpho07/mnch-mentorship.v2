@extends('layouts.dashboard')

@section('title', $facility->name . ' - Facility Analytics')

@section('content')
<div class="container-fluid px-4">
    <!-- Header -->
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <h1 class="h2 mb-1">{{ $facility->name }}</h1>
                <p class="mb-2">{{ $program->title }} | {{ $county->name }} County</p>
                <div class="d-flex gap-3 flex-wrap">
                    @if($facility->mfl_code)
                    <span class="badge bg-white text-dark">MFL: {{ $facility->mfl_code }}</span>
                    @endif
                    <span class="badge bg-white text-dark">{{ $facility->facilityType->name ?? 'N/A' }}</span>
                    <span class="badge bg-white text-dark">{{ $facility->subcounty->name ?? 'N/A' }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Facility Statistics -->
    <div class="stats-grid">
        <div class="stats-card">
            <div class="icon"><i class="fas fa-users"></i></div>
            <h3>{{ number_format($participants->count()) }}</h3>
            <p>Total {{ $mode === 'training' ? 'Participants' : 'Mentees' }}</p>
        </div>
        
    </div>

    <div class="row">
        <!-- Participants List -->
        <div class="col-lg-8 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5>{{ $mode === 'training' ? 'Participants' : 'Mentees' }}</h5>
                    <small class="text-muted">Click on a participant to view detailed profile</small>
                </div>
                <div class="card-body">
                    @if($participants->count() > 0)
                        <div class="row g-3">
                            @foreach($participants as $participant)
                            <div class="col-md-6">
                                <div class="participant-card card h-100" data-participant-id="{{ $participant->id }}">
                                    <div class="card-body">
                                        <div class="d-flex align-items-start mb-3">
                                            <div class="participant-avatar me-3">
                                                @if($participant->user->avatar)
                                                    <img src="{{ $participant->user->avatar }}" alt="{{ $participant->user->full_name }}">
                                                @else
                                                    {{ strtoupper(substr($participant->user->first_name ?? '', 0, 1) . substr($participant->user->last_name ?? '', 0, 1)) }}
                                                @endif
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1 fw-bold">{{ $participant->user->full_name ?? 'N/A' }}</h6>
                                                <p class="text-muted small mb-1">{{ $participant->user->cadre->name ?? 'N/A' }}</p>
                                                <p class="text-muted small mb-0">{{ $participant->user->department->name ?? 'N/A' }}</p>
                                            </div>
                                            <div class="text-end">
                                                @php
                                                    $statusColor = match($participant?->completion_status ?? 'in_progress') {
                                                        'completed' => 'bg-success text-white',
                                                        'dropped' => 'bg-danger text-white',
                                                        default => 'bg-warning text-dark'
                                                    };
                                                @endphp
                                                <span class="status-badge {{ $statusColor }}">
                                                    {{ ucfirst(str_replace('_', ' ', @$participant?->completion_status ?? 'Active')) }}
                                                </span>
                                            </div>
                                        </div>
                                        
                                        @if($participant->assessmentResults && $participant->assessmentResults->count() > 0)
                                        <div class="mb-3">
                                            <small class="text-muted d-block mb-1">Assessment Status</small>
                                            <div class="d-flex align-items-center flex-wrap">
                                                @foreach($participant->assessmentResults->take(3) as $assessment)
                                                <span class="small me-2">{{ Str::limit($assessment->assessmentCategory->name ?? 'Assessment', 8) }}</span>
                                                <span class="assessment-indicator assessment-{{ strtolower($assessment->result ?? 'pending') }}"></span>
                                                @if(!$loop->last && $loop->index < 2)
                                                    <span class="mx-1">•</span>
                                                @endif
                                                @endforeach
                                                @if($participant->assessmentResults->count() > 3)
                                                <span class="small text-muted ms-2">+{{ $participant->assessmentResults->count() - 3 }}</span>
                                                @endif
                                            </div>
                                        </div>
                                        @endif
                                        
                                        <div class="d-flex justify-content-end align-items-center">
                                            <i class="fas fa-arrow-right text-primary"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    @else
                        <div class="empty-state">
                            <div class="icon"><i class="fas fa-users"></i></div>
                            <h6>No Participants Found</h6>
                            <p>No {{ $mode === 'training' ? 'participants' : 'mentees' }} are enrolled from this facility.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Analytics Sidebar -->
        <div class="col-lg-4 mb-4" style="display:none;">
            <div class="card">
                <div class="card-header">
                    <h5>Analytics Overview</h5>
                    <small class="text-muted">Breakdown by categories</small>
                </div>
                <div class="card-body">
                    <!-- Department Stats -->
                    @if(isset($facilityStats['departmentStats']) && $facilityStats['departmentStats']->count() > 0)
                    <div class="mb-4">
                        <h6 class="mb-3">By Department</h6>
                        @foreach($facilityStats['departmentStats'] as $deptName => $stats)
                        <div class="analytics-card">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="fw-semibold">{{ $deptName }}</span>
                                <span class="badge bg-primary">{{ $stats['count'] }}</span>
                            </div>
                           
                        </div>
                        @endforeach 
                    </div>
                    @endif

                    <!-- Cadre Stats -->
                    @if(isset($facilityStats['cadreStats']) && $facilityStats['cadreStats']->count() > 0)
                    <div>
                        <h6 class="mb-3">By Cadre</h6>
                        @foreach($facilityStats['cadreStats'] as $cadreName => $stats)
                        <div class="analytics-card">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="fw-semibold">{{ $cadreName }}</span>
                                <span class="badge bg-success">{{ $stats['count'] }}</span>
                            </div>
                           
                        </div>
                        @endforeach
                    </div>
                    @endif

                    @if((!isset($facilityStats['departmentStats']) || $facilityStats['departmentStats']->count() === 0) && 
                        (!isset($facilityStats['cadreStats']) || $facilityStats['cadreStats']->count() === 0))
                    <div class="empty-state">
                        <div class="icon"><i class="fas fa-chart-bar"></i></div>
                        <h6>No Analytics Data</h6>
                        <p>Analytics breakdown will appear here when participants are enrolled.</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('custom-styles')
.participant-card {
    transition: all 0.3s ease;
    cursor: pointer;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    background: white;
    overflow: hidden;
    height: 100%;
}

.participant-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.1);
    border-color: #3b82f6;
}

.participant-card .card-body {
    padding: 1.25rem;
}

.participant-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
    font-size: 1rem;
    overflow: hidden;
}

.participant-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
}

.assessment-indicator {
    width: 16px;
    height: 16px;
    border-radius: 50%;
    display: inline-block;
    margin-left: 0.25rem;
}

.assessment-pass {
    background-color: #10b981;
}

.assessment-fail {
    background-color: #ef4444;
}

.assessment-pending {
    background-color: #f59e0b;
}

.analytics-card {
    background: white;
    border-radius: 12px;
    padding: 1rem;
    border: 1px solid #e5e7eb;
    margin-bottom: 1rem;
    transition: all 0.2s ease;
}

.analytics-card:hover {
    border-color: #3b82f6;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
@endsection

@section('page-scripts')
// Participant cards click
document.querySelectorAll('.participant-card').forEach(card => {
    card.addEventListener('click', function() {
        const participantId = this.dataset.participantId;
        if (participantId) {
            navigateToParticipant(participantId);
        }
    });
});

function navigateToParticipant(participantId) {
    const params = new URLSearchParams({
        mode: '{{ $mode ?? "training" }}'
    });
    
    const currentYear = '{{ $selectedYear ?? "" }}';
    if (currentYear) {
        params.set('year', currentYear);
    }
    
    window.location.href = `/analytics/dashboard/county/{{ $county->id }}/program/{{ $program->id }}/facility/{{ $facility->id }}/participant/${participantId}?${params.toString()}`;
}
@endsection