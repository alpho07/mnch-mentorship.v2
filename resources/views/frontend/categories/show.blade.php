@extends('layouts.app')

@section('title', $category->name . ' Resources')
@section('meta_description', $category->description ?: "Browse {$category->name} resources and learning materials.")

@section('breadcrumbs')
    <li>
        <div class="flex items-center">
            <i class="fas fa-chevron-right text-gray-400 mx-2"></i>
            <a href="{{ route('resources.index') }}" class="text-gray-500 hover:text-gray-700">Resources</a>
        </div>
    </li>
    <li>
        <div class="flex items-center">
            <i class="fas fa-chevron-right text-gray-400 mx-2"></i>
            <a href="{{ route('categories.index') }}" class="text-gray-500 hover:text-gray-700">Categories</a>
        </div>
    </li>
    @if($category->parent)
    <li>
        <div class="flex items-center">
            <i class="fas fa-chevron-right text-gray-400 mx-2"></i>
            <a href="{{ route('resources.category', $category->parent->slug) }}" class="text-gray-500 hover:text-gray-700">
                {{ $category->parent->name }}
            </a>
        </div>
    </li>
    @endif
    <li>
        <div class="flex items-center">
            <i class="fas fa-chevron-right text-gray-400 mx-2"></i>
            <span class="text-gray-500">{{ $category->name }}</span>
        </div>
    </li>
@endsection

@section('page_header')
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between">
        <div class="flex items-center">
            @if($category->image)
                <img src="{{ Storage::url($category->image) }}"
                     alt="{{ $category->name }}"
                     class="w-20 h-20 rounded-xl object-cover mr-6">
            @else
                <div class="w-20 h-20 bg-primary-100 rounded-xl flex items-center justify-center mr-6">
                    @if($category->icon)
                        <i class="{{ $category->icon }} text-3xl text-primary-600"></i>
                    @else
                        <i class="fas fa-folder text-3xl text-primary-600"></i>
                    @endif
                </div>
            @endif

            <div>
                <h1 class="text-3xl md:text-4xl font-bold text-gray-900">
                    {{ $category->name }}
                </h1>
                <p class="mt-2 text-lg text-gray-600">
                    {{ number_format($resources->total()) }} resources available
                </p>
                @if($category->description)
                    <p class="mt-2 text-gray-600 max-w-2xl">
                        {{ $category->description }}
                    </p>
                @endif
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="mt-6 lg:mt-0 flex flex-col sm:flex-row gap-3">
            <a href="{{ route('resources.browse', ['categories' => [$category->id]]) }}"
               class="inline-flex items-center px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors">
                <i class="fas fa-filter mr-2"></i>
                Advanced Filter
            </a>
            <a href="{{ route('categories.index') }}"
               class="inline-flex items-center px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                <i class="fas fa-folder mr-2"></i>
                All Categories
            </a>
        </div>
    </div>
