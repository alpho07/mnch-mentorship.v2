# MNCH Mentorship Platform — Comprehensive Technical & Product Overview

> **Last updated:** 2026-06-28  
> **Version:** Laravel 12 + Filament v3.3  
> **Environment:** Production Kenya deployment, local dev on `http://localhost:8001`  
> **Scope:** Full feature reference — use as memory/onboarding document

---

## Table of Contents

1. [Platform Purpose](#1-platform-purpose)
2. [Technology Stack](#2-technology-stack)
3. [Architecture Overview](#3-architecture-overview)
4. [User Roles & Access Control](#4-user-roles--access-control)
5. [Training Domain](#5-training-domain)
6. [EmONC Mentorship Lifecycle](#6-emonc-mentorship-lifecycle)
7. [Activity Enrollment & Completion](#7-activity-enrollment--completion)
8. [Module Lifecycle & Table Actions](#8-module-lifecycle--table-actions)
9. [Class Mentee Management](#9-class-mentee-management)
10. [Curriculum Management](#10-curriculum-management)
11. [Facility Assessment](#11-facility-assessment)
12. [Indicator Reporting](#12-indicator-reporting)
13. [Knowledge Base](#13-knowledge-base)
14. [Inventory Management](#14-inventory-management)
15. [Certification & CPD Points](#15-certification--cpd-points)
16. [Data Model Reference](#16-data-model-reference)
17. [Admin Panel Structure](#17-admin-panel-structure)
18. [Service Layer](#18-service-layer)
19. [Web Routes](#19-web-routes)
20. [Mobile API](#20-mobile-api)
21. [Notifications](#21-notifications)
22. [Analytics & Dashboards](#22-analytics--dashboards)
23. [External Integrations](#23-external-integrations)
24. [Development Reference](#24-development-reference)

---

## 1. Platform Purpose

The **MNCH (Maternal, Newborn, and Child Health) Mentorship Platform** is a web-based healthcare training management system built for Kenya's Ministry of Health. It manages the full lifecycle of clinical mentorship programs across health facilities, counties, and divisions.

### Core capabilities

| Domain | What it manages |
|--------|----------------|
| **EmONC Mentorships** | Emergency Obstetric & Newborn Care facility-based cohort training with video review, practical rubric assessments, and two-step certification |
| **Global Trainings** | Centralized MOH training events with participant enrollment, attendance, and exports |
| **Facility Assessments** | Skills and infrastructure assessments against standardised checklists |
| **Indicator Reporting** | County/facility health indicator data submission, validation, and progress tracking |
| **Knowledge Base** | Role-scoped clinical resource library (articles, videos, PDFs) |
| **Inventory** | Medical commodity stock management and request workflows |
| **CPD Points** | Continuous Professional Development point accumulation for mentors and mentees |

The platform serves multiple stakeholder tiers — from frontline health workers (mentees) to county supervisors to national leads — each with a tailored view and set of actions.

---

## 2. Technology Stack

### Backend
| Component | Version / Detail |
|-----------|-----------------|
| PHP | 8.2+ |
| Laravel | 12.x |
| Filament | v3.3 (admin panel framework) |
| Livewire | 3.x (reactive components) |
| Database | MySQL — database name `mnch` |
| Timezone | `Africa/Nairobi` |
| Queue | Laravel queue (database driver), `queue:listen --tries=1` |

### Auth & Permissions
| Component | Purpose |
|-----------|---------|
| Laravel Sanctum | Mobile API token authentication |
| Spatie Permission | Role/permission management |
| Filament Shield | Auto-generated Filament resource permissions per role |

### Frontend
| Component | Detail |
|-----------|--------|
| Tailwind CSS v4 | Utility-first styling |
| Alpine.js | Lightweight reactive UI (toggles, dropdowns) |
| Chart.js | Dashboard charts (bar, doughnut, line) |
| Leaflet.js | Kenya county heatmaps (loaded via CDN — not npm) |
| Vite | Asset bundling (JS + CSS) |

### PDF & Documents
| Library | Use |
|---------|-----|
| DomPDF | Certificate PDFs, assessment reports, class reports |
| Browsershot / Puppeteer | Bulk certificate ZIP export (requires Puppeteer in deployment) |
| League/CSV | Participant CSV imports and exports |

### Other
| Library | Use |
|---------|-----|
| Spatie Activity Log | Audit trail |
| DHIS2 | External health data system sync (`Dhis2SyncService`) |
| QR Server API | QR codes on certificates (`https://api.qrserver.com`) |

---

## 3. Architecture Overview

### Geographic hierarchy

```
Division
  └─ County
       └─ Subcounty
            └─ Facility
```

Users are assigned to geographic scopes via pivot tables: `county_user`, `subcounty_user`, `facility_user`. The `User::isAboveSite()` method returns `true` for national/division-level roles that see all data without geographic filtering.

### Training type discriminator

The `Training` model uses a `type` column to distinguish two fundamentally different workflows:

```
type = "global_training"
  → Managed via GlobalTrainingResource / TrainingResource
  → Simple: training event, participants, attendance
  → No classes, modules, or certification

type = "facility_mentorship"
  → Managed via MentorshipTrainingResource
  → Complex: Training → MentorshipClass → ClassModules → MenteeModuleProgress
  → Supports EmONC (program-based) or Infant/Newborn/Child programs
```

### EmONC vs. non-EmONC mentorship distinction

The platform detects EmONC programs by name: programs containing `"maternal"` and `"emonc"` (case-insensitive) activate EmONC-specific behaviour:

- Hierarchical module picker (parent modules expand to child tracks)
- Activity-based workflow (CME, Hands-on Demo, Drill) instead of session-based
- Hands-on video upload and binary pass/fail video review
- Practical rubric assessments (ConductRubricAssessment wizard)
- Two-step certification: Mentor Approval → Head DRMH Certification
- Pre-test / post-test quiz system (new `ProgramModuleQuiz` model)

Non-EmONC programs (Newborn Care, Infant and Child Care) use the legacy session-based workflow with `ModuleAssessment` / `ModuleAssessmentResult` models.

### Mentorship data hierarchy

```
Training (type=facility_mentorship, program_id)
 └─ MentorshipClass (cohort — enrollment_token, enrollment_link_active)
     ├─ ClassModule (linked to ProgramModule)
     │    ├─ ClassSession (non-EmONC)
     │    ├─ ClassAttendance
     │    ├─ ClassModuleActivityParticipant (mentee × activity enrollment+completion)
     │    └─ MenteeModuleProgress (per mentee: quiz attempts, video, status)
     │         ├─ QuizAttempt → QuizResponse
     │         └─ video_review_status / video_review_notes
     └─ ClassParticipant (mentee enrollment)
          ├─ MenteeModuleProgress (one per ClassModule)
          ├─ mentor_approved_at / mentor_approved_by
          └─ head_drmh_approved_at / head_drmh_approved_by
```

---

## 4. User Roles & Access Control

### All seeded roles

```
super_admin          — full access, all data, no geographic scope
admin                — administrative access
division             — division-level view (isAboveSite)
national             — national-level view (isAboveSite)
division_lead        — division lead (isAboveSite)
national_mentor_lead — national mentor lead (isAboveSite)
county               — county-scoped
subcounty            — subcounty-scoped
facility_mentor      — facility-level mentor
facility_mentor_lead — facility mentor lead
county_mentor_lead   — county mentor lead
subcounty_mentor_lead— subcounty mentor lead
spoke_mentor         — spoke facility mentor
spoke_mentor_lead    — spoke facility mentor lead
head_drmh            — Head DRMH: final EmONC certification authority
mentee               — healthcare worker enrolled in a training
newbie               — newly registered, limited access
```

### Geographic scoping

| Role flag | Behaviour |
|-----------|-----------|
| `isAboveSite()` = true | Sees ALL data: `super_admin`, `admin`, `division`, `national`, `division_lead`, `national_mentor_lead` |
| Other roles | Scoped by assigned counties/subcounties/facilities via pivot tables |

Scoping helpers on `User`: `scopedCountyIds()`, `scopedSubcountyIds()`, `scopedFacilityIds()`.

### Special flag: `can_create_mentorships`

A boolean column on `users`. When `true`, grants the user access to create/manage mentorship trainings regardless of their role. This allows e.g. a `mentee` who is also a facility lead to manage their own cohort.

### Navigation visibility rules

| Role | What they see in the admin panel |
|------|----------------------------------|
| `mentee` | Only **Mentee Dashboard**; all admin groups hidden |
| Mentor roles | Mentorships, Training Management, Assessments |
| `head_drmh` | Head DRMH dashboard, mentee review pages |
| `super_admin` / `admin` | Everything |

### Permission system

Filament Shield auto-generates permissions per resource in the format `view_any_<model>`, `create_<model>`, `update_<model>`, `delete_<model>`. Run `php artisan shield:generate --all` after adding new resources. Permissions are synced to roles in `RolePermissionSeeder`.

---

## 5. Training Domain

### Global Trainings (MOH Trainings)

Centralized training events managed by national/division staff.

- Resource: `GlobalTrainingResource` (nav: Training Management → MOH Trainings)
- Participants enrolled manually or via CSV import (`BulkParticipantImportService`)
- Attendance tracked per session
- Exports available via Export Center
- No cohort/module structure — single event model

### Facility Mentorships

On-site, program-based cohort training. The core domain of the platform.

**Lifecycle:**
1. Admin creates Training (type=facility_mentorship, assigns program + facility)
2. Admin creates MentorshipClass (cohort) with enrollment token
3. Admin assigns modules via `ManageClassModules` (hierarchical for EmONC)
4. Mentees enroll via token link or manual add
5. Training progresses through module completion
6. Class lifecycle: `pending` → `active` → `completed`

**Key resources:**
- `MentorshipTrainingResource` — parent resource (nav: Training Management → Mentorship)
- Sub-pages: Classes, Modules, Mentees, Module Mentees, Review, Resources, Sessions, Assessments

**Co-mentors:**
- Invited via `ManageMentorshipCoMentors` page
- Accept via public token URL: `/co-mentor/accept/{token}`
- Status: `pending` | `accepted` | `declined`
- Accepted co-mentors receive all mentor notifications

---

## 6. EmONC Mentorship Lifecycle

### Program structure

**Maternal Health (EmONC)** program in the database:
- 13 top-level modules (Module 1 through Module 13)
- Module 5 (PPH Management) contains 10 child tracks stored as `ProgramModule` records with `parent_id` set
- Total: 12 rubrics seeded (Modules 4 & 5 + Tracks 1–10), 252+ rubric checklist items

### Mentee flow (per module)

```
1. Pre-test         → attempt unlocks module content (attempt required, not pass)
2. Introduction     → always visible (ProgramModuleContent intro notes)
3. Hands-on video   → upload file or paste YouTube/external link
4. Case scenarios   → clinical scenario reading
5. Post-test        → available after video submitted; retakes allowed
6. Results          → pre-test score, post-test score, average
```

### Mentor flow (admin panel)

```
1. Create Training → select EmONC program → assign facility
2. Create MentorshipClass (cohort) → set enrollment token
3. Add modules via EmoncModulePicker (hierarchical — click parent = auto-select tracks)
4. Enroll mentees → invitation email sent with modules/activities list
5. Mark activities complete → ActivityCompletionMatrix
   └─ Auto-cascade: all activities done → MenteeModuleProgress.status = 'completed'
6. Review hands-on videos → ReviewModuleMentee page
   └─ Video preview (YouTube iframe or direct video player)
   └─ Set video_review_status = 'passed' | 'failed' + notes
7. Conduct Practical Rubric Assessment → ConductRubricAssessment wizard
   └─ Step 1: select rubric, mentee, assessor, date
   └─ Step 2: score 21 checklist items (binary: performed/not)
   └─ Live score bar → saves RubricAssessment + RubricItemResponse records
8. Mentor Approval → 'Approve Mentee' button (enabled when ALL modules complete + videos passed)
   └─ Sets ClassParticipant.mentor_approved_at/by
   └─ Notifies mentee + all Head DRMH users
9. Head DRMH certifies → HeadDrmhReviewMentee page
   └─ Sets ClassParticipant.head_drmh_approved_at/by
   └─ Certificate PDF now downloadable
```

### Mentor approval gate

`isReadyForMentorApproval()` requires:
- All `ClassModule` IDs for the class have a `MenteeModuleProgress` record for the participant
- Every progress record: `status IN ('completed', 'exempted')`
- Every progress record: `video_review_status = 'passed'` (via `isVideoPassed()`)

### Practical rubric assessments

- Model: `ModuleRubric` → `RubricItem` (checklist steps)
- Model: `RubricAssessment` → `RubricItemResponse` (scored results)
- Each rubric has `total_marks`, `pass_marks`, `pass_percentage` (auto-calculated)
- Items are binary (performed/not performed) — no partial credit
- Rubrics are reusable across cohorts (linked to `ModuleRubric`, not `ClassModule`)
- Managed via: Curriculum → **Rubric Definitions** (`admin/rubric-managements`)
- Conducted via: Mentorships → **Practical Assessments** (`admin/rubric-assessments`)

---

## 7. Activity Enrollment & Completion

This is the core EmONC workflow mechanism that tracks which mentees are doing which activities in each module, and drives the auto-cascade that completes progress records and triggers notifications.

### The model: `ClassModuleActivityParticipant`

Table: `class_module_activity_participants`

| Field | Type | Description |
|-------|------|-------------|
| `class_module_id` | FK | Which class module |
| `class_participant_id` | FK | Which enrolled mentee |
| `activity_id` | FK | Which activity (CME / Hands-on Demo / Drill) |
| `status` | enum | `pending` \| `completed` |
| `completed_at` | datetime | When marked complete |
| `completed_by` | FK → users | Who marked it complete (mentor user ID) |

Key model methods:
- `markCompleted(?int $userId)` — sets status, completed_at, completed_by; returns false if already completed
- `scopeCompleted($query)` — where status = 'completed'
- `scopePending($query)` — where status = 'pending'

### Auto-enrollment when modules are added

When a mentor adds modules to a class via the "Add Modules" slide-over, the system **automatically creates pending enrollment records** for all currently enrolled/active mentees across all activities of each newly added module:

```
foreach new ClassModules:
  foreach activities on programModule:
    foreach active ClassParticipants:
      ClassModuleActivityParticipant::insertOrIgnore([
        class_module_id, class_participant_id, activity_id,
        status = 'pending'
      ])
```

This uses `insertOrIgnore` so re-adding a module doesn't duplicate records.

### Activity Enrollment Matrix (manual adjustment)

Accessed via the **Activities** (clipboard) icon button on any module row in ManageClassModules.

- Opens a **modal** with `ActivityEnrollmentMatrix` custom Filament field
- Renders a **grid: mentees (rows) × activities (columns)** with checkboxes
- Pre-filled from existing `ClassModuleActivityParticipant` records
- On save (`saveActivityEnrollments()`):
  1. **Deletes** all existing enrollment records for the module + those participant IDs
  2. **Re-inserts** only the checked combinations
  3. Wrapped in a DB transaction

> Note: Enrollment save is a **replace** (delete + re-insert), not a patch. Unchecking removes the enrollment record entirely.

### Activity Completion Matrix

Accessed via the **Complete Activities** (check-circle) icon button on any module row.

- Opens a **modal** with `ActivityCompletionMatrix` custom Filament field
- Same grid layout **plus** two extra status columns:
  - **Video review status** — pulled from `MenteeModuleProgress.video_review_status` (not_submitted / pending / passed / failed)
  - **Certificate status** — shows mentor_approved, head_drmh_approved, certified flags per mentee
- On save (`saveActivityCompletions()`):
  1. Updates existing records:
     - Checked → `status = 'completed'`, `completed_at = now()`, `completed_by = auth()->id()`
     - Unchecked (was completed) → `status = 'pending'`, `completed_at = null`, `completed_by = null`
  2. Creates new records for checked combinations that don't exist yet (with `status = 'completed'`)
  3. **Auto-cascade 1**: For each participant — if ALL enrolled activities are now `status = 'completed'` → calls `MenteeModuleProgress::markCompleted()` and queues that participant for notification
  4. **Auto-cascade 2**: If ALL participants' `MenteeModuleProgress` are in `completed/exempted` AND `ClassModule.status = 'in_progress'` → calls `$classModule->complete()`
  5. After transaction: fires `EmoncNotificationService::activityCompleted()` for each newly completed participant

### Auto-cascade summary

```
Mentor checks all activities for a mentee
  └─ ClassModuleActivityParticipant: all → status=completed
       └─ MenteeModuleProgress::markCompleted() [auto]
            └─ EmoncNotificationService::activityCompleted() → mentee notified
                 └─ (if ALL mentees done) ClassModule::complete() [auto]
```

Completion can be **reversed**: unchecking a completed activity resets it to `pending` (and clears `completed_at/completed_by`). This does NOT automatically revert the `MenteeModuleProgress` status — that must be manually managed if needed.

### ActivityEnrollmentMatrix component

`app/Filament/Forms/Components/ActivityEnrollmentMatrix.php`

Custom Filament `Field` wrapping a Blade view. Three configuration methods:
- `->participants(array)` — `[['id', 'name', 'email'], ...]`
- `->activities(array)` — `[['id', 'name'], ...]`
- `->enrolledActivityIds(array)` — keyed by `class_participant_id` → `[activity_id, ...]`

Serialises state as JSON: `[{participantId, activityIds: [...]}, ...]`

### ActivityCompletionMatrix component

`app/Filament/Forms/Components/ActivityCompletionMatrix.php`

Same structure plus two extra data props:
- `->videoReviews(array)` — keyed by participant_id → `'not_submitted'|'pending'|'passed'|'failed'`
- `->certificateStatuses(array)` — keyed by participant_id → `{mentor_approved, head_drmh_approved, certified}`

Serialises state as JSON: `[{participantId, activityIds: [...]}, ...]`

---

## 8. Module Lifecycle & Table Actions

### Module statuses

`ClassModule.status`:
- `not_started` — default after being added to class
- `in_progress` — started (attendance link active, mentees can access content)
- `completed` — locked (no further changes, attendance closed)

### Starting a module (EmONC gates)

The **Start** button on a module row has two hard guards:

1. **No mentees enrolled** → blocks start, shows warning modal, offers redirect to class-mentees page
2. **Missing start/end date** (EmONC only) → blocks start, shows warning modal, auto-opens the Edit inline modal for that row after dismissal

When a module starts:
- `MentorshipClass.status` is upgraded from `draft` → `active` if still draft
- `ClassModule::start()` is called (sets `started_at`, status → `in_progress`)
- Mentee attendance link becomes active

### Completing a module

The **Complete** button (visible only when `in_progress`):
- Confirmation modal shows live attendance rate: `confirmed/total (%)` via `$record->attendanceRate()`
- Calls `ClassModule::complete()` → sets `completed_at`, status → `completed`
- Closes the attendance token link

### Row actions (EmONC)

| Icon | Action | Visibility |
|------|--------|-----------|
| ▶ Start | Start module | `status = not_started` |
| ✓ Complete | Complete module | `status = in_progress` |
| 👤 Mentees | → ManageModuleMentees (pending video badge) | Always |
| 📋 Activities | ActivityEnrollmentMatrix modal | EmONC only |
| ✅ Complete Activities | ActivityCompletionMatrix modal | EmONC only |
| 📄 Resources | → ManageModuleResources | Always |
| ✏️ Edit | Edit start/end date, order, notes | `status ≠ completed` |
| 🗑 Remove | Remove from class | `status = not_started` only |

### Row actions (non-EmONC)

| Icon | Action |
|------|--------|
| ▶ Start | Start module |
| ✓ Complete | Complete module |
| 📅 Sessions | → ManageModuleSessions |
| 👤 Mentees | → ManageModuleMentees |
| 📊 Summary | → ModuleSummary (analytics) |
| 📄 Resources | → ManageModuleResources |
| ✏️ Edit | Edit settings |
| 🗑 Remove | Remove (not_started only) |

### Attendance column

The module table shows an **Attendance** column computed in real-time from `MenteeModuleProgress`:
- Counts progress records with `status IN ('in_progress', 'completed')` as "confirmed"
- `confirmed / total enrolled (%)` — e.g. `8/12 (67%)`
- Green with check icon if any confirmed; red if none; gray if not started

### Module reordering

The table supports **drag-to-reorder** via Filament's `->reorderable('order_sequence')` — updates `order_sequence` on `class_modules`.

### Remove module guards

- Only allowed when `status = not_started`
- Checks `$record->canBeRemoved()` — fails if there are sessions or progress records
- On removal: `ModuleUsageService::removeModuleFromClass()` cleans up all related records

---

## 9. Class Mentee Management

Page: `ManageClassMentees` — URL: `admin/mentorship/{training}/classes/{class}/mentees`

### Mentee enrollment

Mentees can be added to a class in two ways:
1. **Add from List** — select existing users from the system (filtered by facility/role)
2. **Add Mentee** — create a new user account and immediately enroll them
3. **Token enrollment link** — share `GET /enroll/{token}` (mentees self-register)

On enrollment:
- Creates `ClassParticipant` record (status = `enrolled`)
- Sends invitation email with class details, modules list, and activities list
- Auto-creates `ClassModuleActivityParticipant` pending records for all existing active modules + activities

### Mentee table columns

| Column | Source |
|--------|--------|
| Name | `user.name` |
| Status badge | `ClassParticipant.status` (enrolled/active/completed) |
| Mentor Approved | `mentor_approved_at` non-null |
| Head DRMH Certified | `head_drmh_approved_at` non-null |
| Cert link | Enabled when `isCertified()` |

### Row actions on each mentee

| Action | Condition |
|--------|-----------|
| View Progress | Always |
| Mentor Approve | `status = completed` AND `mentor_approved_at = null` |
| Head DRMH Certify | `mentor_approved_at` set AND `head_drmh_approved_at = null` AND `status = completed` |
| Download Certificate | `isCertified()` = true |
| Remove | Only if not yet started any progress |

### Bulk actions

| Bulk Action | Behaviour |
|------------|-----------|
| **Bulk Approve** | Approves all selected mentees who are eligible (`status = completed`, not yet mentor-approved); skips ineligible; reports count approved + skipped |
| **Bulk Certify** | Certifies all selected mentees who have mentor approval but not yet DRMH; reports count certified + skipped |
| **Download Certificates (ZIP)** | Generates PDFs for all certified selected mentees, zips them, streams download as `certificates-{class-slug}-{date}.zip`. Uses `ZipArchive` + DomPDF. |
| **Export Progress CSV** | Streams a CSV with one row per participant: name, email, module progress statuses, video review status, quiz scores, cert status |

### CSV export columns (Export Progress CSV)

Per mentee row includes:
- Name, email, cadre, facility
- Per module: status, video_review_status, pre-test score, post-test score
- mentor_approved_at, head_drmh_approved_at, certified (boolean)

---

## 10. Curriculum Management

Navigation group: **Curriculum**

| Item | Purpose |
|------|---------|
| Programs | 3 programs: Newborn Care, Infant and Child Care, Maternal Health (EmONC) |
| Modules | Individual training modules (24 total) |
| Program Modules | Link modules to programs; `parent_id` for track hierarchy |
| Activities | Master list: CME, Hands-on Demo, Drill |
| Methodologies | Teaching methodologies reference |
| Rubric Definitions | Create/edit practical assessment rubrics and checklist items |

### ProgramModule hierarchy

- `parent_id = null` → top-level module
- `parent_id = <id>` → sub-track under a parent module
- `isTrack()` helper on the model
- `ModuleUsageService::getAvailableModules()` hides parents when their tracks are already assigned to a class

### Quiz system (EmONC)

- `ProgramModuleQuiz` — linked to ProgramModule, type: `pre_test` | `post_test` | `both`
- `QuizQuestion` → `QuizOption` (flagged `is_correct`)
- `QuizAttempt` — per-participant attempt with score
- `QuizResponse` — per-question answer record
- `QuizAttemptService` — shared between web and mobile API for start/submit logic

---

## 11. Facility Assessment

Navigation group: **Facility Assessment**

Structured checklist-based assessments of healthcare facility capabilities and infrastructure.

| Model | Purpose |
|-------|---------|
| `Assessment` | A facility assessment instance |
| `AssessmentSection` | Logical grouping of questions |
| `AssessmentQuestion` | Individual checklist item |
| `AssessmentDepartment` | Department context |
| `Commodity` / `CommodityCategory` | Health products scored in assessments |

### Services
- `AssessmentScoringService` — singleton; scores assessment responses
- `AssessmentAnalyticsService` — aggregate scoring and trend analysis
- `AssessmentPdfReportService` — generates PDF assessment reports
- `AssessmentExportService` — exports assessment data
- `AssessmentTeamService` — manages the team conducting an assessment
- `DynamicFormBuilder` / `DynamicScoringService` — dynamic question forms

### Reports
- `GET /assessments/{assessment}/report` — HTML report
- `GET /assessments/{assessment}/download` — PDF download
- `GET /assessments/{assessment}/executive` — executive summary

---

## 12. Indicator Reporting

Navigation group: **Indicator Reporting**

Structured health indicator data submission against a predefined indicator catalog.

### Catalog structure
- `IndicatorReportType` — report type (e.g. monthly, quarterly)
- `IndicatorGroup` — logical grouping of indicators
- `Indicator` — individual indicator definition with targets

### Workflow
1. User submits indicator report via **Submit Report** form
2. Submitted reports enter **Validation Queue**
3. Validators approve or request revision
4. Approved data visible in **Progress Dashboard**

### Services
- `IndicatorReportingService` — data submission and validation logic
- `IndicatorNotificationService` — notifies validators and submitters

---

## 13. Knowledge Base

Navigation group: **Content Management**

A role-scoped library of clinical resources (articles, PDFs, videos, links).

### Visibility tiers

| Scope | Who sees it |
|-------|------------|
| `public` | Anyone (including unauthenticated) |
| `authenticated` | Any logged-in user |
| `restricted` | Filtered by `AccessGroup`, facility assignment, or authorship |

Use `Resource::accessibleTo($user)` scope — never filter after fetch.

### Models
- `Resource` — main knowledge base article/file (uses `HasAccessControl` + `HasFileManagement` traits)
- `ResourceCategory`, `ResourceType`, `Tag` — classification
- `ResourceComment` — user comments
- `AccessGroup` — defines which users/facilities can see restricted resources

### Public routes
- `GET /resources/` — browse portal
- `GET /resources/{slug}` — article view
- `GET /resources/{slug}/download` — file download

---

## 14. Inventory Management

Navigation group: **Inventory Management**

Stock tracking and request workflows for medical commodities.

| Resource | Purpose |
|----------|---------|
| Suppliers | Vendor/supplier registry |
| Inventory Items | Item catalog with categories |
| Stock Levels | Current stock per facility |
| Stock Requests | Request and fulfilment workflow |

---

## 15. Certification & CPD Points

### EmONC Certificate chain

```
Mentor Approval
  └─ ClassParticipant.mentor_approved_at = now()
  └─ ClassParticipant.mentor_approved_by = auth()->id()
  └─ Notification: mentee + all head_drmh users
      ↓
Head DRMH Certification
  └─ ClassParticipant.head_drmh_approved_at = now()
  └─ ClassParticipant.head_drmh_approved_by = auth()->id()
  └─ Certificate PDF now available
  └─ Notification: mentee + mentor
```

### Certificate

- Route: `GET /admin/reports/class/{class}/certificate/{participant}` → PDF download
- Verification: `GET /certificates/{class}/{participant}/verify` (public)
- Badge: `GET /certificates/{class}/{participant}/badge` → SVG
- PDF includes: mentee name, program, class name, dates, QR code, CPD points
- QR code links to the public verify endpoint

### CPD Points system (`CpdPointsService`)

| Event | Points | Condition |
|-------|--------|-----------|
| Certificate issued (mentee) | 3 | `head_drmh_approved_at` is set |
| Module completed (mentee) | 1 per module | Only when `mentorship_classes.status = 'completed'` |
| Class led (mentor) | 3 | `ClassModule.status = 'completed'` |
| Module delivered (mentor) | 1 per module | `ClassModule.status = 'completed'` |

### CPD Levels

| Level | Points range |
|-------|-------------|
| Foundation | 0–5 |
| Practitioner | 6–15 |
| Advanced Practitioner | 16–30 |
| Expert | 31–50 |
| Master Practitioner | 51+ |

CPD totals are displayed on both mentee certificates and mentor certificates, and in the EmONC dashboard mentee matrix.

---

## 16. Data Model Reference

### Core models

| Model | Table | Key fields |
|-------|-------|-----------|
| `User` | `users` | `role`, `can_create_mentorships`, `supervisor_id`, `county_id` |
| `Training` | `trainings` | `type` (global/mentorship), `program_id`, `mentor_id`, `facility_id` |
| `MentorshipClass` | `mentorship_classes` | `training_id`, `enrollment_token`, `status` |
| `ClassModule` | `class_modules` | `mentorship_class_id`, `program_module_id`, `status` |
| `ClassParticipant` | `class_participants` | `class_id`, `user_id`, `mentor_approved_at`, `head_drmh_approved_at` |
| `MenteeModuleProgress` | `mentee_module_progress` | `class_participant_id`, `class_module_id`, `status`, `video_review_status`, `hands_on_video_url`, `pre_test_attempt_id`, `post_test_attempt_id` |
| `ClassModuleActivityParticipant` | `class_module_activity_participants` | `class_module_id`, `class_participant_id`, `activity_id`, `status` (pending\|completed), `completed_at`, `completed_by` |
| `ProgramModule` | `program_modules` | `program_id`, `parent_id`, `name` |
| `QuizAttempt` | `quiz_attempts` | `class_participant_id`, `quiz_id`, `attempt_type`, `score` |
| `ModuleRubric` | `module_rubrics` | `program_module_id`, `total_marks`, `pass_marks` |
| `RubricAssessment` | `rubric_assessments` | `module_rubric_id`, `mentee_id`, `mentor_id`, `score`, `passed` |
| `RubricItem` | `rubric_items` | `module_rubric_id`, `description`, `order_sequence` |
| `RubricItemResponse` | `rubric_item_responses` | `rubric_assessment_id`, `rubric_item_id`, `performed` |

### MenteeModuleProgress — full field reference

`app/Models/MenteeModuleProgress.php`

| Field | Type | Description |
|-------|------|-------------|
| `class_participant_id` | FK | Enrolled mentee |
| `class_module_id` | FK | Which module |
| `status` | enum | `not_started` \| `in_progress` \| `completed` \| `exempted` |
| `assessment_status` | string | Legacy non-EmONC assessment result |
| `completed_in_previous_class` | bool | Exemption flag for re-enrollees |
| `pre_test_attempt_id` | FK | QuizAttempt for pre-test |
| `post_test_attempt_id` | FK | QuizAttempt for post-test |
| `hands_on_video_url` | string | YouTube/external URL or uploaded path |
| `hands_on_video_path` | string | Storage path for direct file uploads |
| `video_review_status` | enum | `null` (not submitted) \| `pending` \| `passed` \| `failed` |
| `video_reviewed_at` | datetime | When reviewed |
| `video_reviewed_by` | FK | Mentor who reviewed |
| `video_review_notes` | text | Mentor notes on video review |

Key methods:
- `isExempted()` — `status = 'exempted'` OR `completed_in_previous_class = true`
- `isCompleted()` — `status IN ('completed', 'exempted')`
- `isAssessmentPassed()` — `assessment_status = 'passed'`
- `hasSubmittedVideo()` — `hands_on_video_url` or `hands_on_video_path` filled
- `isVideoPassed()` — `video_review_status = 'passed'`
- `isVideoFailed()` — `video_review_status = 'failed'`
- `youtubeEmbedUrl()` — converts YouTube watch URL to embed URL
- `isDirectVideoUrl()` — detects non-YouTube direct video links
- `markCompleted()` — sets `status = 'completed'`
- `recordVideoReview($status, $notes, $reviewerId)` — updates all video review fields
- `areAllActivitiesCompleted()` — checks `ClassModuleActivityParticipant` records

### Geographic models

| Model | Table |
|-------|-------|
| `Division` | `divisions` |
| `County` | `counties` |
| `Subcounty` | `subcounties` |
| `Facility` | `facilities` (10,700+ records) |
| `FacilityLevel` | `facility_levels` |
| `FacilityOwnership` | `facility_ownerships` |
| `FacilityType` | `facility_types` |

### Key traits

| Trait | File | Purpose |
|-------|------|---------|
| `HasAccessControl` | `app/Models/Concerns/` | Resource visibility tiers + `accessibleTo` scope |
| `HasFileManagement` | `app/Models/Concerns/` + `app/Traits/` | File upload handling |
| `HasTrainingFilters` | (Training model) | Reusable query filters for training queries |

---

## 17. Admin Panel Structure

Panel ID: `admin`, path: `/admin`

### Navigation groups (ordered)

1. **Dashboards** — Mentor Dashboard, Head DRMH, Mentee Dashboard
2. **Mentorships** — Practical Assessments
3. **Facility Assessment** — Assessments
4. **Training Management** — Training Areas, MOH Trainings, Mentorship, Export Center, Profiles
5. **Indicator Catalog** — Report Types, Indicator Groups, Indicators, Reference
6. **Curriculum** — Programs, Modules, Program Modules, Activities, Methodologies, Rubric Definitions
7. **Report Management** — Performance Tracker, Report Templates, Report Management
8. **Settings** — Grades, Access Groups, Mentee Statuses, Partners
9. **Filament Shield** — Roles
10. **Assessment Management** — Sections & Questions, All Questions, Departments, Commodities
11. **Organizational Structure** — Departments, Cadres
12. **Geographic Structure** — Divisions, Counties, Subcounties, Facility Types
13. **System Administration** — Facilities, Facility Levels, Facility Ownership
14. **Quiz Management** — Program Module Quizzes
15. **Content Management** — Resources, Categories, Types, Tags, Comments
16. **App Configuration** — Scopes
17. **Inventory Management** — Suppliers, Inventory Items, Stock Levels, Stock Requests
18. **User Management** — All Users
19. **Indicator Reporting** — Submit Report, Validation Queue, Progress Dashboard

### Dashboard pages

| Page | Group | Description |
|------|-------|-------------|
| `MentorDashboard` | Dashboards | KPI cards, pending video reviews, class list, insights |
| `MenteeDashboard` | Mentorships | Per-enrollment Next Step CTA cards |
| `EmoncDashboard` | (Dashboards) | Leaflet county heatmap, Chart.js completion charts, mentee matrix |
| `HeadDrmhDashboard` | Dashboards | Pending certifications queue |
| `TrainingCoverageDashboard` | — | Training reach analytics |

### Custom Filament form components

| Component | Purpose |
|-----------|---------|
| `ProgramPicker` | Card-style program selector (EmONC is 3rd card) |
| `EmoncModulePicker` | Hierarchical: click parent = select/deselect all tracks |
| `ActivityEnrollmentMatrix` | Mentee × activity enrollment grid |
| `ActivityCompletionMatrix` | Mentee × activity completion grid with checkboxes |

### Notable custom pages (not standard resources)

| Page | URL | Purpose |
|------|-----|---------|
| `ReviewModuleMentee` | `…/mentees/{participant}/review` | Full mentor review: video, quiz scores, rubric, approval |
| `HeadDrmhReviewMentee` | `admin/head-drmh-review-mentee?participant=…` | DRMH certification page |
| `ConductRubricAssessment` | `admin/rubric-assessments/create?rubric_id=…&mentee_id=…` | 2-step rubric wizard |
| `MentorshipProgressDashboard` | `admin/indicators/progress-dashboard` | Indicator progress view |
| `AttendanceReportPage` | — | Attendance data by class/module |

---

## 18. Service Layer

All services live in `app/Services/`.

### EmONC services

| Service | Responsibility |
|---------|---------------|
| `EmoncNotificationService` | 5 lifecycle notifications: activity completed, video submitted, video reviewed, mentor approved, head DRMH certified. Each sends Filament in-app + `EmoncNotificationMail` email. |
| `EmoncReportingService` | Builds class progress report; `pendingItemsForUser()`, `pendingVideoReviewItemsForUser()` |
| `EmoncDashboardService` | KPIs, action cards, completion matrix, chart data for EmoncDashboard |
| `ModuleUsageService` | `getAvailableModules()` hides parent when tracks assigned; `assignModulesToClass()` expands parent → per-track ClassModule records |
| `QuizAttemptService` | `startAttempt()`, `submitAttempt()`, `getLatestAttempts()` — shared web + mobile |
| `CpdPointsService` | CPD point calculation for mentees and mentors; batch methods for dashboard matrix |

### Mentorship services

| Service | Responsibility |
|---------|---------------|
| `EnrollmentService` | Mentee class enrollment |
| `AttendanceService` | `confirmModuleAttendance()` — used by public link, mentee dashboard, mobile API |
| `BulkParticipantImportService` | CSV import for training participants |
| `SmartParticipantSuggestionService` | Smart mentee suggestions based on facility/cadre |

### Analytics & reporting

| Service | Responsibility |
|---------|---------------|
| `TrainingAnalyticsService` | Insights and trends for global trainings |
| `TrainingReportService` | HTML/PDF class report generation |
| `MentorshipProgressService` | Mentorship-level aggregate progress |
| `MonthlyReportService` | Auto-generated monthly reports (scheduled via `GenerateMonthlyReports` artisan command) |
| `MentorAnalyticsDashboardService` | Mentor analytics dashboard: CPD leaderboard, mentee reach, class status, trends |

### Assessment services

| Service | Responsibility |
|---------|---------------|
| `AssessmentScoringService` (singleton) | Facility assessment scoring |
| `AssessmentAnalyticsService` | Aggregate scoring and trends |
| `AssessmentPdfReportService` | PDF report generation |
| `AssessmentExportService` | Data exports |
| `AssessmentTeamService` | Assessment team management |
| `DynamicFormBuilder` / `DynamicScoringService` | Dynamic question forms |
| `CommodityScoringService` | Health products scoring |

### Other services

| Service | Responsibility |
|---------|---------------|
| `MenteeAiAdvisor` | AI recommendations for mentees (calls Claude API) |
| `FacilityAssignmentService` | Geographic assignment helpers |
| `IndicatorReportingService` | Indicator data submission and validation |
| `IndicatorNotificationService` | Indicator report notifications |
| `FileUploadService` | File upload handling |
| `ResourceService` | Knowledge base operations |
| `Dhis2SyncService` | DHIS2 health data system integration |

---

## 19. Web Routes

### Public (no authentication required)

| Route | Purpose |
|-------|---------|
| `GET /enroll/{token}` | Mentee self-enrollment landing page |
| `POST /enroll/{token}` | Submit enrollment form |
| `GET /module/attend/{token}` | Module attendance confirmation |
| `POST /module/attend/{token}` | Process attendance |
| `GET /account/verify/{user}` | New user account verification & password set |
| `POST /account/verify/{user}` | Submit verification |
| `GET /co-mentor/accept/{token}` | Co-mentor invitation acceptance |
| `GET /certificates/{class}/{participant}/verify` | Certificate verification (public) |
| `GET /certificates/{class}/{participant}/badge` | Certificate badge (returns SVG) |
| `GET /resources/*` | Public knowledge base portal |
| `GET /analytics/dashboard` | Training analytics heatmap |
| `GET /training-dashboard/` | Full training analytics |

### Authenticated (mentee flows)

| Route | Purpose |
|-------|---------|
| `GET /my-class/{class}` | Mentee class progress page |
| `GET /my-class/{class}/module/{classModule}` | Module detail (content, quizzes, video) |
| `POST …/quiz/{quiz}/start` | Start a quiz attempt |
| `POST …/quiz/{attempt}/submit` | Submit quiz answers |
| `POST …/video` | Upload hands-on video |
| `GET /enroll/{token}/complete` | Post-enrollment completion page |

### Authenticated (reports)

| Route | Purpose |
|-------|---------|
| `GET /admin/reports/class/{class}/html` | Class progress report (HTML) |
| `GET /admin/reports/class/{class}/pdf` | Class report PDF |
| `GET /admin/reports/class/{class}/certificate/{participant}` | Certificate PDF download |
| `GET /assessments/{assessment}/report` | Facility assessment HTML report |
| `GET /assessments/{assessment}/download` | Facility assessment PDF |
| `GET /assessments/{assessment}/executive` | Executive summary PDF |

---

## 20. Mobile API

Base prefix: `/api/v1/` — middleware: `MobileApiCors`

### Public endpoints

| Endpoint | Purpose |
|----------|---------|
| `POST /v1/auth/login` | Mobile login (returns Sanctum token) |
| `POST /v1/auth/forgot-password` | Password reset request |
| `POST /v1/auth/reset-password` | Password reset submission |
| `GET /v1/health` | Health check |

### Authenticated endpoints (auth:sanctum + api.active)

**Auth & Profile**
- `GET /me` — current user profile
- `POST /logout`, `/logout-all`, `/refresh`
- `GET|PUT /profile/` — view/update profile
- `PUT /profile/password` — change password
- `POST /profile/avatar` — upload avatar

**Facilities & Geography**
- `GET /facilities/` — list facilities
- `GET /facilities/{id}` — facility detail
- `GET /facilities/county/{id}` — facilities by county
- `GET /counties`, `/cadres`, `/departments` — lookup lists

**Assessments (mobile field assessments)**
- Full CRUD: create, read, update, delete
- `POST /assessments/{id}/submit` — submit completed assessment
- `GET /assessments/{id}/responses` — assessment responses
- `GET /assessments/{id}/report` — PDF report (email or download)

**Mentorships (mentor mobile)**
- `GET|POST /mentorships/` — list/create trainings
- `GET /mentorships/{id}/classes/{class}` — class detail
- `GET|POST /classes/{class}/modules/` — list/add modules
- Module lifecycle: start, complete, sessions
- Mentee management: enroll, invite, remove
- `GET|PUT /classes/{class}/enrollment-link` — manage token

**Mentee (self-service mobile)**
- `GET /me/classes/` — my enrolled classes
- `GET /me/classes/{class}` — class progress
- Module detail, quiz start/submit, video upload
- `POST /chat/assistant` — AI advisor (MenteeAiAdvisor → Claude API)

---

## 21. Notifications

### EmONC notification events

All events send: **Filament in-app notification** + **email** (`EmoncNotificationMail` Mailable).

| Event | Trigger | Recipients |
|-------|---------|-----------|
| Activity completed | All module activities marked complete for mentee | Mentee |
| Video submitted | Mentee uploads hands-on video | Mentor + accepted co-mentors |
| Video reviewed | Mentor sets video pass/fail | Mentee |
| Mentor approved | Mentor clicks "Approve Mentee" | Mentee + all `head_drmh` role users |
| Head DRMH certified | Head DRMH clicks "Issue Certificate" | Mentee + mentor |

### Indicator notifications

`IndicatorNotificationService` handles notifications for indicator report submission and validation events.

### Monthly reports

`MonthlyReportObserver` is registered on `MonthlyReport` model. The `GenerateMonthlyReports` artisan command runs on schedule to auto-generate monthly performance reports.

---

## 22. Analytics & Dashboards

### Mentor Dashboard (`admin/mentor-dashboard`)

- KPI cards: active mentees, pending video reviews, completed modules, certified mentees
- Pending video reviews panel (quick action)
- Mentorship breakdown table (paginated 10/page)
- Insight cards from `TrainingAnalyticsService`

### EmONC Dashboard (`analytics/dashboard?mode=emonc` or `admin/emonc-dashboard`)

- **Leaflet.js county heatmap** — mentee/completion density by county
- **Chart.js charts** — completion rate distribution, module progress bars
- **Mentee status matrix** — one row per mentee: module status, video status, CPD points, certified flag
  - Certified rows sorted first
  - JavaScript filter + pagination (25 per page)
  - CPD column shows level + point total

### Mentor Analytics Dashboard (`analytics/dashboard?mode=mentor`)

- Excludes `super_admin` role users and users named "collins simiti" (business rule)
- Scoped to `facility_mentorship` trainings only
- Matrix: one row per **active (live) class**
- Charts: CPD leaderboard, mentee reach, level donut, class status donut, 12-month trend, by cadre/department/facility
- Filters: program, mentor, county→subcounty→facility cascade, cadre, department

### Training Analytics (`/analytics/dashboard`)

- Progressive heatmap: county-level training coverage
- Training explorer: drill-down by region/program/date

### Indicator Progress Dashboard (`admin/indicators/progress-dashboard`)

- Indicator submission rates by county/facility
- Trend charts for key health indicators

---

## 23. External Integrations

### DHIS2

`Dhis2SyncService` — syncs health indicator data to/from the national DHIS2 health information system.

### Claude AI (MenteeAiAdvisor)

`MenteeAiAdvisor` calls the Anthropic API to provide AI-driven recommendations and guidance to mentees. Accessible via:
- Web: `/chat` (mentee portal)
- Mobile: `POST /api/v1/chat/assistant`

### QR Server API

Certificate QR codes are generated via `https://api.qrserver.com`. **Note:** QR codes will not work in air-gapped environments. Production deployments should use a local QR library.

### Email (Laravel Mail)

Configured via `MAIL_MAILER` in `.env`. The platform sends notifications via `EmoncNotificationMail` and other Mailable classes. **Note:** There are duplicate `MAIL_MAILER` entries in `.env` — the last one wins.

---

## 24. Development Reference

### Commands

```bash
# Run all dev servers concurrently
composer run dev

# Individual servers
php artisan serve --port=8001
php artisan queue:listen --tries=1
npm run dev

# Database
php artisan migrate
php artisan migrate:fresh --seed
php artisan db:seed --class=RolePermissionSeeder
php artisan db:seed --class=SuperAdminSeeder

# After adding Filament resources
php artisan shield:generate --all

# Cache
php artisan config:clear && php artisan cache:clear
php artisan storage:link

# Testing
composer test                          # clears config cache then runs PHPUnit
php artisan test
php artisan test --filter=TestName

# Code quality
./vendor/bin/pint                      # format
./vendor/bin/pint --test               # check without fixing
```

### Test accounts (local)

| Role | Email | Password |
|------|-------|----------|
| Super Admin | super@admin.com | password |
| Facility Mentor | zacksoita@gmail.com | password |
| Mentee | test-mentee@example.com | password |

### AppServiceProvider notes

- `URL::forceScheme('https')` and `URL::forceRootUrl()` are active. In local dev over HTTP, these may need to be overridden.
- `AssessmentScoringService` registered as a singleton.
- `MonthlyReportObserver` registered for `MonthlyReport`.
- Livewire components for charts and heatmaps are manually registered here (not auto-discovered).

### Key file paths

| Path | Purpose |
|------|---------|
| `app/Filament/` | All Filament resources, pages, widgets |
| `app/Filament/Resources/MentorshipResource/Pages/` | All mentorship sub-pages |
| `app/Livewire/` | Interactive dashboard Livewire components |
| `app/Services/` | All business logic services |
| `app/Models/Concerns/` | Reusable model traits |
| `resources/views/certificates/` | Certificate PDF Blade templates |
| `routes/web.php` | All web routes |
| `routes/api.php` | All mobile API routes |
| `database/seeders/` | Role/permission and data seeders |

### Known issues

| Issue | Status |
|-------|--------|
| PHPUnit suite fails on SQLite | `tests/TestCase.php` missing `RefreshDatabase` — use MySQL for tests |
| QR codes on certificates | Uses external `api.qrserver.com` — fails air-gapped |
| Bulk certificate ZIP | Requires `ZipArchive` + `Browsershot` (Puppeteer) on the server |
| `shield:generate --all` warning | Throws `ConductRubricAssessment::route` error in non-interactive mode — cosmetic only, does not affect runtime |
| Local dev HTTPS | `URL::forceScheme('https')` in AppServiceProvider may need HTTP override locally |

---

*Document generated from platform codebase analysis, E2E test sessions, and memory profiles. For implementation detail on specific features, see also:*
- `docs/EMONC-IMPLEMENTATION-GUIDE.md`
- `docs/mobile-mentorship-training-flow.md`
- `docs/maternal-health-mentorship-plan.md`
