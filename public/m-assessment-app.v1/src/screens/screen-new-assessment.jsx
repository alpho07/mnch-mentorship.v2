import { useState, useEffect, useRef, useMemo } from "react";
import { T } from "../constants.js";
import api from "../services/api.service.js";

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

function todayStr() {
    const d = new Date();
    const yyyy = d.getFullYear();
    const mm = String(d.getMonth() + 1).padStart(2, "0");
    const dd = String(d.getDate()).padStart(2, "0");
    return `${yyyy}-${mm}-${dd}`;
}

const TYPES = [
    { value: "baseline", label: "Baseline" },
];

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

export function NewAssessmentSheet({ facilities, sections, user, onSubmit, onClose }) {
    const sectionCodes = useMemo(() => extractSectionCodes(sections), [sections]);

    const [mounted, setMounted] = useState(false);
    const [animDone, setAnimDone] = useState(false);
    useEffect(() => {
        const frame = requestAnimationFrame(() => setMounted(true));
        const timer = setTimeout(() => setAnimDone(true), 350);
        return () => {
            cancelAnimationFrame(frame);
            clearTimeout(timer);
        };
    }, []);

    useEffect(() => {
        if (!document.getElementById("mnch-spinner-style")) {
            const el = document.createElement("style");
            el.id = "mnch-spinner-style";
            el.textContent = SPINNER_STYLE;
            document.head.appendChild(el);
        }
    }, []);

    const noCache = !facilities || facilities.length === 0;

    // ── County selection ────────────────────────────────────────────────────────
    const counties = useMemo(() => {
        if (noCache) return [];
        const names = [...new Set(facilities.map(f => f.county).filter(Boolean))].sort();
        return names;
    }, [facilities, noCache]);

    const [selectedCounty, setSelectedCounty] = useState("");
    const [countyQuery, setCountyQuery] = useState("");
    const [countyFocused, setCountyFocused] = useState(false);

    const filteredCounties = useMemo(() => {
        const q = countyQuery.trim().toLowerCase();
        if (!q) return counties;
        return counties.filter(c => c.toLowerCase().includes(q));
    }, [counties, countyQuery]);

    const showCountyDropdown = countyFocused && !selectedCounty;

    function handleSelectCounty(county) {
        setSelectedCounty(county);
        setCountyQuery(county);
        setCountyFocused(false);
        // Reset facility when county changes
        setSelectedFacility(null);
        setFacilityQuery("");
    }

    function handleCountyInputChange(e) {
        setCountyQuery(e.target.value);
        if (selectedCounty) setSelectedCounty("");
    }

    // ── Facility selection (filtered by county) ─────────────────────────────────
    const [facilityQuery, setFacilityQuery] = useState("");
    const [selectedFacility, setSelectedFacility] = useState(null);
    const [inputFocused, setInputFocused] = useState(false);
    const inputRef = useRef(null);

    const facilityPool = useMemo(() => {
        if (noCache) return [];
        if (!selectedCounty) return [];
        return facilities.filter(f => f.county === selectedCounty);
    }, [facilities, selectedCounty, noCache]);

    const filteredFacilities = useMemo(() => {
        const q = facilityQuery.trim().toLowerCase();
        if (!q) return facilityPool;
        return facilityPool.filter(f =>
            (f.name ?? "").toLowerCase().includes(q) ||
            (f.mfl_code ?? "").toString().toLowerCase().includes(q) ||
            (f.subcounty ?? "").toLowerCase().includes(q)
        );
    }, [facilityPool, facilityQuery]);

    const showDropdown = inputFocused && !selectedFacility && !!selectedCounty;

    function handleSelectFacility(facility) {
        setSelectedFacility(facility);
        setFacilityQuery(facility.name);
        setInputFocused(false);
    }

    function handleFacilityInputChange(e) {
        setFacilityQuery(e.target.value);
        if (selectedFacility) setSelectedFacility(null);
    }

    const [assessmentType, setAssessmentType] = useState("baseline");

    const today = todayStr();
    const [assessmentDate, setAssessmentDate] = useState(today);

    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState(null);
    const [conflictData, setConflictData] = useState(null);
    const [fieldErrors, setFieldErrors] = useState({});

    const canSubmit =
        !noCache &&
        selectedCounty &&
        selectedFacility &&
        assessmentDate &&
        !submitting;

    async function handleSubmit() {
        if (!canSubmit) return;
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
            const data = await api.assessments.create(
                selectedFacility.id,
                assessmentType,
                assessmentDate,
                facilityMeta,
                user,
                sectionCodes,
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

    const inputStyle = {
        width: "100%",
        boxSizing: "border-box",
        borderRadius: 10,
        border: `1px solid ${T.border}`,
        padding: "10px 14px",
        fontSize: 14,
        color: T.text,
        outline: "none",
        background: "#fff",
    };

    return (
        <>
            <div
                onClick={animDone ? onClose : undefined}
                style={{
                    position: "fixed",
                    inset: 0,
                    background: "rgba(0,0,0,0.5)",
                    zIndex: 1000,
                }}
            />

            <div
                style={{
                    position: "fixed",
                    bottom: "calc(64px + env(safe-area-inset-bottom, 0px))",
                    left: 0,
                    right: 0,
                    background: "#fff",
                    borderRadius: "20px 20px 0 0",
                    zIndex: 1001,
                    transform: mounted ? "translateY(0)" : "translateY(100%)",
                    transition: "transform 300ms ease",
                    maxHeight: "80vh",
                    display: "flex",
                    flexDirection: "column",
                }}
            >
                <div style={{ display: "flex", justifyContent: "center", marginTop: 12, flexShrink: 0 }}>
                    <div style={{ width: 36, height: 4, background: "#E5E7EB", borderRadius: 99 }} />
                </div>

                <div style={{
                    display: "flex",
                    alignItems: "center",
                    justifyContent: "space-between",
                    padding: "12px 20px 0",
                    flexShrink: 0,
                }}>
                    <span style={{ fontSize: 16, fontWeight: 700, color: T.text }}>New Assessment</span>
                    <button
                        onClick={onClose}
                        style={{ background: "none", border: "none", fontSize: 22, color: T.textMuted, cursor: "pointer", lineHeight: 1, padding: "4px 6px" }}
                        aria-label="Close"
                    >×</button>
                </div>

                <div style={{ overflowY: "auto", maxHeight: "50vh", padding: "16px 20px 24px", flex: 1 }}>

                    {noCache ? (
                        <div style={{ background: "#FEF3C7", color: "#92400E", borderRadius: 8, padding: "10px 14px", fontSize: 13 }}>
                            Facilities not available offline. Please connect to load the facility list.
                        </div>
                    ) : (
                        <>
                            {/* Step 1 — County */}
                            <div style={{ marginBottom: 18 }}>
                                <label style={{ display: "block", fontSize: 13, fontWeight: 600, color: T.textMid, marginBottom: 6 }}>
                                    County
                                </label>
                                <div style={{ position: "relative" }}>
                                    <input
                                        type="text"
                                        placeholder="Search county…"
                                        value={countyQuery}
                                        onChange={handleCountyInputChange}
                                        onFocus={() => setCountyFocused(true)}
                                        onBlur={() => setTimeout(() => setCountyFocused(false), 150)}
                                        style={{
                                            ...inputStyle,
                                            border: `1px solid ${selectedCounty ? T.primary : T.border}`,
                                        }}
                                    />
                                    {selectedCounty && (
                                        <span style={{
                                            position: "absolute", right: 12, top: "50%", transform: "translateY(-50%)",
                                            fontSize: 14, color: T.primary, fontWeight: 700,
                                        }}>✓</span>
                                    )}
                                    {showCountyDropdown && (
                                        <div style={{
                                            position: "absolute", top: "calc(100% + 4px)", left: 0, right: 0,
                                            background: "#fff", border: `1px solid ${T.border}`, borderRadius: 10,
                                            boxShadow: T.shadowMd, maxHeight: 200, overflowY: "auto", zIndex: 10,
                                        }}>
                                            {filteredCounties.length === 0 ? (
                                                <div style={{ padding: "12px 14px", fontSize: 13, color: T.textMuted }}>No counties found</div>
                                            ) : (
                                                filteredCounties.map(county => (
                                                    <button
                                                        key={county}
                                                        onMouseDown={() => handleSelectCounty(county)}
                                                        style={{
                                                            display: "block", width: "100%", textAlign: "left",
                                                            background: "none", border: "none",
                                                            padding: "10px 14px", cursor: "pointer",
                                                            fontSize: 14, color: T.text, fontWeight: 500,
                                                            borderBottom: `1px solid ${T.borderLight}`,
                                                        }}
                                                    >
                                                        {county}
                                                    </button>
                                                ))
                                            )}
                                        </div>
                                    )}
                                </div>
                            </div>

                            {/* Step 2 — Facility (only after county selected) */}
                            <div style={{ marginBottom: 18, opacity: selectedCounty ? 1 : 0.4, transition: "opacity 0.2s" }}>
                                <label style={{ display: "block", fontSize: 13, fontWeight: 600, color: T.textMid, marginBottom: 6 }}>
                                    Facility {selectedCounty && <span style={{ color: T.textMuted, fontWeight: 400 }}>({facilityPool.length} in {selectedCounty})</span>}
                                </label>
                                <div style={{ position: "relative" }}>
                                    <input
                                        ref={inputRef}
                                        type="text"
                                        placeholder={selectedCounty ? "Search facility…" : "Select a county first"}
                                        value={facilityQuery}
                                        onChange={handleFacilityInputChange}
                                        onFocus={() => selectedCounty && setInputFocused(true)}
                                        onBlur={() => setTimeout(() => setInputFocused(false), 150)}
                                        disabled={!selectedCounty}
                                        style={{
                                            ...inputStyle,
                                            border: `1px solid ${fieldErrors.facility_id ? "#EF4444" : selectedFacility ? T.primary : T.border}`,
                                            background: selectedCounty ? "#fff" : T.borderLight,
                                            cursor: selectedCounty ? "text" : "not-allowed",
                                        }}
                                    />
                                    {selectedFacility && (
                                        <span style={{
                                            position: "absolute", right: 12, top: "50%", transform: "translateY(-50%)",
                                            fontSize: 14, color: T.primary, fontWeight: 700,
                                        }}>✓</span>
                                    )}
                                    {showDropdown && (
                                        <div style={{
                                            position: "absolute", top: "calc(100% + 4px)", left: 0, right: 0,
                                            background: "#fff", border: `1px solid ${T.border}`, borderRadius: 10,
                                            boxShadow: T.shadowMd, maxHeight: 220, overflowY: "auto", zIndex: 10,
                                        }}>
                                            {filteredFacilities.length === 0 ? (
                                                <div style={{ padding: "12px 14px", fontSize: 13, color: T.textMuted }}>
                                                    No facilities found{facilityQuery ? ` for "${facilityQuery}"` : ""}
                                                </div>
                                            ) : (
                                                filteredFacilities.slice(0, 60).map((facility) => (
                                                    <button
                                                        key={facility.id ?? facility.mfl_code}
                                                        onMouseDown={() => handleSelectFacility(facility)}
                                                        style={{
                                                            display: "block", width: "100%", textAlign: "left",
                                                            background: "none", border: "none",
                                                            padding: "10px 14px", cursor: "pointer",
                                                            borderBottom: `1px solid ${T.borderLight}`,
                                                        }}
                                                    >
                                                        <div style={{ fontSize: 14, fontWeight: 700, color: T.text }}>{facility.name}</div>
                                                        <div style={{ fontSize: 12, color: T.textMuted, marginTop: 2 }}>
                                                            MFL {facility.mfl_code ?? "—"} · {facility.subcounty ?? "—"}
                                                        </div>
                                                    </button>
                                                ))
                                            )}
                                        </div>
                                    )}
                                </div>
                                {fieldErrors.facility_id && (
                                    <div style={{ fontSize: 12, color: "#EF4444", marginTop: 4 }}>
                                        {fieldErrors.facility_id[0] ?? fieldErrors.facility_id}
                                    </div>
                                )}
                            </div>
                        </>
                    )}

                    {/* Assessment Type */}
                    <div style={{ marginBottom: 18 }}>
                        <label style={{ display: "block", fontSize: 13, fontWeight: 600, color: T.textMid, marginBottom: 6 }}>
                            Assessment Type
                        </label>
                        <div style={{ display: "flex", borderRadius: 10, background: "#F3F4F6", padding: 3 }}>
                            {TYPES.map((t) => {
                                const active = assessmentType === t.value;
                                return (
                                    <button
                                        key={t.value}
                                        onClick={() => setAssessmentType(t.value)}
                                        style={{
                                            flex: 1, borderRadius: 8, padding: "8px 0", fontSize: 13, fontWeight: 600,
                                            border: "none", cursor: "pointer",
                                            background: active ? T.gradientPrimary : "transparent",
                                            color: active ? "#fff" : T.textMuted,
                                            transition: "all 0.18s",
                                        }}
                                    >
                                        {t.label}
                                    </button>
                                );
                            })}
                        </div>
                        {fieldErrors.assessment_type && (
                            <div style={{ fontSize: 12, color: "#EF4444", marginTop: 4 }}>
                                {fieldErrors.assessment_type[0] ?? fieldErrors.assessment_type}
                            </div>
                        )}
                    </div>

                    {/* Assessment Date */}
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
                                setAssessmentDate(val && val < today ? today : val);
                            }}
                            style={{
                                ...inputStyle,
                                border: `1px solid ${fieldErrors.assessment_date ? "#EF4444" : T.border}`,
                            }}
                        />
                        {fieldErrors.assessment_date && (
                            <div style={{ fontSize: 12, color: "#EF4444", marginTop: 4 }}>
                                {fieldErrors.assessment_date[0] ?? fieldErrors.assessment_date}
                            </div>
                        )}
                    </div>
                </div>

                <div style={{
                    padding: "12px 20px 18px",
                    background: "#fff",
                    borderTop: `1px solid ${T.border}`,
                    boxShadow: "0 -4px 20px rgba(0,0,0,0.06)",
                    flexShrink: 0,
                }}>
                    {conflictData && (
                        <div style={{ marginBottom: 12, padding: "10px 14px", borderRadius: 8, background: "#FEF3C7", color: "#92400E", fontSize: 13 }}>
                            An assessment already exists for this facility, type, and date.{" "}
                            <button onClick={handleOpenExisting} style={{ background: "none", border: "none", color: T.primary, fontWeight: 700, fontSize: 13, cursor: "pointer", padding: 0, textDecoration: "underline" }}>
                                Open Existing
                            </button>
                        </div>
                    )}

                    {error && !conflictData && (
                        <div style={{ marginBottom: 12, padding: "10px 14px", borderRadius: 8, background: "#FEE2E2", color: "#991B1B", fontSize: 13 }}>
                            {error}
                        </div>
                    )}

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
                </div>
            </div>
        </>
    );
}
