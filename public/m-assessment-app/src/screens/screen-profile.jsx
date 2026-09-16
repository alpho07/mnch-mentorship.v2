import { useState } from "react";
import { T } from "../constants.js";
import { Avatar } from "../components/shared-components.jsx";
import api from "../services/api.service.js";

export function ProfileScreen({ user, assessments, onUpdateUser, onLogout }) {
    const [changingPw, setChangingPw] = useState(false);
    const [pw, setPw] = useState({ current: "", newPw: "", confirm: "" });
    const [saving, setSaving] = useState(false);
    const [msg, setMsg] = useState(null);

    const flash = (type, text) => {
        setMsg({ type, text });
        setTimeout(() => setMsg(null), 3500);
    };

    const handleChangePassword = async () => {
        if (!pw.current) return flash("error", "Enter your current password.");
        if (pw.newPw.length < 8) return flash("error", "New password must be at least 8 characters.");
        if (pw.newPw !== pw.confirm) return flash("error", "Passwords do not match.");
        setSaving(true);
        try {
            await api.profile.changePassword({
                current_password: pw.current,
                password: pw.newPw,
                password_confirmation: pw.confirm,
            });
            setChangingPw(false);
            setPw({ current: "", newPw: "", confirm: "" });
            flash("success", "Password changed successfully.");
        } catch (e) {
            flash("error", e.message || "Failed to change password.");
        } finally {
            setSaving(false);
        }
    };

    const list = assessments ?? [];
    const completed = list.filter(a => a.status === "completed");
    const avgScore = completed.length
        ? (completed.reduce((s, a) => s + (Number(a.overall_percentage) || 0), 0) / completed.length).toFixed(1)
        : null;

    const inputStyle = () => ({
        width: "100%", padding: "12px 14px", borderRadius: T.radiusSm,
        border: `2px solid ${T.border}`, fontSize: 14, color: T.text,
        outline: "none", boxSizing: "border-box", fontFamily: "inherit",
        background: T.borderLight, transition: "all 0.2s",
    });

    const profileFields = [
        { label: "Full Name", value: user?.name, icon: "👤" },
        { label: "Email", value: user?.email, icon: "✉️" },
        { label: "Phone", value: user?.phone, icon: "📱" },
        { label: "Role", value: user?.role, icon: "🔑" },
        { label: "County", value: user?.county, icon: "📍" },
        { label: "Facility", value: user?.facility, icon: "🏥" },
        user?.subcounty ? { label: "Subcounty", value: user.subcounty, icon: "🗺️" } : null,
        user?.mfl_code ? { label: "MFL Code", value: user.mfl_code, icon: "🏷️" } : null,
    ].filter(Boolean);

    return (
        <div style={{ height: "100%", overflowY: "auto", background: T.bg }}>
            {/* Hero */}
            <div style={{
                background: T.gradientDark,
                padding: "52px 20px 34px", textAlign: "center",
                borderRadius: "24px 24px 28px 28px",
                position: "relative", overflow: "hidden",
            }}>
                <div style={{ position: "absolute", width: 180, height: 180, borderRadius: "50%", background: "radial-gradient(circle, rgba(79,106,245,0.20) 0%, transparent 70%)", top: -50, right: -50 }} />
                <div style={{ position: "absolute", width: 100, height: 100, borderRadius: "50%", background: "radial-gradient(circle, rgba(108,99,255,0.14) 0%, transparent 70%)", bottom: -20, left: 20 }} />

                <div style={{ animation: "fadeInUp 0.4s ease both", display: "inline-block" }}>
                    <Avatar initials={user?.initials ?? "??"} size={72} color="rgba(255,255,255,0.12)" />
                </div>
                <div style={{ color: "white", fontSize: 20, fontWeight: 800, marginTop: 14, letterSpacing: -0.3, animation: "fadeInUp 0.4s ease 0.05s both" }}>
                    {user?.name ?? "—"}
                </div>
                <div style={{ color: "rgba(255,255,255,0.5)", fontSize: 13, marginTop: 4, fontWeight: 500, animation: "fadeInUp 0.4s ease 0.1s both" }}>
                    {user?.role ?? "—"}
                </div>
                {user?.email && (
                    <div style={{ color: "rgba(255,255,255,0.35)", fontSize: 12, marginTop: 3, animation: "fadeInUp 0.4s ease 0.12s both" }}>
                        {user.email}
                    </div>
                )}

                {/* Stats */}
                <div style={{ display: "flex", justifyContent: "center", gap: 12, marginTop: 20, animation: "fadeInUp 0.4s ease 0.15s both" }}>
                    {[
                        { label: "Assessments", value: list.length },
                        { label: "Completed", value: completed.length },
                        { label: "Avg Score", value: avgScore != null && !isNaN(avgScore) ? `${avgScore}%` : "—" },
                    ].map(({ label, value }) => (
                        <div key={label} style={{ textAlign: "center", flex: 1, background: "rgba(255,255,255,0.06)", backdropFilter: "blur(4px)", borderRadius: 14, padding: "10px 8px", border: "1px solid rgba(255,255,255,0.08)" }}>
                            <div style={{ color: "white", fontSize: 20, fontWeight: 800 }}>{value}</div>
                            <div style={{ color: "rgba(255,255,255,0.45)", fontSize: 10, marginTop: 3, fontWeight: 500 }}>{label}</div>
                        </div>
                    ))}
                </div>
            </div>

            <div style={{ padding: "16px 16px 20px" }}>

                {/* Flash message */}
                {msg && (
                    <div style={{
                        background: msg.type === "success" ? "linear-gradient(135deg, #D1FAE5, #ECFDF5)" : "linear-gradient(135deg, #FEE2E2, #FFF1F2)",
                        color: msg.type === "success" ? "#065F46" : "#991B1B",
                        borderRadius: T.radiusSm, padding: "12px 16px",
                        fontSize: 13, fontWeight: 600, marginBottom: 14,
                        border: `1px solid ${msg.type === "success" ? "#6EE7B733" : "#FECACA"}`,
                        display: "flex", alignItems: "center", gap: 8,
                        animation: "scaleIn 0.2s ease",
                    }}>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke={msg.type === "success" ? "#10B981" : "#EF4444"} strokeWidth="2">
                            {msg.type === "success"
                                ? <><path d="M22 11.08V12a10 10 0 11-5.93-9.14" /><polyline points="22 4 12 14.01 9 11.01" /></>
                                : <><circle cx="12" cy="12" r="10" /><line x1="15" y1="9" x2="9" y2="15" /><line x1="9" y1="9" x2="15" y2="15" /></>
                            }
                        </svg>
                        {msg.text}
                    </div>
                )}

                {/* Profile info card — read-only */}
                <div style={{
                    background: "white", borderRadius: T.radius, padding: 18,
                    marginBottom: 14, boxShadow: T.shadowCard, border: `1px solid ${T.border}`,
                    animation: "fadeInUp 0.4s ease 0.2s both",
                }}>
                    <div style={{ fontSize: 12, fontWeight: 700, color: T.textMuted, textTransform: "uppercase", letterSpacing: 0.8, marginBottom: 16, display: "flex", alignItems: "center", gap: 6 }}>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke={T.primary} strokeWidth="2">
                            <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2" /><circle cx="12" cy="7" r="4" />
                        </svg>
                        Account Details
                    </div>
                    <div style={{ display: "flex", flexDirection: "column", gap: 0 }}>
                        {profileFields.map(({ label, value, icon }, i) => (
                            <div key={label} style={{
                                display: "flex", alignItems: "center", gap: 12,
                                padding: "11px 0",
                                borderBottom: i < profileFields.length - 1 ? `1px solid ${T.borderLight}` : "none",
                            }}>
                                <div style={{
                                    width: 34, height: 34, borderRadius: 10, flexShrink: 0,
                                    background: T.primaryGhost, display: "flex", alignItems: "center", justifyContent: "center",
                                    fontSize: 15,
                                }}>
                                    {icon}
                                </div>
                                <div style={{ flex: 1 }}>
                                    <div style={{ fontSize: 10, color: T.textMuted, fontWeight: 600, textTransform: "uppercase", letterSpacing: 0.5 }}>{label}</div>
                                    <div style={{ fontSize: 13, color: value ? T.text : T.textMuted, fontWeight: value ? 600 : 400, marginTop: 1 }}>
                                        {value || "—"}
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>

                {/* Security card */}
                <div style={{
                    background: "white", borderRadius: T.radius, padding: 18,
                    marginBottom: 14, boxShadow: T.shadowCard, border: `1px solid ${T.border}`,
                    animation: "fadeInUp 0.4s ease 0.25s both",
                }}>
                    <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: changingPw ? 16 : 0 }}>
                        <div style={{ fontSize: 12, fontWeight: 700, color: T.textMuted, textTransform: "uppercase", letterSpacing: 0.8, display: "flex", alignItems: "center", gap: 6 }}>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke={T.primary} strokeWidth="2">
                                <rect x="3" y="11" width="18" height="11" rx="2" /><path d="M7 11V7a5 5 0 0110 0v4" />
                            </svg>
                            Security
                        </div>
                        <button onClick={() => setChangingPw(!changingPw)} style={{ background: changingPw ? "linear-gradient(135deg, #FEE2E2, #FFF1F2)" : T.primaryGhost, color: changingPw ? "#EF4444" : T.primary, border: changingPw ? "1px solid #FECACA" : `1px solid ${T.primary}20`, borderRadius: 10, padding: "6px 14px", fontSize: 12, fontWeight: 700, cursor: "pointer", transition: "all 0.2s" }}>
                            {changingPw ? "Cancel" : "Change Password"}
                        </button>
                    </div>

                    {changingPw && (
                        <div style={{ display: "flex", flexDirection: "column", gap: 14 }}>
                            {[
                                { key: "current", label: "Current Password" },
                                { key: "newPw", label: "New Password" },
                                { key: "confirm", label: "Confirm New Password" },
                            ].map(({ key, label }) => (
                                <div key={key}>
                                    <div style={{ fontSize: 11, color: T.textMuted, fontWeight: 600, marginBottom: 6, textTransform: "uppercase", letterSpacing: 0.6 }}>{label}</div>
                                    <input type="password" value={pw[key]} onChange={e => setPw(p => ({ ...p, [key]: e.target.value }))} style={inputStyle()} />
                                </div>
                            ))}
                            <button onClick={handleChangePassword} disabled={saving} style={{ padding: "13px", borderRadius: T.radiusSm, background: saving ? "#D1D5DB" : T.gradientSky, color: "white", border: "none", fontSize: 14, fontWeight: 700, cursor: saving ? "not-allowed" : "pointer", boxShadow: saving ? "none" : "0 6px 20px rgba(14,165,233,0.2)", transition: "all 0.2s" }}>
                                {saving ? "Updating…" : "Update Password"}
                            </button>
                        </div>
                    )}
                </div>

                {/* Sign out */}
                <button onClick={onLogout} style={{ width: "100%", padding: 15, borderRadius: T.radius, background: "linear-gradient(135deg, #FEE2E2, #FFF1F2)", color: "#EF4444", border: "1px solid #FECACA", fontSize: 15, fontWeight: 700, cursor: "pointer", display: "flex", alignItems: "center", justifyContent: "center", gap: 8, transition: "all 0.2s", animation: "fadeInUp 0.4s ease 0.3s both" }}>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#EF4444" strokeWidth="2" strokeLinecap="round">
                        <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4" /><polyline points="16 17 21 12 16 7" /><line x1="21" y1="12" x2="9" y2="12" />
                    </svg>
                    Sign Out
                </button>
            </div>
        </div>
    );
}
