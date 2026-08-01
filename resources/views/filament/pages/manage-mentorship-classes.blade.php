<x-filament-panels::page>
    @unless ($viewingModules)
        <div class="mb-4 flex items-center gap-3 rounded-xl border border-primary-200 bg-primary-50 px-5 py-4 dark:border-primary-800 dark:bg-primary-900/20">
            <span class="text-2xl">👋</span>
            <p class="text-sm text-primary-900 dark:text-primary-100">
                <span class="font-semibold">Welcome, {{ auth()->user()?->first_name ?? auth()->user()?->name }}!</span>
                Here are the classes for "{{ $record->title }}" — pick one below to manage its modules, mentees, and progress, or create a new class to get started.
            </p>
        </div>
    @endunless

    {{ $this->table }}
</x-filament-panels::page>
