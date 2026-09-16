# Assessment Team Management Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Wire up the already-migrated "assessment team management" feature — model relations/authorization, a Filament "Manage Team" action, and the mobile API — without touching any of this repo's existing v2-specific behavior.

**Architecture:** Additive changes only. `AssessmentTeamMember` (pivot model), `AssessmentTeamService`, and the `assessment_team`/`is_locked` schema already exist and are correct — this plan adds the `Assessment` model methods that service already calls, then wires that into the Filament table and the mobile API, following each file's own established conventions (PSR-12 brace style in `app/Models` and `app/Filament`, same-line brace style in `app/Http/Controllers/Api` and `app/Http/Resources/Api`).

**Tech Stack:** Laravel 12, Filament v3, Spatie Permission (roles), Laravel Sanctum (mobile API tokens), PHPUnit + Livewire testing helpers.

## Global Constraints

- Do not modify `assessment_type_id`, the `assessmentType()` relation, `excluded_cadre_ids`, or `getCompletionPercentageAttribute()` on `Assessment` — these are v2-specific and absent/regressed in the reference repo.
- Do not restructure `getEloquentQuery()`'s existing admin-role branch (`super_admin`, `admin`, `division`) — only extend the non-admin branch.
- Do not touch `ManageTeamAction.php`, `HasTeamAuthorization.php`, or `AssessmentPolicy.php` — confirmed dead/unrelated to this feature in both repos.
- No lock/unlock UI — add the model methods only (schema already supports them, and `AssessmentTeamService` already calls them), matching the reference repo's own state.
- Match each file's existing brace/formatting style exactly (see Architecture above) — don't introduce a second style into a file.
- Every new test follows the existing per-directory convention already in this repo: `makeUserWithRole()`/`RefreshDatabase` pattern (`tests/Feature/AssessmentResourceTest.php`), `Livewire::test(ListAssessments::class)->callTableAction(...)` (`tests/Feature/AssessmentDownloadActionTest.php`), Sanctum `createToken()` bearer-token pattern (`tests/Feature/Api/AssessmentSectionApiTest.php`).

---

## Task 1: `Assessment` model — team relations & authorization methods

**Files:**
- Modify: `app/Models/Assessment.php`
- Test: `tests/Feature/AssessmentTeamManagementTest.php` (new)

**Interfaces:**
- Produces (used by Tasks 2–7): `Assessment::teamMembers(): BelongsToMany`, `teamLeads(): BelongsToMany`, `teamMembersOnly(): BelongsToMany`, `locker(): BelongsTo`, `isTeamMember(int $userId): bool`, `isTeamLead(int $userId): bool`, `canManageTeam(?int $userId): bool`, `canToggleLock(?int $userId): bool`, `isLocked(): bool`, `lock(int $userId): void`, `unlock(): void`. A `static::created` boot hook that attaches the assessor as `team_lead` on every new assessment.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/AssessmentTeamManagementTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AssessmentTeamManagementTest extends TestCase
{
    use RefreshDatabase;

    private function makeUserWithRole(string $role): User
    {
        $user = User::factory()->create(['name' => "Test {$role}"]);
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view_any_assessment', 'guard_name' => 'web']);
        $user->assignRole($role);
        $user->givePermissionTo('view_any_assessment');

        return $user;
    }

    private function createAssessmentAs(User $assessor): Assessment
    {
        $this->actingAs($assessor);
        $facility = Facility::factory()->create();

        return Assessment::create([
            'facility_id' => $facility->id,
            'assessment_type' => 'baseline',
            'assessment_date' => now(),
        ]);
    }

    public function test_creating_an_assessment_auto_attaches_the_assessor_as_team_lead(): void
    {
        $assessor = $this->makeUserWithRole('assessor');
        $assessment = $this->createAssessmentAs($assessor);

        $this->assertTrue($assessment->isTeamLead($assessor->id));
        $this->assertTrue($assessment->canManageTeam($assessor->id));
    }

    public function test_a_regular_team_member_cannot_manage_the_team(): void
    {
        $assessor = $this->makeUserWithRole('assessor');
        $member = $this->makeUserWithRole('assessor');
        $assessment = $this->createAssessmentAs($assessor);

        $assessment->teamMembers()->attach($member->id, [
            'role' => 'member',
            'added_by' => $assessor->id,
            'added_at' => now(),
        ]);

        $this->assertTrue($assessment->isTeamMember($member->id));
        $this->assertFalse($assessment->canManageTeam($member->id));
    }

    public function test_an_uninvited_assessor_is_not_a_team_member(): void
    {
        $assessor = $this->makeUserWithRole('assessor');
        $outsider = $this->makeUserWithRole('assessor');
        $assessment = $this->createAssessmentAs($assessor);

        $this->assertFalse($assessment->isTeamMember($outsider->id));
        $this->assertFalse($assessment->canManageTeam($outsider->id));
    }

    public function test_super_admin_admin_and_division_can_always_manage_the_team(): void
    {
        $assessor = $this->makeUserWithRole('assessor');
        $assessment = $this->createAssessmentAs($assessor);

        foreach (['super_admin', 'admin', 'division'] as $role) {
            $privileged = $this->makeUserWithRole($role);
            $this->assertTrue(
                $assessment->canManageTeam($privileged->id),
                "Role {$role} should be able to manage the team"
            );
        }
    }

    public function test_lock_and_unlock_toggle_is_locked_and_stamp_locked_by(): void
    {
        $assessor = $this->makeUserWithRole('assessor');
        $assessment = $this->createAssessmentAs($assessor);

        $assessment->lock($assessor->id);
        $this->assertTrue($assessment->fresh()->isLocked());
        $this->assertSame($assessor->id, $assessment->fresh()->locked_by);

        $assessment->unlock();
        $this->assertFalse($assessment->fresh()->isLocked());
        $this->assertNull($assessment->fresh()->locked_by);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=AssessmentTeamManagementTest`
Expected: FAIL — `Call to undefined method App\Models\Assessment::isTeamLead()` (and similar) on every test.

- [ ] **Step 3: Add the fillable/cast entries for the lock columns**

In `app/Models/Assessment.php`, the migration `2026_03_03_082118_assessment_team_management.php` already added `is_locked`, `locked_at`, `locked_by` columns, but the model doesn't expose them yet. Add to `$fillable` (after `'excluded_cadre_ids',` on line 38):

```php
        'excluded_cadre_ids',
        'is_locked',
        'locked_at',
        'locked_by',
    ];
```

Add to `$casts` (after `'excluded_cadre_ids' => 'array',` on line 51):

```php
        'excluded_cadre_ids' => 'array',
        'is_locked' => 'boolean',
        'locked_at' => 'datetime',
    ];
