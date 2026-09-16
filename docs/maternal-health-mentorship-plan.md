# Maternal Health Mentorship — Implementation Plan (In Progress)

> **DEPRECATED COPY** — the authoritative implementation plan is `maternal-health-mentorship-plan.md` in the repository root.
> Status: Phase 8 complete. See root file for current details.
> Created: 2026-06-15
> Updated: 2026-06-22
> Context: Adding a Maternal Health (EmONC) mentorship program to the existing MNCH platform without disrupting Infant Care, Newborn Care, and Child Care programs.

---

## 1. What was explored

The following core models, Filament resources, migrations, controllers, views, and seeders were reviewed to understand the current mentorship architecture:

### Key models
- `Program` / `ProgramModule` / `ModuleSession` — curriculum templates
- `Training` — discriminator `type = 'facility_mentorship'` for mentorships
- `MentorshipClass` — class/cohort
- `ClassModule` — program module assigned to a class
- `ClassSession` — scheduled session within a class module
- `ClassParticipant` / `MenteeModuleProgress` — enrollment and progress
- `ClassAttendance` / `SessionAttendance` — attendance records
- `ModuleAssessment` / `ModuleAssessmentResult` — assessments and scoring
- `User` — roles and geographic scoping

### Key Filament resources / pages
- `MentorshipTrainingResource` — mentorship CRUD + program picker
- `ManageMentorshipClasses` — class/cohort creation and module assignment
- `ManageClassModules` — add/remove/start/complete modules
- `ManageClassMentees` — enroll mentees, send invites, start/end class
- `ManageModuleSessions` — sessions inside a module
- `ManageModuleAssessments` — module assessments and results
- `ProgramResource` / `ModuleResource` — curriculum admin

### Key controllers / routes
- `MenteeEnrollmentController` — public `/enroll/{token}` flow
- `MenteeClassProgressController` — authenticated `/my-class/{class}` dashboard
- `ModuleAttendanceController` — public `/module/attend/{token}` attendance
- Web routes in `routes/web.php`

### Seeders
- `RolePermissionSeeder` — existing roles
- `ProgramModulesSeeder` — Infant/Child and Newborn program modules

---

## 2. Current architecture (reusable foundation)

```
Program
  └── ProgramModule
        └── ModuleSession (template)

Training (facility_mentorship)
  └── MentorshipClass (cohort)
        └── ClassModule → ProgramModule
              └── ClassSession
              └── ClassAttendance
              └── ModuleAssessment
        └── ClassParticipant (mentee enrollment)
              └── MenteeModuleProgress
              └── ModuleAssessmentResult
```

This architecture already supports:
- Program-based curriculum
- Cohort management
- Module-level attendance
- Assessment scoring
- Public enrollment via token
- Geographic scoping (county → subcounty → facility)
- Role-based access

---

## 3. EmONC logbook findings

The reference document (`EmONC SKILLS LOGBOOK`) defines:

- **13 modules:**
  1. Ante Partum Hemorrhage
  2. Partograph Use and Interpretation
  3. Obstructed Labour
  4. Active Management of the Third Stage of Labor (AMSTL)
  5. Management of Postpartum Hemorrhage (PPH)
  6. Management of Cord Prolapse
  7. Vaginal Breech Delivery
  8. Shoulder Dystocia Delivery
  9. Vaginal Vacuum Assisted Delivery
  10. Maternal Resuscitation
  11. Management of Maternal Shock
  12. Management of Pre-Eclampsia/Eclampsia
  13. Immediate Neonatal Resuscitation

- **Module 5 contains 10 tracks (sub-skills):**
  1. Bimanual compression of the uterus
  2. Compression of abdominal aorta
  3. Removal of retained placenta
  4. Uterine inversion
  5a. The placement of the intrauterine balloon tamponade (condom tamponade)
  5b. The placement of the intrauterine balloon tamponade (free flow system)
  6. Repair of perineal tear
  7. Repair of cervical tear
  8. The placement of the B-Lynch suture
  9. Placement of non-pneumatic anti-shock garment (NASG)
  10. Post-partum hemorrhage simulation

- **Pass mark:** 85% per skill/module/track.
- **Certification flow:**
  1. Mentor selects and orients mentee
  2. Training and mentorship by mentor
  3. Assessment by mentor
  4. Mentee scores ≥ 85% for each skill
  5. Mentee completes all topics/skills
  6. Mentor approves mentee for certification
  7. Certification by DRMH (Head DRMH)
- **Mentorship timeline:** 6 months closure; recommended weekly 6 hours.
- **Module/track logbook page includes:** start date, end date, score, pass/fail (≥85%), additional comments, mentor signature.

---

## 4. Proposed design

### 4.1 Program structure

```
Program: Maternal Health (EmONC)
  └── ProgramModule 1..13
        └── ProgramModuleTrack (only Module 5 in EmONC, but model supports any module)
        └── ProgramModuleActivity templates: CME, Hands-on Demo, Drill
        └── ProgramModuleContent: intro notes, videos, case scenarios
        └── ProgramModuleAssessment: pre-test and post-test question banks
```

### 4.2 New tables/models (tentative)

