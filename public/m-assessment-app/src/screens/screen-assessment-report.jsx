import { useState, useEffect } from "react";
import { T, GRADE_COLOR, GRADE_BG, GRADE_TEXT, calcGrade } from "../constants.js";
import { BackButton, GradeBadge, ProgressBar } from "../components/shared-components.jsx";
import api from "../services/api.service.js";

// Overall score = sum of 4 scored section percentages / 4 (Blade formula)
const SCORED_SECTION_CODES = ["infrastructure", "skills_lab", "information_systems", "quality_of_care"];
function calcOverallScore(sectionScores) {
    const vals = SCORED_SECTION_CODES.map(c => {
        const raw = sectionScores[c]?.percentage;
        if (raw == null) return null;
        const n = Number(raw);
        return isNaN(n) ? null : n;
    }).filter(v => v !== null);
    if (vals.length === 0) return null;
    return vals.reduce((a, b) => a + b, 0) / 4;
}

const SECTION_ICONS = {
    infrastructure: "🏗️", skills_lab: "🔬", human_resources: "👥",
    health_products: "💊", information_systems: "💻", quality_of_care: "⭐",
};

const SECTION_NAMES = {
    infrastructure: "Infrastructure",
    skills_lab: "Skills Lab",
    human_resources: "Human Resources",
    health_products: "Health Products",
    information_systems: "Information Systems",
    quality_of_care: "Quality of Care",
};

const GRADE_LABEL_MAP = { green: "Good", yellow: "Fair", red: "Needs Work" };
const GRADE_GRADIENT = {
    green:  "linear-gradient(135deg, #059669, #10B981)",
    yellow: "linear-gradient(135deg, #D97706, #F59E0B)",
    red:    "linear-gradient(135deg, #DC2626, #EF4444)",
};

// ── Animated score ring ────────────────────────────────────────────────────────
function ScoreRing({ pct, grade, size = 100 }) {
    const stroke = 9;
    const r = (size - stroke * 2) / 2;
    const circ = 2 * Math.PI * r;
    const offset = circ - (Math.min(Math.max(pct, 0), 100) / 100) * circ;
    const color = GRADE_COLOR[grade] ?? "#9CA3AF";
    return (
        <div style={{ position: "relative", width: size, height: size, flexShrink: 0 }}>
            <svg width={size} height={size} style={{ transform: "rotate(-90deg)" }}>
                <circle cx={size/2} cy={size/2} r={r} fill="none" stroke="rgba(255,255,255,0.15)" strokeWidth={stroke} />
                <circle cx={size/2} cy={size/2} r={r} fill="none" stroke="rgba(255,255,255,0.9)" strokeWidth={stroke}
                    strokeDasharray={circ} strokeDashoffset={offset} strokeLinecap="round"
                    style={{ transition: "stroke-dashoffset 1.2s cubic-bezier(0.34,1.56,0.64,1)" }} />
            </svg>
            <div style={{ position: "absolute", inset: 0, display: "flex", flexDirection: "column", alignItems: "center", justifyContent: "center" }}>
                <span style={{ color: "white", fontSize: 24, fontWeight: 900, lineHeight: 1 }}>{Number(pct).toFixed(1)}%</span>
                {grade && <span style={{ color: "rgba(255,255,255,0.75)", fontSize: 10, fontWeight: 700, marginTop: 2, textTransform: "uppercase", letterSpacing: 0.8 }}>{GRADE_LABEL_MAP[grade] ?? grade}</span>}
            </div>
        </div>
    );
}

