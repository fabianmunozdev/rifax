<?php

use App\Actions\Purchases\ExpireReservationAction;
use App\Models\Purchase;
use App\Models\RaffleNumber;
use App\Models\Reservation;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

app()->booted(function (): void {
    if (! app()->bound(Schedule::class)) {
        return;
    }

    /** @var Schedule $schedule */
    $schedule = app(Schedule::class);

    $schedule->call(function (ExpireReservationAction $expireReservationAction): void {
        try {
            $dueReservations = Reservation::query()
                ->where('status', 'active')
                ->where('expires_at', '<', now())
                ->cursor();

            $expired = 0;

            foreach ($dueReservations as $reservation) {
                try {
                    $expireReservationAction->execute($reservation);
                    $expired++;
                } catch (\Throwable $e) {
                    Log::warning('Scheduler ExpireReservation: error individual reservation.', [
                        'reservation_id' => $reservation->id,
                        'purchase_id' => $reservation->purchase_id,
                        'message' => $e->getMessage(),
                    ]);
                }
            }

            if ($expired > 0) {
                Log::info('Scheduler ExpireReservation executed.', ['expired_count' => $expired]);
            }
        } catch (\Throwable $e) {
            Log::error('Scheduler ExpireReservation fatal error.', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
        }
    })
        ->name('rifax:expire-reservations')
        ->everyMinute()
        ->withoutOverlapping(5)
        ->onOneServer();

    $schedule->call(function (): void {
        try {
            DB::transaction(function (): void {
                $orphanPurchases = Purchase::query()
                    ->whereIn('status', ['reserved'])
                    ->whereNotNull('reserved_until')
                    ->where('reserved_until', '<', now()->subMinutes(15))
                    ->whereDoesntHave('reservation', fn ($q) => $q->where('status', 'active'))
                    ->lockForUpdate()
                    ->get(['id', 'status', 'reserved_until']);

                $released = 0;
                foreach ($orphanPurchases as $orphan) {
                    $orphan->forceFill(['status' => 'cancelled', 'reserved_until' => null, 'cancelled_at' => now()])->save();
                    $released += RaffleNumber::query()
                        ->whereIn('id', fn ($sub) => $sub->select('raffle_number_id')->from('purchase_numbers')->where('purchase_id', $orphan->id))
                        ->where('status', 'reserved')
                        ->update(['status' => 'available', 'reserved_until' => null]);
                }

                if ($released > 0 || $orphanPurchases->count() > 0) {
                    Log::info('Scheduler Cleanup orphaned reserved purchases done.', [
                        'orphan_count' => $orphanPurchases->count(),
                        'released_raffle_numbers' => $released,
                    ]);
                }
            });
        } catch (\Throwable $e) {
            Log::error('Scheduler cleanup orphans fatal error.', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
        }
    })
        ->name('rifax:cleanup-reservation-orphans')
        ->everyFiveMinutes()
        ->withoutOverlapping(10)
        ->onOneServer();
});
