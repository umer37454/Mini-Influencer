<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ProfileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'username' => strtolower(
                fake()->unique()->bothify('user_#####')
            ),
            'platform' => 'youtube',
            'status' => 'pending',
        ];
    }
}