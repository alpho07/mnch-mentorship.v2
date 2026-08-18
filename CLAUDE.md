# CLAUDE.md
## Token discipline


This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

MNCH Mentorship Platform — a Laravel 12 + Filament v3 application for managing healthcare training and mentorship programs in Kenya. It tracks training events, mentorship classes, participant progress, facility assessments, knowledge base resources, and inventory.

## Commands

### Development
```bash
# Run all dev servers concurrently (web + queue + logs + vite)
composer run dev

# Or individually:
php artisan serve
php artisan queue:listen --tries=1
npm run dev
```

### Build
```bash
npm run build
php artisan filament:upgrade   # Run after Filament package updates
```

### Database
```bash
php artisan migrate
php artisan migrate:fresh --seed
php artisan db:seed --class=RolePermissionSeeder
php artisan db:seed --class=SuperAdminSeeder
```

### Testing
```bash
composer test                  # Clears config cache then runs PHPUnit
php artisan test               # Run all tests
php artisan test --filter=TestName   # Run a single test
```

### Code Quality
```bash
./vendor/bin/pint              # Laravel Pint (code formatter)
./vendor/bin/pint --test       # Check without fixing
```

### Useful Artisan
```bash
php artisan shield:generate --all   # Regenerate Filament Shield permissions
php artisan config:clear && php artisan cache:clear
php artisan storage:link       # Link public storage
```

## Architecture

### Admin Panel (Filament)
- Panel ID: `admin`, path: `/admin`
- All resources, pages, and widgets are **auto-discovered** from `app/Filament/`
- Permission UI gating via `FilamentShield` — permissions are generated per resource with `php artisan shield:generate --all`
- Navigation groups (order matters in sidebar): Dashboards, Training Management, Indicator Catalog, knowledge Base, Reporting, Curriculum, Organization Units, Inventory, Report Management, Reports & Analytics

### Training Domain — Two Types
The `Training` model has a `type` discriminator:
- `global_training` — centralized trainings managed via `GlobalTrainingResource` / `TrainingResource`
- `facility_mentorship` — on-site mentorships managed via `MentorshipTrainingResource` (pages live in `app/Filament/Resources/MentorshipResource/Pages/`)

Mentorship structure: `Training` → `MentorshipClass` → `ClassModule` → `ClassSession` → `SessionAttendance`

### Geographic Hierarchy
Division → County → Subcounty → Facility

Users are scoped to counties/subcounties/facilities via pivot tables (`county_user`, `subcounty_user`, `facility_user`).

`User::isAboveSite()` returns `true` for roles: Super Admin, Division Lead, National Mentor Lead — these see all data. Other roles see only their assigned geographic scope via `scopedCountyIds()`, `scopedFacilityIds()`, etc.

### RBAC (Spatie Permission + Filament Shield)
Roles: `super_admin`, `admin`, `division`, `national`, `county`, `subcounty`, `facility_mentor`, `spoke_mentor`, `spoke_mentor_lead`, `division_lead`, `national_mentor_lead`, `county_mentor_lead`, `subcounty_mentor_lead`, `facility_mentor_lead`, `mentee`

### Knowledge Base / Resources
The `Resource` model (knowledge base articles/files) uses `HasAccessControl` trait for visibility:
- `public` — accessible to guests
- `authenticated` — any logged-in user
- `restricted` — scoped by `AccessGroup`, facility assignment, or authorship

Use `Resource::accessibleTo($user)` scope in queries rather than filtering after fetch.

### Service Layer
Complex business logic lives in `app/Services/`. Key services:
- `TrainingAnalyticsService` — insights and trends
- `AssessmentPdfReportService` / `AssessmentExportService` — facility assessment PDF/CSV report generation (used by `ViewAssessmentSummary` and the assessments table's Download action)
- `EnrollmentService` — mentee class enrollment
- `BulkParticipantImportService` — CSV participant imports
- `MonthlyReportService` — auto-generated monthly reports (via `GenerateMonthlyReports` command)
- `MenteeAiAdvisor` — mentee AI recommendations

### Frontend
- Tailwind CSS v4 + Alpine.js + Chart.js (via Vite)
- Leaflet.js loaded via `FilamentAsset` for Kenya county heatmaps (CDN, not npm)
- Livewire components for interactive dashboard panels (`app/Livewire/`)

### Key Traits
- `HasAccessControl` (`app/Models/Concerns/`) — resource visibility and `accessibleTo` scope
- `HasFileManagement` (`app/Models/Concerns/` and `app/Traits/`) — file upload handling
- `HasTrainingFilters` — reusable query filters for training queries

### Public Routes (no auth required)
- `/enroll/{token}` — mentee self-enrollment via token link
- `/module/attend/{token}` — module attendance confirmation
- `/co-mentor/accept/{token}` — co-mentor invitation acceptance
- `/account/verify/{user}` — new user account verification & password set
- `/resources/*` — public knowledge base frontend

### AppServiceProvider Notes
- `URL::forceScheme('https')` and `URL::forceRootUrl()` are set — be aware in local dev if HTTP is needed
- `MonthlyReportObserver` is registered for `MonthlyReport`
- Livewire components for charts/heatmaps are manually registered here

### Database
- MySQL, database name: `mnch`
- Timezone: `Africa/Nairobi`
- Multiple overlapping migration files exist for assessments and reports (some deleted in git history). When adding migrations, check existing table structures carefully before altering.
