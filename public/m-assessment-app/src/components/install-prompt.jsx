import { useState, useEffect } from 'react';

/**
 * Shows a bottom-sheet install banner when the browser fires beforeinstallprompt.
 * The user can accept (triggers native install dialog) or dismiss (hides for the session).
 * On iOS Safari, there is no beforeinstallprompt — we show manual instructions instead.
 */
export function InstallPrompt() {
    const [deferredPrompt, setDeferredPrompt] = useState(null);
    const [show, setShow]                     = useState(false);
    const [isIos, setIsIos]                   = useState(false);
    const [dismissed, setDismissed]           = useState(false);

    useEffect(() => {
        // Already installed (standalone mode) — don't show anything
        if (window.matchMedia('(display-mode: standalone)').matches) return;
        // User dismissed this session
        if (sessionStorage.getItem('pwa_dismissed')) return;

        const ua = navigator.userAgent;
        const ios = /iphone|ipad|ipod/i.test(ua) && !/crios/i.test(ua);
        if (ios) {
            // iOS Safari: show manual instructions after a short delay
            const t = setTimeout(() => setIsIos(true), 3000);
            return () => clearTimeout(t);
        }

        const handler = (e) => {
            e.preventDefault();
            setDeferredPrompt(e);
            setShow(true);
        };
        window.addEventListener('beforeinstallprompt', handler);
        return () => window.removeEventListener('beforeinstallprompt', handler);
    }, []);

    function dismiss() {
        sessionStorage.setItem('pwa_dismissed', '1');
        setShow(false);
        setIsIos(false);
        setDismissed(true);
    }

    async function install() {
        if (!deferredPrompt) return;
        deferredPrompt.prompt();
        const { outcome } = await deferredPrompt.userChoice;
        setDeferredPrompt(null);
        setShow(false);
        if (outcome === 'dismissed') sessionStorage.setItem('pwa_dismissed', '1');
    }

    const visible = (show && deferredPrompt) || (isIos && !dismissed);
    if (!visible) return null;

    return (
        <div style={{
            position: 'fixed', bottom: 72, left: 12, right: 12, zIndex: 9999,
            background: '#1E293B', borderRadius: 16,
            boxShadow: '0 8px 32px rgba(0,0,0,0.35)',
            padding: '14px 16px',
            animation: 'slideUp 0.3s cubic-bezier(0.34,1.56,0.64,1)',
        }}>
            <style>{`@keyframes slideUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}`}</style>

            <div style={{ display: 'flex', gap: 12, alignItems: 'center' }}>
                <img src="/icon-192.png" alt="" style={{ width: 44, height: 44, borderRadius: 10, flexShrink: 0 }} />
                <div style={{ flex: 1, minWidth: 0 }}>
                    <div style={{ fontSize: 13, fontWeight: 700, color: '#fff', marginBottom: 2 }}>
                        Install MNCH Assessments
                    </div>
                    {isIos ? (
                        <div style={{ fontSize: 11, color: 'rgba(255,255,255,0.7)', lineHeight: 1.4 }}>
                            Tap <strong style={{ color: '#0097A7' }}>Share</strong> then <strong style={{ color: '#0097A7' }}>Add to Home Screen</strong> to install
                        </div>
                    ) : (
                        <div style={{ fontSize: 11, color: 'rgba(255,255,255,0.7)', lineHeight: 1.4 }}>
                            Add to your home screen for offline access
                        </div>
                    )}
                </div>

                <button onClick={dismiss} style={{
                    background: 'rgba(255,255,255,0.1)', border: 'none', borderRadius: 8,
                    color: 'rgba(255,255,255,0.6)', cursor: 'pointer',
                    width: 28, height: 28, fontSize: 14, flexShrink: 0,
                    display: 'flex', alignItems: 'center', justifyContent: 'center',
                }}>✕</button>
            </div>

            {!isIos && (
                <div style={{ display: 'flex', gap: 8, marginTop: 12 }}>
                    <button onClick={dismiss} style={{
                        flex: 1, padding: '9px 0', border: '1px solid rgba(255,255,255,0.15)',
                        borderRadius: 10, background: 'transparent',
                        color: 'rgba(255,255,255,0.6)', fontSize: 12, fontWeight: 600, cursor: 'pointer',
                    }}>Not now</button>
                    <button onClick={install} style={{
                        flex: 2, padding: '9px 0', border: 'none',
                        borderRadius: 10, background: '#0097A7',
                        color: '#fff', fontSize: 12, fontWeight: 700, cursor: 'pointer',
                    }}>Install app</button>
                </div>
            )}
        </div>
    );
}
