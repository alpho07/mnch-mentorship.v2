# Mentorship Sections Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the single "Upcoming Mentorships" homepage section with three date-bucketed sections (Ongoing / Upcoming / Closed), add a daily auto-close command, and surface human-readable duration on every card.

**Architecture:** Date-based bucketing in the controller (not relying on stored `status`) feeds three Blade sections; a scheduled Artisan command keeps the DB `status` column accurate for the admin panel. No migrations needed — the `status` column and `start_date`/`end_date` casts already exist on `Training`.

**Tech Stack:** Laravel 12 · Blade · Tailwind CDN · Carbon (already cast on Training dates)

---

## Task 1: Add `getDurationLabelAttribute` to Training model

**Files:**
- Modify: `app/Models/Training.php` — add accessor after existing `getDurationDaysAttribute` (~line 504)

- [ ] **Step 1: Open `app/Models/Training.php` and locate `getDurationDaysAttribute`** (around line 498). Confirm it returns `$this->start_date->diffInDays($this->end_date) + 1`.

- [ ] **Step 2: Add `getDurationLabelAttribute` immediately after `getDurationDaysAttribute`**

  Insert this block after the closing `}` of `getDurationDaysAttribute` (after line 504):

  ```php
  public function getDurationLabelAttribute(): ?string
  {
      $days = $this->duration_days; // reuses existing accessor
      if ($days <= 0) {
          return null;
      }
      if ($days < 7) {
          return $days . ($days === 1 ? ' day' : ' days');
      }
      if ($days < 14) {
          return '1 week';
      }
      if ($days < 28) {
          $weeks = (int) ceil($days / 7);
          return $weeks . ' weeks';
      }
      if ($days < 60) {
          return '1 month';
      }
      if ($days < 365) {
          $months = (int) floor($days / 30);
          return $months . ($months === 1 ? ' month' : ' months');
      }
      $years = (int) floor($days / 365);
      return $years . ($years === 1 ? ' year' : ' years');
  }
  ```

- [ ] **Step 3: Verify with tinker**

  Run:
  ```bash
  php artisan tinker --execute="
  \$t = \App\Models\Training::whereNotNull('end_date')->whereNotNull('start_date')->first();
  echo \$t->duration_days . ' days → ' . \$t->duration_label . PHP_EOL;
  \$t2 = \App\Models\Training::where('id', 72)->first();
  echo \$t2->duration_days . ' days → ' . \$t2->duration_label . PHP_EOL;
  "
  ```

  Expected (training 72 runs Apr 15 – May 15 = 31 days):
  ```
  X days → X [unit]
  31 days → 1 month
  ```

- [ ] **Step 4: Commit**

  ```bash
  git add app/Models/Training.php
  git commit -m "feat: add getDurationLabelAttribute to Training model"
  ```

---

## Task 2: Create `AutoCloseMentorships` artisan command

**Files:**
- Create: `app/Console/Commands/AutoCloseMentorships.php`

- [ ] **Step 1: Create the command file**

  Create `app/Console/Commands/AutoCloseMentorships.php` with this content:

  ```php
  <?php

  namespace App\Console\Commands;

  use App\Models\Training;
  use Illuminate\Console\Command;

  class AutoCloseMentorships extends Command
  {
      protected $signature = 'mentorships:auto-close';
      protected $description = 'Mark facility mentorships whose end_date has passed as completed';

      public function handle(): int
      {
          $count = Training::where('type', 'facility_mentorship')
              ->whereNotNull('end_date')
              ->where('end_date', '<', now()->startOfDay())
              ->whereNotIn('status', ['completed', 'cancelled'])
              ->update(['status' => 'completed']);

          $this->info("Auto-closed {$count} mentorships.");

          return Command::SUCCESS;
      }
  }
  ```

- [ ] **Step 2: Run the command to verify it works and to backfill existing data**

  ```bash
  php artisan mentorships:auto-close
  ```

  Expected output (exact count will vary):
  ```
  Auto-closed 5 mentorships.
  ```

  Confirm in tinker that records were updated:
  ```bash
  php artisan tinker --execute="
  echo \App\Models\Training::where('type','facility_mentorship')
      ->where('status','completed')
      ->count() . ' completed mentorships in DB' . PHP_EOL;
  "
  ```

