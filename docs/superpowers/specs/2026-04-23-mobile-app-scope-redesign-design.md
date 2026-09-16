# Mobile App Scope Redesign — Design Spec
**Date:** 2026-04-23  
**Status:** Approved  
**Scope:** `public/m-assessment-app/`

---

## Problem

The current mobile app uses a single flat bottom nav (`dashboard | assessments | reports | profile`) that mixes all feature areas. As new scopes are added (Mentorships, Trainings, and future ones), the nav becomes cluttered and confusing. There is no concept of role-based feature areas — all tabs are visible to all users regardless of their role. Scaling this model is not feasible.

---

## Solution Overview

Introduce a **scope-based navigation architecture**. After login, users see a hub screen showing only the feature areas their role allows. Selecting a scope enters a fully isolated navigation context with scope-specific bottom nav tabs. The scope configuration (which roles access which scopes) is managed from the Filament admin panel and stored in the database — no code changes required to add or modify access.

---

## Architecture — Approach B: ScopeShell Component

### Component Hierarchy

```
App.jsx                  → auth, user state, session restore only (~60 lines)
 ├── LoginScreen          → unchanged
 └── ScopeShell           → scope routing, hub, active scope header
       ├── ScopeHubScreen       → role-based scope card grid
       └── AssessmentsScope     → isolated tab/modal state for assessments
       └── MentorshipsScope     → isolated tab/modal state for mentorships
       └── TrainingsScope       → isolated tab/modal state for trainings
```

### File Structure

```
src/
├── scope-config.js              ← local model: reads from IndexedDB cache, hardcoded fallback
├── App.jsx                      ← MODIFIED: auth + session restore only
├── components/
│   ├── ScopeShell.jsx           ← NEW
│   ├── ScopeHubScreen.jsx       ← NEW
│   └── shared-components.jsx   ← unchanged
├── scopes/
│   ├── AssessmentsScope.jsx     ← NEW
│   ├── MentorshipsScope.jsx     ← NEW
│   └── TrainingsScope.jsx       ← NEW
└── screens/                     ← ALL UNCHANGED — re-wired only
```

**Adding a future scope** = create `src/scopes/NewScope.jsx` + add one DB record via Filament. Zero changes to existing files.

---

## Section 1 — Backend: Database-Driven Scope Config

### New Tables

**`scopes`**
| column | type | notes |
|--------|------|-------|
| id | bigint PK | |
| slug | string unique | e.g. `assessments` |
| label | string | Display name |
| icon | string | emoji or icon key |
| color | string | hex color |
| gradient | json | `["#hex1", "#hex2"]` |
| tabs | json | ordered tab slugs e.g. `["home","assessments","reports","profile"]` |
| is_active | boolean | toggle scope on/off globally |
| sort_order | integer | hub card display order |
| timestamps | | |

**`scope_role_access`**
| column | type | notes |
|--------|------|-------|
| id | bigint PK | |
| scope_id | FK → scopes | cascade delete |
| role_name | string | matches Spatie role names |

### Scope Model
- `Scope` model with `roles()` via `scope_role_access`
- Scopes seeded with initial data: `assessments`, `mentorships`, `trainings`

### API Endpoint
- `GET /api/v1/scope-config` — returns scopes the authenticated user's roles are allowed
- Response includes full visual config + a lightweight `summary` object per scope (contextual stats for hub cards)
- Response cached server-side per unique role combination
- Scope config also **included in `GET /auth/me` response** to avoid a second round-trip on login

**Response shape:**
```json
{
  "scopes": [
    {
      "id": "assessments",
      "label": "Assessments",
      "icon": "🏥",
      "color": "#6366F1",
      "gradient": ["#6366F1", "#4F46E5"],
      "tabs": ["home", "assessments", "reports", "profile"],
      "summary": { "in_progress": 2, "completed": 5 }
    }
  ]
}
```

