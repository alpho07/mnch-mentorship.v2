@php
$statusColors = [
    'active'    => ['dot' => '#16a34a', 'badge_bg' => '#dcfce7', 'badge_color' => '#15803d', 'label' => 'Active'],
    'draft'     => ['dot' => '#94a3b8', 'badge_bg' => '#f1f5f9', 'badge_color' => '#475569', 'label' => 'Draft'],
    'completed' => ['dot' => '#1d4ed8', 'badge_bg' => '#dbeafe', 'badge_color' => '#1e40af', 'label' => 'Completed'],
    'cancelled' => ['dot' => '#dc2626', 'badge_bg' => '#fef2f2', 'badge_color' => '#991b1b', 'label' => 'Cancelled'],
];
$sc = $statusColors[$enrollment['class_status']] ?? $statusColors['draft'];

$pStatusColors = [
    'enrolled'  => ['bg' => '#fffbeb', 'color' => '#92400e', 'label' => 'Enrolled'],
    'active'    => ['bg' => '#ecfdf5', 'color' => '#065f46', 'label' => 'Active'],
    'completed' => ['bg' => '#eff6ff', 'color' => '#1e40af', 'label' => 'Completed'],
];
$pc = $pStatusColors[$enrollment['p_status']] ?? $pStatusColors['enrolled'];

$progressPct   = $enrollment['progress_pct'] ?? 0;
$progressColor = $progressPct >= 80 ? '#16a34a' : ($progressPct >= 50 ? '#f59e0b' : '#1d4ed8');
$classId       = $enrollment['class_id'];
$modules       = collect($enrollment['modules'] ?? []);
$isCertified   = $enrollment['is_certified'] ?? false;
$mentorApproved = $enrollment['mentor_approved'] ?? false;
$isEmonc       = $enrollment['is_emonc'] ?? false;

// Accordion: defaultOpen passed from parent (true for first/latest class)
$isOpen = $defaultOpen ?? true;
@endphp

