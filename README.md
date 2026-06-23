# Cashfree Payment Gateway Laravel SDK

A lightweight PHP SDK and Laravel integration wrapper for the **Cashfree Payment Gateway (PG v3 API)**. It supports creating orders, checking order statuses, processing refunds, securely verifying webhooks using SHA256 signatures, and writing detailed, redacted transaction logs.

---

## Features

- **API v3 PG Engine**: Ready for `/orders` and `/refunds` endpoints.
- **Webhook signature validation**: Simple validation against signature spoofing.
- **Environment config**: Fully configurable using your Laravel `.env` file.
- **Log management**: Formatted logs that automatically redact your `x-client-secret` key for data safety.
- **Service Provider & Facade**: Native Laravel integration.

---

## Installation

### 1. Locally (Path Repository)
Since the package is local, register it in your Laravel project's `composer.json` using a path repository:

```json
"repositories": [
    {
        "type": "path",
        "url": "../path-to-this-package-folder"
    }
],
"require": {
    "pratiksahu1535/cashfree-payment": "@dev"
}
```

Then run:
```bash
composer update pratiksahu1535/cashfree-payment
```

### 2. Auto-Discovery
The package is designed with auto-discovery enabled. Once installed, Laravel will register `CashfreePaymentServiceProvider` and the `Cashfree` facade automatically.

---

## Configuration

### 1. Publish Configuration File
Publish the configuration to your Laravel project config folder:

```bash
php artisan vendor:publish --tag=cashfree-config
```

This creates `config/cashfree.php`.

### 2. Configure Environment Variables (`.env`)
Add the following keys to your project's `.env` file:

```env
# Cashfree Credentials
CASHFREE_APP_ID="your_cashfree_app_id"
CASHFREE_SECRET_KEY="your_cashfree_secret_key"
CASHFREE_ENV="sandbox" # Use "production" for live payments

# Optional Configuration
CASHFREE_API_VERSION="2023-08-01"
CASHFREE_LOGGING_ENABLED=true
CASHFREE_LOG_CHANNEL="cashfree"
```

### 3. Setup Custom Logging Channel (Optional)
To send all Cashfree operations and webhook events to a dedicated log file (`storage/logs/cashfree.log`) instead of `laravel.log`, add the channel inside `config/logging.php`:

```php
'channels' => [
    // ... other channels ...

    'cashfree' => [
        'driver' => 'single',
        'path' => storage_path('logs/cashfree.log'),
        'level' => 'debug',
    ],
],
```

### 4. Database Migrations & Recording
The package includes a database migration to create the `cashfree_payments` table for recording and logging transaction statuses.

#### Running Migrations
Migrations are automatically loaded by the service provider. Run them inside your Laravel project using:
```bash
php artisan migrate
```

#### Publishing Migrations (Optional)
If you wish to modify or customize the migration schema, you can publish it:
```bash
php artisan vendor:publish --tag=cashfree-migrations
```

---

## Usage

Check the `examples/` directory in the package for complete code implementations of:
- **`PaymentController.php`**: Full controller handling payment creation, webhook validation, and refunds.
- **`routes.php`**: Route definitions and instructions to exclude the webhook route from CSRF protection.

### Quick Syntax Examples

#### Create an Order Session
```php
use CashfreePayment\Facades\Cashfree;

$response = Cashfree::createOrder([
    'order_id' => 'my_order_id_101',
    'order_amount' => 150.00,
    'order_currency' => 'INR',
    'customer_details' => [
        'customer_id' => 'user_992',
        'customer_phone' => '9999999999',
        'customer_email' => 'customer@example.com',
    ],
    'order_meta' => [
        'return_url' => 'https://example.com/payment/callback?order_id={order_id}',
    ]
]);

$paymentSessionId = $response['payment_session_id'];
$paymentLink = $response['payment_link'];
```

#### Fetch Order Details
```php
$order = Cashfree::getOrder('my_order_id_101');
$status = $order['order_status']; // PAID, ACTIVE, EXPIRED, etc.
```

#### Refund an Order
```php
$refund = Cashfree::createRefund('my_order_id_101', [
    'refund_amount' => 150.00,
    'refund_id' => 'ref_101_unique',
    'refund_note' => 'Item out of stock',
]);
```

#### Webhook Validation
Validate that incoming webhook notifications are authentic:

```php
use CashfreePayment\Facades\Cashfree;

$signature = $request->header('x-webhook-signature');
$timestamp = $request->header('x-webhook-timestamp');
$rawPayload = $request->getContent(); // Raw request body

$isValid = Cashfree::verifyWebhook($timestamp, $rawPayload, $signature);

if ($isValid) {
    // Process payment event safely...
}
```

#### Database Model
You can import the `CashfreePayment` Eloquent model to query or update records in the `cashfree_payments` table:

```php
use CashfreePayment\Models\CashfreePayment;

// Fetch payment by order ID
$payment = CashfreePayment::where('order_id', 'order_123456')->first();

if ($payment) {
    $status = $payment->status; // PAID, ACTIVE, FAILED, etc.
    $transactionId = $payment->transaction_id;
    $amount = $payment->amount;
    
    // The raw API response arrays are cast automatically:
    $rawResponseData = $payment->raw_response; 
}
```

---

## Logging Behavior

When `logging_enabled` is set to `true`, the SDK writes log entries for tracking:
- **API Request**: Records the HTTP method, endpoint URL, JSON payload, and redacted request headers.
- **API Response**: Records status codes and parsed JSON payloads.
- **API Errors**: Logs Guzzle client errors, error response structures, and low-level PHP exceptions.
- **Webhook Verifications**: Logs confirmation of signature verification success, or alerts on mismatches (signature spoofing attempts) containing timestamps and payloads.

All log entries are fully compatible with any custom logger matching `Psr\Log\LoggerInterface` (defaulting to Laravel's log manager).

---

## Running Package Tests

If you wish to run unit tests locally inside the package:

```bash
composer install
./vendor/bin/phpunit
```
