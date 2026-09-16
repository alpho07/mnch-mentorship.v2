import { useState, useEffect, useCallback } from "react";
import { T } from "../constants.js";
import { BackButton } from "../components/shared-components.jsx";
import api from "../services/api.service.js";

const STATUS_CONFIG = {
    local_pending: { label: "Pending (offline)", bg: "#FEF3C7", color: "#92400E", dot: "#F59E0B" },
    queued:        { label: "Queued",             bg: "#EFF6FF", color: "#1E40AF", dot: "#3B82F6" },
    processing:    { label: "Sending…",           bg: "#F0FDF4", color: "#065F46", dot: "#10B981" },
    sent:          { label: "Sent",               bg: "#D1FAE5", color: "#065F46", dot: "#10B981" },
    failed:        { label: "Failed",             bg: "#FEE2E2", color: "#991B1B", dot: "#EF4444" },
};

function StatusBadge({ status }) {
    const s = STATUS_CONFIG[status] ?? { label: status, bg: "#F3F4F6", color: T.textMuted, dot: T.border };
    return (
        <div style={{
            display: "inline-flex", alignItems: "center", gap: 5,
            background: s.bg, color: s.color,
            borderRadius: 20, padding: "4px 10px",
            fontSize: 11, fontWeight: 700, flexShrink: 0,
        }}>
            <div style={{ width: 6, height: 6, borderRadius: "50%", background: s.dot, flexShrink: 0 }} />
            {s.label}
        </div>
    );
}

function JobCard({ job, onRetry, retrying }) {
    const emails = Array.isArray(job.emails) ? job.emails : [];
    const date = job.created_at ? new Date(job.created_at).toLocaleString() : "—";
    const sentAt = job.sent_at ? new Date(job.sent_at).toLocaleString() : null;

    return (
        <div style={{
            background: "#fff",
            borderRadius: T.radius,
            padding: "14px 16px",
            marginBottom: 10,
            border: `1.5px solid ${job.status === "failed" ? "#FECACA" : job.status === "sent" ? "#A7F3D0" : T.border}`,
            boxShadow: T.shadowCard,
        }}>
            <div style={{ display: "flex", alignItems: "flex-start", justifyContent: "space-between", gap: 10 }}>
                <div style={{ flex: 1, minWidth: 0 }}>
                    <div style={{ fontWeight: 700, fontSize: 13, color: T.text, overflow: "hidden", textOverflow: "ellipsis", whiteSpace: "nowrap" }}>
                        {job.facility_name ?? "Assessment"}
                    </div>
                    <div style={{ fontSize: 11, color: T.textSub, marginTop: 2 }}>
                        {[job.assessment_type, job.assessment_date].filter(Boolean).join(" · ")}
                    </div>
                </div>
                <StatusBadge status={job.status} />
            </div>

            {/* Recipients */}
            <div style={{ marginTop: 10, display: "flex", flexWrap: "wrap", gap: 5 }}>
                {emails.map(e => (
                    <div key={e} style={{ background: T.primaryGhost, color: T.primary, borderRadius: 20, padding: "3px 10px", fontSize: 11, fontWeight: 600 }}>
                        {e}
                    </div>
                ))}
            </div>

            {/* Timestamps */}
            <div style={{ marginTop: 8, fontSize: 11, color: T.textMuted }}>
                {sentAt ? `Sent ${sentAt}` : `Queued ${date}`}
            </div>

            {/* Error message */}
            {job.status === "failed" && job.error && (
                <div style={{ marginTop: 8, padding: "8px 10px", background: "#FEF2F2", borderRadius: 8, fontSize: 11, color: "#B91C1C", lineHeight: 1.5 }}>
                    {job.error}
                </div>
            )}

            {/* Retry */}
            {job.status === "failed" && onRetry && (
                <button
                    onClick={() => onRetry(job)}
                    disabled={retrying}
                    style={{
                        marginTop: 10, width: "100%", padding: "9px 0",
                        background: "#FEE2E2", color: "#DC2626",
                        border: "1px solid #FECACA", borderRadius: 8,
                        fontSize: 12, fontWeight: 700, cursor: retrying ? "not-allowed" : "pointer",
                        opacity: retrying ? 0.6 : 1,
                    }}
                >
                    {retrying ? "Retrying…" : "Retry Send"}
                </button>
            )}
        </div>
    );
}

