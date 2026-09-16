# Mentor & Mentee Dashboards Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement three screen changes — enrich MentorshipCard with stats, create the new MentorshipOverviewScreen (mentor landing after tapping a card), and fully redesign MyClassesScreen (mentee inline module progress).

**Architecture:** All navigation lives in `MentorshipsScope.jsx` as a flat modal state machine. The list's `onOpen` callback currently opens `mentorshipDetail`; we reroute it through a new `mentorshipOverview` modal type. `MyClassesScreen` gets a new `onModuleDetail` prop that the scope wires to the existing `moduleDetail` modal type.

**Tech Stack:** React 18, Vite, Capacitor. Design tokens in `T` from `src/constants.js`. API via `api` default export from `src/services/api.service.js`.

---

## File Map

| File | Change |
|------|--------|
| `src/screens/screen-mentorships-list.jsx` | Add stat row (Mentees · Classes · Progress%) + progress bar to `MentorshipCard` |
| `src/screens/screen-mentorship-overview.jsx` | **Create** — hero, action strip, lazy class-mentee expand |
| `src/screens/screen-my-classes.jsx` | **Rewrite** — hero stat pills, inline module list, offline banner |
| `src/scopes/MentorshipsScope.jsx` | Add `mentorshipOverview` modal case; reroute list `onOpen`; pass `onModuleDetail` to MyClassesScreen |

---

## Task 1: Enrich MentorshipCard with stat row + progress bar

**Files:**
- Modify: `src/screens/screen-mentorships-list.jsx`

Context: `MentorshipCard` (lines 19–101) already renders status badge, title, meta row (facility, mentor, dates, class count). The spec adds a stat row showing Mentees · Classes · Progress % and a 5px progress bar underneath.

Fields available on mentorship list items: `m.participant_count` (mentee count), `m.class_count`, `m.progress_percentage`.

- [ ] **Step 1: Add stat row and progress bar inside MentorshipCard**

In `screen-mentorships-list.jsx`, replace the closing `</div>` of the card's inner `padding: "14px 16px"` div so it includes a stat row and progress bar. The change is inside `MentorshipCard`, after the existing meta row (`display: "flex", flexWrap: "wrap"` div ending at line ~97).

Replace the bottom of the `MentorshipCard` return (after the meta row div) with:

```jsx
                {/* Stat row */}
                <div style={{ display: "flex", gap: 16, marginTop: 10, fontSize: 12 }}>
                    <span style={{ color: T.textMuted, display: "flex", alignItems: "center", gap: 3 }}>
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
                        <span style={{ fontWeight: 600, color: T.textSub }}>{m.participant_count ?? 0}</span> mentees
                    </span>
                    <span style={{ color: T.textMuted, display: "flex", alignItems: "center", gap: 3 }}>
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        <span style={{ fontWeight: 600, color: T.textSub }}>{m.class_count ?? 0}</span> classes
                    </span>
                    <span style={{ marginLeft: "auto", fontWeight: 700, fontSize: 11,
                        color: (m.progress_percentage ?? 0) >= 60 ? T.success : T.primary }}>
                        {Math.round(m.progress_percentage ?? 0)}%
                    </span>
                </div>
                {/* Progress bar */}
                <div style={{ marginTop: 7, height: 5, borderRadius: 6, background: T.borderLight, overflow: "hidden" }}>
                    <div style={{
                        height: "100%",
                        width: Math.min(100, Math.round(m.progress_percentage ?? 0)) + "%",
                        background: T.gradientPrimary,
                        borderRadius: 6,
                        transition: "width 0.5s ease",
                    }} />
                </div>
```

- [ ] **Step 2: Verify the build compiles**

```bash
cd C:\xampp\htdocs\MNCH-Master\public\m-assessment-app
npm run build 2>&1 | tail -20
```
Expected: no errors, `dist/` updated.

- [ ] **Step 3: Commit**

```bash
git add public/m-assessment-app/src/screens/screen-mentorships-list.jsx
git commit -m "feat(ui): mentorship card — stat row (mentees/classes/progress) + progress bar"
```

---

## Task 2: Create screen-mentorship-overview.jsx

**Files:**
- Create: `src/screens/screen-mentorship-overview.jsx`

This new screen is the landing page a mentor sees after tapping a mentorship card. It shows: hero (title, dates, facility, stat pills), action strip (View Detail & Reports / Edit buttons), and a collapsible class list where each class lazily loads its mentees on first expand.

