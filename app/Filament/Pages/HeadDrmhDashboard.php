<?php

namespace App\Filament\Pages;

use App\Models\ClassParticipant;
use Filament\Pages\Page;

class HeadDrmhDashboard extends Page
{
    protected static string $view = 'filament.pages.head-drmh-dashboard';

    protected static ?string $slug = 'head-drmh-dashboard';

    protected static ?string $navigationGroup = 'Dashboards';

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationLabel = 'Head DRMH';

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

    public string $activeTab = 'pending';

    // ─── Mount ───────────────────────────────────────────────────────────────
    public function mount(): void
    {
        $this->loadData();
    }

    // ─── Data loader ─────────────────────────────────────────────────────────
    private function loadData(): void
    {
        $with = [
            'user.facility.subcounty.county',
            'user.cadre',
            'mentorshipClass.training.facility',
            'mentorApprovedBy',
            'headDrmhApprovedBy',
            'moduleProgress',
        ];

        $mentorApprovedPending = ClassParticipant::query()
            ->whereNotNull('mentor_approved_at')
            ->whereNull('head_drmh_approved_at')
            ->whereHas('mentorshipClass.training', fn ($q) => $q->where('type', 'facility_mentorship'))
            ->with($with)
            ->get();

        // Non-EmONC mentees never go through mentor_approve (see
        // ManageClassMentees.php) — they're pending the moment they've
        // completed every module, per ClassParticipant::isReadyForHeadDrmhCertification().
        $nonEmoncPending = ClassParticipant::query()
            ->whereNull('mentor_approved_at')
            ->whereNull('head_drmh_approved_at')
            ->where('status', 'completed')
            ->whereHas('mentorshipClass.training', function ($q) {
                $q->where('type', 'facility_mentorship')
                    ->whereHas('program', fn ($pq) => $pq->whereRaw('LOWER(name) NOT LIKE ?', ['%maternal%'])
                        ->orWhereRaw('LOWER(name) NOT LIKE ?', ['%emonc%']));
            })
            ->with($with)
            ->get();

        $pending = $mentorApprovedPending->concat($nonEmoncPending)
            ->sortByDesc(fn (ClassParticipant $p) => $p->mentor_approved_at ?? $p->updated_at)
            ->values();

        $certified = ClassParticipant::query()
            ->whereNotNull('head_drmh_approved_at')
            ->whereHas('mentorshipClass.training', fn ($q) => $q->where('type', 'facility_mentorship'))
            ->with($with)
            ->orderByDesc('head_drmh_approved_at')
            ->limit(60)
            ->get();

        $this->kpis = [
            'pending'               => $pending->count(),
            'certified_this_month'  => $certified->filter(fn ($p) => $p->head_drmh_approved_at && \Carbon\Carbon::parse($p->head_drmh_approved_at)->isCurrentMonth())->count(),
            'certified_total'       => ClassParticipant::whereNotNull('head_drmh_approved_at')->count(),
            'mentorships_with_pending' => $pending->pluck('mentorshipClass.training_id')->filter()->unique()->count(),
        ];

        $this->pendingList  = $pending->map(fn ($p) => $this->fmt($p))->toArray();
        $this->certifiedList = $certified->map(fn ($p) => $this->fmt($p))->toArray();
    }

    private function fmt(ClassParticipant $p): array
    {
        $modTotal = $p->moduleProgress->count();
        $modDone  = $p->moduleProgress->where('status', 'completed')->count();

        return [
            'id'                    => $p->id,
            'name'                  => $p->user?->full_name ?? '—',
            'initials'              => strtoupper(
                substr($p->user?->first_name ?? 'M', 0, 1) .
                substr($p->user?->last_name  ?? 'E', 0, 1)
            ),
            'cadre'                 => $p->user?->cadre?->name ?? '—',
            'facility'              => $p->user?->facility?->name ?? '—',
            'county'                => $p->user?->facility?->subcounty?->county?->name ?? '—',
            'class'                 => $p->mentorshipClass?->name ?? '—',
            'training'              => $p->mentorshipClass?->training?->title ?? '—',
            'training_facility'     => $p->mentorshipClass?->training?->facility?->name ?? '—',
            'mentor_approved_at'    => $p->mentor_approved_at ? \Carbon\Carbon::parse($p->mentor_approved_at)->diffForHumans() : '—',
            'mentor_approved_by'    => $p->mentorApprovedBy?->full_name ?? '—',
            'head_drmh_approved_at' => $p->head_drmh_approved_at ? \Carbon\Carbon::parse($p->head_drmh_approved_at)->format('d M Y') : null,
            'head_drmh_approved_by' => $p->headDrmhApprovedBy?->full_name,
            'modules_done'          => $modDone,
            'modules_total'         => $modTotal,
            'class_id'              => $p->mentorshipClass?->id,
            'review_url'            => HeadDrmhReviewMentee::getUrl(['participant' => $p->id]),
            'cert_url'              => $p->head_drmh_approved_at
                ? route('reports.class.certificate', ['class' => $p->mentorshipClass?->id, 'participant' => $p->id])
                : null,
        ];
    }

    // ─── Reactive search ─────────────────────────────────────────────────────
    public function updatedDSearch(): void {} // view filters inline

    // ─── View data ───────────────────────────────────────────────────────────
    protected function getViewData(): array
    {
        $needle = strtolower($this->dSearch);

        $filter = fn ($list) => $needle === '' ? $list : array_values(array_filter(
            $list,
            fn ($p) => str_contains(strtolower($p['name']), $needle)
                || str_contains(strtolower($p['facility']), $needle)
                || str_contains(strtolower($p['training']), $needle)
                || str_contains(strtolower($p['county']), $needle)
        ));

        return [
            'pending'   => $filter($this->pendingList),
            'certified' => $filter($this->certifiedList),
        ];
    }
}
