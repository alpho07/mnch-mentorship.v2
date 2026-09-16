<?php

namespace App\Http\Controllers;

use App\Models\Training;
use Illuminate\Http\Request;

class TrainingPagesController extends Controller
{
    public function mohTraining(Request $request)
    {
        $status = $request->get('status', 'upcoming');
        
        $query = Training::where('type', 'global_training')
            ->with(['programs', 'organizer', 'facility'])
            ->orderBy('start_date', 'desc');

        // Apply status filter
        switch ($status) {
            case 'upcoming':
                $query->where('start_date', '>', now());
                break;
            case 'ongoing':
                $query->where('start_date', '<=', now())
                      ->where('end_date', '>=', now());
                break;
            case 'completed':
                $query->where('end_date', '<', now());
                break;
            case 'all':
            default:
                // No additional filter for 'all'
                break;
        }

        $trainings = $query->paginate(12)->withQueryString();

        return view('training.moh', compact('trainings', 'status'));
    }

    public function mentorshipTraining(Request $request)
    {
        $status = $request->get('status', 'upcoming');
        
        $query = Training::where('type', 'facility_mentorship')
            ->with(['programs', 'organizer', 'facility', 'mentor'])
            ->orderBy('start_date', 'desc');

        // Apply status filter
        switch ($status) {
            case 'upcoming':
                $query->where('start_date', '>', now());
                break;
            case 'ongoing':
                $query->where('start_date', '<=', now())
                      ->where('end_date', '>=', now());
                break;
            case 'completed':
                $query->where('end_date', '<', now());
                break;
            case 'all':
            default:
                // No additional filter for 'all'
                break;
        }

        $trainings = $query->paginate(12)->withQueryString();

        return view('training.mentorship', compact('trainings', 'status'));
    }

    public function allTraining(Request $request)
    {
        $status = $request->get('status', 'upcoming');
        $type = $request->get('type', 'all');
        
        $query = Training::with(['programs', 'organizer', 'facility', 'mentor'])
            ->orderBy('start_date', 'desc');

        // Apply type filter
        if ($type !== 'all') {
            $query->where('type', $type);
        }

        // Apply status filter
        switch ($status) {
            case 'upcoming':
                $query->where('start_date', '>', now());
                break;
            case 'ongoing':
                $query->where('start_date', '<=', now())
                      ->where('end_date', '>=', now());
                break;
            case 'completed':
                $query->where('end_date', '<', now());
                break;
            case 'all':
            default:
                // No additional filter for 'all'
                break;
        }

        $trainings = $query->paginate(12)->withQueryString();

        return view('training.index', compact('trainings', 'status', 'type'));
    }
}