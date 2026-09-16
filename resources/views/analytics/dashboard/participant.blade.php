@extends('layouts.dashboard')

@section('title', $participant->user->full_name . ' - Participant Profile')

@section('content')
<div class="container-fluid px-4">
    <!-- Profile Header -->
    <div class="profile-header">
        <div class="row align-items-center">
            <div class="col-md-2 text-center">
                <div class="profile-avatar mx-auto">
                    @if($participant->user->avatar)
                        <img src="{{ $participant->user->avatar }}" alt="{{ $participant->user->full_name }}">
                    @else
                        {{ strtoupper(substr($participant->user->first_name ?? '', 0, 1) . substr($participant->user->last_name ?? '', 0, 1)) }}
                    @endif
                </div>
            </div>
            <div class="col-md-6">
                <h1 class="h2 mb-2">{{ $participant->user->full_name ?? 'N/A' }}</h1>
                <p class="mb-3 opacity-75">{{ $mode === 'training' ? 'Training Participant' : 'Mentee' }} Profile</p>
                
                <div class="info-grid">
                    <div class="info-item">
                        <i class="fas fa-hospital"></i>
                        <div>
                            <small class="d-block opacity-75">Facility</small>
                            <span class="fw-semibold">{{ $participant->user->facility->name ?? 'N/A' }}</span>
                        </div>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-building"></i>
                        <div>
                            <small class="d-block opacity-75">Department</small>
                            <span class="fw-semibold">{{ $participant->user->department->name ?? 'N/A' }}</span>
                        </div>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-user-md"></i>
                        <div>
                            <small class="d-block opacity-75">Cadre</small>
                            <span class="fw-semibold">{{ $participant->user->cadre->name ?? 'N/A' }}</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 text-end">
                <div class="mb-3">
                    <span class="badge bg-white text-dark fs-6 px-3 py-2">
                        {{ $program->title }}
                    </span>
                </div>
