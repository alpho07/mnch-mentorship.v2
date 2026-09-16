import { useState } from "react";
import { T } from "../constants.js";
import api from "../services/api.service.js";

export function LoginScreen({ onLogin }) {
    const [email, setEmail] = useState("super@admin.com");
    const [password, setPassword] = useState("password");
    const [error, setError] = useState("");
    const [loading, setLoading] = useState(false);

    const handleLogin = async () => {
        if (!email || !password) { setError("Email and password are required."); return; }
        setLoading(true);
        setError("");
        try {
            const data = await api.auth.login(email, password);
            api.setToken(data.token);
            onLogin(data.user);
        } catch (e) {
            setError(e.message || "Login failed. Please try again.");
        } finally {
            setLoading(false);
        }
    };

    return (
        <div style={{ display: "flex", flexDirection: "column", height: "100%", background: "white" }}>
            {/* Hero */}
            <div style={{
                background: "linear-gradient(160deg, #064E3B 0%, #065F46 60%, #047857 100%)",
                padding: "52px 28px 40px", position: "relative", overflow: "hidden",
            }}>
                {[{ s: 160, t: -40, r: -40, o: 0.06 }, { s: 90, b: -20, l: 20, o: 0.05 }].map((c, i) => (
                    <div key={i} style={{
                        position: "absolute", width: c.s, height: c.s, borderRadius: "50%",
                        background: "white", opacity: c.o,
                        top: c.t, right: c.r, bottom: c.b, left: c.l,
                    }} />
                ))}
                <div style={{
                    width: 64, height: 64, borderRadius: 18,
                    background: "rgba(255,255,255,0.15)",
                    display: "flex", alignItems: "center", justifyContent: "center",
                    fontSize: 36, marginBottom: 18,
                }}>🩺</div>
                <div style={{ color: "white", fontSize: 26, fontWeight: 900, letterSpacing: -0.5, lineHeight: 1.2 }}>
                    MNCH Kenya<br />Mentorship
                </div>
                <div style={{ color: "rgba(255,255,255,0.65)", fontSize: 13, marginTop: 6 }}>
                    Ministry of Health · Assessment Platform
                </div>
            </div>

            {/* Form */}
            <div style={{ flex: 1, padding: "32px 24px", overflowY: "auto" }}>
                <div style={{ fontSize: 20, fontWeight: 800, color: T.text, marginBottom: 6 }}>Welcome back</div>
                <div style={{ fontSize: 13, color: T.textSub, marginBottom: 28 }}>Sign in to your assessor account</div>

                {error && (
                    <div style={{
                        background: "#FEE2E2", color: "#991B1B", borderRadius: T.radiusSm,
                        padding: "10px 14px", fontSize: 13, marginBottom: 16,
                    }}>{error}</div>
                )}

                {[
                    { label: "Email Address", val: email, set: setEmail, type: "email", icon: "✉" },
                    { label: "Password", val: password, set: setPassword, type: "password", icon: "🔒" },
                ].map((f) => (
                    <div key={f.label} style={{ marginBottom: 16 }}>
                        <div style={{ fontSize: 12, fontWeight: 700, color: T.textMid, marginBottom: 6, textTransform: "uppercase", letterSpacing: 0.8 }}>
                            {f.label}
                        </div>
                        <div style={{ position: "relative" }}>
                            <span style={{ position: "absolute", left: 13, top: "50%", transform: "translateY(-50%)", fontSize: 15 }}>{f.icon}</span>
                            <input
                                type={f.type} value={f.val}
                                onChange={(e) => f.set(e.target.value)}
                                onKeyDown={(e) => e.key === "Enter" && handleLogin()}
                                style={{
                                    width: "100%", padding: "13px 14px 13px 38px",
                                    borderRadius: T.radiusSm, border: `2px solid ${T.border}`,
                                    fontSize: 14, color: T.text, outline: "none",
                                    boxSizing: "border-box", fontFamily: "inherit",
                                    background: T.borderLight,
                                }}
                            />
                        </div>
                    </div>
                ))}

                <button onClick={handleLogin} disabled={loading} style={{
                    width: "100%", padding: 16, borderRadius: T.radius, border: "none",
                    background: loading ? "#D1D5DB" : "linear-gradient(135deg, #064E3B, #059669)",
                    color: "white", fontSize: 16, fontWeight: 700,
                    cursor: loading ? "not-allowed" : "pointer", transition: "all 0.2s",
                    marginTop: 8,
                }}>
                    {loading ? "Signing in…" : "Sign In →"}
                </button>

                <div style={{ marginTop: 20, padding: "14px 16px", background: "#F0FDF4", borderRadius: T.radiusSm }}>
                    <div style={{ fontSize: 11, color: T.textSub, fontWeight: 600, marginBottom: 6, textTransform: "uppercase", letterSpacing: 0.8 }}>
                        Demo credentials
                    </div>
                    <div style={{ fontSize: 12, color: T.textMid, lineHeight: 1.7 }}>
                        Email: alphonce@mnch.go.ke<br />Password: password123
                    </div>
                </div>
            </div>
        </div>
    );
}
