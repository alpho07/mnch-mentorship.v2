import { useState, useEffect } from "react";
import { T } from "../constants.js";
import { BackButton } from "../components/shared-components.jsx";
import api from "../services/api.service.js";

const FIELDS = [
    { key: "etat_plus", label: "ETAT+" },
    { key: "comprehensive_newborn_care", label: "Comprehensive NB Care" },
    { key: "imnci", label: "IMNCI" },
    { key: "type_1_diabetes", label: "Type 1 Diabetes" },
    { key: "essential_newborn_care", label: "Essential NB Care" },
];

// ── Number stepper ─────────────────────────────────────────────────────────────
function Stepper({ value, onChange }) {
    const n = parseInt(value, 10) || 0;
    return (
        <div style={{ display: "flex", alignItems: "center", gap: 6 }}>
            <button onClick={() => onChange(Math.max(0, n - 1))} style={{
                width: 28, height: 28, borderRadius: 8, border: `1px solid ${T.border}`,
                background: T.borderLight, fontSize: 16, cursor: "pointer",
                display: "flex", alignItems: "center", justifyContent: "center",
            }}>−</button>
            <input
                type="number" min="0" value={n}
                onChange={e => onChange(Math.max(0, parseInt(e.target.value, 10) || 0))}
                style={{
                    width: 48, textAlign: "center", padding: "4px 0",
                    border: `1px solid ${T.border}`, borderRadius: 8,
                    fontSize: 14, fontWeight: 700, color: T.text, fontFamily: "inherit",
                    background: T.borderLight,
                }}
            />
            <button onClick={() => onChange(n + 1)} style={{
                width: 28, height: 28, borderRadius: 8, border: `1px solid ${T.border}`,
                background: T.borderLight, fontSize: 16, cursor: "pointer",
                display: "flex", alignItems: "center", justifyContent: "center",
            }}>+</button>
        </div>
    );
}