API calls used:
- `api.mentorships.find(mentorshipId)` → `GET /mentorships/:id` — hero data; resolves `data?.data ?? data`
- `api.mentorships.classes(mentorshipId)` → `GET /mentorships/:id/classes` — class list; resolves `Array.isArray(d?.data) ? d.data : []`
- `api.participants.list(classId)` → `GET /classes/:classId/participants` — lazy mentees per class; resolves `Array.isArray(d?.data) ? d.data : []`

Avatar gradient is derived from name: `AVATAR_GRADIENTS[name.charCodeAt(0) % AVATAR_GRADIENTS.length]`.

- [ ] **Step 1: Create the file**

Create `src/screens/screen-mentorship-overview.jsx` with the following content:

```jsx
import { useState, useEffect } from "react";
import { T } from "../constants.js";
import api from "../services/api.service.js";

const AVATAR_GRADIENTS = [
    "linear-gradient(135deg, #4F6AF5 0%, #6C63FF 100%)",
    "linear-gradient(135deg, #10B981 0%, #34D399 100%)",
    "linear-gradient(135deg, #F59E0B 0%, #FBBF24 100%)",
    "linear-gradient(135deg, #EF4444 0%, #F97316 100%)",
    "linear-gradient(135deg, #8B5CF6 0%, #A78BFA 100%)",
    "linear-gradient(135deg, #06B6D4 0%, #38BDF8 100%)",
];

function avatarGradient(name) {
    if (!name) return AVATAR_GRADIENTS[0];
    return AVATAR_GRADIENTS[(name.charCodeAt(0) ?? 0) % AVATAR_GRADIENTS.length];
}

const STATUS_MAP = {
    active:    { bg: "#D1FAE5", color: "#065F46" },
    draft:     { bg: "#FEF3C7", color: "#92400E" },
    completed: { bg: "#F3F4F6", color: "#6B7280" },
    cancelled: { bg: "#FEE2E2", color: "#991B1B" },
};

function StatusBadge({ status }) {
    const s = STATUS_MAP[status] ?? STATUS_MAP.draft;
    return (
        <span style={{ fontSize: 10, fontWeight: 700, padding: "3px 8px", borderRadius: 20,
            background: s.bg, color: s.color, textTransform: "capitalize" }}>
            {status}
        </span>
    );
}

function ClassCard({ cls, onViewMentees, menteeCache, onExpand, isExpanded }) {
    const mentees      = menteeCache[cls.id];
    const loadingMentees = mentees === undefined && isExpanded;

    return (
        <div style={{ background: T.card, borderRadius: T.radiusSm, boxShadow: T.shadowCard,
            border: `1px solid ${T.border}`, overflow: "hidden", marginBottom: 10 }}>
            {/* Header row — always visible, tap to expand */}
            <button
                onClick={() => onExpand(cls.id)}
                style={{ width: "100%", background: "none", border: "none", cursor: "pointer",
                    padding: "10px 12px", textAlign: "left", display: "flex",
                    alignItems: "center", gap: 8 }}
            >
                <div style={{ flex: 1 }}>
                    <div style={{ fontSize: 12, fontWeight: 700, color: T.text, marginBottom: 2 }}>
                        {cls.name ?? "Class"}
                    </div>
                    <div style={{ fontSize: 10, color: T.textMuted }}>
                        {cls.participant_count ?? 0} mentees
                        {cls.progress_percentage != null ? ` · ${Math.round(cls.progress_percentage)}%` : ""}
                    </div>
                </div>
                <StatusBadge status={cls.status ?? "draft"} />
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke={T.textMuted} strokeWidth="2.5"
                    strokeLinecap="round"
                    style={{ transform: isExpanded ? "rotate(180deg)" : "rotate(0)", transition: "transform 0.2s" }}>
                    <polyline points="6 9 12 15 18 9" />
                </svg>
            </button>

            {/* Expanded mentees */}
            {isExpanded && (
                <div style={{ borderTop: `1px solid ${T.border}`, padding: "8px 0" }}>
                    {loadingMentees && (
                        <div style={{ padding: "10px 12px", color: T.textMuted, fontSize: 12 }}>Loading…</div>
                    )}
                    {mentees && mentees.length === 0 && (
                        <div style={{ padding: "10px 12px", color: T.textMuted, fontSize: 12 }}>No mentees yet</div>
                    )}
                    {(mentees ?? []).slice(0, 3).map(p => {
                        const name      = p.name ?? p.user_name ?? "Mentee";
                        const cadre     = p.cadre ?? p.cadre_name ?? "";
                        const modulesDone   = p.modules_completed ?? 0;
                        const modulesTotal  = p.modules_total ?? p.module_count ?? 0;
                        const pct       = modulesTotal > 0 ? Math.round(modulesDone / modulesTotal * 100) : 0;
                        return (
                            <div key={p.id ?? p.participant_id} style={{ display: "flex", alignItems: "center",
                                gap: 8, padding: "7px 12px" }}>
                                {/* Avatar */}
                                <div style={{ width: 28, height: 28, borderRadius: 10, flexShrink: 0,
                                    background: avatarGradient(name),
                                    display: "flex", alignItems: "center", justifyContent: "center",
                                    color: "white", fontSize: 11, fontWeight: 700 }}>
                                    {name.slice(0, 2).toUpperCase()}
                                </div>
                                <div style={{ flex: 1, minWidth: 0 }}>
                                    <div style={{ fontSize: 11, fontWeight: 600, color: T.text,
                                        whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis" }}>
                                        {name}
                                    </div>
                                    {cadre && <div style={{ fontSize: 9, color: T.textMuted }}>{cadre}</div>}
                                </div>
                                {/* Mini progress */}
                                <div style={{ display: "flex", flexDirection: "column", alignItems: "flex-end", gap: 3 }}>
                                    <div style={{ fontSize: 9, color: T.textMuted }}>
                                        {modulesDone}/{modulesTotal} modules
                                    </div>
                                    <div style={{ width: 50, height: 4, borderRadius: 4, background: T.borderLight, overflow: "hidden" }}>
                                        <div style={{ height: "100%", width: pct + "%", background: T.gradientPrimary }} />
                                    </div>
                                </div>
                            </div>
                        );
                    })}
                    {mentees && mentees.length > 3 && (
                        <button
                            onClick={() => onViewMentees(cls)}
                            style={{ width: "100%", padding: "8px 12px", background: "none", border: "none",
                                cursor: "pointer", textAlign: "left", fontSize: 11, fontWeight: 700,
                                color: T.primary }}>
                            +{mentees.length - 3} more →
                        </button>
                    )}
                </div>
            )}
        </div>
    );
}

export function MentorshipOverviewScreen({ mentorshipId, onBack, onViewDetail, onEdit, onViewMentees }) {
    const [mentorship, setMentorship]   = useState(null);
    const [classes, setClasses]         = useState([]);
    const [loading, setLoading]         = useState(true);
    const [expanded, setExpanded]       = useState({});   // classId → bool
    const [menteeCache, setMenteeCache] = useState({});   // classId → participants[]

    useEffect(() => {
        Promise.all([
            api.mentorships.find(mentorshipId),
            api.mentorships.classes(mentorshipId),
        ]).then(([mData, cData]) => {
            const m  = mData?.data ?? mData;
            const cs = Array.isArray(cData?.data) ? cData.data : [];
            setMentorship(m);
            setClasses(cs);
            // Auto-expand first class
            if (cs.length > 0) {
                setExpanded({ [cs[0].id]: true });
            }
        }).catch(() => {}).finally(() => setLoading(false));
    }, [mentorshipId]);

    // Lazy load mentees for a class when it's expanded for the first time
    useEffect(() => {
        for (const [classId, isOpen] of Object.entries(expanded)) {
            if (isOpen && menteeCache[classId] === undefined) {
                // Mark as loading (null) before fetching
                setMenteeCache(prev => ({ ...prev, [classId]: null }));
                api.participants.list(classId)
                    .then(d => {
                        const arr = Array.isArray(d?.data) ? d.data : [];
                        setMenteeCache(prev => ({ ...prev, [classId]: arr }));
                    })
                    .catch(() => {
                        setMenteeCache(prev => ({ ...prev, [classId]: [] }));
                    });
            }
        }
    }, [expanded]);

    function toggleExpand(classId) {
        setExpanded(prev => ({ ...prev, [classId]: !prev[classId] }));
    }

    const m = mentorship;
    const totalMentees  = classes.reduce((s, c) => s + (c.participant_count ?? 0), 0);
    const avgPct        = classes.length > 0
        ? Math.round(classes.reduce((s, c) => s + (c.progress_percentage ?? 0), 0) / classes.length)
        : 0;

    return (
        <div style={{ height: "100%", overflowY: "auto", background: T.bg }}>
            {/* Top spacer */}
            <div style={{ height: 6, background: T.bg }} />

            {/* Hero */}
            <div style={{ background: T.gradientHero, padding: "20px 20px 22px",
                borderRadius: "0 0 28px 28px", margin: "0 6px", position: "relative", overflow: "hidden" }}>
                <div style={{ position: "absolute", width: 180, height: 180, borderRadius: "50%",
                    background: "radial-gradient(circle, rgba(79,106,245,0.20) 0%, transparent 70%)",
                    top: -50, right: -50 }} />
                <div style={{ position: "absolute", width: 100, height: 100, borderRadius: "50%",
                    background: "radial-gradient(circle, rgba(108,99,255,0.14) 0%, transparent 70%)",
                    bottom: 0, left: -20 }} />

                {/* Back button */}
                <button onClick={onBack} style={{ background: "rgba(255,255,255,0.12)",
                    border: "1px solid rgba(255,255,255,0.18)", borderRadius: 10, padding: "6px 12px",
                    color: "white", fontWeight: 600, fontSize: 12, cursor: "pointer",
                    display: "flex", alignItems: "center", gap: 5, marginBottom: 14 }}>
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="2.5" strokeLinecap="round">
                        <polyline points="15 18 9 12 15 6" />
                    </svg>
                    Back
                </button>

                <div style={{ color: "rgba(255,255,255,0.5)", fontSize: 10, fontWeight: 700,
                    letterSpacing: 1.5, marginBottom: 4 }}>MENTORSHIP</div>
                <div style={{ color: "white", fontWeight: 800, fontSize: 18, lineHeight: 1.3, marginBottom: 4 }}>
                    {loading ? "Loading…" : (m?.title ?? "Mentorship")}
                </div>
                {!loading && m && (
                    <div style={{ color: "rgba(255,255,255,0.55)", fontSize: 11, marginBottom: 14 }}>
                        {[m.start_date, m.end_date].filter(Boolean).join(" – ")}
                        {m.facility ? ` · ${m.facility}` : ""}
                    </div>
                )}

                {/* Stat pills */}
                {!loading && (
                    <div style={{ display: "flex", gap: 8 }}>
                        {[
                            { label: "Mentees",  value: totalMentees },
                            { label: "Classes",  value: classes.length },
                            { label: "Progress", value: avgPct + "%", highlight: avgPct >= 60 },
                            { label: "Status",   value: m?.status ?? "—", isStatus: true },
                        ].map(pill => {
                            const s = STATUS_MAP[m?.status] ?? STATUS_MAP.draft;
                            return (
                                <div key={pill.label} style={{
                                    flex: 1, padding: "8px 4px", borderRadius: 12, textAlign: "center",
                                    background: pill.isStatus ? s.bg + "22" : "rgba(255,255,255,0.08)",
                                    border: "1px solid rgba(255,255,255,0.12)",
                                    backdropFilter: "blur(8px)",
                                }}>
                                    <div style={{ color: pill.highlight ? T.success : "white",
                                        fontSize: 14, fontWeight: 800, lineHeight: 1 }}>
                                        {pill.value}
                                    </div>
                                    <div style={{ color: "rgba(255,255,255,0.5)", fontSize: 9,
                                        fontWeight: 600, marginTop: 3 }}>{pill.label}</div>
                                </div>
                            );
                        })}
                    </div>
                )}
            </div>

            {/* Action strip */}
            <div style={{ margin: "12px 16px 0", display: "flex", gap: 10 }}>
                <button
                    onClick={() => onViewDetail(mentorshipId)}
                    style={{ flex: 1, padding: "12px 0", background: T.gradientPrimary,
                        color: "white", border: "none", borderRadius: T.radiusSm,
                        fontWeight: 700, fontSize: 13, cursor: "pointer",
                        boxShadow: `0 4px 16px ${T.primaryGlow}` }}>
                    View Detail & Reports →
                </button>
                <button
                    onClick={() => onEdit(mentorshipId)}
                    style={{ padding: "12px 18px", background: T.primaryGhost,
                        color: T.primary, border: `1px solid ${T.primary}33`,
                        borderRadius: T.radiusSm, fontWeight: 700, fontSize: 13, cursor: "pointer" }}>
                    Edit
                </button>
            </div>

            {/* Classes section */}
            <div style={{ padding: "16px 16px 80px" }}>
                <div style={{ fontSize: 11, fontWeight: 700, color: T.textMuted, letterSpacing: 1,
                    textTransform: "uppercase", marginBottom: 10 }}>
                    Mentees by Class
                </div>
                {loading && <div style={{ color: T.textMuted, fontSize: 13 }}>Loading classes…</div>}
                {!loading && classes.length === 0 && (
                    <div style={{ color: T.textMuted, fontSize: 13, textAlign: "center", paddingTop: 20 }}>
                        No classes yet
                    </div>
                )}
                {classes.map(cls => (
                    <ClassCard
                        key={cls.id}
                        cls={cls}
                        onViewMentees={onViewMentees}
                        menteeCache={menteeCache}
                        onExpand={toggleExpand}
                        isExpanded={!!expanded[cls.id]}
                    />
                ))}
            </div>
        </div>
    );
}
```

