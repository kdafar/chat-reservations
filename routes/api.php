<?php

use App\Http\Controllers\Api\AutomationTriggerController;
use App\Http\Controllers\Api\CartEvaluateController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\LandingPageController;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\PromotionsController;
use App\Http\Controllers\Api\WhatsAppFlowEndpointController;
use App\Http\Controllers\Api\WhatsAppWebhookController;
use App\Http\Controllers\BookingCheckInController;
use App\Http\Controllers\Payments\GatewayPickController;
use App\Http\Controllers\Payments\PaymentWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/flows/booking', WhatsAppFlowEndpointController::class);
Route::get('/flows/health', fn () => response('ok', 200));

Route::match(['get', 'post'], '/whatsapp/webhook', [WhatsAppWebhookController::class, 'handle']);

Route::post('/automations/trigger', [AutomationTriggerController::class, 'handle']);

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

use App\Services\WhatsAppMessageHandler;

Route::post('/wa/test-webhook', function (Request $req, WhatsAppMessageHandler $h) {
    $h->process($req->all());

    return response()->json(['ok' => true]);
});

Route::get('/wa/test-webhook', fn () => response('ok', 200));

Route::get('/health', HealthController::class);
Route::get('/pages/{slug}', [LandingPageController::class, 'show']);
Route::post('/leads', [LeadController::class, 'store']);

Route::get('/payments/pick', GatewayPickController::class);
Route::post('/payments/webhook/{driver}', PaymentWebhookController::class);

Route::prefix('locations')->group(function () {
    Route::get('/states', [LocationController::class, 'states']);
    Route::get('/cities', [LocationController::class, 'cities']);   // ?state_id=#
    Route::get('/blocks', [LocationController::class, 'blocks']);   // ?city_id=#
});

Route::get('/offers', [PromotionsController::class, 'index'])->name('api.offers'); // ?branch_id=&service_id=&partner_id=
Route::post('/cart/evaluate', CartEvaluateController::class);

Route::post('/bookings/check-in', BookingCheckInController::class)->middleware('auth:sanctum');
