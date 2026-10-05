<?php

/*
|--------------------------------------------------------------------------
| Skills lab readiness and equipment
|--------------------------------------------------------------------------
|
| Programme status for the 29 sites in the mentorship roll-out, as recorded
| by the Division. Held in config rather than the database because it is a
| manually maintained tracker revised as sites progress — the per-device
| records, with their asset tags, live in the skills_lab_devices table.
|
| Column meanings
|   assessment / sensitization / rollout
|                1 done, 0 not done, null not reported
|   lab          'functional' a working skills lab
|                'room'       a room identified but not yet equipped
|                'none'       neither
|                null         not reported
|   preemie / neo / anne / air
|                units held; null where the site has not reported
|   pox          'yes' | 'partial' | 'no' | null — pulse oximeters dispatched
|   poxTotal     units received; null where none or not reported
|   active       whether mentorship is currently running at the site
|
| Neo Natalie is two units per equipped site: the issue is a pair, which the
| source tracker records on a single line.
|
| null is meaningful here and is NOT the same as 0. A null means the site has
| not reported; a 0 means it reported holding none. Any count that conflates
| the two will understate the reporting gap.
|
*/

return [

    'sites' => [
        ['county' => 'Nairobi', 'facility' => 'Mama Lucy Kibaki County Hospital', 'mfl' => '17411', 'assessment' => 1, 'sensitization' => 1, 'rollout' => 1, 'lab' => 'functional', 'preemie' => 1, 'neo' => 2, 'anne' => 1, 'air' => 2, 'pox' => 'partial', 'poxTotal' => 15, 'active' => true],
        ['county' => 'Nairobi', 'facility' => 'Pumwani Maternity Hospital', 'mfl' => '13156', 'assessment' => 1, 'sensitization' => 1, 'rollout' => 1, 'lab' => 'functional', 'preemie' => 0, 'neo' => 0, 'anne' => 0, 'air' => 2, 'pox' => 'partial', 'poxTotal' => null, 'active' => false],
        ['county' => 'Nairobi', 'facility' => 'Mbagathi County Hospital', 'mfl' => '13080', 'assessment' => 1, 'sensitization' => 1, 'rollout' => 1, 'lab' => 'room', 'preemie' => 0, 'neo' => 0, 'anne' => 0, 'air' => 0, 'pox' => 'partial', 'poxTotal' => null, 'active' => false],
        ['county' => 'Machakos', 'facility' => 'Machakos County Referral Hospital', 'mfl' => '12438', 'assessment' => 1, 'sensitization' => 1, 'rollout' => 1, 'lab' => 'room', 'preemie' => 1, 'neo' => 2, 'anne' => 1, 'air' => 2, 'pox' => 'yes', 'poxTotal' => 47, 'active' => true],
        ['county' => "Murang'a", 'facility' => "Murang'a County Referral Hospital", 'mfl' => '10777', 'assessment' => 1, 'sensitization' => 1, 'rollout' => 1, 'lab' => 'room', 'preemie' => 1, 'neo' => 2, 'anne' => 1, 'air' => 2, 'pox' => 'no', 'poxTotal' => 27, 'active' => true],
        ['county' => 'Kiambu', 'facility' => 'Kiambu County Referral Hospital', 'mfl' => '10539', 'assessment' => 1, 'sensitization' => 1, 'rollout' => 1, 'lab' => 'none', 'preemie' => null, 'neo' => null, 'anne' => null, 'air' => null, 'pox' => 'no', 'poxTotal' => null, 'active' => false],
        ['county' => 'Kiambu', 'facility' => 'Thika Level 5 Hospital', 'mfl' => '11094', 'assessment' => 1, 'sensitization' => 1, 'rollout' => 1, 'lab' => 'room', 'preemie' => null, 'neo' => null, 'anne' => null, 'air' => null, 'pox' => null, 'poxTotal' => null, 'active' => false],
        ['county' => 'Nakuru', 'facility' => 'Nakuru County Referral Hospital', 'mfl' => '15288', 'assessment' => 1, 'sensitization' => 1, 'rollout' => 1, 'lab' => 'functional', 'preemie' => 1, 'neo' => 2, 'anne' => 1, 'air' => 2, 'pox' => 'yes', 'poxTotal' => 55, 'active' => true],
        ['county' => 'Trans Nzoia', 'facility' => 'Wamalwa Kijana Referral Hospital', 'mfl' => '27376', 'assessment' => 1, 'sensitization' => 1, 'rollout' => 1, 'lab' => 'functional', 'preemie' => 1, 'neo' => 2, 'anne' => 1, 'air' => 2, 'pox' => 'yes', 'poxTotal' => 26, 'active' => false],
        ['county' => 'Elgeyo Marakwet', 'facility' => 'Iten County Referral Hospital', 'mfl' => '14586', 'assessment' => 1, 'sensitization' => 1, 'rollout' => 1, 'lab' => 'room', 'preemie' => 1, 'neo' => 2, 'anne' => 1, 'air' => 2, 'pox' => 'yes', 'poxTotal' => 24, 'active' => true],
        ['county' => 'West Pokot', 'facility' => 'Kapenguria County Referral Hospital', 'mfl' => '14701', 'assessment' => 1, 'sensitization' => 1, 'rollout' => 1, 'lab' => 'room', 'preemie' => 1, 'neo' => 2, 'anne' => 1, 'air' => 2, 'pox' => 'yes', 'poxTotal' => 17, 'active' => true],
        ['county' => 'Uasin Gishu', 'facility' => 'Moi Teaching and Referral Hospital', 'mfl' => '15204', 'assessment' => 1, 'sensitization' => 1, 'rollout' => 0, 'lab' => 'functional', 'preemie' => 0, 'neo' => 0, 'anne' => 0, 'air' => 0, 'pox' => 'yes', 'poxTotal' => 31, 'active' => false],
        ['county' => 'Uasin Gishu', 'facility' => 'Mama Rachel Ruto Maternity Hospital', 'mfl' => '15779', 'assessment' => 1, 'sensitization' => 1, 'rollout' => 1, 'lab' => 'room', 'preemie' => 1, 'neo' => 2, 'anne' => 1, 'air' => 2, 'pox' => null, 'poxTotal' => null, 'active' => true],
        ['county' => 'Baringo', 'facility' => 'Baringo County Referral Hospital', 'mfl' => '14607', 'assessment' => 1, 'sensitization' => 1, 'rollout' => 1, 'lab' => 'room', 'preemie' => null, 'neo' => null, 'anne' => null, 'air' => null, 'pox' => 'yes', 'poxTotal' => 24, 'active' => false],
        ['county' => 'Vihiga', 'facility' => 'Vihiga County Referral Hospital', 'mfl' => '16157', 'assessment' => 1, 'sensitization' => 1, 'rollout' => 1, 'lab' => 'functional', 'preemie' => 1, 'neo' => 2, 'anne' => 1, 'air' => 2, 'pox' => 'yes', 'poxTotal' => 19, 'active' => true],
        ['county' => 'Kakamega', 'facility' => 'Kakamega County Referral Hospital', 'mfl' => '15915', 'assessment' => 1, 'sensitization' => 1, 'rollout' => 1, 'lab' => 'functional', 'preemie' => 1, 'neo' => 2, 'anne' => 1, 'air' => 2, 'pox' => 'partial', 'poxTotal' => 38, 'active' => true],
        ['county' => 'Bungoma', 'facility' => 'Bungoma County Referral Hospital', 'mfl' => '15828', 'assessment' => 1, 'sensitization' => 1, 'rollout' => 1, 'lab' => 'room', 'preemie' => 1, 'neo' => 2, 'anne' => 1, 'air' => 2, 'pox' => 'yes', 'poxTotal' => 28, 'active' => true],
        ['county' => 'Busia', 'facility' => 'Busia County Referral Hospital', 'mfl' => '15834', 'assessment' => 1, 'sensitization' => 1, 'rollout' => 1, 'lab' => 'room', 'preemie' => null, 'neo' => null, 'anne' => null, 'air' => null, 'pox' => 'no', 'poxTotal' => null, 'active' => true],
        ['county' => 'Homa Bay', 'facility' => 'Homabay County Referral Hospital', 'mfl' => '13608', 'assessment' => 1, 'sensitization' => 1, 'rollout' => 1, 'lab' => 'functional', 'preemie' => null, 'neo' => null, 'anne' => null, 'air' => null, 'pox' => 'partial', 'poxTotal' => 39, 'active' => true],
        ['county' => 'Kitui', 'facility' => 'Kitui County Referral Hospital', 'mfl' => '12366', 'assessment' => 1, 'sensitization' => 1, 'rollout' => 1, 'lab' => 'room', 'preemie' => null, 'neo' => null, 'anne' => null, 'air' => null, 'pox' => 'no', 'poxTotal' => null, 'active' => false],
        ['county' => 'Makueni', 'facility' => 'Makueni County Referral Hospital', 'mfl' => '12457', 'assessment' => 1, 'sensitization' => 1, 'rollout' => 1, 'lab' => 'room', 'preemie' => null, 'neo' => null, 'anne' => null, 'air' => null, 'pox' => 'no', 'poxTotal' => null, 'active' => false],
        ['county' => 'Nyeri', 'facility' => 'Karatina County Referral Hospital', 'mfl' => '10485', 'assessment' => 1, 'sensitization' => 1, 'rollout' => 1, 'lab' => 'room', 'preemie' => null, 'neo' => null, 'anne' => null, 'air' => null, 'pox' => 'no', 'poxTotal' => null, 'active' => true],
        ['county' => 'Lamu', 'facility' => 'Lamu County Referral Hospital', 'mfl' => '11512', 'assessment' => 1, 'sensitization' => 1, 'rollout' => 1, 'lab' => 'room', 'preemie' => null, 'neo' => null, 'anne' => null, 'air' => null, 'pox' => 'no', 'poxTotal' => null, 'active' => false],
        ['county' => 'Kwale', 'facility' => 'Msambweni County Referral Hospital', 'mfl' => '11655', 'assessment' => 1, 'sensitization' => 1, 'rollout' => 1, 'lab' => 'room', 'preemie' => null, 'neo' => null, 'anne' => null, 'air' => null, 'pox' => 'yes', 'poxTotal' => 19, 'active' => false],
        ['county' => 'Nandi', 'facility' => 'Kapsabet County Referral Hospital', 'mfl' => '14749', 'assessment' => 1, 'sensitization' => 1, 'rollout' => 1, 'lab' => 'none', 'preemie' => 1, 'neo' => 2, 'anne' => 1, 'air' => 2, 'pox' => 'yes', 'poxTotal' => 29, 'active' => false],
        ['county' => 'Tharaka Nithi', 'facility' => 'Chuka County Referral Hospital', 'mfl' => '11973', 'assessment' => 1, 'sensitization' => 1, 'rollout' => 1, 'lab' => 'none', 'preemie' => null, 'neo' => null, 'anne' => null, 'air' => null, 'pox' => 'no', 'poxTotal' => null, 'active' => false],
        ['county' => 'Siaya', 'facility' => 'Siaya County Referral Hospital', 'mfl' => '14080', 'assessment' => 1, 'sensitization' => 1, 'rollout' => 1, 'lab' => 'functional', 'preemie' => null, 'neo' => null, 'anne' => null, 'air' => null, 'pox' => 'no', 'poxTotal' => null, 'active' => false],
        ['county' => 'Kisumu', 'facility' => 'Kisumu County Referral Hospital', 'mfl' => '13704', 'assessment' => 1, 'sensitization' => 1, 'rollout' => 1, 'lab' => 'room', 'preemie' => null, 'neo' => null, 'anne' => null, 'air' => null, 'pox' => 'yes', 'poxTotal' => 55, 'active' => false],
        ['county' => 'Kisumu', 'facility' => 'Jaramogi Oginga Odinga TRH', 'mfl' => '13939', 'assessment' => 0, 'sensitization' => 0, 'rollout' => 0, 'lab' => null, 'preemie' => null, 'neo' => null, 'anne' => null, 'air' => null, 'pox' => null, 'poxTotal' => null, 'active' => false],
    ],

];
