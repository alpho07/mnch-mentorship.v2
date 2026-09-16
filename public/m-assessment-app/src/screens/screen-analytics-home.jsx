/**
 * screen-analytics-home.jsx
 * Mobile-native replica of /analytics/dashboard?mode=training|mentorship
 * Sections: KPI strip → Insights → Monthly trend → Status breakdown →
 *           Top counties → Department breakdown → Cadre breakdown → Facility types
 */
import { useState, useEffect, useMemo } from 'react';
import api from '../services/api.service.js';
import { KENYA_MAP } from '../data/kenya-counties-svg.js';
import { SectionIcon } from '../components/section-icons.jsx';

// ── Design tokens ──────────────────────────────────────────────────────────────
const C = {
    teal:    '#0097A7',
    tealDk:  '#004D40',
    tealLt:  '#E0F7FA',
    amber:   '#F59E0B',
    emerald: '#10B981',
    violet:  '#8B5CF6',
    blue:    '#2563EB',
    red:     '#EF4444',
    text:    '#1E293B',
    sub:     '#64748B',
    border:  '#E2E8F0',
    bg:      '#F8FAFC',
    card:    '#FFFFFF',
};

const STATUS_COLORS = {
    active:    { bg: '#D1FAE5', color: '#065F46' },
    ongoing:   { bg: '#D1FAE5', color: '#065F46' },
    completed: { bg: '#DBEAFE', color: '#1E40AF' },
    draft:     { bg: '#FEF3C7', color: '#92400E' },
    cancelled: { bg: '#FEE2E2', color: '#991B1B' },
};

const INSIGHT_COLORS = {
    success: { border: '#10B981', bg: '#ECFDF5', icon: '#065F46' },
    warning: { border: '#F59E0B', bg: '#FFFBEB', icon: '#92400E' },
    danger:  { border: '#EF4444', bg: '#FEF2F2', icon: '#991B1B' },
    primary: { border: '#0097A7', bg: '#E0F7FA', icon: '#004D40' },
    info:    { border: '#2563EB', bg: '#EFF6FF', icon: '#1D4ED8' },
};

// ── Helpers ────────────────────────────────────────────────────────────────────
function fmt(n) { return (n ?? 0).toLocaleString(); }

// ── Sub-components ─────────────────────────────────────────────────────────────

function KpiCard({ label, value, color, bg, loading, suffix = '' }) {
    return (
        <div style={{
            background: card => card, flex: 1, minWidth: 0,
            background: C.card, borderRadius: 14,
            borderTop: `3px solid ${color}`,
            padding: '14px 12px 12px',
            boxShadow: '0 2px 10px rgba(0,0,0,0.07)',
        }}>
            <div style={{ fontSize: 22, fontWeight: 800, color: C.text, lineHeight: 1, marginBottom: 4 }}>
                {loading ? <span style={{ color: C.border }}>—</span> : (fmt(value) + suffix)}
            </div>
            <div style={{ fontSize: 11, fontWeight: 700, color: C.sub, textTransform: 'uppercase', letterSpacing: '0.04em' }}>
                {label}
            </div>
        </div>
    );
}

function SectionTitle({ icon, children }) {
    return (
        <div style={{ display: 'flex', alignItems: 'center', gap: 7, marginBottom: 10 }}>
            <span style={{ fontSize: 16 }}>{icon}</span>
            <span style={{ fontSize: 14, fontWeight: 700, color: C.text }}>{children}</span>
        </div>
    );
}

function Card({ children, style = {} }) {
    return (
        <div style={{
            background: C.card, borderRadius: 14,
            border: `1px solid ${C.border}`,
            boxShadow: '0 2px 10px rgba(0,0,0,0.06)',
            overflow: 'hidden',
            ...style,
        }}>
            {children}
        </div>
    );
}

function CardHeader({ children, sub }) {
    return (
        <div style={{
            padding: '12px 14px 8px',
            borderBottom: `1px solid ${C.border}`,
            background: C.bg,
        }}>
            <div style={{ fontSize: 13, fontWeight: 700, color: C.text }}>{children}</div>
            {sub && <div style={{ fontSize: 11, color: C.sub, marginTop: 2 }}>{sub}</div>}
        </div>
    );
}

// Mini bar chart for monthly trend
function MonthlyTrend({ data }) {
    const max = Math.max(...data.map(d => d.count), 1);
    return (
        <div style={{ display: 'flex', alignItems: 'flex-end', gap: 4, height: 80 }}>
            {data.map((d, i) => (
                <div key={i} style={{ flex: 1, display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 3 }}>
                    <div style={{ fontSize: 9, color: C.sub, fontWeight: 600 }}>{d.count > 0 ? d.count : ''}</div>
                    <div style={{
                        width: '100%', borderRadius: '4px 4px 0 0',
                        background: `linear-gradient(180deg, ${C.teal} 0%, #26C6DA 100%)`,
                        height: `${Math.max((d.count / max) * 60, d.count > 0 ? 4 : 2)}px`,
                        opacity: d.count === 0 ? 0.15 : 1,
                        transition: 'height 0.4s ease',
                    }} />
                    <div style={{ fontSize: 9, color: C.sub, fontWeight: 600 }}>{d.month}</div>
                </div>
            ))}
        </div>
    );
}