- [ ] **Step 2: Verify the build compiles**

```bash
cd C:\xampp\htdocs\MNCH-Master\public\m-assessment-app
npm run build 2>&1 | tail -20
```
Expected: no errors.

- [ ] **Step 3: Commit**

```bash
git add public/m-assessment-app/src/screens/screen-mentorship-overview.jsx
git commit -m "feat(ui): mentorship overview screen — hero, action strip, lazy class-mentee expand"
```

---

## Task 3: Rewrite screen-my-classes.jsx

**Files:**
- Modify: `src/screens/screen-my-classes.jsx`

The existing file (57 lines) is a bare list. Replace it entirely with the redesigned version: hero with stat pills, inline module list per class with status icons, tappable module rows, and an offline banner.

API calls:
- `api.me.classes()` → resolves `Array.isArray(d?.data) ? d.data : []` — list of classes; expects each class to have `modules` array nested; if not nested, fall back to `api.me.classDetail(c.id)` to load modules.

Module status icons:
- Completed (`m.status === "completed"` or `m.attended === true`): `T.gradientSuccess` bg, white ✓
- In progress (`m.status === "in_progress"` or `m.status === "active"`): `T.gradientPrimary` bg, white →
- Not started (otherwise): `#E0E0E8` bg, no icon

Overall hero % = `completedModules / totalModules * 100` across all classes.

