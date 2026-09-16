import { useState } from "react";
import { T } from "../constants.js";
import api from "../services/api.service.js";

export function LoginScreen({ onLogin }) {
    const [email, setEmail]             = useState("");
    const [password, setPassword]       = useState("");
    const [error, setError]             = useState("");
    const [loading, setLoading]         = useState(false);
    const [focused, setFocused]         = useState(null);
    const [showPassword, setShowPassword] = useState(false);

    const handleLogin = async () => {
        if (!email || !password) { setError("Email and password are required."); return; }
        setLoading(true); setError("");
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
        <div style={{ display: "flex", flexDirection: "column", height: "100%", background: T.bg,
            fontFamily: "-apple-system, 'SF Pro Display', 'Segoe UI', system-ui, sans-serif" }}>
            <style>{`
                @keyframes logoFloat{0%,100%{transform:translateY(0)}50%{transform:translateY(-6px)}}
                @keyframes loginFade{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}
                @keyframes btnShine{0%{left:-100%}100%{left:150%}}
                @keyframes spinCW{from{transform:rotate(0deg)}to{transform:rotate(360deg)}}
            `}</style>

            {/* Hero */}
            <div style={{
                background: T.gradientHero,
                padding: "44px 28px 40px",
                position: "relative", overflow: "hidden",
                borderRadius: "0 0 28px 28px",
                margin: "0 6px",
            }}>
                {/* Orbs */}
                <div style={{ position:"absolute", width:200, height:200, borderRadius:"50%",
                    background:"radial-gradient(circle, rgba(79,106,245,0.25) 0%, transparent 70%)",
                    top:-60, right:-60, pointerEvents:"none" }} />
                <div style={{ position:"absolute", width:120, height:120, borderRadius:"50%",
                    background:"radial-gradient(circle, rgba(108,99,255,0.18) 0%, transparent 70%)",
                    bottom:-30, left:20, pointerEvents:"none" }} />

                {/* Logo mark */}
                <div style={{
                    width:64, height:64, borderRadius:20,
                    background: T.gradientPrimary,
                    display:"flex", alignItems:"center", justifyContent:"center",
                    marginBottom:20,
                    boxShadow:"0 8px 28px rgba(79,106,245,0.40)",
                    animation:"logoFloat 4s ease-in-out infinite",
                    position:"relative", zIndex:1,
                }}>
                    <svg width="34" height="34" viewBox="0 0 34 34" fill="none">
                        <rect x="13" y="2" width="8" height="30" rx="4" fill="white" fillOpacity="0.95"/>
                        <rect x="2" y="13" width="30" height="8" rx="4" fill="white" fillOpacity="0.95"/>
                        <circle cx="17" cy="17" r="5" fill="white"/>
                    </svg>
                </div>

                <div style={{ color:"white", fontSize:28, fontWeight:800, letterSpacing:-0.5,
                    lineHeight:1.15, animation:"loginFade 0.5s ease 0.1s both", position:"relative", zIndex:1 }}>
                    MNCH Kenya
                </div>
                <div style={{ color:"rgba(255,255,255,0.55)", fontSize:16, fontWeight:600,
                    marginTop:3, letterSpacing:-0.2,
                    animation:"loginFade 0.5s ease 0.2s both", position:"relative", zIndex:1 }}>
                    Mentorship Platform
                </div>
                <div style={{ color:"rgba(255,255,255,0.35)", fontSize:12, marginTop:6,
                    letterSpacing:0.5, animation:"loginFade 0.5s ease 0.3s both",
                    position:"relative", zIndex:1 }}>
                    MINISTRY OF HEALTH
                </div>
            </div>

            {/* Form */}
            <div style={{ flex:1, padding:"28px 20px 24px", overflowY:"auto",
                animation:"loginFade 0.45s ease 0.25s both" }}>
                <div style={{ fontSize:22, fontWeight:800, color:T.text, marginBottom:4,
                    letterSpacing:-0.4 }}>Welcome back</div>
                <div style={{ fontSize:14, color:T.textMuted, marginBottom:28 }}>
                    Sign in to your assessor account
                </div>

                {error && (
                    <div style={{
                        background:"#FEF2F2", color:"#991B1B", borderRadius:T.radiusSm,
                        padding:"12px 16px", fontSize:13, marginBottom:18,
                        border:"1px solid #FECACA", display:"flex", alignItems:"center", gap:8,
                        animation:"loginFade 0.2s ease",
                    }}>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#EF4444" strokeWidth="2">
                            <circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>
                        </svg>
                        {error}
                    </div>
                )}

                {/* Email */}
                <div style={{ marginBottom:16 }}>
                    <div style={{ fontSize:12, fontWeight:700, color:T.textMid, marginBottom:7, letterSpacing:0.2 }}>
                        Email Address
                    </div>
                    <div style={{
                        position:"relative", borderRadius:T.radiusSm,
                        background:"white",
                        boxShadow: focused === "email"
                            ? `0 0 0 3px rgba(79,106,245,0.20), ${T.shadowCard}`
                            : T.shadowCard,
                        transition:"box-shadow 0.2s cubic-bezier(0.4,0,0.2,1)",
                        overflow:"hidden",
                    }}>
                        <span style={{ position:"absolute", left:14, top:"50%", transform:"translateY(-50%)",
                            display:"flex", alignItems:"center" }}>
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none"
                                stroke={focused === "email" ? T.primary : T.textMuted} strokeWidth="2" strokeLinecap="round">
                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                                <polyline points="22,6 12,13 2,6"/>
                            </svg>
                        </span>
                        <input type="email" value={email} placeholder="Enter your email"
                            onChange={e => setEmail(e.target.value)}
                            onFocus={() => setFocused("email")} onBlur={() => setFocused(null)}
                            onKeyDown={e => e.key === "Enter" && handleLogin()}
                            style={{ width:"100%", padding:"14px 16px 14px 44px",
                                border:"none", background:"transparent",
                                fontSize:14, color:T.text, outline:"none",
                                boxSizing:"border-box", fontFamily:"inherit" }} />
                    </div>
                </div>

                {/* Password */}
                <div style={{ marginBottom:20 }}>
                    <div style={{ fontSize:12, fontWeight:700, color:T.textMid, marginBottom:7, letterSpacing:0.2 }}>
                        Password
                    </div>
                    <div style={{
                        position:"relative", borderRadius:T.radiusSm,
                        background:"white",
                        boxShadow: focused === "password"
                            ? `0 0 0 3px rgba(79,106,245,0.20), ${T.shadowCard}`
                            : T.shadowCard,
                        transition:"box-shadow 0.2s cubic-bezier(0.4,0,0.2,1)",
                        overflow:"hidden",
                    }}>
                        <span style={{ position:"absolute", left:14, top:"50%", transform:"translateY(-50%)",
                            display:"flex", alignItems:"center" }}>
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none"
                                stroke={focused === "password" ? T.primary : T.textMuted} strokeWidth="2" strokeLinecap="round">
                                <rect x="3" y="11" width="18" height="11" rx="2"/>
                                <path d="M7 11V7a5 5 0 0110 0v4"/>
                            </svg>
                        </span>
                        <input type={showPassword ? "text" : "password"} value={password}
                            placeholder="Enter your password"
                            onChange={e => setPassword(e.target.value)}
                            onFocus={() => setFocused("password")} onBlur={() => setFocused(null)}
                            onKeyDown={e => e.key === "Enter" && handleLogin()}
                            style={{ width:"100%", padding:"14px 48px 14px 44px",
                                border:"none", background:"transparent",
                                fontSize:14, color:T.text, outline:"none",
                                boxSizing:"border-box", fontFamily:"inherit" }} />
                        <button type="button" onClick={() => setShowPassword(p => !p)} aria-label={showPassword ? "Hide password" : "Show password"} style={{
                            position:"absolute", right:4, top:"50%", transform:"translateY(-50%)",
                            width:38, height:38, borderRadius:10, border:"none",
                            background:"transparent", cursor:"pointer",
                            display:"flex", alignItems:"center", justifyContent:"center",
                        }}>
                            {showPassword
                                ? <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke={T.textMuted} strokeWidth="2" strokeLinecap="round"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/><path d="M14.12 14.12a3 3 0 11-4.24-4.24"/></svg>
                                : <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke={T.textMuted} strokeWidth="2" strokeLinecap="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            }
                        </button>
                    </div>
                </div>

                <button onClick={handleLogin} disabled={loading} style={{
                    width:"100%", padding:15, borderRadius:T.radiusSm, border:"none",
                    background: loading ? T.borderLight : T.gradientPrimary,
                    color: loading ? T.textMuted : "white",
                    fontSize:15, fontWeight:700,
                    cursor: loading ? "not-allowed" : "pointer",
                    transition:"all 0.3s cubic-bezier(0.4,0,0.2,1)",
                    boxShadow: loading ? "none" : `0 6px 20px ${T.primaryGlow}`,
                    position:"relative", overflow:"hidden", letterSpacing:0.2,
                }}>
                    {!loading && (
                        <div style={{
                            position:"absolute", top:0, left:"-100%",
                            width:"50%", height:"100%",
                            background:"linear-gradient(90deg,transparent,rgba(255,255,255,0.18),transparent)",
                            animation:"btnShine 3s infinite",
                        }}/>
                    )}
                    {loading ? (
                        <span style={{ display:"flex", alignItems:"center", justifyContent:"center", gap:8 }}>
                            <svg width="17" height="17" viewBox="0 0 24 24" style={{ animation:"spinCW 1s linear infinite" }}>
                                <circle cx="12" cy="12" r="10" fill="none" stroke="rgba(79,106,245,0.15)" strokeWidth="3"/>
                                <path d="M12 2a10 10 0 019.95 9" fill="none" stroke={T.primary} strokeWidth="3" strokeLinecap="round"/>
                            </svg>
                            Signing in…
                        </span>
                    ) : "Sign In"}
                </button>

                <button type="button" style={{
                    background:"none", border:"none", padding:0,
                    textAlign:"center", marginTop:16, fontSize:13, color:T.primary, fontWeight:600,
                    cursor:"pointer", width:"100%",
                }}>
                    Forgot password?
                </button>
            </div>
        </div>
    );
}
