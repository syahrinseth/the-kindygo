<?php

namespace Database\Factories;

use App\Models\Child;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\Centre;
use App\Enums\ChildEnrollmentStatus;
use App\Enums\ChildEnrollmentBilledEvery;
use App\Enums\ChildEnrollmentType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ChildEnrollment>
 */
class ChildEnrollmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = $this->faker->dateTimeBetween('-1 year', '+1 month');
        $endDate = $this->faker->optional(0.7)->dateTimeBetween($startDate, '+2 years');

        return [
            'tenant_id' => Tenant::factory(),
            'centre_id' => Centre::factory(),
            'child_id' => Child::factory(),
            'product_id' => Product::factory(),
            'status' => $this->faker->randomElement(ChildEnrollmentStatus::cases()),
            'billed_every' => $this->faker->randomElement(ChildEnrollmentBilledEvery::cases()),
            'date_start' => $startDate,
            'date_end' => $endDate,
            'type' => $this->faker->randomElement(ChildEnrollmentType::cases()),
        ];
    }

    /**
     * Indicate that the enrollment is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ChildEnrollmentStatus::ACTIVE,
        ]);
    }

    /**
     * Indicate that the enrollment is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ChildEnrollmentStatus::INACTIVE,
        ]);
    }

    /**
     * Indicate that the enrollment is for full-time care.
     */
    public function fullTime(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ChildEnrollmentType::FULL_TIME,
        ]);
    }

    /**
     * Indicate that the enrollment is for part-time care.
     */
    public function partTime(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ChildEnrollmentType::PART_TIME,
        ]);
    }

    /**
     * Indicate that the enrollment is billed monthly.
     */
    public function monthly(): static
    {
        return $this->state(fn (array $attributes) => [
            'billed_every' => ChildEnrollmentBilledEvery::MONTHLY,
        ]);
    }
}
