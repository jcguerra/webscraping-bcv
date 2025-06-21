<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bcv_exchange_rates', function (Blueprint $table) {
            $table->id();
            
            // Valor del dólar USD (con 4 decimales para precisión)
            $table->decimal('usd_rate', 10, 4);
            
            // Fecha de valor de la actualización del BCV
            $table->date('value_date');
            
            // Timestamp de cuando se hizo el scraping
            $table->timestamp('scraped_at');
            
            // Campos adicionales útiles
            $table->string('currency_code', 3)->default('USD');
            $table->text('raw_data')->nullable(); // Para guardar data cruda en caso de debugging
            $table->string('source_url')->default('https://www.bcv.org.ve/');
            
            // Índices para optimizar consultas
            $table->index('value_date');
            $table->index('scraped_at');
            $table->index(['currency_code', 'value_date']);
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bcv_exchange_rates');
    }
};
