# Mentorship Mobile Gap Review

Date: 2026-04-14

Scope reviewed:

- `public/m-assessment-app/docs/mentorship-mobile-workflow-plan.md`
- `public/m-assessment-app/src`
- `routes/api.php`
- Related Laravel mobile API controllers and attendance service

## Summary

The mobile mentorship/training expansion is partially implemented, but it is not yet the complete offline-first field workflow described in the plan. Current mobile coverage is mostly:

- Mentorship list
- Mentorship class/module navigation
- Module start/complete
- Attendance marking
- Mentee class progress and attendance confirmation
- Read-only global training list/detail

The main gaps are authorization, broken mentorship class loading, missing mentorship creation/session/note workflows, incomplete offline persistence/sync, missing resource/manual support, and training workflows that are mostly read-only.

## Highest Risk Findings

### 1. Mentorship class navigation is currently broken

`screen-mentorship-detail.jsx` calls `api.mentorships.find(training.id)` and expects `data.classes`.

Current backend `MentorshipController::show()` only returns:

- `id`
- `title`
- `status`
- `class_count`

It does not return `classes`.

Impact:

- A mentorship detail screen can show "No classes yet" even when classes exist.
- The primary mentor path can dead-end.

Fix:

- Either call `api.mentorships.classes(training.id)` from the detail screen, or
- Embed classes in `GET /api/v1/mentorships/{training}`.

Relevant files:

- `public/m-assessment-app/src/screens/screen-mentorship-detail.jsx`
- `app/Http/Controllers/Api/MentorshipController.php`

### 2. Several mentorship API routes lack user-scoped authorization

The route file exposes class/module/attendance/participant endpoints, but the controllers do not consistently verify that the authenticated user is authorized for the class/module.

Risky endpoints include:

- `GET /api/v1/classes/{class}/modules`
- `POST /api/v1/modules/{module}/start`
- `POST /api/v1/modules/{module}/complete`
- `GET /api/v1/modules/{module}/attendance`
- `POST /api/v1/modules/{module}/attendance/{participant}`
- `POST /api/v1/modules/{module}/attendance/bulk`
- `GET /api/v1/classes/{class}/participants`
- `GET /api/v1/participants/{participant}/progress`

Impact:

- Any authenticated user who can guess IDs may be able to read or mutate mentorship class/module/attendance data.
- Module state and attendance integrity can be compromised.

Fix:

- Add a shared authorization guard for mentorship class access.
- Apply it to class modules, module start/complete, attendance, participant list, and participant progress endpoints.
- Explicitly distinguish mentor/co-mentor, enrolled mentee, and admin access.

Relevant files:

- `routes/api.php`
- `app/Http/Controllers/Api/ClassModuleController.php`
- `app/Http/Controllers/Api/AttendanceApiController.php`
- `app/Http/Controllers/Api/ParticipantController.php`
- `app/Services/AttendanceService.php`

### 3. Attendance marking can be abused by authenticated users

`AttendanceApiController::mark()` accepts the current authenticated user and passes them as the mentor to `AttendanceService::markManualModuleAttendance()`.

The service verifies that the target mentee is enrolled, but it does not verify that the acting user is a mentor or co-mentor for the class.

Impact:

- Attendance can be marked by users who should not have mentor privileges.

Fix:

- Before calling `markManualModuleAttendance()`, verify the acting user can manage the module's parent mentorship class.
- Also validate that the participant belongs to the same class as the module.

Relevant files:

- `app/Http/Controllers/Api/AttendanceApiController.php`
- `app/Services/AttendanceService.php`

### 4. Global training detail and participants are under-scoped

`GlobalTrainingController::index()` scopes list visibility for non-above-site users, but `show()` and `participants()` do not enforce the same visibility rule.

Impact:

- Any authenticated user may request a known training ID and view details or participant lists.

Fix:

- Reuse the same visibility rule from `index()` in `show()` and `participants()`.
- Above-site roles can see all; regular users should only see trainings where they are registered or otherwise permitted.

Relevant file:

- `app/Http/Controllers/Api/GlobalTrainingController.php`

## Functional Gaps

### 1. Mentorship detail is not the planned tabbed workflow

The plan calls for tabs:

- Guide
- Overview
- Mentees
- Modules
- Sessions / Notes
- Submit / Report