// ── Section score card ─────────────────────────────────────────────────────────
function SectionScoreCard({ section, index }) {
    const [open, setOpen] = useState(false);
    const { code, percentage, grade, total_questions, answered_questions, responses } = section;
    const name = SECTION_NAMES[code] ?? section.name ?? code;
    const icon = SECTION_ICONS[code] ?? "📋";
    const color = GRADE_COLOR[grade] ?? "#9CA3AF";
    const pct = Number(percentage ?? 0); // keep as number for circle geometry
    const isSpecial = code === "human_resources" || code === "health_products";

    const RESPONSE_CHIP = {
        Yes:     { bg: "#D1FAE5", color: "#065F46", dot: "#10B981" },
        No:      { bg: "#FEE2E2", color: "#991B1B", dot: "#EF4444" },
        Partial: { bg: "#FEF3C7", color: "#92400E", dot: "#F59E0B" },
    };

    return (
        <div style={{
            background: "white", borderRadius: 18, marginBottom: 10, overflow: "hidden",
            boxShadow: `0 4px 20px ${color}18`,
            border: `1.5px solid ${color}22`,
            animation: `fadeInUp 0.35s ease ${index * 0.06}s both`,
        }}>
            {/* Top accent bar */}
            <div style={{ height: 3, background: `linear-gradient(90deg, ${color}, ${color}55)` }} />

            <button onClick={() => !isSpecial && setOpen(o => !o)} style={{
                width: "100%", padding: "14px 16px", border: "none", background: "none",
                cursor: isSpecial ? "default" : "pointer",
                display: "flex", alignItems: "center", gap: 12, textAlign: "left",
            }}>
                {/* Icon + progress ring */}
                <div style={{ position: "relative", width: 48, height: 48, flexShrink: 0 }}>
                    <svg width="48" height="48" style={{ transform: "rotate(-90deg)" }}>
                        <circle cx="24" cy="24" r="19" fill="none" stroke={`${color}18`} strokeWidth="4" />
                        <circle cx="24" cy="24" r="19" fill="none" stroke={color} strokeWidth="4"
                            strokeDasharray={2 * Math.PI * 19}
                            strokeDashoffset={2 * Math.PI * 19 - (pct / 100) * 2 * Math.PI * 19}
                            strokeLinecap="round"
                            style={{ transition: "stroke-dashoffset 1s ease" }} />
                    </svg>
                    <div style={{ position: "absolute", inset: 0, display: "flex", alignItems: "center", justifyContent: "center", fontSize: 20 }}>
                        {icon}
                    </div>
                </div>

                <div style={{ flex: 1, minWidth: 0 }}>
                    <div style={{ fontWeight: 700, fontSize: 13, color: T.text }}>{name}</div>
                    <div style={{ fontSize: 11, color: T.textMuted, marginTop: 2 }}>
                        {isSpecial
                            ? (code === "human_resources" ? "Staff training data" : "Commodity availability")
                            : `${answered_questions ?? 0}/${total_questions ?? 0} answered`}
                    </div>
                </div>

                <div style={{ display: "flex", flexDirection: "column", alignItems: "flex-end", gap: 4, flexShrink: 0 }}>
                    {grade && (
                        <div style={{ padding: "5px 12px", borderRadius: 20, background: `${color}15`, border: `1px solid ${color}33` }}>
                            <span style={{ fontSize: 14, fontWeight: 900, color }}>{pct.toFixed(1)}%</span>
                        </div>
                    )}
                    {!isSpecial && (
                        <span style={{ color: T.textMuted, fontSize: 13, transform: open ? "rotate(180deg)" : "none", transition: "transform 0.2s" }}>▾</span>
                    )}
                </div>
            </button>

            {/* Expanded responses */}
            {!isSpecial && open && responses && responses.length > 0 && (
                <div style={{ borderTop: `1px solid ${T.borderLight}` }}>
                    {responses.map((resp, i) => {
                        const c = RESPONSE_CHIP[resp.response] ?? { bg: T.borderLight, color: T.textMuted, dot: T.border };
                        return (
                            <div key={i} style={{ padding: "10px 16px", borderBottom: i < responses.length - 1 ? `1px solid ${T.borderLight}` : "none" }}>
                                <div style={{ display: "flex", alignItems: "flex-start", gap: 10 }}>
                                    <div style={{ width: 7, height: 7, borderRadius: "50%", background: c.dot, flexShrink: 0, marginTop: 4 }} />
                                    <div style={{ flex: 1, fontSize: 12, color: T.textMid, lineHeight: 1.5 }}>{resp.question_text}</div>
                                    <div style={{ padding: "3px 10px", borderRadius: 8, fontSize: 11, fontWeight: 700, flexShrink: 0, background: c.bg, color: c.color }}>
                                        {resp.response ?? "—"}
                                    </div>
                                </div>
                                {resp.explanation && (
                                    <div style={{ marginTop: 5, paddingLeft: 17, fontSize: 11, color: T.textSub, fontStyle: "italic" }}>
                                        💬 {resp.explanation}
                                    </div>
                                )}
                            </div>
                        );
                    })}
                </div>
            )}
            {!isSpecial && open && (!responses || responses.length === 0) && (
                <div style={{ padding: "12px 16px", borderTop: `1px solid ${T.borderLight}`, fontSize: 12, color: T.textMuted }}>
                    No responses recorded for this section.
                </div>
            )}
        </div>
    );
}

