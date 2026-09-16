# Assessment Creation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a FAB-triggered bottom-sheet form to the Assessments list screen so field assessors can start new facility assessments, with full offline support (provisional local create → queue → sync).

**Architecture:** Six focused changes across three layers: offline store (add `facilities` store + migration helpers), API service (patch error shape, add create + cached facilities), sync queue (add `assessments.create` replay with temp→real ID migration), then UI (new `NewAssessmentSheet` component, FAB in list screen, state wiring in App.jsx).

**Tech Stack:** React 19, Vite, IndexedDB (via custom offline-store wrapper), Capacitor 8, no test framework (verify manually with `npm run dev`)

**Spec:** `docs/superpowers/specs/2026-03-29-assessment-creation-design.md`

---

## File Map

| Action | File | Responsibility |
|---|---|---|
| Modify | `src/services/offline-store.js` | Add `facilities` store (DB v3), `deleteAssessment`, `copyAssessmentData`, `getFacilities`, `saveFacilities` |
| Modify | `src/services/api.service.js` | Patch `request()` error shape; add `assessments.create` to `_rawApi` + offline-aware `api`; upgrade `facilities.list` to cached; extend `prefetchForOffline` |
| Modify | `src/services/sync-queue.js` | Add `assessments.create` case to `executeOp` with internal 409 handling + ID migration |
| Create | `src/screens/screen-new-assessment.jsx` | `NewAssessmentSheet` bottom-sheet: facility picker, type selector, date picker, submit |
| Modify | `src/screens/screen-assessments-list.jsx` | Add FAB, wire `NewAssessmentSheet`, accept `user`/`facilities`/`onCreate` props, update empty state, show `_isOffline` badge |
| Modify | `src/App.jsx` | Manage `facilities` state, pass props down, `handleCreate`, `assessment:id-resolved` listener |

---

## Task 1: Extend offline-store.js — facilities store + migration helpers

**Files:**
- Modify: `src/services/offline-store.js`

The DB version must bump from 2 to 3. The existing `onupgradeneeded` handler already iterates `Object.values(STORES)` and creates any missing store, so adding `facilities` to the `STORES` constant is the only structural change needed. Five new public methods are added.

- [ ] **Step 1.1: Bump DB version and add facilities store**

In `src/services/offline-store.js`, change line 13 and the `STORES` object:

```js
// line 13 — change 2 to 3
const DB_VERSION = 3;

// add `facilities` to the STORES object (after existing entries)
const STORES = {
    schema: "schema",
    assessments: "assessments",
    responses: "responses",
    hr: "hr",
    hp: "hp",
    user: "user",
    syncQueue: "syncQueue",
    meta: "meta",
    facilities: "facilities",   // ← new
};
```

- [ ] **Step 1.2: Add five new public methods to `offlineStore`**

Inside the `offlineStore` object (after the existing `// ── Meta` section, before `// ── Full wipe`), add:

```js
    // ── Facilities ────────────────────────────────────────────────────────────
    getFacilities: () => dbGet(STORES.facilities, "all"),
    saveFacilities: (list) => dbPut(STORES.facilities, "all", list),

    // ── Assessment lifecycle helpers ──────────────────────────────────────────
    deleteAssessment: (id) => dbDelete(STORES.assessments, id),

    /**
     * Migrate data stored under fromId to toId across responses, hr, and hp stores.
     * Used when a provisional offline assessment gets a real server ID.
     * Deletes the fromId records after copying to avoid orphaned entries.
     */
    copyAssessmentData: async (fromId, toId) => {
        for (const storeName of [STORES.responses, STORES.hr, STORES.hp]) {
            try {
                const data = await dbGet(storeName, fromId);
                if (data !== null) {
                    await dbPut(storeName, toId, data);
                    await dbDelete(storeName, fromId);
                }
            } catch {
                // Non-critical — best effort migration
            }
        }
    },
```

- [ ] **Step 1.3: Verify the DB opens cleanly**

Run: `npm run dev`

Open the app in the browser, log in. Open DevTools → Application → IndexedDB → `mnch_offline`. Confirm version shows **3** and a `facilities` store appears alongside the existing stores. If the DB was previously at version 2, the upgrade fires automatically on first load.

- [ ] **Step 1.4: Commit**

```bash
git add src/services/offline-store.js
git commit -m "feat(offline): add facilities store + deleteAssessment + copyAssessmentData (DB v3)"
```

---

## Task 2: Patch api.service.js — error shape, create endpoint, cached facilities, prefetch

