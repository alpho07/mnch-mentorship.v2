# MNCHGPT Conversational Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Turn MNCHGPT into a pure conversational chat (no card/button UI) that greets the user, presents options as backend-authored numbered/lettered lists the LLM only frames in words, fuzzy-matches partial or slightly-off names, and resolves a bare number reply instantly without an LLM call.

**Architecture:** A new `FuzzyOptionMatcher` helper (wrapping `loilo/fuse`) becomes a third resolution tier under `MentorshipSetupToolProvider`/`MentorshipModulesToolProvider`'s existing exact/substring matching, turning "no match" into "here's a shortlist" instead of a dead end. A new `determineNextStep()` method on `MnchGptSetup` decides, after every turn, whether a numbered option list should accompany the next question — this is fed to the LLM as context (for framing only) and separately, deterministically rendered and appended to the bot's message. A `pendingOptions` property lets a bare number/letter reply resolve instantly, bypassing the LLM entirely.

**Tech Stack:** Laravel 12, Livewire, `loilo/fuse` (new dependency — v7.1.1+, PHP fuzzy-search library, PHP port of Fuse.js), PHPUnit.

## Global Constraints

- Scope is `MnchGptSetup` and its own files only. `HasMentorshipChatSlots` trait's `mount()` must not change. `ChatMentorshipSetup` and its 21-test suite (`tests/Feature/ChatMentorshipSetupTest.php`) must remain green and untouched.
- `chat-modules-turn.blade.php`, `chat-emonc-modules-turn.blade.php`, `chat-mentees-turn.blade.php`, `chat-mentorship-setup.blade.php`, `chat-turn.blade.php`, and `mnchgpt-transcript.blade.php` are not modified by this plan.
- Fuzzy match threshold: Fuse's default, `0.6` (`0` = perfect match, `1` = complete mismatch). Candidate shortlists capped at 8, best-first.
- Small-enum proactive list threshold: `MAX_PROACTIVE_OPTIONS = 10`.
- Letter selection maps case-insensitively: `A`→1, `B`→2, etc.
- Every task follows strict TDD: failing test → confirm fails → minimal implementation → confirm passes → Pint → commit.

---

### Task 1: `FuzzyOptionMatcher` helper + `loilo/fuse` dependency

**Files:**
- Modify: `composer.json` (via `composer require loilo/fuse`)
- Create: `app/Services/Chat/FuzzyOptionMatcher.php`
- Test: `tests/Unit/FuzzyOptionMatcherTest.php`

**Interfaces:**
- Produces: `FuzzyOptionMatcher::search(array $options, string $query): array` — `$options` is `[id => label]` (id can be `int` or `string`), returns a list of `['id' => mixed, 'label' => string]`, best-first, capped at 8, empty array if nothing scores within Fuse's default threshold or `$query`/`$options` is empty. This is the interface Task 2 and Task 3 both consume.

- [ ] **Step 1: Add the dependency**

```bash
composer require loilo/fuse
```

- [ ] **Step 2: Verify it installed**

Run: `composer show loilo/fuse`
Expected: shows a version `^7.x` install.

- [ ] **Step 3: Write the failing test**

```php
<?php

namespace Tests\Unit;

use App\Services\Chat\FuzzyOptionMatcher;
use Tests\TestCase;

class FuzzyOptionMatcherTest extends TestCase
{
    public function test_exact_query_returns_that_option_first(): void
    {
        $options = [
            1 => 'Chuka County Referral Hospital',
            2 => 'Chuka Sub-District Hospital',
            3 => 'Kisumu District Hospital',
        ];

        $results = FuzzyOptionMatcher::search($options, 'Chuka County Referral Hospital');

        $this->assertSame(1, $results[0]['id']);
    }

    public function test_a_typo_still_finds_the_intended_option(): void
    {
        $options = [
            1 => 'Chuka County Referral Hospital',
            2 => 'Kisumu District Hospital',
        ];

        // Missing the second "u" — a real typo, not an exact or substring match.
        $results = FuzzyOptionMatcher::search($options, 'Chuka Refferal Hospital');

        $this->assertContains(1, array_column($results, 'id'));
    }

    public function test_a_partial_name_matching_multiple_options_returns_all_of_them(): void
    {
        $options = [
            1 => 'Chuka County Referral Hospital',
            2 => 'Chuka Sub-District Hospital',
            3 => 'Kisumu District Hospital',
        ];

        $results = FuzzyOptionMatcher::search($options, 'Chuka');

        $ids = array_column($results, 'id');
        $this->assertContains(1, $ids);
        $this->assertContains(2, $ids);
        $this->assertNotContains(3, $ids);
    }

    public function test_completely_unrelated_input_returns_nothing(): void
    {
        $options = [
            1 => 'Chuka County Referral Hospital',
            2 => 'Kisumu District Hospital',
        ];

        $results = FuzzyOptionMatcher::search($options, 'zzzzzzzzzz nonsense');

        $this->assertSame([], $results);
    }

    public function test_results_are_capped_at_eight(): void
    {
        $options = [];
        for ($i = 1; $i <= 20; $i++) {
            $options[$i] = "Chuka Hospital Branch {$i}";
        }

        $results = FuzzyOptionMatcher::search($options, 'Chuka Hospital');

        $this->assertLessThanOrEqual(8, count($results));
    }

    public function test_empty_query_returns_nothing(): void
    {
        $options = [1 => 'Chuka County Referral Hospital'];

        $this->assertSame([], FuzzyOptionMatcher::search($options, ''));
        $this->assertSame([], FuzzyOptionMatcher::search($options, '   '));
    }

    public function test_empty_options_returns_nothing(): void
    {
        $this->assertSame([], FuzzyOptionMatcher::search([], 'Chuka'));
    }
}
```

- [ ] **Step 4: Run test to verify it fails**

Run: `php artisan test --filter=FuzzyOptionMatcherTest`
Expected: FAIL — `Class "App\Services\Chat\FuzzyOptionMatcher" not found`.

- [ ] **Step 5: Write the implementation**

```php
<?php

namespace App\Services\Chat;

/**
 * Fuzzy shortlist for CARDS slot options, backed by loilo/fuse (a PHP port
 * of Fuse.js). Used as the last resolution tier in
 * MentorshipSetupToolProvider/MentorshipModulesToolProvider, once exact and
 * substring matching have already failed — never picks a winner itself,
 * only ever returns a ranked shortlist for the caller to present.
 */
class FuzzyOptionMatcher
{
    public const MAX_CANDIDATES = 8;

    /**
     * @param  array<int|string, string>  $options  id => label
     * @return array<int, array{id: int|string, label: string}> best match first, capped at MAX_CANDIDATES, empty if nothing scores within Fuse's default threshold (0.6)
     */
    public static function search(array $options, string $query): array
    {
        if (trim($query) === '' || empty($options)) {
            return [];
        }

        $ids = array_keys($options);
        $labels = array_values($options);

        // ignoreLocation: facility labels carry a variable-length MFL code
        // prefix ("MFL012 — Name"), so the real name doesn't start at a
        // fixed position — without this, Fuse's default location-scoring
        // would unfairly penalize matches later in the string.
        $fuse = new \Fuse\Fuse($labels, ['ignoreLocation' => true]);

        return collect($fuse->search($query))
            ->take(self::MAX_CANDIDATES)
            ->map(fn (array $result) => [
                'id' => $ids[$result['refIndex']],
                'label' => $labels[$result['refIndex']],
            ])
            ->values()
            ->all();
    }
}
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --filter=FuzzyOptionMatcherTest`
Expected: PASS (7 tests).

