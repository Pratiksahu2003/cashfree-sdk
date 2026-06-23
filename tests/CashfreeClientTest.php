<?php

namespace CashfreePayment\Tests;

use PHPUnit\Framework\TestCase;
use CashfreePayment\CashfreeClient;
use CashfreePayment\Exceptions\CashfreeException;
use Psr\Log\LoggerInterface;

class CashfreeClientTest extends TestCase
{
    protected string $appId = 'test_app_id';
    protected string $secretKey = 'test_secret_key';

    public function test_webhook_verification_success()
    {
        $logger = $this->createMock(LoggerInterface::class);
        $client = new CashfreeClient($this->appId, $this->secretKey, 'sandbox', '2023-08-01', $logger);

        $timestamp = '1692789123';
        $rawBody = '{"order_id":"123","order_amount":100}';
        
        // Calculate signature manually
        $data = $timestamp . $rawBody;
        $signature = base64_encode(hash_hmac('sha256', $data, $this->secretKey, true));

        // Expect the logger to log the successful verification
        $logger->expects($this->once())
            ->method('log')
            ->with('info', $this->stringContains('Webhook verified successfully'));

        $result = $client->verifyWebhook($timestamp, $rawBody, $signature);
        $this->assertTrue($result);
    }

    public function test_webhook_verification_failure()
    {
        $logger = $this->createMock(LoggerInterface::class);
        $client = new CashfreeClient($this->appId, $this->secretKey, 'sandbox', '2023-08-01', $logger);

        $timestamp = '1692789123';
        $rawBody = '{"order_id":"123","order_amount":100}';
        $signature = 'invalid_signature';

        // Expect the logger to log signature verification failure
        $logger->expects($this->once())
            ->method('log')
            ->with('error', $this->stringContains('Webhook signature verification failed'));

        $result = $client->verifyWebhook($timestamp, $rawBody, $signature);
        $this->assertFalse($result);
    }

    public function test_webhook_verification_missing_params()
    {
        $logger = $this->createMock(LoggerInterface::class);
        $client = new CashfreeClient($this->appId, $this->secretKey, 'sandbox', '2023-08-01', $logger);

        $logger->expects($this->once())
            ->method('log')
            ->with('warning', $this->stringContains('Missing timestamp or signature'));

        $result = $client->verifyWebhook('', '{}', '');
        $this->assertFalse($result);
    }

    public function test_webhook_verification_fails_when_secret_key_missing()
    {
        $logger = $this->createMock(LoggerInterface::class);
        $client = new CashfreeClient($this->appId, '', 'sandbox', '2023-08-01', $logger);

        $logger->expects($this->once())
            ->method('log')
            ->with('error', $this->stringContains('Secret key is not configured'));

        $result = $client->verifyWebhook('1692789123', '{}', 'signature');
        $this->assertFalse($result);
    }

    public function test_create_order_throws_when_credentials_missing()
    {
        $client = new CashfreeClient('', '', 'sandbox', '2023-08-01', null, false);

        $this->expectException(CashfreeException::class);
        $this->expectExceptionMessage('Cashfree credentials are not configured');

        $client->createOrder([
            'order_id' => 'order_123',
            'order_amount' => 100,
            'order_currency' => 'INR',
        ]);
    }

    public function test_production_environment_uses_live_api_base_uri()
    {
        $client = new CashfreeClient($this->appId, $this->secretKey, 'production', '2023-08-01', null, false);

        $reflection = new \ReflectionClass($client);
        $property = $reflection->getProperty('baseUri');
        $property->setAccessible(true);

        $this->assertSame('https://api.cashfree.com/pg', $property->getValue($client));
    }
}
