# 🏦 BCV Web Scraping System

> Automated scraping system to retrieve exchange rates from Venezuela's Central Bank (BCV)

**🌐 Languages**: **English** | [Español](README.md)

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-12-FF2D20?style=for-the-badge&logo=laravel&logoColor=white" alt="Laravel 12">
  <img src="https://img.shields.io/badge/PHP-8.3+-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP 8.3+">
  <img src="https://img.shields.io/badge/PostgreSQL-16-4169E1?style=for-the-badge&logo=postgresql&logoColor=white" alt="PostgreSQL">
  <img src="https://img.shields.io/badge/Docker-blue?style=for-the-badge&logo=docker&logoColor=white" alt="Docker">
</p>

<p align="center">
  <img src="https://img.shields.io/badge/Tests-74_passed-28A745?style=for-the-badge&logo=checkmarx&logoColor=white" alt="74 Tests">
  <img src="https://img.shields.io/badge/Coverage-64%25-FFA500?style=for-the-badge&logo=codecov&logoColor=white" alt="64% Coverage">
  <img src="https://img.shields.io/badge/Status-Production_Ready-28A745?style=for-the-badge&logo=checkmarx&logoColor=white" alt="Production Ready">
  <img src="https://img.shields.io/badge/Automation-Fully_Automated-00D4AA?style=for-the-badge&logo=robot&logoColor=white" alt="Fully Automated">
</p>

## 📋 Description

