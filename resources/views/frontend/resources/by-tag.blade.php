@extends('layouts.app')

@section('title', "Resources tagged with \"{$tag->name}\"")
@section('meta_description', "Browse resources tagged with {$tag->name}. Find related learning materials and tools.")

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
            <span class="text-gray-500">Tags</span>
        </div>
    </li>
    <li>
        <div class="flex items-center">
            <i class="fas fa-chevron-right text-gray-400 mx-1"></i>
            <span class="text-gray-500">{{ $tag->name }}</span>
        </div>
    </li>
@endsection

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8" x-data="tagPage()">
    <!-- Tag Header -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-8 mb-8">
        <div class="flex items-start justify-between">
            <div class="flex-1">
                <div class="flex items-center mb-4">
                    <div class="w-12 h-12 bg-primary-100 rounded-lg flex items-center justify-center mr-4">
                        <i class="fas fa-tag text-xl text-primary-600"></i>
                    </div>
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">{{ $tag->name }}</h1>
                        <p class="text-gray-600 mt-1">
                            {{ number_format($resources->total()) }} {{ Str::plural('resource', $resources->total()) }} found
                        </p>
                    </div>
                </div>
                
                @if($tag->description)
                    <div class="prose max-w-none">
                        <p class="text-gray-700 text-lg leading-relaxed">{{ $tag->description }}</p>
                    </div>
                @endif
            </div>
            
            <!-- Tag Actions -->
            <div class="flex items-center space-x-3 ml-6">
                <!-- Sort Options -->
                <div class="relative" x-data="{ open: false }">
                    <button @click="open = !open" 
                            class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-500">
                        <i class="fas fa-sort mr-2"></i>
                        <span x-text="getCurrentSort()"></span>
                        <i class="fas fa-chevron-down ml-2 text-xs"></i>
                    </button>
                    
                    <div x-show="open" @click.away="open = false" x-transition
                         class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg border border-gray-200 z-10">
                        <div class="py-1">
                            <a href="{{ route('resources.tag', ['tag' => $tag->slug, 'sort' => 'latest']) }}" 
                               class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 {{ request('sort', 'latest') === 'latest' ? 'bg-primary-50 text-primary-700' : '' }}">
                                <i class="fas fa-clock mr-2"></i> Latest
                            </a>
                            <a href="{{ route('resources.tag', ['tag' => $tag->slug, 'sort' => 'popular']) }}" 
                               class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 {{ request('sort') === 'popular' ? 'bg-primary-50 text-primary-700' : '' }}">
                                <i class="fas fa-fire mr-2"></i> Most Popular
                            </a>
                            <a href="{{ route('resources.tag', ['tag' => $tag->slug, 'sort' => 'title']) }}" 
                               class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 {{ request('sort') === 'title' ? 'bg-primary-50 text-primary-700' : '' }}">
                                <i class="fas fa-sort-alpha-down mr-2"></i> A-Z
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Share Tag -->
                <button @click="shareTag()"  
                        class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                    <i class="fas fa-share mr-2"></i>
                    Share
                </button>
            </div>
        </div>
    </div>

    @if($resources->count() > 0) 
        <!-- Resources Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
            @foreach($resources as $resource) 
                <article class="bg-white rounded-lg shadow-sm border border-gray-200 hover:shadow-md transition-all duration-200 group">
                    <div class="p-6">
                        <!-- Resource Header -->
                        <div class="flex items-start justify-between mb-4">
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 bg-{{ $resource->resourceType->color ?? 'blue' }}-100 rounded-lg flex items-center justify-center">
                                    <i class="{{ $resource->resourceType->icon ?? 'fas fa-file' }} text-{{ $resource->resourceType->color ?? 'blue' }}-600"></i>
                                </div>
                                <div>
                                    <span class="text-xs font-medium text-{{ $resource->resourceType->color ?? 'blue' }}-600 uppercase tracking-wide">
                                        {{ $resource->resourceType->name }}
                                    </span>
                                    @if($resource->difficulty_level)
                                        <div class="text-xs mt-1">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full font-medium
                                                {{ $resource->difficulty_level === 'beginner' ? 'bg-green-100 text-green-700' : '' }}
                                                {{ $resource->difficulty_level === 'intermediate' ? 'bg-yellow-100 text-yellow-700' : '' }}
                                                {{ $resource->difficulty_level === 'advanced' ? 'bg-red-100 text-red-700' : '' }}">
                                                {{ ucfirst($resource->difficulty_level) }}
                                            </span>
                                        </div>
                                    @endif
                                </div>
                            </div>
                            
                            <!-- Bookmark Button -->
                            <button class="opacity-0 group-hover:opacity-100 transition-opacity p-2 text-gray-400 hover:text-primary-600">
                                <i class="fas fa-bookmark"></i>
                            </button>
                        </div>

                        <!-- Title & Summary -->
                        <h3 class="font-semibold text-lg text-gray-900 mb-3 line-clamp-2 leading-tight">
                            <a href="{{ route('resources.show', $resource->slug) }}" 
                               class="hover:text-primary-600 transition-colors">
                                {{ $resource->title }}
                            </a>
                        </h3>
                        
                        @if($resource->summary)
                            <p class="text-gray-600 text-sm mb-4 line-clamp-3 leading-relaxed">
                                {{ $resource->summary }}
                            </p>
                        @endif

                        <!-- Category & Author -->
                        <div class="flex items-center text-sm text-gray-500 mb-4">
                            @if($resource->category)
                                <a href="{{ route('resources.category', $resource->category->slug) }}" 
                                   class="hover:text-primary-600 transition-colors">
                                    <i class="fas fa-folder mr-1"></i>
                                    {{ $resource->category->name }}
                                </a>
                            @endif
                            
                            @if($resource->category && $resource->author)
                                <span class="mx-2">•</span>
                            @endif
                            
                            @if($resource->author)
                                <span class="flex items-center">
                                    <i class="fas fa-user mr-1"></i>
                                    {{ $resource->author->name }}
                                </span>
                            @endif
                        </div>

                        <!-- Tags (excluding current tag) -->
                        @php
                            $otherTags = $resource->tags->where('id', '!=', $tag->id);
                        @endphp
                        @if($otherTags->count() > 0)
                            <div class="flex flex-wrap gap-1 mb-4">
                                @foreach($otherTags->take(4) as $otherTag)
                                    <a href="{{ route('resources.tag', $otherTag->slug) }}" 
                                       class="inline-block px-2 py-1 text-xs bg-gray-100 text-gray-600 rounded-md hover:bg-primary-100 hover:text-primary-700 transition-colors">
                                        {{ $otherTag->name }}
                                    </a>
                                @endforeach
                                @if($otherTags->count() > 4)
                                    <span class="text-xs text-gray-500 py-1">+{{ $otherTags->count() - 4 }} more</span>
                                @endif
                            </div>
                        @endif

                        <!-- Footer Stats -->
                        <div class="flex items-center justify-between pt-4 border-t border-gray-100">
                            <div class="flex items-center space-x-4 text-xs text-gray-500">
                                <span class="flex items-center">
                                    <i class="fas fa-eye mr-1"></i>
                                    {{ number_format($resource->view_count) }}
                                </span>
                                <span class="flex items-center">
                                    <i class="fas fa-heart mr-1"></i>
                                    {{ number_format($resource->like_count) }}
                                </span>
                                @if($resource->download_count > 0)
                                    <span class="flex items-center">
                                        <i class="fas fa-download mr-1"></i>
                                        {{ number_format($resource->download_count) }}
                                    </span>
                                @endif
                            </div>
                            <time datetime="{{ $resource->published_at }}" class="text-xs text-gray-500">
                                {{ $resource->published_at->diffForHumans() }}
                            </time>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>

        <!-- Pagination -->
        <div class="flex justify-center">
            {{ $resources->withQueryString()->links() }}
        </div>

    @else
        <!-- No Resources State -->
        <div class="text-center py-16">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-8 max-w-md mx-auto">
                <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-tag text-2xl text-gray-400"></i>
                </div>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No resources found</h3>
                <p class="text-gray-600 mb-6">
                    There are currently no published resources with the "{{ $tag->name }}" tag.
                </p>
                
                <div class="space-y-3">
                    <a href="{{ route('resources.index') }}" 
                       class="inline-flex items-center px-4 py-2 bg-primary-600 text-white rounded-md hover:bg-primary-700 transition-colors">
                        <i class="fas fa-book mr-2"></i>
                        Browse All Resources
                    </a>
                    <div>
                        <a href="{{ route('resources.search', ['q' => $tag->name]) }}" 
                           class="text-primary-600 hover:text-primary-700 text-sm">
                            Search for "{{ $tag->name }}" instead
                        </a>
                    </div>
                </div>
            </div>
        </div>
    @endif

  

    <!-- Tag Statistics -->
    @if($resources->total() > 0)
        <div class="mt-8 bg-gradient-to-r from-primary-50 to-blue-50 rounded-lg p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Tag Statistics</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="text-center">
                    <div class="text-2xl font-bold text-primary-600">{{ number_format($resources->total()) }}</div>
                    <div class="text-sm text-gray-600">Total Resources</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-primary-600">
                        {{ $resources->groupBy('resourceType.name')->count() }}
                    </div>
                    <div class="text-sm text-gray-600">Resource Types</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-primary-600">
                        {{ $resources->groupBy('category.name')->count() }}
                    </div>
                    <div class="text-sm text-gray-600">Categories</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-primary-600">
                        {{ $resources->groupBy('author.name')->count() }}
                    </div>
                    <div class="text-sm text-gray-600">Authors</div>
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

    /* Hover animations */
    .group:hover .group-hover\:scale-105 {
        transform: scale(1.05);
    }
    
    /* Custom scrollbar for tag container */
    .tag-container::-webkit-scrollbar {
        height: 4px;
    }
    
    .tag-container::-webkit-scrollbar-track {
        background: #f1f5f9;
        border-radius: 2px;
    }
    
    .tag-container::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 2px;
    }
    
    .tag-container::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }
