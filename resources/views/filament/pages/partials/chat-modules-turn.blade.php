<div
    x-data="{ selected: [] }"
    class="rounded-xl border border-gray-200 dark:border-gray-700 p-4 space-y-3"
>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
        @foreach ($this->getModuleFieldOptions() as $id => $name)
            <label class="flex items-center gap-2 rounded-lg border border-gray-200 dark:border-gray-700 px-3 py-2 text-sm cursor-pointer">
                <input type="checkbox" value="{{ $id }}" x-model="selected">
                {{ $name }}
            </label>
        @endforeach
    </div>

    @error('value')
        <p class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
    @enderror

    <x-filament::button x-on:click="$wire.submitModules(selected)">
        Continue
    </x-filament::button>
</div>
