<?php

namespace App\Services;

use Netflie\WhatsAppCloudApi\WhatsAppCloudApi;

class WhatsAppApiServiceFactory
{
    public function make(): WhatsAppApiService|WhatsAppApiServiceFake
    {
        $useFake = config('services.whatsapp.fake', app()->environment('local'));

        if ($useFake) {
            return new WhatsAppApiServiceFake;
        }

        $token = (string) config('services.whatsapp.access_token');
        $phoneId = (string) config('services.whatsapp.phone_id');

        if (empty($token) || empty($phoneId)) {
            \Log::critical('WhatsApp API credentials missing.', ['has_token' => ! empty($token), 'has_phone_id' => ! empty($phoneId)]);

            throw new \Exception('WhatsApp API credentials are not configured.');
        }

        $client = new WhatsAppCloudApi([
            'from_phone_number_id' => $phoneId,
            'access_token' => $token,
        ]);

        return new WhatsAppApiService($client);
    }
}
