import { useState, useEffect, useRef, useMemo } from "react";
import { T, ROUND_OPTIONS } from "../constants.js";
import api from "../services/api.service.js";

// ── Spinner (inline CSS animation) ────────────────────────────────────────────
const SPINNER_STYLE = `
@keyframes mnch-spin {
  from { transform: rotate(0deg); }
  to   { transform: rotate(360deg); }
}
@keyframes mnch-slideUp {
  from { transform: translateY(100%); }
  to   { transform: translateY(0); }
}
`;

function Spinner() {
    return (
        <span style={{
            display: "inline-block",
            width: 14,
            height: 14,
            border: "2px solid rgba(255,255,255,0.35)",
            borderTopColor: "#fff",
            borderRadius: "50%",
            animation: "mnch-spin 0.7s linear infinite",
            marginRight: 8,
            verticalAlign: "middle",
        }} />
    );
}

// ── Today in YYYY-MM-DD ────────────────────────────────────────────────────────
function todayStr() {
    const d = new Date();
    const yyyy = d.getFullYear();
    const mm = String(d.getMonth() + 1).padStart(2, "0");
    const dd = String(d.getDate()).padStart(2, "0");
    return `${yyyy}-${mm}-${dd}`;
}

// ── Extract unique section codes from schema ──────────────────────────────────
// sections can be:
//   - an array of section objects  [{ code, name, questions, ... }, ...]
//   - an object keyed by code      { infrastructure: { ... }, ... }
function extractSectionCodes(schemaSections) {
    if (!schemaSections) return [];
    if (Array.isArray(schemaSections)) {
        return [...new Set(schemaSections.map((s) => s.code).filter(Boolean))];
    }
    if (typeof schemaSections === "object") {
        return Object.keys(schemaSections);
    }
    return [];
}

