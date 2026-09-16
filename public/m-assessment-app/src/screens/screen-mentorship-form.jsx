import { useState, useEffect, useRef } from "react";
import { T } from "../constants.js";
import api from "../services/api.service.js";

const inputStyle = {
    display: "block", width: "100%", padding: "11px 13px",
    borderRadius: T.radiusSm, border: `1px solid ${T.border}`,
    fontSize: 14, background: "#fff", color: T.text,
    fontFamily: "inherit", outline: "none", boxSizing: "border-box",
};

const selectStyle = {
    ...inputStyle,
    appearance: "none", WebkitAppearance: "none",
    backgroundImage: `url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236B7280' stroke-width='2.5'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E")`,
    backgroundRepeat: "no-repeat", backgroundPosition: "right 12px center",
    paddingRight: 36,
};

function Field({ label, required, hint, children }) {
    return (
        <div style={{ marginBottom: 16 }}>
            <label style={{ display: "block", fontSize: 12, fontWeight: 700, color: T.textSub, marginBottom: 6, textTransform: "uppercase", letterSpacing: 0.5 }}>
                {label}{required && <span style={{ color: "#EF4444", marginLeft: 2 }}>*</span>}
            </label>
            {children}
            {hint && <div style={{ fontSize: 11, color: T.textMuted, marginTop: 4 }}>{hint}</div>}
        </div>
    );
}

function SearchableDropdown({
    options,
    value,
    onChange,
    getLabel = (item) => item?.name ?? "",
    placeholder,
    searchPlaceholder = "Search...",
    disabled = false,
    emptyText = "No results found",
}) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState("");
    const selected = options.find(item => String(item.id) === String(value));
    const selectedLabel = selected ? getLabel(selected) : "";
    const filtered = options.filter(item =>
        getLabel(item).toLowerCase().includes(query.trim().toLowerCase())
    );

    const close = () => {
        setOpen(false);
        setQuery("");
    };

    return (
        <div
            style={{ position: "relative" }}
            onBlur={(e) => {
                if (!e.currentTarget.contains(e.relatedTarget)) {
                    setTimeout(close, 120);
                }
            }}
        >
            <button
                type="button"
                disabled={disabled}
                onClick={() => !disabled && setOpen(prev => !prev)}
                style={{
                    ...selectStyle,
                    textAlign: "left",
                    cursor: disabled ? "not-allowed" : "pointer",
                    opacity: disabled ? 0.5 : 1,
                    color: selectedLabel ? T.text : T.textMuted,
                    minHeight: 42,
                }}
            >
                <span style={{ display: "block", overflow: "hidden", textOverflow: "ellipsis", whiteSpace: "nowrap" }}>
                    {selectedLabel || placeholder}
                </span>
            </button>

            {open && (
                <div
                    style={{
                        position: "absolute",
                        left: 0,
                        right: 0,
                        top: "calc(100% + 4px)",
                        zIndex: 30,
                        background: "#fff",
                        border: `1px solid ${T.border}`,
                        borderRadius: T.radiusSm,
                        boxShadow: T.shadowMd,
                        overflow: "hidden",
                    }}
                >
                    <div style={{ padding: 8, borderBottom: `1px solid ${T.borderLight}` }}>
                        <input
                            autoFocus
                            value={query}
                            onChange={e => setQuery(e.target.value)}
                            placeholder={searchPlaceholder}
                            style={{ ...inputStyle, padding: "9px 11px" }}
                        />
                    </div>
                    <div style={{ maxHeight: 220, overflowY: "auto" }}>
                        {filtered.length === 0 && (
                            <div style={{ padding: "12px 14px", color: T.textMuted, fontSize: 13 }}>
                                {emptyText}
                            </div>
                        )}
                        {filtered.map(item => {
                            const itemValue = String(item.id);
                            const active = itemValue === String(value);
                            return (
                                <button
                                    key={item.id}
                                    type="button"
                                    onMouseDown={e => e.preventDefault()}
                                    onClick={() => {
                                        onChange(itemValue, item);
                                        close();
                                    }}
                                    style={{
                                        width: "100%",
                                        padding: "10px 12px",
                                        border: "none",
                                        borderBottom: `1px solid ${T.borderLight}`,
                                        background: active ? T.primaryGhost : "#fff",
                                        color: T.text,
                                        textAlign: "left",
                                        cursor: "pointer",
                                        fontSize: 14,
                                        fontFamily: "inherit",
                                    }}
                                >
                                    {getLabel(item)}
                                </button>
                            );
                        })}
                    </div>
                </div>
            )}
        </div>
    );
}

function getProgramTheme(name, i) {
    const n = (name ?? "").toLowerCase();
    if (n.includes("infant") || n.includes("child"))
        return { from: "#7DB83A", to: "#A8D84E", shadow: "rgba(125,184,58,.40)", emoji: "👶", tag: "Paediatric Care" };
    if (n.includes("newborn") || n.includes("neonat"))
        return { from: "#A855C8", to: "#CF8FE0", shadow: "rgba(168,85,200,.40)", emoji: "🤱", tag: "Neonatal Care" };
    if (n.includes("maternal") || n.includes("mother") || n.includes("safe"))
        return { from: "#B5006C", to: "#E91E8C", shadow: "rgba(181,0,108,.40)", emoji: "❤️", tag: "Maternal Health" };
    if (n.includes("nutrition") || n.includes("feeding"))
        return { from: "#E05A00", to: "#FF8C00", shadow: "rgba(224,90,0,.40)", emoji: "🌿", tag: "Nutrition" };
    const fb = [
        { from: "#C2185B", to: "#E91E63", shadow: "rgba(194,24,91,.35)", emoji: "❤️", tag: "Maternal Health" },
        { from: "#E65100", to: "#EF8C00", shadow: "rgba(230,81,0,.35)", emoji: "🌿", tag: "Nutrition" },
        { from: "#5E35B1", to: "#9C27B0", shadow: "rgba(94,53,177,.35)", emoji: "🧬", tag: "Programme" },
        { from: "#00838F", to: "#26C6DA", shadow: "rgba(0,131,143,.35)", emoji: "🩺", tag: "Clinical" },
    ];
    return fb[i % fb.length];
}

