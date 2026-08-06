# Phase 1: Discovery & Baseline

**Status:** Discovery document, later updated with fix outcomes. The original 2026-08-06 pass was discovery-only (no code changed). A same-day follow-up, done with explicit per-item user approval, fixed several Risk Register items (§9) — those are marked **FIXED** inline with what changed and why; everything else remains untouched, logged-only.
**Scope:** Per the Production-Safe System Audit process — Phase 1 tasks (architecture, module/route/permission/database/report inventories, dependency mapping, baseline counts, test/backup posture), plus Phase 2's first regression tests (`docs/superpowers/plans/2026-08-06-phase2-testing-safety-net.md`) and a subsequent risk-fix pass. Broader recommendations/implementation beyond what's noted as fixed still await Phase 3+ review.
**Generated:** 2026-08-06

---

## 1. Architecture Snapshot

| Layer | Value |
|---|---|
| Backend | Laravel `^12.0`, PHP `^8.2` |
| Admin panel | Filament `^3.3` + `bezhansalleh/filament-shield ^3.3` |
| RBAC | `spatie/laravel-permission ^6.20` |
| Audit log | `spatie/laravel-activitylog ^4.10` |
| API auth | `laravel/sanctum` (mobile app) |
| PDF | `barryvdh/laravel-dompdf ^3.1` + Puppeteer via `spatie`-style `Browsershot` (JS dep, not in composer.json) |
| CSV | `league/csv ^9.24` |
| Images | `intervention/image ^3.11` |
| Frontend | Tailwind CSS `^4.0`, Alpine.js `^3.14.9`, Chart.js `^4.5.0`, Vite `^6.2.4` |
| Maps | Leaflet `1.9.4` + `leaflet.heat` + Turf.js `6.5.0` — **CDN only (unpkg.com), not an npm dependency** |
| Queue | `database` driver (both `.env` and default) |
| Cache | `database` driver |
| Session | `.env` overrides default to `file` (config default is `database`) |
| Logging | `stack` → `single` channel → `storage/logs/laravel.log` |
| Default filesystem disk | `local`. `s3` / `s3-private` disks are defined but **not populated** with AWS credentials in current `.env` |
| Local DB | MySQL, `DB_DATABASE=mnch-feb` (not `mnch` — verify per environment; CLAUDE.md's `mnch` reference is aspirational/other-environment) |
| Deployment/CI | **None found.** No Dockerfile, Procfile, docker-compose, `.github/workflows/`, or `app.json` exist anywhere in the repo. |

### AppServiceProvider notes
- `URL::forceScheme('https')` / `URL::forceRootUrl()` are present but **currently commented out** — contradicts the CLAUDE.md note that says they're active. Verify per-environment before assuming HTTPS is force-enforced.
- One model observer: `MonthlyReport::observe(MonthlyReportObserver::class)`.
- Custom rate limiters: `search`, `downloads`, `previews`, `interactions`, `comments`, `guest-comments`, `comment-updates`, `api`, `uploads`, `admin`.
- 3 Livewire components manually registered: `training-coverage-stats-widget`, `training-charts-widget`, `kenya-training-heatmap-widget` (a 4th, `mentor-stats-widget`, is commented out — dead reference or paused feature).
- Custom `/livewire/upload-file` route registered ahead of Livewire's own — intentional override, worth understanding before touching file upload behavior.

---

## 2. Domain Model Summary

*(Consolidated from prior verified project memory — see `domain_models.md`.)*

```
Geographic: Division → County → Subcounty → Facility
  Users scoped via county_user / subcounty_user / facility_user pivots

Curriculum: Program → ProgramModule (parent_id null=module, set=track)
  → ProgramModuleContent, ProgramModuleActivity→Activity, ProgramModuleQuiz→QuizQuestion→QuizOption

Mentorship: Training (type=facility_mentorship) → MentorshipClass → ClassModule → ProgramModule
  → ClassSession → SessionAttendance
  → ClassModuleActivityParticipant (per-mentee activity enrollment/completion)
  → MenteeModuleProgress (quiz attempts, video, review status)
  ClassParticipant (mentee enrollment) → mentor_approved_at/by, head_drmh_approved_at/by → isCertified()

Facility Assessment (separate domain): Assessment → AssessmentSection → AssessmentQuestion
  → AssessmentQuestionResponse, AssessmentSectionScore, AssessmentDepartmentScore
  → HumanResourceResponse, CommodityResponse (commodities/commodity_categories)

Indicator Reporting (DHIS2-style): IndicatorReportType → IndicatorFrequency → IndicatorGroup → Indicator
  → FacilityIndicatorAssignment, IndicatorReportPeriod (draft→submitted→validated→pushed_to_dhis2) → IndicatorValue
```

Programs in DB: **Newborn Care**, **Infant and Child Care**, **Maternal Health (EmONC)** — 13 modules, Module 5 has 10 sub-track children.

---

## 3. Module Inventory (Filament)

Navigation groups (order as registered): Dashboards → Mentorships → Facility Assessment → Training Management → Indicator Catalog → knowledge Base (lowercase 'k', pre-existing typo — do not "fix" without confirming nothing depends on the literal string) → Reporting → Curriculum → Organization Units → Inventory → Report Management → Reports & Analytics.

| Module | Key resources/pages | Notes |
|---|---|---|
| Dashboards | `MentorDashboard`, `MenteeDashboard`, `EmoncDashboard`, `TrainingCoverageDashboard`, `CoverageDashboard`, `CoverageOverview`, `MentorshipProgressDashboard` | Role-gated via `page_*` permissions |
| Mentorships | `MentorshipTrainingResource` + ~13 sub-pages (`ManageMentorshipClasses`, `ManageClassModules`, `ManageClassMentees`, `ManageModuleMentees`, `ReviewModuleMentee`, `ManageModuleResources`, `ManageMentorshipCoMentors`, etc.) | Custom `userCanAccess()` — mentees excluded unless `can_create_mentorships` flag set |
| Facility Assessment | `AssessmentResource` + Type/Section family | Live schema as of 2026-08-01 batch — see §5 |
| Training Management | `GlobalTrainingResource`, `TrainingResource`, `TrainingExportResource` | |
| Indicator Catalog | `IndicatorResource`, `FillReport`, `IndicatorReporting`, `ReviewReport`, `ValidationQueue` (Livewire pages) | Feeds DHIS2 sync (§7) |
| Knowledge Base | `AccessGroupResource`, `CategoryResource`, `ResourceType`, `ResourceResource` | Uses `HasAccessControl` trait, independent of geo-scoping |
| Curriculum | `ProgramModuleResource`, `ProgramModuleQuizResource` | |
| Organization Units | `CountyResource`, `DivisionResource`, `SubcountyResource`, `FacilityResource` | |
| Inventory | Commodity/Stock request family | Heavy audit trail (`requested_by`, `approved_by`, `dispatched_by`, `received_by`) |
| Report Management | `MonthlyReportResource` | **Facility-scoping check on this resource is currently a no-op — see Risk Register §9.1** |
| Reports & Analytics | Analytics dashboards | |

~90+ resources/pages override `canAccess()`/`shouldRegisterNavigation()`; the overwhelming majority just re-check `can('{action}_{slug}')`, which policies already enforce — duplicated but not contradictory. Exceptions with real custom logic are listed in §4.

---

## 4. RBAC / Permission Matrix

### Confirmed current role list (from `RolePermissionSeeder::ensureRoles()` — supersedes prior memory)

```
super_admin, admin, division, national, county, subcounty,
facility_mentor, facility_mentor_lead, spoke_mentor, spoke_mentor_lead,
county_mentor_lead, subcounty_mentor_lead, national_mentor_lead, division_lead,
national_mentor, head_drmh, mentee, assessor, inventory, reporting,
resource_manager, team_lead
```

**Corrections vs. prior memory:** `newbie` role does not exist anywhere in the codebase (zero grep hits) — drop it from any future reference. `national_mentor` (base) and `national_mentor_lead` (full authority) are two separate roles, easy to conflate.

- `super_admin` and `admin` are currently **permission-equivalent** — both get `Permission::all()` in the seeder.
- Permissions follow Shield's `{action}_{resource_slug}` convention, generated by hand-written combinatorial expansion (`FULL`/`MANAGE`/`WRITE`/`READ` action sets × resource-slug groups) in `RolePermissionSeeder.php` — this is **not** a `shield:generate --all` artifact, so a schema/resource change requires updating this seeder's slug groups, not just running Shield's generator.
- 3 non-Shield extra permissions seeded directly: `page_HeadDrmhDashboard`, `page_EmoncDashboard`, `page_HeadDrmhReviewMentee`.

### Policies
47 policy classes in `app/Policies/`, one per Filament resource, all pure Shield boilerplate (`$user->can('{action}_{slug}')`, no ownership logic). One defect found — see Risk Register §9.2.

### Custom access-control logic (beyond plain Shield checks)
- `MentorshipTrainingResource::userCanAccess()` — mentees excluded unless `can_create_mentorships` is true; others need `view_any_mentorship::training` OR the flag.
- `CreateMentorshipTraining` — additionally gated by `Setting::getBool(Setting::NEW_MENTORSHIP_BUTTON_ENABLED)` (a real runtime feature flag already in use — good precedent to follow for future additive rollouts).
- `GuidedMentorshipSetup` / `ChatMentorshipSetup` / `MnchGptSetup` pages chain extra conditions on top of `parent::canAccess()`.
- `MonthlyReportResource` / `EditMonthlyReport` call `User::canAccessFacility($record->facility_id)` — **fixed 2026-08-06**, see §9.1.

### Middleware
- `MobileApiCors` — hand-rolled CORS for `/api/v1/*` (native app requests arrive with no `Origin` header, which Laravel's built-in CORS middleware skips).
- `EnsureUserIsActive` (alias `api.active`) — revokes Sanctum token + 403s if `status === 'inactive'`.
- ~~`CheckResourceAccess`~~ — dead code, removed 2026-08-06 (§9.8).

### Geographic scoping mechanisms (two independent systems — do not assume one covers the other)
1. `User::isAboveSite()` / `scopedCountyIds()` / `scopedSubcountyIds()` / `scopedFacilityIds()` — primary mechanism for mentorship/training/facility data.
   - `scopedFacilityIds()` also checks roles `county_admin` and `County Mentor Lead` — **neither exists in the current role list**; likely dead legacy condition, left in place, harmless but confusing.
2. `HasAccessControl` trait (`app/Models/Concerns/`) — separate visibility system for knowledge-base `Resource` records (public/authenticated/restricted + access groups + facility fallback). Independent of `isAboveSite()`.

---

## 5. Database Inventory

Full migration list captured (150+ files); see agent transcript for exhaustive filenames. Highlights below.

### 5.1 Assessments — overlap confirmed, but self-resolved (matches CLAUDE.md's warning)

- The `2025_11_29_*` batch (`assessment_types`, `assessment_sections`, `assessment_questions`, `assessments`) is explicitly marked superseded in its own migration bodies (comment + early `return;` before dead `Schema::create` code) — **inert, not a live risk**.
- The `2025_12_01_*` batch is the **current live schema**: `assessments`, `assessment_sections`, `assessment_questions`, `assessment_question_responses`, `assessment_section_scores`, `assessment_department_scores`, `assessment_departments`, `human_resource_responses`, `commodity_categories`/`commodities`/`commodity_applicability`/`assessment_commodity_responses`.
- `assessment_types` was patched twice post-hoc (`fix_assessment_types_id_column_type`, `repair_assessment_types_table_schema`) to correct a production drift where the live table didn't match its own creation migration.
- **One genuine orphan**: `2025_11_29_120458_create_assessment_responses_table` was never marked superseded (only `hasTable()`-guarded), so it can still silently create a stray `assessment_responses` table (singular-ish name, distinct from the live `assessment_question_responses`). No model/controller references it — confirmed dead, but not yet removed. **Do not delete without a formal check per the audit's "prove unused" rule**, even though it looks safe.
- `has_nbu`/`has_paediatric`/`nbu_*`/`paediatric_*` columns were added 2025_12_01 and later **dropped** 2026_08_01 in favor of generic `assessment_question_responses.metadata` — a real precedent of a completed, intentional cleanup (expand-and-contract done correctly).
- A stray non-migration file (`AYP_HIV_Cascade_Report_Dec2025.pdf`) sits inside `database/migrations/` — cosmetic issue, flag for cleanup, not a functional risk.

### 5.2 Reports / Indicators
Single clean migration chain, no duplication found: `indicator_report_types → indicator_frequencies → indicator_groups → indicators → facility_indicator_assignments → indicator_report_periods → indicator_values`. No separate `monthly_reports` table — `MonthlyReportResource` operates over this indicator-period model.

### 5.3 Soft-deletes tables
`suppliers`, `inventory_categories`, `inventory_items`, `stock_requests`, `stock_transfers`, `resource_categories`, `resources`, `resource_comments`, `facilities`, `assessments`, `assessment_types`, `partners`, mentorship-system tables, `users`, `programs`, `trainings`.

### 5.4 Audit fields
Heaviest in inventory/stock (`requested_by`, `approved_by`, `dispatched_by`, `received_by`, `initiated_by`, `changed_by`), assessments (`created_by`, `updated_by`, `approved_by`, `locked_by`, `feedback_given_by`, `trained_marked_by`), mentorship (`assessed_by`, `marked_by`, `completed_by`, `video_reviewed_by`, `mentor_approved_by`, `head_drmh_approved_by`), and `indicator_report_periods` (`submitted_by`, `validated_by`).

### 5.5 PII / sensitive data
`users` (email, id_number, phone), `facilities` (email, telephone, incharge contact), `training_participants` (email), `suppliers`/`partners` (email, phone).

### 5.6 Baseline row counts (local dev DB `mnch-feb`, captured 2026-08-06)

| Table | Count |
|---|---|
| users | 7,579 |
| trainings | 460 |
| mentorship_classes | 447 |
| class_modules | 562 |
| class_participants | 594 |
| mentee_module_progress | 1,121 |
| quiz_attempts | **0** ⚠️ |
| facilities | 10,705 |
| counties | 54 |
| assessments | 66 |
| facility_assessments | 3 ⚠️ |

`facility_assessments` has **no corresponding migration file** — confirmed 2026-08-06 to be dead legacy data (see §9.4, resolved as no-action-needed).

### 5.6a Migration backlog found and cleared, 2026-08-06
`php artisan migrate:status` against the real `mnch-feb` database revealed **21 pending migrations** dating back to 2026-07-17 that had never been applied there, despite existing in the codebase — meaning the live database schema was significantly behind what the migration files (and this document's §5.1 analysis, which was based on reading files, not live application state) assumed. Notable ones: the `assessment_types` repair pair (§5.1), `drop_dead_nbu_paediatric_columns_from_assessments_table` (drops 9 confirmed-dead columns), `backfill_standard_facility_assessment_type` (creates the first real `AssessmentType` row and backfills `assessment_type_id` everywhere), and `fix_human_resource_responses_cadre_id_foreign_key` (deletes a small number of confirmed-empty placeholder rows before adding a previously-missing FK constraint).

Applied after: a fresh `mysqldump` backup (`database/dbsql/pre-migrate-backup-<timestamp>.sql.gz`), independent verification of the one row-deleting migration's safety claim (queried the real data directly — confirmed exactly 6 rows matched its criteria, all genuinely zero-valued placeholders, before trusting the migration's own comment), and full before/after row counts on every affected table. Result: all 21 applied cleanly, zero unexpected data loss — `users`/`facilities`/`trainings`/`mentorship_classes`/`assessments` row counts unchanged, `human_resource_responses` dropped by exactly the verified 6, `assessment_types` gained its first real row, `assessments`/`assessment_sections` `assessment_type_id` fully backfilled (0 NULLs remaining).

Also discovered during this work: `report_templates` (plus its two pivot tables `report_template_indicators` and `facility_report_templates`) has the exact same problem as `monthly_reports` (§9.1a) — live in the database, referenced by models, zero migration coverage anywhere, not even recoverable from git history via the same search that found `monthly_reports`'. New guarded migrations were added for all three as part of fixing §9.1a (they're no-ops against the real DB, only matter for fresh installs / the test suite).

---

## 6. Report / Export Inventory

| Service | Produces | Source data | Format | Invoked by |
|---|---|---|---|---|
| `AssessmentPdfReportService` | Facility assessment executive report | `Assessment` + sections/responses/HR/commodities | PDF (`pdf.assessment-executive-report`) + HTML (`reports.assessment-html-report`) | `ViewAssessmentSummary`, `Api\ReportController::downloadPdf` |
| `AssessmentExportService` | Raw assessment data dump | Same as above | CSV | `ViewAssessmentSummary`, `ListAssessments` bulk actions |
| `TrainingReportService` | Participant/objective reports | `Training`, `TrainingParticipant`, `ParticipantObjectiveResult` | CSV/text | **No live caller found via grep — confirm before treating as dead** |
| `MonthlyReportService` | `MonthlyReport` + `IndicatorValue` record creation | Facility/template/period | N/A (data entry, not a file exporter) | `GenerateMonthlyReports` command, `MonthlyReportObserver` |
| `EmoncReportingService` | Per-class mentee progress report | `ClassParticipant`, `MenteeModuleProgress`, `ClassModuleActivityParticipant` | Array → Blade | `ClassReportController`, `MentorDashboard` |
| `TrainingAnalyticsService` | Insights/trends | Various | Array | `TrainingCoverageDashboard`, `TrainingInsightsWidget` |
| `MentorshipProgressService` | Headline stats/trends | `IndicatorReportPeriod`/`IndicatorValue` | Array | `MentorshipProgressDashboard` |

**Known parallel-version risk**: `AssessmentPdfReportService::prepareReportData()` deliberately returns both "old" key names (`infrastructure`, `skillsLab`, …) and "new" key names (`infrastructureDetails`, …) from one shared builder, because the PDF and HTML views evolved separately. **Any future change to this data builder must be verified against both `pdf/assessment-executive-report.blade.php` and `reports/assessment-html-report.blade.php`** — exactly the silent-calculation-change risk the audit process warns against.

`ClassReportController` uses one data-builder for both web and PDF (`isPdf` flag) — lower drift risk, same pattern done more safely.

Full controller/route inventory (`ClassReportController`, `Api\ReportController`, `Api\ClassReportApiController`) and the ~15 Filament resources with Download/Export/CSV/PDF actions are captured in the discovery transcript; omitted here for length but available on request.

---

## 7. Integration Catalogue

| Integration | Where | Status |
|---|---|---|
| DHIS2 | `Dhis2SyncService` — `Http::withBasicAuth()`, POSTs `dataValueSet` to `{base_url}/api/dataValueSets`, has dry-run + pre-flight blocker checks | Implemented, config-driven via `config('services.dhis2.*')` |
| QR code (api.qrserver.com) | Referenced only in planning docs (`EMONC-IMPLEMENTATION-GUIDE.md`, `PLATFORM-OVERVIEW.md`) | **Not actually implemented in code** — prior memory calling this "used" was inaccurate; correct going forward |
| Browsershot/Puppeteer | `ManageClassMentees.php` — bulk certificate PDF generation → zipped via `ZipArchive` | Implemented, JS-side Puppeteer dep, no PHP Browsershot package in composer.json |
| Leaflet + Turf.js | `AppServiceProvider::boot()` via `FilamentAsset`, CDN (unpkg.com) | Implemented, CDN dependency (fails air-gapped, per known issue) |
| Claude/Anthropic | `Api\ChatController::assistant()` — direct `Http` call to `api.anthropic.com/v1/messages`, model `claude-sonnet-4-20250514` | **Config gap**: `config/services.php` has no `anthropic` entry, so `config('services.anthropic.api_key')` always resolves null regardless of any `ANTHROPIC_API_KEY` in `.env` — feature is silently broken until that config array is added |
| `MenteeAiAdvisor` | Despite the name, this is a **rule-based heuristic** scorer (attrition/training-recency/scores) — does not call any LLM | Correcting prior assumption |
| Sanctum | `config/sanctum.php`, guard `web`, **no token expiration set** (`'expiration' => null`) | Implemented — flag `null` expiration for the Security Audit phase |
| Mail providers configured | postmark, resend, ses, slack (bot token/channel), deepseek — `deepseek` has config but **no code reference found in `app/`** | Partial — several configured services with no confirmed callers |

---

## 8. Background Jobs & Scheduler

| Type | Name | Behavior |
|---|---|---|
| Job | `ProcessResourceUpload` | **Stub — empty `handle()`**, `ShouldQueue` |
| Job | `SendAssessmentReportEmail` | Queued (3 tries/60s backoff); generates PDF via `AssessmentPdfReportService`, emails via `AssessmentReportMail`, tracks status on `AssessmentEmailJob` |
| Job | `UpdateResourceStats` | **Stub — empty `handle()`**, `ShouldQueue` |
| Command | `mentorships:auto-close` | Marks `facility_mentorship` trainings past `end_date` as completed |
| Command | `reports:generate-monthly` | Iterates active `ReportTemplate`s × assigned facilities → `MonthlyReportService::createMonthlyReport()` |
| Scheduler (`routes/console.php`) | `Schedule::command('mentorships:auto-close')->dailyAt('00:05')` | **Only one scheduled task.** `reports:generate-monthly` is **not scheduled** — currently manual-only |

**Notifications** (`app/Notifications/`, 10 files, all `ShouldQueue`) — all stock/inventory request lifecycle (approved/dispatched/received/rejected/overdue/very-overdue/status-update/new-request/bulk-result).

**Mail** (`app/Mail/` + `app/Mail/Indicators/`, 8 files) — **none implement `ShouldQueue`**, sent synchronously: `AccountVerificationMail`, `AssessmentReportMail`, `CoMentorInvitation`, `EmoncNotificationMail`, `MenteeEnrollmentInvitationMail`, `ReportRejectedMail`, `ReportSubmittedMail`, `ReportValidatedMail`.

No `app/Events/` or `app/Listeners/` directories exist — no event/listener architecture in use anywhere in this codebase.

---

## 9. Risk Register

These surfaced during read-only discovery. Items marked **FIXED** were addressed on 2026-08-06 after explicit user approval per-item — see each note for the commit and reasoning. Everything else is still logged only, untouched, per the audit's non-negotiable rule.

### 9.1 Facility-scoping bypass on Monthly Reports — **FIXED 2026-08-06**
`User::canAccessFacility(int $facilityId)` unconditionally `return true;` (`app/Models/User.php:304-307`) — the real check (`$this->isAboveSite() || $this->scopedFacilityIds()->contains(...)`) was commented out in the source. `EditMonthlyReport::beforeSave()` (`app/Filament/Resources/MonthlyReportResource/Pages/EditMonthlyReport.php:40-41` — not `mount()` as first reported) relies on it to `abort(403)`, and `MonthlyReportResource::getEloquentQuery()`'s facility filter plus `canViewAny()` were also disabled, so the whole resource had zero geographic scoping.
**Fix:** restored the real logic — `isAboveSite() || in_array($facilityId, $this->scopedFacilityIds(), true)`. The original commented-out code also had a latent bug (`->contains()` called on a plain PHP array, which `scopedFacilityIds()` actually returns, not a Collection — would have fatal-errored if ever uncommented as-is). Re-enabled `getEloquentQuery()`'s facility filter and removed the `canViewAny()` override (which bypassed the already-correct `MonthlyReportPolicy::viewAny()`). Regression coverage: `tests/Unit/UserCanAccessFacilityTest.php`, `tests/Feature/MonthlyReportResourceScopingTest.php` (tests the real Filament table via Livewire, confirms scoped users only see their own facility's reports). Safe to fix immediately rather than just document, because of §9.1a below — the feature was 100% unusable before this, so nothing could have depended on the bypass.

