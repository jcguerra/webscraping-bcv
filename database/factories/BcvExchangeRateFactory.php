<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BcvExchangeRate>
 */
class BcvExchangeRateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $baseRate = $this->faker->randomFloat(4, 50.0000, 200.0000);
        $valueDate = $this->faker->dateTimeBetween('-1 year', 'now');
        $scrapedAt = $this->faker->dateTimeBetween($valueDate, 'now');
        
        return [
            'usd_rate' => $baseRate,
            'value_date' => $valueDate,
            'scraped_at' => $scrapedAt,
            'currency_code' => 'USD',
            'raw_data' => json_encode([
                'original_text' => number_format($baseRate, 8, ',', '.'),
                'parsed_rate' => $baseRate,
                'date_text' => $valueDate->format('l, j F Y'),
                'selector_used' => '#dolar strong',
                'scraping_method' => 'test',
            ]),
            'source_url' => 'https://www.bcv.org.ve/',
        ];
    }

    /**
     * Recent exchange rate (last 24 hours)
     */
    public function recent(): static
    {
        return $this->state(fn (array $attributes) => [
            'value_date' => now()->subHours(rand(1, 24)),
            'scraped_at' => now()->subHours(rand(0, 12)),
        ]);
    }

    /**
     * Old exchange rate (more than 1 week ago)
     */
    public function old(): static
    {
        return $this->state(fn (array $attributes) => [
            'value_date' => now()->subWeeks(rand(1, 10)),
            'scraped_at' => now()->subWeeks(rand(1, 10)),
        ]);
    }

    /**
     * High USD rate (above 150)
     */
    public function highRate(): static
    {
        return $this->state(fn (array $attributes) => [
            'usd_rate' => $this->faker->randomFloat(4, 150.0000, 300.0000),
        ]);
    }

    /**
     * Low USD rate (below 100)
     */
    public function lowRate(): static
    {
        return $this->state(fn (array $attributes) => [
            'usd_rate' => $this->faker->randomFloat(4, 50.0000, 99.9999),
        ]);
    }

    /**
     * Today's rate
     */
    public function today(): static
    {
        return $this->state(fn (array $attributes) => [
            'value_date' => now()->toDateString(),
            'scraped_at' => now()->subHours(rand(1, 6)),
        ]);
    }

    /**
     * Rate with specific value
     */
    public function withRate(float $rate): static
    {
        return $this->state(fn (array $attributes) => [
            'usd_rate' => $rate,
        ]);
    }
}
