<?php

namespace Database\Factories;

use App\Enums\ChildEnrolmentBilledEvery;
use App\Enums\ChildEnrolmentStatus;
use App\Enums\ChildEnrolmentType;
use App\Models\Centre;
use App\Models\Child;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ChildEnrolment>
 */
class ChildEnrolmentFactory extends Factory
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
            'status' => $this->faker->randomElement(ChildEnrolmentStatus::cases()),
            'billed_every' => $this->faker->randomElement(ChildEnrolmentBilledEvery::cases()),
            'date_start' => $startDate,
            'date_end' => $endDate,
            'type' => $this->faker->randomElement(ChildEnrolmentType::cases()),
        ];
    }

    /**
     * Indicate that the enrolment is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ChildEnrolmentStatus::ACTIVE,
        ]);
    }

    /**
     * Indicate that the enrolment is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ChildEnrolmentStatus::INACTIVE,
        ]);
    }

    /**
     * Indicate that the enrolment is for full-time care.
     */
    public function fullTime(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ChildEnrolmentType::FULL_TIME,
        ]);
    }

    /**
     * Indicate that the enrolment is for part-time care.
     */
    public function partTime(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ChildEnrolmentType::PART_TIME,
        ]);
    }

    /**
     * Indicate that the enrolment is billed monthly.
     */
    public function monthly(): static
    {
        return $this->state(fn (array $attributes) => [
            'billed_every' => ChildEnrolmentBilledEvery::MONTHLY,
        ]);
    }
}