- [ ] **Step 3: Commit**

  ```bash
  git add app/Console/Commands/AutoCloseMentorships.php
  git commit -m "feat: add AutoCloseMentorships artisan command"
  ```

---

## Task 3: Register command on daily schedule

**Files:**
- Modify: `routes/console.php`

- [ ] **Step 1: Open `routes/console.php`** — currently only has the `inspire` command.

- [ ] **Step 2: Replace the entire file contents** with:

  ```php
  <?php

  use Illuminate\Foundation\Inspiring;
  use Illuminate\Support\Facades\Artisan;
  use Illuminate\Support\Facades\Schedule;

  Artisan::command('inspire', function () {
      $this->comment(Inspiring::quote());
  })->purpose('Display an inspiring quote');

  Schedule::command('mentorships:auto-close')->dailyAt('00:05');
  ```

  > `00:05` (5 minutes past midnight) avoids exact-midnight contention with other potential jobs.

- [ ] **Step 3: Verify the schedule is registered**

  ```bash
  php artisan schedule:list
  ```

  Expected: a row showing `mentorships:auto-close` with `Daily at 00:05`.

- [ ] **Step 4: Commit**

  ```bash
  git add routes/console.php
  git commit -m "feat: schedule mentorships:auto-close daily at midnight"
  ```

---

## Task 4: Update `ResourceController@home` with three queries

**Files:**
- Modify: `app/Http/Controllers/Frontend/ResourceController.php`

- [ ] **Step 1: Open the file and locate the single `$upcomingMentorships` query** (lines 82–88). It currently looks like:

  ```php
  $upcomingMentorships = Training::where('type', 'facility_mentorship')
      ->whereNotIn('status', ['cancelled', 'completed'])
      ->where(function ($q) {
          $q->where('end_date', '>=', now())
            ->orWhere(function ($q2) {
                $q2->whereNull('end_date')->where('start_date', '>=', now());
            });
      })
      ->with(['county', 'facility'])
      ->orderBy('start_date')
      ->limit(6)
      ->get();
  ```

- [ ] **Step 2: Replace that block with three queries**

  Replace the entire `$upcomingMentorships = Training::...->get();` block with:

  ```php
  $ongoingMentorships = Training::where('type', 'facility_mentorship')
      ->where('status', '!=', 'cancelled')
      ->where('start_date', '<=', now())
      ->where('end_date', '>=', now())
      ->with(['county', 'facility'])
      ->orderBy('end_date')
      ->limit(6)
      ->get();

  $upcomingMentorships = Training::where('type', 'facility_mentorship')
      ->where('status', '!=', 'cancelled')
      ->where('start_date', '>', now())
      ->with(['county', 'facility'])
      ->orderBy('start_date')
      ->limit(4)
      ->get();

  $closedMentorships = Training::where('type', 'facility_mentorship')
      ->where('status', '!=', 'cancelled')
      ->where('end_date', '<', now())
      ->where('end_date', '>=', now()->subDays(30))
      ->with(['county', 'facility'])
      ->orderBy('end_date', 'desc')
      ->limit(4)
      ->get();
  ```

- [ ] **Step 3: Update the `compact()` return call**

  Find:
  ```php
  return compact('featuredResources', 'recentResources', 'popularResources', 'categories', 'resourceTypes', 'upcomingTrainings', 'upcomingMentorships', 'trainingInsights');
  ```

  Replace with:
  ```php
  return compact('featuredResources', 'recentResources', 'popularResources', 'categories', 'resourceTypes', 'upcomingTrainings', 'ongoingMentorships', 'upcomingMentorships', 'closedMentorships', 'trainingInsights');
  ```

