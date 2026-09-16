# MNCH Mobile App UI Redesign — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Restyle the MNCH mobile app from teal/DM-Sans to Apple iOS Minimal with Indigo Sapphire accent — zero logic changes, pure styling.

**Architecture:** Update design tokens in `constants.js` first (cascades to all screens via `T.*` imports), then restyle the three components that have structural layout changes (BottomNav pill, login hero, dashboard hero), then sweep remaining screens to replace hardcoded teal orb colours with indigo equivalents.

**Tech Stack:** React 19, Vite 8, inline styles + `T.*` token system, `-apple-system` font stack, CSS keyframes in `index.css`.

---

## File Map

| File | Change type |
|---|---|
| `src/index.css` | Remove Google Fonts import; update CSS vars; add `slideInUp` keyframe |
| `src/constants.js` | Replace entire `T` object with new tokens |
| `src/components/shared-components.jsx` | BottomNav: frosted-glass + indigo pill; PhoneShell: font + shadow |
| `src/screens/screen-login.jsx` | Hero: gradient + floating logo mark + focus rings |
| `src/screens/screen-dashboard.jsx` | Hero: glassmorphism stat pills; cards: new shadow |
| `src/screens/screen-assessments-list.jsx` | Orb colours → indigo |
| `src/screens/screen-mentorships-list.jsx` | Orb colours → indigo |
| `src/screens/screen-mentorship-detail.jsx` | Orb colours → indigo |
| `src/screens/screen-class-detail.jsx` | Orb colours → indigo |
| `src/screens/screen-module-detail.jsx` | Orb colours → indigo |
| `src/screens/screen-profile.jsx` | Orb colours → indigo |
| `src/screens/screen-training-detail.jsx` | Orb colours → indigo |
| `src/App.jsx` | Loading spinner colours |

---

## Task 1 — Design Tokens (`constants.js` + `index.css`)

**Files:**
- Modify: `src/constants.js`
- Modify: `src/index.css`

> Updating tokens here cascades to every screen that imports `T`. Do this first — it gives the biggest visual shift with one edit.

- [ ] **Step 1: Replace the `T` object in `src/constants.js`**

Replace the entire `export const T = { ... }` block (lines 2–54) with:

```js
export const T = {
    // Backgrounds
    bg:           "#F2F2F7",
    card:         "#FFFFFF",
    cardHover:    "#F9F9FB",

    // Primary — Indigo Sapphire
    primary:      "#4F6AF5",
    primaryLight: "#6C63FF",
    primaryDark:  "#3A54D4",
    primaryGhost: "rgba(79,106,245,0.08)",
    primaryGlow:  "rgba(79,106,245,0.20)",

    // Accent (alias — used by a few screens)
    accent:       "#6C63FF",
    accentLight:  "#A5B4FF",
    accentGhost:  "rgba(108,99,255,0.08)",

    // Success
    success:      "#10B981",
    successLight: "#34D399",
    successGhost: "rgba(16,185,129,0.08)",

    // Text hierarchy (iOS system colours)
    text:         "#1C1C1E",
    textMid:      "#3C3C43",
    textSub:      "#636366",
    textMuted:    "#8E8E93",

    // Borders
    border:       "rgba(0,0,0,0.08)",
    borderLight:  "#F2F2F7",
    separator:    "rgba(60,60,67,0.12)",

    // Radii
    radius:       18,
    radiusSm:     14,
    radiusXs:     10,
    radiusPill:   50,

    // Shadows
    shadow:       "0 1px 3px rgba(0,0,0,0.04)",
    shadowMd:     "0 4px 20px rgba(79,106,245,0.12)",
    shadowLg:     "0 8px 32px rgba(79,106,245,0.18)",
    shadowCard:   "0 1px 3px rgba(0,0,0,0.04), 0 4px 12px rgba(79,106,245,0.06)",

    // Gradients
    gradientPrimary: "linear-gradient(135deg, #4F6AF5 0%, #6C63FF 100%)",
    gradientHero:    "linear-gradient(150deg, #1A1A2E 0%, #1E2A5E 55%, #2D3B8E 100%)",
    gradientDark:    "linear-gradient(150deg, #1A1A2E 0%, #1E2A5E 55%, #2D3B8E 100%)",
    gradientSky:     "linear-gradient(135deg, #4F6AF5 0%, #6C63FF 100%)",
    gradientSuccess: "linear-gradient(135deg, #10B981 0%, #34D399 100%)",
    gradientWarm:    "linear-gradient(135deg, #F59E0B 0%, #FBBF24 100%)",
    gradientGlass:   "linear-gradient(135deg, rgba(255,255,255,0.12), rgba(255,255,255,0.06))",
};
```

