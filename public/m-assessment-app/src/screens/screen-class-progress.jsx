import { useState, useEffect } from "react";
import { T } from "../constants.js";
import api from "../services/api.service.js";

export function ClassProgressScreen({ cls, user, onBack }) {
    const [detail, setDetail]   = useState(null);
    const [loading, setLoading] = useState(true);
    const [attending, setAttending] = useState({});

    useEffect(() => {
        api.me.classDetail(cls.id)
            .then(d => setDetail(d?.data ?? null))
            .catch(() => {})
            .finally(() => setLoading(false));
    }, [cls.id]);

    const confirmAttendance = async (moduleId) => {
        setAttending(prev => ({ ...prev, [moduleId]: true }));
        try {
            await api.me.attend(cls.id, moduleId);
            setDetail(prev => {
                if (!prev) return prev;
                return {
                    ...prev,
                    modules: prev.modules.map(m =>
                        m.id === moduleId ? { ...m, attended: true } : m
                    ),
                };
            });
        } catch (e) {
            // Silent — sync queue handles retry
        } finally {
            setAttending(prev => ({ ...prev, [moduleId]: false }));
        }
    };

    const data = detail ?? cls;
    const modules = data?.modules ?? [];

    return (
        <div style={{ display: "flex", flexDirection: "column", height: "100%", background: T.bg }}>
            <div style={{ padding: "16px 20px 12px", background: T.card, borderBottom: `1px solid ${T.border}`, display: "flex", gap: 12, alignItems: "center" }}>
                <button onClick={onBack} style={{ border: "none", background: "none", cursor: "pointer", padding: 4 }}>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke={T.text} strokeWidth="2.5"><path d="M19 12H5M12 19l-7-7 7-7" /></svg>
                </button>
                <div>
                    <div style={{ fontSize: 16, fontWeight: 800, color: T.text }}>{data.name}</div>
                    <div style={{ fontSize: 12, color: T.textSub }}>{data.progress_percentage ?? 0}% complete</div>
                </div>
            </div>

            <div style={{ flex: 1, overflowY: "auto", padding: 16, display: "flex", flexDirection: "column", gap: 10 }}>
                {loading && <div style={{ color: T.textSub, textAlign: "center", paddingTop: 32 }}>Loading…</div>}
                {modules.map(m => (
                    <div
                        key={m.id}
                        style={{
                            background: T.card, borderRadius: T.radiusSm, padding: "14px 16px",
                            boxShadow: T.shadowCard, border: `1px solid ${T.border}`,
                        }}
                    >
                        <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start" }}>
                            <div>
                                <div style={{ fontWeight: 700, color: T.text, fontSize: 14 }}>{m.name}</div>
                                <div style={{ fontSize: 12, color: T.textSub, marginTop: 2, textTransform: "capitalize" }}>
                                    {m.status?.replace("_", " ") ?? "not started"}
                                </div>
                            </div>
                            {m.attended && (
                                <div style={{ fontSize: 11, fontWeight: 700, background: "#D1FAE5", color: "#065F46", padding: "3px 8px", borderRadius: 6 }}>
                                    ✓ Attended
                                </div>
                            )}
                        </div>
                        {m.status === "in_progress" && !m.attended && (
                            <button
                                onClick={() => confirmAttendance(m.id)}
                                disabled={attending[m.id]}
                                style={{
                                    marginTop: 10, width: "100%", background: "#0EA5E9",
                                    border: "none", borderRadius: T.radiusXs, color: "#fff",
                                    fontWeight: 700, fontSize: 13, padding: "10px 0",
                                    cursor: attending[m.id] ? "not-allowed" : "pointer",
                                    opacity: attending[m.id] ? 0.7 : 1,
                                }}
                            >
                                {attending[m.id] ? "Confirming…" : "Confirm My Attendance"}
                            </button>
                        )}
                    </div>
                ))}
            </div>
        </div>
    );
}
