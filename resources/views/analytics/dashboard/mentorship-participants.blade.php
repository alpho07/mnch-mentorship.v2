@extends('layouts.dashboard')

@section('title', $program->title . ' Mentees - Analytics')

@section('content')
<div class="container-fluid px-4">
    <!-- Header -->
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <h1 class="h2 mb-1">{{ $program->title }}</h1>
                <p class="mb-2">Mentorship Participants | {{ $county->name }} County</p>
                <div class="d-flex gap-3 flex-wrap">
                    @if($program->identifier)
                    <span class="badge bg-white text-dark">ID: {{ $program->identifier }}</span>
                    @endif
                    @if($program->start_date)
                    <span class="badge bg-white text-dark">Year: {{ \Carbon\Carbon::parse($program->start_date)->format('Y') }}</span>
                    @endif
                    <span class="badge bg-white text-dark">Mentorship Program</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Program Statistics -->
    <div class="stats-grid">
        <div class="stats-card">
            <div class="icon"><i class="fas fa-users"></i></div>
            <h3>{{ number_format($participants->count() ?? 0) }}</h3>
            <p>Total Mentees</p>
        </div>
        <div class="stats-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
            <div class="icon"><i class="fas fa-check-circle"></i></div>
            <h3>{{ number_format($participants->where('completion_status', 'completed')->count() ?? 0) }}</h3>
            <p>Completed</p>
        </div>
        <div class="stats-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
            <div class="icon"><i class="fas fa-clock"></i></div>
            <h3>{{ number_format($participants->where('completion_status', 'in_progress')->count() ?? 0) }}</h3>
            <p>In Progress</p>
        </div>
        <div class="stats-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
            <div class="icon"><i class="fas fa-chart-line"></i></div>
            @php
                $completionRate = $participants->count() > 0 ? 
                    round($participants->where('completion_status', 'completed')->count() / $participants->count() * 100, 1) : 0;
            @endphp
            <h3>{{ $completionRate }}%</h3>
            <p>Completion Rate</p>
        </div>
    </div>

    <!-- Participants List -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5>Mentees</h5>
                    <small class="text-muted">Click on a mentee to view detailed profile and assessment results</small>
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
                                                    $statusColor = match($participant->completion_status ?? 'in_progress') {
                                                        'completed' => 'bg-success text-white',
                                                        'dropped' => 'bg-danger text-white',
                                                        default => 'bg-warning text-dark'
                                                    };
                                                @endphp
                                                <span class="status-badge {{ $statusColor }}">
                                                    {{ ucfirst(str_replace('_', ' ', $participant->completion_status ?? 'Active')) }}
                                                </span>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-2">
                                            <small class="text-muted d-block">Facility</small>
                                            <span class="fw-semibold">{{ $participant->user->facility->name ?? 'N/A' }}</span>
                                        </div>
                                        
                                        @if($participant->assessmentResults && $participant->assessmentResults->count() > 0)
                                        <div class="mb-3">
                                            <small class="text-muted d-block mb-1">Assessment Status</small>
                                            <div class="d-flex align-items-center flex-wrap">
                                                @foreach($participant->assessmentResults->take(3) as $assessment)
                                                @php
                                                    $assessmentLabel = $assessment->assessmentCategory->name
                                                        ?? $assessment->moduleAssessment->title
                                                        ?? 'Assessment';
                                                    $rawStatus = strtolower($assessment->result ?? $assessment->status ?? 'pending');
                                                    $normalizedStatus = in_array($rawStatus, ['pass', 'passed', 'completed'], true)
                                                        ? 'pass'
                                                        : (in_array($rawStatus, ['fail', 'failed'], true) ? 'fail' : 'pending');
                                                @endphp
                                                <span class="small me-2">{{ Str::limit($assessmentLabel, 8) }}</span>
                                                <span class="assessment-indicator assessment-{{ $normalizedStatus }}"></span>
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
                                            <i class="fas fa-arrow-right text-success"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    @else
                        <div class="empty-state">
                            <div class="icon"><i class="fas fa-users"></i></div>
                            <h6>No Mentees Found</h6>
                            <p>No mentees are enrolled in this mentorship program.</p>
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
    border-color: #10b981;
}

.participant-card .card-body {
    padding: 1.25rem;
}

.participant-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
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

@media (max-width: 768px) {
    .participant-card .card-body {
        padding: 1rem;
    }
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
        mode: 'mentorship'
    });
    
    const currentYear = '{{ $selectedYear ?? "" }}';
    if (currentYear) {
        params.set('year', currentYear);
    }
    
    // For mentorships, we go directly to participant without facility level
    window.location.href = `/analytics/dashboard/county/{{ $county->id }}/program/{{ $program->id }}/participant/${participantId}?${params.toString()}`;
}
@endsection
