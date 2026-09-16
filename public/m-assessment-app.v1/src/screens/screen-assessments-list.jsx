import { useState, useEffect } from "react";
import { T, GRADE_COLOR, GRADE_BG, GRADE_TEXT } from "../constants.js";
import { GradeBadge, StatusChip, ProgressBar } from "../components/shared-components.jsx";
import { NewAssessmentSheet } from "./screen-new-assessment.jsx";

export function AssessmentsListScreen({ assessments, sections, onView, loading, onCreate, facilities, user, openSheet, onSheetClose }) {
    const [filter, setFilter] = useState("all");
    const [showSheet, setShowSheet] = useState(false);

    // Allow App.jsx (e.g. bottom-nav "New" tap) to open the sheet externally
    useEffect(() => {
        if (openSheet) setShowSheet(true);
    }, [openSheet]);

    const list = filter === "all"
        ? (assessments || [])
        : (assessments || []).filter(a => a.status === filter);

    return (
        <div style={{ height: "100%", overflowY: "auto", background: T.bg, position: "relative" }}>
            <div style={{
                background: T.gradientDark,
                padding: "52px 20px 22px",
                borderRadius: "24px 24px 28px 28px",
                position: "relative", overflow: "hidden",
            }}>
                <div style={{ position: "absolute", width: 160, height: 160, borderRadius: "50%", background: "radial-gradient(circle, rgba(52,211,153,0.1) 0%, transparent 70%)", top: -40, right: -40 }} />

                <div style={{
                    color: "white", fontSize: 22, fontWeight: 800, letterSpacing: -0.3,
                    animation: "fadeInUp 0.4s ease both",
                }}>
                    My Assessments
                </div>
                <div style={{
                    color: "rgba(255,255,255,0.45)", fontSize: 13, marginTop: 3, fontWeight: 500,
                    animation: "fadeInUp 0.4s ease 0.05s both",
                }}>
                    {(assessments || []).length} total assessments
                </div>

                <div style={{
                    display: "flex", gap: 6, marginTop: 16,
                    animation: "fadeInUp 0.4s ease 0.1s both",
                }}>
                    {["all", "completed", "in_progress"].map(f => (
                        <button key={f} onClick={() => setFilter(f)} style={{
                            padding: "7px 16px", borderRadius: 20,
                            background: filter === f ? "rgba(255,255,255,0.95)" : "rgba(255,255,255,0.08)",
                            color: filter === f ? T.primary : "rgba(255,255,255,0.6)",
                            border: filter === f ? "none" : "1px solid rgba(255,255,255,0.1)",
                            fontSize: 12, fontWeight: 600, cursor: "pointer",
                            transition: "all 0.2s cubic-bezier(0.4,0,0.2,1)",
                            boxShadow: filter === f ? `0 4px 12px rgba(0,0,0,0.15)` : "none",
                        }}>
                            {f === "all" ? "All" : f === "completed" ? "Completed" : "In Progress"}
                        </button>
                    ))}
                </div>
            </div>

            <div style={{ padding: "16px 16px 20px" }}>
                {loading && (
                    <div style={{ textAlign: "center", padding: "40px 0", color: T.textMuted }}>
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
                        <div style={{ fontSize: 13, fontWeight: 500 }}>Loading…</div>
                    </div>
                )}
                {!loading && list.length === 0 && (
                    <div style={{
                        textAlign: "center", padding: "50px 24px", color: T.textMuted,
                        background: "white", borderRadius: T.radius,
                        boxShadow: T.shadowCard, border: `1px solid ${T.border}`,
                    }}>
                        <div style={{ fontSize: 56, marginBottom: 16 }}>📋</div>
                        <div style={{ fontSize: 17, fontWeight: 700, color: T.textMid, marginBottom: 8 }}>No assessments yet</div>
                        <div style={{ fontSize: 13, color: T.textSub, lineHeight: 1.6 }}>
                            No assessments yet. Tap + to start one.
                        </div>
                    </div>
                )}
                {!loading && list.map((a, i) => (
                    <button key={a.id} onClick={() => onView(a)} style={{
                        width: "100%", background: "white", borderRadius: T.radius,
                        padding: "15px 16px", marginBottom: 12,
                        border: `1.5px solid ${a.overall_grade ? GRADE_BG[a.overall_grade] : T.border}`,
                        cursor: "pointer", textAlign: "left",
                        boxShadow: T.shadowCard,
                        transition: "all 0.25s cubic-bezier(0.4,0,0.2,1)",
                        animation: `fadeInUp 0.4s ease ${i * 0.05}s both`,
                    }}>
                        {a._isOffline && (
                            <div style={{
                                display: "inline-flex",
                                alignItems: "center",
                                gap: 4,
                                background: GRADE_BG.yellow,
                                color: GRADE_TEXT.yellow,
                                fontSize: 11,
                                fontWeight: 600,
                                borderRadius: 6,
                                padding: "3px 8px",
                                marginBottom: 6,
                            }}>
                                <span style={{ width: 6, height: 6, borderRadius: "50%", background: GRADE_COLOR.yellow, display: "inline-block" }} />
                                Pending sync
                            </div>
                        )}
                        <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start" }}>
                            <div style={{ flex: 1, minWidth: 0 }}>
                                <div style={{
                                    fontWeight: 700, fontSize: 14, color: T.text,
                                    overflow: "hidden", textOverflow: "ellipsis", whiteSpace: "nowrap",
                                }}>
                                    {a.facility_name}
                                </div>
                                <div style={{
                                    fontSize: 12, color: T.textSub, marginTop: 4, fontWeight: 500,
                                    display: "flex", alignItems: "center", gap: 4,
                                }}>
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke={T.textMuted} strokeWidth="2">
                                        <rect x="3" y="4" width="18" height="18" rx="2" /><line x1="16" y1="2" x2="16" y2="6" />
                                        <line x1="8" y1="2" x2="8" y2="6" /><line x1="3" y1="10" x2="21" y2="10" />
                                    </svg>
                                    {a.assessment_type} · {a.assessment_date}
                                </div>
                                {(a.mfl_code || a.subcounty || a.county) && (
                                    <div style={{
                                        fontSize: 11, color: T.textMuted, marginTop: 3,
                                        display: "flex", alignItems: "center", gap: 4, flexWrap: "wrap",
                                    }}>
                                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke={T.textMuted} strokeWidth="2">
                                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z" /><circle cx="12" cy="10" r="3" />
                                        </svg>
                                        {[a.mfl_code && `MFL: ${a.mfl_code}`, a.subcounty, a.county].filter(Boolean).join(" · ")}
                                    </div>
                                )}
                            </div>
                            <div style={{ marginLeft: 10, flexShrink: 0 }}>
                                {a.overall_grade
                                    ? <GradeBadge grade={a.overall_grade} pct={a.overall_percentage} />
                                    : <StatusChip status={a.status} />
                                }
                            </div>
                        </div>
                        {a.status === "completed" && sections && sections.length > 0 && (
                            <div style={{ display: "flex", gap: 8, marginTop: 12 }}>
                                {sections.map(s => {
                                    const sc = (a.section_scores || {})[s.code];
                                    return (
                                        <div key={s.code} style={{ flex: 1, textAlign: "center" }}>
                                            <div style={{ fontSize: 10, marginBottom: 4 }}>{s.icon}</div>
                                            <ProgressBar
                                                pct={sc ? sc.percentage : 0}
                                                color={sc ? GRADE_COLOR[sc.grade] : T.border}
                                                height={4}
                                            />
                                        </div>
                                    );
                                })}
                            </div>
                        )}
                    </button>
                ))}
            </div>

            {/* FAB — create new assessment */}
            <button
                onClick={() => setShowSheet(true)}
                aria-label="New assessment"
                style={{
                    position: "fixed",
                    bottom: 80,
                    right: 20,
                    width: 52,
                    height: 52,
                    borderRadius: "50%",
                    background: T.gradientPrimary,
                    border: "none",
                    cursor: "pointer",
                    display: "flex",
                    alignItems: "center",
                    justifyContent: "center",
                    boxShadow: T.shadowMd,
                    zIndex: 10,
                }}
            >
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19" />
                    <line x1="5" y1="12" x2="19" y2="12" />
                </svg>
            </button>

            {showSheet && (
                <NewAssessmentSheet
                    facilities={facilities}
                    sections={sections}
                    user={user}
                    onSubmit={(assessment) => {
                        setShowSheet(false);
                        onSheetClose?.();
                        onCreate(assessment);
                    }}
                    onClose={() => { setShowSheet(false); onSheetClose?.(); }}
                />
            )}
        </div>
    );
}
