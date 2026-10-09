// ─── Design Tokens — Teal Wellness (Enterprise Healthcare) ───────────────────
export const T = {
    // Backgrounds
    bg: "#F0F9FA",
    card: "#FFFFFF",
    cardHover: "#F7FDFD",

    // Primary palette — clinical teal
    primary: "#0097A7",
    primaryLight: "#26C6DA",
    primaryDark: "#00565A",
    primaryGhost: "rgba(0,151,167,0.08)",
    primaryGlow: "rgba(0,151,167,0.18)",

    // Accent — sky blue
    accent: "#0EA5E9",
    accentLight: "#7DD3FC",
    accentGhost: "rgba(14,165,233,0.08)",

    // Success — health green
    success: "#10B981",
    successLight: "#6EE7B7",
    successGhost: "rgba(16,185,129,0.08)",

    // Text hierarchy
    text: "#1A3A3A",
    textMid: "#2A4A4A",
    textSub: "#4A8080",
    textMuted: "#8BC8C8",

    // Borders
    border: "#B2EBF2",
    borderLight: "#E0F2F1",

    // Radii
    radius: 18,
    radiusSm: 12,
    radiusXs: 8,

    // Shadows
    shadow: "0 2px 16px rgba(0,151,167,0.06)",
    shadowMd: "0 8px 32px rgba(0,151,167,0.10)",
    shadowLg: "0 12px 48px rgba(0,151,167,0.14)",
    shadowCard: "0 1px 3px rgba(0,0,0,0.04), 0 4px 16px rgba(0,151,167,0.06)",

    // Gradients
    gradientPrimary: "linear-gradient(135deg, #0097A7 0%, #26C6DA 100%)",
    gradientHero:    "linear-gradient(160deg, #00565A 0%, #0097A7 55%, #26C6DA 100%)",
    gradientSky:     "linear-gradient(135deg, #0EA5E9 0%, #7DD3FC 100%)",
    gradientSuccess: "linear-gradient(135deg, #059669 0%, #6EE7B7 100%)",
    gradientDark:    "linear-gradient(160deg, #00565A 0%, #0097A7 55%, #26C6DA 100%)",
    gradientGlass:   "linear-gradient(135deg, rgba(255,255,255,0.95), rgba(255,255,255,0.8))",
    gradientWarm:    "linear-gradient(135deg, #F59E0B 0%, #FBBF24 100%)",
};

// ─── Stacking order ──────────────────────────────────────────────────────────
// (Same values the deployed bundle already uses; the export was missing from source.)
export const Z = { navBar: 40, fab: 45, header: 100, sheet: 200, toast: 300 };

// ─── Grade System ─────────────────────────────────────────────────────────────
export const GRADE_COLOR = { green: "#10B981", yellow: "#F59E0B", red: "#EF4444" };
export const GRADE_BG = { green: "#D1FAE5", yellow: "#FEF3C7", red: "#FEE2E2" };
export const GRADE_TEXT = { green: "#065F46", yellow: "#92400E", red: "#991B1B" };
export const GRADE_LABEL = { green: "Good", yellow: "Fair", red: "Poor" };

export function calcGrade(pct) {
    if (pct >= 80) return "green";
    if (pct >= 50) return "yellow";
    return "red";
}

// ─── Assessment Sections (static config — icons/colors only) ─────────────────
// Questions and section metadata come from the API: GET /api/v1/sections/schema/full
export const SECTION_META = {
    infrastructure: { icon: "🏗️", gradient: ["#8B5CF6", "#7C3AED"] },
    skills_lab: { icon: "🔬", gradient: ["#10B981", "#059669"] },
    human_resources: { icon: "👥", gradient: ["#F59E0B", "#D97706"] },
    health_products: { icon: "💊", gradient: ["#EF4444", "#DC2626"] },
    information_systems: { icon: "💻", gradient: ["#06B6D4", "#0891B2"] },
    quality_of_care: { icon: "⭐", gradient: ["#EC4899", "#DB2777"] },
};

// ─── Template-aware section helpers ──────────────────────────────────────────
// Templates name their sections differently (template 2's commodity matrix is
// "department_health_products"), so the UI dispatches on the schema's `kind`
// — "question_group" | "human_resources" | "commodity_matrix" — not on codes.
// Older cached schemas have no `kind`; fall back to the two legacy codes.
export function sectionKind(section) {
    if (section?.kind) return section.kind;
    if (section?.code === "human_resources") return "human_resources";
    if (section?.code === "health_products") return "commodity_matrix";
    return "question_group";
}
export const isSpecialSection = (section) => sectionKind(section) !== "question_group";

