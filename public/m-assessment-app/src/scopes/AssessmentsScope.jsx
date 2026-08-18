import { useState, useEffect } from 'react';
import api from '../services/api.service.js';
import { calcGrade, Z } from '../constants.js';
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

const SCORED = ['infrastructure', 'skills_lab', 'information_systems', 'quality_of_care'];
function enrichAssessment(a) {
    if (!a) return a;
    const ss = a.section_scores ?? {};
    const vals = SCORED.map(c => { const n = Number(ss[c]?.percentage); return isNaN(n) ? null : n; }).filter(v => v !== null);
    const pct = vals.length ? vals.reduce((x, y) => x + y, 0) / 4 : null;
    return { ...a, section_scores: ss, section_progress: a.section_progress ?? {}, responses: a.responses ?? {}, overall_percentage: pct ?? (Number(a.overall_percentage) || null), overall_grade: pct != null ? calcGrade(pct) : a.overall_grade };
}

export function AssessmentsScope({ user, onLogout, onUserUpdate }) {
    const [tab, setTab]     = useState('home');
    const [modal, setModal] = useState(null);
    const [assessments, setAssessments]         = useState([]);
    const [sections, setSections]               = useState([]);
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
        ]).then(([aRes, sRes, fRes]) => {
            if (!mounted) return;
            if (aRes.status === 'fulfilled') { const arr = Array.isArray(aRes.value?.data) ? aRes.value.data : Array.isArray(aRes.value) ? aRes.value : []; setAssessments(arr.map(enrichAssessment)); }
            else { setError(aRes.reason?.message ?? 'Failed to load'); }
            if (sRes.status === 'fulfilled') { const arr = Array.isArray(sRes.value) ? sRes.value : Array.isArray(sRes.value?.data) ? sRes.value.data : []; setSections(arr); }
            if (fRes.status === 'fulfilled') { const arr = Array.isArray(fRes.value) ? fRes.value : Array.isArray(fRes.value?.data) ? fRes.value.data : []; setFacilities(arr); }
            setLoading(false);
        });
        return () => { mounted = false; };
    }, []);

    function refreshAssessments() { api.assessments.list().then(data => { const arr = Array.isArray(data?.data) ? data.data : Array.isArray(data) ? data : []; setAssessments(arr.map(enrichAssessment)); }).catch(() => {}); }

    if (modal?.type === 'detail') return <AssessmentDetailScreen assessment={modal.data} sections={sections} onBack={() => setModal(null)} onContinue={(a) => setModal({ type: 'form', data: a })} onViewReport={() => setModal({ type: 'report', data: modal.data })} onTeamUpdated={(updated) => { setAssessments(prev => prev.map(item => item.id === updated.id ? updated : item)); setModal({ type: 'detail', data: updated }); }} onDelete={(a) => { api.assessments.delete(a.id).then(() => { setAssessments(prev => prev.filter(x => x.id !== a.id)); setModal(null); }).catch(() => {}); }} />;
    if (modal?.type === 'form') return <AssessmentFormScreen user={user} sections={sections} editAssessment={modal.data} onBack={() => setModal({ type: 'detail', data: modal.data })} onComplete={(a) => { const enriched = enrichAssessment(a); setAssessments(prev => prev.map(x => x.id === enriched.id ? enriched : x)); setModal({ type: 'detail', data: enriched }); }} />;
    if (modal?.type === 'report') return <AssessmentReportScreen assessment={modal.data} onBack={() => setModal({ type: 'detail', data: modal.data })} />;
    if (modal?.type === 'emailJobs') return <EmailJobsScreen onBack={() => setModal(null)} />;

    return (
        <div style={{ paddingBottom: 64, minHeight: '100vh', background: '#f0f4f8' }}>
            {tab === 'home' && <AssessmentAnalyticsHomeScreen user={user} />}
            {tab === 'assessments' && <AssessmentsListScreen assessments={assessments} sections={sections} onView={(a) => setModal({ type: 'detail', data: a })} loading={loading} onCreate={(a) => { const enriched = enrichAssessment(a); setAssessments(prev => [...prev, enriched]); setModal({ type: 'detail', data: enriched }); }} facilities={facilities} user={user} openSheet={false} onSheetClose={() => {}} />}
            {tab === 'reports' && <ReportsScreen user={user} assessments={assessments} sectionAverages={sectionAverages} loading={loading} onViewAssessment={(a) => setModal({ type: 'detail', data: a })} onViewEmailJobs={() => setModal({ type: 'emailJobs' })} />}
            <BottomNav active={tab} onChange={setTab} />
        </div>
    );
}
