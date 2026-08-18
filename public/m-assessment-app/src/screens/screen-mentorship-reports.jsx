import { useState, useEffect, useMemo } from "react";
import { T } from "../constants.js";
import api from "../services/api.service.js";

const TEAL = "#0097A7";

const STATUS_COLORS = {
    active:    "#10B981",
    draft:     "#F59E0B",
    completed: "#6366F1",
    cancelled: "#EF4444",
};

// ── SVG Donut Chart ───────────────────────────────────────────────────────────
function DonutChart({ segments, size = 130, strokeWidth = 17 }) {
    const r = (size - strokeWidth) / 2;
    const cx = size / 2;
    const cy = size / 2;
    const circumference = 2 * Math.PI * r;
    const total = segments.reduce((s, seg) => s + seg.value, 0);
    let accumulated = 0;

    return (
        <svg width={size} height={size} style={{ display: "block", flexShrink: 0 }}>
            <g transform={`rotate(-90, ${cx}, ${cy})`}>
                {/* Track */}
                <circle cx={cx} cy={cy} r={r} fill="none" stroke="#EEF2F2" strokeWidth={strokeWidth} />
                {total > 0 && segments.filter(s => s.value > 0).map((seg, i) => {
                    const portion   = (seg.value / total) * circumference;
                    const gap       = portion > 10 ? 4 : 0;
                    const dash      = Math.max(portion - gap, 0);
                    const offset    = -accumulated;
                    accumulated    += portion;
                    return (
                        <circle
                            key={i}
                            cx={cx} cy={cy} r={r}
                            fill="none"
                            stroke={seg.color}
                            strokeWidth={strokeWidth}
                            strokeDasharray={`${dash} ${circumference}`}
                            strokeDashoffset={offset}
                            strokeLinecap="butt"
                        />
                    );
                })}
            </g>
        </svg>
    );
}

// ── SVG Area / Line Sparkline ─────────────────────────────────────────────────
function AreaChart({ data, height = 68, color = TEAL }) {
    if (!data || data.length < 2) return null;
    const max = Math.max(...data.map(d => d.value), 1);
    const W = 280;
    const H = height;
    const padY = 6;
    const pts = data.map((d, i) => ({
        x: (i / (data.length - 1)) * W,
        y: H - padY - (d.value / max) * (H - padY * 2.5),
    }));
    const pathLine = pts.map((p, i) => `${i === 0 ? "M" : "L"}${p.x.toFixed(1)},${p.y.toFixed(1)}`).join(" ");
    const pathArea = `${pathLine} L${W},${H} L0,${H} Z`;
    const gradId   = `ag${color.replace("#", "")}`;

    return (
        <svg viewBox={`0 0 ${W} ${H}`} style={{ width: "100%", height, display: "block" }} preserveAspectRatio="none">
            <defs>
                <linearGradient id={gradId} x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%"   stopColor={color} stopOpacity="0.22" />
                    <stop offset="100%" stopColor={color} stopOpacity="0.01" />
                </linearGradient>
            </defs>
            <path d={pathArea} fill={`url(#${gradId})`} />
            <path d={pathLine} fill="none" stroke={color} strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round" />
            {pts.map((p, i) => (
                <circle key={i} cx={p.x} cy={p.y} r="3.5" fill="#fff" stroke={color} strokeWidth="2" />
            ))}
        </svg>
    );
}

// ── Horizontal bar row ────────────────────────────────────────────────────────
function HBar({ label, value, max, total, color }) {
    const pct   = max > 0 ? (value / max) * 100 : 0;
    const share = total > 0 ? Math.round((value / total) * 100) : 0;
    return (
        <div style={{ marginBottom: 11 }}>
            <div style={{ display: "flex", justifyContent: "space-between", alignItems: "baseline", marginBottom: 4 }}>
                <span style={{ fontSize: 13, color: T.text, fontWeight: 500, flex: 1, marginRight: 8, overflow: "hidden", textOverflow: "ellipsis", whiteSpace: "nowrap" }}>
                    {label}
                </span>
                <span style={{ fontSize: 12, fontWeight: 700, color: T.textSub, flexShrink: 0 }}>
                    {value}
                    <span style={{ color: T.textMuted, fontWeight: 500, marginLeft: 4, fontSize: 11 }}>({share}%)</span>
                </span>
            </div>
            <div style={{ height: 7, background: "#EEF2F2", borderRadius: 4, overflow: "hidden" }}>
                <div style={{ height: "100%", width: `${pct}%`, background: color, borderRadius: 4 }} />
            </div>
        </div>
    );
}