- [ ] **Step 2: Update `src/index.css`**

Replace the existing `:root` block and `@import` with:

```css
/* ── Reset ─────────────────────────────────────────────────────────────────── */
*,
*::before,
*::after {
    box-sizing: border-box;
}

:root {
    font-family: -apple-system, 'SF Pro Display', 'Segoe UI', system-ui, sans-serif;
    line-height: 1.5;
    font-weight: 400;
    font-synthesis: none;
    text-rendering: optimizeLegibility;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;

    --sat: env(safe-area-inset-top, 0px);
    --sar: env(safe-area-inset-right, 0px);
    --sab: env(safe-area-inset-bottom, 0px);
    --sal: env(safe-area-inset-left, 0px);

    --nav-bar-height: 64px;
    --bottom-inset: calc(var(--nav-bar-height) + var(--sab));

    --primary: #4F6AF5;
    --primary-light: #6C63FF;
    --accent: #6C63FF;
    --success: #10B981;
    --bg: #F2F2F7;
}
```

Then add the `slideInUp` keyframe after the existing `slideInUp` (replace it, or add if missing):

```css
@keyframes slideInUp {
    from {
        opacity: 0;
        transform: translateY(100%);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
```

- [ ] **Step 3: Update `html, body` background colour**

In `index.css`, change:
```css
background: #F4F6FB;
```
to:
```css
background: #F2F2F7;
```

- [ ] **Step 4: Commit**

```bash
cd C:\xampp\htdocs\MNCH-Master
git add public/m-assessment-app/src/constants.js public/m-assessment-app/src/index.css
git commit -m "feat(ui): update design tokens to Indigo Sapphire + iOS system palette"
```

---

## Task 2 — Shared Components (`shared-components.jsx`)

**Files:**
- Modify: `src/components/shared-components.jsx`

- [ ] **Step 1: Update `PhoneShell` — font stack and shadow colour**

Replace the `PhoneShell` function (lines 12–26):

```jsx
export function PhoneShell({ children }) {
    return (
        <div style={{
            width: "100%", maxWidth: 390, height: "100dvh",
            margin: "0 auto", background: T.bg,
            position: "relative", overflow: "hidden",
            boxShadow: "0 0 80px rgba(26,26,46,0.18)",
            fontFamily: "-apple-system, 'SF Pro Display', 'Segoe UI', system-ui, sans-serif",
            borderRadius: 0,
        }}>
            <style>{ANIM_STYLES}</style>
            {children}
        </div>
    );
}
```

- [ ] **Step 2: Replace `BottomNav` with frosted-glass + indigo pill**

Replace the entire `BottomNav` function (lines 79–205) with:

```jsx
export function BottomNav({ active, onChange, tabs, showFab = false }) {
    const resolvedTabs = tabs ?? [
        { key: "dashboard",   label: "Home",        iconKey: "dashboard"   },
        { key: "assessments", label: "Assessments",  iconKey: "assessments" },
        { key: "reports",     label: "Reports",      iconKey: "reports"     },
        { key: "profile",     label: "Profile",      iconKey: "profile"     },
    ];

    const mid = Math.floor(resolvedTabs.length / 2);
    const displayTabs = showFab
        ? [...resolvedTabs.slice(0, mid), { key: "new", label: "New", iconKey: null }, ...resolvedTabs.slice(mid)]
        : resolvedTabs;

    return (
        <div style={{
            paddingBottom: "env(safe-area-inset-bottom, 0px)",
            background: "rgba(255,255,255,0.92)",
            backdropFilter: "blur(20px) saturate(180%)",
            WebkitBackdropFilter: "blur(20px) saturate(180%)",
            borderTop: "0.5px solid rgba(0,0,0,0.10)",
            boxShadow: "0 -4px 24px rgba(79,106,245,0.06)",
        }}>
            <div style={{ height: 64, display: "flex", alignItems: "stretch" }}>
                {displayTabs.map(t => {
                    const isActive = active === t.key;
                    const iconFn   = NavIcons[t.iconKey];

                    if (t.key === "new") return (
                        <button
                            key="new"
                            onClick={() => onChange("new")}
                            aria-label="New assessment"
                            style={{
                                flex: 1, border: "none", background: "none",
                                cursor: "pointer", padding: 0,
                                display: "flex", flexDirection: "column",
                                alignItems: "center", justifyContent: "flex-end",
                                paddingBottom: 8, position: "relative",
                            }}
                        >
                            <div style={{
                                position: "absolute", bottom: 10,
                                width: 52, height: 52, borderRadius: "50%",
                                background: T.gradientPrimary,
                                boxShadow: "0 -2px 16px rgba(79,106,245,0.35), 0 4px 16px rgba(79,106,245,0.30)",
                                display: "flex", alignItems: "center", justifyContent: "center",
                                transition: "transform 0.2s cubic-bezier(0.34,1.56,0.64,1)",
                            }}>
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="2.5" strokeLinecap="round">
                                    <line x1="12" y1="5" x2="12" y2="19" /><line x1="5" y1="12" x2="19" y2="12" />
                                </svg>
                            </div>
                            <span style={{ fontSize: 10, fontWeight: 600, color: T.primary, paddingTop: 32 }}>New</span>
                        </button>
                    );

                    return (
                        <button
                            key={t.key}
                            onClick={() => onChange(t.key)}
                            style={{
                                flex: 1, border: "none", background: "none",
                                cursor: "pointer", padding: "8px 4px 6px",
                                display: "flex", flexDirection: "column",
                                alignItems: "center", justifyContent: "center", gap: 3,
                                transition: "all 0.2s cubic-bezier(0.4,0,0.2,1)",
                            }}
                        >
                            {/* Filled pill behind active icon */}
                            <div style={{
                                width: 44, height: 28, borderRadius: 14,
                                background: isActive ? T.gradientPrimary : "transparent",
                                display: "flex", alignItems: "center", justifyContent: "center",
                                transition: "all 0.25s cubic-bezier(0.34,1.56,0.64,1)",
                                boxShadow: isActive ? "0 4px 12px rgba(79,106,245,0.28)" : "none",
                            }}>
                                {isActive ? (
                                    /* White icon inside filled pill */
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                                        stroke="white" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                        {t.iconKey === "dashboard"   && <><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z" fill="rgba(255,255,255,0.25)"/><polyline points="9 22 9 12 15 12 15 22"/></>}
                                        {t.iconKey === "assessments" && <><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z" fill="rgba(255,255,255,0.25)"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></>}
                                        {t.iconKey === "reports"     && <><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></>}
                                        {t.iconKey === "mentorship"  && <><circle cx="12" cy="8" r="4" fill="rgba(255,255,255,0.25)"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></>}
                                        {t.iconKey === "myClasses"   && <><path d="M2 3h6a4 4 0 014 4v14a3 3 0 00-3-3H2z" fill="rgba(255,255,255,0.25)"/><path d="M22 3h-6a4 4 0 00-4 4v14a3 3 0 013-3h7z"/></>}
                                        {t.iconKey === "trainings"   && <><rect x="3" y="4" width="18" height="16" rx="2" fill="rgba(255,255,255,0.25)"/><path d="M8 9h8M8 13h5"/></>}
                                        {t.iconKey === "profile"     && <><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4" fill="rgba(255,255,255,0.25)"/></>}
                                    </svg>
                                ) : iconFn && iconFn(false)}
                            </div>
                            <span style={{
                                fontSize: 9, fontWeight: isActive ? 700 : 500,
                                color: isActive ? T.primary : T.textMuted,
                                transition: "all 0.2s",
                            }}>
                                {t.label}
                            </span>
                        </button>
                    );
                })}
            </div>
        </div>
    );
}
```

- [ ] **Step 3: Update `BackButton` colours**

Replace the `BackButton` function (lines 208–229):

