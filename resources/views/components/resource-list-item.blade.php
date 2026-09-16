@props(['resource'])

<div
    class="bg-white rounded-lg shadow-sm border border-gray-200 hover:border-primary-300 hover:shadow-md transition-all duration-300 p-6">
    <div class="flex flex-col md:flex-row gap-6">
        <!-- Resource Thumbnail -->
        <div
            class="md:w-40 md:h-32 w-full h-40 bg-gradient-to-br from-primary-50 to-primary-100 rounded-lg overflow-hidden flex-shrink-0">
            @if ($resource->featured_image)
                <img src="{{ Storage::disk('thumbnails')->url($resource->featured_image) }}"
                     alt="{{ $resource->title }}"
                     class="w-full h-full object-cover">
            @else
                <x-resource-placeholder :resource="$resource" icon-size="text-2xl" :show-label="false" />
            @endif
        </div>

        <!-- Content -->
        <div class="flex-1">
            <div class="flex flex-col h-full">
                <!-- Header -->
                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between mb-3">
                    <div class="flex-1">
                        <!-- Category & Type -->
                        <div class="flex items-center gap-2 mb-2">
                            @if ($resource->category)
                                <a href="{{ route('resources.category', $resource->category->slug) }}"
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 hover:bg-gray-200 transition-colors">
                                    {{ $resource->category->name }}
                                </a>
                            @endif

                            @if ($resource->resourceType)
                                <span
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-primary-100 text-primary-800">
                                    {{ $resource->resourceType->name }}
                                </span>
                            @endif

                            @if ($resource->difficulty_level)
                                <span
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                    @if ($resource->difficulty_level === 'beginner') bg-green-100 text-green-800
                                    @elseif($resource->difficulty_level === 'intermediate') bg-yellow-100 text-yellow-800
                                    @else bg-red-100 text-red-800 @endif">
                                    {{ ucfirst($resource->difficulty_level) }}
                                </span>
                            @endif
                        </div>

                        <!-- Title -->
                        <h3 class="text-xl font-semibold text-gray-900 mb-2 hover:text-primary-600 transition-colors">
                            <a href="{{ route('resources.show', $resource->slug) }}">
                                {{ $resource->title }}
                            </a>
                        </h3>
                    </div>

                    <!-- Quick Actions -->
                    <div class="flex items-center space-x-2 mt-2 sm:mt-0">
                        @auth
                            <button onclick="toggleBookmark({{ $resource->id }})"
                                class="p-2 text-gray-400 hover:text-yellow-500 transition-colors" title="Bookmark">
                                <i class="fas fa-bookmark"></i>
                            </button>
                        @endauth

                        @if ($resource->is_downloadable && ($resource->file_path || $resource->hasPrimaryFile()))
                            <a href="{{ route('resources.download', $resource->slug) }}"
                                class="p-2 text-gray-400 hover:text-primary-600 transition-colors" title="Download">
                                <i class="fas fa-download"></i>
                            </a>
                        @endif
                    </div>
                </div>

                <!-- Description -->
                @if ($resource->excerpt)
                    <p class="text-gray-600 mb-4 line-clamp-2">
                        {{ $resource->excerpt }}
                    </p>
                @endif

                <!-- Tags -->
                @if ($resource->tags && $resource->tags->count() > 0)
                    <div class="flex flex-wrap gap-1 mb-4">
                        @foreach ($resource->tags->take(5) as $tag)
                            <a href="{{ route('resources.tag', $tag->slug) }}"
                                class="inline-flex items-center px-2 py-1 rounded text-xs bg-gray-100 text-gray-700 hover:bg-primary-100 hover:text-primary-700 transition-colors">
                                #{{ $tag->name }}
                            </a>
                        @endforeach
                        @if ($resource->tags->count() > 5)
                            <span class="inline-flex items-center px-2 py-1 rounded text-xs bg-gray-100 text-gray-700">
                                +{{ $resource->tags->count() - 5 }} more
                            </span>
                        @endif
                    </div>
                @endif

                <!-- Footer -->
                <div
                    class="flex flex-col sm:flex-row sm:items-center sm:justify-between mt-auto pt-4 border-t border-gray-100">
                    <!-- Meta Information -->
                    <div class="flex items-center text-sm text-gray-500 mb-3 sm:mb-0">
                        @if ($resource->author)
                            <img src="https://ui-avatars.com/api/?name={{ urlencode($resource->author->full_name) }}&size=24&background=3b82f6&color=ffffff"
                                alt="{{ $resource->author->full_name }}" class="w-6 h-6 rounded-full mr-2">
                            <span class="mr-4">{{ $resource->author->full_name }}</span>
                        @else
                            <span class="mr-4">Unknown Author</span>
                        @endif
                        @if ($resource->published_at)
                            <time datetime="{{ $resource->published_at->toISOString() }}" class="mr-4">
                                {{ $resource->published_at->diffForHumans() }}
                            </time>
                        @endif
                        @if ($resource->read_time && $resource->read_time > 0)
                            <span class="flex items-center">
                                <i class="fas fa-clock mr-1"></i>
                                {{ $resource->read_time }} min read
                            </span>
                        @endif
                    </div>

                    <!-- Stats and Actions -->
                    <div class="flex items-center justify-between sm:justify-end">
                        <!-- Stats -->
                        <div class="flex items-center space-x-4 text-sm text-gray-500 mr-4">
                            <span class="flex items-center">
                                <i class="fas fa-eye mr-1"></i>
                                {{ number_format($resource->view_count) }}
                            </span>

                            @if ($resource->is_downloadable)
                                <span class="flex items-center">
                                    <i class="fas fa-download mr-1"></i>
                                    {{ number_format($resource->download_count) }}
                                </span>
                            @endif

                            <span class="flex items-center">
                                <i class="fas fa-heart mr-1"></i>
                                {{ number_format($resource->like_count) }}
                            </span>

                            @if (isset($resource->comments_count) && $resource->comments_count > 0)
                                <span class="flex items-center">
                                    <i class="fas fa-comment mr-1"></i>
                                    {{ number_format($resource->comments_count) }}
                                </span>
                            @endif
                        </div>

                        <!-- Action Button -->
                        <a href="{{ route('resources.show', $resource->slug) }}"
                            class="inline-flex items-center px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors font-medium">
                            <i class="fas fa-eye mr-2"></i>
                            View Resource
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@once
    @push('scripts')
        <script>
            function toggleBookmark(resourceId) {
                @auth
                fetch(`/api/v1/resources/${resourceId}/interactions/bookmark`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.bookmarked) {
                            showToast('Resource bookmarked!', 'success');
                        } else {
                            showToast('Bookmark removed!', 'info');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showToast('An error occurred', 'error');
                    });
            @else
                window.location.href = '{{ url('admin/login') }}';
            @endauth
            }

            function showToast(message, type = 'info') {
                const toast = document.createElement('div');
                toast.className = `fixed top-4 right-4 px-4 py-2 rounded-lg text-white z-50 ${
            type === 'success' ? 'bg-green-500' : 
            type === 'error' ? 'bg-red-500' : 'bg-blue-500'
        }`;
                toast.textContent = message;
                document.body.appendChild(toast);

                setTimeout(() => {
                    toast.remove();
                }, 3000);
            }
        </script>
    @endpush

    @push('styles')
        <style>
            .line-clamp-2 {
                display: -webkit-box;
                -webkit-line-clamp: 2;
                -webkit-box-orient: vertical;
                overflow: hidden;
            }
        </style>
    @endpush
@endonce
