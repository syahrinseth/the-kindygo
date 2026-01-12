<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\QuotationItem>
 */
class QuotationItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $price = $this->faker->numberBetween(5000, 50000); // 50.00 to 500.00 in cents
        $quantity = $this->faker->numberBetween(1, 3);
        $discount = $this->faker->optional(0.3)->numberBetween(0, $price * $quantity * 0.2); // 30% chance of discount
        $total = ($price * $quantity) - ($discount ?? 0);

        $itemNames = [
            'Monthly Childcare Fee',
            'Weekly Care Package',
            'Extended Hours Service',
            'Meal Plan',
            'Transportation Service',
            'Learning Materials',
            'Field Trip Fee',
            'Special Activity',
            'Holiday Care',
            'Extra Curriculum',
        ];

        return [
            'name' => $this->faker->randomElement($itemNames),
            'description' => $this->faker->optional()->sentence(),
            'price' => $price,
            'quantity' => $quantity,
            'discount' => $discount ?? 0,
            'total' => $total,
            'period_start' => $this->faker->optional()->date(),
            'period_end' => $this->faker->optional()->date(),
        ];
    }
}
