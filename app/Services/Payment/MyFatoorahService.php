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
            'CustomerReference' => $refId,
            'Language' => app()->getLocale() === 'ar' ? 'ar' : 'en',
            'NotificationOption' => 'LNK',
            // HMAC the account_id so the callback can verify it wasn't tampered with.
            // We can't use Laravel's signedRoute here because MyFatoorah appends paymentId/Id
            // to the query string, which breaks the signed-route signature check.
            // Callers may pass their own callback/error URLs (e.g. the visit-balance
            // flow points at the visit finalizer); bookings fall back to the
            // booking routes for backward compatibility.
            'CallBackUrl' => $data['callback_url'] ?? route('bookings.payment.finalize', [
                'account_id' => $accountId,
                'sig' => $this->accountSig((string) $accountId),
            ]),
            'ErrorUrl' => $data['error_url'] ?? route('bookings.payment.failed', [
                'account_id' => $accountId,
                'sig' => $this->accountSig((string) $accountId),
            ]),
        ];

        // MyFatoorah caps CustomerMobile at 11 chars and rejects invalid ones.
        // Only send it when it normalizes to a sane Kuwait length (8 local, or
        // 11 with the 965 code) — otherwise omit it; the invoice link still
        // generates, we just don't trigger MyFatoorah's own SMS/LNK notification.
        if (in_array(strlen($cleanPhone), [8, 11], true)) {
            $curlData['CustomerMobile'] = $cleanPhone;
        }

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

    /**
     * Stable HMAC of the gateway account id used on callback URLs.
     * Lets the callback verify the account_id query param wasn't tampered with.
     */
    public static function accountSig(string $accountId): string
    {
        return hash_hmac('sha256', 'mf:'.$accountId, (string) config('app.key'));
    }

    private function normalizeKuwaitPhone(string $phone): string
    {
        // Keep digits only.
        $digits = preg_replace('/[^0-9]/', '', $phone);
        if ($digits === '') {
            return '';
        }

        // Drop a leading 965 country code, then keep the last 8 subscriber
        // digits (Kuwait mobiles are 8 digits). This guarantees we never emit
        // more than 11 chars, which MyFatoorah rejects.
        if (str_starts_with($digits, '965')) {
            $digits = substr($digits, 3);
        }
        if (strlen($digits) > 8) {
            $digits = substr($digits, -8);
        }

        // A clean 8-digit local number → prefix the country code (965 + 8 = 11).
        // Anything shorter is malformed; return the digits and let the caller
        // decide whether to send them.
        return strlen($digits) === 8 ? '965'.$digits : $digits;
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
