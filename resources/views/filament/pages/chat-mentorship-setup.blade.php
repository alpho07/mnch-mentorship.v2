<x-filament-panels::page>
    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6 space-y-4">
        @include('filament.pages.partials.chat-transcript', ['messages' => $messages])

        @unless ($completed)
            @if ($this->activeStage() === 'modules')
                @include('filament.pages.partials.chat-modules-turn')
            @elseif ($this->activeStage() === 'enroll_mentees')
                @include('filament.pages.partials.chat-mentees-turn')
            @elseif ($this->nextUnfilledSlot())
                @include('filament.pages.partials.chat-turn', ['slot' => $this->nextUnfilledSlot(), 'answers' => $answers])
            @endif
        @endunless
    </div>
</x-filament-panels::page>
