{{-- File: resources/views/filament/modals/topic-details.blade.php --}}

<div class="space-y-4">
    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
        <div class="flex items-center space-x-2 mb-2">
            <x-heroicon-o-book-open class="w-5 h-5 text-gray-500" />
            <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Module</span>
        </div>
        <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">
            {{ $topic->module->name }}
        </p>
        <p class="text-sm text-gray-600 dark:text-gray-400">
            Program: {{ $topic->module->program->name }}
        </p>
    </div>

    <div>
        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">Description</h3>
        @if($topic->description)
            <p class="text-gray-700 dark:text-gray-300 leading-relaxed">
                {{ $topic->description }}
            </p>
        @else
            <p class="text-gray-500 dark:text-gray-400 italic">No description provided.</p>
        @endif
    </div>

    @if($topic->trainingLinks->count() > 0)
        <div>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">Training Links</h3>
            <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-3">
                <p class="text-sm text-blue-800 dark:text-blue-200">
                    This topic is linked to {{ $topic->trainingLinks->count() }} training(s).
                </p>
            </div>
        </div>
    @endif

    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
        <div class="grid grid-cols-2 gap-4 text-sm">
            <div>
                <span class="font-medium text-gray-600 dark:text-gray-400">Created:</span>
                <p class="text-gray-900 dark:text-gray-100">{{ $topic->created_at->format('M j, Y') }}</p>
            </div>
            <div>
                <span class="font-medium text-gray-600 dark:text-gray-400">Last Updated:</span>
                <p class="text-gray-900 dark:text-gray-100">{{ $topic->updated_at->format('M j, Y') }}</p>
            </div>
        </div>
    </div>
</div>