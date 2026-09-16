import { useState } from 'react';
import { AnalyticsHomeScreen }  from '../screens/screen-analytics-home.jsx';
import { TrainingsListScreen }  from '../screens/screen-trainings-list.jsx';
import { TrainingDetailScreen } from '../screens/screen-training-detail.jsx';

const TABS = [
    { id: 'home',      icon: '🏠', label: 'Home' },
    { id: 'trainings', icon: '📋', label: 'Trainings' },
];

function BottomNav({ active, onChange }) {
    return (
        <nav style={{ position: 'fixed', bottom: 0, left: 0, right: 0, background: '#ffffff', backdropFilter: 'blur(12px)', WebkitBackdropFilter: 'blur(12px)', borderTop: '1px solid #EAF6F7', boxShadow: '0 -2px 16px rgba(0,0,0,0.06)', display: 'flex', zIndex: 50, paddingBottom: 'env(safe-area-inset-bottom)' }}>
            {TABS.map(tab => (
                <button key={tab.id} onClick={() => onChange(tab.id)} style={{ flex: 1, padding: '10px 0 8px', background: 'none', border: 'none', cursor: 'pointer', display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 3, color: active === tab.id ? '#0097A7' : '#8BC8C8', fontSize: 10, fontWeight: active === tab.id ? 700 : 400, transition: 'color 0.15s' }}>
                    <span style={{ fontSize: 20 }}>{tab.icon}</span>
                    <span>{tab.label}</span>
                </button>
            ))}
        </nav>
    );
}

function TrainingsHomeScreen({ user }) {
    return <div style={{ padding: '24px 16px' }}><p style={{ color: '#1e293b', fontWeight: 700, fontSize: 20 }}>Welcome, {user?.name?.split(' ')[0]}</p><p style={{ color: '#64748b', fontSize: 14 }}>Browse and manage training events.</p></div>;
}

export function TrainingsScope({ user, onLogout, onUserUpdate }) {
    const [tab, setTab]     = useState('home');
    const [modal, setModal] = useState(null);

    if (modal?.type === 'trainingDetail') return (
        <TrainingDetailScreen training={modal.data} user={user} onBack={() => setModal(null)} />
    );

    return (
        <div style={{ paddingBottom: 64, minHeight: '100vh', background: '#f0f4f8' }}>
            {tab === 'home'      && <AnalyticsHomeScreen mode="training" user={user} />}
            {tab === 'trainings' && <TrainingsListScreen user={user} onOpen={(t) => setModal({ type: 'trainingDetail', data: t })} />}
            <BottomNav active={tab} onChange={setTab} />
        </div>
    );
}
