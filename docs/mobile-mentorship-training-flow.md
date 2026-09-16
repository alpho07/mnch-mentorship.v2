# Mobile Development Process Map: Mentorships and MOH Trainings

This document maps the behavior found in `MentorshipTrainingResource`, `GlobalTrainingResource`, and the related models, services, routes, controllers, and mail classes. It is intended as a mobile development reference.

## 1. Main Areas

### Mentorships

Resource: `app/Filament/Resources/MentorshipTrainingResource.php`

Model: `Training` with `type = facility_mentorship`.

Core flow:

1. Create mentorship for county, facility, program, dates, and mentee capacity.
2. Create one or more classes/cohorts.
3. Add program modules to each class.
4. Auto-create or manually manage module sessions.
5. Add mentees from existing users or create a new mentee.
6. Generate enrollment link and send/resend invitations.
7. Start class or module.
8. Mentees confirm attendance while module is in progress.
9. Mentor marks attendance manually if needed, writes recommendations, completes modules, and ends class.
10. Dashboards/reports show progress by mentorship, class, module, and mentee.

### MOH Trainings

Resource: `app/Filament/Resources/GlobalTrainingResource.php`

Model: `Training` with `type = global_training`.

Core flow:

1. Create MOH training with approved area, leadership, dates, capacity, and location.
2. Add participants from existing users, quick-create a user, or import CSV.
3. Optionally configure assessment categories and weights.
4. Track participant status, attendance, and assessment outcomes.

## 2. Access Rules

Mentorship web access is registered for `super_admin`, `admin`, `division`, `facility_mentor`, and `national_mentor`.

MOH training web access is registered for `super_admin`, `division`, `admin`, and `national_mentor`.

For mentorship listing, above-site roles see all records. Other mentor roles see only records where `mentor_id` is the current user.

Mobile API routes live under `/api/v1`, use Sanctum, and are protected by `auth:sanctum` and `api.active`.

## 3. Core Data Model

### Training

Important fields:

- `type`: `facility_mentorship` or `global_training`
- `title`, `description`, `identifier`, `status`
- `program_id`, `facility_id`, `county_id`, `mentor_id`
- `start_date`, `end_date`, `max_participants`
- Global-only fields include `approved_training_area_id`, `lead_type`, `lead_division_id`, `location_type`, `online_link`, `assess_participants`, and `provide_materials`

Important relationships:

- `program`, `facility`, `county`, `mentor`
- `mentorshipClasses`, `coMentors`, `acceptedCoMentors`
- `participants`, `assessmentCategories`
- `counties`, `partners`, `hospitals`, `hotels`

### MentorshipClass

Fields:

- `training_id`
- `name`
- `start_date`, `end_date`
- `status`: `draft`, `active`, `completed`, `cancelled`
- `created_by`
- `notes`
- `enrollment_token`
- `enrollment_link_active`

Computed attributes:

- `module_count`
- `session_count`
- `completed_modules_count`
- `progress_percentage`
- `duration_days`

Lifecycle:

- `canStart()`: class must be `draft`, have modules, and have participants.
- `start()`: sets class to `active`, starts all not-started modules, opens module attendance links, and creates progress rows.
- `canEnd()`: class must be `active`.
- `complete()`: completes in-progress modules, disables enrollment link, sets class completed, and marks enrolled/active participants completed.
- `cancel()`: sets class cancelled and closes class/module links.
- `canBeDeleted()`: blocked if completed sessions exist.

### ClassModule

Fields:

- `mentorship_class_id`
- `program_module_id`
- `status`: `not_started`, `in_progress`, `completed`
- `started_at`, `completed_at`
- `requires_assessment`
- `min_attendance_percentage`
- `order_sequence`
- `notes`
- `attendance_token`
- `attendance_link_active`

Lifecycle:

- `canStart()`: module is `not_started` and parent class is `active`.
- `start()`: sets `in_progress`, generates attendance token if missing, opens attendance link, and creates mentee progress placeholders.
- `canComplete()`: module is `in_progress`.
- `complete()`: sets `completed`, closes attendance link, marks present mentees completed, and leaves absent mentees `not_started`.
- `canBeRemoved()`: module is `not_started` and has no sessions or progress rows.

Attendance display uses `MenteeModuleProgress` status `in_progress` or `completed` as the main source of truth.

### ClassSession

Fields:

- `class_module_id`, `module_session_id`
- `session_number`, `title`, `description`
- `scheduled_date`, `scheduled_time`
- `actual_date`, `actual_time`
- `duration_minutes`
- `facilitator_id`
- `location`
- `status`: `scheduled`, `in_progress`, `completed`, `cancelled`
- `attendance_taken`
- `notes`

