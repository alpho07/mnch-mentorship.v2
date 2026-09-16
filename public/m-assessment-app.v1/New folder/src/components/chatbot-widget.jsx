import { useState, useRef, useEffect } from "react";
import { T } from "../constants.js";

// ─── Inline styles for the chatbot ───────────────────────────────────────────

const BOT_AVATAR = "🩺";
const USER_AVATAR = "👤";

const SYSTEM_PROMPT = `You are MNCH Kenya Assessment Assistant, an expert on Kenya's Ministry of Health
Maternal, Newborn and Child Health (MNCH) facility assessment standards.

When asked about an assessment question:
1. Explain what the question is assessing in plain language (1-2 sentences)
2. Explain WHY it matters for MNCH service quality
3. Give concrete examples of what "Yes", "Partial", or "No" would look like
4. Give a practical tip for the assessor collecting this data

Keep responses concise (under 200 words). Use emojis sparingly. Be warm, professional, and helpful.
If given section context, reference it. Respond in English.`;

async function callClaudeAPI(messages) {
    const res = await fetch("https://api.anthropic.com/v1/messages", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
            model: "claude-sonnet-4-20250514",
            max_tokens: 1000,
            system: SYSTEM_PROMPT,
            messages,
        }),
    });
    const data = await res.json();
    const text = data.content?.map(b => b.text || "").join("") || "Sorry, I couldn't get a response.";
    return text;
}

// ─── Message bubble ───────────────────────────────────────────────────────────
function Bubble({ msg }) {
    const isBot = msg.role === "assistant";
    return (
        <div style={{
            display: "flex",
            flexDirection: isBot ? "row" : "row-reverse",
            gap: 8,
            marginBottom: 12,
            alignItems: "flex-end",
        }}>
            <div style={{
                width: 28, height: 28, borderRadius: 10,
                background: isBot ? "linear-gradient(135deg,#064E3B,#059669)" : "linear-gradient(135deg,#3730A3,#6366F1)",
                display: "flex", alignItems: "center", justifyContent: "center",
                fontSize: 14, flexShrink: 0, boxShadow: "0 2px 8px rgba(0,0,0,0.15)",
            }}>
                {isBot ? BOT_AVATAR : USER_AVATAR}
            </div>
            <div style={{
                maxWidth: "78%",
                padding: "10px 13px",
                borderRadius: isBot ? "4px 14px 14px 14px" : "14px 4px 14px 14px",
                background: isBot
                    ? "linear-gradient(135deg,#F0FDF4,#ECFDF5)"
                    : "linear-gradient(135deg,#EEF2FF,#E0E7FF)",
                border: isBot ? "1px solid #BBF7D0" : "1px solid #C7D2FE",
                fontSize: 13,
                lineHeight: 1.55,
                color: isBot ? "#064E3B" : "#312E81",
                fontWeight: 400,
                whiteSpace: "pre-wrap",
                boxShadow: "0 1px 4px rgba(0,0,0,0.06)",
            }}>
                {msg.content}
            </div>
        </div>
    );
}

// ─── Typing indicator ─────────────────────────────────────────────────────────
function TypingIndicator() {
    return (
        <div style={{ display: "flex", gap: 8, alignItems: "flex-end", marginBottom: 12 }}>
            <div style={{
                width: 28, height: 28, borderRadius: 10,
                background: "linear-gradient(135deg,#064E3B,#059669)",
                display: "flex", alignItems: "center", justifyContent: "center", fontSize: 14,
            }}>🩺</div>
            <div style={{
                padding: "10px 14px",
                borderRadius: "4px 14px 14px 14px",
                background: "linear-gradient(135deg,#F0FDF4,#ECFDF5)",
                border: "1px solid #BBF7D0",
                display: "flex", gap: 4, alignItems: "center",
            }}>
                {[0, 1, 2].map(i => (
                    <div key={i} style={{
                        width: 7, height: 7, borderRadius: "50%",
                        background: "#059669",
                        animation: `bounce 1.2s ease-in-out ${i * 0.2}s infinite`,
                    }} />
                ))}
            </div>
            <style>{`@keyframes bounce{0%,80%,100%{transform:translateY(0)}40%{transform:translateY(-6px)}}`}</style>
        </div>
    );
}

