<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Additive: nullable program_area on curriculum building blocks; existing rows are newborn & child. */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['modules', 'topics', 'methodologies', 'activities'] as $table) {
            if (! Schema::hasColumn($table, 'program_area')) {
                Schema::table($table, fn (Blueprint $t) => $t->string('program_area', 32)->nullable()->index());
            }
            DB::table($table)->whereNull('program_area')->update(['program_area' => 'newborn_child']);
        }
    }

    public function down(): void {}
};