```

- [ ] **Step 4: Add the `BelongsToMany` import**

At the top of `app/Models/Assessment.php`, after the existing `use Illuminate\Database\Eloquent\Relations\BelongsTo;` (line 6):

```php
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
```

- [ ] **Step 5: Add the auto-attach-team-lead boot hook**

In `app/Models/Assessment.php`, inside `protected static function boot()`, insert a new `static::created(...)` block between the existing `static::creating(...)` block (ends line 77) and `static::updating(...)` block (starts line 79):

```php
        static::creating(function ($assessment) {
            // ... existing body, unchanged
        });

        static::created(function (self $assessment) {
            if ($assessment->assessor_id && ! $assessment->teamMembers()->whereKey($assessment->assessor_id)->exists()) {
                $assessment->teamMembers()->attach($assessment->assessor_id, [
                    'role' => 'team_lead',
                    'added_by' => $assessment->assessor_id,
                    'added_at' => now(),
                ]);
            }
        });

        static::updating(function ($assessment) {
            // ... existing body, unchanged
        });
```

- [ ] **Step 6: Add the team relations**

In `app/Models/Assessment.php`, after `feedbackGivenBy()` (ends line 123) and before the `// Dynamic Question Responses` comment (line 125), insert:

```php
    public function locker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function teamMembers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'assessment_team')
            ->using(AssessmentTeamMember::class)
            ->withPivot(['role', 'added_by', 'added_at'])
            ->withTimestamps();
    }

    public function teamLeads(): BelongsToMany
    {
        return $this->teamMembers()->wherePivot('role', 'team_lead');
    }

    public function teamMembersOnly(): BelongsToMany
    {
        return $this->teamMembers()->wherePivot('role', 'member');
    }

```

- [ ] **Step 7: Add the authorization/lock helper methods**

At the end of `app/Models/Assessment.php`, after `getGradeLabelAttribute()` and before the final closing `}` of the class:

```php
    public function isTeamMember(int $userId): bool
    {
        return $this->teamMembers()->whereKey($userId)->exists();
    }

    public function isTeamLead(int $userId): bool
    {
        return $this->teamLeads()->whereKey($userId)->exists();
    }

    public function canManageTeam(?int $userId): bool
    {
        if ($userId === null) {
            return false;
        }

        $user = User::find($userId);

        if ($user && $user->hasRole(['super_admin', 'admin', 'division'])) {
            return true;
        }

        if ($this->isTeamLead($userId)) {
            return true;
        }

        // Assessments created before team management was introduced have no
        // pivot row yet. Their original assessor may initialise the team once.
        return $this->teamLeads()->doesntExist()
            && ($this->assessor_id === $userId || $this->created_by === $userId);
    }

    public function canToggleLock(?int $userId): bool
    {
        return $this->canManageTeam($userId);
    }

    public function isLocked(): bool
    {
        return (bool) $this->is_locked;
    }

    public function lock(int $userId): void
    {
        $this->update(['is_locked' => true, 'locked_at' => now(), 'locked_by' => $userId]);
    }

    public function unlock(): void
    {
        $this->update(['is_locked' => false, 'locked_at' => null, 'locked_by' => null]);
    }
```

- [ ] **Step 8: Run tests to verify they pass**

Run: `php artisan test --filter=AssessmentTeamManagementTest`
Expected: PASS (all 5 tests)

- [ ] **Step 9: Run the existing assessment test suite to check for regressions**

Run: `php artisan test --filter=AssessmentResourceTest`
Expected: PASS (unchanged — this task doesn't touch `getEloquentQuery()` yet, that's Task 3)

- [ ] **Step 10: Commit**

```bash
git add app/Models/Assessment.php tests/Feature/AssessmentTeamManagementTest.php
git commit -m "feat: add team relations and authorization methods to Assessment model"
```

---

## Task 2: `AssessmentTeamService` — legacy creator promotion

**Files:**
- Modify: `app/Services/AssessmentTeamService.php`
- Test: `tests/Feature/AssessmentTeamManagementTest.php` (append)

