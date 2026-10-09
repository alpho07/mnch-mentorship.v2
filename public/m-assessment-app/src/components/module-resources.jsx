import { useEffect, useState } from "react";
import { T } from "../constants.js";
import api from "../services/api.service.js";
import { saveFileOffline, openSavedFile, removeSavedFile, savedFileKeys, fileKey } from "../services/offline-files.js";

const SECTION_META = [
    ["introduction", "Introduction", "📖"],
    ["objectives", "Learning objectives", "🎯"],
    ["pre_tests", "Pre-test", "📝"],
    ["videos", "Learning video", "🎬"],
    ["case_scenarios", "Case scenario", "📄"],
    ["equipment", "Equipment & materials needed", "🧰"],
    ["debrief", "Debrief", "💬"],
    ["post_tests", "Post-test", "✅"],
];

function Block({ title, icon, children }) {
    return (
        <div style={{ background: T.card, borderRadius: T.radiusSm, boxShadow: T.shadowCard, marginBottom: 12, overflow: "hidden" }}>
            <div style={{ padding: "10px 14px", fontSize: 12, fontWeight: 800, color: T.text, borderBottom: `1px solid ${T.borderLight}` }}>{icon} {title}</div>
            <div style={{ padding: "10px 14px" }}>{children}</div>
        </div>
    );
}

const textStyle = { fontSize: 13, color: T.text, lineHeight: 1.5 };
const plain = (html) => (html ?? "").replace(/<[^>]*>/g, " ").replace(/\s+/g, " ").trim();

function ContentItem({ item }) {
    const text = plain(item.content);
    return (
        <div style={{ marginBottom: 10 }}>
            {item.title && <div style={{ ...textStyle, fontWeight: 700 }}>{item.title}</div>}
            {text && <div style={{ ...textStyle, color: T.textSub }}>{text}</div>}
            {item.video_url && <LinkButton url={item.video_url} label="▶ Watch video" />}
            {item.manual_reference_url && <LinkButton url={item.manual_reference_url} label="📘 Open manual" />}
        </div>
    );
}

function LinkButton({ url, label }) {
    return (
        <button onClick={() => window.open(url, "_blank")} style={{
            marginTop: 6, padding: "6px 12px", borderRadius: 9, border: `1.5px solid ${T.border}`,
            background: T.borderLight, color: T.primaryDark, fontSize: 12, fontWeight: 700, cursor: "pointer",
        }}>{label}</button>
    );
}

function ResourceRow({ resource, offline, saved, onSavedChange }) {
    const { file } = resource;
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState(null);

    const download = async () => {
        setBusy(true);
        setError(null);
        try {
            if (!saved) {
                await saveFileOffline(resource.id, file);
                onSavedChange(resource.id, true);
            }
            await openSavedFile(resource.id);
        } catch (e) {
            // A cancelled share sheet isn't an error worth showing.
            if (!/cancel/i.test(e?.message ?? "")) setError(e?.message || "Could not open the file.");
        } finally {
            setBusy(false);
        }
    };

    const remove = async () => {
        await removeSavedFile(resource.id);
        onSavedChange(resource.id, false);
    };

    const canDownload = saved || !offline;

    return (
        <div style={{ padding: "10px 0", borderBottom: `1px solid ${T.borderLight}` }}>
            <div style={{ ...textStyle, fontWeight: 700 }}>{resource.title}</div>
            <div style={{ fontSize: 11, color: T.textSub, marginTop: 2 }}>
                {[resource.type_label, resource.category, file?.size].filter(Boolean).join(" · ")}
            </div>
            {resource.description && <div style={{ fontSize: 12, color: T.textSub, marginTop: 4 }}>{resource.description}</div>}
            <div style={{ display: "flex", gap: 8, flexWrap: "wrap", alignItems: "center" }}>
                {file && (
                    <button disabled={!canDownload || busy} onClick={download} style={{
                        marginTop: 8, padding: "7px 14px", borderRadius: 9, border: "none",
                        background: canDownload && !busy ? T.gradientPrimary : T.border, color: canDownload && !busy ? "white" : T.textSub,
                        fontSize: 12, fontWeight: 800, cursor: canDownload && !busy ? "pointer" : "default",
                    }}>
                        {busy ? "⏳ Please wait…" : saved ? "📂 Open" : canDownload ? "⬇ Download" : "Not saved offline"}
                    </button>
                )}
                {file && saved && (
                    <>
                        <span style={{ marginTop: 8, fontSize: 11, color: "#065F46", fontWeight: 700 }}>✓ Saved on device</span>
                        <button onClick={remove} style={{ marginTop: 8, border: "none", background: "none", color: T.textSub, fontSize: 11, textDecoration: "underline", cursor: "pointer" }}>Remove</button>
                    </>
                )}
                {resource.external_url && <LinkButton url={resource.external_url} label="🔗 Open link" />}
            </div>
            {error && <div style={{ fontSize: 11, color: "#991B1B", marginTop: 6 }}>{error}</div>}
            {!resource.can_access && (
                <div style={{ fontSize: 11, color: "#92400E", marginTop: 6 }}>You don't have access to download this resource.</div>
            )}
        </div>
    );
}

