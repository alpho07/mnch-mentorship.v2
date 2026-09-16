// src/hooks/useConfirm.jsx
// Usage:
//   const { confirm, ConfirmDialog } = useConfirm();
//   const ok = await confirm({ title: "…", message: "…", confirmLabel: "Delete", danger: true });
//   Render <ConfirmDialog /> once at the app root.

import { useState, useCallback, useRef } from "react";
import { T } from "../constants.js";

export function useConfirm() {
    const [dialog, setDialog] = useState(null); // { title, message, confirmLabel, cancelLabel, danger, resolve }
    const resolveRef = useRef(null);

    const confirm = useCallback(({ title, message, confirmLabel = "Confirm", cancelLabel = "Cancel", danger = false } = {}) => {
        return new Promise((resolve) => {
            resolveRef.current = resolve;
            setDialog({ title, message, confirmLabel, cancelLabel, danger });
        });
    }, []);

    const handleConfirm = () => {
        setDialog(null);
        resolveRef.current?.(true);
    };

    const handleCancel = () => {
        setDialog(null);
        resolveRef.current?.(false);
    };

    function ConfirmDialog() {
        if (!dialog) return null;
        const { title, message, confirmLabel, cancelLabel, danger } = dialog;

        return (
            <div
                onClick={handleCancel}
                style={{
                    position: "absolute", inset: 0, zIndex: 9999,
                    background: "rgba(15, 10, 40, 0.55)",
                    display: "flex", alignItems: "flex-end", justifyContent: "center",
                    backdropFilter: "blur(3px)",
                    WebkitBackdropFilter: "blur(3px)",
                    animation: "fadeIn 0.15s ease",
                }}
            >
                <div
                    onClick={(e) => e.stopPropagation()}
                    style={{
                        width: "100%", maxWidth: 420,
                        background: "#fff",
                        borderRadius: "20px 20px 0 0",
                        padding: "8px 0 0",
                        boxShadow: "0 -8px 40px rgba(0,0,0,0.18)",
                        animation: "slideUp 0.22s cubic-bezier(0.34,1.56,0.64,1)",
                        overflow: "hidden",
                    }}
                >
                    {/* Drag handle */}
                    <div style={{ display: "flex", justifyContent: "center", paddingBottom: 8 }}>
                        <div style={{ width: 36, height: 4, borderRadius: 2, background: "#E5E7EB" }} />
                    </div>

                    {/* Icon */}
                    <div style={{ display: "flex", justifyContent: "center", paddingTop: 8, paddingBottom: 4 }}>
                        <div style={{
                            width: 52, height: 52, borderRadius: "50%",
                            background: danger ? "#FEF2F2" : T.primaryGhost,
                            display: "flex", alignItems: "center", justifyContent: "center",
                        }}>
                            {danger ? (
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#DC2626" strokeWidth="2.5">
                                    <polyline points="3 6 5 6 21 6"/>
                                    <path d="M19 6l-1 14H6L5 6"/>
                                    <path d="M10 11v6M14 11v6"/>
                                    <path d="M9 6V4h6v2"/>
                                </svg>
                            ) : (
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke={T.primary} strokeWidth="2.5">
                                    <circle cx="12" cy="12" r="10"/>
                                    <line x1="12" y1="8" x2="12" y2="12"/>
                                    <line x1="12" y1="16" x2="12.01" y2="16"/>
                                </svg>
                            )}
                        </div>
                    </div>

                    {/* Text */}
                    <div style={{ padding: "12px 24px 20px", textAlign: "center" }}>
                        {title && (
                            <div style={{ fontSize: 16, fontWeight: 800, color: T.text, marginBottom: 8, lineHeight: 1.3 }}>
                                {title}
                            </div>
                        )}
                        {message && (
                            <div style={{ fontSize: 13, color: T.textSub, lineHeight: 1.6 }}>
                                {message}
                            </div>
                        )}
                    </div>

                    {/* Buttons */}
                    <div style={{ display: "flex", flexDirection: "column", gap: 0, borderTop: `1px solid ${T.borderLight}` }}>
                        <button
                            onClick={handleConfirm}
                            style={{
                                width: "100%", padding: "16px",
                                border: "none",
                                background: danger
                                    ? "linear-gradient(135deg, #DC2626, #EF4444)"
                                    : "linear-gradient(135deg, #3730A3, #6366F1)",
                                color: "#fff", fontSize: 15, fontWeight: 700,
                                cursor: "pointer", letterSpacing: 0.2,
                            }}
                        >
                            {confirmLabel}
                        </button>
                        <button
                            onClick={handleCancel}
                            style={{
                                width: "100%", padding: "15px",
                                border: "none", background: "#fff",
                                color: T.textSub, fontSize: 14, fontWeight: 600,
                                cursor: "pointer",
                            }}
                        >
                            {cancelLabel}
                        </button>
                    </div>
                </div>

                <style>{`
                    @keyframes fadeIn { from { opacity: 0 } to { opacity: 1 } }
                    @keyframes slideUp { from { transform: translateY(100%) } to { transform: translateY(0) } }
                `}</style>
            </div>
        );
    }

    return { confirm, ConfirmDialog };
}
