<?php

namespace App\Services;

use App\Models\BcvExchangeRate;
use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Exception;

class BcvScrapingService
{
    private Client $client;
    private string $bcvUrl = 'https://www.bcv.org.ve/';
    private int $timeout;
    private int $delay;
    private int $maxRetries;

    public function __construct()
    {
        $this->timeout = (int) env('SCRAPING_TIMEOUT', 30);
        $this->delay = (int) env('SCRAPING_DELAY', 2);
        $this->maxRetries = (int) env('SCRAPING_MAX_RETRIES', 3);
        
        $this->client = new Client([
            'timeout' => $this->timeout,
            'headers' => [
                'User-Agent' => env('SCRAPING_USER_AGENT', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'),
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language' => 'es-ES,es;q=0.9,en;q=0.8',
                'Accept-Encoding' => 'gzip, deflate, br',
                'DNT' => '1',
                'Connection' => 'keep-alive',
                'Upgrade-Insecure-Requests' => '1',
            ],
            'verify' => false, // Para evitar problemas con SSL en desarrollo
        ]);
    }

    /**
     * Scrape del BCV con reintentos automáticos
     */
    public function scrapeExchangeRate(): array
    {
        $attempts = 0;
        $lastException = null;

        while ($attempts < $this->maxRetries) {
            try {
                $attempts++;
                Log::info("BCV Scraping attempt {$attempts}/{$this->maxRetries}");

                // Hacer petición HTTP
                $response = $this->client->get($this->bcvUrl);
                $html = $response->getBody()->getContents();

                // Parsear HTML con DomCrawler
                $crawler = new Crawler($html);

                // Extraer datos
                $usdRate = $this->extractUsdRate($crawler);
                $valueDate = $this->extractValueDate($crawler);

                // Validar datos
                if (!$usdRate || !$valueDate) {
                    throw new Exception('No se pudieron extraer todos los datos necesarios');
                }

                $result = [
                    'success' => true,
                    'data' => [
                        'usd_rate' => $usdRate,
                        'value_date' => $valueDate,
                        'scraped_at' => Carbon::now(),
                        'source_url' => $this->bcvUrl,
                        'raw_data' => $this->extractRawData($crawler),
                    ],
                    'attempts' => $attempts,
                ];

                Log::info('BCV Scraping successful', $result['data']);
                return $result;

            } catch (Exception $e) {
                $lastException = $e;
                Log::warning("BCV Scraping attempt {$attempts} failed: " . $e->getMessage());

                // Esperar antes del siguiente intento
                if ($attempts < $this->maxRetries) {
                    sleep($this->delay);
                }
            }
        }

        // Si llegamos aquí, fallaron todos los intentos
        Log::error('BCV Scraping failed after all attempts', [
            'attempts' => $attempts,
            'error' => $lastException?->getMessage()
        ]);

        return [
            'success' => false,
            'error' => $lastException?->getMessage() ?? 'Error desconocido',
            'attempts' => $attempts,
        ];
    }

    /**
     * Extraer el valor del USD del HTML
     */
    private function extractUsdRate(Crawler $crawler): ?float
    {
        try {
            // Buscar el div con id="dolar"
            $dolarDiv = $crawler->filter('#dolar');
            
            if ($dolarDiv->count() === 0) {
                throw new Exception('No se encontró el div #dolar');
            }

            // Buscar el valor dentro del strong
            $strongElement = $dolarDiv->filter('strong');
            
            if ($strongElement->count() === 0) {
                throw new Exception('No se encontró el elemento strong con el valor USD');
            }

            $rawValue = trim($strongElement->text());
            
            // Limpiar el valor (remover espacios, comas como separadores de miles)
            $cleanValue = str_replace([' ', ','], ['', '.'], $rawValue);
            
            // Convertir a float
            $usdRate = (float) $cleanValue;
            
            if ($usdRate <= 0) {
                throw new Exception("Valor USD inválido: {$rawValue}");
            }
            
            Log::info("USD Rate extracted: {$rawValue} -> {$usdRate}");
            return $usdRate;

        } catch (Exception $e) {
            Log::error('Error extracting USD rate: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Extraer la fecha valor del HTML
     */
    private function extractValueDate(Crawler $crawler): ?Carbon
    {
        try {
            // Buscar el span con class="date-display-single"
            $dateElement = $crawler->filter('.date-display-single');
            
            if ($dateElement->count() === 0) {
                throw new Exception('No se encontró el elemento .date-display-single');
            }

            $rawDate = trim($dateElement->text());
            
            // La fecha viene en formato: "Miércoles, 25 Junio 2025"
            // Necesitamos parsearla a Carbon
            $valueDate = $this->parseSpanishDate($rawDate);
            
            if (!$valueDate) {
                throw new Exception("No se pudo parsear la fecha: {$rawDate}");
            }
            
            Log::info("Value Date extracted: {$rawDate} -> {$valueDate->format('Y-m-d')}");
            return $valueDate;

        } catch (Exception $e) {
            Log::error('Error extracting value date: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Parsear fecha en español a Carbon
     */
    private function parseSpanishDate(string $rawDate): ?Carbon
    {
        try {
            // Limpiar y preparar la fecha
            $cleaned = trim($rawDate);
            
            // Mapear meses en español a inglés
            $meses = [
                'enero' => 'January', 'febrero' => 'February', 'marzo' => 'March',
                'abril' => 'April', 'mayo' => 'May', 'junio' => 'June',
                'julio' => 'July', 'agosto' => 'August', 'septiembre' => 'September',
                'octubre' => 'October', 'noviembre' => 'November', 'diciembre' => 'December'
            ];

            // Reemplazar mes en español por inglés
            foreach ($meses as $esp => $eng) {
                $cleaned = str_ireplace($esp, $eng, $cleaned);
            }

            // Remover día de la semana si está presente
            $cleaned = preg_replace('/^[a-záéíóú]+,\s*/i', '', $cleaned);

            // Intentar parsear con Carbon
            return Carbon::parse($cleaned);

        } catch (Exception $e) {
            Log::error("Error parsing Spanish date '{$rawDate}': " . $e->getMessage());
            return null;
        }
    }

    /**
     * Extraer datos adicionales para debugging
     */
    private function extractRawData(Crawler $crawler): array
    {
        try {
            return [
                'dolar_div_html' => $crawler->filter('#dolar')->count() > 0 
                    ? $crawler->filter('#dolar')->html() 
                    : null,
                'date_element_html' => $crawler->filter('.date-display-single')->count() > 0 
                    ? $crawler->filter('.date-display-single')->html() 
                    : null,
                'scraped_at_timestamp' => Carbon::now()->timestamp,
            ];
        } catch (Exception $e) {
            Log::warning('Error extracting raw data: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Guardar datos en la base de datos
     */
    public function saveExchangeRate(array $data): BcvExchangeRate
    {
        return BcvExchangeRate::create([
            'usd_rate' => $data['usd_rate'],
            'value_date' => $data['value_date'],
            'scraped_at' => $data['scraped_at'],
            'source_url' => $data['source_url'],
            'raw_data' => json_encode($data['raw_data']),
        ]);
    }

    /**
     * Ejecutar scraping completo y guardar en BD
     */
    public function scrapeAndSave(): array
    {
        // Hacer scraping
        $result = $this->scrapeExchangeRate();
        
        if (!$result['success']) {
            return $result;
        }

        try {
            // Guardar en base de datos
            $record = $this->saveExchangeRate($result['data']);
            
            return [
                'success' => true,
                'message' => 'Scraping realizado y datos guardados exitosamente',
                'data' => [
                    'id' => $record->id,
                    'usd_rate' => $record->usd_rate,
                    'value_date' => $record->value_date->format('Y-m-d'),
                    'scraped_at' => $record->scraped_at->format('Y-m-d H:i:s'),
                ],
                'attempts' => $result['attempts'],
            ];

        } catch (Exception $e) {
            Log::error('Error saving scraped data: ' . $e->getMessage());
            
            return [
                'success' => false,
                'error' => 'Error guardando datos: ' . $e->getMessage(),
                'scraped_data' => $result['data'],
            ];
        }
    }
} 