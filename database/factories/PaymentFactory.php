<?php

namespace Database\Factories;

use App\Enums\Gateway;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = Payment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'gateway' => fake()->randomElement([Gateway::BANK_TRANSFER, Gateway::CHIP]),
            'reference_no' => 'REF-'.fake()->unique()->numerify('######'),
            'gateway_payment_id' => null,
            'gateway_payment_data' => null,
            'status' => PaymentStatus::PAID,
            'amount' => fake()->numberBetween(5000, 50000), // Amount in cents
            'description' => fake()->sentence(),
            'paid_at' => now(),
        ];
    }

    /**
     * Indicate that the payment is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::PENDING,
            'paid_at' => null,
        ]);
    }

    /**
     * Indicate that the payment is via CHIP gateway.
     */
    public function chip(): static
    {
        return $this->state(fn (array $attributes) => [
            'gateway' => Gateway::CHIP,
            'gateway_payment_id' => 'chip_'.fake()->uuid(),
            'gateway_payment_data' => [
                'status' => 'pending',
                'payment_method' => 'fpx',
            ],
        ]);
    }

    /**
     * Indicate that the payment is via bank transfer.
     */
    public function bankTransfer(): static
    {
        return $this->state(fn (array $attributes) => [
            'gateway' => Gateway::BANK_TRANSFER,
            'status' => PaymentStatus::PAID,
            'paid_at' => now(),
        ]);
    }
}
