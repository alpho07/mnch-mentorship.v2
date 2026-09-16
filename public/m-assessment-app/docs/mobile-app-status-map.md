# M Assessment App Status Map

Date reviewed: 2026-04-15

Scope reviewed: `public/m-assessment-app`, its React/Capacitor source, mobile API service, offline store, sync queue, role/tab logic, and the Laravel API routes/controllers used by the mobile mentorship and global training flows.

## Executive Status

The mobile app has a substantial mentorship and global training implementation started, but it is not currently shippable. The largest blocker is unresolved Git merge conflicts across many source files. Until those are resolved, the app should be treated as not buildable and not testable.

The intended app shape is clear: one field app for assessments, mentorship creation and delivery, mentee class participation, global/MOH training participation, offline capture, and background sync. The feature intent is visible in code, but several flows are partial and several backend/API contracts still need correction.

## Build And Source Health

Done:

- React/Vite/Capacitor app exists under `public/m-assessment-app`.
- Android Capacitor project exists under `public/m-assessment-app/android`.
- App shell exists in `src/App.jsx` with tab navigation, session restore, modal screen stack, and sync indicator.
- Mobile API wrapper exists in `src/services/api.service.js`.
- IndexedDB offline layer exists in `src/services/offline-store.js`.
- Background sync exists in `src/services/sync-queue.js`.
- Existing assessment workflow is still present alongside mentorship/training screens.

Blocking corrections:

- Resolve source conflicts in `src/App.jsx`, `src/services/api.service.js`, assessment screens, health products screen, all mentorship/class/module/mentee screens, and training screens.
- Resolve backend API conflicts in `app/Http/Controllers/Api/MentorshipController.php`, `MentorshipCreateController.php`, and `ClassModuleController.php`.
- Build verification was attempted with `npm run build`, but Vite failed early with a Windows sandbox `spawn EPERM` error before JSX parsing. The conflict markers independently prove the source is invalid.

Files with conflict markers:

- `src/App.jsx`
- `src/services/api.service.js`
- `src/screens/screen-assessment-detail.jsx`
- `src/screens/screen-assessments-list.jsx`
- `src/screens/screen-health-products.jsx`
- `src/screens/screen-mentorships-list.jsx`
- `src/screens/screen-mentorship-form.jsx`
- `src/screens/screen-mentorship-detail.jsx`
- `src/screens/screen-class-form.jsx`
- `src/screens/screen-class-detail.jsx`
- `src/screens/screen-module-picker.jsx`
- `src/screens/screen-module-detail.jsx`
- `src/screens/screen-mentee-manager.jsx`
- `src/screens/screen-session-notes.jsx`
- `src/screens/screen-trainings-list.jsx`
- `src/screens/screen-training-detail.jsx`

## App Architecture Already Present

Done:

- `src/App.jsx`: login/session handling, tab selection, screen/modal routing, sync status display.
- `src/constants.js`: design tokens, assessment helpers, role sets, tab calculation.
- `src/services/api.service.js`: raw API methods plus offline-aware wrapper methods.
- `src/services/offline-store.js`: IndexedDB stores for assessments, responses, HR, HP, user, facilities, email jobs, mentorships, participants, my classes, trainings, attendance, mentorship mentees, sessions, resources metadata, conflicts, and sync queue.
- `src/services/sync-queue.js`: queued writes for assessments, reports, mentorships, modules, attendance, mentee attendance, sessions, and training actions.

Pending:

- Resolve all conflicts and choose one implementation per conflicted screen.
- Run build/lint and Android verification.
- Add a manual QA checklist covering every mentorship/training action.
- Verify Capacitor behavior for offline/online transitions, app resume, IndexedDB, and Android permissions.

Needs correction:

- Role mapping does not match backend fully. Backend uses roles like `national_mentor`; mobile `MENTOR_ROLES` does not include it, and `ADMIN_ROLES` includes `national`.
- Source contains mojibake/encoding corruption in comments/icons. Normalize to UTF-8.
- Confirm design tokens against product requirements before UI polish.

## Backend API Surface Used By Mobile

Done API groups:

