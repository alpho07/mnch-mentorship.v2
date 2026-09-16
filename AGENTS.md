# AGENTS.md — MNCH Mentorship Platform

> This file is the **agent memory** for the MNCH-Master project. Any AI working in this codebase should read this first to understand the domain, architecture, and common gotchas.

---

## 1. Project Identity

**MNCH Mentorship Platform** is a production Laravel 12 + Filament v3 web application for managing healthcare training and mentorship programs in **Kenya**. MNCH = **Maternal, Newborn and Child Health**.

It is essentially a health-sector **Learning Management / Mentorship Management System** that also handles:

- Centralized MOH trainings and on-site facility mentorships
- Participant enrollment, attendance, and assessments
- A public knowledge base / resource center
- Healthcare commodity inventory and stock transfers
- County-level analytics and Kenya heatmaps

**Repository scale:** ~109 Eloquent models, ~134 migrations, 52 Filament resources, 53 controllers, 25 services.

---

## 2. Technology Stack

- **Framework:** Laravel 12 (PHP ^8.2)
- **Admin panel:** Filament v3 (auto-discovers `app/Filament/`)
- **RBAC:** Spatie Laravel Permission + Filament Shield
- **API auth:** Laravel Sanctum
- **Database:** MySQL, database name `mnch`, timezone `Africa/Nairobi`
- **Frontend build:** Vite + Tailwind CSS v4 + Alpine.js
- **Charts / maps:** Chart.js, Leaflet.js (loaded via CDN in `AppServiceProvider`)
- **PDFs:** barryvdh/laravel-dompdf
- **CSV imports:** league/csv
- **Activity log:** spatie/laravel-activitylog
- **Code style:** Laravel Pint

---

## 3. The Two Training Types (Central Domain Split)

The `Training` model has a `type` discriminator. This is the most important architectural concept.

| Type | Resource | Purpose | Model Marker |
|------|----------|---------|--------------|
| **Global / MOH Training** | `GlobalTrainingResource` (`TrainingResource`) | Centralized workshops/courses (hospital/hotel/online) | `type = 'global_training'` |
| **Facility Mentorship** | `MentorshipTrainingResource` | On-site mentorship at facilities | `type = 'facility_mentorship'` |

### Mentorship hierarchy

```
Training (facility_mentorship)
  └── MentorshipClass (cohort)
        └── ClassModule
              └── ClassSession
                    └── SessionAttendance / ClassAttendance
```

### Global training flow

Create training → add participants (existing user / quick-add / CSV import) → optionally configure weighted assessment categories → track attendance/outcomes.

### Mentorship flow

Create mentorship → create classes → add program modules → auto-create sessions → enroll mentees → start class → mentees confirm attendance → mark attendance → complete modules → submit mentorship.

---

## 4. Geography & Access Scoping

Kenya health geography is central:

```
Division → County → Subcounty → Facility
```

Users are scoped to geography via pivot tables:

- `county_user`
- `subcounty_user`
- `facility_user`

Key helpers on `User`:

- `isAboveSite()` — true for super-admin, division, national, division_lead, national_mentor_lead roles; they see all data.
- `scopedCountyIds()`, `scopedSubcountyIds()`, `scopedFacilityIds()` — everyone else sees only assigned geography.

---

## 5. Roles

There are 15+ Spatie roles:

`super_admin`, `admin`, `division`, `national`, `county`, `subcounty`, `facility_mentor`, `spoke_mentor`, `spoke_mentor_lead`, `division_lead`, `national_mentor_lead`, `county_mentor_lead`, `subcounty_mentor_lead`, `facility_mentor_lead`, `mentee`.

Filament Shield permissions are generated per resource. Run `php artisan shield:generate --all` after adding new resources.

---

## 6. Core Feature Modules

### Training Management
- Programs, modules, methodologies, approved training areas.
- Participant enrollment, attendance, status logs.
- Assessment categories with weighted pass/fail scoring.
- Training materials planning and cost tracking.
- Co-mentor invitations via token links.

### Mentorship Lifecycle
- Tokenized public enrollment: `/enroll/{token}`.
- Tokenized public attendance: `/module/attend/{token}`.
- Class lifecycle: `draft → active → completed/cancelled`.
- `MenteeModuleProgress` tracks per-module progress.
- Reports, certificates, HTML/PDF class reports.

### Knowledge Base / Resources (`Resource` model)
- Public resource center at `/resources/*`.
- Visibility levels: `public`, `authenticated`, `restricted`.
- Restricted visibility scoped by `AccessGroup`, county, facility, department, or authorship.
- Always use `Resource::accessibleTo($user)` scope in queries.
- Features: downloads, previews, thumbnails, views, likes, bookmarks, comments, sitemap, RSS feed.

### Facility Assessments
- Two overlapping assessment subsystems exist:
  1. Training/mentorship participant assessments.
  2. Facility assessments (`FacilityAssessment`, sections, questions, responses, scoring).
