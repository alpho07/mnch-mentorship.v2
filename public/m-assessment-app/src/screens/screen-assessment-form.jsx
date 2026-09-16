import { useState, useEffect, useRef, useCallback } from "react";
import { T, calcGrade, isQuestionVisible, getSectionCompletion, GRADE_COLOR, GRADE_BG } from "../constants.js";
import { BackButton, ProgressBar } from "../components/shared-components.jsx";
import { SectionIcon } from "../components/section-icons.jsx";
import { QuestionCard, MortalityThreeMonthInput } from "../components/question-inputs.jsx";
import { ChatbotPanel } from "../components/chatbot-widget.jsx";
import { HumanResourcesScreen } from "./screen-human-resources.jsx";
import { HealthProductsScreen } from "./screen-health-products.jsx";
import api from "../services/api.service.js";
import offlineStore from "../services/offline-store.js";

const SPECIAL_SECTIONS = {
    human_resources: HumanResourcesScreen,
    health_products: HealthProductsScreen,
};

const AUTO_SAVE_DELAY = 30000; // 30 seconds

// HR field keys used to determine whether a cadre has any data entered
const HR_FIELDS = ["etat_plus", "comprehensive_newborn_care", "imnci", "type_1_diabetes", "essential_newborn_care"];

// ── Compute HR/HP progress from cached offline store data ─────────────────────
async function computeSpecialProgress(assessmentId) {
    const [hrCached, hpCached] = await Promise.all([
        offlineStore.getHR(assessmentId),
        offlineStore.getHP(assessmentId),
    ]);

    // HR: count cadres that have at least one training field > 0
    const hrStructure = hrCached?.structure ?? [];
    const hrTotal = hrStructure.length;
    const hrAnswered = hrStructure.filter(c =>
        HR_FIELDS.some(f => (c[f] ?? 0) > 0)
    ).length;

    // HP: count commodities that have been explicitly answered (available set)
    const hpStructure = hpCached?.structure ?? [];
    let hpTotal = 0, hpAnswered = 0;
    hpStructure.forEach(dept => {
        (dept.categories ?? []).forEach(cat => {
            (cat.commodities ?? []).forEach(c => {
                hpTotal++;
                if (c.available !== null && c.available !== undefined) hpAnswered++;
            });
        });
    });

    return {
        hr: { answered: hrAnswered, total: hrTotal },
        hp: { answered: hpAnswered, total: hpTotal },
    };
}

// ── Circular ring ─────────────────────────────────────────────────────────────
function CircleRing({ pct, size = 56, stroke = 5, color = "#10B981", bg = "rgba(255,255,255,0.15)", children }) {
    const r = (size - stroke * 2) / 2;
    const circ = 2 * Math.PI * r;
    const offset = circ - (pct / 100) * circ;
    return (
        <div style={{ position: "relative", width: size, height: size, flexShrink: 0 }}>
            <svg width={size} height={size} style={{ transform: "rotate(-90deg)" }}>
                <circle cx={size / 2} cy={size / 2} r={r} fill="none" stroke={bg} strokeWidth={stroke} />
                <circle cx={size / 2} cy={size / 2} r={r} fill="none" stroke={color} strokeWidth={stroke}
                    strokeDasharray={circ} strokeDashoffset={offset} strokeLinecap="round"
                    style={{ transition: "stroke-dashoffset 0.6s cubic-bezier(0.34,1.56,0.64,1)" }} />
            </svg>
            <div style={{ position: "absolute", inset: 0, display: "flex", alignItems: "center", justifyContent: "center" }}>
                {children}
            </div>
        </div>
    );
}

// ── Auto-save status pill ─────────────────────────────────────────────────────
function AutoSavePill({ status }) {
    // status: "idle" | "saving" | "saved" | "error"
    const map = {
        idle: { label: "", color: "transparent", bg: "transparent" },
        saving: { label: "⏳ Saving…", color: "#92400E", bg: "#FEF3C7" },
        saved: { label: "✓ Auto-saved", color: "#065F46", bg: "#D1FAE5" },
        error: { label: "⚠ Save failed", color: "#991B1B", bg: "#FEE2E2" },
    };
    const s = map[status] ?? map.idle;
    if (!s.label) return null;
    return (
        <div style={{
            position: "absolute", top: 12, right: 16,
            padding: "3px 10px", borderRadius: 20,
            fontSize: 10, fontWeight: 700,
            color: s.color, background: s.bg,
            transition: "all 0.3s",
            zIndex: 10,
        }}>{s.label}</div>
    );
}

