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
    
    // Ejecutar scraping manual
    Route::post('/scrape', [BcvScrapingController::class, 'scrapeManual'])->name('scrape');
}); 