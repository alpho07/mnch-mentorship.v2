// src/screens/screen-mentorship-detail.jsx
import { useState, useEffect } from "react";
import { T, MENTEE_ROLES } from "../constants.js";
import api from "../services/api.service.js";
import { ResourceCard } from "../components/ResourceCard.jsx";

const inputStyle = {
    display: "block", width: "100%", padding: "10px 12px",
    borderRadius: T.radiusSm, border: `1px solid ${T.border}`,
    fontSize: 14, background: "#fff", color: T.text,
    fontFamily: "inherit", outline: "none", boxSizing: "border-box",
};

function EditMentorshipPanel({ data, onClose, onSaved }) {
    const [title, setTitle]           = useState(data.title ?? "");
    const [startDate, setStartDate]   = useState(data.start_date ?? "");
    const [endDate, setEndDate]       = useState(data.end_date ?? "");
    const [maxP, setMaxP]             = useState(data.max_participants ?? 20);
    const [saving, setSaving]         = useState(false);
    const [error, setError]           = useState(null);

    const handleSave = async () => {
        setSaving(true); setError(null);
        try {
            const res = await api.mentorships.update(data.id, {
                title: title || undefined,
                start_date: startDate || undefined,
                end_date: endDate || undefined,
                max_participants: maxP,
            });
            onSaved(res?.data ?? res);
        } catch (e) {
            setError(e.message ?? "Failed to save.");
        } finally {
            setSaving(false);
        }
    };

    return (
        <div style={{
            position: "fixed", inset: 0, background: "rgba(0,0,0,0.45)", zIndex: 300,
            display: "flex", alignItems: "flex-end",
        }}>
            <div style={{ width: "100%", background: "#fff", borderRadius: "20px 20px 0 0", padding: "20px 16px 32px", maxHeight: "80vh", overflowY: "auto" }}>
                <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: 16 }}>
                    <div style={{ fontSize: 15, fontWeight: 800, color: T.text }}>Edit Mentorship</div>
                    <button onClick={onClose} style={{ background: "none", border: "none", fontSize: 20, color: T.textSub, cursor: "pointer" }}>×</button>
                </div>
                {error && (
                    <div style={{ background: "#FEF2F2", border: "1px solid #FECACA", borderRadius: T.radiusSm, padding: "10px 12px", marginBottom: 12, color: "#DC2626", fontSize: 13 }}>
                        {error}
                    </div>
                )}
                <div style={{ marginBottom: 14 }}>
                    <label style={{ fontSize: 11, fontWeight: 700, color: T.textSub, display: "block", marginBottom: 5, textTransform: "uppercase", letterSpacing: 0.5 }}>Title</label>
                    <input value={title} onChange={e => setTitle(e.target.value)} style={inputStyle} placeholder="Auto-generated if blank" />
                </div>
                <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12, marginBottom: 14 }}>
                    <div>
                        <label style={{ fontSize: 11, fontWeight: 700, color: T.textSub, display: "block", marginBottom: 5, textTransform: "uppercase", letterSpacing: 0.5 }}>Start Date</label>
                        <input type="date" value={startDate} onChange={e => setStartDate(e.target.value)} style={inputStyle} />
                    </div>
                    <div>
                        <label style={{ fontSize: 11, fontWeight: 700, color: T.textSub, display: "block", marginBottom: 5, textTransform: "uppercase", letterSpacing: 0.5 }}>End Date</label>
                        <input type="date" value={endDate} min={startDate} onChange={e => setEndDate(e.target.value)} style={inputStyle} />
                    </div>
                </div>
                <div style={{ marginBottom: 20 }}>
                    <label style={{ fontSize: 11, fontWeight: 700, color: T.textSub, display: "block", marginBottom: 5, textTransform: "uppercase", letterSpacing: 0.5 }}>Max Mentees</label>
                    <input type="number" value={maxP} min={1} onChange={e => setMaxP(parseInt(e.target.value) || 20)} style={inputStyle} />
                </div>
                <button
                    onClick={handleSave}
                    disabled={saving}
                    style={{
                        width: "100%", padding: 13, borderRadius: T.radiusSm, border: "none",
                        background: "linear-gradient(135deg, #3730A3, #6366F1)",
                        color: "#fff", fontSize: 14, fontWeight: 700,
                        cursor: saving ? "not-allowed" : "pointer", opacity: saving ? 0.6 : 1,
                    }}
                >
                    {saving ? "Saving…" : "Save Changes"}
                </button>
            </div>
        </div>
    );
}

