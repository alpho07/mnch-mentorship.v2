import { useState } from "react";
import { T } from "../constants.js";
import { Field, inputStyle } from "./screen-mentorship-form.jsx";
import api from "../services/api.service.js";

export function ForgotPasswordScreen({ onBack, initialEmail }) {
    const [email, setEmail]   = useState(initialEmail || "");
    const [error, setError]   = useState("");
    const [saving, setSaving] = useState(false);
    const [sent, setSent]     = useState(false);

    const handleSubmit = async () => {
        setError("");
        if (!email.trim()) { setError("Enter your email address."); return; }
        setSaving(true);
        try {
            await api.auth.forgotPassword(email.trim());
            setSent(true);
        } catch (e) {
            setError(e.message || "Could not send reset link. Check the email address and try again.");
        } finally {
            setSaving(false);
        }
    };

    if (sent) {
        return (
            <div style={{ display: "flex", flexDirection: "column", height: "100%", alignItems: "center",
                justifyContent: "center", padding: 32, textAlign: "center", background: T.bg }}>
                <div style={{ fontSize: 40, marginBottom: 12 }}>📬</div>
                <div style={{ fontSize: 18, fontWeight: 800, color: T.text, marginBottom: 8 }}>Check your email</div>
                <div style={{ fontSize: 14, color: T.textSub, marginBottom: 28, lineHeight: 1.6 }}>
                    We've sent a password reset link to <strong>{email.trim()}</strong>. Open it on this device to set a new password.
                </div>
                <button onClick={onBack} style={{
                    padding: "12px 28px", background: T.gradientPrimary, color: "white", border: "none",
                    borderRadius: 12, fontWeight: 600, fontSize: 15, cursor: "pointer",
                }}>
                    Back to Login
                </button>
            </div>
        );
    }

    return (
        <div style={{ display: "flex", flexDirection: "column", height: "100%", background: T.bg,
            fontFamily: "-apple-system, 'SF Pro Display', 'Segoe UI', system-ui, sans-serif" }}>
            <div style={{ background: T.gradientHero, padding: "40px 20px 20px", borderRadius: "28px", margin: "calc(6px + env(safe-area-inset-top, 0px)) 6px 0" }}>
                <button onClick={onBack} style={{ background: "rgba(255,255,255,0.15)", border: "none", cursor: "pointer",
                    padding: "6px 10px", borderRadius: 10, marginBottom: 12, color: "white", fontSize: 12, fontWeight: 600 }}>
                    ← Back
                </button>
                <div style={{ color: "white", fontSize: 22, fontWeight: 800 }}>Forgot Password</div>
                <div style={{ color: "rgba(255,255,255,0.6)", fontSize: 13, marginTop: 4 }}>
                    Enter your email and we'll send you a reset link.
                </div>
            </div>

            <div style={{ flex: 1, padding: "28px 20px", overflowY: "auto" }}>
                {error && (
                    <div style={{ background: "#FEF2F2", color: "#991B1B", borderRadius: T.radiusSm,
                        padding: "12px 16px", fontSize: 13, marginBottom: 18, border: "1px solid #FECACA" }}>
                        {error}
                    </div>
                )}
                <Field label="Email Address" required>
                    <input type="email" value={email} onChange={e => setEmail(e.target.value)}
                        onKeyDown={e => e.key === "Enter" && handleSubmit()} style={inputStyle} placeholder="Enter your email" />
                </Field>
                <button onClick={handleSubmit} disabled={saving} style={{
                    width: "100%", padding: 15, borderRadius: T.radiusSm, border: "none",
                    background: saving ? T.borderLight : T.gradientPrimary,
                    color: saving ? T.textMuted : "white", fontSize: 15, fontWeight: 700,
                    cursor: saving ? "not-allowed" : "pointer", marginTop: 8,
                }}>
                    {saving ? "Sending…" : "Send Reset Link"}
                </button>
            </div>
        </div>
    );
}
