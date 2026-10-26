<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Activities are EmONC-only: retag the rows tagged newborn_child by the previous default. */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('activities')->where('program_area', 'newborn_child')->update(['program_area' => 'emonc']);
    }

    public function down(): void {}
};
