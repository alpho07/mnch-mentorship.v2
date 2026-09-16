# Mentorships Offline-First Design

**Date:** 2026-05-14
**Status:** Approved
**Scope:** `public/m-assessment-app/src/services/` — three files only; zero screen changes.

---

## Problem

The mentorship section of the mobile app requires internet for most operations. Field workers use the app in areas with no or minimal connectivity. Only the email invitation send (`markInvited`) should require internet; everything else must work offline.

---

## Approach: Extend Existing Offline Infrastructure (Option A)

Three files change. All screens remain untouched — they already call `api.*`.

| File | Change |
|---|---|
| `offline-store.js` | 10 new cache helper methods |
| `api.service.js` | Fill 7 read gaps + 12 write gaps + prefetch function |
| `sync-queue.js` | 12 new `executeOp` cases |

---

## 1. Offline Store Additions (`offline-store.js`)

No DB version bump — all new keys go into existing stores.

```
mentorships store:
  getClassDetail(classId)         → key "classDetail_{id}"
  saveClassDetail(classId, data)
  getModuleList(classId)          → key "modules_{id}"
  saveModuleList(classId, list)

mentorshipSessions store:
  getModuleSessions(moduleId)     → key "sessions_{id}"
  saveModuleSessions(moduleId, list)

meta store:
  getSessionTemplates(moduleId)   → key "sessionTemplates_{id}"
  saveSessionTemplates(moduleId, list)
  getEnrollmentLink(classId)      → key "enrollmentLink_{id}"
  saveEnrollmentLink(classId, data)
```

Cadres and departments reuse existing `getMeta('cadres')` / `setMeta('cadres', ...)` — no new store methods needed.

---

## 2. API Service — Read Gaps (`api.service.js`)

Pattern: try network → cache on success → return cache on `isNetworkError`.

| Method | Cache location | Offline fallback |
|---|---|---|
| `lookups.cadres` | `meta.cadres` | `[]` |
| `lookups.departments` | `meta.departments` | `[]` |
| `mentorships.classDetail(tId, cId)` | `classDetail_{cId}` | cached object |
| `modules.list(classId)` | `modules_{classId}` | cached array |
| `modules.sessions(moduleId)` | `sessions_{moduleId}` | cached array |
| `modules.sessionTemplates(moduleId)` | `sessionTemplates_{moduleId}` | `[]` |
| `classLifecycle.enrollmentLink(classId)` | `enrollmentLink_{classId}` | cached object |

---

## 3. API Service — Write Gaps (`api.service.js`)

Pattern: try network → on `isNetworkError`: apply optimistic local patch + enqueue.

| Method | Optimistic local action | Queue type |
|---|---|---|
| `mentorships.update` | update `detail_{id}` cache | `mentorships.update` *(existing)* |
| `mentorships.createClass` | provisional class `local_class_{ts}` in `classes_{tId}` | `mentorships.createClass` |
| `mentorships.updateClass` | patch `classDetail_{cId}` | `mentorships.updateClass` |
| `mentorships.deleteClass` | remove from `classes_{tId}` | `mentorships.deleteClass` |
| `classLifecycle.end` | set status `completed` in `classDetail_{cId}` | `mentorships.endClass` |
| `classLifecycle.createMentee` | provisional participant `local_participant_{ts}` in `participants_{cId}` | `mentorships.createMentee` |
| `classLifecycle.updateMentee` | patch email in `participants_{cId}` | `mentorships.updateMenteeEmail` |
| `classLifecycle.regenerateToken` | clear `enrollmentLink_{cId}` | `mentorships.regenerateToken` |
| `classLifecycle.addModule` | provisional module `local_module_{ts}` in `modules_{cId}` | `mentorships.addModule` |
| `modules.remove` | filter from `modules_{cId}` | `mentorships.removeModule` |
| `modules.addSession` | provisional session `local_session_{ts}` in `sessions_{mId}` | `mentorships.addSession` |
| `sessions.remove` | filter from `sessions_{mId}` | `mentorships.removeSession` |

