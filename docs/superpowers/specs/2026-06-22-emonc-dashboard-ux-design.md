# EmONC Dashboard, Mentee UX & Activity Completion Tracking — Design Spec

> Date: 2026-06-22
> Project: MNCH Mentorship Platform
> Scope: Maternal Health (EmONC) mentorship enhancements

---

## 1. Goal

Make the EmONC mentorship experience complete, informative, and user-friendly for three audiences:

1. **Mentees** — clearly see what to do next in a module, complete introductions, pre/post tests, case scenarios, and upload hands-on videos.
2. **Mentors** — see a rich EmONC dashboard with KPIs, maps, charts, and tables; mark activities complete per mentee; track exactly which activities block certification.
3. **Program managers / Head DRMH** — get a high-level view of EmONC mentorships, pending approvals, and completion rates.

---

## 2. Context

The EmONC feature set is already implemented through Phase 8. This spec covers the **UX/dashboard layer** that ties it together:

- Existing: `ProgramModule` self-referential tracks, activities, content, quizzes, activity enrollment pivot, activity completion, video review, mentor/Head DRMH approvals, certificates, notifications, mobile API, bulk operations.
- New in this spec:
  - A top-level **Mentorships** navigation group with program-specific dashboards (Infant Care, Newborn Care, Child Care, EmONC).
  - A rich **EmONC Mentor Dashboard** with KPIs, pending actions, county/facility map, and per-mentee completion matrix (by module and track).
  - A polished, colorful **mentee module-detail view** with status chips, progress timeline, and clear locked/unlocked sections.
  - A clear **activity completion map** so mentors can say: "Mentee X completed CME and Drill on Module 5, Hands-on still pending → certificate blocked."

---

## 3. Navigation: "Mentorships" group

Create a new Filament navigation group **Mentorships** with child items:

- Infant Care
- Newborn Care
- Child Care
- **Maternal Health (EmONC)**

For the Infant/Newborn/Child Care items, link to a generic program-filtered dashboard (reuse existing Mentor Dashboard logic filtered by program name).

For **Maternal Health (EmONC)**, link to the new rich EmONC dashboard page.

### Access control
- Visible to: mentors, co-mentors, senior roles (`super_admin`, `admin`, `division`, `national`), and `head_drmh`.
- Scoped to the user's assigned mentorships unless they have a senior role.

---

## 4. EmONC Mentor Dashboard

### 4.1 KPI cards (top row)

Colorful Tailwind-styled cards:

| Card | Color | Badge action |
|------|-------|--------------|
| Active Mentees | Indigo | — |
| Active Classes | Blue | — |
| Modules Completed | Emerald | — |
| Pending Video Reviews | Amber | Click → filtered module mentees list |
| Pending Mentor Approvals | Blue | Click → filtered class mentees list |
| Pending Head DRMH Certifications | Violet | Click → filtered class mentees list |
| Certificates Issued | Rose | — |

### 4.2 Pending actions strip

A compact row of pill badges showing the same pending counts with one-click navigation to the relevant worklist.

### 4.3 County / facility map

Use the existing Leaflet + Kenya GeoJSON infrastructure:
- Color counties by EmONC completion rate or active mentee count.
- Click a county to filter the completion matrix below.
- Fallback to a facility list if map data is unavailable.

### 4.4 Completion matrix

A rich table showing:
- Rows: mentees (filterable by county/facility/class).
- Columns: modules and their child tracks (expandable groups).
- Cells: activity status chips for each track:
  - `CME` — done / pending
  - `Hands-on` — done / pending review / failed / not submitted
  - `Drill` — done / pending
- Aggregate columns:
  - Module progress (not started / in progress / completed)
  - Video review (passed / failed / pending)
  - Mentor approval (approved / pending)
  - Head DRMH certification (certified / pending)
  - Certificate (issued / blocked)

Color coding:
- Green = complete / passed / approved
- Amber = pending / in progress / awaiting review
- Red = failed / blocked / missing
- Grey = not started / not enrolled

### 4.5 Charts

- Donut chart: overall activity completion distribution.
- Bar chart: modules completed vs enrolled per cohort.
- Line chart: mentorship completion trend over the last 6 months.

Use Chart.js (already loaded via CDN in `AppServiceProvider`).

### 4.6 Drill-down flow

1. User opens EmONC dashboard.
2. Sees KPIs and pending actions.
3. Clicks a pending count → goes to the relevant filtered table.
4. In the completion matrix, clicks a mentee row → goes to the mentee's class progress page.
5. Clicks a track cell → goes to the module mentees page for that track to review videos or mark activities.

---

## 5. Mentee Module-Detail View

### 5.1 Header

- Breadcrumb: Dashboard > Class Name > Module Name
- Program badge: "Maternal Health (EmONC)"
- Module/track title
- Status chips row:
  - Module status
  - Pre-test status
  - Hands-on video status
  - Post-test status
  - Mentor approval status
  - Certificate status

### 5.2 Progress timeline

