# 🏦 BCV Web Scraping System

> Sistema automático de scraping para obtener las tasas de cambio del Banco Central de Venezuela (BCV)

**🌐 Languages**: [English](README.en.md) | **Español**

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-12-FF2D20?style=for-the-badge&logo=laravel&logoColor=white" alt="Laravel 12">
  <img src="https://img.shields.io/badge/PHP-8.3+-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP 8.3+">
  <img src="https://img.shields.io/badge/PostgreSQL-16-4169E1?style=for-the-badge&logo=postgresql&logoColor=white" alt="PostgreSQL">
  <img src="https://img.shields.io/badge/Docker-blue?style=for-the-badge&logo=docker&logoColor=white" alt="Docker">
</p>

## 📋 Descripción

Sistema completo de web scraping desarrollado en Laravel 12 que extrae automáticamente las tasas de cambio del USD desde la página oficial del BCV (https://www.bcv.org.ve/). 

Incluye sistema de colas, programación automática, APIs REST, dashboard web, y herramientas de monitoreo avanzadas.

## ✨ Características Principales

### 🔄 **Scraping Automático**
- **Horarios programados**: Lunes a Viernes 5:00 PM (Venezuela)
- **Sistema de respaldo**: Lunes a Viernes 6:00 PM si falla el principal
- **Scraping de emergencia**: Sábados 12:00 PM si no hay datos recientes
- **Zona horaria**: America/Caracas (UTC-4)

### ⚡ **Sistema de Jobs/Colas**
- **Jobs asíncronos** con Laravel Queue
- **Reintentos inteligentes** (3 intentos con backoff exponencial)
- **Protección anti-overlapping** (evita ejecuciones simultáneas)
- **Timeout**: 5 minutos máximo por job
- **Logging detallado** de todo el proceso

### 🛡️ **Robustez y Confiabilidad**
- **Manejo de errores** con reintentos automáticos
- **Headers HTTP realistas** para evitar bloqueos
- **Delays configurables** entre requests
- **Validación de datos** extraídos
- **Verificación de datos recientes** (evita scraping innecesario)

### 📊 **APIs REST Completas**
- **GET** `/api/bcv/latest` - Última tasa de cambio
- **GET** `/api/bcv/history` - Historial paginado
- **GET** `/api/bcv/stats` - Estadísticas generales
- **POST** `/api/bcv/scrape` - Scraping manual síncrono
- **POST** `/api/bcv/jobs/scrape` - Lanzar job asíncrono
- **GET** `/api/bcv/jobs/status` - Estado actual de jobs
- **GET** `/api/bcv/jobs/stats` - Estadísticas de jobs

### 🎮 **Dashboard Web**
- **Interfaz moderna** con datos en tiempo real
- **Visualización de tasas** con formato venezolano
- **Historial de scrapings** con paginación
- **Estadísticas del sistema** 

### 🖥️ **Comandos Artisan Avanzados**
```bash
# Scraping automático (para scheduler)
php artisan bcv:scrape auto

# Scraping manual
php artisan bcv:scrape manual [--sync] [--force]

# Gestión de jobs
php artisan bcv:scrape job
php artisan bcv:scrape status
php artisan bcv:scrape stats
php artisan bcv:scrape clear

# Información de horarios
php artisan bcv:scrape time
```

## 🚀 Instalación y Configuración

### **Prerequisitos**
- Docker y Docker Compose
- Git

### **1. Clonar el Repositorio**
```bash
git clone <repository-url>
cd webscraping-bcv
```

### **2. Configurar Entorno**
```bash
# Copiar archivo de entorno
cp .env.example .env

# Configurar variables principales en .env
APP_NAME="BCV Webscraping"
APP_TIMEZONE=America/Caracas
DB_CONNECTION=pgsql
QUEUE_CONNECTION=database
CACHE_STORE=database

# Variables de scraping
BCV_USER_AGENT="Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)..."
BCV_TIMEOUT=30
BCV_DELAY=2
BCV_MAX_RETRIES=3
```

### **3. Iniciar con Laravel Sail**
```bash
# Instalar dependencias
docker run --rm -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html laravelsail/php83-composer:latest \
    composer install --ignore-platform-reqs

# Iniciar contenedores
./vendor/bin/sail up -d

# Generar clave de aplicación
./vendor/bin/sail artisan key:generate

# Ejecutar migraciones
./vendor/bin/sail artisan migrate

# Opcional: Ejecutar seeders
./vendor/bin/sail artisan db:seed
```

### **4. Configurar Worker de Colas**
```bash
# En desarrollo (terminal separado)
./vendor/bin/sail artisan queue:work --queue=scraping,default --tries=3 --timeout=300

# En producción (usar Supervisor - ver SCHEDULER_SETUP.md)
```

### **5. Configurar Scheduler (Producción)**
```bash
# Agregar al crontab del servidor
* * * * * cd /ruta/al/proyecto && php artisan schedule:run >> /dev/null 2>&1
```

## 📊 Estructura de Base de Datos

### **Tabla: `bcv_exchange_rates`**
```sql
- id (bigint, primary key)
- usd_rate (decimal 10,4) - Tasa del USD en Bolívares
- value_date (date) - Fecha de valor según BCV
- scraped_at (timestamp) - Momento del scraping
- currency_code (string) - Código de moneda (USD)
- raw_data (text) - Datos crudos para debugging
- source_url (string) - URL fuente del scraping
- created_at, updated_at (timestamps)

# Índices optimizados para consultas rápidas
```

## 🎯 Uso del Sistema

### **APIs REST**

#### **📈 Obtener Última Tasa**
```bash
curl -X GET "http://localhost:8000/api/bcv/latest"

# Respuesta
{
  "success": true,
  "data": {
    "id": 5,
    "usd_rate": "105.4527",
    "formatted_rate": "105,45 Bs.",
    "value_date": "2025-06-25",
    "scraped_at": "2025-06-21 15:52:52"
  }
}
```

#### **📊 Historial Paginado**
```bash
curl -X GET "http://localhost:8000/api/bcv/history?page=1&per_page=10"
```

#### **🔄 Scraping Manual**
```bash
# Síncrono
curl -X POST "http://localhost:8000/api/bcv/scrape"

# Asíncrono (Job)
curl -X POST "http://localhost:8000/api/bcv/jobs/scrape"
```

#### **📊 Estado de Jobs**
```bash
curl -X GET "http://localhost:8000/api/bcv/jobs/status"

# Respuesta
{
  "success": true,
  "data": {
    "current_job": {
      "status": "completed",
      "job_id": "2",
      "execution_time_ms": 283.63,
      "is_manual": true
    },
    "last_success": { ... },
    "last_failure": null,
    "has_active_job": false
  }
}
```

### **Dashboard Web**
- **URL**: `http://localhost:8000/bcv`
- **Funcionalidades**: Visualización de datos, historial, estadísticas

### **Comandos de Terminal**

#### **🔍 Información del Sistema**
```bash
# Ver horarios y configuración
./vendor/bin/sail artisan bcv:scrape time

# Estado actual
./vendor/bin/sail artisan bcv:scrape status

# Estadísticas
./vendor/bin/sail artisan bcv:scrape stats
```

#### **🛠️ Gestión Manual**
```bash
# Scraping manual síncrono
./vendor/bin/sail artisan bcv:scrape manual --sync

# Lanzar job asíncrono
./vendor/bin/sail artisan bcv:scrape job

# Forzar scraping (ignora datos recientes)
./vendor/bin/sail artisan bcv:scrape manual --force --sync
```

#### **🧹 Mantenimiento**
```bash
# Limpiar cache de jobs
./vendor/bin/sail artisan bcv:scrape clear

# Ver tareas programadas
./vendor/bin/sail artisan schedule:list

# Ejecutar scheduler manualmente
./vendor/bin/sail artisan schedule:run --verbose
```

## ⏰ Programación Automática

### **Horarios Configurados (Venezuela - UTC-4)**

| Tipo | Frecuencia | Horario | Condición |
|------|------------|---------|-----------|
| **Principal** | Lun-Vie | 17:00 (5:00 PM) | Siempre |
| **Respaldo** | Lun-Vie | 18:00 (6:00 PM) | Solo si no hay datos del día |
| **Emergencia** | Sábados | 12:00 (12:00 PM) | Solo si no hay datos de 3 días |

### **Próximas Ejecuciones**
```bash
# Ver próximas ejecuciones
./vendor/bin/sail artisan schedule:list

# Información detallada de horarios
./vendor/bin/sail artisan bcv:scrape time
```

## 🏗️ Arquitectura del Sistema

### **Componentes Principales**

#### **🔧 Servicios**
- **`BcvScrapingService`**: Lógica principal de scraping
- **`BcvScrapingServiceProvider`**: Registro de servicios

#### **⚡ Jobs/Colas**
- **`BcvScrapingJob`**: Job asíncrono con reintentos
- **Sistema de colas**: Laravel Queue con base de datos

#### **🎮 Controladores**
- **`BcvScrapingController`**: APIs REST y dashboard

#### **💾 Modelos**
- **`BcvExchangeRate`**: Modelo Eloquent con scopes y mutators

#### **🖥️ Comandos**
- **`BcvScrapingCommand`**: Comando Artisan multifuncional

#### **📅 Scheduler**
- **`bootstrap/app.php`**: Configuración de tareas programadas

### **Flujo de Datos**
```
Scheduler → Command → Job → Service → HTTP Client → BCV Website
                                ↓
Database ← Model ← Service ← Parser ← HTML Response
```

## 🔧 Tecnologías Utilizadas

### **Backend**
- **Laravel 12** - Framework PHP
- **PHP 8.3+** - Lenguaje de programación
- **PostgreSQL 16** - Base de datos
- **Redis** - Cache y sesiones (opcional)

### **Scraping**
- **Guzzle HTTP 7.x** - Cliente HTTP
- **Symfony DomCrawler 7.x** - Parser HTML
- **Symfony CSS Selector 7.x** - Selectores CSS

### **Infraestructura**
- **Laravel Sail** - Entorno de desarrollo Docker
- **Docker & Docker Compose** - Contenerización
- **Laravel Queue** - Sistema de colas
- **Laravel Scheduler** - Tareas programadas

### **Frontend**
- **Blade Templates** - Plantillas PHP
- **Tailwind CSS** - Framework CSS (opcional)
- **Alpine.js** - JavaScript reactivo (opcional)

## 📈 Monitoreo y Logs

### **Archivos de Log**
- **Laravel**: `storage/logs/laravel.log`
- **Scheduler**: `storage/logs/bcv-scheduler.log`
- **Queue Worker**: `storage/logs/worker.log` (producción)

### **Métricas Disponibles**
- **Total de registros** en base de datos
- **Tasa de éxito** de jobs
- **Tiempo promedio** de ejecución
- **Últimos éxitos y fallos**
- **Estado de colas** (jobs pendientes)

### **Comandos de Monitoreo**
```bash
# Estado completo del sistema
./vendor/bin/sail artisan bcv:scrape status

# Ver logs en tiempo real
./vendor/bin/sail logs -f laravel.test

# Verificar worker de colas
ps aux | grep "queue:work"
```

## 🛠️ Desarrollo y Testing

### **Ambiente de Desarrollo**
```bash
# Iniciar entorno
./vendor/bin/sail up -d

# Ver logs
./vendor/bin/sail logs -f

# Acceder al contenedor
./vendor/bin/sail exec laravel.test bash

# Ejecutar tests
./vendor/bin/sail artisan test
```

### **Testing Manual**
```bash
# Probar scraping
./vendor/bin/sail exec laravel.test curl -s "http://localhost:8000/api/bcv/latest" | jq .

# Probar job
./vendor/bin/sail exec laravel.test curl -s -X POST "http://localhost:8000/api/bcv/jobs/scrape" | jq .

# Verificar scheduler
./vendor/bin/sail artisan schedule:run --verbose
```

## 🚀 Despliegue en Producción

Ver archivo detallado: **[SCHEDULER_SETUP.md](SCHEDULER_SETUP.md)**

### **Pasos Principales**
1. **Servidor**: Ubuntu/CentOS con Nginx/Apache
2. **PHP**: Versión 8.3+ con extensiones necesarias
3. **Base de Datos**: PostgreSQL 16+
4. **Crontab**: Configurar scheduler de Laravel
5. **Supervisor**: Worker de colas persistente
6. **SSL**: Certificado HTTPS (Let's Encrypt)

### **Variables de Entorno Producción**
```bash
APP_ENV=production
APP_DEBUG=false
APP_TIMEZONE=America/Caracas
QUEUE_CONNECTION=database
CACHE_STORE=redis
MAIL_ADMIN_EMAIL=admin@tudominio.com
```

## 📄 Documentación Adicional

- **[README.en.md](README.en.md)** - English version of this documentation
- **[SCHEDULER_SETUP.md](SCHEDULER_SETUP.md)** - Configuración detallada del scheduler
- **Logs**: `storage/logs/` - Logs del sistema
- **Migraciones**: `database/migrations/` - Estructura de BD
- **Comandos**: `app/Console/Commands/` - Comandos Artisan

## 🤝 Contribuir

1. Fork del proyecto
2. Crear rama feature (`git checkout -b feature/nueva-funcionalidad`)
3. Commit cambios (`git commit -am 'Agregar nueva funcionalidad'`)
4. Push a la rama (`git push origin feature/nueva-funcionalidad`)
5. Crear Pull Request

## 📋 Changelog

### **v1.0.0** - 2025-06-21
- ✅ Sistema de scraping completo
- ✅ Jobs/colas asíncronos
- ✅ APIs REST completas
- ✅ Dashboard web
- ✅ Scheduler automático
- ✅ Comandos Artisan avanzados
- ✅ Monitoreo y logging
- ✅ Documentación completa

## 📞 Soporte

Para preguntas, problemas o sugerencias:

- **Issues**: [GitHub Issues](link-to-issues)
- **Email**: [tu-email@dominio.com](mailto:tu-email@dominio.com)
- **Documentación**: Ver archivos `.md` en el repositorio

## 📜 Licencia

Este proyecto está licenciado bajo la [MIT License](LICENSE).

---

**Desarrollado con ❤️ para automatizar el monitoreo de tasas de cambio del BCV** 🇻🇪
