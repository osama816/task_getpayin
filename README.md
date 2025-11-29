# Flash-Sale Checkout API Project - Laravel 12

## Overview

This is a complete API project built on Laravel 12 for managing flash sale operations with strong guarantees against overselling and inventory management in a high-concurrency environment. The project implements a temporary "Hold" system for products before order completion, with secure handling of webhooks from payment gateways.

## Key Features

- ✅ **Safe Inventory Management**: Prevents overselling under high load using DB transactions and row locking
- ✅ **Temporary Hold System**: Reserves products for 2 minutes (configurable)
- ✅ **Webhook Idempotency**: Secure handling of duplicate or out-of-order webhooks
- ✅ **Automatic Expiry**: Background system to release inventory from expired holds
- ✅ **Smart Caching**: Temporary storage of product results with automatic invalidation
- ✅ **API Documentation**: Interactive Swagger/OpenAPI documentation
- ✅ **Log Viewer**: Built-in web interface for monitoring application logs and errors

## Technical Requirements

- PHP ^8.2
- Laravel 12
- MySQL (InnoDB) - Recommended
- Redis (Optional) - For cache and queues, but also works with file cache
- Composer

## Installation & Setup

### 1. Install Dependencies

```bash
composer install
```

### 2. Install API Documentation Package (Swagger)

```bash
composer require "darkaonline/l5-swagger"
php artisan vendor:publish --provider "L5Swagger\L5SwaggerServiceProvider"
```

### 3. Install Log Viewer Package

```bash
composer require rap2hpoutre/laravel-log-viewer
```

### 4. Environment Setup

Copy `.env.example` to `.env`:

```bash
cp .env.example .env
php artisan key:generate
```

### 5. Database Configuration

Update `.env` with database settings:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=flash_sale
DB_USERNAME=root
DB_PASSWORD=

# Swagger Configuration
L5_SWAGGER_CONST_HOST="http://localhost:8000"
L5_SWAGGER_GENERATE_ALWAYS=true
```

### 6. Run Migrations

```bash
php artisan migrate
```

### 7. Seed Data

```bash
php artisan db:seed
```

This will create 3 sample products:
- Flash Sale Product (100 units - 99.99)
- Limited Edition Item (50 units - 199.99)
- Premium Product (25 units - 299.99)

### 8. Generate API Documentation

```bash
php artisan l5-swagger:generate
```

### 9. Start Queue Worker

You must run the queue worker to process jobs (especially ExpireHoldsJob):

```bash
php artisan queue:work
```

### 10. Start Scheduler

You must run Laravel Scheduler to execute the hold expiry task every 30 seconds:

**On Windows (Laragon/XAMPP):**
```bash
php artisan schedule:work
```

**On Linux/Mac:**
Add the following line to crontab:
```bash
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

### 11. Start Server

```bash
php artisan serve
```

The server will run on `http://localhost:8000`

---

## 📚 API Documentation (Swagger)

### Access Interactive Documentation

Once the server is running, visit:

**Swagger UI**: `http://localhost:8000/api/documentation`

### Features

- 🔍 **Interactive API Testing**: Test all endpoints directly from the browser
- 📖 **Complete Documentation**: All request/response schemas documented
- 🎯 **Try It Out**: Execute API calls with sample data
- 📥 **Export**: Download OpenAPI JSON/YAML files

### API Documentation Files

- **JSON**: `http://localhost:8000/docs/api-docs.json`
- **YAML**: `http://localhost:8000/docs/api-docs.yaml`

### Regenerate Documentation

Whenever you update API annotations:

```bash
php artisan l5-swagger:generate
```

### Import to Postman

1. Open Postman
2. Click Import
3. Enter URL: `http://localhost:8000/docs/api-docs.json`
4. Click Continue

---

## 📊 Log Viewer

### Access Log Viewer

Visit: `http://localhost:8000/logs`

### Features

- 📝 **View All Logs**: Browse all application logs in a web interface
- 🔍 **Search & Filter**: Filter by log level (emergency, alert, critical, error, warning, notice, info, debug)
- 📅 **Date Navigation**: Browse logs by date
- 🎨 **Syntax Highlighting**: Color-coded log levels for easy identification
- 📥 **Download Logs**: Download log files directly from the interface

### Log Levels

| Level | Description | Color |
|-------|-------------|-------|
| EMERGENCY | System is unusable | Red |
| ALERT | Action must be taken immediately | Red |
| CRITICAL | Critical conditions | Red |
| ERROR | Runtime errors | Orange |
| WARNING | Warning messages | Yellow |
| NOTICE | Normal but significant events | Blue |
| INFO | Informational messages | Green |
| DEBUG | Debug-level messages | Gray |

### Monitoring Important Events

The application logs the following events:

#### Hold Operations
- Hold creation with product ID and quantity
- Hold expiry and stock release
- Hold consumption when order is paid

#### Order Operations
- Order creation from hold
- Order status changes (pending → paid/cancelled)
- Order cancellation and stock restoration

