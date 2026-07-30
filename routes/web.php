<?php

use App\Http\Controllers\AdminLocaleController;
use App\Http\Controllers\PublicLandingController;
use App\Http\Controllers\RaffleNumberPickerController;
use App\Http\Controllers\Api\TicketVerificationController;
use Illuminate\Support\Facades\Route;

Route::get('/tickets/{verificationToken}', [TicketVerificationController::class, 'verifyByToken'])
    ->name('tickets.show');

Route::get('/raffles/{raffle:slug}/number-picker', [RaffleNumberPickerController::class, 'show'])
    ->name('raffles.number-picker');
Route::post('/raffles/{raffle:slug}/number-picker/intents', [RaffleNumberPickerController::class, 'store'])
    ->name('raffles.number-picker.intents');

Route::get('/', PublicLandingController::class)
    ->name('landing');

Route::middleware('auth')->post('/admin/preferences/locale', AdminLocaleController::class)
    ->name('admin.locale.update');