const CLASS_STATUS = {
    draft:     { bg: "#F3F4F6", color: "#374151" },
    active:    { bg: "#D1FAE5", color: "#065F46" },
    completed: { bg: "#DBEAFE", color: "#1D4ED8" },
    cancelled: { bg: "#FEE2E2", color: "#991B1B" },
};

function InfoRow({ label, value }) {
    if (!value) return null;
    return (
        <div style={{ display: "flex", justifyContent: "space-between", paddingBlock: 7, borderBottom: `1px solid ${T.borderLight}` }}>
            <span style={{ fontSize: 12, color: T.textSub }}>{label}</span>
            <span style={{ fontSize: 12, fontWeight: 600, color: T.text, textAlign: "right", maxWidth: "60%" }}>{value}</span>
        </div>
    );
}

// ── Reports tab — mentor/admin default ───────────────────────────────────────
function ReportsTab({ data, classes, onSwitchToClasses }) {
    const totalMentees  = classes.reduce((a, c) => a + (c.participant_count ?? 0), 0);
    const avgProgress   = classes.length > 0
        ? Math.round(classes.reduce((a, c) => a + (c.progress_percentage ?? 0), 0) / classes.length)
        : 0;
    const activeClasses = classes.filter(c => c.status === "active").length;

    return (
        <div style={{ display: "flex", flexDirection: "column", gap: 12 }}>
            {/* Stat row */}
            <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr 1fr", gap: 8 }}>
                {[
                    { label: "Mentees",  value: totalMentees,  color: T.primary },
                    { label: "Classes",  value: classes.length, color: "#10B981" },
                    { label: "Progress", value: `${avgProgress}%`, color: "#F59E0B" },
                ].map(s => (
                    <div key={s.label} style={{
                        background: T.card, borderRadius: T.radiusSm, padding: "12px 8px",
                        textAlign: "center", border: `1px solid ${T.border}`, boxShadow: T.shadowCard,
                    }}>
                        <div style={{ fontSize: 22, fontWeight: 800, color: s.color, lineHeight: 1 }}>{s.value}</div>
                        <div style={{ fontSize: 10, color: T.textSub, fontWeight: 600, marginTop: 4 }}>{s.label}</div>
                    </div>
                ))}
            </div>

            {/* Overall completion */}
            <div style={{ background: T.card, borderRadius: T.radiusSm, padding: "14px 16px", border: `1px solid ${T.border}`, boxShadow: T.shadowCard }}>
                <div style={{ fontSize: 11, fontWeight: 700, color: T.textMuted, textTransform: "uppercase", letterSpacing: 0.6, marginBottom: 10 }}>
                    Overall Completion
                </div>
                <div style={{ height: 8, background: T.borderLight, borderRadius: 999, overflow: "hidden", marginBottom: 6 }}>
                    <div style={{
                        height: "100%", width: `${avgProgress}%`,
                        background: T.gradientPrimary, borderRadius: 999,
                        transition: "width 0.6s cubic-bezier(0.4,0,0.2,1)",
                    }} />
                </div>
                <div style={{ display: "flex", justifyContent: "space-between", fontSize: 11, color: T.textSub }}>
                    <span>{avgProgress}% avg across {classes.length} class{classes.length !== 1 ? "es" : ""}</span>
                    <span style={{ color: "#10B981", fontWeight: 600 }}>{activeClasses} active</span>
                </div>
            </div>

            {/* Per-class breakdown */}
            {classes.length > 0 && (
                <div style={{ background: T.card, borderRadius: T.radiusSm, padding: "14px 16px", border: `1px solid ${T.border}`, boxShadow: T.shadowCard }}>
                    <div style={{ fontSize: 11, fontWeight: 700, color: T.textMuted, textTransform: "uppercase", letterSpacing: 0.6, marginBottom: 12 }}>
                        Class Breakdown
                    </div>
                    {classes.map((c, i) => {
                        const pct = c.progress_percentage ?? 0;
                        const cs  = CLASS_STATUS[c.status] ?? CLASS_STATUS.draft;
                        return (
                            <div key={c.id} style={{
                                marginBottom: i < classes.length - 1 ? 12 : 0,
                                paddingBottom: i < classes.length - 1 ? 12 : 0,
                                borderBottom: i < classes.length - 1 ? `1px solid ${T.borderLight}` : "none",
                            }}>
                                <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: 5 }}>
                                    <span style={{ fontSize: 12, fontWeight: 700, color: T.text, flex: 1, minWidth: 0, overflow: "hidden", textOverflow: "ellipsis", whiteSpace: "nowrap" }}>{c.name}</span>
                                    <span style={{ fontSize: 10, fontWeight: 700, padding: "2px 7px", borderRadius: 5, background: cs.bg, color: cs.color, flexShrink: 0, marginLeft: 8 }}>{c.status}</span>
                                </div>
                                <div style={{ display: "flex", alignItems: "center", gap: 8 }}>
                                    <div style={{ flex: 1, height: 5, background: T.borderLight, borderRadius: 999, overflow: "hidden" }}>
                                        <div style={{ height: "100%", width: `${pct}%`, background: pct === 100 ? "#10B981" : T.gradientPrimary, borderRadius: 999 }} />
                                    </div>
                                    <span style={{ fontSize: 11, fontWeight: 600, color: T.textSub, flexShrink: 0 }}>{pct}%</span>
                                </div>
                                <div style={{ fontSize: 10, color: T.textMuted, marginTop: 3 }}>
                                    {c.participant_count ?? 0} mentees · {c.module_count ?? 0} modules
                                </div>
                            </div>
                        );
                    })}
                </div>
            )}

            {/* View classes shortcut */}
            <button onClick={onSwitchToClasses} style={{
                width: "100%", padding: "12px 0", borderRadius: T.radiusSm, border: `1.5px solid ${T.border}`,
                background: T.card, color: T.primary, fontSize: 13, fontWeight: 700, cursor: "pointer",
                display: "flex", alignItems: "center", justifyContent: "center", gap: 6,
            }}>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round">
                    <path d="M2 3h6a4 4 0 014 4v14a3 3 0 00-3-3H2z"/><path d="M22 3h-6a4 4 0 00-4 4v14a3 3 0 013-3h7z"/>
                </svg>
                View All Classes
            </button>
        </div>
    );
}

