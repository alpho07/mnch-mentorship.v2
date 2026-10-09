import { useEffect, useState } from "react";
import { T, GRADE_COLOR } from "../constants.js";
import api from "../services/api.service.js";

const INSIGHT_STYLE = {
    danger:  { bg: "#FEF2F2", border: "#FECACA", fg: "#991B1B", icon: "🔴" },
    warning: { bg: "#FFFBEB", border: "#FDE68A", fg: "#92400E", icon: "🟠" },
    success: { bg: "#ECFDF5", border: "#A7F3D0", fg: "#065F46", icon: "🟢" },
    info:    { bg: "#EFF6FF", border: "#BFDBFE", fg: "#1E40AF", icon: "🔵" },
};

const pctColor = (p) => (p >= 80 ? "#10B981" : p >= 50 ? "#F59E0B" : "#EF4444");

function Card({ title, children }) {
    return (
        <div style={{ background: T.card, borderRadius: 16, marginBottom: 14, border: `1px solid ${T.borderLight}`, boxShadow: T.shadow, overflow: "hidden" }}>
            <div style={{ padding: "11px 16px", fontSize: 12, fontWeight: 800, color: T.textMid, textTransform: "uppercase", letterSpacing: 0.7, borderBottom: `1px solid ${T.borderLight}` }}>{title}</div>
            <div style={{ padding: "12px 16px" }}>{children}</div>
        </div>
    );
}

function Bar({ label, pct, right }) {
    const p = Math.max(0, Math.min(100, Number(pct) || 0));
    return (
        <div style={{ marginBottom: 10 }}>
            <div style={{ display: "flex", justifyContent: "space-between", gap: 8, fontSize: 12, marginBottom: 4 }}>
                <span style={{ color: T.text, fontWeight: 600, minWidth: 0 }}>{label}</span>
                <span style={{ color: pctColor(p), fontWeight: 800, flexShrink: 0 }}>{right ?? `${p.toFixed(1)}%`}</span>
            </div>
            <div style={{ height: 6, background: T.borderLight, borderRadius: 999, overflow: "hidden" }}>
                <div style={{ height: "100%", width: `${p}%`, background: pctColor(p), borderRadius: 999 }} />
            </div>
        </div>
    );
}

/**
 * Executive report for a completed assessment — the same summary the web
 * executive dashboard shows (insights, scores, HR, commodities, indicators,
 * data quality), with a PDF download.
 */
