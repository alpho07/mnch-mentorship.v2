// src/screens/screen-module-detail.jsx
import { useState, useEffect } from "react";
import { T } from "../constants.js";
import api from "../services/api.service.js";

const TEAL = "#0097A7";

const SESSION_STATUS = {
    scheduled:   { bg: "#F3F4F6", color: "#6B7280" },
    in_progress: { bg: "#FEF3C7", color: "#92400E" },
    completed:   { bg: "#D1FAE5", color: "#065F46" },
    cancelled:   { bg: "#FEE2E2", color: "#991B1B" },
};

function SessionCard({ session, onOpen, onRemove, removing }) {
    const s = SESSION_STATUS[session.status] ?? SESSION_STATUS.scheduled;
    const displayDate = session.actual_date ?? session.scheduled_date ?? null;
    const displayTime = session.actual_time ?? session.scheduled_time ?? null;
    const canRemove = session.status === "scheduled" && onRemove;

    return (
        <div style={{
            background: T.card, borderRadius: T.radiusSm, border: `1px solid ${T.border}`,
            marginBottom: 8, overflow: "hidden",
        }}>
            <div style={{ height: 3, background: session.status === "completed" ? "#10B981" : session.status === "in_progress" ? "#F59E0B" : T.borderLight }} />
            <div style={{ padding: "12px 14px" }}>
                <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start", marginBottom: 6 }}>
                    <div
                        style={{ flex: 1, marginRight: 8, cursor: onOpen ? "pointer" : "default" }}
                        onClick={() => onOpen?.(session)}
                    >
                        <div style={{ fontSize: 14, fontWeight: 600, color: T.text }}>
                            {session.title ?? `Session ${session.session_number}`}
                        </div>
                        {session.description && (
                            <div style={{ fontSize: 12, color: T.textSub, marginTop: 2 }}>{session.description}</div>
                        )}
                    </div>
                    <span style={{ fontSize: 10, fontWeight: 700, padding: "2px 7px", borderRadius: 5, background: s.bg, color: s.color, flexShrink: 0 }}>
                        {session.status?.replace(/_/g, " ")}
                    </span>
                </div>

                <div style={{ display: "flex", flexWrap: "wrap", gap: 12, fontSize: 12, color: T.textSub }}>
                    {displayDate && (
                        <span style={{ display: "flex", alignItems: "center", gap: 4 }}>
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            {displayDate}{displayTime ? ` · ${displayTime}` : ""}
                        </span>
                    )}
                    {session.location && (
                        <span style={{ display: "flex", alignItems: "center", gap: 4 }}>
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
                            {session.location}
                        </span>
                    )}
                    {session.facilitator && (
                        <span style={{ display: "flex", alignItems: "center", gap: 4 }}>
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            {session.facilitator}
                        </span>
                    )}
                    {session.duration_minutes && (
                        <span>{session.duration_minutes} min</span>
                    )}
                </div>

                {session.notes && (
                    <div style={{ marginTop: 8, fontSize: 12, color: T.textSub, background: T.bg, borderRadius: 6, padding: "6px 10px", borderLeft: `3px solid ${T.border}` }}>
                        {session.notes}
                    </div>
                )}

                <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginTop: 8 }}>
                    {session.attendance_taken && (
                        <div style={{ fontSize: 11, color: "#10B981", fontWeight: 600 }}>✓ Attendance recorded</div>
                    )}
                    {!session.attendance_taken && <div />}
                    {canRemove && (
                        <button
                            onClick={() => onRemove(session.id)}
                            disabled={removing === session.id}
                            style={{
                                border: "none", background: "#FEE2E2", color: "#991B1B",
                                borderRadius: 6, padding: "4px 10px", fontSize: 11,
                                fontWeight: 700, cursor: removing === session.id ? "not-allowed" : "pointer",
                                opacity: removing === session.id ? 0.6 : 1,
                            }}
                        >
                            {removing === session.id ? "Removing…" : "Remove"}
                        </button>
                    )}
                </div>
            </div>
        </div>
    );
}