Current implementation mainly shows classes and then modules. Missing:

- Full-page guide
- Manual/resource links
- Mentee details tab
- Session capture
- Gap addressed fields
- Activities conducted
- Observations
- Recommendations
- Follow-up actions
- Submit/report flow

Relevant files:

- `public/m-assessment-app/docs/mentorship-mobile-workflow-plan.md`
- `public/m-assessment-app/src/screens/screen-mentorship-detail.jsx`
- `public/m-assessment-app/src/screens/screen-class-detail.jsx`
- `public/m-assessment-app/src/screens/screen-module-detail.jsx`

### 2. Mentorship creation is not implemented

The plan requires a full-screen wizard with:

- Context
- Gap identification
- Resources reviewed
- Mentees
- Modules
- Review and start
- Offline local creation
- Sync with ID reconciliation

Missing:

- `screen-mentorship-form.jsx`
- `POST /api/v1/mentorships`
- `PUT /api/v1/mentorships/{mentorship}`
- `POST /api/v1/mentorships/{mentorship}/submit`
- Offline `mentorships.create`
- `mentorship:id-resolved` handling

Relevant files:

- `public/m-assessment-app/docs/mentorship-mobile-workflow-plan.md`
- `routes/api.php`
- `public/m-assessment-app/src/services/api.service.js`
- `public/m-assessment-app/src/services/sync-queue.js`
- `public/m-assessment-app/src/App.jsx`

### 3. Session and notes workflow is missing

The plan expects mentors to capture:

- Session date
- Optional start/end time
- Location/facility
- Module/topic covered
- Mentees present
- Gap addressed
- Activities conducted
- Practical demonstration status
- Observations
- Recommendations
- Follow-up actions
- Optional next session date

Current implementation has module start/complete and attendance, but not session note capture.

Fix:

- Add mobile API endpoints for sessions/notes.
- Add offline stores for mentorship sessions and notes.
- Add a session capture screen.
- Require or validate notes before module/mentorship completion if the business rule requires it.

### 4. Training workflow is mostly read-only

The plan expects training list/detail with:

- Filters
- Resources/materials
- Enrollment
- Attendance
- Modules
- Completion tracking
- Offline queueing

Current training screens show list/detail and participants only.

Missing:

- Enrollment action
- Training attendance action
- Training module completion
- Training resources/materials
- Offline training enrollment/attendance queue

Relevant files:

- `public/m-assessment-app/src/screens/screen-trainings-list.jsx`
- `public/m-assessment-app/src/screens/screen-training-detail.jsx`
- `app/Http/Controllers/Api/GlobalTrainingController.php`
- `routes/api.php`

### 5. Training tab is only exposed to admins

`computeTabs()` only adds the `Trainings` tab for admin roles. The workflow plan expects trainees/participants to access trainings.

Impact:

- Non-admin users may not see the training workflow even when assigned/enrolled.

Fix:

- Revisit role-to-tab rules.
- Add `Trainings` for enrolled participants/mentees where appropriate, or expose it through Home cards.

Relevant file:

- `public/m-assessment-app/src/constants.js`

## Offline and Sync Gaps

### 1. IndexedDB stores are thinner than the planned model

The app bumped `DB_VERSION` to `5` and added:

- `mentorships`
- `participants`
- `myClasses`
- `trainings`
- `attendance`

The plan also calls for:

- `mentorshipMentees`
- `mentorshipModules`
- `mentorshipSessions`
- `mentorshipNotes`
- `trainingEnrollments`
- `resources`

Impact:

- The app cannot support the planned offline mentorship creation/session/note workflow yet.

Relevant file:

- `public/m-assessment-app/src/services/offline-store.js`

### 2. Sync queue support is partial

Current mentorship/training-related queued operations include:

- `mentorship.module.start`
- `mentorship.module.complete`
- `mentorship.attendance.mark`
- `mentee.attendance.confirm`

Missing planned operations include:

- `mentorships.create`
- `mentorships.update`
- `mentorships.submit`
- `mentorships.addMentee`
- `mentorships.updateMentee`
- `mentorships.removeMentee`
- `mentorships.addModule`
- `mentorships.removeModule`
- `mentorships.saveSession`
- `mentorships.completeModule`
- `trainings.enroll`
- `trainings.attendance`
- `trainings.completeModule`
- `resources.markViewed`

