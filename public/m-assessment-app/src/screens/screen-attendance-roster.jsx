// src/screens/screen-attendance-roster.jsx
import { useState, useEffect } from "react";
import { T } from "../constants.js";
import api from "../services/api.service.js";

export function AttendanceRosterScreen({ module: mod, user, onBack }) {
    const [roster, setRoster]   = useState(null);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving]   = useState({});

    useEffect(() => {
        api.attendance.roster(mod.id)
            .then(d => setRoster(Array.isArray(d?.data) ? d.data : []))
            .catch(() => setRoster([]))
            .finally(() => setLoading(false));
    }, [mod.id]);

    const mark = async (participantId, status) => {
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

    return (
        <div style={{ display: "flex", flexDirection: "column", height: "100%", background: T.bg }}>
            <div style={{ padding: "16px 20px 12px", background: T.card, borderBottom: `1px solid ${T.border}`, display: "flex", gap: 12, alignItems: "center" }}>
                <button onClick={onBack} style={{ border: "none", background: "none", cursor: "pointer", padding: 4 }}>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke={T.text} strokeWidth="2.5"><path d="M19 12H5M12 19l-7-7 7-7" /></svg>
                </button>
                <div style={{ fontSize: 16, fontWeight: 800, color: T.text }}>Attendance — {mod.name}</div>
            </div>

            <div style={{ flex: 1, overflowY: "auto", padding: 16 }}>
                {loading && <div style={{ color: T.textSub, textAlign: "center", paddingTop: 32 }}>Loading roster…</div>}
                {(roster ?? []).map(p => (
                    <div
                        key={p.participant_id}
                        style={{
                            background: T.card, borderRadius: T.radiusSm, padding: "12px 16px",
                            marginBottom: 10, boxShadow: T.shadowCard,
                            display: "flex", justifyContent: "space-between", alignItems: "center",
                        }}
                    >
                        <div style={{ fontWeight: 600, color: T.text, fontSize: 14 }}>{p.name}</div>
                        <div style={{ display: "flex", gap: 8 }}>
                            {["present", "absent"].map(s => (
                                <button
                                    key={s}
                                    disabled={saving[p.participant_id]}
                                    onClick={() => mark(p.participant_id, s)}
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
            </div>
        </div>
    );
}
