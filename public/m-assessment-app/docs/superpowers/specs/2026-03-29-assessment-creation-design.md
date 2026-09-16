# Assessment Creation — Design Spec
**Date:** 2026-03-29
**Status:** Approved

---

## Overview

Add the ability for field assessors to start a new facility assessment directly from the mobile app (React + Capacitor), with full offline support matching the existing data layer patterns.

---

## Approach

FAB (Floating Action Button) on the Assessments list screen opens a bottom-sheet form. On submit the app creates an assessment online or queues creation offline, then immediately opens the assessment form so the user can begin filling sections.

---

## Architecture

### New Files
- `src/screens/screen-new-assessment.jsx` — bottom-sheet form component (`NewAssessmentSheet`)

### Modified Files
| File | Change |
|---|---|
| `src/services/api.service.js` | Patch `request()` to attach `err.data = data` on non-ok responses; add `assessments.create` to `_rawApi` and offline-aware `api`; upgrade `facilities.list` to offline-aware with cache; extend `prefetchForOffline` to fetch facilities unconditionally |
| `src/services/sync-queue.js` | Add `assessments.create` op type; the `executeOp` handler must catch 409 internally (checking `e.status === 409`) before it bubbles to the generic flush error handler, which would otherwise silently discard the op without running ID migration |
| `src/services/offline-store.js` | Add `facilities` store (DB version bump 2 → 3); add `deleteAssessment(id)` method |
| `src/screens/screen-assessments-list.jsx` | Add FAB button; accept `onCreate`, `facilities`, and `user` props; wire to `NewAssessmentSheet` |
| `src/App.jsx` | Manage `facilities` state; pass `user` + `facilities` + `onCreate` down; handle new assessment in state; add `window.addEventListener("assessment:id-resolved", …)` in a `useEffect` (with cleanup on unmount) to swap tempId → realId in assessments state and active modal |

---

## Data Flow

```
User taps FAB on Assessments screen
  → NewAssessmentSheet slides up
  → Loads facility list (api.facilities.list → IndexedDB cache)
  → User selects facility, type, date → taps "Start Assessment"

      [online]
        POST /api/v1/assessments
        → 201: save assessment to offline store → add to app state → open form modal
        → 409: show inline "duplicate" error with "Open Existing" action

      [offline]
        Generate tempId = "offline_" + Date.now()
        Build provisional assessment (all known fields, status: "in_progress",
          section_progress from cached schema)
        Save to IndexedDB assessments store under tempId
        Enqueue { type: "assessments.create", tempId, facility_id, assessment_type, assessment_date }
        Add to app state → open form modal immediately

  → When connectivity returns, sync queue replays assessments.create:
      POST /api/v1/assessments
      → 201: migrate tempId data (responses, HR, HP) to realId in IndexedDB
             dispatch CustomEvent("assessment:id-resolved", { tempId, realId })
             App.jsx swaps tempId → realId in assessments state
      → 409: use returned existing assessment's ID, discard temp record
```

---

## UI Components

### FAB
- Circular button, bottom-right of Assessments list, above bottom nav (z-index above list, below nav)
- `T.gradientPrimary` fill, white `+` icon, `T.shadowMd` glow
- Tapping opens `NewAssessmentSheet`

### NewAssessmentSheet
Bottom-sheet overlay (absolutely positioned, slides up via CSS transform animation) with a drag handle and three fields:

1. **Facility picker**
   - Search input filters cached facility list in real time
   - Each result shows: facility name (bold) + MFL code + subcounty
   - Offline with no cache: shows a dismissable warning banner, still allows proceeding
   - Tapping a result selects it and collapses the results list

2. **Assessment type**
   - 3-pill segmented control: Baseline · Midline · Endline
   - Defaults to Baseline
   - Maps to backend values: `baseline | midline | endline`

3. **Assessment date**
   - Native `<input type="date">` defaulting to today
   - `max` attribute set to today (enforces `before_or_equal:today`)

4. **Submit button** — "Start Assessment"
   - Disabled until all three fields are filled
   - Shows spinner while API call is in-flight
   - On 409 duplicate: inline error message + "Open Existing" link

---

## Offline Handling

### Facility Cache
- New IndexedDB store: `facilities` (DB version 3)
- `offlineStore.getFacilities()` / `saveFacilities(list)`
- `api.facilities.list()` becomes offline-aware: try network → cache on success → return cache on network failure
- Added to `api.prefetchForOffline` — fetched **unconditionally** (not gated on `inProgress.length > 0`) since facilities are needed before any assessment is opened

### Provisional Assessment (offline create)
- `tempId = "offline_" + Date.now() + "_" + Math.random().toString(36).slice(2, 8)` — random suffix prevents collisions; follows the same pattern as `offlineStore.addToQueue`
- `facilityMeta` shape (passed by `NewAssessmentSheet` from the selected facility object): `{ name, mfl_code, subcounty, county }` — required to populate the provisional object for display while offline
- `assessor_id` / `assessor_name` sourced from the `user` prop (requires adding `user` prop to `AssessmentsListScreen` and threading to `NewAssessmentSheet`)
- Provisional object fields:
  ```js
  {
    id: tempId,
    facility_id,
    facility_name: facilityMeta.name,
    mfl_code: facilityMeta.mfl_code,
    county: facilityMeta.county,
    subcounty: facilityMeta.subcounty,
    assessment_type,
    assessment_date,
    assessor_id: user.id,
    assessor_name: user.name,
    status: "in_progress",
    section_progress,    // all section codes → false, from cached schema
    _isOffline: true,    // used to show "Pending sync" badge in AssessmentsListScreen
  }
  ```
