# Smart Sync, Data Integrity & User Lookup Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add delta sync across all 4 backend list endpoints, cache the full user table as an email-keyed lookup index, fix queue dependency corruption after ID reconciliation, surface pending-op summaries and inbound-change toasts to users.

**Architecture:** `offline-store.js` gains sync-timestamp and lookup-map helpers plus `patchQueueIds` for safe in-place queue patching. Backend list endpoints gain `?since=` query parameters. `api.service.js` gains `smartSync()` which fans out 4 delta syncs in parallel and fires an `app:sync-complete` event. `sync-queue.js` gains `assertNoTempIds` guard, calls `patchQueueIds` after every ID reconciliation, and exposes `getPendingSummary()`. `SyncIndicator` shows per-type breakdown on tap. `ScopeShell.jsx` shows an inbound-change toast.

**Tech Stack:** React + IndexedDB (offline-store.js) · Laravel 12 PHP (backend controllers) · Capacitor (resume event) · CustomEvent API (cross-component messaging)

---

## File Change Map

| File | Role |
|---|---|
| `src/services/offline-store.js` | 6 new helpers: getSyncedAt, setSyncedAt, getUserLookupMap, saveUserLookupMap, mergeUserLookupMap, patchQueueIds |
| `src/services/sync-queue.js` | assertNoTempIds guard + patchQueueIds calls after each reconciliation + conflict saving + getPendingSummary |
| `app/Http/Controllers/Api/AssessmentController.php` | ?since= filter with is_trashed delta response |
| `app/Http/Controllers/Api/MentorshipController.php` | ?since= filter |
| `app/Http/Controllers/Api/GlobalTrainingController.php` | ?since= filter |
| `app/Http/Controllers/Api/LookupController.php` | New userLookupIndex() method |
| `routes/api.php` | Register GET /api/v1/users/lookup-index |
| `src/services/api.service.js` | mergeById + syncAssessments + syncTrainings + syncMentorships + syncUserLookupIndex + smartSync + offline userByEmail + validation guards |
| `src/components/sync-indicator.jsx` | Pending summary breakdown on tap |
| `src/components/ScopeShell.jsx` | app:sync-complete toast + smartSync on mount |

---

## Task 1: offline-store.js — Sync timestamps, user lookup map, queue patch

**Files:**
- Modify: `src/services/offline-store.js` (add before `clearAll`)

No test file needed — this is pure IndexedDB CRUD wiring, verified end-to-end in Task 8.

- [ ] **Step 1: Add the 6 new helpers to the `offlineStore` object**

Open `src/services/offline-store.js`. Before the `clearAll` method (currently the last entry in the `offlineStore` object), add these 6 methods:

```js
    // ── Sync timestamps (delta sync) ─────────────────────────────────────────
    getSyncedAt: (resource) => dbGet(STORES.meta, 'synced_at_' + resource),
    setSyncedAt: (resource, ts) => dbPut(STORES.meta, 'synced_at_' + resource, ts),

    // ── User lookup map (email → {id, name, phone}) ──────────────────────────
    getUserLookupMap: () => dbGet(STORES.meta, 'user_lookup_map'),
    saveUserLookupMap: (map) => dbPut(STORES.meta, 'user_lookup_map', map),
    mergeUserLookupMap: async (updates) => {
        const existing = (await dbGet(STORES.meta, 'user_lookup_map')) ?? {};
        for (const [email, record] of Object.entries(updates)) {
            if (record === null) {
                delete existing[email];
            } else {
                existing[email] = record;
            }
        }
        await dbPut(STORES.meta, 'user_lookup_map', existing);
    },

    // ── Queue dependency patching ─────────────────────────────────────────────
    // Replaces all occurrences of tempId with realId across every queued op.
    // Uses in-place dbPut (not clear+rewrite) to avoid losing ops under concurrent writes.
    patchQueueIds: async (tempId, realId) => {
        const queue = await dbGetAll(STORES.syncQueue);
        for (const entry of queue) {
            const json = JSON.stringify(entry);
            if (!json.includes(String(tempId))) continue;
            const patched = JSON.parse(json.replaceAll(String(tempId), String(realId)));
            await dbPut(STORES.syncQueue, patched.id, patched);
        }
    },
```

- [ ] **Step 2: Verify the file still exports correctly**

The file must still end with `export default offlineStore;`. Confirm `clearAll` is still the last method before the closing `};`.

- [ ] **Step 3: Commit**

```bash
git add public/m-assessment-app/src/services/offline-store.js
git commit -m "feat(offline-store): add sync timestamps, user lookup map, and patchQueueIds"
```

---

## Task 2: sync-queue.js — Integrity layer (assertNoTempIds, patchQueueIds, conflict saving, getPendingSummary)

**Files:**
- Modify: `src/services/sync-queue.js`

- [ ] **Step 1: Add `assertNoTempIds` function after the `enqueue` function definition**

Find the line `async function flush() {` (around line 86). Insert this new function just before it:

```js
// Guard: reject any op that still carries an unresolved local_* ID.
// Throws with status 400 so flush() discards the op rather than sending bad data.
function assertNoTempIds(op) {
    const json = JSON.stringify(op);
    const match = json.match(/local_[a-z]+_\d+/);
    if (match) {
        console.error(`[SyncQueue] Op ${op.type} contains unresolved temp ID: ${match[0]}`);
        throw Object.assign(new Error('Unresolved temp ID'), { status: 400, _tempIdGuard: true });
    }
}
```

- [ ] **Step 2: Call `assertNoTempIds` at the top of `executeOp`**

Find `async function executeOp(rawApi, op) {` (around line 155). Change the first line of the function body from `switch (op.type) {` to:

```js
async function executeOp(rawApi, op) {
    assertNoTempIds(op);
    switch (op.type) {
```

- [ ] **Step 3: Extend `flush()` 4xx discard to save conflicts**

Find the 4xx handler in `flush()`:

```js
if (e.status && e.status >= 400 && e.status < 500 && e.status !== 401) {
    console.warn(`[SyncQueue] Discarding op ${op.id} due to ${e.status}`);
    await offlineStore.removeFromQueue(op.id);
    await refreshCount();
    continue;
}
```

Replace it with:

```js
if (e.status && e.status >= 400 && e.status < 500 && e.status !== 401) {
    if (!e._tempIdGuard) {
        // Save to conflict store so no data silently disappears
        await offlineStore.saveConflict({
            id: 'conflict_' + Date.now() + '_' + Math.random().toString(36).slice(2, 6),
            op_type: op.type,
            payload: op,
            error: e.message ?? `HTTP ${e.status}`,
            created_at: new Date().toISOString(),
            resolved: false,
        });
    }
    console.warn(`[SyncQueue] Discarding op ${op.id} due to ${e.status}`);
    await offlineStore.removeFromQueue(op.id);
    await refreshCount();
    continue;
}
```

(`_tempIdGuard` ops are already logged via `console.error` in `assertNoTempIds` — no duplicate conflict needed.)

- [ ] **Step 4: Add `patchQueueIds` call after `assessments.create` reconciliation**

Inside the `case 'assessments.create':` block, the `migrateId` helper currently does:

```js
const migrateId = async (fromId, toId) => {
    await offlineStore.copyAssessmentData(fromId, toId);
    await offlineStore.deleteAssessment(fromId);
    window.dispatchEvent(new CustomEvent("assessment:id-resolved", {
        detail: { tempId: fromId, realId: toId },
    }));
};
```

Add `patchQueueIds` as the FIRST step so downstream ops are patched before the event fires:

```js
const migrateId = async (fromId, toId) => {
    await offlineStore.patchQueueIds(fromId, toId);
    await offlineStore.copyAssessmentData(fromId, toId);
    await offlineStore.deleteAssessment(fromId);
    window.dispatchEvent(new CustomEvent("assessment:id-resolved", {
        detail: { tempId: fromId, realId: toId },
    }));
};
```

- [ ] **Step 5: Add `patchQueueIds` call after `mentorships.create` reconciliation**

Inside `case 'mentorships.create':`, the `migrateId` helper currently is:

```js
const migrateId = async (fromId, toId) => {
    const existing = await offlineStore.getMentorship(fromId);
    if (existing) {
        await offlineStore.saveMentorship({ ...existing, id: toId, _isOffline: false });
        await offlineStore.deleteMentorship(fromId);
    }
    window.dispatchEvent(new CustomEvent('mentorship:id-resolved', {
        detail: { tempId: fromId, realId: toId },
    }));
};
```

Add `patchQueueIds` first:

```js
const migrateId = async (fromId, toId) => {
    await offlineStore.patchQueueIds(fromId, toId);
    const existing = await offlineStore.getMentorship(fromId);
    if (existing) {
        await offlineStore.saveMentorship({ ...existing, id: toId, _isOffline: false });
        await offlineStore.deleteMentorship(fromId);
    }
    window.dispatchEvent(new CustomEvent('mentorship:id-resolved', {
        detail: { tempId: fromId, realId: toId },
    }));
};
```

- [ ] **Step 6: Add `patchQueueIds` call after `mentorships.createClass` reconciliation**

Inside `case 'mentorships.createClass':`, the `migrateClassId` helper currently is:

```js
const migrateClassId = async (fromId, toId, trainingId) => {
    const list = (await offlineStore.getMentorshipClasses(trainingId)) ?? [];
    const idx = list.findIndex(c => c.id === fromId);
    if (idx !== -1) {
        list[idx] = { ...list[idx], id: toId, _isOffline: false };
        await offlineStore.saveMentorshipClasses(trainingId, list);
    }
    window.dispatchEvent(new CustomEvent('mentorship:classId-resolved', {
        detail: { tempId: fromId, realId: toId },
    }));
};
```

Add `patchQueueIds` first:

```js
const migrateClassId = async (fromId, toId, trainingId) => {
    await offlineStore.patchQueueIds(fromId, toId);
    const list = (await offlineStore.getMentorshipClasses(trainingId)) ?? [];
    const idx = list.findIndex(c => c.id === fromId);
    if (idx !== -1) {
        list[idx] = { ...list[idx], id: toId, _isOffline: false };
        await offlineStore.saveMentorshipClasses(trainingId, list);
    }
    window.dispatchEvent(new CustomEvent('mentorship:classId-resolved', {
        detail: { tempId: fromId, realId: toId },
    }));
};
```

Also add conflict saving to the 4xx path (currently it silently removes the provisional):

```js
if (e.status >= 400 && e.status < 500) {
    await offlineStore.saveConflict({
        id: 'conflict_' + Date.now() + '_' + Math.random().toString(36).slice(2, 6),
        op_type: op.type,
        payload: op,
        error: e.message ?? `HTTP ${e.status}`,
        created_at: new Date().toISOString(),
        resolved: false,
    });
    const list = (await offlineStore.getMentorshipClasses(op.trainingId)) ?? [];
    await offlineStore.saveMentorshipClasses(op.trainingId, list.filter(c => c.id !== op.tempId));
    return null;
}
```

- [ ] **Step 7: Add `patchQueueIds` call after `mentorships.createMentee` reconciliation**

Inside `case 'mentorships.createMentee':`, modify `migrateMenteeId`:

```js
const migrateMenteeId = async (fromId, toId, classId) => {
    await offlineStore.patchQueueIds(fromId, toId);
    const list = (await offlineStore.getParticipants(classId)) ?? [];
    const idx = list.findIndex(p => p.id === fromId);
    if (idx !== -1) {
        list[idx] = { ...list[idx], id: toId, _isOffline: false };
        await offlineStore.saveParticipants(classId, list);
    }
    window.dispatchEvent(new CustomEvent('mentorship:participantId-resolved', {
        detail: { tempId: fromId, realId: toId, classId },
    }));
};
```

Also add conflict saving to its 4xx path (same pattern as createClass above):

```js
if (e.status >= 400 && e.status < 500) {
    await offlineStore.saveConflict({
        id: 'conflict_' + Date.now() + '_' + Math.random().toString(36).slice(2, 6),
        op_type: op.type,
        payload: op,
        error: e.message ?? `HTTP ${e.status}`,
        created_at: new Date().toISOString(),
        resolved: false,
    });
    const list = (await offlineStore.getParticipants(op.classId)) ?? [];
    await offlineStore.saveParticipants(op.classId, list.filter(p => p.id !== op.tempId));
    return null;
}
```

- [ ] **Step 8: Add `patchQueueIds` call after `mentorships.addModule` reconciliation**

Inside `case 'mentorships.addModule':`, modify `migrateModuleId`:

```js
const migrateModuleId = async (fromId, toId, classId) => {
    await offlineStore.patchQueueIds(fromId, toId);
    const list = (await offlineStore.getModuleList(classId)) ?? [];
    const idx = list.findIndex(m => m.id === fromId);
    if (idx !== -1) {
        list[idx] = { ...list[idx], id: toId, _isOffline: false };
        await offlineStore.saveModuleList(classId, list);
    }
    window.dispatchEvent(new CustomEvent('mentorship:moduleId-resolved', {
        detail: { tempId: fromId, realId: toId, classId },
    }));
};
```

Also add conflict saving to its 4xx path (same pattern).

- [ ] **Step 9: Add `patchQueueIds` call after `mentorships.addSession` reconciliation**

Inside `case 'mentorships.addSession':`, modify `migrateSessionId`:

```js
const migrateSessionId = async (fromId, toId, moduleId, realSession) => {
    await offlineStore.patchQueueIds(fromId, toId);
    const list = (await offlineStore.getSessionsByModule(moduleId)) ?? [];
    const idx = list.findIndex(s => s.id === fromId);
    if (idx !== -1) {
        list[idx] = { ...realSession, _isOffline: false };
        await offlineStore.saveSessionsByModule(moduleId, list);
    }
    window.dispatchEvent(new CustomEvent('mentorship:sessionId-resolved', {
        detail: { tempId: fromId, realId: toId, moduleId },
    }));
};
```

Also add conflict saving to its 4xx path (same pattern).

- [ ] **Step 10: Add `getPendingSummary` function and expose it on the public API**

Add this function after `function assertNoTempIds`:

```js
const OP_LABELS = {
    'mentorships.createClass':       'New class',
    'mentorships.createMentee':      'New mentee',
    'mentorships.addModule':         'New module',
    'mentorships.addSession':        'New session',
    'mentorships.updateClass':       'Class edit',
    'mentorships.updateMenteeEmail': 'Mentee update',
    'mentorships.endClass':          'Class completion',
    'mentorships.regenerateToken':   'Link regeneration',
    'mentorships.create':            'New mentorship',
    'mentorships.update':            'Mentorship edit',
    'mentorships.submit':            'Mentorship submit',
    'responses.bulkSave':            'Assessment responses',
    'assessments.create':            'New assessment',
    'assessments.submit':            'Assessment submission',
    'assessments.progress':          'Section progress',
    'humanResources.save':           'HR section',
    'healthProducts.save':           'HP section',
    'report.email':                  'Report email',
    'trainings.enroll':              'Training enrollment',
    'trainings.attendance':          'Training attendance',
};

async function getPendingSummary() {
    const queue = await offlineStore.getQueue();
    const groups = {};
    for (const op of queue) {
        const label = OP_LABELS[op.type] ?? op.type;
        groups[label] = (groups[label] ?? 0) + 1;
    }
    return { total: queue.length, groups };
}
```

Then add `getPendingSummary` to the `syncQueue` public API object:

```js
const syncQueue = {
    enqueue,
    flush,
    refreshCount,
    getPendingSummary,   // <-- add this line

    subscribe: (fn) => { ... },
    getStatus: () => ({ ... }),
    clearAll: async () => { ... },
};
```

- [ ] **Step 11: Commit**

```bash
git add public/m-assessment-app/src/services/sync-queue.js
git commit -m "feat(sync-queue): assertNoTempIds guard, patchQueueIds after reconciliation, getPendingSummary"
```

---

## Task 3: AssessmentController.php — Delta sync via ?since=

**Files:**
- Modify: `app/Http/Controllers/Api/AssessmentController.php`

- [ ] **Step 1: Add the `?since=` early-return branch inside `index()`**

The current `index()` paginates. Delta requests should return all matching records (no pagination) with an `is_trashed` flag. Add the following block immediately after the filter conditions (`status`, `search`, `type`) but BEFORE `$query->paginate(...)`:

```php
// Delta sync: return all records updated after the given timestamp (no pagination)
if ($request->filled('since')) {
    $since = \Carbon\Carbon::parse($request->since);
    // Include soft-deleted so the app can remove them from cache (is_trashed: true)
    $query->withTrashed()->where('updated_at', '>', $since);
    $delta = $query->get();
    $data = $delta->map(function (Assessment $a) {
        return [
            'id'         => $a->id,
            'updated_at' => $a->updated_at?->toIso8601String(),
            'is_trashed' => $a->trashed(),
            // Minimal shape — app merges by id, only needs to know if it changed or was deleted
        ];
    });
    return response()->json(['data' => $data]);
}
```