### 9.1a `monthly_reports` table did not exist in the database at all — **FIXED 2026-08-06**
`Schema::hasTable('monthly_reports')` returned **false** against the live `mnch-feb` DB — no migration anywhere created it. Yet `App\Models\MonthlyReport`, the full `MonthlyReportResource`, `MonthlyReportObserver`, and `reports:generate-monthly` all referenced this table — the feature was entirely non-functional.
**Fix:** git-archaeology recovered the exact original migration (`database/migrations/2025_07_21_185719_create_monthly_reports_table.php`), deleted twice in history (`ac19ce7`, `a9c7728`) as a side effect of large, unrelated batch commits ("Updated Indicators", "docs: add assessment creation design spec") — not a documented, deliberate product decision, confirmed by checking both commits' messages and diffs. Its content matched `MonthlyReport::$fillable` exactly, so restored verbatim. Its FK dependency, `report_templates`, turned out to have the identical problem — live in the database (confirmed via `SHOW CREATE TABLE`), referenced by `ReportTemplate`/`FacilityReportTemplate` models, but with no migration anywhere, not even in history. Added three new migrations (`create_report_templates_table`, `create_report_template_indicators_table`, `create_facility_report_templates_table`), each matching the real database's live schema exactly and guarded with `Schema::hasTable()` so they're a no-op against the real DB and only create the tables on a fresh install or the test suite. Applied to the real `mnch-feb` database after a fresh `mysqldump` backup and before/after row-count verification (see §5.6a). `report_templates` itself is still empty (0 rows) post-migration — the feature is schema-complete but needs at least one `ReportTemplate` record before anyone can create a `MonthlyReport` through the UI; that's a content/configuration gap for the team, not something to fabricate.

