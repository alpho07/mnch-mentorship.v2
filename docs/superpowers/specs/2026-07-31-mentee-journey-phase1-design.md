# Mentee Journey Phase 1 — Guided Dashboard, Feedback Surfacing & Offline Resilience

> Date: 2026-07-31
> Project: MNCH Mentorship Platform
> Scope: Phase 1 of the design overhaul described in `mnch_mentorship_agentic_development_guide.txt` — the mentee-facing experience only.

---

## 1. Goal

Rebuild the mentee dashboard around the guide's core principle (§7.1, §9, §10): **never leave the user to determine the next step alone.** Today `MenteeDashboard` (`app/Filament/Pages/MenteeDashboard.php` + `resources/views/filament/pages/mentee-dashboard.blade.php`) is a stats-dense analytics page — a gradient profile card, 8 stat tiles, a progress donut, a class accordion, and four sidebar cards. There is no single dominant "here is the one thing to do" action, and the one piece of next-step logic that does exist (`next_module` at `MenteeDashboard.php:228-236`) only runs for EmONC.

This phase bundles four related gaps identified during the audit of the current implementation:

1. No cross-program, priority-ranked "next best action" driving a hero card.
2. Mentor feedback surfacing is incomplete — a failed video review's `video_review_notes` never reaches the mentee at all; only the free-text `mentor_recommendation` field is shown.
3. Empty states are inconsistent — some already meet the guide's standard (heading + explanation + action), others are bare.
4. No offline/retry resilience for the highest-risk mentee action: hands-on video upload on rural connectivity.

---

## 2. Context — what already exists

- `MenteeDashboard.php` loads all of a mentee's `ClassParticipant` records with modules, sessions, and progress, and computes global stats (completion %, attendance %, learning velocity, streak).
- `ClassModule` → `MenteeModuleProgress` carries `status`, `pre_test_attempt_id`, `post_test_attempt_id`, `hands_on_video_url`, `video_review_status` (`passed|failed|null`), `video_review_notes`, `mentor_recommendation`.
- EmONC vs. non-EmONC (Newborn, Infant/Child) is detected via `isEmonc()` (name-string match on "maternal" + "emonc") in three separate places: `MenteeDashboard.php`, `MentorshipTrainingResource.php`, and the EmONC flow services. This spec does not change that detection mechanism, only reuses it.
- Non-EmONC programs are session-based (`ClassModule->sessions`, attendance confirmation) rather than activity-based; "in progress" for those today just means the module has started and attendance isn't yet confirmed for the open session.
- Mentee-facing empty states live in `class-progress.blade.php` (bare "No modules started yet") and `module-detail.blade.php` (mixed — some already have explanations, e.g. the "not yet enrolled in activities" amber notice).
- No upload-retry or draft-preservation logic exists anywhere in `module-detail.blade.php`'s video upload form today.

---

## 3. Next-Best-Action logic (mentee only)

A new method, `MenteeNextActionResolver` (service class, `app/Services/MenteeNextActionResolver.php`), computes one recommended action across **all** of a mentee's active enrollments, regardless of program. It replaces the EmONC-only `next_module` computation in `MenteeDashboard.php`.

### Priority ladder (highest wins; first match across all enrollments/modules)

1. **Video review failed** (`video_review_status === 'failed'`) → action: "Review Mentor Feedback", links to the module detail page, shows `video_review_notes`.
2. **Attendance link open** (`attendance_link_active` true, module `status = in_progress`, mentee not yet confirmed) → action: "Confirm Attendance", links to the public/token attendance flow already used elsewhere.
3. **In-progress module**, ranked by highest completion fraction (activities done / total for EmONC; sessions attended / total for non-EmONC) → action: "Continue Learning".
4. **Post-test available** (video submitted for EmONC, or all sessions attended for non-EmONC, and post-test not yet attempted) → action: "Take Assessment".
5. **Pre-test not yet taken** on an active module → action: "Start Module".
6. **Nothing urgent** → action: "You're on track", with the soonest upcoming session date if one exists, else a completion/certificate nudge if every enrollment is done.

Ties within the same tier are broken by soonest deadline/session date, then by most-recently-active enrollment.

### Data contract

`MenteeNextActionResolver::resolve(User $mentee): array` returns:
```php
[
  'tier' => int,               // 1-6, which ladder rung matched
  'label' => string,           // button text, e.g. "Continue Learning"
  'headline' => string,        // "Continue your current module"
  'subtext' => string,         // "Postpartum Haemorrhage Management — 65% completed"
  'url' => string,
  'meta' => array,             // tier-specific extra data (e.g. video_review_notes, session date)
]
```
This keeps the resolver reusable later for the mentor/coordinator dashboards (Phase 0.1 in the overall roadmap) without redesigning it then.

---

## 4. Dashboard layout

`MenteeDashboard.php` calls the resolver once in `loadDashboard()` and exposes `$this->nextAction`. The Blade view is restructured:

