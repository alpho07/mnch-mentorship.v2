<style>
@keyframes guide-pulse {
    0%,100% { box-shadow: 0 0 0 0 rgba(0,151,167,0.55); }
    50%      { box-shadow: 0 0 0 10px rgba(0,151,167,0); }
}
.guide-pulse-btn { animation: guide-pulse 2s ease-in-out infinite; }
</style>

<div
    x-data="{
        showPrompt: false,
        open: false,
        step: 0,
        init() {
            if (!localStorage.getItem('mnch_guide_prompted')) {
                this.$nextTick(() => { this.showPrompt = true; });
            }
        },
        dismiss() {
            localStorage.setItem('mnch_guide_prompted', '1');
            this.showPrompt = false;
        },
        openGuide() {
            this.step = 0;
            this.open = true;
        },
        acceptGuide() {
            this.dismiss();
            this.openGuide();
        },
        next() { if (this.step < 6) this.step++; },
        prev() { if (this.step > 0) this.step--; },
        close() { this.open = false; }
    }"
>
    {{-- ── Auto-prompt banner ─────────────────────────────────── --}}
    <div
        x-show="showPrompt"
        x-cloak
        x-transition:enter="transition ease-out duration-400"
        x-transition:enter-start="opacity-0 -translate-y-3"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 -translate-y-3"
        class="mb-4 flex flex-wrap items-center justify-between gap-4 rounded-xl border border-primary-200 bg-primary-50 px-5 py-4 dark:border-primary-800 dark:bg-primary-900/20"
    >
        <div class="flex items-center gap-3">
            <span class="text-2xl">👋</span>
            <p class="text-base font-medium text-primary-900 dark:text-primary-100">
                New here? Want a quick walkthrough of how mentorships work on this system?
            </p>
        </div>
        <div class="flex shrink-0 gap-2">
            <button
                type="button"
                @click="acceptGuide()"
                class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-primary-700 focus:outline-none"
            >
                Yes, show me
            </button>
            <button
                type="button"
                @click="dismiss()"
                class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
            >
                No thanks
            </button>
        </div>
    </div>

    {{-- ── Existing guidance notice (content unchanged) ───────── --}}
    <x-filament::section>
        <div class="space-y-4">
            <div class="flex items-start justify-between gap-4">
                <div class="flex items-start gap-3">
                    <x-filament::icon icon="heroicon-o-light-bulb" class="mt-1 h-6 w-6 text-primary-600" />
                    <div class="space-y-1">
                        <h3 class="text-base font-semibold text-gray-950 dark:text-white">
                            Prepare before starting mentorship
                        </h3>
                        <p class="text-sm leading-6 text-gray-600 dark:text-gray-300">
                            First identify the gaps at the facility, then review the mentorship manuals so you know what to mentor on and how each session should be delivered. After that, confirm the mentee list and check every mentee has a name, email, phone, cadre, and department before you start the class.
                        </p>
                    </div>
                </div>
                <button
                    type="button"
                    @click="openGuide()"
                    class="guide-pulse-btn shrink-0 flex items-center gap-2 rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-md hover:bg-primary-700 focus:outline-none"
                >
                    <x-filament::icon icon="heroicon-o-play-circle" class="h-5 w-5 text-white" />
                    How does this work?
                </button>
            </div>

            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                @foreach($manualLinks as $link)
                    <a href="{{ $link['url'] }}" target="_blank" rel="noopener noreferrer"
                       class="rounded-lg border border-gray-200 bg-white p-4 text-sm transition hover:border-primary-300 hover:bg-primary-50 dark:border-gray-700 dark:bg-gray-900 dark:hover:border-primary-600 dark:hover:bg-gray-800">
                        <div class="font-semibold text-gray-950 dark:text-white">{{ $link['label'] }}</div>
                        <div class="mt-1 text-gray-600 dark:text-gray-400">{{ $link['description'] }}</div>
                    </a>
                @endforeach
                <a href="{{ route('resources.search', ['q' => 'manual']) }}" target="_blank" rel="noopener noreferrer"
                   class="rounded-lg border border-gray-200 bg-white p-4 text-sm transition hover:border-primary-300 hover:bg-primary-50 dark:border-gray-700 dark:bg-gray-900 dark:hover:border-primary-600 dark:hover:bg-gray-800">
                    <div class="font-semibold text-gray-950 dark:text-white">Search Mentorship Manuals</div>
                    <div class="mt-1 text-gray-600 dark:text-gray-400">Find manuals, guides, and learning materials.</div>
                </a>
                <a href="{{ route('resources.search', ['q' => 'MNCH guidelines']) }}" target="_blank" rel="noopener noreferrer"
                   class="rounded-lg border border-gray-200 bg-white p-4 text-sm transition hover:border-primary-300 hover:bg-primary-50 dark:border-gray-700 dark:bg-gray-900 dark:hover:border-primary-600 dark:hover:bg-gray-800">
                    <div class="font-semibold text-gray-950 dark:text-white">MNCH Guidelines</div>
                    <div class="mt-1 text-gray-600 dark:text-gray-400">Review supporting protocols before mentoring.</div>
                </a>
            </div>
        </div>
    </x-filament::section>

    {{-- ── Guide modal ────────────────────────────────────────── --}}
    <div
        x-show="open"
        x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        @click.self="close()"
        class="guide-modal-overlay"
    >
        <div
            @click.stop
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 scale-95 translate-y-4"
            x-transition:enter-end="opacity-100 scale-100 translate-y-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 scale-100 translate-y-0"
            x-transition:leave-end="opacity-0 scale-95 translate-y-4"
            class="guide-modal-panel"
        >
            {{-- Modal top bar: step counter + progress bar + close --}}
            <div class="guide-modal-header">
                <span class="shrink-0 text-sm font-semibold text-gray-500 dark:text-gray-400">
                    Step <span x-text="step + 1" class="text-primary-600 dark:text-primary-400"></span> of 7
                </span>
                <div class="flex-1 h-2.5 rounded-full bg-gray-200 dark:bg-gray-700">
                    <div
                        class="h-2.5 rounded-full bg-primary-500 transition-all duration-500 ease-out"
                        :style="`width: ${Math.round(((step + 1) / 7) * 100)}%`"
                    ></div>
                </div>
                <button
                    type="button"
                    @click="close()"
                    class="shrink-0 rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-300"
                    aria-label="Close guide"
                >
                    <x-filament::icon icon="heroicon-o-x-mark" class="h-5 w-5" />
                </button>
            </div>

            {{-- Step cards --}}
            <div class="guide-modal-body">

                {{-- Step 1: Create a Mentorship --}}
                <div x-show="step === 0"
                     x-transition:enter="transition ease-out duration-300"
                     x-transition:enter-start="opacity-0 scale-95"
                     x-transition:enter-end="opacity-100 scale-100"
                     x-transition:leave="transition ease-in duration-200"
                     x-transition:leave-start="opacity-100 scale-100"
                     x-transition:leave-end="opacity-0 scale-95"
                     class="flex flex-col items-center text-center">
                    <x-filament::icon icon="heroicon-o-academic-cap" class="mb-4 h-16 w-16 text-primary-500" />
                    <h2 class="mb-5 text-2xl font-bold text-gray-900 dark:text-white">Create a Mentorship</h2>
                    <ul class="w-full space-y-3 text-left text-base text-gray-700 dark:text-gray-300">
                        <li class="flex gap-2"><span class="mt-0.5 shrink-0 font-bold text-primary-500">✔</span>Select the county and facility where mentoring will happen</li>
                        <li class="flex gap-2"><span class="mt-0.5 shrink-0 font-bold text-primary-500">✔</span>Choose the program (e.g. Newborn Care, Infant &amp; Child)</li>
                        <li class="flex gap-2"><span class="mt-0.5 shrink-0 font-bold text-primary-500">✔</span>Set the start date, end date, and maximum number of mentees</li>
                    </ul>
                </div>

                {{-- Step 2: Set Up a Class --}}
                <div x-show="step === 1"
                     x-transition:enter="transition ease-out duration-300"
                     x-transition:enter-start="opacity-0 scale-95"
                     x-transition:enter-end="opacity-100 scale-100"
                     x-transition:leave="transition ease-in duration-200"
                     x-transition:leave-start="opacity-100 scale-100"
                     x-transition:leave-end="opacity-0 scale-95"
                     class="flex flex-col items-center text-center">
                    <x-filament::icon icon="heroicon-o-user-group" class="mb-4 h-16 w-16 text-primary-500" />
                    <h2 class="mb-5 text-2xl font-bold text-gray-900 dark:text-white">Set Up a Class</h2>
                    <ul class="w-full space-y-3 text-left text-base text-gray-700 dark:text-gray-300">
                        <li class="flex gap-2"><span class="mt-0.5 shrink-0 font-bold text-primary-500">✔</span>Give the class a name, like "Cohort 1" or "July Group"</li>
                        <li class="flex gap-2"><span class="mt-0.5 shrink-0 font-bold text-primary-500">✔</span>Set the class start and end dates within the mentorship period</li>
                        <li class="flex gap-2"><span class="mt-0.5 shrink-0 font-bold text-primary-500">✔</span>One mentorship can run many classes — one after another or at the same time</li>
                    </ul>
                </div>

                {{-- Step 3: Add Modules --}}
                <div x-show="step === 2"
                     x-transition:enter="transition ease-out duration-300"
                     x-transition:enter-start="opacity-0 scale-95"
                     x-transition:enter-end="opacity-100 scale-100"
                     x-transition:leave="transition ease-in duration-200"
                     x-transition:leave-start="opacity-100 scale-100"
                     x-transition:leave-end="opacity-0 scale-95"
                     class="flex flex-col items-center text-center">
                    <x-filament::icon icon="heroicon-o-book-open" class="mb-4 h-16 w-16 text-primary-500" />
                    <h2 class="mb-5 text-2xl font-bold text-gray-900 dark:text-white">Add Modules</h2>
                    <ul class="w-full space-y-3 text-left text-base text-gray-700 dark:text-gray-300">
                        <li class="flex gap-2"><span class="mt-0.5 shrink-0 font-bold text-primary-500">✔</span>Modules are the topics you will teach in this class</li>
                        <li class="flex gap-2"><span class="mt-0.5 shrink-0 font-bold text-primary-500">✔</span>Pick them from the program's ready-made curriculum list</li>
                        <li class="flex gap-2"><span class="mt-0.5 shrink-0 font-bold text-primary-500">✔</span>You can also add sessions to each module to break it into smaller lessons</li>
                    </ul>
                </div>

                {{-- Step 4: Enroll Mentees --}}
                <div x-show="step === 3"
                     x-transition:enter="transition ease-out duration-300"
                     x-transition:enter-start="opacity-0 scale-95"
                     x-transition:enter-end="opacity-100 scale-100"
                     x-transition:leave="transition ease-in duration-200"
                     x-transition:leave-start="opacity-100 scale-100"
                     x-transition:leave-end="opacity-0 scale-95"
                     class="flex flex-col items-center text-center">
                    <x-filament::icon icon="heroicon-o-users" class="mb-4 h-16 w-16 text-primary-500" />
                    <h2 class="mb-5 text-2xl font-bold text-gray-900 dark:text-white">Enroll Mentees</h2>
                    <ul class="w-full space-y-3 text-left text-base text-gray-700 dark:text-gray-300">
                        <li class="flex gap-2"><span class="mt-0.5 shrink-0 font-bold text-primary-500">✔</span>Search for existing staff members and add them to the class</li>
                        <li class="flex gap-2"><span class="mt-0.5 shrink-0 font-bold text-primary-500">✔</span>Or add a brand-new person by filling in their name, phone, and department</li>
                        <li class="flex gap-2"><span class="mt-0.5 shrink-0 font-bold text-primary-500">✔</span>You can also import a list of mentees all at once from a CSV file</li>
                    </ul>
                </div>

                {{-- Step 5: Start a Module --}}
                <div x-show="step === 4"
                     x-transition:enter="transition ease-out duration-300"
                     x-transition:enter-start="opacity-0 scale-95"
                     x-transition:enter-end="opacity-100 scale-100"
                     x-transition:leave="transition ease-in duration-200"
                     x-transition:leave-start="opacity-100 scale-100"
                     x-transition:leave-end="opacity-0 scale-95"
                     class="flex flex-col items-center text-center">
                    <x-filament::icon icon="heroicon-o-play-circle" class="mb-4 h-16 w-16 text-primary-500" />
                    <h2 class="mb-5 text-2xl font-bold text-gray-900 dark:text-white">Start a Module</h2>
                    <ul class="w-full space-y-3 text-left text-base text-gray-700 dark:text-gray-300">
                        <li class="flex gap-2"><span class="mt-0.5 shrink-0 font-bold text-primary-500">✔</span>When ready, click <strong>Start</strong> on a module to open attendance tracking</li>
                        <li class="flex gap-2"><span class="mt-0.5 shrink-0 font-bold text-primary-500">✔</span>Mentees receive a link they tap to confirm they attended the session</li>
                        <li class="flex gap-2"><span class="mt-0.5 shrink-0 font-bold text-primary-500">✔</span>You can see who has confirmed in real time from the module page</li>
                    </ul>
                </div>

                {{-- Step 6: Mentees Confirm Attendance --}}
                <div x-show="step === 5"
                     x-transition:enter="transition ease-out duration-300"
                     x-transition:enter-start="opacity-0 scale-95"
                     x-transition:enter-end="opacity-100 scale-100"
                     x-transition:leave="transition ease-in duration-200"
                     x-transition:leave-start="opacity-100 scale-100"
                     x-transition:leave-end="opacity-0 scale-95"
                     class="flex flex-col items-center text-center">
                    <x-filament::icon icon="heroicon-o-check-badge" class="mb-4 h-16 w-16 text-primary-500" />
                    <h2 class="mb-5 text-2xl font-bold text-gray-900 dark:text-white">Mentees Confirm Attendance</h2>
                    <ul class="w-full space-y-3 text-left text-base text-gray-700 dark:text-gray-300">
                        <li class="flex gap-2"><span class="mt-0.5 shrink-0 font-bold text-primary-500">✔</span>Each mentee taps their personal attendance link on their phone</li>
                        <li class="flex gap-2"><span class="mt-0.5 shrink-0 font-bold text-primary-500">✔</span>Their attendance is recorded and their progress updates automatically</li>
                        <li class="flex gap-2"><span class="mt-0.5 shrink-0 font-bold text-primary-500">✔</span>If a mentee cannot tap the link, you can mark them as attended manually</li>
                    </ul>
                </div>

                {{-- Step 7: Complete & Close --}}
                <div x-show="step === 6"
                     x-transition:enter="transition ease-out duration-300"
                     x-transition:enter-start="opacity-0 scale-95"
                     x-transition:enter-end="opacity-100 scale-100"
                     x-transition:leave="transition ease-in duration-200"
                     x-transition:leave-start="opacity-100 scale-100"
                     x-transition:leave-end="opacity-0 scale-95"
                     class="flex flex-col items-center text-center">
                    <x-filament::icon icon="heroicon-o-trophy" class="mb-4 h-16 w-16 text-primary-500" />
                    <h2 class="mb-5 text-2xl font-bold text-gray-900 dark:text-white">Complete &amp; Close</h2>
                    <ul class="w-full space-y-3 text-left text-base text-gray-700 dark:text-gray-300">
                        <li class="flex gap-2"><span class="mt-0.5 shrink-0 font-bold text-primary-500">✔</span>Mark each module as <strong>Complete</strong> once all its sessions are done</li>
                        <li class="flex gap-2"><span class="mt-0.5 shrink-0 font-bold text-primary-500">✔</span>Then end the class — all scores and attendance records are saved</li>
                        <li class="flex gap-2"><span class="mt-0.5 shrink-0 font-bold text-primary-500">✔</span>Run a full class report to share results with your supervisor</li>
                    </ul>
                </div>

            </div>

            {{-- Navigation footer --}}
            <div class="guide-modal-footer">
                <div>
                    <button
                        type="button"
                        x-show="step > 0"
                        @click="prev()"
                        class="guide-btn-back"
                    >
                        ← Back
                    </button>
                </div>
                <div>
                    <button
                        type="button"
                        x-show="step < 6"
                        @click="next()"
                        class="guide-btn-next"
                    >
                        Next Step →
                    </button>
                    <a
                        x-show="step === 6"
                        href="/admin/mentorship/create"
                        class="guide-btn-create"
                    >
                        ✚ Create My First Mentorship
                    </a>
                </div>
            </div>
        </div>
    </div>

</div>
