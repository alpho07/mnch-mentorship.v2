/**
 * MNCH Sync Queue
 * src/services/sync-queue.js
 *
 * Manages a queue of pending write operations (responses, HR, HP, submit, progress).
 * When connectivity returns, replays operations in order against the live API.
 *
 * Emits status via a subscriber pattern so the UI can show sync state.
 */

import offlineStore from "./offline-store.js";
// ── Status management ────────────────────────────────────────────────────────
// status: "idle" | "syncing" | "error" | "offline"
let _status = navigator.onLine ? "idle" : "offline";
let _pendingCount = 0;
let _lastError = null;
let _listeners = new Set();

function notify() {
    const state = {status: _status, pendingCount: _pendingCount, lastError: _lastError};
    _listeners.forEach(fn => {
        try {
            fn(state);
        } catch {
            // Swallow listener errors to prevent one bad subscriber from breaking others
        }
    });
}

function setStatus(s, err = null) {
    _status = s;
    _lastError = err;
    notify();
}

async function refreshCount() {
    _pendingCount = await offlineStore.getQueueCount();
    notify();
}

// ── Queue an operation ───────────────────────────────────────────────────────
// op shape: { type, assessmentId, sectionCode?, payload }
//
// Types:
//   "responses.bulkSave"       → { assessmentId, sectionCode, responses, explanations }
//   "assessments.progress"     → { assessmentId, sectionCode, done }
//   "assessments.submit"       → { assessmentId }
//   "humanResources.save"      → { assessmentId, responses }
//   "healthProducts.save"      → { assessmentId, responses, departmentId? }
//   "assessments.create"       → { tempId, facility_id, assessment_type, assessment_date }

async function enqueue(op) {
    await offlineStore.addToQueue(op);
    await refreshCount();
    console.log(`[SyncQueue] Enqueued: ${op.type} (${_pendingCount} pending)`);

    // If online, try to flush immediately
    if (navigator.onLine) {
        flush();
    }
}

// ── Replay pending operations ────────────────────────────────────────────────
let _flushing = false;

async function flush() {
    if (_flushing)
        return;
    if (!navigator.onLine) {
        setStatus("offline");
        return;
    }

    _flushing = true;
    setStatus("syncing");

    try {
        // We need the raw API (without offline wrapper) to avoid recursion
        const {_rawApi} = await import("./api.service.js");

        const queue = await offlineStore.getQueue();
        if (queue.length === 0) {
            setStatus("idle");
            _flushing = false;
            await refreshCount();
            return;
        }

        // Sort by timestamp to maintain order
        queue.sort((a, b) => a.timestamp - b.timestamp);

        let successCount = 0;
        for (const op of queue) {
            if (!navigator.onLine) {
                setStatus("offline");
                break;
            }

            try {
                await executeOp(_rawApi, op);
                await offlineStore.removeFromQueue(op.id);
                successCount++;
                await refreshCount();
            } catch (e) {
                console.error(`[SyncQueue] Failed to sync op ${op.type}:`, e);

                // If it's a 4xx client error (not 401), the data is bad — discard it
                if (e.status && e.status >= 400 && e.status < 500 && e.status !== 401) {
                    console.warn(`[SyncQueue] Discarding op ${op.id} due to ${e.status}`);
                    await offlineStore.removeFromQueue(op.id);
                    await refreshCount();
                    continue;
                }

                // Network or 5xx error — stop and retry later
                setStatus("error", e.message || "Sync failed");
                _flushing = false;
                return;
            }
        }

        await refreshCount();
        setStatus(_pendingCount > 0 ? "error" : "idle");
        if (successCount > 0) {
            console.log(`[SyncQueue] Synced ${successCount} operations`);
        }
    } catch (e) {
        console.error("[SyncQueue] Flush error:", e);
        setStatus("error", e.message);
    } finally {
        _flushing = false;
    }
}