1. **Pending-enrollment banner** (existing, session-based) — stays at the very top; it's a blocking pre-condition, not a progress state.
2. **Hero card** (new) — one card, one button, driven entirely by `$nextAction`. Visually distinct (larger, colored per urgency tier — red/amber for tiers 1-2, blue for 3-5, green for tier 6).
3. **Secondary strip** (new, compact, non-clickable info only) — next upcoming session date, count of any other overdue items not shown in the hero, mentor-feedback badge count.
4. **"My Progress" section** — existing stat tiles + donut + activity feed, wrapped in a collapsed-by-default `x-collapse` panel (Alpine, matching the pattern already used for the activity feed's "view more").
5. **"My Classes" section** — existing per-class accordion, also collapsed by default.

No existing computed data is discarded — `globalStats`, `enrollments`, `recommendations`, `activityFeed` all stay; they're just demoted visually and wrapped in toggles.

---

## 5. Mentor-feedback surfacing fix

Add `video_review_notes` (whenever `video_review_status = 'failed'`) into the same feedback collection that already surfaces `mentor_recommendation`, so both appear in the "Mentor Feedback" sidebar card. A failed video review additionally drives ladder tier 1, so it's never silently missed — it shows in the hero, not just buried in a sidebar list.

No new migration needed — both fields already exist on `mentee_module_progress`.

---

## 6. Empty-state standardization

Apply the guide's format (heading, explanation, primary action where one exists) to the two non-compliant spots found:

| File | Current | New |
|---|---|---|
| `class-progress.blade.php` (~line 533) | "No modules started yet" (bare) | "No modules started yet" + "Your mentor hasn't started any modules for this class yet. You'll be notified when the first one opens." (no action button — this is a mentor-side gate, not something the mentee can act on) |
| `module-detail.blade.php` (~line 347) | "Module content not yet set up" (bare) | Same heading + "Contact your mentor if you believe this is a mistake." + a "Back to Class" link |

The dashboard's existing "not enrolled in any classes" empty state and the module-detail "not yet enrolled in activities" notice already meet the standard and are left unchanged.

---

## 7. Offline / upload resilience

Scoped to the single highest-risk interaction: hands-on video upload in `module-detail.blade.php`.

- Add a visible upload-progress indicator (percentage) using the existing form submission (Livewire/Alpine `x-data` progress binding on the file input's XHR, no new backend endpoint needed since the upload already posts to `MenteeClassProgressController@uploadHandsOnVideo`).
- On failure (network drop mid-upload), keep the selected file in the browser (don't clear the input) and show a "Retry Upload" button that resubmits without requiring the mentee to re-pick the file.
- Distinguish, in the UI only, "Uploading…" vs "Submitted" — no new "draft" database state is introduced; if the request never completes, nothing is saved server-side and the mentee sees a clear "not yet submitted, please retry" message rather than a silent failure.
- Quiz submission gets a simple retry-on-failure toast (small payload, no file) — not full draft persistence; out of scope beyond that.

---

## 8. Business rules / edge cases

- A mentee with zero enrollments sees no hero card — the existing "not enrolled" empty state takes over the whole content area, unchanged.
- A mentee whose every enrollment is fully complete and certified sees tier 6 with a certificate-download nudge instead of a session date.
- If two modules across different enrollments tie on urgency tier and completion %, the one with the more recent `updated_at` on its `MenteeModuleProgress` row wins (proxy for "most recently active").
- Non-EmONC "in progress" completion fraction uses sessions attended / total sessions for that module, since those programs have no activity-completion matrix.
- The resolver must not throw for a mentee with malformed/partial data (e.g. a `ClassModule` with zero sessions and zero activities) — falls through to tier 6 rather than dividing by zero.

---

## 9. Testing

- Feature test: mentee with a failed video review sees it as the hero action (tier 1), regardless of other in-progress modules.
- Feature test: mentee with an open, unconfirmed attendance link sees tier 2 over a lower-urgency in-progress module.
- Feature test: EmONC mentee and non-EmONC mentee both get a valid tier 3 "Continue Learning" hero when only an in-progress module exists — proves the ladder isn't EmONC-only anymore.
- Feature test: mentee with no enrollments renders the existing empty state, no hero card, no error.
- Feature test: mentee with all enrollments complete/certified sees tier 6 with certificate nudge.
- Manual/browser check (Playwright): video upload retry-after-failure keeps the file selected and successfully resubmits.

---

## 10. Acceptance criteria

- [ ] `MenteeNextActionResolver` returns a correct tier for all six ladder cases, covering both EmONC and non-EmONC programs, verified by feature tests.
- [ ] Mentee dashboard hero card renders the resolver's output; existing stats/donut/feed/class-accordion content is preserved but collapsed by default below the hero.
- [ ] A failed video review's `video_review_notes` appears both in the hero (tier 1) and in the Mentor Feedback sidebar card.
- [ ] The two identified bare empty states now show heading + explanation (+ action where applicable).
- [ ] Hands-on video upload shows progress, survives a mid-upload network failure without losing the selected file, and offers a one-click retry.
- [ ] No regressions to existing dashboard data (`globalStats`, `enrollments`, `recommendations`, `activityFeed` all still computed and displayed, just relocated).

---

## 11. Recommended implementation order

1. `MenteeNextActionResolver` service + feature tests (no UI change yet — safe to land first).
2. Wire the resolver into `MenteeDashboard.php`, add `$nextAction` property.
3. Hero card + secondary strip in the Blade view; wrap existing sections in collapsed panels.
4. Mentor-feedback surfacing fix (video_review_notes into the feedback list).
5. Empty-state copy updates (two files, low risk).
6. Video upload progress/retry (isolated to `module-detail.blade.php` + its JS).
