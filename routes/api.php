<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BcvScrapingController;

// Rutas API para el scraping del BCV (sin CSRF)
Route::prefix('bcv')->name('bcv.api.')->group(function () {
    // Obtener datos
    Route::get('/latest', [BcvScrapingController::class, 'getLatestRate'])->name('latest');
    Route::get('/history', [BcvScrapingController::class, 'getHistory'])->name('history');
    Route::get('/stats', [BcvScrapingController::class, 'getStats'])->name('stats');
    
    // Scraping manual (síncrono)
    Route::post('/scrape', [BcvScrapingController::class, 'scrapeManual'])->name('scrape');
    
    // Gestión de Jobs (asíncrono)
    Route::prefix('jobs')->name('jobs.')->group(function () {
        Route::post('/scrape', [BcvScrapingController::class, 'scrapeAsync'])->name('scrape');
        Route::get('/status', [BcvScrapingController::class, 'getJobStatus'])->name('status');
        Route::get('/stats', [BcvScrapingController::class, 'getJobStats'])->name('stats');
        Route::delete('/cancel', [BcvScrapingController::class, 'cancelJob'])->name('cancel');
    });
}); 