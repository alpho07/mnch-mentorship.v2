# Mobile Mentorship, Assessment, and Training Workflow Plan

## Goal

Add a complete mobile mentorship workflow into `public/m-assessment-app`, using the existing React/Capacitor structure, visual language, offline-first architecture, and sync queue patterns. The mobile app should become a unified field tool where users can access Assessments, Mentorships, and Trainings intuitively from one place.

## Product Shape

The app should keep the current mobile shell, but the main experience should evolve from "assessment app" into a broader MNCH field work app.

Core areas:

1. Assessments
   Existing workflow stays mostly intact.

2. Mentorships
   New workflow for mentors to plan, run, document, and submit mentorship activities.

3. Trainings
   New workflow for viewing assigned trainings, enrolling or participating where applicable, accessing manuals and resources, and tracking completion.

4. Resources
   Mentorship manuals and job aids should be accessible from mentorship and training screens, not hidden in one place.

## Recommended Navigation

The current app uses bottom navigation with Dashboard, Assessments, Reports, Profile, and a floating/new action. For the expanded scope, use a field-work structure:

1. Home
   Unified dashboard with assessment, mentorship, and training summary.

2. Assessments
   Existing assessment list and creation workflow.

3. Mentorships
   New mentorship cohort, class, and session workflow.

4. Trainings
   Training activities and learning resources.

5. Profile
   User profile, sync status, and account tools.

Reports should remain accessible from Home cards and completed assessment or mentorship detail screens, rather than consuming a bottom tab. If the team wants to keep Reports as a tab, Trainings can be reached from Home, but the cleaner product direction is to give field actions priority.

## Home Dashboard Workflow

The Home screen should give the user a quick operational picture.

Sections:

1. Today / Active Work
   - Active assessments
   - Active mentorships
   - Upcoming trainings
   - Pending sync items

2. Quick Actions
   - Start assessment
   - Start mentorship session
   - View mentorship resources
   - Open trainings

3. My Work Summary
   - Assessments completed
   - Mentorship sessions completed
   - Trainings attended or completed
   - Items pending sync

4. Needs Attention
   Offline-aware warning panel showing:
   - Unsynced mentorship notes
   - Incomplete mentorship sessions
   - Assessments not submitted
   - Failed email or report jobs

This should reuse the existing dashboard card structure, gradient header, progress rings, status chips, and pending sync indicator.

## Mentorship Workflow

The mentorship workflow should be designed around how mentors actually work in the field:

1. Identify gaps.
2. Review manuals and resources.
3. Plan what to mentor on.
4. Select or confirm mentees.
5. Conduct mentorship session.
6. Record attendance and observations.
7. Capture competency or progress.
8. Save offline if needed.
9. Sync and submit.

### Mentorship List Screen

This screen shows the mentor's mentorship work, similar to the current assessment list.

Filters:

- All
- Active
- Upcoming
- Completed
- Pending sync

Each mentorship card should show:

- Facility or cohort/class name
- Mentorship type
- Date or date range
- Status
- Number of mentees
- Modules/topics
- Sync status if offline-created or locally updated

Do not show full mentee details on the list. Keep it high-level and tappable.

### Mentorship Detail Screen

Tabs:

1. Guide
   A full-page guide, matching what was added on the web side:
   - Identify the gaps first.
   - Review the mentorship manuals.
   - Decide what the mentorship will focus on.
   - Confirm mentees and their details.
   - Conduct mentorship using the agreed modules.
   - Record session outcomes before submitting.

   Resource links:
   - MNCH mentorship mentor manual
   - Newborn mentorship mentor manual: `https://mnchkenyamentorship.org/resources/newborn-mentorship-mentors-manual`

2. Overview
   - Facility/class/cohort details
   - Mentor
   - Start date/end date
   - Number of mentees
   - Modules assigned
   - Status and progress

3. Mentees
   Show mentee details here, not on the list:
   - Name
   - Email
   - Phone
   - Cadre
   - Department
   - Attendance/session participation status

4. Modules
   - Assigned mentorship modules
   - Topic descriptions
   - Completion status per module
   - Notes per module
   - Gap addressed by module

