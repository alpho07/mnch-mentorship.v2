<?php

namespace App\Services\Site;

use App\Models\Resource;
use App\Models\ResourceCategory;
use App\Models\Training;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * SiteDataSource on the real schema.
 *
 * A "mentorship" is a facility_mentorship Training that is live (Training::liveMentorship()), the same
 * definition the analytics dashboards use. Resources are the guest-visible published ones, as on the old pages.
 */
class DatabaseSiteData implements SiteDataSource
{
    /** resource_types.slug → the five formats the pages know. Anything unlisted is a PDF. */
    private const FORMAT_MAP = [
        'presentation-slides' => 'Slides', 'webinar' => 'Slides', 'microlearning' => 'Slides',
        'manual' => 'Manual', 'textbook' => 'Manual', 'workbook' => 'Manual', 'handout' => 'Manual', 'e-book' => 'Manual',
        'policy-document' => 'Guideline', 'checklist' => 'Guideline', 'job-aid' => 'Guideline', 'template' => 'Guideline',
        'website-link' => 'Online', 'online-course' => 'Online', 'interactive-module' => 'Online', 'video-tutorial' => 'Online',
        'mobile-app' => 'Online', 'software-tool' => 'Online', 'calculator' => 'Online', 'animation' => 'Online',
    ];

    private function mentorships(): Builder
    {
        return Training::query()->where('type', 'facility_mentorship')->dashboardVisible();
    }

    /** Live mentorships joined to their facility's county. */
    private function liveByCounty()
    {
        return DB::table('trainings as t')
            ->join('facilities as fa', 'fa.id', '=', 't.facility_id')
            ->join('subcounties as sc', 'sc.id', '=', 'fa.subcounty_id')
            ->join('counties as co', 'co.id', '=', 'sc.county_id')
            ->whereIn('t.id', $this->mentorships()->liveMentorship()->select('trainings.id'));
    }

    public function homeStats(): array
    {
        $today = Carbon::today();
        $live = $this->liveByCounty();
        $pipeline = $this->mentorships()->whereIn('status', ['draft', 'active'])
            ->whereBetween('start_date', [$today, $today->copy()->addDays(30)]);

        return [
            'mentorships' => (clone $live)->count('t.id'),
            'counties' => (clone $live)->distinct()->count('co.id'),
            'facilities' => (clone $live)->distinct()->count('fa.id'),
            'mentees' => (clone $live)
                ->join('mentorship_classes as mc', fn ($j) => $j->on('mc.training_id', '=', 't.id')->whereNull('mc.deleted_at'))
                ->join('class_participants as cp', 'cp.mentorship_class_id', '=', 'mc.id')
                ->whereIn('cp.status', ['enrolled', 'active', 'completed'])
                ->distinct()->count('cp.user_id'),
            'active_mentorships' => (clone $live)->where('t.status', 'active')->count('t.id'),
            'active_classes' => (clone $live)->where('t.status', 'active')
                ->join('mentorship_classes as mc', fn ($j) => $j->on('mc.training_id', '=', 't.id')->whereNull('mc.deleted_at'))
                ->count('mc.id'),
            'pipeline_30d' => (clone $pipeline)->count(),
            'pipeline_counties' => (clone $pipeline)
                ->join('facilities as fa', 'fa.id', '=', 'trainings.facility_id')
                ->join('subcounties as sc', 'sc.id', '=', 'fa.subcounty_id')
                ->distinct()->count('sc.county_id'),
        ];
    }

    public function liveMentorshipsByCounty(): array
    {
        $rows = $this->liveByCounty()
            ->groupBy('co.name', 'fa.name')
            ->orderByDesc(DB::raw('count(*)'))
            ->get(['co.name as county', 'fa.name as facility', DB::raw('count(*) as n')]);

        $out = [];
        foreach ($rows as $r) {
            $out[$r->county][] = ['facility' => $r->facility, 'count' => (int) $r->n];
        }

        return $out;
    }

