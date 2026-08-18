<?php

namespace App\Filament\Pages;

use App\Models\ClassParticipant;
use Filament\Pages\Page;
use Illuminate\Pagination\LengthAwarePaginator;

class HeadDrmhDashboard extends Page
{
    protected static string $view = 'filament.pages.head-drmh-dashboard';

    protected static ?string $slug = 'head-drmh-dashboard';

    protected static ?string $navigationGroup = 'Dashboards';

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationLabel = 'Certificate Center';

    protected static ?string $title = 'Certificate Issuance Hub';

    protected static ?int $navigationSort = 3;

    private const ALLOWED_ROLES = ['head_drmh', 'super_admin', 'admin'];

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->can('page_HeadDrmhDashboard');
    }

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->can('page_HeadDrmhDashboard');
    }

    // ─── State ───────────────────────────────────────────────────────────────
    public array $kpis = [];

    public array $pendingList = [];

    public array $certifiedList = [];

    public string $dSearch = '';

    public string $dProgram = '';

    public string $activeTab = 'pending';

    public int $dPage = 1;

    public int $perPage = 10;

    // ─── Mount ───────────────────────────────────────────────────────────────
    public function mount(): void
    {
        $this->loadData();
    }

    // ─── Tab / filter / pagination actions ──────────────────────────────────
    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->dPage = 1;
    }

    public function updatedDSearch(): void
    {
        $this->dPage = 1;
    }

    public function updatedDProgram(): void
    {
        $this->dPage = 1;
    }

    public function setDPage(int $page): void
    {
        $this->dPage = max(1, $page);
    }

    // ─── Data loader ─────────────────────────────────────────────────────────
    private function loadData(): void
    {
        $with = [
            'user.facility.subcounty.county',
            'user.cadre',
            'mentorshipClass.training.facility',
            'mentorshipClass.training.program',
            'mentorApprovedBy',
            'headDrmhApprovedBy',
            'moduleProgress',
        ];

        // Each program (EmONC, Newborn Care, Infant & Child Care) certifies
        // independently, and readiness now depends on the mentee's progress
        // across ALL of their enrollments in that program — not just this
        // one class — so candidates are pulled broadly here and the actual
        // gate (ClassParticipant::isReadyForHeadDrmhCertification()) is
        // evaluated in PHP rather than approximated in SQL.
        $candidates = ClassParticipant::query()
            ->whereNull('head_drmh_approved_at')
            ->whereHas('mentorshipClass.training', fn ($q) => $q->where('type', 'facility_mentorship'))
            ->with($with)
            ->get();

        $pending = $candidates->filter(fn (ClassParticipant $p) => $p->isReadyForHeadDrmhCertification())
            ->sortByDesc(fn (ClassParticipant $p) => $p->mentor_approved_at ?? $p->updated_at)
            ->values();

        $certified = ClassParticipant::query()
            ->whereNotNull('head_drmh_approved_at')
            ->whereHas('mentorshipClass.training', fn ($q) => $q->where('type', 'facility_mentorship'))
            ->with($with)
            ->orderByDesc('head_drmh_approved_at')
            ->get();

        $this->kpis = [
            'pending' => $pending->count(),
            'certified_this_month' => $certified->filter(fn ($p) => $p->head_drmh_approved_at && \Carbon\Carbon::parse($p->head_drmh_approved_at)->isCurrentMonth())->count(),
            'certified_total' => ClassParticipant::whereNotNull('head_drmh_approved_at')->count(),
            'mentorships_with_pending' => $pending->pluck('mentorshipClass.training_id')->filter()->unique()->count(),
        ];

        $this->pendingList = $pending->map(fn ($p) => $this->fmt($p))->toArray();
        $this->certifiedList = $certified->map(fn ($p) => $this->fmt($p))->toArray();
    }

    private function fmt(ClassParticipant $p): array
    {
        $modTotal = $p->moduleProgress->count();
        $modDone = $p->moduleProgress->where('status', 'completed')->count();
        $program = $p->mentorshipClass?->training?->program;

        return [
            'id' => $p->id,
            'name' => $p->user?->full_name ?? '—',
            'initials' => strtoupper(
                substr($p->user?->first_name ?? 'M', 0, 1).
                substr($p->user?->last_name ?? 'E', 0, 1)
            ),
            'cadre' => $p->user?->cadre?->name ?? '—',
            'facility' => $p->user?->facility?->name ?? '—',
            'county' => $p->user?->facility?->subcounty?->county?->name ?? '—',
            'class' => $p->mentorshipClass?->name ?? '—',
            'training' => $p->mentorshipClass?->training?->title ?? '—',
            'training_facility' => $p->mentorshipClass?->training?->facility?->name ?? '—',
            'program_name' => $program?->name ?? '—',
            'is_emonc' => $program?->isEmonc() ?? false,
            'mentor_approved_at' => $p->mentor_approved_at ? \Carbon\Carbon::parse($p->mentor_approved_at)->diffForHumans() : '—',
            'mentor_approved_by' => $p->mentorApprovedBy?->full_name ?? '—',
            'head_drmh_approved_at' => $p->head_drmh_approved_at ? \Carbon\Carbon::parse($p->head_drmh_approved_at)->format('d M Y') : null,
            'head_drmh_approved_by' => $p->headDrmhApprovedBy?->full_name,
            'modules_done' => $modDone,
            'modules_total' => $modTotal,
            'class_id' => $p->mentorshipClass?->id,
            'review_url' => HeadDrmhReviewMentee::getUrl(['participant' => $p->id]),
            'cert_url' => $p->head_drmh_approved_at
                ? route('reports.class.certificate', ['class' => $p->mentorshipClass?->id, 'participant' => $p->id])
                : null,
        ];
    }

    // ─── View data ───────────────────────────────────────────────────────────
    protected function getViewData(): array
    {
        $needle = strtolower($this->dSearch);

        $filter = function ($list) use ($needle) {
            $items = collect($list);

            if ($this->dProgram !== '') {
                $items = $items->filter(fn ($p) => $p['program_name'] === $this->dProgram);
            }

            if ($needle !== '') {
                $items = $items->filter(
                    fn ($p) => str_contains(strtolower($p['name']), $needle)
                        || str_contains(strtolower($p['facility']), $needle)
                        || str_contains(strtolower($p['training']), $needle)
                        || str_contains(strtolower($p['county']), $needle)
                );
            }

            return $items->values();
        };

        $filteredPending = $filter($this->pendingList);
        $filteredCertified = $filter($this->certifiedList);

        $activeList = $this->activeTab === 'pending' ? $filteredPending : $filteredCertified;
        $total = $activeList->count();
        $page = max(1, $this->dPage);

        $paginated = new LengthAwarePaginator(
            $activeList->slice(($page - 1) * $this->perPage, $this->perPage)->values(),
            $total,
            $this->perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        $programOptions = collect($this->pendingList)
            ->concat($this->certifiedList)
            ->pluck('program_name')
            ->filter()
            ->unique()
            ->sort()
            ->values();

        return [
            'pending' => $filteredPending,
            'certified' => $filteredCertified,
            'paginated' => $paginated,
            'programOptions' => $programOptions,
        ];
    }
}
