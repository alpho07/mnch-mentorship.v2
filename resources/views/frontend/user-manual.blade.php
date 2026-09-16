<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MNCH Platform — User Manual</title>
<style>
:root{
  --cyan:#0891B2;--cyan-light:#CFFAFE;--cyan-dark:#0C4A6E;
  --green:#15803D;--green-light:#DCFCE7;--green-dark:#14532D;
  --rose:#BE123C;--rose-light:#FFE4E6;--rose-dark:#881337;
  --teal:#0F766E;--teal-light:#CCFBF1;--teal-dark:#134E4A;
  --navy:#0C1445;--sidebar-w:280px;--header-h:56px;
}
*{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{font-family:'Segoe UI',system-ui,sans-serif;color:#1e293b;background:#f8fafc;line-height:1.6}

/* ── PROGRESS BAR ── */
#progress-bar{position:fixed;top:0;left:0;height:3px;background:linear-gradient(90deg,#0891B2,#0F766E,#15803D);z-index:9999;width:0;transition:width .2s}

/* ── TOP HEADER ── */
.topbar{position:fixed;top:3px;left:0;right:0;height:var(--header-h);background:rgba(12,20,69,.97);backdrop-filter:blur(12px);z-index:900;display:flex;align-items:center;padding:0 16px;gap:12px;border-bottom:1px solid rgba(255,255,255,.08)}
.topbar-logo{display:flex;align-items:center;gap:10px;color:#fff;font-weight:700;font-size:1rem;white-space:nowrap;text-decoration:none}
.topbar-logo span{font-size:.7rem;font-weight:400;color:#94a3b8;display:block;line-height:1}
.topbar-divider{width:1px;height:28px;background:rgba(255,255,255,.15);flex-shrink:0}
.topbar-title{color:#cbd5e1;font-size:.85rem;font-weight:500;flex:1}
.topbar-search{position:relative;flex-shrink:0}
.topbar-search input{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.15);border-radius:8px;color:#e2e8f0;padding:6px 12px 6px 32px;font-size:.8rem;width:200px;outline:none;transition:all .2s}
.topbar-search input:focus{background:rgba(255,255,255,.12);border-color:rgba(8,145,178,.5);width:240px}
.topbar-search input::placeholder{color:#64748b}
.topbar-search-icon{position:absolute;left:9px;top:50%;transform:translateY(-50%);color:#64748b;font-size:.75rem}
.btn-print{background:rgba(8,145,178,.2);border:1px solid rgba(8,145,178,.4);color:#7dd3fc;padding:6px 14px;border-radius:8px;font-size:.78rem;cursor:pointer;transition:all .2s;white-space:nowrap}
.btn-print:hover{background:rgba(8,145,178,.35);color:#bae6fd}

/* ── SIDEBAR ── */
.sidebar{position:fixed;top:calc(var(--header-h) + 3px);left:0;width:var(--sidebar-w);height:calc(100vh - var(--header-h) - 3px);overflow-y:auto;background:#fff;border-right:1px solid #e2e8f0;z-index:800;padding:16px 0 40px;scrollbar-width:thin;scrollbar-color:#cbd5e1 transparent}
.sidebar::-webkit-scrollbar{width:4px}
.sidebar::-webkit-scrollbar-track{background:transparent}
.sidebar::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:4px}
.sidebar-section{margin-bottom:4px}
.sidebar-section-header{display:flex;align-items:center;justify-content:space-between;padding:6px 16px;cursor:pointer;user-select:none}
.sidebar-section-label{font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#94a3b8}
.sidebar-section-arrow{font-size:.6rem;color:#94a3b8;transition:transform .25s}
.sidebar-section.open .sidebar-section-arrow{transform:rotate(90deg)}
.sidebar-links{display:none;padding:2px 0}
.sidebar-section.open .sidebar-links{display:block}
.sidebar-link{display:flex;align-items:center;gap:8px;padding:5px 20px;font-size:.8rem;color:#475569;text-decoration:none;transition:all .18s;border-left:3px solid transparent}
.sidebar-link:hover{color:#0891B2;background:#f0f9ff;border-left-color:#bae6fd}
.sidebar-link.active{color:#0891B2;background:#e0f2fe;border-left-color:#0891B2;font-weight:600}
.sidebar-link .dot{width:6px;height:6px;border-radius:50%;background:#cbd5e1;flex-shrink:0}
.sidebar-link.active .dot{background:#0891B2}

/* ── MAIN CONTENT ── */
.main{margin-left:var(--sidebar-w);padding-top:calc(var(--header-h) + 3px);min-height:100vh}
.content{max-width:860px;margin:0 auto;padding:40px 40px 80px}

/* ── SECTIONS ── */
.section{margin-bottom:56px;animation:fadeUp .4s ease both}
@keyframes fadeUp{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}
.section-anchor{scroll-margin-top:calc(var(--header-h) + 20px)}

/* ── HEADINGS ── */
h1.page-title{font-size:2rem;font-weight:800;color:var(--navy);margin-bottom:8px;line-height:1.2}
.page-subtitle{color:#64748b;font-size:1rem;margin-bottom:40px;padding-bottom:24px;border-bottom:2px solid #e2e8f0}
h2.sec-title{font-size:1.35rem;font-weight:700;color:#1e293b;margin-bottom:16px;display:flex;align-items:center;gap:10px}
h2.sec-title .badge{font-size:.7rem;font-weight:600;padding:2px 8px;border-radius:20px;letter-spacing:.03em}
h3.sub-title{font-size:1rem;font-weight:700;color:#334155;margin:20px 0 10px}
h4{font-size:.88rem;font-weight:700;color:#475569;margin:14px 0 6px}
p{color:#475569;font-size:.9rem;margin-bottom:12px}
ul,ol{color:#475569;font-size:.9rem;margin:0 0 12px 20px}
li{margin-bottom:5px}

/* ── PROGRAM CARDS ── */
.program-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin:20px 0}
.program-card{border-radius:12px;padding:20px;border:1px solid}
.program-card.cyan{background:#ecfeff;border-color:#a5f3fc}
.program-card.green{background:#f0fdf4;border-color:#86efac}
.program-card.rose{background:#fff1f2;border-color:#fda4af}
.program-card h4{margin:0 0 6px;font-size:.85rem}
.program-card .mod-count{font-size:1.5rem;font-weight:800;line-height:1}
.program-card .mod-label{font-size:.72rem;color:#94a3b8}

/* ── ROLE BADGES ── */
.role-badge{display:inline-flex;align-items:center;gap:5px;font-size:.72rem;font-weight:600;padding:3px 10px;border-radius:20px;white-space:nowrap}
.role-badge.teal{background:var(--teal-light);color:var(--teal-dark)}
.role-badge.cyan{background:var(--cyan-light);color:var(--cyan-dark)}
.role-badge.rose{background:var(--rose-light);color:var(--rose-dark)}
.role-badge.green{background:var(--green-light);color:var(--green-dark)}
.role-badge.navy{background:#dbeafe;color:#1e40af}
.role-badge.slate{background:#f1f5f9;color:#475569}

/* ── STEPS ── */
.steps{counter-reset:step;margin:16px 0}
.step{display:flex;gap:14px;margin-bottom:16px;align-items:flex-start}
.step-num{counter-increment:step;display:flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:50%;background:var(--teal);color:#fff;font-size:.75rem;font-weight:700;flex-shrink:0;margin-top:1px}
.step-body{}
.step-body strong{display:block;font-size:.88rem;color:#1e293b;margin-bottom:2px}
.step-body p{margin:0;font-size:.83rem}

/* ── CALLOUT BOXES ── */
.callout{border-radius:10px;padding:14px 16px;margin:16px 0;border-left:4px solid;font-size:.85rem}
.callout-info{background:#eff6ff;border-color:#3b82f6;color:#1e40af}
.callout-tip{background:#f0fdf4;border-color:#22c55e;color:#15803d}
.callout-warn{background:#fffbeb;border-color:#f59e0b;color:#92400e}
.callout-danger{background:#fff1f2;border-color:#f43f5e;color:#9f1239}
.callout strong{display:block;margin-bottom:3px}

/* ── TABLE ── */
table{width:100%;border-collapse:collapse;font-size:.82rem;margin:12px 0}
th{background:#f1f5f9;padding:8px 12px;text-align:left;font-weight:700;color:#334155;border-bottom:2px solid #e2e8f0}
td{padding:8px 12px;border-bottom:1px solid #f1f5f9;color:#475569}
tr:hover td{background:#fafafa}

/* ── FLOW DIAGRAM ── */
.flow{display:flex;flex-wrap:wrap;gap:0;margin:16px 0;align-items:center}
.flow-item{background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:8px 14px;font-size:.78rem;font-weight:600;color:#334155;text-align:center;white-space:nowrap}
.flow-arrow{font-size:.9rem;color:#94a3b8;padding:0 6px;flex-shrink:0}
.flow-item.cyan{background:#ecfeff;border-color:#67e8f9;color:#0e7490}
.flow-item.rose{background:#fff1f2;border-color:#fda4af;color:#9f1239}
.flow-item.green{background:#f0fdf4;border-color:#86efac;color:#15803d}
.flow-item.teal{background:#f0fdfa;border-color:#5eead4;color:#0f766e}

/* ── MODULE LIST ── */
.module-list{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:8px;margin:12px 0}
.module-item{background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:8px 12px;font-size:.78rem;display:flex;gap:8px;align-items:flex-start}
.module-num{background:#e0f2fe;color:#0369a1;border-radius:5px;padding:1px 7px;font-size:.7rem;font-weight:700;flex-shrink:0;margin-top:1px}
.module-item.rose .module-num{background:#ffe4e6;color:#9f1239}
.module-item.green .module-num{background:#dcfce7;color:#15803d}

/* ── CPD LEVELS ── */
.cpd-levels{display:flex;flex-direction:column;gap:6px;margin:12px 0}
.cpd-level{display:flex;align-items:center;gap:12px;background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:10px 14px}
.cpd-level-bar{height:8px;border-radius:4px;flex-shrink:0}
.cpd-level-info{flex:1}
.cpd-level-name{font-size:.82rem;font-weight:700;color:#1e293b}
.cpd-level-range{font-size:.73rem;color:#94a3b8}

/* ── KPI GRID ── */
.kpi-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:10px;margin:12px 0}
.kpi-card{background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:14px;text-align:center}
.kpi-card .kpi-val{font-size:1.5rem;font-weight:800;color:var(--teal)}
.kpi-card .kpi-label{font-size:.72rem;color:#94a3b8;margin-top:2px}

/* ── SEARCH HIGHLIGHT ── */
.highlight{background:#fef08a;border-radius:2px}

/* ── PRINT ── */
@media print{
  .topbar,.sidebar,#progress-bar,.btn-print{display:none!important}
  .main{margin-left:0;padding-top:0}
  .section{break-inside:avoid}
  .callout,.program-card,.module-item,.step{break-inside:avoid}
}

/* ── RESPONSIVE ── */
@media(max-width:768px){
  .sidebar{transform:translateX(-100%);transition:transform .3s}
  .sidebar.open{transform:translateX(0)}
  .main{margin-left:0}
  .content{padding:24px 16px 60px}
  .topbar-search input{width:140px}
}
</style>
</head>
<body>
<div id="progress-bar"></div>

<!-- TOP HEADER -->
<header class="topbar">
  <a href="{{ url('/') }}" class="topbar-logo">
    <svg width="28" height="28" viewBox="0 0 28 28" fill="none"><circle cx="14" cy="14" r="14" fill="#0891B2"/><path d="M7 14h14M14 7v14" stroke="#fff" stroke-width="2.5" stroke-linecap="round"/></svg>
    <div>
      <div>MNCH Kenya</div>
      <span>User Manual</span>
    </div>
  </a>
  <div class="topbar-divider"></div>
  <div class="topbar-title">Platform Guide — All Programs &amp; Roles</div>
  <div class="topbar-search">
    <span class="topbar-search-icon">&#128269;</span>
    <input type="text" id="searchInput" placeholder="Search manual…" oninput="doSearch(this.value)">
  </div>
  <button class="btn-print" onclick="window.print()">&#128438; Print</button>
  <button class="btn-print" onclick="toggleSidebar()" style="display:none" id="menuBtn">&#9776;</button>
</header>

<!-- SIDEBAR -->
<nav class="sidebar" id="sidebar">

  <div class="sidebar-section open">
    <div class="sidebar-section-header" onclick="toggleSection(this)">
      <span class="sidebar-section-label">Overview</span>
      <span class="sidebar-section-arrow">&#9654;</span>
    </div>
    <div class="sidebar-links">
      <a href="#intro" class="sidebar-link"><span class="dot"></span>Introduction</a>
      <a href="#programs" class="sidebar-link"><span class="dot"></span>The Three Programs</a>
      <a href="#roles" class="sidebar-link"><span class="dot"></span>User Roles</a>
      <a href="#getting-started" class="sidebar-link"><span class="dot"></span>Getting Started</a>
    </div>
  </div>

  <div class="sidebar-section open">
    <div class="sidebar-section-header" onclick="toggleSection(this)">
      <span class="sidebar-section-label">Mentee Guide</span>
      <span class="sidebar-section-arrow">&#9654;</span>
    </div>
    <div class="sidebar-links">
      <a href="#mentee-enroll" class="sidebar-link"><span class="dot"></span>Enrollment</a>
      <a href="#mentee-dashboard" class="sidebar-link"><span class="dot"></span>Mentee Dashboard</a>
      <a href="#mentee-modules" class="sidebar-link"><span class="dot"></span>Working Through Modules</a>
      <a href="#mentee-activities" class="sidebar-link"><span class="dot"></span>Activities &amp; Pre/Post-Tests</a>
      <a href="#mentee-video" class="sidebar-link"><span class="dot"></span>Video Submission (EmONC)</a>
      <a href="#mentee-cert" class="sidebar-link"><span class="dot"></span>Certificates &amp; CPD</a>
    </div>
  </div>

  <div class="sidebar-section open">
    <div class="sidebar-section-header" onclick="toggleSection(this)">
      <span class="sidebar-section-label">Mentor Guide</span>
      <span class="sidebar-section-arrow">&#9654;</span>
    </div>
    <div class="sidebar-links">
      <a href="#mentor-dashboard" class="sidebar-link"><span class="dot"></span>Mentor Dashboard</a>
      <a href="#mentor-create" class="sidebar-link"><span class="dot"></span>Creating a Mentorship</a>
      <a href="#mentor-classes" class="sidebar-link"><span class="dot"></span>Managing Classes</a>
      <a href="#mentor-modules" class="sidebar-link"><span class="dot"></span>Module Sessions</a>
      <a href="#mentor-enroll" class="sidebar-link"><span class="dot"></span>Enrolling Mentees</a>
      <a href="#mentor-review" class="sidebar-link"><span class="dot"></span>Reviewing &amp; Approving</a>
      <a href="#mentor-cert" class="sidebar-link"><span class="dot"></span>Mentor Certificate</a>
    </div>
  </div>

  <div class="sidebar-section open">
    <div class="sidebar-section-header" onclick="toggleSection(this)">
      <span class="sidebar-section-label">Programs</span>
      <span class="sidebar-section-arrow">&#9654;</span>
    </div>
    <div class="sidebar-links">
      <a href="#emonc" class="sidebar-link"><span class="dot"></span>EmONC — Maternal Health</a>
      <a href="#newborn" class="sidebar-link"><span class="dot"></span>Newborn Care</a>
      <a href="#infant" class="sidebar-link"><span class="dot"></span>Infant &amp; Child Care</a>
    </div>
  </div>

  <div class="sidebar-section open">
    <div class="sidebar-section-header" onclick="toggleSection(this)">
      <span class="sidebar-section-label">Head DRMH</span>
      <span class="sidebar-section-arrow">&#9654;</span>
    </div>
    <div class="sidebar-links">
      <a href="#drmh-overview" class="sidebar-link"><span class="dot"></span>Role Overview</a>
      <a href="#drmh-approve" class="sidebar-link"><span class="dot"></span>Approving Certificates</a>
    </div>
  </div>

  <div class="sidebar-section open">
    <div class="sidebar-section-header" onclick="toggleSection(this)">
      <span class="sidebar-section-label">CPD &amp; Certificates</span>
      <span class="sidebar-section-arrow">&#9654;</span>
    </div>
    <div class="sidebar-links">
      <a href="#cpd-system" class="sidebar-link"><span class="dot"></span>CPD Points System</a>
      <a href="#cpd-levels" class="sidebar-link"><span class="dot"></span>CPD Levels</a>
      <a href="#cert-download" class="sidebar-link"><span class="dot"></span>Downloading Certificates</a>
    </div>
  </div>

  <div class="sidebar-section open">
    <div class="sidebar-section-header" onclick="toggleSection(this)">
      <span class="sidebar-section-label">Analytics Dashboard</span>
      <span class="sidebar-section-arrow">&#9654;</span>
    </div>
    <div class="sidebar-links">
      <a href="#analytics-overview" class="sidebar-link"><span class="dot"></span>Dashboard Overview</a>
      <a href="#analytics-mentorships" class="sidebar-link"><span class="dot"></span>Mentorships Mode</a>
      <a href="#analytics-emonc" class="sidebar-link"><span class="dot"></span>EmONC Mode</a>
      <a href="#analytics-mentors" class="sidebar-link"><span class="dot"></span>Mentors Mode</a>
      <a href="#analytics-assessments" class="sidebar-link"><span class="dot"></span>Assessments Mode</a>
      <a href="#analytics-trainings" class="sidebar-link"><span class="dot"></span>Trainings Mode</a>
    </div>
  </div>

  <div class="sidebar-section open">
    <div class="sidebar-section-header" onclick="toggleSection(this)">
      <span class="sidebar-section-label">Admin Functions</span>
      <span class="sidebar-section-arrow">&#9654;</span>
    </div>
    <div class="sidebar-links">
      <a href="#admin-users" class="sidebar-link"><span class="dot"></span>User Management</a>
      <a href="#admin-facilities" class="sidebar-link"><span class="dot"></span>Facilities &amp; Geography</a>
      <a href="#admin-assessments" class="sidebar-link"><span class="dot"></span>Facility Assessments</a>
      <a href="#admin-knowledge" class="sidebar-link"><span class="dot"></span>Knowledge Base</a>
      <a href="#admin-inventory" class="sidebar-link"><span class="dot"></span>Inventory</a>
      <a href="#admin-indicators" class="sidebar-link"><span class="dot"></span>Indicators &amp; Reports</a>
    </div>
  </div>

</nav>

<!-- MAIN CONTENT -->
<main class="main">
<div class="content" id="manualContent">

  <!-- ══════════════════════════════════
       INTRO
  ══════════════════════════════════ -->
  <div id="intro" class="section section-anchor">
    <h1 class="page-title">MNCH Platform — User Manual</h1>
    <p class="page-subtitle">
      Complete guide for all users of the Maternal, Newborn and Child Health (MNCH) mentorship and training platform.
      This manual covers every role, every program, and every feature — from first login to certificate download.
    </p>

    <h2 class="sec-title">&#127968; About This Platform</h2>
    <p>
      The MNCH Mentorship Platform is Kenya's national facility-based mentorship system for Maternal, Newborn and Child Health.
      It enables structured clinical mentorship across facilities, tracks participant progress module-by-module, manages
      CPD (Continuing Professional Development) points, and generates verified digital certificates.
    </p>
    <p>
      The platform is used by mentees (healthcare workers being mentored), mentors (clinical experts facilitating the programs),
      Head DRMH officers (who approve final certifications), and administrators who manage users, geography, assessments, and reporting.
    </p>
    <div class="callout callout-info">
      <strong>Access the platform</strong>
      Sign in at <strong>/admin</strong> (admin panel) or visit <strong>/</strong> to browse the public homepage, resource library, and ongoing mentorship programs.
    </div>
  </div>

  <!-- ══════════════════════════════════
       THREE PROGRAMS
  ══════════════════════════════════ -->
  <div id="programs" class="section section-anchor">
    <h2 class="sec-title">&#127891; The Three Programs <span class="badge" style="background:#dbeafe;color:#1e40af">Core</span></h2>
    <p>The platform supports three distinct clinical mentorship programs, each with its own module set and certification pathway:</p>

    <div class="program-grid">
      <div class="program-card cyan">
        <h4 style="color:var(--cyan-dark)">&#128147; Newborn Care</h4>
        <div class="mod-count" style="color:var(--cyan)">12</div>
        <div class="mod-label">Modules &nbsp;·&nbsp; Facility Mentorship</div>
        <p style="font-size:.78rem;color:#0e7490;margin-top:8px">
          Covers essential newborn care from immediate care at birth through KMC, CPAP, phototherapy, and neonatal sepsis management.
        </p>
      </div>
      <div class="program-card green">
        <h4 style="color:var(--green-dark)">&#128118; Infant &amp; Child Care</h4>
        <div class="mod-count" style="color:var(--green)">10</div>
        <div class="mod-label">Modules &nbsp;·&nbsp; Facility Mentorship</div>
        <p style="font-size:.78rem;color:#166534;margin-top:8px">
          Covers IMNCI, nutrition assessment, malaria case management, pneumonia, diarrhoea, and routine immunisation.
        </p>
      </div>
      <div class="program-card rose">
        <h4 style="color:var(--rose-dark)">&#10084;&#65039; Maternal Health (EmONC)</h4>
        <div class="mod-count" style="color:var(--rose)">13</div>
        <div class="mod-label">Modules &nbsp;·&nbsp; Facility Mentorship</div>
        <p style="font-size:.78rem;color:#9f1239;margin-top:8px">
          Emergency Obstetric &amp; Neonatal Care. Includes haemorrhage, eclampsia, sepsis, obstructed labour, and hands-on skills with video assessment.
        </p>
      </div>
    </div>

    <div class="callout callout-tip">
      <strong>Which program should I join?</strong>
      Your mentor or facility in-charge will enrol you in the appropriate program based on your cadre and service area.
      You may be enrolled in more than one program.
    </div>
  </div>

  <!-- ══════════════════════════════════
       USER ROLES
  ══════════════════════════════════ -->
  <div id="roles" class="section section-anchor">
    <h2 class="sec-title">&#128101; User Roles</h2>
    <p>The platform uses role-based access control. Your role determines what you can see and do.</p>

    <table>
      <thead>
        <tr><th>Role</th><th>Primary Responsibilities</th><th>Scope</th></tr>
      </thead>
      <tbody>
        <tr>
          <td><span class="role-badge slate">mentee</span></td>
          <td>Complete modules, activities, pre/post-tests, submit videos, download certificates</td>
          <td>Own enrolled classes only</td>
        </tr>
        <tr>
          <td><span class="role-badge teal">facility_mentor</span></td>
          <td>Facilitate module sessions, review mentee progress and videos, approve completion</td>
          <td>Own mentorships &amp; classes</td>
        </tr>
        <tr>
          <td><span class="role-badge teal">facility_mentor_lead</span></td>
          <td>Senior mentor — all facility mentor capabilities plus reporting</td>
          <td>Facility</td>
        </tr>
        <tr>
          <td><span class="role-badge cyan">spoke_mentor</span> / <span class="role-badge cyan">spoke_mentor_lead</span></td>
          <td>Spoke site mentors; same as facility mentor within their facility</td>
          <td>Assigned facilities</td>
        </tr>
        <tr>
          <td><span class="role-badge rose">head_drmh</span></td>
          <td>Approve final certificates for mentees after mentor sign-off (EmONC)</td>
          <td>County / Subcounty</td>
        </tr>
        <tr>
          <td><span class="role-badge navy">county_mentor_lead</span></td>
          <td>Oversee mentorships across county, analytics, scheduling</td>
          <td>County</td>
        </tr>
        <tr>
          <td><span class="role-badge navy">subcounty_mentor_lead</span></td>
          <td>Oversee subcounty mentorships and progress</td>
          <td>Subcounty</td>
        </tr>
        <tr>
          <td><span class="role-badge navy">national_mentor_lead</span> / <span class="role-badge navy">division</span></td>
          <td>National-level analytics, program oversight, all data visible</td>
          <td>National</td>
        </tr>
        <tr>
          <td><span class="role-badge slate">admin</span> / <span class="role-badge slate">super_admin</span></td>
          <td>Full system access — users, settings, all resources</td>
          <td>All</td>
        </tr>
      </tbody>
    </table>

    <div class="callout callout-info">
      <strong>Geographic scoping</strong>
      Users assigned at facility/subcounty/county level only see data for their geography. Users at National, Division Lead, or Admin level see all data across Kenya.
    </div>
  </div>

  <!-- ══════════════════════════════════
       GETTING STARTED
  ══════════════════════════════════ -->
  <div id="getting-started" class="section section-anchor">
    <h2 class="sec-title">&#128273; Getting Started</h2>

    <h3 class="sub-title">Logging In</h3>
    <div class="steps">
      <div class="step"><div class="step-num"></div><div class="step-body"><strong>Go to the login page</strong><p>Visit <code>/admin</code> or click <em>Sign In</em> on the homepage. Use your email and password.</p></div></div>
      <div class="step"><div class="step-num"></div><div class="step-body"><strong>First-time setup</strong><p>New accounts receive a verification email. Click the link in the email to set your password before your first login.</p></div></div>
      <div class="step"><div class="step-num"></div><div class="step-body"><strong>Dashboard redirect</strong><p>Mentees are taken directly to their Mentee Dashboard. All other roles land on the Admin panel home.</p></div></div>
    </div>

    <h3 class="sub-title">Navigation</h3>
    <p>The left sidebar in the admin panel is grouped by function:</p>
    <table>
      <thead><tr><th>Sidebar Group</th><th>Contains</th></tr></thead>
      <tbody>
        <tr><td><strong>Dashboards</strong></td><td>Mentee Dashboard, Mentor Dashboard, Analytics</td></tr>
        <tr><td><strong>Mentorships</strong></td><td>Newborn Care, Infant &amp; Child Care, Maternal Health (EmONC) program views</td></tr>
        <tr><td><strong>Training Management</strong></td><td>Global training events and participants</td></tr>
        <tr><td><strong>Facility Assessment</strong></td><td>Readiness assessments per facility</td></tr>
        <tr><td><strong>Knowledge Base</strong></td><td>Resources, articles, guidelines</td></tr>
        <tr><td><strong>Indicators</strong></td><td>Monthly indicator reporting</td></tr>
        <tr><td><strong>Inventory</strong></td><td>Stock tracking and transfers</td></tr>
        <tr><td><strong>Organization Units</strong></td><td>Counties, subcounties, facilities</td></tr>
      </tbody>
    </table>

    <div class="callout callout-tip">
      <strong>Quick tip</strong>
      Use the <em>Home</em> link in the top-right user menu to return to the public homepage at any time.
    </div>
  </div>

  <!-- ══════════════════════════════════
       MENTEE — ENROLLMENT
  ══════════════════════════════════ -->
  <div id="mentee-enroll" class="section section-anchor">
    <h2 class="sec-title">&#128203; Mentee Enrollment <span class="badge" style="background:#f1f5f9;color:#475569">Mentee</span></h2>

    <p>There are two ways to enroll as a mentee in a mentorship class:</p>

    <h3 class="sub-title">Option A — Invitation Email</h3>
    <div class="steps">
      <div class="step"><div class="step-num"></div><div class="step-body"><strong>Receive invitation</strong><p>Your mentor sends an enrollment invitation to your email address from the Manage Class page.</p></div></div>
      <div class="step"><div class="step-num"></div><div class="step-body"><strong>Click the enrollment link</strong><p>The email contains a unique tokenised link. Click it to open the enrollment page (no login required).</p></div></div>
      <div class="step"><div class="step-num"></div><div class="step-body"><strong>Confirm your details</strong><p>Review your name and class, then confirm enrollment. You will be added to the class roster.</p></div></div>
      <div class="step"><div class="step-num"></div><div class="step-body"><strong>Account verification</strong><p>If you are a new user, you will receive a second email to set your password and verify your account.</p></div></div>
    </div>

    <h3 class="sub-title">Option B — Mentor Direct Enrollment</h3>
    <p>Mentors can also search for existing users and add them directly from the class management page without sending an email. Contact your mentor if you need to be added to a class.</p>

    <div class="callout callout-warn">
      <strong>One email per invitation</strong>
      Each invitation link is unique and single-use per mentee-class pair. If you lose the email, ask your mentor to resend the invitation.
    </div>
  </div>

  <!-- ══════════════════════════════════
       MENTEE DASHBOARD
  ══════════════════════════════════ -->
  <div id="mentee-dashboard" class="section section-anchor">
    <h2 class="sec-title">&#128203; Mentee Dashboard <span class="badge" style="background:#f1f5f9;color:#475569">Mentee</span></h2>

    <p>After logging in, mentees land on their personal dashboard at <code>/admin/mentee-dashboard</code>. The dashboard shows all classes you are enrolled in.</p>

    <h3 class="sub-title">Class Cards</h3>
    <p>Each enrolled class appears as a card showing:</p>
    <ul>
      <li><strong>Program name</strong> and class name</li>
      <li><strong>Progress bar</strong> — percentage of modules completed</li>
      <li><strong>Module count</strong> — how many modules exist and how many you have completed</li>
      <li><strong>Class status</strong> — Active, Completed, or Upcoming</li>
      <li><strong>Your status</strong> — whether you have been certified in this class</li>
      <li><strong>CPD points</strong> earned so far from this class</li>
    </ul>

    <h3 class="sub-title">Accessing a Class</h3>
    <p>Click <em>View Progress</em> on any class card to open the class progress page, which lists all modules in order.</p>

    <h3 class="sub-title">AI Advisor (where available)</h3>
    <p>Some classes include an AI study advisor button that provides personalised recommendations based on your current progress, weakest modules, and upcoming activities.</p>
  </div>

  <!-- ══════════════════════════════════
       MENTEE — MODULES
  ══════════════════════════════════ -->
  <div id="mentee-modules" class="section section-anchor">
    <h2 class="sec-title">&#128218; Working Through Modules <span class="badge" style="background:#f1f5f9;color:#475569">Mentee</span></h2>

    <p>Each module must be completed in sequence. The module detail page is the core of your learning experience.</p>

    <h3 class="sub-title">Module Status</h3>
    <table>
      <thead><tr><th>Status</th><th>Meaning</th></tr></thead>
      <tbody>
        <tr><td>&#9900; Locked</td><td>You must complete the previous module first</td></tr>
        <tr><td>&#9679; Available</td><td>You can open and work on this module</td></tr>
        <tr><td>&#9989; Completed</td><td>All activities and post-test done, mentor has approved</td></tr>
      </tbody>
    </table>

    <h3 class="sub-title">Inside a Module</h3>
    <p>Each module page contains:</p>
    <ul>
      <li><strong>Module notes / reading material</strong> — clinical content to review before activities</li>
      <li><strong>Pre-test</strong> — a knowledge check before the session</li>
      <li><strong>Activities</strong> — structured clinical tasks to complete with your mentor</li>
      <li><strong>Video submission</strong> — EmONC modules with hands-on skills require uploading a competency video</li>
      <li><strong>Post-test</strong> — a knowledge check after the session</li>
    </ul>

    <h3 class="sub-title">Attendance Confirmation</h3>
    <p>When your mentor starts a class session, they share an attendance link or QR code. Visit the link (<code>/module/attend/{token}</code>) to confirm your attendance for that session. Attendance is required before some module activities unlock.</p>
  </div>

  <!-- ══════════════════════════════════
       MENTEE — ACTIVITIES & TESTS
  ══════════════════════════════════ -->
  <div id="mentee-activities" class="section section-anchor">
    <h2 class="sec-title">&#9997;&#65039; Activities &amp; Pre/Post-Tests <span class="badge" style="background:#f1f5f9;color:#475569">Mentee</span></h2>

    <h3 class="sub-title">Pre-Test</h3>
    <p>The pre-test assesses your baseline knowledge before the module session. It does not need a passing score — it is for measurement only. Complete it before the facilitated session.</p>

    <h3 class="sub-title">Activities</h3>
    <p>Each module has a structured set of activities — these are clinical tasks you perform during the mentorship session alongside your mentor. For each activity:</p>
    <div class="steps">
      <div class="step"><div class="step-num"></div><div class="step-body"><strong>Read the activity description</strong><p>Understand what skill or task you need to demonstrate.</p></div></div>
      <div class="step"><div class="step-num"></div><div class="step-body"><strong>Perform the activity</strong><p>Carry out the clinical task during your session with the mentor present.</p></div></div>
      <div class="step"><div class="step-num"></div><div class="step-body"><strong>Mark as complete</strong><p>Once done, tick the activity as complete on the platform. Your mentor can also mark it from their side.</p></div></div>
    </div>

    <h3 class="sub-title">Post-Test</h3>
    <p>After the session, complete the post-test. A passing score is required for the module to be marked complete. If you do not pass, review the module content and retake the test.</p>

    <div class="callout callout-info">
      <strong>Score tracking</strong>
      Both pre-test and post-test scores are visible on your progress page. Your mentor can also view your scores when reviewing your progress.
    </div>
  </div>

  <!-- ══════════════════════════════════
       MENTEE — VIDEO SUBMISSION
  ══════════════════════════════════ -->
  <div id="mentee-video" class="section section-anchor">
    <h2 class="sec-title">&#127909; Video Submission <span class="badge" style="background:#ffe4e6;color:#9f1239">EmONC Only</span></h2>

    <p>EmONC modules that include hands-on clinical skills require you to submit a short video demonstrating your competency. This is the primary assessment method for clinical skills.</p>

    <div class="steps">
      <div class="step"><div class="step-num"></div><div class="step-body"><strong>Record your competency video</strong><p>Record yourself performing the clinical skill (e.g. active management of third stage of labour, bimanual compression). The video should be clear and demonstrate the key steps.</p></div></div>
      <div class="step"><div class="step-num"></div><div class="step-body"><strong>Upload the video</strong><p>On the module detail page, find the <em>Video Submission</em> section and upload your recording. Supported formats: MP4, MOV, AVI.</p></div></div>
      <div class="step"><div class="step-num"></div><div class="step-body"><strong>Await mentor review</strong><p>Your mentor will review the video and give a binary <em>Pass</em> or <em>Fail</em> result. You will be notified when a decision is made.</p></div></div>
      <div class="step"><div class="step-num"></div><div class="step-body"><strong>If failed — resubmit</strong><p>If your video receives a Fail, you can upload a new recording. There is no limit on resubmissions.</p></div></div>
      <div class="step"><div class="step-num"></div><div class="step-body"><strong>Pass leads to completion</strong><p>A passed video, combined with completed activities and post-test, allows your mentor to mark the module complete.</p></div></div>
    </div>

    <div class="callout callout-warn">
      <strong>Tips for a successful video</strong>
      Good lighting, a stable camera, and a co-worker to record you will significantly improve your video quality. Narrate each step as you perform it so the assessor can follow your reasoning.
    </div>
  </div>

  <!-- ══════════════════════════════════
       MENTEE — CERTIFICATES
  ══════════════════════════════════ -->
  <div id="mentee-cert" class="section section-anchor">
    <h2 class="sec-title">&#127942; Certificates &amp; CPD <span class="badge" style="background:#f1f5f9;color:#475569">Mentee</span></h2>

    <p>Upon completing all modules in a class and receiving final approval, you are awarded a digital certificate and CPD points.</p>

    <h3 class="sub-title">Certificate Pathway (EmONC — Two-Step)</h3>
    <div class="flow">
      <div class="flow-item teal">All modules completed</div><div class="flow-arrow">→</div>
      <div class="flow-item teal">Mentor approves</div><div class="flow-arrow">→</div>
      <div class="flow-item rose">Head DRMH approves</div><div class="flow-arrow">→</div>
      <div class="flow-item green">Certificate issued</div>
    </div>

    <h3 class="sub-title">Certificate Pathway (Newborn / Infant — Single-Step)</h3>
    <div class="flow">
      <div class="flow-item teal">All modules completed</div><div class="flow-arrow">→</div>
      <div class="flow-item teal">Mentor approves</div><div class="flow-arrow">→</div>
      <div class="flow-item green">Certificate issued</div>
    </div>

    <h3 class="sub-title">Downloading Your Certificate</h3>
    <p>Once issued, the certificate appears on your Mentee Dashboard class card. Click <em>Download Certificate</em> to get a PDF. The certificate includes:</p>
    <ul>
      <li>Your full name and cadre</li>
      <li>Program name and class name</li>
      <li>Facility and date of completion</li>
      <li>CPD points awarded and your current CPD level</li>
      <li>QR code for verification</li>
      <li>Mentor's and Head DRMH's name (if applicable)</li>
    </ul>

    <div class="callout callout-info">
      <strong>CPD points from a certificate</strong>
      Each certificate earns you <strong>3 CPD points</strong>. Additionally, each module you complete in a <em>closed</em> class earns <strong>1 CPD point</strong>. Points accumulate across all programs.
    </div>
  </div>

  <!-- ══════════════════════════════════
       MENTOR DASHBOARD
  ══════════════════════════════════ -->
  <div id="mentor-dashboard" class="section section-anchor">
    <h2 class="sec-title">&#128203; Mentor Dashboard <span class="badge" style="background:var(--teal-light);color:var(--teal-dark)">Mentor</span></h2>

    <p>Mentors access their dashboard at <code>/admin/mentor-dashboard</code> or via the <em>Dashboards</em> sidebar group. The dashboard can be filtered by program (Newborn Care, Infant &amp; Child Care, or EmONC) using the tabs at the top.</p>

    <h3 class="sub-title">Dashboard Panels</h3>
    <ul>
      <li><strong>Active Classes</strong> — classes currently in progress, with mentee count and module completion percentage</li>
      <li><strong>Pending Actions</strong> — mentee videos awaiting review, modules awaiting approval, mentees ready for certification</li>
      <li><strong>Program Overview</strong> — all your mentorships, their classes, and overall status</li>
      <li><strong>Recent Activity</strong> — latest actions taken by mentees in your classes</li>
    </ul>

    <div class="callout callout-tip">
      <strong>Pending actions badge</strong>
      The red badge on Pending Actions shows how many items require your attention right now. Clear these regularly to keep your mentees progressing.
    </div>
  </div>

  <!-- ══════════════════════════════════
       MENTOR — CREATE MENTORSHIP
  ══════════════════════════════════ -->
  <div id="mentor-create" class="section section-anchor">
    <h2 class="sec-title">&#128640; Creating a Mentorship <span class="badge" style="background:var(--teal-light);color:var(--teal-dark)">Mentor</span></h2>

    <p>A <em>Mentorship</em> (also called a <em>Training</em> of type <code>facility_mentorship</code>) is the top-level container for a program at a facility. It groups one or more cohort <em>Classes</em>.</p>

    <div class="steps">
      <div class="step"><div class="step-num"></div><div class="step-body"><strong>Go to Mentorship Trainings</strong><p>In the admin sidebar, navigate to <em>Training Management → Mentorship Trainings</em>.</p></div></div>
      <div class="step"><div class="step-num"></div><div class="step-body"><strong>Click Create</strong><p>Fill in: Training name, Program (Newborn / Infant / EmONC), Facility, Start and End dates, description.</p></div></div>
      <div class="step"><div class="step-num"></div><div class="step-body"><strong>Add co-mentors (optional)</strong><p>You can invite co-mentors by email. They receive an acceptance link and gain access to the mentorship once they accept.</p></div></div>
      <div class="step"><div class="step-num"></div><div class="step-body"><strong>Save and create classes</strong><p>After saving, you can add cohort classes from the mentorship detail page.</p></div></div>
    </div>

    <div class="callout callout-info">
      <strong>Program selection matters</strong>
      The program you select determines which modules are available in the classes. You cannot change the program after saving.
    </div>
  </div>

  <!-- ══════════════════════════════════
       MENTOR — MANAGING CLASSES
  ══════════════════════════════════ -->
  <div id="mentor-classes" class="section section-anchor">
    <h2 class="sec-title">&#128101; Managing Classes <span class="badge" style="background:var(--teal-light);color:var(--teal-dark)">Mentor</span></h2>

    <p>Each mentorship can contain multiple cohort <em>Classes</em> — typically one cohort per intake of mentees.</p>

    <h3 class="sub-title">Class Lifecycle</h3>
    <div class="flow">
      <div class="flow-item">Draft</div><div class="flow-arrow">→</div>
      <div class="flow-item cyan">Active</div><div class="flow-arrow">→</div>
      <div class="flow-item green">Completed</div>
    </div>
    <ul style="margin-top:12px">
      <li><strong>Draft</strong> — class is set up but not yet started. Mentees can be enrolled but modules are not facilitated.</li>
      <li><strong>Active</strong> — class is running. Modules are being facilitated session by session.</li>
      <li><strong>Completed</strong> — all modules are done. CPD module points are now awarded to mentees.</li>
    </ul>

    <h3 class="sub-title">Class Management Page</h3>
    <p>From the class page you can:</p>
    <ul>
      <li>View all enrolled mentees and their progress</li>
      <li>Add or remove mentees</li>
      <li>Manage module sessions</li>
      <li>View class-level attendance</li>
      <li>Change class status (Active → Completed)</li>
      <li>Print a class report</li>
    </ul>
  </div>

  <!-- ══════════════════════════════════
       MENTOR — MODULE SESSIONS
  ══════════════════════════════════ -->
  <div id="mentor-modules" class="section section-anchor">
    <h2 class="sec-title">&#128209; Module Sessions <span class="badge" style="background:var(--teal-light);color:var(--teal-dark)">Mentor</span></h2>

    <p>Each module in a class is facilitated as a session. You control when each module session starts and ends.</p>

    <div class="steps">
      <div class="step"><div class="step-num"></div><div class="step-body"><strong>Open a module for the class</strong><p>From Manage Class Modules, click <em>Start Session</em> on a module. This generates an attendance link/QR code.</p></div></div>
      <div class="step"><div class="step-num"></div><div class="step-body"><strong>Share the attendance link</strong><p>Share the QR code or URL with mentees so they can confirm attendance on their devices.</p></div></div>
      <div class="step"><div class="step-num"></div><div class="step-body"><strong>Facilitate the session</strong><p>Conduct the clinical training session. Mentees complete activities and tests on the platform during or after the session.</p></div></div>
      <div class="step"><div class="step-num"></div><div class="step-body"><strong>Review mentee submissions</strong><p>After the session, check each mentee's activity completion, post-test score, and (for EmONC) video submission.</p></div></div>
      <div class="step"><div class="step-num"></div><div class="step-body"><strong>Mark module complete</strong><p>Once a mentee has met all criteria, mark their module progress as complete from the Review Mentee page.</p></div></div>
    </div>
  </div>

  <!-- ══════════════════════════════════
       MENTOR — ENROLLING MENTEES
  ══════════════════════════════════ -->
  <div id="mentor-enroll" class="section section-anchor">
    <h2 class="sec-title">&#128100; Enrolling Mentees <span class="badge" style="background:var(--teal-light);color:var(--teal-dark)">Mentor</span></h2>

    <h3 class="sub-title">Via Email Invitation</h3>
    <div class="steps">
      <div class="step"><div class="step-num"></div><div class="step-body"><strong>Open Manage Class Mentees</strong><p>From the class page, go to the Mentees tab.</p></div></div>
      <div class="step"><div class="step-num"></div><div class="step-body"><strong>Send Invitation</strong><p>Enter the mentee's email address and click <em>Send Invitation</em>. They receive an enrollment email with a tokenised link.</p></div></div>
      <div class="step"><div class="step-num"></div><div class="step-body"><strong>Track status</strong><p>The enrollment table shows <em>Pending</em> until the mentee accepts. Once accepted, their status becomes <em>Enrolled</em>.</p></div></div>
    </div>

    <h3 class="sub-title">Direct Enrollment (Existing Users)</h3>
    <p>To add an existing system user directly: use the <em>Add Existing User</em> search on the Mentees page. Search by name or email and select the user.</p>

    <h3 class="sub-title">Bulk Import</h3>
    <p>For large cohorts, download the CSV template from the Mentees page, fill in participant details, and upload the file. The system will create accounts and send invitations in bulk.</p>
  </div>

  <!-- ══════════════════════════════════
       MENTOR — REVIEWING & APPROVING
  ══════════════════════════════════ -->
  <div id="mentor-review" class="section section-anchor">
    <h2 class="sec-title">&#9989; Reviewing &amp; Approving Mentees <span class="badge" style="background:var(--teal-light);color:var(--teal-dark)">Mentor</span></h2>

    <p>The <em>Review Module Mentee</em> page is where you assess each mentee's work for a module. Access it from the module management page by clicking on a mentee's row.</p>

    <h3 class="sub-title">What You Review</h3>
    <ul>
      <li><strong>Pre-test score</strong> — baseline knowledge (informational only)</li>
      <li><strong>Post-test score</strong> — knowledge after session (must be passing)</li>
      <li><strong>Activities</strong> — checklist of completed clinical tasks</li>
      <li><strong>Video submission</strong> (EmONC) — uploaded competency video</li>
      <li><strong>Notes</strong> — any written feedback from the mentee</li>
    </ul>

    <h3 class="sub-title">Video Review (EmONC)</h3>
    <p>Play the video directly in the browser. After watching, select <strong>Pass</strong> or <strong>Fail</strong> and optionally add feedback notes. The mentee is notified of your decision automatically.</p>

    <h3 class="sub-title">Approving for Certification</h3>
    <p>Once all modules are complete and post-tests passed, a <em>Recommend for Certificate</em> button appears on the mentee's class card. Click this to:</p>
    <ul>
      <li>Mark the mentee as ready for certification</li>
      <li>Notify the Head DRMH (for EmONC) or issue the certificate directly (Newborn/Infant programs)</li>
    </ul>
  </div>

  <!-- ══════════════════════════════════
       MENTOR CERTIFICATE
  ══════════════════════════════════ -->
  <div id="mentor-cert" class="section section-anchor">
    <h2 class="sec-title">&#127942; Mentor Certificate <span class="badge" style="background:var(--teal-light);color:var(--teal-dark)">Mentor</span></h2>

    <p>Mentors also earn CPD points and a certificate for facilitating classes.</p>

    <h3 class="sub-title">Mentor CPD Points</h3>
    <ul>
      <li><strong>3 CPD points</strong> — per class you complete as lead mentor</li>
      <li><strong>1 CPD point</strong> — per class module you facilitated to completion</li>
    </ul>

    <h3 class="sub-title">Downloading Your Mentor Certificate</h3>
    <p>On the Analytics Dashboard → Mentors tab, or from your profile, you can download your Mentor Participation Certificate. It lists all classes facilitated, total CPD points, and your current CPD level.</p>
  </div>

  <!-- ══════════════════════════════════
       EMONC PROGRAM
  ══════════════════════════════════ -->
  <div id="emonc" class="section section-anchor">
    <h2 class="sec-title"><span style="color:var(--rose)">&#10084;&#65039;</span> Maternal Health (EmONC) Program <span class="badge" style="background:var(--rose-light);color:var(--rose-dark)">EmONC</span></h2>

    <p>
      Emergency Obstetric and Neonatal Care (EmONC) is the most comprehensive program on the platform, with 13 modules covering the full spectrum of obstetric emergencies and neonatal care. It features video-based skills assessment and a two-step certification process.
    </p>

    <h3 class="sub-title">EmONC Modules</h3>
    <div class="module-list">
      <div class="module-item rose"><span class="module-num">M1</span>Infection Prevention &amp; Control</div>
      <div class="module-item rose"><span class="module-num">M2</span>Focused Antenatal Care</div>
      <div class="module-item rose"><span class="module-num">M3</span>Labour &amp; Delivery (Normal)</div>
      <div class="module-item rose"><span class="module-num">M4</span>Partograph Use</div>
      <div class="module-item rose"><span class="module-num">M5</span>Postpartum Haemorrhage (PPH)</div>
      <div class="module-item rose"><span class="module-num">M6</span>Pre-eclampsia &amp; Eclampsia</div>
      <div class="module-item rose"><span class="module-num">M7</span>Obstructed Labour &amp; Shoulder Dystocia</div>
      <div class="module-item rose"><span class="module-num">M8</span>Sepsis &amp; Puerperal Infection</div>
      <div class="module-item rose"><span class="module-num">M9</span>Newborn Resuscitation</div>
      <div class="module-item rose"><span class="module-num">M10</span>Preterm &amp; Low Birth Weight</div>
      <div class="module-item rose"><span class="module-num">M11</span>HIV in Pregnancy</div>
      <div class="module-item rose"><span class="module-num">M12</span>Nutrition in Pregnancy</div>
      <div class="module-item rose"><span class="module-num">M13</span>Family Planning Post-Partum</div>
    </div>

    <h3 class="sub-title">Module 5 — PPH Sub-tracks</h3>
    <p>Module 5 (Postpartum Haemorrhage) is the most complex module, containing <strong>10 sub-tracks</strong> covering specific PPH management techniques:</p>
    <ul style="columns:2;column-gap:20px">
      <li>Active Management of 3rd Stage (AMTSL)</li>
      <li>Uterine Massage</li>
      <li>Bimanual Compression</li>
      <li>Aortic Compression</li>
      <li>Manual Removal of Placenta</li>
      <li>Exploration of Uterine Cavity</li>
      <li>B-Lynch Suture</li>
      <li>Uterine Balloon Tamponade</li>
      <li>Tranexamic Acid Administration</li>
      <li>Blood Transfusion Setup</li>
    </ul>
    <p>Each sub-track has its own activities and may require a separate video submission.</p>

    <h3 class="sub-title">EmONC Certification Flow</h3>
    <div class="steps">
      <div class="step"><div class="step-num"></div><div class="step-body"><strong>Complete all 13 modules</strong><p>Including all activities, post-tests, and passed video submissions.</p></div></div>
      <div class="step"><div class="step-num"></div><div class="step-body"><strong>Mentor review &amp; sign-off</strong><p>Your mentor reviews all submissions and recommends you for certification.</p></div></div>
      <div class="step"><div class="step-num"></div><div class="step-body"><strong>Head DRMH approval</strong><p>A Head DRMH officer reviews your completion record and makes the final certification decision.</p></div></div>
      <div class="step"><div class="step-num"></div><div class="step-body"><strong>Certificate issued</strong><p>Once approved, download your EmONC certificate from your Mentee Dashboard. It carries 3 CPD points.</p></div></div>
    </div>

    <div class="callout callout-danger">
      <strong>EmONC is mandatory for two-step certification</strong>
      Unlike Newborn and Infant programs (single-step), EmONC requires Head DRMH approval before the certificate is issued. This reflects the high-acuity clinical skills being certified.
    </div>
  </div>

  <!-- ══════════════════════════════════
       NEWBORN CARE
  ══════════════════════════════════ -->
  <div id="newborn" class="section section-anchor">
    <h2 class="sec-title"><span style="color:var(--cyan)">&#128147;</span> Newborn Care Program <span class="badge" style="background:var(--cyan-light);color:var(--cyan-dark)">12 Modules</span></h2>

    <p>
      The Newborn Care program equips healthcare workers with skills to manage newborns from the moment of birth through the early neonatal period. All 12 modules must be completed in sequence.
    </p>

    <h3 class="sub-title">Modules</h3>
    <div class="module-list">
      <div class="module-item"><span class="module-num">M1</span>Immediate Care at Birth</div>
      <div class="module-item"><span class="module-num">M2</span>Neonatal Resuscitation</div>
      <div class="module-item"><span class="module-num">M3</span>Kangaroo Mother Care (KMC)</div>
      <div class="module-item"><span class="module-num">M4</span>Breastfeeding &amp; Nutrition</div>
      <div class="module-item"><span class="module-num">M5</span>Neonatal Sepsis</div>
      <div class="module-item"><span class="module-num">M6</span>Neonatal Jaundice &amp; Phototherapy</div>
      <div class="module-item"><span class="module-num">M7</span>Respiratory Distress &amp; CPAP</div>
      <div class="module-item"><span class="module-num">M8</span>Fluid &amp; Medication Administration</div>
      <div class="module-item"><span class="module-num">M9</span>Preterm Care</div>
      <div class="module-item"><span class="module-num">M10</span>HIV-exposed Newborn</div>
      <div class="module-item"><span class="module-num">M11</span>Birth Defects &amp; Referral</div>
      <div class="module-item"><span class="module-num">M12</span>Discharge &amp; Follow-up</div>
    </div>

    <h3 class="sub-title">Certification</h3>
    <p>Single-step: mentor approval issues the certificate. Head DRMH approval is <strong>not</strong> required for this program.</p>

    <h3 class="sub-title">CPD Points</h3>
    <ul>
      <li>3 CPD points for the certificate</li>
      <li>1 CPD point per module completed (once class is closed) = up to 12 additional points</li>
    </ul>
  </div>

  <!-- ══════════════════════════════════
       INFANT & CHILD CARE
  ══════════════════════════════════ -->
  <div id="infant" class="section section-anchor">
    <h2 class="sec-title"><span style="color:var(--green)">&#128118;</span> Infant &amp; Child Care Program <span class="badge" style="background:var(--green-light);color:var(--green-dark)">10 Modules</span></h2>

    <p>
      The Infant &amp; Child Care program covers the integrated management of neonatal and childhood illness (IMNCI), nutrition, and common conditions in under-5 children.
    </p>

    <h3 class="sub-title">Modules</h3>
    <div class="module-list">
      <div class="module-item green"><span class="module-num">M1</span>IMNCI Overview &amp; Assessment</div>
      <div class="module-item green"><span class="module-num">M2</span>Pneumonia Management</div>
      <div class="module-item green"><span class="module-num">M3</span>Diarrhoea &amp; Dehydration</div>
      <div class="module-item green"><span class="module-num">M4</span>Malaria Case Management</div>
      <div class="module-item green"><span class="module-num">M5</span>Malnutrition &amp; SAM</div>
      <div class="module-item green"><span class="module-num">M6</span>Ear Infections &amp; Throat</div>
      <div class="module-item green"><span class="module-num">M7</span>Anaemia &amp; HIV in Children</div>
      <div class="module-item green"><span class="module-num">M8</span>Immunisation &amp; Growth Monitoring</div>
      <div class="module-item green"><span class="module-num">M9</span>Child Development &amp; Disability</div>
      <div class="module-item green"><span class="module-num">M10</span>Referral &amp; Emergency Signs</div>
    </div>

    <h3 class="sub-title">Certification</h3>
    <p>Single-step: mentor approval issues the certificate. Head DRMH approval is <strong>not</strong> required for this program.</p>

    <h3 class="sub-title">CPD Points</h3>
    <ul>
      <li>3 CPD points for the certificate</li>
      <li>1 CPD point per module completed (once class is closed) = up to 10 additional points</li>
    </ul>
  </div>

  <!-- ══════════════════════════════════
       HEAD DRMH
  ══════════════════════════════════ -->
  <div id="drmh-overview" class="section section-anchor">
    <h2 class="sec-title">&#127963;&#65039; Head DRMH <span class="badge" style="background:var(--rose-light);color:var(--rose-dark)">EmONC</span></h2>

    <p>The Head Deputy Resource Manager for Health (Head DRMH) is the final approval authority for EmONC mentee certificates. This role is assigned at county or subcounty level.</p>

    <h3 class="sub-title">When You Are Notified</h3>
    <p>You receive a notification (email + in-app) when a mentor recommends a mentee for EmONC certification. The notification includes:</p>
    <ul>
      <li>Mentee name and facility</li>
      <li>Class name and module completion summary</li>
      <li>Link to the review page</li>
    </ul>
  </div>

  <div id="drmh-approve" class="section section-anchor">
    <h2 class="sec-title">&#10003; Approving Certificates <span class="badge" style="background:var(--rose-light);color:var(--rose-dark)">Head DRMH</span></h2>

    <div class="steps">
      <div class="step"><div class="step-num"></div><div class="step-body"><strong>Open the HeadDRMH Dashboard</strong><p>Navigate to <em>Dashboards → Head DRMH</em> in the sidebar, or click the link in the notification email.</p></div></div>
      <div class="step"><div class="step-num"></div><div class="step-body"><strong>Review pending approvals</strong><p>The dashboard lists all mentees awaiting your approval, grouped by facility and class.</p></div></div>
      <div class="step"><div class="step-num"></div><div class="step-body"><strong>Review the mentee's record</strong><p>Click the mentee's row to see their full module completion record, scores, and mentor's notes.</p></div></div>
      <div class="step"><div class="step-num"></div><div class="step-body"><strong>Approve or Decline</strong><p>Click <em>Approve Certificate</em> to issue the certificate, or <em>Decline</em> with a reason if you need the mentor to follow up.</p></div></div>
      <div class="step"><div class="step-num"></div><div class="step-body"><strong>Certificate issued automatically</strong><p>On approval, the system generates the PDF certificate and notifies the mentee.</p></div></div>
    </div>

    <div class="callout callout-warn">
      <strong>Declined certificates</strong>
      If you decline, the mentor and mentee are notified with your reason. The mentor must review the gap and re-submit once resolved. You will receive a new notification to re-review.
    </div>
  </div>

  <!-- ══════════════════════════════════
       CPD SYSTEM
  ══════════════════════════════════ -->
  <div id="cpd-system" class="section section-anchor">
    <h2 class="sec-title">&#127775; CPD Points System</h2>

    <p>CPD (Continuing Professional Development) points are awarded automatically for completing training milestones. They accumulate across all programs and classes.</p>

    <h3 class="sub-title">How Mentees Earn CPD Points</h3>
    <table>
      <thead><tr><th>Action</th><th>Points</th><th>When Awarded</th></tr></thead>
      <tbody>
        <tr><td>Certificate issued (any program)</td><td><strong>3 pts</strong></td><td>Immediately on certificate approval</td></tr>
        <tr><td>Module completed (class must be closed)</td><td><strong>1 pt</strong> per module</td><td>When class status changes to Completed</td></tr>
      </tbody>
    </table>

    <h3 class="sub-title">How Mentors Earn CPD Points</h3>
    <table>
      <thead><tr><th>Action</th><th>Points</th><th>When Awarded</th></tr></thead>
      <tbody>
        <tr><td>Class completed (as lead mentor)</td><td><strong>3 pts</strong></td><td>When class is marked Completed</td></tr>
        <tr><td>Class module facilitated to completion</td><td><strong>1 pt</strong> per module</td><td>When the class module is marked complete</td></tr>
      </tbody>
    </table>

    <div class="callout callout-tip">
      <strong>Maximum CPD per program</strong>
      Newborn Care: 3 (cert) + 12 (modules) = <strong>15 points</strong>. Infant &amp; Child: 3 + 10 = <strong>13 points</strong>. EmONC: 3 + 13 = <strong>16 points</strong>.
    </div>
  </div>

  <div id="cpd-levels" class="section section-anchor">
    <h2 class="sec-title">&#127942; CPD Levels</h2>
    <p>Your total CPD points determine your professional level, shown on your profile and certificates:</p>

    <div class="cpd-levels">
      <div class="cpd-level">
        <div class="cpd-level-bar" style="width:80px;background:#94a3b8"></div>
        <div class="cpd-level-info">
          <div class="cpd-level-name">Foundation</div>
          <div class="cpd-level-range">0 – 5 points</div>
        </div>
      </div>
      <div class="cpd-level">
        <div class="cpd-level-bar" style="width:120px;background:#3b82f6"></div>
        <div class="cpd-level-info">
          <div class="cpd-level-name">Practitioner</div>
          <div class="cpd-level-range">6 – 15 points</div>
        </div>
      </div>
      <div class="cpd-level">
        <div class="cpd-level-bar" style="width:160px;background:#0891B2"></div>
        <div class="cpd-level-info">
          <div class="cpd-level-name">Advanced Practitioner</div>
          <div class="cpd-level-range">16 – 30 points</div>
        </div>
      </div>
      <div class="cpd-level">
        <div class="cpd-level-bar" style="width:200px;background:#7c3aed"></div>
        <div class="cpd-level-info">
          <div class="cpd-level-name">Expert</div>
          <div class="cpd-level-range">31 – 50 points</div>
        </div>
      </div>
      <div class="cpd-level">
        <div class="cpd-level-bar" style="width:240px;background:linear-gradient(90deg,#f59e0b,#ef4444)"></div>
        <div class="cpd-level-info">
          <div class="cpd-level-name">Master Practitioner</div>
          <div class="cpd-level-range">51+ points</div>
        </div>
      </div>
    </div>
  </div>

  <div id="cert-download" class="section section-anchor">
    <h2 class="sec-title">&#128438; Downloading Certificates</h2>
    <p>All certificates are available as A4 landscape PDFs. They include a QR code that links to a public verification page.</p>

    <h3 class="sub-title">Mentee Certificate</h3>
    <p>Found on the Mentee Dashboard class card → <em>Download Certificate</em>. Available immediately after approval.</p>

    <h3 class="sub-title">Mentor Certificate</h3>
    <p>Available from the Analytics Dashboard → Mentors view, or from your user profile. Lists all facilitated classes.</p>

    <h3 class="sub-title">Certificate Verification</h3>
    <p>Anyone can scan the QR code on a certificate to verify its authenticity. The verification page shows the mentee's name, program, dates, and issuing authority without requiring a login.</p>
  </div>

  <!-- ══════════════════════════════════
       ANALYTICS DASHBOARD
  ══════════════════════════════════ -->
  <div id="analytics-overview" class="section section-anchor">
    <h2 class="sec-title">&#128202; Analytics Dashboard <span class="badge" style="background:#dbeafe;color:#1e40af">Managers &amp; Above</span></h2>

    <p>The multi-mode analytics dashboard at <code>/analytics/dashboard</code> provides programme-level insights. It has five modes selectable via the tab bar:</p>

    <table>
      <thead><tr><th>Mode</th><th>Title</th><th>Primary Audience</th></tr></thead>
      <tbody>
        <tr><td><strong>Mentorships</strong></td><td>Infant, Child &amp; Newborn Care Dashboard</td><td>County leads, Managers</td></tr>
        <tr><td><strong>EmONC</strong></td><td>Maternal Health (EmONC) Dashboard</td><td>County leads, Head DRMH</td></tr>
        <tr><td><strong>Mentors</strong></td><td>Mentor Analytics</td><td>Program managers</td></tr>
        <tr><td><strong>Assessments</strong></td><td>Facility Readiness</td><td>Admin, County leads</td></tr>
        <tr><td><strong>Trainings</strong></td><td>Global Training Coverage</td><td>National leads</td></tr>
      </tbody>
    </table>

    <div class="callout callout-info">
      <strong>Geographic filtering</strong>
      Users scoped to a county or subcounty only see data for their geography. National and Super Admin users see all-Kenya data. Use the County / Year filter dropdowns to drill down.
    </div>
  </div>

  <div id="analytics-mentorships" class="section section-anchor">
    <h2 class="sec-title">&#128202; Mentorships Mode</h2>
    <p>Shows the status and progress of all facility mentorships (Newborn Care + Infant &amp; Child Care) in your scope.</p>
    <ul>
      <li><strong>KPIs</strong>: Active mentorships, total mentees enrolled, modules facilitated, certified mentees</li>
      <li><strong>Class lifecycle chart</strong>: Distribution of classes by Draft / Active / Completed status</li>
      <li><strong>Module completion trend</strong>: Month-by-month count of modules completed over 12 months</li>
      <li><strong>Mentorship table</strong>: Each row = one mentorship, with class count, mentee count, and progress %</li>
      <li><strong>County heatmap</strong>: Kenya map showing mentorship intensity by county</li>
    </ul>
  </div>

  <div id="analytics-emonc" class="section section-anchor">
    <h2 class="sec-title">&#128202; EmONC Mode</h2>
    <p>Dedicated view for the EmONC program with video pipeline and dual-certification metrics.</p>
    <ul>
      <li><strong>KPIs</strong>: Active EmONC classes, mentees enrolled, videos submitted, videos passed, certificates issued</li>
      <li><strong>Certification pipeline</strong>: Funnel from enrolled → modules complete → mentor-approved → head DRMH approved → certified</li>
      <li><strong>Module completion matrix</strong>: Which modules are complete for each mentee across all active classes</li>
      <li><strong>CPD distribution</strong>: Histogram of CPD points across all EmONC mentees</li>
      <li><strong>Pending actions</strong>: Videos awaiting review, mentees awaiting Head DRMH approval</li>
    </ul>
  </div>

  <div id="analytics-mentors" class="section section-anchor">
    <h2 class="sec-title">&#128202; Mentors Mode</h2>
    <p>Focuses on the mentors themselves — their CPD, activity, and class coverage.</p>
    <ul>
      <li><strong>KPIs</strong>: Active classes, total mentors, total mentees, modules facilitated, certified mentees, CPD awarded</li>
      <li><strong>CPD Leaderboard</strong>: Top mentors by CPD points</li>
      <li><strong>Active class matrix</strong>: One row per active class showing mentor name, cadre, department, facility, module count, mentee count, certified count, CPD total and level</li>
      <li><strong>By Facility chart</strong>: How many unique mentors are active per facility</li>
      <li><strong>By Department chart</strong>: Mentors per department (maternity, paediatrics, etc.)</li>
      <li><strong>By Cadre chart</strong>: Mentors per clinical cadre</li>
      <li><strong>By Module chart</strong>: Which modules have the most mentor engagement</li>
    </ul>
    <p>All chart values count <em>unique mentors</em>, not classes. The matrix table can be sorted by clicking any column header.</p>
  </div>

  <div id="analytics-assessments" class="section section-anchor">
    <h2 class="sec-title">&#128202; Assessments Mode</h2>
    <p>Tracks facility readiness assessments across the geographic hierarchy.</p>
    <ul>
      <li><strong>KPIs</strong>: Facilities assessed, average readiness score, assessments this year</li>
      <li><strong>Score distribution</strong>: Histogram of facility scores</li>
      <li><strong>County breakdown</strong>: Average score per county, colour-coded by threshold</li>
      <li><strong>Trend chart</strong>: Assessment scores over time showing improvement</li>
      <li><strong>Facility table</strong>: Each row = one facility, with latest assessment score and date</li>
    </ul>
  </div>

  <div id="analytics-trainings" class="section section-anchor">
    <h2 class="sec-title">&#128202; Trainings Mode</h2>
    <p>Analytics for centralised (global) training events managed outside the mentorship structure.</p>
    <ul>
      <li><strong>KPIs</strong>: Total trainings, total participants, upcoming events</li>
      <li><strong>Training calendar</strong>: Upcoming global training events</li>
      <li><strong>Coverage map</strong>: Geographic reach of training events</li>
      <li><strong>Participant breakdown</strong>: By cadre, county, and training type</li>
    </ul>
  </div>

  <!-- ══════════════════════════════════
       ADMIN FUNCTIONS
  ══════════════════════════════════ -->
  <div id="admin-users" class="section section-anchor">
    <h2 class="sec-title">&#128100; User Management <span class="badge" style="background:#f1f5f9;color:#475569">Admin</span></h2>

    <p>Manage all platform users from <em>Organization Units → Users</em>.</p>

    <h3 class="sub-title">Creating a User</h3>
    <div class="steps">
      <div class="step"><div class="step-num"></div><div class="step-body"><strong>Click New User</strong><p>Fill in: name, email, phone, cadre, department, and role.</p></div></div>
      <div class="step"><div class="step-num"></div><div class="step-body"><strong>Assign geography</strong><p>Select the user's county, subcounty, and/or facility. This determines their data scope.</p></div></div>
      <div class="step"><div class="step-num"></div><div class="step-body"><strong>Save</strong><p>The system sends a verification email so the user can set their password.</p></div></div>
    </div>

    <h3 class="sub-title">Editing a User</h3>
    <p>Click a user's row to edit their profile, change their role, or update their geographic assignments. Roles take effect immediately.</p>

    <h3 class="sub-title">Regenerating Permissions</h3>
    <p>After adding new resources or changing roles, run <code>php artisan shield:generate --all</code> to regenerate the permission set. This is an admin-only server-side operation.</p>
  </div>

  <div id="admin-facilities" class="section section-anchor">
    <h2 class="sec-title">&#127963;&#65039; Facilities &amp; Geography <span class="badge" style="background:#f1f5f9;color:#475569">Admin</span></h2>

    <p>The geographic hierarchy is: <strong>Division → County → Subcounty → Facility</strong>. Manage these from the <em>Organization Units</em> sidebar group.</p>

    <h3 class="sub-title">Adding a Facility</h3>
    <div class="steps">
      <div class="step"><div class="step-num"></div><div class="step-body"><strong>Go to Organization Units → Facilities</strong><p>Click <em>New Facility</em>.</p></div></div>
      <div class="step"><div class="step-num"></div><div class="step-body"><strong>Fill facility details</strong><p>Name, type (Level 2–6), county, subcounty, GPS coordinates (for the map).</p></div></div>
      <div class="step"><div class="step-num"></div><div class="step-body"><strong>Save</strong><p>The facility is immediately available for mentorship assignment.</p></div></div>
    </div>

    <div class="callout callout-info">
      <strong>GPS coordinates</strong>
      Providing accurate latitude/longitude values allows the facility to appear correctly on the county heatmap in the analytics dashboard.
    </div>
  </div>

  <div id="admin-assessments" class="section section-anchor">
    <h2 class="sec-title">&#128203; Facility Assessments <span class="badge" style="background:#f1f5f9;color:#475569">Admin / Mentor Lead</span></h2>

    <p>Facility assessments measure healthcare readiness. They use a dynamic scored form with multiple sections.</p>

    <h3 class="sub-title">Conducting an Assessment</h3>
    <div class="steps">
      <div class="step"><div class="step-num"></div><div class="step-body"><strong>Go to Facility Assessment</strong><p>Select the facility and assessment form template.</p></div></div>
      <div class="step"><div class="step-num"></div><div class="step-body"><strong>Complete all sections</strong><p>Each section has weighted questions. Answer all and add optional observation notes.</p></div></div>
      <div class="step"><div class="step-num"></div><div class="step-body"><strong>Submit</strong><p>The system calculates the score automatically and saves the assessment with your name and date.</p></div></div>
    </div>

    <h3 class="sub-title">Assessment Reports</h3>
    <p>View historical assessments for a facility, compare scores over time, and download reports from the facility's assessment history page.</p>
  </div>

  <div id="admin-knowledge" class="section section-anchor">
    <h2 class="sec-title">&#128218; Knowledge Base <span class="badge" style="background:#f1f5f9;color:#475569">All Roles</span></h2>

    <p>The knowledge base is a searchable library of clinical guidelines, training materials, forms, and reference documents.</p>

    <h3 class="sub-title">Browsing Resources</h3>
    <p>Visit <code>/resources</code> to browse the public library. Resources are organised by category, type, and tags. Use the search bar to find specific documents.</p>

    <h3 class="sub-title">Access Levels</h3>
    <table>
      <thead><tr><th>Level</th><th>Who Can See</th></tr></thead>
      <tbody>
        <tr><td><strong>Public</strong></td><td>Everyone, including visitors who are not logged in</td></tr>
        <tr><td><strong>Authenticated</strong></td><td>Any logged-in user</td></tr>
        <tr><td><strong>Restricted</strong></td><td>Users in specific access groups, assigned facilities, or authors</td></tr>
      </tbody>
    </table>

    <h3 class="sub-title">Adding a Resource (Admin)</h3>
    <p>From <em>Knowledge Base → Resources → New Resource</em>: upload a file or enter a URL, set the title, category, type, tags, and access level. Resources are immediately searchable after saving.</p>
  </div>

  <div id="admin-inventory" class="section section-anchor">
    <h2 class="sec-title">&#128230; Inventory <span class="badge" style="background:#f1f5f9;color:#475569">Admin / Facility</span></h2>

    <p>The inventory module tracks medical supplies, equipment, and training materials across facilities.</p>

    <h3 class="sub-title">Key Functions</h3>
    <ul>
      <li><strong>Stock levels</strong> — view current stock per item per facility</li>
      <li><strong>Stock in / Stock out</strong> — record supplies received or consumed</li>
      <li><strong>Transfers</strong> — move stock between facilities with a transfer record</li>
      <li><strong>Low stock alerts</strong> — items below threshold appear in the alerts panel</li>
    </ul>

    <p>Access inventory from the <em>Inventory</em> sidebar group. You will only see facilities within your geographic scope.</p>
  </div>

  <div id="admin-indicators" class="section section-anchor">
    <h2 class="sec-title">&#128200; Indicators &amp; Monthly Reports <span class="badge" style="background:#f1f5f9;color:#475569">Admin / Facility</span></h2>

    <p>Indicators are standardised data points collected monthly at facility level — e.g. number of deliveries, ANC visits, neonatal deaths.</p>

    <h3 class="sub-title">Submitting Indicators</h3>
    <div class="steps">
      <div class="step"><div class="step-num"></div><div class="step-body"><strong>Go to Indicators → Indicator Reporting</strong><p>Select your facility and the reporting month.</p></div></div>
      <div class="step"><div class="step-num"></div><div class="step-body"><strong>Fill in all values</strong><p>Each indicator has a label, expected range, and an optional comment field.</p></div></div>
      <div class="step"><div class="step-num"></div><div class="step-body"><strong>Submit for review</strong><p>County leads receive a notification to validate the submitted data.</p></div></div>
    </div>

    <h3 class="sub-title">Monthly Reports</h3>
    <p>The system auto-generates monthly summary reports from indicator data. Reports can be viewed and downloaded from <em>Report Management</em>. Reports are generated by the scheduled <code>GenerateMonthlyReports</code> command that runs on the 1st of each month.</p>

    <h3 class="sub-title">Indicators Dashboard</h3>
    <p>The <em>Indicators → Dashboard</em> page provides aggregated views across facilities: trend lines, county comparisons, and anomaly flags for out-of-range values.</p>

    <div class="callout callout-tip">
      <strong>Validation queue</strong>
      County leads should regularly check the Indicators validation queue to approve or flag submitted data. Unapproved data is excluded from national aggregations.
    </div>
  </div>

  <!-- FOOTER -->
  <div style="margin-top:60px;padding-top:24px;border-top:1px solid #e2e8f0;text-align:center;color:#94a3b8;font-size:.78rem">
    <p>MNCH Kenya — User Manual &nbsp;·&nbsp; Platform Version {{ config('app.version', '1.0') }}</p>
    <p>For support, contact your county mentor lead or system administrator.</p>
    <p style="margin-top:8px"><a href="{{ url('/') }}" style="color:#0891B2;text-decoration:none">← Return to Homepage</a></p>
  </div>

</div><!-- /content -->
</main>

<script>
// ── PROGRESS BAR ──
const bar = document.getElementById('progress-bar');
function updateProgress(){
  const s = document.documentElement.scrollTop, h = document.documentElement.scrollHeight - window.innerHeight;
  bar.style.width = (h ? (s/h*100) : 0)+'%';
}
window.addEventListener('scroll', updateProgress, {passive:true});

// ── SIDEBAR SECTIONS ──
function toggleSection(header){
  const sec = header.closest('.sidebar-section');
  sec.classList.toggle('open');
}

// ── SIDEBAR MOBILE ──
function toggleSidebar(){
  document.getElementById('sidebar').classList.toggle('open');
}
if(window.innerWidth <= 768){
  document.getElementById('menuBtn').style.display='';
}

// ── SCROLL SPY ──
const anchors = [...document.querySelectorAll('.section-anchor')];
const links   = [...document.querySelectorAll('.sidebar-link')];
const hh = parseInt(getComputedStyle(document.documentElement).getPropertyValue('--header-h'))||56;

function updateSpy(){
  let cur = '';
  anchors.forEach(el=>{
    if(el.getBoundingClientRect().top <= hh+30) cur = el.id;
  });
  links.forEach(l=>{
    const active = l.getAttribute('href')==='#'+cur;
    l.classList.toggle('active', active);
    if(active){
      // open the parent section
      const sec = l.closest('.sidebar-section');
      if(sec && !sec.classList.contains('open')) sec.classList.add('open');
    }
  });
}
window.addEventListener('scroll', updateSpy, {passive:true});
updateSpy();

// ── SEARCH ──
function doSearch(q){
  const content = document.getElementById('manualContent');
  if(!q){
    clearHighlights(content);
    return;
  }
  clearHighlights(content);
  if(q.length < 2) return;
  const re = new RegExp('('+escapeRe(q)+')', 'gi');
  highlightText(content, re);
  // scroll to first highlight
  const first = content.querySelector('.highlight');
  if(first) first.scrollIntoView({behavior:'smooth', block:'center'});
}

function highlightText(node, re){
  if(node.nodeType === 3){
    const m = node.textContent.match(re);
    if(m){
      const span = document.createElement('span');
      span.innerHTML = node.textContent.replace(re, '<mark class="highlight">$1</mark>');
      node.parentNode.replaceChild(span, node);
    }
  } else if(node.nodeType === 1 && !['SCRIPT','STYLE','INPUT'].includes(node.tagName)){
    [...node.childNodes].forEach(c=>highlightText(c,re));
  }
}

function clearHighlights(root){
  root.querySelectorAll('mark.highlight').forEach(m=>{
    m.outerHTML = m.textContent;
  });
}

function escapeRe(s){return s.replace(/[.*+?^${}()|[\]\\]/g,'\\$&')}

// ── ANIMATE SECTIONS ON SCROLL ──
const io = new IntersectionObserver(entries=>{
  entries.forEach(e=>{ if(e.isIntersecting) e.target.style.animationPlayState='running'; });
},{threshold:0.05});
document.querySelectorAll('.section').forEach(s=>{
  s.style.animationPlayState='paused';
  io.observe(s);
});
</script>
</body>
</html>