**Interfaces:**
- Consumes: `Assessment::teamLeads()`, `teamMembers()`, `isTeamMember()`, `isTeamLead()` (Task 1).
- Produces: `AssessmentTeamService::addMember()` and `addMembers()` now also backfill a `team_lead` pivot row for assessments that predate this feature.

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/AssessmentTeamManagementTest.php` (add `use App\Services\AssessmentTeamService;` to the imports at the top):

```php
    public function test_adding_a_member_to_a_legacy_assessment_promotes_the_original_assessor_to_lead(): void
    {
        $assessor = $this->makeUserWithRole('assessor');
        $newMember = $this->makeUserWithRole('assessor');
        $assessment = $this->createAssessmentAs($assessor);

        // Simulate a pre-team-management assessment: no pivot rows at all,
        // as if it were created before the `created` boot hook existed.
        $assessment->teamMembers()->detach();
        $this->assertTrue($assessment->teamLeads()->doesntExist());

        app(AssessmentTeamService::class)->addMember($assessment, $newMember->id, $assessor->id);

        $this->assertTrue($assessment->fresh()->isTeamLead($assessor->id));
        $this->assertTrue($assessment->fresh()->isTeamMember($newMember->id));
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=test_adding_a_member_to_a_legacy_assessment_promotes_the_original_assessor_to_lead`
Expected: FAIL — after detaching, `$assessment->canManageTeam($assessor->id)` is false (no team lead, but `assessor_id === $assessor->id` should make this true per Task 1's `canManageTeam`... check: actually this should already pass `assertCanManageTeam` since assessor_id matches). The assertion that fails is `isTeamLead($assessor->id)` — after `addMember`, the assessor still has no pivot row (only the new member does), so `isTeamLead` is false.

- [ ] **Step 3: Add `ensureLegacyCreatorIsTeamLead()` and call it from `addMember`/`addMembers`**

In `app/Services/AssessmentTeamService.php`, modify `addMember()`:

```php
    public function addMember(Assessment $assessment, int $userId, int $actorId): void {
        $this->assertCanManageTeam($assessment, $actorId);
        $this->ensureLegacyCreatorIsTeamLead($assessment, $actorId);

        if ($assessment->isTeamMember($userId)) {
            return; // already on team — silent no-op
        }

        $assessment->teamMembers()->attach($userId, [
            'role' => 'member',
            'added_by' => $actorId,
            'added_at' => now(),
        ]);
    }
```

And `addMembers()`:

```php
    public function addMembers(Assessment $assessment, array $userIds, int $actorId): void {
        $this->assertCanManageTeam($assessment, $actorId);
        $this->ensureLegacyCreatorIsTeamLead($assessment, $actorId);

        $existing = $assessment->teamMembers()->pluck('users.id')->toArray();

        foreach ($userIds as $userId) {
            if (!in_array($userId, $existing)) {
                $assessment->teamMembers()->attach($userId, [
                    'role' => 'member',
                    'added_by' => $actorId,
                    'added_at' => now(),
                ]);
            }
        }
    }
```

Add the new private method next to `assertCanManageTeam`/`assertCanToggleLock`:

```php
    /**
     * Give the original assessor lead ownership the first time a legacy
     * assessment is shared. New assessments receive this pivot row on
     * create via the Assessment model's `created` event.
     */
    private function ensureLegacyCreatorIsTeamLead(Assessment $assessment, int $actorId): void {
        if ($assessment->teamLeads()->exists()) {
            return;
        }

        if ($assessment->assessor_id !== $actorId && $assessment->created_by !== $actorId) {
            return;
        }

        $assessment->teamMembers()->syncWithoutDetaching([
            $actorId => [
                'role' => 'team_lead',
                'added_by' => $actorId,
                'added_at' => now(),
            ],
        ]);
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=AssessmentTeamManagementTest`
Expected: PASS (all 6 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Services/AssessmentTeamService.php tests/Feature/AssessmentTeamManagementTest.php
git commit -m "feat: backfill team lead on legacy assessments when their team is first touched"
```

---

## Task 3: Filament row-scoping — team members can see shared assessments

**Files:**
- Modify: `app/Filament/Resources/AssessmentResource.php:56-66`
- Test: `tests/Feature/AssessmentResourceTest.php` (append)

**Interfaces:**
- Consumes: `Assessment::teamMembers()` (Task 1).
- Produces: `AssessmentResource::getEloquentQuery()` now also returns assessments where the current user is `created_by` or a team member, not just `assessor_id`.

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/AssessmentResourceTest.php`:

```php
    public function test_team_member_can_see_an_assessment_they_were_invited_to(): void
    {
        $assessorA = $this->makeUserWithRole('assessor');
        $assessorB = $this->makeUserWithRole('assessor');
        $assessmentA = $this->createAssessmentAs($assessorA);

        $assessmentA->teamMembers()->attach($assessorB->id, [
            'role' => 'member',
            'added_by' => $assessorA->id,
            'added_at' => now(),
        ]);

        $this->actingAs($assessorB);
        $visibleIds = AssessmentResource::getEloquentQuery()->pluck('id')->all();

        $this->assertContains($assessmentA->id, $visibleIds);
    }

    public function test_an_uninvited_assessor_still_cannot_see_another_assessors_record(): void
    {
        $assessorA = $this->makeUserWithRole('assessor');
        $outsider = $this->makeUserWithRole('assessor');
        $assessmentA = $this->createAssessmentAs($assessorA);

        $this->actingAs($outsider);
        $visibleIds = AssessmentResource::getEloquentQuery()->pluck('id')->all();

        $this->assertNotContains($assessmentA->id, $visibleIds);
    }
```

- [ ] **Step 2: Run tests to verify the first one fails**

Run: `php artisan test --filter=AssessmentResourceTest`
Expected: `test_team_member_can_see_an_assessment_they_were_invited_to` FAILS (assessor B isn't in the visible set yet); the rest, including the new `test_an_uninvited_assessor_still_cannot_see_another_assessors_record`, PASS already under the current query.

- [ ] **Step 3: Extend the query scoping**

In `app/Filament/Resources/AssessmentResource.php`, replace the body of `getEloquentQuery()` (lines 56-66):

```php
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user && ! $user->hasRole(['super_admin', 'admin', 'division'])) {
            $query->where(function (Builder $query) use ($user) {
                $query->where('assessor_id', $user->id)
                    ->orWhere('created_by', $user->id)
                    ->orWhereHas('teamMembers', fn (Builder $team) => $team->where('users.id', $user->id));
            });
        }

        return $query;
    }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=AssessmentResourceTest`
Expected: PASS (all 8 tests — the 6 pre-existing plus the 2 new ones)

- [ ] **Step 5: Run the wider assessment suite to check for regressions**

Run: `php artisan test --filter=Assessment`
Expected: PASS across `AssessmentResourceTest`, `AssessmentTableFiltersTest`, `AssessmentTeamManagementTest`, and the other `Assessment*` feature/unit tests.

- [ ] **Step 6: Commit**

```bash
git add app/Filament/Resources/AssessmentResource.php tests/Feature/AssessmentResourceTest.php
git commit -m "feat: let assessment creators and team members see shared assessments in the admin list"
```

---

## Task 4: Filament "Manage Team" action + team column

**Files:**
- Modify: `app/Filament/Resources/AssessmentResource/Pages/ListAssessments.php`
- Test: `tests/Feature/AssessmentManageTeamActionTest.php` (new)

**Interfaces:**
- Consumes: `Assessment::canManageTeam()` (Task 1), `AssessmentTeamService::getTeamForDisplay()`, `getEligibleUsers()`, `addMembers()` (already existed; Task 2 added the legacy-backfill call inside `addMembers()`).
- Produces: a `manage_team` table action (name matters — tests target it by this name) and a `team_members_count` column, both on `ListAssessments`.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/AssessmentManageTeamActionTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Filament\Resources\AssessmentResource\Pages\ListAssessments;
use App\Models\Assessment;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AssessmentManageTeamActionTest extends TestCase
{
    use RefreshDatabase;

    private function makeAssessor(string $name): User
    {
        $user = User::factory()->create(['name' => $name]);
        Role::firstOrCreate(['name' => 'assessor', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view_any_assessment', 'guard_name' => 'web']);
        $user->assignRole('assessor');
        $user->givePermissionTo('view_any_assessment');

        return $user;
    }

    public function test_manage_team_action_invites_selected_assessors(): void
    {
        $lead = $this->makeAssessor('Lead Assessor');
        $invitee = $this->makeAssessor('Invitee Assessor');

        $this->actingAs($lead);
        $facility = Facility::factory()->create();
        $assessment = Assessment::create([
            'facility_id' => $facility->id,
            'assessment_type' => 'baseline',
            'assessment_date' => now(),
        ]);

        Livewire::test(ListAssessments::class)
            ->callTableAction('manage_team', $assessment, data: [
                'member_ids' => [$invitee->id],
            ]);

        $this->assertTrue($assessment->fresh()->isTeamMember($invitee->id));
    }

    public function test_manage_team_action_is_hidden_for_a_regular_team_member(): void
    {
        $lead = $this->makeAssessor('Lead Assessor');
        $member = $this->makeAssessor('Member Assessor');

        $this->actingAs($lead);
        $facility = Facility::factory()->create();
        $assessment = Assessment::create([
            'facility_id' => $facility->id,
            'assessment_type' => 'baseline',
            'assessment_date' => now(),
        ]);
        $assessment->teamMembers()->attach($member->id, [
            'role' => 'member',
            'added_by' => $lead->id,
            'added_at' => now(),
        ]);

        $this->actingAs($member);

        Livewire::test(ListAssessments::class)
            ->assertTableActionHidden('manage_team', $assessment);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=AssessmentManageTeamActionTest`
