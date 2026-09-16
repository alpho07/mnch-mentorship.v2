{{-- resources/views/analytics/heatmap.blade.php --}}
@extends('layouts.app')

@section('title', 'Kenya Training Heatmap')

@push('styles')
<link rel="stylesheet"
      href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
      integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
      crossorigin=""/>
<style>
  /* =========================
     Lightweight Theme
     ========================= */
  :root{
    --bg: #fafafa;
    --card: #ffffff;
    --ink: #0f172a;
    --muted: #6b7280;
    --line: #e5e7eb;
    --brand: #4f46e5;
    --brand-weak:#eef2ff;
    --ring: rgba(79,70,229,.18);
    --radius: 14px;
    --radius-sm: 10px;
    --shadow-sm: 0 2px 8px rgba(2,6,23,.05);
    --shadow-md: 0 6px 20px rgba(2,6,23,.06);
    --fade: .25s cubic-bezier(.2,.6,.2,1);
  }
  @media (prefers-color-scheme: dark){
    :root{
      --bg:#0b1020; --card:#0f172a; --ink:#e5e7eb; --muted:#94a3b8; --line:#1f2937;
      --brand-weak:#1e1b4b; --ring:rgba(99,102,241,.28);
      --shadow-sm: 0 2px 10px rgba(0,0,0,.35); --shadow-md: 0 10px 30px rgba(0,0,0,.35);
    }
    .leaflet-container{ background:#0b1020; }
  }
  @media (prefers-reduced-motion: reduce){
    *{ animation: none !important; transition: none !important }
  }

  body{ background: var(--bg); }
  .wrap{ min-height:100vh; padding:2rem 1rem; }
  .container{ max-width:1180px; margin:0 auto; }

  /* Header */
  .eyebrow{ font: 700 .72rem/1.2 ui-sans-serif,system-ui,sans-serif; color:var(--brand); letter-spacing:.12em; text-transform:uppercase }
  .title{ font: 900 clamp(1.6rem,3vw,2.2rem)/1.1 ui-sans-serif,system-ui,sans-serif; color:var(--ink); letter-spacing:-.02em; margin:.3rem 0 .25rem }
  .sub{ color:var(--muted) }

  /* Card */
  .card{
    background:var(--card); border:1px solid var(--line); border-radius:var(--radius);
    box-shadow: var(--shadow-sm); transition: transform var(--fade), box-shadow var(--fade), border-color var(--fade), background var(--fade);
  }
  .card:hover{ transform: translateY(-1px); box-shadow: var(--shadow-md); }

  /* KPI grid */
  .grid{ display:grid; gap:12px }
  .grid-5{ grid-template-columns: 1fr 1fr 1fr 1fr 1.4fr }
  @media (max-width:960px){ .grid-5{ grid-template-columns: 1fr 1fr; } }
  @media (max-width:560px){ .grid-5{ grid-template-columns: 1fr; } }
  .kpi{ padding:1rem 1.1rem; }
  .kpi small{ color:var(--muted); font:700 .7rem/1 ui-sans-serif,system-ui; text-transform:uppercase; letter-spacing:.04em }
  .kpi b{ display:block; margin-top:.35rem; font: 900 1.6rem/1 ui-sans-serif,system-ui; color:var(--ink) }

  /* Panel */
  .panel{ padding:1rem 1.1rem; display:flex; flex-direction:column; gap:.5rem }
  .panel-head{ display:flex; align-items:center; justify-content:space-between }
  .badge{ display:inline-flex; align-items:center; gap:.4rem; padding:.28rem .6rem; border-radius:999px; border:1px solid var(--line); background:var(--brand-weak); color:#3730a3; font: 700 .76rem/1 ui-sans-serif }
  @media (prefers-color-scheme: dark){ .badge{ color:#c7d2fe; } }

  /* Toolbar (lightweight, focusable inputs) */
  .toolbar{ display:flex; gap:.6rem; align-items:flex-end; flex-wrap:wrap; margin-top: .6rem }
  .field{ display:grid; gap:.3rem }
  .label{ font:700 .72rem/1 ui-sans-serif; color:var(--muted); text-transform:uppercase; letter-spacing:.04em }
  .select, .input, .button{
    font: 600 .92rem/1.1 ui-sans-serif,system-ui; color:var(--ink);
    padding:.6rem .75rem; border:1px solid var(--line); border-radius:var(--radius-sm);
    background:var(--card); transition: box-shadow var(--fade), border-color var(--fade), transform .06s ease;
    box-shadow: inset 0 -2px 0 rgba(2,6,23,.02);
  }
  .select{ appearance:none; background-image:
      linear-gradient(45deg, transparent 50%, var(--muted) 50%),
      linear-gradient(135deg, var(--muted) 50%, transparent 50%),
      linear-gradient(to right, transparent, transparent);
    background-position: calc(100% - 18px) calc(.85em), calc(100% - 13px) calc(.85em), calc(100% - 2.4rem) .35em;
    background-size: 5px 5px, 5px 5px, 1px 1.5em; background-repeat:no-repeat; min-width: 210px;
  }
  .input:focus, .select:focus{ outline: none; border-color: var(--brand); box-shadow: 0 0 0 4px var(--ring); }
  .button{ cursor:pointer; }
  .button:hover{ transform: translateY(-1px); }
  .btn-clear{ color:var(--muted); background:transparent }
  .btn-brand{ background:var(--brand); color:#fff; border-color: transparent }

  /* Legend */
  .legend{ display:flex; flex-wrap:wrap; gap:.5rem; align-items:center }
  .chip{ display:inline-flex; gap:.4rem; align-items:center; border:1px solid var(--line); border-radius:999px; padding:.32rem .6rem; background:var(--card); font:.8rem/1 ui-sans-serif }
  .sw{ width:14px; height:12px; border-radius:3px; border:1px solid rgba(0,0,0,.08) }

  /* Map */
  .map-shell{ position:relative; border:1px solid var(--line); border-radius:var(--radius); overflow:hidden }
  .map{ width:100%; height:72vh; min-height:460px }
  .overlay{ position:absolute; inset:0; display:grid; place-items:center; background:linear-gradient(to bottom, rgba(255,255,255,.55), rgba(255,255,255,.35)); backdrop-filter:saturate(160%) blur(6px); opacity:0; pointer-events:none; transition: opacity var(--fade) }
  .shell-loading .overlay{ opacity:1; pointer-events:auto }
  .spin{ width:42px; height:42px; border-radius:999px; border:3px solid #e0e7ff; border-top-color:var(--brand); animation:spin 1s linear infinite }
  @keyframes spin { to{ transform:rotate(360deg) } }

  /* Popup content */
  .county{ min-width:240px }
  .county .title{ font: 800 1rem/1 ui-sans-serif; color:var(--ink) }
  .county .meta{ color:var(--muted); font:.82rem/1.2 ui-sans-serif }
  .county .link{ display:inline-flex; gap:.4rem; align-items:center; color:var(--brand); text-decoration:none; font:700 .9rem/1 ui-sans-serif }
  .county .link:hover{ text-decoration:underline }

  /* Micro animations on mount */
  .fade-in{ opacity:0; transform: translateY(6px); animation:fadeIn .45s var(--fade) forwards }
  .fade-in:nth-child(1){ animation-delay:.02s } .fade-in:nth-child(2){ animation-delay:.05s }
  .fade-in:nth-child(3){ animation-delay:.08s } .fade-in:nth-child(4){ animation-delay:.11s }
  @keyframes fadeIn { to{ opacity:1; transform:none } }

  /* Modal (simple, no dependencies) */
  .modal-backdrop{
    position:fixed; inset:0; background:rgba(2,6,23,.38);
    display:none; place-items:center; z-index:60;
    transition: opacity var(--fade);
  }
  .modal-backdrop.show{ display:grid; }
  .modal-card{
    width:min(680px,92vw); background:var(--card); border:1px solid var(--line);
    border-radius:var(--radius); box-shadow:var(--shadow-md); transform: translateY(10px);
    opacity:0; transition: transform var(--fade), opacity var(--fade);
  }
  .modal-backdrop.show .modal-card{ transform:none; opacity:1 }
  .modal-head{ padding:1rem 1.1rem; border-bottom:1px solid var(--line); display:flex; align-items:center; justify-content:space-between }
  .modal-body{ padding:1rem 1.1rem }
  .xbtn{ background:transparent; border:1px solid var(--line); border-radius:10px; padding:.4rem .55rem; cursor:pointer }
  
   /* Title dark */
  .title{ 
    font: 900 clamp(1.6rem,3vw,2.2rem)/1.1 ui-sans-serif,system-ui,sans-serif; 
    color:#111827;  /* DARK text */
    letter-spacing:-.02em; 
    margin:.3rem 0 .25rem 
  }

  /* Legend text now white */
  .legend{ color:#fff; }

  /* Popup wrapper background adjustment */
.leaflet-popup-content-wrapper {
  background: #1f2937 !important; /* dark gray/near black */
  color: #fff !important;
  border-radius: 10px;
  box-shadow: 0 4px 20px rgba(0,0,0,.35);
}

/* Popup tip (arrow) also dark */
.leaflet-popup-tip {
  background: #1f2937 !important;
}

/* Content */
.leaflet-popup-content {
  color: #fff !important;
  font: .9rem/1.45 ui-sans-serif,system-ui;
}
.leaflet-popup-content .title { 
  font-weight: 700; 
  color:#fff !important;
}
.leaflet-popup-content .meta { 
  color:#d1d5db !important; 
}
.leaflet-popup-content a { 
  color:#93c5fd !important; 
  font-weight:600; 
  text-decoration:none; 
}
.leaflet-popup-content a:hover { 
  text-decoration:underline; 
}
</style>
@endpush

@section('content')
@php
  use Illuminate\Support\Facades\Route as RouteFacade;

  $geojsonUrl = RouteFacade::has('analytics.heatmap.geojson')
      ? route('analytics.heatmap.geojson')
      : asset('analytics/geo/kenyan-counties.geojson');

  $widgetId = 'map_' . substr(sha1(uniqid('', true)), 0, 8);
  $training_type = $training_type ?? request('training_type');
@endphp

<div class="wrap">
  <div class="container">
    <div class="fade-in">
      <div class="eyebrow">MOH Analytics</div>
      <h1 class="title">Kenya Training Heatmap</h1>
                <p class="sub" style="color:black !important;">Click a county → open trainings → facilities → participants → profile/history.</p>

      <div class="toolbar" role="region" aria-label="Filters">
        <form method="GET" action="{{ route('analytics.heatmap') }}" class="toolbar" onsubmit="return true;">
          <div class="field">
            <label class="label" for="ttype">Training Type</label>
            <select id="ttype" class="select" name="training_type" onchange="this.form.submit()">
              <option value="" {{ ($training_type ?? '')==='' ? 'selected' : '' }}>All</option>
              <option value="global_training" {{ ($training_type ?? '')==='global_training' ? 'selected' : '' }}>MOH Trainings</option>
              <option value="facility_mentorship" {{ ($training_type ?? '')==='facility_mentorship' ? 'selected' : '' }}>Facility Mentorship</option>
            </select>
          </div>

          @if(($training_type ?? '') !== '')
            <button type="button" class="button btn-clear" onclick="window.location='{{ route('analytics.heatmap') }}'">
              Clear
            </button>
          @endif

          <button type="button" class="button btn-brand" onclick="openModal('aboutModal')">
            About data
          </button>
        </form>
      </div>
    </div>

    {{-- KPIs & Insights --}}
    <div class="grid grid-5" style="margin:14px 0 12px">
      <div class="card kpi fade-in">
        <small>Total Trainings</small>
        <b>{{ number_format($mapData['totalTrainings'] ?? 0) }}</b>
      </div>
      <div class="card kpi fade-in">
        <small>Participants</small>
        <b>{{ number_format($mapData['totalParticipants'] ?? 0) }}</b>
      </div>
      <div class="card kpi fade-in">
        <small>Facilities</small>
        <b>{{ number_format($mapData['totalFacilities'] ?? 0) }}</b>
      </div>
      <div class="card panel fade-in" style="grid-column: span 2">
        <div class="panel-head">
          <div class="label" style="text-transform:none">AI Insights</div>
         @if(($training_type ?? '')!=='')
                    <span class="badge">
                        <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M3 5h18M6 12h12M10 19h4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                        {{ $training_type === 'global_training' ? 'MOH Trainings' : str_replace('_',' ', $training_type) }}
                    </span>
        @endif
        </div>
        <div style="font:.92rem/1.35 ui-sans-serif; color:var(--ink)">
          @if(!empty($ai['coverage']))        <div>{{ $ai['coverage'] }}</div> @endif
          @if(!empty($ai['participation']))   <div style="margin-top:.4rem">{{ $ai['participation'] }}</div> @endif
          @if(!empty($ai['recommendations'])) <div style="margin-top:.4rem">{{ $ai['recommendations'] }}</div> @endif
        </div>
      </div>
    </div>

    {{-- Legend --}}
    <div class="card" style="padding:.8rem 1rem; margin:8px 0 12px">
      <div class="legend">
        @forelse(($mapData['intensityLevels'] ?? []) as $lvl)
          <span class="chip"><span class="sw" style="background: {{ $lvl['color'] }}"></span>{{ $lvl['label'] }}</span>
        @empty
          <span class="sub">Legend will appear once intensity levels are provided.</span>
        @endforelse
      </div>
    </div>

    {{-- Map --}}
    <div class="map-shell card fade-in" id="shell-{{ $widgetId }}">
      <div id="{{ $widgetId }}" class="map" role="img" aria-label="Kenya training intensity choropleth"></div>
      <div class="overlay"><div style="display:grid;gap:.6rem;place-items:center">
        <div class="spin" aria-hidden="true"></div>
        <div class="sub" style="font-size:.9rem">Loading Kenya counties…</div>
      </div></div>
    </div>
  </div>
</div>

{{-- Data priming for JS --}}
<script>
  window.__HEATMAP__ = {
    countyRows: @json($mapData['countyData'] ?? []),
    geojsonUrl: @json($geojsonUrl),
    explorerBase: @json(route('analytics.training-explorer')),
    trainingType: @json($training_type ?? '')
  };
</script>

{{-- Lightweight Modal (About) --}}
<div id="aboutModal" class="modal-backdrop" aria-hidden="true" aria-labelledby="aboutTitle" aria-modal="true" role="dialog">
  <div class="modal-card">
    <div class="modal-head">
      <strong id="aboutTitle" style="font:800 1rem/1 ui-sans-serif;color:var(--ink)">About this heatmap</strong>
      <button class="xbtn" onclick="closeModal('aboutModal')" aria-label="Close dialog">✕</button>
    </div>
    <div class="modal-body">
      <p class="sub" style="margin-bottom:.6rem">Intensity is based on trainings, participants, and facilities per county.</p>
      <ul style="margin:0; padding-left:1rem; color:var(--ink); line-height:1.5">
        <li>Click a county to open filtered results in the Explorer.</li>
        <li>Use the Training Type filter above to scope the map.</li>
        <li>Legend colors reflect relative intensity across counties.</li>
      </ul>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
        crossorigin=""></script>
<script>
  // ============ Modal helpers ============
  function openModal(id){
    const el = document.getElementById(id);
    if(!el) return;
    el.classList.add('show');
    el.style.opacity = '0';
    el.removeAttribute('aria-hidden');
    requestAnimationFrame(()=>{ el.style.opacity = '1'; });
  }
  function closeModal(id){
    const el = document.getElementById(id);
    if(!el) return;
    el.style.opacity = '0';
    setTimeout(()=>{ el.classList.remove('show'); el.setAttribute('aria-hidden','true'); }, 200);
  }
  document.addEventListener('keydown', e=>{
    if(e.key === 'Escape'){ document.querySelectorAll('.modal-backdrop.show').forEach(m => closeModal(m.id)); }
  });
  document.querySelectorAll('.modal-backdrop').forEach(bg=>{
    bg.addEventListener('click', e=>{ if(e.target === bg) closeModal(bg.id); });
  });

  // ============ Map boot ============
  (function() {
    const cfg = window.__HEATMAP__ || {};
    const countyRows = cfg.countyRows || [];
    const geojsonUrl = cfg.geojsonUrl;
    const explorerBase = cfg.explorerBase;
    const trainingType = cfg.trainingType;

    const mapId = @json($widgetId);
    const shell = document.getElementById('shell-' + mapId);
    shell?.classList.add('shell-loading');

    // Indices
    const byId = Object.create(null);
    const byName = Object.create(null);
    const norm = s => (s || '')
      .toLowerCase()
      .normalize('NFD').replace(/[\u0300-\u036f]/g,'')
      .replace(/[^a-z0-9\s-]/g,' ').replace(/\s+/g,' ').replace(/-/g,' ').trim();

    (countyRows||[]).forEach(r => {
      if(!r) return;
      if(r.id != null) byId[String(r.id)] = r;
      if(r.name) byName[norm(r.name)] = r;
    });

    const pickName = p => p?.COUNTY ?? p?.COUNTY_NAM ?? p?.COUNTY_NAME ?? p?.name ?? 'Unknown';
    const pickId   = p => p?.COUNTY3_ID ?? p?.COUNTY_ID ?? p?.OBJECTID ?? null;

    const colorFor = v => {
      if (v >= 80) return '#16a34a';
      if (v >= 50) return '#84cc16';
      if (v >= 25) return '#a3a3a3';
      if (v >  10) return '#fbbf24';
      if (v >   0) return '#fca5a5';
      return '#e5e7eb';
    };

    const map = L.map(mapId, { scrollWheelZoom:true, zoomControl: true });
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution:'&copy; OpenStreetMap contributors', maxZoom:19, detectRetina:true
    }).addTo(map);

    // Fade-in on first render
    function softReveal(){
      const el = document.getElementById(mapId);
      if(!el) return;
      el.style.opacity = '0';
      el.style.transition = 'opacity .5s ease';
      requestAnimationFrame(()=>{ el.style.opacity = '1'; });
    }

    fetch(geojsonUrl, { cache:'no-store' })
      .then(r => r.json())
      .then(geojson => {
        const layer = L.geoJSON(geojson, {
          style: f => {
            const p = f?.properties || {};
            const gid = pickId(p);
            const gname = pickName(p);
            const rec = (gid != null && byId[String(gid)]) || byName[norm(gname)];
            const intensity = rec ? (rec.intensity||0) : 0;
            return { color:'#374151', weight:1, fillColor: colorFor(intensity), fillOpacity:.78 };
          },
          onEachFeature: (f, lyr) => {
            const p = f?.properties || {};
            const gid = pickId(p);
            const gname = pickName(p);
            const rec = (gid != null && byId[String(gid)]) || byName[norm(gname)] || {};

            const trainings = rec.trainings ?? 0;
            const participants = rec.participants ?? 0;
            const facilities = rec.facilities ?? 0;
            const countyId = rec.id ?? gid ?? '';

            const qs = new URLSearchParams();
            if (countyId) qs.set('county_id', countyId);
            if (trainingType) qs.set('training_type', trainingType);

            const url = explorerBase + '?' + qs.toString();

            const html = `
              <div class="county">
                <div class="title">${gname}</div>
                <div class="meta">Trainings: <b>${trainings}</b> • Participants: <b>${participants}</b> • Facilities: <b>${facilities}</b></div>
                <div style="margin-top:.45rem">
                  <a class="link" href="${url}" target="_blank" rel="noopener">
                    <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true"><path d="M14 3h7v7M10 14L21 3M21 13v7H3V3h7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    Open in Explorer
                  </a>
                </div>
              </div>
            `;
            lyr.bindPopup(html);

            lyr.on({
              mouseover: e => e.target.setStyle({ weight:3, fillOpacity:.92 }).bringToFront(),
              mouseout:  () => lyr.setStyle({ weight:1, fillOpacity:.78 })
            });
          }
        }).addTo(map);

        try { map.fitBounds(layer.getBounds(), { padding:[10,10] }); }
        catch { map.setView([-0.0236, 37.9062], 6); }

        softReveal();
      })
      .catch(err => {
        console.error('GeoJSON load failed', err);
        const m = document.getElementById(mapId);
        if (m) m.innerHTML = '<div style="padding:1rem" class="sub">GeoJSON not available.</div>';
      })
      .finally(()=> shell?.classList.remove('shell-loading'));
  })();
</script>
@endpush