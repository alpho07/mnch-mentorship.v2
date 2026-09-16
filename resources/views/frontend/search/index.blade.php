@extends('layouts.app')

@section('title', $searchTerm ? "Search results for \"{$searchTerm}\"" : 'Search Resources')
@section('meta_description', 'Search through our comprehensive resource library to find exactly what you need.')

@section('breadcrumbs')
    <li>
        <div class="flex items-center">
            <i class="fas fa-chevron-right text-gray-400 mx-1"></i>
            <span class="text-gray-500">Search</span>
        </div>
    </li>
    @if($searchTerm)
        <li>
            <div class="flex items-center">
                <i class="fas fa-chevron-right text-gray-400 mx-1"></i>
                <span class="text-gray-500">"{{ $searchTerm }}"</span>
            </div>
        </li>
    @endif
@endsection

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8" x-data="searchPage()">
    <!-- Search Header -->
    <div class="mb-8">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h1 class="text-2xl font-bold text-gray-900 mb-4">
                @if($searchTerm)
                    Search Results for "{{ $searchTerm }}"
                @else
                    Search Resources
                @endif
            </h1>
            
            <!-- Enhanced Search Form -->
            <form action="{{ route('resources.search') }}" method="GET" class="space-y-4">
                <div class="relative">
                    <input type="text" 
                           name="q" 
                           value="{{ $searchTerm }}"
                           placeholder="Search for resources, topics, or keywords..."
                           class="w-full pl-12 pr-4 py-3 text-lg border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent shadow-sm"
                           autocomplete="off">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                        <i class="fas fa-search text-gray-400 text-lg"></i>
                    </div>
                    <button type="submit" 
                            class="absolute inset-y-0 right-0 mr-2 px-4 py-2 bg-primary-600 text-white rounded-md hover:bg-primary-700 transition-colors">
                        Search
                    </button>
                </div>

                <!-- Quick Filters -->
                <div class="flex flex-wrap gap-3 items-center">
                    <label class="text-sm font-medium text-gray-700">Filters:</label>
                    
                    <select name="category" class="text-sm border border-gray-300 rounded-md px-3 py-1 focus:ring-1 focus:ring-primary-500">
                        <option value="">All Categories</option>
                        @foreach($suggestedCategories as $category)
                            <option value="{{ $category->slug }}" {{ request('category') === $category->slug ? 'selected' : '' }}>
                                {{ $category->name }} ({{ $category->resources_count }})
                            </option>
                        @endforeach
                    </select>

                    <select name="type" class="text-sm border border-gray-300 rounded-md px-3 py-1 focus:ring-1 focus:ring-primary-500">
                        <option value="">All Types</option>
                        @foreach($resourceTypes as $type)
                            <option value="{{ $type->slug }}" {{ request('type') === $type->slug ? 'selected' : '' }}>
                                {{ $type->name }} ({{ $type->resources_count }})
                            </option>
                        @endforeach
                    </select>

                    <select name="difficulty" class="text-sm border border-gray-300 rounded-md px-3 py-1 focus:ring-1 focus:ring-primary-500">
                        <option value="">Any Level</option>
                        <option value="beginner" {{ request('difficulty') === 'beginner' ? 'selected' : '' }}>Beginner</option>
                        <option value="intermediate" {{ request('difficulty') === 'intermediate' ? 'selected' : '' }}>Intermediate</option>
                        <option value="advanced" {{ request('difficulty') === 'advanced' ? 'selected' : '' }}>Advanced</option>
                    </select>

                    <select name="sort" class="text-sm border border-gray-300 rounded-md px-3 py-1 focus:ring-1 focus:ring-primary-500">
                        <option value="relevance" {{ request('sort') === 'relevance' ? 'selected' : '' }}>Most Relevant</option>
                        <option value="latest" {{ request('sort') === 'latest' ? 'selected' : '' }}>Latest</option>
                        <option value="popular" {{ request('sort') === 'popular' ? 'selected' : '' }}>Most Popular</option>
                        <option value="title" {{ request('sort') === 'title' ? 'selected' : '' }}>A-Z</option>
                    </select>

                    @if(request()->anyFilled(['category', 'type', 'difficulty', 'sort']))
                        <a href="{{ route('resources.search', ['q' => $searchTerm]) }}" 
                           class="text-sm text-primary-600 hover:text-primary-700 underline">
                            Clear filters
                        </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    @if($searchTerm && strlen($searchTerm) >= 2)
        <!-- Results Summary -->
        <div class="mb-6 flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <p class="text-gray-600">
                    @if($resources->total() > 0)
                        Found <span class="font-semibold text-gray-900">{{ number_format($resources->total()) }}</span> 
                        {{ Str::plural('result', $resources->total()) }} for 
                        <span class="font-semibold text-gray-900">"{{ $searchTerm }}"</span>
                    @else
                        No results found for "<span class="font-semibold text-gray-900">{{ $searchTerm }}</span>"
                    @endif
                </p>
            </div>
            
            @if($resources->total() > 0)
                <div class="flex items-center space-x-2 text-sm text-gray-500">
                    <i class="fas fa-clock"></i>
                    <span>Search completed in {{ number_format(microtime(true) - LARAVEL_START, 3) }}s</span>
                </div>
            @endif
        </div>

        @if($resources->total() > 0)
            <!-- Search Results -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                @foreach($resources as $resource)
                    <article class="bg-white rounded-lg shadow-sm border border-gray-200 hover:shadow-md transition-shadow duration-200">
                        <div class="p-6">
                            <!-- Resource Type Badge -->
                            <div class="flex items-center justify-between mb-3">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-{{ $resource->resourceType->color ?? 'blue' }}-100 text-{{ $resource->resourceType->color ?? 'blue' }}-800">
                                    <i class="{{ $resource->resourceType->icon ?? 'fas fa-file' }} mr-1"></i>
                                    {{ $resource->resourceType->name }}
                                </span>
                                
                                @if($resource->difficulty_level)
                                    <span class="text-xs px-2 py-1 rounded-md font-medium
                                        {{ $resource->difficulty_level === 'beginner' ? 'bg-green-100 text-green-700' : '' }}
                                        {{ $resource->difficulty_level === 'intermediate' ? 'bg-yellow-100 text-yellow-700' : '' }}
                                        {{ $resource->difficulty_level === 'advanced' ? 'bg-red-100 text-red-700' : '' }}">
                                        {{ ucfirst($resource->difficulty_level) }}
                                    </span>
                                @endif
                            </div>

                            <!-- Title & Description -->
                            <h3 class="font-semibold text-lg text-gray-900 mb-2 line-clamp-2">
                                <a href="{{ route('resources.show', $resource->slug) }}" 
                                   class="hover:text-primary-600 transition-colors">
                                    {{ $resource->title }}
                                </a>
                            </h3>
                            
                            @if($resource->summary)
                                <p class="text-gray-600 text-sm mb-4 line-clamp-3">{{ $resource->summary }}</p>
                            @endif

                            <!-- Tags -->
                            @if($resource->tags->count() > 0)
                                <div class="flex flex-wrap gap-1 mb-4">
                                    @foreach($resource->tags->take(3) as $tag)
                                        <span class="inline-block px-2 py-1 text-xs bg-gray-100 text-gray-600 rounded-md">
                                            {{ $tag->name }}
                                        </span>
                                    @endforeach
                                    @if($resource->tags->count() > 3)
                                        <span class="text-xs text-gray-500">+{{ $resource->tags->count() - 3 }} more</span>
                                    @endif
                                </div>
                            @endif

                            <!-- Metadata -->
                            <div class="flex items-center justify-between text-xs text-gray-500">
                                <div class="flex items-center space-x-3">
                                    <span class="flex items-center">
                                        <i class="fas fa-eye mr-1"></i>
                                        {{ number_format($resource->view_count) }}
                                    </span>
                                    <span class="flex items-center">
                                        <i class="fas fa-heart mr-1"></i>
                                        {{ number_format($resource->like_count) }}
                                    </span>
                                    @if($resource->author)
                                        <span class="flex items-center">
                                            <i class="fas fa-user mr-1"></i>
                                            {{ $resource->author->name }}
                                        </span>
                                    @endif
                                </div>
                                <time datetime="{{ $resource->published_at }}">
                                    {{ $resource->published_at->diffForHumans() }}
                                </time>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            <!-- Pagination -->
            <div class="flex justify-center">
                {{ $resources->links() }}
            </div>

        @else
            <!-- No Results State -->
            <div class="text-center py-12">
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-8 max-w-md mx-auto">
                    <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-search text-2xl text-gray-400"></i>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No results found</h3>
                    <p class="text-gray-600 mb-6">
                        We couldn't find any resources matching "{{ $searchTerm }}". 
                        Try adjusting your search terms or browse our suggestions below.
                    </p>
                    
                    <div class="space-y-4">
                        <a href="{{ route('resources.index') }}" 
                           class="inline-flex items-center px-4 py-2 bg-primary-600 text-white rounded-md hover:bg-primary-700 transition-colors">
                            <i class="fas fa-book mr-2"></i>
                            Browse All Resources
                        </a>
                        
                        <div class="text-sm">
                            <p class="text-gray-500 mb-2">Search suggestions:</p>
                            <ul class="space-y-1">
                                <li>• Try different keywords or synonyms</li>
                                <li>• Check for spelling mistakes</li>
                                <li>• Use broader search terms</li>
                                <li>• Browse by category instead</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- Suggestions Section -->
        @if($suggestedCategories->count() > 0 || $suggestedTags->count() > 0)
            <div class="mt-12 bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-6">You might also like</h2>
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- Suggested Categories -->
                    @if($suggestedCategories->count() > 0)
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 mb-4 flex items-center">
                                <i class="fas fa-folder text-primary-600 mr-2"></i>
                                Related Categories
                            </h3>
                            <div class="space-y-3">
                                @foreach($suggestedCategories as $category)
                                    <a href="{{ route('resources.category', $category->slug) }}" 
                                       class="block p-3 rounded-lg border border-gray-200 hover:border-primary-300 hover:bg-primary-50 transition-colors group">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <h4 class="font-medium text-gray-900 group-hover:text-primary-700">
                                                    {{ $category->name }}
                                                </h4>
                                                @if($category->description)
                                                    <p class="text-sm text-gray-600 mt-1">{{ Str::limit($category->description, 80) }}</p>
                                                @endif
                                            </div>
                                            <div class="text-right">
                                                <span class="text-sm font-medium text-gray-900">{{ $category->resources_count }}</span>
                                                <p class="text-xs text-gray-500">resources</p>
                                            </div>
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <!-- Suggested Tags -->
                    @if($suggestedTags->count() > 0)
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 mb-4 flex items-center">
                                <i class="fas fa-tags text-primary-600 mr-2"></i>
                                Related Tags
                            </h3>
                            <div class="flex flex-wrap gap-2">
                                @foreach($suggestedTags as $tag)
                                    <a href="{{ route('resources.search', ['q' => $searchTerm, 'tag' => $tag->name]) }}" 
                                       class="inline-flex items-center px-3 py-2 text-sm bg-gray-100 text-gray-700 rounded-full hover:bg-primary-100 hover:text-primary-700 transition-colors">
                                        {{ $tag->name }}
                                        <span class="ml-2 text-xs bg-white rounded-full px-2 py-0.5">{{ $tag->resources_count }}</span>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        @endif
    @else
        <!-- Initial Search State -->
        <div class="text-center py-16">
            <div class="max-w-md mx-auto">
                <div class="w-20 h-20 bg-primary-100 rounded-full flex items-center justify-center mx-auto mb-6">
                    <i class="fas fa-search text-3xl text-primary-600"></i>
                </div>
                <h1 class="text-2xl font-bold text-gray-900 mb-4">Search Resources</h1>
                <p class="text-gray-600 mb-8">
                    Search through our comprehensive library of resources, guides, and learning materials.
                    Enter at least 2 characters to begin your search.
                </p>
                
                <!-- Popular Searches -->
                <div class="bg-white rounded-lg border border-gray-200 p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Popular Searches</h3>
                    <div class="flex flex-wrap gap-2 justify-center">
                        @php
                            $popularSearches = ['tutorials', 'guides', 'documentation', 'templates', 'tools', 'frameworks'];
                        @endphp
                        @foreach($popularSearches as $search)
                            <a href="{{ route('resources.search', ['q' => $search]) }}" 
                               class="px-4 py-2 text-sm bg-gray-100 text-gray-700 rounded-full hover:bg-primary-100 hover:text-primary-700 transition-colors">
                                {{ $search }}
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