- [ ] **Step 4: Verify counts in tinker**

  ```bash
  php artisan tinker --execute="
  \$now = now();
  echo 'Ongoing: ' . \App\Models\Training::where('type','facility_mentorship')->where('status','!=','cancelled')->where('start_date','<='  ,\$now)->where('end_date','>='  ,\$now)->count() . PHP_EOL;
  echo 'Upcoming: ' . \App\Models\Training::where('type','facility_mentorship')->where('status','!=','cancelled')->where('start_date','>'   ,\$now)->count() . PHP_EOL;
  echo 'Closed (30d): ' . \App\Models\Training::where('type','facility_mentorship')->where('status','!=','cancelled')->where('end_date','<' ,\$now)->where('end_date','>='  ,\$now->subDays(30))->count() . PHP_EOL;
  "
  ```

  Expected: Ongoing > 0, values match what you see in the admin panel.

- [ ] **Step 5: Commit**

  ```bash
  git add app/Http/Controllers/Frontend/ResourceController.php
  git commit -m "feat: split mentorship homepage query into ongoing/upcoming/closed"
  ```

---

## Task 5: Replace single blade section with three sections

**Files:**
- Modify: `resources/views/frontend/home.blade.php`

- [ ] **Step 1: Locate the single mentorship section** — it starts at:

  ```blade
  {{-- ═══════════════════════════════════════════
       UPCOMING MENTORSHIPS  ← moved to top
  ═══════════════════════════════════════════ --}}
  @if(isset($upcomingMentorships) && $upcomingMentorships->count() > 0)
  ```

  and ends after the closing `@endif` (currently line ~228).

