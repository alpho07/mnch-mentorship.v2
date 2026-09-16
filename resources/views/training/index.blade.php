@extends('layouts.app')
@section('title','Training')

@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-semibold">Training</h1>
    <a href="{{ route('training.heatmap') }}" class="inline-flex items-center gap-2 rounded-xl border px-4 py-2 hover:bg-gray-50">
        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M4 6l4 2 4-2 4 2 4-2v12l-4 2-4-2-4 2-4-2z"/><path d="M8 8v12M16 6v12"/></svg>
        MOH Training Heatmap
    </a>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <a href="{{ route('training.moh') }}" class="rounded-2xl border bg-white p-6 hover:shadow-lg transition">
        <h2 class="text-xl font-semibold">MOH</h2>
        <p class="mt-2 text-gray-600">Official training modules, courses, and national programs.</p>
    </a>
    <a href="{{ route('training.mentorship') }}" class="rounded-2xl border bg-white p-6 hover:shadow-lg transition">
        <h2 class="text-xl font-semibold">Mentorship</h2>
        <p class="mt-2 text-gray-600">Facility-based mentorships and practice sessions.</p>
    </a>
</div>

<div class="mt-10">
    <div class="flex items-center justify-between">
        <h2 class="text-lg font-semibold">Recent MOH Trainings</h2>
        <a href="{{ route('training.moh') }}" class="text-sm text-indigo-700 hover:underline">View all</a>
    </div>
    @if($moh->isEmpty())
        <div class="mt-3 rounded-xl border bg-white p-6 text-center text-gray-600">No MOH trainings yet.</div>
    @else
        <div class="mt-4 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($moh as $t) @include('training._card', ['t' => $t]) @endforeach
        </div>
    @endif
</div>

<div class="mt-10">
    <div class="flex items-center justify-between">
        <h2 class="text-lg font-semibold">Recent Mentorships</h2>
        <a href="{{ route('training.mentorship') }}" class="text-sm text-indigo-700 hover:underline">View all</a>
    </div>
    @if($mentorship->isEmpty())
        <div class="mt-3 rounded-xl border bg-white p-6 text-center text-gray-600">No mentorships yet.</div>
    @else
        <div class="mt-4 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($mentorship as $t) @include('training._card', ['t' => $t]) @endforeach
        </div>
    @endif
</div>
@endsection