// ─────────────────────────────────────────────────────────────────────────────
export function NewAssessmentSheet({ facilities, templates, schemas, user, onSubmit, onClose }) {
    // ── Template (which assessment to start) ───────────────────────────────────
    const templateList = templates?.data ?? [];
    const [templateId, setTemplateId] = useState(templates?.default_template_id ?? templateList[0]?.id ?? null);
    const template = templateList.find((t) => t.id === templateId) ?? null;

    // The chosen template's sections — needed offline to build the provisional
    // assessment's progress map. Use the shared cache, else fetch it.
    const [fetchedSchema, setFetchedSchema] = useState({});
    useEffect(() => {
        if (!templateId || schemas?.[templateId] || fetchedSchema[templateId]) return;
        api.templates.schema(templateId)
            .then((arr) => setFetchedSchema((prev) => ({ ...prev, [templateId]: arr })))
            .catch(() => {});
    }, [templateId, schemas, fetchedSchema]);
    const sectionCodes = useMemo(
        () => extractSectionCodes(schemas?.[templateId] ?? fetchedSchema[templateId]),
        [schemas, fetchedSchema, templateId]
    );

    // ── Slide-up animation ─────────────────────────────────────────────────────
    const [mounted, setMounted] = useState(false);
    const [animDone, setAnimDone] = useState(false);
    useEffect(() => {
        // Trigger on next tick so CSS transition fires
        const frame = requestAnimationFrame(() => setMounted(true));
        // Guard backdrop dismissal until animation finishes (slightly > 300ms transition)
        const timer = setTimeout(() => setAnimDone(true), 350);
        return () => { cancelAnimationFrame(frame); clearTimeout(timer); };
    }, []);

    // ── Inject spinner styles once into <head> ─────────────────────────────────
    useEffect(() => {
        if (!document.getElementById('mnch-spinner-style')) {
            const el = document.createElement('style');
            el.id = 'mnch-spinner-style';
            el.textContent = SPINNER_STYLE;
            document.head.appendChild(el);
        }
    }, []);

    // ── Facility search ────────────────────────────────────────────────────────
    const [facilityQuery, setFacilityQuery]       = useState("");
    const [selectedFacility, setSelectedFacility] = useState(null);
    const [inputFocused, setInputFocused]         = useState(false);
    const inputRef = useRef(null);

    const noCache = !facilities || facilities.length === 0;

    const filteredFacilities = noCache
        ? []
        : facilities.filter((f) => {
              const q = facilityQuery.trim().toLowerCase();
              if (!q) return true;   // empty query → show all (browseable list)
              return (
                  (f.name      ?? "").toLowerCase().includes(q) ||
                  (f.mfl_code  ?? "").toString().toLowerCase().includes(q) ||
                  (f.county    ?? "").toLowerCase().includes(q) ||
                  (f.subcounty ?? "").toLowerCase().includes(q)
              );
          });

    const showDropdown =
        inputFocused &&
        !selectedFacility;

    function handleSelectFacility(facility) {
        setSelectedFacility(facility);
        setFacilityQuery(facility.name);
        setInputFocused(false);
    }

    function handleFacilityInputChange(e) {
        setFacilityQuery(e.target.value);
        if (selectedFacility) setSelectedFacility(null);
    }

    // ── Round (baseline / midline / endline / other + label) ───────────────────
    const [round, setRound] = useState("baseline");
    const [roundLabelText, setRoundLabelText] = useState("");

    // ── Assessment date ────────────────────────────────────────────────────────
    const today = todayStr();
    const [assessmentDate, setAssessmentDate] = useState(today);

    // ── Submit state ───────────────────────────────────────────────────────────
    const [submitting, setSubmitting]   = useState(false);
    const [error, setError]             = useState(null);         // generic error string
    const [conflictData, setConflictData] = useState(null);       // 409 payload
    const [fieldErrors, setFieldErrors] = useState({});           // 422 errors

    const canSubmit =
        !noCache &&
        selectedFacility &&
        templateId &&
        (round !== "other" || roundLabelText.trim()) &&
        assessmentDate &&
        !submitting;

    async function handleSubmit() {
        if (!canSubmit) return;
        // Final guard — reject past dates regardless of how the value was set
        if (assessmentDate < today) {
            setAssessmentDate(today);
            return;
        }
        setSubmitting(true);
        setError(null);
        setConflictData(null);
        setFieldErrors({});

        try {
            const facilityMeta = {
                name: selectedFacility.name,
                mfl_code: selectedFacility.mfl_code,
                subcounty: selectedFacility.subcounty,
                county: selectedFacility.county,
            };
            // The second argument is offline-only — api.service.js uses it to build a
            // provisional assessment when there's no network connection.
            const data = await api.assessments.create(
                {
                    facility_id: selectedFacility.id,
                    assessment_type_id: templateId,
                    round,
                    round_label: round === "other" ? roundLabelText.trim() : null,
                    assessment_date: assessmentDate,
                },
                {
                    facilityMeta,
                    user,
                    sectionCodes,
                    template: template && {
                        id: template.id, name: template.name, code: template.code,
                        version: template.version, is_active: true, is_retired: false,
                    },
                },
            );
            const assessment = data?.assessment ?? data?.data ?? data;
            onSubmit(assessment);
        } catch (e) {
            if (e.status === 409) {
                setConflictData(e.data);
            } else if (e.status === 422) {
                setFieldErrors(e.errors ?? {});
                setError(e.message ?? "Validation failed. Please check the fields.");
            } else {
                setError(e.message ?? "Something went wrong. Please try again.");
            }
        } finally {
            setSubmitting(false);
        }
    }

    function handleOpenExisting() {
        if (!conflictData) return;
        const existing = conflictData?.assessment ?? conflictData?.data ?? conflictData;
        if (!existing?.id) {
            setError("Could not open the existing assessment. Please search for it manually.");
            return;
        }
        onSubmit(existing);
    }

    // ─────────────────────────────────────────────────────────────────────────
    return (
        <>
            {/* Backdrop */}
            <div
                onClick={animDone ? onClose : undefined}
                style={{
                    position: "fixed",
                    inset: 0,
                    background: "rgba(0,0,0,0.5)",
                    zIndex: 1000,
                }}
            />

            {/* Sheet */}
            <div
                style={{
                    position: "fixed",
                    bottom: 0,
                    left: 0,
                    right: 0,
                    background: "#fff",
                    borderRadius: "20px 20px 0 0",
                    zIndex: 1001,
                    transform: mounted ? "translateY(0)" : "translateY(100%)",
                    transition: "transform 300ms ease",
                    maxHeight: "90vh",
                    display: "flex",
                    flexDirection: "column",
                }}
            >
                {/* Drag handle */}
                <div style={{ display: "flex", justifyContent: "center", marginTop: 12, flexShrink: 0 }}>
                    <div style={{
                        width: 36,
                        height: 4,
                        background: "#E5E7EB",
                        borderRadius: 99,
                    }} />
                </div>

                {/* Header */}
                <div style={{
                    display: "flex",
                    alignItems: "center",
                    justifyContent: "space-between",
                    padding: "12px 20px 0",
                    flexShrink: 0,
                }}>
                    <span style={{ fontSize: 16, fontWeight: 700, color: T.text }}>
                        New Assessment
                    </span>
                    <button
                        onClick={onClose}
                        style={{
                            background: "none",
                            border: "none",
                            fontSize: 22,
                            color: T.textMuted,
                            cursor: "pointer",
                            lineHeight: 1,
                            padding: "4px 6px",
                        }}
                        aria-label="Close"
                    >
                        ×
                    </button>
                </div>

                {/* Scrollable content */}
                <div style={{
                    overflowY: "auto",
                    maxHeight: "50vh",
                    padding: "16px 20px 24px",
                    flex: 1,
                }}>

                    {/* ── Facility picker ──────────────────────────────────────── */}
                    <div style={{ marginBottom: 18 }}>
                        <label style={{ display: "block", fontSize: 13, fontWeight: 600, color: T.textMid, marginBottom: 6 }}>
                            Facility
                        </label>

                        {noCache ? (
                            <div style={{
                                background: "#FEF3C7",
                                color: "#92400E",
                                borderRadius: 8,
                                padding: "10px 14px",
                                fontSize: 13,
                            }}>
                                Facilities not available offline. Please connect to load the facility list.
                            </div>
                        ) : (
                            <div style={{ position: "relative" }}>
                                <input
                                    ref={inputRef}
                                    type="text"
                                    placeholder="Search facility..."
                                    value={facilityQuery}
                                    onChange={handleFacilityInputChange}
                                    onFocus={() => setInputFocused(true)}
                                    onBlur={() => {
                                        // Delay so click on list item fires first
                                        setTimeout(() => setInputFocused(false), 150);
                                    }}
                                    style={{
                                        width: "100%",
                                        boxSizing: "border-box",
                                        borderRadius: 10,
                                        border: `1px solid ${fieldErrors.facility_id ? "#EF4444" : T.border}`,
                                        padding: "10px 14px",
                                        fontSize: 14,
                                        color: T.text,
                                        outline: "none",
                                        background: "#fff",
                                    }}
                                />

                                {showDropdown && (
                                    <div style={{
                                        position: "absolute",
                                        top: "calc(100% + 4px)",
                                        left: 0,
                                        right: 0,
                                        background: "#fff",
                                        border: `1px solid ${T.border}`,
                                        borderRadius: 10,
                                        boxShadow: T.shadowMd,
                                        maxHeight: 220,
                                        overflowY: "auto",
                                        zIndex: 10,
                                    }}>
                                        {filteredFacilities.length === 0 ? (
                                            <div style={{ padding: "12px 14px", fontSize: 13, color: T.textMuted }}>
                                                No facilities found for &ldquo;{facilityQuery}&rdquo;
                                            </div>
                                        ) : (
                                            filteredFacilities.slice(0, 60).map((facility) => (
                                                <button
                                                    key={facility.id ?? facility.mfl_code}
                                                    onMouseDown={() => handleSelectFacility(facility)}
                                                    style={{
                                                        display: "block",
                                                        width: "100%",
                                                        textAlign: "left",
                                                        background: "none",
                                                        border: "none",
                                                        padding: "10px 14px",
                                                        cursor: "pointer",
                                                        borderBottom: `1px solid ${T.borderLight}`,
                                                    }}
                                                >
                                                    <div style={{ fontSize: 14, fontWeight: 700, color: T.text }}>
                                                        {facility.name}
                                                    </div>
                                                    <div style={{ fontSize: 12, color: T.textMuted, marginTop: 2 }}>
                                                        MFL {facility.mfl_code ?? "—"} · {facility.subcounty ?? "—"} · {facility.county ?? "—"}
                                                    </div>
                                                </button>
                                            ))
                                        )}
                                    </div>
                                )}
                            </div>
                        )}

                        {fieldErrors.facility_id && (
                            <div style={{ fontSize: 12, color: "#EF4444", marginTop: 4 }}>
                                {fieldErrors.facility_id[0] ?? fieldErrors.facility_id}
                            </div>
                        )}
                    </div>

                    {/* ── Assessment template ──────────────────────────────────── */}
                    <div style={{ marginBottom: 18 }}>
                        <label style={{ display: "block", fontSize: 13, fontWeight: 600, color: T.textMid, marginBottom: 6 }}>
                            Assessment
                        </label>
                        {templateList.length === 0 ? (
                            <div style={{ background: "#FEF3C7", color: "#92400E", borderRadius: 8, padding: "10px 14px", fontSize: 13 }}>
                                Assessment templates not available offline. Please connect once to load them.
                            </div>
                        ) : (
                            <div style={{ display: "grid", gap: 8 }}>
                                {templateList.map((t) => {
                                    const active = t.id === templateId;
                                    return (
                                        <button
                                            key={t.id}
                                            onClick={() => setTemplateId(t.id)}
                                            style={{
                                                textAlign: "left", borderRadius: 10, padding: "10px 14px", cursor: "pointer",
                                                border: `1.5px solid ${active ? T.primary : T.border}`,
                                                background: active ? `${T.primary}10` : "#fff",
                                            }}
                                        >
                                            <div style={{ fontSize: 14, fontWeight: 700, color: T.text }}>{t.name}</div>
                                            <div style={{ fontSize: 11, color: T.textMuted, marginTop: 2 }}>
                                                v{t.version}{t.category?.name ? ` · ${t.category.name}` : ""}{t.sections_count ? ` · ${t.sections_count} sections` : ""}
                                            </div>
                                        </button>
                                    );
                                })}
                            </div>
                        )}
                        {fieldErrors.assessment_type_id && (
                            <div style={{ fontSize: 12, color: "#EF4444", marginTop: 4 }}>
                                {fieldErrors.assessment_type_id[0] ?? fieldErrors.assessment_type_id}
                            </div>
                        )}
                    </div>

                    {/* ── Round ────────────────────────────────────────────────── */}
                    <div style={{ marginBottom: 18 }}>
                        <label style={{ display: "block", fontSize: 13, fontWeight: 600, color: T.textMid, marginBottom: 6 }}>
                            Round
                        </label>
                        <div style={{ display: "flex", borderRadius: 10, background: "#F3F4F6", padding: 3 }}>
                            {ROUND_OPTIONS.map((r) => {
                                const active = round === r.value;
                                return (
                                    <button
                                        key={r.value}
                                        onClick={() => setRound(r.value)}
                                        style={{
                                            flex: 1, borderRadius: 8, padding: "8px 0", fontSize: 12, fontWeight: 600,
                                            border: "none", cursor: "pointer", transition: "all 0.18s",
                                            background: active ? T.gradientPrimary : "transparent",
                                            color: active ? "#fff" : T.textMuted,
                                        }}
                                    >
                                        {r.label}
                                    </button>
                                );
                            })}
                        </div>
                        {round === "other" && (
                            <input
                                type="text"
                                maxLength={100}
                                placeholder='Specify round, e.g. "Post-COVID Re-assessment"'
                                value={roundLabelText}
                                onChange={(e) => setRoundLabelText(e.target.value)}
                                style={{
                                    width: "100%", boxSizing: "border-box", marginTop: 8, borderRadius: 10,
                                    border: `1px solid ${fieldErrors.round_label ? "#EF4444" : T.border}`,
                                    padding: "10px 14px", fontSize: 14, color: T.text, outline: "none", background: "#fff",
                                }}
                            />
                        )}
                        {(fieldErrors.round || fieldErrors.round_label) && (
                            <div style={{ fontSize: 12, color: "#EF4444", marginTop: 4 }}>
                                {[].concat(fieldErrors.round ?? fieldErrors.round_label)[0]}
                            </div>
                        )}
                    </div>

                    {/* ── Assessment date ──────────────────────────────────────── */}
                    <div style={{ marginBottom: 18 }}>
                        <label style={{ display: "block", fontSize: 13, fontWeight: 600, color: T.textMid, marginBottom: 6 }}>
                            Assessment Date
                        </label>
                        <input
                            type="date"
                            value={assessmentDate}
                            min={today}
                            onChange={(e) => {
                                const val = e.target.value;
                                // Reject past dates — min attr not enforced on all Android WebViews
                                setAssessmentDate(val && val < today ? today : val);
                            }}
                            style={{
                                width: "100%",
                                boxSizing: "border-box",
                                borderRadius: 10,
                                border: `1px solid ${fieldErrors.assessment_date ? "#EF4444" : T.border}`,
                                padding: "10px 14px",
                                fontSize: 14,
                                color: T.text,
                                outline: "none",
                                background: "#fff",
                            }}
                        />
                        {fieldErrors.assessment_date && (
                            <div style={{ fontSize: 12, color: "#EF4444", marginTop: 4 }}>
                                {fieldErrors.assessment_date[0] ?? fieldErrors.assessment_date}
                            </div>
                        )}
                    </div>

                    {/* ── Submit button ────────────────────────────────────────── */}
                    <button
                        onClick={handleSubmit}
                        disabled={!canSubmit}
                        style={{
                            width: "100%",
                            background: T.gradientPrimary,
                            color: "#fff",
                            fontSize: 14,
                            fontWeight: 700,
                            borderRadius: 12,
                            padding: "14px",
                            marginTop: 20,
                            border: "none",
                            cursor: canSubmit ? "pointer" : "not-allowed",
                            opacity: canSubmit ? 1 : 0.5,
                            display: "flex",
                            alignItems: "center",
                            justifyContent: "center",
                            transition: "opacity 0.2s",
                        }}
                    >
                        {submitting && <Spinner />}
                        {submitting ? "Creating..." : "Start Assessment"}
                    </button>

                    {/* ── 409 conflict error ───────────────────────────────────── */}
                    {conflictData && (
                        <div style={{
                            marginTop: 12,
                            padding: "10px 14px",
                            borderRadius: 8,
                            background: "#FEF3C7",
                            color: "#92400E",
                            fontSize: 13,
                        }}>
                            An assessment already exists for this facility, assessment and round.{" "}
                            <button
                                onClick={handleOpenExisting}
                                style={{
                                    background: "none",
                                    border: "none",
                                    color: T.primary,
                                    fontWeight: 700,
                                    fontSize: 13,
                                    cursor: "pointer",
                                    padding: 0,
                                    textDecoration: "underline",
                                }}
                            >
                                Open Existing
                            </button>
                        </div>
                    )}

                    {/* ── Generic / 422 error ──────────────────────────────────── */}
                    {error && !conflictData && (
                        <div style={{
                            marginTop: 12,
                            padding: "10px 14px",
                            borderRadius: 8,
                            background: "#FEE2E2",
                            color: "#991B1B",
                            fontSize: 13,
                        }}>
                            {error}
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}