function ProgramPickerCards({ programs, value, onChange }) {
    if (!programs || programs.length === 0) {
        return (
            <div style={{ padding: "20px 0", textAlign: "center", color: T.textMuted, fontSize: 13 }}>
                No programmes configured yet.
            </div>
        );
    }
    return (
        <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 10 }}>
            {programs.map((p, i) => {
                const theme = getProgramTheme(p.name, i);
                const selected = String(p.id) === String(value);
                return (
                    <button
                        key={p.id}
                        type="button"
                        onClick={() => onChange(String(p.id))}
                        style={{
                            position: "relative",
                            background: `linear-gradient(150deg, ${theme.from} 0%, ${theme.to} 100%)`,
                            border: selected ? "2.5px solid rgba(255,255,255,0.9)" : "2px solid transparent",
                            borderRadius: 14,
                            padding: "12px 10px 10px",
                            cursor: "pointer",
                            display: "flex",
                            flexDirection: "column",
                            alignItems: "center",
                            gap: 2,
                            overflow: "hidden",
                            minHeight: 140,
                            transform: selected ? "translateY(-4px)" : "none",
                            boxShadow: selected
                                ? `0 10px 24px ${theme.shadow}, 0 3px 8px rgba(0,0,0,0.15)`
                                : "0 3px 10px rgba(0,0,0,0.18)",
                            transition: "transform 0.22s ease, box-shadow 0.22s ease, border-color 0.18s",
                            textAlign: "center",
                        }}
                    >
                        {/* dot-pattern watermark */}
                        <div style={{
                            position: "absolute", inset: 0, pointerEvents: "none",
                            backgroundImage: "radial-gradient(circle, rgba(255,255,255,0.18) 1.2px, transparent 1.2px)",
                            backgroundSize: "9px 9px",
                            maskImage: "radial-gradient(ellipse 75% 70% at 55% 80%, black 0%, transparent 70%)",
                            WebkitMaskImage: "radial-gradient(ellipse 75% 70% at 55% 80%, black 0%, transparent 70%)",
                        }} />
                        {/* MoH badge */}
                        <div style={{
                            display: "inline-flex", alignItems: "center", gap: 3,
                            background: "rgba(255,255,255,0.18)", border: "1px solid rgba(255,255,255,0.25)",
                            borderRadius: 99, padding: "2px 7px 2px 4px",
                            fontSize: 9, fontWeight: 700, letterSpacing: "0.06em",
                            textTransform: "uppercase", color: "rgba(255,255,255,0.92)",
                            marginBottom: 2, alignSelf: "flex-start",
                        }}>
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,0.9)" strokeWidth="1.8">
                                <path d="M12 2L3 7v5c0 5.25 3.75 10.15 9 11.35C17.25 22.15 21 17.25 21 12V7L12 2z"/>
                            </svg>
                            MoH
                        </div>
                        {/* checkmark */}
                        <div style={{
                            position: "absolute", top: 10, right: 10,
                            width: 20, height: 20, borderRadius: "50%",
                            background: selected ? "rgba(255,255,255,0.95)" : "rgba(255,255,255,0.2)",
                            border: `1.5px solid ${selected ? "rgba(255,255,255,1)" : "rgba(255,255,255,0.35)"}`,
                            display: "flex", alignItems: "center", justifyContent: "center",
                            transform: selected ? "scale(1)" : "scale(0.65)",
                            opacity: selected ? 1 : 0,
                            transition: "all 0.2s cubic-bezier(0.34,1.56,0.64,1)",
                        }}>
                            <svg width="10" height="10" viewBox="0 0 24 24" fill="none"
                                stroke={theme.from} strokeWidth="3">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                        </div>
                        {/* emoji */}
                        <div style={{ fontSize: 28, lineHeight: 1, margin: "4px 0 2px", filter: "drop-shadow(0 2px 4px rgba(0,0,0,0.2))" }}>
                            {theme.emoji}
                        </div>
                        {/* tag */}
                        <div style={{ fontSize: 9, fontWeight: 700, letterSpacing: "0.1em", textTransform: "uppercase", color: "rgba(255,255,255,0.65)", marginBottom: 2 }}>
                            {theme.tag}
                        </div>
                        {/* title */}
                        <div style={{ fontSize: 11, fontWeight: 900, color: "#fff", lineHeight: 1.25, letterSpacing: "0.01em", textShadow: "0 1px 4px rgba(0,0,0,0.25)" }}>
                            {p.name}
                        </div>
                        {/* module count */}
                        {p.module_count != null && (
                            <div style={{
                                marginTop: "auto", paddingTop: 6,
                                fontSize: 10, fontWeight: 700, textTransform: "uppercase", letterSpacing: "0.04em",
                                color: "rgba(255,255,255,0.85)",
                                background: "rgba(255,255,255,0.18)", border: "1px solid rgba(255,255,255,0.28)",
                                borderRadius: 99, padding: "3px 8px", display: "inline-block",
                            }}>
                                {p.module_count} module{p.module_count !== 1 ? "s" : ""}
                            </div>
                        )}
                    </button>
                );
            })}
        </div>
    );
}

