import { T } from "../constants.js";

// ── Phone shell ────────────────────────────────────────────────────────────────
export function PhoneShell({ children }) {
    return (
        <div style={{
            width: "100%", maxWidth: 390, height: "100dvh",
            margin: "0 auto", background: T.bg,
            position: "relative", overflow: "hidden",
            boxShadow: "0 0 60px rgba(0,0,0,0.15)",
            fontFamily: "'Plus Jakarta Sans', Inter, system-ui, sans-serif",
        }}>
            {children}
        </div>
    );
}

// ── Bottom navigation ──────────────────────────────────────────────────────────
// The nav bar is 56px tall + env(safe-area-inset-bottom) for gesture/button nav.
// App.jsx must use var(--bottom-inset) for the content area bottom offset.
export function BottomNav({ active, onChange, hideNew = false }) {
    const tabs = [
        { key: "dashboard", label: "Home", icon: "🏠" },
        { key: "assessments", label: "Assessments", icon: "📋" },
        ...(!hideNew ? [{ key: "new", label: "New", icon: "➕" }] : []),
        { key: "reports", label: "Reports", icon: "📊" },
        { key: "profile", label: "Profile", icon: "👤" },
    ];

    return (
        <div style={{
            // The visible tab strip is 56px. Below it we add padding equal to the
            // system navigation bar height so buttons are never hidden behind it.
            paddingBottom: "env(safe-area-inset-bottom, 0px)",
            background: T.card,
            borderTop: `1px solid ${T.border}`,
            boxShadow: "0 -2px 12px rgba(0,0,0,0.06)",
        }}>
            <div style={{
                height: 56,
                display: "flex",
            }}>
                {tabs.map(t => {
                    const isActive = active === t.key;
                    return (
                        <button
                            key={t.key}
                            onClick={() => onChange(t.key)}
                            style={{
                                flex: 1, border: "none", background: "none",
                                cursor: "pointer", padding: "8px 4px",
                                display: "flex", flexDirection: "column",
                                alignItems: "center", justifyContent: "center", gap: 3,
                            }}
                        >
                            <span style={{ fontSize: 20 }}>{t.icon}</span>
                            <span style={{
                                fontSize: 10, fontWeight: isActive ? 700 : 500,
                                color: isActive ? T.primary : T.textMuted,
                            }}>
                                {t.label}
                            </span>
                            {isActive && (
                                <div style={{
                                    width: 4, height: 4, borderRadius: "50%",
                                    background: T.primary, marginTop: 1,
                                }} />
                            )}
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
                background: light ? "rgba(255,255,255,0.15)" : T.borderLight,
                border: "none", borderRadius: 10, padding: "8px 14px",
                cursor: "pointer", fontSize: 13, fontWeight: 700,
                color: light ? "white" : T.textMid,
                display: "flex", alignItems: "center", gap: 6,
            }}
        >
            ← Back
        </button>
    );
}

// ── Avatar ─────────────────────────────────────────────────────────────────────
export function Avatar({ initials, size = 40, color }) {
    return (
        <div style={{
            width: size, height: size, borderRadius: size / 3,
            background: color || T.primary,
            display: "flex", alignItems: "center", justifyContent: "center",
            color: "white", fontWeight: 800,
            fontSize: Math.round(size * 0.35),
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
            borderRadius: 8, padding: "4px 10px",
            fontSize: 12, fontWeight: 700, flexShrink: 0,
            display: "flex", alignItems: "center", gap: 5,
        }}>
            <span style={{
                width: 7, height: 7, borderRadius: "50%",
                background: GRADE_COLOR[grade] ?? "#9CA3AF",
                display: "inline-block",
            }} />
            {pct != null ? `${pct}%` : (GRADE_LABEL[grade] ?? grade)}
        </div>
    );
}

// ── Status chip ────────────────────────────────────────────────────────────────
const STATUS_STYLE = {
    completed: { bg: "#D1FAE5", color: "#065F46", label: "Completed" },
    in_progress: { bg: "#FEF3C7", color: "#92400E", label: "In Progress" },
    draft: { bg: "#F3F4F6", color: "#374151", label: "Draft" },
};

export function StatusChip({ status }) {
    const s = STATUS_STYLE[status] ?? { bg: "#F3F4F6", color: "#374151", label: status ?? "Unknown" };
    return (
        <div style={{
            background: s.bg, color: s.color,
            borderRadius: 8, padding: "4px 10px",
            fontSize: 12, fontWeight: 700, flexShrink: 0,
        }}>
            {s.label}
        </div>
    );
}

// ── Progress bar ───────────────────────────────────────────────────────────────
export function ProgressBar({ pct = 0, color = T.primary, height = 6 }) {
    const safePct = Math.min(Math.max(pct ?? 0, 0), 100);
    return (
        <div style={{
            height, background: T.borderLight, borderRadius: 999,
            overflow: "hidden", width: "100%",
        }}>
            <div style={{
                height: "100%", width: `${safePct}%`,
                background: color, borderRadius: 999,
                transition: "width 0.3s",
            }} />
        </div>
    );
}
