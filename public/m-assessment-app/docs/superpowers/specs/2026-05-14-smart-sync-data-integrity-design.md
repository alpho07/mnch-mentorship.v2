# Smart Sync, User Lookup & Data Integrity Design

**Date:** 2026-05-14
**Status:** Approved
**Scope:** Backend (4 endpoints) + `offline-store.js` + `api.service.js` + `sync-queue.js` + `SyncIndicator` enhancement + toast in `App.jsx` / `ScopeShell.jsx`

---

## Problem

1. The app does a full re-fetch every time it comes online — wasteful and slow on poor connections.
2. Changes made via the web admin (new classes, updated assessments, newly registered users) never appear in the mobile app until a full manual refresh.
3. The offline email lookup for adding mentees only searches participants in the mentor's own classes — users registered on the web but never enrolled anywhere are invisible offline.
4. Users have no awareness of what pending changes are queued, or what the server just delivered on sync.
5. A chain of offline operations (create class → add module → add session) silently breaks on sync because downstream queue entries still reference provisional `local_*` IDs after the parent record is reconciled.

---

## Design

### 1. Delta Sync — Backend

Four endpoints gain an optional `?since=ISO8601` query parameter. The server returns only records with `updated_at > since`. Soft-deleted records are included in the delta with `is_trashed: true` so the app can remove them from cache.

**No `since`** → full result set (first sync / forced refresh).
**With `since`** → delta only (all subsequent syncs).

| Endpoint | Model filter | Deletion signal |
|---|---|---|
| `GET /api/v1/mentorships?since=` | `Training` where `updated_at > since` | `is_trashed: true` (already in response shape) |
| `GET /api/v1/assessments?since=` | `Assessment` including soft-deleted where `updated_at > since` | `is_trashed: true` added to response |
| `GET /api/v1/trainings?since=` | `Training` where `type=global_training`, `updated_at > since` | `is_trashed: true` |
| `GET /api/v1/users/lookup-index?since=` | `User` where `updated_at > since`, slim shape only | `is_active: false` signals removal |

**User lookup index response shape:**
```json
{
  "data": [
    { "id": 42, "name": "Jane Doe", "email": "jane@example.com", "phone": "0712345678" }
  ],
  "meta": { "generated_at": "2026-05-14T10:00:00Z", "total": 247 }
}
```

Authorization: same as existing endpoint guards. Super admin sees all; others see scoped users.

---

### 2. Delta Sync — Frontend (`api.service.js`)

Replace `prefetchMentorshipsForOffline()` with `smartSync()`. Called:
- After successful login (non-blocking)
- Every time the app comes back online (`window` online event, Capacitor resume)
- After `mentorships.list()` succeeds (replaces current non-blocking prefetch call)

```
smartSync():
  if (!navigator.onLine) return

  const since = await offlineStore.getSyncedAt('global') // ISO string or null
  const nowTs  = new Date().toISOString()

  results = await Promise.allSettled([
    syncMentorships(since),
    syncAssessments(since),
    syncTrainings(since),
    syncUserLookupIndex(since),
  ])

  await offlineStore.setSyncedAt('global', nowTs)

  const summary = buildSummary(results)   // { mentorships, assessments, trainings, users }
  if (summary.hasChanges) {
    window.dispatchEvent(new CustomEvent('app:sync-complete', { detail: summary }))
  }
```

**`syncMentorships(since)`:**
1. `GET /mentorships?since=since` → delta array
2. For each item: if `is_trashed` → remove from `getMentorships()` cache; else → update in place by id
3. For each non-trashed item with `updated_at > cached.updated_at`: re-run deep prefetch for that mentorship (classes, participants, modules, sessions)

**`syncAssessments(since)`:**
1. `GET /assessments?since=since` → delta array
2. Merge into assessment cache by id — trashed → remove, else → upsert

**`syncTrainings(since)`:**
1. `GET /trainings?since=since` → delta array
2. Merge into trainings cache by id

**`syncUserLookupIndex(since)`:**
1. `GET /users/lookup-index?since=since`
2. Merge into email→user map: new users added, updated users updated, inactive users removed

**Delta merge rule (all resources):**
```js
// Never overwrite a locally-modified record with older server data
if (cached.updated_at && serverRecord.updated_at < cached.updated_at) skip
// Otherwise upsert by id
```

