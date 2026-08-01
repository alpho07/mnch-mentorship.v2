<div class="flex justify-start">
    <div class="max-w-lg w-full rounded-xl border border-gray-200 dark:border-gray-700 p-4 space-y-3">
        @if ($slot->renderKind() === \App\Services\Chat\Render::CARDS)
            @php $cardOptions = $slot->getOptions($answers); @endphp
            <div x-data="{ search: '' }">
                @if (count($cardOptions) > 8)
                    <input
                        type="text"
                        x-model="search"
                        placeholder="Type to search..."
                        class="fi-input w-full rounded-lg border-gray-300 dark:border-gray-600 text-sm mb-2"
                    >
                @endif

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    @foreach ($cardOptions as $value => $label)
                        <button
                            type="button"
                            wire:click="answer('{{ $slot->id }}', '{{ $value }}')"
                            x-show="search === '' || @js(strtolower($label)).includes(search.toLowerCase())"
                            class="rounded-lg border border-gray-300 dark:border-gray-600 px-4 py-2 text-sm text-left hover:border-primary-500 hover:bg-primary-50 dark:hover:bg-primary-900/20"
                        >
                            {{ $label }}
                        </button>
                    @endforeach
                </div>

                @if (count($cardOptions) > 8)
                    <p
                        x-show="search !== '' && ! @js(array_map('strtolower', array_values($cardOptions))).some(l => l.includes(search.toLowerCase()))"
                        x-cloak
                        class="text-sm text-gray-500 dark:text-gray-400 mt-2"
                    >
                        No matches — try a different term.
                    </p>
                @endif
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
