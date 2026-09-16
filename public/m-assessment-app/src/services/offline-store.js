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
const DB_VERSION = 7;

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
    // v5 — mentorship/training modules
    mentorships: "mentorships",   // keyed by training_id
    participants: "participants",  // keyed by class_id
    myClasses: "myClasses",       // keyed by class_id (mentee view)
    trainings: "trainings",        // keyed by training_id (global)
    attendance: "attendance",      // keyed by module_id
    // v6 — mentorship creation, session notes, conflicts
    mentorshipMentees: "mentorshipMentees",  // keyed by class_id
    mentorshipSessions: "mentorshipSessions", // keyed by "class_${classId}" or "module_${moduleId}"
    conflicts: "conflicts",                   // keyed by conflict id (auto-generated)
    // v7 — scope config (role-driven navigation)
    scopeConfig: "scopeConfig",               // keyed by "config"
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

    // ── Mentorships (mentor view) ─────────────────────────────────────────────
    getMentorships: () => dbGet(STORES.mentorships, "list"),
    saveMentorships: (list) => dbPut(STORES.mentorships, "list", list),
    getMentorship: (id) => dbGet(STORES.mentorships, "detail_" + id),
    saveMentorship: (training) => dbPut(STORES.mentorships, "detail_" + training.id, training),
    getMentorshipClasses: (trainingId) => dbGet(STORES.mentorships, "classes_" + trainingId),
    saveMentorshipClasses: (trainingId, classes) => dbPut(STORES.mentorships, "classes_" + trainingId, classes),

    // ── Participants (per class) ───────────────────────────────────────────────
    getParticipants: (classId) => dbGet(STORES.participants, classId),
    saveParticipants: (classId, list) => dbPut(STORES.participants, classId, list),

    // ── Module attendance (offline state per module) ──────────────────────────
    getAttendance: (moduleId) => dbGet(STORES.attendance, moduleId),
    saveAttendance: (moduleId, data) => dbPut(STORES.attendance, moduleId, data),

    // ── Mentorship (add deleteMentorship for ID reconciliation) ──────────────
    deleteMentorship: (id) => dbDelete(STORES.mentorships, 'detail_' + id),

    // ── Mentorship mentees (enrolled participants per class) ──────────────────
    getMentees: (classId) => dbGet(STORES.mentorshipMentees, 'class_' + classId),
    saveMentees: (classId, list) => dbPut(STORES.mentorshipMentees, 'class_' + classId, list),

    // ── Session notes ─────────────────────────────────────────────────────────
    getSession: (sessionId) => dbGet(STORES.mentorshipSessions, 'session_' + sessionId),
    saveSession: (session) => dbPut(STORES.mentorshipSessions, 'session_' + session.id, session),
    getSessionsByModule: (moduleId) => dbGet(STORES.mentorshipSessions, 'module_' + moduleId),
    saveSessionsByModule: (moduleId, list) => dbPut(STORES.mentorshipSessions, 'module_' + moduleId, list),

    // ── Conflicts (failed sync ops needing user review) ───────────────────────
    getConflicts: () => dbGetAll(STORES.conflicts),
    saveConflict: (conflict) => dbPut(STORES.conflicts, conflict.id, conflict),
    resolveConflict: (id) => dbDelete(STORES.conflicts, id),
    getConflictCount: async () => (await dbGetAllKeys(STORES.conflicts)).length,

    // ── My Classes (mentee view) ──────────────────────────────────────────────
    getMyClasses: () => dbGet(STORES.myClasses, "list"),
    saveMyClasses: (list) => dbPut(STORES.myClasses, "list", list),
    getMyClass: (classId) => dbGet(STORES.myClasses, classId),
    saveMyClass: (classId, data) => dbPut(STORES.myClasses, classId, data),

    // ── Global Trainings ──────────────────────────────────────────────────────
    getTrainings: () => dbGet(STORES.trainings, "list"),
    saveTrainings: (list) => dbPut(STORES.trainings, "list", list),
    getTraining: (id) => dbGet(STORES.trainings, "detail_" + id),
    saveTraining: (t) => dbPut(STORES.trainings, "detail_" + t.id, t),

    // ── Resources ─────────────────────────────────────────────────────────────
    getResources: () => dbGet(STORES.meta, 'resources_list'),
    saveResources: (list) => dbPut(STORES.meta, 'resources_list', list),
    getResourcesCachedAt: () => dbGet(STORES.meta, 'resources_cached_at'),
    setResourcesCachedAt: (ts) => dbPut(STORES.meta, 'resources_cached_at', ts),

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

    // ── Scope Config ─────────────────────────────────────────────────────────
    getScopeConfig: () => dbGet(STORES.scopeConfig, "config"),
    saveScopeConfig: (scopes) => dbPut(STORES.scopeConfig, "config", scopes),
    clearScopeConfig: () => dbDelete(STORES.scopeConfig, "config"),

    // ── Class detail (modules embedded) ──────────────────────────────────────
    getClassDetail: (classId) => dbGet(STORES.mentorships, 'class_detail_' + classId),
    saveClassDetail: (classId, data) => dbPut(STORES.mentorships, 'class_detail_' + classId, data),

    // ── Module list per class ─────────────────────────────────────────────────
    getModuleList: (classId) => dbGet(STORES.mentorships, 'modules_' + classId),
    saveModuleList: (classId, list) => dbPut(STORES.mentorships, 'modules_' + classId, list),

    // ── Session templates per module ──────────────────────────────────────────
    getSessionTemplates: (moduleId) => dbGet(STORES.meta, 'session_templates_' + moduleId),
    saveSessionTemplates: (moduleId, list) => dbPut(STORES.meta, 'session_templates_' + moduleId, list),

    // ── Enrollment link per class ─────────────────────────────────────────────
    getEnrollmentLink: (classId) => dbGet(STORES.meta, 'enrollment_link_' + classId),
    saveEnrollmentLink: (classId, data) => dbPut(STORES.meta, 'enrollment_link_' + classId, data),

    // ── Sync timestamps (delta sync) ─────────────────────────────────────────
    getSyncedAt: (resource) => dbGet(STORES.meta, 'synced_at_' + resource),
    setSyncedAt: (resource, ts) => dbPut(STORES.meta, 'synced_at_' + resource, ts),

    // ── User lookup map (email → {id, name, phone}) ──────────────────────────
    getUserLookupMap: () => dbGet(STORES.meta, 'user_lookup_map'),
    saveUserLookupMap: (map) => dbPut(STORES.meta, 'user_lookup_map', map),
    mergeUserLookupMap: async (updates) => {
        const existing = (await dbGet(STORES.meta, 'user_lookup_map')) ?? {};
        for (const [email, record] of Object.entries(updates)) {
            if (record === null) {
                delete existing[email];
            } else {
                existing[email] = record;
            }
        }
        await dbPut(STORES.meta, 'user_lookup_map', existing);
    },

    // ── Queue dependency patching ─────────────────────────────────────────────
    // Replaces all occurrences of tempId with realId across every queued op.
    // Uses in-place dbPut (not clear+rewrite) to avoid losing ops under concurrent writes.
    patchQueueIds: async (tempId, realId) => {
        const queue = await dbGetAll(STORES.syncQueue);
        for (const entry of queue) {
            const json = JSON.stringify(entry);
            if (!json.includes(String(tempId))) continue;
            const patched = JSON.parse(json.replaceAll(String(tempId), String(realId)));
            await dbPut(STORES.syncQueue, patched.id, patched);
        }
    },

    // ── Full wipe (logout) ───────────────────────────────────────────────────
    clearAll: async () => {
        await Promise.all(Object.values(STORES).map(s => dbClear(s)));
    },
};

export default offlineStore;