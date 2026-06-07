<?php

namespace App\Wa\Services;

use App\Wa\Hub\Models\PointPurchase;
use Illuminate\Support\Facades\Log;
use MyFatoorah\Library\API\Payment\MyFatoorahPayment;

class MyFatoorahPointPurchaseService
{
    public function testInvoiceRequest(
        float $amount = 1.000,
        string $currency = 'KWD',
        string $customerName = 'Payment Config Test',
        string $customerEmail = 'payments-test@example.com'
    ): array {
        $isTest = filter_var(config('myfatoorah.test_mode'), FILTER_VALIDATE_BOOLEAN);

        $mfConfig = [
            'apiKey' => config('myfatoorah.api_key'),
            'isTest' => $isTest,
            'countryCode' => config('myfatoorah.country_iso'),
        ];

        if (empty($mfConfig['apiKey'])) {
            return [
                'ok' => false,
                'message' => 'Missing MyFatoorah API key in config(myfatoorah.api_key).',
                'diagnostics' => [
                    'is_test' => $mfConfig['isTest'],
                    'country_code' => $mfConfig['countryCode'],
                ],
            ];
        }

        $baseUrl = rtrim((string) config('app.url', ''), '/');
        $callbackUrl = $baseUrl.'/points/callback';
        $errorUrl = $baseUrl.'/points/error';

        $payload = [
            'InvoiceValue' => round(max($amount, 0.001), 3),
            'CustomerName' => $customerName,
            'CustomerEmail' => $customerEmail,
            'DisplayCurrency' => strtoupper($currency),
            'CallBackUrl' => $callbackUrl,
            'ErrorUrl' => $errorUrl,
            'Language' => 'en',
            'UserDefinedField' => 'mf-config-test-'.now()->timestamp,
        ];

        try {
            $mfPayment = new MyFatoorahPayment($mfConfig);
            $result = $mfPayment->getInvoiceURL($payload);

            return [
                'ok' => true,
                'message' => 'Invoice URL generated successfully.',
                'invoice_url' => $result['invoiceURL'] ?? null,
                'raw' => $result,
                'diagnostics' => [
                    'is_test' => $mfConfig['isTest'],
                    'country_code' => $mfConfig['countryCode'],
                    'key_prefix' => substr((string) $mfConfig['apiKey'], 0, 12),
                    'key_length' => strlen((string) $mfConfig['apiKey']),
                    'callback_url' => $callbackUrl,
                    'error_url' => $errorUrl,
                ],
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => $e->getMessage(),
                'diagnostics' => [
                    'is_test' => $mfConfig['isTest'],
                    'country_code' => $mfConfig['countryCode'],
                    'key_prefix' => substr((string) $mfConfig['apiKey'], 0, 12),
                    'key_length' => strlen((string) $mfConfig['apiKey']),
                    'callback_url' => $callbackUrl,
                    'error_url' => $errorUrl,
                ],
            ];
        }
    }