**Files:**
- Modify: `src/services/api.service.js`

Four independent changes in one file.

- [ ] **Step 2.1: Attach parsed body to thrown errors in `request()`**

Find the error block inside `request()` (around line 63) and add `err.data = data`:

```js
    if (!res.ok) {
        const err = new Error(data.message || `HTTP ${res.status}`);
        err.status = res.status;
        err.data = data;                // ← add this line
        err.errors = data.errors ?? {};
        throw err;
    }
```

This is additive — existing callers that only check `e.status` or `e.errors` are unaffected. It allows sync-queue's `assessments.create` handler to access `e.data.assessment` on a 409.

- [ ] **Step 2.2: Add `assessments.create` to `_rawApi`**

Inside `_rawApi.assessments` (after the existing `updateSectionProgress` entry), add:

```js
        create: (facility_id, assessment_type, assessment_date) =>
            post('/assessments', {facility_id, assessment_type, assessment_date}),
```

- [ ] **Step 2.3: Upgrade `api.facilities` to offline-aware with cache**

The current `facilities: _rawApi.facilities` line passes through directly. Replace it:

```js
    // ── Facilities (cached reads) ─────────────────────────────────────────────
    facilities: {
        list: async (params) => {
            try {
                const data = await _rawApi.facilities.list(params);
                const arr = Array.isArray(data) ? data
                    : Array.isArray(data?.data) ? data.data : [];
                if (arr.length > 0)
                    await offlineStore.saveFacilities(arr);
                return data;
            } catch (e) {
                if (isNetworkError(e)) {
                    const cached = await offlineStore.getFacilities();
                    if (cached && cached.length > 0) {
                        console.log(`[API] Offline — returning ${cached.length} cached facilities`);
                        return cached;
                    }
                }
                throw e;
            }
        },
        byCounty: _rawApi.facilities.byCounty,
        find: _rawApi.facilities.find,
    },
```

- [ ] **Step 2.4: Add offline-aware `api.assessments.create`**

Inside `api.assessments` (after the existing `updateSectionProgress` method), add:

```js
        create: async (facility_id, assessment_type, assessment_date, facilityMeta, user, sectionCodes) => {
            try {
                const data = await _rawApi.assessments.create(facility_id, assessment_type, assessment_date);
                const a = data?.assessment ?? data?.data ?? data;
                if (a?.id) await offlineStore.saveAssessment(a);
                return data;
            } catch (e) {
                if (isNetworkError(e)) {
                    console.log('[API] Offline — creating provisional assessment');
                    const tempId = 'offline_' + Date.now() + '_' + Math.random().toString(36).slice(2, 8);
                    // Build section_progress from cached schema codes
                    const sectionProgress = {};
                    (sectionCodes ?? []).forEach(code => { sectionProgress[code] = false; });
                    const provisional = {
                        id: tempId,
                        facility_id,
                        facility_name: facilityMeta?.name ?? '',
                        mfl_code: facilityMeta?.mfl_code ?? '',
                        county: facilityMeta?.county ?? '',
                        subcounty: facilityMeta?.subcounty ?? '',
                        assessment_type,
                        assessment_date,
                        assessor_id: user?.id ?? null,
                        assessor_name: user?.name ?? '',
                        status: 'in_progress',
                        section_progress: sectionProgress,
                        section_scores: {},
                        section_progress_detail: {},
                        responses: {},
                        overall_percentage: null,
                        overall_grade: null,
                        _isOffline: true,
                    };
                    await offlineStore.saveAssessment(provisional);
                    await syncQueue.enqueue({
                        type: 'assessments.create',
                        tempId,
                        facility_id,
                        assessment_type,
                        assessment_date,
                    });
                    return {_provisional: true, assessment: provisional};
                }
                throw e;
            }
        },
```