### 9.2 `RolePolicy` has un-replaced Shield stub tokens — **FIXED 2026-08-06**
`app/Policies/RolePolicy.php` lines 66–107 contained literal, unreplaced boilerplate tokens (`{{ ForceDelete }}`, `{{ RestoreAny }}`, etc.) as permission-name strings.
**Fix:** replaced with real, conventionally-named slugs (`force_delete_role`, `restore_role`, `restore_any_role`, `replicate_role`, `reorder_role`) matching Shield's own naming convention. Confirmed via the live `permissions` table that `role` only ever had the base `MANAGE` set seeded (view_any/view/create/update/delete_any/delete) — these four abilities were never granted to anyone, including `super_admin`, so this is a pure hygiene fix with **zero behavior change today** (still denies for everyone, because the permissions still don't exist in the `permissions` table) — it just makes the abilities grantable through the normal permission system the moment anyone decides they should be, instead of being permanently unreachable garbage strings. Regression coverage: `tests/Unit/RolePolicyTest.php`.

### 9.3 Orphaned `assessment_responses` migration — **FIXED 2026-08-06 (removed, with approval)**
`2025_11_29_120458_create_assessment_responses_table` was not marked superseded and could still create a stray table distinct from the live `assessment_question_responses`. Confirmed zero code references. User explicitly approved removal. File deleted.

