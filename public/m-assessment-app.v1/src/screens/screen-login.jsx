import { useState } from "react";
import { T } from "../constants.js";
import api from "../services/api.service.js";

export function LoginScreen({ onLogin }) {
    const [email, setEmail] = useState("");
    const [password, setPassword] = useState("");
    const [error, setError] = useState("");
    const [loading, setLoading] = useState(false);
    const [focused, setFocused] = useState(null);
    const [showPassword, setShowPassword] = useState(false);

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
        <div style={{ display: "flex", flexDirection: "column", height: "100%", background: T.bg }}>
            <style>{`
                @keyframes heroFloat{0%,100%{transform:translateY(0) rotate(0deg)}50%{transform:translateY(-8px) rotate(2deg)}}
                @keyframes loginFade{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}
                @keyframes gradientBg{0%{background-position:0% 50%}50%{background-position:100% 50%}100%{background-position:0% 50%}}
                @keyframes btnShine{0%{left:-100%}100%{left:100%}}
            `}</style>

            {/* Hero */}
            <div style={{
                background: T.gradientDark,
                backgroundSize: "200% 200%",
                animation: "gradientBg 8s ease infinite",
                padding: "56px 28px 44px", position: "relative", overflow: "hidden",
                borderRadius: "24px 24px 28px 28px",
            }}>
                {/* Decorative orbs */}
                <div style={{ position: "absolute", width: 200, height: 200, borderRadius: "50%", background: "radial-gradient(circle, rgba(16,185,129,0.15) 0%, transparent 70%)", top: -60, right: -60 }} />
                <div style={{ position: "absolute", width: 120, height: 120, borderRadius: "50%", background: "radial-gradient(circle, rgba(52,211,153,0.1) 0%, transparent 70%)", bottom: -30, left: 20 }} />
                <div style={{ position: "absolute", width: 80, height: 80, borderRadius: "50%", background: "radial-gradient(circle, rgba(52,211,153,0.08) 0%, transparent 70%)", top: 40, left: -20 }} />

                {/* Logo */}
                <div style={{
                    width: 64, height: 64, borderRadius: 20,
                    background: "rgba(255,255,255,0.1)",
                    backdropFilter: "blur(8px)",
                    border: "1px solid rgba(255,255,255,0.15)",
                    display: "flex", alignItems: "center", justifyContent: "center",
                    fontSize: 32, marginBottom: 20,
                    animation: "heroFloat 4s ease-in-out infinite",
                }}>🩺</div>

                <div style={{
                    color: "white", fontSize: 28, fontWeight: 800,
                    letterSpacing: -0.5, lineHeight: 1.15,
                    animation: "loginFade 0.6s ease 0.1s both",
                }}>
                    MNCH Kenya
                </div>
                <div style={{
                    color: "#6EE7B7", fontSize: 18, fontWeight: 600,
                    marginTop: 2, letterSpacing: -0.3,
                    animation: "loginFade 0.6s ease 0.2s both",
                }}>
                    Mentorship Platform
                </div>
                <div style={{
                    color: "rgba(255,255,255,0.45)", fontSize: 12, marginTop: 8,
                    letterSpacing: 0.5,
                    animation: "loginFade 0.6s ease 0.3s both",
                }}>
                    Ministry of Health · Assessment Platform
                </div>
            </div>

            {/* Form */}
            <div style={{
                flex: 1, padding: "28px 24px 24px", overflowY: "auto",
                animation: "loginFade 0.5s ease 0.3s both",
            }}>
                <div style={{ fontSize: 22, fontWeight: 800, color: T.text, marginBottom: 4, letterSpacing: -0.3 }}>
                    Welcome back
                </div>
                <div style={{ fontSize: 14, color: T.textSub, marginBottom: 28 }}>
                    Sign in to your assessor account
                </div>

                {error && (
                    <div style={{
                        background: "linear-gradient(135deg, #FEE2E2, #FFF1F2)",
                        color: "#991B1B", borderRadius: T.radiusSm,
                        padding: "12px 16px", fontSize: 13, marginBottom: 18,
                        border: "1px solid #FECACA",
                        display: "flex", alignItems: "center", gap: 8,
                        animation: "scaleIn 0.2s ease",
                    }}>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#EF4444" strokeWidth="2">
                            <circle cx="12" cy="12" r="10" /><line x1="15" y1="9" x2="9" y2="15" /><line x1="9" y1="9" x2="15" y2="15" />
                        </svg>
                        {error}
                    </div>
                )}

                {/* Email field */}
                <div style={{ marginBottom: 18 }}>
                    <div style={{
                        fontSize: 12, fontWeight: 600, color: T.textMid,
                        marginBottom: 7, letterSpacing: 0.2,
                    }}>
                        Email Address
                    </div>
                    <div style={{
                        position: "relative",
                        borderRadius: T.radiusSm,
                        border: `2px solid ${focused === "email" ? T.primary : T.border}`,
                        background: focused === "email" ? "white" : T.borderLight,
                        transition: "all 0.2s cubic-bezier(0.4,0,0.2,1)",
                        boxShadow: focused === "email" ? `0 0 0 4px ${T.primaryGhost}` : "none",
                        overflow: "hidden",
                    }}>
                        <span style={{
                            position: "absolute", left: 14, top: "50%", transform: "translateY(-50%)",
                            display: "flex", alignItems: "center", transition: "all 0.2s",
                        }}>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke={focused === "email" ? T.primary : T.textMuted} strokeWidth="2" strokeLinecap="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" /><polyline points="22,6 12,13 2,6" /></svg>
                        </span>
                        <input
                            type="email" value={email}
                            placeholder="Enter your email address"
                            onChange={(e) => setEmail(e.target.value)}
                            onFocus={() => setFocused("email")}
                            onBlur={() => setFocused(null)}
                            onKeyDown={(e) => e.key === "Enter" && handleLogin()}
                            style={{
                                width: "100%", padding: "14px 16px 14px 44px",
                                border: "none", background: "transparent",
                                fontSize: 14, color: T.text, outline: "none",
                                boxSizing: "border-box", fontFamily: "inherit",
                            }}
                        />
                    </div>
                </div>

                {/* Password field with eye toggle */}
                <div style={{ marginBottom: 18 }}>
                    <div style={{
                        fontSize: 12, fontWeight: 600, color: T.textMid,
                        marginBottom: 7, letterSpacing: 0.2,
                    }}>
                        Password
                    </div>
                    <div style={{
                        position: "relative",
                        borderRadius: T.radiusSm,
                        border: `2px solid ${focused === "password" ? T.primary : T.border}`,
                        background: focused === "password" ? "white" : T.borderLight,
                        transition: "all 0.2s cubic-bezier(0.4,0,0.2,1)",
                        boxShadow: focused === "password" ? `0 0 0 4px ${T.primaryGhost}` : "none",
                        overflow: "hidden",
                    }}>
                        <span style={{
                            position: "absolute", left: 14, top: "50%", transform: "translateY(-50%)",
                            display: "flex", alignItems: "center", transition: "all 0.2s",
                        }}>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke={focused === "password" ? T.primary : T.textMuted} strokeWidth="2" strokeLinecap="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2" /><path d="M7 11V7a5 5 0 0110 0v4" /></svg>
                        </span>
                        <input
                            type={showPassword ? "text" : "password"} value={password}
                            placeholder="Enter your password"
                            onChange={(e) => setPassword(e.target.value)}
                            onFocus={() => setFocused("password")}
                            onBlur={() => setFocused(null)}
                            onKeyDown={(e) => e.key === "Enter" && handleLogin()}
                            style={{
                                width: "100%", padding: "14px 48px 14px 44px",
                                border: "none", background: "transparent",
                                fontSize: 14, color: T.text, outline: "none",
                                boxSizing: "border-box", fontFamily: "inherit",
                            }}
                        />
                        <button
                            type="button"
                            onClick={() => setShowPassword(p => !p)}
                            style={{
                                position: "absolute", right: 4, top: "50%", transform: "translateY(-50%)",
                                width: 38, height: 38, borderRadius: 10,
                                border: "none", background: "transparent",
                                cursor: "pointer", display: "flex", alignItems: "center", justifyContent: "center",
                                color: T.textMuted, transition: "color 0.2s",
                            }}
                        >
                            {showPassword ? (
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke={T.textMuted} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                    <path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94" />
                                    <path d="M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19" />
                                    <line x1="1" y1="1" x2="23" y2="23" />
                                    <path d="M14.12 14.12a3 3 0 11-4.24-4.24" />
                                </svg>
                            ) : (
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke={T.textMuted} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                    <circle cx="12" cy="12" r="3" />
                                </svg>
                            )}
                        </button>
                    </div>
                </div>

                <button onClick={handleLogin} disabled={loading} style={{
                    width: "100%", padding: 16, borderRadius: T.radius, border: "none",
                    background: loading ? "#D1D5DB" : T.gradientPrimary,
                    color: "white", fontSize: 16, fontWeight: 700,
                    cursor: loading ? "not-allowed" : "pointer",
                    transition: "all 0.3s cubic-bezier(0.4,0,0.2,1)",
                    marginTop: 4,
                    boxShadow: loading ? "none" : `0 8px 24px ${T.primaryGlow}`,
                    position: "relative", overflow: "hidden",
                    letterSpacing: 0.3,
                }}>
                    {!loading && (
                        <div style={{
                            position: "absolute", top: 0, left: "-100%",
                            width: "50%", height: "100%",
                            background: "linear-gradient(90deg, transparent, rgba(255,255,255,0.15), transparent)",
                            animation: "btnShine 3s infinite",
                        }} />
                    )}
                    {loading ? (
                        <span style={{ display: "flex", alignItems: "center", justifyContent: "center", gap: 8 }}>
                            <svg width="18" height="18" viewBox="0 0 24 24" style={{ animation: "spin 1s linear infinite" }}>
                                <circle cx="12" cy="12" r="10" fill="none" stroke="rgba(255,255,255,0.3)" strokeWidth="3" />
                                <path d="M12 2a10 10 0 019.95 9" fill="none" stroke="white" strokeWidth="3" strokeLinecap="round" />
                            </svg>
                            Signing in…
                        </span>
                    ) : "Sign In"}
                </button>

                <div style={{
                    marginTop: 22, padding: "14px 16px",
                    background: T.primaryGhost,
                    borderRadius: T.radiusSm,
                    border: `1px solid ${T.primary}15`,
                }}>
                    <div style={{
                        fontSize: 11, color: T.primary, fontWeight: 700,
                        marginBottom: 6, textTransform: "uppercase", letterSpacing: 0.8,
                        display: "flex", alignItems: "center", gap: 6,
                    }}>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke={T.primary} strokeWidth="2">
                            <circle cx="12" cy="12" r="10" /><line x1="12" y1="16" x2="12" y2="12" /><line x1="12" y1="8" x2="12.01" y2="8" />
                        </svg>
                        Assessments Made Easy!
                    </div>

                </div>
            </div>
        </div>
    );
}