5. Sessions / Notes
   - Session date
   - Gap identified
   - Activities conducted
   - Demonstrations/practical work
   - Mentor observations
   - Mentee progress
   - Recommendations
   - Follow-up actions

6. Submit / Report
   - Summary of completed modules
   - Mentee attendance
   - Gaps addressed
   - Pending gaps
   - Submit mentorship completion
   - Generate or view mentorship report if backend supports it

## Mentorship Creation Workflow

Use a bottom sheet or full-screen stepper depending on complexity. Since mentorship has more data than assessment creation, a full-screen wizard is better.

### Step 1: Context

- Facility or class/cohort
- Mentorship type
- Start date
- End date if applicable
- Number of mentees
- Description

Description helper text:

> Describe the gap identified and how the mentorship/class will be conducted.

### Step 2: Gap Identification

- Gap category
- Gap description
- Source of gap:
  - Assessment finding
  - Supervisor observation
  - Facility request
  - Routine mentorship
  - Other
- Priority:
  - High
  - Medium
  - Low

This is important because mentorship should not just be a calendar item; it should respond to an identified gap.

### Step 3: Resources

Show manual/resource links before module selection:

- Mentor manual
- Newborn mentorship mentor manual
- Any backend-provided resources

Add "I have reviewed the relevant manual/resource" checkbox if the business process requires it.

### Step 4: Mentees

Add or select mentees with:

- Name
- Email
- Phone
- Cadre
- Department

Offline behavior:

- Allow local mentee creation.
- Assign local IDs.
- Sync mentees when online.
- Reconcile IDs after server creation.

### Step 5: Modules

Select modules for the mentorship/class.

Rules:

- A module cannot be added twice to the same class/cohort.
- The same module can be used in different classes/cohorts.
- Offline validation must enforce the same rule locally.

### Step 6: Review & Start

Show:

- Facility/class
- Gap
- Mentees count
- Modules count
- Resources reviewed
- Start button

If offline:

- Create a local mentorship with an `offline_...` ID.
- Queue mentorship creation.
- Allow the mentor to continue working locally.

## Mentorship Session Workflow

A mentor may conduct multiple sessions under one mentorship/class.

Session fields:

- Session date
- Start time/end time optional
- Location/facility
- Module/topic covered
- Mentees present
- Gap addressed
- Activities conducted
- Practical demonstration done: Yes/No/Partial
- Observations
- Recommendations
- Follow-up actions
- Next session date optional
- Attachments optional, if backend supports files later

Session completion logic:

- A module is complete when required notes/outcomes are saved.
- A mentorship is complete when all required modules/sessions are complete, or when the mentor explicitly marks it complete with a reason.

## Training Workflow

Trainings should be accessible separately but visually connected to mentorship.

### Training List Screen

Filters:

- All
- Upcoming
- Enrolled
- Completed
- Pending sync

Training card:

- Training title
- Date
- Venue/facility/county
- Status
- Modules/resources count
- Attendance/completion status

### Training Detail Screen

Tabs:

1. Overview
   - Title
   - Description
   - Date/time
   - Venue
   - Facilitator
   - Status

2. Resources
   - Same manual links where relevant
   - Training materials from backend
   - Download/cache status

3. Enrollment / Attendance
   - Enroll button if allowed
   - Attendance status
   - Completion status

4. Modules
   - Training modules/topics
   - Completion markers
   - Notes if needed

Offline behavior:

- Cache trainings and resources metadata.
- Queue enrollment and attendance updates if offline.
- Allow reading previously cached resources offline.

## Offline Architecture

Extend the existing offline architecture instead of creating a new one.

Current stores include assessments, responses, HR, HP, facilities, email jobs, schema, user, and sync queue. Add stores like:

- `mentorships`
- `mentorshipMentees`
- `mentorshipModules`
- `mentorshipSessions`
- `mentorshipNotes`
- `trainings`
- `trainingEnrollments`
- `resources`

Increase IndexedDB version from `4` to `5`.

### Offline IDs

Use the existing pattern:

- `offline_${Date.now()}_${random}`

