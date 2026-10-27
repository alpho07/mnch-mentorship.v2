@extends('layouts.app')

@section('title', 'My bookmarks')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-8 mb-8">
        <h1 class="text-3xl font-bold text-gray-900">My bookmarks</h1>
        <p class="text-gray-600 mt-1">{{ number_format($resources->total()) }} saved {{ Str::plural('resource', $resources->total()) }}</p>
    </div>

    @if($resources->count())
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($resources as $resource)
                @include('components.resource-card', ['resource' => $resource])
            @endforeach
        </div>
        <div class="mt-8">{{ $resources->links() }}</div>
    @else
        <div class="text-center text-gray-500 py-16">
            <i class="far fa-bookmark text-4xl mb-3"></i>
            <p>You have not saved any resources yet.</p>
            <a href="{{ route('resources.index') }}" class="inline-block mt-4 text-primary-600 underline">Browse resources</a>
        </div>
    @endif
</div>
@endsection
