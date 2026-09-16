import { useState } from "react";
import { T } from "../constants.js";
import { Avatar } from "../components/shared-components.jsx";
import api from "../services/api.service.js";

export function ProfileScreen({ user, assessments, onUpdateUser, onLogout }) {
  const [editing,    setEditing]    = useState(false);
  const [changingPw, setChangingPw] = useState(false);
  const [form,       setForm]       = useState({
    name:     user?.name     ?? "",
    phone:    user?.phone    ?? "",
    county:   user?.county   ?? "",
    facility: user?.facility ?? "",
  });
  const [pw,      setPw]      = useState({ current:"", newPw:"", confirm:"" });
  const [saving,  setSaving]  = useState(false);
  const [msg,     setMsg]     = useState(null); // { type:"success"|"error", text }

  const flash = (type, text) => {
    setMsg({ type, text });
    setTimeout(() => setMsg(null), 3500);
  };

  // ── Profile update ─────────────────────────────────────────────────────────
  const handleSaveProfile = async () => {
    setSaving(true);
    try {
      const res = await api.profile.update(form);
      onUpdateUser({ ...(user ?? {}), ...(res?.user ?? res ?? {}), ...form });
      setEditing(false);
      flash("success", "Profile updated successfully.");
    } catch (e) {
      flash("error", e.message || "Failed to update profile.");
    } finally {
      setSaving(false);
    }
  };

  // ── Password change ────────────────────────────────────────────────────────
  const handleChangePassword = async () => {
    if (!pw.current)             return flash("error", "Enter your current password.");
    if (pw.newPw.length < 8)     return flash("error", "New password must be at least 8 characters.");
    if (pw.newPw !== pw.confirm) return flash("error", "Passwords do not match.");
    setSaving(true);
    try {
      await api.profile.changePassword({
        current_password:      pw.current,
        password:              pw.newPw,
        password_confirmation: pw.confirm,
      });
      setChangingPw(false);
      setPw({ current:"", newPw:"", confirm:"" });
      flash("success", "Password changed successfully.");
    } catch (e) {
      flash("error", e.message || "Failed to change password.");
    } finally {
      setSaving(false);
    }
  };

  const list      = assessments ?? [];
  const completed = list.filter(a => a.status === "completed");
  const avgScore  = completed.length
    ? Math.round(completed.reduce((s, a) => s + (a.overall_percentage ?? 0), 0) / completed.length)
    : 0;

  return (
    <div style={{ height:"100%", overflowY:"auto", background:T.bg }}>
      {/* Hero */}
      <div style={{
        background:"linear-gradient(135deg, #1E1B4B 0%, #3730A3 100%)",
        padding:"52px 20px 32px", textAlign:"center",
      }}>
        <Avatar initials={user?.initials ?? "??"} size={72} color="rgba(255,255,255,0.2)" />
        <div style={{ color:"white", fontSize:20, fontWeight:800, marginTop:12 }}>
          {user?.name ?? "—"}
        </div>
        <div style={{ color:"rgba(255,255,255,0.6)", fontSize:13, marginTop:4 }}>
          {[user?.role, user?.email].filter(Boolean).join(" · ")}
        </div>

        {/* Stats */}
        <div style={{ display:"flex", justifyContent:"center", gap:24, marginTop:16 }}>
          {[
            { label:"Assessments", value: list.length          },
            { label:"Completed",   value: completed.length     },
            { label:"Avg Score",   value: completed.length ? `${avgScore}%` : "—" },
          ].map(({ label, value }) => (
            <div key={label} style={{ textAlign:"center" }}>
              <div style={{ color:"white", fontSize:20, fontWeight:800 }}>{value}</div>
              <div style={{ color:"rgba(255,255,255,0.55)", fontSize:11, marginTop:2 }}>{label}</div>
            </div>
          ))}
        </div>
      </div>

      <div style={{ padding:"16px 16px 100px" }}>

        {/* Flash message */}
        {msg && (
          <div style={{
            background: msg.type === "success" ? "#D1FAE5" : "#FEE2E2",
            color:      msg.type === "success" ? "#065F46" : "#991B1B",
            borderRadius: T.radiusSm, padding:"10px 14px",
            fontSize:13, fontWeight:600, marginBottom:14,
          }}>
            {msg.type === "success" ? "✅" : "⚠️"} {msg.text}
          </div>
        )}

        {/* Profile card */}
        <div style={{ background:T.card, borderRadius:T.radius, padding:16, marginBottom:12, boxShadow:T.shadow }}>
          <div style={{ display:"flex", justifyContent:"space-between", alignItems:"center", marginBottom: editing ? 14 : 0 }}>
            <div style={{ fontSize:13, fontWeight:700, color:T.textMid }}>Profile</div>
            <button
              onClick={() => setEditing(!editing)}
              style={{
                background: editing ? "#FEE2E2" : T.borderLight,
                color:      editing ? "#EF4444" : T.textMid,
                border:"none", borderRadius:7, padding:"5px 12px",
                fontSize:12, fontWeight:700, cursor:"pointer",
              }}
            >
              {editing ? "Cancel" : "Edit"}
            </button>
          </div>

          {editing ? (
            <div style={{ display:"flex", flexDirection:"column", gap:12 }}>
              {[
                { key:"name",     label:"Full Name" },
                { key:"phone",    label:"Phone" },
                { key:"county",   label:"County" },
                { key:"facility", label:"Facility" },
              ].map(({ key, label }) => (
                <div key={key}>
                  <div style={{ fontSize:11, color:T.textMuted, fontWeight:600, marginBottom:5, textTransform:"uppercase", letterSpacing:0.6 }}>
                    {label}
                  </div>
                  <input
                    type="text"
                    value={form[key]}
                    onChange={e => setForm(p => ({ ...p, [key]: e.target.value }))}
                    style={{
                      width:"100%", padding:"10px 13px", borderRadius:T.radiusSm,
                      border:`2px solid ${T.border}`, fontSize:14, color:T.text,
                      outline:"none", boxSizing:"border-box", fontFamily:"inherit",
                      background:T.borderLight,
                    }}
                  />
                </div>
              ))}
              <button
                onClick={handleSaveProfile}
                disabled={saving}
                style={{
                  padding:"12px", borderRadius:T.radiusSm,
                  background: saving ? "#D1D5DB" : "linear-gradient(135deg, #064E3B, #059669)",
                  color:"white", border:"none", fontSize:14, fontWeight:700,
                  cursor: saving ? "not-allowed" : "pointer",
                }}
              >
                {saving ? "Saving…" : "Save Changes"}
              </button>
            </div>
          ) : (
            <div style={{ display:"grid", gridTemplateColumns:"1fr 1fr", gap:12, marginTop:12 }}>
              {[
                { label:"Name",     value: user?.name     ?? "—" },
                { label:"Email",    value: user?.email    ?? "—" },
                { label:"Phone",    value: user?.phone    ?? "—" },
                { label:"County",   value: user?.county   ?? "—" },
                { label:"Facility", value: user?.facility ?? "—" },
                { label:"Role",     value: user?.role     ?? "—" },
              ].map(({ label, value }) => (
                <div key={label}>
                  <div style={{ fontSize:10, color:T.textMuted, fontWeight:600, textTransform:"uppercase", letterSpacing:0.6 }}>
                    {label}
                  </div>
                  <div style={{ fontSize:13, color:T.text, fontWeight:600, marginTop:2, wordBreak:"break-word" }}>
                    {value}
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>

        {/* Security card */}
        <div style={{ background:T.card, borderRadius:T.radius, padding:16, marginBottom:12, boxShadow:T.shadow }}>
          <div style={{ display:"flex", justifyContent:"space-between", alignItems:"center", marginBottom: changingPw ? 14 : 0 }}>
            <div style={{ fontSize:13, fontWeight:700, color:T.textMid }}>Security</div>
            <button
              onClick={() => setChangingPw(!changingPw)}
              style={{
                background: changingPw ? "#FEE2E2" : T.borderLight,
                color:      changingPw ? "#EF4444" : T.textMid,
                border:"none", borderRadius:7, padding:"5px 12px",
                fontSize:12, fontWeight:700, cursor:"pointer",
              }}
            >
              {changingPw ? "Cancel" : "Change Password"}
            </button>
          </div>

          {changingPw && (
            <div style={{ display:"flex", flexDirection:"column", gap:12 }}>
              {[
                { key:"current", label:"Current Password" },
                { key:"newPw",   label:"New Password"     },
                { key:"confirm", label:"Confirm New Password" },
              ].map(({ key, label }) => (
                <div key={key}>
                  <div style={{ fontSize:11, color:T.textMuted, fontWeight:600, marginBottom:5 }}>{label}</div>
                  <input
                    type="password"
                    value={pw[key]}
                    onChange={e => setPw(p => ({ ...p, [key]: e.target.value }))}
                    style={{
                      width:"100%", padding:"10px 13px", borderRadius:T.radiusSm,
                      border:`2px solid ${T.border}`, fontSize:14, color:T.text,
                      outline:"none", boxSizing:"border-box", fontFamily:"inherit",
                      background:T.borderLight,
                    }}
                  />
                </div>
              ))}
              <button
                onClick={handleChangePassword}
                disabled={saving}
                style={{
                  padding:"12px", borderRadius:T.radiusSm,
                  background: saving ? "#D1D5DB" : "#1D4ED8",
                  color:"white", border:"none", fontSize:14, fontWeight:700,
                  cursor: saving ? "not-allowed" : "pointer",
                }}
              >
                {saving ? "Updating…" : "Update Password"}
              </button>
            </div>
          )}
        </div>

        {/* Sign out */}
        <button
          onClick={onLogout}
          style={{
            width:"100%", padding:14, borderRadius:T.radius,
            background:"#FEE2E2", color:"#EF4444",
            border:"none", fontSize:15, fontWeight:700, cursor:"pointer",
          }}
        >
          Sign Out
        </button>
      </div>
    </div>
  );
}
