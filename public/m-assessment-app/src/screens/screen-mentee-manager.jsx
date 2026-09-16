// screen-mentee-manager.jsx
import { useState, useEffect, useRef } from "react";
import { T } from "../constants.js";
import api from "../services/api.service.js";

function Avatar({ name, size = 38 }) {
    const initials = (name ?? "?").split(" ").map(p => p[0]).join("").slice(0, 2).toUpperCase();
    return (
        <div style={{
            width: size, height: size, borderRadius: "50%", background: T.primaryGhost,
            display: "flex", alignItems: "center", justifyContent: "center",
            fontWeight: 700, fontSize: size * 0.34, color: T.primary, flexShrink: 0,
        }}>
            {initials}
        </div>
    );
}

const inputStyle = (focused) => ({
    width: "100%", padding: "11px 12px", borderRadius: T.radiusSm,
    border: `1.5px solid ${focused ? T.primary : T.border}`,
    fontSize: 14, boxSizing: "border-box", background: "#fff",
    color: T.text, outline: "none", fontFamily: "inherit",
    boxShadow: focused ? `0 0 0 3px ${T.primaryGhost}` : "none",
    transition: "all 0.18s",
});

function SearchableDropdown({ options, value, onChange, getLabel = (i) => i?.name ?? "", placeholder, searchPlaceholder = "Search..." }) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState("");
    const selected = options.find(item => String(item.id) === String(value));
    const filtered = options.filter(item => getLabel(item).toLowerCase().includes(query.trim().toLowerCase()));
    const close = () => { setOpen(false); setQuery(""); };
    return (
        <div style={{ position: "relative" }} onBlur={(e) => { if (!e.currentTarget.contains(e.relatedTarget)) setTimeout(close, 120); }}>
            <button type="button" onClick={() => setOpen(p => !p)} style={{ ...inputStyle(false), textAlign: "left", cursor: "pointer", color: selected ? T.text : T.textMuted, minHeight: 42 }}>
                <span style={{ display: "block", overflow: "hidden", textOverflow: "ellipsis", whiteSpace: "nowrap" }}>
                    {selected ? getLabel(selected) : placeholder}
                </span>
            </button>
            {open && (
                <div style={{ position: "absolute", left: 0, right: 0, top: "calc(100% + 4px)", zIndex: 30, background: "#fff", border: `1px solid ${T.border}`, borderRadius: T.radiusSm, boxShadow: T.shadowMd, overflow: "hidden" }}>
                    <div style={{ padding: 8, borderBottom: `1px solid ${T.borderLight}` }}>
                        <input autoFocus value={query} onChange={e => setQuery(e.target.value)} placeholder={searchPlaceholder} style={{ ...inputStyle(false), padding: "9px 11px" }} />
                    </div>
                    <div style={{ maxHeight: 200, overflowY: "auto" }}>
                        {filtered.length === 0 && <div style={{ padding: "12px 14px", color: T.textMuted, fontSize: 13 }}>No results</div>}
                        {filtered.map(item => (
                            <button key={item.id} type="button" onMouseDown={e => e.preventDefault()} onClick={() => { onChange(String(item.id), item); close(); }}
                                style={{ width: "100%", padding: "10px 12px", border: "none", borderBottom: `1px solid ${T.borderLight}`, background: String(item.id) === String(value) ? T.primaryGhost : "#fff", color: T.text, textAlign: "left", cursor: "pointer", fontSize: 14, fontFamily: "inherit" }}>
                                {getLabel(item)}
                            </button>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}

// Bottom sheet wrapper
function Sheet({ show, onClose, title, children }) {
    return show ? (
        <div style={{ position: "fixed", inset: 0, background: "rgba(0,0,0,0.4)", zIndex: 300, display: "flex", alignItems: "flex-end" }} onClick={onClose}>
            <div style={{ background: "#fff", width: "100%", maxHeight: "88vh", borderRadius: "20px 20px 0 0", display: "flex", flexDirection: "column", boxShadow: "0 -8px 40px rgba(0,0,0,0.12)" }} onClick={e => e.stopPropagation()}>
                <div style={{ padding: "10px 20px 0" }}>
                    <div style={{ width: 40, height: 4, borderRadius: 2, background: T.border, margin: "0 auto 14px" }} />
                    {title && <div style={{ fontWeight: 700, fontSize: 16, color: T.text, marginBottom: 14 }}>{title}</div>}
                </div>
                <div style={{ flex: 1, overflowY: "auto", padding: "0 20px 40px" }}>
                    {children}
                </div>
            </div>
        </div>
    ) : null;
}

export function MenteeManagerScreen({ cls, onBack, confirm = (o) => Promise.resolve(window.confirm(o?.title ?? "Confirm?")) }) {
    const [mentees, setMentees]           = useState([]);
    const [rosterLoading, setRosterLoading] = useState(true);
    const [sheet, setSheet]               = useState(null); // null | 'search' | 'create' | 'editEmail' | 'inviteLink'
    const [editingMentee, setEditingMentee] = useState(null);
    const [editEmail, setEditEmail]       = useState("");
    const [editEmailFocused, setEditEmailFocused] = useState(false);
    const [savingEmail, setSavingEmail]   = useState(false);

    // Search state
    const [search, setSearch]             = useState("");
    const [results, setResults]           = useState([]);
    const [searching, setSearching]       = useState(false);
    const [adding, setAdding]             = useState(null);

    // Create state
    const [creating, setCreating]         = useState(false);
    const [newMentee, setNewMentee]       = useState({ first_name: "", middle_name: "", last_name: "", email: "", phone: "", cadre_id: "", department_id: "", facility_id: cls.facility_id ? String(cls.facility_id) : "" });
    const [newMenteeLookup, setNewMenteeLookup] = useState({ loading: false, user: null, checkedEmail: "" });
    const [cadres, setCadres]             = useState([]);
    const [departments, setDepartments]   = useState([]);
    const [facilities, setFacilities]     = useState([]);

    // Invite state
    const [enrollLink, setEnrollLink]     = useState(null);
    const [linkLoading, setLinkLoading]   = useState(false);
    const [sharing, setSharing]           = useState(null); // participantId being shared
    const [copied, setCopied]             = useState(false);
    const [linkSharing, setLinkSharing]   = useState(false);
    const [linkCopied, setLinkCopied]     = useState(false);

    const [removing, setRemoving]         = useState(null);
    const [error, setError]               = useState(null);

    const searchTimer = useRef(null);
    const emailLookupTimer = useRef(null);

    // Load roster
    useEffect(() => {
        setRosterLoading(true);
        api.participants.list(cls.id)
            .then(d => setMentees(Array.isArray(d?.data) ? d.data : []))
            .catch(() => {})
            .finally(() => setRosterLoading(false));
    }, [cls.id]);

    // Load enrollment link
    useEffect(() => {
        setLinkLoading(true);
        api.classLifecycle.enrollmentLink(cls.id)
            .then(d => setEnrollLink(d?.data ?? null))
            .catch(() => {})
            .finally(() => setLinkLoading(false));
    }, [cls.id]);

    // Load lookup data for create form
    useEffect(() => {
        api.lookups.cadres().then(d => setCadres(Array.isArray(d?.data) ? d.data : Array.isArray(d) ? d : [])).catch(() => {});
        api.lookups.departments().then(d => setDepartments(Array.isArray(d?.data) ? d.data : Array.isArray(d) ? d : [])).catch(() => {});
        api.facilities.list().then(list => setFacilities((Array.isArray(list?.data) ? list.data : Array.isArray(list) ? list : []).map(f => ({ ...f, label: f?.mfl_code ? `${f.mfl_code} - ${f.name}` : f?.name })).filter(f => f.id && f.name))).catch(() => {});
    }, []);

    // Debounced user search
    useEffect(() => {
        clearTimeout(searchTimer.current);
        if (search.trim().length < 2) { setResults([]); return; }
        searchTimer.current = setTimeout(() => {
            setSearching(true);
            api.lookups.userSearch(search.trim())
                .then(d => setResults(Array.isArray(d?.data) ? d.data : []))
                .catch(() => setResults([]))
                .finally(() => setSearching(false));
        }, 400);
        return () => clearTimeout(searchTimer.current);
    }, [search]);

    // Email lookup for create form
    useEffect(() => {
        clearTimeout(emailLookupTimer.current);
        const email = newMentee.email.trim();
        if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { setNewMenteeLookup({ loading: false, user: null, checkedEmail: "" }); return; }
        emailLookupTimer.current = setTimeout(() => {
            setNewMenteeLookup({ loading: true, user: null, checkedEmail: email });
            (async () => {
                let found = null;
                try { const res = await api.lookups.userByEmail(email); found = res?.data ?? null; } catch {}
                if (!found) {
                    try {
                        const res = await api.lookups.userSearch(email, 20);
                        const list = Array.isArray(res?.data) ? res.data : [];
                        found = list.find(u => String(u.email ?? "").trim().toLowerCase() === email.toLowerCase()) ?? null;
                    } catch {}
                }
                setNewMenteeLookup({ loading: false, user: found, checkedEmail: email });
                if (found) setNewMentee(v => ({ ...v, first_name: found.first_name ?? "", middle_name: found.middle_name ?? "", last_name: found.last_name ?? "", phone: found.phone ?? "", cadre_id: found.cadre_id ? String(found.cadre_id) : "", department_id: found.department_id ? String(found.department_id) : "", facility_id: found.facility_id ? String(found.facility_id) : "" }));
            })();
        }, 500);
        return () => clearTimeout(emailLookupTimer.current);
    }, [newMentee.email]);

    const enrolledIds = new Set(mentees.map(m => m.user_id ?? m.id));

    const handleAdd = async (user) => {
        setAdding(user.id); setError(null);
        try {
            const res = await api.classLifecycle.enrollMentee(cls.id, user.id);
            setMentees(prev => [...prev, { id: res?.data?.participant_id, user_id: user.id, name: user.name, email: user.email, invitation_sent_at: null }]);
            setSearch(""); setResults([]);
            setSheet(null);
        } catch (e) {
            setError(e.message ?? "Could not add mentee.");
        } finally {
            setAdding(null);
        }
    };

    const resolveExistingUserByEmail = async (email) => {
        try { const res = await api.lookups.userByEmail(email); if (res?.data) return res.data; } catch {}
        try {
            const res = await api.lookups.userSearch(email, 20);
            const list = Array.isArray(res?.data) ? res.data : [];
            return list.find(u => String(u.email ?? "").trim().toLowerCase() === email.toLowerCase()) ?? null;
        } catch { return null; }
    };

    const handleCreate = async () => {
        const firstName = newMentee.first_name.trim(), lastName = newMentee.last_name.trim(), email = newMentee.email.trim();
        if (!email) { setError("Email is required."); return; }
        let foundUser = newMenteeLookup.user && newMenteeLookup.checkedEmail === email ? newMenteeLookup.user : null;
        if (!foundUser) foundUser = await resolveExistingUserByEmail(email);
        if (foundUser) {
            if (enrolledIds.has(foundUser.id)) { setError("This mentee is already enrolled."); return; }
            await handleAdd(foundUser);
            setNewMentee({ first_name: "", middle_name: "", last_name: "", email: "", phone: "", cadre_id: "", department_id: "", facility_id: cls.facility_id ? String(cls.facility_id) : "" });
            setNewMenteeLookup({ loading: false, user: null, checkedEmail: "" });
            return;
        }
        if (!firstName || !lastName || !email) { setError("First name, last name, and email are required."); return; }
        setCreating(true); setError(null);
        try {
            const res = await api.classLifecycle.createMentee(cls.id, { first_name: firstName, middle_name: newMentee.middle_name.trim() || null, last_name: lastName, email, phone: newMentee.phone.trim() || null, cadre_id: newMentee.cadre_id ? parseInt(newMentee.cadre_id) : null, department_id: newMentee.department_id ? parseInt(newMentee.department_id) : null, facility_id: newMentee.facility_id ? parseInt(newMentee.facility_id) : null });
            const data = res?.data ?? {};
            setMentees(prev => [...prev, { id: data.participant_id, user_id: data.user_id, name: data.name ?? `${firstName} ${lastName}`, email: data.email ?? email, invitation_sent_at: null }]);
            setNewMentee({ first_name: "", middle_name: "", last_name: "", email: "", phone: "", cadre_id: "", department_id: "", facility_id: cls.facility_id ? String(cls.facility_id) : "" });
            setNewMenteeLookup({ loading: false, user: null, checkedEmail: "" });
            setSheet(null);
        } catch (e) {
            setError(e.message ?? "Could not create mentee.");
        } finally {
            setCreating(false);
        }
    };

    const handleRemove = async (mentee) => {
        const ok = await confirm({ title: `Remove ${mentee.name}?`, message: "They can be re-enrolled later.", confirmLabel: "Remove", danger: true });
        if (!ok) return;
        setRemoving(mentee.id); setError(null);
        try {
            await api.classLifecycle.removeMentee(cls.id, mentee.id);
            setMentees(prev => prev.filter(m => m.id !== mentee.id));
        } catch (e) {
            setError(e.message ?? "Could not remove mentee.");
        } finally {
            setRemoving(null);
        }
    };

    const handleSaveEmail = async () => {
        if (!editEmail.trim() || !editingMentee) return;
        setSavingEmail(true); setError(null);
        try {
            await api.classLifecycle.updateMentee(cls.id, editingMentee.id, { email: editEmail.trim() });
            setMentees(prev => prev.map(m => m.id === editingMentee.id ? { ...m, email: editEmail.trim() } : m));
            setSheet(null); setEditingMentee(null); setEditEmail("");
        } catch (e) {
            setError(e.message ?? "Could not update email.");
        } finally {
            setSavingEmail(false);
        }
    };

    const handleShareInvite = async (mentee) => {
        if (!enrollLink?.url) return;
        const shareText = `Hi ${mentee.name?.split(" ")[0] ?? "there"}, you've been invited to join the "${cls.name}" class on the MNCH Mentorship Platform. Use this link to enroll:`;
        setSharing(mentee.id);
        try {
            if (navigator.share) {
                await navigator.share({ title: "MNCH Class Enrollment", text: shareText, url: enrollLink.url });
            } else {
                await navigator.clipboard.writeText(`${shareText}\n${enrollLink.url}`);
                setCopied(mentee.id);
                setTimeout(() => setCopied(null), 2500);
            }
            // Mark as invited in backend
            try {
                const res = await api.classLifecycle.markInvited(cls.id, mentee.id);
                const ts = res?.data?.invitation_sent_at ?? new Date().toISOString();
                setMentees(prev => prev.map(m => m.id === mentee.id ? { ...m, invitation_sent_at: ts } : m));
            } catch {}
        } catch (e) {
            // User cancelled share — silently ignore
        } finally {
            setSharing(null);
        }
    };

    const handleShareLink = async () => {
        if (!enrollLink?.url) return;
        const shareText = `Join the "${cls.name}" class on the MNCH Mentorship Platform. Use this link to self-enroll:`;
        setLinkSharing(true);
        try {
            if (navigator.share) {
                await navigator.share({ title: "MNCH Class Enrollment", text: shareText, url: enrollLink.url });
            } else {
                await navigator.clipboard.writeText(`${shareText}\n${enrollLink.url}`);
                setLinkCopied(true);
                setTimeout(() => setLinkCopied(false), 2500);
            }
        } catch {
            // user cancelled — ignore
        } finally {
            setLinkSharing(false);
        }
    };

    const handleRegenerate = async () => {
        const ok = await confirm({ title: "Regenerate enrollment link?", message: "The current link will be invalidated. Anyone who had it won't be able to use it.", confirmLabel: "Regenerate" });
        if (!ok) return;
        setLinkLoading(true);
        try {
            const res = await api.classLifecycle.regenerateToken(cls.id);
            setEnrollLink(res?.data ?? null);
        } catch (e) {
            setError(e.message ?? "Failed to regenerate.");
        } finally {
            setLinkLoading(false);
        }
    };

    return (
        <div style={{ display: "flex", flexDirection: "column", height: "100%", background: T.bg }}>
            {/* Header */}
            <div style={{ background: T.gradientHero, padding: "44px 20px 16px", position: "relative", overflow: "hidden" }}>
                <div style={{ position: "absolute", width: 140, height: 140, borderRadius: "50%", background: "radial-gradient(circle, rgba(79,106,245,0.20) 0%, transparent 70%)", top: -40, right: -30 }} />
                <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
                    <button onClick={onBack} style={{ background: "rgba(255,255,255,0.12)", border: "none", cursor: "pointer", padding: "6px 10px", borderRadius: 10, display: "flex", alignItems: "center", gap: 4, flexShrink: 0 }}>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="2.5"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                        <span style={{ fontSize: 12, color: "rgba(255,255,255,0.85)", fontWeight: 600 }}>Back</span>
                    </button>
                    <div style={{ flex: 1 }}>
                        <div style={{ fontWeight: 800, fontSize: 16, color: "white" }}>Manage Mentees</div>
                        <div style={{ fontSize: 12, color: "rgba(255,255,255,0.55)" }}>{cls.name} · {mentees.length} enrolled</div>
                    </div>
                </div>
            </div>

            <div style={{ flex: 1, overflowY: "auto", padding: "14px 16px 80px" }}>
                {error && (
                    <div style={{ background: "#FEF2F2", border: "1px solid #FECACA", borderRadius: T.radiusSm, padding: "10px 14px", marginBottom: 12, color: "#DC2626", fontSize: 13 }}>
                        {error}
                    </div>
                )}

                {/* Action buttons */}
                <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 10, marginBottom: 16 }}>
                    <button onClick={() => { setError(null); setSearch(""); setResults([]); setSheet("search"); }} style={{
                        padding: "11px 8px", borderRadius: T.radiusSm, border: `1.5px solid ${T.primary}`,
                        background: T.primaryGhost, color: T.primary, fontSize: 13, fontWeight: 700,
                        cursor: "pointer", display: "flex", alignItems: "center", justifyContent: "center", gap: 6,
                    }}>
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
                        Add from List
                    </button>
                    <button onClick={() => { setError(null); setSheet("create"); }} style={{
                        padding: "11px 8px", borderRadius: T.radiusSm, border: "none",
                        background: T.gradientPrimary, color: "#fff", fontSize: 13, fontWeight: 700,
                        cursor: "pointer", display: "flex", alignItems: "center", justifyContent: "center", gap: 6,
                        boxShadow: `0 4px 14px ${T.primaryGlow}`,
                    }}>
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
                        Register New
                    </button>
                </div>

                {/* Enrollment link share card */}
                {(enrollLink?.url || linkLoading) && (
                    <div style={{
                        background: T.card, border: `1px solid ${T.border}`,
                        borderRadius: T.radius, marginBottom: 16,
                        boxShadow: T.shadowCard, overflow: "hidden",
                    }}>
                        {/* Teal accent strip */}
                        <div style={{ height: 3, background: T.gradientPrimary }} />
                        <div style={{ padding: "14px 16px" }}>
                            <div style={{ display: "flex", alignItems: "center", gap: 8, marginBottom: 10 }}>
                                <div style={{ width: 32, height: 32, borderRadius: 10, background: T.primaryGhost, display: "flex", alignItems: "center", justifyContent: "center", flexShrink: 0 }}>
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke={T.primary} strokeWidth="2"><path d="M10 13a5 5 0 007.54.54l3-3a5 5 0 00-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 00-7.54-.54l-3 3a5 5 0 007.07 7.07l1.71-1.71"/></svg>
                                </div>
                                <div style={{ flex: 1 }}>
                                    <div style={{ fontSize: 13, fontWeight: 700, color: T.text }}>Self-Enrollment Link</div>
                                    <div style={{ fontSize: 11, color: T.textSub }}>Share with mentees to let them self-enroll</div>
                                </div>
                                <button onClick={handleRegenerate} disabled={linkLoading} style={{ fontSize: 10, color: T.textMuted, background: "none", border: `1px solid ${T.border}`, borderRadius: 6, cursor: "pointer", padding: "3px 8px", flexShrink: 0 }}>
                                    {linkLoading ? "…" : "Regenerate"}
                                </button>
                            </div>

                            {enrollLink?.url && (
                                <div style={{ background: T.bg, borderRadius: T.radiusXs, padding: "8px 10px", marginBottom: 12, fontSize: 11, color: T.textSub, wordBreak: "break-all", lineHeight: 1.5, border: `1px solid ${T.borderLight}` }}>
                                    {enrollLink.url}
                                </div>
                            )}

                            <div style={{ display: "grid", gridTemplateColumns: "1fr auto", gap: 8 }}>
                                <button
                                    onClick={handleShareLink}
                                    disabled={linkSharing || !enrollLink?.url}
                                    style={{
                                        padding: "10px 0", borderRadius: T.radiusSm, border: "none",
                                        background: linkCopied ? "#10B981" : T.gradientPrimary,
                                        color: "#fff", fontSize: 14, fontWeight: 700,
                                        cursor: linkSharing || !enrollLink?.url ? "not-allowed" : "pointer",
                                        display: "flex", alignItems: "center", justifyContent: "center", gap: 8,
                                        boxShadow: linkCopied || !enrollLink?.url ? "none" : `0 4px 16px ${T.primaryGlow}`,
                                        transition: "background 0.25s",
                                        opacity: linkSharing ? 0.7 : 1,
                                    }}
                                >
                                    {linkCopied ? (
                                        <>
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                            Copied!
                                        </>
                                    ) : (
                                        <>
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="2"><path d="M4 12v8a2 2 0 002 2h12a2 2 0 002-2v-8"/><polyline points="16 6 12 2 8 6"/><line x1="12" y1="2" x2="12" y2="15"/></svg>
                                            {linkSharing ? "Opening share…" : "Share Enrollment Link"}
                                        </>
                                    )}
                                </button>

                                {/* Copy-only fallback button */}
                                <button
                                    onClick={async () => {
                                        if (!enrollLink?.url) return;
                                        await navigator.clipboard.writeText(enrollLink.url);
                                        setLinkCopied(true);
                                        setTimeout(() => setLinkCopied(false), 2000);
                                    }}
                                    disabled={!enrollLink?.url}
                                    title="Copy link"
                                    style={{
                                        width: 42, borderRadius: T.radiusSm, border: `1.5px solid ${T.border}`,
                                        background: T.card, color: T.textSub, cursor: "pointer",
                                        display: "flex", alignItems: "center", justifyContent: "center",
                                    }}
                                >
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                )}

                {/* Roster */}
                {rosterLoading && <div style={{ color: T.textSub, textAlign: "center", paddingTop: 32 }}>Loading mentees…</div>}

                {!rosterLoading && mentees.length === 0 && (
                    <div style={{ textAlign: "center", paddingTop: 40 }}>
                        <div style={{ width: 60, height: 60, borderRadius: "50%", background: T.primaryGhost, display: "flex", alignItems: "center", justifyContent: "center", margin: "0 auto 14px" }}>
                            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke={T.primary} strokeWidth="1.8"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
                        </div>
                        <div style={{ color: T.text, fontWeight: 600, fontSize: 15, marginBottom: 6 }}>No mentees enrolled yet</div>
                        <div style={{ color: T.textSub, fontSize: 13, marginBottom: 20 }}>Use the buttons above to add or register mentees.</div>
                    </div>
                )}

                {!rosterLoading && mentees.map(m => {
                    const hasEmail = !!m.email;
                    const invited = !!m.invitation_sent_at;
                    const isRemoving = removing === m.id;
                    const isSharing = sharing === m.id;
                    const wasCopied = copied === m.id;

                    return (
                        <div key={m.id} style={{
                            background: T.card, borderRadius: T.radiusSm,
                            marginBottom: 10, boxShadow: T.shadowCard,
                            border: `1px solid ${T.border}`, overflow: "hidden",
                            opacity: isRemoving ? 0.5 : 1, transition: "opacity 0.2s",
                        }}>
                            {/* Main row */}
                            <div style={{ padding: "12px 14px", display: "flex", alignItems: "flex-start", gap: 12 }}>
                                <Avatar name={m.name} />
                                <div style={{ flex: 1, minWidth: 0 }}>
                                    <div style={{ fontWeight: 700, color: T.text, fontSize: 14, marginBottom: 2 }}>{m.name}</div>
                                    {hasEmail ? (
                                        <div style={{ fontSize: 12, color: T.textSub }}>{m.email}</div>
                                    ) : (
                                        <div style={{ display: "flex", alignItems: "center", gap: 6 }}>
                                            <span style={{ fontSize: 12, color: "#F59E0B", fontWeight: 600 }}>Email needed</span>
                                            <button
                                                onClick={() => { setEditingMentee(m); setEditEmail(""); setSheet("editEmail"); }}
                                                style={{ width: 20, height: 20, borderRadius: "50%", background: "#FEF3C7", border: "1px solid #FDE68A", display: "flex", alignItems: "center", justifyContent: "center", cursor: "pointer", padding: 0 }}
                                            >
                                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="#92400E" strokeWidth="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                            </button>
                                        </div>
                                    )}
                                </div>
                                <button onClick={() => handleRemove(m)} disabled={isRemoving} style={{
                                    padding: "4px 10px", borderRadius: T.radiusXs, border: "1px solid #FECACA",
                                    background: "#FEF2F2", color: "#DC2626", fontSize: 11, fontWeight: 600,
                                    cursor: isRemoving ? "not-allowed" : "pointer", flexShrink: 0,
                                }}>
                                    Remove
                                </button>
                            </div>

                            {/* Invite action row */}
                            <div style={{ padding: "8px 14px 12px 66px", display: "flex", alignItems: "center", gap: 8 }}>
                                <button
                                    onClick={() => handleShareInvite(m)}
                                    disabled={isSharing || !enrollLink?.url}
                                    style={{
                                        display: "flex", alignItems: "center", gap: 5,
                                        padding: "6px 14px", borderRadius: T.radiusXs, border: "none",
                                        background: wasCopied ? "#10B981" : invited ? T.primaryGhost : T.gradientPrimary,
                                        color: wasCopied ? "#fff" : invited ? T.primary : "#fff",
                                        fontSize: 12, fontWeight: 700, cursor: isSharing || !enrollLink?.url ? "not-allowed" : "pointer",
                                        opacity: isSharing ? 0.7 : 1,
                                        transition: "all 0.2s",
                                    }}
                                >
                                    {wasCopied ? (
                                        <>
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                            Copied!
                                        </>
                                    ) : (
                                        <>
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M4 12v8a2 2 0 002 2h12a2 2 0 002-2v-8"/><polyline points="16 6 12 2 8 6"/><line x1="12" y1="2" x2="12" y2="15"/></svg>
                                            {isSharing ? "Sharing…" : invited ? "Resend Invite" : "Send Invite"}
                                        </>
                                    )}
                                </button>
                                {invited && !wasCopied && (
                                    <span style={{ fontSize: 11, color: T.textSub }}>
                                        Sent {new Date(m.invitation_sent_at).toLocaleDateString()}
                                    </span>
                                )}
                            </div>
                        </div>
                    );
                })}
            </div>

            {/* ── Search sheet ── */}
            <Sheet show={sheet === "search"} onClose={() => { setSheet(null); setSearch(""); setResults([]); }} title="Add from List">
                <div style={{ position: "relative", marginBottom: 12 }}>
                    <input value={search} onChange={e => setSearch(e.target.value)} placeholder="Search by name, email, phone, or MFL..." autoFocus style={{ ...inputStyle(false), padding: "11px 40px 11px 14px" }} />
                    <svg style={{ position: "absolute", right: 12, top: "50%", transform: "translateY(-50%)" }} width="16" height="16" viewBox="0 0 24 24" fill="none" stroke={T.textMuted} strokeWidth="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
                </div>
                {searching && <div style={{ color: T.textSub, textAlign: "center", paddingTop: 16 }}>Searching…</div>}
                {!searching && search.length >= 2 && results.length === 0 && <div style={{ color: T.textSub, textAlign: "center", paddingTop: 16 }}>No users found.</div>}
                {search.length < 2 && <div style={{ color: T.textMuted, textAlign: "center", paddingTop: 16, fontSize: 13 }}>Type at least 2 characters to search</div>}
                {results.map(u => {
                    const alreadyEnrolled = enrolledIds.has(u.id);
                    const isAdding = adding === u.id;
                    return (
                        <div key={u.id} style={{ background: T.card, borderRadius: T.radiusSm, padding: "11px 14px", marginBottom: 8, boxShadow: T.shadowCard, border: `1px solid ${T.border}`, display: "flex", alignItems: "center", gap: 12, opacity: alreadyEnrolled ? 0.5 : 1 }}>
                            <Avatar name={u.name} />
                            <div style={{ flex: 1, minWidth: 0 }}>
                                <div style={{ fontWeight: 600, color: T.text, fontSize: 14 }}>{u.name}</div>
                                <div style={{ fontSize: 11, color: T.textSub }}>{[u.email, u.phone, u.facility_name].filter(Boolean).join(" · ")}</div>
                            </div>
                            {alreadyEnrolled ? (
                                <span style={{ fontSize: 11, color: "#065F46", background: "#D1FAE5", padding: "3px 8px", borderRadius: 20, flexShrink: 0 }}>Enrolled</span>
                            ) : (
                                <button onClick={() => handleAdd(u)} disabled={isAdding} style={{ padding: "6px 14px", borderRadius: T.radiusSm, border: "none", background: isAdding ? T.border : T.gradientPrimary, color: "#fff", fontSize: 13, fontWeight: 700, cursor: isAdding ? "not-allowed" : "pointer", flexShrink: 0 }}>
                                    {isAdding ? "Adding…" : "Add"}
                                </button>
                            )}
                        </div>
                    );
                })}
            </Sheet>

            {/* ── Create sheet ── */}
            <Sheet show={sheet === "create"} onClose={() => setSheet(null)} title="Register New Mentee">
                <div style={{ fontSize: 12, color: T.textSub, marginBottom: 14, lineHeight: 1.6 }}>
                    Start with the email. If an account already exists, the mentee is enrolled directly. Otherwise, fill in the details to create a new account (default password: 123456).
                </div>
                {error && <div style={{ background: "#FEF2F2", border: "1px solid #FECACA", borderRadius: T.radiusSm, padding: "10px 14px", marginBottom: 10, color: "#DC2626", fontSize: 13 }}>{error}</div>}
                <input value={newMentee.email} onChange={e => setNewMentee(v => ({ ...v, email: e.target.value }))} placeholder="Email address *" style={{ ...inputStyle(false), marginBottom: 6 }} />
                {newMenteeLookup.loading && <div style={{ fontSize: 12, color: T.textSub, marginBottom: 8 }}>Checking email…</div>}
                {!newMenteeLookup.loading && newMenteeLookup.checkedEmail && (
                    <div style={{ fontSize: 12, color: newMenteeLookup.user ? "#065F46" : "#1D4ED8", marginBottom: 10, fontWeight: 600 }}>
                        {newMenteeLookup.user ? "Existing user found — details loaded." : "No account found — complete details below."}
                    </div>
                )}
                <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 8, marginBottom: 8 }}>
                    <input value={newMentee.first_name} onChange={e => setNewMentee(v => ({ ...v, first_name: e.target.value }))} placeholder="First name *" style={inputStyle(false)} />
                    <input value={newMentee.last_name} onChange={e => setNewMentee(v => ({ ...v, last_name: e.target.value }))} placeholder="Last name *" style={inputStyle(false)} />
                </div>
                <input value={newMentee.middle_name} onChange={e => setNewMentee(v => ({ ...v, middle_name: e.target.value }))} placeholder="Middle name (optional)" style={{ ...inputStyle(false), marginBottom: 8 }} />
                <input value={newMentee.phone} onChange={e => setNewMentee(v => ({ ...v, phone: e.target.value }))} placeholder="Phone (optional)" style={{ ...inputStyle(false), marginBottom: 8 }} />
                <div style={{ marginBottom: 8 }}><SearchableDropdown options={cadres} value={newMentee.cadre_id} onChange={(v) => setNewMentee(p => ({ ...p, cadre_id: v }))} placeholder="Cadre (optional)" searchPlaceholder="Search cadre..." /></div>
                <div style={{ marginBottom: 8 }}><SearchableDropdown options={departments} value={newMentee.department_id} onChange={(v) => setNewMentee(p => ({ ...p, department_id: v }))} placeholder="Department (optional)" searchPlaceholder="Search department..." /></div>
                <div style={{ marginBottom: 16 }}><SearchableDropdown options={facilities} value={newMentee.facility_id} onChange={(v) => setNewMentee(p => ({ ...p, facility_id: v }))} getLabel={(f) => f?.label || f?.name || ""} placeholder="Facility (optional)" searchPlaceholder="Search facility or MFL..." /></div>
                <button onClick={handleCreate} disabled={creating} style={{ width: "100%", padding: "13px 0", borderRadius: T.radiusSm, border: "none", background: creating ? T.border : T.gradientPrimary, color: "#fff", fontSize: 14, fontWeight: 700, cursor: creating ? "not-allowed" : "pointer", boxShadow: creating ? "none" : `0 4px 14px ${T.primaryGlow}` }}>
                    {creating ? "Creating…" : newMenteeLookup.user ? "Add Existing Mentee" : "Create and Add Mentee"}
                </button>
            </Sheet>

            {/* ── Edit email sheet ── */}
            <Sheet show={sheet === "editEmail"} onClose={() => { setSheet(null); setEditingMentee(null); setEditEmail(""); }} title={`Add Email — ${editingMentee?.name ?? ""}`}>
                <div style={{ fontSize: 13, color: T.textSub, marginBottom: 14, lineHeight: 1.6 }}>
                    Adding an email allows this mentee to receive their enrollment invite and log in to the platform.
                </div>
                {error && <div style={{ background: "#FEF2F2", border: "1px solid #FECACA", borderRadius: T.radiusSm, padding: "10px 14px", marginBottom: 10, color: "#DC2626", fontSize: 13 }}>{error}</div>}
                <input
                    value={editEmail}
                    onChange={e => setEditEmail(e.target.value)}
                    onFocus={() => setEditEmailFocused(true)}
                    onBlur={() => setEditEmailFocused(false)}
                    placeholder="Email address"
                    type="email"
                    autoFocus
                    style={{ ...inputStyle(editEmailFocused), marginBottom: 16 }}
                />
                <button onClick={handleSaveEmail} disabled={savingEmail || !editEmail.trim()} style={{ width: "100%", padding: "13px 0", borderRadius: T.radiusSm, border: "none", background: savingEmail || !editEmail.trim() ? T.border : T.gradientPrimary, color: "#fff", fontSize: 14, fontWeight: 700, cursor: savingEmail || !editEmail.trim() ? "not-allowed" : "pointer" }}>
                    {savingEmail ? "Saving…" : "Save Email"}
                </button>
            </Sheet>
        </div>
    );
}
