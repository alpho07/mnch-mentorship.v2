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

// ─── Helpers ──────────────────────────────────────────────────────────────────
export function generateLocalId() {
    return "MT-" + Math.random().toString(36).substring(2, 8).toUpperCase();
}

/**
 * Evaluate a single condition object:
 *   { question_code, value, operator? }
 * operator defaults to "equals" if not provided.
 */
function evalCondition(condition, responses) {
    const { question_code, value, operator = "equals" } = condition;
    if (!question_code) return false;
    const actual = responses[question_code];
    // If the parent has not been answered yet, hide the dependent question
    if (actual === undefined || actual === null || actual === "") return false;
    switch (operator) {
        case "equals": return actual === value;
        case "not_equals": return actual !== value;
        case "in": return Array.isArray(value) && value.includes(actual);
        case "not_in": return Array.isArray(value) && !value.includes(actual);
        case "greater_than": return Number(actual) > Number(value);
        case "less_than": return Number(actual) < Number(value);
        default: return false;
    }
}

/**
 * Determine whether a question should be visible given current responses.
 *
 * Handles all four formats stored in the DB:
 *
 *  1. conditional_logic with operator "or"
 *     { operator: "or", conditions: [{ question_code, value, operator? }, ...] }
 *
 *  2. conditional_logic with operator "and"
 *     { operator: "and", conditions: [{ question_code, value, operator? }, ...] }
 *
 *  3. conditional_logic single (legacy root-level)
 *     { question_code: "SL_FUNCTIONAL", value: "Yes", operator?: "equals" }
 *
 *  4. display_conditions (older legacy format, same shape as #3)
 *     { question_code: "SL_FUNCTIONAL", value: "Yes" }
 *
 * Priority: conditional_logic wins over display_conditions when both exist.
 */
export function isQuestionVisible(question, responses) {
    const logic = question.conditional_logic || question.display_conditions;

    // No condition at all → always visible
    if (!logic) return true;

    // OR group: show if ANY condition matches
    if (logic.operator === "or" && Array.isArray(logic.conditions)) {
        return logic.conditions.some((c) => evalCondition(c, responses));
    }

    // AND group: show only if ALL conditions match
    if (logic.operator === "and" && Array.isArray(logic.conditions)) {
        return logic.conditions.every((c) => evalCondition(c, responses));
    }

    // Single condition (root-level question_code)
    if (logic.question_code) {
        return evalCondition(logic, responses);
    }

    // Unknown format → show by default (fail open so nothing disappears unexpectedly)
    return true;
}

export function getSectionCompletion(questions, responses) {
    const required = questions.filter(
        (q) => q.is_required && isQuestionVisible(q, responses)
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