// Kenya SVG choropleth map
function KenyaChoroplethMap({ countyMap, personLabel }) {
    const [selected, setSelected] = useState(null);

    const byName = useMemo(() => {
        const m = {};
        (countyMap ?? []).forEach(c => { m[c.name.toLowerCase()] = c; });
        return m;
    }, [countyMap]);

    const maxCount = useMemo(() => Math.max(...(countyMap ?? []).map(c => c.count), 1), [countyMap]);

    function heatFill(name) {
        const d = byName[name.toLowerCase()];
        if (!d) return '#E0F7FA';
        const r = d.count / maxCount;
        if (r > 0.75) return '#004D40';
        if (r > 0.5)  return '#0097A7';
        if (r > 0.25) return '#26C6DA';
        return '#80DEEA';
    }
    function textFill(name) {
        const d = byName[name.toLowerCase()];
        if (!d) return null;
        return d.count / maxCount > 0.5 ? 'white' : '#004D40';
    }

    const selData = selected ? byName[selected.name.toLowerCase()] : null;

    return (
        <div>
            <svg
                viewBox={KENYA_MAP.viewBox}
                style={{ width: '100%', height: 'auto', display: 'block', borderRadius: 8, overflow: 'visible' }}
            >
                {KENYA_MAP.counties.map((county, i) => (
                    <path
                        key={i}
                        d={county.d}
                        fill={heatFill(county.name)}
                        stroke={selected?.name === county.name ? '#F59E0B' : 'white'}
                        strokeWidth={selected?.name === county.name ? 1.5 : 0.6}
                        strokeLinejoin="round"
                        onClick={() => setSelected(selected?.name === county.name ? null : county)}
                        style={{ cursor: 'pointer', transition: 'fill 0.25s' }}
                    />
                ))}
                {/* Count labels for active counties */}
                {KENYA_MAP.counties.map((county, i) => {
                    const tf = textFill(county.name);
                    const d = byName[county.name.toLowerCase()];
                    if (!d) return null;
                    return (
                        <text key={`t${i}`} x={county.cx} y={county.cy + 3}
                            textAnchor="middle" fontSize="6"
                            fill={tf} fontWeight="bold" pointerEvents="none"
                            style={{ userSelect: 'none' }}>
                            {d.count}
                        </text>
                    );
                })}
            </svg>

            {/* Selected county tooltip */}
            {selected && (
                <div style={{
                    background: '#1E293B', color: 'white',
                    borderRadius: 10, padding: '8px 12px', marginTop: 8,
                    display: 'flex', alignItems: 'center', gap: 8,
                    animation: 'fadeIn 0.15s ease',
                }}>
                    <div style={{ width: 10, height: 10, borderRadius: 3, background: heatFill(selected.name), flexShrink: 0, border: '1px solid rgba(255,255,255,0.3)' }} />
                    <div style={{ flex: 1 }}>
                        <span style={{ fontSize: 13, fontWeight: 700 }}>{selected.name}</span>
                        {selData
                            ? <span style={{ fontSize: 11, opacity: 0.75, marginLeft: 8 }}>{selData.count} {personLabel?.toLowerCase()}</span>
                            : <span style={{ fontSize: 11, opacity: 0.5, marginLeft: 8 }}>No data</span>
                        }
                    </div>
                    <button onClick={() => setSelected(null)} style={{ background: 'rgba(255,255,255,0.15)', border: 'none', borderRadius: 4, color: 'white', width: 18, height: 18, cursor: 'pointer', fontSize: 10, display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0 }}>✕</button>
                </div>
            )}

            {/* Legend */}
            <div style={{ display: 'flex', gap: 10, marginTop: 10, flexWrap: 'wrap' }}>
                {[['#E0F7FA','No data'],['#80DEEA','Low'],['#26C6DA','Medium'],['#0097A7','High'],['#004D40','Top']].map(([color, label]) => (
                    <div key={label} style={{ display: 'flex', alignItems: 'center', gap: 3 }}>
                        <div style={{ width: 10, height: 10, borderRadius: 3, background: color, border: '1px solid rgba(0,0,0,0.1)' }} />
                        <span style={{ fontSize: 10, color: C.sub, fontWeight: 600 }}>{label}</span>
                    </div>
                ))}
            </div>
        </div>
    );
}