// ── HR table ──────────────────────────────────────────────────────────────────
function HrSection({ assessment }) {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        api.humanResources.get(assessment.id)
            .then(r => setData(r?.data ?? []))
            .catch(() => setData([]))
            .finally(() => setLoading(false));
    }, [assessment.id]);

    if (loading) return <div style={{ padding: "20px", textAlign: "center", color: T.textMuted, fontSize: 12 }}>Loading…</div>;
    if (!data || data.length === 0) return <div style={{ padding: "16px", fontSize: 12, color: T.textMuted }}>No HR data recorded.</div>;

    const cols = ["total_in_facility", "etat_plus", "comprehensive_newborn_care", "imnci", "type_1_diabetes", "essential_newborn_care"];
    const labels = { total_in_facility: "Total\nStaff", etat_plus: "ETAT+", comprehensive_newborn_care: "Comp.\nNB", imnci: "IMNCI", type_1_diabetes: "T1\nDM", essential_newborn_care: "ENC" };

    // Compute totals
    const totals = cols.reduce((acc, c) => {
        acc[c] = data.reduce((s, row) => s + (Number(row[c]) || 0), 0);
        return acc;
    }, {});

    return (
        <div style={{ overflowX: "auto", margin: "0 -16px" }}>
            <table style={{ width: "100%", borderCollapse: "collapse", fontSize: 11, minWidth: 380 }}>
                <thead>
                    <tr style={{ background: "linear-gradient(135deg, #F0FDF4, #ECFDF5)" }}>
                        <th style={{ padding: "10px 14px", textAlign: "left", color: T.textMid, fontWeight: 700, borderBottom: `2px solid #D1FAE5` }}>Cadre</th>
                        {cols.map(c => (
                            <th key={c} style={{ padding: "10px 8px", textAlign: "center", color: T.textMid, fontWeight: 700, borderBottom: `2px solid #D1FAE5`, whiteSpace: "pre-line", lineHeight: 1.3 }}>
                                {labels[c]}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {data.map((row, i) => (
                        <tr key={i} style={{ borderBottom: `1px solid ${T.borderLight}`, background: i % 2 === 0 ? "white" : "#FAFAFA" }}>
                            <td style={{ padding: "10px 14px", color: T.textMid, fontWeight: 600 }}>{row.cadre_name ?? "—"}</td>
                            {cols.map(c => {
                                const val = Number(row[c]) || 0;
                                const isTotalCol = c === "total_in_facility";
                                return (
                                    <td key={c} style={{ padding: "10px 8px", textAlign: "center" }}>
                                        {val > 0 ? (
                                            <span style={{ display: "inline-block", minWidth: 24, padding: "2px 6px", borderRadius: 6, background: isTotalCol ? "#EDE9FE" : "#D1FAE5", color: isTotalCol ? "#5B21B6" : "#065F46", fontWeight: 700, fontSize: 11 }}>{val}</span>
                                        ) : (
                                            <span style={{ color: T.border, fontSize: 13 }}>—</span>
                                        )}
                                    </td>
                                );
                            })}
                        </tr>
                    ))}
                    {/* Totals row */}
                    <tr style={{ background: "linear-gradient(135deg, #EFF6FF, #F0F9FF)", borderTop: `2px solid #BFDBFE` }}>
                        <td style={{ padding: "10px 14px", color: "#1D4ED8", fontWeight: 800, fontSize: 11, textTransform: "uppercase", letterSpacing: 0.5 }}>Total</td>
                        {cols.map(c => (
                            <td key={c} style={{ padding: "10px 8px", textAlign: "center" }}>
                                <span style={{ fontWeight: 800, color: "#1D4ED8", fontSize: 12 }}>{totals[c]}</span>
                            </td>
                        ))}
                    </tr>
                </tbody>
            </table>
        </div>
    );
}

// ── HP commodities ─────────────────────────────────────────────────────────────
function HpSection({ assessment }) {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        api.healthProducts.get(assessment.id)
            .then(r => setData(r?.data ?? []))
            .catch(() => setData([]))
            .finally(() => setLoading(false));
    }, [assessment.id]);

    if (loading) return <div style={{ padding: "20px", textAlign: "center", color: T.textMuted, fontSize: 12 }}>Loading…</div>;
    if (!data || data.length === 0) return <div style={{ padding: "16px", fontSize: 12, color: T.textMuted }}>No commodity data.</div>;

    return (
        <div>
            {data.map((dept, di) => {
                const allCmds = dept.categories?.flatMap(c => c.commodities) ?? [];
                const answered = allCmds.filter(c => c.available !== null).length;
                const available = allCmds.filter(c => c.available === true).length;
                const pct = answered > 0 ? +((available / answered) * 100).toFixed(1) : 0;
                const grade = calcGrade(pct);
                const color = GRADE_COLOR[grade];
                return (
                    <div key={dept.department_id} style={{ marginBottom: di < data.length - 1 ? 18 : 0 }}>
                        {/* Dept header */}
                        <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", marginBottom: 10 }}>
                            <div style={{ fontSize: 12, fontWeight: 800, color: T.textMid }}>{dept.department_name}</div>
                            <div style={{ display: "flex", alignItems: "center", gap: 6 }}>
                                <div style={{ fontSize: 10, color: T.textMuted }}>{available}/{answered} available</div>
                                <div style={{ padding: "3px 8px", borderRadius: 6, background: `${color}18`, color, fontSize: 11, fontWeight: 700 }}>{pct}%</div>
                            </div>
                        </div>
                        <div style={{ height: 4, background: T.borderLight, borderRadius: 999, overflow: "hidden", marginBottom: 10 }}>
                            <div style={{ height: "100%", width: `${pct}%`, background: `linear-gradient(90deg, ${color}, ${color}88)`, borderRadius: 999, transition: "width 0.8s ease" }} />
                        </div>

                        {dept.categories?.map(cat => (
                            <div key={cat.category_id} style={{ marginBottom: 8 }}>
                                <div style={{ fontSize: 10, fontWeight: 700, color: T.textSub, textTransform: "uppercase", letterSpacing: 0.5, marginBottom: 5 }}>{cat.category_name}</div>
                                <div style={{ display: "flex", flexWrap: "wrap", gap: 5 }}>
                                    {(cat.commodities ?? []).map(c => (
                                        <div key={c.commodity_id} style={{
                                            padding: "4px 10px", borderRadius: 20, fontSize: 11, fontWeight: 600,
                                            background: c.available === true ? "#D1FAE5" : c.available === false ? "#FEE2E2" : T.borderLight,
                                            color: c.available === true ? "#065F46" : c.available === false ? "#991B1B" : T.textMuted,
                                            border: `1px solid ${c.available === true ? "#6EE7B733" : c.available === false ? "#FCA5A533" : T.border}`,
                                            display: "flex", alignItems: "center", gap: 4,
                                        }}>
                                            <span style={{ fontSize: 10 }}>{c.available === true ? "✓" : c.available === false ? "✗" : "·"}</span>
                                            {c.name}
                                        </div>
                                    ))}
                                </div>
                            </div>
                        ))}
                    </div>
                );
            })}
        </div>
    );
}