<!--                <div>
                    <small class="d-block opacity-75">Registration Date</small>
                    <span class="h5">{{ $participant->registration_date ? \Carbon\Carbon::parse($participant->registration_date)->format('M j, Y') : 'N/A' }}</span>
                </div>-->
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Main Content -->
        <div class="col-lg-8">
            <!-- Assessment Results -->
            @if($participant->assessmentResults && $participant->assessmentResults->count() > 0)
            <div class="card mb-4">
                <div class="card-header">
                    <h5>Assessment Results</h5>
                    <small class="text-muted">Performance across different assessment categories</small>
                </div>
                <div class="card-body">
                    @php
                        $overallScore = $program->calculateOverallScore($participant);
                    @endphp
                    
                    <div class="alert alert-{{ $overallScore['status'] === 'PASSED' ? 'success' : ($overallScore['status'] === 'FAILED' ? 'danger' : 'warning') }}">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1">Overall Assessment Status: {{ $overallScore['status'] }}</h6>
                                <small>Score: {{ $overallScore['score'] }}% | Categories: {{ $overallScore['assessed_categories'] }}/{{ $overallScore['total_categories'] }}</small>
                            </div>
                            <div class="fs-2">
                                @if($overallScore['status'] === 'PASSED')
                                    <i class="fas fa-check-circle"></i>
                                @elseif($overallScore['status'] === 'FAILED')
                                    <i class="fas fa-times-circle"></i>
                                @else
                                    <i class="fas fa-clock"></i>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="row g-3">
                        @foreach($participant->assessmentResults as $result)
                        <div class="col-md-6">
                            <div class="assessment-card">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h6 class="mb-0">{{ $result->assessmentCategory->name ?? 'Assessment' }}</h6>
                                    <span class="assessment-result-{{ strtolower($result->result ?? 'pending') }}">
                                        {{ strtoupper($result->result ?? 'PENDING') }}
                                    </span>
                                </div>
                                <div class="small text-muted mb-2">
                                    Weight: {{ $result->category_weight ?? 0 }}%
                                </div>
                                @if($result->feedback)
                                <div class="small">
                                    <strong>Feedback:</strong> {{ $result->feedback }}
                                </div>
                                @endif
                                @if($result->assessment_date)
                                <div class="small text-muted mt-1">
                                    Assessed: {{ \Carbon\Carbon::parse($result->assessment_date)->format('M j, Y') }}
                                </div>
                                @endif
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif

            <!-- Training History -->
            @if($trainingHistory && $trainingHistory->count() > 0)
            <div class="card mb-4">
                <div class="card-header">
                    <h5>Training/Mentorship History</h5>
                    <small class="text-muted">Other programs and mentorships attended</small>
                </div>
                <div class="card-body">
                    @foreach($trainingHistory as $history)
                    <div class="training-history-item">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <h6 class="mb-1">{{ $history->training->title ?? 'N/A' }}</h6>
                                <div class="d-flex gap-2 mb-2">
                                    <span class="badge bg-{{ $history->training->type === 'global_training' ? 'primary' : 'success' }}">
                                        {{ $history->training->type === 'global_training' ? 'Training' : 'Mentorship' }}
                                    </span>
                                    @if($history->completion_status)
                                    <span class="badge bg-{{ $history->completion_status === 'completed' ? 'success' : ($history->completion_status === 'in_progress' ? 'warning' : 'danger') }}">
                                        {{ ucfirst(str_replace('_', ' ', $history->completion_status)) }}
                                    </span>
                                    @endif
                                </div>
                            </div>
                            <div class="text-end">
                                <div class="small text-muted">
                                    {{ $history->registration_date ? \Carbon\Carbon::parse($history->registration_date)->format('M j, Y') : 'N/A' }}
                                </div>
                                @if($history->assessmentResults && $history->assessmentResults->count() > 0)
                                @php
                                    $historyScore = $history->training->calculateOverallScore($history);
                                @endphp
                                <div class="fw-semibold">
                                    Score: {{ $historyScore['all_assessed'] ? $historyScore['score'] . '%' : 'Incomplete' }}
                                </div>
                                @endif
                            </div>
                        </div>
                        
                        @if($history->training->facility)
                        <div class="small text-muted">
                            <i class="fas fa-hospital me-1"></i>
                            {{ $history->training->facility->name }}
                        </div>
                        @endif
                        
                        @if($history->assessmentResults && $history->assessmentResults->count() > 0)
                        <div class="mt-2">
                            <small class="text-muted d-block mb-1">Assessment Results:</small>
                            <div class="d-flex flex-wrap gap-1">
                                @foreach($history->assessmentResults as $assessment)
                                <span class="badge bg-{{ $assessment->result === 'pass' ? 'success' : 'danger' }} bg-opacity-25 text-dark">
                                    {{ $assessment->assessmentCategory->name ?? 'Assessment' }}: {{ strtoupper($assessment->result ?? 'N/A') }}
                                </span>
                                @endforeach
                            </div>
                        </div>
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            @if((!$participant->assessmentResults || $participant->assessmentResults->count() === 0) && (!$trainingHistory || $trainingHistory->count() === 0))
            <div class="card">
                <div class="card-body text-center py-5">
                    <div class="empty-state">
                        <div class="icon"><i class="fas fa-clipboard-list"></i></div>
                        <h6>Limited Profile Data</h6>
                        <p>This participant has minimal assessment data and training history available.</p>
                    </div>
                </div>
            </div>
            @endif
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <div class="status-info-card">
                <h6 class="mb-3">Current Status Information</h6>
                
                <!-- Overall Status -->
                <div class="card border-0 bg-light mb-3">
                    <div class="card-body">
                        <h6 class="card-title mb-3">Current Status</h6>
                        <div class="mb-3">
                            <small class="text-muted d-block">Overall Status</small>
                            <span class="badge bg-success fs-6">
                                {{ $participant->completion_status ? ucfirst(str_replace('_', ' ', $participant->completion_status)) : 'Active' }}
                            </span>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted d-block">Current Cadre</small>
                            <span class="fw-semibold">{{ $participant->user->cadre->name ?? 'N/A' }}</span>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted d-block">Current Department</small>
                            <span class="fw-semibold">{{ $participant->user->department->name ?? 'N/A' }}</span>
                        </div>
                        <div class="mb-0">
                            <small class="text-muted d-block">Current Facility</small>
                            <span class="fw-semibold">{{ $participant->user->facility->name ?? 'N/A' }}</span>
                        </div>
                    </div>
                </div>

                <!-- Location Details -->
                <div class="card border-0 bg-light">
                    <div class="card-body">
                        <h6 class="card-title">Location Details</h6>
                        <div class="small">
                            <div class="d-flex justify-content-between mb-1">
                                <span>County:</span>
                                <span class="fw-semibold">{{ $county->name ?? 'N/A' }}</span>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span>Subcounty:</span>
                                <span class="fw-semibold">{{ $facility->subcounty->name ?? 'N/A' }}</span>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span>MFL Code:</span>
                                <span class="fw-semibold">{{ $facility->mfl_code ?? 'N/A' }}</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Facility Type:</span>
                                <span class="fw-semibold">{{ $facility->facilityType->name ?? 'N/A' }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('custom-styles')
.profile-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 16px;
    padding: 2rem;
    margin-bottom: 2rem;
    position: relative;
    overflow: hidden;
}

