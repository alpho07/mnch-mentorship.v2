@extends('layouts.app')
@section('title','MOH Training')

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-semibold text-gray-900">MOH Global Training</h1>
        <p class="text-gray-600 mt-1">Multi-facility training programs across Kenya</p>
    </div>
    <div class="flex items-center space-x-3">
        <a href="{{ route('training.heatmap.moh') }}" 
           class="inline-flex items-center gap-2 rounded-xl border border-blue-200 bg-blue-50 px-4 py-2 text-blue-700 hover:bg-blue-100 transition-colors">
            <i class="fas fa-map w-5 h-5"></i>
            View Coverage Map
        </a>
        <a href="{{ route('training.mentorship') }}" 
           class="inline-flex items-center gap-2 rounded-xl border px-4 py-2 hover:bg-gray-50 transition-colors">
            <i class="fas fa-hospital w-5 h-5"></i>
            Mentorship Programs
        </a>
    </div>
</div>

{{-- Filter Form --}}
<div class="bg-white rounded-lg border border-gray-200 p-4 mb-6">
    <form method="get" class="flex items-center space-x-4">
        @php $status = request('status','upcoming'); @endphp
        
        <div class="flex items-center space-x-2">
            <label for="status" class="text-sm font-medium text-gray-700">Status:</label>
            <select name="status" id="status" onchange="this.form.submit()" 
                    class="rounded-lg border-gray-300 text-sm focus:ring-blue-500 focus:border-blue-500">
                <option value="upcoming" {{ $status==='upcoming'?'selected':'' }}>
                    <i class="fas fa-clock"></i> Upcoming
                </option>
                <option value="ongoing" {{ $status==='ongoing'?'selected':'' }}>
                    <i class="fas fa-play"></i> Ongoing
                </option>
                <option value="completed" {{ $status==='completed'?'selected':'' }}>
                    <i class="fas fa-check"></i> Completed
                </option>
                <option value="all" {{ $status==='all'?'selected':'' }}>
                    <i class="fas fa-list"></i> All
                </option>
            </select>
        </div>
        
        {{-- Summary Stats --}}
        <div class="flex-1 flex items-center justify-end space-x-6 text-sm text-gray-600">
            <div class="flex items-center">
                <i class="fas fa-graduation-cap mr-1 text-blue-500"></i>
                <span>{{ $trainings->total() }} Total Programs</span>
            </div>
            @if($trainings->sum('participants_count') > 0)
                <div class="flex items-center">
                    <i class="fas fa-users mr-1 text-green-500"></i>
                    <span>{{ number_format($trainings->sum('participants_count')) }} Participants</span>
                </div>
            @endif
        </div>
    </form>
</div>

{{-- Training Cards --}}
@if($trainings->count() === 0)
    <div class="rounded-xl border bg-white p-12 text-center">
        <div class="mx-auto w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mb-4">
            <i class="fas fa-graduation-cap text-3xl text-gray-400"></i>
        </div>
        <h3 class="text-lg font-medium text-gray-900 mb-2">No MOH Training Programs Found</h3>
        <p class="text-gray-600 mb-4">
            @if($status === 'upcoming')
                No upcoming global training programs scheduled.
            @elseif($status === 'ongoing')
                No training programs currently in progress.
            @elseif($status === 'completed')
                No completed training programs found.
            @else
                No MOH global training programs available.
            @endif
        </p>
        @auth
            <a href="{{ url('admin/trainings/create') }}" 
               class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                <i class="fas fa-plus mr-2"></i>
                Create New Training
            </a>
        @endauth
    </div>
@else
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach($trainings as $t)
            @include('training._card', ['t' => $t])
        @endforeach
    </div>
    
    {{-- Pagination --}}
    @if($trainings->hasPages())
        <div class="mt-8">
            {{ $trainings->appends(request()->query())->links() }}
        </div>
    @endif
@endif

{{-- Quick Actions --}}
<div class="mt-8 bg-gray-50 rounded-lg p-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">Quick Actions</h3>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <a href="{{ route('training.heatmap.moh') }}" 
           class="flex items-center p-4 bg-white rounded-lg border hover:shadow-md transition-all">
            <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                <i class="fas fa-map text-blue-600"></i>
            </div>
            <div>
                <div class="font-medium text-gray-900">Coverage Dashboard</div>
                <div class="text-sm text-gray-500">Interactive county-level insights</div>
            </div>
        </a>
        
        <a href="{{ route('training.mentorship') }}" 
           class="flex items-center p-4 bg-white rounded-lg border hover:shadow-md transition-all">
            <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                <i class="fas fa-user-friends text-green-600"></i>
            </div>
            <div>
                <div class="font-medium text-gray-900">Mentorship Programs</div>
                <div class="text-sm text-gray-500">Facility-based training</div>
            </div>
        </a>
        
        @auth
            <a href="{{ url('admin/trainings') }}" 
               class="flex items-center p-4 bg-white rounded-lg border hover:shadow-md transition-all">
                <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center mr-3">
                    <i class="fas fa-cog text-purple-600"></i>
                </div>
                <div>
                    <div class="font-medium text-gray-900">Manage Training</div>
                    <div class="text-sm text-gray-500">Admin dashboard</div>
                </div>
            </a>
        @endauth
    </div>
</div>
@endsection