- [ ] **Step 1: Rewrite the file**

Replace the full content of `src/screens/screen-my-classes.jsx`:

```jsx
import { useState, useEffect, useMemo } from "react";
import { T } from "../constants.js";
import api from "../services/api.service.js";

function moduleStatusIcon(mod) {
    const s = mod.status ?? "";
    if (s === "completed" || mod.attended === true) {
        return { bg: T.gradientSuccess, icon: "✓", textColor: T.success };
    }
    if (s === "in_progress" || s === "active") {
        return { bg: T.gradientPrimary, icon: "→", textColor: T.primary };
    }
    return { bg: "#E0E0E8", icon: null, textColor: T.textMuted };
}

function ClassCard({ cls, onModuleDetail }) {
    const modules        = cls.modules ?? [];
    const completedCount = modules.filter(m => m.status === "completed" || m.attended === true).length;
    const pct            = modules.length > 0 ? Math.round(completedCount / modules.length * 100) : 0;

    return (
        <div style={{ background: T.card, borderRadius: T.radius, boxShadow: T.shadowCard,
            border: `1px solid ${T.border}`, overflow: "hidden" }}>
            {/* Card header */}
            <div style={{ padding: "14px 16px 10px", borderBottom: modules.length > 0 ? `1px solid ${T.border}` : "none" }}>
                <div style={{ display: "flex", alignItems: "flex-start", justifyContent: "space-between", gap: 8, marginBottom: 6 }}>
                    <div style={{ fontSize: 14, fontWeight: 700, color: T.text, lineHeight: 1.3 }}>
                        {cls.name ?? cls.title ?? "Class"}
                    </div>
                    <span style={{ fontSize: 10, color: T.textMuted, fontWeight: 600, flexShrink: 0, marginTop: 2 }}>
                        {completedCount}/{modules.length} modules
                    </span>
                </div>
                {(cls.facility || cls.mentor_name) && (
                    <div style={{ fontSize: 11, color: T.textMuted, marginBottom: 8 }}>
                        {[cls.facility, cls.mentor_name ? `Mentor: ${cls.mentor_name}` : null]
                            .filter(Boolean).join(" · ")}
                    </div>
                )}
                <div style={{ height: 5, borderRadius: 6, background: T.borderLight, overflow: "hidden" }}>
                    <div style={{ height: "100%", width: pct + "%", background: T.gradientPrimary,
                        borderRadius: 6, transition: "width 0.4s ease" }} />
                </div>
            </div>

            {/* Inline module list */}
            {modules.length > 0 && (
                <div style={{ padding: "4px 0 6px" }}>
                    {modules.map((mod, idx) => {
                        const icon = moduleStatusIcon(mod);
                        const isInProgress = (mod.status === "in_progress" || mod.status === "active");
                        const isComplete   = (mod.status === "completed" || mod.attended === true);
                        const nameColor    = isInProgress ? T.primary : isComplete ? T.text : T.textMuted;

                        const sessionLabel = (() => {
                            if (mod.sessions_attended != null && mod.session_count != null) {
                                return mod.sessions_attended === mod.session_count
                                    ? `${mod.session_count} sessions · Attended all`
                                    : `${mod.sessions_attended} of ${mod.session_count} sessions`;
                            }
                            if (isComplete) return "Completed";
                            if (isInProgress) return "In progress";
                            return "Not started";
                        })();

                        return (
                            <button
                                key={mod.id ?? idx}
                                onClick={() => onModuleDetail?.(mod, cls)}
                                style={{ width: "100%", background: "none", border: "none",
                                    cursor: onModuleDetail ? "pointer" : "default",
                                    display: "flex", alignItems: "center", gap: 10,
                                    padding: "8px 16px", textAlign: "left" }}
                            >
                                {/* Status icon */}
                                <div style={{ width: 28, height: 28, borderRadius: 8, flexShrink: 0,
                                    background: icon.bg,
                                    display: "flex", alignItems: "center", justifyContent: "center",
                                    color: "white", fontSize: 12, fontWeight: 700 }}>
                                    {icon.icon}
                                </div>
                                {/* Name + session label */}
                                <div style={{ flex: 1, minWidth: 0 }}>
                                    <div style={{ fontSize: 11, fontWeight: 700, color: nameColor,
                                        whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis" }}>
                                        {mod.name ?? mod.title ?? "Module"}
                                    </div>
                                    <div style={{ fontSize: 9, color: T.textMuted, marginTop: 1 }}>
                                        {sessionLabel}
                                    </div>
                                </div>
                                {onModuleDetail && (
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none"
                                        stroke={T.textMuted} strokeWidth="2" strokeLinecap="round">
                                        <polyline points="9 18 15 12 9 6" />
                                    </svg>
                                )}
                            </button>
                        );
                    })}
                </div>
            )}

            {modules.length === 0 && (
                <div style={{ padding: "10px 16px", fontSize: 12, color: T.textMuted }}>
                    No modules yet
                </div>
            )}
        </div>
    );
}

export function MyClassesScreen({ user, onModuleDetail }) {
    const [classes, setClasses] = useState(null);
    const [loading, setLoading] = useState(true);
    const [isOffline, setIsOffline]   = useState(false);

    useEffect(() => {
        api.me.classes()
            .then(async d => {
                let arr = Array.isArray(d?.data) ? d.data : (Array.isArray(d) ? d : []);
                setIsOffline(!!(d?._fromCache));

                // If modules are not nested, fetch per class
                const needsModules = arr.some(c => !Array.isArray(c.modules));
                if (needsModules && navigator.onLine) {
                    arr = await Promise.all(arr.map(async c => {
                        if (Array.isArray(c.modules)) return c;
                        try {
                            const det = await api.me.classDetail(c.id);
                            const detail = det?.data ?? det;
                            return { ...c, modules: detail?.modules ?? [] };
                        } catch {
                            return { ...c, modules: [] };
                        }
                    }));
                } else {
                    arr = arr.map(c => ({ ...c, modules: c.modules ?? [] }));
                }
                setClasses(arr);
            })
            .catch(() => setClasses([]))
            .finally(() => setLoading(false));
    }, []);

    const stats = useMemo(() => {
        if (!classes) return { enrolled: 0, totalModules: 0, completedModules: 0, overallPct: 0 };
        let totalModules = 0, completedModules = 0;
        for (const c of classes) {
            totalModules    += (c.modules ?? []).length;
            completedModules += (c.modules ?? []).filter(m => m.status === "completed" || m.attended === true).length;
        }
        return {
            enrolled: classes.length,
            totalModules,
            completedModules,
            overallPct: totalModules > 0 ? Math.round(completedModules / totalModules * 100) : 0,
        };
    }, [classes]);

    return (
        <div style={{ height: "100%", overflowY: "auto", background: T.bg }}>
            <div style={{ height: 6, background: T.bg }} />

            {/* Hero */}
            <div style={{ background: T.gradientHero, padding: "24px 20px 22px",
                borderRadius: "0 0 28px 28px", margin: "0 6px",
                position: "relative", overflow: "hidden" }}>
                <div style={{ position: "absolute", width: 180, height: 180, borderRadius: "50%",
                    background: "radial-gradient(circle, rgba(79,106,245,0.20) 0%, transparent 70%)",
                    top: -50, right: -50 }} />
                <div style={{ position: "absolute", width: 100, height: 100, borderRadius: "50%",
                    background: "radial-gradient(circle, rgba(108,99,255,0.14) 0%, transparent 70%)",
                    bottom: 0, left: -20 }} />

                <div style={{ color: "rgba(255,255,255,0.5)", fontSize: 10, fontWeight: 700,
                    letterSpacing: 1.5, marginBottom: 4 }}>MY CLASSES</div>
                <div style={{ color: "white", fontWeight: 800, fontSize: 22, marginBottom: 16 }}>
                    {user?.name?.split(" ")[0] ?? "Classes"}
                </div>

                {/* Stat pills */}
                <div style={{ display: "flex", gap: 8 }}>
                    {[
                        { label: "Enrolled",  value: stats.enrolled },
                        { label: "Modules",   value: stats.totalModules },
                        { label: "% Done",    value: stats.overallPct + "%" },
                    ].map(pill => (
                        <div key={pill.label} style={{ flex: 1, padding: "10px 8px", borderRadius: 14,
                            textAlign: "center", background: "rgba(255,255,255,0.08)",
                            border: "1px solid rgba(255,255,255,0.12)", backdropFilter: "blur(6px)" }}>
                            <div style={{ color: "white", fontSize: 18, fontWeight: 800, lineHeight: 1 }}>
                                {loading ? "—" : pill.value}
                            </div>
                            <div style={{ color: "rgba(255,255,255,0.55)", fontSize: 10, fontWeight: 600, marginTop: 3 }}>
                                {pill.label}
                            </div>
                        </div>
                    ))}
                </div>
            </div>

            {/* Offline banner */}
            {isOffline && (
                <div style={{ margin: "10px 16px 0", padding: "8px 12px", borderRadius: T.radiusSm,
                    background: T.gradientWarm, color: "white", fontSize: 12, fontWeight: 600,
                    display: "flex", alignItems: "center", gap: 6 }}>
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="2.5" strokeLinecap="round">
                        <path d="M1 1l22 22M16.72 11.06A10.94 10.94 0 0119 12.55M5 12.55a10.94 10.94 0 015.17-2.39M10.71 5.05A16 16 0 0122.56 9M1.42 9a15.91 15.91 0 014.7-2.88M8.53 16.11a6 6 0 016.95 0M12 20h.01"/>
                    </svg>
                    Viewing offline data
                </div>
            )}

            {/* Class cards */}
            <div style={{ padding: "12px 16px 80px", display: "flex", flexDirection: "column", gap: 12 }}>
                {loading && (
                    <div style={{ color: T.textMuted, textAlign: "center", paddingTop: 40, fontSize: 13 }}>
                        Loading your classes…
                    </div>
                )}
                {!loading && classes?.length === 0 && (
                    <div style={{ textAlign: "center", paddingTop: 60 }}>
                        <div style={{ width: 56, height: 56, borderRadius: 18, background: T.primaryGhost,
                            display: "flex", alignItems: "center", justifyContent: "center", margin: "0 auto 12px" }}>
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke={T.primary}
                                strokeWidth="1.8" strokeLinecap="round">
                                <path d="M2 3h6a4 4 0 014 4v14a3 3 0 00-3-3H2z" />
                                <path d="M22 3h-6a4 4 0 00-4 4v14a3 3 0 013-3h7z" />
                            </svg>
                        </div>
                        <div style={{ color: T.text, fontWeight: 700, fontSize: 16, marginBottom: 6 }}>No classes yet</div>
                        <div style={{ color: T.textMuted, fontSize: 13 }}>
                            Ask your mentor for an enrollment link.
                        </div>
                    </div>
                )}
                {(classes ?? []).map(cls => (
                    <ClassCard key={cls.id} cls={cls} onModuleDetail={onModuleDetail} />
                ))}
            </div>
        </div>
    );
}
```