- Lookups: programs, program modules, counties, facilities by county, user search.
- Mentorships: list, show, classes, class detail, create, update, submit.
- Class CRUD: create, update, delete.
- Class modules: list, add, remove, start, complete, sessions.
- Attendance: roster, bulk mark, individual mark.
- Participants: class participants, participant progress.
- Class lifecycle: start, end, enroll mentee, remove mentee, enrollment link, regenerate token.
- Session notes: update session.
- Mentee self-service: my classes, class detail, confirm module attendance.
- Global trainings: list, show, participants, enroll, attendance.
- Resources: list.

Pending:

- Formal response-shape documentation. Mobile alternates between `data`, `data.data`, and plain arrays.
- Standard error semantics for sync and UI. Most non-401 4xx sync errors are discarded, which is risky for mentorship data.
- Decide which web/Filament actions must exist in mobile APIs versus remain web-only.

Needs correction:

- Backend API controllers with conflict markers must be fixed.
- Duplicate public `/enroll/{token}` web routes should be cleaned up to one canonical route set.
- Enrollment link behavior must match the business rule: mentee enrollment only after mentor starts the class.
- Attendance source/status semantics need correction so absent records cannot be interpreted as present.

## Mentorship List

Done:

- `screen-mentorships-list.jsx` intends to fetch mentorships through `api.mentorships.list()`.
- Displays mentorship cards, supports search/filter, opens detail, and starts new mentorship creation.
- Cached offline read support exists through `api.service.js` and `offline-store.js`.

Pending:

- Confirm final UI after conflict resolution.
- Add status filters that match backend statuses exactly.
- Distinguish empty state from offline-with-no-cache state.
- Verify role visibility for facility mentor, national mentor, admin, and division.

Needs correction:

- Resolve conflicts.
- Refresh list after create, edit, class add/edit/delete, mentee add/remove, module add/remove, class start/end, and submit.

## New Mentorship Creation

Done:

- `screen-mentorship-form.jsx` is a wizard-style implementation.
- Loads programs, counties, facilities by county, and program modules.
- Searches existing mentees by name, optionally facility filtered.
- Supports selecting modules by checkbox.
- Supports selecting existing mentees.
- Captures start date, end date, and participant count/capacity.
- Review step exists.
- Saves through `api.mentorshipCreate.create`.
- Enrolls selected mentees after creation through `api.classLifecycle.enrollMentee`.
- Optional "start immediately" calls `api.classLifecycle.start`.
- Offline create queues a local mentorship create operation.

Pending:

- No complete "create new mentee" form was found. Current code searches and selects existing users.
- Need exact payload contract for `program_id`, `county_id`, `facility_id`, dates, `max_participants`, and `module_ids`.
- Need decision on whether creation is one transaction for `Training + first class + modules + participants`, since mobile expects `newTraining.class.id`.
- Need rollback/warning behavior if create succeeds but mentee enrollment or class start fails.
- Need visible draft/submitted/approved status if that workflow is required.

Needs correction:

- Resolve conflicts; the file contains competing implementations.
- "Start immediately" must enforce backend start requirements: modules and participants must exist.
- If class start triggers enrollment emails, mobile must show that result. If emails are manual, mobile needs send/resend actions.
- Offline create does not fully reconcile dependent local class/module/mentee/session IDs.

Needs detail:

- Whether mobile creates full mentorships, classes under mentorships, or both.
- New mentee fields, validation, default role, password/verification, and notification rules.
- Whether selected mentees receive email immediately or only when class starts.
- Offline partial-sync behavior.

## Mentorship Detail

Done:

- `screen-mentorship-detail.jsx` fetches mentorship detail through `api.mentorships.find(training.id)`.
- Displays status, program/facility/county, start/end dates, and classes.
- Shows class cards with participant/module counts.
- Opens class detail.
- Has intended add/edit/delete class actions.
- Loads resources through `api.resources.list("mentorship_manual")`.

Pending:

- Co-mentor management is not visible in mobile.
- Mentee progress summaries and report actions are not visible here.
- Class summary, attendance report, certificate/report PDF, and dashboard drilldowns are not visible.

Needs correction:

- Resolve conflicts.
- Guard class delete by status and backend constraints.
- Refresh detail after class changes.
- Confirm `mentorship_manual` resource filter matches backend resource data.

## Class Create/Edit

Done:

- `screen-class-form.jsx` creates classes through `api.mentorships.createClass`.
- Updates classes through `api.mentorships.updateClass`.
- Captures class name, start date, end date, and max participants.

