<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\VerifyCsrfToken::class,
        ]);
    })
    ->withProviders([
        \App\Providers\BcvScrapingServiceProvider::class,
    ])
    ->withSchedule(function (\Illuminate\Console\Scheduling\Schedule $schedule): void {
        // Scraping automático del BCV - Lunes a Viernes 5:00 PM hora Venezuela
        $schedule->command('bcv:scrape auto')
            ->weekdays()                    // Solo días laborables (Lun-Vie)
            ->dailyAt('17:00')             // 5:00 PM
            ->timezone('America/Caracas')   // Zona horaria de Venezuela (UTC-4)
            ->name('bcv-daily-scraping')
            ->description('Scraping automático del BCV - Lun-Vie 5:00 PM (Venezuela)')
            ->emailOutputOnFailure(config('mail.admin_email', 'admin@example.com'))
            ->appendOutputTo(storage_path('logs/bcv-scheduler.log'));
            
        // Scraping de respaldo - Lunes a Viernes 6:00 PM (por si falla el principal)
        $schedule->command('bcv:scrape auto --force')
            ->weekdays()                    // Solo días laborables
            ->dailyAt('18:00')             // 6:00 PM (1 hora después)
            ->timezone('America/Caracas')   // Zona horaria de Venezuela
            ->name('bcv-backup-scraping')
            ->description('Scraping de respaldo del BCV - Lun-Vie 6:00 PM (Venezuela)')
            ->emailOutputOnFailure(config('mail.admin_email', 'admin@example.com'))
            ->appendOutputTo(storage_path('logs/bcv-scheduler.log'))
            ->skip(function () {
                // Solo ejecutar si el scraping principal falló o no hay datos del día
                $today = now('America/Caracas')->toDateString();
                $todayScrapings = \App\Models\BcvExchangeRate::whereDate('scraped_at', $today)->count();
                return $todayScrapings > 0; // Saltar si ya hay scraping del día
            });
            
        // Scraping de emergencia - Sábados 12:00 PM (por si no hubo datos en la semana)
        $schedule->command('bcv:scrape auto --force')
            ->saturdays()                   // Solo sábados
            ->dailyAt('12:00')             // 12:00 PM
            ->timezone('America/Caracas')   // Zona horaria de Venezuela
            ->name('bcv-weekend-scraping')
            ->description('Scraping de emergencia del BCV - Sábados 12:00 PM (Venezuela)')
            ->emailOutputOnFailure(config('mail.admin_email', 'admin@example.com'))
            ->appendOutputTo(storage_path('logs/bcv-scheduler.log'))
            ->skip(function () {
                // Solo ejecutar si no hay datos de los últimos 3 días
                $threeDaysAgo = now('America/Caracas')->subDays(3);
                $recentScrapings = \App\Models\BcvExchangeRate::where('scraped_at', '>=', $threeDaysAgo)->count();
                return $recentScrapings > 0; // Saltar si hay datos recientes
            });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