    public function enquiry(PointPurchase $purchase): ?array
    {
        // Ensure strictly boolean to guarantee the library sets the correct Base URL
        // If this is not boolean, apiBaseUrl might remain empty, causing "Could not resolve host"
        $isTest = filter_var(config('myfatoorah.test_mode'), FILTER_VALIDATE_BOOLEAN);

        $mfConfig = [
            'apiKey' => config('myfatoorah.api_key'),
            'isTest' => $isTest,
            'countryCode' => config('myfatoorah.country_iso'),
        ];

        Log::info('[MF] Enquiry start', [
            'purchase_id' => $purchase->id,
            'transaction_id' => $purchase->transaction_id,
            'is_test' => $mfConfig['isTest'],
            'country_code' => $mfConfig['countryCode'],
            'has_api_key' => ! empty($mfConfig['apiKey']),
        ]);

        if (empty($mfConfig['apiKey'])) {
            Log::error('[MF] Enquiry blocked: missing api_key', ['purchase_id' => $purchase->id]);

            return null;
        }

        if (! $purchase->transaction_id) {
            Log::warning('[MF] Enquiry skipped: missing transaction_id', ['purchase_id' => $purchase->id]);

            return null;
        }

        try {
            $mf = new MyFatoorahPayment($mfConfig);

            // FIX: Manually construct the full URL.
            // 1. getApiURL() previously returned a truncated URL (root domain only).
            // 2. Passing a relative path ('v2/...') caused "Could not resolve host: v2".
            // We explicitly build the full path here to ensure cURL gets a valid Absolute URL.
            $baseUrl = $mfConfig['isTest'] ? 'https://apitest.myfatoorah.com' : 'https://api.myfatoorah.com';
            $url = $baseUrl.'/v2/GetPaymentStatus';

            $payload = [
                'Key' => (string) $purchase->transaction_id,
                'KeyType' => 'PaymentId', // Default assumption
            ];

            Log::info('[MF] Calling GetPaymentStatus', [
                'purchase_id' => $purchase->id,
                'url' => $url,
                'payload' => $payload,
            ]);

            $res = null;

            // Try PaymentId first, fallback to InvoiceId if not found
            try {
                $res = $mf->callAPI($url, $payload, 'POST');
            } catch (\Exception $e) {
                // If API returns "No data match...", the ID might be an InvoiceId
                if (str_contains($e->getMessage(), 'No data match')) {
                    Log::warning('[MF] PaymentId lookup failed, retrying as InvoiceId', [
                        'purchase_id' => $purchase->id,
                        'original_error' => $e->getMessage(),
                    ]);

                    $payload['KeyType'] = 'InvoiceId';
                    $res = $mf->callAPI($url, $payload, 'POST');
                } else {
                    throw $e; // Re-throw genuine errors (auth, network, etc)
                }
            }

            // Sanity check: If response is a string containing HTML, the endpoint is still wrong
            if (is_string($res) && (str_contains($res, '<html') || str_contains($res, '<!DOCTYPE'))) {
                throw new \Exception('MyFatoorah API returned HTML instead of JSON. Check API Endpoint configuration.');
            }

            if (! $res) {
                Log::warning('[MF] GetPaymentStatus returned empty response', [
                    'purchase_id' => $purchase->id,
                ]);

                return null;
            }

            Log::info('[MF] GetPaymentStatus success', [
                'purchase_id' => $purchase->id,
                'invoice_status' => data_get($res, 'Data.InvoiceStatus'),
                'trx_status' => data_get($res, 'Data.InvoiceTransactions.0.TransactionStatus'),
                'paid_date' => data_get($res, 'Data.InvoiceTransactions.0.TransactionDate'),
            ]);

            Log::debug('[MF] GetPaymentStatus full response', [
                'purchase_id' => $purchase->id,
                'response' => $res,
            ]);

            return is_array($res) ? $res : (array) $res;

        } catch (\Throwable $e) {
            // Truncate message if it's that huge HTML blob to keep logs clean
            $msg = $e->getMessage();
            if (strlen($msg) > 500 && str_contains($msg, '<html')) {
                $msg = 'HTML Response (Asp.Net Home Page) - Request likely hit root domain instead of API endpoint.';
            }

            Log::error('[MF] Enquiry exception', [
                'purchase_id' => $purchase->id,
                'transaction_id' => (string) $purchase->transaction_id,
                'exception' => get_class($e),
                'message' => $msg,
            ]);

            return null;
        }
    }

    public function syncFromEnquiry(PointPurchase $purchase, array $enquiry): void
    {
        $purchase->gateway_meta = $enquiry;

        $invoiceStatus = strtolower((string) data_get($enquiry, 'Data.InvoiceStatus', ''));
        $trxStatus = strtolower((string) data_get($enquiry, 'Data.InvoiceTransactions.0.TransactionStatus', ''));

        $mapped = match (true) {
            // "succss" is a known typo from some MyFatoorah upstream gateways (KNET)
            in_array($invoiceStatus, ['paid'], true) || in_array($trxStatus, ['success', 'succss'], true) => 'completed',
            in_array($invoiceStatus, ['pending'], true) => 'pending',
            in_array($invoiceStatus, ['expired', 'unpaid'], true) || in_array($trxStatus, ['failed'], true) => 'failed',
            default => $purchase->status,
        };

        // Save gateway reference fields if returned
        // This ensures the next enquiry uses the correct PaymentId instead of InvoiceId
        $paymentId = data_get($enquiry, 'Data.PaymentId') ?? data_get($enquiry, 'PaymentId');
        if ($paymentId) {
            $purchase->transaction_id = (string) $paymentId;
            $purchase->payment_gateway = $purchase->payment_gateway ?: 'myfatoorah';
        }

        $purchase->status = $mapped;
        $purchase->save();
    }
}
