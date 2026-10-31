<?php

use App\Http\Controllers\Api\TicketVerificationController;
use App\Http\Controllers\Api\TwilioWhatsappWebhookController;
use App\Http\Controllers\Api\WhatsappWebhookController;
use App\Http\Middleware\ThrottleWhatsappWebhook;
use Illuminate\Support\Facades\Route;

Route::get('/webhooks/whatsapp', [WhatsappWebhookController::class, 'verify']);
Route::post('/webhooks/whatsapp', [WhatsappWebhookController::class, 'handle'])
    ->middleware(ThrottleWhatsappWebhook::class);
Route::post('/webhooks/twilio/whatsapp', [TwilioWhatsappWebhookController::class, 'inbound'])
    ->middleware(ThrottleWhatsappWebhook::class)
    ->name('webhooks.twilio.whatsapp');
Route::post('/webhooks/twilio/whatsapp/status', [TwilioWhatsappWebhookController::class, 'status'])
    ->middleware(ThrottleWhatsappWebhook::class)
    ->name('webhooks.twilio.whatsapp.status');
Route::get('/tickets/{code}/verify', [TicketVerificationController::class, 'verifyByCode']);
