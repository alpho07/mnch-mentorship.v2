@extends('layouts.app')

@section('title', 'All Resources')
@section('meta_description', 'Browse our complete collection of educational resources, tools, and learning materials.')

@section('breadcrumbs')
    <li>
        <div class="flex items-center">
            <i class="fas fa-chevron-right text-gray-400 mx-2"></i>
            <span class="text-gray-500">Resources</span>
        </div>
    </li>
@endsection

@section('content')
{{-- ═══════════════════════════════════════════
     RESOURCE LIBRARY HERO
═══════════════════════════════════════════ --}}
<section class="relative overflow-hidden" style="background: linear-gradient(135deg, #16224A 0%, #1B2E5E 48%, #2C478D 100%);">
    <div class="absolute inset-0 opacity-[0.07]">
        <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none">
            <pattern id="resources-hero-grid" width="42" height="42" patternUnits="userSpaceOnUse">
                <path d="M 42 0 L 0 0 0 42" fill="none" stroke="white" stroke-width="1"/>
            </pattern>
            <rect width="100%" height="100%" fill="url(#resources-hero-grid)"/>
        </svg>
    </div>
    <div class="absolute -right-20 -top-20 h-72 w-72 rounded-full opacity-20" style="background: radial-gradient(circle, #6FC4EF 0%, transparent 68%);"></div>

    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-6">
            <div>
                <div class="inline-flex items-center gap-2 rounded-full border border-white/20 px-3 py-1 text-xs font-bold uppercase tracking-widest text-sky-100 mb-4"
                     style="background: rgba(255,255,255,0.08);">
                    <i class="fas fa-book-medical text-sky-200"></i>
                    Clinical Knowledge Library
                </div>
                <h1 class="font-display text-3xl md:text-4xl font-extrabold text-white tracking-tight">
                    Resources for maternal, newborn &amp; child health
                </h1>
                <p class="mt-3 text-sm md:text-base text-sky-100/90 leading-relaxed max-w-2xl">
                    {{ number_format($resources->total()) }} manuals, guidelines, and training materials — curated for
                    healthcare workers mentoring across Kenya.
                </p>
            </div>

            <!-- Quick Actions -->
            <div class="flex flex-col sm:flex-row gap-3 flex-shrink-0">
                <a href="{{ route('resources.browse') }}"
                   class="inline-flex items-center justify-center gap-2 px-5 py-3 bg-white text-primary-700 rounded-xl font-bold text-sm hover:bg-gray-100 transition-all shadow-lg">
                    <i class="fas fa-sliders"></i>
                    Advanced Search
                </a>
                <a href="{{ route('categories.index') }}"
                   class="inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl font-bold text-sm text-white border-2 border-white/30 hover:border-white/60 transition-all"
                   style="background: rgba(255,255,255,0.12);">
                    <i class="fas fa-folder-open"></i>
                    Browse Categories
                </a>
            </div>
        </div>

        {{-- Pillar quick-filter chips --}}
        <div class="flex flex-wrap gap-2.5 mt-8">
            <a href="{{ route('resources.category', 'maternal-health') }}"
               class="inline-flex items-center gap-2 px-4 py-2 rounded-full text-xs font-bold text-white transition-transform hover:scale-105"
               style="background: linear-gradient(135deg, #C81E70 0%, #8F1152 100%);">
                <i class="fas fa-heart-pulse"></i> Maternal Health
            </a>
            <a href="{{ route('resources.category', 'newborn-care') }}"
               class="inline-flex items-center gap-2 px-4 py-2 rounded-full text-xs font-bold text-white transition-transform hover:scale-105"
               style="background: linear-gradient(135deg, #A855C8 0%, #6B2E8C 100%);">
                <i class="fas fa-baby-carriage"></i> Newborn Care
            </a>
            <a href="{{ route('resources.category', 'child-health') }}"
               class="inline-flex items-center gap-2 px-4 py-2 rounded-full text-xs font-bold text-white transition-transform hover:scale-105"
               style="background: linear-gradient(135deg, #7DB83A 0%, #4B7A1A 100%);">
                <i class="fas fa-child-reaching"></i> Infant &amp; Child
            </a>
        </div>
    </div>
