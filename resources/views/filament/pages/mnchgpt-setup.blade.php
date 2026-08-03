<x-filament-panels::page>
    <div
        x-data
        x-init="$nextTick(() => $refs.scrollArea.scrollTop = $refs.scrollArea.scrollHeight)"
        x-on:mnchgpt-reply.window="$nextTick(() => $refs.scrollArea.scrollTop = $refs.scrollArea.scrollHeight)"
        style="height: 75vh;"
        class="flex flex-col overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900"
    >
        {{-- Title bar --}}
        <div class="flex shrink-0 items-center gap-3 border-b border-gray-200 px-4 py-3 dark:border-white/10">
            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary-600 text-xs font-bold text-white">
                AI
            </div>
            <div>
                <p class="text-sm font-semibold text-gray-900 dark:text-white">MNCHGPT</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">Mentorship setup assistant</p>
            </div>
        </div>

        @include('filament.pages.partials.mnchgpt-checklist', ['requirements' => $this->remainingRequirements()])

        {{-- Scrollable message area — min-height:0 (inline: min-h-0 isn't in
             this panel's compiled CSS) is required here: a flex child
             defaults to min-height:auto, which lets it grow to fit all its
             content instead of respecting the parent's bounded height, so
             without it this never actually scrolls (the whole card just
             grows past the viewport, clipped by overflow-hidden above). --}}
        <div x-ref="scrollArea" style="min-height: 0;" class="flex-1 overflow-y-auto px-4 py-4">
            @include('filament.pages.partials.mnchgpt-transcript', ['messages' => $messages])
        </div>

        {{-- Input / stage-specific turn / completed actions --}}
        <div class="shrink-0 border-t border-gray-200 px-4 py-3 dark:border-white/10">
            @unless ($completed)
                @if ($this->activeStage() === 'modules')
                    @unless ($this->isModulesStageEmonc())
                        @include('filament.pages.partials.mnchgpt-input')
                    @endunless

                    @include('filament.pages.partials.chat-modules-turn')
                @elseif ($this->activeStage() === 'enroll_mentees')
                    @include('filament.pages.partials.chat-mentees-turn')
                @else
                    @include('filament.pages.partials.mnchgpt-input')
                @endif
            @else
                <div class="flex gap-3">
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
    </div>
</x-filament-panels::page>
