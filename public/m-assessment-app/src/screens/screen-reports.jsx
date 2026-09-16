import { useState, useEffect } from "react";
import { T, GRADE_COLOR, GRADE_BG, GRADE_LABEL, calcGrade } from "../constants.js";
import { GradeBadge, ProgressBar } from "../components/shared-components.jsx";
import api from "../services/api.service.js";

function ReadinessCard({ f }) {
    const isEligible   = f.eligibility_status === 'eligible';
    const isPending    = !f.eligibility_status || f.eligibility_status === 'pending';
    const eligColor    = isEligible ? '#065F46' : isPending ? '#92400E' : '#991B1B';
    const eligBg       = isEligible ? '#D1FAE5' : isPending ? '#FEF3C7' : '#FEE2E2';
    const eligLabel    = isEligible ? '✓ Eligible' : isPending ? '? Pending' : '✗ Not Eligible';
    const pct          = f.overall_percentage != null ? Number(f.overall_percentage) : null;
    const pctColor     = pct == null ? '#94A3B8' : pct >= 80 ? '#10B981' : pct >= 50 ? '#F59E0B' : '#EF4444';
    const pctBg        = pct == null ? '#F1F5F9' : pct >= 80 ? '#D1FAE5' : pct >= 50 ? '#FEF3C7' : '#FEE2E2';

    return (
        <div style={{ background: 'white', borderRadius: T.radius, padding: '12px 14px', marginBottom: 10, boxShadow: T.shadowCard, border: `1px solid ${T.border}`, display: 'flex', gap: 12, alignItems: 'flex-start' }}>
            <div style={{ width: 48, height: 48, borderRadius: 12, flexShrink: 0, background: pctBg, display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center' }}>
                <div style={{ fontSize: pct != null ? 14 : 18, fontWeight: 900, color: pctColor, lineHeight: 1 }}>
                    {pct != null ? `${pct.toFixed(0)}%` : '—'}
                </div>
            </div>
            <div style={{ flex: 1, minWidth: 0 }}>
                <div style={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', gap: 6, marginBottom: 3 }}>
                    <span style={{ fontSize: 13, fontWeight: 700, color: T.text, flex: 1, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{f.facility}</span>
                    <span style={{ fontSize: 10, fontWeight: 700, padding: '2px 8px', borderRadius: 20, background: eligBg, color: eligColor, flexShrink: 0, whiteSpace: 'nowrap' }}>{eligLabel}</span>
                </div>
                <div style={{ fontSize: 11, color: T.textMuted, marginBottom: 5 }}>
                    {[f.mfl_code && `MFL ${f.mfl_code}`, f.county, f.subcounty, f.level].filter(Boolean).join(' · ')}
                </div>
                <div style={{ display: 'flex', flexWrap: 'wrap', gap: 5 }}>
                    <span style={{ fontSize: 10, padding: '2px 7px', borderRadius: 6, fontWeight: 600, background: f.has_skills_lab ? '#D1FAE5' : '#FEE2E2', color: f.has_skills_lab ? '#065F46' : '#991B1B' }}>
                        {f.has_skills_lab ? '✓' : '✗'} Skills Lab
                    </span>
                    <span style={{ fontSize: 10, padding: '2px 7px', borderRadius: 6, fontWeight: 600, background: f.has_room ? '#D1FAE5' : '#FEE2E2', color: f.has_room ? '#065F46' : '#991B1B' }}>
                        {f.has_room ? '✓' : '✗'} Training Room
                    </span>
                    {f.mentorship_count > 0 && (
                        <span style={{ fontSize: 10, padding: '2px 7px', borderRadius: 6, fontWeight: 600, background: '#EEF2FF', color: '#4338CA' }}>
                            {f.mentorship_count} mentorship{f.mentorship_count !== 1 ? 's' : ''}
                        </span>
                    )}
                </div>
                {(f.assessment_date || f.assessment_type) && (
                    <div style={{ fontSize: 10, color: T.textSub, marginTop: 4 }}>
                        {[f.assessment_type, f.assessment_date].filter(Boolean).join(' · ')}
                    </div>
                )}
            </div>
        </div>
    );
}

export function ReportsScreen({ user, assessments, sectionAverages, loading, onViewAssessment, onViewEmailJobs }) {
    const [selectedFacility, setSelectedFacility] = useState(null);
    const [readiness, setReadiness]               = useState(null);
    const [readinessLoading, setReadinessLoading] = useState(true);
    const [rSearch, setRSearch]       = useState('');
    const [rEligibility, setREligibility] = useState('all');
    const [rSkillsLab, setRSkillsLab] = useState('all');
    const [rRoom, setRRoom]           = useState('all');
    const [rCounty, setRCounty]       = useState('all');
    const [rType, setRType]           = useState('all');
    const [rSort, setRSort]           = useState('score_desc');
    const list = assessments ?? [];
    const completed = list.filter(a => a.status === "completed");
    const avgScore = completed.length
        ? Math.round(completed.reduce((s, a) => s + (Number(a.overall_percentage) || 0), 0) / completed.length)
        : 0;

    const gradeDistribution = ["green", "yellow", "red"].map(g => ({
        grade: g,
        count: completed.filter(a => a.overall_grade === g).length,
    }));

    const isMentor = (user?.roles ?? []).some(r =>
        ['facility_mentor','spoke_mentor','spoke_mentor_lead','county_mentor_lead',
         'subcounty_mentor_lead','facility_mentor_lead','mentor_lead'].includes(r)
    );
    const isAssessor = (user?.roles ?? []).includes('Assessor');
    const showInnerTabs = isMentor && isAssessor;

    const [innerTab, setInnerTab] = useState(isAssessor ? 'assessments' : 'mentorship');
    const [mentorships, setMentorships] = useState(null);

    useEffect(() => {
        if (isMentor) {
            api.mentorships.list()
                .then(d => setMentorships(Array.isArray(d?.data) ? d.data : []))
                .catch(() => setMentorships([]));
        }
    }, [user?.id]);

    useEffect(() => {
        api.analytics.summary('assessment')
            .then(d => setReadiness(d?.facilitiesReadiness ?? []))
            .catch(() => setReadiness([]))
            .finally(() => setReadinessLoading(false));
    }, []);

    return (
        <div style={{ height: "100%", overflowY: "auto", background: T.bg }}>
            {/* Header */}
            <div style={{
                background: T.gradientDark,
                padding: "52px 20px 24px",
                borderRadius: "24px 24px 28px 28px",
                position: "relative", overflow: "hidden",
            }}>
                <div style={{ position: "absolute", width: 150, height: 150, borderRadius: "50%", background: "radial-gradient(circle, rgba(79,106,245,0.20) 0%, transparent 70%)", top: -30, right: -30 }} />
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

            {/* ── Inner tab selector (only shown for combined roles) ── */}
            {showInnerTabs && (
                <div style={{
                    display: "flex", background: "#F3F4F6",
                    borderRadius: 8, padding: 3, margin: "0 16px 12px",
                }}>
                    {[
                        { key: 'assessments', label: 'Assessments' },
                        { key: 'mentorship',  label: 'Mentorship' },
                    ].map(tab => (
                        <button
                            key={tab.key}
                            onClick={() => setInnerTab(tab.key)}
                            style={{
                                flex: 1, border: "none", borderRadius: 6,
                                padding: "8px 0", fontWeight: 700, fontSize: 13, cursor: "pointer",
                                background: innerTab === tab.key ? "#fff" : "transparent",
                                color: innerTab === tab.key ? "#6C5CE7" : "#6B7280",
                                boxShadow: innerTab === tab.key ? "0 1px 4px rgba(0,0,0,0.08)" : "none",
                                transition: "all 0.15s",
                            }}
                        >
                            {tab.label}
                        </button>
                    ))}
                </div>
            )}

            {(!showInnerTabs || innerTab === 'assessments') && (
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

                            {/* Facilities Readiness & Mentorship Eligibility */}
                            {(readiness?.length > 0 || readinessLoading) && (() => {
                                // Derived filter options
                                const allCounties  = [...new Set((readiness ?? []).map(f => f.county).filter(Boolean))].sort();
                                const allTypes     = [...new Set((readiness ?? []).map(f => f.assessment_type).filter(Boolean))].sort();
                                const eligible    = (readiness ?? []).filter(f => f.eligibility_status === 'eligible').length;
                                const notEligible = (readiness ?? []).filter(f => f.eligibility_status === 'not_eligible').length;
                                const pending     = (readiness ?? []).length - eligible - notEligible;

                                // Apply filters
                                let filtered = readiness ?? [];
                                if (rSearch.trim()) {
                                    const q = rSearch.toLowerCase();
                                    filtered = filtered.filter(f =>
                                        (f.facility ?? '').toLowerCase().includes(q) ||
                                        (f.county ?? '').toLowerCase().includes(q) ||
                                        (f.mfl_code ?? '').toLowerCase().includes(q)
                                    );
                                }
                                if (rEligibility !== 'all') filtered = filtered.filter(f => f.eligibility_status === rEligibility);
                                if (rSkillsLab === 'yes') filtered = filtered.filter(f => f.has_skills_lab);
                                if (rSkillsLab === 'no')  filtered = filtered.filter(f => !f.has_skills_lab);
                                if (rRoom === 'yes') filtered = filtered.filter(f => f.has_room);
                                if (rRoom === 'no')  filtered = filtered.filter(f => !f.has_room);
                                if (rCounty !== 'all') filtered = filtered.filter(f => f.county === rCounty);
                                if (rType !== 'all')   filtered = filtered.filter(f => f.assessment_type === rType);
                                if (rSort === 'score_desc') filtered = [...filtered].sort((a, b) => (b.overall_percentage ?? 0) - (a.overall_percentage ?? 0));
                                if (rSort === 'score_asc')  filtered = [...filtered].sort((a, b) => (a.overall_percentage ?? 0) - (b.overall_percentage ?? 0));
                                if (rSort === 'name')       filtered = [...filtered].sort((a, b) => (a.facility ?? '').localeCompare(b.facility ?? ''));
                                if (rSort === 'mentorships') filtered = [...filtered].sort((a, b) => (b.mentorship_count ?? 0) - (a.mentorship_count ?? 0));

                                const activeFilters = [rEligibility!=='all', rSkillsLab!=='all', rRoom!=='all', rCounty!=='all', rType!=='all', rSearch.trim()!==''].filter(Boolean).length;

                                return (
                                    <div style={{ background: "white", borderRadius: T.radius, padding: 18, marginBottom: 14, boxShadow: T.shadowCard, border: `1px solid ${T.border}`, animation: "fadeInUp 0.4s ease 0.23s both" }}>
                                        {/* Header */}
                                        <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", marginBottom: 12 }}>
                                            <div style={{ fontSize: 12, fontWeight: 700, color: T.textMuted, textTransform: "uppercase", letterSpacing: 0.8, display: "flex", alignItems: "center", gap: 6 }}>
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke={T.primary} strokeWidth="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                                                Facilities Readiness &amp; Eligibility
                                            </div>
                                            {activeFilters > 0 && (
                                                <button onClick={() => { setRSearch(''); setREligibility('all'); setRSkillsLab('all'); setRRoom('all'); setRCounty('all'); setRType('all'); setRSort('score_desc'); }} style={{ fontSize: 11, color: T.primary, fontWeight: 700, background: T.primaryGhost, border: "none", borderRadius: 6, padding: "3px 8px", cursor: "pointer" }}>
                                                    Clear {activeFilters}
                                                </button>
                                            )}
                                        </div>

                                        {readinessLoading && <div style={{ textAlign: "center", color: T.textMuted, padding: "16px 0", fontSize: 13 }}>Loading…</div>}

                                        {!readinessLoading && (
                                            <>
                                                {/* Summary pills */}
                                                <div style={{ display: "flex", gap: 6, flexWrap: "wrap", marginBottom: 12 }}>
                                                    <span style={{ fontSize: 11, padding: "3px 10px", borderRadius: 20, background: "#D1FAE5", color: "#065F46", fontWeight: 700 }}>✓ {eligible} Eligible</span>
                                                    {pending > 0 && <span style={{ fontSize: 11, padding: "3px 10px", borderRadius: 20, background: "#FEF3C7", color: "#92400E", fontWeight: 700 }}>? {pending} Pending</span>}
                                                    {notEligible > 0 && <span style={{ fontSize: 11, padding: "3px 10px", borderRadius: 20, background: "#FEE2E2", color: "#991B1B", fontWeight: 700 }}>✗ {notEligible} Not Eligible</span>}
                                                </div>

                                                {/* Search */}
                                                <div style={{ position: "relative", marginBottom: 10 }}>
                                                    <svg style={{ position: "absolute", left: 10, top: "50%", transform: "translateY(-50%)" }} width="14" height="14" viewBox="0 0 24 24" fill="none" stroke={T.textMuted} strokeWidth="2" strokeLinecap="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                                                    <input value={rSearch} onChange={e => setRSearch(e.target.value)} placeholder="Search facility, county, MFL…" style={{ width: "100%", padding: "8px 12px 8px 30px", border: `1px solid ${T.border}`, borderRadius: 10, fontSize: 13, outline: "none", boxSizing: "border-box", background: T.bg }} />
                                                </div>

                                                {/* Filter row 1: Eligibility */}
                                                <div style={{ overflowX: "auto", paddingBottom: 4, marginBottom: 8 }}>
                                                    <div style={{ display: "flex", gap: 6, whiteSpace: "nowrap" }}>
                                                        <span style={{ fontSize: 10, fontWeight: 700, color: T.textMuted, alignSelf: "center", flexShrink: 0 }}>Eligibility:</span>
                                                        {[['all','All'],['eligible','✓ Eligible'],['not_eligible','✗ Not Eligible'],['pending','? Pending']].map(([v, label]) => (
                                                            <button key={v} onClick={() => setREligibility(v)} style={{ fontSize: 11, padding: "4px 10px", borderRadius: 20, border: "1.5px solid", fontWeight: 700, cursor: "pointer", whiteSpace: "nowrap", transition: "all 0.15s", borderColor: rEligibility===v ? T.primary : T.border, background: rEligibility===v ? T.primaryGhost : "white", color: rEligibility===v ? T.primary : T.textMuted }}>
                                                                {label}
                                                            </button>
                                                        ))}
                                                    </div>
                                                </div>

                                                {/* Filter row 2: Skills Lab + Room */}
                                                <div style={{ overflowX: "auto", paddingBottom: 4, marginBottom: 8 }}>
                                                    <div style={{ display: "flex", gap: 6, whiteSpace: "nowrap" }}>
                                                        <span style={{ fontSize: 10, fontWeight: 700, color: T.textMuted, alignSelf: "center", flexShrink: 0 }}>Skills Lab:</span>
                                                        {[['all','All'],['yes','Has'],['no','None']].map(([v, label]) => (
                                                            <button key={v} onClick={() => setRSkillsLab(v)} style={{ fontSize: 11, padding: "4px 10px", borderRadius: 20, border: "1.5px solid", fontWeight: 700, cursor: "pointer", whiteSpace: "nowrap", transition: "all 0.15s", borderColor: rSkillsLab===v ? '#10B981' : T.border, background: rSkillsLab===v ? '#ECFDF5' : "white", color: rSkillsLab===v ? '#065F46' : T.textMuted }}>
                                                                {label}
                                                            </button>
                                                        ))}
                                                        <span style={{ fontSize: 10, fontWeight: 700, color: T.textMuted, alignSelf: "center", flexShrink: 0, marginLeft: 4 }}>Room:</span>
                                                        {[['all','All'],['yes','Has'],['no','None']].map(([v, label]) => (
                                                            <button key={v} onClick={() => setRRoom(v)} style={{ fontSize: 11, padding: "4px 10px", borderRadius: 20, border: "1.5px solid", fontWeight: 700, cursor: "pointer", whiteSpace: "nowrap", transition: "all 0.15s", borderColor: rRoom===v ? '#6366F1' : T.border, background: rRoom===v ? '#EEF2FF' : "white", color: rRoom===v ? '#4338CA' : T.textMuted }}>
                                                                {label}
                                                            </button>
                                                        ))}
                                                    </div>
                                                </div>

                                                {/* Filter row 3: County + Type */}
                                                {(allCounties.length > 1 || allTypes.length > 1) && (
                                                    <div style={{ overflowX: "auto", paddingBottom: 4, marginBottom: 8 }}>
                                                        <div style={{ display: "flex", gap: 6, whiteSpace: "nowrap" }}>
                                                            {allCounties.length > 1 && (
                                                                <>
                                                                    <span style={{ fontSize: 10, fontWeight: 700, color: T.textMuted, alignSelf: "center", flexShrink: 0 }}>County:</span>
                                                                    {['all', ...allCounties].map(v => (
                                                                        <button key={v} onClick={() => setRCounty(v)} style={{ fontSize: 11, padding: "4px 10px", borderRadius: 20, border: "1.5px solid", fontWeight: 700, cursor: "pointer", whiteSpace: "nowrap", transition: "all 0.15s", borderColor: rCounty===v ? T.primary : T.border, background: rCounty===v ? T.primaryGhost : "white", color: rCounty===v ? T.primary : T.textMuted }}>
                                                                            {v === 'all' ? 'All' : v}
                                                                        </button>
                                                                    ))}
                                                                </>
                                                            )}
                                                            {allTypes.length > 1 && (
                                                                <>
                                                                    <span style={{ fontSize: 10, fontWeight: 700, color: T.textMuted, alignSelf: "center", flexShrink: 0, marginLeft: 4 }}>Type:</span>
                                                                    {['all', ...allTypes].map(v => (
                                                                        <button key={v} onClick={() => setRType(v)} style={{ fontSize: 11, padding: "4px 10px", borderRadius: 20, border: "1.5px solid", fontWeight: 700, cursor: "pointer", whiteSpace: "nowrap", transition: "all 0.15s", borderColor: rType===v ? '#F59E0B' : T.border, background: rType===v ? '#FFFBEB' : "white", color: rType===v ? '#92400E' : T.textMuted }}>
                                                                            {v === 'all' ? 'All' : v}
                                                                        </button>
                                                                    ))}
                                                                </>
                                                            )}
                                                        </div>
                                                    </div>
                                                )}

                                                {/* Sort row */}
                                                <div style={{ overflowX: "auto", paddingBottom: 4, marginBottom: 12 }}>
                                                    <div style={{ display: "flex", gap: 6, whiteSpace: "nowrap" }}>
                                                        <span style={{ fontSize: 10, fontWeight: 700, color: T.textMuted, alignSelf: "center", flexShrink: 0 }}>Sort:</span>
                                                        {[['score_desc','Score ↓'],['score_asc','Score ↑'],['name','Name'],['mentorships','Mentorships']].map(([v, label]) => (
                                                            <button key={v} onClick={() => setRSort(v)} style={{ fontSize: 11, padding: "4px 10px", borderRadius: 20, border: "1.5px solid", fontWeight: 700, cursor: "pointer", whiteSpace: "nowrap", transition: "all 0.15s", borderColor: rSort===v ? '#8B5CF6' : T.border, background: rSort===v ? '#EDE9FE' : "white", color: rSort===v ? '#6D28D9' : T.textMuted }}>
                                                                {label}
                                                            </button>
                                                        ))}
                                                    </div>
                                                </div>

                                                {/* Results */}
                                                <div style={{ fontSize: 11, color: T.textMuted, marginBottom: 10, fontWeight: 600 }}>
                                                    {filtered.length} of {(readiness??[]).length} facilities
                                                </div>
                                                {filtered.length === 0 && (
                                                    <div style={{ textAlign: "center", padding: "20px 0", color: T.textSub }}>
                                                        No facilities match the current filters.
                                                    </div>
                                                )}
                                                {filtered.map((f, i) => <ReadinessCard key={i} f={f} />)}
                                            </>
                                        )}
                                    </div>
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
            )}

            {/* ── Mentorship analytics (inner tab or sole view for mentor-only) ── */}
            {isMentor && (!showInnerTabs || innerTab === 'mentorship') && (
                <div style={{ flex: 1, overflowY: "auto", padding: 16 }}>
                    <div style={{ fontSize: 13, fontWeight: 700, color: T.textSub, marginBottom: 12, letterSpacing: 0.4 }}>
                        MENTORSHIP OVERVIEW
                    </div>
                    {mentorships === null && (
                        <div style={{ color: T.textSub, textAlign: "center", paddingTop: 32 }}>Loading…</div>
                    )}
                    {(mentorships ?? []).map(m => {
                        const total = m.class_count ?? 0;
                        return (
                            <div key={m.id} style={{
                                background: T.card, borderRadius: T.radiusSm, padding: "14px 16px",
                                marginBottom: 12, boxShadow: T.shadowCard, border: `1px solid ${T.border}`,
                            }}>
                                <div style={{ fontWeight: 700, color: T.text }}>{m.title}</div>
                                <div style={{ fontSize: 12, color: T.textSub, marginTop: 2 }}>
                                    {m.facility ?? m.county ?? ""} · {total} class{total !== 1 ? "es" : ""}
                                </div>
                                <div style={{ marginTop: 8, fontSize: 12, display: "flex", gap: 16 }}>
                                    <span style={{ color: T.textSub }}>Status: <b style={{ color: T.text }}>{m.status}</b></span>
                                </div>
                            </div>
                        );
                    })}
                    {mentorships?.length === 0 && (
                        <div style={{ color: T.textSub, textAlign: "center", paddingTop: 40 }}>
                            No mentorship data yet.
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
