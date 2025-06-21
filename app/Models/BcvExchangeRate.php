<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;

class BcvExchangeRate extends Model
{
    use HasFactory;

    /**
     * Nombre de la tabla
     */
    protected $table = 'bcv_exchange_rates';

    /**
     * Campos que pueden ser asignados masivamente
     */
    protected $fillable = [
        'usd_rate',
        'value_date',
        'scraped_at',
        'currency_code',
        'raw_data',
        'source_url',
    ];

    /**
     * Campos que deben ser tratados como fechas
     */
    protected $casts = [
        'value_date' => 'date',
        'scraped_at' => 'datetime',
        'usd_rate' => 'decimal:4',
        'raw_data' => 'array',
    ];

    /**
     * Scopes para consultas frecuentes
     */
    
    /**
     * Obtener la tasa más reciente por fecha de valor y fecha de scraping
     */
    public function scopeByLatestValue($query)
    {
        return $query->orderBy('value_date', 'desc')
                    ->orderBy('scraped_at', 'desc');
    }

    /**
     * Obtener tasas por fecha específica
     */
    public function scopeByDate($query, $date)
    {
        return $query->whereDate('value_date', $date);
    }

    /**
     * Obtener tasas del día actual
     */
    public function scopeToday($query)
    {
        return $query->whereDate('value_date', Carbon::today());
    }

    /**
     * Obtener la tasa actual más reciente
     */
    public function scopeCurrent($query)
    {
        return $query->latest('scraped_at');
    }

    /**
     * Métodos de ayuda
     */
    
    /**
     * Obtener la tasa formateada para mostrar
     */
    public function getFormattedRateAttribute()
    {
        return number_format($this->usd_rate, 2, ',', '.') . ' Bs.';
    }

    /**
     * Verificar si la tasa es del día actual
     */
    public function getIsCurrentAttribute()
    {
        return $this->value_date->isToday();
    }
}
