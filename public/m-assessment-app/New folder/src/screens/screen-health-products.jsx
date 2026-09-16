import { useState, useEffect, useRef, useCallback } from "react";
import { T } from "../constants.js";
import { BackButton } from "../components/shared-components.jsx";
import api from "../services/api.service.js";

const AUTO_SAVE_DELAY = 25000; // 25s

// ── Availability toggle ───────────────────────────────────────────────────────
function AvailabilityToggle({ value, onChange }) {
    // value: true | false | null (unanswered)
    const opts = [
        { v: true, label: "Available", color: "#10B981", bg: "#D1FAE5", border: "#6EE7B7", icon: "✓" },
        { v: false, label: "Not Available", color: "#EF4444", bg: "#FEE2E2", border: "#FCA5A5", icon: "✗" },
    ];
    return (
        <div style={{ display: "flex", gap: 5, flexShrink: 0 }}>
            {opts.map(o => {
                const active = value === o.v;
                return (
                    <button key={String(o.v)} onClick={() => onChange(active ? null : o.v)} style={{
                        padding: "5px 10px", borderRadius: 8, border: `1.5px solid ${active ? o.border : T.border}`,
                        background: active ? o.bg : T.borderLight, color: active ? o.color : T.textMuted,
                        fontWeight: active ? 800 : 500, fontSize: 11, cursor: "pointer",
                        display: "flex", alignItems: "center", gap: 4,
                        transition: "all 0.15s cubic-bezier(0.34,1.56,0.64,1)",
                        transform: active ? "scale(1.04)" : "scale(1)",
                    }}>
                        <span style={{
                            width: 14, height: 14, borderRadius: "50%",
                            background: active ? o.color : T.border,
                            color: "white", fontSize: 9, fontWeight: 900,
                            display: "flex", alignItems: "center", justifyContent: "center", flexShrink: 0,
                        }}>{o.icon}</span>
                        {o.label}
                    </button>
                );
            })}
        </div>
    );
}

// ── Category accordion ────────────────────────────────────────────────────────
function CategorySection({ category, deptId, responses, onChange }) {
    const [open, setOpen] = useState(true);
    const answered = category.commodities.filter(c => responses[`${deptId}_${c.commodity_id}`] !== undefined).length;
    const available = category.commodities.filter(c => responses[`${deptId}_${c.commodity_id}`] === true).length;

    return (
        <div style={{ marginBottom: 8, borderRadius: 12, overflow: "hidden", border: `1px solid ${T.border}`, background: T.card }}>
            <button onClick={() => setOpen(o => !o)} style={{
                width: "100%", padding: "11px 14px", display: "flex", alignItems: "center",
                gap: 10, background: "none", border: "none", cursor: "pointer", textAlign: "left",
            }}>
                <div style={{ flex: 1 }}>
                    <div style={{ fontSize: 12, fontWeight: 700, color: T.text }}>{category.category_name}</div>
                    <div style={{ fontSize: 10, color: T.textMuted, marginTop: 1 }}>
                        {answered}/{category.commodities.length} answered
                        {answered > 0 && ` · ${available} available`}
                    </div>
                </div>
                {/* Mini progress bar */}
                <div style={{ width: 50, height: 4, background: T.borderLight, borderRadius: 999, overflow: "hidden" }}>
                    <div style={{ height: "100%", width: `${category.commodities.length > 0 ? (answered / category.commodities.length) * 100 : 0}%`, background: "#10B981", borderRadius: 999, transition: "width 0.3s" }} />
                </div>
                <span style={{ color: T.textMuted, fontSize: 14, transform: open ? "rotate(180deg)" : "none", transition: "transform 0.2s" }}>▾</span>
            </button>

            {open && (
                <div style={{ borderTop: `1px solid ${T.borderLight}` }}>
                    {category.commodities.map((c, i) => {
                        const key = `${deptId}_${c.commodity_id}`;
                        const val = responses[key];
                        return (
                            <div key={c.commodity_id} style={{
                                padding: "10px 14px",
                                borderBottom: i < category.commodities.length - 1 ? `1px solid ${T.borderLight}` : "none",
                                display: "flex", alignItems: "center", gap: 10,
                                background: val === true ? "rgba(16,185,129,0.03)" : val === false ? "rgba(239,68,68,0.03)" : "transparent",
                            }}>
                                <div style={{ flex: 1 }}>
                                    <div style={{ fontSize: 12, color: T.text, lineHeight: 1.4 }}>{c.name}</div>
                                    {c.description && <div style={{ fontSize: 10, color: T.textMuted, marginTop: 2 }}>{c.description}</div>}
                                </div>
                                <AvailabilityToggle value={val === undefined ? null : val} onChange={v => onChange(key, v)} />
                            </div>
                        );
                    })}
                </div>
            )}
        </div>
    );
}

