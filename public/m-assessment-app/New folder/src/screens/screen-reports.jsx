import { T, GRADE_COLOR, GRADE_BG, GRADE_LABEL } from "../constants.js";
import { ProgressBar } from "../components/shared-components.jsx";

export function ReportsScreen({ user, assessments, sectionAverages, loading }) {
    const list = assessments ?? [];
    const completed = list.filter(a => a.status === "completed");
    const avgScore = completed.length
        ? Math.round(completed.reduce((s, a) => s + (a.overall_percentage ?? 0), 0) / completed.length)
        : 0;

    const gradeDistribution = ["green", "yellow", "red"].map(g => ({
        grade: g,
        count: completed.filter(a => a.overall_grade === g).length,
    }));

    return (
        <div style={{ height: "100%", overflowY: "auto", background: T.bg }}>
            {/* Header */}
            <div style={{
                background: "linear-gradient(135deg, #1E1B4B 0%, #4338CA 100%)",
                padding: "52px 20px 24px",
            }}>
                <div style={{ color: "white", fontSize: 20, fontWeight: 800 }}>Reports</div>
                <div style={{ color: "rgba(255,255,255,0.5)", fontSize: 13, marginTop: 2 }}>
                    {user?.facility ?? user?.county ?? ""}
                </div>
            </div>

            <div style={{ padding: "14px 16px 100px" }}>

                {loading && (
                    <div style={{ textAlign: "center", padding: "30px 0", color: T.textMuted }}>
                        <div style={{ fontSize: 30, marginBottom: 8 }}>⏳</div>Loading…
                    </div>
                )}

                {!loading && completed.length === 0 && (
                    <div style={{ textAlign: "center", padding: "50px 24px", color: T.textMuted }}>
                        <div style={{ fontSize: 56, marginBottom: 16 }}>📊</div>
                        <div style={{ fontSize: 17, fontWeight: 700, color: T.textMid, marginBottom: 8 }}>No completed assessments</div>
                        <div style={{ fontSize: 13, color: T.textSub, lineHeight: 1.6 }}>
                            Reports will appear here after assessments are completed.
                        </div>
                    </div>
                )}

                {!loading && completed.length > 0 && (
                    <>
                        {/* Summary card */}
                        <div style={{ background: T.card, borderRadius: T.radius, padding: 16, marginBottom: 14, boxShadow: T.shadow }}>
                            <div style={{ fontSize: 13, fontWeight: 700, color: T.textMid, marginBottom: 12 }}>Summary</div>
                            <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr 1fr", gap: 12 }}>
                                {[
                                    { label: "Completed", value: completed.length },
                                    { label: "Avg Score", value: `${avgScore}%` },
                                    { label: "Total", value: list.length },
                                ].map(({ label, value }) => (
                                    <div key={label} style={{ textAlign: "center" }}>
                                        <div style={{ fontSize: 24, fontWeight: 900, color: T.primary }}>{value}</div>
                                        <div style={{ fontSize: 11, color: T.textMuted, marginTop: 2 }}>{label}</div>
                                    </div>
                                ))}
                            </div>
                        </div>

                        {/* Grade distribution */}
                        <div style={{ background: T.card, borderRadius: T.radius, padding: 16, marginBottom: 14, boxShadow: T.shadow }}>
                            <div style={{ fontSize: 13, fontWeight: 700, color: T.textMid, marginBottom: 12 }}>Grade Distribution</div>
                            {gradeDistribution.map(({ grade, count }) => (
                                <div key={grade} style={{ marginBottom: 12 }}>
                                    <div style={{ display: "flex", justifyContent: "space-between", marginBottom: 4 }}>
                                        <span style={{ fontSize: 13, fontWeight: 600, color: GRADE_COLOR[grade] }}>{GRADE_LABEL[grade]}</span>
                                        <span style={{ fontSize: 13, color: T.textSub }}>{count} / {completed.length}</span>
                                    </div>
                                    <ProgressBar
                                        pct={completed.length > 0 ? Math.round((count / completed.length) * 100) : 0}
                                        color={GRADE_COLOR[grade]}
                                        height={8}
                                    />
                                </div>
                            ))}
                        </div>

                        {/* Section averages */}
                        {sectionAverages && sectionAverages.length > 0 && (
                            <div style={{ background: T.card, borderRadius: T.radius, padding: 16, boxShadow: T.shadow }}>
                                <div style={{ fontSize: 13, fontWeight: 700, color: T.textMid, marginBottom: 12 }}>Section Averages</div>
                                {sectionAverages.map(s => (
                                    <div key={s.code} style={{ marginBottom: 12 }}>
                                        <div style={{ display: "flex", alignItems: "center", gap: 8, marginBottom: 5 }}>
                                            <span style={{ fontSize: 16 }}>{s.icon}</span>
                                            <span style={{ fontSize: 13, fontWeight: 600, color: T.text, flex: 1 }}>{s.name}</span>
                                            <span style={{ fontSize: 13, fontWeight: 700, color: s.average_pct >= 80 ? "#10B981" : s.average_pct >= 50 ? "#F59E0B" : "#EF4444" }}>
                                                {s.average_pct}%
                                            </span>
                                        </div>
                                        <ProgressBar
                                            pct={s.average_pct}
                                            color={s.average_pct >= 80 ? "#10B981" : s.average_pct >= 50 ? "#F59E0B" : "#EF4444"}
                                            height={6}
                                        />
                                    </div>
                                ))}
                            </div>
                        )}
                    </>
                )}
            </div>
        </div>
    );
}
