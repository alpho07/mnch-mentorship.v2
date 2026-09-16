import { useState, useEffect, useRef } from "react";
import { T } from "../constants.js";
import { BackButton } from "../components/shared-components.jsx";
import api from "../services/api.service.js";
import offlineStore from "../services/offline-store.js";
import syncQueue from "../services/sync-queue.js";

// Training area fields only (not total — handled separately)
const TRAINING_FIELDS = [
    { key: "etat_plus", label: "ETAT+" },
    { key: "comprehensive_newborn_care", label: "Comprehensive NB Care" },
    { key: "imnci", label: "IMNCI" },
    { key: "type_1_diabetes", label: "Type 1 Diabetes" },
    { key: "essential_newborn_care", label: "Essential NB Care" },
];

// ── Number stepper ─────────────────────────────────────────────────────────────
function Stepper({ value, onChange, max }) {
    const n = parseInt(value, 10) || 0;
    const atMax = max !== undefined && n >= max;
    return (
        <div style={{ display: "flex", alignItems: "center", gap: 6 }}>
            <button onClick={() => onChange(Math.max(0, n - 1))} style={{
                width: 28, height: 28, borderRadius: 8, border: `1px solid ${T.border}`,
                background: T.borderLight, fontSize: 16, cursor: "pointer",
                display: "flex", alignItems: "center", justifyContent: "center",
            }}>−</button>
            <input
                type="number" min="0" max={max} value={n}
                onChange={e => {
                    const v = Math.max(0, parseInt(e.target.value, 10) || 0);
                    onChange(max !== undefined ? Math.min(v, max) : v);
                }}
                style={{
                    width: 48, textAlign: "center", padding: "4px 0",
                    border: `1px solid ${atMax ? "#FCA5A5" : T.border}`, borderRadius: 8,
                    fontSize: 14, fontWeight: 700, color: T.text, fontFamily: "inherit",
                    background: T.borderLight,
                }}
            />
            <button
                onClick={() => !atMax && onChange(n + 1)}
                disabled={atMax}
                style={{
                    width: 28, height: 28, borderRadius: 8, border: `1px solid ${atMax ? "#FCA5A5" : T.border}`,
                    background: atMax ? "#FEE2E2" : T.borderLight, fontSize: 16,
                    cursor: atMax ? "not-allowed" : "pointer", opacity: atMax ? 0.5 : 1,
                    display: "flex", alignItems: "center", justifyContent: "center",
                }}>+</button>
        </div>
    );
}

