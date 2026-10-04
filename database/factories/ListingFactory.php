<?php

namespace Database\Factories;

use App\Currency\CurrencyCode;
use App\Models\Listing;
use App\Pipeline\Decision;
use App\Sources\SourceName;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Listing>
 */
class ListingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $id = (string) fake()->unique()->numberBetween(1_000_000, 99_999_999);

        return [
            'source' => SourceName::DimRia,
            'external_id' => $id,
            'url' => "https://dom.ria.com/uk/realty-{$id}.html",
            'title' => null,
            'description' => fake()->paragraph(),
            'raw' => ['realty_id' => (int) $id],
            'price' => fake()->numberBetween(8, 25) * 1000,
            'currency' => CurrencyCode::Uah,
            'rooms' => fake()->numberBetween(1, 3),
            'floor' => fake()->numberBetween(1, 16),
            'floors_total' => 16,
            'area' => fake()->randomFloat(1, 30, 90),
            'lat' => fake()->latitude(46.38, 46.55),
            'lng' => fake()->longitude(30.65, 30.78),
            'published_at' => now(),
            'decision' => Decision::Pending,
        ];
    }

    public function rejected(string $stage, string $reason): static
    {
        return $this->state([
            'decision' => Decision::Rejected,
            'rejected_by' => $stage,
            'reject_reason' => $reason,
        ]);
    }
}
