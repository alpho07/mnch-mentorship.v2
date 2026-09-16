# Top-Tab Scope Navigation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the two-step hub-then-scope flow with a persistent sticky segmented control at the top of the shell so users can switch between areas (Assessments / Mentorships / Trainings) instantly via a slide animation — no hub screen, no back button.

**Architecture:** `ScopeShell` is rewritten as a persistent carousel shell: a sticky header (title + avatar + segmented control) sits above a `300%`-wide flex track whose panels are the three scope components. Switching tabs sets `translateX` on the track. All three scopes stay mounted so scroll position is preserved. `ScopeHubScreen` is deleted entirely. Each scope component loses its `header` prop render — the shell owns the header now.

**Tech Stack:** React 18, inline styles (no CSS modules), IndexedDB via `offlineStore`, scope config via `scope-config.js`.

---

## File Map

| File | Action | What changes |
|---|---|---|
| `src/components/ScopeShell.jsx` | **Rewrite** | Carousel shell, sticky header, segmented control, profile sheet |
| `src/components/ScopeHubScreen.jsx` | **Delete** | No longer used |
| `src/scopes/AssessmentsScope.jsx` | **Edit** | Remove `header` prop + `{header}` render (lines 42, 78) |
| `src/scopes/MentorshipsScope.jsx` | **Edit** | Remove `header` prop + `{header}` render (lines 126, 232) |
| `src/scopes/TrainingsScope.jsx` | **Edit** | Remove `header` prop + `{header}` render (lines 29, 39) |

No other files change.

---

## Task 1: Remove `header` prop from AssessmentsScope

**Files:**
- Modify: `src/scopes/AssessmentsScope.jsx` lines 42, 78

The scope currently receives and renders a `header` JSX prop passed down from `ScopeShell`. The shell will own the header going forward, so the prop is dropped.

- [ ] **Step 1.1 — Edit the function signature (line 42)**

Change:
```jsx
export function AssessmentsScope({ user, header, onLogout, onUserUpdate }) {
```
To:
```jsx
export function AssessmentsScope({ user, onLogout, onUserUpdate }) {
```

- [ ] **Step 1.2 — Remove the `{header}` render (line 78)**

Change:
```jsx
        <div style={{ paddingBottom: 64, minHeight: '100vh', background: '#0f172a' }}>
            {header}
            {tab === 'home' && <DashboardScreen
```
To:
```jsx
        <div style={{ paddingBottom: 64, minHeight: '100vh', background: '#0f172a' }}>
            {tab === 'home' && <DashboardScreen
```

- [ ] **Step 1.3 — Commit**
```bash
git add src/scopes/AssessmentsScope.jsx
git commit -m "refactor: remove header prop from AssessmentsScope"
```

---

## Task 2: Remove `header` prop from MentorshipsScope

**Files:**
- Modify: `src/scopes/MentorshipsScope.jsx` lines 126, 232

- [ ] **Step 2.1 — Edit the function signature (line 126)**

Change:
```jsx
export function MentorshipsScope({ user, header, onLogout, onUserUpdate }) {
```
To:
```jsx
export function MentorshipsScope({ user, onLogout, onUserUpdate }) {
```

- [ ] **Step 2.2 — Remove the `{header}` render (line 232)**

Change:
```jsx
        <div style={{ paddingBottom: 64, minHeight: '100vh', background: '#F0F9FA' }}>
            {header}
            {tab === 'home' && !isMentee && <HomeScreen user={user} />}
```
To:
```jsx
        <div style={{ paddingBottom: 64, minHeight: '100vh', background: '#F0F9FA' }}>
            {tab === 'home' && !isMentee && <HomeScreen user={user} />}
```

- [ ] **Step 2.3 — Commit**
```bash
git add src/scopes/MentorshipsScope.jsx
git commit -m "refactor: remove header prop from MentorshipsScope"
```

---

## Task 3: Remove `header` prop from TrainingsScope

**Files:**
- Modify: `src/scopes/TrainingsScope.jsx` lines 29, 39

- [ ] **Step 3.1 — Edit the function signature (line 29)**

Change:
```jsx
export function TrainingsScope({ user, header, onLogout, onUserUpdate }) {
```
To:
```jsx
export function TrainingsScope({ user, onLogout, onUserUpdate }) {
```

- [ ] **Step 3.2 — Remove the `{header}` render (line 39)**

