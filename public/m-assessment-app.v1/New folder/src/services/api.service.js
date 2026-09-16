/**
 * MNCH Assessment API Service
 * src/services/api.service.js
 *
 * BASE_URL is set per environment via .env files:
 *   .env.development  → used by `npm run dev`
 *   .env.production   → used by `npm run build` (Capacitor APK/IPA)
 *
 * Vite replaces import.meta.env.VITE_API_BASE_URL at build time,
 * so the correct URL is hardcoded into the final JS bundle.
 * On a real device there is no proxy, no localhost — just the absolute URL.
 */
const BASE_URL = import.meta.env.VITE_API_BASE_URL ?? 'https://mnchkenyamentorship.org/api/v1';

const TokenStore = {
    get: () => { try { return localStorage.getItem('mnch_token'); } catch { return null; } },
    set: (t) => { try { localStorage.setItem('mnch_token', t); } catch { } },
    clear: () => { try { localStorage.removeItem('mnch_token'); } catch { } },
};

async function request(method, path, body = null) {
    const token = TokenStore.get();
    const res = await fetch(BASE_URL + path, {
        method,
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            ...(token ? { Authorization: 'Bearer ' + token } : {}),
        },
        ...(body !== null ? { body: JSON.stringify(body) } : {}),
    });

    if (res.status === 204) return null;

    let data;
    try { data = await res.json(); } catch { data = {}; }

    if (!res.ok) {
        const err = new Error(data.message || `HTTP ${res.status}`);
        err.status = res.status;
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
const post = (path, body) => request('POST', path, body);
const put = (path, body) => request('PUT', path, body);
const del = (path) => request('DELETE', path);

const api = {
    setToken: (t) => TokenStore.set(t),
    clearToken: () => TokenStore.clear(),
    getToken: () => TokenStore.get(),

    // ── Auth ──────────────────────────────────────────────────────────────────
    auth: {
        login: (email, password, deviceName) =>
            post('/auth/login', { email, password, device_name: deviceName || 'mobile-app' }),
        logout: () => post('/auth/logout').finally(() => TokenStore.clear()),
        logoutAll: () => post('/auth/logout-all').finally(() => TokenStore.clear()),
        me: () => get('/auth/me'),
        refresh: () => post('/auth/refresh').then(d => { if (d?.token) TokenStore.set(d.token); return d; }),
        forgotPassword: (email) => post('/auth/forgot-password', { email }),
        resetPassword: (token, email, password, password_confirmation) =>
            post('/auth/reset-password', { token, email, password, password_confirmation }),
    },

    // ── Profile ───────────────────────────────────────────────────────────────
    profile: {
        get: () => get('/profile'),
        update: (data) => put('/profile', data),
        changePassword: (data) => put('/profile/password', data),
        stats: () => get('/profile/stats'),
    },

    // ── Facilities ────────────────────────────────────────────────────────────
    facilities: {
        list: (params) => get('/facilities', params),
        byCounty: (countyId) => get('/facilities/county/' + countyId),
        find: (id) => get('/facilities/' + id),
    },

    // ── Sections / Schema ─────────────────────────────────────────────────────
    sections: {
        list: () => get('/sections'),
        find: (id) => get('/sections/' + id),
        fullSchema: () => get('/sections/schema/full'),
        clearSchemaCache: () => { },
    },

    // ── Assessments ───────────────────────────────────────────────────────────
    assessments: {
        list: (params) => get('/assessments', params),
        find: (id) => get('/assessments/' + id),
        update: (id, data) => put('/assessments/' + id, data),
        delete: (id) => del('/assessments/' + id),
        submit: (id) => post('/assessments/' + id + '/submit'),
        updateSectionProgress: (assessmentId, sectionCode, done) =>
            put('/assessments/' + assessmentId + '/sections/' + sectionCode + '/progress', { done }),
    },

    // ── Human Resources ───────────────────────────────────────────────────────
    humanResources: {
        get: (assessmentId) => get('/assessments/' + assessmentId + '/human-resources'),
        save: (assessmentId, responses) =>
            post('/assessments/' + assessmentId + '/human-resources', { responses }),
    },

    // ── Health Products ───────────────────────────────────────────────────────
    healthProducts: {
        get: (assessmentId) => get('/assessments/' + assessmentId + '/health-products'),
        save: (assessmentId, responses, departmentId = null) =>
            post('/assessments/' + assessmentId + '/health-products', {
                responses,
                ...(departmentId !== null ? { department_id: departmentId } : {}),
            }),
    },

    // ── Responses ─────────────────────────────────────────────────────────────
    responses: {
        list: (assessmentId) => get('/assessments/' + assessmentId + '/responses'),
        bulkSave: (assessmentId, sectionCode, responses, explanations) =>
            post('/assessments/' + assessmentId + '/responses', {
                section_code: sectionCode,
                responses: responses ?? {},
                explanations: explanations ?? {},
            }),
    },

    // ── Reports ───────────────────────────────────────────────────────────────
    reports: {
        show: (assessmentId) => get('/assessments/' + assessmentId + '/report'),
        full: (assessmentId) => get('/assessments/' + assessmentId + '/report'),
        summary: (assessmentId) => get('/assessments/' + assessmentId + '/report/summary'),
        downloadPdf: (assessmentId) => get('/assessments/' + assessmentId + '/report/pdf'),
        dashboard: () => get('/reports/dashboard'),
        sectionAverages: () => get('/reports/section-averages'),
    },
};

export default api;
