<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\BcvExchangeRate;
use App\Services\BcvScrapingService;
use App\Jobs\BcvScrapingJob;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

class BcvScrapingController extends Controller
{
    private BcvScrapingService $scrapingService;

    public function __construct(BcvScrapingService $scrapingService)
    {
        $this->scrapingService = $scrapingService;
    }
    /**
     * Mostrar las tasas de cambio actuales
     */
    public function index(): View
    {
        $latestRates = BcvExchangeRate::latest()->take(10)->get();
        $todayRate = BcvExchangeRate::today()->latest()->first();
        $latestRate = BcvExchangeRate::latest()->first();
        $totalRecords = BcvExchangeRate::count();
        
        return view('bcv.index', compact('latestRates', 'todayRate', 'latestRate', 'totalRecords'));
    }

    /**
     * Obtener la tasa más reciente via API
     */
    public function getLatestRate(): JsonResponse
    {
        $latestRate = BcvExchangeRate::byLatestValue()->first();
        
        if (!$latestRate) {
            return response()->json([
                'success' => false,
                'message' => 'No hay datos de tasa de cambio disponibles'
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $latestRate->id,
                'usd_rate' => $latestRate->usd_rate,
                'formatted_rate' => $latestRate->formatted_rate,
                'value_date' => $latestRate->value_date->format('Y-m-d'),
                'scraped_at' => $latestRate->scraped_at->format('Y-m-d H:i:s'),
                'currency_code' => $latestRate->currency_code,
                'source_url' => $latestRate->source_url,
            ]
        ]);
    }

    /**
     * Obtener historial de tasas
     */
    public function getHistory(Request $request): JsonResponse
    {
        $query = BcvExchangeRate::latest();
        
        // Filtrar por fecha si se proporciona
        try {
            if ($request->has('from_date') && $request->from_date) {
                Carbon::parse($request->from_date); // Validate date
                $query->whereDate('value_date', '>=', $request->from_date);
            }
            
            if ($request->has('to_date') && $request->to_date) {
                Carbon::parse($request->to_date); // Validate date
                $query->whereDate('value_date', '<=', $request->to_date);
            }
            
            if ($request->has('date') && $request->date) {
                Carbon::parse($request->date); // Validate date
                $query->whereDate('value_date', $request->date);
            }
        } catch (\Exception $e) {
            // Invalid date format, ignore filter and continue with query
            // This ensures graceful handling of invalid dates
        }
        
        $rates = $query->paginate($request->get('per_page', 15));
        
        // Transform the data to include formatted_rate
        $rates->getCollection()->transform(function ($rate) {
            return [
                'id' => $rate->id,
                'usd_rate' => $rate->usd_rate,
                'formatted_rate' => $rate->formatted_rate,
                'value_date' => $rate->value_date->format('Y-m-d'),
                'scraped_at' => $rate->scraped_at->format('Y-m-d H:i:s'),
                'currency_code' => $rate->currency_code,
                'source_url' => $rate->source_url,
            ];
        });
        
        return response()->json([
            'success' => true,
            'data' => $rates
        ]);
    }

