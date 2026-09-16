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
        let cancelled = false;

        api.me.classes()
            .then(async d => {
                let arr = Array.isArray(d?.data) ? d.data : (Array.isArray(d) ? d : []);

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

                if (!cancelled) {
                    setIsOffline(!navigator.onLine);
                    setClasses(arr);
                }
            })
            .catch(() => { if (!cancelled) setClasses([]); })
            .finally(() => { if (!cancelled) setLoading(false); });

        return () => { cancelled = true; };
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