When syncing:

1. Create mentorship on server.
2. Receive real mentorship ID.
3. Copy dependent local data from offline ID to server ID.
4. Dispatch an event like `mentorship:id-resolved`.

### Sync Queue Operations

Add operations:

Mentorship:

- `mentorships.create`
- `mentorships.update`
- `mentorships.submit`
- `mentorships.delete` if allowed
- `mentorships.addMentee`
- `mentorships.updateMentee`
- `mentorships.removeMentee`
- `mentorships.addModule`
- `mentorships.removeModule`
- `mentorships.saveSession`
- `mentorships.completeModule`

Training:

- `trainings.enroll`
- `trainings.attendance`
- `trainings.completeModule`

Resources:

- `resources.markViewed`
- Optional: `resources.cacheDownload`

### Conflict Handling

Use predictable rules:

- If mentorship already exists server-side, return existing ID and migrate local data.
- If the same module is added twice to the same mentorship/class, discard duplicate locally and server-side.
- If local and server versions differ, prefer latest updated timestamp for notes.
- Never drop unsynced notes silently; mark conflict and show "Needs review."

## API Requirements

The Laravel backend needs mobile API endpoints for mentorship and training. The mobile app should not scrape Filament screens.

Suggested `/api/v1` endpoints:

Mentorships:

- `GET /mentorships`
- `POST /mentorships`
- `GET /mentorships/{mentorship}`
- `PUT /mentorships/{mentorship}`
- `POST /mentorships/{mentorship}/submit`
- `GET /mentorships/{mentorship}/mentees`
- `POST /mentorships/{mentorship}/mentees`
- `PUT /mentorships/{mentorship}/mentees/{mentee}`
- `DELETE /mentorships/{mentorship}/mentees/{mentee}`
- `GET /mentorships/{mentorship}/modules`
- `POST /mentorships/{mentorship}/modules`
- `DELETE /mentorships/{mentorship}/modules/{module}`
- `GET /mentorships/{mentorship}/sessions`
- `POST /mentorships/{mentorship}/sessions`
- `PUT /mentorships/{mentorship}/sessions/{session}`

Trainings:

- `GET /trainings`
- `GET /trainings/{training}`
- `POST /trainings/{training}/enroll`
- `POST /trainings/{training}/attendance`
- `POST /trainings/{training}/complete-module`

Resources:

- `GET /resources`
- `GET /resources/mentorship-manuals`
- `POST /resources/{resource}/viewed`

Dashboard:

- `GET /field-dashboard`
- Or extend the current dashboard API to include assessments, mentorships, trainings, and pending counts.

## Frontend Files To Add

Recommended new files:

- `src/screens/screen-home.jsx`
- `src/screens/screen-mentorships-list.jsx`
- `src/screens/screen-mentorship-detail.jsx`
- `src/screens/screen-mentorship-form.jsx`
- `src/screens/screen-mentorship-session.jsx`
- `src/screens/screen-trainings-list.jsx`
- `src/screens/screen-training-detail.jsx`
- `src/components/resource-links.jsx`
- `src/components/mentorship-components.jsx`
- `src/services/mentorship-api.service.js` or extend `api.service.js`
- `src/services/training-api.service.js` or extend `api.service.js`

Given the existing structure, extend `api.service.js` first to preserve one offline-aware API layer, then split later only if it grows too large.

## Frontend State Changes

In `src/App.jsx`, add state for:

- `mentorships`
- `trainings`
- `resources`
- Selected mentorship
- Selected training
- Mentorship creation/edit modal/screen
- Active mentorship session
- Pending mentorship sync state

App bootstrap should load:

1. User
2. Assessment schema
3. Facilities
4. Assessments
5. Mentorships
6. Trainings
7. Resources/manual links

Then prefetch active/offline-needed records:

- Active assessments
- Active mentorships
- Enrolled/upcoming trainings
- Mentorship resources metadata

## Data Model For Mobile Cache

A mobile mentorship object should be normalized enough to work offline:

