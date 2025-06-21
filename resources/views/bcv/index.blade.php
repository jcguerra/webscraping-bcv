<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Web Scraping BCV - Tasas de Cambio</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
            color: #333;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 30px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .current-rate {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .rate-value {
            font-size: 3em;
            font-weight: bold;
            margin: 10px 0;
        }
        
        .rate-date {
            font-size: 1.1em;
            opacity: 0.9;
        }
        
        .actions {
            text-align: center;
            margin: 30px 0;
        }
        
        .btn {
            background: #4CAF50;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            margin: 0 10px;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn:hover {
            background: #45a049;
        }
        
        .btn-secondary {
            background: #2196F3;
        }
        
        .btn-secondary:hover {
            background: #1976D2;
        }
        
        .history {
            margin-top: 40px;
        }
        
        .history-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .history-table th,
        .history-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .history-table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #666;
            font-style: italic;
        }
        
        .status {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .status.current {
            background: #d4edda;
            color: #155724;
        }
        
        .status.old {
            background: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🏦 Web Scraping BCV</h1>
            <p>Tasas de Cambio del Banco Central de Venezuela</p>
        </div>

        @if($todayRate)
            <div class="current-rate">
                <h2>💱 Tasa Actual USD</h2>
                <div class="rate-value">{{ $todayRate->formatted_rate }}</div>
                <div class="rate-date">
                    📅 Fecha Valor: {{ $todayRate->value_date->format('d/m/Y') }}
                    <br>
                    🕒 Actualizado: {{ $todayRate->scraped_at->format('d/m/Y H:i:s') }}
                </div>
            </div>
        @else
            <div class="current-rate">
                <h2>💱 Tasa Actual USD</h2>
                <div class="no-data">
                    No hay datos disponibles para hoy
                    <br>
                    <small>Ejecuta el scraping para obtener la tasa actual</small>
                </div>
            </div>
        @endif

        <div class="actions">
            <button class="btn" onclick="scrapeNow()">🔄 Hacer Scraping Ahora</button>
            <a href="/bcv/api/latest" class="btn btn-secondary" target="_blank">📊 Ver API</a>
            <a href="/bcv/api/stats" class="btn btn-secondary" target="_blank">📈 Estadísticas</a>
        </div>

        <div class="history">
            <h3>📋 Historial de Tasas (Últimas 10)</h3>
            
            @if($latestRates->count() > 0)
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>Tasa USD</th>
                            <th>Fecha Valor</th>
                            <th>Scrapeado</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($latestRates as $rate)
                            <tr>
                                <td><strong>{{ $rate->formatted_rate }}</strong></td>
                                <td>{{ $rate->value_date->format('d/m/Y') }}</td>
                                <td>{{ $rate->scraped_at->format('d/m/Y H:i') }}</td>
                                <td>
                                    <span class="status {{ $rate->is_current ? 'current' : 'old' }}">
                                        {{ $rate->is_current ? 'Actual' : 'Histórico' }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="no-data">
                    📝 No hay datos históricos disponibles
                    <br>
                    <small>Los datos aparecerán aquí después del primer scraping</small>
                </div>
            @endif
        </div>
    </div>

    <script>
        function scrapeNow() {
            const btn = event.target;
            btn.textContent = '⏳ Scraping...';
            btn.disabled = true;
            
            fetch('/bcv/api/scrape', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ ' + data.message);
                    location.reload();
                } else {
                    alert('❌ Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('❌ Error: ' + error.message);
            })
            .finally(() => {
                btn.textContent = '🔄 Hacer Scraping Ahora';
                btn.disabled = false;
            });
        }
    </script>
</body>
</html> 