// ── Special section wrapper ────────────────────────────────────────────────────
function SpecialCard({ title, icon, code, children, index }) {
    const [open, setOpen] = useState(false);
    return (
        <div style={{
            background: "white", borderRadius: 18, marginBottom: 10, overflow: "hidden",
            boxShadow: T.shadowCard, border: `1.5px solid ${T.border}`,
            animation: `fadeInUp 0.35s ease ${index * 0.06}s both`,
        }}>
            <div style={{ height: 3, background: `linear-gradient(90deg, ${code === "human_resources" ? "#6C5CE7" : "#0EA5E9"}, ${code === "human_resources" ? "#A29BFE" : "#7DD3FC"})` }} />
            <button onClick={() => setOpen(o => !o)} style={{
                width: "100%", padding: "14px 16px", border: "none", background: "none",
                cursor: "pointer", display: "flex", alignItems: "center", gap: 12, textAlign: "left",
            }}>
                <div style={{
                    width: 48, height: 48, borderRadius: 14, flexShrink: 0, fontSize: 22,
                    background: code === "human_resources" ? "linear-gradient(135deg, #EDE9FE, #DDD6FE)" : "linear-gradient(135deg, #E0F2FE, #BAE6FD)",
                    display: "flex", alignItems: "center", justifyContent: "center",
                }}>
                    {icon}
                </div>
                <div style={{ flex: 1 }}>
                    <div style={{ fontWeight: 700, fontSize: 13, color: T.text }}>{title}</div>
                    <div style={{ fontSize: 11, color: T.textMuted, marginTop: 2 }}>Tap to {open ? "collapse" : "expand"}</div>
                </div>
                <span style={{ color: T.textMuted, fontSize: 20, transform: open ? "rotate(180deg)" : "none", transition: "transform 0.25s" }}>▾</span>
            </button>
            {open && (
                <div style={{ borderTop: `1px solid ${T.borderLight}`, padding: "14px 16px" }}>
                    {children}
                </div>
            )}
        </div>
    );
}