Expected: FAIL — `manage_team` action doesn't exist on the table yet (Livewire throws an "action not found" style error).

- [ ] **Step 3: Add the team column**

In `app/Filament/Resources/AssessmentResource/Pages/ListAssessments.php`, inside the `->columns([...])` array, insert after the `assessor.name` column (after line 154, before the `created_at` column):

```php
                Tables\Columns\TextColumn::make('assessor.name')
                    ->label('Assessor')
                    ->searchable(),
                Tables\Columns\TextColumn::make('team_members_count')
                    ->label('Team')
                    ->counts('teamMembers')
                    ->badge()
                    ->color('primary')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
```

- [ ] **Step 4: Add the "Manage Team" action**

In the same file, inside the `ActionGroup::make([...])` (starting line 237), insert a new action between the `view_summary` action (ends line 250) and the `export_csv` action (starts line 252):

```php
                    // View Summary Action
                    Tables\Actions\Action::make('view_summary')
                        ->label('View Summary')
                        ->icon('heroicon-o-eye')
                        ->color('info')
                        ->url(fn ($record) => AssessmentResource::getUrl('summary', ['record' => $record])),
                    // Manage Team Action
                    Tables\Actions\Action::make('manage_team')
                        ->label('Manage Team')
                        ->icon('heroicon-o-user-group')
                        ->color('primary')
                        ->visible(fn ($record) => $record->canManageTeam(auth()->id()))
                        ->form([
                            \Filament\Forms\Components\Section::make('Team Members')
                                ->description('People who can currently view and work on this assessment.')
                                ->schema([
                                    \Filament\Forms\Components\Placeholder::make('current_team')
                                        ->hiddenLabel()
                                        ->content(function ($record): \Illuminate\Support\HtmlString {
                                            $members = app(\App\Services\AssessmentTeamService::class)
                                                ->getTeamForDisplay($record)
                                                ->map(function ($member): string {
                                                    $role = $member->pivot->role === 'team_lead' ? 'Lead Assessor' : 'Team Member';
                                                    $name = e($member->name);
                                                    $email = e($member->email ?? 'No email');

                                                    return "<div class=\"flex items-center justify-between py-2\"><div><p class=\"font-medium text-gray-950 dark:text-white\">{$name}</p><p class=\"text-xs text-gray-500\">{$email}</p></div><span class=\"text-xs font-medium text-primary-600\">{$role}</span></div>";
                                                })
                                                ->implode('<hr class="border-gray-200 dark:border-gray-700">');

                                            return new \Illuminate\Support\HtmlString($members ?: '<p class="text-sm text-gray-500">No team members yet.</p>');
                                        }),
                                ])
                                ->columnSpanFull(),
                            \Filament\Forms\Components\Placeholder::make('other_potential_members_heading')
                                ->hiddenLabel()
                                ->content(new \Illuminate\Support\HtmlString('<hr class="border-gray-200 dark:border-gray-700 mb-4"><h3 class="text-base font-semibold text-gray-950 dark:text-white">Other Potential Members</h3><p class="text-sm text-gray-500">Select assessors to add to this assessment team.</p>'))
                                ->columnSpanFull(),
                            \Filament\Forms\Components\CheckboxList::make('member_ids')
                                ->label('Available assessors')
                                ->options(fn ($record): array => app(\App\Services\AssessmentTeamService::class)
                                    ->getEligibleUsers($record)
                                    ->mapWithKeys(fn ($user) => [$user->id => "{$user->name} — {$user->email}"])
                                    ->all())
                                ->searchable()
                                ->columns(1)
                                ->helperText('Invited assessors can open and work on this assessment.'),
                        ])
                        ->modalHeading('Assessment Team')
                        ->modalDescription(fn ($record) => "This assessment currently has {$record->teamMembers()->count()} team member(s), including its lead.")
                        ->modalSubmitActionLabel('Invite Selected Assessors')
                        ->action(function ($record, array $data): void {
                            $memberIds = $data['member_ids'] ?? [];

                            if ($memberIds === []) {
                                return;
                            }

                            app(\App\Services\AssessmentTeamService::class)
                                ->addMembers($record, $memberIds, auth()->id());

                            \Filament\Notifications\Notification::make()
                                ->title(count($memberIds).' assessor(s) added to the team')
                                ->success()
                                ->send();
                        }),
                    // Export Single CSV Action
                    Tables\Actions\Action::make('export_csv')
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --filter=AssessmentManageTeamActionTest`
Expected: PASS (both tests)

