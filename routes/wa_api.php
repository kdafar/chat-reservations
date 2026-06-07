<?php

use App\Wa\Http\Controllers\Api\DirectBotController;
use App\Wa\Http\Controllers\Api\WhatsappRelayController;
use App\Wa\Http\Controllers\MetaWhatsAppController;
use App\Wa\Http\Controllers\Webhooks\WhatsAppCloudWebhookController;
use App\Wa\Http\Middleware\VerifyWhatsAppSignature;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| WhatsApp Module API routes
|--------------------------------------------------------------------------
| Mounted under the /api/wa prefix by App\Wa\Providers\WaServiceProvider so
| they never collide with the clinic's own /api/whatsapp/* routes.
*/

// Direct bot (legacy flow engine)
Route::post('/whatsapp/webhook', [DirectBotController::class, 'handleWebhook'])
    ->middleware(VerifyWhatsAppSignature::class);
Route::get('/whatsapp/webhook', [DirectBotController::class, 'verifyWebhook']);
Route::post('/whatsapp/flow-data', [DirectBotController::class, 'handleDataExchange']);

// Relay send
Route::post('/whatsapp/send', [WhatsappRelayController::class, 'send']);

// Meta embedded signup / token exchange
Route::match(['GET', 'POST'], '/meta/callback', [MetaWhatsAppController::class, 'exchangeToken'])
    ->name('wa.meta.exchange-token');
Route::post('/meta/session-info', [MetaWhatsAppController::class, 'sessionInfo'])
    ->name('wa.meta.session-info');

// Multi-account "core" Cloud API webhook
Route::prefix('webhooks/whatsapp-cloud')->group(function () {
    Route::get('/', [WhatsAppCloudWebhookController::class, 'verify']);
    Route::post('/', [WhatsAppCloudWebhookController::class, 'handle']);
});
