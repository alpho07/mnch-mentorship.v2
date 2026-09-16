<?php

namespace App\Traits;

use App\Models\Training;
use App\Models\County;
use App\Models\Subcounty;
use App\Models\Facility;
use App\Models\Program;
use App\Models\Cadre;
use App\Models\Department;
use App\Models\FacilityType;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

trait HasTrainingFilters
{
    /**
     * Get counties that have training data
     */
    public static function getAvailableCounties(array $filters = []): array
    {
        $query = County::whereHas('subcounties.facilities.trainings', function ($q) use ($filters) {
            self::applyNonGeographicFilters($q, $filters);
        });

        return $query->orderBy('name')->pluck('name', 'id')->toArray();
    }

    /**
     * Get subcounties with cascading county filter
     */
    public static function getAvailableSubcounties(array $filters = []): array
    {
        $query = Subcounty::whereHas('facilities.trainings', function ($q) use ($filters) {
            self::applyNonGeographicFilters($q, $filters);
        });

        // Cascading: filter by selected counties
        if (!empty($filters['counties'])) {
            $query->whereIn('county_id', $filters['counties']);
        }

        return $query->orderBy('name')->pluck('name', 'id')->toArray();
    }

    /**
     * Get facilities with cascading geographic filters
     */
    public static function getAvailableFacilities(array $filters = []): array
    {
        $query = Facility::whereHas('trainings', function ($q) use ($filters) {
            self::applyNonGeographicFilters($q, $filters);
        });

        // Cascading filters
        if (!empty($filters['counties'])) {
            $query->whereHas('subcounty.county', fn($q) => $q->whereIn('id', $filters['counties']));
        }

        if (!empty($filters['subcounties'])) {
            $query->whereIn('subcounty_id', $filters['subcounties']);
        }

        if (!empty($filters['facility_types'])) {
            $query->whereIn('facility_type_id', $filters['facility_types']);
        }

        return $query->select('id', 'name', 'mfl_code')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn($facility) => [
                $facility->id => "{$facility->name} ({$facility->mfl_code})"
            ])
            ->toArray();
    }

    /**
     * Get programs with training data
     */
    public static function getAvailablePrograms(array $filters = []): array
    {
        return Program::whereHas('trainings', function ($q) use ($filters) {
            self::applyAllFilters($q, $filters);
        })->orderBy('name')->pluck('name', 'id')->toArray();
    }

    /**
     * Get cadres with training data
     */
    public static function getAvailableCadres(array $filters = []): array
    {
        return Cadre::whereHas('trainingParticipants.training', function ($q) use ($filters) {
            self::applyAllFilters($q, $filters);
        })->orderBy('name')->pluck('name', 'id')->toArray();
    }

    /**
     * Get departments with training data
     */
    public static function getAvailableDepartments(array $filters = []): array
    {
        return Department::where(function ($query) use ($filters) {
            $query->whereHas('trainings', function ($q) use ($filters) {
                self::applyAllFilters($q, $filters);
            })->orWhereHas('trainingParticipants.training', function ($q) use ($filters) {
                self::applyAllFilters($q, $filters);
            });
        })->orderBy('name')->pluck('name', 'id')->toArray();
    }

    /**
     * Get facility types with training data
     */
    public static function getAvailableFacilityTypes(array $filters = []): array
    {
        return FacilityType::whereHas('facilities.trainings', function ($q) use ($filters) {
            self::applyAllFilters($q, $filters);
        })->orderBy('name')->pluck('name', 'id')->toArray();
    }

    /**
     * Get organizers with training data
     */
    public static function getAvailableOrganizers(array $filters = []): array
    {
        return User::whereHas('organizedTrainings', function ($q) use ($filters) {
            self::applyAllFilters($q, $filters);
        })->orderBy('name')->pluck('name', 'id')->toArray();
    }

    /**
     * Get available years from training data
     */
    public static function getAvailableYears(): array
    {
        return Training::selectRaw('YEAR(start_date) as year')
            ->distinct()
            ->orderBy('year', 'desc')
            ->pluck('year', 'year')
            ->toArray();
    }

    /**
     * Get available quarters in Q1-2024 format
     */
    public static function getAvailableQuarters(): array
    {
        return Training::selectRaw('YEAR(start_date) as year, QUARTER(start_date) as quarter')
            ->distinct()
            ->orderBy('year', 'desc')
            ->orderBy('quarter', 'desc')
            ->get()
            ->mapWithKeys(function ($item) {
                $quarterName = "Q{$item->quarter}";
                $value = "{$item->year}-Q{$item->quarter}";
                $label = "{$quarterName}-{$item->year}";
                return [$value => $label];
            })
            ->toArray();
    }

    /**
     * Get available months in Oct-2025 format
     */
    public static function getAvailableMonths(): array
    {
        return Training::selectRaw('YEAR(start_date) as year, MONTH(start_date) as month')
            ->distinct()
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->get()
            ->mapWithKeys(function ($item) {
                $monthName = \Carbon\Carbon::createFromDate($item->year, $item->month, 1)->format('M');
                $value = "{$item->year}-{$item->month}";
                $label = "{$monthName}-{$item->year}";
                return [$value => $label];
            })
            ->toArray();
    }

    /**
     * Apply time-based and non-geographic filters
     */
    protected static function applyNonGeographicFilters(Builder $query, array $filters): void
    {
        // Time-based filters
        if (!empty($filters['years'])) {
            $query->whereIn(DB::raw('YEAR(start_date)'), $filters['years']);
        }

        if (!empty($filters['quarters'])) {
            $query->where(function ($q) use ($filters) {
                foreach ($filters['quarters'] as $quarter) {
                    if (str_contains($quarter, '-Q')) {
                        [$year, $quarterPart] = explode('-Q', $quarter);
                        $quarterNum = (int) $quarterPart;
                        $q->orWhere(function ($subQ) use ($year, $quarterNum) {
                            $subQ->whereYear('start_date', $year)
                                ->whereRaw('QUARTER(start_date) = ?', [$quarterNum]);
                        });
                    }
                }
            });
        }

        if (!empty($filters['months'])) {
            $query->where(function ($q) use ($filters) {
                foreach ($filters['months'] as $month) {
                    if (str_contains($month, '-')) {
                        [$year, $monthNum] = explode('-', $month);
                        $q->orWhere(function ($subQ) use ($year, $monthNum) {
                            $subQ->whereYear('start_date', $year)
                                ->whereMonth('start_date', $monthNum);
                        });
                    }
                }
            });
        }

        // Program and approach filters
        if (!empty($filters['programs'])) {
            $query->whereIn('program_id', $filters['programs']);
        }

        if (!empty($filters['approaches'])) {
            $query->whereIn('approach', $filters['approaches']);
        }
    }

    /**
     * Apply geographic filters
     */
    protected static function applyGeographicFilters(Builder $query, array $filters): void
    {
        if (!empty($filters['counties'])) {
            $query->whereHas('facility.subcounty.county', fn($q) => $q->whereIn('id', $filters['counties']));
        }

        if (!empty($filters['subcounties'])) {
            $query->whereHas('facility.subcounty', fn($q) => $q->whereIn('id', $filters['subcounties']));
        }

        if (!empty($filters['facilities'])) {
            $query->whereIn('facility_id', $filters['facilities']);
        }

        if (!empty($filters['facility_types'])) {
            $query->whereHas('facility.facilityType', fn($q) => $q->whereIn('id', $filters['facility_types']));
        }
    }

    /**
     * Apply participant-related filters
     */
    protected static function applyParticipantFilters(Builder $query, array $filters): void
    {
        if (!empty($filters['departments'])) {
            $query->where(function ($q) use ($filters) {
                $q->whereHas('departments', fn($subQ) => $subQ->whereIn('departments.id', $filters['departments']))
                    ->orWhereHas('participants.department', fn($subQ) => $subQ->whereIn('id', $filters['departments']));
            });
        }

        if (!empty($filters['cadres'])) {
            $query->whereHas('participants.cadre', fn($q) => $q->whereIn('id', $filters['cadres']));
        }

        if (!empty($filters['organizers'])) {
            $query->whereIn('organizer_id', $filters['organizers']);
        }
    }

    /**
     * Apply all filters to a query
     */
    protected static function applyAllFilters(Builder $query, array $filters): void
    {
        self::applyNonGeographicFilters($query, $filters);
        self::applyGeographicFilters($query, $filters);
        self::applyParticipantFilters($query, $filters);
    }

    /**
     * Get current filter state from request or session
     */
    public static function getCurrentFilters(): array
    {
        return session('training_dashboard_filters', []);
    }

    /**
     * Save filter state to session
     */
    public static function saveFilters(array $filters): void
    {
        session(['training_dashboard_filters' => $filters]);
    }
}
