import { T } from "../constants.js";

// ─── Yes / No / Partial ───────────────────────────────────────────────────────
export function YesNoInput({ question, value, onChange }) {
    const opts = question.question_type === "yes_no_partial"
        ? ["Yes", "Partial", "No"]
        : ["Yes", "No"];
    const colors = { Yes: "#10B981", Partial: "#F59E0B", No: "#EF4444" };
    const bgs = { Yes: "#D1FAE5", Partial: "#FEF3C7", No: "#FEE2E2" };
    const icons = { Yes: "✓", Partial: "◐", No: "✗" };

    return (
        <div style={{ display: "flex", gap: 6, marginTop: 8 }}>
            {opts.map((o) => {
                const active = value === o;
                return (
                    <button key={o} onClick={() => onChange(o)} style={{
                        flex: 1,
                        padding: "7px 4px",
                        borderRadius: 10,
                        border: `2px solid ${active ? colors[o] : T.border}`,
                        background: active ? bgs[o] : T.borderLight,
                        color: active ? colors[o] : T.textMuted,
                        fontWeight: active ? 800 : 500,
                        fontSize: 12,
                        cursor: "pointer",
                        display: "flex",
                        alignItems: "center",
                        justifyContent: "center",
                        gap: 5,
                        transition: "all 0.15s cubic-bezier(0.34,1.56,0.64,1)",
                        transform: active ? "scale(1.03)" : "scale(1)",
                        boxShadow: active ? `0 3px 10px ${colors[o]}33` : "none",
                    }}>
                        <span style={{
                            width: 18, height: 18, borderRadius: "50%",
                            background: active ? colors[o] : T.border,
                            color: "white",
                            display: "flex", alignItems: "center", justifyContent: "center",
                            fontSize: 10, fontWeight: 900, flexShrink: 0,
                        }}>{icons[o]}</span>
                        <span>{o}</span>
                    </button>
                );
            })}
        </div>
    );
}

// ─── Proportion ───────────────────────────────────────────────────────────────
export function ProportionInput({ value, onChange }) {
    const num = Math.min(Math.max(parseFloat(value) || 0, 0), 100);
    const color = num >= 80 ? "#10B981" : num >= 50 ? "#F59E0B" : "#EF4444";
    const bg = num >= 80 ? "#D1FAE5" : num >= 50 ? "#FEF3C7" : "#FEE2E2";

    return (
        <div style={{ marginTop: 10 }}>
            <div style={{ position: "relative", height: 6, background: T.borderLight, borderRadius: 999, marginBottom: 10 }}>
                <div style={{
                    position: "absolute", left: 0, top: 0, height: "100%",
                    width: `${num}%`, background: color, borderRadius: 999,
                    transition: "width 0.2s",
                }} />
                <input type="range" min={0} max={100} value={num}
                    onChange={(e) => onChange(parseFloat(e.target.value))}
                    style={{ position: "absolute", inset: 0, opacity: 0, width: "100%", cursor: "pointer", margin: 0 }}
                />
            </div>
            <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
                <div style={{
                    padding: "5px 12px", borderRadius: 10,
                    background: bg, color: color, fontSize: 20, fontWeight: 900,
                    minWidth: 64, textAlign: "center",
                }}>{num}<span style={{ fontSize: 12 }}>%</span></div>
                <input type="number" min={0} max={100} value={num}
                    onChange={(e) => onChange(Math.min(100, Math.max(0, parseFloat(e.target.value) || 0)))}
                    style={{
                        padding: "7px 10px", borderRadius: 10, width: 68,
                        border: `2px solid ${T.border}`, fontSize: 14, fontWeight: 700,
                        color: T.text, outline: "none", background: T.borderLight, textAlign: "center",
                    }}
                />
            </div>
        </div>
    );
}

