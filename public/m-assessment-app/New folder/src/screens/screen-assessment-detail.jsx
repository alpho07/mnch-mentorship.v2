import { useState, useEffect } from "react";
import { T, GRADE_COLOR, GRADE_BG, calcGrade } from "../constants.js";
import { BackButton, StatusChip, ProgressBar } from "../components/shared-components.jsx";
import api from "../services/api.service.js";

// ─────────────────────────────────────────────────────────────────────────────
// Special sections that don't have dynamic question responses
const SPECIAL_SECTIONS = ["human_resources", "health_products"];

// ── Helpers ───────────────────────────────────────────────────────────────────
function SectionStatusChip({ done, isSpecial }) {
    if (done) return (
        <div style={{ padding: "3px 10px", borderRadius: 8, fontSize: 11, fontWeight: 700, background: "#D1FAE5", color: "#065F46" }}>✓ Done</div>
    );
    if (isSpecial) return (
        <div style={{ padding: "3px 10px", borderRadius: 8, fontSize: 11, fontWeight: 700, background: T.borderLight, color: T.textMuted }}>Not started</div>
    );
    return null;
}

// ── Tab: Overview ─────────────────────────────────────────────────────────────
function OverviewTab({ assessment, sections }) {
    const sectionScores = assessment.section_scores ?? {};
    const sectionProgress = assessment.section_progress ?? {};

    return (
        <>
            {/* Overall score card — completed only */}
            {assessment.status === "completed" && (
                <div style={{
                    background: assessment.overall_grade
                        ? `linear-gradient(135deg, ${GRADE_BG[assessment.overall_grade]}, white)`
                        : T.borderLight,
                    borderRadius: T.radius, padding: 16, marginBottom: 14, boxShadow: T.shadow,
                }}>
                    <div style={{ fontSize: 12, fontWeight: 700, color: T.textMuted, textTransform: "uppercase", letterSpacing: 0.8, marginBottom: 8 }}>
                        Overall Score
                    </div>
                    <div style={{ display: "flex", alignItems: "center", gap: 16, marginBottom: 10 }}>
                        <div style={{ fontSize: 48, fontWeight: 900, lineHeight: 1, color: assessment.overall_grade ? GRADE_COLOR[assessment.overall_grade] : T.textMid }}>
                            {assessment.overall_percentage != null ? Math.round(assessment.overall_percentage) : "—"}
                            {assessment.overall_percentage != null && <span style={{ fontSize: 22 }}>%</span>}
                        </div>
                        {assessment.overall_grade && <GradeBadge grade={assessment.overall_grade} />}
                    </div>
                    <ProgressBar pct={assessment.overall_percentage ?? 0} color={GRADE_COLOR[assessment.overall_grade] ?? "#6B7280"} height={6} />
                </div>
            )}

            {/* In-progress completion hint */}
            {assessment.status === "in_progress" && (
                <div style={{ padding: "12px 14px", background: "#FEF3C7", borderRadius: T.radiusSm, marginBottom: 14, border: "1px solid #FCD34D", fontSize: 12, color: "#92400E" }}>
                    ⚡ Assessment in progress — complete all sections to submit.
                </div>
            )}

            {/* Section breakdown */}
            {sections.length > 0 && (
                <div style={{ background: T.card, borderRadius: T.radius, padding: 16, boxShadow: T.shadow }}>
                    <div style={{ fontSize: 12, fontWeight: 700, color: T.textMuted, textTransform: "uppercase", letterSpacing: 0.8, marginBottom: 14 }}>
                        Section Breakdown
                    </div>
                    {sections.map((s, i) => {
                        const sc = sectionScores[s.code];          // from AssessmentResource
                        const done = sectionProgress[s.code] === true; // HR & HP use this
                        const isSpecial = SPECIAL_SECTIONS.includes(s.code);
                        const [g1] = s.gradient ?? [s.color ?? "#6B7280"];
                        const hasSc = sc && (sc.percentage != null);

                        return (
                            <div key={s.id ?? s.code} style={{ marginBottom: i < sections.length - 1 ? 14 : 0, paddingBottom: i < sections.length - 1 ? 14 : 0, borderBottom: i < sections.length - 1 ? `1px solid ${T.borderLight}` : "none" }}>
                                {/* Row */}
                                <div style={{ display: "flex", alignItems: "center", gap: 10, marginBottom: hasSc ? 6 : 0 }}>
                                    <div style={{ width: 36, height: 36, borderRadius: 10, background: `${g1}18`, display: "flex", alignItems: "center", justifyContent: "center", fontSize: 18, flexShrink: 0 }}>
                                        {s.icon ?? "📋"}
                                    </div>
                                    <div style={{ flex: 1, minWidth: 0 }}>
                                        <div style={{ fontWeight: 700, fontSize: 13, color: T.text }}>{s.name}</div>
                                        {hasSc && (
                                            <div style={{ fontSize: 11, color: T.textMuted, marginTop: 1 }}>
                                                {sc.answered_questions} answered · {sc.total_questions} questions
                                            </div>
                                        )}
                                        {!hasSc && isSpecial && done && (
                                            <div style={{ fontSize: 11, color: "#059669", marginTop: 1 }}>Responses recorded</div>
                                        )}
                                        {!hasSc && isSpecial && !done && (
                                            <div style={{ fontSize: 11, color: T.textMuted, marginTop: 1 }}>Not yet completed</div>
                                        )}
                                        {!hasSc && !isSpecial && (
                                            <div style={{ fontSize: 11, color: T.textMuted, marginTop: 1 }}>
                                                {assessment.status === "in_progress" ? "Pending" : "No data"}
                                            </div>
                                        )}
                                    </div>
                                    {hasSc
                                        ? <GradeBadge grade={sc.grade} pct={Math.round(sc.percentage)} />
                                        : <SectionStatusChip done={done} isSpecial={isSpecial} />
                                    }
                                </div>
                                {/* Progress bar — only when we have a score */}
                                {hasSc && (
                                    <ProgressBar pct={sc.percentage ?? 0} color={GRADE_COLOR[sc.grade] ?? g1} height={4} />
                                )}
                                {/* HR/HP done bar */}
                                {!hasSc && isSpecial && done && (
                                    <ProgressBar pct={100} color="#10B981" height={4} />
                                )}
                            </div>
                        );
                    })}
                </div>
            )}
        </>
    );
}

