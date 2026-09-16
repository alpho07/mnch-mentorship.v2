<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Carbon;
use App\Models\Training;
use App\Models\Facility;
use App\Models\Department;
use App\Models\Program;
use App\Models\County;
use App\Models\Subcounty;
use App\Models\TrainingParticipant;
use App\Models\Cadre;

class TrainingCoverageDashboard extends Component
{
    // Filters
    public $program_id = [];
    public $period = [];
    public $county_id = [];
    public $subcounty_id = [];
    public $facility_id = [];
    public $department_id = null;
    public $cadre_id = null;

    // Chart + KPI data
    public $labels = [];
    public $data = [];
    public $county_labels = [];
    public $county_data = [];
    public $dept_labels = [];
    public $dept_data = [];
    public $cadre_labels = [];
    public $cadre_data = [];

    // Metrics
    public $totalTrainings = 0;
    public $totalParticipants = 0;
    public $facilitiesTrained = 0;
    public $lastTrainingDate = null;
    public $coveragePercentage = 0;
    public $activeMonths = 0;

    // For filter dropdowns
    public $programOptions = [];
    public $periodOptions = [];
    public $countyOptions = [];
    public $subcountyOptions = [];
    public $facilityOptions = [];
    public $departmentOptions = [];
    public $cadreOptions = [];

    public function mount()
    {
        $this->fetchFilterOptions();
        $this->updateDashboard();
    }

    public function updated($propertyName)
    {
        // If filter was changed, update dashboard
        $this->updateDashboard();

        // Cascade dropdowns
        if ($propertyName == 'county_id') {
            $this->subcounty_id = [];
            $this->facility_id = [];
            $this->fetchFilterOptions();
        }
        if ($propertyName == 'subcounty_id') {
            $this->facility_id = [];
            $this->fetchFilterOptions();
        }
    }

    public function clearFilters()
    {
        $this->program_id = [];
        $this->period = [];
        $this->county_id = [];
        $this->subcounty_id = [];
        $this->facility_id = [];
        $this->department_id = null;
        $this->cadre_id = null;
        $this->fetchFilterOptions();
        $this->updateDashboard();
    }

    public function fetchFilterOptions()
    {
        $this->programOptions = Program::pluck('name', 'id')->toArray();
        $this->periodOptions = Training::selectRaw('DISTINCT YEAR(start_date) as year, MONTH(start_date) as month')
            ->orderBy('year')->orderBy('month')
            ->get()
            ->map(fn($t) => [
                'value' => sprintf('%04d-%02d', $t->year, $t->month),
                'label' => Carbon::create($t->year, $t->month)->format('M-Y'),
            ])
            ->unique('value')->pluck('label', 'value')->toArray();
        $this->countyOptions = County::pluck('name', 'id')->toArray();

        $subcountyQuery = Subcounty::query();
        if ($this->county_id) $subcountyQuery->whereIn('county_id', (array)$this->county_id);
        $this->subcountyOptions = $subcountyQuery->pluck('name', 'id')->toArray();

        $facilityQuery = Facility::query();
        if ($this->subcounty_id) $facilityQuery->whereIn('subcounty_id', (array)$this->subcounty_id);
        elseif ($this->county_id) $facilityQuery->whereHas('subcounty', fn($q) => $q->whereIn('county_id', (array)$this->county_id));
        $this->facilityOptions = $facilityQuery->pluck('name', 'id')->toArray();

        $this->departmentOptions = Department::pluck('name', 'id')->toArray();
        $this->cadreOptions = Cadre::pluck('name', 'id')->toArray();
    }

