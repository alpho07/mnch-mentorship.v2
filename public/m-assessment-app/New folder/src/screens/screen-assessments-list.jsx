import { useState } from "react";
import { T, GRADE_COLOR, GRADE_BG } from "../constants.js";
import { GradeBadge, StatusChip, ProgressBar } from "../components/shared-components.jsx";

export function AssessmentsListScreen({ assessments, sections, onView, loading }) {
    const [filter, setFilter] = useState("all");

    const list = filter === "all"
        ? (assessments || [])
        : (assessments || []).filter(a => a.status === filter);

    return (
        <div style={{ height: "100%", overflowY: "auto", background: T.bg }}>
            <div style={{ background: "linear-gradient(135deg, #0F172A 0%, #1E293B 100%)", padding: "52px 20px 20px" }}>
                <div style={{ color: "white", fontSize: 20, fontWeight: 800 }}>My Assessments</div>
                <div style={{ color: "rgba(255,255,255,0.5)", fontSize: 13, marginTop: 2 }}>{(assessments || []).length} total</div>
                <div style={{ display: "flex", gap: 8, marginTop: 14 }}>
                    {["all", "completed", "in_progress"].map(f => (
                        <button key={f} onClick={() => setFilter(f)} style={{
                            padding: "6px 13px", borderRadius: 20,
                            background: filter === f ? "white" : "rgba(255,255,255,0.1)",
                            color: filter === f ? "#0F172A" : "rgba(255,255,255,0.7)",
                            border: "none", fontSize: 12, fontWeight: 600, cursor: "pointer",
                        }}>
                            {f === "all" ? "All" : f === "completed" ? "Completed" : "In Progress"}
                        </button>
                    ))}
                </div>
            </div>

            <div style={{ padding: "14px 16px 100px" }}>
                {loading && (
                    <div style={{ textAlign: "center", padding: "30px 0", color: T.textMuted }}>
                        <div style={{ fontSize: 30, marginBottom: 8 }}>⏳</div>Loading…
                    </div>
                )}
                {!loading && list.length === 0 && (
                    <div style={{ textAlign: "center", padding: "50px 24px", color: T.textMuted }}>
                        <div style={{ fontSize: 56, marginBottom: 16 }}>📋</div>
                        <div style={{ fontSize: 17, fontWeight: 700, color: T.textMid, marginBottom: 8 }}>No assessments yet</div>
                        <div style={{ fontSize: 13, color: T.textSub, lineHeight: 1.6 }}>
                            Your assessments will appear here once assigned by an administrator.
                        </div>
                    </div>
                )}
                {!loading && list.map(a => (
                    <button key={a.id} onClick={() => onView(a)} style={{
                        width: "100%", background: T.card, borderRadius: T.radius,
                        padding: "14px 16px", marginBottom: 10,
                        border: `2px solid ${a.overall_grade ? GRADE_BG[a.overall_grade] : T.borderLight}`,
                        cursor: "pointer", textAlign: "left", boxShadow: T.shadow,
                    }}>
                        <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start" }}>
                            <div style={{ flex: 1, minWidth: 0 }}>
                                <div style={{ fontWeight: 700, fontSize: 14, color: T.text, overflow: "hidden", textOverflow: "ellipsis", whiteSpace: "nowrap" }}>
                                    {a.facility_name}
                                </div>
                                <div style={{ fontSize: 12, color: T.textSub, marginTop: 3 }}>{a.assessment_type} · {a.assessment_date}</div>
                            </div>
                            <div style={{ marginLeft: 10, flexShrink: 0 }}>
                                {a.overall_grade
                                    ? <GradeBadge grade={a.overall_grade} pct={a.overall_percentage} />
                                    : <StatusChip status={a.status} />
                                }
                            </div>
                        </div>
                        {a.status === "completed" && sections && sections.length > 0 && (
                            <div style={{ display: "flex", gap: 6, marginTop: 10 }}>
                                {sections.map(s => {
                                    const sc = (a.section_scores || {})[s.code];
                                    return (
                                        <div key={s.code} style={{ flex: 1, textAlign: "center" }}>
                                            <div style={{ fontSize: 10, marginBottom: 3 }}>{s.icon}</div>
                                            <ProgressBar pct={sc ? sc.percentage : 0} color={sc ? GRADE_COLOR[sc.grade] : T.border} height={4} />
                                        </div>
                                    );
                                })}
                            </div>
                        )}
                    </button>
                ))}
            </div>
        </div>
    );
}
