import { T } from "../constants.js";

// ── Inline keyframes (injected once) ────────────────────────────────────────
const ANIM_STYLES = `
@keyframes navPop{from{transform:scale(0);opacity:0}to{transform:scale(1);opacity:1}}
@keyframes navSlide{from{transform:translateY(8px);opacity:0}to{transform:translateY(0);opacity:1}}
@keyframes barGrow{from{width:0%}to{width:var(--bar-w)}}
@keyframes dotPulse{0%,100%{transform:scale(1)}50%{transform:scale(1.3)}}
@keyframes shimmer{from{background-position:200% 0}to{background-position:-200% 0}}
`;

// ── Phone shell ────────────────────────────────────────────────────────────────
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

// ── SVG Icon components for nav ─────────────────────────────────────────────
const NavIcons = {
    dashboard: (active) => (
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke={active ? T.primary : T.textMuted} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z" fill={active ? T.primaryGhost : "none"} />
            <polyline points="9 22 9 12 15 12 15 22" />
        </svg>
    ),
    assessments: (active) => (
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke={active ? T.primary : T.textMuted} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z" fill={active ? T.primaryGhost : "none"} />
            <polyline points="14 2 14 8 20 8" />
            <line x1="16" y1="13" x2="8" y2="13" /><line x1="16" y1="17" x2="8" y2="17" />
            <polyline points="10 9 9 9 8 9" />
        </svg>
    ),
    reports: (active) => (
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke={active ? T.primary : T.textMuted} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <line x1="18" y1="20" x2="18" y2="10" /><line x1="12" y1="20" x2="12" y2="4" />
            <line x1="6" y1="20" x2="6" y2="14" />
            {active && <circle cx="12" cy="4" r="2" fill={T.primary} stroke="none" />}
        </svg>
    ),
    profile: (active) => (
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke={active ? T.primary : T.textMuted} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2" />
            <circle cx="12" cy="7" r="4" fill={active ? T.primaryGhost : "none"} />
        </svg>
    ),
    mentorship: (active) => (
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke={active ? T.primary : T.textMuted} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <circle cx="12" cy="8" r="4" fill={active ? T.primaryGhost : "none"} />
            <path d="M4 20c0-4 3.6-7 8-7s8 3 8 7" />
            <path d="M17 11l2 2 4-4" stroke={active ? T.success : T.textMuted} />
        </svg>
    ),
    myClasses: (active) => (
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke={active ? T.primary : T.textMuted} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z" fill={active ? T.primaryGhost : "none"} />
            <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z" />
        </svg>
    ),
    trainings: (active) => (
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke={active ? T.primary : T.textMuted} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <rect x="3" y="4" width="18" height="16" rx="2" fill={active ? T.primaryGhost : "none"} />
            <path d="M8 9h8M8 13h5" />
        </svg>
    ),
};