    public function updateDashboard()
    {
        $query = Training::query();

        // Filters
        if ($this->program_id && count($this->program_id))
            $query->whereIn('program_id', (array)$this->program_id);

        if ($this->period && count($this->period)) {
            $query->where(function ($q) {
                foreach ($this->period as $period) {
                    $date = Carbon::createFromFormat('Y-m', $period);
                    $q->orWhereBetween('start_date', [
                        $date->startOfMonth(),
                        $date->endOfMonth(),
                    ]);
                }
            });
        }

        if ($this->facility_id && count($this->facility_id))
            $query->whereIn('facility_id', (array)$this->facility_id);
        elseif ($this->subcounty_id && count($this->subcounty_id))
            $query->whereHas('facility', fn($q) => $q->whereIn('subcounty_id', (array)$this->subcounty_id));
        elseif ($this->county_id && count($this->county_id))
            $query->whereHas('facility.subcounty', fn($q) => $q->whereIn('county_id', (array)$this->county_id));
        if ($this->department_id)
            $query->whereHas('departments', fn($q) => $q->where('departments.id', $this->department_id));

        // KPIs
        $this->totalTrainings = $query->count();
        $this->facilitiesTrained = $query->distinct('facility_id')->count('facility_id');

        // PATCH: Get last training date by ordering on non-grouped query
        $last = (clone $query)->orderBy('start_date', 'desc')->first();
        $this->lastTrainingDate = $last ? Carbon::parse($last->start_date)->format('M-Y') : null;

        // PATCH: Remove orderBy from grouped query for activeMonths
        $this->activeMonths = (clone $query)
            ->selectRaw('YEAR(start_date) as year, MONTH(start_date) as month')
            ->groupByRaw('YEAR(start_date), MONTH(start_date)')
            // No orderBy here!
            ->get()
            ->count();

        $this->coveragePercentage = Facility::count() > 0
            ? ($this->facilitiesTrained / Facility::count()) * 100 : 0;

        // Participants KPI
        $participantQuery = TrainingParticipant::query();
        $participantQuery->whereHas('training', function ($q) use ($query) {
            $query->getQuery()->wheres && $q->addNestedWhereQuery($query->getQuery());
        });
        if ($this->department_id) $participantQuery->where('department_id', $this->department_id);
        if ($this->cadre_id) $participantQuery->where('cadre_id', $this->cadre_id);
        $this->totalParticipants = $participantQuery->count();

        // Trainings by Month chart (OK: order/group by the same columns)
        $results = (clone $query)
            ->selectRaw('YEAR(start_date) as year, MONTH(start_date) as month, COUNT(*) as count')
            ->groupByRaw('YEAR(start_date), MONTH(start_date)')
            ->orderByRaw('YEAR(start_date) DESC, MONTH(start_date) DESC')
            ->get();
        $this->labels = $results->map(fn($r) => Carbon::create($r->year, $r->month)->format('M-Y'))->toArray();
        $this->data = $results->map(fn($r) => $r->count)->toArray();

        // By County
        $countyResults = (clone $query)->with('facility.subcounty.county')->get()
            ->groupBy('facility.subcounty.county.name')->map->count()->sortDesc()->take(10);
        $this->county_labels = $countyResults->keys()->toArray();
        $this->county_data = $countyResults->values()->toArray();

        // By Department
        $deptResults = TrainingParticipant::query()
            ->whereHas('training', function ($q) use ($query) {
                $query->getQuery()->wheres && $q->addNestedWhereQuery($query->getQuery());
            })
            ->with('department')
            ->get()
            ->groupBy('department.name')
            ->map->count()
            ->sortDesc()
            ->take(10);
        $this->dept_labels = $deptResults->keys()->toArray();
        $this->dept_data = $deptResults->values()->toArray();

        // By Cadre
        $cadreResults = TrainingParticipant::query()
            ->whereHas('training', function ($q) use ($query) {
                $query->getQuery()->wheres && $q->addNestedWhereQuery($query->getQuery());
            })
            ->with('cadre')
            ->get()
            ->groupBy('cadre.name')
            ->map->count()
            ->sortDesc()
            ->take(10);
        $this->cadre_labels = $cadreResults->keys()->toArray();
        $this->cadre_data = $cadreResults->values()->toArray();
    }

    public function render()
    {
        return view('filament.livewire.training-coverage-dashboard');
    }
}