Change:
```jsx
        <div style={{ paddingBottom: 64, minHeight: '100vh', background: '#0f172a' }}>
            {header}
            {tab === 'home'      && <TrainingsHomeScreen user={user} />}
```
To:
```jsx
        <div style={{ paddingBottom: 64, minHeight: '100vh', background: '#0f172a' }}>
            {tab === 'home'      && <TrainingsHomeScreen user={user} />}
```

- [ ] **Step 3.3 — Commit**
```bash
git add src/scopes/TrainingsScope.jsx
git commit -m "refactor: remove header prop from TrainingsScope"
```

---

## Task 4: Rewrite ScopeShell as carousel shell

**Files:**
- Rewrite: `src/components/ScopeShell.jsx`

This is the main change. The new shell:
- Reads scopes from cache exactly as before
- If zero scopes → existing error screen (unchanged)
- If one scope → render carousel with no tab control visible
- If 2+ scopes → render carousel with segmented control
- Active tab index drives both `translateX` on the track and the header gradient

- [ ] **Step 4.1 — Replace the entire file content**

```jsx
import { useState, useEffect } from "react";
import { T } from "../constants.js";
import { getScopesFromCache, cacheScopeConfig } from "../scope-config.js";
import { AssessmentsScope } from "../scopes/AssessmentsScope.jsx";
import { MentorshipsScope } from "../scopes/MentorshipsScope.jsx";
import { TrainingsScope }   from "../scopes/TrainingsScope.jsx";

const SCOPE_COMPONENTS = {
    assessments: AssessmentsScope,
    mentorships: MentorshipsScope,
    trainings:   TrainingsScope,
};

function ScopeIcon({ id, size = 20 }) {
    const w = "1.8";
    if (id === "assessments") return (
        <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth={w} strokeLinecap="round" strokeLinejoin="round">
            <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2" />
            <rect x="9" y="3" width="6" height="4" rx="1" />
            <path d="M9 12l2 2 4-4" /><line x1="9" y1="17" x2="13" y2="17" />
        </svg>
    );
    if (id === "mentorships") return (
        <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth={w} strokeLinecap="round" strokeLinejoin="round">
            <circle cx="8" cy="7" r="3" /><circle cx="17" cy="7" r="3" />
            <path d="M2 21v-2a4 4 0 014-4h5" /><path d="M17 14l2 2 3-3" />
        </svg>
    );
    if (id === "trainings") return (
        <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth={w} strokeLinecap="round" strokeLinejoin="round">
            <path d="M22 10v6M2 10l10-5 10 5-10 5z" />
            <path d="M6 12v5c3 3 9 3 12 0v-5" />
        </svg>
    );
    return (
        <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth={w}>
            <circle cx="12" cy="12" r="8" /><path d="M12 8v4l3 3" strokeLinecap="round" />
        </svg>
    );
}

function headerGradient(scope) {
    if (scope?.gradient?.length === 2) {
        return `linear-gradient(135deg, ${scope.gradient[0]}, ${scope.gradient[1]})`;
    }
    return scope?.color ?? "#0097A7";
}

export function ScopeShell({ user, onLogout, onUserUpdate }) {
    const [scopes, setScopes]         = useState([]);
    const [activeIdx, setActiveIdx]   = useState(0);
    const [ready, setReady]           = useState(false);
    const [showProfile, setShowProfile] = useState(false);
    const [animating, setAnimating]   = useState(false);

    useEffect(() => {
        const userScopes = user?.scopes;
        if (Array.isArray(userScopes) && userScopes.length > 0) {
            cacheScopeConfig(userScopes);
            applyScopes(userScopes);
        } else {
            getScopesFromCache().then(applyScopes);
        }
    }, [user?.id]);

    function applyScopes(resolved) {
        setScopes(resolved);
        setReady(true);
    }

    function switchTab(idx) {
        if (idx === activeIdx || animating) return;
        setAnimating(true);
        setActiveIdx(idx);
        setTimeout(() => setAnimating(false), 400);
    }

    if (!ready) {
        return (
            <div style={{ display: "flex", alignItems: "center", justifyContent: "center", height: "100vh", background: T.bg }}>
                <div style={{ color: T.textSub, fontSize: 14 }}>Loading…</div>
            </div>
        );
    }

    if (scopes.length === 0) {
        return (
            <div style={{ display: "flex", flexDirection: "column", alignItems: "center", justifyContent: "center", height: "100vh", background: T.bg, padding: 32, textAlign: "center" }}>
                <div style={{ width: 72, height: 72, borderRadius: 22, marginBottom: 20, background: T.primaryGhost, border: `2px solid ${T.border}`, display: "flex", alignItems: "center", justifyContent: "center" }}>
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke={T.primary} strokeWidth="1.8" strokeLinecap="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" />
                        <path d="M7 11V7a5 5 0 0110 0v4" />
                    </svg>
                </div>
                <p style={{ color: T.text, fontWeight: 700, fontSize: 18, marginBottom: 8 }}>No areas configured</p>
                <p style={{ color: T.textSub, fontSize: 14, marginBottom: 32 }}>Contact your administrator to get access.</p>
                <button onClick={onLogout} style={{ padding: "12px 28px", background: T.gradientPrimary, color: "white", border: "none", borderRadius: 12, fontWeight: 600, fontSize: 15, cursor: "pointer", boxShadow: `0 4px 16px ${T.primaryGlow}` }}>
                    Log Out
                </button>
            </div>
        );
    }

    const activeScope    = scopes[activeIdx] ?? scopes[0];
    const showTabs       = scopes.length > 1;
    const trackTranslate = `translateX(calc(-${activeIdx} * 100% / ${scopes.length}))`;
    const trackWidth     = `${scopes.length * 100}%`;

    return (
        <div style={{ display: "flex", flexDirection: "column", height: "100vh", overflow: "hidden" }}>

            {/* ── Sticky header ── */}
            <div style={{
                flexShrink: 0,
                background: headerGradient(activeScope),
                transition: "background 0.35s ease",
                zIndex: 100,
            }}>
                {/* Title row */}
                <div style={{ padding: "14px 16px 0", display: "flex", alignItems: "center", justifyContent: "space-between" }}>
                    <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
                        <ScopeIcon id={activeScope.id} size={20} />
                        <span style={{ color: "white", fontWeight: 800, fontSize: 17, letterSpacing: -0.3 }}>
                            MNCH Kenya
                        </span>
                    </div>
                    <button
                        onClick={() => setShowProfile(true)}
                        style={{
                            background: "rgba(255,255,255,0.2)",
                            border: "1px solid rgba(255,255,255,0.25)",
                            borderRadius: "50%",
                            width: 36, height: 36,
                            color: "white", fontWeight: 700, fontSize: 13,
                            cursor: "pointer",
                            display: "flex", alignItems: "center", justifyContent: "center",
                        }}
                    >
                        {user?.initials ?? "?"}
                    </button>
                </div>

                {/* Segmented control — hidden when only one scope */}
                {showTabs && (
                    <div style={{ padding: "10px 14px 12px" }}>
                        <div style={{
                            display: "flex",
                            background: "rgba(255,255,255,0.18)",
                            borderRadius: 12,
                            padding: 3,
                            gap: 3,
                        }}>
                            {scopes.map((scope, idx) => {
                                const isActive = idx === activeIdx;
                                return (
                                    <button
                                        key={scope.id}
                                        onClick={() => switchTab(idx)}
                                        style={{
                                            flex: 1,
                                            padding: "8px 4px",
                                            border: "none",
                                            borderRadius: 10,
                                            cursor: "pointer",
                                            fontWeight: 700,
                                            fontSize: 12,
                                            transition: "all 0.25s ease",
                                            background: isActive ? "white" : "transparent",
                                            color: isActive ? (activeScope.color ?? "#0097A7") : "rgba(255,255,255,0.65)",
                                        }}
                                    >
                                        {scope.label}
                                    </button>
                                );
                            })}
                        </div>
                    </div>
                )}
                {!showTabs && <div style={{ height: 12 }} />}
            </div>

            {/* ── Carousel ── */}
            <div style={{ flex: 1, overflow: "hidden", position: "relative" }}>
                <div style={{
                    display: "flex",
                    width: trackWidth,
                    height: "100%",
                    transform: trackTranslate,
                    transition: "transform 0.38s cubic-bezier(0.4, 0, 0.2, 1)",
                }}>
                    {scopes.map((scope) => {
                        const ScopeComponent = SCOPE_COMPONENTS[scope.id];
                        if (!ScopeComponent) return (
                            <div key={scope.id} style={{ width: `calc(100% / ${scopes.length})`, flexShrink: 0, display: "flex", alignItems: "center", justifyContent: "center" }}>
                                <p style={{ color: T.textSub }}>Unknown scope: {scope.id}</p>
                            </div>
                        );
                        return (
                            <div key={scope.id} style={{ width: `calc(100% / ${scopes.length})`, flexShrink: 0, overflowY: "auto", height: "100%" }}>
                                <ScopeComponent
                                    user={user}
                                    scope={scope}
                                    onLogout={onLogout}
                                    onUserUpdate={onUserUpdate}
                                />
                            </div>
                        );
                    })}
                </div>
            </div>

            {/* ── Profile bottom sheet ── */}
            {showProfile && (
                <div
                    style={{ position: "fixed", inset: 0, background: "rgba(0,0,0,0.4)", zIndex: 200, display: "flex", alignItems: "flex-end" }}
                    onClick={() => setShowProfile(false)}
                >
                    <div
                        style={{ background: "white", width: "100%", borderRadius: "20px 20px 0 0", padding: "8px 20px 40px", boxShadow: "0 -8px 40px rgba(0,0,0,0.12)" }}
                        onClick={e => e.stopPropagation()}
                    >
                        <div style={{ width: 40, height: 4, borderRadius: 2, background: T.border, margin: "12px auto 20px" }} />
                        <div style={{ display: "flex", alignItems: "center", gap: 12, marginBottom: 24 }}>
                            <div style={{ width: 50, height: 50, borderRadius: "50%", background: T.gradientPrimary, display: "flex", alignItems: "center", justifyContent: "center", color: "white", fontWeight: 700, fontSize: 17, flexShrink: 0 }}>
                                {user?.initials ?? "?"}
                            </div>
                            <div>
                                <p style={{ color: T.text, fontWeight: 700, fontSize: 16, margin: "0 0 2px" }}>{user?.name}</p>
                                <p style={{ color: T.textSub, fontSize: 13, margin: 0 }}>{user?.email}</p>
                            </div>
                        </div>
                        <button
                            onClick={onLogout}
                            style={{ width: "100%", padding: "14px 0", background: "linear-gradient(135deg, #EF4444 0%, #DC2626 100%)", color: "white", border: "none", borderRadius: 14, fontWeight: 700, fontSize: 15, cursor: "pointer", boxShadow: "0 4px 16px rgba(239,68,68,0.22)", letterSpacing: 0.2 }}
                        >
                            Log Out
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}
```