// Horizontal bar row
function BarRow({ label, count, max, color = C.teal }) {
    const pct = max > 0 ? Math.round((count / max) * 100) : 0;
    return (
        <div style={{ marginBottom: 10 }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 3 }}>
                <span style={{ fontSize: 12, color: C.text, fontWeight: 600, flex: 1, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap', paddingRight: 8 }}>{label}</span>
                <span style={{ fontSize: 12, color: C.sub, fontWeight: 700, flexShrink: 0 }}>{fmt(count)}</span>
            </div>
            <div style={{ background: C.border, borderRadius: 99, height: 6, overflow: 'hidden' }}>
                <div style={{
                    width: `${pct}%`, height: '100%',
                    background: `linear-gradient(90deg, ${color}, ${color}99)`,
                    borderRadius: 99, transition: 'width 0.6s ease',
                }} />
            </div>
        </div>
    );
}

// Status chips row
function StatusBreakdown({ data }) {
    const total = data.reduce((s, d) => s + d.count, 0);
    if (total === 0) return null;
    return (
        <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6 }}>
            {data.map((d, i) => {
                const sc = STATUS_COLORS[d.status] ?? STATUS_COLORS.draft;
                const pct = total > 0 ? Math.round((d.count / total) * 100) : 0;
                return (
                    <div key={i} style={{
                        display: 'flex', alignItems: 'center', gap: 6,
                        background: sc.bg, color: sc.color,
                        borderRadius: 10, padding: '6px 12px',
                        fontSize: 12, fontWeight: 700,
                    }}>
                        <span style={{ textTransform: 'capitalize' }}>{d.status}</span>
                        <span style={{ opacity: 0.7 }}>·</span>
                        <span>{d.count}</span>
                        <span style={{ opacity: 0.6, fontSize: 11 }}>({pct}%)</span>
                    </div>
                );
            })}
        </div>
    );
}

// Insight card
function InsightCard({ insight }) {
    const ic = INSIGHT_COLORS[insight.type] ?? INSIGHT_COLORS.primary;
    const ICONS = {
        'check-circle': '✅', 'exclamation-triangle': '⚠️', 'exclamation-circle': '⚠️',
        'times-circle': '❌', 'users': '👥', 'user': '👤',
        'chart-line': '📈', 'chart-bar': '📊', 'info-circle': 'ℹ️', 'clock': '🕐',
    };
    return (
        <div style={{
            display: 'flex', gap: 10, alignItems: 'flex-start',
            background: ic.bg, borderLeft: `4px solid ${ic.border}`,
            borderRadius: '0 10px 10px 0', padding: '10px 12px',
            marginBottom: 8,
        }}>
            <span style={{ fontSize: 18, flexShrink: 0 }}>{ICONS[insight.icon] ?? 'ℹ️'}</span>
            <p style={{ fontSize: 13, color: C.text, margin: 0, lineHeight: 1.5 }}>{insight.text}</p>
        </div>
    );
}

// Skeleton loader row
function Skeleton({ height = 14, width = '100%', radius = 6, mb = 8 }) {
    return (
        <div style={{
            width, height, borderRadius: radius,
            background: `linear-gradient(90deg, #E2E8F0 25%, #F1F5F9 50%, #E2E8F0 75%)`,
            backgroundSize: '200% 100%',
            animation: 'shimmer 1.4s infinite',
            marginBottom: mb,
        }} />
    );
}

// ── Assessment-specific chart components ───────────────────────────────────────

// Stacked bar chart — 3 assessment types per month
function AssessmentMonthlyTrend({ data }) {
    const last6   = data.slice(-6);
    const maxTotal = Math.max(...last6.map(d => d.baseline + d.midline + d.endline), 1);
    return (
        <div>
            <div style={{ display: 'flex', alignItems: 'flex-end', gap: 4, height: 80 }}>
                {last6.map((d, i) => {
                    const total = d.baseline + d.midline + d.endline;
                    const barH  = Math.max((total / maxTotal) * 60, total > 0 ? 4 : 2);
                    return (
                        <div key={i} style={{ flex: 1, display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 3 }}>
                            <div style={{ fontSize: 9, color: C.sub, fontWeight: 600 }}>{total > 0 ? total : ''}</div>
                            <div style={{ width: '100%', height: barH, borderRadius: '3px 3px 0 0', overflow: 'hidden', display: 'flex', flexDirection: 'column', opacity: total === 0 ? 0.15 : 1, transition: 'height 0.4s ease' }}>
                                {total > 0 ? (
                                    <>
                                        <div style={{ flex: d.endline,   background: '#8B5CF6', minHeight: d.endline   > 0 ? 2 : 0 }} />
                                        <div style={{ flex: d.midline,   background: '#F59E0B', minHeight: d.midline   > 0 ? 2 : 0 }} />
                                        <div style={{ flex: d.baseline,  background: C.teal,    minHeight: d.baseline  > 0 ? 2 : 0 }} />
                                    </>
                                ) : (
                                    <div style={{ flex: 1, background: C.border }} />
                                )}
                            </div>
                            <div style={{ fontSize: 9, color: C.sub, fontWeight: 600 }}>{d.short}</div>
                        </div>
                    );
                })}
            </div>
            <div style={{ display: 'flex', gap: 14, marginTop: 10, justifyContent: 'center' }}>
                {[[C.teal,'Baseline'],['#F59E0B','Midline'],['#8B5CF6','Endline']].map(([color, label]) => (
                    <div key={label} style={{ display: 'flex', alignItems: 'center', gap: 4 }}>
                        <div style={{ width: 10, height: 10, borderRadius: 3, background: color }} />
                        <span style={{ fontSize: 10, color: C.sub, fontWeight: 600 }}>{label}</span>
                    </div>
                ))}
            </div>
        </div>
    );
}

