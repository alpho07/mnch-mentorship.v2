# Mobile Mentorship & Training Expansion — Design Spec

**Date:** 2026-04-14  
**Status:** Approved  
**Scope:** Clusters A–D from mentorship-mobile-gap-review.md (deferred items)

---

## Goal

Complete the mobile mentorship and training workflow so a mentor can create a mentorship, run sessions, track attendance, and finalize — all online or offline — and a mentee can view and act on their training assignments. Resources are surfaced from the backend. Home and navigation reflect the full field-work picture.

---

## Architecture

- 12 new API endpoints across 4 new controller files + extensions to existing ones
- 5 new mobile screens; 4 existing screens extended
- 6 new IndexedDB stores; 12 new sync queue operation types
- Key simplification vs web: `ClassModule::autoCreateSessions()` is called server-side automatically during mentorship creation — no manual "Add Sessions" step on mobile

---

## Cluster A — Mentorship Creation, Session Notes, and Mentee Enrollment

### A1. New API Endpoints

All routes sit inside the authenticated `api/v1` group.

#### Lookup endpoints (new controller: `LookupController`)

```
GET  /api/v1/programs
```
Response: `{ data: [{ id, name, description }] }`  
Scope: all active programs. No authorization filter needed.

```
GET  /api/v1/programs/{program}/modules
```
Response: `{ data: [{ id, name, description, order_sequence, session_count }] }`  
`session_count` = count of active `ModuleSession` template rows (helps user understand what will be created).

```
GET  /api/v1/counties
```
Response: `{ data: [{ id, name }] }` — all counties, ordered by name.

```
GET  /api/v1/users/search?q=&facility_id=&role=mentee
```
Response: `{ data: [{ id, name, email, facility_name }] }`  
Scope: users matching the query at the given facility. `role=mentee` filters by mentee role. Results capped at 30. Requires `q` to be at least 2 characters.

#### Mentorship CRUD (new controller: `MentorshipCreateController`)

```
POST /api/v1/mentorships
```
Body:
```json
{
  "program_id": 1,
  "facility_id": 5,
  "start_date": "2026-05-01",
  "end_date": "2026-06-30",
  "max_participants": 20,
  "title": "optional override",
  "module_ids": [1, 2, 3],
  "status": "draft"
}
```
Server actions:
1. Creates `Training` with `type = facility_mentorship`, `mentor_id = auth()->id()`, auto-generates `identifier` as `MT-{random6}`, auto-generates `title` from program + facility + start_date if not supplied.
2. Creates one `MentorshipClass` with `status = draft`, name = `"{program_name} Class 1"`, same dates.
3. Calls `ModuleUsageService::assignModulesToClass()` for each `module_id`.
4. Calls `$classModule->autoCreateSessions()` for each created module.
5. Returns full mentorship object (same shape as `MentorshipController::show()`).

Response: `{ data: { id, title, identifier, status, start_date, end_date, class: { id, name, status, modules: [...] } } }`

```
PUT /api/v1/mentorships/{training}
```
Updatable fields: `title`, `start_date`, `end_date`, `max_participants`.  
Authorization: mentor or above-site only.

```
POST /api/v1/mentorships/{training}/submit
```
Marks mentorship `status = submitted`. Only callable when all classes are `completed`.  
Authorization: mentor only.

#### Class lifecycle (add to existing route group)

```
POST /api/v1/classes/{class}/start
```
Calls `$class->start()`. Returns `{ data: { id, status } }`.  
Requires class status = `draft`, has modules, has enrolled mentees.  
Authorization: `AuthorizesClassAccess` trait.

```
POST /api/v1/classes/{class}/end
```
Calls `$class->end()`. Returns `{ data: { id, status } }`.  
Authorization: `AuthorizesClassAccess` trait.

#### Mentee enrollment (add to existing route group)

```
POST /api/v1/classes/{class}/mentees
```
Body: `{ "user_id": 7 }`  
Server actions: creates `ClassParticipant`, calls `ModuleUsageService::cascadeAllModulesToParticipant()`.  
Authorization: `AuthorizesClassAccess` trait.  
Response: `{ data: { participant_id, user_id, name } }`

