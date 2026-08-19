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
import { Network } from "@capacitor/network";
import { App as CapacitorApp } from "@capacitor/app";
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
//   "mentorships.create"        → { tempId, payload }
//   "mentorships.update"        → { id, payload }
//   "mentorships.submit"        → { id }
//   "mentorships.addMentee"     → { classId, userId }
//   "mentorships.removeMentee"  → { classId, participantId }
//   "mentorships.updateSession" → { sessionId, payload }
//   "mentorships.startClass"    → { classId }
//   "mentorships.createClass"    → { trainingId, payload, tempId }
//   "mentorships.updateClass"    → { trainingId, classId, payload }
//   "mentorships.deleteClass"    → { trainingId, classId }
//   "mentorships.endClass"       → { classId }
//   "mentorships.createMentee"   → { classId, payload, tempId }
//   "mentorships.updateMenteeEmail" → { classId, participantId, data }
//   "mentorships.regenerateToken"→ { classId }
//   "mentorships.addModule"      → { classId, programModuleId, tempId }
//   "mentorships.removeModule"   → { moduleId, classId }
//   "mentorships.addSession"     → { moduleId, templateId, tempId }
//   "mentorships.removeSession"  → { sessionId, moduleId }
//   "trainings.enroll"          → { trainingId }
//   "trainings.attendance"      → { trainingId }

async function enqueue(op) {
    await offlineStore.addToQueue(op);
    await refreshCount();
    console.log(`[SyncQueue] Enqueued: ${op.type} (${_pendingCount} pending)`);

    // If online, try to flush immediately
    if (navigator.onLine) {
        flush();
    }
}

// Guard: reject any op whose DEPENDENCY fields still carry an unresolved local_* ID.
// `tempId` is intentionally excluded — it is the provisional ID of the record being
// created by this very op, not a dependency on another op's output.
// Throws with status 400 so flush() discards the op rather than sending bad data.
function assertNoTempIds(op) {
    const DEP_FIELDS = ['assessmentId', 'classId', 'moduleId', 'sessionId', 'participantId', 'trainingId', 'id'];
    const TEMP_PATTERN = /^local_[a-z]+_\d+$/;
    for (const field of DEP_FIELDS) {
        const val = op[field];
        if (typeof val === 'string' && TEMP_PATTERN.test(val)) {
            console.error(`[SyncQueue] Op ${op.type} has unresolved temp ID in field "${field}": ${val}`);
            throw Object.assign(new Error('Unresolved temp ID'), { status: 400, _tempIdGuard: true });
        }
    }
}

const OP_LABELS = {
    'mentorships.createClass':       'New class',
    'mentorships.createMentee':      'New mentee',
    'mentorships.addModule':         'New module',
    'mentorships.addSession':        'New session',
    'mentorships.updateClass':       'Class edit',
    'mentorships.updateMenteeEmail': 'Mentee update',
    'mentorships.endClass':          'Class completion',
    'mentorships.regenerateToken':   'Link regeneration',
    'assessments.delete':            'Assessment deletion',
    'profile.update':                'Profile update',
    'mentorships.create':            'New mentorship',
    'mentorships.update':            'Mentorship edit',
    'mentorships.submit':            'Mentorship submit',
    'mentorships.addMentee':         'Mentee enrollment',
    'mentorships.removeMentee':      'Mentee removal',
    'mentorships.updateSession':     'Session update',
    'mentorships.startClass':        'Class start',
    'mentorships.deleteClass':       'Class deletion',
    'mentorships.removeModule':      'Module removal',
    'mentorships.removeSession':     'Session removal',
    'responses.bulkSave':            'Assessment responses',
    'assessments.create':            'New assessment',
    'assessments.submit':            'Assessment submission',
    'assessments.progress':          'Section progress',
    'humanResources.save':           'HR section',
    'healthProducts.save':           'HP section',
    'report.email':                  'Report email',
    'trainings.enroll':              'Training enrollment',
    'trainings.attendance':          'Training attendance',
};