```jsx
export function BackButton({ onBack, light = false }) {
    return (
        <button
            onClick={onBack}
            style={{
                background: light ? "rgba(255,255,255,0.12)" : T.primaryGhost,
                border: "none", borderRadius: 12, padding: "8px 16px",
                cursor: "pointer", fontSize: 13, fontWeight: 600,
                color: light ? "white" : T.primary,
                display: "flex", alignItems: "center", gap: 6,
                backdropFilter: light ? "blur(8px)" : "none",
                transition: "all 0.2s",
                marginTop: "env(safe-area-inset-top, 0px)",
            }}
        >
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                <polyline points="15 18 9 12 15 6" />
            </svg>
            Back
        </button>
    );
}
```

- [ ] **Step 4: Update `Skeleton` shimmer colours**

Replace the `Skeleton` function (lines 356–366):

```jsx
export function Skeleton({ width = "100%", height = 16, radius = 8, style = {} }) {
    return (
        <div style={{
            width, height, borderRadius: radius,
            background: "linear-gradient(90deg, #EBEBF0 25%, #E0E0E8 50%, #EBEBF0 75%)",
            backgroundSize: "200% 100%",
            animation: "shimmer 1.5s infinite",
            ...style,
        }} />
    );
}
```

- [ ] **Step 5: Commit**

```bash
cd C:\xampp\htdocs\MNCH-Master
git add public/m-assessment-app/src/components/shared-components.jsx
git commit -m "feat(ui): BottomNav indigo pill, PhoneShell SF Pro font"
```

---

## Task 3 — Login Screen (`screen-login.jsx`)

**Files:**
- Modify: `src/screens/screen-login.jsx`

- [ ] **Step 1: Replace the entire `LoginScreen` component**

Replace the full contents of `src/screens/screen-login.jsx` with:

```jsx
import { useState } from "react";
import { T } from "../constants.js";
import api from "../services/api.service.js";

export function LoginScreen({ onLogin }) {
    const [email, setEmail]             = useState("");
    const [password, setPassword]       = useState("");
    const [error, setError]             = useState("");
    const [loading, setLoading]         = useState(false);
    const [focused, setFocused]         = useState(null);
    const [showPassword, setShowPassword] = useState(false);

    const handleLogin = async () => {
        if (!email || !password) { setError("Email and password are required."); return; }
        setLoading(true); setError("");
        try {
            const data = await api.auth.login(email, password);
            api.setToken(data.token);
            onLogin(data.user);
        } catch (e) {
            setError(e.message || "Login failed. Please try again.");
        } finally {
            setLoading(false);
        }
    };

    return (
        <div style={{ display: "flex", flexDirection: "column", height: "100%", background: T.bg,
            fontFamily: "-apple-system, 'SF Pro Display', 'Segoe UI', system-ui, sans-serif" }}>
            <style>{`
                @keyframes logoFloat{0%,100%{transform:translateY(0)}50%{transform:translateY(-6px)}}
                @keyframes loginFade{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}
                @keyframes btnShine{0%{left:-100%}100%{left:150%}}
                @keyframes spinCW{from{transform:rotate(0deg)}to{transform:rotate(360deg)}}
            `}</style>

            {/* Hero */}
            <div style={{
                background: T.gradientHero,
                padding: "44px 28px 40px",
                position: "relative", overflow: "hidden",
                borderRadius: "0 0 28px 28px",
                margin: "0 6px",
            }}>
                {/* Orbs */}
                <div style={{ position:"absolute", width:200, height:200, borderRadius:"50%",
                    background:"radial-gradient(circle, rgba(79,106,245,0.25) 0%, transparent 70%)",
                    top:-60, right:-60, pointerEvents:"none" }} />
                <div style={{ position:"absolute", width:120, height:120, borderRadius:"50%",
                    background:"radial-gradient(circle, rgba(108,99,255,0.18) 0%, transparent 70%)",
                    bottom:-30, left:20, pointerEvents:"none" }} />

                {/* Logo mark */}
                <div style={{
                    width:64, height:64, borderRadius:20,
                    background: T.gradientPrimary,
                    display:"flex", alignItems:"center", justifyContent:"center",
                    marginBottom:20,
                    boxShadow:"0 8px 28px rgba(79,106,245,0.40)",
                    animation:"logoFloat 4s ease-in-out infinite",
                    position:"relative", zIndex:1,
                }}>
                    <svg width="34" height="34" viewBox="0 0 34 34" fill="none">
                        <rect x="13" y="2" width="8" height="30" rx="4" fill="white" fillOpacity="0.95"/>
                        <rect x="2" y="13" width="30" height="8" rx="4" fill="white" fillOpacity="0.95"/>
                        <circle cx="17" cy="17" r="5" fill="white"/>
                    </svg>
                </div>

                <div style={{ color:"white", fontSize:28, fontWeight:800, letterSpacing:-0.5,
                    lineHeight:1.15, animation:"loginFade 0.5s ease 0.1s both", position:"relative", zIndex:1 }}>
                    MNCH Kenya
                </div>
                <div style={{ color:"rgba(255,255,255,0.55)", fontSize:16, fontWeight:600,
                    marginTop:3, letterSpacing:-0.2,
                    animation:"loginFade 0.5s ease 0.2s both", position:"relative", zIndex:1 }}>
                    Mentorship Platform
                </div>
                <div style={{ color:"rgba(255,255,255,0.35)", fontSize:12, marginTop:6,
                    letterSpacing:0.5, animation:"loginFade 0.5s ease 0.3s both",
                    position:"relative", zIndex:1 }}>
                    MINISTRY OF HEALTH
                </div>
            </div>

            {/* Form */}
            <div style={{ flex:1, padding:"28px 20px 24px", overflowY:"auto",
                animation:"loginFade 0.45s ease 0.25s both" }}>
                <div style={{ fontSize:22, fontWeight:800, color:T.text, marginBottom:4,
                    letterSpacing:-0.4 }}>Welcome back</div>
                <div style={{ fontSize:14, color:T.textMuted, marginBottom:28 }}>
                    Sign in to your assessor account
                </div>

                {error && (
                    <div style={{
                        background:"#FEF2F2", color:"#991B1B", borderRadius:T.radiusSm,
                        padding:"12px 16px", fontSize:13, marginBottom:18,
                        border:"1px solid #FECACA", display:"flex", alignItems:"center", gap:8,
                        animation:"loginFade 0.2s ease",
                    }}>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#EF4444" strokeWidth="2">
                            <circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>
                        </svg>
                        {error}
                    </div>
                )}

                {/* Email */}
                <div style={{ marginBottom:16 }}>
                    <div style={{ fontSize:12, fontWeight:700, color:T.textMid, marginBottom:7, letterSpacing:0.2 }}>
                        Email Address
                    </div>
                    <div style={{
                        position:"relative", borderRadius:T.radiusSm,
                        background:"white",
                        boxShadow: focused === "email"
                            ? `0 0 0 3px rgba(79,106,245,0.20), ${T.shadowCard}`
                            : T.shadowCard,
                        transition:"box-shadow 0.2s cubic-bezier(0.4,0,0.2,1)",
                        overflow:"hidden",
                    }}>
                        <span style={{ position:"absolute", left:14, top:"50%", transform:"translateY(-50%)",
                            display:"flex", alignItems:"center" }}>
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none"
                                stroke={focused === "email" ? T.primary : T.textMuted} strokeWidth="2" strokeLinecap="round">
                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                                <polyline points="22,6 12,13 2,6"/>
                            </svg>
                        </span>
                        <input type="email" value={email} placeholder="Enter your email"
                            onChange={e => setEmail(e.target.value)}
                            onFocus={() => setFocused("email")} onBlur={() => setFocused(null)}
                            onKeyDown={e => e.key === "Enter" && handleLogin()}
                            style={{ width:"100%", padding:"14px 16px 14px 44px",
                                border:"none", background:"transparent",
                                fontSize:14, color:T.text, outline:"none",
                                boxSizing:"border-box", fontFamily:"inherit" }} />
                    </div>
                </div>

                {/* Password */}
                <div style={{ marginBottom:20 }}>
                    <div style={{ fontSize:12, fontWeight:700, color:T.textMid, marginBottom:7, letterSpacing:0.2 }}>
                        Password
                    </div>
                    <div style={{
                        position:"relative", borderRadius:T.radiusSm,
                        background:"white",
                        boxShadow: focused === "password"
                            ? `0 0 0 3px rgba(79,106,245,0.20), ${T.shadowCard}`
                            : T.shadowCard,
                        transition:"box-shadow 0.2s cubic-bezier(0.4,0,0.2,1)",
                        overflow:"hidden",
                    }}>
                        <span style={{ position:"absolute", left:14, top:"50%", transform:"translateY(-50%)",
                            display:"flex", alignItems:"center" }}>
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none"
                                stroke={focused === "password" ? T.primary : T.textMuted} strokeWidth="2" strokeLinecap="round">
                                <rect x="3" y="11" width="18" height="11" rx="2"/>
                                <path d="M7 11V7a5 5 0 0110 0v4"/>
                            </svg>
                        </span>
                        <input type={showPassword ? "text" : "password"} value={password}
                            placeholder="Enter your password"
                            onChange={e => setPassword(e.target.value)}
                            onFocus={() => setFocused("password")} onBlur={() => setFocused(null)}
                            onKeyDown={e => e.key === "Enter" && handleLogin()}
                            style={{ width:"100%", padding:"14px 48px 14px 44px",
                                border:"none", background:"transparent",
                                fontSize:14, color:T.text, outline:"none",
                                boxSizing:"border-box", fontFamily:"inherit" }} />
                        <button type="button" onClick={() => setShowPassword(p => !p)} style={{
                            position:"absolute", right:4, top:"50%", transform:"translateY(-50%)",
                            width:38, height:38, borderRadius:10, border:"none",
                            background:"transparent", cursor:"pointer",
                            display:"flex", alignItems:"center", justifyContent:"center",
                        }}>
                            {showPassword
                                ? <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke={T.textMuted} strokeWidth="2" strokeLinecap="round"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/><path d="M14.12 14.12a3 3 0 11-4.24-4.24"/></svg>
                                : <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke={T.textMuted} strokeWidth="2" strokeLinecap="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            }
                        </button>
                    </div>
                </div>

                <button onClick={handleLogin} disabled={loading} style={{
                    width:"100%", padding:15, borderRadius:T.radiusSm, border:"none",
                    background: loading ? T.borderLight : T.gradientPrimary,
                    color: loading ? T.textMuted : "white",
                    fontSize:15, fontWeight:700,
                    cursor: loading ? "not-allowed" : "pointer",
                    transition:"all 0.3s cubic-bezier(0.4,0,0.2,1)",
                    boxShadow: loading ? "none" : `0 6px 20px ${T.primaryGlow}`,
                    position:"relative", overflow:"hidden", letterSpacing:0.2,
                }}>
                    {!loading && (
                        <div style={{
                            position:"absolute", top:0, left:"-100%",
                            width:"50%", height:"100%",
                            background:"linear-gradient(90deg,transparent,rgba(255,255,255,0.18),transparent)",
                            animation:"btnShine 3s infinite",
                        }}/>
                    )}
                    {loading ? (
                        <span style={{ display:"flex", alignItems:"center", justifyContent:"center", gap:8 }}>
                            <svg width="17" height="17" viewBox="0 0 24 24" style={{ animation:"spinCW 1s linear infinite" }}>
                                <circle cx="12" cy="12" r="10" fill="none" stroke="rgba(0,0,0,0.15)" strokeWidth="3"/>
                                <path d="M12 2a10 10 0 019.95 9" fill="none" stroke={T.primary} strokeWidth="3" strokeLinecap="round"/>
                            </svg>
                            Signing in…
                        </span>
                    ) : "Sign In"}
                </button>

                <div style={{ textAlign:"center", marginTop:16, fontSize:13, color:T.primary, fontWeight:600 }}>
                    Forgot password?
                </div>
            </div>
        </div>
    );
}
```