// ─── Select / Radio ───────────────────────────────────────────────────────────
export function SelectInput({ question, value, onChange }) {
    const opts = Array.isArray(question.options) ? question.options : [];
    return (
        <div style={{ marginTop: 10, display: "flex", flexDirection: "column", gap: 6 }}>
            {opts.map((opt) => {
                const active = value === opt;
                return (
                    <button key={opt} onClick={() => onChange(opt)} style={{
                        padding: "10px 13px", borderRadius: 11,
                        border: `2px solid ${active ? T.primary : T.border}`,
                        background: active ? `linear-gradient(135deg,${T.primaryGhost},rgba(108,92,231,0.08))` : T.borderLight,
                        color: active ? T.primaryDark : T.textMid,
                        fontWeight: active ? 700 : 400, fontSize: 13,
                        cursor: "pointer", textAlign: "left",
                        display: "flex", alignItems: "center", gap: 10,
                        transition: "all 0.15s",
                        boxShadow: active ? `0 2px 10px ${T.primaryGlow}` : "none",
                    }}>
                        <div style={{
                            width: 16, height: 16, borderRadius: "50%",
                            border: active ? `5px solid ${T.primary}` : `2px solid ${T.border}`,
                            background: "white", flexShrink: 0,
                        }} />
                        {opt}
                    </button>
                );
            })}
        </div>
    );
}

// ─── Number ───────────────────────────────────────────────────────────────────
export function NumberInput({ value, onChange }) {
    return (
        <div style={{ marginTop: 10, display: "flex", alignItems: "center", gap: 8 }}>
            <button onClick={() => onChange(Math.max(0, (parseInt(value) || 0) - 1))}
                style={{ width: 36, height: 36, borderRadius: 10, border: `2px solid ${T.border}`, background: T.borderLight, fontSize: 20, cursor: "pointer", color: T.textMid, display: "flex", alignItems: "center", justifyContent: "center" }}>−</button>
            <input type="number" min={0} value={value || ""} onChange={(e) => onChange(e.target.value)}
                placeholder="0"
                style={{ padding: "8px 12px", borderRadius: 10, width: 80, border: `2px solid ${T.border}`, fontSize: 18, fontWeight: 700, color: T.text, outline: "none", background: T.borderLight, fontFamily: "inherit", textAlign: "center" }} />
            <button onClick={() => onChange((parseInt(value) || 0) + 1)}
                style={{ width: 36, height: 36, borderRadius: 10, border: `2px solid ${T.border}`, background: T.borderLight, fontSize: 20, cursor: "pointer", color: T.textMid, display: "flex", alignItems: "center", justifyContent: "center" }}>+</button>
        </div>
    );
}

// ─── Text / Date ──────────────────────────────────────────────────────────────
export function TextInput({ question, value, onChange }) {
    return (
        <input
            type={question.question_type === "date" ? "date" : "text"}
            value={value || ""}
            onChange={(e) => onChange(e.target.value)}
            placeholder={question.placeholder || "Type your answer…"}
            style={{
                marginTop: 10, padding: "11px 14px", borderRadius: 10,
                border: `2px solid ${T.border}`, fontSize: 14, color: T.text,
                outline: "none", width: "100%", boxSizing: "border-box",
                background: T.borderLight, fontFamily: "inherit",
            }}
            onFocus={e => e.target.style.borderColor = T.primary}
            onBlur={e => e.target.style.borderColor = T.border}
        />
    );
}

// ─── Explanation textarea ─────────────────────────────────────────────────────
export function ExplanationInput({ label, value, onChange }) {
    return (
        <div style={{
            marginTop: 10, padding: "10px 12px",
            background: "linear-gradient(135deg,#FFFBEB,#FEF3C7)",
            borderRadius: 10, border: "1.5px solid #FDE68A",
        }}>
            <div style={{ fontSize: 11, color: "#92400E", marginBottom: 5, fontWeight: 700, display: "flex", alignItems: "center", gap: 5 }}>
                <span>📝</span> {label || "Comments / Recommendations"}
            </div>
            <textarea
                value={value || ""}
                onChange={(e) => onChange(e.target.value)}
                rows={2}
                placeholder="Add comments, observations or recommendations…"
                style={{
                    width: "100%", padding: "8px 10px", borderRadius: 8,
                    border: "1.5px solid #FDE68A", fontSize: 13, color: "#78350F",
                    outline: "none", resize: "none", boxSizing: "border-box",
                    background: "rgba(255,255,255,0.6)", fontFamily: "inherit",
                }}
            />
        </div>
    );
}