### 9.4 `facility_assessments` table has no migration provenance — **investigated, no action needed**
3 rows, confirmed via the historical dump `database/dbsql/localhost_12_12_2025.sql` to be a legacy predecessor table (its own now-superseded migration was `2025_08_03_183149_create_facility_assessments_table`, batch 24) to today's `assessments` table. Zero live code references it anywhere (`grep` across `app/` and `database/` returns nothing outside old SQL dumps). Genuinely dead legacy data, not a functional risk — user chose to leave the table alone (it's live data, not a migration file, so removal would need a separate, explicit data-deletion decision).

### 9.5 `quiz_attempts` table is empty despite 1,121 `mentee_module_progress` rows — **Medium**
Either a data-integrity gap, a reporting/analytics blind spot, or an expected state worth confirming with the team (e.g. progress records predate the quiz system, or attempts are stored elsewhere). Needs product/domain confirmation, not a code fix — do not assume broken.

### 9.6 Live SMTP credential and API key readable in `.env` — **High (operational security)**
`.env` contains a duplicate `MAIL_MAILER` (first `log`, immediately overridden to `smtp`) with a live Gmail app-password, and a populated `DEEPSEEK_API_KEY`. This is standard for a `.env` file (should already be git-ignored — **verify `.gitignore` covers it and that it was never committed**), but flagging per the audit's Security and Privacy requirement to audit "database credentials, environment secrets." No action taken; recommend confirming `.env` has never been committed to git history as a first check.

