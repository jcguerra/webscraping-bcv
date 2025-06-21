<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\BcvScrapingJob;
use App\Services\BcvScrapingService;
use App\Models\BcvExchangeRate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class BcvScrapingCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bcv:scrape 
                            {action=auto : Acción a realizar (auto, manual, job, status, stats, clear, time)}
                            {--force : Forzar scraping aunque haya datos recientes}
                            {--sync : Ejecutar de forma síncrona en lugar de usar job}
                            {--no-cache : No verificar cache de datos recientes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Gestionar el scraping automático del BCV';

    private BcvScrapingService $scrapingService;

    /**
     * Create a new command instance.
     */
    public function __construct(BcvScrapingService $scrapingService)
    {
        parent::__construct();
        $this->scrapingService = $scrapingService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $action = $this->argument('action');

        $this->info("🏦 BCV Scraping Command - Acción: {$action}");
        $this->newLine();

        try {
            return match ($action) {
                'auto' => $this->handleAutoScraping(),
                'manual' => $this->handleManualScraping(),
                'job' => $this->handleJobScraping(),
                'status' => $this->handleStatus(),
                'stats' => $this->handleStats(),
                'clear' => $this->handleClear(),
                'time' => $this->handleTimeInfo(),
                default => $this->handleUnknownAction($action),
            };
        } catch (\Exception $e) {
            $this->error("❌ Error ejecutando comando: " . $e->getMessage());
            return self::FAILURE;
        }
    }

    /**
     * Scraping automático (para scheduler)
     */
    private function handleAutoScraping(): int
    {
        $this->info("🔄 Ejecutando scraping automático...");

        // Verificar si hay datos recientes (última hora) y no estamos forzando
        if (!$this->option('force') && !$this->option('no-cache')) {
            $recentScraping = BcvExchangeRate::where('scraped_at', '>=', now()->subHour())
                ->latest('scraped_at')
                ->first();

            if ($recentScraping) {
                $this->warn("⏭️  Saltando scraping - datos recientes encontrados");
                $this->line("   Último scraping: " . $recentScraping->scraped_at->format('d/m/Y H:i:s'));
                $this->line("   Valor USD: " . $recentScraping->formatted_rate);
                return self::SUCCESS;
            }
        }

        if ($this->option('sync')) {
            return $this->executeSyncScraping('automático');
        } else {
            return $this->executeJobScraping(false);
        }
    }

    /**
     * Scraping manual
     */
    private function handleManualScraping(): int
    {
        $this->info("🔧 Ejecutando scraping manual...");

        if ($this->option('sync')) {
            return $this->executeSyncScraping('manual');
        } else {
            return $this->executeJobScraping(true);
        }
    }

    /**
     * Lanzar job de scraping
     */
    private function handleJobScraping(): int
    {
        $this->info("🚀 Lanzando job de scraping...");

        // Verificar si ya hay un job en ejecución
        $currentJobStatus = Cache::get('bcv_scraping_job_status');
        
        if ($currentJobStatus && in_array($currentJobStatus['status'], ['running', 'retrying'])) {
            $this->warn("⚠️  Ya hay un job de scraping en ejecución");
            $this->displayJobStatus($currentJobStatus);
            
            if (!$this->confirm('¿Quieres lanzar otro job de todas formas?', false)) {
                return self::SUCCESS;
            }
        }

        $job = new BcvScrapingJob(
            isManual: true,
            requestedBy: 'artisan-command'
        );
        
        Queue::push($job);
        
        $this->info("✅ Job lanzado exitosamente");
        $this->line("   Cola: scraping");
        $this->line("   Tipo: manual");
        $this->line("   Solicitado por: artisan-command");
        
        return self::SUCCESS;
    }

    /**
     * Mostrar estado actual
     */
    private function handleStatus(): int
    {
        $this->info("📊 Estado actual del sistema de scraping");
        $this->newLine();

        // Estado del job actual
        $currentJob = Cache::get('bcv_scraping_job_status');
        if ($currentJob) {
            $this->displayJobStatus($currentJob);
        } else {
            $this->line("   No hay job activo");
        }

        $this->newLine();

        // Último éxito
        $lastSuccess = Cache::get('bcv_last_job_success');
        if ($lastSuccess) {
            $this->info("✅ Último éxito:");
            $this->line("   Fecha: " . Carbon::parse($lastSuccess['completed_at'])->format('d/m/Y H:i:s'));
            $this->line("   Job ID: " . $lastSuccess['job_id']);
            $this->line("   Tiempo: " . $lastSuccess['execution_time_ms'] . "ms");
            $this->line("   USD: " . $lastSuccess['data']['usd_rate']);
        } else {
            $this->line("   No hay registros de éxito");
        }

        $this->newLine();

        // Último fallo
        $lastFailure = Cache::get('bcv_last_job_failure');
        if ($lastFailure) {
            $this->error("❌ Último fallo:");
            $this->line("   Fecha: " . Carbon::parse($lastFailure['failed_at'])->format('d/m/Y H:i:s'));
            $this->line("   Job ID: " . $lastFailure['job_id']);
            $this->line("   Error: " . $lastFailure['error']);
        } else {
            $this->line("   No hay registros de fallos");
        }

        return self::SUCCESS;
    }

    /**
     * Mostrar estadísticas
     */
    private function handleStats(): int
    {
        $this->info("📈 Estadísticas del sistema");
        $this->newLine();

        $totalRecords = BcvExchangeRate::count();
        $latestRate = BcvExchangeRate::latest()->first();
        $oldestRate = BcvExchangeRate::oldest()->first();

        $this->info("💾 Base de datos:");
        $this->line("   Total registros: {$totalRecords}");
        if ($latestRate) {
            $this->line("   Última tasa: " . $latestRate->formatted_rate);
            $this->line("   Fecha valor: " . $latestRate->value_date->format('d/m/Y'));
            $this->line("   Último scraping: " . $latestRate->scraped_at->format('d/m/Y H:i:s'));
        }
        if ($oldestRate && $latestRate && $oldestRate->id !== $latestRate->id) {
            $this->line("   Primer registro: " . $oldestRate->scraped_at->format('d/m/Y H:i:s'));
        }

        $this->newLine();

        // Estadísticas de cola
        try {
            $queueSize = Queue::size('scraping');
            $this->info("🔄 Cola de jobs:");
            $this->line("   Jobs pendientes: {$queueSize}");
        } catch (\Exception $e) {
            $this->warn("   Error obteniendo info de cola: " . $e->getMessage());
        }

        return self::SUCCESS;
    }

    /**
     * Limpiar cache y estados
     */
    private function handleClear(): int
    {
        $this->info("🧹 Limpiando cache y estados...");

        $keys = [
            'bcv_scraping_job_status',
            'bcv_last_job_success',
            'bcv_last_job_failure'
        ];

        foreach ($keys as $key) {
            Cache::forget($key);
            $this->line("   ✓ Limpiado: {$key}");
        }

        $this->info("✅ Cache limpiado exitosamente");
        return self::SUCCESS;
    }

    /**
     * Mostrar información de horarios y zona horaria
     */
    private function handleTimeInfo(): int
    {
        $this->info("🕐 Información de Horarios y Zona Horaria");
        $this->newLine();

        // Zona horaria actual de la aplicación
        $appTimezone = config('app.timezone');
        $this->info("⚙️  Configuración de la aplicación:");
        $this->line("   Zona horaria: {$appTimezone}");
        
        // Hora actual en diferentes zonas horarias
        $now = now();
        $nowVenezuela = now('America/Caracas');
        $nowUtc = now('UTC');
        
        $this->newLine();
        $this->info("🌍 Hora actual:");
        $this->line("   UTC:       " . $nowUtc->format('Y-m-d H:i:s T'));
        $this->line("   Venezuela: " . $nowVenezuela->format('Y-m-d H:i:s T'));
        $this->line("   App:       " . $now->format('Y-m-d H:i:s T'));
        
        // Información del día actual
        $this->newLine();
        $this->info("📅 Día actual (Venezuela):");
        $this->line("   Fecha: " . $nowVenezuela->format('Y-m-d'));
        $this->line("   Día: " . $nowVenezuela->format('l')); // Nombre completo del día
        $this->line("   Es día laboral: " . ($nowVenezuela->isWeekday() ? 'Sí' : 'No'));
        $this->line("   Es fin de semana: " . ($nowVenezuela->isWeekend() ? 'Sí' : 'No'));
        
        // Horarios programados
        $this->newLine();
        $this->info("⏰ Horarios programados:");
        $this->line("   Scraping principal: Lun-Vie 17:00 (5:00 PM) Venezuela");
        $this->line("   Scraping respaldo:  Lun-Vie 18:00 (6:00 PM) Venezuela");
        $this->line("   Scraping emergencia: Sábados 12:00 (12:00 PM) Venezuela");
        
        // Próxima ejecución
        $this->newLine();
        $this->info("🔮 Próxima ejecución:");
        
        // Calcular próxima ejecución para scraping principal
        $nextExecution = $this->calculateNextExecution($nowVenezuela);
        $this->line("   Próximo scraping: " . $nextExecution['date']->format('Y-m-d H:i:s T'));
        $this->line("   Tipo: " . $nextExecution['type']);
        $this->line("   En: " . $nextExecution['in']);
        
        // Verificar si estamos en horario de scraping
        $this->newLine();
        if ($this->isScrapingTime($nowVenezuela)) {
            $this->info("✅ ESTAMOS EN HORARIO DE SCRAPING");
            $this->line("   Se puede ejecutar scraping automático ahora");
        } else {
            $this->warn("⏰ NO estamos en horario de scraping");
            $this->line("   El scraping automático esperará al próximo horario programado");
        }
        
        return self::SUCCESS;
    }

    /**
     * Calcular próxima ejecución
     */
    private function calculateNextExecution(Carbon $now): array
    {
        // Si es un día laboral y no han pasado las 5 PM
        if ($now->isWeekday() && $now->hour < 17) {
            $nextExecution = $now->copy()->setTime(17, 0, 0);
            return [
                'date' => $nextExecution,
                'type' => 'Scraping principal',
                'in' => $nextExecution->diffForHumans($now)
            ];
        }
        
        // Si es un día laboral y han pasado las 5 PM pero no las 6 PM
        if ($now->isWeekday() && $now->hour == 17) {
            $nextExecution = $now->copy()->setTime(18, 0, 0);
            return [
                'date' => $nextExecution,
                'type' => 'Scraping respaldo',
                'in' => $nextExecution->diffForHumans($now)
            ];
        }
        
        // Si es viernes después de las 6 PM, próximo será lunes 5 PM
        if ($now->isFriday() && $now->hour >= 18) {
            $nextExecution = $now->copy()->next(Carbon::MONDAY)->setTime(17, 0, 0);
            return [
                'date' => $nextExecution,
                'type' => 'Scraping principal',
                'in' => $nextExecution->diffForHumans($now)
            ];
        }
        
        // Si es sábado y no han pasado las 12 PM
        if ($now->isSaturday() && $now->hour < 12) {
            $nextExecution = $now->copy()->setTime(12, 0, 0);
            return [
                'date' => $nextExecution,
                'type' => 'Scraping emergencia',
                'in' => $nextExecution->diffForHumans($now)
            ];
        }
        
        // Si es fin de semana, próximo será lunes 5 PM
        if ($now->isWeekend()) {
            $nextExecution = $now->copy()->next(Carbon::MONDAY)->setTime(17, 0, 0);
            return [
                'date' => $nextExecution,
                'type' => 'Scraping principal',
                'in' => $nextExecution->diffForHumans($now)
            ];
        }
        
        // Para otros casos, próximo día laboral a las 5 PM
        $nextExecution = $now->copy()->next(Carbon::MONDAY)->setTime(17, 0, 0);
        return [
            'date' => $nextExecution,
            'type' => 'Scraping principal',
            'in' => $nextExecution->diffForHumans($now)
        ];
    }

    /**
     * Verificar si estamos en horario de scraping
     */
    private function isScrapingTime(Carbon $now): bool
    {
        // Días laborables entre 17:00 y 18:59
        if ($now->isWeekday() && $now->hour >= 17 && $now->hour < 19) {
            return true;
        }
        
        // Sábados entre 12:00 y 12:59 (solo si no hay datos recientes)
        if ($now->isSaturday() && $now->hour == 12) {
            $threeDaysAgo = $now->copy()->subDays(3);
            $recentScrapings = BcvExchangeRate::where('scraped_at', '>=', $threeDaysAgo)->count();
            return $recentScrapings == 0;
        }
        
        return false;
    }

    /**
     * Acción desconocida
     */
    private function handleUnknownAction(string $action): int
    {
        $this->error("❌ Acción desconocida: {$action}");
        $this->newLine();
        $this->line("Acciones disponibles:");
        $this->line("  auto    - Scraping automático (para scheduler)");
        $this->line("  manual  - Scraping manual");
        $this->line("  job     - Lanzar job de scraping");
        $this->line("  status  - Mostrar estado actual");
        $this->line("  stats   - Mostrar estadísticas");
        $this->line("  clear   - Limpiar cache");
        $this->line("  time    - Información de horarios y zona horaria");
        
        return self::INVALID;
    }

    /**
     * Ejecutar scraping síncrono
     */
    private function executeSyncScraping(string $type): int
    {
        $this->line("   🔄 Ejecutando scraping {$type} síncrono...");

        $startTime = microtime(true);
        $result = $this->scrapingService->scrapeAndSave();
        $executionTime = round((microtime(true) - $startTime) * 1000, 2);

        if ($result['success']) {
            $this->info("   ✅ Scraping exitoso ({$executionTime}ms)");
            $this->line("   📊 USD: " . $result['data']['usd_rate']);
            $this->line("   📅 Fecha: " . $result['data']['value_date']);
            $this->line("   🆔 ID: " . $result['data']['id']);
            return self::SUCCESS;
        } else {
            $this->error("   ❌ Scraping falló: " . $result['error']);
            return self::FAILURE;
        }
    }

    /**
     * Ejecutar scraping via job
     */
    private function executeJobScraping(bool $isManual): int
    {
        $type = $isManual ? 'manual' : 'automático';
        $this->line("   🚀 Lanzando job {$type}...");

        $job = new BcvScrapingJob(
            isManual: $isManual,
            requestedBy: 'artisan-command'
        );
        
        Queue::push($job);
        
        $this->info("   ✅ Job lanzado exitosamente");
        return self::SUCCESS;
    }

    /**
     * Mostrar estado de un job
     */
    private function displayJobStatus(array $jobStatus): void
    {
        $status = $jobStatus['status'];
        $statusIcon = match ($status) {
            'running' => '🔄',
            'completed' => '✅',
            'failed' => '❌',
            'retrying' => '⏳',
            default => '❓',
        };

        $this->info("{$statusIcon} Job actual:");
        $this->line("   Estado: {$status}");
        $this->line("   Job ID: " . $jobStatus['job_id']);
        $this->line("   Intento: " . $jobStatus['attempt']);
        $this->line("   Tipo: " . ($jobStatus['is_manual'] ? 'manual' : 'automático'));
        $this->line("   Actualizado: " . Carbon::parse($jobStatus['updated_at'])->format('d/m/Y H:i:s'));
        
        if (isset($jobStatus['execution_time_ms'])) {
            $this->line("   Tiempo: " . $jobStatus['execution_time_ms'] . "ms");
        }
        
        if (isset($jobStatus['error'])) {
            $this->line("   Error: " . $jobStatus['error']);
        }
    }
}
