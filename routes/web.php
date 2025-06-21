<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BcvScrapingController;

// Ruta principal - mostrar directamente el dashboard del BCV
Route::get('/', [BcvScrapingController::class, 'index']);

// Rutas para el scraping del BCV
Route::prefix('bcv')->name('bcv.')->group(function () {
    // Dashboard principal
    Route::get('/', [BcvScrapingController::class, 'index'])->name('index');
    
    // API endpoints
    Route::prefix('api')->name('api.')->group(function () {
        Route::get('/latest', [BcvScrapingController::class, 'getLatestRate'])->name('latest');
        Route::get('/history', [BcvScrapingController::class, 'getHistory'])->name('history');
        Route::get('/stats', [BcvScrapingController::class, 'getStats'])->name('stats');
        Route::post('/scrape', [BcvScrapingController::class, 'scrapeManual'])->name('scrape');
    });
});