<div class="md-class-card" style="{{ $isCertified ? 'border-color:#fbbf24;box-shadow:0 0 0 1px #fbbf2444;' : ($highlight ? 'border-color:#bfdbfe;box-shadow:0 0 0 1px #bfdbfe44;' : '') }}">

    {{-- ── Accordion header (always visible, clickable) ───────────────────── --}}
    <div class="md-class-header md-accordion-trigger" onclick="toggleClassCard({{ $classId }})" style="cursor:pointer;user-select:none;">
        <div class="md-class-status-dot" style="background:{{ $sc['dot'] }};box-shadow:0 0 0 3px {{ $sc['dot'] }}22;"></div>

        <div class="md-class-info" style="min-width:0;">
            <div class="md-class-name" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                {{ $enrollment['class_name'] }}
                @if($isCertified)
                    <span style="display:inline-flex;align-items:center;gap:4px;font-size:10px;font-weight:700;padding:2px 8px;border-radius:100px;background:#fef3c7;color:#92400e;white-space:nowrap;">
                        🏆 Certified
                    </span>
                @elseif($mentorApproved)
                    <span style="display:inline-flex;align-items:center;gap:4px;font-size:10px;font-weight:700;padding:2px 8px;border-radius:100px;background:#ede9fe;color:#6d28d9;white-space:nowrap;">
                        ✓ Mentor Approved
                    </span>
                @endif
            </div>
            <div class="md-class-meta">
                <span>{{ $enrollment['program_name'] }}</span>
                @if($enrollment['facility_name'] && $enrollment['facility_name'] !== '—')
                    <span>· 🏥 {{ $enrollment['facility_name'] }}</span>
                @endif
                @if($enrollment['mentor_name'] !== '—')
                    <span>· 👨‍⚕️ {{ $enrollment['mentor_name'] }}</span>
                @endif
                @if($enrollment['start_date'])
                    <span>· 📅 {{ $enrollment['start_date'] }}{{ $enrollment['end_date'] ? ' – ' . $enrollment['end_date'] : '' }}</span>
                @endif
            </div>
        </div>

        <div style="display:flex;flex-direction:column;align-items:flex-end;gap:6px;flex-shrink:0;">
            <div style="display:flex;align-items:center;gap:6px;">
                <span class="md-class-badge" style="background:{{ $sc['badge_bg'] }};color:{{ $sc['badge_color'] }}">{{ $sc['label'] }}</span>
                <span id="class-toggle-icon-{{ $classId }}"
                      style="font-size:11px;color:#94a3b8;font-weight:600;transition:transform .2s;{{ $isOpen ? '' : 'transform:rotate(180deg)' }}">▲</span>
            </div>
            <span class="md-class-badge" style="background:{{ $pc['bg'] }};color:{{ $pc['color'] }}">{{ $pc['label'] }}</span>
            {{-- Mini progress summary --}}
            <span style="font-size:11px;font-weight:700;color:{{ $progressColor }};">
                {{ $enrollment['completed_modules'] }}/{{ $enrollment['total_modules'] }} · {{ $progressPct }}%
            </span>
        </div>
    </div>

    {{-- ── Collapsible body ─────────────────────────────────────────────────── --}}
    <div id="class-body-{{ $classId }}" style="{{ $isOpen ? '' : 'display:none' }}">

        {{-- Progress bar --}}
        <div class="md-progress-row">
            <div style="font-size:12px;color:#64748b;white-space:nowrap;">
                {{ $enrollment['completed_modules'] }}/{{ $enrollment['total_modules'] }} modules
            </div>
            <div class="md-progress-bar-wrap">
                <div class="md-progress-bar-fill" style="width:{{ $progressPct }}%;background:linear-gradient(90deg,{{ $progressColor }},{{ $progressColor }}cc);"></div>
            </div>
            <div class="md-progress-pct" style="color:{{ $progressColor }}">{{ $progressPct }}%</div>
            @if($enrollment['pending_attendance'] > 0)
                <span style="background:#fef2f2;color:#dc2626;font-size:10px;font-weight:700;padding:2px 8px;border-radius:100px;white-space:nowrap;">
                    🔔 {{ $enrollment['pending_attendance'] }} pending
                </span>
            @endif
            @if($enrollment['recommendations'] > 0)
                <span style="background:#ede9fe;color:#6d28d9;font-size:10px;font-weight:700;padding:2px 8px;border-radius:100px;white-space:nowrap;">
                    📋 {{ $enrollment['recommendations'] }} feedback
                </span>
            @endif
        </div>

        {{-- Certificate download banner --}}
        @if($isCertified && $enrollment['cert_url'])
            <div style="padding:0 22px 14px;">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;background:linear-gradient(135deg,#fffbeb 0%,#fef3c7 100%);border:1px solid #fcd34d;border-radius:12px;padding:12px 16px;">
                    <div style="display:flex;align-items:center;gap:10px;min-width:0;">
                        <div style="width:36px;height:36px;border-radius:10px;background:#f59e0b;color:#fff;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;">🏆</div>
                        <div>
                            <div style="font-size:12px;font-weight:700;color:#92400e;">Certificate of Completion</div>
                            <div style="font-size:11px;color:#a16207;">You've completed this program</div>
                        </div>
                    </div>
                    <a href="{{ $enrollment['cert_url'] }}"
                       style="display:inline-flex;align-items:center;gap:6px;font-size:11px;font-weight:700;padding:6px 14px;border-radius:8px;background:#f59e0b;color:#fff;text-decoration:none;white-space:nowrap;flex-shrink:0;">
                        ⬇ Download
                    </a>
                </div>
            </div>
        @elseif($mentorApproved && !$isCertified && $isEmonc)
            <div style="padding:0 22px 14px;">
                <div style="display:flex;align-items:center;gap:10px;background:#f5f3ff;border:1px solid #ddd6fe;border-radius:12px;padding:10px 16px;">
                    <span style="font-size:16px;">✅</span>
                    <div>
                        <div style="font-size:12px;font-weight:700;color:#5b21b6;">Mentor Approved</div>
                        <div style="font-size:11px;color:#7c3aed;">Awaiting Head DRMH certification</div>
                    </div>
                </div>
            </div>
        @endif

        {{-- EmONC next-step CTA --}}
        @if($isEmonc && !empty($enrollment['next_module']))
            <div style="padding:0 22px 14px;">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;background:linear-gradient(135deg,#eff6ff 0%,#eef2ff 100%);border:1px solid #bfdbfe;border-radius:12px;padding:12px 16px;">
                    <div style="min-width:0;">
                        <div style="font-size:11px;font-weight:700;color:#1d4ed8;text-transform:uppercase;letter-spacing:0.06em;">Next Step</div>
                        <div style="font-size:14px;font-weight:700;color:#1e293b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $enrollment['next_module']['name'] }}</div>
                    </div>
                    <a href="{{ route('mentee.class.module', [$enrollment['class_id'], $enrollment['next_module']['id']]) }}"
                       class="md-attend-btn" style="flex-shrink:0;">Continue →</a>
                </div>
            </div>
        @endif

        {{-- Module list --}}
        @if($modules->isNotEmpty())
            <div class="md-module-list">
                @foreach($modules as $mod)
                    @php
                        $progStatus = $mod['progress_status'];
                        $modConfig = match($progStatus) {
                            'completed'   => ['icon' => '✅', 'bg' => '#dcfce7', 'badge_bg' => '#dcfce7', 'badge_color' => '#15803d', 'label' => 'Completed'],
                            'in_progress' => ['icon' => '🔵', 'bg' => '#dbeafe', 'badge_bg' => '#dbeafe', 'badge_color' => '#1e40af', 'label' => 'In Progress'],
                            'exempted'    => ['icon' => '⭐', 'bg' => '#ede9fe', 'badge_bg' => '#ede9fe', 'badge_color' => '#6d28d9', 'label' => 'Exempted'],
                            default       => ['icon' => '⬜', 'bg' => '#f1f5f9', 'badge_bg' => '#f1f5f9', 'badge_color' => '#64748b', 'label' => 'Not Started'],
                        };

                        // Determine score to display
                        $preScore  = $mod['pre_test_score'] ?? null;
                        $postScore = $mod['post_test_score'] ?? null;
                        $assScore  = $mod['assessment_score'] ?? null;
                        $hasScore  = $preScore !== null || $postScore !== null || $assScore !== null;

                        // For display: use post-test if available (most recent/relevant), else pre-test, else assessment
                        if ($postScore !== null) {
                            $displayScore = $postScore;
                            $scoreLabel   = 'Post';
                        } elseif ($preScore !== null) {
                            $displayScore = $preScore;
                            $scoreLabel   = 'Pre';
                        } elseif ($assScore !== null) {
                            $displayScore = $assScore;
                            $scoreLabel   = 'Score';
                        } else {
                            $displayScore = null;
                            $scoreLabel   = '';
                        }

                        $scoreColor = ($displayScore !== null && $displayScore >= 85) ? '#16a34a'
                            : (($displayScore !== null && $displayScore >= 50) ? '#d97706'
                            : '#dc2626');
                    @endphp

                    <div class="md-module-row">
                        <div class="md-module-icon" style="background:{{ $modConfig['bg'] }}">{{ $modConfig['icon'] }}</div>

                        <div class="md-module-name" style="flex:1;min-width:0;">
                            @if(in_array($mod['progress_status'], ['in_progress', 'completed', 'exempted']))
                            <a href="{{ route('mentee.class.module', [$enrollment['class_id'], $mod['id']]) }}"
                               style="color:inherit;text-decoration:none;">
                                {{ $mod['name'] }}
                            </a>
                            @else
                            <span style="color:{{ $mod['module_status'] === 'not_started' ? '#94a3b8' : '#d97706' }};">
                                @if($mod['module_status'] === 'not_started') 🔒 @else 🎯 @endif
                                {{ $mod['name'] }}
                            </span>
                            @endif
                            @if($mod['completed_at'])
                                <div class="md-module-desc" style="color:#16a34a;">✓ {{ $mod['completed_at'] }}</div>
                            @elseif($mod['started_at'])
                                <div class="md-module-desc">Started {{ $mod['started_at'] }}</div>
                            @endif
                            @if($mod['prev_class'])
                                <div class="md-module-desc" style="color:#7c3aed;">Previously completed in another class</div>
                            @endif
                        </div>

                        {{-- Score pill --}}
                        @if($displayScore !== null)
                            <div style="display:flex;flex-direction:column;align-items:center;flex-shrink:0;margin-right:4px;">
                                <span style="font-size:13px;font-weight:800;color:{{ $scoreColor }};line-height:1;">{{ $displayScore }}%</span>
                                <span style="font-size:9px;color:#94a3b8;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;">{{ $scoreLabel }}</span>
                                @if($preScore !== null && $postScore !== null)
                                    <span style="font-size:9px;color:#64748b;margin-top:1px;">{{ $preScore }}↗{{ $postScore }}</span>
                                @endif
                            </div>
                        @endif

                        {{-- Status / CTA --}}
                        @if($mod['attendance_open'] && !$mod['confirmed'])
                            <a href="{{ route('module.attend', ['token' => $mod['attendance_token']]) }}"
                               class="md-attend-btn" target="_blank">✓ Attend</a>
                        @elseif($mod['confirmed'] && $progStatus !== 'completed')
                            <span class="md-module-status" style="background:#dcfce7;color:#15803d;">Confirmed</span>
                        @else
                            <span class="md-module-status" style="background:{{ $modConfig['badge_bg'] }};color:{{ $modConfig['badge_color'] }}">
                                {{ $modConfig['label'] }}
                            </span>
                        @endif

                        @if(!empty($mod['recommendation']))
                            <span title="{{ $mod['recommendation'] }}"
                                  style="cursor:help;background:#ede9fe;color:#6d28d9;font-size:16px;padding:0 4px;border-radius:6px;flex-shrink:0;">📋</span>
                        @endif
                    </div>
                @endforeach
            </div>

            {{-- View class link --}}
            <div style="padding:10px 22px 16px;">
                <a href="{{ route('mentee.class.progress', $enrollment['class_id']) }}"
                   style="display:inline-flex;align-items:center;gap:5px;font-size:12px;font-weight:600;color:#1d4ed8;text-decoration:none;">
                    View full class →
                </a>
            </div>
        @else
            <div style="padding:14px 22px;font-size:13px;color:#94a3b8;font-style:italic;">
                No modules have been added to this class yet.
            </div>
        @endif

    </div>{{-- /class-body --}}

</div>