export function EmailJobsScreen({ onBack }) {
    const [jobs, setJobs] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [retryingId, setRetryingId] = useState(null);

    const loadJobs = useCallback(async () => {
        setLoading(true);
        setError(null);
        try {
            const data = await api.reports.emailJobs();
            setJobs(Array.isArray(data) ? data : []);
        } catch (e) {
            setError(e.message || "Failed to load email jobs.");
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        loadJobs();
    }, [loadJobs]);

    // Listen for synced events so UI auto-refreshes when a pending job is sent
    useEffect(() => {
        const handler = () => loadJobs();
        window.addEventListener("emailJob:synced", handler);
        return () => window.removeEventListener("emailJob:synced", handler);
    }, [loadJobs]);

    const handleRetry = async (job) => {
        setRetryingId(job.id);
        try {
            await api.reports.emailReport(job.assessment_id, job.emails);
            await loadJobs();
        } catch {
            // Error handled inside emailReport — reload to reflect queued status
            await loadJobs();
        } finally {
            setRetryingId(null);
        }
    };

    const pending = jobs.filter(j => ["local_pending", "queued", "processing"].includes(j.status));
    const done    = jobs.filter(j => j.status === "sent");
    const failed  = jobs.filter(j => j.status === "failed");

    return (
        <div style={{ display: "flex", flexDirection: "column", height: "100%" }}>
            {/* Header */}
            <div style={{
                background: T.gradientDark,
                padding: "20px 20px 24px",
                borderRadius: "24px 24px 28px 28px",
                position: "relative", overflow: "hidden",
            }}>
                <div style={{ position: "absolute", width: 140, height: 140, borderRadius: "50%", background: "radial-gradient(circle, rgba(59,130,246,0.12) 0%, transparent 70%)", top: -40, right: -30 }} />
                <BackButton onBack={onBack} light />
                <div style={{ marginTop: 12, color: "white", fontSize: 20, fontWeight: 800, letterSpacing: -0.3 }}>Email Jobs</div>
                <div style={{ color: "rgba(255,255,255,0.5)", fontSize: 13, marginTop: 3 }}>
                    {jobs.length} total · {pending.length} pending · {failed.length} failed
                </div>
            </div>

            {/* Body */}
            <div style={{ flex: 1, overflowY: "auto", padding: "16px 16px 24px", background: T.bg }}>
                {loading && (
                    <div style={{ textAlign: "center", padding: "40px 0", color: T.textMuted }}>
                        <div style={{ fontSize: 13 }}>Loading…</div>
                    </div>
                )}

                {!loading && error && (
                    <div style={{ padding: "14px 16px", background: "#FEE2E2", borderRadius: T.radius, color: "#991B1B", fontSize: 13 }}>
                        {error}
                        <button onClick={loadJobs} style={{ marginLeft: 10, fontWeight: 700, color: "#991B1B", background: "none", border: "none", cursor: "pointer", textDecoration: "underline", fontSize: 13 }}>Retry</button>
                    </div>
                )}

                {!loading && !error && jobs.length === 0 && (
                    <div style={{ textAlign: "center", padding: "50px 24px", color: T.textMuted, background: "white", borderRadius: T.radius, boxShadow: T.shadowCard, border: `1px solid ${T.border}` }}>
                        <div style={{ fontSize: 48, marginBottom: 14 }}>📧</div>
                        <div style={{ fontSize: 16, fontWeight: 700, color: T.textMid, marginBottom: 6 }}>No email jobs yet</div>
                        <div style={{ fontSize: 13, color: T.textSub, lineHeight: 1.6 }}>
                            When you share a report via email, the job will appear here with its status.
                        </div>
                    </div>
                )}

                {!loading && failed.length > 0 && (
                    <div style={{ marginBottom: 4 }}>
                        <div style={{ fontSize: 11, fontWeight: 800, color: "#DC2626", textTransform: "uppercase", letterSpacing: 0.5, marginBottom: 8 }}>
                            Failed ({failed.length})
                        </div>
                        {failed.map(j => (
                            <JobCard key={j.id} job={j} onRetry={handleRetry} retrying={retryingId === j.id} />
                        ))}
                    </div>
                )}

                {!loading && pending.length > 0 && (
                    <div style={{ marginBottom: 4 }}>
                        <div style={{ fontSize: 11, fontWeight: 800, color: T.primary, textTransform: "uppercase", letterSpacing: 0.5, marginBottom: 8 }}>
                            Pending ({pending.length})
                        </div>
                        {pending.map(j => (
                            <JobCard key={j.id} job={j} />
                        ))}
                    </div>
                )}

                {!loading && done.length > 0 && (
                    <div>
                        <div style={{ fontSize: 11, fontWeight: 800, color: "#059669", textTransform: "uppercase", letterSpacing: 0.5, marginBottom: 8 }}>
                            Sent ({done.length})
                        </div>
                        {done.map(j => (
                            <JobCard key={j.id} job={j} />
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
}
