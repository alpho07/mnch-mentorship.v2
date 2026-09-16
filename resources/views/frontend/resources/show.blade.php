@extends('layouts.app')

@section('title', $resource->title)
@section('meta_description', $resource->meta_description ?: Str::limit(strip_tags($resource->excerpt), 160))

@section('breadcrumbs')
    <li>
        <div class="flex items-center">
            <i class="fas fa-chevron-right text-gray-400 mx-2"></i>
            <a href="{{ route('resources.index') }}" class="text-gray-500 hover:text-gray-700">Resources</a>
        </div>
    </li>
    @if($resource->category)
    <li>
        <div class="flex items-center">
            <i class="fas fa-chevron-right text-gray-400 mx-2"></i>
            <a href="{{ route('resources.category', $resource->category->slug) }}" class="text-gray-500 hover:text-gray-700">
                {{ $resource->category->name }}
            </a>
        </div>
    </li>
    @endif
    <li>
        <div class="flex items-center">
            <i class="fas fa-chevron-right text-gray-400 mx-2"></i>
            <span class="text-gray-500">{{ Str::limit($resource->title, 50) }}</span>
        </div>
    </li>
@endsection

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8" x-data="resourcePage">
    <div class="flex flex-col lg:flex-row gap-8">
        <!-- Main Content -->
        <main class="lg:w-2/3">
            <!-- Resource Header -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden mb-8">
                <!-- Featured Image / Placeholder -->
                <div class="h-48 md:h-64 overflow-hidden">
                    @if($resource->featured_image)
                        <img src="{{ Storage::disk('thumbnails')->url($resource->featured_image) }}"
                             alt="{{ $resource->title }}"
                             class="w-full h-full object-cover">
                    @else
                        <x-resource-placeholder :resource="$resource" icon-size="text-6xl" />
                    @endif
                </div>

                <div class="p-6 md:p-8">
                    <!-- Meta Info -->
                    <div class="flex flex-wrap items-center gap-3 mb-4">
                        @if($resource->category)
                            <a href="{{ route('resources.category', $resource->category->slug) }}"
                               class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-primary-100 text-primary-800 hover:bg-primary-200 transition-colors">
                                <i class="fas fa-folder mr-2"></i>
                                {{ $resource->category->name }}
                            </a>
                        @endif

                        @if($resource->resourceType)
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-800">
                                <i class="{{ $resource->resourceType->icon ?? 'fas fa-file' }} mr-2"></i>
                                {{ $resource->resourceType->name }}
                            </span>
                        @endif

                        @if($resource->difficulty_level)
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                                @if($resource->difficulty_level === 'beginner') bg-green-100 text-green-800
                                @elseif($resource->difficulty_level === 'intermediate') bg-yellow-100 text-yellow-800
                                @else bg-red-100 text-red-800 @endif">
                                {{ ucfirst($resource->difficulty_level) }}
                            </span>
                        @endif

                        @if($resource->is_featured)
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800">
                                <i class="fas fa-star mr-2"></i>
                                Featured
                            </span>
                        @endif
                    </div>

                    <!-- Title -->
                    <h1 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">
                        {{ $resource->title }}
                    </h1>

                    <!-- Excerpt -->
                    @if($resource->excerpt)
                        <p class="text-xl text-gray-600 mb-6">
                            {{ $resource->excerpt }}
                        </p>
                    @endif

                    <!-- Author & Meta -->
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between py-4 border-t border-b border-gray-200">
                        <div class="flex items-center mb-4 sm:mb-0">
                            <img src="https://ui-avatars.com/api/?name={{ urlencode($resource->author->full_name) }}&size=48&background=3b82f6&color=ffffff"
                                 alt="{{ $resource->author->full_name }}"
                                 class="w-12 h-12 rounded-full mr-4">
                            <div>
                                <p class="font-medium text-gray-900">{{ $resource->author->full_name }}</p>
                                <p class="text-sm text-gray-500">
                                    Published {{ $resource->published_at?->format('M j, Y') }}
                                    @if($resource->read_time > 0)
                                        • {{ $resource->read_time }} min read
                                    @endif
                                </p>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex items-center space-x-3">
                            @auth
                                <!-- Like Button -->
                                <button @click="toggleLike()"
                                        :class="liked ? 'bg-red-100 text-red-600' : 'bg-gray-100 text-gray-600'"
                                        class="flex items-center px-4 py-2 rounded-lg hover:bg-red-50 transition-colors">
                                    <i :class="liked ? 'fas fa-heart' : 'far fa-heart'" class="mr-2"></i>
                                    <span x-text="likeCount"></span>
                                </button>

                                <!-- Bookmark Button -->
                                <button @click="toggleBookmark()"
                                        :class="bookmarked ? 'bg-yellow-100 text-yellow-600' : 'bg-gray-100 text-gray-600'"
                                        class="flex items-center px-4 py-2 rounded-lg hover:bg-yellow-50 transition-colors">
                                    <i :class="bookmarked ? 'fas fa-bookmark' : 'far fa-bookmark'" class="mr-2"></i>
                                    Save
                                </button>
                            @else
                                <a href="{{ url('admin/login') }}"
                                   class="flex items-center px-4 py-2 bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200 transition-colors">
                                    <i class="far fa-heart mr-2"></i>
                                    {{ $resource->like_count }}
                                </a>
                            @endauth

                            @if($resource->is_downloadable && ($resource->file_path || $resource->hasPrimaryFile()))
                                <a href="{{ route('resources.download', $resource->slug) }}"
                                   class="flex items-center px-6 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors font-medium">
                                    <i class="fas fa-download mr-2"></i>
                                    Download
                                </a>
                            @endif

                            <!-- Share Button -->
                            <button @click="shareResource()"
                                    class="flex items-center px-4 py-2 bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200 transition-colors">
                                <i class="fas fa-share mr-2"></i>
                                Share
                            </button>
                        </div>
                    </div>

                    <!-- Tags -->
                    @if($resource->tags->count() > 0)
                        <div class="mt-6">
                            <h3 class="text-sm font-medium text-gray-900 mb-2">Tags:</h3>
                            <div class="flex flex-wrap gap-2">
                                @foreach($resource->tags as $tag)
                                    <a href="{{ route('resources.tag', $tag->slug) }}"
                                       class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-gray-100 text-gray-700 hover:bg-primary-100 hover:text-primary-700 transition-colors">
                                        #{{ $tag->name }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Resource Content -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 md:p-8 mb-8">
                <div class="prose prose-lg max-w-none">
                    {!! $resource->content !!}
                </div>

                <!-- Learning Outcomes -->
                @if($resource->learning_outcomes && count($resource->learning_outcomes) > 0)
                    <div class="mt-8 p-6 bg-green-50 rounded-lg border border-green-200">
                        <h3 class="text-lg font-semibold text-green-900 mb-4 flex items-center">
                            <i class="fas fa-graduation-cap mr-2"></i>
                            What You'll Learn
                        </h3>
                        <ul class="space-y-2">
                            @foreach($resource->learning_outcomes as $outcome)
                                <li class="flex items-start">
                                    <i class="fas fa-check text-green-600 mt-1 mr-3 flex-shrink-0"></i>
                                    <span class="text-green-800">{{ $outcome }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <!-- Prerequisites -->
                @if($resource->prerequisites && count($resource->prerequisites) > 0)
                    <div class="mt-6 p-6 bg-blue-50 rounded-lg border border-blue-200">
                        <h3 class="text-lg font-semibold text-blue-900 mb-4 flex items-center">
                            <i class="fas fa-list-check mr-2"></i>
                            Prerequisites
                        </h3>
                        <ul class="space-y-2">
                            @foreach($resource->prerequisites as $prerequisite)
                                <li class="flex items-start">
                                    <i class="fas fa-arrow-right text-blue-600 mt-1 mr-3 flex-shrink-0"></i>
                                    <span class="text-blue-800">{{ $prerequisite }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <!-- External URL -->
                @if($resource->external_url)
                    <div class="mt-6 p-6 bg-purple-50 rounded-lg border border-purple-200">
                        <h3 class="text-lg font-semibold text-purple-900 mb-2 flex items-center">
                            <i class="fas fa-external-link-alt mr-2"></i>
                            External Resource
                        </h3>
                        <p class="text-purple-800 mb-4">This resource is available on an external platform.</p>
                        <a href="{{ $resource->external_url }}"
                           target="_blank"
                           rel="noopener noreferrer"
                           class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors">
                            <i class="fas fa-external-link-alt mr-2"></i>
                            Visit External Resource
                        </a>
                    </div>
                @endif
            </div>

            <!-- Inline File Preview -->
            @php
                use App\Http\Controllers\Admin\ResourceFileController;
                $previewFile = $resource->primaryFile;
                $fileType    = $previewFile?->file_type ?? '';
                $isPdf    = $fileType === 'application/pdf';
                $isImage  = str_starts_with($fileType, 'image/');
                $isVideo  = str_starts_with($fileType, 'video/');
                $isAudio  = str_starts_with($fileType, 'audio/');
                $isOffice = in_array($fileType, [
                    'application/vnd.ms-powerpoint',
                    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ]);
                $hasPreview = $previewFile && $previewFile->exists();
                $previewUrl = $hasPreview ? route('resources.preview', $resource->slug) : null;
                $officeViewerUrl = ($hasPreview && $isOffice)
                    ? 'https://view.officeapps.live.com/op/embed.aspx?src=' . urlencode(ResourceFileController::signedTempUrl($previewFile))
                    : null;
            @endphp

            @if($hasPreview && ($isPdf || $isImage || $isVideo || $isAudio || $isOffice))
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden mb-8">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                        <i class="fas fa-file text-primary-600"></i>
                        File Preview
                    </h2>
                    <a href="{{ route('resources.download', $resource->slug) }}"
                       class="inline-flex items-center gap-2 text-sm font-medium text-primary-600 hover:text-primary-700">
                        <i class="fas fa-download"></i> Download
                    </a>
                </div>

                <div class="p-4">
                    @if($isPdf)
                        <iframe src="{{ $previewUrl }}"
                                class="w-full rounded border border-gray-100"
                                style="height: 70vh;"
                                title="{{ $resource->title }}">
                        </iframe>

                    @elseif($isImage)
                        <div class="flex items-center justify-center bg-gray-50 rounded p-4">
                            <img src="{{ $previewUrl }}"
                                 alt="{{ $resource->title }}"
                                 class="max-w-full max-h-[65vh] object-contain rounded shadow-sm">
                        </div>

                    @elseif($isVideo)
                        <div class="bg-black rounded overflow-hidden">
                            <video controls class="w-full" style="max-height: 60vh;">
                                <source src="{{ $previewUrl }}" type="{{ $fileType }}">
                            </video>
                        </div>

                    @elseif($isAudio)
                        <div class="flex items-center justify-center py-8 bg-gray-50 rounded">
                            <audio controls class="w-full max-w-xl">
                                <source src="{{ $previewUrl }}" type="{{ $fileType }}">
                            </audio>
                        </div>

                    @elseif($isOffice && $officeViewerUrl)
                        <div class="rounded overflow-hidden" style="height: 70vh;">
                            <iframe src="{{ $officeViewerUrl }}"
                                    class="w-full h-full"
                                    frameborder="0"
                                    allowfullscreen
                                    title="{{ $resource->title }}">
                            </iframe>
                        </div>
                        <p class="text-xs text-gray-400 text-center mt-2">
                            Powered by Microsoft Office Online &mdash; requires internet access
                        </p>
                    @endif
                </div>
            </div>
            @endif

            <!-- Comments Section -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 md:p-8">
                <h3 class="text-2xl font-bold text-gray-900 mb-6 flex items-center">
                    <i class="fas fa-comments mr-3"></i>
                    Comments ({{ $resource->comments->where('parent_id', null)->count() }})
                </h3>

                @auth
                    <!-- Comment Form -->
                    <form action="{{ route('resources.comment.store', $resource->slug) }}" method="POST" class="mb-8">
                        @csrf
                        <div class="mb-4">
                            <label for="content" class="block text-sm font-medium text-gray-700 mb-2">
                                Add a comment
                            </label>
                            <textarea name="content"
                                      id="content"
                                      rows="4"
                                      class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                      placeholder="Share your thoughts about this resource..."
                                      required></textarea>
                        </div>
                        <button type="submit"
                                class="inline-flex items-center px-6 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors font-medium">
                            <i class="fas fa-comment mr-2"></i>
                            Post Comment
                        </button>
                    </form>
                @else
                    <div class="mb-8 p-4 bg-gray-50 rounded-lg border border-gray-200">
                        <p class="text-gray-600 mb-3">Please log in to leave a comment.</p>
                        <a href="{{ url('admin/login') }}"
                           class="inline-flex items-center px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors">
                            <i class="fas fa-sign-in-alt mr-2"></i>
                            Login to Comment
                        </a>
                    </div>
                @endauth

                <!-- Comments List -->
                @if($resource->comments->where('parent_id', null)->count() > 0)
                    <div class="space-y-6">
                        @foreach($resource->comments->where('parent_id', null) as $comment)
                            @include('components.comment', ['comment' => $comment, 'resource' => $resource])
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-8 text-gray-500">
                        <i class="fas fa-comment text-4xl mb-4 text-gray-300"></i>
                        <p>No comments yet. Be the first to share your thoughts!</p>
                    </div>
                @endif
            </div>
        </main>

        <!-- Sidebar -->
        <aside class="lg:w-1/3">
            <!-- Resource Stats -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6 sticky top-24">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Resource Details</h3>

                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <span class="text-gray-600 flex items-center">
                            <i class="fas fa-eye mr-2"></i> Views
                        </span>
                        <span class="font-medium">{{ number_format($resource->view_count) }}</span>
                    </div>

                    @if($resource->is_downloadable)
                    <div class="flex items-center justify-between">
                        <span class="text-gray-600 flex items-center">
                            <i class="fas fa-download mr-2"></i> Downloads
                        </span>
                        <span class="font-medium">{{ number_format($resource->download_count) }}</span>
                    </div>
                    @endif

                    <div class="flex items-center justify-between">
                        <span class="text-gray-600 flex items-center">
                            <i class="fas fa-heart mr-2"></i> Likes
                        </span>
                        <span class="font-medium">{{ number_format($resource->like_count) }}</span>
                    </div>

                    @if($resource->file_size)
                    <div class="flex items-center justify-between">
                        <span class="text-gray-600 flex items-center">
                            <i class="fas fa-file mr-2"></i> File Size
                        </span>
                        <span class="font-medium">{{ $resource->formatted_file_size }}</span>
                    </div>
                    @endif

                    @if($resource->duration)
                    <div class="flex items-center justify-between">
                        <span class="text-gray-600 flex items-center">
                            <i class="fas fa-clock mr-2"></i> Duration
                        </span>
                        <span class="font-medium">{{ $resource->duration }}</span>
                    </div>
                    @endif
                </div>

                @if($resource->is_downloadable && ($resource->file_path || $resource->hasPrimaryFile()))
                <div class="mt-6 pt-6 border-t border-gray-200">
                    <a href="{{ route('resources.download', $resource->slug) }}"
                       class="w-full inline-flex items-center justify-center px-6 py-3 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors font-medium">
                        <i class="fas fa-download mr-2"></i>
                        Download Resource
                    </a>
                </div>
                @endif
            </div>

            <!-- Related Resources -->
            @if($relatedResources->count() > 0)
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Related Resources</h3>
                <div class="space-y-4">
                    @foreach($relatedResources as $related)
                        <div class="border-b border-gray-100 pb-4 last:border-b-0 last:pb-0">
                            <h4 class="font-medium text-gray-900 mb-2 line-clamp-2">
                                <a href="{{ route('resources.show', $related->slug) }}" class="hover:text-primary-600 transition-colors">
                                    {{ $related->title }}
                                </a>
                            </h4>
                            <div class="flex items-center justify-between text-sm text-gray-500">
                                <span>{{ $related->resourceType->name ?? 'Resource' }}</span>
                                <span>{{ $related->view_count }} views</span>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-4 pt-4 border-t border-gray-200">
                    <a href="{{ route('resources.category', $resource->category->slug) }}"
                       class="text-primary-600 hover:text-primary-700 text-sm font-medium">
                        View more in {{ $resource->category->name }} →
                    </a>
                </div>
            </div>
            @endif
        </aside>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('resourcePage', () => ({
            liked: {{ in_array('like', $userInteractions) ? 'true' : 'false' }},
            bookmarked: {{ in_array('bookmark', $userInteractions) ? 'true' : 'false' }},
            likeCount: {{ $resource->like_count }},

            async toggleLike() {
                try {
                    const response = await fetch(`{{ route('resources.like', $resource->slug) }}`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        }
                    });

                    const data = await response.json();
                    this.liked = data.liked;
                    this.likeCount = data.like_count;

                    this.showToast(this.liked ? 'Resource liked!' : 'Like removed!', 'success');
                } catch (error) {
                    console.error('Error:', error);
                    this.showToast('An error occurred', 'error');
                }
            },

            async toggleBookmark() {
                try {
                    const response = await fetch(`{{ route('resources.bookmark', $resource->slug) }}`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        }
                    });

                    const data = await response.json();
                    this.bookmarked = data.bookmarked;

                    this.showToast(data.message, 'success');
                } catch (error) {
                    console.error('Error:', error);
                    this.showToast('An error occurred', 'error');
                }
            },

            shareResource() {
                if (navigator.share) {
                    navigator.share({
                        title: '{{ $resource->title }}',
                        text: '{{ $resource->excerpt }}',
                        url: window.location.href
                    });
                } else {
                    // Fallback: copy to clipboard
                    navigator.clipboard.writeText(window.location.href).then(() => {
                        this.showToast('Link copied to clipboard!', 'success');
                    });
                }
            },

            showToast(message, type = 'info') {
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
        }))
    })
</script>
@endpush

@push('styles')
<style>
    .prose {
        max-width: none;
    }

    .prose h1, .prose h2, .prose h3, .prose h4, .prose h5, .prose h6 {
        @apply text-gray-900 font-semibold;
    }

    .prose p {
        @apply text-gray-700 leading-relaxed;
    }

    .prose a {
        @apply text-primary-600 hover:text-primary-700 underline;
    }

    .prose img {
        @apply rounded-lg shadow-sm;
    }

    .prose blockquote {
        @apply border-l-4 border-primary-500 bg-primary-50 p-4 rounded-r-lg;
    }

    .prose code {
        @apply bg-gray-100 px-2 py-1 rounded text-sm;
    }

    .prose pre {
        @apply bg-gray-900 text-gray-100 p-4 rounded-lg overflow-x-auto;
    }
</style>
@endpush
@endsection