// ── Auto-save status pill ─────────────────────────────────────────────────────
function SavePill({ status }) {
    const map = {
        idle: null,
        saving: { label: "⏳ Saving…", color: "#92400E", bg: "#FEF3C7" },
        saved: { label: "✓ Saved", color: "#065F46", bg: "#D1FAE5" },
        error: { label: "⚠ Save failed", color: "#991B1B", bg: "#FEE2E2" },
    };
    const s = map[status];
    if (!s) return null;
    return (
        <div style={{ padding: "3px 10px", borderRadius: 20, fontSize: 10, fontWeight: 700, color: s.color, background: s.bg, transition: "all 0.3s" }}>
            {s.label}
        </div>
    );
}

// ── Main screen ───────────────────────────────────────────────────────────────
export function HealthProductsScreen({ assessment, onBack, onComplete }) {
    const [departments, setDepartments] = useState([]);
    const [activeDeptIdx, setActiveDeptIdx] = useState(0);
    const [responses, setResponses] = useState({});
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [deptSaveStatus, setDeptSaveStatus] = useState({}); // { deptId: "idle"|"saving"|"saved"|"error" }
    const autoSaveTimer = useRef(null);

    // ── Load data ──────────────────────────────────────────────────────────────
    useEffect(() => {
        api.healthProducts.get(assessment.id)
            .then(res => {
                const depts = Array.isArray(res?.data) ? res.data : [];
                setDepartments(depts);
                // Hydrate saved responses
                const init = {};
                depts.forEach(dept => {
                    dept.categories.forEach(cat => {
                        cat.commodities.forEach(c => {
                            if (c.available !== null && c.available !== undefined) {
                                init[`${dept.department_id}_${c.commodity_id}`] = c.available;
                            }
                        });
                    });
                });
                setResponses(init);
            })
            .catch(e => setError(e.message || "Failed to load"))
            .finally(() => setLoading(false));
    }, [assessment.id]);

    const handleChange = (key, val) => {
        setResponses(prev => {
            const next = { ...prev };
            if (val === null) delete next[key];
            else next[key] = val;
            return next;
        });
    };

    // ── Save a single department's responses ───────────────────────────────────
    const saveDepartment = useCallback(async (dept, silent = false) => {
        const deptId = dept.department_id;
        if (!silent) setDeptSaveStatus(p => ({ ...p, [deptId]: "saving" }));

        try {
            const commodityKeys = dept.categories.flatMap(cat =>
                cat.commodities.map(c => `${deptId}_${c.commodity_id}`)
            );
            // Only send responses that have been answered for this dept
            const responseArray = commodityKeys
                .filter(key => responses[key] !== undefined)
                .map(key => {
                    const [department_id, commodity_id] = key.split("_").map(Number);
                    return { department_id, commodity_id, available: responses[key] };
                });

            if (responseArray.length === 0) {
                if (!silent) setDeptSaveStatus(p => ({ ...p, [deptId]: "saved" }));
                setTimeout(() => setDeptSaveStatus(p => ({ ...p, [deptId]: "idle" })), 2000);
                return true;
            }

            // Use department_id param so controller knows this is a per-dept save
            await api.healthProducts.save(assessment.id, responseArray, deptId);
            if (!silent) {
                setDeptSaveStatus(p => ({ ...p, [deptId]: "saved" }));
                setTimeout(() => setDeptSaveStatus(p => ({ ...p, [deptId]: "idle" })), 2500);
            }
            return true;
        } catch (e) {
            setDeptSaveStatus(p => ({ ...p, [deptId]: "error" }));
            setTimeout(() => setDeptSaveStatus(p => ({ ...p, [deptId]: "idle" })), 4000);
            return false;
        }
    }, [assessment.id, responses]);

    // ── Auto-save current dept on response change ──────────────────────────────
    const activeDept = departments[activeDeptIdx];
    useEffect(() => {
        if (!activeDept) return;
        if (autoSaveTimer.current) clearTimeout(autoSaveTimer.current);
        autoSaveTimer.current = setTimeout(() => saveDepartment(activeDept, true), AUTO_SAVE_DELAY);
        return () => clearTimeout(autoSaveTimer.current);
    }, [responses, activeDept, saveDepartment]);

    // ── Save & Next / Save & Finish ───────────────────────────────────────────
    const handleSaveAndNext = async () => {
        if (!activeDept) return;
        const ok = await saveDepartment(activeDept);
        if (!ok) return; // error already shown in pill

        const isLast = activeDeptIdx === departments.length - 1;
        if (isLast) {
            // Final save marks section complete
            await saveAllAndComplete();
        } else {
            setActiveDeptIdx(i => i + 1);
        }
    };

    const saveAllAndComplete = async () => {
        try {
            // Build full array for all answered responses
            const responseArray = Object.entries(responses).map(([key, available]) => {
                const [department_id, commodity_id] = key.split("_").map(Number);
                return { department_id, commodity_id, available };
            });
            await api.healthProducts.save(assessment.id, responseArray);
            onComplete?.(assessment.id);
        } catch (e) {
            setError(e.message || "Final save failed");
        }
    };

    // ── Dept-level progress ────────────────────────────────────────────────────
    const getDeptProgress = (dept) => {
        const all = dept.categories.flatMap(c => c.commodities);
        const answered = all.filter(c => responses[`${dept.department_id}_${c.commodity_id}`] !== undefined).length;
        return { total: all.length, answered, pct: all.length > 0 ? Math.round((answered / all.length) * 100) : 0 };
    };

    if (loading) {
        return (
            <div style={{ display: "flex", flexDirection: "column", height: "100%", background: T.bg }}>
                <div style={{ background: "linear-gradient(135deg,#7F1D1D,#EF4444)", padding: "20px 20px 24px" }}>
                    <BackButton onBack={onBack} light />
                    <div style={{ color: "white", fontSize: 18, fontWeight: 800, marginTop: 12 }}>Health Products</div>
                </div>
                <div style={{ flex: 1, display: "flex", alignItems: "center", justifyContent: "center", flexDirection: "column", gap: 12, color: T.textMuted }}>
                    <div style={{ fontSize: 32 }}>⏳</div>
                    <div style={{ fontSize: 13 }}>Loading commodities…</div>
                </div>
            </div>
        );
    }

    const isLast = activeDeptIdx === departments.length - 1;

    return (
        <div style={{ display: "flex", flexDirection: "column", height: "100%" }}>
            {/* Header */}
            <div style={{ background: "linear-gradient(135deg,#7F1D1D,#EF4444)", padding: "18px 20px 0" }}>
                <BackButton onBack={onBack} light />
                <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", marginTop: 10, marginBottom: 10 }}>
                    <div>
                        <div style={{ color: "white", fontSize: 17, fontWeight: 800 }}>Health Products</div>
                        <div style={{ color: "rgba(255,255,255,0.6)", fontSize: 11, marginTop: 2 }}>
                            {assessment.facility_name} · Department {activeDeptIdx + 1} of {departments.length}
                        </div>
                    </div>
                    {activeDept && <SavePill status={deptSaveStatus[activeDept.department_id] ?? "idle"} />}
                </div>

                {/* Overall progress bar */}
                <div style={{ marginBottom: 10 }}>
                    {(() => {
                        const totalAll = departments.reduce((a, d) => a + getDeptProgress(d).total, 0);
                        const answeredAll = departments.reduce((a, d) => a + getDeptProgress(d).answered, 0);
                        const pctAll = totalAll > 0 ? Math.round((answeredAll / totalAll) * 100) : 0;
                        return (
                            <>
                                <div style={{ display: "flex", justifyContent: "space-between", marginBottom: 4 }}>
                                    <span style={{ color: "rgba(255,255,255,0.7)", fontSize: 11 }}>Overall progress</span>
                                    <span style={{ color: "white", fontSize: 11, fontWeight: 700 }}>{answeredAll}/{totalAll} · {pctAll}%</span>
                                </div>
                                <div style={{ height: 4, background: "rgba(255,255,255,0.2)", borderRadius: 999, overflow: "hidden" }}>
                                    <div style={{ height: "100%", width: `${pctAll}%`, background: "white", borderRadius: 999, transition: "width 0.4s" }} />
                                </div>
                            </>
                        );
                    })()}
                </div>

                {/* Department tabs */}
                <div style={{ display: "flex", gap: 0, overflowX: "auto", paddingBottom: 0, scrollbarWidth: "none" }}>
                    {departments.map((dept, idx) => {
                        const prog = getDeptProgress(dept);
                        const active = idx === activeDeptIdx;
                        return (
                            <button key={dept.department_id} onClick={() => setActiveDeptIdx(idx)} style={{
                                padding: "9px 14px", border: "none", background: "none",
                                color: active ? "white" : "rgba(255,255,255,0.5)",
                                fontWeight: active ? 700 : 500, fontSize: 12, cursor: "pointer", whiteSpace: "nowrap",
                                borderBottom: active ? "2.5px solid white" : "2.5px solid transparent",
                                position: "relative", transition: "all 0.15s",
                            }}>
                                {dept.department_name}
                                {prog.answered > 0 && (
                                    <span style={{
                                        position: "absolute", top: 5, right: 6,
                                        width: 6, height: 6, borderRadius: "50%",
                                        background: prog.pct === 100 ? "#10B981" : "#F59E0B",
                                    }} />
                                )}
                            </button>
                        );
                    })}
                </div>
            </div>

            {/* Department content */}
            <div style={{ flex: 1, overflowY: "auto", padding: "12px 14px 10px", background: T.bg }}>
                {error && (
                    <div style={{ padding: "10px 14px", background: "#FEE2E2", borderRadius: 10, marginBottom: 10, fontSize: 12, color: "#991B1B" }}>
                        ⚠️ {error}
                    </div>
                )}

                {activeDept ? (
                    <>
                        {/* Dept header card */}
                        <div style={{ padding: "12px 14px", background: T.card, borderRadius: 13, marginBottom: 12, border: `1px solid ${T.border}` }}>
                            <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between" }}>
                                <div style={{ fontSize: 14, fontWeight: 800, color: T.text }}>{activeDept.department_name}</div>
                                {(() => {
                                    const p = getDeptProgress(activeDept);
                                    return <span style={{ fontSize: 11, fontWeight: 700, color: p.pct === 100 ? "#10B981" : "#F59E0B" }}>{p.answered}/{p.total} · {p.pct}%</span>;
                                })()}
                            </div>
                            <div style={{ height: 4, background: T.borderLight, borderRadius: 999, overflow: "hidden", marginTop: 8 }}>
                                <div style={{ height: "100%", width: `${getDeptProgress(activeDept).pct}%`, background: "linear-gradient(90deg,#7F1D1D,#EF4444)", borderRadius: 999, transition: "width 0.4s" }} />
                            </div>
                        </div>

                        {activeDept.categories.map(cat => (
                            <CategorySection
                                key={cat.category_id}
                                category={cat}
                                deptId={activeDept.department_id}
                                responses={responses}
                                onChange={handleChange}
                            />
                        ))}
                    </>
                ) : (
                    <div style={{ textAlign: "center", padding: 40, color: T.textMuted }}>No departments found.</div>
                )}
            </div>

            {/* Save bar */}
            {departments.length > 0 && (
                <div style={{ padding: "11px 16px 18px", background: T.card, borderTop: `1px solid ${T.border}`, boxShadow: "0 -4px 20px rgba(0,0,0,0.06)" }}>
                    {/* Navigation row */}
                    <div style={{ display: "flex", gap: 8, marginBottom: 10 }}>
                        <button
                            onClick={() => setActiveDeptIdx(i => Math.max(0, i - 1))}
                            disabled={activeDeptIdx === 0}
                            style={{
                                flex: 1, padding: "10px", borderRadius: 11, border: `1.5px solid ${T.border}`,
                                background: activeDeptIdx === 0 ? T.borderLight : T.card,
                                color: activeDeptIdx === 0 ? T.textMuted : T.textMid,
                                fontSize: 13, fontWeight: 700, cursor: activeDeptIdx === 0 ? "default" : "pointer",
                            }}>
                            ← Previous
                        </button>
                        {/* Dept dots */}
                        <div style={{ display: "flex", alignItems: "center", gap: 5, padding: "0 8px" }}>
                            {departments.map((_, i) => {
                                const prog = getDeptProgress(departments[i]);
                                return (
                                    <div key={i} onClick={() => setActiveDeptIdx(i)} style={{
                                        width: i === activeDeptIdx ? 20 : 7, height: 7, borderRadius: 999,
                                        background: i === activeDeptIdx ? "#EF4444" : prog.pct === 100 ? "#10B981" : prog.answered > 0 ? "#F59E0B" : T.border,
                                        transition: "all 0.2s", cursor: "pointer",
                                    }} />
                                );
                            })}
                        </div>
                        <button
                            onClick={() => setActiveDeptIdx(i => Math.min(departments.length - 1, i + 1))}
                            disabled={isLast}
                            style={{
                                flex: 1, padding: "10px", borderRadius: 11, border: `1.5px solid ${T.border}`,
                                background: isLast ? T.borderLight : T.card,
                                color: isLast ? T.textMuted : T.textMid,
                                fontSize: 13, fontWeight: 700, cursor: isLast ? "default" : "pointer",
                            }}>
                            Next →
                        </button>
                    </div>

                    <button onClick={handleSaveAndNext} disabled={deptSaveStatus[activeDept?.department_id] === "saving"} style={{
                        width: "100%", padding: "13px", borderRadius: 13, border: "none",
                        background: deptSaveStatus[activeDept?.department_id] === "saving"
                            ? T.border
                            : isLast
                                ? "linear-gradient(135deg,#7F1D1D,#EF4444)"
                                : "linear-gradient(135deg,#7F1D1D,#DC2626)",
                        color: deptSaveStatus[activeDept?.department_id] === "saving" ? T.textMuted : "white",
                        fontSize: 15, fontWeight: 800,
                        cursor: deptSaveStatus[activeDept?.department_id] === "saving" ? "default" : "pointer",
                        boxShadow: "0 5px 18px rgba(127,29,29,0.35)", transition: "all 0.2s",
                    }}>
                        {deptSaveStatus[activeDept?.department_id] === "saving"
                            ? "⏳ Saving…"
                            : isLast
                                ? "💾 Save & Complete Section"
                                : `💾 Save & Next: ${departments[activeDeptIdx + 1]?.department_name ?? ""} →`}
                    </button>
                </div>
            )}
        </div>
    );
}
