<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $renames = [
        'Assessor'         => 'assessor',
        'Inventory'        => 'inventory',
        'Reporting'        => 'reporting',
        'Resource Manager' => 'resource_manager',
        'Team_Lead'        => 'team_lead',
    ];

    public function up(): void
    {
        foreach ($this->renames as $old => $new) {
            DB::table('roles')
                ->where('name', $old)
                ->where('guard_name', 'web')
                ->update(['name' => $new]);
        }
    }

    public function down(): void
    {
        foreach ($this->renames as $old => $new) {
            DB::table('roles')
                ->where('name', $new)
                ->where('guard_name', 'web')
                ->update(['name' => $old]);
        }
    }
};