</style>
@endpush

@push('scripts')
<script>
function tagPage() {
    return {
        getCurrentSort() {
            const sort = new URLSearchParams(window.location.search).get('sort') || 'latest';
            const sortOptions = {
                'latest': 'Latest',
                'popular': 'Most Popular', 
                'title': 'A-Z'
            };
            return sortOptions[sort] || 'Latest';
        },
        
        async shareTag() {
            const url = window.location.href;
            const title = document.title;
            
            if (navigator.share) {
                try {
                    await navigator.share({
                        title: title,
                        url: url
                    });
                } catch (err) {
                    this.fallbackShare(url);
                }
            } else {
                this.fallbackShare(url);
            }
        },
        
        fallbackShare(url) {
            navigator.clipboard.writeText(url).then(() => {
                this.showToast('Link copied to clipboard!');
            }).catch(() => {
                // Fallback for older browsers
                const textArea = document.createElement('textarea');
                textArea.value = url;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                this.showToast('Link copied to clipboard!');
            });
        },
        
        showToast(message) {
            // Simple toast notification
            const toast = document.createElement('div');
            toast.className = 'fixed bottom-4 right-4 bg-gray-900 text-white px-4 py-2 rounded-md shadow-lg z-50 transition-opacity';
            toast.textContent = message;
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.style.opacity = '0';
                setTimeout(() => document.body.removeChild(toast), 300);
            }, 2000);
        }
    }
}
</script>
@endpush
@endsection