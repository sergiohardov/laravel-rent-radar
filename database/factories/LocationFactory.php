<?php

namespace Database\Factories;

use App\Location\LocationKind;
use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Location>
 */
class LocationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kind' => LocationKind::Street,
            'name' => 'вулиця '.fake()->unique()->lastName(),
            'aliases' => [],
        ];
    }
}
