import { useState } from "react";
import { T, GRADE_COLOR, GRADE_BG, GRADE_TEXT, GRADE_LABEL, calcGrade } from "../constants.js";
import { GradeBadge, StatusChip, ProgressBar } from "../components/shared-components.jsx";

// ─── Circular ring ────────────────────────────────────────────────────────────
function CircleRing({ pct, size = 64, stroke = 6, color, bg, children }) {
    const r = (size - stroke * 2) / 2;
    const circ = 2 * Math.PI * r;
    const offset = circ - (pct / 100) * circ;
    return (
        <div style={{ position: "relative", width: size, height: size, flexShrink: 0 }}>
            <svg width={size} height={size} style={{ transform: "rotate(-90deg)" }}>
                <circle cx={size / 2} cy={size / 2} r={r} fill="none" stroke={bg || "rgba(255,255,255,0.15)"} strokeWidth={stroke} />
                <circle
                    cx={size / 2} cy={size / 2} r={r} fill="none"
                    stroke={color || "white"} strokeWidth={stroke}
                    strokeDasharray={circ} strokeDashoffset={offset}
                    strokeLinecap="round"
                    style={{ transition: "stroke-dashoffset 0.8s cubic-bezier(0.34,1.56,0.64,1)" }}
                />
            </svg>
            <div style={{ position: "absolute", inset: 0, display: "flex", alignItems: "center", justifyContent: "center" }}>
                {children}
            </div>
        </div>
    );
}

// ─── Mini sparkline bar chart ─────────────────────────────────────────────────
function MiniBar({ values, color, height = 28 }) {
    if (!values.length) return null;
    const max = Math.max(...values, 1);
    return (
        <div style={{ display: "flex", alignItems: "flex-end", gap: 2, height }}>
            {values.map((v, i) => (
                <div key={i} style={{
                    flex: 1, borderRadius: "3px 3px 0 0",
                    background: i === values.length - 1 ? color : `${color}55`,
                    height: `${Math.max((v / max) * 100, 8)}%`,
                    transition: "height 0.5s",
                }} />
            ))}
        </div>
    );
}

// ─── Score donut widget ───────────────────────────────────────────────────────
function ScoreDonut({ assessments }) {
    const completed = assessments.filter(a => a.status === "completed");
    if (!completed.length) return null;

    const green = completed.filter(a => a.overall_grade === "green").length;
    const yellow = completed.filter(a => a.overall_grade === "yellow").length;
    const red = completed.filter(a => a.overall_grade === "red").length;
    const total = completed.length;

    const segments = [
        { label: "Good", count: green, color: "#10B981", bg: "#D1FAE5" },
        { label: "Fair", count: yellow, color: "#F59E0B", bg: "#FEF3C7" },
        { label: "Poor", count: red, color: "#EF4444", bg: "#FEE2E2" },
    ].filter(s => s.count > 0);

    return (
        <div style={{
            background: T.card, borderRadius: T.radius, padding: "14px 16px",
            boxShadow: T.shadow, marginBottom: 12,
        }}>
            <div style={{ fontSize: 12, fontWeight: 800, color: T.textMuted, textTransform: "uppercase", letterSpacing: 0.8, marginBottom: 12 }}>
                Score Distribution
            </div>
            <div style={{ display: "flex", alignItems: "center", gap: 16 }}>
                {/* Stacked bar */}
                <div style={{ flex: 1 }}>
                    <div style={{ height: 10, borderRadius: 999, overflow: "hidden", display: "flex", gap: 1 }}>
                        {segments.map((s, i) => (
                            <div key={i} style={{
                                flex: s.count, background: s.color,
                                transition: "flex 0.5s",
                            }} />
                        ))}
                        {total === 0 && <div style={{ flex: 1, background: T.border }} />}
                    </div>
                    <div style={{ display: "flex", gap: 10, marginTop: 8, flexWrap: "wrap" }}>
                        {segments.map((s, i) => (
                            <div key={i} style={{ display: "flex", alignItems: "center", gap: 5 }}>
                                <div style={{ width: 8, height: 8, borderRadius: 2, background: s.color }} />
                                <span style={{ fontSize: 11, color: T.textMid }}>
                                    {s.label} ({s.count})
                                </span>
                            </div>
                        ))}
                    </div>
                </div>
                {/* Count */}
                <div style={{ textAlign: "center", flexShrink: 0 }}>
                    <div style={{ fontSize: 28, fontWeight: 900, color: T.text }}>{total}</div>
                    <div style={{ fontSize: 10, color: T.textMuted }}>Scored</div>
                </div>
            </div>
        </div>
    );
}

// ─── Assessment card ───────────────────────────────────────────────────────────
const GRADE_ICONS = { green: "✅", yellow: "🟡", red: "🔴" };