Note: The delta shape is intentionally minimal (id + updated_at + is_trashed). The app will re-fetch the full `AssessmentResource` shape for records it needs to update in cache. If you later want to return the full shape, replace the map body with `(new AssessmentResource($a))->resolve() + ['is_trashed' => $a->trashed()]`.

Ensure the `Assessment` model is already imported (it is at line 9).

- [ ] **Step 2: Verify the existing paginated path is unaffected**

Check that when `since` is NOT in the request, execution falls through to the existing `$query->paginate(...)` call unchanged.

- [ ] **Step 3: Commit**

```bash
git add app/Http/Controllers/Api/AssessmentController.php
git commit -m "feat(api): add ?since= delta filter to GET /assessments"
```

---

## Task 4: MentorshipController.php — Delta sync via ?since=

**Files:**
- Modify: `app/Http/Controllers/Api/MentorshipController.php`

- [ ] **Step 1: Add the `?since=` filter inside `index()`**

The current `index()` returns all mentorships. Add the since filter after the super_admin/role branching but BEFORE `$mentorships = $query->latest()->get();`:

```php
if ($request->filled('since')) {
    $since = \Carbon\Carbon::parse($request->since);
    $query->withTrashed()->where('updated_at', '>', $since);
}
```

The super_admin branch already calls `withTrashed()` — calling it again is a no-op (Eloquent deduplicates). For non-super_admin roles, this adds both the trashed records AND the `updated_at` filter.

- [ ] **Step 2: Verify `is_trashed` is already in the response shape**

Looking at the `map()` at line 33:
```php
'is_trashed'  => $t->deleted_at !== null,
```
This is already present — no change needed.

- [ ] **Step 3: Commit**

```bash
git add app/Http/Controllers/Api/MentorshipController.php
git commit -m "feat(api): add ?since= delta filter to GET /mentorships"
```

---

## Task 5: GlobalTrainingController.php — Delta sync via ?since=

**Files:**
- Modify: `app/Http/Controllers/Api/GlobalTrainingController.php`

- [ ] **Step 1: Add the `?since=` filter inside `index()`**

Add this before `$trainings = $query->get()`:

```php
if ($request->filled('since')) {
    $since = \Carbon\Carbon::parse($request->since);
    $query->where('updated_at', '>', $since);
}
```

Global trainings are not soft-deleted in the current schema, so `withTrashed()` is not needed. The training's `status` field communicates lifecycle changes.

- [ ] **Step 2: Verify the response shape includes `updated_at`**

The current response map does not include `updated_at`. Add it so the frontend merge rule can compare timestamps:

```php
$trainings = $query->get()->map(fn(Training $t) => [
    'id'            => $t->id,
    'title'         => $t->title,
    'status'        => $t->status,
    'start_date'    => $t->start_date?->toDateString(),
    'end_date'      => $t->end_date?->toDateString(),
    'county'        => $t->county?->name,
    'facility'      => $t->facility?->name,
    'location_type' => $t->location_type,
    'updated_at'    => $t->updated_at?->toIso8601String(),  // <-- add this
]);
```

- [ ] **Step 3: Commit**

```bash
git add app/Http/Controllers/Api/GlobalTrainingController.php
git commit -m "feat(api): add ?since= delta filter to GET /trainings, include updated_at"
```

---

## Task 6: User lookup index endpoint

**Files:**
- Modify: `app/Http/Controllers/Api/LookupController.php`
- Modify: `routes/api.php`

- [ ] **Step 1: Add `userLookupIndex` method to `LookupController`**

Add this method after `userByEmail` in `LookupController.php`:

```php
/**
 * GET /api/v1/users/lookup-index
 *
 * Returns a slim index of all active users for offline email lookups.
 * Supports ?since= for delta updates.
 * Super admin: all users. Others: scoped to their assigned facilities/counties.
 *
 * Shape: { data: [ { id, name, email, phone } ], meta: { generated_at, total } }
 */
public function userLookupIndex(Request $request): JsonResponse
{
    $user = $request->user();

    $query = User::query()->where('status', 'active');

    if (!$user->hasRole('super_admin') && !$user->isAboveSite()) {
        // Scope to users in the same facility/county as the requesting user
        $facilityIds = $user->facilities()->pluck('facilities.id');
        if ($facilityIds->isNotEmpty()) {
            $query->whereIn('facility_id', $facilityIds);
        }
    }

    if ($request->filled('since')) {
        $since = \Carbon\Carbon::parse($request->since);
        // Include inactive users updated after since — is_active: false signals removal
        $query->where(function ($q) use ($since) {
            $q->where('updated_at', '>', $since);
        })->withoutGlobalScopes();
        // Re-apply: show all regardless of active status so app can remove inactive ones
        $query = User::query()
            ->where('updated_at', '>', $since);
        if (!$user->hasRole('super_admin') && !$user->isAboveSite()) {
            $facilityIds = $user->facilities()->pluck('facilities.id');
            if ($facilityIds->isNotEmpty()) {
                $query->whereIn('facility_id', $facilityIds);
            }
        }
    }

    $users = $query->get(['id', 'name', 'first_name', 'last_name', 'email', 'phone', 'status'])
        ->map(fn(User $u) => [
            'id'        => $u->id,
            'name'      => $u->full_name ?? $u->name,
            'email'     => strtolower(trim($u->email ?? '')),
            'phone'     => $u->phone,
            'is_active' => $u->status === 'active',
        ]);

    return response()->json([
        'data' => $users,
        'meta' => [
            'generated_at' => now()->toIso8601String(),
            'total'        => $users->count(),
        ],
    ]);
}
```