</section>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8" x-data="resourcesPage">
    <div class="flex flex-col lg:flex-row gap-8">
        <!-- Sidebar Filters -->
        <aside class="lg:w-80 flex-shrink-0">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 sticky top-24">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">
                        <i class="fas fa-filter mr-2"></i>Filter Resources
                    </h3>

                    <!-- Search within results -->
                    <form action="{{ route('resources.index') }}" method="GET" class="mb-6">
                        @foreach(request()->except(['search', 'page']) as $key => $value)
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                        @endforeach

                        <div class="relative">
                            <input type="text"
                                   name="search"
                                   value="{{ request('search') }}"
                                   placeholder="Search in results..."
                                   class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-search text-gray-400"></i>
                            </div>
                        </div>
                        <button type="submit" class="w-full mt-2 bg-primary-600 text-white py-2 rounded-lg hover:bg-primary-700 transition-colors">
                            Search
                        </button>
                    </form>

                    <!-- Sort Options -->
                    <div class="mb-6">
                        <h4 class="font-medium text-gray-900 mb-3">Sort By</h4>
                        <select name="sort" onchange="updateFilters()"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                            <option value="latest" {{ request('sort') === 'latest' ? 'selected' : '' }}>Latest First</option>
                            <option value="popular" {{ request('sort') === 'popular' ? 'selected' : '' }}>Most Popular</option>
                            <option value="title" {{ request('sort') === 'title' ? 'selected' : '' }}>Title A-Z</option>
                            <option value="views" {{ request('sort') === 'views' ? 'selected' : '' }}>Most Viewed</option>
                            <option value="downloads" {{ request('sort') === 'downloads' ? 'selected' : '' }}>Most Downloaded</option>
                            <option value="oldest" {{ request('sort') === 'oldest' ? 'selected' : '' }}>Oldest First</option>
                        </select>
                    </div>

                    <!-- Categories Filter -->
                    @if($categories->count() > 0)
                    <div class="mb-6">
                        <h4 class="font-medium text-gray-900 mb-3">Categories</h4>
                        <div class="space-y-2 max-h-48 overflow-y-auto">
                            @foreach($categories as $category)
                                <label class="flex items-center">
                                    <input type="checkbox"
                                           value="{{ $category->slug }}"
                                           {{ request('category') === $category->slug ? 'checked' : '' }}
                                           onchange="updateCategoryFilter(this)"
                                           class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                                    <span class="ml-2 text-sm text-gray-700">
                                        {{ $category->name }} ({{ $category->resources_count }})
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    <!-- Resource Types Filter -->
                    @if($resourceTypes->count() > 0)
                    <div class="mb-6">
                        <h4 class="font-medium text-gray-900 mb-3">Types</h4>
                        <div class="space-y-2">
                            @foreach($resourceTypes as $type)
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

                    <!-- Popular Tags -->
                    @if($popularTags->count() > 0)
                    <div class="mb-6">
                        <h4 class="font-medium text-gray-900 mb-3">Popular Tags</h4>
                        <div class="flex flex-wrap gap-2">
                            @foreach($popularTags->take(10) as $tag)
                                <a href="{{ route('resources.tag', $tag->slug) }}"
                                   class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 hover:bg-primary-100 hover:text-primary-800 transition-colors">
                                    #{{ $tag->name }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    <!-- Clear Filters -->
                    @if(request()->hasAny(['category', 'type', 'difficulty', 'search', 'tag']))
                    <div class="pt-4 border-t border-gray-200">
                        <a href="{{ route('resources.index') }}"
                           class="w-full inline-flex items-center justify-center px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                            <i class="fas fa-times mr-2"></i>
                            Clear All Filters
                        </a>
                    </div>
                    @endif
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1">
            <!-- Active Filters -->
            @if(request()->hasAny(['category', 'type', 'difficulty', 'search', 'tag']))
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 mb-6">
                <div class="flex items-center justify-between">
                    <h3 class="font-medium text-gray-900">Active Filters:</h3>
                    <a href="{{ route('resources.index') }}" class="text-sm text-primary-600 hover:text-primary-700">
                        Clear All
                    </a>
                </div>
                <div class="flex flex-wrap gap-2 mt-2">
                    @if(request('search'))
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-primary-100 text-primary-800">
                            Search: "{{ request('search') }}"
                            <a href="{{ route('resources.index', request()->except('search')) }}" class="ml-2 text-primary-600 hover:text-primary-800">
                                <i class="fas fa-times"></i>
                            </a>
                        </span>
                    @endif
                    @if(request('category'))
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-blue-100 text-blue-800">
                            Category: {{ $categories->where('slug', request('category'))->first()?->name ?? request('category') }}
                            <a href="{{ route('resources.index', request()->except('category')) }}" class="ml-2 text-blue-600 hover:text-blue-800">
                                <i class="fas fa-times"></i>
                            </a>
                        </span>
                    @endif
                    @if(request('type'))
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-green-100 text-green-800">
                            Type: {{ $resourceTypes->where('slug', request('type'))->first()?->name ?? request('type') }}
                            <a href="{{ route('resources.index', request()->except('type')) }}" class="ml-2 text-green-600 hover:text-green-800">
                                <i class="fas fa-times"></i>
                            </a>
                        </span>
                    @endif
                    @if(request('difficulty'))
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-yellow-100 text-yellow-800">
                            Difficulty: {{ ucfirst(request('difficulty')) }}
                            <a href="{{ route('resources.index', request()->except('difficulty')) }}" class="ml-2 text-yellow-600 hover:text-yellow-800">
                                <i class="fas fa-times"></i>
                            </a>
                        </span>
                    @endif
                    @if(request('tag'))
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-purple-100 text-purple-800">
                            Tag: #{{ request('tag') }}
                            <a href="{{ route('resources.index', request()->except('tag')) }}" class="ml-2 text-purple-600 hover:text-purple-800">
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
                        <h3 class="text-lg font-medium text-gray-900 mb-2">No resources found</h3>
                        <p class="text-gray-600 mb-6">
                            We couldn't find any resources matching your criteria. Try adjusting your filters or search terms.
                        </p>
                        <div class="flex flex-col sm:flex-row gap-3 justify-center">
                            <a href="{{ route('resources.index') }}"
                               class="inline-flex items-center px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors">
                                <i class="fas fa-list mr-2"></i>
                                View All Resources
                            </a>
                            <a href="{{ route('categories.index') }}"
                               class="inline-flex items-center px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                                <i class="fas fa-folder mr-2"></i>
                                Browse Categories
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
        Alpine.data('resourcesPage', () => ({
            viewMode: localStorage.getItem('resourcesViewMode') || 'grid',

            init() {
                this.$watch('viewMode', value => {
                    localStorage.setItem('resourcesViewMode', value);
                });
            }
        }))
    })

    function updateFilters() {
        const url = new URL(window.location);
        const sort = document.querySelector('select[name="sort"]').value;

        if (sort) {
            url.searchParams.set('sort', sort);
        } else {
            url.searchParams.delete('sort');
        }

        url.searchParams.delete('page'); // Reset to first page
        window.location.href = url.toString();
    }

    function updateCategoryFilter(checkbox) {
        const url = new URL(window.location);

        if (checkbox.checked) {
            url.searchParams.set('category', checkbox.value);
        } else {
            url.searchParams.delete('category');
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