// ── Card ──────────────────────────────────────────────────────────────────────
function Card({ title, subtitle, children, noPad }) {
    return (
        <div style={{
            background: T.card, borderRadius: T.radius,
            boxShadow: T.shadowCard, border: `1px solid ${T.border}`,
            overflow: "hidden",
        }}>
            {title && (
                <div style={{ padding: "14px 16px 0" }}>
                    <div style={{ fontSize: 14, fontWeight: 700, color: T.text }}>{title}</div>
                    {subtitle && <div style={{ fontSize: 12, color: T.textSub, marginTop: 2 }}>{subtitle}</div>}
                </div>
            )}
            <div style={{ padding: noPad ? 0 : "14px 16px 16px" }}>{children}</div>
        </div>
    );
}

// ── Insight row ───────────────────────────────────────────────────────────────
function Insight({ icon, text, accent }) {
    return (
        <div style={{
            display: "flex", gap: 10, alignItems: "flex-start",
            padding: "10px 12px", borderRadius: T.radiusSm,
            background: accent ? `${accent}10` : T.bg,
            border: `1px solid ${accent ? `${accent}30` : T.borderLight}`,
        }}>
            <span style={{ fontSize: 17, flexShrink: 0, lineHeight: "1.35" }}>{icon}</span>
            <span style={{ fontSize: 13, color: T.text, lineHeight: 1.55 }}>{text}</span>
        </div>
    );
}

// ── Stat pill ─────────────────────────────────────────────────────────────────
function StatPill({ label, value, color }) {
    return (
        <div style={{
            flex: 1, padding: "10px 6px", borderRadius: 14,
            background: `${color}18`, border: `1px solid ${color}30`,
            textAlign: "center",
        }}>
            <div style={{ fontSize: 20, fontWeight: 800, color, lineHeight: 1 }}>{value}</div>
            <div style={{ fontSize: 10, fontWeight: 600, color, opacity: 0.7, marginTop: 3 }}>{label}</div>
        </div>
    );
}

