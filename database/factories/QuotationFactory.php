<?php

namespace Database\Factories;

use App\Enums\QuotationStatus;
use App\Models\Centre;
use App\Models\Child;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Quotation>
 */
class QuotationFactory extends Factory
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
        $total = $totalAmount;
        $date = Carbon::instance(fake()->dateTimeBetween('-30 days', 'now'));

        return [
            'number' => 'QUO/' . strtoupper(fake()->unique()->regexify('[A-Z0-9]{8}')),
            'tenant_id' => Tenant::factory(),
            'centre_id' => Centre::factory(),
            'user_id' => User::factory(),
            'child_id' => null,
            'date' => $date,
            'valid_until' => $date->copy()->addDays(30),
            'status' => fake()->randomElement(QuotationStatus::cases()),
            'converted_invoice_id' => null,
            'total_items' => $totalItems,
            'total_amount' => $totalAmount,
            'total' => $total,
            'terms_conditions' => fake()->optional()->paragraph(),
            'notes' => fake()->optional()->sentence(),
        ];
    }

    /**
     * Indicate that the quotation is in draft status.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function draft(): Factory
    {
        return $this->state(fn (array $attributes) => [
            'status' => QuotationStatus::DRAFT,
        ]);
    }

    /**
     * Indicate that the quotation is in pending status.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function pending(): Factory
    {
        return $this->state(fn (array $attributes) => [
            'status' => QuotationStatus::PENDING,
        ]);
    }

    /**
     * Indicate that the quotation is in accepted status.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function accepted(): Factory
    {
        return $this->state(fn (array $attributes) => [
            'status' => QuotationStatus::ACCEPTED,
        ]);
    }

    /**
     * Indicate that the quotation is in converted status.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function converted(): Factory
    {
        return $this->state(fn (array $attributes) => [
            'status' => QuotationStatus::CONVERTED,
            'converted_invoice_id' => Invoice::factory(),
        ]);
    }

    /**
     * Indicate that the quotation is in expired status.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function expired(): Factory
    {
        return $this->state(fn (array $attributes) => [
            'status' => QuotationStatus::EXPIRED,
            'valid_until' => fake()->dateTimeBetween('-30 days', '-1 day'),
        ]);
    }

    /**
     * Indicate that the quotation is in rejected status.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function rejected(): Factory
    {
        return $this->state(fn (array $attributes) => [
            'status' => QuotationStatus::REJECTED,
        ]);
    }

    /**
     * Indicate that the quotation has a child.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function withChild(): Factory
    {
        return $this->state(fn (array $attributes) => [
            'child_id' => Child::factory(),
        ]);
    }
}
