@extends('layouts.app')
@section('title','Mentorship')

@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-semibold">Mentorship</h1>
</div>

<form method="get" class="mb-4">
    @php $status = request('status','upcoming'); @endphp
    <select name="status" onchange="this.form.submit()" class="rounded-lg border-gray-300 text-sm">
        <option value="upcoming" {{ $status==='upcoming'?'selected':'' }}>Upcoming</option>
        <option value="ongoing" {{ $status==='ongoing'?'selected':'' }}>Ongoing</option>
        <option value="completed" {{ $status==='completed'?'selected':'' }}>Completed</option>
        <option value="all" {{ $status==='all'?'selected':'' }}>All</option>
    </select>
</form>

@if($trainings->count() === 0)
    <div class="rounded-xl border bg-white p-8 text-center text-gray-600">No mentorships found.</div>
@else
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach($trainings as $t) @include('training._card', ['t' => $t]) @endforeach
    </div>
    <div class="mt-8">{{ $trainings->links() }}</div>
@endif
@endsection
