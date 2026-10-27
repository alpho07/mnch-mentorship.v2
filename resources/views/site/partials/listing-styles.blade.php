@push('styles')
.tpill{display:inline-flex;align-items:center;gap:8px;min-height:40px;padding:0 16px;border-radius:999px;font-size:14px;font-weight:600;background:#FFF;border:1px solid #D7E4E5;color:#2E4A4E;text-decoration:none;transition:background .2s,border-color .2s,color .2s}
.tpill:hover{border-color:#0097A7;color:#00363B}
.tpill.on{background:#00363B;border-color:#00363B;color:#FFF}
.frow{display:flex;justify-content:space-between;align-items:center;gap:10px;width:100%;min-height:40px;padding:6px 10px;border-radius:10px;font-size:14px;color:#2E4A4E;text-decoration:none;transition:background .2s}
.frow:hover{background:#F0F7F7;color:#00363B}
.frow.on{background:#E3F5F7;color:#00363B;font-weight:700}
.sel{appearance:none;-webkit-appearance:none;height:44px;border:1px solid #D3DFE0;border-radius:12px;background:#FFF;padding:0 38px 0 14px;font:inherit;font-size:14px;color:#0F2A2E}
.sel:focus,.finp:focus{outline:2px solid #0097A7;outline-offset:1px;border-color:#0097A7}
.finp{height:44px;border:1px solid #D3DFE0;border-radius:12px;background:#FFF;padding:0 14px;font:inherit;font-size:14px;color:#0F2A2E;min-width:0}
.rcard{display:flex;flex-direction:column;background:#FFF;border:1px solid #E1E9EA;border-radius:18px;overflow:hidden;text-decoration:none;color:#0F2A2E;transition:transform .25s,box-shadow .25s}
.rcard:hover{transform:translateY(-4px);box-shadow:0 24px 40px -26px rgba(15,42,46,.45);color:#0F2A2E}
.panel{background:#FFF;border:1px solid #E1E9EA;border-radius:18px;padding:18px}
.pg{display:inline-flex;align-items:center;justify-content:center;min-width:44px;height:44px;padding:0 10px;border-radius:12px;border:1px solid #D3DFE0;background:#FFF;color:#0F2A2E;text-decoration:none;font-weight:600;font-size:14px}
.pg:hover{border-color:#0097A7}.pg.on{background:#00363B;border-color:#00363B;color:#FFF}.pg.off{opacity:.4;pointer-events:none}
@endpush
