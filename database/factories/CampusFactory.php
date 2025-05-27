<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Campus>
 */
class CampusFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->company() . ' Campus',
            'phone' => fake()->phoneNumber(),
            'email' => fake()->companyEmail(),
            'address_1' => fake()->streetAddress(),
            'address_2' => fake()->optional(0.3)->secondaryAddress(),
            'postal_code' => fake()->postcode(),
            'city' => fake()->city(),
            'state' => fake()->state(),
        ];
    }
}
