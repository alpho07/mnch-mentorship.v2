# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What This Is

A React + Capacitor mobile app for MNCH facility assessments. Field workers use it to conduct structured healthcare facility assessments while potentially offline. The web build is served from `dist/` by the Laravel backend; the same build is also packaged as an Android APK via Capacitor.

## Commands

```bash
# Dev server (proxies /api → https://mnchkenyamentorship.org)
npm run dev

# Production build (outputs to dist/ — served by Laravel)
npm run build

# Lint
npm run lint

# Android: sync web build to native project
npx cap sync android

# Android: open in Android Studio
npx cap open android
```

Environment variable `VITE_API_BASE_URL` overrides the default API base (`https://mnchkenyamentorship.org/api/v1`). Set it in `.env.local` for local backend development.

The dev server proxy (`/api → https://mnchkenyamentorship.org`) only applies during browser dev; Capacitor builds always use the baked-in `BASE_URL`.

## Architecture

### Navigation model
The app uses a **flat state machine** in `App.jsx` — no router. Two state variables drive everything:
- `tab` — active bottom-nav tab (`dashboard | assessments | reports | profile`)
- `modal` — overlay screen (`{ type: "detail" | "form" | "report", data }` or `null`)

Screens are rendered conditionally based on these; modals are absolutely positioned over the tab content.

### Screen structure (`src/screens/`)
| File | Purpose |
|---|---|
| `screen-login.jsx` | Auth (email/password → Bearer token) |
| `screen-dashboard.jsx` | Summary stats + recent assessments |
| `screen-assessments-list.jsx` | All assessments with filter/search |
| `screen-assessment-detail.jsx` | Single assessment sections overview |
| `screen-assessment-form.jsx` | Multi-section form (one section at a time) |
| `screen-assessment-report.jsx` | Scored report with section breakdowns |
| `screen-reports.jsx` | Aggregate analytics across assessments |
| `screen-profile.jsx` | User profile + logout |

Specialist section screens (`screen-human-resources.jsx`, `screen-health-products.jsx`) handle structured table-based data entry for those specific assessment sections.

### Offline-first data layer (`src/services/`)

**`api.service.js`** — Default export `api` is the offline-aware wrapper. Every method:
- GETs: try network → cache in IndexedDB on success → return cached data on network failure
- Writes: try network → queue in `syncQueue` on network failure

`_rawApi` (named export) is the bare fetch wrapper used by the sync queue to replay operations without recursion.

**`offline-store.js`** — IndexedDB wrapper (`mnch_offline` DB, version 2). Stores: `schema`, `assessments`, `responses`, `hr`, `hp`, `user`, `syncQueue`, `meta`. All methods are async and fail silently if IndexedDB is unavailable.

**`sync-queue.js`** — Manages the pending write queue. Auto-flushes on `window.online` and Capacitor `resume` events. Subscribers (via `syncQueue.subscribe()`) get `{ status, pendingCount, lastError }`. The `SyncIndicator` component renders the queue status in the UI.

### Design system (`src/constants.js`)
All design tokens are in the `T` object (colors, radii, shadows, gradients). Use `T.*` throughout components — never hardcode hex values. Helper functions also live here:
- `calcGrade(pct)` → `"green" | "yellow" | "red"` (≥80 green, ≥50 yellow, else red)
- `isQuestionVisible(question, responses)` — evaluates `conditional_logic` / `display_conditions` for dynamic form rendering
- `getSectionCompletion(questions, responses)` — counts required answered questions
- `SECTION_META` — icon and gradient config keyed by section code; the authoritative section schema (questions, labels, etc.) comes from the API (`GET /api/v1/sections/schema/full`)

### Key API endpoints (relative to `BASE_URL`)
- `POST /auth/login` — returns `{ user, token }`
- `GET /auth/me` — session restore
- `GET /sections/schema/full` — full question schema for all sections
- `GET /assessments` — user's assessment list
- `PUT /assessments/:id` — update assessment fields
- `POST /assessments/:id/responses` — bulk save section responses `{ section_code, responses, explanations }`
- `POST /assessments/:id/submit` — mark complete
- `GET /assessments/:id/human-resources` — HR cadre table data
- `POST /assessments/:id/human-resources` — save HR responses
- `GET /assessments/:id/health-products` — HP commodity table data
- `POST /assessments/:id/health-products` — save HP responses

### Auth
Bearer token stored in `localStorage` under key `mnch_token`. `api.setToken()` / `api.clearToken()` / `api.getToken()` manage it. On logout, the offline store is fully wiped.

### Capacitor
App ID: `com.mnch.assessments.app`. `webDir` is `dist/`. The `@jcesarmobile/ssl-skip` package is included for dev/staging environments with self-signed certificates — remove or gate it for production.