Relevant file:

- `public/m-assessment-app/src/services/sync-queue.js`

### 3. 4xx sync failures can be discarded silently

`sync-queue.js` discards most non-401 4xx operations. That can be acceptable for some permanent validation failures, but the plan says unsynced notes must never be dropped silently and should be marked as "Needs review."

Impact:

- Future notes/session operations could be lost without user review if implemented on top of the current generic 4xx behavior.

Fix:

- Add conflict records for important user-entered data.
- Treat note/session conflicts as durable review states.
- Show conflicts in a Needs Attention panel.

### 4. Offline attendance/module UI state is not fully durable

When offline, attendance/module actions can be queued and local React state changes, but the updated module/roster state is not consistently persisted back to IndexedDB.

Impact:

- If the app restarts before sync, the user may not see the attendance/module state they recorded offline.

Fix:

- Persist optimistic updates to `attendance`, `mentorships`, `myClasses`, or normalized module stores.
- Rehydrate queued local changes when loading cached data.

## Product and Navigation Gaps

### 1. Home is still assessment-centered

The plan expects Home to show:

- Active assessments
- Active mentorships
- Upcoming trainings
- Pending sync
- Quick actions
- Needs Attention

Current app bootstrap loads schema and assessments first; mentorships, trainings, and resources are loaded inside individual screens instead of as part of unified Home state.

Relevant file:

- `public/m-assessment-app/src/App.jsx`

### 2. Reports still occupy a bottom tab

The plan recommends prioritizing field actions in bottom nav and moving reports to Home cards/detail screens. Current tab logic still adds `Reports` for assessors or mentors.

Relevant file:

- `public/m-assessment-app/src/constants.js`

### 3. Resources/manuals are not wired into mobile

The plan calls for:

- MNCH mentorship mentor manual
- Newborn mentorship mentor manual
- Backend-provided resources
- Resource metadata caching
- Optional viewed/download tracking

Current mobile API routes do not expose `/resources`, and the mobile UI does not include resource links/components.

Relevant files:

- `public/m-assessment-app/docs/mentorship-mobile-workflow-plan.md`
- `routes/api.php`

## Cleanup Gaps

### UTF-8 text corruption

Several mobile files contain mojibake such as `â€¦`, `Â·`, and corrupted icon text.

Impact:

- User-facing copy can render incorrectly.
- Comments and UI labels are harder to maintain.

Fix:

- Normalize touched mobile files to clean UTF-8.
- Replace corrupted punctuation and icons intentionally.

Relevant examples:

- `public/m-assessment-app/src/constants.js`
- `public/m-assessment-app/src/screens/screen-trainings-list.jsx`
- `public/m-assessment-app/src/screens/screen-mentorships-list.jsx`

## Recommended Fix Order

1. Fix authorization on class/module/attendance/participant/training detail endpoints.
2. Fix mentorship detail class loading.
3. Add missing mentorship API contract for create/update/submit, mentee CRUD, module add/remove, and session save/update.
4. Add resource/manual API routes and mobile resource component.
5. Expand offline stores and sync operations for mentorship sessions, notes, mentees, modules, resources, and training actions.
6. Add durable conflict handling for user-entered mentorship notes/sessions.
7. Build the mentorship wizard and session capture screens.
8. Expand training from read-only to enrollment/attendance/resources/modules.
9. Rework Home/navigation so assessments, mentorships, and trainings feel like one field-work app.
10. Normalize UTF-8 text in touched mobile files.

## Suggested Acceptance Checks

- Mentor can open a mentorship and see its real classes.
- Unauthorized users cannot access or mutate class/module/attendance/participant data by ID.
- Non-admin users cannot view training details or participant lists unless permitted.
- Mentor can create a mentorship online and offline.
- Offline mentorship creation receives a server ID and dependent data migrates cleanly.
- Mentor can add mentees/modules offline and sync later.
- Duplicate module rules are enforced offline and online.
- Mentor can save session notes offline and see them after app restart.
- Sync conflicts for notes are visible as "Needs review."
- Trainee/participant can access assigned trainings without admin role.
- Training enrollment/attendance actions queue offline.
- Resource/manual links are visible from mentorship and training screens.
- Android WebView can reopen cached mentorship/training data offline.