#### Webhook Processing
- Webhook reception with idempotency key
- Duplicate webhook detection
- Out-of-order webhook handling
- Webhook processing success/failure

#### Concurrency & Errors
- Deadlock detection and retry attempts
- Database transaction errors
- Queue job failures
- Cache operations

### Real-time Log Monitoring

**Via Command Line:**
```bash
tail -f storage/logs/laravel.log
```

**Via Log Viewer:**
Visit `http://localhost:8000/logs` and refresh the page

---

## API Endpoints

### 1. GET /api/products/{id}

Get product information with available stock.

**Response:**
```json
{
  "id": 1,
  "name": "Flash Sale Product",
  "price": "99.99",
  "available_stock": 100
}
```

**Notes:**
- `available_stock` = `stock` - `active_holds` - `consumed_orders`
- Results are cached for 5 seconds to improve performance

---

### 2. POST /api/holds

Create a temporary hold for a product.

**Request:**
```json
{
  "product_id": 1,
  "qty": 2
}
```

**Response:**
```json
{
  "hold_id": 1,
  "expires_at": "2024-01-01T12:02:00.000000Z"
}
```

**Notes:**
- Hold is valid for 2 minutes (configurable in `HoldService::HOLD_DURATION_MINUTES`)
- Uses DB transaction with `SELECT ... FOR UPDATE` to prevent overselling
- In case of deadlock, retries up to 3 times with exponential backoff

---

### 3. POST /api/orders

Create an order from an existing hold.

**Request:**
```json
{
  "hold_id": 1
}
```

**Response:**
```json
{
  "order_id": 1,
  "total_amount": "199.98",
  "status": "pending"
}
```

**Notes:**
- Verifies that the hold exists, is not expired, and hasn't been used before
- Each hold can only be used once
- When creating an order, automatically checks for pending webhooks for this order

---

### 4. POST /api/payments/webhook

Receive webhook from payment gateway.

**Request:**
```json
{
  "idempotency_key": "unique-key-123",
  "order_id": 1,
  "status": "paid"
}
```

**Response:**
```json
{
  "message": "Webhook processed successfully",
  "status": "success"
}
```

**Notes:**
- **Idempotent**: Same `idempotency_key` will not be processed twice
- **Out-of-order safe**: If webhook arrives before order creation, it will be saved and processed later
- When `status=paid`: Marks order as paid and hold as consumed (does not release stock)
- When `status=failed`: Cancels order and releases stock

---

## Database Structure

### products
- `id` (PK)
- `name`
- `price` (decimal 10,2)
- `stock` (integer)
- `created_at`, `updated_at`

### holds
- `id` (PK)
- `product_id` (FK)
- `qty` (integer)
- `expires_at` (datetime)
- `used_for_order_id` (FK nullable)
- `status` (enum: reserved, expired, consumed)
- `created_at`, `updated_at`

### orders
- `id` (PK)
- `hold_id` (FK)
- `total_amount` (decimal 10,2)
- `status` (enum: pending, paid, cancelled)
- `created_at`, `updated_at`

### payments_webhooks
- `id` (PK)
- `idempotency_key` (unique)
- `payload` (json)
- `processed_at` (datetime nullable)
- `status` (enum: success, failed, pending)
- `order_id` (FK nullable)
- `created_at`, `updated_at`

---

## Architecture & Design

### Services

- **ProductService**: Manages products and calculates available stock with caching
- **HoldService**: Creates and manages holds with concurrency control
- **OrderService**: Creates orders and links them to holds
- **PaymentWebhookService**: Processes webhooks with idempotency and out-of-order handling

### Jobs

- **ExpireHoldsJob**: Background job to release inventory from expired holds
  - Runs every 30 seconds via Laravel Scheduler
  - Uses distributed lock (Cache::lock) to prevent double-run

### Concurrency Control

The project uses several mechanisms to ensure safety in a high-concurrency environment:

1. **DB Transactions**: All sensitive operations are within transactions
2. **Row Locking**: `SELECT ... FOR UPDATE` on product row when creating hold
3. **Retry Mechanism**: Automatic retry (3 times) with exponential backoff in case of deadlocks
4. **Cache Locks**: Uses `Cache::lock` in jobs to prevent concurrent execution

### Caching Strategy

- **Product Cache**: Results of `GET /api/products/{id}` are cached for 5 seconds
- **Cache Invalidation**: Cache is automatically invalidated when:
  - New hold is created
  - Hold expires
  - Order is paid
  - Order is cancelled

---

## Monitoring & Debugging

### Using Log Viewer

1. **Access**: `http://localhost:8000/log-viewer`
2. **Filter by Level**: Click on log level badges (ERROR, WARNING, INFO, etc.)
3. **Search**: Use the search box to find specific messages
4. **Download**: Click download button to save log files

### Important Log Messages to Monitor

