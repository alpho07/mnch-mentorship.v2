<?php

namespace Database\Factories;

use App\Models\County;
use App\Models\Facility;
use App\Models\FacilityType;
use App\Models\Subcounty;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Facility>
 */
class FacilityFactory extends Factory
{
    protected $model = Facility::class;

    public function definition(): array
    {
        $county = County::factory()->create();
        $subcounty = Subcounty::create(['name' => fake()->city(), 'county_id' => $county->id]);
        $facilityType = FacilityType::firstOrCreate(['name' => 'Health Centre']);

        return [
            'name'             => fake()->company() . ' Health Centre',
            'subcounty_id'     => $subcounty->id,
            'facility_type_id' => $facilityType->id,
            'mfl_code'         => fake()->numerify('#####'),
            'is_hub'           => false,
            'is_central_store' => false,
        ];
    }
}
