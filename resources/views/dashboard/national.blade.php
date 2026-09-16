@extends('layouts.app')

@section('content')
    <div class="container mx-auto py-6">
    <!-- Stat Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="p-4 bg-white shadow rounded-xl">
                <h3 class="text-gray-500">Total Trainings</h3>
                <p class="text-2xl font-bold">{{ $totals['trainings'] }}</p>
            </div>
            <div class="p-4 bg-white shadow rounded-xl">
                <h3 class="text-gray-500">Total Participants</h3>
                <p class="text-2xl font-bold">{{ $totals['participants'] }}</p>
            </div>
            <div class="p-4 bg-white shadow rounded-xl">
                <h3 class="text-gray-500">Facilities</h3>
                <p class="text-2xl font-bold">{{ $totals['facilities'] }}</p>
            </div>
            <div class="p-4 bg-white shadow rounded-xl">
                <h3 class="text-gray-500">Covered Facilities</h3>
                <p class="text-2xl font-bold">{{ $totals['covered_facilities'] }}</p>
            </div>
        </div>

    <!-- Heatmap -->
        <div class="bg-white shadow rounded-xl p-4 mb-6">
            <h2 class="text-lg font-semibold mb-2">Training Coverage by County</h2>
            <div id="map" class="w-full h-96 rounded-lg"></div>
        </div>

    <!-- Charts -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div class="bg-white shadow rounded-xl p-4">
                <h2 class="text-lg font-semibold mb-2">Participants by Facility Type</h2>
                <canvas id="facilityTypeChart"></canvas>
            </div>
            <div class="bg-white shadow rounded-xl p-4">
                <h2 class="text-lg font-semibold mb-2">Participants by Department</h2>
                <canvas id="departmentChart"></canvas>
            </div>
            <div class="bg-white shadow rounded-xl p-4">
                <h2 class="text-lg font-semibold mb-2">Participants by Cadre</h2>
                <canvas id="cadreChart"></canvas>
            </div>
        </div>

    <!-- Insights -->
        <div class="bg-white shadow rounded-xl p-4">
            <h2 class="text-lg font-semibold mb-2">Insights</h2>
            <ul class="list-disc pl-6 text-gray-700">
                <li>Hospitals may dominate trainings — check balance with health centres & dispensaries.</li>
                <li>Identify cadres under-represented in trainings.</li>
                <li>Counties with low participant coverage should be prioritized.</li>
            </ul>
        </div>
    </div>

<!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    document.addEventListener("DOMContentLoaded", function () {
        // ================================
        // Leaflet Map
        // ================================
        const map = L.map('map').setView([0.0236, 37.9062], 6); // Kenya center
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        @foreach($counties as $county)
    {
        let lat = 1 + Math.random() * 3;
        let lng = 36 + Math.random() * 3;
            let intensity = Math.min(1, {{ $county->trainings_count }} / 50);
        let color = intensity > 0.7 ? 'red' : intensity > 0.3 ? 'orange' : 'green';

        let marker = L.circleMarker([lat, lng], {
            radius: 10 + intensity * 20,
            color: color,
            fillOpacity: 0.6
        }).addTo(map);

        marker.bindPopup(`
            <strong>{{ $county->name }}</strong><br>
            Trainings: {{ $county->trainings_count }}<br>
            Participants: {{ $county->participants_count }}
        `);
    }
        @endforeach


        
        
        // ================================
        // Facility Types
        // ================================
        const facilityTypeLabels = {!! json_encode($distribution['facility_types']->pluck('label')) !!};
        const facilityTypeData = {!! json_encode($distribution['facility_types']->pluck('participants')) !!};

        new Chart(document.getElementById('facilityTypeChart'), {
            type: 'pie',
            data: {
                labels: facilityTypeLabels,
                datasets: [{
                    data: facilityTypeData,
                    backgroundColor: ['#4caf50', '#2196f3', '#ff9800', '#f44336', '#9c27b0']
                }]
            }
        });

        // ================================
        // Departments
        // ================================
        const departmentLabels = {!! json_encode($distribution['departments']->pluck('label')) !!};
        const departmentData = {!! json_encode($distribution['departments']->pluck('participants')) !!};

        new Chart(document.getElementById('departmentChart'), {
            type: 'pie',
            data: {
                labels: departmentLabels,
                datasets: [{
                    data: departmentData,
                    backgroundColor: ['#2196f3', '#4caf50', '#ff9800', '#f44336', '#9c27b0']
                }]
            }
        });

        // ================================
        // Cadres
        // ================================
        const cadreLabels = {!! json_encode($distribution['cadres']->pluck('label')) !!};
        const cadreData = {!! json_encode($distribution['cadres']->pluck('participants')) !!};

        new Chart(document.getElementById('cadreChart'), {
            type: 'pie',
            data: {
                labels: cadreLabels,
                datasets: [{
                    data: cadreData,
                    backgroundColor: ['#9c27b0', '#2196f3', '#4caf50', '#ff9800', '#f44336']
                }]
            }
        });
    });
    </script>
@endsection
