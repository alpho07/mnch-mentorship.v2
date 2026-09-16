import { useState, useEffect, useCallback } from "react";
import { T } from "../constants.js";
import syncQueue from "../services/sync-queue.js";

/**
 * SyncIndicator — floating status bar that shows:
 *   - Online + no pending   → hidden (nothing to show)
 *   - Online + pending      → teal bar "X changes ready to sync" + Sync Now button
 *   - Online + syncing      → blue pulsing bar "Syncing X changes…"
 *   - Online + error        → red bar with Retry button
 *   - Offline + no pending  → subtle amber bar "Offline"
 *   - Offline + pending     → amber bar "Offline · X changes pending"
 *
 * Supports both automatic syncing (triggered by connectivity change)
 * and manual syncing via the "Sync Now" button.
 */

const STATUS_CONFIG = {
    idle_pending: {bg: "linear-gradient(135deg, #ECFDF5, #D1FAE5)", color: "#065F46", icon: "pending", border: "#6EE7B7"},
    syncing:      {bg: "linear-gradient(135deg, #0EA5E9, #7DD3FC)", color: "#fff",    icon: "sync",    border: "#0EA5E930"},
    error:        {bg: "linear-gradient(135deg, #FEE2E2, #FFF1F2)", color: "#991B1B", icon: "error",   border: "#FCA5A5"},
    offline:      {bg: "linear-gradient(135deg, #FEF3C7, #FFFBEB)", color: "#92400E", icon: "offline", border: "#FDE68A"},
};

function SyncIcon({ type, size = 14 }) {
    if (type === "sync")
        return (
            <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" style={{animation: "spin 1.5s linear infinite"}}>
                <polyline points="23 4 23 10 17 10" /><polyline points="1 20 1 14 7 14" />
                <path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15" />
            </svg>
        );
    if (type === "pending")
        return (
            <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round">
                <polyline points="23 4 23 10 17 10" /><polyline points="1 20 1 14 7 14" />
                <path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15" />
            </svg>
        );
    if (type === "error")
        return (
            <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round">
                <circle cx="12" cy="12" r="10" /><line x1="12" y1="8" x2="12" y2="12" /><line x1="12" y1="16" x2="12.01" y2="16" />
            </svg>
        );
    // offline
    return (
        <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round">
            <line x1="1" y1="1" x2="23" y2="23" />
            <path d="M16.72 11.06A10.94 10.94 0 0119 12.55" />
            <path d="M5 12.55a10.94 10.94 0 015.17-2.39" />
            <path d="M10.71 5.05A16 16 0 0122.56 9" />
            <path d="M1.42 9a15.91 15.91 0 014.7-2.88" />
            <path d="M8.53 16.11a6 6 0 016.95 0" />
            <line x1="12" y1="20" x2="12.01" y2="20" />
        </svg>
    );
}