function AddSessionSheet({ moduleId, onAdd, onClose }) {
    const [templates, setTemplates] = useState([]);
    const [loading, setLoading] = useState(true);
    const [addingId, setAddingId] = useState(null);
    const [err, setErr] = useState(null);

    useEffect(() => {
        api.modules.sessionTemplates(moduleId)
            .then(d => setTemplates(d?.data ?? []))
            .catch(() => setTemplates([]))
            .finally(() => setLoading(false));
    }, [moduleId]);

    const handleAdd = async (template) => {
        setAddingId(template.id);
        setErr(null);
        try {
            const d = await api.modules.addSession(moduleId, template.id);
            onAdd(d?.data);
            setTemplates(prev => prev.filter(t => t.id !== template.id));
        } catch (e) {
            setErr(e.message ?? "Failed to add session.");
            setAddingId(null);
        }
    };

    return (
        <>
            {/* Backdrop */}
            <div
                onClick={onClose}
                style={{ position: "fixed", inset: 0, background: "rgba(0,0,0,0.45)", zIndex: 50 }}
            />
            {/* Sheet */}
            <div style={{
                position: "fixed", left: 0, right: 0, bottom: 0, zIndex: 51,
                background: T.card, borderRadius: "20px 20px 0 0",
                padding: "0 0 32px",
                maxHeight: "70vh", display: "flex", flexDirection: "column",
            }}>
                {/* Drag handle */}
                <div style={{ display: "flex", justifyContent: "center", padding: "10px 0 2px" }}>
                    <div style={{ width: 36, height: 4, borderRadius: 2, background: T.borderLight }} />
                </div>
                {/* Header */}
                <div style={{ padding: "8px 20px 12px", display: "flex", justifyContent: "space-between", alignItems: "center" }}>
                    <div style={{ fontSize: 16, fontWeight: 800, color: T.text }}>Add Session</div>
                    <button onClick={onClose} style={{ border: "none", background: "none", cursor: "pointer", padding: 4 }}>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke={T.textSub} strokeWidth="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                {/* Body */}
                <div style={{ flex: 1, overflowY: "auto", padding: "0 16px" }}>
                    {err && (
                        <div style={{ background: "#FEE2E2", color: "#991B1B", borderRadius: T.radiusXs, padding: "8px 12px", fontSize: 13, marginBottom: 10 }}>
                            {err}
                        </div>
                    )}
                    {loading && (
                        <div style={{ color: T.textSub, fontSize: 13, textAlign: "center", padding: "20px 0" }}>Loading available sessions…</div>
                    )}
                    {!loading && templates.length === 0 && (
                        <div style={{ textAlign: "center", padding: "24px 0" }}>
                            <div style={{ fontSize: 13, color: T.textSub }}>All sessions from this module have been added.</div>
                        </div>
                    )}
                    {templates.map(t => (
                        <div key={t.id} style={{
                            display: "flex", justifyContent: "space-between", alignItems: "center",
                            padding: "12px 14px", background: T.bg, borderRadius: T.radiusSm,
                            border: `1px solid ${T.border}`, marginBottom: 8,
                        }}>
                            <div style={{ flex: 1, marginRight: 10 }}>
                                <div style={{ fontSize: 14, fontWeight: 600, color: T.text }}>{t.name}</div>
                                {t.description && <div style={{ fontSize: 12, color: T.textSub, marginTop: 2 }}>{t.description}</div>}
                                {t.duration_minutes && <div style={{ fontSize: 11, color: T.textSub, marginTop: 2 }}>{t.duration_minutes} min</div>}
                            </div>
                            <button
                                onClick={() => handleAdd(t)}
                                disabled={addingId === t.id}
                                style={{
                                    border: "none", background: TEAL, color: "#fff",
                                    borderRadius: 8, padding: "7px 14px", fontSize: 12,
                                    fontWeight: 700, cursor: addingId === t.id ? "not-allowed" : "pointer",
                                    opacity: addingId === t.id ? 0.7 : 1, flexShrink: 0,
                                }}
                            >
                                {addingId === t.id ? "Adding…" : "Add"}
                            </button>
                        </div>
                    ))}
                </div>
            </div>
        </>
    );
}

