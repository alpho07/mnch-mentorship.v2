@extends('layouts.app')
@section('title', $training->title ?? 'Training')

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <div class="text-sm text-gray-500">
            {{ $training->type === 'global_training' ? 'MOH Training' : 'Mentorship' }}
        </div>
        <h1 class="text-2xl font-semibold">{{ $training->title ?? 'Training' }}</h1>
        @if(!empty($training->location) || $training->start_date)
            <div class="mt-1 text-sm text-gray-600">
                @if(!empty($training->location)) {{ $training->location }} • @endif
                @if($training->start_date)
                    {{ \Carbon\Carbon::parse($training->start_date)->format('M d, Y') }}
                    @if($training->end_date)
                        – {{ \Carbon\Carbon::parse($training->end_date)->format('M d, Y') }}
                    @endif
                @endif
            </div>
        @endif
    </div>

    @if($training->type === 'global_training')
        <a href="{{ route('training.heatmap') }}" class="inline-flex items-center gap-2 rounded-xl border px-4 py-2 hover:bg-gray-50">
            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M4 6l4 2 4-2 4 2 4-2v12l-4 2-4-2-4 2-4-2z"/><path d="M8 8v12M16 6v12"/></svg>
            MOH Training Heatmap
        </a>
    @endif
</div>

@php
$cards = [
    ['label' => 'Participants',   'value' => $completionStats['total'] ?? 0],
    ['label' => 'Completed',      'value' => $completionStats['completed'] ?? 0],
    ['label' => 'In Progress',    'value' => $completionStats['in_progress'] ?? 0],
    ['label' => 'Completion Rate','value' => ($completionStats['completion_rate'] ?? 0) . '%'],
];
if(!is_null($advanced['pass_rate']))     $cards[] = ['label'=>'Pass Rate','value'=> $advanced['pass_rate'].'%'];
if(!is_null($advanced['average_score'])) $cards[] = ['label'=>'Average Score','value'=> $advanced['average_score']];
@endphp

<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-8">
    @foreach($cards as $c)
        <div class="rounded-xl border bg-white p-4">
            <div class="text-sm text-gray-500">{{ $c['label'] }}</div>
            <div class="text-2xl font-semibold mt-1">{{ $c['value'] }}</div>
        </div>
    @endforeach
</div>

@if(!empty($training->description))
    <div class="rounded-2xl border bg-white p-6 mb-8">
        <h2 class="font-semibold mb-2">Overview</h2>
        <p class="text-gray-700">{{ $training->description }}</p>
    </div>
@endif

<div class="rounded-2xl border bg-white p-6">
    <div class="flex items-center justify-between mb-4">
        <h2 class="font-semibold">Participants</h2>
        <form method="get" class="flex items-center gap-2">
            <input type="text" name="q" value="{{ $q }}" placeholder="Search name..." class="rounded-lg border-gray-300 text-sm" />
            <select name="attendance" class="rounded-lg border-gray-300 text-sm">
                <option value="">Attendance</option>
                <option value="present" {{ $attendance==='present'?'selected':'' }}>Present</option>
                <option value="absent"  {{ $attendance==='absent'?'selected':'' }}>Absent</option>
            </select>
            <select name="status" class="rounded-lg border-gray-300 text-sm">
                <option value="">Status</option>
                <option value="in_progress" {{ $status==='in_progress'?'selected':'' }}>In Progress</option>
                <option value="completed"   {{ $status==='completed'?'selected':'' }}>Completed</option>
            </select>
            <button class="rounded-lg border px-3 py-1.5 text-sm hover:bg-gray-50">Filter</button>
        </form>
    </div>

    @if($participants->count() === 0)
        <div class="text-gray-600 text-center py-8">No participants found.</div>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500">
                        <th class="py-2 pr-4">Name</th>
                        <th class="py-2 pr-4">Attendance</th>
                        <th class="py-2 pr-4">Status</th>
                        <th class="py-2 pr-4">Outcome</th>
                        <th class="py-2 pr-4">Registered</th>
                    </tr>
                </thead>
                <tbody class="align-top">
                    @foreach($participants as $p)
                        @php
                            $u = $p->user;
                            $name = $u->full_name ?? trim(($u->first_name ?? '').' '.($u->middle_name ?? '').' '.($u->last_name ?? '')) ?: ($u->name ?? '—');
                        @endphp
                        <tr class="border-t">
                            <td class="py-2 pr-4">{{ $name }}</td>
                            <td class="py-2 pr-4 capitalize">{{ $p->attendance_status ?? '—' }}</td>
                            <td class="py-2 pr-4 capitalize">{{ $p->completion_status ?? '—' }}</td>
                            <td class="py-2 pr-4">{{ $p->outcome->name ?? '—' }}</td>
                            <td class="py-2 pr-4">{{ $p->registration_date ? \Carbon\Carbon::parse($p->registration_date)->format('M d, Y') : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-6">{{ $participants->links() }}</div>
    @endif
</div>
@endsection
