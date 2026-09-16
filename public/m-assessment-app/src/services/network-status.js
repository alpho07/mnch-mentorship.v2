import { useState, useEffect } from "react";

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

window.addEventListener('online',  () => networkStatus._notify(true));
window.addEventListener('offline', () => networkStatus._notify(false));

// Initial probe on load if navigator thinks we're online
if (navigator.onLine) probe();

export function useNetworkStatus() {
    const [online, setOnline] = useState(_confirmed);
    useEffect(() => networkStatus.subscribe(setOnline), []);
    return online;
}

export default networkStatus;


