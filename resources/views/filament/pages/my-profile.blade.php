<x-filament-panels::page>
@php
$user   = auth()->user();
$roles  = $user->getRoleNames();
$roleLabel = $roles->map(fn($r) => ucwords(str_replace(['_', '::'], [' ', ' '], $r)))->implode(', ');
$facility  = $user->facility?->name;
$subcounty = $user->facility?->subcounty?->name ?? $user->subcounties->first()?->name;
$county    = $user->facility?->subcounty?->county?->name ?? $user->counties->first()?->name;
$dept      = $user->department?->name;
$cadre     = $user->cadre?->name;
$initials  = strtoupper(substr($user->first_name ?? 'U', 0, 1) . substr($user->last_name ?? '', 0, 1));
$statusColors = ['active'=>'#16a34a','inactive'=>'#6b7280','suspended'=>'#dc2626','trainee'=>'#d97706'];
$statusColor  = $statusColors[$user->status] ?? '#6b7280';
@endphp

<style>
.mp-wrap{max-width:860px;margin:0 auto;font-family:'Inter',system-ui,sans-serif;}
.mp-hero{background:linear-gradient(135deg,#0f172a 0%,#1e3a8a 45%,#2563eb 100%);border-radius:20px;padding:32px;display:flex;align-items:center;gap:28px;margin-bottom:24px;box-shadow:0 8px 32px rgba(15,23,42,.35);}
.mp-avatar{width:90px;height:90px;border-radius:50%;background:rgba(255,255,255,.18);display:flex;align-items:center;justify-content:center;font-size:32px;font-weight:700;color:#fff;border:3px solid rgba(255,255,255,.35);flex-shrink:0;}
.mp-hero-name{font-size:22px;font-weight:700;color:#fff;margin-bottom:4px;}
.mp-hero-role{font-size:13px;color:rgba(255,255,255,.75);margin-bottom:10px;}
.mp-hero-badges{display:flex;flex-wrap:wrap;gap:6px;}
.mp-badge{padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;letter-spacing:.3px;}
.mp-card{background:#fff;border:1px solid #e5e7eb;border-radius:16px;overflow:hidden;margin-bottom:20px;}
.dark .mp-card{background:#1e293b;border-color:#334155;}
.mp-tabs{display:flex;border-bottom:1px solid #e5e7eb;background:#f8fafc;}
.dark .mp-tabs{border-color:#334155;background:#0f172a;}
.mp-tab{padding:14px 22px;font-size:13px;font-weight:600;cursor:pointer;border-bottom:2px solid transparent;color:#6b7280;transition:all .15s;white-space:nowrap;}
.mp-tab.active{color:#2563eb;border-bottom-color:#2563eb;background:#fff;}
.dark .mp-tab.active{background:#1e293b;color:#60a5fa;border-bottom-color:#60a5fa;}
.mp-tab:hover:not(.active){color:#374151;background:#f1f5f9;}
.mp-body{padding:28px;}
.mp-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;}
@media(max-width:600px){.mp-grid{grid-template-columns:1fr;}}
.mp-field{display:flex;flex-direction:column;gap:4px;}
.mp-label{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:#6b7280;}
.mp-value{font-size:14px;color:#111827;font-weight:500;padding:8px 12px;background:#f8fafc;border-radius:8px;border:1px solid #e5e7eb;min-height:38px;}
.dark .mp-value{background:#0f172a;border-color:#334155;color:#f1f5f9;}
.mp-empty{color:#9ca3af;font-style:italic;}
.mp-section-title{font-size:13px;font-weight:700;color:#374151;text-transform:uppercase;letter-spacing:.5px;margin-bottom:16px;padding-bottom:8px;border-bottom:1px solid #e5e7eb;}
.dark .mp-section-title{color:#94a3b8;border-color:#334155;}
.mp-input{width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:14px;color:#111827;background:#fff;outline:none;transition:border .15s,box-shadow .15s;}
.mp-input:focus{border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.12);}
.dark .mp-input{background:#0f172a;border-color:#334155;color:#f1f5f9;}
.mp-btn{padding:10px 24px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;border:none;transition:all .15s;}
.mp-btn-primary{background:#2563eb;color:#fff;}
.mp-btn-primary:hover{background:#1d4ed8;}
.mp-btn-ghost{background:#f1f5f9;color:#374151;border:1px solid #e5e7eb;}
.mp-btn-ghost:hover{background:#e5e7eb;}
.mp-error{color:#dc2626;font-size:12px;margin-top:3px;}
.mp-divider{border:none;border-top:1px solid #e5e7eb;margin:24px 0;}
.dark .mp-divider{border-color:#334155;}
</style>

<div class="mp-wrap">

    {{-- Hero --}}
    <div class="mp-hero">
        <div class="mp-avatar">{{ $initials }}</div>
        <div style="flex:1;min-width:0;">
            <div class="mp-hero-name">{{ $user->full_name }}</div>
            <div class="mp-hero-role">{{ $user->email }}</div>
            <div class="mp-hero-badges">
                @foreach($roles as $role)
                    <span class="mp-badge" style="background:rgba(255,255,255,.18);color:#fff;">
                        {{ ucwords(str_replace(['_','::'], [' ',' '], $role)) }}
                    </span>
                @endforeach
                <span class="mp-badge" style="background:{{ $statusColor }};color:#fff;">
                    {{ ucfirst($user->status) }}
                </span>
            </div>
        </div>
    </div>

    {{-- Tabs --}}
    <div class="mp-card">
        <div class="mp-tabs">
            <div class="mp-tab {{ $activeTab === 'view' ? 'active' : '' }}"
                 wire:click="$set('activeTab','view')">👤 Profile Info</div>
            <div class="mp-tab {{ $activeTab === 'edit' ? 'active' : '' }}"
                 wire:click="$set('activeTab','edit')">✏️ Edit Profile</div>
            <div class="mp-tab {{ $activeTab === 'password' ? 'active' : '' }}"
                 wire:click="$set('activeTab','password')">🔒 Change Password</div>
        </div>

        <div class="mp-body">

            {{-- ── View Profile ──────────────────────────────────────────── --}}
            @if($activeTab === 'view')
                <div class="mp-section-title">Personal Information</div>
                <div class="mp-grid" style="margin-bottom:24px;">
                    <div class="mp-field">
                        <div class="mp-label">First Name</div>
                        <div class="mp-value">{{ $user->first_name ?: '—' }}</div>
                    </div>
                    <div class="mp-field">
                        <div class="mp-label">Middle Name</div>
                        <div class="mp-value">{{ $user->middle_name ?: '—' }}</div>
                    </div>
                    <div class="mp-field">
                        <div class="mp-label">Last Name</div>
                        <div class="mp-value">{{ $user->last_name ?: '—' }}</div>
                    </div>
                    <div class="mp-field">
                        <div class="mp-label">Email Address</div>
                        <div class="mp-value">{{ $user->email }}</div>
                    </div>
                    <div class="mp-field">
                        <div class="mp-label">Phone Number</div>
                        <div class="mp-value">{{ $user->phone ?: '—' }}</div>
                    </div>
                    <div class="mp-field">
                        <div class="mp-label">ID Number</div>
                        <div class="mp-value">{{ $user->id_number ?: '—' }}</div>
                    </div>
                </div>

                <hr class="mp-divider">
                <div class="mp-section-title">Role & Access</div>
                <div class="mp-grid" style="margin-bottom:24px;">
                    <div class="mp-field">
                        <div class="mp-label">System Role</div>
                        <div class="mp-value">{{ $roleLabel ?: '—' }}</div>
                    </div>
                    <div class="mp-field">
                        <div class="mp-label">Account Status</div>
                        <div class="mp-value">
                            <span style="color:{{ $statusColor }};font-weight:600;">{{ ucfirst($user->status) }}</span>
                        </div>
                    </div>
                    @if($dept)
                    <div class="mp-field">
                        <div class="mp-label">Department</div>
                        <div class="mp-value">{{ $dept }}</div>
                    </div>
                    @endif
                    @if($cadre)
                    <div class="mp-field">
                        <div class="mp-label">Cadre</div>
                        <div class="mp-value">{{ $cadre }}</div>
                    </div>
                    @endif
                </div>

                <hr class="mp-divider">
                <div class="mp-section-title">Geographic Assignment</div>
                <div class="mp-grid">
                    <div class="mp-field">
                        <div class="mp-label">Facility</div>
                        <div class="mp-value">{{ $facility ?: '—' }}</div>
                    </div>
                    <div class="mp-field">
                        <div class="mp-label">Subcounty</div>
                        <div class="mp-value">{{ $subcounty ?: '—' }}</div>
                    </div>
                    <div class="mp-field">
                        <div class="mp-label">County</div>
                        <div class="mp-value">{{ $county ?: '—' }}</div>
                    </div>
                </div>

                <div style="margin-top:24px;">
                    <button class="mp-btn mp-btn-primary" wire:click="$set('activeTab','edit')">
                        ✏️ Edit Profile
                    </button>
                </div>
            @endif

            {{-- ── Edit Profile ───────────────────────────────────────────── --}}
            @if($activeTab === 'edit')
                <div class="mp-section-title">Edit Personal Information</div>
                <form wire:submit.prevent="updateProfile">
                    <div class="mp-grid" style="margin-bottom:20px;">
                        <div class="mp-field">
                            <label class="mp-label">First Name *</label>
                            <input class="mp-input" wire:model="first_name" type="text" placeholder="First name">
                            @error('first_name') <div class="mp-error">{{ $message }}</div> @enderror
                        </div>
                        <div class="mp-field">
                            <label class="mp-label">Middle Name</label>
                            <input class="mp-input" wire:model="middle_name" type="text" placeholder="Middle name (optional)">
                        </div>
                        <div class="mp-field">
                            <label class="mp-label">Last Name *</label>
                            <input class="mp-input" wire:model="last_name" type="text" placeholder="Last name">
                            @error('last_name') <div class="mp-error">{{ $message }}</div> @enderror
                        </div>
                        <div class="mp-field">
                            <label class="mp-label">Email Address *</label>
                            <input class="mp-input" wire:model="email" type="email" placeholder="Email address">
                            @error('email') <div class="mp-error">{{ $message }}</div> @enderror
                        </div>
                        <div class="mp-field">
                            <label class="mp-label">Phone Number</label>
                            <input class="mp-input" wire:model="phone" type="text" placeholder="e.g. +254712345678">
                            @error('phone') <div class="mp-error">{{ $message }}</div> @enderror
                        </div>
                        <div class="mp-field">
                            <label class="mp-label">ID Number</label>
                            <input class="mp-input" wire:model="id_number" type="text" placeholder="National ID number">
                            @error('id_number') <div class="mp-error">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div style="display:flex;gap:12px;">
                        <button type="submit" class="mp-btn mp-btn-primary" wire:loading.attr="disabled">
                            <span wire:loading.remove>Save Changes</span>
                            <span wire:loading>Saving…</span>
                        </button>
                        <button type="button" class="mp-btn mp-btn-ghost" wire:click="$set('activeTab','view')">
                            Cancel
                        </button>
                    </div>
                </form>
            @endif

            {{-- ── Change Password ─────────────────────────────────────────── --}}
            @if($activeTab === 'password')
                <div class="mp-section-title">Change Password</div>
                <form wire:submit.prevent="updatePassword" style="max-width:440px;">
                    <div style="display:flex;flex-direction:column;gap:16px;margin-bottom:24px;">
                        <div class="mp-field">
                            <label class="mp-label">Current Password *</label>
                            <input class="mp-input" wire:model="current_password" type="password" placeholder="Enter current password">
                            @error('current_password') <div class="mp-error">{{ $message }}</div> @enderror
                        </div>
                        <div class="mp-field">
                            <label class="mp-label">New Password *</label>
                            <input class="mp-input" wire:model="new_password" type="password" placeholder="Min 8 chars, mixed case + numbers">
                            @error('new_password') <div class="mp-error">{{ $message }}</div> @enderror
                        </div>
                        <div class="mp-field">
                            <label class="mp-label">Confirm New Password *</label>
                            <input class="mp-input" wire:model="new_password_confirmation" type="password" placeholder="Repeat new password">
                            @error('new_password_confirmation') <div class="mp-error">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <button type="submit" class="mp-btn mp-btn-primary" wire:loading.attr="disabled">
                        <span wire:loading.remove>🔒 Update Password</span>
                        <span wire:loading>Updating…</span>
                    </button>
                </form>
            @endif

        </div>
    </div>

</div>
</x-filament-panels::page>
