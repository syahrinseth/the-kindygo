<?php

namespace Database\Factories;

use App\Models\Campus;
use App\Models\Tenant;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Centre>
 */
class CentreFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->company() . ' Centre';
        
        return [
            'tenant_id' => Tenant::factory(),
            'campus_id' => Campus::factory(),
            'slug' => Str::slug($name),
            'name' => $name,
            'status' => 'active',
            'phone' => fake()->phoneNumber(),
            'email' => fake()->companyEmail(),
            'address_1' => fake()->streetAddress(),
            'address_2' => fake()->optional(0.3)->secondaryAddress(),
            'postal_code' => fake()->postcode(),
            'city' => fake()->city(),
            'state' => fake()->state(),
        ];
    }
    
    /**
     * Indicate that the centre belongs to a specific tenant.
     */
    public function forTenant(Tenant $tenant): Factory
    {
        return $this->state(function (array $attributes) use ($tenant) {
            return [
                'tenant_id' => $tenant->id,
            ];
        });
    }
    
    /**
     * Indicate that the centre belongs to a specific campus.
     */
    public function forCampus(Campus $campus): Factory
    {
        return $this->state(function (array $attributes) use ($campus) {
            return [
                'tenant_id' => $campus->tenant_id,
                'campus_id' => $campus->id,
            ];
        });
    }
}