// ─── Rounds ──────────────────────────────────────────────────────────────────
export const ROUND_OPTIONS = [
    { value: "baseline", label: "Baseline" },
    { value: "midline", label: "Midline" },
    { value: "endline", label: "Endline" },
    { value: "other", label: "Other" },
];

export function roundLabel(assessment) {
    const round = assessment?.round ?? assessment?.assessment_type;
    if (!round) return "";
    if (round === "other") return assessment?.round_label || "Other";
    return round.charAt(0).toUpperCase() + round.slice(1);
}

// ─── Overall score ───────────────────────────────────────────────────────────
// Original template: the app has always shown (sum of the 4 scored sections ÷ 4)
// to match the server report. Any other template has its own section set, so
// trust the percentage the server computed.
const LEGACY_SCORED = ["infrastructure", "skills_lab", "information_systems", "quality_of_care"];
export function overallPercent(assessment) {
    const legacy = !assessment?.template || assessment.template.code === "STANDARD_FACILITY_ASSESSMENT";
    if (legacy) {
        const ss = assessment?.section_scores ?? {};
        const vals = LEGACY_SCORED.map((c) => {
            const n = Number(ss[c]?.percentage);
            return isNaN(n) || ss[c]?.percentage == null ? null : n;
        }).filter((v) => v !== null);
        if (vals.length) return vals.reduce((a, b) => a + b, 0) / 4;
    }
    const n = Number(assessment?.overall_percentage);
    return assessment?.overall_percentage == null || isNaN(n) ? null : n;
}

// ─── Helpers ──────────────────────────────────────────────────────────────────
export function generateLocalId() {
    return "MT-" + Math.random().toString(36).substring(2, 8).toUpperCase();
}

// A multi_select answer is stored as a JSON-encoded array; decode it so every
// operator behaves the same as the server's ConditionalLogicEvaluator.
function normalizeAnswer(v) {
    if (typeof v === "string" && v.trim().startsWith("[")) {
        try {
            const d = JSON.parse(v);
            if (Array.isArray(d)) return d;
        } catch { /* not JSON — keep the string */ }
    }
    return v;
}

const isBlank = (v) => v === undefined || v === null || v === "" || (Array.isArray(v) && v.length === 0);

/**
 * Evaluate one condition { question_code, value, operator? } against the
 * answers. Mirrors App\Services\ConditionalLogicEvaluator::evaluateCondition.
 */
function evalCondition(condition, responses) {
    const { question_code, value, operator = "equals" } = condition;
    if (!question_code) return false;
    const actual = normalizeAnswer(responses[question_code]);
    switch (operator) {
        case "equals": return actual === value;
        case "not_equals": return actual !== value;
        case "in": return Array.isArray(value) && value.includes(actual);
        case "not_in": return Array.isArray(value) && !value.includes(actual);
        case "intersects": return Array.isArray(actual) && Array.isArray(value) && actual.some((a) => value.includes(a));
        case "greater_than": return !isNaN(Number(actual)) && !isBlank(actual) && Number(actual) > Number(value);
        case "less_than": return !isNaN(Number(actual)) && !isBlank(actual) && Number(actual) < Number(value);
        default: return false;
    }
}

/**
 * Evaluate a display_conditions tree against the current answers. Shared by
 * questions and sections. Same shapes and same fail-closed behaviour as the
 * server (ConditionalLogicEvaluator::isVisible):
 *
 *  - { operator: "or"|"and", conditions: [{ question_code, value, operator? }, ...] }
 *  - { question_code, value, operator? }  (single, hidden until the parent is answered)
 *  - { show_if: { question_code, value } } (legacy)
 *  - empty / absent → always visible; unrecognised non-empty shape → hidden
 */
