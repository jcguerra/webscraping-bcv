# 📅 Configuración del Scheduler para BCV Scraping

## ⏰ Horarios Configurados

El sistema está configurado para ejecutar scraping automático del BCV con los siguientes horarios (zona horaria Venezuela - America/Caracas):

### 🔄 Scraping Principal
- **Horario**: Lunes a Viernes 5:00 PM (17:00)
- **Comando**: `php artisan bcv:scrape auto`
- **Descripción**: Scraping automático diario durante días laborables

### 🔄 Scraping de Respaldo  
- **Horario**: Lunes a Viernes 6:00 PM (18:00)
- **Comando**: `php artisan bcv:scrape auto --force`
- **Descripción**: Se ejecuta solo si el scraping principal falló
- **Condición**: Salta si ya hay datos del día actual

### 🆘 Scraping de Emergencia
- **Horario**: Sábados 12:00 PM (12:00)
- **Comando**: `php artisan bcv:scrape auto --force`
- **Descripción**: Se ejecuta solo si no hay datos de los últimos 3 días
- **Condición**: Salta si hay datos recientes

## 🚀 Configuración en Producción

### 1. Configurar Crontab

Agregar la siguiente línea al crontab del servidor:

```bash
# BCV Scraping Scheduler - Laravel
* * * * * cd /ruta/al/proyecto && php artisan schedule:run >> /dev/null 2>&1
```

### 2. Verificar Configuración

```bash
# Ver tareas programadas
php artisan schedule:list

# Ver información de horarios
php artisan bcv:scrape time

# Probar scheduler manualmente
php artisan schedule:run
```

### 3. Logs y Monitoreo

Los logs del scheduler se guardan en:
- **Archivo**: `storage/logs/bcv-scheduler.log`
- **Logs de Laravel**: `storage/logs/laravel.log`

### 4. Comandos Útiles

```bash
# Información de horarios actual
php artisan bcv:scrape time

# Scraping manual (emergencia)
php artisan bcv:scrape manual --sync

# Estado del sistema
php artisan bcv:scrape status

# Limpiar cache de jobs
php artisan bcv:scrape clear
```

## 🌍 Zona Horaria

- **Zona horaria de la app**: America/Caracas (UTC-4)
- **Configuración**: `config/app.php` → `timezone`
- **Variable de entorno**: `APP_TIMEZONE=America/Caracas`

## 📊 Monitoreo

### APIs de Monitoreo:
- `GET /api/bcv/jobs/status` - Estado actual
- `GET /api/bcv/jobs/stats` - Estadísticas
- `GET /api/bcv/stats` - Datos generales

### Comandos de Monitoreo:
- `php artisan bcv:scrape status`
- `php artisan bcv:scrape stats`
- `php artisan bcv:scrape time`

## ⚠️ Notas Importantes

1. **Worker de Colas**: Asegúrate de tener un worker activo:
   ```bash
   php artisan queue:work --queue=scraping,default --tries=3 --timeout=300
   ```

2. **Supervisor**: En producción, usa Supervisor para mantener el worker:
   ```ini
   [program:laravel-worker]
   process_name=%(program_name)s_%(process_num)02d
   command=php /ruta/al/proyecto/artisan queue:work --queue=scraping,default --tries=3 --timeout=300
   autostart=true
   autorestart=true
   user=www-data
   numprocs=1
   redirect_stderr=true
   stdout_logfile=/ruta/al/proyecto/storage/logs/worker.log
   ```

3. **Notificaciones**: Configurar email para recibir notificaciones de fallos:
   - Variable: `MAIL_ADMIN_EMAIL` en .env
   - Se envían emails automáticamente cuando fallan las tareas

## 🧪 Testing

```bash
# Simular ejecución del scheduler
php artisan schedule:run --verbose

# Ver próximas ejecuciones
php artisan schedule:list

# Probar horarios
php artisan bcv:scrape time
``` 