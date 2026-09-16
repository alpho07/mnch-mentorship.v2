/**
 * MNCH Assessment API Service — Offline-Aware
 * src/services/api.service.js
 *
 * Wraps every API call with offline fallback:
 *   - GET requests: try network first, cache result in IndexedDB; on failure, return cached
 *   - POST/PUT writes: try network first; on failure, queue in sync queue + save locally
 *
 * The raw (unwrapped) API is also exported as `_rawApi` so the sync queue
 * can replay operations without going through the offline layer again.
 */

import offlineStore from "./offline-store.js";
import syncQueue from "./sync-queue.js";

const BASE_URL = import.meta.env.VITE_API_BASE_URL ?? 'https://mnchkenyamentorship.org/api/v1';

const TokenStore = {
    get: () => {
        try {
            return localStorage.getItem('mnch_token');
        } catch {
            return null;
        }
    },
    set: (t) => {
        try {
            localStorage.setItem('mnch_token', t);
        } catch { /* localStorage unavailable */ }
    },
    clear: () => {
        try {
            localStorage.removeItem('mnch_token');
        } catch { /* localStorage unavailable */ }
    },
};

// ── Raw fetch (no offline logic) ─────────────────────────────────────────────
async function request(method, path, body = null) {
    const token = TokenStore.get();
    const res = await fetch(BASE_URL + path, {
        method,
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            ...(token ? {Authorization: 'Bearer ' + token} : {}),
        },
        ...(body !== null ? {body: JSON.stringify(body)} : {}),
    });

    if (res.status === 204)
        return null;

    let data;
    try {
        data = await res.json();
    } catch {
        data = {};
    }

    if (!res.ok) {
        const err = new Error(data.message || `HTTP ${res.status}`);
        err.status = res.status;
        err.data = data;                // ← add this line
        err.errors = data.errors ?? {};
        throw err;
    }
    return data;
}

const get = (path, params) => {
    const qs = params && Object.keys(params).length > 0
            ? '?' + new URLSearchParams(params).toString() : '';
    return request('GET', path + qs);
};
const post  = (path, body) => request('POST',  path, body);
const put   = (path, body) => request('PUT',   path, body);
const patch = (path, body) => request('PATCH', path, body);
const del   = (path)       => request('DELETE', path);

// ── Helper: is a fetch error a network error? ───────────────────────────────
function isNetworkError(e) {
    return (
            e instanceof TypeError || // fetch throws TypeError on network fail
            e.message?.includes('Failed to fetch') ||
            e.message?.includes('Network request failed') ||
            e.message?.includes('NetworkError') ||
            !navigator.onLine
            );
}

// ── Merge-by-id utility (used by smartSync) ──────────────────────────────────
// Merges a delta (partial server response) into an existing cached array.
// Records with is_trashed=true are removed; otherwise the server version wins
// when its updated_at is >= the cached version.
function mergeById(existing, delta) {
    const map = Object.fromEntries((existing ?? []).map(r => [r.id, r]));
    for (const record of delta) {
        if (record.is_trashed) {
            delete map[record.id];
        } else {
            const cached = map[record.id];
            if (!cached || (record.updated_at ?? '') >= (cached.updated_at ?? '')) {
                map[record.id] = record;
            }
        }
    }
    return Object.values(map);
}

// ── Incremental sync helpers (used by smartSync, not exported on api object) ─
// syncAssessments: fetches delta from server and upserts/deletes in IndexedDB.
// The delta contains minimal records (id + updated_at + is_trashed).
async function syncAssessments(since) {
    const url = since
        ? `/assessments?since=${encodeURIComponent(since)}`
        : '/assessments';
    const data = await get(url);
    const delta = Array.isArray(data?.data) ? data.data : [];
    if (delta.length === 0) return { count: 0 };

    for (const record of delta) {
        if (record.is_trashed) {
            await offlineStore.deleteAssessment(record.id);
        } else {
            const cached = await offlineStore.getAssessment(record.id);
            if (!cached || (record.updated_at ?? '') >= (cached.updated_at ?? '')) {
                await offlineStore.saveAssessment(record);
            }
        }
    }

    return { count: delta.length };
}

// syncTrainings: fetches delta from server and merges into the cached trainings list.
async function syncTrainings(since) {
    const url = since
        ? `/trainings?since=${encodeURIComponent(since)}`
        : '/trainings';
    const data = await get(url);
    const delta = Array.isArray(data?.data) ? data.data : [];
    if (delta.length === 0) return { count: 0 };

    const existing = await offlineStore.getTrainings();
    const merged = mergeById(existing, delta);
    await offlineStore.saveTrainings(merged);

    return { count: delta.length };
}

// syncMentorships: fetches delta from server, merges list, re-prefetches details for updated records.
async function syncMentorships(since) {
    const url = since
        ? `/mentorships?since=${encodeURIComponent(since)}`
        : '/mentorships';
    const data = await get(url);
    const delta = Array.isArray(data?.data) ? data.data : [];
    if (delta.length === 0) return { count: 0 };

    const existing = await offlineStore.getMentorships();
    const merged = mergeById(existing, delta);
    await offlineStore.saveMentorships(merged);

    // Re-prefetch detail for updated (non-trashed) mentorships
    const updated = delta.filter(r => !r.is_trashed);
    for (const m of updated) {
        try {
            const detail = await get(`/mentorships/${m.id}`);
            if (detail?.data) await offlineStore.saveMentorship(detail.data);
            const classes = await get(`/mentorships/${m.id}/classes`);
            const classArr = Array.isArray(classes?.data) ? classes.data : [];
            if (classArr.length > 0) await offlineStore.saveMentorshipClasses(m.id, classArr);
        } catch {
            // Non-fatal — skip on any error
        }
    }

    return { count: delta.length };
}

// syncUserLookupIndex: fetches delta of user lookup entries and merges into the cached map.
async function syncUserLookupIndex(since) {
    const url = since
        ? `/users/lookup-index?since=${encodeURIComponent(since)}`
        : '/users/lookup-index';
    const data = await get(url);
    const users = Array.isArray(data?.data) ? data.data : [];
    if (users.length === 0) return { count: 0 };

    const updates = {};
    for (const u of users) {
        const key = (u.email ?? '').toLowerCase().trim();
        if (!key) continue;
        const inactive = u.is_active === false || u.status === 'inactive' || u.status === 'suspended';
        updates[key] = inactive
            ? null  // signals removal in mergeUserLookupMap
            : { id: u.id, name: u.name, phone: u.phone ?? null };
    }
    await offlineStore.mergeUserLookupMap(updates);

    return { count: users.length };
}

// smartSync: orchestrates all incremental sync operations using a shared `since` timestamp.
async function smartSync() {
    if (!navigator.onLine) return;

    const since = await offlineStore.getSyncedAt('global');
    const nowTs = new Date().toISOString();

    const [assessments, mentorships, trainings, users] = await Promise.allSettled([
        syncAssessments(since),
        syncMentorships(since),
        syncTrainings(since),
        syncUserLookupIndex(since),
    ]);

    await offlineStore.setSyncedAt('global', nowTs);

    const summary = {
        assessments: assessments.status === 'fulfilled' ? assessments.value.count : 0,
        mentorships: mentorships.status === 'fulfilled' ? mentorships.value.count : 0,
        trainings:   trainings.status === 'fulfilled'   ? trainings.value.count   : 0,
        users:       users.status === 'fulfilled'       ? users.value.count       : 0,
    };

    const hasChanges = Object.values(summary).some(n => n > 0);
    if (hasChanges) {
        window.dispatchEvent(new CustomEvent('app:sync-complete', { detail: summary }));
    }
}

function normalizeApiList(data) {
    if (Array.isArray(data?.data)) return data.data;
    if (Array.isArray(data?.data?.data)) return data.data.data;
    if (Array.isArray(data)) return data;
    return [];
}

function normalizeFacilityOption(facility) {
    const mfl = facility?.mfl_code ? String(facility.mfl_code).trim() : "";
    const name = facility?.name ? String(facility.name).trim() : "";
    return {
        ...facility,
        id: facility?.id,
        name,
        mfl_code: mfl,
        label: facility?.label || (mfl ? `${mfl} - ${name}` : name),
    };
}

