@extends('layouts.dashboard')

@section('title', $facility->name . ' Mentorships - Analytics')

@section('content')
<div class="container-fluid px-4">
    <!-- Header -->
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <h1 class="h2 mb-1">{{ $facility->name }}</h1>
                <p class="mb-2">Mentorship Programs | {{ $county->name }} County</p>
                <div class="d-flex gap-3 flex-wrap">
                    @if($facility->mfl_code)
                    <span class="badge bg-white text-dark">MFL: {{ $facility->mfl_code }}</span>
                    @endif
                    <span class="badge bg-white text-dark">{{ $facility->facilityType->name ?? 'N/A' }}</span>
                    <span class="badge bg-white text-dark">{{ $facility->subcounty->name ?? 'N/A' }}</span>
                </div>
            </div>
            
            <div class="d-flex gap-3 align-items-center flex-wrap">
                <span class="mode-indicator">Mentorship Mode</span>
                
                <select id="yearFilter" class="form-select" style="width: auto; display:none;">
                    <option value="" {{ empty($selectedYear) ? 'selected' : '' }}>All Years</option>
                    @foreach($availableYears ?? [] as $year)
                        <option value="{{ $year }}" {{ ($selectedYear ?? '') == $year ? 'selected' : '' }}>{{ $year }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <!-- Facility Statistics -->
    <div class="stats-grid">
        <div class="stats-card">
            <div class="icon"><i class="fas fa-user-friends"></i></div>
            <h3>{{ number_format($mentorships->count()) }}</h3>
            <p>Mentorship Programs</p>
        </div>
        <div class="stats-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
            <div class="icon"><i class="fas fa-users"></i></div>
            <h3>{{ number_format($mentorships->sum('mentees_count')) }}</h3>
            <p>Total Mentees</p>
        </div>
        <div class="stats-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
            <div class="icon"><i class="fas fa-calendar"></i></div>
            <h3>{{ $mentorships->count() > 0 ? \Carbon\Carbon::parse($mentorships->first()->start_date)->format('Y') : 'N/A' }}</h3>
            <p>Latest Program</p>
        </div>
        <div class="stats-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
            <div class="icon"><i class="fas fa-check-circle"></i></div>
            <h3>{{ $mentorships->where('status', 'completed')->count() }}</h3>
            <p>Completed Programs</p>
        </div>
    </div>

    <!-- Mentorships List -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5>Mentorship Programs</h5>
                    <small class="text-muted">Click on a mentorship to view participants (mentees)</small>
                </div>
                <div class="card-body">
                    @if($mentorships->count() > 0)
                        <div class="row g-3">
                            @foreach($mentorships as $mentorship)
                            <div class="col-lg-6 col-md-12">
                                <div class="mentorship-card card h-100" data-mentorship-id="{{ $mentorship->id }}">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h6 class="card-title mb-1">{{ $mentorship->title }}</h6>
                                            <span class="badge bg-success">{{ $mentorship->identifier ?? 'N/A' }}</span>
                                        </div>
                                        
                                        <div class="row g-2 mb-3">
                                            <div class="col-6">
                                                <small class="text-muted d-block">Year</small>
                                                <span class="fw-semibold">{{ $mentorship->start_date ? \Carbon\Carbon::parse($mentorship->start_date)->format('Y') : 'N/A' }}</span>
                                            </div>
                                            <div class="col-6">
                                                <small class="text-muted d-block">Mentees</small>
                                                <span class="fw-semibold">{{ number_format($mentorship->mentees_count) }}</span>
                                            </div>
                                        </div>
                                        
                                        @if($mentorship->mentor)
                                        <div class="mb-2">
                                            <small class="text-muted d-block">Mentor</small>
                                            <span class="fw-semibold">{{ $mentorship->mentor->full_name }}</span>
                                        </div>
                                        @endif
                                        
                                        @if($mentorship->start_date && $mentorship->end_date)
                                        <div class="mb-2">
                                            <small class="text-muted d-block">Duration</small>
                                            <span class="fw-semibold">
                                                {{ \Carbon\Carbon::parse($mentorship->start_date)->format('M j') }} - 
                                                {{ \Carbon\Carbon::parse($mentorship->end_date)->format('M j, Y') }}
                                            </span>
                                        </div>
                                        @endif
                                        
                                        @if($mentorship->status)
                                        <div class="mb-3">
                                            <span class="badge bg-{{ $mentorship->status === 'completed' ? 'success' : ($mentorship->status === 'ongoing' ? 'warning' : 'secondary') }}">
                                                {{ ucfirst($mentorship->status) }}
                                            </span>
                                        </div>
                                        @endif
                                        
                                        <div class="d-flex justify-content-between align-items-center">
                                            <small class="text-muted">View mentees</small>
                                            <i class="fas fa-arrow-right text-success"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    @else
                        <div class="empty-state">
                            <div class="icon"><i class="fas fa-user-friends"></i></div>
                            <h6>No Mentorship Programs</h6>
                            <p>No mentorship programs found for {{ $facility->name }} in the selected period.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('custom-styles')
.mentorship-card {
    transition: all 0.3s ease;
    cursor: pointer;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    background: white;
    overflow: hidden;
    height: 100%;
}

.mentorship-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 28px rgba(0,0,0,0.15);
    border-color: #10b981;
}

.mentorship-card .card-body {
    padding: 1.5rem;
    position: relative;
}

.mentorship-card .card-body::before {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(5, 150, 105, 0.1) 100%);
    border-radius: 50%;
    transform: translate(20px, -20px);
}

@media (max-width: 768px) {
    .mentorship-card .card-body {
        padding: 1rem;
    }
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
    url.searchParams.set('mode', 'mentorship');
    window.location.href = url.toString();
});

// Mentorship cards click
document.querySelectorAll('.mentorship-card').forEach(card => {
    card.addEventListener('click', function() {
        const mentorshipId = this.dataset.mentorshipId;
        if (mentorshipId) {
            navigateToMentorship(mentorshipId);
        }
    });
});

function navigateToMentorship(mentorshipId) {
    const params = new URLSearchParams({
        mode: 'mentorship'
    });
    
    const currentYear = '{{ $selectedYear ?? "" }}';
    if (currentYear) {
        params.set('year', currentYear);
    }
    
    window.location.href = `/analytics/dashboard/county/{{ $county->id }}/program/${mentorshipId}?${params.toString()}`;
}
@endsection