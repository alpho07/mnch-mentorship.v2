<div x-data="{ selected: [], newEmail: '', newFirst: '', newLast: '' }" class="rounded-xl border border-gray-200 dark:border-gray-700 p-4 space-y-3">
    <input
        type="text"
        wire:model.live.debounce.400ms="menteeSearch"
        placeholder="Search by name, phone, email, or facility..."
        class="fi-input w-full rounded-lg border-gray-300 dark:border-gray-600 text-sm"
    >

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
        @foreach ($this->getMenteeFieldOptions() as $id => $label)
            <label class="flex items-center gap-2 rounded-lg border border-gray-200 dark:border-gray-700 px-3 py-2 text-sm cursor-pointer">
                <input type="checkbox" value="{{ $id }}" x-model="selected">
                {{ $label }}
            </label>
        @endforeach
    </div>

    <div class="flex gap-2 text-sm">
        <button type="button" wire:click="$set('menteePage', {{ max(1, $menteePage - 1) }})" class="text-gray-500">← Previous</button>
        <button type="button" wire:click="$set('menteePage', {{ $menteePage + 1 }})" class="text-gray-500">Next →</button>
    </div>

    <details class="text-sm">
        <summary class="cursor-pointer text-primary-600">+ Add a new mentee</summary>
        <div class="mt-2 space-y-2">
            <input type="email" x-model="newEmail" placeholder="Email" class="fi-input w-full rounded-lg border-gray-300 dark:border-gray-600 text-sm">
            <input type="text" x-model="newFirst" placeholder="First name" class="fi-input w-full rounded-lg border-gray-300 dark:border-gray-600 text-sm">
            <input type="text" x-model="newLast" placeholder="Last name" class="fi-input w-full rounded-lg border-gray-300 dark:border-gray-600 text-sm">
        </div>
    </details>

    @error('value')
        <p class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
    @enderror

    <x-filament::button
        x-on:click="$wire.submitMentees(selected, { email: newEmail, first_name: newFirst, last_name: newLast })"
    >
        Continue
    </x-filament::button>
</div>