async function getPendingSummary() {
    const queue = await offlineStore.getQueue();
    const groups = {};
    for (const op of queue) {
        const label = OP_LABELS[op.type] ?? op.type;
        groups[label] = (groups[label] ?? 0) + 1;
    }
    return { total: queue.length, groups };
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

        let successCount = 0;

        // Re-read from IndexedDB on each iteration so that patchQueueIds() changes
        // made by create-ops (temp→real ID migration) are visible to dependent ops.
        while (true) {
            if (!navigator.onLine) {
                setStatus("offline");
                break;
            }

            const pending = await offlineStore.getQueue();
            if (pending.length === 0) break;
            pending.sort((a, b) => a.timestamp - b.timestamp);
            const op = pending[0];

            try {
                await executeOp(_rawApi, op);
                await offlineStore.removeFromQueue(op.id);
                successCount++;
                await refreshCount();
            } catch (e) {
                console.error(`[SyncQueue] Failed to sync op ${op.type}:`, e);

                // If it's a 4xx client error (not 401), the data is bad — discard it
                if (e.status && e.status >= 400 && e.status < 500 && e.status !== 401) {
                    await offlineStore.saveConflict({
                        id: 'conflict_' + Date.now() + '_' + Math.random().toString(36).slice(2, 6),
                        op_type: op.type,
                        payload: op,
                        error: e._tempIdGuard ? 'Discarded: contained unresolved temp ID' : (e.message ?? `HTTP ${e.status}`),
                        created_at: new Date().toISOString(),
                        resolved: false,
                    });
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
    assertNoTempIds(op);
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

        case "assessments.delete": {
            try {
                return await rawApi.assessments.delete(op.id);
            } catch (e) {
                if (e.status === 404) return null; // already deleted
                throw e;
            }
        }

        case "profile.update":
            return rawApi.profile.update(op.data);

        case "assessments.create": {
            const migrateId = async (fromId, toId) => {
                await offlineStore.patchQueueIds(fromId, toId);
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

        case 'mentorship.module.start':
            return rawApi.modules.start(op.moduleId);

        case 'mentorship.module.complete':
            return rawApi.modules.complete(op.moduleId);

        case 'mentorship.attendance.mark':
            return rawApi.attendance.mark(op.moduleId, op.participantId, op.status);

        case 'mentee.attendance.confirm':
            return rawApi.me.attend(op.classId, op.moduleId);

        case 'mentorships.create': {
            const migrateId = async (fromId, toId, fromClassId, toClassId) => {
                await offlineStore.patchQueueIds(fromId, toId);
                // Also migrate the provisional class ID so any queued mentee/module ops
                // that reference tempClassId get updated to the real server class ID.
                if (fromClassId && toClassId) {
                    await offlineStore.patchQueueIds(fromClassId, toClassId);
                }
                const existing = await offlineStore.getMentorship(fromId);
                if (existing) {
                    await offlineStore.saveMentorship({ ...existing, id: toId, _isOffline: false });
                    await offlineStore.deleteMentorship(fromId);
                }
                window.dispatchEvent(new CustomEvent('mentorship:id-resolved', {
                    detail: { tempId: fromId, realId: toId },
                }));
            };
            try {
                const response = await rawApi.mentorshipCreate.create(op.payload);
                const realId = response?.data?.id;
                const realClassId = response?.data?.class?.id;
                if (!realId) throw new Error('[SyncQueue] mentorships.create: server returned no id');
                await migrateId(op.tempId, realId, op.tempClassId, realClassId);
                return response;
            } catch (e) {
                if (e.status === 409) {
                    const realId = e.data?.data?.id;
                    const realClassId = e.data?.data?.class?.id;
                    if (realId) await migrateId(op.tempId, realId, op.tempClassId, realClassId);
                    return null;
                }
                if (e.status >= 400 && e.status < 500) {
                    await offlineStore.saveConflict({
                        id: 'conflict_' + Date.now() + '_' + Math.random().toString(36).slice(2, 6),
                        op_type: op.type,
                        payload: op.payload,
                        error: e.message ?? 'Request rejected',
                        created_at: new Date().toISOString(),
                        resolved: false,
                    });
                    return null;
                }
                throw e;
            }
        }

        case 'mentorships.update':
            return rawApi.mentorshipCreate.update(op.id, op.payload);

        case 'mentorships.submit':
            return rawApi.mentorshipCreate.submit(op.id);

        case 'mentorships.addMentee':
            return rawApi.classLifecycle.enrollMentee(op.classId, op.userId);

        case 'mentorships.removeMentee': {
            try {
                return await rawApi.classLifecycle.removeMentee(op.classId, op.participantId);
            } catch (e) {
                if (e.status === 404) return null; // already removed — discard
                throw e;
            }
        }

        case 'mentorships.updateSession': {
            // NEVER discard silently — write to conflicts on permanent failure
            try {
                return await rawApi.sessions.update(op.sessionId, op.payload);
            } catch (e) {
                if (e.status >= 400 && e.status < 500) {
                    await offlineStore.saveConflict({
                        id: 'conflict_' + Date.now(),
                        op_type: 'mentorships.updateSession',
                        payload: { sessionId: op.sessionId, ...op.payload },
                        error: e.message ?? 'Session sync failed',
                        created_at: new Date().toISOString(),
                        resolved: false,
                    });
                    return null; // dequeue — handled via conflicts UI
                }
                throw e;
            }
        }

        case 'mentorships.startClass':
            return rawApi.classLifecycle.start(op.classId);

        case 'mentorships.createClass': {
            const migrateClassId = async (fromId, toId, trainingId) => {
                await offlineStore.patchQueueIds(fromId, toId);
                const list = (await offlineStore.getMentorshipClasses(trainingId)) ?? [];
                const idx = list.findIndex(c => c.id === fromId);
                if (idx !== -1) {
                    list[idx] = { ...list[idx], id: toId, _isOffline: false };
                    await offlineStore.saveMentorshipClasses(trainingId, list);
                }
                window.dispatchEvent(new CustomEvent('mentorship:classId-resolved', {
                    detail: { tempId: fromId, realId: toId },
                }));
            };
            try {
                const response = await rawApi.mentorships.createClass(op.trainingId, op.payload);
                const realId = response?.data?.id;
                if (!realId) throw new Error('[SyncQueue] mentorships.createClass: server returned no id');
                await migrateClassId(op.tempId, realId, op.trainingId);
                return response;
            } catch (e) {
                if (e.status === 409) {
                    const realId = e.data?.data?.id;
                    if (realId) await migrateClassId(op.tempId, realId, op.trainingId);
                    return null;
                }
                if (e.status >= 400 && e.status < 500) {
                    await offlineStore.saveConflict({
                        id: 'conflict_' + Date.now() + '_' + Math.random().toString(36).slice(2, 6),
                        op_type: op.type,
                        payload: op,
                        error: e.message ?? `HTTP ${e.status}`,
                        created_at: new Date().toISOString(),
                        resolved: false,
                    });
                    // Remove the provisional class from local cache
                    const list = (await offlineStore.getMentorshipClasses(op.trainingId)) ?? [];
                    await offlineStore.saveMentorshipClasses(op.trainingId, list.filter(c => c.id !== op.tempId));
                    return null; // dequeue
                }
                throw e;
            }
        }

        case 'mentorships.updateClass':
            return rawApi.mentorships.updateClass(op.trainingId, op.classId, op.payload);

        case 'mentorships.deleteClass': {
            try {
                return await rawApi.mentorships.deleteClass(op.trainingId, op.classId);
            } catch (e) {
                if (e.status === 404) return null; // already deleted
                throw e;
            }
        }

        case 'mentorships.endClass':
            return rawApi.classLifecycle.end(op.classId);

        case 'mentorships.createMentee': {
            const migrateMenteeId = async (fromId, toId, classId) => {
                await offlineStore.patchQueueIds(fromId, toId);
                const list = (await offlineStore.getParticipants(classId)) ?? [];
                const idx = list.findIndex(p => p.id === fromId);
                if (idx !== -1) {
                    list[idx] = { ...list[idx], id: toId, _isOffline: false };
                    await offlineStore.saveParticipants(classId, list);
                }
                window.dispatchEvent(new CustomEvent('mentorship:participantId-resolved', {
                    detail: { tempId: fromId, realId: toId, classId },
                }));
            };
            try {
                const response = await rawApi.classLifecycle.createMentee(op.classId, op.payload);
                const realId = response?.data?.participant_id;
                if (!realId) throw new Error('[SyncQueue] mentorships.createMentee: server returned no participant_id');
                await migrateMenteeId(op.tempId, realId, op.classId);
                return response;
            } catch (e) {
                if (e.status === 409) {
                    const realId = e.data?.data?.participant_id;
                    if (realId) await migrateMenteeId(op.tempId, realId, op.classId);
                    return null;
                }
                if (e.status >= 400 && e.status < 500) {
                    await offlineStore.saveConflict({
                        id: 'conflict_' + Date.now() + '_' + Math.random().toString(36).slice(2, 6),
                        op_type: op.type,
                        payload: op,
                        error: e.message ?? `HTTP ${e.status}`,
                        created_at: new Date().toISOString(),
                        resolved: false,
                    });
                    const list = (await offlineStore.getParticipants(op.classId)) ?? [];
                    await offlineStore.saveParticipants(op.classId, list.filter(p => p.id !== op.tempId));
                    return null;
                }
                throw e;
            }
        }

        case 'mentorships.updateMenteeEmail':
            return rawApi.classLifecycle.updateMentee(op.classId, op.participantId, op.data);

        case 'mentorships.regenerateToken': {
            const response = await rawApi.classLifecycle.regenerateToken(op.classId);
            if (response?.data) {
                await offlineStore.saveEnrollmentLink(op.classId, response.data);
            }
            return response;
        }

        case 'mentorships.addModule': {
            const migrateModuleId = async (fromId, toId, classId) => {
                await offlineStore.patchQueueIds(fromId, toId);
                const list = (await offlineStore.getModuleList(classId)) ?? [];
                const idx = list.findIndex(m => m.id === fromId);
                if (idx !== -1) {
                    list[idx] = { ...list[idx], id: toId, _isOffline: false };
                    await offlineStore.saveModuleList(classId, list);
                }
                window.dispatchEvent(new CustomEvent('mentorship:moduleId-resolved', {
                    detail: { tempId: fromId, realId: toId, classId },
                }));
            };
            try {
                const response = await rawApi.classLifecycle.addModule(op.classId, op.programModuleId);
                const realId = response?.data?.id;
                if (!realId) throw new Error('[SyncQueue] mentorships.addModule: server returned no id');
                await migrateModuleId(op.tempId, realId, op.classId);
                return response;
            } catch (e) {
                if (e.status === 409) {
                    const realId = e.data?.data?.id;
                    if (realId) await migrateModuleId(op.tempId, realId, op.classId);
                    return null;
                }
                if (e.status >= 400 && e.status < 500) {
                    await offlineStore.saveConflict({
                        id: 'conflict_' + Date.now() + '_' + Math.random().toString(36).slice(2, 6),
                        op_type: op.type,
                        payload: op,
                        error: e.message ?? `HTTP ${e.status}`,
                        created_at: new Date().toISOString(),
                        resolved: false,
                    });
                    const list = (await offlineStore.getModuleList(op.classId)) ?? [];
                    await offlineStore.saveModuleList(op.classId, list.filter(m => m.id !== op.tempId));
                    return null;
                }
                throw e;
            }
        }

        case 'mentorships.removeModule': {
            try {
                return await rawApi.modules.remove(op.moduleId);
            } catch (e) {
                if (e.status === 404) return null; // already removed
                throw e;
            }
        }

        case 'mentorships.addSession': {
            const migrateSessionId = async (fromId, toId, moduleId, realSession) => {
                await offlineStore.patchQueueIds(fromId, toId);
                const list = (await offlineStore.getSessionsByModule(moduleId)) ?? [];
                const idx = list.findIndex(s => s.id === fromId);
                if (idx !== -1) {
                    list[idx] = { ...realSession, _isOffline: false };
                    await offlineStore.saveSessionsByModule(moduleId, list);
                }
                window.dispatchEvent(new CustomEvent('mentorship:sessionId-resolved', {
                    detail: { tempId: fromId, realId: toId, moduleId },
                }));
            };
            try {
                const response = await rawApi.modules.addSession(op.moduleId, op.templateId);
                const realSession = response?.data;
                const realId = realSession?.id;
                if (!realId) throw new Error('[SyncQueue] mentorships.addSession: server returned no id');
                await migrateSessionId(op.tempId, realId, op.moduleId, realSession);
                return response;
            } catch (e) {
                if (e.status === 409) {
                    const realId = e.data?.data?.id;
                    if (realId && op.tempId) {
                        const realSession = e.data?.data ?? { id: realId };
                        await migrateSessionId(op.tempId, realId, op.moduleId, realSession);
                    }
                    return null;
                }
                if (e.status >= 400 && e.status < 500) {
                    await offlineStore.saveConflict({
                        id: 'conflict_' + Date.now() + '_' + Math.random().toString(36).slice(2, 6),
                        op_type: op.type,
                        payload: op,
                        error: e.message ?? `HTTP ${e.status}`,
                        created_at: new Date().toISOString(),
                        resolved: false,
                    });
                    // Remove the provisional session from local cache
                    const list = (await offlineStore.getSessionsByModule(op.moduleId)) ?? [];
                    await offlineStore.saveSessionsByModule(op.moduleId, list.filter(s => s.id !== op.tempId));
                    return null; // dequeue
                }
                throw e;
            }
        }

        case 'mentorships.removeSession': {
            try {
                return await rawApi.sessions.remove(op.sessionId);
            } catch (e) {
                if (e.status === 404) return null; // already removed
                throw e;
            }
        }

        case 'trainings.enroll': {
            try {
                return await rawApi.trainingActions.enroll(op.trainingId);
            } catch (e) {
                if (e.status === 409) return null; // already enrolled
                throw e;
            }
        }

        case 'trainings.attendance':
            return rawApi.trainingActions.attendance(op.trainingId);

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
    // Native connectivity events — real OS-level changes on native, falls
    // back to navigator.onLine/online/offline transparently in a browser.
    Network.addListener("networkStatusChange", (status) => {
        if (status.connected) handleOnline();
        else handleOffline();
    });

    // App resume via the Capacitor App plugin, replacing the legacy
    // Cordova-style document "resume" event for a more reliable trigger.
    CapacitorApp.addListener("resume", async () => {
        const status = await Network.getStatus();
        if (status.connected) flush();
    });

    // Initial count
    refreshCount();
    Network.getStatus().then(status => {
        if (status.connected && _pendingCount > 0) flush();
    });

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
    getPendingSummary,

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