Pending:

- Module selection is separate in `screen-module-picker.jsx`.
- Mentee selection is separate in `screen-mentee-manager.jsx`.
- No offline queue exists for class create/update/delete.
- No full lifecycle status display in the form.

Needs correction:

- Resolve conflicts.
- Validate dates against mentorship dates.
- Restrict editing after class start if backend requires it.
- Validate capacity against enrolled mentee count.

## Class Detail And Lifecycle

Done:

- `screen-class-detail.jsx` loads class detail through `api.mentorships.classDetail(trainingId, classId)`.
- Fallback module loading exists through `api.modules.list(classId)`.
- Displays class metadata.
- Shows modules and mentees tabs.
- Opens module detail.
- Starts modules through `api.modules.start`.
- Completes modules through `api.modules.complete`.
- Deletes not-started modules through `api.modules.remove`.
- Parent hooks exist for adding modules and managing mentees.
- API wrapper has `api.classLifecycle.start(classId)` and `api.classLifecycle.end(classId)`.

Pending:

- Class detail does not clearly expose explicit "Start Class" and "End Class" actions.
- Module start exists, but backend requires class active before module start. Mobile must make class start visible first.
- No visible resend enrollment email action per mentee.
- No visible close/revoke enrollment link action except class end if backend handles it.
- No class dashboard summary, report, or certificate action is visible.

Needs correction:

- Resolve conflicts.
- Add class lifecycle controls by state:
  - draft/not started: Start Class.
  - active: End Class.
  - completed: read-only summary.
- Disable module start until class is active.
- Disable self-enrollment link before class start if that is the required rule.
- Refresh participant/module progress after class end.

Needs detail:

- Does class start send emails to existing mentees?
- Does class start activate public enrollment?
- Can mentors add/remove mentees after start?
- Can mentors add/remove modules after start?
- Can mentors end class if modules are incomplete?
- What exact class status labels should mobile show?

## Module Selection And Module CRUD

Done:

- `screen-module-picker.jsx` loads program modules through `api.lookups.programModules(programId)`.
- Shows available modules for the selected program.
- Prevents duplicate selection using existing module IDs.
- Adds a module to class.
- Shows session count.
- `screen-class-detail.jsx` includes module remove/start/complete actions.

Pending:

- The file has conflicting single-add and multi-select implementations; final behavior must be chosen.
- No module edit/settings screen is visible.
- No module reorder action is visible.
- No module exemption or per-mentee module usage control is visible.
- No offline queue exists for module add/remove.

Needs correction:

- Resolve conflicts.
- Align method names: `modules.add`, `classLifecycle.addModule`, and screen calls should use one canonical API method.
- Either add offline queue for module add/remove or require online.
- Confirm backend duplicate-module rejection and started-class restrictions.

## Module Detail

Done:

- `screen-module-detail.jsx` loads sessions through `api.modules.sessions(mod.id)`.
- Displays module status, session count, and progress.
- Starts module.
- Opens attendance.
- Completes module.
- Displays session cards.
- Opens session notes.

Pending:

- No attendance link/token action is visible.
- No module resource/manual list is visible.
- No per-mentee progress drilldown is visible.
- No session create/delete/duplicate/reorder/reset actions are visible.

Needs correction:

- Resolve conflicts.
- Disable module start if class is not active.
- Disable module completion until attendance/session requirements are satisfied if backend requires that.
- Refresh sessions and module/class status after actions.

## Sessions And Session Notes

Done:

- `screen-session-notes.jsx` saves basic session data through `api.sessions.update(session.id, payload)`.
- Implemented fields are actual date, actual time, location, notes, and status.
- Offline support saves the session locally, queues `mentorships.updateSession`, and stores permanent 4xx failures as conflicts.

Pending:

- No create, delete, duplicate, reorder, or reset session action.
- No end time editing beyond existing display fields.
- No attachments/photos/signatures.
- No structured mentorship documentation fields such as gap addressed, activities, observations, recommendations, action items, follow-up date, or per-mentee remarks.

Needs correction:

- Resolve conflicts.
- Add validation for required session fields.
- Confirm backend status values.
- Add a visible conflict review UI for failed offline session sync.

Needs detail:

- Decide whether mobile session notes are only basic notes or must replace full mentorship session documentation.

