<?php

namespace CashfreePayment;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;
use CashfreePayment\Exceptions\CashfreeException;

class CashfreeClient
{
    /**
     * HTTP status codes that are safe to retry.
     */
    protected const RETRYABLE_STATUS_CODES = [408, 429, 500, 502, 503, 504];

    /**
     * Guzzle HTTP Client.
     */
    protected Client $httpClient;

    /**
     * Cashfree API Base URL.
     */
    protected string $baseUri;

    /**
     * CashfreeClient constructor.
     */
    public function __construct(
        protected string $appId,
        protected string $secretKey,
        protected string $environment = 'sandbox',
        protected string $apiVersion = '2023-08-01',
        protected ?LoggerInterface $logger = null,
        protected bool $loggingEnabled = true,
        protected int $retryAttempts = 3,
        protected int $retryBackoffMs = 500
    ) {
        $this->baseUri = $this->resolveBaseUri($this->environment);

        $this->httpClient = new Client([
            'base_uri' => $this->baseUri . '/',
            'headers' => [
                'x-client-id' => $this->appId,
                'x-client-secret' => $this->secretKey,
                'x-api-version' => $this->apiVersion,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ]);
    }

    /**
     * Create a new payment order.
     *
     * @throws CashfreeException
     */
    public function createOrder(array $params): array
    {
        return $this->request('POST', 'orders', $params);
    }

    /**
     * Get order details by Order ID.
     *
     * @throws CashfreeException
     */
    public function getOrder(string $orderId): array
    {
        return $this->request('GET', "orders/{$orderId}");
    }

    /**
     * Create a refund for an order.
     *
     * @throws CashfreeException
     */
    public function createRefund(string $orderId, array $params): array
    {
        return $this->request('POST', "orders/{$orderId}/refunds", $params);
    }

    /**
     * Retrieve all refunds for an order.
     *
     * @throws CashfreeException
     */
    public function getRefunds(string $orderId): array
    {
        return $this->request('GET', "orders/{$orderId}/refunds");
    }

    /**
     * Verify the authenticity of a Cashfree webhook signature.
     */
    public function verifyWebhook(string $timestamp, string $rawBody, string $signature): bool
    {
        if (empty($this->secretKey)) {
            $this->log('error', 'Cashfree Webhook verification failed: Secret key is not configured.', []);
            return false;
        }

        if (empty($timestamp) || empty($signature)) {
            $this->log('warning', 'Cashfree Webhook verification failed: Missing timestamp or signature headers.', [
                'timestamp' => $timestamp,
                'signature' => $signature,
            ]);
            return false;
        }

        $data = $timestamp . $rawBody;
        $computedSignature = base64_encode(hash_hmac('sha256', $data, $this->secretKey, true));

        $isValid = hash_equals($signature, $computedSignature);

        if ($isValid) {
            $this->log('info', 'Cashfree Webhook verified successfully.', [
                'timestamp' => $timestamp,
                'payload' => json_decode($rawBody, true) ?: $rawBody,
            ]);
        } else {
            $this->log('error', 'Cashfree Webhook signature verification failed.', [
                'timestamp' => $timestamp,
                'received_signature' => $signature,
                'computed_signature' => $computedSignature,
                'payload' => json_decode($rawBody, true) ?: $rawBody,
            ]);
        }

        return $isValid;
    }

    /**
     * Perform HTTP request via Guzzle with optional retries.
     *
     * @throws CashfreeException
     */
    protected function request(string $method, string $uri, array $body = []): array
    {
        $this->ensureCredentials();

        $options = [];
        if (!empty($body)) {
            $options['json'] = $body;
        }

        $this->logRequest($method, $uri, $body);

        $attempts = max(1, $this->retryAttempts);
        $lastException = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $response = $this->httpClient->request($method, $uri, $options);
                $responseBody = (string) $response->getBody();
                $statusCode = $response->getStatusCode();

                $this->logResponse($method, $uri, $statusCode, $responseBody);

                return json_decode($responseBody, true) ?? [];
            } catch (ConnectException $e) {
                $lastException = $e;
                $this->logError($method, $uri, 0, '', $e->getMessage());

                if (!$this->shouldRetry($attempt, $attempts, null, true)) {
                    throw new CashfreeException(
                        'Unable to connect to Cashfree API: ' . $e->getMessage(),
                        0,
                        null,
                        $e
                    );
                }

                $this->sleepBeforeRetry($attempt);
            } catch (RequestException $e) {
                $response = $e->getResponse();
                $statusCode = $response ? $response->getStatusCode() : 500;
                $responseBody = $response ? (string) $response->getBody() : '';

                $this->logError($method, $uri, $statusCode, $responseBody, $e->getMessage());

                if ($this->shouldRetry($attempt, $attempts, $statusCode, false)) {
                    $lastException = $e;
                    $this->sleepBeforeRetry($attempt);
                    continue;
                }

                $errorData = json_decode($responseBody, true) ?? [];
                $errorMessage = $errorData['message'] ?? $e->getMessage();

                throw new CashfreeException($errorMessage, $statusCode, $errorData, $e);
            } catch (\Throwable $e) {
                $this->logError($method, $uri, 500, '', $e->getMessage());
                throw new CashfreeException($e->getMessage(), 500, null, $e);
            }
        }

