import { useState } from "react";
import { T, GRADE_COLOR, GRADE_BG, GRADE_LABEL, calcGrade } from "../constants.js";
import { GradeBadge, ProgressBar } from "../components/shared-components.jsx";

export function ReportsScreen({ user, assessments, sectionAverages, loading, onViewAssessment, onViewEmailJobs }) {
    const [selectedFacility, setSelectedFacility] = useState(null);
    const list = assessments ?? [];
    const completed = list.filter(a => a.status === "completed");
    const avgScore = completed.length
        ? Math.round(completed.reduce((s, a) => s + (Number(a.overall_percentage) || 0), 0) / completed.length)
        : 0;

    const gradeDistribution = ["green", "yellow", "red"].map(g => ({
        grade: g,
        count: completed.filter(a => a.overall_grade === g).length,
    }));

    return (
        <div style={{ height: "100%", overflowY: "auto", background: T.bg }}>
            {/* Header */}
            <div style={{
                background: T.gradientDark,
                padding: "52px 20px 24px",
                borderRadius: "24px 24px 28px 28px",
                position: "relative", overflow: "hidden",
            }}>
                <div style={{ position: "absolute", width: 150, height: 150, borderRadius: "50%", background: "radial-gradient(circle, rgba(16,185,129,0.12) 0%, transparent 70%)", top: -30, right: -30 }} />
                <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between" }}>
                    <div style={{ color: "white", fontSize: 22, fontWeight: 800, letterSpacing: -0.3, animation: "fadeInUp 0.4s ease both" }}>Reports</div>
                    {onViewEmailJobs && (
                        <button onClick={onViewEmailJobs} style={{ background: "rgba(255,255,255,0.12)", border: "1px solid rgba(255,255,255,0.2)", borderRadius: 10, padding: "7px 12px", color: "white", fontSize: 12, fontWeight: 700, cursor: "pointer", display: "flex", alignItems: "center", gap: 6 }}>
                            📧 Email Jobs
                        </button>
                    )}
                </div>
                <div style={{ color: "rgba(255,255,255,0.45)", fontSize: 13, marginTop: 3, fontWeight: 500, animation: "fadeInUp 0.4s ease 0.05s both" }}>
                    {user?.facility ?? user?.county ?? ""}
                </div>
            </div>

            <div style={{ padding: "16px 16px 20px" }}>
                {loading && (
                    <div style={{ textAlign: "center", padding: "40px 0", color: T.textMuted }}>
                        <div style={{ width: 44, height: 44, borderRadius: 14, margin: "0 auto 12px", background: T.gradientPrimary, display: "flex", alignItems: "center", justifyContent: "center", boxShadow: `0 6px 20px ${T.primaryGlow}` }}>
                            <svg width="22" height="22" viewBox="0 0 24 24" style={{ animation: "spin 1s linear infinite" }}>
                                <circle cx="12" cy="12" r="10" fill="none" stroke="rgba(255,255,255,0.25)" strokeWidth="3" />
                                <path d="M12 2a10 10 0 019.95 9" fill="none" stroke="white" strokeWidth="3" strokeLinecap="round" />
                            </svg>
                        </div>
                        <div style={{ fontSize: 13, fontWeight: 500 }}>Loading…</div>
                    </div>
                )}

                {!loading && completed.length === 0 && (
                    <div style={{ textAlign: "center", padding: "50px 24px", color: T.textMuted, background: "white", borderRadius: T.radius, boxShadow: T.shadowCard, border: `1px solid ${T.border}` }}>
                        <div style={{ fontSize: 56, marginBottom: 16 }}>📊</div>
                        <div style={{ fontSize: 17, fontWeight: 700, color: T.textMid, marginBottom: 8 }}>No completed assessments</div>
                        <div style={{ fontSize: 13, color: T.textSub, lineHeight: 1.6 }}>Reports will appear here after assessments are completed.</div>
                    </div>
                )}

                {!loading && completed.length > 0 && (
                    <>
                        {/* Summary card */}
                        <div style={{ background: "white", borderRadius: T.radius, padding: 18, marginBottom: 14, boxShadow: T.shadowCard, border: `1px solid ${T.border}`, animation: "fadeInUp 0.4s ease both" }}>
                            <div style={{ fontSize: 12, fontWeight: 700, color: T.textMuted, marginBottom: 14, textTransform: "uppercase", letterSpacing: 0.8, display: "flex", alignItems: "center", gap: 6 }}>
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke={T.primary} strokeWidth="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2" /></svg>
                                Summary
                            </div>
                            <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr 1fr", gap: 12 }}>
                                {[
                                    { label: "Completed", value: completed.length, color: T.success, bg: T.successGhost },
                                    { label: "Avg Score", value: !isNaN(avgScore) ? `${avgScore}%` : "—", color: T.primary, bg: T.primaryGhost },
                                    { label: "Total", value: list.length, color: T.accent, bg: T.accentGhost },
                                ].map(({ label, value, color, bg }, i) => (
                                    <div key={label} style={{ textAlign: "center", background: bg, borderRadius: 14, padding: "14px 8px", animation: `fadeInUp 0.4s ease ${0.1 + i * 0.05}s both` }}>
                                        <div style={{ fontSize: 24, fontWeight: 800, color }}>{value}</div>
                                        <div style={{ fontSize: 11, color: T.textMuted, marginTop: 3, fontWeight: 500 }}>{label}</div>
                                    </div>
                                ))}
                            </div>
                        </div>

                        {/* Grade distribution */}
                        <div style={{ background: "white", borderRadius: T.radius, padding: 18, marginBottom: 14, boxShadow: T.shadowCard, border: `1px solid ${T.border}`, animation: "fadeInUp 0.4s ease 0.1s both" }}>
                            <div style={{ fontSize: 12, fontWeight: 700, color: T.textMuted, marginBottom: 14, textTransform: "uppercase", letterSpacing: 0.8, display: "flex", alignItems: "center", gap: 6 }}>
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke={T.primary} strokeWidth="2"><line x1="18" y1="20" x2="18" y2="10" /><line x1="12" y1="20" x2="12" y2="4" /><line x1="6" y1="20" x2="6" y2="14" /></svg>
                                Grade Distribution
                            </div>
                            {gradeDistribution.map(({ grade, count }, i) => (
                                <div key={grade} style={{ marginBottom: 14, animation: `fadeInUp 0.4s ease ${0.15 + i * 0.05}s both` }}>
                                    <div style={{ display: "flex", justifyContent: "space-between", marginBottom: 6, alignItems: "center" }}>
                                        <div style={{ display: "flex", alignItems: "center", gap: 8 }}>
                                            <div style={{ width: 10, height: 10, borderRadius: 3, background: GRADE_COLOR[grade], boxShadow: `0 0 6px ${GRADE_COLOR[grade]}33` }} />
                                            <span style={{ fontSize: 13, fontWeight: 600, color: T.textMid }}>{GRADE_LABEL[grade]}</span>
                                        </div>
                                        <span style={{ fontSize: 13, color: T.textSub, fontWeight: 600 }}>{count} / {completed.length}</span>
                                    </div>
                                    <ProgressBar pct={completed.length > 0 ? Math.round((count / completed.length) * 100) : 0} color={GRADE_COLOR[grade]} height={8} />
                                </div>
                            ))}
                        </div>

                        {/* Section averages — scored sections only */}
                        {sectionAverages && sectionAverages.length > 0 && (() => {
                            const SPECIAL = ["human_resources", "health_products"];
                            const scored = sectionAverages.filter(s => !SPECIAL.includes(s.code));
                            const special = sectionAverages.filter(s => SPECIAL.includes(s.code));
                            return (
                                <>
                                    {scored.length > 0 && (
                                        <div style={{ background: "white", borderRadius: T.radius, padding: 18, marginBottom: 14, boxShadow: T.shadowCard, border: `1px solid ${T.border}`, animation: "fadeInUp 0.4s ease 0.2s both" }}>
                                            <div style={{ fontSize: 12, fontWeight: 700, color: T.textMuted, marginBottom: 14, textTransform: "uppercase", letterSpacing: 0.8, display: "flex", alignItems: "center", gap: 6 }}>
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke={T.primary} strokeWidth="2"><circle cx="12" cy="12" r="10" /><polyline points="12 6 12 12 16 14" /></svg>
                                                Section Averages
                                            </div>
                                            {scored.map((s, i) => {
                                                const pctColor = s.average_pct >= 80 ? "#10B981" : s.average_pct >= 50 ? "#F59E0B" : "#EF4444";
                                                return (
                                                    <div key={s.code} style={{ marginBottom: i < scored.length - 1 ? 14 : 0, animation: `fadeInUp 0.4s ease ${0.25 + i * 0.04}s both` }}>
                                                        <div style={{ display: "flex", alignItems: "center", gap: 8, marginBottom: 6 }}>
                                                            <span style={{ fontSize: 16, width: 30, height: 30, borderRadius: 8, background: T.borderLight, display: "flex", alignItems: "center", justifyContent: "center" }}>{s.icon}</span>
                                                            <span style={{ fontSize: 13, fontWeight: 600, color: T.text, flex: 1 }}>{s.name}</span>
                                                            <span style={{ fontSize: 13, fontWeight: 700, color: pctColor, background: pctColor === "#10B981" ? "#D1FAE5" : pctColor === "#F59E0B" ? "#FEF3C7" : "#FEE2E2", padding: "3px 10px", borderRadius: 8 }}>
                                                                {s.average_pct}%
                                                            </span>
                                                        </div>
                                                        <ProgressBar pct={s.average_pct} color={pctColor} height={6} />
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    )}

                                    {special.length > 0 && (
                                        <div style={{ background: "white", borderRadius: T.radius, padding: 18, marginBottom: 14, boxShadow: T.shadowCard, border: `1px solid ${T.border}`, animation: "fadeInUp 0.4s ease 0.22s both" }}>
                                            <div style={{ fontSize: 12, fontWeight: 700, color: T.textMuted, marginBottom: 14, textTransform: "uppercase", letterSpacing: 0.8, display: "flex", alignItems: "center", gap: 6 }}>
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke={T.primary} strokeWidth="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2" /><circle cx="9" cy="7" r="4" /><path d="M23 21v-2a4 4 0 00-3-3.87" /><path d="M16 3.13a4 4 0 010 7.75" /></svg>
                                                Support Sections
                                            </div>
                                            {special.map((s, i) => {
                                                const isHr = s.code === "human_resources";
                                                const accentColor = isHr ? "#F59E0B" : "#EF4444";
                                                const accentBg = isHr ? "#FEF3C7" : "#FEE2E2";
                                                const accentText = isHr ? "#92400E" : "#991B1B";
                                                return (
                                                    <div key={s.code} style={{
                                                        display: "flex", alignItems: "center", gap: 12,
                                                        padding: "12px 14px", borderRadius: 14,
                                                        background: accentBg,
                                                        border: `1px solid ${accentColor}33`,
                                                        marginBottom: i < special.length - 1 ? 10 : 0,
                                                        animation: `fadeInUp 0.4s ease ${0.27 + i * 0.04}s both`,
                                                    }}>
                                                        <span style={{ fontSize: 22 }}>{s.icon}</span>
                                                        <div style={{ flex: 1 }}>
                                                            <div style={{ fontSize: 13, fontWeight: 700, color: accentText }}>{s.name}</div>
                                                            <div style={{ fontSize: 11, color: accentText, opacity: 0.7, marginTop: 2 }}>
                                                                {isHr ? "Tracked by cadre count — not scored" : "Tracked by commodity availability — not scored"}
                                                            </div>
                                                        </div>
                                                        <div style={{ padding: "4px 12px", borderRadius: 10, background: "rgba(255,255,255,0.6)", border: `1px solid ${accentColor}44` }}>
                                                            <span style={{ fontSize: 11, fontWeight: 700, color: accentText }}>{completed.length} assessed</span>
                                                        </div>
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    )}
                                </>
                            );
                        })()}

                        {/* Facility Reports List */}
                        <div style={{ animation: "fadeInUp 0.4s ease 0.25s both" }}>
                            <div style={{ fontSize: 12, fontWeight: 700, color: T.textMuted, textTransform: "uppercase", letterSpacing: 0.8, marginBottom: 12, display: "flex", alignItems: "center", gap: 6 }}>
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke={T.primary} strokeWidth="2">
                                    <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z" /><polyline points="14 2 14 8 20 8" />
                                </svg>
                                Facility Reports
                            </div>
                            {completed.map((a, i) => {
                                const grade = a.overall_grade;
                                const pct = (a.overall_percentage != null && !isNaN(Number(a.overall_percentage))) ? Number(a.overall_percentage).toFixed(1) : "—";
                                const gradeColor = GRADE_COLOR[grade] ?? T.textMuted;
                                const gradeBg = GRADE_BG[grade] ?? T.borderLight;
                                return (
                                    <button
                                        key={a.id}
                                        onClick={() => onViewAssessment?.(a)}
                                        style={{
                                            width: "100%", background: "white", borderRadius: T.radius,
                                            padding: "14px 16px", marginBottom: 10,
                                            border: `1.5px solid ${gradeBg}`,
                                            cursor: "pointer", textAlign: "left",
                                            boxShadow: `0 4px 16px ${gradeColor}14`,
                                            display: "flex", alignItems: "center", gap: 12,
                                            overflow: "hidden", position: "relative",
                                            animation: `fadeInUp 0.4s ease ${i * 0.05}s both`,
                                        }}
                                    >
                                        {/* Score badge */}
                                        <div style={{
                                            width: 52, height: 52, borderRadius: 14, flexShrink: 0,
                                            background: `linear-gradient(135deg, ${gradeColor}22, ${gradeColor}11)`,
                                            border: `2px solid ${gradeColor}33`,
                                            display: "flex", flexDirection: "column", alignItems: "center", justifyContent: "center",
                                        }}>
                                            <div style={{ fontSize: 16, fontWeight: 900, color: gradeColor, lineHeight: 1 }}>{pct}%</div>
                                            <div style={{ fontSize: 9, fontWeight: 700, color: gradeColor, textTransform: "uppercase", marginTop: 2 }}>{GRADE_LABEL[grade] ?? ""}</div>
                                        </div>
                                        {/* Info */}
                                        <div style={{ flex: 1, minWidth: 0 }}>
                                            <div style={{ fontWeight: 700, fontSize: 13, color: T.text, overflow: "hidden", textOverflow: "ellipsis", whiteSpace: "nowrap" }}>
                                                {a.facility_name}
                                            </div>
                                            <div style={{ fontSize: 11, color: T.textMuted, marginTop: 2 }}>
                                                {[a.assessment_type, a.assessment_date].filter(Boolean).join(" · ")}
                                            </div>
                                            {(a.county || a.mfl_code) && (
                                                <div style={{ fontSize: 10, color: T.textSub, marginTop: 2 }}>
                                                    {[a.mfl_code && `MFL: ${a.mfl_code}`, a.county].filter(Boolean).join(" · ")}
                                                </div>
                                            )}
                                        </div>
                                        {/* Arrow */}
                                        <div style={{ display: "flex", flexDirection: "column", alignItems: "flex-end", gap: 4, flexShrink: 0 }}>
                                            <GradeBadge grade={grade} />
                                            <span style={{ fontSize: 18, color: T.textMuted }}>›</span>
                                        </div>
                                    </button>
                                );
                            })}
                        </div>
                    </>
                )}
            </div>
        </div>
    );
}
