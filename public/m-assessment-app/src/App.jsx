import { useState, useEffect } from "react";
import { App as CapacitorApp } from "@capacitor/app";
import { SplashScreen } from "@capacitor/splash-screen";
import { LoginScreen } from "./screens/screen-login.jsx";
import { RegisterScreen } from "./screens/screen-register.jsx";
import { ForgotPasswordScreen } from "./screens/screen-forgot-password.jsx";
import { SetPasswordScreen } from "./screens/screen-set-password.jsx";
import { ScopeShell } from "./components/ScopeShell.jsx";
import { InstallPrompt } from "./components/install-prompt.jsx";
import { T } from "./constants.js";
import api from "./services/api.service.js";
import offlineStore from "./services/offline-store.js";

// ── normaliseUser ─────────────────────────────────────────────────────────────
function normaliseUser(u) {
    if (!u) return u;
    const fac = typeof u.facility === "object" && u.facility !== null ? u.facility : null;
    return {
        ...u,
        facility:    fac?.name ?? (typeof u.facility === "string" ? u.facility : ""),
        facility_id: u.facility_id ?? fac?.id ?? null,
        county:      fac?.county ?? u.county ?? "",
        county_id:   u.county_id ?? fac?.county_id ?? null,
        subcounty:   fac?.subcounty ?? u.subcounty ?? "",
        mfl_code:    fac?.mfl_code ?? u.mfl_code ?? "",
        initials:    u.initials || ((u.name || "??").split(" ").map(p => p[0]).join("").slice(0, 2).toUpperCase()),
    };
}

// ── parseDeepLink ────────────────────────────────────────────────────────────
// Parses the App-Links URLs handed to the app via appUrlOpen:
//   https://mnchkenyamentorship.org/account/verify/{user}?expires=...&signature=...
//   https://mnchkenyamentorship.org/admin/set-password/{token}?email=...
function parseDeepLink(urlString) {
    let url;
    try { url = new URL(urlString); } catch { return null; }

    const verifyMatch = url.pathname.match(/\/account\/verify\/(\d+)/);
    if (verifyMatch) {
        return {
            type: "verify-account",
            userId: verifyMatch[1],
            expires: url.searchParams.get("expires"),
            signature: url.searchParams.get("signature"),
        };
    }

    const resetMatch = url.pathname.match(/\/admin\/set-password\/([^/?]+)/);
    if (resetMatch) {
        return {
            type: "reset-password",
            token: resetMatch[1],
            email: url.searchParams.get("email"),
        };
    }

    return null;
}

export default function App() {
    const [user, setUser]       = useState(null);
    const [loading, setLoading] = useState(true);
    const [deepLinkTarget, setDeepLinkTarget] = useState(null);
    const [authScreen, setAuthScreen] = useState("login"); // "login" | "register" | "forgot-password"
    const [forgotPasswordEmail, setForgotPasswordEmail] = useState("");

    useEffect(() => {
        const listenerPromise = CapacitorApp.addListener("appUrlOpen", ({ url }) => {
            const parsed = parseDeepLink(url);
            if (parsed) setDeepLinkTarget(parsed);
        });
        return () => { listenerPromise.then(l => l.remove()); };
    }, []);

    // ── Session restore ──────────────────────────────────────────────────────
    // Load cached user immediately (offline-first), then refresh from server
    // in the background so the app is usable instantly without a network round-trip.
    useEffect(() => {
        const token = api.getToken();
        if (!token) { setLoading(false); return; }

        offlineStore.getUser().then(cached => {
            if (cached?.id) {
                setUser(normaliseUser(cached));
                setLoading(false);
                // Background refresh — silently update cached user; on 401 log out
                api.auth.me()
                    .then(data => {
                        const u = data?.user ?? data;
                        if (u?.id) setUser(normaliseUser(u));
                    })
                    .catch((e) => { if (e?.status === 401) { api.clearToken(); setUser(null); } });
            } else {
                // No cached user — must go to network
                api.auth.me()
                    .then(data => {
                        const u = data?.user ?? data;
                        if (u?.id) setUser(normaliseUser(u));
                    })
                    .catch(() => api.clearToken())
                    .finally(() => setLoading(false));
            }
        });
    }, []);

    // Hide the native splash (launchAutoHide:false in capacitor.config.json)
    // only once the session-restore check above has actually resolved —
    // avoids a flash of the login/loading screen behind the native splash
    // on a cold start.
    useEffect(() => {
        if (!loading) SplashScreen.hide();
    }, [loading]);

    if (loading) {
        return (
            <div style={{
                display: "flex", flexDirection: "column",
                alignItems: "center", justifyContent: "center",
                height: "100vh", background: T.bg,
                fontFamily: "-apple-system, 'SF Pro Display', 'Segoe UI', system-ui, sans-serif",
                gap: 16,
            }}>
                <div style={{
                    width: 52, height: 52, borderRadius: 16,
                    background: T.gradientPrimary,
                    display: "flex", alignItems: "center", justifyContent: "center",
                    boxShadow: `0 8px 24px ${T.primaryGlow}`,
                }}>
                    <svg width="26" height="26" viewBox="0 0 24 24" style={{ animation: "spin 1s linear infinite" }}>
                        <circle cx="12" cy="12" r="10" fill="none" stroke="rgba(255,255,255,0.25)" strokeWidth="3"/>
                        <path d="M12 2a10 10 0 019.95 9" fill="none" stroke="white" strokeWidth="3" strokeLinecap="round"/>
                    </svg>
                </div>
                <div style={{ color: T.textMuted, fontSize: 13, fontWeight: 500 }}>Loading…</div>
            </div>
        );
    }

    if (!user) {
        if (deepLinkTarget?.type === "verify-account") {
            return (
                <SetPasswordScreen
                    mode="verify-account"
                    target={deepLinkTarget}
                    onDone={(verifiedUser) => {
                        setDeepLinkTarget(null);
                        if (verifiedUser) setUser(normaliseUser(verifiedUser));
                        else setAuthScreen("login");
                    }}
                />
            );
        }
        if (deepLinkTarget?.type === "reset-password") {
            return (
                <SetPasswordScreen
                    mode="reset-password"
                    target={deepLinkTarget}
                    onDone={() => { setDeepLinkTarget(null); setAuthScreen("login"); }}
                    onBack={() => { setDeepLinkTarget(null); setAuthScreen("login"); }}
                />
            );
        }
        if (authScreen === "register") {
            return (
                <RegisterScreen
                    onRegistered={() => setAuthScreen("login")}
                    onBack={() => setAuthScreen("login")}
                    onGoToForgotPassword={(email) => { setForgotPasswordEmail(email || ""); setAuthScreen("forgot-password"); }}
                />
            );
        }
        if (authScreen === "forgot-password") {
            return <ForgotPasswordScreen initialEmail={forgotPasswordEmail} onBack={() => setAuthScreen("login")} />;
        }
        return (
            <LoginScreen
                onLogin={(u) => setUser(normaliseUser(u))}
                onGoToRegister={() => setAuthScreen("register")}
                onGoToForgotPassword={() => setAuthScreen("forgot-password")}
            />
        );
    }

    return (
        <>
            <ScopeShell
                user={user}
                onLogout={() => { api.clearToken(); setUser(null); }}
                onUserUpdate={(u) => setUser(normaliseUser(u))}
            />
            <InstallPrompt />
        </>
    );
}