- [ ] **Step 6: Run the wider assessment suite to check for regressions**

Run: `php artisan test --filter=Assessment`
Expected: PASS — in particular `AssessmentDownloadActionTest` and `AssessmentTableFiltersTest`, which also exercise `ListAssessments`' table.

- [ ] **Step 7: Commit**

```bash
git add app/Filament/Resources/AssessmentResource/Pages/ListAssessments.php tests/Feature/AssessmentManageTeamActionTest.php
git commit -m "feat: add Manage Team action and team count column to the assessments list"
```

---

## Task 5: Mobile API — `AssessmentTeamController` + routes

**Files:**
- Create: `app/Http/Controllers/Api/AssessmentTeamController.php`
- Modify: `routes/api.php:1-14` (imports), `routes/api.php:102-105` (route group)
- Test: `tests/Feature/Api/AssessmentTeamApiTest.php` (new)

**Interfaces:**
- Consumes: `Assessment::canManageTeam()` (Task 1), `AssessmentTeamService::getEligibleUsers()`, `getTeamForDisplay()`, `addMembers()` (existing + Task 2).
- Produces: `GET /api/v1/assessments/{assessment}/team`, `GET /api/v1/assessments/{assessment}/team/eligible`, `POST /api/v1/assessments/{assessment}/team`.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Api/AssessmentTeamApiTest.php`:

```php
<?php

namespace Tests\Feature\Api;