## Mentee Management

Done:

- `screen-mentee-manager.jsx` has roster, search, and invite link tabs.
- Loads current mentees from class data.
- Searches users through `api.lookups.userSearch`.
- Adds existing users through `api.classLifecycle.enrollMentee`.
- Removes mentees through `api.classLifecycle.removeMentee`.
- Fetches enrollment link through `api.classLifecycle.enrollmentLink`.
- Copies enrollment link.
- Regenerates enrollment token through `api.classLifecycle.regenerateToken`.
- Offline queue exists for add/remove mentee.

Pending:

- No complete "create new mentee" flow was found.
- No explicit email send/resend button was found.
- No per-mentee invitation delivery status was found.
- No per-mentee enrollment status detail was found.
- No bulk import/add from facility list was found beyond search and individual add.
- No UI was found for whether a mentee clicked a link, verified account, accepted enrollment, or joined class.

Needs correction:

- Resolve conflicts.
- Align removal IDs. The API route deletes by participant, while mobile data may expose `id`, `user_id`, or participant IDs.
- Enforce class status rules. Invite link and self-enrollment should be hidden/disabled before class start if required.
- Define add/remove rules after class start and after completion.
- Make online-only actions explicit for search and token generation.

Needs detail:

- Add existing user from searchable list.
- Create new mentee.
- Bulk add from facility user list.
- Share public link.
- Resend invite email.
- Remove mentee.
- Re-add removed mentee.

Each mode needs required fields, email behavior, offline behavior, and status messages.

## Enrollment Link And Email Flow

Done:

- Mobile can fetch enrollment link.
- Mobile can regenerate enrollment token.
- Mobile can copy/share the link manually.
- Backend has public `/enroll/{token}` route intent.

Pending:

- Mobile does not appear to send/resend enrollment email.
- Mobile does not expose email delivery status.
- Mobile does not show whether link is active, expired, revoked, or waiting for class start.
- Mobile relies on web enrollment link behavior rather than an in-app enrollment flow.

Needs correction:

- Enforce enrollment only after class start.
- Clean duplicate public `/enroll/{token}` web routes.
- Clarify token regeneration: old link invalidation, audit trail, and whether already invited mentees are notified.
- Add resend invite endpoint/UI if required.

Required lifecycle detail:

1. Mentor adds existing or new mentee.
2. System decides whether to send email immediately or wait for class start.
3. Mentor starts class.
4. Enrollment link becomes active.
5. Mentee receives email or copied link.
6. Mentee opens link.
7. New/unauthenticated user verifies account or sets password.
8. Mentee accepts enrollment.
9. Mentee sees class in "My Classes".
10. Mentor can resend link or remove mentee.

## Attendance

Done:

- `screen-attendance-roster.jsx` loads roster through `api.attendance.roster(moduleId)`.
- Marks participant attendance through `api.attendance.mark(moduleId, participantId, status)`.
- API service supports bulk attendance.
- Offline queue exists for individual attendance marks.
- `screen-class-progress.jsx` lets a mentee confirm own attendance for an in-progress module.

Pending:

- Attendance screen is minimal and needs final present/absent/late/excused status support.
- Bulk attendance UI is not clearly visible.
- Attendance link/QR/self-confirmation flow is not fully exposed for mentors.
- No visible offline conflict handling for attendance clashes.

Needs correction:

- Backend attendance semantics need correction so absent records cannot be read as present.
- Mobile should show synced/queued/failed attendance state.
- Mobile should prevent marking attendance when module is not in progress or attendance window is closed.
- Mentee self-attendance must be limited to valid active class/module windows.

## Mentee My Classes And Progress

Done:

- `screen-my-classes.jsx` fetches mentee classes through `api.me.classes()`.
- Shows progress percentage and module count.
- Opens class progress.
- Offline cache exists.
- `screen-class-progress.jsx` fetches class detail through `api.me.classDetail(cls.id)`, displays modules, shows module status, and confirms attendance for in-progress modules.

Pending:

- No detailed session schedule view for mentees was found.
- No certificate/completion artifact view was found.
- No feedback/evaluation form was found.
- No in-app enrollment acceptance screen was found.
- No push notification or in-app notification for class/module start was found.

Needs correction:

- Align mentee role names with backend.
- Block attendance outside valid windows.
- Ensure progress percentages match backend participant/module progress calculations.

