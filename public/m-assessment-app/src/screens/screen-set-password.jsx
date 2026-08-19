import { useState } from "react";
import { T } from "../constants.js";
import { Field, inputStyle } from "./screen-mentorship-form.jsx";
import api from "../services/api.service.js";

export function SetPasswordScreen({ mode, target, onDone, onBack }) {
    const [password, setPassword]         = useState("");
    const [confirmPassword, setConfirmPw] = useState("");
    const [error, setError]               = useState("");
    const [saving, setSaving]             = useState(false);

    const isVerify = mode === "verify-account";

    const handleSubmit = async () => {
        setError("");
        if (password.length < 8) { setError("Password must be at least 8 characters."); return; }
        if (isVerify && (!/[A-Z]/.test(password) || !/[0-9]/.test(password))) {
            setError("Password must contain at least one uppercase letter and one number.");
            return;
        }
        if (password !== confirmPassword) { setError("Passwords do not match."); return; }

        setSaving(true);
        try {
            if (isVerify) {
                const data = await api.auth.verifyAccount(
                    target.userId, target.expires, target.signature, password, confirmPassword
                );
                onDone(data?.user ?? null);
            } else {
                await api.auth.resetPassword(target.token, target.email, password, confirmPassword);
                onDone(null);
            }
        } catch (e) {
            setError(e.message || "Something went wrong. The link may have expired — please request a new one.");
        } finally {
            setSaving(false);
        }
    };

    return (
        <div style={{ display: "flex", flexDirection: "column", height: "100%", background: T.bg,
            fontFamily: "-apple-system, 'SF Pro Display', 'Segoe UI', system-ui, sans-serif" }}>
            <div style={{ background: T.gradientHero, padding: "44px 28px 32px", borderRadius: "28px", margin: "calc(6px + env(safe-area-inset-top, 0px)) 6px 0" }}>
                <div style={{ color: "white", fontSize: 22, fontWeight: 800 }}>
                    {isVerify ? "Set Your Password" : "Choose a New Password"}
                </div>
                <div style={{ color: "rgba(255,255,255,0.6)", fontSize: 14, marginTop: 4 }}>
                    {isVerify
                        ? "Welcome to MNCH Kenya — create a password to activate your account."
                        : "Enter a new password for your account."}
                </div>
            </div>

            <div style={{ flex: 1, padding: "28px 20px", overflowY: "auto" }}>
                {error && (
                    <div style={{ background: "#FEF2F2", color: "#991B1B", borderRadius: T.radiusSm,
                        padding: "12px 16px", fontSize: 13, marginBottom: 18, border: "1px solid #FECACA" }}>
                        {error}
                    </div>
                )}

                <Field label="New Password" required hint={isVerify ? "At least 8 characters, one uppercase letter, one number." : "At least 8 characters."}>
                    <input type="password" value={password} onChange={e => setPassword(e.target.value)} style={inputStyle} placeholder="Enter new password" />
                </Field>
                <Field label="Confirm Password" required>
                    <input type="password" value={confirmPassword} onChange={e => setConfirmPw(e.target.value)} style={inputStyle} placeholder="Re-enter new password" />
                </Field>

                <button onClick={handleSubmit} disabled={saving} style={{
                    width: "100%", padding: 15, borderRadius: T.radiusSm, border: "none",
                    background: saving ? T.borderLight : T.gradientPrimary,
                    color: saving ? T.textMuted : "white", fontSize: 15, fontWeight: 700,
                    cursor: saving ? "not-allowed" : "pointer", marginTop: 8,
                }}>
                    {saving ? "Saving…" : "Set Password"}
                </button>

                {onBack && (
                    <button type="button" onClick={onBack} style={{
                        background: "none", border: "none", padding: 0, textAlign: "center",
                        marginTop: 16, fontSize: 13, color: T.primary, fontWeight: 600,
                        cursor: "pointer", width: "100%",
                    }}>
                        Back to Login
                    </button>
                )}
            </div>
        </div>
    );
}
