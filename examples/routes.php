<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PaymentController;

/*
|--------------------------------------------------------------------------
| Cashfree Payment Routes Example
|--------------------------------------------------------------------------
|
| These route definitions illustrate how to map the endpoints to your
| PaymentController methods. Note that the webhook route should bypass
| CSRF middleware.
|
*/

// Web routes or API routes
Route::group(['prefix' => 'payment'], function () {
    // 1. Create a payment order session
    Route::post('/create', [PaymentController::class, 'createOrder'])->name('payment.create');

    // 2. Client redirect return landing page callback
    Route::get('/callback', [PaymentController::class, 'callback'])->name('payment.callback');

    // 3. Cashfree backend notifications webhook (make sure to exclude this from CSRF in bootstrap/app.php or VerifyCsrfToken middleware)
    Route::post('/webhook', [PaymentController::class, 'webhook'])->name('payment.webhook');

    // 4. Order Refund initiation
    Route::post('/refund/{orderId}', [PaymentController::class, 'refund'])->name('payment.refund');
});

/*
|--------------------------------------------------------------------------
| CSRF Verification Exclusion
|--------------------------------------------------------------------------
|
| Cashfree webhooks are POST requests sent directly from Cashfree's servers.
| To prevent CSRF verification failures:
|
| For Laravel 11+:
| In `bootstrap/app.php` add the webhook URL to the excepted list:
|
| ->withMiddleware(function (Middleware $middleware) {
|     $middleware->validateCsrfTokens(except: [
|         'payment/webhook',
|     ]);
| })
|
| For Laravel 10 and below:
| In `app/Http/Middleware/VerifyCsrfToken.php` add the route:
|
| protected $except = [
|     'payment/webhook',
| ];
|
*/