@push('styles')
<style>
    .line-clamp-2 {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    
    .line-clamp-3 {
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    /* Custom pagination styles */
    .pagination {
        @apply flex items-center space-x-1;
    }
    
    .pagination .page-link {
        @apply px-3 py-2 text-sm text-gray-500 bg-white border border-gray-300 hover:bg-gray-50 hover:text-gray-700 transition-colors;
    }
    
    .pagination .page-item.active .page-link {
        @apply bg-primary-600 text-white border-primary-600;
    }
    
    .pagination .page-item.disabled .page-link {
        @apply text-gray-300 cursor-not-allowed;
    }
</style>
@endpush

@push('scripts')
<script>
function searchPage() {
    return {
        init() {
            // Auto-submit form when filters change
            this.$el.querySelectorAll('select[name]').forEach(select => {
                select.addEventListener('change', () => {
                    this.$el.querySelector('form').submit();
                });
            });
            
            // Highlight search terms in results
            this.highlightSearchTerms();
        },
        
        highlightSearchTerms() {
            const searchTerm = @json($searchTerm);
            if (!searchTerm || searchTerm.length < 2) return;
            
            const terms = searchTerm.toLowerCase().split(' ').filter(term => term.length > 2);
            const articles = this.$el.querySelectorAll('article');
            
            articles.forEach(article => {
                const title = article.querySelector('h3 a');
                const summary = article.querySelector('p');
                
                [title, summary].forEach(element => {
                    if (!element) return;
                    
                    let html = element.innerHTML;
                    terms.forEach(term => {
                        const regex = new RegExp(`(${term})`, 'gi');
                        html = html.replace(regex, '<mark class="bg-yellow-200 px-1 rounded">$1</mark>');
                    });
                    element.innerHTML = html;
                });
            });
        }
    }
}
</script>
@endpush
@endsection