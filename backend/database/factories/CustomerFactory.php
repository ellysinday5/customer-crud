<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Customer>
 */
class CustomerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'first_name'     => $this->faker->firstName(),
            'last_name'      => $this->faker->lastName(),
            'email'          => $this->faker->unique()->safeEmail(),
            'contact_number' => $this->faker->numerify('09#########'),
        ];
    }
}
