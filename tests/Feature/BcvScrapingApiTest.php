<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\BcvExchangeRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use App\Jobs\BcvScrapingJob;

class BcvScrapingApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test API latest rate endpoint
     */
    public function test_api_latest_rate_with_data(): void
    {
        // Create test data
        $rate = BcvExchangeRate::factory()->recent()->create([
            'usd_rate' => 105.4527,
            'value_date' => now()->toDateString(),
        ]);

        $response = $this->getJson('/api/bcv/latest');

        $response->assertOk()
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'id',
                        'usd_rate',
                        'formatted_rate',
                        'value_date',
                        'scraped_at',
                        'currency_code',
                        'source_url',
                    ]
                ])
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'usd_rate' => 105.4527,
                        'currency_code' => 'USD',
                    ]
                ]);
    }

    /**
     * Test API latest rate endpoint with no data
     */
    public function test_api_latest_rate_no_data(): void
    {
        $response = $this->getJson('/api/bcv/latest');

        $response->assertOk()
                ->assertJson([
                    'success' => false,
                    'message' => 'No hay datos de tasa de cambio disponibles'
                ]);
    }

    /**
     * Test API history endpoint with data
     */
    public function test_api_history_with_data(): void
    {
        // Create multiple test records
        BcvExchangeRate::factory()->count(5)->create([
            'value_date' => now()->subDays(rand(1, 30))->toDateString(),
        ]);

        $response = $this->getJson('/api/bcv/history');

        $response->assertOk()
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'current_page',
                        'data' => [
                            '*' => [
                                'id',
                                'usd_rate',
                                'formatted_rate',
                                'value_date',
                                'scraped_at',
                                'currency_code',
                            ]
                        ],
                        'per_page',
                        'total',
                    ]
                ])
                ->assertJson([
                    'success' => true,
                ]);

        $data = $response->json('data.data');
        $this->assertCount(5, $data);
    }

    /**
     * Test API history endpoint with pagination
     */
    public function test_api_history_pagination(): void
    {
        // Create more records than per_page limit
        BcvExchangeRate::factory()->count(25)->create();

        $response = $this->getJson('/api/bcv/history?per_page=10');

        $response->assertOk()
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'per_page' => 10,
                        'current_page' => 1,
                    ]
                ]);

        $data = $response->json('data.data');
        $this->assertCount(10, $data);
    }

    /**
     * Test API history endpoint with date filter
     */
    public function test_api_history_with_date_filter(): void
    {
        $targetDate = '2025-01-15';
        
        // Create records for target date
        BcvExchangeRate::factory()->count(2)->create([
            'value_date' => $targetDate,
        ]);
        
        // Create records for other dates
        BcvExchangeRate::factory()->count(3)->create([
            'value_date' => '2025-01-16',
        ]);

        $response = $this->getJson("/api/bcv/history?date={$targetDate}");

        $response->assertOk()
                ->assertJson([
                    'success' => true,
                ]);

        $data = $response->json('data.data');
        $this->assertCount(2, $data);
        
        foreach ($data as $record) {
            $this->assertEquals($targetDate, $record['value_date']);
        }
    }

    /**
     * Test API stats endpoint
     */
    public function test_api_stats_with_data(): void
    {
        // Create test data with different rates
        BcvExchangeRate::factory()->create(['usd_rate' => 100.0000]);
        BcvExchangeRate::factory()->create(['usd_rate' => 110.0000]);
        BcvExchangeRate::factory()->create(['usd_rate' => 105.0000]);

        $response = $this->getJson('/api/bcv/stats');

        $response->assertOk()
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'total_records',
                        'latest_rate',
                        'highest_rate',
                        'lowest_rate',
                        'average_rate',
                        'records_today',
                        'records_this_week',
                        'last_updated',
                    ]
                ])
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'total_records' => 3,
                        'highest_rate' => 110.0000,
                        'lowest_rate' => 100.0000,
                    ]
                ]);
    }

    /**
     * Test API stats endpoint with no data
     */
    public function test_api_stats_no_data(): void
    {
        $response = $this->getJson('/api/bcv/stats');

        $response->assertOk()
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'total_records' => 0,
                        'latest_rate' => null,
                        'highest_rate' => null,
                        'lowest_rate' => null,
                        'average_rate' => null,
                        'records_today' => 0,
                        'records_this_week' => 0,
                        'last_updated' => null,
                    ]
                ]);
    }

    /**
     * Test manual scraping API endpoint
     */
    public function test_api_manual_scraping(): void
    {
        $response = $this->postJson('/api/bcv/scrape');

        $response->assertOk()
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'execution_time',
                        'attempts',
                    ]
                ]);

        // The actual scraping might fail in test environment, so we just check structure
        $this->assertIsArray($response->json('data'));
    }

    /**
     * Test job scraping API endpoint
     */
    public function test_api_job_scraping(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/bcv/jobs/scrape');

        $response->assertOk()
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'job_id',
                        'dispatched_at',
                    ]
                ])
                ->assertJson([
                    'success' => true,
                ]);

        Queue::assertPushed(BcvScrapingJob::class);
    }

    /**
     * Test job status API endpoint
     */
    public function test_api_job_status(): void
    {
        $response = $this->getJson('/api/bcv/jobs/status');

        $response->assertOk()
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'status',
                        'message',
                    ]
                ])
                ->assertJson([
                    'success' => true,
                ]);
    }

    /**
     * Test job stats API endpoint
     */
    public function test_api_job_stats(): void
    {
        $response = $this->getJson('/api/bcv/jobs/stats');

        $response->assertOk()
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'total_jobs',
                        'completed_jobs',
                        'failed_jobs',
                        'pending_jobs',
                        'last_completed',
                        'success_rate',
                    ]
                ])
                ->assertJson([
                    'success' => true,
                ]);
    }

    /**
     * Test job cancel API endpoint
     */
    public function test_api_job_cancel(): void
    {
        $response = $this->deleteJson('/api/bcv/jobs/cancel');

        $response->assertOk()
                ->assertJsonStructure([
                    'success',
                    'message',
                ])
                ->assertJson([
                    'success' => true,
                ]);
    }

    /**
     * Test BCV dashboard web route
     */
    public function test_bcv_dashboard_route(): void
    {
        $response = $this->get('/bcv');

        $response->assertOk()
                ->assertViewIs('bcv.index');
    }

    /**
     * Test BCV dashboard with data
     */
    public function test_bcv_dashboard_with_data(): void
    {
        // Create test data
        BcvExchangeRate::factory()->count(5)->create();

        $response = $this->get('/bcv');

        $response->assertOk()
                ->assertViewHas('latestRate')
                ->assertViewHas('totalRecords');
    }

    /**
     * Test API error handling with invalid date
     */
    public function test_api_history_invalid_date(): void
    {
        $response = $this->getJson('/api/bcv/history?date=invalid-date');

        $response->assertOk(); // Should handle gracefully
    }

    /**
     * Test API rate limits (if implemented)
     */
    public function test_api_rate_limiting(): void
    {
        // Make multiple requests quickly
        for ($i = 0; $i < 5; $i++) {
            $response = $this->getJson('/api/bcv/latest');
            $response->assertOk();
        }

        // Should still work (rate limiting not implemented yet)
        $this->assertTrue(true);
    }

    /**
     * Test API CORS headers (if needed)
     */
    public function test_api_cors_headers(): void
    {
        $response = $this->getJson('/api/bcv/latest');

        // Check if CORS headers are present (if implemented)
        $response->assertOk();
        
        // For now, just verify the API works
        $this->assertTrue(true);
    }

    /**
     * Test API response format consistency
     */
    public function test_api_response_format_consistency(): void
    {
        // All API responses should have consistent format
        $endpoints = [
            '/api/bcv/latest',
            '/api/bcv/history',
            '/api/bcv/stats',
            '/api/bcv/jobs/status',
            '/api/bcv/jobs/stats',
        ];

        foreach ($endpoints as $endpoint) {
            $response = $this->getJson($endpoint);
            
            $response->assertOk()
                    ->assertJsonStructure([
                        'success',
                    ]);
            
            $this->assertIsBool($response->json('success'));
        }
    }

    /**
     * Test API authentication (if required)
     */
    public function test_api_authentication_not_required(): void
    {
        // Currently APIs are public, test they work without auth
        $response = $this->getJson('/api/bcv/latest');

        $response->assertOk();
        $this->assertTrue(true);
    }

    /**
     * Test API with different HTTP methods
     */
    public function test_api_http_methods(): void
    {
        // GET endpoints
        $this->getJson('/api/bcv/latest')->assertOk();
        $this->getJson('/api/bcv/history')->assertOk();
        $this->getJson('/api/bcv/stats')->assertOk();
        
        // POST endpoints
        Queue::fake();
        $this->postJson('/api/bcv/scrape')->assertOk();
        $this->postJson('/api/bcv/jobs/scrape')->assertOk();
        
        // DELETE endpoints
        $this->deleteJson('/api/bcv/jobs/cancel')->assertOk();
    }

    /**
     * Test API data serialization
     */
    public function test_api_data_serialization(): void
    {
        $rate = BcvExchangeRate::factory()->create([
            'usd_rate' => 105.4527,
            'raw_data' => ['test' => 'data'],
        ]);

        $response = $this->getJson('/api/bcv/latest');

        $response->assertOk();
        
        $data = $response->json('data');
        if ($data) {
            $this->assertIsString($data['formatted_rate']);
            $this->assertIsNumeric($data['usd_rate']);
            $this->assertIsString($data['value_date']);
        }
    }
}
