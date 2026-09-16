# Maternal Health (EmONC) Mentorship — Implementation & Continuation Guide

> **Purpose:** This document is a single source of truth for the EmONC mentorship feature set that has been built in the MNCH platform. It covers architecture, data model, key code locations, user flows, mobile API, notifications, certificates, dashboards, and practical notes for anyone continuing development, debugging, or QA.
>
> **Last updated:** 2026-06-22
> **Status:** Phases 1–8 implemented. Mentor/mentee dashboards, review page, navigation visibility, and video/quiz polish are in place. Ready for QA / staging / launch.

---

## Table of Contents

1. [Overview & Scope](#overview--scope)
2. [Architecture at a Glance](#architecture-at-a-glance)
3. [Data Model](#data-model)
4. [Migrations](#migrations)
5. [Core Models & Relationships](#core-models--relationships)
6. [Service Layer](#service-layer)
7. [Filament Admin / Mentor UI](#filament-admin--mentor-ui)
8. [Mentee Frontend](#mentee-frontend)
9. [Mobile API](#mobile-api)
10. [Notifications](#notifications)
11. [Certificates, Verification & Badges](#certificates-verification--badges)
12. [Dashboards](#dashboards)
13. [Roles, Permissions & Navigation](#roles-permissions--navigation)
14. [Seeding & Initial Setup](#seeding--initial-setup)
15. [Testing Notes](#testing-notes)
16. [Notable Recent Fixes & Polish](#notable-recent-fixes--polish)
17. [Known Issues & Next Steps](#known-issues--next-steps)
18. [File Index](#file-index)

---

## Overview & Scope

The MNCH platform already supported facility mentorships for **Newborn Care** and **Infant and Child Care**. This work adds a third program, **Maternal Health (EmONC)**, with a curriculum structure, activities, quizzes, hands-on video submission/review, two-step certification, analytics, notifications, and a mobile API.

Key product decisions:

- **Tracks are child `ProgramModule` records** (`parent_id` self-reference) instead of a separate track table. This lets the existing `ClassModule` / `MenteeModuleProgress` machinery work unchanged.
- **Activities** (CME, Hands-on Demo, Drill) are attached to modules/tracks via a pivot and tracked per mentee.
- **Quizzes** are a brand-new subsystem (`ProgramModuleQuiz`, `QuizQuestion`, `QuizOption`, `QuizAttempt`, `QuizResponse`) rather than reusing the legacy `ModuleAssessment` flow.
- **Pre-test** unlocks content on attempt; **post-test** allows retakes. Pass mark is 85%.
- **Hands-on videos** can be uploaded files or external links (YouTube/direct video). Mentors review pass/fail.
- **Certification** is two-step: mentor approval, then Head DRMH certification.
- The implementation is **backward-compatible**: Infant/Newborn mentorships continue to use sessions and the old certificate behavior.

---

## Architecture at a Glance

```
Program: Maternal Health (EmONC)
  └── ProgramModule (module) e.g. Module 5: Management of PPH
        └── ProgramModule (track) e.g. Track 1: Bimanual compression
              └── ProgramModuleActivity (CME, Hands-on Demo, Drill)
              └── ProgramModuleContent (introduction, video, case scenario)
              └── ProgramModuleQuiz (pre-test / post-test / both)

Training (facility_mentorship, program = EmONC)
  └── MentorshipClass (cohort)
        └── ClassModule → ProgramModule (or a track)
              └── ClassModuleActivityParticipant (enrollment + completion)
              └── MenteeModuleProgress (pre/post tests, video, review)
              └── ClassAttendance
        └── ClassParticipant
              └── mentor_approved_at / head_drmh_approved_at (certification)
```

---

## Data Model

### New tables

| Table | Model | Purpose |
|-------|-------|---------|
| `activities` | `Activity` | Master catalog: CME, Hands-on Demo, Drill, etc. |
| `program_module_activities` | `ProgramModuleActivity` (pivot) | Links activities to modules/tracks |
| `program_module_contents` | `ProgramModuleContent` | Learning items: introduction notes, video URLs/uploads, case scenarios |
| `program_module_quizzes` | `ProgramModuleQuiz` | Quiz header per module/track; type `pre_test` / `post_test` / `both` |
| `quiz_questions` | `QuizQuestion` | MCQ question |
| `quiz_options` | `QuizOption` | Answer options; `is_correct` flag |
| `quiz_attempts` | `QuizAttempt` | A mentee's attempt; `attempt_type`, score, correct answers |
| `quiz_responses` | `QuizResponse` | One row per question answer |
| `class_module_activity_participants` | `ClassModuleActivityParticipant` | Per-mentee activity enrollment & completion |

### Modified tables

| Table | Changes |
|-------|---------|
| `program_modules` | Added `parent_id` (self-referential), `start_date`, `end_date` |
| `class_modules` | Added `start_date`, `end_date` |
| `mentee_module_progress` | Added `pre_test_attempt_id`, `post_test_attempt_id`, `hands_on_video_url`, `hands_on_video_path`, `video_review_status`, `video_reviewed_at`, `video_reviewed_by`, `video_review_notes` |
| `class_participants` | Added `mentor_approved_at`, `mentor_approved_by`, `head_drmh_approved_at`, `head_drmh_approved_by` |
| `users` | Added `can_create_mentorships` boolean, `supervisor_id` |

---

## Migrations

All migrations are timestamped in `database/migrations/`:

| Migration | Purpose |
|-----------|---------|
| `2026_06_21_120000_create_class_module_activity_participants_table.php` | Activity enrollment/completion pivot |
| `2026_06_21_190000_add_start_end_date_to_class_modules_table.php` | Class module scheduling |
| `2026_06_21_200000_add_quiz_attempts_and_video_to_mentee_module_progress.php` | Quiz attempts + hands-on video fields |
| `2026_06_22_085009_add_supervisor_id_to_users_table.php` | Quasi-mentor supervisor relationship |
| `2026_06_22_100000_add_completion_to_class_module_activity_participants.php` | Activity completion fields |
| `2026_06_22_100001_add_video_review_to_mentee_module_progress.php` | Mentor video review fields |
| `2026_06_22_100002_add_certificate_approval_to_class_participants.php` | Two-step certification fields |
| `2026_06_22_110000_add_can_create_mentorships_to_users.php` | Mentee mentorship-creation flag |

> The `parent_id` on `program_modules`, quiz tables, content tables, and activity tables were created in earlier migrations that are already part of the repository history.

---

## Core Models & Relationships

### `ProgramModule`

- `parent()` / `children()` — self-referential track relationship.
- `activities()` — `belongsToMany(Activity::class, 'program_module_activities')`.
- `contents()` — `hasMany(ProgramModuleContent::class)`.
- `quizzes()` — `hasMany(ProgramModuleQuiz::class)`.
- A module with `parent_id = null` is a parent module; with `parent_id` set it is a track.

### `ClassModule`

- `programModule()` — the linked `ProgramModule` (module or track).
- `classModules()` on `MentorshipClass` returns the ordered list.
- `confirmedAttendanceCount()` / `enrolledMenteeCount()` / `attendanceRate()` helpers.

### `ClassParticipant`

- `user()` — mentee.
- `mentorshipClass()` — cohort.
- `moduleProgress()` — `hasMany(MenteeModuleProgress::class)`.
- `isMentorApproved()` / `isHeadDrmhApproved()` / `isCertified()` — certification helpers.

### `MenteeModuleProgress`

- `classParticipant()` / `classModule()`.
- `preTestAttempt()` / `postTestAttempt()` — `belongsTo(QuizAttempt::class)`.
- `recordVideoReview($status, $notes, $reviewerId)` — updates review fields and timestamps.
- `hasSubmittedVideo()` — true if `hands_on_video_url` or `hands_on_video_path` present.
- `youtubeEmbedUrl()` — normalizes YouTube URLs to `embed` format.
- `isDirectVideoUrl()` — detects direct video file extensions for `<video>` tag rendering.

### `ClassModuleActivityParticipant`

- Pivot with extra fields: `status` (`enrolled` / `completed`), `completed_at`, `completed_by`.

### `QuizAttempt` / `QuizResponse`

- `QuizAttempt::isPassed()` compares `score >= quiz->pass_mark_percentage`.
- `QuizAttemptService::getLatestAttempts($user, $quiz)` returns `['pre_test' => ..., 'post_test' => ...]`.

---

## Service Layer

### `QuizAttemptService` (`app/Services/QuizAttemptService.php`)

- `startAttempt(ProgramModuleQuiz $quiz, User $user, string $attemptType): QuizAttempt`
- `submitAttempt(QuizAttempt $attempt, array $responses): QuizAttempt`
- `getLatestAttempts(User $user, ProgramModuleQuiz $quiz): array`

Used by web mentee dashboard, mentor review page, and mobile API.

### `EmoncReportingService` (`app/Services/EmoncReportingService.php`)

- `buildClassReport(MentorshipClass $class)` — per-mentee/module report data used by the class HTML/PDF report.
- `pendingItemsForUser(int $userId, array $trainingIds)` — KPI pending counts.
- `pendingVideoReviewItemsForUser(int $userId, array $trainingIds)` — list of pending video review items with URLs.

### `EmoncNotificationService` (`app/Services/EmoncNotificationService.php`)

- `activityCompleted(ClassParticipant, ClassModule)`
- `videoSubmitted(MenteeModuleProgress)` — notifies mentor + co-mentors.
- `videoReviewed(MenteeModuleProgress)` — notifies mentee.
- `mentorApproved(ClassParticipant)` — notifies mentee + Head DRMH users.
- `headDrmhCertified(ClassParticipant)` — notifies mentee + mentor.

Sends both Filament in-app notifications and `EmoncNotificationMail` emails.

### `EmoncDashboardService` (`app/Services/EmoncDashboardService.php`)

Builds data for the rich EmONC dashboard page:
- KPIs (active mentees, classes, modules, pending reviews/approvals, certificates).
- Pending action cards.
- Completion matrix (per mentee × module × activity).
- Chart data (completion distribution).

### `ModuleUsageService` (`app/Services/ModuleUsageService.php`)

- `getAvailableModules(Training $training)` — returns modules not yet assigned, hiding parents whose tracks are already assigned.
- `assignModulesToClass(MentorshipClass $class, array $programModuleIds)` — expands parent modules into per-track `ClassModule` records for EmONC.

### `AttendanceService`

- `confirmModuleAttendance(User $user, ClassModule $module)` — used by public attendance link, mentee dashboard, and mobile API.

---

## Filament Admin / Mentor UI

### `MentorshipTrainingResource`

Main CRUD resource for mentorships. Key changes:
- `canAccess()` / `shouldRegisterNavigation()` now also allow users with `can_create_mentorships = true`.
- `start_date` / `end_date` hidden when the selected program is EmONC.
- New page route registered: `module-mentee-review`.

### `ManageClassModules`

- EmONC class modules use a custom hierarchical picker (`EmoncModulePicker`) that expands parent modules into tracks.
- The table shows the parent module name above the track name for EmONC.
- The sessions column becomes an **Activities** column for EmONC.
- Action icons limited to Start, Mentees, Activities, Resources, Edit, Remove (no Sessions/Summary/Analytics for EmONC).
- **Activities** action opens `ActivityEnrollmentMatrix` to enroll mentees in activities.
- **Complete Activities** action opens `ActivityCompletionMatrix` to mark activities done.
- Activity completion auto-completes `MenteeModuleProgress` and `ClassModule` when all enrolled mentees are done.

### `ManageModuleMentees`

- Table now has a **Full Review** action that links to the new review page.
- Columns include attendance, source, progress, recommendation, video submitted, and video review status.
- Mark Present / Remove Attendance actions for in-progress modules.
- Write / View Recommendation actions.

### `ReviewModuleMentee` (new page)

URL: `/admin/mentorship/{training}/classes/{class}/modules/{module}/mentees/{participant}/review`

Displays:
- Full mentee details.
- Mentorship and module context.
- Activities with enrollment/completion status.
- Hands-on video preview.
- Pre-test and post-test status + expandable question review.
- Evaluation form (video review outcome + notes) with save.

### `ManageClassMentees`

- Mentor approval action (requires all modules complete + all video reviews passed).
- Head DRMH certification action.
- Bulk actions: bulk mentor approve, bulk Head DRMH certify, ZIP download of selected certificates, CSV export of class progress.
- Certificate download action appears only when both approvals exist.

### `ManageModuleResources`

Accordion view for mentors/SMEs: Introduction, Pre-Test, Hands-on Videos, Case Scenarios, Post-Test, Attached Resources.

### `ModuleSummary`

For EmONC shows program, module/track, and activity enrollment counts per activity.

### `ProgramModuleResource` / `ActivityResource` / `ProgramModuleQuizResource`

Curriculum builder admin:
- Manage modules, tracks, activities, content, and quizzes.
- Quiz resource is under **Quiz Management** navigation group.

---

## Mentee Frontend

Controllers:
- `MenteeClassProgressController` (`app/Http/Controllers/MenteeClassProgressController.php`)
- `MenteeEnrollmentController` (`app/Http/Controllers/MenteeEnrollmentController.php`)

### Routes (`routes/web.php`)

| Route | Name | Purpose |
|-------|------|---------|
| `/enroll/{token}` | `mentee.enroll` | Public enrollment landing; stores email in session for login prefill |
| `/account/verify/{user}` | (existing) | New user verification |
| `/my-class/{class}` | `mentee.class.progress` | Class progress overview |
| `/my-class/{class}/module/{classModule}` | `mentee.class.module` | Module detail page |
| `/my-class/{class}/module/{classModule}/quiz/{quiz}/start` | `mentee.class.quiz.start` | Start pre/post test |
| `/my-class/{class}/module/{classModule}/quiz/{attempt}/submit` | `mentee.class.quiz.submit` | Submit quiz |
| `/my-class/{class}/module/{classModule}/video` | `mentee.class.video.upload` | Upload/link hands-on video |
| `/certificates/{class}/{participant}/verify` | (new) | Public certificate verification |
| `/certificates/{class}/{participant}/badge` | (new) | SVG digital badge |

### Module detail page (`resources/views/mentee/module-detail.blade.php`)

- Colorful status chips and progress timeline.
- Dynamic **Next step** banner with jump links.
- Introduction always visible.
- Pre-test unlocks content on attempt (not on pass).
- Hands-on video via file upload or external link; supports YouTube embed, direct `<video>` tag, or external link card.
- Post-test available after video submission; retakes allowed.
- Expandable quiz review after pre/post test submission.
- Activity enrollment/completion list.

### Class progress page (`resources/views/mentee/class-progress.blade.php`)

- Module/track labels.
- **Next Up** banner for EmONC pointing to the next unfinished module.
- EmONC-aware footer (hides non-EmONC resource links).

### Enrollment page (`resources/views/mentee/enroll.blade.php`)

- Lists modules/tracks and activities for EmONC.
- Shows pending enrollment resume notice.
- Stores email in session; login page prefills it.

### Quiz review partial (`resources/views/mentee/partials/quiz-review.blade.php`)

Reusable expandable review showing selected answers, correct answers, explanations.

---

## Mobile API

Routes in `routes/api.php` under the authenticated `v1` group:

| Method | Endpoint | Controller | Purpose |
|--------|----------|------------|---------|
| GET | `/api/v1/me/classes` | `MenteeApiController@index` | List enrolled classes |
| GET | `/api/v1/me/classes/{class}` | `MenteeApiController@show` | Class detail + module list |
| POST | `/api/v1/me/classes/{class}/modules/{module}/attend` | `MenteeApiController@attend` | Self-confirm attendance |
| GET | `/api/v1/me/classes/{class}/modules/{module}` | `MenteeApiController@moduleDetail` | Full module detail |
| POST | `/api/v1/me/classes/{class}/modules/{module}/quiz/{quiz}/start` | `MenteeApiController@startQuiz` | Start pre/post test |
| POST | `/api/v1/me/quiz-attempts/{attempt}/submit` | `MenteeApiController@submitQuiz` | Submit answers |
| POST | `/api/v1/me/classes/{class}/modules/{module}/video` | `MenteeApiController@uploadVideo` | Upload file or link |

Additional mentorship creation/class APIs exist in `MentorshipController`, `MentorshipCreateController`, `ClassSessionController`, `AttendanceApiController`, and `ParticipantController`.

---

## Notifications

`EmoncNotificationService` dispatches notifications for these events:

1. **Activity completed** → mentee
2. **Hands-on video submitted** → mentor + accepted co-mentors
3. **Video reviewed** → mentee
4. **Mentor approved** → mentee + Head DRMH users
5. **Head DRMH certified** → mentee + mentor

Each notification:
- Sends a Filament in-app notification (`Notification::make()->sendToDatabase()`).
- Sends an email via `EmoncNotificationMail` (`resources/views/emails/emonc-notification.blade.php`).

Enrollment invitation emails use `MenteeEnrollmentInvitationMail` and include module/track/activity details for EmONC.

---

## Certificates, Verification & Badges

### Certificate flow

1. Mentor approves mentee (`mentor_approved_at/by` on `class_participants`).
2. Head DRMH certifies (`head_drmh_approved_at/by`).
3. Certificate download becomes available.
4. For EmONC, certificate generation is blocked until both approvals exist. Non-EmONC programs keep completion-based behavior.

### PDF certificate

- View: `resources/views/certificates/completion.blade.php`
- Generated by `ClassReportController` / existing certificate logic.
- Shows mentor and Head DRMH signature blocks with names and dates.
- Includes a QR code linking to the public verification page (uses `https://api.qrserver.com/v1/create-qr-code/`).

### Public verification

- `GET /certificates/{class}/{participant}/verify` — `ClassReportController@verifyCertificate`
- `resources/views/certificates/verify.blade.php`

### Digital badge

- `GET /certificates/{class}/{participant}/badge` — `ClassReportController@badge`
- Returns SVG from `resources/views/certificates/badge.blade.php`

---

## Dashboards

### Mentor Dashboard (`app/Filament/Pages/MentorDashboard.php`)

- KPI cards: active mentorships, total mentees, avg completion, attendance, recommendations, pending video reviews, pending approvals.
- Paginated mentorship breakdown (10 per page).
- Pending video reviews section with direct links.
- Insights cards (low completion, low attendance, stalled modules, rec coverage).
- Recent recommendations feed.

> Implementation note: the mentorship list is paginated. Because Livewire cannot dehydrate a `LengthAwarePaginator` public property, the component stores the current-page items in public `$mentorshipItems` and reconstructs the paginator in `getViewData()`.

### Mentee Dashboard (`app/Filament/Pages/MenteeDashboard.php`)

- Moved under **Mentorships** navigation group.
- Pending enrollment resume notice.
- Per-enrollment **Next Step** CTA linking to the next unfinished module.

### EmONC Dashboard (`app/Filament/Pages/EmoncDashboard.php`)

Rich EmONC-specific dashboard:
- KPI cards.
- Pending actions.
- Kenya county map (Leaflet + GeoJSON).
- Chart.js completion distribution chart.
- Per-mentee completion matrix.

Data built by `EmoncDashboardService`.

---

## Roles, Permissions & Navigation

### New / updated roles

- `head_drmh` — added in `RolePermissionSeeder`; can certify mentees after mentor approval.
- `can_create_mentorships` — per-user boolean flag on `users`; grants access to `MentorshipTrainingResource` and the ability to create mentorships regardless of role.
- `supervisor_id` — per-user dropdown for quasi-mentor supervision.

### Navigation visibility

- Mentees only see **Mentee Dashboard** under the **Mentorships** group.
- Program dashboards (Newborn Care, Infant and Child Care, Maternal Health EmONC) are hidden from mentees.
- Facility Assessment, Indicator Reporting, Indicator Catalog, and admin reporting groups are hidden from mentees via `shouldRegisterNavigation()` / `canAccess()` checks.

Run `php artisan shield:generate --all` after adding new resources.

---

## Seeding & Initial Setup

1. Run migrations: `php artisan migrate`
2. Seed roles: `php artisan db:seed --class=RolePermissionSeeder`
3. Seed super admin: `php artisan db:seed --class=SuperAdminSeeder`
4. Seed EmONC program/modules/tracks: ensure `EmoncProgramSeeder` (or equivalent) has been run.
5. Create `public/storage` symlink: `php artisan storage:link`
6. Regenerate permissions: `php artisan shield:generate --all`
7. Upgrade Filament assets / clear caches: `php artisan filament:upgrade`
8. Additional cache clear if needed: `php artisan config:clear && php artisan cache:clear && php artisan route:clear`

---

## Testing Notes

### Local test accounts

- Super admin: `super@admin.com` / `password`
- Test mentee: `test-mentee@example.com` / `password`

### Playwright test scripts

Located at `/var/folders/rp/ss8dywtd3sld8v55g63cwf800000gn/T/opencode/pw-test/`:
- `test-mentor-dashboard-super.js`
- `test-review-page.js`
- `test-review-link.js`
- `test-mentee-dashboard.js`
- `test-modules.js`
- etc.

Run with `node <script>.js` (requires Chrome at `/Applications/Google Chrome.app/Contents/MacOS/Google Chrome`).

### Key IDs used during testing

- EmONC training: `242`
- Cohort: `233`
- Module: `269` (Module 1: Ante Partum Hemorrhage)
- Participant: `248` (Grace Mwende Munyao)

---

## Notable Recent Fixes & Polish

1. **Mentor Dashboard pagination**
   - Mentorship list is now paginated (10 per page) and sorted latest-first.
   - Removed the full mentee roster table in favor of a focused pending-video-reviews section.
   - Livewire cannot dehydrate a `LengthAwarePaginator` public property, so the component stores current-page items in `$mentorshipItems` and reconstructs the paginator in `getViewData()`.

2. **Navigation visibility for mentees**
   - Mentees now see only **Mentee Dashboard** under **Mentorships**.
   - Program dashboards and admin/reporting groups are hidden via `shouldRegisterNavigation()` / `canAccess()` checks.

3. **Quiz review**
   - After pre-test or post-test submission, mentees see an expandable question-by-question review (`resources/views/mentee/partials/quiz-review.blade.php`) with their answers, correct answers, and explanations.

4. **Video playback fixes**
   - `public/storage` symlink created for uploaded videos.
   - YouTube regex improved to handle `watch`, `embed`, `shorts`, `youtu.be`, and mobile URLs.
   - Added direct-video detection for `<video>` tag rendering and external-link card fallback.
   - Upload validation relaxed from `mimetypes:video/*` to `mimes:mp4,mov,avi,mkv,webm,m4v,3gp,ogg`.

5. **Mentor evaluation review page**
   - Added `ReviewModuleMentee` page (see Filament Admin section) replacing the previous slide-over video review.

---

## Known Issues & Next Steps

### Pre-existing
- The PHPUnit test suite fails on in-memory SQLite because `tests/TestCase.php` lacks `RefreshDatabase`. This is unrelated to EmONC.
- `.env` has duplicate `MAIL_MAILER` entries (`log` and `smtp`); the last one wins, so local dev may attempt SMTP. Use `config(['mail.default' => 'log'])` for local mail testing.

### EmONC-specific follow-ups
1. **QA / UAT on staging** with real data and multiple mentor/mentee accounts.
2. **Kenya GeoJSON** availability in air-gapped deployments for the EmONC dashboard map.
3. **QR service** fallback if `api.qrserver.com` is unavailable.
4. **Offline support** — currently mobile-first/responsive only; service worker out of scope.
5. **Rubric scoring** — currently binary pass/fail video review; full rubric checklist deferred.
6. **Mentee mentorship creation** flow may need additional UX polish.
7. **Bulk certificate ZIP** uses `ZipArchive` + `Browsershot`; verify in the deployment environment.

---

## File Index

### New files

```
app/Filament/Forms/Components/ActivityCompletionMatrix.php
app/Filament/Forms/Components/ActivityEnrollmentMatrix.php
app/Filament/Pages/EmoncDashboard.php
app/Filament/Resources/MentorshipResource/Pages/ReviewModuleMentee.php
app/Mail/EmoncNotificationMail.php
app/Models/ClassModuleActivityParticipant.php
app/Services/EmoncDashboardService.php
app/Services/EmoncNotificationService.php
app/Services/EmoncReportingService.php
database/migrations/2026_06_21_120000_create_class_module_activity_participants_table.php
database/migrations/2026_06_21_190000_add_start_end_date_to_class_modules_table.php
database/migrations/2026_06_21_200000_add_quiz_attempts_and_video_to_mentee_module_progress.php
database/migrations/2026_06_22_085009_add_supervisor_id_to_users_table.php
database/migrations/2026_06_22_100000_add_completion_to_class_module_activity_participants.php
database/migrations/2026_06_22_100001_add_video_review_to_mentee_module_progress.php
database/migrations/2026_06_22_100002_add_certificate_approval_to_class_participants.php
database/migrations/2026_06_22_110000_add_can_create_mentorships_to_users.php
resources/views/certificates/badge.blade.php
resources/views/certificates/verify.blade.php
resources/views/emails/emonc-notification.blade.php
resources/views/filament/components/mentee-module-evaluation.blade.php
resources/views/filament/components/video-preview.blade.php
resources/views/filament/forms/components/activity-completion-matrix.blade.php
resources/views/filament/forms/components/activity-enrollment-matrix.blade.php
resources/views/filament/pages/emonc-dashboard.blade.php
resources/views/filament/pages/review-module-mentee.blade.php
resources/views/mentee/module-detail.blade.php
resources/views/mentee/partials/quiz-review.blade.php
```

### Significantly modified files

```
app/Filament/Pages/Indicators/IndicatorReporting.php
app/Filament/Pages/Indicators/IndicatorsReference.php
app/Filament/Pages/Indicators/MentorshipProgressDashboard.php
app/Filament/Pages/MenteeDashboard.php
app/Filament/Pages/MentorDashboard.php
app/Filament/Resources/AssessmentResource.php
app/Filament/Resources/IndicatorResource/Pages/FacilitySetup.php
app/Filament/Resources/MentorshipResource/Pages/ManageClassMentees.php
app/Filament/Resources/MentorshipResource/Pages/ManageClassModules.php
app/Filament/Resources/MentorshipResource/Pages/ManageModuleMentees.php
app/Filament/Resources/MentorshipResource/Pages/ManageModuleResources.php
app/Filament/Resources/MentorshipResource/Pages/ModuleSummary.php
app/Filament/Resources/MentorshipTrainingResource.php
app/Filament/Resources/UserResource.php
app/Http/Controllers/Api/MenteeApiController.php
app/Http/Controllers/ClassReportController.php
app/Http/Controllers/MenteeClassProgressController.php
app/Http/Controllers/MenteeEnrollmentController.php
app/Livewire/Auth/CustomLogin.php
app/Mail/MenteeEnrollmentInvitationMail.php
app/Models/Activity.php
app/Models/ClassAttendance.php
app/Models/ClassModule.php
app/Models/ClassParticipant.php
app/Models/MenteeModuleProgress.php
app/Models/User.php
app/Providers/Filament/AdminPanelProvider.php
app/Services/QuizAttemptService.php
database/seeders/RolePermissionSeeder.php
resources/views/certificates/completion.blade.php
resources/views/emails/mentee-enrollment-invitation.blade.php
resources/views/filament/components/mentee-class-card.blade.php
resources/views/filament/pages/manage-module-resources.blade.php
resources/views/filament/pages/mentee-dashboard.blade.php
resources/views/filament/pages/mentor-dashboard.blade.php
resources/views/livewire/auth/custom-login.blade.php
resources/views/mentee/class-progress.blade.php
resources/views/mentee/enroll.blade.php
resources/views/reports/class-report.blade.php
routes/api.php
routes/web.php
```

---

## Quick Reference: EmONC Decision Log

| # | Decision |
|---|----------|
| 1 | Program named **Maternal Health (EmONC)**. |
| 2 | Tracks stored as child `ProgramModule` via `parent_id`. |
| 3 | New quiz system instead of legacy `ModuleAssessment`. |
| 4 | Activities attached via checkbox pivot. |
| 5 | Activity completion drives module completion. |
| 6 | Video review is binary pass/fail. |
| 7 | Pre-test unlocks content on attempt; retake via post-test. |
| 8 | Certification is two-step: Mentor → Head DRMH. |
| 9 | `head_drmh` role created for final certification. |
| 10 | `can_create_mentorships` flag controls mentorship creation access. |
| 11 | Mentee dashboard moved to **Mentorships** group; admin groups hidden from mentees. |
| 12 | Mentor dashboard mentorships paginated; review page replaces slide-over evaluation. |

---

## How to Continue

1. **Fix pre-existing test suite issue** by adding `RefreshDatabase` to `tests/TestCase.php` (or decide on a testing strategy).
2. **Run QA on staging** with real mentor/mentee accounts across all three programs.
3. **Address known follow-ups** above.
4. **Add rubric scoring** when product decides to move beyond binary video review.
5. **Enhance offline/mobile** experience if required.
6. **Keep this guide updated** whenever new EmONC-related files, routes, or behaviors are added.