@endsection

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8" x-data="categoryPage">
    <div class="flex flex-col lg:flex-row gap-8">
        <!-- Sidebar -->
        <aside class="lg:w-80 flex-shrink-0">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 sticky top-24">
                <div class="p-6">
                    <!-- Subcategories -->
                    @if($category->children->count() > 0)
                    <div class="mb-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">
                            <i class="fas fa-sitemap mr-2"></i>Subcategories
                        </h3>
                        <div class="space-y-2">
                            <a href="{{ route('resources.category', $category->slug) }}"
                               class="flex items-center justify-between p-3 rounded-lg {{ request('subcategory') ? 'hover:bg-gray-50' : 'bg-primary-50 text-primary-700' }} transition-colors">
                                <span class="font-medium">All {{ $category->name }}</span>
                                <span class="text-sm bg-primary-100 text-primary-600 px-2 py-1 rounded-full">
                                    {{ $resources->total() }}
                                </span>
                            </a>
                            @foreach($category->children as $child)
                                <a href="{{ route('resources.category', $child->slug) }}"
                                   class="flex items-center justify-between p-3 rounded-lg hover:bg-gray-50 transition-colors">
                                    <span>{{ $child->name }}</span>
                                    <span class="text-sm text-gray-500 bg-gray-100 px-2 py-1 rounded-full">
                                        {{ $child->resources_count }}
                                    </span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    <!-- Filters -->
                    <div class="mb-6">
                        <h4 class="font-medium text-gray-900 mb-3">Sort By</h4>
                        <select name="sort" onchange="updateSort(this.value)"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                            <option value="latest" {{ request('sort') === 'latest' ? 'selected' : '' }}>Latest First</option>
                            <option value="popular" {{ request('sort') === 'popular' ? 'selected' : '' }}>Most Popular</option>
                            <option value="title" {{ request('sort') === 'title' ? 'selected' : '' }}>Title A-Z</option>
                            <option value="views" {{ request('sort') === 'views' ? 'selected' : '' }}>Most Viewed</option>
                            <option value="downloads" {{ request('sort') === 'downloads' ? 'selected' : '' }}>Most Downloaded</option>
                        </select>
                    </div>

                    <!-- Resource Type Filter -->
                    @php
                        $categoryTypes = \App\Models\ResourceType::whereHas('resources', function($q) use ($category) {
                            $q->where('category_id', $category->id)->published();
                        })->withCount('resources')->get();
                    @endphp

                    @if($categoryTypes->count() > 0)
                    <div class="mb-6">
                        <h4 class="font-medium text-gray-900 mb-3">Resource Types</h4>
                        <div class="space-y-2">
                            @foreach($categoryTypes as $type)
                                <label class="flex items-center">
                                    <input type="checkbox"
                                           value="{{ $type->slug }}"
                                           {{ request('type') === $type->slug ? 'checked' : '' }}
                                           onchange="updateTypeFilter(this)"
                                           class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                                    <span class="ml-2 text-sm text-gray-700">
                                        {{ $type->name }} ({{ $type->resources_count }})
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    <!-- Difficulty Filter -->
                    <div class="mb-6">
                        <h4 class="font-medium text-gray-900 mb-3">Difficulty</h4>
                        <div class="space-y-2">
                            <label class="flex items-center">
                                <input type="radio"
                                       name="difficulty_filter"
                                       value=""
                                       {{ !request('difficulty') ? 'checked' : '' }}
                                       onchange="updateDifficultyFilter(this)"
                                       class="border-gray-300 text-primary-600 focus:ring-primary-500">
                                <span class="ml-2 text-sm text-gray-700">All Levels</span>
                            </label>
                            <label class="flex items-center">
                                <input type="radio"
                                       name="difficulty_filter"
                                       value="beginner"
                                       {{ request('difficulty') === 'beginner' ? 'checked' : '' }}
                                       onchange="updateDifficultyFilter(this)"
                                       class="border-gray-300 text-primary-600 focus:ring-primary-500">
                                <span class="ml-2 text-sm text-gray-700">Beginner</span>
                            </label>
                            <label class="flex items-center">
                                <input type="radio"
                                       name="difficulty_filter"
                                       value="intermediate"
                                       {{ request('difficulty') === 'intermediate' ? 'checked' : '' }}
                                       onchange="updateDifficultyFilter(this)"
                                       class="border-gray-300 text-primary-600 focus:ring-primary-500">
                                <span class="ml-2 text-sm text-gray-700">Intermediate</span>
                            </label>
                            <label class="flex items-center">
                                <input type="radio"
                                       name="difficulty_filter"
                                       value="advanced"
                                       {{ request('difficulty') === 'advanced' ? 'checked' : '' }}
                                       onchange="updateDifficultyFilter(this)"
                                       class="border-gray-300 text-primary-600 focus:ring-primary-500">
                                <span class="ml-2 text-sm text-gray-700">Advanced</span>
                            </label>
                        </div>
                    </div>

                    <!-- Category Stats -->
                    <div class="pt-6 border-t border-gray-200">
                        <h4 class="font-medium text-gray-900 mb-3">Category Stats</h4>
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-600">Total Resources</span>
                                <span class="font-medium">{{ number_format($resources->total()) }}</span>
                            </div>
                            @if($category->children->count() > 0)
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-600">Subcategories</span>
                                <span class="font-medium">{{ $category->children->count() }}</span>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1">
            <!-- Search within category -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 mb-6">
                <form action="{{ route('resources.category', $category->slug) }}" method="GET" class="flex gap-3">
                    @foreach(request()->except(['search', 'page']) as $key => $value)
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endforeach

                    <div class="flex-1 relative">
                        <input type="text"
                               name="search"
                               value="{{ request('search') }}"
                               placeholder="Search in {{ $category->name }}..."
                               class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-search text-gray-400"></i>
                        </div>
                    </div>
                    <button type="submit"
                            class="px-6 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors">
                        Search
                    </button>
                </form>
            </div>

            <!-- Active Filters -->
            @if(request()->hasAny(['type', 'difficulty', 'search']))
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 mb-6">
                <div class="flex items-center justify-between">
                    <h3 class="font-medium text-gray-900">Active Filters:</h3>
                    <a href="{{ route('resources.category', $category->slug) }}" class="text-sm text-primary-600 hover:text-primary-700">
                        Clear All
                    </a>
                </div>
                <div class="flex flex-wrap gap-2 mt-2">
                    @if(request('search'))
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-primary-100 text-primary-800">
                            Search: "{{ request('search') }}"
                            <a href="{{ route('resources.category', [$category->slug] + request()->except('search')) }}" class="ml-2 text-primary-600 hover:text-primary-800">
                                <i class="fas fa-times"></i>
                            </a>
                        </span>
                    @endif
                    @if(request('type'))
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-green-100 text-green-800">
                            Type: {{ $categoryTypes->where('slug', request('type'))->first()?->name ?? request('type') }}
                            <a href="{{ route('resources.category', [$category->slug] + request()->except('type')) }}" class="ml-2 text-green-600 hover:text-green-800">
                                <i class="fas fa-times"></i>
                            </a>
                        </span>
                    @endif
                    @if(request('difficulty'))
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-yellow-100 text-yellow-800">
                            Difficulty: {{ ucfirst(request('difficulty')) }}
                            <a href="{{ route('resources.category', [$category->slug] + request()->except('difficulty')) }}" class="ml-2 text-yellow-600 hover:text-yellow-800">
                                <i class="fas fa-times"></i>
                            </a>
                        </span>
                    @endif
                </div>
            </div>
            @endif

            <!-- Results Header -->
            <div class="flex items-center justify-between mb-6">
                <div>
                    <p class="text-gray-600">
                        Showing {{ number_format($resources->firstItem()) }}-{{ number_format($resources->lastItem()) }}
                        of {{ number_format($resources->total()) }} resources
                    </p>
                </div>

                <!-- View Toggle -->
                <div class="flex items-center space-x-3">
                    <span class="text-sm text-gray-600">View:</span>
                    <div class="flex border border-gray-300 rounded-lg overflow-hidden">
                        <button @click="viewMode = 'grid'"
                                :class="viewMode === 'grid' ? 'bg-primary-600 text-white' : 'bg-white text-gray-700'"
                                class="px-3 py-2 hover:bg-primary-50 transition-colors">
                            <i class="fas fa-th-large"></i>
                        </button>
                        <button @click="viewMode = 'list'"
                                :class="viewMode === 'list' ? 'bg-primary-600 text-white' : 'bg-white text-gray-700'"
                                class="px-3 py-2 hover:bg-primary-50 transition-colors border-l border-gray-300">
                            <i class="fas fa-list"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Resources Grid/List -->
            @if($resources->count() > 0)
                <!-- Grid View -->
                <div x-show="viewMode === 'grid'" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6 mb-8">
                    @foreach($resources as $resource)
                        @include('components.resource-card', ['resource' => $resource])
                    @endforeach
                </div>

                <!-- List View -->
                <div x-show="viewMode === 'list'" class="space-y-4 mb-8">
                    @foreach($resources as $resource)
                        @include('components.resource-list-item', ['resource' => $resource])
                    @endforeach
                </div>

                <!-- Pagination -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    {{ $resources->links('components.pagination') }}
                </div>
            @else
                <!-- No Results -->
                <div class="text-center py-16 bg-white rounded-lg shadow-sm border border-gray-200">
                    <div class="max-w-md mx-auto">
                        <i class="fas fa-search text-4xl text-gray-300 mb-4"></i>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">No resources found in {{ $category->name }}</h3>
                        <p class="text-gray-600 mb-6">
                            @if(request()->hasAny(['type', 'difficulty', 'search']))
                                Try adjusting your filters or search terms.
                            @else
                                This category doesn't have any resources yet.
                            @endif
                        </p>
                        <div class="flex flex-col sm:flex-row gap-3 justify-center">
                            @if(request()->hasAny(['type', 'difficulty', 'search']))
                                <a href="{{ route('resources.category', $category->slug) }}"
                                   class="inline-flex items-center px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors">
                                    <i class="fas fa-times mr-2"></i>
                                    Clear Filters
                                </a>
                            @endif
                            <a href="{{ route('categories.index') }}"
                               class="inline-flex items-center px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                                <i class="fas fa-folder mr-2"></i>
                                Browse Other Categories
                            </a>
                        </div>
                    </div>
                </div>
            @endif
        </main>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('categoryPage', () => ({
            viewMode: localStorage.getItem('categoryViewMode') || 'grid',

            init() {
                this.$watch('viewMode', value => {
                    localStorage.setItem('categoryViewMode', value);
                });
            }
        }))
    })

    function updateSort(value) {
        const url = new URL(window.location);
        if (value) {
            url.searchParams.set('sort', value);
        } else {
            url.searchParams.delete('sort');
        }
        url.searchParams.delete('page');
        window.location.href = url.toString();
    }

    function updateTypeFilter(checkbox) {
        const url = new URL(window.location);
        if (checkbox.checked) {
            url.searchParams.set('type', checkbox.value);
        } else {
            url.searchParams.delete('type');
        }
        url.searchParams.delete('page');
        window.location.href = url.toString();
    }

    function updateDifficultyFilter(radio) {
        const url = new URL(window.location);
        if (radio.value) {
            url.searchParams.set('difficulty', radio.value);
        } else {
            url.searchParams.delete('difficulty');
        }
        url.searchParams.delete('page');
        window.location.href = url.toString();
    }
</script>
@endpush
@endsection
