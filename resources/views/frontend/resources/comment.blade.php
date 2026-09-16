@props(['comment', 'resource', 'depth' => 0])

<div class="comment" style="margin-left: {{ $depth * 2 }}rem;">
    <div class="flex space-x-4">
        <!-- Avatar -->
        <div class="flex-shrink-0">
            @if($comment->user)
                <img src="https://ui-avatars.com/api/?name={{ urlencode($comment->user->full_name) }}&size=40&background=3b82f6&color=ffffff"
                     alt="{{ $comment->user->full_name }}"
                     class="w-10 h-10 rounded-full">
            @else
                <div class="w-10 h-10 bg-gray-300 rounded-full flex items-center justify-center">
                    <i class="fas fa-user text-gray-600"></i>
                </div>
            @endif
        </div>

        <!-- Comment Content -->
        <div class="flex-1 min-w-0">
            <div class="bg-gray-50 rounded-lg p-4">
                <!-- Comment Header -->
                <div class="flex items-center justify-between mb-2">
                    <div class="flex items-center space-x-2">
                        <h4 class="font-medium text-gray-900">
                            {{ $comment->user ? $comment->user->full_name : $comment->author_name }}
                        </h4>
                        @if(!$comment->user)
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                Guest
                            </span>
                        @endif
                        @if($comment->user && $comment->user->id === $resource->author_id)
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                                <i class="fas fa-crown mr-1"></i>
                                Author
                            </span>
                        @endif
                    </div>

                    <div class="flex items-center space-x-2">
                        <time class="text-sm text-gray-500" datetime="{{ $comment->created_at->toISOString() }}">
                            {{ $comment->created_at->diffForHumans() }}
                        </time>

                        @auth
                            @if($comment->user_id === auth()->id())
                                <!-- Edit/Delete for comment owner -->
                                <div class="relative" x-data="{ open: false }">
                                    <button @click="open = !open" class="text-gray-400 hover:text-gray-600 p-1">
                                        <i class="fas fa-ellipsis-h"></i>
                                    </button>

                                    <div x-show="open"
                                         @click.away="open = false"
                                         x-transition
                                         class="absolute right-0 mt-2 w-32 bg-white rounded-md shadow-lg py-1 z-10">
                                        <button @click="editComment({{ $comment->id }})"
                                                class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                            <i class="fas fa-edit mr-2"></i>Edit
                                        </button>
                                        <form action="{{ route('resources.comment.destroy', $comment) }}" method="POST" class="block">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    onclick="return confirm('Are you sure you want to delete this comment?')"
                                                    class="block w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-gray-100">
                                                <i class="fas fa-trash mr-2"></i>Delete
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            @endif
                        @endauth
                    </div>
                </div>

                <!-- Comment Text -->
                <div class="text-gray-700 prose prose-sm max-w-none" id="comment-content-{{ $comment->id }}">
                    {{ $comment->content }}
                </div>

                <!-- Edit Form (Hidden by default) -->
                @auth
                    @if($comment->user_id === auth()->id())
                        <form action="{{ route('resources.comment.update', $comment) }}"
                              method="POST"
                              id="edit-form-{{ $comment->id }}"
                              class="hidden mt-4">
                            @csrf
                            @method('PUT')
                            <textarea name="content"
                                      rows="3"
                                      class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                      required>{{ $comment->content }}</textarea>
                            <div class="flex items-center space-x-3 mt-3">
                                <button type="submit"
                                        class="inline-flex items-center px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors text-sm">
                                    <i class="fas fa-save mr-2"></i>Save
                                </button>
                                <button type="button"
                                        @click="cancelEdit({{ $comment->id }})"
                                        class="inline-flex items-center px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition-colors text-sm">
                                    Cancel
                                </button>
                            </div>
                        </form>
                    @endif
                @endauth
            </div>

            <!-- Comment Actions -->
            <div class="flex items-center space-x-4 mt-3">
                @auth
                    @if($depth < 3)
                        <button @click="toggleReplyForm({{ $comment->id }})"
                                class="text-sm text-gray-500 hover:text-primary-600 transition-colors">
                            <i class="fas fa-reply mr-1"></i>Reply
                        </button>
                    @endif
                @else
                    <a href="{{ url('admin/login') }}"
                       class="text-sm text-gray-500 hover:text-primary-600 transition-colors">
                        <i class="fas fa-reply mr-1"></i>Reply
                    </a>
                @endauth
            </div>

            <!-- Reply Form -->
            @auth
                @if($depth < 3)
                    <form action="{{ route('resources.comment.store', $resource->slug) }}"
                          method="POST"
                          id="reply-form-{{ $comment->id }}"
                          class="hidden mt-4">
                        @csrf
                        <input type="hidden" name="parent_id" value="{{ $comment->id }}">
                        <div class="flex space-x-3">
                            <img src="https://ui-avatars.com/api/?name={{ urlencode(auth()->user()->full_name) }}&size=32&background=3b82f6&color=ffffff"
                                 alt="{{ auth()->user()->full_name }}"
                                 class="w-8 h-8 rounded-full flex-shrink-0">
                            <div class="flex-1">
                                <textarea name="content"
                                          rows="3"
                                          placeholder="Write a reply..."
                                          class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                          required></textarea>
                                <div class="flex items-center space-x-3 mt-3">
                                    <button type="submit"
                                            class="inline-flex items-center px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors text-sm">
                                        <i class="fas fa-comment mr-2"></i>Reply
                                    </button>
                                    <button type="button"
                                            @click="toggleReplyForm({{ $comment->id }})"
                                            class="inline-flex items-center px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition-colors text-sm">
                                        Cancel
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                @endif
            @endauth

            <!-- Nested Replies -->
            @if($comment->replies && $comment->replies->count() > 0)
                <div class="mt-4 space-y-4">
                    @foreach($comment->replies as $reply)
                        @include('components.comment', ['comment' => $reply, 'resource' => $resource, 'depth' => $depth + 1])
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>

@once
@push('scripts')
<script>
    function toggleReplyForm(commentId) {
        const form = document.getElementById(`reply-form-${commentId}`);
        form.classList.toggle('hidden');

        if (!form.classList.contains('hidden')) {
            form.querySelector('textarea').focus();
        }
    }

    function editComment(commentId) {
        const content = document.getElementById(`comment-content-${commentId}`);
        const form = document.getElementById(`edit-form-${commentId}`);

        content.classList.add('hidden');
        form.classList.remove('hidden');
        form.querySelector('textarea').focus();
    }

    function cancelEdit(commentId) {
        const content = document.getElementById(`comment-content-${commentId}`);
        const form = document.getElementById(`edit-form-${commentId}`);

        form.classList.add('hidden');
        content.classList.remove('hidden');
    }
</script>
@endpush
@endonce
