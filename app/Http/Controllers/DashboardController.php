<?php

namespace App\Http\Controllers;

use App\Models\Training;
use App\Models\TrainingParticipant;
use App\Models\Facility;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function national()
    {
        // ================================
        // Top-Level Stats
        // ================================
        $totalTrainings = Training::count();
        $totalParticipants = TrainingParticipant::count();
        $totalFacilities = Facility::count();
        $coveredFacilities = Facility::whereHas('trainings')->count();

        // ================================
        // County Coverage
        // ================================
        $counties = DB::table('counties')
            ->leftJoin('subcounties', 'counties.id', '=', 'subcounties.county_id')
            ->leftJoin('facilities', 'subcounties.id', '=', 'facilities.subcounty_id')
            ->leftJoin('trainings', 'facilities.id', '=', 'trainings.facility_id')
            ->leftJoin('training_participants', 'trainings.id', '=', 'training_participants.training_id')
            ->leftJoin('users', 'training_participants.user_id', '=', 'users.id')
            ->select(
                'counties.id',
                'counties.name',
                DB::raw('COUNT(DISTINCT trainings.id) as trainings_count'),
                DB::raw('COUNT(DISTINCT training_participants.id) as participants_count'),
                DB::raw('COUNT(DISTINCT facilities.id) as facilities_count'),
                DB::raw('COUNT(DISTINCT users.department_id) as departments_covered'),
                DB::raw('COUNT(DISTINCT users.cadre_id) as cadres_covered')
            )
            ->groupBy('counties.id', 'counties.name')
            ->get();

        // ================================
        // Distributions (by participants)
        // ================================

        // Participants by Facility Type
        $byFacilityType = DB::table('facilities')
            ->join('facility_types', 'facilities.facility_type_id', '=', 'facility_types.id')
            ->join('trainings', 'facilities.id', '=', 'trainings.facility_id')
            ->join('training_participants', 'trainings.id', '=', 'training_participants.training_id')
            ->select('facility_types.name as label', DB::raw('COUNT(training_participants.id) as participants'))
            ->groupBy('facility_types.name')
            ->get();

        // Participants by Department
        $byDepartment = DB::table('users')
            ->join('departments', 'users.department_id', '=', 'departments.id')
            ->join('training_participants', 'users.id', '=', 'training_participants.user_id')
            ->select('departments.name as label', DB::raw('COUNT(training_participants.id) as participants'))
            ->groupBy('departments.name')
            ->get();

        // Participants by Cadre
        $byCadre = DB::table('users')
            ->join('cadres', 'users.cadre_id', '=', 'cadres.id')
            ->join('training_participants', 'users.id', '=', 'training_participants.user_id')
            ->select('cadres.name as label', DB::raw('COUNT(training_participants.id) as participants'))
            ->groupBy('cadres.name')
            ->get();

        // ================================
        // Return to Blade View
        // ================================
        return view('dashboard.national', [
            'totals' => [
                'trainings' => $totalTrainings,
                'participants' => $totalParticipants,
                'facilities' => $totalFacilities,
                'covered_facilities' => $coveredFacilities,
            ],
            'counties' => $counties,
            'distribution' => [
                'facility_types' => $byFacilityType,
                'departments' => $byDepartment,
                'cadres' => $byCadre,
            ],
        ]);
    }
}
