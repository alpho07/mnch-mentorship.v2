/**
 * scope-config.js
 *
 * Provides getScopesFromCache() — the single function ScopeShell calls to get
 * the list of scopes a user may access.
 *
 * Source of truth: the database (via /auth/me → cached in IndexedDB).
 * Fallback: FALLBACK_SCOPES — used only on first offline boot before any
 * successful login has populated the cache.
 */

import offlineStore from "./services/offline-store.js";

// ── Hardcoded fallback (first offline boot only) ─────────────────────────────
const FALLBACK_SCOPES = [
    {
        id: "assessments",
        label: "Assessments",
        icon: "assessments",
        color: "#0097A7",
        gradient: ["#00565A", "#0097A7"],
        tabs: ["home", "assessments", "reports", "profile"],
        summary: {},
    },
    {
        id: "mentorships",
        label: "Mentorships",
        icon: "mentorships",
        color: "#0EA5E9",
        gradient: ["#0284C7", "#0EA5E9"],
        tabs: ["home", "mentorships", "classes", "profile"],
        summary: {},
    },
    {
        id: "trainings",
        label: "Trainings",
        icon: "trainings",
        color: "#059669",
        gradient: ["#047857", "#10B981"],
        tabs: ["home", "trainings", "profile"],
        summary: {},
    },
];

/**
 * Returns scopes from IndexedDB cache (populated by ScopeShell from /auth/me).
 * Falls back to FALLBACK_SCOPES when cache is empty.
 *
 * @returns {Promise<Array>} Array of scope objects
 */
export async function getScopesFromCache() {
    const cached = await offlineStore.getScopeConfig();
    return Array.isArray(cached) && cached.length > 0 ? cached : FALLBACK_SCOPES;
}

/**
 * Saves scope config to IndexedDB cache.
 * Called by ScopeShell after a successful /auth/me response.
 *
 * @param {Array} scopes — scopes array from /auth/me user.scopes
 */
export async function cacheScopeConfig(scopes) {
    if (Array.isArray(scopes) && scopes.length > 0) {
        await offlineStore.saveScopeConfig(scopes);
    }
}