// ── Classes tab ───────────────────────────────────────────────────────────────
function ClassesTab({ data, classes, loading, onOpenClass, onAddClass, onEditClass, onDeleteClass }) {
    return (
        <div>
            {loading && <div style={{ color: T.textSub, textAlign: "center", paddingTop: 32 }}>Loading…</div>}
            {classes.length > 0 && (
                <div>
                    <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", marginBottom: 10 }}>
                        <div style={{ fontSize: 11, fontWeight: 700, color: T.textSub, textTransform: "uppercase", letterSpacing: 0.5 }}>
                            Classes ({classes.length})
                        </div>
                        {onAddClass && (
                            <button onClick={onAddClass} style={{
                                padding: "5px 12px", borderRadius: T.radiusSm, border: "none",
                                background: T.gradientPrimary, color: "#fff", fontSize: 12, fontWeight: 700, cursor: "pointer",
                                boxShadow: `0 4px 12px ${T.primaryGlow}`,
                            }}>
                                + Add Class
                            </button>
                        )}
                    </div>
                    {classes.map(c => {
                        const cs  = CLASS_STATUS[c.status] ?? CLASS_STATUS.draft;
                        const pct = c.progress_percentage ?? 0;
                        return (
                            <div key={c.id} style={{
                                background: T.card, border: `1px solid ${T.border}`,
                                borderRadius: T.radiusSm, boxShadow: T.shadowCard, marginBottom: 8,
                                overflow: "hidden",
                            }}>
                                <div onClick={() => onOpenClass({ ...c, trainingId: data.id })} style={{ padding: "14px 16px", cursor: "pointer" }}>
                                    <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start", marginBottom: 6 }}>
                                        <div style={{ fontWeight: 700, color: T.text, fontSize: 14 }}>{c.name}</div>
                                        <span style={{ fontSize: 10, fontWeight: 700, padding: "2px 7px", borderRadius: 5, background: cs.bg, color: cs.color, flexShrink: 0, marginLeft: 8 }}>{c.status}</span>
                                    </div>
                                    <div style={{ fontSize: 12, color: T.textSub, marginBottom: 8 }}>
                                        {c.participant_count ?? 0} mentees · {c.module_count ?? 0} modules{c.start_date ? ` · ${c.start_date}` : ""}
                                    </div>
                                    <div style={{ height: 5, borderRadius: 4, background: T.borderLight, overflow: "hidden" }}>
                                        <div style={{ height: "100%", width: `${pct}%`, background: pct === 100 ? "#10B981" : T.gradientPrimary, borderRadius: 4 }} />
                                    </div>
                                    <div style={{ fontSize: 11, color: T.textSub, marginTop: 3 }}>{pct}% complete</div>
                                </div>
                                <div style={{ display: "flex", gap: 8, padding: "8px 12px", borderTop: `1px solid ${T.borderLight}`, background: T.bg }}>
                                    <button onClick={(e) => { e.stopPropagation(); onOpenClass({ ...c, trainingId: data.id }); }} style={{ flex: 1, display: "flex", alignItems: "center", justifyContent: "center", gap: 5, padding: "6px 0", borderRadius: T.radiusXs, border: "none", background: T.gradientPrimary, color: "#fff", fontSize: 12, fontWeight: 600, cursor: "pointer" }}>
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                        View
                                    </button>
                                    {onEditClass && (
                                        <button onClick={(e) => { e.stopPropagation(); onEditClass({ ...c, trainingId: data.id }); }} style={{ flex: 1, display: "flex", alignItems: "center", justifyContent: "center", gap: 5, padding: "6px 0", borderRadius: T.radiusXs, border: `1px solid ${T.border}`, background: T.card, color: T.primary, fontSize: 12, fontWeight: 600, cursor: "pointer" }}>
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 00 2 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                            Edit
                                        </button>
                                    )}
                                    {onDeleteClass && (
                                        <button onClick={(e) => { e.stopPropagation(); onDeleteClass({ ...c, trainingId: data.id }); }} style={{ flex: 1, display: "flex", alignItems: "center", justifyContent: "center", gap: 5, padding: "6px 0", borderRadius: T.radiusXs, border: "1px solid #FECACA", background: "#FEF2F2", color: "#DC2626", fontSize: 12, fontWeight: 600, cursor: "pointer" }}>
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                                            Delete
                                        </button>
                                    )}
                                </div>
                            </div>
                        );
                    })}
                </div>
            )}
            {!loading && classes.length === 0 && (
                <div style={{ textAlign: "center", paddingTop: 20 }}>
                    <div style={{ color: T.textSub, marginBottom: 12, fontSize: 14 }}>No classes yet.</div>
                    {onAddClass && (
                        <button onClick={onAddClass} style={{ padding: "10px 24px", borderRadius: T.radiusSm, border: "none", background: T.gradientPrimary, color: "#fff", fontSize: 13, fontWeight: 700, cursor: "pointer", boxShadow: `0 4px 12px ${T.primaryGlow}` }}>
                            + Add First Class
                        </button>
                    )}
                </div>
            )}
        </div>
    );
}