export function ExecutiveReport({ assessment }) {
    const [data, setData] = useState(null);
    const [error, setError] = useState(null);
    const [loading, setLoading] = useState(true);
    const [pdfBusy, setPdfBusy] = useState(false);
    const [msg, setMsg] = useState(null);

    useEffect(() => {
        let active = true;
        setLoading(true);
        api.reports.executive(assessment.id)
            .then((d) => { if (active) setData(d); })
            .catch((e) => { if (active) setError(e?.message || "Could not load the executive report."); })
            .finally(() => { if (active) setLoading(false); });
        return () => { active = false; };
    }, [assessment.id]);

    const downloadPdf = async () => {
        setPdfBusy(true);
        setMsg(null);
        try {
            const res = await api.reports.executivePdf(assessment.id);
            if (res?.download_url) window.open(res.download_url, "_blank");
            else setMsg("PDF link not available.");
        } catch (e) {
            setMsg(e?.message || "PDF generation failed.");
        } finally {
            setPdfBusy(false);
        }
    };

    if (loading) return <div style={{ textAlign: "center", padding: "36px 16px", color: T.textMuted, fontSize: 13 }}>Loading executive report…</div>;
    if (error) return <div style={{ padding: "12px 14px", background: "#FEF3C7", borderRadius: 12, fontSize: 12, color: "#92400E", border: "1px solid #FDE68A" }}>⚠️ {error}</div>;
    if (!data) return null;

    const hr = data.human_resources ?? {};
    const com = data.commodities ?? {};
    const dq = data.data_quality ?? {};
    const insights = [...(data.insights ?? []), ...(data.indicator_insights ?? [])];
    const topCadreGaps = [...(hr.cadres ?? [])].filter((c) => c.total_in_facility > 0).sort((a, b) => a.coverage_pct - b.coverage_pct).slice(0, 5);

    return (
        <div>
            <button onClick={downloadPdf} disabled={pdfBusy} style={{
                width: "100%", padding: "12px", marginBottom: 12, borderRadius: 13, border: "none",
                background: pdfBusy ? T.border : T.gradientPrimary, color: pdfBusy ? T.textMuted : "white",
                fontSize: 13, fontWeight: 800, cursor: pdfBusy ? "default" : "pointer",
            }}>
                {pdfBusy ? "⏳ Generating…" : "📄 Download Executive Report (PDF)"}
            </button>
            {msg && <div style={{ marginBottom: 12, fontSize: 12, color: "#991B1B" }}>{msg}</div>}
            {data.previous_round && (
                <div style={{ marginBottom: 12, fontSize: 11, color: T.textMuted }}>Compared with previous round: {data.previous_round}</div>
            )}

            {insights.length > 0 && (
                <Card title="Executive Insights">
                    {insights.map((i, idx) => {
                        const st = INSIGHT_STYLE[i.type] ?? INSIGHT_STYLE.info;
                        return (
                            <div key={idx} style={{ background: st.bg, border: `1px solid ${st.border}`, borderRadius: 10, padding: "8px 10px", marginBottom: 8 }}>
                                <div style={{ fontSize: 10, fontWeight: 800, color: st.fg, textTransform: "uppercase", marginBottom: 2 }}>{st.icon} {i.area}</div>
                                <div style={{ fontSize: 12, color: st.fg, lineHeight: 1.45 }}>{i.text}</div>
                            </div>
                        );
                    })}
                </Card>
            )}

            {(data.section_scores ?? []).length > 0 && (
                <Card title="Section Scores">
                    {data.section_scores.map((s) => (
                        <Bar key={s.code} label={s.name} pct={s.percentage}
                            right={`${Number(s.percentage).toFixed(1)}% · ${GRADE_COLOR[s.grade] ? s.grade : ""}`.replace(/ · $/, "")} />
                    ))}
                </Card>
            )}

            {(hr.total_staff ?? 0) > 0 && (
                <Card title="Human Resources">
                    <Bar label={`Trained staff (${hr.total_trained}/${hr.total_staff})`} pct={hr.coverage_pct} />
                    {topCadreGaps.length > 0 && <div style={{ fontSize: 11, color: T.textMuted, margin: "4px 0 8px" }}>Lowest training coverage</div>}
                    {topCadreGaps.map((c) => (
                        <Bar key={c.cadre} label={`${c.cadre} (${c.total_trained}/${c.total_in_facility})`} pct={c.coverage_pct} />
                    ))}
                </Card>
            )}

            {(com.departments ?? []).length > 0 && (
                <Card title={`Health Products · ${Number(com.overall_pct ?? 0).toFixed(1)}% available`}>
                    {com.departments.map((d) => (
                        <Bar key={d.department} label={d.department} pct={d.percentage} right={`${d.available}/${d.total} · ${Number(d.percentage).toFixed(0)}%`} />
                    ))}
                </Card>
            )}

            {(data.indicator_metrics ?? []).length > 0 && (
                <Card title="Indicators">
                    {data.indicator_metrics.map((m, idx) => {
                        const st = INSIGHT_STYLE[m.status] ?? INSIGHT_STYLE.info;
                        return (
                            <div key={idx} style={{ display: "flex", justifyContent: "space-between", gap: 10, padding: "7px 0", borderBottom: `1px solid ${T.borderLight}` }}>
                                <div style={{ minWidth: 0 }}>
                                    <div style={{ fontSize: 10, fontWeight: 700, color: T.textMuted, textTransform: "uppercase" }}>{m.group}</div>
                                    <div style={{ fontSize: 12, color: T.text }}>{m.label}</div>
                                    <div style={{ fontSize: 10, color: T.textMuted }}>{m.numerator}/{m.denominator}</div>
                                </div>
                                <div style={{ flexShrink: 0, fontSize: 14, fontWeight: 900, color: st.fg }}>{m.pct != null ? `${m.pct}%` : "—"}</div>
                            </div>
                        );
                    })}
                </Card>
            )}

            {(dq.sections ?? []).length > 0 && (
                <Card title={`Data Quality · ${Number(dq.overall_completeness ?? 0).toFixed(0)}% complete`}>
                    {dq.sections.map((s) => (
                        <Bar key={s.code} label={s.name} pct={s.percentage} right={s.display} />
                    ))}
                    {(dq.insights ?? []).map((i, idx) => {
                        const st = INSIGHT_STYLE[i.type] ?? INSIGHT_STYLE.info;
                        return <div key={idx} style={{ fontSize: 11, color: st.fg, background: st.bg, border: `1px solid ${st.border}`, borderRadius: 8, padding: "6px 8px", marginTop: 6 }}>{i.text}</div>;
                    })}
                </Card>
            )}
        </div>
    );
}
