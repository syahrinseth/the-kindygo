<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\Centre;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $totalItems = fake()->numberBetween(1, 10);
        $totalAmount = fake()->numberBetween(1000, 100000);
        $totalDiscounts = fake()->numberBetween(0, $totalAmount / 10);
        $total = $totalAmount - $totalDiscounts;

        return [
            'number' => 'INV-'.strtoupper(fake()->unique()->regexify('[A-Z0-9]{8}')),
            'tenant_id' => Tenant::factory(),
            'centre_id' => Centre::factory(),
            'user_id' => User::factory(),
            'date' => fake()->dateTimeBetween('-30 days', 'now'),
            'due_at' => fake()->dateTimeBetween('now', '+30 days'),
            'status' => fake()->randomElement(InvoiceStatus::cases()),
            'total_items' => $totalItems,
            'total_discounts' => $totalDiscounts,
            'total_amount' => $totalAmount,
            'total' => $total,
        ];
    }

    /**
     * Indicate that the invoice is in draft status.
     */
    public function draft(): Factory
    {
        return $this->state(fn (array $attributes) => [
            'status' => InvoiceStatus::DRAFT,
        ]);
    }

    /**
     * Indicate that the invoice is in pending status.
     */
    public function pending(): Factory
    {
        return $this->state(fn (array $attributes) => [
            'status' => InvoiceStatus::PENDING,
        ]);
    }

    /**
     * Indicate that the invoice is in paid status.
     */
    public function paid(): Factory
    {
        return $this->state(fn (array $attributes) => [
            'status' => InvoiceStatus::PAID,
            'due_at' => fake()->dateTimeBetween('-30 days', 'now'),
        ]);
    }

    /**
     * Indicate that the invoice is in overdue status.
     */
    public function overdue(): Factory
    {
        return $this->state(fn (array $attributes) => [
            'status' => InvoiceStatus::OVERDUE,
            'due_at' => fake()->dateTimeBetween('-30 days', '-1 day'),
        ]);
    }
}