Complete web scraping system developed in Laravel 12 that automatically extracts USD exchange rates from the official BCV website (https://www.bcv.org.ve/). 

Includes queue system, automatic scheduling, REST APIs, web dashboard, and advanced monitoring tools.

## ✨ Key Features

### 🔄 **Automatic Scraping**
- **Scheduled times**: Monday to Friday 5:00 PM (Venezuela)
- **Backup system**: Monday to Friday 6:00 PM if main fails
- **Emergency scraping**: Saturdays 12:00 PM if no recent data
- **Timezone**: America/Caracas (UTC-4)

### ⚡ **Jobs/Queue System**
- **Asynchronous jobs** with Laravel Queue
- **Smart retries** (3 attempts with exponential backoff)
- **Anti-overlapping protection** (prevents simultaneous executions)
- **Timeout**: 5 minutes maximum per job
- **Detailed logging** of entire process

### 🛡️ **Robustness and Reliability**
- **Error handling** with automatic retries
- **Realistic HTTP headers** to avoid blocking
- **Configurable delays** between requests
- **Data validation** extracted
- **Recent data verification** (avoids unnecessary scraping)

### 📊 **Complete REST APIs**
- **GET** `/api/bcv/latest` - Latest exchange rate
- **GET** `/api/bcv/history` - Paginated history
- **GET** `/api/bcv/stats` - General statistics
- **POST** `/api/bcv/scrape` - Manual synchronous scraping
- **POST** `/api/bcv/jobs/scrape` - Launch asynchronous job
- **GET** `/api/bcv/jobs/status` - Current job status
- **GET** `/api/bcv/jobs/stats` - Job statistics

### 🎮 **Web Dashboard**
- **Modern interface** with real-time data
- **Rate visualization** with Venezuelan format
- **Scraping history** with pagination
- **System statistics** 

### 🖥️ **Advanced Artisan Commands**
```bash
# Automatic scraping (for scheduler)
php artisan bcv:scrape auto

# Manual scraping
php artisan bcv:scrape manual [--sync] [--force]

# Job management
php artisan bcv:scrape job
php artisan bcv:scrape status
php artisan bcv:scrape stats
php artisan bcv:scrape clear

# Schedule information
php artisan bcv:scrape time
```

### 🧪 **Complete Automated Testing**
- **74 automated tests** (52 unit + 22 feature)
- **64% coverage** of the system with detailed metrics
- **Advanced factory** with multiple data states
- **Mocking and reflection** for robust tests
- **Performance tests** with benchmarks
- **Robustness tests** for edge cases
- **Automated quality validation** of code

## 🚀 Installation and Configuration

### **Prerequisites**
- Docker and Docker Compose
- Git

### **1. Clone Repository**
```bash
git clone <repository-url>
cd webscraping-bcv
```

### **2. Configure Environment**
```bash
# Copy environment file
cp .env.example .env

# Configure main variables in .env
APP_NAME="BCV Webscraping"
APP_TIMEZONE=America/Caracas
DB_CONNECTION=pgsql
QUEUE_CONNECTION=database
CACHE_STORE=database

# Scraping variables
BCV_USER_AGENT="Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)..."
BCV_TIMEOUT=30
BCV_DELAY=2
BCV_MAX_RETRIES=3
```

### **3. Start with Laravel Sail**
```bash
# Install dependencies
docker run --rm -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html laravelsail/php83-composer:latest \
    composer install --ignore-platform-reqs

# Start containers
./vendor/bin/sail up -d

# Generate application key
./vendor/bin/sail artisan key:generate

# Run migrations
./vendor/bin/sail artisan migrate

# Optional: Run seeders
./vendor/bin/sail artisan db:seed
```

### **4. Configure Queue Worker**
```bash
# In development (separate terminal)
./vendor/bin/sail artisan queue:work --queue=scraping,default --tries=3 --timeout=300

# In production (use Supervisor - see SCHEDULER_SETUP.md)
```

### **5. Configure Scheduler (Production)**
```bash
# Add to server crontab
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

## 📊 Database Structure

### **Table: `bcv_exchange_rates`**
```sql
- id (bigint, primary key)
- usd_rate (decimal 10,4) - USD rate in Bolivars
- value_date (date) - Value date according to BCV
- scraped_at (timestamp) - Scraping moment
- currency_code (string) - Currency code (USD)
- raw_data (text) - Raw data for debugging
- source_url (string) - Scraping source URL
- created_at, updated_at (timestamps)

# Optimized indexes for fast queries
```

## 🎯 System Usage

### **REST APIs**

#### **📈 Get Latest Rate**
```bash
curl -X GET "http://localhost:8000/api/bcv/latest"

# Response
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

#### **📊 Paginated History**
```bash
curl -X GET "http://localhost:8000/api/bcv/history?page=1&per_page=10"
```

#### **🔄 Manual Scraping**
```bash
# Synchronous
curl -X POST "http://localhost:8000/api/bcv/scrape"

# Asynchronous (Job)
curl -X POST "http://localhost:8000/api/bcv/jobs/scrape"
```

#### **📊 Job Status**
```bash
curl -X GET "http://localhost:8000/api/bcv/jobs/status"

# Response
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

### **Web Dashboard**
- **URL**: `http://localhost:8000/bcv`
- **Features**: Data visualization, history, statistics

### **Terminal Commands**

#### **🔍 System Information**
```bash
# View schedules and configuration
./vendor/bin/sail artisan bcv:scrape time

# Current status
./vendor/bin/sail artisan bcv:scrape status

# Statistics
./vendor/bin/sail artisan bcv:scrape stats
```

#### **🛠️ Manual Management**
```bash
# Manual synchronous scraping
./vendor/bin/sail artisan bcv:scrape manual --sync

# Launch asynchronous job
./vendor/bin/sail artisan bcv:scrape job

# Force scraping (ignore recent data)
./vendor/bin/sail artisan bcv:scrape manual --force --sync
```

#### **🧹 Maintenance**
```bash
# Clear job cache
./vendor/bin/sail artisan bcv:scrape clear

# View scheduled tasks
./vendor/bin/sail artisan schedule:list

# Run scheduler manually
./vendor/bin/sail artisan schedule:run --verbose
```

## ⏰ Automatic Scheduling

### **Configured Schedules (Venezuela - UTC-4)**

| Type | Frequency | Schedule | Condition |
|------|-----------|----------|-----------|
| **Main** | Mon-Fri | 17:00 (5:00 PM) | Always |
| **Backup** | Mon-Fri | 18:00 (6:00 PM) | Only if no data for the day |
| **Emergency** | Saturdays | 12:00 (12:00 PM) | Only if no data for 3 days |

### **Next Executions**
```bash
# View next executions
./vendor/bin/sail artisan schedule:list

# Detailed schedule information
./vendor/bin/sail artisan bcv:scrape time
```

## 🏗️ System Architecture

### **Main Components**

#### **🔧 Services**
- **`BcvScrapingService`**: Main scraping logic
- **`BcvScrapingServiceProvider`**: Service registration

#### **⚡ Jobs/Queues**
- **`BcvScrapingJob`**: Asynchronous job with retries
- **Queue system**: Laravel Queue with database

#### **🎮 Controllers**
- **`BcvScrapingController`**: REST APIs and dashboard

#### **💾 Models**
- **`BcvExchangeRate`**: Eloquent model with scopes and mutators

#### **🖥️ Commands**
- **`BcvScrapingCommand`**: Multifunctional Artisan command

#### **📅 Scheduler**
- **`bootstrap/app.php`**: Scheduled task configuration

### **Data Flow**
```
Scheduler → Command → Job → Service → HTTP Client → BCV Website
                                ↓
Database ← Model ← Service ← Parser ← HTML Response
```

## 🔧 Technologies Used

### **Backend**
- **Laravel 12** - PHP Framework
- **PHP 8.3+** - Programming language
- **PostgreSQL 16** - Database
- **Redis** - Cache and sessions (optional)

### **Scraping**
- **Guzzle HTTP 7.x** - HTTP Client
- **Symfony DomCrawler 7.x** - HTML Parser
- **Symfony CSS Selector 7.x** - CSS Selectors

### **Infrastructure**
- **Laravel Sail** - Docker development environment
- **Docker & Docker Compose** - Containerization
- **Laravel Queue** - Queue system
- **Laravel Scheduler** - Scheduled tasks

### **Frontend**
- **Blade Templates** - PHP templates
- **Tailwind CSS** - CSS Framework (optional)
- **Alpine.js** - Reactive JavaScript (optional)

## 📈 Monitoring and Logs

### **Log Files**
- **Laravel**: `storage/logs/laravel.log`
- **Scheduler**: `storage/logs/bcv-scheduler.log`
- **Queue Worker**: `storage/logs/worker.log` (production)

### **Available Metrics**
- **Total records** in database
- **Job success rate**
- **Average execution time**
- **Latest successes and failures**
- **Queue status** (pending jobs)

### **Monitoring Commands**
```bash
# Complete system status
./vendor/bin/sail artisan bcv:scrape status

# View logs in real time
./vendor/bin/sail logs -f laravel.test

# Check queue worker
ps aux | grep "queue:work"
```

## 🛠️ Development and Testing

### **Development Environment**
```bash
# Start environment
./vendor/bin/sail up -d

# View logs
./vendor/bin/sail logs -f

# Access container
./vendor/bin/sail exec laravel.test bash

# Run tests
./vendor/bin/sail artisan test
```

### **Manual Testing**
```bash
# Test scraping
./vendor/bin/sail exec laravel.test curl -s "http://localhost:8000/api/bcv/latest" | jq .

# Test job
./vendor/bin/sail exec laravel.test curl -s -X POST "http://localhost:8000/api/bcv/jobs/scrape" | jq .

# Check scheduler
./vendor/bin/sail artisan schedule:run --verbose
```

## 🚀 Production Deployment

See detailed file: **[SCHEDULER_SETUP.md](SCHEDULER_SETUP.md)**

### **Main Steps**
1. **Server**: Ubuntu/CentOS with Nginx/Apache
2. **PHP**: Version 8.3+ with necessary extensions
3. **Database**: PostgreSQL 16+
4. **Crontab**: Configure Laravel scheduler
5. **Supervisor**: Persistent queue worker
6. **SSL**: HTTPS certificate (Let's Encrypt)

### **Production Environment Variables**
```bash
APP_ENV=production
APP_DEBUG=false
APP_TIMEZONE=America/Caracas
QUEUE_CONNECTION=database
CACHE_STORE=redis
MAIL_ADMIN_EMAIL=admin@yourdomain.com
```

## 📄 Additional Documentation

- **[README.md](README.md)** - Versión en español de esta documentación
- **[SCHEDULER_SETUP.md](SCHEDULER_SETUP.md)** - Detailed scheduler configuration
- **Logs**: `storage/logs/` - System logs
- **Migrations**: `database/migrations/` - Database structure
- **Commands**: `app/Console/Commands/` - Artisan commands

## 🤝 Contributing

1. Fork the project
2. Create feature branch (`git checkout -b feature/new-feature`)
3. Commit changes (`git commit -am 'Add new feature'`)
4. Push to branch (`git push origin feature/new-feature`)
5. Create Pull Request

## 📋 Changelog

### **v1.0.0** - 2025-06-21
- ✅ Complete scraping system
- ✅ Asynchronous jobs/queues
- ✅ Complete REST APIs
- ✅ Web dashboard
- ✅ Automatic scheduler
- ✅ Advanced Artisan commands
- ✅ Monitoring and logging
- ✅ Complete documentation

## 📞 Support

For questions, issues or suggestions:

- **Issues**: [GitHub Issues](link-to-issues)
- **Email**: [your-email@domain.com](mailto:your-email@domain.com)
- **Documentation**: See `.md` files in the repository

## 📜 License

This project is licensed under the [MIT License](LICENSE).

---

**Developed with ❤️ to automate BCV exchange rate monitoring** 🇻🇪 