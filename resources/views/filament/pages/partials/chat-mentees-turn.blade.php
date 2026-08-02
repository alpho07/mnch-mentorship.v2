@php $menteeCap = $this->training->max_participants; @endphp
<div
    wire:key="turn-enroll-mentees"
    x-data="{
        selected: [],
        newEmail: '', newFirst: '', newLast: '',
        max: {{ $menteeCap === null ? 'null' : (int) $menteeCap }},
        atLimit() {
            return this.max !== null && this.selected.length >= this.max;
        },
        toggle(id) {
            const idStr = String(id);
            if (this.selected.includes(idStr)) {
                this.selected = this.selected.filter(v => v !== idStr);
                return;
            }
            if (this.atLimit()) {
                return;
            }
            this.selected = [...this.selected, idStr];
        }
    }"
    class="rounded-xl border border-gray-200 dark:border-gray-700 p-4 space-y-3"
>
    <input
        type="text"
        wire:model.live.debounce.400ms="menteeSearch"
        placeholder="Search by name, phone, email, or facility..."
        class="fi-input w-full rounded-lg border-gray-300 dark:border-gray-600 text-sm"
    >

    @if ($menteeCap)
        <div class="text-sm font-medium text-gray-700 dark:text-gray-300">
            <span x-text="selected.length"></span>/{{ $menteeCap }} selected
            <span x-show="atLimit()" x-cloak class="ml-1 text-warning-600 dark:text-warning-400">
                — limit reached, uncheck one to pick another
            </span>
        </div>
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
        @foreach ($this->getMenteeFieldOptions() as $id => $label)
            <label
                class="flex items-center gap-2 rounded-lg border border-gray-200 dark:border-gray-700 px-3 py-2 text-sm select-none transition-opacity"
                :class="{ 'cursor-not-allowed opacity-50': !selected.includes(@js((string) $id)) && atLimit(), 'cursor-pointer': selected.includes(@js((string) $id)) || !atLimit() }"
                @click.prevent="toggle(@js((string) $id))"
            >
                <input
                    type="checkbox"
                    :checked="selected.includes(@js((string) $id))"
                    :disabled="!selected.includes(@js((string) $id)) && atLimit()"
                    class="pointer-events-none"
                >
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
        x-on:click="$wire.checkAndSubmitMentees(selected, { email: newEmail, first_name: newFirst, last_name: newLast })"
    >
        Continue
    </x-filament::button>
</div>

@if (! empty($menteesNeedingEmail))
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-950/50 p-6">
        <div class="w-full max-w-sm rounded-xl bg-white p-6 shadow-xl dark:bg-gray-900 space-y-4">
            <h3 class="text-base font-semibold text-gray-950 dark:text-white">
                Add an email to send an invite
            </h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ count($menteesNeedingEmail) }} selected mentee(s) don't have an email on file, so they can't be invited yet. Add one now, or leave blank to skip inviting them for now.
            </p>

            <div class="space-y-3 max-h-64 overflow-y-auto">
                @foreach ($menteesNeedingEmail as $mentee)
                    <div>
                        <label class="text-xs font-medium text-gray-700 dark:text-gray-300">{{ $mentee['name'] }}</label>
                        <input
                            type="email"
                            wire:model="pendingEmails.{{ $mentee['id'] }}"
                            placeholder="email@example.com"
                            class="mt-1.5 block w-full rounded-lg border-gray-300 text-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                        >
                    </div>
                @endforeach
            </div>

            <div class="flex justify-end gap-2">
                <button
                    type="button"
                    wire:click="cancelMenteeEmailPrompt"
                    class="rounded-lg px-3.5 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800"
                >
                    Cancel
                </button>
                <button
                    type="button"
                    wire:click="saveMenteeEmailsAndContinue"
                    class="rounded-lg bg-primary-600 px-3.5 py-2 text-sm font-semibold text-white hover:bg-primary-500"
                >
                    Save &amp; Continue
                </button>
            </div>
        </div>
    </div>
@endif
