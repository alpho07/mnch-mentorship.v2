<x-filament-panels::page>
    <p class="fi-section-header-description text-sm text-gray-500 dark:text-gray-400">
        @if ($showsEveryone)
            Mentorships that haven't started yet — yours and everyone in your scope. Pick up where they left off.
        @else
            Mentorships you're set as mentor for that haven't started yet — pick up where you left off.
        @endif
    </p>

    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="overflow-x-auto">
            <table class="fi-ta-table w-full text-start">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-white/10">
                        <th class="px-4 py-3 text-xs font-semibold uppercase text-gray-500">Mentorship</th>
                        @if ($showsEveryone)
                            <th class="px-4 py-3 text-xs font-semibold uppercase text-gray-500">Mentor</th>
                        @endif
                        <th class="px-4 py-3 text-xs font-semibold uppercase text-gray-500">Status</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase text-gray-500">Created</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase text-gray-500"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                    @forelse ($pending as $row)
                        <tr>
                            <td class="px-4 py-3 font-medium">
                                {{ $row['training']->title ?: 'Untitled mentorship' }}
                            </td>
                            @if ($showsEveryone)
                                <td class="px-4 py-3 text-sm text-gray-500">
                                    {{ $row['training']->mentor?->name ?? 'Unassigned' }}
                                </td>
                            @endif
                            <td class="px-4 py-3">
                                <x-filament::badge :color="match ($row['bucket']) {
                                    'no_class' => 'danger',
                                    'no_mentee' => 'warning',
                                    'no_modules' => 'info',
                                    default => 'gray',
                                }">
                                    {{ match ($row['bucket']) {
                                        'no_class' => 'Needs a class',
                                        'no_mentee' => 'Needs mentees',
                                        'no_modules' => 'Needs modules / start',
                                        default => $row['bucket'],
                                    } }}
                                </x-filament::badge>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-500">
                                {{ $row['days_stalled'] }} {{ Str::plural('day', $row['days_stalled']) }} ago
                            </td>
                            <td class="px-4 py-3 text-end">
                                <x-filament::button tag="a" :href="$row['continueUrl']" size="sm" icon="heroicon-o-arrow-right">
                                    Continue
                                </x-filament::button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $showsEveryone ? 5 : 4 }}" class="px-4 py-8 text-center text-sm text-gray-500">
                                Nothing pending — every mentorship in scope has been started.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>