// ── Section card ──────────────────────────────────────────────────────────────
function SectionCard({ section, completedSections, responses, onOpen, index, specialProgress }) {
    const isCompleted = completedSections.has(section.code);
    const isHr = section.code === "human_resources";
    const isHp = section.code === "health_products";

    let completion;
    if (isHr && specialProgress?.hr) {
        completion = specialProgress.hr;
    } else if (isHp && specialProgress?.hp) {
        completion = specialProgress.hp;
    } else {
        completion = getSectionCompletion(section.questions || [], responses);
    }

    const pct = completion.total > 0 ? Math.round((completion.answered / completion.total) * 100) : 0;
    const [g1, g2] = section.gradient ?? [section.color ?? "#6B7280", section.color ?? "#374151"];

    return (
        <button onClick={() => onOpen(section)} style={{
            width: "100%", background: T.card, borderRadius: 16, padding: 0, marginBottom: 10,
            border: `2px solid ${isCompleted ? "#6EE7B7" : pct > 0 ? "#FCD34D" : T.borderLight}`,
            cursor: "pointer", textAlign: "left",
            boxShadow: isCompleted ? "0 4px 16px rgba(79,106,245,0.12)" : T.shadow,
            overflow: "hidden", transition: "all 0.2s",
            animation: `cardIn 0.3s ease ${index * 0.05}s both`,
        }}>
            <style>{`@keyframes cardIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}`}</style>
            <div style={{ height: 4, background: `linear-gradient(90deg,${g1},${g2})`, opacity: isCompleted ? 1 : 0.5 }} />
            <div style={{ padding: "13px 16px", display: "flex", alignItems: "center", gap: 12 }}>
                <CircleRing pct={pct} size={50} stroke={4} color={g1} bg={T.borderLight}>
                    <div style={{ width: 34, height: 34, borderRadius: 10, background: `linear-gradient(135deg,${g1}22,${g2}33)`, display: "flex", alignItems: "center", justifyContent: "center" }}>
                        <SectionIcon code={section.code} size={18} />
                    </div>
                </CircleRing>
                <div style={{ flex: 1, minWidth: 0 }}>
                    <div style={{ fontSize: 14, fontWeight: 700, color: T.text, marginBottom: 2 }}>{section.name}</div>
                    <div style={{ fontSize: 11, color: T.textMuted, overflow: "hidden", textOverflow: "ellipsis", whiteSpace: "nowrap" }}>
                        {isHr
                            ? completion.total > 0
                                ? `${completion.answered}/${completion.total} cadres responded`
                                : "Staff training counts"
                            : isHp
                                ? completion.total > 0
                                    ? `${completion.answered}/${completion.total} commodities answered`
                                    : "Commodity availability"
                                : section.description || `${(section.questions || []).length} questions`}
                    </div>
                    <div style={{ height: 3, background: T.borderLight, borderRadius: 999, overflow: "hidden", marginTop: 6 }}>
                        <div style={{ height: "100%", width: `${pct}%`, background: `linear-gradient(90deg,${g1},${g2})`, borderRadius: 999, transition: "width 0.5s" }} />
                    </div>
                </div>
                <div style={{ display: "flex", flexDirection: "column", alignItems: "flex-end", gap: 3, flexShrink: 0 }}>
                    <div style={{
                        width: 24, height: 24, borderRadius: 8, fontSize: 13, fontWeight: 900,
                        background: isCompleted ? "#D1FAE5" : pct > 0 ? "#FEF3C7" : T.borderLight,
                        display: "flex", alignItems: "center", justifyContent: "center",
                        color: isCompleted ? "#10B981" : pct > 0 ? "#F59E0B" : T.textMuted,
                    }}>{isCompleted ? "✓" : pct > 0 ? "…" : "○"}</div>
                    <span style={{ fontSize: 10, fontWeight: 700, color: isCompleted ? "#10B981" : pct > 0 ? "#F59E0B" : T.textMuted }}>
                        {isCompleted ? "Complete" : pct > 0 ? `${pct}%` : "Not started"}
                    </span>
                </div>
                <span style={{ color: T.textMuted, fontSize: 16 }}>›</span>
            </div>
        </button>
    );
}