- Services: `AssessmentScoringService`, `AssessmentPdfReportService`, `AssessmentExportService`, `AssessmentAnalyticsService`.

### Inventory / Commodities
- Inventory items, categories, batches, stock levels.
- Stock requests and transfers.
- Central store receiving and distribution.
- Low-stock alerts and inventory widgets.

### Analytics & Reporting
- Filament dashboard widgets in `app/Filament/Widgets/`.
- Controller dashboards:
  - `/analytics/dashboard` — drill-down by county → program → facility → participant.
  - `/training-dashboard` — national overview with API-driven drill-downs.
  - `/dashboard` — main dashboard with heatmap data.
- Kenya county heatmaps with GeoJSON + Leaflet.
- Monthly report generation (`GenerateMonthlyReports` command + `MonthlyReportService`).

---

## 7. Service Layer

Complex logic lives in `app/Services/`:

- `TrainingAnalyticsService` — insights and trends
- `TrainingReportService` — report generation
- `EnrollmentService` — mentee class enrollment/removal
- `BulkParticipantImportService` — CSV participant imports
- `AttendanceService` — attendance marking (manual, link, auto)
- `AssessmentScoringService` — singleton registered in `AppServiceProvider`
- `AssessmentPdfReportService`, `AssessmentExportService`, `AssessmentAnalyticsService`
- `MonthlyReportService` — auto-generated monthly reports
- `MenteeAiAdvisor` — AI-driven mentee recommendations
- `FacilityAssignmentService`, `FacilityReportTemplateService`
- `Dhis2SyncService` — external DHIS2 integration
- `IndicatorReportingService`, `IndicatorNotificationService`
- `FileUploadService`, `ResourceService`
- `CommodityScoringService`, `DynamicScoringService`, `DynamicFormBuilder`
- `SmartParticipantSuggestionService`, `ModuleUsageService`, `MentorshipProgressService`

---

## 8. Public Routes (No Auth Required)

- `/enroll/{token}` — mentee self-enrollment
- `/module/attend/{token}` — module attendance confirmation
- `/co-mentor/accept/{token}` — co-mentor invitation acceptance
- `/account/verify/{user}` — new user account verification & password set
- `/resources/*` — public knowledge base frontend

The admin panel is at `/admin` (redirects to `/admin/login`).

---

## 9. Common Commands

```bash
# Development
composer run dev

# Build
npm run build
php artisan filament:upgrade

# Database
php artisan migrate
php artisan migrate:fresh --seed
php artisan db:seed --class=RolePermissionSeeder
php artisan db:seed --class=SuperAdminSeeder

# Testing
composer test
php artisan test --filter=TestName

# Code style
./vendor/bin/pint
./vendor/bin/pint --test

# Permissions / cache
php artisan shield:generate --all
php artisan config:clear && php artisan cache:clear
php artisan storage:link
```

---

## 10. Critical Gotchas

1. **Forced HTTPS / root URL** — `AppServiceProvider` calls `URL::forceScheme('https')` and `URL::forceRootUrl()`. Be aware during local HTTP development.
2. **Migration overlap** — Multiple overlapping migrations exist, especially for assessments. Inspect existing table structures before adding new migrations.
3. **Livewire upload bypass** — A custom `/livewire/upload-file` route overrides Livewire's default signed-URL upload behavior.
4. **Two assessment systems** — There are legacy and current assessment tables/models; verify which subsystem you are touching.
5. **Mobile API in flux** — The project has a growing `/api/v1` mobile API layer. `docs/mobile-mentorship-training-flow.md` documents the current state and known backend risks (e.g., unresolved conflict markers in some controllers, enrollment rule mismatches, missing endpoints).
6. **Resource access** — Always query resources with `Resource::accessibleTo($user)` rather than filtering collections after fetch.
7. **Geographic scoping** — Most non-admin queries should respect `scopedCountyIds()` / `scopedFacilityIds()`.

---

## 11. File Locations to Know

| Concern | Location |
|---------|----------|
| Models | `app/Models/` |
| Filament resources | `app/Filament/Resources/` |
| Filament widgets | `app/Filament/Widgets/` |
| Controllers | `app/Http/Controllers/` |
| API controllers | `app/Http/Controllers/Api/` |
| Services | `app/Services/` |
| Livewire components | `app/Livewire/` |
| Migrations | `database/migrations/` |
| Seeders | `database/seeders/` |
| Tests | `tests/` |
| Frontend assets | `resources/` |
| Public knowledge base views | `resources/views/frontend/` |
| Mobile API spec | `docs/mobile-mentorship-training-flow.md` |
| Project guidance (Claude) | `CLAUDE.md` |

---

## 12. Summary for Quick Recall

> This is a **Kenyan MNCH training & mentorship platform** built with **Laravel 12 + Filament 3**. It runs two kinds of programs — centralized MOH trainings and facility mentorships — across a Division→County→Subcounty→Facility hierarchy, with role-based access, assessments, a public knowledge base, inventory tracking, and county-level analytics. The codebase is large and actively evolving, especially its mobile API layer.
