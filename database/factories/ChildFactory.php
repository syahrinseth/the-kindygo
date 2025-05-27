<?php

namespace Database\Factories;

use App\Enums\ChildStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Child>
 */
class ChildFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'patronymic' => fake()->optional(0.7)->firstName('male'),
            'mykid_no' => fake()->optional(0.9)->numerify('##########'),
            'date_of_birth' => fake()->dateTimeBetween('-7 years', '-1 year'),
            'place_of_birth' => fake()->city(),
            'gender' => fake()->randomElement(['male', 'female']),
            'cert_number' => fake()->optional(0.8)->numerify('BC########'),
            'position_of_child' => fake()->optional(0.8)->numberBetween(1, 5),
            'race' => fake()->optional(0.8)->randomElement(['Malay', 'Chinese', 'Indian', 'Others']),
            'religion' => fake()->optional(0.8)->randomElement(['Islam', 'Buddhism', 'Hinduism', 'Christianity', 'Others']),
            'languages' => fake()->optional(0.8)->randomElement(['English', 'Malay', 'English, Malay', 'English, Mandarin', 'English, Tamil']),
            
            'allergies' => fake()->optional(0.3)->text(100),
            'diseases' => fake()->optional(0.3)->text(100),
            'family_clinic' => fake()->optional(0.7)->company(),
            'family_clinic_phone' => fake()->optional(0.7)->phoneNumber(),
            
            'status' => fake()->randomElement(ChildStatus::values()),
        ];
    }
}