```
DELETE /api/v1/classes/{class}/mentees/{participant}
```
Only allowed if participant status is `enrolled` (not started any modules).  
Authorization: `AuthorizesClassAccess` trait.

#### Session notes (new controller: `ClassSessionController`)

```
PUT /api/v1/sessions/{session}
```
Updatable fields: `actual_date`, `actual_time`, `location`, `notes`, `facilitator_id`, `attendance_taken`, `status`.  
`facilitator_id` defaults to `auth()->id()` if not supplied.  
Authorization: session's class module must pass `authorizeModuleAccess`.  
Response: `{ data: { id, title, actual_date, actual_time, location, notes, status } }`

### A2. Mobile Screens

#### `screen-mentorship-form.jsx` — 4-step creation wizard

**Step 1 — Setup**
- Program: dropdown populated from `GET /api/v1/programs` (cached in `meta` store key `programs`)
- Start date / End date: date pickers
- Facility: pre-filled from `user.facility`; editable picker (search `GET /api/v1/facilities?county_id=`)
- Max participants: number input, default 20
- Title: auto-composed from program + facility name + start year; editable

**Step 2 — Modules**
- Load `GET /api/v1/programs/{program_id}/modules` (cached in `meta` store key `program_modules_{id}`)
- Multi-select list; shows module name + session count hint
- Selected modules shown as ordered pills; drag-to-reorder not required
- At least one module must be selected to advance

**Step 3 — Mentees**
- If offline: yellow banner with lock icon — "Turn on mobile data to search and add mentees. You can skip this step and enroll mentees after starting the class." + "Skip for now" button
- If online: search field (`GET /api/v1/users/search?q=&facility_id=`), tap to add, added mentees shown as removable chips
- Step can be skipped

**Step 4 — Review & Save**
- Summary: program, facility, dates, X modules, Y mentees
- Two buttons: **Save as Draft** (status=draft) and **Save & Start Class** (status=draft, then immediate class.start())
- "Save & Start Class" disabled if no mentees enrolled

**Offline behaviour:**
- If network available: POST to `/api/v1/mentorships` immediately
- If offline: store locally with `id: "local_${Date.now()}"`, enqueue `mentorships.create` op
- On sync response: server returns `{ data: { id, ... } }` → update local record and all child `mentorshipMentees`, `mentorshipSessions` records with resolved id

**Navigation:** Opened as a modal from `NewAssessmentSheet` (or a new "New Mentorship" FAB action on the Mentorships tab). On success, navigates to `screen-mentorship-detail` with new mentorship.

#### `screen-session-notes.jsx` — session detail / notes capture

Opened by tapping a session row in `screen-module-detail.jsx`.

Fields (all optional except session identity):
- Session title (read-only, from template)
- Actual date (date picker, defaults to today)
- Start time / End time (time pickers)
- Location (text input)
- Notes / observations (multi-line text)
- Activities conducted (multi-line text)
- Recommendations (multi-line text)
- Follow-up actions (multi-line text)
- Mark attendance taken (toggle → calls existing attendance roster flow)
- Session status: Scheduled / In Progress / Completed (select)

Save: PUT `/api/v1/sessions/{session}` or queue `mentorships.updateSession` if offline.

### A3. Offline Stores (additions to `offline-store.js`)

Bump `DB_VERSION` to `6`. Add stores:

| Store | Key | Purpose |
|-------|-----|---------|
| `mentorshipMentees` | `id` | Enrolled mentees per class (cached + optimistic) |
| `mentorshipSessions` | `id` | Session records per module (cached + notes drafts) |
| `conflicts` | `id` | Failed sync ops needing user review |

### A4. Sync Queue Operations (additions to `sync-queue.js`)