| Table / Model | Purpose |
|---------------|---------|
| `program_module_tracks` | Sub-skills under a `ProgramModule` |
| `program_module_contents` | Curated learning items: intro notes, YouTube links, case scenarios |
| `program_module_assessments` | Pre-test / post-test question banks per module/track |
| `program_module_activities` | Activity templates (CME, Hands-on Demo, Drill) per module/track |
| `class_module_tracks` | Track instance within a `ClassModule` |
| `class_module_activities` | Activity instance within a `ClassModule` / `ClassModuleTrack` |
| `class_module_activity_attendance` | Which mentees attended each activity |
| `mentee_assessment_attempts` | Pre-test / post-test attempts and scores |
| `mentee_uploads` | Hands-on video uploads by mentees |
| `mentee_activity_progress` | Per-mentee activity completion tracking |
| `module_rubrics` / `mentee_rubric_scores` | Configurable rubric criteria + mentor scores |

### 4.3 Changes to existing tables

- `trainings` — remove `start_date` / `end_date` requirement for `facility_mentorship`.
- `mentorship_classes` — remove `start_date` / `end_date` requirement.
- `class_modules` — add `start_date`, `end_date`.

### 4.4 Mentor flow

1. Create mentorship:
   - Select County → Facility
   - Select Program (Maternal Health)
   - Set number of mentees
2. Create class/cohort:
   - Name of class/cohort
   - Description of identified gap
3. Select mentees/newbies
4. Select modules (with start/end dates):
   - Activities (CME, Hands-on Demo, Drill) auto-attach
   - Track activities also auto-attach where applicable
5. Send invite link to mentees
6. Mark activity attendance and completion
7. Score hands-on video uploads using rubric
8. Approve certificate

### 4.5 Mentee/newbie flow

1. Receive invite link
2. Enter email address as check
3. Log in
4. See class and open it
5. Per module/track dashboard:
   - Introduction notes
   - YouTube video links/resources
   - Pre-test
   - Hands-on video upload
   - Case scenario
   - Post-test
   - Grades (automated)
6. Visibility gated by activity completion

### 4.6 Admin / SME curriculum builder

Dedicated admin page where subject matter experts can curate universal learning materials and attach them to modules/tracks:
- Introduction notes
- YouTube/video links
- Pre-test questions
- Post-test questions
- Case scenarios
- Activity templates and rubric checklists

### 4.7 Roles

Add a new `newbie` role alongside the existing `mentee` role.

| Role | Description |
|------|-------------|
| `super_admin` | All functions |
| County health coordinator | Tied to county |
| Sub-County health coordinator | Tied to subcounty |
| Mentor / Overseer / Supervisor | Tied to mentorships they created and their mentees |
| `mentee` | Health worker enrolled for mentorship; can train newbie if elevated by super admin |
| `newbie` | Complete amateur; eligible to be trained |

---

## 5. Implementation phases (proposed)

### Phase 1 — Foundation and data
- Add `newbie` role to `RolePermissionSeeder`
- Seed Maternal Health (EmONC) program with 13 modules and Module 5 tracks
- Add `start_date` / `end_date` to `class_modules`

### Phase 2 — Admin curriculum builder
- New models and migrations for tracks, content, assessments, activities, rubrics
- Admin UI for SMEs to curate module/track content

### Phase 3 — Mentor mentorship flow changes
- Update mentorship creation form: remove dates, keep program/facility/county/mentees
- Update class creation: remove dates, add gap description
- Update module assignment: add start/end dates, auto-create activities and tracks

### Phase 4 — Activity execution and attendance
- Activity instance creation
- Mentor activity attendance UI
- Activity completion triggers progress updates

### Phase 5 — Mentee dashboard enhancement
- Extend `/my-class/{class}` with module/track content
- Pre-test and post-test taking
- Hands-on video upload
- Activity-gated visibility

### Phase 6 — Scoring and certification
- Rubric scoring UI for mentor
- Two-step certificate approval (mentor → Head DRMH)
- PDF certificate generation

---

## 6. Open questions (awaiting user clarifications)

1. **Program naming:** Is the Maternal Health program the same as EmONC, or is EmONC just one example? Should it be seeded as “Maternal Health (EmONC)”?

2. **Activity unlocking sequence:** Should the mentee dashboard unlock strictly like this:
   - CME done → Introduction notes + videos visible
   - Hands-on Demo done → Pre-test + hands-on video upload visible
   - Drill done → Case scenarios + post-test visible
   Or a different mapping?

3. **Pre-test / post-test creation:** Should SMEs create MCQ questions per module/track in the admin, or start with a generic question builder?

4. **Hands-on rubric:** Should the rubric be a configurable checklist per module/track (e.g., specific steps for “Bimanual compression of the uterus”), or generic criteria like Technique / Communication / Safety / Overall?

5. **Certificate approval flow:** Should the system enforce both Mentor approval then Head DRMH certification, or is mentor approval enough for now with Head DRMH added later?

---

## 7. Notes for resumption

- Existing Infant Care, Newborn Care, and Child Care programs should remain untouched.
- The new Maternal Health flow will reuse the same tables but add program-specific curriculum content and activity tracking.
- All changes should be implemented via migrations and should be backward-compatible where possible.
- After adding new Filament resources, run `php artisan shield:generate --all` to regenerate permissions.
