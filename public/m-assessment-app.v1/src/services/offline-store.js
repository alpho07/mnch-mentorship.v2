/**
 * MNCH Offline Store
 * src/services/offline-store.js
 *
 * Thin IndexedDB wrapper that caches API data locally for offline use.
 * Uses a single database "mnch_offline" with object stores for each data type.
 *
 * All methods are async and safe to call even if IndexedDB is unavailable
 * (gracefully returns null/empty).
 */

const DB_NAME = "mnch_offline";
const DB_VERSION = 4;

const STORES = {
    schema: "schema", // full section schema (keyed by "full")
    assessments: "assessments", // assessment list (keyed by assessment id)
    responses: "responses", // responses per assessment (keyed by assessment id)
    hr: "hr", // HR data per assessment (keyed by assessment id)
    hp: "hp", // Health Products per assessment (keyed by assessment id)
    user: "user", // cached user profile (keyed by "me")
    syncQueue: "syncQueue", // pending write operations
    meta: "meta", // timestamps, version info
    facilities: "facilities", // facilities list
    emailJobs: "emailJobs", // pending / completed report email jobs (keyed by job id)
};

// ── Open / upgrade database ─────────────────────────────────────────────────
let dbPromise = null;

function openDB() {
    if (dbPromise)
        return dbPromise;
    dbPromise = new Promise((resolve, reject) => {
        try {
            const req = indexedDB.open(DB_NAME, DB_VERSION);
            req.onupgradeneeded = (e) => {
                const db = e.target.result;
                Object.values(STORES).forEach(name => {
                    if (!db.objectStoreNames.contains(name)) {
                        db.createObjectStore(name);
                    }
                });
            };
            req.onsuccess = () => resolve(req.result);
            req.onerror = () => {
                console.warn("[OfflineStore] IndexedDB open failed:", req.error);
                reject(req.error);
            };
        } catch (e) {
            console.warn("[OfflineStore] IndexedDB not available:", e);
            reject(e);
        }
    });
    return dbPromise;
}

// ── Generic get/put/delete/getAll ────────────────────────────────────────────
async function dbGet(storeName, key) {
    try {
        const db = await openDB();
        return new Promise((resolve, reject) => {
            const tx = db.transaction(storeName, "readonly");
            const req = tx.objectStore(storeName).get(key);
            req.onsuccess = () => resolve(req.result ?? null);
            req.onerror = () => reject(req.error);
        });
    } catch {
        return null;
    }
}

async function dbPut(storeName, key, value) {
    try {
        const db = await openDB();
        return new Promise((resolve, reject) => {
            const tx = db.transaction(storeName, "readwrite");
            tx.objectStore(storeName).put(value, key);
            tx.oncomplete = () => resolve(true);
            tx.onerror = () => reject(tx.error);
        });
    } catch {
        return false;
    }
}

async function dbDelete(storeName, key) {
    try {
        const db = await openDB();
        return new Promise((resolve, reject) => {
            const tx = db.transaction(storeName, "readwrite");
            tx.objectStore(storeName).delete(key);
            tx.oncomplete = () => resolve(true);
            tx.onerror = () => reject(tx.error);
        });
    } catch {
        return false;
    }
}

async function dbGetAll(storeName) {
    try {
        const db = await openDB();
        return new Promise((resolve, reject) => {
            const tx = db.transaction(storeName, "readonly");
            const req = tx.objectStore(storeName).getAll();
            req.onsuccess = () => resolve(req.result ?? []);
            req.onerror = () => reject(req.error);
        });
    } catch {
        return [];
    }
}

async function dbGetAllKeys(storeName) {
    try {
        const db = await openDB();
        return new Promise((resolve, reject) => {
            const tx = db.transaction(storeName, "readonly");
            const req = tx.objectStore(storeName).getAllKeys();
            req.onsuccess = () => resolve(req.result ?? []);
            req.onerror = () => reject(req.error);
        });
    } catch {
        return [];
    }
}

async function dbClear(storeName) {
    try {
        const db = await openDB();
        return new Promise((resolve, reject) => {
            const tx = db.transaction(storeName, "readwrite");
            tx.objectStore(storeName).clear();
            tx.oncomplete = () => resolve(true);
            tx.onerror = () => reject(tx.error);
        });
    } catch {
        return false;
    }
}

