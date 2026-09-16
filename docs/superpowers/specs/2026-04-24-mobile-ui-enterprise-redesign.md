# Mobile App Enterprise Healthcare UI Redesign

**Date:** 2026-04-24
**Branch:** feature/scope-redesign
**Scope:** `public/m-assessment-app/src/`

## Summary

Five targeted improvements to the MNCH mobile app:

1. Replace broken emoji section icons with reliable SVG icons
2. Swap dark gradient headers for Teal Wellness healthcare palette
3. Fix assessment header spacing (gap + reduced top padding)
4. Show Reports-first tab in mentorship detail for mentor/admin roles
5. Update design tokens (`T`) to Teal Wellness system-wide

---

## 1. Design Tokens — Teal Wellness Palette

**File:** `src/constants.js` — update the `T` object.

| Token | Old value | New value |
|---|---|---|
| `bg` | `#F4F6FB` | `#F0F9FA` |
| `card` | `#FFFFFF` | `#FFFFFF` |
| `primary` | `#6C5CE7` | `#0097A7` |
| `primaryLight` | `#A29BFE` | `#26C6DA` |
| `primaryDark` | `#4A3DC7` | `#00565A` |
| `primaryGhost` | `rgba(108,92,231,0.08)` | `rgba(0,151,167,0.08)` |
| `primaryGlow` | `rgba(108,92,231,0.18)` | `rgba(0,151,167,0.18)` |
| `text` | `#1A1A2E` | `#1A3A3A` |
| `textMid` | `#3D3D5C` | `#2A4A4A` |
| `textSub` | `#6B7194` | `#4A8080` |
| `textMuted` | `#A0A3BD` | `#8BC8C8` |
| `border` | `#E2E4F0` | `#B2EBF2` |
| `borderLight` | `#F0F1F8` | `#E0F2F1` |
| `gradientPrimary` | purple-indigo | `linear-gradient(135deg, #0097A7 0%, #26C6DA 100%)` |
| `gradientHero` | dark green | `linear-gradient(160deg, #00565A 0%, #0097A7 55%, #26C6DA 100%)` |
| `gradientDark` | near-black green | `linear-gradient(160deg, #00565A 0%, #0097A7 55%, #26C6DA 100%)` |
| `shadow` | purple-tinted | `0 2px 16px rgba(0,151,167,0.06)` |
| `shadowMd` | purple-tinted | `0 8px 32px rgba(0,151,167,0.10)` |
| `shadowLg` | purple-tinted | `0 12px 48px rgba(0,151,167,0.14)` |
| `shadowCard` | purple-tinted | `0 1px 3px rgba(0,0,0,0.04), 0 4px 16px rgba(0,151,167,0.06)` |

Keep `accent`, `success`, `gradientSuccess`, `gradientSky`, `gradientWarm` unchanged.

---

## 2. SVG Section Icons

**File:** `src/constants.js` — replace emoji strings in `SECTION_META` with SVG component functions.

Change `SECTION_META` from `{ icon: "🏗️" }` strings to `{ iconSvg: (color, size) => <svg...> }` functions, one per section. Each icon is colour-coded to the section's existing gradient colour.

| Section | SVG icon | Colour |
|---|---|---|
| `infrastructure` | Building/lock outline | `#8B5CF6` |
| `skills_lab` | Grid/lab-bench outline | `#10B981` |
| `human_resources` | People/group outline | `#F59E0B` |
| `health_products` | Heart-medical outline | `#EF4444` |
| `information_systems` | Monitor outline | `#06B6D4` |
| `quality_of_care` | Heart outline | `#EC4899` |

**Render helper** — add `renderSectionIcon(code, size = 14)` exported from `constants.js`. Returns a `<svg>` element. Falls back to a generic clipboard SVG for unknown codes.

**Usage sites to update:**
- `screen-assessments-list.jsx:162` — `{s.icon}` → `{renderSectionIcon(s.code, 11)}`
- `screen-assessment-detail.jsx:154` — `{s.icon ?? "📋"}` → `{renderSectionIcon(s.code, 18)}`
- `screen-assessment-detail.jsx:349` — `{s.icon ?? "📋"}` → `{renderSectionIcon(s.code, 20)}`

---

## 3. Assessment Header Spacing

**File:** `screen-assessments-list.jsx` and `screen-assessment-detail.jsx`

### Assessments List header (`screen-assessments-list.jsx`)
- Wrap the entire screen in a container that adds `paddingTop: 6` between the phone status bar and the gradient card.
- Reduce top padding inside gradient from `52px` to `28px`.
- Add `borderRadius: "0 0 24px 24px"` and horizontal margin `0 6px` so card floats.
- Background of the outer container: `T.bg` (shows the 6px gap).

### Assessment Detail header (`screen-assessment-detail.jsx`)
- Same treatment: 6px gap above, reduce `padding: "20px 20px 24px"` top to `padding: "14px 20px 24px"` (the back button already provides visual spacing).

---

## 4. Mentorship Detail — Reports-First Tab for Mentors

**File:** `screen-mentorship-detail.jsx`

Add a `user` prop to `MentorshipDetailScreen`. Derive `isMentee` from `user.roles` using `MENTEE_ROLES` from `constants.js`.

Add a tab bar to the mentorship detail with three tabs:
- `reports` (label: "Reports") — **default for mentors/admins**
- `classes` (label: "Classes") — existing classes list view
- `info` (label: "Info") — existing info card + description

**Reports tab content** (new, visible for non-mentee default):
- Stat row: Mentees count · Classes count · Overall progress %
- Overall completion progress bar (derived from `classes` array `progress_percentage` avg)
- Attendance rate sparkline (bar chart from session data if available, else placeholder)
- "View Classes" shortcut button linking to the Classes tab

**Classes tab content**: existing classes list (moved from inline to tab)

**Info tab content**: existing `InfoRow` info card + description block

For mentees (`isMentee === true`): show Classes tab first (they manage their own class flow from here when navigating in from a deep link).

`MentorshipDetailScreen` must receive `user` prop — update the call site in `App.jsx`.

---

## 5. Mentorship List Header

**File:** `screen-mentorships-list.jsx`

Update the hardcoded gradient `"linear-gradient(160deg, #1E1B4B 0%, #3730A3 55%, #818CF8 100%)"` to use `T.gradientHero` (now Teal Wellness).

---

## Out of Scope

- No changes to API contracts, offline layer, or sync queue
- No changes to assessment form, report, or HR/HP screens
- No changes to Filament admin panel
- No changes to backend

---

## Files Changed

| File | Change |
|---|---|
| `src/constants.js` | Token update + `SECTION_META` SVG icons + `renderSectionIcon()` helper |
| `src/screens/screen-assessments-list.jsx` | Header spacing + SVG icons |
| `src/screens/screen-assessment-detail.jsx` | Header spacing + SVG icons |
| `src/screens/screen-mentorships-list.jsx` | Gradient token update |
| `src/screens/screen-mentorship-detail.jsx` | Reports/Classes/Info tab bar |
| `src/App.jsx` | Pass `user` prop to `MentorshipDetailScreen` |