export function ModuleDetailScreen({ module: mod, user, onBack, onOpenAttendance, onOpenSession }) {
    const [busy, setBusy]               = useState(false);
    const [localStatus, setLocalStatus] = useState(mod.status);
    const [error, setError]             = useState(null);
    const [sessions, setSessions]       = useState([]);
    const [loadingSessions, setLoadingSessions] = useState(true);

    const [activeTab, setActiveTab]     = useState("sessions");

    // Attendance tab
    const [roster, setRoster]           = useState(null);
    const [rosterLoading, setRosterLoading] = useState(false);
    const [rosterLoaded, setRosterLoaded]   = useState(false);
    const [saving, setSaving]           = useState({});

    // Session management
    const [addSheet, setAddSheet]       = useState(false);
    const [removingId, setRemovingId]   = useState(null);

    useEffect(() => {
        if (mod?.id) {
            api.modules.sessions(mod.id)
                .then(d => setSessions(d?.data ?? d ?? []))
                .catch(() => {})
                .finally(() => setLoadingSessions(false));
        }
    }, [mod?.id]);

    // Load attendance roster lazily on tab switch
    useEffect(() => {
        if (activeTab === "attendance" && !rosterLoaded) {
            setRosterLoading(true);
            api.attendance.roster(mod.id)
                .then(d => setRoster(Array.isArray(d?.data) ? d.data : []))
                .catch(() => setRoster([]))
                .finally(() => { setRosterLoading(false); setRosterLoaded(true); });
        }
    }, [activeTab, mod.id, rosterLoaded]);

    const handleStart = async () => {
        setBusy(true); setError(null);
        try {
            await api.modules.start(mod.id);
            setLocalStatus("in_progress");
        } catch (e) {
            setError(e.message ?? "Failed to start module.");
        } finally { setBusy(false); }
    };

    const handleComplete = async () => {
        setBusy(true); setError(null);
        try {
            await api.modules.complete(mod.id);
            setLocalStatus("completed");
        } catch (e) {
            setError(e.message ?? "Failed to complete module.");
        } finally { setBusy(false); }
    };

    const handleMark = async (participantId, status) => {
        setSaving(prev => ({ ...prev, [participantId]: true }));
        try {
            await api.attendance.mark(mod.id, participantId, status);
            setRoster(prev => prev.map(p =>
                p.participant_id === participantId ? { ...p, status } : p
            ));
        } finally {
            setSaving(prev => ({ ...prev, [participantId]: false }));
        }
    };

    const handleRemoveSession = async (sessionId) => {
        setRemovingId(sessionId);
        setError(null);
        try {
            await api.sessions.remove(sessionId, mod.id);
            setSessions(prev => prev.filter(s => s.id !== sessionId));
        } catch (e) {
            setError(e.message ?? "Failed to remove session.");
        } finally {
            setRemovingId(null);
        }
    };

    const handleSessionAdded = (newSession) => {
        if (newSession) setSessions(prev => [...prev, newSession]);
    };

    const completedSessions = sessions.filter(s => s.status === "completed").length;
    const progressPct = sessions.length > 0 ? Math.round((completedSessions / sessions.length) * 100) : 0;
    const canManageSessions = localStatus !== "completed" && localStatus !== "cancelled";

    return (
        <div style={{ display: "flex", flexDirection: "column", height: "100%", background: T.bg }}>
            {/* ── Gradient Hero ── */}
            <div style={{
                background: "linear-gradient(160deg, #1E1B4B 0%, #3730A3 55%, #818CF8 100%)",
                padding: "44px 20px 20px",
                position: "relative", overflow: "hidden",
            }}>
                <div style={{ position: "absolute", width: 140, height: 140, borderRadius: "50%", background: "radial-gradient(circle, rgba(165,180,252,0.15) 0%, transparent 70%)", top: -30, right: -30 }} />
                <button onClick={onBack} style={{ border: "none", background: "rgba(255,255,255,0.12)", cursor: "pointer", padding: "6px 10px", borderRadius: 10, marginBottom: 14, display: "flex", alignItems: "center", gap: 4 }}>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="2.5"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                    <span style={{ fontSize: 12, color: "rgba(255,255,255,0.8)", fontWeight: 600 }}>Back</span>
                </button>
                <div style={{ fontSize: 11, color: "rgba(255,255,255,0.5)", fontWeight: 600, marginBottom: 4, textTransform: "uppercase", letterSpacing: 0.5 }}>
                    Module {mod.order_sequence}{mod.requires_assessment ? " · Assessment required" : ""}
                </div>
                <div style={{ fontSize: 18, fontWeight: 800, color: "white", lineHeight: 1.3, marginBottom: 10 }}>{mod.name}</div>
                <div style={{ display: "flex", gap: 12, alignItems: "center" }}>
                    <div style={{ flex: 1, height: 4, borderRadius: 4, background: "rgba(255,255,255,0.15)", overflow: "hidden" }}>
                        <div style={{ height: "100%", width: `${progressPct}%`, background: progressPct === 100 ? "#34D399" : "rgba(255,255,255,0.8)", borderRadius: 4, transition: "width 0.6s" }} />
                    </div>
                    <span style={{ fontSize: 12, color: "rgba(255,255,255,0.7)", fontWeight: 600, flexShrink: 0 }}>
                        {completedSessions}/{sessions.length} sessions
                    </span>
                </div>
            </div>

            <div style={{ padding: "12px 16px 0", background: T.card }}>
                {error && (
                    <div style={{ background: "#FEE2E2", color: "#991B1B", borderRadius: T.radiusXs, padding: "10px 14px", fontSize: 13, marginBottom: 10 }}>
                        {error}
                    </div>
                )}

                {/* Status card */}
                <div style={{ background: T.bg, borderRadius: T.radiusSm, padding: "10px 14px", border: `1px solid ${T.border}`, marginBottom: 10 }}>
                    <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: sessions.length > 0 ? 8 : 0 }}>
                        <div>
                            <div style={{ fontSize: 10, color: T.textSub, marginBottom: 1 }}>Status</div>
                            <div style={{ fontWeight: 700, fontSize: 14, color: T.text, textTransform: "capitalize" }}>
                                {localStatus.replace(/_/g, " ")}
                            </div>
                        </div>
                        {sessions.length > 0 && (
                            <div style={{ textAlign: "right" }}>
                                <div style={{ fontSize: 10, color: T.textSub, marginBottom: 1 }}>Sessions</div>
                                <div style={{ fontWeight: 700, fontSize: 14, color: T.text }}>{completedSessions}/{sessions.length}</div>
                            </div>
                        )}
                    </div>
                    {sessions.length > 0 && (
                        <div style={{ height: 4, borderRadius: 4, background: T.borderLight, overflow: "hidden" }}>
                            <div style={{ height: "100%", width: `${progressPct}%`, background: progressPct === 100 ? "#10B981" : T.gradientPrimary, borderRadius: 4 }} />
                        </div>
                    )}
                </div>

                {/* Action buttons */}
                {localStatus === "not_started" && (
                    <button onClick={handleStart} disabled={busy} style={{
                        width: "100%", background: "#10B981", border: "none", borderRadius: T.radiusSm,
                        color: "#fff", fontWeight: 700, fontSize: 15, padding: "12px 0",
                        cursor: busy ? "not-allowed" : "pointer", opacity: busy ? 0.7 : 1, marginBottom: 10,
                    }}>
                        {busy ? "Starting…" : "Start Module"}
                    </button>
                )}

                {localStatus === "in_progress" && (
                    <button onClick={handleComplete} disabled={busy} style={{
                        width: "100%", background: T.card, border: `1.5px solid ${T.border}`,
                        borderRadius: T.radiusSm, color: T.text,
                        fontWeight: 700, fontSize: 15, padding: "12px 0",
                        cursor: busy ? "not-allowed" : "pointer", opacity: busy ? 0.7 : 1, marginBottom: 10,
                    }}>
                        {busy ? "Completing…" : "Complete Module"}
                    </button>
                )}

                {localStatus === "completed" && (
                    <div style={{ textAlign: "center", color: "#10B981", fontWeight: 700, fontSize: 14, padding: "8px 0 10px" }}>
                        ✓ Module completed
                    </div>
                )}

                {/* Tab bar */}
                <div style={{ display: "flex", borderTop: `1px solid ${T.border}` }}>
                    {[
                        { key: "sessions", label: `Sessions${sessions.length > 0 ? ` (${sessions.length})` : ""}` },
                        { key: "attendance", label: "Attendance" },
                    ].map(tab => (
                        <button
                            key={tab.key}
                            onClick={() => setActiveTab(tab.key)}
                            style={{
                                flex: 1, padding: "11px 0", border: "none", background: "none",
                                fontWeight: activeTab === tab.key ? 700 : 500,
                                fontSize: 13,
                                color: activeTab === tab.key ? TEAL : T.textSub,
                                borderBottom: activeTab === tab.key ? `2px solid ${TEAL}` : "2px solid transparent",
                                cursor: "pointer",
                            }}
                        >
                            {tab.label}
                        </button>
                    ))}
                </div>
            </div>

            {/* ── Tab content ── */}
            <div style={{ flex: 1, overflowY: "auto", padding: 16 }}>

                {/* ── Sessions tab ── */}
                {activeTab === "sessions" && (
                    <>
                        {canManageSessions && (
                            <button
                                onClick={() => setAddSheet(true)}
                                style={{
                                    width: "100%", border: `1.5px dashed ${TEAL}`, background: "transparent",
                                    color: TEAL, borderRadius: T.radiusSm, padding: "10px 0",
                                    fontWeight: 700, fontSize: 13, cursor: "pointer", marginBottom: 12,
                                    display: "flex", alignItems: "center", justifyContent: "center", gap: 6,
                                }}
                            >
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                Add Session
                            </button>
                        )}

                        {loadingSessions && <div style={{ color: T.textSub, fontSize: 13 }}>Loading sessions…</div>}

                        {!loadingSessions && sessions.length === 0 && (
                            <div style={{ textAlign: "center", padding: "24px 0" }}>
                                <div style={{ fontSize: 13, color: T.textSub }}>No sessions scheduled.</div>
                                {canManageSessions && (
                                    <div style={{ fontSize: 12, color: T.textSub, marginTop: 4 }}>Use "Add Session" to add from this module's template.</div>
                                )}
                            </div>
                        )}

                        {sessions.map(s => (
                            <SessionCard
                                key={s.id}
                                session={s}
                                onOpen={onOpenSession}
                                onRemove={canManageSessions ? handleRemoveSession : null}
                                removing={removingId}
                            />
                        ))}
                    </>
                )}

                {/* ── Attendance tab ── */}
                {activeTab === "attendance" && (
                    <>
                        {rosterLoading && (
                            <div style={{ color: T.textSub, textAlign: "center", paddingTop: 32, fontSize: 13 }}>Loading roster…</div>
                        )}
                        {!rosterLoading && roster !== null && roster.length === 0 && (
                            <div style={{ textAlign: "center", padding: "32px 0" }}>
                                <div style={{ fontSize: 13, color: T.textSub }}>No mentees enrolled yet.</div>
                            </div>
                        )}
                        {(roster ?? []).map(p => (
                            <div
                                key={p.participant_id}
                                style={{
                                    background: T.card, borderRadius: T.radiusSm,
                                    padding: "12px 16px", marginBottom: 10, boxShadow: T.shadowCard,
                                    display: "flex", justifyContent: "space-between", alignItems: "center",
                                }}
                            >
                                <div>
                                    <div style={{ fontWeight: 600, color: T.text, fontSize: 14 }}>{p.name}</div>
                                    {p.status && p.status !== "not_started" && (
                                        <div style={{
                                            fontSize: 11, fontWeight: 600, marginTop: 2,
                                            color: p.status === "present" ? "#065F46" : "#991B1B",
                                        }}>
                                            {p.status === "present" ? "✓ Present" : "✗ Absent"}
                                        </div>
                                    )}
                                </div>
                                <div style={{ display: "flex", gap: 8 }}>
                                    {["present", "absent"].map(s => (
                                        <button
                                            key={s}
                                            disabled={saving[p.participant_id]}
                                            onClick={() => handleMark(p.participant_id, s)}
                                            style={{
                                                padding: "5px 12px", borderRadius: 8, border: "none",
                                                fontWeight: 700, fontSize: 12, cursor: "pointer",
                                                background: p.status === s
                                                    ? (s === "present" ? "#D1FAE5" : "#FEE2E2")
                                                    : "#F3F4F6",
                                                color: p.status === s
                                                    ? (s === "present" ? "#065F46" : "#991B1B")
                                                    : "#6B7280",
                                                opacity: saving[p.participant_id] ? 0.6 : 1,
                                            }}
                                        >
                                            {s === "present" ? "Present" : "Absent"}
                                        </button>
                                    ))}
                                </div>
                            </div>
                        ))}
                    </>
                )}
            </div>

            {/* Add Session bottom sheet */}
            {addSheet && (
                <AddSessionSheet
                    moduleId={mod.id}
                    onAdd={handleSessionAdded}
                    onClose={() => setAddSheet(false)}
                />
            )}
        </div>
    );
}


