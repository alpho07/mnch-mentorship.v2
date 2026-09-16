import { useState, useEffect, useRef } from "react";
import { T, GRADE_COLOR, GRADE_BG, calcGrade } from "../constants.js";
import { BackButton, GradeBadge, StatusChip, ProgressBar } from "../components/shared-components.jsx";
import { SectionIcon } from "../components/section-icons.jsx";
import api from "../services/api.service.js";
import offlineStore from "../services/offline-store.js";

const HR_FIELDS = ["etat_plus", "comprehensive_newborn_care", "imnci", "type_1_diabetes", "essential_newborn_care"];

// ─────────────────────────────────────────────────────────────────────────────
// Special sections that don't have dynamic question responses
const SPECIAL_SECTIONS = ["human_resources", "health_products"];

// ── Helpers ───────────────────────────────────────────────────────────────────
function SectionStatusChip({ done, inProgress, isSpecial }) {
    if (done) return (
        <div style={{ padding: "4px 12px", borderRadius: 10, fontSize: 11, fontWeight: 700, background: "#D1FAE5", color: "#065F46", border: "1px solid #6EE7B733" }}>✓ Done</div>
    );
    if (inProgress) return (
        <div style={{ padding: "4px 12px", borderRadius: 10, fontSize: 11, fontWeight: 700, background: "#FEF3C7", color: "#92400E", border: "1px solid #FDE68A" }}>In Progress</div>
    );
    if (isSpecial) return (
        <div style={{ padding: "4px 12px", borderRadius: 10, fontSize: 11, fontWeight: 700, background: T.borderLight, color: T.textMuted, border: `1px solid ${T.border}` }}>Not started</div>
    );
    return null;
}

// ── Scoring: overall = sum of 4 section percentages / 4 (matches server Blade) ──
const SCORED_SECTIONS = ["infrastructure", "skills_lab", "information_systems", "quality_of_care"];
function calcOverallFromSections(sectionScores) {
    const vals = SCORED_SECTIONS.map(c => {
        const raw = sectionScores[c]?.percentage;
        if (raw == null) return null;
        const n = Number(raw);
        return isNaN(n) ? null : n;
    }).filter(v => v !== null);
    if (vals.length === 0) return null;
    return vals.reduce((a, b) => a + b, 0) / 4;
}

