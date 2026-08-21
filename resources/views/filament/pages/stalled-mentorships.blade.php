<x-filament-panels::page>
    <div class="flex items-center justify-between gap-4">
        <p class="fi-section-header-description text-sm text-gray-500 dark:text-gray-400">
            Facility mentorships stuck in draft — no class created, no mentee enrolled, or mentees enrolled with no
            curriculum modules assigned. {{ $dueCount }} {{ Str::plural('mentorship', $dueCount) }} due for a reminder right now.
        </p>

        @if ($dueCount > 0)
            <x-filament::button
                wire:click="sendAllDue"
                wire:confirm="Send a reminder to every mentor with a due mentorship?"
                icon="heroicon-o-paper-airplane"
            >
                Send all due ({{ $dueCount }})
            </x-filament::button>
        @endif
    </div>

    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="overflow-x-auto">
            <table class="fi-ta-table w-full text-start">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-white/10">
                        <th class="px-4 py-3 text-xs font-semibold uppercase text-gray-500">Mentorship</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase text-gray-500">Mentor</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase text-gray-500">Status</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase text-gray-500">Stalled</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase text-gray-500">Last reminded</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase text-gray-500"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                    @forelse ($stalled as $row)
                        <tr>
                            <td class="px-4 py-3">
                                <a href="{{ $row['editUrl'] }}" class="font-medium text-primary-600 hover:underline">
                                    {{ $row['training']->title ?: 'Untitled mentorship' }}
                                </a>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                @if ($row['training']->mentor)
                                    <div class="font-medium text-gray-900 dark:text-gray-100">{{ $row['training']->mentor->name }}</div>
                                    <div class="text-xs text-gray-500">
                                        {{ $row['training']->mentor->email ?: '—' }}
                                        @if ($row['training']->mentor->phone)
                                            · {{ $row['training']->mentor->phone }}
                                        @endif
                                    </div>
                                @else
                                    <span class="text-gray-400">Unassigned</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <x-filament::badge :color="match ($row['bucket']) {
                                    'no_class' => 'danger',
                                    'no_mentee' => 'warning',
                                    'no_modules' => 'info',
                                    default => 'gray',
                                }">
                                    {{ match ($row['bucket']) {
                                        'no_class' => 'No class created',
                                        'no_mentee' => 'No mentees enrolled',
                                        'no_modules' => 'No modules assigned',
                                        default => $row['bucket'],
                                    } }}
                                </x-filament::badge>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                {{ $row['days_stalled'] }} {{ Str::plural('day', $row['days_stalled']) }}
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-500">
                                {{ $row['last_reminded_at']?->diffForHumans() ?? 'Never' }}
                            </td>
                            <td class="px-4 py-3 text-end">
                                <div class="flex items-center justify-end gap-1.5">
                                    <x-filament::button
                                        size="sm"
                                        :color="$row['due'] ? 'primary' : 'gray'"
                                        wire:click="sendReminder({{ $row['training']->id }}, '{{ $row['bucket'] }}')"
                                        wire:confirm="Send a stall reminder to {{ $row['training']->mentor?->name ?? 'this mentor' }}?"
                                    >
                                        {{ $row['due'] ? 'Send reminder' : 'Send anyway' }}
                                    </x-filament::button>
                                    <x-filament::icon-button
                                        icon="heroicon-o-no-symbol"
                                        color="warning"
                                        label="Make inactive"
                                        tooltip="Make inactive"
                                        wire:click="deactivateMentorship({{ $row['training']->id }})"
                                        wire:confirm="Mark &quot;{{ $row['training']->title }}&quot; inactive? This can be reversed."
                                    />
                                    <x-filament::icon-button
                                        icon="heroicon-o-trash"
                                        color="danger"
                                        label="Delete"
                                        tooltip="Delete"
                                        wire:click="deleteMentorship({{ $row['training']->id }})"
                                        wire:confirm="Delete &quot;{{ $row['training']->title }}&quot;? This can be restored later from Recently Actioned below."
                                    />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500">
                                No stalled mentorships right now.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($recentlyActioned->isNotEmpty())
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="px-4 pt-4">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Recently Actioned</h3>
                <p class="text-xs text-gray-500">Deactivated or deleted in the last 90 days — reverse here.</p>
            </div>
            <div class="overflow-x-auto mt-2">
                <table class="fi-ta-table w-full text-start">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-white/10">
                            <th class="px-4 py-3 text-xs font-semibold uppercase text-gray-500">Mentorship</th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase text-gray-500">Mentor</th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase text-gray-500">State</th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase text-gray-500"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                        @foreach ($recentlyActioned as $training)
                            <tr>
                                <td class="px-4 py-3 font-medium">{{ $training->title ?: 'Untitled mentorship' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500">{{ $training->mentor?->name ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    @if ($training->trashed())
                                        <x-filament::badge color="danger">Deleted</x-filament::badge>
                                    @else
                                        <x-filament::badge color="warning">Inactive</x-filament::badge>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-end">
                                    @if ($training->trashed())
                                        <x-filament::button
                                            size="sm"
                                            color="gray"
                                            icon="heroicon-o-arrow-uturn-left"
                                            wire:click="restoreMentorship({{ $training->id }})"
                                        >
                                            Restore
                                        </x-filament::button>
                                    @else
                                        <x-filament::button
                                            size="sm"
                                            color="gray"
                                            icon="heroicon-o-arrow-uturn-left"
                                            wire:click="reactivateMentorship({{ $training->id }})"
                                        >
                                            Reactivate
                                        </x-filament::button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</x-filament-panels::page>