- [ ] **Step 2: Verify the build compiles**

```bash
cd C:\xampp\htdocs\MNCH-Master\public\m-assessment-app
npm run build 2>&1 | tail -20
```
Expected: no errors.

- [ ] **Step 3: Commit**

```bash
git add public/m-assessment-app/src/screens/screen-my-classes.jsx
git commit -m "feat(ui): my classes screen — hero stats, inline module list, offline banner"
```

---

## Task 4: Wire MentorshipsScope.jsx

**Files:**
- Modify: `src/scopes/MentorshipsScope.jsx`

Four changes needed:

1. **Import** `MentorshipOverviewScreen` (add after existing imports)
2. **Add `mentorshipOverview` modal case** — renders `MentorshipOverviewScreen`, bridges to `mentorshipDetail` and `mentorshipEdit` and `menteeManager`
3. **Change list's `onOpen`** — currently opens `mentorshipDetail`; change to open `mentorshipOverview`
4. **Pass `onModuleDetail` to `MyClassesScreen`** — wires tap to existing `moduleDetail` modal type; back from module goes back to `my-classes` tab

- [ ] **Step 1: Add the import**

In `src/scopes/MentorshipsScope.jsx`, add after the existing imports (after `ClassProgressScreen` import):

```jsx
import { MentorshipOverviewScreen } from '../screens/screen-mentorship-overview.jsx';
```

