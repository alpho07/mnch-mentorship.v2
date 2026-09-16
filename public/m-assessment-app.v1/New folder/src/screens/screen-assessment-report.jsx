import { useState, useEffect, useCallback } from "react";
import { T, GRADE_COLOR, GRADE_BG, GRADE_TEXT, calcGrade } from "../constants.js";
import { BackButton, GradeBadge, ProgressBar } from "../components/shared-components.jsx";
import api from "../services/api.service.js";

// ── Helpers ───────────────────────────────────────────────────────────────────
const SECTION_ICONS = {
    infrastructure: "🏗️", skills_lab: "🔬", human_resources: "👥",
    health_products: "💊", information_systems: "💻", quality_of_care: "⭐",
};

const RESPONSE_CHIP = {
    Yes: { bg: "#D1FAE5", color: "#065F46", label: "Yes" },
    No: { bg: "#FEE2E2", color: "#991B1B", label: "No" },
    Partial: { bg: "#FEF3C7", color: "#92400E", label: "Partial" },
};

function chip(val) {
    return RESPONSE_CHIP[val] ?? { bg: T.borderLight, color: T.textMuted, label: val ?? "—" };
}

// ── Grade ring ────────────────────────────────────────────────────────────────
function GradeRing({ pct, grade, size = 80 }) {
    const stroke = 7;
    const r = (size - stroke * 2) / 2;
    const circ = 2 * Math.PI * r;
    const offset = circ - (Math.min(pct, 100) / 100) * circ;
    const color = GRADE_COLOR[grade] ?? "#9CA3AF";
    return (
        <div style={{ position: "relative", width: size, height: size, flexShrink: 0 }}>
            <svg width={size} height={size} style={{ transform: "rotate(-90deg)" }}>
                <circle cx={size / 2} cy={size / 2} r={r} fill="none" stroke={T.borderLight} strokeWidth={stroke} />
                <circle cx={size / 2} cy={size / 2} r={r} fill="none" stroke={color} strokeWidth={stroke}
                    strokeDasharray={circ} strokeDashoffset={offset} strokeLinecap="round"
                    style={{ transition: "stroke-dashoffset 0.8s ease" }} />
            </svg>
            <div style={{ position: "absolute", inset: 0, display: "flex", flexDirection: "column", alignItems: "center", justifyContent: "center" }}>
                <span style={{ fontSize: 16, fontWeight: 900, color, lineHeight: 1 }}>{Math.round(pct)}%</span>
                {grade && <span style={{ fontSize: 9, fontWeight: 700, color, marginTop: 1, textTransform: "uppercase" }}>{grade}</span>}
            </div>
        </div>
    );
}