// ── Recommendations ────────────────────────────────────────────────────────────
function RecommendationsCard({ sectionReports }) {
    const issues = [];
    sectionReports.forEach(s => {
        (s.responses ?? []).forEach(r => {
            if (r.response === "No") issues.push({ type: "critical", section: s.name, text: r.question_text });
            if (r.response === "Partial") issues.push({ type: "warning", section: s.name, text: r.question_text });
        });
    });
    if (issues.length === 0) return (
        <div style={{ padding: 20, background: "linear-gradient(135deg, #D1FAE5, #ECFDF5)", borderRadius: 18, border: "1.5px solid #6EE7B7", textAlign: "center" }}>
            <div style={{ fontSize: 32, marginBottom: 8 }}>🎉</div>
            <div style={{ fontSize: 15, fontWeight: 800, color: "#064E3B", marginBottom: 4 }}>Excellent Performance</div>
            <div style={{ fontSize: 12, color: "#065F46" }}>No critical issues identified across all sections.</div>
        </div>
    );

    const critical = issues.filter(i => i.type === "critical").slice(0, 6);
    const warnings = issues.filter(i => i.type === "warning").slice(0, 4);

    return (
        <div style={{ background: "white", borderRadius: 18, overflow: "hidden", boxShadow: T.shadowCard, border: "1.5px solid #FEE2E2" }}>
            <div style={{ padding: "14px 16px 12px", background: "linear-gradient(135deg, #FFF1F2, #FEF2F2)", borderBottom: "1px solid #FEE2E2", display: "flex", alignItems: "center", gap: 10 }}>
                <div style={{ width: 36, height: 36, borderRadius: 10, background: "#FEE2E2", display: "flex", alignItems: "center", justifyContent: "center", fontSize: 18 }}>⚠️</div>
                <div>
                    <div style={{ fontSize: 13, fontWeight: 800, color: "#991B1B" }}>Improvement Areas</div>
                    <div style={{ fontSize: 11, color: "#B91C1C", marginTop: 1 }}>{critical.length} critical · {warnings.length} partial</div>
                </div>
            </div>
            <div style={{ padding: "10px 16px 14px" }}>
                {[...critical, ...warnings].map((item, i) => (
                    <div key={i} style={{
                        display: "flex", gap: 10, padding: "10px 12px", borderRadius: 12, marginBottom: i < critical.length + warnings.length - 1 ? 7 : 0,
                        background: item.type === "critical" ? "#FEF2F2" : "#FFFBEB",
                        border: `1px solid ${item.type === "critical" ? "#FECACA" : "#FDE68A"}`,
                    }}>
                        <div style={{ width: 6, height: 6, borderRadius: "50%", background: item.type === "critical" ? "#EF4444" : "#F59E0B", flexShrink: 0, marginTop: 4 }} />
                        <div style={{ flex: 1 }}>
                            <div style={{ fontSize: 10, fontWeight: 700, color: item.type === "critical" ? "#DC2626" : "#D97706", textTransform: "uppercase", letterSpacing: 0.5, marginBottom: 2 }}>{item.section}</div>
                            <div style={{ fontSize: 12, lineHeight: 1.4, color: item.type === "critical" ? "#7F1D1D" : "#78350F" }}>
                                {item.text.length > 100 ? item.text.slice(0, 100) + "…" : item.text}
                            </div>
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}

// ── Email share modal ──────────────────────────────────────────────────────────
function EmailShareModal({ assessment, onClose, onSent }) {
    const [input, setInput] = useState("");
    const [emails, setEmails] = useState([]);
    const [inputError, setInputError] = useState(null);
    const [submitting, setSubmitting] = useState(false);
    const [result, setResult] = useState(null); // { type: "success"|"queued"|"error", text }

    const isValidEmail = (e) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(e.trim());

    function addEmails() {
        const parts = input.split(/[\s,;\n]+/).map(s => s.trim()).filter(Boolean);
        const invalid = parts.filter(p => !isValidEmail(p));
        if (invalid.length > 0) {
            setInputError(`Invalid email${invalid.length > 1 ? "s" : ""}: ${invalid.join(", ")}`);
            return;
        }
        const merged = [...new Set([...emails, ...parts])];
        if (merged.length > 10) { setInputError("Maximum 10 recipients."); return; }
        setEmails(merged);
        setInput("");
        setInputError(null);
    }

    function removeEmail(e) {
        setEmails(prev => prev.filter(x => x !== e));
    }

    function handleKeyDown(e) {
        if (e.key === "Enter" || e.key === ",") { e.preventDefault(); addEmails(); }
    }

    async function handleSend() {
        if (emails.length === 0) { setInputError("Add at least one email address."); return; }
        setSubmitting(true);
        setResult(null);
        try {
            const res = await api.reports.emailReport(assessment.id, emails);
            if (res?.queued) {
                setResult({ type: "queued", text: `Queued — will send when back online (${emails.length} recipient${emails.length > 1 ? "s" : ""}).` });
            } else {
                setResult({ type: "success", text: `Report queued for delivery to ${emails.length} recipient${emails.length > 1 ? "s" : ""}.` });
            }
            onSent?.();
        } catch (e) {
            setResult({ type: "error", text: e.message || "Failed to queue email. Please try again." });
        } finally {
            setSubmitting(false);
        }
    }

    const sent = result?.type === "success" || result?.type === "queued";

    return (
        <>
            <div onClick={sent ? onClose : undefined} style={{ position: "fixed", inset: 0, background: "rgba(0,0,0,0.55)", zIndex: 2000, backdropFilter: "blur(4px)", WebkitBackdropFilter: "blur(4px)" }} />
            <div style={{ position: "fixed", bottom: 0, left: 0, right: 0, zIndex: 2001, background: "#fff", borderRadius: "20px 20px 0 0", padding: "20px 20px 32px", boxShadow: "0 -8px 40px rgba(0,0,0,0.18)" }}>
                {/* Handle */}
                <div style={{ width: 36, height: 4, background: "#E5E7EB", borderRadius: 99, margin: "0 auto 16px" }} />

                <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", marginBottom: 16 }}>
                    <div style={{ fontSize: 16, fontWeight: 800, color: T.text }}>Share Report via Email</div>
                    <button onClick={onClose} style={{ background: "none", border: "none", fontSize: 22, color: T.textMuted, cursor: "pointer", lineHeight: 1, padding: "2px 6px" }}>×</button>
                </div>

                <div style={{ fontSize: 12, color: T.textSub, marginBottom: 14, lineHeight: 1.5 }}>
                    The PDF report for <strong style={{ color: T.textMid }}>{assessment.facility_name}</strong> will be attached and emailed to the recipients below.
                </div>

                {/* Email chips */}
                {emails.length > 0 && (
                    <div style={{ display: "flex", flexWrap: "wrap", gap: 6, marginBottom: 10 }}>
                        {emails.map(e => (
                            <div key={e} style={{ display: "flex", alignItems: "center", gap: 5, background: T.primaryGhost, border: `1px solid ${T.primary}22`, borderRadius: 20, padding: "4px 10px 4px 12px", fontSize: 12, fontWeight: 600, color: T.primary }}>
                                {e}
                                <button onClick={() => removeEmail(e)} disabled={submitting} style={{ background: "none", border: "none", color: T.primary, cursor: "pointer", fontSize: 14, lineHeight: 1, padding: 0, opacity: submitting ? 0.5 : 1 }}>×</button>
                            </div>
                        ))}
                    </div>
                )}

                {!sent && (
                    <>
                        <div style={{ display: "flex", gap: 8 }}>
                            <input
                                type="email"
                                value={input}
                                onChange={e => { setInput(e.target.value); setInputError(null); }}
                                onKeyDown={handleKeyDown}
                                onBlur={() => { if (input.trim()) addEmails(); }}
                                placeholder="email@example.com, another@example.com"
                                disabled={submitting}
                                style={{ flex: 1, borderRadius: 10, border: `1px solid ${inputError ? "#EF4444" : T.border}`, padding: "10px 12px", fontSize: 13, color: T.text, outline: "none", background: "#fff" }}
                            />
                            <button onClick={addEmails} disabled={!input.trim() || submitting} style={{ background: T.gradientPrimary, color: "#fff", border: "none", borderRadius: 10, padding: "0 16px", fontWeight: 700, fontSize: 13, cursor: "pointer", opacity: !input.trim() ? 0.5 : 1 }}>Add</button>
                        </div>
                        {inputError && <div style={{ fontSize: 12, color: "#EF4444", marginTop: 6 }}>{inputError}</div>}
                        <div style={{ fontSize: 11, color: T.textMuted, marginTop: 6 }}>Press Enter or comma to add. Up to 10 recipients.</div>
                    </>
                )}

                {result && (
                    <div style={{ marginTop: 14, padding: "12px 14px", borderRadius: 10, fontSize: 13, fontWeight: 600, background: result.type === "error" ? "#FEE2E2" : result.type === "queued" ? "#FEF3C7" : "#D1FAE5", color: result.type === "error" ? "#991B1B" : result.type === "queued" ? "#92400E" : "#065F46" }}>
                        {result.type === "queued" && "📵 "}
                        {result.type === "success" && "✓ "}
                        {result.type === "error" && "⚠ "}
                        {result.text}
                    </div>
                )}

                {!sent && (
                    <button onClick={handleSend} disabled={submitting || emails.length === 0} style={{ width: "100%", marginTop: 16, padding: 14, background: T.gradientPrimary, color: "#fff", border: "none", borderRadius: 12, fontSize: 14, fontWeight: 700, cursor: submitting || emails.length === 0 ? "not-allowed" : "pointer", opacity: emails.length === 0 ? 0.5 : 1, display: "flex", alignItems: "center", justifyContent: "center", gap: 8 }}>
                        {submitting ? "Sending…" : `Send Report${emails.length > 0 ? ` to ${emails.length} Recipient${emails.length > 1 ? "s" : ""}` : ""}`}
                    </button>
                )}
                {sent && (
                    <button onClick={onClose} style={{ width: "100%", marginTop: 14, padding: 14, background: "#fff", color: T.primary, border: `1.5px solid ${T.primary}`, borderRadius: 12, fontSize: 14, fontWeight: 700, cursor: "pointer" }}>
                        Done
                    </button>
                )}
            </div>
        </>
    );
}

// ── Main screen ────────────────────────────────────────────────────────────────
export function AssessmentReportScreen({ assessment, onBack }) {
    const [report, setReport] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [downloading, setDownloading] = useState(false);
    const [shareMsg, setShareMsg] = useState(null);
    const [showEmailModal, setShowEmailModal] = useState(false);

    useEffect(() => {
        api.reports.show(assessment.id)
            .then(data => setReport(data))
            .catch(e => setError(e.message || "Failed to load report"))
            .finally(() => setLoading(false));
    }, [assessment.id]);

    const handleDownload = async () => {
        setDownloading(true);
        setShareMsg(null);
        try {
            const res = await api.reports.downloadPdf(assessment.id);
            const url = res?.download_url;
            if (url) { window.open(url, "_blank"); setShareMsg({ type: "success", text: "PDF opened in new tab." }); }
            else setShareMsg({ type: "error", text: "PDF URL not available." });
        } catch (e) {
            setShareMsg({ type: "error", text: e.message || "PDF generation failed." });
        } finally {
            setDownloading(false);
            setTimeout(() => setShareMsg(null), 4000);
        }
    };

    const sectionScores = assessment.section_scores ?? {};
    const _calcPct = calcOverallScore(sectionScores);
    const pct = _calcPct ?? assessment.overall_percentage ?? report?.assessment?.overall_percentage ?? 0;
    const grade = _calcPct != null ? calcGrade(_calcPct) : (assessment.overall_grade ?? report?.assessment?.overall_grade);
    const sectionReports = report?.section_reports ?? [];
    const gradeColor = GRADE_COLOR[grade] ?? "#6B7280";
    const gradeGradient = GRADE_GRADIENT[grade] ?? "linear-gradient(135deg, #374151, #6B7280)";

    // Section order: scored first, then special
    const scoredReports = sectionReports.filter(s => !["human_resources", "health_products"].includes(s.code));
    const hrReport = sectionReports.find(s => s.code === "human_resources");
    const hpReport = sectionReports.find(s => s.code === "health_products");

    // Fallback from section_scores if no full report
    const scoredFallback = Object.entries(sectionScores)
        .filter(([code]) => !["human_resources", "health_products"].includes(code))
        .map(([code, sc]) => ({ code, name: SECTION_NAMES[code] ?? sc.name ?? code, ...sc, responses: [] }));

    return (
        <div style={{ display: "flex", flexDirection: "column", height: "100%" }}>
            {showEmailModal && (
                <EmailShareModal
                    assessment={assessment}
                    onClose={() => setShowEmailModal(false)}
                    onSent={() => {}}
                />
            )}
            {/* ── Hero Header ─────────────────────────────────────────────── */}
            <div style={{
                background: gradeGradient,
                padding: "20px 20px 26px", position: "relative", overflow: "hidden",
                borderRadius: "24px 24px 32px 32px",
                boxShadow: `0 8px 32px ${gradeColor}33`,
            }}>
                <div style={{ position: "absolute", width: 180, height: 180, borderRadius: "50%", background: "rgba(255,255,255,0.06)", top: -60, right: -50 }} />
                <div style={{ position: "absolute", width: 80, height: 80, borderRadius: "50%", background: "rgba(255,255,255,0.04)", bottom: 10, left: -20 }} />

                <BackButton onBack={onBack} light />

                <div style={{ display: "flex", alignItems: "center", gap: 16, marginTop: 14 }}>
                    <ScoreRing pct={Number(pct) || 0} grade={grade} size={100} />
                    <div style={{ flex: 1, minWidth: 0 }}>
                        <div style={{ color: "rgba(255,255,255,0.6)", fontSize: 11, fontWeight: 700, textTransform: "uppercase", letterSpacing: 0.8, marginBottom: 4 }}>Assessment Report</div>
                        <div style={{ color: "white", fontSize: 16, fontWeight: 800, lineHeight: 1.2, marginBottom: 4 }}>
                            {assessment.facility_name}
                        </div>
                        {(assessment.mfl_code || assessment.subcounty || assessment.county) && (
                            <div style={{ display: "flex", flexWrap: "wrap", gap: 4, marginBottom: 4 }}>
                                {[assessment.mfl_code && `MFL: ${assessment.mfl_code}`, assessment.subcounty, assessment.county]
                                    .filter(Boolean).map(t => (
                                    <span key={t} style={{ padding: "2px 8px", borderRadius: 20, background: "rgba(255,255,255,0.15)", color: "rgba(255,255,255,0.85)", fontSize: 10, fontWeight: 600 }}>{t}</span>
                                ))}
                            </div>
                        )}
                        <div style={{ color: "rgba(255,255,255,0.55)", fontSize: 11, marginBottom: 2 }}>
                            {[assessment.assessment_type, assessment.assessment_date].filter(Boolean).join(" · ")}
                        </div>
                        {(report?.assessment?.lead_assessor?.name || assessment.lead_assessor?.name || assessment.assessor_name) && (
                            <div style={{ color: "rgba(255,255,255,0.4)", fontSize: 10 }}>Lead Assessor: {report?.assessment?.lead_assessor?.name ?? assessment.lead_assessor?.name ?? assessment.assessor_name}</div>
                        )}
                        {((report?.assessment?.team_members ?? assessment.team_members ?? []).length > 0) && (
                            <div style={{ color: "rgba(255,255,255,0.4)", fontSize: 10, marginTop: 2 }}>Team: {(report?.assessment?.team_members ?? assessment.team_members).map(member => member.name).join(', ')}</div>
                        )}
                    </div>
                </div>

                {/* Section score pills */}
                {Object.keys(sectionScores).length > 0 && (
                    <div style={{ display: "flex", gap: 6, marginTop: 16, flexWrap: "wrap" }}>
                        {Object.entries(sectionScores).map(([code, sc]) => {
                            if (!sc?.percentage) return null;
                            const c = GRADE_COLOR[sc.grade] ?? "#9CA3AF";
                            return (
                                <div key={code} style={{ padding: "4px 10px", borderRadius: 20, background: "rgba(255,255,255,0.15)", border: "1px solid rgba(255,255,255,0.2)", display: "flex", alignItems: "center", gap: 5 }}>
                                    <span style={{ fontSize: 12 }}>{SECTION_ICONS[code] ?? "📋"}</span>
                                    <span style={{ color: "white", fontSize: 11, fontWeight: 700 }}>{Number(sc.percentage).toFixed(1)}%</span>
                                </div>
                            );
                        })}
                    </div>
                )}

                {/* Action buttons */}
                <div style={{ display: "flex", gap: 8, marginTop: 14 }}>
                    <button onClick={handleDownload} disabled={downloading} style={{ flex: 1, padding: "10px 12px", borderRadius: 14, border: "1.5px solid rgba(255,255,255,0.3)", background: "rgba(255,255,255,0.12)", color: "white", fontSize: 12, fontWeight: 700, cursor: downloading ? "default" : "pointer", display: "flex", alignItems: "center", justifyContent: "center", gap: 6 }}>
                        {downloading ? "⏳" : "📥"} {downloading ? "Generating…" : "Download PDF"}
                    </button>
                    <button onClick={() => setShowEmailModal(true)} style={{ flex: 1, padding: "10px 12px", borderRadius: 14, border: "1.5px solid rgba(255,255,255,0.3)", background: "rgba(255,255,255,0.12)", color: "white", fontSize: 12, fontWeight: 700, cursor: "pointer", display: "flex", alignItems: "center", justifyContent: "center", gap: 6 }}>
                        📧 Share via Email
                    </button>
                </div>

                {shareMsg && (
                    <div style={{ marginTop: 8, padding: "6px 12px", borderRadius: 8, fontSize: 11, fontWeight: 600, background: shareMsg.type === "success" ? "rgba(16,185,129,0.25)" : "rgba(239,68,68,0.25)", color: "white" }}>
                        {shareMsg.text}
                    </div>
                )}
            </div>

            {/* ── Body ────────────────────────────────────────────────────── */}
            <div style={{ flex: 1, overflowY: "auto", padding: "16px 16px 100px", background: T.bg }}>

                {loading && (
                    <div style={{ textAlign: "center", padding: "40px 16px", color: T.textMuted }}>
                        <div style={{ width: 44, height: 44, borderRadius: 14, margin: "0 auto 12px", background: T.gradientPrimary, display: "flex", alignItems: "center", justifyContent: "center", boxShadow: `0 6px 20px ${T.primaryGlow}` }}>
                            <svg width="22" height="22" viewBox="0 0 24 24" style={{ animation: "spin 1s linear infinite" }}>
                                <circle cx="12" cy="12" r="10" fill="none" stroke="rgba(255,255,255,0.25)" strokeWidth="3" />
                                <path d="M12 2a10 10 0 019.95 9" fill="none" stroke="white" strokeWidth="3" strokeLinecap="round" />
                            </svg>
                        </div>
                        <div style={{ fontSize: 13 }}>Loading full report…</div>
                    </div>
                )}

                {error && (
                    <div style={{ padding: "12px 14px", background: "#FEF3C7", borderRadius: 12, fontSize: 12, color: "#92400E", marginBottom: 14, border: "1px solid #FDE68A" }}>
                        ⚠️ {error} — showing summary only.
                    </div>
                )}

                {/* Not completed */}
                {assessment.status !== "completed" && (
                    <div style={{ padding: "16px", background: "linear-gradient(135deg, #FEF3C7, #FFFBEB)", borderRadius: 16, border: "1.5px solid #FDE68A", fontSize: 13, color: "#92400E", textAlign: "center", marginBottom: 14 }}>
                        ⚡ Full report will be available once you submit the assessment.
                    </div>
                )}

                {/* Section label */}
                {(sectionReports.length > 0 || Object.keys(sectionScores).length > 0) && (
                    <div style={{ fontSize: 11, fontWeight: 700, color: T.textMuted, textTransform: "uppercase", letterSpacing: 0.8, marginBottom: 12, display: "flex", alignItems: "center", gap: 6 }}>
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke={T.primary} strokeWidth="2"><rect x="3" y="3" width="7" height="7" /><rect x="14" y="3" width="7" height="7" /><rect x="14" y="14" width="7" height="7" /><rect x="3" y="14" width="7" height="7" /></svg>
                        Section Results
                    </div>
                )}

                {/* Scored sections */}
                {scoredReports.length > 0 && scoredReports.map((s, i) => (
                    <SectionScoreCard key={s.code} section={{ ...s, ...(sectionScores[s.code] ?? {}) }} index={i} />
                ))}

                {/* Fallback section cards */}
                {!loading && scoredReports.length === 0 && scoredFallback.map((s, i) => (
                    <SectionScoreCard key={s.code} section={s} index={i} />
                ))}

                {/* Special sections */}
                {(hrReport || sectionScores.human_resources) && (
                    <SpecialCard title="Human Resources" icon="👥" code="human_resources" index={scoredReports.length || scoredFallback.length}>
                        <HrSection assessment={assessment} />
                    </SpecialCard>
                )}
                {(hpReport || sectionScores.health_products) && (
                    <SpecialCard title="Health Products" icon="💊" code="health_products" index={(scoredReports.length || scoredFallback.length) + 1}>
                        <HpSection assessment={assessment} />
                    </SpecialCard>
                )}

                {/* Recommendations */}
                {!loading && sectionReports.length > 0 && (
                    <div style={{ marginTop: 4 }}>
                        <div style={{ fontSize: 11, fontWeight: 700, color: T.textMuted, textTransform: "uppercase", letterSpacing: 0.8, marginBottom: 12, display: "flex", alignItems: "center", gap: 6 }}>
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke={T.primary} strokeWidth="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2" /></svg>
                            Action Points
                        </div>
                        <RecommendationsCard sectionReports={sectionReports} />
                    </div>
                )}
            </div>
        </div>
    );
}
