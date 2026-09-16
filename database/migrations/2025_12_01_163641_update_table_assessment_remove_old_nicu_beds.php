<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
         

            // Add new NBU columns
            $table->integer('nbu_nicu_beds')->default(0)->after('has_nbu');
            $table->integer('nbu_general_cots')->default(0)->after('nbu_nicu_beds');
            $table->integer('nbu_kmc_beds')->default(0)->after('nbu_general_cots');
            $table->text('nbu_comments')->nullable()->after('nbu_kmc_beds');

            // Add new Paediatric columns
            $table->integer('paediatric_general_beds')->default(0)->after('has_paediatric');
            $table->integer('paediatric_picu_beds')->default(0)->after('paediatric_general_beds');
            $table->text('paediatric_comments')->nullable()->after('paediatric_picu_beds');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            // Drop new columns
            $table->dropColumn([
                'nbu_nicu_beds',
                'nbu_general_cots',
                'nbu_kmc_beds',
                'nbu_comments',
                'paediatric_general_beds',
                'paediatric_picu_beds',
                'paediatric_comments',
            ]);

            // Restore old columns
            $table->integer('nbu_beds')->default(0);
            $table->integer('nbu_cots')->default(0);
            $table->integer('nbu_incubators')->default(0);
            $table->integer('nbu_radiant_warmers')->default(0);
            $table->integer('paediatric_beds')->default(0);
            $table->integer('paediatric_cots')->default(0);
        });
    }
};