// ── Section card ──────────────────────────────────────────────────────────────
function SectionReportCard({ section }) {
    const [open, setOpen] = useState(false);
    const { code, name, percentage, grade, total_questions, answered_questions, responses } = section;
    const icon = SECTION_ICONS[code] ?? "📋";
    const color = GRADE_COLOR[grade] ?? "#9CA3AF";

    return (
        <div style={{ background: T.card, borderRadius: T.radius, marginBottom: 12, overflow: "hidden", boxShadow: T.shadow, border: `1px solid ${grade ? GRADE_COLOR[grade] + "33" : T.border}` }}>
            {/* Section header */}
            <button onClick={() => setOpen(o => !o)} style={{
                width: "100%", padding: "14px 16px", border: "none", background: "none",
                cursor: "pointer", display: "flex", alignItems: "center", gap: 12, textAlign: "left",
            }}>
                <div style={{ width: 40, height: 40, borderRadius: 12, background: `${color}18`, display: "flex", alignItems: "center", justifyContent: "center", fontSize: 20, flexShrink: 0 }}>
                    {icon}
                </div>
                <div style={{ flex: 1, minWidth: 0 }}>
                    <div style={{ fontWeight: 700, fontSize: 13, color: T.text }}>{name}</div>
                    <div style={{ fontSize: 11, color: T.textMuted, marginTop: 2 }}>
                        {answered_questions ?? 0} / {total_questions ?? 0} answered
                    </div>
                    <div style={{ height: 3, background: T.borderLight, borderRadius: 999, overflow: "hidden", marginTop: 5 }}>
                        <div style={{ height: "100%", width: `${percentage ?? 0}%`, background: color, borderRadius: 999 }} />
                    </div>
                </div>
                <div style={{ display: "flex", flexDirection: "column", alignItems: "flex-end", gap: 4 }}>
                    {grade && <GradeBadge grade={grade} pct={Math.round(percentage ?? 0)} />}
                    <span style={{ color: T.textMuted, fontSize: 14, transform: open ? "rotate(180deg)" : "none", transition: "transform 0.2s" }}>▾</span>
                </div>
            </button>

            {/* Responses detail */}
            {open && responses && responses.length > 0 && (
                <div style={{ borderTop: `1px solid ${T.border}` }}>
                    {responses.map((resp, i) => {
                        const c = chip(resp.response);
                        return (
                            <div key={i} style={{
                                padding: "10px 16px",
                                borderBottom: i < responses.length - 1 ? `1px solid ${T.borderLight}` : "none",
                            }}>
                                <div style={{ display: "flex", alignItems: "flex-start", gap: 10 }}>
                                    <div style={{ flex: 1, fontSize: 12, color: T.textMid, lineHeight: 1.4 }}>
                                        {resp.question_text}
                                    </div>
                                    <div style={{ padding: "3px 10px", borderRadius: 8, fontSize: 11, fontWeight: 700, flexShrink: 0, background: c.bg, color: c.color, whiteSpace: "nowrap" }}>
                                        {c.label}
                                    </div>
                                </div>
                                {resp.explanation && (
                                    <div style={{ marginTop: 4, fontSize: 11, color: T.textSub, fontStyle: "italic" }}>
                                        💬 {resp.explanation}
                                    </div>
                                )}
                            </div>
                        );
                    })}
                </div>
            )}
            {open && (!responses || responses.length === 0) && (
                <div style={{ padding: "12px 16px", borderTop: `1px solid ${T.border}`, fontSize: 12, color: T.textMuted }}>
                    No question responses recorded for this section.
                </div>
            )}
        </div>
    );
}