- [ ] **Step 2: Delete that entire block** (from the comment through `@endif`) and replace it with the three sections below:

  ```blade
  {{-- ═══════════════════════════════════════════
       ONGOING MENTORSHIPS
  ═══════════════════════════════════════════ --}}
  @if(isset($ongoingMentorships) && $ongoingMentorships->count() > 0)
  <section class="py-14" style="background: linear-gradient(135deg, #E0F7FA 0%, #F0FDFF 100%);">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div class="flex items-end justify-between mb-8">
              <div>
                  <div class="flex items-center gap-2 mb-1">
                      <span class="inline-block w-2 h-2 rounded-full bg-green-400" style="animation:pulse 2s cubic-bezier(.4,0,.6,1) infinite;box-shadow:0 0 6px #4ade80;"></span>
                      <span class="text-xs font-semibold text-primary-700 uppercase tracking-widest">Live Now · {{ $ongoingMentorships->count() }}</span>
                  </div>
                  <h2 class="text-2xl md:text-3xl font-extrabold text-gray-900">Ongoing Mentorships</h2>
                  <p class="text-gray-500 text-sm mt-1">Currently running at facilities across Kenya</p>
              </div>
              <a href="{{ url('analytics/dashboard') }}"
                 class="hidden md:inline-flex items-center gap-2 text-sm font-semibold text-primary-600 hover:text-primary-700 transition-colors">
                  View all <i class="fas fa-arrow-right text-xs"></i>
              </a>
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
              @foreach($ongoingMentorships as $mentorship)
              <div class="group bg-white rounded-2xl border border-primary-100 p-5 hover:border-primary-300 hover:shadow-lg transition-all duration-200 relative overflow-hidden">
                  <div class="absolute top-0 left-0 right-0 h-1 rounded-t-2xl" style="background: linear-gradient(90deg, #00838F, #0097A7);"></div>
                  <div class="flex items-start justify-between mb-3 mt-1">
                      <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0"
                           style="background: linear-gradient(135deg, #00838F 0%, #0097A7 100%);">
                          <i class="fas fa-user-md text-white text-xs"></i>
                      </div>
                      <div class="text-right text-xs text-gray-500 leading-tight">
                          @if($mentorship->end_date)
                          <div class="font-semibold text-gray-700">ends {{ $mentorship->end_date->format('M d, Y') }}</div>
                          @endif
                          @if($mentorship->duration_label)
                          <div class="text-primary-600 mt-0.5">⏱ {{ $mentorship->duration_label }}</div>
                          @endif
                      </div>
                  </div>
                  <h3 class="font-bold text-gray-900 text-sm leading-snug mb-3 group-hover:text-primary-700 transition-colors line-clamp-2">
                      {{ $mentorship->title }}
                  </h3>
                  <div class="space-y-1.5">
                      @if($mentorship->facility)
                      <div class="flex items-center gap-1.5 text-xs text-gray-500">
                          <i class="fas fa-hospital text-primary-400 w-3.5"></i>
                          <span class="truncate">{{ $mentorship->facility->name }}</span>
                      </div>
                      @elseif($mentorship->county)
                      <div class="flex items-center gap-1.5 text-xs text-gray-500">
                          <i class="fas fa-map-marker-alt text-primary-400 w-3.5"></i>
                          <span class="truncate">{{ $mentorship->county->name }}</span>
                      </div>
                      @endif
                      <div class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold"
                           style="background: #E0F7FA; color: #0097A7;">
                          <span class="w-1.5 h-1.5 rounded-full bg-green-400 inline-block"></span>
                          Ongoing
                      </div>
                  </div>
              </div>
              @endforeach
          </div>
      </div>
  </section>
  @endif

  {{-- ═══════════════════════════════════════════
       UPCOMING MENTORSHIPS
  ═══════════════════════════════════════════ --}}
  @if(isset($upcomingMentorships) && $upcomingMentorships->count() > 0)
  <section class="py-14" style="background: linear-gradient(135deg, #F0FDF4 0%, #DCFCE7 100%);">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div class="flex items-end justify-between mb-8">
              <div>
                  <div class="flex items-center gap-2 mb-1">
                      <i class="fas fa-calendar-alt text-xs" style="color:#15803d;"></i>
                      <span class="text-xs font-semibold uppercase tracking-widest" style="color:#15803d;">Coming Up · {{ $upcomingMentorships->count() }}</span>
                  </div>
                  <h2 class="text-2xl md:text-3xl font-extrabold text-gray-900">Upcoming Mentorships</h2>
                  <p class="text-gray-500 text-sm mt-1">Facility mentorships scheduled to begin soon</p>
              </div>
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
              @foreach($upcomingMentorships as $mentorship)
              <div class="group bg-white rounded-2xl border border-green-100 p-5 hover:border-green-300 hover:shadow-lg transition-all duration-200 relative overflow-hidden">
                  <div class="absolute top-0 left-0 right-0 h-1 rounded-t-2xl" style="background: linear-gradient(90deg, #15803d, #22c55e);"></div>
                  <div class="flex items-start justify-between mb-3 mt-1">
                      <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0"
                           style="background: linear-gradient(135deg, #15803d 0%, #22c55e 100%);">
                          <i class="fas fa-calendar-check text-white text-xs"></i>
                      </div>
                      <div class="text-right text-xs text-gray-500 leading-tight">
                          @if($mentorship->start_date)
                          <div class="font-semibold text-gray-700">starts {{ $mentorship->start_date->format('M d, Y') }}</div>
                          @endif
                          @if($mentorship->duration_label)
                          <div class="mt-0.5" style="color:#15803d;">⏱ {{ $mentorship->duration_label }}</div>
                          @endif
                      </div>
                  </div>
                  <h3 class="font-bold text-gray-900 text-sm leading-snug mb-3 group-hover:text-green-700 transition-colors line-clamp-2">
                      {{ $mentorship->title }}
                  </h3>
                  <div class="space-y-1.5">
                      @if($mentorship->facility)
                      <div class="flex items-center gap-1.5 text-xs text-gray-500">
                          <i class="fas fa-hospital text-green-400 w-3.5"></i>
                          <span class="truncate">{{ $mentorship->facility->name }}</span>
                      </div>
                      @elseif($mentorship->county)
                      <div class="flex items-center gap-1.5 text-xs text-gray-500">
                          <i class="fas fa-map-marker-alt text-green-400 w-3.5"></i>
                          <span class="truncate">{{ $mentorship->county->name }}</span>
                      </div>
                      @endif
                      <div class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold"
                           style="background: #DCFCE7; color: #15803d;">
                          Upcoming
                      </div>
                  </div>
              </div>
              @endforeach
          </div>
      </div>
  </section>
  @endif

  {{-- ═══════════════════════════════════════════
       RECENTLY CLOSED MENTORSHIPS
  ═══════════════════════════════════════════ --}}
  @if(isset($closedMentorships) && $closedMentorships->count() > 0)
  <section class="py-10" style="background: linear-gradient(135deg, #F9FAFB 0%, #F3F4F6 100%);">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div class="flex items-end justify-between mb-6">
              <div>
                  <div class="flex items-center gap-2 mb-1">
                      <i class="fas fa-check-circle text-xs text-gray-400"></i>
                      <span class="text-xs font-semibold text-gray-400 uppercase tracking-widest">Recently Closed · {{ $closedMentorships->count() }}</span>
                  </div>
                  <h2 class="text-xl md:text-2xl font-extrabold text-gray-500">Completed Mentorships</h2>
                  <p class="text-gray-400 text-sm mt-1">Programs that wrapped up in the last 30 days</p>
              </div>
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
              @foreach($closedMentorships as $mentorship)
              <div class="bg-white rounded-2xl border border-gray-100 p-5 opacity-75 relative overflow-hidden">
                  <div class="absolute top-0 left-0 right-0 h-1 rounded-t-2xl bg-gray-300"></div>
                  <div class="flex items-start justify-between mb-3 mt-1">
                      <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0 bg-gray-200">
                          <i class="fas fa-user-md text-gray-400 text-xs"></i>
                      </div>
                      <div class="text-right text-xs text-gray-400 leading-tight">
                          @if($mentorship->end_date)
                          <div class="font-semibold">ended {{ $mentorship->end_date->format('M d, Y') }}</div>
                          @endif
                          @if($mentorship->duration_label)
                          <div class="mt-0.5">⏱ {{ $mentorship->duration_label }}</div>
                          @endif
                      </div>
                  </div>
                  <h3 class="font-semibold text-gray-500 text-sm leading-snug mb-3 line-clamp-2">
                      {{ $mentorship->title }}
                  </h3>
                  <div class="space-y-1.5">
                      @if($mentorship->facility)
                      <div class="flex items-center gap-1.5 text-xs text-gray-400">
                          <i class="fas fa-hospital text-gray-300 w-3.5"></i>
                          <span class="truncate">{{ $mentorship->facility->name }}</span>
                      </div>
                      @elseif($mentorship->county)
                      <div class="flex items-center gap-1.5 text-xs text-gray-400">
                          <i class="fas fa-map-marker-alt text-gray-300 w-3.5"></i>
                          <span class="truncate">{{ $mentorship->county->name }}</span>
                      </div>
                      @endif
                      <div class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-400">
                          ✓ Closed
                      </div>
                  </div>
              </div>
              @endforeach
          </div>
      </div>
  </section>
  @endif
  ```

- [ ] **Step 3: Commit**

  ```bash
  git add resources/views/frontend/home.blade.php
  git commit -m "feat: split homepage into Ongoing / Upcoming / Closed mentorship sections"
  ```

---

## Task 6: Clear cache and verify visually

- [ ] **Step 1: Clear the homepage cache**

  ```bash
  php artisan cache:clear
  ```

- [ ] **Step 2: Open the homepage in a browser**

  Visit `http://localhost/MNCH-Master/public` (or wherever your local dev URL is).

  Verify:
  - **Ongoing** section appears first (teal), shows pulsing green dot, cards show "ends [date]" and duration chip
  - **Upcoming** section appears second (light green) — may be empty if no future-start mentorships exist in the DB
  - **Closed** section appears last (grey), cards are muted/opaque, show "ended [date]"
  - Duration chips show human-readable labels ("1 month", "6 weeks", etc.) — not raw numbers

- [ ] **Step 3: Confirm auto-close runs correctly by checking the schedule**

  ```bash
  php artisan schedule:run
  ```

  Expected output includes `Running [mentorships:auto-close]` with a zero count (since Task 2 already backfilled).

