import { useState, useEffect } from "react";
import { T } from "../constants.js";
import { Field, SearchableDropdown, inputStyle } from "./screen-mentorship-form.jsx";
import api from "../services/api.service.js";

const ROLE_OPTIONS = [
    { id: "mentee", name: "Mentee" },
    { id: "facility_mentor", name: "Facility Mentor" },
];

const STEP_LABELS = ["Personal", "Contact", "Professional", "Geographic"];

// Maps each backend validation field name to the wizard step that collects it,
// so a 422 response can jump the user straight to the right step.
const FIELD_STEP = {
    first_name: 1, middle_name: 1, last_name: 1,
    email: 2, phone: 2,
    cadre_id: 3, department_id: 3, role: 3,
    county_id: 4, facility_id: 4,
};

const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

export function RegisterScreen({ onRegistered, onBack, onGoToForgotPassword }) {
    const [step, setStep] = useState(1);

    const [firstName, setFirstName]   = useState("");
    const [middleName, setMiddleName] = useState("");
    const [lastName, setLastName]     = useState("");
    const [email, setEmail]           = useState("");
    const [phone, setPhone]           = useState("");
    const [cadreId, setCadreId]       = useState("");
    const [departmentId, setDeptId]   = useState("");
    const [role, setRole]             = useState("mentee");
    const [countyId, setCountyId]     = useState("");
    const [facilityId, setFacilityId] = useState("");

    const [cadres, setCadres]         = useState([]);
    const [departments, setDeptments] = useState([]);
    const [counties, setCounties]     = useState([]);
    const [facilities, setFacilities] = useState([]);
    const [facilitiesLoading, setFacilitiesLoading] = useState(false);

    // "idle" | "checking" | "exists" | "available"
    const [emailStatus, setEmailStatus] = useState("idle");
    const [phoneStatus, setPhoneStatus] = useState("idle");

    const [error, setError]             = useState("");
    const [fieldErrors, setFieldErrors] = useState({});
    const [saving, setSaving]           = useState(false);
    const [done, setDone]               = useState(false);

    useEffect(() => {
        api.registerLookups.cadres().then(d => setCadres(Array.isArray(d?.data) ? d.data : Array.isArray(d) ? d : [])).catch(() => {});
        api.registerLookups.departments().then(d => setDeptments(Array.isArray(d?.data) ? d.data : Array.isArray(d) ? d : [])).catch(() => {});
        api.registerLookups.counties().then(d => setCounties(Array.isArray(d?.data) ? d.data : Array.isArray(d) ? d : [])).catch(() => {});
    }, []);

    useEffect(() => {
        if (!countyId) { setFacilities([]); setFacilityId(""); return; }
        setFacilitiesLoading(true);
        api.registerLookups.facilitiesByCounty(countyId)
            .then(list => {
                const arr = (Array.isArray(list?.data) ? list.data : Array.isArray(list) ? list : [])
                    .map(f => ({ ...f, label: f?.label || (f?.mfl_code ? `${f.mfl_code} - ${f.name}` : f?.name) }))
                    .filter(f => f.id && f.name);
                setFacilities(arr);
                setFacilityId("");
            })
            .catch(() => setFacilities([]))
            .finally(() => setFacilitiesLoading(false));
    }, [countyId]);

    // Debounced "does this email already exist" check while typing.
    useEffect(() => {
        const trimmed = email.trim();
        if (!EMAIL_RE.test(trimmed)) { setEmailStatus("idle"); return; }
        setEmailStatus("checking");
        const timer = setTimeout(() => {
            api.registerLookups.checkEmail(trimmed)
                .then(d => setEmailStatus(d?.exists ? "exists" : "available"))
                .catch(() => setEmailStatus("idle"));
        }, 500);
        return () => clearTimeout(timer);
    }, [email]);

    // Debounced "is this phone already taken" check — only meaningful once
    // the email has been confirmed free (a duplicate email already has its
    // own, more actionable prompt below).
    useEffect(() => {
        const trimmed = phone.trim();
        if (trimmed.length < 7 || emailStatus === "exists") { setPhoneStatus("idle"); return; }
        setPhoneStatus("checking");
        const timer = setTimeout(() => {
            api.registerLookups.checkPhone(trimmed)
                .then(d => setPhoneStatus(d?.exists ? "exists" : "available"))
                .catch(() => setPhoneStatus("idle"));
        }, 500);
        return () => clearTimeout(timer);
    }, [phone, emailStatus]);

    const step1Valid = firstName.trim() && lastName.trim();
    const step2Valid = email.trim() && phone.trim() && emailStatus !== "exists";
    const step3Valid = cadreId && departmentId && role;
    const step4Valid = countyId && facilityId;
    const stepValid = [true, step1Valid, step2Valid, step3Valid, step4Valid][step];

    // Clear a field's error the moment the user edits it.
    const clearFieldError = (name) => {
        if (fieldErrors[name]) {
            setFieldErrors(prev => {
                const next = { ...prev };
                delete next[name];
                return next;
            });
        }
    };

    const handleSubmit = async () => {
        setError("");
        setFieldErrors({});
        setSaving(true);
        try {
            await api.auth.register({
                first_name: firstName.trim(),
                middle_name: middleName.trim() || null,
                last_name: lastName.trim(),
                email: email.trim(),
                phone: phone.trim(),
                cadre_id: parseInt(cadreId),
                department_id: parseInt(departmentId),
                role,
                county_id: parseInt(countyId),
                facility_id: parseInt(facilityId),
            });
            setDone(true);
        } catch (e) {
            if (e.status === 422 && e.errors && Object.keys(e.errors).length > 0) {
                const flat = {};
                for (const [field, messages] of Object.entries(e.errors)) {
                    flat[field] = Array.isArray(messages) ? messages[0] : String(messages);
                }
                setFieldErrors(flat);
                const firstField = Object.keys(flat)[0];
                const targetStep = FIELD_STEP[firstField] ?? 1;
                setStep(targetStep);
                setError("Please fix the highlighted field" + (Object.keys(flat).length > 1 ? "s" : "") + " below.");
            } else {
                setError(e.message || "Registration failed. Please check your details and try again.");
            }
        } finally {
            setSaving(false);
        }
    };

    if (done) {
        return (
            <div style={{ display: "flex", flexDirection: "column", height: "100%", alignItems: "center",
                justifyContent: "center", padding: 32, textAlign: "center", background: T.bg }}>
                <div style={{ fontSize: 40, marginBottom: 12 }}>📬</div>
                <div style={{ fontSize: 18, fontWeight: 800, color: T.text, marginBottom: 8 }}>Check your email</div>
                <div style={{ fontSize: 14, color: T.textSub, marginBottom: 28, lineHeight: 1.6 }}>
                    We've sent a verification link to <strong>{email.trim()}</strong>. Open it on this device to set your password and activate your account.
                </div>
                <button onClick={onRegistered} style={{
                    padding: "12px 28px", background: T.gradientPrimary, color: "white", border: "none",
                    borderRadius: 12, fontWeight: 600, fontSize: 15, cursor: "pointer",
                }}>
                    Back to Login
                </button>
            </div>
        );
    }

    const handleHeaderBack = () => {
        if (step > 1) { setStep(s => s - 1); return; }
        onBack();
    };

    const handleContinue = () => {
        if (step < 4) setStep(s => s + 1);
        else handleSubmit();
    };

    return (
        <div style={{ display: "flex", flexDirection: "column", height: "100%", background: T.bg,
            fontFamily: "-apple-system, 'SF Pro Display', 'Segoe UI', system-ui, sans-serif" }}>
            <div style={{ background: T.gradientHero, padding: "24px 20px 0", borderRadius: "28px", margin: "calc(6px + env(safe-area-inset-top, 0px)) 6px 0" }}>
                <div style={{ display: "flex", alignItems: "center", gap: 10, marginBottom: 18 }}>
                    <div style={{
                        width: 34, height: 34, borderRadius: 11, flexShrink: 0,
                        background: T.gradientPrimary,
                        display: "flex", alignItems: "center", justifyContent: "center",
                        boxShadow: "0 4px 14px rgba(79,106,245,0.4)",
                    }}>
                        <svg width="19" height="19" viewBox="0 0 34 34" fill="none">
                            <rect x="13" y="2" width="8" height="30" rx="4" fill="white" fillOpacity="0.95"/>
                            <rect x="2" y="13" width="30" height="8" rx="4" fill="white" fillOpacity="0.95"/>
                            <circle cx="17" cy="17" r="5" fill="white"/>
                        </svg>
                    </div>
                    <div>
                        <div style={{ color: "white", fontSize: 15, fontWeight: 800, lineHeight: 1.2, letterSpacing: -0.2 }}>MNCH Kenya</div>
                        <div style={{ color: "rgba(255,255,255,0.55)", fontSize: 10, fontWeight: 700, letterSpacing: 0.6, textTransform: "uppercase", marginTop: 1 }}>Ministry of Health</div>
                    </div>
                </div>
                <div style={{ display: "flex", alignItems: "center", gap: 10, marginBottom: 14 }}>
                    <button onClick={handleHeaderBack} style={{ background: "rgba(255,255,255,0.15)", border: "none", cursor: "pointer",
                        padding: "6px 10px", borderRadius: 10, color: "white", fontSize: 12, fontWeight: 600 }}>
                        ← Back
                    </button>
                    <div style={{ color: "white", fontSize: 20, fontWeight: 800 }}>Create Account</div>
                </div>
                <div style={{ display: "flex", gap: 4, paddingBottom: 14 }}>
                    {STEP_LABELS.map((label, i) => (
                        <div key={label} style={{ flex: 1, textAlign: "center" }}>
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
            </div>

            <div style={{ flex: 1, overflowY: "auto", padding: "20px 20px 0" }}>
                {error && (
                    <div style={{ background: "#FEF2F2", color: "#991B1B", borderRadius: T.radiusSm,
                        padding: "12px 16px", fontSize: 13, marginBottom: 16, border: "1px solid #FECACA" }}>
                        {error}
                    </div>
                )}

                {step === 1 && (
                    <div>
                        <Field label="First Name" required error={fieldErrors.first_name}>
                            <input value={firstName} onChange={e => { setFirstName(e.target.value); clearFieldError("first_name"); }} style={inputStyle} />
                        </Field>
                        <Field label="Middle Name" error={fieldErrors.middle_name}>
                            <input value={middleName} onChange={e => { setMiddleName(e.target.value); clearFieldError("middle_name"); }} style={inputStyle} />
                        </Field>
                        <Field label="Last Name" required error={fieldErrors.last_name}>
                            <input value={lastName} onChange={e => { setLastName(e.target.value); clearFieldError("last_name"); }} style={inputStyle} />
                        </Field>
                    </div>
                )}

                {step === 2 && (
                    <div>
                        <Field
                            label="Email Address" required error={fieldErrors.email}
                            hint={emailStatus === "checking" ? "Checking…" : emailStatus === "available" ? "✓ Available" : undefined}
                        >
                            <input type="email" value={email} onChange={e => { setEmail(e.target.value); clearFieldError("email"); }} style={inputStyle} />
                        </Field>
                        {emailStatus === "exists" && (
                            <div style={{ background: "#FFFBEB", border: "1px solid #FCD34D", borderRadius: T.radiusSm,
                                padding: "12px 14px", marginTop: -8, marginBottom: 16 }}>
                                <div style={{ fontSize: 12, color: "#92400E", fontWeight: 600, marginBottom: 10, lineHeight: 1.5 }}>
                                    An account with this email already exists. Would you like to reset your password instead?
                                </div>
                                <div style={{ display: "flex", gap: 8 }}>
                                    <button type="button" onClick={() => onGoToForgotPassword?.(email.trim())} style={{
                                        flex: 1, padding: "9px 0", borderRadius: T.radiusXs, border: "none",
                                        background: "#D97706", color: "#fff", fontSize: 12, fontWeight: 700, cursor: "pointer",
                                    }}>
                                        Yes, reset password
                                    </button>
                                    <button type="button" onClick={() => setEmail("")} style={{
                                        flex: 1, padding: "9px 0", borderRadius: T.radiusXs, border: "1px solid #FCD34D",
                                        background: "transparent", color: "#92400E", fontSize: 12, fontWeight: 700, cursor: "pointer",
                                    }}>
                                        No, use a different email
                                    </button>
                                </div>
                            </div>
                        )}

                        <Field
                            label="Phone Number" required error={fieldErrors.phone}
                            hint={phoneStatus === "checking" ? "Checking…" : phoneStatus === "available" ? "✓ Available" : undefined}
                        >
                            <input type="tel" value={phone} onChange={e => { setPhone(e.target.value); clearFieldError("phone"); }} style={inputStyle} />
                        </Field>
                        {phoneStatus === "exists" && (
                            <div style={{ background: "#FEF2F2", border: "1px solid #FECACA", borderRadius: T.radiusSm,
                                padding: "12px 14px", marginTop: -8, fontSize: 12, color: "#991B1B", fontWeight: 600, lineHeight: 1.5 }}>
                                This phone number is already registered to another account. Please contact your system administrator to update the account.
                            </div>
                        )}
                    </div>
                )}

                {step === 3 && (
                    <div>
                        <Field label="Cadre" required error={fieldErrors.cadre_id}>
                            <SearchableDropdown options={cadres} value={cadreId} onChange={v => { setCadreId(v); clearFieldError("cadre_id"); }} placeholder="Select cadre..." searchPlaceholder="Search cadre..." />
                        </Field>
                        <Field label="Department" required error={fieldErrors.department_id}>
                            <SearchableDropdown options={departments} value={departmentId} onChange={v => { setDeptId(v); clearFieldError("department_id"); }} placeholder="Select department..." searchPlaceholder="Search department..." />
                        </Field>
                        <Field label="Role" required error={fieldErrors.role}>
                            <SearchableDropdown options={ROLE_OPTIONS} value={role} onChange={v => { setRole(v); clearFieldError("role"); }} placeholder="Select role..." />
                        </Field>
                    </div>
                )}

                {step === 4 && (
                    <div>
                        <Field label="County" required error={fieldErrors.county_id} hint="Select county to load facilities">
                            <SearchableDropdown options={counties} value={countyId} onChange={v => { setCountyId(v); clearFieldError("county_id"); }} placeholder="Select county..." searchPlaceholder="Search county..." />
                        </Field>
                        <Field label="Facility" required error={fieldErrors.facility_id} hint={!countyId ? "Select a county first" : facilitiesLoading ? "Loading facilities…" : undefined}>
                            <SearchableDropdown
                                options={facilities} value={facilityId} onChange={v => { setFacilityId(v); clearFieldError("facility_id"); }}
                                disabled={!countyId || facilitiesLoading}
                                getLabel={f => f.label ?? f.name}
                                placeholder={facilitiesLoading ? "Loading facilities..." : "Select facility..."}
                                searchPlaceholder="Search facility or MFL..."
                            />
                        </Field>
                    </div>
                )}
            </div>

            <div style={{ padding: "12px 20px", paddingBottom: "calc(12px + env(safe-area-inset-bottom, 0px))", background: T.card, borderTop: `1px solid ${T.borderLight}`, display: "flex", gap: 10 }}>
                {step > 1 && (
                    <button onClick={() => setStep(s => s - 1)} style={{
                        flex: 1, padding: 14, borderRadius: T.radiusSm,
                        background: T.bg, border: `1px solid ${T.border}`,
                        color: T.text, fontSize: 14, fontWeight: 600, cursor: "pointer",
                    }}>
                        Back
                    </button>
                )}
                <button onClick={handleContinue} disabled={saving || !stepValid} style={{
                    flex: step > 1 ? 2 : 1, padding: 14, borderRadius: T.radiusSm, border: "none",
                    background: (saving || !stepValid) ? T.borderLight : T.gradientPrimary,
                    color: (saving || !stepValid) ? T.textMuted : "white", fontSize: 15, fontWeight: 700,
                    cursor: (saving || !stepValid) ? "not-allowed" : "pointer",
                }}>
                    {saving ? "Registering…" : step === 4 ? "Register" : "Continue"}
                </button>
            </div>
        </div>
    );
}