// ── Tab: Responses ────────────────────────────────────────────────────────────
function ResponsesTab({ assessment, sections }) {
    const [expanded, setExpanded] = useState(sections[0]?.code ?? null);
    const [responses, setResponses] = useState(null);
    const [hrData, setHrData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {
        Promise.all([
            api.responses.list(assessment.id),
            api.humanResources.get(assessment.id).catch(() => null),
        ])
            .then(([respData, hrResp]) => {
                setResponses(respData?.responses ?? {});
                setHrData(hrResp?.data ?? null);
            })
            .catch(e => setError(e.message || "Failed to load responses"))
            .finally(() => setLoading(false));
    }, [assessment.id]);

    if (loading) return (
        <div style={{ textAlign: "center", padding: "40px 16px", color: T.textMuted }}>
            <div style={{ fontSize: 32, marginBottom: 8 }}>⏳</div>
            <div style={{ fontSize: 13 }}>Loading responses…</div>
        </div>
    );
    if (error) return (
        <div style={{ padding: "12px 14px", background: "#FEE2E2", borderRadius: 10, fontSize: 12, color: "#991B1B" }}>⚠️ {error}</div>
    );

    const r = responses ?? {};

    return (
        <>
            {sections.map(s => {
                const isHr = s.code === "human_resources";
                const isHp = s.code === "health_products";
                const isSpecial = isHr || isHp;
                const questions = isSpecial ? [] : (s.questions || []);
                const answered = questions.filter(q => r[q.question_code] != null && r[q.question_code] !== "").length;
                const open = expanded === s.code;

                return (
                    <div key={s.id ?? s.code} style={{ background: T.card, borderRadius: T.radius, marginBottom: 10, overflow: "hidden", boxShadow: T.shadow }}>
                        <button onClick={() => setExpanded(open ? null : s.code)} style={{
                            width: "100%", padding: "12px 16px", border: "none", background: "none",
                            cursor: "pointer", display: "flex", alignItems: "center", gap: 10, textAlign: "left",
                        }}>
                            <span style={{ fontSize: 20 }}>{s.icon ?? "📋"}</span>
                            <div style={{ flex: 1 }}>
                                <div style={{ fontWeight: 700, fontSize: 13, color: T.text }}>{s.name}</div>
                                <div style={{ fontSize: 11, color: T.textMuted, marginTop: 1 }}>
                                    {isSpecial ? "Structured data" : `${answered} / ${questions.length} answered`}
                                </div>
                            </div>
                            <span style={{ color: T.textMuted, fontSize: 14, transform: open ? "rotate(180deg)" : "none", transition: "transform 0.2s" }}>▾</span>
                        </button>

                        {open && (
                            <div style={{ borderTop: `1px solid ${T.border}` }}>
                                {/* Human Resources structured table */}
                                {isHr && (
                                    <HrResponsesView data={hrData} />
                                )}

                                {/* Health Products — link to dedicated view */}
                                {isHp && (
                                    <div style={{ padding: "12px 16px", fontSize: 12, color: T.textMuted }}>
                                        Commodity availability data is shown in the Report tab.
                                    </div>
                                )}

                                {/* Standard dynamic questions */}
                                {!isSpecial && questions.length === 0 && (
                                    <div style={{ padding: "12px 16px", fontSize: 12, color: T.textMuted }}>No questions.</div>
                                )}
                                {!isSpecial && questions.map((q, i) => {
                                    const val = r[q.question_code];
                                    const hasAnswer = val != null && val !== "";
                                    const chips = { Yes: { bg: "#D1FAE5", color: "#065F46" }, No: { bg: "#FEE2E2", color: "#991B1B" }, Partial: { bg: "#FEF3C7", color: "#92400E" } };
                                    const chip = chips[val] ?? { bg: T.borderLight, color: T.textMuted };
                                    const expl = responses?.__explanations?.[q.question_code];
                                    return (
                                        <div key={q.id ?? q.question_code} style={{
                                            padding: "10px 16px",
                                            borderBottom: i < questions.length - 1 ? `1px solid ${T.borderLight}` : "none",
                                        }}>
                                            <div style={{ display: "flex", alignItems: "flex-start", gap: 10 }}>
                                                <div style={{ flex: 1, fontSize: 12, color: T.textMid, lineHeight: 1.4 }}>{q.question_text}</div>
                                                <div style={{ padding: "3px 10px", borderRadius: 8, fontSize: 11, fontWeight: 700, flexShrink: 0, background: chip.bg, color: chip.color }}>
                                                    {hasAnswer ? String(val) : "—"}
                                                </div>
                                            </div>
                                            {expl && <div style={{ fontSize: 11, color: T.textSub, marginTop: 4, paddingLeft: 0, fontStyle: "italic" }}>💬 {expl}</div>}
                                        </div>
                                    );
                                })}
                            </div>
                        )}
                    </div>
                );
            })}
        </>
    );
}

