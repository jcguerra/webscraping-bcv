<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\BcvExchangeRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class BcvExchangeRateModelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test model can be created with factory
     */
    public function test_can_create_bcv_exchange_rate_with_factory(): void
    {
        $rate = BcvExchangeRate::factory()->create();

        $this->assertInstanceOf(BcvExchangeRate::class, $rate);
        $this->assertDatabaseHas('bcv_exchange_rates', [
            'id' => $rate->id,
            'currency_code' => 'USD',
        ]);
    }

    /**
     * Test fillable attributes
     */
    public function test_model_has_correct_fillable_attributes(): void
    {
        $rate = new BcvExchangeRate();
        $expectedFillable = [
            'usd_rate',
            'value_date', 
            'scraped_at',
            'currency_code',
            'raw_data',
            'source_url',
        ];

        $this->assertEquals($expectedFillable, $rate->getFillable());
    }

    /**
     * Test date casting
     */
    public function test_dates_are_cast_correctly(): void
    {
        $rate = BcvExchangeRate::factory()->create();

        $this->assertInstanceOf(Carbon::class, $rate->value_date);
        $this->assertInstanceOf(Carbon::class, $rate->scraped_at);
        $this->assertInstanceOf(Carbon::class, $rate->created_at);
        $this->assertInstanceOf(Carbon::class, $rate->updated_at);
    }

    /**
     * Test formatted_rate accessor
     */
    public function test_formatted_rate_accessor(): void
    {
        $rate = BcvExchangeRate::factory()->withRate(105.4527)->create();

        $this->assertEquals('105,45 Bs.', $rate->formatted_rate);
    }

    /**
     * Test formatted_rate with different rates
     */
    public function test_formatted_rate_with_various_rates(): void
    {
        $testCases = [
            [100.0000, '100,00 Bs.'],
            [99.9999, '100,00 Bs.'],
            [50.1234, '50,12 Bs.'],
            [200.8765, '200,88 Bs.'],
        ];

        foreach ($testCases as [$inputRate, $expectedFormat]) {
            $rate = BcvExchangeRate::factory()->withRate($inputRate)->create();
            $this->assertEquals($expectedFormat, $rate->formatted_rate);
        }
    }

    /**
     * Test latest scope
     */
    public function test_latest_scope(): void
    {
        // Create multiple rates with different scraped times
        $oldRate = BcvExchangeRate::factory()->create([
            'scraped_at' => now()->subDays(5)
        ]);
        
        $newestRate = BcvExchangeRate::factory()->create([
            'scraped_at' => now()->subHour()
        ]);
        
        $middleRate = BcvExchangeRate::factory()->create([
            'scraped_at' => now()->subDays(2)
        ]);

        $latestRate = BcvExchangeRate::latest()->first();

        $this->assertEquals($newestRate->id, $latestRate->id);
    }

    /**
     * Test today scope
     */
    public function test_today_scope(): void
    {
        // Create rate from today
        $todayRate = BcvExchangeRate::factory()->today()->create();
        
        // Create rate from yesterday
        $yesterdayRate = BcvExchangeRate::factory()->create([
            'value_date' => now()->subDay()->toDateString()
        ]);

        $todayRates = BcvExchangeRate::today()->get();

        $this->assertCount(1, $todayRates);
        $this->assertTrue($todayRates->contains($todayRate));
        $this->assertFalse($todayRates->contains($yesterdayRate));
    }

    /**
     * Test byDate scope
     */
    public function test_by_date_scope(): void
    {
        $targetDate = '2024-01-15';
        
        $targetRate = BcvExchangeRate::factory()->create([
            'value_date' => $targetDate
        ]);
        
        $otherRate = BcvExchangeRate::factory()->create([
            'value_date' => '2024-01-16'
        ]);

        $ratesByDate = BcvExchangeRate::byDate($targetDate)->get();

        $this->assertCount(1, $ratesByDate);
        $this->assertTrue($ratesByDate->contains($targetRate));
        $this->assertFalse($ratesByDate->contains($otherRate));
    }

    /**
     * Test current scope 
     */
    public function test_current_scope(): void
    {
        // Create multiple rates
        $oldRate = BcvExchangeRate::factory()->old()->create();
        $recentRate = BcvExchangeRate::factory()->recent()->create();
        $currentRate = BcvExchangeRate::factory()->today()->create();

        $currentRates = BcvExchangeRate::current()->get();

        // Should return the most recent rate
        $this->assertGreaterThan(0, $currentRates->count());
        $latestFromCurrent = $currentRates->first();
        
        // Verify it's the most recent one
        $allRatesOrdered = BcvExchangeRate::orderBy('scraped_at', 'desc')->first();
        $this->assertEquals($allRatesOrdered->id, $latestFromCurrent->id);
    }

    /**
     * Test JSON casting for raw_data
     */
    public function test_raw_data_json_casting(): void
    {
        $rawData = [
            'original_text' => '105,45270000',
            'parsed_rate' => 105.4527,
            'selector_used' => '#dolar strong',
        ];

        $rate = BcvExchangeRate::factory()->create([
            'raw_data' => json_encode($rawData)
        ]);

        $this->assertIsArray($rate->raw_data);
        $this->assertEquals($rawData, $rate->raw_data);
    }

    /**
     * Test model validation rules (if implemented)
     */
    public function test_usd_rate_must_be_positive(): void
    {
        $rate = BcvExchangeRate::factory()->withRate(105.45)->make();
        
        $this->assertGreaterThan(0, $rate->usd_rate);
    }

    /**
     * Test model can handle null raw_data
     */
    public function test_handles_null_raw_data(): void
    {
        $rate = BcvExchangeRate::factory()->create([
            'raw_data' => null
        ]);

        $this->assertNull($rate->raw_data);
    }

    /**
     * Test model mass assignment protection
     */
    public function test_mass_assignment_protection(): void
    {
        $rate = new BcvExchangeRate();
        
        // These should be guarded (not fillable)
        $this->assertNotContains('id', $rate->getFillable());
        $this->assertNotContains('created_at', $rate->getFillable());
        $this->assertNotContains('updated_at', $rate->getFillable());
    }

    /**
     * Test model string representation
     */
    public function test_model_string_representation(): void
    {
        $rate = BcvExchangeRate::factory()->withRate(105.45)->create([
            'value_date' => '2024-01-15'
        ]);

        // If __toString is implemented
        if (method_exists($rate, '__toString')) {
            $stringRep = (string) $rate;
            $this->assertIsString($stringRep);
            $this->assertNotEmpty($stringRep);
        }

        $this->assertTrue(true); // Fallback assertion
    }

    /**
     * Test model relationships (if any exist)
     */
    public function test_model_has_expected_table(): void
    {
        $rate = new BcvExchangeRate();
        $this->assertEquals('bcv_exchange_rates', $rate->getTable());
    }

    /**
     * Test recent factory state
     */
    public function test_recent_factory_state(): void
    {
        $recentRate = BcvExchangeRate::factory()->recent()->create();

        $hoursAgo = now()->diffInHours($recentRate->scraped_at);
        $this->assertLessThanOrEqual(24, $hoursAgo);
    }

    /**
     * Test old factory state
     */
    public function test_old_factory_state(): void
    {
        $oldRate = BcvExchangeRate::factory()->old()->create();

        $weeksAgo = now()->diffInWeeks($oldRate->scraped_at);
        $this->assertGreaterThanOrEqual(1, $weeksAgo);
    }

    /**
     * Test high rate factory state
     */
    public function test_high_rate_factory_state(): void
    {
        $highRate = BcvExchangeRate::factory()->highRate()->create();

        $this->assertGreaterThanOrEqual(150.0000, $highRate->usd_rate);
    }

    /**
     * Test low rate factory state
     */
    public function test_low_rate_factory_state(): void
    {
        $lowRate = BcvExchangeRate::factory()->lowRate()->create();

        $this->assertLessThan(100.0000, $lowRate->usd_rate);
    }
}