Web actions:

- Add session
- Edit if not completed
- Duplicate
- Delete if scheduled
- Bulk delete
- Reset from template if module has not started

### ClassParticipant

Fields:

- `mentorship_class_id`
- `user_id`
- `status`: `enrolled`, `active`, `completed`, `dropped`
- `enrolled_at`
- `invitation_sent_at`
- `completed_at`, `dropped_at`, `drop_reason`

Actions:

- Add existing user
- Create new user and enroll
- Update missing email
- Send/resend invitation
- Remove mentee
- Bulk remove
- Bulk send invitations

### MenteeModuleProgress

Fields:

- `class_participant_id`
- `class_module_id`
- `status`: `not_started`, `in_progress`, `completed`, `exempted`
- `started_at`, `completed_at`, `exempted_at`
- `completed_in_previous_class`
- `attendance_percentage`
- `assessment_score`
- `assessment_status`
- `notes`

Meaning:

- `not_started`: not confirmed for the module.
- `in_progress`: confirmed present or manually marked present.
- `completed`: module completed and mentee was present.
- `exempted`: mentee completed this program module previously.

Important risk: some Filament code writes recommendation fields to `MenteeModuleProgress`, but inspected model fillable/migrations do not show those fields clearly. Mobile should not rely on recommendations until backend schema is confirmed.

### ClassAttendance

Purpose: audit record for attendance.

- `class_module_id = null`: enrollment-level attendance.
- `class_module_id != null`: module-level attendance.
- Sources used in code include `auto`, `manual`, and `link`.

Risk: the migration inspected defines only `auto` and `manual`, while web public attendance uses `link`. Confirm the production enum supports `link`.

## 4. New Mentorship Flow

Web form fields in `MentorshipTrainingResource`:

- Hidden `type = facility_mentorship`
- Hidden `mentor_id = auth()->id()`
- `county_id`: required, searchable, resets facility on change
- `facility_id`: required, filtered by county, label shows MFL code and facility name
- `program_id`: required mentorship program
- `start_date`: required, min today in web UI
- `end_date`: required, after start date
- `max_participants`: numeric, default 20

Mobile API:

`POST /api/v1/mentorships`

Body:

```json
{
  "program_id": 1,
  "facility_id": 10,
  "county_id": 5,
  "start_date": "2026-05-01",
  "end_date": "2026-05-30",
  "max_participants": 20,
  "title": "Optional title",
  "module_ids": [1, 2, 3]
}
```

Backend behavior:

- Creates a `facility_mentorship` training.
- Sets mentor to authenticated user.
- Sets status `draft`.
- Generates identifier `MT-{random}`.
- Auto-generates title if not provided.
- Creates a default class named `{program} - Class 1`.
- If `module_ids` are provided, assigns those modules and auto-creates sessions.

Mobile lookups:

- `GET /api/v1/programs`
- `GET /api/v1/programs/{program}/modules`
- `GET /api/v1/counties`
- `GET /api/v1/counties/{county}/facilities`
- `GET /api/v1/users/search`

Important backend issue: `MentorshipCreateController.php` currently contains unresolved Git conflict markers around `county_id`.

## 5. Mentorship List, View, Edit, Submit

List:

`GET /api/v1/mentorships`

Returns id, title, status, class count, dates, facility, county, program, and mentor.

Show:

`GET /api/v1/mentorships/{training}`

Returns full mentorship details plus embedded classes with module count, participant count, and progress.

Update:

`PUT /api/v1/mentorships/{training}`

Current API fields:

- `title`
- `start_date`
- `end_date`
- `max_participants`

Submit:

`POST /api/v1/mentorships/{training}/submit`

Rules:

- Only lead mentor.
- All classes must be completed.
- Sets status to `submitted`.

## 6. Class/Cohort Flow

Create class web fields:

- `name`: required
- `start_date`: required, within mentorship period
- `end_date`: required, within mentorship period and after/equal start
- `description`: UI field, but model uses `notes`; confirm backend column mapping

API:

`POST /api/v1/mentorships/{training}/classes`

```json
{
  "name": "Class 1",
  "start_date": "2026-05-01",
  "end_date": "2026-05-30",
  "notes": "Gap and delivery notes"
}
```

Class list:

`GET /api/v1/mentorships/{training}/classes`

Class detail:

`GET /api/v1/mentorships/{training}/classes/{class}`

Update:

`PUT /api/v1/mentorships/{training}/classes/{class}`

Delete:

`DELETE /api/v1/mentorships/{training}/classes/{class}`

Delete is blocked if completed sessions exist.

Start class:

`POST /api/v1/classes/{class}/start`

Start requires:

- class is `draft`
- at least one module
- at least one mentee

Start effects:

- class becomes `active`
- all not-started modules become `in_progress`
- module attendance links open
- progress rows are created
- enrollment link remains open for late joiners

End class:

`POST /api/v1/classes/{class}/end`

End effects:

- in-progress modules complete
- module attendance links close
- enrollment link closes
- class becomes `completed`
- enrolled/active participants become `completed`

## 7. Module Flow

Web add modules fields:

- `module_ids`: checkbox list of available program modules
- `auto_create_sessions`: default true
- `notes`: present in form, not currently applied by service

Available modules are active modules for the mentorship program, excluding modules already assigned to this class. The same module can still be used in another class.

API list modules:

`GET /api/v1/classes/{class}/modules`

API add module:

`POST /api/v1/classes/{class}/modules`

```json
{
  "program_module_id": 1
}
```

Current API adds one module at a time. Web supports multi-select.

Start module:

`POST /api/v1/modules/{module}/start`

Complete module:

`POST /api/v1/modules/{module}/complete`

Remove module:

`DELETE /api/v1/modules/{module}`

Web edit module settings:

- `min_attendance_percentage`
- `requires_assessment`
- `order_sequence`
- `notes`

Mobile gap: no API currently edits module settings.

Important backend issue: `ClassModuleController.php` currently has unresolved Git conflict markers.

## 8. Session Flow

Auto-create sessions copies active `module_sessions` into `class_sessions`:

- template `name` becomes `title`
- `time_minutes` becomes `duration_minutes`
- `order_sequence` becomes `session_number`
- status defaults to `scheduled`

Web add/edit session fields:

- `title`
- `duration_minutes`
- `methodology_id`
- `scheduled_date`
- `scheduled_time`
- `location`
- `description`
- `notes`

API:

- `GET /api/v1/modules/{module}/sessions`
- `PUT /api/v1/sessions/{session}`

API update fields:

- `actual_date`
- `actual_time`
- `location`
- `notes`
- `facilitator_id`
- `attendance_taken`
- `status`: `scheduled`, `in_progress`, `completed`

Mobile gaps:

- create session
- delete session
- duplicate session
- reset from template
- reorder sessions

Risk: session form includes `methodology_id`, but inspected `ClassSession` model fillable does not include it and create action does not save it.

## 9. Mentee Management Flow

### Add from list

Web:

- Search active users by name, phone, email, or facility.
- Already-enrolled users are pre-checked.
- Checking adds users; unchecking removes users.

API:

`POST /api/v1/classes/{class}/mentees`

```json
{
  "user_id": 123
}
```

Behavior:

- Blocks duplicate enrollment.
- Creates `ClassParticipant`.
- Cascades class modules into `MenteeModuleProgress`.
- Applies exemptions for previously completed modules.

### Add new mentee

Web fields:

- `email`
- `first_name`, `middle_name`, `last_name`
- `phone`
- `cadre_id`
- `department_id`
- `facility_id`

Behavior:

- If email exists, prefill/enroll existing user.
- If email does not exist, create active user, assign `mentee` role, default password `123456`, then enroll.

Mobile gap: no API currently creates a mentee and enrolls them into a class.

### Update email

Web row action appears when a mentee has no email.

Field:

- `email`, unique in users

Mobile gap: no class-specific email update endpoint.

### Remove mentee

Web removes participant plus module progress and assessment results through `EnrollmentService::removeFromClass()`.

API:

`DELETE /api/v1/classes/{class}/mentees/{participant}`

Rules:

- participant must belong to class
- participant status must be `enrolled`

Risk: API deletes only participant; align it with `EnrollmentService::removeFromClass()`.

## 10. Enrollment Link and Email Flow

Web enrollment link action:

- Visible only after class has participants.
- Blocks if any participant has no email.
- Generates token if missing and activates link.
- Existing token can be reactivated.

API get/generate enrollment link:

`GET /api/v1/classes/{class}/enrollment-link`

API regenerate:

`POST /api/v1/classes/{class}/regenerate-token`

Regeneration is blocked for completed/cancelled classes.

Invitation email:

- Mail class: `MenteeEnrollmentInvitationMail`
- View: `resources/views/emails/mentee-enrollment-invitation.blade.php`
- Subject is invite or reminder depending on resend.
- Email includes class, program, facility, mentor, start date, module count, and `/enroll/{token}` link.
- Email tells mentee to enter email, log in, and use default password `123456` if unchanged.

