import { useState, useEffect } from "react";
import { T } from "../constants.js";
import api from "../services/api.service.js";
import { ResourceCard } from "../components/ResourceCard.jsx";

const STATUS_MAP = {
    upcoming:  { bg: "#DBEAFE", color: "#1D4ED8", stripe: "#3B82F6" },
    active:    { bg: "#D1FAE5", color: "#065F46", stripe: "#10B981" },
    ongoing:   { bg: "#D1FAE5", color: "#065F46", stripe: "#10B981" },
    completed: { bg: "#F3F4F6", color: "#6B7280", stripe: "#9CA3AF" },
    cancelled: { bg: "#FEE2E2", color: "#991B1B", stripe: "#EF4444" },
    draft:     { bg: "#FEF3C7", color: "#92400E", stripe: "#F59E0B" },
};

const COMPLETION_MAP = {
    registered:  { bg: "#EDE9FE", color: "#5B21B6" },
    in_progress: { bg: "#FEF3C7", color: "#92400E" },
    completed:   { bg: "#D1FAE5", color: "#065F46" },
};

function SectionHeader({ children }) {
    return (
        <div style={{ fontSize: 11, fontWeight: 700, color: T.textSub, textTransform: "uppercase", letterSpacing: 0.7, marginBottom: 8 }}>
            {children}
        </div>
    );
}

function InfoRow({ label, value, last }) {
    if (!value) return null;
    return (
        <div style={{
            display: "flex", justifyContent: "space-between", alignItems: "flex-start",
            paddingBlock: 9, borderBottom: last ? "none" : `1px solid ${T.borderLight}`, gap: 12,
        }}>
            <span style={{ fontSize: 13, color: T.textSub, flexShrink: 0 }}>{label}</span>
            <span style={{ fontSize: 13, fontWeight: 600, color: T.text, textAlign: "right" }}>{value}</span>
        </div>
    );
}

