import { useState, useEffect } from 'react';
import api from '../services/api.service.js';
import { calcGrade, Z, overallPercent } from '../constants.js';
import { AssessmentAnalyticsHomeScreen } from '../screens/screen-analytics-home.jsx';
import { AssessmentsListScreen } from '../screens/screen-assessments-list.jsx';
import { AssessmentDetailScreen } from '../screens/screen-assessment-detail.jsx';
import { AssessmentFormScreen } from '../screens/screen-assessment-form.jsx';
import { AssessmentReportScreen } from '../screens/screen-assessment-report.jsx';
import { ReportsScreen } from '../screens/screen-reports.jsx';
import { EmailJobsScreen } from '../screens/screen-email-jobs.jsx';

const TABS = [
    { id: 'home',        icon: '🏠', label: 'Home' },
    { id: 'assessments', icon: '📋', label: 'Assessments' },
    { id: 'reports',     icon: '📊', label: 'Reports' },
];

function BottomNav({ active, onChange }) {
    return (
        <nav style={{ position: 'fixed', bottom: 0, left: 0, right: 0, background: '#ffffff', backdropFilter: 'blur(12px)', WebkitBackdropFilter: 'blur(12px)', borderTop: '1px solid #EAF6F7', boxShadow: '0 -2px 16px rgba(0,0,0,0.06)', display: 'flex', zIndex: Z.navBar, paddingBottom: 'env(safe-area-inset-bottom)' }}>
            {TABS.map(tab => (
                <button key={tab.id} onClick={() => onChange(tab.id)} style={{ flex: 1, padding: '10px 0 8px', background: 'none', border: 'none', cursor: 'pointer', display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 3, color: active === tab.id ? '#0097A7' : '#8BC8C8', fontSize: 10, fontWeight: active === tab.id ? 700 : 400, transition: 'color 0.15s' }}>
                    <span style={{ fontSize: 20 }}>{tab.icon}</span>
                    <span>{tab.label}</span>
                </button>
            ))}
        </nav>
    );
}

function enrichAssessment(a) {
    if (!a) return a;
    const base = { ...a, section_scores: a.section_scores ?? {}, section_progress: a.section_progress ?? {}, responses: a.responses ?? {} };
    // Original template keeps its (4 sections ÷ 4) figure; other templates use the server's score.
    const pct = overallPercent(base);
    return { ...base, overall_percentage: pct ?? (Number(a.overall_percentage) || null), overall_grade: pct != null ? calcGrade(pct) : a.overall_grade };
}