// ── Tab: Overview ─────────────────────────────────────────────────────────────
function OverviewTab({ assessment, sections }) {
    const sectionScores = assessment.section_scores ?? {};
    const sectionProgress = assessment.section_progress ?? {};
    const [specialProgress, setSpecialProgress] = useState({ hr: null, hp: null });

    useEffect(() => {
        if (!assessment.id) return;
        offlineStore.getHR(assessment.id).then(hr => {
            const structure = hr?.structure ?? [];
            const answered = structure.filter(c => HR_FIELDS.some(f => (c[f] ?? 0) > 0)).length;
            setSpecialProgress(p => ({ ...p, hr: { answered, total: structure.length } }));
        }).catch(() => {});
        offlineStore.getHP(assessment.id).then(hp => {
            const structure = hp?.structure ?? [];
            let total = 0, answered = 0;
            structure.forEach(dept => {
                (dept.categories ?? []).forEach(cat => {
                    (cat.commodities ?? []).forEach(c => {
                        total++;
                        if (c.available !== null && c.available !== undefined) answered++;
                    });
                });
            });
            setSpecialProgress(p => ({ ...p, hp: { answered, total } }));
        }).catch(() => {});
    }, [assessment.id]);

    // Always use client-side formula (sum of 4 sections ÷ 4); server value is fallback only
    const overallPct = calcOverallFromSections(sectionScores) ?? assessment.overall_percentage;
    const overallGrade = overallPct != null ? calcGrade(overallPct) : assessment.overall_grade;

    return (
        <>
            {/* Overall score card — completed only */}
            {assessment.status === "completed" && (
                <div style={{
                    background: overallGrade
                        ? `linear-gradient(135deg, ${GRADE_BG[overallGrade]}, white)`
                        : T.borderLight,
                    borderRadius: T.radius, padding: 18, marginBottom: 14,
                    boxShadow: T.shadowCard, border: `1px solid ${T.border}`,
                    animation: "fadeInUp 0.4s ease both",
                }}>
                    <div style={{ fontSize: 12, fontWeight: 700, color: T.textMuted, textTransform: "uppercase", letterSpacing: 0.8, marginBottom: 10, display: "flex", alignItems: "center", gap: 6 }}>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke={T.primary} strokeWidth="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2" /></svg>
                        Overall Score
                    </div>
                    <div style={{ display: "flex", alignItems: "center", gap: 16, marginBottom: 12 }}>
                        <div style={{ fontSize: 48, fontWeight: 900, lineHeight: 1, color: overallGrade ? GRADE_COLOR[overallGrade] : T.textMid }}>
                            {overallPct != null && !isNaN(overallPct) ? Number(overallPct).toFixed(2) : "—"}
                            {overallPct != null && !isNaN(overallPct) && <span style={{ fontSize: 22 }}>%</span>}
                        </div>
                        {overallGrade && <GradeBadge grade={overallGrade} />}
                    </div>
                    <ProgressBar pct={(!isNaN(overallPct) && overallPct != null) ? overallPct : 0} color={GRADE_COLOR[overallGrade] ?? "#6B7280"} height={6} />
                </div>
            )}

            {/* In-progress completion hint */}
            {assessment.status === "in_progress" && (
                <div style={{
                    padding: "14px 16px", background: "linear-gradient(135deg, #FEF3C7, #FFFBEB)",
                    borderRadius: T.radiusSm, marginBottom: 14, border: "1px solid #FDE68A",
                    fontSize: 13, color: "#92400E", display: "flex", alignItems: "center", gap: 8,
                    animation: "fadeInUp 0.4s ease both",
                }}>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#F59E0B" strokeWidth="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2" /></svg>
                    Assessment in progress — complete all sections to submit.
                </div>
            )}

            {/* Section breakdown */}
            {sections.length > 0 && (
                <div style={{
                    background: "white", borderRadius: T.radius, padding: 18,
                    boxShadow: T.shadowCard, border: `1px solid ${T.border}`,
                    animation: "fadeInUp 0.4s ease 0.05s both",
                }}>
                    <div style={{ fontSize: 12, fontWeight: 700, color: T.textMuted, textTransform: "uppercase", letterSpacing: 0.8, marginBottom: 16, display: "flex", alignItems: "center", gap: 6 }}>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke={T.primary} strokeWidth="2"><rect x="3" y="3" width="7" height="7" /><rect x="14" y="3" width="7" height="7" /><rect x="14" y="14" width="7" height="7" /><rect x="3" y="14" width="7" height="7" /></svg>
                        Section Breakdown
                    </div>
                    {sections.map((s, i) => {
                        const sc = sectionScores[s.code];
                        const done = sectionProgress[s.code] === true;
                        const isSpecial = SPECIAL_SECTIONS.includes(s.code);
                        const [g1] = s.gradient ?? [s.color ?? "#6B7280"];
                        const hasSc = sc && (sc.percentage != null);

                        // For special sections, check offline store progress
                        const spHr = specialProgress.hr;
                        const spHp = specialProgress.hp;
                        const spData = s.code === "human_resources" ? spHr : s.code === "health_products" ? spHp : null;
                        const spAnswered = spData?.answered ?? 0;
                        const spTotal = spData?.total ?? 0;
                        const spPct = spTotal > 0 ? Math.round((spAnswered / spTotal) * 100) : 0;
                        // Consider done if section_progress says so, or if all commodities/cadres answered
                        const effectiveDone = done || (isSpecial && spTotal > 0 && spAnswered === spTotal);
                        const effectiveInProgress = !effectiveDone && isSpecial && spAnswered > 0;

                        return (
                            <div key={s.id ?? s.code} style={{
                                marginBottom: i < sections.length - 1 ? 14 : 0,
                                paddingBottom: i < sections.length - 1 ? 14 : 0,
                                borderBottom: i < sections.length - 1 ? `1px solid ${T.borderLight}` : "none",
                                animation: `fadeInUp 0.3s ease ${0.1 + i * 0.04}s both`,
                            }}>
                                <div style={{ display: "flex", alignItems: "center", gap: 10, marginBottom: (hasSc || (isSpecial && spTotal > 0)) ? 8 : 0 }}>
                                    <div style={{
                                        width: 38, height: 38, borderRadius: 12,
                                        background: `${g1}12`, display: "flex", alignItems: "center", justifyContent: "center",
                                        flexShrink: 0, border: `1px solid ${g1}20`,
                                    }}>
                                        <SectionIcon code={s.code} size={18} />
                                    </div>
                                    <div style={{ flex: 1, minWidth: 0 }}>
                                        <div style={{ fontWeight: 700, fontSize: 13, color: T.text }}>{s.name}</div>
                                        {hasSc && (
                                            <div style={{ fontSize: 11, color: T.textMuted, marginTop: 2 }}>
                                                {sc.answered_questions} answered · {sc.total_questions} questions
                                            </div>
                                        )}
                                        {!hasSc && isSpecial && spTotal > 0 && (
                                            <div style={{ fontSize: 11, color: effectiveDone ? "#059669" : T.textMuted, marginTop: 2 }}>
                                                {s.code === "human_resources"
                                                    ? `${spAnswered}/${spTotal} cadres responded`
                                                    : `${spAnswered}/${spTotal} commodities answered`}
                                            </div>
                                        )}
                                        {!hasSc && isSpecial && spTotal === 0 && (
                                            <div style={{ fontSize: 11, color: T.textMuted, marginTop: 2 }}>Not yet completed</div>
                                        )}
                                        {!hasSc && !isSpecial && (
                                            <div style={{ fontSize: 11, color: T.textMuted, marginTop: 2 }}>
                                                {assessment.status === "in_progress" ? "Pending" : "No data"}
                                            </div>
                                        )}
                                    </div>
                                    {hasSc
                                        ? <GradeBadge grade={sc.grade} pct={Number(sc.percentage).toFixed(1)} />
                                        : <SectionStatusChip done={effectiveDone} inProgress={effectiveInProgress} isSpecial={isSpecial} />
                                    }
                                </div>
                                {hasSc && (
                                    <ProgressBar pct={sc.percentage ?? 0} color={GRADE_COLOR[sc.grade] ?? g1} height={5} />
                                )}
                                {!hasSc && isSpecial && spTotal > 0 && (
                                    <ProgressBar pct={spPct} color={effectiveDone ? "#10B981" : "#F59E0B"} height={5} />
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
    const [hpData, setHpData] = useState(null);
    const [specialProgress, setSpecialProgress] = useState({ hr: null, hp: null });
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {
        function applyData(respData, hrResp, hpResp) {
            setResponses(respData?.responses ?? respData ?? {});

            let hrStructure = hrResp?.data ?? (Array.isArray(hrResp) ? hrResp : null);
            setHrData(hrStructure);

            let hpStructure = Array.isArray(hpResp?.data) ? hpResp.data : (Array.isArray(hpResp) ? hpResp : null);
            setHpData(hpStructure);

            const hrList = Array.isArray(hrStructure) ? hrStructure : [];
            const hrAnswered = hrList.filter(c => HR_FIELDS.some(f => (c[f] ?? 0) > 0)).length;

            let hpTotal = 0, hpAnswered = 0;
            (hpStructure ?? []).forEach(dept => {
                (dept.categories ?? []).forEach(cat => {
                    (cat.commodities ?? []).forEach(c => {
                        hpTotal++;
                        if (c.available !== null && c.available !== undefined) hpAnswered++;
                    });
                });
            });

            setSpecialProgress({
                hr: { answered: hrAnswered, total: hrList.length },
                hp: { answered: hpAnswered, total: hpTotal },
            });
        }

        Promise.all([
            api.responses.list(assessment.id),
            api.humanResources.get(assessment.id).catch(() => null),
            api.healthProducts.get(assessment.id).catch(() => null),
        ])
            .then(([respData, hrResp, hpResp]) => applyData(respData, hrResp, hpResp))
            .catch(async () => {
                // Network failed — load directly from IndexedDB
                try {
                    const [offResp, offHr, offHp] = await Promise.all([
                        offlineStore.getResponses(assessment.id),
                        offlineStore.getHR(assessment.id),
                        offlineStore.getHP(assessment.id),
                    ]);

                    // Merge pending HR values into structure
                    let hrStructure = offHr?.structure ?? [];
                    if (offHr?.pendingValues) {
                        hrStructure = hrStructure.map(cadre => {
                            const p = offHr.pendingValues[cadre.cadre_id];
                            return p ? { ...cadre, ...p } : cadre;
                        });
                    }

                    // Merge pending HP flat-map into structure
                    let hpStructure = offHp?.structure ?? [];
                    if (offHp?.pendingFlat) {
                        hpStructure = hpStructure.map(dept => ({
                            ...dept,
                            categories: dept.categories.map(cat => ({
                                ...cat,
                                commodities: cat.commodities.map(c => {
                                    const key = `${dept.department_id}_${c.commodity_id}`;
                                    return offHp.pendingFlat[key] !== undefined
                                        ? { ...c, available: offHp.pendingFlat[key] }
                                        : c;
                                }),
                            })),
                        }));
                    }

                    applyData(
                        offResp,
                        hrStructure.length > 0 ? { data: hrStructure } : null,
                        hpStructure.length > 0 ? { data: hpStructure } : null,
                    );
                } catch {
                    setError("Responses unavailable — no offline data found.");
                }
            })
            .finally(() => setLoading(false));
    }, [assessment.id]);

    if (loading) return (
        <div style={{ textAlign: "center", padding: "40px 16px", color: T.textMuted }}>
            <div style={{
                width: 44, height: 44, borderRadius: 14, margin: "0 auto 12px",
                background: T.gradientPrimary,
                display: "flex", alignItems: "center", justifyContent: "center",
                boxShadow: `0 6px 20px ${T.primaryGlow}`,
            }}>
                <svg width="22" height="22" viewBox="0 0 24 24" style={{ animation: "spin 1s linear infinite" }}>
                    <circle cx="12" cy="12" r="10" fill="none" stroke="rgba(255,255,255,0.25)" strokeWidth="3" />
                    <path d="M12 2a10 10 0 019.95 9" fill="none" stroke="white" strokeWidth="3" strokeLinecap="round" />
                </svg>
            </div>
            <div style={{ fontSize: 13 }}>Loading responses…</div>
        </div>
    );
    if (error) return (
        <div style={{ padding: "14px 16px", background: "linear-gradient(135deg,#FEE2E2,#FFF1F2)", borderRadius: T.radiusSm, fontSize: 13, color: "#991B1B", border: "1px solid #FECACA", display: "flex", alignItems: "center", gap: 8 }}>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#EF4444" strokeWidth="2"><circle cx="12" cy="12" r="10" /><line x1="15" y1="9" x2="9" y2="15" /><line x1="9" y1="9" x2="15" y2="15" /></svg>
            {error}
        </div>
    );

    const r = responses ?? {};

    return (
        <>
            {sections.map((s, sIdx) => {
                const isHr = s.code === "human_resources";
                const isHp = s.code === "health_products";
                const isSpecial = isHr || isHp;
                const questions = isSpecial ? [] : (s.questions || []);

                // Use special progress counts for HR/HP; fall back to question count
                let answered, total;
                if (isHr && specialProgress?.hr) {
                    answered = specialProgress.hr.answered;
                    total = specialProgress.hr.total;
                } else if (isHp && specialProgress?.hp) {
                    answered = specialProgress.hp.answered;
                    total = specialProgress.hp.total;
                } else {
                    answered = questions.filter(q => r[q.question_code] != null && r[q.question_code] !== "").length;
                    total = questions.length;
                }

                const open = expanded === s.code;

                return (
                    <div key={s.id ?? s.code} style={{
                        background: "white", borderRadius: T.radius, marginBottom: 10,
                        overflow: "hidden", boxShadow: T.shadowCard, border: `1px solid ${T.border}`,
                        animation: `fadeInUp 0.3s ease ${sIdx * 0.04}s both`,
                    }}>
                        <button onClick={() => setExpanded(open ? null : s.code)} style={{
                            width: "100%", padding: "13px 16px", border: "none", background: "none",
                            cursor: "pointer", display: "flex", alignItems: "center", gap: 10, textAlign: "left",
                        }}>
                            <SectionIcon code={s.code} size={20} />
                            <div style={{ flex: 1 }}>
                                <div style={{ fontWeight: 700, fontSize: 13, color: T.text }}>{s.name}</div>
                                <div style={{ fontSize: 11, color: T.textMuted, marginTop: 2 }}>
                                    {isHr
                                        ? total > 0 ? `${answered}/${total} cadres responded` : "Staff training counts"
                                        : isHp
                                            ? total > 0 ? `${answered}/${total} commodities answered` : "Commodity availability"
                                            : `${answered} / ${total} answered`}
                                </div>
                            </div>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke={T.textMuted} strokeWidth="2.5"
                                style={{ transition: "transform 0.2s", transform: open ? "rotate(180deg)" : "none" }}>
                                <polyline points="6 9 12 15 18 9" />
                            </svg>
                        </button>

                        {open && (
                            <div style={{ borderTop: `1px solid ${T.borderLight}` }}>
                                {isHr && <HrResponsesView data={hrData} />}
                                {isHp && <HpResponsesView data={hpData} />}
                                {!isSpecial && questions.length === 0 && (
                                    <div style={{ padding: "14px 16px", fontSize: 12, color: T.textMuted }}>No questions.</div>
                                )}
                                {!isSpecial && questions.map((q, i) => {
                                    const val = r[q.question_code];
                                    const hasAnswer = val != null && val !== "";
                                    const chips = { Yes: { bg: "#D1FAE5", color: "#065F46" }, No: { bg: "#FEE2E2", color: "#991B1B" }, Partial: { bg: "#FEF3C7", color: "#92400E" } };
                                    const chip = chips[val] ?? { bg: T.borderLight, color: T.textMuted };
                                    const expl = responses?.__explanations?.[q.question_code];
                                    return (
                                        <div key={q.id ?? q.question_code} style={{
                                            padding: "11px 16px",
                                            borderBottom: i < questions.length - 1 ? `1px solid ${T.borderLight}` : "none",
                                        }}>
                                            <div style={{ display: "flex", alignItems: "flex-start", gap: 10 }}>
                                                <div style={{ flex: 1, fontSize: 12, color: T.textMid, lineHeight: 1.5 }}>{q.question_text}</div>
                                                <div style={{ padding: "4px 12px", borderRadius: 10, fontSize: 11, fontWeight: 700, flexShrink: 0, background: chip.bg, color: chip.color, border: `1px solid ${chip.color}22` }}>
                                                    {hasAnswer ? String(val) : "—"}
                                                </div>
                                            </div>
                                            {expl && <div style={{ fontSize: 11, color: T.textSub, marginTop: 5, fontStyle: "italic" }}>💬 {expl}</div>}
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
        return <div style={{ padding: "14px 16px", fontSize: 12, color: T.textMuted }}>No HR data recorded.</div>;
    }
    const cols = ["etat_plus", "comprehensive_newborn_care", "imnci", "type_1_diabetes", "essential_newborn_care"];
    const labels = { etat_plus: "ETAT+", comprehensive_newborn_care: "Comp. NB Care", imnci: "IMNCI", type_1_diabetes: "T1 Diabetes", essential_newborn_care: "Essential NB" };
    return (
        <div style={{ overflowX: "auto" }}>
            <table style={{ width: "100%", borderCollapse: "collapse", fontSize: 11 }}>
                <thead>
                    <tr style={{ background: T.borderLight }}>
                        <th style={{ padding: "10px 12px", textAlign: "left", color: T.textMuted, fontWeight: 700 }}>Cadre</th>
                        {cols.map(c => <th key={c} style={{ padding: "10px 8px", textAlign: "center", color: T.textMuted, fontWeight: 700, whiteSpace: "nowrap" }}>{labels[c]}</th>)}
                    </tr>
                </thead>
                <tbody>
                    {data.map((row, i) => (
                        <tr key={i} style={{ borderBottom: `1px solid ${T.borderLight}` }}>
                            <td style={{ padding: "10px 12px", color: T.textMid, fontWeight: 600 }}>{row.cadre_name ?? row.cadre ?? "—"}</td>
                            {cols.map(c => (
                                <td key={c} style={{
                                    padding: "10px 8px", textAlign: "center",
                                    color: (row[c] ?? 0) > 0 ? "#065F46" : T.textMuted,
                                    fontWeight: (row[c] ?? 0) > 0 ? 700 : 400,
                                    background: (row[c] ?? 0) > 0 ? "#D1FAE508" : "transparent",
                                }}>
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

// ── HP summary view ───────────────────────────────────────────────────────────
function HpResponsesView({ data }) {
    if (!data || !Array.isArray(data) || data.length === 0) {
        return <div style={{ padding: "14px 16px", fontSize: 12, color: T.textMuted }}>No commodity data recorded.</div>;
    }
    return (
        <div style={{ padding: "10px 14px 14px" }}>
            {data.map(dept => {
                const allCommodities = (dept.categories ?? []).flatMap(c => c.commodities ?? []);
                const answered = allCommodities.filter(c => c.available !== null && c.available !== undefined).length;
                const available = allCommodities.filter(c => c.available === true).length;
                return (
                    <div key={dept.department_id} style={{ marginBottom: 10 }}>
                        <div style={{ fontSize: 11, fontWeight: 800, color: T.textMid, marginBottom: 6, display: "flex", alignItems: "center", justifyContent: "space-between" }}>
                            <span>{dept.department_name}</span>
                            <span style={{ fontSize: 10, fontWeight: 600, color: T.textMuted }}>{answered}/{allCommodities.length} answered · {available} available</span>
                        </div>
                        {(dept.categories ?? []).map(cat => (
                            <div key={cat.category_id} style={{ marginBottom: 4 }}>
                                <div style={{ fontSize: 10, color: T.textMuted, fontWeight: 700, marginBottom: 3 }}>{cat.category_name}</div>
                                <div style={{ display: "flex", flexWrap: "wrap", gap: 4 }}>
                                    {(cat.commodities ?? []).map(c => {
                                        const chip =
                                            c.available === true  ? { bg: "#D1FAE5", color: "#065F46", label: "✓" } :
                                            c.available === false ? { bg: "#FEE2E2", color: "#991B1B", label: "✗" } :
                                            { bg: T.borderLight, color: T.textMuted, label: "—" };
                                        return (
                                            <div key={c.commodity_id} style={{
                                                padding: "3px 8px", borderRadius: 6, fontSize: 10, fontWeight: 600,
                                                background: chip.bg, color: chip.color,
                                                display: "flex", alignItems: "center", gap: 3,
                                            }}>
                                                <span>{chip.label}</span>
                                                <span>{c.name}</span>
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>
                        ))}
                    </div>
                );
            })}
        </div>
    );
}

// ── Delete confirmation modal ─────────────────────────────────────────────────
function DeleteConfirmModal({ assessment, onCancel, onConfirmed }) {
    const COUNTDOWN = 4;
    const [seconds, setSeconds] = useState(COUNTDOWN);
    const [deleting, setDeleting] = useState(false);
    const [error, setError] = useState(null);
    const timerRef = useRef(null);

    useEffect(() => {
        timerRef.current = setInterval(() => {
            setSeconds(s => {
                if (s <= 1) {
                    clearInterval(timerRef.current);
                    return 0;
                }
                return s - 1;
            });
        }, 1000);
        return () => clearInterval(timerRef.current);
    }, []);

    async function handleDelete() {
        if (seconds > 0 || deleting) return;
        setDeleting(true);
        setError(null);
        try {
            await api.assessments.delete(assessment.id);
            await offlineStore.deleteAssessment(assessment.id).catch(() => {});
            onConfirmed(assessment.id);
        } catch (e) {
            setError(e.message ?? "Failed to delete. Please try again.");
            setDeleting(false);
        }
    }

    const canDelete = seconds === 0 && !deleting;

    return (
        <>
            {/* Backdrop */}
            <div style={{
                position: "fixed", inset: 0,
                background: "rgba(0,0,0,0.65)",
                zIndex: 2000,
                backdropFilter: "blur(4px)",
                WebkitBackdropFilter: "blur(4px)",
            }} />

            {/* Modal */}
            <div style={{
                position: "fixed", inset: 0,
                zIndex: 2001,
                display: "flex", alignItems: "center", justifyContent: "center",
                padding: "0 24px",
            }}>
                <div style={{
                    background: "#fff",
                    borderRadius: 20,
                    padding: "28px 24px 24px",
                    width: "100%",
                    maxWidth: 360,
                    boxShadow: "0 24px 60px rgba(0,0,0,0.35)",
                }}>
                    {/* Icon */}
                    <div style={{
                        width: 56, height: 56, borderRadius: "50%",
                        background: "#FEE2E2",
                        display: "flex", alignItems: "center", justifyContent: "center",
                        margin: "0 auto 18px",
                    }}>
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#DC2626" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
                            <polyline points="3 6 5 6 21 6" />
                            <path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6" />
                            <path d="M10 11v6M14 11v6" />
                            <path d="M9 6V4a1 1 0 011-1h4a1 1 0 011 1v2" />
                        </svg>
                    </div>

                    <div style={{ textAlign: "center", marginBottom: 16 }}>
                        <div style={{ fontSize: 17, fontWeight: 800, color: "#111827", marginBottom: 8 }}>
                            Permanently Delete Assessment?
                        </div>
                        <div style={{
                            fontSize: 13, color: "#6B7280", lineHeight: 1.6,
                        }}>
                            You are about to permanently delete the assessment for{" "}
                            <strong style={{ color: "#374151" }}>{assessment.facility_name}</strong>.
                        </div>
                    </div>

                    {/* Warning box */}
                    <div style={{
                        background: "#FEF2F2",
                        border: "1px solid #FECACA",
                        borderRadius: 10,
                        padding: "12px 14px",
                        marginBottom: 20,
                    }}>
                        <div style={{ fontSize: 12, fontWeight: 700, color: "#991B1B", marginBottom: 4, display: "flex", alignItems: "center", gap: 6 }}>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#DC2626" strokeWidth="2.5" strokeLinecap="round"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                            This action is irreversible
                        </div>
                        <div style={{ fontSize: 12, color: "#B91C1C", lineHeight: 1.6 }}>
                            All responses, scores, and data for this assessment will be wiped from both the server and this device. There is no way to recover it.
                        </div>
                    </div>

                    {error && (
                        <div style={{
                            background: "#FEE2E2", color: "#991B1B",
                            borderRadius: 8, padding: "10px 12px",
                            fontSize: 13, marginBottom: 14,
                        }}>
                            {error}
                        </div>
                    )}

                    <div style={{ display: "flex", gap: 10 }}>
                        <button
                            onClick={onCancel}
                            disabled={deleting}
                            style={{
                                flex: 1, padding: "13px 0",
                                borderRadius: 12, border: `1.5px solid ${T.border}`,
                                background: "#fff", color: T.textMid,
                                fontSize: 14, fontWeight: 600, cursor: "pointer",
                                opacity: deleting ? 0.5 : 1,
                            }}
                        >
                            Cancel
                        </button>
                        <button
                            onClick={handleDelete}
                            disabled={!canDelete}
                            style={{
                                flex: 1, padding: "13px 0",
                                borderRadius: 12, border: "none",
                                background: canDelete ? "#DC2626" : "#FCA5A5",
                                color: "#fff",
                                fontSize: 14, fontWeight: 700,
                                cursor: canDelete ? "pointer" : "not-allowed",
                                transition: "background 0.3s",
                                position: "relative",
                                overflow: "hidden",
                            }}
                        >
                            {deleting
                                ? "Deleting…"
                                : seconds > 0
                                    ? `Delete (${seconds})`
                                    : "Yes, Delete"}
                        </button>
                    </div>
                </div>
            </div>
        </>
    );
}

// ── Main screen ───────────────────────────────────────────────────────────────
export function AssessmentDetailScreen({ assessment, sections, onBack, onContinue, onViewReport, onDelete }) {
    const [tab, setTab] = useState("overview");
    const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);

    const tabs = assessment.status === "completed"
        ? ["overview", "responses", "report"]
        : ["overview", "responses"];
    const headerPct = calcOverallFromSections(assessment.section_scores ?? {}) ?? assessment.overall_percentage;
    const headerGrade = headerPct != null ? calcGrade(headerPct) : assessment.overall_grade;

    // Show delete only when assessment is not completed (may have partial/no data)
    const canDelete = assessment.status !== "completed" && onDelete;

    return (
        <div style={{ display: "flex", flexDirection: "column", height: "100%" }}>
            {showDeleteConfirm && (
                <DeleteConfirmModal
                    assessment={assessment}
                    onCancel={() => setShowDeleteConfirm(false)}
                    onConfirmed={(id) => {
                        setShowDeleteConfirm(false);
                        onDelete(id);
                    }}
                />
            )}

            {/* 6px breathing gap between status bar and gradient card */}
            <div style={{ height: 6, background: T.bg }} />
            {/* Header */}
            <div style={{
                background: T.gradientDark, padding: "14px 20px 24px",
                position: "relative", overflow: "hidden",
                borderRadius: "0 0 28px 28px",
                margin: "0 6px",
            }}>
                <div style={{ position: "absolute", width: 140, height: 140, borderRadius: "50%", background: "radial-gradient(circle, rgba(79,106,245,0.20) 0%, transparent 70%)", top: -40, right: -30 }} />

                {/* Top row: back + delete */}
                <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between" }}>
                    <BackButton onBack={onBack} light />
                    {canDelete && (
                        <button
                            onClick={() => setShowDeleteConfirm(true)}
                            aria-label="Delete assessment"
                            style={{
                                background: "rgba(255,255,255,0.12)",
                                border: "1px solid rgba(255,255,255,0.2)",
                                borderRadius: 10,
                                padding: "8px 10px",
                                cursor: "pointer",
                                display: "flex", alignItems: "center", justifyContent: "center",
                                backdropFilter: "blur(8px)",
                            }}
                        >
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="rgba(255,200,200,0.9)" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
                                <polyline points="3 6 5 6 21 6" />
                                <path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6" />
                                <path d="M10 11v6M14 11v6" />
                                <path d="M9 6V4a1 1 0 011-1h4a1 1 0 011 1v2" />
                            </svg>
                        </button>
                    )}
                </div>

                <div style={{ marginTop: 12, color: "white", fontSize: 18, fontWeight: 800, letterSpacing: -0.3, animation: "fadeInUp 0.4s ease both" }}>
                    {assessment.facility_name || "Assessment"}
                </div>
                <div style={{ color: "rgba(255,255,255,0.5)", fontSize: 13, marginTop: 3, animation: "fadeInUp 0.4s ease 0.05s both" }}>
                    {[assessment.assessment_type, assessment.assessment_date].filter(Boolean).join(" · ")}
                </div>
                <div style={{ marginTop: 10, animation: "fadeInUp 0.4s ease 0.1s both" }}>
                    {headerGrade
                        ? <GradeBadge grade={headerGrade} pct={Number(headerPct ?? 0).toFixed(1)} />
                        : <StatusChip status={assessment.status} />
                    }
                </div>
            </div>

            {/* Tabs */}
            <div style={{
                display: "flex", background: "white",
                borderBottom: `1px solid ${T.border}`,
                boxShadow: "0 2px 8px rgba(0,0,0,0.03)",
            }}>
                {tabs.map(t => (
                    <button key={t} onClick={() => setTab(t)} style={{
                        flex: 1, padding: "13px 0", border: "none",
                        background: "white",
                        borderBottom: tab === t ? `2.5px solid ${T.primary}` : "2.5px solid transparent",
                        color: tab === t ? T.primary : T.textMuted,
                        fontSize: 13, fontWeight: tab === t ? 700 : 500,
                        cursor: "pointer", textTransform: "capitalize",
                        transition: "all 0.2s",
                    }}>
                        {t === "report" ? "📄 Report" : t}
                    </button>
                ))}
            </div>

            {/* Content */}
            <div style={{ flex: 1, overflowY: "auto", padding: "14px 16px 20px", background: T.bg }}>
                {tab === "overview" && <OverviewTab assessment={assessment} sections={sections ?? []} />}
                {tab === "responses" && <ResponsesTab assessment={assessment} sections={sections ?? []} />}
                {tab === "report" && <ReportPreviewTab assessment={assessment} onViewFull={onViewReport} />}
            </div>

            {/* Footer CTA */}
            {assessment.status === "in_progress" && onContinue && (
                <div style={{
                    padding: "12px 16px", paddingBottom: "calc(12px + env(safe-area-inset-bottom, 0px))",
                    background: "rgba(255,255,255,0.95)",
                    backdropFilter: "blur(12px)", borderTop: `1px solid ${T.border}`,
                }}>
                    <button onClick={() => onContinue(assessment)} style={{
                        width: "100%", padding: 15, borderRadius: T.radius, border: "none",
                        background: T.gradientPrimary,
                        color: "white", fontSize: 15, fontWeight: 700, cursor: "pointer",
                        boxShadow: `0 6px 20px ${T.primaryGlow}`, transition: "all 0.2s",
                    }}>
                        Continue Assessment →
                    </button>
                </div>
            )}
            {assessment.status === "completed" && tab === "report" && onViewReport && (
                <div style={{
                    padding: "12px 16px", paddingBottom: "calc(12px + env(safe-area-inset-bottom, 0px))",
                    background: "rgba(255,255,255,0.95)",
                    backdropFilter: "blur(12px)", borderTop: `1px solid ${T.border}`,
                    display: "flex", gap: 10,
                }}>
                    <button onClick={onViewReport} style={{
                        flex: 1, padding: 13, borderRadius: T.radius, border: "none",
                        background: T.gradientPrimary,
                        color: "white", fontSize: 14, fontWeight: 700, cursor: "pointer",
                        boxShadow: `0 6px 20px ${T.primaryGlow}`,
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
    const _calcPct = calcOverallFromSections(sectionScores);
    const pct = _calcPct ?? assessment.overall_percentage ?? 0;
    const grade = _calcPct != null ? calcGrade(_calcPct) : (assessment.overall_grade ?? null);

    return (
        <div>
            {/* Summary card */}
            <div style={{
                background: grade ? `linear-gradient(135deg, ${GRADE_BG[grade]}, white)` : "white",
                borderRadius: T.radius, padding: 18, marginBottom: 14,
                boxShadow: T.shadowCard, border: `1px solid ${grade ? GRADE_COLOR[grade] + "33" : T.border}`,
                animation: "fadeInUp 0.4s ease both",
            }}>
                <div style={{ fontSize: 11, fontWeight: 700, color: T.textMuted, textTransform: "uppercase", letterSpacing: 0.8, marginBottom: 10 }}>Assessment Summary</div>
                <div style={{ display: "flex", alignItems: "center", gap: 14, marginBottom: 12 }}>
                    <div style={{ fontSize: 44, fontWeight: 900, lineHeight: 1, color: grade ? GRADE_COLOR[grade] : T.textMid }}>
                        {Number(pct).toFixed(1)}<span style={{ fontSize: 20 }}>%</span>
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
                <div style={{
                    background: "white", borderRadius: T.radius, padding: "16px 18px", marginBottom: 14,
                    boxShadow: T.shadowCard, border: `1px solid ${T.border}`,
                    animation: "fadeInUp 0.4s ease 0.05s both",
                }}>
                    <div style={{ fontSize: 11, fontWeight: 700, color: T.textMuted, textTransform: "uppercase", letterSpacing: 0.8, marginBottom: 14 }}>Section Scores</div>
                    {sections.map(([code, sc], i) => (
                        <div key={code} style={{ marginBottom: i < sections.length - 1 ? 12 : 0 }}>
                            <div style={{ display: "flex", justifyContent: "space-between", marginBottom: 5 }}>
                                <span style={{ fontSize: 12, color: T.textMid, fontWeight: 600 }}>{sc.name ?? code}</span>
                                <span style={{ fontSize: 12, fontWeight: 700, color: GRADE_COLOR[sc.grade] ?? T.textMuted }}>{Number(sc.percentage ?? 0).toFixed(1)}%</span>
                            </div>
                            <ProgressBar pct={sc.percentage ?? 0} color={GRADE_COLOR[sc.grade] ?? "#6B7280"} height={5} />
                        </div>
                    ))}
                </div>
            )}

            {/* Full report CTA */}
            <div style={{
                background: "white", borderRadius: T.radius, padding: 18,
                boxShadow: T.shadowCard, border: `1px solid ${T.border}`, textAlign: "center",
                animation: "fadeInUp 0.4s ease 0.1s both",
            }}>
                <div style={{
                    width: 48, height: 48, borderRadius: 16, margin: "0 auto 10px",
                    background: T.primaryGhost, display: "flex", alignItems: "center", justifyContent: "center",
                }}>
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke={T.primary} strokeWidth="2">
                        <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z" /><polyline points="14 2 14 8 20 8" />
                    </svg>
                </div>
                <div style={{ fontSize: 14, fontWeight: 700, color: T.text, marginBottom: 4 }}>Full Assessment Report</div>
                <div style={{ fontSize: 12, color: T.textSub, marginBottom: 16 }}>
                    View detailed findings, responses per section, and download a PDF copy.
                </div>
                {onViewFull ? (
                    <button onClick={onViewFull} style={{
                        width: "100%", padding: "13px", borderRadius: T.radiusSm, border: "none",
                        background: T.gradientPrimary,
                        color: "white", fontSize: 14, fontWeight: 700, cursor: "pointer",
                        boxShadow: `0 6px 20px ${T.primaryGlow}`,
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