- [ ] **Step 7: Pint + commit**

```bash
./vendor/bin/pint app/Services/Chat/FuzzyOptionMatcher.php tests/Unit/FuzzyOptionMatcherTest.php
git add composer.json composer.lock app/Services/Chat/FuzzyOptionMatcher.php tests/Unit/FuzzyOptionMatcherTest.php
git commit -m "feat: add FuzzyOptionMatcher backed by loilo/fuse"
```

---

### Task 2: Wire fuzzy matching into `MentorshipSetupToolProvider`

**Files:**
- Modify: `app/Services/Chat/Tools/MentorshipSetupToolProvider.php`
- Test: `tests/Unit/MentorshipSetupToolProviderTest.php`

**Interfaces:**
- Consumes: `FuzzyOptionMatcher::search(array $options, string $query): array` (Task 1).
- Produces: `resolveValue()` now returns `array{status: 'resolved'|'ambiguous'|'unresolved', value?: mixed, candidates?: array}` instead of a bare scalar/sentinel. `tool()`'s `execute()` result gains a third key, `candidates`: `array<string, array<int, array{id: mixed, label: string}>>` (slot id => shortlist), alongside the existing `filled`/`rejected`. Tasks 4/5 in `MnchGptSetup` consume this `candidates` key.

- [ ] **Step 1: Update the existing ambiguous-match test to expect a shortlist, not a rejection**

This test currently asserts `"Chuka"` (which matches two facility labels via the existing substring tier) gets rejected outright. That was correct when there was no third tier to fall through to — now it should produce a candidate shortlist instead. Replace it in `tests/Unit/MentorshipSetupToolProviderTest.php`:

```php
    public function test_execute_returns_a_candidate_shortlist_for_an_ambiguous_partial_match(): void
    {
        $user = $this->actingAsCoordinator();
        $county = County::factory()->create(['name' => 'Tharaka Nithi']);
        $subcounty = Subcounty::create(['name' => 'Chuka', 'county_id' => $county->id]);
        Facility::factory()->create(['subcounty_id' => $subcounty->id, 'name' => 'Chuka County Referral Hospital']);
        Facility::factory()->create(['subcounty_id' => $subcounty->id, 'name' => 'Chuka Sub-District Hospital']);
        $page = new ChatMentorshipSetup;
        $page->mount();

        MentorshipSetupToolProvider::tool($page)->execute(['county_id' => (string) $county->id], $user);

        // "Chuka" alone matches both facilities' labels — instead of a dead
        // end, this now surfaces both as a shortlist for the user to pick
        // from (see FuzzyOptionMatcher).
        $result = MentorshipSetupToolProvider::tool($page)->execute(['facility_id' => 'Chuka'], $user);

        $this->assertArrayNotHasKey('facility_id', $page->answers);
        $this->assertArrayHasKey('facility_id', $result['candidates']);
        $labels = array_column($result['candidates']['facility_id'], 'label');
        $this->assertContains('Chuka County Referral Hospital', $labels);
        $this->assertContains('Chuka Sub-District Hospital', $labels);
    }
```

Also add one new test proving fuzzy resolution alone (no substring match at all — a genuine typo) still produces a usable shortlist rather than a flat rejection:

```php
    public function test_execute_returns_a_shortlist_for_a_near_miss_typo(): void
    {
        $user = $this->actingAsCoordinator();
        $county = County::factory()->create(['id' => 56427, 'name' => 'Tharaka Nithi']);
        $page = new ChatMentorshipSetup;
        $page->mount();

        // "Tharaka Nith" (missing the final "i") doesn't exact-match or
        // substring-contain the real name the other way around, but is a
        // clear typo Fuse should still catch.
        $result = MentorshipSetupToolProvider::tool($page)->execute(['county_id' => 'Tharaka Nith'], $user);

        $this->assertArrayNotHasKey('county_id', $page->answers);
        $this->assertArrayHasKey('county_id', $result['candidates']);
        $this->assertSame($county->id, $result['candidates']['county_id'][0]['id']);
    }
```

Update the existing `test_execute_rejects_a_value_that_matches_no_option_instead_of_guessing` test — verify `'Nairobi'` against only `'Tharaka Nithi'` still doesn't fuzzy-match (two genuinely dissimilar county names) by asserting the `candidates` key is absent for that slot too:

```php
    public function test_execute_rejects_a_value_that_matches_no_option_instead_of_guessing(): void
    {
        $user = $this->actingAsCoordinator();
        County::factory()->create(['name' => 'Tharaka Nithi']);
        $page = new ChatMentorshipSetup;
        $page->mount();

        $tool = MentorshipSetupToolProvider::tool($page);
        $result = $tool->execute(['county_id' => 'Nairobi'], $user);

        $this->assertContains('county_id', $result['rejected']);
        $this->assertArrayNotHasKey('county_id', $result['candidates']);
        $this->assertArrayNotHasKey('county_id', $page->answers);
    }
```

- [ ] **Step 2: Run tests to verify the new/changed ones fail**

Run: `php artisan test --filter=MentorshipSetupToolProviderTest`
Expected: the two new tests FAIL (`Undefined array key "candidates"`), and `test_execute_returns_a_candidate_shortlist_for_an_ambiguous_partial_match` fails the same way (old version) or the new assertions fail since `candidates` doesn't exist yet.

- [ ] **Step 3: Rewrite `resolveValue()` and the `execute()` closure**

Replace the whole file's `execute()` closure and `resolveValue()` method:

```php
            execute: function (array $args, User $user) use ($page) {
                $filled = [];
                $rejected = [];
                $candidates = [];

                // module_ids/selected_users aren't generic Slot objects, so
                // nextUnfilledSlot() skips straight past the modules/
                // enroll_mentees stages to 'recipients' — answering it early
                // fires sendInvitations() immediately, completing the class
                // with nothing in it. This mirrors the same guard schemaFor()
                // applies, checked again here in case a value for a
                // not-currently-offered slot arrives anyway.
                if ($page->activeStage() !== 'slot') {
                    return ['filled' => [], 'rejected' => array_keys($args), 'candidates' => []];
                }

                foreach ($args as $slotId => $value) {
                    $slot = collect($page->slots())->firstWhere('id', $slotId);

                    if (! $slot) {
                        continue;
                    }

                    if (array_key_exists($slotId, $page->answers)) {
                        continue;
                    }

                    $resolution = self::resolveValue($slot, $value, $page->answers);

                    if ($resolution['status'] === 'ambiguous') {
                        $candidates[$slotId] = $resolution['candidates'];

                        continue;
                    }

                    if ($resolution['status'] === 'unresolved') {
                        $rejected[] = $slotId;

                        continue;
                    }

                    $before = $page->answers;
                    $page->answer($slotId, $resolution['value']);

                    if (array_key_exists($slotId, $page->answers) && $page->answers !== $before) {
                        $filled[] = $slotId;
                    } else {
                        $rejected[] = $slotId;
                    }
                }

                return ['filled' => $filled, 'rejected' => $rejected, 'candidates' => $candidates];
            },
```