// ── HR Section ────────────────────────────────────────────────────────────────
function HrSection({ assessment }) {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        api.humanResources.get(assessment.id)
            .then(r => setData(r?.data ?? []))
            .catch(() => setData([]))
            .finally(() => setLoading(false));
    }, [assessment.id]);

    if (loading) return <div style={{ padding: "12px 16px", fontSize: 12, color: T.textMuted }}>Loading HR data…</div>;
    if (!data || data.length === 0) return <div style={{ padding: "12px 16px", fontSize: 12, color: T.textMuted }}>No HR data recorded.</div>;

    const cols = ["etat_plus", "comprehensive_newborn_care", "imnci", "type_1_diabetes", "essential_newborn_care"];
    const labels = { etat_plus: "ETAT+", comprehensive_newborn_care: "Comp. NB", imnci: "IMNCI", type_1_diabetes: "T1 DM", essential_newborn_care: "ENC" };

    return (
        <div style={{ overflowX: "auto" }}>
            <table style={{ width: "100%", borderCollapse: "collapse", fontSize: 11 }}>
                <thead>
                    <tr style={{ background: T.borderLight }}>
                        <th style={{ padding: "8px 10px", textAlign: "left", color: T.textMuted, fontWeight: 700, whiteSpace: "nowrap" }}>Cadre</th>
                        {cols.map(c => <th key={c} style={{ padding: "8px 6px", textAlign: "center", color: T.textMuted, fontWeight: 700, whiteSpace: "nowrap" }}>{labels[c]}</th>)}
                    </tr>
                </thead>
                <tbody>
                    {data.map((row, i) => (
                        <tr key={i} style={{ borderBottom: `1px solid ${T.borderLight}` }}>
                            <td style={{ padding: "8px 10px", color: T.textMid, fontWeight: 600, fontSize: 11 }}>{row.cadre_name ?? "—"}</td>
                            {cols.map(c => (
                                <td key={c} style={{ padding: "8px 6px", textAlign: "center", color: (row[c] ?? 0) > 0 ? "#065F46" : T.textMuted, fontWeight: (row[c] ?? 0) > 0 ? 700 : 400 }}>
                                    {row[c] ?? 0}
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

// ── HP Section ────────────────────────────────────────────────────────────────
function HpSection({ assessment }) {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        api.healthProducts.get(assessment.id)
            .then(r => setData(r?.data ?? []))
            .catch(() => setData([]))
            .finally(() => setLoading(false));
    }, [assessment.id]);

    if (loading) return <div style={{ padding: "12px 16px", fontSize: 12, color: T.textMuted }}>Loading commodity data…</div>;
    if (!data || data.length === 0) return <div style={{ padding: "12px 16px", fontSize: 12, color: T.textMuted }}>No commodity data.</div>;

    return (
        <>
            {data.map(dept => {
                const allCommodities = dept.categories?.flatMap(c => c.commodities) ?? [];
                const answered = allCommodities.filter(c => c.available !== null).length;
                const available = allCommodities.filter(c => c.available === true).length;
                const pct = answered > 0 ? Math.round((available / answered) * 100) : 0;
                const grade = calcGrade(pct);
                return (
                    <div key={dept.department_id} style={{ marginBottom: 12 }}>
                        <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", marginBottom: 6 }}>
                            <div style={{ fontSize: 12, fontWeight: 700, color: T.textMid }}>{dept.department_name}</div>
                            <GradeBadge grade={grade} pct={pct} />
                        </div>
                        <ProgressBar pct={pct} color={GRADE_COLOR[grade]} height={4} />
                        <div style={{ fontSize: 10, color: T.textMuted, marginTop: 3 }}>{available}/{answered} available</div>
                        {dept.categories?.map(cat => {
                            const catItems = cat.commodities ?? [];
                            const catAvail = catItems.filter(c => c.available === true).length;
                            const catAns = catItems.filter(c => c.available !== null).length;
                            return (
                                <div key={cat.category_id} style={{ marginTop: 8, paddingLeft: 10, borderLeft: "2px solid " + T.border }}>
                                    <div style={{ fontSize: 11, fontWeight: 700, color: T.textMuted, marginBottom: 4 }}>
                                        {cat.category_name} — {catAvail}/{catAns}
                                    </div>
                                    <div style={{ display: "flex", flexWrap: "wrap", gap: 4 }}>
                                        {catItems.map(c => (
                                            <div key={c.commodity_id} style={{
                                                padding: "2px 8px", borderRadius: 6, fontSize: 10, fontWeight: 600,
                                                background: c.available === true ? "#D1FAE5" : c.available === false ? "#FEE2E2" : T.borderLight,
                                                color: c.available === true ? "#065F46" : c.available === false ? "#991B1B" : T.textMuted,
                                            }}>
                                                {c.available === true ? "✓" : c.available === false ? "✗" : "?"} {c.name}
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                );
            })}
        </>
    );
}

// ── Special section wrapper card ──────────────────────────────────────────────
function SpecialSectionCard({ title, icon, children, sectionScore }) {
    const [open, setOpen] = useState(false);
    const grade = sectionScore?.grade;
    const pct = sectionScore?.percentage;
    const color = GRADE_COLOR[grade] ?? "#9CA3AF";

    return (
        <div style={{ background: T.card, borderRadius: T.radius, marginBottom: 12, overflow: "hidden", boxShadow: T.shadow, border: `1px solid ${grade ? color + "33" : T.border}` }}>
            <button onClick={() => setOpen(o => !o)} style={{
                width: "100%", padding: "14px 16px", border: "none", background: "none",
                cursor: "pointer", display: "flex", alignItems: "center", gap: 12, textAlign: "left",
            }}>
                <div style={{ width: 40, height: 40, borderRadius: 12, background: `${color}18`, display: "flex", alignItems: "center", justifyContent: "center", fontSize: 20, flexShrink: 0 }}>
                    {icon}
                </div>
                <div style={{ flex: 1, minWidth: 0 }}>
                    <div style={{ fontWeight: 700, fontSize: 13, color: T.text }}>{title}</div>
                    {pct != null
                        ? <div style={{ fontSize: 11, color: T.textMuted, marginTop: 2 }}>{Math.round(pct)}% availability score</div>
                        : <div style={{ fontSize: 11, color: T.textMuted, marginTop: 2 }}>Structured data</div>
                    }
                    {pct != null && (
                        <div style={{ height: 3, background: T.borderLight, borderRadius: 999, overflow: "hidden", marginTop: 5 }}>
                            <div style={{ height: "100%", width: `${pct}%`, background: color, borderRadius: 999 }} />
                        </div>
                    )}
                </div>
                <div style={{ display: "flex", flexDirection: "column", alignItems: "flex-end", gap: 4 }}>
                    {grade && <GradeBadge grade={grade} pct={Math.round(pct ?? 0)} />}
                    <span style={{ color: T.textMuted, fontSize: 14, transform: open ? "rotate(180deg)" : "none", transition: "transform 0.2s" }}>▾</span>
                </div>
            </button>
            {open && (
                <div style={{ borderTop: `1px solid ${T.border}`, padding: "12px 16px" }}>
                    {children}
                </div>
            )}
        </div>
    );
}

// ── Main screen ───────────────────────────────────────────────────────────────
export function AssessmentReportScreen({ assessment, onBack }) {
    const [report, setReport] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [downloading, setDownloading] = useState(false);
    const [shareMsg, setShareMsg] = useState(null);

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
            if (url) {
                window.open(url, "_blank");
                setShareMsg({ type: "success", text: "PDF opened in new tab." });
            } else {
                setShareMsg({ type: "error", text: "PDF URL not available." });
            }
        } catch (e) {
            setShareMsg({ type: "error", text: e.message || "PDF generation failed." });
        } finally {
            setDownloading(false);
            setTimeout(() => setShareMsg(null), 4000);
        }
    };

    const handleShare = async () => {
        const text = `MNCH Assessment Report\n${assessment.facility_name}\n${assessment.assessment_date}\nScore: ${Math.round(assessment.overall_percentage ?? 0)}% (${assessment.overall_grade?.toUpperCase()})\n${window.location.href}`;
        if (navigator.share) {
            try { await navigator.share({ title: "MNCH Assessment Report", text }); }
            catch { }
        } else if (navigator.clipboard) {
            await navigator.clipboard.writeText(text);
            setShareMsg({ type: "success", text: "Summary copied to clipboard." });
            setTimeout(() => setShareMsg(null), 3000);
        }
    };

    const grade = assessment.overall_grade ?? (report?.assessment?.overall_grade);
    const pct = assessment.overall_percentage ?? report?.assessment?.overall_percentage ?? 0;
    const sectionScores = assessment.section_scores ?? {};
    const sectionReports = report?.section_reports ?? [];

    return (
        <div style={{ display: "flex", flexDirection: "column", height: "100%" }}>
            {/* Header */}
            <div style={{ background: "linear-gradient(135deg, #1E3A5F 0%, #0F172A 100%)", padding: "20px 20px 24px" }}>
                <BackButton onBack={onBack} light />
                <div style={{ marginTop: 12, display: "flex", alignItems: "flex-start", gap: 14 }}>
                    <GradeRing pct={Math.round(pct)} grade={grade} size={72} />
                    <div style={{ flex: 1 }}>
                        <div style={{ color: "white", fontSize: 16, fontWeight: 800 }}>Assessment Report</div>
                        <div style={{ color: "rgba(255,255,255,0.7)", fontSize: 13, marginTop: 3 }}>{assessment.facility_name}</div>
                        <div style={{ color: "rgba(255,255,255,0.5)", fontSize: 12, marginTop: 2 }}>
                            {assessment.assessment_type} · {assessment.assessment_date}
                        </div>
                        {assessment.assessor_name && (
                            <div style={{ color: "rgba(255,255,255,0.4)", fontSize: 11, marginTop: 2 }}>
                                Assessor: {assessment.assessor_name}
                            </div>
                        )}
                    </div>
                </div>

                {/* Action buttons */}
                <div style={{ display: "flex", gap: 8, marginTop: 14 }}>
                    <button onClick={handleDownload} disabled={downloading} style={{
                        flex: 1, padding: "9px 12px", borderRadius: 12, border: "1.5px solid rgba(255,255,255,0.25)",
                        background: "rgba(255,255,255,0.1)", color: "white", fontSize: 12, fontWeight: 700,
                        cursor: downloading ? "default" : "pointer", display: "flex", alignItems: "center", justifyContent: "center", gap: 6,
                    }}>
                        {downloading ? "⏳" : "📥"} {downloading ? "Generating…" : "Download PDF"}
                    </button>
                    <button onClick={handleShare} style={{
                        flex: 1, padding: "9px 12px", borderRadius: 12, border: "1.5px solid rgba(255,255,255,0.25)",
                        background: "rgba(255,255,255,0.1)", color: "white", fontSize: 12, fontWeight: 700,
                        cursor: "pointer", display: "flex", alignItems: "center", justifyContent: "center", gap: 6,
                    }}>
                        📤 Share
                    </button>
                </div>

                {shareMsg && (
                    <div style={{ marginTop: 8, padding: "6px 12px", borderRadius: 8, fontSize: 11, fontWeight: 600, background: shareMsg.type === "success" ? "rgba(16,185,129,0.2)" : "rgba(239,68,68,0.2)", color: shareMsg.type === "success" ? "#6EE7B7" : "#FCA5A5" }}>
                        {shareMsg.text}
                    </div>
                )}
            </div>

            {/* Body */}
            <div style={{ flex: 1, overflowY: "auto", padding: "14px 16px 100px", background: T.bg }}>
                {loading && (
                    <div style={{ textAlign: "center", padding: "40px 16px", color: T.textMuted }}>
                        <div style={{ fontSize: 32, marginBottom: 8 }}>⏳</div>
                        <div>Loading full report…</div>
                    </div>
                )}
                {error && (
                    <div style={{ padding: "12px 14px", background: "#FEE2E2", borderRadius: 10, fontSize: 12, color: "#991B1B", marginBottom: 14 }}>
                        ⚠️ {error} — showing summary only.
                    </div>
                )}

                {/* Section cards — from full report */}
                {sectionReports.length > 0 && sectionReports.map(s => {
                    if (s.code === "human_resources") return (
                        <SpecialSectionCard key={s.code} title={s.name} icon="👥" sectionScore={sectionScores[s.code]}>
                            <HrSection assessment={assessment} />
                        </SpecialSectionCard>
                    );
                    if (s.code === "health_products") return (
                        <SpecialSectionCard key={s.code} title={s.name} icon="💊" sectionScore={sectionScores[s.code]}>
                            <HpSection assessment={assessment} />
                        </SpecialSectionCard>
                    );
                    return <SectionReportCard key={s.code} section={{ ...s, ...(sectionScores[s.code] ?? {}) }} />;
                })}

                {/* Fallback — no full report yet but have section_scores */}
                {!loading && sectionReports.length === 0 && Object.entries(sectionScores).length > 0 && (
                    Object.entries(sectionScores).map(([code, sc]) => (
                        <SectionReportCard key={code} section={{ code, name: sc.name ?? code, ...sc, responses: [] }} />
                    ))
                )}

                {/* Recommendations */}
                {!loading && sectionReports.length > 0 && (
                    <RecommendationsCard sectionReports={sectionReports} />
                )}

                {/* Not completed yet notice */}
                {assessment.status !== "completed" && (
                    <div style={{ padding: "14px 16px", background: "#FEF3C7", borderRadius: T.radius, border: "1px solid #FCD34D", fontSize: 13, color: "#92400E", textAlign: "center" }}>
                        ⚡ The full report will be available once you submit the assessment.
                    </div>
                )}
            </div>
        </div>
    );
}

// ── Recommendations card ──────────────────────────────────────────────────────
function RecommendationsCard({ sectionReports }) {
    const issues = [];
    sectionReports.forEach(s => {
        (s.responses ?? []).forEach(r => {
            if (r.response === "No") issues.push({ type: "critical", section: s.name, text: r.question_text });
            if (r.response === "Partial") issues.push({ type: "warning", section: s.name, text: r.question_text });
        });
    });
    if (issues.length === 0) return (
        <div style={{ padding: 16, background: "#D1FAE5", borderRadius: T.radius, border: "1px solid #6EE7B7", textAlign: "center" }}>
            <div style={{ fontSize: 28, marginBottom: 6 }}>🎉</div>
            <div style={{ fontSize: 14, fontWeight: 700, color: "#064E3B" }}>Excellent Performance</div>
            <div style={{ fontSize: 12, color: "#065F46", marginTop: 4 }}>No critical issues identified.</div>
        </div>
    );

    const critical = issues.filter(i => i.type === "critical").slice(0, 6);
    const warnings = issues.filter(i => i.type === "warning").slice(0, 4);

    return (
        <div style={{ background: T.card, borderRadius: T.radius, overflow: "hidden", boxShadow: T.shadow, marginTop: 4 }}>
            <div style={{ padding: "12px 16px 10px", background: "linear-gradient(135deg, #FFF1F2, #FEF2F2)", borderBottom: `1px solid #FEE2E2` }}>
                <div style={{ fontSize: 13, fontWeight: 800, color: "#991B1B" }}>⚠️ Improvement Areas</div>
                <div style={{ fontSize: 11, color: "#B91C1C", marginTop: 2 }}>{critical.length} critical · {warnings.length} partial</div>
            </div>
            <div style={{ padding: "10px 16px 14px" }}>
                {[...critical, ...warnings].map((item, i) => (
                    <div key={i} style={{
                        display: "flex", gap: 8, padding: "8px 10px", borderRadius: 10, marginBottom: i < critical.length + warnings.length - 1 ? 6 : 0,
                        background: item.type === "critical" ? "#FEF2F2" : "#FFFBEB",
                        border: `1px solid ${item.type === "critical" ? "#FEE2E2" : "#FDE68A"}`,
                    }}>
                        <div style={{ flex: 1 }}>
                            <div style={{ fontSize: 10, fontWeight: 700, color: item.type === "critical" ? "#EF4444" : "#F59E0B", textTransform: "uppercase", marginBottom: 2 }}>{item.section}</div>
                            <div style={{ fontSize: 11, lineHeight: 1.4, color: item.type === "critical" ? "#7F1D1D" : "#78350F" }}>
                                {item.text.length > 90 ? item.text.slice(0, 90) + "…" : item.text}
                            </div>
                        </div>
                        <div style={{ flexShrink: 0, padding: "2px 7px", borderRadius: 5, fontSize: 10, fontWeight: 700, alignSelf: "flex-start", background: item.type === "critical" ? "#FEE2E2" : "#FEF3C7", color: item.type === "critical" ? "#991B1B" : "#92400E" }}>
                            {item.type === "critical" ? "No" : "Partial"}
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}
