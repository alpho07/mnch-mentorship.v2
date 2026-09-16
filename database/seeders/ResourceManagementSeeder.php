<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ResourceManagementSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🏥 Starting MNICH & Diabetes Resource Management System seeding...');
        $this->command->newLine();

        // Check if we should include sample data
       // $withSamples = $this->command->option('with-samples') ?? true;

        try {
            DB::beginTransaction();

            // Seed basic reference data first
           /*$this->command->info('📚 Seeding Resource Types...');
            $this->call(ResourceTypeSeeder::class);
            $this->showProgress('Resource Types', \App\Models\ResourceType::count());

            $this->command->info('📁 Seeding Resource Categories...');
            $this->call(ResourceCategorySeeder::class);
            $this->showProgress('Categories', \App\Models\ResourceCategory::count());

            $this->command->info('🏷️ Seeding Tags...');
            $this->call(TagSeeder::class);
            $this->showProgress('Tags', \App\Models\Tag::count());

            $this->command->info('👥 Seeding Access Groups...');
            $this->call(AccessGroupSeeder::class);
            $this->showProgress('Access Groups', \App\Models\AccessGroup::count());*/

            // Seed sample resources if requested

            $this->command->info('📄 Seeding Sample MNICH & Diabetes Resources...');
            $this->call(SampleResourceSeeder::class);
            $this->showProgress('Sample Resources', \App\Models\Resource::count());
            DB::commit();

            $this->command->newLine();
          //  $this->showSuccessMessage($withSamples);
        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('❌ Seeding failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Show progress for each seeder
     */
    private function showProgress(string $type, int $count): void
    {
        $this->command->info("   ✅ {$count} {$type} created successfully");
    }

    /**
     * Show final success message with summary
     */
    private function showSuccessMessage(bool $withSamples): void
    {
        $this->command->info('🎉 MNICH & Diabetes Resource Management System seeded successfully!');
        $this->command->newLine();

        // Show detailed summary
        $this->command->info('📊 <fg=cyan>SEEDING SUMMARY:</fg=cyan>');
        $this->command->table(
            ['Component', 'Count', 'Description'],
            [
                ['Resource Types', \App\Models\ResourceType::count(), 'PDF, Video, Manual, Protocol, etc.'],
                ['Categories', \App\Models\ResourceCategory::count(), 'MNICH & Diabetes focused categories'],
                ['Tags', \App\Models\Tag::count(), 'Clinical and healthcare specific tags'],
                ['Access Groups', \App\Models\AccessGroup::count(), 'Role-based healthcare access groups'],
                ['Sample Resources', $withSamples ? \App\Models\Resource::count() : 0, 'MNICH & Diabetes sample content'],
            ]
        );

        $this->command->newLine();
        $this->command->info('🎯 <fg=yellow>HEALTHCARE FOCUS AREAS:</fg=yellow>');
        $this->command->info('   🤱 Maternal Health - Antenatal care, delivery, postpartum');
        $this->command->info('   👶 Newborn Care - Essential care, breastfeeding, resuscitation');
        $this->command->info('   🍼 Infant Health - Growth monitoring, immunization, nutrition');
        $this->command->info('   👦 Child Health - Development, adolescent care, IMCI');
        $this->command->info('   🩺 Diabetes Management - Type 1/2, gestational, prevention');

        $this->command->newLine();
        $this->command->info('🚀 <fg=green>NEXT STEPS:</fg=green>');

        // Check if admin user exists
        $adminExists = \App\Models\User::exists();
        if (!$adminExists) {
            $this->command->warn('   ⚠️  No admin user found. Create one with:');
            $this->command->info('      php artisan make:filament-user');
        } else {
            $this->command->info('   ✅ Admin user exists - you can log in');
        }

        $this->command->info('   🌐 Access admin panel at: <fg=blue>/admin</fg=blue>');
        $this->command->info('   📝 Start creating MNICH & Diabetes resources');
        $this->command->info('   👥 Assign users to appropriate access groups');

        if (!$withSamples) {
            $this->command->newLine();
            $this->command->info('💡 <fg=yellow>TIP:</fg=yellow> Run with sample data next time:');
            $this->command->info('   php artisan db:seed --class=ResourceManagementSeeder --with-samples');
        }

        $this->command->newLine();
        $this->command->info('📚 <fg=magenta>SAMPLE RESOURCES AVAILABLE:</fg=magenta>');
        if ($withSamples) {
            $this->command->info('   ✅ Essential Newborn Care Protocol');
            $this->command->info('   ✅ Antenatal Care Guidelines (4-visit model)');
            $this->command->info('   ✅ Type 2 Diabetes Management');
            $this->command->info('   ✅ IMCI Chart Booklet');
            $this->command->info('   ✅ Breastfeeding Support Guide');
            $this->command->info('   ✅ Gestational Diabetes Management');
        } else {
            $this->command->info('   💡 Run with --with-samples to get sample MNICH & Diabetes content');
        }

        $this->command->newLine();
        $this->command->info('<fg=green,bg=black> 🎉 Your MNICH & Diabetes Resource Center is ready! 🎉 </fg=green,bg=black>');
    }
}
