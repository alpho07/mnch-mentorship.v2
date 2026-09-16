# Mentor & Mentee Dashboards — Design Spec

**Date:** 2026-05-27
**Status:** Approved

---

## Goal

Give mentor and mentee users role-specific screens that surface the information most relevant to them: mentors see their mentorships as filterable rich cards and can drill into a per-mentorship overview showing mentees grouped by class; mentees see all their enrolled classes with inline module progress.

---

## Scope

Three screens are created or redesigned. All other screens (`screen-mentorship-detail.jsx`, `screen-mentorship-form.jsx`, `screen-mentee-manager.jsx`, `screen-module-detail.jsx`) are untouched.

| File | Change | Visible to |
|---|---|---|
| `src/screens/screen-mentorships-list.jsx` | Redesign — filterable rich cards | Mentor roles |
| `src/screens/screen-mentorship-overview.jsx` | New screen | Mentor roles |
| `src/screens/screen-my-classes.jsx` | New screen | Mentee role |
| `src/App.jsx` | Add two new screen routes | All |

---

## Design Tokens

All colours, radii, and shadows use the existing `T` object from `src/constants.js` (Indigo Sapphire palette). No new tokens needed.

---

## Screen 1 — Mentor Mentorship List (`screen-mentorships-list.jsx`, redesigned)

### Layout

**Hero panel** (`T.gradientHero`, `borderRadius: "0 0 28px 28px"`, `margin: "0 6px"`):
- Label: "MENTORSHIP" (caps, muted)
- Heading: "My Mentorships" (28px, 800 weight)
- Filter pill row beneath heading: `All (N) · Active · Completed · Draft`
  - Active pill: `T.gradientPrimary` fill, white text
  - Inactive pill: `rgba(255,255,255,0.12)` background, white text
- Two decorative indigo radial-gradient orbs (consistent with other heroes)

**Card list** (scrollable, `padding: "12px 16px"`, `gap: 12px`):

Each mentorship renders as a white card (`T.card`, `borderRadius: T.radius`, `boxShadow: T.shadowCard`):
- **Title** (14px, 700)
- **Subtitle:** facility name · date range (10px, `T.textMuted`)
- **Status badge:** pill shape, colour-coded (Active = `T.primaryGhost`/`T.primary`; Completed = `T.successGhost`/`T.success`; Draft = muted)
- **Stat row:** three inline figures — Mentees · Classes · Progress %
- **Progress bar:** `T.gradientPrimary` fill, `T.borderLight` track, `borderRadius: 6px`, `height: 5px`
- Card tap → navigates to `screen-mentorship-overview` with the mentorship id

**FAB (+):** fixed bottom-right, 52px circle, `T.gradientPrimary`, indigo glow shadow — navigates to `screen-mentorship-form` (create mode).

### Filtering

Client-side filter on the loaded mentorship list. Pill selection sets a `filter` state (`"all" | "active" | "completed" | "draft"`). Filtered list derived with `useMemo`.

### Data

```js
api.mentorships.list()   // GET /mentorships — returns array of mentorship objects
```

Mentorship object fields used: `id`, `title`, `status`, `facility`, `start_date`, `end_date`, `mentor_name`, `class_count`, `max_participants`. Progress percentage: use `progress_percentage` if present in the API response; otherwise compute as `Math.round(classes.reduce((s, c) => s + (c.progress_percentage ?? 0), 0) / classes.length)` — requires fetching classes first, so show a 0% bar as placeholder until loaded.

### Empty state

If no mentorships match the active filter: centred indigo icon + "No [filter] mentorships" label + "Create one" link.

---

## Screen 2 — Mentorship Overview (`screen-mentorship-overview.jsx`, new)

This screen sits between the mentorship list and the existing detail screen. It is the first thing a mentor sees after tapping a mentorship card.

### Layout

**Hero panel** (`T.gradientHero`, `borderRadius: "0 0 28px 28px"`, `margin: "0 6px"`):
- Back button (light variant — white text, `rgba(255,255,255,0.12)` background)
- Label: "MENTORSHIP" (caps, muted)
- Heading: mentorship title (up to 2 lines, 18px, 800)
- Subtitle: `start_date – end_date · facility` (11px, muted)
- **Stat pills row** (glassmorphism — `rgba(255,255,255,0.08)` background, `backdrop-filter: blur(8px)`):
  - Mentees count
  - Classes count
  - Progress % (coloured green when ≥ 60%)
  - Status badge

**Action strip** (white bar beneath hero, `borderBottom: "0.5px solid rgba(0,0,0,0.06)"`):
- **"View Detail & Reports →"** primary button (`T.gradientPrimary`) — navigates to existing `screen-mentorship-detail` with the mentorship id
- **"Edit"** ghost button (`T.primaryGhost`, `T.primary` text) — navigates to `screen-mentorship-form` in edit mode

**Mentees by Class section** (`padding: "12px 16px"`):

Section label: "MENTEES BY CLASS" (caps, 11px, `T.textMuted`)

Each class renders as a white card (`borderRadius: T.radiusSm`, `T.shadowCard`):

- **Header row** (always visible, `padding: "10px 12px"`):
  - Class name (12px, 700)
  - Mentee count + progress % (10px, `T.textMuted`)
  - Status badge (pill)
  - Expand/collapse chevron (right side)