- [ ] **Step 2: Commit**

```bash
cd C:\xampp\htdocs\MNCH-Master
git add public/m-assessment-app/src/screens/screen-login.jsx
git commit -m "feat(ui): login screen — indigo hero, floating logo, clean form"
```

---

## Task 4 — Dashboard Screen (`screen-dashboard.jsx`)

**Files:**
- Modify: `src/screens/screen-dashboard.jsx`

- [ ] **Step 1: Update the hero section stat pills to glassmorphism style**

In `screen-dashboard.jsx`, find the stat pill `div` inside `/* Stats row */` (around line 446). The current style is:

```js
background: "rgba(255,255,255,0.06)",
backdropFilter: "blur(8px)",
borderRadius: 16, border: "1px solid rgba(255,255,255,0.1)",
```

This is already close. Update the outer hero `div` gradient and orb colours. Find the hero `div` (line ~403):

```js
background: T.gradientDark,
```

Stays as-is (now resolves to the indigo gradient via the token update). Find the two orb `div`s (lines ~411–412) and replace their `radial-gradient` colours:

```jsx
{/* Orb 1 */}
<div style={{ position: "absolute", width: 200, height: 200, borderRadius: "50%",
    background: "radial-gradient(circle, rgba(79,106,245,0.20) 0%, transparent 70%)",
    top: -60, right: -50, pointerEvents: "none" }} />
{/* Orb 2 */}
<div style={{ position: "absolute", width: 120, height: 120, borderRadius: "50%",
    background: "radial-gradient(circle, rgba(108,99,255,0.14) 0%, transparent 70%)",
    bottom: 0, left: -30, pointerEvents: "none" }} />
```