```php
    /**
     * Resolves a CARDS slot's model-supplied value against its real
     * options, in three tiers — exact match, unique substring match, then
     * a fuzzy shortlist (FuzzyOptionMatcher) — returning one of:
     * - ['status' => 'resolved', 'value' => $id]
     * - ['status' => 'ambiguous', 'candidates' => [['id' => ..., 'label' => ...], ...]]
     * - ['status' => 'unresolved']
     * Non-CARDS (free-text) slots always resolve immediately. Fuzzy never
     * auto-picks a winner, even a clearly-best one — ambiguous always means
     * "show the shortlist", never "guess".
     */
    private static function resolveValue($slot, mixed $value, array $answers): array
    {
        if ($slot->renderKind() !== Render::CARDS) {
            return ['status' => 'resolved', 'value' => $value];
        }

        $options = $slot->getOptions($answers);
        $needle = trim((string) $value);

        foreach ($options as $id => $label) {
            if ((string) $id === (string) $value || strcasecmp((string) $label, $needle) === 0) {
                return ['status' => 'resolved', 'value' => $id];
            }
        }

        if ($needle !== '') {
            $partial = collect($options)->filter(
                fn ($label) => stripos((string) $label, $needle) !== false
            );

            if ($partial->count() === 1) {
                return ['status' => 'resolved', 'value' => $partial->keys()->first()];
            }
        }

        if ($needle !== '') {
            $candidates = FuzzyOptionMatcher::search($options, $needle);

            if (! empty($candidates)) {
                return ['status' => 'ambiguous', 'candidates' => $candidates];
            }
        }

        return ['status' => 'unresolved'];
    }
```

Add the import at the top of the file:

```php
use App\Services\Chat\FuzzyOptionMatcher;
```

(Remove the now-unused `self::UNRESOLVED` constant and its doc comment — replaced by the `status` discriminator.)

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=MentorshipSetupToolProviderTest`
Expected: PASS (all tests, including the two new/changed ones).

- [ ] **Step 5: Run the full MNCHGPT-adjacent suite to check for fallout**

Run: `php artisan test --filter="MnchGpt|ChatMentorshipSetup|LlmMentorshipAssistant"`
Expected: PASS. (`ChatMentorshipSetupTest` doesn't use this provider at all, so it's unaffected; `MnchGptSetupTest`/`MnchGptEndToEndTest` don't currently assert on the `candidates` key, so they should be unaffected by its addition.)

- [ ] **Step 6: Pint + commit**

```bash
./vendor/bin/pint app/Services/Chat/Tools/MentorshipSetupToolProvider.php tests/Unit/MentorshipSetupToolProviderTest.php
git add app/Services/Chat/Tools/MentorshipSetupToolProvider.php tests/Unit/MentorshipSetupToolProviderTest.php
git commit -m "feat: add fuzzy-match shortlist tier to MentorshipSetupToolProvider"
```

---

### Task 3: Wire fuzzy matching into `MentorshipModulesToolProvider`

**Files:**
- Modify: `app/Services/Chat/Tools/MentorshipModulesToolProvider.php`
- Test: `tests/Unit/MentorshipModulesToolProviderTest.php`

**Interfaces:**
- Consumes: `FuzzyOptionMatcher::search()` (Task 1).
- Produces: unchanged public shape (`fill_mentorship_modules` still returns `submitted`/`unresolved`/`error`), but `unresolved` module names that DO fuzzy-match something now report their candidates too — the module-picker UI itself is unchanged (out of scope, per spec), this only makes the assistant's text able to say "did you mean X?" instead of a flat "not found."

This is a smaller, symmetric extension of Task 2's pattern — included for consistency (both tool providers share the same "never guess, offer a shortlist instead" rule) rather than leaving one resolver more capable than the other.

- [ ] **Step 1: Write the failing test**

Add to `tests/Unit/MentorshipModulesToolProviderTest.php`:

```php
    public function test_execute_suggests_close_matches_for_an_unresolved_name(): void
    {
        $user = $this->actingAsCoordinator();
        $program = Program::factory()->create(['name' => 'Newborn Care', 'is_active' => true]);
        $facility = Facility::factory()->create();
        ProgramModule::factory()->create(['program_id' => $program->id, 'is_active' => true, 'name' => 'Neonatal Resuscitation']);
        $page = new ChatMentorshipSetup;
        $page->mount();
        $this->advanceToModulesStage($page, $program, $facility);

        $tool = MentorshipModulesToolProvider::tool($page);
        // "Resusitation" — a real, plausible typo.
        $result = $tool->execute(['module_names' => ['Neonatal Resusitation']], $user);

        $this->assertArrayNotHasKey('submitted', $result);
        $this->assertArrayHasKey('suggestions', $result);
        $labels = array_column($result['suggestions']['Neonatal Resusitation'], 'label');
        $this->assertContains('Neonatal Resuscitation', $labels);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=MentorshipModulesToolProviderTest`
Expected: FAIL — `Undefined array key "suggestions"`.

- [ ] **Step 3: Update the implementation**

In `app/Services/Chat/Tools/MentorshipModulesToolProvider.php`, add the import:

```php
use App\Services\Chat\FuzzyOptionMatcher;
```

Replace the `execute` closure's unresolved-name handling and the `resolveModuleId()` method:

```php
            execute: function (array $args, User $user) use ($page, $options) {
                if ($args['skip'] ?? false) {
                    $page->submitModules([]);

                    return ['submitted' => []];
                }

                $names = $args['module_names'] ?? [];

                if (empty($names)) {
                    return ['error' => 'No module names given.'];
                }

                $resolvedIds = [];
                $unresolved = [];
                $suggestions = [];

                foreach ($names as $name) {
                    $id = self::resolveModuleId($options, $name);

                    if ($id === null) {
                        $unresolved[] = $name;
                        $shortlist = FuzzyOptionMatcher::search($options, $name);

                        if (! empty($shortlist)) {
                            $suggestions[$name] = $shortlist;
                        }
                    } else {
                        $resolvedIds[] = $id;
                    }
                }

                // submitModules() is a one-shot "Continue" that finalizes
                // the whole module list for this class — submitting just
                // the names that resolved would silently truncate what the
                // user actually asked for, so nothing is assigned unless
                // every name resolved.
                if (! empty($unresolved)) {
                    return array_filter([
                        'unresolved' => $unresolved,
                        'suggestions' => $suggestions,
                    ]);
                }

                $page->submitModules($resolvedIds);

                return ['submitted' => $resolvedIds];
            },
```

`resolveModuleId()` itself is unchanged — it still only does exact + substring matching and returns `?int`; the fuzzy step is a separate, additional lookup used only to enrich the response when resolution fails, not to auto-resolve.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=MentorshipModulesToolProviderTest`
Expected: PASS (5 tests).

- [ ] **Step 5: Pint + commit**

```bash
./vendor/bin/pint app/Services/Chat/Tools/MentorshipModulesToolProvider.php tests/Unit/MentorshipModulesToolProviderTest.php
git add app/Services/Chat/Tools/MentorshipModulesToolProvider.php tests/Unit/MentorshipModulesToolProviderTest.php
git commit -m "feat: suggest fuzzy-matched module names when a name doesn't resolve"
```

---

### Task 4: `determineNextStep()` on `MnchGptSetup`

**Files:**
- Modify: `app/Filament/Resources/MentorshipResource/Pages/MnchGptSetup.php`
- Test: `tests/Unit/MnchGptSetupDetermineNextStepTest.php`

**Interfaces:**
- Consumes: `$page->slots()`, `$page->answers`, `$page->nextUnfilledSlot()` (all from `HasMentorshipChatSlots`, unchanged).
- Produces: `MnchGptSetup::determineNextStep(array $candidatesFromLastTurn = []): ?array` — returns `null` (no list to show) or `['slot' => string, 'options' => [1 => ['id' => mixed, 'label' => string], 2 => ...]]`. `$candidatesFromLastTurn` is the `candidates` key from a tool's `execute()` result (Task 2), shaped `[slotId => [['id'=>.., 'label'=>..], ...]]` — i.e. a *plain* list per slot, not yet numbered; `determineNextStep()` is what assigns the 1-based numbering. Tasks 5 and 6 both call this and consume its return shape.

This task builds `determineNextStep()` in isolation — a pure function of the page's current state plus one input — with no wiring into `sendMessage()` yet (that's Task 5).

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Unit;