### Filament Resource — `ScopeResource`
- Navigation group: Settings (or new "App Configuration" group)
- **Table view:** list all scopes, sortable by `sort_order`, toggle `is_active` inline
- **Form fields:**
  - `slug` — text, unique, required
  - `label` — text, required
  - `icon` — text (emoji or icon key)
  - `color` — color picker
  - `gradient` — two color pickers (start + end)
  - `tabs` — repeater field (ordered list of tab slugs)
  - `sort_order` — numeric
  - `is_active` — toggle
  - `roles` — multi-select checkbox list of all 15 system roles
- **Super Admin override:** hardcoded in `getScopesForUser()` — super_admin always sees all active scopes regardless of DB config (lockout prevention)

---

## Section 2 — `scope-config.js` (Mobile)

Becomes a local model helper, not a static config file. Source of truth is the database.

```js
// src/scope-config.js

// Hardcoded fallback — used only on first boot with no network
const FALLBACK_SCOPES = [
  {
    id: "assessments",
    label: "Assessments",
    icon: "🏥",
    color: "#6366F1",
    gradient: ["#6366F1", "#4F46E5"],
    tabs: ["home", "assessments", "reports", "profile"],
    summary: {}
  },
  // ... mentorships, trainings
];

// Returns scopes from IndexedDB cache (populated on login from /auth/me)
// Falls back to FALLBACK_SCOPES if cache is empty (first offline boot)
export async function getScopesForUser() {
  const cached = await offlineStore.getScopeConfig();
  return cached?.length ? cached : FALLBACK_SCOPES;
}
```

On login → `GET /auth/me` includes scope config → saved to `offlineStore` → available offline.

---

## Section 3 — ScopeHubScreen

Post-login landing screen shown to users with 2+ allowed scopes.

### Routing Logic (runs immediately after session restore)
```
getScopesForUser(roles)
  → 0 scopes  →  "Contact your administrator" empty state
  → 1 scope   →  auto-enter that scope, hub never shown
  → 2+ scopes →  render ScopeHubScreen
```
Single-scope roles (e.g. `mentee`) land directly inside their scope with zero extra interaction.

### Layout
```
┌─────────────────────────────────┐
│  Good morning, {name}       👤  │
│  MNCH Mentorship Platform       │
├─────────────────────────────────┤
│                                 │
│  Choose your area               │
│                                 │
│  ┌─────────────┐ ┌───────────┐  │
│  │  🏥         │ │  🎓       │  │
│  │ Assessments │ │Mentorships│  │
│  │ 2 in prog.  │ │1 active   │  │
│  └─────────────┘ └───────────┘  │
│                                 │
│  ┌─────────────┐                │
│  │  📋         │                │
│  │  Trainings  │                │
│  │  3 upcoming │                │
│  └─────────────┘                │
│                                 │
└─────────────────────────────────┘
```

### Card Design
- Background: scope `gradient` from DB config
- Contextual stat: from `summary` field in scope config response
- Staggered scale + fade animation on mount
- Odd number of scopes: last card centered
- No bottom nav on this screen
- Top-right avatar → profile/logout sheet

---

## Section 4 — ScopeShell Component

Owns scope routing and the persistent scope header shown inside any active scope.

### State
```js
const [activeScope, setActiveScope] = useState(null); // null = hub
const [scopes, setScopes]           = useState([]);   // loaded from cache
```

### Render Logic
```
if !activeScope → <ScopeHubScreen />
if  activeScope → <ScopeHeader /> + <{ActiveScope}Component />
```

### ScopeHeader (top bar)
```
┌──────────────────────────────────┐
│  ⊞  {Scope Name}            👤  │
└──────────────────────────────────┘
```
- **Left:** grid icon → `setActiveScope(null)` → returns to hub
- **Center:** active scope name
- **Right:** user avatar → profile sheet
- **Single-scope roles:** grid icon is hidden (no hub to return to)

### Scope Isolation
Each scope component owns its own `tab` + `modal` state. Switching scopes resets that state — stale modal state never leaks between scopes.

---

## Section 5 — Per-Scope Components

### Bottom Nav Tabs

| Scope | Tabs |
|-------|------|
| Assessments | `home` · `assessments` · `reports` · `profile` |
| Mentorships (mentor) | `home` · `mentorships` · `classes` · `profile` |
| Mentorships (mentee) | `home` · `my-classes` · `profile` |
| Trainings | `home` · `trainings` · `profile` |