### Special case: `classLifecycle.markInvited`
If `!navigator.onLine` → return `{ requiresInternet: true }` immediately, no queue.
If online → proceed normally. Screen checks the return value to show inline message.

---

## 4. Sync Queue New Cases (`sync-queue.js`)

12 new `executeOp` switch cases.

### ID Reconciliation cases (3)
Follow the `assessment:id-resolved` pattern — migrate provisional key in IndexedDB, dispatch CustomEvent for active UI.

**`mentorships.createClass`**
- Call `rawApi.mentorships.createClass(trainingId, payload)`
- Replace `local_class_{ts}` with real classId in `classes_{tId}` cache
- Dispatch `mentorship:classId-resolved { tempId, realId }`
- 409 → treat as success, reconcile using ID in response body

**`mentorships.createMentee`**
- Call `rawApi.classLifecycle.createMentee(classId, payload)`
- Replace `local_participant_{ts}` with real participantId in `participants_{classId}` cache
- Dispatch `mentorship:participantId-resolved { tempId, realId, classId }`
- 409 → treat as success

**`mentorships.addModule`**
- Call `rawApi.classLifecycle.addModule(classId, programModuleId)`
- Replace `local_module_{ts}` with real moduleId in `modules_{classId}` cache
- Dispatch `mentorship:moduleId-resolved { tempId, realId, classId }`

### Simple cases (9, no reconciliation)
- `mentorships.updateClass` → `rawApi.mentorships.updateClass(trainingId, classId, payload)`
- `mentorships.deleteClass` → `rawApi.mentorships.deleteClass(trainingId, classId)`
- `mentorships.endClass` → `rawApi.classLifecycle.end(classId)`
- `mentorships.updateMenteeEmail` → `rawApi.classLifecycle.updateMentee(classId, participantId, { email })`
- `mentorships.regenerateToken` → `rawApi.classLifecycle.regenerateToken(classId)`
- `mentorships.removeModule` → `rawApi.modules.remove(moduleId)`
- `mentorships.addSession` → `rawApi.modules.addSession(moduleId, templateId)` *(session reconciliation: update sessions cache with real ID)*
- `mentorships.removeSession` → `rawApi.sessions.remove(sessionId)`
- `mentorships.update` → already exists, no change needed

---

## 5. Prefetch Function (`api.service.js`)

`prefetchMentorshipsForOffline()` — called automatically (non-blocking) after `api.mentorships.list()` succeeds online. All steps use `Promise.allSettled` so one failure never blocks the rest.

```
1. mentorships.list()                         → caches list
2. for each mentorship:
     mentorships.find(id)                     → caches detail + classes array
3. for each class (across all mentorships):
     mentorships.classDetail(tId, cId)        → caches class detail + modules
     participants.list(classId)               → caches mentee roster
     classLifecycle.enrollmentLink(classId)   → caches enrollment link
4. for each module (across all classes):
     modules.sessions(moduleId)               → caches sessions
     attendance.roster(moduleId)              → caches attendance
     modules.sessionTemplates(moduleId)       → caches add-session templates
5. lookups.cadres()                           → caches for create forms
   lookups.departments()                      → caches for create forms
```

Total network calls: roughly `1 + M + (C×3) + (Mod×3) + 2` where M=mentorships, C=classes, Mod=modules. Runs fire-and-forget after list load.

---

## 6. Provisional Record Shape

Offline-created records carry `_isOffline: true` and a temp ID so the UI can render them immediately.

```js
// createClass offline
{ id: "local_class_1715000000000", name, status: "draft", _isOffline: true,
  module_count: 0, participant_count: 0, progress_percentage: 0 }

// createMentee offline
{ id: "local_participant_1715000000000", user_id: null, name, email,
  phone, invitation_sent_at: null, _isOffline: true }

// addModule offline
{ id: "local_module_1715000000000", name, status: "pending", _isOffline: true,
  session_count: 0 }

// addSession offline
{ id: "local_session_1715000000000", name, _isOffline: true }
```

---

## Out of Scope

- No changes to any `screen-*.jsx` files
- `userSearch` and `userByEmail` remain online-only (search semantics require live data; screens already gate on `navigator.onLine`)
- No new IndexedDB version bump