// ── Recommendations panel ─────────────────────────────────────────────────────
function RecommendationsPanel({ sections, responses }) {
    const issues = [];
    sections.forEach(s => {
        (s.questions || []).filter(q => isQuestionVisible(q, responses)).forEach(q => {
            const v = responses[q.question_code];
            if (v === "No") issues.push({ type: "critical", section: s.name, code: s.code, question: q.question_text });
            else if (v === "Partial") issues.push({ type: "warning", section: s.name, code: s.code, question: q.question_text });
        });
    });
    const critical = issues.filter(i => i.type === "critical").slice(0, 3);
    const warnings = issues.filter(i => i.type === "warning").slice(0, 2);
    if (!issues.length) return null;

    return (
        <div style={{ background: T.card, borderRadius: 16, marginBottom: 14, border: "2px solid #FEE2E2", overflow: "hidden", boxShadow: T.shadow }}>
            <div style={{ padding: "11px 16px 9px", background: "linear-gradient(135deg,#FEF2F2,#FFF1F2)", borderBottom: "1px solid #FEE2E2", display: "flex", alignItems: "center", gap: 8 }}>
                <span style={{ fontSize: 16 }}>⚠️</span>
                <div>
                    <div style={{ fontSize: 13, fontWeight: 800, color: "#991B1B" }}>Improvement Areas</div>
                    <div style={{ fontSize: 11, color: "#B91C1C" }}>{critical.length} critical · {warnings.length} partial</div>
                </div>
            </div>
            <div style={{ padding: "10px 16px 12px" }}>
                {[...critical, ...warnings].map((item, i) => (
                    <div key={i} style={{
                        display: "flex", gap: 8, alignItems: "flex-start",
                        marginBottom: i < critical.length + warnings.length - 1 ? 8 : 0,
                        padding: "7px 10px", borderRadius: 10,
                        background: item.type === "critical" ? "#FEF2F2" : "#FFFBEB",
                        border: `1px solid ${item.type === "critical" ? "#FEE2E2" : "#FDE68A"}`,
                    }}>
                        <span style={{ flexShrink: 0, display: "flex", alignItems: "center" }}><SectionIcon code={item.code} size={14} /></span>
                        <div style={{ flex: 1 }}>
                            <div style={{ fontSize: 10, fontWeight: 700, color: item.type === "critical" ? "#EF4444" : "#F59E0B", textTransform: "uppercase", marginBottom: 2 }}>{item.section}</div>
                            <div style={{ fontSize: 11, lineHeight: 1.4, color: item.type === "critical" ? "#7F1D1D" : "#78350F" }}>
                                {item.question.length > 80 ? item.question.slice(0, 80) + "…" : item.question}
                            </div>
                        </div>
                        <span style={{ flexShrink: 0, fontSize: 10, fontWeight: 700, padding: "2px 6px", borderRadius: 5, color: item.type === "critical" ? "#991B1B" : "#92400E", background: item.type === "critical" ? "#FEE2E2" : "#FEF3C7" }}>
                            {item.type === "critical" ? "No" : "Partial"}
                        </span>
                    </div>
                ))}
            </div>
        </div>
    );
}