- [ ] **Step 2: Add the `mentorshipOverview` modal case**

In `MentorshipsScope`, add this block immediately before the `if (modal?.type === 'mentorshipDetail')` block (which is currently the first if-chain at line 329):

```jsx
    if (modal?.type === 'mentorshipOverview') return (
        <MentorshipOverviewScreen
            mentorshipId={modal.data?.id ?? modal.data}
            onBack={() => setModal(null)}
            onViewDetail={(id) => setModal({ type: 'mentorshipDetail', data: modal.data })}
            onEdit={(id) => setModal({ type: 'mentorshipEdit', data: modal.data })}
            onViewMentees={(cls) => setModal({ type: 'menteeManager', data: cls, prev: modal.data })}
        />
    );
```

- [ ] **Step 3: Reroute list `onOpen` to `mentorshipOverview`**

In `MentorshipsScope`, find the line in the tab render section:

```jsx
{tab === 'mentorships' && <MentorshipsListScreen user={user} onOpen={(t) => setModal({ type: 'mentorshipDetail', data: t })} onNew={() => setModal({ type: 'mentorshipForm', data: null })} onEdit={(t) => setModal({ type: 'mentorshipEdit', data: t })} />}
```

Change `mentorshipDetail` to `mentorshipOverview`:

```jsx
{tab === 'mentorships' && <MentorshipsListScreen user={user} onOpen={(t) => setModal({ type: 'mentorshipOverview', data: t })} onNew={() => setModal({ type: 'mentorshipForm', data: null })} onEdit={(t) => setModal({ type: 'mentorshipEdit', data: t })} />}
```