### 9.7 Claude/Anthropic chat assistant is silently non-functional — **FIXED 2026-08-06**
`Api\ChatController::assistant()` calls `config('services.anthropic.api_key')`, but `config/services.php` had no `anthropic` array — always resolved null regardless of any env var set. The endpoint already had a graceful "not configured" fallback message, so this was never a crash, just always-disabled.
**Fix:** added an `anthropic` block to `config/services.php` reading `ANTHROPIC_API_KEY`, matching the existing `deepseek`/`dhis2` pattern. Purely additive — does nothing until someone sets the env var. Regression coverage: `tests/Unit/AnthropicServiceConfigTest.php`.

### 9.8 `CheckResourceAccess` middleware is dead code — **FIXED 2026-08-06 (removed, with approval)**
Empty pass-through `handle()`, not registered in `bootstrap/app.php` or any route file. User explicitly approved removal. File deleted.

### 9.9 Deployment/CI/rollback tooling is essentially absent — **High (process risk)**
No Dockerfile, CI workflow, or deployment scripts exist in the repo. **Correction (2026-08-06 follow-up):** `database/dbsql/` actually holds 7 ad-hoc dumps, not the single file first reported — `02-02-2026 backup.sql.gz`, `dbsql5-08-2025.gz`, `localhost_12_12_2025.sql`, `localhost.sql_6.gz`, `localhost.sql_8.gz_15_09_2025`, `mch-mentorship(3).sql`, `mnch.sql.gz`. They use inconsistent, informal naming with no clear versioning/rotation scheme, and it's not obvious which is most recent or authoritative without opening each one — this is manual accumulation, not a backup *process*. None have been verified to actually restore. No rollback runbooks, no staging environment reference beyond narrative mentions in `EMONC-IMPLEMENTATION-GUIDE.md`. This directly blocks the audit's Phase 1 requirement to "establish backup verification," "establish rollback procedures," and "establish staging environment" — **these three Phase 1 tasks cannot be marked complete from what exists today; they require new setup, which is itself a Phase-1-appropriate, non-destructive addition** worth prioritizing before any Phase 3+ change lands. A restore-verification script is being added in the Phase 2 plan (`docs/superpowers/plans/2026-08-06-phase2-testing-safety-net.md`) as a first, non-destructive step toward closing this gap.

