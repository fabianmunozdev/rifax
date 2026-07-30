<?php

use App\Http\Controllers\Api\TicketVerificationController;
use App\Http\Controllers\Api\WhatsappWebhookController;
use App\Http\Middleware\ThrottleWhatsappWebhook;
use Illuminate\Support\Facades\Route;

Route::get('/webhooks/whatsapp', [WhatsappWebhookController::class, 'verify']);
Route::post('/webhooks/whatsapp', [WhatsappWebhookController::class, 'handle'])
    ->middleware(ThrottleWhatsappWebhook::class);
Route::get('/tickets/{code}/verify', [TicketVerificationController::class, 'verifyByCode']);
