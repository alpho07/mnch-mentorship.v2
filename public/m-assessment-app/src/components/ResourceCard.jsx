import { T } from "../constants.js";

const TYPE_COLORS = {
    mentorship_manual: { bg: "#EDE9FE", color: "#5B21B6", label: "Manual" },
    training:          { bg: "#DBEAFE", color: "#1D4ED8", label: "Training" },
    document:          { bg: "#F3F4F6", color: "#374151", label: "Document" },
    video:             { bg: "#FEE2E2", color: "#991B1B", label: "Video" },
};

export function ResourceCard({ resource }) {
    const type = TYPE_COLORS[resource.type] ?? TYPE_COLORS.document;
    const url = resource.url ?? resource.file_url;

    const handleOpen = () => {
        if (url) window.open(url, "_blank");
    };

    return (
        <div style={{ padding: "12px 14px", background: T.surface, borderRadius: T.radius, border: `1px solid ${T.border}`, marginBottom: 8, display: "flex", alignItems: "flex-start", gap: 12 }}>
            <div style={{ flex: 1 }}>
                <div style={{ display: "flex", alignItems: "center", gap: 8, marginBottom: 4 }}>
                    <span style={{ fontSize: 11, padding: "2px 7px", borderRadius: 8, background: type.bg, color: type.color, fontWeight: 600 }}>{type.label}</span>
                </div>
                <div style={{ fontSize: 14, fontWeight: 500, color: T.text }}>{resource.title}</div>
                {resource.description && (
                    <div style={{ fontSize: 12, color: T.textMuted, marginTop: 3, overflow: "hidden", textOverflow: "ellipsis", whiteSpace: "nowrap" }}>
                        {resource.description}
                    </div>
                )}
            </div>
            {url && (
                <button onClick={handleOpen}
                    style={{ padding: "6px 12px", borderRadius: T.radius, background: T.primary, border: "none", color: "#fff", fontSize: 13, cursor: "pointer", flexShrink: 0 }}>
                    Open
                </button>
            )}
        </div>
    );
}
