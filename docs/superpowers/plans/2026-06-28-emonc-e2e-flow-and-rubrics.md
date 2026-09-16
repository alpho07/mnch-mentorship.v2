# EmONC End-to-End Flow & Rubric Assessment — Implementation Plan

> **Status as of 2026-06-28.** Tracks the full lifecycle: mentor creates EmONC mentorship → invites mentees → mentees do pre-tests, video uploads, case scenario reviews → mentor uses rubrics to evaluate → cert chain.

**Goal:** Deliver a complete, testable EmONC mentorship cycle within the MNCH platform. A mentor creates a facility-level EmONC class, enrolls mentees, delivers content module-by-module, and evaluates each mentee via pre/post quizzes, video review, and hands-on practical rubric assessments. On completion, an EmONC certificate is generated after mentor + Head DRMH approval.

**Tech Stack:** Laravel 12, Filament v3, Livewire, Spatie Permission, Blade, Alpine.js, Tailwind CSS v4.

---

## Architecture Summary

```
Training (type=facility_mentorship, program=EmONC)
 └─ MentorshipClass
     └─ ClassModule (linked to ProgramModule)
         └─ ClassParticipant (mentee)
             └─ MenteeModuleProgress
                 ├─ QuizAttempt (pre-test / post-test)
                 ├─ video_review_status / video_review_notes
                 └─ ClassModuleActivityParticipant (per activity)
         └─ RubricAssessment (mentor → mentee, linked to ModuleRubric)
             └─ RubricItemResponse (per checklist item)
 └─ Certification chain:
     ClassParticipant.mentor_approved_at → head_drmh_approved_at → certificate PDF
```

---

## Feature Completion Status

### ✅ DONE — Backend & Data

| Item | Evidence |
|------|----------|
| EmONC program seeded (13 modules, 10 PPH tracks) | `php artisan tinker` → ProgramModule::count() |
| 12 rubrics seeded (Modules 4 & 5 + Tracks 1–10) | 252 checklist items in DB |
| ModuleRubric, RubricItem, RubricAssessment, RubricItemResponse models | Migrations ran (batch 77) |
| RubricManagementResource (Curriculum nav) | Route: `admin/rubric-managements` → 302 |
| RubricAssessmentResource + ConductRubricAssessment wizard | Route: `admin/rubric-assessments` → 302 |
| Shield permissions for both rubric resources | super_admin + mentors: `view_any_rubric::*` = YES |
| EmoncNotificationService | File exists, used in ReviewModuleMentee |
| ReviewModuleMentee page with rubric section | Route registered: `mentees/{participant}/review` |
| Mentor Dashboard (hero, insight cards, class list) | File: mentor-dashboard.blade.php |
| Mentee Dashboard (class cards, module status) | File: mentee-dashboard.blade.php |
| AphModuleContentSeeder | Populates CHAI APH content |
| Rich user-menu header | Component: user-menu-header.blade.php |
| MyProfile page | File: my-profile.blade.php |
| Role-based nav visibility | Mentors → Mentorships/Trainings/Assessments; Mentees → Mentee Dashboard |

### 🧪 NEEDS BROWSER TESTING (current focus)

Test the full EmONC mentorship lifecycle in order:

- [ ] **T1** — Super admin logs in, navigates to Mentorships → create new EmONC mentorship training
- [ ] **T2** — Mentor creates a class within the training, adds modules
- [ ] **T3** — Mentor invites mentees (enrollment link or manual add)
- [ ] **T4** — Mentee logs in, sees Mentee Dashboard with enrolled class
- [ ] **T5** — Mentee opens module, takes **pre-test**
- [ ] **T6** — Mentee uploads video (module activity completion)
- [ ] **T7** — Mentee completes case scenario activity (if applicable)
- [ ] **T8** — Mentor opens ReviewModuleMentee page, sees pre-test status
- [ ] **T9** — Mentor reviews video (sets video_review_status = passed/failed)
- [ ] **T10** — Mentor conducts rubric assessment (ConductRubricAssessment wizard)
- [ ] **T11** — Mentee takes **post-test**
- [ ] **T12** — Mentor sees all green on ReviewModuleMentee — clicks Approve
- [ ] **T13** — Head DRMH approves → certificate generated
- [ ] **T14** — Rubric Management (admin) — create/edit a rubric, add/reorder items

### 🔲 NOT STARTED

- [ ] Pre-test / post-test content for Modules 4 & 5 (user to share — mentioned in email)
- [ ] Video upload size limits & storage configuration for production
- [ ] Email notification to mentee on enrollment
- [ ] Email notification to Head DRMH when mentor approves
- [ ] Certificate PDF polish (logo, signatures)
- [ ] Mobile mentee app flow (separate scope)
- [ ] Reporting: rubric assessment aggregate report per cohort

---

## Key Routes (all behind auth)

| URL | Purpose |
|-----|---------|
| `admin/mentorship` | List EmONC trainings |
| `admin/mentorship/create` | Create new EmONC training |
| `admin/mentorship/{id}/classes` | Manage classes |
| `admin/mentorship/{t}/classes/{c}/modules` | Manage modules |
| `admin/mentorship/{t}/classes/{c}/modules/{m}/mentees` | Mentee list per module |
| `admin/mentorship/{t}/classes/{c}/modules/{m}/mentees/{p}/review` | Mentor full review of one mentee |
| `admin/rubric-managements` | List/create/edit rubrics |
| `admin/rubric-assessments` | List practical assessments |
| `admin/rubric-assessments/create` | Conduct practical assessment wizard |
| `admin/mentor-dashboard` | Mentor dashboard |
| `admin/mentee-dashboard` | Mentee dashboard |

---

## Known Issues / Bugs

| Issue | Status |
|-------|--------|
| Playwright MCP Bridge must be connected in Chrome for browser testing | External — user must open browser |
| `shield:generate --all` throws `ConductRubricAssessment::route` error in non-interactive mode | Cosmetic — does not affect runtime |
| Dev server must be on port 8001 (not 8000) | `php artisan serve --port=8001` |

---

## Test Accounts (local)

| Role | Email | Password |
|------|-------|----------|
| Super Admin | super@admin.com | password |
| Mentor (facility_mentor) | zacksoita@gmail.com | password |
| Mentee | (check users table with role=mentee) | password |

---

## Decisions Made

- **Rubric items are binary (performed/not performed)** — matches CHAI APH rubric format; no partial marks per item
- **Pass mark stored on ModuleRubric** — not a percentage, so it can be updated without changing historical data
- **RubricAssessment is linked to ModuleRubric, not ClassModule** — rubrics are reusable across cohorts
- **ReviewModuleMentee is a Filament resource page (not standalone)** — shares nav/breadcrumbs with MentorshipTrainingResource
- **ConductRubricAssessment is a 2-step Livewire wizard** — step 1 selects context, step 2 scores items with live score bar
- **Video review is mentor-only** — mentee uploads, mentor reviews and sets pass/fail status in ReviewModuleMentee

---

## Exact Next Step

Run the full E2E test sequence T1–T14 above using Chrome + Playwright MCP Bridge on `http://localhost:8001`. Start with super@admin.com to create the training, then switch to mentor and mentee accounts.
