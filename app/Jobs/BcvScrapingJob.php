<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use App\Services\BcvScrapingService;
use App\Models\BcvExchangeRate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use Exception;

class BcvScrapingJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public int $timeout = 300; // 5 minutos máximo
    public int $tries = 3; // 3 reintentos máximo
    public int $maxExceptions = 2; // Máximo 2 excepciones antes de fallar
    public int $backoff = 60; // Esperar 60 segundos entre reintentos

    private bool $isManual;
    private ?string $requestedBy;

    /**
     * Create a new job instance.
     */
    public function __construct(bool $isManual = false, ?string $requestedBy = null)
    {
        $this->isManual = $isManual;
        $this->requestedBy = $requestedBy;
        
        // Configurar la cola específica para scraping
        $this->onQueue('scraping');
    }

    /**
     * Get the middleware the job should pass through.
     */
    public function middleware(): array
    {
        return [
            // Evitar múltiples ejecuciones simultáneas del mismo job
            new WithoutOverlapping('bcv_scraping', 600), // 10 minutos de overlap protection
        ];
    }

    /**
     * Execute the job.
     */
    public function handle(BcvScrapingService $scrapingService): void
    {
        $startTime = microtime(true);
        $jobId = $this->job?->getJobId() ?? 'unknown';
        
        Log::info("BCV Scraping Job started", [
            'job_id' => $jobId,
            'attempt' => $this->attempts(),
            'is_manual' => $this->isManual,
            'requested_by' => $this->requestedBy,
            'queued_at' => $this->job?->reserved_at ?? now(),
        ]);

        try {
            // Verificar si ya existe un scraping reciente (última hora)
            if (!$this->isManual && $this->hasRecentScraping()) {
                Log::info("Skipping automatic scraping - recent data exists", [
                    'job_id' => $jobId,
                    'last_scraping' => $this->getLastScrapingTime(),
                ]);
                return;
            }

            // Actualizar estado en cache
            $this->updateJobStatus('running');

            // Ejecutar scraping
            $result = $scrapingService->scrapeAndSave();

            $executionTime = round((microtime(true) - $startTime) * 1000, 2); // milisegundos

            if ($result['success']) {
                $this->handleSuccess($result, $executionTime, $jobId);
            } else {
                $this->handleFailure($result, $executionTime, $jobId);
            }

        } catch (Exception $e) {
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);
            $this->handleException($e, $executionTime, $jobId);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Exception $exception = null): void
    {
        $jobId = $this->job?->getJobId() ?? 'unknown';
        
        Log::error("BCV Scraping Job failed permanently", [
            'job_id' => $jobId,
            'attempts' => $this->attempts(),
            'is_manual' => $this->isManual,
            'requested_by' => $this->requestedBy,
            'exception' => $exception?->getMessage(),
            'trace' => $exception?->getTraceAsString(),
        ]);

        // Limpiar estado en cache
        Cache::forget('bcv_scraping_job_status');
        
        // Registrar fallo para estadísticas
        Cache::put('bcv_last_job_failure', [
            'failed_at' => now()->toISOString(),
            'job_id' => $jobId,
            'attempts' => $this->attempts(),
            'error' => $exception?->getMessage() ?? 'Unknown error',
            'is_manual' => $this->isManual,
            'requested_by' => $this->requestedBy,
        ], now()->addDay());
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function backoff(): array
    {
        // Backoff exponencial: 60s, 120s, 240s
        return [60, 120, 240];
    }

    /**
     * Determine if the job should be retried.
     */
    public function retryUntil(): Carbon
    {
        // Reintentar hasta 1 hora después de la creación del job
        return now()->addHour();
    }

    /**
     * Verificar si existe scraping reciente (última hora)
     */
    private function hasRecentScraping(): bool
    {
        $lastScraping = BcvExchangeRate::where('scraped_at', '>=', now()->subHour())
            ->latest('scraped_at')
            ->first();

        return $lastScraping !== null;
    }

    /**
     * Obtener timestamp del último scraping
     */
    private function getLastScrapingTime(): ?string
    {
        $lastScraping = BcvExchangeRate::latest('scraped_at')->first();
        return $lastScraping?->scraped_at?->toISOString();
    }

    /**
     * Actualizar estado del job en cache
     */
    private function updateJobStatus(string $status, array $data = []): void
    {
        $jobStatus = array_merge([
            'status' => $status,
            'job_id' => $this->job?->getJobId() ?? 'unknown',
            'attempt' => $this->attempts(),
            'is_manual' => $this->isManual,
            'requested_by' => $this->requestedBy,
            'updated_at' => now()->toISOString(),
        ], $data);

        Cache::put('bcv_scraping_job_status', $jobStatus, now()->addMinutes(30));
    }

    /**
     * Manejar scraping exitoso
     */
    private function handleSuccess(array $result, float $executionTime, string $jobId): void
    {
        Log::info("BCV Scraping Job completed successfully", [
            'job_id' => $jobId,
            'execution_time_ms' => $executionTime,
            'attempts' => $this->attempts(),
            'data' => $result['data'],
            'scraping_attempts' => $result['attempts'] ?? 1,
        ]);

        // Actualizar estado final
        $this->updateJobStatus('completed', [
            'execution_time_ms' => $executionTime,
            'result' => $result,
            'completed_at' => now()->toISOString(),
        ]);

        // Registrar estadísticas de éxito
        Cache::put('bcv_last_job_success', [
            'completed_at' => now()->toISOString(),
            'job_id' => $jobId,
            'execution_time_ms' => $executionTime,
            'attempts' => $this->attempts(),
            'data' => $result['data'],
            'is_manual' => $this->isManual,
            'requested_by' => $this->requestedBy,
        ], now()->addDay());
    }

    /**
     * Manejar fallo en scraping
     */
    private function handleFailure(array $result, float $executionTime, string $jobId): void
    {
        Log::warning("BCV Scraping Job failed (attempt {$this->attempts()})", [
            'job_id' => $jobId,
            'execution_time_ms' => $executionTime,
            'error' => $result['error'] ?? 'Unknown error',
            'attempts' => $this->attempts(),
            'will_retry' => $this->attempts() < $this->tries,
        ]);

        if ($this->attempts() >= $this->tries) {
            // Si ya no hay más reintentos, registrar como fallo definitivo
            $this->updateJobStatus('failed', [
                'execution_time_ms' => $executionTime,
                'error' => $result['error'] ?? 'Unknown error',
                'final_attempt' => true,
            ]);
            
            throw new Exception($result['error'] ?? 'Scraping failed after all attempts');
        } else {
            // Actualizar estado para reintento
            $this->updateJobStatus('retrying', [
                'execution_time_ms' => $executionTime,
                'error' => $result['error'] ?? 'Unknown error',
                'next_retry_at' => now()->addSeconds($this->backoff()[$this->attempts() - 1] ?? 60)->toISOString(),
            ]);
            
            throw new Exception($result['error'] ?? 'Scraping failed, will retry');
        }
    }

    /**
     * Manejar excepciones inesperadas
     */
    private function handleException(Exception $e, float $executionTime, string $jobId): void
    {
        Log::error("BCV Scraping Job exception (attempt {$this->attempts()})", [
            'job_id' => $jobId,
            'execution_time_ms' => $executionTime,
            'exception' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'attempts' => $this->attempts(),
        ]);

        $this->updateJobStatus('error', [
            'execution_time_ms' => $executionTime,
            'exception' => $e->getMessage(),
        ]);

        throw $e; // Re-lanzar para que el sistema de colas maneje el reintento
    }
}