- [ ] **Step 4: Add `onModuleDetail` to MyClassesScreen call**

In `MentorshipsScope`, find the line:

```jsx
{tab === 'my-classes'  && <MyClassesScreen user={user} onOpen={(cls) => setModal({ type: 'classProgress', data: cls })} />}
```

Replace it with:

```jsx
{tab === 'my-classes'  && <MyClassesScreen user={user} onModuleDetail={(mod, cls) => setModal({ type: 'moduleDetail', data: mod, prev: cls, mentorship: null, backTab: 'my-classes' })} />}
```

- [ ] **Step 5: Update the `moduleDetail` back handler to support `backTab`**

In `MentorshipsScope`, find the `moduleDetail` modal case:

```jsx
    if (modal?.type === 'moduleDetail') return (
        <ModuleDetailScreen
            module={modal.data}
            user={user}
            onBack={() => setModal({ type: 'classDetail', data: modal.prev, prev: modal.mentorship })}
```

Update `onBack` to:

```jsx
            onBack={() => modal.backTab ? setModal(null) : setModal({ type: 'classDetail', data: modal.prev, prev: modal.mentorship })}
```

- [ ] **Step 6: Verify the build compiles**

```bash
cd C:\xampp\htdocs\MNCH-Master\public\m-assessment-app
npm run build 2>&1 | tail -20
```
Expected: no errors.

