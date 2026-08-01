<x-filament-panels::page>
    @if ($completed)
        <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <p class="text-lg font-semibold text-gray-950 dark:text-white">
                Mentorship "{{ $training?->title }}" created.
            </p>
            @if ($class)
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Class "{{ $class->name }}" has {{ $invitedCount }} mentee(s) invited.
                    @if ($classStarted)
                        The class is now <span class="font-semibold text-success-600 dark:text-success-400">active</span> — modules are open and mentors can begin.
                    @else
                        It's still saved as a draft — add modules and enroll mentees before it can start.
                    @endif
                </p>
            @endif
            <div class="mt-4 flex gap-3">
                @if ($class)
                    <a href="{{ \App\Filament\Resources\MentorshipTrainingResource::getUrl('classes', ['record' => $training->id]) }}"
                       class="fi-btn fi-btn-color-primary fi-btn-size-md fi-color-primary rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white">
                        Go to Class
                    </a>
                @endif
                <a href="{{ \App\Filament\Resources\MentorshipTrainingResource::getUrl('index') }}"
                   class="fi-btn fi-btn-color-gray fi-btn-size-md rounded-lg px-4 py-2 text-sm font-semibold ring-1 ring-gray-300 dark:ring-gray-700">
                    Back to Mentorships
                </a>
            </div>
        </div>
    @else
        <form wire:submit="submit">
            {{ $this->form }}
        </form>
    @endif
</x-filament-panels::page>