// ── Info tab ─────────────────────────────────────────────────────────────────
function InfoTab({ data, classes, resources, showResources, setShowResources }) {
    return (
        <div style={{ display: "flex", flexDirection: "column", gap: 12 }}>
            <div style={{ background: T.card, borderRadius: T.radiusSm, padding: "4px 14px", boxShadow: T.shadowCard }}>
                <InfoRow label="Facility"   value={data.facility} />
                <InfoRow label="County"     value={data.county} />
                <InfoRow label="Mentor"     value={data.mentor_name} />
                <InfoRow label="Start Date" value={data.start_date} />
                <InfoRow label="End Date"   value={data.end_date} />
                <InfoRow label="Location"   value={data.location_type} />
                <InfoRow label="Classes"    value={classes.length > 0 ? `${classes.length} class${classes.length !== 1 ? "es" : ""}` : null} />
            </div>
            {data.description && (
                <div style={{ background: T.card, borderRadius: T.radiusSm, padding: "12px 14px", boxShadow: T.shadowCard }}>
                    <div style={{ fontSize: 11, fontWeight: 700, color: T.textSub, marginBottom: 6, textTransform: "uppercase", letterSpacing: 0.5 }}>Description</div>
                    <div style={{ fontSize: 13, color: T.text, lineHeight: 1.6 }}>{data.description}</div>
                </div>
            )}
            {resources.length > 0 && (
                <div>
                    <button onClick={() => setShowResources(v => !v)} style={{ width: "100%", display: "flex", justifyContent: "space-between", alignItems: "center", background: T.card, border: `1px solid ${T.border}`, borderRadius: T.radiusSm, padding: "12px 14px", cursor: "pointer", marginBottom: showResources ? 8 : 0 }}>
                        <span style={{ fontSize: 12, fontWeight: 700, color: T.textSub, textTransform: "uppercase", letterSpacing: 0.5 }}>Resources & Manuals ({resources.length})</span>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke={T.textSub} strokeWidth="2.5" style={{ transform: showResources ? "rotate(180deg)" : "none", transition: "transform 0.2s" }}><polyline points="6 9 12 15 18 9"/></svg>
                    </button>
                    {showResources && resources.map(r => <ResourceCard key={r.id} resource={r} />)}
                </div>
            )}
        </div>
    );
}

