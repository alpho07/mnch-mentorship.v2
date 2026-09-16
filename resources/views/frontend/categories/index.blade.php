@extends('layouts.app')

@section('title', 'Categories')
@section('meta_description', 'Browse all resource categories and discover content organized by topics and subjects.')

@section('breadcrumbs')
    <li>
        <div class="flex items-center">
            <i class="fas fa-chevron-right text-gray-400 mx-2"></i>
            <span class="text-gray-500">Categories</span>
        </div>
    </li>
@endsection

@section('page_header')
    <div class="text-center">
        <h1 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">
            <i class="fas fa-folder-open text-primary-600 mr-3"></i>
            Browse Categories
        </h1>
        <p class="text-xl text-gray-600 max-w-2xl mx-auto">
            Discover resources organized by topics and subjects. Find exactly what you're looking for.
        </p>
    </div>
@endsection

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    @if($categories->count() > 0)
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            @foreach($categories as $category)
                <div class="bg-white rounded-xl shadow-sm hover:shadow-lg transition-all duration-300 overflow-hidden group border border-gray-200 hover:border-primary-300">
                    <!-- Category Header -->
                    <div class="p-6 border-b border-gray-100">
                        <div class="flex items-center mb-4">
                            @if($category->image)
                                <img src="{{ Storage::url($category->image) }}"
                                     alt="{{ $category->name }}"
                                     class="w-16 h-16 rounded-lg object-cover mr-4">
                            @else
                                <div class="w-16 h-16 bg-primary-100 rounded-lg flex items-center justify-center mr-4 group-hover:bg-primary-200 transition-colors">
                                    @if($category->icon)
                                        <i class="{{ $category->icon }} text-2xl text-primary-600"></i>
                                    @else
                                        <i class="fas fa-folder text-2xl text-primary-600"></i>
                                    @endif
                                </div>
                            @endif

                            <div class="flex-1">
                                <h3 class="text-xl font-bold text-gray-900 group-hover:text-primary-600 transition-colors">
                                    <a href="{{ route('resources.category', $category->slug) }}">
                                        {{ $category->name }}
                                    </a>
                                </h3>
                                <p class="text-sm text-gray-500">
                                    {{ number_format($category->resources_count) }} resources
                                </p>
                            </div>
                        </div>

                        @if($category->description)
                            <p class="text-gray-600 mb-4">
                                {{ Str::limit($category->description, 120) }}
                            </p>
                        @endif

                        <a href="{{ route('resources.category', $category->slug) }}"
                           class="inline-flex items-center text-primary-600 hover:text-primary-700 font-medium group-hover:text-primary-700 transition-colors">
                            Browse Resources
                            <i class="fas fa-arrow-right ml-2 group-hover:translate-x-1 transition-transform"></i>
                        </a>
                    </div>

                    <!-- Subcategories -->
                    @if($category->children->count() > 0)
                        <div class="p-6">
                            <h4 class="text-sm font-semibold text-gray-700 mb-3 uppercase tracking-wide">
                                Subcategories
                            </h4>
                            <div class="grid grid-cols-1 gap-2">
                                @foreach($category->children->take(6) as $child)
                                    <a href="{{ route('resources.category', $child->slug) }}"
                                       class="flex items-center justify-between p-3 rounded-lg hover:bg-gray-50 transition-colors group/item">
                                        <span class="text-sm text-gray-700 group-hover/item:text-primary-600 transition-colors">
                                            {{ $child->name }}
                                        </span>
                                        <span class="text-xs text-gray-500 bg-gray-100 px-2 py-1 rounded-full">
                                            {{ $child->resources_count }}
                                        </span>
                                    </a>
                                @endforeach

                                @if($category->children->count() > 6)
                                    <div class="text-center pt-2">
                                        <a href="{{ route('resources.category', $category->slug) }}"
                                           class="text-sm text-primary-600 hover:text-primary-700 font-medium">
                                            +{{ $category->children->count() - 6 }} more subcategories
                                        </a>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        <!-- Popular Tags Section -->
        @php
            $popularTags = \App\Models\Tag::popular(20)->get();
        @endphp

        @if($popularTags->count() > 0)
            <div class="mt-16 bg-white rounded-xl shadow-sm border border-gray-200 p-8">
                <div class="text-center mb-8">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">
                        <i class="fas fa-tags text-primary-600 mr-2"></i>
                        Popular Tags
                    </h2>
                    <p class="text-gray-600">
                        Discover content by popular topics and keywords
                    </p>
                </div>

                <div class="flex flex-wrap justify-center gap-3">
                    @foreach($popularTags as $tag)
                        <a href="{{ route('resources.tag', $tag->slug) }}"
                           class="inline-flex items-center px-4 py-2 rounded-full text-sm font-medium bg-gray-100 text-gray-800 hover:bg-primary-100 hover:text-primary-800 transition-all duration-200 hover:scale-105">
                            #{{ $tag->name }}
                            <span class="ml-2 text-xs bg-gray-200 text-gray-600 px-2 py-0.5 rounded-full">
                                {{ $tag->resource_count }}
                            </span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    @else
        <!-- No Categories State -->
        <div class="text-center py-16">
            <div class="max-w-md mx-auto">
                <i class="fas fa-folder-open text-6xl text-gray-300 mb-6"></i>
                <h3 class="text-2xl font-bold text-gray-900 mb-4">No Categories Available</h3>
                <p class="text-gray-600 mb-8">
                    Categories haven't been created yet. Check back later for organized content.
                </p>
                <a href="{{ route('resources.index') }}"
                   class="inline-flex items-center px-6 py-3 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors">
                    <i class="fas fa-list mr-2"></i>
                    View All Resources
                </a>
            </div>
        </div>
    @endif
</div>
@endsection