// ── Public API ───────────────────────────────────────────────────────────────

const offlineStore = {
    // ── Schema ───────────────────────────────────────────────────────────────
    getSchema: () => dbGet(STORES.schema, "full"),
    saveSchema: (data) => dbPut(STORES.schema, "full", data),

    // ── Assessments ──────────────────────────────────────────────────────────
    getAssessments: () => dbGetAll(STORES.assessments),
    getAssessment: (id) => dbGet(STORES.assessments, id),
    saveAssessment: (a) => dbPut(STORES.assessments, a.id, a),
    saveAssessments: async (list) => {
        for (const a of list)
            await dbPut(STORES.assessments, a.id, a);
    },

    // ── Responses (per assessment) ───────────────────────────────────────────
    getResponses: (assessmentId) => dbGet(STORES.responses, assessmentId),
    saveResponses: (assessmentId, data) => dbPut(STORES.responses, assessmentId, data),

    // ── Human Resources (per assessment) ─────────────────────────────────────
    getHR: (assessmentId) => dbGet(STORES.hr, assessmentId),
    saveHR: (assessmentId, data) => dbPut(STORES.hr, assessmentId, data),

    // ── Health Products (per assessment) ─────────────────────────────────────
    getHP: (assessmentId) => dbGet(STORES.hp, assessmentId),
    saveHP: (assessmentId, data) => dbPut(STORES.hp, assessmentId, data),

    // ── User ─────────────────────────────────────────────────────────────────
    getUser: () => dbGet(STORES.user, "me"),
    saveUser: (u) => dbPut(STORES.user, "me", u),
    clearUser: () => dbDelete(STORES.user, "me"),

    // ── Sync Queue ───────────────────────────────────────────────────────────
    addToQueue: async (op) => {
        const id = Date.now() + "_" + Math.random().toString(36).slice(2, 8);
        await dbPut(STORES.syncQueue, id, {...op, id, timestamp: Date.now()});
        return id;
    },
    getQueue: () => dbGetAll(STORES.syncQueue),
    getQueueKeys: () => dbGetAllKeys(STORES.syncQueue),
    removeFromQueue: (id) => dbDelete(STORES.syncQueue, id),
    clearQueue: () => dbClear(STORES.syncQueue),
    getQueueCount: async () => (await dbGetAllKeys(STORES.syncQueue)).length,

    // ── Meta ─────────────────────────────────────────────────────────────────
    getMeta: (key) => dbGet(STORES.meta, key),
    setMeta: (key, value) => dbPut(STORES.meta, key, value),

    // ── Facilities ────────────────────────────────────────────────────────────
    getFacilities: () => dbGet(STORES.facilities, "all"),
    saveFacilities: (list) => dbPut(STORES.facilities, "all", list),

    // ── Email Jobs ────────────────────────────────────────────────────────────
    // Each job is stored under its id (string). Local-only jobs use "local_" prefix.
    saveEmailJob: (job) => dbPut(STORES.emailJobs, String(job.id), job),
    getEmailJob: (id) => dbGet(STORES.emailJobs, String(id)),
    getAllEmailJobs: () => dbGetAll(STORES.emailJobs),
    deleteEmailJob: (id) => dbDelete(STORES.emailJobs, String(id)),
    saveEmailJobs: async (jobs) => {
        for (const job of jobs) {
            await dbPut(STORES.emailJobs, String(job.id), job);
        }
    },

    // ── Assessment lifecycle helpers ──────────────────────────────────────────
    deleteAssessment: (id) => dbDelete(STORES.assessments, id),

    /**
     * Migrate data stored under fromId to toId across responses, hr, and hp stores.
     * Used when a provisional offline assessment gets a real server ID.
     * Deletes the fromId records after copying to avoid orphaned entries.
     */
    copyAssessmentData: async (fromId, toId) => {
        for (const storeName of [STORES.responses, STORES.hr, STORES.hp]) {
            try {
                const data = await dbGet(storeName, fromId);
                if (data !== null) {
                    await dbPut(storeName, toId, data);
                    await dbDelete(storeName, fromId);
                }
            } catch {
                // Non-critical — best effort migration
            }
        }
    },

    // ── Full wipe (logout) ───────────────────────────────────────────────────
    clearAll: async () => {
        await Promise.all(Object.values(STORES).map(s => dbClear(s)));
    },
};

export default offlineStore;