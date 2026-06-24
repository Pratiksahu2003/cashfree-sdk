# Cashfree Payment Gateway — PHP & Laravel SDK

[![Tests](https://github.com/Pratiksahu2003/cashfree-sdk/actions/workflows/tests.yml/badge.svg)](https://github.com/Pratiksahu2003/cashfree-sdk/actions/workflows/tests.yml)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/php-%5E8.0-777BB4.svg)](https://php.net)

> **Repository:** [github.com/Pratiksahu2003/cashfree-sdk](https://github.com/Pratiksahu2003/cashfree-sdk)

A production-ready PHP SDK and Laravel package for the **Cashfree Payment Gateway (PG v3 API)**.

Create orders, fetch payment status, process refunds, verify webhooks with HMAC-SHA256 signatures, and optionally persist transactions in Laravel using the included Eloquent model and migration.

---

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
  - [Laravel (Facade)](#laravel-facade)
  - [Plain PHP (No Framework)](#plain-php-no-framework)
  - [Webhook Verification](#webhook-verification)
  - [Database Model (Laravel)](#database-model-laravel)
- [Complete Laravel Example](#complete-laravel-example)
- [Error Handling](#error-handling)
- [Logging](#logging)
- [API Reference](#api-reference)
- [Testing](#testing)
- [Troubleshooting](#troubleshooting)
- [License](#license)

---

## Features

| Feature | Description |
|---------|-------------|
| **PG v3 API** | Orders, order status, refunds |
| **Webhook security** | HMAC-SHA256 signature verification |
| **Laravel integration** | Auto-discovered service provider, facade, config, migrations |
| **Plain PHP support** | Use `CashfreeClient` directly without Laravel |
| **Safe logging** | Request/response logging with secret key redaction |
| **Automatic retries** | Configurable retries for network and transient API errors |
| **Eloquent model** | Optional `cashfree_payments` table for transaction records |

---

## Requirements

- PHP **8.0+**
- [Composer](https://getcomposer.org/)
- Cashfree merchant account ([Sign up](https://www.cashfree.com/))
- **Laravel 10, 11, or 12** (only if using the Laravel integration)

---

## Installation

### Option 1: Install via Composer (Recommended)

Install from Packagist:

```bash
composer require pratiksahu2003/cashfree-sdk
```

Or install directly from GitHub before Packagist indexing:

```bash
composer require pratiksahu2003/cashfree-sdk:dev-main
```

### Option 2: Local Path Repository

For local development, add a path repository to your Laravel project's `composer.json`:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../path-to-this-package-folder"
        }
    ],
    "require": {
        "pratiksahu2003/cashfree-sdk": "@dev"
    }
}
```

Then run:

```bash
composer update pratiksahu2003/cashfree-sdk
```

### Laravel Auto-Discovery

No manual registration is required. Laravel automatically loads:

- `CashfreePayment\CashfreePaymentServiceProvider`
- `Cashfree` facade alias

After installation, clear config cache if you use it:

```bash
php artisan config:clear
```

---

## Configuration

### 1. Publish Config (Laravel)

```bash
php artisan vendor:publish --tag=cashfree-config
```

This creates `config/cashfree.php` in your Laravel project.

### 2. Environment Variables

Add these to your `.env` file:

```env
# Required — get from Cashfree Merchant Dashboard
CASHFREE_APP_ID=your_app_id
CASHFREE_SECRET_KEY=your_secret_key

# sandbox (testing) or production (live payments)
CASHFREE_ENV=sandbox

# Optional
CASHFREE_API_VERSION=2023-08-01
CASHFREE_LOGGING_ENABLED=true
CASHFREE_LOG_CHANNEL=cashfree
CASHFREE_RETRY_ATTEMPTS=3
CASHFREE_RETRY_BACKOFF_MS=500
```

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `CASHFREE_APP_ID` | Yes | — | Cashfree Client ID |
| `CASHFREE_SECRET_KEY` | Yes | — | Cashfree Client Secret |
| `CASHFREE_ENV` | No | `sandbox` | `sandbox` or `production` |
| `CASHFREE_API_VERSION` | No | `2023-08-01` | Cashfree API version header |
| `CASHFREE_LOGGING_ENABLED` | No | `true` | Enable API and webhook logs |
| `CASHFREE_LOG_CHANNEL` | No | Laravel default | Dedicated log channel name |
| `CASHFREE_RETRY_ATTEMPTS` | No | `3` | Retry count for transient failures |
| `CASHFREE_RETRY_BACKOFF_MS` | No | `500` | Backoff delay in milliseconds |

### 3. Custom Log Channel (Optional)

Add to `config/logging.php`:

```php
'channels' => [
    'cashfree' => [
        'driver' => 'single',
        'path' => storage_path('logs/cashfree.log'),
        'level' => 'debug',
    ],
],
```

### 4. Database Migration (Optional)

Run the included migration to create the `cashfree_payments` table:

```bash
php artisan migrate
```

To customize the schema, publish migrations first:

```bash
php artisan vendor:publish --tag=cashfree-migrations
php artisan migrate
```

---

## Usage

### Laravel (Facade)

#### Create an Order

```php
use CashfreePayment\Facades\Cashfree;

$response = Cashfree::createOrder([
    'order_id' => 'order_' . uniqid(),
    'order_amount' => 150.00,
    'order_currency' => 'INR',
    'customer_details' => [
        'customer_id' => 'user_992',
        'customer_phone' => '9999999999',
        'customer_email' => 'customer@example.com',
    ],
    'order_meta' => [
        'return_url' => 'https://yoursite.com/payment/callback?order_id={order_id}',
        'notify_url' => 'https://yoursite.com/payment/webhook',
    ],
]);

$paymentSessionId = $response['payment_session_id'];
$paymentLink = $response['payment_link'];
```

#### Get Order Status

```php
$order = Cashfree::getOrder('order_abc123');
$status = $order['order_status']; // PAID, ACTIVE, EXPIRED, etc.
```

#### Create a Refund

```php
$refund = Cashfree::createRefund('order_abc123', [
    'refund_amount' => 150.00,
    'refund_id' => 'refund_' . uniqid(),
    'refund_note' => 'Customer requested refund',
]);
```

#### List Refunds for an Order

```php
$refunds = Cashfree::getRefunds('order_abc123');
```

#### Dependency Injection

You can also inject the client directly:

```php
use CashfreePayment\CashfreeClient;

public function __construct(protected CashfreeClient $cashfree) {}

public function pay()
{
    return $this->cashfree->createOrder([/* ... */]);
}
```

Register the binding in a service provider if needed:

```php
$this->app->alias('cashfree', CashfreeClient::class);
```

---

### Plain PHP (No Framework)

Use `CashfreeClient` directly in any PHP project:

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use CashfreePayment\CashfreeClient;
use CashfreePayment\Exceptions\CashfreeException;

$client = new CashfreeClient(
    appId: getenv('CASHFREE_APP_ID'),
    secretKey: getenv('CASHFREE_SECRET_KEY'),
    environment: 'sandbox',
    apiVersion: '2023-08-01',
    logger: null,
    loggingEnabled: false
);

try {
    $order = $client->createOrder([
        'order_id' => 'order_' . uniqid(),
        'order_amount' => 99.00,
        'order_currency' => 'INR',
        'customer_details' => [
            'customer_id' => 'cust_1',
            'customer_phone' => '9999999999',
            'customer_email' => 'user@example.com',
        ],
    ]);

    echo $order['payment_link'];
} catch (CashfreeException $e) {
    echo 'Payment error: ' . $e->getMessage();
}
```

---

### Webhook Verification

Cashfree sends webhooks with `x-webhook-signature` and `x-webhook-timestamp` headers. Always verify before processing.

#### Laravel Controller

```php
use CashfreePayment\Facades\Cashfree;
use Illuminate\Http\Request;

public function webhook(Request $request)
{
    $signature = $request->header('x-webhook-signature');
    $timestamp = $request->header('x-webhook-timestamp');
    $rawPayload = $request->getContent();

    if (!Cashfree::verifyWebhook($timestamp, $rawPayload, $signature)) {
        return response()->json(['message' => 'Invalid signature'], 400);
    }

    $payload = json_decode($rawPayload, true);
    // Process payment event safely...

    return response()->json(['status' => 'OK']);
}
```

#### Exclude Webhook from CSRF (Laravel)

**Laravel 11+** — in `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->validateCsrfTokens(except: [
        'payment/webhook',
    ]);
})
```

**Laravel 10 and below** — in `app/Http/Middleware/VerifyCsrfToken.php`:

```php
protected $except = [
    'payment/webhook',
];
```

Configure the webhook URL in your [Cashfree Dashboard](https://merchant.cashfree.com/) to match your route (e.g. `https://yoursite.com/payment/webhook`).

---

### Database Model (Laravel)

```php
use CashfreePayment\Models\CashfreePayment;

$payment = CashfreePayment::where('order_id', 'order_abc123')->first();

if ($payment) {
    echo $payment->status;           // PAID, ACTIVE, FAILED, etc.
    echo $payment->transaction_id;
    echo $payment->amount;
    $rawData = $payment->raw_response; // auto-cast to array
}
```

Save a record after creating an order:

```php
CashfreePayment::create([
    'order_id' => $orderId,
    'amount' => 150.00,
    'currency' => 'INR',
    'customer_id' => 'user_992',
    'customer_phone' => '9999999999',
    'customer_email' => 'customer@example.com',
    'status' => $response['order_status'] ?? 'ACTIVE',
    'payment_session_id' => $response['payment_session_id'] ?? null,
    'raw_response' => $response,
]);
```

---

## Complete Laravel Example

The `examples/` directory contains ready-to-use reference code:

| File | Description |
|------|-------------|
| `examples/PaymentController.php` | Full controller: create order, callback, webhook, refund |
| `examples/routes.php` | Route definitions and CSRF exclusion notes |

Copy these into your Laravel app and adjust route names/URLs as needed.

---

## Error Handling

All API methods throw `CashfreePayment\Exceptions\CashfreeException` on failure.

```php
use CashfreePayment\Exceptions\CashfreeException;

try {
    $order = Cashfree::createOrder($params);
} catch (CashfreeException $e) {
    $message = $e->getMessage();           // Human-readable error
    $httpCode = $e->getCode();             // HTTP status code
    $apiError = $e->getErrorResponse();    // Raw Cashfree error payload (array|null)
}
```

Common causes:

| Error | Fix |
|-------|-----|
| `Cashfree credentials are not configured` | Set `CASHFREE_APP_ID` and `CASHFREE_SECRET_KEY` in `.env` |
| `401 Unauthorized` | Wrong App ID or Secret Key |
| `Signature verification failed` | Wrong secret key or modified webhook body |
| Connection errors | Check server outbound HTTPS; retries run automatically |

---

## Logging

When `CASHFREE_LOGGING_ENABLED=true`, the SDK logs:

- **API requests** — method, URL, payload, redacted headers
- **API responses** — status code and JSON body
- **API errors** — error payloads and system messages
- **Webhook events** — verification success/failure with payload

The `x-client-secret` header is always logged as `REDACTED`.

---

## API Reference

### `CashfreeClient` / `Cashfree` Facade

| Method | Description |
|--------|-------------|
| `createOrder(array $params): array` | Create a new payment order |
| `getOrder(string $orderId): array` | Fetch order details |
| `createRefund(string $orderId, array $params): array` | Initiate a refund |
| `getRefunds(string $orderId): array` | List refunds for an order |
| `verifyWebhook(string $timestamp, string $rawBody, string $signature): bool` | Validate webhook signature |

### Order Payload (Minimum)

```php
[
    'order_id' => 'unique_order_id',      // Required — your reference
    'order_amount' => 100.00,             // Required
    'order_currency' => 'INR',            // Required
    'customer_details' => [              // Required
        'customer_id' => 'cust_1',
        'customer_phone' => '9999999999',
        'customer_email' => 'user@example.com',
    ],
    'order_meta' => [                    // Recommended
        'return_url' => 'https://yoursite.com/callback?order_id={order_id}',
        'notify_url' => 'https://yoursite.com/webhook',
    ],
]
```

Refer to the [Cashfree PG API documentation](https://www.cashfree.com/docs/api-reference/payments/latest/overview) for full parameter details.

---

## Testing

Run the package test suite:

```bash
composer install
composer test
```

Or directly:

```bash
./vendor/bin/phpunit
```

Tests cover webhook verification, credential validation, and environment configuration.

---

## Troubleshooting

### Package not auto-discovered

```bash
php artisan package:discover
php artisan config:clear
```

### Webhook returns 419 (CSRF token mismatch)

Add your webhook route to CSRF exceptions (see [Webhook Verification](#webhook-verification)).

### Webhook signature always fails

- Use `$request->getContent()` for the **raw** body — do not use `$request->all()` or `$request->json()`
- Ensure `CASHFREE_SECRET_KEY` matches the environment (sandbox vs production)
- Confirm `x-webhook-timestamp` and `x-webhook-signature` headers are present

### Payments work in sandbox but not production

- Set `CASHFREE_ENV=production`
- Use production App ID and Secret Key from the Cashfree dashboard
- Update webhook URL to your live domain

### `Class "CashfreePayment\Facades\Cashfree" not found`

Run `composer dump-autoload` and ensure the package is installed correctly.

---

## License

This project is open-sourced software licensed under the [MIT License](LICENSE).

---

## Publishing & Releases

This package is published as [`pratiksahu2003/cashfree-sdk`](https://packagist.org/packages/pratiksahu2003/cashfree-sdk) on Packagist.

Release checklist:

1. Update `CHANGELOG.md` for the new version
2. Tag the release: `git tag v1.0.0 && git push origin v1.0.0`
3. Packagist auto-updates when connected to this GitHub repository

---

## Support

- **Cashfree Docs:** [https://www.cashfree.com/docs](https://www.cashfree.com/docs)
- **Issues:** [github.com/Pratiksahu2003/cashfree-sdk/issues](https://github.com/Pratiksahu2003/cashfree-sdk/issues)
- **Author:** [Pratik Sahu](https://github.com/Pratiksahu2003) — pratiksahu1535@gmail.com