// ── Progress header ───────────────────────────────────────────────────────────
function ProgressHeader({ sections, completedSections, responses, onBack, specialProgress }) {
    const overallPct = sections.length > 0 ? Math.round((completedSections.size / sections.length) * 100) : 0;

    // Sum answered/total across all sections, using special progress for HR/HP
    let totalAnswered = 0, totalRequired = 0;
    sections.forEach(s => {
        if (s.code === "human_resources" && specialProgress?.hr) {
            totalAnswered += specialProgress.hr.answered;
            totalRequired += specialProgress.hr.total;
        } else if (s.code === "health_products" && specialProgress?.hp) {
            totalAnswered += specialProgress.hp.answered;
            totalRequired += specialProgress.hp.total;
        } else {
            const c = getSectionCompletion(s.questions || [], responses);
            totalAnswered += c.answered;
            totalRequired += c.total;
        }
    });
    const yesCount = Object.values(responses).filter(v => v === "Yes").length;
    const scoredCount = Object.values(responses).filter(v => ["Yes", "No", "Partial"].includes(v)).length;
    const scoreEst = scoredCount > 0 ? Math.round((yesCount / scoredCount) * 100) : null;

    return (
        <div style={{ background: T.gradientDark, padding: "20px 20px 22px", position: "relative", overflow: "hidden", borderRadius: "24px 24px 28px 28px" }}>
            <div style={{ position: "absolute", width: 160, height: 160, borderRadius: "50%", background: "radial-gradient(circle, rgba(79,106,245,0.20) 0%, transparent 70%)", top: -50, right: -40 }} />
            <div style={{ position: "absolute", width: 80, height: 80, borderRadius: "50%", background: "radial-gradient(circle, rgba(108,99,255,0.14) 0%, transparent 70%)", bottom: -20, left: 10 }} />
            <BackButton onBack={onBack} light />
            <div style={{ marginTop: 14, display: "flex", alignItems: "center", gap: 12 }}>
                <CircleRing pct={overallPct} size={58} stroke={5} color="white" bg="rgba(255,255,255,0.2)">
                    <span style={{ color: "white", fontSize: 12, fontWeight: 900 }}>{overallPct}%</span>
                </CircleRing>
                <div>
                    <div style={{ color: "white", fontSize: 17, fontWeight: 800 }}>Assessment Progress</div>
                    <div style={{ color: "rgba(255,255,255,0.6)", fontSize: 12, marginTop: 2 }}>
                        {completedSections.size}/{sections.length} sections · {totalAnswered}/{totalRequired} answered
                    </div>
                </div>
            </div>
            <div style={{ display: "flex", gap: 8, marginTop: 14 }}>
                {[
                    { label: "Sections", value: `${completedSections.size}/${sections.length}` },
                    { label: "Questions", value: `${totalAnswered}/${totalRequired}` },
                    ...(scoreEst != null ? [{ label: "Est. Score", value: `${scoreEst}%` }] : []),
                ].map((s, i) => (
                    <div key={i} style={{ flex: 1, padding: "8px 10px", borderRadius: 12, background: "rgba(255,255,255,0.1)", border: "1px solid rgba(255,255,255,0.15)" }}>
                        <div style={{ color: "white", fontSize: 14, fontWeight: 900 }}>{s.value}</div>
                        <div style={{ color: "rgba(255,255,255,0.55)", fontSize: 10, marginTop: 1 }}>{s.label}</div>
                    </div>
                ))}
            </div>
        </div>
    );
}

// Questions in these sections show a comment field when answered "No" or "Partial"
const EXPLAIN_ON_NO_SECTIONS = ["infrastructure", "information_systems"];

// Detect the mortality question by question code or text
function isMortalityQuestion(q) {
    const code = (q.question_code ?? "").toLowerCase();
    const text = (q.question_text ?? "").toLowerCase();
    return code.includes("mortalit") || text.includes("mortalit");
}

