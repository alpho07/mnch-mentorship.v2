# MNCH Mobile App Expansion — Training & Mentorship Modules

**Date:** 2026-04-13  
**Status:** Approved  
**Scope:** Scale `public/m-assessment-app` (React + Capacitor) to support Training and Mentorship modules alongside the existing Assessments module.

---

## 1. Goal

Add Mentor and Mentee views to the existing mobile app without touching or breaking any existing assessment code. Field workers who hold combined roles (e.g., Assessor + Mentor) see a unified app with both sets of functionality. Everything is offline-capable using the same IndexedDB + sync queue pattern already in place.

---

## 2. Architecture — Option A: Additive Tab Extension

The existing flat state machine in `App.jsx` is extended. No router is introduced. Tabs and modals remain the only navigation primitives.

### What stays the same
- `tab` and `modal` state variables
- All existing assessment screens, services, and offline store entries
- `SyncIndicator`, bottom nav rendering logic, `handleTabChange()`
- IndexedDB DB name (`mnch_offline`), existing stores, version is bumped only via `onupgradeneeded`

### What is added
- Role-aware tab list computed at login from `user.roles`, stored in IndexedDB `user` store
- New tabs: `mentorship`, `myClasses`, `trainings`
- New screens under `src/screens/`
- New `api.service.js` namespaces: `api.mentorships`, `api.modules`, `api.attendance`, `api.me`, `api.trainings`, `api.participants`
- IndexedDB version bump: 4 → 5, five new stores
- Six new Laravel API controllers under `app/Http/Controllers/Api/`

---

## 3. Role-Aware Navigation

### Role detection (computed once at login in `App.jsx`)

```
isMentor    = roles includes any of [facility_mentor, spoke_mentor, mentor_lead, spoke_mentor_lead, county_mentor_lead, subcounty_mentor_lead, facility_mentor_lead]
isMentee    = roles includes [mentee]
isAssessor  = roles includes [Assessor]
isAdmin     = roles includes [super_admin, admin, division, national]
```

### Tab lists per persona

| Persona | Tabs (left → right) |
|---|---|
| Assessor only | Dashboard · Assessments · Reports · Profile (+ center FAB for New Assessment) |
| Mentor + Assessor | Dashboard · Assessments · Mentorship · Reports · Profile (FAB removed; New Assessment moves to header button inside Assessments tab) |
| Mentee only | Dashboard · My Classes · Profile |
| Mentor only | Dashboard · Mentorship · Reports · Profile |
| Admin/National | Dashboard · Trainings · Reports · Profile |

The computed tab list is persisted in the `user` store so offline-first sessions use the cached role set.

---

## 4. Dashboard — Unified Scrollable (Option B)

One scrollable screen renders role-specific sections stacked vertically. Each section ends with a "See all →" link that sets `tab` to the relevant module tab.

### Section order for Mentor + Assessor

1. **Overview cards** — total assessments this month, pending sync count, total mentees, modules in progress (4 stat cards, 2×2 grid)
2. **Mentorship summary** — active mentorship name, completion %, next module due date, "Go to Mentorship →"
3. **Recent assessments** — last 3 assessments with status pill, "See all assessments →"
4. **Mentee progress snapshot** — top 3 mentees by completion %, "See all mentees →"
5. **Sync status** — only shown when `syncQueue.pendingCount > 0`

### Assessor-only dashboard (unchanged behaviour)

Renders only the assessment summary + recent assessments sections — identical to current dashboard.

### Mentee-only dashboard

1. Overview: enrolled classes count, completed modules count
2. My active class — current class name, modules completed/total, next module
3. Attendance rate card
4. "Go to My Classes →"

Data sources: `api.mentorships.list()` (mentor), `api.me.classes()` (mentee), `api.assessments.list()` (assessor) — all offline-capable via IndexedDB cache.

---

## 5. Reports — Inner Tabs (Option A)

The `tab="reports"` screen renders a two-segment inner tab control at the top. Selecting a segment swaps the content below without changing the bottom nav tab.

| Inner Tab | Shown to | Contents |
|---|---|---|
| Assessments | Assessor roles | Existing analytics: section averages, score distribution, PDF export, email |
| Mentorship | Mentor roles | Cohort completion rate, attendance rate per module, per-mentee progress table |