// ─── Recommendation chip ──────────────────────────────────────────────────────
function RecommendationChip({ value }) {
    const map = {
        Yes: { label: "Meeting Standard", icon: "✅", bg: "#D1FAE5", color: "#065F46", border: "#6EE7B7" },
        No: { label: "Needs Improvement", icon: "⚠️", bg: "#FEE2E2", color: "#991B1B", border: "#FCA5A5" },
        Partial: { label: "Partially Meeting", icon: "🔶", bg: "#FEF3C7", color: "#92400E", border: "#FCD34D" },
    };
    const rec = map[value];
    if (!rec)
        return null;
    return (
        <div style={{
            marginTop: 7, padding: "5px 10px",
            background: rec.bg, border: `1.5px solid ${rec.border}`,
            borderRadius: 8, display: "flex", alignItems: "center", gap: 6,
            animation: "recIn 0.2s ease",
        }}>
            <style>{`@keyframes recIn{from{opacity:0;transform:translateY(-3px)}to{opacity:1;transform:translateY(0)}}`
            }</style>
            <span style={{ fontSize: 12 }}>{rec.icon}</span>
            <span style={{ fontSize: 11, fontWeight: 700, color: rec.color }}>{rec.label}</span>
        </div>
    );
}

// ─── Mortality three-month input ───────────────────────────────────────────────
// Stores value as JSON string: '{"aug_2025":5,"sep_2025":3,"oct_2025":7}'
export function MortalityThreeMonthInput({ value, onChange }) {
    let parsed = {};
    try { parsed = JSON.parse(value || "{}"); } catch { /* ignore */ }

    const MONTHS = [
        { key: "aug_2025", label: "August 2025" },
        { key: "sep_2025", label: "September 2025" },
        { key: "oct_2025", label: "October 2025" },
    ];

    const update = (key, val) => {
        const next = { ...parsed, [key]: Math.max(0, val) };
        onChange(JSON.stringify(next));
    };

    return (
        <div style={{ marginTop: 10, display: "flex", flexDirection: "column", gap: 6 }}>
            {MONTHS.map(m => {
                const n = parseInt(parsed[m.key] ?? 0, 10) || 0;
                return (
                    <div key={m.key} style={{
                        display: "flex", justifyContent: "space-between", alignItems: "center",
                        padding: "10px 12px", background: T.borderLight, borderRadius: 10,
                        border: `1.5px solid ${n > 0 ? "#FCA5A5" : T.border}`,
                    }}>
                        <div style={{ fontSize: 13, fontWeight: 600, color: T.textMid }}>{m.label}</div>
                        <div style={{ display: "flex", alignItems: "center", gap: 6 }}>
                            <button onClick={() => update(m.key, n - 1)}
                                style={{ width: 30, height: 30, borderRadius: 8, border: `1.5px solid ${T.border}`, background: "white", fontSize: 18, cursor: "pointer", display: "flex", alignItems: "center", justifyContent: "center", color: T.textMid }}>−</button>
                            <input
                                type="number" min="0" value={n || ""}
                                onChange={e => update(m.key, parseInt(e.target.value, 10) || 0)}
                                placeholder="0"
                                style={{
                                    width: 58, textAlign: "center", padding: "5px 0",
                                    border: `1.5px solid ${T.border}`, borderRadius: 8,
                                    fontSize: 15, fontWeight: 700, color: T.text,
                                    background: "white", outline: "none",
                                }}
                            />
                            <button onClick={() => update(m.key, n + 1)}
                                style={{ width: 30, height: 30, borderRadius: 8, border: `1.5px solid ${T.border}`, background: "white", fontSize: 18, cursor: "pointer", display: "flex", alignItems: "center", justifyContent: "center", color: T.textMid }}>+</button>
                        </div>
                    </div>
                );
            })}
            {Object.values(parsed).some(v => v > 0) && (
                <div style={{ fontSize: 11, color: T.textMuted, textAlign: "right", paddingRight: 4 }}>
                    Total: {Object.values(parsed).reduce((a, b) => a + (parseInt(b) || 0), 0)} mortalities
                </div>
            )}
        </div>
    );
}

