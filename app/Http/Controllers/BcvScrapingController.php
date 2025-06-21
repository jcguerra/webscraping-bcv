<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\BcvExchangeRate;
use App\Services\BcvScrapingService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

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
        
        return view('bcv.index', compact('latestRates', 'todayRate'));
    }

    /**
     * Obtener la tasa más reciente via API
     */
    public function getLatestRate(): JsonResponse
    {
        $latestRate = BcvExchangeRate::latest()->first();
        
        if (!$latestRate) {
            return response()->json([
                'success' => false,
                'message' => 'No hay datos disponibles'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'usd_rate' => $latestRate->usd_rate,
                'formatted_rate' => $latestRate->formatted_rate,
                'value_date' => $latestRate->value_date->format('d/m/Y'),
                'scraped_at' => $latestRate->scraped_at->format('d/m/Y H:i:s'),
                'is_current' => $latestRate->is_current,
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
        if ($request->has('from_date')) {
            $query->whereDate('value_date', '>=', $request->from_date);
        }
        
        if ($request->has('to_date')) {
            $query->whereDate('value_date', '<=', $request->to_date);
        }
        
        $rates = $query->paginate($request->get('per_page', 15));
        
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
        try {
            // Ejecutar scraping usando el servicio
            $result = $this->scrapingService->scrapeAndSave();
            
            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => $result['message'],
                    'data' => $result['data'],
                    'meta' => [
                        'attempts' => $result['attempts'],
                        'scraped_at' => now()->format('Y-m-d H:i:s')
                    ]
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => $result['error'],
                    'meta' => [
                        'attempts' => $result['attempts'] ?? 0,
                        'scraped_at' => now()->format('Y-m-d H:i:s')
                    ]
                ], 422);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error interno del servidor: ' . $e->getMessage(),
                'meta' => [
                    'scraped_at' => now()->format('Y-m-d H:i:s')
                ]
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
        
        $stats = [
            'total_records' => $totalRecords,
            'latest_rate' => $latestRate?->usd_rate,
            'latest_date' => $latestRate?->value_date?->format('d/m/Y'),
            'oldest_date' => $oldestRate?->value_date?->format('d/m/Y'),
            'last_scraping' => $latestRate?->scraped_at?->format('d/m/Y H:i:s'),
        ];
        
        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }
}