#### Success Operations
```
[INFO] Hold created: Product #1, Qty: 2
[INFO] Order created: Order #1, Total: 199.98
[INFO] Webhook processed: idempotency_key=test-123
```

#### Warnings
```
[WARNING] Hold expired: Hold #1, Product #1, Qty: 2
[WARNING] Duplicate webhook detected: idempotency_key=test-123
[WARNING] Webhook received before order creation
```

#### Errors
```
[ERROR] Insufficient stock: Product #1, Available: 5, Requested: 10
[ERROR] Hold not found or already used: Hold #1
[ERROR] Deadlock detected, attempt 1/3
```

### Performance Monitoring

Check the following metrics in logs:

- **Hold Creation Time**: Monitor for slow DB operations
- **Cache Hit Rate**: Check product cache effectiveness
- **Deadlock Frequency**: Monitor concurrent access issues
- **Queue Processing**: Monitor job execution times

---

## Local Development

### Running All Services Together

```bash
# Terminal 1: Server
php artisan serve

# Terminal 2: Queue Worker
php artisan queue:work

# Terminal 3: Scheduler
php artisan schedule:work

# Terminal 4: Log Monitoring (Optional)
tail -f storage/logs/laravel.log
```

### Manual API Testing via Swagger

1. Visit `http://localhost:8000/api/documentation`
2. Click on any endpoint
3. Click "Try it out"
4. Fill in the parameters
5. Click "Execute"
6. View the response

### Manual API Testing via cURL

```bash
# Get product
curl http://localhost:8000/api/products/1

# Create hold
curl -X POST http://localhost:8000/api/holds \
  -H "Content-Type: application/json" \
  -d '{"product_id": 1, "qty": 2}'

# Create order
curl -X POST http://localhost:8000/api/orders \
  -H "Content-Type: application/json" \
  -d '{"hold_id": 1}'

# Send webhook
curl -X POST http://localhost:8000/api/payments/webhook \
  -H "Content-Type: application/json" \
  -d '{"idempotency_key": "test-123", "order_id": 1, "status": "paid"}'
```

---

## Production Deployment

### Recommendations

1. **Use Redis** for cache and queues to improve performance
2. **Supervisor** to manage queue workers
3. **Cron** to run Laravel Scheduler
4. **Monitoring Tools** to monitor logs and queue jobs
5. **Database Indexing**: All required indexes are in migrations
6. **Disable Swagger in Production**: Set `L5_SWAGGER_GENERATE_ALWAYS=false`

### Environment Variables

```env
# Cache & Queue
CACHE_STORE=redis
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Swagger (Production)
L5_SWAGGER_GENERATE_ALWAYS=false

# Logging
LOG_CHANNEL=daily
LOG_LEVEL=warning
```

### Security Considerations

#### Protect Log Viewer in Production

Add middleware to `routes/web.php`:

```php
Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('logs', '\Rap2hpoutre\LaravelLogViewer\LogViewerController@index');
});
```

#### Disable Swagger in Production

Update `config/l5-swagger.php`:

```php
'generate_always' => env('L5_SWAGGER_GENERATE_ALWAYS', false),
```

---

## Common Issues

### Queue Jobs Not Working

**Solution**: Make sure to run `php artisan queue:work` or `php artisan queue:listen`

**Check**: Visit Log Viewer at `http://localhost:8000/logs` and filter by ERROR

---

### Holds Not Expiring Automatically

**Solution**: Make sure to run Laravel Scheduler: `php artisan schedule:work`

**Check**: Look for scheduled job logs in Log Viewer

---

### Swagger Not Showing Endpoints

**Solution**:
```bash
php artisan config:clear
php artisan l5-swagger:generate
```

**Check**: Visit `http://localhost:8000/docs/api-docs.json` to verify JSON is generated

---

### Log Viewer Showing 404

**Solution**: Clear route cache:
```bash
php artisan route:clear
php artisan cache:clear
```

---

### Deadlocks in High Concurrency

**Note**: This is normal in a high-concurrency environment. The system automatically retries.

**Monitor**: Check ERROR level logs in Log Viewer for frequency

---

## Useful Links

- **API Documentation**: `http://localhost:8000/api/documentation`
- **Log Viewer**: `http://localhost:8000/logs`
- **OpenAPI JSON**: `http://localhost:8000/docs/api-docs.json`
- **OpenAPI YAML**: `http://localhost:8000/docs/api-docs.yaml`

---

## Package Documentation

- **Swagger (L5-Swagger)**: [GitHub](https://github.com/DarkaOnLine/L5-Swagger)
- **Log Viewer**: [GitHub](https://github.com/rap2hpoutre/laravel-log-viewer)
- **OpenAPI Specification**: [Swagger.io](https://swagger.io/specification/)

---

## License

MIT License

---

## Support

For issues or questions:
- Check API Documentation: `http://localhost:8000/api/documentation`
- Check Application Logs: `http://localhost:8000/logs`
- Review this README file

---

**Made with ❤️ using Laravel 12**
