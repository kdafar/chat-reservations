<?php

namespace App\Services\Payment;

use Exception;
use Illuminate\Support\Facades\Log;
use MyFatoorah\Library\API\Payment\MyFatoorahPayment;
use MyFatoorah\Library\API\Payment\MyFatoorahPaymentStatus;

class MyFatoorahService
{
    protected array $mfConfig;

    protected array $credentials;

    public function __construct(array $credentials)
    {
        $this->credentials = $credentials;

        $apiKey = (string) ($credentials['api_key'] ?? '');
        $mode = (string) ($credentials['mode'] ?? ''); // 'test' or 'live' preferred

        // Decide mode (do not over-trust heuristics; log what we picked)
        if ($mode !== '') {
            $isTest = ($mode === 'test');
        } else {
            // Heuristic fallback only if legacy rows don't have 'mode'
            $isTest = ! str_starts_with($apiKey, 'mf_live');
        }

        $country = (string) ($credentials['country_iso'] ?? 'KWT');

        $this->mfConfig = [
            'apiKey' => $apiKey,
            'isTest' => $isTest,
            'countryCode' => $country,
        ];

        Log::info('[MyFatoorah] init', [
            'is_test' => $isTest,
            'country' => $country,
            'api_key_present' => $apiKey !== '',
            'api_key_prefix' => $this->maskKeyPrefix($apiKey),
            'credentials_keys' => array_keys($credentials),
        ]);
    }

    public function createInvoice(array $data): string
    {
        if (empty($this->mfConfig['apiKey'])) {
            Log::warning('[MyFatoorah] missing api_key', [
                'country' => $this->mfConfig['countryCode'] ?? null,
                'is_test' => $this->mfConfig['isTest'] ?? null,
            ]);

            throw new Exception('MyFatoorah API Key is missing in Gateway Account settings.');
        }

        $accountId = $data['account_id'] ?? null;

        $rawPhone = (string) ($data['phone'] ?? '');
        $cleanPhone = $this->normalizeKuwaitPhone($rawPhone);

        $amount = (float) ($data['amount'] ?? 0);
        $refId = (string) ($data['ref_id'] ?? '');

        $curlData = [
            'CustomerName' => $data['name'] ?? 'Guest Patient',
            'InvoiceValue' => $amount,
            'DisplayCurrencyIso' => 'KWD',
            'CustomerMobile' => $cleanPhone,
            'CustomerReference' => $refId,
            'Language' => app()->getLocale() === 'ar' ? 'ar' : 'en',
            'NotificationOption' => 'LNK',
            'CallBackUrl' => route('bookings.payment.finalize', ['account_id' => $accountId]),
            'ErrorUrl' => route('bookings.payment.failed', ['account_id' => $accountId]),
        ];

        Log::info('[MyFatoorah] createInvoice request', [
            'account_id' => $accountId,
            'is_test' => $this->mfConfig['isTest'] ?? null,
            'country' => $this->mfConfig['countryCode'] ?? null,
            'api_key_prefix' => $this->maskKeyPrefix((string) $this->mfConfig['apiKey']),
            'amount' => $amount,
            'ref_id' => $refId,
            'raw_phone' => $rawPhone,
            'customer_mobile_sent' => $cleanPhone,
            'callback' => $curlData['CallBackUrl'],
            'error_url' => $curlData['ErrorUrl'],
        ]);

        try {
            $mfObj = new MyFatoorahPayment($this->mfConfig);

            $response = $mfObj->getInvoiceURL($curlData, 0);

            Log::info('[MyFatoorah] createInvoice response', [
                'account_id' => $accountId,
                'ref_id' => $refId,
                'has_invoice_url' => isset($response['invoiceURL']) && $response['invoiceURL'] !== '',
                'response_keys' => is_array($response) ? array_keys($response) : null,
            ]);

            $url = (string) ($response['invoiceURL'] ?? '');
            if ($url === '') {
                Log::error('[MyFatoorah] createInvoice missing invoiceURL', [
                    'account_id' => $accountId,
                    'ref_id' => $refId,
                    'response' => $response,
                ]);

                throw new Exception('MyFatoorah did not return invoiceURL.');
            }

            return $url;

        } catch (Exception $e) {
            Log::error('[MyFatoorah] SDK exception', [
                'message' => $e->getMessage(),
                'account_id' => $accountId,
                'ref_id' => $refId,
                'is_test' => $this->mfConfig['isTest'] ?? null,
                'country' => $this->mfConfig['countryCode'] ?? null,
                'api_key_prefix' => $this->maskKeyPrefix((string) $this->mfConfig['apiKey']),
            ]);

            throw new Exception('MyFatoorah Error: '.$e->getMessage());
        }
    }

    public function getPaymentStatus(string $paymentId)
    {
        if (empty($this->mfConfig['apiKey'])) {
            throw new Exception('MyFatoorah API Key is missing.');
        }

        Log::info('[MyFatoorah] getPaymentStatus request', [
            'payment_id' => $paymentId,
            'is_test' => $this->mfConfig['isTest'] ?? null,
            'country' => $this->mfConfig['countryCode'] ?? null,
            'api_key_prefix' => $this->maskKeyPrefix((string) $this->mfConfig['apiKey']),
        ]);

        $mfObj = new MyFatoorahPaymentStatus($this->mfConfig);

        return $mfObj->getPaymentStatus($paymentId, 'PaymentId');
    }

    private function normalizeKuwaitPhone(string $phone): string
    {
        // Keep digits only
        $digits = preg_replace('/[^0-9]/', '', $phone);

        // If already includes country code 965 (11 digits typical: 965 + 8 digits)
        if (str_starts_with($digits, '965') && strlen($digits) >= 11) {
            return $digits;
        }

        // If local Kuwait number (8 digits), prefix 965
        if (strlen($digits) === 8) {
            return '965'.$digits;
        }

        // Otherwise return what we have (still logged)
        return $digits;
    }

    private function maskKeyPrefix(string $apiKey): string
    {
        if ($apiKey === '') {
            return 'empty';
        }

        // show prefix + last 4 only
        $prefix = substr($apiKey, 0, 8);
        $last4 = substr($apiKey, -4);

        return $prefix.'...'.$last4;
    }
}