export function evaluateConditions(logic, responses) {
    if (!logic || (typeof logic === "object" && Object.keys(logic).length === 0)) return true;

    if (logic.operator === "or") return (logic.conditions ?? []).some((c) => evalCondition(c, responses));
    if (logic.operator === "and") return (logic.conditions ?? []).every((c) => evalCondition(c, responses));

    if (logic.question_code) {
        if (isBlank(normalizeAnswer(responses[logic.question_code]))) return false;
        return evalCondition(logic, responses);
    }

    if (logic.show_if) {
        const { question_code, value } = logic.show_if;
        if (!question_code) return true;
        const actual = responses[question_code];
        if (isBlank(actual)) return false;
        return actual === value;
    }

    return false;
}

export function isQuestionVisible(question, responses) {
    return evaluateConditions(question.conditional_logic || question.display_conditions, responses);
}

/** Sections can be conditional too (e.g. a module only for facilities with a given service). */
export function isSectionVisible(section, responses) {
    return evaluateConditions(section.display_conditions, responses);
}

export function getSectionCompletion(questions, responses) {
    const required = questions.filter(
        (q) => q.is_required && q.question_type !== "heading" && isQuestionVisible(q, responses)
    );
    const answered = required.filter((q) => {
        const v = responses[q.question_code];
        return v !== undefined && v !== "" && v !== null;
    });
    return { total: required.length, answered: answered.length };
}

// ─── Role sets ────────────────────────────────────────────────────────────────
export const MENTOR_ROLES = new Set([
    'facility_mentor', 'spoke_mentor', 'spoke_mentor_lead',
    'county_mentor_lead', 'subcounty_mentor_lead', 'facility_mentor_lead',
    'mentor_lead',
]);
export const MENTEE_ROLES = new Set(['mentee']);
export const ASSESSOR_ROLES = new Set(['Assessor']);
export const ADMIN_ROLES = new Set(['super_admin', 'admin', 'division', 'national']);

/**
 * Compute the dynamic tab list from an array of role name strings.
 * Returns { tabs, showFab }.
 */
export function computeTabs(roles = []) {
    const roleSet = new Set(roles);
    const isMentor   = [...MENTOR_ROLES].some(r => roleSet.has(r));
    const isMentee   = [...MENTEE_ROLES].some(r => roleSet.has(r));
    const isAssessor = [...ASSESSOR_ROLES].some(r => roleSet.has(r));
    const isAdmin    = [...ADMIN_ROLES].some(r => roleSet.has(r));

    const tabs = [{ key: 'dashboard', label: 'Home', iconKey: 'dashboard' }];

    if (isAssessor || isAdmin) {
        tabs.push({ key: 'assessments', label: 'Assessments', iconKey: 'assessments' });
    }
    if (isMentor || isAdmin) {
        tabs.push({ key: 'mentorship', label: 'Mentorship', iconKey: 'mentorship' });
    }
    if (isMentee || isAdmin) {
        tabs.push({ key: 'myClasses', label: 'My Classes', iconKey: 'myClasses' });
    }
    if (isAdmin || isMentee) {
        tabs.push({ key: 'trainings', label: 'Trainings', iconKey: 'trainings' });
    }
    if (isAssessor || isAdmin) {
        tabs.push({ key: 'reports', label: 'Reports', iconKey: 'reports' });
    }

    tabs.push({ key: 'profile', label: 'Profile', iconKey: 'profile' });

    // FAB only for assessor-only users (not mentors/mentees)
    const showFab = isAssessor && !isMentor && !isMentee;

    return { tabs, showFab };
}

// ─── Mentor/Mentee metadata ───────────────────────────────────────────────────
export const MENTOR_META = {
    icon: '🎓',
    gradient: ['#10B981', '#059669'],
};
export const MENTEE_META = {
    icon: '📚',
    gradient: ['#0EA5E9', '#0369A1'],
};

/**
 * Payload for POST /responses: answers for visible questions, plus an empty
 * string for any hidden question that still holds an old answer, so a stale
 * answer can't keep counting once its condition no longer applies.
 */
export function buildSectionPayload(section, responses, explanations) {
    const answers = {};
    const notes = {};
    (section.questions ?? []).forEach((q) => {
        if (q.question_type === "heading") return;
        const code = q.question_code;
        const v = responses[code];
        const has = v !== undefined && v !== null && v !== "";
        if (!isQuestionVisible(q, responses)) {
            if (has) answers[code] = "";
            return;
        }
        if (has) answers[code] = v;
        if (explanations[code]) notes[code] = explanations[code];
    });
    return { answers, notes };
}