- [ ] **Step 7: Commit**

```bash
git add public/m-assessment-app/src/scopes/MentorshipsScope.jsx
git commit -m "feat(ui): wire mentorship overview + my classes module detail navigation"
```

---

## Spec Coverage Self-Review

| Spec requirement | Task |
|-----------------|------|
| Mentor list — stat row (Mentees · Classes · Progress%) | Task 1 |
| Mentor list — progress bar per card | Task 1 |
| Mentor list — card tap → overview screen | Task 4, Step 3 |
| Mentor list — FAB (+) already present | ✅ existing |
| Mentor list — filter pills (All/Active/Draft/Completed) | ✅ existing (current has richer tabs) |
| MentorshipOverview — hero, back button, stat pills | Task 2 |
| MentorshipOverview — "View Detail & Reports" button | Task 2 |
| MentorshipOverview — "Edit" button | Task 2 |
| MentorshipOverview — class list with lazy mentee expand | Task 2 |
| MentorshipOverview — mentee rows (avatar, name, cadre, progress) | Task 2 |
| MentorshipOverview — "+N more →" link | Task 2 |
| MentorshipOverview — avatar gradient from name | Task 2 |
| MyClasses — hero with stat pills | Task 3 |
| MyClasses — inline module list (no tap needed) | Task 3 |
| MyClasses — module status icons (done/in-progress/pending) | Task 3 |
| MyClasses — module row tap → module detail | Task 3 + Task 4 |
| MyClasses — offline banner | Task 3 |
| MyClasses — empty state | Task 3 |
| Navigation wiring (mentorshipOverview case) | Task 4 |
