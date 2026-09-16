{{-- resources/views/analytics/training-explorer.blade.php --}}
@extends('layouts.app')

@section('title', 'Training Explorer')

@push('styles')
  <!-- Lightweight page-specific polish (Tailwind-friendly; graceful fallback without it) -->
  <style>
    :root{ --ring: rgba(79,70,229,.18); }
    @media (prefers-reduced-motion: reduce){ *{animation:none!important;transition:none!important} }

    /* Micro entrances */
    .fade-in{opacity:0;transform:translateY(8px);animation:fadeIn .45s cubic-bezier(.2,.6,.2,1) .02s forwards}
    .fade-in-2{opacity:0;transform:translateY(8px);animation:fadeIn .5s cubic-bezier(.2,.6,.2,1) .08s forwards}
    .fade-in-3{opacity:0;transform:translateY(8px);animation:fadeIn .55s cubic-bezier(.2,.6,.2,1) .12s forwards}
    @keyframes fadeIn{to{opacity:1;transform:none}}

    /* Hover lift */
    .lift{transition:transform .22s ease, box-shadow .22s ease}
    .lift:hover{transform:translateY(-2px)}

    /* Thin scrollbars for lists */
    .thin-scroll{ scrollbar-width:thin; scrollbar-color:#c7d2fe transparent; }
    .thin-scroll::-webkit-scrollbar{height:6px;width:6px}
    .thin-scroll::-webkit-scrollbar-thumb{background:#c7d2fe;border-radius:999px}
    .thin-scroll::-webkit-scrollbar-track{background:transparent}

    /* Focus ring for custom elements */
    .ring-brand:focus{outline:none;box-shadow:0 0 0 4px var(--ring)}

    /* Breadcrumb / drill path */
    .drill-path{display:flex;flex-wrap:wrap;align-items:center;gap:.4rem;margin-top:.5rem}
    .drill-step{
      display:inline-flex;align-items:center;gap:.4rem;
      padding:.28rem .6rem;border-radius:999px;
      background:#eef2ff;color:#3730a3;border:1px solid #e0e7ff;
      font:700 .72rem/1 ui-sans-serif,system-ui;
      transition:transform .15s ease, box-shadow .15s ease, background .15s ease, color .15s ease;
      max-width:100%;
    }
    .drill-step .label{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:32ch}
    @media (min-width:640px){ .drill-step .label{max-width:46ch} }
    .drill-step.active{
      background:#4f46e5;color:#fff;border-color:transparent;
      box-shadow:0 6px 16px rgba(79,70,229,.35); transform:translateY(-1px);
    }
    .drill-sep{color:#9ca3af}
  </style>
@endpush

@section('content')
<div
  x-data="trainingExplorer()"
  x-init="init()"
  class="min-h-screen bg-gradient-to-br from-gray-50 to-gray-100/80 py-8"
>
  <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8">

    {{-- Header with breadcrumb --}}
    <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4 mb-3 fade-in">
      <div>
        <div class="text-xs font-semibold tracking-wider uppercase text-indigo-600">MOH Analytics</div>
        <h1 class="text-3xl md:text-4xl font-extrabold text-gray-900 tracking-tight">Training Explorer</h1>
        <p class="text-gray-600 mt-1">Drill down from county to individual profiles with AI-style insights.</p>

        {{-- Breadcrumb / drill path --}}
        <div class="drill-path" x-show="true">
          <span class="drill-step" :class="{ 'active': currentLevel==='training' || currentLevel==='facility' || currentLevel==='participant' }" title="Training selection">
            <span>Training</span>
            <template x-if="selected.training">
              <span class="label"><span class="hidden sm:inline">· </span><span x-text="selected.training.name"></span></span>
            </template>
          </span>
          <span class="drill-sep">→</span>
          <span class="drill-step" :class="{ 'active': currentLevel==='facility' || currentLevel==='participant' }" title="Facility selection">
            <span>Facility</span>
            <template x-if="selected.facility">
              <span class="label"><span class="hidden sm:inline">· </span><span x-text="selected.facility.name"></span></span>
            </template>
          </span>
          <span class="drill-sep">→</span>
          <span class="drill-step" :class="{ 'active': currentLevel==='participant' }" title="Participant selection">
            <span>Participant</span>
            <template x-if="selected.participant">
              <span class="label"><span class="hidden sm:inline">· </span><span x-text="selected.participant.name"></span></span>
            </template>
          </span>
        </div>
      </div>

      <a href="{{ route('analytics.heatmap') }}"
         class="inline-flex items-center justify-center px-3 py-2 rounded-lg text-sm font-medium text-gray-700 bg-white/80 border border-gray-200 hover:bg-gray-100 lift transition">
        <span class="mr-1">←</span> Back to Heatmap
      </a>
    </div>

    {{-- Filters --}}
    <div class="bg-white/80 backdrop-blur border border-gray-200 rounded-2xl p-4 mb-6 shadow-sm fade-in-2">
      <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-7 gap-3 items-end" x-ref="toolbar">
        <div class="col-span-1">
          <label class="block text-[11px] font-semibold text-gray-600 mb-1 uppercase tracking-wide">Training Type</label>
          <select x-model="state.training_type" @change="reloadCurrent()"
                  class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 ring-brand bg-white text-sm py-2.5">
            <option value="">All</option>
            <option value="global_training">MOH Trainings</option>
            <option value="facility_mentorship">Facility Mentorship</option>
          </select>
        </div>

        <div class="col-span-1">
          <label class="block text-[11px] font-semibold text-gray-600 mb-1 uppercase tracking-wide">County</label>
          <select x-model="state.county_id" @change="loadTrainings()"
                  class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 ring-brand bg-white text-sm py-2.5">
            <option value="">All Counties</option>
            @foreach ($counties as $c)
              <option value="{{ $c->id }}" @selected($initial['county_id'] == $c->id)>{{ $c->name }}</option>
            @endforeach
          </select>
        </div>

        <div>
          <label class="block text-[11px] font-semibold text-gray-600 mb-1 uppercase tracking-wide">From</label>
          <input type="date" x-model="state.from" @change="reloadCurrent()"
                 class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 ring-brand bg-white text-sm py-2.5"/>
        </div>

        <div>
          <label class="block text-[11px] font-semibold text-gray-600 mb-1 uppercase tracking-wide">To</label>
          <input type="date" x-model="state.to" @change="reloadCurrent()"
                 class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 ring-brand bg-white text-sm py-2.5"/>
        </div>

        <div>
          <label class="block text-[11px] font-semibold text-gray-600 mb-1 uppercase tracking-wide">Completion</label>
          <select x-model="state.completion" @change="reloadCurrent()"
                  class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 ring-brand bg-white text-sm py-2.5">
            <option value="">Any</option>
            <option value="completed">Completed</option>
            <option value="in_progress">In Progress</option>
          </select>
        </div>

        <div>
          <label class="block text-[11px] font-semibold text-gray-600 mb-1 uppercase tracking-wide">Status</label>
          <select x-model="state.status" @change="reloadCurrent()"
                  class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 ring-brand bg-white text-sm py-2.5">
            <option value="">Any</option>
            <option value="passed">Passed</option>
            <option value="failed">Failed</option>
            <option value="not_assessed">Not Assessed</option>
          </select>
        </div>

        <div>
          <label class="block text-[11px] font-semibold text-gray-600 mb-1 uppercase tracking-wide">Search</label>
          <input type="search"
                 x-model.debounce.500ms="state.search"
                 @input.debounce.500ms="reloadCurrent()"
                 placeholder="name, email, training…"
                 class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 ring-brand bg-white text-sm py-2.5 px-3"/>
        </div>
      </div>
    </div>

    {{-- KPIs + Insights --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
      <template x-for="card in kpis" :key="card.label">
        <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm lift fade-in">
          <div class="text-[11px] uppercase tracking-wide text-gray-500" x-text="card.label"></div>
          <div class="text-3xl font-extrabold mt-1 text-gray-900" x-text="card.value"></div>
        </div>
      </template>

      <div class="md:col-span-2 bg-white border border-gray-200 rounded-2xl p-5 shadow-sm lift fade-in-2">
        <div class="text-[11px] uppercase tracking-wide text-gray-500 mb-1">AI Insights</div>
        <p class="text-sm text-gray-800" x-text="insights.overview"></p>
        <div class="mt-3 grid sm:grid-cols-2 gap-3">
          <div>
            <div class="text-[11px] text-gray-500 mb-1">Drivers</div>
            <ul class="list-disc list-inside text-sm text-gray-700 space-y-1">
              <template x-for="d in insights.drivers"><li x-text="d"></li></template>
            </ul>
          </div>
          <div>
            <div class="text-[11px] text-gray-500 mb-1">Actions</div>
            <ul class="list-disc list-inside text-sm text-gray-700 space-y-1">
              <template x-for="a in insights.actions"><li x-text="a"></li></template>
            </ul>
          </div>
        </div>
      </div>
    </div>

    {{-- Lists --}}
    <div class="grid grid-cols-1 md:grid-cols-12 gap-4">

      {{-- Trainings --}}
      <div class="md:col-span-4 bg-white border border-gray-200 rounded-2xl shadow-sm flex flex-col lift fade-in">
        <div class="p-4 border-b border-gray-200 flex items-center justify-between">
          <h2 class="font-semibold text-gray-900">
            Trainings
            <span class="ml-2 px-2 py-1 rounded-full bg-gray-100 text-gray-700 text-xs" x-text="trainings.length"></span>
          </h2>
        </div>

        <div class="p-2 overflow-y-auto thin-scroll" style="max-height: 28rem">
          <template x-if="loading.trainings">
            <div class="p-4 text-sm text-gray-500">Loading…</div>
          </template>

          <template x-for="t in trainings" :key="t.id">
            <button @click="selectTraining(t)"
                    :class="['w-full text-left p-4 rounded-xl border border-gray-200 bg-white mb-2 transition lift',
                             (selected.training?.id===t.id ? 'ring-2 ring-indigo-500/40 bg-indigo-50/40' : 'hover:bg-gray-50')]">
              <div class="flex items-center justify-between">
                <div class="font-semibold text-gray-900" x-text="t.name"></div>
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-50 text-indigo-700"
                      x-text="t.participants_count + ' ppl'"></span>
              </div>
              <div class="text-xs text-gray-500 mt-1" x-text="(t.start_date || '') + (t.end_date ? ' → ' + t.end_date : '')"></div>
              <div class="text-xs text-gray-600 mt-1" x-text="[(t.department||'—'), (t.module||'—')].join(' • ')"></div>
            </button>
          </template>
        </div>
      </div>

      {{-- Facilities --}}
      <div class="md:col-span-4 bg-white border border-gray-200 rounded-2xl shadow-sm flex flex-col lift fade-in-2">
        <div class="p-4 border-b border-gray-200 flex items-center justify-between">
          <h2 class="font-semibold text-gray-900">
            Facilities
            <span class="ml-2 px-2 py-1 rounded-full bg-gray-100 text-gray-700 text-xs" x-text="facilities.length"></span>
          </h2>
          <div class="text-xs text-gray-500" x-show="state.training_id">for selected training</div>
        </div>

        <div class="p-2 overflow-y-auto thin-scroll" style="max-height: 28rem">
          <template x-if="!state.training_id">
            <div class="p-4 text-sm text-gray-500">Select a training to see facilities.</div>
          </template>
          <template x-if="loading.facilities">
            <div class="p-4 text-sm text-gray-500">Loading…</div>
          </template>

          <template x-for="f in facilities" :key="f.id">
            <button @click="selectFacility(f)"
                    :class="['w-full text-left p-4 rounded-xl border border-gray-200 bg-white mb-2 transition lift',
                             (selected.facility?.id===f.id ? 'ring-2 ring-indigo-500/40 bg-indigo-50/40' : 'hover:bg-gray-50')]">
              <div class="flex items-center justify-between">
                <div class="font-semibold text-gray-900" x-text="f.name"></div>
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700"
                      x-text="f.participants_count + ' ppl'"></span>
              </div>
            </button>
          </template>
        </div>
      </div>

      {{-- Participants --}}
      <div class="md:col-span-4 bg-white border border-gray-200 rounded-2xl shadow-sm flex flex-col lift fade-in-3">
        <div class="p-4 border-b border-gray-200 flex items-center justify-between">
          <h2 class="font-semibold text-gray-900">
            Participants
            <span class="ml-2 px-2 py-1 rounded-full bg-gray-100 text-gray-700 text-xs" x-text="participants.length"></span>
          </h2>
          <div class="text-xs text-gray-500" x-show="state.facility_id">for selected facility</div>
        </div>

        <div class="p-2 overflow-y-auto thin-scroll" style="max-height: 28rem">
          <template x-if="!state.facility_id">
            <div class="p-4 text-sm text-gray-500">Select a facility to see participants.</div>
          </template>
          <template x-if="loading.participants">
            <div class="p-4 text-sm text-gray-500">Loading…</div>
          </template>

          <template x-for="p in participants" :key="p.participant_id">
            <button @click="openProfile(p.user_id)"
                    :class="['w-full text-left p-4 rounded-xl border border-gray-200 bg-white mb-2 transition lift',
                             (selected.participant?.id===p.user_id ? 'ring-2 ring-indigo-500/40 bg-indigo-50/40' : 'hover:bg-gray-50')]">
              <div class="flex items-center justify-between">
                <div>
                  <div class="font-semibold text-gray-900" x-text="p.name"></div>
                  <div class="text-xs text-gray-500" x-text="p.email || ''"></div>
                </div>
                <div class="text-right">
                  <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700"
                        x-text="p.completion || '—'"></span>
                </div>
              </div>
              <div class="text-xs text-gray-600 mt-1" x-text="p.facility || ''"></div>
            </button>
          </template>
        </div>
      </div>

    </div>
  </div>

  {{-- Profile Drawer --}}
  <div x-show="drawer.open" x-cloak class="fixed inset-0 z-40">
    <div class="absolute inset-0 bg-black/40 transition-opacity" @click="drawer.open=false"></div>

    <div
      class="absolute right-0 top-0 h-full w-full sm:w-[520px] bg-white shadow-2xl transform transition"
      x-transition:enter="transition ease-out duration-300"
      x-transition:enter-start="translate-x-full"
      x-transition:enter-end="translate-x-0"
      x-transition:leave="transition ease-in duration-200"
      x-transition:leave-start="translate-x-0"
      x-transition:leave-end="translate-x-full"
    >
      <div class="p-4 border-b flex items-center justify-between">
        <div>
          <div class="text-xs text-indigo-600 font-medium">Participant</div>
          <h3 class="text-lg font-bold text-gray-900" x-text="profile.name"></h3>
        </div>
        <button class="inline-flex items-center justify-center px-3 py-2 rounded-lg text-sm font-medium text-gray-700 bg-white border border-gray-200 hover:bg-gray-100"
                @click="drawer.open=false">Close</button>
      </div>

      <div class="p-4 space-y-5 overflow-y-auto thin-scroll" style="max-height: calc(100% - 64px)">

        {{-- Identity --}}
        <div class="border border-gray-200 rounded-2xl p-4 bg-white lift">
          <div class="grid grid-cols-2 gap-3 text-sm">
            <div>
              <div class="text-gray-500 text-[11px]">Email</div>
              <div class="font-medium text-gray-900" x-text="profile.email || '—'"></div>
            </div>
            <div>
              <div class="text-gray-500 text-[11px]">Facility</div>
              <div class="font-medium text-gray-900" x-text="profile.facility || '—'"></div>
            </div>
            <div>
              <div class="text-gray-500 text-[11px]">County</div>
              <div class="font-medium text-gray-900" x-text="profile.county || '—'"></div>
            </div>
            <div>
              <div class="text-gray-500 text-[11px]">Cadre</div>
              <div class="font-medium text-gray-900" x-text="profile.cadre || '—'"></div>
            </div>
            <div>
              <div class="text-gray-500 text-[11px]">Grade</div>
              <div class="font-medium text-gray-900" x-text="profile.grade || '—'"></div>
            </div>
            <div>
              <div class="text-gray-500 text-[11px]">Department</div>
              <div class="font-medium text-gray-900" x-text="profile.department || '—'"></div>
            </div>
            <div>
              <div class="text-gray-500 text-[11px]">Last Training</div>
              <div class="font-medium text-gray-900" x-text="profile.last_training_date || '—'"></div>
            </div>
            <div>
              <div class="text-gray-500 text-[11px]">Score</div>
              <div class="font-medium text-gray-900" x-text="profile.score + '%'"></div>
            </div>
          </div>
        </div>

        {{-- KPIs --}}
        <div class="border border-gray-200 rounded-2xl p-4 bg-white lift">
          <div class="grid grid-cols-3 gap-3">
            <template x-for="k in profileKpis" :key="k.label">
              <div class="rounded-xl border border-gray-200 p-3 text-center bg-white">
                <div class="text-[11px] text-gray-500" x-text="k.label"></div>
                <div class="text-xl font-bold text-gray-900" x-text="k.value"></div>
              </div>
            </template>
          </div>
        </div>

        {{-- AI summary --}}
        <div class="border border-gray-200 rounded-2xl p-4 bg-gradient-to-br from-indigo-50 to-emerald-50 lift">
          <div class="text-[11px] text-gray-600 mb-1">AI Insights</div>
          <div class="text-sm text-gray-800" x-text="profileAi.headline"></div>
          <ul class="list-disc list-inside text-sm mt-2 text-gray-700">
            <template x-for="r in profileAi.recommendations" :key="r"><li x-text="r"></li></template>
          </ul>
        </div>

        {{-- Attrition Actions --}}
        <div class="border border-gray-200 rounded-2xl p-4 bg-white lift">
          <div class="flex items-center justify-between mb-3">
            <div class="font-semibold text-gray-900">Attrition Actions</div>
            <div class="flex gap-2">
              <button class="px-2 py-1 rounded-full text-xs"
                      :class="form.period_months===3 ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700'"
                      @click="form.period_months=3">3 mo</button>
              <button class="px-2 py-1 rounded-full text-xs"
                      :class="form.period_months===6 ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700'"
                      @click="form.period_months=6">6 mo</button>
              <button class="px-2 py-1 rounded-full text-xs"
                      :class="form.period_months===9 ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700'"
                      @click="form.period_months=9">9 mo</button>
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block text-[11px] text-gray-600 mb-1">Status</label>
              <select x-model="form.status"
                      class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 ring-brand bg-white text-sm py-2.5">
                <option value="active">Active</option>
                <option value="moved">Moved</option>
                <option value="changed_cadre">Changed Cadre</option>
                <option value="changed_department">Changed Department</option>
                <option value="changed_facility">Changed Facility/Hospital</option>
                <option value="retired">Retired</option>
              </select>
            </div>

            <div>
              <label class="block text-[11px] text-gray-600 mb-1">New Grade</label>
              <input x-model="form.changes.grade" placeholder="e.g., UPG-III"
                     class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 ring-brand bg-white text-sm py-2.5 px-3">
            </div>

            <div>
              <label class="block text-[11px] text-gray-600 mb-1">New Cadre</label>
              <input x-model="form.changes.cadre" placeholder="e.g., Nursing Officer"
                     class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 ring-brand bg-white text-sm py-2.5 px-3">
            </div>

            <div>
              <label class="block text-[11px] text-gray-600 mb-1">New Department</label>
              <input x-model="form.changes.department" placeholder="e.g., Maternity"
                     class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 ring-brand bg-white text-sm py-2.5 px-3">
            </div>

            <div class="sm:col-span-2">
              <label class="block text-[11px] text-gray-600 mb-1">New Facility/Hospital</label>
              <input x-model="form.changes.facility" placeholder="e.g., KNH"
                     class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 ring-brand bg-white text-sm py-2.5 px-3">
            </div>

            <div class="sm:col-span-2">
              <label class="block text-[11px] text-gray-600 mb-1">Notes</label>
              <textarea x-model="form.notes" rows="3" placeholder="Context, source, effective date…"
                        class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 ring-brand bg-white text-sm p-3"></textarea>
            </div>
          </div>

          <div class="mt-3 flex items-center justify-end gap-2">
            <button class="inline-flex items-center justify-center px-3 py-2 rounded-lg text-sm font-medium text-gray-700 bg-white border border-gray-200 hover:bg-gray-100"
                    @click="resetForm()">Reset</button>
            <button class="inline-flex items-center justify-center px-3 py-2 rounded-lg text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-60"
                    :disabled="saving" @click="saveAttrition()">Save</button>
          </div>

          <template x-if="saveOk">
            <div class="mt-3 text-sm text-emerald-700">Saved ✔</div>
          </template>
        </div>

        {{-- Attrition History --}}
        <div class="border border-gray-200 rounded-2xl p-4 bg-white lift">
          <div class="font-semibold mb-2 text-gray-900">Attrition History</div>
          <template x-if="logs.length===0">
            <div class="text-sm text-gray-500">No entries yet.</div>
          </template>
          <div class="space-y-2">
            <template x-for="l in logs" :key="l.id">
              <div class="rounded-xl border border-gray-200 p-3 bg-white">
                <div class="flex items-center justify-between">
                  <div class="text-sm font-medium text-gray-900">
                    <span class="px-2 py-1 rounded-full bg-gray-100 text-gray-700 text-xs mr-2" x-text="l.period_months + ' mo'"></span>
                    <span x-text="l.status"></span>
                  </div>
                  <div class="text-xs text-gray-500" x-text="l.recorded_at"></div>
                </div>
                <div class="text-xs text-gray-600 mt-1" x-text="l.notes || ''"></div>
                <div class="text-xs text-gray-500 mt-1" x-text="formatChanges(l.changes)"></div>
              </div>
            </template>
          </div>
        </div>

        {{-- Training History --}}
        <div class="border border-gray-200 rounded-2xl p-4 bg-white lift">
          <div class="font-semibold mb-2 text-gray-900">Training History</div>
          <div class="space-y-2">
            <template x-for="h in history" :key="h.training + (h.start_date||'')">
              <div class="rounded-xl border border-gray-200 p-3 bg-white">
                <div class="font-medium text-gray-900" x-text="h.training"></div>
                <div class="text-xs text-gray-500" x-text="(h.start_date||'') + (h.end_date ? ' → ' + h.end_date : '')"></div>
                <div class="text-xs text-gray-600" x-text="h.completion || '—'"></div>
              </div>
            </template>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

@push('scripts')
<script>
function trainingExplorer() {
  return {
    /* --------- STATE ---------- */
    state: {
      county_id: @json($initial['county_id']),
      training_id: @json($initial['training_id']),
      facility_id: @json($initial['facility_id']),
      from: @json($initial['from']),
      to: @json($initial['to']),
      completion: @json($filters['completion']),
      status: @json($filters['status']),
      search: @json($filters['search']),
      training_type: @json($initial['training_type']),
    },

    trainings: [], facilities: [], participants: [],
    insights: @json($insights), kpis: [],
    loading: { trainings: false, facilities: false, participants: false },

    /* Breadcrumb selections */
    selected: { training: null, facility: null, participant: null },
    get currentLevel(){
      if (this.selected.participant) return 'participant';
      if (this.selected.facility) return 'facility';
      if (this.selected.training) return 'training';
      return 'root';
    },

    /* Drawer & profile */
    drawer: { open: false, loading: false },
    profile: {}, history: [], profileKpis: [], profileAi: { headline: '', recommendations: [] },

    /* Attrition form */
    logs: [], form: { period_months: 3, status: 'active', changes: {}, notes: '' }, saving: false, saveOk: false,

    /* --------- LIFECYCLE ---------- */
    init() {
      this.refreshKpis(this.insights.metrics);
      if (this.state.county_id) {
        this.loadTrainings().then(() => {
          if (this.state.training_id) {
            // Try to re-select training by id if present in list
            const t = this.trainings.find(x => x.id === this.state.training_id);
            if (t) this.selected.training = t;

            this.loadFacilities().then(() => {
              if (this.state.facility_id) {
                const f = this.facilities.find(x => x.id === this.state.facility_id);
                if (f) this.selected.facility = f;
                if (this.state.facility_id) this.loadParticipants();
              }
            });
          }
        });
      }
    },

    /* --------- HELPERS ---------- */
    baseParams() {
      const p = new URLSearchParams();
      if (this.state.from) p.set('from', this.state.from);
      if (this.state.to) p.set('to', this.state.to);
      if (this.state.completion) p.set('completion', this.state.completion);
      if (this.state.status) p.set('status', this.state.status);
      if (this.state.search) p.set('search', this.state.search);
      if (this.state.training_type) p.set('training_type', this.state.training_type);
      return p.toString();
    },

    /* --------- LOADERS ---------- */
    async loadTrainings() {
      if (!this.state.county_id) {
        this.trainings = []; this.facilities = []; this.participants = [];
        this.insights = @json($insights); this.refreshKpis(this.insights.metrics);
        // Reset breadcrumb selections
        this.selected.training = null; this.selected.facility = null; this.selected.participant = null;
        this.state.training_id = null; this.state.facility_id = null;
        return;
      }
      this.loading.trainings = true;
      const r = await fetch(`/analytics/${this.state.county_id}/trainings?` + this.baseParams());
      const data = await r.json();
      this.trainings = data.items || [];
      this.insights = data.insights || this.insights;
      this.refreshKpis(this.insights.metrics);
      this.loading.trainings = false;
      // Reset downstream
      this.state.training_id = null; this.state.facility_id = null;
      this.facilities = []; this.participants = [];
      this.selected.training = null; this.selected.facility = null; this.selected.participant = null;
    },

    async loadFacilities() {
      if (!this.state.county_id || !this.state.training_id) return;
      this.loading.facilities = true;
      const r = await fetch(`/analytics/${this.state.county_id}/trainings/${this.state.training_id}/facilities?` + this.baseParams());
      const data = await r.json();
      this.facilities = data.items || [];
      this.insights = data.insights || this.insights;
      this.refreshKpis(this.insights.metrics);
      this.loading.facilities = false;
      // Reset downstream
      this.state.facility_id = null; this.participants = [];
      this.selected.facility = null; this.selected.participant = null;
    },

    async loadParticipants() {
      if (!this.state.training_id || !this.state.facility_id) return;
      this.loading.participants = true;
      const r = await fetch(`/analytics/trainings/${this.state.training_id}/facilities/${this.state.facility_id}/participants?` + this.baseParams());
      const data = await r.json();
      this.participants = data.items || [];
      this.insights = data.insights || this.insights;
      this.refreshKpis(this.insights.metrics);
      this.loading.participants = false;
    },

    /* --------- ACTIONS ---------- */
    selectTraining(t) {
      this.state.training_id = t.id;
      this.selected.training = t;
      this.selected.facility = null;
      this.selected.participant = null;
      this.loadFacilities();
    },

    selectFacility(f) {
      this.state.facility_id = f.id;
      this.selected.facility = f;
      this.selected.participant = null;
      this.loadParticipants();
    },

    reloadCurrent() {
      if (this.state.facility_id) return this.loadParticipants();
      if (this.state.training_id) return this.loadFacilities();
      if (this.state.county_id) return this.loadTrainings();
    },

    refreshKpis(m) {
      this.kpis = [
        { label: 'Total Participants', value: (m?.total_participants ?? 0) },
        { label: 'Completed',          value: (m?.completed ?? 0) },
        { label: 'Pass Rate',          value: (m?.pass_rate ?? 0) + '%' },
        { label: 'Completion Rate',    value: (m?.completion_rate ?? 0) + '%' },
      ];
    },

    async openProfile(userId) {
      this.drawer.open = true;
      this.drawer.loading = true;
      this.resetForm(); this.logs = []; this.saveOk = false;

      const [pRes, lRes] = await Promise.all([
        fetch(`/analytics/participants/${userId}`),
        fetch(`/analytics/participants/${userId}/attrition-logs`),
      ]);

      const p = await pRes.json();
      const l = await lRes.json();

      this.profile = p.profile || {};
      this.history = p.history || [];
      const s = p.stats || {};
      this.profileKpis = [
        { label: 'Total',     value: s.total_trainings ?? 0 },
        { label: 'Completed', value: s.completed_trainings ?? 0 },
        { label: 'Pass %',    value: (s.pass_rate ?? 0) + '%' },
      ];
      this.profileAi = p.ai || this.profileAi;
      this.logs = l.items || [];

      // Highlight participant in breadcrumb
      this.selected.participant = { id: userId, name: this.profile?.name || 'Selected' };

      this.drawer.loading = false;
    },

    resetForm() { this.form = { period_months: 3, status: 'active', changes: {}, notes: '' }; },

    async saveAttrition() {
      if (!this.profile?.id) return;
      this.saving = true; this.saveOk = false;
      const r = await fetch(`/analytics/participants/${this.profile.id}/attrition-logs`, {
        method: 'POST',
        headers: {
          'Content-Type':'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify(this.form)
      });
      this.saving = false;
      if (r.ok) {
        this.saveOk = true;
        this.resetForm();
        const logs = await fetch(`/analytics/participants/${this.profile.id}/attrition-logs`);
        this.logs = (await logs.json()).items || [];
      }
    },

    formatChanges(ch) {
      if (!ch) return '';
      const pairs = [];
      if (ch.grade) pairs.push('Grade → ' + ch.grade);
      if (ch.cadre) pairs.push('Cadre → ' + ch.cadre);
      if (ch.department) pairs.push('Dept → ' + ch.department);
      if (ch.facility) pairs.push('Facility → ' + ch.facility);
      return pairs.join(' • ');
    }
  }
}
</script>
@endpush
@endsection