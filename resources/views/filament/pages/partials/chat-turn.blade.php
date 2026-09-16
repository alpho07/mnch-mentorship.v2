<div class="flex justify-start" wire:key="turn-{{ $slot->id }}">
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
                            wire:loading.attr="disabled"
                            wire:target="answer"
                            x-show="search === '' || @js(strtolower($label)).includes(search.toLowerCase())"
                            class="rounded-lg border border-gray-300 dark:border-gray-600 px-4 py-2 text-sm text-left hover:border-primary-500 hover:bg-primary-50 dark:hover:bg-primary-900/20 disabled:cursor-wait disabled:opacity-60"
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

                @if ($slot->id === 'recipients')
                    <div wire:loading wire:target="answer" class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 mt-3">
                        <svg class="h-4 w-4 animate-spin text-primary-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Please hold on — creating the mentorship and sending invitations to your mentees. This can take a moment...
                    </div>
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
