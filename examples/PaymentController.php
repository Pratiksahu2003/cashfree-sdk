<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use CashfreePayment\Facades\Cashfree;
use CashfreePayment\Exceptions\CashfreeException;
use CashfreePayment\Models\CashfreePayment;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    /**
     * Create a payment order.
     *
     * POST /payment/create
     */
    public function createOrder(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'customer_phone' => 'required|string',
            'customer_email' => 'required|email',
        ]);

        // Generate a unique order reference
        $orderId = 'order_' . time() . '_' . rand(1000, 9999);
        $customerId = 'cust_' . uniqid();

        try {
            $response = Cashfree::createOrder([
                'order_id' => $orderId,
                'order_amount' => (float) $request->input('amount'),
                'order_currency' => 'INR',
                'customer_details' => [
                    'customer_id' => $customerId,
                    'customer_phone' => $request->input('customer_phone'),
                    'customer_email' => $request->input('customer_email'),
                ],
                'order_meta' => [
                    'return_url' => route('payment.callback') . '?order_id={order_id}',
                    'notify_url' => route('payment.webhook'),
                ]
            ]);

            // Save transaction record to the database
            CashfreePayment::create([
                'order_id' => $orderId,
                'amount' => (float) $request->input('amount'),
                'currency' => 'INR',
                'customer_id' => $customerId,
                'customer_phone' => $request->input('customer_phone'),
                'customer_email' => $request->input('customer_email'),
                'status' => $response['order_status'] ?? 'ACTIVE',
                'payment_session_id' => $response['payment_session_id'] ?? null,
                'raw_response' => $response,
            ]);

            return response()->json([
                'success' => true,
                'order_id' => $response['order_id'] ?? $orderId,
                'payment_session_id' => $response['payment_session_id'] ?? null,
                'payment_link' => $response['payment_link'] ?? null,
                'raw_response' => $response
            ]);

        } catch (CashfreeException $e) {
            Log::error('Cashfree Order Creation Failed: ' . $e->getMessage(), [
                'error_response' => $e->getErrorResponse()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create payment order: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * Handle user return callback (after checkout completion).
     *
     * GET /payment/callback
     */
    public function callback(Request $request)
    {
        $orderId = $request->query('order_id');
        if (!$orderId) {
            return response()->json(['success' => false, 'message' => 'Order ID missing'], 400);
        }

        try {
            $order = Cashfree::getOrder($orderId);
            $status = $order['order_status'] ?? 'UNKNOWN';

            // Sync with local record
            $paymentRecord = CashfreePayment::where('order_id', $orderId)->first();
            if ($paymentRecord) {
                $paymentRecord->update([
                    'status' => $status,
                    'raw_response' => array_merge((array) $paymentRecord->raw_response, ['callback_sync' => $order]),
                ]);
            }

            return response()->json([
                'success' => true,
                'order_id' => $orderId,
                'status' => $status,
                'details' => $order
            ]);

        } catch (CashfreeException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve order: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * Handle Cashfree Webhook callback.
     *
     * POST /payment/webhook
     */
    public function webhook(Request $request)
    {
        $signature = $request->header('x-webhook-signature');
        $timestamp = $request->header('x-webhook-timestamp');
        $rawPayload = $request->getContent();

        // Verify the authenticity of the webhook
        $isValid = Cashfree::verifyWebhook($timestamp, $rawPayload, $signature);

        if (!$isValid) {
            return response()->json(['message' => 'Signature verification failed'], 400);
        }

        // Process webhook payload safely
        $payload = json_decode($rawPayload, true);
        
        $orderId = $payload['data']['order']['order_id'] ?? null;
        $orderStatus = $payload['data']['order']['order_status'] ?? null;
        $transactionId = $payload['data']['payment']['cf_payment_id'] ?? null;
        $paymentMethod = $payload['data']['payment']['payment_group'] ?? null;

        Log::info('Processing Cashfree Webhook Event', [
            'event' => $payload['event'] ?? 'UNKNOWN',
            'order_id' => $orderId ?? 'UNKNOWN'
        ]);

        if ($orderId) {
            $paymentRecord = CashfreePayment::where('order_id', $orderId)->first();
            if ($paymentRecord) {
                $paymentRecord->update([
                    'status' => $orderStatus ?? $paymentRecord->status,
                    'transaction_id' => $transactionId ?? $paymentRecord->transaction_id,
                    'payment_method' => $paymentMethod ?? $paymentRecord->payment_method,
                    'raw_response' => array_merge((array) $paymentRecord->raw_response, ['webhook' => $payload]),
                ]);
            }
        }

        return response()->json(['status' => 'OK']);
    }

    /**
     * Initiate a refund.
     *
     * POST /payment/refund/{orderId}
     */
    public function refund(Request $request, string $orderId)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'refund_id' => 'required|string',
        ]);

        try {
            $response = Cashfree::createRefund($orderId, [
                'refund_amount' => (float) $request->input('amount'),
                'refund_id' => $request->input('refund_id'),
                'refund_note' => $request->input('note', 'Customer refund request'),
            ]);

            // Update database record status
            $paymentRecord = CashfreePayment::where('order_id', $orderId)->first();
            if ($paymentRecord) {
                $paymentRecord->update([
                    'refund_status' => $response['refund_status'] ?? 'SUCCESS',
                    'status' => 'REFUNDED',
                    'raw_response' => array_merge((array) $paymentRecord->raw_response, ['refund' => $response]),
                ]);
            }

            return response()->json([
                'success' => true,
                'refund_status' => $response['refund_status'] ?? 'PENDING',
                'details' => $response
            ]);

        } catch (CashfreeException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Refund initiation failed: ' . $e->getMessage()
            ], 400);
        }
    }
}