A horizontal step timeline showing:
1. Pre-test
2. Content (introduction + videos + case scenarios)
3. Hands-on video submission
4. Post-test
5. Mentor review / approval

Current step highlighted; completed steps green; locked steps grey.

### 5.3 Section cards

Each module component as a card with a colored left border:

| Section | Border color | State |
|---------|--------------|-------|
| Introduction | Emerald | Open after pre-test attempt |
| Hands-on Videos | Emerald | Open after pre-test attempt |
| Case Scenarios | Blue | Open after pre-test attempt |
| Submit Hands-on Video | Amber | Active when content is open |
| Post-Test | Rose | Locked until video is reviewed and passed |
| Results & Feedback | Violet | Visible after post-test |

Each locked card shows the unlock condition, e.g.:
> "Post-test will unlock after your hands-on video is reviewed and passed by your mentor."

### 5.4 Video upload

Two tabs:
- **Upload file** — drag-and-drop or file picker.
- **External link** — YouTube or direct video URL with inline preview.

After submission, show:
- File name / link
- Submitted date
- Review status: pending / passed / failed
- Mentor notes (if any)

### 5.5 Results section

Show:
- Pre-test score
- Post-test score
- Average score
- Pass/fail indicator (≥ 85%)
- Mentor recommendation (if any)

---

## 6. Mentor Activity Completion Tracking

### 6.1 Entry points

- From the EmONC dashboard completion matrix, click a track cell.
- From the class modules table, click the **Activities** action on an EmONC module/track.

### 6.2 Completion matrix view

Table with:
- Rows: enrolled mentees.
- Columns: activities attached to this module/track (CME, Hands-on Demo, Drill, etc.).
- Checkboxes to mark each activity complete/incomplete.
- "All done" auto-calculated column.
- Bulk actions:
  - Mark selected mentees complete for all activities.
  - Mark selected mentees incomplete for all activities.

### 6.3 Auto-updates

When a mentor saves the matrix:
- Each checked activity gets `status = completed`, `completed_at = now()`, `completed_by = current user`.
- If all activities for a mentee are completed, that mentee's `MenteeModuleProgress` is marked `completed`.
- If all enrolled mentees in the class module are completed, the `ClassModule` status becomes `completed`.
- Notifications fire (already implemented via `EmoncNotificationService`).
- Certificate eligibility is recalculated:
  - All module progress completed.
  - All hands-on videos reviewed and passed.
  - Then mentor can approve.
  - Then Head DRMH can certify.

### 6.4 Clear completion language

The UI must make it obvious why a certificate is blocked:

> "Jane Doe — Module 5: PPH > Track 1: CME ✓, Drill ✓, Hands-on ✗ → certificate blocked."

---

## 7. Data sources

- `Training` / `MentorshipClass` / `ClassModule` — mentorship structure.
- `ProgramModule` (self-referential) — modules and tracks.
- `ProgramModuleActivity` / `ClassModuleActivityParticipant` — activity enrollment and completion.
- `MenteeModuleProgress` — module/track progress, quiz attempts, video review.
- `ClassParticipant` — certificate approval fields.
- `users`, `county_user`, `subcounty_user`, `facility_user` — geography and access scoping.

---

## 8. Error handling

- If a mentor tries to approve a mentee before all activities/video reviews are complete, show a clear modal listing what is missing.
- If a non-mentor tries to access the completion matrix, deny with 403.
- If the Kenya GeoJSON map fails to load, fall back to a county/facility filter dropdown.

---

## 9. Accessibility

- Skip-to-content link.
- Visible focus rings.
- `aria-label` on all icon-only buttons.
- Color is not the only indicator; use text labels + icons.
- Tables are responsive (horizontal scroll on small screens or card layout).

---

## 10. Testing considerations

- Verify EmONC dashboard shows only EmONC mentorships.
- Verify Infant/Newborn/Child Care dashboards still work and are not affected.
- Verify activity completion updates `MenteeModuleProgress` and `ClassModule` status.
- Verify certificate approval is blocked until all activities and video reviews are complete.
- Verify mentee module-detail view reflects locked/unlocked states correctly.
- Verify county/facility map filters the completion matrix.

---

## 11. Out of scope

- Full offline service-worker support.
- Real-time push notifications (email notifications already implemented).
- Native mobile app UI (mobile web responsive only).
- Rubric-based scoring for hands-on videos (binary pass/fail is in scope).

---

## 12. Decisions made during brainstorming

| Decision | Choice |
|----------|--------|
| Dashboard location | New top-level **Mentorships** navigation group with program-specific dashboards. |
| Dashboard focus | KPI cards + pending actions + county/facility map + completion matrix. |
| Completion matrix grouping | By module and track (expandable). |
| Visual style | Tailwind CSS, colorful, not basic, mobile-first. |
| Mentee module view | Status chips + progress timeline + section cards with colored borders. |
| Activity completion entry point | From EmONC dashboard matrix cell or class modules table **Activities** action. |
| Certificate readiness | All activities complete + all video reviews passed → mentor approval → Head DRMH certification. |
