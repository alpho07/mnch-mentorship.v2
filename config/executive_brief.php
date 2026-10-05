<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Issuing office
    |--------------------------------------------------------------------------
    |
    | Masthead strings for the executive brief. Kept in config so the brief
    | can be re-badged without touching the view.
    |
    */

    'ministry' => 'Ministry of Health — Republic of Kenya',
    'division' => 'Division of Newborn & Child Health',
    'programme' => 'National Newborn & Child Health Mentorship Programme',
    'audience' => 'Mentorship Brief',
    'contact' => 'newbornchild@health.go.ke',

    /*
    |--------------------------------------------------------------------------
    | Definition of mentorship
    |--------------------------------------------------------------------------
    |
    | Carried verbatim as agreed by the Division — the brief opens with it so
    | every reader works from the same definition. Edit here, not in the view.
    |
    */

    'definition' => 'Mentorship is a structured, continuous, workplace-based capacity-building approach in which '
        .'experienced healthcare professionals provide guidance, coaching, and supportive supervision to strengthen '
        .'the knowledge, clinical skills, confidence, and competence of healthcare workers. It reinforces practical '
        .'skills and promotes the delivery of safe, standardized, and evidence-based care for the prevention, early '
        .'detection, and management of common newborn and childhood illnesses and emergencies, ultimately '
        .'contributing to better newborn and child health outcomes.',

    /*
    |--------------------------------------------------------------------------
    | Reporting window
    |--------------------------------------------------------------------------
    |
    | The brief reports on mentorship delivered between these two dates. The
    | month-by-month table is built by walking this range, so extending the
    | programme is a matter of moving 'end'.
    |
    */

    'window' => [
        'start' => '2026-04-01',
        'end' => '2026-09-30', 
    ],

    /*
    |--------------------------------------------------------------------------
    | Priority counties
    |--------------------------------------------------------------------------
    |
    | PROVISIONAL. The 25 counties the mentorship programme is accountable
    | for — derived from neonatal mortality burden and annual delivery
    | volume, not from an official Ministry gazette. Replace this array with
    | the gazetted RRI list when it is issued; every coverage figure in the
    | brief (reached, gap, percentage) is computed from it, so no other file
    | needs to change.
    |
    | Names must match the `counties.name` column exactly.
    |
    */

    'priority_counties' => [
        'Bungoma',
        'Busia',
        'Elgeyo Marakwet',
        'Garissa',
        'Homa Bay',
        'Kakamega',
        'Kilifi',
        'Kisumu',
        'Kitui',
        'Lamu',
        'Machakos',
        'Mandera',
        'Migori',
        'Mombasa',
        "Murang'a",
        'Nairobi',
        'Nakuru',
        'Nyeri',
        'Siaya',
        'Trans Nzoia',
        'Turkana',
        'Uasin Gishu',
        'Vihiga',
        'Wajir',
        'West Pokot',
    ],

    /*
    |--------------------------------------------------------------------------
    | Mortality-weighted module priority
    |--------------------------------------------------------------------------
    |
    | Maps the leading causes of newborn and child death to the curriculum
    | modules that address them, so the brief can say which of the modules
    | already in use deserve priority rather than simply reporting uptake.
    |
    | 'rank' is the cause's standing in the national burden of newborn and
    | under-five mortality (1 = highest). It encodes the established ordering
    | of causes — prematurity, intrapartum events and infection for newborns;
    | pneumonia, diarrhoea and malnutrition for children — and is NOT derived
    | from data held on this platform. Revise it against the current KDHS or
    | civil registration figures before the brief is issued externally.
    |
    | 'modules' are matched case-insensitively against the substring of a
    | program module's name, so curriculum renumbering does not break it.
    |
    */

    'mortality_priorities' => [
        [
            'cause' => 'Prematurity & low birth weight',
            'rank' => 1,
            'modules' => [
                'Care of the Small and Sick',
                'Thermoregulation',
                'Newborn Feeding',
                'Hypoglycaemia',
            ],
        ],
        [
            'cause' => 'Birth asphyxia',
            'rank' => 2,
            'modules' => [
                'Newborn Resuscitation',
                'Essential Newborn Care',
            ],
        ],
        [
            'cause' => 'Newborn sepsis & infection',
            'rank' => 3,
            'modules' => [
                'Danger Signs',
                'Infection Prevention',
            ],
        ],
        [
            'cause' => 'Pneumonia & respiratory illness',
            'rank' => 4,
            'modules' => [
                'Respiratory Distress',
                'Oxygen Therapy',
                'Basic Life Support',
            ],
        ],
        [
            'cause' => 'Diarrhoea & dehydration',
            'rank' => 5,
            'modules' => [
                'Dehydration',
                'Triage',
            ],
        ],
        [
            'cause' => 'Acute malnutrition',
            'rank' => 6,
            'modules' => [
                'Malnutrition',
            ],
        ],
    ],

];
