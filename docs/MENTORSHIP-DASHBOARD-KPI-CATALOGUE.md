# Mentorship Dashboard — KPI Catalogue

**Status:** Discovery/inventory deliverable, Phase 4 (Dashboard Design) of the Production-Safe System Audit process.
**Scope:** Cross-referenced against the original brief's 15-KPI "Mentorship Dashboard" wishlist, against what's actually computed today across the mentorship-related dashboards/services.
**Generated:** 2026-08-07

---

## Sources reviewed

| File | Audience | Role |
|---|---|---|
| `app/Filament/Pages/MentorDashboard.php` | Individual mentor (own trainings) | Personal KPIs, priority queue, mentorship breakdown |
| `app/Services/MentorPriorityQueueResolver.php` | Feeds MentorDashboard | 5-tier ranked action list (video review → approve → follow up → support → review class) |
| `app/Services/MentorAnalyticsDashboardService.php` | Coordinators/admins | Cross-mentor analytics, leaderboards, matrix |
| `app/Services/CoordinatorExceptionResolver.php` | Feeds MentorAnalyticsDashboardService | 3-tier exception list (facility → inactive mentor → zero-CPD mentor) |
| `app/Services/EmoncDashboardService.php` | Same scoping as MentorDashboard, EmONC only | EmONC-specific completion matrix, certification tracking |
| `app/Filament/Pages/Indicators/MentorshipProgressDashboard.php` + `app/Services/MentorshipProgressService.php` | Facility-scoped users | **Not actually about mentorship classes** — operates on the separate DHIS2-style indicator-reporting domain (`indicator_report_periods`/`indicator_values`). One crude cross-reference (`mentorship_count`). |
| `app/Services/CpdPointsService.php` | Shared calculator | CPD points/levels for mentees and mentors |

`app/Filament/Pages/CoverageDashboard.php` exists and looked purpose-built for coverage tracking but was outside this pass's scope — worth reading before building a new coverage widget, to avoid duplicating it.

---

## Cross-reference: the 15 target KPIs

| # | KPI | Status | Detail |
|---|---|---|---|
| 1 | Mentorship coverage | Partially covered | `MentorshipProgressService` has a crude `Training` count per facility, not a true coverage %. `CoverageDashboard.php` may already do this better — check before building. |
| 2 | Mentor activity | Partially covered | Inactivity flags exist (≥14 days, both resolvers) but no aggregate activity-rate/trend KPI tile. |
| 3 | Mentee enrollment | **Covered** | `MentorDashboard`, `MentorAnalyticsDashboardService`, `EmoncDashboardService` all compute this. |
| 4 | Attendance | **Covered** | `ClassAttendance`-based rate + low-attendance flags in both resolvers. |
| 5 | Module completion | **Covered** | Extensively — multiple KPIs across all 3 mentorship-class services. |
| 6 | Assessment scores | **Available, not displayed** | `quiz_attempts.score`, `mentee_module_progress.assessment_score` exist and are populated (used by `QuizAttemptService`, `ReviewModuleMentee`), just never shown on a dashboard. |
| 7 | Competency progress | **Not available** | No `Competency` model or taxonomy exists anywhere. Would need new data modeling, not just a new widget. |
| 8 | Practical skills | **Available, not displayed** | `rubric_assessments.score`/`passed` exist and are actively used for data entry/review (`RubricAssessmentResource`), never surfaced on a dashboard. |
| 9 | Facility improvement | **Covered** | `MentorshipProgressService::getFacilityTrends()` — solid, working — but sourced from the DHIS2 indicator subsystem, not cross-tabbed against mentorship activity. |
| 10 | Learning gaps | Partially covered | Stalled/struggling-mentee proxies exist; no module/topic-level gap analysis using scores. |
| 11 | Overdue activities | Partially covered | Fixed 14-day inactivity window ≠ true due-date/deadline overdue tracking. `Training.end_date` exists but isn't used this way. |
| 12 | Certificates | **Covered** | `ClassParticipant.head_drmh_approved_at`/`mentor_approved_at` → `isCertified()`. No separate Certificate model needed. |
| 13 | Follow-up sessions | **Not available** | Only free-text recommendations exist (`mentor_recommendation`). No scheduled-session model. |
| 14 | Mentor-to-mentee ratio | **Trivially derivable, not computed** | `total_mentors` and `total_mentees` are both already computed separately in `MentorAnalyticsDashboardService` — just never divided. Zero new queries needed. |
| 15 | Inactive participants | Partially covered | Per-item flags exist for both mentors and mentees; no aggregate "N inactive (X%)" stat card. `ClassParticipant.status='dropped'` exists and is unused as a metric. |

## Correctness issue found during this pass (not a KPI gap — a bug)

`MentorAnalyticsDashboardService::build()` (lines 69-83) re-derives mentor CPD **inline**, counting completed `ClassModule`s across classes of **any** status. `CpdPointsService::forMentor()`/`batchForMentors()` — the version used everywhere else, including mentors' own certificates — requires the class itself to have `status='completed'`. **These two code paths currently disagree** on a mentor's CPD total depending on which dashboard you're looking at. Not part of the original KPI wishlist, but worth fixing alongside any dashboard work in this area since it directly affects data trustworthiness of a number already on screen today.

---

## Recommended next step

Six genuine gaps worth closing, all buildable from data that already exists (zero new migrations for 5 of 6; #15's aggregate rollup also needs none):
1. **Assessment scores** (#6) — avg score / pass rate widget.
2. **Practical skills** (#8) — avg rubric score / pass rate widget.
3. **Mentor-to-mentee ratio** (#14) — one-line division of numbers already computed.
4. **Inactive participants aggregate** (#15) — roll the existing per-item flags into headline stat cards.
5. **CPD calculation bug** — make `MentorAnalyticsDashboardService` use `CpdPointsService::forMentor()` for consistency.

Deferred (need more than a widget): coverage % (#1, check `CoverageDashboard.php` first), competency progress (#7, needs new data model), follow-up sessions (#13, needs new data model), learning-gaps heatmap (#10, needs #6+#8 built first) and overdue-activities due-date tracking (#11, needs scheduling-field investigation).