export function SyncIndicator() {
    const [state, setState] = useState(syncQueue.getStatus());
    const [dismissed, setDismissed] = useState(false);
    const [isSyncing, setIsSyncing] = useState(false);
    const [showDetail, setShowDetail] = useState(false);
    const [summary, setSummary] = useState(null);

    useEffect(() => {
        return syncQueue.subscribe((s) => {
            setState(s);
            setDismissed(false); // un-dismiss on any status change
            setShowDetail(false);
            if (s.status !== "syncing") setIsSyncing(false);
        });
    }, []);

    const handleManualSync = useCallback(async () => {
        if (isSyncing) return;
        setIsSyncing(true);
        await syncQueue.flush();
        // isSyncing will be reset by the subscribe callback above
    }, [isSyncing]);

    const handleLabelTap = useCallback(async () => {
        if (showDetail) {
            setShowDetail(false);
            return;
        }
        const s = await syncQueue.getPendingSummary();
        setSummary(s);
        setShowDetail(true);
    }, [showDetail]);

    const {status, pendingCount, lastError} = state;
    const online = navigator.onLine;

    // Derive effective display status
    let effectiveStatus = status;
    if (status === "idle" && pendingCount === 0) return null; // truly nothing to show
    if (status === "idle" && pendingCount > 0 && online) effectiveStatus = "idle_pending";
    if (status === "syncing" || isSyncing) effectiveStatus = "syncing";

    const config = STATUS_CONFIG[effectiveStatus];
    if (!config) return null;
    if (dismissed) return null;

    const label =
        effectiveStatus === "syncing"
            ? `Syncing ${pendingCount} change${pendingCount !== 1 ? "s" : ""}…`
        : effectiveStatus === "idle_pending"
            ? `${pendingCount} change${pendingCount !== 1 ? "s" : ""} ready to upload`
        : effectiveStatus === "error"
            ? `Sync failed${pendingCount > 0 ? ` · ${pendingCount} pending` : ""}${lastError ? `: ${lastError}` : ""}`
        : pendingCount > 0
            ? `Offline · ${pendingCount} change${pendingCount !== 1 ? "s" : ""} pending`
            : "Offline mode";

    const showSyncBtn = (effectiveStatus === "idle_pending" || effectiveStatus === "error") && online;
    const showRetryBtn = effectiveStatus === "error" && online;

    return (
        <div style={{
            position: "absolute", top: 0, left: 0, right: 0, zIndex: 200,
            padding: "0 12px",
            paddingTop: "calc(4px + env(safe-area-inset-top, 0px))",
            pointerEvents: "none",
        }}>
            <style>{`
                @keyframes spin { to { transform: rotate(360deg); } }
                @keyframes slideDown { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: translateY(0); } }
            `}</style>
            <div style={{
                background: config.bg,
                color: config.color,
                borderRadius: 14,
                padding: "8px 12px",
                display: "flex", flexDirection: "column", alignItems: "stretch",
                fontSize: 12, fontWeight: 600,
                border: `1px solid ${config.border}`,
                boxShadow: "0 4px 20px rgba(0,0,0,0.08)",
                pointerEvents: "auto",
                backdropFilter: "blur(8px)",
                animation: "slideDown 0.25s ease",
            }}>
                {/* Main row */}
                <div style={{ display: "flex", alignItems: "center", gap: 8 }}>
                    <SyncIcon type={effectiveStatus === "syncing" ? "sync" : config.icon} />
                    <span
                        style={{ flex: 1, lineHeight: 1.3, cursor: pendingCount > 0 ? "pointer" : "default" }}
                        onClick={pendingCount > 0 ? handleLabelTap : undefined}
                    >
                        {label}
                        {pendingCount > 0 && (
                            <span style={{ marginLeft: 4, opacity: 0.6, fontSize: 10 }}>
                                {showDetail ? "▲" : "▼"}
                            </span>
                        )}
                    </span>

                    {/* Manual Sync Now button */}
                    {showSyncBtn && !showRetryBtn && (
                        <button
                            onClick={handleManualSync}
                            disabled={isSyncing}
                            style={{
                                padding: "4px 10px", borderRadius: 8, border: "none",
                                background: "rgba(16,185,129,0.15)", color: "#065F46",
                                fontSize: 11, fontWeight: 700, cursor: "pointer",
                                whiteSpace: "nowrap",
                            }}
                        >
                            ↑ Sync Now
                        </button>
                    )}

                    {/* Retry button for errors */}
                    {showRetryBtn && (
                        <button
                            onClick={handleManualSync}
                            style={{
                                padding: "4px 10px", borderRadius: 8, border: "none",
                                background: "rgba(255,255,255,0.9)", color: "#991B1B",
                                fontSize: 11, fontWeight: 700, cursor: "pointer",
                            }}
                        >
                            Retry
                        </button>
                    )}

                    {/* Dismiss */}
                    <button
                        onClick={() => setDismissed(true)}
                        style={{
                            width: 20, height: 20, borderRadius: 6, border: "none",
                            background: "rgba(0,0,0,0.08)", color: config.color,
                            fontSize: 12, cursor: "pointer",
                            display: "flex", alignItems: "center", justifyContent: "center",
                            flexShrink: 0,
                        }}
                    >
                        ✕
                    </button>
                </div>

                {/* Tap-to-expand pending breakdown */}
                {showDetail && summary && summary.total > 0 && (
                    <div style={{
                        marginTop: 4,
                        borderTop: `1px solid ${config.border}`,
                        paddingTop: 6,
                        display: "flex",
                        flexDirection: "column",
                        gap: 2,
                    }}>
                        {Object.entries(summary.groups).map(([label, count]) => (
                            <div key={label} style={{ display: "flex", justifyContent: "space-between", fontSize: 11 }}>
                                <span>{label}</span>
                                <span style={{ fontWeight: 700 }}>{count}×</span>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
}
