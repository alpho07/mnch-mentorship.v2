<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Additive data tag: every assessment template that exists today belongs to newborn & child. */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('assessment_types')->whereNull('program_area')->update(['program_area' => 'newborn_child']);
    }

    public function down(): void {}
};
