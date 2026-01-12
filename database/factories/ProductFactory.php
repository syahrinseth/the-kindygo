<?php

namespace Database\Factories;

use App\Enums\ProductPriority;
use App\Enums\ProductStatus;
use App\Enums\ProductType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $types = ProductType::cases();
        $statuses = ProductStatus::cases();
        $priorities = ProductPriority::cases();

        $serviceNames = [
            'Monthly Childcare Fee',
            'Weekly Childcare Service',
            'Daily Care Package',
            'Extended Hours Care',
            'Holiday Care Service',
            'Special Events Care',
            'Transportation Service',
            'Meal Plan Service',
            'Learning Activity Package',
            'Field Trip Service',
        ];

        return [
            'code' => 'PROD-'.strtoupper($this->faker->bothify('###??')),
            'name' => $this->faker->randomElement($serviceNames),
            'status' => $this->faker->randomElement($statuses)->value,
            'type' => $this->faker->randomElement($types)->value,
            'priority' => $this->faker->randomElement($priorities)->value,
        ];
    }
}