| Op type | Endpoint | Retry policy |
|---------|----------|-------------|
| `mentorships.create` | POST /api/v1/mentorships | Retry on 5xx; discard on 4xx; write to `conflicts` on 422 |
| `mentorships.update` | PUT /api/v1/mentorships/{id} | Retry on 5xx; merge conflict → `conflicts` store |
| `mentorships.submit` | POST /api/v1/mentorships/{id}/submit | Retry on 5xx; 422 → `conflicts` store |
| `mentorships.addMentee` | POST /api/v1/classes/{class}/mentees | Retry on 5xx; 404/422 → `conflicts` store |
| `mentorships.removeMentee` | DELETE /api/v1/classes/{class}/mentees/{p} | Retry on 5xx; discard 404 (already removed) |
| `mentorships.updateSession` | PUT /api/v1/sessions/{id} | Retry on 5xx; **never discard** — always write to `conflicts` on permanent failure |
| `mentorships.startClass` | POST /api/v1/classes/{class}/start | Retry on 5xx; 422 → `conflicts` store |

**ID reconciliation:** When `mentorships.create` syncs successfully and `temp_id` is in the response, the queue processor must update all pending ops that reference the temp id with the real server id, and update the local `mentorships` IndexedDB record.

### A5. Conflict Handling

A conflict record shape:
```json
{
  "id": "conflict_1713088800000",
  "op_type": "mentorships.updateSession",
  "payload": { ... },
  "error": "422 Unprocessable",
  "created_at": "2026-04-14T10:00:00Z",
  "resolved": false
}
```

The Needs Attention panel on Home (see Cluster D) shows unresolved conflicts. User can tap to retry or dismiss.

---

## Cluster B — Training Participant Actions

### B1. New API Endpoints (add to `GlobalTrainingController`)

```
POST /api/v1/trainings/{training}/enroll
```
Creates a `TrainingParticipant` record for the authenticated user if not already enrolled.  
Returns `{ data: { participant_id, status } }`.  
Blocked if training status is not `upcoming` or `active`.  
Authorization: `authorizeTrainingAccess` — above-site or must be eligible (open enrollment).

```
POST /api/v1/trainings/{training}/attendance
```
Body: `{ "date": "2026-05-10" }` (optional, defaults to today)  
Marks the authenticated user's `attendance_status = "present"` on their `TrainingParticipant` record.  
Must be enrolled.  
Returns `{ message: "Attendance recorded." }`.

### B2. Mobile Screen Changes

**`screen-training-detail.jsx`** — extend existing screen:
- Add **Enroll** button when `user is not enrolled AND training.status IN (upcoming, active)`. Calls `POST /api/v1/trainings/{id}/enroll`. On success, update local state + cached training record.
- Add **Mark My Attendance** button when `user is enrolled AND training.status = active AND user.attendance_status != present`. Calls `POST /api/v1/trainings/{id}/attendance`. On success, update local state.
- Both buttons queue offline if no network.

### B3. Sync Queue Operations

| Op type | Endpoint |
|---------|----------|
| `trainings.enroll` | POST /api/v1/trainings/{id}/enroll |
| `trainings.attendance` | POST /api/v1/trainings/{id}/attendance |

Both: retry on 5xx; discard on 409 (already enrolled/attended).

---

## Cluster C — Resource / Manual Links

### C1. API Endpoint (implement `ResourceController::index()`)

```
GET /api/v1/resources?type=mentorship_manual
```
Uses `Resource::accessibleTo($user)` scope.  
Filters by `type` query param when provided.  
Response: `{ data: [{ id, title, description, type, url, file_url }] }`  
`url` = external link if resource is a URL type; `file_url` = storage URL if file type.

Add route: `Route::get('resources', [ResourceController::class, 'index'])->name('resources.index');`

### C2. Mobile Component: `ResourceCard`

A reusable card component (not a screen):
```jsx
<ResourceCard resource={r} />
```
Renders: title, type badge (color-coded), description (truncated), "Open" button → `window.open(url)` or download.

**Embedded in:**
- `screen-mentorship-detail.jsx`: "Resources & Manuals" collapsible section, loads `GET /api/v1/resources?type=mentorship_manual`
- `screen-training-detail.jsx`: "Resources" collapsible section, loads `GET /api/v1/resources?type=training`

