import { useState, useEffect, useRef } from "react";

// ── Shared ────────────────────────────────────────────────────────────────────
import { SECTION_META } from "./constants.js";
import { PhoneShell, BottomNav } from "./components/shared-components.jsx";

// ── Screens ───────────────────────────────────────────────────────────────────
import { LoginScreen } from "./screens/screen-login.jsx";
import { DashboardScreen } from "./screens/screen-dashboard.jsx";
import { AssessmentsListScreen } from "./screens/screen-assessments-list.jsx";
import { AssessmentDetailScreen } from "./screens/screen-assessment-detail.jsx";
import { AssessmentFormScreen } from "./screens/screen-assessment-form.jsx";
import { AssessmentReportScreen } from "./screens/screen-assessment-report.jsx";
import { ReportsScreen } from "./screens/screen-reports.jsx";
import { ProfileScreen } from "./screens/screen-profile.jsx";

import api from "./services/api.service.js";

// ─────────────────────────────────────────────────────────────────────────────
// Pure helpers — outside component so identity never changes between renders
// ─────────────────────────────────────────────────────────────────────────────

function normaliseUser(u) {
    if (!u) return u;
    const fac = typeof u.facility === "object" && u.facility !== null ? u.facility : null;
    return {
        ...u,
        facility: fac?.name ?? (typeof u.facility === "string" ? u.facility : ""),
        county: fac?.county ?? u.county ?? "",
        subcounty: fac?.subcounty ?? u.subcounty ?? "",
        mfl_code: fac?.mfl_code ?? u.mfl_code ?? "",
        initials: u.initials || ((u.name || "??").split(" ").map(p => p[0]).join("").slice(0, 2).toUpperCase()),
    };
}

function enrichAssessment(a) {
    if (!a) return a;
    return {
        ...a,
        section_scores: a.section_scores ?? {},
        section_progress: a.section_progress ?? {},
        responses: a.responses ?? {},
        facility_name: a.facility_name ?? "",
        mfl_code: a.mfl_code ?? "",
        county: a.county ?? "",
        subcounty: a.subcounty ?? "",
    };
}