// ── Bottom navigation ──────────────────────────────────────────────────────────
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
                                transition: "all 0.25s cubic-bezier(0.34,1.56,0.64,1)",
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
                                        {t.iconKey === "mentorship"  && <><circle cx="12" cy="8" r="4" fill="rgba(255,255,255,0.25)"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/><path d="M17 11l2 2 4-4"/></>}
                                        {t.iconKey === "myClasses"   && <><path d="M2 3h6a4 4 0 014 4v14a3 3 0 00-3-3H2z" fill="rgba(255,255,255,0.25)"/><path d="M22 3h-6a4 4 0 00-4 4v14a3 3 0 013-3h7z"/></>}
                                        {t.iconKey === "trainings"   && <><rect x="3" y="4" width="18" height="16" rx="2" fill="rgba(255,255,255,0.25)"/><path d="M8 9h8M8 13h5"/></>}
                                        {t.iconKey === "profile"     && <><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4" fill="rgba(255,255,255,0.25)"/></>}
                                    </svg>
                                ) : iconFn && iconFn(false)}
                            </div>
                            <span style={{
                                fontSize: 9, fontWeight: isActive ? 700 : 500,
                                color: isActive ? T.primary : T.textMuted,
                                transition: "color 0.25s cubic-bezier(0.34,1.56,0.64,1)",
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
// ── Back button ────────────────────────────────────────────────────────────────
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
// ── Avatar ─────────────────────────────────────────────────────────────────────
export function Avatar({ initials, size = 40, color }) {
    return (
        <div style={{
            width: size, height: size, borderRadius: size * 0.35,
            background: color || T.gradientPrimary,
            display: "flex", alignItems: "center", justifyContent: "center",
            color: "white", fontWeight: 700,
            fontSize: Math.round(size * 0.34),
            letterSpacing: 0.5,
            boxShadow: `0 4px 16px ${T.primaryGlow}`,
        }}>
            {(initials || "??").slice(0, 2).toUpperCase()}
        </div>
    );
}

// ── Grade badge ────────────────────────────────────────────────────────────────
const GRADE_COLOR = { green: "#10B981", yellow: "#F59E0B", red: "#EF4444" };
const GRADE_BG = { green: "#D1FAE5", yellow: "#FEF3C7", red: "#FEE2E2" };
const GRADE_TEXT = { green: "#065F46", yellow: "#92400E", red: "#991B1B" };
const GRADE_LABEL = { green: "Good", yellow: "Fair", red: "Poor" };

export function GradeBadge({ grade, pct }) {
    if (!grade) return null;
    return (
        <div style={{
            background: GRADE_BG[grade] ?? "#F3F4F6",
            color: GRADE_TEXT[grade] ?? "#374151",
            borderRadius: 10, padding: "5px 12px",
            fontSize: 12, fontWeight: 700, flexShrink: 0,
            display: "flex", alignItems: "center", gap: 5,
            border: `1px solid ${GRADE_COLOR[grade]}22`,
        }}>
            <span style={{
                width: 7, height: 7, borderRadius: "50%",
                background: GRADE_COLOR[grade] ?? "#9CA3AF",
                display: "inline-block",
                boxShadow: `0 0 6px ${GRADE_COLOR[grade]}44`,
            }} />
            {pct != null && !isNaN(Number(pct)) ? `${Number(pct).toFixed(1)}%` : (GRADE_LABEL[grade] ?? grade)}
        </div>
    );
}

// ── Status chip ────────────────────────────────────────────────────────────────
const STATUS_STYLE = {
    completed: { bg: "#D1FAE5", color: "#065F46", label: "Completed", border: "#6EE7B733" },
    in_progress: { bg: "#FEF3C7", color: "#92400E", label: "In Progress", border: "#FBBF2433" },
    draft: { bg: "#F0F1F8", color: "#3D3D5C", label: "Draft", border: "#E2E4F0" },
};

export function StatusChip({ status }) {
    const s = STATUS_STYLE[status] ?? { bg: "#F0F1F8", color: "#3D3D5C", label: status ?? "Unknown", border: "#E2E4F0" };
    return (
        <div style={{
            background: s.bg, color: s.color,
            borderRadius: 10, padding: "5px 12px",
            fontSize: 12, fontWeight: 700, flexShrink: 0,
            border: `1px solid ${s.border}`,
        }}>
            {s.label}
        </div>
    );
}

// ── Progress bar with gradient ─────────────────────────────────────────────────
export function ProgressBar({ pct = 0, color = T.primary, height = 6, gradient, animated = false }) {
    const safePct = Math.min(Math.max(pct ?? 0, 0), 100);
    const bg = gradient || color;
    return (
        <div style={{
            height, background: T.borderLight, borderRadius: 999,
            overflow: "hidden", width: "100%",
            position: "relative",
        }}>
            <div style={{
                height: "100%",
                width: `${safePct}%`,
                background: bg,
                borderRadius: 999,
                transition: "width 0.6s cubic-bezier(0.4,0,0.2,1)",
                position: "relative",
                overflow: "hidden",
                "--bar-w": `${safePct}%`,
            }}>
                {/* Shimmer effect on animated bars */}
                {animated && safePct > 0 && (
                    <div style={{
                        position: "absolute", inset: 0,
                        background: "linear-gradient(90deg, transparent 0%, rgba(255,255,255,0.3) 50%, transparent 100%)",
                        backgroundSize: "200% 100%",
                        animation: "shimmer 2s infinite",
                    }} />
                )}
            </div>
        </div>
    );
}

// ── Glass card wrapper ─────────────────────────────────────────────────────────
export function GlassCard({ children, style = {}, onClick }) {
    const Component = onClick ? "button" : "div";
    return (
        <Component
            onClick={onClick}
            style={{
                background: "rgba(255,255,255,0.85)",
                backdropFilter: "blur(12px) saturate(150%)",
                WebkitBackdropFilter: "blur(12px) saturate(150%)",
                borderRadius: T.radius,
                border: `1px solid rgba(255,255,255,0.6)`,
                boxShadow: T.shadowCard,
                padding: 16,
                ...(onClick ? { cursor: "pointer", textAlign: "left", width: "100%" } : {}),
                transition: "all 0.2s cubic-bezier(0.4,0,0.2,1)",
                ...style,
            }}
        >
            {children}
        </Component>
    );
}

// ── Skeleton loader ────────────────────────────────────────────────────────────
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
