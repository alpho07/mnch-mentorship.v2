@extends('layouts.app')

@section('title', 'Participants Management')

@section('styles')
<style>
    .participant-card {
        transition: all 0.3s ease;
        cursor: pointer;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        overflow: hidden;
    }
    .participant-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        border-color: #3b82f6;
    }
    .participant-avatar {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: bold;
        font-size: 1.2rem;
    }
    .status-badge {
        font-size: 0.75rem;
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-weight: 600;
    }
    .filter-section {
        background: #f8fafc;
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 2rem;
        border: 1px solid #e2e8f0;
    }
    .stats-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 12px;
        padding: 1.5rem;
        text-align: center;
        margin-bottom: 1rem;
    }
    .assessment-indicator {
        width: 20px;
        height: 20px;
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
    .search-box {
        position: relative;
    }
    .search-box i {
        position: absolute;
        left: 1rem;
        top: 50%;
        transform: translateY(-50%);
        color: #6b7280;
    }
    .search-box input {
        padding-left: 2.5rem;
    }
    .grid-view {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 1.5rem;
    }
    .list-view .participant-card {
        margin-bottom: 1rem;
    }
    .view-toggle {
        background: #f1f5f9;
        border-radius: 8px;
        padding: 4px;
    }
    .view-toggle button {
        padding: 8px 12px;
        border: none;
        background: transparent;
        border-radius: 6px;
        transition: all 0.2s;
    }
    .view-toggle button.active {
        background: white;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
</style>
@endsection

@section('content')
<div class="container-fluid px-4 py-6">
    <!-- Header Section -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h2 mb-1">Participants Management</h1>
            <p class="text-muted">View and manage training participants and mentees across all programs</p>
        </div>
        
        <div class="d-flex gap-3 align-items-center">
            <!-- View Toggle -->
            <div class="view-toggle">
                <button type="button" class="view-btn active" data-view="grid">
                    <i class="fas fa-th"></i>
                </button>
                <button type="button" class="view-btn" data-view="list">
                    <i class="fas fa-list"></i>
                </button>
            </div>
            
            <!-- Export Button -->
            <button class="btn btn-outline-primary">
                <i class="fas fa-download me-2"></i>
                Export
            </button>
        </div>
    </div>

    <!-- Summary Statistics -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stats-card">
                <h3 class="h4 mb-1">{{ number_format($totalParticipants ?? 0) }}</h3>
                <p class="mb-0 opacity-75">Total Participants</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                <h3 class="h4 mb-1">{{ number_format($trainingParticipants ?? 0) }}</h3>
                <p class="mb-0 opacity-75">Training Participants</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                <h3 class="h4 mb-1">{{ number_format($mentees ?? 0) }}</h3>
                <p class="mb-0 opacity-75">Mentees</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                <h3 class="h4 mb-1">{{ $completionRate ?? 0 }}%</h3>
                <p class="mb-0 opacity-75">Completion Rate</p>
            </div>
        </div>
    </div>

    <!-- Filters Section -->
    <div class="filter-section">
        <form method="GET" id="filterForm">
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" class="form-control" name="search" placeholder="Search participants..." 
                               value="{{ request('search') }}">
                    </div>
                </div>
                
                <div class="col-md-2">
                    <select name="training_type" class="form-select">
                        <option value="">All Types</option>
                        <option value="global_training" {{ request('training_type') === 'global_training' ? 'selected' : '' }}>
                            Training Programs
                        </option>
                        <option value="facility_mentorship" {{ request('training_type') === 'facility_mentorship' ? 'selected' : '' }}>
                            Mentorships
                        </option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <select name="county_id" class="form-select">
                        <option value="">All Counties</option>
                        @foreach($counties ?? [] as $county)
                            <option value="{{ $county->id }}" {{ request('county_id') == $county->id ? 'selected' : '' }}>
                                {{ $county->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                
                <div class="col-md-2">
                    <select name="department_id" class="form-select">
                        <option value="">All Departments</option>
                        @foreach($departments ?? [] as $department)
                            <option value="{{ $department->id }}" {{ request('department_id') == $department->id ? 'selected' : '' }}>
                                {{ $department->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                
                <div class="col-md-2">
                    <select name="completion_status" class="form-select">
                        <option value="">All Status</option>
                        <option value="completed" {{ request('completion_status') === 'completed' ? 'selected' : '' }}>Completed</option>
                        <option value="in_progress" {{ request('completion_status') === 'in_progress' ? 'selected' : '' }}>In Progress</option>
                        <option value="dropped" {{ request('completion_status') === 'dropped' ? 'selected' : '' }}>Dropped</option>
                    </select>
                </div>
                
                <div class="col-md-1">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter"></i>
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Participants Grid/List -->
    <div id="participantsContainer" class="grid-view">
        @forelse($participants ?? [] as $participant)
        <div class="participant-card card border-0 shadow-sm" data-participant-id="{{ $participant->id }}">
            <div class="card-body p-4">
                <div class="d-flex align-items-start mb-3">
                    <div class="participant-avatar me-3">
                        @if($participant->user->avatar)
                            <img src="{{ $participant->user->avatar }}" alt="{{ $participant->user->full_name }}" 
                                 class="w-100 h-100 rounded-circle object-fit-cover">
                        @else
                            {{ strtoupper(substr($participant->user->first_name ?? '', 0, 1) . substr($participant->user->last_name ?? '', 0, 1)) }}
                        @endif
                    </div>
                    <div class="flex-grow-1">
                        <h6 class="mb-1 fw-bold">{{ $participant->user->full_name ?? 'N/A' }}</h6>
                        <p class="text-muted small mb-1">{{ $participant->user->cadre->name ?? 'N/A' }}</p>
                        <p class="text-muted small mb-0">
                            <i class="fas fa-hospital me-1"></i>
                            {{ $participant->user->facility->name ?? 'N/A' }}
                        </p>
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
                            {{ ucfirst(str_replace('_', ' ', $participant->completion_status ?? 'In Progress')) }}
                        </span>
                    </div>
                </div>
                
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <small class="text-muted d-block">County</small>
                        <span class="fw-semibold">{{ $participant->user->facility->subcounty->county->name ?? 'N/A' }}</span>
                    </div>
                    <div class="col-6">
                        <small class="text-muted d-block">Program</small>
                        <span class="fw-semibold">{{ Str::limit($participant->training->title ?? 'N/A', 20) }}</span>
                    </div>
                    <div class="col-6">
                        <small class="text-muted d-block">Department</small>
                        <span class="fw-semibold">{{ $participant->user->department->name ?? 'N/A' }}</span>
                    </div>
                    <div class="col-6">
                        <small class="text-muted d-block">Registration</small>
                        <span class="fw-semibold">
                            {{ $participant->registration_date ? \Carbon\Carbon::parse($participant->registration_date)->format('M j, Y') : 'N/A' }}
                        </span>
                    </div>
                </div>
                
                @if($participant->assessmentResults && $participant->assessmentResults->count() > 0)
                <div class="mb-3">
                    <small class="text-muted d-block mb-1">Assessment Status</small>
                    <div class="d-flex align-items-center flex-wrap">
                        @foreach($participant->assessmentResults->take(3) as $assessment)
                        <span class="small me-2">{{ Str::limit($assessment->assessmentCategory->name ?? 'Assessment', 10) }}</span>
                        <span class="assessment-indicator assessment-{{ strtolower($assessment->result ?? 'pending') }}"></span>
                        @if(!$loop->last)
                            <span class="mx-1">•</span>
                        @endif
                        @endforeach
                        @if($participant->assessmentResults->count() > 3)
                        <span class="small text-muted ms-2">+{{ $participant->assessmentResults->count() - 3 }} more</span>
                        @endif
                    </div>
                </div>
                @endif
                
                <div class="d-flex justify-content-end align-items-center">
                    <div>
                        <a href="{{ route('participants.show', $participant->id) }}" 
                           class="btn btn-sm btn-outline-primary me-1" title="View Profile">
                            <i class="fas fa-eye"></i>
                        </a>
                        <button class="btn btn-sm btn-outline-secondary" 
                                onclick="showUpdateModal({{ $participant->id }})" title="Update Status">
                            <i class="fas fa-edit"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        @empty
        <div class="col-12">
            <div class="text-center py-5">
                <div class="mb-3 fs-1">👥</div>
                <h6 class="text-muted">No Participants Found</h6>
                <p class="text-muted">No participants match your current filters. Try adjusting your search criteria.</p>
            </div>
        </div>
        @endforelse
    </div>

    <!-- Pagination -->
    @if(isset($participants) && method_exists($participants, 'links'))
    <div class="d-flex justify-content-center mt-4">
        {{ $participants->appends(request()->query())->links() }}
    </div>
    @endif
</div>

<!-- Update Status Modal -->
<div class="modal fade" id="updateStatusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Participant Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="updateStatusForm">
                    <div class="mb-3">
                        <label class="form-label">Completion Status</label>
                        <select class="form-select" name="completion_status" required>
                            <option value="">Select status...</option>
                            <option value="in_progress">In Progress</option>
                            <option value="completed">Completed</option>
                            <option value="dropped">Dropped</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" rows="3" placeholder="Add any relevant notes..."></textarea>
                    </div>
                    <input type="hidden" name="participant_id" id="updateParticipantId">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitStatusUpdate()">Update Status</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // View toggle functionality
    document.querySelectorAll('.view-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const view = this.dataset.view;
            toggleView(view);
        });
    });

    // Auto-submit filter form on change
    document.querySelectorAll('#filterForm select').forEach(select => {
        select.addEventListener('change', function() {
            document.getElementById('filterForm').submit();
        });
    });
});

function toggleView(view) {
    const container = document.getElementById('participantsContainer');
    const buttons = document.querySelectorAll('.view-btn');
    
    buttons.forEach(btn => {
        btn.classList.toggle('active', btn.dataset.view === view);
    });
    
    if (view === 'list') {
        container.className = 'list-view';
    } else {
        container.className = 'grid-view';
    }
}

function showUpdateModal(participantId) {
    document.getElementById('updateParticipantId').value = participantId;
    const modal = new bootstrap.Modal(document.getElementById('updateStatusModal'));
    modal.show();
}

function submitStatusUpdate() {
    const form = document.getElementById('updateStatusForm');
    const formData = new FormData(form);
    
    fetch('/participants/update-status', {
        method: 'POST',
        body: formData,
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Status updated successfully!');
            const modal = bootstrap.Modal.getInstance(document.getElementById('updateStatusModal'));
            modal.hide();
            location.reload();
        } else {
            alert('Error updating status: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while updating the status.');
    });
}
</script>
@endsection