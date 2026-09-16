<?php

namespace Database\Seeders;

use App\Models\Scope;
use App\Models\ScopeRoleAccess;
use Illuminate\Database\Seeder;

class ScopeSeeder extends Seeder
{
    public function run(): void
    {
        $scopes = [
            [
                'slug'       => 'assessments',
                'label'      => 'Assessments',
                'icon'       => '🏥',
                'color'      => '#6366F1',
                'gradient'   => ['#6366F1', '#4F46E5'],
                'tabs'       => ['home', 'assessments', 'reports', 'profile'],
                'is_active'  => true,
                'sort_order' => 1,
                'roles'      => [
                    'super_admin', 'admin', 'division', 'national', 'division_lead',
                    'county', 'subcounty', 'facility_mentor', 'facility_mentor_lead',
                ],
            ],
            [
                'slug'       => 'mentorships',
                'label'      => 'Mentorships',
                'icon'       => '🎓',
                'color'      => '#0EA5E9',
                'gradient'   => ['#0EA5E9', '#0284C7'],
                'tabs'       => ['home', 'mentorships', 'classes', 'profile'],
                'is_active'  => true,
                'sort_order' => 2,
                'roles'      => [
                    'super_admin', 'admin', 'division', 'national', 'division_lead',
                    'national_mentor_lead', 'county', 'county_mentor_lead',
                    'subcounty', 'subcounty_mentor_lead', 'facility_mentor',
                    'facility_mentor_lead', 'spoke_mentor', 'spoke_mentor_lead', 'mentee',
                ],
            ],
            [
                'slug'       => 'trainings',
                'label'      => 'Trainings',
                'icon'       => '📋',
                'color'      => '#10B981',
                'gradient'   => ['#10B981', '#059669'],
                'tabs'       => ['home', 'trainings', 'profile'],
                'is_active'  => true,
                'sort_order' => 3,
                'roles'      => [
                    'super_admin', 'admin', 'division', 'national',
                    'division_lead', 'national_mentor_lead',
                ],
            ],
        ];

        foreach ($scopes as $data) {
            $roles = $data['roles'];
            unset($data['roles']);

            $scope = Scope::updateOrCreate(['slug' => $data['slug']], $data);

            // Full sync — safe to run multiple times
            $scope->roleAccess()->delete();
            foreach ($roles as $role) {
                ScopeRoleAccess::create(['scope_id' => $scope->id, 'role_name' => $role]);
            }
        }
    }
}
