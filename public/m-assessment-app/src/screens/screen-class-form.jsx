// screen-class-form.jsx — Add / Edit a MentorshipClass
import { useState } from "react";
import { T } from "../constants.js";
import api from "../services/api.service.js";

const inputStyle = {
    width: "100%", padding: "11px 13px", borderRadius: T.radiusSm,
    border: `1px solid ${T.border}`, fontSize: 14, boxSizing: "border-box",
    background: "#fff", color: T.text, fontFamily: "inherit", outline: "none",
};

function Field({ label, required, children }) {
    return (
        <div style={{ marginBottom: 16 }}>
            <label style={{ display: "block", fontSize: 12, fontWeight: 700, color: T.textSub, marginBottom: 6, textTransform: "uppercase", letterSpacing: 0.5 }}>
                {label}{required && <span style={{ color: "#EF4444", marginLeft: 2 }}>*</span>}
            </label>
            {children}
        </div>
    );
}

export function ClassFormScreen({ trainingId, existingClass, onBack, onSaved }) {
    const isEdit = !!existingClass;

    const [name, setName]           = useState(existingClass?.name ?? "");
    const [startDate, setStartDate] = useState(existingClass?.start_date ?? "");
    const [endDate, setEndDate]     = useState(existingClass?.end_date ?? "");
    const [notes, setNotes]         = useState(existingClass?.notes ?? "");
    const [saving, setSaving]       = useState(false);
    const [error, setError]         = useState(null);

    const handleSave = async () => {
        if (!name.trim()) { setError("Class name is required."); return; }
        setSaving(true); setError(null);
        try {
            const payload = {
                name: name.trim(),
                start_date: startDate || null,
                end_date: endDate || null,
                notes: notes || null,
            };
            let result;
            if (isEdit) {
                result = await api.mentorships.updateClass(trainingId, existingClass.id, payload);
            } else {
                result = await api.mentorships.createClass(trainingId, payload);
            }
            onSaved?.(result?.data);
        } catch (e) {
            setError(e.message ?? "Failed to save class.");
        } finally {
            setSaving(false);
        }
    };

    return (
        <div style={{ display: "flex", flexDirection: "column", height: "100%", background: T.bg }}>
            {/* ── Gradient Header ── */}
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
                    <div style={{ flex: 1, fontWeight: 800, fontSize: 16, color: "white" }}>
                        {isEdit ? "Edit Class" : "New Class"}
                    </div>
                    <button onClick={handleSave} disabled={saving} style={{
                        padding: "8px 18px", borderRadius: T.radiusSm, border: "none",
                        background: saving ? "rgba(255,255,255,0.15)" : "rgba(255,255,255,0.95)",
                        color: saving ? "rgba(255,255,255,0.5)" : "#3730A3",
                        fontSize: 13, fontWeight: 700, cursor: saving ? "not-allowed" : "pointer",
                    }}>
                        {saving ? "Saving…" : isEdit ? "Update" : "Create"}
                    </button>
                </div>
            </div>

            <div style={{ flex: 1, overflowY: "auto", padding: 16 }}>
                {error && (
                    <div style={{ background: "#FEF2F2", border: "1px solid #FECACA", borderRadius: T.radiusSm, padding: "10px 14px", marginBottom: 14, color: "#DC2626", fontSize: 13 }}>
                        {error}
                    </div>
                )}

                <div style={{ background: T.card, borderRadius: T.radiusSm, padding: 16, boxShadow: T.shadowCard }}>
                    <Field label="Class Name" required>
                        <input
                            value={name}
                            onChange={e => setName(e.target.value)}
                            placeholder="e.g. Cohort A — Nairobi West"
                            style={inputStyle}
                        />
                    </Field>
                    <Field label="Start Date">
                        <input type="date" value={startDate} onChange={e => setStartDate(e.target.value)} style={inputStyle} />
                    </Field>
                    <Field label="End Date">
                        <input type="date" value={endDate} onChange={e => setEndDate(e.target.value)} style={inputStyle} />
                    </Field>
                    <Field label="Notes">
                        <textarea
                            value={notes}
                            onChange={e => setNotes(e.target.value)}
                            rows={3}
                            placeholder="Optional notes about this class…"
                            style={{ ...inputStyle, resize: "vertical" }}
                        />
                    </Field>
                </div>
            </div>
        </div>
    );
}
