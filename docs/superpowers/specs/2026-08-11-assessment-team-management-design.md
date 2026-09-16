# Assessment Team Management — Port from v1

**Date:** 2026-08-11
**Status:** Approved

## Context

The reference repo `github.com/alpho07/mnch-mentorship` (v1) has a working "assessment team management" feature: an assessor can invite other assessors onto an assessment so they can jointly view/work on it, with one designated team lead. This app (v2, `mnch-mentorship.v2`) already carries the schema for it — migration `2026_03_03_082118_assessment_team_management.php` has run, and `AssessmentTeamMember` (pivot model) and `AssessmentTeamService` already exist and are byte-for-byte close to v1 — but `AssessmentTeamService` calls `Assessment` model methods (`canManageTeam()`, `isTeamMember()`, `teamMembers()`, `lock()`, etc.) that don't exist here yet. Nothing in the UI or API currently exposes the feature. It's dead code waiting to be wired up.

`ManageTeamAction.php` and `HasTeamAuthorization.php` under `AssessmentResource/Actions` and `AssessmentResource/Traits` exist in both repos but are unused in both — leftover files, not part of this port.

## Goal

Port the working parts of v1's team management into v2 without regressing any v2-specific work already in place (the `assessment_type_id`/`assessmentType()` relation, the current `getCompletionPercentageAttribute()` that uses it, `excluded_cadre_ids`, the current admin-role set used for row scoping, and the current Filament table's styling/structure in `ListAssessments.php`). This is a targeted addition, not a file replacement.

v1 also added `is_locked`/`locked_at`/`locked_by` columns and model methods (`lock()`, `unlock()`, `canToggleLock()`, `isLocked()`) via the same migration, but never wired a lock/unlock button into any UI. We port the model methods (cheap, matches the already-migrated schema, and `AssessmentTeamService` calls them) but do not build new lock/unlock UI — there's no reference implementation of that UI to port.

## Changes

### 1. `app/Models/Assessment.php`
Add, without touching existing fields/relations/casts:
- `use BelongsToMany` relations: `teamMembers()` (`belongsToMany(User::class, 'assessment_team')->using(AssessmentTeamMember::class)->withPivot(['role','added_by','added_at'])->withTimestamps()`), `teamLeads()` (`teamMembers()->wherePivot('role','team_lead')`), `teamMembersOnly()`, `locker()` (`belongsTo(User::class, 'locked_by')`).
- A `static::created()` boot hook that attaches the assessor as `team_lead` on creation (mirrors the existing `static::creating` hook already there; new hook, doesn't replace it).
- Methods: `isTeamMember(int $userId): bool`, `isTeamLead(int $userId): bool`, `isLocked(): bool`, `lock(int $userId): void`, `unlock(): void`, `canToggleLock(?int $userId): bool` (delegates to `canManageTeam`).
- `canManageTeam(?int $userId): bool` — **adapted, not copied**: uses this app's existing admin role set (`super_admin`, `admin`, `division` — the same set `AssessmentResource::getEloquentQuery()` already treats as "sees everything"), not v1's `isAboveSite()`/`hasRole('admin')` check. Falls back to: current team lead → true; no team lead yet (legacy assessment) and caller is the original `assessor_id`/`created_by` → true (one-time claim); otherwise false.

### 2. `app/Services/AssessmentTeamService.php`
Add the one missing private method, `ensureLegacyCreatorIsTeamLead(Assessment $assessment, int $actorId): void`, called at the top of `addMember()` and `addMembers()` (both already assert `canManageTeam` first) — gives assessments created before this feature existed a team-lead pivot row the first time their team is touched, without a migration backfill.

### 3. `app/Filament/Resources/AssessmentResource.php`
`getEloquentQuery()`: extend the non-admin branch from `where('assessor_id', $user->id)` to also match `created_by` and `whereHas('teamMembers', ...)`, so team members can see (and, via existing edit/dashboard routes that resolve through this query, work on) assessments they've been invited to. Admin-role branch is untouched.

### 4. `app/Filament/Resources/AssessmentResource/Pages/ListAssessments.php`
- Add a `team_members_count` badge column (`->counts('teamMembers')`) next to the existing columns, following this file's existing column style (not v1's — keep the current column definitions as-is, just insert one more).
- Add a **Manage Team** action inside the existing `ActionGroup` (alongside `dashboard`/`view_summary`/etc.), visible only when `$record->canManageTeam(auth()->id())`. Modal form: read-only list of current team (name, email, Lead/Member badge) via `AssessmentTeamService::getTeamForDisplay()`, plus a `CheckboxList` of eligible assessors via `AssessmentTeamService::getEligibleUsers()`. Submit calls `AssessmentTeamService::addMembers()`. Ported from v1's version of this action, restyled to match this file's existing action conventions (arrow-fn closures, `Filament\Notifications\Notification`).

### 5. `app/Http/Controllers/Api/AssessmentTeamController.php` (new)
Ported as-is from v1: `show` (team payload for an assessment, policy-gated on `view`), `eligible` (list of assessors not yet on the team, gated on `canManageTeam`), `store` (add members, gated on `canManageTeam`).

### 6. `routes/api.php`
Inside the existing `assessments` route group, add:
```php
Route::get('{assessment}/team', [AssessmentTeamController::class, 'show'])->name('team.show');
Route::get('{assessment}/team/eligible', [AssessmentTeamController::class, 'eligible'])->name('team.eligible');
Route::post('{assessment}/team', [AssessmentTeamController::class, 'store'])->name('team.store');
```

### 7. `app/Http/Controllers/Api/AssessmentController.php`
- `index()`: eager-load `teamMembers`; extend the non-`isAboveSite()` scoping branch the same way as change #3 (assessor_id OR created_by OR team member), and also let `admin` role bypass it (matches v1; consistent with the roles already used in #3).
- `store()`, `show()`, `update()`, `submit()`: add `teamMembers` to the existing eager-load / `fresh()` calls so the API resource below has the relation loaded.

### 8. `app/Http/Resources/Api/AssessmentResource.php`
Add four `whenLoaded`/`when`-guarded fields to the response array: `team`, `lead_assessor` (falls back to `assessor_id`/`assessor_name`/`assessor_contact` for legacy records with no team yet), `team_members` (role `member` only), `can_manage_team`.

## Out of scope
- Lock/unlock UI (no reference implementation exists to port; model methods are added but unused, matching v1's own state).
- `ManageTeamAction.php` / `HasTeamAuthorization.php` (dead in both repos; not touched).
- `AssessmentPolicy` (identical in both repos already; blanket `view_assessment`/`update_assessment` gate, unrelated to per-record team scoping).

## Testing
- Manual: create an assessment as assessor A, use "Manage Team" to add assessor B, confirm B now sees it in their Filament list and can open the dashboard/edit pages; confirm a third assessor C still cannot see it.
- Manual: hit the three new API routes with a mobile-style bearer token as both the lead and a non-member, confirming `eligible`/`store` 403 for non-members.
- `php artisan test --filter=Assessment` if an existing suite covers this model/controller (check first; add none new unless the repo already has a pattern for these).