// ── Section form with auto-save ───────────────────────────────────────────────
function SectionForm({ section, responses, explanations, onAnswer, onExplain, onBack, onSave, isLast, saving, assessmentId }) {
    const questions = (section.questions || []).filter(q => isQuestionVisible(q, responses));
    const completion = getSectionCompletion(section.questions || [], responses);
    const pct = completion.total > 0 ? Math.round((completion.answered / completion.total) * 100) : 0;
    const [g1, g2] = section.gradient ?? [section.color ?? "#6B7280", section.color ?? "#374151"];
    const [botTarget, setBotTarget] = useState(null);
    const [autoSaveStatus, setAutoSaveStatus] = useState("idle");
    const autoSaveTimer = useRef(null);

    // ── Auto-save: fires 30s after last change ─────────────────────────────────
    const triggerAutoSave = useCallback(async () => {
        if (!assessmentId || !section) return;
        setAutoSaveStatus("saving");
        try {
            const sectionResponses = {};
            const sectionExplanations = {};
            (section.questions ?? []).forEach(q => {
                const code = q.question_code;
                if (responses[code] !== undefined && responses[code] !== null && responses[code] !== "") {
                    sectionResponses[code] = responses[code];
                }
                if (explanations[code]) sectionExplanations[code] = explanations[code];
            });
            await api.responses.bulkSave(assessmentId, section.code, sectionResponses, sectionExplanations);
            setAutoSaveStatus("saved");
            setTimeout(() => setAutoSaveStatus("idle"), 3000);
        } catch {
            setAutoSaveStatus("error");
            setTimeout(() => setAutoSaveStatus("idle"), 4000);
        }
    }, [assessmentId, section, responses, explanations]);

    // Reset auto-save timer on every response change
    useEffect(() => {
        if (autoSaveTimer.current) clearTimeout(autoSaveTimer.current);
        autoSaveTimer.current = setTimeout(triggerAutoSave, AUTO_SAVE_DELAY);
        return () => clearTimeout(autoSaveTimer.current);
    }, [responses, explanations, triggerAutoSave]);

    const groups = questions.reduce((acc, q) => {
        const g = q.group || "__default";
        if (!acc[g]) acc[g] = [];
        acc[g].push(q);
        return acc;
    }, {});

    let globalIdx = 0;

    return (
        <div style={{ display: "flex", flexDirection: "column", height: "100%", position: "relative" }}>
            {/* Header */}
            <div style={{ background: `linear-gradient(135deg,${g1},${g2})`, padding: "20px 20px 22px", position: "relative", overflow: "hidden", borderRadius: "24px 24px 28px 28px" }}>
                <div style={{ position: "absolute", width: 120, height: 120, borderRadius: "50%", background: "rgba(255,255,255,0.07)", top: -30, right: -30 }} />
                <BackButton onBack={onBack} light />
                <AutoSavePill status={autoSaveStatus} />
                <div style={{ display: "flex", alignItems: "center", gap: 12, marginTop: 12 }}>
                    <div style={{ width: 48, height: 48, borderRadius: 15, background: "rgba(255,255,255,0.2)", border: "1.5px solid rgba(255,255,255,0.3)", display: "flex", alignItems: "center", justifyContent: "center" }}>
                        <SectionIcon code={section.code} size={24} />
                    </div>
                    <div style={{ flex: 1 }}>
                        <div style={{ color: "white", fontSize: 17, fontWeight: 800 }}>{section.name}</div>
                        <div style={{ color: "rgba(255,255,255,0.65)", fontSize: 12, marginTop: 1 }}>{section.description}</div>
                    </div>
                    <button onClick={() => setBotTarget("section")} style={{ width: 36, height: 36, borderRadius: 11, background: "rgba(255,255,255,0.15)", border: "1.5px solid rgba(255,255,255,0.3)", cursor: "pointer", fontSize: 18, display: "flex", alignItems: "center", justifyContent: "center" }} title="AI overview">🩺</button>
                </div>
                <div style={{ marginTop: 13 }}>
                    <div style={{ display: "flex", justifyContent: "space-between", marginBottom: 5 }}>
                        <span style={{ color: "rgba(255,255,255,0.7)", fontSize: 12 }}>Progress</span>
                        <span style={{ color: "white", fontSize: 12, fontWeight: 700 }}>{completion.answered}/{completion.total} · {pct}%</span>
                    </div>
                    <div style={{ height: 5, background: "rgba(255,255,255,0.2)", borderRadius: 999, overflow: "hidden" }}>
                        <div style={{ height: "100%", width: `${pct}%`, background: "white", borderRadius: 999, transition: "width 0.4s" }} />
                    </div>
                </div>
                <div style={{ display: "flex", gap: 6, marginTop: 8 }}>
                    {[25, 50, 75, 100].map(m => (
                        <div key={m} style={{ padding: "2px 8px", borderRadius: 20, fontSize: 10, fontWeight: 700, transition: "all 0.3s", background: pct >= m ? "rgba(255,255,255,0.25)" : "rgba(255,255,255,0.07)", border: `1px solid ${pct >= m ? "rgba(255,255,255,0.5)" : "rgba(255,255,255,0.15)"}`, color: pct >= m ? "white" : "rgba(255,255,255,0.3)" }}>
                            {m === 100 ? "✓ Done" : `${m}%`}
                        </div>
                    ))}
                </div>
            </div>

            {/* Questions */}
            <div style={{ flex: 1, overflowY: "auto", padding: "14px 16px 16px", background: T.bg }}>
                {questions.length === 0 ? (
                    <div style={{ textAlign: "center", padding: "40px 20px", color: T.textMuted }}>
                        <div style={{ fontSize: 40, marginBottom: 10 }}>✨</div>
                        <div style={{ fontWeight: 700 }}>No questions in this section</div>
                    </div>
                ) : (
                    Object.entries(groups).map(([groupName, groupQs]) => (
                        <div key={groupName}>
                            {groupName !== "__default" && (
                                <div style={{ fontSize: 11, fontWeight: 800, color: T.textMuted, textTransform: "uppercase", letterSpacing: 1.2, marginBottom: 10, marginTop: 6, display: "flex", alignItems: "center", gap: 8 }}>
                                    <div style={{ height: 1, flex: 1, background: T.border }} />
                                    {groupName}
                                    <div style={{ height: 1, flex: 1, background: T.border }} />
                                </div>
                            )}
                            {groupQs.map((q) => {
                                const idx = globalIdx++;
                                const explainOnNo = EXPLAIN_ON_NO_SECTIONS.includes(section.code);
                                // Check schema type (from cached API) or keyword match as fallback
                                const forceMortality = q.question_type === "mortality_three_month" ||
                                    (section.code === "information_systems" && isMortalityQuestion(q));
                                return (
                                    <QuestionCard
                                        key={q.id ?? q.question_code}
                                        question={q}
                                        value={responses[q.question_code]}
                                        explanation={explanations[q.question_code]}
                                        onAnswer={(v) => onAnswer(q.question_code, v)}
                                        onExplain={(v) => onExplain(q.question_code, v)}
                                        index={idx}
                                        onAskBot={(question) => setBotTarget(question)}
                                        explainOnNo={explainOnNo}
                                        forceMortality={forceMortality}
                                    />
                                );
                            })}
                        </div>
                    ))
                )}
            </div>

            {/* Save bar */}
            <div style={{ padding: "11px 16px", paddingBottom: "calc(18px + env(safe-area-inset-bottom, 0px))", background: T.card, borderTop: `1px solid ${T.border}`, boxShadow: "0 -4px 20px rgba(0,0,0,0.06)" }}>
                <div style={{ display: "flex", alignItems: "center", gap: 8, marginBottom: 10, padding: "7px 12px", background: pct === 100 ? "#D1FAE5" : T.borderLight, borderRadius: 10, border: `1px solid ${pct === 100 ? "#6EE7B7" : T.border}`, transition: "all 0.3s" }}>
                    <span style={{ fontSize: 14 }}>{pct === 100 ? "🎉" : "📝"}</span>
                    <span style={{ fontSize: 12, fontWeight: 600, color: pct === 100 ? "#065F46" : T.textMid }}>
                        {pct === 100 ? "All questions answered — ready to save!" : `${completion.total - completion.answered} question${completion.total - completion.answered !== 1 ? "s" : ""} remaining`}
                    </span>
                </div>
                <button onClick={() => onSave()} disabled={saving} style={{
                    width: "100%", padding: "13px", borderRadius: 13, border: "none",
                    background: saving ? T.border : T.gradientPrimary,
                    color: saving ? T.textMuted : "white", fontSize: 15, fontWeight: 800,
                    cursor: saving ? "default" : "pointer",
                    boxShadow: saving ? "none" : `0 5px 18px ${T.primaryGlow}`, transition: "all 0.2s",
                }}>
                    {saving ? "⏳ Saving…" : isLast ? "💾 Save & Finish" : "💾 Save & Continue →"}
                </button>
            </div>

            {botTarget && (
                <ChatbotPanel
                    question={botTarget === "section" ? null : botTarget}
                    sectionName={section.name}
                    onClose={() => setBotTarget(null)}
                />
            )}
        </div>
    );
}

