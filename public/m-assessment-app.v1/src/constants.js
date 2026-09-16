// ─── Design Tokens ────────────────────────────────────────────────────────────
export const T = {
    // Backgrounds
    bg: "#F4F6FB",
    card: "#FFFFFF",
    cardHover: "#FAFAFF",

    // Primary palette — baby purple / indigo
    primary: "#6C5CE7",
    primaryLight: "#A29BFE",
    primaryDark: "#4A3DC7",
    primaryGhost: "rgba(108,92,231,0.08)",
    primaryGlow: "rgba(108,92,231,0.18)",

    // Accent — sky blue
    accent: "#0EA5E9",
    accentLight: "#7DD3FC",
    accentGhost: "rgba(14,165,233,0.08)",

    // Success — teal green
    success: "#10B981",
    successLight: "#6EE7B7",
    successGhost: "rgba(16,185,129,0.08)",

    // Text hierarchy
    text: "#1A1A2E",
    textMid: "#3D3D5C",
    textSub: "#6B7194",
    textMuted: "#A0A3BD",

    // Borders
    border: "#E2E4F0",
    borderLight: "#F0F1F8",

    // Radii
    radius: 18,
    radiusSm: 12,
    radiusXs: 8,

    // Shadows
    shadow: "0 2px 16px rgba(108,92,231,0.06)",
    shadowMd: "0 8px 32px rgba(108,92,231,0.10)",
    shadowLg: "0 12px 48px rgba(108,92,231,0.14)",
    shadowCard: "0 1px 3px rgba(0,0,0,0.04), 0 4px 16px rgba(108,92,231,0.06)",

    // Gradients
    gradientPrimary: "linear-gradient(135deg, #6C5CE7 0%, #A29BFE 100%)",
    gradientHero: "linear-gradient(160deg, #064E3B 0%, #059669 50%, #34D399 100%)",
    gradientSky: "linear-gradient(135deg, #0EA5E9 0%, #7DD3FC 100%)",
    gradientSuccess: "linear-gradient(135deg, #059669 0%, #6EE7B7 100%)",
    gradientDark: "linear-gradient(160deg, #022C22 0%, #064E3B 50%, #059669 100%)",
    gradientGlass: "linear-gradient(135deg, rgba(255,255,255,0.95), rgba(255,255,255,0.8))",
    gradientWarm: "linear-gradient(135deg, #F59E0B 0%, #FBBF24 100%)",
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