### C3. Offline Store

`resources` store (already planned in DB_VERSION 6): cache resource list keyed by id. TTL: 24 hours via `meta` timestamp.

---

## Cluster D — Home & Navigation

### D1. `screen-dashboard.jsx` — Home expansion

New sections (added to existing dashboard structure):

**Needs Attention** (top, only shown when `conflicts.length > 0`):
- Red/orange banner with count: "X items need your review"
- Tap → opens conflict list modal

**Active Mentorships** (already partially implemented; ensure it uses live data from `mentorships` store)

**Upcoming Trainings** (new, for mentee + admin roles):
- Load from `trainings` store (bootstrapped on app start)
- Show next 2 upcoming; "See all" links to Trainings tab

### D2. `App.jsx` — Bootstrap expansion

In the existing `bootstrapData()` function (runs after login and on app resume):
- Existing: loads schema + assessments
- Add for isMentor: load `api.mentorships.list()` → cache to `mentorships` store
- Add for isMentee: load `api.me.classes()` → cache to `myClasses` store
- Add for isMentee OR isAdmin: load `api.trainings.list()` → cache to `trainings` store
- Add for all: load `api.resources.list()` → cache to `resources` store (with 24h TTL guard)

### D3. `constants.js` — Navigation

Current: `Reports` tab added for assessors and mentors.  
Change: keep `Reports` tab only for users who are assessors OR admins. For mentor-only users, surface a "Reports" home card instead (links to mentorship analytics). This prevents the bottom nav from being too crowded for field mentors.

---

## New Files Summary

### Backend (Laravel)

| File | Type |
|------|------|
| `app/Http/Controllers/Api/LookupController.php` | New controller |
| `app/Http/Controllers/Api/MentorshipCreateController.php` | New controller |
| `app/Http/Controllers/Api/ClassLifecycleController.php` | New controller |
| `app/Http/Controllers/Api/ClassSessionController.php` | New controller |

### Mobile (React)

| File | Type |
|------|------|
| `src/screens/screen-mentorship-form.jsx` | New screen |
| `src/screens/screen-session-notes.jsx` | New screen |
| `src/components/ResourceCard.jsx` | New component |

### Modified Files

| File | Changes |
|------|---------|
| `routes/api.php` | 12 new routes |
| `app/Http/Controllers/Api/GlobalTrainingController.php` | +enroll, +attendance |
| `app/Http/Controllers/Api/ResourceController.php` | Implement index() |
| `src/services/offline-store.js` | DB_VERSION 6, +3 stores |
| `src/services/sync-queue.js` | +7 op types, ID reconciliation |
| `src/services/api.service.js` | +8 new method groups |
| `src/screens/screen-mentorship-detail.jsx` | +Resources section |
| `src/screens/screen-training-detail.jsx` | +Enroll, +Attendance, +Resources |
| `src/screens/screen-module-detail.jsx` | +Sessions list with notes tap |
| `src/screens/screen-dashboard.jsx` | +Needs Attention, +Upcoming Trainings |
| `src/App.jsx` | +Bootstrap mentorships/trainings/resources |
| `src/constants.js` | Reports tab nav rule change |

---

## Acceptance Criteria

- Mentor can create a mentorship online; all modules get sessions auto-created server-side.
- Mentor can create a mentorship fully offline; on sync, server ID propagates to all local dependent records.
- Step 3 (mentees) shows data-prompt banner when offline and allows skipping.
- Mentor can record session notes offline; notes are never silently dropped — conflicts surface in Needs Attention.
- Mentee can enroll in a training online or queue the enrollment offline.
- Mentee can mark their own attendance; action queues offline.
- Resources/manuals are visible from mentorship and training detail screens.
- Home shows Needs Attention count when unresolved conflicts exist.
- Bottom nav for mentor-only users does not show a Reports tab (home card instead).
- `DB_VERSION` is `6`; migration is safe and additive.
