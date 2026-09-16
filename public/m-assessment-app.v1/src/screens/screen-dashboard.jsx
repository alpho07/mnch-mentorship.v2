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
                <circle cx={size / 2} cy={size / 2} r={r} fill="none" stroke={bg || "rgba(255,255,255,0.12)"} strokeWidth={stroke} />
                <circle
                    cx={size / 2} cy={size / 2} r={r} fill="none"
                    stroke={color || "white"} strokeWidth={stroke}
                    strokeDasharray={circ} strokeDashoffset={offset}
                    strokeLinecap="round"
                    style={{ transition: "stroke-dashoffset 1s cubic-bezier(0.34,1.56,0.64,1)" }}
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
        <div style={{ display: "flex", alignItems: "flex-end", gap: 3, height }}>
            {values.map((v, i) => (
                <div key={i} style={{
                    flex: 1, borderRadius: "4px 4px 0 0",
                    background: i === values.length - 1 ? color : `${color}44`,
                    height: `${Math.max((v / max) * 100, 8)}%`,
                    transition: "height 0.6s cubic-bezier(0.4,0,0.2,1)",
                    transitionDelay: `${i * 0.05}s`,
                }} />
            ))}
        </div>
    );
}

// ─── Action points insight ────────────────────────────────────────────────────
function ActionPointsHint({ assessments, onView }) {
    const completed = assessments.filter(a => a.status === "completed");
    if (!completed.length) return null;

    let critCount = 0, warnCount = 0;
    const sectionTally = {};
    completed.forEach(a => {
        Object.entries(a.section_scores ?? {}).forEach(([code, sc]) => {
            if (sc.grade === "red") {
                critCount++;
                sectionTally[code] = (sectionTally[code] ?? 0) + 2;
            } else if (sc.grade === "yellow") {
                warnCount++;
                sectionTally[code] = (sectionTally[code] ?? 0) + 1;
            }
        });
    });

    if (critCount === 0 && warnCount === 0) return null;

    // Most-impacted section name
    const SECTION_NAMES = {
        infrastructure: "Infrastructure", skills_lab: "Skills Lab",
        information_systems: "Information Systems", quality_of_care: "Quality of Care",
    };
    const topCode = Object.entries(sectionTally).sort((a, b) => b[1] - a[1])[0]?.[0];
    const topName = topCode ? (SECTION_NAMES[topCode] ?? topCode) : null;

    // Link to lowest-scoring completed assessment
    const worst = [...completed].sort((a, b) =>
        (Number(a.overall_percentage) || 0) - (Number(b.overall_percentage) || 0)
    )[0];

    const hasCrit = critCount > 0;
    const accentColor = hasCrit ? "#DC2626" : "#D97706";
    const accentBg   = hasCrit ? "#FEF2F2" : "#FFFBEB";
    const accentBorder = hasCrit ? "#FECACA" : "#FDE68A";
    const iconColor  = hasCrit ? "#EF4444" : "#F59E0B";

    return (
        <div style={{
            marginBottom: 14,
            background: accentBg,
            borderRadius: T.radius,
            border: `1px solid ${accentBorder}`,
            padding: "12px 14px",
            display: "flex", alignItems: "center", gap: 12,
            animation: "fadeInUp 0.4s ease 0.08s both",
            boxShadow: `0 2px 12px ${iconColor}12`,
        }}>
            {/* Pulsing dot icon */}
            <div style={{ position: "relative", flexShrink: 0 }}>
                <div style={{
                    width: 36, height: 36, borderRadius: 12,
                    background: `${iconColor}18`,
                    display: "flex", alignItems: "center", justifyContent: "center",
                }}>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke={iconColor} strokeWidth="2" strokeLinecap="round">
                        <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                        <line x1="12" y1="9" x2="12" y2="13" /><line x1="12" y1="17" x2="12.01" y2="17" />
                    </svg>
                </div>
                {hasCrit && (
                    <div style={{
                        position: "absolute", top: -2, right: -2,
                        width: 10, height: 10, borderRadius: "50%",
                        background: "#EF4444", border: "2px solid white",
                        animation: "pulse 1.8s ease infinite",
                    }} />
                )}
            </div>

            {/* Text */}
            <div style={{ flex: 1, minWidth: 0 }}>
                <div style={{ fontSize: 12, fontWeight: 700, color: accentColor, marginBottom: 2 }}>
                    {critCount > 0 && warnCount > 0
                        ? `${critCount} critical · ${warnCount} partial`
                        : critCount > 0
                            ? `${critCount} critical issue${critCount > 1 ? "s" : ""} found`
                            : `${warnCount} area${warnCount > 1 ? "s" : ""} need attention`}
                </div>
                <div style={{ fontSize: 11, color: accentColor, opacity: 0.7, overflow: "hidden", textOverflow: "ellipsis", whiteSpace: "nowrap" }}>
                    {topName ? `Most impacted: ${topName}` : "Review reports for details"}
                </div>
            </div>

            {/* CTA */}
            {worst && (
                <button
                    onClick={() => onView(worst)}
                    style={{
                        flexShrink: 0, padding: "7px 13px",
                        borderRadius: 10, border: `1px solid ${accentBorder}`,
                        background: "white", color: accentColor,
                        fontSize: 11, fontWeight: 700, cursor: "pointer",
                        display: "flex", alignItems: "center", gap: 4,
                        boxShadow: `0 2px 8px ${iconColor}18`,
                        transition: "all 0.18s",
                    }}
                >
                    Review
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round">
                        <polyline points="9 18 15 12 9 6" />
                    </svg>
                </button>
            )}
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
            background: "white", borderRadius: T.radius, padding: "16px 18px",
            boxShadow: T.shadowCard, marginBottom: 14,
            border: `1px solid ${T.border}`,
        }}>
            <div style={{
                fontSize: 12, fontWeight: 700, color: T.textMuted,
                textTransform: "uppercase", letterSpacing: 0.8, marginBottom: 14,
                display: "flex", alignItems: "center", gap: 6,
            }}>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke={T.primary} strokeWidth="2">
                    <path d="M21.21 15.89A10 10 0 118 2.83" /><path d="M22 12A10 10 0 0012 2v10z" />
                </svg>
                Score Distribution
            </div>
            <div style={{ display: "flex", alignItems: "center", gap: 16 }}>
                {/* Stacked bar */}
                <div style={{ flex: 1 }}>
                    <div style={{ height: 12, borderRadius: 999, overflow: "hidden", display: "flex", gap: 2 }}>
                        {segments.map((s, i) => (
                            <div key={i} style={{
                                flex: s.count,
                                background: `linear-gradient(135deg, ${s.color}, ${s.color}CC)`,
                                transition: "flex 0.5s",
                                borderRadius: 999,
                            }} />
                        ))}
                        {total === 0 && <div style={{ flex: 1, background: T.border }} />}
                    </div>
                    <div style={{ display: "flex", gap: 12, marginTop: 10, flexWrap: "wrap" }}>
                        {segments.map((s, i) => (
                            <div key={i} style={{ display: "flex", alignItems: "center", gap: 5 }}>
                                <div style={{ width: 8, height: 8, borderRadius: 3, background: s.color }} />
                                <span style={{ fontSize: 12, color: T.textMid, fontWeight: 500 }}>
                                    {s.label} ({s.count})
                                </span>
                            </div>
                        ))}
                    </div>
                </div>
                {/* Count */}
                <div style={{
                    textAlign: "center", flexShrink: 0,
                    background: T.primaryGhost, borderRadius: 14, padding: "10px 14px",
                }}>
                    <div style={{ fontSize: 28, fontWeight: 800, color: T.primary }}>{total}</div>
                    <div style={{ fontSize: 10, color: T.textMuted, fontWeight: 500 }}>Scored</div>
                </div>
            </div>
        </div>
    );
}