// ─────────────────────────────────────────────────────────────────────────────
export default function App() {
    const [user, setUser] = useState(null);
    const [tab, setTab] = useState("dashboard");
    const [modal, setModal] = useState(null);
    const [assessments, setAssessments] = useState(null); // null = not yet loaded
    const [sections, setSections] = useState(null); // null = not yet loaded
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);

    // Prevents loadData re-firing when user object identity changes on re-renders
    const dataLoadedRef = useRef(false);

    // ── Session restore on mount (runs once) ─────────────────────────────────
    useEffect(() => {
        const token = api.getToken();
        if (!token) return;
        api.auth.me()
            .then(data => {
                const u = data?.user ?? data;
                if (u?.id) setUser(normaliseUser(u));
            })
            .catch(() => api.clearToken());
    }, []);

    // ── Load data when user.id becomes available ──────────────────────────────
    useEffect(() => {
        if (!user?.id) { dataLoadedRef.current = false; return; }
        if (dataLoadedRef.current) return;
        dataLoadedRef.current = true;
        runLoadData();
    }, [user?.id]); // eslint-disable-line react-hooks/exhaustive-deps

    const runLoadData = async () => {
        setLoading(true);
        setError(null);
        try {
            const [schemaRes, assessRes] = await Promise.all([
                api.sections.fullSchema(),
                api.assessments.list(),
            ]);

            const rawSections = Array.isArray(schemaRes) ? schemaRes
                : Array.isArray(schemaRes?.data) ? schemaRes.data : [];
            const rawAssessments = Array.isArray(assessRes) ? assessRes
                : Array.isArray(assessRes?.data) ? assessRes.data : [];

            console.log(`[MNCH] ${rawSections.length} sections, ${rawAssessments.length} assessments`);

            setSections(rawSections.map(s => ({
                ...s,
                icon: SECTION_META[s.code]?.icon ?? s.icon ?? "📋",
                gradient: SECTION_META[s.code]?.gradient ?? [s.color ?? "#6B7280", s.color ?? "#374151"],
            })));
            setAssessments(rawAssessments);
        } catch (e) {
            console.error("[MNCH] loadData error:", e);
            setError(e.message || "Failed to load. Please retry.");
            setSections(s => s ?? []);
            setAssessments(a => a ?? []);
        } finally {
            setLoading(false);
        }
    };

    // ── Retry ─────────────────────────────────────────────────────────────────
    const handleRetry = () => {
        dataLoadedRef.current = false;
        setAssessments(null);
        setSections(null);
        setError(null);
        dataLoadedRef.current = true;
        runLoadData();
    };

    // ── Auth ──────────────────────────────────────────────────────────────────
    const handleLogin = (u) => {
        dataLoadedRef.current = false;
        setAssessments(null);
        setSections(null);
        setError(null);
        setModal(null);
        setUser(normaliseUser(u));
        setTab("dashboard");
    };

    const handleLogout = async () => {
        try { await api.auth.logout(); } catch (_) { }
        api.clearToken();
        api.sections.clearSchemaCache();
        dataLoadedRef.current = false;
        setUser(null);
        setAssessments(null);
        setSections(null);
        setModal(null);
        setTab("dashboard");
        setError(null);
    };

    // ── Navigation ────────────────────────────────────────────────────────────
    const handleTabChange = (t) => {
        if (t === "new") return;
        setTab(t);
        setModal(null);
    };

    // ── Assessment navigation ─────────────────────────────────────────────────
    const openDetail = (a) => setModal({ type: "detail", data: a });
    const openContinue = (a) => setModal({ type: "form", data: a });
    const openReport = (a) => setModal({ type: "report", data: a });
    const closeModal = () => setModal(null);

    // Go back from report → detail
    const backFromReport = () => {
        if (modal?.prevData) setModal({ type: "detail", data: modal.prevData });
        else closeModal();
    };

    const handleAssessmentComplete = async (assessmentOrId) => {
        const id = assessmentOrId?.id ?? assessmentOrId;
        try {
            const res = await api.assessments.find(id);
            const updated = res?.data ?? res;
            if (!updated?.id) return;
            const enriched = enrichAssessment(updated);
            setAssessments(prev => {
                const list = prev ?? [];
                const idx = list.findIndex(a => a.id === enriched.id);
                if (idx >= 0) { const n = [...list]; n[idx] = enriched; return n; }
                return [enriched, ...list];
            });
            // After completion, go straight to the report
            setModal({ type: "report", data: enriched, prevData: enriched });
        } catch (e) {
            console.error("Refresh failed", e);
            // Fallback: show detail screen
            if (assessmentOrId?.id) setModal({ type: "detail", data: assessmentOrId });
        }
    };

    // ── Derived ───────────────────────────────────────────────────────────────
    const userAssessments = (assessments ?? []).map(enrichAssessment);
    const sectionsResolved = sections ?? [];
    const isLoading = loading || (!!user?.id && assessments === null && !error);

    const sectionAverages = sectionsResolved.map(s => {
        const scores = userAssessments
            .filter(a => a.status === "completed")
            .map(a => a.section_scores?.[s.code]?.percentage ?? 0);
        return {
            ...s,
            average_pct: scores.length
                ? Math.round(scores.reduce((a, b) => a + b, 0) / scores.length)
                : 0,
        };
    });

    // ── Render ────────────────────────────────────────────────────────────────
    return (
        <PhoneShell>
            {/* ── Login ── */}
            {!user && (
                <div style={{ position: "absolute", inset: 0 }}>
                    <LoginScreen onLogin={handleLogin} />
                </div>
            )}

            {/* ── Tab screens (no modal) ── */}
            {user && !modal && (
                <>
                    <div style={{ position: "absolute", inset: 0, bottom: 56, overflow: "hidden" }}>
                        {tab === "dashboard" && (
                            <DashboardScreen
                                user={user}
                                assessments={userAssessments}
                                onViewAssessment={openDetail}
                                loading={isLoading}
                                error={error}
                                onRetry={handleRetry}
                            />
                        )}
                        {tab === "assessments" && (
                            <AssessmentsListScreen
                                assessments={userAssessments}
                                sections={sectionsResolved}
                                onView={openDetail}
                                loading={isLoading}
                            />
                        )}
                        {tab === "reports" && (
                            <ReportsScreen
                                user={user}
                                assessments={userAssessments}
                                sectionAverages={sectionAverages}
                                loading={isLoading}
                                onViewAssessment={openDetail}
                            />
                        )}
                        {tab === "profile" && (
                            <ProfileScreen
                                user={user}
                                assessments={userAssessments}
                                onUpdateUser={u => setUser(normaliseUser(u))}
                                onLogout={handleLogout}
                            />
                        )}
                    </div>
                    <div style={{ position: "absolute", bottom: 0, left: 0, right: 0, height: 56, zIndex: 100 }}>
                        <BottomNav active={tab} onChange={handleTabChange} hideNew />
                    </div>
                </>
            )}

            {/* ── Detail modal ── */}
            {user && modal?.type === "detail" && (
                <div style={{ position: "absolute", inset: 0 }}>
                    <AssessmentDetailScreen
                        assessment={modal.data}
                        sections={sectionsResolved}
                        onBack={closeModal}
                        onContinue={openContinue}
                        onViewReport={() => openReport(modal.data)}
                    />
                </div>
            )}

            {/* ── Form (continue) modal ── */}
            {user && modal?.type === "form" && modal.data && (
                <div style={{ position: "absolute", inset: 0 }}>
                    <AssessmentFormScreen
                        user={user}
                        sections={sectionsResolved}
                        editAssessment={modal.data}
                        onBack={closeModal}
                        onComplete={handleAssessmentComplete}
                    />
                </div>
            )}

            {/* ── Full report modal ── */}
            {user && modal?.type === "report" && modal.data && (
                <div style={{ position: "absolute", inset: 0 }}>
                    <AssessmentReportScreen
                        assessment={modal.data}
                        onBack={backFromReport}
                    />
                </div>
            )}
        </PhoneShell>
    );
}
