<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\Response;
use App\Services\Analytics\KenyaHeatmapService;

class KenyaHeatmapController extends Controller {

    public function __construct(private KenyaHeatmapService $svc) {
        
    }

    public function index(Request $request) {
        $type = $request->get('training_type'); // null | global_trainings | facility_mentorship
        $scope = [];
        if ($type)
            $scope['training_types'] = [$type];

        $mapData = $this->svc->getMapData($scope);
        $ai = $this->svc->getAIInsights($scope);

        return view('analytics.heatmap', [
            'mapData' => $mapData,
            'ai' => $ai,
            'training_type' => $type ?: '',
        ]);
    }

    public function data(Request $request) {
        return response()->json([
                    'mapData' => $this->svc->getMapData([
                        'from' => $request->get('from'),
                        'to' => $request->get('to'),
                    ]),
                    'aiInsights' => $this->svc->getAIInsights([
                        'from' => $request->get('from'),
                        'to' => $request->get('to'),
                    ]),
        ]);
    }

    /**
     * Serves GeoJSON from public/kenyan-counties.geojson
     * Falls back to an empty FeatureCollection if the file is missing.
     */
    public function geojson(): Response {
        $path = public_path('kenyan-counties.geojson');
        if (File::exists($path)) {
            return response(File::get($path), 200, ['Content-Type' => 'application/json']);
        }
        return response(json_encode(['type' => 'FeatureCollection', 'features' => []]), 200, ['Content-Type' => 'application/json']);
    }
}