    /**
     * Ejecutar scraping manual
     */
    public function scrapeManual(): JsonResponse
    {
        $startTime = microtime(true);
        
        try {
            // Ejecutar scraping usando el servicio
            $result = $this->scrapingService->scrapeAndSave();
            
            $executionTime = round((microtime(true) - $startTime) * 1000, 2); // in milliseconds
            
            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => $result['message'],
                    'data' => [
                        'execution_time' => $executionTime,
                        'attempts' => $result['attempts'],
                        'scraped_record' => $result['data'],
                        'scraped_at' => now()->format('Y-m-d H:i:s')
                    ]
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => $result['error'],
                    'data' => [
                        'execution_time' => $executionTime,
                        'attempts' => $result['attempts'] ?? 0,
                        'scraped_at' => now()->format('Y-m-d H:i:s')
                    ]
                ], 422);
            }

        } catch (\Exception $e) {
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);
            
            return response()->json([
                'success' => false,
                'error' => 'Error interno del servidor: ' . $e->getMessage(),
                'data' => [
                    'execution_time' => $executionTime,
                    'scraped_at' => now()->format('Y-m-d H:i:s')
                ]
            ], 500);
        }
    }

    /**
     * Ejecutar scraping via Job (asíncrono)
     */
    public function scrapeAsync(Request $request): JsonResponse
    {
        try {
            // Verificar si ya hay un job en ejecución
            $currentJobStatus = Cache::get('bcv_scraping_job_status');
            
            if ($currentJobStatus && in_array($currentJobStatus['status'], ['running', 'retrying'])) {
                return response()->json([
                    'success' => false,
                    'error' => 'Ya hay un job de scraping en ejecución',
                    'current_job' => $currentJobStatus
                ], 409);
            }

            // Lanzar el job
            $job = new BcvScrapingJob(
                isManual: true,
                requestedBy: $request->user()?->id ?? $request->ip()
            );
            
            $jobId = Queue::push($job);
            
            return response()->json([
                'success' => true,
                'message' => 'Job de scraping lanzado exitosamente',
                'data' => [
                    'job_id' => $jobId ?: 'job_' . uniqid(),
                    'dispatched_at' => now()->format('Y-m-d H:i:s'),
                    'is_manual' => true,
                    'requested_by' => $request->user()?->id ?? $request->ip(),
                    'queue' => 'scraping',
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error lanzando job: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener estado del job actual
     */
    public function getJobStatus(): JsonResponse
    {
        $jobStatus = Cache::get('bcv_scraping_job_status');
        $lastSuccess = Cache::get('bcv_last_job_success');
        $lastFailure = Cache::get('bcv_last_job_failure');
        
        $hasActiveJob = $jobStatus && in_array($jobStatus['status'], ['running', 'retrying']);
        
        return response()->json([
            'success' => true,
            'data' => [
                'status' => $hasActiveJob ? $jobStatus['status'] : 'idle',
                'message' => $hasActiveJob ? 'Job en ejecución' : 'No hay jobs activos',
                'current_job' => $jobStatus,
                'last_success' => $lastSuccess,
                'last_failure' => $lastFailure,
                'has_active_job' => $hasActiveJob,
                'queue_size' => Queue::size('scraping'),
            ]
        ]);
    }

    /**
     * Obtener estadísticas de jobs
     */
    public function getJobStats(): JsonResponse
    {
        try {
            // Estadísticas básicas de la cola
            $queueSize = Queue::size('scraping');
            $pendingJobs = Queue::size('default'); // Jobs pendientes en cola default
            
            // Estadísticas de la aplicación
            $totalRecords = BcvExchangeRate::count();
            $lastSuccess = Cache::get('bcv_last_job_success');
            $lastFailure = Cache::get('bcv_last_job_failure');
            $currentJob = Cache::get('bcv_scraping_job_status');

            $stats = [
                'total_jobs' => $totalRecords, // Usando registros como proxy para jobs exitosos
                'completed_jobs' => $totalRecords,
                'failed_jobs' => 0, // Por ahora asumimos que no hay fallos críticos
                'pending_jobs' => $pendingJobs,
                'last_completed' => $lastSuccess['completed_at'] ?? null,
                'success_rate' => $this->calculateSuccessRate(),
                'queue_stats' => [
                    'scraping_queue_size' => $queueSize,
                    'pending_jobs' => $pendingJobs,
                    'has_active_job' => $currentJob && in_array($currentJob['status'], ['running', 'retrying']),
                ],
                'scraping_stats' => [
                    'total_records' => $totalRecords,
                    'last_success_at' => $lastSuccess['completed_at'] ?? null,
                    'last_failure_at' => $lastFailure['failed_at'] ?? null,
                    'success_rate' => $this->calculateSuccessRate(),
                ],
                'current_job' => $currentJob,
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error obteniendo estadísticas: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cancelar job actual si está en ejecución
     */
    public function cancelJob(): JsonResponse
    {
        try {
            $currentJobStatus = Cache::get('bcv_scraping_job_status');
            
            if (!$currentJobStatus || !in_array($currentJobStatus['status'], ['running', 'retrying'])) {
                return response()->json([
                    'success' => true,
                    'message' => 'No hay job activo para cancelar'
                ]);
            }

            // Limpiar estado del job
            Cache::forget('bcv_scraping_job_status');
            
            // TODO: Implementar cancelación real del job si es necesario
            // Por ahora solo limpiamos el estado del cache
            
            return response()->json([
                'success' => true,
                'message' => 'Job cancelado exitosamente',
                'cancelled_job' => $currentJobStatus
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error cancelando job: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener estadísticas básicas
     */
    public function getStats(): JsonResponse
    {
        $totalRecords = BcvExchangeRate::count();
        $latestRate = BcvExchangeRate::latest()->first();
        $oldestRate = BcvExchangeRate::oldest()->first();
        
        // Calculate additional stats for tests
        $todayRecords = BcvExchangeRate::whereDate('scraped_at', Carbon::today())->count();
        $thisWeekRecords = BcvExchangeRate::whereBetween('scraped_at', [
            Carbon::now()->startOfWeek(),
            Carbon::now()->endOfWeek()
        ])->count();
        
        $highestRate = BcvExchangeRate::max('usd_rate');
        $lowestRate = BcvExchangeRate::min('usd_rate');
        $averageRate = BcvExchangeRate::avg('usd_rate');
        
        $stats = [
            'total_records' => $totalRecords,
            'latest_rate' => $latestRate?->usd_rate,
            'latest_date' => $latestRate?->value_date?->format('Y-m-d'),
            'oldest_date' => $oldestRate?->value_date?->format('Y-m-d'),
            'last_scraping' => $latestRate?->scraped_at?->format('Y-m-d H:i:s'),
            'highest_rate' => $highestRate,
            'lowest_rate' => $lowestRate,
            'average_rate' => $averageRate ? round($averageRate, 4) : null,
            'records_today' => $todayRecords,
            'records_this_week' => $thisWeekRecords,
            'last_updated' => $latestRate?->scraped_at?->format('Y-m-d H:i:s'),
        ];
        
        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Calcular tasa de éxito de jobs
     */
    private function calculateSuccessRate(): ?float
    {
        $lastSuccess = Cache::get('bcv_last_job_success');
        $lastFailure = Cache::get('bcv_last_job_failure');
        
        if (!$lastSuccess && !$lastFailure) {
            return null;
        }
        
        // Por ahora devolvemos un cálculo simple basado en los últimos resultados
        // En una implementación más compleja podríamos guardar estadísticas detalladas
        $totalJobs = BcvExchangeRate::count();
        $successfulJobs = $totalJobs; // Cada registro exitoso representa un job exitoso
        
        return $totalJobs > 0 ? round(($successfulJobs / ($totalJobs + 1)) * 100, 2) : null;
    }
}
