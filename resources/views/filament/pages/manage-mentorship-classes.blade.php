<x-filament-panels::page>
    @unless ($viewingModules)
        <div class="mb-4 rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="flex items-start gap-3">
                <span class="text-2xl">👋</span>
                <div class="text-sm text-gray-700 dark:text-gray-300">
                    <p>
                        <span class="font-semibold text-gray-950 dark:text-white">Welcome, {{ auth()->user()?->first_name ?? auth()->user()?->name }}!</span>
                        Here are the classes for "{{ $record->title }}" — pick one below, or create a new class to get started.
                    </p>
                    <p class="mt-3 font-medium text-gray-950 dark:text-white">
                        Look for the <x-heroicon-o-ellipsis-vertical class="inline h-4 w-4 -mt-0.5" /> (three dots) at the end of each row:
                    </p>
                    <ol class="mt-2 list-decimal space-y-1.5 pl-5">
                        <li>
                            Click <span class="font-semibold text-gray-950 dark:text-white">"View Modules"</span>
                            to view program modules and attendance, and to start or complete modules for that class.
                        </li>
                        <li>
                            Click <span class="font-semibold text-gray-950 dark:text-white">"Manage/Invite Mentees"</span>
                            to invite more mentees and send emails, see the invitation link, start/end the class, and view the class report.
                        </li>
                    </ol>
                </div>
            </div>
        </div>
    @endunless

    {{ $this->table }}
</x-filament-panels::page>