// ── Cadre row ──────────────────────────────────────────────────────────────────
function CadreRow({ cadre, values, onChange, isDirty }) {
    const [open, setOpen] = useState(false);
    const totalInFacility = parseInt(values.total_in_facility, 10) || 0;
    const trainedSum = TRAINING_FIELDS.reduce((s, f) => s + (parseInt(values[f.key], 10) || 0), 0);
    const hasAny = totalInFacility > 0 || trainedSum > 0;

    return (
        <div style={{
            background: T.card, borderRadius: T.radius, marginBottom: 10, overflow: "hidden",
            boxShadow: T.shadow,
            border: isDirty
                ? `1.5px solid ${T.primaryLight}`
                : `1px solid ${T.borderLight}`,
        }}>
            <button onClick={() => setOpen(o => !o)} style={{
                width: "100%", padding: "14px 16px", border: "none", background: "none",
                cursor: "pointer", display: "flex", alignItems: "center", gap: 10, textAlign: "left",
            }}>
                <div style={{
                    width: 36, height: 36, borderRadius: 10,
                    background: hasAny ? "#EDE9FE" : T.borderLight,
                    display: "flex", alignItems: "center", justifyContent: "center",
                    fontSize: 18, flexShrink: 0,
                }}>👤</div>
                <div style={{ flex: 1 }}>
                    <div style={{ fontWeight: 700, fontSize: 14, color: T.text }}>{cadre.cadre_name}</div>
                    {hasAny ? (
                        <div style={{ fontSize: 11, color: "#7C3AED", marginTop: 2, fontWeight: 600 }}>
                            {totalInFacility} total · {trainedSum} trained
                        </div>
                    ) : (
                        <div style={{ fontSize: 11, color: T.textMuted, marginTop: 2 }}>Not filled yet</div>
                    )}
                </div>
                {isDirty && (
                    <span style={{
                        padding: "2px 7px", borderRadius: 6, fontSize: 9, fontWeight: 700,
                        background: T.primaryGhost, color: T.primary,
                        border: `1px solid ${T.primaryLight}`, flexShrink: 0,
                    }}>unsaved</span>
                )}
                <span style={{ color: T.textMuted, fontSize: 16, transform: open ? "rotate(180deg)" : "none", transition: "transform 0.2s" }}>▾</span>
            </button>

            {open && (
                <div style={{ padding: "0 16px 16px", borderTop: `1px solid ${T.border}` }}>
                    {/* Total staff count — always first */}
                    <div style={{ paddingTop: 12, marginBottom: 4 }}>
                        <div style={{
                            display: "flex", justifyContent: "space-between", alignItems: "center",
                            padding: "10px 12px", background: "#F0F4FF", borderRadius: 10,
                            border: `1.5px solid ${T.primaryLight}`,
                        }}>
                            <div style={{ flex: 1 }}>
                                <div style={{ fontSize: 13, fontWeight: 700, color: T.primary }}>Total Staff in Facility</div>
                                <div style={{ fontSize: 10, color: T.textMuted, marginTop: 1 }}>Total number of this cadre at the facility</div>
                            </div>
                            <Stepper value={values.total_in_facility ?? 0} onChange={v => onChange("total_in_facility", v)} />
                        </div>
                    </div>

                    {/* Divider */}
                    <div style={{ display: "flex", alignItems: "center", gap: 8, margin: "12px 0 4px" }}>
                        <div style={{ height: 1, flex: 1, background: T.border }} />
                        <span style={{ fontSize: 10, fontWeight: 700, color: T.textMuted, textTransform: "uppercase", letterSpacing: 0.8 }}>Trained in 5 Areas</span>
                        <div style={{ height: 1, flex: 1, background: T.border }} />
                    </div>

                    {TRAINING_FIELDS.map(f => {
                        const fieldVal = parseInt(values[f.key], 10) || 0;
                        const otherSum = trainedSum - fieldVal;
                        // Max for this field = remaining slots after other fields are filled
                        const fieldMax = totalInFacility > 0 ? Math.max(0, totalInFacility - otherSum) : undefined;
                        return (
                            <div key={f.key} style={{ display: "flex", justifyContent: "space-between", alignItems: "center", paddingTop: 10 }}>
                                <div style={{ fontSize: 13, color: T.textMid, flex: 1 }}>{f.label}</div>
                                <Stepper value={values[f.key] ?? 0} onChange={v => onChange(f.key, v)} max={fieldMax} />
                            </div>
                        );
                    })}

                    {/* Training vs total indicator */}
                    {totalInFacility > 0 && (
                        <div style={{ marginTop: 12, padding: "8px 12px", borderRadius: 8, background: trainedSum > totalInFacility ? "#FEE2E2" : "#D1FAE5", border: `1px solid ${trainedSum > totalInFacility ? "#FCA5A5" : "#6EE7B7"}` }}>
                            <div style={{ fontSize: 11, fontWeight: 700, color: trainedSum > totalInFacility ? "#991B1B" : "#065F46" }}>
                                {trainedSum > totalInFacility
                                    ? `⚠ Trained (${trainedSum}) exceeds total staff (${totalInFacility}) — reduce before saving`
                                    : `✓ ${trainedSum} of ${totalInFacility} trained (${totalInFacility - trainedSum} remaining)`}
                            </div>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}

// ── Main screen ────────────────────────────────────────────────────────────────
export function HumanResourcesScreen({ assessment, onBack, onComplete }) {
    const [cadres, setCadres] = useState([]);
    const [values, setValues] = useState({});   // { cadreId: { etat_plus: 0, ... } }
    const [dirtyIds, setDirtyIds] = useState(new Set()); // cadreIds changed since last save
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState(null);
    const [isOffline, setIsOffline] = useState(!navigator.onLine);
    const [syncStatus, setSyncStatus] = useState(syncQueue.getStatus());
    const pendingTimer = useRef(null);

    // Track connectivity
    useEffect(() => {
        const up = () => setIsOffline(false);
        const down = () => setIsOffline(true);
        window.addEventListener("online", up);
        window.addEventListener("offline", down);
        return () => { window.removeEventListener("online", up); window.removeEventListener("offline", down); };
    }, []);

    // Track sync queue
    useEffect(() => syncQueue.subscribe(s => setSyncStatus(s)), []);

    // ── Load ───────────────────────────────────────────────────────────────────
    useEffect(() => {
        setLoading(true);
        api.humanResources.get(assessment.id)
            .then(async res => {
                const rows = Array.isArray(res?.data) ? res.data : [];
                setCadres(rows);

                // Build values from structure (may already be merged with saved offline data by api layer)
                const init = {};
                rows.forEach(c => {
                    init[c.cadre_id] = {
                        total_in_facility: c.total_in_facility ?? 0,
                        etat_plus: c.etat_plus ?? 0,
                        comprehensive_newborn_care: c.comprehensive_newborn_care ?? 0,
                        imnci: c.imnci ?? 0,
                        type_1_diabetes: c.type_1_diabetes ?? 0,
                        essential_newborn_care: c.essential_newborn_care ?? 0,
                    };
                });

                // Also overlay any pendingValues saved between changes (pre-save-button)
                const cached = await offlineStore.getHR(assessment.id);
                if (cached?.pendingValues && typeof cached.pendingValues === "object") {
                    Object.entries(cached.pendingValues).forEach(([cadreId, fields]) => {
                        const id = Number(cadreId);
                        if (init[id]) init[id] = { ...init[id], ...fields };
                    });
                }

                setValues(init);
            })
            .catch(e => setError(e.message || "Failed to load"))
            .finally(() => setLoading(false));
    }, [assessment.id]);

    // ── Persist changes to offline store automatically (debounced 800ms) ───────
    // Ensures that if user refreshes without clicking Save, their work is still there.
    useEffect(() => {
        if (!Object.keys(values).length) return;
        if (pendingTimer.current) clearTimeout(pendingTimer.current);
        pendingTimer.current = setTimeout(async () => {
            const existing = await offlineStore.getHR(assessment.id);
            await offlineStore.saveHR(assessment.id, {
                ...(existing ?? {}),
                pendingValues: values,
            });
        }, 800);
        return () => clearTimeout(pendingTimer.current);
    }, [values, assessment.id]);

    const handleChange = (cadreId, field, val) => {
        setValues(prev => ({
            ...prev,
            [cadreId]: { ...(prev[cadreId] ?? {}), [field]: val },
        }));
        setDirtyIds(prev => new Set([...prev, cadreId]));
    };

    const handleSave = async () => {
        setError(null);

        // Validate: sum of training counts must not exceed total_in_facility
        const violations = cadres.filter(c => {
            const v = values[c.cadre_id] ?? {};
            const total = parseInt(v.total_in_facility, 10) || 0;
            if (total === 0) return false;
            const sum = TRAINING_FIELDS.reduce((s, f) => s + (parseInt(v[f.key], 10) || 0), 0);
            return sum > total;
        });

        if (violations.length > 0) {
            setError(`Trained count exceeds total staff for: ${violations.map(c => c.cadre_name).join(", ")}. Please correct before saving.`);
            return;
        }

        setSaving(true);
        try {
            const responses = cadres.map(c => ({
                cadre_id: c.cadre_id,
                total_in_facility: values[c.cadre_id]?.total_in_facility ?? 0,
                ...TRAINING_FIELDS.reduce((acc, f) => ({ ...acc, [f.key]: values[c.cadre_id]?.[f.key] ?? 0 }), {}),
            }));
            await api.humanResources.save(assessment.id, responses);
            setDirtyIds(new Set());
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
                background: T.gradientDark, padding: "20px 20px 24px",
                position: "relative", overflow: "hidden",
                borderRadius: "24px 24px 28px 28px",
            }}>
                <div style={{ position: "absolute", width: 120, height: 120, borderRadius: "50%", background: "radial-gradient(circle, rgba(79,106,245,0.20) 0%, transparent 70%)", top: -30, right: -30 }} />
                <BackButton onBack={onBack} light />

                {/* Status badge */}
                {(isOffline || syncStatus.pendingCount > 0) && (
                    <div style={{
                        position: "absolute", top: 18, right: 16,
                        padding: "3px 10px", borderRadius: 10, fontSize: 10, fontWeight: 700,
                        background: isOffline ? "rgba(251,191,36,0.2)" : "rgba(79,106,245,0.2)",
                        color: isOffline ? "#FDE68A" : "#A5B4FC",
                        border: `1px solid ${isOffline ? "rgba(251,191,36,0.3)" : "rgba(79,106,245,0.3)"}`,
                    }}>
                        {isOffline ? "✈ Offline" : `⏳ ${syncStatus.pendingCount} pending`}
                    </div>
                )}

                <div style={{ marginTop: 12, color: "white", fontSize: 18, fontWeight: 800 }}>
                    Human Resources
                </div>
                <div style={{ color: "rgba(255,255,255,0.6)", fontSize: 13, marginTop: 2 }}>
                    {assessment.facility_name} · Staff training counts
                    {dirtyIds.size > 0 && (
                        <span style={{ color: T.accentLight, marginLeft: 8 }}>
                            · {dirtyIds.size} unsaved change{dirtyIds.size !== 1 ? "s" : ""}
                        </span>
                    )}
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
                        isDirty={dirtyIds.has(c.cadre_id)}
                    />
                ))}
            </div>

            {/* Save bar */}
            {!loading && cadres.length > 0 && (
                <div style={{ padding: "12px 16px", paddingBottom: "calc(12px + env(safe-area-inset-bottom, 0px))", background: T.card, borderTop: `1px solid ${T.border}` }}>
                    {isOffline && (
                        <div style={{
                            padding: "8px 12px", borderRadius: 10, marginBottom: 10,
                            background: "#FEF3C7", border: "1px solid #FDE68A",
                            fontSize: 12, color: "#92400E", display: "flex", alignItems: "center", gap: 6,
                        }}>
                            <span>✈</span>
                            <span>Offline — changes saved locally and will sync when back online.</span>
                        </div>
                    )}
                    <button onClick={handleSave} disabled={saving} style={{
                        width: "100%", padding: 15, borderRadius: T.radius, border: "none",
                        background: saving ? "#D1D5DB" : T.gradientPrimary,
                        color: "white", fontSize: 15, fontWeight: 700,
                        cursor: saving ? "not-allowed" : "pointer",
                    }}>
                        {saving
                            ? "Saving…"
                            : isOffline
                                ? "💾 Save Offline (will sync later) →"
                                : "Save Human Resources →"}
                    </button>
                </div>
            )}
        </div>
    );
}
