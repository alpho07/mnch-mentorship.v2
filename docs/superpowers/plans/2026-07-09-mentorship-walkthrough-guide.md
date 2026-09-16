# Mentorship Walkthrough Guide Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a 7-step animated walkthrough guide modal to the Mentorship list page, with a first-visit auto-prompt banner and a persistent blinking trigger button.

**Architecture:** Pure Alpine.js component added to the existing `mentorship-guidance-notice.blade.php`. All state (open/closed, current step, prompt dismissed) lives in Alpine `x-data`. `localStorage` tracks first-visit. No PHP changes, no Livewire, no new files created.

**Tech Stack:** Alpine.js (already loaded by Filament), Tailwind CSS utility classes, inline `<style>` block for the pulse keyframe, Filament `<x-filament::icon>` and `<x-filament::section>` components.

## Global Constraints

- One file only: `resources/views/filament/widgets/mentorship-guidance-notice.blade.php`
- No PHP changes, no new Filament resources, widgets, or Livewire components
- Dark mode must work — use `dark:` Tailwind variants throughout
- All body text must be `text-base` or larger (elderly readability)
- `localStorage` key: `mnch_guide_prompted` (string `"1"` when set)
- "Create My First Mentorship" links to `/admin/mentorship/create`
- Primary teal colour: `#0097A7` — use `primary-500` / `primary-600` Tailwind classes

---

### Task 1: Alpine scaffold + auto-prompt banner

**Files:**
- Modify: `resources/views/filament/widgets/mentorship-guidance-notice.blade.php`

**What this task produces:** The entire blade is replaced with an Alpine `x-data` wrapper. The auto-prompt banner slides in on first visit (localStorage absent) and is dismissed permanently by either button. The existing guidance notice content is preserved exactly inside the Alpine wrapper.

- [ ] **Step 1: Replace the entire blade with the scaffolded version below**

Full replacement content for `resources/views/filament/widgets/mentorship-guidance-notice.blade.php`:

```blade
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
                @click="acceptGuide()"
                class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-primary-700 focus:outline-none"
            >
                Yes, show me
            </button>
            <button
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

            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                <a href="{{ url('/resources/infant-child-mentorship-manual') }}" target="_blank" rel="noopener noreferrer"
                   class="rounded-lg border border-gray-200 bg-white p-4 text-sm transition hover:border-primary-300 hover:bg-primary-50 dark:border-gray-700 dark:bg-gray-900 dark:hover:border-primary-600 dark:hover:bg-gray-800">
                    <div class="font-semibold text-gray-950 dark:text-white">Infant and Child Mentorship Manual</div>
                    <div class="mt-1 text-gray-600 dark:text-gray-400">Use this to plan content and session flow.</div>
                </a>
                <a href="https://mnchkenyamentorship.org/resources/newborn-mentorship-mentors-manual" target="_blank" rel="noopener noreferrer"
                   class="rounded-lg border border-gray-200 bg-white p-4 text-sm transition hover:border-primary-300 hover:bg-primary-50 dark:border-gray-700 dark:bg-gray-900 dark:hover:border-primary-600 dark:hover:bg-gray-800">
                    <div class="font-semibold text-gray-950 dark:text-white">Newborn Mentorship Mentor's Manual</div>
                    <div class="mt-1 text-gray-600 dark:text-gray-400">Use this when the mentorship focus includes newborn care.</div>
                </a>
                <a href="{{ route('resources.search', ['q' => 'mentorship manual']) }}" target="_blank" rel="noopener noreferrer"
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

</div>
```

- [ ] **Step 2: Verify auto-prompt banner in browser**

Open `/admin/mentorship`. Expected:
- Teal banner appears above the guidance notice with "New here? Want a quick walkthrough..."
- Clicking "No thanks" hides the banner; refreshing the page does NOT show it again
- Open DevTools → Application → Local Storage → delete `mnch_guide_prompted` → refresh → banner appears again
- Clicking "Yes, show me" hides the banner and sets localStorage (modal does not open yet — that comes in Task 3)

- [ ] **Step 3: Commit**

```bash
git add resources/views/filament/widgets/mentorship-guidance-notice.blade.php
git commit -m "feat: add mentorship guide Alpine scaffold and auto-prompt banner"
```

---

### Task 2: Pulse keyframe style + blinking trigger button

**Files:**
- Modify: `resources/views/filament/widgets/mentorship-guidance-notice.blade.php`

**What this task produces:** A `<style>` block with the pulse-glow keyframe, and a "How does this work?" button in the guidance notice header row that pulses permanently and opens the guide modal on click.

- [ ] **Step 1: Add the `<style>` block at the very top of the blade (before the `<div x-data>` opening tag)**

Insert this as the first line of the file:

```blade
<style>
@keyframes guide-pulse {
    0%,100% { box-shadow: 0 0 0 0 rgba(0,151,167,0.55); }
    50%      { box-shadow: 0 0 0 10px rgba(0,151,167,0); }
}
.guide-pulse-btn { animation: guide-pulse 2s ease-in-out infinite; }
</style>
```

- [ ] **Step 2: Replace the guidance notice header row to add the button**

Find this block inside the `<x-filament::section>`:

```blade
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
```

Replace it with:

```blade
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
                    @click="openGuide()"
                    class="guide-pulse-btn shrink-0 flex items-center gap-2 rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-md hover:bg-primary-700 focus:outline-none"
                >
                    <x-filament::icon icon="heroicon-o-play-circle" class="h-5 w-5 text-white" />
                    How does this work?
                </button>
            </div>
```

- [ ] **Step 3: Verify in browser**

