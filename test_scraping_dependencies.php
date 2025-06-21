<?php

require_once 'vendor/autoload.php';

use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;

echo "🔧 Probando dependencias de Web Scraping...\n\n";

try {
    // 1. Probar Guzzle HTTP Client
    echo "✅ Guzzle HTTP Client: ";
    $client = new Client([
        'timeout' => 10,
        'headers' => [
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36'
        ]
    ]);
    echo "INSTALADO ✓\n";

    // 2. Probar Symfony DomCrawler
    echo "✅ Symfony DomCrawler: ";
    $html = '<html><body><h1>Test</h1><p class="content">Hello World</p></body></html>';
    $crawler = new Crawler($html);
    $title = $crawler->filter('h1')->text();
    echo "INSTALADO ✓ (Test: '$title')\n";

    // 3. Probar CSS Selector
    echo "✅ Symfony CSS Selector: ";
    $content = $crawler->filter('.content')->text();
    echo "INSTALADO ✓ (Test: '$content')\n";

    echo "\n🎉 ¡Todas las dependencias funcionan correctamente!\n";
    echo "📦 Versiones instaladas:\n";
    echo "   - Guzzle: " . GuzzleHttp\Client::MAJOR_VERSION . "\n";
    echo "   - Symfony DomCrawler: 7.x\n";
    echo "   - Symfony CSS Selector: 7.x\n";

} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
} 