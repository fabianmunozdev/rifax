<?php

use App\Http\Controllers\Admin\PaymentProofController;
use App\Http\Controllers\AdminLocaleController;
use App\Http\Controllers\LegalPageController;
use App\Models\LegalPage;
use App\Http\Controllers\PublicLandingController;
use App\Http\Controllers\RaffleNumberPickerController;
use App\Http\Controllers\Api\TicketVerificationController;
use App\Http\Controllers\TicketPrototypeController;
use Illuminate\Support\Facades\Route;

if (app()->environment(['local', 'staging', 'development'])) {
    Route::get('/prototype/ticket-premium', [TicketPrototypeController::class, 'show'])
        ->name('tickets.prototype');
}

// Rutas públicas en español (SEO para público hispano).
Route::get('/boleto/{verificationToken}', [TicketVerificationController::class, 'verifyByToken'])
    ->name('tickets.show');

Route::get('/rifa/{raffle:slug}', [RaffleNumberPickerController::class, 'show'])
    ->name('raffles.number-picker');
Route::get('/rifa/{raffle:slug}/numeros', [RaffleNumberPickerController::class, 'numbers'])
    ->name('raffles.number-picker.numbers');
Route::post('/rifa/{raffle:slug}/apartar', [RaffleNumberPickerController::class, 'store'])
    ->name('raffles.number-picker.intents');
Route::post('/rifa/{raffle:slug}/confirmar', [RaffleNumberPickerController::class, 'confirm'])
    ->name('raffles.number-picker.confirm');
Route::get('/rifa/{raffle:slug}/confirmada', [RaffleNumberPickerController::class, 'confirmed'])
    ->name('raffles.number-picker.confirmed');

// Direcciones anteriores: redirección permanente para no romper enlaces ya compartidos ni QR impresos.
Route::get('/tickets/{verificationToken}', fn (string $verificationToken) => redirect()->route('tickets.show', $verificationToken, 301));
Route::get('/raffles/{slug}/number-picker', fn (\Illuminate\Http\Request $request, string $slug) => redirect(
    route('raffles.number-picker', $slug).($request->getQueryString() ? '?'.$request->getQueryString() : ''),
    301,
));
Route::get('/raffles/{slug}/number-picker/confirmed', fn (\Illuminate\Http\Request $request, string $slug) => redirect(
    route('raffles.number-picker.confirmed', $slug).($request->getQueryString() ? '?'.$request->getQueryString() : ''),
    301,
));

Route::get('/terminos-y-condiciones', fn () => app(LegalPageController::class)(LegalPage::TERMS))
    ->name('legal.terms');
Route::get('/politica-de-privacidad', fn () => app(LegalPageController::class)(LegalPage::PRIVACY))
    ->name('legal.privacy');

Route::get('/', PublicLandingController::class)
    ->name('landing');

Route::middleware('auth')->post('/admin/preferences/locale', AdminLocaleController::class)
    ->name('admin.locale.update');

// Comprobantes de pago: privados, solo para usuarios del panel con permiso de ver pagos.
Route::get('/admin/comprobantes/{paymentProof}', [PaymentProofController::class, 'show'])
    ->name('admin.payment-proofs.show');
