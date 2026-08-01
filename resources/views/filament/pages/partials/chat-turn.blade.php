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

        @if ($slot->renderKind() === \App\Services\Chat\Render::WIDGET)
            <form wire:submit.prevent="answer('{{ $slot->id }}', $refs.widgetInput.value)" class="flex gap-2">
                @if (str_ends_with($slot->id, '_date'))
                    <input type="date" x-ref="widgetInput" class="fi-input rounded-lg border-gray-300 dark:border-gray-600 text-sm" required>
                @else
                    <input type="number" x-ref="widgetInput" min="2" max="10" class="fi-input rounded-lg border-gray-300 dark:border-gray-600 text-sm" required>
                @endif
                <x-filament::button type="submit">Send</x-filament::button>
            </form>
        @endif

        @if ($slot->renderKind() === \App\Services\Chat\Render::FREE_TEXT)
            <form wire:submit.prevent="answer('{{ $slot->id }}', $refs.textInput.value)" class="flex gap-2">
                <input type="text" x-ref="textInput" class="fi-input flex-1 rounded-lg border-gray-300 dark:border-gray-600 text-sm" {{ $slot->isRequired() ? 'required' : '' }}>
                <x-filament::button type="submit">Send</x-filament::button>
            </form>
        @endif

        @error('value')
            <p class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
        @enderror
    </div>
</div>