- `section_progress` derived from cached schema (all section codes → `false`)
- Saved to `offlineStore.saveAssessment(provisional)`
- Enqueued in sync queue as `assessments.create`

### Sync Queue: `assessments.create` Op
```
op shape: {
  type: "assessments.create",
  tempId,           // "offline_<timestamp>_<random>"
  facility_id,
  assessment_type,
  assessment_date
}
```

Replay logic (inside `executeOp` in `sync-queue.js`):
1. Call `_rawApi.assessments.create(facility_id, assessment_type, assessment_date)`
2. On **201**: get `realId` from `response.assessment.id`
   - `await offlineStore.copyAssessmentData(tempId, realId)` — copies responses, hr, hp records
   - `await offlineStore.deleteAssessment(tempId)` — removes provisional record
   - `window.dispatchEvent(new CustomEvent("assessment:id-resolved", { detail: { tempId, realId } }))`
   - Return without throwing — op is removed from queue by the flush loop
3. On **409**: extract `assessment` from `e.data?.assessment`; use its `id` as `realId`; run same migration as step 2. **Do not re-throw** — treat as success so the op is dequeued.
   - **Critical:** The `executeOp` handler must catch the 409 (`e.status === 409`) before it bubbles to the generic `flush()` error handler, which would otherwise discard the op silently without running migration.
   - **Required `api.service.js` change:** The `request()` function (line 66) currently builds thrown errors with only `err.status` and `err.errors`. The 409 body includes `{ message, assessment }` — the full parsed `data` must also be attached: `err.data = data`. This makes `e.data.assessment.id` accessible in `executeOp`. This change is additive and does not affect existing error handling elsewhere.
4. On other errors: re-throw so `flush()` keeps the op in the queue for the next retry

### App.jsx: ID Resolution
- `useEffect` with `window.addEventListener("assessment:id-resolved", handler)` registered on mount, cleaned up on unmount (not conditional on `user` — registered always so it fires even if the user state changes during a long session)
- `handler`: swaps `tempId → realId` in `assessments` state array; if form modal is currently open with `modal.data.id === tempId`, updates `modal.data.id` to `realId`

### `offlineStore` additions
- `deleteAssessment: (id) => dbDelete(STORES.assessments, id)`
- `copyAssessmentData: async (fromId, toId)` — for each of responses, hr, hp stores: `dbGet(store, fromId)` → if found, `dbPut(store, toId, data)` then `dbDelete(store, fromId)`; no-ops gracefully if source records don't exist. This ensures no orphaned entries remain under `tempId` after migration.
- Facility cache key: `"all"` (consistent with schema using `"full"`)
- `getFacilities: () => dbGet(STORES.facilities, "all")`
- `saveFacilities: (list) => dbPut(STORES.facilities, "all", list)`

---

## API Changes

### `_rawApi.assessments.create`
```js
create: (facility_id, assessment_type, assessment_date) =>
  post('/assessments', { facility_id, assessment_type, assessment_date })
```

### `api.assessments.create` (offline-aware)
```js
create: async (facility_id, assessment_type, assessment_date, facilityMeta) => {
  try {
    const data = await _rawApi.assessments.create(facility_id, assessment_type, assessment_date);
    const a = data?.assessment ?? data?.data ?? data;
    if (a?.id) await offlineStore.saveAssessment(a);
    return data;
  } catch (e) {
    if (isNetworkError(e)) {
      // provisional creation — see offline flow above
    }
    throw e;
  }
}
```

### `api.facilities.list` (upgraded to offline-aware)
Currently delegates straight to `_rawApi`. Upgraded to cache results and serve from cache on network failure.

---

## Backend Contract (existing, no changes needed)

```
POST /api/v1/assessments
Body: { facility_id, assessment_type, assessment_date }
201: { message, assessment: AssessmentResource }
409: { message, assessment: AssessmentResource }  ← existing duplicate
422: { errors }  ← validation failure
```

---

## Error States

| Scenario | Handling |
|---|---|
| No facility selected | Submit button disabled |
| No facilities in cache (offline) | Warning banner + submit blocked with "Facilities not available offline. Please connect to continue." message |
| Network error on create | Offline flow: provisional assessment, queue |
| 409 duplicate | Inline error + "Open Existing" CTA |
| 422 validation | Show field-level errors inline |
| Sync replay failure | Op stays in queue; SyncIndicator shows pending count |

---

## Required Copy Change
- `screen-assessments-list.jsx` empty state currently reads "Your assessments will appear here once assigned by an administrator." — must be updated to "No assessments yet. Tap + to start one." since users can now create their own.

---

## Out of Scope
- Editing an existing assessment's header fields (facility, type, date) post-creation — handled by existing `PUT /assessments/:id`
- Deleting assessments from the app — endpoint exists but no UI needed now
- Role-based creation restrictions — backend `store` authenticates by token only; any logged-in user may create
- Data preservation on logout with pending offline creates — `offlineStore.clearAll()` on logout will wipe provisional assessments and their queued create ops. This is an intentional trade-off: logout implies intent to end the session. Acceptable.