// ── Cadre row ──────────────────────────────────────────────────────────────────
function CadreRow({ cadre, values, onChange }) {
    const [open, setOpen] = useState(false);
    const total = FIELDS.reduce((s, f) => s + (parseInt(values[f.key], 10) || 0), 0);

    return (
        <div style={{ background: T.card, borderRadius: T.radius, marginBottom: 10, overflow: "hidden", boxShadow: T.shadow }}>
            <button onClick={() => setOpen(o => !o)} style={{
                width: "100%", padding: "14px 16px", border: "none", background: "none",
                cursor: "pointer", display: "flex", alignItems: "center", gap: 10, textAlign: "left",
            }}>
                <div style={{
                    width: 36, height: 36, borderRadius: 10, background: "#EDE9FE",
                    display: "flex", alignItems: "center", justifyContent: "center",
                    fontSize: 18, flexShrink: 0,
                }}>👤</div>
                <div style={{ flex: 1 }}>
                    <div style={{ fontWeight: 700, fontSize: 14, color: T.text }}>{cadre.cadre_name}</div>
                    {total > 0 && (
                        <div style={{ fontSize: 11, color: "#7C3AED", marginTop: 2 }}>
                            {total} trained
                        </div>
                    )}
                </div>
                <span style={{ color: T.textMuted, fontSize: 16, transform: open ? "rotate(180deg)" : "none", transition: "transform 0.2s" }}>▾</span>
            </button>

            {open && (
                <div style={{ padding: "0 16px 16px", borderTop: `1px solid ${T.border}` }}>
                    {FIELDS.map(f => (
                        <div key={f.key} style={{ display: "flex", justifyContent: "space-between", alignItems: "center", paddingTop: 12 }}>
                            <div style={{ fontSize: 13, color: T.textMid, flex: 1 }}>{f.label}</div>
                            <Stepper value={values[f.key] ?? 0} onChange={v => onChange(f.key, v)} />
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}

// ── Main screen ────────────────────────────────────────────────────────────────
export function HumanResourcesScreen({ assessment, onBack, onComplete }) {
    const [cadres, setCadres] = useState([]);
    const [values, setValues] = useState({});   // { cadreId: { etat_plus: 0, ... } }
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState(null);

    useEffect(() => {
        api.humanResources.get(assessment.id)
            .then(res => {
                const rows = Array.isArray(res?.data) ? res.data : [];
                setCadres(rows);
                // Build initial values map
                const init = {};
                rows.forEach(c => {
                    init[c.cadre_id] = {
                        etat_plus: c.etat_plus ?? 0,
                        comprehensive_newborn_care: c.comprehensive_newborn_care ?? 0,
                        imnci: c.imnci ?? 0,
                        type_1_diabetes: c.type_1_diabetes ?? 0,
                        essential_newborn_care: c.essential_newborn_care ?? 0,
                    };
                });
                setValues(init);
            })
            .catch(e => setError(e.message || "Failed to load"))
            .finally(() => setLoading(false));
    }, [assessment.id]);

    const handleChange = (cadreId, field, val) => {
        setValues(prev => ({
            ...prev,
            [cadreId]: { ...(prev[cadreId] ?? {}), [field]: val },
        }));
    };

    const handleSave = async () => {
        setSaving(true);
        setError(null);
        try {
            const responses = cadres.map(c => ({
                cadre_id: c.cadre_id,
                ...FIELDS.reduce((acc, f) => ({ ...acc, [f.key]: values[c.cadre_id]?.[f.key] ?? 0 }), {}),
            }));
            await api.humanResources.save(assessment.id, responses);
            onComplete?.(assessment.id);
        } catch (e) {
            setError(e.message || "Save failed");
        } finally {
            setSaving(false);
        }
    };

    return (
        <div style={{ display: "flex", flexDirection: "column", height: "100%" }}>
            {/* Header */}
            <div style={{
                background: "linear-gradient(135deg, #4C1D95 0%, #7C3AED 100%)",
                padding: "20px 20px 24px",
            }}>
                <BackButton onBack={onBack} light />
                <div style={{ marginTop: 12, color: "white", fontSize: 18, fontWeight: 800 }}>
                    Human Resources
                </div>
                <div style={{ color: "rgba(255,255,255,0.6)", fontSize: 13, marginTop: 2 }}>
                    {assessment.facility_name} · Staff training counts
                </div>
            </div>

            {/* Content */}
            <div style={{ flex: 1, overflowY: "auto", padding: "14px 16px 100px", background: T.bg }}>
                {loading && (
                    <div style={{ textAlign: "center", padding: "40px 0", color: T.textMuted }}>
                        <div style={{ fontSize: 30, marginBottom: 8 }}>⏳</div>Loading cadres…
                    </div>
                )}
                {error && (
                    <div style={{ background: "#FEE2E2", color: "#991B1B", borderRadius: T.radiusSm, padding: "10px 14px", fontSize: 13, marginBottom: 12 }}>
                        {error}
                    </div>
                )}
                {!loading && cadres.length === 0 && (
                    <div style={{ textAlign: "center", padding: "40px 24px", color: T.textMuted }}>
                        <div style={{ fontSize: 40, marginBottom: 12 }}>👥</div>
                        No cadres configured.
                    </div>
                )}
                {!loading && cadres.map(c => (
                    <CadreRow
                        key={c.cadre_id}
                        cadre={c}
                        values={values[c.cadre_id] ?? {}}
                        onChange={(field, val) => handleChange(c.cadre_id, field, val)}
                    />
                ))}
            </div>

            {/* Save */}
            {!loading && cadres.length > 0 && (
                <div style={{ padding: "12px 16px", background: T.card, borderTop: `1px solid ${T.border}` }}>
                    <button onClick={handleSave} disabled={saving} style={{
                        width: "100%", padding: 15, borderRadius: T.radius, border: "none",
                        background: saving ? "#D1D5DB" : "linear-gradient(135deg, #4C1D95, #7C3AED)",
                        color: "white", fontSize: 15, fontWeight: 700,
                        cursor: saving ? "not-allowed" : "pointer",
                    }}>
                        {saving ? "Saving…" : "Save Human Resources →"}
                    </button>
                </div>
            )}
        </div>
    );
}
