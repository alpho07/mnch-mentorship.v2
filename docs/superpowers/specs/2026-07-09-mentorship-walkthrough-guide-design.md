# Mentorship Walkthrough Guide — Design Spec
**Date:** 2026-07-09  
**Status:** Approved

---

## Overview

Add an interactive step-by-step walkthrough guide to the Mentorship list page. The guide explains the full mentorship workflow in plain language, is clear enough for elderly users, and draws attention with a blinking trigger button. Users are auto-prompted once on first visit and can re-open the guide any time.

---

## Scope

**One file changed:** `resources/views/filament/widgets/mentorship-guidance-notice.blade.php`  
No new PHP classes, no new Livewire components, no new widgets.

---

## Components

### 1. Auto-Prompt Banner

- Renders at the top of `MentorshipGuidanceNotice` blade on first page load
- Controlled by `localStorage` key `mnch_guide_prompted`
- If key is absent → banner is visible; if key is `"1"` → banner is hidden
- Banner slides down using Alpine `x-transition:enter` (translate-y + opacity)
- Contains: greeting text, "Yes, show me" button, "No thanks" button
- "Yes, show me" → sets localStorage key, opens guide modal
- "No thanks" → sets localStorage key, hides banner (never shows again)

### 2. Persistent Blinking Trigger Button

- Always visible in the header row of the existing guidance notice section
- Label: "How does this work?" with a play/film icon
- Teal background matching project primary (`#0097A7`)
- CSS `@keyframes` pulse-glow animation: soft box-shadow breathing every 2 s
- Clicking it opens the guide modal (no localStorage interaction — always available)

### 3. Guide Modal

- Centred overlay modal, `max-width: 42rem`
- Semi-transparent dark backdrop
- Alpine.js manages all state — no Livewire round-trips
- State properties:
  - `open` — bool, controls modal visibility
  - `step` — int 0–6, current step index
  - `direction` — `'next'` | `'prev'`, drives slide animation direction
- Closes on backdrop click or ✕ button

#### Modal Header
- Step counter: "Step N of 7" (small, gray)
- Progress bar: fills proportionally as `step` advances, teal fill
- ✕ close button top-right

#### Step Card
- Large heroicon (4 rem, teal) — centered
- Bold step title (`text-2xl font-bold`) — centered
- 3 bullet points (`text-base leading-relaxed`) — left-aligned with teal checkmark prefix
- Text sizes chosen for elderly readability (no text smaller than `text-base`)

#### Navigation
- Steps 1–6: "← Back" (gray) + "Next Step →" (teal) buttons
- Step 1: no Back button
- Step 7 (final): "← Back" + "✚ Create My First Mentorship" (green, links to `/admin/mentorship/create`)

#### Slide Animation
- Cards slide in/out using Alpine `x-transition` with `translateX`
- Direction-aware: Next → slides left-in / right-out; Back → slides right-in / left-out
- Duration: 250 ms

---

## The 7 Steps

| # | Heroicon | Title | Bullet 1 | Bullet 2 | Bullet 3 |
|---|----------|-------|----------|----------|----------|
| 1 | `academic-cap` | Create a Mentorship | Select the county and facility where mentoring will happen | Choose the program (e.g. Newborn Care, Infant & Child) | Set the start date, end date, and maximum number of mentees |
| 2 | `user-group` | Set Up a Class | Give the class a name, like "Cohort 1" or "July Group" | Set the class start and end dates | One mentorship can run many classes — one after another or at the same time |
| 3 | `book-open` | Add Modules | Modules are the topics you will teach in this class | Pick them from the program's curriculum list | You can also add sessions to each module to break it into smaller lessons |
| 4 | `users` | Enroll Mentees | Search for existing staff members and add them to the class | Or add a brand-new person by filling in their name, phone, and department | You can also import a list of mentees from a CSV file |
| 5 | `play-circle` | Start a Module | When ready, click Start on a module to open attendance tracking | Mentees receive a link they tap to confirm they attended the session | You can see who has confirmed in real time from the module page |
| 6 | `check-badge` | Mentees Confirm Attendance | Each mentee taps their personal attendance link on their phone | Their attendance is recorded and their progress updates automatically | If a mentee cannot tap the link, you can mark them as attended manually |
| 7 | `trophy` | Complete & Close | Mark each module as Complete once all sessions are done | Then end the class — all scores and attendance records are saved | You can run a full class report to share results with your supervisor |

---

## Data Flow

```
Page load
  └─ Alpine x-init checks localStorage['mnch_guide_prompted']
       ├─ absent  → showPrompt = true  (banner slides in)
       └─ "1"     → showPrompt = false (banner stays hidden)

User clicks "Yes, show me" on banner
  └─ localStorage['mnch_guide_prompted'] = "1"
  └─ showPrompt = false, open = true, step = 0

User clicks "No thanks" on banner
  └─ localStorage['mnch_guide_prompted'] = "1"
  └─ showPrompt = false

User clicks "How does this work?" button (any time)
  └─ open = true, step = 0

User clicks Next / Back
  └─ direction = 'next'|'prev', step += 1 or -= 1
  └─ Alpine x-transition drives card slide animation

User clicks "Create My First Mentorship" (step 7)
  └─ window.location = '/admin/mentorship/create'
```

---

## CSS

Added inline in the blade `<style>` block (scoped to this widget only):

```css
@keyframes guide-pulse {
  0%, 100% { box-shadow: 0 0 0 0 rgba(0,151,167,0.4); }
  50%       { box-shadow: 0 0 0 8px rgba(0,151,167,0); }
}
.guide-pulse-btn { animation: guide-pulse 2s ease-in-out infinite; }
```

No changes to `filament-admin-theme.css` or any compiled CSS file.

---

## Constraints

- No Livewire, no PHP logic changes — pure Alpine.js + HTML in one blade file
- All step content is hardcoded in the Alpine `steps` array inside the blade
- localStorage is the only persistence mechanism (no DB, no user preference model)
- Existing guidance notice content (manuals links) is preserved below the guide trigger button
- Must work in dark mode (uses Tailwind dark: variants throughout)
