<?php

namespace Database\Factories;

use App\Models\PushNotification;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PushNotification>
 */
class PushNotificationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'tenant_id' => Tenant::factory(),
            'type' => fake()->randomElement(['payment', 'invoice', 'reminder', 'general']),
            'title' => fake()->sentence(4),
            'message' => fake()->paragraph(1),
            'data' => null,
            'is_read' => false,
            'read_at' => null,
            'sent_at' => now(),
        ];
    }

    /**
     * Indicate that the notification has been read.
     */
    public function read(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_read' => true,
            'read_at' => now(),
        ]);
    }

    /**
     * Indicate that the notification is unread.
     */
    public function unread(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_read' => false,
            'read_at' => null,
        ]);
    }

    /**
     * Set the notification type to payment.
     */
    public function payment(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'payment',
            'title' => 'Payment Received',
            'message' => 'Your payment of RM '.fake()->randomFloat(2, 100, 1000).' has been received.',
            'data' => [
                'payment_id' => fake()->randomNumber(5),
                'amount' => fake()->randomFloat(2, 100, 1000),
            ],
        ]);
    }

    /**
     * Set the notification type to invoice.
     */
    public function invoice(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'invoice',
            'title' => 'New Invoice',
            'message' => 'A new invoice of RM '.fake()->randomFloat(2, 100, 1000).' has been created.',
            'data' => [
                'invoice_id' => fake()->randomNumber(5),
                'amount' => fake()->randomFloat(2, 100, 1000),
            ],
        ]);
    }

    /**
     * Set the notification type to reminder.
     */
    public function reminder(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'reminder',
            'title' => 'Payment Reminder',
            'message' => 'Your invoice is due today. Please make the payment.',
            'data' => [
                'invoice_id' => fake()->randomNumber(5),
            ],
        ]);
    }
}