// ── Main Screen ───────────────────────────────────────────────────────────────
export function MentorshipReportsScreen({ user }) {
    const [mentorships, setMentorships] = useState(null);
    const [loading, setLoading]         = useState(true);
    const [error, setError]             = useState(null);

    useEffect(() => {
        api.mentorships.list()
            .then(d => setMentorships(Array.isArray(d?.data) ? d.data : []))
            .catch(e => setError(e.message))
            .finally(() => setLoading(false));
    }, []);

    const stats = useMemo(() => {
        if (!mentorships) return null;
        const all = mentorships;
        const total = all.length;

        const byStatus = { active: 0, draft: 0, completed: 0, cancelled: 0 };
        const byProgram = {};
        const byCounty  = {};
        const byMonth   = {};
        let totalClasses = 0;
        let totalClassCount = 0;

        all.forEach(m => {
            const s = m.status ?? "draft";
            byStatus[s] = (byStatus[s] || 0) + 1;
            if (m.program) byProgram[m.program] = (byProgram[m.program] || 0) + 1;
            if (m.county)  byCounty[m.county]   = (byCounty[m.county]   || 0) + 1;
            if (m.start_date) {
                const month = m.start_date.slice(0, 7);
                byMonth[month] = (byMonth[month] || 0) + 1;
            }
            const cc = m.class_count ?? 0;
            totalClasses += cc;
            if (cc > 0) totalClassCount++;
        });

        const programs = Object.entries(byProgram)
            .map(([name, count]) => ({ name, count }))
            .sort((a, b) => b.count - a.count);

        const counties = Object.entries(byCounty)
            .map(([name, count]) => ({ name, count }))
            .sort((a, b) => b.count - a.count);

        // Last 6 months
        const now = new Date();
        const months = Array.from({ length: 6 }, (_, i) => {
            const d     = new Date(now.getFullYear(), now.getMonth() - (5 - i), 1);
            const key   = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}`;
            const label = d.toLocaleString("default", { month: "short" });
            return { key, label, value: byMonth[key] ?? 0 };
        });

        const activeRate    = total > 0 ? Math.round(((byStatus.active    ?? 0) / total) * 100) : 0;
        const completedRate = total > 0 ? Math.round(((byStatus.completed ?? 0) / total) * 100) : 0;
        const avgClasses    = total > 0 ? (totalClasses / total).toFixed(1) : "0";
        const recentMonths  = months.slice(-3).reduce((s, m) => s + m.value, 0);

        // Donut segments (only non-zero)
        const donutSegments = [
            { label: "Active",    value: byStatus.active    ?? 0, color: STATUS_COLORS.active },
            { label: "Draft",     value: byStatus.draft     ?? 0, color: STATUS_COLORS.draft },
            { label: "Completed", value: byStatus.completed ?? 0, color: STATUS_COLORS.completed },
            { label: "Cancelled", value: byStatus.cancelled ?? 0, color: STATUS_COLORS.cancelled },
        ].filter(s => s.value > 0);

        // Auto insights
        const insights = [];
        if (total === 0) {
            insights.push({ icon: "📋", text: "No mentorship data yet. Create your first mentorship to see analytics here.", accent: TEAL });
        } else {
            if (byStatus.active > 0)
                insights.push({ icon: "✅", text: `${activeRate}% of mentorships are currently active — ${byStatus.active} of ${total} ongoing.`, accent: "#10B981" });
            if (programs.length > 0)
                insights.push({ icon: "📚", text: `"${programs[0].name}" is the most common program with ${programs[0].count} mentorship${programs[0].count !== 1 ? "s" : ""}.`, accent: TEAL });
            if (counties.length > 0)
                insights.push({ icon: "📍", text: `${counties.length} ${counties.length === 1 ? "county" : "counties"} covered. ${counties[0].name} leads with ${counties[0].count} mentorship${counties[0].count !== 1 ? "s" : ""}.`, accent: "#6366F1" });
            if (recentMonths > 0)
                insights.push({ icon: "📈", text: `${recentMonths} new mentorship${recentMonths !== 1 ? "s were" : " was"} started in the last 3 months.`, accent: "#F59E0B" });
            if (byStatus.draft > 0)
                insights.push({ icon: "⏳", text: `${byStatus.draft} mentorship${byStatus.draft !== 1 ? "s" : ""} still in draft — consider activating ${byStatus.draft !== 1 ? "them" : "it"}.`, accent: "#F59E0B" });
            if (byStatus.completed > 0)
                insights.push({ icon: "🎓", text: `${completedRate}% overall completion rate — ${byStatus.completed} mentorship${byStatus.completed !== 1 ? "s" : ""} fully completed.`, accent: "#6366F1" });
            insights.push({ icon: "🏫", text: `Average of ${avgClasses} class${avgClasses !== "1.0" ? "es" : ""} per mentorship (${totalClasses} total).`, accent: TEAL });
            if (programs.length > 1)
                insights.push({ icon: "🔍", text: `${programs.length} programs are active. Consider consolidating focus if spread too thin.`, accent: "#9CA3AF" });
        }

        return { total, byStatus, donutSegments, programs, counties, months, totalClasses, avgClasses, activeRate, completedRate, insights };
    }, [mentorships]);

    if (loading) return (
        <div style={{ height: "100%", display: "flex", alignItems: "center", justifyContent: "center", background: T.bg }}>
            <div style={{ color: T.textSub, fontSize: 14 }}>Loading analytics…</div>
        </div>
    );

    if (error) return (
        <div style={{ height: "100%", display: "flex", alignItems: "center", justifyContent: "center", background: T.bg, padding: 32, textAlign: "center" }}>
            <div style={{ color: "#EF4444", fontSize: 14 }}>{error}</div>
        </div>
    );

    if (!stats) return null;

    const programMax = stats.programs[0]?.count ?? 1;
    const countyMax  = stats.counties[0]?.count  ?? 1;

    const programPalette = ["#0097A7", "#26C6DA", "#00838F", "#4DB6AC", "#006064"];
    const countyPalette  = ["#6366F1", "#818CF8", "#4F46E5", "#A5B4FC", "#312E81"];

    const timelineMax = Math.max(...stats.months.map(m => m.value), 1);

    return (
        <div style={{ height: "100%", overflowY: "auto", background: T.bg }}>
            {/* ── Hero ─────────────────────────────────────────────────────────── */}
            <div style={{ height: 6, background: T.bg }} />
            <div style={{
                background: T.gradientHero, padding: "28px 20px 24px",
                borderRadius: "0 0 28px 28px", margin: "0 6px",
                position: "relative", overflow: "hidden",
            }}>
                <div style={{ position: "absolute", width: 200, height: 200, borderRadius: "50%", background: "radial-gradient(circle, rgba(38,198,218,0.14) 0%, transparent 70%)", top: -60, right: -60 }} />
                <div style={{ position: "absolute", width: 120, height: 120, borderRadius: "50%", background: "radial-gradient(circle, rgba(0,131,143,0.1) 0%, transparent 70%)", bottom: -20, left: -20 }} />

                <div style={{ color: "white", fontSize: 22, fontWeight: 800, letterSpacing: -0.3 }}>Mentorship Reports</div>
                <div style={{ color: "rgba(255,255,255,0.45)", fontSize: 13, marginTop: 3, fontWeight: 500 }}>
                    {stats.total} mentorship{stats.total !== 1 ? "s" : ""} · {stats.counties.length} {stats.counties.length === 1 ? "county" : "counties"}
                </div>

                {/* Hero KPI pills */}
                <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr 1fr 1fr", gap: 8, marginTop: 18 }}>
                    {[
                        { label: "Total",    value: stats.total,                    glow: "rgba(255,255,255,0.2)"  },
                        { label: "Active",   value: stats.byStatus.active    ?? 0,  glow: "rgba(16,185,129,0.35)" },
                        { label: "Done",     value: stats.byStatus.completed ?? 0,  glow: "rgba(99,102,241,0.35)" },
                        { label: "Classes",  value: stats.totalClasses,             glow: "rgba(245,158,11,0.35)" },
                    ].map(k => (
                        <div key={k.label} style={{
                            padding: "11px 6px", borderRadius: 14, textAlign: "center",
                            background: k.glow, border: "1px solid rgba(255,255,255,0.15)",
                            backdropFilter: "blur(8px)",
                        }}>
                            <div style={{ color: "white", fontSize: 20, fontWeight: 800, lineHeight: 1 }}>{k.value}</div>
                            <div style={{ color: "rgba(255,255,255,0.55)", fontSize: 10, fontWeight: 600, marginTop: 3 }}>{k.label}</div>
                        </div>
                    ))}
                </div>
            </div>

            <div style={{ padding: "16px 14px 88px", display: "flex", flexDirection: "column", gap: 14 }}>

                {stats.total === 0 ? (
                    <Insight icon="📋" text="No mentorship data yet. Create your first mentorship to see analytics here." accent={TEAL} />
                ) : (<>

                    {/* ── Status breakdown ─────────────────────────────────────── */}
                    <Card title="Status Breakdown" subtitle="Distribution of your mentorships by current status">
                        <div style={{ display: "flex", alignItems: "center", gap: 18 }}>
                            {/* Donut */}
                            <div style={{ position: "relative", flexShrink: 0 }}>
                                <DonutChart segments={stats.donutSegments} size={124} strokeWidth={17} />
                                <div style={{
                                    position: "absolute", inset: 0,
                                    display: "flex", flexDirection: "column", alignItems: "center", justifyContent: "center",
                                    pointerEvents: "none",
                                }}>
                                    <div style={{ fontSize: 24, fontWeight: 800, color: T.text, lineHeight: 1 }}>{stats.total}</div>
                                    <div style={{ fontSize: 10, color: T.textSub, fontWeight: 600, marginTop: 2 }}>total</div>
                                </div>
                            </div>

                            {/* Legend */}
                            <div style={{ flex: 1 }}>
                                {stats.donutSegments.map(seg => {
                                    const pct = Math.round((seg.value / stats.total) * 100);
                                    return (
                                        <div key={seg.label} style={{ display: "flex", alignItems: "center", gap: 8, marginBottom: 8 }}>
                                            <div style={{ width: 10, height: 10, borderRadius: "50%", background: seg.color, flexShrink: 0 }} />
                                            <span style={{ flex: 1, fontSize: 13, color: T.text }}>{seg.label}</span>
                                            <span style={{ fontSize: 13, fontWeight: 700, color: T.text, marginRight: 4 }}>{seg.value}</span>
                                            <span style={{
                                                fontSize: 10, fontWeight: 700, padding: "2px 6px", borderRadius: 8,
                                                background: `${seg.color}18`, color: seg.color,
                                            }}>{pct}%</span>
                                        </div>
                                    );
                                })}
                            </div>
                        </div>

                        {/* Stacked bar strip */}
                        <div style={{ display: "flex", gap: 3, marginTop: 14, height: 8, borderRadius: 4, overflow: "hidden" }}>
                            {stats.donutSegments.map(seg => (
                                <div key={seg.label} style={{ flex: seg.value, background: seg.color, minWidth: seg.value > 0 ? 6 : 0 }} />
                            ))}
                        </div>
                    </Card>

                    {/* ── Timeline ─────────────────────────────────────────────── */}
                    <Card title="Activity Timeline" subtitle="Mentorships started — last 6 months">
                        <AreaChart data={stats.months} height={72} color={TEAL} />

                        {/* Month labels + values */}
                        <div style={{ display: "flex", marginTop: 8 }}>
                            {stats.months.map(m => (
                                <div key={m.key} style={{ flex: 1, textAlign: "center" }}>
                                    <div style={{
                                        fontSize: 14, fontWeight: 700,
                                        color: m.value > 0 ? TEAL : T.textMuted,
                                        lineHeight: 1,
                                    }}>{m.value > 0 ? m.value : "–"}</div>
                                    <div style={{ fontSize: 10, color: T.textMuted, marginTop: 3 }}>{m.label}</div>
                                </div>
                            ))}
                        </div>

                        {/* Heatmap row */}
                        <div style={{ display: "flex", gap: 4, marginTop: 10 }}>
                            {stats.months.map(m => {
                                const intensity = timelineMax > 0 ? m.value / timelineMax : 0;
                                return (
                                    <div key={m.key} style={{
                                        flex: 1, height: 24, borderRadius: 6,
                                        background: m.value > 0
                                            ? `rgba(0,151,167,${0.15 + intensity * 0.75})`
                                            : "#F0F4F6",
                                        display: "flex", alignItems: "center", justifyContent: "center",
                                    }} />
                                );
                            })}
                        </div>
                    </Card>

                    {/* ── Rate cards row ────────────────────────────────────────── */}
                    <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 10 }}>
                        {/* Completion rate */}
                        <div style={{ background: T.card, borderRadius: T.radius, padding: "14px 16px", boxShadow: T.shadowCard, border: `1px solid ${T.border}` }}>
                            <div style={{ fontSize: 12, color: T.textSub, marginBottom: 6, fontWeight: 600 }}>Completion Rate</div>
                            <div style={{ fontSize: 28, fontWeight: 800, color: STATUS_COLORS.completed, lineHeight: 1 }}>
                                {stats.completedRate}%
                            </div>
                            <div style={{ height: 5, background: "#EEF2F2", borderRadius: 3, marginTop: 8 }}>
                                <div style={{ height: "100%", width: `${stats.completedRate}%`, background: STATUS_COLORS.completed, borderRadius: 3 }} />
                            </div>
                            <div style={{ fontSize: 11, color: T.textMuted, marginTop: 5 }}>{stats.byStatus.completed ?? 0} completed</div>
                        </div>

                        {/* Active rate */}
                        <div style={{ background: T.card, borderRadius: T.radius, padding: "14px 16px", boxShadow: T.shadowCard, border: `1px solid ${T.border}` }}>
                            <div style={{ fontSize: 12, color: T.textSub, marginBottom: 6, fontWeight: 600 }}>Active Rate</div>
                            <div style={{ fontSize: 28, fontWeight: 800, color: STATUS_COLORS.active, lineHeight: 1 }}>
                                {stats.activeRate}%
                            </div>
                            <div style={{ height: 5, background: "#EEF2F2", borderRadius: 3, marginTop: 8 }}>
                                <div style={{ height: "100%", width: `${stats.activeRate}%`, background: STATUS_COLORS.active, borderRadius: 3 }} />
                            </div>
                            <div style={{ fontSize: 11, color: T.textMuted, marginTop: 5 }}>{stats.byStatus.active ?? 0} active now</div>
                        </div>
                    </div>

                    {/* ── Avg classes card ────────────────────────────────────── */}
                    <div style={{ background: T.card, borderRadius: T.radius, padding: "14px 16px", boxShadow: T.shadowCard, border: `1px solid ${T.border}`, display: "flex", alignItems: "center", gap: 16 }}>
                        <div style={{ width: 50, height: 50, borderRadius: 14, background: `${TEAL}15`, display: "flex", alignItems: "center", justifyContent: "center", flexShrink: 0 }}>
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke={TEAL} strokeWidth="2">
                                <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>
                                <rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/>
                            </svg>
                        </div>
                        <div style={{ flex: 1 }}>
                            <div style={{ fontSize: 12, color: T.textSub, fontWeight: 600 }}>Avg Classes per Mentorship</div>
                            <div style={{ fontSize: 26, fontWeight: 800, color: TEAL, lineHeight: 1.1, marginTop: 2 }}>{stats.avgClasses}</div>
                        </div>
                        <div style={{ textAlign: "right" }}>
                            <div style={{ fontSize: 18, fontWeight: 800, color: T.text }}>{stats.totalClasses}</div>
                            <div style={{ fontSize: 11, color: T.textMuted }}>total classes</div>
                        </div>
                    </div>

                    {/* ── By Program ───────────────────────────────────────────── */}
                    {stats.programs.length > 0 && (
                        <Card title="By Program" subtitle={`${stats.programs.length} program${stats.programs.length !== 1 ? "s" : ""} in use`}>
                            {stats.programs.slice(0, 7).map((p, i) => (
                                <HBar
                                    key={p.name}
                                    label={p.name}
                                    value={p.count}
                                    max={programMax}
                                    total={stats.total}
                                    color={programPalette[i % programPalette.length]}
                                />
                            ))}
                            {stats.programs.length > 7 && (
                                <div style={{ fontSize: 12, color: T.textMuted, textAlign: "center", marginTop: 6 }}>
                                    +{stats.programs.length - 7} more programs
                                </div>
                            )}
                        </Card>
                    )}

                    {/* ── By County ────────────────────────────────────────────── */}
                    {stats.counties.length > 0 && (
                        <Card title="County Coverage" subtitle={`Mentorships across ${stats.counties.length} ${stats.counties.length === 1 ? "county" : "counties"}`}>
                            {stats.counties.slice(0, 7).map((c, i) => (
                                <HBar
                                    key={c.name}
                                    label={c.name}
                                    value={c.count}
                                    max={countyMax}
                                    total={stats.total}
                                    color={countyPalette[i % countyPalette.length]}
                                />
                            ))}
                            {stats.counties.length > 7 && (
                                <div style={{ fontSize: 12, color: T.textMuted, textAlign: "center", marginTop: 6 }}>
                                    +{stats.counties.length - 7} more counties
                                </div>
                            )}
                        </Card>
                    )}

                    {/* ── Draft pipeline ────────────────────────────────────────── */}
                    {(stats.byStatus.draft ?? 0) > 0 && (
                        <Card title="Draft Pipeline" subtitle="Mentorships pending activation">
                            <div style={{ display: "flex", alignItems: "center", gap: 14 }}>
                                <div style={{
                                    width: 52, height: 52, borderRadius: "50%",
                                    background: `${STATUS_COLORS.draft}15`,
                                    border: `3px solid ${STATUS_COLORS.draft}40`,
                                    display: "flex", alignItems: "center", justifyContent: "center",
                                    flexShrink: 0,
                                }}>
                                    <span style={{ fontSize: 22, fontWeight: 800, color: STATUS_COLORS.draft }}>{stats.byStatus.draft}</span>
                                </div>
                                <div>
                                    <div style={{ fontSize: 14, fontWeight: 700, color: T.text }}>
                                        {stats.byStatus.draft} draft mentorship{stats.byStatus.draft !== 1 ? "s" : ""}
                                    </div>
                                    <div style={{ fontSize: 12, color: T.textSub, marginTop: 2, lineHeight: 1.5 }}>
                                        {Math.round(((stats.byStatus.draft ?? 0) / stats.total) * 100)}% of all mentorships are still in draft. Activate them to begin tracking progress.
                                    </div>
                                </div>
                            </div>
                        </Card>
                    )}

                    {/* ── Key Insights ─────────────────────────────────────────── */}
                    <Card title="Key Insights" subtitle="Auto-generated from your data">
                        <div style={{ display: "flex", flexDirection: "column", gap: 8 }}>
                            {stats.insights.map((ins, i) => (
                                <Insight key={i} icon={ins.icon} text={ins.text} accent={ins.accent} />
                            ))}
                        </div>
                    </Card>

                </>)}
            </div>
        </div>
    );
}