## Global/MOH Training List

Done:

- `screen-trainings-list.jsx` fetches trainings through `api.trainings.list()`.
- Displays training cards with status, location type, and dates.
- Supports search/filter.
- Opens training detail.
- Offline cached reads exist.

Pending:

- Mobile appears participant-focused. It does not implement full admin CRUD from `GlobalTrainingResource`.
- No mobile create/edit/duplicate/delete global training screens were found.
- No mobile training assessment management screens were found.
- No participant import or admin participant management screen was found.

Needs correction:

- Resolve conflicts.
- Decide whether admins should manage global trainings from mobile or only view them.
- Align role access with backend roles: super admin, division, admin, national mentor, mentee.

## Global/MOH Training Detail

Done:

- `screen-training-detail.jsx` fetches detail through `api.trainings.find(training.id)`.
- Fetches participants through `api.trainings.participants(training.id)`.
- Fetches resources through `api.resources.list("training")`.
- Shows training information, participants, and resources.
- Enrolls through `api.trainingActions.enroll(training.id)`.
- Marks attendance through `api.trainingActions.attendance(training.id)`.
- Offline queue exists for training enroll and attendance.

Pending:

- No full admin participant CRUD.
- No manual attendance roster for admin/facilitator.
- No training session/day schedule management.
- No pre/post assessment assignment or completion workflow.
- No certificate/completion view.
- No duplicate, delete, or cancel training action.
- No approved training area selection or location editing.

Needs correction:

- Resolve conflicts.
- Gate training attendance by training status/date/window.
- Participant list should distinguish invited, enrolled, in progress, completed, absent, cancelled, and attended if backend supports those states.

## Resources And Manuals

Done:

- API route exists: `GET /api/v1/resources`.
- API client has `resources.list`.
- Offline store has resource metadata cache methods.
- Mentorship detail loads `mentorship_manual` resources.
- Training detail loads `training` resources.

Pending:

- No full mobile resource browser was found.
- No download/open/preview state was confirmed.
- No offline file caching was found; current cache appears metadata-focused.
- No resource-to-program/module mapping was confirmed.

Needs correction:

- Confirm backend resource filter values match mobile strings.
- Add Android file download/open handling if manuals are required offline.
- Add stale-cache and refresh behavior.

## Offline And Sync

Done offline stores:

- assessments, responses, HR, health products, user, facilities, email jobs,
- mentorship list/detail/classes,
- participants,
- mentee classes,
- global trainings,
- module attendance,
- mentorship mentees,
- mentorship sessions,
- resources metadata,
- conflicts,
- sync queue.

Done queued operations:

- assessment response save, assessment progress, assessment submit, assessment create,
- HR save, health products save,
- report email,
- module start and complete,
- attendance mark,
- mentee attendance confirm,
- mentorship create, update, submit,
- add/remove mentee,
- update session,
- start class,
- training enroll and attendance.

Pending:

- No queue operation for class create/update/delete.
- No queue operation for module add/remove.
- No queue operation for class end.
- No queue operation for enrollment link/token regeneration.
- No queue operation for resend invite email.
- No queue operation for global training admin CRUD.
- No visible conflict resolution UI was found.
- Local-to-server ID reconciliation is clear for assessments and mentorship create, but not for dependent local class/module/mentee/session records.

Needs correction:

- Do not generically discard all non-401 4xx sync errors. Some should create visible conflicts.
- Add a durable outbox UI showing pending, synced, failed, and conflict items.
- Define which actions are allowed offline and which require online.

## Dashboards, Reports, And Summaries

Done:

- Assessment dashboard/reporting features exist in the broader app.
- Laravel has web dashboards and report routes for training/mentorship analytics.
- API has aggregate assessment reports.

Pending mentorship mobile screens:

- mentor dashboard summary,
- class summary,
- module summary,
- mentee progress detail,
- attendance report,
- certificates,
- analytics by county/facility/program,
- co-mentor dashboard,
- admin/national mentorship dashboard.

Pending global training mobile screens:

- admin training dashboard,
- participant completion dashboard,
- training attendance reports,
- assessment result reports,
- certificate exports.

Needs detail:

- Decide which dashboards must be native in mobile and which can link to web reports.
- For each dashboard define role visibility, filters, metrics, drilldowns, offline needs, and export/share needs.