// ─── Assessment card ───────────────────────────────────────────────────────────
function AssessmentCard({ assessment, onView, index }) {
    const rawPct = assessment.overall_percentage;
    const pct = rawPct != null && !isNaN(Number(rawPct)) ? Number(rawPct) : null;
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
                width: "100%", background: "white", borderRadius: T.radius,
                padding: 0, marginBottom: 12,
                border: `1.5px solid ${grade ? GRADE_BG[grade] : inProgress ? "#FEF3C7" : T.border}`,
                cursor: "pointer", textAlign: "left",
                boxShadow: grade
                    ? `0 4px 20px ${GRADE_COLOR[grade]}15`
                    : T.shadowCard,
                overflow: "hidden",
                transition: "all 0.25s cubic-bezier(0.4,0,0.2,1)",
                animation: `fadeInUp 0.4s ease ${index * 0.06}s both`,
            }}>
            {/* Top gradient stripe */}
            <div style={{
                height: 3,
                background: grade
                    ? `linear-gradient(90deg,${GRADE_COLOR[grade]},${GRADE_COLOR[grade]}66)`
                    : inProgress
                        ? T.gradientWarm
                        : `linear-gradient(90deg,${T.border},${T.borderLight})`,
            }} />

            <div style={{ padding: "14px 16px" }}>
                <div style={{ display: "flex", alignItems: "flex-start", gap: 12 }}>
                    {/* Icon or score circle */}
                    {grade ? (
                        <CircleRing
                            pct={pct ?? 0} size={50} stroke={4}
                            color={GRADE_COLOR[grade]}
                            bg={GRADE_BG[grade]}
                        >
                            <span style={{ fontSize: 11, fontWeight: 800, color: GRADE_COLOR[grade], whiteSpace: "nowrap" }}>
                                {pct != null ? `${pct.toFixed(1)}%` : "—"}
                            </span>
                        </CircleRing>
                    ) : (
                        <CircleRing
                            pct={progressPct} size={50} stroke={4}
                            color="#F59E0B"
                            bg="#FEF3C7"
                        >
                            <span style={{ fontSize: 11, fontWeight: 800, color: "#F59E0B", whiteSpace: "nowrap" }}>
                                {progressPct}%
                            </span>
                        </CircleRing>
                    )}

                    {/* Content */}
                    <div style={{ flex: 1, minWidth: 0 }}>
                        <div style={{ fontSize: 14, fontWeight: 700, color: T.text, marginBottom: 3 }}>
                            {assessment.facility_name || "Facility Assessment"}
                        </div>
                        <div style={{ fontSize: 12, color: T.textMuted, marginBottom: 7 }}>
                            {[assessment.assessment_type, assessment.assessment_date].filter(Boolean).join(" · ")}
                        </div>

                        {/* Progress bar for in-progress */}
                        {inProgress && (
                            <div>
                                <ProgressBar
                                    pct={progressPct}
                                    gradient={T.gradientWarm}
                                    height={4}
                                    animated
                                />
                                <div style={{ fontSize: 10, color: T.textMuted, marginTop: 4, fontWeight: 500 }}>
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
                        marginTop: 10, display: "flex", alignItems: "center", gap: 6,
                        padding: "6px 10px", background: T.borderLight, borderRadius: 10,
                    }}>
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke={T.textMuted} strokeWidth="2">
                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z" /><circle cx="12" cy="10" r="3" />
                        </svg>
                        <span style={{ fontSize: 11, color: T.textMid, fontWeight: 500 }}>
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
        ? Math.round(completed.reduce((s, a) => s + (Number(a.overall_percentage) || 0), 0) / completed.length)
        : 0;
    const avgGrade = completed.length ? calcGrade(avgScore) : null;

    const filteredList = filter === "all" ? list
        : filter === "completed" ? completed
            : inProgress;

    // Trend data (sparkline from scores)
    const trendData = completed.slice(-7).map(a => a.overall_percentage ?? 0);

    return (
        <div style={{ height: "100%", overflowY: "auto", background: T.bg }}>
            {/* ── Hero Header ── */}
            <div style={{
                background: T.gradientDark,
                backgroundSize: "200% 200%",
                animation: "gradientShift 10s ease infinite",
                padding: "52px 20px 28px",
                position: "relative", overflow: "hidden",
                borderRadius: "24px 24px 28px 28px",
            }}>
                {/* Decorative elements */}
                <div style={{ position: "absolute", width: 200, height: 200, borderRadius: "50%", background: "radial-gradient(circle, rgba(16,185,129,0.12) 0%, transparent 70%)", top: -60, right: -50 }} />
                <div style={{ position: "absolute", width: 120, height: 120, borderRadius: "50%", background: "radial-gradient(circle, rgba(52,211,153,0.08) 0%, transparent 70%)", bottom: 0, left: -30 }} />

                {/* Greeting */}
                <div style={{
                    color: "rgba(255,255,255,0.55)", fontSize: 13, marginBottom: 4,
                    fontWeight: 500, animation: "fadeInUp 0.5s ease both",
                }}>
                    Good day 👋
                </div>
                <div style={{
                    color: "white", fontSize: 22, fontWeight: 800,
                    lineHeight: 1.2, marginBottom: 2, letterSpacing: -0.3,
                    animation: "fadeInUp 0.5s ease 0.05s both",
                }}>
                    {user?.name ?? "Assessor"}
                </div>
                {user?.facility && (
                    <div style={{
                        color: "rgba(255,255,255,0.45)", fontSize: 12, marginBottom: 18,
                        display: "flex", alignItems: "center", gap: 4,
                        animation: "fadeInUp 0.5s ease 0.1s both",
                    }}>
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,0.5)" strokeWidth="2">
                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z" /><circle cx="12" cy="10" r="3" />
                        </svg>
                        {user.facility}{user.county ? ` · ${user.county}` : ""}
                    </div>
                )}

                {/* Stats row */}
                <div style={{ display: "flex", gap: 8, animation: "fadeInUp 0.5s ease 0.15s both" }}>
                    {/* Average score ring */}
                    <div style={{
                        flex: 1.2, padding: "12px",
                        background: "rgba(255,255,255,0.06)",
                        backdropFilter: "blur(8px)",
                        borderRadius: 16, border: "1px solid rgba(255,255,255,0.1)",
                        display: "flex", alignItems: "center", gap: 10,
                    }}>
                        <CircleRing
                            pct={avgScore} size={52} stroke={5}
                            color={avgGrade ? GRADE_COLOR[avgGrade] : "rgba(255,255,255,0.4)"}
                            bg="rgba(255,255,255,0.1)"
                        >
                            <span style={{ color: "white", fontSize: 10, fontWeight: 800, whiteSpace: "nowrap" }}>
                                {completed.length && !isNaN(avgScore) ? `${avgScore}%` : "—"}
                            </span>
                        </CircleRing>
                        <div>
                            <div style={{ color: "white", fontSize: 13, fontWeight: 700 }}>
                                Avg Score
                            </div>
                            <div style={{ color: "rgba(255,255,255,0.45)", fontSize: 10 }}>
                                {completed.length} scored
                            </div>
                            {trendData.length > 1 && (
                                <div style={{ marginTop: 4 }}>
                                    <MiniBar values={trendData} color="rgba(255,255,255,0.6)" height={18} />
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Quick stats */}
                    <div style={{ flex: 1, display: "flex", flexDirection: "column", gap: 8 }}>
                        {[
                            { label: "Total", value: list.length, bg: "rgba(16,185,129,0.12)" },
                            { label: "In Progress", value: inProgress.length, bg: "rgba(245,158,11,0.15)" },
                        ].map((stat, i) => (
                            <div key={i} style={{
                                flex: 1, padding: "8px 12px",
                                background: stat.bg,
                                backdropFilter: "blur(4px)",
                                borderRadius: 12, border: "1px solid rgba(255,255,255,0.08)",
                                display: "flex", justifyContent: "space-between", alignItems: "center",
                            }}>
                                <span style={{ color: "rgba(255,255,255,0.6)", fontSize: 11, fontWeight: 500 }}>{stat.label}</span>
                                <span style={{ color: "white", fontSize: 18, fontWeight: 800 }}>{stat.value}</span>
                            </div>
                        ))}
                    </div>
                </div>
            </div>

            {/* ── Content ── */}
            <div style={{ padding: "16px 16px 20px" }}>

                {/* Score distribution */}
                {completed.length > 0 && <ScoreDonut assessments={list} />}

                {/* Action points insight */}
                <ActionPointsHint assessments={list} onView={onViewAssessment} />

                {/* Filter tabs */}
                <div style={{
                    display: "flex", gap: 4, marginBottom: 16,
                    background: "white", borderRadius: 14, padding: 4,
                    boxShadow: T.shadowCard, border: `1px solid ${T.border}`,
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
                                flex: 1, padding: "9px 4px", borderRadius: 11,
                                border: "none", cursor: "pointer", fontSize: 12, fontWeight: 600,
                                background: filter === tab.key
                                    ? T.gradientPrimary
                                    : "none",
                                color: filter === tab.key ? "white" : T.textMuted,
                                transition: "all 0.25s cubic-bezier(0.4,0,0.2,1)",
                                boxShadow: filter === tab.key ? `0 4px 12px ${T.primaryGlow}` : "none",
                                letterSpacing: 0.1,
                            }}>
                            {tab.label}
                        </button>
                    ))}
                </div>

                {/* Error state */}
                {error && (
                    <div style={{
                        padding: "20px", background: "white", borderRadius: T.radius,
                        border: "1.5px solid #FCA5A5", marginBottom: 14,
                        textAlign: "center", boxShadow: T.shadowCard,
                    }}>
                        <div style={{ fontSize: 36, marginBottom: 10 }}>⚠️</div>
                        <div style={{ fontSize: 14, fontWeight: 700, color: "#991B1B", marginBottom: 10 }}>{error}</div>
                        <button
                            onClick={onRetry}
                            style={{
                                padding: "10px 24px", borderRadius: 12, border: "none",
                                background: "linear-gradient(135deg, #EF4444, #F87171)",
                                color: "white", fontSize: 13, fontWeight: 700,
                                cursor: "pointer", boxShadow: "0 4px 12px rgba(239,68,68,0.25)",
                            }}>
                            Retry
                        </button>
                    </div>
                )}

                {/* Loading */}
                {loading && !error && (
                    <div style={{ textAlign: "center", padding: "44px 0" }}>
                        <div style={{
                            width: 48, height: 48, borderRadius: 16, margin: "0 auto 14px",
                            background: T.gradientPrimary,
                            display: "flex", alignItems: "center", justifyContent: "center",
                            boxShadow: `0 8px 24px ${T.primaryGlow}`,
                        }}>
                            <svg width="24" height="24" viewBox="0 0 24 24" style={{ animation: "spin 1s linear infinite" }}>
                                <circle cx="12" cy="12" r="10" fill="none" stroke="rgba(255,255,255,0.25)" strokeWidth="3" />
                                <path d="M12 2a10 10 0 019.95 9" fill="none" stroke="white" strokeWidth="3" strokeLinecap="round" />
                            </svg>
                        </div>
                        <div style={{ color: T.textMuted, fontSize: 13, fontWeight: 500 }}>Loading assessments…</div>
                    </div>
                )}

                {/* Empty state */}
                {!loading && !error && filteredList.length === 0 && (
                    <div style={{
                        textAlign: "center", padding: "48px 24px",
                        background: "white", borderRadius: T.radius,
                        boxShadow: T.shadowCard, border: `1px solid ${T.border}`,
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
                        marginTop: 6, padding: "16px 18px",
                        background: "linear-gradient(135deg, rgba(108,92,231,0.06), rgba(14,165,233,0.06))",
                        borderRadius: T.radius,
                        border: `1px solid ${T.primary}15`,
                        display: "flex", alignItems: "flex-start", gap: 12,
                    }}>
                        <div style={{
                            width: 32, height: 32, borderRadius: 10,
                            background: T.primaryGhost,
                            display: "flex", alignItems: "center", justifyContent: "center",
                            flexShrink: 0,
                        }}>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke={T.primary} strokeWidth="2">
                                <circle cx="12" cy="12" r="10" /><line x1="12" y1="16" x2="12" y2="12" /><line x1="12" y1="8" x2="12.01" y2="8" />
                            </svg>
                        </div>
                        <div>
                            <div style={{ fontSize: 13, fontWeight: 700, color: T.primary, marginBottom: 3 }}>
                                Performance Tip
                            </div>
                            <div style={{ fontSize: 12, color: T.textSub, lineHeight: 1.6 }}>
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
