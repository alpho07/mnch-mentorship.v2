import { useState } from "react";
import { T } from "../constants.js";
import api from "../services/api.service.js";

const inputStyle = {
    width: "100%", padding: "10px 12px", borderRadius: T.radiusSm,
    border: `1px solid ${T.border}`, fontSize: 14, boxSizing: "border-box",
    background: "#fff", color: T.text, outline: "none",
    fontFamily: "inherit",
};

function Field({ label, children }) {
    return (
        <div style={{ marginBottom: 14 }}>
            <label style={{ display: "block", fontSize: 12, fontWeight: 600, color: T.textSub, marginBottom: 5, textTransform: "uppercase", letterSpacing: 0.4 }}>
                {label}
            </label>
            {children}
        </div>
    );
}

export function SessionNotesScreen({ session, onBack, onSaved }) {
    const [actualDate, setActualDate] = useState(session.actual_date ?? "");
    const [startTime, setStartTime]   = useState(session.actual_time ?? "");
    const [location, setLocation]     = useState(session.location ?? "");
    const [notes, setNotes]           = useState(session.notes ?? "");
    const [status, setStatus]         = useState(session.status ?? "scheduled");
    const [saving, setSaving]         = useState(false);
    const [error, setError]           = useState(null);

    const handleSave = async () => {
        setSaving(true); setError(null);
        try {
            await api.sessions.update(session.id, {
                actual_date: actualDate || null,
                actual_time: startTime || null,
                location: location || null,
                notes: notes || null,
                status,
            });
            onSaved?.({ ...session, actual_date: actualDate, actual_time: startTime, location, notes, status });
        } catch (e) {
            setError(e.message ?? "Failed to save.");
        } finally {
            setSaving(false);
        }
    };

    const STATUS_OPTIONS = [
        { value: "scheduled",   label: "Scheduled" },
        { value: "in_progress", label: "In Progress" },
        { value: "completed",   label: "Completed" },
        { value: "cancelled",   label: "Cancelled" },
    ];

    return (
        <div style={{ display: "flex", flexDirection: "column", height: "100%", background: T.bg }}>
            {/* Header */}
            <div style={{
                background: "linear-gradient(135deg, #1E1B4B 0%, #3730A3 60%, #6366F1 100%)",
                padding: "40px 16px 14px",
                position: "relative", overflow: "hidden",
            }}>
                <div style={{ position: "absolute", width: 120, height: 120, borderRadius: "50%", background: "radial-gradient(circle, rgba(165,180,252,0.15) 0%, transparent 70%)", top: -30, right: -20 }} />
                <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
                    <button onClick={onBack} style={{ background: "rgba(255,255,255,0.12)", border: "none", cursor: "pointer", padding: "6px 10px", borderRadius: 10, display: "flex", alignItems: "center", gap: 4 }}>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="2.5"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                        <span style={{ fontSize: 12, color: "rgba(255,255,255,0.8)", fontWeight: 600 }}>Back</span>
                    </button>
                    <div style={{ flex: 1 }}>
                        <div style={{ fontWeight: 700, fontSize: 15, color: "white" }}>{session.title ?? "Session Notes"}</div>
                        <div style={{ fontSize: 12, color: "rgba(255,255,255,0.55)" }}>Session {session.session_number}</div>
                    </div>
                    <button
                        onClick={handleSave}
                        disabled={saving}
                        style={{
                            padding: "8px 18px", borderRadius: T.radiusSm,
                            background: saving ? "rgba(255,255,255,0.15)" : "rgba(255,255,255,0.95)",
                            border: "none", color: saving ? "rgba(255,255,255,0.5)" : "#3730A3",
                            fontSize: 13, fontWeight: 700,
                            cursor: saving ? "not-allowed" : "pointer",
                        }}
                    >
                        {saving ? "Saving…" : "Save"}
                    </button>
                </div>
            </div>

            {/* Scheduled info banner */}
            {(session.scheduled_date || session.scheduled_time) && (
                <div style={{ background: T.bg, borderBottom: `1px solid ${T.border}`, padding: "8px 16px", display: "flex", gap: 16, fontSize: 12, color: T.textSub }}>
                    <span>📅 Scheduled: {session.scheduled_date ?? ""}{session.scheduled_time ? ` at ${session.scheduled_time}` : ""}</span>
                    {session.facilitator && <span>👤 {session.facilitator}</span>}
                </div>
            )}

            <div style={{ flex: 1, overflowY: "auto", padding: 16 }}>
                {error && (
                    <div style={{ background: "#FEF2F2", border: "1px solid #FECACA", borderRadius: T.radiusSm, padding: "10px 14px", marginBottom: 14, color: "#DC2626", fontSize: 13 }}>
                        {error}
                    </div>
                )}
                {!navigator.onLine && (
                    <div style={{ background: "#FFFBEB", border: "1px solid #FCD34D", borderRadius: T.radiusSm, padding: 10, marginBottom: 14, fontSize: 13, color: "#92400E" }}>
                        Offline — notes will sync when you reconnect.
                    </div>
                )}

                <div style={{ background: T.card, borderRadius: T.radiusSm, padding: 16, boxShadow: T.shadowCard, marginBottom: 14 }}>
                    <Field label="Status">
                        <div style={{ display: "flex", gap: 6, flexWrap: "wrap" }}>
                            {STATUS_OPTIONS.map(opt => (
                                <button
                                    key={opt.value}
                                    onClick={() => setStatus(opt.value)}
                                    style={{
                                        padding: "6px 14px", borderRadius: 20, fontSize: 12, fontWeight: 600,
                                        border: `1.5px solid ${status === opt.value ? T.primary : T.border}`,
                                        background: status === opt.value ? T.primaryGhost : "#fff",
                                        color: status === opt.value ? T.primary : T.textSub,
                                        cursor: "pointer",
                                    }}
                                >
                                    {opt.label}
                                </button>
                            ))}
                        </div>
                    </Field>
                </div>

                <div style={{ background: T.card, borderRadius: T.radiusSm, padding: 16, boxShadow: T.shadowCard }}>
                    <Field label="Actual Date">
                        <input type="date" value={actualDate} onChange={e => setActualDate(e.target.value)} style={inputStyle} />
                    </Field>
                    <Field label="Start Time">
                        <input type="time" value={startTime} onChange={e => setStartTime(e.target.value)} style={inputStyle} />
                    </Field>
                    <Field label="Location">
                        <input placeholder="Ward, room, or facility" value={location} onChange={e => setLocation(e.target.value)} style={inputStyle} />
                    </Field>
                    <Field label="Notes / Observations">
                        <textarea
                            value={notes}
                            onChange={e => setNotes(e.target.value)}
                            rows={5}
                            placeholder="What was observed, discussed, or noted during this session…"
                            style={{ ...inputStyle, resize: "vertical", lineHeight: 1.6 }}
                        />
                    </Field>
                </div>
            </div>
        </div>
    );
}