export function TrainingDetailScreen({ training, user, onBack }) {
    const [detail, setDetail]          = useState(null);
    const [participants, setParticipants] = useState(null);
    const [resources, setResources]    = useState([]);
    const [loading, setLoading]        = useState(true);
    const [enrolled, setEnrolled]      = useState(false);
    const [attended, setAttended]      = useState(false);
    const [enrolling, setEnrolling]    = useState(false);
    const [attending, setAttending]    = useState(false);
    const [actionError, setActionError] = useState(null);
    const [tab, setTab]                = useState("info");

    useEffect(() => {
        Promise.allSettled([
            api.trainings.find(training.id),
            api.trainings.participants(training.id),
            api.resources.list("training"),
        ]).then(([detailRes, partsRes, resRes]) => {
            if (detailRes.status === "fulfilled") setDetail(detailRes.value?.data ?? training);
            if (partsRes.status === "fulfilled") {
                const parts = Array.isArray(partsRes.value?.data) ? partsRes.value.data : [];
                setParticipants(parts);
                const me = parts.find(p => p.user_id === user?.id);
                if (me) {
                    setEnrolled(true);
                    if (me.completion_status === "in_progress" || me.completion_status === "completed") setAttended(true);
                }
            }
            if (resRes.status === "fulfilled") setResources(Array.isArray(resRes.value?.data) ? resRes.value.data : []);
        }).finally(() => setLoading(false));
    }, [training.id]);

    const data = detail ?? training;
    const s = STATUS_MAP[data.status] ?? STATUS_MAP.draft;
    const isActive = ["upcoming", "active", "ongoing"].includes(data.status);

    const handleEnroll = async () => {
        setEnrolling(true); setActionError(null);
        try { await api.trainingActions.enroll(training.id); setEnrolled(true); }
        catch (e) { setActionError(e.message ?? "Enroll failed."); }
        finally { setEnrolling(false); }
    };

    const handleAttendance = async () => {
        setAttending(true); setActionError(null);
        try { await api.trainingActions.attendance(training.id); setAttended(true); }
        catch (e) { setActionError(e.message ?? "Could not mark attendance."); }
        finally { setAttending(false); }
    };

    return (
        <div style={{ display: "flex", flexDirection: "column", height: "100%", background: T.bg }}>
            {/* ── Gradient Hero ── */}
            <div style={{
                background: "linear-gradient(160deg, #1E1B4B 0%, #3730A3 55%, #818CF8 100%)",
                padding: "44px 20px 16px",
                position: "relative", overflow: "hidden",
            }}>
                <div style={{ position: "absolute", width: 160, height: 160, borderRadius: "50%", background: "radial-gradient(circle, rgba(165,180,252,0.15) 0%, transparent 70%)", top: -40, right: -40 }} />
                <button onClick={onBack} style={{ border: "none", background: "rgba(255,255,255,0.12)", cursor: "pointer", padding: "6px 10px", borderRadius: 10, marginBottom: 14, display: "flex", alignItems: "center", gap: 4 }}>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="2.5"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                    <span style={{ fontSize: 12, color: "rgba(255,255,255,0.8)", fontWeight: 600 }}>Back</span>
                </button>
                <div style={{ fontSize: 18, fontWeight: 800, color: "white", lineHeight: 1.25, marginBottom: 8 }}>{data.title}</div>
                <div style={{ display: "flex", flexWrap: "wrap", gap: 8, alignItems: "center" }}>
                    <span style={{ fontSize: 11, fontWeight: 700, padding: "3px 10px", borderRadius: 20, background: "rgba(255,255,255,0.15)", color: "rgba(255,255,255,0.9)", border: "1px solid rgba(255,255,255,0.2)" }}>
                        {data.status}
                    </span>
                    {data.program && <span style={{ fontSize: 11, color: "rgba(255,255,255,0.55)" }}>{data.program}</span>}
                    {data.start_date && <span style={{ fontSize: 11, color: "rgba(255,255,255,0.45)" }}>· {data.start_date}</span>}
                </div>
            </div>

            {/* Tabs — on white card below gradient */}
            <div style={{ background: T.card, borderBottom: `1px solid ${T.border}` }}>
                <div style={{ display: "flex", paddingInline: 4 }}>
                    {[
                        { key: "info", label: "Details" },
                        { key: "participants", label: `Participants${participants ? ` (${participants.length})` : ""}` },
                        ...(resources.length > 0 ? [{ key: "resources", label: `Resources (${resources.length})` }] : []),
                    ].map(t => (
                        <button key={t.key} onClick={() => setTab(t.key)} style={{
                            flex: 1, padding: "10px 4px", border: "none", background: "none",
                            fontSize: 12, fontWeight: tab === t.key ? 700 : 500,
                            color: tab === t.key ? T.primary : T.textSub, cursor: "pointer",
                            borderBottom: tab === t.key ? `2px solid ${T.primary}` : "2px solid transparent",
                        }}>
                            {t.label}
                        </button>
                    ))}
                </div>
            </div>

            <div style={{ flex: 1, overflowY: "auto", padding: 16 }}>
                {loading && <div style={{ color: T.textSub, textAlign: "center", paddingTop: 32 }}>Loading…</div>}

                {/* Action buttons — always visible */}
                {actionError && (
                    <div style={{ background: "#FEE2E2", color: "#991B1B", borderRadius: T.radiusXs, padding: "10px 14px", fontSize: 13, marginBottom: 12 }}>
                        {actionError}
                    </div>
                )}
                {!enrolled && isActive && (
                    <button onClick={handleEnroll} disabled={enrolling} style={{
                        width: "100%", background: T.gradientPrimary, border: "none", borderRadius: T.radiusSm,
                        color: "#fff", fontWeight: 700, fontSize: 15, padding: "14px 0",
                        cursor: enrolling ? "not-allowed" : "pointer", opacity: enrolling ? 0.7 : 1, marginBottom: 12,
                        boxShadow: `0 4px 14px ${T.primaryGlow}`,
                    }}>
                        {enrolling ? "Enrolling…" : "Enroll in Training"}
                    </button>
                )}
                {enrolled && !attended && isActive && (
                    <button onClick={handleAttendance} disabled={attending} style={{
                        width: "100%", background: "linear-gradient(135deg,#10B981,#059669)", border: "none",
                        borderRadius: T.radiusSm, color: "#fff", fontWeight: 700, fontSize: 15, padding: "14px 0",
                        cursor: attending ? "not-allowed" : "pointer", opacity: attending ? 0.7 : 1, marginBottom: 12,
                        boxShadow: "0 4px 14px rgba(16,185,129,0.25)",
                    }}>
                        {attending ? "Marking…" : "Mark My Attendance"}
                    </button>
                )}
                {enrolled && attended && (
                    <div style={{
                        background: "#D1FAE5", borderRadius: T.radiusSm, padding: "10px 14px",
                        marginBottom: 12, display: "flex", alignItems: "center", gap: 8,
                    }}>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#065F46" strokeWidth="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                        <span style={{ color: "#065F46", fontWeight: 700, fontSize: 13 }}>Attendance recorded</span>
                    </div>
                )}

                {/* Details tab */}
                {tab === "info" && !loading && (
                    <>
                        <SectionHeader>Training Information</SectionHeader>
                        <div style={{ background: T.card, borderRadius: T.radiusSm, padding: "0 16px", boxShadow: T.shadowCard, marginBottom: 16 }}>
                            <InfoRow label="Facility" value={data.facility} />
                            <InfoRow label="County" value={data.county} />
                            <InfoRow label="Program" value={data.program} />
                            <InfoRow label="Start Date" value={data.start_date} />
                            <InfoRow label="End Date" value={data.end_date} />
                            <InfoRow label="Location" value={data.location_type?.replace(/_/g, " ")} />
                            <InfoRow label="Max Participants" value={data.max_participants} />
                            <InfoRow label="Online Link" value={data.online_link} last />
                        </div>

                        {data.description && (
                            <>
                                <SectionHeader>Description</SectionHeader>
                                <div style={{ background: T.card, borderRadius: T.radiusSm, padding: "12px 16px", boxShadow: T.shadowCard, marginBottom: 16 }}>
                                    <p style={{ margin: 0, fontSize: 13, color: T.text, lineHeight: 1.7 }}>{data.description}</p>
                                </div>
                            </>
                        )}

                        {data.target_audience && (
                            <>
                                <SectionHeader>Target Audience</SectionHeader>
                                <div style={{ background: T.card, borderRadius: T.radiusSm, padding: "12px 16px", boxShadow: T.shadowCard, marginBottom: 16 }}>
                                    <p style={{ margin: 0, fontSize: 13, color: T.text, lineHeight: 1.7 }}>{data.target_audience}</p>
                                </div>
                            </>
                        )}

                        {data.learning_outcomes && (
                            <>
                                <SectionHeader>Learning Outcomes</SectionHeader>
                                <div style={{ background: T.card, borderRadius: T.radiusSm, padding: "12px 16px", boxShadow: T.shadowCard, marginBottom: 16 }}>
                                    <p style={{ margin: 0, fontSize: 13, color: T.text, lineHeight: 1.7 }}>{data.learning_outcomes}</p>
                                </div>
                            </>
                        )}
                    </>
                )}

                {/* Participants tab */}
                {tab === "participants" && (
                    <>
                        <SectionHeader>Participants {participants ? `(${participants.length})` : ""}</SectionHeader>
                        {!participants && <div style={{ color: T.textSub, fontSize: 13 }}>Loading…</div>}
                        {participants?.length === 0 && <div style={{ color: T.textSub, fontSize: 13 }}>No participants yet.</div>}
                        {(participants ?? []).map(p => {
                            const cs = COMPLETION_MAP[p.completion_status] ?? COMPLETION_MAP.registered;
                            return (
                                <div key={p.id} style={{
                                    background: T.card, borderRadius: T.radiusSm, padding: "12px 14px",
                                    marginBottom: 8, boxShadow: T.shadowCard, border: `1px solid ${T.border}`,
                                    display: "flex", justifyContent: "space-between", alignItems: "center",
                                }}>
                                    <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
                                        <div style={{
                                            width: 34, height: 34, borderRadius: "50%", background: T.primaryGhost,
                                            display: "flex", alignItems: "center", justifyContent: "center",
                                            fontWeight: 700, fontSize: 12, color: T.primary, flexShrink: 0,
                                        }}>
                                            {(p.name ?? "?").split(" ").map(w => w[0]).join("").slice(0,2).toUpperCase()}
                                        </div>
                                        <span style={{ fontSize: 14, fontWeight: 500, color: T.text }}>{p.name}</span>
                                    </div>
                                    <span style={{ fontSize: 11, fontWeight: 700, padding: "2px 8px", borderRadius: 20, background: cs.bg, color: cs.color }}>
                                        {(p.completion_status ?? "registered").replace(/_/g, " ")}
                                    </span>
                                </div>
                            );
                        })}
                    </>
                )}

                {/* Resources tab */}
                {tab === "resources" && (
                    <>
                        <SectionHeader>Training Resources</SectionHeader>
                        {resources.map(r => <ResourceCard key={r.id} resource={r} />)}
                    </>
                )}
            </div>
        </div>
    );
}
