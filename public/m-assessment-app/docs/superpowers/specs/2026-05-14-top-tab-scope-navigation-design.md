# Top-Tab Scope Navigation — Design Spec
**Date:** 2026-05-14  
**Status:** Approved

---

## Overview

Replace the current two-step hub-then-scope flow with a persistent top-tab layout. Users land directly on their first scope after login; a sticky segmented control at the top lets them switch between areas instantly with a slide animation — no hub screen, no back-and-forth.

The pattern is modelled on TikTok / Uber: tabs at the very top of the screen, content loads below, switching is instant and animated.

---

## What Changes

| Before | After |
|---|---|
| Login → ScopeHubScreen (card grid) → tap card → scope loads | Login → scope tabs always visible, first tab active |
| ⊞ button in header to go back to hub | Tap any tab to switch directly |
| Each scope has its own coloured header rendered separately | One unified sticky header, colour driven by active tab |
| `ScopeHubScreen` component used | `ScopeHubScreen` removed |

---

## Architecture

### ScopeShell restructure

`ScopeShell` currently acts as a router: show hub OR show active scope. After this change it becomes a **persistent shell** that always renders:

1. A sticky header bar (title + avatar)  
2. A segmented control (tabs) inside the header  
3. A carousel content area below

The hub screen (`ScopeHubScreen`) is deleted entirely.

### Carousel container

```
┌─────────────────────────────┐  ← overflow: hidden
│ ◄──── carousel-track ────► │  ← width: 300%, display: flex
│ [AssessmentsScope][MentorshipsScope][TrainingsScope] │
└─────────────────────────────┘
```

- The track is `width: 300%` with `display: flex`
- Each panel is `width: calc(100% / 3)` — exactly one viewport wide
- Switching tabs sets `transform: translateX(calc(-idx * 100% / 3))` on the track
- Transition: `0.38s cubic-bezier(0.4, 0, 0.2, 1)` (Material-style ease-in-out)
- All three scope components are always mounted → scroll position preserved, no re-fetch on switch

### Header

- `position: sticky; top: 0; z-index: 100`
- Background transitions via CSS `transition: background 0.35s ease` when tab changes
- Contains: app title left, avatar button right, segmented control below

### Segmented control

- Style: rounded container (`border-radius: 12px`), `background: rgba(255,255,255,0.18)`
- Each tab: `border-radius: 10px`, inactive label `rgba(255,255,255,0.65)`
- Active tab: `background: white`, label colour = scope's accent colour
- Padding: `3px` inside container, `3px` gap between tabs

### Per-scope colours

| Scope | Header gradient | Active tab label |
|---|---|---|
| assessments | `#007B8E → #0097A7` (teal) | `#0097A7` |
| mentorships | `#3730a3 → #4F46E5` (indigo) | `#4F46E5` |
| trainings | `#b45309 → #D97706` (amber) | `#D97706` |

Header background transitions smoothly when switching tabs (`transition: background 0.35s ease`).

---

## Edge Cases

**Single scope:** If `scopes.length === 1`, the segmented control is hidden (`display: none`). The header still shows with the scope's colour and title. Carousel still used internally (one panel), so no separate code path is needed.

**Zero scopes:** Unchanged — existing "No areas configured" error screen is shown as today.

**Scope not in known list:** If a user's scope id doesn't match `assessments | mentorships | trainings`, that tab still renders with a fallback neutral colour (`#6366F1`).

**Tab order:** Tabs are rendered in the order they appear in `user.scopes` from the API — not hardcoded. This means the server controls which tabs a user sees and in what order.

---

## Components Affected

| File | Change |
|---|---|
| `src/components/ScopeShell.jsx` | Full rewrite — add carousel, sticky header with segmented control, remove hub routing |
| `src/components/ScopeHubScreen.jsx` | Deleted |
| `src/App.jsx` | No change |
| `src/scopes/AssessmentsScope.jsx` | No change — receives same `header` prop (now unused; shell owns the header) |
| `src/scopes/MentorshipsScope.jsx` | Same |
| `src/scopes/TrainingsScope.jsx` | Same |

### Header prop deprecation

`ScopeShell` currently passes a `header` JSX prop to each scope so the scope can render its own header. With the new design, the shell owns the header — it no longer passes `header` down. Each scope component must stop rendering the header prop at the top of its layout.

This means a small edit to each scope: remove the `{header}` render line from `AssessmentsScope`, `MentorshipsScope`, and `TrainingsScope`.

---

## What Is Not Changing

- Internal navigation within each scope (bottom tabs, modals, back stacks) — untouched
- Auth, session restore, logout flow — untouched
- Offline/sync layer — untouched
- All screen components — untouched
- The profile bottom sheet (currently in `ScopeHeader`) moves into the new shell header

---

## Success Criteria

1. After login, user lands directly on their first assigned scope — no hub screen
2. Tapping a tab slides the content left/right with the carousel animation
3. Header colour transitions smoothly to the new scope's gradient
4. Switching tabs and switching back preserves the previous tab's scroll position
5. Users with only one scope see no tab control (single area, no UI clutter)
6. Profile bottom sheet accessible via avatar button in the header
