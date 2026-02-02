<?php

namespace Database\Factories;

use App\Models\DeviceToken;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeviceToken>
 */
class DeviceTokenFactory extends Factory
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
            'device_token' => fake()->unique()->sha256(),
            'device_name' => fake()->randomElement(['iPhone 14 Pro', 'Samsung Galaxy S24', 'Pixel 8', 'iPad Pro']),
            'device_type' => fake()->randomElement(['ios', 'android']),
            'push_token_verified_at' => null,
            'last_used_at' => null,
        ];
    }

    /**
     * Indicate that the device token is verified.
     */
    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'push_token_verified_at' => now(),
        ]);
    }

    /**
     * Indicate that the device token was recently used.
     */
    public function recentlyUsed(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_used_at' => now(),
        ]);
    }

    /**
     * Set the device type to iOS.
     */
    public function ios(): static
    {
        return $this->state(fn (array $attributes) => [
            'device_type' => 'ios',
            'device_name' => fake()->randomElement(['iPhone 14 Pro', 'iPhone 15', 'iPad Pro', 'iPad Air']),
        ]);
    }

    /**
     * Set the device type to Android.
     */
    public function android(): static
    {
        return $this->state(fn (array $attributes) => [
            'device_type' => 'android',
            'device_name' => fake()->randomElement(['Samsung Galaxy S24', 'Pixel 8', 'OnePlus 12', 'Xiaomi 14']),
        ]);
    }
}