function HrResponsesView({ data }) {
    if (!data || !Array.isArray(data) || data.length === 0) {
        return <div style={{ padding: "12px 16px", fontSize: 12, color: T.textMuted }}>No HR data recorded.</div>;
    }
    const cols = ["etat_plus", "comprehensive_newborn_care", "imnci", "type_1_diabetes", "essential_newborn_care"];
    const labels = { etat_plus: "ETAT+", comprehensive_newborn_care: "Comp. NB Care", imnci: "IMNCI", type_1_diabetes: "T1 Diabetes", essential_newborn_care: "Essential NB" };
    return (
        <div style={{ overflowX: "auto" }}>
            <table style={{ width: "100%", borderCollapse: "collapse", fontSize: 11 }}>
                <thead>
                    <tr style={{ background: T.borderLight }}>
                        <th style={{ padding: "8px 12px", textAlign: "left", color: T.textMuted, fontWeight: 700 }}>Cadre</th>
                        {cols.map(c => <th key={c} style={{ padding: "8px 8px", textAlign: "center", color: T.textMuted, fontWeight: 700, whiteSpace: "nowrap" }}>{labels[c]}</th>)}
                    </tr>
                </thead>
                <tbody>
                    {data.map((row, i) => (
                        <tr key={i} style={{ borderBottom: `1px solid ${T.borderLight}` }}>
                            <td style={{ padding: "8px 12px", color: T.textMid, fontWeight: 600 }}>{row.cadre_name ?? row.cadre ?? "—"}</td>
                            {cols.map(c => (
                                <td key={c} style={{ padding: "8px 8px", textAlign: "center", color: (row[c] ?? 0) > 0 ? "#065F46" : T.textMuted, fontWeight: (row[c] ?? 0) > 0 ? 700 : 400 }}>
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

// ── Main screen ───────────────────────────────────────────────────────────────
export function AssessmentDetailScreen({ assessment, sections, onBack, onContinue, onViewReport }) {
    const [tab, setTab] = useState("overview");
    const tabs = assessment.status === "completed"
        ? ["overview", "responses", "report"]
        : ["overview", "responses"];

    return (
        <div style={{ display: "flex", flexDirection: "column", height: "100%" }}>
            {/* Header */}
            <div style={{ background: "linear-gradient(135deg, #1E3A5F 0%, #1D4ED8 100%)", padding: "20px 20px 24px" }}>
                <BackButton onBack={onBack} light />
                <div style={{ marginTop: 12, color: "white", fontSize: 18, fontWeight: 800 }}>
                    {assessment.facility_name || "Assessment"}
                </div>
                <div style={{ color: "rgba(255,255,255,0.6)", fontSize: 13, marginTop: 2 }}>
                    {[assessment.assessment_type, assessment.assessment_date].filter(Boolean).join(" · ")}
                </div>
                <div style={{ marginTop: 10 }}>
                    {assessment.overall_grade
                        ? <GradeBadge grade={assessment.overall_grade} pct={Math.round(assessment.overall_percentage ?? 0)} />
                        : <StatusChip status={assessment.status} />
                    }
                </div>
            </div>

            {/* Tabs */}
            <div style={{ display: "flex", background: T.card, borderBottom: `1px solid ${T.border}` }}>
                {tabs.map(t => (
                    <button key={t} onClick={() => setTab(t)} style={{
                        flex: 1, padding: "12px 0", border: "none",
                        background: tab === t ? T.card : T.borderLight,
                        borderBottom: tab === t ? "2px solid #1D4ED8" : "2px solid transparent",
                        color: tab === t ? "#1D4ED8" : T.textMuted,
                        fontSize: 13, fontWeight: tab === t ? 700 : 500,
                        cursor: "pointer", textTransform: "capitalize",
                    }}>
                        {t === "report" ? "📄 Report" : t}
                    </button>
                ))}
            </div>

            {/* Content */}
            <div style={{ flex: 1, overflowY: "auto", padding: "14px 16px 100px", background: T.bg }}>
                {tab === "overview" && <OverviewTab assessment={assessment} sections={sections ?? []} />}
                {tab === "responses" && <ResponsesTab assessment={assessment} sections={sections ?? []} />}
                {tab === "report" && <ReportPreviewTab assessment={assessment} onViewFull={onViewReport} />}
            </div>

            {/* Footer CTA */}
            {assessment.status === "in_progress" && onContinue && (
                <div style={{ padding: "12px 16px", background: T.card, borderTop: `1px solid ${T.border}` }}>
                    <button onClick={() => onContinue(assessment)} style={{
                        width: "100%", padding: 15, borderRadius: T.radius, border: "none",
                        background: "linear-gradient(135deg, #1E3A5F, #1D4ED8)",
                        color: "white", fontSize: 15, fontWeight: 700, cursor: "pointer",
                    }}>
                        Continue Assessment →
                    </button>
                </div>
            )}
            {assessment.status === "completed" && tab === "report" && onViewReport && (
                <div style={{ padding: "12px 16px", background: T.card, borderTop: `1px solid ${T.border}`, display: "flex", gap: 10 }}>
                    <button onClick={onViewReport} style={{
                        flex: 1, padding: 13, borderRadius: T.radius, border: "none",
                        background: "linear-gradient(135deg, #1E3A5F, #1D4ED8)",
                        color: "white", fontSize: 14, fontWeight: 700, cursor: "pointer",
                    }}>
                        📄 View Full Report
                    </button>
                </div>
            )}
        </div>
    );
}

// ── Report preview tab (teaser + CTA) ─────────────────────────────────────────
function ReportPreviewTab({ assessment, onViewFull }) {
    const sectionScores = assessment.section_scores ?? {};
    const sections = Object.entries(sectionScores);
    const grade = assessment.overall_grade;
    const pct = assessment.overall_percentage ?? 0;

    return (
        <div>
            {/* Summary card */}
            <div style={{ background: grade ? `linear-gradient(135deg, ${GRADE_BG[grade]}, white)` : T.card, borderRadius: T.radius, padding: 18, marginBottom: 14, boxShadow: T.shadow, border: `1px solid ${grade ? GRADE_COLOR[grade] + "44" : T.border}` }}>
                <div style={{ fontSize: 11, fontWeight: 700, color: T.textMuted, textTransform: "uppercase", letterSpacing: 0.8, marginBottom: 8 }}>Assessment Summary</div>
                <div style={{ display: "flex", alignItems: "center", gap: 14, marginBottom: 12 }}>
                    <div style={{ fontSize: 44, fontWeight: 900, lineHeight: 1, color: grade ? GRADE_COLOR[grade] : T.textMid }}>
                        {Math.round(pct)}<span style={{ fontSize: 20 }}>%</span>
                    </div>
                    <div>
                        {grade && <GradeBadge grade={grade} />}
                        <div style={{ fontSize: 12, color: T.textSub, marginTop: 4 }}>{assessment.facility_name}</div>
                        <div style={{ fontSize: 11, color: T.textMuted }}>{assessment.assessment_date} · {assessment.assessment_type}</div>
                    </div>
                </div>
                <ProgressBar pct={pct} color={GRADE_COLOR[grade] ?? "#6B7280"} height={6} />
            </div>

            {/* Section score sparklines */}
            {sections.length > 0 && (
                <div style={{ background: T.card, borderRadius: T.radius, padding: "14px 16px", marginBottom: 14, boxShadow: T.shadow }}>
                    <div style={{ fontSize: 11, fontWeight: 700, color: T.textMuted, textTransform: "uppercase", letterSpacing: 0.8, marginBottom: 12 }}>Section Scores</div>
                    {sections.map(([code, sc], i) => (
                        <div key={code} style={{ marginBottom: i < sections.length - 1 ? 10 : 0 }}>
                            <div style={{ display: "flex", justifyContent: "space-between", marginBottom: 4 }}>
                                <span style={{ fontSize: 12, color: T.textMid, fontWeight: 600 }}>{sc.name ?? code}</span>
                                <span style={{ fontSize: 12, fontWeight: 700, color: GRADE_COLOR[sc.grade] ?? T.textMuted }}>{Math.round(sc.percentage ?? 0)}%</span>
                            </div>
                            <ProgressBar pct={sc.percentage ?? 0} color={GRADE_COLOR[sc.grade] ?? "#6B7280"} height={5} />
                        </div>
                    ))}
                </div>
            )}

            {/* Full report CTA */}
            <div style={{ background: T.card, borderRadius: T.radius, padding: 16, boxShadow: T.shadow, textAlign: "center" }}>
                <div style={{ fontSize: 28, marginBottom: 8 }}>📋</div>
                <div style={{ fontSize: 14, fontWeight: 700, color: T.text, marginBottom: 4 }}>Full Assessment Report</div>
                <div style={{ fontSize: 12, color: T.textSub, marginBottom: 16 }}>
                    View detailed findings, responses per section, and download a PDF copy.
                </div>
                {onViewFull ? (
                    <button onClick={onViewFull} style={{
                        width: "100%", padding: "12px", borderRadius: T.radiusSm, border: "none",
                        background: "linear-gradient(135deg, #1E3A5F, #1D4ED8)",
                        color: "white", fontSize: 14, fontWeight: 700, cursor: "pointer",
                    }}>
                        Open Full Report →
                    </button>
                ) : (
                    <div style={{ fontSize: 12, color: T.textMuted }}>Report available after completion.</div>
                )}
            </div>
        </div>
    );
}