// Green / Yellow / Red grade chips + proportional bar
function GradeDistribution({ data, total }) {
    const GRADES = [
        { key: 'green',  label: 'Good',  sub: '≥80%', color: '#10B981', bg: '#D1FAE5', text: '#065F46' },
        { key: 'yellow', label: 'Fair',  sub: '50–79%', color: '#F59E0B', bg: '#FEF3C7', text: '#92400E' },
        { key: 'red',    label: 'Poor',  sub: '<50%',  color: '#EF4444', bg: '#FEE2E2', text: '#991B1B' },
    ];
    return (
        <div>
            <div style={{ display: 'flex', gap: 8, marginBottom: 12 }}>
                {GRADES.map(({ key, label, sub, color, bg, text }) => {
                    const count = data[key] ?? 0;
                    const pct   = total > 0 ? Math.round((count / total) * 100) : 0;
                    return (
                        <div key={key} style={{ flex: 1, background: bg, borderRadius: 12, padding: '10px 8px', textAlign: 'center', border: `1px solid ${color}33` }}>
                            <div style={{ fontSize: 22, fontWeight: 800, color: text, lineHeight: 1 }}>{count}</div>
                            <div style={{ fontSize: 10, fontWeight: 700, color: text, opacity: 0.8, marginTop: 3 }}>{pct}%</div>
                            <div style={{ fontSize: 11, fontWeight: 700, color: text, marginTop: 2 }}>{label}</div>
                            <div style={{ fontSize: 9, color: text, opacity: 0.65 }}>{sub}</div>
                        </div>
                    );
                })}
            </div>
            <div style={{ height: 8, borderRadius: 99, overflow: 'hidden', display: 'flex' }}>
                {GRADES.map(({ key, color }) => {
                    const flex = total > 0 ? (data[key] ?? 0) : 0;
                    return flex > 0 ? <div key={key} style={{ flex, background: color }} /> : null;
                })}
            </div>
            <div style={{ display: 'flex', justifyContent: 'space-between', marginTop: 4 }}>
                <span style={{ fontSize: 10, color: C.sub }}>{total} assessed</span>
                <span style={{ fontSize: 10, color: C.sub }}>≥80% = Good</span>
            </div>
        </div>
    );
}