---

### 3. User Lookup Index — Offline Email Search

**Storage:** `meta` store, key `'user_lookup_map'` — JSON object `{ email: { id, name, phone } }`. O(1) lookup by email. No IndexedDB version bump.

**`offline-store.js` additions:**
```js
getUserLookupMap()           → dbGet(STORES.meta, 'user_lookup_map')
saveUserLookupMap(map)       → dbPut(STORES.meta, 'user_lookup_map', map)
mergeUserLookupMap(updates)  → load existing, merge updates by email, save
getSyncedAt(resource)        → dbGet(STORES.meta, 'synced_at_' + resource)
setSyncedAt(resource, ts)    → dbPut(STORES.meta, 'synced_at_' + resource, ts)
```

**`api.service.js` — `lookups.userByEmail` offline path:**
```js
userByEmail: async (email) => {
  try {
    return await _rawApi.lookups.userByEmail(email)
  } catch (e) {
    if (isNetworkError(e)) {
      const map = await offlineStore.getUserLookupMap()
      const found = map?.[email.toLowerCase().trim()]
      if (found) return { data: found }
      return null   // not found offline — caller shows "will create new user"
    }
    throw e
  }
}
```

**Limitation (documented):** Users registered in the last sync interval will not appear until next sync. Acceptable — enrollment still requires a completed form either way.

---

### 4. Notifications

#### 4a. Outbound — Pending Changes Summary

`sync-queue.js` gains `getPendingSummary()`:
```js
getPendingSummary: async () => {
  const queue = await offlineStore.getQueue()
  // Group by human label
  const labels = {
    'mentorships.createClass':   'New class',
    'mentorships.createMentee':  'New mentee',
    'mentorships.addModule':     'New module',
    'mentorships.addSession':    'New session',
    'mentorships.updateClass':   'Class edit',
    'mentorships.updateMenteeEmail': 'Mentee update',
    'mentorships.endClass':      'Class completion',
    'mentorships.regenerateToken': 'Link regeneration',
    'responses.bulkSave':        'Assessment responses',
    'assessments.create':        'New assessment',
    'assessments.submit':        'Assessment submission',
    'humanResources.save':       'HR section',
    'healthProducts.save':       'HP section',
    // ...
  }
  const groups = {}
  for (const op of queue) {
    const label = labels[op.type] ?? op.type
    groups[label] = (groups[label] ?? 0) + 1
  }
  return { total: queue.length, groups }
}
```

`SyncIndicator` renders the summary when tapped — shows a bottom sheet / dropdown listing each group: "2 × New mentee", "1 × Assessment responses".

#### 4b. Inbound — Delta Toast

`ScopeShell.jsx` (or `App.jsx`) listens for `app:sync-complete`:
```js
window.addEventListener('app:sync-complete', (e) => {
  const { mentorships, assessments, trainings, users } = e.detail
  // Build human string: "2 mentorships updated, 47 new users"
  showSyncToast(buildSyncMessage(e.detail))
})
```

Toast auto-dismisses after 5 seconds. Silent if no changes (`summary.hasChanges === false`). Stacks if multiple syncs fire rapidly (show only the latest).

---

### 5. Data Integrity Safeguards

#### 5a. Queue Dependency Patching (most critical)

**Problem:** After `mentorships.createClass` reconciles `local_class_123 → 47`, downstream queue ops (`addModule`, `createMentee`, etc.) still carry `classId: 'local_class_123'`.

**Fix:** `offlineStore.patchQueueIds(tempId, realId)` — scans all queue entries and replaces any field value matching `tempId` with `realId`.

Called immediately after every ID reconciliation in `sync-queue.js`:
```js
// After migrateClassId:
await offlineStore.patchQueueIds(op.tempId, realId)

// After migrateMenteeId:
await offlineStore.patchQueueIds(op.tempId, realId)
// etc.
```

**`patchQueueIds` implementation:**
```js
patchQueueIds: async (tempId, realId) => {
  const queue = await offlineStore.getQueue()
  const patched = queue.map(entry => {
    const json = JSON.stringify(entry)
    if (!json.includes(tempId)) return entry
    return JSON.parse(json.replace(new RegExp(tempId, 'g'), realId))
  })
  await offlineStore.saveQueue(patched)
}
```