Note: `syncQueue` is already imported at the top of `api.service.js` (it's used in other methods). Confirm it's imported before this step.

- [ ] **Step 2.5: Extend `prefetchForOffline` to fetch facilities unconditionally**

In `api.prefetchForOffline`, add the facilities fetch **before** the `inProgress` early-return guard:

```js
    prefetchForOffline: async (assessments) => {
        if (!navigator.onLine)
            return;

        // Facilities are fetched unconditionally — needed before any assessment opens
        try {
            await api.facilities.list();
        } catch {
            // Non-critical
        }

        const inProgress = (assessments ?? []).filter(a => a.status === "in_progress");
        if (inProgress.length === 0)
            return;
        // IMPORTANT: Only prepend the facilities fetch above. Everything from
        // `const inProgress = ...` to the end of the function is UNCHANGED.
        // Do not replace the function — insert these lines after the `!navigator.onLine` guard.
```

- [ ] **Step 2.6: Verify in browser**

`npm run dev`, log in, open DevTools → Network. Confirm `GET /facilities` fires on load. Open Application → IndexedDB → `mnch_offline` → `facilities` — confirm the list is cached.

- [ ] **Step 2.7: Commit**

```bash
git add src/services/api.service.js
git commit -m "feat(api): add assessments.create with offline fallback + cached facilities"
```

---

## Task 3: Add `assessments.create` handler to sync-queue.js

**Files:**
- Modify: `src/services/sync-queue.js`

The handler must be added inside `executeOp` and must catch 409 **before** it re-throws to the outer `flush()` catch block (which discards all 4xx errors silently).

- [ ] **Step 3.1: Add the case to `executeOp`**

Inside `executeOp`, add a new case **before** the `default` case:

```js
        case "assessments.create": {
            let realId;
            try {
                const res = await rawApi.assessments.create(
                    op.facility_id, op.assessment_type, op.assessment_date
                );
                realId = res?.assessment?.id ?? res?.data?.id ?? res?.id;
            } catch (e) {
                if (e.status === 409) {
                    // Duplicate in-progress assessment — use the existing one's ID
                    realId = e.data?.assessment?.id;
                    if (!realId) {
                        console.warn('[SyncQueue] 409 but no assessment in body — cleaning up provisional');
                        // Clean up the provisional record so it doesn't persist in IndexedDB
                        await offlineStore.deleteAssessment(op.tempId);
                        return null; // Returning null prevents re-throw; flush loop dequeues it
                    }
                } else {
                    throw e; // Network / 5xx — keep in queue
                }
            }

            if (!realId) {
                console.warn('[SyncQueue] assessments.create returned no ID — discarding');
                return null;
            }

            // Migrate all offline data from tempId → realId
            await offlineStore.copyAssessmentData(op.tempId, realId);
            await offlineStore.deleteAssessment(op.tempId);

            // Notify App.jsx to swap the ID in React state
            window.dispatchEvent(
                new CustomEvent('assessment:id-resolved', {detail: {tempId: op.tempId, realId}})
            );
            console.log(`[SyncQueue] Resolved offline assessment ${op.tempId} → ${realId}`);
            return null;
        }
```

- [ ] **Step 3.2: Update the op-types comment block**

At the top of the file, update the comment listing op types:

```js
// Types:
//   "responses.bulkSave"       → { assessmentId, sectionCode, responses, explanations }
//   "assessments.progress"     → { assessmentId, sectionCode, done }
//   "assessments.submit"       → { assessmentId }
//   "humanResources.save"      → { assessmentId, responses }
//   "healthProducts.save"      → { assessmentId, responses, departmentId? }
//   "assessments.create"       → { tempId, facility_id, assessment_type, assessment_date }
```

- [ ] **Step 3.3: Commit**

```bash
git add src/services/sync-queue.js
git commit -m "feat(sync): handle assessments.create op with 409-safe ID migration"
```

---

## Task 4: Create screen-new-assessment.jsx

**Files:**
- Create: `src/screens/screen-new-assessment.jsx`

This is a self-contained bottom-sheet component. It receives `facilities`, `user`, `sections` (for `sectionCodes`), `onSubmit`, and `onClose` as props.

- [ ] **Step 4.1: Create the file with full implementation**

```jsx
/**
 * NewAssessmentSheet
 * src/screens/screen-new-assessment.jsx
 *
 * Bottom-sheet form for starting a new facility assessment.
 * Props:
 *   facilities  — array of facility objects from api.facilities.list
 *   sections    — array of section objects (to derive sectionCodes)
 *   user        — current user object { id, name }
 *   onSubmit    — async (assessment) => void  — called with the created/provisional assessment
 *   onClose     — () => void
 */
import { useState, useMemo } from "react";
import { T } from "../constants.js";
import api from "../services/api.service.js";

const TYPES = [
    { label: "Baseline", value: "baseline" },
    { label: "Midline", value: "midline" },
    { label: "Endline", value: "endline" },
];

function todayStr() {
    return new Date().toISOString().slice(0, 10);
}

export function NewAssessmentSheet({ facilities, sections, user, onSubmit, onClose }) {
    const [search, setSearch] = useState("");
    const [selectedFacility, setSelectedFacility] = useState(null);
    const [showList, setShowList] = useState(false);
    const [assessmentType, setAssessmentType] = useState("baseline");
    const [assessmentDate, setAssessmentDate] = useState(todayStr());
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState(null);         // null | { type, message, existing? }

    const facilitiesList = facilities ?? [];
    const noCache = facilitiesList.length === 0;

    const filtered = useMemo(() => {
        if (!search.trim()) return facilitiesList.slice(0, 40);
        const q = search.toLowerCase();
        return facilitiesList
            .filter(f =>
                (f.name ?? "").toLowerCase().includes(q) ||
                (f.mfl_code ?? "").toLowerCase().includes(q) ||
                (f.subcounty ?? "").toLowerCase().includes(q)
            )
            .slice(0, 40);
    }, [search, facilitiesList]);

    const sectionCodes = useMemo(() => (sections ?? []).map(s => s.code), [sections]);

    const canSubmit = selectedFacility && assessmentDate && !submitting && !noCache;

    const handleFacilitySelect = (f) => {
        setSelectedFacility(f);
        setSearch(f.name);
        setShowList(false);
        setError(null);
    };

    const handleSubmit = async () => {
        if (!canSubmit) return;
        setSubmitting(true);
        setError(null);
        try {
            const facilityMeta = {
                name: selectedFacility.name,
                mfl_code: selectedFacility.mfl_code ?? "",
                county: selectedFacility.county ?? selectedFacility.county_name ?? "",
                subcounty: selectedFacility.subcounty ?? selectedFacility.subcounty_name ?? "",
            };
            const data = await api.assessments.create(
                selectedFacility.id,
                assessmentType,
                assessmentDate,
                facilityMeta,
                user,
                sectionCodes
            );
            const assessment = data?.assessment ?? data?.data ?? data;
            await onSubmit(assessment);
        } catch (e) {
            if (e.status === 409 && e.data?.assessment) {
                setError({ type: "duplicate", message: "An in-progress assessment already exists for this facility and type.", existing: e.data.assessment });
            } else if (e.status === 422) {
                const msgs = Object.values(e.errors ?? {}).flat();
                setError({ type: "validation", message: msgs[0] ?? "Invalid input." });
            } else {
                setError({ type: "generic", message: e.message ?? "Something went wrong." });
            }
            setSubmitting(false);
        }
    };

    return (
        <>
            {/* Backdrop */}
            <div
                onClick={onClose}
                style={{
                    position: "absolute", inset: 0,
                    background: "rgba(0,0,0,0.45)",
                    zIndex: 200,
                    animation: "fadeIn 0.2s ease both",
                }}
            />

            {/* Sheet */}
            <div style={{
                position: "absolute", left: 0, right: 0, bottom: 0,
                background: "white",
                borderRadius: "24px 24px 0 0",
                zIndex: 201,
                padding: "0 0 env(safe-area-inset-bottom)",
                boxShadow: "0 -8px 40px rgba(0,0,0,0.15)",
                animation: "slideUp 0.3s cubic-bezier(0.34,1.1,0.64,1) both",
                maxHeight: "88dvh",
                display: "flex",
                flexDirection: "column",
            }}>
                {/* Drag handle */}
                <div style={{ display: "flex", justifyContent: "center", padding: "12px 0 4px" }}>
                    <div style={{ width: 36, height: 4, borderRadius: 2, background: T.border }} />
                </div>

                {/* Header */}
                <div style={{ padding: "8px 20px 16px", borderBottom: `1px solid ${T.borderLight}` }}>
                    <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center" }}>
                        <div style={{ fontSize: 18, fontWeight: 800, color: T.text }}>New Assessment</div>
                        <button
                            onClick={onClose}
                            style={{ background: T.borderLight, border: "none", borderRadius: 10, width: 32, height: 32, cursor: "pointer", display: "flex", alignItems: "center", justifyContent: "center", color: T.textMuted, fontSize: 18 }}
                        >×</button>
                    </div>
                    <div style={{ fontSize: 12, color: T.textSub, marginTop: 3 }}>
                        Select a facility and fill in the details below
                    </div>
                </div>

                {/* Scrollable body */}
                <div style={{ flex: 1, overflowY: "auto", padding: "16px 20px" }}>
                    {/* Offline / no cache warning */}
                    {noCache && (
                        <div style={{
                            background: "#FEF3C7", border: "1px solid #FDE68A",
                            borderRadius: T.radiusSm, padding: "10px 14px", marginBottom: 16,
                            fontSize: 12, color: "#92400E", fontWeight: 500,
                        }}>
                            ⚠ Facilities not available offline. Please connect to continue.
                        </div>
                    )}

                    {/* ── Facility picker ── */}
                    <div style={{ marginBottom: 18 }}>
                        <div style={{ fontSize: 12, fontWeight: 700, color: T.textMid, marginBottom: 6, textTransform: "uppercase", letterSpacing: 0.5 }}>
                            Facility
                        </div>
                        <input
                            type="text"
                            placeholder="Search by name or MFL code…"
                            value={search}
                            onChange={e => {
                                setSearch(e.target.value);
                                setSelectedFacility(null);
                                setShowList(true);
                                setError(null);
                            }}
                            onFocus={() => setShowList(true)}
                            disabled={noCache}
                            style={{
                                width: "100%", boxSizing: "border-box",
                                padding: "11px 14px", borderRadius: T.radiusSm,
                                border: `1.5px solid ${selectedFacility ? T.primary : T.border}`,
                                fontSize: 14, color: T.text,
                                background: noCache ? T.borderLight : "white",
                                outline: "none",
                                transition: "border-color 0.2s",
                            }}
                        />
                        {showList && !selectedFacility && filtered.length > 0 && (
                            <div style={{
                                border: `1px solid ${T.border}`,
                                borderRadius: T.radiusSm,
                                marginTop: 4,
                                maxHeight: 200,
                                overflowY: "auto",
                                background: "white",
                                boxShadow: T.shadowMd,
                            }}>
                                {filtered.map(f => (
                                    <button
                                        key={f.id}
                                        onClick={() => handleFacilitySelect(f)}
                                        style={{
                                            width: "100%", textAlign: "left",
                                            padding: "10px 14px", background: "none",
                                            border: "none", borderBottom: `1px solid ${T.borderLight}`,
                                            cursor: "pointer", display: "block",
                                        }}
                                    >
                                        <div style={{ fontSize: 13, fontWeight: 600, color: T.text }}>{f.name}</div>
                                        <div style={{ fontSize: 11, color: T.textMuted, marginTop: 2 }}>
                                            {[f.mfl_code && `MFL: ${f.mfl_code}`, f.subcounty, f.county].filter(Boolean).join(" · ")}
                                        </div>
                                    </button>
                                ))}
                            </div>
                        )}
                        {showList && !selectedFacility && search.trim() && filtered.length === 0 && (
                            <div style={{ padding: "10px 0", fontSize: 12, color: T.textMuted }}>
                                No facilities found for "{search}"
                            </div>
                        )}
                    </div>

                    {/* ── Assessment type ── */}
                    <div style={{ marginBottom: 18 }}>
                        <div style={{ fontSize: 12, fontWeight: 700, color: T.textMid, marginBottom: 6, textTransform: "uppercase", letterSpacing: 0.5 }}>
                            Type
                        </div>
                        <div style={{ display: "flex", gap: 8 }}>
                            {TYPES.map(t => (
                                <button
                                    key={t.value}
                                    onClick={() => setAssessmentType(t.value)}
                                    style={{
                                        flex: 1, padding: "10px 0", borderRadius: T.radiusSm,
                                        border: assessmentType === t.value ? "none" : `1.5px solid ${T.border}`,
                                        background: assessmentType === t.value ? T.gradientPrimary : "white",
                                        color: assessmentType === t.value ? "white" : T.textMid,
                                        fontSize: 13, fontWeight: 600, cursor: "pointer",
                                        transition: "all 0.2s",
                                        boxShadow: assessmentType === t.value ? T.shadowMd : "none",
                                    }}
                                >
                                    {t.label}
                                </button>
                            ))}
                        </div>
                    </div>

                    {/* ── Date ── */}
                    <div style={{ marginBottom: 20 }}>
                        <div style={{ fontSize: 12, fontWeight: 700, color: T.textMid, marginBottom: 6, textTransform: "uppercase", letterSpacing: 0.5 }}>
                            Date
                        </div>
                        <input
                            type="date"
                            value={assessmentDate}
                            max={todayStr()}
                            onChange={e => setAssessmentDate(e.target.value)}
                            style={{
                                width: "100%", boxSizing: "border-box",
                                padding: "11px 14px", borderRadius: T.radiusSm,
                                border: `1.5px solid ${T.border}`,
                                fontSize: 14, color: T.text,
                                background: "white", outline: "none",
                            }}
                        />
                    </div>

                    {/* ── Error states ── */}
                    {error?.type === "duplicate" && (
                        <div style={{
                            background: "#FEE2E2", border: "1px solid #FECACA",
                            borderRadius: T.radiusSm, padding: "10px 14px", marginBottom: 16,
                        }}>
                            <div style={{ fontSize: 13, fontWeight: 600, color: "#991B1B", marginBottom: 4 }}>
                                Duplicate assessment
                            </div>
                            <div style={{ fontSize: 12, color: "#B91C1C" }}>{error.message}</div>
                            {error.existing && (
                                <button
                                    onClick={() => { onSubmit(error.existing); }}
                                    style={{
                                        marginTop: 8, padding: "6px 12px", borderRadius: T.radiusXs,
                                        background: "#991B1B", color: "white",
                                        border: "none", fontSize: 12, fontWeight: 600, cursor: "pointer",
                                    }}
                                >
                                    Open Existing
                                </button>
                            )}
                        </div>
                    )}
                    {error?.type === "validation" && (
                        <div style={{
                            background: "#FEE2E2", borderRadius: T.radiusSm, padding: "10px 14px",
                            marginBottom: 16, fontSize: 12, color: "#991B1B",
                        }}>
                            {error.message}
                        </div>
                    )}
                    {error?.type === "generic" && (
                        <div style={{
                            background: "#FEE2E2", borderRadius: T.radiusSm, padding: "10px 14px",
                            marginBottom: 16, fontSize: 12, color: "#991B1B",
                        }}>
                            {error.message}
                        </div>
                    )}
                </div>

                {/* Submit */}
                <div style={{ padding: "12px 20px 20px", borderTop: `1px solid ${T.borderLight}` }}>
                    <button
                        onClick={handleSubmit}
                        disabled={!canSubmit}
                        style={{
                            width: "100%", padding: "14px 0", borderRadius: T.radiusSm,
                            background: canSubmit ? T.gradientPrimary : T.borderLight,
                            color: canSubmit ? "white" : T.textMuted,
                            border: "none", fontSize: 15, fontWeight: 700,
                            cursor: canSubmit ? "pointer" : "not-allowed",
                            transition: "all 0.2s",
                            display: "flex", alignItems: "center", justifyContent: "center", gap: 8,
                            boxShadow: canSubmit ? T.shadowMd : "none",
                        }}
                    >
                        {submitting ? (
                            <>
                                <svg width="16" height="16" viewBox="0 0 24 24" style={{ animation: "spin 1s linear infinite" }}>
                                    <circle cx="12" cy="12" r="10" fill="none" stroke="rgba(255,255,255,0.3)" strokeWidth="3" />
                                    <path d="M12 2a10 10 0 019.95 9" fill="none" stroke="white" strokeWidth="3" strokeLinecap="round" />
                                </svg>
                                Starting…
                            </>
                        ) : "Start Assessment"}
                    </button>
                </div>
            </div>

            <style>{`
                @keyframes fadeIn { from { opacity: 0 } to { opacity: 1 } }
                @keyframes slideUp { from { transform: translateY(100%) } to { transform: translateY(0) } }
                @keyframes spin { to { transform: rotate(360deg) } }
            `}</style>
        </>
    );
}
```

- [ ] **Step 4.2: Verify the file lints cleanly**

```bash
npm run lint
```

Expected: no errors for the new file.

- [ ] **Step 4.3: Commit**

```bash
git add src/screens/screen-new-assessment.jsx
git commit -m "feat(ui): add NewAssessmentSheet bottom-sheet component"
```

---

## Task 5: Update screen-assessments-list.jsx — FAB, props, empty state, offline badge

**Files:**
- Modify: `src/screens/screen-assessments-list.jsx`

Four changes: new props, FAB button, updated empty state, `_isOffline` badge on cards.

- [ ] **Step 5.1: Add import and update component signature**

At the top of the file, add the import:

```js
import { NewAssessmentSheet } from "./screen-new-assessment.jsx";
```

Change the component signature from:

```js
export function AssessmentsListScreen({ assessments, sections, onView, loading }) {
```

to:

```js
export function AssessmentsListScreen({ assessments, sections, onView, loading, onCreate, facilities, user }) {
```

- [ ] **Step 5.2: Add `showSheet` state**

After the existing `const [filter, setFilter] = useState("all");` line, add:

```js
    const [showSheet, setShowSheet] = useState(false);
```

- [ ] **Step 5.3: Add the FAB button and NewAssessmentSheet to the JSX**

The component currently returns a single `<div>`. Wrap the entire return in a relative-positioned container and add the FAB + sheet **after** the existing content div:

Change the return statement to:

```jsx
    return (
        <div style={{ height: "100%", position: "relative" }}>
            {/* Existing scrollable content */}
            <div style={{ height: "100%", overflowY: "auto", background: T.bg }}>
                {/* ... all existing JSX unchanged ... */}
            </div>

            {/* FAB */}
            {!showSheet && (
                <button
                    onClick={() => setShowSheet(true)}
                    style={{
                        position: "absolute", bottom: 20, right: 20,
                        width: 52, height: 52, borderRadius: "50%",
                        background: T.gradientPrimary,
                        border: "none", cursor: "pointer",
                        display: "flex", alignItems: "center", justifyContent: "center",
                        boxShadow: T.shadowMd,
                        zIndex: 50,
                        transition: "transform 0.15s",
                    }}
                    onMouseDown={e => e.currentTarget.style.transform = "scale(0.93)"}
                    onMouseUp={e => e.currentTarget.style.transform = "scale(1)"}
                    onTouchStart={e => e.currentTarget.style.transform = "scale(0.93)"}
                    onTouchEnd={e => e.currentTarget.style.transform = "scale(1)"}
                    aria-label="New assessment"
                >
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="2.5" strokeLinecap="round">
                        <line x1="12" y1="5" x2="12" y2="19" /><line x1="5" y1="12" x2="19" y2="12" />
                    </svg>
                </button>
            )}

            {/* New Assessment Sheet */}
            {showSheet && (
                <NewAssessmentSheet
                    facilities={facilities}
                    sections={sections}
                    user={user}
                    onClose={() => setShowSheet(false)}
                    onSubmit={async (assessment) => {
                        setShowSheet(false);
                        if (onCreate) await onCreate(assessment);
                    }}
                />
            )}
        </div>
    );
```

- [ ] **Step 5.4: Update the empty state copy**

Find the empty state text:

```
"Your assessments will appear here once assigned by an administrator."
```

Replace it with:

```
"No assessments yet. Tap + to start one."
```

- [ ] **Step 5.5: Add `_isOffline` "Pending sync" badge to assessment cards**

In the card JSX (inside the `.map()` for the list), find where `<StatusChip status={a.status} />` or `<GradeBadge .../>` is rendered and add an offline badge just above the card's main content area:

```jsx
{a._isOffline && (
    <div style={{
        display: "inline-flex", alignItems: "center", gap: 4,
        padding: "3px 8px", borderRadius: 8,
        background: "#FEF3C7", color: "#92400E",
        fontSize: 10, fontWeight: 700, marginBottom: 6,
        border: "1px solid #FDE68A",
    }}>
        ⏳ Pending sync
    </div>
)}
```

Place this at the **top** of the card's inner content `<div>` (before `facility_name`).

- [ ] **Step 5.6: Verify visually**

`npm run dev` — open the Assessments tab. Confirm:
- FAB appears bottom-right above the bottom nav
- Tapping FAB opens the sheet from below
- Tapping backdrop closes it
- Empty state shows the new copy

- [ ] **Step 5.7: Commit**

```bash
git add src/screens/screen-assessments-list.jsx
git commit -m "feat(ui): add FAB + NewAssessmentSheet to assessments list, update empty state"
```

---

## Task 6: Wire everything in App.jsx

**Files:**
- Modify: `src/App.jsx`

Three changes: `facilities` state + fetch, `handleCreate` callback, `assessment:id-resolved` event listener.

- [ ] **Step 6.1: Add `facilities` state**

After the existing `const [loading, setLoading] = useState(false);` line, add:

```js
    const [facilities, setFacilities] = useState([]);
```

- [ ] **Step 6.2: Load facilities alongside assessments in `runLoadData`**

In `runLoadData`, extend the `Promise.all` to also fetch facilities:

```js
        const [schemaRes, assessRes, facilitiesRes] = await Promise.all([
            api.sections.fullSchema(),
            api.assessments.list(),
            api.facilities.list(),
        ]);
```

After `setAssessments(rawAssessments)`, add:

```js
            const rawFacilities = Array.isArray(facilitiesRes) ? facilitiesRes
                : Array.isArray(facilitiesRes?.data) ? facilitiesRes.data : [];
            setFacilities(rawFacilities);
```

- [ ] **Step 6.3: Add `handleCreate` callback**

After the existing `handleAssessmentComplete` function, add:

```js
    const handleCreate = async (assessment) => {
        if (!assessment?.id) return;
        const enriched = enrichAssessment(assessment);
        setAssessments(prev => [enriched, ...(prev ?? [])]);
        // Open form modal immediately so user can start filling sections
        setModal({ type: "form", data: enriched });
    };
```

- [ ] **Step 6.4: Add the `assessment:id-resolved` event listener**

Add a `useEffect` after the session-restore `useEffect`:

```js
    // ── Resolve offline temp IDs when connectivity returns ────────────────────
    useEffect(() => {
        function handleIdResolved(e) {
            const { tempId, realId } = e.detail ?? {};
            if (!tempId || !realId) return;

            setAssessments(prev =>
                (prev ?? []).map(a => a.id === tempId ? { ...a, id: realId, _isOffline: false } : a)
            );
            setModal(prev => {
                if (!prev?.data) return prev;
                if (prev.data.id === tempId) {
                    return { ...prev, data: { ...prev.data, id: realId, _isOffline: false } };
                }
                return prev;
            });
        }

        window.addEventListener("assessment:id-resolved", handleIdResolved);
        return () => window.removeEventListener("assessment:id-resolved", handleIdResolved);
    }, []); // empty deps — registered once on mount, cleaned up on unmount
```

- [ ] **Step 6.5: Pass new props to `AssessmentsListScreen`**

Find the existing `AssessmentsListScreen` render block:

```jsx
{tab === "assessments" && (
    <AssessmentsListScreen
        assessments={userAssessments}
        sections={sectionsResolved}
        onView={openDetail}
        loading={isLoading}
        />
)}
```

Update it to:

```jsx
{tab === "assessments" && (
    <AssessmentsListScreen
        assessments={userAssessments}
        sections={sectionsResolved}
        onView={openDetail}
        loading={isLoading}
        onCreate={handleCreate}
        facilities={facilities}
        user={user}
        />
)}
```

- [ ] **Step 6.6: Reset facilities on logout and retry**

In `handleLogout`, after `setAssessments(null)`, add:

```js
        setFacilities([]);
```

Also in `handleRetry` (App.jsx lines 147–154), after `setAssessments(null)`, add the same:

```js
        setFacilities([]);
```

This keeps facilities consistent with the reset pattern applied to `assessments` and `sections`.

- [ ] **Step 6.7: Full end-to-end verification**

`npm run dev` — work through these scenarios:

**Online create:**
1. Go to Assessments tab → tap FAB
2. Search for a facility → select it
3. Choose Midline, pick today's date
4. Tap "Start Assessment"
5. Expected: sheet closes, assessment form opens at the first section
6. Go back → new assessment appears in list with `in_progress` status

**409 duplicate:**
1. Tap FAB again for same facility + type
2. Expected: sheet shows "Duplicate assessment" error with "Open Existing" button
3. Tap "Open Existing" → form opens for the existing assessment

**Offline create (simulate):**
1. In DevTools → Network → set to "Offline"
2. Tap FAB → fill in a facility (must already be cached) → submit
3. Expected: sheet closes, assessment form opens with a "Pending sync" badge
4. Set Network back to "Online"
5. Expected: SyncIndicator shows syncing → badge disappears, assessment ID updates

- [ ] **Step 6.8: Lint check**

```bash
npm run lint
```

Expected: no errors.

- [ ] **Step 6.9: Commit**

```bash
git add src/App.jsx
git commit -m "feat: wire assessment creation — facilities state, handleCreate, id-resolved listener"
```

---

## Task 7: Build verification

- [ ] **Step 7.1: Production build**

```bash
npm run build
```

Expected: build completes with no errors. Warnings about bundle size are acceptable.

- [ ] **Step 7.2: Final commit tag**

```bash
git add -A
git commit -m "feat: assessment creation complete — FAB, offline support, sync queue migration" --allow-empty
```

---

## Quick Reference

| What broke | Where to look |
|---|---|
| Sheet doesn't open | `showSheet` state in `screen-assessments-list.jsx` |
| Facilities list empty | `api.facilities.list()` in DevTools Network; `facilities` store in IndexedDB |
| Offline create fails | `api.assessments.create` offline branch in `api.service.js`; check `syncQueue` store in IndexedDB |
| ID not swapping after sync | `sync-queue.js` `assessments.create` case; `assessment:id-resolved` listener in `App.jsx` |
| 409 silently discarded | Confirm `executeOp` catches `e.status === 409` before re-throwing |
| `err.data` undefined in sync | Confirm `err.data = data` patch was applied in `request()` |