// Section name + icon + score bar row
function SectionAvgRow({ section }) {
    const pct   = section.percentage ?? 0;
    const color = section.color ?? (pct >= 80 ? '#10B981' : pct >= 50 ? '#F59E0B' : '#EF4444');
    return (
        <div style={{ marginBottom: 12 }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 4 }}>
                <SectionIcon code={section.code} size={14} />
                <span style={{ fontSize: 12, color: C.text, fontWeight: 600, flex: 1, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{section.name}</span>
                <span style={{ fontSize: 12, fontWeight: 700, color, flexShrink: 0 }}>{pct}%</span>
            </div>
            <div style={{ background: C.border, borderRadius: 99, height: 6, overflow: 'hidden' }}>
                <div style={{ width: `${pct}%`, height: '100%', background: `linear-gradient(90deg, ${color}, ${color}88)`, borderRadius: 99, transition: 'width 0.6s ease' }} />
            </div>
        </div>
    );
}

// ── Main export ────────────────────────────────────────────────────────────────
export function AnalyticsHomeScreen({ mode, user }) {
    const [data, setData]         = useState(null);
    const [loading, setLoading]   = useState(true);
    const [error, setError]       = useState(null);
    const [year, setYear]         = useState('');
    const [countyView, setCountyView] = useState('bars');

    useEffect(() => {
        setLoading(true);
        setError(null);
        api.analytics.summary(mode, year)
            .then(d => setData(d))
            .catch(e => setError(e?.message ?? 'Failed to load analytics'))
            .finally(() => setLoading(false));
    }, [mode, year]);

    const stats     = data?.summaryStats ?? {};
    const chart     = data?.chartData ?? {};
    const status    = data?.statusData ?? [];
    const counties  = data?.topCounties ?? [];
    const countyMap = data?.countyMap ?? [];
    const insights  = data?.insights ?? [];

    const monthlyMax = useMemo(() => Math.max(...(chart.monthly ?? []).map(d => d.count), 1), [chart.monthly]);
    const deptMax    = useMemo(() => (chart.departments ?? [])[0]?.count ?? 1, [chart.departments]);
    const cadreMax   = useMemo(() => (chart.cadres ?? [])[0]?.count ?? 1, [chart.cadres]);
    const countyMax  = useMemo(() => (counties)[0]?.count ?? 1, [counties]);

    const isMentorship = mode === 'mentorship';
    const programLabel = isMentorship ? 'Mentorships' : 'Trainings';
    const personLabel  = isMentorship ? 'Mentees' : 'Participants';

    return (
        <div style={{ paddingBottom: 16 }}>
            <style>{`@keyframes shimmer { 0%{background-position:200% 0} 100%{background-position:-200% 0} }`}</style>

            {/* ── Hero greeting ── */}
            <div style={{
                background: `linear-gradient(135deg, ${C.tealDk} 0%, #00695C 40%, ${C.teal} 80%, #26C6DA 100%)`,
                padding: '18px 16px 28px',
            }}>
                <div style={{ fontSize: 11, fontWeight: 800, color: 'rgba(255,255,255,0.7)', letterSpacing: '0.1em', textTransform: 'uppercase', marginBottom: 4 }}>
                    MNCH Analytics
                </div>
                <div style={{ fontSize: 20, fontWeight: 800, color: '#fff', marginBottom: 2 }}>
                    {programLabel} Dashboard
                </div>
                <div style={{ fontSize: 13, color: 'rgba(255,255,255,0.75)' }}>
                    {loading ? 'Loading data…' : `Kenya healthcare ${mode} analytics`}
                </div>
            </div>

            {/* ── KPI strip — pulled up over hero ── */}
            <div style={{ padding: '0 12px', marginTop: -16 }}>
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(2, 1fr)', gap: 8, marginBottom: 12 }}>
                    <KpiCard label={programLabel}   value={stats.totalPrograms}    color={C.teal}    loading={loading} />
                    <KpiCard label={personLabel}     value={stats.totalParticipants} color={C.amber}   loading={loading} />
                    <KpiCard label="Facilities"      value={stats.totalFacilities}  color={C.emerald} loading={loading} />
                    <KpiCard label="Coverage"        value={stats.facilityCoverage} color={C.violet}  loading={loading} suffix="%" />
                </div>

                {/* Avg participants pill */}
                {!loading && (stats.avgParticipants ?? 0) > 0 && (
                    <div style={{
                        background: C.tealLt, border: `1px solid ${C.teal}33`,
                        borderRadius: 10, padding: '8px 14px', marginBottom: 12,
                        display: 'flex', alignItems: 'center', gap: 8,
                    }}>
                        <span style={{ fontSize: 20 }}>📊</span>
                        <span style={{ fontSize: 13, color: C.tealDk, fontWeight: 600 }}>
                            Avg <strong>{stats.avgParticipants}</strong> {personLabel.toLowerCase()} per {isMentorship ? 'mentorship' : 'training'}
                        </span>
                    </div>
                )}

                {/* Error state */}
                {error && (
                    <div style={{ background: '#FEF2F2', border: '1px solid #EF444433', borderRadius: 12, padding: '12px 14px', marginBottom: 12 }}>
                        <p style={{ color: '#991B1B', fontSize: 13, margin: 0 }}>⚠️ {error}</p>
                    </div>
                )}

                {/* ── Insights ── */}
                {!loading && insights.length > 0 && (
                    <div style={{ marginBottom: 16 }}>
                        <SectionTitle icon="💡">Intelligent Insights</SectionTitle>
                        {insights.map((ins, i) => <InsightCard key={i} insight={ins} />)}
                    </div>
                )}
                {loading && (
                    <div style={{ marginBottom: 16 }}>
                        <Skeleton height={12} width="40%" mb={10} />
                        <Skeleton height={52} mb={8} radius={10} />
                        <Skeleton height={52} mb={8} radius={10} />
                    </div>
                )}

                {/* ── Monthly trend ── */}
                <div style={{ marginBottom: 14 }}>
                    <SectionTitle icon="📈">6-Month Activity Trend</SectionTitle>
                    <Card>
                        <CardHeader sub={`Monthly ${personLabel.toLowerCase()} enrolled/registered`}>
                            12-Month {isMentorship ? 'Activity' : 'Enrollment'} Trend
                        </CardHeader>
                        <div style={{ padding: '14px 14px 10px' }}>
                            {loading
                                ? <Skeleton height={80} radius={8} mb={0} />
                                : <MonthlyTrend data={chart.monthly ?? []} />
                            }
                        </div>
                    </Card>
                </div>

                {/* ── Status breakdown ── */}
                {!loading && status.length > 0 && (
                    <div style={{ marginBottom: 14 }}>
                        <SectionTitle icon="🔢">{programLabel} Status</SectionTitle>
                        <Card style={{ padding: 14 }}>
                            <StatusBreakdown data={status} />
                        </Card>
                    </div>
                )}

                {/* ── County coverage ── */}
                {!loading && (counties.length > 0 || countyMap.length > 0) && (
                    <div style={{ marginBottom: 14 }}>
                        <SectionTitle icon="🗺️">County Coverage by {personLabel}</SectionTitle>
                        <Card>
                            <div style={{ padding: '10px 14px 8px', borderBottom: `1px solid ${C.border}`, background: C.bg, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                                <div>
                                    <div style={{ fontSize: 13, fontWeight: 700, color: C.text }}>Geographic Reach</div>
                                    <div style={{ fontSize: 11, color: C.sub, marginTop: 2 }}>Leading counties for {mode}</div>
                                </div>
                                <div style={{ display: 'flex', background: '#E2E8F0', borderRadius: 8, padding: 2, gap: 2 }}>
                                    {[['bars','📊 Bars'],['map','🗺️ Map']].map(([v, label]) => (
                                        <button key={v} onClick={() => setCountyView(v)} style={{
                                            padding: '4px 10px', border: 'none', borderRadius: 6,
                                            fontSize: 11, fontWeight: 700, cursor: 'pointer',
                                            background: countyView === v ? 'white' : 'transparent',
                                            color: countyView === v ? C.teal : C.sub,
                                            boxShadow: countyView === v ? '0 1px 3px rgba(0,0,0,0.1)' : 'none',
                                            transition: 'all 0.15s',
                                        }}>{label}</button>
                                    ))}
                                </div>
                            </div>
                            <div style={{ padding: '12px 14px' }}>
                                {countyView === 'bars'
                                    ? counties.slice(0, 10).map((c, i) => (
                                        <BarRow key={i} label={c.name} count={c.count} max={countyMax} color={C.teal} />
                                      ))
                                    : <KenyaChoroplethMap countyMap={countyMap.length > 0 ? countyMap : counties} personLabel={personLabel} />
                                }
                            </div>
                        </Card>
                    </div>
                )}

                {/* ── Department breakdown ── */}
                {!loading && (chart.departments ?? []).length > 0 && (
                    <div style={{ marginBottom: 14 }}>
                        <SectionTitle icon="🏥">Participation by Department</SectionTitle>
                        <Card>
                            <CardHeader sub={`Top departments by ${personLabel.toLowerCase()}`}>Department Breakdown</CardHeader>
                            <div style={{ padding: '12px 14px' }}>
                                {(chart.departments ?? []).slice(0, 8).map((d, i) => (
                                    <BarRow key={i} label={d.name} count={d.count} max={deptMax} color={C.blue} />
                                ))}
                            </div>
                        </Card>
                    </div>
                )}

                {/* ── Cadre breakdown ── */}
                {!loading && (chart.cadres ?? []).length > 0 && (
                    <div style={{ marginBottom: 14 }}>
                        <SectionTitle icon="👨‍⚕️">Participation by Cadre</SectionTitle>
                        <Card>
                            <CardHeader sub="Professional role distribution">Cadre Breakdown</CardHeader>
                            <div style={{ padding: '12px 14px' }}>
                                {(chart.cadres ?? []).slice(0, 8).map((d, i) => (
                                    <BarRow key={i} label={d.name} count={d.count} max={cadreMax} color={C.violet} />
                                ))}
                            </div>
                        </Card>
                    </div>
                )}

                {/* ── Facility type coverage ── */}
                {!loading && (chart.facilityTypes ?? []).length > 0 && (
                    <div style={{ marginBottom: 14 }}>
                        <SectionTitle icon="🏨">Coverage by Facility Type</SectionTitle>
                        <Card>
                            <CardHeader sub="Penetration by facility category">Facility Type Coverage</CardHeader>
                            <div style={{ padding: '12px 14px' }}>
                                {(chart.facilityTypes ?? []).slice(0, 8).map((ft, i) => (
                                    <div key={i} style={{ marginBottom: 12 }}>
                                        <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 3 }}>
                                            <span style={{ fontSize: 12, color: C.text, fontWeight: 600, flex: 1, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap', paddingRight: 8 }}>{ft.name}</span>
                                            <span style={{ fontSize: 12, color: C.sub, fontWeight: 700, flexShrink: 0 }}>{ft.covered}/{ft.total} · {ft.pct}%</span>
                                        </div>
                                        <div style={{ background: C.border, borderRadius: 99, height: 6, overflow: 'hidden' }}>
                                            <div style={{
                                                width: `${ft.pct}%`, height: '100%',
                                                background: `linear-gradient(90deg, ${C.emerald}, ${C.emerald}88)`,
                                                borderRadius: 99,
                                            }} />
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </Card>
                    </div>
                )}

                {/* Empty state */}
                {!loading && !error && (stats.totalPrograms === 0) && (
                    <div style={{ textAlign: 'center', padding: '32px 16px' }}>
                        <div style={{ fontSize: 48, marginBottom: 12 }}>📋</div>
                        <p style={{ color: C.text, fontWeight: 700, fontSize: 16, margin: '0 0 6px' }}>No data yet</p>
                        <p style={{ color: C.sub, fontSize: 13, margin: 0 }}>No {mode} programs found for the selected period.</p>
                    </div>
                )}
            </div>
        </div>
    );
}

// ── Assessment analytics home ──────────────────────────────────────────────────
export function AssessmentAnalyticsHomeScreen({ user }) {
    const [data, setData]         = useState(null);
    const [loading, setLoading]   = useState(true);
    const [error, setError]       = useState(null);
    const [countyView, setCountyView] = useState('bars');

    useEffect(() => {
        setLoading(true);
        setError(null);
        api.analytics.summary('assessment')
            .then(d => setData(d))
            .catch(e => setError(e?.message ?? 'Failed to load analytics'))
            .finally(() => setLoading(false));
    }, []);

    const stats     = data?.summaryStats ?? {};
    const chart     = data?.chartData    ?? {};
    const insights  = data?.insights     ?? [];
    const counties  = data?.topCounties  ?? [];
    const countyMap = data?.countyMap    ?? [];

    const countyMax  = useMemo(() => (counties)[0]?.count ?? 1, [counties]);
    const gradeTotal = (chart.gradeDistribution?.green ?? 0) + (chart.gradeDistribution?.yellow ?? 0) + (chart.gradeDistribution?.red ?? 0);

    return (
        <div style={{ paddingBottom: 16 }}>
            <style>{`@keyframes shimmer{0%{background-position:200% 0}100%{background-position:-200% 0}}`}</style>

            {/* Hero */}
            <div style={{
                background: 'linear-gradient(135deg, #1B5E20 0%, #2E7D32 45%, #388E3C 80%, #66BB6A 100%)',
                padding: '18px 16px 28px',
            }}>
                <div style={{ fontSize: 11, fontWeight: 800, color: 'rgba(255,255,255,0.7)', letterSpacing: '0.1em', textTransform: 'uppercase', marginBottom: 4 }}>MNCH Analytics</div>
                <div style={{ fontSize: 20, fontWeight: 800, color: '#fff', marginBottom: 2 }}>Assessment Dashboard</div>
                <div style={{ fontSize: 13, color: 'rgba(255,255,255,0.75)' }}>
                    {loading ? 'Loading data…' : 'Kenya facility assessment analytics'}
                </div>
            </div>

            <div style={{ padding: '0 12px', marginTop: -16 }}>
                {/* KPI strip */}
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(2, 1fr)', gap: 8, marginBottom: 12 }}>
                    <KpiCard label="Assessments"  value={stats.uniqueAssessments}       color={C.teal}    loading={loading} />
                    <KpiCard label="Facilities"    value={stats.facilitiesAssessed}      color={C.emerald} loading={loading} />
                    <KpiCard label="Avg Score"     value={stats.avgScore}                color={C.amber}   loading={loading} suffix="%" />
                    <KpiCard label="Coverage"      value={stats.facilityCoveragePercent} color={C.violet}  loading={loading} suffix="%" />
                </div>

                {/* Eligibility pill */}
                {!loading && (stats.eligible ?? 0) > 0 && (
                    <div style={{ background: '#ECFDF5', border: '1px solid #10B98133', borderRadius: 10, padding: '8px 14px', marginBottom: 12, display: 'flex', alignItems: 'center', gap: 8 }}>
                        <span style={{ fontSize: 20 }}>✅</span>
                        <span style={{ fontSize: 13, color: '#065F46', fontWeight: 600 }}>
                            <strong>{stats.eligible}</strong> of <strong>{stats.withSkillsLab}</strong> skills-lab facilities fully eligible for mentorship
                        </span>
                    </div>
                )}

                {/* Error */}
                {error && (
                    <div style={{ background: '#FEF2F2', border: '1px solid #EF444433', borderRadius: 12, padding: '12px 14px', marginBottom: 12 }}>
                        <p style={{ color: '#991B1B', fontSize: 13, margin: 0 }}>⚠️ {error}</p>
                    </div>
                )}

                {/* Insights */}
                {!loading && insights.length > 0 && (
                    <div style={{ marginBottom: 16 }}>
                        <SectionTitle icon="💡">Intelligent Insights</SectionTitle>
                        {insights.map((ins, i) => <InsightCard key={i} insight={ins} />)}
                    </div>
                )}
                {loading && (
                    <div style={{ marginBottom: 16 }}>
                        <Skeleton height={12} width="40%" mb={10} />
                        <Skeleton height={52} mb={8} radius={10} />
                        <Skeleton height={52} mb={8} radius={10} />
                    </div>
                )}

                {/* Monthly trend */}
                <div style={{ marginBottom: 14 }}>
                    <SectionTitle icon="📈">12-Month Assessment Trend</SectionTitle>
                    <Card>
                        <CardHeader sub="Baseline · Midline · Endline per month">Monthly Activity</CardHeader>
                        <div style={{ padding: '14px 14px 10px' }}>
                            {loading
                                ? <Skeleton height={80} radius={8} mb={0} />
                                : <AssessmentMonthlyTrend data={chart.monthlyTrend ?? []} />
                            }
                        </div>
                    </Card>
                </div>

                {/* Grade distribution */}
                {!loading && gradeTotal > 0 && (
                    <div style={{ marginBottom: 14 }}>
                        <SectionTitle icon="🎯">Score Grade Distribution</SectionTitle>
                        <Card style={{ padding: 14 }}>
                            <GradeDistribution data={chart.gradeDistribution ?? {}} total={gradeTotal} />
                        </Card>
                    </div>
                )}

                {/* Section averages */}
                {!loading && (chart.sectionAverages ?? []).length > 0 && (
                    <div style={{ marginBottom: 14 }}>
                        <SectionTitle icon="📊">Section Score Averages</SectionTitle>
                        <Card>
                            <CardHeader sub="National average scores by assessment section">Section Performance</CardHeader>
                            <div style={{ padding: '12px 14px' }}>
                                {(chart.sectionAverages ?? []).map((s, i) => (
                                    <SectionAvgRow key={i} section={s} />
                                ))}
                            </div>
                        </Card>
                    </div>
                )}

                {/* County coverage */}
                {!loading && (counties.length > 0 || countyMap.length > 0) && (
                    <div style={{ marginBottom: 14 }}>
                        <SectionTitle icon="🗺️">County Coverage</SectionTitle>
                        <Card>
                            <div style={{ padding: '10px 14px 8px', borderBottom: `1px solid ${C.border}`, background: C.bg, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                                <div>
                                    <div style={{ fontSize: 13, fontWeight: 700, color: C.text }}>Geographic Reach</div>
                                    <div style={{ fontSize: 11, color: C.sub, marginTop: 2 }}>Counties with completed assessments</div>
                                </div>
                                <div style={{ display: 'flex', background: '#E2E8F0', borderRadius: 8, padding: 2, gap: 2 }}>
                                    {[['bars','📊 Bars'],['map','🗺️ Map']].map(([v, label]) => (
                                        <button key={v} onClick={() => setCountyView(v)} style={{
                                            padding: '4px 10px', border: 'none', borderRadius: 6,
                                            fontSize: 11, fontWeight: 700, cursor: 'pointer',
                                            background: countyView === v ? 'white' : 'transparent',
                                            color: countyView === v ? C.teal : C.sub,
                                            boxShadow: countyView === v ? '0 1px 3px rgba(0,0,0,0.1)' : 'none',
                                            transition: 'all 0.15s',
                                        }}>{label}</button>
                                    ))}
                                </div>
                            </div>
                            <div style={{ padding: '12px 14px' }}>
                                {countyView === 'bars'
                                    ? counties.slice(0, 10).map((c, i) => (
                                        <BarRow key={i} label={c.name} count={c.count} max={countyMax} color={C.emerald} />
                                      ))
                                    : <KenyaChoroplethMap countyMap={countyMap.length > 0 ? countyMap : counties} personLabel="Assessments" />
                                }
                            </div>
                        </Card>
                    </div>
                )}

                {/* Empty state */}
                {!loading && !error && !(stats.uniqueAssessments > 0) && (
                    <div style={{ textAlign: 'center', padding: '32px 16px' }}>
                        <div style={{ fontSize: 48, marginBottom: 12 }}>🏥</div>
                        <p style={{ color: C.text, fontWeight: 700, fontSize: 16, margin: '0 0 6px' }}>No data yet</p>
                        <p style={{ color: C.sub, fontSize: 13, margin: 0 }}>No assessments found.</p>
                    </div>
                )}
            </div>
        </div>
    );
}
