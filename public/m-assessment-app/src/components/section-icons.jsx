// SVG section icons — colour-coded per section, always render (no emoji dependency).

const COLORS = {
    infrastructure:      "#8B5CF6",
    skills_lab:          "#10B981",
    human_resources:     "#F59E0B",
    health_products:     "#EF4444",
    information_systems: "#06B6D4",
    quality_of_care:     "#EC4899",
};

const PATHS = {
    infrastructure: (sz, c) => (
        <svg width={sz} height={sz} viewBox="0 0 24 24" fill="none" stroke={c} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <rect x="3" y="10" width="18" height="11" rx="2"/>
            <path d="M7 10V7a5 5 0 0110 0v3"/>
        </svg>
    ),
    skills_lab: (sz, c) => (
        <svg width={sz} height={sz} viewBox="0 0 24 24" fill="none" stroke={c} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <path d="M6 2v6l-2 4a4 4 0 004 6h8a4 4 0 004-6l-2-4V2"/>
            <line x1="6" y1="2" x2="18" y2="2"/>
            <line x1="9" y1="12" x2="15" y2="12"/>
        </svg>
    ),
    human_resources: (sz, c) => (
        <svg width={sz} height={sz} viewBox="0 0 24 24" fill="none" stroke={c} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
            <circle cx="9" cy="7" r="4"/>
            <path d="M23 21v-2a4 4 0 00-3-3.87"/>
            <path d="M16 3.13a4 4 0 010 7.75"/>
        </svg>
    ),
    health_products: (sz, c) => (
        <svg width={sz} height={sz} viewBox="0 0 24 24" fill="none" stroke={c} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0016.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 002 8.5c0 2.3 1.5 4.05 3 5.5l7 7 7-7z"/>
        </svg>
    ),
    information_systems: (sz, c) => (
        <svg width={sz} height={sz} viewBox="0 0 24 24" fill="none" stroke={c} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <rect x="3" y="4" width="18" height="14" rx="2"/>
            <line x1="8" y1="21" x2="16" y2="21"/>
            <line x1="12" y1="18" x2="12" y2="21"/>
        </svg>
    ),
    quality_of_care: (sz, c) => (
        <svg width={sz} height={sz} viewBox="0 0 24 24" fill="none" stroke={c} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/>
        </svg>
    ),
};

const Fallback = (sz, c) => (
    <svg width={sz} height={sz} viewBox="0 0 24 24" fill="none" stroke={c} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
        <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
        <polyline points="14 2 14 8 20 8"/>
        <line x1="16" y1="13" x2="8" y2="13"/>
        <line x1="16" y1="17" x2="8" y2="17"/>
    </svg>
);

export function SectionIcon({ code, size = 16 }) {
    const fn = PATHS[code] ?? Fallback;
    return fn(size, COLORS[code] ?? "#6B7280");
}

export function sectionColor(code) {
    return COLORS[code] ?? "#6B7280";
}