.profile-header::before {
    content: '';
    position: absolute;
    top: -50px;
    right: -50px;
    width: 200px;
    height: 200px;
    background: rgba(255,255,255,0.1);
    border-radius: 50%;
}

.profile-avatar {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    border: 4px solid rgba(255,255,255,0.3);
    background: rgba(255,255,255,0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    margin-bottom: 1rem;
    position: relative;
    z-index: 2;
    overflow: hidden;
}

.profile-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.profile-header h1,
.profile-header p,
.profile-header .badge {
    position: relative;
    z-index: 2;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.info-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.info-item i {
    color: rgba(255,255,255,0.8);
    width: 20px;
}

.assessment-card {
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 1.25rem;
    margin-bottom: 1rem;
    transition: all 0.2s;
    background: white;
    position: relative;
    overflow: hidden;
}

.assessment-card:hover {
    border-color: #3b82f6;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.assessment-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
}

.assessment-result-pass {
    background: #dcfce7;
    color: #166534;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.875rem;
    font-weight: 600;
}

.assessment-result-fail {
    background: #fee2e2;
    color: #991b1b;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.875rem;
    font-weight: 600;
}

.assessment-result-pending {
    background: #fef3c7;
    color: #92400e;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.875rem;
    font-weight: 600;
}

.training-history-item {
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 1.25rem;
    margin-bottom: 1rem;
    transition: all 0.2s;
    background: white;
    position: relative;
    overflow: hidden;
}

.training-history-item:hover {
    border-color: #3b82f6;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.training-history-item::before {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.1) 0%, rgba(29, 78, 216, 0.05) 100%);
    border-radius: 50%;
    transform: translate(20px, -20px);
}

.status-info-card {
    position: sticky;
    top: 20px;
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    border: 1px solid #e5e7eb;
}

.alert {
    border: none;
    border-radius: 12px;
    padding: 1.25rem 1.5rem;
    margin-bottom: 1.5rem;
}

@media (max-width: 768px) {
    .profile-header {
        padding: 1.5rem;
    }
    
    .profile-avatar {
        width: 80px;
        height: 80px;
        font-size: 2rem;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
    }
    
    .status-info-card {
        position: static;
        margin-top: 1rem;
    }
}

@media (max-width: 576px) {
    .assessment-card,
    .training-history-item {
        padding: 1rem;
    }
}
@endsection

@section('page-scripts')
console.log('Participant profile loaded for:', '{{ $participant->user->full_name ?? "N/A" }}');
@endsection