function AssessmentCard({ assessment, onView, index }) {
    const pct = assessment.overall_percentage;
    const grade = assessment.overall_grade;
    const inProgress = assessment.status === "in_progress";

    const sectionsDone = assessment.section_progress
        ? Object.values(assessment.section_progress).filter(Boolean).length
        : 0;
    const sectionsTotal = assessment.section_progress
        ? Object.keys(assessment.section_progress).length
        : 0;
    const progressPct = sectionsTotal > 0 ? Math.round((sectionsDone / sectionsTotal) * 100) : 0;

    return (
        <button
            onClick={() => onView(assessment)}
            style={{
                width: "100%", background: T.card, borderRadius: T.radius,
                padding: 0, marginBottom: 10,
                border: `2px solid ${grade ? GRADE_BG[grade] : inProgress ? "#FEF3C7" : T.borderLight}`,
                cursor: "pointer", textAlign: "left",
                boxShadow: grade ? `0 4px 16px ${GRADE_COLOR[grade]}20` : T.shadow,
                overflow: "hidden",
                transition: "all 0.2s",
                animation: `cardIn 0.35s ease ${index * 0.06}s both`,
            }}>
            <style>{`@keyframes cardIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}`}</style>

            {/* Top stripe */}
            <div style={{
                height: 4,
                background: grade
                    ? `linear-gradient(90deg,${GRADE_COLOR[grade]},${GRADE_COLOR[grade]}88)`
                    : inProgress
                        ? "linear-gradient(90deg,#F59E0B,#FCD34D)"
                        : "linear-gradient(90deg,#E5E7EB,#F3F4F6)",
            }} />

            <div style={{ padding: "12px 16px" }}>
                <div style={{ display: "flex", alignItems: "flex-start", gap: 12 }}>
                    {/* Icon or score circle */}
                    {grade ? (
                        <CircleRing
                            pct={pct ?? 0} size={50} stroke={4}
                            color={GRADE_COLOR[grade]}
                            bg={GRADE_BG[grade]}
                        >
                            <span style={{ fontSize: 11, fontWeight: 900, color: GRADE_COLOR[grade] }}>
                                {pct}%
                            </span>
                        </CircleRing>
                    ) : (
                        <CircleRing
                            pct={progressPct} size={50} stroke={4}
                            color="#F59E0B"
                            bg="#FEF3C7"
                        >
                            <span style={{ fontSize: 11, fontWeight: 900, color: "#F59E0B" }}>
                                {progressPct}%
                            </span>
                        </CircleRing>
                    )}

                    {/* Content */}
                    <div style={{ flex: 1, minWidth: 0 }}>
                        <div style={{ fontSize: 14, fontWeight: 700, color: T.text, marginBottom: 2 }}>
                            {assessment.facility_name || "Facility Assessment"}
                        </div>
                        <div style={{ fontSize: 12, color: T.textMuted, marginBottom: 6 }}>
                            {[assessment.assessment_type, assessment.assessment_date].filter(Boolean).join(" · ")}
                        </div>

                        {/* Progress bar for in-progress */}
                        {inProgress && (
                            <div>
                                <div style={{ height: 3, background: T.borderLight, borderRadius: 999, overflow: "hidden" }}>
                                    <div style={{
                                        height: "100%", width: `${progressPct}%`,
                                        background: "linear-gradient(90deg,#F59E0B,#FBBF24)",
                                        borderRadius: 999, transition: "width 0.5s",
                                    }} />
                                </div>
                                <div style={{ fontSize: 10, color: T.textMuted, marginTop: 3 }}>
                                    {sectionsDone}/{sectionsTotal} sections completed
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Badge */}
                    <div style={{ flexShrink: 0 }}>
                        {grade ? <GradeBadge grade={grade} /> : <StatusChip status={assessment.status} />}
                    </div>
                </div>

                {/* County / subcounty */}
                {(assessment.county || assessment.mfl_code) && (
                    <div style={{
                        marginTop: 8, display: "flex", alignItems: "center", gap: 6,
                        padding: "5px 10px", background: T.borderLight, borderRadius: 8,
                    }}>
                        <span style={{ fontSize: 11 }}>📍</span>
                        <span style={{ fontSize: 11, color: T.textMid }}>
                            {[assessment.county, assessment.subcounty, assessment.mfl_code && `MFL: ${assessment.mfl_code}`]
                                .filter(Boolean).join(" · ")}
                        </span>
                    </div>
                )}
            </div>
        </button>
    );
}

// ─── Dashboard Screen ─────────────────────────────────────────────────────────
export function DashboardScreen({ user, assessments, onViewAssessment, loading, error, onRetry }) {
    const [filter, setFilter] = useState("all");
    const list = assessments ?? [];
    const completed = list.filter(a => a.status === "completed");
    const inProgress = list.filter(a => a.status === "in_progress");
    const avgScore = completed.length
        ? Math.round(completed.reduce((s, a) => s + (a.overall_percentage ?? 0), 0) / completed.length)
        : 0;
    const avgGrade = completed.length ? calcGrade(avgScore) : null;

    const filteredList = filter === "all" ? list
        : filter === "completed" ? completed
            : inProgress;

    // Trend data (dummy sparkline from scores)
    const trendData = completed.slice(-7).map(a => a.overall_percentage ?? 0);

    return (
        <div style={{ height: "100%", overflowY: "auto", background: T.bg }}>
            {/* ── Hero Header ── */}
            <div style={{
                background: "linear-gradient(160deg,#064E3B 0%,#065F46 60%,#047857 100%)",
                padding: "52px 20px 28px",
                position: "relative", overflow: "hidden",
            }}>
                {[{ s: 180, t: -50, r: -50, o: 0.05 }, { s: 100, b: -25, l: 15, o: 0.04 }].map((c, i) => (
                    <div key={i} style={{
                        position: "absolute", width: c.s, height: c.s, borderRadius: "50%",
                        background: "white", opacity: c.o,
                        top: c.t, right: c.r, bottom: c.b, left: c.l,
                    }} />
                ))}

                {/* Greeting */}
                <div style={{ color: "rgba(255,255,255,0.65)", fontSize: 13, marginBottom: 4 }}>
                    Good day 👋
                </div>
                <div style={{ color: "white", fontSize: 22, fontWeight: 900, lineHeight: 1.2, marginBottom: 2 }}>
                    {user?.name ?? "Assessor"}
                </div>
                {user?.facility && (
                    <div style={{ color: "rgba(255,255,255,0.55)", fontSize: 12, marginBottom: 16 }}>
                        📍 {user.facility}{user.county ? ` · ${user.county}` : ""}
                    </div>
                )}

                {/* Stats row */}
                <div style={{ display: "flex", gap: 8 }}>
                    {/* Average score ring */}
                    <div style={{
                        flex: 1.2, padding: "12px",
                        background: "rgba(255,255,255,0.1)",
                        backdropFilter: "blur(4px)",
                        borderRadius: 16, border: "1px solid rgba(255,255,255,0.15)",
                        display: "flex", alignItems: "center", gap: 10,
                    }}>
                        <CircleRing
                            pct={avgScore} size={52} stroke={5}
                            color={avgGrade ? GRADE_COLOR[avgGrade] : "rgba(255,255,255,0.5)"}
                            bg="rgba(255,255,255,0.15)"
                        >
                            <span style={{ color: "white", fontSize: 10, fontWeight: 900 }}>
                                {completed.length ? `${avgScore}%` : "—"}
                            </span>
                        </CircleRing>
                        <div>
                            <div style={{ color: "white", fontSize: 13, fontWeight: 800 }}>
                                Avg Score
                            </div>
                            <div style={{ color: "rgba(255,255,255,0.55)", fontSize: 10 }}>
                                {completed.length} scored
                            </div>
                            {trendData.length > 1 && (
                                <div style={{ marginTop: 4 }}>
                                    <MiniBar values={trendData} color="rgba(255,255,255,0.7)" height={18} />
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Quick stats */}
                    <div style={{ flex: 1, display: "flex", flexDirection: "column", gap: 8 }}>
                        {[
                            { label: "Total", value: list.length, color: "rgba(255,255,255,0.15)" },
                            { label: "In Progress", value: inProgress.length, color: "rgba(245,158,11,0.25)" },
                        ].map((stat, i) => (
                            <div key={i} style={{
                                flex: 1, padding: "8px 12px",
                                background: stat.color,
                                backdropFilter: "blur(4px)",
                                borderRadius: 12, border: "1px solid rgba(255,255,255,0.12)",
                                display: "flex", justifyContent: "space-between", alignItems: "center",
                            }}>
                                <span style={{ color: "rgba(255,255,255,0.7)", fontSize: 11 }}>{stat.label}</span>
                                <span style={{ color: "white", fontSize: 18, fontWeight: 900 }}>{stat.value}</span>
                            </div>
                        ))}
                    </div>
                </div>
            </div>

            {/* ── Content ── */}
            <div style={{ padding: "14px 16px 80px" }}>

                {/* Score distribution */}
                {completed.length > 0 && <ScoreDonut assessments={list} />}

                {/* Filter tabs */}
                <div style={{
                    display: "flex", gap: 6, marginBottom: 14,
                    background: T.card, borderRadius: 12, padding: 4,
                    boxShadow: T.shadow,
                }}>
                    {[
                        { key: "all", label: `All (${list.length})` },
                        { key: "in_progress", label: `Active (${inProgress.length})` },
                        { key: "completed", label: `Done (${completed.length})` },
                    ].map(tab => (
                        <button
                            key={tab.key}
                            onClick={() => setFilter(tab.key)}
                            style={{
                                flex: 1, padding: "8px 4px", borderRadius: 9,
                                border: "none", cursor: "pointer", fontSize: 11, fontWeight: 700,
                                background: filter === tab.key
                                    ? "linear-gradient(135deg,#064E3B,#059669)"
                                    : "none",
                                color: filter === tab.key ? "white" : T.textMuted,
                                transition: "all 0.2s",
                                boxShadow: filter === tab.key ? "0 2px 8px rgba(6,78,59,0.3)" : "none",
                            }}>
                            {tab.label}
                        </button>
                    ))}
                </div>

                {/* Error state */}
                {error && (
                    <div style={{
                        padding: "16px", background: "#FEF2F2", borderRadius: T.radius,
                        border: "1.5px solid #FCA5A5", marginBottom: 12,
                        textAlign: "center",
                    }}>
                        <div style={{ fontSize: 32, marginBottom: 8 }}>⚠️</div>
                        <div style={{ fontSize: 14, fontWeight: 700, color: "#991B1B", marginBottom: 8 }}>{error}</div>
                        <button
                            onClick={onRetry}
                            style={{
                                padding: "8px 20px", borderRadius: 10, border: "none",
                                background: "#EF4444", color: "white", fontSize: 13, fontWeight: 700,
                                cursor: "pointer",
                            }}>
                            Retry
                        </button>
                    </div>
                )}

                {/* Loading */}
                {loading && !error && (
                    <div style={{ textAlign: "center", padding: "40px 0" }}>
                        <div style={{
                            width: 44, height: 44, borderRadius: 14, margin: "0 auto 12px",
                            background: "linear-gradient(135deg,#064E3B,#059669)",
                            display: "flex", alignItems: "center", justifyContent: "center",
                            fontSize: 22, animation: "spin 2s linear infinite",
                        }}>⏳</div>
                        <style>{`@keyframes spin{from{transform:rotate(0deg)}to{transform:rotate(360deg)}}`}</style>
                        <div style={{ color: T.textMuted, fontSize: 13 }}>Loading assessments…</div>
                    </div>
                )}

                {/* Empty state */}
                {!loading && !error && filteredList.length === 0 && (
                    <div style={{
                        textAlign: "center", padding: "48px 24px",
                        background: T.card, borderRadius: T.radius, boxShadow: T.shadow,
                    }}>
                        <div style={{ fontSize: 52, marginBottom: 14 }}>
                            {filter === "completed" ? "🏆" : filter === "in_progress" ? "📋" : "📭"}
                        </div>
                        <div style={{ fontSize: 16, fontWeight: 700, color: T.textMid, marginBottom: 8 }}>
                            {filter === "completed" ? "No completed assessments"
                                : filter === "in_progress" ? "No active assessments"
                                    : "No assessments yet"}
                        </div>
                        <div style={{ fontSize: 13, color: T.textMuted, lineHeight: 1.6 }}>
                            {filter !== "all"
                                ? `Switch to "All" to see all assessments.`
                                : "Contact your MNCH administrator to have assessments assigned to your account."}
                        </div>
                    </div>
                )}

                {/* Assessment list */}
                {!loading && filteredList.map((a, i) => (
                    <AssessmentCard
                        key={a.id}
                        assessment={a}
                        onView={onViewAssessment}
                        index={i}
                    />
                ))}

                {/* Performance tip */}
                {completed.length > 0 && (
                    <div style={{
                        marginTop: 4, padding: "14px 16px",
                        background: "linear-gradient(135deg,#EEF2FF,#E0E7FF)",
                        borderRadius: T.radius,
                        border: "1.5px solid #C7D2FE",
                        display: "flex", alignItems: "flex-start", gap: 10,
                    }}>
                        <span style={{ fontSize: 20, flexShrink: 0 }}>💡</span>
                        <div>
                            <div style={{ fontSize: 12, fontWeight: 700, color: "#3730A3", marginBottom: 3 }}>
                                Performance Tip
                            </div>
                            <div style={{ fontSize: 11, color: "#4338CA", lineHeight: 1.5 }}>
                                {avgScore >= 80
                                    ? "Excellent performance! Facilities are meeting MNCH standards well."
                                    : avgScore >= 50
                                        ? "Fair performance. Focus on infrastructure and human resource gaps to improve scores."
                                        : "Significant improvements needed. Prioritize critical sections for immediate action."}
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}