- **Expanded content** (shown when class is tapped to expand):
  - Up to 3 mentee rows:
    - Avatar circle (initials, gradient background derived from name hash)
    - Name (11px, 600) + cadre (9px, `T.textMuted`)
    - Modules done label (e.g. "3/4 modules", 9px)
    - Mini progress bar (50px wide, 4px tall)
  - If class has > 3 mentees: `"+N more →"` link row (`T.primary`) — navigates to `screen-mentee-manager` for that class
- **Default state:** first class expanded, rest collapsed

### Data

```js
api.mentorships.find(id)           // GET /mentorships/:id — hero data
api.mentorships.classes(id)        // GET /mentorships/:id/classes — class list
api.classes.mentees(classId)       // GET /classes/:classId/mentees — lazy per class on expand
```

Mentees loaded lazily: when a class is expanded for the first time, fetch its mentees and cache in local state. Show a skeleton row while loading.

### Avatar colour

Derive a stable gradient from the mentee's name: `charCodeAt(0) % gradients.length` where `gradients` is a fixed array of 6 indigo/green/amber gradient pairs. Fallback to `T.gradientPrimary`.

---

## Screen 3 — Mentee My Classes (`screen-my-classes.jsx`, new)

### Layout

**Hero panel** (`T.gradientHero`, `borderRadius: "0 0 28px 28px"`, `margin: "0 6px"`):
- Label: "MY CLASSES" (caps, muted)
- Heading: mentee's display name (from `user` prop)
- Stat pills: Classes enrolled · Modules total · Overall % done
- Two decorative indigo radial-gradient orbs

**Class card list** (scrollable, `padding: "12px 16px"`, `gap: 12px`):

Each enrolled class renders as a white card:
- **Card header:**
  - Class name (14px, 700)
  - Facility · "Mentor: [name]" (10px, `T.textMuted`)
  - Progress bar + "X/Y modules" label (right-aligned, 10px, `T.textMuted`)
- **Inline module list** (always visible — no tap needed):
  Each module row:
  - **Icon container** (28px × 28px, `borderRadius: 8px`):
    - Completed: `T.gradientSuccess` background, white ✓
    - In progress: `T.gradientPrimary` background, white →
    - Not started: `#E0E0E8` background, no icon
  - **Module name** (11px, 700 — `T.primary` if in-progress, `T.text` if done, `T.textMuted` if pending)
  - **Right label:** session attendance or status ("3 sessions · Attended all" / "1 of 3 sessions" / "Not started")
  - Entire row is tappable → navigates to `screen-module-detail` with that module's id

**Empty state:** if no classes enrolled: indigo icon + "No classes yet" + "Ask your mentor for an enrollment link."

### Data

```js
api.me.classes()                    // GET /me/classes — list of classes with nested modules
api.me.classDetail(classId)         // GET /me/classes/:classId — used if modules not nested
```

The `/me/classes` response is expected to return an array of class objects, each with a `modules` array. If modules are not nested, fetch each class's modules via `api.me.classDetail(classId)` after the initial list loads.

Overall % done in hero: `completedModules / totalModules * 100` across all classes.

### Offline

Classes and modules are already cached in the offline store (`IndexedDB`). If the API call fails and no network, fall back to cached data and show a "Viewing offline data" banner (`T.gradientWarm` background).

---

## Navigation Wiring (`App.jsx`)

Two new entries in the screen router:

```js
case "mentorshipOverview":
    return <MentorshipOverviewScreen
        mentorshipId={screenParams.id}
        onBack={() => navigate("mentorships")}
        onViewDetail={(id) => navigate("mentorshipDetail", { id })}
        onEdit={(id) => navigate("mentorshipForm", { id })}
        onViewMentees={(classId) => navigate("menteeManager", { classId })}
    />;
// Note: verify exact screen key names ("mentorshipDetail", "mentorshipForm", "menteeManager")
// against App.jsx's existing case labels before implementing.

case "myClasses":
    return <MyClassesScreen
        user={user}
        onModuleDetail={(moduleId) => navigate("module-detail", { moduleId })}
    />;
```

The "My Classes" bottom nav tab key (`"myClasses"`) already exists in `computeTabs` for mentee roles. Wire it to navigate to screen key `"myClasses"` — matching the tab key convention used in `App.jsx`.

The mentorship list card `onPress` navigates to `"mentorship-overview"` instead of `"mentorship-detail"`.

---

## What Does NOT Change

- `screen-mentorship-detail.jsx` — untouched (accessed via "View Detail & Reports" button)
- `screen-mentorship-form.jsx` — untouched (accessed via FAB and Edit button)
- `screen-mentee-manager.jsx` — untouched (accessed via "+N more →" link)
- `screen-module-detail.jsx` — untouched (accessed via module row tap)
- `constants.js`, `shared-components.jsx`, `index.css` — untouched
- All logic, offline store, sync queue — untouched

---

## Success Criteria

- Mentor taps "Mentorship" tab → sees filterable rich mentorship cards
- Mentor taps a card → sees mentorship overview with mentees grouped by class
- Mentor taps "View Detail & Reports" → arrives at existing detail screen
- Mentor taps FAB → arrives at create mentorship form
- Mentee taps "My Classes" tab → sees classes with inline module progress
- Mentee taps a module row → arrives at existing module detail screen
- Both screens render without JS errors and work offline (cached data)
- Build passes with no new warnings