### 9.10 Test suite: base `TestCase` doesn't use `RefreshDatabase`, but 61 test files exist — **resolved, see [[phase1-discovery-2026-08-06]]**
Corrected 2026-08-06: the suite actually runs clean (313 passing at the time, since grown). Per-file `use RefreshDatabase;` (57 of 61 files) is this codebase's working convention, not a defect — not "fixed," just not a real issue.

### 9.11 `admin/analytics/progressive-dashboard/{system-info,performance-metrics}` are 100% broken — **High** (found 2026-08-06 during route smoke testing)
`routes/web.php:238` gates this route group with `->middleware(['auth', 'admin'])`, but **no middleware alias named `admin` is registered anywhere in this application** (checked `bootstrap/app.php` and all service providers). Laravel's container throws `BindingResolutionException: Target class [admin] does not exist` trying to resolve it as a class, for every single request — these two routes have never worked, for anyone, ever (fails closed with a 500, not open — not an active vulnerability, just dead functionality). Notable because of what they'd expose once "fixed": `system-info` returns `PHP_VERSION`, Laravel version, cache driver, DB connection name, memory usage, and `disk_free_space('/')` as JSON; a sibling POST route `rebuild-cache` (same broken middleware) calls `Cache::flush()` unconditionally. **Do not simply delete the broken `'admin'` string** — that would make these routes reachable by anyone with only `auth` (any logged-in user, any role), turning a currently-inert bug into a real information-disclosure/DoS-via-cache-flush issue. Needs a deliberate decision on what access control was intended (a permission check? role check? `isAboveSite()`?) before touching it.