export function AssessmentsScope({ user, onLogout, onUserUpdate }) {
    const [tab, setTab]     = useState('home');
    const [modal, setModal] = useState(null);
    const [assessments, setAssessments]         = useState([]);
    const [sections, setSections]               = useState([]);   // legacy global schema (assessments with no template)
    const [templates, setTemplates]             = useState({ data: [], default_template_id: null });
    const [schemas, setSchemas]                 = useState({});   // templateId -> sections[]
    const [facilities, setFacilities]           = useState([]);
    const [sectionAverages]                     = useState([]);
    const [loading, setLoading]                 = useState(true);
    const [error, setError]                     = useState(null);

    useEffect(() => {
        let mounted = true;
        Promise.allSettled([
            api.assessments.list(),
            api.sections.fullSchema(),
            api.facilities.list(),
            api.templates.list(),
        ]).then(([aRes, sRes, fRes, tRes]) => {
            if (!mounted) return;
            if (aRes.status === 'fulfilled') { const arr = Array.isArray(aRes.value?.data) ? aRes.value.data : Array.isArray(aRes.value) ? aRes.value : []; setAssessments(arr.map(enrichAssessment)); }
            else { setError(aRes.reason?.message ?? 'Failed to load'); }
            if (sRes.status === 'fulfilled') { const arr = Array.isArray(sRes.value) ? sRes.value : Array.isArray(sRes.value?.data) ? sRes.value.data : []; setSections(arr); }
            if (fRes.status === 'fulfilled') { const arr = Array.isArray(fRes.value) ? fRes.value : Array.isArray(fRes.value?.data) ? fRes.value.data : []; setFacilities(arr); }
            let tpls = { data: [], default_template_id: null };
            if (tRes.status === 'fulfilled' && Array.isArray(tRes.value?.data)) { tpls = tRes.value; setTemplates(tpls); }
            setLoading(false);

            // Fetch the schema of every template in play: the ones that can be started AND the
            // ones existing assessments were created on (which may since have been retired).
            const arr = aRes.status === 'fulfilled' ? (Array.isArray(aRes.value?.data) ? aRes.value.data : Array.isArray(aRes.value) ? aRes.value : []) : [];
            const ids = [...new Set([...tpls.data.map(t => t.id), ...arr.map(a => a.assessment_type_id)].filter(Boolean))];
            ids.forEach(id => api.templates.schema(id)
                .then(sec => { if (mounted) setSchemas(prev => ({ ...prev, [id]: sec })); })
                .catch(() => {}));
        });
        return () => { mounted = false; };
    }, []);

    // An assessment is rendered with the schema of ITS OWN template; older records with no
    // template fall back to the legacy global schema.
    function sectionsFor(a) { return (a?.assessment_type_id && schemas[a.assessment_type_id]) || sections; }
    // Replace an assessment everywhere after it changed (reopened, submitted, ...).
    function applyUpdate(updated) { const e = enrichAssessment(updated); setAssessments(prev => prev.map(x => x.id === e.id ? e : x)); setModal(m => m?.data?.id === e.id ? { ...m, data: e } : m); return e; }

    function refreshAssessments() { api.assessments.list().then(data => { const arr = Array.isArray(data?.data) ? data.data : Array.isArray(data) ? data : []; setAssessments(arr.map(enrichAssessment)); }).catch(() => {}); }

    if (modal?.type === 'detail') return <AssessmentDetailScreen assessment={modal.data} sections={sectionsFor(modal.data)} onAssessmentChanged={applyUpdate} onBack={() => setModal(null)} onContinue={(a) => setModal({ type: 'form', data: a })} onViewReport={() => setModal({ type: 'report', data: modal.data })} onTeamUpdated={(updated) => { setAssessments(prev => prev.map(item => item.id === updated.id ? updated : item)); setModal({ type: 'detail', data: updated }); }} onDelete={(a) => { api.assessments.delete(a.id).then(() => { setAssessments(prev => prev.filter(x => x.id !== a.id)); setModal(null); }).catch(() => {}); }} />;
    if (modal?.type === 'form') return <AssessmentFormScreen user={user} sections={sectionsFor(modal.data)} editAssessment={modal.data} onBack={() => setModal({ type: 'detail', data: modal.data })} onComplete={(a) => { const enriched = enrichAssessment(a); setAssessments(prev => prev.map(x => x.id === enriched.id ? enriched : x)); setModal({ type: 'detail', data: enriched }); }} />;
    if (modal?.type === 'report') return <AssessmentReportScreen assessment={modal.data} onBack={() => setModal({ type: 'detail', data: modal.data })} />;
    if (modal?.type === 'emailJobs') return <EmailJobsScreen onBack={() => setModal(null)} />;

    return (
        <div style={{ paddingBottom: 64, minHeight: '100vh', background: '#f0f4f8' }}>
            {tab === 'home' && <AssessmentAnalyticsHomeScreen user={user} />}
            {tab === 'assessments' && <AssessmentsListScreen assessments={assessments} sections={sections} sectionsFor={sectionsFor} templates={templates} schemas={schemas} onView={(a) => setModal({ type: 'detail', data: a })} loading={loading} onCreate={(a) => { const enriched = enrichAssessment(a); setAssessments(prev => [...prev, enriched]); setModal({ type: 'detail', data: enriched }); }} facilities={facilities} user={user} openSheet={false} onSheetClose={() => {}} />}
            {tab === 'reports' && <ReportsScreen user={user} assessments={assessments} sectionAverages={sectionAverages} loading={loading} onViewAssessment={(a) => setModal({ type: 'detail', data: a })} onViewEmailJobs={() => setModal({ type: 'emailJobs' })} />}
            <BottomNav active={tab} onChange={setTab} />
        </div>
    );
}