// ── Raw API (exported for sync-queue to use directly) ────────────────────────
export const _rawApi = {
    auth: {
        login: (email, password, deviceName) =>
            post('/auth/login', {email, password, device_name: deviceName || 'mobile-app'}),
        logout: () => post('/auth/logout').finally(() => TokenStore.clear()),
        logoutAll: () => post('/auth/logout-all').finally(() => TokenStore.clear()),
        me: () => get('/auth/me'),
        refresh: () => post('/auth/refresh').then(d => {
                if (d?.token)
                    TokenStore.set(d.token);
                return d;
            }),
        forgotPassword: (email) => post('/auth/forgot-password', {email}),
        resetPassword: (token, email, password, password_confirmation) =>
            post('/auth/reset-password', {token, email, password, password_confirmation}),
    },
    profile: {
        get: () => get('/profile'),
        update: (data) => put('/profile', data),
        changePassword: (data) => put('/profile/password', data),
        stats: () => get('/profile/stats'),
    },
    facilities: {
        list: (params) => get('/facilities', params),
        byCounty: (countyId) => get('/facilities', { county_id: countyId, per_page: 1000 }),
        find: (id) => get('/facilities/' + id),
    },
    sections: {
        list: () => get('/sections'),
        find: (id) => get('/sections/' + id),
        fullSchema: () => get('/sections/schema/full'),
        clearSchemaCache: () => {
        },
    },
    assessments: {
        list: (params) => get('/assessments', params),
        find: (id) => get('/assessments/' + id),
        update: (id, data) => put('/assessments/' + id, data),
        delete: (id) => del('/assessments/' + id),
        submit: (id) => post('/assessments/' + id + '/submit'),
        updateSectionProgress: (assessmentId, sectionCode, done) =>
            put('/assessments/' + assessmentId + '/sections/' + sectionCode + '/progress', {done}),
        create: (facility_id, assessment_type, assessment_date) =>
            post('/assessments', {facility_id, assessment_type, assessment_date}),
    },
    humanResources: {
        get: (assessmentId) => get('/assessments/' + assessmentId + '/human-resources'),
        save: (assessmentId, responses) =>
            post('/assessments/' + assessmentId + '/human-resources', {responses}),
    },
    healthProducts: {
        get: (assessmentId) => get('/assessments/' + assessmentId + '/health-products'),
        save: (assessmentId, responses, departmentId = null) =>
            post('/assessments/' + assessmentId + '/health-products', {
                responses,
                ...(departmentId !== null ? {department_id: departmentId} : {}),
            }),
    },
    responses: {
        list: (assessmentId) => get('/assessments/' + assessmentId + '/responses'),
        bulkSave: (assessmentId, sectionCode, responses, explanations) =>
            post('/assessments/' + assessmentId + '/responses', {
                section_code: sectionCode,
                responses: responses ?? {},
                explanations: explanations ?? {},
            }),
    },
    reports: {
        show: (assessmentId) => get('/assessments/' + assessmentId + '/report'),
        full: (assessmentId) => get('/assessments/' + assessmentId + '/report'),
        summary: (assessmentId) => get('/assessments/' + assessmentId + '/report/summary'),
        downloadPdf: (assessmentId) => get('/assessments/' + assessmentId + '/report/pdf'),
        emailReport: (assessmentId, emails) => post('/assessments/' + assessmentId + '/report/email', { emails }),
        emailJobStatus: (assessmentId, jobId) => get('/assessments/' + assessmentId + '/report/email/' + jobId),
        emailJobs: () => get('/reports/email-jobs'),
        dashboard: () => get('/reports/dashboard'),
        sectionAverages: () => get('/reports/section-averages'),
    },
    mentorships: {
        list: () => get('/mentorships'),
        find: (id) => get('/mentorships/' + id),
        classes: (id) => get('/mentorships/' + id + '/classes'),
        classDetail: (id, classId) => get('/mentorships/' + id + '/classes/' + classId),
        createClass: (trainingId, payload) => post('/mentorships/' + trainingId + '/classes', payload),
        updateClass: (trainingId, classId, payload) => put('/mentorships/' + trainingId + '/classes/' + classId, payload),
        deleteClass: (trainingId, classId) => del('/mentorships/' + trainingId + '/classes/' + classId),
    },
    modules: {
        list: (classId) => get('/classes/' + classId + '/modules'),
        add: (classId, programModuleId) => post('/classes/' + classId + '/modules', { program_module_id: programModuleId }),
        remove: (moduleId) => del('/modules/' + moduleId),
        start: (moduleId) => post('/modules/' + moduleId + '/start'),
        complete: (moduleId) => post('/modules/' + moduleId + '/complete'),
        sessions: (moduleId) => get('/modules/' + moduleId + '/sessions'),
        sessionTemplates: (moduleId) => get('/modules/' + moduleId + '/sessions/available-templates'),
        addSession: (moduleId, moduleSessionId) => post('/modules/' + moduleId + '/sessions', { module_session_id: moduleSessionId }),
    },
    attendance: {
        roster: (moduleId) => get('/modules/' + moduleId + '/attendance'),
        mark: (moduleId, participantId, status) =>
            post('/modules/' + moduleId + '/attendance/' + participantId, { status }),
        bulk: (moduleId, attendances) =>
            post('/modules/' + moduleId + '/attendance/bulk', { attendances }),
    },
    me: {
        classes: () => get('/me/classes'),
        classDetail: (classId) => get('/me/classes/' + classId),
        attend: (classId, moduleId) =>
            post('/me/classes/' + classId + '/modules/' + moduleId + '/attend'),
    },
    trainings: {
        list: () => get('/trainings'),
        find: (id) => get('/trainings/' + id),
        participants: (id) => get('/trainings/' + id + '/participants'),
    },
    participants: {
        list: (classId) => get('/classes/' + classId + '/participants'),
        progress: (participantId) => get('/participants/' + participantId + '/progress'),
    },
    lookups: {
        programs: () => get('/programs'),
        programModules: (programId) => get('/programs/' + programId + '/modules'),
        counties: () => get('/counties'),
        cadres: () => get('/cadres'),
        departments: () => get('/departments'),
        facilitiesByCounty: (countyId) => get('/counties/' + countyId + '/facilities'),
        userByEmail: (email) => get('/users/by-email?email=' + encodeURIComponent(email)),
        userSearch: (q, perPage = 20) => get('/users/search?q=' + encodeURIComponent(q) + '&per_page=' + encodeURIComponent(perPage)),
    },
    mentorshipCreate: {
        create: (payload) => post('/mentorships', payload),
        update: (id, payload) => put('/mentorships/' + id, payload),
        submit: (id) => post('/mentorships/' + id + '/submit'),
    },
    classLifecycle: {
        start: (classId) => post('/classes/' + classId + '/start'),
        end: (classId) => post('/classes/' + classId + '/end'),
        report: (classId) => get('/classes/' + classId + '/report'),
        enrollMentee: (classId, userId) => post('/classes/' + classId + '/mentees', { user_id: userId }),
        createMentee: (classId, payload) => post('/classes/' + classId + '/mentees/create', payload),
        removeMentee: (classId, participantId) => del('/classes/' + classId + '/mentees/' + participantId),
        updateMentee: (classId, participantId, data) => patch('/classes/' + classId + '/mentees/' + participantId, data),
        markInvited: (classId, participantId) => post('/classes/' + classId + '/mentees/' + participantId + '/invite'),
        enrollmentLink: (classId) => get('/classes/' + classId + '/enrollment-link'),
        regenerateToken: (classId) => post('/classes/' + classId + '/regenerate-token'),
        addModule: (classId, programModuleId) => post('/classes/' + classId + '/modules', { program_module_id: programModuleId }),
    },
    sessions: {
        update: (sessionId, payload) => put('/sessions/' + sessionId, payload),
        remove: (sessionId) => del('/sessions/' + sessionId),
    },
    trainingActions: {
        enroll: (trainingId) => post('/trainings/' + trainingId + '/enroll'),
        attendance: (trainingId) => post('/trainings/' + trainingId + '/attendance'),
    },
    resources: {
        list: (type) => get('/resources' + (type ? '?type=' + type : '')),
    },
    scopes: {
        getConfig: () => get('/scope-config'),
    },
};

// ─────────────────────────────────────────────────────────────────────────────
// Offline-aware API (default export)
// ─────────────────────────────────────────────────────────────────────────────