- [ ] **Step 4.2 — Verify the dev server builds without errors**

```bash
npm run dev
```

Expected: Vite compiles with no errors. Browser shows the new tab shell immediately after login — segmented control at top, content below.

- [ ] **Step 4.3 — Commit**
```bash
git add src/components/ScopeShell.jsx
git commit -m "feat: rewrite ScopeShell as sticky carousel tab shell"
```

---

## Task 5: Delete ScopeHubScreen

**Files:**
- Delete: `src/components/ScopeHubScreen.jsx`

`ScopeHubScreen` is no longer imported by anything after Task 4.

- [ ] **Step 5.1 — Confirm no remaining imports**

```bash
grep -r "ScopeHubScreen" src/
```

Expected: zero matches (ScopeShell no longer imports it).

- [ ] **Step 5.2 — Delete the file**

```bash
rm src/components/ScopeHubScreen.jsx
```

- [ ] **Step 5.3 — Verify dev server still compiles**

```bash
npm run dev
```

Expected: No errors. File deletion doesn't break the build.

- [ ] **Step 5.4 — Commit**
```bash
git add -A
git commit -m "chore: delete ScopeHubScreen (replaced by carousel shell)"
```

---

## Task 6: Manual verification

No automated test runner is configured for the React app. Verify these manually in the browser (`npm run dev`):

- [ ] **6.1 — After login, hub screen is gone.** You should land directly on the first scope's content (no card grid).

- [ ] **6.2 — Tab switching slides content.** Tap each tab in the segmented control. Content should slide left/right smoothly (≈380ms).

- [ ] **6.3 — Header colour transitions.** Header gradient changes with each tab. Transition is smooth (≈350ms).

- [ ] **6.4 — Scroll position preserved.** Scroll down in Assessments, switch to Mentorships, switch back — Assessments content is still scrolled to the same position.

- [ ] **6.5 — Profile sheet still works.** Tap the avatar initials button top-right. Bottom sheet slides up with name, email, and Log Out button. Tapping outside or tapping overlay dismisses it.

- [ ] **6.6 — Single-scope user (simulate).** Temporarily set `FALLBACK_SCOPES` to a single-item array in `scope-config.js`. Reload. Segmented control should be invisible — only the header and content show.  
Restore the original `FALLBACK_SCOPES` after verifying.

- [ ] **6.7 — Logout works.** Tap avatar → Log Out. App returns to login screen.

- [ ] **6.8 — Final commit**
```bash
git add -A
git commit -m "feat: top-tab scope navigation complete"
```