        throw new CashfreeException(
            'Cashfree API request failed after ' . $attempts . ' attempts.',
            0,
            null,
            $lastException instanceof \Exception ? $lastException : null
        );
    }

    /**
     * Resolve the API base URL for the configured environment.
     */
    protected function resolveBaseUri(string $environment): string
    {
        return strtolower($environment) === 'production'
            ? 'https://api.cashfree.com/pg'
            : 'https://sandbox.cashfree.com/pg';
    }

    /**
     * Ensure API credentials are configured before making requests.
     *
     * @throws CashfreeException
     */
    protected function ensureCredentials(): void
    {
        if (empty($this->appId) || empty($this->secretKey)) {
            throw new CashfreeException(
                'Cashfree credentials are not configured. Set CASHFREE_APP_ID and CASHFREE_SECRET_KEY in your environment or pass them to CashfreeClient.'
            );
        }
    }

    /**
     * Determine whether another request attempt should be made.
     */
    protected function shouldRetry(int $attempt, int $maxAttempts, ?int $statusCode, bool $isConnectionError): bool
    {
        if ($attempt >= $maxAttempts) {
            return false;
        }

        if ($isConnectionError) {
            return true;
        }

        return $statusCode !== null && in_array($statusCode, self::RETRYABLE_STATUS_CODES, true);
    }

    /**
     * Pause execution before the next retry attempt.
     */
    protected function sleepBeforeRetry(int $attempt): void
    {
        if ($this->retryBackoffMs <= 0) {
            return;
        }

        usleep($this->retryBackoffMs * 1000 * $attempt);
    }

    /**
     * Log an API request.
     */
    protected function logRequest(string $method, string $uri, array $body): void
    {
        if (!$this->loggingEnabled || !$this->logger) {
            return;
        }

        $this->logger->info("Cashfree API Request: {$method} {$this->baseUri}/{$uri}", [
            'payload' => $body,
            'headers' => [
                'x-client-id' => $this->appId,
                'x-client-secret' => 'REDACTED',
                'x-api-version' => $this->apiVersion,
            ],
        ]);
    }

    /**
     * Log an API response.
     */
    protected function logResponse(string $method, string $uri, int $statusCode, string $body): void
    {
        if (!$this->loggingEnabled || !$this->logger) {
            return;
        }

        $this->logger->info("Cashfree API Response: {$method} {$this->baseUri}/{$uri} [{$statusCode}]", [
            'response' => json_decode($body, true) ?: $body,
        ]);
    }

    /**
     * Log API error responses.
     */
    protected function logError(string $method, string $uri, int $statusCode, string $body, string $systemError): void
    {
        if (!$this->loggingEnabled || !$this->logger) {
            return;
        }

        $this->logger->error("Cashfree API Error: {$method} {$this->baseUri}/{$uri} [{$statusCode}]", [
            'response' => json_decode($body, true) ?: $body,
            'system_error' => $systemError,
        ]);
    }

    /**
     * Log a general message.
     */
    protected function log(string $level, string $message, array $context = []): void
    {
        if (!$this->loggingEnabled || !$this->logger) {
            return;
        }

        $this->logger->log($level, $message, $context);
    }
}
