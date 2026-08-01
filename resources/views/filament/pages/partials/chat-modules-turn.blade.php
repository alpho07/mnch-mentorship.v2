@php $moduleOptions = $this->getModuleFieldOptions(); @endphp
<div
    wire:key="turn-modules"
    x-data="{ selected: [], search: '' }"
    class="rounded-xl border border-gray-200 dark:border-gray-700 p-4 space-y-3"
>
    @if (count($moduleOptions) > 8)
        <input
            type="text"
            x-model="search"
            placeholder="Type to search..."
            class="fi-input w-full rounded-lg border-gray-300 dark:border-gray-600 text-sm"
        >
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
        @foreach ($moduleOptions as $id => $name)
            <label
                x-show="search === '' || @js(strtolower($name)).includes(search.toLowerCase())"
                class="flex items-center gap-2 rounded-lg border border-gray-200 dark:border-gray-700 px-3 py-2 text-sm cursor-pointer"
            >
                <input type="checkbox" value="{{ $id }}" x-model="selected">
                {{ $name }}
            </label>
        @endforeach
    </div>

    @if (count($moduleOptions) > 8)
        <p
            x-show="search !== '' && ! @js(array_map('strtolower', array_values($moduleOptions))).some(l => l.includes(search.toLowerCase()))"
            x-cloak
            class="text-sm text-gray-500 dark:text-gray-400"
        >
            No matches — try a different term.
        </p>
    @endif

    @error('value')
        <p class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
    @enderror

    <x-filament::button x-on:click="$wire.submitModules(selected)">
        Continue
    </x-filament::button>
</div>
