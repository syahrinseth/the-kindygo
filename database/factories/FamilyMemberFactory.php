<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FamilyMember>
 */
class FamilyMemberFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => \App\Models\User::factory(),
            'relationship_type' => 'spouse',
            'name' => fake()->name(),
            'nric' => fake()->numerify('######-##-####'),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->safeEmail(),
            'occupation' => fake()->jobTitle(),
            'address' => fake()->streetAddress(),
            'address_2' => fake()->optional()->secondaryAddress(),
            'city' => fake()->city(),
            'postal_code' => fake()->postcode(),
            'state_code' => fake()->randomElement(['01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12', '13', '14', '15', '16']),
        ];
    }
}
