// src/screens/screen-class-detail.jsx
import { useState, useEffect } from "react";
import { T } from "../constants.js";
import api from "../services/api.service.js";


const STATUS_STYLE = {
    not_started: { bg: "#F3F4F6", color: "#6B7280" },
    in_progress:  { bg: "#FEF3C7", color: "#92400E" },
    completed:    { bg: "#D1FAE5", color: "#065F46" },
    draft:        { bg: "#F3F4F6", color: "#6B7280" },
    active:       { bg: "#DBEAFE", color: "#1E40AF" },
    cancelled:    { bg: "#FEE2E2", color: "#991B1B" },
};

function StatusBadge({ status }) {
    const s = STATUS_STYLE[status] ?? STATUS_STYLE.not_started;
    return (
        <span style={{ fontSize: 11, fontWeight: 700, padding: "2px 8px", borderRadius: 6, background: s.bg, color: s.color, flexShrink: 0 }}>
            {status?.replace(/_/g, " ")}
        </span>
    );
}

const MODULE_PROGRESS_COLOR = {
    completed:   "#10B981",
    in_progress: "#F59E0B",
    exempted:    "#8B5CF6",
    not_started: "#D1D5DB",
};

function ClassReportSheet({ classId, onClose }) {
    const [loading, setLoading] = useState(true);
    const [report, setReport]   = useState(null);
    const [err, setErr]         = useState(null);

    useEffect(() => {
        api.classLifecycle.report(classId)
            .then(d => setReport(d?.data ?? null))
            .catch(e => setErr(e?.message ?? "Failed to load report."))
            .finally(() => setLoading(false));
    }, [classId]);

    const summary = report?.summary ?? {};
    const modules = report?.modules ?? [];
    const mentees = report?.mentees ?? [];
    const cls     = report?.class ?? {};

    return (
        <div style={{ position: "fixed", inset: 0, zIndex: 60, background: T.bg, display: "flex", flexDirection: "column" }}>
            {/* Hero */}
            <div style={{
                background: "linear-gradient(160deg, #1E1B4B 0%, #3730A3 55%, #818CF8 100%)",
                padding: "44px 20px 18px", position: "relative", overflow: "hidden",
            }}>
                <div style={{ position: "absolute", width: 150, height: 150, borderRadius: "50%", background: "radial-gradient(circle, rgba(79,106,245,0.20) 0%, transparent 70%)", top: -20, right: -20 }} />
                <button onClick={onClose} style={{ border: "none", background: "rgba(255,255,255,0.15)", cursor: "pointer", padding: "6px 10px", borderRadius: 10, marginBottom: 14, display: "flex", alignItems: "center", gap: 4 }}>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="2.5"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                    <span style={{ fontSize: 12, color: "rgba(255,255,255,0.85)", fontWeight: 600 }}>Back</span>
                </button>
                <div style={{ fontSize: 11, color: "rgba(255,255,255,0.55)", fontWeight: 600, textTransform: "uppercase", letterSpacing: 0.5, marginBottom: 3 }}>Class Report</div>
                <div style={{ fontSize: 18, fontWeight: 800, color: "white", lineHeight: 1.25, marginBottom: 4 }}>{cls.name ?? "…"}</div>
                {cls.program && <div style={{ fontSize: 12, color: "rgba(255,255,255,0.65)" }}>{cls.program}{cls.facility ? ` · ${cls.facility}` : ""}</div>}
            </div>

            <div style={{ flex: 1, overflowY: "auto", padding: 16 }}>
                {loading && (
                    <div style={{ textAlign: "center", paddingTop: 40, color: T.textSub, fontSize: 13 }}>Loading report…</div>
                )}
                {err && (
                    <div style={{ background: "#FEE2E2", color: "#991B1B", borderRadius: T.radiusSm, padding: "12px 16px", fontSize: 13 }}>{err}</div>
                )}

                {!loading && report && (
                    <>
                        {/* Summary cards */}
                        <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr 1fr", gap: 8, marginBottom: 16 }}>
                            {[
                                { label: "Enrolled", value: summary.enrolled ?? 0, color: "#1E40AF", bg: "#DBEAFE" },
                                { label: "Modules Done", value: `${summary.modules_completed ?? 0}/${summary.modules_total ?? 0}`, color: "#065F46", bg: "#D1FAE5" },
                                { label: "Avg Attendance", value: `${summary.avg_attendance_pct ?? 0}%`, color: "#92400E", bg: "#FEF3C7" },
                            ].map(card => (
                                <div key={card.label} style={{ background: card.bg, borderRadius: T.radiusSm, padding: "10px 8px", textAlign: "center" }}>
                                    <div style={{ fontSize: 18, fontWeight: 800, color: card.color }}>{card.value}</div>
                                    <div style={{ fontSize: 10, color: card.color, fontWeight: 600, marginTop: 2, opacity: 0.8 }}>{card.label}</div>
                                </div>
                            ))}
                        </div>

                        {/* Module list */}
                        <div style={{ marginBottom: 16 }}>
                            <div style={{ fontSize: 11, fontWeight: 700, color: T.textSub, textTransform: "uppercase", letterSpacing: 0.5, marginBottom: 8 }}>
                                Modules ({modules.length})
                            </div>
                            {modules.map(m => {
                                const s = STATUS_STYLE[m.status] ?? STATUS_STYLE.not_started;
                                return (
                                    <div key={m.id} style={{
                                        display: "flex", justifyContent: "space-between", alignItems: "center",
                                        background: T.card, borderRadius: T.radiusXs, padding: "10px 14px",
                                        marginBottom: 6, border: `1px solid ${T.border}`,
                                    }}>
                                        <div style={{ fontSize: 13, fontWeight: 600, color: T.text }}>
                                            {m.order_sequence}. {m.name}
                                        </div>
                                        <span style={{ fontSize: 10, fontWeight: 700, padding: "2px 7px", borderRadius: 5, background: s.bg, color: s.color, flexShrink: 0, marginLeft: 8 }}>
                                            {m.status?.replace(/_/g, " ")}
                                        </span>
                                    </div>
                                );
                            })}
                        </div>

                        {/* Mentee progress */}
                        <div>
                            <div style={{ fontSize: 11, fontWeight: 700, color: T.textSub, textTransform: "uppercase", letterSpacing: 0.5, marginBottom: 8 }}>
                                Mentee Progress ({mentees.length})
                            </div>
                            {mentees.length === 0 && (
                                <div style={{ color: T.textSub, fontSize: 13, textAlign: "center", padding: "16px 0" }}>No mentees enrolled.</div>
                            )}
                            {mentees.map(mentee => (
                                <div key={mentee.participant_id} style={{
                                    background: T.card, borderRadius: T.radiusSm, padding: "12px 14px",
                                    marginBottom: 8, boxShadow: T.shadowCard, border: `1px solid ${T.border}`,
                                }}>
                                    <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start", marginBottom: 8 }}>
                                        <div>
                                            <div style={{ fontSize: 14, fontWeight: 700, color: T.text }}>{mentee.name}</div>
                                            {mentee.cadre && <div style={{ fontSize: 11, color: T.textSub, marginTop: 1 }}>{mentee.cadre}</div>}
                                        </div>
                                        <div style={{ textAlign: "right" }}>
                                            <div style={{
                                                fontSize: 16, fontWeight: 800,
                                                color: mentee.attendance_pct >= 80 ? "#065F46" : mentee.attendance_pct >= 50 ? "#92400E" : "#991B1B",
                                            }}>{mentee.attendance_pct}%</div>
                                            <div style={{ fontSize: 10, color: T.textSub }}>{mentee.attended}/{mentee.total_modules} modules</div>
                                        </div>
                                    </div>

                                    {/* Per-module dots */}
                                    {modules.length > 0 && (
                                        <div style={{ display: "flex", gap: 5, flexWrap: "wrap" }}>
                                            {modules.map(mod => {
                                                const status = mentee.module_progress?.[String(mod.id)] ?? "not_started";
                                                const color = MODULE_PROGRESS_COLOR[status] ?? "#D1D5DB";
                                                return (
                                                    <div
                                                        key={mod.id}
                                                        title={`${mod.name}: ${status.replace(/_/g, " ")}`}
                                                        style={{
                                                            width: 10, height: 10, borderRadius: "50%",
                                                            background: color, flexShrink: 0,
                                                        }}
                                                    />
                                                );
                                            })}
                                        </div>
                                    )}

                                    {mentee.class_complete && (
                                        <div style={{ marginTop: 6, fontSize: 11, color: "#065F46", fontWeight: 700 }}>✓ Class completed</div>
                                    )}
                                </div>
                            ))}

                            {/* Legend */}
                            {modules.length > 0 && mentees.length > 0 && (
                                <div style={{ display: "flex", gap: 12, flexWrap: "wrap", padding: "8px 4px" }}>
                                    {[
                                        { color: "#10B981", label: "Completed" },
                                        { color: "#F59E0B", label: "In progress" },
                                        { color: "#8B5CF6", label: "Exempted" },
                                        { color: "#D1D5DB", label: "Not started" },
                                    ].map(l => (
                                        <div key={l.label} style={{ display: "flex", alignItems: "center", gap: 4 }}>
                                            <div style={{ width: 8, height: 8, borderRadius: "50%", background: l.color }} />
                                            <span style={{ fontSize: 10, color: T.textSub }}>{l.label}</span>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    </>
                )}
            </div>
        </div>
    );
}

export function ClassDetailScreen({
    cls,
    onBack,
    onOpenModule,
    onManageMentees,
    onEditClass,
    onAddModule,
    confirm = (o) => Promise.resolve(window.confirm(o?.title ?? "Confirm?")),
}) {
    const [detail, setDetail]     = useState(null);
    const [loading, setLoading]   = useState(true);
    const [modules, setModules]   = useState([]);
    const [tab, setTab]           = useState("modules");
    const [moduleActing, setModuleActing] = useState(null);

    // Class lifecycle state
    const [localStatus, setLocalStatus]   = useState(cls.status ?? "draft");
    const [classActing, setClassActing]   = useState(null); // 'start' | 'end'
    const [classError, setClassError]     = useState(null);
    const [reportOpen, setReportOpen]     = useState(false);

    useEffect(() => {
        const trainingId = cls.trainingId;
        const classId    = cls.id;

        if (trainingId) {
            api.mentorships.classDetail(trainingId, classId)
                .then(d => {
                    const data = d?.data ?? null;
                    setDetail(data);
                    setModules(data?.modules ?? []);
                    if (data?.status) setLocalStatus(data.status);
                })
                .catch(() => {
                    setDetail(cls);
                    setModules(cls.modules ?? []);
                })
                .finally(() => setLoading(false));
        } else {
            api.modules.list(classId)
                .then(d => {
                    const mods = Array.isArray(d?.data) ? d.data : [];
                    setDetail({ ...cls, modules: mods });
                    setModules(mods);
                })
                .catch(() => {
                    setDetail(cls);
                    setModules(cls.modules ?? []);
                })
                .finally(() => setLoading(false));
        }
    }, [cls.id]);

    useEffect(() => {
        if (cls.modules) setModules(cls.modules);
    }, [cls.modules]);

    const data    = detail ?? cls;
    const mentees = data?.mentees ?? [];
    const pct     = data?.progress_percentage ?? 0;

    // ── Module actions ──────────────────────────────────────────────────────────

    const handleStart = async (mod) => {
        setModuleActing(mod.id);
        try {
            const res = await api.modules.start(mod.id);
            const updated = res?.data ?? {};
            setModules(prev => prev.map(m => m.id === mod.id ? { ...m, ...updated } : m));
        } catch (e) {
            alert(e.message ?? "Failed to start module.");
        } finally {
            setModuleActing(null);
        }
    };

    const handleComplete = async (mod) => {
        setModuleActing(mod.id);
        try {
            const res = await api.modules.complete(mod.id);
            const updated = res?.data ?? {};
            setModules(prev => prev.map(m => m.id === mod.id ? { ...m, ...updated } : m));
        } catch (e) {
            alert(e.message ?? "Failed to complete module.");
        } finally {
            setModuleActing(null);
        }
    };

    const handleDelete = async (mod) => {
        const ok = await confirm({
            title: `Delete "${mod.name}"?`,
            message: "This module and its sessions will be permanently removed. Only modules that haven't been started can be deleted.",
            confirmLabel: "Delete Module",
            danger: true,
        });
        if (!ok) return;
        setModuleActing(mod.id);
        try {
            await api.modules.remove(mod.id, cls.id);
            setModules(prev => prev.filter(m => m.id !== mod.id));
        } catch (e) {
            alert(e.message ?? "Failed to delete module.");
        } finally {
            setModuleActing(null);
        }
    };

    // ── Class lifecycle actions ─────────────────────────────────────────────────

    const handleStartClass = async () => {
        const ok = await confirm({
            title: "Start This Class?",
            message: `This will activate the class and open attendance for all ${modules.length} module(s). The enrollment link stays open so mentees can still join.`,
            confirmLabel: "Start Class",
        });
        if (!ok) return;
        setClassActing("start");
        setClassError(null);
        try {
            await api.classLifecycle.start(cls.id);
            setLocalStatus("active");
        } catch (e) {
            setClassError(e.message ?? "Failed to start class.");
        } finally {
            setClassActing(null);
        }
    };

    const handleEndClass = async () => {
        const ok = await confirm({
            title: "End This Class?",
            message: "This will permanently close the class. All modules will be marked complete and attendance links will stop working. This cannot be undone.",
            confirmLabel: "Yes, End Class",
            danger: true,
        });
        if (!ok) return;
        setClassActing("end");
        setClassError(null);
        try {
            await api.classLifecycle.end(cls.id);
            setLocalStatus("completed");
        } catch (e) {
            setClassError(e.message ?? "Failed to end class.");
        } finally {
            setClassActing(null);
        }
    };

    return (
        <div style={{ display: "flex", flexDirection: "column", height: "100%", background: T.bg }}>
            {/* ── Gradient Hero ── */}
            <div style={{
                background: "linear-gradient(160deg, #1E1B4B 0%, #3730A3 55%, #818CF8 100%)",
                padding: "44px 20px 16px",
                position: "relative", overflow: "hidden",
            }}>
                <div style={{ position: "absolute", width: 150, height: 150, borderRadius: "50%", background: "radial-gradient(circle, rgba(165,180,252,0.15) 0%, transparent 70%)", top: -30, right: -30 }} />
                <div style={{ display: "flex", alignItems: "flex-start", gap: 10, marginBottom: 12 }}>
                    <button onClick={onBack} style={{ border: "none", background: "rgba(255,255,255,0.12)", cursor: "pointer", padding: "6px 10px", borderRadius: 10, flexShrink: 0, display: "flex", alignItems: "center", gap: 4 }}>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="2.5"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                        <span style={{ fontSize: 12, color: "rgba(255,255,255,0.8)", fontWeight: 600 }}>Back</span>
                    </button>
                    {onEditClass && (
                        <button onClick={onEditClass} style={{ marginLeft: "auto", border: "none", background: "rgba(255,255,255,0.12)", cursor: "pointer", padding: "6px 10px", borderRadius: 10, display: "flex", alignItems: "center", gap: 4 }}>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            <span style={{ fontSize: 12, color: "rgba(255,255,255,0.8)", fontWeight: 600 }}>Edit</span>
                        </button>
                    )}
                </div>
                <div style={{ fontSize: 18, fontWeight: 800, color: "white", lineHeight: 1.25, marginBottom: 6 }}>{data.name}</div>
                <div style={{ display: "flex", alignItems: "center", gap: 10, flexWrap: "wrap" }}>
                    <span style={{ fontSize: 11, fontWeight: 700, padding: "2px 9px", borderRadius: 20, background: "rgba(255,255,255,0.15)", color: "rgba(255,255,255,0.9)", border: "1px solid rgba(255,255,255,0.2)" }}>
                        {localStatus}
                    </span>
                    <span style={{ fontSize: 12, color: "rgba(255,255,255,0.55)" }}>
                        {data.participant_count ?? mentees.length} mentees · {modules.length} modules
                    </span>
                </div>
                <div style={{ marginTop: 14 }}>
                    <div style={{ height: 5, borderRadius: 4, background: "rgba(255,255,255,0.15)", overflow: "hidden" }}>
                        <div style={{ height: "100%", width: `${pct}%`, background: pct === 100 ? "#34D399" : "rgba(255,255,255,0.8)", borderRadius: 4, transition: "width 0.6s" }} />
                    </div>
                    <div style={{ display: "flex", justifyContent: "space-between", marginTop: 4 }}>
                        <span style={{ fontSize: 11, color: "rgba(255,255,255,0.5)" }}>{data.start_date ?? ""}{data.end_date ? ` → ${data.end_date}` : ""}</span>
                        <span style={{ fontSize: 11, fontWeight: 700, color: pct === 100 ? "#34D399" : "rgba(255,255,255,0.8)" }}>{pct}%</span>
                    </div>
                </div>
            </div>

            {/* ── Class action bar ── */}
            <div style={{ background: T.card, borderBottom: `1px solid ${T.border}`, padding: "10px 14px" }}>
                {classError && (
                    <div style={{ background: "#FEE2E2", color: "#991B1B", borderRadius: T.radiusXs, padding: "8px 12px", fontSize: 12, marginBottom: 8 }}>
                        {classError}
                    </div>
                )}
                <div style={{ display: "flex", gap: 8 }}>
                    {localStatus === "draft" && (
                        <button
                            onClick={handleStartClass}
                            disabled={classActing === "start"}
                            style={{
                                flex: 1, background: "#10B981", border: "none", borderRadius: T.radiusXs,
                                color: "#fff", fontWeight: 700, fontSize: 13, padding: "9px 0",
                                cursor: classActing === "start" ? "not-allowed" : "pointer",
                                opacity: classActing === "start" ? 0.7 : 1,
                                display: "flex", alignItems: "center", justifyContent: "center", gap: 5,
                            }}
                        >
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                            {classActing === "start" ? "Starting…" : "Start Class"}
                        </button>
                    )}

                    {localStatus === "active" && (
                        <button
                            onClick={handleEndClass}
                            disabled={classActing === "end"}
                            style={{
                                flex: 1, background: "#FEF2F2", border: "1px solid #FECACA", borderRadius: T.radiusXs,
                                color: "#DC2626", fontWeight: 700, fontSize: 13, padding: "9px 0",
                                cursor: classActing === "end" ? "not-allowed" : "pointer",
                                opacity: classActing === "end" ? 0.7 : 1,
                                display: "flex", alignItems: "center", justifyContent: "center", gap: 5,
                            }}
                        >
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><rect x="3" y="3" width="18" height="18" rx="2"/></svg>
                            {classActing === "end" ? "Ending…" : "End Class"}
                        </button>
                    )}

                    {localStatus === "completed" && (
                        <div style={{ flex: 1, textAlign: "center", color: "#065F46", fontWeight: 700, fontSize: 13, padding: "9px 0" }}>
                            ✓ Class Completed
                        </div>
                    )}

                    <button
                        onClick={() => setReportOpen(true)}
                        style={{
                            flex: 1, background: T.primaryGhost, border: `1px solid ${T.primary}`, borderRadius: T.radiusXs,
                            color: T.primary, fontWeight: 700, fontSize: 13, padding: "9px 0",
                            cursor: "pointer",
                            display: "flex", alignItems: "center", justifyContent: "center", gap: 5,
                        }}
                    >
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                        Class Report
                    </button>
                </div>
            </div>

            {/* Tabs */}
            <div style={{ display: "flex", background: T.card, borderBottom: `1px solid ${T.border}` }}>
                {["modules", "mentees"].map(t => (
                    <button key={t} onClick={() => setTab(t)} style={{
                        flex: 1, padding: "10px 0", border: "none", background: "none",
                        fontSize: 13, fontWeight: tab === t ? 700 : 500,
                        color: tab === t ? T.primary : T.textSub, cursor: "pointer",
                        borderBottom: tab === t ? `2px solid ${T.primary}` : "2px solid transparent",
                    }}>
                        {t === "modules" ? `Modules (${modules.length})` : `Mentees (${mentees.length || (data.participant_count ?? 0)})`}
                    </button>
                ))}
            </div>

            <div style={{ flex: 1, overflowY: "auto", padding: 16 }}>
                {loading && <div style={{ color: T.textSub, textAlign: "center", paddingTop: 32 }}>Loading…</div>}

                {/* Modules tab */}
                {!loading && tab === "modules" && (
                    <div style={{ display: "flex", flexDirection: "column", gap: 10 }}>
                        {onAddModule && (
                            <button onClick={() => onAddModule(modules)} style={{
                                padding: "10px 14px", borderRadius: T.radiusSm, border: `1.5px dashed ${T.primary}`,
                                background: T.primaryGhost, color: T.primary, fontSize: 13, fontWeight: 700,
                                cursor: "pointer", textAlign: "center",
                            }}>
                                + Add Module
                            </button>
                        )}
                        {modules.length === 0 && (
                            <div style={{ color: T.textSub, textAlign: "center", paddingTop: 32 }}>No modules.</div>
                        )}
                        {modules.map(m => {
                            const isActing = moduleActing === m.id;
                            const canStart    = m.status === "not_started";
                            const canComplete = m.status === "in_progress";
                            const canDelete   = m.status === "not_started";

                            return (
                                <div
                                    key={m.id}
                                    style={{
                                        background: T.card, border: `1px solid ${T.border}`,
                                        borderRadius: T.radiusSm, boxShadow: T.shadowCard,
                                        overflow: "hidden", opacity: isActing ? 0.7 : 1,
                                    }}
                                >
                                    <div
                                        onClick={() => onOpenModule({ ...m, classId: cls.id })}
                                        style={{ padding: "14px 16px", cursor: "pointer" }}
                                    >
                                        <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start", marginBottom: 4 }}>
                                            <div style={{ fontWeight: 700, color: T.text, fontSize: 14, flex: 1, marginRight: 8 }}>
                                                {m.order_sequence}. {m.name}
                                            </div>
                                            <StatusBadge status={m.status} />
                                        </div>
                                        <div style={{ fontSize: 12, color: T.textSub, display: "flex", gap: 12, flexWrap: "wrap" }}>
                                            <span>{m.session_count ?? 0} sessions</span>
                                            {m.requires_assessment && <span style={{ color: "#7C3AED" }}>· Assessment required</span>}
                                            {m.started_at && <span>· Started {new Date(m.started_at).toLocaleDateString()}</span>}
                                            {m.completed_at && <span>· Completed {new Date(m.completed_at).toLocaleDateString()}</span>}
                                        </div>
                                    </div>

                                    <div style={{
                                        display: "flex", gap: 8, padding: "8px 12px",
                                        borderTop: `1px solid ${T.borderLight}`,
                                        background: T.bg,
                                    }}>
                                        <button
                                            onClick={() => onOpenModule({ ...m, classId: cls.id })}
                                            style={{
                                                flex: 1, display: "flex", alignItems: "center", justifyContent: "center", gap: 5,
                                                padding: "6px 0", borderRadius: T.radiusXs,
                                                border: "none", background: "linear-gradient(135deg, #3730A3, #6366F1)",
                                                color: "#fff", fontSize: 12, fontWeight: 600, cursor: "pointer",
                                            }}
                                        >
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5">
                                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                                            </svg>
                                            View
                                        </button>

                                        {canStart && (
                                            <button
                                                onClick={(e) => { e.stopPropagation(); handleStart(m); }}
                                                disabled={isActing}
                                                style={{
                                                    flex: 1, display: "flex", alignItems: "center", justifyContent: "center", gap: 5,
                                                    padding: "6px 0", borderRadius: T.radiusXs,
                                                    border: "1px solid #6EE7B7", background: "#ECFDF5",
                                                    color: "#065F46", fontSize: 12, fontWeight: 600,
                                                    cursor: isActing ? "not-allowed" : "pointer",
                                                }}
                                            >
                                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                                                {isActing ? "…" : "Start"}
                                            </button>
                                        )}
                                        {canComplete && (
                                            <button
                                                onClick={(e) => { e.stopPropagation(); handleComplete(m); }}
                                                disabled={isActing}
                                                style={{
                                                    flex: 1, display: "flex", alignItems: "center", justifyContent: "center", gap: 5,
                                                    padding: "6px 0", borderRadius: T.radiusXs,
                                                    border: "1px solid #93C5FD", background: "#EFF6FF",
                                                    color: "#1D4ED8", fontSize: 12, fontWeight: 600,
                                                    cursor: isActing ? "not-allowed" : "pointer",
                                                }}
                                            >
                                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                                {isActing ? "…" : "Complete"}
                                            </button>
                                        )}
                                        {m.status === "completed" && (
                                            <div style={{
                                                flex: 1, display: "flex", alignItems: "center", justifyContent: "center",
                                                fontSize: 12, color: "#065F46", fontWeight: 600,
                                            }}>
                                                ✓ Done
                                            </div>
                                        )}

                                        {canDelete ? (
                                            <button
                                                onClick={(e) => { e.stopPropagation(); handleDelete(m); }}
                                                disabled={isActing}
                                                style={{
                                                    flex: 1, display: "flex", alignItems: "center", justifyContent: "center", gap: 5,
                                                    padding: "6px 0", borderRadius: T.radiusXs,
                                                    border: "1px solid #FECACA", background: "#FEF2F2",
                                                    color: "#DC2626", fontSize: 12, fontWeight: 600,
                                                    cursor: isActing ? "not-allowed" : "pointer",
                                                }}
                                            >
                                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                                                Remove
                                            </button>
                                        ) : (
                                            <div style={{ flex: 1 }} />
                                        )}
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                )}

                {/* Mentees tab */}
                {!loading && tab === "mentees" && (
                    <div style={{ display: "flex", flexDirection: "column", gap: 8 }}>
                        {onManageMentees && (
                            <button onClick={onManageMentees} style={{
                                padding: "10px 14px", borderRadius: T.radiusSm, border: `1.5px solid ${T.primary}`,
                                background: T.primaryGhost, color: T.primary, fontSize: 13, fontWeight: 700,
                                cursor: "pointer", textAlign: "center",
                            }}>
                                Manage Mentees
                            </button>
                        )}
                        {mentees.length === 0 && (
                            <div style={{ color: T.textSub, textAlign: "center", paddingTop: 20 }}>No mentees enrolled.</div>
                        )}
                        {mentees.map(m => (
                            <div key={m.id} style={{
                                background: T.card, borderRadius: T.radiusSm, padding: "12px 14px",
                                boxShadow: T.shadowCard, border: `1px solid ${T.border}`,
                                display: "flex", alignItems: "center", gap: 12,
                            }}>
                                <div style={{
                                    width: 36, height: 36, borderRadius: "50%",
                                    background: T.primaryGhost, display: "flex", alignItems: "center",
                                    justifyContent: "center", fontWeight: 700, fontSize: 13, color: T.primary, flexShrink: 0,
                                }}>
                                    {(m.name ?? "?").split(" ").map(p => p[0]).join("").slice(0, 2).toUpperCase()}
                                </div>
                                <div>
                                    <div style={{ fontWeight: 600, color: T.text, fontSize: 14 }}>{m.name}</div>
                                    {m.email && <div style={{ fontSize: 11, color: T.textSub }}>{m.email}</div>}
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>

            {/* Class Report full-screen overlay */}
            {reportOpen && (
                <ClassReportSheet classId={cls.id} onClose={() => setReportOpen(false)} />
            )}
        </div>
    );
}