// ─── Chatbot slide-up panel ───────────────────────────────────────────────────
export function ChatbotPanel({ question, sectionName, onClose }) {
    const [messages, setMessages] = useState([]);
    const [input, setInput] = useState("");
    const [loading, setLoading] = useState(false);
    const [initialized, setInitialized] = useState(false);
    const bottomRef = useRef(null);
    const inputRef = useRef(null);

    // Auto-explain the question on open
    useEffect(() => {
        if (initialized) return;
        setInitialized(true);

        const initialMsg = question
            ? `Please explain this assessment question from the "${sectionName || "Assessment"}" section:\n\n"${question.question_text}"${question.help_text ? `\n\nHint provided: ${question.help_text}` : ""}`
            : `I'm working on the "${sectionName || "MNCH Assessment"}" section. Can you give me an overview of what this section covers?`;

        const userMessage = { role: "user", content: initialMsg };
        setLoading(true);
        callClaudeAPI([userMessage])
            .then(reply => {
                setMessages([{ role: "assistant", content: reply }]);
            })
            .catch(() => {
                setMessages([{ role: "assistant", content: "Sorry, I'm having trouble connecting. Please try again." }]);
            })
            .finally(() => setLoading(false));
    }, []);

    useEffect(() => {
        bottomRef.current?.scrollIntoView({ behavior: "smooth" });
    }, [messages, loading]);

    const sendMessage = async () => {
        const text = input.trim();
        if (!text || loading) return;
        setInput("");
        const newMessages = [...messages, { role: "user", content: text }];
        setMessages(newMessages);
        setLoading(true);
        try {
            const reply = await callClaudeAPI(newMessages);
            setMessages([...newMessages, { role: "assistant", content: reply }]);
        } catch {
            setMessages([...newMessages, { role: "assistant", content: "Sorry, an error occurred." }]);
        } finally {
            setLoading(false);
        }
        setTimeout(() => inputRef.current?.focus(), 100);
    };

    const handleKey = (e) => {
        if (e.key === "Enter" && !e.shiftKey) { e.preventDefault(); sendMessage(); }
    };

    return (
        <div style={{
            position: "absolute", inset: 0,
            background: "rgba(0,0,0,0.45)",
            backdropFilter: "blur(3px)",
            zIndex: 200,
            display: "flex",
            flexDirection: "column",
            justifyContent: "flex-end",
        }}
            onClick={(e) => { if (e.target === e.currentTarget) onClose(); }}
        >
            {/* Panel */}
            <div style={{
                background: "#FAFFFE",
                borderRadius: "24px 24px 0 0",
                maxHeight: "80%",
                display: "flex",
                flexDirection: "column",
                boxShadow: "0 -8px 40px rgba(6,78,59,0.15)",
                animation: "slideUp 0.3s cubic-bezier(0.34,1.56,0.64,1)",
            }}>
                <style>{`@keyframes slideUp{from{transform:translateY(100%)}to{transform:translateY(0)}}`}</style>

                {/* Handle + header */}
                <div style={{ padding: "12px 20px 0" }}>
                    <div style={{
                        width: 40, height: 4, borderRadius: 2,
                        background: "#D1FAE5", margin: "0 auto 14px",
                    }} />
                    <div style={{ display: "flex", alignItems: "center", gap: 10, paddingBottom: 12, borderBottom: "1px solid #ECFDF5" }}>
                        <div style={{
                            width: 38, height: 38, borderRadius: 12,
                            background: "linear-gradient(135deg,#064E3B,#059669)",
                            display: "flex", alignItems: "center", justifyContent: "center",
                            fontSize: 20, boxShadow: "0 4px 12px rgba(6,78,59,0.3)",
                        }}>🩺</div>
                        <div>
                            <div style={{ fontSize: 15, fontWeight: 800, color: "#064E3B" }}>Assessment Assistant</div>
                            <div style={{ fontSize: 11, color: "#6B7280" }}>
                                {question ? `Explaining: ${sectionName}` : sectionName}
                            </div>
                        </div>
                        <button onClick={onClose} style={{
                            marginLeft: "auto", width: 32, height: 32, borderRadius: 8,
                            border: "none", background: "#F3F4F6", cursor: "pointer",
                            fontSize: 16, color: "#6B7280", display: "flex", alignItems: "center", justifyContent: "center",
                        }}>✕</button>
                    </div>
                </div>

                {/* Question context chip */}
                {question && (
                    <div style={{ padding: "10px 20px 0" }}>
                        <div style={{
                            padding: "8px 12px",
                            background: "linear-gradient(135deg,#EEF2FF,#E0E7FF)",
                            border: "1px solid #C7D2FE",
                            borderRadius: 10, fontSize: 12,
                            color: "#3730A3", lineHeight: 1.45,
                            fontStyle: "italic",
                        }}>
                            💬 "{question.question_text.length > 100
                                ? question.question_text.slice(0, 100) + "…"
                                : question.question_text}"
                        </div>
                    </div>
                )}

                {/* Messages */}
                <div style={{ flex: 1, overflowY: "auto", padding: "14px 20px 4px", minHeight: 100 }}>
                    {messages.map((m, i) => <Bubble key={i} msg={m} />)}
                    {loading && <TypingIndicator />}
                    <div ref={bottomRef} />
                </div>

                {/* Suggested follow-up chips */}
                {messages.length > 0 && !loading && (
                    <div style={{ padding: "4px 20px 8px", display: "flex", gap: 6, flexWrap: "wrap" }}>
                        {[
                            "Give me an example",
                            "What standards apply?",
                            "Common mistakes?",
                        ].map(chip => (
                            <button key={chip} onClick={() => { setInput(chip); setTimeout(sendMessage, 50); }}
                                style={{
                                    padding: "5px 10px", borderRadius: 20,
                                    border: "1px solid #BBF7D0", background: "#F0FDF4",
                                    fontSize: 11, color: "#065F46", cursor: "pointer",
                                    fontWeight: 600,
                                }}>
                                {chip}
                            </button>
                        ))}
                    </div>
                )}

                {/* Input area */}
                <div style={{
                    padding: "8px 16px 20px",
                    borderTop: "1px solid #ECFDF5",
                    display: "flex", gap: 8, alignItems: "flex-end",
                }}>
                    <textarea
                        ref={inputRef}
                        value={input}
                        onChange={e => setInput(e.target.value)}
                        onKeyDown={handleKey}
                        placeholder="Ask a follow-up question…"
                        rows={1}
                        disabled={loading}
                        style={{
                            flex: 1, padding: "10px 14px", borderRadius: 14,
                            border: "2px solid #BBF7D0", fontSize: 13, color: "#111827",
                            outline: "none", resize: "none", fontFamily: "inherit",
                            background: loading ? "#F9FAFB" : "white",
                            lineHeight: 1.5, boxSizing: "border-box",
                            transition: "border-color 0.2s",
                        }}
                        onFocus={e => e.target.style.borderColor = "#059669"}
                        onBlur={e => e.target.style.borderColor = "#BBF7D0"}
                    />
                    <button
                        onClick={sendMessage}
                        disabled={!input.trim() || loading}
                        style={{
                            width: 42, height: 42, borderRadius: 13, border: "none",
                            background: input.trim() && !loading
                                ? "linear-gradient(135deg,#064E3B,#059669)"
                                : "#E5E7EB",
                            cursor: input.trim() && !loading ? "pointer" : "default",
                            display: "flex", alignItems: "center", justifyContent: "center",
                            fontSize: 18, flexShrink: 0,
                            boxShadow: input.trim() && !loading ? "0 4px 12px rgba(6,78,59,0.35)" : "none",
                            transition: "all 0.2s",
                        }}>
                        ↑
                    </button>
                </div>
            </div>
        </div>
    );
}

// ─── Floating chatbot trigger button (for section-level help) ─────────────────
export function ChatbotFAB({ onClick, pulse = false }) {
    return (
        <button
            onClick={onClick}
            style={{
                position: "fixed",
                bottom: 80, right: 16,
                width: 52, height: 52,
                borderRadius: 17,
                background: "linear-gradient(135deg,#064E3B,#059669)",
                border: "none",
                boxShadow: "0 6px 20px rgba(6,78,59,0.4)",
                cursor: "pointer",
                display: "flex", alignItems: "center", justifyContent: "center",
                fontSize: 24, zIndex: 100,
                animation: pulse ? "pulseGlow 2s ease-in-out infinite" : "none",
            }}>
            🩺
            <style>{`
        @keyframes pulseGlow {
          0%,100% { box-shadow: 0 6px 20px rgba(6,78,59,0.4); transform: scale(1); }
          50% { box-shadow: 0 6px 32px rgba(6,78,59,0.65); transform: scale(1.05); }
        }
      `}</style>
        </button>
    );
}