const api = {
    setToken: (t) => TokenStore.set(t),
    clearToken: () => TokenStore.clear(),
    getToken: () => TokenStore.get(),

    // ── Auth (no offline cache — login requires network, session restore uses cached user) ──
    auth: {
        login: async (email, password, deviceName) => {
            const data = await _rawApi.auth.login(email, password, deviceName);
            // Cache user for offline session restore
            if (data?.user)
                await offlineStore.saveUser(data.user);
            return data;
        },
        logout: async () => {
            try {
                await _rawApi.auth.logout();
            } catch { /* ignore server-side logout errors */ }
            TokenStore.clear();
            await offlineStore.clearAll();
        },
        logoutAll: _rawApi.auth.logoutAll,
        me: async () => {
            try {
                const data = await _rawApi.auth.me();
                if (data?.user ?? data)
                    await offlineStore.saveUser(data?.user ?? data);
                return data;
            } catch (e) {
                if (isNetworkError(e)) {
                    const cached = await offlineStore.getUser();
                    if (cached) {
                        console.log("[API] Offline — using cached user");
                        return {user: cached};
                    }
                }
                throw e;
            }
        },
        refresh: _rawApi.auth.refresh,
        forgotPassword: _rawApi.auth.forgotPassword,
        resetPassword: _rawApi.auth.resetPassword,
    },

    // ── Profile ──────────────────────────────────────────────────────────────
    profile: {
        get: async () => {
            try {
                const data = await _rawApi.profile.get();
                const u = data?.data ?? data;
                if (u?.id) await offlineStore.saveUser(u);
                return data;
            } catch (e) {
                if (isNetworkError(e)) {
                    const cached = await offlineStore.getUser();
                    if (cached) return { data: cached };
                }
                throw e;
            }
        },
        update: async (data) => {
            try {
                return await _rawApi.profile.update(data);
            } catch (e) {
                if (isNetworkError(e)) {
                    await syncQueue.enqueue({ type: 'profile.update', data });
                    return { queued: true };
                }
                throw e;
            }
        },
        changePassword: (data) => {
            if (!navigator.onLine)
                throw Object.assign(new Error('Password change requires an internet connection.'), { offlineBlocked: true });
            return _rawApi.profile.changePassword(data);
        },
        stats: async () => {
            try {
                const data = await _rawApi.profile.stats();
                await offlineStore.setMeta('profile_stats', data);
                return data;
            } catch (e) {
                if (isNetworkError(e)) {
                    const cached = await offlineStore.getMeta('profile_stats');
                    if (cached) return cached;
                }
                throw e;
            }
        },
    },

    // ── Facilities (cached reads — fetches ALL pages) ─────────────────────────
    facilities: {
        list: async () => {
            try {
                // Fetch first page with a large per_page to minimise round-trips.
                // The API paginates at 50 by default; most deployments have < 10 000
                // active facilities so a single request with per_page=10000 usually
                // returns everything at once.
                const first = await _rawApi.facilities.list({ per_page: 10000, page: 1 });
                const extractArr = (d) =>
                    Array.isArray(d?.data) ? d.data : Array.isArray(d) ? d : [];

                let all = extractArr(first);
                const lastPage = first?.meta?.last_page ?? 1;

                // If the server still has more pages, fetch them in parallel.
                if (lastPage > 1) {
                    const pages = await Promise.all(
                        Array.from({ length: lastPage - 1 }, (_, i) =>
                            _rawApi.facilities.list({ per_page: 10000, page: i + 2 })
                        )
                    );
                    for (const page of pages) all = all.concat(extractArr(page));
                }

                if (all.length > 0) {
                    await offlineStore.saveFacilities(all);
                    console.log(`[API] Cached ${all.length} facilities for offline use`);
                }
                return all;
            } catch (e) {
                if (isNetworkError(e)) {
                    const cached = await offlineStore.getFacilities();
                    if (cached && cached.length > 0) {
                        console.log(`[API] Offline — returning ${cached.length} cached facilities`);
                        return cached;
                    }
                }
                throw e;
            }
        },
        byCounty: _rawApi.facilities.byCounty,
        find: _rawApi.facilities.find,
    },

    // ── Sections / Schema (cached) ───────────────────────────────────────────
    sections: {
        list: _rawApi.sections.list,
        find: _rawApi.sections.find,
        fullSchema: async () => {
            try {
                const data = await _rawApi.sections.fullSchema();
                const arr = Array.isArray(data) ? data : Array.isArray(data?.data) ? data.data : [];
                if (arr.length > 0)
                    await offlineStore.saveSchema(arr);
                return data;
            } catch (e) {
                if (isNetworkError(e)) {
                    const cached = await offlineStore.getSchema();
                    if (cached && cached.length > 0) {
                        console.log("[API] Offline — using cached schema");
                        return cached;
                    }
                }
                throw e;
            }
        },
        clearSchemaCache: () => {
            offlineStore.saveSchema(null);
        },
    },

    // ── Assessments (cached reads, queued writes) ────────────────────────────
    assessments: {
        list: async (params) => {
            try {
                const data = await _rawApi.assessments.list(params);
                const arr = Array.isArray(data) ? data : Array.isArray(data?.data) ? data.data : [];
                if (arr.length > 0)
                    await offlineStore.saveAssessments(arr);
                return data;
            } catch (e) {
                if (isNetworkError(e)) {
                    const cached = await offlineStore.getAssessments();
                    if (cached.length > 0) {
                        console.log(`[API] Offline — returning ${cached.length} cached assessments`);
                        return cached;
                    }
                }
                throw e;
            }
        },
        find: async (id) => {
            try {
                const data = await _rawApi.assessments.find(id);
                const a = data?.data ?? data;
                if (a?.id)
                    await offlineStore.saveAssessment(a);
                return data;
            } catch (e) {
                if (isNetworkError(e)) {
                    const cached = await offlineStore.getAssessment(id);
                    if (cached) {
                        console.log(`[API] Offline — returning cached assessment ${id}`);
                        return cached;
                    }
                }
                throw e;
            }
        },
        update: _rawApi.assessments.update,
        delete: async (id) => {
            try {
                return await _rawApi.assessments.delete(id);
            } catch (e) {
                if (isNetworkError(e)) {
                    await offlineStore.deleteAssessment(id);
                    await syncQueue.enqueue({ type: 'assessments.delete', id });
                    return { queued: true };
                }
                throw e;
            }
        },

        submit: async (id) => {
            try {
                return await _rawApi.assessments.submit(id);
            } catch (e) {
                if (isNetworkError(e)) {
                    console.log(`[API] Offline — queuing submit for assessment ${id}`);
                    await syncQueue.enqueue({type: "assessments.submit", assessmentId: id});
                    return {queued: true, message: "Submission queued — will sync when online"};
                }
                throw e;
            }
        },

        updateSectionProgress: async (assessmentId, sectionCode, done) => {
            try {
                return await _rawApi.assessments.updateSectionProgress(assessmentId, sectionCode, done);
            } catch (e) {
                if (isNetworkError(e)) {
                    console.log(`[API] Offline — queuing progress update ${sectionCode}`);
                    await syncQueue.enqueue({
                        type: "assessments.progress",
                        assessmentId, sectionCode, done,
                    });
                    return {queued: true};
                }
                throw e;
            }
        },

        create: async (facility_id, assessment_type, assessment_date, facilityMeta, user, sectionCodes) => {
            try {
                const data = await _rawApi.assessments.create(facility_id, assessment_type, assessment_date);
                const a = data?.assessment ?? data?.data ?? data;
                if (a?.id) await offlineStore.saveAssessment(a);
                return data;
            } catch (e) {
                if (isNetworkError(e)) {
                    console.log('[API] Offline — creating provisional assessment');
                    const tempId = 'offline_' + Date.now() + '_' + Math.random().toString(36).slice(2, 8);
                    // Build section_progress from cached schema codes
                    const sectionProgress = {};
                    (sectionCodes ?? []).forEach(code => { sectionProgress[code] = false; });
                    const provisional = {
                        id: tempId,
                        facility_id,
                        facility_name: facilityMeta?.name ?? '',
                        mfl_code: facilityMeta?.mfl_code ?? '',
                        county: facilityMeta?.county ?? '',
                        subcounty: facilityMeta?.subcounty ?? '',
                        assessment_type,
                        assessment_date,
                        assessor_id: user?.id ?? null,
                        assessor_name: user?.name ?? '',
                        status: 'in_progress',
                        section_progress: sectionProgress,
                        section_scores: {},
                        section_progress_detail: {},
                        responses: {},
                        overall_percentage: null,
                        overall_grade: null,
                        _isOffline: true,
                    };
                    await offlineStore.saveAssessment(provisional);
                    await syncQueue.enqueue({
                        type: 'assessments.create',
                        tempId,
                        facility_id,
                        assessment_type,
                        assessment_date,
                    });
                    return {_provisional: true, assessment: provisional};
                }
                throw e;
            }
        },
    },

    // ── Human Resources (cached reads, queued writes) ────────────────────────
    humanResources: {
        get: async (assessmentId) => {
            try {
                const data = await _rawApi.humanResources.get(assessmentId);
                // Cache the FULL cadre structure (cadre_id, cadre_name, field values)
                if (data?.data)
                    await offlineStore.saveHR(assessmentId, {structure: data.data});
                return data;
            } catch (e) {
                if (isNetworkError(e)) {
                    const cached = await offlineStore.getHR(assessmentId);
                    if (cached?.structure) {
                        console.log(`[API] Offline — returning cached HR for ${assessmentId}`);
                        // Merge any pending (unsaved) values on top of the structure
                        let structure = cached.structure;
                        if (cached.pendingValues && typeof cached.pendingValues === 'object') {
                            structure = structure.map(cadre => {
                                const pending = cached.pendingValues[cadre.cadre_id];
                                if (!pending) return cadre;
                                return { ...cadre, ...pending };
                            });
                        }
                        return {data: structure};
                    }
                    if (cached?.responses) {
                        return {data: cached.responses};
                    }
                }
                throw e;
            }
        },
        save: async (assessmentId, responses) => {
            const existing = await offlineStore.getHR(assessmentId);

            // Update the cached structure so offline reloads see the new values
            let updatedStructure = existing?.structure;
            if (updatedStructure && Array.isArray(responses)) {
                const respMap = {};
                responses.forEach(r => { respMap[r.cadre_id] = r; });
                updatedStructure = updatedStructure.map(cadre => ({
                    ...cadre,
                    ...(respMap[cadre.cadre_id] ?? {}),
                }));
            }

            await offlineStore.saveHR(assessmentId, {
                ...(existing ?? {}),
                ...(updatedStructure ? {structure: updatedStructure} : {}),
                responses,
                pendingValues: null, // clear pending — structure is now up-to-date
                lastSaved: Date.now(),
            });

            try {
                return await _rawApi.humanResources.save(assessmentId, responses);
            } catch (e) {
                if (isNetworkError(e)) {
                    console.log(`[API] Offline — queuing HR save for ${assessmentId}`);
                    await syncQueue.enqueue({
                        type: "humanResources.save",
                        assessmentId, responses,
                    });
                    return {queued: true};
                }
                throw e;
            }
        },
    },

    // ── Health Products (cached reads, queued writes) ────────────────────────
    healthProducts: {
        get: async (assessmentId) => {
            try {
                const data = await _rawApi.healthProducts.get(assessmentId);
                // Cache the FULL department/category/commodity structure
                if (data?.data)
                    await offlineStore.saveHP(assessmentId, {structure: data.data});
                return data;
            } catch (e) {
                if (isNetworkError(e)) {
                    const cached = await offlineStore.getHP(assessmentId);
                    if (cached?.structure) {
                        console.log(`[API] Offline — returning cached HP for ${assessmentId}`);
                        // Merge pending flat-map responses on top of structure
                        let structure = cached.structure;
                        if (cached.pendingFlat && typeof cached.pendingFlat === 'object') {
                            structure = structure.map(dept => ({
                                ...dept,
                                categories: dept.categories.map(cat => ({
                                    ...cat,
                                    commodities: cat.commodities.map(c => {
                                        const key = `${dept.department_id}_${c.commodity_id}`;
                                        return cached.pendingFlat[key] !== undefined
                                            ? { ...c, available: cached.pendingFlat[key] }
                                            : c;
                                    }),
                                })),
                            }));
                        }
                        return {data: structure};
                    }
                    if (cached?.responses) {
                        return {data: cached.responses};
                    }
                }
                throw e;
            }
        },
        save: async (assessmentId, responses, departmentId = null) => {
            const existing = await offlineStore.getHP(assessmentId);

            // Update the cached structure so offline reloads see the new values
            let updatedStructure = existing?.structure;
            if (updatedStructure && Array.isArray(responses)) {
                const respMap = {};
                responses.forEach(r => {
                    respMap[`${r.department_id}_${r.commodity_id}`] = r.available;
                });
                updatedStructure = updatedStructure.map(dept => ({
                    ...dept,
                    categories: dept.categories.map(cat => ({
                        ...cat,
                        commodities: cat.commodities.map(c => {
                            const key = `${dept.department_id}_${c.commodity_id}`;
                            return respMap[key] !== undefined
                                ? { ...c, available: respMap[key] }
                                : c;
                        }),
                    })),
                }));
            }

            await offlineStore.saveHP(assessmentId, {
                ...(existing ?? {}),
                ...(updatedStructure ? {structure: updatedStructure} : {}),
                responses,
                pendingFlat: null, // clear pending — structure is now up-to-date
                lastSaved: Date.now(),
            });

            try {
                return await _rawApi.healthProducts.save(assessmentId, responses, departmentId);
            } catch (e) {
                if (isNetworkError(e)) {
                    console.log(`[API] Offline — queuing HP save for ${assessmentId}`);
                    await syncQueue.enqueue({
                        type: "healthProducts.save",
                        assessmentId, responses, departmentId,
                    });
                    return {queued: true};
                }
                throw e;
            }
        },
    },

    // ── Responses (cached reads, queued writes) ──────────────────────────────
    responses: {
        list: async (assessmentId) => {
            try {
                const data = await _rawApi.responses.list(assessmentId);
                if (data) {
                    // Normalize to { responses, explanations } before caching
                    const normalized = {
                        responses: data.responses ?? (('explanations' in (data ?? {})) ? {} : (data ?? {})),
                        explanations: data.explanations ?? {},
                    };
                    await offlineStore.saveResponses(assessmentId, normalized);
                }
                return data;
            } catch (e) {
                if (isNetworkError(e)) {
                    const cached = await offlineStore.getResponses(assessmentId);
                    if (cached) {
                        console.log(`[API] Offline — returning cached responses for ${assessmentId}`);
                        return cached;
                    }
                }
                throw e;
            }
        },
        bulkSave: async (assessmentId, sectionCode, responses, explanations) => {
            // Always persist locally — normalize to { responses, explanations } shape
            // regardless of whether existing data is flat { CODE: val } or nested.
            const existing = await offlineStore.getResponses(assessmentId) ?? {};
            const existingResponses = existing.responses
                ?? (('explanations' in existing) ? {} : existing);
            const existingExplanations = existing.explanations ?? {};
            const merged = {
                responses: {...existingResponses, ...responses},
                explanations: {...existingExplanations, ...explanations},
            };
            await offlineStore.saveResponses(assessmentId, merged);

            try {
                return await _rawApi.responses.bulkSave(assessmentId, sectionCode, responses, explanations);
            } catch (e) {
                if (isNetworkError(e)) {
                    console.log(`[API] Offline — queuing responses for ${assessmentId}/${sectionCode}`);
                    await syncQueue.enqueue({
                        type: "responses.bulkSave",
                        assessmentId, sectionCode, responses, explanations,
                    });
                    return {queued: true};
                }
                throw e;
            }
        },
    },

    // ── Reports ──────────────────────────────────────────────────────────────
    reports: {
        ..._rawApi.reports,

        show: async (assessmentId) => {
            const cacheKey = `report_${assessmentId}`;
            try {
                const data = await _rawApi.reports.show(assessmentId);
                await offlineStore.setMeta(cacheKey, data);
                return data;
            } catch (e) {
                if (isNetworkError(e)) {
                    const cached = await offlineStore.getMeta(cacheKey);
                    if (cached) return cached;
                }
                throw e;
            }
        },
        full: async (assessmentId) => {
            const cacheKey = `report_${assessmentId}`;
            try {
                const data = await _rawApi.reports.full(assessmentId);
                await offlineStore.setMeta(cacheKey, data);
                return data;
            } catch (e) {
                if (isNetworkError(e)) {
                    const cached = await offlineStore.getMeta(cacheKey);
                    if (cached) return cached;
                }
                throw e;
            }
        },
        summary: async (assessmentId) => {
            const cacheKey = `report_summary_${assessmentId}`;
            try {
                const data = await _rawApi.reports.summary(assessmentId);
                await offlineStore.setMeta(cacheKey, data);
                return data;
            } catch (e) {
                if (isNetworkError(e)) {
                    const cached = await offlineStore.getMeta(cacheKey);
                    if (cached) return cached;
                }
                throw e;
            }
        },

        // Email report — queues locally when offline, dispatches job when online
        emailReport: async (assessmentId, emails) => {
            // Always save a local pending job immediately so the UI can show it
            const localId = "local_" + Date.now() + "_" + Math.random().toString(36).slice(2, 7);
            const localJob = {
                id: localId,
                assessment_id: assessmentId,
                emails,
                status: "local_pending",
                created_at: new Date().toISOString(),
            };
            await offlineStore.saveEmailJob(localJob);

            try {
                const data = await _rawApi.reports.emailReport(assessmentId, emails);
                // Replace local job with server job
                await offlineStore.deleteEmailJob(localId);
                await offlineStore.saveEmailJob({ ...data, assessment_id: assessmentId, emails });
                return { ...data, _localId: localId };
            } catch (e) {
                if (isNetworkError(e)) {
                    console.log(`[API] Offline — queuing email report for assessment ${assessmentId}`);
                    await syncQueue.enqueue({
                        type: "report.email",
                        assessmentId,
                        emails,
                        localJobId: localId,
                    });
                    return { queued: true, localJobId: localId, status: "local_pending" };
                }
                // On hard error, remove the local placeholder
                await offlineStore.deleteEmailJob(localId);
                throw e;
            }
        },

        // Fetch all email jobs — server first, fall back to local cache
        emailJobs: async () => {
            try {
                const data = await _rawApi.reports.emailJobs();
                const jobs = data?.data ?? [];
                await offlineStore.saveEmailJobs(jobs);
                return jobs;
            } catch (e) {
                if (isNetworkError(e)) {
                    const cached = await offlineStore.getAllEmailJobs();
                    return Object.values(cached ?? {}).sort(
                        (a, b) => new Date(b.created_at) - new Date(a.created_at)
                    );
                }
                throw e;
            }
        },

        emailJobStatus: _rawApi.reports.emailJobStatus,
    },

    // ── Mentorships (cached reads) ───────────────────────────────────────────
    mentorships: {
        list: async () => {
            try {
                const data = await _rawApi.mentorships.list();
                const arr = Array.isArray(data?.data) ? data.data : [];
                if (arr.length > 0) await offlineStore.saveMentorships(arr);
                // Non-blocking smart sync while online
                smartSync().catch(() => {});
                return data;
            } catch (e) {
                if (isNetworkError(e)) {
                    const cached = await offlineStore.getMentorships();
                    if (cached) return { data: cached };
                }
                throw e;
            }
        },
        find: async (id) => {
            try {
                const data = await _rawApi.mentorships.find(id);
                if (data?.data) await offlineStore.saveMentorship(data.data);
                return data;
            } catch (e) {
                if (isNetworkError(e)) {
                    const cached = await offlineStore.getMentorship(id);
                    if (cached) return { data: cached };
                }
                throw e;
            }
        },
        classes: async (id) => {
            try {
                const data = await _rawApi.mentorships.classes(id);
                const arr = Array.isArray(data?.data) ? data.data : [];
                await offlineStore.saveMentorshipClasses(id, arr);
                return data;
            } catch (e) {
                if (isNetworkError(e)) {
                    const cached = await offlineStore.getMentorshipClasses(id);
                    if (cached) return { data: cached };
                }
                throw e;
            }
        },
        update: async (id, payload) => {
            try {
                return await _rawApi.mentorshipCreate.update(id, payload);
            } catch (e) {
                if (isNetworkError(e)) {
                    const cached = await offlineStore.getMentorship(id);
                    if (cached) await offlineStore.saveMentorship({ ...cached, ...payload });
                    await syncQueue.enqueue({ type: 'mentorships.update', id, payload });
                    return { data: { id, ...payload } };
                }
                throw e;
            }
        },
        classDetail: async (trainingId, classId) => {
            try {
                const data = await _rawApi.mentorships.classDetail(trainingId, classId);
                if (data?.data) await offlineStore.saveClassDetail(classId, data.data);
                return data;
            } catch (e) {
                if (isNetworkError(e)) {
                    const cached = await offlineStore.getClassDetail(classId);
                    if (cached) return { data: cached };
                }
                throw e;
            }
        },
        createClass: async (trainingId, payload) => {
            try {
                return await _rawApi.mentorships.createClass(trainingId, payload);
            } catch (e) {
                if (isNetworkError(e)) {
                    const tempId = 'local_class_' + Date.now();
                    const provisional = {
                        id: tempId, name: payload.name ?? '', status: 'draft',
                        start_date: payload.start_date ?? null, end_date: payload.end_date ?? null,
                        notes: payload.notes ?? null,
                        module_count: 0, participant_count: 0, progress_percentage: 0,
                        _isOffline: true,
                    };
                    const existing = (await offlineStore.getMentorshipClasses(trainingId)) ?? [];
                    await offlineStore.saveMentorshipClasses(trainingId, [...existing, provisional]);
                    await syncQueue.enqueue({ type: 'mentorships.createClass', trainingId, payload, tempId });
                    return { data: provisional };
                }
                throw e;
            }
        },
        updateClass: async (trainingId, classId, payload) => {
            try {
                return await _rawApi.mentorships.updateClass(trainingId, classId, payload);
            } catch (e) {
                if (isNetworkError(e)) {
                    const cached = await offlineStore.getClassDetail(classId);
                    if (cached) await offlineStore.saveClassDetail(classId, { ...cached, ...payload });
                    await syncQueue.enqueue({ type: 'mentorships.updateClass', trainingId, classId, payload });
                    return { data: { id: classId, ...payload } };
                }
                throw e;
            }
        },
        deleteClass: async (trainingId, classId) => {
            try {
                return await _rawApi.mentorships.deleteClass(trainingId, classId);
            } catch (e) {
                if (isNetworkError(e)) {
                    const existing = (await offlineStore.getMentorshipClasses(trainingId)) ?? [];
                    await offlineStore.saveMentorshipClasses(trainingId, existing.filter(c => c.id !== classId));
                    await syncQueue.enqueue({ type: 'mentorships.deleteClass', trainingId, classId });
                    return { message: 'Queued for sync.' };
                }
                throw e;
            }
        },
    },

    // ── Modules (write ops queued when offline) ──────────────────────────────
    modules: {
        list: async (classId) => {
            try {
                const data = await _rawApi.modules.list(classId);
                const arr = Array.isArray(data?.data) ? data.data : [];
                await offlineStore.saveModuleList(classId, arr);
                return data;
            } catch (e) {
                if (isNetworkError(e)) {
                    const cached = await offlineStore.getModuleList(classId);
                    if (cached) return { data: cached };
                }
                throw e;
            }
        },
        start: async (moduleId) => {
            try {
                return await _rawApi.modules.start(moduleId);
            } catch (e) {
                if (isNetworkError(e)) {
                    await syncQueue.enqueue({ type: 'mentorship.module.start', moduleId });
                    return { queued: true };
                }
                throw e;
            }
        },
        complete: async (moduleId) => {
            try {
                return await _rawApi.modules.complete(moduleId);
            } catch (e) {
                if (isNetworkError(e)) {
                    await syncQueue.enqueue({ type: 'mentorship.module.complete', moduleId });
                    return { queued: true };
                }
                throw e;
            }
        },
        add: async (classId, programModuleId) => {
            try {
                return await _rawApi.modules.add(classId, programModuleId);
            } catch (e) {
                if (isNetworkError(e)) {
                    const tempId = 'local_module_' + Date.now();
                    const provisional = {
                        id: tempId, name: 'Module', status: 'pending',
                        session_count: 0, _isOffline: true,
                    };
                    const existing = (await offlineStore.getModuleList(classId)) ?? [];
                    await offlineStore.saveModuleList(classId, [...existing, provisional]);
                    await syncQueue.enqueue({ type: 'mentorships.addModule', classId, programModuleId, tempId });
                    return { data: provisional };
                }
                throw e;
            }
        },
        remove: async (moduleId, classId) => {
            try {
                return await _rawApi.modules.remove(moduleId);
            } catch (e) {
                if (isNetworkError(e)) {
                    if (classId) {
                        const existing = (await offlineStore.getModuleList(classId)) ?? [];
                        await offlineStore.saveModuleList(classId, existing.filter(m => m.id !== moduleId));
                    } else {
                        console.warn('[API] modules.remove offline: classId missing, cache not patched');
                    }
                    await syncQueue.enqueue({ type: 'mentorships.removeModule', moduleId, classId });
                    return { message: 'Queued for sync.' };
                }
                throw e;
            }
        },
        sessions: async (moduleId) => {
            try {
                const data = await _rawApi.modules.sessions(moduleId);
                const arr = Array.isArray(data?.data) ? data.data : [];
                await offlineStore.saveSessionsByModule(moduleId, arr);
                return data;
            } catch (e) {
                if (isNetworkError(e)) {
                    const cached = await offlineStore.getSessionsByModule(moduleId);
                    if (cached) return { data: cached };
                }
                throw e;
            }
        },
        sessionTemplates: async (moduleId) => {
            try {
                const data = await _rawApi.modules.sessionTemplates(moduleId);
                const list = data?.data ?? data ?? [];
                await offlineStore.saveSessionTemplates(moduleId, list);
                return data;
            } catch (e) {
                if (isNetworkError(e)) {
                    const cached = await offlineStore.getSessionTemplates(moduleId);
                    return cached ? { data: cached } : { data: [] };
                }
                throw e;
            }
        },
        addSession: async (moduleId, templateId) => {
            try {
                return await _rawApi.modules.addSession(moduleId, templateId);
            } catch (e) {
                if (isNetworkError(e)) {
                    const tempId = 'local_session_' + Date.now();
                    const provisional = { id: tempId, name: 'Session', _isOffline: true };
                    const existing = (await offlineStore.getSessionsByModule(moduleId)) ?? [];
                    await offlineStore.saveSessionsByModule(moduleId, [...existing, provisional]);
                    await syncQueue.enqueue({ type: 'mentorships.addSession', moduleId, templateId, tempId });
                    return { data: provisional };
                }
                throw e;
            }
        },
    },

    // ── Attendance ────────────────────────────────────────────────────────────
    attendance: {
        roster: async (moduleId) => {
            try {
                const data = await _rawApi.attendance.roster(moduleId);
                if (data?.data) await offlineStore.saveAttendance(moduleId, data.data);
                return data;
            } catch (e) {
                if (isNetworkError(e)) {
                    const cached = await offlineStore.getAttendance(moduleId);
                    if (cached) return { data: cached };
                }
                throw e;
            }
        },
        mark: async (moduleId, participantId, status) => {
            try {
                return await _rawApi.attendance.mark(moduleId, participantId, status);
            } catch (e) {
                if (isNetworkError(e)) {
                    const existing = (await offlineStore.getAttendance(moduleId)) ?? [];
                    await offlineStore.saveAttendance(moduleId, existing.map(p =>
                        p.participant_id === participantId ? { ...p, status } : p
                    ));
                    await syncQueue.enqueue({ type: 'mentorship.attendance.mark', moduleId, participantId, status });
                    return { queued: true };
                }
                throw e;
            }
        },
        bulk: async (moduleId, attendances) => {
            try {
                return await _rawApi.attendance.bulk(moduleId, attendances);
            } catch (e) {
                if (isNetworkError(e)) {
                    for (const a of attendances) {
                        await syncQueue.enqueue({ type: 'mentorship.attendance.mark', moduleId, participantId: a.participant_id, status: a.status });
                    }
                    return { queued: true };
                }
                throw e;
            }
        },
    },

    // ── Me / Mentee classes ───────────────────────────────────────────────────
    me: {
        classes: async () => {
            try {
                const data = await _rawApi.me.classes();
                const arr = Array.isArray(data?.data) ? data.data : [];
                if (arr.length > 0) await offlineStore.saveMyClasses(arr);
                return data;
            } catch (e) {
                if (isNetworkError(e)) {
                    const cached = await offlineStore.getMyClasses();
                    if (cached) return { data: cached };
                }
                throw e;
            }
        },
        classDetail: async (classId) => {
            try {
                const data = await _rawApi.me.classDetail(classId);
                if (data?.data) await offlineStore.saveMyClass(classId, data.data);
                return data;
            } catch (e) {
                if (isNetworkError(e)) {
                    const cached = await offlineStore.getMyClass(classId);
                    if (cached) return { data: cached };
                }
                throw e;
            }
        },
        attend: async (classId, moduleId) => {
            try {
                return await _rawApi.me.attend(classId, moduleId);
            } catch (e) {
                if (isNetworkError(e)) {
                    await syncQueue.enqueue({ type: 'mentee.attendance.confirm', classId, moduleId });
                    return { queued: true };
                }
                throw e;
            }
        },
    },

    // ── Global trainings ─────────────────────────────────────────────────────
    trainings: {
        list: async () => {
            try {
                const data = await _rawApi.trainings.list();
                const arr = Array.isArray(data?.data) ? data.data : [];
                if (arr.length > 0) await offlineStore.saveTrainings(arr);
                return data;
            } catch (e) {
                if (isNetworkError(e)) {
                    const cached = await offlineStore.getTrainings();
                    if (cached) return { data: cached };
                }
                throw e;
            }
        },
        find: async (id) => {
            try {
                const data = await _rawApi.trainings.find(id);
                if (data?.data) await offlineStore.saveTraining(data.data);
                return data;
            } catch (e) {
                if (isNetworkError(e)) {
                    const cached = await offlineStore.getTraining(id);
                    if (cached) return { data: cached };
                }
                throw e;
            }
        },
        participants: async (id) => {
            try {
                const data = await _rawApi.trainings.participants(id);
                const arr = Array.isArray(data?.data) ? data.data : [];
                if (arr.length > 0)
                    await offlineStore.setMeta(`training_participants_${id}`, arr);
                return data;
            } catch (e) {
                if (isNetworkError(e)) {
                    const cached = await offlineStore.getMeta(`training_participants_${id}`);
                    if (cached) return { data: cached };
                }
                throw e;
            }
        },
    },

    // ── Lookup (cache-first, no writes) ─────────────────────────────────────
    lookups: {
        programs: async () => {
            try {
                const data = await _rawApi.lookups.programs();
                const list = data?.data ?? data;
                await offlineStore.setMeta('programs', list);
                return list;
            } catch {
                return (await offlineStore.getMeta('programs')) ?? [];
            }
        },
        programModules: async (programId) => {
            try {
                const data = await _rawApi.lookups.programModules(programId);
                const list = data?.data ?? data;
                await offlineStore.setMeta('program_modules_' + programId, list);
                return list;
            } catch {
                return (await offlineStore.getMeta('program_modules_' + programId)) ?? [];
            }
        },
        counties: async () => {
            try {
                const data = await _rawApi.lookups.counties();
                const list = data?.data ?? data;
                await offlineStore.setMeta('counties', list);
                return list;
            } catch {
                return (await offlineStore.getMeta('counties')) ?? [];
            }
        },
        facilitiesByCounty: async (countyId) => {
            try {
                let data;
                try {
                    data = await _rawApi.lookups.facilitiesByCounty(countyId);
                } catch (lookupError) {
                    data = await _rawApi.facilities.byCounty(countyId);
                }
                const list = normalizeApiList(data)
                    .map(normalizeFacilityOption)
                    .filter(f => f.id && f.name);
                await offlineStore.setMeta('facilities_county_' + countyId, list);
                return list;
            } catch (e) {
                const cached = (await offlineStore.getMeta('facilities_county_' + countyId)) ?? [];
                if (cached.length > 0 || isNetworkError(e)) return cached;
                throw e;
            }
        },
        cadres: async () => {
            try {
                const data = await _rawApi.lookups.cadres();
                const list = data?.data ?? data;
                await offlineStore.setMeta('cadres', list);
                return list;
            } catch {
                return (await offlineStore.getMeta('cadres')) ?? [];
            }
        },
        departments: async () => {
            try {
                const data = await _rawApi.lookups.departments();
                const list = data?.data ?? data;
                await offlineStore.setMeta('departments', list);
                return list;
            } catch {
                return (await offlineStore.getMeta('departments')) ?? [];
            }
        },
        userByEmail: async (email) => {
            try {
                return await _rawApi.lookups.userByEmail(email);
            } catch (e) {
                if (isNetworkError(e)) {
                    const map = await offlineStore.getUserLookupMap();
                    const key = (email ?? '').toLowerCase().trim();
                    const found = map?.[key];
                    if (found) return { data: { ...found, email: key } };
                    return null;
                }
                throw e;
            }
        },
        userSearch: async (q, perPage = 20) => {
            try {
                return await _rawApi.lookups.userSearch(q, perPage);
            } catch (e) {
                if (isNetworkError(e)) {
                    const map = await offlineStore.getUserLookupMap();
                    if (!map) return { data: [] };
                    const qLower = (q ?? '').toLowerCase().trim();
                    const results = Object.entries(map)
                        .filter(([email, u]) => u && (
                            email.includes(qLower) ||
                            (u.name ?? '').toLowerCase().includes(qLower)
                        ))
                        .map(([email, u]) => ({ ...u, email }))
                        .slice(0, perPage);
                    return { data: results };
                }
                throw e;
            }
        },
    },

    // ── Mentorship creation (online→queue) ───────────────────────────────────
    mentorshipCreate: {
        create: async (payload) => {
            try {
                const data = await _rawApi.mentorshipCreate.create(payload);
                return data;
            } catch (e) {
                if (isNetworkError(e)) {
                    const tempId = 'local_' + Date.now();
                    const tempClassId = 'local_class_' + (Date.now() + 1);
                    const local = {
                        ...payload,
                        id: tempId,
                        _isOffline: true,
                        status: 'draft',
                        class: {
                            id: tempClassId,
                            name: payload.class_name ?? '',
                            status: 'draft',
                            _isOffline: true,
                        },
                    };
                    await offlineStore.saveMentorship(local);
                    await syncQueue.enqueue({ type: 'mentorships.create', tempId, tempClassId, payload });
                    return { data: local };
                }
                throw e;
            }
        },
        update: async (id, payload) => {
            try {
                return await _rawApi.mentorshipCreate.update(id, payload);
            } catch (e) {
                if (isNetworkError(e)) {
                    await syncQueue.enqueue({ type: 'mentorships.update', id, payload });
                    return { data: { id, ...payload } };
                }
                throw e;
            }
        },
        submit: async (id) => {
            try {
                return await _rawApi.mentorshipCreate.submit(id);
            } catch (e) {
                if (isNetworkError(e)) {
                    await syncQueue.enqueue({ type: 'mentorships.submit', id });
                    return { message: 'Queued for sync.' };
                }
                throw e;
            }
        },
    },

    // ── Class lifecycle (online→queue) ───────────────────────────────────────
    classLifecycle: {
        start: async (classId) => {
            try {
                return await _rawApi.classLifecycle.start(classId);
            } catch (e) {
                if (isNetworkError(e)) {
                    await syncQueue.enqueue({ type: 'mentorships.startClass', classId });
                    return { data: { id: classId, status: 'active' } };
                }
                throw e;
            }
        },
        end: async (classId) => {
            try {
                return await _rawApi.classLifecycle.end(classId);
            } catch (e) {
                if (isNetworkError(e)) {
                    const cached = await offlineStore.getClassDetail(classId);
                    if (cached) await offlineStore.saveClassDetail(classId, { ...cached, status: 'completed' });
                    await syncQueue.enqueue({ type: 'mentorships.endClass', classId });
                    return { data: { id: classId, status: 'completed' } };
                }
                throw e;
            }
        },
        report: async (classId) => {
            try {
                const data = await _rawApi.classLifecycle.report(classId);
                await offlineStore.setMeta(`class_report_${classId}`, data);
                return data;
            } catch (e) {
                if (isNetworkError(e)) {
                    const cached = await offlineStore.getMeta(`class_report_${classId}`);
                    if (cached) return cached;
                }
                throw e;
            }
        },
        enrollmentLink: async (classId) => {
            try {
                const data = await _rawApi.classLifecycle.enrollmentLink(classId);
                if (data?.data) await offlineStore.saveEnrollmentLink(classId, data.data);
                return data;
            } catch (e) {
                if (isNetworkError(e)) {
                    const cached = await offlineStore.getEnrollmentLink(classId);
                    if (cached) return { data: cached };
                }
                throw e;
            }
        },
        regenerateToken: async (classId) => {
            try {
                return await _rawApi.classLifecycle.regenerateToken(classId);
            } catch (e) {
                if (isNetworkError(e)) {
                    await offlineStore.saveEnrollmentLink(classId, null);
                    await syncQueue.enqueue({ type: 'mentorships.regenerateToken', classId });
                    return { queued: true };
                }
                throw e;
            }
        },
        addModule: async (classId, programModuleId) => {
            try {
                return await _rawApi.classLifecycle.addModule(classId, programModuleId);
            } catch (e) {
                if (isNetworkError(e)) {
                    const tempId = 'local_module_' + Date.now();
                    const provisional = {
                        id: tempId, name: 'Module', status: 'pending',
                        session_count: 0, _isOffline: true,
                    };
                    const existing = (await offlineStore.getModuleList(classId)) ?? [];
                    await offlineStore.saveModuleList(classId, [...existing, provisional]);
                    await syncQueue.enqueue({ type: 'mentorships.addModule', classId, programModuleId, tempId });
                    return { data: provisional };
                }
                throw e;
            }
        },
        enrollMentee: async (classId, userId) => {
            try {
                return await _rawApi.classLifecycle.enrollMentee(classId, userId);
            } catch (e) {
                if (isNetworkError(e)) {
                    await syncQueue.enqueue({ type: 'mentorships.addMentee', classId, userId });
                    return { data: { participant_id: 'local_' + Date.now(), user_id: userId } };
                }
                throw e;
            }
        },
        createMentee: async (classId, payload) => {
            if (!payload?.email) throw new Error('createMentee: email is required');
            if (!classId) throw new Error('createMentee: classId is required');
            try {
                return await _rawApi.classLifecycle.createMentee(classId, payload);
            } catch (e) {
                if (isNetworkError(e)) {
                    const tempId = 'local_participant_' + Date.now();
                    const provisional = {
                        id: tempId,
                        user_id: null,
                        name: [payload.first_name, payload.last_name].filter(Boolean).join(' ') || payload.email,
                        email: payload.email,
                        phone: payload.phone ?? null,
                        invitation_sent_at: null,
                        _isOffline: true,
                    };
                    const existing = (await offlineStore.getParticipants(classId)) ?? [];
                    await offlineStore.saveParticipants(classId, [...existing, provisional]);
                    await syncQueue.enqueue({ type: 'mentorships.createMentee', classId, payload, tempId });
                    return { data: provisional };
                }
                throw e;
            }
        },
        updateMentee: async (classId, participantId, data) => {
            try {
                return await _rawApi.classLifecycle.updateMentee(classId, participantId, data);
            } catch (e) {
                if (isNetworkError(e)) {
                    const existing = (await offlineStore.getParticipants(classId)) ?? [];
                    await offlineStore.saveParticipants(classId, existing.map(p =>
                        p.id === participantId ? { ...p, ...data } : p
                    ));
                    await syncQueue.enqueue({ type: 'mentorships.updateMenteeEmail', classId, participantId, data });
                    return { data };
                }
                throw e;
            }
        },
        markInvited: async (classId, participantId) => {
            if (!navigator.onLine) {
                return { requiresInternet: true };
            }
            return _rawApi.classLifecycle.markInvited(classId, participantId);
        },
        removeMentee: async (classId, participantId) => {
            try {
                return await _rawApi.classLifecycle.removeMentee(classId, participantId);
            } catch (e) {
                if (isNetworkError(e)) {
                    await syncQueue.enqueue({ type: 'mentorships.removeMentee', classId, participantId });
                    return { message: 'Queued for sync.' };
                }
                throw e;
            }
        },
    },

    // ── Session notes / lifecycle ────────────────────────────────────────────
    sessions: {
        remove: async (sessionId, moduleId) => {
            try {
                return await _rawApi.sessions.remove(sessionId);
            } catch (e) {
                if (isNetworkError(e)) {
                    if (moduleId) {
                        const existing = (await offlineStore.getSessionsByModule(moduleId)) ?? [];
                        await offlineStore.saveSessionsByModule(moduleId, existing.filter(s => s.id !== sessionId));
                    } else {
                        console.warn('[API] sessions.remove offline: moduleId missing, cache not patched');
                    }
                    await syncQueue.enqueue({ type: 'mentorships.removeSession', sessionId, moduleId });
                    return { message: 'Queued for sync.' };
                }
                throw e;
            }
        },
        update: async (sessionId, payload) => {
            try {
                const data = await _rawApi.sessions.update(sessionId, payload);
                await offlineStore.saveSession({ id: sessionId, ...payload });
                return data;
            } catch (e) {
                if (isNetworkError(e)) {
                    await offlineStore.saveSession({ id: sessionId, ...payload, _pending: true });
                    await syncQueue.enqueue({ type: 'mentorships.updateSession', sessionId, payload });
                    return { data: { id: sessionId, ...payload } };
                }
                throw e;
            }
        },
    },

    // ── Training actions (online→queue) ──────────────────────────────────────
    trainingActions: {
        enroll: async (trainingId) => {
            try {
                return await _rawApi.trainingActions.enroll(trainingId);
            } catch (e) {
                if (isNetworkError(e)) {
                    await syncQueue.enqueue({ type: 'trainings.enroll', trainingId });
                    return { data: { participant_id: 'pending', status: 'registered' } };
                }
                throw e;
            }
        },
        attendance: async (trainingId) => {
            try {
                return await _rawApi.trainingActions.attendance(trainingId);
            } catch (e) {
                if (isNetworkError(e)) {
                    await syncQueue.enqueue({ type: 'trainings.attendance', trainingId });
                    return { message: 'Queued for sync.' };
                }
                throw e;
            }
        },
    },

    // ── Resources (cache with 24h TTL — both typed and untyped) ────────────────
    resources: {
        list: async (type) => {
            const now = Date.now();
            const ttl = 24 * 60 * 60 * 1000;

            if (!type) {
                const cachedAt = (await offlineStore.getResourcesCachedAt()) ?? 0;
                if (now - cachedAt < ttl) {
                    const cached = await offlineStore.getResources();
                    if (cached) return cached;
                }
            } else {
                const entry = await offlineStore.getMeta(`resources_type_${type}`);
                if (entry && now - (entry._cachedAt ?? 0) < ttl) return entry.data;
            }

            try {
                const data = await _rawApi.resources.list(type);
                const list = data?.data ?? data;
                if (!type) {
                    await offlineStore.saveResources(list);
                    await offlineStore.setResourcesCachedAt(now);
                } else {
                    await offlineStore.setMeta(`resources_type_${type}`, { data: list, _cachedAt: now });
                }
                return list;
            } catch {
                if (type) {
                    const entry = await offlineStore.getMeta(`resources_type_${type}`);
                    return entry?.data ?? [];
                }
                return (await offlineStore.getResources()) ?? [];
            }
        },
    },

    // ── Participants ──────────────────────────────────────────────────────────
    participants: {
        list: async (classId) => {
            try {
                const data = await _rawApi.participants.list(classId);
                if (data?.data) await offlineStore.saveParticipants(classId, data.data);
                return data;
            } catch (e) {
                if (isNetworkError(e)) {
                    const cached = await offlineStore.getParticipants(classId);
                    if (cached) return { data: cached };
                }
                throw e;
            }
        },
        progress: _rawApi.participants.progress,
    },

    // ── Analytics summary (mobile dashboard) ─────────────────────────────────
    analytics: {
        summary: async (mode, year = '') => {
            const cacheKey = `analytics_${mode}${year ? `_${year}` : ''}`;
            try {
                const data = await get(`/analytics/summary?mode=${mode}${year ? `&year=${year}` : ''}`);
                await offlineStore.setMeta(cacheKey, data);
                return data;
            } catch (e) {
                if (isNetworkError(e)) {
                    const cached = await offlineStore.getMeta(cacheKey);
                    if (cached) return cached;
                }
                throw e;
            }
        },
    },

    // ── Scope config ─────────────────────────────────────────────────────────
    scopes: {
        getConfig: () => _rawApi.scopes.getConfig(),
    },

    // ── Smart sync (incremental, delta-based) ────────────────────────────────
    smartSync,

    // ── Prefetch for offline ─────────────────────────────────────────────────
    // Call after initial data load while online. Pre-caches HR structure,
    // HP structure, and responses for all in-progress assessments so they
    // are available if the user goes into the field without connectivity.
    prefetchForOffline: async (assessments) => {
        if (!navigator.onLine)
            return;
        // Facilities are fetched unconditionally — needed before any assessment opens
        try {
            await api.facilities.list();
        } catch {
            // Non-critical
        }
        const inProgress = (assessments ?? []).filter(a => a.status === "in_progress");
        if (inProgress.length === 0)
            return;

        console.log(`[API] Prefetching offline data for ${inProgress.length} in-progress assessments…`);

        for (const a of inProgress) {
            try {
                // These calls go through the offline-aware wrapper, so they
                // automatically cache the results in IndexedDB
                await Promise.allSettled([
                    api.responses.list(a.id),
                    api.humanResources.get(a.id),
                    api.healthProducts.get(a.id),
                ]);
            } catch {
                // Non-critical — best effort
            }
        }

        // Cache reports for completed/submitted assessments so they're viewable offline
        const completed = (assessments ?? []).filter(a => a.status === 'submitted' || a.status === 'completed');
        for (const a of completed) {
            try {
                await Promise.allSettled([
                    api.reports.show(a.id),
                    api.reports.summary(a.id),
                ]);
            } catch {
                // Non-critical
            }
        }

        await offlineStore.setMeta("lastPrefetch", Date.now());
        console.log("[API] Prefetch complete");
    },

    prefetchMentorshipsForOffline: async () => {
        if (!navigator.onLine) return;

        let mentorships = [];
        try {
            const data = await _rawApi.mentorships.list();
            mentorships = Array.isArray(data?.data) ? data.data : [];
            if (mentorships.length > 0) await offlineStore.saveMentorships(mentorships);
        } catch { return; }

        const allClasses = [];
        await Promise.allSettled(mentorships.map(async (m) => {
            try {
                const d = await _rawApi.mentorships.find(m.id);
                if (d?.data) {
                    await offlineStore.saveMentorship(d.data);
                    (d.data.classes ?? []).forEach(c => allClasses.push({ trainingId: m.id, classId: c.id }));
                }
            } catch { /* continue */ }
        }));

        const allModules = [];
        await Promise.allSettled(allClasses.map(({ trainingId, classId }) =>
            Promise.allSettled([
                _rawApi.mentorships.classDetail(trainingId, classId).then(d => {
                    if (d?.data) {
                        offlineStore.saveClassDetail(classId, d.data);
                        (d.data.modules ?? []).forEach(mod => allModules.push({ classId, moduleId: mod.id }));
                    }
                }),
                _rawApi.participants.list(classId).then(d => {
                    if (d?.data) offlineStore.saveParticipants(classId, d.data);
                }),
                _rawApi.classLifecycle.enrollmentLink(classId).then(d => {
                    if (d?.data) offlineStore.saveEnrollmentLink(classId, d.data);
                }),
            ])
        ));

        await Promise.allSettled(allModules.map(({ moduleId }) =>
            Promise.allSettled([
                _rawApi.modules.sessions(moduleId).then(d => {
                    const arr = Array.isArray(d?.data) ? d.data : [];
                    return offlineStore.saveSessionsByModule(moduleId, arr);
                }),
                _rawApi.attendance.roster(moduleId).then(d => {
                    if (d?.data) offlineStore.saveAttendance(moduleId, d.data);
                }),
                _rawApi.modules.sessionTemplates(moduleId).then(d => {
                    const list = d?.data ?? d ?? [];
                    return offlineStore.saveSessionTemplates(moduleId, list);
                }),
            ])
        ));

        await Promise.allSettled([
            _rawApi.lookups.cadres().then(d => offlineStore.setMeta('cadres', d?.data ?? d ?? [])),
            _rawApi.lookups.departments().then(d => offlineStore.setMeta('departments', d?.data ?? d ?? [])),
        ]);
    },
};

// ── Module-level event listeners: trigger smartSync on network recovery ──────
window.addEventListener('online', () => { smartSync().catch(() => {}); });
document.addEventListener('resume', () => {
    if (navigator.onLine) smartSync().catch(() => {});
});

export default api;