- [ ] **Step 2: Update the filter tab bar**

Find the filter tabs `div` (line ~532). Update the container `background` and `border`:

```js
background: "white", borderRadius: 14, padding: 4,
boxShadow: T.shadowCard, border: `1px solid ${T.border}`,
```

Active tab style (line ~548) — already uses `T.gradientPrimary`, so it picks up the new colour automatically. Just ensure `boxShadow` uses `T.primaryGlow`:

```js
boxShadow: filter === tab.key ? `0 4px 12px ${T.primaryGlow}` : "none",
```

- [ ] **Step 3: Update mentorship + training card icon backgrounds**

In the `My Mentorships` section (line ~684), the icon container uses `T.primaryGhost`. No change needed — resolves correctly now.

In the `Upcoming Trainings` section (line ~716), the icon uses `background: "rgba(14,165,233,0.08)"` (sky blue hardcode). Replace with:

```js
background: T.accentGhost,
```

- [ ] **Step 4: Commit**

```bash
cd C:\xampp\htdocs\MNCH-Master
git add public/m-assessment-app/src/screens/screen-dashboard.jsx
git commit -m "feat(ui): dashboard — indigo orbs, glassmorphism hero, updated tabs"
```

---

## Task 5 — Sweep Remaining Screen Heroes

**Files:** `screen-assessments-list.jsx`, `screen-mentorships-list.jsx`, `screen-mentorship-detail.jsx`, `screen-class-detail.jsx`, `screen-module-detail.jsx`, `screen-profile.jsx`, `screen-training-detail.jsx`

> Each file has a hero `div` using `T.gradientDark` (now correct via token) but with hardcoded teal/green orb `radial-gradient` colours. Replace them with indigo equivalents.

- [ ] **Step 1: `screen-assessments-list.jsx` — fix orb colour (line ~41)**

Find:
```js
background: "radial-gradient(circle, rgba(52,211,153,0.1) 0%, transparent 70%)"
```
Replace with:
```js
background: "radial-gradient(circle, rgba(79,106,245,0.20) 0%, transparent 70%)"
```

- [ ] **Step 2: `screen-mentorships-list.jsx` — fix orb colours**

Find any `radial-gradient` with `rgba(38,198,218`, `rgba(0,151,167`, `rgba(52,211,153`, or `rgba(16,185,129` in the hero section. Replace each with:
- Primary orb (large): `rgba(79,106,245,0.20)`
- Secondary orb (small): `rgba(108,99,255,0.14)`

- [ ] **Step 3: `screen-mentorship-detail.jsx` — fix orb colours**

Same pattern: replace any teal/green radial-gradient orbs in the hero section with:
- `rgba(79,106,245,0.20)` and `rgba(108,99,255,0.14)`

- [ ] **Step 4: `screen-class-detail.jsx` — fix orb colours**

Same pattern.

- [ ] **Step 5: `screen-module-detail.jsx` — fix orb colours**

Same pattern.

