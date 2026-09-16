@extends('layouts.app')

@section('title', 'Browse Resources - Advanced Search')
@section('meta_description', 'Advanced search and filtering for educational resources. Find exactly what you need with our comprehensive filtering system.')

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
            <span class="text-gray-500">Advanced Browse</span>
        </div>
    </li>
@endsection

@section('page_header')
    <div class="text-center">
        <h1 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">
            <i class="fas fa-search text-primary-600 mr-3"></i>
            Advanced Resource Search
        </h1>
        <p class="text-xl text-gray-600 max-w-3xl mx-auto">
            Use our powerful filtering system to find exactly the resources you need.
            Filter by categories, types, difficulty levels, and more.
        </p>
    </div>
@endsection

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8" x-data="browsePage">
    <!-- Advanced Search Form -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 md:p-8 mb-8">
        <form action="{{ route('resources.browse') }}" method="GET" id="advancedSearchForm">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Search Query -->
                <div class="lg:col-span-3">
                    <label for="search" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-search mr-2"></i>Search Keywords
                    </label>
                    <div class="relative">
                        <input type="text"
                               id="search"
                               name="search"
                               value="{{ request('search') }}"
                               placeholder="Enter keywords, topics, or specific terms..."
                               class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent text-lg">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-search text-gray-400"></i>
                        </div>
                    </div>
                </div>

                <!-- Categories -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-3">
                        <i class="fas fa-folder mr-2"></i>Categories
                    </label>
                    <div class="space-y-2 max-h-64 overflow-y-auto border border-gray-200 rounded-lg p-4">
                        @foreach($categories as $category)
                            <label class="flex items-center hover:bg-gray-50 p-2 rounded transition-colors">
                                <input type="checkbox"
                                       name="categories[]"
                                       value="{{ $category->id }}"
                                       {{ in_array($category->id, request('categories', [])) ? 'checked' : '' }}
                                       class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                                <div class="ml-3 flex-1">
                                    <span class="text-sm text-gray-900">{{ $category->name }}</span>
                                    <span class="text-xs text-gray-500 ml-2">({{ $category->resources_count }})</span>
                                </div>
                            </label>

                            @if($category->children->count() > 0)
                                <div class="ml-6 space-y-1">
                                    @foreach($category->children as $child)
                                        <label class="flex items-center hover:bg-gray-50 p-1 rounded transition-colors">
                                            <input type="checkbox"
                                                   name="categories[]"
                                                   value="{{ $child->id }}"
                                                   {{ in_array($child->id, request('categories', [])) ? 'checked' : '' }}
                                                   class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                                            <div class="ml-2 flex-1">
                                                <span class="text-xs text-gray-700">{{ $child->name }}</span>
                                                <span class="text-xs text-gray-400 ml-1">({{ $child->resources_count }})</span>
                                            </div>
                                        </label>
                                    @endforeach
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>

                <!-- Resource Types -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-3">
                        <i class="fas fa-shapes mr-2"></i>Resource Types
                    </label>
                    <div class="space-y-2 max-h-64 overflow-y-auto border border-gray-200 rounded-lg p-4">
                        @foreach($resourceTypes as $type)
                            <label class="flex items-center hover:bg-gray-50 p-2 rounded transition-colors">
                                <input type="checkbox"
                                       name="types[]"
                                       value="{{ $type->id }}"
                                       {{ in_array($type->id, request('types', [])) ? 'checked' : '' }}
                                       class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                                <div class="ml-3 flex items-center flex-1">
                                    @if($type->icon)
                                        <i class="{{ $type->icon }} text-primary-600 mr-2"></i>
                                    @endif
                                    <span class="text-sm text-gray-900">{{ $type->name }}</span>
                                    <span class="text-xs text-gray-500 ml-2">({{ $type->resources_count }})</span>
                                </div>
                            </label>
                        @endforeach
                    </div>
                </div>

                <!-- Additional Filters -->
                <div class="space-y-6">
                    <!-- Difficulty Levels -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-3">
                            <i class="fas fa-signal mr-2"></i>Difficulty Level
                        </label>
                        <div class="space-y-2">
                            <label class="flex items-center">
                                <input type="checkbox"
                                       name="difficulty_levels[]"
                                       value="beginner"
                                       {{ in_array('beginner', request('difficulty_levels', [])) ? 'checked' : '' }}
                                       class="rounded border-gray-300 text-green-600 focus:ring-green-500">
                                <span class="ml-2 text-sm text-gray-900 flex items-center">
                                    <span class="w-3 h-3 bg-green-500 rounded-full mr-2"></span>
                                    Beginner
                                </span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox"
                                       name="difficulty_levels[]"
                                       value="intermediate"
                                       {{ in_array('intermediate', request('difficulty_levels', [])) ? 'checked' : '' }}
                                       class="rounded border-gray-300 text-yellow-600 focus:ring-yellow-500">
                                <span class="ml-2 text-sm text-gray-900 flex items-center">
                                    <span class="w-3 h-3 bg-yellow-500 rounded-full mr-2"></span>
                                    Intermediate
                                </span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox"
                                       name="difficulty_levels[]"
                                       value="advanced"
                                       {{ in_array('advanced', request('difficulty_levels', [])) ? 'checked' : '' }}
                                       class="rounded border-gray-300 text-red-600 focus:ring-red-500">
                                <span class="ml-2 text-sm text-gray-900 flex items-center">
                                    <span class="w-3 h-3 bg-red-500 rounded-full mr-2"></span>
                                    Advanced
                                </span>
                            </label>
                        </div>
                    </div>

                    <!-- Tags -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-3">
                            <i class="fas fa-tags mr-2"></i>Tags
                        </label>
                        <div class="space-y-2 max-h-40 overflow-y-auto border border-gray-200 rounded-lg p-3">
                            @foreach($tags->take(20) as $tag)
                                <label class="flex items-center hover:bg-gray-50 p-1 rounded transition-colors">
                                    <input type="checkbox"
                                           name="tags[]"
                                           value="{{ $tag->id }}"
                                           {{ in_array($tag->id, request('tags', [])) ? 'checked' : '' }}
                                           class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                                    <span class="ml-2 text-xs text-gray-700">
                                        #{{ $tag->name }} ({{ $tag->resources_count }})
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <!-- Additional Options -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-3">
                            <i class="fas fa-cog mr-2"></i>Options
                        </label>
                        <div class="space-y-2">
                            <label class="flex items-center">
                                <input type="checkbox"
                                       name="is_downloadable"
                                       value="1"
                                       {{ request('is_downloadable') ? 'checked' : '' }}
                                       class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                                <span class="ml-2 text-sm text-gray-900">
                                    <i class="fas fa-download mr-1 text-primary-600"></i>
                                    Downloadable Only
                                </span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox"
                                       name="featured"
                                       value="1"
                                       {{ request('featured') ? 'checked' : '' }}
                                       class="rounded border-gray-300 text-yellow-600 focus:ring-yellow-500">
                                <span class="ml-2 text-sm text-gray-900">
                                    <i class="fas fa-star mr-1 text-yellow-600"></i>
                                    Featured Only
                                </span>
                            </label>
                        </div>
                    </div>

                    <!-- Date Range -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-3">
                            <i class="fas fa-calendar mr-2"></i>Publication Date
                        </label>
                        <div class="space-y-3">
                            <div>
                                <label for="date_from" class="block text-xs text-gray-600 mb-1">From</label>
                                <input type="date"
                                       id="date_from"
                                       name="date_from"
                                       value="{{ request('date_from') }}"
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                            </div>
                            <div>
                                <label for="date_to" class="block text-xs text-gray-600 mb-1">To</label>
                                <input type="date"
                                       id="date_to"
                                       name="date_to"
                                       value="{{ request('date_to') }}"
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sort Options -->
            <div class="mt-8 pt-6 border-t border-gray-200">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div class="flex items-center space-x-4">
                        <label for="sort" class="text-sm font-medium text-gray-700">
                            <i class="fas fa-sort mr-2"></i>Sort By:
                        </label>
                        <select name="sort"
                                id="sort"
                                class="border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                            <option value="latest" {{ request('sort') === 'latest' ? 'selected' : '' }}>Latest First</option>
                            <option value="popular" {{ request('sort') === 'popular' ? 'selected' : '' }}>Most Popular</option>
                            <option value="title" {{ request('sort') === 'title' ? 'selected' : '' }}>Title A-Z</option>
                            <option value="views" {{ request('sort') === 'views' ? 'selected' : '' }}>Most Viewed</option>
                            <option value="downloads" {{ request('sort') === 'downloads' ? 'selected' : '' }}>Most Downloaded</option>
                            <option value="oldest" {{ request('sort') === 'oldest' ? 'selected' : '' }}>Oldest First</option>
                        </select>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex items-center space-x-3">
                        <button type="button"
                                @click="clearAllFilters()"
                                class="inline-flex items-center px-4 py-2 text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">
                            <i class="fas fa-times mr-2"></i>
                            Clear All
                        </button>
                        <button type="submit"
                                class="inline-flex items-center px-6 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors font-medium">
                            <i class="fas fa-search mr-2"></i>
                            Search Resources
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- Active Filters Display -->
    @if(request()->hasAny(['search', 'categories', 'types', 'difficulty_levels', 'tags', 'is_downloadable', 'featured', 'date_from', 'date_to']))
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
        <div class="flex items-center justify-between mb-3">
            <h3 class="font-medium text-blue-900 flex items-center">
                <i class="fas fa-filter mr-2"></i>
                Active Filters ({{ $resources->total() }} results)
            </h3>
            <a href="{{ route('resources.browse') }}"
               class="text-sm text-blue-600 hover:text-blue-800 font-medium">
                Clear All Filters
            </a>
        </div>

        <div class="flex flex-wrap gap-2">
            @if(request('search'))
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-blue-100 text-blue-800">
                    Search: "{{ request('search') }}"
                    <button onclick="removeFilter('search')" class="ml-2 text-blue-600 hover:text-blue-800">
                        <i class="fas fa-times"></i>
                    </button>
                </span>
            @endif

            @foreach(request('categories', []) as $categoryId)
                @php $category = $categories->firstWhere('id', $categoryId) @endphp
                @if($category)
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-green-100 text-green-800">
                        Category: {{ $category->name }}
                        <button onclick="removeArrayFilter('categories', '{{ $categoryId }}')" class="ml-2 text-green-600 hover:text-green-800">
                            <i class="fas fa-times"></i>
                        </button>
                    </span>
                @endif
            @endforeach

            @foreach(request('types', []) as $typeId)
                @php $type = $resourceTypes->firstWhere('id', $typeId) @endphp
                @if($type)
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-purple-100 text-purple-800">
                        Type: {{ $type->name }}
                        <button onclick="removeArrayFilter('types', '{{ $typeId }}')" class="ml-2 text-purple-600 hover:text-purple-800">
                            <i class="fas fa-times"></i>
                        </button>
                    </span>
                @endif
            @endforeach

            @foreach(request('difficulty_levels', []) as $difficulty)
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm
                    @if($difficulty === 'beginner') bg-green-100 text-green-800
                    @elseif($difficulty === 'intermediate') bg-yellow-100 text-yellow-800
                    @else bg-red-100 text-red-800 @endif">
                    {{ ucfirst($difficulty) }}
                    <button onclick="removeArrayFilter('difficulty_levels', '{{ $difficulty }}')"
                            class="ml-2 @if($difficulty === 'beginner') text-green-600 hover:text-green-800
                                   @elseif($difficulty === 'intermediate') text-yellow-600 hover:text-yellow-800
                                   @else text-red-600 hover:text-red-800 @endif">
                        <i class="fas fa-times"></i>
                    </button>
                </span>
            @endforeach

            @if(request('is_downloadable'))
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-indigo-100 text-indigo-800">
                    Downloadable Only
                    <button onclick="removeFilter('is_downloadable')" class="ml-2 text-indigo-600 hover:text-indigo-800">
                        <i class="fas fa-times"></i>
                    </button>
                </span>
            @endif

            @if(request('featured'))
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-yellow-100 text-yellow-800">
                    Featured Only
                    <button onclick="removeFilter('featured')" class="ml-2 text-yellow-600 hover:text-yellow-800">
                        <i class="fas fa-times"></i>
                    </button>
                </span>
            @endif
        </div>
    </div>
    @endif

    <!-- Results Section -->
    <div class="flex flex-col lg:flex-row gap-8">
        <!-- Results Summary & View Controls -->
        <div class="lg:w-2/3">
            <div class="flex items-center justify-between mb-6">
                <div>
                    @if($resources->total() > 0)
                        <h3 class="text-lg font-medium text-gray-900">
                            {{ number_format($resources->total()) }} Resources Found
                        </h3>
                        <p class="text-sm text-gray-600">
                            Showing {{ number_format($resources->firstItem()) }}-{{ number_format($resources->lastItem()) }}
                        </p>
                    @else
                        <h3 class="text-lg font-medium text-gray-900">No Resources Found</h3>
                        <p class="text-sm text-gray-600">Try adjusting your search criteria</p>
                    @endif
                </div>

                <!-- View Toggle -->
                @if($resources->total() > 0)
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
                @endif
            </div>

            <!-- Resources Grid/List -->
            @if($resources->count() > 0)
                <!-- Grid View -->
                <div x-show="viewMode === 'grid'" class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
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
                    {{ $resources->appends(request()->query())->links('components.pagination') }}
                </div>
            @else
                <!-- No Results -->
                <div class="text-center py-16 bg-white rounded-lg shadow-sm border border-gray-200">
                    <div class="max-w-md mx-auto">
                        <i class="fas fa-search-minus text-4xl text-gray-300 mb-4"></i>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">No resources match your criteria</h3>
                        <p class="text-gray-600 mb-6">
                            Try broadening your search by removing some filters or using different keywords.
                        </p>
                        <div class="flex flex-col sm:flex-row gap-3 justify-center">
                            <button @click="clearAllFilters()"
                                    class="inline-flex items-center px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors">
                                <i class="fas fa-times mr-2"></i>
                                Clear All Filters
                            </button>
                            <a href="{{ route('resources.index') }}"
                               class="inline-flex items-center px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                                <i class="fas fa-list mr-2"></i>
                                View All Resources
                            </a>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <!-- Saved Searches Sidebar -->
        <aside class="lg:w-1/3">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 sticky top-24">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">
                    <i class="fas fa-bookmark mr-2"></i>Quick Searches
                </h3>

                <div class="space-y-3">
                    <a href="{{ route('resources.browse', ['is_downloadable' => 1]) }}"
                       class="block p-3 rounded-lg hover:bg-gray-50 transition-colors border border-gray-200">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-gray-900">Downloadable Resources</span>
                            <i class="fas fa-download text-primary-600"></i>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Resources you can download offline</p>
                    </a>

                    <a href="{{ route('resources.browse', ['featured' => 1]) }}"
                       class="block p-3 rounded-lg hover:bg-gray-50 transition-colors border border-gray-200">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-gray-900">Featured Content</span>
                            <i class="fas fa-star text-yellow-500"></i>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Hand-picked premium resources</p>
                    </a>

                    <a href="{{ route('resources.browse', ['difficulty_levels' => ['beginner']]) }}"
                       class="block p-3 rounded-lg hover:bg-gray-50 transition-colors border border-gray-200">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-gray-900">Beginner Friendly</span>
                            <i class="fas fa-seedling text-green-500"></i>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Perfect for getting started</p>
                    </a>

                    <a href="{{ route('resources.browse', ['sort' => 'popular']) }}"
                       class="block p-3 rounded-lg hover:bg-gray-50 transition-colors border border-gray-200">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-gray-900">Most Popular</span>
                            <i class="fas fa-fire text-red-500"></i>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Community favorites</p>
                    </a>
                </div>

                <!-- Search Tips -->
                <div class="mt-8 p-4 bg-blue-50 rounded-lg">
                    <h4 class="text-sm font-semibold text-blue-900 mb-2">
                        <i class="fas fa-lightbulb mr-2"></i>Search Tips
                    </h4>
                    <ul class="text-xs text-blue-800 space-y-1">
                        <li>• Use quotes for exact phrases</li>
                        <li>• Combine multiple filters for better results</li>
                        <li>• Try different keywords if no results</li>
                        <li>• Check spelling and try synonyms</li>
                    </ul>
                </div>
            </div>
        </aside>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('browsePage', () => ({
            viewMode: localStorage.getItem('browseViewMode') || 'grid',

            init() {
                this.$watch('viewMode', value => {
                    localStorage.setItem('browseViewMode', value);
                });
            },

            clearAllFilters() {
                window.location.href = '{{ route("resources.browse") }}';
            }
        }))
    })

    function removeFilter(filterName) {
        const url = new URL(window.location);
        url.searchParams.delete(filterName);
        url.searchParams.delete('page');
        window.location.href = url.toString();
    }

    function removeArrayFilter(filterName, value) {
        const url = new URL(window.location);
        const currentValues = url.searchParams.getAll(filterName + '[]');

        // Remove all current values
        url.searchParams.delete(filterName + '[]');

        // Add back all values except the one to remove
        currentValues.forEach(val => {
            if (val !== value) {
                url.searchParams.append(filterName + '[]', val);
            }
        });

        url.searchParams.delete('page');
        window.location.href = url.toString();
    }

    // Auto-submit form on filter changes
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('advancedSearchForm');
        const checkboxes = form.querySelectorAll('input[type="checkbox"]');
        const selects = form.querySelectorAll('select');
        const dateInputs = form.querySelectorAll('input[type="date"]');

        // Auto-submit on checkbox changes (with debounce)
        let timeout;
        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                clearTimeout(timeout);
                timeout = setTimeout(() => {
                    form.submit();
                }, 500);
            });
        });

        // Auto-submit on select changes
        selects.forEach(select => {
            select.addEventListener('change', function() {
                form.submit();
            });
        });

        // Auto-submit on date changes
        dateInputs.forEach(input => {
            input.addEventListener('change', function() {
                form.submit();
            });
        });
    });
</script>
@endpush
@endsection