## Feature Matrix

| Area | Current mobile status | Backend/API status | Action needed |
| --- | --- | --- | --- |
| Login/session | Present | Existing API assumed | Verify after conflicts |
| Role tabs | Present | Roles mismatch | Add `national_mentor`, align names |
| Mentorship list | Present but conflicted | API present | Resolve conflicts, verify filters |
| New mentorship | Present but conflicted | API present, controller conflicted | Define transaction/offline behavior |
| Add existing mentee | Present | API present | Fix ID handling and state rules |
| Create new mentee | Not found | Not confirmed | Design/API needed |
| Enrollment link | Present | API present | Enforce class-start rule |
| Resend invite email | Not found | Not confirmed | Add endpoint/UI if required |
| Class create/edit/delete | Present but conflicted | API present | Add offline queue or require online |
| Start/end class | API wrapper present, UI incomplete | API present | Add lifecycle UI |
| Module add/remove | Present but conflicted | API present, controller conflicted | Resolve and choose single/multi add |
| Module start/complete | Present | API present | Gate by class/module state |
| Sessions list | Present | API present | Verify response shape |
| Session notes | Basic only | API present | Add structured fields if required |
| Attendance roster | Present minimal | API present | Fix attendance semantics/statuses |
| Mentee my classes | Present | API present | Add enrollment/progress details |
| Global training list | Present but conflicted | API present | Verify role access |
| Global training enroll | Present | API present | Gate by status/capacity |
| Global training attendance | Present | API present | Gate by date/status |
| Global training admin CRUD | Not found | Web exists, mobile API incomplete | Decide mobile scope |
| Resources/manuals | Partial | API present | Add download/offline file handling |
| Offline reads | Partial | IndexedDB | Expand coverage and stale states |
| Offline writes | Partial | Sync queue | Add missing ops and conflict UI |
| Reports/dashboards | Mostly missing for mentorship/training | Web routes exist | Define mobile dashboard scope |

## Priority Fix Order

1. Resolve all source conflict markers in `public/m-assessment-app/src`.
2. Resolve backend API conflict markers in mentorship/mobile controllers.
3. Run build/lint and fix compile errors.
4. Verify login and role-based tabs with real backend roles.
5. Verify mentorship list/detail/create against real API responses.
6. Add explicit class start/end UI and enforce class-start-before-enrollment.
7. Complete mentee management: existing list, new mentee, invite email, resend, remove, enrollment status.
8. Complete module management: add, remove, start, complete, sessions, attendance, state gates.
9. Expand session documentation or confirm basic notes are enough.
10. Correct attendance semantics and add queued/failed state display.
11. Decide global training mobile scope: participant-only, admin CRUD, or both.
12. Add reports/dashboard screens or web report links by role.
13. Add offline outbox and conflict resolution UI.
14. Validate Android build and offline/online field behavior.

## Main Product Decisions Still Needed

- Should mobile mentors create full mentorships, classes under existing mentorships, or both?
- Should new mentees be creatable on mobile?
- Are enrollment emails sent when mentees are added, when class starts, or manually by resend?
- Can mentees self-enroll only after class start? The requirement says yes; backend and mobile must enforce it.
- Can mentors add/remove mentees after class start?
- Can mentors add/remove modules after class start?
- What exactly completes a class?
- What exact session documentation is mandatory?
- Which reports/dashboards must be native in mobile?
- Is global training mobile participant-only, admin-only, or both?
- Which actions must work offline?

## Bottom Line

The app is beyond a blank scaffold: most major mentorship and training screens have been started, the API wrapper covers a broad route set, and offline storage/sync foundations exist. The current state is still incomplete and unstable because unresolved merge conflicts make the source invalid, key backend controllers are also conflicted, and several required business workflows are only partially represented.

The next milestone should be an end-to-end mentorship path:

1. Create mentorship/class.
2. Select modules.
3. Add existing or new mentees.
4. Start class.
5. Send/share enrollment link only after start.
6. Mentee enrolls and sees class.
7. Mentor starts modules.
8. Sessions are documented.
9. Attendance is recorded.
10. Modules/classes complete.
11. Dashboards/reports show progress.

After that path works, expand global training admin features, dashboards, certificates, resource downloads, and richer offline conflict handling.
