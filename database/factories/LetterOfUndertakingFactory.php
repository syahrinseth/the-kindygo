<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LetterOfUndertaking>
 */
class LetterOfUndertakingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => \App\Models\Tenant::factory(),
            'title' => fake()->sentence(),
            'description' => fake()->optional(0.7)->sentence(10),
            'content' => fake()->paragraphs(3, true),
            'version' => 1,
            'is_active' => false,
            'created_by' => \App\Models\User::factory(),
        ];
    }

    /**
     * Indicate that the letter is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }
}
