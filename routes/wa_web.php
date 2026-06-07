<?php

use App\Wa\Http\Controllers\WhatsAppManagementController;
use App\Wa\Http\Controllers\WhatsAppPointPurchaseController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| WhatsApp Module Web routes
|--------------------------------------------------------------------------
| Mounted under the /wa prefix by App\Wa\Providers\WaServiceProvider.
*/

// Point purchase (MyFatoorah) callbacks
Route::get('/points/callback', [WhatsAppPointPurchaseController::class, 'callback'])->name('wa.points.callback');
Route::get('/points/error', [WhatsAppPointPurchaseController::class, 'error'])->name('wa.points.error');
Route::middleware('auth')->get('/points/result/{purchase}', [WhatsAppPointPurchaseController::class, 'result'])
    ->name('wa.points.result');

// Admin tools (test send / template creation)
Route::middleware('auth')
    ->prefix('admin/whatsapp')
    ->name('wa.tools.')
    ->group(function () {
        Route::post('send-test', [WhatsAppManagementController::class, 'sendTestMessage'])->name('send-test');
        Route::post('create-template', [WhatsAppManagementController::class, 'createTemplate'])->name('create-template');
    });
