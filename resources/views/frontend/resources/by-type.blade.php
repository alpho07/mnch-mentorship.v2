@extends('layouts.app')

@section('title', "{$type->name} resources")
@section('meta_description', "Browse {$type->name} resources in the knowledge base.")

@section('breadcrumbs')
    <li>
        <div class="flex items-center">
            <i class="fas fa-chevron-right text-gray-400 mx-1"></i>
            <a href="{{ route('resources.index') }}" class="text-gray-500 hover:text-gray-700">Resources</a>
        </div>
    </li>
    <li>
        <div class="flex items-center">
            <i class="fas fa-chevron-right text-gray-400 mx-1"></i>
            <span class="text-gray-500">{{ $type->name }}</span>
        </div>
    </li>
@endsection

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-8 mb-8">
        <h1 class="text-3xl font-bold text-gray-900">{{ $type->name }}</h1>
        <p class="text-gray-600 mt-1">
            {{ number_format($resources->total()) }} {{ Str::plural('resource', $resources->total()) }} found
        </p>
        @if($type->description)
            <p class="text-gray-700 text-lg leading-relaxed mt-4">{{ $type->description }}</p>
        @endif
    </div>

    @if($resources->count())
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($resources as $resource)
                @include('components.resource-card', ['resource' => $resource])
            @endforeach
        </div>

        <div class="mt-8">
            {{ $resources->links() }}
        </div>
    @else
        <div class="text-center text-gray-500 py-16">
            <i class="fas fa-box-open text-4xl mb-3"></i>
            <p>No {{ strtolower($type->name) }} resources are available yet.</p>
        </div>
    @endif
</div>
@endsection