String-replace approach is safe here because temp IDs (`local_class_1715000000000`) are unique enough to never appear in payload data.

**Dependency chains covered:**
```
local_class_*       → classId field in: addModule, updateClass, deleteClass,
                       endClass, createMentee, regenerateToken, removeModule
local_participant_* → participantId field in: updateMenteeEmail
local_module_*      → moduleId field in: addSession, removeModule, removeSession
local_session_*     → sessionId field in: removeSession, updateSession
local_assessment_*  → assessmentId field in: responses.bulkSave, progress,
                       submit, HR/HP saves
```

#### 5b. Temp ID Guard Before Every API Call

Before each `executeOp` dispatches to the raw API, check for unresolved temp IDs:
```js
function assertNoTempIds(op) {
  const json = JSON.stringify(op)
  const match = json.match(/local_[a-z]+_\d+/)
  if (match) {
    console.error(`[SyncQueue] Op ${op.type} contains unresolved temp ID: ${match[0]}`)
    // Write to conflict store and return null (dequeue) rather than sending bad data
    throw Object.assign(new Error('Unresolved temp ID'), { status: 400, _tempIdGuard: true })
  }
}
```

Called at the top of `executeOp` for all mentorship and assessment cases.

#### 5c. Delta Merge Safety

```js
function mergeById(existing, delta) {
  const map = Object.fromEntries((existing ?? []).map(r => [r.id, r]))
  for (const record of delta) {
    if (record.is_trashed) {
      delete map[record.id]
    } else {
      const cached = map[record.id]
      // Don't overwrite if local copy is newer (has pending edits)
      if (!cached || record.updated_at >= (cached.updated_at ?? '')) {
        map[record.id] = record
      }
    }
  }
  return Object.values(map)
}
```

Used by all `sync*` functions.

#### 5d. Validation Before Enqueue

Each write method in `api.service.js` validates required fields before calling `syncQueue.enqueue`:
```js
// Example for createMentee
if (!payload.email) throw new Error('createMentee: email is required')
if (!classId) throw new Error('createMentee: classId is required')
```

Prevents malformed ops from entering the queue at all.

#### 5e. Conflict Store for All 4xx Failures

Currently only `mentorships.updateSession` and `mentorships.create` write to the conflict store. Extend to all reconciliation cases and all write ops that return 4xx, so no data silently disappears. The existing conflict store schema is sufficient.

#### 5f. `offlineStore.saveQueue` (new helper)

To support `patchQueueIds`, add a bulk-replace queue method:
```js
saveQueue: async (entries) => {
  // Clear and rewrite entire queue with patched entries
  await offlineStore.clearQueue()
  for (const entry of entries) {
    await offlineStore.addToQueue(entry)
  }
}
```

---

## Out of Scope

- No changes to any `screen-*.jsx` files (except adding toast listener to `ScopeShell.jsx` or `App.jsx`)
- No new IndexedDB version bump
- No conflict resolution UI (conflicts are logged; resolution is future work)
- Mid-session real-time push (WebSockets/SSE) — delta sync on reconnect is sufficient

---

## File Change Summary

| File | Changes |
|---|---|
| `MentorshipController.php` | Add `?since=` filter to `index()` |
| `AssessmentController.php` | Add `?since=` filter to `index()`, include soft-deleted in delta |
| `GlobalTrainingController.php` (or equivalent) | Add `?since=` filter |
| `LookupController.php` | New `userLookupIndex()` method |
| `routes/api.php` | Register `GET /users/lookup-index` |
| `offline-store.js` | `getUserLookupMap`, `saveUserLookupMap`, `mergeUserLookupMap`, `getSyncedAt`, `setSyncedAt`, `patchQueueIds`, `saveQueue` |
| `api.service.js` | `smartSync()`, `syncMentorships()`, `syncAssessments()`, `syncTrainings()`, `syncUserLookupIndex()`, `mergeById()`, offline `userByEmail`, validation guards |
| `sync-queue.js` | `getPendingSummary()`, `assertNoTempIds()` guard in `executeOp`, `patchQueueIds` call after each reconciliation |
| `SyncIndicator` component | Pending summary bottom sheet |
| `ScopeShell.jsx` or `App.jsx` | `app:sync-complete` toast listener |