// ── Main export ───────────────────────────────────────────────────────────────
export function AssessmentFormScreen({ user, sections, editAssessment, onBack, onComplete }) {
    const [view, setView] = useState("dashboard");
    const [activeSection, setActiveSection] = useState(null);
    const [activeSectionIdx, setActiveSectionIdx] = useState(0);
    const [responses, setResponses] = useState({});
    const [explanations, setExplanations] = useState({});
    const [completedSections, setCompletedSections] = useState(new Set());
    const [assessmentId, setAssessmentId] = useState(null);
    const [specialProgress, setSpecialProgress] = useState({ hr: null, hp: null });
    const [saving, setSaving] = useState(false);
    const [saveError, setSaveError] = useState(null);
    const [loadingResp, setLoadingResp] = useState(false);
    const [dashBotOpen, setDashBotOpen] = useState(false);

    // ── Reload HR/HP progress from offline store ──────────────────────────────
    const refreshSpecialProgress = useCallback(async (id) => {
        const prog = await computeSpecialProgress(id);
        setSpecialProgress(prog);
    }, []);

    // ── Bootstrap: load saved responses from API ───────────────────────────────
    useEffect(() => {
        if (!editAssessment) return;
        const id = editAssessment.id;
        setAssessmentId(id);
        refreshSpecialProgress(id);

        // Seed completed sections — only keys that exist in the sections list
        // (section_progress may contain extra keys like 'facility_assessor' not in schema)
        const progress = editAssessment.section_progress ?? {};
        const sectionCodes = new Set(sections.map(s => s.code));
        setCompletedSections(new Set(
            Object.entries(progress)
                .filter(([k, v]) => v === true && sectionCodes.has(k))
                .map(([k]) => k)
        ));

        // Load all saved responses — controller returns { responses, explanations, sectionProgress }
        setLoadingResp(true);
        api.responses.list(id)
            .then(data => {
                // Handle both shapes: { responses: {...} } and direct { "CODE": "value" }
                const r = data?.responses ?? data ?? {};
                const e = data?.explanations ?? {};
                setResponses(r);
                setExplanations(e);
            })
            .catch(err => console.error("Failed to load responses:", err))
            .finally(() => setLoadingResp(false));
    }, [editAssessment?.id]); // depend on id only — avoids re-fire when enrichAssessment recreates the object

    // Auto-save responses to IndexedDB as user types (1s debounce)
    useEffect(() => {
        if (!assessmentId || !Object.keys(responses).length) return;
        const timer = setTimeout(() => {
            offlineStore.saveResponses(assessmentId, { responses, explanations });
        }, 1000);
        return () => clearTimeout(timer);
    }, [responses, explanations, assessmentId]);

    const handleAnswer = (code, val) => setResponses(p => ({ ...p, [code]: val }));
    const handleExplain = (code, val) => setExplanations(p => ({ ...p, [code]: val }));

    // ── Save section ───────────────────────────────────────────────────────────
    const handleSectionSave = async () => {
        if (!activeSection || !assessmentId) return;
        setSaving(true);
        setSaveError(null);
        try {
            const sectionResponses = {};
            const sectionExplanations = {};
            (activeSection.questions ?? []).forEach(q => {
                const code = q.question_code;
                if (responses[code] !== undefined && responses[code] !== null && responses[code] !== "") {
                    sectionResponses[code] = responses[code];
                }
                if (explanations[code]) sectionExplanations[code] = explanations[code];
            });
            await api.responses.bulkSave(assessmentId, activeSection.code, sectionResponses, sectionExplanations);
            await api.assessments.updateSectionProgress(assessmentId, activeSection.code, true);
            setCompletedSections(p => new Set([...p, activeSection.code]));
            setView("dashboard");
        } catch (e) {
            console.error("Section save failed:", e);
            setSaveError(e.message || "Failed to save. Please retry.");
        } finally {
            setSaving(false);
        }
    };

    const handleSubmit = async () => {
        if (!assessmentId) return;
        setSaving(true);
        setSaveError(null);
        try {
            await api.assessments.submit(assessmentId);
            onComplete(assessmentId);
        } catch (e) {
            setSaveError(e.message || "Failed to submit. Please retry.");
            setSaving(false);
        }
    };

    const openSection = (section) => {
        setSaveError(null);
        setActiveSection(section);
        setActiveSectionIdx(sections.findIndex(s => s.id === section.id));
        setView("section");
    };

    // ── Section view ───────────────────────────────────────────────────────────
    if (view === "section" && activeSection) {
        const SpecialScreen = SPECIAL_SECTIONS[activeSection.code];
        if (SpecialScreen) {
            return (
                <SpecialScreen
                    assessment={editAssessment}
                    onBack={() => { setSaveError(null); setView("dashboard"); }}
                    onComplete={async () => {
                        await api.assessments.updateSectionProgress(assessmentId, activeSection.code, true).catch(() => { });
                        setCompletedSections(p => new Set([...p, activeSection.code]));
                        await refreshSpecialProgress(assessmentId);
                        setView("dashboard");
                    }}
                />
            );
        }
        return (
            <SectionForm
                section={activeSection}
                responses={responses}
                explanations={explanations}
                onAnswer={handleAnswer}
                onExplain={handleExplain}
                onBack={() => { setSaveError(null); setView("dashboard"); }}
                onSave={handleSectionSave}
                isLast={activeSectionIdx === sections.length - 1}
                saving={saving}
                assessmentId={assessmentId}
            />
        );
    }

    // ── Dashboard view ─────────────────────────────────────────────────────────
    const allDone = completedSections.size === sections.length && sections.length > 0;

    return (
        <div style={{ display: "flex", flexDirection: "column", height: "100%", position: "relative" }}>
            <ProgressHeader sections={sections} completedSections={completedSections} responses={responses} onBack={onBack} specialProgress={specialProgress} />

            {loadingResp && (
                <div style={{ padding: "10px 16px", background: "#EFF6FF", borderBottom: "1px solid #BFDBFE", fontSize: 12, color: "#1D4ED8", display: "flex", alignItems: "center", gap: 8 }}>
                    <span style={{ animation: "spin 1s linear infinite", display: "inline-block" }}>⏳</span>
                    <style>{`@keyframes spin{to{transform:rotate(360deg)}}`}</style>
                    Loading saved responses…
                </div>
            )}

            <div style={{ flex: 1, overflowY: "auto", padding: "14px 16px 100px", background: T.bg }}>
                {saveError && (
                    <div style={{ padding: "10px 14px", background: "#FEE2E2", borderRadius: 12, marginBottom: 12, border: "1.5px solid #FCA5A5", fontSize: 13, color: "#991B1B", display: "flex", alignItems: "center", gap: 8 }}>
                        <span>⚠️</span> {saveError}
                    </div>
                )}

                <RecommendationsPanel sections={sections} responses={responses} />

                {sections.map((s, i) => (
                    <SectionCard key={s.id ?? s.code} section={s} completedSections={completedSections} responses={responses} onOpen={openSection} index={i} specialProgress={specialProgress} />
                ))}

                {allDone && (
                    <div style={{ marginTop: 8, padding: "18px 16px", background: "linear-gradient(135deg,#D1FAE5,#ECFDF5)", borderRadius: 18, border: "2px solid #6EE7B7", textAlign: "center", animation: "celebIn 0.5s cubic-bezier(0.34,1.56,0.64,1)" }}>
                        <style>{`@keyframes celebIn{from{opacity:0;transform:scale(0.9)}to{opacity:1;transform:scale(1)}}`}</style>
                        <div style={{ fontSize: 36, marginBottom: 8 }}>🎉</div>
                        <div style={{ fontSize: 16, fontWeight: 800, color: "#064E3B", marginBottom: 4 }}>All Sections Complete!</div>
                        <div style={{ fontSize: 12, color: "#065F46", marginBottom: 16 }}>Review your responses before final submission.</div>
                        <button onClick={handleSubmit} disabled={saving} style={{
                            width: "100%", padding: "14px", borderRadius: 14, border: "none",
                            background: saving ? T.border : T.gradientPrimary,
                            color: saving ? T.textMuted : "white", fontSize: 15, fontWeight: 800,
                            cursor: saving ? "default" : "pointer",
                            boxShadow: saving ? "none" : `0 6px 20px ${T.primaryGlow}`,
                        }}>
                            {saving ? "⏳ Submitting…" : "🚀 Submit Assessment"}
                        </button>
                    </div>
                )}
            </div>

            <button onClick={() => setDashBotOpen(true)} style={{
                position: "absolute", bottom: "calc(24px + env(safe-area-inset-bottom, 0px))", right: 16, width: 50, height: 50, borderRadius: 16,
                background: "linear-gradient(135deg,#6C5CE7,#A29BFE)", border: "none",
                cursor: "pointer", fontSize: 22, display: "flex", alignItems: "center", justifyContent: "center",
                boxShadow: `0 5px 18px ${T.primaryGlow}`, zIndex: 50,
                animation: "pulseGlow 2.5s ease-in-out infinite",
            }}>
                🩺
                <style>{`@keyframes pulseGlow{0%,100%{box-shadow:0 5px 18px rgba(108,92,231,0.3)}50%{box-shadow:0 5px 28px rgba(108,92,231,0.55)}}`}</style>
            </button>

            {dashBotOpen && (
                <ChatbotPanel question={null} sectionName="MNCH Assessment" onClose={() => setDashBotOpen(false)} />
            )}
        </div>
    );
}
