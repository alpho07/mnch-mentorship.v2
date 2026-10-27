@extends('layouts.app')

@section('title', 'My downloads')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-8 mb-8">
        <h1 class="text-3xl font-bold text-gray-900">My downloads</h1>
        <p class="text-gray-600 mt-1">{{ number_format($downloads->total()) }} {{ Str::plural('download', $downloads->total()) }}</p>
    </div>

    @php($items = $downloads->filter(fn ($d) => $d->resource))
    @if($items->count())
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($items as $download)
                @include('components.resource-card', ['resource' => $download->resource])
            @endforeach
        </div>
        <div class="mt-8">{{ $downloads->links() }}</div>
    @else
        <div class="text-center text-gray-500 py-16">
            <i class="fas fa-download text-4xl mb-3"></i>
            <p>You have not downloaded any resources yet.</p>
            <a href="{{ route('resources.index') }}" class="inline-block mt-4 text-primary-600 underline">Browse resources</a>
        </div>
    @endif
</div>
@endsection
