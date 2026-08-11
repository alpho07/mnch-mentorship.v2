<x-filament-panels::page>
    <div class="adash">
        {{-- Assessment Summary --}}
        {{ $this->getInfolist('assessment_summary') }}

        {{-- Progress Overview Stats --}}
        <div class="adash-stats">
            <div class="adash-stat adash-stat-blue">
                <div class="adash-stat-icon">
                    @svg('heroicon-o-document-text', 'adash-stat-icon-svg')
                </div>
                <div class="adash-stat-body">
                    <p class="adash-stat-label">Total Sections</p>
                    <p class="adash-stat-val">{{ $progressStats['total'] }}</p>
                </div>
            </div>

            <div class="adash-stat adash-stat-green">
                <div class="adash-stat-icon">
                    @svg('heroicon-o-check-circle', 'adash-stat-icon-svg')
                </div>
                <div class="adash-stat-body">
                    <p class="adash-stat-label">Completed</p>
                    <p class="adash-stat-val">{{ $progressStats['completed'] }}</p>
                </div>
            </div>

            <div class="adash-stat adash-stat-purple">
                <div class="adash-stat-icon">
                    @svg('heroicon-o-chart-bar', 'adash-stat-icon-svg')
                </div>
                <div class="adash-stat-body">
                    <p class="adash-stat-label">Progress</p>
                    <p class="adash-stat-val">{{ $progressStats['percentage'] }}%</p>
                    <div class="adash-progress-track">
                        <div class="adash-progress-fill" style="width: {{ $progressStats['percentage'] }}%"></div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Team --}}
        <div class="adash-card">
            <div class="adash-card-header">
                <h3 class="adash-card-title">
                    @svg('heroicon-o-user-group', 'adash-card-title-icon')
                    Team
                </h3>
                <span class="adash-card-count">{{ count($team) }} member(s)</span>
            </div>

            <div class="adash-team-grid">
                @foreach ($team as $member)
                    @php $isLead = $member->pivot->role === 'team_lead'; @endphp
                    <div class="adash-team-member">
                        <div class="adash-team-avatar {{ $isLead ? 'is-lead' : 'is-member' }}">
                            {{ strtoupper(mb_substr($member->name, 0, 1)) }}
                        </div>
                        <div class="adash-team-info">
                            <p class="adash-team-name">{{ $member->name }}</p>
                            <p class="adash-team-email">{{ $member->email }}</p>
                        </div>
                        <span class="adash-team-badge {{ $isLead ? 'is-lead' : 'is-member' }}">
                            {{ $isLead ? 'Lead' : 'Member' }}
                        </span>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Filters Section --}}
        <div class="adash-card">
            <div class="adash-card-header">
                <h3 class="adash-card-title">
                    @svg('heroicon-o-funnel', 'adash-card-title-icon')
                    Assessment Sections
                </h3>
                <span class="adash-card-count">{{ count($sections) }} section(s) shown</span>
            </div>

            <form wire:submit.prevent class="adash-filters">
                <div class="adash-filters-grid">
                    <div class="adash-field">
                        <label class="adash-field-label">Search Sections</label>
                        <div class="adash-input-wrap">
                            @svg('heroicon-o-magnifying-glass', 'adash-input-icon')
                            <input
                                type="text"
                                wire:model.live.debounce.300ms="searchTerm"
                                placeholder="Type to search..."
                                class="adash-input"
                            />
                        </div>
                    </div>

                    <div class="adash-field">
                        <label class="adash-field-label">Status</label>
                        <select wire:model.live="statusFilter" class="adash-select">
                            <option value="all">All Sections</option>
                            <option value="completed">Completed Only</option>
                            <option value="incomplete">Incomplete Only</option>
                        </select>
                    </div>

                    <div class="adash-field adash-field-btn">
                        <button
                            type="button"
                            wire:click="$set('searchTerm', null); $set('statusFilter', 'all')"
                            class="adash-btn-clear"
                        >
                            @svg('heroicon-o-x-mark', 'adash-btn-clear-icon')
                            Clear Filters
                        </button>
                    </div>
                </div>
            </form>
        </div>

        {{-- Section Cards Grid --}}
        @if(count($sections) > 0)
            <div class="adash-sections-grid">
                @foreach ($sections as $section)
                    <div class="adash-section-card {{ $section['done'] ? 'is-done' : 'is-pending' }}">
                        <div class="adash-section-badge {{ $section['done'] ? 'is-done' : 'is-pending' }}">
                            @if($section['done'])
                                @svg('heroicon-s-check-circle', 'adash-section-badge-icon')
                                Done
                            @else
                                @svg('heroicon-s-exclamation-circle', 'adash-section-badge-icon')
                                Pending
                            @endif
                        </div>

                        <div class="adash-section-icon {{ $section['done'] ? 'is-done' : 'is-pending' }}">
                            @svg($section['icon'] ?? 'heroicon-o-document', 'adash-section-icon-svg')
                        </div>

                        <h3 class="adash-section-title">{{ $section['label'] }}</h3>

                        @if ($section['route'])
                            @if($section['done'])
                                <a href="{{ $section['route'] }}" class="adash-section-cta is-done">
                                    @svg('heroicon-o-arrow-path', 'adash-section-cta-icon')
                                    Review Section
                                </a>
                            @else
                                <a href="{{ $section['route'] }}" class="adash-section-cta is-pending">
                                    @svg('heroicon-o-arrow-right', 'adash-section-cta-icon')
                                    Start Section
                                </a>
                            @endif
                        @else
                            @if($section['done'])
                                <div class="adash-section-cta is-static-done">
                                    @svg('heroicon-s-check-circle', 'adash-section-cta-icon')
                                    Completed
                                </div>
                            @else
                                <div class="adash-section-cta is-static-disabled">
                                    Not Available
                                </div>
                            @endif
                        @endif
                    </div>
                @endforeach
            </div>
        @else
            {{-- Empty State --}}
            <div class="adash-empty">
                <div class="adash-empty-icon">
                    @svg('heroicon-o-funnel', 'adash-empty-icon-svg')
                </div>
                <h3 class="adash-empty-title">No sections found</h3>
                <p class="adash-empty-desc">Try adjusting your filters to see more results</p>
                <button
                    wire:click="$set('searchTerm', null); $set('statusFilter', 'all')"
                    class="adash-empty-btn"
                >
                    Clear All Filters
                </button>
            </div>
        @endif
    </div>

    <style>
        /* ── Layout ──────────────────────────────────────────────────────── */
        .adash{display:flex;flex-direction:column;gap:1.5rem}

        /* ── Stat cards ──────────────────────────────────────────────────── */
        .adash-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:1rem}
        @media(max-width:900px){.adash-stats{grid-template-columns:1fr}}

        .adash-stat{display:flex;align-items:flex-start;gap:1rem;padding:1.35rem 1.5rem;border-radius:16px;border:1px solid transparent;box-shadow:0 1px 3px rgba(15,23,42,.06)}
        .adash-stat-blue{background:linear-gradient(150deg,#eff6ff 0%,#dbeafe 100%);border-color:#bfdbfe}
        .adash-stat-green{background:linear-gradient(150deg,#f0fdf4 0%,#dcfce7 100%);border-color:#bbf7d0}
        .adash-stat-purple{background:linear-gradient(150deg,#faf5ff 0%,#ede9fe 100%);border-color:#ddd6fe}

        .adash-stat-icon{flex-shrink:0;width:52px;height:52px;border-radius:14px;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 12px rgba(15,23,42,.10)}
        .adash-stat-blue .adash-stat-icon{background:linear-gradient(135deg,#60a5fa,#2563eb)}
        .adash-stat-green .adash-stat-icon{background:linear-gradient(135deg,#4ade80,#16a34a)}
        .adash-stat-purple .adash-stat-icon{background:linear-gradient(135deg,#c084fc,#7c3aed)}
        .adash-stat-icon-svg{width:26px;height:26px;stroke:#fff;fill:none;stroke-width:1.75;stroke-linecap:round;stroke-linejoin:round}

        .adash-stat-body{flex:1;min-width:0}
        .adash-stat-label{font-size:.8rem;font-weight:600;margin:0 0 .2rem}
        .adash-stat-blue .adash-stat-label{color:#2563eb}
        .adash-stat-green .adash-stat-label{color:#16a34a}
        .adash-stat-purple .adash-stat-label{color:#7c3aed}
        .adash-stat-val{font-size:2.1rem;font-weight:800;letter-spacing:-.03em;line-height:1.1;margin:0}
        .adash-stat-blue .adash-stat-val{color:#1e3a8a}
        .adash-stat-green .adash-stat-val{color:#14532d}
        .adash-stat-purple .adash-stat-val{color:#4c1d95}

        .adash-progress-track{margin-top:.6rem;height:6px;border-radius:99px;background:rgba(124,58,237,.15);overflow:hidden}
        .adash-progress-fill{height:100%;border-radius:99px;background:linear-gradient(90deg,#c084fc,#7c3aed);transition:width .4s ease}

        /* ── Cards / filters ─────────────────────────────────────────────── */
        .adash-card{background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:1.5rem;box-shadow:0 1px 3px rgba(15,23,42,.05)}
        .adash-card-header{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem;margin-bottom:1.25rem}
        .adash-card-title{display:flex;align-items:center;gap:.5rem;font-size:1.05rem;font-weight:700;color:#111827;margin:0}
        .adash-card-title-icon{width:19px;height:19px;stroke:#6b7280;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
        .adash-card-count{font-size:.82rem;color:#6b7280;font-weight:500}

        .adash-filters-grid{display:grid;grid-template-columns:1fr 1fr auto;gap:1rem;align-items:end}
        @media(max-width:800px){.adash-filters-grid{grid-template-columns:1fr}}

        .adash-field{display:flex;flex-direction:column;min-width:0}
        .adash-field-label{font-size:.8rem;font-weight:600;color:#374151;margin-bottom:.4rem}
        .adash-field-btn{justify-content:flex-end}
        @media(max-width:800px){.adash-field-btn{justify-content:stretch}}

        .adash-input-wrap{position:relative;display:flex;align-items:center}
        .adash-input-icon{position:absolute;left:.85rem;width:16px;height:16px;stroke:#9ca3af;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;pointer-events:none}
        .adash-input{width:100%;padding:.65rem .9rem .65rem 2.4rem;border:1px solid #d1d5db;border-radius:10px;font-size:.88rem;color:#111827;transition:border-color .15s,box-shadow .15s}
        .adash-input:focus{outline:none;border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.14)}

        .adash-select{width:100%;padding:.65rem .9rem;border:1px solid #d1d5db;border-radius:10px;font-size:.88rem;color:#111827;background-color:#fff;transition:border-color .15s,box-shadow .15s}
        .adash-select:focus{outline:none;border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.14)}

        .adash-btn-clear{display:flex;align-items:center;justify-content:center;gap:.4rem;width:100%;padding:.65rem 1rem;background:#f3f4f6;color:#374151;border:1px solid #e5e7eb;border-radius:10px;font-size:.85rem;font-weight:600;cursor:pointer;transition:background .15s}
        .adash-btn-clear:hover{background:#e5e7eb}
        .adash-btn-clear-icon{width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:2.25;stroke-linecap:round;stroke-linejoin:round}

        /* ── Team ────────────────────────────────────────────────────────── */
        .adash-team-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:.85rem}

        .adash-team-member{display:flex;align-items:center;gap:.85rem;padding:.85rem 1rem;border:1.5px solid #e5e7eb;border-radius:14px;background:#fafafa}

        .adash-team-avatar{flex-shrink:0;width:42px;height:42px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.95rem;font-weight:800;color:#fff}
        .adash-team-avatar.is-lead{background:linear-gradient(135deg,#c084fc,#7c3aed)}
        .adash-team-avatar.is-member{background:linear-gradient(135deg,#60a5fa,#2563eb)}

        .adash-team-info{flex:1;min-width:0}
        .adash-team-name{font-size:.88rem;font-weight:700;color:#111827;margin:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .adash-team-email{font-size:.76rem;color:#6b7280;margin:.1rem 0 0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}

        .adash-team-badge{flex-shrink:0;padding:.25rem .65rem;border-radius:99px;font-size:.68rem;font-weight:700;letter-spacing:.02em}
        .adash-team-badge.is-lead{background:#ede9fe;color:#6d28d9;border:1px solid #ddd6fe}
        .adash-team-badge.is-member{background:#dbeafe;color:#1d4ed8;border:1px solid #bfdbfe}

        /* ── Section cards grid ──────────────────────────────────────────── */
        .adash-sections-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:1.25rem}
        @media(max-width:1200px){.adash-sections-grid{grid-template-columns:repeat(3,1fr)}}
        @media(max-width:860px){.adash-sections-grid{grid-template-columns:repeat(2,1fr)}}
        @media(max-width:560px){.adash-sections-grid{grid-template-columns:1fr}}

        .adash-section-card{position:relative;display:flex;flex-direction:column;align-items:center;background:#fff;border:1.5px solid #e5e7eb;border-radius:18px;padding:1.75rem 1.35rem 1.5rem;text-align:center;box-shadow:0 1px 3px rgba(15,23,42,.05);transition:box-shadow .2s,border-color .2s,transform .2s}
        .adash-section-card:hover{box-shadow:0 12px 28px rgba(15,23,42,.10);border-color:#93c5fd;transform:translateY(-3px)}
        .adash-section-card.is-done{border-color:#bbf7d0}

        .adash-section-badge{position:absolute;top:.85rem;right:.85rem;display:flex;align-items:center;gap:.3rem;padding:.3rem .65rem;border-radius:99px;font-size:.68rem;font-weight:700;letter-spacing:.02em}
        .adash-section-badge.is-done{background:#dcfce7;color:#15803d;border:1px solid #bbf7d0}
        .adash-section-badge.is-pending{background:#fef3c7;color:#b45309;border:1px solid #fde68a}
        .adash-section-badge-icon{width:13px;height:13px;flex-shrink:0}

        .adash-section-icon{width:64px;height:64px;border-radius:18px;display:flex;align-items:center;justify-content:center;margin-bottom:1rem;box-shadow:0 6px 16px rgba(15,23,42,.14);transition:transform .25s}
        .adash-section-card:hover .adash-section-icon{transform:scale(1.07)}
        .adash-section-icon.is-done{background:linear-gradient(135deg,#4ade80,#16a34a)}
        .adash-section-icon.is-pending{background:linear-gradient(135deg,#60a5fa,#2563eb)}
        .adash-section-icon-svg{width:30px;height:30px;stroke:#fff;fill:none;stroke-width:1.6;stroke-linecap:round;stroke-linejoin:round}

        .adash-section-title{font-size:.95rem;font-weight:700;color:#111827;margin:0 0 1.1rem;min-height:2.5rem;display:flex;align-items:center;justify-content:center;line-height:1.3}

        .adash-section-cta{display:flex;align-items:center;justify-content:center;gap:.4rem;width:100%;padding:.7rem 1rem;border-radius:11px;font-size:.85rem;font-weight:700;text-decoration:none;transition:transform .15s,box-shadow .15s;box-sizing:border-box}
        .adash-section-cta-icon{width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:2.25;stroke-linecap:round;stroke-linejoin:round;flex-shrink:0}
        .adash-section-cta.is-pending{background:linear-gradient(135deg,#3b82f6,#2563eb);color:#fff;box-shadow:0 4px 12px rgba(37,99,235,.28)}
        .adash-section-cta.is-pending:hover{transform:translateY(-1px);box-shadow:0 8px 20px rgba(37,99,235,.36)}
        .adash-section-cta.is-done{background:linear-gradient(135deg,#22c55e,#16a34a);color:#fff;box-shadow:0 4px 12px rgba(22,163,74,.28)}
        .adash-section-cta.is-done:hover{transform:translateY(-1px);box-shadow:0 8px 20px rgba(22,163,74,.36)}
        .adash-section-cta.is-static-done{background:#16a34a;color:#fff}
        .adash-section-cta.is-static-disabled{background:#f9fafb;color:#9ca3af;border:1.5px dashed #d1d5db;font-weight:600}

        /* ── Empty state ──────────────────────────────────────────────────── */
        .adash-empty{background:#fff;border:2px dashed #d1d5db;border-radius:18px;padding:3.5rem 2rem;text-align:center}
        .adash-empty-icon{width:64px;height:64px;margin:0 auto 1rem;border-radius:50%;background:#f3f4f6;display:flex;align-items:center;justify-content:center}
        .adash-empty-icon-svg{width:30px;height:30px;stroke:#9ca3af;fill:none;stroke-width:1.75;stroke-linecap:round;stroke-linejoin:round}
        .adash-empty-title{font-size:1.1rem;font-weight:700;color:#111827;margin:0 0 .4rem}
        .adash-empty-desc{font-size:.88rem;color:#6b7280;margin:0 0 1.25rem}
        .adash-empty-btn{padding:.65rem 1.5rem;background:#2563eb;color:#fff;border:none;border-radius:10px;font-size:.85rem;font-weight:700;cursor:pointer;transition:background .15s}
        .adash-empty-btn:hover{background:#1d4ed8}
    </style>
</x-filament-panels::page>
