<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rag_term_bridges', function (Blueprint $table) {
            $table->id();
            $table->string('trigger', 120)->unique();
            $table->json('synonyms')->nullable();
            $table->json('queries');
            $table->string('category', 120)->nullable();
            $table->unsignedSmallInteger('priority')->default(50);
            $table->boolean('enabled')->default(true)->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['priority', 'enabled']);
            $table->index('category');
        });

        DB::table('rag_term_bridges')->insert([
            [
                'trigger' => 'sepsis',
                'synonyms' => json_encode(['neonatal sepsis', 'danger signs', 'infection']),
                'queries' => json_encode([
                    'neonatal sepsis danger signs management',
                    'sepsis evaluation antibiotics antimicrobial therapy',
                    'sepsis urgent care newborn child',
                ]),
                'category' => 'neonatal care',
                'priority' => 90,
                'enabled' => true,
                'notes' => 'Keeps sepsis questions grounded in danger signs, evaluation, and urgent management sources.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'trigger' => 'hypothermia',
                'synonyms' => json_encode(['cold baby', 'low temperature', 'thermal care']),
                'queries' => json_encode([
                    'neonatal thermoregulation',
                    'hypothermia radiant warmer incubator temperature',
                    'newborn temperature thermal care',
                ]),
                'category' => 'newborn care',
                'priority' => 80,
                'enabled' => true,
                'notes' => 'Maps hypothermia wording to thermoregulation modules and warming equipment.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'trigger' => 'oxygen',
                'synonyms' => json_encode(['oxygen therapy', 'spo2', 'oxygen saturation', 'pulse oximetry']),
                'queries' => json_encode([
                    'oxygen therapy safe oxygen use pulse oximetry',
                    'oxygen delivery devices prescribing monitoring',
                    'oxygen saturation respiratory distress',
                ]),
                'category' => 'respiratory care',
                'priority' => 80,
                'enabled' => true,
                'notes' => 'Connects oxygen questions to pulse oximetry, delivery devices, prescribing, and monitoring.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('rag_term_bridges');
    }
};