// ─── QuestionCard ─────────────────────────────────────────────────────────────
// onAskBot(question) — parent owns the ChatbotPanel so it renders above all overflow
// explainOnNo — if true, show explanation textarea whenever answer is "No"
// forceMortality — if true, override input with MortalityThreeMonthInput
export function QuestionCard({ question, value, explanation, onAnswer, onExplain, index, onAskBot, explainOnNo, forceMortality }) {
    const hasValue = value !== undefined && value !== "" && value !== null;
    // requires_explanation_on is an array from the API, e.g. ["No"] or ["No","Partial"]
    const needsExplain =
        (Array.isArray(question.requires_explanation_on) && question.requires_explanation_on.includes(value)) ||
        (explainOnNo && (value === "No" || value === "Partial"));

    const renderInput = () => {
        // forceMortality is a prop-based override; question_type check covers schema-cached type
        if (forceMortality || question.question_type === "mortality_three_month") {
            return <MortalityThreeMonthInput value={value} onChange={onAnswer} />;
        }
        switch (question.question_type) {
            case "yes_no":
            case "yes_no_partial":
                return <YesNoInput question={question} value={value} onChange={onAnswer} />;
            case "proportion":
                return <ProportionInput value={value} onChange={onAnswer} />;
            case "select":
            case "radio":
                return <SelectInput question={question} value={value} onChange={onAnswer} />;
            case "number":
                return <NumberInput value={value} onChange={onAnswer} />;
            default:
                return <TextInput question={question} value={value} onChange={onAnswer} />;
        }
    };

    return (
        <div style={{
            background: T.card,
            borderRadius: T.radius,
            padding: "14px 15px",
            marginBottom: 10,
            border: `2px solid ${hasValue ? "#6EE7B7" : T.borderLight}`,
            boxShadow: hasValue ? "0 3px 14px rgba(16,185,129,0.09)" : T.shadow,
            transition: "border-color 0.2s, box-shadow 0.2s",
            position: "relative",
        }}>
            {/* Answered left accent */}
            {hasValue && (
                <div style={{
                    position: "absolute", left: 0, top: 8, bottom: 8, width: 4,
                    background: "linear-gradient(180deg,#10B981,#059669)",
                    borderRadius: "0 3px 3px 0",
                }} />
            )}

            {/* Header row */}
            <div style={{ display: "flex", gap: 9, alignItems: "flex-start" }}>
                <div style={{
                    minWidth: 24, height: 24, borderRadius: 7,
                    background: hasValue ? "linear-gradient(135deg,#D1FAE5,#A7F3D0)" : T.borderLight,
                    border: `1.5px solid ${hasValue ? "#6EE7B7" : T.border}`,
                    display: "flex", alignItems: "center", justifyContent: "center",
                    fontSize: hasValue ? 12 : 10, fontWeight: 700,
                    color: hasValue ? "#059669" : T.textMuted, flexShrink: 0,
                    transition: "all 0.2s",
                }}>{hasValue ? "✓" : index + 1}</div>

                <div style={{ flex: 1 }}>
                    <div style={{ fontSize: 13, fontWeight: 600, color: T.text, lineHeight: 1.5 }}>
                        {question.question_text}
                        {question.is_required && (
                            <span style={{ color: "#EF4444", marginLeft: 5, fontSize: 10, background: "#FEE2E2", padding: "1px 5px", borderRadius: 4, fontWeight: 700 }}>Required</span>
                        )}
                    </div>
                    {question.help_text && (
                        <div style={{ fontSize: 11, color: T.textMuted, marginTop: 4, padding: "4px 8px", background: T.borderLight, borderRadius: 6, borderLeft: `3px solid ${T.border}`, lineHeight: 1.45 }}>
                            {question.help_text}
                        </div>
                    )}
                </div>

                {/* AI explain button — fires callback so parent renders the overlay */}
                {onAskBot && (
                    <button
                        onClick={() => onAskBot(question)}
                        title="Get AI explanation for this question"
                        style={{
                            flexShrink: 0, width: 30, height: 30, borderRadius: 9,
                            border: "1.5px solid #BBF7D0",
                            background: "linear-gradient(135deg,#F0FDF4,#ECFDF5)",
                            cursor: "pointer", fontSize: 14,
                            display: "flex", alignItems: "center", justifyContent: "center",
                            color: "#059669", transition: "all 0.15s",
                            boxShadow: "0 1px 4px rgba(16,185,129,0.12)",
                        }}
                        onMouseEnter={e => e.currentTarget.style.transform = "scale(1.12)"}
                        onMouseLeave={e => e.currentTarget.style.transform = "scale(1)"}
                    >🩺</button>
                )}
            </div>

            {renderInput()}

            {["yes_no", "yes_no_partial"].includes(question.question_type) && (
                <RecommendationChip value={value} />
            )}

            {needsExplain && (
                <ExplanationInput
                    label={question.explanation_label}
                    value={explanation}
                    onChange={onExplain}
                />
            )}
        </div>
    );
}