### 9.12 Several dashboards use MySQL-only SQL, untestable against SQLite — **Medium** (found 2026-08-06, widened same day)
`admin/coverage-dashboard`, `admin/coverage-overview`, `admin/training-dashboard`, plus the public `analytics/dashboard`, `training-dashboard/api/years`, and `dashboard/api/years` all run raw queries using `DATE_FORMAT()`, `YEAR()`, and/or `MONTH()` directly. A second, related pattern also found the same day: `resources`, `resources/search`, and `resources/browse` use `HAVING <subquery-alias> > 0` — MySQL permits `HAVING` to reference any `SELECT`-list alias, SQLite requires an actual aggregate expression there and errors with `"HAVING clause on a non-aggregate query"`. Both patterns confirmed working fine against the real MySQL database — this isn't an app bug, it's a real, structural **testing gap**: none of these ~9 routes (and likely more using the same query styles, not exhaustively searched) can be covered by this test suite as currently configured, only by manual/browser verification or by switching CI to a MySQL service container instead of SQLite. Excluded from both route smoke tests (`AdminRouteSmokeTest`, `PublicRouteSmokeTest`) with this reasoning inline; worth a deliberate decision later — rewrite the queries to be portable vs. accept MySQL-only CI.

### 9.13 `Filament\FilamentManager::getUserName()` crashes on a null `users.name` — **Medium** (found 2026-08-06)
`users.name` is nullable (added later via `2026_07_31_120000_add_name_and_nullable_id_number_to_users_table`, alongside the pre-existing `first_name`/`last_name`). Filament's own topbar/avatar component calls `getUserName()`, which throws a `TypeError` (`Return value must be of type string, null returned`) — not a graceful fallback — for any user with `name IS NULL`. Confirmed against the real database: **5 of 7,614 users currently have a null `name`** (low blast radius today, since most were apparently backfilled), but any newly created user that doesn't explicitly set `name` (relying on `first_name`/`last_name` alone, which several parts of the codebase treat as the canonical fields) would 500 on every single admin panel page. Also noted in passing: existing non-null values look backfill-generated and occasionally malformed (e.g. one row has `name = "Super Admin Admin"` from `first_name = "Super", last_name = "Admin"` — a duplicated-word artifact worth a data-quality look separately). Needs a decision: add a `name` accessor/mutator that falls back to `first_name . ' ' . last_name` when null, or backfill + enforce not-null going forward — not fixed here, since it's a real behavior decision, not a typo.