    public function mentorshipCalendar(\DateTimeInterface $today): array
    {
        $t = Carbon::instance(\DateTime::createFromInterface($today))->startOfDay();

        // Not mentorships(): dashboardVisible() drops every draft, but a draft that has not started is "coming up".
        return Training::query()->where('type', 'facility_mentorship')->where('is_pilot', false)
            ->whereNotNull('start_date')
            // Status decides what is shown, not stray dates: live and closed need active / completed;
            // a draft only qualifies as upcoming (it has not started yet).
            ->where(fn ($q) => $q
                ->where(fn ($w) => $w->where('status', 'active')->where('end_date', '>=', $t))
                ->orWhere(fn ($w) => $w->where('status', 'draft')->where('start_date', '>', $t))
                ->orWhere('status', 'completed'))
            ->whereRaw('COALESCE(end_date, start_date) >= ?', [$t->copy()->subDays(30)->toDateString()])
            ->where('start_date', '<=', $t->copy()->addDays(30))
            ->with(['program:id,name', 'facility.subcounty.county'])
            ->orderBy('end_date')
            ->limit(60)
            ->get()
            ->filter(fn ($m) => $m->facility?->subcounty?->county)
            ->map(function ($m) use ($t) {
                $name = (string) $m->program?->name;
                $label = $m->program_area === 'emonc' || stripos($name, 'EmONC') !== false ? 'EmONC'
                    : (stripos($name, 'Infant') !== false || stripos($name, 'Child') !== false ? 'Infant & Child Care' : 'Newborn Care');
                $end = Carbon::parse($m->end_date ?? $m->start_date);

                return [
                    'programme' => $label,
                    'facility' => $m->facility->name,
                    'county' => $m->facility->subcounty->county->name,
                    'starts_on' => Carbon::parse($m->start_date)->toDateString(),
                    'ends_on' => $end->toDateString(),
                    'closed' => $m->status === 'completed' || $end->lt($t),
                ];
            })->values()->all();
    }

    public function resources(): array
    {
        return Resource::published()->public()
            ->with(['resourceType:id,slug', 'category:id,name'])
            ->get()
            ->map(fn (Resource $r) => self::row($r))->all();
    }

    /** One resource in the shape SiteDataSource::resources() returns (also used for listing pages). */
    public static function row(Resource $r): array
    {
        return [
            'slug' => $r->slug,
            'title' => $r->title,
            'format' => self::FORMAT_MAP[$r->resourceType?->slug] ?? 'PDF',
            'subcategory' => (string) $r->category?->name,
            'views' => (int) $r->view_count,
            'downloads' => (int) $r->download_count,
            'created_at' => Carbon::parse($r->published_at ?? $r->created_at)->toDateString(),
            'advanced' => $r->difficulty_level === 'advanced',
            'featured' => (bool) $r->is_featured,
            'thumbnail' => $r->featured_image ? Storage::disk('thumbnails')->url($r->featured_image) : null,
            'excerpt' => $r->excerpt ? mb_strimwidth(strip_tags($r->excerpt), 0, 180, '…') : null,
        ];
    }

    public function categories(): array
    {
        $cats = ResourceCategory::active()->orderBy('sort_order')->orderBy('name')->get();
        $counts = Resource::published()->public()->withoutGlobalScopes()->without(['resourceType', 'category', 'author'])
            ->groupBy('category_id')->pluck(DB::raw('count(*)'), 'category_id');
        $children = $cats->whereNotNull('parent_id')->groupBy('parent_id');

        return $cats->whereNull('parent_id')->map(fn ($c) => [
            'name' => $c->name,
            'slug' => $c->slug,
            'description' => (string) $c->description,
            'direct' => (int) ($counts[$c->id] ?? 0),
            'subcategories' => ($children[$c->id] ?? collect())->map(fn ($s) => [
                'name' => $s->name, 'slug' => $s->slug, 'count' => (int) ($counts[$s->id] ?? 0),
            ])->values()->all(),
        ])->values()->all();
    }
}
