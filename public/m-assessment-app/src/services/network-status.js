import { useState, useEffect } from "react";
import { Network } from "@capacitor/network";

const BASE_URL = import.meta.env.VITE_API_BASE_URL ?? 'https://mnchkenyamentorship.org/api/v1';
// Derive the server root for the connectivity probe (/up is a Laravel 11 built-in health endpoint)
const PROBE_URL = new URL('/up', BASE_URL).href;

const listeners = new Set();
let _confirmed = navigator.onLine;

// Probe the actual server to confirm real connectivity (navigator.onLine is unreliable on
// captive portals, weak WiFi with no upstream, or airplane-mode transitions).
async function probe() {
    try {
        const controller = new AbortController();
        const tid = setTimeout(() => controller.abort(), 5000);
        await fetch(PROBE_URL, { method: 'HEAD', cache: 'no-store', signal: controller.signal });
        clearTimeout(tid);
        _confirmed = true;
    } catch {
        _confirmed = false;
    }
    listeners.forEach(fn => fn(_confirmed));
    return _confirmed;
}

const networkStatus = {
    get isOnline() { return _confirmed; },

    subscribe(fn) {
        listeners.add(fn);
        return () => listeners.delete(fn);
    },

    probe,

    _notify(forceOnline) {
        if (forceOnline === false) {
            _confirmed = false;
            listeners.forEach(fn => fn(false));
        } else {
            // navigator says online — confirm with a real probe
            probe();
        }
    },
};

// Native connectivity events (Capacitor's Network plugin — real OS-level
// changes on native, transparently falls back to navigator.onLine/online/
// offline in a plain browser). Replaces raw window online/offline listeners,
// which don't reliably fire on every WiFi<->cellular transition in an
// Android WebView.
Network.addListener('networkStatusChange', (status) => {
    networkStatus._notify(status.connected ? true : false);
});

// Initial probe on load if the device thinks we're online
Network.getStatus().then(status => {
    if (status.connected) probe();
});

export function useNetworkStatus() {
    const [online, setOnline] = useState(_confirmed);
    useEffect(() => networkStatus.subscribe(setOnline), []);
    return online;
}

export default networkStatus;


