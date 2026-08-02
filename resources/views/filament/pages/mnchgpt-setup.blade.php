<x-filament-panels::page>
    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6 space-y-4">
        @include('filament.pages.partials.mnchgpt-checklist', ['requirements' => $this->remainingRequirements()])

        @include('filament.pages.partials.chat-transcript', ['messages' => $messages])

        @unless ($completed)
            @if ($this->activeStage() === 'modules')
                @include('filament.pages.partials.chat-modules-turn')
            @elseif ($this->activeStage() === 'enroll_mentees')
                @include('filament.pages.partials.chat-mentees-turn')
            @else
                @include('filament.pages.partials.mnchgpt-input')

                @if ($this->nextUnfilledSlot())
                    @include('filament.pages.partials.chat-turn', ['slot' => $this->nextUnfilledSlot(), 'answers' => $answers])
                @endif
            @endif
        @else
            <div class="flex gap-3 pt-2">
                <a href="{{ \App\Filament\Resources\MentorshipTrainingResource::getUrl('classes', ['record' => $training->id]) }}"
                   class="fi-btn fi-btn-color-primary fi-btn-size-md fi-color-primary rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white">
                    Go to Class
                </a>
                <a href="{{ \App\Filament\Resources\MentorshipTrainingResource::getUrl('index') }}"
                   class="fi-btn fi-btn-color-gray fi-btn-size-md rounded-lg px-4 py-2 text-sm font-semibold ring-1 ring-gray-300 dark:ring-gray-700">
                    Back to Mentorships
                </a>
            </div>
        @endunless
    </div>
</x-filament-panels::page>