### Screen Wiring

**AssessmentsScope**
| Tab | Screens |
|-----|---------|
| home | `DashboardScreen` (assessments data) |
| assessments | `AssessmentsListScreen` → `AssessmentDetailScreen` → `AssessmentFormScreen` → `HumanResourcesScreen` → `HealthProductsScreen` |
| reports | `ReportsScreen` → `AssessmentReportScreen` → `EmailJobsScreen` |
| profile | `ProfileScreen` |

**MentorshipsScope**
| Tab | Screens |
|-----|---------|
| home | `DashboardScreen` (mentorships data) |
| mentorships | `MentorshipsListScreen` → `MentorshipDetailScreen` → `MentorshipFormScreen` |
| classes | `ClassDetailScreen` → `ModuleDetailScreen` → `AttendanceRosterScreen` → `SessionNotesScreen` → `MenteeManagerScreen` → `ModulePickerScreen` → `ClassFormScreen` |
| my-classes (mentee) | `MyClassesScreen` → `ClassProgressScreen` |
| profile | `ProfileScreen` |

**TrainingsScope**
| Tab | Screens |
|-----|---------|
| home | `DashboardScreen` (trainings data) |
| trainings | `TrainingsListScreen` → `TrainingDetailScreen` |
| profile | `ProfileScreen` |

### Mentee Special Case
`mentee` role auto-loads `MentorshipsScope`. Inside `MentorshipsScope`, role is checked — if `mentee`, render reduced tab set (`home | my-classes | profile`) wiring mentee-specific screens. Mentor tab set otherwise.

### Component Pattern
```jsx
export function AssessmentsScope({ user, onScopeSwitch }) {
  const [tab, setTab]     = useState("home");
  const [modal, setModal] = useState(null);

  return (
    <>
      <ScopeHeader scope="assessments" onSwitch={onScopeSwitch} user={user} />
      {/* screen render based on tab/modal */}
      <ScopeBottomNav tabs={SCOPE_TABS.assessments} active={tab} onChange={setTab} />
    </>
  );
}
```

---

## Section 6 — App.jsx

Reduced to auth + session restore only.

```jsx
export default function App() {
  const [user, setUser]     = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const token = api.getToken();
    if (!token) { setLoading(false); return; }
    api.auth.me()
      .then(data => setUser(normaliseUser(data?.user ?? data)))
      .catch(() => api.clearToken())
      .finally(() => setLoading(false));
  }, []);

  if (loading) return <SplashScreen />;
  if (!user)   return <LoginScreen onLogin={setUser} />;
  return       <ScopeShell user={user} onLogout={() => setUser(null)} />;
}
```

`App.jsx` reduces from ~400 lines to ~60 lines.

---

## Offline Behaviour

- Scope config cached in IndexedDB on login via `offlineStore.saveScopeConfig()`
- On network failure, cached config used — user enters their scope normally
- Scope config refreshed on every successful `auth/me` call
- First-ever offline boot (no cache): falls back to `FALLBACK_SCOPES` in `scope-config.js`

---

## What Does NOT Change

- All existing screen files in `src/screens/` — untouched
- `api.service.js` — untouched (new endpoint added only)
- `offline-store.js` — one new store key (`scopeConfig`) added
- Design tokens in `constants.js` — untouched
- `T.*` usage throughout — untouched

---

## Implementation Order

1. Backend: migration + `Scope` model + seed data
2. Backend: `ScopeResource` in Filament
3. Backend: `GET /api/v1/scope-config` endpoint + include in `/auth/me`
4. Mobile: `offlineStore` — add `scopeConfig` store
5. Mobile: `scope-config.js` — cache reader + fallback
6. Mobile: `App.jsx` — strip down to auth only
7. Mobile: `ScopeShell.jsx` + `ScopeHubScreen.jsx`
8. Mobile: `AssessmentsScope.jsx`
9. Mobile: `MentorshipsScope.jsx` (with mentee variant)
10. Mobile: `TrainingsScope.jsx`
