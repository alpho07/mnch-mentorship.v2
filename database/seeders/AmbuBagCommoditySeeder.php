<?php

namespace Database\Seeders;

use App\Models\Commodity;
use App\Models\CommodityCategory;
use Illuminate\Database\Seeder;

/**
 * Splits the generic "Ambu Bag" commodity into three size-specific commodities:
 *   - Ambu Bag - Infant (250ml)
 *   - Ambu Bag - Child (500ml)
 *   - Ambu Bag - Adult (1500ml)
 *
 * Any applicable-department relationships on the original commodity are copied
 * to each replacement. The original is deactivated (not deleted) to preserve
 * historical response records.
 *
 * Safe to run multiple times (idempotent).
 *
 * Run with:
 *   php artisan db:seed --class=AmbuBagCommoditySeeder
 */
class AmbuBagCommoditySeeder extends Seeder
{
    private const SIZE_VARIANTS = [
        ['suffix' => 'Infant (250ml)',  'order_offset' => 0],
        ['suffix' => 'Child (500ml)',   'order_offset' => 1],
        ['suffix' => 'Adult (1500ml)', 'order_offset' => 2],
    ];

    public function run(): void
    {
        // Resolve Airway category — match by slug or name (case-insensitive)
        $category = CommodityCategory::where('slug', 'airway')
            ->orWhere('name', 'like', '%airway%')
            ->first();

        if (! $category) {
            $this->command->warn('Airway commodity category not found — skipping ambu bag split.');
            return;
        }

        // Find the generic ambu bag commodity (match by name keyword)
        $original = Commodity::where('commodity_category_id', $category->id)
            ->where('name', 'like', '%ambu%')
            ->where('name', 'not like', '%(250%')
            ->where('name', 'not like', '%(500%')
            ->where('name', 'not like', '%(1500%')
            ->first();

        // Collect department IDs to replicate on new commodities
        $departmentIds = $original
            ? $original->applicableDepartments()->pluck('assessment_department_id')->toArray()
            : [];

        // Base order: after the original if found, else high number
        $baseOrder = $original ? ($original->order ?? 50) : 50;

        foreach (self::SIZE_VARIANTS as $variant) {
            $name = 'Ambu Bag - ' . $variant['suffix'];
            $order = $baseOrder + $variant['order_offset'];

            $commodity = Commodity::firstOrCreate(
                ['commodity_category_id' => $category->id, 'name' => $name],
                [
                    'description' => $original?->description,
                    'order'       => $order,
                    'is_active'   => true,
                ]
            );

            // Sync departments from original (safe to re-sync)
            if ($departmentIds) {
                $commodity->applicableDepartments()->sync($departmentIds);
            }

            $this->command->info("✓ Commodity [{$commodity->id}] \"{$name}\" ready.");
        }

        // Deactivate the generic commodity (preserve history, hide from new assessments)
        if ($original) {
            if ($original->is_active) {
                $original->update(['is_active' => false]);
                $this->command->info("  Generic \"{$original->name}\" deactivated (historical data preserved).");
            } else {
                $this->command->info("  Generic \"{$original->name}\" already inactive.");
            }
        } else {
            $this->command->warn('  No generic Ambu Bag found to deactivate — variants created fresh.');
        }
    }
}