export function MentorshipFormScreen({ user, onBack, onCreated, existingMentorship }) {
    const isEditMode = !!existingMentorship;
    const [step, setStep] = useState(1);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState(null);
    const [editLoading, setEditLoading] = useState(isEditMode);

    // ── Step 1: Setup ──────────────────────────────────────────────────────
    const [programs, setPrograms]         = useState([]);
    const [programId, setProgramId]       = useState("");
    const [isPilot, setIsPilot]           = useState(false);

    // County → Facility cascade (matches web form)
    const [counties, setCounties]           = useState([]);
    const [countyId, setCountyId]           = useState(user?.county_id ? String(user.county_id) : "");
    const [facilities, setFacilities]       = useState([]);
    const [allFacilities, setAllFacilities] = useState([]);
    const [cadres, setCadres]               = useState([]);
    const [departments, setDepartments]     = useState([]);
    const [facilityId, setFacilityId]       = useState(user?.facility_id ? String(user.facility_id) : "");
    const [facilityLabel, setFacilityLabel] = useState(user?.facility ?? "");
    const [facilitiesLoading, setFacilitiesLoading] = useState(false);
    const [facilitiesError, setFacilitiesError] = useState(null);

    const [startDate, setStartDate]           = useState("");
    const [endDate, setEndDate]               = useState("");
    const [maxParticipants, setMaxParticipants] = useState(20);
    const [className, setClassName]           = useState("Class 1");
    const [classStartDate, setClassStartDate] = useState("");
    const [classEndDate, setClassEndDate]     = useState("");
    const [classNotes, setClassNotes]         = useState("");

    // ── Step 2: Modules ────────────────────────────────────────────────────
    const [availableModules, setAvailableModules]   = useState([]);
    const [selectedModuleIds, setSelectedModuleIds] = useState([]);
    const [modulesLoading, setModulesLoading]       = useState(false);

    // ── Step 3: Mentees ────────────────────────────────────────────────────
    const [menteeSearch, setMenteeSearch]     = useState("");
    const [menteeResults, setMenteeResults]   = useState([]);
    const [selectedMentees, setSelectedMentees] = useState([]);
    const [menteeSearching, setMenteeSearching] = useState(false);
    const [showCreateMentee, setShowCreateMentee] = useState(false);
    const [newMenteeLookup, setNewMenteeLookup] = useState({ loading: false, user: null, checkedEmail: "" });
    const [newMentee, setNewMentee] = useState({
        first_name: "",
        middle_name: "",
        last_name: "",
        email: "",
        phone: "",
        cadre_id: "",
        department_id: "",
        facility_id: "",
    });
    const searchTimer = useRef(null);
    const emailLookupTimer = useRef(null);
    const selectedFacilityIdRef = useRef(facilityId);

    useEffect(() => {
        selectedFacilityIdRef.current = facilityId;
    }, [facilityId]);

    // ── Initial data load ──────────────────────────────────────────────────
    useEffect(() => {
        api.lookups.programs()
            .then(d => setPrograms(Array.isArray(d?.data) ? d.data : Array.isArray(d) ? d : []))
            .catch(() => {});
        api.lookups.counties()
            .then(d => setCounties(Array.isArray(d?.data) ? d.data : Array.isArray(d) ? d : []))
            .catch(() => {});
        api.lookups.cadres()
            .then(d => setCadres(Array.isArray(d?.data) ? d.data : Array.isArray(d) ? d : []))
            .catch((e) => setError(e?.message ?? "Failed to load cadres."));
        api.lookups.departments()
            .then(d => setDepartments(Array.isArray(d?.data) ? d.data : Array.isArray(d) ? d : []))
            .catch((e) => setError(e?.message ?? "Failed to load departments."));
        api.facilities.list()
            .then(list => setAllFacilities((Array.isArray(list?.data) ? list.data : Array.isArray(list) ? list : [])
                .map(f => ({
                    ...f,
                    label: f?.label || (f?.mfl_code ? `${f.mfl_code} - ${f.name}` : f?.name),
                }))
                .filter(f => f.id && f.name)))
            .catch(() => {});
    }, []);

    // Load facilities when county changes (matches web form behaviour)
    useEffect(() => {
        if (!countyId) { setFacilities([]); setFacilityId(""); setFacilityLabel(""); setFacilitiesError(null); return; }
        setFacilitiesLoading(true);
        setFacilitiesError(null);
        api.lookups.facilitiesByCounty(countyId)
            .then(list => {
                const arr = (Array.isArray(list?.data) ? list.data : Array.isArray(list) ? list : [])
                    .map(f => {
                        const mfl = f?.mfl_code ? String(f.mfl_code).trim() : "";
                        const name = f?.name ? String(f.name).trim() : "";
                        return {
                            ...f,
                            label: f?.label || (mfl ? `${mfl} - ${name}` : name),
                        };
                    })
                    .filter(f => f.id && f.name);
                setFacilities(arr);
                const currentMatch = arr.find(f => String(f.id) === String(selectedFacilityIdRef.current));
                if (currentMatch) {
                    setFacilityId(String(currentMatch.id));
                    setFacilityLabel(currentMatch.label);
                    return;
                }

                // Keep user's own facility pre-selected when county matches
                const match = arr.find(f => String(f.id) === String(user?.facility_id));
                if (match && String(countyId) === String(user?.county_id)) {
                    setFacilityId(String(match.id));
                    setFacilityLabel(match.label);
                } else if (arr.length === 1) {
                    setFacilityId(String(arr[0].id));
                    setFacilityLabel(arr[0].label);
                } else {
                    setFacilityId("");
                    setFacilityLabel("");
                }
            })
            .catch((e) => {
                setFacilities([]);
                setFacilityId("");
                setFacilityLabel("");
                setFacilitiesError(e?.message ?? "Facilities could not be loaded for this county.");
            })
            .finally(() => setFacilitiesLoading(false));
    }, [countyId]);

    // Load full mentorship data in edit mode to get IDs
    useEffect(() => {
        if (!isEditMode) return;
        api.mentorships.find(existingMentorship.id)
            .then(d => {
                const t = d?.data ?? d;
                if (t.program_id) setProgramId(String(t.program_id));
                if (t.county_id)  setCountyId(String(t.county_id));
                if (t.facility_id) setFacilityId(String(t.facility_id));
                if (t.start_date)  setStartDate(t.start_date);
                if (t.end_date)    setEndDate(t.end_date);
                if (t.max_participants) setMaxParticipants(t.max_participants);
                if (t.is_pilot !== undefined) setIsPilot(!!t.is_pilot);
            })
            .catch(() => {})
            .finally(() => setEditLoading(false));
    }, []);

    // Load modules when program changes (skip in edit mode — modules managed from class screens)
    useEffect(() => {
        if (!programId || isEditMode) { setAvailableModules([]); return; }
        setModulesLoading(true);
        api.lookups.programModules(programId)
            .then(d => setAvailableModules(Array.isArray(d?.data) ? d.data : Array.isArray(d) ? d : []))
            .catch(() => setAvailableModules([]))
            .finally(() => setModulesLoading(false));
    }, [programId]);

    // Debounced mentee search
    useEffect(() => {
        clearTimeout(searchTimer.current);
        if (menteeSearch.trim().length < 2) { setMenteeResults([]); return; }
        searchTimer.current = setTimeout(() => {
            setMenteeSearching(true);
            api.lookups.userSearch(menteeSearch.trim(), 20)
                .then(d => {
                    const list = Array.isArray(d?.data) ? d.data : Array.isArray(d) ? d : [];
                    setMenteeResults(list.filter(u => !selectedMentees.find(s => s.id === u.id)));
                })
                .catch(() => setMenteeResults([]))
                .finally(() => setMenteeSearching(false));
        }, 400);
        return () => clearTimeout(searchTimer.current);
    }, [menteeSearch]);

    useEffect(() => {
        clearTimeout(emailLookupTimer.current);
        const email = newMentee.email.trim();
        if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            setNewMenteeLookup({ loading: false, user: null, checkedEmail: "" });
            return;
        }
        emailLookupTimer.current = setTimeout(() => {
            setNewMenteeLookup({ loading: true, user: null, checkedEmail: email });
            (async () => {
                let found = null;
                try {
                    const res = await api.lookups.userByEmail(email);
                    found = res?.data ?? null;
                } catch {
                    found = null;
                }

                // Fallback: use global search and exact-match email.
                if (!found) {
                    try {
                        const res = await api.lookups.userSearch(email, 20);
                        const list = Array.isArray(res?.data) ? res.data : Array.isArray(res) ? res : [];
                        const exact = list.find(u => String(u.email ?? "").trim().toLowerCase() === email.toLowerCase());
                        found = exact ?? null;
                    } catch {
                        found = null;
                    }
                }

                setNewMenteeLookup({ loading: false, user: found, checkedEmail: email });
                if (found) {
                    setNewMentee(v => ({
                        ...v,
                        first_name: found.first_name ?? "",
                        middle_name: found.middle_name ?? "",
                        last_name: found.last_name ?? "",
                        phone: found.phone ?? "",
                        cadre_id: found.cadre_id ? String(found.cadre_id) : "",
                        department_id: found.department_id ? String(found.department_id) : "",
                        facility_id: found.facility_id ? String(found.facility_id) : "",
                    }));
                }
            })();
        }, 500);
        return () => clearTimeout(emailLookupTimer.current);
    }, [newMentee.email]);

    const toggleModule = (id) =>
        setSelectedModuleIds(prev => prev.includes(id) ? prev.filter(i => i !== id) : [...prev, id]);

    const addMentee = (u) => {
        setSelectedMentees(prev => [...prev, u]);
        setMenteeResults(prev => prev.filter(r => r.id !== u.id));
        setMenteeSearch("");
    };

    const resolveExistingUserByEmail = async (email) => {
        try {
            const res = await api.lookups.userByEmail(email);
            const found = res?.data ?? null;
            if (found) return found;
        } catch {}

        try {
            const res = await api.lookups.userSearch(email, 20);
            const list = Array.isArray(res?.data) ? res.data : Array.isArray(res) ? res : [];
            return list.find(u => String(u.email ?? "").trim().toLowerCase() === email.toLowerCase()) ?? null;
        } catch {
            return null;
        }
    };

    const addNewMentee = async () => {
        const firstName = newMentee.first_name.trim();
        const middleName = newMentee.middle_name.trim();
        const lastName = newMentee.last_name.trim();
        const email = newMentee.email.trim();
        if (!email) {
            setError("Email is required.");
            return;
        }

        let foundUser = newMenteeLookup.user && newMenteeLookup.checkedEmail === email ? newMenteeLookup.user : null;
        if (!foundUser) {
            foundUser = await resolveExistingUserByEmail(email);
        }

        if (foundUser) {
            if (selectedMentees.some(m => String(m.id) === String(foundUser.id))) {
                setError("This mentee has already been added.");
                return;
            }
            setSelectedMentees(prev => [...prev, foundUser]);
            setNewMentee({ first_name: "", middle_name: "", last_name: "", email: "", phone: "", cadre_id: "", department_id: "", facility_id: "" });
            setNewMenteeLookup({ loading: false, user: null, checkedEmail: "" });
            setShowCreateMentee(false);
            setError(null);
            return;
        }
        if (!firstName || !lastName || !email) {
            setError("First name, last name, and email are required to create a new mentee.");
            return;
        }
        const tempId = "new_" + Date.now();
        setSelectedMentees(prev => [...prev, {
            id: tempId,
            _isNew: true,
            name: `${firstName} ${lastName}`,
            email,
            phone: newMentee.phone.trim(),
            payload: {
                first_name: firstName,
                middle_name: middleName || null,
                last_name: lastName,
                email,
                phone: newMentee.phone.trim() || null,
                cadre_id: newMentee.cadre_id ? parseInt(newMentee.cadre_id) : null,
                department_id: newMentee.department_id ? parseInt(newMentee.department_id) : null,
                facility_id: (newMentee.facility_id || facilityId) ? parseInt(newMentee.facility_id || facilityId) : null,
            },
        }]);
        setNewMentee({ first_name: "", middle_name: "", last_name: "", email: "", phone: "", cadre_id: "", department_id: "", facility_id: "" });
        setNewMenteeLookup({ loading: false, user: null, checkedEmail: "" });
        setShowCreateMentee(false);
        setError(null);
    };

    const removeMentee = (id) => setSelectedMentees(prev => prev.filter(u => u.id !== id));

    const effectiveClassStartDate = classStartDate || startDate;
    const effectiveClassEndDate = classEndDate || endDate;
    const step1Valid = programId && countyId && facilityId && startDate && endDate;
    const step2Valid = className.trim() && effectiveClassStartDate && effectiveClassEndDate;

    const handleUpdate = async () => {
        setSaving(true); setError(null);
        try {
            const res = await api.mentorshipCreate.update(existingMentorship.id, {
                program_id:       parseInt(programId),
                facility_id:      parseInt(facilityId),
                county_id:        parseInt(countyId),
                start_date:       startDate,
                end_date:         endDate,
                max_participants: maxParticipants,
                is_pilot:         isPilot,
            });
            onCreated(res?.data ?? res);
        } catch (e) {
            setError(e.message ?? "Failed to update mentorship.");
        } finally {
            setSaving(false);
        }
    };

    const handleSave = async (startNow) => {
        setSaving(true); setError(null);
        try {
            const res = await api.mentorshipCreate.create({
                program_id:       parseInt(programId),
                facility_id:      parseInt(facilityId),
                county_id:        parseInt(countyId),
                start_date:       startDate,
                end_date:         endDate,
                max_participants: maxParticipants,
                is_pilot:         isPilot,
                class_name:       className.trim(),
                class_start_date: effectiveClassStartDate,
                class_end_date:   effectiveClassEndDate,
                class_notes:      classNotes.trim() || null,
                module_ids:       selectedModuleIds,
            });
            const newTraining = res?.data ?? res;

            if (selectedMentees.length > 0 && newTraining?.class?.id) {
                for (const mentee of selectedMentees) {
                    if (mentee._isNew) {
                        await api.classLifecycle.createMentee(newTraining.class.id, mentee.payload).catch(() => {});
                    } else {
                        await api.classLifecycle.enrollMentee(newTraining.class.id, mentee.id).catch(() => {});
                    }
                }
            }

            if (startNow && newTraining?.class?.id) {
                try {
                    await api.classLifecycle.start(newTraining.class.id);
                } catch (startErr) {
                    // Mentorship saved but start failed — still navigate, show warning via error state
                    onCreated({ ...newTraining, _startWarning: startErr?.message ?? "Class saved but could not be started. Add modules then start from the class screen." });
                    return;
                }
            }

            onCreated(newTraining);
        } catch (e) {
            setError(e.message ?? "Failed to save mentorship.");
        } finally {
            setSaving(false);
        }
    };

    const stepLabels = ["Setup", "Class", "Modules", "Mentees", "Review"];
    const selectedProgram = programs.find(p => String(p.id) === String(programId));
    const handleHeaderBack = () => {
        if (step > 1) {
            setStep(s => s - 1);
            return;
        }
        onBack();
    };

    return (
        <div style={{ display: "flex", flexDirection: "column", height: "100%", background: T.bg }}>
            {/* ── Gradient Header ── */}
            <div style={{
                background: "linear-gradient(160deg, #1E1B4B 0%, #3730A3 55%, #818CF8 100%)",
                padding: "40px 16px 0",
                position: "relative", overflow: "hidden",
            }}>
                <div style={{ position: "absolute", width: 140, height: 140, borderRadius: "50%", background: "radial-gradient(circle, rgba(165,180,252,0.15) 0%, transparent 70%)", top: -30, right: -20 }} />
                <div style={{ display: "flex", alignItems: "center", gap: 10, marginBottom: 14 }}>
                    <button onClick={handleHeaderBack} style={{ background: "rgba(255,255,255,0.12)", border: "none", cursor: "pointer", padding: "6px 10px", borderRadius: 10, display: "flex", alignItems: "center", gap: 4 }}>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="2.5"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                        <span style={{ fontSize: 12, color: "rgba(255,255,255,0.8)", fontWeight: 600 }}>Back</span>
                    </button>
                    <div style={{ fontWeight: 800, fontSize: 17, color: "white" }}>
                        {isEditMode ? "Edit Mentorship" : "New Mentorship"}
                    </div>
                </div>

                {/* Step progress — hidden in edit mode */}
                {!isEditMode && (
                    <div style={{ display: "flex", gap: 4, paddingBottom: 14 }}>
                        {stepLabels.map((label, i) => (
                            <div key={i} style={{ flex: 1, textAlign: "center" }}>
                                <div style={{
                                    height: 3, borderRadius: 2, marginBottom: 4,
                                    background: step > i + 1 ? "rgba(255,255,255,0.9)"
                                        : step === i + 1 ? "rgba(255,255,255,0.6)"
                                        : "rgba(255,255,255,0.15)",
                                }} />
                                <span style={{ fontSize: 10, fontWeight: step === i + 1 ? 700 : 400, color: step === i + 1 ? "rgba(255,255,255,0.9)" : "rgba(255,255,255,0.4)" }}>
                                    {label}
                                </span>
                            </div>
                        ))}
                    </div>
                )}
                {isEditMode && <div style={{ paddingBottom: 14 }} />}
            </div>

            {/* ── Content ── */}
            <div style={{ flex: 1, overflowY: "auto", padding: 16 }}>
                {editLoading && (
                    <div style={{ textAlign: "center", color: T.textSub, paddingTop: 60 }}>Loading mentorship…</div>
                )}
                {!editLoading && error && (
                    <div style={{ background: "#FEF2F2", border: "1px solid #FECACA", borderRadius: T.radiusSm, padding: "10px 14px", marginBottom: 14, color: "#DC2626", fontSize: 13 }}>
                        {error}
                    </div>
                )}

                {/* ── Step 1: Setup ── */}
                {!editLoading && step === 1 && (
                    <div>
                        {/* Run Type toggle */}
                        <div style={{ background: T.card, borderRadius: T.radiusSm, padding: "14px 14px 14px", boxShadow: T.shadowCard, marginBottom: 12 }}>
                            <div style={{ fontSize: 11, fontWeight: 700, color: T.textSub, textTransform: "uppercase", letterSpacing: 0.5, marginBottom: 12, display: "flex", alignItems: "center", gap: 6 }}>
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                Run Type
                            </div>
                            <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 8 }}>
                                {[
                                    { pilot: false, label: "Live", icon: "🎯", desc: "Production mentorship tracked in official reporting" },
                                    { pilot: true,  label: "Pilot Run", icon: "🧪", desc: "Trial run — excluded from official KPIs and reports" },
                                ].map(opt => {
                                    const active = isPilot === opt.pilot;
                                    return (
                                        <button
                                            key={opt.label}
                                            type="button"
                                            onClick={() => setIsPilot(opt.pilot)}
                                            style={{
                                                padding: "12px 10px", borderRadius: 10, cursor: "pointer",
                                                border: active ? `2px solid ${opt.pilot ? "#F59E0B" : T.primary}` : `1px solid ${T.border}`,
                                                background: active ? (opt.pilot ? "#FFFBEB" : T.primaryGhost) : "#fff",
                                                textAlign: "center",
                                                transition: "all 0.18s ease",
                                                boxShadow: active ? `0 2px 8px ${opt.pilot ? "rgba(245,158,11,0.2)" : T.primaryGlow}` : "none",
                                            }}
                                        >
                                            <div style={{ fontSize: 20, marginBottom: 4 }}>{opt.icon}</div>
                                            <div style={{ fontSize: 13, fontWeight: 700, color: active ? (opt.pilot ? "#D97706" : T.primary) : T.text }}>
                                                {opt.label}
                                            </div>
                                            <div style={{ fontSize: 10, color: T.textMuted, marginTop: 3, lineHeight: 1.4 }}>
                                                {opt.desc}
                                            </div>
                                        </button>
                                    );
                                })}
                            </div>
                        </div>

                        {/* Location card */}
                        <div style={{ background: T.card, borderRadius: T.radiusSm, padding: "14px 14px 2px", boxShadow: T.shadowCard, marginBottom: 12 }}>
                            <div style={{ fontSize: 11, fontWeight: 700, color: T.textSub, textTransform: "uppercase", letterSpacing: 0.5, marginBottom: 12, display: "flex", alignItems: "center", gap: 6 }}>
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                                Location
                            </div>

                            <Field label="County" required hint="Select county to load facilities">
                                <div style={{ display: "none" }}><select
                                    value={countyId}
                                    onChange={e => setCountyId(e.target.value)}
                                    style={selectStyle}
                                >
                                    <option value="">Select county…</option>
                                    {counties.map(c => (
                                        <option key={c.id} value={c.id}>{c.name}</option>
                                    ))}
                                </select></div>
                                <SearchableDropdown
                                    options={counties}
                                    value={countyId}
                                    onChange={(id) => setCountyId(id)}
                                    getLabel={county => county.name}
                                    placeholder="Select county..."
                                    searchPlaceholder="Search county..."
                                    emptyText="No counties found"
                                />
                            </Field>

                            <Field label="Facility" required hint={!countyId ? "Select a county first" : facilitiesLoading ? "Loading facilities…" : facilitiesError ? facilitiesError : facilities.length === 0 ? "No facilities found for this county" : `${facilities.length} facilities in this county`}>
                                <div style={{ display: "none" }}><select
                                    value={facilityId}
                                    onChange={e => {
                                        const f = facilities.find(f => String(f.id) === e.target.value);
                                        setFacilityId(e.target.value);
                                        setFacilityLabel(f?.label ?? "");
                                    }}
                                    disabled={!countyId || facilitiesLoading}
                                    style={{ ...selectStyle, opacity: (!countyId || facilitiesLoading) ? 0.5 : 1 }}
                                >
                                    <option value="">Select facility…</option>
                                    {facilities.map(f => (
                                        <option key={f.id} value={f.id}>{f.label}</option>
                                    ))}
                                </select></div>
                                <SearchableDropdown
                                    options={facilities}
                                    value={facilityId}
                                    onChange={(id, f) => {
                                        setFacilityId(id);
                                        setFacilityLabel(f?.label ?? "");
                                    }}
                                    disabled={!countyId || facilitiesLoading}
                                    getLabel={facility => facility.label ?? facility.name}
                                    placeholder={facilitiesLoading ? "Loading facilities..." : "Select facility..."}
                                    searchPlaceholder="Search facility or MFL..."
                                    emptyText="No facilities found"
                                />
                            </Field>
                        </div>

                        {/* Program & Schedule card */}
                        <div style={{ background: T.card, borderRadius: T.radiusSm, padding: "14px 14px 2px", boxShadow: T.shadowCard }}>
                            <div style={{ fontSize: 11, fontWeight: 700, color: T.textSub, textTransform: "uppercase", letterSpacing: 0.5, marginBottom: 12, display: "flex", alignItems: "center", gap: 6 }}>
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                Program & Schedule
                            </div>

                            <Field label="Mentorship Program" required>
                                <ProgramPickerCards
                                    programs={programs}
                                    value={programId}
                                    onChange={(id) => { setProgramId(id); setSelectedModuleIds([]); }}
                                />
                            </Field>

                            <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12 }}>
                                <Field label="Start Date" required>
                                    <input
                                        type="date"
                                        value={startDate}
                                        min={new Date().toISOString().split("T")[0]}
                                        onChange={e => { setStartDate(e.target.value); if (endDate && e.target.value > endDate) setEndDate(""); }}
                                        style={inputStyle}
                                    />
                                </Field>
                                <Field label="End Date" required>
                                    <input
                                        type="date"
                                        value={endDate}
                                        min={startDate || new Date().toISOString().split("T")[0]}
                                        onChange={e => setEndDate(e.target.value)}
                                        style={inputStyle}
                                    />
                                </Field>
                            </div>

                            <Field label="Number of Mentees" hint="Recommended minimum: 2. No maximum limit enforced.">
                                <input
                                    type="number"
                                    value={maxParticipants}
                                    min={1}
                                    onChange={e => setMaxParticipants(parseInt(e.target.value) || 20)}
                                    style={inputStyle}
                                />
                            </Field>
                        </div>
                    </div>
                )}

                {/* ── Step 2: Modules ── */}
                {!isEditMode && step === 2 && (
                    <div>
                        <div style={{ background: T.card, borderRadius: T.radiusSm, padding: "14px 14px 2px", boxShadow: T.shadowCard }}>
                            <div style={{ fontSize: 11, fontWeight: 700, color: T.textSub, textTransform: "uppercase", letterSpacing: 0.5, marginBottom: 12 }}>
                                Class / Cohort
                            </div>
                            <Field label="Class Name" required hint="Modules and mentees will be added to this class.">
                                <input value={className} onChange={e => setClassName(e.target.value)} placeholder="e.g. Class 1 or April Cohort" style={inputStyle} />
                            </Field>
                            <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12 }}>
                                <Field label="Class Start" required hint="Select the date this class will begin. It must fall within the mentorship dates.">
                                    <input type="date" value={effectiveClassStartDate} min={startDate} max={endDate || undefined} onChange={e => { setClassStartDate(e.target.value); if (effectiveClassEndDate && e.target.value > effectiveClassEndDate) setClassEndDate(""); }} style={inputStyle} />
                                </Field>
                                <Field label="Class End" required hint="Select the date this class will end. It cannot be before the class start date.">
                                    <input type="date" value={effectiveClassEndDate} min={effectiveClassStartDate} max={endDate || undefined} onChange={e => setClassEndDate(e.target.value)} style={inputStyle} />
                                </Field>
                            </div>
                            <Field label="Class Description" hint="Provide detail on why this class is being created. What gap or performance need should this mentorship address?">
                                <textarea value={classNotes} onChange={e => setClassNotes(e.target.value)} rows={3} placeholder="Describe the gap, need, or reason for this class..." style={{ ...inputStyle, resize: "vertical" }} />
                            </Field>
                        </div>
                    </div>
                )}

                {!isEditMode && step === 3 && (
                    <div>
                        <div style={{ fontSize: 13, color: T.textSub, marginBottom: 12, lineHeight: 1.6 }}>
                            Select modules to include in {className.trim() || "this class"}. Sessions are auto-created from program templates.
                        </div>
                        {modulesLoading && (
                            <div style={{ textAlign: "center", color: T.textSub, padding: 32 }}>Loading modules…</div>
                        )}
                        {!modulesLoading && availableModules.length === 0 && (
                            <div style={{ textAlign: "center", color: T.textMuted, padding: 32 }}>
                                {programId ? "No modules available for this program." : "Select a program in Step 1 first."}
                            </div>
                        )}
                        {availableModules.map(m => {
                            const selected = selectedModuleIds.includes(m.id);
                            return (
                                <div
                                    key={m.id}
                                    onClick={() => toggleModule(m.id)}
                                    style={{
                                        display: "flex", alignItems: "flex-start", gap: 12,
                                        padding: "14px 16px", marginBottom: 8, cursor: "pointer",
                                        borderRadius: T.radiusSm, boxShadow: T.shadowCard,
                                        border: `1px solid ${selected ? "#6366F1" : T.border}`,
                                        background: selected ? "rgba(99,102,241,0.05)" : T.card,
                                        transition: "border-color 0.15s",
                                    }}
                                >
                                    <div style={{
                                        width: 22, height: 22, borderRadius: 6, flexShrink: 0, marginTop: 1,
                                        border: `2px solid ${selected ? "#6366F1" : T.border}`,
                                        background: selected ? "#6366F1" : "#fff",
                                        display: "flex", alignItems: "center", justifyContent: "center",
                                        transition: "background 0.15s",
                                    }}>
                                        {selected && (
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="3">
                                                <polyline points="20 6 9 17 4 12"/>
                                            </svg>
                                        )}
                                    </div>
                                    <div style={{ flex: 1 }}>
                                        <div style={{ fontSize: 14, fontWeight: 600, color: T.text }}>
                                            {m.order_sequence}. {m.name}
                                        </div>
                                        {m.description && (
                                            <div style={{ fontSize: 12, color: T.textSub, marginTop: 2, lineHeight: 1.5 }}>{m.description}</div>
                                        )}
                                        {m.session_count > 0 && (
                                            <div style={{ fontSize: 11, color: T.textMuted, marginTop: 3 }}>
                                                {m.session_count} session{m.session_count !== 1 ? "s" : ""}
                                            </div>
                                        )}
                                    </div>
                                </div>
                            );
                        })}
                        {availableModules.length > 0 && (
                            <div style={{ textAlign: "center", fontSize: 12, color: T.textSub, marginTop: 8 }}>
                                {selectedModuleIds.length} of {availableModules.length} selected
                            </div>
                        )}
                    </div>
                )}

                {/* ── Step 3: Mentees ── */}
                {!isEditMode && step === 4 && (
                    <div>
                        {!navigator.onLine && (
                            <div style={{ background: "#FFFBEB", border: "1px solid #FCD34D", borderRadius: T.radiusSm, padding: "12px 14px", marginBottom: 14, display: "flex", gap: 10 }}>
                                <span style={{ fontSize: 18 }}>🔒</span>
                                <div>
                                    <div style={{ fontSize: 14, fontWeight: 600, color: "#92400E" }}>Mobile data required</div>
                                    <div style={{ fontSize: 13, color: "#78350F", marginTop: 2 }}>Turn on mobile data to search mentees. You can skip and enroll them after saving.</div>
                                </div>
                            </div>
                        )}

                        {navigator.onLine && (
                            <div style={{ position: "relative", marginBottom: 12 }}>
                                <input
                                    placeholder="Search by name, phone, email, facility, or MFL..."
                                    value={menteeSearch}
                                    onChange={e => setMenteeSearch(e.target.value)}
                                    autoFocus
                                    style={{
                                        width: "100%", padding: "11px 40px 11px 14px",
                                        borderRadius: T.radiusSm, border: `1px solid ${T.border}`,
                                        fontSize: 14, boxSizing: "border-box",
                                        background: "#fff", color: T.text, outline: "none",
                                    }}
                                />
                                <svg style={{ position: "absolute", right: 12, top: "50%", transform: "translateY(-50%)" }}
                                    width="16" height="16" viewBox="0 0 24 24" fill="none" stroke={T.textMuted} strokeWidth="2">
                                    <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>
                                </svg>
                            </div>
                        )}

                        {menteeSearching && <div style={{ color: T.textSub, textAlign: "center", padding: 16, fontSize: 13 }}>Searching…</div>}
                        <div style={{ background: T.card, borderRadius: T.radiusSm, padding: 14, boxShadow: T.shadowCard, marginBottom: 12 }}>
                            <button
                                onClick={() => setShowCreateMentee(prev => !prev)}
                                style={{ width: "100%", padding: 11, borderRadius: T.radiusSm, border: `1px solid ${T.border}`, background: T.bg, color: T.text, fontSize: 14, fontWeight: 700, cursor: "pointer" }}
                            >
                                {showCreateMentee ? "Hide New Mentee Form" : "Create New Mentee"}
                            </button>
                            {showCreateMentee && (
                                <div style={{ marginTop: 12 }}>
                                    <div style={{ fontSize: 12, color: T.textSub, lineHeight: 1.5, marginBottom: 10 }}>
                                        Start with email. If an account exists, details are loaded and the mentee is added from the existing user list. If not, fill the profile to create a new account. Default password will be 123456.
                                    </div>
                                    <input value={newMentee.email} onChange={e => setNewMentee(v => ({ ...v, email: e.target.value }))} placeholder="Email address" style={inputStyle} />
                                    {newMenteeLookup.loading && (
                                        <div style={{ fontSize: 12, color: T.textSub, marginTop: 6 }}>Checking email...</div>
                                    )}
                                    {!newMenteeLookup.loading && newMenteeLookup.checkedEmail && (
                                        <div style={{ fontSize: 12, color: newMenteeLookup.user ? "#065F46" : "#1D4ED8", marginTop: 6, fontWeight: 600 }}>
                                            {newMenteeLookup.user ? "Existing user found. Details have been loaded." : "No account found. Complete the details to create a new mentee."}
                                        </div>
                                    )}
                                    <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 10 }}>
                                        <input value={newMentee.first_name} onChange={e => setNewMentee(v => ({ ...v, first_name: e.target.value }))} placeholder="First name" style={{ ...inputStyle, marginTop: 10 }} />
                                        <input value={newMentee.last_name} onChange={e => setNewMentee(v => ({ ...v, last_name: e.target.value }))} placeholder="Last name" style={{ ...inputStyle, marginTop: 10 }} />
                                    </div>
                                    <input value={newMentee.middle_name} onChange={e => setNewMentee(v => ({ ...v, middle_name: e.target.value }))} placeholder="Middle name (optional)" style={{ ...inputStyle, marginTop: 10 }} />
                                    <input value={newMentee.phone} onChange={e => setNewMentee(v => ({ ...v, phone: e.target.value }))} placeholder="Phone (optional)" style={{ ...inputStyle, marginTop: 10 }} />
                                    <div style={{ marginTop: 10 }}>
                                        <SearchableDropdown
                                            options={cadres}
                                            value={newMentee.cadre_id}
                                            onChange={(value) => setNewMentee(v => ({ ...v, cadre_id: value }))}
                                            placeholder="Cadre (optional)"
                                            searchPlaceholder="Search cadre..."
                                        />
                                    </div>
                                    <div style={{ marginTop: 10 }}>
                                        <SearchableDropdown
                                            options={departments}
                                            value={newMentee.department_id}
                                            onChange={(value) => setNewMentee(v => ({ ...v, department_id: value }))}
                                            placeholder="Department (optional)"
                                            searchPlaceholder="Search department..."
                                        />
                                    </div>
                                    <div style={{ marginTop: 10 }}>
                                        <SearchableDropdown
                                            options={allFacilities.length > 0 ? allFacilities : facilities}
                                            value={newMentee.facility_id || facilityId}
                                            onChange={(value) => setNewMentee(v => ({ ...v, facility_id: value }))}
                                            getLabel={(f) => f?.label || (f?.mfl_code ? `${f.mfl_code} - ${f.name}` : f?.name ?? "")}
                                            placeholder="Facility (optional)"
                                            searchPlaceholder="Search facility or MFL..."
                                        />
                                    </div>
                                    <button onClick={addNewMentee} style={{ width: "100%", marginTop: 10, padding: 11, borderRadius: T.radiusSm, border: "none", background: T.primary, color: "#fff", fontSize: 14, fontWeight: 700, cursor: "pointer" }}>
                                        {newMenteeLookup.user ? "Add Existing Mentee" : "Create and Add Mentee"}
                                    </button>
                                </div>
                            )}
                        </div>

                        {!menteeSearching && menteeSearch.length >= 2 && menteeResults.length === 0 && (
                            <div style={{ color: T.textSub, textAlign: "center", padding: 16, fontSize: 13 }}>No users found.</div>
                        )}
                        {navigator.onLine && menteeSearch.length < 2 && (
                            <div style={{ color: T.textMuted, textAlign: "center", padding: 16, fontSize: 13 }}>
                                Type at least 2 characters to search
                            </div>
                        )}

                        {menteeResults.map(u => (
                            <div
                                key={u.id}
                                onClick={() => addMentee(u)}
                                style={{
                                    padding: "12px 14px", borderRadius: T.radiusSm, border: `1px solid ${T.border}`,
                                    marginBottom: 8, cursor: "pointer", background: T.card,
                                    display: "flex", alignItems: "center", gap: 12, boxShadow: T.shadowCard,
                                }}
                            >
                                <div style={{
                                    width: 36, height: 36, borderRadius: "50%", background: T.primaryGhost,
                                    display: "flex", alignItems: "center", justifyContent: "center",
                                    fontWeight: 700, fontSize: 13, color: T.primary, flexShrink: 0,
                                }}>
                                    {(u.name ?? "?").split(" ").map(p => p[0]).join("").slice(0, 2).toUpperCase()}
                                </div>
                                <div style={{ flex: 1 }}>
                                    <div style={{ fontSize: 14, fontWeight: 600, color: T.text }}>{u.name}</div>
                                    <div style={{ fontSize: 12, color: T.textSub }}>
                                        {[u.email, u.phone, u.facility_name, u.mfl_code ? `MFL ${u.mfl_code}` : null].filter(Boolean).join(" · ")}
                                    </div>
                                </div>
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke={T.primary} strokeWidth="2.5">
                                    <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                                </svg>
                            </div>
                        ))}

                        {selectedMentees.length > 0 && (
                            <div style={{ marginTop: menteeResults.length > 0 ? 16 : 0 }}>
                                <div style={{ fontSize: 11, fontWeight: 700, color: T.textSub, textTransform: "uppercase", letterSpacing: 0.5, marginBottom: 8 }}>
                                    Added ({selectedMentees.length})
                                </div>
                                {selectedMentees.map(u => (
                                    <div key={u.id} style={{
                                        display: "flex", alignItems: "center", gap: 12,
                                        padding: "10px 14px", borderRadius: T.radiusSm,
                                        background: T.primaryGhost, border: `1px solid ${T.border}`,
                                        marginBottom: 6,
                                    }}>
                                        <div style={{
                                            width: 32, height: 32, borderRadius: "50%", background: T.primary,
                                            display: "flex", alignItems: "center", justifyContent: "center",
                                            fontWeight: 700, fontSize: 12, color: "#fff", flexShrink: 0,
                                        }}>
                                            {(u.name ?? "?").split(" ").map(p => p[0]).join("").slice(0, 2).toUpperCase()}
                                        </div>
                                        <span style={{ flex: 1, fontSize: 14, color: T.text, fontWeight: 500 }}>{u.name}</span>
                                        <button
                                            onClick={() => removeMentee(u.id)}
                                            style={{ background: "none", border: "none", color: T.textMuted, fontSize: 18, cursor: "pointer", lineHeight: 1, padding: 4 }}
                                        >×</button>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                )}

                {/* ── Step 4: Review ── */}
                {!isEditMode && step === 5 && (
                    <div>
                        <div style={{ background: T.card, borderRadius: T.radiusSm, padding: 16, boxShadow: T.shadowCard, marginBottom: 12 }}>
                            {[
                                ["Run Type",   isPilot ? "🧪 Pilot Run" : "🎯 Live"],
                                ["Program",    selectedProgram?.name ?? "—"],
                                ["County",     counties.find(c => String(c.id) === String(countyId))?.name ?? "—"],
                                ["Facility",   facilityLabel || "—"],
                                ["Start Date", startDate],
                                ["End Date",   endDate],
                                ["Max Mentees", `${maxParticipants}`],
                                ["Class",      className.trim() || "—"],
                                ["Class Dates", `${effectiveClassStartDate || "—"} → ${effectiveClassEndDate || "—"}`],
                                ["Modules",    `${selectedModuleIds.length} selected`],
                                ["Mentees",    `${selectedMentees.length} added`],
                            ].map(([label, value]) => (
                                <div key={label} style={{ display: "flex", justifyContent: "space-between", paddingBlock: 8, borderBottom: `1px solid ${T.borderLight}` }}>
                                    <span style={{ fontSize: 12, color: T.textSub }}>{label}</span>
                                    <span style={{ fontSize: 13, fontWeight: 600, color: T.text, textAlign: "right", maxWidth: "60%" }}>{value}</span>
                                </div>
                            ))}
                        </div>

                        <button
                            disabled={saving}
                            onClick={() => handleSave(false)}
                            style={{
                                width: "100%", padding: 14, marginBottom: 10, borderRadius: T.radiusSm,
                                background: T.card, border: `1px solid ${T.border}`,
                                color: T.text, fontSize: 14, fontWeight: 600, cursor: saving ? "not-allowed" : "pointer",
                                opacity: saving ? 0.6 : 1,
                            }}
                        >
                            {saving ? "Saving…" : "Save as Draft"}
                        </button>
                        <button
                            disabled={saving || selectedMentees.length === 0}
                            onClick={() => handleSave(true)}
                            style={{
                                width: "100%", padding: 14, borderRadius: T.radiusSm, border: "none",
                                background: "linear-gradient(135deg, #3730A3, #6366F1)",
                                color: "#fff", fontSize: 14, fontWeight: 700,
                                cursor: (saving || selectedMentees.length === 0) ? "not-allowed" : "pointer",
                                opacity: selectedMentees.length === 0 ? 0.5 : 1,
                                boxShadow: "0 4px 12px rgba(55,48,163,0.3)",
                            }}
                        >
                            {saving ? "Starting…" : "Save & Start Class"}
                        </button>
                        {selectedMentees.length === 0 && (
                            <div style={{ textAlign: "center", fontSize: 12, color: T.textMuted, marginTop: 8 }}>
                                Add at least one mentee in Step 4 to start immediately
                            </div>
                        )}
                    </div>
                )}
            </div>

            {/* ── Footer Nav ── */}
            {isEditMode ? (
                <div style={{ padding: "12px 16px", background: T.card, borderTop: `1px solid ${T.borderLight}` }}>
                    <button
                        onClick={handleUpdate}
                        disabled={saving || !step1Valid}
                        style={{
                            width: "100%", padding: 13, borderRadius: T.radiusSm, border: "none",
                            background: "linear-gradient(135deg, #0097A7, #26C6DA)",
                            color: "#fff", fontSize: 14, fontWeight: 700,
                            cursor: (saving || !step1Valid) ? "not-allowed" : "pointer",
                            opacity: (saving || !step1Valid) ? 0.6 : 1,
                            boxShadow: "0 4px 12px rgba(79,106,245,0.28)",
                        }}
                    >
                        {saving ? "Saving…" : "Save Changes"}
                    </button>
                </div>
            ) : step < 5 && (
                <div style={{ padding: "12px 16px", background: T.card, borderTop: `1px solid ${T.borderLight}`, display: "flex", gap: 10 }}>
                    {step > 1 && (
                        <button
                            onClick={() => setStep(s => s - 1)}
                            style={{
                                flex: 1, padding: 12, borderRadius: T.radiusSm,
                                background: T.bg, border: `1px solid ${T.border}`,
                                color: T.text, fontSize: 14, fontWeight: 600, cursor: "pointer",
                            }}
                        >
                            Back
                        </button>
                    )}
                    <button
                        onClick={() => step === 4 ? setStep(5) : setStep(s => s + 1)}
                        disabled={(step === 1 && !step1Valid) || (step === 2 && !step2Valid)}
                        style={{
                            flex: 2, padding: 12, borderRadius: T.radiusSm, border: "none",
                            background: "linear-gradient(135deg, #3730A3, #6366F1)",
                            color: "#fff", fontSize: 14, fontWeight: 700,
                            cursor: ((step === 1 && !step1Valid) || (step === 2 && !step2Valid)) ? "not-allowed" : "pointer",
                            opacity: ((step === 1 && !step1Valid) || (step === 2 && !step2Valid)) ? 0.5 : 1,
                        }}
                    >
                        {step === 4 ? (selectedMentees.length > 0 ? "Continue" : "Skip & Continue") : "Continue"}
                    </button>
                </div>
            )}
        </div>
    );
}