use App\Models\Assessment;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AssessmentTeamApiTest extends TestCase
{
    use RefreshDatabase;

    private function tokenFor(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    private function makeAssessor(string $name): User
    {
        $user = User::factory()->create(['name' => $name]);
        Role::firstOrCreate(['name' => 'assessor', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view_assessment', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'update_assessment', 'guard_name' => 'web']);
        $user->assignRole('assessor');
        $user->givePermissionTo(['view_assessment', 'update_assessment']);

        return $user;
    }

    private function createAssessmentAs(User $assessor): Assessment
    {
        $this->actingAs($assessor);
        $facility = Facility::factory()->create();

        return Assessment::create([
            'facility_id' => $facility->id,
            'assessment_type' => 'baseline',
            'assessment_date' => now(),
        ]);
    }

    public function test_show_returns_the_lead_and_team_members(): void
    {
        $lead = $this->makeAssessor('Lead Assessor');
        $assessment = $this->createAssessmentAs($lead);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($lead))
            ->getJson("/api/v1/assessments/{$assessment->id}/team");

        $response->assertSuccessful();
        $response->assertJsonPath('lead_assessor.id', $lead->id);
        $response->assertJsonPath('can_manage_team', true);
    }

    public function test_eligible_rejects_a_non_manager(): void
    {
        $lead = $this->makeAssessor('Lead Assessor');
        $assessment = $this->createAssessmentAs($lead);
        $outsider = $this->makeAssessor('Outsider Assessor');

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($outsider))
            ->getJson("/api/v1/assessments/{$assessment->id}/team/eligible");

        $response->assertForbidden();
    }

    public function test_store_adds_team_members_and_returns_the_updated_payload(): void
    {
        $lead = $this->makeAssessor('Lead Assessor');
        $assessment = $this->createAssessmentAs($lead);
        $invitee = $this->makeAssessor('Invitee Assessor');

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($lead))
            ->postJson("/api/v1/assessments/{$assessment->id}/team", [
                'member_ids' => [$invitee->id],
            ]);

        $response->assertSuccessful();
        $this->assertTrue($assessment->fresh()->isTeamMember($invitee->id));
        $response->assertJsonPath('team_members.0.id', $invitee->id);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=AssessmentTeamApiTest`
Expected: FAIL — 404s, since the routes don't exist yet.

- [ ] **Step 3: Create the controller**

Create `app/Http/Controllers/Api/AssessmentTeamController.php` (matching the same-line-brace style already used by every other file in `app/Http/Controllers/Api`):

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Services\AssessmentTeamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssessmentTeamController extends Controller {

    public function show(Request $request, Assessment $assessment, AssessmentTeamService $teamService): JsonResponse {
        $this->authorize('view', $assessment);

        return response()->json($this->teamPayload($assessment, $teamService, $request->user()->id));
    }

    public function eligible(Request $request, Assessment $assessment, AssessmentTeamService $teamService): JsonResponse {
        abort_unless($assessment->canManageTeam($request->user()->id), 403);

        return response()->json(['data' => $teamService->getEligibleUsers($assessment)->map(fn ($user) => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'facility_name' => $user->facility?->name,
        ])->values()]);
    }

    public function store(Request $request, Assessment $assessment, AssessmentTeamService $teamService): JsonResponse {
        abort_unless($assessment->canManageTeam($request->user()->id), 403);

        $data = $request->validate([
            'member_ids' => ['required', 'array', 'min:1'],
            'member_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $teamService->addMembers($assessment, $data['member_ids'], $request->user()->id);

        return response()->json([
            'message' => 'Team members added successfully.',
            ...$this->teamPayload($assessment->fresh(), $teamService, $request->user()->id),
        ]);
    }

    private function teamPayload(Assessment $assessment, AssessmentTeamService $teamService, int $userId): array {
        $members = $teamService->getTeamForDisplay($assessment)->map(fn ($member) => [
            'id' => $member->id,
            'name' => $member->name,
            'email' => $member->email,
            'role' => $member->pivot->role,
        ])->values();

        return [
            'lead_assessor' => $members->firstWhere('role', 'team_lead') ?? [
                'id' => $assessment->assessor_id,
                'name' => $assessment->assessor_name,
                'email' => $assessment->assessor_contact,
                'role' => 'team_lead',
            ],
            'team_members' => $members->where('role', 'member')->values(),
            'can_manage_team' => $assessment->canManageTeam($userId),
        ];
    }
}
```

- [ ] **Step 4: Register the routes**

In `routes/api.php`, add the import after the existing `AssessmentSectionController` import (line 5):

```php
use App\Http\Controllers\Api\AssessmentSectionController;
use App\Http\Controllers\Api\AssessmentTeamController;
```

Then, inside the `assessments` route group, insert after the section-progress route (after line 105, before the `// ── Responses ──` comment on line 106):

```php
            // ── Section progress ──────────────────────────────────────────────
            Route::put('{assessment}/sections/{sectionCode}/progress',
                [AssessmentController::class, 'updateSectionProgress'])->name('section-progress');

            // ── Team ──────────────────────────────────────────────────────────
            Route::get('{assessment}/team', [AssessmentTeamController::class, 'show'])->name('team.show');
            Route::get('{assessment}/team/eligible', [AssessmentTeamController::class, 'eligible'])->name('team.eligible');
            Route::post('{assessment}/team', [AssessmentTeamController::class, 'store'])->name('team.store');

            // ── Responses ─────────────────────────────────────────────────────
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --filter=AssessmentTeamApiTest`
Expected: PASS (all 3 tests)

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Api/AssessmentTeamController.php routes/api.php tests/Feature/Api/AssessmentTeamApiTest.php
git commit -m "feat: add mobile API endpoints for assessment team management"
```

---

## Task 6: Mobile API — team-aware scoping and eager loading on `AssessmentController`

**Files:**
- Modify: `app/Http/Controllers/Api/AssessmentController.php:30-45` (index), `:99-138` (store), `:143-155` (show), `:163-176` (update), `:203-241` (submit)
- Test: `tests/Feature/Api/AssessmentApiTeamScopingTest.php` (new)

**Interfaces:**
- Consumes: `Assessment::teamMembers()` (Task 1).
- Produces: `AssessmentController::index()` now includes assessments where the user is `created_by` or a team member (not just `assessor_id`), and every response method eager-loads `teamMembers` so `AssessmentResource` (Task 7) can render team fields.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Api/AssessmentApiTeamScopingTest.php`:

```php
<?php

namespace Tests\Feature\Api;

use App\Models\Assessment;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AssessmentApiTeamScopingTest extends TestCase
{
    use RefreshDatabase;

    private function tokenFor(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    private function makeAssessor(string $name): User
    {
        $user = User::factory()->create(['name' => $name]);
        Role::firstOrCreate(['name' => 'assessor', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view_assessment', 'guard_name' => 'web']);
        $user->assignRole('assessor');
        $user->givePermissionTo('view_assessment');

        return $user;
    }

    private function createAssessmentAs(User $assessor): Assessment
    {
        $this->actingAs($assessor);
        $facility = Facility::factory()->create();

        return Assessment::create([
            'facility_id' => $facility->id,
            'assessment_type' => 'baseline',
            'assessment_date' => now(),
        ]);
    }

    public function test_index_includes_assessments_the_user_was_added_to_as_a_team_member(): void
    {
        $lead = $this->makeAssessor('Lead Assessor');
        $member = $this->makeAssessor('Member Assessor');
        $assessment = $this->createAssessmentAs($lead);
        $assessment->teamMembers()->attach($member->id, [
            'role' => 'member',
            'added_by' => $lead->id,
            'added_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($member))
            ->getJson('/api/v1/assessments');

        $response->assertSuccessful();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($assessment->id, $ids);
    }

    public function test_index_still_excludes_assessments_the_user_has_no_relationship_to(): void
    {
        $lead = $this->makeAssessor('Lead Assessor');
        $outsider = $this->makeAssessor('Outsider Assessor');
        $assessment = $this->createAssessmentAs($lead);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($outsider))
            ->getJson('/api/v1/assessments');

        $response->assertSuccessful();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertNotContains($assessment->id, $ids);
    }
}
```

- [ ] **Step 2: Run tests to verify the first one fails**

Run: `php artisan test --filter=AssessmentApiTeamScopingTest`
Expected: `test_index_includes_assessments_the_user_was_added_to_as_a_team_member` FAILS (member isn't in the response yet); `test_index_still_excludes_assessments_the_user_has_no_relationship_to` PASSES already.

- [ ] **Step 3: Update `index()` scoping and eager-loading**

In `app/Http/Controllers/Api/AssessmentController.php`, replace lines 30-45:

```php
    public function index(Request $request): JsonResponse {
        $user = $request->user();

        $query = Assessment::with([
                    'facility.subcounty.county',
                    'sectionScores.section',
                    'teamMembers',
                ])
                ->latest();

        if ($user->hasRole('super_admin')) {
            // Super admin sees everything including soft-deleted
            $query->withTrashed();
        } elseif (!$user->isAboveSite() && !$user->hasRole('admin')) {
            // Assessors see records they created and assessments shared with
            // them by a team lead.
            $query->where(function ($query) use ($user) {
                $query->where('assessor_id', $user->id)
                    ->orWhere('created_by', $user->id)
                    ->orWhereHas('teamMembers', fn ($team) => $team->where('users.id', $user->id));
            });
        }
        // isAboveSite()/admin roles see all non-deleted
```

- [ ] **Step 4: Eager-load `teamMembers` in `store()`, `show()`, `update()`, `submit()`**

In the same file:

`store()` (line 136), change:
```php
                    'assessment' => new AssessmentResource($assessment->load('facility.subcounty.county')),
```
to:
```php
                    'assessment' => new AssessmentResource($assessment->load(['facility.subcounty.county', 'teamMembers'])),
```

`show()` (lines 146-150), change:
```php
        $assessment->load([
            'facility.subcounty.county',
            'sectionScores.section',
            'questionResponses.question.section',
        ]);
```
to:
```php
        $assessment->load([
            'facility.subcounty.county',
            'sectionScores.section',
            'questionResponses.question.section',
            'teamMembers',
        ]);
```

`update()` (line 174), change:
```php
                    'assessment' => new AssessmentResource($assessment->fresh('facility.subcounty.county')),
```
to:
```php
                    'assessment' => new AssessmentResource($assessment->fresh(['facility.subcounty.county', 'teamMembers'])),
```

`submit()` (lines 237-239), change:
```php
                    'assessment' => new AssessmentResource(
                            $assessment->fresh(['facility.subcounty.county', 'sectionScores.section'])
                    ),
```
to:
```php
                    'assessment' => new AssessmentResource(
                            $assessment->fresh(['facility.subcounty.county', 'sectionScores.section', 'teamMembers'])
                    ),
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --filter=AssessmentApiTeamScopingTest`
Expected: PASS (both tests)

- [ ] **Step 6: Run the wider API suite to check for regressions**

Run: `php artisan test --filter=Api`
Expected: PASS — `AssessmentSectionApiTest` and `AssessmentTeamApiTest` (Task 5) unaffected.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Api/AssessmentController.php tests/Feature/Api/AssessmentApiTeamScopingTest.php
git commit -m "feat: include team-shared assessments in the mobile API index and eager-load team data"
```

---

## Task 7: Mobile API resource — team fields in the JSON payload

**Files:**
- Modify: `app/Http/Resources/Api/AssessmentResource.php`
- Test: `tests/Feature/Api/AssessmentApiTeamScopingTest.php` (append)

**Interfaces:**
- Consumes: `teamMembers` relation loaded by Task 6, `Assessment::canManageTeam()` (Task 1).
- Produces: the JSON payload returned by every `assessments` endpoint now includes `team`, `lead_assessor`, `team_members`, `can_manage_team` whenever `teamMembers` is loaded.

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/Api/AssessmentApiTeamScopingTest.php`:

```php
    public function test_show_payload_includes_team_and_lead_assessor_fields(): void
    {
        $lead = $this->makeAssessor('Lead Assessor');
        $member = $this->makeAssessor('Member Assessor');
        $assessment = $this->createAssessmentAs($lead);
        $assessment->teamMembers()->attach($member->id, [
            'role' => 'member',
            'added_by' => $lead->id,
            'added_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($lead))
            ->getJson("/api/v1/assessments/{$assessment->id}");

        $response->assertSuccessful();
        $response->assertJsonPath('data.lead_assessor.id', $lead->id);
        $response->assertJsonPath('data.can_manage_team', true);
        $memberIds = collect($response->json('data.team_members'))->pluck('id')->all();
        $this->assertContains($member->id, $memberIds);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=test_show_payload_includes_team_and_lead_assessor_fields`
Expected: FAIL — `lead_assessor`, `can_manage_team`, `team_members` keys don't exist in the response yet.

- [ ] **Step 3: Add the team fields to the resource**

In `app/Http/Resources/Api/AssessmentResource.php`, replace the `toArray()` method:

```php
    public function toArray($request): array {
        return [
            'id' => $this->id,
            'facility_id' => $this->facility_id,
            'facility_name' => $this->facility?->name,
            'mfl_code' => $this->facility?->mfl_code,
            'county' => $this->facility?->subcounty?->county?->name,
            'subcounty' => $this->facility?->subcounty?->name,
            'assessment_type' => $this->assessment_type,
            'assessment_date' => $this->assessment_date instanceof \Carbon\Carbon ? $this->assessment_date->toDateString() : $this->assessment_date,
            'assessor_name' => $this->assessor_name,
            'assessor_contact' => $this->assessor_contact,
            'status' => $this->status,
            'section_progress' => $this->section_progress ?? [],
            'overall_score' => $this->overall_score,
            'overall_percentage' => $this->overall_percentage,
            'overall_grade' => $this->overall_grade,
            'completed_at' => $this->completed_at instanceof \Carbon\Carbon ? $this->completed_at->toDateString() : $this->completed_at,
            'created_at'  => $this->created_at?->toIso8601String(),
            'is_trashed'  => $this->deleted_at !== null,
            'section_scores' => $this->whenLoaded('sectionScores', fn() =>
                    $this->sectionScores->mapWithKeys(fn($s) => [
                        $s->section->code => [
                            'percentage' => $s->percentage,
                            'grade' => $s->grade,
                            'answered_questions' => $s->answered_questions,
                            'total_questions' => $s->total_questions,
                            'skipped_questions' => $s->skipped_questions,
                        ],
                            ])
            ),
            'team' => $this->whenLoaded('teamMembers', fn () => $this->teamMembers->map(fn ($member) => [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'role' => $member->pivot->role,
            ])->values()),
            'lead_assessor' => $this->when($this->relationLoaded('teamMembers'), function () {
                $lead = $this->teamMembers->first(fn ($member) => $member->pivot->role === 'team_lead');

                return [
                    'id' => $lead?->id ?? $this->assessor_id,
                    'name' => $lead?->name ?? $this->assessor_name,
                    'email' => $lead?->email ?? $this->assessor_contact,
                    'role' => 'team_lead',
                ];
            }),
            'team_members' => $this->whenLoaded('teamMembers', fn () => $this->teamMembers
                ->filter(fn ($member) => $member->pivot->role === 'member')
                ->map(fn ($member) => [
                    'id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                    'role' => 'member',
                ])->values()),
            'can_manage_team' => $this->when($request->user(), fn () => $this->canManageTeam($request->user()->id)),
        ];
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=AssessmentApiTeamScopingTest`
Expected: PASS (all 3 tests in this file)

- [ ] **Step 5: Run the full test suite**

Run: `php artisan test`
Expected: PASS across the board — this is the final task, so this is the full-suite regression check for the whole feature.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Resources/Api/AssessmentResource.php tests/Feature/Api/AssessmentApiTeamScopingTest.php
git commit -m "feat: expose team, lead_assessor, team_members, and can_manage_team in the mobile API"
```

---

## Post-implementation manual check

Not automatable without a running mobile client, so do this by hand once all 7 tasks are merged:

1. Log into `/admin` as an existing assessor, open an assessment you created, click **Manage Team** in the row actions, and confirm you can see yourself listed as Lead Assessor and invite another assessor.
2. Log in as the invited assessor and confirm the assessment now appears in their assessments list and they can open its dashboard.
3. Log in as a third, uninvited assessor and confirm the assessment does not appear for them.
