<?php

/*
|--------------------------------------------------------------------------
| Pulse oximeter distribution
|--------------------------------------------------------------------------
|
| County-level dispatch tracker for the pulse oximeter roll-out, as recorded
| by the Division. One row per county, all 47.
|
| Column meanings
|   tagged      devices tagged for the county (1 yes, 0 no, null not recorded)
|   dispatched  consignment released (1 yes, 0 no, null not recorded)
|   devices     units dispatched; null where none or not recorded
|   status      'full' | 'partial' | 'none'
|   facilities  receiving facilities identified; null where not recorded
|
| This tracker counts what has been dispatched to counties. It is a different
| view from the facility-level allocation list held in skills_lab_devices,
| which records 1,244 tagged units against 381 named facilities — the two
| will not reconcile, and neither is wrong: one is dispatch, one allocation.
|
*/

return [

    'counties' => [
        ['county' => 'Elgeyo Marakwet', 'tagged' => 1, 'dispatched' => 1, 'devices' => 17, 'status' => 'full', 'facilities' => 7],
        ['county' => 'Kakamega', 'tagged' => 1, 'dispatched' => 1, 'devices' => 38, 'status' => 'full', 'facilities' => 16],
        ['county' => 'Machakos', 'tagged' => 1, 'dispatched' => 1, 'devices' => 47, 'status' => 'full', 'facilities' => 14],
        ['county' => 'Nairobi', 'tagged' => 1, 'dispatched' => 1, 'devices' => 15, 'status' => 'partial', 'facilities' => 3],
        ['county' => 'Nakuru', 'tagged' => 1, 'dispatched' => 1, 'devices' => 55, 'status' => 'full', 'facilities' => 17],
        ['county' => 'Nandi', 'tagged' => 1, 'dispatched' => 1, 'devices' => 29, 'status' => 'full', 'facilities' => 10],
        ['county' => 'Trans-Nzoia', 'tagged' => 1, 'dispatched' => 1, 'devices' => 26, 'status' => 'full', 'facilities' => 8],
        ['county' => 'Uasin Gishu', 'tagged' => 1, 'dispatched' => 1, 'devices' => 31, 'status' => 'full', 'facilities' => 8],
        ['county' => 'Vihiga', 'tagged' => 1, 'dispatched' => 1, 'devices' => 19, 'status' => 'full', 'facilities' => 6],
        ['county' => 'West Pokot', 'tagged' => 1, 'dispatched' => 1, 'devices' => 17, 'status' => 'full', 'facilities' => 5],
        ['county' => 'Baringo', 'tagged' => 1, 'dispatched' => 1, 'devices' => 24, 'status' => 'full', 'facilities' => 7],
        ['county' => 'Bomet', 'tagged' => 1, 'dispatched' => 1, 'devices' => 20, 'status' => 'full', 'facilities' => 6],
        ['county' => 'Bungoma', 'tagged' => 1, 'dispatched' => 1, 'devices' => 28, 'status' => 'full', 'facilities' => 11],
        ['county' => 'Busia', 'tagged' => 1, 'dispatched' => 1, 'devices' => 24, 'status' => 'full', 'facilities' => 7],
        ['county' => 'Embu', 'tagged' => 1, 'dispatched' => 1, 'devices' => 17, 'status' => 'full', 'facilities' => 5],
        ['county' => 'Garissa', 'tagged' => null, 'dispatched' => 0, 'devices' => null, 'status' => 'none', 'facilities' => null],
        ['county' => 'Homabay', 'tagged' => 1, 'dispatched' => 1, 'devices' => 39, 'status' => 'partial', 'facilities' => 11],
        ['county' => 'Isiolo', 'tagged' => null, 'dispatched' => null, 'devices' => null, 'status' => 'none', 'facilities' => null],
        ['county' => 'Kajiado', 'tagged' => 1, 'dispatched' => 0, 'devices' => null, 'status' => 'none', 'facilities' => 5],
        ['county' => 'Kericho', 'tagged' => 1, 'dispatched' => 0, 'devices' => null, 'status' => 'none', 'facilities' => 8],
        ['county' => 'Kiambu', 'tagged' => 1, 'dispatched' => 1, 'devices' => 40, 'status' => 'partial', 'facilities' => 13],
        ['county' => 'Kilifi', 'tagged' => 1, 'dispatched' => 1, 'devices' => 25, 'status' => 'full', 'facilities' => 7],
        ['county' => 'Kirinyaga', 'tagged' => 1, 'dispatched' => 0, 'devices' => null, 'status' => 'none', 'facilities' => 4],
        ['county' => 'Kisii', 'tagged' => 1, 'dispatched' => 1, 'devices' => 25, 'status' => 'full', 'facilities' => 7],
        ['county' => 'Kisumu', 'tagged' => 1, 'dispatched' => 1, 'devices' => 55, 'status' => 'full', 'facilities' => 17],
        ['county' => 'Kitui', 'tagged' => 1, 'dispatched' => 1, 'devices' => 44, 'status' => 'full', 'facilities' => 14],
        ['county' => 'Kwale', 'tagged' => 1, 'dispatched' => 1, 'devices' => 19, 'status' => 'full', 'facilities' => 6],
        ['county' => 'Laikipia', 'tagged' => null, 'dispatched' => null, 'devices' => null, 'status' => 'none', 'facilities' => null],
        ['county' => 'Lamu', 'tagged' => 1, 'dispatched' => 1, 'devices' => 15, 'status' => 'full', 'facilities' => 5],
        ['county' => 'Makueni', 'tagged' => 1, 'dispatched' => 1, 'devices' => 50, 'status' => 'full', 'facilities' => 16],
        ['county' => 'Mandera', 'tagged' => null, 'dispatched' => null, 'devices' => null, 'status' => 'none', 'facilities' => null],
        ['county' => 'Marsabit', 'tagged' => null, 'dispatched' => null, 'devices' => null, 'status' => 'none', 'facilities' => null],
        ['county' => 'Meru', 'tagged' => 1, 'dispatched' => null, 'devices' => null, 'status' => 'none', 'facilities' => null],
        ['county' => 'Migori', 'tagged' => 1, 'dispatched' => 1, 'devices' => 35, 'status' => 'full', 'facilities' => 11],
        // Tracker records a dispatch of 19 units to 5 facilities but a status
        // of Not Dispatched. Left exactly as recorded; flagged in the brief.
        ['county' => 'Mombasa', 'tagged' => 1, 'dispatched' => 1, 'devices' => 19, 'status' => 'none', 'facilities' => 5],
        ['county' => "Murang'a", 'tagged' => 1, 'dispatched' => 1, 'devices' => 27, 'status' => 'full', 'facilities' => 9],
        ['county' => 'Narok', 'tagged' => 1, 'dispatched' => 0, 'devices' => null, 'status' => 'none', 'facilities' => 5],
        ['county' => 'Nyamira', 'tagged' => 1, 'dispatched' => 1, 'devices' => 26, 'status' => 'full', 'facilities' => 8],
        ['county' => 'Nyandarua', 'tagged' => null, 'dispatched' => null, 'devices' => null, 'status' => 'none', 'facilities' => null],
        ['county' => 'Nyeri', 'tagged' => 1, 'dispatched' => 1, 'devices' => 22, 'status' => 'full', 'facilities' => 6],
        ['county' => 'Samburu', 'tagged' => null, 'dispatched' => null, 'devices' => null, 'status' => 'none', 'facilities' => null],
        ['county' => 'Siaya', 'tagged' => null, 'dispatched' => null, 'devices' => null, 'status' => 'none', 'facilities' => null],
        // Recorded as fully dispatched to 5 facilities, but with no unit count.
        ['county' => 'Taita Taveta', 'tagged' => 1, 'dispatched' => 1, 'devices' => null, 'status' => 'full', 'facilities' => 5],
        ['county' => 'Tana River', 'tagged' => null, 'dispatched' => null, 'devices' => null, 'status' => 'none', 'facilities' => null],
        ['county' => 'Tharaka-Nithi', 'tagged' => 1, 'dispatched' => 1, 'devices' => 17, 'status' => 'full', 'facilities' => 5],
        ['county' => 'Turkana', 'tagged' => null, 'dispatched' => null, 'devices' => null, 'status' => 'none', 'facilities' => null],
        ['county' => 'Wajir', 'tagged' => null, 'dispatched' => null, 'devices' => null, 'status' => 'none', 'facilities' => null],
    ],

];
