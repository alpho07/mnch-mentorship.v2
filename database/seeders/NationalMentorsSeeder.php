<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\User;
use Spatie\Permission\Models\Role;

class NationalMentorsSeeder extends Seeder {

    public function run(): void {
        // --------------------------------------------------
        // Ensure Roles Exist
        // --------------------------------------------------
        $nationalRole = Role::firstOrCreate(['name' => 'national_mentor']);
        $facilityRole = Role::firstOrCreate(['name' => 'facility_mentor']);

        // --------------------------------------------------
        // Mentor Dataset
        // --------------------------------------------------
        $mentors = [
            ['DR. JULLIET OMWOHA', 'NATIONAL', '0725764062', 'dr.omwoha@gmail.com'],
            ['ALLAN GOVOGA', 'NATIONAL', '0722235670', 'agovoga@gmail.com'],
            ['WINNIE MUHORO', 'NATIONAL', '0723508070', 'winniemuhoro@gmail.com'],
            ['NAOMY ARUSSEY', 'NATIONAL', '0723536009', 'narusey04@gmail.com'],
            ['ALEX MUTUA', 'NATIONAL', '0722462126', 'amutua789@gmail.com'],
            ['RICHARD KIMENYE', 'NATIONAL', '0722663962', 'richaddmk@gmail.com'],
            ['DR. DEBORAH OKUMU', 'NATIONAL', '0722612924', 'deborahokumu@gmail.com'],
            ['DR. EMELDA MAGURO', 'ATHI RIVER LEVEL 4', '0724360873', 'emelda.manguro@gmail.com'],
            ['DR. FELISTAS MAKHOHA', 'BUNGOMA COUNTY REFERRAL HOSPITAL', '0722622651', 'drmakfelis@gmail.com'],
            ['DR. MAURINE IKOL', 'KISII TEACHING AND REFERRAL HOSPITAL', '0722451059', 'ikolmourine@gmail.com'],
            ['DR. EINSTEIN KIBET', 'MWAI KIBAKI HOSPITAL', '0723810824', 'einkibetz@gmail.com'],
            ['DR. NICK MUTISYA', 'MURANGA LEVEL 5 HOSPITAL', '0712412129', 'nickkioko15@gmail.com'],
            ['DR. LEAH MORIASI', 'ARMED FORCES MEMORIAL HOSPITAL', '0718085150', 'leagesa2004@yahoo.com'],
            ['DR. ROSELYNE MALANGACHI', 'KAKAMEGA COUNTY GENERAL HOSPITAL', '0722971501', 'rozzymalangachi@gmail.com'],
            ['DR. MIRIAM WERU', 'KENYATTA NATIONAL HOSPITAL', '0721377605', 'senteruaweru@gmail.com'],
            ['PATRICK TOO', 'KENYATTA NATIONAL HOSPITAL', '0713658137', 'patrickkimtos@gmail.com'],
            ['SIMON PKEMOI', 'MOI TEACHING AND REFERRAL HOSPITAL', '0710318800', 'pkemoi32@gmail.com'],
            ['JOSEPHINE KARORI', 'NATIONAL', '0725795221', 'josephinekarori25@gmail.com'],
            ['DR. MARYANN WACHU', 'LONGISA COUNTRY REFERRAL HOSPITAL', '0720763815', 'maryannewachu@gmail.com'],
            ['DR. MARIA GERALD', 'HOMABAY COUNTY TEACHING AND REFERRAL HOSPITAL', '0727361366', 'ogayag11@gmail.com'],
            ['DR. RACHAEL KANGUHA', 'CHUKA LEVEL 5 HOSPITAL', '0723787178', 'rkanguha@gmail.com'],
            ['DR. ABDULLAHI HASSAN', 'WAJIR COUNTY REFERRAL HOSPITAL', '0722243428', 'abdullahihssn@gmail.com'],
            ['DR. AUDREY CHEPKEMOI', 'MOI TEACHING AND REFERRAL HOSPITAL', '0725678364', 'audreychepkemoi@gmail.com'],
            ['JASON KIRUJA', 'KENYATTA NATIONAL HOSPITAL', '0721966220', 'gjkiruja@gmail.com'],
            ['CAROLYNE OUMA', 'KENYATTA NATIONAL HOSPITAL', '0722409591', 'nadongoouma@gmail.com'],
            ['BECKY BURETI', 'KENYATTA NATIONAL HOSPITAL', '0726871840', 'buretibecky@gmail.com'],
            ['GRIFFIN ANASI', 'KENYATTA UNIVERSITY TEACHING, REFERRAL AND RESEARCH HOSPITAL', '0724660888', 'griffin.anasi@kutrrh.go.ke'],
            ['BERNADINE LUSWETI', 'THIKA LEVEL 5 HOSPITAL', '0722888228', 'bmuthumbi@gmail.com'],
            ['DR. JOY ODHIAMBO', 'LUMUMBA SUBCOUNTY HOSPITAL', '0727513746', 'joy.adhyambo@gmail.com'],
            ['KAREN AURA', 'NATIONAL', '0727999873', 'owendeka2010@gmail.com'],
            ['BRIAN DEMESI', 'VIHIGA COUNTY REFFERAL HOSPITAL', '0705320341', 'briandemesi@gmail.com'],
            ['DR. WINNIE SAUMU', 'CHUKA LEVEL 5 HOSPITAL', '0723317632', 'saumubundi@gmail.com'],
            ['DR. ESTHER NJERI', 'NYERI COUNTY REFERRAL HOSPITAL', '0723618696', 'esther.snjeri09@gmail.com'],
            ['DR. PURITY MUHORO', 'SAMBURU COUNTY REFERRAL HOSPITAL', '0726671571', 'puritymuhoro@gmail.com'],
            ['DR. EDITH MWASI', 'MSAMBWENI COUNTY REFERRAL HOSPITAL', '0721237653', 'edithmwasi@gmail.com'],
        ];

        // --------------------------------------------------
        // Insert Users
        // --------------------------------------------------
        foreach ($mentors as $mentor) {

            [$name, $location, $phone, $email] = $mentor;

            $role = strtoupper(trim($location)) === 'NATIONAL' ? $nationalRole : $facilityRole;

            $user = User::updateOrCreate(
                    ['email' => strtolower(trim($email))],
                    [
                        'name' => trim($name),
                        'phone' => preg_replace('/\D/', '', $phone),
                        'email_verified_at' => now(),
                        'password' => Hash::make('123456'),
                        'remember_token' => Str::random(10),
                        'status' => true,
                    ]
            );

            $user->syncRoles([$role]);
        }
    }
}
