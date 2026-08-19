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
    const loadingMentees = mentees === null && isExpanded;

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

    // Lazy load mentees for a class when it's expanded for the first time.
    // menteeCache intentionally omitted from deps: including it re-runs after every fetch and causes a loop.
    /* eslint-disable react-hooks/exhaustive-deps */
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
    /* eslint-enable react-hooks/exhaustive-deps */

    function toggleExpand(classId) {
        setExpanded(prev => ({ ...prev, [classId]: !prev[classId] }));
    }

    const m = mentorship;
    const hasCompletedClass = classes.some(c => c.status === "completed");
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
                        {(() => {
                            const statusStyle = STATUS_MAP[m?.status] ?? STATUS_MAP.draft;
                            return [
                                { label: "Mentees",  value: totalMentees },
                                { label: "Classes",  value: classes.length },
                                { label: "Progress", value: avgPct + "%", highlight: avgPct >= 60 },
                                { label: "Status",   value: m?.status ?? "—", isStatus: true },
                            ].map(pill => (
                                <div key={pill.label} style={{
                                    flex: 1, padding: "8px 4px", borderRadius: 12, textAlign: "center",
                                    background: pill.isStatus ? statusStyle.bg + "22" : "rgba(255,255,255,0.08)",
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
                            ));
                        })()}
                    </div>
                )}
            </div>

            {/* Action strip */}
            <div style={{ margin: "12px 16px 0", display: "flex", gap: 10 }}>
                <button
                    onClick={() => onViewDetail(m ?? { id: mentorshipId })}
                    style={{ flex: 1, padding: "12px 0", background: T.gradientPrimary,
                        color: "white", border: "none", borderRadius: T.radiusSm,
                        fontWeight: 700, fontSize: 13, cursor: "pointer",
                        boxShadow: `0 4px 16px ${T.primaryGlow}` }}>
                    View Detail & Reports →
                </button>
                {!hasCompletedClass && (
                    <button
                        onClick={() => onEdit(m ?? { id: mentorshipId })}
                        style={{ padding: "12px 18px", background: T.primaryGhost,
                            color: T.primary, border: `1px solid ${T.primary}33`,
                            borderRadius: T.radiusSm, fontWeight: 700, fontSize: 13, cursor: "pointer" }}>
                        Edit
                    </button>
                )}
            </div>

            {/* Classes section */}
            <div style={{ padding: "16px 16px 80px" }}>
                <div style={{ fontSize: 11, fontWeight: 700, color: T.textMuted, letterSpacing: 1,
                    marginBottom: 10 }}>
                    MENTEES BY CLASS
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