### 9.14 `indicators.target_value` had no migration — **FIXED 2026-08-06**
Same "live in the database, zero migration coverage" pattern as `monthly_reports`/`report_templates` (§9.1a) — found because `admin/indicators/progress-dashboard` 500'd in the SQLite test environment with `no such column: ind.target_value`, while the real MySQL database has always had it. Added a guarded migration (`2026_08_06_220000_add_target_value_to_indicators_table.php`) matching the live column exactly (`int unsigned`, nullable) — no-op against the real database, only matters for a fresh install or the test suite. **This is the third instance of this exact pattern found this session** — worth a dedicated audit of the full live schema against the full migration history at some point, rather than continuing to find these one 500 at a time.

### 9.15 `sitemap.xml` and `feed` (RSS) are completely broken — **High** (found 2026-08-06)
`routes/web.php:484-512` defines both routes rendering Blade views (`response()->view('sitemap', ...)`, and an RSS view for `feed`) that **do not exist anywhere in the codebase** (`find resources/views -iname "sitemap*" -o -iname "feed*"` returns nothing). This 500s in every environment, not just the test suite — it isn't a SQLite-portability issue like §9.12. Both routes are public, SEO/syndication-facing, and each independently references `route('sitemap')` from a `robots.txt` route (`routes/web.php:517`) that also links to it, so `robots.txt` itself would point search engines at a broken URL. The `sitemap` route wraps its response in `cache()->remember('sitemap', 3600, ...)`, which may be why this has gone unnoticed — a lucky cached hit from before the view was removed (or before this code was ever fully deployed) could mask the break for up to an hour at a time in a live environment. Not fixed here — writing a correct sitemap.xml/RSS 2.0 template is content/schema work, not a drive-by fix, but this is a real, currently-broken, publicly-reachable defect worth prioritizing.

---

## 10. Phase 1 Task Checklist — status against the audit's own list

| Task | Status |
|---|---|
| Document current architecture | ✅ Done (§1) |
| Module inventory | ✅ Done (§3) |
| Route inventory | ✅ Done (prior memory `routes_api.md`, cross-verified via report/integration agents — no drift found) |
| Permission inventory | ✅ Done (§4) |
| Database inventory | ✅ Done (§5) |
| Report inventory | ✅ Done (§6) |
| Map integrations | ✅ Done (§7) |
| Map background jobs | ✅ Done (§8) |
| Map business rules | ✅ Done (§2, plus existing `emonc_flow.md` memory) |
| Map clinical rules | ✅ Done (EmONC cert chain, quiz pass-mark 85%, video pass/fail — see `emonc_flow.md`) |
| Capture screenshots / user journeys | ⛔ Not done — needs browser-driven walkthrough, out of scope for this text-based discovery pass |
| Baseline performance | ⛔ Not done — no APM/profiling tool found; would need manual timing pass |
| Baseline data counts | ✅ Done (§5.6) |
| Backup verification | ⚠️ Gap found — only one manual dump exists, no verified restore process (§9.9) |
| Rollback procedures | ⚠️ Gap found — none exist (§9.9) |
| Staging environment | ⚠️ Gap found — none exist (§9.9) |
| Regression test baseline | ⚠️ Partial — tests exist but runnability unverified (§9.10) |

---

## Sources

Consolidated from: existing verified project memory (`domain_models.md`, `rbac_roles.md`, `filament_structure.md`, `routes_api.md`, `services.md`, `emonc_flow.md` — all cross-checked, corrections noted inline above where found stale), plus fresh read-only discovery across `composer.json`, `package.json`, `config/*`, `.env`, `database/migrations/*`, `app/Policies/*`, `app/Http/Middleware/*`, `app/Filament/**`, `app/Jobs/*`, `app/Console/Commands/*`, `routes/console.php`, `app/Notifications/*`, `app/Mail/*`, `app/Services/*`, `app/Http/Controllers/**`, and a live `php artisan tinker` row-count query against the local `mnch-feb` database.