- [ ] **Step 6: `screen-profile.jsx` — fix orb colours**

Same pattern.

- [ ] **Step 7: `screen-training-detail.jsx` — fix orb colours**

Same pattern.

- [ ] **Step 8: Commit all screen sweeps**

```bash
cd C:\xampp\htdocs\MNCH-Master
git add public/m-assessment-app/src/screens/screen-assessments-list.jsx \
        public/m-assessment-app/src/screens/screen-mentorships-list.jsx \
        public/m-assessment-app/src/screens/screen-mentorship-detail.jsx \
        public/m-assessment-app/src/screens/screen-class-detail.jsx \
        public/m-assessment-app/src/screens/screen-module-detail.jsx \
        public/m-assessment-app/src/screens/screen-profile.jsx \
        public/m-assessment-app/src/screens/screen-training-detail.jsx
git commit -m "feat(ui): sweep screen heroes — indigo orbs replace teal"
```

---

## Task 6 — App Loading Screen (`App.jsx`)

**Files:**
- Modify: `src/App.jsx`

- [ ] **Step 1: Update the loading spinner**

Find the loading return (around line 60):

```jsx
if (loading) {
    return (
        <div style={{ display: "flex", alignItems: "center", justifyContent: "center", height: "100vh", background: T.bg }}>
            <div style={{ color: T.textSub, fontSize: 14 }}>Loading…</div>
        </div>
    );
}
```

Replace with:

```jsx
if (loading) {
    return (
        <div style={{
            display: "flex", flexDirection: "column",
            alignItems: "center", justifyContent: "center",
            height: "100vh", background: T.bg,
            fontFamily: "-apple-system, 'SF Pro Display', 'Segoe UI', system-ui, sans-serif",
            gap: 16,
        }}>
            <div style={{
                width: 52, height: 52, borderRadius: 16,
                background: T.gradientPrimary,
                display: "flex", alignItems: "center", justifyContent: "center",
                boxShadow: `0 8px 24px ${T.primaryGlow}`,
            }}>
                <svg width="26" height="26" viewBox="0 0 24 24" style={{ animation: "spin 1s linear infinite" }}>
                    <circle cx="12" cy="12" r="10" fill="none" stroke="rgba(255,255,255,0.25)" strokeWidth="3"/>
                    <path d="M12 2a10 10 0 019.95 9" fill="none" stroke="white" strokeWidth="3" strokeLinecap="round"/>
                </svg>
            </div>
            <div style={{ color: T.textMuted, fontSize: 13, fontWeight: 500 }}>Loading…</div>
        </div>
    );
}
```

- [ ] **Step 2: Commit**

```bash
cd C:\xampp\htdocs\MNCH-Master
git add public/m-assessment-app/src/App.jsx
git commit -m "feat(ui): App loading screen — indigo spinner"
```

---

## Task 7 — Build + Verify

**Files:** none modified

- [ ] **Step 1: Run the production build**

```bash
cd C:\xampp\htdocs\MNCH-Master\public\m-assessment-app
npm run build
```

Expected output:
```
✓ built in ~1s
dist/index.html  ...
dist/assets/index-*.js  ...
PWA v1.3.0  generateSW  precache N entries
```

No new errors. The existing chunk-size warning about `api.service.js` is benign — ignore it.

- [ ] **Step 2: Spot-check — open browser DevTools**

Open the built `dist/index.html` (or the local dev server with `npm run dev`). Check:
- **Network tab:** No request to `fonts.googleapis.com` — font is now system stack
- **Console:** No JS errors on login screen, dashboard, or any list screen
- **Visual:** Hero panels are deep navy→indigo, not teal. Bottom nav active tab shows indigo pill.

- [ ] **Step 3: Touch dist files for FTP pickup**

```powershell
Get-ChildItem "C:\xampp\htdocs\MNCH-Master\public\m-assessment-app\dist" -Recurse -File |
    ForEach-Object { $_.LastWriteTime = Get-Date }
```

- [ ] **Step 4: Final commit**

```bash
cd C:\xampp\htdocs\MNCH-Master
git add public/m-assessment-app/dist
git commit -m "feat(ui): production build — iOS Minimal Indigo Sapphire redesign"
```