Send/resend behavior:

- Row action sends or resends to one mentee.
- Bulk action sends to selected mentees.
- Header action sends to all, only not-sent, or only already-invited.
- `invitation_sent_at` is updated after successful mail send.

Mobile gap: no API currently sends/resends invitation emails.

## 11. Public Enrollment Flow

Routes:

- `GET /enroll/{token}`
- `POST /enroll/{token}`
- `GET /enroll/{token}/complete`

Current behavior:

1. Token must exist and `enrollment_link_active` must be true.
2. Completed/cancelled classes are blocked.
3. Draft and active classes are currently allowed.
4. Guest enters email.
5. Backend finds existing user by email.
6. If no user exists, mentee is told to contact mentor.
7. Enrollment intent is stored in session.
8. User is redirected to login.
9. After login, backend confirms intent belongs to logged-in user.
10. Participant is created and module progress is cascaded.
11. User is redirected to class progress.

Important requirement mismatch:

The requested behavior says mentees should enroll only if the class has been started by the mentor. Current code allows enrollment before start because it only blocks `completed` and `cancelled`.

Backend change needed for strict behavior:

- In `MenteeEnrollmentController::show()`, `submit()`, and `complete()`, require `class.status === active`.
- Optionally prevent `ClassLifecycleController::enrollmentLink()` from returning an active link for draft classes.

## 12. Attendance Flow

When a module starts:

- `attendance_token` is generated if missing.
- `attendance_link_active = true`.

Mentee self-attendance API:

`POST /api/v1/me/classes/{class}/modules/{module}/attend`

Rules:

- module must be `in_progress`
- attendance link must be active
- user must be enrolled/active in the parent class
- duplicate confirmation is blocked

Effects:

- creates `ClassAttendance`
- updates `MenteeModuleProgress` from `not_started` to `in_progress`

Mentor attendance APIs:

- `GET /api/v1/modules/{module}/attendance`
- `POST /api/v1/modules/{module}/attendance/{participant}`
- `POST /api/v1/modules/{module}/attendance/bulk`

Manual attendance body:

```json
{
  "status": "present"
}
```

Bulk body:

```json
{
  "attendances": [
    { "participant_id": 1, "status": "present" },
    { "participant_id": 2, "status": "absent" }
  ]
}
```

Web attendance page supports:

- activate/deactivate attendance link
- regenerate link
- mark all present
- mark one present
- remove attendance
- write/edit/view recommendation
- filter by attendance status and progress status

Mobile gaps:

- no API for toggling/regenerating module attendance link
- no recommendation API

Important bug risk:

`AttendanceService::markManualModuleAttendance()` currently creates/updates an attendance row even when status is `absent`, while roster status treats the existence of a row as present. This should be fixed before using absent marking on mobile.

## 13. Dashboards and Reports

Web artifacts include:

- `MentorDashboard`
- `MenteeDashboard`
- `MenteeProgress`
- `ModuleSummary`
- `AttendanceReportPage`
- class HTML/PDF report routes
- class certificates
- analytics drilldowns by county, facility, program, and participant

Mobile APIs currently support:

- mentorship list/detail/classes
- class detail
- class modules
- participants
- participant progress
- attendance roster
- mentee class list/detail

Recommended mobile mentor dashboard cards:

- active mentorships
- draft classes missing modules/mentees
- active classes
- modules in progress
- attendance rates requiring action
- invitations sent/not sent

Recommended mobile mentee dashboard:

- enrolled classes
- class status
- module statuses
- attendance confirmation CTA for active modules
- completed/exempted states

## 14. MOH Training Flow

Create MOH training fields:

- Hidden `type = global_training`
- Hidden `mentor_id = auth()->id()`
- `approved_training_area_id`: required
- `title`: required
- `identifier`: required, read-only, unique, auto-generated `TRN-{random}`
- `description`
- `lead_type`: `national`, `county`, `partner`
- National: `lead_division_id`, `counties[]`
- County: `counties[]`
- Partner: `partners[]`, with inline partner creation
- `start_date`, `end_date`, `max_participants`
- `location_type`: `hospital`, `hotel`, `online`
- Hospital: `hospitals[]`
- Hotel: `hotels_data[]` with name, contact, address
- Online: `online_link`

Table actions:

- view
- edit
- manage participants
- manage assessments if configured
- duplicate
- delete
- bulk delete

Participant actions:

- add existing users
- quick add new user
- bulk import CSV
- update participant status
- remove participant
- bulk mark attending
- bulk remove

Participant statuses:

- `registered`
- `attending`
- `completed`
- `dropped`

Assessment behavior:

- assessment categories attach with weight, threshold, required flag, sequence, active flag
- weights must total 100 percent
- result is `pass` or `fail`
- overall status is `PASSED`, `FAILED`, or `INCOMPLETE`
- completed assessment can mark participant completed and set outcome

Mobile MOH APIs:

- `GET /api/v1/trainings`
- `GET /api/v1/trainings/{training}`
- `POST /api/v1/trainings/{training}/enroll`
- `POST /api/v1/trainings/{training}/attendance`
- `GET /api/v1/trainings/{training}/participants`

Mobile MOH gaps:

- no create/update/delete training API
- no admin participant add/remove/status API
- no assessment matrix API
- no duplicate endpoint
- no bulk import endpoint

## 15. Recommended Mobile Screen Map

Mentor mentorship list:

- data: `GET /api/v1/mentorships`
- actions: create, open, filter

New mentorship:

- fields: program, county, facility, start date, end date, max participants, optional title, optional initial modules
- submit: `POST /api/v1/mentorships`

Mentorship detail:

- data: `GET /api/v1/mentorships/{training}`
- actions: edit, create class, open class, submit

Class detail:

- data: class detail, class modules, class participants
- actions: edit/delete class, start/end class, add modules, add/remove mentees, enrollment link

Module detail:

- data: sessions and attendance roster
- actions: start/complete module, mark attendance, manage sessions if APIs are added

Mentee app:

- `GET /api/v1/me/classes`
- `GET /api/v1/me/classes/{class}`
- `POST /api/v1/me/classes/{class}/modules/{module}/attend`

MOH training:

- list/show trainings
- self-enroll
- record attendance
- view participants if allowed

## 16. Backend Risks Before Mobile Build

Fix these before mobile integration:

1. Unresolved conflict markers in `MentorshipController.php`, `MentorshipCreateController.php`, and `ClassModuleController.php`.
2. Enrollment rule mismatch: current code allows draft-class enrollment, but requested behavior requires class to be started.
3. Duplicate `/enroll/{token}` web route definitions exist, including references to missing controller methods before later correct routes.
4. Recommendation fields are used by web code but not confirmed in inspected model/migration.
5. `ClassAttendance.source` values are inconsistent (`auto`, `manual`, `link`).
6. Manual absent marking likely creates a row that roster reads as present.
7. API remove mentee should use `EnrollmentService::removeFromClass()`.
8. Module API supports single add only; web supports checkbox multi-add.
9. Session create/delete/duplicate/reset/reorder APIs are missing.
10. Invitation email send/resend APIs are missing.

## 17. Recommended API Additions

Mentorship/class:

- `POST /api/v1/classes/{class}/mentees/bulk`
- `POST /api/v1/classes/{class}/mentees/create`
- `PATCH /api/v1/classes/{class}/mentees/{participant}/email`
- `POST /api/v1/classes/{class}/invitations/send`
- `POST /api/v1/classes/{class}/mentees/{participant}/invitation`

Modules:

- `POST /api/v1/classes/{class}/modules/bulk`
- `PATCH /api/v1/modules/{module}`
- `POST /api/v1/modules/{module}/attendance-link/toggle`
- `POST /api/v1/modules/{module}/attendance-link/regenerate`
- `GET /api/v1/modules/{module}/summary`
- `PUT /api/v1/modules/{module}/participants/{participant}/recommendation`

Sessions:

- `POST /api/v1/modules/{module}/sessions`
- `DELETE /api/v1/sessions/{session}`
- `POST /api/v1/sessions/{session}/duplicate`
- `POST /api/v1/modules/{module}/sessions/reset-from-template`
- `PATCH /api/v1/modules/{module}/sessions/reorder`

MOH trainings:

- `POST /api/v1/trainings`
- `PUT /api/v1/trainings/{training}`
- `DELETE /api/v1/trainings/{training}`
- `POST /api/v1/trainings/{training}/participants`
- `DELETE /api/v1/trainings/{training}/participants/{participant}`
- `PATCH /api/v1/trainings/{training}/participants/{participant}/status`
- `POST /api/v1/trainings/{training}/participants/import`
- `GET /api/v1/trainings/{training}/assessments`
- `POST /api/v1/trainings/{training}/assessments/bulk-category`
- `PUT /api/v1/trainings/{training}/participants/{participant}/assessments`