- [ ] **Step 2: Register the route in `routes/api.php`**

Find the existing users lookup routes (around line 139-140):

```php
Route::get('users/by-email', [\App\Http\Controllers\Api\LookupController::class, 'userByEmail'])->name('users.by-email');
Route::get('users/search', [\App\Http\Controllers\Api\LookupController::class, 'userSearch'])->name('users.search');
```

Add the new route immediately after:

```php
Route::get('users/lookup-index', [\App\Http\Controllers\Api\LookupController::class, 'userLookupIndex'])->name('users.lookup-index');
```

**Important:** This must come BEFORE any `Route::get('users/{user}', ...)` wildcard route if one exists, to avoid the wildcard swallowing `lookup-index` as a `{user}` segment.

- [ ] **Step 3: Commit**

```bash
git add app/Http/Controllers/Api/LookupController.php routes/api.php
git commit -m "feat(api): add GET /users/lookup-index for offline user email lookup"
```

---

## Task 7: api.service.js — mergeById, syncAssessments, syncTrainings, offline userByEmail, validation guards

**Files:**
- Modify: `src/services/api.service.js`

- [ ] **Step 1: Add `mergeById` utility at the top of the file (after imports)**

Find the top of `api.service.js` where constants and helpers are defined. Add this after the `isNetworkError` function:

```js
/**
 * Merge a delta array into an existing cached array by id.
 * - Records with is_trashed:true are removed from the map.
 * - Existing records with a newer updated_at are not overwritten (protects local edits).
 * - New or updated records are upserted by id.
 */
function mergeById(existing, delta) {
    const map = Object.fromEntries((existing ?? []).map(r => [r.id, r]));
    for (const record of delta) {
        if (record.is_trashed) {
            delete map[record.id];
        } else {
            const cached = map[record.id];
            if (!cached || (record.updated_at ?? '') >= (cached.updated_at ?? '')) {
                map[record.id] = record;
            }
        }
    }
    return Object.values(map);
}
```

- [ ] **Step 2: Add `syncAssessments(since)` function**

Add this after `mergeById`:

```js
async function syncAssessments(since) {
    const url = since ? `/assessments?since=${encodeURIComponent(since)}` : '/assessments';
    const data = await _rawApi._fetch(url);
    const delta = Array.isArray(data?.data) ? data.data : [];
    if (delta.length === 0) return { count: 0 };

    const existing = await offlineStore.getAssessments();
    const merged = mergeById(existing, delta);

    // Remove trashed assessments from store, upsert the rest
    for (const record of delta) {
        if (record.is_trashed) {
            await offlineStore.deleteAssessment(record.id);
        } else {
            const updated = merged.find(r => r.id === record.id);
            if (updated) await offlineStore.saveAssessment(updated);
        }
    }

    return { count: delta.length };
}
```

Note: `_rawApi._fetch` is the low-level fetch wrapper in `api.service.js`. If the internal helper is named differently, use whatever the file uses for a raw authenticated GET. See how `_rawApi.assessments.list` is implemented — use the same fetch pattern.

- [ ] **Step 3: Add `syncTrainings(since)` function**

Add after `syncAssessments`:

```js
async function syncTrainings(since) {
    const url = since ? `/trainings?since=${encodeURIComponent(since)}` : '/trainings';
    const data = await _rawApi._fetch(url);
    const delta = Array.isArray(data?.data) ? data.data : [];
    if (delta.length === 0) return { count: 0 };

    const existing = await offlineStore.getTrainings();
    const merged = mergeById(existing, delta);
    await offlineStore.saveTrainings(merged);

    return { count: delta.length };
}
```

- [ ] **Step 4: Add offline fallback to `lookups.userByEmail`**

Find the existing `lookups.userByEmail` method. It currently just calls `_rawApi.lookups.userByEmail(email)`. Replace it with:

```js
userByEmail: async (email) => {
    try {
        return await _rawApi.lookups.userByEmail(email);
    } catch (e) {
        if (isNetworkError(e)) {
            const map = await offlineStore.getUserLookupMap();
            const key = email.toLowerCase().trim();
            const found = map?.[key];
            if (found) return { data: found };
            return null; // caller shows "will create new user" path
        }
        throw e;
    }
},
```

- [ ] **Step 5: Add validation guards to `mentorships.createMentee`**

Find the `createMentee` method in the `mentorships` section of `api`. At the top of the method, before the try/catch, add:

```js
if (!payload?.email) throw new Error('createMentee: email is required');
if (!classId) throw new Error('createMentee: classId is required');
```

- [ ] **Step 6: Commit**

```bash
git add public/m-assessment-app/src/services/api.service.js
git commit -m "feat(api.service): mergeById util, syncAssessments, syncTrainings, offline userByEmail fallback"
```

---

## Task 8: api.service.js — smartSync (mentorships + user index + orchestration + online wiring)

**Files:**
- Modify: `src/services/api.service.js`

This task completes the sync layer by adding the two remaining sync functions, the orchestrator, and the online/resume event handlers.

- [ ] **Step 1: Add `syncMentorships(since)` function**

Add after `syncTrainings`:

```js
async function syncMentorships(since) {
    const url = since ? `/mentorships?since=${encodeURIComponent(since)}` : '/mentorships';
    const data = await _rawApi._fetch(url);
    const delta = Array.isArray(data?.data) ? data.data : [];
    if (delta.length === 0) return { count: 0 };

    const existing = await offlineStore.getMentorships();
    const merged = mergeById(existing, delta);
    await offlineStore.saveMentorships(merged);

    // Re-prefetch detail for updated mentorships
    const updated = delta.filter(r => !r.is_trashed);
    for (const m of updated) {
        try {
            // Re-cache mentorship detail + classes (best-effort, non-blocking per item)
            const detail = await _rawApi.mentorships.find(m.id);
            if (detail?.data) await offlineStore.saveMentorship(detail.data);
            const classes = await _rawApi.mentorships.classes(m.id);
            const classArr = Array.isArray(classes?.data) ? classes.data : [];
            if (classArr.length > 0) await offlineStore.saveMentorshipClasses(m.id, classArr);
        } catch {
            // Non-fatal — skip on any error
        }
    }

    return { count: delta.length };
}
```

- [ ] **Step 2: Add `syncUserLookupIndex(since)` function**

Add after `syncMentorships`:

```js
async function syncUserLookupIndex(since) {
    const url = since
        ? `/users/lookup-index?since=${encodeURIComponent(since)}`
        : '/users/lookup-index';
    const data = await _rawApi._fetch(url);
    const users = Array.isArray(data?.data) ? data.data : [];
    if (users.length === 0) return { count: 0 };

    // Build delta map: email → record (null for inactive users = remove)
    const updates = {};
    for (const u of users) {
        const key = (u.email ?? '').toLowerCase().trim();
        if (!key) continue;
        if (u.is_active === false) {
            updates[key] = null; // signals removal in mergeUserLookupMap
        } else {
            updates[key] = { id: u.id, name: u.name, phone: u.phone ?? null };
        }
    }
    await offlineStore.mergeUserLookupMap(updates);

    return { count: users.length };
}
```

- [ ] **Step 3: Add `smartSync()` orchestrator**

Add after `syncUserLookupIndex`:

```js
async function smartSync() {
    if (!navigator.onLine) return;

    const since = await offlineStore.getSyncedAt('global');
    const nowTs = new Date().toISOString();

    const [assessments, mentorships, trainings, users] = await Promise.allSettled([
        syncAssessments(since),
        syncMentorships(since),
        syncTrainings(since),
        syncUserLookupIndex(since),
    ]);

    await offlineStore.setSyncedAt('global', nowTs);

    const summary = {
        assessments: assessments.status === 'fulfilled' ? assessments.value.count : 0,
        mentorships: mentorships.status === 'fulfilled' ? mentorships.value.count : 0,
        trainings:   trainings.status === 'fulfilled'   ? trainings.value.count   : 0,
        users:       users.status === 'fulfilled'       ? users.value.count       : 0,
    };

    const hasChanges = Object.values(summary).some(n => n > 0);
    if (hasChanges) {
        window.dispatchEvent(new CustomEvent('app:sync-complete', { detail: summary }));
    }
}
```

- [ ] **Step 4: Replace `prefetchMentorshipsForOffline` call in `mentorships.list` with `smartSync`**

Find this line inside `mentorships.list`:

```js
api.prefetchMentorshipsForOffline().catch(() => {});
```

Replace it with:

```js
api.smartSync().catch(() => {});
```

- [ ] **Step 5: Wire `smartSync` to the `window.online` and Capacitor resume events**

Find the existing `window.addEventListener("online", handleOnline)` pattern. This is inside `sync-queue.js`. For `api.service.js`, add its own listeners at the bottom of the file (before the `export default api` line):

```js
// Delta sync on reconnect and Capacitor resume
window.addEventListener('online', () => { smartSync().catch(() => {}); });
document.addEventListener('resume', () => {
    if (navigator.onLine) smartSync().catch(() => {});
});
```

- [ ] **Step 6: Export `smartSync` on the `api` object**

Add `smartSync` to the default export so `ScopeShell.jsx` can call it:

```js
const api = {
    // ... existing methods ...
    smartSync,  // <-- add this
};
```

- [ ] **Step 7: Commit**

```bash
git add public/m-assessment-app/src/services/api.service.js
git commit -m "feat(api.service): smartSync orchestrator, syncMentorships, syncUserLookupIndex, online/resume wiring"
```

---

## Task 9: Components — Pending summary in SyncIndicator + inbound toast in ScopeShell

**Files:**
- Modify: `src/components/sync-indicator.jsx`
- Modify: `src/components/ScopeShell.jsx`

- [ ] **Step 1: Add pending summary detail to `SyncIndicator`**

The current `SyncIndicator` shows a count label. Extend it to show a per-type breakdown when the label area is tapped.

Replace the `SyncIndicator` function body with this version:

```jsx
export function SyncIndicator() {
    const [state, setState] = useState(syncQueue.getStatus());
    const [dismissed, setDismissed] = useState(false);
    const [isSyncing, setIsSyncing] = useState(false);
    const [showDetail, setShowDetail] = useState(false);
    const [summary, setSummary] = useState(null);

    useEffect(() => {
        return syncQueue.subscribe((s) => {
            setState(s);
            setDismissed(false);
            setShowDetail(false);
            if (s.status !== "syncing") setIsSyncing(false);
        });
    }, []);

    const handleManualSync = useCallback(async () => {
        if (isSyncing) return;
        setIsSyncing(true);
        await syncQueue.flush();
    }, [isSyncing]);

    const handleLabelTap = useCallback(async () => {
        if (showDetail) {
            setShowDetail(false);
            return;
        }
        const s = await syncQueue.getPendingSummary();
        setSummary(s);
        setShowDetail(true);
    }, [showDetail]);

    const { status, pendingCount, lastError } = state;
    const online = navigator.onLine;

    let effectiveStatus = status;
    if (status === "idle" && pendingCount === 0) return null;
    if (status === "idle" && pendingCount > 0 && online) effectiveStatus = "idle_pending";
    if (status === "syncing" || isSyncing) effectiveStatus = "syncing";

    const config = STATUS_CONFIG[effectiveStatus];
    if (!config) return null;
    if (dismissed) return null;

    const label =
        effectiveStatus === "syncing"
            ? `Syncing ${pendingCount} change${pendingCount !== 1 ? "s" : ""}…`
        : effectiveStatus === "idle_pending"
            ? `${pendingCount} change${pendingCount !== 1 ? "s" : ""} ready to upload`
        : effectiveStatus === "error"
            ? `Sync failed${pendingCount > 0 ? ` · ${pendingCount} pending` : ""}${lastError ? `: ${lastError}` : ""}`
        : pendingCount > 0
            ? `Offline · ${pendingCount} change${pendingCount !== 1 ? "s" : ""} pending`
            : "Offline mode";

    const showSyncBtn = (effectiveStatus === "idle_pending" || effectiveStatus === "error") && online;
    const showRetryBtn = effectiveStatus === "error" && online;

    return (
        <div style={{
            position: "absolute", top: 0, left: 0, right: 0, zIndex: 200,
            padding: "0 12px",
            paddingTop: "calc(4px + env(safe-area-inset-top, 0px))",
            pointerEvents: "none",
        }}>
            <style>{`
                @keyframes spin { to { transform: rotate(360deg); } }
                @keyframes slideDown { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: translateY(0); } }
            `}</style>
            <div style={{
                background: config.bg,
                color: config.color,
                borderRadius: 14,
                padding: "8px 12px",
                display: "flex", alignItems: "center", gap: 8,
                fontSize: 12, fontWeight: 600,
                border: `1px solid ${config.border}`,
                boxShadow: "0 4px 20px rgba(0,0,0,0.08)",
                pointerEvents: "auto",
                backdropFilter: "blur(8px)",
                animation: "slideDown 0.25s ease",
                flexDirection: "column",
                alignItems: "stretch",
            }}>
                <div style={{ display: "flex", alignItems: "center", gap: 8 }}>
                    <SyncIcon type={effectiveStatus === "syncing" ? "sync" : config.icon} />
                    <span
                        style={{ flex: 1, lineHeight: 1.3, cursor: pendingCount > 0 ? "pointer" : "default" }}
                        onClick={pendingCount > 0 ? handleLabelTap : undefined}
                    >
                        {label}
                        {pendingCount > 0 && (
                            <span style={{ marginLeft: 4, opacity: 0.6, fontSize: 10 }}>
                                {showDetail ? "▲" : "▼"}
                            </span>
                        )}
                    </span>

                    {showSyncBtn && !showRetryBtn && (
                        <button
                            onClick={handleManualSync}
                            disabled={isSyncing}
                            style={{
                                padding: "4px 10px", borderRadius: 8, border: "none",
                                background: "rgba(16,185,129,0.15)", color: "#065F46",
                                fontSize: 11, fontWeight: 700, cursor: "pointer",
                                whiteSpace: "nowrap",
                            }}
                        >
                            ↑ Sync Now
                        </button>
                    )}

                    {showRetryBtn && (
                        <button
                            onClick={handleManualSync}
                            style={{
                                padding: "4px 10px", borderRadius: 8, border: "none",
                                background: "rgba(255,255,255,0.9)", color: "#991B1B",
                                fontSize: 11, fontWeight: 700, cursor: "pointer",
                            }}
                        >
                            Retry
                        </button>
                    )}

                    <button
                        onClick={() => setDismissed(true)}
                        style={{
                            width: 20, height: 20, borderRadius: 6, border: "none",
                            background: "rgba(0,0,0,0.08)", color: config.color,
                            fontSize: 12, cursor: "pointer",
                            display: "flex", alignItems: "center", justifyContent: "center",
                            flexShrink: 0,
                        }}
                    >
                        ✕
                    </button>
                </div>

                {/* Pending breakdown detail */}
                {showDetail && summary && summary.total > 0 && (
                    <div style={{
                        marginTop: 4,
                        borderTop: `1px solid ${config.border}`,
                        paddingTop: 6,
                        display: "flex",
                        flexDirection: "column",
                        gap: 2,
                    }}>
                        {Object.entries(summary.groups).map(([label, count]) => (
                            <div key={label} style={{ display: "flex", justifyContent: "space-between", fontSize: 11 }}>
                                <span>{label}</span>
                                <span style={{ fontWeight: 700 }}>{count}×</span>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
}
```

- [ ] **Step 2: Add `app:sync-complete` toast and `smartSync` on mount to `ScopeShell.jsx`**

Open `src/components/ScopeShell.jsx`. Add this import at the top:

```js
import { useState, useEffect, useCallback } from "react";
import api from "../services/api.service.js";
```

(The file likely already imports `useState` and `useEffect` — just add `useCallback` and the `api` import if missing.)

Inside the `ScopeShell` function body, add a new state and effect AFTER the existing `useEffect` that loads scope config:

```js
const [syncToast, setSyncToast] = useState(null);

// Trigger smart sync on mount and listen for inbound-change events
useEffect(() => {
    api.smartSync().catch(() => {});

    function handleSyncComplete(e) {
        const { assessments, mentorships, trainings, users } = e.detail ?? {};
        const parts = [];
        if (mentorships > 0) parts.push(`${mentorships} mentorship${mentorships !== 1 ? "s" : ""}`);
        if (assessments > 0) parts.push(`${assessments} assessment${assessments !== 1 ? "s" : ""}`);
        if (trainings > 0) parts.push(`${trainings} training${trainings !== 1 ? "s" : ""}`);
        if (users > 0) parts.push(`${users} user${users !== 1 ? "s" : ""}`);
        if (parts.length === 0) return;
        setSyncToast(parts.join(", ") + " updated");
        setTimeout(() => setSyncToast(null), 5000);
    }

    window.addEventListener("app:sync-complete", handleSyncComplete);
    return () => window.removeEventListener("app:sync-complete", handleSyncComplete);
}, []);
```

Then add the toast overlay inside the JSX returned by `ScopeShell`, as a sibling to the existing `SyncIndicator` render (or just before the `return`'s outermost closing div):

```jsx
{/* Inbound sync toast */}
{syncToast && (
    <div style={{
        position: "fixed",
        bottom: "calc(80px + env(safe-area-inset-bottom, 0px))",
        left: 16, right: 16,
        background: "linear-gradient(135deg, #ECFDF5, #D1FAE5)",
        color: "#065F46",
        border: "1px solid #6EE7B7",
        borderRadius: 14,
        padding: "10px 14px",
        fontSize: 13,
        fontWeight: 600,
        zIndex: 300,
        boxShadow: "0 4px 20px rgba(0,0,0,0.12)",
        display: "flex",
        alignItems: "center",
        gap: 8,
    }}>
        <span>↓</span>
        <span style={{ flex: 1 }}>{syncToast}</span>
        <button
            onClick={() => setSyncToast(null)}
            style={{
                background: "rgba(0,0,0,0.08)", border: "none",
                borderRadius: 6, width: 20, height: 20,
                cursor: "pointer", color: "#065F46", fontSize: 12,
                display: "flex", alignItems: "center", justifyContent: "center",
            }}
        >✕</button>
    </div>
)}
```

Place this JSX just before the last closing `</div>` in the component's return.

- [ ] **Step 3: Verify `ScopeShell.jsx` does not have a `useState` import conflict**

The file currently imports `useState` and `useEffect`. If `useCallback` was added in step 2, make sure the import is `import { useState, useEffect, useCallback } from "react";`.

- [ ] **Step 4: Smoke test manually**

Start dev server (`npm run dev`), log in. Open browser DevTools > Application > IndexedDB > mnch_offline > meta. Verify:
- `synced_at_global` key appears after login (set by `smartSync`)
- `user_lookup_map` key appears after login (populated by `syncUserLookupIndex`)

Open Network tab and look for requests to `/api/v1/assessments?since=`, `/api/v1/mentorships?since=`, `/api/v1/trainings?since=`, `/api/v1/users/lookup-index?since=`.

On second login or after going offline then online, the requests should include the `since` timestamp from the previous sync.

- [ ] **Step 5: Commit**

```bash
git add public/m-assessment-app/src/components/sync-indicator.jsx
git add public/m-assessment-app/src/components/ScopeShell.jsx
git commit -m "feat(ui): pending summary detail in SyncIndicator, inbound sync toast in ScopeShell"
```

---

## Self-Review

**Spec coverage check:**

| Spec requirement | Task covering it |
|---|---|
| `?since=` on /assessments | Task 3 |
| `?since=` on /mentorships | Task 4 |
| `?since=` on /trainings | Task 5 |
| `?since=` on /users/lookup-index | Task 6 |
| `mergeById` with `updated_at` guard | Task 7 Step 1 |
| `syncAssessments` / `syncTrainings` | Task 7 Steps 2–3 |
| `syncMentorships` with deep prefetch | Task 8 Step 1 |
| `syncUserLookupIndex` merging email map | Task 8 Step 2 |
| `smartSync()` orchestrator | Task 8 Step 3 |
| Replace `prefetchMentorshipsForOffline` | Task 8 Step 4 |
| Wire to online / Capacitor resume | Task 8 Step 5 |
| Offline `userByEmail` from map | Task 7 Step 4 |
| Validation guard in `createMentee` | Task 7 Step 5 |
| `getSyncedAt` / `setSyncedAt` | Task 1 |
| `getUserLookupMap` / `saveUserLookupMap` / `mergeUserLookupMap` | Task 1 |
| `patchQueueIds` (in-place queue patching) | Task 1 |
| `assertNoTempIds` guard in `executeOp` | Task 2 Steps 1–2 |
| `patchQueueIds` call after each reconciliation (6 sites) | Task 2 Steps 4–9 |
| 4xx conflict saving in `flush()` | Task 2 Step 3 |
| 4xx conflict saving in createClass/createMentee/addModule/addSession | Task 2 Steps 6–9 |
| `getPendingSummary()` | Task 2 Step 10 |
| `SyncIndicator` pending breakdown | Task 9 Step 1 |
| `app:sync-complete` toast in `ScopeShell.jsx` | Task 9 Step 2 |
| `smartSync` triggered after login | Task 9 Step 2 (mount effect) |

**Notes:**
- `_rawApi._fetch` usage in Task 7/8 assumes that name exists. Before implementing, grep the file for the internal fetch helper name and substitute if needed. Likely candidates: `_fetch`, `authFetch`, `fetchJSON`.
- The `?since=` filter in `AssessmentController` bypasses `AssessmentResource` for delta responses — this is intentional. The app only needs `id`, `updated_at`, and `is_trashed` to invalidate its cache. Full details are fetched on demand by individual `assessments.find()` calls.
- `userLookupIndex` scoping: super_admin sees all users, above-site roles see all, others see facility-scoped users. The `User::isAboveSite()` method exists per the codebase profile. Add it to the `User` import in `LookupController.php` if needed.