- If user has only one role type, the inner tab control is hidden and the single view renders directly (no UI clutter for single-role users).
- Mentorship analytics data comes from `api.mentorships.list()` cached data — computed client-side from the cached tree (no extra API endpoint needed for basic metrics).

---

## 6. Screen Hierarchy — New Screens

All new screens live under `src/screens/`. Modal stacking uses the same `modal = { type, data }` pattern.

### Mentor module (`tab="mentorship"`)

| Screen | Type | API |
|---|---|---|
| `screen-mentorships-list.jsx` | Tab root | `GET /mentorships` |
| `screen-mentorship-detail.jsx` | Modal | `GET /mentorships/{id}` |
| `screen-class-detail.jsx` | Modal (stacked) | `GET /mentorships/{id}/classes/{classId}` |
| `screen-module-detail.jsx` | Modal (stacked) | `GET /classes/{classId}/modules/{moduleId}` |
| `screen-attendance-roster.jsx` | Bottom sheet | `GET /modules/{moduleId}/attendance` |
| `screen-sessions-list.jsx` | Modal | `GET /modules/{moduleId}/sessions` |
| `screen-mentee-progress.jsx` | Modal | `GET /participants/{id}/progress` |

### Mentee module (`tab="myClasses"`)

| Screen | Type | API |
|---|---|---|
| `screen-my-classes.jsx` | Tab root | `GET /me/classes` |
| `screen-class-progress.jsx` | Modal | `GET /me/classes/{id}` |
| `screen-attendance-confirm.jsx` | Bottom sheet | `POST /me/classes/{classId}/modules/{moduleId}/attend` |

### Global Training module (`tab="trainings"`, admin/national roles)

| Screen | Type | API |
|---|---|---|
| `screen-trainings-list.jsx` | Tab root | `GET /trainings` |
| `screen-training-detail.jsx` | Modal | `GET /trainings/{id}` |
| `screen-training-participants.jsx` | Modal | `GET /trainings/{id}/participants` |

---

## 7. Offline-First Data Layer

### IndexedDB — version 4 → 5

Five new stores added in `onupgradeneeded`. Existing stores (schema, assessments, responses, hr, hp, user, syncQueue, meta, facilities, emailJobs) are untouched.

| New Store | Key | Contents |
|---|---|---|
| `mentorships` | `training_id` | Full training + classes + modules tree for mentor |
| `participants` | `class_id` | Mentee roster per class |
| `myClasses` | `class_id` | Enrolled class + module progress for mentee |
| `trainings` | `training_id` | Global/MOH training list |
| `attendance` | `module_id` | Offline attendance state per module |

### Read flow (all new data types)

```
App requests data
  → api.service.js
  → network request
    ✓ success → cache in IndexedDB → return to UI
    ✗ network error → return cached data → UI works normally
    ✗ no cache → return null → empty-state shown (not crash)
```

### Write flow (offline actions)

```
User takes action (start module, mark attendance, confirm attendance)
  → update local IndexedDB state (optimistic)
  → try network
    ✓ success → confirm
    ✗ offline → enqueue in syncQueue with typed operation
      → SyncIndicator badge increments
      → back online: auto-replay → badge clears
```

### Sync queue — new operation types

| Operation | Triggered by |
|---|---|
| `mentorship.module.start` | Mentor starts a module while offline |
| `mentorship.module.complete` | Mentor completes a module while offline |
| `mentorship.attendance.mark` | Mentor marks a mentee present/absent offline |
| `mentee.attendance.confirm` | Mentee self-confirms attendance offline |

Each operation stores `{ type, payload, timestamp, retries }`. The sync queue replay logic in `sync-queue.js` routes each type to the correct `_rawApi` call.

---

## 8. Backend API — New Controllers

All new controllers live in `app/Http/Controllers/Api/` and are registered under the existing `Route::prefix('v1')->middleware(['auth:sanctum', 'api.active'])` group in `routes/api.php`. No business logic is duplicated — all controllers delegate to existing services and models.

