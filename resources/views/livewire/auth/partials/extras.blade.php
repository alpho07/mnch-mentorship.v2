<style>
/* Page-specific parts shared by register / password reset (hero is shared with sign-in) */
.steps{display:flex;align-items:center;gap:.3rem;margin-bottom:1.75rem}
.step{display:flex;align-items:center;gap:.45rem;font-size:.75rem;font-weight:600;color:#94a3b8}
.step.active{color:#1245A8}
.step.done{color:#1A54C8}
.step-num{width:24px;height:24px;border-radius:50%;border:2px solid #cbd5e1;display:flex;align-items:center;justify-content:center;font-size:.68rem;font-weight:800;color:#94a3b8;flex-shrink:0;transition:all .3s}
.step.active .step-num{border-color:#1A54C8;color:#1A54C8;background:#EFF6FF}
.step.done .step-num{border-color:#1245A8;background:#1245A8;color:#fff}
.step-connector{flex:1;height:2px;background:#e2e8f0;border-radius:1px;min-width:16px;transition:background .3s}
.step-connector.done{background:#1A54C8}
.auth-infobox{display:flex;align-items:flex-start;gap:.6rem;padding:.7rem .9rem;border-radius:9px;margin-bottom:1.25rem;background:#EFF6FF;border:1px solid #BFDBFE}
.auth-infobox svg{width:15px;height:15px;flex-shrink:0;margin-top:1px;stroke:#1A54C8;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.auth-infobox span{font-size:.78rem;color:#1245A8;line-height:1.5}
.auth-back{display:inline-flex;align-items:center;gap:.4rem;font-size:.83rem;font-weight:600;color:#6b7280;text-decoration:none;transition:color .2s;margin-top:1.25rem}
.auth-back:hover{color:#1245A8}
.auth-back svg{width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:2.5;stroke-linecap:round;stroke-linejoin:round}
.auth-back-center{display:flex;justify-content:center;width:100%}
.inbox-panel{text-align:center;padding-top:.5rem}
.inbox-icon{width:68px;height:68px;border-radius:18px;background:linear-gradient(135deg,#1245A8,#1A54C8);display:flex;align-items:center;justify-content:center;margin:0 auto 1.25rem;box-shadow:0 8px 24px rgba(18,69,168,.3)}
.inbox-icon svg{width:30px;height:30px;stroke:#fff;fill:none;stroke-width:1.75;stroke-linecap:round;stroke-linejoin:round}
.inbox-title{font-size:1.2rem;font-weight:800;color:#111827;margin:0 0 .25rem;letter-spacing:-.02em}
.inbox-sub{font-size:.85rem;color:#6b7280;margin:0}
.inbox-email{font-size:.92rem;font-weight:700;color:#1245A8;margin:.4rem 0 1rem;word-break:break-all}
.inbox-hint{font-size:.8rem;color:#94a3b8;margin:0 0 .5rem;line-height:1.6}
.inbox-hint button{background:none;border:none;color:#1A54C8;font-weight:600;cursor:pointer;font-size:inherit;padding:0;text-decoration:underline}
.inbox-hint button:hover{color:#1245A8}
.pw-strength {
            margin-top: 0.6rem;
            margin-bottom: 0.1rem;
        }
.pw-bars {
            display: flex;
            gap: 4px;
            margin-bottom: 0.25rem;
        }
.pw-bar {
            flex: 1;
            height: 4px;
            border-radius: 100px;
            background: #e2e8f0;
            opacity: 0.4;
            transition: background 0.3s ease, opacity 0.3s ease;
        }
.pw-strength-label {
            font-size: 0.7rem;
            font-weight: 600;
            letter-spacing: 0.03em;
            transition: color 0.3s;
        }
.pw-hints {
            margin-top: 0.55rem;
            margin-bottom: 0.1rem;
            display: flex;
            flex-direction: column;
            gap: 0.28rem;
        }
.pw-hint {
            display: flex;
            align-items: center;
            gap: 0.38rem;
            font-size: 0.72rem;
            font-weight: 500;
            color: #94a3b8;
            transition: color 0.2s;
        }
.pw-hint svg {
            width: 11px;
            height: 11px;
            stroke: #cbd5e1;
            fill: none;
            stroke-width: 3;
            stroke-linecap: round;
            stroke-linejoin: round;
            flex-shrink: 0;
            transition: stroke 0.2s;
        }
.pw-hint.met {
            color: #10b981;
        }
.pw-hint.met svg {
            stroke: #10b981;
        }
.auth-mark{width:54px;height:54px;border-radius:16px;background:#e8f1fc;display:grid;place-items:center;margin:0 0 1.2rem}
.auth-mark svg{width:26px;height:26px;stroke:var(--blue);fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.hero-tips{margin-top:1.4rem;max-width:34rem;display:flex;flex-direction:column;gap:.6rem}
.tip-label{color:#98c7ff;text-transform:uppercase;letter-spacing:.12em;font-size:.66rem;font-weight:800}
.tip-item{display:flex;align-items:flex-start;gap:.7rem;padding:.7rem .85rem;border:1px solid rgba(255,255,255,.16);border-radius:12px;background:rgba(255,255,255,.08);backdrop-filter:blur(10px);font-size:.8rem;line-height:1.5;color:rgba(255,255,255,.85)}
.tip-icon{flex:0 0 28px;width:28px;height:28px;border-radius:8px;background:rgba(126,190,255,.16);display:grid;place-items:center}
.tip-icon svg{width:15px;height:15px;stroke:#9dccff;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.auth-back-center{display:flex;justify-content:center;width:100%}
/* long forms scroll with the page instead of being clipped */
html,body{overflow:auto!important}
/* steps row must fit inside the card */
.steps{display:flex;align-items:center;flex-wrap:nowrap;gap:.3rem;margin-bottom:1.3rem}
.step{display:flex;align-items:center;gap:.35rem;font-size:.66rem;white-space:nowrap}
.step-connector{flex:1 1 8px;min-width:8px;max-width:28px}
@media(max-width:520px){.step span:last-child{display:none}.step.active span:last-child{display:inline}}
</style>
