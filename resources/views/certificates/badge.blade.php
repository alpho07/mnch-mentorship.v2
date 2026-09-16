@php
    $name = $participant->user->name ?? 'Mentee';
    $program = $class->training->program?->name ?? 'MNCH Mentorship';
    $completed = $participant->head_drmh_approved_at?->format('M d, Y') ?? now()->format('M d, Y');
@endphp
<svg xmlns="http://www.w3.org/2000/svg" width="400" height="220" viewBox="0 0 400 220" role="img" aria-label="Digital badge for {{ $name }}, {{ $program }}, completed {{ $completed }}">
  <defs>
    <linearGradient id="bg" x1="0" y1="0" x2="400" y2="220" gradientUnits="userSpaceOnUse">
      <stop offset="0%" stop-color="#4f46e5"/>
      <stop offset="100%" stop-color="#0ea5e9"/>
    </linearGradient>
    <filter id="shadow" x="-20%" y="-20%" width="140%" height="140%">
      <feDropShadow dx="0" dy="4" stdDeviation="4" flood-color="#000000" flood-opacity="0.25"/>
    </filter>
  </defs>
  <rect width="400" height="220" rx="16" fill="url(#bg)" filter="url(#shadow)"/>
  <circle cx="60" cy="110" r="36" fill="rgba(255,255,255,0.15)"/>
  <g transform="translate(36,86)" fill="none" stroke="#ffffff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
    <path d="M5 3v4M3 5h4M6 37v4m-2-2h4m5-36l2.286 6.857L21 16l-5.714 2.143L13 25l-2.286-6.857L5 16l5.714-2.143L13 3z"/>
  </g>
  <text x="108" y="95" fill="#ffffff" font-family="'Plus Jakarta Sans', sans-serif" font-size="14" font-weight="600" opacity="0.9">CERTIFIED</text>
  <text x="108" y="125" fill="#ffffff" font-family="'Plus Jakarta Sans', sans-serif" font-size="20" font-weight="800">{{ $name }}</text>
  <text x="108" y="150" fill="#e0f2fe" font-family="'Plus Jakarta Sans', sans-serif" font-size="13" font-weight="500">{{ $program }}</text>
  <text x="108" y="172" fill="#bae6fd" font-family="'Plus Jakarta Sans', sans-serif" font-size="12" font-weight="500">Completed {{ $completed }}</text>
  <text x="380" y="200" text-anchor="end" fill="rgba(255,255,255,0.6)" font-family="'Plus Jakarta Sans', sans-serif" font-size="10" font-weight="600">MNCH MENTORSHIP</text>
</svg>