async function executeOp(rawApi, op) {
    switch (op.type) {
        case "responses.bulkSave":
            return rawApi.responses.bulkSave(
                    op.assessmentId, op.sectionCode, op.responses, op.explanations
                    );

        case "assessments.progress":
            return rawApi.assessments.updateSectionProgress(
                    op.assessmentId, op.sectionCode, op.done
                    );

        case "assessments.submit":
            return rawApi.assessments.submit(op.assessmentId);

        case "humanResources.save":
            return rawApi.humanResources.save(op.assessmentId, op.responses);

        case "healthProducts.save":
            return rawApi.healthProducts.save(
                    op.assessmentId, op.responses, op.departmentId ?? null
                    );

        case "assessments.create": {
            const migrateId = async (fromId, toId) => {
                await offlineStore.copyAssessmentData(fromId, toId);
                await offlineStore.deleteAssessment(fromId);
                window.dispatchEvent(new CustomEvent("assessment:id-resolved", {
                    detail: { tempId: fromId, realId: toId },
                }));
            };

            // Inner try/catch: 409 must be handled here, not by flush()'s 4xx discard handler,
            // because we need to run ID migration before the op is dequeued.
            try {
                const response = await rawApi.assessments.create(
                    op.facility_id, op.assessment_type, op.assessment_date
                );
                const realId = response?.assessment?.id;
                if (!realId) {
                    throw new Error("[SyncQueue] assessments.create: server returned no assessment.id");
                }
                await migrateId(op.tempId, realId);
                return response;
            } catch (e) {
                if (e.status === 409) {
                    const realId = e.data?.assessment?.id;
                    if (realId) {
                        await migrateId(op.tempId, realId);
                    } else {
                        console.warn("[SyncQueue] assessments.create: 409 with no realId — deleting orphan", op.tempId);
                        await offlineStore.deleteAssessment(op.tempId);
                    }
                    // Treat as success — op should be dequeued
                    return null;
                }
                throw e;
            }
        }

        case "report.email": {
            try {
                const data = await rawApi.reports.emailReport(op.assessmentId, op.emails);
                // Swap the local placeholder with the real server job
                if (op.localJobId) {
                    await offlineStore.deleteEmailJob(op.localJobId);
                }
                if (data?.id) {
                    await offlineStore.saveEmailJob({
                        ...data,
                        assessment_id: op.assessmentId,
                        emails: op.emails,
                    });
                }
                window.dispatchEvent(new CustomEvent("emailJob:synced", {
                    detail: { localJobId: op.localJobId, serverJob: data },
                }));
                return data;
            } catch (e) {
                // 4xx means a permanent error — update local job status and remove from queue
                if (e.status >= 400 && e.status < 500) {
                    if (op.localJobId) {
                        const existing = await offlineStore.getEmailJob(op.localJobId);
                        if (existing) {
                            await offlineStore.saveEmailJob({
                                ...existing,
                                status: "failed",
                                error: e.message ?? "Request rejected by server",
                            });
                        }
                    }
                    return null; // dequeue
                }
                throw e;
            }
        }

        default:
            console.warn(`[SyncQueue] Unknown op type: ${op.type}`);
            return null;
    }
}

// ── Network listeners ────────────────────────────────────────────────────────
function handleOnline() {
    console.log("[SyncQueue] Online — flushing queue");
    _lastError = null;
    flush();
}

function handleOffline() {
    console.log("[SyncQueue] Offline");
    setStatus("offline");
}

// ── Periodic connectivity check ──────────────────────────────────────────────
// Every 15s: if online and there are pending ops, flush automatically.
// Also acts as a safety net if the "online" event was missed (e.g. Capacitor).
const PERIODIC_MS = 15_000;
let _periodicTimer = null;

function startPeriodicCheck() {
    if (_periodicTimer) return;
    _periodicTimer = setInterval(async () => {
        if (!navigator.onLine) {
            if (_status !== "offline") setStatus("offline");
            return;
        }
        // Came back online (status might still be "offline" from last check)
        if (_status === "offline") {
            _lastError = null;
            setStatus("idle");
        }
        const count = await offlineStore.getQueueCount();
        if (count > 0 && !_flushing) {
            console.log(`[SyncQueue] Periodic check: ${count} pending ops — flushing`);
            flush();
        }
    }, PERIODIC_MS);
}

// ── Initialize ───────────────────────────────────────────────────────────────
function init() {
    window.addEventListener("online", handleOnline);
    window.addEventListener("offline", handleOffline);

    // Also listen for Capacitor app resume (if available)
    document.addEventListener("resume", () => {
        if (navigator.onLine) flush();
    });

    // Initial count
    refreshCount();
    if (navigator.onLine && _pendingCount > 0) flush();

    // Start periodic check
    startPeriodicCheck();
}

// Auto-initialize
init();

// ── Public API ───────────────────────────────────────────────────────────────
const syncQueue = {
    enqueue,
    flush,        // manual or automatic flush
    refreshCount,

    /** Subscribe to status changes. Returns unsubscribe function. */
    subscribe: (fn) => {
        _listeners.add(fn);
        // Immediately send current state
        fn({status: _status, pendingCount: _pendingCount, lastError: _lastError});
        return () => _listeners.delete(fn);
    },

    getStatus: () => ({status: _status, pendingCount: _pendingCount, lastError: _lastError}),

    /** Force clear queue (dangerous — discards unsent data) */
    clearAll: async () => {
        await offlineStore.clearQueue();
        await refreshCount();
        setStatus(navigator.onLine ? "idle" : "offline");
    },
};

export default syncQueue;