export function MentorshipDetailScreen({ training, user, onBack, onOpenClass, onAddClass, onEditClass, onDeleteClass }) {
    const [detail, setDetail]       = useState(null);
    const [loading, setLoading]     = useState(true);
    const [resources, setResources] = useState([]);
    const [showResources, setShowResources] = useState(false);
    const [showEdit, setShowEdit]   = useState(false);
    const [startWarning, setStartWarning] = useState(training?._startWarning ?? null);

    const isMentee = [...MENTEE_ROLES].some(r => (user?.roles ?? []).includes(r));
    const [activeTab, setActiveTab] = useState(isMentee ? "classes" : "reports");

    useEffect(() => {
        api.mentorships.find(training.id)
            .then(d => setDetail(d?.data ?? null))
            .catch(() => setDetail(training))
            .finally(() => setLoading(false));
        api.resources.list("mentorship_manual")
            .then(d => setResources(Array.isArray(d?.data) ? d.data : []))
            .catch(() => {});
    }, [training.id]);

    const data    = detail ?? training;
    const classes = data?.classes ?? [];
    const trainingStatus = data.status ?? "draft";

    const TABS = isMentee
        ? [{ key: "classes", label: "Classes" }, { key: "info", label: "Info" }]
        : [{ key: "reports", label: "Reports" }, { key: "classes", label: "Classes" }, { key: "info", label: "Info" }];

    return (
        <div style={{ height: "100%", overflowY: "auto", background: T.bg }}>
            {/* ── Gradient Hero ── */}
            <div style={{ height: 6, background: T.bg }} />
            <div style={{
                background: T.gradientHero,
                padding: "20px 20px 20px",
                borderRadius: "0 0 24px 24px",
                margin: "0 6px",
                position: "relative", overflow: "hidden",
            }}>
                <div style={{ position: "absolute", width: 160, height: 160, borderRadius: "50%", background: "radial-gradient(circle, rgba(79,106,245,0.20) 0%, transparent 70%)", top: -40, right: -40 }} />
                <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: 14 }}>
                    <button onClick={onBack} style={{ border: "none", background: "rgba(255,255,255,0.12)", cursor: "pointer", padding: "6px 10px", borderRadius: 10, display: "flex", alignItems: "center", gap: 4 }}>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="2.5"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                        <span style={{ fontSize: 12, color: "rgba(255,255,255,0.8)", fontWeight: 600 }}>Back</span>
                    </button>
                    {data.status !== "submitted" && data.status !== "completed" && (
                        <button onClick={() => setShowEdit(true)} style={{ border: "none", background: "rgba(255,255,255,0.12)", cursor: "pointer", padding: "6px 10px", borderRadius: 10, display: "flex", alignItems: "center", gap: 4 }}>
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="2.5"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            <span style={{ fontSize: 12, color: "rgba(255,255,255,0.8)", fontWeight: 600 }}>Edit</span>
                        </button>
                    )}
                </div>
                <div style={{ fontSize: 18, fontWeight: 800, color: "white", lineHeight: 1.25, marginBottom: 8 }}>{data.title}</div>
                <div style={{ display: "flex", gap: 8, flexWrap: "wrap", alignItems: "center" }}>
                    <span style={{ fontSize: 11, fontWeight: 700, padding: "3px 10px", borderRadius: 20, background: "rgba(255,255,255,0.15)", color: "rgba(255,255,255,0.9)", border: "1px solid rgba(255,255,255,0.2)" }}>
                        {trainingStatus}
                    </span>
                    {data.program  && <span style={{ fontSize: 11, color: "rgba(255,255,255,0.6)" }}>{data.program}</span>}
                    {data.facility && <span style={{ fontSize: 11, color: "rgba(255,255,255,0.5)" }}>· {data.facility}</span>}
                </div>
            </div>

            {/* ── Tab bar ── */}
            <div style={{ display: "flex", background: "white", borderBottom: `1px solid ${T.border}`, margin: "0 6px", boxShadow: "0 2px 8px rgba(0,0,0,0.03)" }}>
                {TABS.map(t => (
                    <button key={t.key} onClick={() => setActiveTab(t.key)} style={{
                        flex: 1, padding: "13px 0", border: "none", background: "white",
                        borderBottom: activeTab === t.key ? `2.5px solid ${T.primary}` : "2.5px solid transparent",
                        color: activeTab === t.key ? T.primary : T.textMuted,
                        fontSize: 13, fontWeight: activeTab === t.key ? 700 : 500,
                        cursor: "pointer", transition: "all 0.2s",
                    }}>
                        {t.label}
                    </button>
                ))}
            </div>

            {/* ── Tab content ── */}
            <div style={{ padding: 16, display: "flex", flexDirection: "column", gap: 12 }}>
                {startWarning && (
                    <div style={{ background: "#FFFBEB", border: "1px solid #FCD34D", borderRadius: T.radiusSm, padding: "12px 14px", display: "flex", gap: 10, alignItems: "flex-start" }}>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#F59E0B" strokeWidth="2" style={{ flexShrink: 0, marginTop: 1 }}><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                        <div style={{ flex: 1 }}>
                            <div style={{ fontSize: 13, fontWeight: 600, color: "#92400E" }}>Class not started</div>
                            <div style={{ fontSize: 12, color: "#78350F", marginTop: 2 }}>{startWarning}</div>
                        </div>
                        <button onClick={() => setStartWarning(null)} style={{ background: "none", border: "none", color: T.textMuted, fontSize: 16, cursor: "pointer", lineHeight: 1, flexShrink: 0 }}>×</button>
                    </div>
                )}

                {activeTab === "reports" && (
                    <ReportsTab data={data} classes={classes} onSwitchToClasses={() => setActiveTab("classes")} />
                )}
                {activeTab === "classes" && (
                    <ClassesTab data={data} classes={classes} loading={loading} onOpenClass={onOpenClass} onAddClass={onAddClass} onEditClass={onEditClass} onDeleteClass={onDeleteClass} />
                )}
                {activeTab === "info" && (
                    <InfoTab data={data} classes={classes} resources={resources} showResources={showResources} setShowResources={setShowResources} />
                )}
            </div>

            {showEdit && (
                <EditMentorshipPanel
                    data={data}
                    onClose={() => setShowEdit(false)}
                    onSaved={(updated) => {
                        setDetail(prev => ({ ...(prev ?? data), ...updated }));
                        setShowEdit(false);
                    }}
                />
            )}
        </div>
    );
}