use App\Filament\Resources\MentorshipResource\Pages\MnchGptSetup;
use App\Models\County;
use App\Models\Facility;
use App\Models\Subcounty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MnchGptSetupDetermineNextStepTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsCoordinator(): User
    {
        $user = User::factory()->create(['name' => 'Coordinator']);
        Permission::firstOrCreate(['name' => 'create_mentorship::training', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view_any_mentorship::training', 'guard_name' => 'web']);
        $user->givePermissionTo(['create_mentorship::training', 'view_any_mentorship::training']);
        $this->actingAs($user);

        return $user;
    }

    public function test_ambiguous_candidates_from_the_last_turn_take_priority(): void
    {
        $this->actingAsCoordinator();
        $page = new MnchGptSetup;
        $page->mount();

        $step = $page->determineNextStep([
            'county_id' => [
                ['id' => 1, 'label' => 'Tharaka Nithi'],
                ['id' => 2, 'label' => 'Tharaka North'],
            ],
        ]);

        $this->assertSame('county_id', $step['slot']);
        $this->assertSame('Tharaka Nithi', $step['options'][1]['label']);
        $this->assertSame('Tharaka North', $step['options'][2]['label']);
    }

    public function test_a_small_enum_next_slot_is_shown_proactively(): void
    {
        $this->actingAsCoordinator();
        $page = new MnchGptSetup;
        $page->mount();

        // Nothing filled yet — is_pilot (2 options) is next, well under the
        // proactive-list threshold.
        $step = $page->determineNextStep([]);

        $this->assertSame('is_pilot', $step['slot']);
        $this->assertCount(2, $step['options']);
    }

    public function test_a_large_enum_next_slot_is_not_shown_proactively(): void
    {
        $this->actingAsCoordinator();
        County::factory()->count(15)->create();
        $page = new MnchGptSetup;
        $page->mount();
        $page->answer('is_pilot', 0);

        // county_id has more than MAX_PROACTIVE_OPTIONS options — no list,
        // just the open question (handled by the normal reply, not here).
        $step = $page->determineNextStep([]);

        $this->assertNull($step);
    }

    public function test_no_list_once_past_the_generic_slot_flow(): void
    {
        $this->actingAsCoordinator();
        $program = \App\Models\Program::factory()->create(['is_active' => true]);
        $facility = Facility::factory()->create();
        $page = new MnchGptSetup;
        $page->mount();
        $page->answer('is_pilot', 0);
        $page->answer('county_id', $facility->subcounty->county_id);
        $page->answer('facility_id', $facility->id);
        $page->answer('program_id', $program->id);
        $page->answer('start_date', now()->addDay()->toDateString());
        $page->answer('end_date', now()->addMonth()->toDateString());
        $page->answer('max_participants', 8);
        $page->answer('class_name', 'Cohort A');
        $page->answer('class_start_date', now()->addDay()->toDateString());
        $page->answer('class_end_date', now()->addMonth()->toDateString());
        $page->answer('class_description', 'skip');

        $this->assertSame('modules', $page->activeStage());

        $step = $page->determineNextStep([]);

        $this->assertNull($step);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=MnchGptSetupDetermineNextStepTest`
Expected: FAIL — `Call to undefined method ... determineNextStep()`.

- [ ] **Step 3: Add the method**

In `app/Filament/Resources/MentorshipResource/Pages/MnchGptSetup.php`, add:

```php
    private const MAX_PROACTIVE_OPTIONS = 10;

    /**
     * Decides whether a numbered/lettered option list should accompany the
     * next question — never generated by the LLM, always from real slot
     * data. $candidatesFromLastTurn is the 'candidates' key from this
     * turn's tool execution result (MentorshipSetupToolProvider::tool()),
     * shaped [slotId => [['id'=>.., 'label'=>..], ...]].
     *
     * @param  array<string, array<int, array{id: mixed, label: string}>>  $candidatesFromLastTurn
     * @return array{slot: string, options: array<int, array{id: mixed, label: string}>}|null
     */
    public function determineNextStep(array $candidatesFromLastTurn = []): ?array
    {
        if (! empty($candidatesFromLastTurn)) {
            $slotId = array_key_first($candidatesFromLastTurn);

            return [
                'slot' => $slotId,
                'options' => self::numberOptions($candidatesFromLastTurn[$slotId]),
            ];
        }

        // module_ids/selected_users aren't generic Slot objects, so once
        // the modules/enroll_mentees stages begin, nextUnfilledSlot() would
        // otherwise skip straight past them to 'recipients' (send_
        // invitations) — the exact premature-exposure bug already fixed in
        // MentorshipSetupToolProvider::schemaFor()/execute(). Same guard,
        // same reason.
        if ($this->activeStage() !== 'slot') {
            return null;
        }

        $next = $this->nextUnfilledSlot();

        if (! $next || $next->renderKind() !== \App\Services\Chat\Render::CARDS) {
            return null;
        }

        $options = $next->getOptions($this->answers);

        if (count($options) > self::MAX_PROACTIVE_OPTIONS) {
            return null;
        }

        return [
            'slot' => $next->id,
            'options' => self::numberOptions(
                collect($options)->map(fn ($label, $id) => ['id' => $id, 'label' => $label])->values()->all()
            ),
        ];
    }

    /**
     * @param  array<int, array{id: mixed, label: string}>  $candidates
     * @return array<int, array{id: mixed, label: string}> 1-based
     */
    private static function numberOptions(array $candidates): array
    {
        $numbered = [];

        foreach (array_values($candidates) as $index => $candidate) {
            $numbered[$index + 1] = $candidate;
        }

        return $numbered;
    }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=MnchGptSetupDetermineNextStepTest`
Expected: PASS (4 tests).

- [ ] **Step 5: Pint + commit**

```bash
./vendor/bin/pint app/Filament/Resources/MentorshipResource/Pages/MnchGptSetup.php tests/Unit/MnchGptSetupDetermineNextStepTest.php
git add app/Filament/Resources/MentorshipResource/Pages/MnchGptSetup.php tests/Unit/MnchGptSetupDetermineNextStepTest.php
git commit -m "feat: add determineNextStep() to decide when to show an option list"
```

---

### Task 5: Wire `determineNextStep()` into `sendMessage()` — context, appended list, `pendingOptions`

**Files:**
- Modify: `app/Filament/Resources/MentorshipResource/Pages/MnchGptSetup.php`
- Modify: `app/Services/Chat/LlmMentorshipAssistantService.php`
- Test: `tests/Feature/MnchGptSetupTest.php`

**Interfaces:**
- Consumes: `determineNextStep()` (Task 4), the `candidates` key from `LlmMentorshipAssistantService::respond()`'s `tool_calls` results (Task 2).
- Produces: `MnchGptSetup::$pendingOptions` (public property, same shape as `determineNextStep()`'s return, or `null`) — consumed by Task 6's fast path. `LlmMentorshipAssistantService::systemPrompt()` gains a `next_options` context key, alongside the existing `remaining_requirements`.

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/MnchGptSetupTest.php`:

```php
    public function test_a_reply_gets_a_proactively_rendered_option_list_appended(): void
    {
        $this->actingAsCoordinator();
        Setting::setBool(Setting::MNCHGPT_BUTTON_ENABLED, true);

        // No tool call at all this turn — the model just chats — but
        // is_pilot is still the next slot and has only 2 options, so the
        // backend appends its list regardless of what the model said.
        Http::fake([
            'api.deepseek.com/*' => Http::response([
                'choices' => [['message' => ['role' => 'assistant', 'content' => 'Sure — is this a real mentorship or a pilot run?']]],
            ]),
        ]);

        $component = Livewire::test(MnchGptSetup::class);
        $component->call('sendMessage', 'I want to set up a mentorship');

        $lastMessage = collect($component->get('messages'))->last();
        $this->assertStringContainsString('Live Mentorship', $lastMessage['text']);
        $this->assertStringContainsString('Pilot Run', $lastMessage['text']);
        $this->assertSame('is_pilot', $component->get('pendingOptions')['slot']);
    }

    public function test_an_ambiguous_facility_name_appends_a_candidate_shortlist(): void
    {
        $this->actingAsCoordinator();
        Setting::setBool(Setting::MNCHGPT_BUTTON_ENABLED, true);

        $county = County::factory()->create(['name' => 'Tharaka Nithi']);
        $subcounty = Subcounty::create(['name' => 'Chuka', 'county_id' => $county->id]);
        Facility::factory()->create(['subcounty_id' => $subcounty->id, 'name' => 'Chuka County Referral Hospital']);
        Facility::factory()->create(['subcounty_id' => $subcounty->id, 'name' => 'Chuka Sub-District Hospital']);

        $component = Livewire::test(MnchGptSetup::class);
        $component->call('answer', 'is_pilot', 0);
        $component->call('answer', 'county_id', $county->id);

        Http::fake([
            'api.deepseek.com/*' => Http::sequence()
                ->push([
                    'choices' => [[
                        'message' => [
                            'role' => 'assistant',
                            'content' => null,
                            'tool_calls' => [[
                                'id' => 'call_1',
                                'type' => 'function',
                                'function' => ['name' => 'fill_mentorship_setup_slots', 'arguments' => json_encode(['facility_id' => 'Chuka'])],
                            ]],
                        ],
                    ]],
                ])
                ->push([
                    'choices' => [['message' => ['role' => 'assistant', 'content' => 'A couple of facilities match "Chuka" — which one did you mean?']]],
                ]),
        ]);

        $component->call('sendMessage', 'Chuka hospital');

        $lastMessage = collect($component->get('messages'))->last();
        $this->assertStringContainsString('Chuka County Referral Hospital', $lastMessage['text']);
        $this->assertStringContainsString('Chuka Sub-District Hospital', $lastMessage['text']);
        $this->assertSame('facility_id', $component->get('pendingOptions')['slot']);
        $this->assertArrayNotHasKey('facility_id', $component->get('answers'));
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=MnchGptSetupTest`
Expected: FAIL — `pendingOptions` undefined / assertions about appended list text fail (list isn't appended yet).

- [ ] **Step 3: Add `$pendingOptions`, a rendering helper, and wire it into `sendMessage()`**

In `app/Filament/Resources/MentorshipResource/Pages/MnchGptSetup.php`:

```php
    public ?array $pendingOptions = null;
```

Replace `sendMessage()`:

```php
    public function sendMessage(string $text): void
    {
        $text = trim($text);

        if ($text === '') {
            return;
        }

        $this->messages[] = ['role' => 'user', 'text' => $text, 'timestamp' => now()->toIso8601String()];

        $step = $this->determineNextStep([]);

        $result = app(LlmMentorshipAssistantService::class)->respond(
            userMessage: $text,
            history: $this->historyForLlm(),
            registryFactory: fn () => $this->buildToolRegistry(),
            user: auth()->user(),
            context: [
                'remaining_requirements' => $this->remainingRequirements(),
                'next_options' => $step,
            ],
        );

        $candidatesFromThisTurn = collect($result['tool_calls'])
            ->pluck('result.candidates')
            ->filter()
            ->collapse()
            ->all();

        $step = $this->determineNextStep($candidatesFromThisTurn);
        $this->pendingOptions = $step;

        $reply = $result['reply'];

        if ($step) {
            $reply .= "\n\n".$this->renderOptionList($step['options']);
        }

        $this->messages[] = ['role' => 'bot', 'text' => $reply, 'timestamp' => now()->toIso8601String()];
        $this->syncTranscript();
        $this->dispatch('mnchgpt-reply');
    }

    /**
     * @param  array<int, array{id: mixed, label: string}>  $numberedOptions
     */
    private function renderOptionList(array $numberedOptions): string
    {
        return collect($numberedOptions)
            ->map(fn (array $option, int $number) => "{$number}. {$option['label']}")
            ->implode("\n");
    }
```

- [ ] **Step 4: Extend `LlmMentorshipAssistantService::systemPrompt()` to use `next_options`**

In `app/Services/Chat/LlmMentorshipAssistantService.php`, in `systemPrompt()`, after the existing `remaining_requirements` block, add:

```php
        if (! empty($context['next_options'])) {
            $labels = collect($context['next_options']['options'])->pluck('label')->implode(', ');
            $prompt .= " A list of options ({$labels}) will be shown to the user directly below your reply — ".
                'write a short, warm sentence asking the question, but do NOT list the options yourself; '.
                'the app already displays them.';
        }
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --filter=MnchGptSetupTest`
Expected: PASS (all tests, including the two new ones).

- [ ] **Step 6: Run the full MNCHGPT-adjacent suite**

Run: `php artisan test --filter="MnchGpt|ChatMentorshipSetup|LlmMentorshipAssistant|MentorshipSetupToolProvider|MentorshipModulesToolProvider"`
Expected: PASS.

- [ ] **Step 7: Pint + commit**

```bash
./vendor/bin/pint app/Filament/Resources/MentorshipResource/Pages/MnchGptSetup.php app/Services/Chat/LlmMentorshipAssistantService.php tests/Feature/MnchGptSetupTest.php
git add app/Filament/Resources/MentorshipResource/Pages/MnchGptSetup.php app/Services/Chat/LlmMentorshipAssistantService.php tests/Feature/MnchGptSetupTest.php
git commit -m "feat: append backend-rendered option lists to MNCHGPT replies"
```

---

### Task 6: Fast-path bare number/letter resolution

**Files:**
- Modify: `app/Filament/Resources/MentorshipResource/Pages/MnchGptSetup.php`
- Test: `tests/Feature/MnchGptSetupTest.php`

**Interfaces:**
- Consumes: `$this->pendingOptions` (Task 5).
- Produces: no new public interface — this changes `sendMessage()`'s internal behavior only (still the same public method signature).

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/MnchGptSetupTest.php`:

```php
    public function test_a_bare_number_reply_resolves_instantly_without_calling_the_llm(): void
    {
        $this->actingAsCoordinator();
        Setting::setBool(Setting::MNCHGPT_BUTTON_ENABLED, true);

        $component = Livewire::test(MnchGptSetup::class);
        $messagesBefore = count($component->get('messages'));
        $component->set('pendingOptions', [
            'slot' => 'is_pilot',
            'options' => [
                1 => ['id' => 0, 'label' => 'Live Mentorship'],
                2 => ['id' => 1, 'label' => 'Pilot Run'],
            ],
        ]);

        Http::fake(); // no request should be made at all

        $component->call('sendMessage', '1');

        Http::assertNothingSent();
        $this->assertSame(0, $component->get('answers')['is_pilot']);
        // Exactly one user message (the echoed choice, via answer()'s own
        // getEcho()) and one bot message (the next question) got added —
        // guards against double-posting from both answer()'s own
        // message-appending and this fast path's.
        $this->assertCount($messagesBefore + 2, $component->get('messages'));
    }

    public function test_a_letter_reply_maps_to_the_matching_position(): void
    {
        $this->actingAsCoordinator();
        Setting::setBool(Setting::MNCHGPT_BUTTON_ENABLED, true);

        $component = Livewire::test(MnchGptSetup::class);
        $component->set('pendingOptions', [
            'slot' => 'is_pilot',
            'options' => [
                1 => ['id' => 0, 'label' => 'Live Mentorship'],
                2 => ['id' => 1, 'label' => 'Pilot Run'],
            ],
        ]);

        Http::fake();

        $component->call('sendMessage', 'b');

        Http::assertNothingSent();
        $this->assertSame(1, $component->get('answers')['is_pilot']);
    }

    public function test_an_out_of_range_number_falls_through_to_the_normal_llm_flow(): void
    {
        $this->actingAsCoordinator();
        Setting::setBool(Setting::MNCHGPT_BUTTON_ENABLED, true);

        $component = Livewire::test(MnchGptSetup::class);
        $component->set('pendingOptions', [
            'slot' => 'is_pilot',
            'options' => [
                1 => ['id' => 0, 'label' => 'Live Mentorship'],
                2 => ['id' => 1, 'label' => 'Pilot Run'],
            ],
        ]);

        Http::fake([
            'api.deepseek.com/*' => Http::response([
                'choices' => [['message' => ['role' => 'assistant', 'content' => "I only have options 1 and 2 — which did you mean?"]]],
            ]),
        ]);

        $component->call('sendMessage', '99');

        Http::assertSentCount(1);
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=MnchGptSetupTest`
Expected: FAIL — the first two make a real HTTP call (`Http::assertNothingSent()` fails) since the fast path doesn't exist yet.

- [ ] **Step 3: Add the fast path at the top of `sendMessage()`**

In `app/Filament/Resources/MentorshipResource/Pages/MnchGptSetup.php`, right after the empty-text guard:

```php
        if ($this->pendingOptions) {
            $index = $this->matchPendingOptionIndex($text);

            if ($index !== null) {
                $option = $this->pendingOptions['options'][$index];

                // answer() already appends both the user's echoed choice
                // (via the slot's getEcho(), e.g. "Live Mentorship" rather
                // than a bare "1") and the plain next-slot question as its
                // own bot message — reusing it here avoids re-implementing
                // that echo/validation/stage-completion logic. This method
                // only adds the numbered list on top, by appending to
                // whichever bot message answer() just pushed.
                $this->answer($this->pendingOptions['slot'], $option['id']);

                $step = $this->determineNextStep([]);
                $this->pendingOptions = $step;

                if ($step) {
                    $lastIndex = array_key_last($this->messages);

                    if ($this->messages[$lastIndex]['role'] === 'bot') {
                        $this->messages[$lastIndex]['text'] .= "\n\n".$this->renderOptionList($step['options']);
                    }
                }

                $this->syncTranscript();
                $this->dispatch('mnchgpt-reply');

                return;
            }
        }

        $this->messages[] = ['role' => 'user', 'text' => $text, 'timestamp' => now()->toIso8601String()];
```

(This inserts a new branch before the existing user-message append — `answer()` handles its own message-appending on the fast path, so no separate user-message line is added there; the fall-through path below it is unchanged from before this task.)

Add the matcher helper:

```php
    /**
     * Bare "2" or single-letter "B" (case-insensitive, A=1, B=2, ...)
     * against the currently pending option list. Anything else (a longer
     * reply, an out-of-range number) returns null so the caller falls
     * through to the normal LLM flow.
     */
    private function matchPendingOptionIndex(string $text): ?int
    {
        if (preg_match('/^\d+$/', $text)) {
            $index = (int) $text;
        } elseif (preg_match('/^[a-zA-Z]$/', $text)) {
            $index = ord(strtoupper($text)) - ord('A') + 1;
        } else {
            return null;
        }

        return array_key_exists($index, $this->pendingOptions['options']) ? $index : null;
    }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=MnchGptSetupTest`
Expected: PASS (all tests).

- [ ] **Step 5: Verify `answer()` clears `pendingOptions` staleness correctly**

Since `answer()` (in `HasMentorshipChatSlots`) is also called directly by the click-driven `chat-turn.blade.php` path if it were ever re-added, and by the fast path here — confirm no other code path leaves `$this->pendingOptions` stale after a *different* slot gets answered through the normal LLM route. This is already covered by Task 5's `determineNextStep([])` call recomputing (and overwriting) `pendingOptions` on every normal turn — add one more explicit regression test:

```php
    public function test_pending_options_are_cleared_once_a_different_step_is_computed(): void
    {
        $this->actingAsCoordinator();
        Setting::setBool(Setting::MNCHGPT_BUTTON_ENABLED, true);

        $component = Livewire::test(MnchGptSetup::class);
        $component->set('pendingOptions', [
            'slot' => 'county_id',
            'options' => [1 => ['id' => 999, 'label' => 'Stale County']],
        ]);

        Http::fake([
            'api.deepseek.com/*' => Http::sequence()
                ->push([
                    'choices' => [[
                        'message' => [
                            'role' => 'assistant',
                            'content' => null,
                            'tool_calls' => [[
                                'id' => 'call_1',
                                'type' => 'function',
                                'function' => ['name' => 'fill_mentorship_setup_slots', 'arguments' => json_encode(['is_pilot' => 0])],
                            ]],
                        ],
                    ]],
                ])
                ->push(['choices' => [['message' => ['role' => 'assistant', 'content' => 'Got it.']]]]),
        ]);

        $component->call('sendMessage', 'live mentorship');

        // The stale list is gone — a bare "1" now falls through to the LLM
        // rather than resolving against the old (irrelevant) county list.
        $this->assertNotSame('county_id', $component->get('pendingOptions')['slot'] ?? null);
    }
```

Run: `php artisan test --filter=MnchGptSetupTest`
Expected: PASS.

- [ ] **Step 6: Pint + commit**

```bash
./vendor/bin/pint app/Filament/Resources/MentorshipResource/Pages/MnchGptSetup.php tests/Feature/MnchGptSetupTest.php
git add app/Filament/Resources/MentorshipResource/Pages/MnchGptSetup.php tests/Feature/MnchGptSetupTest.php
git commit -m "feat: resolve bare number/letter replies instantly, no LLM call"
```

---

### Task 7: Greeting & intent-routing `mount()` override

**Files:**
- Modify: `app/Filament/Resources/MentorshipResource/Pages/MnchGptSetup.php`
- Test: `tests/Feature/MnchGptSetupTest.php`

**Interfaces:**
- Produces: `MnchGptSetup::mount()` (overrides the trait's version for this page only — `HasMentorshipChatSlots::mount()` is untouched, still used as-is by `ChatMentorshipSetup`).

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/MnchGptSetupTest.php`:

```php
    public function test_the_greeting_asks_what_the_user_wants_to_do_instead_of_jumping_into_slots(): void
    {
        $this->actingAsCoordinator();
        Setting::setBool(Setting::MNCHGPT_BUTTON_ENABLED, true);

        $component = Livewire::test(MnchGptSetup::class);

        $greeting = collect($component->get('messages'))->first()['text'];
        $this->assertStringContainsString('Coordinator', $greeting);
        $this->assertStringContainsString('MNCHGPT', $greeting);
        $this->assertStringNotContainsString('Is this a real live mentorship', $greeting);
    }

    public function test_an_analytics_question_works_before_any_mentorship_intent_is_expressed(): void
    {
        $this->actingAsCoordinator();
        Setting::setBool(Setting::MNCHGPT_BUTTON_ENABLED, true);

        Http::fake([
            'api.deepseek.com/*' => Http::response([
                'choices' => [['message' => ['role' => 'assistant', 'content' => 'There are 3 live mentorships.']]],
            ]),
        ]);

        $component = Livewire::test(MnchGptSetup::class);
        $component->call('sendMessage', 'how many live mentorships are there?');

        $this->assertSame([], $component->get('answers'));
        $lastMessage = collect($component->get('messages'))->last();
        $this->assertSame('There are 3 live mentorships.', $lastMessage['text']);
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=MnchGptSetupTest`
Expected: FAIL — the greeting still contains "Is this a real live mentorship..." (from the trait's `mount()`).

- [ ] **Step 3: Add the override**

In `app/Filament/Resources/MentorshipResource/Pages/MnchGptSetup.php`, add (this intentionally does **not** call `parent::mount()`/the trait's version — it duplicates the small resume-from-training-record branch, since that part must stay identical, but replaces the fresh-start greeting):

```php
    public function mount(): void
    {
        if ($this->trainingId) {
            $this->training = \App\Models\Training::find($this->trainingId);
        }

        if ($this->classId) {
            $this->class = \App\Models\MentorshipClass::find($this->classId);
        }

        if ($this->training && ! empty($this->training->chat_setup_transcript)) {
            $this->messages = $this->training->chat_setup_transcript;
            $this->answers = $this->rebuildAnswersFromTraining();

            return;
        }

        $firstName = explode(' ', auth()->user()->name)[0];

        $this->messages[] = [
            'role' => 'bot',
            'text' => "Hello {$firstName}, welcome back! I'm MNCHGPT. How can I help today — would you like to ".
                'start creating a mentorship, or is there something else I can help with?',
            'timestamp' => now()->toIso8601String(),
        ];
    }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=MnchGptSetupTest`
Expected: PASS (all tests).

- [ ] **Step 5: Confirm `ChatMentorshipSetup` is unaffected**

Run: `php artisan test --filter=ChatMentorshipSetupTest`
Expected: PASS (all 21, unchanged) — `HasMentorshipChatSlots::mount()` was never touched, so its greeting/first-question behavior is identical to before.

- [ ] **Step 6: Pint + commit**

```bash
./vendor/bin/pint app/Filament/Resources/MentorshipResource/Pages/MnchGptSetup.php tests/Feature/MnchGptSetupTest.php
git add app/Filament/Resources/MentorshipResource/Pages/MnchGptSetup.php tests/Feature/MnchGptSetupTest.php
git commit -m "feat: greet and ask intent instead of jumping straight into slot questions"
```

---

### Task 8: Blade changes — remove card UI, restyle input, collapsible checklist

**Files:**
- Modify: `resources/views/filament/pages/mnchgpt-setup.blade.php`
- Modify: `resources/views/filament/pages/partials/mnchgpt-input.blade.php`
- Modify: `resources/views/filament/pages/partials/mnchgpt-checklist.blade.php`

**Interfaces:**
- Consumes: `$this->activeStage()`, `$this->isModulesStageEmonc()` (unchanged, from the trait) — the `modules`/`enroll_mentees` branches and their includes (`chat-modules-turn`, `chat-mentees-turn`) are untouched; only the generic-slot (`chat-turn`) branch is removed.

No PHP behavior changes in this task — purely template changes, verified by re-running the full feature suite (Livewire tests render the view, so a broken blade file surfaces as a test failure) plus a manual look in the browser.

- [ ] **Step 1: Remove the card UI include from `mnchgpt-setup.blade.php`**

Current:

```blade
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

                @if ($this->nextUnfilledSlot())
                    @include('filament.pages.partials.chat-turn', ['slot' => $this->nextUnfilledSlot(), 'answers' => $answers])
                @endif
            @endif
        @else
```

Replace with:

```blade
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
```

- [ ] **Step 2: Restyle the input to feel like a ChatGPT/DeepSeek textarea**

Replace the contents of `resources/views/filament/pages/partials/mnchgpt-input.blade.php`:

```blade
<form
    wire:submit="sendMessage($refs.messageInput.value); $refs.messageInput.value = ''; $refs.messageInput.style.height = 'auto'"
    class="flex items-end gap-2 pt-2"
>
    <textarea
        x-ref="messageInput"
        rows="1"
        placeholder="Message MNCHGPT..."
        wire:loading.attr="disabled"
        wire:target="sendMessage"
        x-on:input="$el.style.height = 'auto'; $el.style.height = $el.scrollHeight + 'px'"
        x-on:keydown.enter.prevent="if (! $event.shiftKey) { $el.closest('form').requestSubmit() }"
        class="fi-input flex-1 resize-none rounded-2xl border-gray-300 dark:border-gray-600 text-sm max-h-40"
    ></textarea>
    <button
        type="submit"
        wire:loading.attr="disabled"
        wire:target="sendMessage"
        class="fi-btn fi-btn-color-primary fi-btn-size-md rounded-full bg-primary-600 p-2.5 text-white disabled:cursor-wait disabled:opacity-60"
    >
        <span wire:loading.remove wire:target="sendMessage">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4">
                <path d="M3.105 2.289a.75.75 0 00-.826.95l1.414 4.925A1.5 1.5 0 005.135 9.25h6.115a.75.75 0 010 1.5H5.135a1.5 1.5 0 00-1.442 1.086l-1.414 4.926a.75.75 0 00.826.95 28.897 28.897 0 0015.293-7.154.75.75 0 000-1.115A28.897 28.897 0 003.105 2.289z" />
            </svg>
        </span>
        <span wire:loading wire:target="sendMessage">
            <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
        </span>
    </button>
</form>
```

- [ ] **Step 3: Make the checklist collapsed by default**

Replace `resources/views/filament/pages/partials/mnchgpt-checklist.blade.php`:

```blade
@php $outstanding = array_filter($requirements, fn ($r) => ! $r['filled']); @endphp
@if(! empty($outstanding))
<div x-data="{ open: false }" class="rounded-lg bg-gray-50 dark:bg-gray-800/60 text-sm">
    <button
        type="button"
        x-on:click="open = ! open"
        class="flex w-full items-center justify-between px-4 py-2 font-semibold text-gray-700 dark:text-gray-300"
    >
        <span>{{ count($outstanding) }} of {{ count($requirements) }} still needed</span>
        <span x-text="open ? '▾' : '▸'"></span>
    </button>
    <ul x-show="open" x-cloak class="list-disc list-inside space-y-1 px-4 pb-3 text-gray-600 dark:text-gray-400">
        @foreach($outstanding as $item)
            <li>{{ $item['label'] }}</li>
        @endforeach
    </ul>
</div>
@endif
```

- [ ] **Step 4: Run the full feature suite to confirm the templates render without error**

Run: `php artisan test --filter="MnchGpt"`
Expected: PASS (all `MnchGptSetupTest`/`MnchGptEndToEndTest` tests) — Livewire's `Livewire::test()` fully renders the Blade view on every `->call()`, so a syntax error or missing variable here would surface as a failure.

- [ ] **Step 5: Confirm `ChatMentorshipSetup`'s own blade path is untouched**

Run: `php artisan test --filter=ChatMentorshipSetupTest`
Expected: PASS (all 21) — `chat-turn.blade.php`, `chat-mentorship-setup.blade.php` were not modified, only `mnchgpt-setup.blade.php`'s inclusion of `chat-turn` was removed.

- [ ] **Step 6: Pint (blade files aren't Pint's concern, but re-run on any touched PHP) + commit**

```bash
git add resources/views/filament/pages/mnchgpt-setup.blade.php resources/views/filament/pages/partials/mnchgpt-input.blade.php resources/views/filament/pages/partials/mnchgpt-checklist.blade.php
git commit -m "feat: pure chat UI for MNCHGPT — remove card buttons, collapsible checklist"
```

---

### Task 9: Full end-to-end conversation test + final regression

**Files:**
- Test: `tests/Feature/MnchGptEndToEndTest.php` (add one new test)

**Interfaces:**
- Consumes: everything built in Tasks 1–8.

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/MnchGptEndToEndTest.php`:

```php
    public function test_a_full_conversation_with_a_paragraph_an_ambiguous_name_and_a_number_reply(): void
    {
        $user = User::factory()->create(['name' => 'Coordinator']);
        Permission::firstOrCreate(['name' => 'create_mentorship::training', 'guard_name' => 'web']);
        $user->givePermissionTo(['create_mentorship::training']);
        $this->actingAs($user);
        Setting::setBool(Setting::MNCHGPT_BUTTON_ENABLED, true);

        $county = County::factory()->create(['name' => 'Tharaka Nithi']);
        $subcounty = \App\Models\Subcounty::create(['name' => 'Chuka', 'county_id' => $county->id]);
        $facilityA = \App\Models\Facility::factory()->create(['subcounty_id' => $subcounty->id, 'name' => 'Chuka County Referral Hospital']);
        \App\Models\Facility::factory()->create(['subcounty_id' => $subcounty->id, 'name' => 'Chuka Sub-District Hospital']);

        $component = Livewire::test(MnchGptSetup::class);

        // Greeting is the first message.
        $this->assertStringContainsString('MNCHGPT', collect($component->get('messages'))->first()['text']);

        // One paragraph fills is_pilot + county in a single turn.
        Http::fake([
            'api.deepseek.com/*' => Http::sequence()
                ->push(['choices' => [[
                    'message' => [
                        'role' => 'assistant', 'content' => null,
                        'tool_calls' => [[
                            'id' => 'call_1', 'type' => 'function',
                            'function' => ['name' => 'fill_mentorship_setup_slots', 'arguments' => json_encode([
                                'is_pilot' => 0, 'county_id' => (string) $county->id,
                            ])],
                        ]],
                    ],
                ]]])
                ->push(['choices' => [['message' => ['role' => 'assistant', 'content' => 'Great, live mentorship in Tharaka Nithi! Which facility?']]]]),
        ]);
        $component->call('sendMessage', 'I want to set up a real live mentorship in Tharaka Nithi county');

        $this->assertSame(0, $component->get('answers')['is_pilot']);
        $this->assertEquals($county->id, $component->get('answers')['county_id']);

        // An ambiguous facility name produces a shortlist, not a fill.
        Http::fake([
            'api.deepseek.com/*' => Http::sequence()
                ->push(['choices' => [[
                    'message' => [
                        'role' => 'assistant', 'content' => null,
                        'tool_calls' => [[
                            'id' => 'call_2', 'type' => 'function',
                            'function' => ['name' => 'fill_mentorship_setup_slots', 'arguments' => json_encode(['facility_id' => 'Chuka'])],
                        ]],
                    ],
                ]]])
                ->push(['choices' => [['message' => ['role' => 'assistant', 'content' => 'A couple of facilities match "Chuka" — which one?']]]]),
        ]);
        $component->call('sendMessage', 'Chuka hospital');

        $this->assertArrayNotHasKey('facility_id', $component->get('answers'));
        $lastMessage = collect($component->get('messages'))->last();
        $this->assertStringContainsString('Chuka County Referral Hospital', $lastMessage['text']);

        // Picking "1" resolves it instantly — no further HTTP call.
        Http::fake();
        $component->call('sendMessage', '1');
        Http::assertNothingSent();

        $this->assertEquals($facilityA->id, $component->get('answers')['facility_id']);
    }
```

- [ ] **Step 2: Run test to verify it passes**

Run: `php artisan test --filter=MnchGptEndToEndTest`
Expected: PASS.

(If it fails, work through the failure by re-checking Tasks 5/6's exact wiring — this test exercises the full path: greeting → paragraph tool-call fill → ambiguous fuzzy shortlist → fast-path number resolution.)

- [ ] **Step 3: Pint the new test file**

```bash
./vendor/bin/pint tests/Feature/MnchGptEndToEndTest.php
```

- [ ] **Step 4: Full regression**

```bash
php artisan config:clear
php artisan test
```

Expected: all tests pass except the 2 pre-existing, unrelated baseline failures if they've resurfaced (there should be none at this point in the session — if any appear, stop and investigate before continuing, don't assume they're pre-existing without checking `git stash` + re-running on a clean tree).

- [ ] **Step 5: Commit**

```bash
git add tests/Feature/MnchGptEndToEndTest.php
git commit -m "test: add full MNCHGPT conversational redesign end-to-end test"
```