Open `/admin/mentorship`. Expected:
- A teal "How does this work?" button with a play icon appears top-right of the guidance notice
- Button has a visible slow pulsing glow effect
- Clicking the button does nothing visible yet (modal comes in Task 3) — check browser console shows no errors

- [ ] **Step 4: Commit**

```bash
git add resources/views/filament/widgets/mentorship-guidance-notice.blade.php
git commit -m "feat: add blinking guide trigger button with pulse-glow animation"
```

---

### Task 3: Guide modal shell (backdrop, header, progress bar, navigation)

**Files:**
- Modify: `resources/views/filament/widgets/mentorship-guidance-notice.blade.php`

**What this task produces:** A fully working modal — opens/closes, shows step counter, animated progress bar, Previous/Next/Close navigation, and "Create My First Mentorship" on step 7. Step card content area is a placeholder for now (filled in Task 4).

- [ ] **Step 1: Add the modal just before the closing `</div>` of the root Alpine `x-data` wrapper**

Find the last line `</div>` that closes the root `<div x-data="...">` and insert this entire block immediately before it:

```blade
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
        class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50 p-4"
    >
        <div
            @click.stop
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 scale-95 translate-y-4"
            x-transition:enter-end="opacity-100 scale-100 translate-y-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 scale-100 translate-y-0"
            x-transition:leave-end="opacity-0 scale-95 translate-y-4"
            class="relative w-full max-w-xl rounded-2xl bg-white shadow-2xl dark:bg-gray-900"
        >
            {{-- Modal top bar: step counter + progress bar + close --}}
            <div class="flex items-center gap-3 border-b border-gray-200 px-6 py-4 dark:border-gray-700">
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
                    @click="close()"
                    class="shrink-0 rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-300"
                    aria-label="Close guide"
                >
                    <x-filament::icon icon="heroicon-o-x-mark" class="h-5 w-5" />
                </button>
            </div>

            {{-- Step cards content area (filled in Task 4) --}}
            <div class="min-h-[300px] px-8 py-7">
                <p class="text-center text-base text-gray-500 dark:text-gray-400">
                    Step <span x-text="step + 1"></span> content coming soon…
                </p>
            </div>

            {{-- Navigation footer --}}
            <div class="flex items-center justify-between border-t border-gray-200 px-6 py-4 dark:border-gray-700">
                <div>
                    <button
                        x-show="step > 0"
                        @click="prev()"
                        class="flex items-center gap-1.5 rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800"
                    >
                        ← Back
                    </button>
                </div>
                <div>
                    <button
                        x-show="step < 6"
                        @click="next()"
                        class="flex items-center gap-1.5 rounded-lg bg-primary-600 px-5 py-2 text-sm font-semibold text-white hover:bg-primary-700 focus:outline-none"
                    >
                        Next Step →
                    </button>
                    <a
                        x-show="step === 6"
                        href="/admin/mentorship/create"
                        class="flex items-center gap-1.5 rounded-lg bg-green-600 px-5 py-2 text-sm font-semibold text-white hover:bg-green-700"
                    >
                        ✚ Create My First Mentorship
                    </a>
                </div>
            </div>
        </div>
    </div>
```

- [ ] **Step 2: Verify modal shell in browser**

Open `/admin/mentorship`. Expected:
- Clicking "How does this work?" opens a centred modal with dark backdrop
- Top bar shows "Step 1 of 7", a thin teal progress bar filling ~14%, and an ✕ button
- "Next Step →" advances the counter and progress bar fills further
- At step 7, "Next Step →" is replaced by "✚ Create My First Mentorship" (green)
- "← Back" appears from step 2 onward
- Clicking ✕ or the dark backdrop closes the modal
- The "Yes, show me" banner button now also opens the modal

- [ ] **Step 3: Commit**

```bash
git add resources/views/filament/widgets/mentorship-guidance-notice.blade.php
git commit -m "feat: add guide modal shell with progress bar and navigation"
```

---

### Task 4: All 7 step cards with icons, bullets, and fade-scale animation

**Files:**
- Modify: `resources/views/filament/widgets/mentorship-guidance-notice.blade.php`

**What this task produces:** Replace the placeholder content area with all 7 step cards. Each card fades and scales in/out when the step changes. Each card has a large teal heroicon, a bold title, and 3 bullet points.

- [ ] **Step 1: Replace the placeholder content area with all 7 step cards**

Find this block inside the modal:

```blade
            {{-- Step cards content area (filled in Task 4) --}}
            <div class="min-h-[300px] px-8 py-7">
                <p class="text-center text-base text-gray-500 dark:text-gray-400">
                    Step <span x-text="step + 1"></span> content coming soon…
                </p>
            </div>
```

Replace it with:

```blade
            {{-- Step cards --}}
            <div class="relative min-h-[300px] px-8 py-7">

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
```

- [ ] **Step 2: Verify the complete guide in browser**

Open `/admin/mentorship`, click "How does this work?". Expected:
- Step 1 shows academic-cap icon, "Create a Mentorship" title, 3 teal ✔ bullets
- Clicking "Next Step →" fades/scales to Step 2 with user-group icon
- Progress bar fills smoothly with each step
- Step 1 has no "← Back" button; Step 2 onward has it
- Clicking "← Back" returns to the previous step
- Step 7 shows trophy icon and "✚ Create My First Mentorship" green button instead of "Next Step →"
- "✚ Create My First Mentorship" navigates to `/admin/mentorship/create`
- All steps readable in dark mode
- ✕ and backdrop click close the modal at any step

- [ ] **Step 3: Commit**

```bash
git add resources/views/filament/widgets/mentorship-guidance-notice.blade.php
git commit -m "feat: add all 7 guide step cards with fade-scale animation"
```