```js
{
  id,
  facility_id,
  facility_name,
  mentorship_type,
  status,
  start_date,
  end_date,
  mentor_id,
  mentor_name,
  gap_description,
  description,
  mentees_count,
  modules_count,
  completed_modules_count,
  mentees: [],
  modules: [],
  sessions: [],
  resources: [],
  _isOffline,
  _lastSyncedAt,
  updated_at
}
```

Mentee:

```js
{
  id,
  mentorship_id,
  name,
  email,
  phone,
  cadre,
  department,
  attendance_status,
  _isOffline
}
```

Module assignment:

```js
{
  id,
  mentorship_id,
  module_id,
  module_name,
  description,
  status,
  notes,
  gap_addressed
}
```

Session:

```js
{
  id,
  mentorship_id,
  session_date,
  module_id,
  mentee_ids_present,
  gap_addressed,
  activities_done,
  observations,
  recommendations,
  follow_up_actions,
  status,
  _isOffline
}
```

## Access Pattern

The user should not feel like mentorships were bolted onto assessments.

Home should present three clear work streams:

- Assessments: Measure gaps.
- Mentorships: Address gaps.
- Trainings: Build capacity.

Useful dashboard copy:

> Measure gaps, mentor teams, and track capacity building.

For a mentor, the primary path should be:

Home -> Mentorships -> Active Mentorship -> Guide -> Mentees/Modules -> Start Session -> Save/Submit

For an assessor, the primary path remains:

Home -> Assessments -> Start/Continue Assessment -> Submit -> Report

For a trainee or participant:

Home -> Trainings -> Training Detail -> Resources/Enroll/Attendance

## Implementation Phases

### Phase 1: Backend API Contract

- Confirm mentorship/training database models and relationships.
- Add `/api/v1` endpoints.
- Return mobile-friendly JSON.
- Add validation for duplicate modules per mentorship/class only.
- Add resource/manual endpoint.

### Phase 2: Mobile Offline Store

- Add IndexedDB stores.
- Add cache methods for mentorships, mentees, modules, sessions, trainings, resources.
- Add ID migration helpers for offline mentorship creation.

### Phase 3: Mobile API Layer

- Add mentorship and training methods to `api.service.js`.
- Add offline fallbacks for reads.
- Add queueing for writes.
- Add sync operations in `sync-queue.js`.

### Phase 4: Navigation + Home

- Replace dashboard-only mental model with Home.
- Add bottom nav entries for Assessments, Mentorships, Trainings, Profile.
- Keep reports accessible through report cards/details.

### Phase 5: Mentorship Screens

- Build list, detail, create/edit, session capture.
- Add full-page guide and resource links.
- Add offline duplicate-module rule.

### Phase 6: Training Screens

- Build training list and detail.
- Add resources and enrollment/attendance workflow.
- Add offline queue support.

### Phase 7: QA

Test online/offline cases:

- Create mentorship offline.
- Add mentees offline.
- Add modules offline.
- Prevent duplicate module in same mentorship.
- Allow same module in different mentorships/classes.
- Save session notes offline.
- Sync and verify server IDs migrate.
- Enroll in training offline.
- Reopen app while offline and confirm cached data is available.
- Restore connection and verify sync queue clears.

### Phase 8: Android Build

- Build Vite app.
- Sync Capacitor Android.
- Smoke test on Android device/emulator.
- Confirm links/resources open correctly.
- Confirm offline IndexedDB works in WebView.

## Risks To Handle Early

- The backend may currently expose mentorship only through Filament/resources, not API. Mobile needs proper API endpoints.
- Offline conflict handling needs clear business rules before implementation.
- Resource files/manuals may be URLs only; true offline PDF caching may need Capacitor filesystem support.
- The current app has some encoding corruption in JSX text. Touching screens is a chance to normalize new text to clean UTF-8.
- The app currently uses a single large `api.service.js`; mentorship/training expansion may make it large, but keeping the existing pattern first is safer.

## Recommended Build Direction

Build mentorship first, then trainings. Mentorship is the more complex workflow and will force the offline data model, sync queue, dashboard navigation, resource links, and duplicate-module logic into place. Trainings can then reuse the same resource and sync patterns.