function TestBlock({ test }) {
    const [open, setOpen] = useState(false);
    return (
        <div style={{ marginBottom: 8 }}>
            <div style={textStyle}>{test.title} <span style={{ color: T.textSub }}>· {test.question_count} questions</span></div>
            {test.questions?.length > 0 && (
                <button onClick={() => setOpen(o => !o)} style={{ border: "none", background: "none", color: T.primaryDark, fontSize: 12, fontWeight: 700, cursor: "pointer", padding: "4px 0" }}>
                    {open ? "Hide questions" : "Show questions & answers"}
                </button>
            )}
            {open && test.questions.map((q, i) => (
                <div key={q.id ?? i} style={{ margin: "8px 0", padding: "8px 10px", background: T.borderLight, borderRadius: 10 }}>
                    <div style={{ ...textStyle, fontWeight: 700 }}>{i + 1}. {q.question_text}</div>
                    {q.options.map((o, j) => (
                        <div key={j} style={{ fontSize: 12, padding: "2px 0", color: o.is_correct ? "#065F46" : T.textSub, fontWeight: o.is_correct ? 700 : 400 }}>
                            {o.is_correct ? "✓" : "○"} {o.option_text}
                        </div>
                    ))}
                    {q.explanation && <div style={{ fontSize: 11, color: T.textSub, marginTop: 4, fontStyle: "italic" }}>{q.explanation}</div>}
                </div>
            ))}
        </div>
    );
}

/** Module learning content + attached resources, same as the web "Module Resources" page. */
export function ModuleResources({ moduleId }) {
    const [data, setData] = useState(null);
    const [error, setError] = useState(null);
    const [loading, setLoading] = useState(true);
    const [saved, setSaved] = useState(new Set());
    const [online, setOnline] = useState(navigator.onLine);

    useEffect(() => {
        savedFileKeys().then(setSaved).catch(() => {});
        const up = () => setOnline(true), down = () => setOnline(false);
        window.addEventListener("online", up);
        window.addEventListener("offline", down);
        return () => { window.removeEventListener("online", up); window.removeEventListener("offline", down); };
    }, []);

    const onSavedChange = (resourceId, isSaved) => setSaved(prev => {
        const next = new Set(prev);
        isSaved ? next.add(fileKey(resourceId)) : next.delete(fileKey(resourceId));
        return next;
    });

    useEffect(() => {
        let active = true;
        api.modules.resources(moduleId)
            .then((d) => { if (active) setData(d?.data ? { ...d.data, _offline: d._offline } : null); })
            .catch((e) => { if (active) setError(e?.message || "Could not load module resources."); })
            .finally(() => { if (active) setLoading(false); });
        return () => { active = false; };
    }, [moduleId]);

    if (loading) return <div style={{ color: T.textSub, fontSize: 13, textAlign: "center", paddingTop: 24 }}>Loading resources…</div>;
    if (error) return <div style={{ background: "#FEE2E2", color: "#991B1B", borderRadius: T.radiusXs, padding: "10px 14px", fontSize: 13 }}>{error}</div>;
    if (!data) return <div style={{ color: T.textSub, fontSize: 13, textAlign: "center", paddingTop: 24 }}>No resources for this module.</div>;

    const offline = !!data._offline || !online;
    const hasDescription = !!plain(data.module?.description);

    return (
        <>
            {offline && (
                <div style={{ background: "#FEF3C7", color: "#92400E", borderRadius: T.radiusXs, padding: "8px 12px", fontSize: 12, marginBottom: 12 }}>
                    Offline — showing saved content. Files you've already downloaded still open; connect to download others.
                </div>
            )}

            {SECTION_META.map(([key, title, icon]) => {
                const items = data[key] ?? [];
                const showDescription = key === "introduction" && hasDescription;
                if (!items.length && !showDescription) return null;
                return (
                    <Block key={key} title={title} icon={icon}>
                        {showDescription && <div style={{ ...textStyle, color: T.textSub, marginBottom: items.length ? 10 : 0 }}>{plain(data.module.description)}</div>}
                        {items.map((item, i) => {
                            if (typeof item === "string") return <div key={i} style={{ ...textStyle, padding: "3px 0" }}>• {item}</div>;
                            if (key === "pre_tests" || key === "post_tests") return <TestBlock key={item.id ?? i} test={item} />;
                            return <ContentItem key={item.id ?? i} item={item} />;
                        })}
                    </Block>
                );
            })}

            <Block title={`Attached resources (${data.resources.length})`} icon="📁">
                {data.resources.length === 0
                    ? <div style={{ ...textStyle, color: T.textSub }}>No resources attached to this module.</div>
                    : data.resources.map((r) => <ResourceRow key={r.id} resource={r} offline={offline} saved={saved.has(fileKey(r.id))} onSavedChange={onSavedChange} />)}
            </Block>
        </>
    );
}
