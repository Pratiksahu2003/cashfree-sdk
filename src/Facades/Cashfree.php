<?php

namespace CashfreePayment\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array createOrder(array $params)
 * @method static array getOrder(string $orderId)
 * @method static array createRefund(string $orderId, array $params)
 * @method static array getRefunds(string $orderId)
 * @method static bool verifyWebhook(string $timestamp, string $rawBody, string $signature)
 *
 * @see \CashfreePayment\CashfreeClient
 */
class Cashfree extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'cashfree';
    }
}