### MentorshipController
- `GET /mentorships` — list authenticated user's mentorships (where `mentor_id = me` OR co-mentor). Uses `Training` with `type=facility_mentorship`.
- `GET /mentorships/{id}` — training detail + classes + KPIs (mirrors MentorDashboard loader)
- `GET /mentorships/{id}/classes` — all `MentorshipClass` records
- `GET /mentorships/{id}/classes/{classId}` — class detail + modules + participant count

### ClassModuleController
- `GET /mentorships/{id}/classes/{classId}/modules` — all `ClassModule` records with status, session count, attendance rate
- `POST /modules/{moduleId}/start` — delegates to `ClassModule::start()`
- `POST /modules/{moduleId}/complete` — delegates to `ClassModule::complete()`
- `GET /modules/{moduleId}/sessions` — `ClassSession` records for this module

### AttendanceApiController
- `GET /modules/{moduleId}/attendance` — participant roster with present/absent/not_started per module
- `POST /modules/{moduleId}/attendance/{participantId}` — mark one participant. Body: `{ status: "present"|"absent" }`. Delegates to `AttendanceService::markManualModuleAttendance()`.
- `POST /modules/{moduleId}/attendance/bulk` — mark all at once. Body: `{ attendances: [{participant_id, status}] }`.

### MenteeApiController
- `GET /me/classes` — all `ClassParticipant` records for current user with class + training summary
- `GET /me/classes/{classId}` — class detail + all `MenteeModuleProgress` records + attendance flags
- `POST /me/classes/{classId}/modules/{moduleId}/attend` — self-confirm attendance. Guards: module must be `in_progress`, user must be enrolled. Delegates to `AttendanceService::confirmModuleAttendance()`.

### GlobalTrainingController
- `GET /trainings` — list trainings scoped by role (all for admin, own registrations for participants)
- `GET /trainings/{id}` — training detail: dates, location, program, participant count
- `GET /trainings/{id}/participants` — roster with `completion_status` and `attendance_status`

### ParticipantController
- `GET /classes/{classId}/participants` — all `ClassParticipant` records with user info, completion %, attendance rate
- `GET /participants/{participantId}/progress` — full `MenteeModuleProgress` breakdown for one mentee

---

## 9. Error Handling

| Error | Behaviour |
|---|---|
| Network unreachable (GET) | Silent fallback to IndexedDB cache; if cache empty, show empty-state card with "Connect to sync data" message |
| Network unreachable (POST/PUT) | Optimistic local update + enqueue; `SyncIndicator` shows pending count |
| 401 Unauthorized | Detected in `_rawApi`; clears token + offline store; redirects to login screen |
| 403 Forbidden | Toast error "You don't have permission to do this" |
| 422 Validation error | Field-level error display inline (same as assessment form) |
| Sync replay failure | Operation stays in queue, `lastError` shown in `SyncIndicator` expanded view, user can manually retry or dismiss |
| IndexedDB unavailable | All `offline-store.js` methods fail silently; app runs network-only mode |

---

## 10. Shared Infrastructure Changes

| File | Change |
|---|---|
| `src/App.jsx` | Role detection at login, dynamic tab list computation, new tab cases in render, new modal types |
| `src/services/offline-store.js` | `DB_VERSION` 4 → 5, five new stores in `onupgradeneeded` |
| `src/services/api.service.js` | Three new namespaces: `api.mentorships`, `api.me`, `api.trainings`; two helper namespaces: `api.modules`, `api.attendance`, `api.participants` |
| `src/services/sync-queue.js` | Four new operation type handlers in replay switch |
| `src/constants.js` | `MENTOR_META` (icon/gradient per mentorship type), `MENTEE_META`, tab name constants |
| `src/components/BottomNav.jsx` | Accepts dynamic `tabs` prop instead of hardcoded tab list |
| `routes/api.php` | Six new route groups under existing `v1` + `auth:sanctum` middleware |
| `app/Http/Controllers/Api/` | Six new controller files (listed in §8) |

---

## 11. Out of Scope

- Creating or editing trainings/mentorships from mobile (read + action only; creation stays in Filament admin)
- Push notifications for module start/attendance
- Mentee self-enrollment from mobile (enrollment flow stays on web link)
- Any changes to existing Filament admin panel resources
- PDF generation for mentorship reports (assessment PDF export is unchanged)
