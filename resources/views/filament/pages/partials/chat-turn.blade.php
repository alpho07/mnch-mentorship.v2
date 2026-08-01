<div class="flex justify-start">
    <div class="max-w-lg w-full rounded-xl border border-gray-200 dark:border-gray-700 p-4 space-y-3">
        @if ($slot->renderKind() === \App\Services\Chat\Render::CARDS)
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                @foreach ($slot->getOptions($answers) as $value => $label)
                    <button
                        type="button"
                        wire:click="answer('{{ $slot->id }}', '{{ $value }}')"
                        class="rounded-lg border border-gray-300 dark:border-gray-600 px-4 py-2 text-sm text-left hover:border-primary-500 hover:bg-primary-50 dark:hover:bg-primary-900/20"
                    >
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        @endif

        @error('value')
            <p class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
        @enderror
    </div>
</div>
