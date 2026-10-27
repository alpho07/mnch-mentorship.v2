<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>@yield('title') — MNCH Mentorship Guide</title>
<meta name="description" content="@yield('description')">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><style>
:root{
  --navy:#0C1445;--cyan:#0891B2;--cyan-l:#CFFAFE;--teal:#0F766E;--teal-l:#CCFBF1;
  --rose:#BE123C;--rose-l:#FFE4E6;--amber:#B45309;--amber-l:#FEF3C7;--green:#15803D;--green-l:#DCFCE7;
  --ink:#1e293b;--mute:#64748b;--line:#e2e8f0;--bg:#f8fafc;--accent:var(--cyan);--accent-l:var(--cyan-l);
}
.theme-emonc{--accent:var(--rose);--accent-l:var(--rose-l)}
*{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{font-family:system-ui, -apple-system, Segoe UI, Roboto, Helvetica Neue, Arial, sans-serif;color:var(--ink);background:var(--bg);line-height:1.65;font-size:17px}
a{color:var(--accent)}
.wrap{max-width:900px;margin:0 auto;padding:0 20px}

/* top bar */
.bar{position:sticky;top:0;z-index:50;background:rgba(12,20,69,.96);backdrop-filter:blur(10px)}
.bar .wrap{display:flex;align-items:center;gap:14px;height:56px}
.bar a{color:#cbd5e1;text-decoration:none;font-size:.85rem;white-space:nowrap}
.bar a:hover{color:#fff}
.bar .brand{color:#fff;font-weight:700;margin-right:auto}
.bar .brand small{display:block;font-weight:400;font-size:.65rem;color:#94a3b8;line-height:1}
.bar .print{border:1px solid rgba(255,255,255,.25);padding:5px 12px;border-radius:8px}
@media(max-width:640px){.bar .hide-s{display:none}}

/* hero */
.hero{background:radial-gradient(1200px 400px at 85% -10%,rgba(255,255,255,.18),transparent),linear-gradient(135deg,var(--navy),#0b4f6c 60%,var(--accent));color:#fff;padding:64px 0 76px}
.hero .tag{display:inline-block;background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.25);padding:4px 14px;border-radius:99px;font-size:.78rem;letter-spacing:.06em;text-transform:uppercase}
.hero h1{font-size:clamp(2rem,5vw,3.1rem);line-height:1.15;margin:18px 0 14px;font-weight:800}
.hero p{max-width:640px;font-size:1.1rem;color:#dbeafe}
.hero .meta{margin-top:26px;display:flex;flex-wrap:wrap;gap:10px}
.chip{background:rgba(255,255,255,.12);border-radius:10px;padding:7px 14px;font-size:.85rem}

/* journey strip */
.journey{margin-top:-38px;position:relative;z-index:2}
.journey ol{list-style:none;display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:10px;background:#fff;border-radius:18px;padding:16px;box-shadow:0 12px 40px rgba(12,20,69,.14);counter-reset:j}
.journey li{counter-increment:j;text-align:center;padding:10px 6px;border-radius:12px}
.journey li a{text-decoration:none;color:var(--ink);font-size:.85rem;font-weight:600;display:block}
.journey li a::before{content:counter(j);display:flex;align-items:center;justify-content:center;width:34px;height:34px;margin:0 auto 6px;border-radius:50%;background:var(--accent);color:#fff;font-weight:800}
.journey li:hover{background:var(--accent-l)}

/* sections */
section{margin:64px 0 0;scroll-margin-top:76px}
.eyebrow{font-size:.78rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--accent)}
h2{font-size:1.9rem;line-height:1.25;margin:4px 0 10px;color:var(--navy)}
.lead{color:var(--mute);margin-bottom:22px}
h3{font-size:1.1rem;margin:22px 0 8px;color:var(--navy)}
p+p{margin-top:10px}

/* steps timeline */
.steps{list-style:none;counter-reset:s;position:relative;margin:18px 0}
.steps::before{content:"";position:absolute;left:21px;top:10px;bottom:10px;width:3px;background:linear-gradient(var(--accent),var(--accent-l));border-radius:3px}
.steps>li{counter-increment:s;position:relative;padding:0 0 20px 64px}
.steps>li::before{content:counter(s);position:absolute;left:0;top:0;width:44px;height:44px;border-radius:50%;background:var(--accent);color:#fff;font-weight:800;display:flex;align-items:center;justify-content:center;box-shadow:0 0 0 5px var(--bg)}
.steps>li>b{display:block;font-size:1.05rem;color:var(--navy);padding-top:8px}
.steps>li>span{color:#475569;display:block;margin-top:2px}
.steps ul{margin:8px 0 0 18px;color:#475569}

/* cards, callouts */
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:14px;margin:16px 0}
.card{background:#fff;border:1px solid var(--line);border-radius:16px;padding:20px;box-shadow:0 2px 8px rgba(15,23,42,.04)}
.card .ic{font-size:1.7rem}
.card h4{margin:8px 0 4px;color:var(--navy)}
.card p{font-size:.95rem;color:#475569}
.note{display:flex;gap:14px;border-radius:14px;padding:16px 18px;margin:18px 0;font-size:.97rem}
.note .ic{font-size:1.4rem;line-height:1.3}
.note.tip{background:var(--green-l);border-left:5px solid var(--green)}
.note.warn{background:var(--amber-l);border-left:5px solid var(--amber)}
.note.info{background:var(--accent-l);border-left:5px solid var(--accent)}
.note b{display:block}

/* mock screen / link */
.mock{background:#0f172a;color:#e2e8f0;border-radius:14px;padding:16px 20px;font-family:ui-monospace,Menlo,monospace;font-size:.88rem;overflow-x:auto;margin:14px 0}
.mock .k{color:#7dd3fc}
.path{display:inline-flex;flex-wrap:wrap;gap:6px;align-items:center;background:#fff;border:1px solid var(--line);border-radius:10px;padding:6px 12px;font-size:.9rem;font-weight:600}
.path i{font-style:normal;color:var(--mute)}

/* two-column who-does-what */
.duo{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin:16px 0}
@media(max-width:700px){.duo{grid-template-columns:1fr}}
.who{border-radius:16px;padding:20px;border:1px solid var(--line);background:#fff}
.who.mentor{border-top:6px solid var(--teal)}
.who.mentee{border-top:6px solid var(--cyan)}
.who.head{border-top:6px solid var(--rose)}
.who h4{margin-bottom:8px}
.who ul{margin-left:18px;color:#475569}
.who li+li{margin-top:4px}

table{width:100%;border-collapse:collapse;background:#fff;border-radius:14px;overflow:hidden;border:1px solid var(--line);font-size:.93rem;margin:14px 0}
th{background:var(--navy);color:#fff;text-align:left;padding:10px 14px;font-weight:600}
td{padding:10px 14px;border-top:1px solid var(--line);vertical-align:top}
.pill{display:inline-block;padding:2px 10px;border-radius:99px;font-size:.78rem;font-weight:700}
.pill.g{background:var(--green-l);color:var(--green)}.pill.a{background:var(--amber-l);color:var(--amber)}.pill.b{background:var(--accent-l);color:var(--navy)}

details{background:#fff;border:1px solid var(--line);border-radius:12px;padding:14px 18px;margin:8px 0}
summary{cursor:pointer;font-weight:600;color:var(--navy)}
details p{margin-top:8px;color:#475569}

/* footer */
footer{margin-top:90px;background:var(--navy);color:#cbd5e1}
footer .cols{display:grid;grid-template-columns:2fr 1fr 1fr;gap:30px;padding:46px 0 30px}
@media(max-width:700px){footer .cols{grid-template-columns:1fr}}
footer h5{color:#fff;font-size:.8rem;letter-spacing:.1em;text-transform:uppercase;margin-bottom:12px}
footer a{display:block;color:#93c5fd;text-decoration:none;padding:3px 0;font-size:.95rem}
footer a:hover{color:#fff;text-decoration:underline}
footer p{font-size:.9rem;color:#94a3b8}
footer .legal{border-top:1px solid rgba(255,255,255,.1);padding:16px 0 26px;font-size:.8rem;color:#64748b;text-align:center}

@media print{.bar,.journey,footer .cols{display:none}.hero{padding:30px 0;color:#000;background:none}.hero p,.chip{color:#000}section{break-inside:avoid}}
</style>
</head>
<body class="@yield('theme')">
<nav class="bar"><div class="wrap">
  <a class="brand" href="{{ url('/') }}">MNCH Mentorship<small>Ministry of Health · Kenya</small></a>
  <a class="hide-s" href="{{ route('guides.mentorship') }}">Mentorship SOP</a>
  <a class="hide-s" href="{{ route('guides.emonc') }}">EmONC Guide</a>
  <a class="print" href="#" onclick="window.print();return false">Print / Save PDF</a>
</div></nav>

@yield('body')

<footer>
  <div class="wrap">
    <div class="cols">
      <div>
        <h5>MNCH Mentorship Platform</h5>
        <p>Step-by-step guides for mentors, mentees and administrators. Written in plain language — no technical background needed.</p>
      </div>
      <div>
        <h5>Guides</h5>
        <a href="{{ route('guides.mentorship') }}">Mentorship SOP (start to finish)</a>
        <a href="{{ route('guides.emonc') }}">EmONC: Mentors &amp; Mentees</a>
        <a href="{{ route('manual') }}">Full User Manual</a>
      </div>
      <div>
        <h5>Go to</h5>
        <a href="{{ url('/') }}">Home</a>
        <a href="{{ url('/admin') }}">Sign in</a>
        <a href="{{ route('resources.index') }}">Knowledge Base</a>
      </div>
    </div>
    <div class="legal">&copy; {{ date('Y') }} Ministry of Health, Kenya · MNCH Programme. For help, contact your county mentor lead.</div>
  </div>
</footer>
</body>
</html>
